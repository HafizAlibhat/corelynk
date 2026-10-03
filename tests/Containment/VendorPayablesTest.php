<?php

namespace Tests\Containment;

use App\Controllers\Corelynk;
use ReflectionMethod;
use Tests\Support\ContainmentTestCase;

/**
 * The Vendor Payables tile on the home dashboard.
 *
 * It used to read `vendor_bills.balance`, a column nothing writes back when a
 * payment is posted -- so paying a bill by cash or cheque left the dashboard
 * showing the full amount as still owed. What was actually paid lives in
 * vendor_payment_allocations, which is what the vendor bill list already uses.
 */
final class VendorPayablesTest extends ContainmentTestCase
{
    /** A currency nothing else in the database uses, so totals stay isolated. */
    private const CUR = 'XTS';

    private function call(string $method, array $args, ?int &$billCount = null)
    {
        $ref = new ReflectionMethod(Corelynk::class, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs(new Corelynk(), [$args[0], &$billCount]);
    }

    /** Insert a bill with the stale full balance real rows carry. */
    private function bill(float $total, string $status = 'confirmed'): int
    {
        $this->db->table('vendor_bills')->insert([
            'vendor_id'     => 999999,
            'bill_date'     => '2026-09-01',
            'total_amount'  => $total,
            'balance'       => $total,
            'status'        => $status,
            'currency_code' => self::CUR,
        ]);

        return (int) $this->db->insertID();
    }

    private function pay(int $billId, float $amount, string $status = 'posted'): void
    {
        $this->db->table('vendor_payments')->insert([
            'vendor_id'      => 999999,
            'payment_date'   => '2026-09-02',
            'payment_method' => 'cash',
            'currency_code'  => self::CUR,
            'amount'         => $amount,
            'status'         => $status,
        ]);
        $paymentId = (int) $this->db->insertID();

        $this->db->table('vendor_payment_allocations')->insert([
            'payment_id'      => $paymentId,
            'vendor_bill_id'  => $billId,
            'amount'          => $amount,
            'amount_allocated' => $amount,
        ]);
    }

    private function payables(?int &$billCount = null): float
    {
        $totals = $this->call('payablesByCurrency', [$this->db], $billCount);

        return (float) ($totals[self::CUR] ?? 0.0);
    }

    public function testAFullyPaidBillLeavesNothingPayable(): void
    {
        $billId = $this->bill(9000.00);
        self::assertSame(9000.00, $this->payables());

        $this->pay($billId, 9000.00);

        self::assertSame(0.0, $this->payables(), 'a paid bill still counted as payable');
    }

    public function testAPartialPaymentLeavesOnlyTheRemainder(): void
    {
        $billId = $this->bill(7220.00);
        $this->pay($billId, 2220.00);

        self::assertSame(5000.00, $this->payables(), '7220 billed less 2220 paid');
    }

    public function testAnUnpostedPaymentDoesNotReducePayables(): void
    {
        $billId = $this->bill(500.00);
        $this->pay($billId, 500.00, 'draft');

        self::assertSame(500.00, $this->payables(), 'money is only paid once the payment is posted');
    }

    public function testDraftAndCancelledBillsAreNotPayable(): void
    {
        $this->bill(100.00, 'draft');
        $this->bill(200.00, 'cancelled');
        $this->bill(300.00);

        self::assertSame(300.00, $this->payables($count));
        self::assertSame(1, (int) $count, 'only open bills are counted on the tile');
    }
}
