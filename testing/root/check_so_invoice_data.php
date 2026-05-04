<?php
// Direct connection
$db = new mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection error: " . $db->connect_error);
}

// Get the RFQ
echo "=== RFQ RI-PO-0003 ===\n";
$result = $db->query("SELECT id FROM purchase_rfqs WHERE rfq_number = 'RI-PO-0003' LIMIT 1;");
if ($row = $result->fetch_assoc()) {
    $rfq_id = $row['id'];
    echo "RFQ ID: " . $rfq_id . "\n\n";
    
    // Get the lines
    echo "=== RFQ Lines ===\n";
    $result = $db->query("SELECT * FROM purchase_rfq_lines WHERE rfq_id = $rfq_id LIMIT 3;");
    while ($row = $result->fetch_assoc()) {
        echo "Line ID: " . $row['id'] . "\n";
        echo "  product_id: " . ($row['product_id'] ?? 'NULL') . "\n";
        echo "  product_variant_id: " . ($row['product_variant_id'] ?? 'NULL') . "\n";
        echo "  description: " . ($row['description'] ?? '') . "\n";
        echo "  All fields: " . implode(', ', array_keys($row)) . "\n\n";
    }
}

$db->close();
?>
