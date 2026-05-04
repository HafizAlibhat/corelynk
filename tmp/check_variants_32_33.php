<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$res = $mysqli->query("SELECT id, product_id, art_number, name, weight FROM product_variants WHERE product_id IN (32,33) ORDER BY product_id,id");
if(!$res){ echo 'ERR '.$mysqli->error.PHP_EOL; exit; }
$count=0; while($row=$res->fetch_assoc()){ $count++; echo json_encode($row).PHP_EOL; }
echo "rows=$count".PHP_EOL;
?>
