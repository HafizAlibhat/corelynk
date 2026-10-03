<?php

namespace Tests\Containment;

use App\Helpers\CustomerReceivablesHelper;
use Tests\Support\ContainmentTestCase;

/**
 * A payment allocation must never attach to a newly-created invoice merely
 * because a deleted invoice's numeric id was later reused.
 */
final class CustomerPaymentAllocationIntegrityTest extends ContainmentTestCase
{
    private function invoice(int $customerId, string $createdAt, float $total = 121.50): int
    {
        $this->db->table('customer_invoices')->insert([
            'invoice_number' => 'TEST-ALLOC-' . bin2hex(random_bytes(6)),
            'customer_id' => $customerId,
            'issue_date' => substr($createdAt, 0, 10),
            'due_date' => substr($createdAt, 0, 10),
            'currency_code' => 'USD',
            'subtotal' => $total,
            'tax_total' => 0,
            'total_amount' => $total,
            'status' => 'issued',
            'created_by' => 1,
            'created_at' => $createdAt,
        ]);

        return (int) $this->db->insertID();
    }

    private function allocate(int $invoiceId, int $customerId, string $createdAt, float $amount): int
    {
        $this->db->table('customer_payments')->insert([
            'customer_id' => $customerId,
            'payment_date' => substr($createdAt, 0, 10),
            'payment_method_id' => 1,
            'amount' => $amount,
            'posted_entry_id' => 1,
            'created_by' => 1,
            'created_at' => $createdAt,
        ]);
        $paymentId = (int) $this->db->insertID();

        $this->db->table('customer_payment_allocations')->insert([
            'payment_id' => $paymentId,
            'invoice_id' => $invoiceId,
            'allocated_amount' => $amount,
            'created_at' => $createdAt,
        ]);

        return $paymentId;
    }

    public function testStaleAllocationFromAnotherCustomerDoesNotPayReusedInvoiceId(): void
    {
        $invoiceId = $this->invoice(987001, '2026-09-30 10:48:21');
        $stalePaymentId = $this->allocate($invoiceId, 939001, '2026-05-04 10:18:30', 250.00);

        self::assertSame(1, (int) $this->db->query(
            'SELECT COUNT(*) AS n FROM customer_payment_allocations WHERE payment_id = ? AND invoice_id = ?',
            [$stalePaymentId, $invoiceId]
        )->getRowArray()['n'], 'fixture must reproduce the unsafe numeric-id relation');

        $result = CustomerReceivablesHelper::getUnpaidInvoices(987001, $this->db);
        self::assertCount(1, $result['unpaid']);
        self::assertSame(0.0, (float) $result['unpaid'][0]['paid_amount']);
        self::assertSame(121.5, (float) $result['unpaid'][0]['outstanding']);
    }

    public function testMatchingCustomerAllocationCreatedAfterInvoiceStillReducesBalance(): void
    {
        $invoiceId = $this->invoice(987002, '2026-09-30 10:48:21');
        $this->allocate($invoiceId, 987002, '2026-09-30 11:00:00', 20.00);

        $result = CustomerReceivablesHelper::getUnpaidInvoices(987002, $this->db);
        self::assertCount(1, $result['unpaid']);
        self::assertSame(20.0, (float) $result['unpaid'][0]['paid_amount']);
        self::assertSame(101.5, (float) $result['unpaid'][0]['outstanding']);
    }

    public function testEarlierAdvanceAppliedAfterInvoiceRemainsAValidAllocation(): void
    {
        $invoiceId = $this->invoice(987003, '2026-09-30 10:48:21');
        $paymentId = $this->allocate($invoiceId, 987003, '2026-05-04 10:18:30', 30.00);
        $this->db->table('customer_payment_allocations')
            ->where('payment_id', $paymentId)
            ->where('invoice_id', $invoiceId)
            ->update(['created_at' => '2026-09-30 11:15:00']);

        $result = CustomerReceivablesHelper::getUnpaidInvoices(987003, $this->db);
        self::assertCount(1, $result['unpaid']);
        self::assertSame(30.0, (float) $result['unpaid'][0]['paid_amount']);
        self::assertSame(91.5, (float) $result['unpaid'][0]['outstanding']);
    }

    public function testInvoiceFacingPaymentQueriesContainOwnershipAndCreationGuards(): void
    {
        foreach ([
            'app/Controllers/AccountingCustomerPayments.php',
            'app/Controllers/CustomerInvoices.php',
            'app/Helpers/CustomerReceivablesHelper.php',
            'app/Services/AccountingPostingService.php',
            'app/Services/ReportingService.php',
        ] as $file) {
            $source = $this->sourceOf($file);
            self::assertStringContainsString('customer_id', $source, $file);
            self::assertStringContainsString('created_at', $source, $file);
            self::assertStringContainsString('customer_payment_allocations', $source, $file);
        }

        $posting = $this->sourceOf('app/Services/AccountingPostingService.php');
        self::assertStringContainsString(
            'invoice allocations no longer belong to this customer payment',
            $posting,
            'Posting must fail closed when a stale allocation is present.'
        );

        $paymentIndex = $this->sourceOf('app/Views/accounting/customer_payments/index.php');
        self::assertStringContainsString('Receive Payment for This Invoice', $paymentIndex);
        self::assertStringContainsString('payments already saved and allocated to this invoice', $paymentIndex);

        $invoiceView = $this->sourceOf('app/Views/invoices/simple_invoice.php');
        self::assertStringContainsString('Browse This Invoice Payments (<?= count($invoicePayments ?? []) ?>)', $invoiceView);
    }
}
