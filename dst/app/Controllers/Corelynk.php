<?php

namespace App\Controllers;

use App\Libraries\RoleDataAccess;
use App\Services\NotificationService;

class Corelynk extends BaseController
{
    protected NotificationService $notificationService;

    public function __construct()
    {
        $this->notificationService = new NotificationService();
    }

    public function index()
    {
        $this->requireAuth();

        // If user doesn't have dashboard access, redirect to their first accessible module
        $policy = service('policy');
        if (!$policy->isAdmin() && !$policy->can('dashboard', 'read')) {
            $fallbacks = [
                'inventory'      => '/inventory/stock',
                'products'       => '/products',
                'purchase_orders'=> '/new-purchase-orders',
                'delivery_orders'=> '/delivery-orders',
                'vendors'        => '/vendors',
                'grn'            => '/new-purchase-grns/list',
                'work_orders'    => '/work-orders',
                'sales_orders'   => '/sales-orders',
                'accounting'     => '/accounting',
                'reports'        => '/reports',
            ];
            foreach ($fallbacks as $module => $url) {
                if ($policy->can($module, 'read')) {
                    return redirect()->to(base_url($url));
                }
            }
            // No accessible module at all
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/html/error_403', ['message' => 'You have not been assigned any module permissions. Please contact your administrator.']));
        }

        $db = \Config\Database::connect();
        $data = [];
        $userId = (int) (session()->get('user_id') ?? 0);
        try {
            $dataAccess = (new RoleDataAccess())->resolveForUser($userId);
        } catch (\Throwable $_) {
            $dataAccess = [
                'dashboard_sales_visible' => true,
                'dashboard_purchases_visible' => true,
                'dashboard_finance_visible' => true,
            ];
        }
        $data['data_access'] = $dataAccess;
        $baseCurrency = 'PKR';
        $defaultSalesCurrency = 'USD';
        $defaultPurchaseCurrency = 'PKR';
        try {
            $company = (new \App\Models\CompanySettingsModel())->first();
            if (!empty($company['base_currency'])) {
                $baseCurrency = strtoupper((string) $company['base_currency']);
            }
            if (!empty($company['default_sales_currency'])) {
                $defaultSalesCurrency = strtoupper((string)$company['default_sales_currency']);
            } elseif (!empty($company['base_currency'])) {
                $defaultSalesCurrency = $baseCurrency;
            }

            if (!empty($company['default_purchase_currency'])) {
                $defaultPurchaseCurrency = strtoupper((string)$company['default_purchase_currency']);
            } elseif (!empty($company['base_currency'])) {
                $defaultPurchaseCurrency = $baseCurrency;
            }
        } catch (\Throwable $_) {
            // Keep conservative defaults if settings are unavailable.
        }
        $now = new \DateTime();
        $startOfMonth = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
        $prevMonthStart = (clone $startOfMonth)->modify('-1 month');
        $prevMonthEnd = (clone $startOfMonth)->modify('-1 second');
        $startOfMonthStr = $startOfMonth->format('Y-m-d H:i:s');
        $endOfMonthStr = $endOfMonth->format('Y-m-d H:i:s');
        $prevMonthStartStr = $prevMonthStart->format('Y-m-d H:i:s');
        $prevMonthEndStr = $prevMonthEnd->format('Y-m-d H:i:s');

        try {
            $totalBillsCount = 0;
            $data['payables_by_currency'] = $this->payablesByCurrency($db, $totalBillsCount);
            $data['total_bills_count'] = $totalBillsCount;
        } catch (\Throwable $e) {
            $data['payables_by_currency'] = [];
            $data['total_bills_count'] = 0;
        }

        try {
            // Active purchase flow excludes completed, received, closed, and cancelled POs.
            $rows = $db->query(
                "SELECT
                    UPPER(TRIM(COALESCE(NULLIF(currency_code, ''), NULLIF(currency, ''), ?))) AS currency_code,
                    COUNT(*) AS cnt,
                    SUM(COALESCE(total, 0)) AS total
                 FROM purchase_orders
                 WHERE deleted_at IS NULL
                   AND LOWER(COALESCE(status, '')) IN ('draft', 'confirmed', 'partial', 'partial_received', 'open')
                 GROUP BY 1",
                [$defaultPurchaseCurrency]
            )->getResultArray();
            $data['open_pos'] = 0;
            $data['open_pos_value_by_currency'] = [];
            foreach ($rows as $row) {
                $currencyCode = strtoupper(trim((string) ($row['currency_code'] ?? $defaultPurchaseCurrency)));
                $data['open_pos'] += (int) ($row['cnt'] ?? 0);
                $data['open_pos_value_by_currency'][$currencyCode] = (float) ($row['total'] ?? 0);
            }
        } catch (\Throwable $e) {
            $data['open_pos'] = 0;
            $data['open_pos_value_by_currency'] = [];
        }

        try {
            $row = $db->query("SELECT COUNT(*) AS cnt FROM work_orders WHERE status IN ('planned','in_progress','on_hold')")->getRow();
            $data['open_wos'] = $row ? (int)$row->cnt : 0;
        } catch (\Throwable $e) { $data['open_wos'] = 0; }

        try {
            $row = $db->query(
                "SELECT
                    COUNT(*) AS active_products,
                    SUM(CASE WHEN COALESCE(current_stock, 0) <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
                    SUM(CASE WHEN current_stock > 0 AND current_stock < 10 THEN 1 ELSE 0 END) AS low_stock
                 FROM products
                 WHERE is_active = 1"
            )->getRowArray();
            $data['active_products_count'] = (int) ($row['active_products'] ?? 0);
            $data['out_of_stock_count'] = (int) ($row['out_of_stock'] ?? 0);
            $data['low_stock_count'] = (int) ($row['low_stock'] ?? 0);

            $stockRows = $db->query(
                "SELECT
                    UPPER(TRIM(COALESCE(NULLIF(cost_currency, ''), ?))) AS currency_code,
                    SUM(COALESCE(current_stock, 0) * COALESCE(unit_cost, 0)) AS total
                 FROM products
                 WHERE is_active = 1
                 GROUP BY 1",
                [$defaultSalesCurrency]
            )->getResultArray();
            $data['stock_value_by_currency'] = [];
            foreach ($stockRows as $stockRow) {
                $currencyCode = strtoupper(trim((string) ($stockRow['currency_code'] ?? $defaultSalesCurrency)));
                $data['stock_value_by_currency'][$currencyCode] = (float) ($stockRow['total'] ?? 0);
            }
        } catch (\Throwable $e) {
            $data['active_products_count'] = 0;
            $data['out_of_stock_count'] = 0;
            $data['low_stock_count'] = 0;
            $data['stock_value_by_currency'] = [];
        }

        try {
            $row = $db->query("SELECT COUNT(*) AS cnt FROM sales_orders WHERE deleted_at IS NULL AND status = 'confirmed'")->getRowArray();
            $data['confirmed_sales_orders_count'] = (int) ($row['cnt'] ?? 0);
        } catch (\Throwable $e) { $data['confirmed_sales_orders_count'] = 0; }

        try {
            $row = $db->query("SELECT COUNT(*) AS cnt FROM delivery_orders WHERE status = 'shipped'")->getRowArray();
            $data['shipments_in_transit_count'] = (int) ($row['cnt'] ?? 0);
        } catch (\Throwable $e) { $data['shipments_in_transit_count'] = 0; }

        try {
            // Recent work order activity (include product code)
            $rows = $db->query("SELECT wo.id, wo.wo_number, wo.status, wo.created_at, wo.customer_name, p.code AS product_code FROM work_orders wo LEFT JOIN products p ON p.id = wo.product_id ORDER BY wo.id DESC LIMIT 6")->getResultArray();
            $data['recent_activity'] = $rows ?: [];
        } catch (\Throwable $e) { $data['recent_activity'] = []; }

        try {
            // Trending products (real data)
            // Variable products carry no code/sku of their own - the codes live on
            // their variants, so fall back to those instead of rendering a bare "-".
            $products = $db->query("
                SELECT p.id, p.public_id,
                       COALESCE(NULLIF(p.code, ''), NULLIF(p.sku, ''),
                                (SELECT NULLIF(pv.art_number, '') FROM product_variants pv
                                  WHERE pv.product_id = p.id AND pv.art_number IS NOT NULL AND pv.art_number <> ''
                                  ORDER BY pv.id LIMIT 1)) AS code,
                       (SELECT COUNT(*) FROM product_variants pv2 WHERE pv2.product_id = p.id) AS variant_count,
                       p.name, p.current_stock, p.unit_cost, p.cost_currency
                  FROM products p
                 WHERE p.is_active = 1
                 ORDER BY p.current_stock ASC, p.id DESC
                 LIMIT 6")->getResultArray();
            $data['trending_products'] = $products ?: [];
        } catch (\Throwable $e) { $data['trending_products'] = []; }

        $pendingInvoiceRows = [];
        $recentInvoiceRows = [];
        $unpaidInvoiceCount = 0;
        $overdueInvoiceCount = 0;
        if (!empty($dataAccess['dashboard_sales_visible'])) {
            try {
            $invoiceTableExists = $db->tableExists('customer_invoices');
            $customerTableExists = $db->tableExists('customers');
            $salesOrderTableExists = $db->tableExists('sales_orders');
            $allocationTableExists = $db->tableExists('customer_payment_allocations');
            $paymentTableExists = $db->tableExists('customer_payments');
            if ($invoiceTableExists) {
                $invoiceCols = array_map('strtolower', $db->getFieldNames('customer_invoices'));
                $hasInvoiceCol = static function (string $col) use ($invoiceCols): bool {
                    return in_array(strtolower($col), $invoiceCols, true);
                };

                $paymentJoin = '';
                if ($allocationTableExists && $paymentTableExists) {
                    $paymentJoin = "
                        LEFT JOIN (
                            SELECT a.invoice_id, SUM(COALESCE(a.allocated_amount, 0)) AS paid_amount
                            FROM customer_payment_allocations a
                            INNER JOIN customer_payments p ON p.id = a.payment_id
                            WHERE p.posted_entry_id IS NOT NULL
                              AND p.posted_entry_id > 0
                            GROUP BY a.invoice_id
                        ) payments ON payments.invoice_id = ci.id
                    ";
                }

                $customerJoin = $customerTableExists ? "LEFT JOIN customers c ON c.id = ci.customer_id" : '';
                $salesOrderJoin = ($salesOrderTableExists && $hasInvoiceCol('sales_order_id'))
                    ? "LEFT JOIN sales_orders so ON so.id = ci.sales_order_id"
                    : '';
                $dueExpr = $hasInvoiceCol('due_date') ? 'ci.due_date' : 'ci.created_at';
                $issueExpr = $hasInvoiceCol('issue_date') ? 'ci.issue_date' : 'ci.created_at';
                $currencyExpr = $hasInvoiceCol('currency_code') ? "COALESCE(NULLIF(ci.currency_code, ''), ?)" : "?";
                $statusExpr = $hasInvoiceCol('status') ? "LOWER(COALESCE(ci.status, ''))" : "''";
                $invoiceStatusExpr = $hasInvoiceCol('status') ? 'ci.status' : "'open'";
                $paidExpr = ($allocationTableExists && $paymentTableExists) ? 'COALESCE(payments.paid_amount, 0)' : '0';
                $customerNameExpr = $customerTableExists
                    ? "COALESCE(NULLIF(c.name, ''), NULLIF(c.company_name, ''), 'Customer')"
                    : "'Customer'";
                $salesOrderExpr = ($salesOrderTableExists && $hasInvoiceCol('sales_order_id'))
                    ? 'so.order_number'
                    : "NULL";

                $pendingInvoiceRows = $db->query(
                    "
                    SELECT
                        ci.id,
                        ci.invoice_number,
                        {$invoiceStatusExpr} AS status,
                        {$customerNameExpr} AS customer_name,
                        {$salesOrderExpr} AS sales_order_number,
                        {$issueExpr} AS issue_date,
                        {$dueExpr} AS due_date,
                        {$currencyExpr} AS currency_code,
                        COALESCE(ci.total_amount, 0) AS total_amount,
                        {$paidExpr} AS paid_amount,
                        GREATEST(0, COALESCE(ci.total_amount, 0) - {$paidExpr}) AS outstanding
                    FROM customer_invoices ci
                    {$customerJoin}
                    {$salesOrderJoin}
                    {$paymentJoin}
                    WHERE {$statusExpr} NOT IN ('draft', 'paid', 'cancelled', 'void')
                      AND ci.deleted_at IS NULL
                    HAVING outstanding > 0
                    ORDER BY COALESCE({$issueExpr}, {$dueExpr}) DESC, outstanding DESC, ci.id DESC
                    LIMIT 5
                    ",
                    [$defaultSalesCurrency]
                )->getResultArray();

                $recentInvoiceRows = $db->query(
                    "
                    SELECT
                        ci.id,
                        ci.invoice_number,
                        {$invoiceStatusExpr} AS status,
                        {$customerNameExpr} AS customer_name,
                        {$salesOrderExpr} AS sales_order_number,
                        {$issueExpr} AS issue_date,
                        {$dueExpr} AS due_date,
                        {$currencyExpr} AS currency_code,
                        COALESCE(ci.total_amount, 0) AS total_amount,
                        {$paidExpr} AS paid_amount,
                        GREATEST(0, COALESCE(ci.total_amount, 0) - {$paidExpr}) AS outstanding
                    FROM customer_invoices ci
                    {$customerJoin}
                    {$salesOrderJoin}
                    {$paymentJoin}
                    WHERE {$statusExpr} NOT IN ('cancelled', 'void')
                      AND ci.deleted_at IS NULL
                    ORDER BY COALESCE({$issueExpr}, {$dueExpr}) DESC, ci.id DESC
                    LIMIT 6
                    ",
                    [$defaultSalesCurrency]
                )->getResultArray();

                $invoiceAttention = $db->query(
                    "SELECT
                        COUNT(*) AS unpaid_count,
                        SUM(CASE WHEN balances.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_count
                     FROM (
                        SELECT
                            {$dueExpr} AS due_date,
                            GREATEST(0, COALESCE(ci.total_amount, 0) - {$paidExpr}) AS outstanding
                        FROM customer_invoices ci
                        {$paymentJoin}
                        WHERE {$statusExpr} NOT IN ('draft', 'paid', 'cancelled', 'void')
                          AND ci.deleted_at IS NULL
                     ) balances
                     WHERE balances.outstanding > 0"
                )->getRowArray();
                $unpaidInvoiceCount = (int) ($invoiceAttention['unpaid_count'] ?? 0);
                $overdueInvoiceCount = (int) ($invoiceAttention['overdue_count'] ?? 0);
            }
            } catch (\Throwable $e) {
                $pendingInvoiceRows = [];
                $recentInvoiceRows = [];
                $unpaidInvoiceCount = 0;
                $overdueInvoiceCount = 0;
            }
        }

        $emptyTopCustomers = ['currency' => $defaultSalesCurrency, 'labels' => [], 'values' => [], 'summary' => ['label' => 'No customer data', 'amount' => 0, 'count' => 0, 'total' => 0]];
        $data['top_customers_month'] = $emptyTopCustomers;
        $data['top_customers_year'] = $emptyTopCustomers;
        if (!empty($dataAccess['dashboard_sales_visible'])) {
            try {
                $periodStartMonth = (clone $startOfMonth)->format('Y-m-d');
                $periodEndMonth = (clone $endOfMonth)->format('Y-m-d');
                $periodStartYear = (clone $now)->setDate((int) $now->format('Y'), 1, 1)->setTime(0, 0, 0)->format('Y-m-d');
                $periodEndYear = (clone $now)->setDate((int) $now->format('Y'), 12, 31)->setTime(23, 59, 59)->format('Y-m-d');

            $buildTopCustomers = static function (string $startDate, string $endDate) use ($db, $defaultSalesCurrency): array {
                try {
                    $rows = $db->query(
                        "
                        SELECT
                            ci.customer_id,
                            COALESCE(NULLIF(c.name, ''), NULLIF(c.company_name, ''), 'Customer') AS customer_name,
                            SUM(COALESCE(ci.total_amount, 0)) AS total_amount,
                            COUNT(ci.id) AS invoice_count
                        FROM customer_invoices ci
                        LEFT JOIN customers c ON c.id = ci.customer_id
                        WHERE ci.deleted_at IS NULL
                          AND LOWER(COALESCE(ci.status, '')) NOT IN ('draft', 'cancelled', 'void')
                          AND DATE(COALESCE(ci.issue_date, ci.created_at)) >= ?
                          AND DATE(COALESCE(ci.issue_date, ci.created_at)) <= ?
                          AND UPPER(TRIM(COALESCE(NULLIF(ci.currency_code, ''), ?))) = ?
                        GROUP BY ci.customer_id, c.name, c.company_name
                        ORDER BY total_amount DESC, invoice_count DESC, customer_name ASC
                        LIMIT 5
                        ",
                        [$startDate, $endDate, $defaultSalesCurrency, $defaultSalesCurrency]
                    )->getResultArray();
                } catch (\Throwable $e) {
                    return [];
                }

                $labels = [];
                $values = [];
                $topCustomer = null;
                $totalValue = 0.0;
                foreach ($rows as $row) {
                    $label = trim((string) ($row['customer_name'] ?? 'Customer'));
                    if ($label === '') {
                        $label = 'Customer';
                    }
                    $amount = round((float) ($row['total_amount'] ?? 0), 2);
                    $labels[] = $label;
                    $values[] = $amount;
                    $totalValue += $amount;
                    if ($topCustomer === null) {
                        $topCustomer = [
                            'label' => $label,
                            'amount' => $amount,
                            'count' => (int) ($row['invoice_count'] ?? 0),
                        ];
                    }
                }

                return [
                    'currency' => $defaultSalesCurrency,
                    'labels' => $labels,
                    'values' => $values,
                    'summary' => [
                        'label' => $topCustomer['label'] ?? 'No customer data',
                        'amount' => $topCustomer['amount'] ?? 0.0,
                        'count' => $topCustomer['count'] ?? 0,
                        'total' => round($totalValue, 2),
                    ],
                ];
            };

                $data['top_customers_month'] = $buildTopCustomers($periodStartMonth, $periodEndMonth);
                $data['top_customers_year'] = $buildTopCustomers($periodStartYear, $periodEndYear);
            } catch (\Throwable $e) {
                $data['top_customers_month'] = $emptyTopCustomers;
                $data['top_customers_year'] = $emptyTopCustomers;
            }
        }

        // Sales come from the invoices this system issues. `sales_cache` is the Odoo
        // mirror and stopped being fed, so it reported 0 for every native sale.
        // Currencies stay separate: no FX is inferred anywhere on this dashboard.
        $salesMtdByCurrency = $this->salesByCurrency($db, $defaultSalesCurrency, $startOfMonthStr, $endOfMonthStr);
        $salesPrevByCurrency = $this->salesByCurrency($db, $defaultSalesCurrency, $prevMonthStartStr, $prevMonthEndStr);

        // The trend compares like with like, so it uses the default sales currency only.
        $totalSalesMtd = $salesMtdByCurrency[$defaultSalesCurrency] ?? 0.0;
        $totalSalesLastMonth = $salesPrevByCurrency[$defaultSalesCurrency] ?? 0.0;

        $vendorPaymentsByCurrency = [];
        try {
            $paymentRows = $db->table('vendor_payments')
                ->select("UPPER(TRIM(COALESCE(NULLIF(currency_code, ''), " . $db->escape($defaultPurchaseCurrency) . "))) AS currency_code, SUM(IFNULL(amount,0)) AS total", false)
                ->where('payment_date >=', $startOfMonth->format('Y-m-d'))
                ->where('payment_date <=', $endOfMonth->format('Y-m-d'))
                ->groupStart()
                    ->where("LOWER(COALESCE(status,'')) = 'posted'", null, false)
                    ->orWhere('posted_entry_id IS NOT NULL', null, false)
                ->groupEnd()
                ->groupBy('1', false)
                ->get()->getResultArray();
            foreach ($paymentRows as $paymentRow) {
                $paymentCurrency = strtoupper(trim((string) ($paymentRow['currency_code'] ?? $defaultPurchaseCurrency)));
                $vendorPaymentsByCurrency[$paymentCurrency] = (float) ($paymentRow['total'] ?? 0);
            }
            $vendorPaymentsMtd = array_sum($vendorPaymentsByCurrency);
        } catch (\Throwable $e) {
            $vendorPaymentsMtd = 0.0;
            $vendorPaymentsByCurrency = [];
        }

        try {
            // Customer receivables from customer invoices (unpaid/partially paid)
            // Since there's no balance column, we calculate from payments
            // Calculate receivables grouped by invoice currency.
            // Payments are allocated via `customer_payment_allocations` (payment_id -> invoice_id)
            $receivablesByCurrency = $this->receivablesByCurrency($db, $defaultSalesCurrency);
            
            // Calculate total for backward compatibility (sum all currencies)
            $customerReceivables = array_sum($receivablesByCurrency);
            $data['receivables_by_currency'] = $receivablesByCurrency;
        } catch (\Throwable $e) {
            $customerReceivables = 0.0;
            $data['receivables_by_currency'] = [];
        }

        $data['total_sales_mtd'] = $totalSalesMtd;
        $data['vendor_payments_mtd'] = $vendorPaymentsMtd;
        $data['customer_receivables'] = $customerReceivables;
        $data['total_sales_last_month'] = $totalSalesLastMonth;
        
        // Format vendor payables for display (show each currency separately)
        $payablesDisplay = '';
        $payablesCount = count($data['payables_by_currency']);
        if ($payablesCount > 0) {
            $formatted = [];
            foreach ($data['payables_by_currency'] as $currency => $amount) {
                $code = strtoupper(trim((string)$currency));
                if ($code === '') {
                    $code = $defaultPurchaseCurrency;
                }
                $formatted[] = $code . ' ' . number_format($amount, 2);
            }
            $payablesDisplay = implode(' | ', $formatted);
        } else {
            $payablesDisplay = $defaultPurchaseCurrency . ' 0.00';
        }
        
        // Format customer receivables for display (show each currency separately)
        $receivablesDisplay = '';
        $receivablesCount = count($data['receivables_by_currency']);
        if ($receivablesCount > 0) {
            $formatted = [];
            foreach ($data['receivables_by_currency'] as $currency => $amount) {
                $code = strtoupper(trim((string)$currency));
                if ($code === '') {
                    $code = $defaultSalesCurrency;
                }
                $formatted[] = $code . ' ' . number_format($amount, 2);
            }
            $receivablesDisplay = implode(' | ', $formatted);
        } else {
            $receivablesDisplay = $defaultSalesCurrency . ' 0.00';
        }

        $vendorPaymentsDisplay = $defaultPurchaseCurrency . ' 0.00';
        if ($vendorPaymentsByCurrency !== []) {
            $formatted = [];
            foreach ($vendorPaymentsByCurrency as $currency => $amount) {
                $formatted[] = $currency . ' ' . number_format($amount, 2);
            }
            $vendorPaymentsDisplay = implode(' | ', $formatted);
        }
        
        $salesDisplay = $defaultSalesCurrency . ' 0.00';
        if ($salesMtdByCurrency !== []) {
            $formatted = [];
            foreach ($salesMtdByCurrency as $currency => $amount) {
                $formatted[] = $currency . ' ' . number_format($amount, 2);
            }
            $salesDisplay = implode(' | ', $formatted);
        }

        $cards = [];
        if (!empty($dataAccess['dashboard_sales_visible'])) {
            $cards[] = [
                'label' => 'Total Sales (MTD)',
                'value' => $salesDisplay,
                'hint' => 'vs last month: ' . ($totalSalesLastMonth ? sprintf('%+.1f%%', ($totalSalesMtd - $totalSalesLastMonth) / max($totalSalesLastMonth, 1) * 100) : '+0.0%'),
                'icon' => 'bi-cash-stack',
                'link' => base_url('/reports'),
                'linkLabel' => 'View Sales',
                'bg' => 'linear-gradient(135deg,#eef2ff,#e0e7ff)',
                'textColor' => 'text-dark'
            ];
        }

        if (!empty($dataAccess['dashboard_finance_visible'])) {
            $cards[] = [
                'label' => 'Vendor Payments (MTD)',
                'value' => $vendorPaymentsDisplay,
                'hint' => 'Posted vendor payments',
                'icon' => 'bi-graph-up',
                'link' => base_url('/reports'),
                'linkLabel' => 'Profit Report',
                'bg' => 'linear-gradient(135deg,#ecfdf5,#d1fae5)',
                'textColor' => 'text-dark'
            ];
        }

        if (!empty($dataAccess['dashboard_purchases_visible'])) {
            $cards[] = [
                'label' => 'Vendor Payables',
                'value' => $payablesDisplay,
                'hint' => $data['total_bills_count'] ? ($data['total_bills_count'] . ' unpaid bill(s)') : 'No unpaid bills',
                'icon' => 'bi-wallet2',
                'link' => base_url('/vendor-bills'),
                'linkLabel' => 'Vendor Bills',
                'bg' => 'linear-gradient(135deg,#fffbeb,#fde68a)',
                'textColor' => 'text-dark'
            ];
        }

        if (!empty($dataAccess['dashboard_finance_visible'])) {
            $cards[] = [
                'label' => 'Customer Receivables',
                'value' => $receivablesDisplay,
                'hint' => $receivablesCount ? ($receivablesCount . ' currency(ies) with unpaid invoices') : 'No unpaid invoices',
                'icon' => 'bi-wallet',
                'link' => base_url('/customer-invoices'),
                'linkLabel' => 'Customer Invoices',
                'bg' => 'linear-gradient(135deg,#e0f2fe,#bae6fd)',
                'textColor' => 'text-dark'
            ];
        }

        $data['kpi_cards'] = $cards;

        // Optional debug: expose receivables by currency when requested via ?dbg_receiv=1
        try {
            $dbg = isset($_GET['dbg_receiv']) ? ($_GET['dbg_receiv'] == '1') : false;
            if ($dbg) {
                $data['receivables_debug'] = $data['receivables_by_currency'] ?? [];
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Keep document currencies isolated. Comparing or adding currencies requires an
        // explicit FX conversion, which this dashboard intentionally does not infer.
        $chartHistoryStart = new \DateTime(((int) $now->format('Y') - 1) . '-01-01 00:00:00');
        $monthKeys = [];
        $monthLabels = [];
        $cursor = clone $chartHistoryStart;
        while ($cursor <= $startOfMonth) {
            $key = $cursor->format('Y-m');
            $monthKeys[] = $key;
            $monthLabels[] = $cursor->format('M Y');
            $cursor->modify('+1 month');
        }
        $emptyChartSeries = [
            'revenue' => array_fill_keys($monthKeys, 0.0),
            'expenses' => array_fill_keys($monthKeys, 0.0),
        ];
        $chartSeriesByCurrency = [];
        foreach (array_values(array_unique([$baseCurrency, $defaultSalesCurrency, $defaultPurchaseCurrency])) as $currencyCode) {
            $chartSeriesByCurrency[$currencyCode] = $emptyChartSeries;
        }

        try {
            $ledgerRows = $db->query(
                "SELECT
                    DATE_FORMAT(je.entry_date, '%Y-%m') AS month,
                    UPPER(TRIM(COALESCE(NULLIF(jl.currency_code, ''), ?))) AS currency_code,
                    SUM(CASE WHEN a.type = 'Revenue' THEN COALESCE(jl.credit, 0) - COALESCE(jl.debit, 0) ELSE 0 END) AS revenue,
                    SUM(CASE WHEN a.type = 'Expense' THEN COALESCE(jl.debit, 0) - COALESCE(jl.credit, 0) ELSE 0 END) AS expenses
                 FROM journal_entries je
                 JOIN journal_lines jl ON jl.entry_id = je.id
                 JOIN accounts a ON a.id = jl.account_id
                 WHERE je.entry_date >= ?
                   AND je.entry_date <= ?
                   AND a.type IN ('Revenue', 'Expense')
                 GROUP BY month, currency_code
                 ORDER BY month, currency_code",
                [$baseCurrency, $chartHistoryStart->format('Y-m-d H:i:s'), $endOfMonthStr]
            )->getResultArray();

            foreach ($ledgerRows as $row) {
                $month = (string) ($row['month'] ?? '');
                $currencyCode = strtoupper(trim((string) ($row['currency_code'] ?? $baseCurrency)));
                if (!isset($emptyChartSeries['revenue'][$month])) {
                    continue;
                }
                if (!isset($chartSeriesByCurrency[$currencyCode])) {
                    $chartSeriesByCurrency[$currencyCode] = $emptyChartSeries;
                }
                $chartSeriesByCurrency[$currencyCode]['revenue'][$month] = !empty($dataAccess['dashboard_sales_visible'])
                    ? (float) ($row['revenue'] ?? 0)
                    : 0.0;
                $chartSeriesByCurrency[$currencyCode]['expenses'][$month] = !empty($dataAccess['dashboard_finance_visible'])
                    ? (float) ($row['expenses'] ?? 0)
                    : 0.0;
            }
        } catch (\Throwable $e) {
            log_message('error', 'Dashboard revenue matrix query failed: ' . $e->getMessage());
        }

        $orderedChartSeries = [];
        foreach (array_values(array_unique([$baseCurrency, $defaultSalesCurrency, $defaultPurchaseCurrency])) as $currencyCode) {
            if (isset($chartSeriesByCurrency[$currencyCode])) {
                $orderedChartSeries[$currencyCode] = $chartSeriesByCurrency[$currencyCode];
            }
        }
        $otherChartCurrencies = array_diff(array_keys($chartSeriesByCurrency), array_keys($orderedChartSeries));
        sort($otherChartCurrencies);
        foreach ($otherChartCurrencies as $currencyCode) {
            $orderedChartSeries[$currencyCode] = $chartSeriesByCurrency[$currencyCode];
        }
        foreach ($orderedChartSeries as &$series) {
            $series['revenue'] = array_values($series['revenue']);
            $series['expenses'] = array_values($series['expenses']);
        }
        unset($series);

        $chartCurrency = $this->pickChartCurrency($orderedChartSeries, $baseCurrency);
        $selectedChartSeries = $orderedChartSeries[$chartCurrency] ?? ['revenue' => [], 'expenses' => []];
        $data['chart_labels'] = $monthLabels;
        $data['chart_series_by_currency'] = $orderedChartSeries;
        $data['revenue_timeline'] = $selectedChartSeries['revenue'];
        $data['expense_timeline'] = $selectedChartSeries['expenses'];
        $data['chart_currency'] = $chartCurrency;

        try {
            $posForecast = $db->query("SELECT po.id, po.order_date, COALESCE(v.name, 'Vendor') AS vendor_name FROM purchase_orders po LEFT JOIN vendors v ON v.id = po.vendor_id WHERE po.status IS NULL OR po.status NOT IN ('received','closed') ORDER BY po.order_date ASC LIMIT 4")->getResultArray();
            $data['upcoming_pos'] = $posForecast ?: [];
        } catch (\Throwable $e) { $data['upcoming_pos'] = []; }

        $formatCurrencyMap = static function (array $amounts, string $fallbackCurrency): string {
            if ($amounts === []) {
                return $fallbackCurrency . ' 0.00';
            }
            $formatted = [];
            foreach ($amounts as $currency => $amount) {
                $formatted[] = strtoupper((string) $currency) . ' ' . number_format((float) $amount, 2);
            }
            return implode(' | ', $formatted);
        };
        $data['stock_value_display'] = $formatCurrencyMap($data['stock_value_by_currency'] ?? [], $defaultSalesCurrency);
        $data['open_pos_value_display'] = $formatCurrencyMap($data['open_pos_value_by_currency'] ?? [], $defaultPurchaseCurrency);

        $ownerMetrics = [];
        if ($policy->can('inventory', 'read')) {
            $ownerMetrics[] = [
                'label' => 'Inventory Risk',
                'value' => number_format((int) ($data['out_of_stock_count'] ?? 0)) . ' out',
                'detail' => number_format((int) ($data['low_stock_count'] ?? 0)) . ' low stock / ' . number_format((int) ($data['active_products_count'] ?? 0)) . ' active items',
                'icon' => 'bi-box-seam',
                'tone' => ((int) ($data['out_of_stock_count'] ?? 0)) > 0 ? 'danger' : 'success',
                'link' => base_url('/products'),
                'attention' => ((int) ($data['out_of_stock_count'] ?? 0)) > 0,
            ];
        }
        if (!empty($dataAccess['dashboard_sales_visible']) && $policy->can('sales_orders', 'read')) {
            $ownerMetrics[] = [
                'label' => 'Sales Fulfillment',
                'value' => number_format((int) ($data['confirmed_sales_orders_count'] ?? 0)) . ' confirmed',
                'detail' => 'Orders awaiting fulfillment progress',
                'icon' => 'bi-bag-check',
                'tone' => 'violet',
                'link' => base_url('/sales-orders'),
                'attention' => ((int) ($data['confirmed_sales_orders_count'] ?? 0)) > 0,
            ];
        }
        if (!empty($dataAccess['dashboard_purchases_visible']) && $policy->can('purchase_orders', 'read')) {
            $ownerMetrics[] = [
                'label' => 'Purchase Flow',
                'value' => number_format((int) ($data['open_pos'] ?? 0)) . ' active',
                'detail' => 'Draft, confirmed, or partially received POs',
                'icon' => 'bi-receipt-cutoff',
                'tone' => 'amber',
                'link' => base_url('/new-purchase-orders'),
                'attention' => ((int) ($data['open_pos'] ?? 0)) > 0,
            ];
        }
        if ($policy->can('delivery_orders', 'read')) {
            $ownerMetrics[] = [
                'label' => 'Delivery Tracking',
                'value' => number_format((int) ($data['shipments_in_transit_count'] ?? 0)) . ' shipped',
                'detail' => 'Shipments awaiting delivery confirmation',
                'icon' => 'bi-truck',
                'tone' => 'teal',
                'link' => base_url('/delivery-orders'),
                'attention' => ((int) ($data['shipments_in_transit_count'] ?? 0)) > 0,
            ];
        }
        if (!empty($dataAccess['dashboard_finance_visible'])) {
            $ownerMetrics[] = [
                'label' => 'Collections',
                'value' => number_format($overdueInvoiceCount) . ' overdue',
                'detail' => number_format($unpaidInvoiceCount) . ' unpaid customer invoices',
                'icon' => 'bi-cash-coin',
                'tone' => $overdueInvoiceCount > 0 ? 'danger' : 'success',
                'link' => base_url('/customer-invoices'),
                'attention' => $overdueInvoiceCount > 0,
            ];
        }
        if ($policy->can('work_orders', 'read')) {
            $ownerMetrics[] = [
                'label' => 'Production Load',
                'value' => number_format((int) ($data['open_wos'] ?? 0)) . ' active',
                'detail' => 'Planned, in progress, or on hold',
                'icon' => 'bi-clipboard-data',
                'tone' => 'slate',
                'link' => base_url('/work-orders'),
                'attention' => false,
            ];
        }
        $data['owner_metrics'] = $ownerMetrics;
        $data['owner_attention_count'] = count(array_filter($ownerMetrics, static fn(array $metric): bool => !empty($metric['attention'])));

        try {
            $alerts = [];
            if ($data['open_wos'] > 0) {
                $alerts[] = ['icon' => 'bi-list-check', 'title' => 'Open Work Orders', 'text' => $data['open_wos'] . ' pending work orders', 'bg' => 'linear-gradient(135deg,#eef2ff,#e0e7ff)'];
            }
            if ($data['open_pos'] > 0) {
                $alerts[] = ['icon' => 'bi-receipt', 'title' => 'Open Purchase Orders', 'text' => $data['open_pos'] . ' awaiting receipts', 'bg' => 'linear-gradient(135deg,#fffbeb,#fde68a)'];
            }
            if ($customerReceivables > 0) {
                // Prefer per-currency display (e.g. "USD 1,000.00 | PKR 500,000.00") when available
                $receivablesText = isset($receivablesDisplay) && $receivablesDisplay ? $receivablesDisplay : ('$' . number_format($customerReceivables, 2));
                $alerts[] = ['icon' => 'bi-currency-dollar', 'title' => 'Customer Receivables', 'text' => $receivablesText . ' tied to open orders', 'bg' => 'linear-gradient(135deg,#ecfdf5,#d1fae5)'];
            }
            $data['alerts'] = $alerts;
        } catch (\Throwable $e) { $data['alerts'] = []; }

        $data['pending_customer_invoices'] = $pendingInvoiceRows;
        $data['recent_customer_invoices'] = $recentInvoiceRows;

        return view('corelynk/dashboard', $data);
    }

    /**
     * Lightweight JSON endpoint for dashboard FX widget.
     * Server-side fetch avoids browser CORS restrictions and allows basic caching.
     */
    public function fxRates()
    {
        $this->requireAuth();

        $base = strtoupper((string)($this->request->getGet('base') ?? 'USD'));
        $symbolsRaw = (string)($this->request->getGet('symbols') ?? 'PKR,EUR,GBP');
        $symbols = array_values(array_filter(array_map('strtoupper', array_map('trim', explode(',', $symbolsRaw)))));

        // Safety limits
        if (!preg_match('/^[A-Z]{3}$/', $base)) {
            $base = 'USD';
        }
        $symbols = array_values(array_filter($symbols, static fn($s) => preg_match('/^[A-Z]{3}$/', $s)));
        $symbols = array_slice($symbols, 0, 10);

        $cache = 
            
            \Config\Services::cache();
        $cacheKey = 'fx_rates_' . $base . '_' . implode('_', $symbols);

        if ($cache) {
            $cached = $cache->get($cacheKey);
            if (is_array($cached)) {
                return $this->jsonResponse($cached);
            }
        }

        $payload = [
            'success' => false,
            'base' => $base,
            'symbols' => $symbols,
            'rates' => new \stdClass(),
            'provider' => null,
            'fetched_at' => gmdate('c'),
            'cached' => false,
        ];

        // Prefer open.er-api.com (simple + no key)
        $providers = [];
        $providers[] = [
            'name' => 'open.er-api.com',
            'url' => 'https://open.er-api.com/v6/latest/' . rawurlencode($base),
            'parser' => static function(array $json) use ($symbols) {
                // expected: { result:'success', rates:{...} }
                if (($json['result'] ?? null) !== 'success' || empty($json['rates']) || !is_array($json['rates'])) {
                    return null;
                }
                $out = [];
                foreach ($symbols as $sym) {
                    if (isset($json['rates'][$sym])) {
                        $out[$sym] = (float)$json['rates'][$sym];
                    }
                }
                return $out;
            }
        ];

        // Fallback to exchangerate.host
        if (!empty($symbols)) {
            $providers[] = [
                'name' => 'exchangerate.host',
                'url' => 'https://api.exchangerate.host/latest?base=' . rawurlencode($base) . '&symbols=' . rawurlencode(implode(',', $symbols)),
                'parser' => static function(array $json) use ($symbols) {
                    if (empty($json['rates']) || !is_array($json['rates'])) {
                        return null;
                    }
                    $out = [];
                    foreach ($symbols as $sym) {
                        if (isset($json['rates'][$sym])) {
                            $out[$sym] = (float)$json['rates'][$sym];
                        }
                    }
                    return $out;
                }
            ];
        }

        $http = \Config\Services::curlrequest([
            'timeout' => 5,
            'connect_timeout' => 3,
            'http_errors' => false,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Corelynk/1.0 (dashboard fx widget)',
            ],
        ]);

        foreach ($providers as $provider) {
            try {
                $res = $http->get($provider['url']);
                $status = (int)$res->getStatusCode();
                if ($status < 200 || $status >= 300) {
                    continue;
                }

                $json = json_decode((string)$res->getBody(), true);
                if (!is_array($json)) {
                    continue;
                }

                $rates = $provider['parser']($json);
                if (!is_array($rates) || empty($rates)) {
                    continue;
                }

                $payload['success'] = true;
                $payload['rates'] = $rates;
                $payload['provider'] = $provider['name'];
                break;
            } catch (\Throwable $e) {
                // try next provider
                continue;
            }
        }

        // Best-effort fallback from DB if online providers failed
        if (!$payload['success']) {
            try {
                $db = \Config\Database::connect();
                if (!empty($symbols)) {
                    $rows = $db->table('exchange_rates')
                        ->select('quote_code, rate, updated_at')
                        ->where('base_code', $base)
                        ->whereIn('quote_code', $symbols)
                        ->orderBy('updated_at', 'DESC')
                        ->get()
                        ->getResultArray();

                    $rates = [];
                    foreach ($rows as $r) {
                        if (!isset($rates[$r['quote_code']]) && $r['rate'] !== null) {
                            $rates[$r['quote_code']] = (float)$r['rate'];
                        }
                    }

                    if (!empty($rates)) {
                        $payload['success'] = true;
                        $payload['rates'] = $rates;
                        $payload['provider'] = 'local_db';
                    }
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }

        if ($cache) {
            // cache for 15 minutes
            $cache->save($cacheKey, $payload, 900);
        }

        return $this->jsonResponse($payload, $payload['success'] ? 200 : 502);
    }

    /**
     * Which currency the Annual Revenue Matrix opens on.
     *
     * It reads the ledger, so it opens on the currency the books are kept in —
     * not the sales currency, which can hold revenue and no expenses at all and
     * so hides every posted cost behind a flat zero line. If the base currency
     * has no postings yet, the first currency that does is used instead.
     *
     * @param array<string, array{revenue: list<float>, expenses: list<float>}> $series
     */
    private function pickChartCurrency(array $series, string $baseCurrency): string
    {
        $hasPostings = static fn (string $code): bool => isset($series[$code])
            && (array_sum($series[$code]['revenue'] ?? []) != 0.0
                || array_sum($series[$code]['expenses'] ?? []) != 0.0);

        if ($hasPostings($baseCurrency)) {
            return $baseCurrency;
        }

        foreach (array_keys($series) as $code) {
            if ($hasPostings($code)) {
                return $code;
            }
        }

        return isset($series[$baseCurrency]) ? $baseCurrency : ((string) array_key_first($series) ?: $baseCurrency);
    }

    /**
     * Sales for a period, per currency. Reads the invoices this system issues —
     * `sales_cache` is the Odoo mirror and no longer reflects native sales.
     * Currencies are kept apart: this dashboard never infers an FX rate.
     */
    private function salesByCurrency($db, string $defaultCurrency, string $from, string $to): array
    {
        try {
            $rows = $db->query(
                "SELECT
                    UPPER(TRIM(COALESCE(NULLIF(ci.currency_code, ''), ?))) AS currency_code,
                    SUM(COALESCE(ci.total_amount, 0)) AS total
                 FROM customer_invoices ci
                 WHERE ci.deleted_at IS NULL
                   AND LOWER(COALESCE(ci.status, '')) NOT IN ('draft', 'cancelled', 'void')
                   AND DATE(COALESCE(ci.issue_date, ci.created_at)) >= DATE(?)
                   AND DATE(COALESCE(ci.issue_date, ci.created_at)) <= DATE(?)
                 GROUP BY 1",
                [$defaultCurrency, $from, $to]
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Corelynk::salesByCurrency failed: ' . $e->getMessage());

            return [];
        }

        $totals = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['currency_code'] ?? '')));
            if ($code === '') {
                $code = $defaultCurrency;
            }
            $totals[$code] = round((float) ($row['total'] ?? 0), 2);
        }

        return $totals;
    }

    /**
     * Outstanding customer receivables, per currency: issued invoices less the
     * payments allocated to them. Drafts are excluded — nothing reaches
     * Accounts Receivable until the invoice is posted, so counting them here
     * would put the tile out of step with the ledger.
     */
    /**
     * Outstanding vendor bills grouped by their own currency.
     *
     * Open amount = bill total minus what posted payments allocated to it.
     * `vendor_bills.balance` is not written back when a payment is posted, so
     * reading that column reported every bill as fully unpaid forever.
     *
     * @return array<string, float> currency code => open amount
     */
    private function payablesByCurrency($db, ?int &$billCount = null): array
    {
        // Vendor payables from vendor bills (real outstanding balances) - grouped by currency.
        $vbCols = array_map('strtolower', $db->getFieldNames('vendor_bills'));
        $has = static function (string $col) use ($vbCols): bool {
            return in_array(strtolower($col), $vbCols, true);
        };

        $currencyExpr = $has('currency_code') ? "vb.currency_code" : "'PKR'";
        $statusExpr = $has('status') ? "LOWER(COALESCE(vb.status, ''))" : "''";

        $totalCandidates = [];
        if ($has('total_amount')) $totalCandidates[] = 'vb.total_amount';
        if ($has('amount')) $totalCandidates[] = 'vb.amount';
        if ($has('grand_total')) $totalCandidates[] = 'vb.grand_total';
        $totalBaseExpr = !empty($totalCandidates)
            ? 'COALESCE(' . implode(', ', $totalCandidates) . ', 0)'
            : '0';

        // What has actually been paid lives in vendor_payment_allocations;
        // vendor_bills.balance is never written back when a payment is posted,
        // so it always equals the full bill. Same aggregate the vendor bills
        // list and GRN screens use.
        $paidJoin = $db->tableExists('vendor_payment_allocations')
            ? "LEFT JOIN (
                    SELECT vpa.vendor_bill_id,
                           SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)) AS paid
                    FROM vendor_payment_allocations vpa
                    JOIN vendor_payments vp ON vp.id = vpa.payment_id
                    WHERE LOWER(COALESCE(vp.status, '')) = 'posted'
                    GROUP BY vpa.vendor_bill_id
               ) pa ON pa.vendor_bill_id = vb.id"
            : '';
        $paidExpr = $paidJoin !== '' ? 'COALESCE(pa.paid, 0)' : '0';
        $openAmountExpr = "({$totalBaseExpr} - {$paidExpr})";

        $payablesQuery = $db->query("
            SELECT currency, SUM(open_amount) AS total, COUNT(*) AS count
            FROM (
                SELECT
                    COALESCE({$currencyExpr}, 'PKR') AS currency,
                    GREATEST(0, {$openAmountExpr}) AS open_amount
                FROM vendor_bills vb
                {$paidJoin}
                WHERE {$statusExpr} NOT IN ('draft', 'cancelled', 'void', 'paid')
            ) open_bills
            WHERE open_amount > 0
            GROUP BY currency
        ");
        $payablesByCurrency = [];
        $billCount = 0;
        if ($payablesQuery) {
            foreach ($payablesQuery->getResultArray() as $row) {
                $payablesByCurrency[$row['currency']] = (float)$row['total'];
                $billCount += (int)$row['count'];
            }
        }

        return $payablesByCurrency;
    }

    private function receivablesByCurrency($db, string $defaultCurrency): array
    {
        try {
            $rows = $db->query(
                "SELECT
                    UPPER(TRIM(COALESCE(NULLIF(ci.currency_code, ''), ?))) AS currency_code,
                    SUM(ci.total_amount) - COALESCE(SUM(payments.paid_amount), 0) AS receivable
                 FROM customer_invoices ci
                 LEFT JOIN (
                    SELECT a.invoice_id, SUM(a.allocated_amount) AS paid_amount
                    FROM customer_payment_allocations a
                    JOIN customer_payments p ON p.id = a.payment_id
                    WHERE p.posted_entry_id IS NOT NULL
                    GROUP BY a.invoice_id
                 ) payments ON payments.invoice_id = ci.id
                 WHERE LOWER(COALESCE(ci.status, '')) NOT IN ('draft', 'paid', 'cancelled', 'void')
                   AND ci.deleted_at IS NULL
                 GROUP BY 1
                 HAVING receivable > 0",
                [$defaultCurrency]
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Corelynk::receivablesByCurrency failed: ' . $e->getMessage());

            return [];
        }

        $totals = [];
        foreach ($rows as $row) {
            $code = strtoupper(trim((string) ($row['currency_code'] ?? '')));
            if ($code === '') {
                $code = $defaultCurrency;
            }
            $totals[$code] = round((float) ($row['receivable'] ?? 0), 2);
        }

        return $totals;
    }
    /**
     * Activity Center feed JSON endpoint for dashboard widget.
     */
    public function activityCenterFeed()
    {
        $this->requireAuth();
        $this->requirePermission('dashboard.read');

        $userId = (int) ($this->currentUser['id'] ?? 0);
        $limit = (int) ($this->request->getGet('limit') ?? 10);

        $data = $this->notificationService->getActivityCenterFeed($userId, $limit);

        return $this->jsonResponse([
            'success' => true,
            'data' => $data,
            'csrf' => [
                'token' => csrf_token(),
                'hash' => csrf_hash(),
            ],
        ]);
    }

    /**
     * Mark a single activity center notification as read.
     */
    public function activityCenterMarkRead($notificationId = null)
    {
        $this->requireAuth();
        $this->requirePermission('dashboard.read');

        if (!$this->request->isAJAX()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid request type.',
            ], 400);
        }

        $notificationId = (int) ($notificationId ?? 0);
        $userId = (int) ($this->currentUser['id'] ?? 0);

        if ($notificationId <= 0 || $userId <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid notification request.',
            ], 422);
        }

        $ok = $this->notificationService->markAsRead($userId, $notificationId);

        return $this->jsonResponse([
            'success' => $ok,
            'csrf' => [
                'token' => csrf_token(),
                'hash' => csrf_hash(),
            ],
        ], $ok ? 200 : 500);
    }

    /**
     * Mark all currently active activity center notifications as read.
     */
    public function activityCenterMarkAllRead()
    {
        $this->requireAuth();
        $this->requirePermission('dashboard.read');

        if (!$this->request->isAJAX()) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid request type.',
            ], 400);
        }

        $userId = (int) ($this->currentUser['id'] ?? 0);
        if ($userId <= 0) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Invalid user context.',
            ], 422);
        }

        $count = $this->notificationService->markAllAsRead($userId);

        return $this->jsonResponse([
            'success' => true,
            'marked_count' => $count,
            'csrf' => [
                'token' => csrf_token(),
                'hash' => csrf_hash(),
            ],
        ]);
    }
}
