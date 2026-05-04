<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== TESTING FIXED PAYMENT ENDPOINT ===\n\n";

// Test the exact query the controller would build
$customerId = 939;

// Determine correct allocation amount column
$allocCols = [];
$result = $mysqli->query("DESCRIBE customer_payment_allocations");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $allocCols[] = $row['Field'];
    }
}

$allocExpr = 'cpa.allocated_amount';
if (!in_array('allocated_amount', $allocCols)) {
    if (in_array('amount_allocated', $allocCols)) {
        $allocExpr = 'cpa.amount_allocated';
    } elseif (in_array('amount', $allocCols)) {
        $allocExpr = 'cpa.amount';
    }
}

echo "Allocation column detected: $allocExpr\n";

// Check for status on customer_payments
$payCols = [];
$result = $mysqli->query("DESCRIBE customer_payments");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $payCols[] = $row['Field'];
    }
}

$statusCheck = '';
if (in_array('status', $payCols)) {
    $statusCheck = "LOWER(COALESCE(cp.status, '')) = 'posted'";
} elseif (in_array('posted_entry_id', $payCols)) {
    $statusCheck = '(cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)';
}

echo "Payment status check: $statusCheck\n\n";

// Build and execute the query
$paidQuery = "(SELECT COALESCE(SUM($allocExpr),0) 
            FROM customer_payment_allocations cpa 
            INNER JOIN customer_payments cp ON cp.id = cpa.payment_id 
            WHERE cpa.invoice_id = ci.id AND $statusCheck) AS paid_amount";

$query = "SELECT ci.id, 
       ci.invoice_number,
       ci.status,
       ci.total_amount,
       $paidQuery
FROM customer_invoices ci
WHERE ci.customer_id = $customerId
  AND ci.deleted_at IS NULL
  AND LOWER(COALESCE(ci.status, '')) NOT IN ('cancelled','void')
ORDER BY ci.id DESC";

echo "Executing query...\n";
$result = $mysqli->query($query);
if (!$result) {
    echo "ERROR: " . $mysqli->error . "\n";
} else {
    echo "Success! Found " . $result->num_rows . " invoice(s):\n\n";
    while ($row = $result->fetch_assoc()) {
        $outstanding = $row['total_amount'] - $row['paid_amount'];
        echo "Invoice {$row['invoice_number']}:\n";
        echo "  Total: {$row['total_amount']}\n";
        echo "  Paid: {$row['paid_amount']}\n";
        echo "  Outstanding: $outstanding\n";
        echo "  Status: {$row['status']}\n\n";
    }
}

$mysqli->close();
?>
