<?php
$mysqli = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($mysqli->connect_error) {
    die('Connection failed: ' . $mysqli->connect_error . PHP_EOL);
}

$productId = 3;
$stmt = $mysqli->prepare("SELECT * FROM products WHERE id = ? OR name LIKE '%Damascus Steel Billets - Flat%'");
$stmt->bind_param('i', $productId);
$stmt->execute();
$res = $stmt->get_result();
$products = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if (!$products) {
    echo "No matching product found" . PHP_EOL;
    exit;
}

foreach ($products as $product) {
    $productId = (int)$product['id'];
    echo "PRODUCT {$productId}: {$product['name']}" . PHP_EOL;

    $variants = [];
    $vr = $mysqli->query("SELECT id, name, art_number, attributes FROM product_variants WHERE product_id = {$productId} ORDER BY id ASC");
    if ($vr) { $variants = $vr->fetch_all(MYSQLI_ASSOC); $vr->free(); }
    $variantIds = array_map(fn($v) => (int)$v['id'], $variants);
    echo "Variants: " . count($variants) . PHP_EOL;
    foreach ($variants as $variant) {
        echo "  Variant {$variant['id']}: {$variant['name']} | {$variant['art_number']} | {$variant['attributes']}" . PHP_EOL;
    }

    $tables = [
        'quotation_lines' => ['id','quotation_id','product_id','variant_id','product_variant_id','description','product_name'],
        'sales_order_lines' => ['id','sales_order_id','product_id','variant_id','product_variant_id','description','product_name'],
        'purchase_order_lines' => ['id','po_id','product_id','variant_id','description'],
        'vendor_bill_lines' => ['id','vendor_bill_id','po_line_id','product_id','variant_id'],
        'purchase_grn_lines' => ['id','grn_id','po_line_id','product_id','variant_id','qty_received'],
        'stock_movements' => ['id','reference_type','reference_id','product_id','variant_id','quantity','movement_type'],
        'variant_inventory' => ['id','variant_id','warehouse_id','quantity','reserved'],
        'stock_balances' => ['id','product_id','variant_id','quantity'],
    ];

    foreach ($tables as $table => $preferredCols) {
        $cols = [];
        $colRes = $mysqli->query("SHOW COLUMNS FROM `{$table}`");
        if (!$colRes) {
            continue;
        }
        while ($row = $colRes->fetch_assoc()) { $cols[] = $row['Field']; }
        $colRes->free();

        $selectCols = array_values(array_intersect($preferredCols, $cols));
        if (!$selectCols) {
            $selectCols = ['*'];
        }

        $where = [];
        if (in_array('product_id', $cols, true)) {
            $where[] = "product_id = {$productId}";
        }
        if (!empty($variantIds)) {
            if (in_array('variant_id', $cols, true)) {
                $where[] = "variant_id IN (" . implode(',', $variantIds) . ")";
            }
            if (in_array('product_variant_id', $cols, true)) {
                $where[] = "product_variant_id IN (" . implode(',', $variantIds) . ")";
            }
        }
        if (!$where) {
            continue;
        }

        $sql = "SELECT " . implode(',', $selectCols) . " FROM `{$table}` WHERE " . implode(' OR ', $where) . " ORDER BY id ASC";
        $r = $mysqli->query($sql);
        if (!$r) {
            echo "  {$table}: query failed: {$mysqli->error}" . PHP_EOL;
            continue;
        }
        $rows = $r->fetch_all(MYSQLI_ASSOC);
        $r->free();
        echo strtoupper($table) . ': ' . count($rows) . PHP_EOL;
        foreach ($rows as $row) {
            echo '  - ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
    }

    $headerQueries = [
        'quotations' => "SELECT DISTINCT q.id, q.quote_number, q.status FROM quotations q JOIN quotation_lines l ON l.quotation_id = q.id WHERE l.product_id = {$productId}" . (!empty($variantIds) ? " OR l.product_variant_id IN (" . implode(',', $variantIds) . ")" : ''),
        'sales_orders' => "SELECT DISTINCT s.id, s.so_number, s.status FROM sales_orders s JOIN sales_order_lines l ON l.sales_order_id = s.id WHERE l.product_id = {$productId}" . (!empty($variantIds) ? " OR l.product_variant_id IN (" . implode(',', $variantIds) . ")" : ''),
        'purchase_orders' => "SELECT DISTINCT p.id, p.po_number, p.status FROM purchase_orders p JOIN purchase_order_lines l ON l.po_id = p.id WHERE l.product_id = {$productId}" . (!empty($variantIds) ? " OR l.variant_id IN (" . implode(',', $variantIds) . ")" : ''),
        'vendor_bills' => "SELECT DISTINCT b.id, b.bill_number, b.status, b.po_id FROM vendor_bills b LEFT JOIN vendor_bill_lines l ON l.vendor_bill_id = b.id WHERE l.product_id = {$productId}" . (!empty($variantIds) ? " OR l.variant_id IN (" . implode(',', $variantIds) . ")" : ''),
    ];

    foreach ($headerQueries as $label => $sql) {
        $r = $mysqli->query($sql);
        if (!$r) {
            echo strtoupper($label) . ": query failed: {$mysqli->error}" . PHP_EOL;
            continue;
        }
        $rows = $r->fetch_all(MYSQLI_ASSOC);
        $r->free();
        echo strtoupper($label) . ': ' . count($rows) . PHP_EOL;
        foreach ($rows as $row) {
            echo '  - ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }
    }
}

$mysqli->close();
