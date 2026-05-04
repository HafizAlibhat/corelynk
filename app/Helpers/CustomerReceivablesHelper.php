<?php

namespace App\Helpers;

use CodeIgniter\Database\BaseConnection;

class CustomerReceivablesHelper
{
    /**
     * Calculate and fetch unpaid/open invoices for a customer with detailed debugging
     */
    public static function getUnpaidInvoices(int $customerId, ?BaseConnection $db = null, bool $debug = false): array
    {
        if (!$db) {
            $db = \Config\Database::connect();
        }
        
        if ($debug) {
            log_message('debug', "CustomerReceivablesHelper::getUnpaidInvoices() called for customer_id={$customerId}");
        }
        
        $unpaidInvoices = [];
        $orderReceivables = [];
        $totalReceivable = 0.0;
        
        try {
            if (!$db->tableExists('customer_invoices')) {
                if ($debug) log_message('debug', 'customer_invoices table does not exist');
                return ['unpaid' => [], 'total' => 0.0, 'count' => 0, 'order_receivables' => []];
            }
            
            $invCols = $db->getFieldNames('customer_invoices');
            
            // Determine the correct total amount field
            $totalField = 'total_amount';
            if (!in_array('total_amount', $invCols, true)) {
                if (in_array('total', $invCols, true)) {
                    $totalField = 'total';
                } elseif (in_array('amount', $invCols, true)) {
                    $totalField = 'amount';
                }
            }
            if ($debug) log_message('debug', "Total amount field determined: {$totalField}");
            
            // Check for optional columns
            $hasSalesOrderId = in_array('sales_order_id', $invCols, true);
            $hasDueDate = in_array('due_date', $invCols, true);
            $hasIssueDate = in_array('issue_date', $invCols, true);
            $hasStatus = in_array('status', $invCols, true);
            $hasDeleted = in_array('deleted_at', $invCols, true);
            
            if ($debug) {
                log_message('debug', "Column checks: salesOrderId={$hasSalesOrderId}, dueDate={$hasDueDate}, issueDate={$hasIssueDate}, status={$hasStatus}, deleted={$hasDeleted}");
            }
            
            // Build the WHERE clause
            $whereClause = 'ci.customer_id = ?';
            $params = [(int)$customerId];
            
            if ($hasDeleted) {
                $whereClause .= ' AND ci.deleted_at IS NULL';
                if ($debug) log_message('debug', 'Added soft delete filter');
            }
            
            if ($hasStatus) {
                $whereClause .= " AND LOWER(COALESCE(ci.status, '')) NOT IN ('cancelled','void')";
                if ($debug) log_message('debug', 'Added status filter (excluding cancelled, void)');
            }
            
            // Build SELECT clause
            
            // Determine the correct allocation amount column in customer_payment_allocations
            $allocExpr = 'cpa.allocated_amount';
            if ($db->tableExists('customer_payment_allocations')) {
                try {
                    $allocCols = $db->getFieldNames('customer_payment_allocations');
                    // Check for the actual column that exists
                    if (!in_array('allocated_amount', $allocCols, true)) {
                        if (in_array('amount_allocated', $allocCols, true)) {
                            $allocExpr = 'cpa.amount_allocated';
                        } elseif (in_array('amount', $allocCols, true)) {
                            $allocExpr = 'cpa.amount';
                        }
                    }
                    if ($debug) log_message('debug', "Allocation amount field: {$allocExpr}");
                } catch (\Throwable $_) {
                    // Default to allocated_amount
                    if ($debug) log_message('debug', 'Could not determine allocation field, defaulting to allocated_amount');
                }
            }
            
            $soJoin = '';
            $soNumExpr = 'NULL AS sales_order_number';
            if ($hasSalesOrderId && $db->tableExists('sales_orders')) {
                try {
                    $soCols = $db->getFieldNames('sales_orders');
                    $soJoin = 'LEFT JOIN sales_orders so ON so.id = ci.sales_order_id ';
                    $soNumExpr = in_array('order_number', $soCols, true)
                        ? 'so.order_number AS sales_order_number'
                        : "CONCAT('SO-', ci.sales_order_id) AS sales_order_number";
                } catch (\Throwable $_) {
                    // fallback
                }
            }
            
            $selectExpr = 'SELECT ci.id, '
                . (in_array('invoice_number', $invCols, true) ? 'ci.invoice_number' : "CONCAT('INV-', ci.id) AS invoice_number") . ', '
                . ($hasIssueDate ? 'ci.issue_date' : 'NULL AS issue_date') . ', '
                . ($hasDueDate ? 'ci.due_date' : 'NULL AS due_date') . ', '
                . ($hasStatus ? 'ci.status' : "'issued' AS status") . ', '
                . 'ci.' . $totalField . ' AS total_amount, '
                . ($hasSalesOrderId ? 'ci.sales_order_id' : 'NULL AS sales_order_id') . ', '
                . $soNumExpr . ', '
                . '(SELECT COALESCE(SUM(' . $allocExpr . '),0) '
                . ' FROM customer_payment_allocations cpa '
                . ' INNER JOIN customer_payments cp ON cp.id = cpa.payment_id '
                . ' WHERE cpa.invoice_id = ci.id AND (cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)) AS paid_amount '
                . 'FROM customer_invoices ci '
                . $soJoin
                . 'WHERE ' . $whereClause . ' '
                . 'ORDER BY ' . ($hasIssueDate ? 'ci.issue_date DESC, ' : '') . 'ci.id DESC';
            
            if ($debug) {
                log_message('debug', "SQL Query: {$selectExpr} | Params: [" . implode(', ', $params) . "]");
            }
            
            $invoiceRows = $db->query($selectExpr, $params)->getResultArray();
            
            if ($debug) {
                log_message('debug', "Query returned " . count($invoiceRows) . " rows");
            }
            
            foreach ($invoiceRows as $row) {
                $total = max(0.0, (float)($row['total_amount'] ?? 0));
                $paid = max(0.0, (float)($row['paid_amount'] ?? 0));
                $outstanding = max(0.0, round($total - $paid, 2));
                
                if ($debug && $outstanding > 0) {
                    log_message('debug', "Invoice {$row['id']}: total={$total}, paid={$paid}, outstanding={$outstanding}");
                }
                
                // Only include invoices with outstanding balance > 0
                if ($outstanding <= 0.005) {
                    if ($debug && ($total > 0 || $paid > 0)) {
                        log_message('debug', "Invoice {$row['id']} skipped: outstanding={$outstanding} (threshold: 0.005)");
                    }
                    continue;
                }
                
                $row['outstanding'] = $outstanding;
                $unpaidInvoices[] = $row;
                $totalReceivable += $outstanding;
                
                if (!empty($row['sales_order_id'])) {
                    $soId = (int)$row['sales_order_id'];
                    if (!isset($orderReceivables[$soId])) {
                        $orderReceivables[$soId] = [
                            'sales_order_id' => $soId,
                            'sales_order_number' => $row['sales_order_number'] ?? ('SO-' . $soId),
                            'pending_amount' => 0.0,
                            'invoice_count' => 0,
                        ];
                    }
                    $orderReceivables[$soId]['pending_amount'] += $outstanding;
                    $orderReceivables[$soId]['invoice_count']++;
                }
            }
            
            $totalReceivable = round($totalReceivable, 2);
            
            if ($debug) {
                log_message('debug', "Final result: " . count($unpaidInvoices) . " unpaid invoices, total = {$totalReceivable}");
            }
            
        } catch (\Throwable $e) {
            log_message('error', 'CustomerReceivablesHelper::getUnpaidInvoices failed: ' . $e->getMessage());
            if ($debug) {
                log_message('debug', "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            }
        }
        
        return [
            'unpaid' => $unpaidInvoices,
            'total' => $totalReceivable,
            'count' => count($unpaidInvoices),
            'order_receivables' => $orderReceivables,
        ];
    }

    /**
     * Recalculate total pending amount for a customer by directly querying the database
     */
    public static function recalculatePendingAmount(int $customerId, ?BaseConnection $db = null): array
    {
        if (!$db) {
            $db = \Config\Database::connect();
        }
        
        $result = [
            'total_pending' => 0.0,
            'posted_payments' => 0.0,
            'draft_payments' => 0.0,
            'advance_balance' => 0.0,
        ];
        
        try {
            // Get unpaid invoices
            $unpaidData = self::getUnpaidInvoices($customerId, $db, false);
            $result['total_pending'] = $unpaidData['total'];
            
            // Get payment totals
            if ($db->tableExists('customer_payments')) {
                $payCols = $db->getFieldNames('customer_payments');
                $hasStatus = in_array('status', $payCols, true);
                $hasPostedEntryId = in_array('posted_entry_id', $payCols, true);
                
                // Determine how to detect "posted" vs "draft" payments
                if ($hasStatus) {
                    // If table has status column, use it
                    $statusExpr = 'LOWER(COALESCE(cp.status, \'draft\')) AS status';
                    $postedCheck = "LOWER(COALESCE(cp.status, 'draft')) = 'posted'";
                } elseif ($hasPostedEntryId) {
                    // If table has posted_entry_id, use that to determine posted status
                    $statusExpr = 'IF(cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0, \'posted\', \'draft\') AS status';
                    $postedCheck = '(cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)';
                } else {
                    // Default: assume all payments are draft if no status indicators
                    $statusExpr = '\'draft\' AS status';
                    $postedCheck = '1=0';
                }
                
                $paymentQuery = 'SELECT '
                    . $statusExpr . ', '
                    . 'COALESCE(SUM(cp.amount), 0) AS total_amount '
                    . 'FROM customer_payments cp '
                    . 'WHERE cp.customer_id = ? '
                    . 'GROUP BY ' . ($hasStatus ? 'LOWER(COALESCE(cp.status, \'draft\'))' : 'IF(cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0, \'posted\', \'draft\')');
                
                $payments = $db->query($paymentQuery, [(int)$customerId])->getResultArray();
                
                foreach ($payments as $pay) {
                    if ($pay['status'] === 'posted') {
                        $result['posted_payments'] = (float)($pay['total_amount'] ?? 0);
                    } else {
                        $result['draft_payments'] = (float)($pay['total_amount'] ?? 0);
                    }
                }
            }
            
            // Get advance balance
            try {
                $pm = new \App\Models\CustomerPaymentModel();
                $result['advance_balance'] = round($pm->getCustomerAdvanceBalance((int)$customerId), 2);
            } catch (\Throwable $_) {
                $result['advance_balance'] = 0.0;
            }
            
        } catch (\Throwable $e) {
            log_message('error', 'CustomerReceivablesHelper::recalculatePendingAmount failed: ' . $e->getMessage());
        }
        
        return $result;
    }
}
