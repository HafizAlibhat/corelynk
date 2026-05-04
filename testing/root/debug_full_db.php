<?php
// Test database connection and data
$db = new mysqli('localhost', 'root', '', 'production_management_system');
if ($db->connect_error) {
    die('Connection failed: ' . $db->connect_error);
}

echo "=== DATABASE CONNECTION OK ===\n\n";

echo "=== WORK ORDERS ===\n";
$result = $db->query("SELECT * FROM work_orders LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "ID: {$row['id']}, WO: {$row['wo_number']}, Status: {$row['status']}\n";
    }
} else {
    echo "NO WORK ORDERS FOUND!\n";
    echo "Error: " . $db->error . "\n";
}

echo "\n=== WORK ORDER ITEMS ===\n";
$result = $db->query("SELECT woi.*, p.name as product_name FROM work_order_items woi LEFT JOIN products p ON p.id = woi.product_id LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "WOI ID: {$row['id']}, WO ID: {$row['work_order_id']}, Product: {$row['product_name']}, Qty: {$row['quantity_ordered']}\n";
    }
} else {
    echo "NO WORK ORDER ITEMS FOUND!\n";
}

echo "\n=== PROCESS BATCHES ===\n";
$result = $db->query("SELECT * FROM process_batches LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Batch ID: {$row['id']}, Code: {$row['batch_code']}, Status: {$row['status']}, Planned: {$row['planned_qty']}\n";
    }
} else {
    echo "NO PROCESS BATCHES FOUND!\n";
}