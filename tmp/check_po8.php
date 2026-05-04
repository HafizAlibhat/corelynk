<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
if ($mysqli->connect_errno) { echo $mysqli->connect_error; exit(1);} 
echo "PO\n";
$r=$mysqli->query("SELECT id, po_number, status, vendor_id FROM purchase_orders WHERE id=8"); while($row=$r->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
echo "PO_LINES\n";
$r=$mysqli->query("SELECT id, po_id, product_id, variant_id, product_variant_id, sku, description, qty, qty_received, unit_price FROM purchase_order_lines WHERE po_id=8 ORDER BY id ASC"); if(!$r){ echo 'ERR '.$mysqli->error.PHP_EOL; exit; } while($row=$r->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
