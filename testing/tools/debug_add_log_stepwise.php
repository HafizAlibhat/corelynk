<?php
// Debug script to replicate ajaxAddBatchLog logic step-by-step and report DB errors
$batchId = $argv[1] ?? null;
if (!$batchId) { echo "Usage: php debug_add_log_stepwise.php <batchId>\n"; exit(1); }

$db = \Config\Database::connect();
$batch = $db->table('process_batches')->where('id', $batchId)->get()->getRowArray();
if (!$batch) { echo "Batch not found: {$batchId}\n"; exit(1); }

$accepted = 2; $repaired = 0; $rejected = 0; $notes = 'Debug stepwise';
$operatorId = 1;

echo "Batch found: id={$batch['id']} work_order_item_id={$batch['work_order_item_id']} planned={$batch['planned_qty']}\n";

// Sum existing logs
$existing = $db->table('process_batch_logs')->select('SUM(accepted_qty) as a, SUM(repaired_qty) as r, SUM(rejected_qty) as x')->where('process_batch_id', $batchId)->get()->getRowArray();
$already = ((int) ($existing['a'] ?? 0)) + ((int) ($existing['r'] ?? 0)) + ((int) ($existing['x'] ?? 0));
$incoming = $accepted + $repaired + $rejected;
echo "Already: {$already} Incoming: {$incoming}\n";

$planned = (float) ($batch['planned_qty'] ?? 0);
if ($planned > 0 && ($already + $incoming) > $planned) { echo "Would exceed planned. abort.\n"; exit; }

// Start transaction
$db->transStart();

// Insert log
$logData = [
    'process_batch_id' => $batchId,
    'log_date' => date('Y-m-d H:i:s'),
    'accepted_qty' => $accepted,
    'repaired_qty' => $repaired,
    'rejected_qty' => $rejected,
    'operator_id' => $operatorId,
    'notes' => $notes,
];

$ok = $db->table('process_batch_logs')->insert($logData);
$err = $db->error();
echo "Insert log ok=" . ($ok ? '1' : '0') . " errno={$err['code']} msg={$err['message']}\n";

// Update work_order_items completed qty
$completedInc = $accepted + $repaired;
if ($completedInc > 0) {
    try {
        $woItemTable = 'work_order_items';
        $columns = $db->query("SHOW COLUMNS FROM {$woItemTable}")->getResultArray();
        $colNames = array_column($columns, 'Field');
        $candidate = null;
        if (in_array('quantity_completed', $colNames)) {
            $candidate = 'quantity_completed';
        } elseif (in_array('completed_qty', $colNames)) {
            $candidate = 'completed_qty';
        } elseif (in_array('quantity_done', $colNames)) {
            $candidate = 'quantity_done';
        }

        echo "Detected completed column: " . ($candidate ?? 'none') . "\n";
        if ($candidate) {
            $res = $db->table($woItemTable)->set($candidate, "{$candidate} + {$completedInc}", false)->where('id', $batch['work_order_item_id'])->update();
            $err = $db->error();
            echo "Update wo_item ok=" . ($res ? '1' : '0') . " errno={$err['code']} msg={$err['message']}\n";
        }
    } catch (Exception $e) {
        echo "Exception updating wo_item: " . $e->getMessage() . "\n";
    }
}

// Recalculate totals and possibly close batch
$totals = $db->table('process_batch_logs')->select('SUM(accepted_qty) as total_accepted, SUM(repaired_qty) as total_repaired, SUM(rejected_qty) as total_rejected')->where('process_batch_id', $batchId)->get()->getRowArray();
$totalAll = ((int) ($totals['total_accepted'] ?? 0)) + ((int) ($totals['total_repaired'] ?? 0)) + ((int) ($totals['total_rejected'] ?? 0));

if ($planned > 0 && $totalAll >= $planned) {
    $res = $db->table('process_batches')->where('id', $batchId)->update(['status' => 'closed', 'completed_at' => date('Y-m-d H:i:s')]);
    $err = $db->error();
    echo "Update batch close ok=" . ($res ? '1' : '0') . " errno={$err['code']} msg={$err['message']}\n";
}

$db->transComplete();
$transOk = $db->transStatus();
$err = $db->error();
echo "Transaction complete status=" . ($transOk ? '1' : '0') . " errno={$err['code']} msg={$err['message']}\n";

if (!$transOk) {
    echo "Transaction failed; rolling back.\n";
}

exit;
