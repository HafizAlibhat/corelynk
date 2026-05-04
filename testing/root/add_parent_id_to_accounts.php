<?php
// Adds parent_id column to accounts table if missing, for hierarchical COA
ini_set('display_errors','1'); error_reporting(E_ALL);
use mysqli as Mysqli;

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'corelynk_acc_db';

$conn = new Mysqli($host, $user, $pass, $db);
if ($conn->connect_error) { die('DB connection failed: ' . $conn->connect_error); }

$colExists = false;
$res = $conn->query("SHOW COLUMNS FROM accounts LIKE 'parent_id'");
if ($res && $res->num_rows > 0) { $colExists = true; }

if (!$colExists) {
    echo "Adding parent_id to accounts...\n";
    if (!$conn->query("ALTER TABLE accounts ADD COLUMN parent_id INT NULL")) {
        die('Failed to add column: ' . $conn->error);
    }
    // Add FK if not present
    $conn->query("ALTER TABLE accounts ADD CONSTRAINT fk_acc_parent FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL");
    echo "Done.\n";
} else {
    echo "parent_id already exists.\n";
}

$conn->close();
