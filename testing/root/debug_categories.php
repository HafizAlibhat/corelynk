<?php
// Simple debug script for ProcessCategoryModel
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if process_categories table exists and has data
$result = $mysqli->query("SELECT * FROM process_categories WHERE is_active = 1 LIMIT 5");

echo "Process categories table check:\n";
if ($result) {
    echo "Found " . $result->num_rows . " active categories:\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Name: {$row['name']}\n";
    }
} else {
    echo "Error: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
