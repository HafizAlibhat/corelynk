<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
if ($mysqli->connect_errno) { echo $mysqli->connect_error; exit(1);} 
$cols=[]; $r=$mysqli->query("SHOW COLUMNS FROM purchase_order_lines"); while($row=$r->fetch_assoc()){ $cols[]=$row['Field']; }
echo implode(',', $cols).PHP_EOL;
$sql = "SELECT id, po_id, product_id, variant_id, sku, description, qty, qty_received, unit_price FROM purchase_order_lines WHERE po_id=8 ORDER BY id ASC";
$r=$mysqli->query($sql); if(!$r){ echo 'ERR '.$mysqli->error.PHP_EOL; exit; } while($row=$r->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
