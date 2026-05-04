<?php
$mysqli = new mysqli('localhost','root','','corelynk_db');
if ($mysqli->connect_errno) {
    echo 'connect error: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}
$sql = "ALTER TABLE customers ADD COLUMN avatar_path VARCHAR(255) NULL";
if ($mysqli->query($sql) === TRUE) {
    echo "OK: avatar_path added\n";
} else {
    echo "ERR: " . $mysqli->error . "\n";
}
$mysqli->close();
