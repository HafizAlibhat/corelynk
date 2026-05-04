<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Checking process_templates table...\n";
$result = $mysqli->query('SHOW TABLES LIKE "process_templates"');
if ($result->num_rows > 0) {
    echo "✓ process_templates table exists\n";
    $count = $mysqli->query('SELECT COUNT(*) as count FROM process_templates');
    $row = $count->fetch_assoc();
    echo "✓ Table has " . $row['count'] . " records\n";
    
    // Show first few records
    $samples = $mysqli->query('SELECT id, name, category FROM process_templates LIMIT 3');
    echo "Sample records:\n";
    while ($record = $samples->fetch_assoc()) {
        echo "- " . $record['id'] . ": " . $record['name'] . " (category: " . $record['category'] . ")\n";
    }
} else {
    echo "✗ process_templates table does NOT exist\n";
}

echo "\nChecking process_categories table...\n";
$result = $mysqli->query('SHOW TABLES LIKE "process_categories"');
if ($result->num_rows > 0) {
    echo "✓ process_categories table exists\n";
    $count = $mysqli->query('SELECT COUNT(*) as count FROM process_categories');
    $row = $count->fetch_assoc();
    echo "✓ Table has " . $row['count'] . " records\n";
} else {
    echo "✗ process_categories table does NOT exist\n";
}

$mysqli->close();
?>
