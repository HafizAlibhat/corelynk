<?php

namespace App\Controllers;

use App\Models\Accounting\AccountModel;
use App\Models\CustomerModel;
use App\Models\CustomerPaymentAllocationModel;
use App\Models\CustomerPaymentModel;
use App\Services\DocumentAttachmentService;
use App\Services\AccountingPostingService;
use Config\Database;

class AccountingCustomerPayments extends BaseController
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
        $customerModel = new CustomerModel();
        $db = Database::connect();
        $this->ensurePaymentCurrencySchema($db);
        try {
            $hasSourceAccountCol = (bool) $db->query("SHOW COLUMNS FROM customer_payments LIKE 'source_account_id'")->getRowArray();
            if (! $hasSourceAccountCol) {
                $db->query("ALTER TABLE customer_payments ADD COLUMN source_account_id INT(11) NULL AFTER payment_method_id");
            }
        } catch (\Throwable $_) {
            // best effort for legacy installs
        }
        $hasIsActive = false;
        try {
            $hasIsActive = (bool) $db->query("SHOW COLUMNS FROM customers LIKE 'is_active'")->getRowArray();
        } catch (\Throwable $_) {
            $hasIsActive = false;
        }

        if ($hasIsActive) {
            $customers = $customerModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        } else {
            $customers = $customerModel->where('status', 'active')->orderBy('name', 'ASC')->findAll();
        }
        $sourceAccounts = $this->resolveSourceAccounts();

        // Allow prefill via query (invoice_id)
        $invoiceId = (int) ($this->request->getGet('invoice_id') ?? 0);
        $prefill = null;
        if ($invoiceId > 0) {
            $db = Database::connect();
            try {
                $invCols = [];
                try { $invCols = $db->getFieldNames('customer_invoices'); } catch (\Throwable $_) {}
                $invAmtCol = 'total_amount';
                foreach (['balance', 'total_amount', 'total', 'amount'] as $c) {
                    if (in_array($c, $invCols, true)) { $invAmtCol = $c; break; }
                }
                $dueExpr = in_array('due_date', $invCols, true) ? 'due_date' : 'NULL AS due_date';
                $curExpr = in_array('currency_code', $invCols, true) ? 'currency_code' : "'' AS currency_code";
                // Compute balance due: total_amount minus sum of posted allocations
                $balanceDueExpr = "GREATEST(0, COALESCE($invAmtCol, 0) - COALESCE((SELECT SUM(cpa3.allocated_amount) FROM customer_payment_allocations cpa3 INNER JOIN customer_payments cp3 ON cp3.id = cpa3.payment_id WHERE cpa3.invoice_id = customer_invoices.id AND cp3.posted_entry_id IS NOT NULL AND cp3.posted_entry_id > 0), 0))";
                $prefill = $db->query(
                    'SELECT id, customer_id, invoice_number, issue_date, ' . $dueExpr . ', ' . $curExpr . ', COALESCE(' . $invAmtCol . ', 0) AS invoice_total, ' . $balanceDueExpr . ' AS amount '
                    . 'FROM customer_invoices WHERE id = ? LIMIT 1',
                    [$invoiceId]
                )->getRowArray();
            } catch (\Throwable $_) {
                $prefill = null;
            }
        }

        // If editing an existing payment, allow prefill via ?payment_id=
        $editingPaymentId = (int) ($this->request->getGet('payment_id') ?? 0);
        if ($editingPaymentId > 0 && empty($prefill)) {
            try {
                $db = Database::connect();
                $p = $db->query('SELECT cp.*, c.name AS customer_name FROM customer_payments cp LEFT JOIN customers c ON c.id = cp.customer_id WHERE cp.id = ? LIMIT 1', [$editingPaymentId])->getRowArray();
                if (!empty($p)) {
                    $prefill = [
                        'id' => $p['id'],
                        'customer_id' => $p['customer_id'],
                        'amount' => $p['amount'] ?? 0,
                        'payment_date' => $p['payment_date'] ?? null,
                        'memo' => $p['memo'] ?? ($p['reference_number'] ?? null),
                        'notes' => $p['notes'] ?? ($p['memo'] ?? ''),
                    ];

                    // payment method: prefer textual column, otherwise resolve via payment_method_id
                    if (isset($p['payment_method']) && $p['payment_method'] !== null && $p['payment_method'] !== '') {
                        $prefill['payment_method'] = (string) $p['payment_method'];
                    } elseif (array_key_exists('payment_method_id', $p) && $p['payment_method_id'] !== null && $p['payment_method_id'] !== '') {
                        try {
                            $pmModel = new \App\Models\PaymentMethodModel();
                            $pmId = (int)$p['payment_method_id'];
                            $pm = $pmModel->where('id', $pmId)->first();
                            if ($pm && !empty($pm['method_name'])) {
                                // try to map to known slug (SUPPORTED_METHODS values are labels)
                                $found = null;
                                foreach (self::SUPPORTED_METHODS as $slug => $label) {
                                    if (strcasecmp($label, $pm['method_name']) === 0) {
                                        $found = $slug;
                                        break;
                                    }
                                }
                                $prefill['payment_method'] = $found ?? strtolower(str_replace(' ', '_', $pm['method_name']));
                            } elseif ($pmId === 0) {
                                // Legacy installs use id=0 for online transfer.
                                $prefill['payment_method'] = 'online_transfer';
                            }
                        } catch (\Throwable $_) {
                        }
                    }

                    // source account id
                    if (isset($p['source_account_id'])) {
                        $prefill['source_account_id'] = (int) $p['source_account_id'];
                    }

                    // allocations for this payment — select only columns that actually exist
                      try {
                          $cpaColsEdit = [];
                          try { $cpaColsEdit = $db->getFieldNames('customer_payment_allocations'); } catch (\Throwable $_) {}
                          $amtColEdit = in_array('allocated_amount', $cpaColsEdit, true) ? 'allocated_amount' : (in_array('amount', $cpaColsEdit, true) ? 'amount' : 'allocated_amount');
                          $extraEditCols = '';
                          if (in_array('advance_amount', $cpaColsEdit, true)) $extraEditCols .= ', advance_amount';
                          if (in_array('cash_amount', $cpaColsEdit, true)) $extraEditCols .= ', cash_amount';
                          $invColsEdit = [];
                          try { $invColsEdit = $db->getFieldNames('customer_invoices'); } catch (\Throwable $_) {}
                          $invNoExpr = in_array('invoice_number', $invColsEdit, true) ? 'ci.invoice_number' : "CONCAT('INV-', ci.id) AS invoice_number";
                          $invIssueExpr = in_array('issue_date', $invColsEdit, true) ? 'ci.issue_date' : 'NULL AS issue_date';
                          $invDueExpr = in_array('due_date', $invColsEdit, true) ? 'ci.due_date' : 'NULL AS due_date';
                          $allocRows = $db->query(
                              'SELECT cpa.invoice_id, ' . $amtColEdit . ' AS amount' . $extraEditCols . ', ' . $invNoExpr . ', ' . $invIssueExpr . ', ' . $invDueExpr . ' '
                              . 'FROM customer_payment_allocations cpa '
                              . 'LEFT JOIN customer_invoices ci ON ci.id = cpa.invoice_id '
                              . 'WHERE cpa.payment_id = ? ORDER BY cpa.id ASC',
                              [$editingPaymentId]
                          )->getResultArray();
                        $allocs = [];
                        foreach ($allocRows as $ar) {
                            $allocs[] = [
                                'invoice_id' => (int) ($ar['invoice_id'] ?? 0),
                                'amount' => (float) ($ar['amount'] ?? 0),
                                'advance_amount' => (float) ($ar['advance_amount'] ?? 0),
                                'cash_amount' => (float) ($ar['cash_amount'] ?? 0),
                                'invoice_number' => (string)($ar['invoice_number'] ?? ''),
                                'issue_date' => (string)($ar['issue_date'] ?? ''),
                                'due_date' => (string)($ar['due_date'] ?? ''),
                            ];
                        }
                        if (!empty($allocs)) {
                            $prefill['allocations'] = $allocs;
                        }
                    } catch (\Throwable $_) {
                    }

                    try {
                        $attRows = $db->query(
                            "SELECT id, original_name, file_path FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                            [$editingPaymentId, 'customer_payment']
                        )->getResultArray();
                        if (!empty($attRows)) {
                            $prefill['existing_attachments'] = $attRows;
                        }
                    } catch (\Throwable $_) {
                    }

                    // mark that this prefill is from a payment edit (not an invoice prefill)
                    $prefill['is_payment_edit'] = true;
                }
            } catch (\Throwable $_) {
            }
        }

        // Also allow prefill via explicit query params (customer_id, amount)
        $queryCustomer = (int) ($this->request->getGet('customer_id') ?? 0);
        $queryAmount = $this->request->getGet('amount');
        if (empty($prefill) && $queryCustomer > 0) {
            $prefill = ['customer_id' => $queryCustomer, 'amount' => is_numeric($queryAmount) ? (float)$queryAmount : 0.0];
        }

        // Ensure prefill customer is present in the customers list (some installs may have inactive customers)
        if (!empty($prefill['customer_id'])) {
            $found = false;
            foreach ($customers as $c) {
                if ((int)$c['id'] === (int)$prefill['customer_id']) { $found = true; break; }
            }
            if (! $found) {
                try {
                    $extra = $customerModel->find((int)$prefill['customer_id']);
                    if (!empty($extra)) {
                        // prepend so it's visible at top
                        array_unshift($customers, $extra);
                    }
                } catch (\Throwable $_) { }
            }
        }

        // Ensure prefill source account is present in dropdown even if it is outside the default filter.
        if (!empty($prefill['source_account_id'])) {
            $wantedId = (int)$prefill['source_account_id'];
            $found = false;
            foreach ($sourceAccounts as $a) {
                if ((int)($a['id'] ?? 0) === $wantedId) { $found = true; break; }
            }
            if (! $found) {
                try {
                    $extraAcc = (new AccountModel())->select('id,name,account_number')->find($wantedId);
                    if (!empty($extraAcc)) {
                        array_unshift($sourceAccounts, $extraAcc);
                    }
                } catch (\Throwable $_) { }
            }
        }

        return view('accounting/customer_payments/pay', [
            'customers' => $customers,
            'methods' => self::SUPPORTED_METHODS,
            'paymentTypes' => self::PAYMENT_TYPES,
            'sourceAccounts' => $sourceAccounts,
            'prefill' => $prefill,
            'global_date_format' => $this->getGlobalDateFormat(),
        ]);
    }

    /**
     * Simple edit redirect: show pay page with payment preloaded
     */
    public function edit($id)
    {
        // Reuse pay() by redirecting to it with payment_id
        return redirect()->to(base_url('accounting/customer-payments/pay?payment_id=' . (int)$id));
    }

    /**
     * AJAX: list open invoices for a customer (used for allocation UI).
     */
    public function openInvoices($customerId)
    {
        if (! $this->request->isAJAX()) {
            return $this->ajaxError('AJAX required');
        }

        $customerId = (int) $customerId;
        if ($customerId <= 0) {
            return $this->ajaxError('Customer is required');
        }

        $excludePaymentId = (int)($this->request->getGet('exclude_payment_id') ?? 0);
        $rows = $this->getOpenInvoicesForCustomer($customerId, $excludePaymentId);
        $credits = $this->getUnallocatedCustomerPayments($customerId, $excludePaymentId);

        $postedCredit = 0.0;
        $draftPending = 0.0;
        foreach ($credits as $credit) {
            $unallocated = (float)($credit['unallocated_amount'] ?? 0);
            if (!empty($credit['is_posted'])) {
                $postedCredit += $unallocated;
            } else {
                $draftPending += $unallocated;
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => $rows,
            'credits' => $credits,
            'posted_credit_total' => round($postedCredit, 2),
            'draft_pending_total' => round($draftPending, 2),
            'date_format' => $this->getGlobalDateFormat(),
        ]);
    }

    public function index()
    {
        $db = Database::connect();
        $this->ensurePaymentCurrencySchema($db);
        $invoiceId = (int) ($this->request->getGet('invoice_id') ?? 0);
        $customerId = (int) ($this->request->getGet('customer_id') ?? 0);

        // Some installs may not have `source_account_id` column on `customer_payments`.
        $hasSourceAccount = false;
        try {
            $hasSourceAccount = (bool) $db->query("SHOW COLUMNS FROM customer_payments LIKE 'source_account_id'")->getRowArray();
        } catch (\Throwable $_) {
            $hasSourceAccount = false;
        }

        $selectSource = $hasSourceAccount ? 'a.name AS source_account_name, ' : "'' AS source_account_name, ";
        $joinAccount = $hasSourceAccount ? 'LEFT JOIN accounts a ON a.id = cp.source_account_id ' : '';

        $params = ['customer_payment'];
        $where = [];
        $allocJoin = '';

        if ($invoiceId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM customer_payment_allocations cpa_filter WHERE cpa_filter.payment_id = cp.id AND cpa_filter.invoice_id = ?)';
            $params[] = $invoiceId;
        }
        if ($customerId > 0) {
            $where[] = 'cp.customer_id = ?';
            $params[] = $customerId;
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where) . ' ';
        }

        $rows = $db->query(
            'SELECT cp.*, c.name AS customer_name, ' . $selectSource
            . '(SELECT COUNT(*) FROM document_attachments da WHERE da.document_id = cp.id AND LOWER(da.document_type) = ?) AS attachment_count, '
            . '(SELECT COUNT(*) FROM customer_payment_allocations cpa WHERE cpa.payment_id = cp.id) AS allocation_count '
            . 'FROM customer_payments cp '
            . 'LEFT JOIN customers c ON c.id = cp.customer_id '
            . $joinAccount
            . $allocJoin
            . $whereSql
            . 'ORDER BY cp.payment_date DESC, cp.id DESC',
            $params
        )->getResultArray();

        $filteredInvoice = null;
        if ($invoiceId > 0 && $db->tableExists('customer_invoices')) {
            try {
                $invCols = $db->getFieldNames('customer_invoices');
                $invNumExpr = in_array('invoice_number', $invCols, true) ? 'invoice_number' : "CONCAT('INV-', id)";
                $filteredInvoice = $db->query(
                    'SELECT id, customer_id, ' . $invNumExpr . ' AS invoice_number FROM customer_invoices WHERE id = ? LIMIT 1',
                    [$invoiceId]
                )->getRowArray();
            } catch (\Throwable $_) {
                $filteredInvoice = null;
            }
        }

        $drafts = [];
        $posted = [];
        $voided = [];

        foreach ($rows as $row) {
            $statusRaw = trim((string)($row['status'] ?? ''));
            if ($statusRaw !== '') {
                $status = strtolower($statusRaw);
            } else {
                $postedEntryId = (int)($row['posted_entry_id'] ?? 0);
                $status = $postedEntryId > 0 ? 'posted' : 'draft';
            }
            if ($status === 'posted') {
                $posted[] = $row;
            } elseif ($status === 'void') {
                $voided[] = $row;
            } else {
                $drafts[] = $row;
            }
        }

        return view('accounting/customer_payments/index', [
            'drafts' => $drafts,
            'posted' => $posted,
            'voided' => $voided,
            'filteredInvoice' => $filteredInvoice,
            'filterInvoiceId' => $invoiceId,
            'filterCustomerId' => $customerId,
        ]);
    }

    public function createDraft()
    {
        if (! $this->request->isAJAX()) {
            return $this->ajaxError('AJAX required');
        }

        $this->ensurePaymentCurrencySchema(Database::connect());

        $customerRaw = trim((string) ($_POST['customer_id'] ?? ($this->request->getPost('customer_id') ?? '')));
        if ($customerRaw === '' || ! preg_match('/^-?\d+$/', $customerRaw)) {
            return $this->ajaxError('Customer is required');
        }
        $customerId = (int) $customerRaw;
        if ($customerId < 0) {
            return $this->ajaxError('Customer is required');
        }

        $customerModel = new CustomerModel();
        $customer = $customerModel->where('id', $customerId)->first();
        if (! $customer) {
            return $this->ajaxError('Customer not found');
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

        $attachments = $this->request->getFileMultiple('attachments');
        $existingPaymentId = (int) ($this->request->getPost('payment_id') ?? 0);

        $preparedAllocations = [];
        $allocatedTotal = 0.0;
        $advanceAllocatedTotal = 0.0;
        foreach ($allocations as $alloc) {
            $invoiceId = (int) ($alloc['invoice_id'] ?? 0);
            $amount = $this->normalizeAmount($alloc['amount'] ?? 0);
            $cashAmount = $this->normalizeAmount($alloc['cash_amount'] ?? 0);
            $advanceAlloc = $this->normalizeAmount($alloc['advance_amount'] ?? 0);
            if ($invoiceId <= 0 || $amount <= 0) {
                continue;
            }
            $preparedAllocations[] = [
                'invoice_id' => $invoiceId,
                'amount' => $amount,
                'cash_amount' => $cashAmount,
                'advance_amount' => $advanceAlloc,
            ];
            $allocatedTotal += $amount;
            $advanceAllocatedTotal += $advanceAlloc;
        }

        if (!empty($preparedAllocations)) {
            $open = $this->getOpenInvoicesForCustomer($customerId, $existingPaymentId);
            $openMap = [];
            foreach ($open as $r) {
                $openMap[(int)($r['id'] ?? 0)] = (float)($r['outstanding'] ?? 0);
            }

            foreach ($preparedAllocations as $row) {
                $invoiceId = (int)$row['invoice_id'];
                $amount = (float)$row['amount'];
                $outstanding = (float)($openMap[$invoiceId] ?? 0);
                if ($outstanding <= 0) {
                    return $this->ajaxError('Invoice #' . $invoiceId . ' is not open for payment');
                }
                if ($amount - $outstanding > 0.005) {
                    return $this->ajaxError('Allocation exceeds outstanding for invoice #' . $invoiceId);
                }
            }
        }

        if ($allocatedTotal <= 0 && $advanceAmount <= 0 && $advanceAllocatedTotal <= 0) {
            return $this->ajaxError('Add at least one allocation or advance amount');
        }

        if ($allocatedTotal <= 0 && ($advanceAmount > 0 || $advanceAllocatedTotal > 0)) {
            $paymentType = 'advance';
        } else {
            $paymentType = $paymentType ?: 'settlement';
        }

        $dbForCurrency = Database::connect();
        $currency = strtoupper(trim((string) ($this->request->getPost('currency_code') ?? '')));
        if (!empty($preparedAllocations)) {
            $invoiceIds = array_values(array_unique(array_map(static function ($r) {
                return (int)($r['invoice_id'] ?? 0);
            }, $preparedAllocations)));
            $invoiceIds = array_values(array_filter($invoiceIds, static fn($v) => $v > 0));

            if (!empty($invoiceIds)) {
                try {
                    $rows = $dbForCurrency->query(
                        'SELECT DISTINCT UPPER(TRIM(COALESCE(currency_code, ""))) AS currency_code '
                        . 'FROM customer_invoices WHERE id IN (' . implode(',', array_map('intval', $invoiceIds)) . ') '
                        . 'AND COALESCE(currency_code, "") <> ""'
                    )->getResultArray();
                    $currencies = [];
                    foreach ($rows as $r) {
                        $c = strtoupper(trim((string)($r['currency_code'] ?? '')));
                        if ($c !== '') $currencies[] = $c;
                    }
                    $currencies = array_values(array_unique($currencies));
                    if (count($currencies) > 1) {
                        return $this->ajaxError('Selected invoices have mixed currencies. Create separate payments per currency.');
                    }
                    if (count($currencies) === 1) {
                        $currency = $currencies[0];
                    }
                } catch (\Throwable $_) {
                    // fallback below
                }
            }
        }

        if ($currency === '' && $existingPaymentId > 0) {
            try {
                $existing = $dbForCurrency->query('SELECT currency_code FROM customer_payments WHERE id = ? LIMIT 1', [$existingPaymentId])->getRowArray();
                $currency = strtoupper(trim((string)($existing['currency_code'] ?? '')));
            } catch (\Throwable $_) {
                $currency = '';
            }
        }

        if ($currency === '') {
            $currency = 'PKR';
        }

        $effectiveAdvance = $advanceAllocatedTotal > 0 ? $advanceAllocatedTotal : $advanceAmount;
        $paymentAmount = ($paymentType === 'advance') ? $effectiveAdvance : $allocatedTotal;
        $cashPortion = max(0.0, $allocatedTotal - $effectiveAdvance);

        // Do not block draft save when source account is not selected yet.
        // Enforce receiving account at posting time when cash/bank impact exists.

        $paymentModel = new CustomerPaymentModel();
        if ($paymentType !== 'advance' && $effectiveAdvance > 0) {
            $advanceBalance = $paymentModel->getCustomerAdvanceBalance($customerId);
            if ($effectiveAdvance > $advanceBalance) {
                return $this->ajaxError('Advance applied exceeds available advance balance');
            }
        }

        $notes = trim((string) ($this->request->getPost('notes') ?? ''));
        $memo = trim((string) ($this->request->getPost('memo') ?? ''));
        $createdBy = session()->get('user_id') ?? null;
        $now = date('Y-m-d H:i:s');

        $allocationModel = new CustomerPaymentAllocationModel();
        $db = Database::connect();
        $db->transBegin();

        try {
            try {
                $col = $db->query("SHOW COLUMNS FROM customer_payments LIKE 'advance_amount'")->getRowArray();
                if (!$col) {
                    $db->query("ALTER TABLE customer_payments ADD COLUMN advance_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER amount");
                }
            } catch (\Throwable $_) { }

            try {
                $col = $db->query("SHOW COLUMNS FROM customer_payments LIKE 'currency_code'")->getRowArray();
                if (!$col) {
                    $db->query("ALTER TABLE customer_payments ADD COLUMN currency_code VARCHAR(3) NULL AFTER payment_type");
                }
            } catch (\Throwable $_) { }

            try {
                $col = $db->query("SHOW COLUMNS FROM customer_payments LIKE 'source_account_id'")->getRowArray();
                if (!$col) {
                    $db->query("ALTER TABLE customer_payments ADD COLUMN source_account_id INT(11) NULL AFTER payment_method_id");
                }
            } catch (\Throwable $_) { }

            // Build insert payload only with columns that actually exist in the runtime table
            $cols = [];
            try {
                $cols = array_column($db->query("SHOW COLUMNS FROM customer_payments")->getResultArray(), 'Field');
            } catch (\Throwable $_) { $cols = []; }

            $paymentPayload = [];
            if (in_array('customer_id', $cols, true)) { $paymentPayload['customer_id'] = $customerId; }
            if (in_array('payment_date', $cols, true)) { $paymentPayload['payment_date'] = $paymentDate; }

            // Payment method: some installs store textual 'payment_method', others store 'payment_method_id'
            if (in_array('payment_method', $cols, true)) {
                $paymentPayload['payment_method'] = $method;
            } elseif (in_array('payment_method_id', $cols, true)) {
                // Resolve or create a payment method row
                $pmId = null;
                try {
                    $pmModel = new \App\Models\PaymentMethodModel();
                    $label = self::SUPPORTED_METHODS[$method] ?? $method;
                    $pm = $pmModel->where('method_name', $label)->first();
                    if (!$pm) {
                        // try raw slug match
                        $pm = $pmModel->where('method_name', $method)->first();
                    }
                    if ($pm) {
                        $pmId = (int) $pm['id'];
                    } else {
                        $ins = $pmModel->insert(['method_name' => $label, 'is_active' => 1], true);
                        if ($ins) { $pmId = (int) $ins; }
                    }
                } catch (\Throwable $_) { $pmId = null; }
                if ($pmId !== null) { $paymentPayload['payment_method_id'] = $pmId; }
            }

            if (in_array('payment_type', $cols, true)) { $paymentPayload['payment_type'] = $paymentType; }
            if (in_array('currency_code', $cols, true)) { $paymentPayload['currency_code'] = $currency; }
            if (in_array('amount', $cols, true)) { $paymentPayload['amount'] = $paymentAmount; }
            if (in_array('advance_amount', $cols, true)) { $paymentPayload['advance_amount'] = $effectiveAdvance; }
            if (in_array('source_account_id', $cols, true)) { $paymentPayload['source_account_id'] = $sourceAccountId; }
            if (in_array('memo', $cols, true)) { $paymentPayload['memo'] = $memo ?: null; }
            if (in_array('notes', $cols, true)) { $paymentPayload['notes'] = $notes ?: null; }
            if (in_array('status', $cols, true)) { $paymentPayload['status'] = 'draft'; }
            if (in_array('created_by', $cols, true)) { $paymentPayload['created_by'] = $createdBy; }
            if (in_array('created_at', $cols, true)) { $paymentPayload['created_at'] = $now; }

            if ($existingPaymentId > 0 && $paymentModel->find($existingPaymentId)) {
                // Update existing draft
                $paymentModel->update($existingPaymentId, $paymentPayload);
                $paymentId = $existingPaymentId;
                // remove existing allocations so we can reinsert
                try { $db->query('DELETE FROM customer_payment_allocations WHERE payment_id = ?', [$paymentId]); } catch (\Throwable $_) {}
            } else {
                $paymentId = $paymentModel->insert($paymentPayload, true);
            }

            if (! $paymentId) {
                $dbErr = [];
                try {
                    $dbErr = $paymentModel->db->error();
                } catch (\Throwable $_) { $dbErr = []; }
                $modelErr = [];
                try {
                    $modelErr = $paymentModel->errors();
                } catch (\Throwable $_) { $modelErr = []; }
                $msg = 'Failed to persist customer payment draft';
                $msg .= ' | db_error=' . json_encode($dbErr);
                $msg .= ' | model_errors=' . json_encode($modelErr);
                throw new \RuntimeException($msg);
            }

            // Resolve which columns actually exist in customer_payment_allocations
            $cpaActualCols = [];
            try { $cpaActualCols = $db->getFieldNames('customer_payment_allocations'); } catch (\Throwable $_) {}
            $hasAllocatedAmount = in_array('allocated_amount', $cpaActualCols, true);
            $hasAmount = in_array('amount', $cpaActualCols, true);
            $hasAdvanceAmount = in_array('advance_amount', $cpaActualCols, true);
            $hasCashAmount = in_array('cash_amount', $cpaActualCols, true);
            $hasCreatedBy = in_array('created_by', $cpaActualCols, true);

            foreach ($preparedAllocations as $row) {
                $insertData = [
                    'payment_id' => $paymentId,
                    'invoice_id' => $row['invoice_id'],
                    'created_at' => $now,
                ];
                // Write the allocation amount to whichever column(s) exist
                if ($hasAllocatedAmount) {
                    $insertData['allocated_amount'] = $row['amount'];
                } elseif ($hasAmount) {
                    $insertData['amount'] = $row['amount'];
                }
                if ($hasAdvanceAmount) {
                    $insertData['advance_amount'] = $row['advance_amount'];
                }
                if ($hasCashAmount) {
                    $insertData['cash_amount'] = $row['cash_amount'] ?? 0;
                }
                if ($hasCreatedBy) {
                    $insertData['created_by'] = $createdBy;
                }
                $inserted = $allocationModel->insert($insertData);
                if (!$inserted) {
                    log_message('error', 'CustomerPayment allocation insert failed: ' . json_encode($insertData) . ' errors: ' . json_encode($allocationModel->errors()));
                }
            }

            $db->transCommit();

            try {
                if (!empty($attachments)) {
                    $validAttachments = array_filter($attachments, function($f) {
                        return $f instanceof \CodeIgniter\HTTP\Files\UploadedFile
                            && $f->isValid()
                            && $f->getSize() > 0
                            && $f->getClientName() !== '';
                    });
                    if (!empty($validAttachments)) {
                        $svc = new DocumentAttachmentService();
                        $svc->storeMany('customer_payment', (int) $paymentId, $validAttachments, $createdBy ? (int) $createdBy : null);
                    }
                }
            } catch (\Throwable $e) {
                log_message('error', 'CustomerPayment attachment upload failed for payment ' . $paymentId . ': ' . $e->getMessage());
            }

            return $this->response->setJSON([
                'success' => true,
                'payment_id' => (int) $paymentId,
                'message' => 'Draft customer payment saved',
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

        $paymentModel = new CustomerPaymentModel();
        $payment = $paymentModel->find($paymentId);
        if (!$payment) {
            return $this->ajaxError('Customer payment not found', 404);
        }

        $attachments = $this->request->getFileMultiple('attachments');
        $createdBy = session()->get('user_id') ?? null;
        try {
            if (!empty($attachments)) {
                $svc = new DocumentAttachmentService();
                $svc->storeMany('customer_payment', $paymentId, $attachments, $createdBy ? (int) $createdBy : null);
            }
        } catch (\Throwable $e) {
            log_message('error', 'CustomerPayment attachment upload failed on confirm for payment ' . $paymentId . ': ' . $e->getMessage());
        }

        $posting = new AccountingPostingService();
        $result = $posting->postCustomerPayment($paymentId);
        $result['payment_id'] = $paymentId;

        return $this->response->setJSON($result);
    }

    public function view($id)
    {
        $paymentId = (int) $id;
        if ($paymentId <= 0) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Customer payment not found');
        }

        $db = Database::connect();
        // Only join accounts if `source_account_id` exists on the payments table
        $hasSourceAccount = false;
        try {
            $hasSourceAccount = (bool) $db->query("SHOW COLUMNS FROM customer_payments LIKE 'source_account_id'")->getRowArray();
        } catch (\Throwable $_) {
            $hasSourceAccount = false;
        }

        $hasPaymentMethods = false;
        try {
            $hasPaymentMethods = $db->tableExists('payment_methods');
        } catch (\Throwable $_) {
            $hasPaymentMethods = false;
        }

        $paymentMethodJoin = $hasPaymentMethods ? 'LEFT JOIN payment_methods pm ON pm.id = cp.payment_method_id ' : '';
        $paymentMethodExpr = $hasPaymentMethods ? 'pm.method_name AS payment_method_name, ' : "'' AS payment_method_name, ";

        if ($hasSourceAccount) {
            $payment = $db->query(
                'SELECT cp.*, c.name AS customer_name, ' . $paymentMethodExpr . 'a.name AS source_account_name, a.account_number AS source_account_number '
                . 'FROM customer_payments cp '
                . 'LEFT JOIN customers c ON c.id = cp.customer_id '
                . $paymentMethodJoin
                . 'LEFT JOIN accounts a ON a.id = cp.source_account_id '
                . 'WHERE cp.id = ? LIMIT 1',
                [$paymentId]
            )->getRowArray();
        } else {
            $payment = $db->query(
                "SELECT cp.*, c.name AS customer_name, " . $paymentMethodExpr . "'' AS source_account_name, '' AS source_account_number "
                . 'FROM customer_payments cp '
                . 'LEFT JOIN customers c ON c.id = cp.customer_id '
                . $paymentMethodJoin
                . 'WHERE cp.id = ? LIMIT 1',
                [$paymentId]
            )->getRowArray();
        }

        if (!$payment) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Customer payment not found');
        }

        // Build invoice fields dynamically to avoid referencing missing columns on older schemas
        // Determine which columns exist on customer_invoices

        try {
            $invCols = array_column($db->query("SHOW COLUMNS FROM customer_invoices")->getResultArray(), 'Field');
        } catch (\Throwable $_) {
            $invCols = [];
        }

          $invoiceNumberExpr = in_array('invoice_number', $invCols, true) ? 'ci.invoice_number' : "'' AS invoice_number";
          $issueDateExpr = in_array('issue_date', $invCols, true) ? 'ci.issue_date' : "NULL AS issue_date";
          $currencyExpr = in_array('currency_code', $invCols, true) ? 'ci.currency_code' : "'' AS currency_code";

        // invoice_total: prefer total_amount, then total, then amount
        $totalField = null;
        foreach (['total_amount','total','amount'] as $f) {
            if (in_array($f, $invCols, true)) { $totalField = $f; break; }
        }
        $invoiceTotalExpr = $totalField ? "ci.$totalField AS invoice_total" : "0 AS invoice_total";

        // invoice_balance: compute as total_amount minus sum of all posted allocations for that invoice
        if ($totalField) {
            $invoiceBalanceExpr = "GREATEST(0, COALESCE(ci.$totalField, 0) - COALESCE((SELECT SUM(cpa2.allocated_amount) FROM customer_payment_allocations cpa2 INNER JOIN customer_payments cp2 ON cp2.id = cpa2.payment_id WHERE cpa2.invoice_id = ci.id AND cp2.posted_entry_id IS NOT NULL AND cp2.posted_entry_id > 0), 0)) AS invoice_balance";
        } else {
            $invoiceBalanceExpr = "0 AS invoice_balance";
        }

        $invoiceStatusExpr = in_array('status', $invCols, true) ? 'ci.status AS invoice_status' : "'' AS invoice_status";

        $allocations = $db->query(
              'SELECT cpa.*, ' . $invoiceNumberExpr . ', ' . $issueDateExpr . ', ' . $currencyExpr . ', ' . $invoiceTotalExpr . ', ' . $invoiceBalanceExpr . ', ' . $invoiceStatusExpr . ' '
            . 'FROM customer_payment_allocations cpa '
            . 'LEFT JOIN customer_invoices ci ON ci.id = cpa.invoice_id '
            . 'WHERE cpa.payment_id = ? '
            . 'ORDER BY cpa.id ASC',
            [$paymentId]
        )->getResultArray();

        $attachments = [];
        try {
            $attachments = $db->query(
                "SELECT id,document_type,document_id,file_path,original_name,mime_type,uploaded_at FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                [$paymentId, 'customer_payment']
            )->getResultArray();
        } catch (\Throwable $e) {
            $attachments = [];
        }

        $paymentStatus = strtolower(trim((string)($payment['status'] ?? 'draft')));
        if ($paymentStatus === '') {
            $paymentStatus = 'draft';
        }

        $openInvoices = $paymentStatus === 'draft'
            ? $this->getOpenInvoicesForCustomer((int)($payment['customer_id'] ?? 0), $paymentId)
            : [];

        $displayCurrency = strtoupper(trim((string)($payment['currency_code'] ?? '')));
        $allocCurrencies = [];
        foreach ($allocations as $alloc) {
            $candidate = strtoupper(trim((string)($alloc['currency_code'] ?? '')));
            if ($candidate !== '') {
                $allocCurrencies[] = $candidate;
            }
        }
        $allocCurrencies = array_values(array_unique($allocCurrencies));
        if (count($allocCurrencies) === 1) {
            $displayCurrency = $allocCurrencies[0];
        }
        if ($displayCurrency === '') {
            foreach ($allocations as $alloc) {
                $candidate = strtoupper(trim((string)($alloc['currency_code'] ?? '')));
                if ($candidate !== '') {
                    $displayCurrency = $candidate;
                    break;
                }
            }
        }
        if ($displayCurrency === '' && $paymentStatus === 'draft' && count($openInvoices) === 1) {
            $candidate = strtoupper(trim((string)($openInvoices[0]['currency_code'] ?? '')));
            if ($candidate !== '') {
                $displayCurrency = $candidate;
            }
        }
        if ($displayCurrency === '') {
            $displayCurrency = 'PKR';
        }

        return view('accounting/customer_payments/view', [
              'payment'         => $payment,
              'allocations'     => $allocations,
              'attachments'     => $attachments,
              'openInvoices'    => $openInvoices,
              'displayCurrency' => $displayCurrency,
          ]);
    }

    public function delete($id)
    {
        $paymentId = (int) $id;
        if ($paymentId <= 0) {
            return $this->ajaxError('Invalid payment id', 400);
        }

        $db = Database::connect();
        $paymentModel = new CustomerPaymentModel();
        $p = $paymentModel->find($paymentId);
        if (! $p) {
            return $this->ajaxError('Customer payment not found', 404);
        }

        // Prefer soft-void if `status` column exists
        $hasStatus = false;
        try {
            $hasStatus = (bool) $db->query("SHOW COLUMNS FROM customer_payments LIKE 'status'")->getRowArray();
        } catch (\Throwable $_) { $hasStatus = false; }

        try {
            if ($hasStatus) {
                $paymentModel->update($paymentId, ['status' => 'void']);
            } else {
                // delete allocations, attachments, then payment row
                try { $db->query('DELETE FROM customer_payment_allocations WHERE payment_id = ?', [$paymentId]); } catch (\Throwable $_) {}
                try { $db->query('DELETE FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ?', [$paymentId, 'customer_payment']); } catch (\Throwable $_) {}
                $paymentModel->delete($paymentId);
            }
        } catch (\Throwable $e) {
            return $this->ajaxError('Failed to delete payment: ' . $e->getMessage(), 500);
        }

        return $this->response->setJSON(['success' => true, 'message' => 'Payment deleted']);
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

    private function ensurePaymentCurrencySchema($db): void
    {
        try {
            $hasCurrency = (bool) $db->query("SHOW COLUMNS FROM customer_payments LIKE 'currency_code'")->getRowArray();
            if (! $hasCurrency) {
                $db->query("ALTER TABLE customer_payments ADD COLUMN currency_code VARCHAR(3) NULL AFTER payment_method_id");
            }
        } catch (\Throwable $_) {
            return;
        }

        // Backfill missing payment currencies from linked invoice currencies.
        try {
            $db->query(
                "UPDATE customer_payments cp "
                . "JOIN customer_payment_allocations cpa ON cpa.payment_id = cp.id "
                . "JOIN customer_invoices ci ON ci.id = cpa.invoice_id "
                . "SET cp.currency_code = UPPER(TRIM(ci.currency_code)) "
                . "WHERE (cp.currency_code IS NULL OR cp.currency_code = '') "
                . "AND COALESCE(ci.currency_code, '') <> ''"
            );
        } catch (\Throwable $_) {
            // best effort
        }
    }

    /**
     * Compute unpaid/open invoices with outstanding balances.
     */
    private function getOpenInvoicesForCustomer(int $customerId, int $excludePaymentId = 0): array
    {
        $db = Database::connect();
        if (! $db->tableExists('customer_invoices')) {
            return [];
        }

        try {
            $invCols = $db->getFieldNames('customer_invoices');
        } catch (\Throwable $_) {
            $invCols = [];
        }

        $totalField = null;
        foreach (['total_amount', 'total', 'amount'] as $f) {
            if (in_array($f, $invCols, true)) {
                $totalField = $f;
                break;
            }
        }
        if ($totalField === null) {
            return [];
        }

        $invoiceNumberExpr = in_array('invoice_number', $invCols, true) ? 'ci.invoice_number' : "CONCAT('INV-', ci.id) AS invoice_number";
        $issueDateExpr = in_array('issue_date', $invCols, true) ? 'ci.issue_date' : 'NULL AS issue_date';
        $dueDateExpr = in_array('due_date', $invCols, true) ? 'ci.due_date' : 'NULL AS due_date';
        $statusExpr = in_array('status', $invCols, true) ? 'ci.status' : "'issued' AS status";
        $currencyExpr = in_array('currency_code', $invCols, true) ? 'ci.currency_code' : "'' AS currency_code";
        $hasSalesOrder = in_array('sales_order_id', $invCols, true);
        $hasInvoiceNumber = in_array('invoice_number', $invCols, true);
        $salesOrderExpr = 'NULL AS sales_order_id';

        $soCols = [];
        if ($hasSalesOrder && $db->tableExists('sales_orders')) {
            try {
                $soCols = $db->getFieldNames('sales_orders');
            } catch (\Throwable $_) {
                $soCols = [];
            }
        }
        $soNumExpr = "NULL AS sales_order_number";
        $soJoin = '';
        if (!empty($soCols) && in_array('order_number', $soCols, true)) {
            if ($hasSalesOrder) {
                $salesOrderExpr = 'so.id AS sales_order_id';
                $soNumExpr = 'so.order_number AS sales_order_number';
                $soJoin = 'LEFT JOIN sales_orders so ON so.id = ci.sales_order_id ';
            } elseif ($hasInvoiceNumber) {
                // Fallback mapping: invoices usually follow INV-{SO_ORDER_NUMBER}
                $salesOrderExpr = 'so.id AS sales_order_id';
                $soNumExpr = 'so.order_number AS sales_order_number';
                $soJoin = "LEFT JOIN sales_orders so ON so.order_number = CASE WHEN ci.invoice_number LIKE 'INV-%' THEN SUBSTRING(ci.invoice_number, 5) ELSE '' END ";
            }
        }

        // Determine correct allocation amount column
        $allocExpr = 'cpa.allocated_amount';
        if ($db->tableExists('customer_payment_allocations')) {
            try {
                $allocCols = $db->getFieldNames('customer_payment_allocations');
                if (!in_array('allocated_amount', $allocCols, true)) {
                    if (in_array('amount_allocated', $allocCols, true)) {
                        $allocExpr = 'cpa.amount_allocated';
                    } elseif (in_array('amount', $allocCols, true)) {
                        $allocExpr = 'cpa.amount';
                    }
                }
            } catch (\Throwable $_) {
                // Default to allocated_amount
            }
        }

        // Determine payment status check (use posted_entry_id if status column doesn't exist)
        $payCols = [];
        if ($db->tableExists('customer_payments')) {
            try {
                $payCols = $db->getFieldNames('customer_payments');
            } catch (\Throwable $_) {
                $payCols = [];
            }
        }
        
        $statusCheck = '';
        if (in_array('status', $payCols, true)) {
            $statusCheck = " AND LOWER(COALESCE(cp.status, '')) = 'posted'";
        } elseif (in_array('posted_entry_id', $payCols, true)) {
            $statusCheck = ' AND (cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)';
        }

        $paidQuery =
            '(SELECT COALESCE(SUM(' . $allocExpr . '),0) '
            . 'FROM customer_payment_allocations cpa '
            . 'INNER JOIN customer_payments cp ON cp.id = cpa.payment_id '
            . 'WHERE cpa.invoice_id = ci.id' . $statusCheck;
        if ($excludePaymentId > 0) {
            $paidQuery .= ' AND cp.id <> ' . (int)$excludePaymentId;
        }
        $paidQuery .= ')';

        $where = 'ci.customer_id = ?';
        if (in_array('deleted_at', $invCols, true)) {
            $where .= ' AND ci.deleted_at IS NULL';
        }
        if (in_array('status', $invCols, true)) {
            $where .= " AND LOWER(COALESCE(ci.status, '')) NOT IN ('cancelled','void')";
        }

        $rows = $db->query(
            'SELECT ci.id, '
            . $invoiceNumberExpr . ', '
            . $issueDateExpr . ', '
            . $dueDateExpr . ', '
            . $statusExpr . ', '
            . $currencyExpr . ', '
            . 'ci.' . $totalField . ' AS total_amount, '
            . $salesOrderExpr . ', '
            . $soNumExpr . ', '
            . $paidQuery . ' AS paid_amount '
            . 'FROM customer_invoices ci '
            . $soJoin
            . 'WHERE ' . $where . ' '
            . 'ORDER BY ci.id DESC',
            [$customerId]
        )->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $total = max(0.0, (float)($row['total_amount'] ?? 0));
            $paid = max(0.0, (float)($row['paid_amount'] ?? 0));
            $outstanding = max(0.0, round($total - $paid, 2));
            if ($outstanding <= 0.005) {
                continue;
            }

            $row['paid_amount'] = $paid;
            $row['outstanding'] = $outstanding;
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Returns customer payments that still have unallocated balance.
     * Fully allocated payments are excluded.
     */
    private function getUnallocatedCustomerPayments(int $customerId, int $excludePaymentId = 0): array
    {
        $db = Database::connect();
        if (!$db->tableExists('customer_payments')) {
            return [];
        }

        $payCols = [];
        try { $payCols = $db->getFieldNames('customer_payments'); } catch (\Throwable $_) { $payCols = []; }

        $allocExpr = 'cpa.allocated_amount';
        if ($db->tableExists('customer_payment_allocations')) {
            try {
                $allocCols = $db->getFieldNames('customer_payment_allocations');
                if (!in_array('allocated_amount', $allocCols, true)) {
                    if (in_array('amount_allocated', $allocCols, true)) {
                        $allocExpr = 'cpa.amount_allocated';
                    } elseif (in_array('amount', $allocCols, true)) {
                        $allocExpr = 'cpa.amount';
                    }
                }
            } catch (\Throwable $_) {
                // keep default
            }
        }

        $statusExpr = in_array('status', $payCols, true)
            ? 'LOWER(COALESCE(cp.status, \"draft\"))'
            : '(CASE WHEN cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0 THEN \"posted\" ELSE \"draft\" END)';

        $currencyExpr = in_array('currency_code', $payCols, true) ? 'cp.currency_code' : "'' AS currency_code";

        $rows = $db->query(
            'SELECT cp.id, cp.payment_date, cp.amount, ' . $currencyExpr . ', '
            . $statusExpr . ' AS status_key, '
            . '(SELECT COALESCE(SUM(' . $allocExpr . '),0) FROM customer_payment_allocations cpa WHERE cpa.payment_id = cp.id) AS allocated_total '
            . 'FROM customer_payments cp '
            . 'WHERE cp.customer_id = ? '
            . ($excludePaymentId > 0 ? 'AND cp.id <> ' . (int)$excludePaymentId . ' ' : '')
            . 'ORDER BY cp.payment_date ASC, cp.id ASC',
            [$customerId]
        )->getResultArray();

        $out = [];
        foreach ($rows as $row) {
            $total = max(0.0, (float)($row['amount'] ?? 0));
            $allocated = max(0.0, (float)($row['allocated_total'] ?? 0));
            $unallocated = max(0.0, round($total - $allocated, 2));
            if ($unallocated <= 0.005) {
                continue;
            }

            $statusKey = strtolower(trim((string)($row['status_key'] ?? 'draft')));
            $isPosted = $statusKey === 'posted';

            $row['allocated_total'] = $allocated;
            $row['unallocated_amount'] = $unallocated;
            $row['is_posted'] = $isPosted ? 1 : 0;
            $row['status_label'] = $isPosted ? 'Posted' : 'Draft';
            $out[] = $row;
        }

        return $out;
    }

    private function getGlobalDateFormat(): string
    {
        $default = 'Y-m-d';
        try {
            if (!$this->db->tableExists('system_settings')) {
                return $default;
            }
            $row = $this->db->table('system_settings')->where('setting_key', 'global_date_format')->get()->getRowArray();
            $fmt = trim((string)($row['setting_value'] ?? ''));
            $allowed = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'];
            return in_array($fmt, $allowed, true) ? $fmt : $default;
        } catch (\Throwable $_) {
            return $default;
        }
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
