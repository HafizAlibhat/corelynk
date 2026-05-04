<?php
// Debug batch data loading
require_once 'vendor/autoload.php';

// Bootstrap CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

// Get database connection
$db = \Config\Database::connect();

echo "<h3>Debug: Batch Data Loading</h3>";

// Check if process_batches table exists and has data
echo "<h4>Process Batches Table:</h4>";
try {
    $result = $db->query("SELECT * FROM process_batches LIMIT 5")->getResultArray();
    if (empty($result)) {
        echo "No data in process_batches table<br>";
    } else {
        echo "Found " . count($result) . " batches<br>";
        foreach ($result as $batch) {
            echo "Batch ID: {$batch['id']}, Work Order Item ID: {$batch['work_order_item_id']}, Process ID: {$batch['process_id']}<br>";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Test the detailed query manually
echo "<h4>Testing Detailed Query:</h4>";
try {
    $query = "SELECT pb.*, woi.product_id, woi.quantity_ordered, wo.wo_number, wo.customer_name,
              p.name as product_name, p.code as product_code,
              pr.name as process_name, pr.is_vendor_process,
              COALESCE(SUM(pbl.qty_completed), 0) as completed_qty,
              COALESCE(SUM(pbl.qty_rejected), 0) as rejected_qty,
              COALESCE(SUM(pbl.qty_received), 0) as received_qty,
              MAX(pbl.log_date) as last_log_date
              FROM process_batches pb
              LEFT JOIN work_order_items woi ON woi.id = pb.work_order_item_id
              LEFT JOIN work_orders wo ON wo.id = woi.work_order_id
              LEFT JOIN products p ON p.id = woi.product_id
              LEFT JOIN processes pr ON pr.id = pb.process_id
              LEFT JOIN process_batch_logs pbl ON pbl.batch_id = pb.id
              GROUP BY pb.id
              LIMIT 5";
    
    $result = $db->query($query)->getResultArray();
    if (empty($result)) {
        echo "No results from detailed query<br>";
    } else {
        echo "Detailed query results:<br>";
        foreach ($result as $batch) {
            echo "Batch: {$batch['batch_code']}, WO: {$batch['wo_number']}, Product: {$batch['product_name']}<br>";
        }
    }
} catch (Exception $e) {
    echo "Detailed query error: " . $e->getMessage() . "<br>";
}

// Check if related tables have data
echo "<h4>Related Tables Check:</h4>";
$tables = ['work_order_items', 'work_orders', 'products', 'processes'];
foreach ($tables as $table) {
    try {
        $count = $db->query("SELECT COUNT(*) as count FROM $table")->getRow()->count;
        echo "$table: $count records<br>";
    } catch (Exception $e) {
        echo "$table: Error - " . $e->getMessage() . "<br>";
    }
}
?>
