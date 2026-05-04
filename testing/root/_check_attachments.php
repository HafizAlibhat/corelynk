<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
echo "=== All document_attachments for document_id=1 ===\n";
$r = $db->query("SELECT id, document_type, document_id, original_name, file_path, uploaded_at FROM document_attachments WHERE document_id = 1 ORDER BY id");
while ($row = $r->fetch_assoc()) print_r($row);

echo "\n=== All vendor_payment attachments ===\n";
$r = $db->query("SELECT id, document_type, document_id, original_name FROM document_attachments WHERE LOWER(document_type) = 'vendor_payment' ORDER BY id");
while ($row = $r->fetch_assoc()) print_r($row);

echo "\n=== vendor_payments table ===\n";
$r = $db->query("SELECT id, vendor_id, status, payment_date, amount FROM vendor_payments ORDER BY id");
while ($row = $r->fetch_assoc()) print_r($row);
$db->close();
