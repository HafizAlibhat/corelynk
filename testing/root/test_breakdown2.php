<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Checking vendor_payments table ===\n\n";

// Check all payments
$result = $mysqli->query("
    SELECT id, vendor_id, payment_date, amount, advance_amount, status, payment_type 
    FROM vendor_payments 
    ORDER BY id DESC 
    LIMIT 20
");

echo "All payments (last 20):\n";
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n=== Checking payments for vendor_id=0 ===\n\n";

$result = $mysqli->query("
    SELECT id, vendor_id, payment_date, amount, advance_amount, status, payment_type 
    FROM vendor_payments 
    WHERE vendor_id = 0
    ORDER BY id DESC
");

$count = 0;
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
    $count++;
}
echo "\nTotal payments for vendor 0: " . $count;

echo "\n\n=== Checking which payment_ids are in allocations ===\n\n";

$result = $mysqli->query("
    SELECT DISTINCT payment_id 
    FROM vendor_payment_allocations
    ORDER BY payment_id
");

echo "Payment IDs referenced in allocations:\n";
while ($row = $result->fetch_assoc()) {
    echo "  Payment ID: " . $row['payment_id'] . "\n";
}

echo "\n=== Testing if those payment IDs exist in vendor_payments ===\n\n";

$result = $mysqli->query("
    SELECT vp.id, vp.vendor_id, vp.status, vpa.payment_id
    FROM vendor_payment_allocations vpa
    LEFT JOIN vendor_payments vp ON vp.id = vpa.payment_id
    LIMIT 10
");

while ($row = $result->fetch_assoc()) {
    echo "Allocation payment_id: " . $row['payment_id'] . 
         " => vendor_payments record ID: " . ($row['id'] ?? 'NOT FOUND') . 
         " (vendor_id=" . ($row['vendor_id'] ?? 'NULL') . 
         ", status=" . ($row['status'] ?? 'NULL') . ")\n";
}

$mysqli->close();
?>
