<?php
$db = new mysqli('localhost', 'root', '', 'production_management_system');
if ($db->connect_error) die('Connection failed');

echo "=== Expected Tree Data ===\n\n";

// Get work orders
$wos = $db->query("SELECT id, wo_number, status FROM work_orders WHERE status != 'completed' LIMIT 5");
while ($wo = $wos->fetch_assoc()) {
    echo "📋 {$wo['wo_number']} (Status: {$wo['status']})\n";
    
    // Get products for this WO
    $products = $db->query("SELECT woi.id as woi_id, p.name, p.code, woi.quantity_ordered 
                           FROM work_order_items woi 
                           JOIN products p ON p.id = woi.product_id 
                           WHERE woi.work_order_id = {$wo['id']}");
    
    while ($prod = $products->fetch_assoc()) {
        echo "  📦 {$prod['name']} ({$prod['code']}) - Ordered: {$prod['quantity_ordered']}\n";
        
        // Get processes/batches for this product
        $batches = $db->query("SELECT pb.id, pb.batch_code, pb.batch_number, pb.planned_qty, pb.actual_qty, pb.status, pr.name as process_name 
                              FROM process_batches pb 
                              JOIN processes pr ON pr.id = pb.process_id 
                              WHERE pb.work_order_item_id = {$prod['woi_id']}");
        
        while ($batch = $batches->fetch_assoc()) {
            $code = $batch['batch_code'] ?: $batch['batch_number'];
            $actual = $batch['actual_qty'] ?? 0;
            $planned = $batch['planned_qty'] ?? 0;
            $percent = $planned > 0 ? round(($actual / $planned) * 100) : 0;
            echo "    ⚙️  {$batch['process_name']}\n";
            echo "      📊 {$code}: {$actual}/{$planned} pcs ({$percent}%) - {$batch['status']}\n";
        }
    }
    echo "\n";
}
