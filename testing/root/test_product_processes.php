<?php
require_once 'vendor/autoload.php';

// Initialize CodeIgniter
$app = Config\Services::codeigniter();
$app->initialize();

// Load the model
$productProcessModel = new \App\Models\ProductProcessModel();

// Test getting product processes with details
echo "Testing getProductProcessesWithDetails for product ID 1:\n";
$processes = $productProcessModel->getProductProcessesWithDetails(1);
foreach ($processes as $process) {
    echo "- Process Template ID: " . $process['process_template_id'] . "\n";
    echo "  Template Name: " . ($process['template_name'] ?? 'N/A') . "\n";
    echo "  Standard Time: " . ($process['standard_time_minutes'] ?? 'N/A') . "\n";
    echo "  Custom Time: " . ($process['custom_time_minutes'] ?? 'N/A') . "\n";
    echo "  ----\n";
}

// Test getting total time
echo "\nTesting getProductTotalTime for product ID 1:\n";
$totalTime = $productProcessModel->getProductTotalTime(1);
echo "Total Time: " . $totalTime . " minutes\n";
