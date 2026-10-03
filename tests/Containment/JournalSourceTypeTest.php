<?php

namespace Tests\Containment;

use Tests\Support\ContainmentTestCase;

/**
 * TEST J -- a system-generated journal stores its source_type correctly.
 *
 * journal_entries.source_type was ENUM('invoice','payment','cheque','credit_note','manual')
 * while the application posts five further values. All five were silently stored as '',
 * which broke two financial controls at once:
 *
 *   a) VendorLedger filters on source_type IN ('vendor_bill','vendor_payment').
 *      Zero rows matched, so the vendor ledger's journal section has always been blank
 *      for every vendor -- and it fails silently, reading as "no activity" not as an error.
 *
 *   b) AccountingJournals locks posted documents with
 *          !empty($je['source_type']) && $je['source_type'] !== 'manual'
 *      !empty('') is false, so all 24 system-generated journals -- including entries of
 *      7,000,000 and 1,300,000 -- were editable as if they were hand-keyed.
 *
 * This test asserts the value persists AND that both controls engage as a result.
 */
final class JournalSourceTypeTest extends ContainmentTestCase
{
    /** The five values that were never ENUM members. */
    private const SYSTEM_POSTED_TYPES = [
        'vendor_bill',
        'vendor_payment',
        'vendor_advance_application',
        'vendor_bill_advance_application',
        'mobile_expense',
    ];

    private function insertJournal(string $sourceType, int $sourceId = 1): int
    {
        $this->db->table('journal_entries')->insert([
            'entry_date'    => '2026-01-15',
            'memo'          => 'TEST journal ' . $sourceType,
            'source_type'   => $sourceType,
            'source_id'     => $sourceId,
            'total_debits'  => 1000.00,
            'total_credits' => 1000.00,
        ]);

        return (int) $this->db->insertID();
    }

    private function storedSourceType(int $id): string
    {
        $row = $this->db->table('journal_entries')->select('source_type')->where('id', $id)->get()->getRowArray();

        return (string) ($row['source_type'] ?? '__MISSING__');
    }

    public function testEverySystemPostedSourceTypePersists(): void
    {
        foreach (self::SYSTEM_POSTED_TYPES as $type) {
            $id = $this->insertJournal($type);

            self::assertSame(
                $type,
                $this->storedSourceType($id),
                "Journal source_type '{$type}' was silently blanked. The vendor ledger will be "
                . 'empty and the posted-document edit lock will not hold.'
            );
        }
    }

    /**
     * Control (a): the vendor ledger predicate must find the entry.
     */
    public function testVendorLedgerPredicateFindsSystemJournals(): void
    {
        $this->insertJournal('vendor_bill', 4242);
        $this->insertJournal('vendor_payment', 4243);

        $found = $this->db->table('journal_entries')
            ->whereIn('source_type', ['vendor_bill', 'vendor_payment'])
            ->whereIn('source_id', [4242, 4243])
            ->countAllResults();

        self::assertSame(
            2,
            $found,
            'The vendor ledger cannot see system journals, so it renders blank and reports '
            . 'no activity rather than an error.'
        );
    }

    /**
     * Control (b): the edit lock must engage, exactly as AccountingJournals expresses it.
     */
    public function testPostedJournalIsLockedAgainstEditing(): void
    {
        $id  = $this->insertJournal('vendor_bill', 4244);
        $row = $this->db->table('journal_entries')->where('id', $id)->get()->getRowArray();

        // The application's own lock expression.
        $locked = ! empty($row['source_type'] ?? '') && ($row['source_type'] ?? '') !== 'manual';

        self::assertTrue(
            $locked,
            'A system-generated journal is editable as if it were a manual entry. '
            . 'The guarantee that posted documents cannot be altered after the fact does not hold.'
        );
    }

    /**
     * A genuine manual entry must stay editable -- the lock must not over-apply.
     */
    public function testManualJournalRemainsEditable(): void
    {
        $id  = $this->insertJournal('manual', 4245);
        $row = $this->db->table('journal_entries')->where('id', $id)->get()->getRowArray();

        $locked = ! empty($row['source_type'] ?? '') && ($row['source_type'] ?? '') !== 'manual';

        self::assertFalse($locked, 'A manual journal entry was incorrectly locked.');
    }

    /**
     * Negative control: a blanked source_type must defeat the lock. If this ever fails,
     * the lock test above is not measuring what it claims to.
     */
    public function testBlankSourceTypeDemonstrablyDefeatsTheLock(): void
    {
        $id = $this->insertJournal('vendor_bill', 4246);
        $this->db->query('UPDATE journal_entries SET source_type = ? WHERE id = ?', ['', $id]);

        $row    = $this->db->table('journal_entries')->where('id', $id)->get()->getRowArray();
        $locked = ! empty($row['source_type'] ?? '') && ($row['source_type'] ?? '') !== 'manual';

        self::assertFalse($locked, 'Expected a blank source_type to defeat the edit lock.');
    }
}
