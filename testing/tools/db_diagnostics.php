<?php
// DB diagnostics: counts and one sample row from key tables.
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'production_management_system';
$port = 3306;
$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
if ($mysqli->connect_errno) { echo "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n"; exit(1); }
$mysqli->set_charset('utf8mb4');

$tables = ['users','products','processes','work_orders','work_order_items','process_batches','process_batch_logs'];

foreach ($tables as $t) {
    $res = $mysqli->query("SELECT COUNT(*) as c FROM {$t}");
    if ($res) {
        $row = $res->fetch_assoc();
        echo "{$t}: " . ($row['c'] ?? '0') . "\n";
        $res->free();
    } else {
        echo "{$t}: ERROR (" . $mysqli->errno . ") " . $mysqli->error . "\n";
    }
}

echo "\nRecent rows (up to 5)\n";
// For each table, choose safe sample columns by querying the table's columns first.
foreach ($tables as $k) {
    echo "\n--- {$k} ---\n";
    $colsRes = $mysqli->query("SHOW COLUMNS FROM {$k}");
    if (!$colsRes) {
        echo "Unable to inspect columns for {$k}: ({$mysqli->errno}) {$mysqli->error}\n";
        continue;
    }

    $cols = [];
    while ($c = $colsRes->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
    $colsRes->free();

    if (empty($cols)) {
        echo "No columns detected for {$k}\n";
        continue;
    }

    // Prefer commonly useful columns but fallback to the first 3 columns present
    $preferred = [];
    if ($k === 'users') {
        $preferred = ['id', 'name', 'full_name', 'username', 'email'];
    } elseif ($k === 'products') {
        $preferred = ['id', 'name', 'product_code', 'code'];
    } elseif ($k === 'processes') {
        $preferred = ['id', 'name', 'process_code'];
    } elseif ($k === 'work_orders') {
        $preferred = ['id', 'wo_number', 'status'];
    } elseif ($k === 'work_order_items') {
        $preferred = ['id', 'work_order_id', 'product_id', 'quantity_ordered'];
    } elseif ($k === 'process_batches') {
        $preferred = ['id', 'batch_code', 'batch_number', 'work_order_item_id', 'process_id', 'status', 'quantity'];
    } elseif ($k === 'process_batch_logs') {
        $preferred = ['id', 'batch_id', 'batch_id', 'batch_id', 'created_at', 'log_date'];
    }

    $pick = [];
    foreach ($preferred as $p) {
        if (in_array($p, $cols, true)) {
            $pick[] = $p;
        }
        if (count($pick) >= 3) break;
    }

    if (empty($pick)) {
        // fallback: first up to 3 columns
        $pick = array_slice($cols, 0, 3);
    }

    $colList = implode(', ', array_map(function($c){ return $c; }, $pick));
    $sql = "SELECT {$colList} FROM {$k} ORDER BY id DESC LIMIT 5";

    $res = $mysqli->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            echo json_encode($r) . "\n";
        }
        $res->free();
    } else {
        echo "Query error for {$k}: ({$mysqli->errno}) {$mysqli->error}\n";
    }
}

$mysqli->close();
