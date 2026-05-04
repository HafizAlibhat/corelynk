<?php
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

echo "=== Checking RFQ RI-PO-0001 ===\n\n";

$result = $db->query("SELECT * FROM purchase_rfqs WHERE rfq_number = 'RI-PO-0001'");
$rfq = $result->fetch_assoc();

if ($rfq) {
    echo "RFQ ID: " . $rfq['id'] . "\n";
    echo "RFQ Number: " . $rfq['rfq_number'] . "\n";
    echo "Status: " . $rfq['status'] . "\n";
    echo "Vendor ID: " . $rfq['vendor_id'] . "\n";
    echo "Notes: " . ($rfq['notes'] ?? 'N/A') . "\n\n";
    
    echo "=== Option 1: Reset RFQ status to 'draft' ===\n";
    echo "This will allow the button to show again and let you create a new RFQ.\n\n";
    
    echo "SQL Command:\n";
    echo "UPDATE purchase_rfqs SET status = 'draft' WHERE rfq_number = 'RI-PO-0001';\n\n";
    
    echo "=== Option 2: Delete the RFQ and create fresh ===\n";
    echo "This will remove the existing RFQ entirely.\n\n";
    
    echo "SQL Command:\n";
    echo "DELETE FROM purchase_rfqs WHERE rfq_number = 'RI-PO-0001';\n";
    echo "DELETE FROM purchase_rfq_lines WHERE rfq_id = " . $rfq['id'] . ";\n\n";
    
} else {
    echo "RFQ not found\n";
}

$db->close();
