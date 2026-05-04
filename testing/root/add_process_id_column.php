<?php
$pdo = new PDO('mysql:host=localhost;dbname=production_management_system', 'root', '');
echo "Adding process_id column to product_processes table...\n";
try {
    $pdo->exec('ALTER TABLE product_processes ADD COLUMN process_id INT(10) UNSIGNED NULL AFTER product_id');
    echo "Column added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
