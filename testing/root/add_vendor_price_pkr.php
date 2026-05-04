<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

// Check if vendor_price_pkr exists
$result = $mysqli->query("SHOW COLUMNS FROM products LIKE 'vendor_price_pkr'");
if ($result && $result->num_rows == 0) {
    echo "Adding vendor_price_pkr column...\n";
    $sql = "ALTER TABLE products ADD COLUMN vendor_price_pkr DECIMAL(15,2) NULL AFTER vendor_price";
    if ($mysqli->query($sql)) {
        echo "✓ vendor_price_pkr column added successfully\n";
    } else {
        echo "✗ Error: " . $mysqli->error . "\n";
    }
} else {
    echo "vendor_price_pkr column already exists\n";
}

$mysqli->close();
?>
