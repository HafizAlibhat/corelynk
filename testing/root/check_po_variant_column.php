<?php
// Direct database check
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Checking purchase_order_lines table structure ===\n\n";
$result = $mysqli->query("DESCRIBE purchase_order_lines");
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    echo "{$row['Field']} - {$row['Type']}\n";
}

echo "\n=== Checking if variant column exists ===\n";
$hasVariantId = in_array('variant_id', $columns);
$hasProductVariantId = in_array('product_variant_id', $columns);
echo "variant_id exists: " . ($hasVariantId ? 'YES' : 'NO') . "\n";
echo "product_variant_id exists: " . ($hasProductVariantId ? 'YES' : 'NO') . "\n";

echo "\n=== Checking PO #2 lines ===\n";
$result = $mysqli->query("SELECT * FROM purchase_order_lines WHERE po_id = 2");
while ($row = $result->fetch_assoc()) {
    print_r($row);
    echo "\n";
}

$mysqli->close();
