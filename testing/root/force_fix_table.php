<?php
require 'vendor/autoload.php';

try {
    $config = new \Config\Database();
    $db = \CodeIgniter\Database\Config::connect();
    
    echo "Attempting to fix product_processes table...\n\n";
    
    // Drop foreign key constraint if it exists
    try {
        $db->query("ALTER TABLE product_processes DROP FOREIGN KEY fk_product_processes_process");
        echo "Dropped existing foreign key constraint\n";
    } catch (Exception $e) {
        echo "No existing foreign key to drop\n";
    }
    
    // Drop column if it exists
    try {
        $db->query("ALTER TABLE product_processes DROP COLUMN process_id");
        echo "Dropped existing process_id column\n";
    } catch (Exception $e) {
        echo "No existing process_id column to drop\n";
    }
    
    // Add the column fresh
    $db->query("ALTER TABLE product_processes ADD COLUMN process_id INT UNSIGNED NULL AFTER product_id");
    echo "✅ Added process_id column\n";
    
    // Add index
    $db->query("ALTER TABLE product_processes ADD INDEX idx_product_processes_process (process_id)");
    echo "✅ Added index\n";
    
    // Add foreign key constraint
    $db->query("ALTER TABLE product_processes ADD CONSTRAINT fk_product_processes_process FOREIGN KEY (process_id) REFERENCES processes(id) ON DELETE CASCADE");
    echo "✅ Added foreign key constraint\n";
    
    echo "\n=== Final Table Structure ===\n";
    $query = $db->query('DESCRIBE product_processes');
    $fields = $query->getResultArray();
    foreach($fields as $field) {
        echo "{$field['Field']} - {$field['Type']} - {$field['Null']} - {$field['Key']}\n";
    }
    
    echo "\n✅ Table structure fixed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
?>
