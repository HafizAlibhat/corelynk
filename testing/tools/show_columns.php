<?php
// Usage:
//   php tools/show_columns.php customer_invoice_lines
//   php tools/show_columns.php sales_order_lines

$table = $argv[1] ?? null;
if (!$table) {
    fwrite(STDERR, "Usage: php tools/show_columns.php table_name\n");
    exit(1);
}

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbname = getenv('DB_NAME') ?: 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
    exit(2);
}

// Basic identifier hardening
$tableSafe = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
if ($tableSafe === '') {
    fwrite(STDERR, "Invalid table name\n");
    exit(3);
}

$res = $mysqli->query("SHOW COLUMNS FROM `{$tableSafe}`");
if (!$res) {
    fwrite(STDERR, "Query failed: ({$mysqli->errno}) {$mysqli->error}\n");
    exit(4);
}

while ($row = $res->fetch_assoc()) {
    echo $row['Field'], PHP_EOL;
}
