<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$res = $mysqli->query("SELECT id, quotation_id, product_id, product_code, quantity, unit_weight, weight, weight_unit FROM quotation_lines WHERE quotation_id=16 ORDER BY id ASC");
if(!$res){ echo 'ERR '.$mysqli->error.PHP_EOL; exit; }
$count=0;
while($row=$res->fetch_assoc()){ $count++; echo json_encode($row).PHP_EOL; }
echo "rows=$count".PHP_EOL;
?>
