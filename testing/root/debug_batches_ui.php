<?php
// Simple debug script to test batch data loading
require_once 'app/Config/Database.php';

try {
    // Use CodeIgniter database connection
    $db = \Config\Database::connect();
    
    // Test basic batch query
    $query = $db->query('SELECT * FROM process_batches LIMIT 5');
    $batches = $query->getResult('array');
    
    echo "<h2>Debug: Process Batches Data</h2>";
    echo "<p>Found " . count($batches) . " batches</p>";
    
    if (!empty($batches)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Batch Number</th><th>Status</th><th>Quantity</th><th>Completed</th></tr>";
        foreach ($batches as $batch) {
            echo "<tr>";
            echo "<td>" . ($batch['id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['batch_number'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['status'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['quantity'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['quantity_completed'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No batches found in database!</p>";
    }
    
    // Test the complex query from controller
    echo "<h3>Testing Complex Query (like in controller):</h3>";
    
    $builder = $db->table('process_batches')
        ->select('
            process_batches.*,
            work_orders.wo_number as work_order_number,
            products.name as product_name,
            products.code as product_code,
            processes.name as process_name,
            COUNT(process_batch_logs.id) as log_count
        ')
        ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id', 'left')
        ->join('work_orders', 'work_orders.id = work_order_items.work_order_id', 'left')
        ->join('products', 'products.id = work_order_items.product_id', 'left')
        ->join('processes', 'processes.id = process_batches.process_id', 'left')
        ->join('process_batch_logs', 'process_batch_logs.batch_id = process_batches.id', 'left')
        ->groupBy('process_batches.id')
        ->orderBy('process_batches.created_at', 'DESC');
    
    $complexBatches = $builder->get()->getResult('array');
    
    echo "<p>Complex query found " . count($complexBatches) . " batches</p>";
    
    if (!empty($complexBatches)) {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Batch Number</th><th>Product</th><th>Process</th><th>WO Number</th></tr>";
        foreach ($complexBatches as $batch) {
            echo "<tr>";
            echo "<td>" . ($batch['id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['batch_number'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['product_name'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['process_name'] ?? 'NULL') . "</td>";
            echo "<td>" . ($batch['work_order_number'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Complex query returned no results!</p>";
    }
    
} catch (Exception $e) {
    echo "<h2>Error: " . $e->getMessage() . "</h2>";
}
?>
