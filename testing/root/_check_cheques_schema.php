<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($db->connect_error) { die('Connect error: '.$db->connect_error); }
$r = $db->query('DESCRIBE cheques');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        echo implode(' | ', array_values($row)) . "\n";
    }
} else {
    echo "Error: " . $db->error . "\n";
}

echo "\n--- vendor_payments ---\n";
$r2 = $db->query('DESCRIBE vendor_payments');
if ($r2) {
    while ($row = $r2->fetch_assoc()) {
        echo implode(' | ', array_values($row)) . "\n";
    }
}

echo "\n--- cheque_lines table exists? ---\n";
$r3 = $db->query("SHOW TABLES LIKE 'cheque_lines'");
echo $r3->num_rows > 0 ? "YES" : "NO";
echo "\n";

echo "\n--- cheque_sequences ---\n";
$r4 = $db->query('DESCRIBE cheque_sequences');
if ($r4) {
    while ($row = $r4->fetch_assoc()) {
        echo implode(' | ', array_values($row)) . "\n";
    }
} else {
    echo "Table not found: " . $db->error . "\n";
}
$db->close();
