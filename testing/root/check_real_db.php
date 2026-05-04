<?php
$db = new mysqli('localhost', 'root', '', 'production_management_system');
if ($db->connect_error) die('Connection failed: ' . $db->connect_error);

echo "Connected to: production_management_system\n\n";

echo "=== Tables ===\n";
$result = $db->query('SHOW TABLES');
while ($row = $result->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

echo "\n=== Work Orders ===\n";
$result = $db->query("SELECT id, wo_number, status FROM work_orders LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['wo_number']} (Status: {$row['status']})\n";
    }
} else {
    echo "No work orders found\n";
}

echo "\n=== Products ===\n";
$result = $db->query("SELECT id, name, code FROM products LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['name']} ({$row['code']})\n";
    }
} else {
    echo "No products found\n";
}

echo "\n=== Process Batches ===\n";
$result = $db->query("SELECT id, batch_number, status, quantity, quantity_completed FROM process_batches LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['batch_number']}: {$row['quantity_completed']}/{$row['quantity']} (Status: {$row['status']})\n";
    }
} else {
    echo "No batches found\n";
}
