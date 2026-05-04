<?php
$db = new mysqli('localhost', 'root', '', 'production_management_system');
if ($db->connect_error) die('Connection failed');

echo "=== process_batches table structure ===\n";
$result = $db->query('DESCRIBE process_batches');
while ($row = $result->fetch_assoc()) {
    echo "- {$row['Field']} ({$row['Type']})\n";
}
