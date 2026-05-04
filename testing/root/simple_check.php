<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "Current database: " . $mysqli->select_db('corelynk_db') . "\n\n";

// Raw query for allocations
$result = $mysqli->query("SELECT * FROM vendor_payment_allocations");
if (!$result) {
    echo "Error querying allocations: " . $mysqli->error . "\n";
} else {
    echo "Allocation count: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

echo "\n";

// Raw query for payments
$result = $mysqli->query("SELECT * FROM vendor_payments");
if (!$result) {
    echo "Error querying payments: " . $mysqli->error . "\n";
} else {
    echo "Payments count: " . $result->num_rows . "\n";
    while ($row = $result->fetch_assoc()) {
        echo json_encode($row) . "\n";
    }
}

// Check current database
$result = $mysqli->query("SELECT DATABASE()");
$row = $result->fetch_row();
echo "\nLogged in to database: " . $row[0] . "\n";

// Check if table exists and has columns
$result = $mysqli->query("DESCRIBE vendor_payment_allocations");
if ($result) {
    echo "\nvender_payment_allocations columns exist\n";
}

$mysqli->close();
?>
