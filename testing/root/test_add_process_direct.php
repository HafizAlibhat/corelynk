<?php
require 'vendor/autoload.php';

// Simulate what happens when Add Process is called
echo "<h2>Testing Add Process API Call</h2>\n";

// Initialize CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();

try {
    // Simulate the POST request
    $_POST['process_ids'] = '1'; // Assuming process ID 1 exists
    
    echo "Simulating POST request with process_ids = '1'<br>\n";
    
    // Test the controller logic
    $productsController = new \App\Controllers\Products();
    
    // Check if product exists
    $productModel = new \App\Models\ProductModel();
    $product = $productModel->find(10);
    
    if ($product) {
        echo "✅ Product found: " . $product['name'] . "<br>\n";
    } else {
        echo "❌ Product not found<br>\n";
        exit;
    }
    
    // Test the ProductProcessModel directly
    $productProcessModel = new \App\Models\ProductProcessModel();
    
    echo "<h3>Testing addActualProcessesToProduct directly:</h3>\n";
    
    $processIds = ['1']; // Array format
    $result = $productProcessModel->addActualProcessesToProduct(10, $processIds);
    
    if ($result) {
        echo "✅ addActualProcessesToProduct returned true<br>\n";
    } else {
        echo "❌ addActualProcessesToProduct returned false<br>\n";
    }
    
    // Check if the process was actually added
    echo "<h3>Checking if process was added:</h3>\n";
    
    $db = \Config\Database::connect();
    $query = $db->query('SELECT * FROM product_processes WHERE product_id = 10 AND process_id = 1');
    $result = $query->getResultArray();
    
    if ($result) {
        echo "✅ Process found in database:<br>\n";
        print_r($result);
    } else {
        echo "❌ Process not found in database<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>Code: " . $e->getCode() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
    echo "<pre>" . $e->getTraceAsString() . "</pre>\n";
}
?>
