<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');

// Update PO lines with variant_id based on matching descriptions
$updates = [
    6 => 1,  // "Black Feather" → Variant 1
    7 => 1,  // "Black Feather" → Variant 1  
    8 => 3,  // "Feather" → Variant 3
];

foreach ($updates as $poLineId => $variantId) {
    $sql = "UPDATE purchase_order_lines SET variant_id = $variantId WHERE id = $poLineId";
    if ($mysqli->query($sql)) {
        echo "Updated PO Line " . $poLineId . " with variant_id = " . $variantId . "\n";
    } else {
        echo "Error updating PO Line " . $poLineId . ": " . $mysqli->error . "\n";
    }
}

// Verify updates
echo "\n=== Verification ===\n";
$result = $mysqli->query("SELECT id, product_id, variant_id, description FROM purchase_order_lines WHERE product_id = 1");
while($row = $result->fetch_assoc()) {
    echo "PO Line " . $row['id'] . ": variant_id=" . $row['variant_id'] . ", desc=" . substr($row['description'], 0, 40) . "\n";
}

$mysqli->close();
?>
