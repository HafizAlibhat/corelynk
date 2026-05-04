<?php
require_once __DIR__ . '/vendor/autoload.php';

use Config\Database;

$db = Database::connect();

echo "=== purchase_order_lines table columns ===\n";
$columns = $db->query("SHOW COLUMNS FROM purchase_order_lines")->getResultArray();
foreach ($columns as $col) {
    echo $col['Field'] . " - " . $col['Type'] . "\n";
}

echo "\n=== Sample row from purchase_order_lines ===\n";
$sample = $db->table('purchase_order_lines')->limit(1)->get()->getRowArray();
if ($sample) {
    print_r($sample);
} else {
    echo "No rows found\n";
}
