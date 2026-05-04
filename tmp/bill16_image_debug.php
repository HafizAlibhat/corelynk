<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$bill = $pdo->query("SELECT id, public_id, po_id, bill_number FROM vendor_bills WHERE id=16 OR public_id='cdf999d5-a823-4b58-9f25-28018fe0b5bb' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
echo "Bill:\n"; print_r($bill);
if (!$bill) exit;
$billId = (int)$bill['id'];

$lines = $pdo->query("SELECT * FROM vendor_bill_lines WHERE vendor_bill_id={$billId}")->fetchAll(PDO::FETCH_ASSOC);
echo "\nBill lines:\n"; print_r($lines);

foreach ($lines as $ln) {
  $pid = (int)($ln['product_id'] ?? 0);
  $vid = (int)($ln['variant_id'] ?? 0);
  if ($pid > 0) {
    $p = $pdo->query("SELECT id,name,code,images,detailed_type,unit FROM products WHERE id={$pid}")->fetch(PDO::FETCH_ASSOC);
    echo "\nProduct {$pid}:\n"; print_r($p);
  }
  if ($vid > 0) {
    $v = $pdo->query("SELECT id,product_id,art_number,image FROM product_variants WHERE id={$vid}")->fetch(PDO::FETCH_ASSOC);
    echo "\nVariant {$vid}:\n"; print_r($v);
  }
}
