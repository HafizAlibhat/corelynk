<?php
require_once 'vendor/autoload.php';

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

$db = \Config\Database::connect();

echo "=== WORK ORDERS ===\n";
$workOrders = $db->query('SELECT * FROM work_orders LIMIT 5')->getResultArray();
foreach($workOrders as $wo) {
    echo "ID: {$wo['id']} - {$wo['wo_number']} - Status: {$wo['status']}\n";
}

echo "\n=== WORK ORDER ITEMS ===\n";
$items = $db->query('SELECT woi.*, wo.wo_number FROM work_order_items woi JOIN work_orders wo ON wo.id = woi.work_order_id LIMIT 5')->getResultArray();
foreach($items as $item) {
    echo "WO: {$item['wo_number']} - Product ID: {$item['product_id']} - Qty: {$item['quantity_ordered']}\n";
}

echo "\n=== PROCESS BATCHES ===\n";
$batches = $db->query('SELECT pb.*, wo.wo_number FROM process_batches pb JOIN work_order_items woi ON woi.id = pb.work_order_item_id JOIN work_orders wo ON wo.id = woi.work_order_id LIMIT 5')->getResultArray();
foreach($batches as $batch) {
    echo "WO: {$batch['wo_number']} - Batch: {$batch['batch_code']} - Status: {$batch['status']}\n";
}

echo "\n=== CHECKING WO-2025-001 SPECIFICALLY ===\n";
$wo2025001 = $db->query("SELECT * FROM work_orders WHERE wo_number = 'WO-2025-001'")->getResultArray();
if ($wo2025001) {
    $woId = $wo2025001[0]['id'];
    echo "Work Order ID: $woId\n";
    
    $items = $db->query("SELECT * FROM work_order_items WHERE work_order_id = $woId")->getResultArray();
    echo "Work Order Items: " . count($items) . "\n";
    
    foreach($items as $item) {
        echo "  Item ID: {$item['id']}, Product ID: {$item['product_id']}, Qty: {$item['quantity_ordered']}\n";
        
        $batches = $db->query("SELECT * FROM process_batches WHERE work_order_item_id = {$item['id']}")->getResultArray();
        echo "    Batches: " . count($batches) . "\n";
        foreach($batches as $batch) {
            echo "      Batch: {$batch['batch_code']}, Process ID: {$batch['process_id']}, Status: {$batch['status']}\n";
        }
    }
} else {
    echo "WO-2025-001 not found\n";
}
?>