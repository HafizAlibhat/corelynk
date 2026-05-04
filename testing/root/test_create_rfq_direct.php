<?php
// Test the createPurchaseDrafts method directly
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== Testing createPurchaseDrafts Logic ===\n\n";

// Get the sales order
$result = $db->query("SELECT * FROM sales_orders WHERE order_number = 'RI-S0001' LIMIT 1");
$order = $result->fetch_assoc();

if (!$order) {
    echo "Order not found\n";
    exit;
}

$orderId = $order['id'];
echo "Order ID: {$orderId}\n";
echo "Order Number: {$order['order_number']}\n\n";

// Check if the route exists
echo "=== Route Check ===\n";
$expectedUrl = site_url('sales-orders/create-purchase-drafts/' . $orderId);
echo "Expected URL: /sales-orders/create-purchase-drafts/{$orderId}\n";
echo "Full URL would be: /sales-orders/create-purchase-drafts/{$orderId}\n\n";

// Check AutoPurchaseSuggestionService
echo "=== AutoPurchaseSuggestionService Check ===\n";

require_once __DIR__ . '/vendor/autoload.php';

try {
    // Try to load the service
    $service = new \App\Services\AutoPurchaseSuggestionService();
    echo "✓ AutoPurchaseSuggestionService loaded successfully\n";
    
    // Try calling the method
    echo "\nAttempting to create RFQs...\n";
    $result = $service->createDraftRFQsFromSalesOrder($orderId, 1);
    
    echo "\nResult:\n";
    echo "Success: " . ($result['success'] ? 'YES' : 'NO') . "\n";
    echo "Message: " . ($result['message'] ?? 'N/A') . "\n";
    echo "Created POs: " . count($result['created_pos'] ?? []) . "\n";
    
    if (!empty($result['created_pos'])) {
        foreach ($result['created_pos'] as $po) {
            echo "  - " . ($po['rfq_number'] ?? $po['id']) . "\n";
        }
    }
    
} catch (\Throwable $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}

$db->close();
echo "\nDone!\n";

// Helper function (minimal version)
function site_url($path = '') {
    return 'http://localhost/corelynk/' . ltrim($path, '/');
}
