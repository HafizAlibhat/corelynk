<?php
// List recent process_batches rows
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'production_management_system';
$port = 3306;
$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
if ($mysqli->connect_errno) { echo "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n"; exit(1);} 
$mysqli->set_charset('utf8mb4');

$res = $mysqli->query("SELECT id, batch_code, work_order_item_id, status, created_at FROM process_batches ORDER BY id DESC LIMIT 20");
if (!$res) { echo "Query error: ({$mysqli->errno}) {$mysqli->error}\n"; exit(2); }

$rows = $res->fetch_all(MYSQLI_ASSOC);
if (count($rows) === 0) {
    echo "No rows in process_batches\n";
} else {
    echo "Recent process_batches:\n";
    foreach ($rows as $r) {
        printf("id=%d code=%s woi=%s status=%s created=%s\n", $r['id'], $r['batch_code'] ?? '(null)', $r['work_order_item_id'] ?? 'NULL', $r['status'] ?? '(null)', $r['created_at'] ?? '(null)');
    }
}
$mysqli->close();
