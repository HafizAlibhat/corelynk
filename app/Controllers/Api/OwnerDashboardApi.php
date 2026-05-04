<?php

namespace App\Controllers\Api;

use App\Models\SalesOrderModel;
use App\Models\PurchaseOrderModel;
use App\Models\CustomerInvoiceModel;
use App\Models\VendorBillModel;
use App\Models\CustomerPaymentModel;
use App\Models\VendorPaymentModel;
use App\Models\ProductModel;
use App\Models\CustomerModel;
use App\Models\VendorModel;

/**
 * GET /api/owner/summary — Real financial summary for business owner.
 *
 * Returns receivables, payables, sales totals, purchase totals,
 * currency breakdowns, stock snapshot, and overdue counts.
 */
class OwnerDashboardApi extends BaseApiController
{
    public function summary(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('dashboard', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();
        $monthStart = date('Y-m-01');
        $today = date('Y-m-d');

        // ── Total Sales (this month from sales_orders) ──
        $totalSalesMonth = $this->safeScalar($db,
            "SELECT COALESCE(SUM(total), 0) AS val FROM sales_orders WHERE order_date >= ?",
            [$monthStart]
        );

        // ── Total Purchases (this month from purchase_orders) ──
        $totalPurchasesMonth = $this->safeScalar($db,
            "SELECT COALESCE(SUM(total), 0) AS val FROM purchase_orders WHERE order_date >= ?",
            [$monthStart]
        );

        // ── Receivables: unpaid/partially-paid customer invoices ──
        $receivables = $this->safeScalar($db,
            "SELECT COALESCE(SUM(ci.total_amount), 0) - COALESCE((
                SELECT SUM(cpa.allocated_amount) FROM customer_payment_allocations cpa
                INNER JOIN customer_invoices ci2 ON cpa.invoice_id = ci2.id
                WHERE ci2.status NOT IN ('paid','cancelled') AND ci2.deleted_at IS NULL
            ), 0) AS val
            FROM customer_invoices ci
            WHERE ci.status NOT IN ('paid','cancelled') AND ci.deleted_at IS NULL"
        );

        // Simpler fallback if the above fails (table might not exist)
        if ($receivables === null) {
            $receivables = $this->safeScalar($db,
                "SELECT COALESCE(SUM(total_amount), 0) AS val FROM customer_invoices WHERE status NOT IN ('paid','cancelled') AND deleted_at IS NULL"
            );
        }

        // ── Payables: unpaid vendor bills ──
        $payables = $this->safeScalar($db,
            "SELECT COALESCE(SUM(balance), 0) AS val FROM vendor_bills WHERE status NOT IN ('paid','cancelled')"
        );
        // Fallback: use total_amount if balance column doesn't exist
        if ($payables === null) {
            $payables = $this->safeScalar($db,
                "SELECT COALESCE(SUM(total_amount), 0) AS val FROM vendor_bills WHERE status NOT IN ('paid','cancelled')"
            );
        }

        // ── Overdue invoices count ──
        $overdueReceivables = (int) $this->safeScalar($db,
            "SELECT COUNT(*) AS val FROM customer_invoices WHERE status NOT IN ('paid','cancelled') AND deleted_at IS NULL AND due_date < ?",
            [$today]
        );

        // ── Overdue bills count ──
        $overduePayables = (int) $this->safeScalar($db,
            "SELECT COUNT(*) AS val FROM vendor_bills WHERE status NOT IN ('paid','cancelled') AND bill_date < DATE_SUB(?, INTERVAL 30 DAY)",
            [$today]
        );

        // ── Sales by currency (this month) ──
        $salesByCurrency = $this->safeQuery($db,
            "SELECT COALESCE(currency, currency_code, 'PKR') AS currency, SUM(total) AS amount
             FROM sales_orders WHERE order_date >= ? GROUP BY COALESCE(currency, currency_code, 'PKR')",
            [$monthStart]
        );

        // ── Purchases by currency (this month) ──
        $purchasesByCurrency = $this->safeQuery($db,
            "SELECT COALESCE(currency, currency_code, 'PKR') AS currency, SUM(total) AS amount
             FROM purchase_orders WHERE order_date >= ? GROUP BY COALESCE(currency, currency_code, 'PKR')",
            [$monthStart]
        );

        // ── Stock snapshot ──
        $productModel = new ProductModel();
        $totalProducts = $productModel->countAll();
        $lowStockCount = (int) $this->safeScalar($db,
            "SELECT COUNT(*) AS val FROM products WHERE current_stock <= 5 AND current_stock >= 0 AND is_active = 1"
        );

        // ── Pending orders count ──
        $pendingSales = (int) $this->safeScalar($db,
            "SELECT COUNT(*) AS val FROM sales_orders WHERE status IN ('pending','confirmed','processing','draft')"
        );
        $pendingPurchases = (int) $this->safeScalar($db,
            "SELECT COUNT(*) AS val FROM purchase_orders WHERE status IN ('pending','confirmed','draft','sent')"
        );

        // ── Counts ──
        $customerModel = new CustomerModel();
        $vendorModel = new VendorModel();
        $totalCustomers = $customerModel->countAll();
        $totalVendors = $vendorModel->countAll();

        // ── All-time totals ──
        $totalSalesAllTime = $this->safeScalar($db,
            "SELECT COALESCE(SUM(total), 0) AS val FROM sales_orders"
        );
        $totalPurchasesAllTime = $this->safeScalar($db,
            "SELECT COALESCE(SUM(total), 0) AS val FROM purchase_orders"
        );

        return $this->success([
            // Financials
            'total_sales'              => round((float) $totalSalesMonth, 2),
            'total_purchases'          => round((float) $totalPurchasesMonth, 2),
            'total_sales_all_time'     => round((float) $totalSalesAllTime, 2),
            'total_purchases_all_time' => round((float) $totalPurchasesAllTime, 2),
            'receivables'              => round((float) ($receivables ?? 0), 2),
            'payables'                 => round((float) ($payables ?? 0), 2),
            'overdue_receivables'      => $overdueReceivables,
            'overdue_payables'         => $overduePayables,

            // Currency breakdown
            'sales_by_currency'        => $salesByCurrency ?? [],
            'purchases_by_currency'    => $purchasesByCurrency ?? [],

            // Stock
            'total_products'           => $totalProducts,
            'low_stock_count'          => $lowStockCount,

            // Pipeline
            'pending_sales_orders'     => $pendingSales,
            'pending_purchase_orders'  => $pendingPurchases,

            // Counts
            'total_customers'          => $totalCustomers,
            'total_vendors'            => $totalVendors,

            // Meta
            'period'                   => 'this_month',
            'month_start'              => $monthStart,
            'generated_at'             => date('Y-m-d H:i:s'),
        ]);
    }

    // ── Helpers ──

    private function safeScalar(\CodeIgniter\Database\BaseConnection $db, string $sql, array $params = []): ?float
    {
        try {
            $row = $db->query($sql, $params)->getRowArray();
            return isset($row['val']) ? (float) $row['val'] : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function safeQuery(\CodeIgniter\Database\BaseConnection $db, string $sql, array $params = []): array
    {
        try {
            $rows = $db->query($sql, $params)->getResultArray();
            // Cast amount to float so JSON encodes as a number not a string
            return array_map(static function (array $row): array {
                if (isset($row['amount'])) {
                    $row['amount'] = (float) $row['amount'];
                }
                return $row;
            }, $rows);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
