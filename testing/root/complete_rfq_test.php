<?php
// Complete test of the RFQ creation flow
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== COMPLETE RFQ BUTTON TEST ===\n\n";

// 1. Check Sales Order
$result = $db->query("SELECT * FROM sales_orders WHERE order_number = 'RI-S0001' LIMIT 1");
$order = $result->fetch_assoc();

if (!$order) {
    echo "✗ Sales Order RI-S0001 not found\n";
    exit;
}

$orderId = $order['id'];
echo "✓ Sales Order: RI-S0001 (ID: {$orderId})\n";

// 2. Check Lines
$result = $db->query("SELECT * FROM sales_order_lines WHERE sales_order_id = {$orderId}");
$lines = [];
while ($row = $result->fetch_assoc()) {
    $lines[] = $row;
}

echo "✓ Lines: " . count($lines) . " items\n";

// 3. Check Product Details
foreach ($lines as $line) {
    $productId = $line['product_id'];
    $result = $db->query("SELECT id, name, vendor_id FROM products WHERE id = {$productId}");
    $product = $result->fetch_assoc();
    
    if ($product) {
        echo "\n  Product: " . $product['name'] . " (ID: {$productId})\n";
        echo "  Vendor ID: " . ($product['vendor_id'] ?: 'NULL/NOT SET') . "\n";
        
        if (!$product['vendor_id']) {
            echo "  ⚠️ NO VENDOR ASSIGNED - This product needs a vendor!\n";
        } else {
            // Get vendor name
            $vResult = $db->query("SELECT name FROM vendors WHERE id = " . (int)$product['vendor_id']);
            $vendor = $vResult->fetch_assoc();
            if ($vendor) {
                echo "  ✓ Vendor: " . $vendor['name'] . "\n";
            }
        }
    }
}

// 4. Check Inventory
echo "\n=== Stock Check ===\n";
foreach ($lines as $line) {
    $productId = $line['product_id'];
    $orderedQty = (float)$line['quantity'];
    
    $result = $db->query("SELECT * FROM stock_balances WHERE product_id = {$productId}");
    $stock = $result->fetch_assoc();
    
    if ($stock) {
        $onHand = (float)($stock['on_hand'] ?? 0);
        $available = $onHand;
        $shortage = max(0, $orderedQty - $available);
        
        echo "Product {$productId}:\n";
        echo "  On Hand: {$onHand}, Ordered: {$orderedQty}, Shortage: {$shortage}\n";
        
        if ($shortage > 0) {
            echo "  ✓ SHORTAGE EXISTS\n";
        } else {
            echo "  ✗ NO SHORTAGE\n";
        }
    } else {
        echo "Product {$productId}: No stock record (treat as 0 stock = shortage)\n";
        echo "  ✓ SHORTAGE EXISTS (no inventory)\n";
    }
}

// 5. Check for existing RFQs
echo "\n=== RFQ Status ===\n";
$result = $db->query("SELECT * FROM purchase_rfqs WHERE notes LIKE '%SO#{$orderId}%' OR notes LIKE '%{$order['order_number']}%'");
$rfqs = [];
while ($row = $result->fetch_assoc()) {
    if ($row['status'] !== 'cancelled') {
        $rfqs[] = $row;
    }
}

if (empty($rfqs)) {
    echo "✓ No active RFQs found - Button SHOULD appear\n";
} else {
    echo "✗ Active RFQs exist - Button will be disabled\n";
    foreach ($rfqs as $rfq) {
        echo "  - RFQ {$rfq['rfq_number']} (Status: {$rfq['status']})\n";
    }
}

// 6. Check permissions
echo "\n=== Permission Check ===\n";
$result = $db->query("SELECT role FROM users LIMIT 1");
$user = $result->fetch_assoc();
if ($user) {
    $role = $user['role'];
    echo "User role: {$role}\n";
    
    $adminRoles = ['admin', 'planner'];
    if (in_array($role, $adminRoles)) {
        echo "✓ Permission granted for sales_orders.edit\n";
    } else {
        echo "✗ User role may not have sales_orders.edit permission\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "For the button to work:\n";
echo "1. ✓ hasShortage = true (at least one line has shortage)\n";
echo "2. ✓ hasAutoRfq = false (no active RFQs exist)\n";
echo "3. ✓ Products have vendors assigned\n";
echo "4. ✓ User has sales_orders.edit permission\n";
echo "\nIf all 4 are met, the button should appear and work!\n";

$db->close();
