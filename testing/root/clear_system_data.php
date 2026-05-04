<?php
// Clear all system data except users table
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Clearing System Data (Keeping Users) ===\n\n";

// Disable foreign key checks temporarily
$mysqli->query("SET FOREIGN_KEY_CHECKS = 0");

// Tables to clear (in order to respect foreign keys)
$tablesToClear = [
    'product_processes',
    'work_order_items', 
    'work_order_process_runs',
    'component_usage',
    'quality_control_records',
    'work_orders',
    'processes',
    'process_templates',
    'bom_items',
    'products',
    'product_categories',
    'components',
    'vendors',
    'component_transactions'
];

$clearedTables = 0;
$errors = 0;

foreach ($tablesToClear as $table) {
    // Check if table exists first
    $checkResult = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($checkResult->num_rows > 0) {
        // Get count before deletion
        $countResult = $mysqli->query("SELECT COUNT(*) as count FROM $table");
        $count = $countResult ? $countResult->fetch_assoc()['count'] : 0;
        
        if ($mysqli->query("DELETE FROM $table")) {
            echo "✓ Cleared table '$table' ($count records)\n";
            $clearedTables++;
            
            // Reset auto increment
            $mysqli->query("ALTER TABLE $table AUTO_INCREMENT = 1");
        } else {
            echo "✗ Error clearing table '$table': " . $mysqli->error . "\n";
            $errors++;
        }
    } else {
        echo "- Table '$table' doesn't exist, skipping\n";
    }
}

// Re-enable foreign key checks
$mysqli->query("SET FOREIGN_KEY_CHECKS = 1");

echo "\n=== Summary ===\n";
echo "Tables cleared: $clearedTables\n";
echo "Errors: $errors\n";

// Check users table is intact
$userResult = $mysqli->query("SELECT COUNT(*) as count FROM users");
$userCount = $userResult ? $userResult->fetch_assoc()['count'] : 0;
echo "Users preserved: $userCount\n";

$mysqli->close();

echo "\n✅ Data cleanup completed!\n";
echo "You can now start fresh with your own data.\n";
?>
