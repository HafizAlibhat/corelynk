<?php
$invoice = $invoice ?? [];
$lines = $lines ?? [];
$company = $company ?? [];
$customer = $customer ?? [];
$customerAddress = $customerAddress ?? [];

$title = (string)($document_title ?? 'Quotation');
$number = (string)($invoice['invoice_number'] ?? $invoice['number'] ?? '');
$dateRaw = (string)($invoice['issue_date'] ?? $invoice['invoice_date'] ?? $invoice['order_date'] ?? '');
$date = $dateRaw !== '' && strtotime($dateRaw) ? date('m/d/Y', strtotime($dateRaw)) : $dateRaw;
$currency = strtoupper((string)($invoice['currency_code'] ?? $invoice['currency'] ?? $company['base_currency'] ?? 'USD'));
$symbol = $currency === 'USD' ? '$' : ($currency !== '' ? $currency . ' ' : '');

$fmt = static fn($v, int $d = 2): string => number_format((float)$v, $d);
$money = static fn($v): string => $symbol . number_format((float)$v, 2);
$lineType = static fn(array $ln): string => strtolower((string)($ln['display_type'] ?? 'line')) === 'section' ? 'section' : 'line';
$imageFor = static function (array $ln): string {
    $rawCandidates = [];
    foreach (['product_image_path', 'variant_image_path', 'product_image_url', 'variant_image_url', 'product_image', 'variant_image', 'image_url', 'image'] as $key) {
        $v = trim((string)($ln[$key] ?? ''));
        if ($v !== '') $rawCandidates[] = $v;
    }
    if (!empty($ln['product_images'])) {
        $images = is_string($ln['product_images']) ? json_decode($ln['product_images'], true) : $ln['product_images'];
        if (is_array($images)) {
            foreach ($images as $img) {
                if (is_string($img) && trim($img) !== '') $rawCandidates[] = trim($img);
            }
        }
    }

    $localCandidates = [];
    foreach (array_unique($rawCandidates) as $raw) {
        if (strncmp($raw, 'data:', 5) === 0) {
            return $raw;
        }

        $path = $raw;
        if (strncmp($path, 'file://', 7) === 0) {
            $path = substr($path, 7);
            if ($path !== '' && $path[0] !== '/' && !preg_match('#^[A-Za-z]:#', $path)) {
                $path = '/' . $path;
            }
            if (preg_match('#^/[A-Za-z]:#', $path)) {
                $path = ltrim($path, '/');
            }
            $localCandidates[] = $path;
            continue;
        }

        if (preg_match('#^https?://#i', $path)) {
            $urlPath = parse_url($path, PHP_URL_PATH);
            $path = $urlPath ?: $path;
        }

        $normalized = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
        if ($normalized !== '') {
            $localCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $normalized;
            $localCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'variants' . DIRECTORY_SEPARATOR . basename($normalized);
            $localCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($normalized);
            $localCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($normalized);
        }
    }

    foreach (array_unique($localCandidates) as $candidate) {
        if (!is_file($candidate)) continue;
        $size = @filesize($candidate);
        if ($size === false || $size > 2 * 1024 * 1024) continue;
        $content = @file_get_contents($candidate);
        if ($content === false) continue;
        $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'bmp' => 'image/bmp'][$ext] ?? 'image/jpeg';
        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }
    return '';
};
$lineDescription = static function (array $ln): string {
    $name = trim((string)($ln['product_name'] ?? $ln['name'] ?? ''));
    $desc = trim((string)($ln['description'] ?? ''));
    if ($name !== '' && $desc !== '' && stripos($desc, $name) === false) {
        return $name . ' (' . $desc . ')';
    }
    if ($desc !== '') return $desc;
    return $name;
};
$qtyWithUnit = static function (array $ln) use ($fmt): string {
    $qty = $fmt($ln['quantity'] ?? $ln['qty'] ?? 0);
    $unit = trim((string)($ln['unit'] ?? 'Units'));
    return $qty . ' ' . ($unit !== '' ? $unit : 'Units');
};

$addr = array_filter([
    $customerAddress['line1'] ?? '',
    $customerAddress['line2'] ?? '',
    trim((string)($customerAddress['city_name'] ?? '') . ' ' . (string)($customerAddress['state_name'] ?? '')),
    $customerAddress['postal_code'] ?? '',
]);
$companyName = trim((string)($company['name'] ?? ''));
$companyCountry = trim((string)($company['country'] ?? $company['country_name'] ?? $company['company_country'] ?? $company['address_country'] ?? $company['billing_country'] ?? ''));
$companyHeaderLines = array_filter([
    trim((string)($company['address'] ?? '')),
    trim((string)($company['phone'] ?? '')),
    trim((string)($company['email'] ?? '')),
    trim((string)($company['website'] ?? '')),
    $companyCountry,
]);
$hideCompanyLogo = !empty($hide_company_logo);
$logo = $hideCompanyLogo ? '' : trim((string)($company['logo_path'] ?? $company['company_logo'] ?? ''));

$subtotal = (float)($invoice['subtotal'] ?? 0);
$discount = (float)($invoice['discount_total'] ?? $invoice['discount'] ?? 0);
$tax = (float)($invoice['tax_total'] ?? $invoice['tax'] ?? 0);
$shipping = (float)($invoice['shipping_amount'] ?? $invoice['shipping_cost'] ?? 0);
$total = (float)($invoice['total_amount'] ?? $invoice['grand_total'] ?? $invoice['total'] ?? ($subtotal - $discount + $tax + $shipping));
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 15px 34px 76px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#3f474f; font-size:10.25px; }
        .page { width:100%; }
        .top { width:100%; border-bottom:1px solid #e2e6ea; padding-bottom:9px; margin-bottom:24px; }
        .logo { width:72px; height:72px; object-fit:contain; }
        .company { text-align:right; line-height:1.36; color:#3f4448; font-size:10.3px; padding-top:3px; }
        .company strong { font-weight:600; }
        .customer { margin-left:58%; width:39%; line-height:1.42; margin-bottom:28px; font-size:10.8px; color:#4b535a; }
        .customer-name { font-weight:600; color:#3f4448; }
        .document-title { color:#13879d; font-size:27px; font-weight:400; margin:0 0 17px; letter-spacing:.01em; }
        .meta { width:100%; margin-bottom:20px; }
        .meta td { vertical-align:top; }
        .muted { color:#7f8b96; font-size:10px; }
        table.lines { width:100%; border-collapse:collapse; font-size:9.9px; }
        .lines th { color:#8f9aa3; font-weight:500; text-align:left; border:1px solid #5d6267; padding:6px 6px; background:#fff; line-height:1.1; }
        .lines td { border:1px solid #5d6267; padding:6px 6px; vertical-align:middle; line-height:1.35; }
        .lines tr { page-break-inside:avoid; break-inside:avoid; }
        .lines tbody tr.item td { border-top:0; border-bottom:1px solid #d7dadd; }
        .lines tbody tr.item:last-child td { border-bottom:1px solid #5d6267; }
        .num { text-align:center; white-space:nowrap; }
        .desc { width:51%; text-align:left; }
        .image-col { width:7%; text-align:center; }
        .thumb { width:44px; height:44px; object-fit:contain; }
        .section td { background:#4f5358; color:#fff; font-weight:500; border:1px solid #4f5358; padding:6px 7px; }
        .amount, .amount-head { background:#e8e8e8 !important; }
        .money-col { text-align:center; }
        .subtotal-row td { border-top:1px solid #5d6267; border-bottom:1px solid #5d6267; background:#f1f1f1; padding:7px 6px; }
        .subtotal-row .subtotal-label { text-align:right; color:#4e555c; }
        .summary { width:50%; margin-left:auto; margin-top:14px; border-collapse:collapse; font-size:11px; page-break-inside:avoid; break-inside:avoid; }
        .summary td { border:1px solid #4aa6b8; padding:7px 8px; }
        .summary .adjustment td { background:#fff; color:#40505a; border-color:#b7dbe3; }
        .summary .shipping td { background:#f4fbfd; color:#176f82; border-color:#74bdca; font-weight:500; }
        .summary .total td { background:#2597b4; border-color:#16829b; color:#fff; padding:9px 8px; font-size:12px; font-weight:700; }
        .summary .summary-value { text-align:center; }
        .section-subtotal td { background:#f7f8f9; color:#3f474f; border-top:1px solid #5d6267; font-weight:600; padding:6px; }
        .section-subtotal .subtotal-label { text-align:right; padding-right:10px; }
        .section-subtotal .section-subtotal-value { text-align:right; }
        .section-subtotal-gap td { border:0; height:9px; padding:0; background:#fff; }
        .footer { position:fixed; left:34px; right:34px; bottom:40px; border-top:1px solid #111; height:0; font-size:0; }
    </style>
</head>
<body>
<div class="page">
    <table class="top">
        <tr>
            <td style="width:50%;"><?php if ($logo !== ''): ?><img class="logo" src="<?= esc($logo) ?>" alt=""><?php endif; ?></td>
            <td class="company">
                <strong><?= esc($companyName) ?></strong><br>
                <?php foreach ($companyHeaderLines as $companyLine): ?>
                    <?= esc($companyLine) ?><br>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <div class="customer">
        <div class="customer-name"><?= esc($customer['name'] ?? '') ?></div>
        <div><?= esc(implode(' ', $addr)) ?></div>
    </div>

    <div class="document-title"><?= esc($title) ?><?= $number !== '' ? ' # ' . esc($number) : '' ?></div>

    <table class="meta">
        <tr>
            <td style="width:50%;">
                <span class="muted">Date:</span><br>
                <?= esc($date) ?>
            </td>
            <td style="width:25%;">
                <span class="muted">Salesperson:</span><br>
                <?= esc($invoice['salesperson'] ?? '') ?>
            </td>
            <td style="width:25%;"></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th class="desc">DESCRIPTION</th>
                <th class="image-col">IMAGE</th>
                <th class="num" style="width:11%;">QUANTITY</th>
                <th class="num" style="width:10%;">UNIT PRICE</th>
                <th class="num" style="width:7%;">DISC.%</th>
                <th class="num" style="width:7%;">TAXES</th>
                <th class="num amount-head" style="width:12%;">AMOUNT</th>
            </tr>
        </thead>
        <tbody>
        <?php
            $sectionSubtotal = 0.0;
            $sectionOpen = false;
            $printSectionSubtotal = static function (float $amount) use ($money): void {
                ?>
                <tr class="section-subtotal">
                    <td colspan="6" class="subtotal-label">Section Subtotal</td>
                    <td class="num amount money-col section-subtotal-value"><?= $money($amount) ?></td>
                </tr>
                <tr class="section-subtotal-gap">
                    <td colspan="7"></td>
                </tr>
                <?php
            };
        ?>
        <?php foreach ($lines as $ln): ?>
            <?php if ($lineType($ln) === 'section'): ?>
                <?php if ($sectionOpen): $printSectionSubtotal($sectionSubtotal); endif; ?>
                <?php $sectionOpen = true; $sectionSubtotal = 0.0; ?>
                <tr class="section"><td colspan="7"><?= esc($ln['section_title'] ?? $ln['description'] ?? 'Section') ?></td></tr>
                <?php continue; ?>
            <?php endif; ?>
            <?php
                $image = $imageFor($ln);
                $discType = strtolower((string)($ln['discount_type'] ?? 'percent'));
                $discValue = (float)($ln['discount_value'] ?? $ln['discount_percent'] ?? 0);
                $discDisplay = $discType === 'fixed' ? $money($discValue) : $fmt($discValue);
                $taxType = strtolower((string)($ln['tax_type'] ?? 'percent'));
                $taxValue = (float)($ln['tax_value'] ?? $ln['tax_rate'] ?? $ln['tax_percent'] ?? 0);
                $taxDisplay = $taxType === 'fixed' ? $money($taxValue) : ($taxValue > 0 ? $fmt($taxValue) : '');
                $sectionSubtotal += (float)($ln['line_total'] ?? 0);
            ?>
            <tr class="item">
                <td class="desc"><?= esc($lineDescription($ln)) ?></td>
                <td class="image-col"><?php if ($image !== ''): ?><img class="thumb" src="<?= esc($image) ?>" alt=""><?php endif; ?></td>
                <td class="num"><?= esc($qtyWithUnit($ln)) ?></td>
<td class="num money-col"><?= $money($ln['unit_price'] ?? 0) ?></td>
                <td class="num"><?= esc($discDisplay) ?></td>
                <td class="num"><?= esc($taxDisplay) ?></td>
                <td class="num amount money-col"><?= $money($ln['line_total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
            <?php if ($sectionOpen): $printSectionSubtotal($sectionSubtotal); endif; ?>
            <tr class="subtotal-row">
                <td colspan="6" class="subtotal-label">Subtotal</td>
                <td class="num amount money-col"><?= $money($subtotal) ?></td>
            </tr>
        </tbody>
    </table>

    <table class="summary">
        <?php if ($discount > 0): ?><tr class="adjustment"><td>Discount</td><td class="summary-value">-<?= $money($discount) ?></td></tr><?php endif; ?>
        <?php if ($tax > 0): ?><tr class="adjustment"><td>Taxes</td><td class="summary-value"><?= $money($tax) ?></td></tr><?php endif; ?>
        <?php if ($shipping > 0): ?><tr class="shipping"><td>Shipping</td><td class="summary-value"><?= $money($shipping) ?></td></tr><?php endif; ?>
        <tr class="total">
            <td>Total</td>
            <td class="summary-value"><?= $money($total) ?></td>
        </tr>
    </table>
</div>

<div class="footer"></div>
</body>
</html>
