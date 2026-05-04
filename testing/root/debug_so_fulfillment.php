<?php
require_once __DIR__ . '/vendor/autoload.php';

// Bootstrap CodeIgniter
$app = require_once FCPATH . '../app/Config/Paths.php';
$app = new \CodeIgniter\CodeIgniter($app);
$app->initialize();

$db = \Config\Database::connect();

echo "=== Debugging RI-S0002 and RI-S0001 Fulfillment ===\n\n";

$soNumbers = ['RI-S0002', 'RI-S0001'];

foreach ($soNumbers as $soNum) {
    echo "--- SO: $soNum ---\n";
    
    $so = $db->table('sales_orders')->where('order_number', $soNum)->get()->getRowArray();
    
    if (!$so) {
        echo "Sales Order not found!\n\n";
        continue;
    }
    
    $soId = $so['id'];
    echo "SO ID: $soId\n";
    
    // Get SO lines with variant info
    $lines = $db->table('sales_order_lines sol')
        ->select('sol.id, sol.product_id, sol.product_variant_id, sol.quantity, p.name as product_name, p.product_type')
        ->join('products p', 'p.id = sol.product_id', 'left')
        ->where('sol.sales_order_id', $soId)
        ->get()
        ->getResultArray();
    
    echo "Lines: " . count($lines) . "\n\n";
    
    foreach ($lines as $line) {
        echo "  Line ID: {$line['id']}\n";
        echo "  Product: {$line['product_name']} (ID: {$line['product_id']})\n";
        echo "  Product Type: {$line['product_type']}\n";
        echo "  Product Variant ID: " . ($line['product_variant_id'] ?? 'NULL') . "\n";
        echo "  Ordered Qty: {$line['quantity']}\n";
        
        // Check PO mappings
        $mappings = $db->table('sales_order_line_po_map')
            ->where('sales_order_line_id', $line['id'])
            ->get()
            ->getResultArray();
        
        echo "  PO Mappings: " . count($mappings) . "\n";
        
        if (count($mappings) > 0) {
            $poLineIds = array_column($mappings, 'purchase_order_line_id');
            $received = $db->table('purchase_grn_lines')
                ->selectSum('qty_received')
                ->whereIn('po_line_id', $poLineIds)
                ->get()
                ->getRowArray();
            $receivedQty = (float)($received['qty_received'] ?? 0);
            echo "  Received from GRN: $receivedQty\n";
        } else {
            echo "  No PO mappings - checking stock balance...\n";
            
            // Check stock with variant support
            $variantId = (int)($line['product_variant_id'] ?? 0);
            
            if ($variantId > 0) {
                echo "  Checking variant stock (variant_id: $variantId)...\n";
                $stock = $db->table('stock_balances')
                    ->selectSum('quantity')
                    ->where('product_id', $line['product_id'])
                    ->where('variant_id', $variantId)
                    ->get()
                    ->getRowArray();
                $stockQty = (float)($stock['quantity'] ?? 0);
                echo "  Variant Stock: $stockQty\n";
                
                // Also show all stock for this product
                $allStock = $db->table('stock_balances')
                    ->select('variant_id, quantity, warehouse_id, location_id')
                    ->where('product_id', $line['product_id'])
                    ->get()
                    ->getResultArray();
                echo "  All stock for product {$line['product_id']}:\n";
                foreach ($allStock as $s) {
                    echo "    - Variant ID: " . ($s['variant_id'] ?? 'NULL') . ", Qty: {$s['quantity']}, WH: {$s['warehouse_id']}, Loc: {$s['location_id']}\n";
                }
            } else {
                echo "  Checking simple product stock...\n";
                $stock = $db->table('stock_balances')
                    ->selectSum('quantity')
                    ->where('product_id', $line['product_id'])
                    ->where('variant_id IS NULL')
                    ->get()
                    ->getRowArray();
                $stockQty = (float)($stock['quantity'] ?? 0);
                echo "  Simple Product Stock: $stockQty\n";
            }
        }
        echo "\n";
    }
    
    // Now test the actual service
    echo "  === Testing FulfillmentStatusService ===\n";
    $fulfillmentService = new \App\Services\FulfillmentStatusService();
    $result = $fulfillmentService->getSalesOrderFulfillment($soId);
    echo "  Order Status: {$result['orderStatus']}\n";
    echo "  Lines in result: " . count($result['lines']) . "\n";
    foreach ($result['lines'] as $rl) {
        echo "    - Product: {$rl['product_name']}, Ordered: {$rl['ordered_qty']}, Received: {$rl['received_qty']}, Ready: {$rl['ready_to_ship_qty']}\n";
    }
    echo "\n\n";
}
?>
