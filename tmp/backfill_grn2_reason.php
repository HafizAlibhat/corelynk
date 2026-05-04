<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=corelynk_db_dev', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$check = $pdo->query("SELECT id, over_received_qty, over_receipt_reason_type, over_receipt_reason_details FROM purchase_grn_lines WHERE grn_id=2 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "Before:\n";
print_r($check);

$pdo->exec("UPDATE purchase_grn_lines SET over_receipt_reason_type='vendor_extra', over_receipt_reason_details='Vendor sent extra pcs and accepted as payable' WHERE grn_id=2 AND COALESCE(over_received_qty,0)>0 AND COALESCE(TRIM(over_receipt_reason_type),'')=''");

$check2 = $pdo->query("SELECT id, over_received_qty, over_receipt_reason_type, over_receipt_reason_details FROM purchase_grn_lines WHERE grn_id=2 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "\nAfter:\n";
print_r($check2);
