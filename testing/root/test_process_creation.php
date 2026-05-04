<?php
// Test process creation functionality
echo "Testing process creation system...\n";

// Test database connection
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if process_templates table exists and can accept new records
echo "1. Checking process_templates table...\n";
$result = $mysqli->query("DESCRIBE process_templates");
if ($result) {
    echo "✓ process_templates table exists\n";
    echo "   Columns: ";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " ";
    }
    echo "\n";
} else {
    echo "✗ Error: " . $mysqli->error . "\n";
}

// Check categories
echo "\n2. Checking process categories...\n";
$result = $mysqli->query("SELECT id, name FROM process_categories WHERE is_active = 1 LIMIT 3");
if ($result && $result->num_rows > 0) {
    echo "✓ Found " . $result->num_rows . " active categories:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - ID: {$row['id']}, Name: {$row['name']}\n";
    }
} else {
    echo "✗ No active categories found\n";
}

// Check vendors
echo "\n3. Checking vendors...\n";
$result = $mysqli->query("SELECT id, name FROM vendors WHERE is_active = 1 LIMIT 3");
if ($result && $result->num_rows > 0) {
    echo "✓ Found " . $result->num_rows . " active vendors:\n";
    while ($row = $result->fetch_assoc()) {
        echo "   - ID: {$row['id']}, Name: {$row['name']}\n";
    }
} else {
    echo "✗ No active vendors found\n";
}

echo "\nTest completed!\n";
$mysqli->close();
?>
