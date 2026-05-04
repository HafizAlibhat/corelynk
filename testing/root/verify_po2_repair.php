<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
$queries = [
    'po' => "SELECT id, po_number, status FROM purchase_orders WHERE id = 2",
    'po_line' => "SELECT id, po_id, product_id, qty, qty_received FROM purchase_order_lines WHERE id = 2",
    'grn' => "SELECT * FROM purchase_grns WHERE po_id = 2 ORDER BY id DESC",
    'grn_line' => "SELECT * FROM purchase_grn_lines WHERE grn_id = 4",
    'product' => "SELECT id, name, current_stock FROM products WHERE id = 1",
    'stock_balance' => "SELECT * FROM stock_balances WHERE product_id = 1 AND variant_id IS NULL ORDER BY id DESC",
    'stock_movement' => "SELECT * FROM stock_movements WHERE reference_type = 'grn' AND reference_id = 4 ORDER BY id DESC",
    'extra_bill' => "SELECT id, po_id, based_on, notes, total_amount, status FROM vendor_bills WHERE id = 14",
];
foreach ($queries as $label => $sql) {
    echo strtoupper($label) . PHP_EOL;
    $res = $mysqli->query($sql);
    if (!$res) { echo 'ERR: '.$mysqli->error.PHP_EOL; continue; }
    while ($row = $res->fetch_assoc()) echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    $res->free();
}
$mysqli->close();
