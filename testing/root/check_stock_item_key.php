<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Checking stock_balances columns ===\n";
$result = $mysqli->query("DESCRIBE stock_balances");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== Current stock data for product_id = 1 ===\n";
$result = $mysqli->query("SELECT * FROM stock_balances WHERE product_id = 1");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$mysqli->close();
