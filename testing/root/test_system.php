<?php
// Test script to verify our new process templates system
require_once 'vendor/autoload.php';

// Test database connection and tables
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Process Templates System Test ===\n\n";

// Check tables exist
$tables = ['process_templates', 'product_processes'];
foreach ($tables as $table) {
    $result = $mysqli->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✓ Table '$table' exists\n";
        
        // Count records
        $countResult = $mysqli->query("SELECT COUNT(*) as count FROM $table");
        $count = $countResult->fetch_assoc()['count'];
        echo "  - Records: $count\n";
    } else {
        echo "✗ Table '$table' missing\n";
    }
}

echo "\n=== Sample Data Check ===\n";

// Check process templates
$result = $mysqli->query("SELECT category, COUNT(*) as count FROM process_templates GROUP BY category ORDER BY count DESC");
echo "Process Templates by Category:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - {$row['category']}: {$row['count']} templates\n";
}

// Check product processes
$result = $mysqli->query("
    SELECT p.name as product_name, COUNT(pp.id) as process_count 
    FROM products p 
    LEFT JOIN product_processes pp ON p.id = pp.product_id 
    GROUP BY p.id, p.name 
    HAVING process_count > 0
    ORDER BY process_count DESC 
    LIMIT 5
");

echo "\nProducts with Most Processes:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - {$row['product_name']}: {$row['process_count']} processes\n";
}

// Check for any issues
$result = $mysqli->query("
    SELECT pt.name as template_name, COUNT(pp.id) as usage_count 
    FROM process_templates pt 
    LEFT JOIN product_processes pp ON pt.id = pp.process_template_id 
    GROUP BY pt.id, pt.name 
    ORDER BY usage_count DESC 
    LIMIT 5
");

echo "\nMost Used Process Templates:\n";
while ($row = $result->fetch_assoc()) {
    echo "  - {$row['template_name']}: {$row['usage_count']} uses\n";
}

$mysqli->close();
echo "\n✅ System test completed!\n";
?>
