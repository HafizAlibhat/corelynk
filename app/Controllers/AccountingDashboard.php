<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;
use App\Libraries\ExchangeRates;

class AccountingDashboard extends Controller
{
    protected $db;

    public function __construct()
    {
    $this->db = Database::connect();
    }

    public function index()
    {
        try {
            // Resolve currency: prefer explicit GET param, then company settings, then default to PKR
            $currencyParam = $this->request->getGet('currency');
            if (!empty($currencyParam)) {
                $currency = strtoupper($currencyParam);
            } else {
                // Try company settings
                try {
                    $companySettings = (new \App\Models\CompanySettingsModel())->first();
                    $currency = strtoupper($companySettings['currency_code'] ?? 'PKR');
                } catch (\Throwable $e) {
                    $currency = 'PKR';
                }
            }
            $data = $this->getDashboardData($currency);
            // Fetch live FX rates (1 USD/EUR/GBP -> PKR) for display in the dashboard
            try {
                $fx = ExchangeRates::getRates(['USD','EUR','GBP'], 300);
            } catch (\Throwable $e) {
                $fx = [];
            }
            $data['fx_rates'] = $fx;
            $data['selected_currency'] = $currency;
            return view('accounting/dashboard', $data);
        } catch (\Exception $e) {
            log_message('error', 'Dashboard error: ' . $e->getMessage());
            $currency = strtoupper($this->request->getGet('currency') ?? 'PKR');
            return view('accounting/dashboard', [
                'error' => 'Unable to load dashboard data: ' . $e->getMessage(),
                'selected_currency' => $currency,
                'total_revenue' => 0,
                'total_expenses' => 0,
                'cash_position_value' => 0,
                'ie_income' => [],
                'ie_expenses' => [],
                'ap_data' => [],
                'ar_data' => [],
                'recent_activity' => [],
                'sales_by_product' => [],
                'vendor_expenses' => [],
                'monthly_income' => [],
                'monthly_expenses' => [],
                'top_customers_labels' => [],
                'top_customers_data' => [],
                'ytd_revenue' => 0,
                'ytd_expenses' => 0,
            ]);
        }

    }

    private function getDashboardData($currency = 'PKR') {
        // Ensure DB available BEFORE issuing queries (bug fix for undefined $db causing zero totals)
        $db = $this->db;
        $isUSD = ($currency === 'USD');

    // Monthly Income vs Expenses (last 6 months) dynamic labels.
    // Stored debit/credit are ALWAYS PKR. base_amount holds original foreign currency amount.
    $monthly_income = [];
    $monthly_expenses = [];
    $months_labels = [];
    if ($currency === 'USD') {
        $monthlyQ = "SELECT DATE_FORMAT(je.entry_date, '%b') as month,
        SUM(CASE WHEN a.type='Revenue' THEN CASE 
            WHEN jl.currency_code='USD' THEN jl.base_amount 
            WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.credit / jl.fx_rate 
            ELSE 0 END ELSE 0 END) as income,
        SUM(CASE WHEN a.type='Expense' THEN CASE 
            WHEN jl.currency_code='USD' THEN jl.base_amount 
            WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.debit / jl.fx_rate 
            ELSE 0 END ELSE 0 END) as expenses
        FROM journal_entries je
        JOIN journal_lines jl ON je.id = jl.entry_id
        JOIN accounts a ON jl.account_id = a.id
        WHERE je.entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(je.entry_date), MONTH(je.entry_date)
        ORDER BY je.entry_date ASC";
    } else {
        $monthlyQ = "SELECT DATE_FORMAT(je.entry_date, '%b') as month,
        SUM(CASE WHEN a.type='Revenue' THEN jl.credit ELSE 0 END) as income,
        SUM(CASE WHEN a.type='Expense' THEN jl.debit ELSE 0 END) as expenses
        FROM journal_entries je
        JOIN journal_lines jl ON je.id = jl.entry_id
        JOIN accounts a ON jl.account_id = a.id
        WHERE je.entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY YEAR(je.entry_date), MONTH(je.entry_date)
        ORDER BY je.entry_date ASC";
    }
        try {
            $monthlyRows = $db->query($monthlyQ)->getResultArray();
            foreach ($monthlyRows as $row) {
                $months_labels[] = $row['month'];
                $monthly_income[] = (float)($row['income'] ?? 0);
                $monthly_expenses[] = (float)($row['expenses'] ?? 0);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Monthly query failed: ' . $e->getMessage());
        }
        if ($currency === 'USD') {
            // USD view: translate PKR base to USD; use original base_amount for USD lines.
            $revenueQ = "SELECT SUM(CASE 
                    WHEN jl.currency_code='USD' THEN jl.base_amount
                    WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.credit / jl.fx_rate
                    ELSE 0 END) as revenue
                FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Revenue'";
            $revenue = $db->query($revenueQ)->getRow('revenue') ?? 0;
            $expenseQ = "SELECT SUM(CASE 
                    WHEN jl.currency_code='USD' THEN jl.base_amount
                    WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.debit / jl.fx_rate
                    ELSE 0 END) as expenses
                FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Expense'";
            $expenses = $db->query($expenseQ)->getRow('expenses') ?? 0;
            // Cash position (USD view): translate only Cash/Bank accounts
            $cashQ = "SELECT SUM(CASE 
                    WHEN jl.currency_code='USD' THEN jl.base_amount
                    WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN (jl.debit - jl.credit)/ jl.fx_rate
                    ELSE 0 END) as balance
                FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE (a.name LIKE '%cash%' OR a.name LIKE '%bank%')";
            $cash_position = $db->query($cashQ)->getRow('balance') ?? 0;
            // Recent Activity: show both original and converted values
            $recentQ = "SELECT je.id, je.entry_date, je.memo,
                SUM(CASE 
                    WHEN jl.currency_code='USD' THEN jl.base_amount
                    WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN (jl.debit - jl.credit)/ jl.fx_rate
                    ELSE 0 END) as total_amount,
                GROUP_CONCAT(jl.debit) as debits, GROUP_CONCAT(jl.credit) as credits, GROUP_CONCAT(jl.currency_code) as currencies, GROUP_CONCAT(jl.fx_rate) as fx_rates,
                COUNT(jl.id) as line_count
                FROM journal_entries je LEFT JOIN journal_lines jl ON je.id=jl.entry_id
                GROUP BY je.id ORDER BY je.entry_date DESC, je.id DESC LIMIT 5";
            $recent_activity = $db->query($recentQ)->getResultArray();
        } else {
            // PKR view: stored PKR amounts used directly (no further conversion)
            $revenueQ = "SELECT SUM(jl.credit) as revenue FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Revenue'";
            $revenue = $db->query($revenueQ)->getRow('revenue') ?? 0;
            $expenseQ = "SELECT SUM(jl.debit) as expenses FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Expense'";
            $expenses = $db->query($expenseQ)->getRow('expenses') ?? 0;
            // Cash position should reflect only Cash/Bank accounts, not all assets
            $cashQ = "SELECT SUM(jl.debit - jl.credit) as balance FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE (a.name LIKE '%cash%' OR a.name LIKE '%bank%')";
            $cash_position = $db->query($cashQ)->getRow('balance') ?? 0;
            // Recent Activity: show both original and converted values
            $recentQ = "SELECT je.id, je.entry_date, je.memo,
                SUM(jl.debit - jl.credit) as total_amount,
                GROUP_CONCAT(jl.debit) as debits, GROUP_CONCAT(jl.credit) as credits, GROUP_CONCAT(jl.currency_code) as currencies, GROUP_CONCAT(jl.fx_rate) as fx_rates,
                COUNT(jl.id) as line_count
                FROM journal_entries je LEFT JOIN journal_lines jl ON je.id=jl.entry_id
                GROUP BY je.id ORDER BY je.entry_date DESC, je.id DESC LIMIT 5";
            $recent_activity = $db->query($recentQ)->getResultArray();
        }
        // Income vs Expenses (alternate aggregate used by some charts)
        if ($currency === 'USD') {
            $ieQ = "SELECT DATE_FORMAT(je.entry_date, '%b') as month,
                SUM(CASE WHEN a.type='Revenue' THEN CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.credit / jl.fx_rate ELSE 0 END ELSE 0 END) as income,
                SUM(CASE WHEN a.type='Expense' THEN CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.debit / jl.fx_rate ELSE 0 END ELSE 0 END) as expenses
                FROM journal_entries je JOIN journal_lines jl ON je.id=jl.entry_id JOIN accounts a ON jl.account_id=a.id
                WHERE je.entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY YEAR(je.entry_date), MONTH(je.entry_date) ORDER BY je.entry_date ASC";
            $ieRows = $db->query($ieQ)->getResultArray();
        } else {
            $ieQ = "SELECT DATE_FORMAT(je.entry_date, '%b') as month,
                SUM(CASE WHEN a.type='Revenue' THEN jl.credit ELSE 0 END) as income,
                SUM(CASE WHEN a.type='Expense' THEN jl.debit ELSE 0 END) as expenses
                FROM journal_entries je JOIN journal_lines jl ON je.id=jl.entry_id JOIN accounts a ON jl.account_id=a.id
                WHERE je.entry_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY YEAR(je.entry_date), MONTH(je.entry_date) ORDER BY je.entry_date ASC";
            $ieRows = $db->query($ieQ)->getResultArray();
        }
        $ie_income = array_column($ieRows, 'income');
        $ie_expenses = array_column($ieRows, 'expenses');
        // AP/AR (translate similar to totals)
        if ($currency === 'USD') {
            // AR/AP (USD view) restricted to receivable/payable-named accounts only
            $apQ = "SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.credit / jl.fx_rate ELSE 0 END) as ap FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Liability' AND (a.name LIKE '%payable%' OR a.name LIKE '%supplier%')";
            $arQ = "SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.debit / jl.fx_rate ELSE 0 END) as ar FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Asset' AND (a.name LIKE '%receivable%' OR a.name LIKE '%customer%')";
            $ap = $db->query($apQ)->getRow('ap') ?? 0;
            $ar = $db->query($arQ)->getRow('ar') ?? 0;
        } else {
            // AR/AP should not include all assets/liabilities; restrict by name to avoid counting cash
            $apQ = "SELECT SUM(jl.credit) as ap FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Liability' AND (a.name LIKE '%payable%' OR a.name LIKE '%supplier%')";
            $arQ = "SELECT SUM(jl.debit) as ar FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id WHERE a.type='Asset' AND (a.name LIKE '%receivable%' OR a.name LIKE '%customer%')";
            $ap = $db->query($apQ)->getRow('ap') ?? 0;
            $ar = $db->query($arQ)->getRow('ar') ?? 0;
        }
        // Sales by Product, Vendor Expenses, Top Customers: not available, fallback to empty
        $salesRows = [];
        $vendorRows = [];
        $top_customers_labels = [];
        $top_customers_data = [];
        // YTD Revenue/Expenses translated
        if ($currency === 'USD') {
            $ytdRevQ = "SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.credit / jl.fx_rate ELSE 0 END) as ytd_revenue FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id JOIN journal_entries je ON jl.entry_id=je.id WHERE a.type='Revenue' AND je.entry_date >= DATE_FORMAT(NOW(), '%Y-01-01')";
            $ytdExpQ = "SELECT SUM(CASE WHEN jl.currency_code='USD' THEN jl.base_amount WHEN jl.currency_code='PKR' AND jl.fx_rate>0 THEN jl.debit / jl.fx_rate ELSE 0 END) as ytd_expenses FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id JOIN journal_entries je ON jl.entry_id=je.id WHERE a.type='Expense' AND je.entry_date >= DATE_FORMAT(NOW(), '%Y-01-01')";
            $ytd_revenue = $db->query($ytdRevQ)->getRow('ytd_revenue') ?? 0;
            $ytd_expenses = $db->query($ytdExpQ)->getRow('ytd_expenses') ?? 0;
        } else {
            $ytdRevQ = "SELECT SUM(jl.credit) as ytd_revenue FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id JOIN journal_entries je ON jl.entry_id=je.id WHERE a.type='Revenue' AND je.entry_date >= DATE_FORMAT(NOW(), '%Y-01-01')";
            $ytdExpQ = "SELECT SUM(jl.debit) as ytd_expenses FROM journal_lines jl JOIN accounts a ON jl.account_id=a.id JOIN journal_entries je ON jl.entry_id=je.id WHERE a.type='Expense' AND je.entry_date >= DATE_FORMAT(NOW(), '%Y-01-01')";
            $ytd_revenue = $db->query($ytdRevQ)->getRow('ytd_revenue') ?? 0;
            $ytd_expenses = $db->query($ytdExpQ)->getRow('ytd_expenses') ?? 0;
        }
        // Compose data for view (add dynamic month labels)
        // Diagnostics: basic reconciliation checks
        $diagnostics = [];
        try {
            $journalTotals = $db->query('SELECT SUM(total_debits) td, SUM(total_credits) tc FROM journal_entries')->getRowArray();
            $diagnostics['journal_entry_sum_debits'] = (float)($journalTotals['td'] ?? 0);
            $diagnostics['journal_entry_sum_credits'] = (float)($journalTotals['tc'] ?? 0);
            $diagnostics['revenue_calc'] = (float)$revenue;
            $diagnostics['expenses_calc'] = (float)$expenses;
            $diagnostics['currency_view'] = $currency;
            $diagnostics['revenue_vs_entries_delta'] = $diagnostics['revenue_calc'] - $diagnostics['journal_entry_sum_credits'];
        } catch (\Throwable $e) {
            $diagnostics['error'] = 'Diagnostics failed: '.$e->getMessage();
        }
        return [
            'total_revenue' => $revenue,
            'total_expenses' => $expenses,
            'cash_position_value' => $cash_position,
            'ie_income' => $monthly_income,
            'ie_expenses' => $monthly_expenses,
            'ap_data' => [$ap, $ar],
            'ar_data' => [$ap, $ar], // For chart.js compatibility
            'recent_activity' => $recent_activity,
            'sales_by_product' => $salesRows,
            'vendor_expenses' => $vendorRows,
            'monthly_income' => $monthly_income,
            'monthly_expenses' => $monthly_expenses,
            'top_customers_labels' => $top_customers_labels,
            'top_customers_data' => $top_customers_data,
            'ytd_revenue' => $ytd_revenue,
            'ytd_expenses' => $ytd_expenses,
            'months_labels' => $months_labels,
            'diagnostics' => $diagnostics,
        ];
    }

}
