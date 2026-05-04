<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

echo "Product_processes table structure:\n";
$query = $db->query('DESCRIBE product_processes');
$fields = $query->getResultArray();
foreach($fields as $field) {
    echo $field['Field'] . ' - ' . $field['Type'] . "\n";
}

echo "\nSample product_processes data:\n";
$query = $db->query('SELECT * FROM product_processes LIMIT 3');
$processes = $query->getResultArray();
foreach($processes as $process) {
    print_r($process);
    echo "\n";
}
?>
