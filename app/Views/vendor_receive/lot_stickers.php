<?php
/**
 * Printable Lot / Pack Stickers for Vendor Outwork
 * Designed to be printed and attached to individual polybags/packs/boxes.
 * Focuses on Quantity (PCS), Base Product Specification, Process Step, and Mobile QR Verification.
 *
 * Expects: $order, $notes, $company
 */
$companyName = ! empty($company['name']) ? $company['name'] : 'Regal Impex';
$soNumber = $order['order_number'] ?? 'SO';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lot Pack Stickers — <?= esc($soNumber) ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f1f5f9;
            color: #0f172a;
            font-size: 10px;
            line-height: 1.35;
            padding: 16px 12px 28px;
            -webkit-font-smoothing: antialiased;
        }

        .stickers-toolbar {
            max-width: 880px;
            margin: 0 auto 14px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 4px;
            border: 1px solid transparent;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-secondary {
            background-color: #ffffff;
            color: #475569;
            border-color: #cbd5e1;
        }

        .btn-secondary:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .btn-primary {
            background-color: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
        }

        .btn-primary:hover {
            background-color: #1e293b;
        }

        /* Stickers Grid */
        .stickers-container {
            max-width: 880px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        /* Individual Pack Sticker */
        .pack-sticker {
            background: #ffffff;
            border: 1.5px dashed #334155;
            border-radius: 6px;
            padding: 10px 12px;
            position: relative;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 168px;
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Top Header */
        .sticker-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 8px;
        }

        .sticker-company {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .company-name {
            font-size: 9.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #0f172a;
        }

        .badge-sub {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            background: #f1f5f9;
            padding: 1px 4px;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .sticker-so {
            font-size: 9.5px;
            font-weight: 700;
            color: #0369a1;
            background: #e0f2fe;
            border: 1px solid #bae6fd;
            padding: 1px 6px;
            border-radius: 3px;
        }

        /* Hero Row: Prominent Quantity + Lot Ref on Left, QR Code on Right */
        .sticker-hero-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .sticker-hero-left {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Focus: Eye-Catching Quantity Box */
        .sticker-qty-box {
            background: #0f172a;
            color: #ffffff;
            padding: 5px 10px;
            border-radius: 5px;
            display: flex;
            align-items: baseline;
            gap: 4px;
            white-space: nowrap;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.15);
        }

        .sticker-qty-num {
            font-size: 24px;
            font-weight: 900;
            line-height: 1;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
        }

        .sticker-qty-unit {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #94a3b8;
        }

        .sticker-lot-info {
            display: flex;
            flex-direction: column;
            gap: 1px;
        }

        .sticker-lot-badge {
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 14px;
            font-weight: 900;
            color: #0f172a;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 1px 6px;
            display: inline-block;
            letter-spacing: 0.02em;
        }

        .sticker-lot-full {
            font-size: 7.5px;
            color: #64748b;
            font-family: ui-monospace, SFMono-Regular, monospace;
        }

        /* QR Code Container on Right */
        .sticker-qr-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex-shrink: 0;
            text-align: center;
        }

        .sticker-qr-link {
            text-decoration: none;
            display: inline-block;
            padding: 2px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
        }

        .sticker-qr-caption {
            font-size: 6.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 2px;
        }

        /* Process Step / Color Highlight */
        .sticker-step-highlight {
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            padding: 3px 7px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 7px;
            line-height: 1.25;
        }

        .step-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #16a34a;
            flex-shrink: 0;
        }

        /* Focus: Product Name & Variant Specification Box */
        .sticker-product-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 8px;
        }

        .product-header {
            display: flex;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 4px;
        }

        .code-tag {
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 8.5px;
            font-weight: 800;
            padding: 1px 4px;
            border-radius: 3px;
            white-space: nowrap;
        }

        .code-tag.mat {
            color: #334155;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
        }

        .product-title {
            font-size: 11.5px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1.25;
            word-break: break-word;
        }

        .product-variant-specs {
            font-size: 9.5px;
            font-weight: 600;
            color: #475569;
            margin-top: 2px;
            line-height: 1.25;
            word-break: break-word;
        }

        /* Footer: Vendor & Dispatch Date */
        .sticker-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 4px;
            font-size: 8.5px;
            color: #64748b;
        }

        .sticker-meta-item {
            display: flex;
            align-items: center;
            gap: 3px;
        }

        .meta-label {
            color: #94a3b8;
            font-size: 8px;
            text-transform: uppercase;
        }

        .sticker-vendor {
            font-weight: 700;
            color: #1e293b;
        }

        .meta-val {
            font-weight: 700;
            color: #0f172a;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .stickers-toolbar {
                display: none !important;
            }

            .stickers-container {
                max-width: 100% !important;
                gap: 5mm 5mm !important;
            }

            .pack-sticker {
                border: 1px dashed #000000 !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .sticker-qty-box {
                background: #000000 !important;
                color: #ffffff !important;
            }

            .sticker-step-highlight {
                background: #f0fdf4 !important;
                border-color: #86efac !important;
                color: #166534 !important;
            }
        }
    </style>
</head>
<body>

    <div class="stickers-toolbar">
        <div style="display: flex; align-items: center; gap: 8px;">
            <a href="<?= site_url('vendor-receive/order/' . (int)$order['id'] . '/combined-send-slip') ?>" class="btn-action btn-secondary">
                &larr; Back to Combined Slip
            </a>
            <span style="font-weight: 600; color: #475569;">
                Pack Stickers &middot; <?= count($notes) ?> Lot<?= count($notes) === 1 ? '' : 's' ?>
            </span>
        </div>
        <button type="button" onclick="window.print()" class="btn-action btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                <polyline points="6 9 6 2 18 2 18 9"></polyline>
                <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                <rect x="6" y="14" width="12" height="8"></rect>
            </svg>
            Print Pack Stickers
        </button>
    </div>

    <div class="stickers-container">
        <?php foreach ($notes as $note): ?>
            <?php
                $item0 = $note['items'][0] ?? null;
                $unit = $item0['product_unit'] ?? 'PCS';
                $dispatchDate = ! empty($note['created_at']) ? date('d M Y', strtotime($note['created_at'])) : '-';
                $shortRef = $note['short_ref'] ?? ('#' . substr($note['reference_no'] ?? '', -4));
                $verifyUrl = $note['verify_url'] ?? outwork_verify_url('lot', (int)$note['id'], (string)$note['reference_no']);

                $productTitle = $item0['product_name'] ?? ($note['mat_full_name'] ?? 'Material');
                $variantSpecs = ! empty($item0['variant_name']) ? $item0['variant_name'] : '';
            ?>
            <div class="pack-sticker">
                <!-- Top Header: Company + SO Reference -->
                <div class="sticker-top">
                    <div class="sticker-company">
                        <span class="company-name"><?= esc($companyName) ?></span>
                        <span class="badge-sub">Pack Sticker</span>
                    </div>
                    <span class="sticker-so">SO: <?= esc($soNumber) ?></span>
                </div>

                <!-- Core Focus: Big PCS Quantity Badge + Lot Ref on Left, Offline Vector QR on Right -->
                <div class="sticker-hero-row">
                    <div class="sticker-hero-left">
                        <div class="sticker-qty-box" title="Quantity in this pack">
                            <span class="sticker-qty-num"><?= number_format((float)($note['qty'] ?? 0), 0) ?></span>
                            <span class="sticker-qty-unit"><?= esc($unit) ?></span>
                        </div>
                        <div class="sticker-lot-info">
                            <span class="sticker-lot-badge"><?= esc($shortRef) ?></span>
                            <span class="sticker-lot-full"><?= esc($note['reference_no']) ?></span>
                        </div>
                    </div>

                    <!-- Encrypted QR Code for Mobile Verification (Vector SVG) -->
                    <div class="sticker-qr-box" title="Scan with mobile to verify lot authenticity">
                        <a href="<?= esc($verifyUrl) ?>" target="_blank" class="sticker-qr-link">
                            <?= outwork_qr_svg($verifyUrl, 68) ?>
                        </a>
                        <span class="sticker-qr-caption">Scan to Verify</span>
                    </div>
                </div>

                <!-- Process Step / Color Treatment Highlight -->
                <div class="sticker-step-highlight">
                    <span class="step-dot"></span>
                    <span><?= esc($note['step_name'] ?? 'Outwork Processing') ?></span>
                </div>

                <!-- Main Focus: Product Name & Variant Specification (No Long Dashes) -->
                <div class="sticker-product-box">
                    <div class="product-header">
                        <span class="code-tag mat">[<?= esc($note['mat_code']) ?>]</span>
                        <span class="product-title"><?= esc($productTitle) ?></span>
                    </div>
                    <?php if (! empty($variantSpecs)): ?>
                        <div class="product-variant-specs">
                            <?= esc($variantSpecs) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Footer: Vendor & Dispatch Date -->
                <div class="sticker-bottom">
                    <div class="sticker-meta-item">
                        <span class="meta-label">Vendor:</span>
                        <span class="sticker-vendor"><?= esc($note['vendor_name'] ?? 'Vendor') ?></span>
                    </div>
                    <div class="sticker-meta-item">
                        <span class="meta-label">Date:</span>
                        <span class="meta-val"><?= esc($dispatchDate) ?></span>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</body>
</html>
