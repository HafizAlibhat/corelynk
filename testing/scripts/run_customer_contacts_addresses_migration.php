<?php
// Run migration for customer_contacts and customer_addresses tables
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_errno) {
    die('Connect Error: ' . $mysqli->connect_error);
}
$sql = file_get_contents(__DIR__ . '/../database/migrations/20251202_create_customer_contacts_and_addresses.sql');
if ($mysqli->multi_query($sql)) {
    echo "Migration applied successfully.\n";
} else {
    echo "Migration failed: " . $mysqli->error . "\n";
}
$mysqli->close();
