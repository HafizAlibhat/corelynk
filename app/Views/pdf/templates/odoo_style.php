<?php
$invoice = $invoice ?? [];
$lines = $lines ?? [];
$company = $company ?? [];
$customer = $customer ?? [];
$customerAddress = $customerAddress ?? [];

$title = (string)($document_title ?? 'Invoice');
$numberLabel = (string)($document_number_label ?? ($title . ' #'));
$dateLabel = (string)($document_date_label ?? 'Date:');
$number = (string)($invoice['invoice_number'] ?? $invoice['number'] ?? '');
$dateRaw = (string)($invoice['issue_date'] ?? $invoice['invoice_date'] ?? $invoice['order_date'] ?? '');
$date = $dateRaw !== '' && strtotime($dateRaw) ? date('m/d/Y', strtotime($dateRaw)) : $dateRaw;
$currency = (string)($invoice['currency_code'] ?? $invoice['currency'] ?? $company['base_currency'] ?? '');
$symbol = $currency === 'USD' ? '$' : ($currency !== '' ? $currency . ' ' : '');

$fmt = static function ($v, int $d = 2): string {
    return number_format((float)$v, $d);
};
$money = static function ($v) use ($fmt, $symbol): string {
    return $symbol . $fmt($v);
};
$lineType = static function (array $ln): string {
    return strtolower((string)($ln['display_type'] ?? 'line')) === 'section' ? 'section' : 'line';
};
$img = static function (array $ln): string {
    foreach (['product_image_path', 'variant_image_path', 'product_image_url', 'variant_image_url'] as $key) {
        $v = trim((string)($ln[$key] ?? ''));
        if ($v !== '') return $v;
    }
    return '';
};
$addr = array_filter([
    $customerAddress['line1'] ?? '',
    $customerAddress['line2'] ?? '',
    trim((string)($customerAddress['city_name'] ?? '') . ' ' . (string)($customerAddress['state_name'] ?? '')),
    $customerAddress['postal_code'] ?? '',
]);
$companyName = trim((string)($company['name'] ?? ''));
$hideCompanyLogo = !empty($hide_company_logo);
$logo = $hideCompanyLogo ? '' : trim((string)($company['logo_path'] ?? $company['company_logo'] ?? ''));
$companyHeaderLines = array_filter([
    trim((string)($company['address'] ?? '')),
    trim((string)($company['phone'] ?? '')),
    trim((string)($company['email'] ?? '')),
    trim((string)($company['website'] ?? '')),
]);
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
        @page { margin: 28px 34px 64px; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#3f454b; font-size:11px; }
        .top { width:100%; border-bottom:1px solid #e2e6ea; padding-bottom:14px; margin-bottom:34px; }
        .logo { width:78px; height:78px; object-fit:contain; }
        .company { text-align:right; line-height:1.36; color:#333; font-size:10.4px; }
        .party { margin-left:54%; line-height:1.45; margin-bottom:42px; }
        h1 { color:#0b8799; font-size:28px; font-weight:400; margin:0 0 22px; }
        .meta { width:100%; margin-bottom:22px; }
        .muted { color:#9aa3aa; }
        table.lines { width:100%; border-collapse:collapse; font-size:10.5px; }
        .lines th { color:#9aa3aa; font-weight:400; text-align:left; border:1px solid #777; padding:7px 5px; }
        .lines td { border-left:1px solid #777; border-right:1px solid #777; padding:7px 5px; vertical-align:top; }
        .lines tr.item-last td { border-bottom:1px solid #777; }
        .num { text-align:right; white-space:nowrap; }
        .thumb { width:44px; height:44px; object-fit:cover; }
        .section td { background:#4b4f54; color:#fff; font-weight:700; border:1px solid #4b4f54; padding:7px 5px; }
        .section-subtotal td { background:#f7f8f9; color:#3f454b; border-top:1px solid #777; font-weight:600; padding:6px 5px; }
        .section-subtotal .subtotal-label { text-align:right; padding-right:10px; }
        .section-subtotal .section-subtotal-value { text-align:right; }
        .section-subtotal-gap td { border:0; height:9px; padding:0; background:#fff; }
        .desc { width:47%; }
        .amount { background:#e8e8e8; }
        .totals { width:46%; margin-left:auto; margin-top:16px; border-collapse:collapse; }
        .totals td { padding:7px 8px; border:1px solid #168aa4; }
        .totals .grand td { background:#2a98b7; color:#fff; font-size:13px; }
        .footer { position:fixed; left:34px; right:34px; bottom:18px; border-top:1px solid #111; height:14px; font-size:0; }
    </style>
</head>
<body>
    <table class="top">
        <tr>
            <td>
                <?php if ($logo !== ''): ?><img class="logo" src="<?= esc($logo) ?>" alt=""><?php endif; ?>
            </td>
            <td class="company">
                <strong><?= esc($companyName) ?></strong><br>
                <?php foreach ($companyHeaderLines as $companyLine): ?>
                    <?= esc($companyLine) ?><br>
                <?php endforeach; ?>
            </td>
        </tr>
    </table>

    <div class="party">
        <strong><?= esc($customer['name'] ?? '') ?></strong><br>
        <?= esc(implode(' ', $addr)) ?><br>
        <?= esc($customer['email'] ?? '') ?>
    </div>

    <h1><?= esc($title) ?> <?= esc($numberLabel) ?> <?= esc($number) ?></h1>
    <table class="meta">
        <tr>
            <td><span class="muted"><?= esc($dateLabel) ?></span><br><?= esc($date) ?></td>
            <td style="text-align:right;"><span class="muted">Salesperson:</span><br><?= esc($invoice['salesperson'] ?? $invoice['created_by_name'] ?? '') ?></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th class="desc">DESCRIPTION</th>
                <th style="width:7%;">IMAGE</th>
                <th class="num" style="width:11%;">QUANTITY</th>
                <th class="num" style="width:11%;">UNIT PRICE</th>
                <th class="num" style="width:7%;">DISC.%</th>
                <th class="num" style="width:7%;">TAXES</th>
                <th class="num amount" style="width:12%;">AMOUNT</th>
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
                    <td class="num amount section-subtotal-value"><?= $money($amount) ?></td>
                </tr>
                <tr class="section-subtotal-gap">
                    <td colspan="7"></td>
                </tr>
                <?php
            };
        ?>
        <?php foreach ($lines as $idx => $ln): ?>
            <?php if ($lineType($ln) === 'section'): ?>
                <?php if ($sectionOpen): $printSectionSubtotal($sectionSubtotal); endif; ?>
                <?php $sectionOpen = true; $sectionSubtotal = 0.0; ?>
                <tr class="section"><td colspan="7"><?= esc($ln['section_title'] ?? $ln['description'] ?? 'Section') ?></td></tr>
                <?php continue; ?>
            <?php endif; ?>
            <?php $image = $img($ln); ?>
            <?php $sectionSubtotal += (float)($ln['line_total'] ?? 0); ?>
            <tr class="<?= $idx === array_key_last($lines) ? 'item-last' : '' ?>">
                <td class="desc"><?= esc($ln['product_name'] ?? $ln['description'] ?? '') ?></td>
                <td><?php if ($image !== ''): ?><img class="thumb" src="<?= esc($image) ?>" alt=""><?php endif; ?></td>
                <td class="num"><?= $fmt($ln['quantity'] ?? $ln['qty'] ?? 0) ?> <?= esc($ln['unit'] ?? 'Units') ?></td>
<td class="num"><?= $money($ln['unit_price'] ?? 0) ?></td>
                <td class="num"><?= $fmt($ln['discount_value'] ?? $ln['discount_percent'] ?? 0) ?></td>
                <td class="num"><?= $fmt($ln['tax_rate'] ?? $ln['tax_percent'] ?? 0) ?></td>
                <td class="num amount"><?= $money($ln['line_total'] ?? 0) ?></td>
            </tr>
        <?php endforeach; ?>
            <?php if ($sectionOpen): $printSectionSubtotal($sectionSubtotal); endif; ?>
        </tbody>
    </table>

    <table class="totals">
        <?php if ($discount > 0): ?><tr><td>Discount</td><td class="num">-<?= $money($discount) ?></td></tr><?php endif; ?>
        <?php if ($tax > 0): ?><tr><td>Tax</td><td class="num"><?= $money($tax) ?></td></tr><?php endif; ?>
        <?php if ($shipping > 0): ?><tr><td>Shipping</td><td class="num"><?= $money($shipping) ?></td></tr><?php endif; ?>
        <tr class="grand"><td>Total</td><td class="num"><?= $money($total) ?></td></tr>
    </table>

    <div class="footer"></div>
</body>
</html>
