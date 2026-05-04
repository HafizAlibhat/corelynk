<?php
// Quick script to check sales_cache table
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if table exists
$result = $mysqli->query("SHOW TABLES LIKE 'sales_cache'");
if ($result->num_rows == 0) {
    echo "sales_cache table does not exist\n";
} else {
    // Check table structure
    $result = $mysqli->query("DESCRIBE sales_cache");
    echo "sales_cache table structure:\n";
    echo str_repeat("=", 80) . "\n";
    printf("%-25s %-20s\n", "Field", "Type");
    echo str_repeat("=", 80) . "\n";
    while ($row = $result->fetch_assoc()) {
        printf("%-25s %-20s\n", $row['Field'], $row['Type']);
    }
    
    // Check data
    $result = $mysqli->query("SELECT COUNT(*) as count FROM sales_cache");
    $row = $result->fetch_assoc();
    echo "\nTotal records: " . $row['count'] . "\n";
    
    $result = $mysqli->query("SELECT COUNT(*) as count FROM sales_cache WHERE remaining_qty > 0");
    $row = $result->fetch_assoc();
    echo "Records with remaining_qty > 0: " . $row['count'] . "\n";
    
    // Show sample data
    $result = $mysqli->query("SELECT * FROM sales_cache WHERE remaining_qty > 0 LIMIT 5");
    if ($result && $result->num_rows > 0) {
        echo "\nSample records with remaining_qty > 0:\n";
        echo str_repeat("=", 120) . "\n";
        while ($row = $result->fetch_assoc()) {
            echo "ID: " . ($row['id'] ?? 'N/A') . "\n";
            echo "  Order Date: " . ($row['date_order'] ?? 'N/A') . "\n";
            echo "  Amount Total: " . ($row['amount_total'] ?? 'N/A') . "\n";
            echo "  Remaining Qty: " . ($row['remaining_qty'] ?? 'N/A') . "\n";
            echo "  Customer: " . ($row['customer_name'] ?? 'N/A') . "\n";
            echo str_repeat("-", 120) . "\n";
        }
    }
}

$mysqli->close();
