<?php
// Temporary migration runner - safe for local dev only
// Usage (PowerShell):
//   $env:MIGRATION_FILE='database/migrations/20260103_alter_customer_invoice_lines_add_pricing_fields.sql'; php tools/apply_migration.php
//   $env:DB_NAME='production_management_system'; $env:DB_PASS=''; php tools/apply_migration.php

$migrationRel = getenv('MIGRATION_FILE') ?: 'database/migrations/20260103_alter_customer_invoice_lines_add_pricing_fields.sql';
$path = __DIR__ . '/../' . ltrim($migrationRel, '/\\');
if (!file_exists($path)) {
    echo "SQL file not found: $path\n";
    exit(2);
}
$sql = file_get_contents($path);

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbname = getenv('DB_NAME') ?: 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_errno) {
    echo "Connect failed: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error . "\n";
    exit(3);
}

// Increase timeout and packet sizes if needed
$mysqli->options(MYSQLI_OPT_CONNECT_TIMEOUT, 5);

if ($mysqli->multi_query($sql)) {
    do {
        if ($res = $mysqli->store_result()) {
            // consume result
            $res->free();
        }
        // flush multi queries
    } while ($mysqli->more_results() && $mysqli->next_result());

    if ($mysqli->errno) {
        echo "SQL error after execution: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
        exit(4);
    }

    echo "Migration applied successfully: $migrationRel\n";

    $mysqli->close();
    exit(0);
} else {
    echo "Failed to run SQL: (" . $mysqli->errno . ") " . $mysqli->error . "\n";
    $mysqli->close();
    exit(6);
}
