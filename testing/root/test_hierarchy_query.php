<?php
// Simple database test to see what getWorkOrdersWithHierarchy would return
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== Testing Work Orders Hierarchy Query ===\n";

$query = "
SELECT wo.id, wo.wo_number, wo.status as wo_status, wo.created_at,
       COUNT(DISTINCT woi.id) as total_products,
       COUNT(DISTINCT pb.id) as total_batches,
       COALESCE(SUM(pb.planned_qty), 0) as total_planned_qty,
       COALESCE(SUM(CASE WHEN pb.status = 'completed' THEN pb.planned_qty ELSE 0 END), 0) as completed_qty
FROM work_orders wo
LEFT JOIN work_order_items woi ON woi.work_order_id = wo.id
LEFT JOIN process_batches pb ON pb.work_order_item_id = woi.id
WHERE wo.status != 'completed'
GROUP BY wo.id, wo.wo_number, wo.status, wo.created_at
ORDER BY wo.created_at DESC
";

echo "Query:\n$query\n\n";

$result = $mysqli->query($query);
if ($result) {
    echo "Results:\n";
    while ($row = $result->fetch_assoc()) {
        $progress = $row['total_planned_qty'] > 0 ? round(($row['completed_qty'] / $row['total_planned_qty']) * 100, 1) : 0;
        $row['progress_percentage'] = $progress;
        $row['has_children'] = $row['total_products'] > 0;
        print_r($row);
    }
} else {
    echo "Query error: " . $mysqli->error . "\n";
}

$mysqli->close();