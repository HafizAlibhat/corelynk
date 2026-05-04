<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli('localhost', 'root', '', 'corelynk');
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
    die('Connection error: ' . $mysqli->connect_error);
}

echo "=== Checking RI-50002 and RI-50001 ===\n\n";

// Get SO IDs
$result = $mysqli->query('SELECT id, order_number FROM sales_orders WHERE order_number IN ("RI-50002", "RI-50001") ORDER BY order_number');

while ($so = $result->fetch_assoc()) {
    echo "--- SO: {$so['order_number']} (ID: {$so['id']}) ---\n";
    
    // Get SO lines
    $lines = $mysqli->query('
        SELECT sol.id, sol.product_id, sol.quantity, p.name, p.product_type
        FROM sales_order_lines sol
        LEFT JOIN products p ON p.id = sol.product_id
        WHERE sol.sales_order_id = ' . $so['id']);
    
    echo "Lines found: " . $lines->num_rows . "\n";
    
    while ($line = $lines->fetch_assoc()) {
        echo "\n  Product: {$line['name']} (ID: {$line['product_id']}, Type: {$line['product_type']}, Qty: {$line['quantity']})\n";
        
        // Check PO mappings
        $mappings = $mysqli->query('SELECT purchase_order_line_id FROM sales_order_line_po_map WHERE sales_order_line_id = ' . $line['id']);
        $mapCount = $mappings->num_rows;
        echo "  PO Mappings: $mapCount\n";
        
        if ($mapCount > 0) {
            // Get PO Line IDs
            $poLineIds = [];
            while ($map = $mappings->fetch_assoc()) {
                $poLineIds[] = $map['purchase_order_line_id'];
            }
            
            // Get received from GRN
            $receivedResult = $mysqli->query('SELECT SUM(qty_received) as qty FROM purchase_grn_lines WHERE po_line_id IN (' . implode(',', $poLineIds) . ')');
            $recv = $receivedResult->fetch_assoc();
            $receivedQty = (float)($recv['qty'] ?? 0);
            echo "  Received from GRN: $receivedQty\n";
        } else {
            // Check stock balance
            $stockResult = $mysqli->query('SELECT SUM(quantity) as qty FROM stock_balances WHERE product_id = ' . $line['product_id']);
            $stockRow = $stockResult->fetch_assoc();
            $stockQty = (float)($stockRow['qty'] ?? 0);
            echo "  No PO mappings - Fallback Stock balance: $stockQty\n";
        }
    }
    echo "\n";
}

echo "\n=== All Stock Balances ===\n";
$allStock = $mysqli->query('SELECT product_id, warehouse_id, location_id, quantity FROM stock_balances');
echo "Total records: " . $allStock->num_rows . "\n";
while ($s = $allStock->fetch_assoc()) {
    echo "Product {$s['product_id']}: {$s['quantity']} (WH: {$s['warehouse_id']}, Loc: {$s['location_id']})\n";
}

$mysqli->close();
?>
