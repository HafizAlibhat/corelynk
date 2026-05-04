<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system', 3306);
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

echo "PROCESSES table structure:" . PHP_EOL;
$res = $mysqli->query('DESCRIBE processes');
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . "\t" . $row['Type'] . "\t" . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . PHP_EOL;
}

echo PHP_EOL . "Foreign keys on processes table:" . PHP_EOL;
$res = $mysqli->query("SELECT 
    COLUMN_NAME, 
    CONSTRAINT_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME 
FROM 
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
WHERE 
    REFERENCED_TABLE_SCHEMA = 'production_management_system' 
    AND TABLE_NAME = 'processes'");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
