<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
$queries = [
  "SELECT id, name, code, product_type, detailed_type FROM products WHERE id=29",
  "SELECT id, product_id, art_number, name, weight FROM product_variants WHERE art_number IN ('RI-TK-02945','RI-DH-02947','RI-KS-02942','RI-KS-02943','RI-DB-02946') ORDER BY id",
  "SELECT id, name, code, product_type, detailed_type FROM products WHERE code IN ('RI-TK-02945','RI-DH-02947','RI-KS-02944') ORDER BY id"
];
foreach($queries as $sql){ echo "SQL: $sql\n"; $r=$mysqli->query($sql); if(!$r){ echo 'ERR '.$mysqli->error.PHP_EOL; continue; } while($row=$r->fetch_assoc()){ echo json_encode($row).PHP_EOL; } }
?>
