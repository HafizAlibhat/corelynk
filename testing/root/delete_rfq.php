<?php
// Delete the existing RFQ via PHP
$db = new \mysqli('localhost', 'root', '', 'corelynk_db');

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}

echo "Deleting RFQ lines...\n";
$db->query("DELETE FROM purchase_rfq_lines WHERE rfq_id = 1");
if ($db->error) {
    echo "Error: " . $db->error . "\n";
} else {
    echo "✓ Deleted " . $db->affected_rows . " RFQ lines\n";
}

echo "Deleting RFQ...\n";
$db->query("DELETE FROM purchase_rfqs WHERE id = 1");
if ($db->error) {
    echo "Error: " . $db->error . "\n";
} else {
    echo "✓ Deleted " . $db->affected_rows . " RFQ\n";
}

$db->close();
echo "\nDone! The Auto-Create RFQ button should now be visible.\n";
echo "But first, you MUST assign a vendor to Product #2 (Test Product).\n";
