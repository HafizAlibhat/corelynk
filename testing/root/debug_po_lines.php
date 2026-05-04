<?php
// Direct database check
$mysqli = new mysqli('localhost', 'root', '', 'corelynk');

if ($mysqli->connect_error) {
    die('Connect Error: ' . $mysqli->connect_error);
}

echo "=== Purchase Order Lines Table Structure ===\n";
$result = $mysqli->query("SHOW COLUMNS FROM purchase_order_lines");
while ($row = $result->fetch_assoc()) {
    echo "{$row['Field']} - {$row['Type']} - {$row['Null']} - {$row['Key']} - {$row['Default']}\n";
}

echo "\n=== PO #2 Lines Data ===\n";
$result = $mysqli->query("SELECT * FROM purchase_order_lines WHERE po_id = 2");
while ($row = $result->fetch_assoc()) {
    print_r($row);
    echo "\n";
}

echo "\n=== Product Variants Table Sample ===\n";
$result = $mysqli->query("SELECT id, product_id, art_number, name FROM product_variants LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$mysqli->close();
