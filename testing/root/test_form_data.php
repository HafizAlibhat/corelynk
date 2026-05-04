<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "Testing process categories data for forms...\n\n";

// Test the getActiveCategories query
$sql = "SELECT id, name FROM process_categories WHERE is_active = 1 ORDER BY name";
$result = $mysqli->query($sql);

echo "Active Process Categories:\n";
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . "\n";
    }
    echo "\nTotal active categories: " . $result->num_rows . "\n";
} else {
    echo "No active categories found!\n";
}

// Test vendors
echo "\nActive Vendors:\n";
$sql = "SELECT id, name FROM vendors WHERE is_active = 1 ORDER BY name";
$result = $mysqli->query($sql);

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: " . $row['id'] . " | Name: " . $row['name'] . "\n";
    }
    echo "\nTotal active vendors: " . $result->num_rows . "\n";
} else {
    echo "No active vendors found!\n";
}

$mysqli->close();
?>
