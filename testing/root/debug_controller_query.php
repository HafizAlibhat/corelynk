<?php
// Test the exact query the controller is using
$db = new mysqli('localhost', 'root', '', 'production_management_system');
if ($db->connect_error) die('Connection failed');

echo "=== Testing Controller Query ===\n";

$query = "SELECT wo.id, wo.wo_number, wo.status as wo_status, wo.created_at,
          COUNT(DISTINCT woi.id) as total_products,
          COUNT(DISTINCT pb.id) as total_batches,
          COALESCE(SUM(pb.planned_qty), 0) as total_planned_qty,
          COALESCE(SUM(CASE WHEN pb.status = 'completed' THEN pb.planned_qty ELSE 0 END), 0) as completed_qty
          FROM work_orders wo
          LEFT JOIN work_order_items woi ON woi.work_order_id = wo.id
          LEFT JOIN process_batches pb ON pb.work_order_item_id = woi.id
          WHERE wo.status != 'completed'
          GROUP BY wo.id, wo.wo_number, wo.status, wo.created_at
          ORDER BY wo.created_at DESC";

$result = $db->query($query);
if ($result) {
    echo "Found " . $result->num_rows . " work orders\n\n";
    while ($row = $result->fetch_assoc()) {
        print_r($row);
        echo "\n";
    }
} else {
    echo "Query error: " . $db->error . "\n";
}