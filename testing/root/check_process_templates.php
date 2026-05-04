<?php
// Check current process_templates structure and data
$mysqli = new mysqli("localhost", "root", "", "production_management_system");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Checking current process_templates table...\n\n";

// Check structure
$result = $mysqli->query("SHOW CREATE TABLE process_templates");
if ($result) {
    $row = $result->fetch_row();
    echo "Current structure:\n";
    echo $row[1] . "\n\n";
}

// Check data
$result = $mysqli->query("SELECT * FROM process_templates LIMIT 10");
if ($result) {
    echo "Current data (first 10 rows):\n";
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, Name: {$row['name']}\n";
        if (isset($row['description'])) echo "Description: {$row['description']}\n";
        echo "---\n";
    }
    
    // Count total
    $countResult = $mysqli->query("SELECT COUNT(*) as total FROM process_templates");
    $count = $countResult->fetch_assoc();
    echo "\nTotal records: " . $count['total'] . "\n";
}

$mysqli->close();
?>
