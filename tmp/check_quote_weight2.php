<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
if ($mysqli->connect_errno) { echo 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL; exit(1); }
$publicId = '997e9fcb-dc24-4155-8a1e-6c3b6411919a';
$q = $mysqli->prepare("SELECT id, quote_number, status, total_weight, shipping_amount FROM quotations WHERE public_id=? LIMIT 1");
$q->bind_param('s',$publicId); $q->execute(); $quote=$q->get_result()->fetch_assoc();
if (!$quote){ echo "No quotation found\n"; exit; }
$pid = (int)$quote['id'];
echo "QUOTE: ".json_encode($quote).PHP_EOL;
$cols=[]; $rc=$mysqli->query("SHOW COLUMNS FROM products"); while($r=$rc->fetch_assoc()){ $cols[]=$r['Field']; }
echo 'PRODUCT COLS HAS weight_unit='.(in_array('weight_unit',$cols)?'yes':'no').', unit_weight='.(in_array('unit_weight',$cols)?'yes':'no').', weight='.(in_array('weight',$cols)?'yes':'no').PHP_EOL;
$select = "ql.id line_id, ql.product_id, ql.product_code, ql.product_name, ql.quantity, ql.unit_weight line_unit_weight, ql.weight line_weight, ql.weight_unit line_weight_unit";
if (in_array('weight',$cols)) $select .= ", p.weight product_weight";
if (in_array('weight_unit',$cols)) $select .= ", p.weight_unit product_weight_unit";
if (in_array('unit_weight',$cols)) $select .= ", p.unit_weight product_unit_weight";
$sql = "SELECT $select FROM quotation_lines ql LEFT JOIN products p ON p.id = ql.product_id WHERE ql.quotation_id = $pid ORDER BY ql.id ASC";
$res=$mysqli->query($sql);
if(!$res){ echo 'SQL ERR: '.$mysqli->error.PHP_EOL; exit; }
while($row=$res->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
?>
