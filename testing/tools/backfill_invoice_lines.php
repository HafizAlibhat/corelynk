<?php
// Usage:
// php tools/backfill_invoice_lines.php --id=17
// php tools/backfill_invoice_lines.php --all

$options = [];
foreach ($argv as $a) {
    if (strpos($a, '--') === 0) {
        $p = substr($a, 2);
        $parts = explode('=', $p, 2);
        $options[$parts[0]] = $parts[1] ?? true;
    }
}

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';
$dbname = getenv('DB_NAME') ?: 'corelynk_db';

$mysqli = new mysqli($host, $user, $pass, $dbname);
if ($mysqli->connect_errno) {
    fwrite(STDERR, "Connect failed: ({$mysqli->connect_errno}) {$mysqli->connect_error}\n");
    exit(2);
}

function update_invoice_lines($mysqli, $invoiceId) {
    $invoiceId = (int)$invoiceId;
    $invRes = $mysqli->query("SELECT * FROM customer_invoices WHERE id={$invoiceId} LIMIT 1");
    if (!$invRes || !$invRes->num_rows) {
        echo "Invoice {$invoiceId} not found\n";
        return;
    }
    $inv = $invRes->fetch_assoc();

    // If this invoice is linked back to a sales order that originated from a quotation,
    // prefer the original per-line tax_rate from quotation_lines.
    $quoteTaxRateByProduct = [];
    try {
        $salesOrderId = 0;
        $soRes = $mysqli->query("SELECT sales_order_id FROM customer_invoices WHERE id={$invoiceId} LIMIT 1");
        if ($soRes && ($so = $soRes->fetch_assoc())) {
            $salesOrderId = (int)($so['sales_order_id'] ?? 0);
        }
        if ($salesOrderId > 0) {
            $qRes = $mysqli->query("SELECT quotation_id FROM sales_orders WHERE id={$salesOrderId} LIMIT 1");
            $qid = 0;
            if ($qRes && ($q = $qRes->fetch_assoc())) {
                $qid = (int)($q['quotation_id'] ?? 0);
            }
            if ($qid > 0) {
                $qlRes = $mysqli->query("SELECT product_id, tax_rate FROM quotation_lines WHERE quotation_id={$qid}");
                if ($qlRes) {
                    while ($r = $qlRes->fetch_assoc()) {
                        $pid = (int)($r['product_id'] ?? 0);
                        if ($pid > 0 && $r['tax_rate'] !== null && $r['tax_rate'] !== '') {
                            $quoteTaxRateByProduct[$pid] = (float)$r['tax_rate'];
                        }
                    }
                }
            }
        }
    } catch (\Throwable $_) {
        $quoteTaxRateByProduct = [];
    }
    $subtotal = (float)($inv['subtotal'] ?? 0);
    $taxTotal = (float)($inv['tax_total'] ?? 0);
    $shipping = (float)($inv['shipping_cost'] ?? 0);
    $total = (float)($inv['total_amount'] ?? 0);
    $headerDiscount = ($subtotal + $taxTotal + $shipping) - $total;
    if ($headerDiscount < 0) $headerDiscount = 0.0;

    $linesRes = $mysqli->query("SELECT * FROM customer_invoice_lines WHERE invoice_id={$invoiceId}");
    if (!$linesRes) { echo "Failed to read lines: {$mysqli->error}\n"; return; }
    $lines = [];
    $sumBase = 0.0;
    while ($r = $linesRes->fetch_assoc()) {
        $base = (float)$r['quantity'] * (float)$r['unit_price'];
        $sumBase += $base;
        $lines[] = $r;
    }
    if (empty($lines)) { echo "Invoice {$invoiceId} has no lines\n"; return; }
    if ($sumBase <= 0) $sumBase = 1.0;

    // Compute per-line discount/tax and update rows where discount_amount is zero or null
    foreach ($lines as $ln) {
        $id = (int)$ln['id'];
        $qty = (float)$ln['quantity'];
        $price = (float)$ln['unit_price'];
        $base = $qty * $price;
        $share = $base / $sumBase;
        $discountAmt = round($headerDiscount * $share, 2);
        $taxable = max(0, $base - $discountAmt);
        // allocate tax proportional to taxable portion
        $taxAmt = 0.0;
        if ($taxTotal > 0) {
            // denominator for tax allocation: sum of (base - discount share) across lines
            // compute denom
            $denom = 0.0;
            foreach ($lines as $x) { $denom += max(0, ((float)$x['quantity'] * (float)$x['unit_price']) - round($headerDiscount * (((float)$x['quantity'] * (float)$x['unit_price']) / $sumBase),2)); }
            if ($denom <= 0) $denom = 1.0;
            $taxAmt = round($taxTotal * ($taxable / $denom), 2);
        }
        $discountType = 'percent';
        $discountValue = $base > 0 ? round(($discountAmt / $base) * 100.0, 4) : 0.0;
    $pid = (int)($ln['product_id'] ?? 0);
    $taxRate = isset($quoteTaxRateByProduct[$pid]) ? round((float)$quoteTaxRateByProduct[$pid], 4) : ($taxable > 0 ? round(($taxAmt / $taxable) * 100.0, 4) : 0.0);

        // Only update if stored values are missing/zero to avoid overwriting
        $doUpdate = false;
        $updates = [];
        if (empty($ln['discount_amount']) || abs((float)$ln['discount_amount']) < 0.0001) {
            $updates[] = "discount_amount = " . $mysqli->real_escape_string((string)$discountAmt);
            $updates[] = "discount_value = " . $mysqli->real_escape_string((string)$discountValue);
            $updates[] = "discount_type = 'percent'";
            $doUpdate = true;
        }
        if (empty($ln['tax_amount']) || abs((float)$ln['tax_amount']) < 0.0001) {
            $updates[] = "tax_amount = " . $mysqli->real_escape_string((string)$taxAmt);
            $updates[] = "tax_rate = " . $mysqli->real_escape_string((string)$taxRate);
            $doUpdate = true;
        }

        // If tax_amount is present but the stored tax_rate was derived (and doesn't match the quotation rate), fix tax_rate only.
        if (!$doUpdate && $pid > 0 && isset($quoteTaxRateByProduct[$pid])) {
            $storedRate = (float)($ln['tax_rate'] ?? 0);
            $targetRate = (float)$quoteTaxRateByProduct[$pid];
            if (abs($storedRate - $targetRate) > 0.0001) {
                $updates[] = "tax_rate = " . $mysqli->real_escape_string((string)round($targetRate, 4));
                $doUpdate = true;
            }
        }
        if ($doUpdate && !empty($updates)) {
            $sql = "UPDATE customer_invoice_lines SET " . implode(', ', $updates) . " WHERE id = {$id} LIMIT 1";
            if (!$mysqli->query($sql)) {
                echo "Failed to update line {$id}: {$mysqli->error}\n";
            } else {
                echo "Updated line {$id} (discount {$discountAmt}, tax {$taxAmt})\n";
            }
        } else {
            echo "Skipped line {$id} (already populated)\n";
        }
    }
}

if (isset($options['all'])) {
    $res = $mysqli->query('SELECT id FROM customer_invoices');
    while ($r = $res->fetch_assoc()) update_invoice_lines($mysqli, (int)$r['id']);
} elseif (!empty($options['id'])) {
    update_invoice_lines($mysqli, (int)$options['id']);
} else {
    echo "Specify --id=NN or --all\n";
}

