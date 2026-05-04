<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Full PO line descriptions ===\n";
$poLines = $mysqli->query("SELECT id, product_id, variant_id, description FROM purchase_order_lines WHERE product_id = 1 ORDER BY id");
while($row = $poLines->fetch_assoc()) {
    echo "\nPO Line " . $row['id'] . ":\n";
    echo "  Description: " . $row['description'] . "\n";
}

echo "\n\n=== All variants for product_id=1 ===\n";
$variants = $mysqli->query("SELECT id, art_number, name FROM product_variants WHERE product_id = 1 ORDER BY id");
while($row = $variants->fetch_assoc()) {
    echo "Variant " . $row['id'] . " (art_number=" . $row['art_number'] . "): " . $row['name'] . "\n";
}

$mysqli->close();
?>
