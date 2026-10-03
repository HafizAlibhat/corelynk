<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Read-only data-integrity guard for the sales lifecycle.
 *
 * Run: php spark integrity:check
 * Exits non-zero if any invariant is violated, so it can gate a deploy.
 *
 * The round-trip check is the regression test for the 2026-08 incident: writing
 * a status outside the ENUM used to succeed silently and store '', which dropped
 * every converted quotation out of the document list.
 */
class IntegrityCheck extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'integrity:check';
    protected $description = 'Verify sales/accounting data invariants (read-only).';

    private int $failures = 0;

    public function run(array $params)
    {
        $db = \Config\Database::connect();

        // 1. ENUM round-trip: an out-of-range status must NOT silently become ''.
        $db->query("CREATE TEMPORARY TABLE _ic_probe LIKE quotations");
        $db->query("INSERT INTO _ic_probe (quote_number, customer_id, status) VALUES ('_IC_PROBE_', 0, 'draft')");
        $db->query("UPDATE _ic_probe SET status = 'converted' WHERE quote_number = '_IC_PROBE_'");
        $stored = $db->query("SELECT status FROM _ic_probe WHERE quote_number = '_IC_PROBE_'")->getRowArray()['status'] ?? null;
        $db->query("DROP TEMPORARY TABLE _ic_probe");
        $this->assert($stored === 'converted', "quotations.status accepts 'converted'", "got '" . $stored . "' - ENUM is missing the value and truncated it");

        // 2. No document may carry a blank status.
        $this->assertCount($db, 'quotations with blank status',
            "SELECT COUNT(*) c FROM quotations WHERE deleted_at IS NULL AND status = ''");
        $this->assertCount($db, 'sales orders with blank status',
            "SELECT COUNT(*) c FROM sales_orders WHERE status = ''");

        // 3. No orphaned document lines.
        $this->assertCount($db, 'orphaned quotation_lines',
            "SELECT COUNT(*) c FROM quotation_lines l LEFT JOIN quotations q ON q.id = l.quotation_id WHERE q.id IS NULL");
        $this->assertCount($db, 'orphaned sales_order_lines',
            "SELECT COUNT(*) c FROM sales_order_lines l LEFT JOIN sales_orders s ON s.id = l.sales_order_id WHERE s.id IS NULL");

        // 4. No document line may reference a deleted product.
        $this->assertCount($db, 'quotation_lines pointing at a missing product',
            "SELECT COUNT(*) c FROM quotation_lines l LEFT JOIN products p ON p.id = l.product_id
             WHERE l.product_id > 0 AND p.id IS NULL");
        $this->assertCount($db, 'sales_order_lines pointing at a missing product',
            "SELECT COUNT(*) c FROM sales_order_lines l LEFT JOIN products p ON p.id = l.product_id
             WHERE l.product_id > 0 AND p.id IS NULL");

        // 5. Conversion links must resolve.
        $this->assertCount($db, 'quotations linked to a missing sales order',
            "SELECT COUNT(*) c FROM quotations q LEFT JOIN sales_orders s ON s.id = q.converted_to_sales_order_id
             WHERE q.converted_to_sales_order_id IS NOT NULL AND s.id IS NULL");

        // 6. Document numbers stay unique.
        $this->assertCount($db, 'duplicate quote_number',
            "SELECT COUNT(*) c FROM (SELECT quote_number FROM quotations GROUP BY quote_number HAVING COUNT(*) > 1) x");
        $this->assertCount($db, 'duplicate order_number',
            "SELECT COUNT(*) c FROM (SELECT order_number FROM sales_orders GROUP BY order_number HAVING COUNT(*) > 1) x");

        // 7. Every journal balances.
        $this->assertCount($db, 'unbalanced journals',
            "SELECT COUNT(*) c FROM (SELECT je.id FROM journal_entries je JOIN journal_lines jl ON jl.entry_id = je.id
             GROUP BY je.id HAVING ABS(SUM(jl.debit) - SUM(jl.credit)) > 0.005) x");
        $this->assertCount($db, 'journal_lines with no parent entry',
            "SELECT COUNT(*) c FROM journal_lines jl LEFT JOIN journal_entries je ON je.id = jl.entry_id WHERE je.id IS NULL");

        // 8. A journal with a blank source_type is untraceable to its document, is
        //    invisible to VendorLedger, and escapes the AccountingJournals lock rule.
        $this->assertCount($db, 'journals with a blank source_type',
            "SELECT COUNT(*) c FROM journal_entries WHERE source_type = ''");

        // 9. Denormalised header totals must agree with the lines.
        $this->assertCount($db, 'journals whose header totals disagree with their lines',
            "SELECT COUNT(*) c FROM journal_entries je
             JOIN (SELECT entry_id, SUM(debit) d, SUM(credit) cr FROM journal_lines GROUP BY entry_id) x
               ON x.entry_id = je.id
             WHERE ABS(je.total_debits - x.d) > 0.005 OR ABS(je.total_credits - x.cr) > 0.005");

        CLI::newLine();
        if ($this->failures > 0) {
            CLI::error("{$this->failures} integrity check(s) FAILED.");
            return EXIT_ERROR;
        }
        CLI::write('All integrity checks passed.', 'green');
        return EXIT_SUCCESS;
    }

    /** Assert the query returns zero rows. */
    private function assertCount($db, string $label, string $sql): void
    {
        $c = (int) ($db->query($sql)->getRowArray()['c'] ?? -1);
        $this->assert($c === 0, "no {$label}", "found {$c}");
    }

    private function assert(bool $ok, string $label, string $detail = ''): void
    {
        if ($ok) {
            CLI::write('  PASS  ' . $label, 'green');
            return;
        }
        $this->failures++;
        CLI::write('  FAIL  ' . $label . ($detail !== '' ? ' -- ' . $detail : ''), 'red');
    }
}
