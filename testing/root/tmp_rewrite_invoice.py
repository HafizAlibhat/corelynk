from pathlib import Path
content = """<?php
$invoice = $invoice ?? ($payload['invoice'] ?? []);
$lines = $lines ?? ($payload['lines'] ?? []);
$company = $company ?? ($payload['company'] ?? []);
$customer = $customer ?? ($payload['customer'] ?? []);
$customerAddress = $customerAddress ?? ($payload['customerAddress'] ?? []);

$logoSrc = '';
$logoPath = $company['logo_path'] ?? ($company['logo'] ?? '');
if (!empty($logoPath)) {
    if (preg_match('#^https?://#i', $logoPath)) {
        $logoSrc = $logoPath;
    } else {
        $local = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $logoPath), DIRECTORY_SEPARATOR);
        if (is_file($local)) {
            $logoSrc = 'file://' . str_replace(DIRECTORY_SEPARATOR, '/', $local);
        } else {
            $logoSrc = base_url($logoPath);
        }
    }
}

$invoiceNo = $invoice['invoice_number'] ?? ($invoice['id'] ?? '');
$issueDate = $invoice['issue_date'] ?? '';
$dueDate = $invoice['due_date'] ?? '';
$currency = strtoupper(trim($invoice['currency_code'] ?? 'USD'));
$paymentTerms = trim($invoice['payment_terms'] ?? ($invoice['payment_terms_code'] ?? ''));
$paymentTerms = $paymentTerms ?: '—';

$fmtMoney = function ($value = 0.0) use ($currency) {
    $formatted = number_format((float)$value, 2);
    return trim(($currency ? $currency . ' ' : '') . $formatted);
};

$fmtDate = function ($val) {
    if (empty($val)) return '—';
    $ts = strtotime($val);
    return $ts ? date('d/m/Y', $ts) : $val;
};

$subtotal = isset($invoice['subtotal']) ? (float)$invoice['subtotal'] : 0.0;
$tax = isset($invoice['tax_total']) ? (float)$invoice['tax_total'] : 0.0;
$shipping = isset($invoice['shipping_cost']) ? (float)$invoice['shipping_cost'] : (isset($invoice['shipping_amount']) ? (float)$invoice['shipping_amount'] : 0.0);
if (!$subtotal && !empty($lines)) {
    foreach ($lines as $ln) {
        $subtotal += (float)($ln['line_total'] ?? 0);
    }
}
$total = (float)($invoice['total_amount'] ?? ($subtotal + $tax + $shipping));

$customerLabel = trim($customer['name'] ?? $customer['company_name'] ?? $customer['customer_name'] ?? 'Customer');
$customerLines = array_filter([
    trim($customerAddress['line1'] ?? ''),
    trim($customerAddress['line2'] ?? ''),
    trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? '')),
    !empty($customerAddress['postal_code']) ? 'Postal: ' . trim($customerAddress['postal_code']) : '',
    !empty($customer['phone']) ? 'Phone: ' . trim($customer['phone']) : '',
    !empty($customer['mobile']) ? 'Mobile: ' . trim($customer['mobile']) : '',
    !empty($customer['email']) ? 'Email: ' . trim($customer['email']) : '',
], 'strlen');

$companyLines = array_filter([
    trim($company['tagline'] ?? ''),
    trim($company['address'] ?? ''),
    !empty($company['contact']) ? 'Contact: ' . trim($company['contact']) : '',
    !empty($company['email']) ? 'Email: ' . trim($company['email']) : '',
    !empty($company['phone']) ? 'Phone: ' . trim($company['phone']) : '',
], 'strlen');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            background: #fff;
            color: #111;
        }
        .invoice-shell {
            width: 100%;
            max-width: 860px;
            margin: 0 auto;
            padding: 24px;
            box-sizing: border-box;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
        }
        .logo-area {
            width: 150px;
            height: 120px;
            border: 1px solid #d0d7e2;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
        }
        .logo-area img {
            max-width: 140px;
            max-height: 110px;
            object-fit: contain;
        }
        .logo-placeholder {
            font-size: 13px;
            font-weight: 600;
            color: #64748b;
        }
        .company-info {
            text-align: right;
            line-height: 1.4;
            max-width: 380px;
        }
        .company-info .company-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 6px;
        }
        .company-info .company-line {
            font-size: 12px;
            color: #475569;
        }
        .invoice-meta {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }
        .meta-block {
            width: 48%;
            padding: 12px 14px;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
        }
        .meta-block .label {
            font-size: 10px;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: #94a3b8;
            margin-bottom: 4px;
        }
        .meta-block .value {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
        }
        .customer-block {
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 24px;
            background: #f8fafc;
        }
        .customer-block .title {
            font-size: 10px;
            letter-spacing: 0.3em;
            text-transform: uppercase;
            color: #64748b;
            margin-bottom: 6px;
        }
        .customer-block .customer-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #0f172a;
        }
        .customer-line {
            font-size: 12px;
            color: #1f2933;
            margin-bottom: 2px;
        }
        .lines-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .lines-table th,
        .lines-table td {
            border: 1px solid #cfd5db;
            padding: 8px;
            font-size: 12px;
            vertical-align: top;
        }
        .lines-table th {
            background: #0f172a;
            color: #fff;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.08em;
        }
        .description-cell .product-code {
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .description-cell .product-desc {
            font-size: 12px;
            color: #1f2933;
        }
        .image-cell {
            width: 80px;
            text-align: center;
        }
        .image-cell img {
            max-width: 70px;
            max-height: 38px;
            object-fit: contain;
        }
        .numeric {
            text-align: right;
        }
        .totals-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }
        .totals-table td {
            border: none;
            padding: 6px 8px;
            font-size: 12px;
        }
        .totals-table .label {
            text-align: right;
            color: #475569;
            font-weight: 600;
        }
        .totals-table .amount {
            text-align: right;
            font-weight: 700;
            font-size: 14px;
        }
        .totals-table .total-row .amount {
            font-size: 16px;
        }
        .payment-terms {
            margin-top: 18px;
            font-size: 12px;
            display: flex;
            justify-content: space-between;
        }
        .payment-terms .term-label {
            font-weight: 600;
            color: #0f172a;
        }
    </style>
</head>
<body>
    <div class="invoice-shell">
        <div class="header">
            <div class="logo-area">
                <?php if (!empty($logoSrc)): ?>
                    <img src="<?= esc($logoSrc) ?>" alt="Company Logo">
                <?php else: ?>
                    <span class="logo-placeholder">No Logo</span>
                <?php endif; ?>
            </div>
            <div class="company-info">
                <div class="company-name"><?= esc($company['name'] ?? 'Company Name') ?></div>
                <?php foreach ($companyLines as $line): ?>
                    <div class="company-line"><?= esc($line) ?></div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="invoice-meta">
            <div class="meta-block">
                <div class="label">Invoice</div>
                <div class="value"># <?= esc($invoiceNo ?: '—') ?></div>
            </div>
            <div class="meta-block">
                <div class="label">Dates</div>
                <div class="value">Issue: <?= esc($fmtDate($issueDate)) ?> | Due: <?= esc($fmtDate($dueDate)) ?></div>
            </div>
        </div>

        <div class="customer-block">
            <div class="title">Bill To</div>
            <div class="customer-name"><?= esc($customerLabel) ?></div>
            <?php if (!empty($customerLines)): ?>
                <?php foreach ($customerLines as $line): ?>
                    <div class="customer-line"><?= esc($line) ?></div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="customer-line">Address not available</div>
            <?php endif; ?>
        </div>

        <table class="lines-table">
            <thead>
                <tr>
                    <th style="width:34%">Description</th>
                    <th style="width:14%" class="image-cell">Image</th>
                    <th style="width:12%" class="numeric">Qty</th>
                    <th style="width:14%" class="numeric">Unit Price</th>
                    <th style="width:13%" class="numeric">Taxes</th>
                    <th style="width:13%" class="numeric">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $ln):
                        $qty = (float)($ln['quantity'] ?? 0);
                        $unit = $ln['unit'] ?? ($ln['unit_code'] ?? 'Units');
                        $unitPrice = (float)($ln['unit_price'] ?? 0);
                        $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : ($qty * $unitPrice);
                        $taxAmt = (float)($ln['tax_amount'] ?? ($ln['tax'] ?? 0));
                        $img = $ln['product_image_url'] ?? ($ln['image'] ?? '');
                        $code = trim($ln['product_code'] ?? ($ln['code'] ?? ($ln['sku'] ?? '')));
                        $description = trim(($code ? '[' . $code . '] ' : '') . ($ln['description'] ?? $ln['product_name'] ?? ''));
                    ?>
                        <tr>
                            <td class="description-cell">
                                <?php if ($code !== ''): ?>
                                    <div class="product-code"><?= esc('[' . $code . ']') ?></div>
                                <?php endif; ?>
                                <div class="product-desc"><?= esc($description) ?></div>
                            </td>
                            <td class="image-cell">
                                <?php if (!empty($img)): ?>
                                    <img src="<?= esc($img) ?>" alt="Item">
                                <?php endif; ?>
                            </td>
                            <td class="numeric"><?= number_format($qty, 2) ?> <?= esc($unit) ?></td>
                            <td class="numeric"><?= esc($fmtMoney($unitPrice)) ?></td>
                            <td class="numeric"><?= esc($fmtMoney($taxAmt)) ?></td>
                            <td class="numeric" style="font-weight:700;"><?= esc($fmtMoney($lineTotal)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align:center;">No lines</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <table class="totals-table">
            <tr>
                <td class="label" style="width:84%">Subtotal</td>
                <td class="amount" style="width:16%"><?= esc($fmtMoney($subtotal)) ?></td>
            </tr>
            <tr>
                <td class="label">Tax</td>
                <td class="amount"><?= esc($fmtMoney($tax)) ?></td>
            </tr>
            <?php if ($shipping > 0): ?>
                <tr>
                    <td class="label">Shipping</td>
                    <td class="amount"><?= esc($fmtMoney($shipping)) ?></td>
                </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td class="label" style="font-size:14px;">Total</td>
                <td class="amount" style="font-size:18px;"><?= esc($fmtMoney($total)) ?></td>
            </tr>
        </table>

        <div class="payment-terms">
            <div>
                <div class="term-label">Payment Terms</div>
                <div><?= esc($paymentTerms) ?></div>
            </div>
            <div>
                <div class="term-label">Currency</div>
                <div><?= esc($currency) ?></div>
            </div>
        </div>
    </div>
</body>
</html>
"""
Path(r"c:/xampp/htdocs/corelynk/app/Views/pdf/invoice_system.php").write_text(content, encoding='utf-8')
