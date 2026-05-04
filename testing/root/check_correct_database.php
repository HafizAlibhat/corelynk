<?php
// Check the correct database
$mysqli = new mysqli("localhost", "root", "", "production_management_system");

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "Checking tables in production_management_system database...\n\n";

// Show all tables
$result = $mysqli->query("SHOW TABLES");
if ($result) {
    echo "Existing tables:\n";
    $tables = [];
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
        echo "- " . $row[0] . "\n";
    }
    echo "\nTotal tables: " . count($tables) . "\n";
} else {
    echo "Error showing tables: " . $mysqli->error . "\n";
}

$mysqli->close();
?>
