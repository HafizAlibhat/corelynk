<?php
$mysqli = new mysqli('127.0.0.1','root','', 'corelynk_db');
if ($mysqli->connect_errno) { echo 'CONNECT_ERR:'.$mysqli->connect_error; exit(1);} 
$res = $mysqli->query('SELECT id, po_number, rfq_id, status, created_at FROM purchase_orders ORDER BY id DESC LIMIT 20');
if (! $res) { echo 'QUERY_ERR:'.$mysqli->error; exit(1);} 
while($r = $res->fetch_assoc()){ echo json_encode($r)."\n"; }
?>