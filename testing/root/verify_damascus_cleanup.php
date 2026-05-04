<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
$checks = [
    'products' => "SELECT COUNT(*) c FROM products WHERE id = 3",
    'product_variants' => "SELECT COUNT(*) c FROM product_variants WHERE product_id = 3",
    'quotation_3' => "SELECT COUNT(*) c FROM quotations WHERE id = 3",
    'quotation_3_lines' => "SELECT COUNT(*) c FROM quotation_lines WHERE quotation_id = 3",
    'quotation_2_bad_lines' => "SELECT COUNT(*) c FROM quotation_lines WHERE quotation_id = 2 AND product_id = 3",
    'sales_order_2' => "SELECT COUNT(*) c FROM sales_orders WHERE id = 2",
    'sales_order_2_lines' => "SELECT COUNT(*) c FROM sales_order_lines WHERE sales_order_id = 2",
    'purchase_rfq_3' => "SELECT COUNT(*) c FROM purchase_rfqs WHERE id = 3",
    'purchase_order_3' => "SELECT COUNT(*) c FROM purchase_orders WHERE id = 3",
    'purchase_order_3_lines' => "SELECT COUNT(*) c FROM purchase_order_lines WHERE po_id = 3",
    'vendor_bill_11' => "SELECT COUNT(*) c FROM vendor_bills WHERE id = 11",
    'vendor_bill_11_lines' => "SELECT COUNT(*) c FROM vendor_bill_lines WHERE vendor_bill_id = 11",
    'journal_entry_4' => "SELECT COUNT(*) c FROM journal_entries WHERE id = 4",
    'journal_lines_4' => "SELECT COUNT(*) c FROM journal_lines WHERE entry_id = 4",
    'variant_inventory_p3' => "SELECT COUNT(*) c FROM variant_inventory WHERE variant_id IN (SELECT id FROM product_variants WHERE product_id = 3)",
];
foreach ($checks as $label => $sql) {
    $res = $mysqli->query($sql);
    $row = $res->fetch_assoc();
    echo $label . '=' . ($row['c'] ?? 'ERR') . PHP_EOL;
    $res->free();
}
$res = $mysqli->query("SELECT id, subtotal, tax_total, total, converted_to_sales_order_id FROM quotations WHERE id = 2");
while ($row = $res->fetch_assoc()) {
    echo 'quotation_2=' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
$res->free();
$mysqli->close();
