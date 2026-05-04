<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die($mysqli->connect_error . PHP_EOL);
$res = $mysqli->query("SELECT id, subtotal, tax_total, total, converted_to_sales_order_id FROM quotations WHERE id = 2");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
$res->free();
$res = $mysqli->query("SELECT id, product_id, product_variant_id, description, quantity, unit_price, line_total FROM quotation_lines WHERE quotation_id = 2 ORDER BY id");
while ($row = $res->fetch_assoc()) {
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}
$res->free();
$mysqli->close();
