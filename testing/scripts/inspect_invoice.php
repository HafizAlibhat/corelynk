<?php
$mysqli = new mysqli('localhost','root','', 'corelynk_db', 3306);
if ($mysqli->connect_errno) {
    echo "CONN_ERR: " . $mysqli->connect_error . "\n";
    exit(1);
}
$id = $argv[1] ?? '8';
$res = $mysqli->query("SHOW COLUMNS FROM customer_invoices");
if (!$res) { echo "SQL_ERR: " . $mysqli->error . "\n"; exit(1); }
$cols = [];
while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
echo "Columns: " . implode(',', $cols) . "\n";

$select = "SELECT * FROM customer_invoices WHERE id = " . (int)$id;
$res = $mysqli->query($select);
if (!$res) {
    echo "SQL_ERR: " . $mysqli->error . "\n";
    exit(1);
}
$row = $res->fetch_assoc();
if (!$row) {
    echo "NO_ROW\n";
    exit(0);
}
echo json_encode($row, JSON_PRETTY_PRINT) . "\n";
