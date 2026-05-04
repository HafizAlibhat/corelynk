<?php
// Simple debug script for batch data
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'production_management_system';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "<h3>Debug: Batch Data Issue</h3>";

// Check process_batches table
echo "<h4>Process Batches:</h4>";
$result = $mysqli->query("SELECT * FROM process_batches LIMIT 3");
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "Batch ID: {$row['id']}, Code: {$row['batch_code']}, Work Order Item: {$row['work_order_item_id']}, Process: {$row['process_id']}<br>";
    }
} else {
    echo "No batches found<br>";
}

// Test the join query
echo "<h4>Testing Join Query:</h4>";
$query = "SELECT pb.id, pb.batch_code, pb.work_order_item_id, pb.process_id,
          woi.product_id, wo.wo_number, wo.customer_name,
          p.name as product_name, p.code as product_code,
          pr.name as process_name
          FROM process_batches pb
          LEFT JOIN work_order_items woi ON woi.id = pb.work_order_item_id
          LEFT JOIN work_orders wo ON wo.id = woi.work_order_id
          LEFT JOIN products p ON p.id = woi.product_id
          LEFT JOIN processes pr ON pr.id = pb.process_id
          LIMIT 3";

$result = $mysqli->query($query);
if ($result && $result->num_rows > 0) {
    echo "Join query successful:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "Batch: {$row['batch_code']}, WO: " . ($row['wo_number'] ?: 'NULL') . ", Product: " . ($row['product_name'] ?: 'NULL') . ", Process: " . ($row['process_name'] ?: 'NULL') . "<br>";
    }
} else {
    echo "Join query failed or no results<br>";
    if ($mysqli->error) {
        echo "SQL Error: " . $mysqli->error . "<br>";
    }
}

// Check what tables exist
echo "<h4>Table Record Counts:</h4>";
$tables = ['process_batches', 'work_order_items', 'work_orders', 'products', 'processes'];
foreach ($tables as $table) {
    $result = $mysqli->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $count = $result->fetch_assoc()['count'];
        echo "$table: $count records<br>";
    } else {
        echo "$table: Error - " . $mysqli->error . "<br>";
    }
}

$mysqli->close();
?>