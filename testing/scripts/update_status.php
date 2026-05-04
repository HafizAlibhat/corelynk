<?php
$mysqli = new mysqli('localhost','root','', 'corelynk_db', 3306);
if ($mysqli->connect_errno) { echo "CONN_ERR: " . $mysqli->connect_error . "\n"; exit(1); }
$id = $argv[1] ?? 8;
if (!$mysqli->query("UPDATE customer_invoices SET status='confirmed' WHERE id = " . (int)$id)) {
    echo "UPD_ERR: " . $mysqli->error . "\n";
    exit(1);
}
$res = $mysqli->query("SELECT id, status, updated_at FROM customer_invoices WHERE id = " . (int)$id);
if ($res) { $row = $res->fetch_assoc(); echo json_encode($row, JSON_PRETTY_PRINT) . "\n"; } else { echo "SQL_ERR: " . $mysqli->error . "\n"; }
