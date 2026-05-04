<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');

// Get all variants for product 1
echo "=== Variants for Product 1 ===\n";
$result = $db->query("SELECT id, art_number, name, attributes, image FROM product_variants WHERE product_id = 1;");
while ($row = $result->fetch_assoc()) {
    echo "\nVariant ID: " . $row['id'] . "\n";
    echo "  art_number: " . $row['art_number'] . "\n";
    echo "  name: " . $row['name'] . "\n";
    echo "  attributes: " . $row['attributes'] . "\n";
    echo "  image: " . $row['image'] . "\n";
}

$db->close();
?>
