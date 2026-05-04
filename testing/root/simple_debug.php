<?php
// Simple database connection check
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'production_management_system';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "=== WORK ORDERS ===\n";
    $stmt = $pdo->query('SELECT * FROM work_orders LIMIT 5');
    while ($wo = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "ID: {$wo['id']} - {$wo['wo_number']} - Status: {$wo['status']}\n";
    }
    
    echo "\n=== WORK ORDER ITEMS ===\n";
    $stmt = $pdo->query('SELECT woi.*, wo.wo_number FROM work_order_items woi JOIN work_orders wo ON wo.id = woi.work_order_id LIMIT 5');
    while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "WO: {$item['wo_number']} - Product ID: {$item['product_id']} - Qty: {$item['quantity_ordered']}\n";
    }
    
    echo "\n=== PROCESS BATCHES ===\n";
    $stmt = $pdo->query('SELECT pb.*, wo.wo_number FROM process_batches pb JOIN work_order_items woi ON woi.id = pb.work_order_item_id JOIN work_orders wo ON wo.id = woi.work_order_id LIMIT 5');
    while ($batch = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "WO: {$batch['wo_number']} - Batch: {$batch['batch_code']} - Status: {$batch['status']}\n";
    }
    
    echo "\n=== CHECKING WO-2025-001 SPECIFICALLY ===\n";
    $stmt = $pdo->query("SELECT * FROM work_orders WHERE wo_number = 'WO-2025-001'");
    $wo2025001 = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($wo2025001) {
        $woId = $wo2025001['id'];
        echo "Work Order ID: $woId\n";
        
        $stmt = $pdo->query("SELECT * FROM work_order_items WHERE work_order_id = $woId");
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Work Order Items: " . count($items) . "\n";
        
        foreach($items as $item) {
            echo "  Item ID: {$item['id']}, Product ID: {$item['product_id']}, Qty: {$item['quantity_ordered']}\n";
            
            $stmt2 = $pdo->query("SELECT * FROM process_batches WHERE work_order_item_id = {$item['id']}");
            $batches = $stmt2->fetchAll(PDO::FETCH_ASSOC);
            echo "    Batches: " . count($batches) . "\n";
            foreach($batches as $batch) {
                echo "      Batch: {$batch['batch_code']}, Process ID: {$batch['process_id']}, Status: {$batch['status']}\n";
            }
        }
    } else {
        echo "WO-2025-001 not found\n";
    }
    
} catch(PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>