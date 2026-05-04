<?php
// Debug database structure and data
try {
    $db = new mysqli('localhost', 'root', '', 'pro_sys');
    if ($db->connect_error) {
        die('Connection failed: ' . $db->connect_error);
    }
    
    echo "<h3>Database Tables:</h3>";
    $result = $db->query('SHOW TABLES');
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "<br>";
    }
    
    echo "<h3>Work Orders Data:</h3>";
    $result = $db->query('SELECT * FROM work_orders LIMIT 5');
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "WO ID: " . $row['id'] . " - " . $row['wo_number'] . " - " . $row['customer_name'] . "<br>";
        }
    } else {
        echo "No work orders found<br>";
    }
    
    echo "<h3>Work Order Items Data:</h3>";
    $result = $db->query('SELECT woi.*, p.name as product_name FROM work_order_items woi LEFT JOIN products p ON p.id = woi.product_id LIMIT 5');
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "WOI ID: " . $row['id'] . " - WO: " . $row['work_order_id'] . " - Product: " . ($row['product_name'] ?? 'NULL') . "<br>";
        }
    } else {
        echo "No work order items found<br>";
    }
    
    echo "<h3>Process Batches Data:</h3>";
    $result = $db->query('SELECT * FROM process_batches LIMIT 5');
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo "Batch ID: " . $row['id'] . " - Code: " . ($row['batch_code'] ?? 'NULL') . " - WOI: " . ($row['work_order_item_id'] ?? 'NULL') . "<br>";
        }
    } else {
        echo "No process batches found<br>";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>