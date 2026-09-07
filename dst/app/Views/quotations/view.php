<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Quotation <?= esc($quote['quote_number']) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $quoteStatusRaw = trim((string)($quote['status'] ?? ''));
    if ($quoteStatusRaw === '') $quoteStatusRaw = 'draft';
    $quoteStatus = strtolower($quoteStatusRaw);
    $isConvertedToSalesOrder = !empty($quote['converted_to_sales_order_id']);
    $statusLabel = $isConvertedToSalesOrder ? 'converted' : $quoteStatusRaw;
    $statusClass = ($quoteStatus === 'draft' && !$isConvertedToSalesOrder) ? 'bg-secondary' : 'bg-success';
?>
<div class="card cl-detail-page">
    <?php 
        $candidateDates = [
            $quote['issue_date'] ?? '',
            $quote['quote_date'] ?? '',
            $quote['created_at'] ?? '',
        ];
        $fmtDate = '';
        foreach ($candidateDates as $d) {
            if (!empty($d) && strtotime($d) !== false) {
                $ts = strtotime($d);
                $year = (int)date('Y', $ts);
                if ($year >= 1900) {
                    $fmtDate = date('d-m-Y', $ts);
                    break;
                }
            }
        }
        $customerLabel = '';
        if (!empty($customer['customer_code']) && !empty($customer['name'])) {
            $customerLabel = $customer['customer_code'] . ' - ' . $customer['name'];
        } elseif (!empty($customer['name'])) {
            $customerLabel = $customer['name'];
        } elseif (!empty($quote['customer_code']) && !empty($quote['customer_name'])) {
            $customerLabel = $quote['customer_code'] . ' - ' . $quote['customer_name'];
        } elseif (!empty($quote['customer_name'])) {
            $customerLabel = $quote['customer_name'];
        } else {
            $customerLabel = '';
        }
        // The address is frozen on the quotation; it can be refreshed from the
        // customer profile until the quotation becomes a sales order.
        $canRefreshAddress = !$isConvertedToSalesOrder && in_array($quoteStatus, ['', 'draft', 'sent'], true);
        // Deep-link to the customer profile from the address panel.
        helper('security');
        $customerRouteId = function_exists('entityRouteIdentifier')
            ? entityRouteIdentifier(is_array($customer ?? null) ? $customer : null)
            : (string)($customer['id'] ?? '');
    ?>
    <div class="card-header section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="section-title mb-0">Quotation <?= esc($quote['quote_number']) ?></h3>
            <div class="section-sub"></div>
        </div>
        <div class="d-flex flex-column align-items-end gap-1 ms-auto">
            <div class="d-flex align-items-center justify-content-end flex-wrap gap-2">
                <span class="badge <?= $statusClass ?>" style="text-transform:uppercase; min-width:120px; text-align:center;">Status: <?= esc($statusLabel) ?></span>
                <?= view('partials/record_nav', [
                    'table'   => 'quotations',
                    'id'      => $quote['public_id'] ?? $quote['id'],
                    'pattern' => 'quotations/view/{id}',
                    'list'    => 'quotations',
                ]) ?>
                <?php if (!$isConvertedToSalesOrder): ?>
                    <a href="<?= site_url('quotations/pdf/' . (int)$quote['id']) ?>" class="btn btn-outline-danger btn-sm" title="Download Quotation PDF"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
                    <a href="<?= site_url('quotations/print/' . (!empty($quote['public_id']) ? $quote['public_id'] : (int)$quote['id'])) ?>" class="btn btn-outline-light btn-sm" title="Print Quotation" target="_blank" rel="noopener"><i class="bi bi-printer me-1"></i>Print</a>
                    <a href="<?= site_url('quotations/warehouse-document/' . (int)$quote['id']) ?>" class="btn btn-outline-warning btn-sm" title="Open Warehouse Pick Slip" target="_blank" rel="noopener"><i class="bi bi-box-seam me-1"></i>Warehouse Pick Slip</a>
                    <a href="<?= site_url('quotations/edit/' . (int)$quote['id']) ?>" class="btn btn-outline-primary btn-sm" title="Edit Quotation"><i class="bi bi-pencil-square me-1"></i>Edit</a>
                    <a href="<?= site_url('document-studio?edit=quotation&id='.(int)$quote['id']) ?>" class="btn btn-outline-primary btn-sm" title="Edit in Document Studio"><i class="bi bi-easel me-1"></i>Edit in Studio</a>
                <?php endif; ?>
                <a href="<?= site_url('quotations') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
                <?php if ($isConvertedToSalesOrder): ?>
                    <a href="<?= site_url('sales-orders/view/'.(int)$quote['converted_to_sales_order_id']) ?>" class="btn btn-success btn-sm">View Sales Order</a>
                <?php else: ?>
                    <a href="<?= site_url('sales-orders/create-from-quotation/'.$quote['id']) ?>" class="btn btn-primary btn-sm">Convert to Sales Order</a>
                <?php endif; ?>
            </div>
            <div class="text-muted" style="font-size:0.85rem;"></div>
        </div>
    </div>
    <div class="card-body">
        <style>
            .quote-lines-table th,
            .quote-lines-table td {
                white-space: nowrap;
                vertical-align: middle;
            }
            .quote-lines-table .line-desc {
                max-width: 420px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .quote-lines-table .line-code {
                max-width: 120px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .doc-discount-panel {
                border: 1px solid rgba(148, 163, 184, 0.25);
                border-radius: 10px;
                padding: 10px 10px 8px;
                background: rgba(15, 23, 42, 0.28);
            }
            .doc-discount-title {
                font-size: 0.78rem;
                letter-spacing: 0.03em;
                text-transform: uppercase;
                color: #94a3b8;
                margin-bottom: 8px;
            }
            .line-disc-percent {
                min-width: 120px;
            }
            .quote-summary-card {
                min-width: 320px;
                max-width: 360px;
                border: 1px solid rgba(148, 163, 184, 0.18);
                border-radius: 12px;
                background: linear-gradient(160deg, rgba(15, 23, 42, 0.62), rgba(15, 23, 42, 0.28));
                padding: 10px 12px 12px;
            }
            .summary-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                padding: 6px 0;
                border-bottom: 1px solid rgba(148, 163, 184, 0.12);
            }
            .summary-row:last-of-type {
                border-bottom: 0;
            }
            .summary-label {
                color: #94a3b8;
                font-size: 0.83rem;
            }
            .summary-value {
                font-weight: 600;
                font-size: 0.9rem;
                color: #e2e8f0;
            }
            .summary-total-row {
                margin-top: 6px;
                padding: 10px 0 6px;
                border-top: 1px solid rgba(148, 163, 184, 0.2);
                border-bottom: 0;
            }
            .summary-total-row .summary-label,
            .summary-total-row .summary-value {
                font-size: 1rem;
                font-weight: 700;
                color: #f8fafc;
            }
            .doc-discount-panel.compact {
                padding: 8px 9px 7px;
                border-radius: 9px;
                background: rgba(15, 23, 42, 0.4);
            }
            .doc-discount-panel.compact .doc-discount-title {
                margin-bottom: 6px;
                font-size: 0.7rem;
                letter-spacing: 0.04em;
            }
            .doc-discount-panel.compact .form-check-label {
                font-size: 0.76rem !important;
            }
            .shipment-highlight {
                margin-top: 10px;
                border: 1px solid rgba(16, 185, 129, 0.35);
                border-radius: 10px;
                background: linear-gradient(160deg, rgba(5, 46, 29, 0.58), rgba(5, 46, 29, 0.25));
                padding: 8px 10px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
            }
            .shipment-highlight .label {
                display: flex;
                align-items: center;
                gap: 6px;
                color: #6ee7b7;
                font-size: 0.78rem;
                font-weight: 600;
                letter-spacing: 0.02em;
                text-transform: uppercase;
            }
            .shipment-highlight .value {
                font-weight: 700;
                color: #ecfdf5;
                font-size: 0.84rem;
            }
            .quote-image-thumb {
                cursor: zoom-in;
                transition: transform .14s ease, box-shadow .14s ease;
            }
            .quote-image-thumb:hover {
                transform: translateY(-1px);
                box-shadow: 0 8px 18px rgba(2, 6, 23, 0.45);
            }
            /* --- Lines table: modern, non-overlapping, mobile-friendly --- */
            [data-doc-lines-root] {
                border-radius: 10px;
                border: 1px solid rgba(148, 163, 184, 0.16);
                overflow-x: auto;
                overflow-y: hidden;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: thin;
                scrollbar-color: rgba(148, 163, 184, 0.35) transparent;
            }
            [data-doc-lines-root]::-webkit-scrollbar {
                height: 8px;
            }
            [data-doc-lines-root]::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.35);
                border-radius: 8px;
            }
            .so-lines-table {
                table-layout: auto;
                width: 100%;
                min-width: 1180px; /* forces horizontal scroll instead of squeezing/overlapping columns on narrow screens */
                border-collapse: separate;
                border-spacing: 0;
            }
            .so-lines-table th {
                background: rgba(15, 23, 42, 0.55);
                font-size: 0.74rem;
                text-transform: uppercase;
                letter-spacing: 0.03em;
                color: #94a3b8;
                padding: 10px 8px;
            }
            .so-lines-table td {
                padding: 8px;
            }
            .so-lines-table .col-disc,
            .so-lines-table .col-tax {
                white-space: nowrap;
            }
            /* Merge the type-select + value-input pair into a single "pill" control
               instead of two separate boxes crammed together (was the cause of the
               overlapping/duplicated-box look). */
            .so-lines-table .line-discount-editor,
            .so-lines-table .line-tax-editor {
                display: flex;
                align-items: stretch;
                justify-content: flex-end;
                gap: 0;
                margin-left: auto;
                width: fit-content;
                border: 1px solid rgba(148, 163, 184, 0.35);
                border-radius: 8px;
                overflow: hidden;
                background: rgba(15, 23, 42, 0.4);
            }
            .so-lines-table .line-discount-editor > select,
            .so-lines-table .line-tax-editor > select,
            .so-lines-table .line-discount-editor > input,
            .so-lines-table .line-tax-editor > input {
                border: none !important;
                border-radius: 0 !important;
                background: transparent !important;
                box-shadow: none !important;
            }
            .so-lines-table .line-discount-editor > input,
            .so-lines-table .line-tax-editor > input {
                border-left: 1px solid rgba(148, 163, 184, 0.25) !important;
            }
            .so-lines-table .inline-disc-type,
            .so-lines-table .inline-tax-type,
            .so-lines-table .inline-disc,
            .so-lines-table .inline-tax,
            .so-lines-table .new-line-disc-type,
            .so-lines-table .new-line-tax-type,
            .so-lines-table .new-line-disc,
            .so-lines-table .new-line-tax {
                box-sizing: border-box;
                width: 60px !important;
                min-width: 60px !important;
                max-width: 60px !important;
            }
            .so-lines-table .inline-disc,
            .so-lines-table .inline-tax,
            .so-lines-table .new-line-disc,
            .so-lines-table .new-line-tax {
                width: 84px !important;
                min-width: 84px !important;
                max-width: 84px !important;
            }
            /* Clean, modern number inputs: no native OS spinner clutter (tiny,
               hard to tap on mobile, and the cause of the input box appearing to
               render a second overlapping box inside a tight column). */
            .so-lines-table input[type="number"] {
                -moz-appearance: textfield;
            }
            .so-lines-table input[type="number"]::-webkit-outer-spin-button,
            .so-lines-table input[type="number"]::-webkit-inner-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
            /* Actions column stays visible on the right without needing to
               scroll the whole table over. */
            .so-lines-table th:last-child,
            .so-lines-table td:last-child {
                position: sticky;
                right: 0;
                background: #0f172a;
                z-index: 2;
                box-shadow: -6px 0 10px -6px rgba(0, 0, 0, 0.45);
            }
            .so-lines-table th:last-child {
                z-index: 3;
            }
            .so-lines-table .btn-group {
                flex-wrap: nowrap;
            }
            @media (max-width: 767px) {
                [data-doc-lines-root] {
                    border-radius: 8px;
                }
                .so-lines-table th,
                .so-lines-table td {
                    padding: 8px 6px;
                    font-size: 0.82rem;
                }
            }
        </style>
        <?php
            $customer = $customer ?? null;
            $customerAddress = $customerAddress ?? null;
            $addrLines = [];
            if (!empty($customerAddress['line1'])) $addrLines[] = $customerAddress['line1'];
            if (!empty($customerAddress['line2'])) $addrLines[] = $customerAddress['line2'];
            $cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
            if ($cityState !== '') $addrLines[] = $cityState;
            if (!empty($customerAddress['postal_code'])) $addrLines[] = $customerAddress['postal_code'];
            $contactRows = array_filter([
                'Phone'  => $customer['phone'] ?? '',
                'Mobile' => $customer['mobile'] ?? '',
                'Email'  => $customer['email'] ?? '',
            ]);
        ?>
        <style>
            .so-address-card { padding: 14px 16px; }
            .so-address-card .addr-title {
                font-size: 0.72rem;
                letter-spacing: .06em;
                text-transform: uppercase;
                color: #94a3b8;
                margin-bottom: 8px;
            }
            .so-address-card .addr-name { font-weight: 600; line-height: 1.35; }
            .so-address-card .addr-lines { color: #cbd5e1; font-size: 0.88rem; line-height: 1.5; }
            /* label column fixed so every value starts on the same x */
            .so-address-card .addr-grid {
                display: grid;
                grid-template-columns: 64px 1fr;
                gap: 2px 10px;
                margin-top: 8px;
                font-size: 0.85rem;
                align-items: baseline;
            }
            .so-address-card .addr-grid dt { color: #94a3b8; font-weight: 500; }
            .so-address-card .addr-grid dd { margin: 0; color: #e2e8f0; word-break: break-word; }
            /* compact date chip - sized to its content, not stretched to the address card */
            .so-date-chip {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                align-self: flex-start;
                padding: 8px 14px;
                border-radius: 10px;
                background: var(--cl-color-surface-2, #16202e);
                border: 1px solid rgba(148, 163, 184, 0.18);
                white-space: nowrap;
                line-height: 1.2;
            }
            .so-date-chip .chip-label {
                font-size: 0.7rem;
                letter-spacing: .06em;
                text-transform: uppercase;
                color: #94a3b8;
            }
            .so-date-chip .chip-value { font-weight: 600; color: #e2e8f0; font-size: 0.92rem; }
        </style>
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
            <div class="so-address-card shadow-sm" style="width:min(100%,520px);">
                <div class="addr-title d-flex align-items-center gap-2">
                    <span>Address &amp; Contact</span>
                    <?php if ($customerRouteId !== ''): ?>
                        <a href="<?= site_url('customers/' . $customerRouteId) ?>" class="cl-action-icon ms-auto"
                           title="Open customer profile" aria-label="Open customer profile">
                            <i class="bi bi-person-lines-fill"></i>
                        </a>
                    <?php endif; ?>
                    <?php if ($canRefreshAddress): ?>
                        <button type="button" class="cl-action-icon<?= $customerRouteId === '' ? ' ms-auto' : '' ?>" id="refreshCustomerAddressBtn"
                                data-url="<?= site_url('quotations/refresh-customer-address/' . (!empty($quote['public_id']) ? $quote['public_id'] : (int)$quote['id'])) ?>"
                                title="Update address &amp; contact from the customer profile"
                                aria-label="Update address and contact from the customer profile">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($customer['name'])): ?>
                    <div class="addr-name"><?= esc($customer['name']) ?></div>
                <?php endif; ?>
                <?php if ($addrLines): ?>
                    <div class="addr-lines"><?= esc(implode(', ', $addrLines)) ?></div>
                <?php endif; ?>
                <?php if ($contactRows): ?>
                    <dl class="addr-grid mb-0">
                        <?php foreach ($contactRows as $label => $value): ?>
                            <dt><?= esc($label) ?></dt>
                            <dd><?= esc($value) ?></dd>
                        <?php endforeach; ?>
                    </dl>
                <?php endif; ?>
                <?php if (empty($customer['name']) && !$addrLines && !$contactRows): ?>
                    <div class="text-muted" style="font-size:0.85rem;">No address on file</div>
                <?php endif; ?>
            </div>
            <div class="so-date-chip">
                <i class="bi bi-calendar-event" style="color:#94a3b8;"></i>
                <span class="chip-label">Date</span>
                <span class="chip-value"><?= esc($fmtDate ?: '-') ?></span>
            </div>
        </div>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2" data-doc-line-toolbar>
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0" style="font-size:1rem;">Lines</h5>
                <span class="badge rounded-pill text-bg-secondary" style="font-weight:600;"><?= count($lines) ?></span>
            </div>
            <!-- right cluster: view option first, then actions, primary action last -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <div class="form-check form-switch mb-0 me-1">
                    <input class="form-check-input" type="checkbox" id="toggle-discount-tax">
                    <label class="form-check-label text-muted" for="toggle-discount-tax" style="font-size:0.85rem;">Show Discount &amp; Tax</label>
                </div>
                <span class="vr d-none d-sm-block" style="opacity:.25;"></span>
                <!-- pre-rendered so document_line_tools.js binds this one instead of
                     appending its own Sort button at the end of the toolbar -->
                <button type="button" class="btn btn-sm btn-outline-secondary" data-doc-sort-btn="1" title="Reorder lines"><i class="bi bi-sort-down me-1"></i>Sort Lines</button>
                <button type="button" class="btn btn-sm btn-primary" id="btn-add-line" title="Add a product line"><i class="bi bi-plus-lg me-1"></i>Add Product Line</button>
            </div>
        </div>
        <div class="table-responsive" data-doc-lines-root>
            <table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;" data-doc-line-type="quotation" data-doc-id="<?= esc((string)($quote['public_id'] ?? ($quote['id'] ?? ''))) ?>">
                <thead>
                    <tr style="white-space:nowrap;">
                        <th style="width:44px" class="text-center">No.</th>
                        <th style="width:110px">Code</th>
                        <th style="width:56px">Image</th>
                        <th style="width:260px">Product / Description</th>
                        <th style="width:70px">Unit</th>
                        <th style="width:90px" class="text-end">Qty</th>
                        <th style="width:100px" class="text-end">Unit Price</th>
                        <th style="width:150px" class="text-end col-disc">Disc Type / Value</th>
                        <th style="width:90px" class="text-end col-disc">Disc Amt</th>
                        <th style="width:150px" class="text-end col-tax">Tax Type / Value</th>
                        <th style="width:90px" class="text-end col-tax">Tax Amt</th>
                        <th style="width:100px" class="text-end">Line Total</th>
                        <th style="width:150px">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    // Single source of truth per spec
                    // Read shipping only from the canonical 'shipping_amount' column
                    $shippingAmount = isset($quote['shipping_amount']) ? (float)$quote['shipping_amount'] : 0.0;
                    $documentDiscountType = strtolower((string)($quote['document_discount_type'] ?? 'fixed'));
                    if (!in_array($documentDiscountType, ['percent','fixed'], true)) $documentDiscountType = 'fixed';
                    $documentDiscountValue = (float)($quote['document_discount_value'] ?? 0);
                    $discountExcludeShipping = ((int)($quote['discount_exclude_shipping'] ?? 1) === 1);
                    $hasQuote = !empty($quote['id']);
                    $totalWeight = $hasQuote ? (float)($quote['total_weight'] ?? 0) : 0;
                    // Show the unit the user picked on the products when every
                    // weighed line agrees; mixed units fall back to kg.
                    $weightUnitsUsed = [];
                    foreach ($lines as $ln) {
                        if ((float)($ln['unit_weight'] ?? 0) > 0) {
                            $weightUnitsUsed[strtolower(trim((string)($ln['weight_unit'] ?? 'kg')))] = true;
                        }
                    }
                    $shipmentWeightUnit = count($weightUnitsUsed) === 1 ? (string)array_key_first($weightUnitsUsed) : null;
                    $formatShipmentWeight = static function (float $kg) use ($shipmentWeightUnit): string {
                        return \App\Helpers\WeightHelper::formatShipment($kg, $shipmentWeightUnit);
                    };
                    $displayShipmentWeight = $formatShipmentWeight($totalWeight);
                    $showDiscCols = false;
                    $showTaxCols = false;
                    foreach ($lines as $ln) {
                        if ((float)($ln['discount_value'] ?? 0) > 0 || (float)($ln['discount_amount'] ?? 0) > 0) $showDiscCols = true;
                        if ((float)($ln['tax_rate'] ?? 0) > 0 || (float)($ln['tax_amount'] ?? 0) > 0) $showTaxCols = true;
                    }
                ?>
                <?php
                    $sectionSubtotals = [];
                    $activeSection = 0;
                    foreach ($lines as $__ln) {
                        $__type = strtolower((string)($__ln['display_type'] ?? 'line'));
                        $__id = (int)($__ln['id'] ?? 0);
                        if ($__type === 'section') {
                            $activeSection = $__id;
                            if ($activeSection > 0 && !isset($sectionSubtotals[$activeSection])) {
                                $sectionSubtotals[$activeSection] = 0.0;
                            }
                            continue;
                        }
                        if ($activeSection > 0) {
                            $sectionSubtotals[$activeSection] += (float)($__ln['line_total'] ?? 0);
                        }
                    }
                    echo view('partials/document_lines/rows', [
                        'docType' => 'quotation',
                        'lines' => $lines,
                        'sectionSubtotals' => $sectionSubtotals,
                    ]);
                ?>
                </tbody>
            </table>
        </div>
        <div class="mt-3 d-flex justify-content-end">
            <div class="quote-summary-card">
                <div class="summary-row" id="row-subtotal">
                    <div class="summary-label">Subtotal</div>
                    <div class="summary-value" id="view-subtotal"><?= number_format((float)($quote['subtotal'] ?? 0),2) ?></div>
                </div>

                <div class="summary-row" id="row-tax" style="<?= (!empty($quote['tax']) && (float)$quote['tax'] > 0) ? '' : 'display:none' ?>">
                    <div class="summary-label">Tax</div>
                    <div class="summary-value" id="view-tax"><?= number_format((float)($quote['tax'] ?? 0),2) ?></div>
                </div>

                <div class="summary-row" id="row-doc-discount-panel">
                    <div class="summary-label">Document Discount</div>
                    <div class="text-end" style="min-width:220px;">
                        <div class="doc-discount-panel compact">
                            <div class="doc-discount-title">Apply On Entire Quotation</div>
                            <div class="form-check d-flex justify-content-end mb-1">
                                <input class="form-check-input" type="checkbox" id="view-document-discount-enabled" <?= ((float)$documentDiscountValue > 0.0) ? 'checked' : '' ?> <?= $isConvertedToSalesOrder ? 'disabled' : '' ?>>
                                <label class="form-check-label ms-2" for="view-document-discount-enabled">Enable document discount</label>
                            </div>
                            <div class="d-flex justify-content-end align-items-center gap-2 mb-1">
                                <select id="view-document-discount-type" class="form-select form-select-sm" style="max-width:78px;" <?= $isConvertedToSalesOrder ? 'disabled' : '' ?>>
                                    <option value="fixed" <?= $documentDiscountType === 'fixed' ? 'selected' : '' ?>>Fixed</option>
                                    <option value="percent" <?= $documentDiscountType === 'percent' ? 'selected' : '' ?>>%</option>
                                </select>
                                <input type="number" step="0.01" min="0" id="view-document-discount-value" class="form-control form-control-sm text-end" style="max-width:95px;" value="<?= number_format((float)$documentDiscountValue, 2, '.', '') ?>" <?= $isConvertedToSalesOrder ? 'disabled' : '' ?>>
                            </div>
                            <div class="form-check d-flex justify-content-end mb-0">
                                <input class="form-check-input" type="checkbox" id="view-discount-exclude-shipping" <?= $discountExcludeShipping ? 'checked' : '' ?> <?= $isConvertedToSalesOrder ? 'disabled' : '' ?>>
                                <label class="form-check-label ms-2" for="view-discount-exclude-shipping">Exclude shipping from document discount</label>
                            </div>
                            <div class="form-check d-flex justify-content-end mt-1 mb-0">
                                <input class="form-check-input" type="checkbox" id="view-discount-overwrite-lines" checked <?= $isConvertedToSalesOrder ? 'disabled' : '' ?>>
                                <label class="form-check-label ms-2" for="view-discount-overwrite-lines">Overwrite all product line discounts</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="summary-row" id="row-discount" style="<?= (!empty($quote['discount']) && (float)$quote['discount'] > 0) ? '' : 'display:none' ?>">
                    <div class="summary-label">Discount</div>
                    <div class="summary-value text-danger" id="view-discount">-<?= number_format((float)($quote['discount'] ?? 0),2) ?></div>
                </div>

                <div class="summary-row" id="row-shipping">
                    <div class="summary-label">Shipping</div>
                    <div class="summary-value" id="view-shipping">
                        <div class="d-flex justify-content-end">
                            <input type="number" step="0.01" min="0" id="view-shipping-input" class="form-control form-control-sm text-end" style="max-width:120px;" value="<?= number_format((float)$shippingAmount,2,'.','') ?>" <?= $isConvertedToSalesOrder ? 'disabled' : '' ?>>
                        </div>
                    </div>
                </div>

                <div class="summary-row summary-total-row" id="row-total">
                    <div class="summary-label">Total</div>
                    <div class="summary-value" id="view-total"><?= number_format((float)($quote['total'] ?? 0),2) ?></div>
                </div>

                <?php if ($hasQuote): ?>
                <div class="shipment-highlight" id="row-weight-highlight">
                    <div class="label"><i class="bi bi-box2-heart-fill"></i> Shipment Weight</div>
                    <div class="value" id="view-weight" data-weight-mode="calculated" title="Calculated from product unit weight x quantity">
                        <?= esc($displayShipmentWeight) ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!$isConvertedToSalesOrder): ?>
                    <div class="d-grid mt-2">
                        <button type="button" class="btn btn-success" id="btn-save-totals">Save</button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Tags Section -->
<div class="card mt-3 cl-detail-card">
    <div class="card-body">
        <?php
            $this->setVar('document_type', 'quotation');
            $this->setVar('document_id', (int)$quote['id']);
            $this->setVar('existing_tags', $tags ?? []);
            $this->setVar('readonly', false);
        ?>
        <?= $this->include('components/tag_input') ?>
    </div>
</div>

    <script src="<?= base_url('assets/js/corelynk_autocomplete.js') ?>"></script>
    <script src="<?= base_url('assets/js/quotation_view_inline.js') ?>"></script>
    <script src="<?= base_url('assets/js/document_line_tools.js') ?>"></script>
    <script>
    (function(){
        var quoteId = <?= (int)($quote['id'] ?? 0) ?>;
        var quoteStatus = '<?= $isConvertedToSalesOrder ? 'converted' : $quoteStatus ?>';
        var quoteReadOnly = <?= $isConvertedToSalesOrder ? 'true' : 'false' ?>;
        window.quoteId = quoteId;
        window.quoteStatus = quoteStatus;
        window.quoteReadOnly = quoteReadOnly;

        var showDiscCols = true;
        var showTaxCols = <?= $showTaxCols ? 'true' : 'false' ?>;
        var toggleEl = document.getElementById('toggle-discount-tax');
        function applyDiscTaxVisibility(show) {
            var discCols = document.querySelectorAll('.col-disc');
            var taxCols = document.querySelectorAll('.col-tax');
            // Keep discount columns always visible so line discount type/value controls are accessible.
            discCols.forEach(function(el){ el.style.display = ''; });
            taxCols.forEach(function(el){ el.style.display = show ? '' : 'none'; });
        }
        if (toggleEl) {
            toggleEl.checked = (showDiscCols || showTaxCols);
            applyDiscTaxVisibility(toggleEl.checked);
            toggleEl.addEventListener('change', function(){ applyDiscTaxVisibility(this.checked); });
        } else {
            applyDiscTaxVisibility(showDiscCols || showTaxCols);
        }

        function updateTotalsFromResponse(totals){
            if (!totals) return;
            // Mirrors WeightHelper::formatShipment() so the live AJAX total reads
            // the same as the server-rendered one.
            var shipmentWeightUnit = <?= json_encode($shipmentWeightUnit) ?>;
            var shipmentUnitPerKg = { g: 1000, gram: 1000, grams: 1000, mg: 1000000, lb: 1 / 0.453592, lbs: 1 / 0.453592, oz: 1 / 0.0283495, ton: 0.001, tonne: 0.001 };
            function formatShipmentWeight(kg){
                var val = parseFloat(kg || 0) || 0;
                var factor = shipmentWeightUnit ? shipmentUnitPerKg[shipmentWeightUnit] : null;
                if (factor) {
                    var converted = val * factor;
                    return converted.toFixed(converted >= 100 ? 0 : 2) + ' ' + shipmentWeightUnit + ' (' + val.toFixed(3) + ' kg)';
                }
                if (val >= 1) return val.toFixed(3) + ' kg';
                return Math.round(val * 1000) + ' g';
            }
            var elSub = document.getElementById('view-subtotal');
            if (elSub && totals.subtotal != null) elSub.textContent = parseFloat(totals.subtotal).toFixed(2);
            var rowDisc = document.getElementById('row-discount');
            if (rowDisc && totals.discount != null) {
                if (parseFloat(totals.discount) > 0) {
                    rowDisc.style.display = '';
                    var elDisc = document.getElementById('view-discount');
                    if (elDisc) elDisc.textContent = '-' + parseFloat(totals.discount).toFixed(2);
                } else {
                    rowDisc.style.display = 'none';
                }
            }
            var rowTax = document.getElementById('row-tax');
            if (rowTax && totals.tax != null) {
                if (parseFloat(totals.tax) > 0) {
                    rowTax.style.display = '';
                    var elTax = document.getElementById('view-tax');
                    if (elTax) elTax.textContent = parseFloat(totals.tax).toFixed(2);
                } else {
                    rowTax.style.display = 'none';
                }
            }
            var elTotal = document.getElementById('view-total');
            if (elTotal && totals.total != null) elTotal.textContent = parseFloat(totals.total).toFixed(2);
            var shipInput = document.getElementById('view-shipping-input');
            if (shipInput && totals.shipping_amount != null) shipInput.value = parseFloat(totals.shipping_amount).toFixed(2);
            var weightText = document.getElementById('view-weight');
            if (weightText && totals.total_weight != null) {
                weightText.textContent = formatShipmentWeight(totals.total_weight);
            }
        }

        function syncLinesFromResponse(lines){
            if (!Array.isArray(lines) || !lines.length) return;
            lines.forEach(function(line){
                var row = document.querySelector('tr[data-line-id="' + line.id + '"]');
                if (!row) return;
                var lineDiscType = (line.discount_type || 'percent').toLowerCase();
                var lineDiscLabel = lineDiscType === 'fixed' ? 'Fix' : '%';
                var discCellOut = row.querySelector('.line-disc-percent');
                if (discCellOut) {
                    discCellOut.innerHTML = '<span class="badge bg-secondary-subtle text-light-emphasis me-1" style="font-size:0.68rem;">' + lineDiscLabel + '</span><span>' + (line.discount_value != null ? line.discount_value : '') + '</span>';
                    discCellOut.setAttribute('data-discount-type', lineDiscType);
                }
                row.setAttribute('data-discount-type', lineDiscType);
                var discAmtEl = row.querySelector('.line-disc-amt');
                if (discAmtEl) discAmtEl.textContent = parseFloat((line.discount_amount != null ? line.discount_amount : 0)).toFixed(2);
                var lineTotalEl = row.querySelector('.line-total');
                if (lineTotalEl && line.line_total != null) lineTotalEl.textContent = parseFloat(line.line_total).toFixed(2);
                var taxAmtEl = row.querySelector('.line-tax-amt');
                if (taxAmtEl && line.tax_amount != null) taxAmtEl.textContent = parseFloat(line.tax_amount).toFixed(2);
            });
        }

        function saveShipping(val){
            // Build a stable base URL that works when hosted under /corelynk
            var appBase = window.location.pathname.split('/quotations/')[0] || '';
            var url = window.location.origin + appBase + '/quotations/update-shipping/' + quoteId;
            var docTypeEl = document.getElementById('view-document-discount-type');
            var docValueEl = document.getElementById('view-document-discount-value');
            var docEnabledEl = document.getElementById('view-document-discount-enabled');
            var docExcludeEl = document.getElementById('view-discount-exclude-shipping');
            var overwriteEl = document.getElementById('view-discount-overwrite-lines');
            var docEnabled = docEnabledEl ? !!docEnabledEl.checked : true;
            return fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: JSON.stringify({
                    shipping_amount: val,
                    document_discount_type: docTypeEl ? docTypeEl.value : 'fixed',
                    document_discount_value: docEnabled ? (docValueEl ? (parseFloat(docValueEl.value || 0) || 0) : 0) : 0,
                    discount_exclude_shipping: docEnabled ? ((docExcludeEl && docExcludeEl.checked) ? 1 : 0) : 0,
                    overwrite_line_discounts: (docEnabled && overwriteEl && overwriteEl.checked) ? 1 : 0
                })
            }).then(async function(r){
                var ct = r.headers.get('content-type')||'';
                var txt = await r.text();
                if (ct.indexOf('application/json')!==-1) {
                    try { return JSON.parse(txt); } catch(e) { return { success:false, error:'Invalid JSON', raw:txt }; }
                }
                // fallback attempt parse
                try { return JSON.parse(txt); } catch(e) { return { success:false, error:'Non-JSON response', raw:txt }; }
            });
        }

        var shippingInputEl = document.getElementById('view-shipping-input');
        var btnSaveTotals = document.getElementById('btn-save-totals');
        var docEnabledEl = document.getElementById('view-document-discount-enabled');
        var docTypeEl = document.getElementById('view-document-discount-type');
        var docValueEl = document.getElementById('view-document-discount-value');
        var docExcludeEl = document.getElementById('view-discount-exclude-shipping');
        var docOverwriteEl = document.getElementById('view-discount-overwrite-lines');

        function setDocDiscountUiEnabled(enabled){
            if (docTypeEl) docTypeEl.disabled = !enabled;
            if (docValueEl) docValueEl.disabled = !enabled;
            if (docExcludeEl) docExcludeEl.disabled = !enabled;
            if (docOverwriteEl) docOverwriteEl.disabled = !enabled;
        }
        setDocDiscountUiEnabled(docEnabledEl ? !!docEnabledEl.checked : true);

        // Live calculation for exclude shipping toggle
        function recalculateTotalsPreview(){
            var docEnabledChecked = docEnabledEl ? !!docEnabledEl.checked : false;
            if (!docEnabledChecked) {
                // If document discount is disabled, just show base totals
                return;
            }

            var subtotalEl = document.getElementById('view-subtotal');
            var taxEl = document.getElementById('view-tax');
            var shippingEl = document.getElementById('view-shipping');
            var docDiscountEl = document.getElementById('view-discount');
            var totalEl = document.getElementById('view-total');
            
            var subtotal = subtotalEl ? parseFloat(subtotalEl.textContent.replace(/,/g,'') || 0) : 0;
            var tax = taxEl ? parseFloat(taxEl.textContent.replace(/,/g,'') || 0) : 0;
            var shippingInput = shippingInputEl ? (parseFloat(shippingInputEl.value || 0) || 0) : 0;
            
            // Get line discounts (all .line-disc-amt cells)
            var lineDiscountCells = document.querySelectorAll('.line-disc-amt');
            var totalLineDiscount = 0;
            lineDiscountCells.forEach(function(cell){
                totalLineDiscount += parseFloat(cell.textContent.replace(/,/g,'') || 0);
            });

            var docDiscountType = docTypeEl ? docTypeEl.value : 'fixed';
            var docDiscountValue = docValueEl ? (parseFloat(docValueEl.value || 0) || 0) : 0;
            var discountExcludeShipping = docExcludeEl ? !!docExcludeEl.checked : true;

            // Recalculate: lineNet = subtotal - lineDiscounts
            var lineNet = Math.max(0, subtotal - totalLineDiscount);
            
            // documentBase depends on excludeShipping flag
            var documentBase = discountExcludeShipping 
                ? (lineNet + tax)  // Exclude shipping from discount base
                : (lineNet + tax + shippingInput);  // Include shipping

            // Calculate document discount amount
            var documentDiscountAmount = 0;
            if (docDiscountValue > 0) {
                if (docDiscountType === 'percent') {
                    documentDiscountAmount = documentBase * (docDiscountValue / 100.0);
                } else {
                    documentDiscountAmount = docDiscountValue;
                }
            }
            documentDiscountAmount = Math.min(Math.max(0, documentDiscountAmount), documentBase);

            // Calculate new total: lineNet + tax + shipping - documentDiscount
            var newTotal = lineNet + tax + shippingInput - documentDiscountAmount;
            
            // Update display (only the values, not the row visibility which is handled by Save)
            if (docDiscountEl) {
                var totalDiscount = totalLineDiscount + documentDiscountAmount;
                docDiscountEl.textContent = '-' + totalDiscount.toFixed(2);
            }
            if (totalEl) {
                totalEl.textContent = newTotal.toFixed(2);
            }
        }

        if (docEnabledEl) {
            docEnabledEl.addEventListener('change', function(){
                var enabled = !!this.checked;
                setDocDiscountUiEnabled(enabled);
                if (!enabled && docValueEl) {
                    docValueEl.value = '0.00';
                }
                if (!enabled && docOverwriteEl) {
                    docOverwriteEl.checked = false;
                }
                // Recalculate preview when document discount enabled/disabled
                recalculateTotalsPreview();
            });
        }

        // Live calculation when exclude shipping checkbox changes
        if (docExcludeEl) {
            docExcludeEl.addEventListener('change', function(){
                recalculateTotalsPreview();
            });
        }

        // Live calculation when document discount value changes
        if (docValueEl) {
            docValueEl.addEventListener('input', function(){
                recalculateTotalsPreview();
            });
        }

        // Live calculation when document discount type changes
        if (docTypeEl) {
            docTypeEl.addEventListener('change', function(){
                recalculateTotalsPreview();
            });
        }

        // Live calculation when shipping amount changes
        if (shippingInputEl) {
            shippingInputEl.addEventListener('input', function(){
                recalculateTotalsPreview();
            });
        }

        if (quoteId && btnSaveTotals) {
            btnSaveTotals.addEventListener('click', function(){
                var currentShip = shippingInputEl ? (parseFloat(shippingInputEl.value || 0) || 0) : 0;
                saveShipping(currentShip).then(function(json){
                    if (!json || !json.success) {
                        var msg = 'Update failed: ' + (json && json.error ? json.error : 'unknown');
                        if (typeof window.quoteNotify === 'function') window.quoteNotify(msg, 'error');
                        else alert(msg);
                        return;
                    }
                    updateTotalsFromResponse(json.totals);
                    syncLinesFromResponse(json.lines || []);
                    if (typeof window.quoteNotify === 'function') window.quoteNotify('Totals saved successfully.', 'success');
                }).catch(function(e){
                    var msg = 'Update failed: ' + e.toString();
                    if (typeof window.quoteNotify === 'function') window.quoteNotify(msg, 'error');
                    else alert(msg);
                });
            });
        }

    })();
    </script>

<?= $this->include('partials/_document_log') ?>


<script>
// Pull the customer's current primary address/contact onto this quotation. The
// endpoint refuses once the quotation has become a sales order.
document.getElementById('refreshCustomerAddressBtn')?.addEventListener('click', function () {
    const btn = this;
    if (!confirm('Update this quotation's address & contact from the customer profile?')) return;
    btn.disabled = true;
    fetch(btn.dataset.url, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json().catch(() => ({ success: false, error: 'Unexpected response' })))
        .then(resp => {
            if (resp.success) { location.reload(); return; }
            alert(resp.error || 'Could not update the address.');
            btn.disabled = false;
        })
        .catch(() => { alert('Could not update the address.'); btn.disabled = false; });
});
</script>
<?= $this->endSection() ?>
