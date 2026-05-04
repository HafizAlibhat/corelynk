<?php
// Simple test script to check basic functionality
// Place this in public folder and access via browser

// Start session
session_start();

echo "<h2>Pro Sys Debug Test</h2>";
echo "<p>Current time: " . date('Y-m-d H:i:s') . "</p>";

// Check if running from web
if (isset($_SERVER['HTTP_HOST'])) {
    echo "<p>✓ Running from web browser</p>";
    echo "<p>Host: " . $_SERVER['HTTP_HOST'] . "</p>";
    echo "<p>Request URI: " . $_SERVER['REQUEST_URI'] . "</p>";
} else {
    echo "<p>✗ Not running from web</p>";
}

// Test basic PHP functionality
echo "<p>✓ PHP version: " . PHP_VERSION . "</p>";

// Test session
if (isset($_SESSION)) {
    echo "<p>✓ Sessions working</p>";
    if (!empty($_SESSION)) {
        echo "<p>Session data: <pre>" . print_r($_SESSION, true) . "</pre></p>";
    } else {
        echo "<p>Session is empty (not logged in)</p>";
    }
} else {
    echo "<p>✗ Sessions not working</p>";
}

// Test database connection (basic)
try {
    $pdo = new PDO('mysql:host=localhost;dbname=production_management_system;charset=utf8mb4', 'root', '');
    echo "<p>✓ Database connection working</p>";
    
    // Test query
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM work_orders");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>✓ Work orders in database: " . $result['count'] . "</p>";
    
} catch (Exception $e) {
    echo "<p>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test links
echo "<h3>Test Links:</h3>";
echo "<p><a href='/pro_sys/public/index.php/auth/login'>Login Page</a></p>";
echo "<p><a href='/pro_sys/public/index.php/work-orders'>Work Orders List</a></p>";
echo "<p><a href='/pro_sys/public/index.php/work-orders/1'>Work Order 1 Details</a></p>";
echo "<p><a href='/pro_sys/public/test_button.html'>Button Test Page</a></p>";

// JavaScript test
echo "<script>
console.log('Debug script loaded');
function testJS() {
    alert('JavaScript is working!');
    console.log('JS test function called');
}
</script>";

echo "<p><button onclick='testJS()'>Test JavaScript</button></p>";
?>
