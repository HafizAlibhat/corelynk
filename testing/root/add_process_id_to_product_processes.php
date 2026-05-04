<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

try {
    // Check if process_id column exists
    $query = $db->query('DESCRIBE product_processes');
    $fields = $query->getResultArray();
    $hasProcessId = false;
    foreach($fields as $field) {
        if ($field['Field'] === 'process_id') {
            $hasProcessId = true;
            break;
        }
    }
    
    if (!$hasProcessId) {
        // Add process_id field to product_processes table
        $sql = "ALTER TABLE product_processes ADD COLUMN process_id INT UNSIGNED NULL AFTER product_id";
        $db->query($sql);
        echo "Added process_id column to product_processes table\n";
        
        // Add foreign key constraint
        $sql = "ALTER TABLE product_processes ADD CONSTRAINT product_processes_process_fk 
                FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE";
        $db->query($sql);
        echo "Added foreign key constraint for process_id\n";
        
        // Add index for better performance
        $sql = "ALTER TABLE product_processes ADD INDEX idx_product_processes_process (process_id)";
        $db->query($sql);
        echo "Added index for process_id\n";
    } else {
        echo "process_id column already exists\n";
    }
    
    echo "Successfully updated product_processes table structure!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
