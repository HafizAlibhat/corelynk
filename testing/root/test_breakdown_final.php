<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Testing paymentBreakdown query ===\n\n";

$vendorId = 0;

$result = $mysqli->query("
    SELECT 
        vp.id as payment_id,
        vp.payment_date,
        vp.amount,
        vp.advance_amount,
        vb.bill_number,
        vpa.vendor_bill_id as bill_id
    FROM vendor_payment_allocations vpa
    JOIN vendor_payments vp ON vp.id = vpa.payment_id
    JOIN vendor_bills vb ON vb.id = vpa.vendor_bill_id
    WHERE vp.vendor_id = $vendorId AND vp.status = 'posted'
    ORDER BY vp.payment_date DESC, vp.id DESC
");

if (!$result) {
    echo "Error: " . $mysqli->error . "\n";
} else {
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
        $count++;
    }
    echo "\nTotal results: $count\n";
}

echo "\n=== Testing with LEFT JOINs to debug ===\n\n";

$query2 = "
    SELECT 
        vpa.id as alloc_id,
        vpa.payment_id,
        vpa.vendor_bill_id,
        vp.id as vp_id,
        vp.vendor_id,
        vp.status,
        vb.id as vb_id,
        vb.bill_number
    FROM vendor_payment_allocations vpa
    LEFT JOIN vendor_payments vp ON vp.id = vpa.payment_id
    LEFT JOIN vendor_bills vb ON vb.id = vpa.vendor_bill_id
    ORDER BY vpa.id
";

$result = $mysqli->query($query2);

while ($row = $result->fetch_assoc()) {
    echo "Alloc {$row['alloc_id']}: payment_id={$row['payment_id']} → vp.id={$row['vp_id']} (vendor_id={$row['vendor_id']}, status={$row['status']}), bill={$row['vb_id']} ({$row['bill_number']})\n";
}

$mysqli->close();
?>
