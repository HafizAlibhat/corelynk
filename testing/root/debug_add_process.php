<?php
require 'vendor/autoload.php';

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

try {
    echo "<h2>Testing Add Process Functionality</h2>\n";
    
    // Test the ProductProcessModel
    $productProcessModel = new \App\Models\ProductProcessModel();
    echo "✅ ProductProcessModel loaded<br>\n";
    
    // Check if the addActualProcessesToProduct method exists
    if (method_exists($productProcessModel, 'addActualProcessesToProduct')) {
        echo "✅ addActualProcessesToProduct method exists<br>\n";
        
        // Test with sample data
        $productId = 10; // Product ID from the URL
        $processIds = [1]; // Assuming process ID 1 exists
        
        echo "Testing addActualProcessesToProduct($productId, [1])...<br>\n";
        
        $result = $productProcessModel->addActualProcessesToProduct($productId, $processIds);
        
        if ($result) {
            echo "✅ Method executed successfully<br>\n";
        } else {
            echo "❌ Method returned false<br>\n";
        }
        
    } else {
        echo "❌ addActualProcessesToProduct method does not exist<br>\n";
    }
    
    // Check database table structure
    echo "<h3>Product_processes table check:</h3>\n";
    $db = \Config\Database::connect();
    $query = $db->query('DESCRIBE product_processes');
    $fields = $query->getResultArray();
    
    $hasProcessId = false;
    foreach($fields as $field) {
        if ($field['Field'] === 'process_id') {
            $hasProcessId = true;
            break;
        }
    }
    
    if ($hasProcessId) {
        echo "✅ process_id column exists<br>\n";
    } else {
        echo "❌ process_id column missing<br>\n";
    }
    
    // Check available processes
    echo "<h3>Available processes:</h3>\n";
    $query = $db->query('SELECT id, name, category_id, is_active FROM processes WHERE is_active = 1 LIMIT 5');
    $processes = $query->getResultArray();
    
    if ($processes) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category ID</th></tr>\n";
        foreach($processes as $process) {
            echo "<tr><td>{$process['id']}</td><td>{$process['name']}</td><td>" . ($process['category_id'] ?? 'NULL') . "</td></tr>\n";
        }
        echo "</table><br>\n";
    } else {
        echo "❌ No active processes found<br>\n";
    }
    
    // Check existing product processes
    echo "<h3>Existing product processes for product 10:</h3>\n";
    $query = $db->query('SELECT * FROM product_processes WHERE product_id = 10');
    $existing = $query->getResultArray();
    
    if ($existing) {
        echo "<table border='1'><tr><th>ID</th><th>Product ID</th><th>Process Template ID</th><th>Process ID</th><th>Sequence</th></tr>\n";
        foreach($existing as $proc) {
            echo "<tr>";
            echo "<td>{$proc['id']}</td>";
            echo "<td>{$proc['product_id']}</td>";
            echo "<td>" . ($proc['process_template_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($proc['process_id'] ?? 'NULL') . "</td>";
            echo "<td>{$proc['sequence_order']}</td>";
            echo "</tr>\n";
        }
        echo "</table><br>\n";
    } else {
        echo "No existing product processes<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
