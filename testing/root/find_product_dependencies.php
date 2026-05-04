<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
$productId = 3;
$variantIds = [];
$res = $mysqli->query("SELECT id FROM product_variants WHERE product_id = {$productId}");
while ($row = $res->fetch_assoc()) { $variantIds[] = (int)$row['id']; }
$res->free();
$variantList = $variantIds ? implode(',', $variantIds) : '0';

$queries = [
    'quotations' => "SELECT DISTINCT q.id, q.quote_number, q.converted_to_sales_order_id FROM quotations q JOIN quotation_lines l ON l.quotation_id=q.id WHERE l.product_id={$productId} OR (l.product_variant_id IS NOT NULL AND l.product_variant_id IN ({$variantList})) ORDER BY q.id",
    'sales_orders' => "SELECT DISTINCT s.id, s.order_number, s.quotation_id, s.status FROM sales_orders s JOIN sales_order_lines l ON l.sales_order_id=s.id WHERE l.product_id={$productId} OR (l.product_variant_id IS NOT NULL AND l.product_variant_id IN ({$variantList})) ORDER BY s.id",
    'purchase_orders' => "SELECT DISTINCT p.id, p.po_number, p.rfq_id, p.status FROM purchase_orders p JOIN purchase_order_lines l ON l.po_id=p.id WHERE l.product_id={$productId} OR (l.variant_id IS NOT NULL AND l.variant_id IN ({$variantList})) ORDER BY p.id",
    'vendor_bills' => "SELECT DISTINCT b.id, b.bill_number, b.po_id, b.status FROM vendor_bills b LEFT JOIN vendor_bill_lines l ON l.vendor_bill_id=b.id WHERE l.product_id={$productId} OR (l.variant_id IS NOT NULL AND l.variant_id IN ({$variantList})) OR b.po_id IN (SELECT DISTINCT po_id FROM purchase_order_lines WHERE product_id={$productId}) ORDER BY b.id",
    'delivery_orders' => "SELECT * FROM delivery_orders WHERE sales_order_id IN (SELECT DISTINCT sales_order_id FROM sales_order_lines WHERE product_id={$productId} OR (product_variant_id IS NOT NULL AND product_variant_id IN ({$variantList}))) ORDER BY id",
    'sales_order_line_po_map' => "SELECT * FROM sales_order_line_po_map WHERE sales_order_line_id IN (SELECT id FROM sales_order_lines WHERE product_id={$productId} OR (product_variant_id IS NOT NULL AND product_variant_id IN ({$variantList}))) OR purchase_order_line_id IN (SELECT id FROM purchase_order_lines WHERE product_id={$productId} OR (variant_id IS NOT NULL AND variant_id IN ({$variantList}))) ORDER BY id",
    'rfq_lines' => "SELECT * FROM rfq_lines WHERE product_id={$productId} OR (variant_id IS NOT NULL AND variant_id IN ({$variantList})) ORDER BY id",
    'rfqs' => "SELECT * FROM rfqs WHERE id IN (SELECT DISTINCT rfq_id FROM purchase_orders WHERE id IN (SELECT DISTINCT po_id FROM purchase_order_lines WHERE product_id={$productId})) ORDER BY id",
];

foreach ($queries as $label => $sql) {
    echo strtoupper($label) . PHP_EOL;
    $r = $mysqli->query($sql);
    if (!$r) {
        echo '  query failed: ' . $mysqli->error . PHP_EOL;
        continue;
    }
    while ($row = $r->fetch_assoc()) {
        echo '  - ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    $r->free();
}
$mysqli->close();
