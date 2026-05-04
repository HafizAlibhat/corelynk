<?php
// Test script to verify both pages are working
echo "Testing workflow templates and processes pages...\n";

// Test 1: Check if we can access workflow templates URL
$context = stream_context_create([
    'http' => [
        'timeout' => 10
    ]
]);

echo "\n1. Testing workflow templates page...\n";
$response = @file_get_contents('http://localhost:8080/workflow-templates', false, $context);
if ($response !== false) {
    if (strpos($response, 'TypeError') === false && strpos($response, 'Error') === false) {
        echo "✓ Workflow templates page loads successfully\n";
    } else {
        echo "✗ Workflow templates page has errors\n";
    }
} else {
    echo "✗ Could not access workflow templates page\n";
}

echo "\n2. Testing processes create page...\n";
$response = @file_get_contents('http://localhost:8080/processes/create', false, $context);
if ($response !== false) {
    if (strpos($response, 'TypeError') === false && strpos($response, 'Error') === false) {
        echo "✓ Processes create page loads successfully\n";
    } else {
        echo "✗ Processes create page has errors\n";
    }
} else {
    echo "✗ Could not access processes create page\n";
}

echo "\nTest completed!\n";
?>
