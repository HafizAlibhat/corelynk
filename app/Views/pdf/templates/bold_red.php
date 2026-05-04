<?php
/**
 * Bold Red PDF Template
 * Strong header design with red accents
 */
$invoice = $invoice ?? ($payload['invoice'] ?? []);
$lines = $lines ?? ($payload['lines'] ?? []);
$company = $company ?? ($payload['company'] ?? []);
$customer = $customer ?? ($payload['customer'] ?? []);
$customerAddress = $customerAddress ?? ($payload['customerAddress'] ?? []);

$gdLoaded = extension_loaded('gd');
$canRenderImages = true;

// Resolve logo: generator converts to base64 data URI; fall back to file:// path only if needed
$logoSrc = '';
$logoRaw = trim((string)($company['logo_path'] ?? ($company['company_logo'] ?? ($company['logo'] ?? ''))));
if (strncmp($logoRaw, 'data:', 5) === 0) {
    $logoSrc = $logoRaw;
} elseif ($logoRaw !== '') {
    $logoNorm = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, $logoRaw), DIRECTORY_SEPARATOR);
    $logoDir = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'company' . DIRECTORY_SEPARATOR;
    $candidates = [
        rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $logoNorm,
        $logoDir . $logoNorm,
        $logoDir . basename($logoNorm),
    ];
    foreach ($candidates as $cand) {
        if (is_file($cand)) {
            $real = str_replace('\\', '/', realpath($cand) ?: $cand);
            $logoSrc = preg_match('#^[A-Za-z]:/#', $real) ? ('file:///' . $real) : ('file://' . $real);
            break;
        }
    }
}

$documentTitle = trim((string)($document_title ?? 'Invoice'));
if ($documentTitle === '') $documentTitle = 'Invoice';
$documentNumberLabel = trim((string)($document_number_label ?? ($documentTitle . ' #')));
$documentDateLabel = trim((string)($document_date_label ?? ($documentTitle . ' Date:')));
$documentPrefix = (string)($document_prefix ?? 'INV-');

$invoiceNoRaw = $invoice['invoice_number'] ?? ($invoice['id'] ?? '');
$invoiceNo = $invoiceNoRaw;
if ($invoiceNo !== '' && $documentPrefix !== '') {
    $invoiceNo = (stripos($invoiceNo, $documentPrefix) === 0) ? $invoiceNo : $documentPrefix . ltrim($invoiceNo, '# ');
}
$issueDate = $invoice['issue_date'] ?? '';
$currency = 'USD';
$currencyCandidates = [
    $invoice['currency_code'] ?? null, $invoice['currency'] ?? null,
    $invoice['so_currency_code'] ?? null, $invoice['so_currency'] ?? null,
    $company['default_sales_currency'] ?? null, $company['currency_code'] ?? null,
];
foreach ($currencyCandidates as $cand) {
    $cand = strtoupper(trim((string)$cand));
    if ($cand !== '') { $currency = $cand; break; }
}
$currencySymbols = ['USD'=>'$','EUR'=>'€','GBP'=>'£','PKR'=>'₨','INR'=>'₹','JPY'=>'¥','CNY'=>'¥'];
$currencySymbol = $currencySymbols[$currency] ?? $currency;
$paymentTerms = trim((string)($invoice['payment_terms'] ?? ''));

$hasAnyDiscount = false;
$hasAnyTax = false;
if (!empty($lines)) {
    foreach ($lines as $ln) {
        $dv = (float)($ln['discount_value'] ?? 0);
        $da = (float)($ln['discount_amount'] ?? 0);
        $tr = (float)($ln['tax_rate'] ?? ($ln['tax'] ?? 0));
        $ta = (float)($ln['tax_amount'] ?? 0);
        if ($dv > 0 || $da > 0) $hasAnyDiscount = true;
        if ($tr > 0 || $ta > 0) $hasAnyTax = true;
    }
}

$fmtDate = function ($value) {
    if (empty($value)) return '';
    $ts = strtotime($value);
    return $ts ? date('M d, Y', $ts) : $value;
};

$subtotal = isset($invoice['subtotal']) ? (float)$invoice['subtotal'] : 0.0;
$tax = isset($invoice['tax_total']) ? (float)$invoice['tax_total'] : 0.0;
$shipping = (float)($invoice['shipping_amount'] ?? ($invoice['shipping_cost'] ?? ($invoice['shipping'] ?? 0)));
$discountTotal = isset($invoice['discount_total']) ? (float)$invoice['discount_total'] : (float)($invoice['discount'] ?? 0);

$computedSubtotal = 0.0;
$computedDiscount = 0.0;
$computedTax = 0.0;
if (!empty($lines)) {
    foreach ($lines as $ln) {
        $qty = (float)($ln['quantity'] ?? 0);
        $unitPrice = (float)($ln['unit_price'] ?? 0);
        $lineBase = $qty * $unitPrice;
        $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : ($lineBase * ((float)($ln['discount_value'] ?? 0) / 100.0));
        $taxRate = (float)($ln['tax_rate'] ?? ($ln['tax'] ?? 0));
        $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($lineBase - $discAmt) * ($taxRate / 100.0));
        $computedSubtotal += $lineBase;
        $computedDiscount += $discAmt;
        $computedTax += $taxAmt;
    }
}
if ($subtotal == 0.0 && $computedSubtotal > 0) $subtotal = $computedSubtotal;
if ($discountTotal == 0.0 && $computedDiscount > 0) $discountTotal = $computedDiscount;
if ($tax == 0.0 && $computedTax > 0) $tax = $computedTax;

$total = (float)($invoice['total_amount'] ?? ($subtotal - $discountTotal + $tax + $shipping));

$fmtMoney = function ($value = 0.0) use ($currencySymbol) {
    return $currencySymbol . number_format((float)$value, 2);
};

$customerLabel = trim($customer['name'] ?? $customer['company_name'] ?? $customer['customer_name'] ?? 'Customer');
$addr1 = trim((string)($customerAddress['line1'] ?? ''));
$addr2 = trim((string)($customerAddress['line2'] ?? ''));
$addrCombined = implode(', ', array_filter([$addr1, $addr2]));
$cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
if ($cityState !== '') $addrCombined = $addrCombined !== '' ? ($addrCombined . ', ' . $cityState) : $cityState;
$contactParts = [];
if (!empty($customer['mobile'])) $contactParts[] = $customer['mobile'];
if (!empty($customer['phone'])) $contactParts[] = $customer['phone'];
$customerLines = array_filter([$addrCombined ?: null, implode(' | ', $contactParts) ?: null]);

$companyLines = array_filter([
    trim($company['address'] ?? ''),
    !empty($company['phone']) ? $company['phone'] : (!empty($company['contact']) ? $company['contact'] : null),
    !empty($company['email']) ? $company['email'] : null,
]);

$footerText = trim((string)($company['invoice_footer'] ?? ''));
if ($footerText === '') {
    $parts = array_filter([$company['website'] ?? '', $company['email'] ?? '', $company['phone'] ?? $company['contact'] ?? '']);
    $footerText = implode('  |  ', $parts);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 9px;
            color: #1f2937;
            padding: 0;
            background: #fff;
        }
        
        /* Bold red header */
        .header {
            background: #dc2626;
            color: #fff;
            padding: 20px 24px;
        }
        .header-inner { display: table; width: 100%; }
        .header-left, .header-right { display: table-cell; vertical-align: middle; }
        .header-left { width: 60%; }
        .header-right { width: 40%; text-align: right; }
        .logo img { max-height: 50px; max-width: 140px; background: #fff; padding: 4px; border-radius: 4px; }
        .doc-type {
            font-size: 28px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 3px;
        }
        .doc-number { font-size: 11px; opacity: 0.9; margin-top: 4px; }
        
        /* Content */
        .content { padding: 20px 24px 50px 24px; }
        
        /* Info strip */
        .info-strip {
            background: #fef2f2;
            border-left: 4px solid #dc2626;
            padding: 12px 14px;
            margin-bottom: 18px;
            display: table;
            width: 100%;
        }
        .info-cell { display: table-cell; vertical-align: top; }
        .info-cell.customer { width: 55%; }
        .info-cell.details { width: 45%; text-align: right; }
        .info-label {
            font-size: 8px;
            font-weight: 700;
            color: #dc2626;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .info-name { font-size: 12px; font-weight: 700; color: #1f2937; }
        .info-line { font-size: 9px; color: #4b5563; line-height: 1.4; }
        .detail-row { font-size: 9px; color: #4b5563; margin-bottom: 2px; }
        .detail-row strong { color: #1f2937; }
        
        /* Table with red header */
        .items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items th {
            background: #dc2626;
            color: #fff;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            text-align: left;
        }
        .items td {
            border-bottom: 1px solid #fecaca;
            padding: 7px 6px;
            font-size: 9px;
        }
        .items tr:nth-child(even) td { background: #fef2f2; }
        .items .num { text-align: right; }
        .items .img-cell { text-align: center; width: 40px; padding: 3px; }
        .items .img-cell img { max-height: 30px; max-width: 30px; border: 1px solid #fecaca; }
        
        /* Totals with red accent */
        .totals-wrap { width: 230px; margin-left: auto; margin-top: 12px; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 8px; font-size: 9px; }
        .totals tr { border-bottom: 1px solid #fecaca; }
        .totals .lbl { text-align: left; color: #6b7280; }
        .totals .val { text-align: right; font-weight: 600; color: #1f2937; }
        .totals .grand td {
            background: #dc2626;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
            padding: 10px 8px;
            border: none;
        }
        
        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 1.5px solid #dc2626;
            padding: 6px 24px 8px 24px;
            text-align: center;
            font-size: 9px;
            color: #dc2626;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-inner">
            <div class="header-left">
                <?php if (!empty($logoSrc)): ?>
                    <div class="logo"><img src="<?= esc($logoSrc) ?>" alt="Logo"></div>
                <?php else: ?>
                    <div style="font-size:16px; font-weight:700;"><?= esc($company['name'] ?? 'Company') ?></div>
                <?php endif; ?>
            </div>
            <div class="header-right">
                <div class="doc-type"><?= esc($documentTitle) ?></div>
                <div class="doc-number"><?= esc($invoiceNo) ?></div>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="info-strip">
            <div class="info-cell customer">
                <div class="info-label">Bill To</div>
                <div class="info-name"><?= esc($customerLabel) ?></div>
                <?php foreach ($customerLines as $line): ?>
                    <div class="info-line"><?= esc($line) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="info-cell details">
                <div class="detail-row"><strong>Date:</strong> <?= esc($fmtDate($issueDate)) ?></div>
                <div class="detail-row"><strong>Currency:</strong> <?= esc($currency) ?></div>
                <?php if ($paymentTerms): ?>
                    <div class="detail-row"><strong>Terms:</strong> <?= esc($paymentTerms) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:12%;">Code</th>
                    <?php if ($canRenderImages): ?><th style="width:8%;">Image</th><?php endif; ?>
                    <th>Description</th>
                    <th class="num" style="width:7%;">Qty</th>
                    <th class="num" style="width:11%;">Price</th>
                    <?php if ($hasAnyDiscount): ?><th class="num" style="width:9%;">Disc</th><?php endif; ?>
                    <?php if ($hasAnyTax): ?><th class="num" style="width:9%;">Tax</th><?php endif; ?>
                    <th class="num" style="width:11%;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lines as $ln):
                    $qty = (float)($ln['quantity'] ?? 0);
                    $unitPrice = (float)($ln['unit_price'] ?? 0);
                    $lineBase = $qty * $unitPrice;
                    $discAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : ($lineBase * ((float)($ln['discount_value'] ?? 0) / 100.0));
                    $taxRate = (float)($ln['tax_rate'] ?? ($ln['tax'] ?? 0));
                    $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($lineBase - $discAmt) * ($taxRate / 100.0));
                    $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : ($lineBase - $discAmt + $taxAmt);
                    
                    $imgPath = '';
                    $filename = '';
                    if (!empty($ln['product_images'])) {
                        $arr = is_string($ln['product_images']) ? json_decode($ln['product_images'], true) : $ln['product_images'];
                        if (is_array($arr) && !empty($arr[0])) $filename = basename($arr[0]);
                    }
                    if ($filename === '' && !empty($ln['product_image'])) $filename = basename($ln['product_image']);
                    if ($filename === '' && !empty($ln['image'])) $filename = basename($ln['image']);
                    if ($filename !== '') {
                        $raw = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $filename;
                        if (is_file($raw)) {
                            if (!$gdLoaded && preg_match('/\.png$/i', $raw)) {
                                $jpgRaw = preg_replace('/\.png$/i', '.jpg', $raw);
                                if (is_file($jpgRaw)) $imgPath = 'file://' . str_replace('\\', '/', $jpgRaw);
                            } else {
                                $imgPath = 'file://' . str_replace('\\', '/', $raw);
                            }
                        }
                    }
                    
                    $code = trim($ln['product_code'] ?? $ln['code'] ?? $ln['sku'] ?? '');
                    $description = trim($ln['product_name'] ?? $ln['description'] ?? '');
                ?>
                    <tr>
                        <td><?= esc($code) ?></td>
                        <?php if ($canRenderImages): ?>
                            <td class="img-cell"><?php if ($imgPath): ?><img src="<?= esc($imgPath) ?>"><?php endif; ?></td>
                        <?php endif; ?>
                        <td><?= esc($description) ?></td>
                        <td class="num"><?= number_format($qty, 2) ?></td>
                        <td class="num"><?= $fmtMoney($unitPrice) ?></td>
                        <?php if ($hasAnyDiscount): ?><td class="num"><?= $fmtMoney($discAmt) ?></td><?php endif; ?>
                        <?php if ($hasAnyTax): ?><td class="num"><?= $fmtMoney($taxAmt) ?></td><?php endif; ?>
                        <td class="num" style="font-weight:600;"><?= $fmtMoney($lineTotal) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-wrap">
            <table class="totals">
                <tr><td class="lbl">Subtotal</td><td class="val"><?= $fmtMoney($subtotal) ?></td></tr>
                <?php if ($discountTotal > 0): ?><tr><td class="lbl">Discount</td><td class="val">-<?= $fmtMoney($discountTotal) ?></td></tr><?php endif; ?>
                <?php if ($tax > 0): ?><tr><td class="lbl">Tax</td><td class="val"><?= $fmtMoney($tax) ?></td></tr><?php endif; ?>
                <?php if ($shipping > 0): ?><tr><td class="lbl">Shipping</td><td class="val"><?= $fmtMoney($shipping) ?></td></tr><?php endif; ?>
                <tr class="grand"><td class="lbl">TOTAL</td><td class="val"><?= $fmtMoney($total) ?></td></tr>
            </table>
        </div>
    </div>

    <?php if ($footerText !== ''): ?>
        <div class="footer"><?= esc($footerText) ?></div>
    <?php endif; ?>
</body>
</html>
