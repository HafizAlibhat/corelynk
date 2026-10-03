<?php
$companyName = ! empty($company['name']) ? $company['name'] : 'Regal Impex';
$isValid = $isValid ?? false;
$fmt = static fn ($q) => number_format((float) $q, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isValid ? 'Verified Outwork Document' : 'Invalid Verification Token' ?> — CoreLynk</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }
        .verify-card {
            background: #ffffff;
            width: 100%;
            max-width: 540px;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            border: 1px solid #334155;
        }
        .verify-header {
            padding: 20px 24px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .badge-verified {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #dcfce7;
            color: #15803d;
            border: 1.5px solid #86efac;
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .badge-invalid {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1.5px solid #fca5a5;
            padding: 5px 14px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 10px;
        }
        .company-title {
            font-size: 17px;
            font-weight: 800;
            color: #0f172a;
        }
        .doc-subtitle {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }
        .verify-body {
            padding: 20px 24px;
        }
        .data-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
        }
        .data-row:last-child { border-bottom: none; }
        .data-label {
            color: #64748b;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            flex-shrink: 0;
            width: 130px;
        }
        .data-value {
            color: #0f172a;
            font-weight: 600;
            text-align: right;
            word-break: break-word;
            flex: 1;
        }
        .code-chip {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 2px 7px;
            border-radius: 4px;
            font-family: ui-monospace, monospace;
            font-size: 11.5px;
            font-weight: 700;
            color: #0284c7;
        }
        .step-pill {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
        }
        .verify-footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 14px 24px;
            text-align: center;
            font-size: 10.5px;
            color: #64748b;
        }
        .btn-erp {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 11.5px;
            margin-top: 10px;
            transition: background 0.15s;
        }
        .btn-erp:hover { background-color: #1e293b; }
    </style>
</head>
<body>

    <div class="verify-card">
        <div class="verify-header">
            <?php if ($isValid): ?>
                <div class="badge-verified">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    Authentic Verified Outwork Record
                </div>
                <div class="company-title"><?= esc($companyName) ?></div>
                <div class="doc-subtitle">Encrypted Dispatch Verification System</div>
            <?php else: ?>
                <div class="badge-invalid">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                    Verification Failed
                </div>
                <div class="company-title"><?= esc($companyName) ?></div>
                <div class="doc-subtitle"><?= esc($message ?? 'Invalid or Expired Token') ?></div>
            <?php endif; ?>
        </div>

        <?php if ($isValid && ($type ?? '') === 'lot'): ?>
            <?php
                $ref = $note['reference_no'] ?? '';
                $refParts = explode('-', $ref);
                $shortRef = '#' . strtoupper(substr(end($refParts), 0, 5));
                $tCode = ! empty($note['target_variant_art']) ? $note['target_variant_art'] : (! empty($note['target_product_code']) ? $note['target_product_code'] : ($note['target_product_sku'] ?? '-'));
                $tName = $note['target_product_name'] ?? 'Target Product';
                if (! empty($note['target_variant_name'])) {
                    $tFullName = $tName . ' (' . $note['target_variant_name'] . ')';
                } else {
                    $tFullName = $tName;
                }

                $item0 = $items[0] ?? null;
                $mCode = $item0['variant_art_number'] ?? ($item0['product_code'] ?? ($item0['product_sku'] ?? '-'));
                $mName = $item0['product_name'] ?? 'Material';
                if (! empty($item0['variant_name'])) {
                    $mFullName = $mName . ' (' . $item0['variant_name'] . ')';
                } else {
                    $mFullName = $mName;
                }
            ?>
            <div class="verify-body">
                <div class="data-row">
                    <span class="data-label">Lot Reference</span>
                    <span class="data-value">
                        <span class="code-chip"><?= esc($shortRef) ?></span>
                        <span style="font-size: 10px; color: #64748b; margin-left: 4px;"><?= esc($ref) ?></span>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Sales Order</span>
                    <span class="data-value" style="font-weight: 700; color: #0f172a;">
                        <?= esc($note['order_number'] ?? '-') ?>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Target Product</span>
                    <span class="data-value">
                        <div style="font-weight: 700; color: #0284c7;">[<?= esc($tCode) ?>]</div>
                        <div><?= esc($tFullName) ?></div>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Dispatched Material</span>
                    <span class="data-value">
                        <div style="font-weight: 700; color: #334155;">[<?= esc($mCode) ?>]</div>
                        <div><?= esc($mFullName) ?></div>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Process Step</span>
                    <span class="data-value">
                        <span class="step-pill"><?= esc($note['step_name'] ?? 'Outwork Processing') ?></span>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Quantity Sent</span>
                    <span class="data-value" style="font-size: 14px; font-weight: 800; color: #0f172a;">
                        <?= $fmt($note['qty'] ?? 0) ?> PCS
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Vendor</span>
                    <span class="data-value"><?= esc($note['vendor_name'] ?? 'Vendor') ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Dispatched Date</span>
                    <span class="data-value"><?= ! empty($note['created_at']) ? date('d M Y, h:i A', strtotime($note['created_at'])) : '-' ?></span>
                </div>
            </div>

            <div class="verify-footer">
                <div>Cryptographically verified by CoreLynk Anti-Tamper Engine</div>
                <a href="<?= site_url('sales-orders/view/' . (int)($note['so_id'] ?? 0)) ?>" class="btn-erp">
                    Open in CoreLynk ERP
                </a>
            </div>

        <?php elseif ($isValid && ($type ?? '') === 'order'): ?>
            <div class="verify-body">
                <div class="data-row">
                    <span class="data-label">Sales Order</span>
                    <span class="data-value" style="font-size: 14px; font-weight: 800; color: #0f172a;">
                        <?= esc($order['order_number'] ?? '-') ?>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Total Outwork Lots</span>
                    <span class="data-value">
                        <span class="code-chip"><?= count($notes ?? []) ?> Dispatched Lots</span>
                    </span>
                </div>
                <div class="data-row">
                    <span class="data-label">Order Date</span>
                    <span class="data-value"><?= ! empty($order['order_date']) ? date('d M Y', strtotime($order['order_date'])) : '-' ?></span>
                </div>
                <div class="data-row">
                    <span class="data-label">Status</span>
                    <span class="data-value">
                        <span class="step-pill"><?= strtoupper(esc($order['status'] ?? 'IN PROGRESS')) ?></span>
                    </span>
                </div>
            </div>

            <div class="verify-footer">
                <div>Cryptographically verified by CoreLynk Anti-Tamper Engine</div>
                <a href="<?= site_url('sales-orders/view/' . (int)$order['id']) ?>" class="btn-erp">
                    Open Sales Order in ERP
                </a>
            </div>
        <?php else: ?>
            <div class="verify-body" style="text-align: center; color: #64748b; padding: 30px 20px;">
                <p>This verification link is invalid, expired, or has been modified. Please re-scan the original QR code on the physical document or pack sticker.</p>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
