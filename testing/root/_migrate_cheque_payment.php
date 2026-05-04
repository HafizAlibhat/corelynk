<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) { die('Connect error: '.$db->connect_error); }

// 1. Add payment_type to cheques table
$r = $db->query("SHOW COLUMNS FROM cheques LIKE 'payment_type'");
if ($r->num_rows === 0) {
    $db->query("ALTER TABLE cheques ADD COLUMN payment_type VARCHAR(20) NOT NULL DEFAULT 'settlement' AFTER notes");
    echo "Added payment_type to cheques\n";
} else {
    echo "cheques.payment_type already exists\n";
}

// 2. Ensure cheque_lines.amount exists
$r = $db->query("SHOW COLUMNS FROM cheque_lines LIKE 'amount'");
if ($r->num_rows === 0) {
    $db->query("ALTER TABLE cheque_lines ADD COLUMN amount DECIMAL(18,2) NOT NULL DEFAULT 0 AFTER description");
    echo "Added amount to cheque_lines\n";
} else {
    echo "cheque_lines.amount already exists\n";
}

// 3. Ensure vendor_payments.payment_method default allows cheque
$r = $db->query("SHOW COLUMNS FROM vendor_payments LIKE 'payment_method'");
$row = $r->fetch_assoc();
echo "vendor_payments.payment_method type: " . $row['Type'] . "\n";

echo "\nAll DB updates complete.\n";
$db->close();
