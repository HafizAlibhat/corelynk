<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Checking SO #2 (RI-S0002) ===\n\n";

// Get SO lines
$result = $mysqli->query("SELECT id, product_id, quantity FROM sales_order_lines WHERE sales_order_id = 2");
echo "SO Lines:\n";
while ($row = $result->fetch_assoc()) {
    echo "  Line {$row['id']}: product_id={$row['product_id']}, qty={$row['quantity']}\n";
    
    // Check PO mappings
    $lineId = $row['id'];
    $mapResult = $mysqli->query("SELECT * FROM sales_order_line_po_map WHERE sales_order_line_id = $lineId");
    $mapCount = $mapResult->num_rows;
    echo "    PO Mappings: $mapCount\n";
    while ($map = $mapResult->fetch_assoc()) {
        echo "      → PO Line ID: {$map['purchase_order_line_id']}\n";
    }
}

echo "\n=== Checking SO #1 (RI-S0001) ===\n\n";

// Get SO lines
$result = $mysqli->query("SELECT id, product_id, quantity FROM sales_order_lines WHERE sales_order_id = 1");
echo "SO Lines:\n";
while ($row = $result->fetch_assoc()) {
    echo "  Line {$row['id']}: product_id={$row['product_id']}, qty={$row['quantity']}\n";
    
    // Check PO mappings
    $lineId = $row['id'];
    $mapResult = $mysqli->query("SELECT * FROM sales_order_line_po_map WHERE sales_order_line_id = $lineId");
    $mapCount = $mapResult->num_rows;
    echo "    PO Mappings: $mapCount\n";
    while ($map = $mapResult->fetch_assoc()) {
        echo "      → PO Line ID: {$map['purchase_order_line_id']}\n";
    }
}

$mysqli->close();
