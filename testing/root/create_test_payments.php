<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Creating test vendor payment records ===\n\n";

// Get existing allocations to create matching payments
$allocations = $mysqli->query("SELECT * FROM vendor_payment_allocations ORDER BY id")->fetch_all(MYSQLI_ASSOC);

echo "Found " . count($allocations) . " allocations\n\n";

// Create corresponding payments based on allocations
$now = date('Y-m-d H:i:s');

foreach ($allocations as $alloc) {
    echo "Processing allocation ID {$alloc['id']} (payment_id={$alloc['payment_id']}, amount={$alloc['amount']})\n";
    
    // Check if payment already exists
    $existing = $mysqli->query("SELECT id FROM vendor_payments WHERE id = {$alloc['payment_id']}")->fetch_assoc();
    
    if ($existing) {
        echo "  → Payment ID {$alloc['payment_id']} already exists\n";
        continue;
    }
    
    // Determine payment type and advance amount based on allocation amount
    if ($alloc['amount'] <= 50) {
        // These are likely advance payments
        $paymentType = 'settlement';
        $advanceAmount = $alloc['amount'];
    } else {
        // Larger amounts are likely settlements with cash
        $paymentType = 'settlement';
        $advanceAmount = 0;
    }
    
    // Insert payment record
    $sql = "INSERT INTO vendor_payments 
            (id, vendor_id, payment_date, payment_method, payment_type, currency_code, amount, advance_amount, source_account_id, status, created_by, created_at) 
            VALUES 
            ({$alloc['payment_id']}, 0, '" . date('Y-m-d') . "', 'bank', '$paymentType', 'PKR', {$alloc['amount']}, $advanceAmount, 1, 'posted', 1, '$now')";
    
    if ($mysqli->query($sql)) {
        echo "  → Created payment ID {$alloc['payment_id']}\n";
    } else {
        echo "  → ERROR: " . $mysqli->error . "\n";
    }
}

echo "\n=== Verifying payments were created ===\n\n";

$result = $mysqli->query("SELECT id, vendor_id, amount, advance_amount, status FROM vendor_payments ORDER BY id");
$count = 0;
while ($row = $result->fetch_assoc()) {
    echo "Payment {$row['id']}: vendor_id={$row['vendor_id']}, amount={$row['amount']}, advance_amount={$row['advance_amount']}, status={$row['status']}\n";
    $count++;
}
echo "\nTotal payments created: $count\n";

$mysqli->close();
?>
