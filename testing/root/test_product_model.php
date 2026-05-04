<?php
require 'vendor/autoload.php';

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

try {
    echo "<h2>Testing ProductProcessModel</h2>\n";
    
    $productProcessModel = new \App\Models\ProductProcessModel();
    
    echo "Model loaded successfully<br>\n";
    
    // Test the method that's causing the error
    echo "<h3>Testing getProductProcessesWithDetails(10)</h3>\n";
    
    $processes = $productProcessModel->getProductProcessesWithDetails(10);
    
    echo "Method executed successfully<br>\n";
    echo "Number of processes found: " . count($processes) . "<br>\n";
    
    if ($processes) {
        echo "<table border='1'><tr><th>ID</th><th>Product ID</th><th>Process Template ID</th><th>Process ID</th><th>Sequence</th><th>Template Name</th></tr>\n";
        foreach($processes as $process) {
            echo "<tr>";
            echo "<td>" . ($process['id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($process['product_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($process['process_template_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($process['process_id'] ?? 'NULL') . "</td>";
            echo "<td>" . ($process['sequence_order'] ?? 'NULL') . "</td>";
            echo "<td>" . ($process['template_name'] ?? 'No Name') . "</td>";
            echo "</tr>\n";
        }
        echo "</table><br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error in ProductProcessModel:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>Code: " . $e->getCode() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}

try {
    echo "<h2>Testing Products Controller Logic</h2>\n";
    
    // Test the controller logic
    $productModel = new \App\Models\ProductModel();
    $product = $productModel->find(10);
    
    if ($product) {
        echo "Product found: " . $product['name'] . "<br>\n";
        
        // Test getting processes with category information
        $processModel = new \App\Models\ProcessModel();
        $allProcesses = $processModel->select('processes.*, process_categories.name as category_name')
                                   ->join('process_categories', 'process_categories.id = processes.category_id', 'left')
                                   ->where('processes.is_active', true)
                                   ->orderBy('process_categories.name', 'ASC')
                                   ->orderBy('processes.name', 'ASC')
                                   ->findAll();
        
        echo "Available processes found: " . count($allProcesses) . "<br>\n";
        
        if ($allProcesses) {
            echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category</th><th>Time</th></tr>\n";
            foreach(array_slice($allProcesses, 0, 5) as $process) {
                echo "<tr>";
                echo "<td>{$process['id']}</td>";
                echo "<td>{$process['name']}</td>";
                echo "<td>" . ($process['category_name'] ?? 'No Category') . "</td>";
                echo "<td>{$process['standard_time_minutes']} min</td>";
                echo "</tr>\n";
            }
            echo "</table><br>\n";
        }
        
    } else {
        echo "Product not found<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error in Controller Logic:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>Code: " . $e->getCode() . "</p>\n";
}
?>
