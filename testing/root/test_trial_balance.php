<?php
// Test the TrialBalance controller directly
require 'vendor/autoload.php';

// Initialize CodeIgniter
$path = realpath(FCPATH . '../app/Config/Paths.php');
require_once $path;
$paths = new Config\Paths();
require_once SYSTEMPATH . 'bootstrap.php';

$app = Config\Services::codeigniter();
$app->initialize();

// Test TrialBalance controller
echo "Testing TrialBalance controller...\n\n";

try {
    $controller = new \App\Controllers\TrialBalance();
    
    // Capture output
    ob_start();
    $result = $controller->index();
    $output = ob_get_clean();
    
    if ($result && method_exists($result, 'getBody')) {
        echo "Controller executed successfully!\n";
        echo "Response type: " . get_class($result) . "\n";
    } else {
        echo "View rendered successfully!\n";
    }
    
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString();
}
