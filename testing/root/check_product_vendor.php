<?php
// Check product details
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Product #2 Details ===\n";
$result = $db->query("SELECT * FROM products WHERE id = 2");
$product = $result->fetch_assoc();

if ($product) {
    echo "ID: {$product['id']}\n";
    echo "Name: {$product['name']}\n";
    echo "Code/SKU: " . ($product['code'] ?? $product['sku'] ?? 'N/A') . "\n";
    echo "Vendor ID: " . ($product['vendor_id'] ?? 'NULL/NOT SET') . "\n";
    echo "\nAll columns:\n";
    print_r(array_keys($product));
    
    // Check if vendor_id column exists
    $result = $db->query("SHOW COLUMNS FROM products LIKE 'vendor_id'");
    if ($result->num_rows > 0) {
        echo "\n✓ vendor_id column EXISTS in products table\n";
    } else {
        echo "\n✗ vendor_id column DOES NOT EXIST in products table\n";
    }
}

$db->close();
