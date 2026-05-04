<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$ids = [20,27,28,29,30];
foreach($ids as $pid){
  echo "PRODUCT $pid\n";
  $r=$mysqli->query("SELECT id, name, code, product_type, detailed_type FROM products WHERE id=$pid");
  while($row=$r->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
  $r=$mysqli->query("SELECT id, product_id, art_number, name, attributes, weight FROM product_variants WHERE product_id=$pid ORDER BY id ASC");
  $count=0; while($row=$r->fetch_assoc()){ $count++; echo json_encode($row).PHP_EOL; }
  echo "variant_count=$count\n";
}
?>
