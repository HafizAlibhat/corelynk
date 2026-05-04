<?php

namespace App\Controllers;

use App\Models\Accounting\JournalEntryModel;
use App\Models\Accounting\JournalLineModel;
use App\Models\Accounting\AccountModel;
use App\Models\DocumentAttachmentModel;
use App\Services\DocumentAttachmentService;
use Config\Database;

class AccountingJournals extends BaseController
{
    /** Ensure minimal accounting tables exist (accounts, journal_entries, journal_lines). */
    private function ensureAccountingTables(): void
    {
        $db = Database::connect();
        if (!$db) { return; }
        try {
            $check = static function(string $table) use ($db): bool {
                try { $db->query("SHOW TABLES LIKE '$table'"); return $db->getLastQuery() ? true : false; } catch (\Throwable $e) { return false; }
            };
            // Accounts
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
            // Journal entries
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
            // Journal lines
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
        } catch (\Throwable $e) {
            log_message('error', 'ensureAccountingTables failed: '.$e->getMessage());
        }
    }
    public function index()
    {
        $m = new JournalEntryModel();
        $entries = $m->orderBy('entry_date', 'DESC')->limit(50)->findAll();

        // UI-level linkage: keep double-entry records separate, but show related invoice/payment chains together.
        try {
            $db = Database::connect();
            $paymentIds = [];
            $invoiceIds = [];
            foreach ($entries as $row) {
                $stype = strtolower(trim((string)($row['source_type'] ?? '')));
                $sid = (int)($row['source_id'] ?? 0);
                if ($stype === 'payment' && $sid > 0) {
                    $paymentIds[] = $sid;
                } elseif ($stype === 'invoice' && $sid > 0) {
                    $invoiceIds[] = $sid;
                }
            }
            $paymentIds = array_values(array_unique($paymentIds));
            $invoiceIds = array_values(array_unique($invoiceIds));

            $paymentMap = [];
            if (!empty($paymentIds)) {
                $rows = $db->query(
                    'SELECT cpa.payment_id, cpa.invoice_id, ci.invoice_number, jei.id AS invoice_journal_id '
                    . 'FROM customer_payment_allocations cpa '
                    . 'LEFT JOIN customer_invoices ci ON ci.id = cpa.invoice_id '
                    . "LEFT JOIN journal_entries jei ON jei.source_type = 'invoice' AND jei.source_id = cpa.invoice_id "
                    . 'WHERE cpa.payment_id IN (' . implode(',', array_map('intval', $paymentIds)) . ')'
                )->getResultArray();
                foreach ($rows as $r) {
                    $pid = (int)($r['payment_id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    if (!isset($paymentMap[$pid])) {
                        $paymentMap[$pid] = ['invoices' => [], 'journals' => []];
                    }
                    $invNo = trim((string)($r['invoice_number'] ?? ''));
                    if ($invNo === '' && !empty($r['invoice_id'])) {
                        $invNo = 'INV-' . (int)$r['invoice_id'];
                    }
                    if ($invNo !== '') {
                        $paymentMap[$pid]['invoices'][] = $invNo;
                    }
                    $jid = (int)($r['invoice_journal_id'] ?? 0);
                    if ($jid > 0) {
                        $paymentMap[$pid]['journals'][] = $jid;
                    }
                }
            }

            $invoiceMap = [];
            if (!empty($invoiceIds)) {
                $rows = $db->query(
                    'SELECT cpa.invoice_id, cpa.payment_id, jep.id AS payment_journal_id '
                    . 'FROM customer_payment_allocations cpa '
                    . "LEFT JOIN journal_entries jep ON jep.source_type = 'payment' AND jep.source_id = cpa.payment_id "
                    . 'WHERE cpa.invoice_id IN (' . implode(',', array_map('intval', $invoiceIds)) . ')'
                )->getResultArray();
                foreach ($rows as $r) {
                    $iid = (int)($r['invoice_id'] ?? 0);
                    if ($iid <= 0) {
                        continue;
                    }
                    if (!isset($invoiceMap[$iid])) {
                        $invoiceMap[$iid] = ['payments' => [], 'journals' => []];
                    }
                    $pid = (int)($r['payment_id'] ?? 0);
                    if ($pid > 0) {
                        $invoiceMap[$iid]['payments'][] = $pid;
                    }
                    $jid = (int)($r['payment_journal_id'] ?? 0);
                    if ($jid > 0) {
                        $invoiceMap[$iid]['journals'][] = $jid;
                    }
                }
            }

            foreach ($entries as &$row) {
                $stype = strtolower(trim((string)($row['source_type'] ?? '')));
                $sid = (int)($row['source_id'] ?? 0);
                $row['chain_note'] = '';
                $row['chain_journals'] = [];

                if ($stype === 'payment' && $sid > 0 && isset($paymentMap[$sid])) {
                    $invoices = array_values(array_unique($paymentMap[$sid]['invoices'] ?? []));
                    $journals = array_values(array_unique(array_map('intval', $paymentMap[$sid]['journals'] ?? [])));
                    if (!empty($invoices)) {
                        $row['chain_note'] = 'Applies to: ' . implode(', ', $invoices);
                    }
                    $row['chain_journals'] = $journals;
                } elseif ($stype === 'invoice' && $sid > 0) {
                    $info = $invoiceMap[$sid] ?? ['payments' => [], 'journals' => []];
                    $payIds = array_values(array_unique(array_map('intval', $info['payments'] ?? [])));
                    $journals = array_values(array_unique(array_map('intval', $info['journals'] ?? [])));
                    if (!empty($payIds)) {
                        $row['chain_note'] = 'Paid via payment #' . implode(', #', $payIds);
                    } else {
                        $row['chain_note'] = 'No payment posted yet';
                    }
                    $row['chain_journals'] = $journals;
                }
            }
            unset($row);
        } catch (\Throwable $_) {
            // best effort only; list should still load
        }

        $counts = [];
        try {
        $db = Database::connect();
            $counts['entries'] = (int)$db->query('SELECT COUNT(*) c FROM journal_entries')->getRowArray()['c'];
            $counts['lines'] = (int)$db->query('SELECT COUNT(*) c FROM journal_lines')->getRowArray()['c'];
        } catch (\Throwable $e) { $counts['error'] = $e->getMessage(); }
        return view('accounting/journals/index', ['entries' => $entries, 'counts' => $counts]);
    }

    public function quick()
    {
        // Ensure tables exist and seed default accounts if none
        $this->ensureAccountingTables();
        $am = new AccountModel();
        $accounts = $am->orderBy('code','ASC')->findAll();
        if (empty($accounts)) {
            // Seed a minimal chart so quick journal can work
            $this->seedDefaultAccounts();
            $accounts = $am->orderBy('code','ASC')->findAll();
        }
        return view('accounting/journals/quick', ['accounts' => $accounts]);
    }

    public function postQuick()
    {
        if ($this->request->getMethod() !== 'post') {
            return redirect()->to('/accounting/journals/quick');
        }
        // Ensure tables exist for fresh installs
        $this->ensureAccountingTables();
        $rawPost = $this->request->getPost();
        log_message('debug', 'QuickJournal POST raw=' . json_encode($rawPost));
        $date = $this->request->getPost('entry_date');
        $memo = trim((string) $this->request->getPost('memo'));
        $accDebit = (int) $this->request->getPost('account_debit');
        $accCredit = (int) $this->request->getPost('account_credit');
        $amount = (float) $this->request->getPost('amount');
        if (!$date || !$accDebit || !$accCredit || $amount <= 0 || $accDebit === $accCredit) {
            log_message('error', 'QuickJournal validation failed date=' . $date . ' debit=' . $accDebit . ' credit=' . $accCredit . ' amount=' . $amount);
            return redirect()->back()->with('error', 'Invalid input for quick journal.');
        }
        // Ensure accounts exist; auto-seed chart if empty so quick post works from a fresh DB or CLI
        $acctModel = new AccountModel();
        $missing = [];
        $debitExists = (bool)$acctModel->find($accDebit);
        $creditExists = (bool)$acctModel->find($accCredit);
        if (!$debitExists || !$creditExists) {
            log_message('warning', 'QuickJournal accounts missing (debitExists=' . ($debitExists?'Y':'N') . ', creditExists=' . ($creditExists?'Y':'N') . '). Attempting seed.');
            $this->seedDefaultAccounts();
            // Re-check after seed
            $debitExists = (bool)$acctModel->find($accDebit);
            $creditExists = (bool)$acctModel->find($accCredit);
        }
        if (!$debitExists) { $missing[] = 'debit account ' . $accDebit; }
        if (!$creditExists) { $missing[] = 'credit account ' . $accCredit; }
        if ($missing) {
            log_message('error', 'QuickJournal missing accounts after seed attempt: ' . implode(',', $missing));
            return redirect()->back()->with('error', 'Missing accounts: ' . implode(', ', $missing));
        }
        $db = Database::connect();
        if (!$db) {
            log_message('error', 'Accounting DB connection failed');
            return redirect()->back()->with('error', 'Accounting database not reachable.');
        }
        try { $db->query('SELECT 1 FROM journal_entries LIMIT 1'); $db->query('SELECT 1 FROM journal_lines LIMIT 1'); }
        catch (\Throwable $t) {
            log_message('error', 'Accounting tables missing: ' . $t->getMessage());
            return redirect()->back()->with('error', 'Accounting tables missing. Run setup_accounting_db.php');
        }
        $db->transBegin();
        try {
            $je = new JournalEntryModel();
            $jeId = $je->insert([
                'entry_date' => $date,
                'memo' => $memo,
                'currency_code' => 'PKR',
                'total_debits' => $amount,
                'total_credits' => $amount,
            ], true);
            if (!$jeId) {
                log_message('error', 'JournalEntry insert failed errors=' . json_encode($je->errors()));
                throw new \RuntimeException('Failed to insert journal entry: ' . implode('; ', $je->errors() ?: []));
            }
            $jl = new JournalLineModel();
            $ok1 = $jl->insert([
                'entry_id' => $jeId,
                'account_id' => $accDebit,
                'description' => $memo,
                'debit' => $amount,
                'credit' => 0,
                'currency_code' => 'PKR',
                'fx_rate' => 1,
                'base_amount' => $amount,
            ]);
            $ok2 = $jl->insert([
                'entry_id' => $jeId,
                'account_id' => $accCredit,
                'description' => $memo,
                'debit' => 0,
                'credit' => $amount,
                'currency_code' => 'PKR',
                'fx_rate' => 1,
                'base_amount' => $amount,
            ]);
            if (!$ok1 || !$ok2) {
                log_message('error', 'JournalLine insert failed errors=' . json_encode($jl->errors()));
                throw new \RuntimeException('Failed to insert journal lines: ' . implode('; ', $jl->errors() ?: []));
            }
            if ($db->transStatus() === false) {
                throw new \RuntimeException('DB transaction failed');
            }
            $db->transCommit();
            $counts = [
                'entries' => (int)$db->query('SELECT COUNT(*) c FROM journal_entries')->getRowArray()['c'],
                'lines' => (int)$db->query('SELECT COUNT(*) c FROM journal_lines')->getRowArray()['c'],
                'lines_for_entry' => (int)$db->query('SELECT COUNT(*) c FROM journal_lines WHERE entry_id = ?', [$jeId])->getRowArray()['c'],
            ];
            $lastEntry = $db->query('SELECT id, entry_date, total_debits, total_credits FROM journal_entries ORDER BY id DESC LIMIT 1')->getRowArray();
            log_message('debug', 'Quick journal posted ID=' . $jeId . ' counts=' . json_encode($counts) . ' lastEntry=' . json_encode($lastEntry));
            $flashMsg = 'Journal posted (ID ' . $jeId . '). Entries=' . $counts['entries'] . ' Lines=' . $counts['lines'] . ' LinesForEntry=' . $counts['lines_for_entry'];
            return redirect()->to('/accounting/journals')->with('success', $flashMsg);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Quick journal failed: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Failed to post journal: ' . $e->getMessage());
        }
    }

    private function seedDefaultAccounts(): void
    {
        $accts = [
            ['code'=>'1000','name'=>'Cash','type'=>'Asset','currency_code'=>'PKR'],
            ['code'=>'1100','name'=>'Bank','type'=>'Asset','currency_code'=>'PKR'],
            ['code'=>'1200','name'=>'Accounts Receivable','type'=>'Asset','currency_code'=>'PKR'],
            ['code'=>'1300','name'=>'Inventory','type'=>'Asset','currency_code'=>'PKR'],
            ['code'=>'1400','name'=>'Prepaid Expenses','type'=>'Asset','currency_code'=>'PKR'],
            ['code'=>'1500','name'=>'Fixed Assets','type'=>'Asset','currency_code'=>'PKR'],
            ['code'=>'2000','name'=>'Accounts Payable','type'=>'Liability','currency_code'=>'PKR'],
            ['code'=>'2100','name'=>'Accrued Expenses','type'=>'Liability','currency_code'=>'PKR'],
            ['code'=>'2200','name'=>'Taxes Payable','type'=>'Liability','currency_code'=>'PKR'],
            ['code'=>'3000','name'=>'Owner\'s Capital','type'=>'Equity','currency_code'=>'PKR'],
            ['code'=>'3100','name'=>'Retained Earnings','type'=>'Equity','currency_code'=>'PKR'],
            ['code'=>'4000','name'=>'Sales Revenue','type'=>'Revenue','currency_code'=>'PKR'],
            ['code'=>'4100','name'=>'Service Revenue','type'=>'Revenue','currency_code'=>'PKR'],
            ['code'=>'5000','name'=>'Cost of Goods Sold','type'=>'Expense','currency_code'=>'PKR'],
            ['code'=>'5100','name'=>'Rent Expense','type'=>'Expense','currency_code'=>'PKR'],
            ['code'=>'5200','name'=>'Salaries Expense','type'=>'Expense','currency_code'=>'PKR'],
            ['code'=>'5300','name'=>'Utilities Expense','type'=>'Expense','currency_code'=>'PKR'],
            ['code'=>'5400','name'=>'Office Supplies','type'=>'Expense','currency_code'=>'PKR'],
            ['code'=>'5500','name'=>'Depreciation Expense','type'=>'Expense','currency_code'=>'PKR'],
            ['code'=>'5600','name'=>'Marketing Expense','type'=>'Expense','currency_code'=>'PKR'],
        ];
        $model = new AccountModel();
        foreach ($accts as $a) {
            try { $model->insert($a); } catch (\Throwable $e) { /* ignore duplicates */ }
        }
    }

    public function diag()
    {
    $db = Database::connect();
        $summary = [];
        try {
            $summary['entries'] = $db->query('SELECT COUNT(*) c FROM journal_entries')->getRowArray()['c'];
            $summary['lines'] = $db->query('SELECT COUNT(*) c FROM journal_lines')->getRowArray()['c'];
            $summary['sample_entries'] = $db->query('SELECT id, entry_date, memo, total_debits, total_credits FROM journal_entries ORDER BY id DESC LIMIT 5')->getResultArray();
        } catch (\Throwable $e) { $summary['error'] = $e->getMessage(); }
        return $this->response->setJSON(['success' => true, 'data' => $summary]);
    }

    /**
     * Render a printable journal receipt/voucher for a journal entry.
     * URL: /accounting/journals/receipt/{id}
     */
    public function receipt($id = null)
    {
        $id = (int)$id;
        if (!$id) {
            return $this->response->setStatusCode(400)->setBody('Invalid entry id');
        }

        // Handle attachment upload (supporting docs only; never changes accounting values)
        // Allowed only on valid journal id.
        if (strtolower($this->request->getMethod()) === 'post') {
            try {
                $svc = new DocumentAttachmentService();
                $files = $this->request->getFiles();
                $attachments = $files['attachments'] ?? null;
                $uploadedBy = session()->get('user_id') ?? null;

                // Basic safety: allow upload only for authenticated users (journal creators).
                // Project-specific permission checks can be added later.
                if (!$uploadedBy) {
                    return redirect()->back()->with('error', 'Login required to upload attachments.');
                }

                // Lock rule: if journal is posted or accounting period closed => read-only.
                // Current implementation: treat any entry with source_type set (non-manual) as locked.
                // (No schema changes; best-effort rule.)
                $je = (new JournalEntryModel())->find($id);
                $locked = !empty($je['source_type'] ?? '') && ($je['source_type'] ?? '') !== 'manual';
                if ($locked) {
                    return redirect()->back()->with('error', 'This journal is locked. Attachments are read-only.');
                }

                // Normalize multiple uploads
                $fileList = [];
                if (is_array($attachments)) {
                    // When using multiple file input name="attachments[]"
                    $fileList = array_values($attachments);
                } elseif ($attachments) {
                    $fileList = [$attachments];
                }

                if (empty($fileList)) {
                    return redirect()->back()->with('error', 'No files selected.');
                }

                $result = $svc->storeMany('journal', $id, $fileList, (int)$uploadedBy);
                $okCount = 0;
                $failMsgs = [];
                foreach (($result['results'] ?? []) as $r) {
                    if (!empty($r['success'])) $okCount++;
                    else $failMsgs[] = (string)($r['message'] ?? 'Upload failed');
                }
                log_message('debug', 'Journal attachment upload journal_id=' . $id . ' ok=' . $okCount . ' fails=' . json_encode($failMsgs));
                if ($okCount > 0 && empty($failMsgs)) {
                    return redirect()->to(site_url('accounting/journals/receipt/' . $id))->with('success', 'Attachment(s) uploaded.');
                }
                if ($okCount > 0) {
                    return redirect()->to(site_url('accounting/journals/receipt/' . $id))->with('info', 'Some files uploaded. Some failed: ' . implode('; ', $failMsgs));
                }
                return redirect()->to(site_url('accounting/journals/receipt/' . $id))->with('error', 'Upload failed: ' . implode('; ', $failMsgs));
            } catch (\Throwable $e) {
                log_message('error', 'Journal attachment upload failed journal_id=' . $id . ' err=' . $e->getMessage());
                return redirect()->back()->with('error', 'Upload failed: ' . $e->getMessage());
            }
        }

        $data = $this->buildReceiptData($id);

        // Attachments list (supporting docs only)
        try {
            $db = Database::connect();
            $attachments = $db->query(
                "SELECT id,document_type,document_id,file_path,original_name,mime_type,uploaded_at FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                [$id, 'journal']
            )->getResultArray();

            // For source-linked journals (e.g. customer payment posting), also show source document attachments.
            if (!empty($data['source_type']) && strtolower((string)$data['source_type']) === 'payment' && !empty($data['source_id'])) {
                $srcId = (int)$data['source_id'];
                if ($srcId > 0) {
                    $srcAttachments = $db->query(
                        "SELECT id,document_type,document_id,file_path,original_name,mime_type,uploaded_at FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                        [$srcId, 'customer_payment']
                    )->getResultArray();
                    if (!empty($srcAttachments)) {
                        $attachments = array_merge($attachments, $srcAttachments);
                    }
                }
            }

            $data['attachments'] = $attachments;
        } catch (\Throwable $e) {
            $data['attachments'] = [];
        }

        // Pass lock state to view (best-effort; avoid schema changes)
        try {
            $je = (new JournalEntryModel())->find($id);
            $data['attachments_locked'] = (!empty($je['source_type'] ?? '') && ($je['source_type'] ?? '') !== 'manual');
        } catch (\Throwable $e) {
            $data['attachments_locked'] = false;
        }

        // Render the existing receipt template (reused for journal entries)
        echo view('accounting/receipts/receipt', $data);
    }

    /**
     * Detailed view page for a journal entry (includes attachments list).
     * URL: /accounting/journals/view/{id}
     */
    public function view($id = null)
    {
        $id = (int)$id;
        if (!$id) {
            return $this->response->setStatusCode(400)->setBody('Invalid entry id');
        }

        $data = $this->buildReceiptData($id);

        // Attachments list (supporting docs only)
        try {
            $db = Database::connect();
            $attachments = $db->query(
                "SELECT id,document_type,document_id,file_path,original_name,mime_type,uploaded_at FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                [$id, 'journal']
            )->getResultArray();

            // Also include source customer payment attachments for posted payment journals.
            $srcType = strtolower(trim((string)($data['source_type'] ?? '')));
            $srcId = (int)($data['source_id'] ?? 0);
            if ($srcType === 'payment' && $srcId > 0) {
                $srcAttachments = $db->query(
                    "SELECT id,document_type,document_id,file_path,original_name,mime_type,uploaded_at FROM document_attachments WHERE document_id = ? AND LOWER(document_type) = ? ORDER BY uploaded_at DESC",
                    [$srcId, 'customer_payment']
                )->getResultArray();
                if (!empty($srcAttachments)) {
                    $attachments = array_merge($attachments, $srcAttachments);
                }
            }

            $data['attachments'] = $attachments;
        } catch (\Throwable $e) {
            $data['attachments'] = [];
        }

        // Best-effort lock state (avoid schema changes)
        try {
            $je = (new JournalEntryModel())->find($id);
            $data['attachments_locked'] = (!empty($je['source_type'] ?? '') && ($je['source_type'] ?? '') !== 'manual');
        } catch (\Throwable $e) {
            $data['attachments_locked'] = false;
        }

        $data['id'] = $id;
        return view('accounting/journals/detail', $data);
    }

    /**
     * Build and return the receipt data array for a journal entry id.
     */
    private function buildReceiptData(int $id): array
    {
        $jeModel = new JournalEntryModel();
        $entry = $jeModel->find($id);
        if (!$entry) {
            throw new \RuntimeException('Journal entry not found');
        }

        // Fetch lines with account info
        $db = Database::connect();
        try {
            $lines = $db->query('SELECT jl.*, a.code AS account_code, a.name AS account_name, a.type AS account_type FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id WHERE jl.entry_id = ? ORDER BY jl.debit DESC', [$id])->getResultArray();
        } catch (\Throwable $e) {
            $lines = [];
        }

        $sumDebit = 0.0; $sumCredit = 0.0;
        foreach ($lines as $l) { $sumDebit += (float)($l['debit'] ?? 0); $sumCredit += (float)($l['credit'] ?? 0); }
        $amount = max($sumDebit, $sumCredit);

        // Minimal amount to words function (handles up to billions)
        $amount_words = $this->numberToWords((int)floor($amount));
        $fraction = (int)round(($amount - floor($amount)) * 100);
        if ($fraction > 0) {
            $amount_words .= ' and ' . $this->numberToWords($fraction) . ' Paise';
        }

        // Determine a party label/value heuristically
        $party_label = 'Received From';
        $party_value = '';
        if (!empty($lines)) {
            // prefer first line description or account name
            $first = $lines[0];
            $party_value = $first['description'] ?: ($first['account_name'] ?? '');
        }

        // Heuristically extract cheque number and payment mode only when explicitly mentioned
        $cheque_no = '';
        $mode = '';
        foreach ($lines as $l) {
            $desc = strtolower($l['description'] ?? '');
            if (strpos($desc, 'cheque') !== false || strpos($desc, 'check') !== false) {
                // only consider cheque number if the word "cheque" appears
                $mode = 'Cheque';
                if (preg_match('/cheque\s*#?\s*(\d{2,16})/i', $l['description'] ?? '', $m)) {
                    $cheque_no = trim($m[1]);
                    break;
                }
            }
        }

        // Build a human-friendly transaction narrative and simplified lines
        $assetDebit = 0.0; $assetCredit = 0.0;
        foreach ($lines as $l) {
            $atype = strtoupper(trim($l['account_type'] ?? ''));
            if ($atype === 'ASSET') { $assetDebit += (float)($l['debit'] ?? 0); $assetCredit += (float)($l['credit'] ?? 0); }
        }
        $transactionType = 'Transaction';
        $principalParty = '';
        $principalAmount = $amount;
        if ($assetDebit > 0 && $assetDebit >= $assetCredit) {
            // Cash/Bank increased = money received
            $transactionType = 'Received';
            // party is first non-asset credit line or description
            foreach ($lines as $l) {
                $atype = strtoupper(trim($l['account_type'] ?? ''));
                if ($atype !== 'ASSET' && (float)($l['credit'] ?? 0) > 0) { $principalParty = $l['account_name'] ?? ($l['description'] ?? ''); break; }
            }
        } elseif ($assetCredit > 0 && $assetCredit > $assetDebit) {
            // Cash/Bank decreased = money paid
            $transactionType = 'Paid';
            foreach ($lines as $l) {
                $atype = strtoupper(trim($l['account_type'] ?? ''));
                if ($atype !== 'ASSET' && (float)($l['debit'] ?? 0) > 0) { $principalParty = $l['account_name'] ?? ($l['description'] ?? ''); break; }
            }
        } else {
            // fallback: use first line description or account name
            $transactionType = 'Transaction';
            if (!empty($lines)) { $principalParty = $lines[0]['description'] ?: ($lines[0]['account_name'] ?? ''); }
        }

        // ── Enrich with source-entity context ──────────────────────────────────────
        $narrativeContext = [];
        $sourceType = strtolower(trim((string)($entry['source_type'] ?? '')));
        $sourceId   = (int)($entry['source_id'] ?? 0);

        if ($sourceType === 'payment' && $sourceId > 0) {
            try {
                $pCols = $db->getFieldNames('customer_payments');

                $pmJoin    = $db->tableExists('payment_methods') ? 'LEFT JOIN payment_methods pm ON pm.id = cp.payment_method_id ' : '';
                $pmExpr    = $db->tableExists('payment_methods') ? 'pm.method_name AS payment_method_name, ' : "'' AS payment_method_name, ";
                $hasSource = in_array('source_account_id', $pCols, true);
                $accJoin   = $hasSource ? 'LEFT JOIN accounts sa ON sa.id = cp.source_account_id ' : '';
                $accExpr   = $hasSource ? 'sa.name AS receiving_account_name, ' : "'' AS receiving_account_name, ";
                $hasCurr   = in_array('currency_code', $pCols, true);
                $currExpr  = $hasCurr ? 'cp.currency_code AS pay_currency, ' : "'' AS pay_currency, ";
                $hasNotes  = in_array('notes', $pCols, true);
                $notesExpr = $hasNotes ? 'cp.notes AS pay_notes, ' : "'' AS pay_notes, ";
                $hasRef    = in_array('reference_number', $pCols, true);
                $refExpr   = $hasRef ? 'cp.reference_number AS pay_reference, ' : "'' AS pay_reference, ";

                $cp = $db->query(
                    'SELECT cp.id, cp.customer_id, cp.payment_date, cp.amount, cp.payment_method_id, '
                    . $pmExpr . $accExpr . $currExpr . $notesExpr . $refExpr
                    . 'c.name AS customer_name '
                    . 'FROM customer_payments cp '
                    . 'LEFT JOIN customers c ON c.id = cp.customer_id '
                    . $pmJoin . $accJoin
                    . 'WHERE cp.id = ? LIMIT 1',
                    [$sourceId]
                )->getRowArray();

                if ($cp) {
                    // Resolve invoice allocations for this payment
                    $allocCols   = $db->getFieldNames('customer_payment_allocations');
                    $amtCol      = in_array('allocated_amount', $allocCols, true) ? 'allocated_amount' : (in_array('amount', $allocCols, true) ? 'amount' : 'allocated_amount');
                    $invCols2    = $db->getFieldNames('customer_invoices');
                    $invNoExpr2  = in_array('invoice_number', $invCols2, true) ? 'ci.invoice_number' : "CONCAT('INV-', ci.id) AS invoice_number";
                    $allocations = $db->query(
                        'SELECT cpa.invoice_id, cpa.' . $amtCol . ' AS allocated_amount, ' . $invNoExpr2 . ' '
                        . 'FROM customer_payment_allocations cpa '
                        . 'LEFT JOIN customer_invoices ci ON ci.id = cpa.invoice_id '
                        . 'WHERE cpa.payment_id = ? ORDER BY cpa.id ASC',
                        [$sourceId]
                    )->getResultArray();

                    $invoiceList = array_map(fn($a) => ($a['invoice_number'] ?? ('INV-' . $a['invoice_id'])), $allocations);
                    $method      = ucwords(str_replace('_', ' ', $cp['payment_method_name'] ?: 'Online Transfer'));
                    $recvAcc     = trim((string)($cp['receiving_account_name'] ?? ''));
                    $custName    = trim((string)($cp['customer_name'] ?? ('Customer #' . $cp['customer_id'])));
                    $payAmt      = number_format((float)($cp['amount'] ?? 0), 2);
                    $payCurr     = strtoupper(trim((string)($cp['pay_currency'] ?? '')));
                    if ($payCurr === '') {
                        $payCurr = strtoupper(trim((string)($entry['currency_code'] ?? '')));
                    }
                    if ($payCurr === '') {
                        $payCurr = 'PKR';
                    }
                    $payDate     = !empty($cp['payment_date']) ? date('d M Y', strtotime((string)$cp['payment_date'])) : '';
                    $reference   = trim((string)($cp['pay_reference'] ?? ''));

                    // Build one-sentence plain-English narration
                    $invPhrase = !empty($invoiceList) ? (' against ' . implode(', ', $invoiceList)) : '';
                    $recvPhrase = $recvAcc ? ('. Deposited to: ' . $recvAcc) : '';
                    $refPhrase  = $reference ? ('. Ref: ' . $reference) : '';

                    $humanNarration =
                        'Received ' . $payCurr . ' ' . $payAmt . ' from ' . $custName
                        . $invPhrase
                        . ' via ' . $method
                        . ($payDate ? ' on ' . $payDate : '')
                        . $recvPhrase
                        . $refPhrase . '.';

                    $narrativeContext = [
                        'customer_name'      => $custName,
                        'amount_display'     => $payCurr . ' ' . $payAmt,
                        'payment_method'     => $method,
                        'payment_date'       => $payDate,
                        'receiving_account'  => $recvAcc,
                        'reference'          => $reference,
                        'invoice_numbers'    => $invoiceList,
                        'human_narration'    => $humanNarration,
                        'source_label'       => 'Customer Payment #' . $sourceId,
                    ];
                }
            } catch (\Throwable $_) { /* best effort */ }
        }

        // ── Build simplified lines: account name => absolute amount ───────────────
        $simpleLines = [];
        foreach ($lines as $l) {
            $accName = trim(($l['account_code'] ?? '') . ' - ' . ($l['account_name'] ?? ''));
            $amt = max((float)($l['debit'] ?? 0), (float)($l['credit'] ?? 0));
            if ($amt <= 0) continue;
            $simpleLines[] = ['account' => $accName, 'amount' => $amt];
        }

        // Load company settings (if available) for header display
        $companySettings = (new \App\Models\CompanySettingsModel())->first();
        $compName = $companySettings['name'] ?? (config('App')->appName ?? 'Company');
        $compSlogan = $companySettings['contact'] ?? ($companySettings['address'] ?? '');
        $compLogo = !empty($companySettings['logo_path']) ? base_url($companySettings['logo_path']) : '';

        $data = [
            'company_name' => $compName,
            'company_slogan' => $compSlogan,
            'company_logo' => $compLogo,
            'date' => $entry['entry_date'] ?? date('Y-m-d'),
            // NOTE: journal_entries currently has no created_at column; use entry_date as best-available value.
            // (If/when created_at is added, set it here.)
            'created_at' => $entry['entry_date'] ?? null,
            'receipt_number' => 'JV-' . $entry['id'],
            'id' => $entry['id'],
            'source_type' => $entry['source_type'] ?? '',
            'source_id' => (int)($entry['source_id'] ?? 0),
            'amount' => $amount,
            'currency_code' => $entry['currency_code'] ?? 'PKR',
            'amount_words' => $amount_words,
            'amount_in_words' => $amount_words,
            'payer' => $party_value,
            'payer_name' => $party_value,
            'vendor_name' => '',
            'vendor' => '',
            'mode' => $mode,
            'cheque_no' => $cheque_no,
            'lines' => $lines,
            'description' => $entry['memo'] ?? '',
            'payment_description' => $entry['memo'] ?? '',
            'for' => $entry['memo'] ?? '',
            'remarks' => $entry['memo'] ?? '',
            // user-friendly fields
            'transaction_type' => $transactionType,
            'principal_party' => $principalParty,
            'principal_amount' => $principalAmount,
            'simple_lines'      => $simpleLines,
            'show_cheque'       => ($mode === 'Cheque' && $cheque_no !== ''),
            'sum_debit'         => $sumDebit,
            'sum_credit'        => $sumCredit,
            'balanced'          => (abs($sumDebit - $sumCredit) < 0.01),
            'narrative_context' => $narrativeContext,
        ];

        return $data;
    }

    /**
     * Generate a server-side PDF using wkhtmltopdf and stream it to the user.
     * Requires wkhtmltopdf to be installed on the server/host.
     */
    public function receiptPdf($id = null)
    {
        $id = (int)$id;
        if (!$id) {
            return $this->response->setStatusCode(400)->setBody('Invalid entry id');
        }
        // Render a clean HTML variant of the receipt view (pdf flag hides interactive controls & JS)
        try {
            $data = $this->buildReceiptData($id);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(404)->setBody('Journal entry not found');
        }
        $html = view('accounting/receipts/receipt', array_merge($data, ['pdf' => true]));

        if (empty(trim($html))) {
            return $this->response->setStatusCode(500)->setBody('Failed to render receipt HTML');
        }

        // Create temporary files
        $tmpHtml = tempnam(sys_get_temp_dir(), 'rhtml_') . '.html';
        $tmpPdf  = tempnam(sys_get_temp_dir(), 'rpdf_') . '.pdf';
        file_put_contents($tmpHtml, $html);

        // Find wkhtmltopdf binary — try common Windows paths first, then try system lookup (where/which), then fallback to PATH
        $wkCandidates = [
            'C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
            'C:\\Program Files (x86)\\wkhtmltopdf\\bin\\wkhtmltopdf.exe',
        ];

        // Try platform-specific lookup
        $found = '';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows: use where.exe
            $where = trim(@shell_exec('where wkhtmltopdf 2>NUL'));
            if (!empty($where)) {
                // where may return multiple paths; pick first
                $first = strtok($where, PHP_EOL);
                if ($first) $found = trim($first);
            }
        } else {
            // Unix-like: use which
            $which = trim(@shell_exec('which wkhtmltopdf 2>/dev/null'));
            if (!empty($which)) $found = trim($which);
        }

        if ($found) $wkCandidates[] = $found;
        // finally allow plain command name (rely on PATH)
        $wkCandidates[] = 'wkhtmltopdf';

        $wk = null;
        foreach ($wkCandidates as $c) {
            if (empty($c)) continue;
            // if an absolute path was provided, ensure the file exists
            if (strpos($c, DIRECTORY_SEPARATOR) !== false) {
                if (is_file($c)) { $wk = $c; break; }
                continue;
            }
            // otherwise accept a bare command name and hope PATH resolves it
            $wk = $c; break;
        }

        if (!$wk) {
            @unlink($tmpHtml);
            $msg = 'wkhtmltopdf not found on server. Please install wkhtmltopdf (https://wkhtmltopdf.org/downloads.html) and ensure the executable is on the system PATH or installed to "C:\\Program Files\\wkhtmltopdf\\bin\\wkhtmltopdf.exe". After installing, restart Apache so it picks up PATH changes.';
            return $this->response->setStatusCode(500)->setBody($msg);
        }

        // Build command
        $cmd = escapeshellarg($wk) . ' --page-size A4 --orientation Portrait --margin-top 10mm --margin-bottom 10mm --margin-left 10mm --margin-right 10mm '
             . escapeshellarg($tmpHtml) . ' ' . escapeshellarg($tmpPdf);

        // Execute
        exec($cmd . ' 2>&1', $output, $ret);
        if ($ret !== 0 || !file_exists($tmpPdf)) {
            @unlink($tmpHtml);
            @unlink($tmpPdf);
            $err = is_array($output) ? implode("\n", $output) : 'unknown';
            log_message('error', 'wkhtmltopdf failed: ' . $err);
            return $this->response->setStatusCode(500)->setBody('PDF generation failed: ' . $err);
        }

        // Stream generated PDF back to client with filename
    $filename = 'JV-' . $id . '.pdf';
        $pdfContents = file_get_contents($tmpPdf);
        @unlink($tmpHtml); @unlink($tmpPdf);

        return $this->response->setHeader('Content-Type', 'application/pdf')
                              ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
                              ->setBody($pdfContents);
    }

    /**
     * Convert integer number to English words (supports 0..999,999,999)
     */
    private function numberToWords($n)
    {
        $n = (int)$n;
        if ($n === 0) return 'zero';
        $units = ['', 'one','two','three','four','five','six','seven','eight','nine','ten','eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen'];
        $tens = ['', '', 'twenty','thirty','forty','fifty','sixty','seventy','eighty','ninety'];
        $segments = [];
        $billions = intdiv($n, 1000000000); $n %= 1000000000; if ($billions) $segments[] = $this->numberToWords($billions) . ' billion';
        $millions = intdiv($n, 1000000); $n %= 1000000; if ($millions) $segments[] = $this->numberToWords($millions) . ' million';
        $thousands = intdiv($n, 1000); $n %= 1000; if ($thousands) $segments[] = $this->numberToWords($thousands) . ' thousand';
        $hundreds = intdiv($n, 100); $n %= 100; if ($hundreds) $segments[] = $units[$hundreds] . ' hundred';
        if ($n > 0) {
            if ($n < 20) $segments[] = $units[$n];
            else { $t = intdiv($n,10); $u = $n % 10; $segments[] = $tens[$t] . ($u ? '-' . $units[$u] : ''); }
        }
        return trim(implode(' ', $segments));
    }

    /** Debug insertion endpoint (GET) to isolate form/CSRF issues. Creates a test entry and returns JSON counts. */
    public function debugInsert()
    {
        $db = Database::connect();
        $acctModel = new AccountModel();
        // Ensure at least two accounts exist
        $accounts = $acctModel->orderBy('id','ASC')->findAll(2);
        if (count($accounts) < 2) {
            $this->seedDefaultAccounts();
            $accounts = $acctModel->orderBy('id','ASC')->findAll(2);
        }
        if (count($accounts) < 2) {
            return $this->response->setJSON(['success'=>false,'message'=>'Insufficient accounts to create test entry']);
        }
        $debitId = (int)$accounts[0]['id'];
        $creditId = (int)$accounts[1]['id'];
        $amount = 11.11;
        $db->transBegin();
        try {
            $je = new JournalEntryModel();
            $jeId = $je->insert([
                'entry_date' => date('Y-m-d'),
                'memo' => 'DEBUG INSERT',
                'total_debits' => $amount,
                'total_credits' => $amount,
            ], true);
            $jl = new JournalLineModel();
            $jl->insert(['entry_id'=>$jeId,'account_id'=>$debitId,'description'=>'DEBUG','debit'=>$amount,'credit'=>0]);
            $jl->insert(['entry_id'=>$jeId,'account_id'=>$creditId,'description'=>'DEBUG','debit'=>0,'credit'=>$amount]);
            $db->transCommit();
            $counts = [
                'entries'=>(int)$db->query('SELECT COUNT(*) c FROM journal_entries')->getRowArray()['c'],
                'lines'=>(int)$db->query('SELECT COUNT(*) c FROM journal_lines')->getRowArray()['c'],
                'latest'=>$db->query('SELECT id, entry_date, memo FROM journal_entries ORDER BY id DESC LIMIT 1')->getRowArray(),
            ];
            return $this->response->setJSON(['success'=>true,'data'=>$counts]);
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->response->setJSON(['success'=>false,'message'=>'Debug insert failed: '.$e->getMessage()]);
        }
    }
}
