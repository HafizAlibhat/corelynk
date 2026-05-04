<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Updating stock_balances with variant_id from stock_movements ===\n\n";

// Get all stock entries for product_id = 1 that were created from GRN
$result = $mysqli->query("
SELECT DISTINCT sb.id, sb.product_id, sm.reference_id, sm.reference_type
FROM stock_balances sb
LEFT JOIN stock_movements sm ON sm.product_id = sb.product_id AND sm.warehouse_id = sb.warehouse_id AND sm.location_id = sb.location_id
WHERE sb.product_id = 1
");

while ($row = $result->fetch_assoc()) {
    $sbId = $row['id'];
    $refType = $row['reference_type'];
    $grnId = $row['reference_id'];
    
    if ($refType === 'grn' && $grnId) {
        // Get variant_id from GRN lines
        $grnResult = $mysqli->query("SELECT DISTINCT variant_id FROM purchase_grn_lines WHERE grn_id = $grnId AND variant_id IS NOT NULL LIMIT 1");
        if ($grnRow = $grnResult->fetch_assoc()) {
            $variantId = $grnRow['variant_id'];
            echo "Updating stock_balance $sbId with variant_id = $variantId (from GRN #$grnId)\n";
            $mysqli->query("UPDATE stock_balances SET variant_id = $variantId WHERE id = $sbId");
        }
    }
}

echo "\n=== Updated stock_balances ===\n";
$result = $mysqli->query("SELECT id, product_id, variant_id, warehouse_id, location_id, quantity FROM stock_balances WHERE product_id = 1");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$mysqli->close();
echo "\nDone!\n";
