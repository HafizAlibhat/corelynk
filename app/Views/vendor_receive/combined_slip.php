<?php
/**
 * Combined Outwork Manifest & Send Receipt for Sales Order
 * Combines all material lots dispatched to vendor(s) for an order onto a single printable receipt.
 *
 * Expects:
 *   - $order (Sales order array)
 *   - $notes (Array of vendor_send_notes with joined vendor, step, product, locations, and items)
 *   - $distinctVendors (List of vendors involved in this order)
 *   - $selectedVendorId (Optional filter)
 *   - $selectedStatus (Optional filter)
 *   - $company (Company profile array)
 */

$fmt = static fn ($q) => number_format((float) $q, 2);
$company = $company ?? [];
$companyName = ! empty($company['name']) ? $company['name'] : 'Regal Impex';

$companyLogo = null;
if (! empty($company['logo_path'])) {
    $rawPath = ltrim((string) $company['logo_path'], '/\\');
    if (file_exists(FCPATH . $rawPath)) {
        $companyLogo = base_url($rawPath);
    }
}

$totalLots = count($notes);
$totalSentQty = 0.0;
foreach ($notes as $n) {
    if (($n['status'] ?? '') !== 'cancelled') {
        $totalSentQty += (float) ($n['qty'] ?? 0);
    }
}

// Check if all notes belong to a single vendor
$isSingleVendor = (count($distinctVendors) === 1);
$primaryVendor = $isSingleVendor ? reset($distinctVendors) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Combined Vendor Send Note — <?= esc($order['order_number'] ?? '') ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.35;
            color: #1e293b;
            background-color: #f1f5f9;
            min-height: 100vh;
            padding: 16px 12px 28px;
            -webkit-font-smoothing: antialiased;
        }

        /* Screen Action Bar */
        .slip-toolbar {
            max-width: 980px;
            margin: 0 auto 10px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .slip-crumb {
            font-size: 10.5px;
            font-weight: 500;
            color: #64748b;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
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
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .btn-secondary:hover {
            background-color: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-primary {
            background-color: #0f172a;
            color: #ffffff;
            border-color: #0f172a;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.2);
        }

        .btn-primary:hover {
            background-color: #1e293b;
        }

        .filter-select {
            padding: 4px 8px;
            font-size: 10px;
            font-weight: 500;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            background-color: #ffffff;
            color: #334155;
            cursor: pointer;
        }

        /* Compact Paper Box Sheet */
        .slip-sheet {
            max-width: 980px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            padding: 16px 20px;
        }

        /* Clean Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 8px;
            border-bottom: 1.5px solid #e2e8f0;
            margin-bottom: 10px;
            gap: 14px;
        }

        .company-profile {
            max-width: 480px;
        }

        .company-logo {
            max-height: 32px;
            max-width: 130px;
            object-fit: contain;
            margin-bottom: 3px;
            display: block;
        }

        .company-name {
            font-size: 16px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.01em;
            line-height: 1.15;
        }

        .company-sub {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        .doc-info {
            text-align: right;
            min-width: 250px;
        }

        .doc-title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            line-height: 1.15;
        }

        .doc-subtitle {
            font-size: 9px;
            font-weight: 600;
            color: #0284c7;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-top: 2px;
        }

        .doc-ref-box {
            display: inline-block;
            margin-top: 4px;
            background-color: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 3px 8px;
            text-align: right;
        }

        .doc-ref-label {
            display: block;
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1;
            margin-bottom: 2px;
        }

        .doc-ref-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            font-weight: 700;
            color: #0f172a;
        }

        .doc-date-row {
            margin-top: 4px;
            font-size: 9px;
            color: #64748b;
        }

        .doc-date-row strong {
            color: #0f172a;
            font-weight: 700;
        }

        .header-right-wrap {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 12px;
        }

        .header-barcode-qr {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 4px 6px;
        }

        .code-bracket {
            display: inline-block;
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 8.5px;
            font-weight: 800;
            color: #0284c7;
            background: #e0f2fe;
            padding: 1px 4px;
            border-radius: 3px;
            border: 1px solid #bae6fd;
            white-space: nowrap;
        }

        .code-bracket.mat {
            color: #334155;
            background: #f1f5f9;
            border-color: #cbd5e1;
        }

        .mat-prod-line {
            font-size: 9px;
            line-height: 1.3;
            color: #334155;
            word-break: break-word;
        }

        /* 2-Column Metadata Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 10px;
        }

        .meta-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            overflow: hidden;
        }

        .meta-card-header {
            background-color: #f1f5f9;
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 10px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .meta-card-body {
            padding: 6px 10px;
            font-size: 9.5px;
        }

        .meta-row {
            display: flex;
            align-items: baseline;
            margin-bottom: 3px;
            line-height: 1.35;
        }

        .meta-row:last-child {
            margin-bottom: 0;
        }

        .meta-label {
            width: 90px;
            flex-shrink: 0;
            font-size: 8.5px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }

        .meta-value {
            flex: 1;
            font-weight: 500;
            color: #1e293b;
            word-break: break-word;
        }

        .meta-value.strong {
            font-weight: 700;
            color: #0f172a;
        }

        .highlight-chip {
            background-color: #e0f2fe;
            color: #0369a1;
            padding: 1px 6px;
            border-radius: 3px;
            font-weight: 700;
            font-size: 10px;
            font-family: ui-monospace, SFMono-Regular, monospace;
            border: 1px solid #bae6fd;
            display: inline-block;
        }

        /* Combined Manifest Table */
        .table-box {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 10px;
            background: #ffffff;
        }

        .table-box-header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 5px 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }

        .items-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 5px 6px;
            border-bottom: 1.5px solid #cbd5e1;
            text-align: left;
            vertical-align: middle;
        }

        .items-table td {
            padding: 5px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #334155;
            line-height: 1.3;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #fafbfc;
        }

        .items-table .center {
            text-align: center;
        }

        .items-table .num {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .col-idx {
            width: 25px;
            color: #94a3b8;
            font-weight: 600;
        }

        .lot-ref-link {
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 10px;
            font-weight: 800;
            color: #0284c7;
            text-decoration: none;
            display: inline-block;
            background: #f1f5f9;
            padding: 1px 5px;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
            white-space: nowrap;
        }

        .lot-ref-link:hover {
            text-decoration: underline;
            color: #0369a1;
        }

        .lot-date-badge {
            font-weight: 700;
            color: #0f172a;
            white-space: nowrap;
            font-size: 9px;
        }

        .lot-time-sub {
            font-size: 8px;
            color: #64748b;
            display: block;
        }

        .product-title {
            font-size: 10.5px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.25;
            margin-bottom: 2px;
        }

        .product-material {
            font-size: 9px;
            color: #334155;
            line-height: 1.35;
        }

        .product-variant-text {
            color: #0284c7;
            font-weight: 600;
        }

        .sku-code {
            font-family: ui-monospace, SFMono-Regular, monospace;
            font-size: 8.5px;
            background-color: #f1f5f9;
            padding: 1px 4px;
            border-radius: 3px;
            border: 1px solid #e2e8f0;
            color: #334155;
            display: inline-block;
            white-space: nowrap;
        }

        .step-badge {
            display: inline-block;
            background-color: #f0f9ff;
            color: #0369a1;
            font-size: 8.5px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            border: 1px solid #bae6fd;
            white-space: normal;
            line-height: 1.25;
            max-width: 140px;
        }

        .location-badge {
            font-size: 8.5px;
            color: #475569;
            line-height: 1.25;
        }

        .location-badge strong {
            color: #1e293b;
        }

        .qty-val {
            font-weight: 800;
            font-size: 11px;
            color: #0f172a;
        }

        /* Double Check Verification Boxes */
        .verify-cell {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }

        .verify-item {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 7.5px;
            font-weight: 700;
            color: #475569;
            user-select: none;
        }

        .verify-box {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1.5px solid #334155;
            border-radius: 2px;
            background: #ffffff;
            flex-shrink: 0;
        }

        /* Table Summary Footer */
        .items-summary-row {
            background-color: #f8fafc;
            border-top: 1.5px solid #cbd5e1;
            padding: 6px 10px;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            font-size: 10px;
        }

        .summary-total {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .summary-total-qty {
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            background-color: #e2e8f0;
            padding: 2px 8px;
            border-radius: 4px;
        }

        /* Clean Notes Area */
        .notes-box {
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 7px 10px;
            background-color: #fafbfc;
            margin-bottom: 10px;
        }

        .notes-write-area {
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .notes-write-label {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .notes-line-fill {
            flex: 1;
            border-bottom: 1px dashed #cbd5e1;
            height: 16px;
        }

        /* Signatures Box */
        .signatures-box {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 4px;
            margin-bottom: 8px;
        }

        .signature-card {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background: #ffffff;
            overflow: hidden;
        }

        .signature-card-header {
            background-color: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 4px 10px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #334155;
            display: flex;
            justify-content: space-between;
        }

        .signature-lines {
            padding: 7px 10px;
        }

        .sig-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 6px;
            gap: 6px;
        }

        .sig-row:last-child {
            margin-bottom: 0;
        }

        .sig-label {
            font-size: 8.5px;
            font-weight: 600;
            color: #64748b;
            width: 75px;
            flex-shrink: 0;
        }

        .sig-underline {
            flex: 1;
            border-bottom: 1px solid #cbd5e1;
            height: 11px;
        }

        /* Clean Document Footer */
        .doc-footer {
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            margin-top: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8px;
            color: #94a3b8;
        }

        /* Print Media Styles (100% Exact A4 Output) */
        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }

            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 9px !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .slip-toolbar {
                display: none !important;
            }

            .slip-sheet {
                border: none !important;
                box-shadow: none !important;
                max-width: 100% !important;
                padding: 0 !important;
                border-radius: 0 !important;
            }

            .doc-header {
                padding-bottom: 4px !important;
                margin-bottom: 6px !important;
            }

            .meta-grid {
                gap: 6px !important;
                margin-bottom: 6px !important;
            }

            .meta-card, .table-box, .signature-card, .notes-box {
                border-color: #64748b !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .meta-card-header, .table-box-header, .signature-card-header {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .table-box-header {
                background-color: #0f172a !important;
                color: #ffffff !important;
            }

            .items-table {
                font-size: 8.5px !important;
                width: 100% !important;
            }

            .items-table th, .items-table td {
                padding: 3.5px 5px !important;
            }

            .items-table th {
                background-color: #f1f5f9 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .items-table tr {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .verify-box {
                border: 1.5px solid #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .step-badge {
                background-color: #f0f9ff !important;
                border-color: #bae6fd !important;
                color: #0369a1 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Action Bar -->
    <div class="slip-toolbar">
        <div class="toolbar-left">
            <a href="<?= site_url('sales-orders/view/' . (int) $order['id']) ?>" class="btn-action btn-secondary">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Sales Order (<?= esc($order['order_number']) ?>)
            </a>
            <span class="slip-crumb">Combined Outwork Manifest &middot; <?= $totalLots ?> Lots</span>
        </div>

        <div class="toolbar-right">
            <?php if (count($distinctVendors) > 1): ?>
                <select class="filter-select" onchange="location.href=this.value" title="Filter by vendor">
                    <option value="<?= site_url('vendor-receive/order/' . (int)$order['id'] . '/combined-send-slip') ?>">All Vendors (<?= count($distinctVendors) ?>)</option>
                    <?php foreach ($distinctVendors as $v): ?>
                        <option value="<?= site_url('vendor-receive/order/' . (int)$order['id'] . '/combined-send-slip?vendor_id=' . $v['id']) ?>" <?= ((int)$selectedVendorId === (int)$v['id']) ? 'selected' : '' ?>>
                            <?= esc($v['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>

            <a href="<?= site_url('vendor-receive/order/' . (int)$order['id'] . '/lot-stickers') ?>" target="_blank" class="btn-action btn-secondary" title="Print stickers to attach to each pack/bag">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                    <line x1="7" y1="7" x2="7.01" y2="7"></line>
                </svg>
                Print Pack Stickers
            </a>

            <button type="button" onclick="window.print()" class="btn-action btn-primary">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Combined Slip
            </button>
        </div>
    </div>

    <!-- Centered Boxed Document Sheet -->
    <div class="slip-sheet">

        <!-- Header: Company Info Left, Document Title & Order Ref Right -->
        <div class="doc-header">
            <div class="company-profile">
                <?php if ($companyLogo): ?>
                    <img src="<?= esc($companyLogo) ?>" alt="" class="company-logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="company-name"><?= esc($companyName) ?></div>
                <div class="company-sub">Outwork Logistics &middot; Production Dispatch & Processing Manifest</div>
            </div>

            <div class="header-right-wrap">
                <div class="doc-info">
                    <div class="doc-title">Combined Vendor Send Note</div>
                    <div class="doc-subtitle">All Dispatched Lots Manifest</div>
                    <div class="doc-ref-box">
                        <span class="doc-ref-label">Sales Order Reference</span>
                        <span class="doc-ref-code"><?= esc($order['order_number'] ?? '-') ?></span>
                    </div>
                    <div class="doc-date-row">
                        Total Dispatched Lots: <strong><?= $totalLots ?></strong> &middot; Printed: <strong><?= date('d M Y, h:i A') ?></strong>
                    </div>
                </div>

                <div class="header-barcode-qr" title="Encrypted Anti-Tamper Verification QR">
                    <a href="<?= esc($orderVerifyUrl ?? '#') ?>" target="_blank" title="Scan or click to verify order authenticity" style="text-decoration:none;display:inline-block;padding:2px;background:#ffffff;border-radius:4px;">
                        <?= outwork_qr_svg($orderVerifyUrl ?? '', 78) ?>
                    </a>
                    <div style="max-width: 140px; width: 100%; margin-top: 3px;">
                        <?= barcode_code128_svg($order['order_number'] ?? '', 28, 2, true) ?>
                    </div>
                    <span style="font-size: 7px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px;">Scan to Verify</span>
                </div>
            </div>
        </div>

        <!-- 2-Column Metadata Grid -->
        <div class="meta-grid">

            <!-- Card 1: Dispatch & Order Summary -->
            <div class="meta-card">
                <div class="meta-card-header">
                    <span>1. Dispatch & Order Summary</span>
                    <span style="font-size: 8px; font-weight: 700; color: #1d4ed8; background: #eff6ff; padding: 1px 4px; border-radius: 3px; border: 1px solid #bfdbfe;">
                        COMBINED MANIFEST
                    </span>
                </div>
                <div class="meta-card-body">
                    <div class="meta-row">
                        <span class="meta-label">Sales Order</span>
                        <span class="meta-value">
                            <span class="highlight-chip"><?= esc($order['order_number'] ?? 'Direct Work') ?></span>
                            <?php if (! empty($order['order_date'])): ?>
                                <span style="font-size: 9px; color: #64748b; margin-left: 4px;">(<?= date('d M Y', strtotime($order['order_date'])) ?>)</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Total Qty Sent</span>
                        <span class="meta-value strong" style="font-size: 11.5px; color: #0f172a;">
                            <?= $fmt($totalSentQty) ?> PCS
                        </span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Dispatch Mode</span>
                        <span class="meta-value" style="color: #475569;">Vendor Outwork &middot; Combined Batch Transfer</span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Recipient / Vendor Details -->
            <div class="meta-card">
                <div class="meta-card-header">
                    <span>2. Recipient & Vendor Details</span>
                    <?php if ($primaryVendor && ! empty($primaryVendor['code'])): ?>
                        <span style="font-size: 8px; font-weight: 700; color: #475569; background: #e2e8f0; padding: 1px 4px; border-radius: 3px;"><?= esc($primaryVendor['code']) ?></span>
                    <?php elseif (! $isSingleVendor): ?>
                        <span style="font-size: 8px; font-weight: 700; color: #0369a1; background: #e0f2fe; padding: 1px 4px; border-radius: 3px;"><?= count($distinctVendors) ?> Vendors</span>
                    <?php endif; ?>
                </div>
                <div class="meta-card-body">
                    <?php if ($primaryVendor): ?>
                        <div class="meta-row">
                            <span class="meta-label">Vendor</span>
                            <span class="meta-value strong" style="font-size: 11px;"><?= esc($primaryVendor['name']) ?></span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Contact</span>
                            <span class="meta-value"><?= esc($primaryVendor['contact'] ?: '-') ?></span>
                        </div>
                        <?php if (! empty($primaryVendor['phone'])): ?>
                            <div class="meta-row">
                                <span class="meta-label">Phone</span>
                                <span class="meta-value"><?= esc($primaryVendor['phone']) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (! empty($primaryVendor['address'])): ?>
                            <div class="meta-row">
                                <span class="meta-label">Address</span>
                                <span class="meta-value" style="font-size: 9px; color: #475569;"><?= esc($primaryVendor['address']) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="meta-row">
                            <span class="meta-label">Vendors</span>
                            <span class="meta-value strong">Multiple Vendors (<?= count($distinctVendors) ?>)</span>
                        </div>
                        <div class="meta-row">
                            <span class="meta-label">Vendor List</span>
                            <span class="meta-value" style="font-size: 9px;">
                                <?= esc(implode(', ', array_column($distinctVendors, 'name'))) ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Boxed Line Items Manifest Table -->
        <div class="table-box">
            <div class="table-box-header">
                <span>All Dispatched Lots Manifest</span>
                <span style="font-size: 8.5px; opacity: 0.9; font-weight: normal;">Total <?= $fmt($totalSentQty) ?> PCS</span>
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-idx center">#</th>
                        <th style="width: 80px;">Dispatch Date</th>
                        <th style="width: 80px;">Send Ref</th>
                        <th>Dispatched Material (Base Product &amp; Specification)</th>
                        <th style="width: 140px;">Process Step</th>
                        <th style="width: 120px;">Route / Locations</th>
                        <th class="num" style="width: 75px;">Qty Sent</th>
                        <th class="center" style="width: 75px;">Double Check</th>
                    </tr>
                </thead>
                <tbody>
                <?php $idx = 1; foreach ($notes as $note): ?>
                    <?php
                        $dispatchTs = ! empty($note['created_at']) ? strtotime($note['created_at']) : 0;
                        $dispatchDateStr = $dispatchTs ? date('d M Y', $dispatchTs) : '-';
                        $dispatchTimeStr = $dispatchTs ? date('h:i A', $dispatchTs) : '';

                        // Primary item line if present
                        $item0 = $note['items'][0] ?? null;
                        $materialName = $item0['product_name'] ?? ($note['target_product_name'] ?? 'Material');
                        $materialVariant = ! empty($item0['variant_name']) ? $item0['variant_name'] : '';
                        $materialCode = $item0['resolved_code'] ?? ($note['target_product_code'] ?? '-');
                        $unit = $item0['product_unit'] ?? 'PCS';

                        $stepName = $note['step_name'] ?? 'Outwork Processing';
                        $shortRef = $note['short_ref'] ?? ('#' . substr($note['reference_no'] ?? '', -4));
                    ?>
                    <tr>
                        <td class="col-idx center"><?= $idx++ ?></td>

                        <!-- Dispatch Date (Per-Lot Date) -->
                        <td>
                            <span class="lot-date-badge"><?= esc($dispatchDateStr) ?></span>
                            <?php if ($dispatchTimeStr): ?>
                                <span class="lot-time-sub"><?= esc($dispatchTimeStr) ?></span>
                            <?php endif; ?>
                        </td>

                        <!-- Send Ref No (Short, max 6 chars, with link to single send slip) -->
                        <td>
                            <a href="<?= site_url('vendor-receive/' . (int) $note['id'] . '/send-slip') ?>" target="_blank" class="lot-ref-link" title="Full Ref: <?= esc($note['reference_no']) ?>">
                                <?= esc($shortRef) ?>
                            </a>
                            <div style="font-size: 7.5px; color: #64748b; font-family: monospace; margin-top: 2px;" title="<?= esc($note['reference_no']) ?>">
                                <?= esc(substr($note['reference_no'] ?? '', -8)) ?>
                            </div>
                        </td>

                        <!-- Dispatched Base Material Only (No Target Product, No Long Dashes) -->
                        <td>
                            <div class="mat-prod-line">
                                <span class="code-bracket mat">[<?= esc($note['mat_code']) ?>]</span>
                                <strong style="color: #0f172a; margin-left: 2px;">: <?= esc($note['mat_full_name']) ?></strong>
                            </div>
                            <?php if (count($note['items']) > 1): ?>
                                <div style="font-size: 8px; color: #0284c7; margin-top: 2px;">
                                    + <?= (count($note['items']) - 1) ?> additional item(s)
                                </div>
                            <?php endif; ?>
                        </td>

                        <!-- Process Step (Managed across 2 lines) -->
                        <td>
                            <span class="step-badge" title="<?= esc($stepName) ?>">
                                <?= esc($stepName) ?>
                            </span>
                        </td>

                        <!-- Route: From Box & To Location -->
                        <td>
                            <div class="location-badge">
                                <div><span style="color: #64748b;">From:</span> <strong><?= esc($note['from_location_name'] ?? 'Store') ?></strong></div>
                                <div><span style="color: #64748b;">To:</span> <?= esc($note['to_location_name'] ?? 'Vendor Drop-off') ?></div>
                            </div>
                        </td>

                        <!-- Quantity Sent -->
                        <td class="num">
                            <span class="qty-val"><?= $fmt($note['qty'] ?? 0) ?></span>
                            <span style="font-size: 8.5px; color: #64748b; font-weight: 600;"><?= esc($unit) ?></span>
                        </td>

                        <!-- Double Check Physical Checkboxes -->
                        <td class="center">
                            <div class="verify-cell">
                                <span class="verify-item" title="Dispatcher physical count check">
                                    <span class="verify-box"></span> DISP
                                </span>
                                <span class="verify-item" title="Vendor receiving physical count check">
                                    <span class="verify-box"></span> RECV
                                </span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Table Summary / Footer -->
            <div class="items-summary-row">
                <div class="summary-total">
                    <span>Combined Total Quantity Sent:</span>
                    <span class="summary-total-qty"><?= $fmt($totalSentQty) ?> PCS</span>
                </div>
            </div>
        </div>

        <!-- Clean Notes / Remarks Area -->
        <div class="notes-box">
            <div class="notes-write-area">
                <span class="notes-write-label">Notes / Instructions:</span>
                <div class="notes-line-fill"></div>
            </div>
        </div>

        <!-- Signatures Box -->
        <div class="signatures-box">
            <!-- Left: Handed Over By -->
            <div class="signature-card">
                <div class="signature-card-header">
                    <span>1. Handed Over By</span>
                    <span style="color: #64748b; font-size: 8px; font-weight: 600;">DISPATCHER / STORE</span>
                </div>
                <div class="signature-lines">
                    <div class="sig-row">
                        <span class="sig-label">Representative:</span>
                        <div class="sig-underline"></div>
                    </div>
                    <div class="sig-row">
                        <span class="sig-label">Signature:</span>
                        <div class="sig-underline"></div>
                    </div>
                    <div class="sig-row">
                        <span class="sig-label">Date & Time:</span>
                        <div class="sig-underline"></div>
                    </div>
                </div>
            </div>

            <!-- Right: Received By Vendor -->
            <div class="signature-card">
                <div class="signature-card-header">
                    <span>2. Received By Vendor</span>
                    <span style="color: #64748b; font-size: 8px; font-weight: 600;">RECIPIENT / VENDOR</span>
                </div>
                <div class="signature-lines">
                    <div class="sig-row">
                        <span class="sig-label">Received By:</span>
                        <div class="sig-underline"></div>
                    </div>
                    <div class="sig-row">
                        <span class="sig-label">Stamp & Sign:</span>
                        <div class="sig-underline"></div>
                    </div>
                    <div class="sig-row">
                        <span class="sig-label">Date:</span>
                        <div class="sig-underline"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Clean Document Footer -->
        <div class="doc-footer">
            <div>
                Combined Outwork Logistics Manifest &middot; Sales Order: <?= esc($order['order_number'] ?? '') ?> &middot; Printed: <?= date('d M Y H:i:s') ?>
            </div>
            <div>
                <?= $totalLots ?> Lots &middot; Total Quantity: <?= $fmt($totalSentQty) ?>
            </div>
        </div>

    </div>

</body>
</html>
