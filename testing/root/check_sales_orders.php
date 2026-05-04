<?php
// Quick script to check sales_orders table
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check if table exists
$result = $mysqli->query("SHOW TABLES LIKE 'sales_orders'");
if ($result->num_rows == 0) {
    echo "sales_orders table does not exist\n";
} else {
    // Check data
    $result = $mysqli->query("SELECT COUNT(*) as count FROM sales_orders");
    $row = $result->fetch_assoc();
    echo "Total sales orders: " . $row['count'] . "\n";
    
    // Check by status
    $result = $mysqli->query("SELECT status, COUNT(*) as count, SUM(total_amount) as total FROM sales_orders GROUP BY status");
    if ($result && $result->num_rows > 0) {
        echo "\nSales orders by status:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  " . ($row['status'] ?: 'NULL') . ": " . $row['count'] . " orders, Total: " . number_format($row['total'], 2) . "\n";
        }
    }
    
    // Check for unpaid/pending invoices
    echo "\n\nChecking invoices table...\n";
    $result = $mysqli->query("SHOW TABLES LIKE 'invoices'");
    if ($result->num_rows > 0) {
        $result = $mysqli->query("SELECT COUNT(*) as count, SUM(balance) as total_balance FROM invoices WHERE status != 'paid' AND balance > 0");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Unpaid invoices: " . $row['count'] . ", Total balance: " . number_format($row['total_balance'], 2) . "\n";
        }
    } else {
        echo "invoices table does not exist\n";
    }
    
    // Check for customer_invoices
    echo "\nChecking customer_invoices table...\n";
    $result = $mysqli->query("SHOW TABLES LIKE 'customer_invoices'");
    if ($result->num_rows > 0) {
        $result = $mysqli->query("SELECT status, COUNT(*) as count, SUM(total_amount) as total FROM customer_invoices GROUP BY status");
        if ($result && $result->num_rows > 0) {
            echo "Customer invoices by status:\n";
            while ($row = $result->fetch_assoc()) {
                echo "  " . ($row['status'] ?: 'NULL') . ": " . $row['count'] . " invoices, Total: " . number_format($row['total'] ?? 0, 2) . "\n";
            }
        }
        
        // Check balance
        $result = $mysqli->query("SELECT SUM(balance) as total_balance FROM customer_invoices WHERE balance > 0");
        if ($result) {
            $row = $result->fetch_assoc();
            echo "Total receivable balance: " . number_format($row['total_balance'] ?? 0, 2) . "\n";
        }
    } else {
        echo "customer_invoices table does not exist\n";
    }
}

$mysqli->close();
