<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');

// Delete phantom attachments for vendor_payment #1 (IDs 1 and 2)
$db->query("DELETE FROM document_attachments WHERE id IN (1, 2) AND document_type = 'vendor_payment'");
echo "Deleted: " . $db->affected_rows . " phantom attachment(s)\n";

// Verify
$r = $db->query("SELECT id, original_name FROM document_attachments WHERE LOWER(document_type) = 'vendor_payment' ORDER BY id");
echo "Remaining vendor_payment attachments:\n";
while ($row = $r->fetch_assoc()) {
    echo "  ID={$row['id']} => {$row['original_name']}\n";
}
$db->close();
