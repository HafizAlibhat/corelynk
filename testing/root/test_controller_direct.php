<?php
require 'vendor/autoload.php';

// Initialize CodeIgniter properly
$app = \Config\Services::codeigniter();
$app->initialize();

echo "<h2>Direct Controller Test</h2>\n";

try {
    // Simulate being logged in (bypass auth for testing)
    $_SESSION['user_id'] = 1; // Assume user ID 1 exists
    $_SESSION['logged_in'] = true;
    
    // Set up the request data
    $_POST['process_ids'] = '1'; // Single process ID
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
    
    echo "Setting up test environment...<br>\n";
    echo "POST data: " . print_r($_POST, true) . "<br>\n";
    
    // Create Products controller instance
    $productsController = new \App\Controllers\Products();
    
    echo "Controller created successfully<br>\n";
    
    // Call the addProcess method directly
    echo "<h3>Calling addProcess(10) method:</h3>\n";
    
    $response = $productsController->addProcess(10);
    
    if ($response) {
        echo "Response received<br>\n";
        
        // Get the JSON response
        $responseData = $response->getJSON();
        if ($responseData) {
            echo "JSON Response:<br>\n";
            echo "Success: " . ($responseData->success ? 'true' : 'false') . "<br>\n";
            echo "Message: " . $responseData->message . "<br>\n";
        } else {
            echo "Response body: " . $response->getBody() . "<br>\n";
        }
    } else {
        echo "No response received<br>\n";
    }
    
} catch (Exception $e) {
    echo "<h2 style='color: red;'>Exception occurred:</h2>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
    echo "<pre><strong>Stack Trace:</strong>\n" . $e->getTraceAsString() . "</pre>\n";
} catch (Error $e) {
    echo "<h2 style='color: red;'>Fatal Error:</h2>\n";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>\n";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>\n";
}
?>
