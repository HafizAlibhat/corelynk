<?php
// Direct mysqli-based debug to reproduce insert/update steps and capture MySQL errors
$batchId = $argv[1] ?? null;
if (!$batchId) { echo "Usage: php debug_add_log_mysqli.php <batchId>\n"; exit(1); }

$host = '127.0.0.1'; $user = 'root'; $pass = ''; $dbName = 'production_management_system';
$mysqli = new mysqli($host, $user, $pass, $dbName);
if ($mysqli->connect_errno) { echo "DB connect error: {$mysqli->connect_error}\n"; exit(1); }
$mysqli->set_charset('utf8mb4');

$batchId = (int)$batchId;
$res = $mysqli->query("SELECT * FROM process_batches WHERE id={$batchId}");
$batch = $res->fetch_assoc();
if (!$batch) { echo "Batch not found\n"; exit(1); }
echo "Batch: id={$batch['id']} wo_item={$batch['work_order_item_id']} planned={$batch['planned_qty']}\n";

$accepted = 2; $repaired = 0; $rejected = 0; $operator_id = 1; $notes = 'mysqli debug';

// Sum existing logs
$res = $mysqli->query("SELECT SUM(accepted_qty) as a, SUM(repaired_qty) as r, SUM(rejected_qty) as x FROM process_batch_logs WHERE process_batch_id={$batchId}");
$existing = $res->fetch_assoc();
$already = (float)($existing['a'] ?? 0) + (float)($existing['r'] ?? 0) + (float)($existing['x'] ?? 0);
$incoming = $accepted + $repaired + $rejected;

echo "Already={$already} Incoming={$incoming}\n";

$planned = (float)$batch['planned_qty'];
if ($planned > 0 && ($already + $incoming) > $planned) { echo "Would exceed planned. abort.\n"; exit; }

$mysqli->begin_transaction();

// Insert log
$logSql = $mysqli->prepare("INSERT INTO process_batch_logs (process_batch_id, log_date, accepted_qty, repaired_qty, rejected_qty, operator_id, notes) VALUES (?, NOW(), ?, ?, ?, ?, ?)");
$logSql->bind_param('idddis', $batchId, $accepted, $repaired, $rejected, $operator_id, $notes);
$ok = $logSql->execute();
if (!$ok) { echo "Insert log error: ({$logSql->errno}) {$logSql->error}\n"; $mysqli->rollback(); exit; }
echo "Inserted log id={$mysqli->insert_id}\n";

// Update work_order_items: detect column
$cols = [];
$res = $mysqli->query("SHOW COLUMNS FROM work_order_items");
while ($row = $res->fetch_assoc()) $cols[] = $row['Field'];
$candidate = null;
foreach (['quantity_completed','completed_qty','quantity_done','quantity_completed'] as $c) if (in_array($c, $cols)) { $candidate = $c; break; }

echo "Detected completed column: " . ($candidate ?? 'none') . "\n";
if ($candidate) {
    $sql = "UPDATE work_order_items SET {$candidate} = {$candidate} + ? WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('di', $accepted, $batch['work_order_item_id']);
    $ok = $stmt->execute();
    if (!$ok) { echo "Update work_order_items error: ({$stmt->errno}) {$stmt->error}\n"; $mysqli->rollback(); exit; }
    echo "Updated work_order_items id={$batch['work_order_item_id']}\n";
}

// Recalculate totals
$res = $mysqli->query("SELECT SUM(accepted_qty) as a, SUM(repaired_qty) as r, SUM(rejected_qty) as x FROM process_batch_logs WHERE process_batch_id={$batchId}");
$tot = $res->fetch_assoc();
$totalAll = (float)($tot['a'] ?? 0) + (float)($tot['r'] ?? 0) + (float)($tot['x'] ?? 0);

if ($planned > 0 && $totalAll >= $planned) {
    $ok = $mysqli->query("UPDATE process_batches SET status='closed', completed_at=NOW() WHERE id={$batchId}");
    if (!$ok) { echo "Update process_batches error: ({$mysqli->errno}) {$mysqli->error}\n"; $mysqli->rollback(); exit; }
    echo "Batch closed\n";
}

$mysqli->commit();

echo "Transaction committed\n";

$mysqli->close();
