<?php
require __DIR__ . '/vendor/autoload.php';

$db = \Config\Database::connect();

echo "purchase_order_lines table columns:\n";
$fields = $db->getFieldNames('purchase_order_lines');
print_r($fields);

echo "\n\nSample PO line data:\n";
$result = $db->query("SELECT * FROM purchase_order_lines WHERE po_id = 2 LIMIT 1");
$row = $result->getRowArray();
if ($row) {
    print_r($row);
} else {
    echo "No PO lines found for PO #2\n";
}
