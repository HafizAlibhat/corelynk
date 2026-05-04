<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

try {
    // Add category_id field to processes table
    $sql = "ALTER TABLE processes ADD COLUMN category_id INT UNSIGNED NULL AFTER product_id";
    $db->query($sql);
    echo "Added category_id column to processes table\n";
    
    // Add foreign key constraint
    $sql = "ALTER TABLE processes ADD CONSTRAINT processes_category_fk 
            FOREIGN KEY (category_id) REFERENCES process_categories(id) ON DELETE SET NULL";
    $db->query($sql);
    echo "Added foreign key constraint for category_id\n";
    
    // Add index for better performance
    $sql = "ALTER TABLE processes ADD INDEX idx_processes_category (category_id)";
    $db->query($sql);
    echo "Added index for category_id\n";
    
    echo "Successfully updated processes table structure!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
