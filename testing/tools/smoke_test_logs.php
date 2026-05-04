<?php
require __DIR__ . '/../app/Config/Paths.php';
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap CodeIgniter minimal environment
$paths = new Config\Paths();
$loader = new \CodeIgniter\Autoloader( new Config\Autoload() );

// Use models directly
use App\Models\BatchModel;
use App\Models\BatchLogModel;

echo "Running logs module smoke test...\n";
$batchModel = new BatchModel();
$batchLogModel = new BatchLogModel();

// Create a minimal batch (requires work_order_item_id and process_id exist)
$db = \Config\Database::connect();
$woItem = $db->table('work_order_items')->get(1)->getRowArray();
if (!$woItem) {
    echo "No work_order_items found (need at least one) - aborting smoke test.\n";
    exit(1);
}

$sample = [
    'work_order_item_id' => $woItem['id'],
    'process_id' => $db->table('processes')->select('id')->get(1)->getRowArray()['id'] ?? 1,
    'batch_code' => 'SMOKE-' . time(),
    'planned_qty' => 10,
    'status' => 'planned',
    'created_by' => 1,
    'created_at' => date('Y-m-d H:i:s')
];

$id = $batchModel->createBatch($sample);
if (!$id) { echo "Failed to create batch\n"; exit(1); }
echo "Created batch id: $id\n";

try {
    $ok = $batchLogModel->insertLog(['batch_id' => $id, 'qty_completed' => 2, 'qty_rejected' => 0, 'notes' => 'smoke test', 'log_date' => date('Y-m-d')]);
    echo $ok ? "Inserted log\n" : "Failed to insert log\n";
} catch (Exception $e) {
    echo "Insert log exception: " . $e->getMessage() . "\n";
}

// Cleanup
$db->table('process_batch_logs')->where('batch_id', $id)->delete();
$db->table('process_batches')->where('id', $id)->delete();

echo "Cleanup done.\n";
