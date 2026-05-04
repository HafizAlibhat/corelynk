<?php
// Quick debug script to check product processes data using raw mysqli
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'production_management_system';

$mysqli = new mysqli($host, $username, $password, $database);

if ($mysqli->connect_error) {
    die('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

echo "<h3>Debug: Product Processes Data</h3>";

// Check if we have processes
echo "<h4>Available Processes:</h4>";
$result = $mysqli->query("SELECT id, name FROM processes ORDER BY name");
$processes = [];
while ($row = $result->fetch_assoc()) {
    $processes[] = $row;
    echo "ID: {$row['id']} - Name: {$row['name']}<br>";
}

echo "<h4>Available Products:</h4>";
$result = $mysqli->query("SELECT id, name, code FROM products ORDER BY name");
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
    echo "ID: {$row['id']} - Name: {$row['name']} - Code: {$row['code']}<br>";
}

echo "<h4>Product-Process Mappings:</h4>";
$result = $mysqli->query("
    SELECT pp.*, p.name as product_name, pr.name as process_name 
    FROM product_processes pp 
    LEFT JOIN products p ON p.id = pp.product_id 
    LEFT JOIN processes pr ON pr.id = pp.process_id 
    ORDER BY pp.product_id
");

$mappings = [];
while ($row = $result->fetch_assoc()) {
    $mappings[] = $row;
}

if (empty($mappings)) {
    echo "<strong>NO PRODUCT-PROCESS MAPPINGS FOUND!</strong><br>";
    echo "This is why the dropdown shows 'No processes assigned'.<br><br>";
    
    // Let's create some basic mappings
    echo "<h4>Creating basic product-process mappings...</h4>";
    
    if (!empty($products) && !empty($processes)) {
        // Assign first process to first product as example
        $firstProduct = $products[0];
        $firstProcess = $processes[0];
        
        $stmt = $mysqli->prepare("INSERT IGNORE INTO product_processes (product_id, process_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $firstProduct['id'], $firstProcess['id']);
        $stmt->execute();
        
        echo "✅ Assigned process '{$firstProcess['name']}' to product '{$firstProduct['name']}'<br>";
        
        // If there are more processes, assign them too
        if (count($processes) > 1) {
            $secondProcess = $processes[1];
            $stmt = $mysqli->prepare("INSERT IGNORE INTO product_processes (product_id, process_id) VALUES (?, ?)");
            $stmt->bind_param("ii", $firstProduct['id'], $secondProcess['id']);
            $stmt->execute();
            echo "✅ Assigned process '{$secondProcess['name']}' to product '{$firstProduct['name']}'<br>";
        }
        
        echo "<br><strong>Now refresh the Create Batch modal and try again!</strong>";
    }
} else {
    foreach ($mappings as $map) {
        echo "Product: {$map['product_name']} -> Process: {$map['process_name']}<br>";
    }
}

echo "<h4>Work Order Items (for debugging):</h4>";
$result = $mysqli->query("
    SELECT woi.id, woi.product_id, p.name as product_name, wo.wo_number 
    FROM work_order_items woi 
    LEFT JOIN products p ON p.id = woi.product_id 
    LEFT JOIN work_orders wo ON wo.id = woi.work_order_id 
    ORDER BY woi.id
");

while ($row = $result->fetch_assoc()) {
    echo "Item ID: {$row['id']} - Product: {$row['product_name']} - WO: {$row['wo_number']}<br>";
}

$mysqli->close();
?>