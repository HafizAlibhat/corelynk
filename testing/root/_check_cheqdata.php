<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
// Check cheque_lines.amount
$r = $db->query("SHOW COLUMNS FROM cheque_lines LIKE 'amount'");
echo "cheque_lines.amount: " . ($r->num_rows > 0 ? "EXISTS" : "MISSING") . "\n";

// Check existing cheques data
$r2 = $db->query("SELECT COUNT(*) as cnt FROM cheques");
$row = $r2->fetch_assoc();
echo "Cheques count: " . $row['cnt'] . "\n";

// Check vendor_payments.cheque_id
$r3 = $db->query("SHOW COLUMNS FROM vendor_payments LIKE 'cheque_id'");
echo "vendor_payments.cheque_id: " . ($r3->num_rows > 0 ? "EXISTS" : "MISSING") . "\n";

// Existing bank accounts
$r4 = $db->query("SELECT id, name FROM accounts WHERE is_bank = 1 ORDER BY name");
echo "\n--- Bank Accounts ---\n";
while ($row = $r4->fetch_assoc()) { echo $row['id'] . " | " . $row['name'] . "\n"; }

$db->close();
