<?php
$m = new mysqli('127.0.0.1','root','', 'corelynk_db');
if ($m->connect_errno) { echo 'CONNECT_ERR: '.$m->connect_error.PHP_EOL; exit(1); }
$res = $m->query('SELECT id,po_number,rfq_id,vendor_id,status,subtotal,total,created_at FROM purchase_orders ORDER BY id DESC LIMIT 5');
if (!$res) { echo 'ERR: '.$m->error.PHP_EOL; exit(1); }
while ($r = $res->fetch_assoc()) {
    echo json_encode($r, JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
