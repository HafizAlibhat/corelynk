<?php
/**
 * Professional Boxed Layout Slip for Vendor Outwork & Receiving
 * Used for both:
 *  - Vendor Send Note (Outbound material dispatch to vendor/contractor)
 *  - Vendor Receive Note (Inbound finished/processed goods return with QC)
 *
 * Expects: $kind ('send'|'receive'), $title, $note, $lines, $rejections, $company
 */
$fmt = static fn ($q) => number_format((float) $q, 2);
$company = $company ?? [];
$companyName = ! empty($company['name']) ? $company['name'] : 'Regal Impex';

helper('barcode');
$verifyUrl = $verifyUrl ?? (! empty($note['id']) ? outwork_verify_url('lot', (int)$note['id'], (string)($note['reference_no'] ?? '')) : null);

// Ensure company logo exists on disk before attempting to display, avoiding broken alt text
$companyLogo = null;
if (! empty($company['logo_path'])) {
    $rawPath = ltrim((string) $company['logo_path'], '/\\');
    if (file_exists(FCPATH . $rawPath)) {
        $companyLogo = base_url($rawPath);
    }
}

// Calculate line totals
$totalSentQty = 0.0;
$totalRecvQty = 0.0;
$totalAccQty  = 0.0;
$totalRejQty  = 0.0;

foreach ($lines as $l) {
    if ($kind === 'send') {
        $totalSentQty += (float) ($l['qty'] ?? 0);
    } else {
        $totalRecvQty += (float) ($l['qty_received'] ?? 0);
        $totalAccQty  += (float) ($l['qty_accepted'] ?? 0);
        $totalRejQty  += (float) ($l['qty_rejected'] ?? 0);
    }
}

$status = strtolower($note['status'] ?? ($kind === 'send' ? 'sent' : 'received'));
$statusColors = [
    'draft'     => ['bg' => '#f1f5f9', 'text' => '#475569', 'border' => '#cbd5e1'],
    'sent'      => ['bg' => '#eff6ff', 'text' => '#1d4ed8', 'border' => '#bfdbfe'],
    'received'  => ['bg' => '#ecfdf5', 'text' => '#047857', 'border' => '#a7f3d0'],
    'completed' => ['bg' => '#ecfdf5', 'text' => '#047857', 'border' => '#a7f3d0'],
    'cancelled' => ['bg' => '#fef2f2', 'text' => '#b91c1c', 'border' => '#fecaca'],
];
$statusTheme = $statusColors[$status] ?? ['bg' => '#f8fafc', 'text' => '#334155', 'border' => '#e2e8f0'];

$createdAtRaw = $note['created_at'] ?? '';
$formattedDate = $createdAtRaw ? date('d M Y, h:i A', strtotime($createdAtRaw)) : '-';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title) ?> — <?= esc($note['reference_no'] ?? '') ?></title>
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #1e293b;
            background-color: #f1f5f9;
            min-height: 100vh;
            padding: 16px 12px 28px;
            -webkit-font-smoothing: antialiased;
        }

        /* Screen Action Bar */
        .slip-toolbar {
            max-width: 860px;
            margin: 0 auto 10px auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .toolbar-left, .toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
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

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            padding: 2px 7px;
            border-radius: 9999px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        /* Compact Paper Box */
        .slip-sheet {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.05);
            padding: 18px 22px;
        }

        /* Clean Header */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 10px;
            border-bottom: 1.5px solid #e2e8f0;
            margin-bottom: 12px;
            gap: 14px;
        }

        .company-profile {
            max-width: 440px;
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

        .doc-info {
            text-align: right;
            min-width: 220px;
        }

        .doc-title {
            font-size: 15px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            line-height: 1.15;
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
            font-size: 8px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            line-height: 1;
            margin-bottom: 2px;
        }

        .doc-ref-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 11.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .doc-date-row {
            margin-top: 4px;
            font-size: 9.5px;
            color: #64748b;
        }

        .doc-date-row strong {
            color: #334155;
            font-weight: 600;
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

        /* 2-Column Metadata Grid with Distinct Visual Separation */
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
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
            width: 80px;
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
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .meta-value.strong {
            font-weight: 700;
            color: #0f172a;
        }

        .highlight-chip {
            display: inline-block;
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 1px 6px;
            border-radius: 3px;
            font-weight: 700;
            font-size: 9.5px;
        }

        /* Single-Line Transfer Route Strip with Distinct Separation */
        .route-strip-singleline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 5px 10px;
            margin-bottom: 12px;
            white-space: nowrap;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
        }

        .route-box {
            display: flex;
            align-items: center;
            gap: 6px;
            min-width: 0;
            flex: 1;
        }

        .route-box.route-to {
            justify-content: flex-end;
        }

        .route-tag-pill {
            display: inline-flex;
            align-items: center;
            gap: 3px;
            font-size: 8px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 6px;
            border-radius: 3px;
            flex-shrink: 0;
        }

        .tag-pill-from {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .tag-pill-to {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .route-name {
            font-size: 10px;
            color: #334155;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .route-name strong {
            color: #0f172a;
            font-weight: 700;
        }

        .route-arrow-center {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-shrink: 0;
            padding: 0 6px;
        }

        .route-qty-tag {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 9.5px;
            font-weight: 800;
            color: #0f172a;
            background-color: #f8fafc;
            border: 1px solid #94a3b8;
            padding: 1.5px 7px;
            border-radius: 3px;
            letter-spacing: 0.02em;
        }

        /* Material Manifest Table - Single Line Rows with Breathing Room */
        .table-box {
            border: 1.5px solid #0f172a;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 12px;
            background: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        }

        .table-box-header {
            background-color: #0f172a;
            color: #ffffff;
            padding: 5px 10px;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .items-table th {
            background-color: #f8fafc;
            border-bottom: 1.5px solid #cbd5e1;
            color: #475569;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 10px;
            text-align: left;
            line-height: 1.25;
        }

        .items-table td {
            padding: 4.5px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            color: #1e293b;
            line-height: 1.3;
            white-space: nowrap;
        }

        .items-table tbody tr:last-child td {
            border-bottom: none;
        }

        .items-table tbody tr:nth-child(even) {
            background-color: #fcfdfe;
        }

        .items-table th.num, .items-table td.num {
            text-align: right;
            white-space: nowrap;
        }

        .items-table th.center, .items-table td.center {
            text-align: center;
        }

        .col-idx {
            width: 28px;
            color: #94a3b8;
            font-size: 8.5px;
            font-weight: 600;
        }

        .product-line-cell {
            display: flex;
            align-items: baseline;
            gap: 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .product-title {
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
            flex-shrink: 0;
        }

        .product-variant-inline {
            font-size: 9.5px;
            color: #475569;
            font-weight: 500;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sku-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 9.5px;
            font-weight: 700;
            color: #0f172a;
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            padding: 1.5px 6px;
            border-radius: 3px;
            display: inline-block;
            line-height: 1.2;
        }

        .qty-val {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 12px;
            font-weight: 800;
            color: #0f172a;
            padding-right: 2px;
        }

        .qty-accepted {
            color: #047857;
            font-weight: 800;
        }

        .qty-rejected {
            color: #b91c1c;
            font-weight: 800;
        }

        /* Totals / Summary Row */
        .items-summary-row {
            background-color: #f8fafc;
            border-top: 1.5px solid #0f172a;
            padding: 6px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10px;
        }

        .summary-count {
            color: #475569;
            font-weight: 600;
        }

        .summary-total {
            font-size: 10.5px;
            font-weight: 700;
            color: #0f172a;
        }

        .summary-total-qty {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            margin-left: 4px;
        }

        /* QC Rejections Table */
        .rejection-box {
            border: 1px solid #fecaca;
            border-radius: 5px;
            overflow: hidden;
            margin-bottom: 12px;
            background: #ffffff;
        }

        .rejection-box-header {
            background-color: #fef2f2;
            border-bottom: 1px solid #fecaca;
            padding: 4px 10px;
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #991b1b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .rejection-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5px;
        }

        .rejection-table th {
            background-color: #fff5f5;
            border-bottom: 1px solid #fecaca;
            color: #991b1b;
            font-size: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 4px 10px;
            text-align: left;
        }

        .rejection-table td {
            padding: 4px 10px;
            border-bottom: 1px solid #fee2e2;
            color: #1e293b;
            white-space: nowrap;
        }

        .rejection-table tr:last-child td {
            border-bottom: none;
        }

        /* Notes & Remarks Area (Clean write-in section) */
        .notes-box {
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            background-color: #fafbfc;
            padding: 6px 12px;
            margin-top: 10px;
            margin-bottom: 12px;
        }

        .notes-write-area {
            display: flex;
            align-items: flex-end;
            gap: 6px;
        }

        .notes-write-label {
            font-size: 9px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            flex-shrink: 0;
        }

        .notes-line-fill {
            flex: 1;
            border-bottom: 1px dotted #94a3b8;
            height: 11px;
        }

        /* Signatures Box with Balanced Breathing Room */
        .signatures-box {
            margin-top: 10px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .signature-card {
            border: 1px dashed #cbd5e1;
            border-radius: 5px;
            padding: 7px 12px;
            background-color: #fafbfc;
        }

        .signature-card-header {
            font-size: 8.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #1e293b;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }

        .signature-lines {
            font-size: 9px;
            color: #64748b;
        }

        .sig-row {
            display: flex;
            align-items: flex-end;
            margin-bottom: 5px;
        }

        .sig-row:last-child {
            margin-bottom: 0;
        }

        .sig-label {
            width: 85px;
            flex-shrink: 0;
            color: #64748b;
        }

        .sig-underline {
            flex: 1;
            border-bottom: 1px solid #94a3b8;
            height: 9px;
        }

        /* Clean Document Footer */
        .doc-footer {
            margin-top: 10px;
            padding-top: 5px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 8.5px;
            color: #94a3b8;
        }

        /* Print Media Styles */
        @media print {
            body {
                background: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 9.5px !important;
            }

            .slip-toolbar {
                display: none !important;
            }

            .slip-sheet {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 6mm 8mm !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .items-table, .rejection-table {
                font-size: 9px !important;
            }

            .items-table td, .items-table th {
                padding: 3.5px 8px !important;
            }

            .signatures-box, .notes-box {
                page-break-inside: avoid !important;
            }

            tr {
                page-break-inside: avoid !important;
            }

            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }
        }
    </style>
</head>
<body>

    <!-- Screen Action Header -->
    <div class="slip-toolbar">
        <div class="toolbar-left">
            <button type="button" onclick="history.length > 1 ? history.back() : window.location.href='<?= base_url('vendor-receive') ?>'" class="btn-action btn-secondary">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"></line>
                    <polyline points="12 19 5 12 12 5"></polyline>
                </svg>
                Back to Vendor Outwork
            </button>
            <span class="slip-crumb">Outwork Logistics &middot; <?= esc($note['reference_no'] ?? '') ?></span>
        </div>
        <div class="toolbar-right">
            <span class="status-pill" style="background: <?= $statusTheme['bg'] ?>; color: <?= $statusTheme['text'] ?>; border: 1px solid <?= $statusTheme['border'] ?>;">
                <?= esc(strtoupper($status)) ?>
            </span>
            <?php if (! empty($note['sales_order_id'])): ?>
                <a href="<?= site_url('vendor-receive/order/' . (int) $note['sales_order_id'] . '/combined-send-slip') ?>" class="btn-action btn-secondary" title="View & print combined slip for all lots in this order">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    Combined Order Slip (<?= esc($note['order_number'] ?? 'SO') ?>)
                </a>
                <a href="<?= site_url('vendor-receive/order/' . (int) $note['sales_order_id'] . '/lot-stickers') ?>" target="_blank" class="btn-action btn-secondary" title="Print stickers for each pack/bag">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
                        <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
                        <line x1="7" y1="7" x2="7.01" y2="7"></line>
                    </svg>
                    Print Pack Stickers
                </a>
            <?php endif; ?>
            <button type="button" onclick="window.print()" class="btn-action btn-primary">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="6 9 6 2 18 2 18 9"></polyline>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                    <rect x="6" y="14" width="12" height="8"></rect>
                </svg>
                Print Slip
            </button>
        </div>
    </div>

    <!-- Centered Boxed Document Sheet -->
    <div class="slip-sheet">

        <!-- Header: Company Name Left, Document Title Right -->
        <div class="doc-header">
            <div class="company-profile">
                <?php if ($companyLogo): ?>
                    <img src="<?= esc($companyLogo) ?>" alt="" class="company-logo" onerror="this.style.display='none'">
                <?php endif; ?>
                <div class="company-name"><?= esc($companyName) ?></div>
            </div>

            <div class="header-right-wrap">
                <div class="doc-info">
                    <div class="doc-title"><?= esc($title) ?></div>
                    <div class="doc-ref-box">
                        <span class="doc-ref-label"><?= $kind === 'send' ? 'Send Reference No.' : 'Receive Reference No.' ?></span>
                        <span class="doc-ref-code"><?= esc($note['reference_no'] ?? '-') ?></span>
                    </div>
                    <div class="doc-date-row">
                        Date & Time: <strong><?= esc($formattedDate) ?></strong>
                    </div>
                </div>

                <?php if (! empty($verifyUrl)): ?>
                    <div class="header-barcode-qr" title="Encrypted Anti-Tamper Verification QR">
                        <a href="<?= esc($verifyUrl) ?>" target="_blank" title="Scan or click to verify lot authenticity" style="text-decoration:none;display:inline-block;padding:2px;background:#ffffff;border-radius:4px;">
                            <?= outwork_qr_svg($verifyUrl, 76) ?>
                        </a>
                        <span style="font-size: 7px; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.04em; margin-top: 2px;">Scan to Verify</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 2-Column Metadata Grid -->
        <div class="meta-grid">

            <!-- Card 1: Origin & Dispatch Details -->
            <div class="meta-card">
                <div class="meta-card-header">
                    <span>1. Dispatch & Sender</span>
                    <span class="status-pill" style="background: <?= $statusTheme['bg'] ?>; color: <?= $statusTheme['text'] ?>; border: 1px solid <?= $statusTheme['border'] ?>; font-size: 8px; padding: 0 4px;">
                        <?= esc(strtoupper($status)) ?>
                    </span>
                </div>
                <div class="meta-card-body">
                    <div class="meta-row">
                        <span class="meta-label">Issued By</span>
                        <span class="meta-value strong">Production Store / Outwork Dispatch</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Sales Order</span>
                        <span class="meta-value">
                            <span class="highlight-chip"><?= esc($note['order_number'] ?? 'Direct Work') ?></span>
                        </span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Process Step</span>
                        <span class="meta-value strong" style="color: #0369a1;"><?= esc($note['step_name'] ?? 'Outwork Processing') ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label"><?= $kind === 'send' ? 'Total Qty Sent' : 'Sent Quantity' ?></span>
                        <span class="meta-value strong" style="font-size: 11px; color: #0f172a;">
                            <?= $kind === 'send' ? $fmt($totalSentQty) : $fmt($note['sent_qty'] ?? 0) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Card 2: Recipient & Vendor Details -->
            <div class="meta-card">
                <div class="meta-card-header">
                    <span>2. Recipient & Vendor</span>
                    <?php if (! empty($note['vendor_code'])): ?>
                        <span style="font-size: 8px; font-weight: 700; color: #475569; background: #e2e8f0; padding: 1px 4px; border-radius: 3px;"><?= esc($note['vendor_code']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="meta-card-body">
                    <div class="meta-row">
                        <span class="meta-label">Vendor</span>
                        <span class="meta-value strong" style="font-size: 11px;"><?= esc($note['vendor_name'] ?? '-') ?></span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Contact / Recv</span>
                        <span class="meta-value"><?= esc($note['vendor_contact'] ?? '-') ?></span>
                    </div>
                    <?php if (! empty($note['vendor_phone'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">Phone</span>
                            <span class="meta-value"><?= esc($note['vendor_phone']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if (! empty($note['vendor_address'])): ?>
                        <div class="meta-row">
                            <span class="meta-label">Address</span>
                            <span class="meta-value" style="font-size: 9px; color: #475569;"><?= esc($note['vendor_address']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Single-Line Transfer Route Strip -->
        <div class="route-strip-singleline">
            <!-- Origin Location -->
            <div class="route-box route-from">
                <span class="route-tag-pill tag-pill-from">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                    ORIGIN
                </span>
                <span class="route-name">
                    <strong><?= esc($note['from_warehouse_name'] ? $note['from_warehouse_name'] . ' / ' : '') ?></strong><?= esc($note['from_location_name'] ?? 'WH Store') ?>
                </span>
            </div>

            <!-- In-Transit Indicator -->
            <div class="route-arrow-center">
                <span class="route-qty-tag"><?= $fmt($totalSentQty) ?> <?= esc($lines[0]['product_unit'] ?? 'PCS') ?> SENT</span>
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#0f172a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="4" y1="12" x2="20" y2="12"></line>
                    <polyline points="14 6 20 12 14 18"></polyline>
                </svg>
            </div>

            <!-- Destination Location -->
            <div class="route-box route-to">
                <span class="route-tag-pill tag-pill-to">
                    <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"></path>
                        <circle cx="12" cy="10" r="3"></circle>
                    </svg>
                    DESTINATION
                </span>
                <span class="route-name">
                    <strong><?= esc($note['to_warehouse_name'] ? $note['to_warehouse_name'] . ' / ' : '') ?></strong><?= esc($note['to_location_name'] ?? 'Vendor Processing Area') ?>
                </span>
            </div>
        </div>

        <!-- Boxed Line Items Table - Single Line per Item -->
        <div class="table-box">
            <div class="table-box-header">
                <span>Material / Items Manifest</span>
                <span style="font-size: 8.5px; opacity: 0.85; font-weight: normal;"><?= count($lines) ?> item line<?= count($lines) === 1 ? '' : 's' ?></span>
            </div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="col-idx center">#</th>
                        <th>Product & Specification</th>
                        <th style="width: 130px;">Code / SKU</th>
                        <th class="center" style="width: 55px;">Unit</th>
                        <?php if ($kind === 'send'): ?>
                            <th class="num" style="width: 90px;">Qty Sent</th>
                        <?php else: ?>
                            <th class="num" style="width: 75px;">Received</th>
                            <th class="num" style="width: 75px;">Accepted</th>
                            <th class="num" style="width: 75px;">Rejected</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php $idx = 1; foreach ($lines as $line): ?>
                    <tr>
                        <td class="col-idx center"><?= $idx++ ?></td>
                        <td>
                            <div class="product-line-cell">
                                <span class="product-title"><?= esc($line['product_name'] ?? 'Unnamed Product') ?></span>
                                <?php if (! empty($line['variant_name'])): ?>
                                    <span class="product-variant-inline">&mdash; <?= esc($line['variant_name']) ?></span>
                                <?php elseif (! empty($line['product_desc'])): ?>
                                    <?php
                                        $desc = (string) $line['product_desc'];
                                        $descShort = function_exists('character_limiter') ? character_limiter($desc, 60) : (mb_strlen($desc) > 60 ? mb_substr($desc, 0, 57) . '…' : $desc);
                                    ?>
                                    <span class="product-variant-inline">&mdash; <?= esc($descShort) ?></span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <?php $code = $line['resolved_code'] ?? ($line['product_code'] ?? '-'); ?>
                            <?php if (! empty($code) && $code !== '-'): ?>
                                <span class="sku-code"><?= esc($code) ?></span>
                            <?php else: ?>
                                <span style="color: #94a3b8;">-</span>
                            <?php endif; ?>
                        </td>
                        <td class="center" style="color: #475569; font-weight: 600; font-size: 10px;">
                            <?= esc($line['product_unit'] ?? 'Pcs') ?>
                        </td>
                        <?php if ($kind === 'send'): ?>
                            <td class="num qty-val"><?= $fmt($line['qty'] ?? 0) ?></td>
                        <?php else: ?>
                            <td class="num qty-val"><?= $fmt($line['qty_received'] ?? 0) ?></td>
                            <td class="num qty-val qty-accepted"><?= $fmt($line['qty_accepted'] ?? 0) ?></td>
                            <td class="num qty-val <?= ((float)($line['qty_rejected'] ?? 0) > 0) ? 'qty-rejected' : '' ?>">
                                <?= $fmt($line['qty_rejected'] ?? 0) ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($lines)): ?>
                    <tr>
                        <td colspan="<?= $kind === 'send' ? 5 : 7 ?>" style="text-align: center; color: #94a3b8; padding: 12px;">
                            No item lines recorded on this note.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>

            <!-- Table Summary / Footer -->
            <div class="items-summary-row">
                <div class="summary-count">
                    Manifest Items: <strong><?= count($lines) ?></strong>
                </div>
                <div class="summary-total">
                    <?php if ($kind === 'send'): ?>
                        <span>Total Quantity Sent:</span>
                        <span class="summary-total-qty"><?= $fmt($totalSentQty) ?></span>
                    <?php else: ?>
                        <span>Received: <strong><?= $fmt($totalRecvQty) ?></strong></span>
                        <span style="margin: 0 5px; color: #cbd5e1;">|</span>
                        <span style="color: #047857;">Accepted: <strong><?= $fmt($totalAccQty) ?></strong></span>
                        <span style="margin: 0 5px; color: #cbd5e1;">|</span>
                        <span style="color: <?= $totalRejQty > 0 ? '#b91c1c' : '#64748b' ?>;">Rejected: <strong><?= $fmt($totalRejQty) ?></strong></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Rejected Material Section (if applicable for Receive Slip) -->
        <?php if (! empty($rejections)): ?>
            <div class="rejection-box">
                <div class="rejection-box-header">
                    <span>Quality Control &mdash; Rejected Material Log</span>
                    <span>Total Rejected: <?= $fmt($totalRejQty) ?></span>
                </div>
                <table class="rejection-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th class="num">Qty Rejected</th>
                            <th>Rejection Reason</th>
                            <th>Action / Disposition</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rejections as $rej): ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 600;"><?= esc($rej['rejection_ref']) ?></td>
                            <td class="num qty-rejected"><?= $fmt($rej['qty_rejected']) ?></td>
                            <td><?= esc($rej['rejection_reason_text'] ?? 'Defective / Damaged') ?></td>
                            <td>
                                <span style="background: #fee2e2; color: #991b1b; padding: 1px 4px; border-radius: 3px; font-size: 8px; text-transform: uppercase; font-weight: 600;">
                                    <?= esc(str_replace('_', ' ', $rej['action_type'])) ?>
                                </span>
                            </td>
                            <td style="text-transform: capitalize;"><?= esc(str_replace('_', ' ', $rej['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <!-- Clean Notes / Remarks Area -->
        <div class="notes-box">
            <div class="notes-write-area">
                <span class="notes-write-label">Notes / Remarks:</span>
                <div class="notes-line-fill"></div>
            </div>
        </div>

        <!-- Signatures Box -->
        <div class="signatures-box">
            <!-- Left: Handed Over By -->
            <div class="signature-card">
                <div class="signature-card-header">
                    <span>1. Handed Over By</span>
                    <span style="color: #64748b; font-size: 8px; font-weight: 600;">DISPATCHER</span>
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
                    <span style="color: #64748b; font-size: 8px; font-weight: 600;">RECIPIENT</span>
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
                Outwork Logistics Document &middot; Printed: <?= date('d M Y H:i:s') ?>
            </div>
            <div>
                Document Ref: <?= esc($note['reference_no'] ?? '') ?>
            </div>
        </div>

    </div>

</body>
</html>
