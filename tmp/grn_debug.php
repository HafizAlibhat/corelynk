<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== stock_movements for grn_id=9 ===\n";
$s = $pdo->query('SELECT id, reference_type, reference_id, warehouse_id, location_id, movement_type FROM stock_movements WHERE reference_id = 9 ORDER BY id');
foreach ($s->fetchAll(PDO::FETCH_ASSOC) as $r) { print_r($r); }

echo "\n=== purchase_grns id=9 ===\n";
$s2 = $pdo->query('SELECT * FROM purchase_grns WHERE id=9');
foreach ($s2->fetchAll(PDO::FETCH_ASSOC) as $r) { print_r($r); }

echo "\n=== products WEIGHT columns ===\n";
$s3 = $pdo->query('SHOW COLUMNS FROM products LIKE "%weight%"');
foreach ($s3->fetchAll(PDO::FETCH_ASSOC) as $r) { print_r($r); }

echo "\n=== product variants WEIGHT columns ===\n";
$s3b = $pdo->query('SHOW COLUMNS FROM product_variants LIKE "%weight%"');
foreach ($s3b->fetchAll(PDO::FETCH_ASSOC) as $r) { print_r($r); }

echo "\n=== purchase_grn_lines for grn 9 (with product data) ===\n";
$s4 = $pdo->query('SELECT gl.id, gl.product_id, gl.variant_id, gl.qty_received, p.weight, p.description FROM purchase_grn_lines gl LEFT JOIN products p ON p.id = gl.product_id WHERE gl.grn_id = 9');
foreach ($s4->fetchAll(PDO::FETCH_ASSOC) as $r) { print_r($r); }

echo "\n=== ALL products columns ===\n";
$s5 = $pdo->query('SHOW COLUMNS FROM products');
foreach ($s5->fetchAll(PDO::FETCH_ASSOC) as $r) { echo $r['Field'].' ('.$r['Type'].')'.PHP_EOL; }

echo "\n=== purchase_grn_line_issues table (exists?) ===\n";
$s6 = $pdo->query("SHOW TABLES LIKE 'purchase_grn_line_issues'");
$t = $s6->fetchAll(PDO::FETCH_COLUMN);
echo empty($t) ? "TABLE DOES NOT EXIST\n" : "Table exists: ".$t[0]."\n";
if (!empty($t)) {
    $s7 = $pdo->query('SHOW COLUMNS FROM purchase_grn_line_issues');
    foreach ($s7->fetchAll(PDO::FETCH_ASSOC) as $r) { echo $r['Field'].' ('.$r['Type'].')'.PHP_EOL; }
}
