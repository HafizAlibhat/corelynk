<?php
// Simple smoke test using mysqli to avoid full CodeIgniter bootstrap
require __DIR__ . '/../app/Config/Database.php';
use Config\Database;

$dbConf = new Database();
$group = $dbConf->defaultGroup ?? 'default';
$conf = $dbConf->{$group};

$mysqli = new mysqli($conf->hostname, $conf->username, $conf->password, $conf->database, $conf->port ?? 3306);
if ($mysqli->connect_errno) {
    echo "DB connect failed: " . $mysqli->connect_error . "\n"; exit(1);
}

echo "Connected to DB: {$conf->database}\n";

$res = $mysqli->query("SELECT id FROM work_order_items LIMIT 1");
if (!$res || $res->num_rows === 0) { echo "No work_order_items, aborting\n"; exit(1); }
$woItem = $res->fetch_assoc();

$res2 = $mysqli->query("SELECT id FROM processes LIMIT 1");
if (!$res2 || $res2->num_rows === 0) { echo "No processes, aborting\n"; exit(1); }
$proc = $res2->fetch_assoc();

$batchCode = 'SMOKE_SIMPLE_' . time();
$insSql = sprintf("INSERT INTO process_batches (work_order_item_id, process_id, batch_code, planned_qty, status, created_by, created_at) VALUES (%d, %d, '%s', %d, 'planned', 1, NOW())", (int)$woItem['id'], (int)$proc['id'], $mysqli->real_escape_string($batchCode), 5);
if (!$mysqli->query($insSql)) { echo "Insert batch failed: " . $mysqli->error . "\n"; exit(1); }
$batchId = $mysqli->insert_id;
echo "Inserted batch id $batchId\n";

$insLogSql = sprintf("INSERT INTO process_batch_logs (batch_id, qty_completed, qty_rejected, notes, log_date, created_at) VALUES (%d, %d, %d, '%s', CURDATE(), NOW())", $batchId, 1, 0, $mysqli->real_escape_string('smoke')); 
if (!$mysqli->query($insLogSql)) { echo "Insert log failed: " . $mysqli->error . "\n"; }
else { echo "Inserted log for batch $batchId\n"; }

// cleanup
$mysqli->query("DELETE FROM process_batch_logs WHERE batch_id = " . (int)$batchId);
$mysqli->query("DELETE FROM process_batches WHERE id = " . (int)$batchId);
echo "Cleanup done\n";
