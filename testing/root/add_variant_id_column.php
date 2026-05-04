<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if variant_id column exists
$result = $mysqli->query("SHOW COLUMNS FROM purchase_order_lines LIKE 'variant_id'");
if ($result->num_rows == 0) {
    // Column doesn't exist, add it
    $sql = "ALTER TABLE purchase_order_lines ADD COLUMN variant_id INT(11) NULL AFTER product_id";
    if ($mysqli->query($sql)) {
        echo "Successfully added variant_id column to purchase_order_lines\n";
    } else {
        echo "Error adding column: " . $mysqli->error . "\n";
    }
} else {
    echo "variant_id column already exists\n";
}

$mysqli->close();
?>
