<?php

namespace App\Services;

use CodeIgniter\Database\ConnectionInterface;

/**
 * Accounting Auditor Service
 * 
 * Provides intelligent analysis of accounting data to detect:
 * - Data entry errors
 * - Posting mistakes
 * - Missing contra entries
 * - Unusual patterns
 * - Compliance issues
 */
class AccountingAuditor
{
    protected $db;
    
    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }
    
    /**
     * Perform comprehensive audit on trial balance
     * 
     * @param array $trialBalance Trial balance data by account type
     * @param array $totals Debit and credit totals
     * @param array $stats Balance statistics
     * @return array Audit findings with severity, description, and suggestions
     */
    public function auditTrialBalance(array $trialBalance, array $totals, array $stats): array
    {
        $findings = [];
        
        // Critical: Books out of balance
        if (!($stats['balanced'] ?? false)) {
            $difference = abs($totals['debit'] - $totals['credit']);
            $findings[] = [
                'severity' => 'critical',
                'category' => 'Balance Mismatch',
                'title' => 'Trial Balance Not Balanced',
                'description' => sprintf('Debit and Credit totals differ by PKR %s', number_format($difference, 2)),
                'impact' => 'Financial statements cannot be trusted until this is resolved',
                'suggestions' => $this->suggestBalanceFixes($difference, $totals),
                'accounts' => []
            ];
        }
        
        // Check for accounts with zero balance (potential errors)
        foreach ($trialBalance as $type => $accounts) {
            foreach ($accounts as $account) {
                $debit = (float)($account['debit_balance'] ?? 0);
                $credit = (float)($account['credit_balance'] ?? 0);
                
                // Zero balance accounts
                if ($debit == 0 && $credit == 0) {
                    $findings[] = [
                        'severity' => 'info',
                        'category' => 'Inactive Account',
                        'title' => 'Account with No Activity',
                        'description' => sprintf('%s (%s) has zero balance', $account['name'], $account['code']),
                        'impact' => 'May indicate unused account that should be archived',
                        'suggestions' => [
                            'Review if this account is still needed',
                            'Consider marking as inactive if not in use',
                            'Verify no missing transactions'
                        ],
                        'accounts' => [$account['code']]
                    ];
                }
                
                // Unusual: Both debit and credit balances
                if ($debit > 0 && $credit > 0) {
                    $findings[] = [
                        'severity' => 'warning',
                        'category' => 'Dual Balance',
                        'title' => 'Account Has Both Debit and Credit Balances',
                        'description' => sprintf('%s (%s): Dr %.2f, Cr %.2f', $account['name'], $account['code'], $debit, $credit),
                        'impact' => 'Unusual pattern - most accounts should have balance on one side only',
                        'suggestions' => [
                            'Review all transactions for this account',
                            'Check if entries were posted to wrong side',
                            'Verify if offsetting entries are missing',
                            'This may be normal for control accounts'
                        ],
                        'accounts' => [$account['code']]
                    ];
                }
            }
        }
        
        // Check for missing contra entries
        $contraIssues = $this->detectMissingContraEntries();
        $findings = array_merge($findings, $contraIssues);
        
        // Check for unusual amounts (round numbers, duplicates)
        $patternIssues = $this->detectUnusualPatterns();
        $findings = array_merge($findings, $patternIssues);
        
        // Check account type logic
        $typeIssues = $this->validateAccountTypeBalances($trialBalance);
        $findings = array_merge($findings, $typeIssues);
        
        // Check for unposted or draft entries
        $draftIssues = $this->checkUnpostedEntries();
        $findings = array_merge($findings, $draftIssues);
        
        // Sort by severity
        usort($findings, function($a, $b) {
            $severity_order = ['critical' => 1, 'error' => 2, 'warning' => 3, 'info' => 4];
            return ($severity_order[$a['severity']] ?? 5) - ($severity_order[$b['severity']] ?? 5);
        });
        
        return $findings;
    }
    
    /**
     * Suggest fixes for balance mismatches
     */
    protected function suggestBalanceFixes(float $difference, array $totals): array
    {
        $suggestions = [
            'Check for transposition errors (e.g., 54 entered as 45)',
            'Look for entries with missing contra accounts',
            'Verify all journal entries have balanced debits and credits',
            'Search for amount equal to half the difference (may be on wrong side)'
        ];
        
        // Check if difference is divisible by 9 (transposition error indicator)
        if ($difference > 0 && fmod($difference, 9) == 0) {
            array_unshift($suggestions, '⚠️ LIKELY TRANSPOSITION ERROR: Difference is divisible by 9');
        }
        
        // Check if difference matches any recent entry amounts
        $builder = $this->db->table('journal_lines');
        $recentMatches = $builder
            ->select('jl.debit, jl.credit, je.entry_number, je.entry_date, je.description')
            ->join('journal_entries je', 'je.id = jl.journal_entry_id')
            ->groupStart()
                ->where('ABS(jl.debit - ' . $difference . ') < 0.01')
                ->orWhere('ABS(jl.credit - ' . $difference . ') < 0.01')
            ->groupEnd()
            ->orderBy('je.entry_date', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();
        
        if (!empty($recentMatches)) {
            $suggestions[] = sprintf(
                '💡 Found %d recent entries with matching amount - check Entry #%s dated %s',
                count($recentMatches),
                $recentMatches[0]['entry_number'] ?? 'N/A',
                $recentMatches[0]['entry_date'] ?? 'N/A'
            );
        }
        
        return $suggestions;
    }
    
    /**
     * Detect missing contra entries (unbalanced journal entries)
     */
    protected function detectMissingContraEntries(): array
    {
        $findings = [];
        
        $query = "
            SELECT 
                je.id,
                je.entry_number,
                je.entry_date,
                je.description,
                SUM(CASE WHEN jl.type = 'debit' THEN jl.amount ELSE 0 END) as total_debit,
                SUM(CASE WHEN jl.type = 'credit' THEN jl.amount ELSE 0 END) as total_credit,
                COUNT(jl.id) as line_count
            FROM journal_entries je
            LEFT JOIN journal_lines jl ON jl.journal_entry_id = je.id
            GROUP BY je.id, je.entry_number, je.entry_date, je.description
            HAVING ABS(total_debit - total_credit) > 0.01
            ORDER BY je.entry_date DESC
            LIMIT 10
        ";
        
        $unbalancedEntries = $this->db->query($query)->getResultArray();
        
        foreach ($unbalancedEntries as $entry) {
            $difference = abs($entry['total_debit'] - $entry['total_credit']);
            $findings[] = [
                'severity' => 'error',
                'category' => 'Unbalanced Entry',
                'title' => sprintf('Journal Entry #%s Not Balanced', $entry['entry_number']),
                'description' => sprintf(
                    'Entry dated %s: Dr %.2f vs Cr %.2f (Difference: %.2f)',
                    $entry['entry_date'],
                    $entry['total_debit'],
                    $entry['total_credit'],
                    $difference
                ),
                'impact' => 'This entry is causing the trial balance to be out of balance',
                'suggestions' => [
                    'Review the journal entry and add missing contra account',
                    'Verify all line items have correct amounts',
                    'Check if any lines were accidentally deleted',
                    sprintf('Entry has %d line(s) - most entries need at least 2', $entry['line_count'])
                ],
                'accounts' => [],
                'entry_id' => $entry['id'],
                'entry_number' => $entry['entry_number']
            ];
        }
        
        return $findings;
    }
    
    /**
     * Detect unusual patterns that might indicate errors
     */
    protected function detectUnusualPatterns(): array
    {
        $findings = [];
        
        // Find duplicate amounts on same date (possible double posting)
        $query = "
            SELECT 
                GREATEST(jl1.debit, jl1.credit) as amount,
                je1.entry_date,
                COUNT(*) as occurrence_count,
                GROUP_CONCAT(DISTINCT je1.entry_number SEPARATOR ', ') as entry_numbers,
                GROUP_CONCAT(DISTINCT a.name SEPARATOR ', ') as account_names
            FROM journal_lines jl1
            JOIN journal_entries je1 ON je1.id = jl1.journal_entry_id
            JOIN accounts a ON a.id = jl1.account_id
            WHERE GREATEST(jl1.debit, jl1.credit) > 100
            GROUP BY amount, je1.entry_date
            HAVING COUNT(*) > 2
            ORDER BY occurrence_count DESC
            LIMIT 5
        ";
        
        $duplicates = $this->db->query($query)->getResultArray();
        
        foreach ($duplicates as $dup) {
            $findings[] = [
                'severity' => 'warning',
                'category' => 'Duplicate Amount',
                'title' => 'Suspicious Duplicate Entries',
                'description' => sprintf(
                    'Amount %.2f appears %d times on %s',
                    $dup['amount'],
                    $dup['occurrence_count'],
                    $dup['entry_date']
                ),
                'impact' => 'Possible double-posting or copy-paste error',
                'suggestions' => [
                    'Review entries: ' . $dup['entry_numbers'],
                    'Verify these are legitimate separate transactions',
                    'Check if one entry should be reversed',
                    'Ensure supporting documents exist for each entry'
                ],
                'accounts' => []
            ];
        }
        
        // Find suspiciously round numbers (may indicate estimates)
        $query = "
            SELECT 
                GREATEST(jl.debit, jl.credit) as amount,
                je.entry_number,
                je.entry_date,
                je.description,
                a.code,
                a.name
            FROM journal_lines jl
            JOIN journal_entries je ON je.id = jl.journal_entry_id
            JOIN accounts a ON a.id = jl.account_id
            WHERE GREATEST(jl.debit, jl.credit) IN (1000, 5000, 10000, 50000, 100000, 500000, 1000000)
            AND je.entry_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY amount DESC
            LIMIT 5
        ";
        
        $roundNumbers = $this->db->query($query)->getResultArray();
        
        if (!empty($roundNumbers)) {
            $findings[] = [
                'severity' => 'info',
                'category' => 'Round Numbers',
                'title' => 'Suspiciously Round Amounts Detected',
                'description' => sprintf('Found %d entries with perfectly round amounts', count($roundNumbers)),
                'impact' => 'May indicate estimated or placeholder amounts',
                'suggestions' => [
                    'Verify these are actual transaction amounts, not estimates',
                    'Replace estimates with exact amounts when available',
                    'Ensure proper supporting documentation exists',
                    'Review: ' . implode(', ', array_column($roundNumbers, 'entry_number'))
                ],
                'accounts' => array_unique(array_column($roundNumbers, 'code'))
            ];
        }
        
        return $findings;
    }
    
    /**
     * Validate account type balances follow accounting logic
     */
    protected function validateAccountTypeBalances(array $trialBalance): array
    {
        $findings = [];
        
        foreach ($trialBalance as $type => $accounts) {
            foreach ($accounts as $account) {
                $debit = (float)($account['debit_balance'] ?? 0);
                $credit = (float)($account['credit_balance'] ?? 0);
                
                $incorrectSide = false;
                $expectedSide = '';
                
                // Assets should normally have debit balances
                if ($type === 'Asset' && $credit > $debit) {
                    $incorrectSide = true;
                    $expectedSide = 'debit';
                }
                
                // Liabilities and Equity should have credit balances
                if (($type === 'Liability' || $type === 'Equity') && $debit > $credit) {
                    $incorrectSide = true;
                    $expectedSide = 'credit';
                }
                
                // Revenue should have credit balance
                if ($type === 'Revenue' && $debit > $credit) {
                    $incorrectSide = true;
                    $expectedSide = 'credit';
                }
                
                // Expenses should have debit balance
                if ($type === 'Expense' && $credit > $debit) {
                    $incorrectSide = true;
                    $expectedSide = 'credit';
                }
                
                if ($incorrectSide) {
                    $findings[] = [
                        'severity' => 'warning',
                        'category' => 'Incorrect Side',
                        'title' => sprintf('%s Account on Wrong Side', $type),
                        'description' => sprintf(
                            '%s (%s) has balance on %s side, expected %s',
                            $account['name'],
                            $account['code'],
                            $debit > $credit ? 'debit' : 'credit',
                            $expectedSide
                        ),
                        'impact' => 'Account balance on unexpected side - check for posting errors',
                        'suggestions' => [
                            'Review all transactions posted to this account',
                            'Verify entries are on correct debit/credit side',
                            'Check if correcting/reversing entry is needed',
                            sprintf('%s accounts normally have %s balances', $type, $expectedSide)
                        ],
                        'accounts' => [$account['code']]
                    ];
                }
            }
        }
        
        return $findings;
    }
    
    /**
     * Check for unposted or draft entries
     */
    protected function checkUnpostedEntries(): array
    {
        $findings = [];
        
        // Check for entries with 'draft' status if column exists
        $columns = $this->db->getFieldNames('journal_entries');
        if (in_array('status', $columns)) {
            $builder = $this->db->table('journal_entries');
            $draftCount = $builder->where('status', 'draft')->countAllResults();
            
            if ($draftCount > 0) {
                $findings[] = [
                    'severity' => 'warning',
                    'category' => 'Unposted Entries',
                    'title' => 'Draft Journal Entries Found',
                    'description' => sprintf('%d journal entries are still in draft status', $draftCount),
                    'impact' => 'Draft entries are not reflected in trial balance',
                    'suggestions' => [
                        'Review and post all draft entries',
                        'Delete or archive entries that are no longer needed',
                        'Ensure all completed transactions are posted'
                    ],
                    'accounts' => []
                ];
            }
        }
        
        return $findings;
    }
    
    /**
     * Get detailed analysis for a specific account
     */
    public function analyzeAccount(int $accountId): array
    {
        $analysis = [
            'account_info' => [],
            'transaction_summary' => [],
            'issues' => [],
            'recommendations' => []
        ];
        
        // Get account details
        $account = $this->db->table('accounts')->where('id', $accountId)->get()->getRowArray();
        if (!$account) {
            return $analysis;
        }
        
        $analysis['account_info'] = $account;
        
        // Get transaction summary
        $query = "
            SELECT 
                COUNT(*) as transaction_count,
                MIN(je.entry_date) as first_transaction,
                MAX(je.entry_date) as last_transaction,
                SUM(jl.debit) as total_debits,
                SUM(jl.credit) as total_credits,
                AVG(GREATEST(jl.debit, jl.credit)) as avg_amount,
                MIN(GREATEST(jl.debit, jl.credit)) as min_amount,
                MAX(GREATEST(jl.debit, jl.credit)) as max_amount
            FROM journal_lines jl
            JOIN journal_entries je ON je.id = jl.journal_entry_id
            WHERE jl.account_id = ?
        ";
        
        $analysis['transaction_summary'] = $this->db->query($query, [$accountId])->getRowArray();
        
        return $analysis;
    }
}
