<?php
// Check payments for invoice 1 (customer 939)
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== CHECKING PAYMENTS FOR INVOICE 1 (Customer 939) ===\n\n";

// First, list the tables that might be related
echo "1. Available tables:\n";
$result = $mysqli->query("SHOW TABLES LIKE 'customer_payment%'");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $tableName = current($row);
        echo "  ✓ {$tableName}\n";
    }
} else {
    echo "  No customer_payment* tables found\n";
}

echo "\n2. Checking customer_payment_allocations for invoice 1:\n";
$result = $mysqli->query("SELECT * FROM customer_payment_allocations WHERE invoice_id = 1");
if (!$result) {
    echo "  Table might not exist or error: " . $mysqli->error . "\n";
} else if ($result->num_rows == 0) {
    echo "  No allocations found\n";
} else {
    echo "  Found " . $result->num_rows . " allocations:\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - Payment ID: {$row['payment_id']}, Amount: {$row['amount']}\n";
    }
}

echo "\n3. Checking customer_payments table:\n";
$result = $mysqli->query("SELECT * FROM customer_payments");
if (!$result) {
    echo "  Table doesn't exist or error: " . $mysqli->error . "\n";
} else if ($result->num_rows == 0) {
    echo "  No payments in table\n";
} else {
    echo "  Total payments: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo "  - ID: {$row['id']}, Customer: {$row['customer_id']}, Amount: {$row['amount']}, Status: " . ($row['status'] ?: 'N/A') . "\n";
    }
}

echo "\n4. Testing the exact query from the helper:\n";
$query = "SELECT COALESCE(SUM(COALESCE(cpa.amount,0)),0) as paid_amount
          FROM customer_payment_allocations cpa
          INNER JOIN customer_payments cp ON cp.id = cpa.payment_id
          WHERE cpa.invoice_id = 1 AND LOWER(COALESCE(cp.status, '')) = 'posted'";

$result = $mysqli->query($query);
if ($result) {
    $row = $result->fetch_assoc();
    echo "  Paid amount (status='posted'): " . ($row['paid_amount'] ?: 'N/A') . "\n";
} else {
    echo "  Error: " . $mysqli->error . "\n";
}

$mysqli->close();
echo "\n=== END CHECK ===\n";
?>
