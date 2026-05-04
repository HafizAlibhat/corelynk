<?php
// Simple mysqli connection to check relationships
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== WORK ORDERS ===\n";
$result = $mysqli->query('SELECT * FROM work_orders');
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== WORK ORDER ITEMS ===\n";
$result = $mysqli->query('SELECT * FROM work_order_items');
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== PROCESS BATCHES ===\n";
$result = $mysqli->query('SELECT * FROM process_batches');
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== PROCESSES ===\n";
$result = $mysqli->query('SELECT * FROM processes');
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

echo "\n=== RELATIONSHIPS TEST ===\n";
echo "Testing relationship: work_order_item_id = 1, product_id = 10\n";

$relationshipQuery = "
    SELECT 
        pb.*,
        p.name as process_name,
        p.id as process_id
    FROM process_batches pb
    LEFT JOIN processes p ON pb.process_id = p.id 
    WHERE pb.work_order_item_id = 1
";

echo "Query: " . $relationshipQuery . "\n";
$result = $mysqli->query($relationshipQuery);
if ($result) {
    echo "Results:\n";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Query error: " . $mysqli->error . "\n";
}

$mysqli->close();
