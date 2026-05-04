<?php

namespace App\Controllers;

use Config\Database;
use App\Services\DocumentAttachmentService;

class AccountingJournalLite extends BaseController
{
    private function ensureTables(): void
    {
        $db = Database::connect(); if (!$db) return;
        try { $db->query('SELECT 1 FROM accounts LIMIT 1'); }
        catch (\Throwable $e) {
            $db->query("CREATE TABLE IF NOT EXISTS accounts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(50) NOT NULL,
                name VARCHAR(190) NOT NULL,
                type VARCHAR(50) NOT NULL,
                currency_code VARCHAR(10) DEFAULT 'PKR',
                is_active TINYINT(1) DEFAULT 1,
                parent_id INT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                KEY idx_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        try { $db->query('SELECT 1 FROM journal_entries LIMIT 1'); }
        catch (\Throwable $e) {
            $db->query("CREATE TABLE IF NOT EXISTS journal_entries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entry_date DATE NOT NULL,
                memo VARCHAR(255) NULL,
                currency_code VARCHAR(10) DEFAULT 'PKR',
                total_debits DECIMAL(18,2) NOT NULL DEFAULT 0,
                total_credits DECIMAL(18,2) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        try { $db->query('SELECT 1 FROM journal_lines LIMIT 1'); }
        catch (\Throwable $e) {
            $db->query("CREATE TABLE IF NOT EXISTS journal_lines (
                id INT AUTO_INCREMENT PRIMARY KEY,
                entry_id INT NOT NULL,
                account_id INT NOT NULL,
                description VARCHAR(255) NULL,
                debit DECIMAL(18,2) NOT NULL DEFAULT 0,
                credit DECIMAL(18,2) NOT NULL DEFAULT 0,
                currency_code VARCHAR(10) DEFAULT 'PKR',
                fx_rate DECIMAL(18,8) NULL,
                base_amount DECIMAL(18,2) NULL,
                KEY idx_entry (entry_id),
                KEY idx_account (account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        }
        // Ensure at least two accounts exist
        $cnt = (int)($db->query('SELECT COUNT(*) c FROM accounts')->getRowArray()['c'] ?? 0);
        if ($cnt < 2) {
            $seed = [
                ['1000','Cash','Asset'],
                ['4000','Sales Revenue','Revenue'],
                ['5000','Cost of Goods Sold','Expense'],
                ['2000','Accounts Payable','Liability']
            ];
            foreach ($seed as $s) {
                try { $db->query('INSERT IGNORE INTO accounts(code,name,type,currency_code,is_active) VALUES(?,?,?,?,1)', [$s[0], $s[1], $s[2], 'PKR']); } catch (\Throwable $e) { /* ignore */ }
            }
        }

        // Ensure special accounts (Bank Fees, Exchange Gain, Exchange Loss) exist.
        $special = [
            ['5050','Bank Fees','Expense'],
            ['4050','Exchange Gain','Revenue'],
            ['5055','Exchange Loss','Expense']
        ];
        foreach ($special as $s) {
            try {
                $exists = $db->query('SELECT id FROM accounts WHERE code = ? LIMIT 1', [$s[0]])->getRowArray();
                if (!$exists) {
                    $db->query('INSERT INTO accounts(code,name,type,currency_code,is_active) VALUES(?,?,?,?,1)', [$s[0], $s[1], $s[2], 'PKR']);
                }
            } catch (\Throwable $e) { log_message('error', 'Failed ensuring special account '.$s[0].': '.$e->getMessage()); }
        }
    }

    public function index()
    {
        $this->ensureTables();
        $db = Database::connect();
        $accounts = $db->query('SELECT id, code, name, type FROM accounts ORDER BY code')->getResultArray();
        $entries = $db->query('SELECT id, entry_date, memo, total_debits, total_credits FROM journal_entries ORDER BY id DESC LIMIT 50')->getResultArray();
        return view('accounting/journals/lite', ['accounts' => $accounts, 'entries' => $entries]);
    }

    public function post()
    {
        // Use server method instead of CodeIgniter method detection (CI bug workaround)
        $serverMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (strtoupper($serverMethod) !== 'POST') {
            return redirect()->to('/accounting/journal-lite');
        }
        
        $this->ensureTables();
        $db = Database::connect();
        
        // Try multi-line payload first
        $date = $this->request->getPost('entry_date') ?: ($_POST['entry_date'] ?? '');
        $memo = trim((string)($this->request->getPost('memo') ?: ($_POST['memo'] ?? '')));
        $lines = $this->request->getPost('lines');
        // Always pull attachments directly from request.
        // Note: getFiles() returns nested arrays and isn't needed for this.
        $attachments = $this->request->getFileMultiple('attachments');
        
        // Helper for currency conversion
        helper('currency');
        
        if (is_array($lines) && count($lines) > 0) {
            // Normalize lines (filter out empty rows)
            $clean = [];
            foreach ($lines as $idx => $ln) {
                $acc = (int)($ln['account_id'] ?? 0);
                $desc = trim((string)($ln['description'] ?? ''));
                $cur = strtoupper(trim((string)($ln['currency'] ?? 'PKR')));
                $d = (float)($ln['debit'] ?? 0);
                $c = (float)($ln['credit'] ?? 0);
                $userFx = (float)($ln['fx_rate'] ?? 0);
                if ($acc && ($d > 0 || $c > 0)) {
                    $clean[] = [
                        'account_id' => $acc,
                        'description' => $desc ?: ($memo ?: 'Journal Entry'),
                        'currency' => in_array($cur, ['PKR','USD']) ? $cur : 'PKR',
                        'debit' => $d,
                        'credit' => $c,
                        'fx_rate' => $userFx > 0 ? $userFx : null,
                    ];
                }
            }
            
            $errors = [];
            if (!$date) $errors[] = 'Date is required';
            if (empty($clean)) $errors[] = 'Add at least one line with an amount';
            
            // Basic per-line validation
            foreach ($clean as $i => $ln) {
                if ($ln['debit'] > 0 && $ln['credit'] > 0) {
                    $errors[] = 'Row #' . ($i + 1) . ': Provide either debit or credit, not both';
                }
                if ($ln['debit'] <= 0 && $ln['credit'] <= 0) {
                    $errors[] = 'Row #' . ($i + 1) . ': Amount must be greater than zero';
                }
            }
            
            if (!empty($errors)) {
                return redirect()->back()->with('error', 'Please fix: ' . implode(', ', $errors));
            }
            
            // Prepare conversion and totals in PKR
            $totalDebitPKR = 0.0; $totalCreditPKR = 0.0;
            $prepared = [];
            
            // Cache rates per currency for the given date
            $rateCache = ['PKR' => 1.0];
            
            foreach ($clean as $i => $ln) {
                $cur = $ln['currency'];
                $origAmt = $ln['debit'] > 0 ? $ln['debit'] : $ln['credit'];
                $fxRate = 1.0;
                
                if ($cur !== 'PKR') {
                    // Use user-provided FX if available; else fetch from active rates cache
                    if ($ln['fx_rate']) {
                        $fxRate = (float)$ln['fx_rate'];
                    } else {
                        if (!isset($rateCache[$cur])) {
                            $row = get_active_rate($cur, 'PKR', $date);
                            if (!$row) {
                                return redirect()->back()->with('error', 'Missing exchange rate for ' . $cur . '→PKR as of ' . $date . '. Add it in Settings > Exchange Rates.');
                            }
                            $rateCache[$cur] = (float)$row['rate'];
                        }
                        $fxRate = (float)$rateCache[$cur];
                    }
                }
                
                $pkramt = round($origAmt * $fxRate, 2);
                $debitPKR = $ln['debit'] > 0 ? $pkramt : 0.0;
                $creditPKR = $ln['credit'] > 0 ? $pkramt : 0.0;
                $totalDebitPKR += $debitPKR;
                $totalCreditPKR += $creditPKR;
                
                $prepared[] = [
                    'account_id' => $ln['account_id'],
                    'description' => $ln['description'],
                    'currency_code' => $cur,
                    'fx_rate' => $fxRate,
                    'base_amount' => $origAmt,
                    'debit' => $debitPKR,
                    'credit' => $creditPKR,
                ];
            }
            
            // Balance check (PKR)
            if (abs($totalDebitPKR - $totalCreditPKR) > 0.01) {
                return redirect()->back()->with('error', 'Lines do not balance after conversion. Debits PKR ' . number_format($totalDebitPKR, 2) . ' vs Credits PKR ' . number_format($totalCreditPKR, 2));
            }
            
            $db->transBegin();
            try {
                // Insert journal entry (stored in PKR)
                $db->query('INSERT INTO journal_entries(entry_date,memo,currency_code,total_debits,total_credits) VALUES (?,?,?,?,?)',
                    [$date, $memo ?: 'Journal Entry', 'PKR', $totalDebitPKR, $totalCreditPKR]);
                $entryId = (int)$db->insertID();
                if (!$entryId) throw new \RuntimeException('Failed to create journal entry');
                
                // Insert lines
                foreach ($prepared as $ln) {
                    $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)',
                        [$entryId, $ln['account_id'], $ln['description'], $ln['debit'], $ln['credit'], $ln['currency_code'], $ln['fx_rate'], $ln['base_amount']]
                    );
                }
                
                // Attachments are supporting only (do not affect posting/balances)
                // IMPORTANT: getFileMultiple('attachments') matches the form name attachments[]
                // and returns UploadedFile[] (or empty array), which DocumentAttachmentService expects.
                try {
                    if (!empty($attachments)) {
                        $svc = new DocumentAttachmentService();
                        $svc->storeMany('journal', $entryId, $attachments);
                    }
                } catch (\Throwable $e) {
                    // Best-effort: don't block posting if attachment fails
                    log_message('error', 'JournalLite attachment upload failed for entry ' . $entryId . ': ' . $e->getMessage());
                }

                $db->transCommit();
                return redirect()->to('/accounting/journal-lite')->with('success', 'Journal entry posted successfully! (Entry #' . $entryId . ')');
            } catch (\Throwable $e) {
                $db->transRollback();
                return redirect()->back()->with('error', 'Failed to save: ' . $e->getMessage());
            }
        }
        
        // Fallback: legacy two-line quick post in PKR
        // Get form data - use both CI and raw methods as fallback
        $debit = (int)($this->request->getPost('account_debit') ?: ($_POST['account_debit'] ?? 0));
        $credit = (int)($this->request->getPost('account_credit') ?: ($_POST['account_credit'] ?? 0));
        $amount = (float)($this->request->getPost('amount') ?: ($_POST['amount'] ?? 0));
        
        // Enhanced validation with specific error messages
        $errors = [];
        if (!$date) $errors[] = 'Date is required';
        if (!$debit) $errors[] = 'Debit account is required';
        if (!$credit) $errors[] = 'Credit account is required';
        if ($amount <= 0) $errors[] = 'Amount must be greater than zero';
        if ($debit === $credit) $errors[] = 'Debit and credit accounts must be different';
        
        if (!empty($errors)) {
            return redirect()->back()->with('error', 'Please fix: ' . implode(', ', $errors));
        }
        
        $db->transBegin();
        try {
            // Insert journal entry
            $db->query('INSERT INTO journal_entries(entry_date,memo,currency_code,total_debits,total_credits) VALUES (?,?,?,?,?)', 
                [$date, $memo ?: 'Journal Entry', 'PKR', $amount, $amount]);
            $entryId = (int)$db->insertID();
            
            if (!$entryId) {
                throw new \RuntimeException('Failed to create journal entry');
            }
            
            // Insert debit line
            $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', 
                [$entryId, $debit, $memo ?: 'Journal Entry', $amount, 0, 'PKR', 1, $amount]);
                
            // Insert credit line
            $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', 
                [$entryId, $credit, $memo ?: 'Journal Entry', 0, $amount, 'PKR', 1, $amount]);
            
            // Attachments are supporting only (do not affect posting/balances)
            try {
                $files = $uploadedFiles['attachments'] ?? null;
                if ($files) {
                    $svc = new DocumentAttachmentService();
                    $svc->saveUploads(
                        'journal',
                        $entryId,
                        $files,
                        ['memo' => $memo]
                    );
                }
            } catch (\Throwable $e) {
                // Best-effort: don't block posting if attachment fails
                log_message('error', 'JournalLite attachment upload failed for entry ' . $entryId . ': ' . $e->getMessage());
            }

            $db->transCommit();
            
            return redirect()->to('/accounting/journal-lite')->with('success', 'Journal entry posted successfully! (Entry #' . $entryId . ')');
            
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Failed to save: ' . $e->getMessage());
        }
    }
    
    /** Debug endpoint to check accounts and database state */
    public function debug()
    {
        $this->ensureTables();
        $db = Database::connect();
        
        $debug = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tables' => [],
            'accounts' => [],
            'entries' => [],
            'test_insert' => null
        ];
        
        // Check tables
        $tables = ['accounts', 'journal_entries', 'journal_lines'];
        foreach ($tables as $table) {
            try {
                $count = $db->query("SELECT COUNT(*) as c FROM $table")->getRowArray();
                $debug['tables'][$table] = $count['c'];
            } catch (\Throwable $e) {
                $debug['tables'][$table] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        // Get accounts
        try {
            $debug['accounts'] = $db->query('SELECT id, code, name, type FROM accounts ORDER BY code')->getResultArray();
        } catch (\Throwable $e) {
            $debug['accounts'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Get recent entries
        try {
            $debug['entries'] = $db->query('SELECT id, entry_date, memo, total_debits, total_credits FROM journal_entries ORDER BY id DESC LIMIT 5')->getResultArray();
        } catch (\Throwable $e) {
            $debug['entries'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test insert if we have accounts
        if (is_array($debug['accounts']) && count($debug['accounts']) >= 2) {
            try {
                $acc1 = $debug['accounts'][0]['id'];
                $acc2 = $debug['accounts'][1]['id'];
                $amount = 999.99;
                
                $db->transBegin();
                $db->query('INSERT INTO journal_entries(entry_date,memo,currency_code,total_debits,total_credits) VALUES (?,?,?,?,?)', 
                    [date('Y-m-d'), 'DEBUG TEST', 'PKR', $amount, $amount]);
                $entryId = (int)$db->insertID();
                
                $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', 
                    [$entryId, $acc1, 'DEBUG TEST', $amount, 0, 'PKR', 1, $amount]);
                $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', 
                    [$entryId, $acc2, 'DEBUG TEST', 0, $amount, 'PKR', 1, $amount]);
                
                $db->transCommit();
                $debug['test_insert'] = 'SUCCESS: Created entry #' . $entryId;
                
            } catch (\Throwable $e) {
                $db->transRollback();
                $debug['test_insert'] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        return $this->response->setJSON(['success' => true, 'debug' => $debug]);
    }
    
    /** Test insert endpoint - forces an entry creation for testing */
    public function testInsert()
    {
        $this->ensureTables();
        $db = Database::connect();
        
        // Get first two accounts
        $accounts = $db->query('SELECT id FROM accounts ORDER BY id LIMIT 2')->getResultArray();
        if (count($accounts) < 2) {
            return $this->response->setJSON(['success' => false, 'message' => 'Need at least 2 accounts']);
        }
        
        $acc1 = (int)$accounts[0]['id'];
        $acc2 = (int)$accounts[1]['id'];
        $amount = 555.55;
        $date = date('Y-m-d');
        $memo = 'FORCED TEST INSERT';
        
        try {
            $db->transBegin();
            
            // Insert entry
            $db->query('INSERT INTO journal_entries(entry_date,memo,currency_code,total_debits,total_credits) VALUES (?,?,?,?,?)', 
                [$date, $memo, 'PKR', $amount, $amount]);
            $entryId = (int)$db->insertID();
            
            // Insert lines
            $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', 
                [$entryId, $acc1, $memo, $amount, 0, 'PKR', 1, $amount]);
            $db->query('INSERT INTO journal_lines(entry_id,account_id,description,debit,credit,currency_code,fx_rate,base_amount) VALUES (?,?,?,?,?,?,?,?)', 
                [$entryId, $acc2, $memo, 0, $amount, 'PKR', 1, $amount]);
            
            $db->transCommit();
            
            // Check counts
            $counts = [
                'entries' => (int)$db->query('SELECT COUNT(*) as c FROM journal_entries')->getRowArray()['c'],
                'lines' => (int)$db->query('SELECT COUNT(*) as c FROM journal_lines')->getRowArray()['c']
            ];
            
            return $this->response->setJSON([
                'success' => true, 
                'message' => 'Successfully created test entry #' . $entryId,
                'entry_id' => $entryId,
                'counts' => $counts
            ]);
            
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON([
                'success' => false, 
                'message' => 'Test insert failed: ' . $e->getMessage()
            ]);
        }
    }
    
    /** Simple diagnostic to check if any POST is reaching the controller */
    public function testPost()
    {
        $method = $this->request->getMethod();
        $serverMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
        $allPost = $this->request->getPost();
        $rawPost = $_POST;
        
        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'ci_method' => $method,
            'server_method' => $serverMethod,
            'is_post_ci' => ($method === 'post'),
            'is_post_server' => (strtoupper($serverMethod) === 'POST'),
            'ci_post_data' => $allPost,
            'raw_post_data' => $rawPost,
            'ci_post_count' => count($allPost),
            'raw_post_count' => count($rawPost),
            'headers' => $this->request->headers()
        ];
        log_message('info', 'TestPost called: ' . json_encode($result));
        return $this->response->setJSON(['success' => true, 'data' => $result]);
    }
    
    /** Test page with debugging forms */
    public function test()
    {
        return view('journal_test');
    }
    
    /** Lightweight JSON endpoint for account search/autocomplete */
    public function accountsJson()
    {
        $this->ensureTables();
        $db = Database::connect();
        // Optional query filter q: matches code or name (case-insensitive)
        $q = trim((string)($this->request->getGet('q') ?? ''));
        try {
            if ($q !== '') {
                $like = '%' . $db->escapeLikeString($q) . '%';
                $rows = $db->query(
                    'SELECT id, code, name, type FROM accounts 
                     WHERE is_active = 1 AND (code LIKE ? OR name LIKE ?)
                     ORDER BY code LIMIT 50',
                    [$like, $like]
                )->getResultArray();
            } else {
                $rows = $db->query('SELECT id, code, name, type FROM accounts WHERE is_active = 1 ORDER BY code LIMIT 200')->getResultArray();
            }
            return $this->response->setJSON(['success' => true, 'data' => $rows]);
        } catch (\Throwable $e) {
            log_message('error', 'accountsJson failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'error' => 'Failed to load accounts']);
        }
    }
    
    /** Clean all journal data and reset entry numbers */
    public function cleanDatabase()
    {
        $db = Database::connect();
        
        try {
            // Delete all journal data
            $db->query('DELETE FROM journal_lines');
            $db->query('DELETE FROM journal_entries');
            
            // Reset auto increment
            $db->query('ALTER TABLE journal_entries AUTO_INCREMENT = 1');
            $db->query('ALTER TABLE journal_lines AUTO_INCREMENT = 1');
            
            $result = [
                'success' => true,
                'message' => 'Database cleaned successfully!',
                'entries_deleted' => 'All journal entries removed',
                'reset_counter' => 'Entry numbers reset to #1',
                'accounts_kept' => 'Chart of accounts preserved'
            ];
            
        } catch (\Throwable $e) {
            $result = [
                'success' => false,
                'message' => 'Failed to clean database',
                'error' => $e->getMessage()
            ];
        }
        
        return $this->response->setJSON($result);
    }
}
