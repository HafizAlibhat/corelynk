<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
echo "location 28:\n";
print_r($pdo->query('SELECT id,name,warehouse_id FROM warehouse_locations WHERE id=28')->fetchAll(PDO::FETCH_ASSOC));
echo "\nGRN line 9 full:\n";
print_r($pdo->query('SELECT * FROM purchase_grn_lines WHERE id=9')->fetchAll(PDO::FETCH_ASSOC));
echo "\nstock_balances for product 37:\n";
print_r($pdo->query('SELECT * FROM stock_balances WHERE product_id=37')->fetchAll(PDO::FETCH_ASSOC));
