<?php
$output = [];
try {
    $pdo = new PDO('mysql:host=localhost;dbname=corelynk_db;charset=utf8', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $cnt = $pdo->query("SELECT COUNT(*) FROM product_variants")->fetchColumn();
    $output[] = "Total variants: $cnt";
    
    $rows = $pdo->query("SELECT p.id, p.name, pv.id as vid, pv.art_number, pv.attributes FROM products p JOIN product_variants pv ON pv.product_id = p.id WHERE p.name LIKE '%Tweezer%' LIMIT 30")->fetchAll(PDO::FETCH_ASSOC);
    $output[] = "Tweezers variants found: " . count($rows);
    foreach ($rows as $r) {
        $output[] = "P#{$r['id']} | {$r['name']} | V:{$r['vid']} | ART:{$r['art_number']} | ATTRS:{$r['attributes']}";
    }
    
    $c1 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%shape%'")->fetchColumn();
    $output[] = "LIKE %shape%: $c1";
    $c2 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%curved%'")->fetchColumn();
    $output[] = "LIKE %curved%: $c2";
    $c3 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%shape%' AND LOWER(attributes) LIKE '%curved%'")->fetchColumn();
    $output[] = "LIKE shape AND curved: $c3";
    $c4 = $pdo->query("SELECT COUNT(*) FROM product_variants WHERE LOWER(attributes) LIKE '%size%' AND LOWER(attributes) LIKE '%20cm%'")->fetchColumn();
    $output[] = "LIKE size AND 20cm: $c4";
    
    $rows2 = $pdo->query("SELECT p.id, p.name, EXISTS(SELECT 1 FROM product_variants pva WHERE pva.product_id = p.id AND LOWER(pva.attributes) LIKE '%shape%' AND LOWER(pva.attributes) LIKE '%curved%' AND LOWER(pva.attributes) LIKE '%size%' AND LOWER(pva.attributes) LIKE '%20cm%') as has_match FROM products p WHERE p.name LIKE '%Tweezer%'")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows2 as $r) {
        $output[] = "EXISTS check P#{$r['id']} | {$r['name']} | has_match:{$r['has_match']}";
    }
    
} catch (Exception $e) {
    $output[] = "ERROR: " . $e->getMessage();
}

file_put_contents(__DIR__ . '/dbcheck_result.txt', implode("\n", $output) . "\n");
echo "Done. Check dbcheck_result.txt\n";
