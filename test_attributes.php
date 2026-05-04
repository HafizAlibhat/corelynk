<?php
/**
 * Debug Attribute Filtering
 * This script checks if the variant attribute filtering is working correctly
 */

require 'vendor/autoload.php';
$config = new \Config\Database();
$db = $config->connect();

echo "=== CHECKING TWEEZERS PRODUCTS AND THEIR VARIANT ATTRIBUTES ===\n\n";

// Get all tweezers products and their variants
$result = $db->query(
    "SELECT p.id, p.name, p.code, p.product_type, COUNT(pv.id) as variant_count
     FROM products p
     LEFT JOIN product_variants pv ON pv.product_id = p.id  
     WHERE p.name LIKE '%Tweezer%' OR p.code LIKE '%Tweezer%'
     GROUP BY p.id
     ORDER BY p.name"
);

foreach ($result->getResultArray() as $product) {
    echo "Product: {$product['name']} (Code: {$product['code']}, Type: {$product['product_type']})\n";
    echo "  Variants: {$product['variant_count']}\n";
    
    if ($product['variant_count'] > 0) {
        $variants = $db->query(
            "SELECT id, name, art_number, attributes FROM product_variants WHERE product_id = ?",
            [$product['id']]
        )->getResultArray();
        
        foreach ($variants as $v) {
            echo "    - Name: {$v['name']}, Art: {$v['art_number']}\n";
            echo "      Attributes: {$v['attributes']}\n";
        }
    }
    echo "\n";
}

echo "\n=== TESTING ATTRIBUTE FILTER: 'Shape' = 'Curved' ===\n";
$result = $db->query(
    "SELECT DISTINCT p.id, p.name, COUNT(pv.id) as variant_count
     FROM products p
     INNER JOIN product_variants pv ON pv.product_id = p.id
     WHERE p.name LIKE '%Tweezer%' 
       AND LOWER(pv.attributes) LIKE '%curved%'
     GROUP BY p.id"
);
echo "Products with Curved variant: " . count($result->getResultArray()) . "\n";
foreach ($result->getResultArray() as $row) {
    echo "- {$row['name']} ({$row['variant_count']} variants)\n";
}

echo "\n=== TESTING ATTRIBUTE FILTER: 'Size' = '16' ===\n";
$result = $db->query(
    "SELECT DISTINCT p.id, p.name, COUNT(pv.id) as variant_count
     FROM products p
     INNER JOIN product_variants pv ON pv.product_id = p.id
     WHERE p.name LIKE '%Tweezer%'
       AND LOWER(pv.attributes) LIKE '%16%'
     GROUP BY p.id"
);
echo "Products with Size 16: " . count($result->getResultArray()) . "\n";
foreach ($result->getResultArray() as $row) {
    echo "- {$row['name']} ({$row['variant_count']} variants)\n";
}

echo "\n=== TESTING COMBINED: 'Shape' = 'Curved' AND 'Size' = '16' ===\n";
$result = $db->query(
    "SELECT DISTINCT p.id, p.name, COUNT(pv.id) as variant_count
     FROM products p
     INNER JOIN product_variants pv ON pv.product_id = p.id
     WHERE p.name LIKE '%Tweezer%'
       AND LOWER(pva.attributes) LIKE '%curved%'
       AND LOWER(pv.attributes) LIKE '%16%'
     GROUP BY p.id"
);
echo "Products with BOTH Curved AND Size 16: " . count($result->getResultArray()) . "\n";
foreach ($result->getResultArray() as $row) {
    echo "- {$row['name']} ({$row['variant_count']} variants)\n";
}
?>
