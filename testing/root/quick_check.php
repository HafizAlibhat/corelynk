<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$mysqli = new mysqli('localhost', 'root', '', 'corelynk');
$mysqli->set_charset('utf8mb4');

if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error);
}

echo "<pre>";

echo "=== CHECKING SALES ORDER LINES ===\n\n";

// Check SO RI-S0002
$result = $mysqli->query("
    SELECT sol.id, sol.product_id, sol.product_variant_id, sol.quantity, p.name, p.product_type
    FROM sales_order_lines sol
    LEFT JOIN products p ON p.id = sol.product_id
    LEFT JOIN sales_orders so ON so.id = sol.sales_order_id
    WHERE so.order_number IN ('RI-S0002', 'RI-S0001')
    ORDER BY so.order_number
");

echo "Sales Order Lines:\n";
while ($row = $result->fetch_assoc()) {
    echo "Line ID: {$row['id']}, Product: {$row['name']}, Type: {$row['product_type']}\n";
    echo "  Product ID: {$row['product_id']}, Variant ID: ";
    echo ($row['product_variant_id'] ? $row['product_variant_id'] : 'NULL') . "\n";
    echo "  Quantity: {$row['quantity']}\n\n";
}

echo "\n=== CHECKING STOCK_BALANCES ===\n\n";

$stockResult = $mysqli->query("SELECT * FROM stock_balances ORDER BY product_id, variant_id");
echo "All Stock Balances:\n";
while ($row = $stockResult->fetch_assoc()) {
    echo "Product ID: {$row['product_id']}, Variant ID: ";
    echo ($row['variant_id'] ? $row['variant_id'] : 'NULL');
    echo ", Qty: {$row['quantity']}, WH: {$row['warehouse_id']}, Loc: {$row['location_id']}\n";
}

echo "\n=== CHECKING PRODUCT TYPES ===\n\n";

$prodResult = $mysqli->query("
    SELECT id, name, product_type 
    FROM products 
    WHERE id IN (SELECT DISTINCT product_id FROM sales_order_lines sol 
                 LEFT JOIN sales_orders so ON so.id = sol.sales_order_id 
                 WHERE so.order_number IN ('RI-S0002', 'RI-S0001'))
");

echo "Products in SO:\n";
while ($row = $prodResult->fetch_assoc()) {
    echo "ID: {$row['id']}, Name: {$row['name']}, Type: {$row['product_type']}\n";
}

echo "</pre>";

$mysqli->close();
?>
