<?php
require 'vendor/autoload.php';

// Set up error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $config = new \Config\Database();
    $db = \CodeIgniter\Database\Config::connect();
    
    echo "<h2>Database Connection Test</h2>\n";
    echo "Connected successfully<br>\n";
    
    echo "<h2>Product_processes Table Structure</h2>\n";
    $query = $db->query('DESCRIBE product_processes');
    $fields = $query->getResultArray();
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>\n";
    foreach($fields as $field) {
        echo "<tr>";
        echo "<td>{$field['Field']}</td>";
        echo "<td>{$field['Type']}</td>";
        echo "<td>{$field['Null']}</td>";
        echo "<td>{$field['Key']}</td>";
        echo "<td>{$field['Default']}</td>";
        echo "<td>{$field['Extra']}</td>";
        echo "</tr>\n";
    }
    echo "</table><br>\n";
    
    // Check if process_id column exists
    $hasProcessId = false;
    foreach($fields as $field) {
        if ($field['Field'] === 'process_id') {
            $hasProcessId = true;
            break;
        }
    }
    
    if (!$hasProcessId) {
        echo "<h3 style='color: red;'>❌ process_id column is MISSING!</h3>\n";
        echo "Attempting to add it now...<br>\n";
        
        try {
            $db->query("ALTER TABLE product_processes ADD COLUMN process_id INT UNSIGNED NULL AFTER product_id");
            echo "✅ Added process_id column<br>\n";
            
            $db->query("ALTER TABLE product_processes ADD INDEX idx_product_processes_process (process_id)");
            echo "✅ Added index<br>\n";
            
            // Show updated structure
            echo "<h3>Updated Table Structure:</h3>\n";
            $query = $db->query('DESCRIBE product_processes');
            $fields = $query->getResultArray();
            echo "<table border='1'><tr><th>Field</th><th>Type</th></tr>\n";
            foreach($fields as $field) {
                echo "<tr><td>{$field['Field']}</td><td>{$field['Type']}</td></tr>\n";
            }
            echo "</table><br>\n";
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>Error adding column: " . $e->getMessage() . "</p>\n";
        }
    } else {
        echo "<h3 style='color: green;'>✅ process_id column exists!</h3>\n";
    }
    
    echo "<h2>Processes Table Data</h2>\n";
    $query = $db->query('SELECT p.id, p.name, p.category_id, pc.name as category_name, p.is_active FROM processes p LEFT JOIN process_categories pc ON pc.id = p.category_id ORDER BY p.name LIMIT 10');
    $processes = $query->getResultArray();
    if ($processes) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category ID</th><th>Category Name</th><th>Active</th></tr>\n";
        foreach($processes as $process) {
            echo "<tr>";
            echo "<td>{$process['id']}</td>";
            echo "<td>{$process['name']}</td>";
            echo "<td>" . ($process['category_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($process['category_name'] ?? 'No Category') . "</td>";
            echo "<td>" . ($process['is_active'] ? 'Yes' : 'No') . "</td>";
            echo "</tr>\n";
        }
        echo "</table><br>\n";
    } else {
        echo "No processes found<br>\n";
    }
    
    echo "<h2>Process Categories</h2>\n";
    $query = $db->query('SELECT * FROM process_categories WHERE is_active = 1 ORDER BY name');
    $categories = $query->getResultArray();
    if ($categories) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th></tr>\n";
        foreach($categories as $category) {
            echo "<tr><td>{$category['id']}</td><td>{$category['name']}</td></tr>\n";
        }
        echo "</table><br>\n";
    } else {
        echo "No categories found<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>Code: " . $e->getCode() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}
?>
