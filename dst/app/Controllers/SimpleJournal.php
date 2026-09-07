<?php

namespace App\Controllers;

use Config\Database;

class SimpleJournal extends BaseController
{
    public function index()
    {
        // Ensure basic tables exist
        $this->createTables();
        
        $data = [
            'accounts' => $this->getAccounts(),
            'entries' => $this->getRecentEntries(),
            'message' => session()->getFlashdata('message'),
            'error' => session()->getFlashdata('error')
        ];
        
        return view('simple_journal', $data);
    }
    
    public function post()
    {
        $db = Database::connect();
        
        // Get form data
        $date = $this->request->getPost('date');
        $memo = $this->request->getPost('memo') ?: 'Journal Entry';
        $debitAccount = (int)$this->request->getPost('debit_account');
        $creditAccount = (int)$this->request->getPost('credit_account');
        $amount = (float)$this->request->getPost('amount');
        
        // Simple validation
        if (!$date || !$debitAccount || !$creditAccount || $amount <= 0) {
            return redirect()->back()->with('error', 'Please fill all fields with valid data');
        }
        
        if ($debitAccount === $creditAccount) {
            return redirect()->back()->with('error', 'Debit and credit accounts must be different');
        }
        
        try {
            // Insert journal entry
            $sql = "INSERT INTO journal_entries (entry_date, memo, currency_code, total_debits, total_credits) VALUES (?, ?, 'PKR', ?, ?)";
            $db->query($sql, [$date, $memo, $amount, $amount]);
            $entryId = $db->insertID();
            
            // Insert debit line
            $sql = "INSERT INTO journal_lines (entry_id, account_id, description, debit, credit, currency_code) VALUES (?, ?, ?, ?, 0, 'PKR')";
            $db->query($sql, [$entryId, $debitAccount, $memo, $amount]);
            
            // Insert credit line
            $sql = "INSERT INTO journal_lines (entry_id, account_id, description, debit, credit, currency_code) VALUES (?, ?, ?, 0, ?, 'PKR')";
            $db->query($sql, [$entryId, $creditAccount, $memo, $amount]);
            
            return redirect()->to('/simple-journal')->with('message', "Journal entry #{$entryId} posted successfully!");
            
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Error: ' . $e->getMessage());
        }
    }
    
    private function createTables()
    {
        $db = Database::connect();
        
        // Create accounts table
        $sql = "CREATE TABLE IF NOT EXISTS accounts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(20) NOT NULL,
            name VARCHAR(100) NOT NULL,
            type VARCHAR(20) NOT NULL,
            currency_code VARCHAR(3) DEFAULT 'PKR'
        )";
        $db->query($sql);
        
        // Create journal entries table
        $sql = "CREATE TABLE IF NOT EXISTS journal_entries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_date DATE NOT NULL,
            memo VARCHAR(255),
            currency_code VARCHAR(3) DEFAULT 'PKR',
            total_debits DECIMAL(15,2) DEFAULT 0,
            total_credits DECIMAL(15,2) DEFAULT 0
        )";
        $db->query($sql);
        
        // Create journal lines table
        $sql = "CREATE TABLE IF NOT EXISTS journal_lines (
            id INT AUTO_INCREMENT PRIMARY KEY,
            entry_id INT NOT NULL,
            account_id INT NOT NULL,
            description VARCHAR(255),
            debit DECIMAL(15,2) DEFAULT 0,
            credit DECIMAL(15,2) DEFAULT 0,
            currency_code VARCHAR(3) DEFAULT 'PKR'
        )";
        $db->query($sql);
        
        // Seed some basic accounts if none exist
        $count = $db->query("SELECT COUNT(*) as c FROM accounts")->getRowArray()['c'];
        if ($count == 0) {
            $accounts = [
                ['1000', 'Cash', 'Asset'],
                ['1100', 'Bank Account', 'Asset'],
                ['2000', 'Accounts Payable', 'Liability'],
                ['3000', 'Capital', 'Equity'],
                ['4000', 'Sales Revenue', 'Revenue'],
                ['5000', 'Office Expenses', 'Expense']
            ];
            
            foreach ($accounts as $acc) {
                $db->query("INSERT INTO accounts (code, name, type) VALUES (?, ?, ?)", $acc);
            }
        }
    }
    
    private function getAccounts()
    {
        $db = Database::connect();
        return $db->query("SELECT id, code, name FROM accounts ORDER BY code")->getResultArray();
    }
    
    private function getRecentEntries()
    {
        $db = Database::connect();
        return $db->query("SELECT id, entry_date, memo, total_debits, total_credits FROM journal_entries ORDER BY id DESC LIMIT 20")->getResultArray();
    }
}