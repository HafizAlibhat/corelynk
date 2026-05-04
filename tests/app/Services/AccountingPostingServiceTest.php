<?php

namespace Tests\App\Services;

use App\Models\Accounting\AccountModel;
use App\Models\Accounting\ChequeModel;
use App\Models\Accounting\JournalEntryModel;
use App\Models\Accounting\JournalLineModel;
use App\Models\VendorPaymentModel;
use App\Models\VendorPaymentAllocationModel;
use App\Models\CustomerInvoiceModel;
use App\Services\AccountingPostingService;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

class AccountingPostingServiceTest extends CIUnitTestCase
{
    public function testPostCustomerInvoiceSkipsIfNotConfirmed(): void
    {
        $db = Database::connect();

        // Create a draft invoice
        $inv = new CustomerInvoiceModel();
        $invoiceId = $inv->insert([
            'invoice_number' => 'TINV-' . date('YmdHis'),
            'customer_id' => 1,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'),
            'currency_code' => 'PKR',
            'subtotal' => 100,
            'tax_total' => 0,
            'total_amount' => 100,
            'status' => 'draft',
            'created_by' => 1,
        ], true);

        $svc = new AccountingPostingService($db);
        $res = $svc->postCustomerInvoice((int)$invoiceId);

        $this->assertTrue($res['success']);
        $this->assertTrue($res['skipped']);

        // Ensure invoice still not posted
        $row = $inv->find($invoiceId);
        $this->assertEmpty($row['posted_entry_id']);
    }

    public function testPostCustomerInvoiceCreatesJournalAndSetsPostedEntryId(): void
    {
        $db = Database::connect();

        // Ensure required accounts exist (codes: 1200 AR, 4000 Sales, 2200 Taxes Payable)
        $acc = new AccountModel();
        foreach ([
            ['code' => '1200', 'name' => 'Accounts Receivable', 'type' => 'Asset', 'currency_code' => 'PKR'],
            ['code' => '4000', 'name' => 'Sales Revenue', 'type' => 'Revenue', 'currency_code' => 'PKR'],
            ['code' => '2200', 'name' => 'Taxes Payable', 'type' => 'Liability', 'currency_code' => 'PKR'],
        ] as $a) {
            $exists = $acc->where('code', $a['code'])->first();
            if (!$exists) {
                $acc->insert($a);
            }
        }

        // Create confirmed invoice
        $inv = new CustomerInvoiceModel();
        $invoiceId = $inv->insert([
            'invoice_number' => 'TINV-' . date('YmdHis') . '-2',
            'customer_id' => 1,
            'issue_date' => date('Y-m-d'),
            'due_date' => date('Y-m-d'),
            'currency_code' => 'PKR',
            'subtotal' => 100,
            'tax_total' => 17,
            'total_amount' => 117,
            'status' => 'confirmed',
            'created_by' => 1,
        ], true);

        $svc = new AccountingPostingService($db);
        $res = $svc->postCustomerInvoice((int)$invoiceId);

        $this->assertTrue($res['success']);
        $this->assertFalse($res['skipped']);
        $this->assertNotEmpty($res['posted_entry_id']);

        $postedJeId = (int)$res['posted_entry_id'];

        // Invoice should be linked
        $row = $inv->find($invoiceId);
        $this->assertEquals($postedJeId, (int)($row['posted_entry_id'] ?? 0));

        // Journal entry should exist
        $je = new JournalEntryModel();
        $jeRow = $je->find($postedJeId);
        $this->assertNotEmpty($jeRow);

        // Should have 3 lines (AR debit, sales credit, tax credit)
        $jl = new JournalLineModel();
        $lines = $jl->where('entry_id', $postedJeId)->findAll();
        $this->assertCount(3, $lines);

        $debits = 0.0;
        $credits = 0.0;
        foreach ($lines as $l) {
            $debits += (float)($l['debit'] ?? 0);
            $credits += (float)($l['credit'] ?? 0);
        }
        $this->assertEquals(117.0, (float)number_format($debits, 2, '.', ''));
        $this->assertEquals(117.0, (float)number_format($credits, 2, '.', ''));
    }

    public function testPostVendorPaymentCreatesJournalAndAllocations(): void
    {
        $db = Database::connect();
        $accountModel = new AccountModel();
        foreach ([
            ['code' => '1000', 'name' => 'Cash', 'type' => 'Asset', 'currency_code' => 'PKR'],
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'Liability', 'currency_code' => 'PKR'],
        ] as $row) {
            if (!$accountModel->where('code', $row['code'])->first()) {
                $accountModel->insert($row);
            }
        }

        $cashRow = $accountModel->where('code', '1000')->first();
        $cashId = (int)($cashRow['id'] ?? 0);

        $paymentModel = new VendorPaymentModel();
        $paymentId = $paymentModel->insert([
            'vendor_id' => 5,
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'cash',
            'amount' => 500,
            'currency_code' => 'PKR',
            'source_account_id' => $cashId,
            'status' => 'draft',
            'created_by' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], true);

        $allocModel = new VendorPaymentAllocationModel();
        $allocModel->insert([
            'payment_id' => $paymentId,
            'vendor_bill_id' => 11,
            'amount_allocated' => 300,
            'allocated_at' => date('Y-m-d H:i:s'),
        ]);
        $allocModel->insert([
            'payment_id' => $paymentId,
            'vendor_bill_id' => 12,
            'amount_allocated' => 200,
            'allocated_at' => date('Y-m-d H:i:s'),
        ]);

        $svc = new AccountingPostingService($db);
        $res = $svc->postVendorPayment((int)$paymentId);

        $this->assertTrue($res['success']);
        $this->assertFalse($res['skipped']);
        $this->assertNotEmpty($res['vendor_payment_id']);
        $this->assertNotEmpty($res['journal_entry_id']);

        $payment = $paymentModel->find($res['vendor_payment_id']);
        $this->assertNotEmpty($payment);
        $this->assertEquals($res['journal_entry_id'], (int)($payment['posted_entry_id'] ?? 0));

        $allocs = $allocModel->where('payment_id', $res['vendor_payment_id'])->findAll();
        $this->assertCount(2, $allocs);

        $jlModel = new JournalLineModel();
        $lines = $jlModel->where('entry_id', $res['journal_entry_id'])->findAll();
        $this->assertCount(2, $lines);

        $debits = array_sum(array_map(fn($l) => (float)($l['debit'] ?? 0), $lines));
        $credits = array_sum(array_map(fn($l) => (float)($l['credit'] ?? 0), $lines));
        $this->assertEquals(500.0, (float)number_format($debits, 2, '.', ''));
        $this->assertEquals(500.0, (float)number_format($credits, 2, '.', ''));
    }

    public function testPostVendorPaymentRequiresSourceAccountForBank(): void
    {
        $paymentModel = new VendorPaymentModel();
        $paymentId = $paymentModel->insert([
            'vendor_id' => 5,
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'bank',
            'amount' => 250,
            'currency_code' => 'PKR',
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], true);

        $svc = new AccountingPostingService(Database::connect());
        $res = $svc->postVendorPayment((int)$paymentId);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('source_account_id', strtolower($res['message']));
    }

    public function testPostVendorPaymentReusesChequePostedEntry(): void
    {
        $db = Database::connect();
        $accountModel = new AccountModel();
        foreach ([
            ['code' => '2000', 'name' => 'Accounts Payable', 'type' => 'Liability', 'currency_code' => 'PKR'],
            ['code' => '1010', 'name' => 'Bank - Main', 'type' => 'Asset', 'currency_code' => 'PKR'],
        ] as $row) {
            if (!$accountModel->where('code', $row['code'])->first()) {
                $accountModel->insert($row);
            }
        }

        $apId = (int)$accountModel->where('code', '2000')->first()['id'];
        $bankId = (int)$accountModel->where('code', '1010')->first()['id'];

        $jeModel = new JournalEntryModel();
        $jeId = $jeModel->insert([
            'entry_date' => date('Y-m-d'),
            'memo' => 'Cheque posting',
            'currency_code' => 'PKR',
            'total_debits' => 300,
            'total_credits' => 300,
        ], true);

        $jlModel = new JournalLineModel();
        $jlModel->insert(['entry_id' => $jeId, 'account_id' => $apId, 'description' => 'AP', 'debit' => 300, 'credit' => 0, 'currency_code' => 'PKR', 'fx_rate' => 1, 'base_amount' => 300]);
        $jlModel->insert(['entry_id' => $jeId, 'account_id' => $bankId, 'description' => 'Bank', 'debit' => 0, 'credit' => 300, 'currency_code' => 'PKR', 'fx_rate' => 1, 'base_amount' => 300]);

        $chequeModel = new ChequeModel();
        $chequeId = $chequeModel->insert([
            'bank_account_id' => $bankId,
            'cheque_number' => 'CHK-2026',
            'cheque_date' => date('Y-m-d'),
            'payee_type' => 'vendor',
            'vendor_id' => 5,
            'payee_name' => 'Vendor 5',
            'delivery_type' => 'ac_payee',
            'status' => 'posted',
            'amount' => 300,
            'posted_entry_id' => $jeId,
        ], true);

        $paymentModel = new VendorPaymentModel();
        $paymentId = $paymentModel->insert([
            'vendor_id' => 5,
            'payment_date' => date('Y-m-d'),
            'payment_method' => 'cheque',
            'amount' => 300,
            'currency_code' => 'PKR',
            'source_account_id' => $bankId,
            'cheque_id' => $chequeId,
            'status' => 'draft',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ], true);

        $svc = new AccountingPostingService($db);
        $res = $svc->postVendorPayment((int)$paymentId);

        $this->assertTrue($res['success']);
        $this->assertEquals($jeId, $res['journal_entry_id']);
        $row = $paymentModel->find($paymentId);
        $this->assertEquals($jeId, (int)($row['posted_entry_id'] ?? 0));
    }

    public function testPostVendorBillTableMissing(): void
    {
        $svc = new AccountingPostingService(Database::connect());
        $res = $svc->postVendorBill(123);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('vendor_bills table is not available', strtolower($res['message']));
    }
}
