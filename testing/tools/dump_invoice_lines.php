<?php
// Usage: php tools/dump_invoice_lines.php [invoice_id]
$invoiceId = isset($argv[1]) ? (int)$argv[1] : 0;
$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbname = getenv('DB_NAME') ?: 'corelynk_db';
$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
    exit(2);
}
if ($invoiceId <= 0) {
    $res = $mysqli->query('SELECT * FROM customer_invoices ORDER BY id DESC LIMIT 1');
} else {
    $res = $mysqli->query('SELECT * FROM customer_invoices WHERE id=' . intval($invoiceId) . ' LIMIT 1');
}
if (!$res) { fwrite(STDERR, "Failed to fetch invoice: {$mysqli->error}\n"); exit(3); }
$row = $res->fetch_assoc();
if (!$row) { echo "No invoice found\n"; exit(0); }
echo "Invoice: ", json_encode($row), PHP_EOL;
$salesOrderId = isset($row['sales_order_id']) ? (int)$row['sales_order_id'] : 0;
echo "sales_order_id: ", $salesOrderId, PHP_EOL;
$quotationId = 0;
if ($salesOrderId > 0) {
    $qres = $mysqli->query('SELECT quotation_id FROM sales_orders WHERE id=' . $salesOrderId . ' LIMIT 1');
    if ($qres && ($qrow = $qres->fetch_assoc())) {
        $quotationId = (int)($qrow['quotation_id'] ?? 0);
    }
}
echo "quotation_id: ", $quotationId, PHP_EOL;
$iid = (int)$row['id'];
$lres = $mysqli->query('SELECT * FROM customer_invoice_lines WHERE invoice_id=' . $iid);
if (!$lres) { fwrite(STDERR, "Failed to fetch lines: {$mysqli->error}\n"); exit(4); }
$lines = [];
while ($r = $lres->fetch_assoc()) $lines[] = $r;
if (empty($lines)) { echo "No invoice lines found\n"; exit(0); }
foreach ($lines as $idx => $ln) {
    echo "Line ", ($idx+1), ": ", json_encode($ln), PHP_EOL;
}

if ($quotationId > 0 && !empty($lines)) {
    $pids = [];
    foreach ($lines as $ln) {
        $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
        if ($pid > 0) $pids[$pid] = true;
    }
    if (!empty($pids)) {
        $pidList = implode(',', array_keys($pids));
        $ql = $mysqli->query('SELECT product_id, tax_rate, discount_type, discount_value FROM quotation_lines WHERE quotation_id=' . $quotationId . ' AND product_id IN (' . $pidList . ')');
        if ($ql) {
            while ($r = $ql->fetch_assoc()) {
                echo "Quotation line: ", json_encode($r), PHP_EOL;
            }
        }
    }
}

