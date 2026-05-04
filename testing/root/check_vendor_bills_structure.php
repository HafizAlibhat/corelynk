<?php
// Quick script to check vendor_bills table structure
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$result = $mysqli->query("DESCRIBE vendor_bills");

if ($result) {
    echo "vendor_bills table structure:\n";
    echo str_repeat("=", 80) . "\n";
    printf("%-20s %-15s %-10s %-10s\n", "Field", "Type", "Null", "Key");
    echo str_repeat("=", 80) . "\n";
    
    while ($row = $result->fetch_assoc()) {
        printf("%-20s %-15s %-10s %-10s\n", 
            $row['Field'], 
            $row['Type'], 
            $row['Null'], 
            $row['Key']
        );
    }
} else {
    echo "Error: " . $mysqli->error;
}

// Also check if we have any vendor bills data
$result = $mysqli->query("SELECT COUNT(*) as count FROM vendor_bills WHERE status = 'confirmed'");
if ($result) {
    $row = $result->fetch_assoc();
    echo "\n\nConfirmed vendor bills count: " . $row['count'] . "\n";
    
    // Check currency info if we have bills
    if ($row['count'] > 0) {
        $result2 = $mysqli->query("SELECT bill_number, total_amount, balance, currency_code FROM vendor_bills WHERE status = 'confirmed' LIMIT 5");
        if ($result2) {
            echo "\nSample bills:\n";
            while ($bill = $result2->fetch_assoc()) {
                echo "Bill #" . ($bill['bill_number'] ?: 'N/A') . " - Amount: " . $bill['total_amount'] . " - Balance: " . $bill['balance'] . " - Currency: " . ($bill['currency_code'] ?: 'NULL') . "\n";
            }
        }
        
        // Check total payables by currency
        $result3 = $mysqli->query("SELECT currency_code, SUM(balance) as total_payable FROM vendor_bills WHERE status = 'confirmed' AND balance > 0 GROUP BY currency_code");
        if ($result3) {
            echo "\nTotal Payables by Currency:\n";
            while ($row = $result3->fetch_assoc()) {
                echo ($row['currency_code'] ?: 'NULL') . ": " . number_format($row['total_payable'], 2) . "\n";
            }
        }
    }
}

$mysqli->close();
