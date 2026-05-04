<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Checking product_variants table ===\n";
$variants = $mysqli->query("SELECT id, product_id, art_number, name, image FROM product_variants LIMIT 10");
while($row = $variants->fetch_assoc()) {
    echo "Variant " . $row['id'] . ": product_id=" . $row['product_id'] . ", art_number=" . $row['art_number'] . ", name=" . $row['name'] . ", image=" . $row['image'] . "\n";
}

echo "\n=== Checking purchase_order_lines ===\n";
$poLines = $mysqli->query("SELECT id, product_id, variant_id, description FROM purchase_order_lines");
while($row = $poLines->fetch_assoc()) {
    echo "PO Line " . $row['id'] . ": product_id=" . $row['product_id'] . ", variant_id=" . (isset($row['variant_id']) && $row['variant_id'] ? $row['variant_id'] : 'NULL') . ", desc=" . substr($row['description'], 0, 50) . "...\n";
}

$mysqli->close();
?>
