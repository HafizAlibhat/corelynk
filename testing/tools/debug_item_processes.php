<?php
$mysqli = new mysqli('127.0.0.1','root','','production_management_system');
if ($mysqli->connect_errno) { echo "CONN_ERR:".$mysqli->connect_error.PHP_EOL; exit(1); }
// Find a work_order_item with a product
$item = $mysqli->query("SELECT * FROM work_order_items LIMIT 1")->fetch_assoc();
if (!$item) { echo json_encode(['error'=>'no work_order_items found']).PHP_EOL; exit; }
$productId = (int)$item['product_id'];
// Fetch product processes
$sql = "SELECT pp.*, p.name as process_name, p.description as process_description, p.standard_time_minutes, p.is_vendor_process, v.name as vendor_name
        FROM product_processes pp
        LEFT JOIN processes p ON p.id = pp.process_id
        LEFT JOIN vendors v ON v.id = p.vendor_id
        WHERE pp.product_id = ? AND pp.is_active = 1
        ORDER BY pp.sequence_order ASC";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $productId);
$stmt->execute();
$procs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
// Fetch batches for the item
$batchSql = "SELECT * FROM process_batches WHERE work_order_item_id = ? ORDER BY created_at DESC";
$bst = $mysqli->prepare($batchSql);
$bst->bind_param('i', $item['id']);
$bst->execute();
$batches = $bst->get_result()->fetch_all(MYSQLI_ASSOC);
$output = [
    'work_order_item' => $item,
    'processes' => $procs,
    'batches' => $batches
];
echo json_encode($output, JSON_PRETTY_PRINT) . PHP_EOL;
$mysqli->close();
