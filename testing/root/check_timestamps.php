<?php
require 'vendor/autoload.php';

$config = new \Config\Database();
$db = \CodeIgniter\Database\Config::connect();

echo "<h2>Checking product_processes table for timestamp columns</h2>\n";

$query = $db->query('DESCRIBE product_processes');
$fields = $query->getResultArray();

$hasCreatedAt = false;
$hasUpdatedAt = false;

echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Default</th></tr>\n";
foreach($fields as $field) {
    echo "<tr><td>{$field['Field']}</td><td>{$field['Type']}</td><td>" . ($field['Default'] ?? 'NULL') . "</td></tr>\n";
    
    if ($field['Field'] === 'created_at') $hasCreatedAt = true;
    if ($field['Field'] === 'updated_at') $hasUpdatedAt = true;
}
echo "</table><br>\n";

if (!$hasCreatedAt || !$hasUpdatedAt) {
    echo "<h3>Adding missing timestamp columns:</h3>\n";
    
    if (!$hasCreatedAt) {
        try {
            $db->query("ALTER TABLE product_processes ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
            echo "✅ Added created_at column<br>\n";
        } catch (Exception $e) {
            echo "❌ Error adding created_at: " . $e->getMessage() . "<br>\n";
        }
    }
    
    if (!$hasUpdatedAt) {
        try {
            $db->query("ALTER TABLE product_processes ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            echo "✅ Added updated_at column<br>\n";
        } catch (Exception $e) {
            echo "❌ Error adding updated_at: " . $e->getMessage() . "<br>\n";
        }
    }
} else {
    echo "✅ Both timestamp columns exist<br>\n";
}
?>
