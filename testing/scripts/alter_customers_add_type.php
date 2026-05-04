<?php
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
$sql = "ALTER TABLE customers ADD COLUMN `type` VARCHAR(50) DEFAULT 'retail';";
if ($mysqli->query($sql)) {
    echo "ALTER TABLE succeeded\n";
} else {
    echo "ALTER TABLE failed: " . $mysqli->error . "\n";
}
$mysqli->close();
