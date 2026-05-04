<?php
// Insert a quick test customer directly to prove DB writes
$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$db   = 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "DB connect error: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$code = 'TEST-' . time();
$name = 'Automated Test Customer ' . date('YmdHis');
$stmt = $mysqli->prepare("INSERT INTO customers (customer_code, name, status, created_at) VALUES (?, ?, 'active', NOW())");
if (!$stmt) {
    echo "Prepare failed: " . $mysqli->error . PHP_EOL;
    exit(2);
}
$stmt->bind_param('ss', $code, $name);
if (!$stmt->execute()) {
    echo "Execute failed: " . $stmt->error . PHP_EOL;
    exit(3);
}

echo "Inserted test customer id=" . $stmt->insert_id . " code={$code}\n";
$stmt->close();
$mysqli->close();
return 0;
