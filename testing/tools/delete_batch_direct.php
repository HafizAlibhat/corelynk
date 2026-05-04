<?php
// Direct DB delete helper for debugging (run from project root via CLI)
// Usage: php tools/delete_batch_direct.php <batch_id>

$batchId = isset($argv[1]) ? (int) $argv[1] : 0;
if ($batchId <= 0) {
    echo "Usage: php tools/delete_batch_direct.php <batch_id>\n";
    exit(2);
}

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'production_management_system';
$port = 3306;

$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
if ($mysqli->connect_errno) {
    echo "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n";
    exit(3);
}
$mysqli->set_charset('utf8mb4');

function showRow($mysqli, $id) {
    $id = (int) $id;
    $res = $mysqli->query("SELECT * FROM process_batches WHERE id={$id}");
    if (!$res) {
        echo "Select error: ({$mysqli->errno}) {$mysqli->error}\n";
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();
    return $row;
}

echo "Checking batch id={$batchId} before delete...\n";
$before = showRow($mysqli, $batchId);
if ($before) {
    echo "Found batch: id={$before['id']}, batch_code={$before['batch_code']}, status={$before['status']}, work_order_item_id={$before['work_order_item_id']}\n";
} else {
    echo "No batch row found with id={$batchId}\n";
}

// Attempt delete
echo "Attempting DELETE FROM process_batches WHERE id={$batchId}...\n";
$ok = $mysqli->query("DELETE FROM process_batches WHERE id={$batchId}");
if ($ok === false) {
    echo "Delete error: ({$mysqli->errno}) {$mysqli->error}\n";
    $mysqli->close();
    exit(4);
}
$affected = $mysqli->affected_rows;
echo "Delete query executed, affected_rows={$affected}\n";

$after = showRow($mysqli, $batchId);
if ($after) {
    echo "After delete, row still present: id={$after['id']}, batch_code={$after['batch_code']}\n";
} else {
    echo "After delete, no row found with id={$batchId} (deleted)\n";
}

$mysqli->close();
exit(0);
