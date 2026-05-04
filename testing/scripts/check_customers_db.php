<?php
// Quick DB check script for customers table
$host = '127.0.0.1';
$port = 3306;
$user = 'root';
$pass = '';
$db   = 'corelynk_db';

$mysqli = @new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "DB connect error: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$res = $mysqli->query("SHOW TABLES LIKE 'customers'");
if (!$res || $res->num_rows === 0) {
    echo "Table 'customers' not found in database '{$db}'.\n";
    exit(2);
}

$countR = $mysqli->query("SELECT COUNT(*) AS cnt FROM customers");
$count = 0;
if ($countR) {
    $row = $countR->fetch_assoc();
    $count = (int) ($row['cnt'] ?? 0);
}

echo "Customers row count: {$count}\n\n";

    // Discover columns and select a safe subset
    $colsRes = $mysqli->query("SHOW COLUMNS FROM customers");
    $cols = [];
    if ($colsRes) {
        while ($c = $colsRes->fetch_assoc()) {
            $cols[] = $c['Field'];
        }
    }

    $pick = ['id','customer_code','name','status','created_at'];
    $available = [];
    foreach ($pick as $p) if (in_array($p, $cols)) $available[] = $p;
    if (empty($available)) {
        echo "No common customer columns found to display. Columns present: " . implode(', ', $cols) . "\n";
        exit(3);
    }

    $sql = "SELECT " . implode(', ', $available) . " FROM customers ORDER BY id DESC LIMIT 5";
    $rows = $mysqli->query($sql);
    if ($rows && $rows->num_rows > 0) {
        echo "Last up to 5 customers:\n";
        while ($r = $rows->fetch_assoc()) {
            echo json_encode($r, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) . PHP_EOL;
        }
    } else {
        echo "No customer rows found.\n";
}

$mysqli->close();

return 0;
