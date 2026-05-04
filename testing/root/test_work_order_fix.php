<?php
// Simple test to verify work order page functionality
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/test';

// Bootstrap CodeIgniter
require_once __DIR__ . '/vendor/autoload.php';

// Initialize the framework
$paths = new \Config\Paths();
$bootstrap = \CodeIgniter\Boot::bootWeb($paths);

// Test database connection and check for missing tables
try {
    $db = \Config\Database::connect();
    
    echo "✅ Database connection successful\n";
    
    // Check if process_templates table exists
    $tables = $db->listTables();
    if (in_array('process_templates', $tables)) {
        echo "✅ process_templates table exists\n";
    } else {
        echo "⚠️  process_templates table missing (will use fallback query)\n";
    }
    
    // Check process_batches table structure
    $columns = $db->query("SHOW COLUMNS FROM process_batches")->getResultArray();
    $colNames = array_column($columns, 'Field');
    
    echo "📋 process_batches columns: " . implode(', ', $colNames) . "\n";
    
    if (in_array('batch_code', $colNames)) {
        echo "✅ batch_code column exists\n";
    }
    
    if (in_array('batch_number', $colNames)) {
        echo "✅ batch_number column exists\n";
    }
    
    // Test basic query
    $workOrders = $db->table('work_orders')->limit(5)->get()->getResultArray();
    echo "✅ Found " . count($workOrders) . " work orders\n";
    
    echo "\n🎉 Basic functionality test passed!\n";
    echo "You can now try accessing: http://localhost/pro_sys/public/work-orders/1\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
