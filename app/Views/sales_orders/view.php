<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Sales Order <?= esc($order['order_number'] ?? ('SO-' . ($order['id'] ?? ''))) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/* Theme-aware styling for sales order view */
.so-address-card {
    max-width: 520px;
    border-radius: 10px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #0f172a;
}
.so-address-card textarea {
    border-radius: 8px;
    resize: none;
    background: #ffffff;
    color: #0f172a;
    border: 1px solid #d8e0eb;
    font-size: 13px;
    min-height: 110px;
}
.so-lines-table tbody tr:nth-child(odd) { background: #ffffff; }
.so-lines-table tbody tr:nth-child(even) { background: #f8fafc; }
.so-totals-box {
    background: #f1f5f9;
    border-radius: 8px;
}

.so-prep-wrap {
    border-color: #dbeafe !important;
}

.so-prep-header {
    background: #eff6ff;
}

.so-prep-item {
    background: #f8fafc;
    border-color: #e2e8f0 !important;
}
/* Hide discount and tax columns by default */
.so-lines-table .col-disc,
.so-lines-table .col-tax {
    display: none;
}
/* Show when toggle is checked */
.show-discount-tax .so-lines-table .col-disc,
.show-discount-tax .so-lines-table .col-tax {
    display: table-cell;
}
/* Respect user's dark mode preference */
@media (prefers-color-scheme: dark) {
    .so-address-card {
        border: 1px solid #243b55;
        background: #0f172a;
        color: #e2e8f0;
        box-shadow: 0 6px 16px rgba(0,0,0,0.35);
    }
    .so-address-card textarea {
        background: #111827;
        color: #e2e8f0;
        border: 1px solid #1f2a44;
    }
    .so-lines-table tbody tr:nth-child(odd) { background: #0d1726; }
    .so-lines-table tbody tr:nth-child(even) { background: #101c30; }
    .so-totals-box {
        background: #101827;
    }
}

/* Also support Bootstrap's data-bs-theme attribute for explicit theme control */
[data-bs-theme="dark"] .so-address-card {
    border: 1px solid #243b55;
    background: #0f172a;
    color: #e2e8f0;
    box-shadow: 0 6px 16px rgba(0,0,0,0.35);
}
[data-bs-theme="dark"] .so-address-card textarea {
    background: #111827;
    color: #e2e8f0;
    border: 1px solid #1f2a44;
}
[data-bs-theme="dark"] .so-lines-table tbody tr:nth-child(odd) { background: #0d1726; }
[data-bs-theme="dark"] .so-lines-table tbody tr:nth-child(even) { background: #101c30; }
[data-bs-theme="dark"] .so-totals-box {
    background: #101827;
}

[data-bs-theme="dark"] .so-prep-wrap {
    border-color: #334155 !important;
}

[data-bs-theme="dark"] .so-prep-header {
    background: #162033;
    border-bottom: 1px solid #334155;
}

[data-bs-theme="dark"] .so-prep-item {
    background: #1f2937;
    border-color: #334155 !important;
}
</style>
<?php
    $customerLabel = $customer['name'] ?? $order['customer_name'] ?? $order['customer_code'] ?? ($order['customer_id'] ?? '');
    $customerCode = $customer['customer_code'] ?? ($order['customer_code'] ?? '');
    $subtotal = isset($displaySubtotal) ? (float)$displaySubtotal : (float)($order['subtotal'] ?? 0);
    $discountTotal = isset($displayDiscount) ? (float)$displayDiscount : 0.0;
    $taxTotal = isset($displayTax) ? (float)$displayTax : (float)($order['tax_total'] ?? 0);
    $shipping = isset($shippingResolved) ? (float)$shippingResolved : 0.0;
    $total = isset($displayTotal)
        ? (float)$displayTotal
        : (float)($subtotal - $discountTotal + $taxTotal + $shipping);
    $currencyCode = strtoupper(trim((string)($currencyCode ?? $order['currency'] ?? '')));
    $currencyCode = $currencyCode !== '' ? $currencyCode : 'USD';
    $currencySymbols = [
        'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
    ];
    $symbol = $currencySymbols[$currencyCode] ?? ($currencyCode !== '' ? $currencyCode : '$');
    $fmtMoney = function($val) use ($symbol) {
        return $symbol . number_format((float)$val, 2);
    };
    $fmtShipmentWeight = static function($kg) {
        $kg = max(0.0, (float)$kg);
        if ($kg >= 1) {
            return number_format($kg, 3) . ' kg';
        }
        return number_format($kg * 1000, 0) . ' g';
    };

    // Build address/contact block
    $addrLines = [];
    if (!empty($customerLabel)) $addrLines[] = $customerLabel;
    if (!empty($customerAddress['line1'])) $addrLines[] = $customerAddress['line1'];
    if (!empty($customerAddress['line2'])) $addrLines[] = $customerAddress['line2'];
    $cityState = trim(($customerAddress['city_name'] ?? '') . ' ' . ($customerAddress['state_name'] ?? ''));
    if ($cityState !== '') $addrLines[] = $cityState;
    if (!empty($customerAddress['postal_code'])) $addrLines[] = 'Postal: ' . $customerAddress['postal_code'];
    // Add country name if available
    if (!empty($customerAddress['country_name'])) $addrLines[] = $customerAddress['country_name'];
    if (!empty($customer['phone'])) $addrLines[] = 'Phone: ' . $customer['phone'];
    if (!empty($customer['mobile'])) $addrLines[] = 'Mobile: ' . $customer['mobile'];
    if (!empty($customer['email'])) $addrLines[] = 'Email: ' . $customer['email'];
    $addrText = implode("\n", array_filter($addrLines));
    $missingVendorItems = session()->getFlashdata('missing_vendor_items');
    if (!is_array($missingVendorItems)) {
        $missingVendorItems = [];
    }

    $soStatus = strtolower((string)($order['status'] ?? ''));
    $doStatus = strtolower((string)($existingDo['status'] ?? ''));
    $isFulfillmentClosed = in_array($soStatus, ['delivered', 'closed'], true) || $doStatus === 'delivered';
    $isShipped = $soStatus === 'shipped' || $doStatus === 'shipped';
?>

<style>
.status-ribbon-wrapper {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 110px;
    height: 110px;
    overflow: hidden;
    z-index: 100;
    pointer-events: none;
    border-top-right-radius: var(--bs-card-border-radius);
}
.status-ribbon {
    position: absolute;
    top: 22px;
    right: -25px;
    font-weight: 700;
    text-transform: uppercase;
    text-align: center;
    line-height: 25px;
    width: 150px;
    display: block;
    transform: rotate(45deg);
    color: white;
    text-shadow: 1px 1px 3px rgba(0,0,0,0.5);
    letter-spacing: 1px;
    font-size: 0.75rem;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3), inset 0 1px 1px rgba(255,255,255,0.4);
}
.ribbon-delivered .status-ribbon {
    background: linear-gradient(135deg, #22c55e 0%, #166534 100%);
    border-top: 2px solid #4ade80;
    border-bottom: 2px solid #064e3b;
}
.ribbon-shipped .status-ribbon {
    background: linear-gradient(135deg, #f97316 0%, #9a3412 100%);
    border-top: 2px solid #fb923c;
    border-bottom: 2px solid #7c2d12;
}

/* Offset for the buttons to prevent ribbon overlap */
@media (min-width: 768px) {
    .ribbon-offset {
        margin-right: 60px !important;
    }
}
@media (max-width: 767px) {
    .status-ribbon-wrapper {
        border-top-right-radius: 0;
    }
    .ribbon-offset {
        width: 100%;
        justify-content: flex-start !important;
        margin-top: 15px;
    }
}
</style>

<div class="card" style="position: relative; overflow: hidden;">
    <?php if ($isFulfillmentClosed || $isShipped): ?>
        <div class="status-ribbon-wrapper <?= $isFulfillmentClosed ? 'ribbon-delivered' : 'ribbon-shipped' ?>">
            <div class="status-ribbon">
                <?= $isFulfillmentClosed ? 'Delivered' : 'Shipped' ?>
            </div>
        </div>
    <?php endif; ?>
    <div class="card-header section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h3 class="section-title mb-0">Sales Order <?= esc($order['order_number'] ?? ('SO-' . ($order['id'] ?? ''))) ?></h3>
            <?php $fmtDate = !empty($order['order_date']) ? date('d-m-Y', strtotime($order['order_date'])) : ''; ?>
            <div class="section-sub">Date: <?= esc($fmtDate) ?></div>
        </div>
        <div class="d-flex align-items-center flex-wrap gap-2 ms-auto <?= ($isFulfillmentClosed || $isShipped) ? 'ribbon-offset' : '' ?>">
            <?php if ($isFulfillmentClosed): ?>
                <span class="badge bg-secondary" style="text-transform:uppercase; min-width:120px; text-align:center;">Status: Closed</span>
            <?php elseif (!empty($order['status'])): ?>
                <span class="badge bg-success" style="text-transform:uppercase; min-width:120px; text-align:center;">Status: <?= esc($order['status']) ?></span>
            <?php endif; ?>

            <!-- Order Progress button (always visible on confirmed orders) -->
            <?php if (in_array($soStatus, ['confirmed','shipped','delivered','processing'], true)): ?>
                <button type="button" class="btn btn-sm btn-outline-info" id="btnOrderProgress"
                    data-so-id="<?= (int)$order['id'] ?>"
                    title="View Order Progress">
                    <i class="bi bi-diagram-3 me-1"></i>Order Progress
                </button>
            <?php endif; ?>

            <!-- DO button: smart — show View/Draft/Create based on existingDo -->
            <?php if (!empty($existingDo)): ?>
                <?php if (in_array($existingDo['status'] ?? '', ['confirmed','delivered'], true)): ?>
                    <a href="<?= site_url('delivery-orders/view/' . (int)$existingDo['id']) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-truck me-1"></i>View DO (<?= esc($existingDo['do_number'] ?? '') ?>)
                    </a>
                <?php else: ?>
                    <a href="<?= site_url('delivery-orders/view/' . (int)$existingDo['id']) ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-eye me-1"></i>View Draft DO
                    </a>
                <?php endif; ?>
            <?php elseif (!empty($readyToShip)): ?>
                <form method="post" action="<?= site_url('delivery-orders/create-from-sales-order/' . (int)$order['id']) ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-truck me-1"></i>Create Delivery Order
                    </button>
                </form>
            <?php endif; ?>
            
            <!-- Phase-3: Fulfillment Status Badge (derived from PO mappings + GRN receipts) -->
            <?php if (!empty($fulfillmentStatus) && $fulfillmentStatus !== 'UNKNOWN'): ?>
                <?php
                    $fulfillmentBgColor = 'bg-danger';
                    $fulfillmentIcon = 'bi-hourglass-split';
                    $fulfillmentLabel = 'Not Ready';
                    $fulfillmentTooltip = 'No items received yet';
                    
                    if ($fulfillmentStatus === 'PARTIAL_READY') {
                        $fulfillmentBgColor = 'bg-warning';
                        $fulfillmentIcon = 'bi-exclamation-circle';
                        $fulfillmentLabel = 'Partially Ready';
                        $fulfillmentTooltip = 'Some items received, others pending';
                    } elseif ($fulfillmentStatus === 'READY') {
                        $fulfillmentBgColor = 'bg-success';
                        $fulfillmentIcon = 'bi-check-circle-fill';
                        $fulfillmentLabel = 'Ready to Ship';
                        $fulfillmentTooltip = 'All items received';
                    }
                ?>
                <span class="badge <?= $fulfillmentBgColor ?>" style="text-transform:uppercase; min-width:150px; text-align:center; font-size:0.85rem;" title="<?= $fulfillmentTooltip ?>">
                    <i class="bi <?= $fulfillmentIcon ?> me-1"></i>
                    <?= $fulfillmentLabel ?>
                </span>
            <?php endif; ?>
            
            <!-- Professional Actions Dropdown Menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="actionsDropdown" style="min-width: 220px;">
                    <!-- Navigation -->
                    <li>
                        <a class="dropdown-item" href="<?= site_url('sales-orders') ?>">
                            <i class="bi bi-arrow-left text-secondary me-2"></i>Back to Orders List
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- Invoice Actions -->
                    <?php if (!empty($invoice['id'])): ?>
                        <li>
                            <a class="dropdown-item" href="<?= site_url('customer-invoices/view/' . $invoice['id']) ?>">
                                <i class="bi bi-receipt text-success me-2"></i>View Invoice
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= site_url('customer-invoices/pdf/' . $invoice['id']) ?>" target="_blank">
                                <i class="bi bi-file-pdf text-danger me-2"></i>Download Invoice PDF
                            </a>
                        </li>
                    <?php else: ?>
                        <li>
                            <a class="dropdown-item" href="<?= site_url('sales-orders/invoice/'.$order['id']) ?>">
                                <i class="bi bi-plus-circle text-primary me-2"></i>Create Invoice
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Auto-PO Creation (only if shortage exists) -->
                    <?php if (!$isFulfillmentClosed && !empty($hasShortage) && $hasShortage === true): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <h6 class="dropdown-header text-warning">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Stock Actions
                            </h6>
                        </li>
                        <?php if (empty($hasAutoRfq)): ?>
                            <li>
                                <form method="post" action="<?= site_url('sales-orders/create-purchase-drafts/' . ($order['id'] ?? 0)) ?>" style="margin:0;">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dropdown-item text-warning fw-semibold" title="Create draft RFQs for shortage items">
                                        <i class="bi bi-magic me-2"></i>Auto-Create RFQ Drafts
                                    </button>
                                </form>
                            </li>
                        <?php else: ?>
                            <li>
                                <button type="button" class="dropdown-item text-secondary" disabled title="RFQ drafts already created for this order">
                                    <i class="bi bi-check2-circle me-2"></i>RFQ Drafts Created
                                </button>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="so-address-card mb-3 shadow-sm">
            <div class="fw-semibold mb-2">Address &amp; Contact</div>
            <textarea class="form-control" rows="4" readonly>
<?= esc($addrText ?: 'N/A') ?>
            </textarea>
        </div>

        <!-- Phase-1: Stock Readiness Alert -->
        <?php if (!$isFulfillmentClosed && !empty($hasShortage) && $hasShortage === true): ?>
        <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 mb-3 py-2 px-3" style="border-left: 3px solid #0284c7; background: #e0f2fe; border: none;">
            <i class="bi bi-info-circle-fill" style="font-size:1rem; color:#0284c7; flex-shrink:0;"></i>
            <div style="flex:1; font-size:0.9rem; color:#075985;">
                <?php if (empty($hasAutoRfq)): ?>
                    <strong><?= (int)($shortageCount ?? 0) ?></strong> items pending stock. Use "Auto-Create RFQ Drafts" button to generate RFQs.
                <?php else: ?>
                    <strong><?= (int)($shortageCount ?? 0) ?></strong> items pending stock. RFQ drafts already created for this order.
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!$isFulfillmentClosed && !empty($missingVendorItems)): ?>
        <div class="alert alert-warning mb-3" style="border-left:3px solid #f59e0b;">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Vendor assignment required before auto RFQ generation</div>
            <div class="small mb-2">Assign a vendor to each product below, then click Auto-Create RFQ Drafts again.</div>
            <ul class="mb-0" style="padding-left:1rem;">
                <?php foreach ($missingVendorItems as $mv):
                    $productId = (int)($mv['product_id'] ?? 0);
                    $code = (string)($mv['code'] ?? 'Unknown Product');
                ?>
                    <li>
                        <?= esc($code) ?>
                        <?php if ($productId > 0): ?>
                            - <a href="<?= site_url('products/' . $productId . '/edit') ?>">Assign Vendor</a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php if (!$isFulfillmentClosed && !empty($hasPreparationPlans)): ?>
        <div class="card mb-3 border so-prep-wrap">
            <div class="card-header so-prep-header">
                <h5 class="mb-0">Preparation Status</h5>
            </div>
            <div class="card-body">
                <?php foreach (($lines ?? []) as $planLine): ?>
                    <?php $plan = $planLine['preparation_plan'] ?? null; ?>
                    <?php if (empty($plan) || empty($plan['show_panel'])) continue; ?>

                    <div class="border rounded p-3 mb-3 so-prep-item">
                        <?php $sendNotes = $vendorSendNotesByProduct[(int) ($plan['product_id'] ?? 0)] ?? []; ?>
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div class="fw-semibold">
                                <?= esc($plan['product_name'] ?? ($planLine['product_name'] ?? 'Product')) ?>
                                <span class="text-muted">x <?= number_format((float) ($plan['requested_qty'] ?? 0), 2) ?></span>
                            </div>
                            <div class="small text-muted">Available: <?= number_format((float) ($plan['available_qty'] ?? 0), 2) ?></div>
                        </div>

                        <?php if (!empty($sendNotes)): ?>
                            <div class="mb-3">
                                <div class="fw-semibold mb-2">Vendor Receiving + QC</div>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($sendNotes as $note): ?>
                                        <a href="<?= site_url('vendor-receive/' . (int) ($note['id'] ?? 0)) ?>" class="btn btn-sm btn-outline-secondary">
                                            Receive <?= esc($note['reference_no'] ?? 'Send Note') ?>
                                            (Remaining: <?= number_format((float) ($note['remaining_qty'] ?? 0), 4) ?>)
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Materials Needed</div>
                            <?php if (($plan['status'] ?? '') === 'no_profile'): ?>
                                <div class="text-muted small">No preparation profile found for this product.</div>
                            <?php elseif (empty($plan['materials'])): ?>
                                <div class="text-muted small">No materials listed for this preparation profile.</div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead>
                                            <tr>
                                                <th>Material</th>
                                                <th class="text-end">Available / Required</th>
                                                <th style="width:110px;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (($plan['materials'] ?? []) as $material): ?>
                                                <?php $isMissing = (($material['status'] ?? '') === 'missing'); ?>
                                                <tr>
                                                    <td><?= esc($material['name'] ?? '-') ?></td>
                                                    <td class="text-end">
                                                        <?= number_format((float) ($material['available_qty'] ?? 0), 4) ?> /
                                                        <?= number_format((float) ($material['required_qty'] ?? 0), 4) ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($isMissing): ?>
                                                            <span class="text-danger">❌ Missing</span>
                                                        <?php else: ?>
                                                            <span class="text-success">✔ OK</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <div class="fw-semibold mb-2">Steps to Prepare</div>
                            <?php if (($plan['status'] ?? '') === 'no_profile'): ?>
                                <div class="text-muted small">Cannot show steps because no profile is available.</div>
                            <?php elseif (empty($plan['steps'])): ?>
                                <div class="text-muted small">No steps listed for this preparation profile.</div>
                            <?php else: ?>
                                <ul class="list-group">
                                    <?php foreach (($plan['steps'] ?? []) as $step): ?>
                                        <?php
                                            $stepStatus = (string) ($step['status'] ?? 'blocked');
                                            $icon = '❌';
                                            $label = 'Blocked';
                                            if ($stepStatus === 'ready') {
                                                $icon = '✔';
                                                $label = 'Ready';
                                            } elseif ($stepStatus === 'waiting') {
                                                $icon = '⏳';
                                                $label = 'Waiting';
                                            }
                                        ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <div>
                                                <div class="fw-semibold">
                                                    <?= (int) ($step['step_order'] ?? 0) ?>. <?= esc($step['name'] ?? 'Step') ?>
                                                </div>
                                                <?php if (!empty($step['reason'])): ?>
                                                    <div class="small text-muted"><?= esc($step['reason']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="ms-2 <?= $stepStatus === 'blocked' ? 'text-danger' : ($stepStatus === 'waiting' ? 'text-warning' : 'text-success') ?>">
                                                <?= esc($icon . ' ' . $label) ?>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>

                        <?php if (($plan['status'] ?? '') !== 'no_profile' && !empty($plan['steps'])): ?>
                            <div class="row g-3 mb-3">
                                <div class="col-lg-6">
                                    <div class="border rounded p-3 h-100 bg-white">
                                        <div class="fw-semibold mb-2">Send to Vendor</div>
                                        <form method="post" action="<?= site_url('sales-orders/preparation/send-to-vendor') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sales_order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                            <input type="hidden" name="sales_order_line_id" value="<?= (int) ($planLine['id'] ?? 0) ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) ($plan['product_id'] ?? 0) ?>">

                                            <div class="mb-2">
                                                <label class="form-label mb-1">Select Step</label>
                                                <select class="form-select form-select-sm" name="step_id" required>
                                                    <option value="">Select Step</option>
                                                    <?php foreach (($plan['steps'] ?? []) as $stepOpt): ?>
                                                        <option value="<?= (int) ($stepOpt['id'] ?? 0) ?>" <?= (($stepOpt['status'] ?? '') === 'blocked') ? 'disabled' : '' ?>>
                                                            <?= (int) ($stepOpt['step_order'] ?? 0) ?>. <?= esc($stepOpt['name'] ?? 'Step') ?>
                                                            <?php if (($stepOpt['status'] ?? '') === 'blocked'): ?> (Blocked)<?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label mb-1">Select Vendor</label>
                                                <select class="form-select form-select-sm" name="vendor_id" required>
                                                    <option value="">Select Vendor</option>
                                                    <?php foreach (($prepVendors ?? []) as $v): ?>
                                                        <option value="<?= (int) ($v['id'] ?? 0) ?>"><?= esc($v['name'] ?? '-') ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="row g-2 mb-2">
                                                <div class="col-6">
                                                    <label class="form-label mb-1">From Location</label>
                                                    <select class="form-select form-select-sm" name="from_location_id" required>
                                                        <option value="">Select</option>
                                                        <?php foreach (($prepLocations ?? []) as $loc): ?>
                                                            <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' - ' . ($loc['name'] ?? '-')) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label mb-1">To Location</label>
                                                    <select class="form-select form-select-sm" name="to_location_id" required>
                                                        <option value="">Select</option>
                                                        <?php foreach (($prepLocations ?? []) as $loc): ?>
                                                            <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' - ' . ($loc['name'] ?? '-')) ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label mb-1">Enter Qty</label>
                                                <input type="number" class="form-control form-control-sm" name="qty" min="0.0001" step="0.0001" value="<?= esc(number_format((float) ($plan['requested_qty'] ?? 0), 4, '.', '')) ?>" required>
                                            </div>

                                            <button type="submit" class="btn btn-sm btn-outline-primary">Send to Vendor</button>
                                        </form>
                                    </div>
                                </div>

                                <div class="col-lg-6">
                                    <div class="border rounded p-3 h-100 bg-white">
                                        <div class="fw-semibold mb-2">Start In-house Work</div>
                                        <form method="post" action="<?= site_url('sales-orders/preparation/start-inhouse') ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="sales_order_id" value="<?= (int) ($order['id'] ?? 0) ?>">
                                            <input type="hidden" name="sales_order_line_id" value="<?= (int) ($planLine['id'] ?? 0) ?>">
                                            <input type="hidden" name="product_id" value="<?= (int) ($plan['product_id'] ?? 0) ?>">

                                            <div class="mb-2">
                                                <label class="form-label mb-1">Select Step</label>
                                                <select class="form-select form-select-sm" name="step_id" required>
                                                    <option value="">Select Step</option>
                                                    <?php foreach (($plan['steps'] ?? []) as $stepOpt): ?>
                                                        <option value="<?= (int) ($stepOpt['id'] ?? 0) ?>" <?= (($stepOpt['status'] ?? '') === 'blocked') ? 'disabled' : '' ?>>
                                                            <?= (int) ($stepOpt['step_order'] ?? 0) ?>. <?= esc($stepOpt['name'] ?? 'Step') ?>
                                                            <?php if (($stepOpt['status'] ?? '') === 'blocked'): ?> (Blocked)<?php endif; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label mb-1">Location</label>
                                                <select class="form-select form-select-sm" name="location_id" required>
                                                    <option value="">Select Location</option>
                                                    <?php foreach (($prepLocations ?? []) as $loc): ?>
                                                        <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' - ' . ($loc['name'] ?? '-')) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="mb-2">
                                                <label class="form-label mb-1">Enter Qty</label>
                                                <input type="number" class="form-control form-control-sm" name="qty" min="0.0001" step="0.0001" value="<?= esc(number_format((float) ($plan['requested_qty'] ?? 0), 4, '.', '')) ?>" required>
                                            </div>

                                            <button type="submit" class="btn btn-sm btn-outline-success">Start In-house Work</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($plan['has_missing_materials'])): ?>
                            <div>
                                <div class="fw-semibold mb-2">Suggested Actions</div>
                                <a href="<?= site_url('newpurchaseui/rfqs') ?>" class="btn btn-sm btn-outline-primary">
                                    Buy Materials
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
            <h5 class="mb-0 fw-semibold" style="font-size:1rem;">Lines</h5>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggle-discount-tax">
                <label class="form-check-label" for="toggle-discount-tax" style="font-size:0.85rem;">Show Discount &amp; Tax</label>
            </div>
        </div>
        <div class="table-responsive mb-3">
            <table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;">
                <thead>
                    <tr style="white-space:nowrap;">
                        <th style="width:8%">Code</th>
                        <th style="width:5%">Image</th>
                        <th style="width:28%">Product / Description</th>
                        <th style="width:5%">Unit</th>
                        <th style="width:6%" class="text-end">Qty</th>
                        <th style="width:9%" class="text-end">Unit Price</th>
                        <th style="width:7%" class="text-end col-disc">Disc %</th>
                        <th style="width:7%" class="text-end col-disc">Disc Amt</th>
                        <th style="width:6%" class="text-end col-tax">Tax %</th>
                        <th style="width:7%" class="text-end col-tax">Tax Amt</th>
                        <th style="width:7%" class="text-end">Available</th>
                        <th style="width:7%" class="text-end">Shortage</th>
                        <th style="width:7%" class="text-end">Incoming</th>
                        <th style="width:7%" class="text-end">Received</th>
                        <th style="width:7%" class="text-end">Pending</th>
                        <th style="width:7%" class="text-end">Ready Now</th>
                        <th style="width:9%" class="text-end">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($lines)): ?>
                    <?php foreach ($lines as $idx => $l): ?>
                        <?php
                            $img = !empty($l['product_image_url']) ? $l['product_image_url'] : base_url('assets/images/no-image.png');
                            $code = $l['product_code'] ?? ($l['product_id'] ?? '');
                            $productIdentifier = $l['product_identifier'] ?? ($l['product_id'] ?? null);
                            $productUrl = $productIdentifier ? site_url('products/' . $productIdentifier) : null;
                            $lineName = $l['product_name'] ?? $l['name'] ?? '';
                            $lineDesc = $l['description'] ?? '';
                            $lineText = trim($lineName) !== '' ? $lineName : $lineDesc;
                            if (trim($lineText) === '') {
                                $lineText = $code !== '' ? $code : '—';
                            }
                            $discVal = isset($l['discount_value']) ? (float)$l['discount_value'] : null;
                            $discDisplay = $discVal !== null ? rtrim(rtrim(number_format($discVal, 2), '0'), '.') . '%' : '';
                        ?>
                        <tr>
                            <td>
                                <?php if ($productUrl): ?>
                                    <a href="<?= esc($productUrl) ?>" class="text-decoration-none"><?= esc($code) ?></a>
                                <?php else: ?>
                                    <?= esc($code) ?>
                                <?php endif; ?>
                            </td>
                            <td><img src="<?= esc($img) ?>" alt="" style="width:46px;height:36px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>'"></td>
                            <td>
                                <div class="fw-semibold" style="line-height:1.2;">
                                    <?php if ($productUrl): ?>
                                        <a href="<?= esc($productUrl) ?>" class="text-decoration-none"><?= esc($lineText) ?></a>
                                    <?php else: ?>
                                        <?= esc($lineText) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (trim($lineDesc) !== '' && $lineDesc !== $lineText): ?>
                                    <div class="text-muted" style="font-size:0.8rem; line-height:1.2;">
                                        <?= esc($lineDesc) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($l['unit'] ?? 'pcs') ?></td>
                            <td class="text-end"><?= number_format((float)($l['quantity'] ?? 0), 2) ?></td>
                            <td class="text-end"><?= esc($fmtMoney($l['unit_price'] ?? 0)) ?></td>
                            <td class="text-end col-disc"><?= esc($discDisplay) ?></td>
                            <td class="text-end col-disc"><?= number_format((float)($l['discount_amount'] ?? 0), 2) ?></td>
                            <td class="text-end col-tax"><?= isset($l['tax_rate']) ? esc($l['tax_rate']) : '' ?></td>
                            <td class="text-end col-tax"><?= number_format((float)($l['tax_amount'] ?? 0), 2) ?></td>
                            
                            <!-- Phase-1: Available and Shortage Columns -->
                            <?php
                                $isStockable = $l['is_stockable'] ?? true;
                                $available = isset($l['available']) ? (float)$l['available'] : 0;
                                $shortage = isset($l['shortage']) ? (float)$l['shortage'] : 0;
                                $requiredQty = isset($l['required_qty']) ? (float)$l['required_qty'] : (float)($l['quantity'] ?? 0);
                                $shippedQty = isset($l['shipped_qty']) ? (float)$l['shipped_qty'] : 0;
                            ?>
                            <td class="text-end">
                                <?php if ($isFulfillmentClosed): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($isStockable): ?>
                                    <?= number_format($available, 2) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end <?= ($shortage > 0) ? 'text-danger fw-semibold' : '' ?>" title="<?php if ($shortage > 0): ?>Remaining: <?= number_format($requiredQty, 2) ?>, Shipped: <?= number_format($shippedQty, 2) ?>, Available: <?= number_format($available, 2) ?>, Shortage: <?= number_format($shortage, 2) ?><?php endif; ?>">
                                <?php if ($isFulfillmentClosed): ?>
                                    <span class="text-muted">—</span>
                                <?php elseif ($isStockable): ?>
                                    <?php if ($shortage > 0): ?>
                                        <span>−<?= number_format($shortage, 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Phase-3: Fulfillment Columns (Incoming, Received, Pending) -->
                            <?php
                                $incomingQty = $l['incoming_qty'] ?? 0;
                                $receivedQty = $l['received_qty'] ?? 0;
                                $pendingQty = $l['pending_qty'] ?? 0;
                                $poDetails = $l['po_details'] ?? [];
                                $hasPoDetails = !empty($poDetails);
                                $poTooltip = '';
                                if ($hasPoDetails) {
                                    $poLines = [];
                                    foreach ($poDetails as $po) {
                                        $poLines[] = $po['po_number'] . ' (' . number_format($po['po_qty'], 2) . ')';
                                    }
                                    $poTooltip = 'POs: ' . implode(', ', $poLines);
                                }
                            ?>
                            <td class="text-end" title="<?= esc($poTooltip) ?>" style="<?= $hasPoDetails ? 'cursor:help;' : '' ?>">
                                <?= $isFulfillmentClosed ? '—' : ($incomingQty > 0 ? number_format($incomingQty, 2) : '—') ?>
                            </td>
                            <td class="text-end" style="<?= $receivedQty > 0 ? 'color:#28a745;font-weight:bold;' : '' ?>">
                                <?= $isFulfillmentClosed ? '—' : ($receivedQty > 0 ? number_format($receivedQty, 2) : '—') ?>
                            </td>
                            <td class="text-end" style="<?= $pendingQty > 0 ? 'color:#ffc107;font-weight:bold;' : '' ?>">
                                <?= $isFulfillmentClosed ? '—' : ($pendingQty > 0 ? number_format($pendingQty, 2) : '—') ?>
                            </td>

                            <?php $readyQty = isset($l['ready_qty']) ? (float)$l['ready_qty'] : 0; ?>
                            <td class="text-end" style="<?= $readyQty > 0 ? 'color:#16a34a;font-weight:bold;' : '' ?>">
                                <?= $isFulfillmentClosed ? '—' : ($readyQty > 0 ? number_format($readyQty, 2) : '—') ?>
                            </td>
                            
                            <td class="text-end fw-semibold"><?= esc($fmtMoney($l['line_total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="17" class="text-muted">No lines</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end">
            <div style="min-width:320px;" class="table-responsive">
                <table class="table table-sm table-borderless mb-0 so-totals-box">
                    <tbody>
                        <tr>
                            <td class="text-muted">Subtotal</td>
                            <td class="text-end"><?= esc($fmtMoney($subtotal)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Discount</td>
                            <td class="text-end text-danger">-<?= esc($fmtMoney($discountTotal)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Tax</td>
                            <td class="text-end"><?= esc($fmtMoney($taxTotal)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Shipping</td>
                            <td class="text-end"><?= esc($fmtMoney($shipping)) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Est. Shipment Weight</td>
                            <td class="text-end" title="Calculated from product unit weight x remaining quantity to ship">
                                <?= esc($fmtShipmentWeight($estimatedShipmentWeightKg ?? 0)) ?>
                            </td>
                        </tr>
                        <?php if (!empty($orderedWeightKg) && (float)$orderedWeightKg > 0 && (float)$orderedWeightKg !== (float)($estimatedShipmentWeightKg ?? 0)): ?>
                        <tr>
                            <td class="text-muted">Ordered Weight</td>
                            <td class="text-end text-muted"><?= esc($fmtShipmentWeight($orderedWeightKg)) ?></td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td class="fw-bold">Total</td>
                            <td class="text-end fw-bold fs-5"><?= esc($fmtMoney($total)) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Toggle discount and tax columns visibility
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('toggle-discount-tax');
    const cardBody = document.querySelector('.card-body');
    
    if (toggle && cardBody) {
        // Load saved preference from localStorage
        const savedState = localStorage.getItem('show-so-discount-tax');
        if (savedState === 'true') {
            toggle.checked = true;
            cardBody.classList.add('show-discount-tax');
        }
        
        // Listen for changes
        toggle.addEventListener('change', function() {
            if (this.checked) {
                cardBody.classList.add('show-discount-tax');
                localStorage.setItem('show-so-discount-tax', 'true');
            } else {
                cardBody.classList.remove('show-discount-tax');
                localStorage.setItem('show-so-discount-tax', 'false');
            }
        });
    }
});
</script>

<?= $this->include('partials/_document_log') ?>

<!-- Order Progress Modal -->
<div class="modal fade" id="soProgressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a;border:1px solid rgba(255,255,255,.1);">
            <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08);padding:.75rem 1.1rem;">
                <h6 class="modal-title text-light mb-0"><i class="bi bi-diagram-3 me-2"></i>Order Progress</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="soProgressBody" style="padding:1rem 1.1rem;">
                <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
            </div>
        </div>
    </div>
</div>
<script>
(function(){
    const btn = document.getElementById('btnOrderProgress');
    if (!btn) return;
    btn.addEventListener('click', function() {
        const soId = this.dataset.soId;
        const body = document.getElementById('soProgressBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading\u2026</div>';
        new bootstrap.Modal(document.getElementById('soProgressModal')).show();
        fetch('<?= site_url('delivery-orders/progress/so/') ?>' + soId)
            .then(r => r.text())
            .then(html => { body.innerHTML = html; })
            .catch(() => { body.innerHTML = '<p class="text-danger">Failed to load progress.</p>'; });
    });
})();
</script>

<?= $this->endSection() ?>
