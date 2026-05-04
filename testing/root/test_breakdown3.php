<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Checking all tables in corelynk_db ===\n\n";
$result = $mysqli->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

echo "Tables found: " . implode(", ", $tables) . "\n\n";

// Check for any tables with 'payment' in the name
echo "=== Tables containing 'payment' ===\n";
foreach ($tables as $table) {
    if (stripos($table, 'payment') !== false) {
        echo "  - $table\n";
        // Get row count
        $countResult = $mysqli->query("SELECT COUNT(*) as cnt FROM $table");
        $countRow = $countResult->fetch_assoc();
        echo "    Row count: " . $countRow['cnt'] . "\n";
    }
}

echo "\n=== Checking vendor_payment_allocations more carefully ===\n\n";
$result = $mysqli->query("
    SELECT vpa.*, vp.id as vp_id, vp.vendor_id, vp.status
    FROM vendor_payment_allocations vpa
    LEFT JOIN vendor_payments vp ON vp.id = vpa.payment_id
");

$count = 0;
while ($row = $result->fetch_assoc()) {
    $count++;
    echo "Allocation ID {$row['id']}: payment_id={$row['payment_id']}, bill_id={$row['vendor_bill_id']}, vp.id={$row['vp_id']}\n";
}
echo "\nTotal allocations: $count\n";

echo "\n=== Checking if maybe payments are in a different table ===\n";
foreach ($tables as $table) {
    if (stripos($table, 'vendor') !== false && stripos($table, 'pay') !== false) {
        echo "\nFound related table: $table\n";
        $result = $mysqli->query("SELECT COUNT(*) as cnt FROM $table");
        $countRow = $result->fetch_assoc();
        echo "  Row count: " . $countRow['cnt'] . "\n";
        
        // Show first row structure
        $result = $mysqli->query("SELECT * FROM $table LIMIT 1");
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            echo "  Columns: " . implode(", ", array_keys($row)) . "\n";
        }
    }
}

$mysqli->close();
?>
