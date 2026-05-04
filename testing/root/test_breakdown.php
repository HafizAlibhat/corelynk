<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Testing Payment Breakdown Query ===\n\n";

$vendorId = 0;

// First check table structure
echo "1. vendor_payment_allocations columns:\n";
$result = $mysqli->query("DESCRIBE vendor_payment_allocations");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n2. vendor_payments columns:\n";
$result = $mysqli->query("DESCRIBE vendor_payments");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n3. vendor_bills columns:\n";
$result = $mysqli->query("DESCRIBE vendor_bills");
while ($row = $result->fetch_assoc()) {
    echo "  " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

echo "\n4. Sample data from vendor_payment_allocations:\n";
$result = $mysqli->query("SELECT * FROM vendor_payment_allocations LIMIT 3");
while ($row = $result->fetch_assoc()) {
    echo json_encode($row) . "\n";
}

echo "\n5. Testing the JOIN query:\n";
$result = $mysqli->query("
    SELECT 
        vp.id as payment_id,
        vp.vendor_id,
        vp.payment_date,
        vp.amount,
        vp.advance_amount,
        vp.status,
        vb.bill_number,
        vpa.vendor_bill_id as bill_id
    FROM vendor_payment_allocations vpa
    LEFT JOIN vendor_payments vp ON vp.id = vpa.payment_id
    LEFT JOIN vendor_bills vb ON vb.id = vpa.vendor_bill_id
    LIMIT 5
");

if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
} else {
    echo "Query error: " . $mysqli->error . "\n";
}

echo "\n6. Count of allocations for vendor 0:\n";
$result = $mysqli->query("
    SELECT COUNT(*) as cnt FROM vendor_payment_allocations vpa
    JOIN vendor_payments vp ON vp.id = vpa.payment_id
    WHERE vp.vendor_id = 0 AND vp.status = 'posted'
");
$row = $result->fetch_assoc();
echo "Count: " . $row['cnt'] . "\n";

$mysqli->close();
?>

