<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== CUSTOMER_PAYMENTS TABLE STRUCTURE ===\n\n";

$result = $mysqli->query("DESCRIBE customer_payments");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
    }
}

echo "\nSample data:\n";
$result = $mysqli->query("SELECT * FROM customer_payments LIMIT 1");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    foreach ($row as $k => $v) {
        echo "  $k: $v\n";
    }
}

$mysqli->close();
?>
