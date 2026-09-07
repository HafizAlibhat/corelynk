<?php

namespace App\Controllers;

use App\Models\Accounting\AccountModel;
use App\Models\VendorModel;
use App\Models\VendorPaymentAllocationModel;
use App\Models\VendorPaymentModel;
use App\Services\DocumentAttachmentService;
use App\Services\AccountingPostingService;
use Config\Database;

class AccountingVendorPayments extends BaseController
{
    private const SUPPORTED_METHODS = [
        'cash' => 'Cash',
        'bank' => 'Bank',
        'cheque' => 'Cheque',
        'online_transfer' => 'Online Transfer',
    ];

    private const PAYMENT_TYPES = [
        'settlement' => 'Settlement',
        'advance' => 'Advance',
    ];

    public function pay()
    {
        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $sourceAccounts = $this->resolveSourceAccounts();

        $preVendorId = (int) ($this->request->getGet('vendor_id') ?? 0);
        $preBillId   = (int) ($this->request->getGet('bill_id')   ?? 0);

        return view('accounting/vendor_payments/pay', [
            'vendors'       => $vendors,
            'methods'       => self::SUPPORTED_METHODS,
            'paymentTypes'  => self::PAYMENT_TYPES,
            'sourceAccounts'=> $sourceAccounts,
            'preVendorId'   => $preVendorId,
            'preBillId'     => $preBillId,
        ]);
    }

    public function index()
    {
        $db = Database::connect();
        $rows = $db->query(
            'SELECT vp.*, v.name AS vendor_name, a.name AS source_account_name, '
            . '(SELECT COUNT(*) FROM document_attachments da WHERE da.document_id = vp.id AND LOWER(da.document_type) = ?) AS attachment_count, '
            . '(SELECT COUNT(*) FROM vendor_payment_allocations vpa WHERE vpa.payment_id = vp.id) AS allocation_count '
            . 'FROM vendor_payments vp '
            . 'LEFT JOIN vendors v ON v.id = vp.vendor_id '
            . 'LEFT JOIN accounts a ON a.id = vp.source_account_id '
            . 'ORDER BY vp.payment_date DESC, vp.id DESC',
            ['vendor_payment']
        )->getResultArray();

        $drafts = [];
        $posted = [];
        $voided = [];

        foreach ($rows as $row) {
            $status = strtolower((string)($row['status'] ?? 'draft'));
            if ($status === 'posted') {
                $posted[] = $row;
            } elseif ($status === 'void') {
                $voided[] = $row;
            } else {
                $drafts[] = $row;
            }
        }

        return view('accounting/vendor_payments/index', [
            'drafts' => $drafts,
            'posted' => $posted,
            'voided' => $voided,
        ]);
    }

    public function createDraft()
    {
        if (! $this->request->isAJAX()) {
            return $this->ajaxError('AJAX required');
        }

        // NOTE: Some installs may have vendor IDs = 0. Also, CI model find(0) can be tricky because 0 is "empty" in PHP.
        // For reliability with multipart/form-data (FormData), prefer the raw $_POST value if present.
        $vendorRaw = trim((string) ($_POST['vendor_id'] ?? ($this->request->getPost('vendor_id') ?? '')));
        // Allow vendor id 0 if it exists in the DB (some installs use NO_AUTO_VALUE_ON_ZERO).
        // But do not accept empty/non-numeric values.
        if ($vendorRaw === '' || ! preg_match('/^-?\d+$/', $vendorRaw)) {
            return $this->ajaxError('Vendor is required');
        }
        $vendorId = (int) $vendorRaw;
        if ($vendorId < 0) {
            return $this->ajaxError('Vendor is required');
        }

        $vendorModel = new VendorModel();
        $vendor = $vendorModel->where('id', $vendorId)->first();
        if (! $vendor) {
            return $this->ajaxError('Vendor not found');
        }

        $method = strtolower(trim((string) ($this->request->getPost('payment_method') ?? '')));
        if (! isset(self::SUPPORTED_METHODS[$method])) {
            return $this->ajaxError('Select a valid payment method');
        }

        $paymentType = strtolower(trim((string) ($this->request->getPost('payment_type') ?? '')));
        if ($paymentType === '') {
            $paymentType = 'settlement';
        }
        if (!in_array($paymentType, ['settlement', 'advance'], true)) {
            return $this->ajaxError('Select a valid payment type');
        }

        $sourceAccountId = (int) ($this->request->getPost('source_account_id') ?? 0);

        $paymentDate = $this->request->getPost('payment_date') ?: date('Y-m-d');
        $advanceAmount = $this->normalizeAmount($this->request->getPost('advance_amount') ?? 0);
        $advanceAmount = max(0.0, $advanceAmount);

        $allocationsRaw = $this->request->getPost('allocations') ?? '[]';
        $allocations = json_decode($allocationsRaw, true);
        if (! is_array($allocations)) {
            return $this->ajaxError('Allocations payload is invalid');
        }

        // IMPORTANT: getFileMultiple('attachments') matches the form name attachments[]
        // and returns UploadedFile[] (or empty array), which DocumentAttachmentService expects.
        $attachments = $this->request->getFileMultiple('attachments');

        $preparedAllocations = [];
        $allocatedTotal = 0.0;
        $advanceAllocatedTotal = 0.0;
        foreach ($allocations as $alloc) {
            $billId = (int) ($alloc['vendor_bill_id'] ?? 0);
            $amount = $this->normalizeAmount($alloc['amount'] ?? 0);
            $cashAmount = $this->normalizeAmount($alloc['cash_amount'] ?? 0);
            $advanceAlloc = $this->normalizeAmount($alloc['advance_amount'] ?? 0);
            if ($billId <= 0 || $amount <= 0) {
                continue;
            }
            $preparedAllocations[] = [
                'vendor_bill_id' => $billId,
                'amount' => $amount,
                'cash_amount' => $cashAmount,
                'advance_amount' => $advanceAlloc,
            ];
            $allocatedTotal += $amount;
            $advanceAllocatedTotal += $advanceAlloc;
        }

        if ($allocatedTotal <= 0 && $advanceAmount <= 0 && $advanceAllocatedTotal <= 0) {
            return $this->ajaxError('Add at least one allocation or advance amount');
        }

        // Auto-resolve payment type based on allocations/advance
        if ($allocatedTotal <= 0 && ($advanceAmount > 0 || $advanceAllocatedTotal > 0)) {
            $paymentType = 'advance';
        } else {
            $paymentType = $paymentType ?: 'settlement';
        }

        $currency = strtoupper(trim((string) ($this->request->getPost('currency_code') ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $effectiveAdvance = $advanceAllocatedTotal > 0 ? $advanceAllocatedTotal : $advanceAmount;
        $paymentAmount = ($paymentType === 'advance') ? $effectiveAdvance : $allocatedTotal;
        $cashPortion = max(0.0, $allocatedTotal - $effectiveAdvance);

        // Source account required ONLY when:
        // 1. Giving NEW advance to vendor (no bill allocations) - money goes OUT from our account
        // 2. Any cash portion in bill payment - money goes OUT from our account
        // NOT required when using vendor's existing advance to settle bills (no money movement)
        $givingNewAdvance = ($paymentType === 'advance' && count($preparedAllocations) === 0);
        $needsSourceAccount = $givingNewAdvance || $cashPortion > 0;
        
        if ($needsSourceAccount && $sourceAccountId <= 0) {
            return $this->ajaxError('Source account is required');
        }

        $paymentModel = new VendorPaymentModel();
        if ($paymentType !== 'advance' && $effectiveAdvance > 0) {
            $advanceBalance = $paymentModel->getVendorAdvanceBalance($vendorId);
            if ($effectiveAdvance > $advanceBalance) {
                return $this->ajaxError('Advance applied exceeds available advance balance');
            }
        }
        $notes = trim((string) ($this->request->getPost('notes') ?? ''));
        $memo = trim((string) ($this->request->getPost('memo') ?? ''));
        $chequePayeeName = trim((string) ($this->request->getPost('cheque_payee_name') ?? ''));
        $chequeNotes = trim((string) ($this->request->getPost('cheque_notes') ?? ''));
        $chequeNumber = trim((string) ($this->request->getPost('cheque_number') ?? ''));
        $chequeDeliveryType = trim((string) ($this->request->getPost('cheque_delivery_type') ?? ''));
        $createdBy = session()->get('user_id') ?? null;
        $now = date('Y-m-d H:i:s');

        $allocationModel = new VendorPaymentAllocationModel();
        $db = Database::connect();
        $db->transBegin();

        try {
            // best-effort: add advance_amount column if missing
            try {
                $col = $db->query("SHOW COLUMNS FROM vendor_payments LIKE 'advance_amount'")->getRowArray();
                if (!$col) {
                    $db->query("ALTER TABLE vendor_payments ADD COLUMN advance_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER amount");
                }
            } catch (\Throwable $_) { /* ignore */ }

            $paymentId = $paymentModel->insert([
                'vendor_id' => $vendorId,
                'payment_date' => $paymentDate,
                'payment_method' => $method,
                'payment_type' => $paymentType,
                'currency_code' => $currency,
                'amount' => $paymentAmount,
                'advance_amount' => $effectiveAdvance,
                'source_account_id' => $sourceAccountId,
                'memo' => $memo ?: null,
                'notes' => $notes ?: null,
                'cheque_payee_name' => $chequePayeeName ?: null,
                'cheque_notes' => $chequeNotes ?: null,
                'cheque_number' => $chequeNumber ?: null,
                'cheque_delivery_type' => $chequeDeliveryType ?: null,
                'status' => 'draft',
                'created_by' => $createdBy,
                'created_at' => $now,
            ], true);

            if (! $paymentId) {
                throw new \RuntimeException('Failed to persist vendor payment draft');
            }

            foreach ($preparedAllocations as $row) {
                $insertData = [
                    'payment_id' => $paymentId,
                    'vendor_bill_id' => $row['vendor_bill_id'],
                    'amount' => $row['amount'],
                    'amount_allocated' => $row['amount'],
                    'advance_amount' => $row['advance_amount'],
                    'allocated_at' => $now,
                    'created_at' => $now,
                    'created_by' => $createdBy,
                ];
                $inserted = $allocationModel->insert($insertData);
                if (!$inserted) {
                    log_message('error', 'VendorPayment allocation insert failed: ' . json_encode($insertData) . ' errors: ' . json_encode($allocationModel->errors()));
                }
            }

            $db->transCommit();

            // Attachments (supporting docs; required later only for online_transfer)
            try {
                if (!empty($attachments)) {
                    // Filter out empty/invalid file uploads (browser sends empty UploadedFile for unfilled file inputs)
                    $validAttachments = array_filter($attachments, function($f) {
                        return $f instanceof \CodeIgniter\HTTP\Files\UploadedFile
                            && $f->isValid()
                            && $f->getSize() > 0
                            && $f->getClientName() !== '';
                    });
                    if (!empty($validAttachments)) {
                        $svc = new DocumentAttachmentService();
                        $svc->storeMany('vendor_payment', (int) $paymentId, $validAttachments, $createdBy ? (int) $createdBy : null);
                    }
                }
            } catch (\Throwable $e) {
                // Best-effort: don't block saving draft if attachment fails
                log_message('error', 'VendorPayment attachment upload failed for payment ' . $paymentId . ': ' . $e->getMessage());
            }

            return $this->response->setJSON([
                'success' => true,
                'payment_id' => (int) $paymentId,
                'message' => 'Draft vendor payment saved',
                'amount' => $paymentAmount,
                'allocated_total' => $allocatedTotal,
                'advance_amount' => $effectiveAdvance,
                'payment_method' => $method,
                'source_account_id' => $sourceAccountId,
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->ajaxError('Failed to save draft: ' . $e->getMessage());
        }
    }

    public function confirm()
    {
        if (! $this->request->isAJAX()) {
            return $this->ajaxError('AJAX required');
        }

        $paymentId = (int) ($this->request->getPost('payment_id') ?? 0);
        if ($paymentId <= 0) {
            return $this->ajaxError('Payment id is required');
        }

        $paymentModel = new VendorPaymentModel();
        $payment = $paymentModel->find($paymentId);
        if (!$payment) {
            return $this->ajaxError('Vendor payment not found', 404);
        }

        // Accept attachments during confirm as well.
        $attachments = $this->request->getFileMultiple('attachments');
        $createdBy = session()->get('user_id') ?? null;
        try {
            if (!empty($attachments)) {
                $svc = new DocumentAttachmentService();
                $svc->storeMany('vendor_payment', $paymentId, $attachments, $createdBy ? (int) $createdBy : null);
            }
        } catch (\Throwable $e) {
            log_message('error', 'VendorPayment attachment upload failed on confirm for payment ' . $paymentId . ': ' . $e->getMessage());
        }

        $posting = new AccountingPostingService();
        $result = $posting->postVendorPayment($paymentId);
        $result['payment_id'] = $paymentId;

        return $this->response->setJSON($result);
    }

    /**
     * Vendor payment detail view (includes receipts/attachments).
     */
    public function view($id)
    {
        $paymentId = (int) $id;
        if ($paymentId <= 0) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor payment not found');
        }

        $db = Database::connect();
        $payment = $db->query(
            'SELECT vp.*, v.name AS vendor_name, a.name AS source_account_name, a.account_number AS source_account_number '
            . 'FROM vendor_payments vp '
            . 'LEFT JOIN vendors v ON v.id = vp.vendor_id '
            . 'LEFT JOIN accounts a ON a.id = vp.source_account_id '
            . 'WHERE vp.id = ? LIMIT 1',
            [$paymentId]
        )->getRowArray();

        if (!$payment) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Vendor payment not found');
        }

        $allocations = $db->query(
            'SELECT vpa.*, vb.bill_number, vb.bill_date, vb.total_amount AS bill_total, vb.balance AS bill_balance, vb.status AS bill_status '
            . 'FROM vendor_payment_allocations vpa '
            . 'LEFT JOIN vendor_bills vb ON vb.id = vpa.vendor_bill_id '
            . 'WHERE vpa.payment_id = ? '
            . 'ORDER BY vpa.id ASC',
            [$paymentId]
        )->getResultArray();

        $attachments = [];
        try {
            $attachments = $db->query(
                "SELECT id,document_type,document_id,file_path,original_name,mime_type,uploaded_at FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                [$paymentId, 'vendor_payment']
            )->getResultArray();
        } catch (\Throwable $e) {
            $attachments = [];
        }

        return view('accounting/vendor_payments/view', [
            'payment' => $payment,
            'allocations' => $allocations,
            'attachments' => $attachments,
        ]);
    }

    public function edit($id)
    {
        $paymentId = (int) $id;
        $db = Database::connect();

        $payment = $db->query(
            'SELECT vp.*, v.name AS vendor_name '
            . 'FROM vendor_payments vp '
            . 'LEFT JOIN vendors v ON v.id = vp.vendor_id '
            . 'WHERE vp.id = ? LIMIT 1',
            [$paymentId]
        )->getRowArray();

        if (!$payment) {
            return redirect()->to(base_url('accounting/vendor-payments'))->with('error', 'Payment not found.');
        }

        if (strtolower((string)($payment['status'] ?? '')) !== 'draft') {
            return redirect()->to(base_url('accounting/vendor-payments/' . $paymentId))
                ->with('error', 'Only draft payments can be edited.');
        }

        $allocations = $db->query(
            'SELECT vpa.vendor_bill_id, vpa.amount, vpa.amount_allocated, vpa.advance_amount, '
            . 'COALESCE(vpa.amount - vpa.advance_amount, vpa.amount) AS cash_amount '
            . 'FROM vendor_payment_allocations vpa WHERE vpa.payment_id = ? ORDER BY vpa.id',
            [$paymentId]
        )->getResultArray();

        $existingAttachments = $db->query(
            'SELECT id, original_name, file_path, mime_type FROM document_attachments '
            . 'WHERE document_type = ? AND document_id = ? ORDER BY id',
            ['vendor_payment', $paymentId]
        )->getResultArray();

        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $sourceAccounts = $this->resolveSourceAccounts();

        return view('accounting/vendor_payments/pay', [
            'payment'            => $payment,
            'editAllocations'    => $allocations,
            'editAttachments'    => $existingAttachments,
            'vendors'            => $vendors,
            'methods'            => self::SUPPORTED_METHODS,
            'paymentTypes'       => self::PAYMENT_TYPES,
            'sourceAccounts'     => $sourceAccounts,
            'preVendorId'        => (int) $payment['vendor_id'],
            'preBillId'          => 0,
        ]);
    }

    public function updateDraft()
    {
        if (! $this->request->isAJAX()) {
            return $this->ajaxError('AJAX required');
        }

        $paymentId = (int) ($this->request->getPost('payment_id') ?? 0);
        if ($paymentId <= 0) {
            return $this->ajaxError('Payment ID is required');
        }

        $paymentModel = new VendorPaymentModel();
        $existing = $paymentModel->find($paymentId);
        if (!$existing) {
            return $this->ajaxError('Payment not found', 404);
        }
        if (strtolower((string)($existing['status'] ?? '')) !== 'draft') {
            return $this->ajaxError('Only draft payments can be edited');
        }

        // Reuse same validation logic as createDraft
        $vendorRaw = trim((string) ($_POST['vendor_id'] ?? ($this->request->getPost('vendor_id') ?? '')));
        if ($vendorRaw === '' || !preg_match('/^-?\d+$/', $vendorRaw) || (int)$vendorRaw < 0) {
            return $this->ajaxError('Vendor is required');
        }
        $vendorId = (int) $vendorRaw;

        $method = strtolower(trim((string) ($this->request->getPost('payment_method') ?? '')));
        if (!isset(self::SUPPORTED_METHODS[$method])) {
            return $this->ajaxError('Select a valid payment method');
        }

        $paymentType  = strtolower(trim((string) ($this->request->getPost('payment_type') ?? 'settlement')));
        $sourceAccountId = (int) ($this->request->getPost('source_account_id') ?? 0);
        $paymentDate  = $this->request->getPost('payment_date') ?: date('Y-m-d');
        $advanceAmount = max(0.0, $this->normalizeAmount($this->request->getPost('advance_amount') ?? 0));
        $currency     = strtoupper(trim((string) ($this->request->getPost('currency_code') ?? 'PKR'))) ?: 'PKR';
        $notes        = trim((string) ($this->request->getPost('notes') ?? ''));
        $memo         = trim((string) ($this->request->getPost('memo') ?? ''));
        $chequePayeeName   = trim((string) ($this->request->getPost('cheque_payee_name') ?? ''));
        $chequeNotes       = trim((string) ($this->request->getPost('cheque_notes') ?? ''));
        $chequeNumber      = trim((string) ($this->request->getPost('cheque_number') ?? ''));
        $chequeDeliveryType= trim((string) ($this->request->getPost('cheque_delivery_type') ?? ''));

        $allocationsRaw = $this->request->getPost('allocations') ?? '[]';
        $allocations = json_decode($allocationsRaw, true);
        if (!is_array($allocations)) {
            return $this->ajaxError('Allocations payload invalid');
        }

        $preparedAllocations = [];
        $allocatedTotal = 0.0;
        $advanceAllocatedTotal = 0.0;
        foreach ($allocations as $alloc) {
            $billId  = (int) ($alloc['vendor_bill_id'] ?? 0);
            $amount  = $this->normalizeAmount($alloc['amount'] ?? 0);
            $cashAmt = $this->normalizeAmount($alloc['cash_amount'] ?? 0);
            $advAmt  = $this->normalizeAmount($alloc['advance_amount'] ?? 0);
            if ($billId <= 0 || $amount <= 0) continue;
            $preparedAllocations[] = ['vendor_bill_id' => $billId, 'amount' => $amount, 'cash_amount' => $cashAmt, 'advance_amount' => $advAmt];
            $allocatedTotal += $amount;
            $advanceAllocatedTotal += $advAmt;
        }

        $effectiveAdvance = $advanceAllocatedTotal > 0 ? $advanceAllocatedTotal : $advanceAmount;
        if ($allocatedTotal <= 0 && $effectiveAdvance <= 0) {
            return $this->ajaxError('Add at least one allocation or advance amount');
        }

        $paymentType = ($allocatedTotal <= 0 && $effectiveAdvance > 0) ? 'advance' : ($paymentType ?: 'settlement');
        $paymentAmount = ($paymentType === 'advance') ? $effectiveAdvance : $allocatedTotal;
        $cashPortion   = max(0.0, $allocatedTotal - $effectiveAdvance);
        $givingNewAdvance = ($paymentType === 'advance' && count($preparedAllocations) === 0);
        $needsSourceAccount = $givingNewAdvance || $cashPortion > 0;
        if ($needsSourceAccount && $sourceAccountId <= 0) {
            return $this->ajaxError('Source account is required');
        }

        $allocationModel = new VendorPaymentAllocationModel();
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');
        $createdBy = session()->get('user_id') ?? null;

        $db->transBegin();
        try {
            $paymentModel->update($paymentId, [
                'vendor_id'          => $vendorId,
                'payment_date'       => $paymentDate,
                'payment_method'     => $method,
                'payment_type'       => $paymentType,
                'currency_code'      => $currency,
                'amount'             => $paymentAmount,
                'advance_amount'     => $effectiveAdvance,
                'source_account_id'  => $sourceAccountId,
                'memo'               => $memo ?: null,
                'notes'              => $notes ?: null,
                'cheque_payee_name'  => $chequePayeeName ?: null,
                'cheque_notes'       => $chequeNotes ?: null,
                'cheque_number'      => $chequeNumber ?: null,
                'cheque_delivery_type'=> $chequeDeliveryType ?: null,
                'updated_at'         => $now,
            ]);

            // Replace allocations
            $db->query('DELETE FROM vendor_payment_allocations WHERE payment_id = ?', [$paymentId]);
            foreach ($preparedAllocations as $row) {
                $allocationModel->insert([
                    'payment_id'       => $paymentId,
                    'vendor_bill_id'   => $row['vendor_bill_id'],
                    'amount'           => $row['amount'],
                    'amount_allocated' => $row['amount'],
                    'advance_amount'   => $row['advance_amount'],
                    'allocated_at'     => $now,
                    'created_at'       => $now,
                    'created_by'       => $createdBy,
                ]);
            }

            // New attachments (optional)
            $attachments = $this->request->getFileMultiple('attachments');
            if (!empty($attachments)) {
                $validAttachments = array_filter($attachments, function($f) {
                    return $f instanceof \CodeIgniter\HTTP\Files\UploadedFile && $f->isValid() && $f->getSize() > 0 && $f->getClientName() !== '';
                });
                if (!empty($validAttachments)) {
                    $existingCount = (int) ($db->query(
                        'SELECT COUNT(*) AS cnt FROM document_attachments WHERE document_type = ? AND document_id = ?',
                        ['vendor_payment', $paymentId]
                    )->getRowArray()['cnt'] ?? 0);
                    if (count($validAttachments) + $existingCount > 5) {
                        $db->transRollback();
                        return $this->ajaxError('Maximum 5 attachments allowed per payment');
                    }
                    (new DocumentAttachmentService())->storeMany('vendor_payment', $paymentId, $validAttachments, $createdBy ? (int) $createdBy : null);
                }
            }

            $db->transCommit();
            return $this->response->setJSON([
                'success'    => true,
                'payment_id' => $paymentId,
                'message'    => 'Draft updated successfully',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->ajaxError('Failed to update draft: ' . $e->getMessage());
        }
    }

    public function deleteAttachment()
    {
        if (! $this->request->isAJAX()) {
            return $this->ajaxError('AJAX required');
        }

        $attachId = (int) ($this->request->getPost('attachment_id') ?? 0);
        if ($attachId <= 0) {
            return $this->ajaxError('Invalid attachment ID');
        }

        $db = Database::connect();
        $attach = $db->query(
            'SELECT da.*, vp.status FROM document_attachments da '
            . 'JOIN vendor_payments vp ON vp.id = da.document_id '
            . 'WHERE da.id = ? AND da.document_type = ? LIMIT 1',
            [$attachId, 'vendor_payment']
        )->getRowArray();

        if (! $attach) {
            return $this->ajaxError('Attachment not found', 404);
        }

        if (strtolower((string) ($attach['status'] ?? '')) !== 'draft') {
            return $this->ajaxError('Cannot delete attachments on a posted payment');
        }

        $filePath = FCPATH . ltrim((string) $attach['file_path'], '/');
        if (file_exists($filePath)) {
            @unlink($filePath);
        }

        $db->query('DELETE FROM document_attachments WHERE id = ?', [$attachId]);

        return $this->response->setJSON(['success' => true]);
    }

    private function resolveSourceAccounts(): array
    {
        $db = Database::connect();
        $hasIsBank = false;
        try {
            $hasIsBank = (bool) $db->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'")->getRowArray();
        } catch (\Throwable $e) {
            $hasIsBank = false;
        }

        $accountModel = new AccountModel();
        $bankAccounts = [];
        if ($hasIsBank) {
            $bankAccounts = $accountModel->select('id,name,account_number')->where('is_bank', 1)->orderBy('name', 'ASC')->findAll();
        } else {
            $bankAccounts = $accountModel->select('id,name,account_number')->where('type', 'Asset')->like('name', 'Bank')->orderBy('name', 'ASC')->findAll();
        }

        $cashAccounts = $accountModel->select('id,name,account_number')->like('name', 'Cash')->orderBy('name', 'ASC')->findAll();
        $merged = [];
        $seen = [];
        foreach (array_merge($bankAccounts, $cashAccounts) as $account) {
            $id = (int) ($account['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $merged[] = $account;
        }

        return $merged;
    }

    private function ajaxError(string $message, int $statusCode = 400)
    {
        return $this->response->setStatusCode($statusCode)->setJSON([
            'success' => false,
            'message' => $message,
        ]);
    }

    private function normalizeAmount(mixed $value): float
    {
        $clean = str_replace(',', '', trim((string) $value));
        if ($clean === '') {
            return 0.0;
        }

        return (float) $clean;
    }
}
