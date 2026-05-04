<?php
$pdo = new PDO('mysql:host=localhost;dbname=production_management_system', 'root', '');

echo "Checking current foreign key constraints...\n";
$result = $pdo->query("
    SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME 
    FROM information_schema.KEY_COLUMN_USAGE 
    WHERE TABLE_NAME = 'product_processes' 
    AND TABLE_SCHEMA = 'production_management_system' 
    AND REFERENCED_TABLE_NAME IS NOT NULL
");
while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
    echo "Constraint: {$row['CONSTRAINT_NAME']}, Column: {$row['COLUMN_NAME']}, References: {$row['REFERENCED_TABLE_NAME']}.{$row['REFERENCED_COLUMN_NAME']}\n";
}

echo "\nModifying process_template_id column to allow NULL and dropping foreign key constraint...\n";
try {
    // Drop the foreign key constraint
    $pdo->exec('ALTER TABLE product_processes DROP FOREIGN KEY product_processes_ibfk_2');
    echo "Foreign key constraint dropped.\n";
    
    // Modify column to allow NULL
    $pdo->exec('ALTER TABLE product_processes MODIFY COLUMN process_template_id INT(10) UNSIGNED NULL');
    echo "Column modified to allow NULL.\n";
    
    // Add it back but allowing NULL
    $pdo->exec('ALTER TABLE product_processes ADD CONSTRAINT product_processes_ibfk_2 FOREIGN KEY (process_template_id) REFERENCES process_templates(id) ON DELETE CASCADE');
    echo "Foreign key constraint re-added with NULL support.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\nChecking final structure...\n";
$result = $pdo->query('DESCRIBE product_processes');
while ($row = $result->fetch()) {
    if ($row['Field'] == 'process_template_id') {
        echo "process_template_id: {$row['Type']}, Null: {$row['Null']}, Default: {$row['Default']}\n";
    }
}
?>
