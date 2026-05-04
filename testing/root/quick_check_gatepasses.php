<?php
$mysqli = new mysqli('localhost', 'root', '', 'production_management_system');
if ($mysqli->connect_error) {
    echo "DB connect error: " . $mysqli->connect_error . "\n";
    exit(1);
}
$res = $mysqli->query("SHOW TABLES LIKE 'gate_passes'");
if ($res && $res->num_rows === 0) {
    echo "gate_passes table: NOT FOUND in production_management_system\n";
    // Print available tables for context
    $all = $mysqli->query("SHOW TABLES");
    while ($t = $all->fetch_array()) { echo "- ".$t[0]."\n"; }
    exit(0);
}

$countRes = $mysqli->query("SELECT COUNT(*) AS c FROM gate_passes");
$row = $countRes ? $countRes->fetch_assoc() : ['c' => 0];
echo "gate_passes count: " . $row['c'] . "\n";

$listRes = $mysqli->query("SELECT id, gate_pass_number, type, recipient_type, vendor_id, recipient_name, status, created_at FROM gate_passes ORDER BY id DESC LIMIT 5");
while ($r = $listRes->fetch_assoc()) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}
// Also show existence of related tables
foreach (['vendors','users','products'] as $tbl) {
    $exists = $mysqli->query("SHOW TABLES LIKE '".$tbl."'");
    echo $tbl . ': ' . (($exists && $exists->num_rows>0)?'YES':'NO') . "\n";
}
