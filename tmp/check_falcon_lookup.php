<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$like = "%Falcon Edge Damascus Knife%";
$stmt = $mysqli->prepare("SELECT id, name, code, product_type, detailed_type FROM products WHERE name LIKE ? OR code='RI-TK-02945'");
$stmt->bind_param('s',$like); $stmt->execute(); $r=$stmt->get_result();
while($row=$r->fetch_assoc()){ echo 'PRODUCT '.json_encode($row).PHP_EOL; }
$stmt = $mysqli->prepare("SELECT id, product_id, art_number, name FROM product_variants WHERE name LIKE ? OR art_number='RI-TK-02945'");
$stmt->bind_param('s',$like); $stmt->execute(); $r=$stmt->get_result();
while($row=$r->fetch_assoc()){ echo 'VARIANT '.json_encode($row).PHP_EOL; }
?>
