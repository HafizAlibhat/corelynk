<?php
// Test join query directly
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h3>Testing Join Query Step by Step</h3>";

// First, check the base table
echo "<h4>1. Base process_batches data:</h4>";
$result = $mysqli->query("SELECT * FROM process_batches");
while ($row = $result->fetch_assoc()) {
    echo "Batch ID: {$row['id']}, Work Order Item ID: {$row['work_order_item_id']}, Process ID: {$row['process_id']}<br>";
}

// Check work_order_items table
echo "<h4>2. Work Order Items:</h4>";
$result = $mysqli->query("SELECT * FROM work_order_items");
while ($row = $result->fetch_assoc()) {
    echo "Item ID: {$row['id']}, Work Order ID: {$row['work_order_id']}, Product ID: {$row['product_id']}<br>";
}

// Check if work_order_item_id in process_batches matches id in work_order_items
echo "<h4>3. Testing basic join:</h4>";
$query = "SELECT pb.id, pb.batch_code, pb.work_order_item_id, woi.id as woi_id, woi.work_order_id, woi.product_id
          FROM process_batches pb
          LEFT JOIN work_order_items woi ON woi.id = pb.work_order_item_id";
          
$result = $mysqli->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Match: Batch {$row['batch_code']} -> WOI ID {$row['woi_id']}, WO ID {$row['work_order_id']}<br>";
    }
} else {
    echo "No matches found<br>";
}

// Test full join
echo "<h4>4. Full join test:</h4>";
$query = "SELECT pb.id, pb.batch_code, 
          woi.id as woi_id, wo.wo_number, wo.customer_name,
          p.name as product_name, pr.name as process_name
          FROM process_batches pb
          LEFT JOIN work_order_items woi ON woi.id = pb.work_order_item_id
          LEFT JOIN work_orders wo ON wo.id = woi.work_order_id
          LEFT JOIN products p ON p.id = woi.product_id
          LEFT JOIN processes pr ON pr.id = pb.process_id";
          
$result = $mysqli->query($query);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Full: {$row['batch_code']} -> WO: " . ($row['wo_number'] ?: 'NULL') . 
             ", Product: " . ($row['product_name'] ?: 'NULL') . 
             ", Process: " . ($row['process_name'] ?: 'NULL') . "<br>";
    }
} else {
    echo "Full join failed: " . $mysqli->error . "<br>";
}

$mysqli->close();
?>