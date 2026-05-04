<?php

namespace App\Controllers;

use Config\Database;
use App\Services\AccountingAuditor;

class TrialBalance extends BaseController
{
    public function index()
    {
        $db = Database::connect();
        
        // Get trial balance data
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
        
        try {
            $accounts = $db->query($sql)->getResultArray();
            
            // Group by account type
            $trialBalance = [];
            $totals = ['debit' => 0, 'credit' => 0];
            
            foreach ($accounts as $account) {
                $type = $account['type'];
                if (!isset($trialBalance[$type])) {
                    $trialBalance[$type] = [];
                }
                
                // Calculate debit/credit for trial balance display
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
            
            // Get summary stats
            $stats = [
                'total_accounts' => count($accounts),
                'total_entries' => (int)$db->query('SELECT COUNT(*) as c FROM journal_entries')->getRowArray()['c'],
                'total_lines' => (int)$db->query('SELECT COUNT(*) as c FROM journal_lines')->getRowArray()['c'],
                'balanced' => abs($totals['debit'] - $totals['credit']) < 0.01
            ];
            
        } catch (\Throwable $e) {
            $trialBalance = [];
            $totals = ['debit' => 0, 'credit' => 0];
            $stats = ['error' => $e->getMessage()];
            $auditFindings = [];
        }
        
        // Run intelligent audit analysis
        if (!empty($trialBalance)) {
            $auditor = new AccountingAuditor();
            $auditFindings = $auditor->auditTrialBalance($trialBalance, $totals, $stats);
        } else {
            $auditFindings = [];
        }
        
        return view('trial_balance', [
            'trialBalance' => $trialBalance,
            'totals' => $totals,
            'stats' => $stats,
            'auditFindings' => $auditFindings
        ]);
    }
}