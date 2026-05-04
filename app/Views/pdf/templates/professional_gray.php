<?php
/**
 * Professional Gray PDF Template
 * Corporate look with gray/charcoal theme
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
    return $ts ? date('d/m/Y', $ts) : $value;
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

// --- Address and Country Name Logic ---
$addr1 = trim((string)($customerAddress['line1'] ?? ''));
$addr2 = trim((string)($customerAddress['line2'] ?? ''));
$addrCombined = implode(', ', array_filter([$addr1, $addr2]));
$cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
if ($cityState !== '') $addrCombined = $addrCombined !== '' ? ($addrCombined . ', ' . $cityState) : $cityState;

// Country name resolution: prefer country_name, else resolve from country_id, else fallback to country code
$countryName = '';
if (!empty($customerAddress['country_name'])) {
    $countryName = $customerAddress['country_name'];
} elseif (!empty($customerAddress['country_id'])) {
    try {
        $countryModel = new \App\Models\CountryModel();
        $countryRow = $countryModel->find($customerAddress['country_id']);
        if ($countryRow && !empty($countryRow['name'])) {
            $countryName = $countryRow['name'];
        }
    } catch (\Throwable $e) {}
}
if (empty($countryName) && !empty($customerAddress['country'])) {
    $countryName = $customerAddress['country'];
}

$contactParts = [];
if (!empty($customer['mobile'])) $contactParts[] = $customer['mobile'];
if (!empty($customer['phone'])) $contactParts[] = $customer['phone'];

$addressLine = $addrCombined;
if (!empty($countryName)) {
    $addressLine = $addressLine !== '' ? ($addressLine . ', ' . $countryName) : $countryName;
}
$customerLines = array_filter([$addressLine ?: null, implode(' | ', $contactParts) ?: null]);

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
            font-family: 'DejaVu Sans', 'Segoe UI', Arial, sans-serif;
            font-size: 9px;
            color: #111827;
            padding: 0;
            background: #fff;
        }
        
        /* Dark header bar */
        .top-bar {
            background: #1f2937;
            color: #fff;
            padding: 16px 24px;
            margin-bottom: 0;
        }
        .top-bar-inner { display: table; width: 100%; }
        .top-left, .top-right { display: table-cell; vertical-align: middle; }
        .top-left { width: 50%; }
        .top-right { width: 50%; text-align: right; }
        .logo img { max-height: 48px; max-width: 140px; }
        .company-name { font-size: 16px; font-weight: 700; color: #fff; }
        .company-line { font-size: 9px; color: #9ca3af; line-height: 1.4; }
        
        /* Content area */
        .content { padding: 20px 24px 50px 24px; }
        
        /* Document title - left aligned, minimal */
        .doc-info {
            margin-bottom: 18px;
            padding-bottom: 14px;
            border-bottom: 2px solid #e5e7eb;
        }
        .doc-title {
            font-size: 24px;
            font-weight: 300;
            color: #374151;
            letter-spacing: 1px;
        }
        .doc-meta {
            font-size: 10px;
            color: #6b7280;
            margin-top: 4px;
        }
        .doc-meta strong { color: #374151; }
        
        /* Customer/From boxes side by side */
        .parties { width: 100%; margin-bottom: 18px; }
        .party-col { vertical-align: top; width: 50%; }
        .party-box { padding: 0 10px 0 0; }
        .party-box.right { padding: 0 0 0 10px; }
        .party-title {
            font-size: 8px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: 6px;
        }
        .party-name { font-size: 11px; font-weight: 600; color: #111827; margin-bottom: 2px; }
        .party-line { font-size: 9px; color: #4b5563; line-height: 1.4; }
        
        /* Minimal table - no background colors */
        .items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .items th {
            background: #f3f4f6;
            color: #374151;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            text-align: left;
            border-bottom: 2px solid #9ca3af;
        }
        .items td {
            padding: 8px 6px;
            font-size: 9px;
            border-bottom: 1px solid #e5e7eb;
            color: #374151;
        }
        .items .num { text-align: right; }
        .items .img-cell { text-align: center; width: 40px; padding: 4px; }
        .items .img-cell img { max-height: 28px; max-width: 28px; }
        
        /* Totals - minimal right aligned */
        .totals-wrap { width: 240px; margin-left: auto; margin-top: 12px; }
        .totals { width: 100%; border-collapse: collapse; }
        .totals td { padding: 6px 0; font-size: 9px; border-bottom: 1px solid #e5e7eb; }
        .totals .lbl { text-align: left; color: #6b7280; }
        .totals .val { text-align: right; font-weight: 500; color: #111827; }
        .totals .grand td { 
            border-bottom: none; 
            border-top: 2px solid #1f2937; 
            padding-top: 10px;
            font-size: 12px;
            font-weight: 700;
        }
        .totals .grand .lbl { color: #1f2937; }
        
        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: #fff;
            border-top: 1.5px solid #9ca3af;
            padding: 6px 24px 8px 24px;
            text-align: center;
            font-size: 9px;
            color: #6b7280;
            letter-spacing: 0.02em;
        }
    </style>
</head>
<body>
    <div class="top-bar">
        <div class="top-bar-inner">
            <div class="top-left">
                <?php if (!empty($logoSrc)): ?>
                    <div class="logo"><img src="<?= esc($logoSrc) ?>" alt="Logo"></div>
                <?php else: ?>
                    <div class="company-name"><?= esc($company['name'] ?? 'Company') ?></div>
                <?php endif; ?>
            </div>
            <div class="top-right">
                <div class="company-name"><?= esc($company['name'] ?? '') ?></div>
                <?php foreach ($companyLines as $line): ?>
                    <div class="company-line"><?= esc($line) ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="content">
        <div class="doc-info">
            <div class="doc-title"><?= esc($documentTitle) ?></div>
            <div class="doc-meta">
                <strong><?= esc($documentNumberLabel) ?></strong> <?= esc($invoiceNo) ?>
                &nbsp;&nbsp;|&nbsp;&nbsp;
                <strong>Date:</strong> <?= esc($fmtDate($issueDate)) ?>
                <?php if ($paymentTerms): ?>
                    &nbsp;&nbsp;|&nbsp;&nbsp;
                    <strong>Terms:</strong> <?= esc($paymentTerms) ?>
                <?php endif; ?>
            </div>
        </div>

        <table class="parties" cellpadding="0" cellspacing="0">
            <tr>
                <td class="party-col">
                    <div class="party-box">
                        <div class="party-title">Bill To</div>
                        <div class="party-name"><?= esc($customerLabel) ?></div>
                        <?php foreach ($customerLines as $line): ?>
                            <div class="party-line"><?= esc($line) ?></div>
                        <?php endforeach; ?>
                    </div>
                </td>
                <td class="party-col">
                    <div class="party-box right">
                        <div class="party-title">From</div>
                        <div class="party-name"><?= esc($company['name'] ?? '') ?></div>
                        <?php foreach ($companyLines as $line): ?>
                            <div class="party-line"><?= esc($line) ?></div>
                        <?php endforeach; ?>
                    </div>
                </td>
            </tr>
        </table>

        <table class="items">
            <thead>
                <tr>
                    <th style="width:12%;">Code</th>
                    <?php if ($canRenderImages): ?><th style="width:7%;">Img</th><?php endif; ?>
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
                    // Prefer variant image if available
                    if (!empty($ln['variant_image'])) {
                        $filename = basename($ln['variant_image']);
                    } elseif (!empty($ln['variant_images'])) {
                        $arr = is_string($ln['variant_images']) ? json_decode($ln['variant_images'], true) : $ln['variant_images'];
                        if (is_array($arr) && !empty($arr[0])) $filename = basename($arr[0]);
                    }
                    // Fallback to product images if no variant image
                    if ($filename === '' && !empty($ln['product_images'])) {
                        $arr = is_string($ln['product_images']) ? json_decode($ln['product_images'], true) : $ln['product_images'];
                        if (is_array($arr) && !empty($arr[0])) $filename = basename($arr[0]);
                    }
                    if ($filename === '' && !empty($ln['product_image'])) $filename = basename($ln['product_image']);
                    if ($filename === '' && !empty($ln['image'])) $filename = basename($ln['image']);
                    if ($filename !== '') {
                        $raw = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $filename;
                        if (is_file($raw)) {
                            if (!$gdLoaded && preg_match('/\\.png$/i', $raw)) {
                                $jpgRaw = preg_replace('/\\.png$/i', '.jpg', $raw);
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
                <tr class="grand"><td class="lbl">Total Due</td><td class="val"><?= $fmtMoney($total) ?></td></tr>
            </table>
        </div>
    </div>

    <?php if ($footerText !== ''): ?>
        <div class="footer"><?= esc($footerText) ?></div>
    <?php endif; ?>
</body>
</html>
