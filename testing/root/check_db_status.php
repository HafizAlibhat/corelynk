<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system', 3306);
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

echo "Tables in database:" . PHP_EOL;
$res = $mysqli->query('SHOW TABLES');
if (!$res) {
    echo 'ERR: ' . $mysqli->error . PHP_EOL;
    exit(1);
}

while ($row = $res->fetch_array()) {
    echo '- ' . $row[0] . PHP_EOL;
}

// Check if processes table has category_id column
echo PHP_EOL . "Checking processes table structure:" . PHP_EOL;
$res = $mysqli->query('DESCRIBE processes');
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . "\t" . $row['Type'] . PHP_EOL;
    }
} else {
    echo 'processes table does not exist or error: ' . $mysqli->error . PHP_EOL;
}

// Check if process_categories table exists
echo PHP_EOL . "Checking process_categories table:" . PHP_EOL;
$res = $mysqli->query('DESCRIBE process_categories');
if ($res) {
    echo "process_categories table exists" . PHP_EOL;
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . "\t" . $row['Type'] . PHP_EOL;
    }
} else {
    echo 'process_categories table does not exist: ' . $mysqli->error . PHP_EOL;
}

// Check if product_processes table exists
echo PHP_EOL . "Checking product_processes table:" . PHP_EOL;
$res = $mysqli->query('DESCRIBE product_processes');
if ($res) {
    echo "product_processes table exists" . PHP_EOL;
    while ($row = $res->fetch_assoc()) {
        echo $row['Field'] . "\t" . $row['Type'] . PHP_EOL;
    }
} else {
    echo 'product_processes table does not exist: ' . $mysqli->error . PHP_EOL;
}
