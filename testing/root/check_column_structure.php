<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== TABLE STRUCTURE ANALYSIS ===\n\n";

echo "1. customer_payment_allocations columns:\n";
$result = $mysqli->query("DESCRIBE customer_payment_allocations");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
} else {
    echo "  Error: " . $mysqli->error . "\n";
}

echo "\n2. Sample data from customer_payment_allocations:\n";
$result = $mysqli->query("SELECT * FROM customer_payment_allocations LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "  No data found\n";
}

$mysqli->close();
?>
