<?php
// Simple test to check the ProductProcessModel
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include CodeIgniter bootstrap
define('ROOTPATH', __DIR__ . DIRECTORY_SEPARATOR);
define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR);
define('WRITEPATH', __DIR__ . DIRECTORY_SEPARATOR . 'writable' . DIRECTORY_SEPARATOR);
define('APPPATH', __DIR__ . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR);
define('CI_DEBUG', 1);

require_once ROOTPATH . 'vendor/autoload.php';

// Bootstrap CodeIgniter
$app = \Config\Services::codeigniter();
$app->initialize();
$app->setContext('web');

try {
    // Test the ProductProcessModel
    $productProcessModel = new \App\Models\ProductProcessModel();
    
    echo "Testing getProductProcessesWithDetails for product ID 10:\n";
    $processes = $productProcessModel->getProductProcessesWithDetails(10);
    
    if (empty($processes)) {
        echo "No processes found for product ID 10\n";
    } else {
        foreach ($processes as $process) {
            echo "Process ID: " . ($process['id'] ?? 'N/A') . "\n";
            echo "  Template Name: " . ($process['template_name'] ?? 'N/A') . "\n";
            echo "  Category: " . ($process['category'] ?? 'N/A') . "\n";
            echo "  Standard Time: " . ($process['standard_time_minutes'] ?? 'N/A') . "\n";
            echo "  Custom Time: " . ($process['custom_time_minutes'] ?? 'N/A') . "\n";
            echo "  Is Vendor Process: " . ($process['is_vendor_process'] ?? 'N/A') . "\n";
            echo "  Vendor Name: " . ($process['vendor_name'] ?? 'N/A') . "\n";
            echo "  ----\n";
        }
    }
    
    echo "\nTesting getProductTotalTime for product ID 10:\n";
    $totalTime = $productProcessModel->getProductTotalTime(10);
    echo "Total Time: " . $totalTime . " minutes\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
