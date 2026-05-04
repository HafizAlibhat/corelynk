<?php
// Direct test that bypasses CodeIgniter routing
require 'vendor/autoload.php';

echo "<h2>Bypass Test - Direct Add Process</h2>\n";

try {
    // Initialize database connection
    $config = new \Config\Database();
    $db = \CodeIgniter\Database\Config::connect();
    
    echo "✅ Database connected<br>\n";
    
    // Check what we're working with
    echo "<h3>Available Products:</h3>\n";
    $products = $db->query('SELECT id, name FROM products WHERE is_active = 1 LIMIT 5')->getResultArray();
    if ($products) {
        foreach ($products as $p) {
            echo "- Product {$p['id']}: {$p['name']}<br>\n";
        }
        $productId = $products[0]['id'];
        echo "Using product ID: $productId<br>\n";
    } else {
        echo "❌ No products found<br>\n";
        exit;
    }
    
    echo "<h3>Available Processes:</h3>\n";
    $processes = $db->query('SELECT id, name FROM processes WHERE is_active = 1 LIMIT 5')->getResultArray();
    if ($processes) {
        foreach ($processes as $p) {
            echo "- Process {$p['id']}: {$p['name']}<br>\n";
        }
        $processId = $processes[0]['id'];
        echo "Using process ID: $processId<br>\n";
    } else {
        echo "❌ No processes found<br>\n";
        exit;
    }
    
    // Check current product_processes for this product
    echo "<h3>Current processes for product $productId:</h3>\n";
    $current = $db->query("SELECT pp.*, p.name as process_name FROM product_processes pp LEFT JOIN processes p ON p.id = pp.process_id WHERE pp.product_id = $productId AND pp.is_active = 1")->getResultArray();
    if ($current) {
        foreach ($current as $cp) {
            echo "- Process: " . ($cp['process_name'] ?? 'Unknown') . " (Sequence: {$cp['sequence_order']})<br>\n";
        }
    } else {
        echo "No processes currently assigned<br>\n";
    }
    
    // Test 1: Direct database insert
    echo "<h3>Test 1: Direct Database Insert</h3>\n";
    
    // Check if already exists
    $exists = $db->query("SELECT id FROM product_processes WHERE product_id = $productId AND process_id = $processId AND is_active = 1")->getRowArray();
    
    if ($exists) {
        echo "⚠️ Process $processId already assigned to product $productId<br>\n";
    } else {
        // Get max sequence
        $maxSeq = $db->query("SELECT MAX(sequence_order) as max_seq FROM product_processes WHERE product_id = $productId")->getRowArray()['max_seq'] ?? 0;
        
        $insertData = [
            'product_id' => $productId,
            'process_id' => $processId,
            'sequence_order' => $maxSeq + 1,
            'is_active' => 1
        ];
        
        echo "Attempting to insert: " . print_r($insertData, true) . "<br>\n";
        
        $result = $db->table('product_processes')->insert($insertData);
        
        if ($result) {
            echo "✅ Direct insert successful<br>\n";
            $insertId = $db->insertID();
            echo "Insert ID: $insertId<br>\n";
            
            // Verify it was inserted
            $verify = $db->query("SELECT * FROM product_processes WHERE id = $insertId")->getRowArray();
            if ($verify) {
                echo "✅ Verified in database<br>\n";
                
                // Clean up
                $db->query("DELETE FROM product_processes WHERE id = $insertId");
                echo "🧹 Cleaned up test record<br>\n";
            } else {
                echo "❌ Record not found after insert<br>\n";
            }
        } else {
            echo "❌ Direct insert failed<br>\n";
            $error = $db->error();
            echo "Database error: " . print_r($error, true) . "<br>\n";
        }
    }
    
    // Test 2: Simulate the AJAX request
    echo "<h3>Test 2: Simulate AJAX Request</h3>\n";
    
    $url = "http://localhost/pro_sys/public/products/$productId/processes/add";
    $postData = "process_ids=$processId";
    
    echo "URL: $url<br>\n";
    echo "POST data: $postData<br>\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'X-Requested-With: XMLHttpRequest'
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode<br>\n";
    if ($error) {
        echo "CURL Error: $error<br>\n";
    }
    echo "Response: <pre>" . htmlspecialchars($response) . "</pre><br>\n";
    
    if ($httpCode === 200) {
        $json = json_decode($response, true);
        if ($json) {
            echo "Parsed JSON:<br>\n";
            echo "- Success: " . ($json['success'] ? 'true' : 'false') . "<br>\n";
            echo "- Message: " . ($json['message'] ?? 'No message') . "<br>\n";
        } else {
            echo "Response is not valid JSON<br>\n";
        }
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Error:</h2>\n";
    echo "<p>" . $e->getMessage() . "</p>\n";
    echo "<p>File: " . $e->getFile() . " Line: " . $e->getLine() . "</p>\n";
}
?>
