<?php
namespace App\Controllers;

use Config\Database;

class TrialBalanceNew extends BaseController
{
    public function index()
    {
        $db = Database::connect();
        $sql = "
            SELECT 
                a.id,
                a.code, 
                a.name, 
                a.type,
                COALESCE(SUM(jl.debit), 0) as total_debits,
                COALESCE(SUM(jl.credit), 0) as total_credits,
                CASE 
                    WHEN a.type IN ('Asset', 'Expense') THEN COALESCE(SUM(jl.debit), 0) - COALESCE(SUM(jl.credit), 0)
                    ELSE COALESCE(SUM(jl.credit), 0) - COALESCE(SUM(jl.debit), 0)
                END as balance
            FROM accounts a
            LEFT JOIN journal_lines jl ON a.id = jl.account_id
            GROUP BY a.id, a.code, a.name, a.type
            HAVING (COALESCE(SUM(jl.debit), 0) + COALESCE(SUM(jl.credit), 0)) > 0
            ORDER BY a.type, a.code
        ";
        $accounts = $db->query($sql)->getResultArray();

        // Group by type
        $trialBalance = [];
        $totals = ['debit' => 0, 'credit' => 0];
        foreach ($accounts as $account) {
            $type = $account['type'];
            if (!isset($trialBalance[$type])) $trialBalance[$type] = [];
            $debitBalance = 0;
            $creditBalance = 0;
            if (in_array($type, ['Asset', 'Expense']) && $account['balance'] > 0) {
                $debitBalance = $account['balance'];
                $totals['debit'] += $debitBalance;
            } elseif (in_array($type, ['Liability', 'Equity', 'Revenue']) && $account['balance'] > 0) {
                $creditBalance = $account['balance'];
                $totals['credit'] += $creditBalance;
            }
            $account['debit_balance'] = $debitBalance;
            $account['credit_balance'] = $creditBalance;
            $trialBalance[$type][] = $account;
        }
        $stats = [
            'total_accounts' => count($accounts),
            'balanced' => abs($totals['debit'] - $totals['credit']) < 0.01
        ];
        return view('trial_balance_new', [
            'trialBalance' => $trialBalance,
            'totals' => $totals,
            'stats' => $stats
        ]);
    }
}
