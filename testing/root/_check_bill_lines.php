<?php
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '');
foreach (['vendor_bill_lines', 'purchase_order_lines'] as $t) {
    $cols = $pdo->query("DESCRIBE $t")->fetchAll(PDO::FETCH_COLUMN);
    echo "$t: " . implode(', ', $cols) . PHP_EOL;
}
