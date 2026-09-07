<?php

namespace App\Controllers;

use App\Models\Accounting\AccountModel;
use App\Models\Accounting\JournalEntryModel;
use App\Models\Accounting\JournalLineModel;
use App\Models\Accounting\ChequeModel;
use App\Models\Accounting\ChequeLineModel;
use App\Models\VendorModel;
use App\Models\VendorContactModel;
use Config\Database;

class AccountingCheques extends BaseController
{
    private function ensureChequeTables(): void
    {
        $db = Database::connect();
        if (!$db) { return; }
        try {
            // cheques
            try { $db->query('SELECT 1 FROM cheques LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS cheques (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bank_account_id INT NOT NULL,
                    employee_id INT NULL,
                    cheque_number VARCHAR(50) NOT NULL,
                    cheque_date DATE NOT NULL,
                    payee_type VARCHAR(20) NOT NULL,
                    vendor_id INT NULL,
                    contact_id INT NULL,
                    payee_name VARCHAR(190) NULL,
                    delivery_type VARCHAR(20) DEFAULT 'ac_payee',
                    status VARCHAR(20) DEFAULT 'draft',
                    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                    posted_entry_id INT NULL,
                    notes TEXT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    KEY idx_bank (bank_account_id),
                    KEY idx_vendor (vendor_id),
                    KEY idx_date (cheque_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            // cheque_lines
            try { $db->query('SELECT 1 FROM cheque_lines LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS cheque_lines (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cheque_id INT NOT NULL,
                    account_id INT NOT NULL,
                    description VARCHAR(255) NULL,
                    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                    KEY idx_cheque (cheque_id),
                    KEY idx_account (account_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            // cheque_sequences
            try { $db->query('SELECT 1 FROM cheque_sequences LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS cheque_sequences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    bank_account_id INT NOT NULL UNIQUE,
                    prefix VARCHAR(10) NULL,
                    next_number INT NOT NULL DEFAULT 1,
                    suffix VARCHAR(10) NULL,
                    last_issued_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            // vendor_contacts
            try { $db->query('SELECT 1 FROM vendor_contacts LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS vendor_contacts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    vendor_id INT NOT NULL,
                    name VARCHAR(190) NOT NULL,
                    phone VARCHAR(50) NULL,
                    cnic VARCHAR(25) NULL,
                    email VARCHAR(190) NULL,
                    designation VARCHAR(100) NULL,
                    is_primary TINYINT(1) DEFAULT 0,
                    notes TEXT NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    KEY idx_vendor (vendor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            // add cnic to vendor_contacts if missing
            try {
                $res = $db->query("SHOW COLUMNS FROM vendor_contacts LIKE 'cnic'");
                if (!$res->getRowArray()) {
                    $db->query("ALTER TABLE vendor_contacts ADD COLUMN cnic VARCHAR(25) NULL AFTER phone");
                }
            } catch (\Throwable $e) { /* ignore */ }
            // add is_bank to accounts if missing
            try { $db->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'"); $result = $db->getLastQuery(); }
            catch (\Throwable $e) { $result = null; }
            try {
                $exists = false;
                if ($result) {
                    $res = $db->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'");
                    $exists = (bool)$res->getRowArray();
                }
                if (!$exists) {
                    $db->query("ALTER TABLE accounts ADD COLUMN is_bank TINYINT(1) DEFAULT 0 AFTER currency_code");
                }
            } catch (\Throwable $e) { /* ignore */ }
            // add account_number to accounts if missing
            try {
                $res = $db->query("SHOW COLUMNS FROM accounts LIKE 'account_number'");
                if (!$res->getRowArray()) {
                    $db->query("ALTER TABLE accounts ADD COLUMN account_number VARCHAR(50) NULL AFTER name");
                }
            } catch (\Throwable $e) { /* ignore */ }
            // add employee_id to cheques if missing
            try {
                $res = $db->query("SHOW COLUMNS FROM cheques LIKE 'employee_id'");
                if (!$res->getRowArray()) {
                    $db->query("ALTER TABLE cheques ADD COLUMN employee_id INT NULL AFTER bank_account_id");
                }
            } catch (\Throwable $e) { /* ignore */ }
            // add payment_type to cheques if missing (regular / advance)
            try {
                $res = $db->query("SHOW COLUMNS FROM cheques LIKE 'payment_type'");
                if (!$res->getRowArray()) {
                    $db->query("ALTER TABLE cheques ADD COLUMN payment_type VARCHAR(20) DEFAULT 'regular' AFTER delivery_type");
                }
            } catch (\Throwable $e) { /* ignore */ }
            // vendor_advance_adjustments table for tracking advance-to-bill adjustments
            try { $db->query('SELECT 1 FROM vendor_advance_adjustments LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS vendor_advance_adjustments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    vendor_id INT NOT NULL,
                    advance_cheque_id INT NULL,
                    advance_payment_id INT NULL,
                    vendor_bill_id INT NOT NULL,
                    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                    adjustment_date DATE NOT NULL,
                    posted_entry_id INT NULL,
                    notes VARCHAR(255) NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    KEY idx_vendor (vendor_id),
                    KEY idx_cheque (advance_cheque_id),
                    KEY idx_bill (vendor_bill_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            // Ensure index and (best-effort) foreign key for employee_id
            try {
                // Add index if missing
                $idx = $db->query("SHOW INDEX FROM cheques WHERE Key_name = 'idx_cheques_employee'")->getRowArray();
                if (!$idx) {
                    $db->query("ALTER TABLE cheques ADD INDEX idx_cheques_employee (employee_id)");
                }

                // Only attempt FK creation if not present
                $fk = $db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cheques' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_cheques_employee'")->getRowArray();
                if (!$fk) {
                    // Ensure both tables use InnoDB
                    try { $t1 = $db->query("SHOW TABLE STATUS WHERE Name = 'cheques'")->getRowArray(); if ($t1 && isset($t1['Engine']) && strtoupper($t1['Engine']) !== 'INNODB') { $db->query("ALTER TABLE cheques ENGINE=InnoDB"); } } catch (\Throwable $_) {}
                    try { $t2 = $db->query("SHOW TABLE STATUS WHERE Name = 'employees'")->getRowArray(); if ($t2 && isset($t2['Engine']) && strtoupper($t2['Engine']) !== 'INNODB') { $db->query("ALTER TABLE employees ENGINE=InnoDB"); } } catch (\Throwable $_) {}

                    // Check column signed/unsigned compatibility
                    $empCol = $db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='employees' AND COLUMN_NAME='id'")->getRowArray();
                    $cheqCol = $db->query("SELECT COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cheques' AND COLUMN_NAME='employee_id'")->getRowArray();
                    $empType = $empCol['COLUMN_TYPE'] ?? '';
                    $cheqType = $cheqCol['COLUMN_TYPE'] ?? '';
                    // If employees.id is unsigned but cheques.employee_id is not, try to modify cheques.employee_id to unsigned
                    if (stripos($empType, 'unsigned') !== false && stripos($cheqType, 'unsigned') === false) {
                        try {
                            $db->query("ALTER TABLE cheques MODIFY COLUMN employee_id INT(10) UNSIGNED NULL AFTER bank_account_id");
                        } catch (\Throwable $_) { log_message('error','Failed to modify cheques.employee_id to UNSIGNED: '.$_->getMessage()); }
                    }

                    // Clear orphan values (if any) to avoid FK creation failure
                    try {
                        $orphans = $db->query("SELECT DISTINCT employee_id FROM cheques WHERE employee_id IS NOT NULL AND employee_id NOT IN (SELECT id FROM employees)")->getResultArray();
                        if (!empty($orphans)) {
                            $db->query("UPDATE cheques SET employee_id = NULL WHERE employee_id IS NOT NULL AND employee_id NOT IN (SELECT id FROM employees)");
                        }
                    } catch (\Throwable $_) { log_message('error','Failed orphan cleanup: '.$_->getMessage()); }

                    // Finally try to add FK
                    try {
                        $db->query("ALTER TABLE cheques ADD CONSTRAINT fk_cheques_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE SET NULL ON UPDATE CASCADE");
                    } catch (\Throwable $_) {
                        // If it fails, log and continue; FK is optional for app correctness
                        log_message('error','Could not create fk_cheques_employee: '.$_->getMessage());
                    }
                }
            } catch (\Throwable $e) { log_message('error','ensureChequeTables FK/index step failed: '.$e->getMessage()); }
            // vendor payments tables
            try { $db->query('SELECT 1 FROM vendor_payments LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS vendor_payments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    vendor_id INT NOT NULL,
                    cheque_id INT NULL,
                    payment_date DATE NOT NULL,
                    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                    notes VARCHAR(255) NULL,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    KEY idx_vendor (vendor_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            // journal_entries & journal_lines (ensure exist)
            try { $db->query('SELECT 1 FROM journal_entries LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS journal_entries (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    entry_date DATE NOT NULL,
                    memo VARCHAR(255) NULL,
                    currency_code VARCHAR(10) NULL,
                    total_debits DECIMAL(18,2) DEFAULT 0,
                    total_credits DECIMAL(18,2) DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            try { $db->query('SELECT 1 FROM journal_lines LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS journal_lines (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    entry_id INT NOT NULL,
                    account_id INT NOT NULL,
                    description VARCHAR(255) NULL,
                    debit DECIMAL(18,2) DEFAULT 0,
                    credit DECIMAL(18,2) DEFAULT 0,
                    currency_code VARCHAR(10) NULL,
                    fx_rate DECIMAL(18,6) DEFAULT 1,
                    base_amount DECIMAL(18,2) DEFAULT 0
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
            try { $db->query('SELECT 1 FROM vendor_payment_allocations LIMIT 1'); }
            catch (\Throwable $e) {
                $db->query("CREATE TABLE IF NOT EXISTS vendor_payment_allocations (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    payment_id INT NOT NULL,
                    purchase_order_id INT NOT NULL,
                    amount DECIMAL(18,2) NOT NULL DEFAULT 0,
                    KEY idx_payment (payment_id),
                    KEY idx_po (purchase_order_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
        } catch (\Throwable $e) {
            log_message('error', 'ensureChequeTables failed: '.$e->getMessage());
        }
    }

    public function index()
    {
        $this->ensureChequeTables();
        $m = new ChequeModel();
        $rows = $m->getWithJoins(100);
        return view('accounting/cheques/index', ['cheques' => $rows]);
    }

    public function create()
    {
        $this->ensureChequeTables();
        $am = new AccountModel();
        // Prefer is_bank flag; fallback to Asset type named Bank if column missing
        $db = Database::connect();
        $hasIsBank = false;
        try {
            $res = $db->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'")->getRowArray();
            $hasIsBank = (bool)$res;
        } catch (\Throwable $e) { $hasIsBank = false; }
        $select = 'id,name,account_number';
        if ($hasIsBank) {
            $banks = $am->select($select)->where('is_bank', 1)->orderBy('name','ASC')->findAll();
        } else {
            $banks = $am->select($select)->where('type','Asset')->like('name','Bank')->orderBy('name','ASC')->findAll();
        }
        $expenseAccounts = $am->whereIn('type', ['Expense','Cost of Goods Sold','Other Expense'])->orderBy('code','ASC')->findAll();
        $vendors = (new VendorModel())->where('is_active', 1)->orderBy('name','ASC')->findAll();
        // Active employees for payroll cheque payee selection
        $employees = (new \App\Models\EmployeeModel())->where('is_active', 1)->orderBy('first_name','ASC')->findAll();
        // Advance account (Vendor Advances - Asset type)
        $advanceAccounts = $am->whereIn('type', ['Asset','Current Asset'])->like('name','Advance')->orderBy('code','ASC')->findAll();
        // Also include all asset accounts as fallback
        if (empty($advanceAccounts)) {
            $advanceAccounts = $am->whereIn('type', ['Asset','Current Asset'])->orderBy('code','ASC')->findAll();
        }

        return view('accounting/cheques/create', [
            'banks' => $banks,
            'expenseAccounts' => $expenseAccounts,
            'advanceAccounts' => $advanceAccounts,
            'vendors' => $vendors,
            'employees' => $employees,
        ]);
    }

    public function store()
    {
        log_message('info', '=== CHEQUE STORE METHOD CALLED ===');
        log_message('info', 'Request Method: ' . $this->request->getMethod());
        log_message('info', 'Is POST: ' . (strtoupper($this->request->getMethod()) === 'POST' ? 'YES' : 'NO'));
        
        $isAjax = $this->request->isAJAX();
        if (strtoupper($this->request->getMethod()) !== 'POST') { 
            log_message('error', 'Not a POST request, redirecting');
            if ($isAjax) {
                return $this->response->setJSON(['success' => false, 'message' => 'POST required']);
            }
            return redirect()->to('/corelynk/accounting/cheques/create'); 
        }
        $this->ensureChequeTables();

        $bankId = (int)$this->request->getPost('bank_account_id');
        $chequeDate = $this->request->getPost('cheque_date');
        $chequeNumber = trim((string)$this->request->getPost('cheque_number'));
        $deliveryType = (string)$this->request->getPost('delivery_type');
        $payeeType = (string)$this->request->getPost('payee_type');
        $vendorId = (int)($this->request->getPost('vendor_id') ?: 0);
        $contactId = (int)($this->request->getPost('contact_id') ?: 0);
    $employeeId = (int)($this->request->getPost('employee_id') ?: 0);
        $payeeName = trim((string)$this->request->getPost('payee_name'));
        $notes = trim((string)$this->request->getPost('notes'));
        $paymentType = trim((string)($this->request->getPost('payment_type') ?: 'regular'));
        if (!in_array($paymentType, ['regular','advance'])) { $paymentType = 'regular'; }

        $lineAccounts = $this->request->getPost('line_account_id') ?? [];
        $lineAmounts  = $this->request->getPost('line_amount') ?? [];
    $lineDescs    = $this->request->getPost('line_description') ?? [];
    // Bills
    $billIds      = $this->request->getPost('bill_id') ?? [];
    $billAmts     = $this->request->getPost('bill_amount') ?? [];

        // defensive init for bills (avoid undefined variable errors)
        $billAllocations = [];
        $billTotal = 0.0;

        // log POST payload for debugging (sanitized)
        try { log_message('debug', 'Cheque Store POST: ' . json_encode(array_slice($this->request->getPost(),0,50))); } catch (\Throwable $e) {}

        log_message('debug', 'Cheque Store - Bank: '.$bankId.', Date: '.$chequeDate.', PayeeType: '.$payeeType.', VendorId: '.$vendorId);
        log_message('debug', 'Line Accounts: '.json_encode($lineAccounts));
        log_message('debug', 'Line Amounts: '.json_encode($lineAmounts));

        log_message('info', 'Preparing cheque lines and totals');

        if (!$bankId || !$chequeDate) {
            if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Bank and cheque date are required.']);
            return redirect()->back()->withInput()->with('error','Bank and cheque date are required.');
        }
        // Validate bank account exists & flagged as bank (best-effort; do not block if flag missing)
        try {
            $bankRow = Database::connect()->query('SELECT id,is_bank,name FROM accounts WHERE id=?', [$bankId])->getRowArray();
            if (!$bankRow) {
                if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Selected bank account not found.']);
                return redirect()->back()->withInput()->with('error','Selected bank account not found.');
            }
            if (array_key_exists('is_bank', $bankRow) && (int)$bankRow['is_bank'] !== 1) {
                // Warn but allow posting; user may have forgotten to tick flag
                session()->setFlashdata('warning', 'Note: selected account is not marked as Bank.');
            }
        } catch (\Throwable $e) {
            // Log but continue
            log_message('error','Bank validation failed: '.$e->getMessage());
        }
        // Build lines
        $lines = [];
        $total = 0.0;
        for ($i=0; $i < count($lineAccounts); $i++) {
            $accId = (int)$lineAccounts[$i];
            $amt = (float)$lineAmounts[$i];
            $desc = isset($lineDescs[$i]) ? trim((string)$lineDescs[$i]) : '';
            if ($accId && $amt > 0) { $lines[] = ['account_id'=>$accId,'amount'=>$amt,'description'=>$desc]; $total += $amt; }
        }

        // Prepare bill allocations (initialize before logging to avoid undefined variable)
        $billAllocations = [];
        $billTotal = 0.0;
        log_message('info', 'Built lines count=' . count($lines) . ' billAllocations=' . count($billAllocations) . ' total=' . $total);
        // Bills allocations
        for ($i=0; $i < count($billIds); $i++) {
            $bid = (int)($billIds[$i] ?? 0);
            $amt = (float)($billAmts[$i] ?? 0);
            if ($bid && $amt > 0) { $billAllocations[] = ['purchase_order_id'=>$bid,'amount'=>$amt]; $billTotal += $amt; }
        }
        $total += $billTotal;
        if ($total <= 0 || (empty($lines) && empty($billAllocations))) {
            if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Please add at least one line with amount.']);
            return redirect()->back()->withInput()->with('error','Please add at least one line with amount.');
        }
        // Determine payee name
        if ($payeeType === 'vendor') {
            if ($vendorId <= 0) { if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Select a vendor for vendor payee.']); return redirect()->back()->withInput()->with('error','Select a vendor for vendor payee.'); }
            $vendor = (new VendorModel())->find($vendorId);
            $derivedPayee = $vendor ? $vendor['name'] : 'Vendor #'.$vendorId;
        } elseif ($payeeType === 'employee') {
            if ($employeeId <= 0) { if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Select an employee for employee payee.']); return redirect()->back()->withInput()->with('error','Select an employee for employee payee.'); }
            $emp = (new \App\Models\EmployeeModel())->find($employeeId);
            $derivedPayee = $emp ? trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')) : 'Employee #'.$employeeId;
        } elseif ($payeeType === 'person') {
            if ($payeeName === '') { if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Enter payee name for person.']); return redirect()->back()->withInput()->with('error','Enter payee name for person.'); }
            $derivedPayee = $payeeName;
        } else { // self
            $derivedPayee = 'Self';
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            // Autogenerate cheque number if left empty or contains 'Auto'
            $autoRequested = ($chequeNumber === '' || strtolower($chequeNumber) === 'auto' || strtolower($chequeNumber) === 'auto/manual');
            if ($autoRequested) {
                $seq = $db->query('SELECT * FROM cheque_sequences WHERE bank_account_id = ?', [$bankId])->getRowArray();
                if (!$seq) {
                    $db->query('INSERT INTO cheque_sequences (bank_account_id,next_number) VALUES (?,1)', [$bankId]);
                    $seq = ['prefix'=>null,'next_number'=>1,'suffix'=>null];
                }
                $num = (int)($seq['next_number'] ?? 1);
                $chequeNumber = ($seq['prefix'] ?? '') . $num . ($seq['suffix'] ?? '');
                $db->query('UPDATE cheque_sequences SET next_number = next_number + 1, last_issued_at = NOW() WHERE bank_account_id = ?', [$bankId]);
            }
            // Ensure uniqueness per bank (simple retry if auto; error if manual)
            $dupCheck = $db->query('SELECT id FROM cheques WHERE bank_account_id = ? AND cheque_number = ? LIMIT 1', [$bankId,$chequeNumber])->getRowArray();
            if ($dupCheck) {
                if ($autoRequested) {
                    // Retry by advancing sequence until unique (max 5 attempts)
                    $attempts = 0;
                    while ($attempts < 5) {
                        $seq = $db->query('SELECT * FROM cheque_sequences WHERE bank_account_id = ?', [$bankId])->getRowArray();
                        $num = (int)($seq['next_number'] ?? 1);
                        $candidate = ($seq['prefix'] ?? '') . $num . ($seq['suffix'] ?? '');
                        $exists = $db->query('SELECT id FROM cheques WHERE bank_account_id = ? AND cheque_number = ? LIMIT 1', [$bankId,$candidate])->getRowArray();
                        if (!$exists) {
                            $chequeNumber = $candidate;
                            $db->query('UPDATE cheque_sequences SET next_number = next_number + 1, last_issued_at = NOW() WHERE bank_account_id = ?', [$bankId]);
                            break;
                        }
                        // advance sequence and retry
                        $db->query('UPDATE cheque_sequences SET next_number = next_number + 1 WHERE bank_account_id = ?', [$bankId]);
                        $attempts++;
                    }
                    if ($attempts >= 5) {
                        throw new \RuntimeException('Failed to generate unique cheque number after retries.');
                    }
                } else {
                    return redirect()->back()->withInput()->with('error','Cheque number already used for this bank. Choose another or leave blank for Auto.');
                }
            }
            // Insert cheque header
            $cm = new ChequeModel();
            $chequeId = $cm->insert([
                'bank_account_id' => $bankId,
                'employee_id' => $employeeId ?: null,
                'cheque_number'   => $chequeNumber,
                'cheque_date'     => $chequeDate,
                'payee_type'      => $payeeType,
                'vendor_id'       => $vendorId ?: null,
                'contact_id'      => $contactId ?: null,
                'payee_name'      => $payeeType === 'person' ? $payeeName : $derivedPayee,
                'delivery_type'   => $deliveryType ?: 'ac_payee',
                'payment_type'    => $paymentType,
                'status'          => 'posted',
                'amount'          => $total,
                'notes'           => $notes,
            ], true);

            if (!$chequeId) {
                log_message('error', 'ChequeModel::insert returned falsy chequeId. Last DB error: ' . print_r(Database::connect()->error() ?? [], true));
                throw new \RuntimeException('Failed to insert cheque header');
            }
            log_message('info', 'Inserted cheque id=' . $chequeId);

            // Insert cheque lines
            $clm = new ChequeLineModel();
            if ($paymentType === 'advance') {
                // For advance, we'll insert lines after determining the advance account
                // (cheque_lines will be inserted in the journal section below)
            } else {
                foreach ($lines as $l) {
                    $clm->insert([
                        'cheque_id' => $chequeId,
                        'account_id'=> $l['account_id'],
                        'description'=> $l['description'],
                        'amount'    => $l['amount'],
                    ]);
                }
            }
            // Create journal entry
            $jem = new JournalEntryModel();
            $jeId = $jem->insert([
                'entry_date' => $chequeDate,
                'memo' => 'Cheque #'.$chequeNumber.' - '.$derivedPayee,
                'currency_code' => 'PKR',
                'total_debits' => $total,
                'total_credits'=> $total,
            ], true);

            if (!$jeId) {
                $dberr = Database::connect()->error() ?? [];
                log_message('error', 'JournalEntryModel::insert returned falsy jeId. DB error: ' . print_r($dberr, true));
                // try to capture last query if available
                try { $last = Database::connect()->getLastQuery(); log_message('error', 'Last query: ' . $last); } catch (\Throwable $_) {}
                throw new \RuntimeException('Failed to create journal entry');
            }
            log_message('info', 'Created journal entry id=' . $jeId);
            $jlm = new JournalLineModel();
            // For advance cheques, debit Vendor Advances account instead of expense accounts
            if ($paymentType === 'advance') {
                // Find or use the Vendor Advances account
                $advAcctRow = $db->query("SELECT id FROM accounts WHERE (name LIKE '%Vendor Advance%' OR name LIKE '%Advance%Vendor%' OR name LIKE '%Supplier Advance%') AND type IN ('Asset','Current Asset') ORDER BY id ASC LIMIT 1")->getRowArray();
                if (!$advAcctRow) {
                    // Create Vendor Advances account if not found
                    $db->query("INSERT INTO accounts (name, code, type, is_active, created_at) VALUES ('Vendor Advances', '1200', 'Asset', 1, NOW())");
                    $advAcctId = (int)$db->insertID();
                } else {
                    $advAcctId = (int)$advAcctRow['id'];
                }
                // Debit Vendor Advances for full amount
                $jlm->insert([
                    'entry_id' => $jeId,
                    'account_id' => $advAcctId,
                    'description' => 'Advance to ' . $derivedPayee . ' via Cheque #' . $chequeNumber,
                    'debit' => $total,
                    'credit'=> 0,
                    'currency_code' => 'PKR',
                    'fx_rate' => 1,
                    'base_amount' => $total,
                ]);
                // Also insert any custom line descriptions for record but no journal impact
                foreach ($lines as $l) {
                    $clm->insert([
                        'cheque_id' => $chequeId,
                        'account_id'=> $advAcctId,
                        'description'=> $l['description'] ?: ('Advance - ' . $derivedPayee),
                        'amount'    => $l['amount'],
                    ]);
                }
            } else {
                // Regular cheque: debit expense accounts as before
                foreach ($lines as $l) {
                    $jlm->insert([
                        'entry_id' => $jeId,
                        'account_id' => $l['account_id'],
                        'description' => $l['description'] ?: ('Cheque #'.$chequeNumber),
                        'debit' => $l['amount'],
                        'credit'=> 0,
                        'currency_code' => 'PKR',
                        'fx_rate' => 1,
                        'base_amount' => $l['amount'],
                    ]);
                }
            }
            // Bills: debit Accounts Payable for each allocation and create vendor payment records
            if (!empty($billAllocations)) {
                $apRow = $db->query("SELECT id FROM accounts WHERE type='Liability' AND (name LIKE '%Accounts Payable%' OR code='2000') ORDER BY id ASC LIMIT 1")->getRowArray();
                if (!$apRow) { throw new \RuntimeException('Accounts Payable account not found.'); }
                $apId = (int)$apRow['id'];
                foreach ($billAllocations as $ba) {
                    $amt = (float)$ba['amount'];
                    $desc = 'Bill Payment PO#'.$ba['purchase_order_id'].' via Cheque #'.$chequeNumber;
                    $jlm->insert([
                        'entry_id' => $jeId,
                        'account_id' => $apId,
                        'description' => $desc,
                        'debit' => $amt,
                        'credit'=> 0,
                        'currency_code' => 'PKR',
                        'fx_rate' => 1,
                        'base_amount' => $amt,
                    ]);
                }
                // vendor payment header
                $db->query('INSERT INTO vendor_payments (vendor_id, cheque_id, payment_date, amount, notes, created_at) VALUES (?,?,?,?,?,NOW())', [
                    $vendorId, $chequeId, $chequeDate, $billTotal, 'Cheque #'.$chequeNumber, 
                ]);
                $paymentId = (int)$db->insertID();
                foreach ($billAllocations as $ba) {
                    $db->query('INSERT INTO vendor_payment_allocations (payment_id, purchase_order_id, amount) VALUES (?,?,?)', [
                        $paymentId, $ba['purchase_order_id'], $ba['amount']
                    ]);
                }
            }
            // Credit bank for total amount
            $jlm->insert([
                'entry_id' => $jeId,
                'account_id' => $bankId,
                'description' => 'Cheque #'.$chequeNumber,
                'debit' => 0,
                'credit'=> $total,
                'currency_code' => 'PKR',
                'fx_rate' => 1,
                'base_amount' => $total,
            ]);
            // Link cheque to journal
            $cm->update($chequeId, ['posted_entry_id' => $jeId]);

            // For advance cheques: create a vendor_payment record with payment_type='advance'
            if ($paymentType === 'advance' && $vendorId > 0) {
                $db->query('INSERT INTO vendor_payments (vendor_id, cheque_id, payment_date, payment_method, payment_type, amount, source_account_id, posted_entry_id, notes, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())', [
                    $vendorId, $chequeId, $chequeDate, 'cheque', 'advance', $total, $bankId, $jeId, 'Advance Cheque #'.$chequeNumber, 'posted',
                ]);
            }

            if ($db->transStatus() === false) { throw new \RuntimeException('DB transaction failed'); }
            $db->transCommit();
            log_message('info', 'Cheque store completed successfully. chequeId=' . $chequeId . ' jeId=' . $jeId);
            if ($isAjax) return $this->response->setJSON(['success' => true, 'message' => 'Cheque posted and journal created (Entry #'.$jeId.').', 'redirect' => '/corelynk/accounting/cheques']);
            return redirect()->to('/corelynk/accounting/cheques')->with('success','Cheque posted and journal created (Entry #'.$jeId.').');
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Cheque store failed: '.$e->getMessage());
            if ($isAjax) return $this->response->setJSON(['success' => false, 'message' => 'Failed to save cheque: '.$e->getMessage()]);
            return redirect()->back()->withInput()->with('error','Failed to save cheque: '.$e->getMessage());
        }
    }

    public function vendorContacts($vendorId)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['error'=>'AJAX required']);
        }
        try {
            $m = new VendorContactModel();
            $rows = $m->getByVendor((int)$vendorId);
            return $this->response->setJSON(['success'=>true,'data'=>$rows]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);
        }
    }

    /** AJAX: Open vendor bills for a vendor with remaining balances */
    public function vendorBills($vendorId)
    {
        if (!$this->request->isAJAX()) { return $this->response->setJSON(['error'=>'AJAX required']); }
        try {
            $db = Database::connect();
            // First, get all confirmed bills for this vendor
            $bills = $db->query("
                SELECT 
                    vb.id,
                    COALESCE(vb.bill_number, CONCAT('VB-', vb.id)) as bill_number,
                    DATE_FORMAT(vb.bill_date, '%Y-%m-%d') as bill_date,
                    vb.total_amount as total
                FROM vendor_bills vb
                WHERE vb.vendor_id = ? AND LOWER(COALESCE(vb.status,'')) IN ('confirmed','partially_paid')
                ORDER BY vb.bill_date DESC, vb.id DESC
            ", [(int)$vendorId])->getResultArray();
            
            // For each bill, calculate paid amount from allocations and fetch line items
            foreach ($bills as &$bill) {
                // Get total allocated amount from all posted payments
                $paidResult = $db->query("
                    SELECT COALESCE(SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)), 0) as paid_amount
                    FROM vendor_payment_allocations vpa
                    JOIN vendor_payments vp ON vp.id = vpa.payment_id
                    WHERE vpa.vendor_bill_id = ? AND vp.status = 'posted'
                ", [$bill['id']])->getRowArray();
                
                $paid = (float)($paidResult['paid_amount'] ?? 0);
                $balance = max(0.0, (float)$bill['total'] - $paid);
                
                $bill['paid'] = $paid;
                $bill['balance'] = $balance;

                // Fetch product lines for this bill
                $lines = $db->query("
                    SELECT
                        vbl.product_id,
                        vbl.variant_id,
                        p.name          AS product_name,
                        COALESCE(p.code, p.sku) AS product_code,
                        p.images        AS product_images,
                        pv.name         AS variant_name,
                        pv.art_number   AS variant_code,
                        pv.attributes   AS variant_attrs,
                        pv.image        AS variant_image,
                        vbl.qty,
                        vbl.unit_price,
                        vbl.line_total
                    FROM vendor_bill_lines vbl
                    LEFT JOIN products p ON p.id = vbl.product_id
                    LEFT JOIN product_variants pv ON pv.id = vbl.variant_id
                    WHERE vbl.vendor_bill_id = ?
                    ORDER BY vbl.id ASC
                ", [$bill['id']])->getResultArray();

                $baseUrl = base_url();
                $bill['lines'] = array_map(function($ln) use ($baseUrl) {
                    $thumb = null;
                    $vImg  = trim((string)($ln['variant_image'] ?? ''));
                    if ($vImg !== '') {
                        $p1 = FCPATH . 'uploads/variants/' . ltrim($vImg, '/');
                        if (is_file($p1)) $thumb = rtrim($baseUrl,'/') . '/uploads/variants/' . ltrim($vImg, '/');
                    }
                    if (!$thumb) {
                        $raw = $ln['product_images'] ?? '';
                        $f   = '';
                        $d   = json_decode((string)$raw, true);
                        if (is_array($d) && !empty($d[0])) {
                            $first = $d[0];
                            $f = is_array($first) ? (string)($first['path'] ?? $first['file'] ?? $first['url'] ?? $first['name'] ?? '') : (string)$first;
                        } elseif (is_string($raw) && trim($raw) !== '') {
                            $f = trim($raw);
                        }
                        if ($f !== '') {
                            $p2 = FCPATH . 'uploads/products/' . ltrim($f, '/');
                            if (is_file($p2)) $thumb = rtrim($baseUrl,'/') . '/uploads/products/' . ltrim($f, '/');
                        }
                    }
                    $attrs = [];
                    if (!empty($ln['variant_attrs'])) {
                        $decoded = json_decode((string)$ln['variant_attrs'], true);
                        if (is_array($decoded)) $attrs = $decoded;
                    }
                    return [
                        'product_name'  => (string)($ln['product_name'] ?? ''),
                        'product_code'  => (string)($ln['product_code'] ?? ''),
                        'variant_name'  => (string)($ln['variant_name'] ?? ''),
                        'variant_code'  => (string)($ln['variant_code'] ?? ''),
                        'variant_attrs' => $attrs,
                        'is_variant'    => !empty($ln['variant_id']),
                        'thumbnail_url' => $thumb,
                        'qty'           => (float)$ln['qty'],
                        'unit_price'    => (float)$ln['unit_price'],
                        'line_total'    => (float)$ln['line_total'],
                    ];
                }, $lines);
                
                log_message('debug', 'vendorBills bill_id=' . $bill['id'] . ' total=' . $bill['total'] . ' paid=' . $paid . ' balance=' . $balance);
            }
            
            return $this->response->setJSON(['success'=>true,'data'=>$bills]);
        } catch (\Throwable $e) {
            log_message('error', 'vendorBills error: ' . $e->getMessage());
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage(),'data'=>[]]);
        }
    }

    /**
     * Development helper: run schema ensure and return a short report.
     * Accessible only in development environment or via CLI.
     */
    public function ensureSchemaReport()
    {
        // Restrict to development or CLI to avoid exposing DB operations in production
        if (!(defined('ENVIRONMENT') && ENVIRONMENT === 'development') && !$this->request->isCLI()) {
            return $this->response->setStatusCode(403)->setBody('Forbidden');
        }

        $this->ensureChequeTables();
        $db = Database::connect();
        $report = [];
        try {
            $report['employee_column'] = $db->query("SHOW COLUMNS FROM cheques LIKE 'employee_id'")->getRowArray() ?: null;
        } catch (\Throwable $e) { $report['employee_column'] = null; }
        try {
            $report['index_exists'] = (bool)$db->query("SHOW INDEX FROM cheques WHERE Key_name = 'idx_cheques_employee'")->getRowArray();
        } catch (\Throwable $e) { $report['index_exists'] = false; }
        try {
            $fk = $db->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cheques' AND CONSTRAINT_TYPE = 'FOREIGN KEY' AND CONSTRAINT_NAME = 'fk_cheques_employee'")->getRowArray();
            $report['fk_exists'] = (bool)$fk;
        } catch (\Throwable $e) { $report['fk_exists'] = false; }
        try {
            $t1 = $db->query("SHOW TABLE STATUS WHERE Name = 'cheques'")->getRowArray();
            $t2 = $db->query("SHOW TABLE STATUS WHERE Name = 'employees'")->getRowArray();
            $report['cheques_engine'] = $t1['Engine'] ?? null;
            $report['employees_engine'] = $t2['Engine'] ?? null;
        } catch (\Throwable $e) { $report['cheques_engine'] = $report['employees_engine'] = null; }
        try {
            $or = $db->query("SELECT COUNT(*) AS cnt FROM cheques WHERE employee_id IS NOT NULL AND employee_id NOT IN (SELECT id FROM employees)")->getRowArray();
            $report['orphan_count'] = (int)($or['cnt'] ?? 0);
        } catch (\Throwable $e) { $report['orphan_count'] = -1; }

        return view('accounting/cheques/ensure_schema_report', ['report' => $report]);
    }

    public function view($id)
    {
        $db = Database::connect();
        $c = $db->query('SELECT c.*, a.name as bank_name, a.account_number, v.name as vendor_name FROM cheques c LEFT JOIN accounts a ON a.id=c.bank_account_id LEFT JOIN vendors v ON v.id=c.vendor_id WHERE c.id=?', [(int)$id])->getRowArray();
        if (!$c) { 
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Cheque not found'); 
        }
        $lines = $db->query('SELECT cl.*, acc.name as account_name FROM cheque_lines cl LEFT JOIN accounts acc ON acc.id=cl.account_id WHERE cl.cheque_id=?', [$id])->getResultArray();
        
        $amount = number_format((float)$c['amount'], 2);
        $amountWords = $this->amountToWords((float)$c['amount']) . ' Rupees Only';
        
        return view('accounting/cheques/view', [
            'cheque' => $c,
            'lines' => $lines,
            'amount' => $amount,
            'amountWords' => $amountWords,
        ]);
    }

    /**
     * Simple UI for non-accountants to view balances for a vendor or employee.
     * GET: renders the selection UI with lists.
     */
    public function balances()
    {
        $this->ensureChequeTables();
        $db = Database::connect();
        // vendors
        $vendors = (new VendorModel())->where('is_active',1)->orderBy('name','ASC')->findAll();
        // employees
        $employees = (new \App\Models\EmployeeModel())->where('is_active',1)->orderBy('first_name','ASC')->findAll();

        return view('accounting/cheques/balances', [
            'vendors' => $vendors,
            'employees' => $employees,
        ]);
    }

    /**
     * AJAX endpoint: returns paid and owed amounts for a vendor or employee.
     * Query params: type=vendor|employee, id=<int>
     */
    public function balanceData()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success'=>false,'message'=>'AJAX required']);
        }
        $type = $this->request->getGet('type');
        $idRaw = trim((string) ($this->request->getGet('id') ?? ''));
        if (!in_array($type, ['vendor','employee']) || $idRaw === '' || !preg_match('/^-?\d+$/', $idRaw)) {
            return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
        }
        $id = (int) $idRaw;
        if ($type === 'employee') {
            if ($id <= 0) {
                return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
            }
        } else {
            // Vendor: allow id=0 only if that vendor exists.
            if ($id < 0) {
                return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
            }
            $vendor = (new VendorModel())->where('id', $id)->first();
            if (! $vendor) {
                return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
            }
        }
        $db = Database::connect();
        try {
            if ($type === 'vendor') {
                // Get total amount from all confirmed vendor bills for this vendor
                $billsRow = $db->query(
                    'SELECT COALESCE(SUM(total_amount), 0) AS total_bills FROM vendor_bills WHERE vendor_id = ? AND status = "confirmed"',
                    [$id]
                )->getRowArray();
                $totalBills = (float)($billsRow['total_bills'] ?? 0);

                // Paid via allocations (applied to specific bills)
                $paidRow = $db->query(
                    'SELECT COALESCE(SUM(COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0)), 0) AS paid_amount 
                     FROM vendor_payment_allocations vpa
                     JOIN vendor_payments vp ON vp.id = vpa.payment_id AND vp.status = "posted"
                     JOIN vendor_bills vb ON vb.id = COALESCE(vpa.vendor_bill_id, vpa.purchase_order_id)
                     WHERE vb.vendor_id = ?',
                    [$id]
                )->getRowArray();
                $allocatedPaid = (float)($paidRow['paid_amount'] ?? 0);

                // Unallocated posted settlement payments (reduce pending even if not tied to bills)
                $unallocatedRow = $db->query(
                    'SELECT COALESCE(SUM(GREATEST(vp.amount - COALESCE(alloc.total_alloc, 0), 0)), 0) AS unallocated_amount
                     FROM vendor_payments vp
                     LEFT JOIN (
                                SELECT payment_id, SUM(COALESCE(NULLIF(amount_allocated, 0), amount, 0)) AS total_alloc
                        FROM vendor_payment_allocations
                        GROUP BY payment_id
                     ) alloc ON alloc.payment_id = vp.id
                     WHERE vp.vendor_id = ? AND vp.status = "posted" AND vp.payment_type != "advance"',
                    [$id]
                )->getRowArray();
                $unallocatedPaid = (float)($unallocatedRow['unallocated_amount'] ?? 0);
                $paid = $allocatedPaid + $unallocatedPaid;

                // Get vendor advance balance (available advance for this vendor)
                $advanceModel = new \App\Models\VendorPaymentModel();
                $advance = $advanceModel->getVendorAdvanceBalance($id);

                // Owed = total bills - paid
                $owed = max(0.0, $totalBills - $paid);
                
                // --- RICH METADATA FOR ENHANCED UI ---
                // Total confirmed bills count
                $countRow = $db->query('SELECT COUNT(*) as cnt FROM vendor_bills WHERE vendor_id = ? AND status = "confirmed"', [$id])->getRowArray();
                $billsCount = (int)($countRow['cnt'] ?? 0);
                
                // Pending bills count (balance > 0)
                $pendingRow = $db->query('SELECT COUNT(*) as cnt FROM vendor_bills WHERE vendor_id = ? AND status = "confirmed" AND balance > 0', [$id])->getRowArray();
                $pendingCount = (int)($pendingRow['cnt'] ?? 0);
                
                // Overdue amount
                $overdueRow = $db->query('SELECT COALESCE(SUM(balance), 0) as overdue FROM vendor_bills WHERE vendor_id = ? AND status = "confirmed" AND balance > 0 AND due_date < NOW()', [$id])->getRowArray();
                $overdueAmt = (float)($overdueRow['overdue'] ?? 0);
                
                // Last payment info
                $lastPayRow = $db->query('SELECT payment_date, amount FROM vendor_payments WHERE vendor_id = ? AND status = "posted" ORDER BY payment_date DESC, id DESC LIMIT 1', [$id])->getRowArray();
                
                // Breakdown: Total paid from advance vs from cash/bank
                $advancePaidRow = $db->query(
                    'SELECT COALESCE(SUM(vp.advance_amount), 0) AS total_from_advance
                     FROM vendor_payments vp
                     WHERE vp.vendor_id = ? AND vp.status = "posted" AND vp.payment_type <> "advance"',
                    [$id]
                )->getRowArray();
                $totalFromAdvance = (float)($advancePaidRow['total_from_advance'] ?? 0);
                $totalFromCashBank = max(0.0, $paid - $totalFromAdvance);
                
                // Detailed Breakdown (Direct vs Allocated)
                $details = [
                    'payments_direct' => $unallocatedPaid,
                    'payments_allocated' => $allocatedPaid,
                    'paid_from_advance' => $totalFromAdvance,
                    'paid_from_cash_bank' => $totalFromCashBank,
                    'bills_count' => $billsCount,
                    'pending_bills' => $pendingCount,
                    'overdue_amount' => $overdueAmt,
                    'last_payment_date' => $lastPayRow['payment_date'] ?? null,
                    'last_payment_amount' => (float)($lastPayRow['amount'] ?? 0),
                    'health_percentage' => ($totalBills > 0) ? round(($paid / $totalBills) * 100) : 100
                ];

                $found = ($paid > 0 || $totalBills > 0 || $advance > 0);
                $message = $found ? 'Vendor balance reflects confirmed bills and payments.' : 'No transactions found for this vendor.';

                return $this->response->setJSON([
                    'success' => true,
                    'type' => 'vendor',
                    'paid' => $paid,
                    'owed' => $owed,
                    'advance' => $advance,
                    'total_bills' => $totalBills,
                    'details' => $details,
                    'found' => $found,
                    'message' => $message
                ]);
            }

            // employee
            $paidRow = $db->query("SELECT COALESCE(SUM(amount),0) amt FROM cheques WHERE payee_type='employee' AND employee_id = ? AND status = 'posted'", [$id])->getRowArray();
            $paid = (float)($paidRow['amt'] ?? 0);
            // No reliable 'owed' data available in the system for employees; show 0 and flag as N/A
            $owed = 0.0;
            return $this->response->setJSON([
                'success'=>true,
                'type'=>'employee',
                'paid'=>$paid,
                'owed'=>$owed,
                'note'=>'Owed amount not tracked for employees in this system.',
                'details' => [
                    'total_paid' => $paid,
                    'last_payment' => $db->query("SELECT cheque_date FROM cheques WHERE payee_type='employee' AND employee_id = ? AND status='posted' ORDER BY cheque_date DESC LIMIT 1", [$id])->getRowArray()['cheque_date'] ?? null
                ]
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    /**
     * AJAX: return paginated, date-ordered list of payment entries for a vendor or employee.
     * Params: type=vendor|employee, id, start_date (YYYY-MM-DD), end_date (YYYY-MM-DD), page, per_page
     */
    public function balanceEntries()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success'=>false,'message'=>'AJAX required']);
        }
        $type = $this->request->getGet('type');
        $idRaw = trim((string) ($this->request->getGet('id') ?? ''));
        if (!in_array($type, ['vendor','employee']) || $idRaw === '' || !preg_match('/^-?\d+$/', $idRaw)) {
            return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
        }
        $id = (int) $idRaw;
        if ($type === 'employee') {
            if ($id <= 0) {
                return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
            }
        } else {
            if ($id < 0) {
                return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
            }
            $vendor = (new VendorModel())->where('id', $id)->first();
            if (! $vendor) {
                return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
            }
        }
        $start = $this->request->getGet('start_date');
        $end = $this->request->getGet('end_date');
        $page = max(1, (int)$this->request->getGet('page') ?: 1);
        $per = max(5, min(200, (int)$this->request->getGet('per_page') ?: 50));

        $db = Database::connect();
        try {
            // Normalize dates
            if ($start && $end) {
                $startDate = date('Y-m-d', strtotime($start));
                $endDate = date('Y-m-d', strtotime($end));
            } else {
                // default: last 6 months
                $endDate = date('Y-m-d');
                $startDate = date('Y-m-d', strtotime('-6 months'));
            }

            $rows = [];
            // Cheques (posted)
            if ($type === 'vendor') {
                $cheques = $db->query("SELECT c.id, c.cheque_date AS dt, c.amount, 'cheque' AS src, c.cheque_number, c.bank_account_id, c.posted_entry_id
                    FROM cheques c
                    LEFT JOIN vendor_payments vp ON vp.cheque_id = c.id AND vp.status = 'posted'
                    WHERE c.payee_type='vendor' AND c.vendor_id = ? AND c.status='posted' AND c.cheque_date BETWEEN ? AND ? AND vp.id IS NULL", [$id, $startDate, $endDate])->getResultArray();
            } else {
                $cheques = $db->query("SELECT id, cheque_date AS dt, amount, 'cheque' AS src, cheque_number, bank_account_id, posted_entry_id FROM cheques WHERE payee_type='employee' AND employee_id = ? AND status='posted' AND cheque_date BETWEEN ? AND ?", [$id, $startDate, $endDate])->getResultArray();
            }
            foreach ($cheques as $c) {
                $journalId = (int)($c['posted_entry_id'] ?? 0);
                $rows[] = [
                    'date'=>$c['dt'],
                    'amount'=>(float)$c['amount'],
                    'type'=>'Cheque',
                    'source'=>'cheque',
                    'ref_id'=>$c['id'],
                    'label'=>'Cheque #'.($c['cheque_number'] ?? $c['id']),
                    'url'=>base_url('/accounting/cheques/'.$c['id'].'/view'),
                    'journal_id'=>$journalId,
                    'journal_url'=>$journalId > 0 ? base_url('/accounting/journals/view/'.$journalId) : null,
                ];
            }

            // Vendor payments (allocated payments)
            if ($type === 'vendor') {
                $vps = $db->query("SELECT p.id, p.payment_date AS dt, p.amount, p.cheque_id, p.posted_entry_id, 
                    GROUP_CONCAT(DISTINCT b.bill_number ORDER BY b.bill_number SEPARATOR ', ') as bills 
                    FROM vendor_payments p 
                    LEFT JOIN vendor_payment_allocations a ON a.payment_id = p.id 
                    LEFT JOIN vendor_bills b ON b.id = COALESCE(a.vendor_bill_id, a.purchase_order_id) 
                    WHERE p.vendor_id = ? AND p.status = 'posted' AND p.payment_date BETWEEN ? AND ?
                    GROUP BY p.id", [$id, $startDate, $endDate])->getResultArray();
                foreach ($vps as $p) {
                    $url = ($p['cheque_id'] ? base_url('/accounting/cheques/'.$p['cheque_id'].'/view') : null);
                    $journalId = (int)($p['posted_entry_id'] ?? 0);
                    $rows[] = [
                        'date'=>$p['dt'],
                        'amount'=>(float)$p['amount'],
                        'type'=>'Payment',
                        'source'=>'vendor_payment',
                        'ref_id'=>$p['id'],
                        'label'=>'Payment #'.$p['id'],
                        'bills'=>$p['bills'] ?: 'Unallocated',
                        'url'=>$url,
                        'journal_id'=>$journalId,
                        'journal_url'=>$journalId > 0 ? base_url('/accounting/journals/view/'.$journalId) : null,
                    ];
                }
            }

            // Receipts (cash receipts) - if employee type we may consider receipts table
            try {
                $receipts = $db->query("SELECT id, receipt_date AS dt, amount FROM receipts WHERE (vendor_id = ? OR employee_id = ?) AND receipt_date BETWEEN ? AND ?", [$id, $id, $startDate, $endDate])->getResultArray();
                foreach ($receipts as $r) {
                    $rows[] = ['date'=>$r['dt'],'amount'=>(float)$r['amount'],'type'=>'Receipt','source'=>'receipt','ref_id'=>$r['id'],'label'=>'Receipt #'.$r['id'],'url'=>base_url('/accounting/receipts/'.$r['id'])];
                }
            } catch (\Throwable $_) {
                // receipts table may not exist or have these columns; ignore
            }

            // Sort by date asc
            usort($rows, function($a,$b){ return strcmp($a['date'],$b['date']); });

            // Group by date and paginate by flattened list
            $total = count($rows);
            $startIndex = ($page - 1) * $per;
            $paged = array_slice($rows, $startIndex, $per);

            // Group paged by date
            $grouped = [];
            foreach ($paged as $r) {
                $d = $r['date'] ? date('Y-m-d', strtotime($r['date'])) : 'Unknown';
                if (!isset($grouped[$d])) $grouped[$d] = ['date'=>$d,'items'=>[],'subtotal'=>0.0];
                $grouped[$d]['items'][] = $r;
                $grouped[$d]['subtotal'] += (float)$r['amount'];
            }

            return $this->response->setJSON(['success'=>true,'data'=>array_values($grouped),'total'=>$total,'page'=>$page,'per_page'=>$per,'startDate'=>$startDate,'endDate'=>$endDate]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    /**
     * AJAX: return a concise summary for a single entry (cheque, vendor_payment, receipt)
     * Params: source=cheque|vendor_payment|receipt, id
     */
    public function entrySummary()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success'=>false,'message'=>'AJAX required']);
        }
        $source = $this->request->getGet('source');
        $id = (int)$this->request->getGet('id');
        if (!$source || $id <= 0) {
            return $this->response->setJSON(['success'=>false,'message'=>'Invalid parameters']);
        }
        $db = Database::connect();
        try {
            if ($source === 'cheque') {
                $c = $db->query('SELECT c.id,c.cheque_number,c.cheque_date,c.amount,c.payee_type,c.payee_name,a.name as bank_name,v.name as vendor_name FROM cheques c LEFT JOIN accounts a ON a.id=c.bank_account_id LEFT JOIN vendors v ON v.id=c.vendor_id WHERE c.id=?', [$id])->getRowArray();
                if (!$c) return $this->response->setJSON(['success'=>false,'message'=>'Not found']);
                $lines = $db->query('SELECT cl.*, acc.name as account_name FROM cheque_lines cl LEFT JOIN accounts acc ON acc.id=cl.account_id WHERE cl.cheque_id=?', [$id])->getResultArray();
                // return limited data
                return $this->response->setJSON(['success'=>true,'data'=>[
                    'type' => 'cheque',
                    'cheque_number' => $c['cheque_number'] ?? null,
                    'date' => $c['cheque_date'] ?? null,
                    'amount' => (float)($c['amount'] ?? 0),
                    'payee' => $c['payee_name'] ?? ($c['vendor_name'] ?? null),
                    'bank' => $c['bank_name'] ?? null,
                    'lines' => $lines,
                ]]);
            }
            if ($source === 'vendor_payment') {
                $p = $db->query('SELECT id, vendor_id, cheque_id, payment_date, amount, notes FROM vendor_payments WHERE id=?', [$id])->getRowArray();
                if (!$p) return $this->response->setJSON(['success'=>false,'message'=>'Not found']);
                $vendor = $db->query('SELECT id,name FROM vendors WHERE id=?', [(int)$p['vendor_id']])->getRowArray();
                return $this->response->setJSON(['success'=>true,'data'=>[
                    'type' => 'vendor_payment',
                    'date' => $p['payment_date'] ?? null,
                    'amount' => (float)($p['amount'] ?? 0),
                    'payee' => $vendor['name'] ?? null,
                    'notes' => $p['notes'] ?? null,
                    'cheque_id' => $p['cheque_id'] ?? null,
                ]]);
            }
            if ($source === 'receipt') {
                $r = $db->query('SELECT id, receipt_date, amount, notes FROM receipts WHERE id=?', [$id])->getRowArray();
                if (!$r) return $this->response->setJSON(['success'=>false,'message'=>'Not found']);
                return $this->response->setJSON(['success'=>true,'data'=>[
                    'type' => 'receipt',
                    'date' => $r['receipt_date'] ?? null,
                    'amount' => (float)($r['amount'] ?? 0),
                    'notes' => $r['notes'] ?? null,
                ]]);
            }
            return $this->response->setJSON(['success'=>false,'message'=>'Unsupported source']);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    public function pdf($id)
    {
        // Load dompdf autoloader
        require_once ROOTPATH . 'vendor/autoload.php';
        
        $db = Database::connect();
        $c = $db->query('SELECT c.*, a.name as bank_name, a.account_number, v.name as vendor_name FROM cheques c LEFT JOIN accounts a ON a.id=c.bank_account_id LEFT JOIN vendors v ON v.id=c.vendor_id WHERE c.id=?', [(int)$id])->getRowArray();
        if (!$c) { 
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Cheque not found'); 
        }
        $lines = $db->query('SELECT cl.*, acc.name as account_name FROM cheque_lines cl LEFT JOIN accounts acc ON acc.id=cl.account_id WHERE cl.cheque_id=?', [$id])->getResultArray();
        
        $amount = number_format((float)$c['amount'], 2);
        $amountWords = $this->amountToWords((float)$c['amount']) . ' Rupees Only';

        // Company settings for cheque template
        $companySettings = [];
        try {
            $companySettings = $db->query('SELECT * FROM company_settings ORDER BY id ASC LIMIT 1')->getRowArray() ?: [];
        } catch (\Throwable $e) { /* ignore */ }
        
        $html = view('accounting/cheques/pdf', [
            'cheque' => $c,
            'expense_lines' => $lines,
            'amount_in_words' => $amountWords,
            'company' => $companySettings,
        ]);
        
        // Use dompdf
        $options = new \Dompdf\Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        $dompdf->stream('cheque_' . $c['cheque_number'] . '.pdf', ['Attachment' => false]);
    }

    private function amountToWords($number)
    {
        $fmt = new \NumberFormatter('en', \NumberFormatter::SPELLOUT);
        $integer = floor($number);
        $cents = (int)round(($number - $integer)*100);
        $w = ucfirst($fmt->format($integer));
        if ($cents > 0) { $w .= ' and '. $fmt->format($cents) . ' paisa'; }
        return $w;
    }

    /**
     * Show advance cheques for a vendor that can be adjusted against bills.
     * GET /accounting/cheques/adjust-advance
     */
    public function adjustAdvance()
    {
        $this->ensureChequeTables();
        $vendors = (new VendorModel())->where('is_active', 1)->orderBy('name','ASC')->findAll();
        return view('accounting/cheques/adjust_advance', ['vendors' => $vendors]);
    }

    /**
     * AJAX: get vendor advance balance and pending bills for adjustment
     * GET /accounting/cheques/vendorAdvanceData?vendor_id=X
     */
    public function vendorAdvanceData()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success'=>false,'message'=>'AJAX required']);
        }
        $vendorId = (int)$this->request->getGet('vendor_id');
        if ($vendorId <= 0) {
            return $this->response->setJSON(['success'=>false,'message'=>'Invalid vendor']);
        }
        $db = Database::connect();
        try {
            // Get advance balance
            $advanceModel = new \App\Models\VendorPaymentModel();
            $advanceBalance = $advanceModel->getVendorAdvanceBalance($vendorId);

            // Get advance cheques
            $advances = $db->query("
                SELECT c.id, c.cheque_number, c.cheque_date, c.amount,
                       COALESCE((SELECT SUM(vaa.amount) FROM vendor_advance_adjustments vaa WHERE vaa.advance_cheque_id = c.id), 0) as adjusted_amount
                FROM cheques c
                WHERE c.vendor_id = ? AND c.payment_type = 'advance' AND c.status = 'posted'
                ORDER BY c.cheque_date DESC
            ", [$vendorId])->getResultArray();

            foreach ($advances as &$adv) {
                $adv['remaining'] = max(0, (float)$adv['amount'] - (float)$adv['adjusted_amount']);
            }

            // Get pending bills
            $bills = $db->query("
                SELECT vb.id, COALESCE(vb.bill_number, CONCAT('VB-', vb.id)) as bill_number,
                       DATE_FORMAT(vb.bill_date, '%Y-%m-%d') as bill_date, vb.total_amount as total,
                       COALESCE(vb.balance, vb.total_amount) as balance
                FROM vendor_bills vb
                WHERE vb.vendor_id = ? AND vb.status = 'confirmed' AND COALESCE(vb.balance, vb.total_amount) > 0
                ORDER BY vb.bill_date ASC
            ", [$vendorId])->getResultArray();

            // Also subtract any advance adjustments already made against each bill
            foreach ($bills as &$bill) {
                $adjustedRow = $db->query("SELECT COALESCE(SUM(amount),0) as adj FROM vendor_advance_adjustments WHERE vendor_bill_id = ?", [$bill['id']])->getRowArray();
                $bill['advance_adjusted'] = (float)($adjustedRow['adj'] ?? 0);
                $bill['effective_balance'] = max(0, (float)$bill['balance'] - $bill['advance_adjusted']);
            }

            return $this->response->setJSON([
                'success' => true,
                'advance_balance' => $advanceBalance,
                'advances' => $advances,
                'bills' => $bills,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    /**
     * AJAX POST: apply advance against vendor bills
     * POST /accounting/cheques/applyAdvance
     */
    public function applyAdvance()
    {
        $isAjax = $this->request->isAJAX();
        if (strtoupper($this->request->getMethod()) !== 'POST') {
            return $this->response->setJSON(['success'=>false,'message'=>'POST required']);
        }
        $this->ensureChequeTables();

        $vendorId = (int)$this->request->getPost('vendor_id');
        $billIds = $this->request->getPost('bill_id') ?? [];
        $billAmts = $this->request->getPost('bill_amount') ?? [];
        $adjustmentDate = $this->request->getPost('adjustment_date') ?: date('Y-m-d');
        $notes = trim((string)($this->request->getPost('notes') ?? ''));

        if ($vendorId <= 0) {
            return $this->response->setJSON(['success'=>false,'message'=>'Vendor is required.']);
        }

        // Build allocations
        $allocations = [];
        $totalAdjust = 0.0;
        for ($i=0; $i < count($billIds); $i++) {
            $bid = (int)($billIds[$i] ?? 0);
            $amt = (float)($billAmts[$i] ?? 0);
            if ($bid > 0 && $amt > 0) {
                $allocations[] = ['bill_id'=>$bid, 'amount'=>$amt];
                $totalAdjust += $amt;
            }
        }
        if (empty($allocations) || $totalAdjust <= 0) {
            return $this->response->setJSON(['success'=>false,'message'=>'Please allocate at least one bill amount.']);
        }

        // Check advance balance
        $advanceModel = new \App\Models\VendorPaymentModel();
        $availableBalance = $advanceModel->getVendorAdvanceBalance($vendorId);
        if ($totalAdjust > $availableBalance + 0.01) {
            return $this->response->setJSON(['success'=>false,'message'=>'Adjustment amount ('. number_format($totalAdjust,2) .') exceeds available advance balance ('. number_format($availableBalance,2) .').']);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            // Find Vendor Advances asset account and AP account
            $advAcctRow = $db->query("SELECT id FROM accounts WHERE (name LIKE '%Vendor Advance%' OR name LIKE '%Advance%Vendor%' OR name LIKE '%Supplier Advance%') AND type IN ('Asset','Current Asset') ORDER BY id ASC LIMIT 1")->getRowArray();
            $advAcctId = $advAcctRow ? (int)$advAcctRow['id'] : 0;
            $apRow = $db->query("SELECT id FROM accounts WHERE type='Liability' AND (name LIKE '%Accounts Payable%' OR code='2000') ORDER BY id ASC LIMIT 1")->getRowArray();
            $apId = $apRow ? (int)$apRow['id'] : 0;

            if (!$advAcctId || !$apId) {
                throw new \RuntimeException('Vendor Advances or Accounts Payable account not found in chart of accounts.');
            }

            $vendor = (new VendorModel())->find($vendorId);
            $vendorName = $vendor ? $vendor['name'] : 'Vendor #'.$vendorId;

            // Create journal entry: Debit AP, Credit Vendor Advances
            $jem = new JournalEntryModel();
            $jeId = $jem->insert([
                'entry_date' => $adjustmentDate,
                'memo' => 'Advance adjusted against bills - '.$vendorName,
                'currency_code' => 'PKR',
                'total_debits' => $totalAdjust,
                'total_credits'=> $totalAdjust,
            ], true);

            if (!$jeId) { throw new \RuntimeException('Failed to create journal entry for advance adjustment'); }

            $jlm = new JournalLineModel();
            // Debit Accounts Payable (reduces liability)
            $jlm->insert([
                'entry_id'=>$jeId, 'account_id'=>$apId,
                'description'=>'Advance adjustment - '.$vendorName,
                'debit'=>$totalAdjust, 'credit'=>0,
                'currency_code'=>'PKR', 'fx_rate'=>1, 'base_amount'=>$totalAdjust,
            ]);
            // Credit Vendor Advances (reduces asset)
            $jlm->insert([
                'entry_id'=>$jeId, 'account_id'=>$advAcctId,
                'description'=>'Advance adjusted against bills - '.$vendorName,
                'debit'=>0, 'credit'=>$totalAdjust,
                'currency_code'=>'PKR', 'fx_rate'=>1, 'base_amount'=>$totalAdjust,
            ]);

            // Create vendor_payment record for the settlement (non-advance, with advance_amount)
            $db->query('INSERT INTO vendor_payments (vendor_id, payment_date, payment_method, payment_type, amount, advance_amount, posted_entry_id, notes, status, created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())', [
                $vendorId, $adjustmentDate, 'advance_adjustment', 'settlement', $totalAdjust, $totalAdjust,
                $jeId, 'Advance adjusted against bills' . ($notes ? ': '.$notes : ''), 'posted',
            ]);
            $paymentId = (int)$db->insertID();

            // Find advance cheques to link (FIFO)
            $advCheques = $db->query("
                SELECT c.id, c.amount,
                    COALESCE((SELECT SUM(vaa.amount) FROM vendor_advance_adjustments vaa WHERE vaa.advance_cheque_id = c.id), 0) as used
                FROM cheques c
                WHERE c.vendor_id = ? AND c.payment_type = 'advance' AND c.status = 'posted'
                ORDER BY c.cheque_date ASC, c.id ASC
            ", [$vendorId])->getResultArray();

            $remainToLink = $totalAdjust;

            // Insert adjustment records and payment allocations
            foreach ($allocations as $alloc) {
                $billId = $alloc['bill_id'];
                $adjAmt = $alloc['amount'];

                // Find cheque to link (FIFO from remaining advance cheques)
                $linkedChequeId = null;
                foreach ($advCheques as &$ac) {
                    $avail = (float)$ac['amount'] - (float)$ac['used'];
                    if ($avail > 0.01) {
                        $linkedChequeId = (int)$ac['id'];
                        $ac['used'] = (float)$ac['used'] + $adjAmt; // track usage
                        break;
                    }
                }

                $db->query('INSERT INTO vendor_advance_adjustments (vendor_id, advance_cheque_id, advance_payment_id, vendor_bill_id, amount, adjustment_date, posted_entry_id, notes, created_at) VALUES (?,?,?,?,?,?,?,?,NOW())', [
                    $vendorId, $linkedChequeId, $paymentId, $billId, $adjAmt, $adjustmentDate, $jeId, $notes ?: null,
                ]);

                // Create payment allocation to mark bill as paid
                $db->query('INSERT INTO vendor_payment_allocations (payment_id, vendor_bill_id, purchase_order_id, amount, amount_allocated, created_at) VALUES (?,?,?,?,?,NOW())', [
                    $paymentId, $billId, $billId, $adjAmt, $adjAmt,
                ]);

                // Update bill balance
                $db->query('UPDATE vendor_bills SET balance = GREATEST(0, COALESCE(balance, total_amount) - ?) WHERE id = ?', [$adjAmt, $billId]);
            }

            if ($db->transStatus() === false) { throw new \RuntimeException('DB transaction failed'); }
            $db->transCommit();

            return $this->response->setJSON([
                'success'=>true,
                'message'=>'Advance of '.number_format($totalAdjust,2).' adjusted against '.count($allocations).' bill(s). Journal Entry #'.$jeId,
                'redirect'=>'/corelynk/accounting/cheques/adjust-advance',
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'applyAdvance failed: '.$e->getMessage());
            return $this->response->setJSON(['success'=>false,'message'=>'Failed: '.$e->getMessage()]);
        }
    }

    /**
     * AJAX: Get vendor advance balance
     * GET /accounting/cheques/vendorAdvanceBalance?vendor_id=X
     */
    public function vendorAdvanceBalance()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success'=>false,'message'=>'AJAX required']);
        }
        $vendorId = (int)$this->request->getGet('vendor_id');
        if ($vendorId <= 0) {
            return $this->response->setJSON(['success'=>false,'message'=>'Invalid vendor']);
        }
        try {
            $advanceModel = new \App\Models\VendorPaymentModel();
            $balance = $advanceModel->getVendorAdvanceBalance($vendorId);
            return $this->response->setJSON(['success'=>true,'balance'=>$balance]);
        } catch (\Throwable $e) {
            return $this->response->setJSON(['success'=>false,'message'=>$e->getMessage()]);
        }
    }

    /**
     * Get payment breakdown by bill for a vendor (for enhanced UI)
     * Route: GET /accounting/cheques/paymentBreakdown?type=vendor&id=X
     */
    public function paymentBreakdown()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'AJAX required']);
        }

        $type = $this->request->getGet('type');
        $idRaw = trim((string) ($this->request->getGet('id') ?? ''));
        
        if (!in_array($type, ['vendor', 'employee']) || $idRaw === '' || !preg_match('/^-?\d+$/', $idRaw)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Invalid parameters']);
        }

        $id = (int)$idRaw;
        $db = Database::connect();

        try {
            if ($type === 'vendor') {
                // Get all posted payment allocations with bill info
                $payments = $db->query("
                    SELECT 
                        vp.id as payment_id,
                        vp.payment_date,
                        COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0) as amount,
                        CASE 
                            WHEN COALESCE(vp.amount, 0) > 0 THEN ROUND(COALESCE(vp.advance_amount, 0) * (COALESCE(NULLIF(vpa.amount_allocated, 0), vpa.amount, 0) / vp.amount), 2)
                            ELSE 0
                        END as advance_amount,
                        vb.bill_number,
                        COALESCE(vpa.vendor_bill_id, vpa.purchase_order_id) as bill_id,
                        vp.notes,
                        vp.memo
                    FROM vendor_payment_allocations vpa
                    JOIN vendor_payments vp ON vp.id = vpa.payment_id
                    JOIN vendor_bills vb ON vb.id = COALESCE(vpa.vendor_bill_id, vpa.purchase_order_id)
                    WHERE vp.vendor_id = ? AND vp.status = 'posted'
                    ORDER BY vp.payment_date DESC, vp.id DESC
                ", [$id])->getResultArray();

                return $this->response->setJSON([
                    'success' => true,
                    'payments' => $payments
                ]);
            } else {
                // Employee payments (if implemented)
                return $this->response->setJSON([
                    'success' => false,
                    'error' => 'Employee breakdown not yet implemented'
                ]);
            }
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error' => 'Server error: ' . $e->getMessage()
            ]);
        }
    }
}
