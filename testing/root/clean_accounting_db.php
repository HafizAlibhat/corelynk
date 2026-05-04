<?php
// Clean accounting database and reset entry numbers
require_once 'vendor/autoload.php';

echo "=== CLEANING ACCOUNTING DATABASE ===\n";

try {
    $db = Config\Database::connect();
    
    // Delete all journal data
    echo "Deleting journal entries and lines...\n";
    $db->query('DELETE FROM journal_lines');
    $db->query('DELETE FROM journal_entries');
    
    // Reset auto increment
    echo "Resetting entry numbers...\n";
    $db->query('ALTER TABLE journal_entries AUTO_INCREMENT = 1');
    $db->query('ALTER TABLE journal_lines AUTO_INCREMENT = 1');
    
    // Keep accounts but you can uncomment below to reset accounts too
    // $db->query('DELETE FROM accounts');
    // $db->query('ALTER TABLE accounts AUTO_INCREMENT = 1');
    
    echo "✅ Database cleaned successfully!\n";
    echo "✅ Entry numbers reset to start from #1\n";
    echo "✅ Chart of accounts preserved\n";
    
    // Show final counts
    $entries = $db->query('SELECT COUNT(*) as c FROM journal_entries')->getRowArray()['c'];
    $lines = $db->query('SELECT COUNT(*) as c FROM journal_lines')->getRowArray()['c'];
    $accounts = $db->query('SELECT COUNT(*) as c FROM accounts')->getRowArray()['c'];
    
    echo "\nFinal status:\n";
    echo "- Journal entries: $entries\n";
    echo "- Journal lines: $lines\n";
    echo "- Accounts: $accounts\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== CLEANUP COMPLETE ===\n";
echo "Ready for fresh data entry!\n";
?>