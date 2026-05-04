<?php
$m = new mysqli('127.0.0.1','root','','corelynk_db');
if ($m->connect_errno) { echo 'CONNECT_ERR: '.$m->connect_error.PHP_EOL; exit(1); }
$po_number = 'TEST-PO-001';
$rfq_id = 2;
$vendor_id = 11;
$subtotal = 0.00;
$total = 0.00;
$created_by = 1;
$created_at = date('Y-m-d H:i:s');
$sql = "INSERT INTO purchase_orders (po_number, rfq_id, vendor_id, status, subtotal, total, created_by, created_at) VALUES ('".
    $m->real_escape_string($po_number)."', $rfq_id, $vendor_id, 'draft', '".$subtotal."', '".$total."', ".($created_by? $created_by:'NULL').", '".$created_at."')";
if ($m->query($sql)) {
    echo "Inserted PO id: " . $m->insert_id . PHP_EOL;
} else {
    echo "INSERT_ERR: " . $m->error . PHP_EOL;
}
