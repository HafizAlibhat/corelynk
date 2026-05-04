<?php
$db = new mysqli('localhost', 'root', '', 'pro_sys');
if ($db->connect_error) die('Connection failed');

echo "=== work_orders table structure ===\n";
$result = $db->query('DESCRIBE work_orders');
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})\n";
}

echo "\n=== work_order_items table structure ===\n";
$result = $db->query('DESCRIBE work_order_items');
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})\n";
}

echo "\n=== process_batches table structure ===\n";
$result = $db->query('DESCRIBE process_batches');
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})\n";
}
