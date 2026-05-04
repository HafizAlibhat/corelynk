<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$res = $mysqli->query("SELECT id, code, name, weight, unit_weight, weight_unit FROM products WHERE id IN (32,33)");
while($row=$res->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
