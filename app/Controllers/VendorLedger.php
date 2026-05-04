<?php

namespace App\Controllers;

use App\Models\VendorBillModel;
use App\Models\VendorPaymentModel;
use Config\Database;

class VendorLedger extends BaseController
{
    /**
     * Display vendor ledger with all bills, payments, and running balance
     * 
     * Route: /vendors/ledger/{vendor_id}
     */
    public function index($vendorId = null)
    {
        $vendorId = (int)$vendorId;
        if ($vendorId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid vendor id']);
        }

        $db = Database::connect();

        // Get vendor details
        $vendor = null;
        try {
            $vendorRow = $db->table('vendors')
                ->select('id, name, email, phone, address')
                ->where('id', $vendorId)
                ->get()
                ->getRowArray();
            if ($vendorRow) {
                $vendor = $vendorRow;
            }
        } catch (\Throwable $_) {
        }

        if (!$vendor) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Vendor not found']);
        }

        $transactions = [];
        $runningBalance = 0.0;

        try {
            // Get all vendor bills
            $billModel = new VendorBillModel();
            $bills = $billModel->where('vendor_id', $vendorId)
                ->orderBy('bill_date', 'ASC')
                ->orderBy('created_at', 'ASC')
                ->findAll();

            foreach ($bills as $bill) {
                $amount = (float)($bill['total_amount'] ?? 0);
                $runningBalance += $amount;

                $transactions[] = [
                    'date' => $bill['bill_date'] ?? $bill['created_at'] ?? '',
                    'doc_type' => 'Bill',
                    'doc_id' => 'VB' . ($bill['id'] ?? ''),
                    'doc_number' => $bill['vendor_bill_number'] ?? '',
                    'po_id' => $bill['po_id'] ?? null,
                    'debit' => $amount,
                    'credit' => 0.0,
                    'running_balance' => $runningBalance,
                    'status' => $bill['status'] ?? 'draft',
                    'currency' => strtoupper(trim($bill['currency_code'] ?? 'PKR')),
                ];
            }

            // Get all vendor payments
            $paymentModel = new VendorPaymentModel();
            $payments = $paymentModel->where('vendor_id', $vendorId)
                ->orderBy('payment_date', 'ASC')
                ->orderBy('created_at', 'ASC')
                ->findAll();

            foreach ($payments as $payment) {
                $amount = (float)($payment['amount'] ?? 0);
                $runningBalance -= $amount;

                $transactions[] = [
                    'date' => $payment['payment_date'] ?? $payment['created_at'] ?? '',
                    'doc_type' => 'Payment',
                    'doc_id' => 'VP' . ($payment['id'] ?? ''),
                    'doc_number' => $payment['cheque_number'] ?? $payment['transaction_ref'] ?? '',
                    'po_id' => $payment['po_id'] ?? null,
                    'debit' => 0.0,
                    'credit' => $amount,
                    'running_balance' => $runningBalance,
                    'status' => $payment['status'] ?? 'draft',
                    'currency' => strtoupper(trim($payment['currency_code'] ?? 'PKR')),
                ];
            }

            // Get journal entries linked to this vendor (via bills/payments)
            if ($db->tableExists('journal_entries') && $db->tableExists('journal_lines')) {
                try {
                    $journalRows = $db->query(
                        "SELECT je.id, je.entry_date, je.memo, je.source_type, je.source_id,
                                SUM(CASE WHEN jl.debit > 0 THEN jl.debit ELSE 0 END) as total_debit,
                                SUM(CASE WHEN jl.credit > 0 THEN jl.credit ELSE 0 END) as total_credit,
                                je.currency_code
                         FROM journal_entries je
                         LEFT JOIN journal_lines jl ON jl.entry_id = je.id
                         WHERE (
                            je.source_type = 'vendor_bill' AND je.source_id IN 
                            (SELECT id FROM vendor_bills WHERE vendor_id = ?)
                         ) OR (
                            je.source_type = 'vendor_payment' AND je.source_id IN 
                            (SELECT id FROM vendor_payments WHERE vendor_id = ?)
                         )
                         GROUP BY je.id
                         ORDER BY je.entry_date ASC",
                        [$vendorId, $vendorId]
                    )->getResultArray();

                    foreach ($journalRows as $je) {
                        if ($je['source_type'] === 'vendor_bill') {
                            $debit = (float)($je['total_debit'] ?? 0);
                        } elseif ($je['source_type'] === 'vendor_payment') {
                            $credit = (float)($je['total_credit'] ?? 0);
                        } else {
                            continue;
                        }
                    }
                } catch (\Throwable $_) {
                    // Journal entries not available
                }
            }

        } catch (\Throwable $e) {
            log_message('error', 'VendorLedger error: ' . $e->getMessage());
        }

        // Sort transactions by date
        usort($transactions, function ($a, $b) {
            return strtotime($a['date'] ?? '0') - strtotime($b['date'] ?? '0');
        });

        // Recalculate running balance after sort
        $runningBalance = 0.0;
        foreach ($transactions as &$txn) {
            if ($txn['debit'] > 0) {
                $runningBalance += $txn['debit'];
            } else {
                $runningBalance -= $txn['credit'];
            }
            $txn['running_balance'] = $runningBalance;
        }

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'vendor' => $vendor,
                'transactions' => $transactions,
                'final_balance' => $runningBalance,
                'last_updated' => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}
