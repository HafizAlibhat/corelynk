<?php
// Simple test to check what variables are passed to the view
require 'vendor/autoload.php';

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

try {
    echo "<h2>Testing Products Controller processes() method</h2>\n";
    
    // Simulate the controller logic
    $productModel = new \App\Models\ProductModel();
    $product = $productModel->find(10);
    
    if ($product) {
        echo "✅ Product found: " . $product['name'] . "<br>\n";
        
        // Get available processes
        $processModel = new \App\Models\ProcessModel();
        $allProcesses = $processModel->select('processes.*, process_categories.name as category_name')
                                   ->join('process_categories', 'process_categories.id = processes.category_id', 'left')
                                   ->where('processes.is_active', true)
                                   ->orderBy('process_categories.name', 'ASC')
                                   ->orderBy('processes.name', 'ASC')
                                   ->findAll();
        
        echo "✅ All processes retrieved: " . count($allProcesses) . " processes<br>\n";
        
        // Get categories
        $processCategoryModel = new \App\Models\ProcessCategoryModel();
        $categories = $processCategoryModel->getActiveCategoriesArray();
        
        echo "✅ Categories retrieved: " . count($categories) . " categories<br>\n";
        
        // Check what will be passed to view
        echo "<h3>Variables that will be passed to view:</h3>\n";
        echo "- product: " . (isset($product) ? "✅" : "❌") . "<br>\n";
        echo "- all_processes: " . (isset($allProcesses) ? "✅ (" . count($allProcesses) . " items)" : "❌") . "<br>\n";
        echo "- categories: " . (isset($categories) ? "✅ (" . count($categories) . " items)" : "❌") . "<br>\n";
        
        if ($allProcesses) {
            echo "<h3>Sample processes:</h3>\n";
            echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category Name</th></tr>\n";
            foreach(array_slice($allProcesses, 0, 3) as $process) {
                echo "<tr>";
                echo "<td>{$process['id']}</td>";
                echo "<td>{$process['name']}</td>";
                echo "<td>" . ($process['category_name'] ?? 'NULL') . "</td>";
                echo "</tr>\n";
            }
            echo "</table><br>\n";
        }
        
    } else {
        echo "❌ Product not found<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}
?>
