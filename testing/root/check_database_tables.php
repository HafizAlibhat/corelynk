<?php
// Check what tables exist in the database
$mysqli = new mysqli("localhost", "root", "", "production_management");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Checking all tables in production_management database...\n\n";

// Show all tables
$result = $mysqli->query("SHOW TABLES");
if ($result) {
    echo "Existing tables:\n";
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "Error showing tables: " . $mysqli->error . "\n";
}

echo "\n";

// Check for any tables that might contain 'template' in the name
$result = $mysqli->query("SHOW TABLES LIKE '%template%'");
if ($result) {
    echo "Tables containing 'template':\n";
    while ($row = $result->fetch_row()) {
        echo "- " . $row[0] . "\n";
    }
} else {
    echo "No tables containing 'template' found\n";
}

$mysqli->close();
?>
