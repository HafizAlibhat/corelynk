<?php
require 'vendor/autoload.php';
require 'app/Config/Database.php';

$db = \Config\Database::connect();

// Check tweezers products
echo "=== TWEEZERS PRODUCTS WITH VARIANTS ===\n\n";
$result = $db->query("
    SELECT 
        p.id, 
        p.name, 
        p.code,
        p.product_type,
        COUNT(pv.id) as variant_count
    FROM products p
    LEFT JOIN product_variants pv ON pv.product_id = p.id
    WHERE p.name LIKE '%Tweezer%' OR p.code LIKE '%Tweezer%'
    GROUP BY p.id
    LIMIT 10
");

foreach ($result->getResultArray() as $product) {
    echo "Product: " . $product['name'] . " (ID: " . $product['id'] . ", Code: " . $product['code'] . ")\n";
    echo "  Type: " . $product['product_type'] . ", Variants: " . $product['variant_count'] . "\n";
    
    // Get variants for this product
    $variants = $db->query("
        SELECT id, name, art_number, attributes FROM product_variants WHERE product_id = ?
    ", [$product['id']])->getResultArray();
    
    foreach ($variants as $v) {
        echo "  - Variant: {$v['name']} (Art: {$v['art_number']})\n";
        echo "    Attributes: {$v['attributes']}\n";
    }
    echo "\n";
}

// Now test the attribute filter logic
echo "\n=== TESTING ATTRIBUTE FILTER: Shape=Curved AND Size=16 ===\n\n";
$result = $db->query("
    SELECT 
        p.id, 
        p.name, 
        p.code
    FROM products p
    WHERE EXISTS (
        SELECT 1 FROM product_variants pva 
        WHERE pva.product_id = p.id 
        AND LOWER(pva.attributes) LIKE '%curved%' ESCAPE '!'
        AND LOWER(pva.attributes) LIKE '%16%' ESCAPE '!'
    )
    LIMIT 10
");

echo "Products matching both Shape=Curved AND Size=16:\n";
foreach ($result->getResultArray() as $product) {
    echo "- {$product['name']} ({$product['code']})\n";
}
?>
