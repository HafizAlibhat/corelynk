<?php
// Check GRN data
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Checking GRN #5 lines ===\n\n";
$result = $mysqli->query("SELECT * FROM purchase_grn_lines WHERE grn_id = 5");
while ($row = $result->fetch_assoc()) {
    echo "Line ID: {$row['id']}\n";
    echo "Product ID: {$row['product_id']}\n";
    echo "Variant ID: {$row['variant_id']}\n";
    echo "Description: {$row['description']}\n";
    echo "Qty: {$row['qty_received']}\n";
    echo "---\n";
}

echo "\n=== Checking what the detail controller would fetch ===\n\n";
$query = "
SELECT gl.*, 
       p.name as product_name, 
       p.sku as product_sku, 
       p.code as product_code, 
       p.images as product_images, 
       p.unit as product_unit,
       pv.art_number as variant_art_number, 
       pv.name as variant_name, 
       pv.image as variant_image
FROM purchase_grn_lines gl
LEFT JOIN products p ON p.id = gl.product_id
LEFT JOIN product_variants pv ON pv.id = gl.variant_id
WHERE gl.grn_id = 5
";

$result = $mysqli->query($query);
while ($row = $result->fetch_assoc()) {
    echo "Product Name: " . ($row['product_name'] ?? 'NULL') . "\n";
    echo "Product Code: " . ($row['product_code'] ?? 'NULL') . "\n";
    echo "Variant Art Number: " . ($row['variant_art_number'] ?? 'NULL') . "\n";
    echo "Variant Name: " . ($row['variant_name'] ?? 'NULL') . "\n";
    echo "Variant Image: " . ($row['variant_image'] ?? 'NULL') . "\n";
    echo "Description: " . ($row['description'] ?? 'NULL') . "\n";
    echo "---\n";
}

$mysqli->close();
