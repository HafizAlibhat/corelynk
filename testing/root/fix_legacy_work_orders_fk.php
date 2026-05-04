<?php
// Quick fixer: remove legacy product_id foreign key on work_orders if present to avoid FK errors during create.
// Usage (Windows PowerShell): php fix_legacy_work_orders_fk.php

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$name = getenv('DB_NAME') ?: 'production_management_system';

$mysqli = new mysqli($host, $user, $pass, $name);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connection failed: {$mysqli->connect_error}\n");
    exit(1);
}

// Check columns and FKs on work_orders
$res = $mysqli->query("SHOW COLUMNS FROM work_orders");
$cols = [];
while ($row = $res->fetch_assoc()) { $cols[$row['Field']] = true; }

$fkName = null;
$res2 = $mysqli->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = '".$mysqli->real_escape_string($name)."' AND TABLE_NAME = 'work_orders' AND REFERENCED_TABLE_NAME = 'products'");
if ($res2) {
    while ($r = $res2->fetch_assoc()) { $fkName = $r['CONSTRAINT_NAME']; break; }
}

$queries = [];
if ($fkName) {
    $queries[] = "ALTER TABLE work_orders DROP FOREIGN KEY `{$fkName}`";
}
// If legacy columns present and you want to fully migrate away, uncomment to drop columns too:
// if (isset($cols['product_id'])) $queries[] = "ALTER TABLE work_orders DROP COLUMN product_id";
// if (isset($cols['quantity_ordered'])) $queries[] = "ALTER TABLE work_orders DROP COLUMN quantity_ordered";
// if (isset($cols['quantity_completed'])) $queries[] = "ALTER TABLE work_orders DROP COLUMN quantity_completed";

foreach ($queries as $q) {
    if (!$mysqli->query($q)) {
        fwrite(STDERR, "Failed: $q\nError: {$mysqli->error}\n");
        exit(2);
    } else {
        echo "OK: $q\n";
    }
}

echo "Done.\n";
