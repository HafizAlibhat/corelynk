<?php
$db = new mysqli('localhost', 'root', '', 'pro_sys');
if ($db->connect_error) die('Connection failed');

echo "=== Work Orders ===\n";
$result = $db->query("SELECT * FROM work_orders");
echo "Count: " . $result->num_rows . "\n";

echo "\n=== Products ===\n";
$result = $db->query("SELECT * FROM products");
echo "Count: " . $result->num_rows . "\n";

echo "\n=== Processes ===\n";
$result = $db->query("SELECT * FROM processes");
echo "Count: " . $result->num_rows . "\n";

echo "\n=== Work Order Items ===\n";
$result = $db->query("SELECT * FROM work_order_items");
echo "Count: " . $result->num_rows . "\n";

echo "\n=== Process Batches ===\n";
$result = $db->query("SELECT * FROM process_batches");
echo "Count: " . $result->num_rows . "\n";
