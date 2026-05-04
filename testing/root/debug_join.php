<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Quick debug ===\n\n";

// Test raw allocations
echo "1. Allocations in database:\n";
$result = $mysqli->query("SELECT id, payment_id, vendor_bill_id FROM vendor_payment_allocations");
while ($row = $result->fetch_assoc()) {
    echo "   Alloc {$row['id']}: payment_id={$row['payment_id']}, bill_id={$row['vendor_bill_id']}\n";
}

// Test raw payments
echo "\n2. Payments in database:\n";
$result = $mysqli->query("SELECT id, vendor_id, status, amount FROM vendor_payments ORDER BY id");
while ($row = $result->fetch_assoc()) {
    echo "   Payment {$row['id']}: vendor_id={$row['vendor_id']}, status={$row['status']}, amount={$row['amount']}\n";
}

// Test raw bills
echo "\n3. Bills in database:\n";
$result = $mysqli->query("SELECT id, bill_number FROM vendor_bills WHERE id IN (5, 6)");
while ($row = $result->fetch_assoc()) {
    echo "   Bill {$row['id']}: {$row['bill_number']}\n";
}

// Test simple join of allocations and payments
echo "\n4. Test JOIN: allocations with payments\n";
$result = $mysqli->query("
    SELECT vpa.id, vpa.payment_id, vp.id as vp_id, vp.vendor_id
    FROM vendor_payment_allocations vpa
    LEFT JOIN vendor_payments vp ON vp.id = vpa.payment_id
");
while ($row = $result->fetch_assoc()) {
    echo "   Alloc {$row['id']}: payment_id={$row['payment_id']} -> vp.id={$row['vp_id']} (vendor_id={$row['vendor_id']})\n";
}

// Test full query step by step
echo "\n5. Full query (should return 0 if vendor_id filter excludes):\n";
$result = $mysqli->query("
    SELECT vpa.id, vp.vendor_id, vp.status
    FROM vendor_payment_allocations vpa
    JOIN vendor_payments vp ON vp.id = vpa.payment_id
    WHERE vp.vendor_id = 0 AND vp.status = 'posted'
");

echo "   Results: " . $result->num_rows . "\n";
while ($row = $result->fetch_assoc()) {
    echo "   -> ". json_encode($row) . "\n";
}

$mysqli->close();
?>
