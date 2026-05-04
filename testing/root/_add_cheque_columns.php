<?php
$db = new mysqli('localhost','root','','corelynk_db');

// Add cheque-specific columns to vendor_payments if missing
$cols = ['cheque_payee_name'=>'VARCHAR(255) DEFAULT NULL','cheque_notes'=>'VARCHAR(500) DEFAULT NULL','cheque_number'=>'VARCHAR(50) DEFAULT NULL','cheque_delivery_type'=>'VARCHAR(30) DEFAULT NULL'];
foreach ($cols as $col => $def) {
    $check = $db->query("SHOW COLUMNS FROM vendor_payments LIKE '$col'");
    if ($check->num_rows === 0) {
        $db->query("ALTER TABLE vendor_payments ADD COLUMN $col $def AFTER notes");
        echo "Added $col\n";
    } else {
        echo "Already exists: $col\n";
    }
}

// Also add advance_amount column to vendor_payment_allocations if missing
$check = $db->query("SHOW COLUMNS FROM vendor_payment_allocations LIKE 'advance_amount'");
if ($check->num_rows === 0) {
    $db->query("ALTER TABLE vendor_payment_allocations ADD COLUMN advance_amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER amount_allocated");
    echo "Added advance_amount to allocations\n";
} else {
    echo "Already exists: advance_amount in allocations\n";
}

echo "Done!\n";
