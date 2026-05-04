<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

echo "=== Checking all processes ===\n";
$query = $db->query('SELECT p.*, pc.name as category_name FROM processes p LEFT JOIN process_categories pc ON pc.id = p.category_id ORDER BY p.name');
$processes = $query->getResultArray();
foreach($processes as $process) {
    echo "ID: {$process['id']} | Name: {$process['name']} | Category: " . ($process['category_name'] ?? 'No Category') . " | Active: " . ($process['is_active'] ? 'Yes' : 'No') . "\n";
}

echo "\n=== Checking process categories ===\n";
$query = $db->query('SELECT * FROM process_categories WHERE is_active = 1 ORDER BY name');
$categories = $query->getResultArray();
foreach($categories as $category) {
    echo "ID: {$category['id']} | Name: {$category['name']}\n";
}

echo "\n=== Product_processes table structure ===\n";
$query = $db->query('DESCRIBE product_processes');
$fields = $query->getResultArray();
foreach($fields as $field) {
    echo $field['Field'] . ' - ' . $field['Type'] . "\n";
}
?>
