<?php
// Test script to verify public_id implementation

// Load CodeIgniter configuration
define('ENVIRONMENT', 'development');
chdir(__DIR__);

// Try to load CodeIgniter
try {
    require_once 'vendor/autoload.php';
    $boot = require_once 'app/Config/Boot.php';
    
    // Get database connection
    $db = \Config\Database::connect();
    
    // Check quotations table
    echo "=== QUOTATIONS TABLE ===\n";
    $result = $db->query("DESC quotations")->getResult();
    $hasPublicId = false;
    foreach ($result as $col) {
        if ($col->Field === 'public_id') {
            $hasPublicId = true;
            echo "✓ public_id column exists: {$col->Type}\n";
        }
    }
    if (!$hasPublicId) {
        echo "✗ public_id column DOES NOT exist\n";
    }
    
    // Check data
    echo "\n=== QUOTATIONS DATA ===\n";
    $stmt = $db->query("SELECT COUNT(*) as total, COUNT(public_id) as with_public_id FROM quotations");
    $row = $stmt->getRow();
    echo "Total quotations: {$row->total}\n";
    echo "With public_id: {$row->with_public_id}\n";
    
    // Show sample
    echo "\n=== SAMPLE QUOTATIONS ===\n";
    $samples = $db->query("SELECT id, public_id, quote_number FROM quotations LIMIT 3")->getResult();
    foreach ($samples as $q) {
        echo "ID: {$q->id}, Public ID: {$q->public_id}, Quote#: {$q->quote_number}\n";
    }
    
    // Check feature flag
    echo "\n=== FEATURE FLAG ===\n";
    $flag = $db->query("SELECT * FROM feature_flags WHERE flag_key = 'enable_public_ids'")->getRow();
    if ($flag) {
        echo "Flag exists: enabled = {$flag->enabled}\n";
    } else {
        echo "Flag DOES NOT exist\n";
    }
    
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
