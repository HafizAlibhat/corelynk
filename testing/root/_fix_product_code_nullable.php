<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) { die('Connect failed: ' . $db->connect_error); }

// 1. Make products.code nullable so variable products (no code) can coexist safely
$db->query("ALTER TABLE products MODIFY COLUMN `code` VARCHAR(50) DEFAULT NULL");
if ($db->error) {
    echo "ERROR: " . $db->error . PHP_EOL;
} else {
    echo "OK: products.code is now nullable" . PHP_EOL;
}

// 2. Fix any existing variable products that have code='' — set to NULL
$db->query("UPDATE products SET code = NULL WHERE product_type = 'variable' AND (code = '' OR code IS NULL)");
echo "Fixed " . $db->affected_rows . " variable product(s) with empty code" . PHP_EOL;

$db->close();
echo "Done" . PHP_EOL;
