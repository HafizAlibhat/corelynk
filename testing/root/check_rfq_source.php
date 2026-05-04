<?php
$db = new mysqli('localhost', 'root', '', 'corelynk_db');
$r = $db->query('SELECT * FROM purchase_rfqs WHERE rfq_number="RI-PO-0003" LIMIT 1');
$rfq = $r->fetch_assoc();
echo "RFQ columns: " . implode(', ', array_keys($rfq)) . "\n\n";
echo "RFQ sales_order_id: " . ($rfq['sales_order_id'] ?? 'NULL') . "\n";
echo "RFQ notes: " . ($rfq['notes'] ?? '') . "\n\n";

// The RFQ note says "Auto RFQ from SO#2 (RI-S0002)" so it's linked to SO 2
$so_id = 2;
echo "=== Sales Order Lines for SO #$so_id ===\n";
$r = $db->query("SELECT id, product_id, product_variant_id, description FROM sales_order_lines WHERE sales_order_id=$so_id LIMIT 3");
while ($row = $r->fetch_assoc()) {
    print_r($row);
}
?>
