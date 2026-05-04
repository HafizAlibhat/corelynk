<?php
$db = new mysqli('localhost','root','','production_management_system');
if ($db->connect_errno) { echo "connect_failed: " . $db->connect_error . "\n"; exit(1); }
$tables = ['work_orders','work_order_items','process_batches','process_batch_logs'];
foreach ($tables as $t) {
    $res = $db->query("SELECT COUNT(*) as c FROM `".$t."`");
    if (!$res) { echo "$t: query failed: " . $db->error . "\n"; continue; }
    $r = $res->fetch_assoc();
    echo $t . ': ' . ($r['c'] ?? 0) . "\n";
}
