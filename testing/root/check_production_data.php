<?php
// Check production data in database
$db = new mysqli('localhost', 'root', '', 'pro_sys');

if ($db->connect_error) {
    die('Connection failed: ' . $db->connect_error);
}

echo "=== WORK ORDERS ===\n";
$result = $db->query("SELECT id, wo_number, customer_name, status FROM work_orders LIMIT 5");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['wo_number']} ({$row['customer_name']}) - Status: {$row['status']}\n";
    }
} else {
    echo "No work orders found\n";
}

echo "\n=== WORK ORDER ITEMS (Products) ===\n";
$result = $db->query("
    SELECT woi.id, woi.work_order_id, wo.wo_number, p.name as product_name, p.code as product_code, woi.quantity_ordered
    FROM work_order_items woi
    JOIN work_orders wo ON wo.id = woi.work_order_id
    JOIN products p ON p.id = woi.product_id
    LIMIT 5
");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['wo_number']}: {$row['product_name']} ({$row['product_code']}) x{$row['quantity_ordered']}\n";
    }
} else {
    echo "No work order items found\n";
}

echo "\n=== PROCESS BATCHES ===\n";
$result = $db->query("
    SELECT pb.id, pb.batch_code, pb.planned_qty, pb.completed_qty, pb.status,
           wo.wo_number, p.name as product_name, pr.name as process_name
    FROM process_batches pb
    JOIN work_order_items woi ON woi.id = pb.work_order_item_id
    JOIN work_orders wo ON wo.id = woi.work_order_id
    JOIN products p ON p.id = woi.product_id
    JOIN processes pr ON pr.id = pb.process_id
    LIMIT 5
");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "- {$row['batch_code']}: {$row['wo_number']} > {$row['product_name']} > {$row['process_name']} ({$row['completed_qty']}/{$row['planned_qty']}) - {$row['status']}\n";
    }
} else {
    echo "No batches found\n";
}

$db->close();
