<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);

$queries = [
    'po' => "SELECT * FROM purchase_orders WHERE id = 2",
    'po_lines' => "SELECT * FROM purchase_order_lines WHERE po_id = 2 ORDER BY id",
    'grns_old' => "SELECT * FROM grns WHERE po_id = 2 ORDER BY id",
    'grn_lines_old' => "SELECT gl.* FROM grn_lines gl JOIN grns g ON g.id = gl.grn_id WHERE g.po_id = 2 ORDER BY gl.id",
    'grns_new' => "SELECT * FROM purchase_grns WHERE po_id = 2 ORDER BY id",
    'grn_lines_new' => "SELECT gl.* FROM purchase_grn_lines gl JOIN purchase_grns g ON g.id = gl.grn_id WHERE g.po_id = 2 ORDER BY gl.id",
    'vendor_bills' => "SELECT * FROM vendor_bills WHERE po_id = 2 ORDER BY id",
    'vendor_bill_lines' => "SELECT vbl.* FROM vendor_bill_lines vbl JOIN vendor_bills vb ON vb.id = vbl.vendor_bill_id WHERE vb.po_id = 2 ORDER BY vbl.id",
    'stock_movements' => "SELECT * FROM stock_movements WHERE reference_id IN (SELECT id FROM purchase_grns WHERE po_id = 2) OR reference_id IN (SELECT id FROM grns WHERE po_id = 2) ORDER BY id",
    'variant_inventory' => "SELECT * FROM variant_inventory WHERE product_id IN (SELECT product_id FROM purchase_order_lines WHERE po_id = 2)",
    'stock_balances' => "SELECT * FROM stock_balances WHERE product_id IN (SELECT product_id FROM purchase_order_lines WHERE po_id = 2)",
    'products' => "SELECT id, name, code, stock, quantity, current_stock FROM products WHERE id IN (SELECT product_id FROM purchase_order_lines WHERE po_id = 2)",
];

foreach ($queries as $label => $sql) {
    echo strtoupper($label) . PHP_EOL;
    $res = $mysqli->query($sql);
    if (!$res) {
        echo 'ERR: ' . $mysqli->error . PHP_EOL;
        continue;
    }
    while ($row = $res->fetch_assoc()) {
        echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
    $res->free();
}
$mysqli->close();
