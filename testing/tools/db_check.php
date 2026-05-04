<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db = 'corelynk_db';
$port = 3306;

$mysqli = new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "Connect failed: " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$name = 'Surgical Instruments';
$stmt = $mysqli->prepare('SELECT id, name, prefix, start_range, end_range, next_number FROM product_categories WHERE name = ? LIMIT 1');
$stmt->bind_param('s', $name);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
var_export($row);
echo PHP_EOL;
$stmt->close();
$mysqli->close();
