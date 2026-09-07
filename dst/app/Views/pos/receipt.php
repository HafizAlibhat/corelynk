<!DOCTYPE html>
<html>
<head>
<style>
    @page { margin: 0; size: 80mm auto; }
    body {
        font-family: 'Courier New', Courier, monospace;
        font-size: 12px;
        width: 72mm;
        margin: 4mm auto;
        color: #000;
        background: #fff;
    }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .line { border-top: 1px dashed #000; margin: 4px 0; }
    .double-line { border-top: 2px solid #000; margin: 4px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { vertical-align: top; }
    .right { text-align: right; }
    .left  { text-align: left; }
    .big { font-size: 16px; font-weight: bold; }
</style>
</head>
<body>
    <div class="center bold big"><?= esc($company['name'] ?? 'Corelynk') ?></div>
    <?php if (!empty($company['tagline'])): ?>
        <div class="center" style="font-size:10px;"><?= esc($company['tagline']) ?></div>
    <?php endif; ?>
    <?php if (!empty($company['address'])): ?>
        <div class="center" style="font-size:10px;"><?= esc($company['address']) ?></div>
    <?php endif; ?>
    <?php if (!empty($company['phone'])): ?>
        <div class="center" style="font-size:10px;">Tel: <?= esc($company['phone']) ?></div>
    <?php endif; ?>

    <div class="line"></div>

    <div style="display:flex;justify-content:space-between;">
        <span>Order: <?= esc($order['order_number'] ?? '') ?></span>
        <span><?= esc(str_replace('_', ' ', $order['order_type'] ?? '')) ?></span>
    </div>
    <div style="font-size:10px;"><?= date('m/d/Y h:i A', strtotime($order['created_at'] ?? 'now')) ?></div>
    <?php if (!empty($order['customer_name']) && $order['customer_name'] !== 'Walk-in'): ?>
        <div>Customer: <?= esc($order['customer_name']) ?></div>
    <?php endif; ?>

    <div class="line"></div>

    <table>
        <thead>
            <tr style="font-weight:bold;border-bottom:1px solid #000;">
                <td class="left">Item</td>
                <td style="text-align:center;">Qty</td>
                <td class="right">Price</td>
                <td class="right">Total</td>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lines as $line): ?>
                <tr>
                    <td style="text-align:left;padding:2px 0;"><?= esc($line['product_name']) ?></td>
                    <td style="text-align:center;padding:2px 4px;"><?= (int)$line['quantity'] ?></td>
                    <td style="text-align:right;padding:2px 0;"><?= number_format((float)$line['unit_price'], 2) ?></td>
                    <td style="text-align:right;padding:2px 0;"><?= number_format((float)$line['line_total'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td class="left">Subtotal</td>
            <td class="right"><?= number_format((float)($order['subtotal'] ?? 0), 2) ?></td>
        </tr>
        <?php if (($order['tax_amount'] ?? 0) > 0): ?>
            <tr>
                <td class="left">Tax (<?= $order['tax_rate'] ?? 0 ?>%)</td>
                <td class="right"><?= number_format((float)$order['tax_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
        <?php if (($order['discount_amount'] ?? 0) > 0): ?>
            <tr>
                <td class="left">Discount</td>
                <td class="right">-<?= number_format((float)$order['discount_amount'], 2) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="double-line"></div>

    <table>
        <tr class="bold">
            <td class="left big">TOTAL</td>
            <td class="right big"><?= number_format((float)($order['total'] ?? 0), 2) ?></td>
        </tr>
    </table>

    <div class="line"></div>

    <table>
        <tr>
            <td class="left">Paid (<?= esc($order['payment_method'] ?? 'cash') ?>)</td>
            <td class="right"><?= number_format((float)($order['amount_paid'] ?? 0), 2) ?></td>
        </tr>
        <?php if (($order['change_due'] ?? 0) > 0): ?>
            <tr>
                <td class="left">Change</td>
                <td class="right"><?= number_format((float)$order['change_due'], 2) ?></td>
            </tr>
        <?php endif; ?>
    </table>

    <div class="line"></div>

    <div class="center" style="font-size:10px;margin-top:8px;">Thank you for your purchase!</div>
    <div class="center" style="font-size:9px;color:#666;">Powered by Corelynk POS</div>
    <br><br>
</body>
</html>
