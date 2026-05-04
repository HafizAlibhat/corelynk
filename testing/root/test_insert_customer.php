<?php
// Minimal direct MySQL insert test without CI bootstrap
// Uses default localhost/root/blank password and database corelynk_db from .env

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $dbName, 3306);
if ($mysqli->connect_errno) {
    echo "ERROR: MySQL connect failed: {$mysqli->connect_error}\n";
    exit(1);
}

$res = $mysqli->query("SHOW TABLES LIKE 'customers'");
if (!$res || $res->num_rows === 0) {
    echo "ERROR: 'customers' table does not exist in database {$dbName}\n";
    exit(1);
}

$name = 'Test Customer ' . date('Ymd_His');
$code = 'C-' . mt_rand(1000, 9999);
$now = date('Y-m-d H:i:s');

$stmt = $mysqli->prepare("INSERT INTO customers (customer_code, name, type, status, created_at) VALUES (?,?,?,?,?)");
if (!$stmt) {
    echo "ERROR: Prepare failed: {$mysqli->error}\n";
    exit(1);
}

$type = 'regular';
$status = 'active';
$stmt->bind_param('sssss', $code, $name, $type, $status, $now);
$ok = $stmt->execute();
if (!$ok) {
    echo "FAIL: Execute failed: {$stmt->error}\n";
    exit(1);
}

$id = $stmt->insert_id;
echo "OK: Inserted customer id={$id}, name={$name}\n";
$stmt->close();
$mysqli->close();
