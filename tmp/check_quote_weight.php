<?php
$mysqli = new mysqli('127.0.0.1','root','','corelynk_db_dev',3306);
if ($mysqli->connect_errno) { echo 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL; exit(1); }
$publicId = '997e9fcb-dc24-4155-8a1e-6c3b6411919a';
$q = $mysqli->prepare("SELECT id, quote_number, status, total_weight, shipping_amount FROM quotations WHERE public_id=? LIMIT 1");
$q->bind_param('s',$publicId); $q->execute(); $res=$q->get_result(); $quote=$res->fetch_assoc();
if (!$quote) { echo "No quotation found\n"; exit(0);} 
echo "QUOTE: ".json_encode($quote).PHP_EOL;
$sql = "SELECT ql.id line_id, ql.product_id, ql.product_code, ql.product_name, ql.quantity, ql.unit_weight line_unit_weight, ql.weight line_weight, ql.weight_unit line_weight_unit,
               p.weight product_weight, p.weight_unit product_weight_unit
        FROM quotation_lines ql
        LEFT JOIN products p ON p.id = ql.product_id
        WHERE ql.quotation_id = ? ORDER BY ql.id ASC";
$s = $mysqli->prepare($sql); $qid=(int)$quote['id']; $s->bind_param('i',$qid); $s->execute(); $rr=$s->get_result();
while($row=$rr->fetch_assoc()){ echo json_encode($row).PHP_EOL; }
$mysqli->close();
?>
