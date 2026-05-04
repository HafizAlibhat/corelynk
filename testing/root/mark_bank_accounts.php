<?php
/**
 * Mark Bank Accounts Script
 * Sets is_bank=1 for accounts whose name contains "Bank"
 * Run this once to enable the cheque module bank dropdown
 */

require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap CodeIgniter
$app = require_once FCPATH . '../app/Config/Paths.php';
$app = new \Config\App();
$db = \Config\Database::connect('accounting');

echo "🏦 Mark Bank Accounts Script\n";
echo str_repeat('=', 60) . "\n\n";

try {
    // Check if is_bank column exists
    $columns = $db->query("SHOW COLUMNS FROM accounts LIKE 'is_bank'")->getResultArray();
    if (empty($columns)) {
        echo "❌ Column 'is_bank' does not exist in accounts table.\n";
        echo "   Run: php add_cheques_module.php first\n";
        exit(1);
    }
    echo "✅ Column 'is_bank' exists\n\n";

    // Mark accounts with 'Bank' in name
    $result = $db->query("UPDATE accounts SET is_bank = 1 WHERE name LIKE '%Bank%' AND is_bank != 1");
    $affected = $db->affectedRows();
    echo "✅ Marked {$affected} account(s) as bank accounts\n\n";

    // Show all bank accounts
    echo "Current Bank Accounts:\n";
    echo str_repeat('-', 60) . "\n";
    $banks = $db->query("SELECT id, code, name, type, is_bank, account_number FROM accounts WHERE is_bank = 1 ORDER BY code")->getResultArray();
    
    if (empty($banks)) {
        echo "⚠️  No bank accounts found. Use the Edit form to mark accounts as banks.\n";
    } else {
        foreach ($banks as $bank) {
            $acctNum = $bank['account_number'] ? " (****" . substr($bank['account_number'], -4) . ")" : "";
            echo sprintf(
                "  ✓ [%s] %s - %s%s\n",
                $bank['code'],
                $bank['name'],
                $bank['type'],
                $acctNum
            );
        }
    }
    
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "✅ Done! Visit /accounting/cheques/create to see banks in dropdown.\n";
    
} catch (\Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    exit(1);
}
