<?php
// Ultra-simple test - NO JavaScript, pure PHP
// Access: http://localhost/pro_sys/public/simple_test.php

// Start session to check if logged in
session_start();

echo "<h2>Simple Test - View Processes & Batches</h2>";
echo "<p>Testing work order item 1, product 10</p>";

// Test database connection
try {
    $pdo = new PDO('mysql:host=localhost;dbname=production_management_system;charset=utf8mb4', 'root', '');
    echo "<p>✓ Database connected</p>";
    
    // Get product processes directly
    $stmt = $pdo->prepare("
        SELECT pp.*, p.name as process_name, p.description as process_description
        FROM product_processes pp 
        LEFT JOIN processes p ON pp.process_id = p.id 
        WHERE pp.product_id = ? AND pp.is_active = 1
        ORDER BY pp.sequence_order
    ");
    $stmt->execute([10]);
    $processes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Processes for Product 10:</h3>";
    if ($processes) {
        echo "<ul>";
        foreach ($processes as $process) {
            echo "<li><strong>" . ($process['process_name'] ?: 'Process ' . $process['process_id']) . "</strong><br>";
            echo "<small>" . ($process['process_description'] ?: 'No description') . "</small></li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No processes found for product 10</p>";
    }
    
    // Get existing batches
    $stmt = $pdo->prepare("
        SELECT * FROM process_batches 
        WHERE work_order_item_id = 1 
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Existing Batches for Item 1:</h3>";
    if ($batches) {
        echo "<ul>";
        foreach ($batches as $batch) {
            echo "<li>" . $batch['batch_code'] . " (Planned: " . $batch['planned_qty'] . ", Status: " . $batch['status'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>No batches found for item 1</p>";
    }
    
} catch (Exception $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Links to Test:</h3>";
echo "<p><a href='/pro_sys/public/index.php/auth/login'>Login Page</a></p>";
echo "<p><a href='/pro_sys/public/index.php/work-orders/1'>Work Order 1 (after login)</a></p>";
echo "<p><a href='/pro_sys/public/working_test.html'>JavaScript Test Page</a></p>";

// Test the actual AJAX endpoint directly
echo "<h3>Testing AJAX Endpoint:</h3>";
$url = 'http://localhost/pro_sys/public/index.php/work-orders/1/items/1/processes?product_id=10';
echo "<p>Endpoint URL: <code>$url</code></p>";

// Try to call it with cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['X-Requested-With: XMLHttpRequest']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status: <strong>$httpCode</strong></p>";
if ($httpCode == 401) {
    echo "<p>❌ <strong>401 Unauthorized</strong> - Need to log in first!</p>";
    echo "<p>Solution: Log in at <a href='/pro_sys/public/index.php/auth/login'>Login Page</a> then try the work order page</p>";
} elseif ($httpCode == 200) {
    echo "<p>✓ Endpoint working! Response:</p>";
    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
    echo htmlspecialchars(substr($response, 0, 1000));
    echo "</pre>";
} else {
    echo "<p>❌ HTTP Error $httpCode</p>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
}
?>
