<?php
// Temporary debug script — delete after use
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'corelynk_db';
$port = 3306;

$mysqli = new mysqli($host, $user, $pass, $dbname, $port);
if ($mysqli->connect_errno) {
    echo "Connect failed: ($mysqli->connect_errno) $mysqli->connect_error\n";
    exit(1);
}

echo "Connected to DB: $dbname@ $host:$port\n\n";

// Show invoice distribution by currency (all invoices)
if ($rows = $mysqli->query("SELECT currency_code, COUNT(*) AS cnt, SUM(total_amount) AS total FROM customer_invoices GROUP BY currency_code")) {
    echo "Invoice counts by currency (all statuses):\n";
    while ($r = $rows->fetch_assoc()) {
        printf("  %s : %d invoices — total %s\n", $r['currency_code'] ?? '(NULL)', (int)$r['cnt'], number_format((float)$r['total'],2));
    }
    $rows->free();
    echo "\n";
}

// Use customer_payment_allocations to compute payment allocations per invoice
$allocColsRes = $mysqli->query("SHOW COLUMNS FROM customer_payment_allocations");
$allocInvoiceCol = null;
if ($allocColsRes) {
    while ($c = $allocColsRes->fetch_assoc()) {
        if (stripos($c['Field'], 'invoice') !== false) {
            $allocInvoiceCol = $c['Field'];
            break;
        }
    }
    $allocColsRes->free();
}

$query = "SELECT ci.currency_code, SUM(ci.total_amount) - COALESCE(SUM(pay.paid_amount),0) AS receivable
FROM customer_invoices ci
LEFT JOIN (
    SELECT a.invoice_id, SUM(a.allocated_amount) AS paid_amount
    FROM customer_payment_allocations a
    JOIN customer_payments p ON p.id = a.payment_id
    WHERE p.posted_entry_id IS NOT NULL
    GROUP BY a.invoice_id
 ) pay ON pay.invoice_id = ci.id
WHERE ci.status NOT IN ('paid','cancelled') AND ci.deleted_at IS NULL
GROUP BY ci.currency_code";
if ($res = $mysqli->query($query)) {
    echo "Receivables by currency:\n";
    while ($row = $res->fetch_assoc()) {
        printf("  %s : %s\n", $row['currency_code'] ?? '(NULL)', number_format((float)$row['receivable'], 2));
    }
    $res->free();
} else {
    echo "Query error: " . $mysqli->error . "\n";
}

// Detailed per-invoice breakdown for debugging
$dbgQ = "SELECT ci.id, ci.invoice_number, ci.currency_code, ci.total_amount, COALESCE(pay.paid_amount,0) AS paid_amount, (ci.total_amount - COALESCE(pay.paid_amount,0)) AS receivable
FROM customer_invoices ci
LEFT JOIN (
    SELECT a.invoice_id, SUM(a.allocated_amount) AS paid_amount
    FROM customer_payment_allocations a
    JOIN customer_payments p ON p.id = a.payment_id
    WHERE p.posted_entry_id IS NOT NULL
    GROUP BY a.invoice_id
) pay ON pay.invoice_id = ci.id
WHERE ci.status NOT IN ('paid','cancelled') AND ci.deleted_at IS NULL
ORDER BY ci.id DESC";
if ($dres = $mysqli->query($dbgQ)) {
    echo "\nPer-invoice debug:\n";
    while ($row = $dres->fetch_assoc()) {
        printf("  #%d %s — %s %s — paid:%s — receivable:%s\n", $row['id'], $row['invoice_number'], $row['currency_code'] ?? '(NULL)', number_format((float)$row['total_amount'],2), number_format((float)$row['paid_amount'],2), number_format((float)$row['receivable'],2));
    }
    $dres->free();
}

echo "\nRecent unpaid invoices (sample):\n";
$q2 = "SELECT id, invoice_number, currency_code, total_amount, status, created_at FROM customer_invoices WHERE status NOT IN ('paid','cancelled') AND deleted_at IS NULL ORDER BY id DESC LIMIT 20";
if ($res2 = $mysqli->query($q2)) {
    while ($r = $res2->fetch_assoc()) {
        printf("  #%d %s — %s %s — status:%s — %s\n", $r['id'], $r['invoice_number'], $r['currency_code'] ?? '(NULL)', number_format((float)$r['total_amount'],2), $r['status'], $r['created_at']);
    }
    $res2->free();
}

// List invoices in USD specifically
echo "\nInvoices with currency USD:\n";
$qUsd = "SELECT id, invoice_number, currency_code, total_amount, status, created_at FROM customer_invoices WHERE currency_code = 'USD' ORDER BY id DESC";
if ($rUsd = $mysqli->query($qUsd)) {
    while ($rw = $rUsd->fetch_assoc()) {
        printf("  #%d %s — %s %s — status:%s — %s\n", $rw['id'], $rw['invoice_number'], $rw['currency_code'] ?? '(NULL)', number_format((float)$rw['total_amount'],2), $rw['status'], $rw['created_at']);
    }
    $rUsd->free();
}

// Optionally list payments for the first invoice
echo "\nPayments (posted) for latest invoice id if any:\n";
$q3 = "SELECT id FROM customer_invoices WHERE status NOT IN ('paid','cancelled') AND deleted_at IS NULL ORDER BY id DESC LIMIT 1";
$latest = $mysqli->query($q3)->fetch_assoc();
if (!empty($latest['id'])) {
    $invId = (int)$latest['id'];
    if ($paymentInvoiceCol) {
        $sql = "SELECT id, amount, status, payment_date FROM customer_payments WHERE {$paymentInvoiceCol} = ? ORDER BY id DESC";
        $q4 = $mysqli->prepare($sql);
        $q4->bind_param('i', $invId);
        $q4->execute();
        $res4 = $q4->get_result();
    } else {
        // fallback: try invoice_id
        $sql = "SELECT id, amount, status, payment_date FROM customer_payments WHERE invoice_id = ? ORDER BY id DESC";
        $q4 = $mysqli->prepare($sql);
        $q4->bind_param('i', $invId);
        $q4->execute();
        $res4 = $q4->get_result();
    }
    while ($p = $res4->fetch_assoc()) {
        printf("  payment #%d — %s — %s — %s\n", $p['id'], number_format((float)$p['amount'],2), $p['status'], $p['payment_date']);
    }
    $q4->close();
} else {
    echo "  (no unpaid invoices found)\n";
}

$mysqli->close();
echo "\nDone.\n";
