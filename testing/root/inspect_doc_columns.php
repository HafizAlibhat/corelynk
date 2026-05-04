<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
foreach (['quotations','sales_orders','purchase_orders','vendor_bills'] as $table) {
    echo strtoupper($table) . PHP_EOL;
    $res = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
    while ($row = $res->fetch_assoc()) {
        echo '  - ' . $row['Field'] . PHP_EOL;
    }
    $res->free();
}
$mysqli->close();
