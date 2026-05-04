<?php
$db = new mysqli('127.0.0.1', 'root', '', 'corelynk_db');
if ($db->connect_errno) {
    echo "CONNECT_ERR: " . $db->connect_error . PHP_EOL;
    exit(1);
}
$sql = "ALTER TABLE `customer_addresses` ADD COLUMN `country_name` VARCHAR(255) DEFAULT NULL";
if ($db->query($sql) === TRUE) {
    echo "OK" . PHP_EOL;
    exit(0);
} else {
    echo "ERR: " . $db->error . PHP_EOL;
    exit(1);
}
