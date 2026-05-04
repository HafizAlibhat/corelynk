<?php
// Simple diagnostic script for customer 939 invoices
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== CUSTOMER 939 INVOICE DIAGNOSTIC ===\n\n";

// Check if customer_invoices table exists
$result = $mysqli->query("SHOW TABLES LIKE 'customer_invoices'");
if ($result->num_rows == 0) {
    echo "ERROR: customer_invoices table does NOT exist\n";
    $mysqli->close();
    exit;
}

echo "✓ customer_invoices table EXISTS\n\n";

// Check all invoices for customer 939
$result = $mysqli->query("SELECT id, invoice_number, customer_id, status, deleted_at, total_amount FROM customer_invoices WHERE customer_id = 939");
if (!$result) {
    echo "SQL Error: " . $mysqli->error . "\n";
    $mysqli->close();
    exit;
}

echo "Invoices for customer 939:\n";
if ($result->num_rows == 0) {
    echo "  NONE FOUND IN DATABASE\n\n";
} else {
    while ($row = $result->fetch_assoc()) {
        echo "  ID: {$row['id']}\n";
        echo "    Number: {$row['invoice_number']}\n";
        echo "    Status: " . ($row['status'] ?: 'NULL') . "\n";
        echo "    Deleted: " . ($row['deleted_at'] ?: 'NULL') . "\n";
        echo "    Total: " . ($row['total_amount'] ?: 'N/A') . "\n";
        echo "\n";
    }
}

// Check all customer_invoices (to see what customers exist)
echo "\n--- ALL INVOICES IN DATABASE ---\n";
$result = $mysqli->query("SELECT COUNT(*) as count FROM customer_invoices");
if ($result) {
    $row = $result->fetch_assoc();
    echo "Total invoices in table: " . $row['count'] . "\n";
}

// List first 10 invoices
echo "\nFirst 10 invoices:\n";
$result = $mysqli->query("SELECT id, invoice_number, customer_id FROM customer_invoices LIMIT 10");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "  Invoice {$row['id']}: {$row['invoice_number']} (Customer: {$row['customer_id']})\n";
    }
} else {
    echo "  No invoices found\n";
}

$mysqli->close();
echo "\n=== END DIAGNOSTIC ===\n";
?>
