<?php
$mysqli = new mysqli('localhost','root','', 'corelynk_db', 3306);
if ($mysqli->connect_errno) { echo "CONN_ERR: " . $mysqli->connect_error . "\n"; exit(1); }
$sql = "ALTER TABLE `customer_invoices` CHANGE `status` `status` ENUM('draft','confirmed','issued','partially_paid','paid','overdue','cancelled','posted') NOT NULL DEFAULT 'draft'";
if (!$mysqli->query($sql)) {
    echo "ALTER_ERR: " . $mysqli->error . "\n";
    exit(1);
}
echo "ALTER_OK\n";
