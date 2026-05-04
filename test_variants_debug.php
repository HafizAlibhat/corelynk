<?php
require 'vendor/autoload.php';
$config = new \Config\Database();
$db = $config->connect();

echo "=== ATTRIBUTE FILTER DEBUG ===\n\n";

// Show all tweezers variants with attributes
echo "All Tweezers Variants:\n";
$result = $db->query(
    "SELECT p.id, p.name, pv.id as vid, pv.name as vname, pv.attributes
     FROM products p
     LEFT JOIN product_variants pv ON pv.product_id = p.id  
     WHERE p.name LIKE '%Tweezer%'
     ORDER BY p.id, pv.id"
);

foreach ($result->getResultArray() as $row) {
    if(!$row['vid']) {
        echo "- {$row['name']} (NO VARIANTS)\n";
    } else {
        echo "- Product: {$row['name']} | Variant: {$row['vname']}\n";
        echo "  Attributes: {$row['attributes']}\n";
    }
}

echo "\n\nProducts with Curved attribute:\n";
$result = $db->query(
    "SELECT DISTINCT p.id, p.name
     FROM products p
     INNER JOIN product_variants pv ON pv.product_id = p.id
     WHERE p.name LIKE '%Tweezer%' AND LOWER(pv.attributes) LIKE '%curved%'"
);
echo count($result->getResultArray()) . " found\n";

echo "\nProducts with 16 attribute:\n";
$result = $db->query(
    "SELECT DISTINCT p.id, p.name
     FROM products p
     INNER JOIN product_variants pv ON pv.product_id = p.id
     WHERE p.name LIKE '%Tweezer%' AND LOWER(pv.attributes) LIKE '%16%'"
);
echo count($result->getResultArray()) . " found\n";
?>
