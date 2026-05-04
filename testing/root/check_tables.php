<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

echo "Available tables:\n";
$query = $db->query('SHOW TABLES');
$tables = $query->getResultArray();
foreach($tables as $table) {
    echo "- " . array_values($table)[0] . "\n";
}

echo "\nProcesses table structure:\n";
$query = $db->query('DESCRIBE processes');
$fields = $query->getResultArray();
foreach($fields as $field) {
    echo $field['Field'] . ' - ' . $field['Type'] . "\n";
}

echo "\nProcess categories table structure:\n";
$query = $db->query('DESCRIBE process_categories');
$fields = $query->getResultArray();
foreach($fields as $field) {
    echo $field['Field'] . ' - ' . $field['Type'] . "\n";
}
?>
