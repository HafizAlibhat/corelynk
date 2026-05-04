<?php
// Update GRN #5 lines with variant_id from PO lines
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

echo "=== Updating GRN #5 lines with variant_id from PO ===\n\n";

// Get PO ID from GRN
$result = $mysqli->query("SELECT po_id FROM purchase_grns WHERE id = 5");
$row = $result->fetch_assoc();
$poId = $row['po_id'];
echo "PO ID: $poId\n\n";

// Get PO lines with variant_id
$result = $mysqli->query("SELECT id, product_id, variant_id FROM purchase_order_lines WHERE po_id = $poId ORDER BY id");
$poLines = [];
while ($row = $result->fetch_assoc()) {
    $poLines[] = $row;
    echo "PO Line {$row['id']}: product_id={$row['product_id']}, variant_id={$row['variant_id']}\n";
}

echo "\n=== Updating GRN lines ===\n\n";

// Get GRN lines
$result = $mysqli->query("SELECT id, po_line_id, product_id FROM purchase_grn_lines WHERE grn_id = 5 ORDER BY id");
$grnLines = $result->fetch_all(MYSQLI_ASSOC);

foreach ($grnLines as $grnLine) {
    $grnLineId = $grnLine['id'];
    $poLineId = $grnLine['po_line_id'];
    
    // Find matching PO line
    $variantId = null;
    foreach ($poLines as $poLine) {
        if ($poLine['id'] == $poLineId) {
            $variantId = $poLine['variant_id'];
            break;
        }
    }
    
    if ($variantId) {
        echo "Updating GRN line $grnLineId with variant_id = $variantId\n";
        $mysqli->query("UPDATE purchase_grn_lines SET variant_id = $variantId WHERE id = $grnLineId");
    } else {
        echo "GRN line $grnLineId: No variant_id found in PO line $poLineId\n";
    }
}

echo "\n=== Updated GRN lines ===\n";
$result = $mysqli->query("SELECT id, product_id, variant_id FROM purchase_grn_lines WHERE grn_id = 5");
while ($row = $result->fetch_assoc()) {
    echo "Line {$row['id']}: product_id={$row['product_id']}, variant_id={$row['variant_id']}\n";
}

$mysqli->close();
echo "\nDone! Refresh the GRN page.\n";
