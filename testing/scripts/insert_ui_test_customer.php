<?php
// Direct DB insert test for UI payload
$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$db   = 'corelynk_db';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "DB connect error: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$code = 'C-UI';
$name = 'UI Test Customer Script';
$type = 'retail';
$status = 'active';
$created_by = null;
$sql = "INSERT INTO customers (customer_code, name, type, status, created_by) VALUES (?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    echo "Prepare failed: " . $mysqli->error . "\n";
    exit(2);
}
$stmt->bind_param('ssssi', $code, $name, $type, $status, $created_by);
if (!$stmt->execute()) {
    echo "Execute failed: " . $stmt->error . "\n";
    exit(3);
}
echo "Inserted test customer id=" . $stmt->insert_id . "\n";
$stmt->close();
$mysqli->close();
