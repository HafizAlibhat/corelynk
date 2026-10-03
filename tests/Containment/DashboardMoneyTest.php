<?php

namespace Tests\Containment;

use App\Controllers\Corelynk;
use ReflectionMethod;
use Tests\Support\ContainmentTestCase;

/**
 * The two money tiles on the home dashboard.
 *
 * Both used to answer wrongly: sales read `sales_cache` (the Odoo mirror, which
 * stopped being fed, so every natively created sale showed as zero) and
 * receivables counted draft invoices, which have nothing in Accounts Receivable.
 * These tests pin both to the invoices this system actually issues.
 */
final class DashboardMoneyTest extends ContainmentTestCase
{
    private function call(string $method, array $args)
    {
        $ref = new ReflectionMethod(Corelynk::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(new Corelynk(), $args);
    }

    /** @return int inserted invoice id */
    private function invoice(string $status, float $total, string $currency = 'USD', string $date = '2026-09-10'): int
    {
        $this->db->table('customer_invoices')->insert([
            'public_id'      => bin2hex(random_bytes(8)),
            'invoice_number' => 'TEST-' . bin2hex(random_bytes(4)),
            'customer_id'    => 999999,
            'issue_date'     => $date,
            'currency_code'  => $currency,
            'subtotal'       => $total,
            'tax_total'      => 0,
            'total_amount'   => $total,
            'status'         => $status,
        ]);

        return (int) $this->db->insertID();
    }

    public function testSalesCountIssuedInvoicesAndIgnoreDrafts(): void
    {
        $this->invoice('posted', 100.00);
        $this->invoice('paid', 25.50);
        $this->invoice('draft', 999.00);        // never issued
        $this->invoice('cancelled', 777.00);
        $this->invoice('posted', 40.00, 'PKR'); // a second currency stays separate

        $totals = $this->call('salesByCurrency', [$this->db, 'USD', '2026-09-01', '2026-09-30']);

        self::assertSame(125.50, $totals['USD'] ?? null, 'posted + paid, drafts and cancellations excluded');
        self::assertSame(40.00, $totals['PKR'] ?? null, 'currencies are never added together');
    }

    public function testSalesRespectThePeriod(): void
    {
        $this->invoice('posted', 100.00, 'USD', '2026-08-31');

        $totals = $this->call('salesByCurrency', [$this->db, 'USD', '2026-09-01', '2026-09-30']);

        self::assertSame([], $totals, 'an invoice from last month is not this month sales');
    }

    public function testReceivablesExcludeDraftsAndSettledInvoices(): void
    {
        $open = $this->invoice('posted', 200.00);
        $this->invoice('draft', 500.00);   // nothing is posted to AR for a draft
        $paid = $this->invoice('posted', 80.00);

        $this->db->table('customer_payments')->insert([
            'customer_id'     => 999999,
            'payment_date'    => '2026-09-11',
            'currency_code'   => 'USD',
            'amount'          => 80.00,
            'posted_entry_id' => 424242,
        ]);
        $paymentId = (int) $this->db->insertID();
        $this->db->table('customer_payment_allocations')->insert([
            'payment_id'       => $paymentId,
            'invoice_id'       => $paid,
            'allocated_amount' => 80.00,
        ]);

        $totals = $this->call('receivablesByCurrency', [$this->db, 'USD']);

        self::assertSame(200.00, $totals['USD'] ?? null, 'only the unsettled posted invoice is receivable');
        self::assertTrue($open > 0 && $paid > 0);
    }

    public function testAPartiallyPaidInvoiceLeavesOnlyItsBalanceReceivable(): void
    {
        $invoiceId = $this->invoice('partially_paid', 300.00);

        $this->db->table('customer_payments')->insert([
            'customer_id'     => 999999,
            'payment_date'    => '2026-09-11',
            'currency_code'   => 'USD',
            'amount'          => 120.00,
            'posted_entry_id' => 424243,
        ]);
        $paymentId = (int) $this->db->insertID();
        $this->db->table('customer_payment_allocations')->insert([
            'payment_id'       => $paymentId,
            'invoice_id'       => $invoiceId,
            'allocated_amount' => 120.00,
        ]);

        $totals = $this->call('receivablesByCurrency', [$this->db, 'USD']);

        self::assertSame(180.00, $totals['USD'] ?? null, '300 invoiced less 120 allocated');
    }

    public function testAnUnpostedPaymentDoesNotReduceReceivables(): void
    {
        $invoiceId = $this->invoice('posted', 60.00);

        $this->db->table('customer_payments')->insert([
            'customer_id'     => 999999,
            'payment_date'    => '2026-09-11',
            'currency_code'   => 'USD',
            'amount'          => 60.00,
            'posted_entry_id' => null,   // still a draft receipt
        ]);
        $paymentId = (int) $this->db->insertID();
        $this->db->table('customer_payment_allocations')->insert([
            'payment_id'       => $paymentId,
            'invoice_id'       => $invoiceId,
            'allocated_amount' => 60.00,
        ]);

        $totals = $this->call('receivablesByCurrency', [$this->db, 'USD']);

        self::assertSame(60.00, $totals['USD'] ?? null, 'money is only received once the receipt is posted');
    }
}
