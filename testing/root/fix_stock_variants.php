<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Recreating stock entries for each variant separately ===\n\n";

// First, delete the combined entry
echo "Deleting combined stock entry...\n";
$mysqli->query("DELETE FROM stock_balances WHERE product_id = 1 AND variant_id = 2");

// Now get the 3 GRN lines and create separate stock entries
$result = $mysqli->query("
SELECT DISTINCT gl.variant_id, COUNT(gl.id) as cnt, SUM(gl.qty_received) as total_qty
FROM purchase_grn_lines gl
WHERE gl.grn_id = 5
GROUP BY gl.variant_id
");

echo "Creating separate entries for each variant:\n";
while ($row = $result->fetch_assoc()) {
    $variantId = $row['variant_id'];
    $qty = $row['total_qty'];
    $itemKey = 'v' . $variantId;
    
    // Insert stock entry
    $sql = "INSERT INTO stock_balances (product_id, variant_id, item_key, warehouse_id, location_id, quantity, created_at, updated_at)
            VALUES (1, $variantId, '$itemKey', 2, 3, $qty, NOW(), NOW())";
    
    echo "Inserting: variant_id=$variantId, qty=$qty, item_key=$itemKey\n";
    $mysqli->query($sql);
}

echo "\n=== Updated stock_balances ===\n";
$result = $mysqli->query("SELECT * FROM stock_balances WHERE product_id = 1 ORDER BY variant_id");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}

$mysqli->close();
echo "\nDone!\n";
