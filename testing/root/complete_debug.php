<?php
require 'vendor/autoload.php';

echo "<h2>Complete Database and Process Test</h2>\n";

try {
    $config = new \Config\Database();
    $db = \CodeIgniter\Database\Config::connect();
    
    echo "✅ Database connected successfully<br>\n";
    
    // 1. Check if product_processes table exists and has correct structure
    echo "<h3>1. Checking product_processes table structure:</h3>\n";
    $query = $db->query('DESCRIBE product_processes');
    $fields = $query->getResultArray();
    
    $hasProcessId = false;
    $hasProductId = false;
    $hasSequenceOrder = false;
    
    echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Key</th></tr>\n";
    foreach($fields as $field) {
        echo "<tr><td>{$field['Field']}</td><td>{$field['Type']}</td><td>{$field['Key']}</td></tr>\n";
        
        if ($field['Field'] === 'process_id') $hasProcessId = true;
        if ($field['Field'] === 'product_id') $hasProductId = true;
        if ($field['Field'] === 'sequence_order') $hasSequenceOrder = true;
    }
    echo "</table><br>\n";
    
    echo "Required fields check:<br>\n";
    echo "- product_id: " . ($hasProductId ? "✅" : "❌") . "<br>\n";
    echo "- process_id: " . ($hasProcessId ? "✅" : "❌") . "<br>\n";
    echo "- sequence_order: " . ($hasSequenceOrder ? "✅" : "❌") . "<br>\n";
    
    // 2. Check if product 10 exists
    echo "<h3>2. Checking if product 10 exists:</h3>\n";
    $query = $db->query('SELECT * FROM products WHERE id = 10');
    $product = $query->getRowArray();
    
    if ($product) {
        echo "✅ Product 10 exists: " . $product['name'] . "<br>\n";
    } else {
        echo "❌ Product 10 does not exist<br>\n";
        
        // Find any product
        $query = $db->query('SELECT id, name FROM products WHERE is_active = 1 LIMIT 1');
        $anyProduct = $query->getRowArray();
        if ($anyProduct) {
            echo "Alternative product found: ID " . $anyProduct['id'] . " - " . $anyProduct['name'] . "<br>\n";
        }
    }
    
    // 3. Check available processes
    echo "<h3>3. Checking available processes:</h3>\n";
    $query = $db->query('SELECT p.id, p.name, p.is_active, pc.name as category_name FROM processes p LEFT JOIN process_categories pc ON pc.id = p.category_id WHERE p.is_active = 1 LIMIT 5');
    $processes = $query->getResultArray();
    
    if ($processes) {
        echo "<table border='1'><tr><th>ID</th><th>Name</th><th>Category</th></tr>\n";
        foreach($processes as $process) {
            echo "<tr><td>{$process['id']}</td><td>{$process['name']}</td><td>" . ($process['category_name'] ?? 'No Category') . "</td></tr>\n";
        }
        echo "</table><br>\n";
    } else {
        echo "❌ No active processes found<br>\n";
    }
    
    // 4. Test direct database insert
    echo "<h3>4. Testing direct database insert:</h3>\n";
    
    if ($product && $processes) {
        $testData = [
            'product_id' => $product['id'],
            'process_id' => $processes[0]['id'],
            'sequence_order' => 1,
            'is_active' => 1
        ];
        
        // Check if this combination already exists
        $existing = $db->table('product_processes')
                      ->where('product_id', $testData['product_id'])
                      ->where('process_id', $testData['process_id'])
                      ->where('is_active', 1)
                      ->get()
                      ->getRowArray();
        
        if ($existing) {
            echo "⚠️ This process is already assigned to this product<br>\n";
        } else {
            try {
                $result = $db->table('product_processes')->insert($testData);
                
                if ($result) {
                    echo "✅ Direct database insert successful<br>\n";
                    
                    // Clean up - remove the test record
                    $db->table('product_processes')
                      ->where('product_id', $testData['product_id'])
                      ->where('process_id', $testData['process_id'])
                      ->delete();
                    echo "🧹 Test record cleaned up<br>\n";
                } else {
                    echo "❌ Direct database insert failed<br>\n";
                }
                
            } catch (Exception $e) {
                echo "❌ Database insert error: " . $e->getMessage() . "<br>\n";
            }
        }
    }
    
    // 5. Test the ProductProcessModel
    echo "<h3>5. Testing ProductProcessModel:</h3>\n";
    
    $app = \Config\Services::codeigniter();
    $app->initialize();
    
    $productProcessModel = new \App\Models\ProductProcessModel();
    echo "✅ ProductProcessModel loaded<br>\n";
    
    if ($product && $processes) {
        $testProcessIds = [$processes[0]['id']];
        
        echo "Testing addActualProcessesToProduct with product ID {$product['id']} and process ID {$processes[0]['id']}<br>\n";
        
        $result = $productProcessModel->addActualProcessesToProduct($product['id'], $testProcessIds);
        
        if ($result) {
            echo "✅ addActualProcessesToProduct returned true<br>\n";
            
            // Check if it was actually inserted
            $inserted = $db->table('product_processes')
                          ->where('product_id', $product['id'])
                          ->where('process_id', $processes[0]['id'])
                          ->where('is_active', 1)
                          ->get()
                          ->getRowArray();
            
            if ($inserted) {
                echo "✅ Record was actually inserted into database<br>\n";
                
                // Clean up
                $db->table('product_processes')->delete($inserted['id']);
                echo "🧹 Test record cleaned up<br>\n";
            } else {
                echo "❌ Record was not found in database despite method returning true<br>\n";
            }
            
        } else {
            echo "❌ addActualProcessesToProduct returned false<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}
?>
