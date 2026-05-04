<?php
// Test script to verify Sales Order RFQ button logic

$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "=== Testing Sales Order RFQ Button Logic ===\n\n";

// Get the sales order from the image (RI-S0001)
$result = $db->query("SELECT * FROM sales_orders WHERE order_number = 'RI-S0001' LIMIT 1");
$order = $result->fetch_assoc();

if (!$order) {
    echo "Sales Order RI-S0001 not found!\n";
    exit;
}

$orderId = $order['id'];
echo "Found Sales Order: #{$orderId} ({$order['order_number']})\n";
echo "Customer ID: {$order['customer_id']}\n";
echo "Total: {$order['total']}\n\n";

// Get sales order lines
echo "=== Sales Order Lines ===\n";
$result = $db->query("SELECT * FROM sales_order_lines WHERE sales_order_id = {$orderId}");
$lines = [];
while ($row = $result->fetch_assoc()) {
    $lines[] = $row;
    echo "Line #{$row['id']}: Product #{$row['product_id']}";
    if ($row['product_variant_id']) {
        echo " (Variant #{$row['product_variant_id']})";
    }
    echo " - Qty: {$row['quantity']}\n";
}

if (empty($lines)) {
    echo "No lines found for this order!\n";
    exit;
}

// Check if products have vendors assigned
echo "\n=== Product Vendor Check ===\n";
foreach ($lines as $line) {
    $productId = $line['product_id'];
    $result = $db->query("SELECT vendor_id FROM products WHERE id = {$productId}");
    $product = $result->fetch_assoc();
    
    if ($product) {
        $vendorId = $product['vendor_id'] ?? null;
        echo "Product #{$productId}: Vendor = " . ($vendorId ? "#{$vendorId}" : "NOT ASSIGNED") . "\n";
        
        if (!$vendorId) {
            echo "  ⚠️ WARNING: Product has no vendor assigned! RFQ cannot be created.\n";
        } else {
            // Get vendor name
            $vResult = $db->query("SELECT name FROM vendors WHERE id = {$vendorId}");
            $vendor = $vResult->fetch_assoc();
            if ($vendor) {
                echo "  ✓ Vendor: {$vendor['name']}\n";
            }
        }
    }
}

// Check for existing RFQs
echo "\n=== Checking for Existing RFQs ===\n";
$soNumber = $order['order_number'];
$result = $db->query("SELECT * FROM purchase_rfqs WHERE notes LIKE '%SO#{$orderId}%' OR notes LIKE '%{$soNumber}%'");
$rfqs = [];
while ($row = $result->fetch_assoc()) {
    $rfqs[] = $row;
}

if (empty($rfqs)) {
    echo "No RFQs found for this sales order.\n";
    echo "✓ RFQ button SHOULD be visible (hasAutoRfq = false)\n";
} else {
    echo "Found " . count($rfqs) . " existing RFQ(s):\n";
    foreach ($rfqs as $rfq) {
        echo "  - RFQ #{$rfq['id']} ({$rfq['rfq_number']}) - Status: {$rfq['status']}\n";
    }
    $activeRfq = false;
    foreach ($rfqs as $rfq) {
        if ($rfq['status'] !== 'cancelled') {
            $activeRfq = true;
            break;
        }
    }
    if ($activeRfq) {
        echo "✓ Active RFQ exists - Button should show 'RFQ Drafts Created' (hasAutoRfq = true)\n";
    } else {
        echo "✓ Only cancelled RFQs - Button SHOULD be visible (hasAutoRfq = false)\n";
    }
}

// Check inventory availability
echo "\n=== Inventory Availability Check ===\n";
foreach ($lines as $line) {
    $productId = $line['product_id'];
    $variantId = $line['product_variant_id'] ?? null;
    $orderedQty = (float)$line['quantity'];
    
    echo "Product #{$productId}";
    if ($variantId) {
        echo " (Variant #{$variantId})";
    }
    echo ":\n";
    
    if ($variantId) {
        // Check variant inventory
        $result = $db->query("SELECT * FROM variant_inventory WHERE variant_id = {$variantId}");
        $inv = $result->fetch_assoc();
    } else {
        // Check product inventory
        $result = $db->query("SELECT * FROM stock_balances WHERE product_id = {$productId}");
        $inv = $result->fetch_assoc();
    }
    
    if ($inv) {
        $onHand = (float)($inv['on_hand'] ?? $inv['quantity_on_hand'] ?? 0);
        $reserved = (float)($inv['reserved'] ?? $inv['quantity_reserved'] ?? 0);
        $available = max(0, $onHand - $reserved);
        $shortage = max(0, $orderedQty - $available);
        
        echo "  On Hand: {$onHand}\n";
        echo "  Reserved: {$reserved}\n";
        echo "  Available: {$available}\n";
        echo "  Ordered: {$orderedQty}\n";
        echo "  Shortage: {$shortage}\n";
        
        if ($shortage > 0) {
            echo "  ⚠️ SHORTAGE EXISTS - RFQ button should appear!\n";
        } else {
            echo "  ✓ No shortage\n";
        }
    } else {
        echo "  ⚠️ No inventory record found - Treated as shortage!\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "For RFQ button to appear, ALL of the following must be true:\n";
echo "1. At least ONE line item has stock shortage (hasShortage = true)\n";
echo "2. No active RFQs already created (hasAutoRfq = false)\n";
echo "3. Products must have vendors assigned\n";

$db->close();
echo "\nDone!\n";
