<?php
// Direct database connection
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "Checking product_variants table columns...\n";

$columns = ['cost_price', 'sale_price', 'vendor_price_pkr'];

foreach ($columns as $column) {
    echo "\nChecking column: $column\n";
    
    $result = $db->query("SHOW COLUMNS FROM product_variants LIKE '$column'");
    $exists = $result && $result->num_rows > 0;
    
    if ($exists) {
        echo "✓ Column '$column' already exists\n";
    } else {
        echo "✗ Column '$column' does not exist. Adding...\n";
        $success = $db->query("ALTER TABLE product_variants ADD COLUMN $column DECIMAL(15,2) NULL");
        if ($success) {
            echo "✓ Column '$column' added successfully\n";
        } else {
            echo "✗ Failed to add column '$column': " . $db->error . "\n";
        }
    }
}

echo "\n\nFinal table structure:\n";
$result = $db->query("SHOW COLUMNS FROM product_variants");
if ($result) {
    while ($col = $result->fetch_assoc()) {
        echo sprintf("%-25s %-20s %-8s\n", $col['Field'], $col['Type'], $col['Null']);
    }
}

$db->close();
echo "\nDone!\n";
