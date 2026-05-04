<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$r=$mysqli->query("SELECT id, po_id, product_id, variant_id, description, qty, unit_price, qty_received, receive_status FROM purchase_order_lines WHERE po_id=8 ORDER BY id ASC");
if(!$r){ echo 'ERR '.$mysqli->error.PHP_EOL; exit; }
while($row=$r->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
