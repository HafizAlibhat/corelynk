<?php
// Check product variants
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Product variants for product_id = 1 ===\n\n";
$result = $mysqli->query("SELECT id, product_id, art_number, name, attributes, image FROM product_variants WHERE product_id = 1");
while ($row = $result->fetch_assoc()) {
    echo "ID: {$row['id']}\n";
    echo "Art Number: {$row['art_number']}\n";
    echo "Name: {$row['name']}\n";
    echo "Attributes: {$row['attributes']}\n";
    echo "Image: {$row['image']}\n";
    echo "---\n";
}

echo "\n=== Now updating PO #2 lines with variant_id ===\n\n";

// Update PO line 2 (Black Snake Feather)
$result = $mysqli->query("SELECT id FROM product_variants WHERE product_id = 1 AND attributes LIKE '%Black Snake Feather%' LIMIT 1");
if ($row = $result->fetch_assoc()) {
    $variantId = $row['id'];
    echo "Updating PO line 2 with variant_id = $variantId (Black Snake Feather)\n";
    $mysqli->query("UPDATE purchase_order_lines SET variant_id = $variantId WHERE id = 2");
}

// Update PO line 3 (Narrow Ladder)
$result = $mysqli->query("SELECT id FROM product_variants WHERE product_id = 1 AND attributes LIKE '%Narrow Ladder%' LIMIT 1");
if ($row = $result->fetch_assoc()) {
    $variantId = $row['id'];
    echo "Updating PO line 3 with variant_id = $variantId (Narrow Ladder)\n";
    $mysqli->query("UPDATE purchase_order_lines SET variant_id = $variantId WHERE id = 3");
}

// Update PO line 4 (White Feather)
$result = $mysqli->query("SELECT id FROM product_variants WHERE product_id = 1 AND attributes LIKE '%White Feather%' LIMIT 1");
if ($row = $result->fetch_assoc()) {
    $variantId = $row['id'];
    echo "Updating PO line 4 with variant_id = $variantId (White Feather)\n";
    $mysqli->query("UPDATE purchase_order_lines SET variant_id = $variantId WHERE id = 4");
}

echo "\n=== Updated PO #2 lines ===\n";
$result = $mysqli->query("SELECT id, product_id, variant_id, description FROM purchase_order_lines WHERE po_id = 2");
while ($row = $result->fetch_assoc()) {
    echo "Line {$row['id']}: product_id={$row['product_id']}, variant_id={$row['variant_id']}, desc={$row['description']}\n";
}

$mysqli->close();
echo "\nDone! Please refresh the GRN page.\n";
