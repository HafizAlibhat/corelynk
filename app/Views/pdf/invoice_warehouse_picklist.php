<?php
$invoice = $invoice ?? ($payload['invoice'] ?? []);
$lines = $lines ?? ($payload['lines'] ?? []);
$company = $company ?? ($payload['company'] ?? []);

$documentTitle = trim((string)($document_title ?? 'Warehouse Pick List'));
if ($documentTitle === '') {
    $documentTitle = 'Warehouse Pick List';
}

$documentNumberLabel = trim((string)($document_number_label ?? 'Quotation #'));
$documentDateLabel = trim((string)($document_date_label ?? 'Date:'));
$docNumber = trim((string)($invoice['invoice_number'] ?? ($invoice['id'] ?? '')));
$safeDocNumber = preg_replace('/[^A-Za-z0-9\-_]+/', '_', $docNumber) ?? $docNumber;
$safeDocNumber = trim($safeDocNumber, '_');
$downloadTitle = $safeDocNumber !== '' ? ($safeDocNumber . '_WHPL') : 'WHPL';
$issueDateRaw = trim((string)($invoice['issue_date'] ?? ''));
$issueDate = '';
if ($issueDateRaw !== '' && strtotime($issueDateRaw) !== false) {
    $issueDate = date('d-m-Y', strtotime($issueDateRaw));
}

$companyName = trim((string)($company['company_name'] ?? ($company['name'] ?? '')));
$customerNumber = trim((string)($warehouse_customer_number ?? ''));
$printedAt = date('d-m-Y H:i');
$showPrintToolbar = (bool)($show_print_toolbar ?? false);

$fmtQty = static function ($v) {
    return rtrim(rtrim(number_format((float)$v, 2), '0'), '.');
};

$shortLocation = static function (string $loc): string {
    $s = trim($loc);
    if ($s === '') {
        return $s;
    }

    $s = preg_replace('/\bWarehouse\b/i', 'WH', $s) ?? $s;
    $s = preg_replace('/\bLocation\b/i', 'LOC', $s) ?? $s;
    $s = preg_replace('/\bSection\b/i', 'SEC', $s) ?? $s;
    $s = preg_replace('/\bFloor\b/i', 'FL', $s) ?? $s;
    $s = preg_replace('/\bShelf\b/i', 'SH', $s) ?? $s;
    $s = preg_replace('/\bRow\b/i', 'R', $s) ?? $s;
    $s = preg_replace('/\bBin\b/i', 'B', $s) ?? $s;
    $s = preg_replace('/\s*\/\s*/', '/', $s) ?? $s;

    return $s;
};

$defaultImage = base_url('assets/images/no-image.png');
$resolveLineImage = static function (array $line) use ($defaultImage): string {
    $preparedSrc = trim((string)($line['imgSrc'] ?? ''));
    if ($preparedSrc !== '') {
        return $preparedSrc;
    }

    $rawCandidates = [
        ['value' => $line['variant_image_url'] ?? '', 'folder' => 'uploads/variants/'],
        ['value' => $line['product_image_url'] ?? '', 'folder' => 'uploads/products/'],
        ['value' => $line['variant_image'] ?? '', 'folder' => 'uploads/variants/'],
        ['value' => $line['product_image'] ?? '', 'folder' => 'uploads/products/'],
    ];

    foreach ($rawCandidates as $candidate) {
        $raw = trim((string)($candidate['value'] ?? ''));
        $raw = trim((string)$raw);
        if ($raw === '') {
            continue;
        }
        if (preg_match('#^(https?:)?//#i', $raw) || stripos($raw, 'data:') === 0) {
            return $raw;
        }
        if (strpos($raw, '/') !== false || strpos($raw, '\\') !== false) {
            return base_url(ltrim(str_replace('\\', '/', $raw), '/'));
        }
        return base_url(($candidate['folder'] ?? '') . ltrim($raw, '/'));
    }

    $images = $line['product_images'] ?? null;
    if (is_string($images)) {
        $decoded = json_decode($images, true);
        if (is_array($decoded)) {
            $images = $decoded;
        }
    }
    if (is_array($images) && !empty($images[0])) {
        $img = trim((string)$images[0]);
        if ($img !== '') {
            if (preg_match('#^(https?:)?//#i', $img) || stripos($img, 'data:') === 0) {
                return $img;
            }
            if (strpos($img, '/') !== false || strpos($img, '\\') !== false) {
                return base_url(ltrim(str_replace('\\', '/', $img), '/'));
            }
            return base_url('uploads/products/' . ltrim($img, '/'));
        }
    }

    return $defaultImage;
};

$normalizedLines = [];
$shortageLines = [];
$totalLines = 0;
$pickableLines = 0;
$blockedLines = 0;
$totalRequiredQty = 0.0;
$totalAvailableQty = 0.0;

if (!empty($lines) && is_array($lines)) {
    foreach ($lines as $line) {
        $requiredQty = (float)($line['warehouse_required_qty'] ?? ($line['quantity'] ?? 0));
        $availableQty = (float)($line['warehouse_available_qty'] ?? 0);

        $locationList = [];
        if (!empty($line['warehouse_locations']) && is_array($line['warehouse_locations'])) {
            foreach ($line['warehouse_locations'] as $loc) {
                $loc = trim((string)$loc);
                if ($loc !== '') {
                    $locationList[] = $loc;
                }
            }
        }
        if (empty($locationList) && !empty($line['warehouse_locations_text'])) {
            $raw = trim((string)$line['warehouse_locations_text']);
            if ($raw !== '' && strcasecmp($raw, 'Not in stock') !== 0 && strcasecmp($raw, 'Not available in stock') !== 0) {
                $locationList = array_values(array_filter(array_map('trim', explode(',', $raw)), static fn($v) => $v !== ''));
            }
        }
        if (!empty($locationList)) {
            $locationList = array_values(array_unique($locationList));
        }

        $statusText = trim((string)($line['warehouse_status'] ?? 'Not in Stock'));
        $hasLocations = !empty($locationList);
        $isPickable = $hasLocations && strcasecmp($statusText, 'In Stock') === 0;

        $shortQty = max(0.0, $requiredQty - $availableQty);
        $line['_required_qty'] = $requiredQty;
        $line['_available_qty'] = $availableQty;
        $line['_short_qty'] = $shortQty;
        $line['_status'] = $isPickable ? 'Ready to Pick' : 'Stock Blocked';
        $line['_is_pickable'] = $isPickable;
        $line['_locations'] = $locationList;

        $normalizedLines[] = $line;
        if ($shortQty > 0.0001 || !$isPickable) {
            $shortageLines[] = $line;
        }

        $totalLines++;
        $isPickable ? $pickableLines++ : $blockedLines++;
        $totalRequiredQty += $requiredQty;
        $totalAvailableQty += $availableQty;
    }
}
?>
<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title><?= esc($downloadTitle) ?></title>
    <style>
        @page { margin: 12mm; }
        :root {
            --wk-text: #0f172a;
            --wk-muted: #475569;
            --wk-border: #d9e1ea;
            --wk-surface: #f8fafc;
            --wk-brand: #0b3a6f;
            --wk-brand-soft: #e4eefc;
            --wk-ok-bg: #dcfce7;
            --wk-ok-fg: #166534;
            --wk-block-bg: #fee2e2;
            --wk-block-fg: #991b1b;
            --wk-warn-bg: #fff7ed;
            --wk-warn-fg: #9a3412;
        }
        body {
            font-family: Arial, sans-serif;
            color: var(--wk-text);
            font-size: 13px;
            line-height: 1.35;
            background: #f8fafc;
            margin: 0;
            padding: 24px;
        }
        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
        }
        .shell {
            border: 1px solid #dee2e6;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
            box-shadow: none;
        }
        .topbar {
            background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%);
            color: #ffffff;
            border-bottom: 1px solid #0a0f1a;
            padding: 18px 22px 16px;
            position: relative;
            overflow: hidden;
        }
        .topbar::after {
            content: 'WH';
            position: absolute;
            right: -8px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 90px;
            font-weight: 900;
            opacity: 0.04;
            line-height: 1;
            pointer-events: none;
        }
        .topbar-grid {
            width: 100%;
            border-collapse: collapse;
        }
        .topbar-grid td {
            border: none;
            padding: 0;
            vertical-align: top;
            color: #ffffff;
            background: transparent !important;
        }
        .doc-chip {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            background: rgba(255,255,255,.12);
            border: 1px solid rgba(255,255,255,.18);
            border-radius: 999px;
            padding: 3px 9px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .1em;
            text-transform: uppercase;
            color: #93c5fd;
            margin-bottom: 6px;
        }
        /* Order number — hero element */
        .hero-order-number {
            font-size: 38px;
            font-weight: 900;
            letter-spacing: -.02em;
            color: #ffffff;
            line-height: 1.1;
            margin: 0 0 4px;
        }
        /* Total items — 2nd most important */
        .hero-items {
            display: inline-flex;
            align-items: baseline;
            gap: 5px;
            background: rgba(255,255,255,.14);
            border: 1px solid rgba(255,255,255,.22);
            border-radius: 6px;
            padding: 4px 10px;
            margin-top: 4px;
        }
        .hero-items-num {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            line-height: 1;
        }
        .hero-items-label {
            font-size: 11px;
            font-weight: 600;
            color: rgba(255,255,255,.75);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .meta {
            text-align: right;
            font-size: 12px;
            line-height: 1.6;
            color: rgba(255,255,255,.82);
        }
        .meta strong {
            color: #ffffff;
            font-weight: 700;
        }
        .content {
            padding: 0;
        }
        .meta-actions {
            margin-top: 8px;
        }
        .print-btn {
            border: 1px solid rgba(255,255,255,.24);
            background: rgba(255,255,255,.10);
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            border-radius: 7px;
            padding: 6px 12px;
            cursor: pointer;
            line-height: 1.2;
        }
        .print-btn:hover {
            background: rgba(255,255,255,.18);
        }
        .kpi-grid {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
            background: #ffffff;
            border-bottom: 1px solid #dee2e6;
        }
        .kpi-grid td { padding: 0; }
        .kpi {
            padding: 12px 14px;
            border-right: 1px solid #dee2e6;
            background: #ffffff;
        }
        .kpi-grid td:last-child .kpi { border-right: none; }
        .kpi-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: #64748b;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
        }
        .kpi-value.ok { color: #15803d; }
        .kpi-value.block { color: #b91c1c; }
        .stock-note {
            border: 1px solid #fed7aa;
            background: var(--wk-warn-bg);
            border-radius: 8px;
            padding: 9px 12px;
            margin: 12px 18px 14px;
            color: #7c2d12;
            font-size: 12px;
            font-weight: 600;
        }
        .stock-note strong {
            color: var(--wk-warn-fg);
            font-weight: 800;
        }
        .pick-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            table-layout: fixed;
        }
        .pick-table th {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2f7 100%);
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .06em;
            font-weight: 700;
            padding: 12px 10px;
            border-bottom: 2px solid #dbe5f0;
            text-align: left;
            white-space: nowrap;
        }
        .pick-table td {
            border-bottom: 1px solid #eef2f7;
            padding: 14px 10px;
            font-size: 14px;
            vertical-align: top;
            background: #fff;
            overflow-wrap: break-word;
            word-break: normal;
        }
        .row-block td {
            background: #fff7f7 !important;
        }
        .col-num { text-align: center; color: #64748b; }
        .col-img { text-align: center; }
        .col-code {
            text-align: center;
            white-space: nowrap;
            font-size: 12px;
            font-weight: 700;
            color: #0f2745;
            line-height: 1.1;
            overflow: visible;
            text-overflow: clip;
        }
        .col-item {}
        .col-loc {}
        .col-qty { text-align: center; white-space: nowrap; }
        .col-status { text-align: center; }
        .col-pick { text-align: center; }
        .line-thumb {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #dbe5f0;
            box-shadow: 0 2px 6px rgba(15, 23, 42, 0.08);
            background: #fff;
        }
        .item-name {
            font-size: 18px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.5;
            overflow-wrap: anywhere;
            margin-bottom: 4px;
        }
        .item-sub {
            color: var(--wk-muted);
            font-size: 14px;
            line-height: 1.45;
            overflow-wrap: anywhere;
            font-weight: 600;
        }
        .item-cell {
            line-height: 1.3;
            max-height: none;
            overflow: visible;
        }
        .loc {
            margin-bottom: 4px;
            font-size: 12px;
            color: #1f2937;
        }
        .loc-tag {
            display: inline-block;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: .03em;
            color: #1e3a8a;
            background: var(--wk-brand-soft);
            border: 1px solid #bfdbfe;
            border-radius: 999px;
            padding: 2px 7px;
            margin-right: 4px;
        }
        .status {
            display: inline-block;
            min-width: 0;
            max-width: 100%;
            text-align: center;
            border-radius: 999px;
            padding: 2px 6px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .02em;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .status.ok {
            background: var(--wk-ok-bg);
            color: var(--wk-ok-fg);
            border: 1px solid #86efac;
        }
        .status.block {
            background: var(--wk-block-bg);
            color: var(--wk-block-fg);
            border: 1px solid #fca5a5;
        }
        .pick-box {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 1.5px solid #0f2745;
            border-radius: 3px;
            margin-top: 1px;
            background: #fff;
        }
        .empty-msg {
            text-align: center;
            color: #64748b;
            font-size: 12px;
            padding: 18px;
        }
        @media print {
            * { color-adjust: exact !important; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
            body { background: #fff !important; padding: 12mm; color: var(--wk-text) !important; }
            .page-wrap { max-width: 1100px !important; margin: 0 auto !important; }
            .shell { box-shadow: none; border-color: #d7dce5; border-radius: 12px !important; }
            .topbar { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%) !important; color: #fff !important; page-break-inside: avoid !important; }
            .kpi-grid,
            .stock-note,
            .pick-table,
            .pick-table thead,
            .pick-table tbody,
            .pick-table tr,
            .pick-table td,
            .pick-table th { page-break-inside: avoid !important; break-inside: avoid !important; }
            .meta-actions { display: none !important; }
            .content { padding-top: 0; }
            .topbar {
                padding: 16px 20px 14px !important;
            }
            .hero-order-number {
                font-size: 34px !important;
            }
            .hero-items-num {
                font-size: 20px !important;
            }
            .meta {
                font-size: 11px !important;
            }
            .pick-table th { padding: 12px 10px !important; font-size: 10px !important; }
            .pick-table td { padding: 15px 10px !important; font-size: 14px !important; }
            .line-thumb { width: 36px !important; height: 36px !important; }
            .item-name { font-size: 18px !important; line-height: 1.5 !important; }
            .item-sub { font-size: 14px !important; line-height: 1.45 !important; }
        }
    </style>
    <link rel="stylesheet" href="<?= base_url('assets/css/product-image-hover-preview.css') ?>?v=1">
</head>
<body>
    <div class="page-wrap">
    <div class="shell">
        <div class="topbar">
            <table class="topbar-grid">
                <tr>
                    <td style="width:60%">
                        <div class="doc-chip">Warehouse Pick List</div>
                        <div class="hero-order-number"><?= esc($docNumber !== '' ? $docNumber : '-') ?></div>
                        <div style="font-size:12px;color:rgba(255,255,255,.6);margin-bottom:6px;letter-spacing:.02em;"><?= esc($documentNumberLabel ?: 'Document') ?> &mdash; Warehouse Pick Slip</div>
                        <div class="hero-items">
                            <span class="hero-items-num"><?= (int)$totalLines ?></span>
                            <span class="hero-items-label"><?= $totalLines === 1 ? 'Item' : 'Items' ?></span>
                        </div>
                    </td>
                    <td class="meta" style="width:40%">
                        <div><?= esc($documentDateLabel) ?> <strong style="color:#fff"><?= esc($issueDate !== '' ? $issueDate : '-') ?></strong></div>
                        <?php if ($customerNumber !== ''): ?>
                            <div>Customer # <strong style="color:#fff"><?= esc($customerNumber) ?></strong></div>
                        <?php endif; ?>
                        <div>Printed <strong style="color:#fff"><?= esc($printedAt) ?></strong></div>
                        <?php if ($showPrintToolbar): ?>
                            <div class="meta-actions">
                                <button type="button" class="print-btn" onclick="window.print()">&#128438; Print</button>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>

        <div class="content">
            <table class="kpi-grid">
                <tr>
                    <td><div class="kpi"><div class="kpi-label">Total Lines</div><div class="kpi-value"><?= (int)$totalLines ?></div></div></td>
                    <td><div class="kpi"><div class="kpi-label">Ready To Pick</div><div class="kpi-value ok"><?= (int)$pickableLines ?></div></div></td>
                    <td><div class="kpi"><div class="kpi-label">Stock Blocked</div><div class="kpi-value block"><?= (int)$blockedLines ?></div></div></td>
                    <td><div class="kpi"><div class="kpi-label">Required Qty</div><div class="kpi-value"><?= esc($fmtQty($totalRequiredQty)) ?></div></div></td>
                    <td><div class="kpi"><div class="kpi-label">Available Qty</div><div class="kpi-value"><?= esc($fmtQty($totalAvailableQty)) ?></div></div></td>
                </tr>
            </table>

            <?php if ($blockedLines > 0): ?>
                <div class="stock-note">
                    <strong>Stock blocked:</strong> <?= (int)$blockedLines ?> line(s). Review rows marked <strong>Stock Blocked</strong> in the table below.
                </div>
            <?php endif; ?>

            <table class="pick-table">
                <colgroup>
                    <col style="width:4%">
                    <col style="width:8%">
                    <col style="width:16%">
                    <col style="width:34%">
                    <col style="width:18%">
                    <col style="width:10%">
                    <col style="width:8%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="col-num">#</th>
                        <th class="col-img">Image</th>
                        <th class="col-code">Code</th>
                        <th class="col-item">Item / Variant</th>
                        <th class="col-loc">Location(s)</th>
                        <th class="col-qty">Req</th>
                        <th class="col-pick">Pick</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($normalizedLines)): ?>
                        <tr><td colspan="7" class="empty-msg">No lines available</td></tr>
                    <?php else: ?>
                        <?php foreach ($normalizedLines as $idx => $line): ?>
                            <?php
                                $itemTitle = trim((string)($line['product_name'] ?? ''));
                                $itemDescription = trim((string)($line['description'] ?? ''));
                                if ($itemTitle === '') {
                                    $itemTitle = $itemDescription !== '' ? $itemDescription : 'Item';
                                }

                                $detailParts = [];
                                if ($itemDescription !== '') {
                                    $titleNorm = strtolower(trim($itemTitle));
                                    $descNorm = strtolower(trim($itemDescription));
                                    if ($descNorm !== $titleNorm && stripos($descNorm, $titleNorm) === false) {
                                        $detailParts[] = $itemDescription;
                                    }
                                }

                                $attrPairs = [];
                                if (!empty($line['variant_attrs']) && is_array($line['variant_attrs'])) {
                                    foreach ($line['variant_attrs'] as $k => $v) {
                                        $ak = trim((string)$k);
                                        $av = trim((string)$v);
                                        if ($ak !== '' && $av !== '') {
                                            $attrPairs[] = $ak . ': ' . $av;
                                        }
                                    }
                                }
                                if (!empty($attrPairs)) {
                                    $detailParts[] = implode(' | ', $attrPairs);
                                }

                                $locations = $line['_locations'] ?? [];
                                $isPickable = !empty($line['_is_pickable']);
                                $shortQty = (float)($line['_short_qty'] ?? 0);
                                $lineImage = $resolveLineImage($line);
                            ?>
                            <tr class="<?= $isPickable ? 'row-ok' : 'row-block' ?>">
                                <td class="col-num"><?= (int)$idx + 1 ?></td>
                                <td class="col-img"><img src="<?= esc($lineImage) ?>" alt="" class="line-thumb js-product-hover-thumb" data-preview-src="<?= esc($lineImage) ?>" onerror="this.onerror=null;this.src='<?= esc($defaultImage) ?>';this.setAttribute('data-preview-src','<?= esc($defaultImage) ?>');"></td>
                                <td class="col-code"><?= esc((string)($line['code'] ?? ($line['product_code'] ?? '—'))) ?></td>
                                <td class="col-item">
                                    <div class="item-cell">
                                        <div class="item-name"><?= esc($itemTitle) ?></div>
                                        <?php if (!empty($detailParts)): ?>
                                            <div class="item-sub"><?= esc(implode(' | ', $detailParts)) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="col-loc">
                                    <?php if (!empty($locations)): ?>
                                        <?php foreach ($locations as $loc): ?>
                                            <div class="loc"><span class="loc-tag">LOC</span><?= esc($shortLocation((string)$loc)) ?></div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="item-sub" style="color:#b91c1c;font-weight:700;">OOS</div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-qty"><?= esc($fmtQty((float)($line['_required_qty'] ?? 0))) ?></td>
                                <td class="col-pick"><span class="pick-box"></span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>
    <script src="<?= base_url('assets/js/product-image-hover-preview.js') ?>?v=1"></script>
</body>
</html>
