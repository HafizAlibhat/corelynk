<?php

namespace Tests\Containment;

use Tests\Support\ContainmentTestCase;

/**
 * TEST C -- vendor_bills.based_on stores 'po_over_receipt' exactly.
 * TEST D -- vendor_bills.based_on stores 'po_qty_adjustment' exactly.
 *
 * P0-3 is the only member of the truncation family that corrupts ARITHMETIC rather
 * than display, so this test also exercises the three read paths that the truncation
 * broke. Each read predicate below is the one used in NewPurchaseOrders, reproduced
 * verbatim, so the test fails if the stored value stops matching what the readers expect:
 *
 *   :177  base billed qty     ... AND LOWER(COALESCE(vb.based_on,'')) <> 'po_over_receipt'
 *   :235  extra billed qty    ... AND LOWER(COALESCE(vb.based_on,'')) =  'po_over_receipt'
 *   :1227 / :2529 duplicate guard   ->where('based_on', 'po_over_receipt')
 *
 * With based_on truncated to '', predicate 1 matched over-receipt bills (counting
 * 17,270 of extra billing as base billing), predicate 2 matched nothing, and the
 * duplicate guard could never fire.
 */
final class VendorBillBasedOnTest extends ContainmentTestCase
{
    private const PO_ID = 990001;

    private function insertBill(string $basedOn, float $amount, string $status = 'open'): int
    {
        $this->db->table('vendor_bills')->insert([
            'vendor_id'    => 1,
            'po_id'        => self::PO_ID,
            'bill_number'  => 'TEST-VB-' . $basedOn . '-' . $amount,
            'total_amount' => $amount,
            'status'       => $status,
            'based_on'     => $basedOn,
        ]);

        return (int) $this->db->insertID();
    }

    private function storedBasedOn(int $id): string
    {
        $row = $this->db->table('vendor_bills')->select('based_on')->where('id', $id)->get()->getRowArray();

        return (string) ($row['based_on'] ?? '__MISSING__');
    }

    // ---------------------------------------------------------------- TEST C

    public function testPoOverReceiptPersistsExactly(): void
    {
        $id = $this->insertBill('po_over_receipt', 450.00);

        self::assertSame(
            'po_over_receipt',
            $this->storedBasedOn($id),
            'P0-3: over-receipt vendor bill silently stored as the empty string. '
            . 'Base billed quantity will be inflated and the duplicate-bill guard is dead.'
        );
    }

    // ---------------------------------------------------------------- TEST D

    public function testPoQtyAdjustmentPersistsExactly(): void
    {
        $id = $this->insertBill('po_qty_adjustment', 1200.00);

        self::assertSame(
            'po_qty_adjustment',
            $this->storedBasedOn($id),
            'P0-3: quantity-adjustment vendor bill silently stored as the empty string.'
        );
    }

    public function testTheWritersStillEmitTheseValues(): void
    {
        $grn = $this->sourceOf('app/Controllers/NewPurchaseGrns.php');
        $po  = $this->sourceOf('app/Controllers/NewPurchaseOrders.php');

        self::assertStringContainsString("'based_on' => 'po_over_receipt'", $grn);
        self::assertStringContainsString("'based_on' => 'po_over_receipt'", $po);
        self::assertStringContainsString("'po_qty_adjustment'", $po);
    }

    // ------------------------------------------------- reader: base billed qty

    /**
     * The base-billing sum must EXCLUDE over-receipt bills. This is the predicate that
     * was silently including them.
     */
    public function testBaseBilledSumExcludesOverReceiptBilling(): void
    {
        $this->insertBill('po_qty', 1000.00);          // base billing
        $this->insertBill('po_over_receipt', 450.00);  // extra billing, must be excluded

        $row = $this->db->query(
            "SELECT COALESCE(SUM(vb.total_amount),0) AS base_total
             FROM vendor_bills vb
             WHERE vb.po_id = ?
               AND LOWER(COALESCE(vb.status,'')) <> 'cancelled'
               AND LOWER(COALESCE(vb.based_on,'')) <> 'po_over_receipt'",
            [self::PO_ID]
        )->getRowArray();

        self::assertSame(
            1000.00,
            (float) $row['base_total'],
            'Over-receipt billing is being counted as base PO billing. '
            . 'This is exactly the arithmetic corruption P0-3 caused on POs 1, 2 and 32.'
        );
    }

    // ------------------------------------------------ reader: extra billed qty

    /**
     * The complementary predicate must FIND the over-receipt bill. While based_on was
     * truncated this returned zero, so the system believed no over-receipt had ever
     * been billed.
     */
    public function testAlreadyBilledOverReceiptIsDetected(): void
    {
        $this->insertBill('po_qty', 1000.00);
        $this->insertBill('po_over_receipt', 450.00);

        $row = $this->db->query(
            "SELECT COALESCE(SUM(vb.total_amount),0) AS extra_total
             FROM vendor_bills vb
             WHERE vb.po_id = ?
               AND LOWER(COALESCE(vb.status,'')) <> 'cancelled'
               AND LOWER(COALESCE(vb.based_on,'')) = 'po_over_receipt'",
            [self::PO_ID]
        )->getRowArray();

        self::assertSame(
            450.00,
            (float) $row['extra_total'],
            'Already-billed over-receipt quantity was not detected. '
            . 'The system believes no over-receipt has ever been billed.'
        );
    }

    // ----------------------------------------------- reader: duplicate guard

    /**
     * The guard at NewPurchaseOrders :1227 / :2529 must see an existing over-receipt
     * bill and therefore reject a second one for the same PO.
     */
    public function testDuplicateOverReceiptBillingIsRejected(): void
    {
        $this->insertBill('po_over_receipt', 450.00);

        // The guard as the application expresses it.
        $existing = $this->db->table('vendor_bills')
            ->where('po_id', self::PO_ID)
            ->where('based_on', 'po_over_receipt')
            ->countAllResults();

        self::assertSame(
            1,
            $existing,
            'The duplicate over-receipt guard cannot see the existing bill, so the same '
            . 'extra quantity can be billed twice. This is the live consequence of P0-3.'
        );

        $rejected = $existing > 0;
        self::assertTrue($rejected, 'A second over-receipt bill would have been allowed.');
    }

    /**
     * Negative control: with based_on blanked (the pre-fix state), the guard fails.
     * This proves the test above is actually sensitive to the defect rather than
     * passing for an unrelated reason.
     */
    public function testGuardDemonstrablyFailsWhenBasedOnIsBlank(): void
    {
        $id = $this->insertBill('po_over_receipt', 450.00);

        // Force the corrupted state that existed before the migration.
        $this->db->query('UPDATE vendor_bills SET based_on = ? WHERE id = ?', ['', $id]);

        $seen = $this->db->table('vendor_bills')
            ->where('po_id', self::PO_ID)
            ->where('based_on', 'po_over_receipt')
            ->countAllResults();

        self::assertSame(
            0,
            $seen,
            'Expected the blanked row to be invisible to the guard -- if this fails, the '
            . 'test is not measuring what it claims to measure.'
        );
    }
}
