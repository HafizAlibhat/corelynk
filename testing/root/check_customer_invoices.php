<?php
// Quick script to check customer_invoices
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// Check table structure
$result = $mysqli->query("DESCRIBE customer_invoices");
echo "customer_invoices table columns:\n";
$columns = [];
while ($row = $result->fetch_assoc()) {
    $columns[] = $row['Field'];
    if (strpos($row['Field'], 'balance') !== false || strpos($row['Field'], 'amount') !== false || strpos($row['Field'], 'currency') !== false) {
        echo "  " . $row['Field'] . " - " . $row['Type'] . "\n";
    }
}

// Check if balance column exists
$hasBalance = in_array('balance', $columns);
echo "\nHas 'balance' column: " . ($hasBalance ? 'YES' : 'NO') . "\n";

// Check data
$result = $mysqli->query("SELECT COUNT(*) as count FROM customer_invoices");
if ($result) {
    $row = $result->fetch_assoc();
    echo "\nTotal customer invoices: " . $row['count'] . "\n\n";
} else {
    echo "\nError querying customer_invoices: " . $mysqli->error . "\n";
    exit;
}

if ($row['count'] > 0) {
    // Check by status
    $result = $mysqli->query("SELECT status, COUNT(*) as count FROM customer_invoices GROUP BY status");
    echo "\nBy status:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  " . $row['status'] . ": " . $row['count'] . "\n";
    }
    
    // Check unpaid invoices
    $unpaidStatuses = ['issued', 'partially_paid', 'overdue'];
    $statusList = "'" . implode("','", $unpaidStatuses) . "'";
    
    if ($hasBalance) {
        $result = $mysqli->query("SELECT currency_code, SUM(balance) as total FROM customer_invoices WHERE status IN ($statusList) AND balance > 0 GROUP BY currency_code");
    } else {
        $result = $mysqli->query("SELECT currency_code, SUM(total_amount) as total FROM customer_invoices WHERE status IN ($statusList) GROUP BY currency_code");
    }
    
    if ($result && $result->num_rows > 0) {
        echo "\nReceivables by currency:\n";
        while ($row = $result->fetch_assoc()) {
            echo "  " . $row['currency_code'] . ": " . number_format($row['total'], 2) . "\n";
        }
    } else {
        echo "\nNo unpaid invoices found\n";
    }
}

$mysqli->close();
