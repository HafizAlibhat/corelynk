<?php
require __DIR__ . '/../vendor/autoload.php';
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db;charset=utf8', 'root', '');
$rows = $pdo->query("SELECT p.id, p.name, pv.id as vid, pv.art_number, pv.attributes FROM products p JOIN product_variants pv ON pv.product_id = p.id WHERE p.name LIKE '%Tweezer%' LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
echo "Total variants: " . count($rows) . "\n";
foreach ($rows as $r) {
    echo "P#{$r['id']} | {$r['name']} | V#{$r['vid']} | ART:{$r['art_number']} | ATTRS:{$r['attributes']}\n";
}

// Also test the LIKE query that the model uses
echo "\n--- Testing LIKE queries ---\n";
$test = $pdo->query("SELECT COUNT(*) as cnt FROM product_variants pv WHERE LOWER(pv.attributes) LIKE '%shape%' AND LOWER(pv.attributes) LIKE '%curved%'")->fetch(PDO::FETCH_ASSOC);
echo "Variants matching Shape+Curved: {$test['cnt']}\n";

$test2 = $pdo->query("SELECT COUNT(*) as cnt FROM product_variants pv WHERE LOWER(pv.attributes) LIKE '%size%' AND LOWER(pv.attributes) LIKE '%20cm%'")->fetch(PDO::FETCH_ASSOC);
echo "Variants matching Size+20cm: {$test2['cnt']}\n";

// Check the EXISTS subquery for product 1 (first tweezers)
$rows2 = $pdo->query("SELECT p.id, p.name, EXISTS(SELECT 1 FROM product_variants pva WHERE pva.product_id = p.id AND LOWER(pva.attributes) LIKE '%shape%' AND LOWER(pva.attributes) LIKE '%curved%' AND LOWER(pva.attributes) LIKE '%size%' AND LOWER(pva.attributes) LIKE '%20cm%') as has_match FROM products p WHERE p.name LIKE '%Tweezer%'")->fetchAll(PDO::FETCH_ASSOC);
echo "\n--- EXISTS check per product ---\n";
foreach ($rows2 as $r) {
    echo "P#{$r['id']} | {$r['name']} | has_match:{$r['has_match']}\n";
}
