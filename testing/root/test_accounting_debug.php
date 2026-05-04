<?php
require_once 'vendor/autoload.php';

echo "=== ACCOUNTING SYSTEM DEBUG ===\n";

try {
    // Test DB connection
    $db = Config\Database::connect();
    echo "✓ Database connection OK\n";
    
    // Check if accounting tables exist
    $tables = ['accounts', 'journal_entries', 'journal_lines'];
    foreach ($tables as $table) {
        try {
            $result = $db->query("SELECT COUNT(*) as count FROM $table")->getRowArray();
            echo "✓ Table $table exists with {$result['count']} records\n";
        } catch (Exception $e) {
            echo "✗ Table $table missing or error: " . $e->getMessage() . "\n";
        }
    }
    
    // Test AccountingJournalLite controller
    echo "\n--- Testing AccountingJournalLite Controller ---\n";
    
    // Simulate request data
    $_POST = [
        'entry_date' => '2025-11-14',
        'memo' => 'Test Entry via Debug',
        'account_debit' => 1,
        'account_credit' => 2,
        'amount' => 100.00
    ];
    
    // Create a mock request
    $config = new \Config\Services;
    $request = \Config\Services::request();
    $response = \Config\Services::response();
    
    // Test the controller directly
    $controller = new \App\Controllers\AccountingJournalLite();
    
    echo "✓ Controller instantiated\n";
    
    // Check if we have accounts first
    $accounts = $db->query('SELECT id, code, name FROM accounts ORDER BY id LIMIT 10')->getResultArray();
    echo "Available accounts: " . count($accounts) . "\n";
    foreach ($accounts as $acc) {
        echo "  - ID {$acc['id']}: {$acc['code']} - {$acc['name']}\n";
    }
    
    if (count($accounts) >= 2) {
        $_POST['account_debit'] = $accounts[0]['id'];
        $_POST['account_credit'] = $accounts[1]['id'];
        
        // Try to post
        echo "\nAttempting to post entry...\n";
        $result = $controller->post();
        
        // Check what happened
        $entriesAfter = $db->query('SELECT COUNT(*) as count FROM journal_entries')->getRowArray();
        echo "Journal entries after test: {$entriesAfter['count']}\n";
        
        $lastEntry = $db->query('SELECT * FROM journal_entries ORDER BY id DESC LIMIT 1')->getRowArray();
        if ($lastEntry) {
            echo "Last entry: ID {$lastEntry['id']}, Date {$lastEntry['entry_date']}, Memo: {$lastEntry['memo']}\n";
            
            $lines = $db->query('SELECT * FROM journal_lines WHERE entry_id = ?', [$lastEntry['id']])->getResultArray();
            echo "Lines for entry: " . count($lines) . "\n";
            foreach ($lines as $line) {
                echo "  - Account {$line['account_id']}: Debit {$line['debit']}, Credit {$line['credit']}\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== DEBUG COMPLETE ===\n";