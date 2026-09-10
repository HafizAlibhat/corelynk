<?php
/**
 * One printable slip for both directions of vendor work: what we handed over
 * and what came back. Expects: $kind, $title, $note, $lines, $rejections.
 */
$fmt = static fn ($q) => number_format((float) $q, 2);
$company = function_exists('company_setting') ? null : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= esc($title) ?> — <?= esc($note['reference_no']) ?></title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #212529; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6c757d; }
        .meta { display: flex; flex-wrap: wrap; gap: 18px 40px; margin: 16px 0 18px; }
        .meta div { min-width: 160px; }
        .meta span { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #dee2e6; padding: 6px 8px; text-align: left; }
        th { background: #f8f9fa; font-size: 12px; }
        td.num, th.num { text-align: right; }
        .sign { margin-top: 48px; display: flex; gap: 60px; }
        .sign div { flex: 1; border-top: 1px solid #adb5bd; padding-top: 6px; font-size: 12px; color: #6c757d; }
        .toolbar { margin-bottom: 12px; }
        @media print { .toolbar { display: none; } body { margin: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print</button>
    </div>

    <h1><?= esc($title) ?></h1>
    <div class="muted"><?= esc($note['reference_no']) ?> &middot; <?= esc($note['created_at']) ?></div>

    <div class="meta">
        <div><span>Vendor</span><?= esc($note['vendor_name'] ?? '-') ?></div>
        <div><span>Step</span><?= esc($note['step_name'] ?? '-') ?></div>
        <div><span>Sales order</span><?= esc($note['order_number'] ?? '-') ?></div>
        <?php if ($kind === 'send'): ?>
            <div><span>From</span><?= esc($note['from_location_name'] ?? '-') ?></div>
            <div><span>To</span><?= esc($note['to_location_name'] ?? '-') ?></div>
        <?php else: ?>
            <div><span>Against send note</span><?= esc($note['send_reference_no'] ?? '-') ?></div>
            <div><span>Originally sent</span><?= $fmt($note['sent_qty'] ?? 0) ?></div>
        <?php endif; ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>Product</th>
                <th>Code</th>
                <?php if ($kind === 'send'): ?>
                    <th class="num">Qty sent</th>
                <?php else: ?>
                    <th class="num">Received</th>
                    <th class="num">Accepted</th>
                    <th class="num">Rejected</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $line): ?>
            <tr>
                <td><?= esc($line['product_name'] ?? '-') ?></td>
                <td><?= esc($line['product_code'] ?? '') ?></td>
                <?php if ($kind === 'send'): ?>
                    <td class="num"><?= $fmt($line['qty']) ?></td>
                <?php else: ?>
                    <td class="num"><?= $fmt($line['qty_received']) ?></td>
                    <td class="num"><?= $fmt($line['qty_accepted']) ?></td>
                    <td class="num"><?= $fmt($line['qty_rejected']) ?></td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($lines)): ?>
            <tr><td colspan="5" class="muted">No lines on this note.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>

    <?php if (! empty($rejections)): ?>
        <h3 style="font-size:14px;margin-top:22px;">Rejected quantity</h3>
        <table>
            <thead>
                <tr><th>Reference</th><th class="num">Qty</th><th>Reason</th><th>Action</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php foreach ($rejections as $rej): ?>
                <tr>
                    <td><?= esc($rej['rejection_ref']) ?></td>
                    <td class="num"><?= $fmt($rej['qty_rejected']) ?></td>
                    <td><?= esc($rej['rejection_reason_text'] ?? '-') ?></td>
                    <td><?= esc($rej['action_type']) ?></td>
                    <td><?= esc(str_replace('_', ' ', $rej['status'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="sign">
        <div><?= $kind === 'send' ? 'Handed over by' : 'Received by' ?></div>
        <div><?= $kind === 'send' ? 'Received by (vendor)' : 'Checked by (QC)' ?></div>
    </div>
</body>
</html>
