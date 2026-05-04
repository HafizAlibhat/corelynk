<?php
$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'production_management_system';
$port = 3306;

$mysqli = new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "CONNECT_ERR: " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$res = $mysqli->query('DESCRIBE product_processes');
if (! $res) {
    echo "QUERY_ERR: " . $mysqli->error . PHP_EOL;
    exit(1);
}

while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\t" . $row['Type'] . "\n";
}

$mysqli->close();
