<?php

namespace App\Controllers\Api;

/**
 * GET /api/receivables       — Outstanding customer invoices (who owes you)
 * GET /api/payables          — Outstanding vendor bills (what you owe)
 */
class FinanceApi extends BaseApiController
{
    /**
     * GET /api/receivables
     *
     * Returns outstanding customer invoices grouped by customer,
     * with total owed and overdue flag.
     */
    public function receivables(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('accounting', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();
        $today = date('Y-m-d');

        // Summary by customer
        $summary = $db->query(
            "SELECT c.id AS customer_id,
                    COALESCE(c.name, c.company_name, 'Unknown') AS customer_name,
                    COUNT(ci.id) AS invoice_count,
                    SUM(ci.total_amount) AS total_amount,
                    COALESCE(ci.currency_code, 'PKR') AS currency,
                    SUM(CASE WHEN ci.due_date < ? THEN 1 ELSE 0 END) AS overdue_count,
                    MIN(ci.due_date) AS earliest_due
             FROM customer_invoices ci
             LEFT JOIN customers c ON ci.customer_id = c.id
             WHERE ci.status NOT IN ('paid','cancelled') AND ci.deleted_at IS NULL
             GROUP BY c.id, c.name, c.company_name, ci.currency_code
             ORDER BY total_amount DESC",
            [$today]
        )->getResultArray();

        $totalReceivable = 0;
        $totalOverdue = 0;
        foreach ($summary as &$row) {
            $row['total_amount'] = round((float) $row['total_amount'], 2);
            $row['is_overdue'] = (int) $row['overdue_count'] > 0;
            $totalReceivable += $row['total_amount'];
            $totalOverdue += (int) $row['overdue_count'];
        }

        return $this->success([
            'total_receivable' => round($totalReceivable, 2),
            'total_overdue_invoices' => $totalOverdue,
            'customers' => $summary,
        ]);
    }

    /**
     * GET /api/payables
     *
     * Returns outstanding vendor bills grouped by vendor.
     */
    public function payables(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('accounting', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $summary = $db->query(
            "SELECT v.id AS vendor_id,
                    COALESCE(v.name, 'Unknown') AS vendor_name,
                    COUNT(vb.id) AS bill_count,
                    SUM(COALESCE(vb.balance, vb.total_amount)) AS total_amount,
                    COALESCE(vb.currency_code, 'PKR') AS currency
             FROM vendor_bills vb
             LEFT JOIN vendors v ON vb.vendor_id = v.id
             WHERE vb.status NOT IN ('paid','cancelled')
             GROUP BY v.id, v.name, vb.currency_code
             ORDER BY total_amount DESC"
        )->getResultArray();

        $totalPayable = 0;
        foreach ($summary as &$row) {
            $row['total_amount'] = round((float) $row['total_amount'], 2);
            $totalPayable += $row['total_amount'];
        }

        return $this->success([
            'total_payable' => round($totalPayable, 2),
            'vendors' => $summary,
        ]);
    }
}
