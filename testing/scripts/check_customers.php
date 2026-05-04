<?php
// Simple DB check for customers table
$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'corelynk_db';
$port = 3306;

$mysqli = new mysqli($host, $user, $pass, $db, $port);
if ($mysqli->connect_errno) {
    echo "DB connection failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

$res = $mysqli->query("SHOW TABLES LIKE 'customers'");
if (!$res || $res->num_rows === 0) {
    echo "Table `customers` does not exist in database '{$db}'.\n";
    exit(2);
}

$r = $mysqli->query("SELECT COUNT(*) AS cnt FROM `customers`");
$cnt = ($r && $row = $r->fetch_assoc()) ? (int)$row['cnt'] : 0;
echo "customers table row count: {$cnt}\n";

$colsRes = $mysqli->query("SHOW COLUMNS FROM customers");
$cols = [];
if ($colsRes) {
    while ($c = $colsRes->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}
echo "customers table columns: " . implode(', ', $cols) . PHP_EOL;

$selectCols = array_intersect(['id','customer_code','name','status','created_at','metadata'], $cols);
if (empty($selectCols)) {
    echo "No selectable columns found on customers table.\n";
    exit(0);
}

$sql = 'SELECT ' . implode(', ', $selectCols) . ' FROM customers ORDER BY id DESC LIMIT 5';
$q = $mysqli->query($sql);
if ($q && $q->num_rows) {
    echo "Last rows (most recent first):\n";
    while ($row = $q->fetch_assoc()) {
        $out = [];
        foreach ($selectCols as $col) {
            $out[] = $col . '=' . (isset($row[$col]) ? $row[$col] : '');
        }
        echo implode(' | ', $out) . PHP_EOL;
    }
} else {
    echo "No rows found in customers table.\n";
}

$mysqli->close();
