<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk');
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "=== STOCK_BALANCES TABLE STRUCTURE ===\n\n";

$result = $mysqli->query("DESCRIBE stock_balances");
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . " | Type: " . $row['Type'] . " | Null: " . $row['Null'] . " | Key: " . $row['Key'] . "\n";
}

echo "\n=== CHECKING SO RI-S0002 AND RI-S0001 ===\n\n";

$soResult = $mysqli->query("SELECT id, order_number FROM sales_orders WHERE order_number IN ('RI-S0002', 'RI-S0001')");

while ($so = $soResult->fetch_assoc()) {
    echo "--- SO: {$so['order_number']} (ID: {$so['id']}) ---\n";
    
    $soId = $so['id'];
    
    // Get SO lines
    $linesResult = $mysqli->query("
        SELECT sol.id, sol.product_id, sol.product_variant_id, sol.quantity, p.name, p.product_type
        FROM sales_order_lines sol
        LEFT JOIN products p ON p.id = sol.product_id
        WHERE sol.sales_order_id = $soId
    ");
    
    while ($line = $linesResult->fetch_assoc()) {
        echo "\n  Product: {$line['name']} (ID: {$line['product_id']}, Type: {$line['product_type']})\n";
        echo "  Line ID: {$line['id']}\n";
        echo "  Variant ID: " . ($line['product_variant_id'] ? $line['product_variant_id'] : 'NULL') . "\n";
        echo "  Qty: {$line['quantity']}\n";
        
        // Check stock_balances
        echo "\n  Stock_balances data:\n";
        $stockResult = $mysqli->query("
            SELECT product_id, variant_id, quantity, warehouse_id, location_id
            FROM stock_balances
            WHERE product_id = {$line['product_id']}
        ");
        
        if ($stockResult->num_rows == 0) {
            echo "    NO STOCK FOUND for product {$line['product_id']}\n";
        } else {
            while ($stock = $stockResult->fetch_assoc()) {
                echo "    - Product: {$stock['product_id']}, Variant: " . ($stock['variant_id'] ? $stock['variant_id'] : 'NULL') . 
                     ", Qty: {$stock['quantity']}, WH: {$stock['warehouse_id']}, Loc: {$stock['location_id']}\n";
            }
        }
        
        // Check PO mappings
        echo "\n  PO Mappings:\n";
        $mapResult = $mysqli->query("
            SELECT COUNT(*) as cnt FROM sales_order_line_po_map 
            WHERE sales_order_line_id = {$line['id']}
        ");
        $mapRow = $mapResult->fetch_assoc();
        echo "    Total: {$mapRow['cnt']}\n";
    }
    
    echo "\n";
}

$mysqli->close();
?>
