<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111827; }
        .header { border-bottom: 2px solid #1f2937; padding-bottom: 10px; margin-bottom: 12px; }
        .row { width: 100%; clear: both; margin-bottom: 4px; }
        .left { float: left; width: 60%; }
        .right { float: right; width: 38%; text-align: right; }
        h1 { margin: 0; font-size: 20px; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #d1d5db; padding: 6px; vertical-align: top; }
        th { background: #f3f4f6; text-align: left; }
        .text-right { text-align: right; }
        .totals { margin-top: 10px; width: 40%; margin-left: auto; }
        .badge { display: inline-block; padding: 2px 6px; border: 1px solid #111827; font-size: 10px; }
        .footer { margin-top: 30px; font-size: 11px; color: #374151; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
<div class="header">
    <div class="row">
        <div class="left">
            <h1>CUSTOMS INVOICE</h1>
            <div class="muted">Corelynk Export Documentation</div>
        </div>
        <div class="right">
            <div><strong>No:</strong> <?= esc((string)($doc['customs_invoice_no'] ?? '')) ?></div>
            <div><strong>Status:</strong> <span class="badge"><?= esc((string)($doc['status'] ?? 'DRAFT')) ?></span></div>
            <div><strong>Mode:</strong> <?= esc((string)($doc['mode'] ?? 'VALUE_ONLY')) ?></div>
            <div><strong>Generated:</strong> <?= esc((string)($generated_at ?? date('Y-m-d H:i:s'))) ?></div>
        </div>
    </div>
</div>

<div class="row">
    <div class="left">
        <strong>Customer</strong><br>
        <?= esc((string)($doc['customer_name'] ?? '-')) ?><br>
        <?= esc((string)($doc['customer_email'] ?? '')) ?>
    </div>
    <div class="right">
        <strong>Shipment</strong><br>
        Tracking: <?= esc((string)($doc['tracking_no'] ?? '-')) ?><br>
        Currency: <?= esc((string)($doc['currency_code'] ?? 'USD')) ?>
    </div>
</div>

<table>
    <thead>
    <tr>
        <th style="width:5%">#</th>
        <th style="width:45%">Description</th>
        <th style="width:10%">Qty</th>
        <th style="width:10%">UOM</th>
        <th style="width:15%" class="text-right">Unit Price</th>
        <th style="width:15%" class="text-right">Line Total</th>
    </tr>
    </thead>
    <tbody>
    <?php $items = is_array($doc['items'] ?? null) ? $doc['items'] : []; ?>
    <?php if (empty($items)): ?>
        <tr><td colspan="6" class="text-right">No items</td></tr>
    <?php else: ?>
        <?php foreach ($items as $idx => $line): ?>
            <tr>
                <td><?= (int)$idx + 1 ?></td>
                <td><?= esc((string)($line['custom_description'] ?? '')) ?></td>
                <td><?= esc((string)number_format((float)($line['declared_qty'] ?? 0), 2)) ?></td>
                <td><?= esc((string)($line['uom'] ?? '')) ?></td>
                <td class="text-right"><?= esc((string)number_format((float)($line['declared_unit_price'] ?? 0), 2)) ?></td>
                <td class="text-right"><?= esc((string)number_format((float)($line['declared_line_total'] ?? 0), 2)) ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<table class="totals">
    <tr>
        <th>Declared Total</th>
        <td class="text-right"><?= esc((string)number_format((float)($doc['declared_total'] ?? 0), 2)) ?> <?= esc((string)($doc['currency_code'] ?? 'USD')) ?></td>
    </tr>
</table>

<div class="footer">
    <?php if (($variant ?? 'preview') === 'preview'): ?>
        <strong>DRAFT PREVIEW</strong> - Not for final customs submission.
    <?php else: ?>
        Final customs invoice document.
    <?php endif; ?>
</div>
</body>
</html>
