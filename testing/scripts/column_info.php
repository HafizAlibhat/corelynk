<?php
$mysqli = new mysqli('localhost','root','', 'corelynk_db', 3306);
if ($mysqli->connect_errno) { echo "CONN_ERR: " . $mysqli->connect_error . "\n"; exit(1); }
$res = $mysqli->query("SHOW FULL COLUMNS FROM customer_invoices LIKE 'status'");
if (!$res) { echo "ERR: " . $mysqli->error . "\n"; exit(1); }
$row = $res->fetch_assoc();
echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
