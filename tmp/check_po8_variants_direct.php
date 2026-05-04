<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$res = $mysqli->query("SELECT id, product_id, art_number, name FROM product_variants WHERE id IN (SELECT DISTINCT variant_id FROM purchase_order_lines WHERE po_id=8 AND variant_id IS NOT NULL)");
if(!$res){ echo 'No direct variant refs or query error: '.$mysqli->error.PHP_EOL; exit; }
while($row=$res->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
