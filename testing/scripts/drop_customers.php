<?php
// Runner script to execute database/drop_customers_tables.sql
// WARNING: This will permanently drop tables. Run only if you intend to remove customer data.

$host = 'localhost';
$db = 'corelynk_db';
$user = 'root';
$pass = '';

$sqlFile = __DIR__ . '/../database/drop_customers_tables.sql';
if (!file_exists($sqlFile)) {
    echo "SQL file not found: $sqlFile\n";
    exit(1);
}

$sql = file_get_contents($sqlFile);
if (trim($sql) === '') {
    echo "SQL file is empty.\n";
    exit(1);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "Connected to database $db.\n";
    echo "Executing drop script...\n";
    $pdo->exec($sql);
    echo "Drop script executed successfully. Tables removed (if they existed).\n";
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}
