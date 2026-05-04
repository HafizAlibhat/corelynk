<?php

namespace App\Controllers;

use App\Libraries\RoleDataAccess;

class Corelynk extends BaseController
{
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
        $defaultSalesCurrency = 'USD';
        $defaultPurchaseCurrency = 'PKR';
        try {
            $company = (new \App\Models\CompanySettingsModel())->first();
            if (!empty($company['default_sales_currency'])) {
                $defaultSalesCurrency = strtoupper((string)$company['default_sales_currency']);
            } elseif (!empty($company['base_currency'])) {
                $defaultSalesCurrency = strtoupper((string)$company['base_currency']);
            }

            if (!empty($company['default_purchase_currency'])) {
                $defaultPurchaseCurrency = strtoupper((string)$company['default_purchase_currency']);
            } elseif (!empty($company['base_currency'])) {
                $defaultPurchaseCurrency = strtoupper((string)$company['base_currency']);
            }
        } catch (\Throwable $_) {
            // Keep conservative defaults if settings are unavailable.
        }
        $now = new \DateTime();
        $startOfMonth = (clone $now)->modify('first day of this month')->setTime(0, 0, 0);
        $endOfMonth = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);
        $prevMonthStart = (clone $startOfMonth)->modify('-1 month');
        $prevMonthEnd = (clone $startOfMonth)->modify('-1 second');
        $historyStart = (clone $startOfMonth)->modify('-5 months');
        $startOfMonthStr = $startOfMonth->format('Y-m-d H:i:s');
        $endOfMonthStr = $endOfMonth->format('Y-m-d H:i:s');
        $prevMonthStartStr = $prevMonthStart->format('Y-m-d H:i:s');
        $prevMonthEndStr = $prevMonthEnd->format('Y-m-d H:i:s');
        $historyStartStr = $historyStart->format('Y-m-d H:i:s');

        try {
            // Vendor payables from vendor bills (real outstanding balances) - grouped by currency.
            $vbCols = array_map('strtolower', $db->getFieldNames('vendor_bills'));
            $has = static function (string $col) use ($vbCols): bool {
                return in_array(strtolower($col), $vbCols, true);
            };

            $currencyExpr = $has('currency_code') ? "vb.currency_code" : "'PKR'";
            $statusExpr = $has('status') ? "LOWER(COALESCE(vb.status, ''))" : "''";
            $balanceExpr = $has('balance') ? "vb.balance" : "NULL";

            $totalCandidates = [];
            if ($has('total_amount')) $totalCandidates[] = 'vb.total_amount';
            if ($has('amount')) $totalCandidates[] = 'vb.amount';
            if ($has('grand_total')) $totalCandidates[] = 'vb.grand_total';
            $totalBaseExpr = !empty($totalCandidates)
                ? 'COALESCE(' . implode(', ', $totalCandidates) . ', 0)'
                : '0';

            $paidExpr = $has('paid_amount') ? 'COALESCE(vb.paid_amount, 0)' : '0';
            $openAmountExpr = "CASE WHEN {$balanceExpr} IS NOT NULL THEN {$balanceExpr} ELSE ({$totalBaseExpr} - {$paidExpr}) END";

            $payablesQuery = $db->query("
                SELECT 
                    COALESCE({$currencyExpr}, 'PKR') AS currency,
                    SUM(
                        GREATEST(
                            0,
                            {$openAmountExpr}
                        )
                    ) AS total,
                    COUNT(*) AS count
                FROM vendor_bills vb
                WHERE {$statusExpr} NOT IN ('draft', 'cancelled', 'void', 'paid')
                  AND GREATEST(
                        0,
                        {$openAmountExpr}
                      ) > 0
                GROUP BY {$currencyExpr}
            ");
            $payablesByCurrency = [];
            $totalBillsCount = 0;
            if ($payablesQuery) {
                foreach ($payablesQuery->getResultArray() as $row) {
                    $payablesByCurrency[$row['currency']] = (float)$row['total'];
                    $totalBillsCount += (int)$row['count'];
                }
            }
            $data['payables_by_currency'] = $payablesByCurrency;
            $data['total_bills_count'] = $totalBillsCount;
        } catch (\Throwable $e) {
            $data['payables_by_currency'] = [];
            $data['total_bills_count'] = 0;
        }

        try {
            // Open purchase orders + value (kept for reference/future use)
            $row = $db->query("SELECT COUNT(*) AS cnt, SUM(IFNULL(total,0)) AS total FROM purchase_orders WHERE status IS NULL OR status NOT IN ('received','closed')")->getRowArray();
            $data['open_pos'] = $row ? (int)$row['cnt'] : 0;
            $data['open_pos_value'] = $row ? (float)$row['total'] : 0.0;
        } catch (\Throwable $e) {
            $data['open_pos'] = 0;
            $data['open_pos_value'] = 0.0;
        }

        try {
            // Pending work orders
            $row = $db->query("SELECT COUNT(*) AS cnt FROM work_orders WHERE status IS NULL OR status NOT IN ('done','closed')")->getRow();
            $data['open_wos'] = $row ? (int)$row->cnt : 0;
        } catch (\Throwable $e) { $data['open_wos'] = 0; }

        try {
            // Stock value (products.current_stock * unit_cost)
            $row = $db->query("SELECT SUM(IFNULL(current_stock,0) * IFNULL(unit_cost,0)) AS total FROM products")->getRow();
            $data['stock_value'] = $row && $row->total ? (float)$row->total : 0.0;
        } catch (\Throwable $e) { $data['stock_value'] = 0.0; }

        try {
            // Recent work order activity (include product code)
            $rows = $db->query("SELECT wo.id, wo.wo_number, wo.status, wo.created_at, wo.customer_name, p.code AS product_code FROM work_orders wo LEFT JOIN products p ON p.id = wo.product_id ORDER BY wo.id DESC LIMIT 6")->getResultArray();
            $data['recent_activity'] = $rows ?: [];
        } catch (\Throwable $e) { $data['recent_activity'] = []; }

        try {
            // Trending products (real data)
            $products = $db->query("SELECT code, name, current_stock, unit_cost FROM products WHERE is_active=1 ORDER BY current_stock ASC, id DESC LIMIT 6")->getResultArray();
            $data['trending_products'] = $products ?: [];
        } catch (\Throwable $e) { $data['trending_products'] = []; }

        try {
            // Sales this month
            $salesRow = $db->table('sales_cache')->select('SUM(IFNULL(amount_total,0)) AS total')->where('date_order >=', $startOfMonthStr)->where('date_order <=', $endOfMonthStr)->get()->getRowArray();
            $totalSalesMtd = $salesRow ? (float)$salesRow['total'] : 0.0;
        } catch (\Throwable $e) {
            $totalSalesMtd = 0.0;
        }

        try {
            $salesPrev = $db->table('sales_cache')->select('SUM(IFNULL(amount_total,0)) AS total')->where('date_order >=', $prevMonthStartStr)->where('date_order <=', $prevMonthEndStr)->get()->getRowArray();
            $totalSalesLastMonth = $salesPrev ? (float)$salesPrev['total'] : 0.0;
        } catch (\Throwable $e) {
            $totalSalesLastMonth = 0.0;
        }

        try {
            $paymentsRow = $db->table('vendor_payments')
                ->select('SUM(IFNULL(amount,0)) AS total')
                ->where('payment_date >=', $startOfMonth->format('Y-m-d'))
                ->where('payment_date <=', $endOfMonth->format('Y-m-d'))
                ->groupStart()
                    ->where("LOWER(COALESCE(status,'')) = 'posted'", null, false)
                    ->orWhere('posted_entry_id IS NOT NULL', null, false)
                ->groupEnd()
                ->get()->getRowArray();
            $vendorPaymentsMtd = $paymentsRow ? (float)$paymentsRow['total'] : 0.0;
        } catch (\Throwable $e) {
            $vendorPaymentsMtd = 0.0;
        }

        try {
            // Customer receivables from customer invoices (unpaid/partially paid)
            // Since there's no balance column, we calculate from payments
            // Calculate receivables grouped by invoice currency.
            // Payments are allocated via `customer_payment_allocations` (payment_id -> invoice_id)
            $receivablesQuery = $db->query(" 
                SELECT
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
                WHERE ci.status NOT IN ('paid', 'cancelled')
                    AND ci.deleted_at IS NULL
                GROUP BY UPPER(TRIM(COALESCE(NULLIF(ci.currency_code, ''), ?)))
                HAVING receivable > 0
            ", [$defaultSalesCurrency, $defaultSalesCurrency]);
            
            $receivablesByCurrency = [];
            $totalReceivablesCount = 0;
            if ($receivablesQuery) {
                foreach ($receivablesQuery->getResultArray() as $row) {
                    $currencyCode = strtoupper(trim((string)($row['currency_code'] ?? '')));
                    if ($currencyCode === '') {
                        $currencyCode = $defaultSalesCurrency;
                    }
                    $receivablesByCurrency[$currencyCode] = (float)$row['receivable'];
                    $totalReceivablesCount++;
                }
            }
            
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
        
        $cards = [];
        if (!empty($dataAccess['dashboard_sales_visible'])) {
            $cards[] = [
                'label' => 'Total Sales (MTD)',
                'value' => '$' . number_format($totalSalesMtd, 2),
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
                'label' => 'Profit (MTD)',
                'value' => '$' . number_format(max(0, $totalSalesMtd - $vendorPaymentsMtd), 2),
                'hint' => 'Payments: $' . number_format($vendorPaymentsMtd, 2),
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

        // Chart series preparation
        $monthKeys = [];
        $monthLabels = [];
        $cursor = clone $historyStart;
        for ($i = 0; $i < 6; $i++) {
            $key = $cursor->format('Y-m');
            $monthKeys[] = $key;
            $monthLabels[] = $cursor->format('M Y');
            $cursor->modify('+1 month');
        }
        $salesTimeline = array_fill_keys($monthKeys, 0.0);
        $paymentsTimeline = array_fill_keys($monthKeys, 0.0);
        $purchasesTimeline = array_fill_keys($monthKeys, 0.0);

        try {
            $salesAgg = $db->table('sales_cache')->select("DATE_FORMAT(date_order, '%Y-%m') AS month, SUM(IFNULL(amount_total,0)) AS total")->where('date_order >=', $historyStartStr)->groupBy('month')->get()->getResultArray();
            foreach ($salesAgg as $row) {
                if (isset($salesTimeline[$row['month']])) {
                    $salesTimeline[$row['month']] = (float)$row['total'];
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $paymentsAgg = $db->table('vendor_payments')->select("DATE_FORMAT(payment_date, '%Y-%m') AS month, SUM(IFNULL(amount,0)) AS total")->where('payment_date >=', $historyStart->format('Y-m-01'))->groupBy('month')->get()->getResultArray();
            foreach ($paymentsAgg as $row) {
                if (isset($paymentsTimeline[$row['month']])) {
                    $paymentsTimeline[$row['month']] = (float)$row['total'];
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        try {
            $purchaseAgg = $db->table('purchase_orders')->select("DATE_FORMAT(order_date, '%Y-%m') AS month, SUM(IFNULL(total,0)) AS total")->where('order_date >=', $historyStartStr)->groupBy('month')->get()->getResultArray();
            foreach ($purchaseAgg as $row) {
                if (isset($purchasesTimeline[$row['month']])) {
                    $purchasesTimeline[$row['month']] = (float)$row['total'];
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        $data['chart_labels'] = $monthLabels;
        $data['sales_timeline'] = array_values($salesTimeline);
        $data['payments_timeline'] = array_values($paymentsTimeline);
        $data['purchase_timeline'] = array_values($purchasesTimeline);

        try {
            $posForecast = $db->query("SELECT po.id, po.order_date, COALESCE(v.name, 'Vendor') AS vendor_name FROM purchase_orders po LEFT JOIN vendors v ON v.id = po.vendor_id WHERE po.status IS NULL OR po.status NOT IN ('received','closed') ORDER BY po.order_date ASC LIMIT 4")->getResultArray();
            $data['upcoming_pos'] = $posForecast ?: [];
        } catch (\Throwable $e) { $data['upcoming_pos'] = []; }

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
}
