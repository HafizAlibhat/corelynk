<?php

namespace App\Services;

use App\Models\Accounting\AccountModel;
use App\Models\Accounting\ChequeModel;
use App\Models\Accounting\JournalEntryModel;
use App\Models\Accounting\JournalLineModel;
use App\Models\CustomerInvoiceModel;
use App\Models\CustomerPaymentAllocationModel;
use App\Models\CustomerPaymentModel;
use App\Models\VendorModel;
use App\Models\VendorBillModel;
use App\Models\VendorPaymentAllocationModel;
use App\Models\VendorPaymentModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Centralized accounting posting service.
 *
 * Phase-1 goal: documents (invoices/receipts) should not directly write to accounting tables.
 * Only this service is responsible for creating journal_entries + journal_lines and linking posted_entry_id.
 */
class AccountingPostingService
{
    private BaseConnection $db;
    private const VENDOR_PAYMENT_METHODS = ['cash', 'bank', 'online_transfer', 'cheque', 'advance'];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Post a CONFIRMED customer invoice into journals.
     *
     * Rules:
     * - Only post when invoice status = confirmed
     * - Do nothing if posted_entry_id already exists
     * - Create ONE journal_entry and related journal_lines
     * - Debit Accounts Receivable
     * - Credit Sales Revenue
     * - Credit Taxes Payable (if invoice tax_total > 0)
     * - Use invoice currency_code, and store fx_rate/base_amount for each line
     * - Save journal_entries.id into customer_invoices.posted_entry_id
     */
    public function postCustomerInvoice(int $invoiceId): array
    {
        $invoiceId = (int)$invoiceId;
        if ($invoiceId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'Invalid invoice id'];
        }

        $invModel = new CustomerInvoiceModel();
        $invoice = $invModel->find($invoiceId);
        if (!$invoice) {
            return ['success' => false, 'skipped' => false, 'message' => 'Invoice not found'];
        }

        $status = strtolower((string)($invoice['status'] ?? ''));
        if ($status !== 'confirmed') {
            return ['success' => true, 'skipped' => true, 'message' => 'Skipped: invoice not confirmed'];
        }

        $already = (int)($invoice['posted_entry_id'] ?? 0);
        if ($already > 0) {
            log_message('debug', 'AccountingPostingService: invoice ' . $invoiceId . ' skipped (already posted_entry_id=' . $already . ')');
            return ['success' => true, 'skipped' => true, 'posted_entry_id' => $already, 'message' => 'Skipped: already posted'];
        }

        $currency = strtoupper(trim((string)($invoice['currency_code'] ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        // IMPORTANT: we do not assume invoice table has fx_rate. For Phase-1 we treat base currency = PKR.
        // When invoice is in PKR: fx_rate=1.00 and base_amount = amount
        // When invoice is in USD: caller should have already stored PKR-equivalent in base_amount elsewhere; since we lack that,
        // we keep fx_rate=1 and base_amount=amount to avoid breaking existing behavior.
        // Later phases can extend to fetch/store invoice-level FX.
        $fxRate = 1.0;

        $total = (float)($invoice['total_amount'] ?? 0);
        $taxTotal = (float)($invoice['tax_total'] ?? 0);
        $netRevenue = max(0.0, $total - $taxTotal);

        // Resolve required accounts
        $arId = $this->findAccountIdByCodeOrName(['1200'], ['accounts receivable']);
        $salesId = $this->findAccountIdByCodeOrName(['4000'], ['sales revenue', 'revenue']);
        $taxPayableId = $this->findAccountIdByCodeOrName(['2200'], ['taxes payable', 'tax payable', 'sales tax payable']);

        if (!$arId || !$salesId || (!$taxPayableId && $taxTotal > 0)) {
            return [
                'success' => false,
                'skipped' => false,
                'message' => 'Missing required account(s) for posting (AR/Sales/Tax Payable).',
                'missing' => [
                    'ar' => !$arId,
                    'sales' => !$salesId,
                    'tax_payable' => ($taxTotal > 0 && !$taxPayableId),
                ],
            ];
        }

        log_message('debug', 'AccountingPostingService: posting invoice ' . $invoiceId . ' start currency=' . $currency . ' total=' . $total . ' tax=' . $taxTotal);

        $jeModel = new JournalEntryModel();
        $jlModel = new JournalLineModel();

        $this->db->transBegin();
        try {
            $memo = 'Customer Invoice #' . ($invoice['invoice_number'] ?? $invoiceId);
            $entryDate = $invoice['issue_date'] ?? date('Y-m-d');

            $jeId = $jeModel->insert([
                'entry_date' => $entryDate,
                'memo' => $memo,
                'currency_code' => $currency,
                'total_debits' => $total,
                'total_credits' => $total,
                // source_type/source_id columns may exist (migration adds them)
                'source_type' => 'invoice',
                'source_id' => $invoiceId,
            ], true);

            if (!$jeId) {
                throw new \RuntimeException('Failed to create journal entry: ' . json_encode($jeModel->errors()));
            }

            // Line A: DR Accounts Receivable (full invoice amount)
            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $arId,
                'description' => $memo,
                'debit' => $this->roundMoney($total),
                'credit' => 0,
                'currency_code' => $currency,
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($total * $fxRate),
            ]);

            // Line B: CR Sales Revenue (net of tax)
            if ($netRevenue > 0) {
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $salesId,
                    'description' => $memo,
                    'debit' => 0,
                    'credit' => $this->roundMoney($netRevenue),
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($netRevenue * $fxRate),
                ]);
            }

            // Line C: CR Taxes Payable (if tax exists)
            if ($taxTotal > 0) {
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $taxPayableId,
                    'description' => $memo,
                    'debit' => 0,
                    'credit' => $this->roundMoney($taxTotal),
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($taxTotal * $fxRate),
                ]);
            }

            // Link invoice to posted journal entry
            $invModel->update($invoiceId, ['posted_entry_id' => $jeId]);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $this->db->transCommit();
            log_message('debug', 'AccountingPostingService: invoice ' . $invoiceId . ' posted journal_entry_id=' . $jeId);

            return ['success' => true, 'skipped' => false, 'posted_entry_id' => (int)$jeId, 'message' => 'Posted'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'AccountingPostingService: invoice posting failed invoice_id=' . $invoiceId . ' err=' . $e->getMessage());
            return ['success' => false, 'skipped' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Post a customer receipt against an invoice.
     *
     * Input example:
     * [
     *   'invoice_id' => 123,
     *   'received_amount' => 1000,
     *   'bank_charges' => 0,
     *   'withholding_tax' => 0,
     *   'bank_account_id' => 23,
     *   'currency_code' => 'USD',
     *   'fx_rate' => 280.5
     * ]
     *
     * Journal logic:
     * - Debit Bank (received_amount)
     * - Debit Bank Charges Expense
     * - Debit Withholding Tax Receivable
     * - Credit Accounts Receivable (full invoice amount)
     *
     * Notes:
     * - This method writes ONLY accounting; it does not create customer_payments rows (Phase-1).
     * - It does not touch cheque posting.
     */
    public function postCustomerReceipt(array $data): array
    {
        $invoiceId = (int)($data['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'invoice_id is required'];
        }

        $received = (float)($data['received_amount'] ?? 0);
        $bankCharges = (float)($data['bank_charges'] ?? 0);
        $wht = (float)($data['withholding_tax'] ?? 0);

        $bankAccountId = (int)($data['bank_account_id'] ?? 0);
        if ($bankAccountId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'bank_account_id is required'];
        }

        $currency = strtoupper(trim((string)($data['currency_code'] ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $fxRate = (float)($data['fx_rate'] ?? 1);
        if ($fxRate <= 0) {
            $fxRate = 1.0;
        }

        $invModel = new CustomerInvoiceModel();
        $invoice = $invModel->find($invoiceId);
        if (!$invoice) {
            return ['success' => false, 'skipped' => false, 'message' => 'Invoice not found'];
        }

        $invoiceCurrency = strtoupper(trim((string)($invoice['currency_code'] ?? 'PKR')));
        $invoiceTotal = (float)($invoice['total_amount'] ?? 0);

        // Resolve accounts
        $arId = $this->findAccountIdByCodeOrName(['1200'], ['accounts receivable']);

        // Bank account: use the provided bank_account_id directly (it maps to accounts.id)
        $bankId = $bankAccountId;

        // Bank charges and withholding: best-effort by code/name; fall back to existing seeded/special accounts if present.
        $bankChargesId = $this->findAccountIdByCodeOrName(['5050','5051','5054','5056'], ['bank fee', 'bank fees', 'bank charges']);
        $whtRecvId = $this->findAccountIdByCodeOrName(['1104','1105'], ['withholding', 'wht', 'withholding tax']);

        if (!$arId) {
            return ['success' => false, 'skipped' => false, 'message' => 'Missing Accounts Receivable account (code 1200)'];
        }

        if ($bankCharges > 0 && !$bankChargesId) {
            return ['success' => false, 'skipped' => false, 'message' => 'Missing Bank Charges account (expected code 5050 or name Bank Fees/Charges)'];
        }

        if ($wht > 0 && !$whtRecvId) {
            return ['success' => false, 'skipped' => false, 'message' => 'Missing Withholding Tax Receivable account (expected code 1104/1105 or name contains WHT)'];
        }

        log_message('debug', 'AccountingPostingService: posting receipt start invoice_id=' . $invoiceId . ' recv=' . $received . ' charges=' . $bankCharges . ' wht=' . $wht . ' cur=' . $currency . ' fx=' . $fxRate);

        $jeModel = new JournalEntryModel();
        $jlModel = new JournalLineModel();

        $this->db->transBegin();
        try {
            $memo = 'Customer Receipt for Invoice #' . ($invoice['invoice_number'] ?? $invoiceId);
            $entryDate = date('Y-m-d');

            // For now we store receipt in the currency provided by caller; in later phases we can enforce invoice currency.
            // Totals are based on INVOICE total (credit AR full invoice amount by requirement).
            $totalDebit = $received + $bankCharges + $wht;
            $totalCredit = $invoiceTotal;

            $jeId = $jeModel->insert([
                'entry_date' => $entryDate,
                'memo' => $memo,
                'currency_code' => $currency,
                'total_debits' => $this->roundMoney($totalDebit),
                'total_credits' => $this->roundMoney($totalCredit),
                'source_type' => 'payment',
                'source_id' => $invoiceId,
            ], true);

            if (!$jeId) {
                throw new \RuntimeException('Failed to create journal entry: ' . json_encode($jeModel->errors()));
            }

            // DR Bank
            if ($received > 0) {
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $bankId,
                    'description' => $memo,
                    'debit' => $this->roundMoney($received),
                    'credit' => 0,
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($received * $fxRate),
                ]);
            }

            // DR Bank Charges Expense
            if ($bankCharges > 0) {
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $bankChargesId,
                    'description' => 'Bank Charges - ' . $memo,
                    'debit' => $this->roundMoney($bankCharges),
                    'credit' => 0,
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($bankCharges * $fxRate),
                ]);
            }

            // DR Withholding Tax Receivable
            if ($wht > 0) {
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $whtRecvId,
                    'description' => 'Withholding Tax - ' . $memo,
                    'debit' => $this->roundMoney($wht),
                    'credit' => 0,
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($wht * $fxRate),
                ]);
            }

            // CR Accounts Receivable (full invoice amount) - requirement
            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $arId,
                'description' => $memo,
                'debit' => 0,
                'credit' => $this->roundMoney($invoiceTotal),
                'currency_code' => $invoiceCurrency ?: $currency,
                // We use the provided fx_rate for base conversion; if invoice currency differs, later phase should reconcile.
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($invoiceTotal * $fxRate),
            ]);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $this->db->transCommit();
            log_message('debug', 'AccountingPostingService: receipt posted journal_entry_id=' . $jeId . ' invoice_id=' . $invoiceId);

            return ['success' => true, 'skipped' => false, 'journal_entry_id' => (int)$jeId, 'message' => 'Posted'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'AccountingPostingService: receipt posting failed invoice_id=' . $invoiceId . ' err=' . $e->getMessage());
            return ['success' => false, 'skipped' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Post a customer payment draft.
     *
     * Accounting rules:
     * - Settlement payment: DR Cash/Bank (+ DR Customer Advances used), CR Accounts Receivable
     * - Advance receipt:   DR Cash/Bank, CR Customer Advances (liability)
     * - Payment is marked posted only after successful journal creation.
     */
    public function postCustomerPayment(int $paymentId): array
    {
        if ($paymentId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'payment_id is required'];
        }

        $paymentModel = new CustomerPaymentModel();
        $allocationModel = new CustomerPaymentAllocationModel();
        $payment = $paymentModel->find($paymentId);
        if (!$payment) {
            return ['success' => false, 'skipped' => false, 'message' => 'Customer payment not found'];
        }

        $already = (int)($payment['posted_entry_id'] ?? 0);
        $status = strtolower(trim((string)($payment['status'] ?? '')));
        if ($already > 0 || $status === 'posted') {
            return [
                'success' => true,
                'skipped' => true,
                'journal_entry_id' => $already > 0 ? $already : null,
                'customer_payment_id' => $paymentId,
                'message' => 'Customer payment already posted',
            ];
        }

        $allocRows = $allocationModel->where('payment_id', $paymentId)->findAll();
        $allocatedTotal = 0.0;
        $advanceApplied = 0.0;
        foreach ($allocRows as $row) {
            $amt = $this->resolveFirstPositiveAmount($row, ['amount', 'amount_allocated', 'allocated_amount']);
            $allocatedTotal += max(0.0, $amt);
            $advanceApplied += max(0.0, (float)($row['advance_amount'] ?? 0));
        }

        $paymentType = strtolower(trim((string)($payment['payment_type'] ?? 'settlement')));
        if ($paymentType === '') {
            $paymentType = 'settlement';
        }

        $cashTotal = max(0.0, $allocatedTotal - $advanceApplied);
        $sourceAccountId = (int)($payment['source_account_id'] ?? 0);
        $currency = strtoupper(trim((string)($payment['currency_code'] ?? '')));

        // Settlement payments must follow allocated invoice currency.
        $isAdvanceOnlyCandidate = ($paymentType === 'advance' && $allocatedTotal <= 0.0001);
        if (!$isAdvanceOnlyCandidate && !empty($allocRows)) {
            try {
                $invoiceIds = [];
                foreach ($allocRows as $row) {
                    $iid = (int)($row['invoice_id'] ?? 0);
                    if ($iid > 0) $invoiceIds[] = $iid;
                }
                $invoiceIds = array_values(array_unique($invoiceIds));
                if (!empty($invoiceIds)) {
                    $rows = $this->db->query(
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
                        return ['success' => false, 'skipped' => false, 'message' => 'Allocated invoices contain mixed currencies. Post separate payments by currency.'];
                    }
                    if (count($currencies) === 1) {
                        $currency = $currencies[0];
                    }
                }
            } catch (\Throwable $_) {
                // fallback below
            }
        }

        if ($currency === '') {
            $currency = 'PKR';
        }

        $arId = $this->findAccountIdByCodeOrName(['1200'], ['accounts receivable', 'trade receivables']);
        $customerAdvanceId = $this->findAccountIdByCodeOrName(
            ['2300', '2100', '2050'],
            ['customer advance', 'customer advances', 'unearned revenue', 'deferred revenue']
        );

        $entryDate = $payment['payment_date'] ?? date('Y-m-d');
        $memo = trim((string)($payment['memo'] ?? ''));
        if ($memo === '') {
            $memo = 'Customer Payment #' . $paymentId;
        }

        $isAdvanceOnly = ($paymentType === 'advance' && $allocatedTotal <= 0.0001);
        $advanceOnlyAmount = $this->resolveFirstPositiveAmount($payment, ['advance_amount', 'amount']);

        if ($isAdvanceOnly) {
            if ($advanceOnlyAmount <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Advance amount is zero'];
            }
            if ($sourceAccountId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Source account is required for advance receipt'];
            }
            if ($customerAdvanceId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Customer advance liability account is not configured'];
            }
        } else {
            if ($allocatedTotal <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'No payment allocations found'];
            }
            if ($arId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Accounts Receivable account not configured'];
            }
            if ($cashTotal > 0 && $sourceAccountId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Receiving account is required. Open Edit Draft and select the cash/bank account where this payment was received.'];
            }
            if ($advanceApplied > 0 && $customerAdvanceId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Customer advance liability account is not configured'];
            }
        }

        $jeModel = new JournalEntryModel();
        $jlModel = new JournalLineModel();

        $this->db->transBegin();
        try {
            $totalAmount = $isAdvanceOnly ? $advanceOnlyAmount : $allocatedTotal;
            $totalAmount = $this->roundMoney($totalAmount);

            $jeId = $jeModel->insert([
                'entry_date' => $entryDate,
                'memo' => $memo,
                'currency_code' => $currency,
                'total_debits' => $totalAmount,
                'total_credits' => $totalAmount,
                'source_type' => 'payment',
                'source_id' => $paymentId,
            ], true);

            if (!$jeId) {
                throw new \RuntimeException('Failed to create journal entry: ' . json_encode($jeModel->errors()));
            }

            if ($isAdvanceOnly) {
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $sourceAccountId,
                    'description' => $memo,
                    'debit' => $totalAmount,
                    'credit' => 0,
                    'currency_code' => $currency,
                    'fx_rate' => 1.0,
                    'base_amount' => $totalAmount,
                ]);

                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $customerAdvanceId,
                    'description' => 'Customer Advance - ' . $memo,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'currency_code' => $currency,
                    'fx_rate' => 1.0,
                    'base_amount' => $totalAmount,
                ]);
            } else {
                if ($cashTotal > 0) {
                    $cashTotal = $this->roundMoney($cashTotal);
                    $this->insertLine($jlModel, [
                        'entry_id' => $jeId,
                        'account_id' => $sourceAccountId,
                        'description' => $memo,
                        'debit' => $cashTotal,
                        'credit' => 0,
                        'currency_code' => $currency,
                        'fx_rate' => 1.0,
                        'base_amount' => $cashTotal,
                    ]);
                }

                if ($advanceApplied > 0) {
                    $advanceApplied = $this->roundMoney($advanceApplied);
                    $this->insertLine($jlModel, [
                        'entry_id' => $jeId,
                        'account_id' => $customerAdvanceId,
                        'description' => 'Advance Applied - ' . $memo,
                        'debit' => $advanceApplied,
                        'credit' => 0,
                        'currency_code' => $currency,
                        'fx_rate' => 1.0,
                        'base_amount' => $advanceApplied,
                    ]);
                }

                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $arId,
                    'description' => $memo,
                    'debit' => 0,
                    'credit' => $totalAmount,
                    'currency_code' => $currency,
                    'fx_rate' => 1.0,
                    'base_amount' => $totalAmount,
                ]);
            }

            // Mark payment as posted.
            $paymentCols = $this->db->getFieldNames('customer_payments');
            $payUpdate = [];
            if (in_array('posted_entry_id', $paymentCols, true)) {
                $payUpdate['posted_entry_id'] = $jeId;
            }
            if (in_array('status', $paymentCols, true)) {
                $payUpdate['status'] = 'posted';
            }
            if (in_array('updated_at', $paymentCols, true)) {
                $payUpdate['updated_at'] = date('Y-m-d H:i:s');
            }
            if (!empty($payUpdate)) {
                $paymentModel->update($paymentId, $payUpdate);
            }

            // Update invoice balances/status for settlement postings.
            if (!$isAdvanceOnly && !empty($allocRows) && $this->db->tableExists('customer_invoices')) {
                $invCols = $this->db->getFieldNames('customer_invoices');
                $hasBalance = in_array('balance', $invCols, true);
                $hasStatus = in_array('status', $invCols, true);
                $totalField = null;
                foreach (['total_amount', 'total', 'amount'] as $f) {
                    if (in_array($f, $invCols, true)) {
                        $totalField = $f;
                        break;
                    }
                }

                if ($totalField !== null && ($hasBalance || $hasStatus)) {
                    // Determine correct allocation amount column
                    $allocAmountExpr = 'cpa.allocated_amount';
                    try {
                        $allocCols = $this->db->getFieldNames('customer_payment_allocations');
                        if (!in_array('allocated_amount', $allocCols, true)) {
                            if (in_array('amount_allocated', $allocCols, true)) {
                                $allocAmountExpr = 'cpa.amount_allocated';
                            } elseif (in_array('amount', $allocCols, true)) {
                                $allocAmountExpr = 'cpa.amount';
                            }
                        }
                    } catch (\Throwable $_) {
                        // Default to allocated_amount
                    }

                    // Determine correct payment status indicator
                    $payCols = $this->db->getFieldNames('customer_payments');
                    $statusCheck = '';
                    if (in_array('status', $payCols, true)) {
                        $statusCheck = "LOWER(COALESCE(cp.status, '')) = 'posted'";
                    } elseif (in_array('posted_entry_id', $payCols, true)) {
                        $statusCheck = '(cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)';
                    }

                    foreach ($allocRows as $row) {
                        $invoiceId = (int)($row['invoice_id'] ?? 0);
                        if ($invoiceId <= 0) {
                            continue;
                        }

                        $inv = $this->db->query(
                            'SELECT id, ' . $totalField . ' AS total_amount FROM customer_invoices WHERE id = ? LIMIT 1',
                            [$invoiceId]
                        )->getRowArray();
                        if (!$inv) {
                            continue;
                        }

                        $total = max(0.0, (float)($inv['total_amount'] ?? 0));
                        
                        // Build payment query with correct status check
                        $query = 'SELECT COALESCE(SUM(' . $allocAmountExpr . '),0) AS paid '
                            . 'FROM customer_payment_allocations cpa '
                            . 'INNER JOIN customer_payments cp ON cp.id = cpa.payment_id '
                            . 'WHERE cpa.invoice_id = ? AND (' . $statusCheck . ' OR cp.id = ?)';
                        
                        $paid = (float)($this->db->query(
                            $query,
                            [$invoiceId, $paymentId]
                        )->getRowArray()['paid'] ?? 0);

                        $balance = max(0.0, $this->roundMoney($total - $paid));
                        $invUpdate = [];
                        if ($hasBalance) {
                            $invUpdate['balance'] = $balance;
                        }
                        if ($hasStatus) {
                            if ($total > 0 && $balance <= 0.005) {
                                $invUpdate['status'] = 'paid';
                            } elseif ($paid > 0) {
                                $invUpdate['status'] = 'partially_paid';
                            }
                        }
                        if (!empty($invUpdate)) {
                            $this->db->table('customer_invoices')->where('id', $invoiceId)->update($invUpdate);
                        }
                    }
                }
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $this->db->transCommit();
            return [
                'success' => true,
                'skipped' => false,
                'journal_entry_id' => (int)$jeId,
                'customer_payment_id' => $paymentId,
                'message' => 'Customer payment posted',
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'AccountingPostingService: customer payment posting failed payment_id=' . $paymentId . ' err=' . $e->getMessage());
            return ['success' => false, 'skipped' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Post a vendor bill so the AP control account and sub-ledger reflect the liability.
     */
    public function postVendorBill(int $vendorBillId): array
    {
        if ($vendorBillId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'vendor_bill_id is required'];
        }

        if (! $this->db->tableExists('vendor_bills')) {
            return ['success' => false, 'skipped' => false, 'message' => 'vendor_bills table is not available'];
        }

        $billModel = new VendorBillModel();
        $bill = $billModel->find($vendorBillId);
        if (!$bill) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor bill not found'];
        }

        $already = (int)($bill['posted_entry_id'] ?? 0);
        if ($already > 0) {
            log_message('debug', 'AccountingPostingService: vendor bill ' . $vendorBillId . ' skipped (posted_entry_id=' . $already . ')');
            return ['success' => true, 'skipped' => true, 'posted_entry_id' => $already, 'message' => 'Vendor bill already posted'];
        }

        $vendorId = (int)($bill['vendor_id'] ?? null);
        if ($vendorId === null || !isset($bill['vendor_id'])) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor bill missing vendor_id'];
        }

        $amount = $this->resolveFirstPositiveAmount($bill, ['balance', 'amount', 'total_amount', 'grand_total']);
        if ($amount <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor bill amount is zero'];
        }

        $debitAccountId = $this->findBillDebitAccount($bill);
        if ($debitAccountId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'Unable to determine GL account for vendor bill'];
        }

        $apId = $this->findAccountIdByCodeOrName(['2000'], ['accounts payable']);
        if ($apId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'Accounts Payable account not configured'];
        }

        $currency = strtoupper(trim((string)($bill['currency_code'] ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $entryDate = $bill['bill_date'] ?? $bill['issue_date'] ?? date('Y-m-d');
        $memo = $this->memoFromRow($bill, 'Vendor Bill #' . $vendorBillId);
        $fxRate = 1.0;

        $jeModel = new JournalEntryModel();
        $jlModel = new JournalLineModel();

        $this->db->transBegin();
        try {
            $total = $this->roundMoney($amount);
            $jeId = $jeModel->insert([
                'entry_date' => $entryDate,
                'memo' => $memo,
                'currency_code' => $currency,
                'total_debits' => $total,
                'total_credits' => $total,
                'source_type' => 'vendor_bill',
                'source_id' => $vendorBillId,
                'vendor_id' => $vendorId,
            ], true);

            if (!$jeId) {
                throw new \RuntimeException('Failed to create journal entry: ' . json_encode($jeModel->errors()));
            }

            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $debitAccountId,
                'description' => $memo,
                'debit' => $total,
                'credit' => 0,
                'currency_code' => $currency,
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($amount * $fxRate),
            ]);

            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $apId,
                'description' => 'Vendor Bill #' . $vendorBillId,
                'debit' => 0,
                'credit' => $total,
                'currency_code' => $currency,
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($amount * $fxRate),
            ]);

            $billModel->update($vendorBillId, [
                'posted_entry_id' => $jeId,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // AUTO-APPLY ADVANCES: Apply vendor's available advance to reduce AP
            if ($this->db->tableExists('vendor_payments')) {
                $advanceApplicationResult = $this->applyVendorAdvancesToBill($vendorBillId, $vendorId, $amount, $currency, $fxRate);
                if (!empty($advanceApplicationResult['applied'])) {
                    log_message('info', 'AccountingPostingService: Applied vendor advances to bill #' . $vendorBillId . ': ' . $advanceApplicationResult['applied_amount']);
                }
            }

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $this->db->transCommit();
            log_message('debug', 'AccountingPostingService: vendor bill posted journal_entry_id=' . $jeId . ' vendor_bill_id=' . $vendorBillId);

            return ['success' => true, 'skipped' => false, 'posted_entry_id' => $jeId, 'message' => 'Vendor bill posted'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'AccountingPostingService: vendor bill posting failed vendor_bill_id=' . $vendorBillId . ' err=' . $e->getMessage());
            return ['success' => false, 'skipped' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Post a vendor payment/advance by linking to the cash/bank account (or cheque posting).
     */
    public function postVendorPayment(int $vendorPaymentId): array
    {
        if ($vendorPaymentId <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'vendor_payment_id is required'];
        }

        $paymentModel = new VendorPaymentModel();
        $payment = $paymentModel->find($vendorPaymentId);
        if (!$payment) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor payment not found'];
        }

        $already = (int)($payment['posted_entry_id'] ?? 0);
        if ($already > 0) {
            log_message('debug', 'AccountingPostingService: vendor payment ' . $vendorPaymentId . ' skipped (posted_entry_id=' . $already . ')');
            return ['success' => true, 'skipped' => true, 'journal_entry_id' => $already, 'vendor_payment_id' => $vendorPaymentId];
        }

        $vendorRaw = $payment['vendor_id'] ?? null;
        if ($vendorRaw === null || $vendorRaw === '') {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor payment missing vendor_id'];
        }
        $vendorId = (int)$vendorRaw;
        if ($vendorId < 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor payment missing vendor_id'];
        }
        // Validate vendor exists (supports vendor_id=0 in some installs).
        $vendor = (new VendorModel())->where('id', $vendorId)->first();
        if (!$vendor) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor not found'];
        }

        $amount = (float)($payment['amount'] ?? 0);
        if ($amount <= 0) {
            return ['success' => false, 'skipped' => false, 'message' => 'Vendor payment amount must be positive'];
        }

        $method = strtolower(trim((string)($payment['payment_method'] ?? '')));
        if ($method === '') {
            return ['success' => false, 'skipped' => false, 'message' => 'payment_method is required'];
        }

        if (!in_array($method, self::VENDOR_PAYMENT_METHODS, true)) {
            return ['success' => false, 'skipped' => false, 'message' => 'Invalid payment method'];
        }

        $sourceAccountId = (int)($payment['source_account_id'] ?? 0);
        if ($method === 'cash' && $sourceAccountId <= 0) {
            $sourceAccountId = $this->findAccountIdByCodeOrName(['1000'], ['cash']);
        }

        $currency = strtoupper(trim((string)($payment['currency_code'] ?? 'PKR')));
        if ($currency === '') {
            $currency = 'PKR';
        }

        $fxRate = (float)($payment['fx_rate'] ?? 1);
        if ($fxRate <= 0) {
            $fxRate = 1.0;
        }

        // Determine payment type (advance vs bill_payment)
        $paymentType = strtolower(trim((string)($payment['payment_type'] ?? 'bill_payment')));

        // For bill_payment: check allocations
        $allocModel = new VendorPaymentAllocationModel();
        $allocations = $allocModel->where('payment_id', $vendorPaymentId)->findAll();
        $allocatedTotal = $this->sumAllocationAmounts($allocations);
        
        if ($paymentType === 'bill_payment' && $allocatedTotal > $amount) {
            return ['success' => false, 'skipped' => false, 'message' => 'Allocated amount exceeds payment total'];
        }

        $advancePortion = (float)($payment['advance_amount'] ?? 0);
        $advancePortion = max(0.0, min($advancePortion, $amount));
        $cashPortion = $this->roundMoney($amount - $advancePortion);

        // Determine if this is giving NEW advance (no allocations) vs using advance to pay bills
        $hasAllocations = count($allocations) > 0;
        $givingNewAdvance = ($paymentType === 'advance' && !$hasAllocations);

        // Source account required when giving new advance OR when paying with cash
        if ($givingNewAdvance || $cashPortion > 0) {
            if ($method !== 'cheque' && $sourceAccountId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'source_account_id is required for cash, bank, online transfer, or advance payments'];
            }
        }

        if ($method === 'cheque') {
            $chequeId = (int)($payment['cheque_id'] ?? 0);
            if ($chequeId > 0) {
                // Cheque was created via the cheques module — link to its journal entry
                $chequeModel = new ChequeModel();
                $cheque = $chequeModel->find($chequeId);
                if (!$cheque) {
                    return ['success' => false, 'skipped' => false, 'message' => 'Cheque not found'];
                }

                $jeId = (int)($cheque['posted_entry_id'] ?? 0);
                if ($jeId <= 0) {
                    return ['success' => false, 'skipped' => false, 'message' => 'Cheque has not been posted'];
                }

                $paymentModel->update($vendorPaymentId, [
                    'posted_entry_id' => $jeId,
                    'status' => 'posted',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

                log_message('debug', 'AccountingPostingService: vendor payment linked to cheque journal_entry_id=' . $jeId . ' payment_id=' . $vendorPaymentId);

                return [
                    'success' => true,
                    'skipped' => false,
                    'vendor_payment_id' => $vendorPaymentId,
                    'journal_entry_id' => $jeId,
                    'message' => 'Vendor payment linked to cheque posting',
                ];
            }

            // No linked cheque record — payment created directly via vendor payments form.
            // Post like a bank payment using the source_account_id.
            if ($sourceAccountId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Source bank account is required for cheque payments'];
            }
        }

        // CRITICAL: Determine payment type (advance vs bill_payment)
        $paymentType = strtolower(trim((string)($payment['payment_type'] ?? 'bill_payment')));
        
        // IMPORTANT: If payment has allocations (paying bills), treat as bill settlement
        // regardless of payment_type. Only pure advances (no allocations) use Vendor Advances as debit.
        // $hasAllocations already declared above during validation
        
        // Get appropriate debit account based on whether this is a bill payment or pure advance
        if ($paymentType === 'advance' && !$hasAllocations) {
            // Pure advance payment (no bills): Debit Vendor Advances, Credit Bank/Cash
            // Account code was migrated from 1400 -> 1401; accept either
            $debitAccountId = $this->findAccountIdByCodeOrName(
                ['1401', '1400', '1450'], 
                ['vendor advances', 'vendor advance', 'advances to vendors', 'vendor prepayment']
            );
            if ($debitAccountId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Vendor Advances account not configured. Please create account with code 1401 or name "Vendor Advances"'];
            }
            $debitAccountLabel = 'Vendor Advances';
        } else {
            // Bill payment (with or without advance): Debit Accounts Payable, Credit Bank/Cash + Vendor Advances
            $apId = $this->findAccountIdByCodeOrName(['2000'], ['accounts payable']);
            if ($apId <= 0) {
                return ['success' => false, 'skipped' => false, 'message' => 'Accounts Payable account not configured'];
            }
            $debitAccountId = $apId;
            $debitAccountLabel = 'Accounts Payable';
        }

        $entryDate = $payment['payment_date'] ?? date('Y-m-d');
        $isPureAdvance = ($paymentType === 'advance' && !$hasAllocations);
        $memo = $this->memoFromRow($payment, ($isPureAdvance ? 'Vendor Advance Payment #' : 'Vendor Payment #') . $vendorPaymentId);
        $jeModel = new JournalEntryModel();
        $jlModel = new JournalLineModel();

        $this->db->transBegin();
        try {
            $total = $this->roundMoney($amount);
            $jeData = [
                'entry_date' => $entryDate,
                'memo' => $memo,
                'currency_code' => $currency,
                'total_debits' => $total,
                'total_credits' => $total,
                'source_type' => 'vendor_payment',
                'source_id' => $vendorPaymentId,
            ];
            // include vendor_id on journal entry if available
            if (isset($payment['vendor_id']) && (int)$payment['vendor_id'] > 0) {
                $jeData['vendor_id'] = (int)$payment['vendor_id'];
            }

            $jeId = $jeModel->insert($jeData, true);

            if (!$jeId) {
                throw new \RuntimeException('Failed to create journal entry: ' . json_encode($jeModel->errors()));
            }

            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $debitAccountId,
                'description' => $memo,
                'debit' => $total,
                'credit' => 0,
                'currency_code' => $currency,
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($amount * $fxRate),
            ]);

            if ($isPureAdvance) {
                // Giving NEW advance to vendor (no bills) - credit source account
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $sourceAccountId,
                    'description' => 'Advance to vendor #' . $vendorId,
                    'debit' => 0,
                    'credit' => $total,
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($amount * $fxRate),
                ]);
            } else {
                // Paying bills - credit advance account and/or cash account
                $advancePortion = (float)($payment['advance_amount'] ?? 0);
                $advancePortion = max(0.0, min($advancePortion, $total));
                $cashPortion = $this->roundMoney($total - $advancePortion);

                if ($advancePortion > 0) {
                    $advanceAccountId = $this->findAccountIdByCodeOrName(
                        ['1401', '1400', '1450'],
                        ['vendor advances', 'vendor advance', 'advances to vendors', 'vendor prepayment']
                    );
                    if ($advanceAccountId <= 0) {
                        throw new \RuntimeException('Vendor Advances account not configured. Please create account with code 1401 or name "Vendor Advances"');
                    }
                    $this->insertLine($jlModel, [
                        'entry_id' => $jeId,
                        'account_id' => $advanceAccountId,
                        'description' => 'Apply vendor advance #' . $vendorId,
                        'debit' => 0,
                        'credit' => $advancePortion,
                        'currency_code' => $currency,
                        'fx_rate' => $fxRate,
                        'base_amount' => $this->roundMoney($advancePortion * $fxRate),
                    ]);
                }

                if ($cashPortion > 0) {
                    $this->insertLine($jlModel, [
                        'entry_id' => $jeId,
                        'account_id' => $sourceAccountId,
                        'description' => 'Payment to vendor #' . $vendorId,
                        'debit' => 0,
                        'credit' => $cashPortion,
                        'currency_code' => $currency,
                        'fx_rate' => $fxRate,
                        'base_amount' => $this->roundMoney($cashPortion * $fxRate),
                    ]);
                }
            }

            $paymentModel->update($vendorPaymentId, [
                'posted_entry_id' => $jeId,
                'status' => 'posted',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }

            $this->db->transCommit();
            log_message('debug', 'AccountingPostingService: vendor payment posted journal_entry_id=' . $jeId . ' payment_id=' . $vendorPaymentId);

            return [
                'success' => true,
                'skipped' => false,
                'vendor_payment_id' => $vendorPaymentId,
                'journal_entry_id' => $jeId,
                'message' => 'Vendor payment posted',
            ];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'AccountingPostingService: vendor payment posting failed payment_id=' . $vendorPaymentId . ' err=' . $e->getMessage());
            return ['success' => false, 'skipped' => false, 'message' => $e->getMessage()];
        }
    }

    private function insertLine(JournalLineModel $jlModel, array $row): void
    {
        $ok = $jlModel->insert($row);
        if (!$ok) {
            throw new \RuntimeException('Failed to insert journal line: ' . json_encode($jlModel->errors()));
        }
    }

    private function roundMoney(float $v): float
    {
        return (float)number_format($v, 2, '.', '');
    }

    /**
     * Best-effort account resolution.
     *
     * Phase-1 constraints: no schema changes, keep backwards compatible.
     * We resolve by code first, then by name LIKE. Returns 0 if not found.
     */
    private function findAccountIdByCodeOrName(array $codes, array $namesLike): int
    {
        $am = new AccountModel();

        foreach ($codes as $code) {
            $code = trim((string)$code);
            if ($code === '') continue;
            $row = $am->where('code', $code)->first();
            if ($row && isset($row['id'])) return (int)$row['id'];
        }

        foreach ($namesLike as $needle) {
            $needle = trim((string)$needle);
            if ($needle === '') continue;
            $row = $am->like('name', $needle)->first();
            if ($row && isset($row['id'])) return (int)$row['id'];
        }

        return 0;
    }

    private function memoFromRow(array $row, string $fallback): string
    {
        foreach (['memo', 'description', 'notes', 'reference_no'] as $field) {
            $value = trim((string)($row[$field] ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        return $fallback;
    }

    private function resolveFirstPositiveAmount(array $row, array $fields): float
    {
        foreach ($fields as $field) {
            $value = (float)($row[$field] ?? 0);
            if ($value > 0) {
                return $value;
            }
        }

        return 0.0;
    }

    private function findBillDebitAccount(array $bill): int
    {
        foreach (['account_id', 'expense_account_id', 'purchase_account_id'] as $field) {
            if (isset($bill[$field])) {
                $id = (int)$bill[$field];
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return $this->findAccountIdByCodeOrName(['5000', '5100', '6000'], ['purchases', 'cost of goods sold', 'inventory']);
    }

    private function sumAllocationAmounts(array $allocations): float
    {
        $total = 0.0;
        foreach ($allocations as $alloc) {
            $total += (float)($alloc['amount_allocated'] ?? $alloc['amount'] ?? 0);
        }

        return $total;
    }

    /**
     * Apply PO advance payments to vendor bill.
     * 
     * When vendor bill is posted:
     * - Find all posted advance payments for this PO
     * - Create journal entry: Dr AP, Cr Vendor Advance
     * - Reduces AP liability by advance amount
     * 
     * @param int $vendorBillId
     * @param int $poId
     * @param int $vendorId
     * @param string $currency
     * @param float $fxRate
     * @return array {applied: array, message: string}
     */
    private function applyAdvancesToBill(int $vendorBillId, int $poId, int $vendorId, string $currency, float $fxRate): array
    {
        try {
            if (! $this->db->fieldExists('po_id', 'vendor_payments')) {
                return ['applied' => [], 'message' => 'Advance application skipped: vendor_payments.po_id not found'];
            }
            // Find all posted advances for this PO
            $advances = $this->db->table('vendor_payments')
                ->where('po_id', $poId)
                ->where('payment_type', 'advance')
                ->where('status', 'posted')
                ->get()
                ->getResultArray();

            if (empty($advances)) {
                return ['applied' => [], 'message' => 'No advances to apply'];
            }

            $apId = $this->findAccountIdByCodeOrName(['2000'], ['accounts payable']);
            if ($apId <= 0) {
                log_message('warning', 'AccountingPostingService: Cannot apply advances - AP account not found');
                return ['applied' => [], 'message' => 'AP account not configured'];
            }

            $vendorAdvanceId = $this->findAccountIdByCodeOrName(
                ['1400', '1450'], 
                ['vendor advance', 'advances to vendors', 'vendor prepayment']
            );
            if ($vendorAdvanceId <= 0) {
                log_message('warning', 'AccountingPostingService: Cannot apply advances - Vendor Advance account not found');
                return ['applied' => [], 'message' => 'Vendor Advance account not configured'];
            }

            $jeModel = new JournalEntryModel();
            $jlModel = new JournalLineModel();
            $applied = [];

            foreach ($advances as $advance) {
                $advanceAmount = (float)($advance['amount'] ?? 0);
                if ($advanceAmount <= 0) {
                    continue;
                }

                $advanceId = (int)$advance['id'];
                $total = $this->roundMoney($advanceAmount);

                // Create journal entry to apply advance
                $jeId = $jeModel->insert([
                    'entry_date' => date('Y-m-d'),
                    'memo' => sprintf(
                        'Apply advance payment #%d to vendor bill #%d (PO #%d)',
                        $advanceId,
                        $vendorBillId,
                        $poId
                    ),
                    'currency_code' => $currency,
                    'total_debits' => $total,
                    'total_credits' => $total,
                    'source_type' => 'vendor_bill_advance_application',
                    'source_id' => $vendorBillId,
                ], true);

                if (!$jeId) {
                    log_message('error', 'AccountingPostingService: Failed to create advance application JE for payment #' . $advanceId);
                    continue;
                }

                // Dr Accounts Payable (reduce liability)
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $apId,
                    'description' => 'Apply advance payment #' . $advanceId,
                    'debit' => $total,
                    'credit' => 0,
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($advanceAmount * $fxRate),
                ]);

                // Cr Vendor Advance (clear asset)
                $this->insertLine($jlModel, [
                    'entry_id' => $jeId,
                    'account_id' => $vendorAdvanceId,
                    'description' => 'Apply advance to bill #' . $vendorBillId,
                    'debit' => 0,
                    'credit' => $total,
                    'currency_code' => $currency,
                    'fx_rate' => $fxRate,
                    'base_amount' => $this->roundMoney($advanceAmount * $fxRate),
                ]);

                $applied[] = [
                    'payment_id' => $advanceId,
                    'amount' => $advanceAmount,
                    'journal_entry_id' => $jeId,
                ];

                log_message('debug', sprintf(
                    'AccountingPostingService: Applied advance payment #%d (%.2f) to bill #%d via JE #%d',
                    $advanceId,
                    $advanceAmount,
                    $vendorBillId,
                    $jeId
                ));
            }

            return [
                'applied' => $applied,
                'message' => count($applied) . ' advance(s) applied successfully'
            ];

        } catch (\Throwable $e) {
            log_message('error', 'AccountingPostingService: Error applying advances to bill #' . $vendorBillId . ': ' . $e->getMessage());
            return ['applied' => [], 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Apply vendor's available advance balance to reduce Accounts Payable.
     * 
     * Called when posting a vendor bill to automatically reduce AP by available advances.
     * Creates journal entry: Dr AP, Cr Vendor Advances
     * 
     * @param int $vendorBillId
     * @param int $vendorId
     * @param float $billAmount
     * @param string $currency
     * @param float $fxRate
     * @return array ['applied' => bool, 'applied_amount' => float]
     */
    private function applyVendorAdvancesToBill(int $vendorBillId, int $vendorId, float $billAmount, string $currency, float $fxRate): array
    {
        try {
            // Get vendor's available advance balance
            $paymentModel = new VendorPaymentModel();
            $advanceBalance = $paymentModel->getVendorAdvanceBalance($vendorId);
            
            // Determine apply amount: minimum of advance balance and bill amount
            $applyAmount = min($advanceBalance, $billAmount);
            
            if ($applyAmount <= 0) {
                return ['applied' => false, 'applied_amount' => 0];
            }
            
            // Get account IDs
            $apId = $this->findAccountIdByCodeOrName(['2000'], ['accounts payable']);
            $vendorAdvanceId = $this->findAccountIdByCodeOrName(['1401'], ['vendor advances']);
            
            if ($apId <= 0 || $vendorAdvanceId <= 0) {
                log_message('warning', 'AccountingPostingService: Cannot apply advances - required GL accounts not found');
                return ['applied' => false, 'applied_amount' => 0];
            }
            
            // Create journal entry for advance application
            $jeModel = new JournalEntryModel();
            $jlModel = new JournalLineModel();
            
            $total = $this->roundMoney($applyAmount);
            $memo = 'Apply vendor advance to bill #' . $vendorBillId;
            
            $jeId = $jeModel->insert([
                'entry_date' => date('Y-m-d'),
                'memo' => $memo,
                'currency_code' => $currency,
                'total_debits' => $total,
                'total_credits' => $total,
                'source_type' => 'vendor_advance_application',
                'source_id' => $vendorBillId,
                'vendor_id' => $vendorId,
            ], true);
            
            if (!$jeId) {
                return ['applied' => false, 'applied_amount' => 0];
            }
            
            // Dr Accounts Payable (reduce liability)
            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $apId,
                'description' => $memo,
                'debit' => $total,
                'credit' => 0,
                'currency_code' => $currency,
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($applyAmount * $fxRate),
            ]);
            
            // Cr Vendor Advances (reduce asset)
            $this->insertLine($jlModel, [
                'entry_id' => $jeId,
                'account_id' => $vendorAdvanceId,
                'description' => $memo,
                'debit' => 0,
                'credit' => $total,
                'currency_code' => $currency,
                'fx_rate' => $fxRate,
                'base_amount' => $this->roundMoney($applyAmount * $fxRate),
            ]);
            
            log_message('info', 'AccountingPostingService: Applied vendor advance to bill #' . $vendorBillId . ', amount: ' . $applyAmount . ', vendor_id: ' . $vendorId);
            
            return ['applied' => true, 'applied_amount' => $applyAmount];
            
        } catch (\Throwable $e) {
            log_message('error', 'AccountingPostingService: Error auto-applying vendor advances to bill #' . $vendorBillId . ': ' . $e->getMessage());
            return ['applied' => false, 'applied_amount' => 0];
        }
    }
}
