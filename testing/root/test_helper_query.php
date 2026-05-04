<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== TESTING FIXED HELPER QUERY ===\n\n";

$customerId = 939;

$query = "SELECT ci.id,
       ci.invoice_number,
       ci.status,
       ci.total_amount,
       (SELECT COALESCE(SUM(cpa.allocated_amount),0)
        FROM customer_payment_allocations cpa
        INNER JOIN customer_payments cp ON cp.id = cpa.payment_id
        WHERE cpa.invoice_id = ci.id AND (cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)) AS paid_amount
FROM customer_invoices ci
WHERE ci.customer_id = $customerId
  AND ci.deleted_at IS NULL
  AND LOWER(COALESCE(ci.status, '')) NOT IN ('cancelled','void')
ORDER BY ci.id DESC";

echo "Results:\n";
$result = $mysqli->query($query);
if (!$result) {
    echo "ERROR: " . $mysqli->error . "\n";
} else if ($result->num_rows == 0) {
    echo "  NO ROWS RETURNED\n";
} else {
    while ($row = $result->fetch_assoc()) {
        $outstanding = $row['total_amount'] - $row['paid_amount'];
        echo "✓ Invoice {$row['invoice_number']}: Total={$row['total_amount']}, Paid={$row['paid_amount']}, Outstanding=$outstanding\n";
    }
}

$mysqli->close();
?>
