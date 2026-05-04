<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=corelynk_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== VARIANT COUNT ===\n";
    $cnt = $pdo->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
    echo "Total variants: $cnt\n\n";
    
    echo "=== TWEEZERS VARIANTS ===\n";
    $rows = $pdo->query("SELECT p.id, p.name, pv.id as vid, pv.art_number, pv.attributes FROM products p JOIN product_variants pv ON pv.product_id = p.id WHERE p.name LIKE '%Tweezer%' LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    echo "Found: " . count($rows) . " variants\n";
    foreach ($rows as $r) {
        echo "P#{$r['id']} | {$r['name']} | V#{$r['vid']} | ART:{$r['art_number']} | ATTRS:{$r['attributes']}\n";
    }
    
    echo "\n=== LIKE TEST ===\n";
    $c1 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%shape%'")->fetchColumn();
    echo "LIKE %shape%: $c1\n";
    $c2 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%curved%'")->fetchColumn();
    echo "LIKE %curved%: $c2\n";
    $c3 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%shape%' AND LOWER(attributes) LIKE '%curved%'")->fetchColumn();
    echo "LIKE shape+curved: $c3\n";
    
    echo "\n=== EXISTS TEST PER PRODUCT ===\n";
    $rows2 = $pdo->query("SELECT p.id, p.name, EXISTS(SELECT 1 FROM product_variants pva WHERE pva.product_id = p.id AND LOWER(pva.attributes) LIKE '%shape%' AND LOWER(pva.attributes) LIKE '%curved%' AND LOWER(pva.attributes) LIKE '%size%' AND LOWER(pva.attributes) LIKE '%20cm%') as has_match FROM products p WHERE p.name LIKE '%Tweezer%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows2 as $r) {
        echo "P#{$r['id']} | {$r['name']} | has_match:{$r['has_match']}\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
