<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

try {
    echo "Current product_processes table structure:\n";
    $query = $db->query('DESCRIBE product_processes');
    $fields = $query->getResultArray();
    foreach($fields as $field) {
        echo $field['Field'] . ' - ' . $field['Type'] . "\n";
    }
    
    // Check if process_id exists
    $hasProcessId = false;
    foreach($fields as $field) {
        if ($field['Field'] === 'process_id') {
            $hasProcessId = true;
            break;
        }
    }
    
    if (!$hasProcessId) {
        echo "\nAdding process_id column...\n";
        
        // Add process_id column
        $db->query("ALTER TABLE product_processes ADD COLUMN process_id INT UNSIGNED NULL AFTER product_id");
        echo "✓ Added process_id column\n";
        
        // Add foreign key constraint
        $db->query("ALTER TABLE product_processes ADD CONSTRAINT fk_product_processes_process FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE");
        echo "✓ Added foreign key constraint\n";
        
        // Add index
        $db->query("ALTER TABLE product_processes ADD INDEX idx_product_processes_process (process_id)");
        echo "✓ Added index\n";
        
        echo "\nUpdated table structure:\n";
        $query = $db->query('DESCRIBE product_processes');
        $fields = $query->getResultArray();
        foreach($fields as $field) {
            echo $field['Field'] . ' - ' . $field['Type'] . "\n";
        }
    } else {
        echo "\nprocess_id column already exists!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "SQL State: " . $e->getCode() . "\n";
}
?>
