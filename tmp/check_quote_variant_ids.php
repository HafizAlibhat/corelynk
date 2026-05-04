<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$res = $mysqli->query("SELECT id, product_id, product_variant_id, product_code, quantity FROM quotation_lines WHERE quotation_id=16 ORDER BY id");
while($row=$res->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
