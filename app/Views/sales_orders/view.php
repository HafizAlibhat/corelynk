<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Sales Order <?= esc($order['order_number'] ?? ('SO-' . ($order['id'] ?? ''))) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $documentOrderId = (int)($order['id'] ?? 0);

    $soNumber = (string)($order['order_number'] ?? ('SO-' . ($order['id'] ?? '')));
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

    $orderId = (int)($orderId ?? $order['id'] ?? 0);
    $orderId = $orderId > 0 ? $orderId : 0;

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
    // Deep-link to the customer profile from the address panel.
    helper('security');
    $customerRouteId = function_exists('entityRouteIdentifier')
        ? entityRouteIdentifier(is_array($customer ?? null) ? $customer : null)
        : (string)($customer['id'] ?? '');
    $soStatus = strtolower((string)($order['status'] ?? ''));
    // The address is frozen on the document; refreshing it from the customer
    // profile is only offered while nothing downstream exists (server re-checks).
    $canRefreshAddress = in_array($soStatus, ['', 'draft'], true)
        && empty($invoice['id'])
        && empty($existingDo);
    $quickStockLines = [];
    if (!empty($canQuickStock) && !empty($hasShortage)) {
        foreach (($lines ?? []) as $quickLine) {
            $quickShortage = (float)($quickLine['shortage'] ?? 0);
            $quickLineId = (int)($quickLine['id'] ?? 0);
            if (
                $quickShortage <= 0
                || $quickLineId <= 0
                || empty($quickLine['is_stockable'])
                || strtolower((string)($quickLine['display_type'] ?? 'line')) === 'section'
            ) {
                continue;
            }
            $quickName = trim((string)($quickLine['product_name'] ?? $quickLine['name'] ?? $quickLine['description'] ?? ''));
            $quickStockLines[] = [
                'line_id' => $quickLineId,
                'product_id' => (int)($quickLine['product_id'] ?? 0),
                'variant_id' => (int)($quickLine['product_variant_id'] ?? $quickLine['variant_id'] ?? 0) ?: null,
                'code' => (string)($quickLine['product_code'] ?? $quickLine['code'] ?? ''),
                'name' => $quickName !== '' ? $quickName : 'Product',
                'shortage' => $quickShortage,
            ];
        }
    }
?>

<div class="card cl-detail-page cl-sales-order-detail">
    <div class="card-header section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="so-detail-eyebrow">Sales / Order Detail</div>
            <div class="so-document-title-wrap">
                <div class="so-document-type">Sales Order No.</div>
                <h3 class="section-title so-document-number mb-0"><?= esc($soNumber) ?></h3>
            </div>
            <?php $fmtDate = !empty($order['order_date']) ? date('d-m-Y', strtotime($order['order_date'])) : ''; ?>
            <div class="section-sub so-detail-meta">
                <span><i class="bi bi-calendar3"></i><?= esc($fmtDate ?: 'No order date') ?></span>
                <span><i class="bi bi-person"></i><?= esc($customerLabel ?: 'No customer') ?></span>
                <span class="so-currency-chip"><?= esc($currencyCode) ?></span>
            </div>
            <?php
                $soTagDocType = 'sales_order';
                $soTagDocId   = (int)($order['id'] ?? 0);
                $soTagExisting = $tags ?? [];
                $soTagModalId = 'tagModal_' . $soTagDocType . '_' . $soTagDocId;
            ?>
            <?php if ($soTagDocId > 0): ?>
            <!-- Header shows tags only; everything else lives in the manage-tags modal. -->
            <div class="so-detail-tags" id="document-tags-<?= esc($soTagDocType) ?>-<?= $soTagDocId ?>"
                 data-document-type="<?= esc($soTagDocType) ?>" data-document-id="<?= $soTagDocId ?>">
                <div class="so-tag-chips" id="tag_list_<?= $soTagDocType ?>_<?= $soTagDocId ?>">
                    <?php foreach ($soTagExisting as $tag): ?>
                        <span class="tag-badge" data-tag-id="<?= (int)$tag['id'] ?>" data-tag-name="<?= esc($tag['name']) ?>"><?= esc($tag['name']) ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php endif; ?>
        </div>
        <div class="so-header-actions d-flex align-items-center gap-2 ms-auto">
            <?= view('partials/record_nav', [
                'table'   => 'sales_orders',
                'id'      => $order['public_id'] ?? $order['id'],
                'pattern' => 'sales-orders/view/{id}',
                'list'    => 'sales-orders',
            ]) ?>
            <?php if ($soTagDocId > 0): ?>
                <button type="button" class="btn btn-sm btn-outline-secondary so-tag-btn" data-bs-toggle="modal"
                        data-bs-target="#<?= $soTagModalId ?>" title="Manage tags" aria-label="Manage tags">
                    <i class="bi bi-tag"></i>
                    <span class="so-tag-count" data-role="count"<?= empty($soTagExisting) ? ' hidden' : '' ?>><?= count($soTagExisting) ?></span>
                </button>
            <?php endif; ?>
            <?php if (!empty($order['status'])): ?>
                <span class="so-status-badge so-status-<?= esc($soStatus) ?>">Status: <?= esc($order['status']) ?></span>
            <?php endif; ?>

            <?php $soPdfIdentifier = !empty($order['public_id']) ? $order['public_id'] : (int)$order['id']; ?>
            <div class="btn-group" role="group" aria-label="Sales order PDF downloads">
                <a href="<?= site_url('sales-orders/pdf/' . $soPdfIdentifier) ?>" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" title="Download standard sales order PDF">
                    <i class="bi bi-file-earmark-pdf me-1"></i>Download PDF
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false" title="Choose PDF layout">
                    <span class="visually-hidden">Choose PDF layout</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="<?= site_url('sales-orders/pdf/' . $soPdfIdentifier) ?>" target="_blank" rel="noopener">Standard layout</a></li>
                    <li><a class="dropdown-item" href="<?= site_url('sales-orders/pdf/' . $soPdfIdentifier . '?template=odoo_boxed') ?>" target="_blank" rel="noopener">Boxed layout</a></li>
                </ul>
            </div>

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
                <?php if (in_array($existingDo['status'] ?? '', ['confirmed','shipped','delivered'], true)): ?>
                    <a href="<?= site_url('delivery-orders/view/' . (int)$existingDo['id']) ?>" class="btn btn-sm btn-success">
                        <i class="bi bi-truck me-1"></i>View DO (<?= esc($existingDo['do_number'] ?? '') ?>)
                    </a>
                <?php else: ?>
                    <a href="<?= site_url('delivery-orders/view/' . (int)$existingDo['id']) ?>" class="btn btn-sm btn-warning">
                        <i class="bi bi-eye me-1"></i>View Draft DO
                    </a>
                <?php endif; ?>
            <?php elseif (!empty($readyToShip)): ?>
                <span class="badge bg-success-subtle text-success-emphasis so-ready-badge" title="All order lines are in stock and ready to ship">
                    <i class="bi bi-check-circle-fill me-1"></i>Ready to Ship
                </span>
                <form method="post" action="<?= site_url('delivery-orders/create-from-sales-order/' . (int)$order['id']) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-truck me-1"></i>Create Delivery Order
                    </button>
                </form>
            <?php endif; ?>

            <a href="<?= site_url('sales-orders/warehouse-print/' . ($order['public_id'] ?? $order['id'])) ?>" target="_blank"
               class="btn btn-sm btn-outline-secondary" title="Print warehouse pick list">
                <i class="bi bi-printer me-1"></i>Pick List
            </a>

            <!-- Invoice button: surfaced on the header, not buried in the Actions menu -->
            <?php if (!empty($invoice['id'])): ?>
                <a href="<?= site_url('customer-invoices/view/' . $invoice['id']) ?>" class="btn btn-sm btn-success">
                    <i class="bi bi-receipt me-1"></i>View Invoice
                </a>
            <?php else: ?>
                <a href="<?= site_url('sales-orders/invoice/'.$order['id']) ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-receipt-cutoff me-1"></i>Create Invoice
                </a>
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
                <span class="badge <?= $fulfillmentBgColor ?> so-fulfillment-badge" title="<?= $fulfillmentTooltip ?>">
                    <i class="bi <?= $fulfillmentIcon ?> me-1"></i>
                    <?= $fulfillmentLabel ?>
                </span>
            <?php endif; ?>
            
            <!-- Professional Actions Dropdown Menu -->
            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" type="button" id="actionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-lightning-charge-fill me-1"></i>Actions
                </button>
                <ul class="dropdown-menu dropdown-menu-end so-actions-menu" aria-labelledby="actionsDropdown">
                    <!-- Navigation -->
                    <li>
                        <a class="dropdown-item" href="<?= site_url('sales-orders') ?>">
                            <i class="bi bi-arrow-left text-secondary me-2"></i>Back to Orders List
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    
                    <!-- Invoice Actions (create/view live on the header button) -->
                    <?php if (!empty($invoice['id'])): ?>
                        <li>
                            <a class="dropdown-item" href="<?= site_url('customer-invoices/pdf/' . $invoice['id']) ?>" target="_blank">
                                <i class="bi bi-file-pdf text-danger me-2"></i>Download Invoice PDF
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a class="dropdown-item" href="<?= site_url('sales-orders/pdf/' . (!empty($order['public_id']) ? $order['public_id'] : (int)$order['id'])) ?>" target="_blank">
                            <i class="bi bi-file-pdf text-danger me-2"></i>Download Sales Order PDF
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item" href="<?= site_url('sales-orders/print/' . (!empty($order['public_id']) ? $order['public_id'] : (int)$order['id'])) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer me-2"></i>Print Sales Order
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item" href="<?= site_url('sales-orders/warehouse-document/' . (!empty($order['public_id']) ? $order['public_id'] : (int)$order['id'])) ?>" target="_blank">
                            <i class="bi bi-box-seam text-warning me-2"></i>Download Warehouse PDF
                        </a>
                    </li>

                    <li>
                        <a class="dropdown-item" href="<?= site_url('sales-orders/warehouse-print/' . (!empty($order['public_id']) ? $order['public_id'] : (int)$order['id'])) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-printer text-warning me-2"></i>Print Warehouse Pick List
                        </a>
                    </li>
                    
                    <!-- Auto-PO Creation (only if shortage exists) -->
                    <?php if (!empty($hasShortage) && $hasShortage === true): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <h6 class="dropdown-header text-warning">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Stock Actions
                            </h6>
                        </li>
                        <?php if (empty($hasAutoRfq)): ?>
                            <li>
                                <button type="button" id="btnAutoCreateRfq" class="dropdown-item text-warning fw-semibold"
                                        data-url="<?= site_url('sales-orders/create-purchase-drafts/' . ($order['id'] ?? 0)) ?>"
                                        title="Create draft RFQs for shortage items">
                                    <i class="bi bi-magic me-2"></i>Auto-Create RFQ Drafts
                                </button>
                            </li>
                        <?php else: ?>
                            <li>
                                <button type="button" class="dropdown-item text-secondary" disabled title="RFQ drafts already created for this order">
                                    <i class="bi bi-check2-circle me-2"></i>RFQ Drafts Created
                                </button>
                            </li>
                        <?php endif; ?>
                        <?php if (!empty($quickStockLines)): ?>
                            <li>
                                <button type="button" class="dropdown-item text-success fw-semibold js-so-quick-stock" data-line-id="all">
                                    <i class="bi bi-box-arrow-in-down me-2"></i>Quick Add Missing Stock
                                </button>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Undo conversion: only while unconfirmed and nothing downstream has happened -->
                    <?php if (!empty($order['quotation_id']) && in_array($soStatus, ['draft', 'confirmed'], true)): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form method="post" action="<?= site_url('sales-orders/reset-to-quotation/' . (!empty($order['public_id']) ? $order['public_id'] : $documentOrderId)) ?>" class="m-0 so-reset-to-quote-form">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-danger" title="Delete this sales order and revert its quotation back to draft">
                                    <i class="bi bi-arrow-counterclockwise me-2"></i>Reset to Quote
                                </button>
                            </form>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <div class="card-body so-document-body">
        <div class="so-address-card">
            <div class="so-panel-heading">
                <span class="so-panel-icon"><i class="bi bi-geo-alt"></i></span>
                <div><h5>Address &amp; Contact</h5><p>Customer delivery details</p></div>
                <?php if ($customerRouteId !== ''): ?>
                    <a href="<?= site_url('customers/' . $customerRouteId) ?>" class="cl-action-icon ms-auto"
                       title="Open customer profile" aria-label="Open customer profile">
                        <i class="bi bi-person-lines-fill"></i>
                    </a>
                <?php endif; ?>
                <?php if ($canRefreshAddress): ?>
                    <button type="button" class="cl-action-icon<?= $customerRouteId === '' ? ' ms-auto' : '' ?>" id="refreshCustomerAddressBtn"
                            data-url="<?= site_url('sales-orders/refresh-customer-address/' . (!empty($order['public_id']) ? $order['public_id'] : (int)$order['id'])) ?>"
                            title="Update address &amp; contact from the customer profile"
                            aria-label="Update address and contact from the customer profile">
                        <i class="bi bi-arrow-repeat"></i>
                    </button>
                <?php endif; ?>
                <button type="button" class="cl-action-icon<?= $customerRouteId === '' && !$canRefreshAddress ? ' ms-auto' : '' ?>"
                        data-bs-toggle="collapse" data-bs-target="#soAddressDetails" aria-expanded="true"
                        aria-controls="soAddressDetails" title="Show or hide address details" aria-label="Show or hide address details">
                    <i class="bi bi-chevron-up so-collapse-chevron" aria-hidden="true"></i>
                </button>
            </div>
            <div class="collapse show" id="soAddressDetails">
                <address class="so-address-content"><?= nl2br(esc($addrText ?: 'N/A')) ?></address>
            </div>
        </div>

        <!-- Phase-1: Stock Readiness Alert -->
        <?php if (!empty($hasShortage) && $hasShortage === true): ?>
        <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 so-document-alert">
            <i class="bi bi-info-circle-fill"></i>
            <div class="flex-grow-1">
                <?php if (empty($hasAutoRfq)): ?>
                    <strong><?= (int)($shortageCount ?? 0) ?></strong> items pending stock. Use "Auto-Create RFQ Drafts" button to generate RFQs.
                <?php else: ?>
                    <strong><?= (int)($shortageCount ?? 0) ?></strong> items pending stock. RFQ drafts already created for this order.
                <?php endif; ?>
            </div>
            <button type="button" class="btn-close btn-sm" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (!empty($missingVendorItems)): ?>
        <div class="alert alert-warning so-document-alert">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Vendor assignment required before auto RFQ generation</div>
            <div class="small mb-2">Assign a vendor to each product below, then click Auto-Create RFQ Drafts again.</div>
            <ul class="mb-0 ps-3">
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

        <div id="soAutoRfqResult"></div>

        <div class="so-lines-heading d-flex justify-content-between align-items-center gap-3">
            <div class="so-panel-heading mb-0">
                <span class="so-panel-icon"><i class="bi bi-list-check"></i></span>
                <?php $soProductLineCount = count(array_filter($lines ?? [], static fn ($line) => strtolower((string)($line['display_type'] ?? 'line')) !== 'section')); ?>
                <div><h5>Order Lines</h5><p><?= $soProductLineCount ?> item<?= $soProductLineCount === 1 ? '' : 's' ?> on this order</p></div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <?php if (!empty($quickStockLines)): ?>
                    <button type="button" class="btn btn-sm btn-success js-so-quick-stock" data-line-id="all">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Add Missing Stock
                    </button>
                <?php endif; ?>
                <?php if (!in_array($soStatus, ['cancelled', 'canceled'], true)): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#soAddHeadingModal">
                        <i class="bi bi-type-bold me-1"></i>Add Heading
                    </button>
                <?php endif; ?>
                <?php if (count(array_filter($lines ?? [], static fn ($line) => strtolower((string)($line['display_type'] ?? 'line')) === 'section')) > 0): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-so-sections-toggle-all aria-expanded="true">
                        <i class="bi bi-arrows-collapse me-1" aria-hidden="true"></i><span>Collapse All</span>
                    </button>
                <?php endif; ?>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="toggle-discount-tax">
                    <label class="form-check-label" for="toggle-discount-tax">Show Discount &amp; Tax</label>
                </div>
            </div>
        </div>
        <div class="table-responsive so-lines-scroll">
            <table class="table table-sm align-middle so-lines-table">
                <thead>
                    <tr>
                        <th class="text-center so-col-no">No.</th>
                        <th class="so-col-code">Code</th>
                        <th class="so-col-image">Image</th>
                        <th class="so-col-product">Product / Description</th>
                        <th class="so-col-unit">Unit</th>
                        <th class="text-end so-col-quantity">Qty</th>
                        <th class="text-end so-col-money">Unit Price</th>
                        <th class="text-end col-disc so-col-metric">Disc %</th>
                        <th class="text-end col-disc so-col-metric">Disc Amt</th>
                        <th class="text-end col-tax so-col-metric">Tax %</th>
                        <th class="text-end col-tax so-col-metric">Tax Amt</th>
                        <th class="text-end so-col-metric">Available</th>
                        <th class="text-end so-col-metric">Shortage</th>
                        <th class="text-end so-col-metric">Incoming</th>
                        <th class="text-end so-col-metric">Received</th>
                        <th class="text-end so-col-metric">Pending</th>
                        <th class="text-end so-col-metric">Ready Now</th>
                        <th class="text-end so-col-money">Line Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($lines)): ?>
                    <?php
                        $soLineNo = 0;
                        $soSectionSubtotals = [];
                        $soCurrentSectionIndex = null;
                        $soSectionOrdinal = 0;
                        $soCurrentSectionTone = 1;
                        foreach ($lines as $sectionIndex => $sectionLine) {
                            if (strtolower((string)($sectionLine['display_type'] ?? 'line')) === 'section') {
                                $soCurrentSectionIndex = $sectionIndex;
                                $soSectionSubtotals[$sectionIndex] = 0.0;
                                continue;
                            }
                            if ($soCurrentSectionIndex !== null) {
                                $soSectionSubtotals[$soCurrentSectionIndex] += (float)($sectionLine['line_total'] ?? 0);
                            }
                        }
                    ?>
                    <?php foreach ($lines as $idx => $l): ?>
                        <?php
                            $isSection = strtolower((string)($l['display_type'] ?? 'line')) === 'section';
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
                        <?php if ($isSection): ?>
                            <?php
                                $soCurrentSectionIndex = $idx;
                                $soSectionOrdinal++;
                                $soCurrentSectionTone = (($soSectionOrdinal - 1) % 4) + 1;
                            ?>
                            <tr class="so-section-row so-section-tone-<?= $soCurrentSectionTone ?>">
                                <td colspan="18">
                                    <button type="button" class="so-section-content so-section-toggle"
                                            data-so-section-toggle="<?= $soSectionOrdinal ?>" aria-expanded="true"
                                            aria-label="Hide <?= esc($l['section_title'] ?? $l['description'] ?? 'section', 'attr') ?> lines"
                                            title="Hide or show this section">
                                        <span class="so-section-heading-main">
                                            <span class="so-section-marker" aria-hidden="true"><i class="bi bi-folder2-open"></i></span>
                                            <span class="so-section-title"><?= esc($l['section_title'] ?? $l['description'] ?? 'Section') ?></span>
                                        </span>
                                        <i class="bi bi-chevron-up so-section-chevron" aria-hidden="true"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                        <?php $soLineNo++; ?>
                        <tr<?= $soSectionOrdinal > 0 ? ' data-so-section-row="' . $soSectionOrdinal . '"' : '' ?>>
                            <td class="text-center so-line-no"><?= $soLineNo ?></td>
                            <td>
                                <?php if ($productUrl): ?>
                                    <a href="<?= esc($productUrl) ?>" class="text-decoration-none"><?= esc($code) ?></a>
                                <?php else: ?>
                                    <?= esc($code) ?>
                                <?php endif; ?>
                            </td>
                            <td><img src="<?= esc($img) ?>" alt="" class="js-product-hover-thumb so-product-thumb" data-preview-src="<?= esc($img) ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.setAttribute('data-preview-src','<?= base_url('assets/images/no-image.png') ?>');"></td>
                            <td>
                                <div class="fw-semibold so-product-name">
                                    <?php if ($productUrl): ?>
                                        <a href="<?= esc($productUrl) ?>" class="text-decoration-none"><?= esc($lineText) ?></a>
                                    <?php else: ?>
                                        <?= esc($lineText) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($movableSections) && !empty($l['id'])): ?>
                                    <div class="dropdown mt-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle so-move-section-btn"
                                                data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-folder-symlink me-1"></i>Move to section
                                        </button>
                                        <ul class="dropdown-menu">
                                            <?php foreach ($movableSections as $moveSection): ?>
                                                <li>
                                                    <button type="button" class="dropdown-item" data-so-move-line="<?= (int)$l['id'] ?>"
                                                            data-so-target-section="<?= (int)$moveSection['id'] ?>">
                                                        <?= esc($moveSection['section_title'] ?? $moveSection['description'] ?? 'Section') ?>
                                                    </button>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                                <?php if (trim($lineDesc) !== '' && $lineDesc !== $lineText): ?>
                                    <div class="text-muted so-product-description">
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
                                <?php if ($isStockable): ?>
                                    <div class="d-flex flex-column justify-content-end align-items-end">
                                        <div class="d-flex align-items-center gap-1">
                                            <span><?= number_format($available, 2) ?></span>
                                            <?php if ($available > 0 && ($requiredQty - (float)($l["reserved_for_line"] ?? 0)) > 0.0001 && !empty($l["stock_locations"])): ?>
                                                <button type="button" class="btn btn-sm btn-link text-info p-0 js-reserve-line-stock"
                                                        data-line-id="<?= (int)$l["id"] ?>"
                                                        data-product-id="<?= (int)$l["product_id"] ?>"
                                                        data-variant-id="<?= (int)($l["product_variant_id"] ?? 0) ?>"
                                                        data-product-name="<?= esc($lineText, "attr") ?>"
                                                        data-reservable="<?= number_format(min($available, max(0, $requiredQty - (float)($l["reserved_for_line"] ?? 0))), 4, ".", "") ?>"
                                                        data-locations="<?= esc(json_encode($l["stock_locations"]), "attr") ?>"
                                                        title="Reserve stock">
                                                    <i class="bi bi-lock"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($l["reserved_for_line"]) && (float)$l["reserved_for_line"] > 0): ?>
                                            <div class="d-flex align-items-center justify-content-end mt-1 text-info" style="font-size:0.75rem; font-weight:500;" title="Reserved for this line">
                                                <i class="bi bi-lock-fill me-1"></i><?= number_format((float)$l["reserved_for_line"], 2) ?>
                                                <form method="post" action="<?= site_url("sales-orders/release-line-reservation") ?>" class="ms-1 m-0 p-0 d-inline-flex align-items-center" onsubmit="return confirm('Release this reservation?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="sales_order_id" value="<?= (int)$order["id"] ?>">
                                                    <input type="hidden" name="sales_order_line_id" value="<?= (int)$l["id"] ?>">
                                                    <button type="submit" class="btn btn-link text-danger p-0 border-0 ms-1 d-flex align-items-center" title="Release reservation">
                                                        <i class="bi bi-x-circle-fill"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end <?= ($shortage > 0) ? 'text-danger fw-semibold' : '' ?>" title="<?php if ($shortage > 0): ?>Remaining: <?= number_format($requiredQty, 2) ?>, Shipped: <?= number_format($shippedQty, 2) ?>, Available: <?= number_format($available, 2) ?>, Shortage: <?= number_format($shortage, 2) ?><?php endif; ?>">
                                <?php if ($isStockable): ?>
                                    <?php if ($shortage > 0): ?>
                                        <span>−<?= number_format($shortage, 2) ?></span>
                                        <?php if (!empty($canQuickStock) && !empty($l['id'])): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-link text-success p-0 ms-1 js-so-quick-stock"
                                                    data-line-id="<?= (int)$l['id'] ?>"
                                                    aria-label="Add missing stock for <?= esc($lineText, 'attr') ?>"
                                                    title="Quick add this missing stock">
                                                <i class="bi bi-plus-circle-fill"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success-emphasis" title="Fully in stock">
                                            <i class="bi bi-check-circle-fill me-1"></i>In stock
                                        </span>
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
                            <td class="text-end<?= $hasPoDetails ? ' so-help-cell' : '' ?>" title="<?= esc($poTooltip) ?>">
                                <?= $incomingQty > 0 ? number_format($incomingQty, 2) : '—' ?>
                            </td>
                            <td class="text-end<?= $receivedQty > 0 ? ' so-value-positive' : '' ?>">
                                <?= $receivedQty > 0 ? number_format($receivedQty, 2) : '—' ?>
                            </td>
                            <td class="text-end<?= $pendingQty > 0 ? ' so-value-warning' : '' ?>">
                                <?= $pendingQty > 0 ? number_format($pendingQty, 2) : '—' ?>
                            </td>

                            <?php $readyQty = isset($l['ready_qty']) ? (float)$l['ready_qty'] : 0; ?>
                            <td class="text-end<?= $readyQty > 0 ? ' so-value-positive' : '' ?>">
                                <?= $readyQty > 0 ? number_format($readyQty, 2) : '—' ?>
                            </td>
                            
                            <td class="text-end fw-semibold"><?= esc($fmtMoney($l['line_total'] ?? 0)) ?></td>
                        </tr>
                        <?php
                            $soNextLine = $lines[$idx + 1] ?? null;
                            $soEndsSection = $soCurrentSectionIndex !== null
                                && ($soNextLine === null || strtolower((string)($soNextLine['display_type'] ?? 'line')) === 'section');
                        ?>
                        <?php if ($soEndsSection): ?>
                            <tr class="so-section-subtotal-row so-section-tone-<?= $soCurrentSectionTone ?>" data-so-section-row="<?= $soSectionOrdinal ?>">
                                <td colspan="18">
                                    <div class="so-section-subtotal-content">
                                        <span class="so-section-subtotal-label">Section Subtotal</span>
                                        <strong><?= esc($fmtMoney($soSectionSubtotals[$soCurrentSectionIndex] ?? 0)) ?></strong>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="18" class="text-muted">No lines</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="so-totals-wrap">
            <div class="table-responsive">
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
                                <?php if (isset($estimatedShipmentWeightKg) && (float)$estimatedShipmentWeightKg > 0): ?>
                                    <?= esc(\App\Helpers\WeightHelper::formatShipment((float)$estimatedShipmentWeightKg, $shipmentWeightUnit ?? null)) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if (!empty($orderedWeightKg) && (float)$orderedWeightKg > 0 && (float)$orderedWeightKg !== (float)($estimatedShipmentWeightKg ?? 0)): ?>
                        <tr>
                            <td class="text-muted">Ordered Weight</td>
                            <td class="text-end text-muted"><?= esc(\App\Helpers\WeightHelper::formatShipment((float)$orderedWeightKg, $shipmentWeightUnit ?? null)) ?></td>
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

        <?php
            $soHasPrep = ! empty($preparationExecution['blocks']);
            $soHasDocs = ! empty($relatedDocuments);
        ?>
        <?php if ($soHasPrep || $soHasDocs): ?>
        <ul class="nav nav-tabs so-detail-tabs mt-4 mb-3" role="tablist">
            <?php if ($soHasPrep): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?= $soHasPrep ? ' active' : '' ?>" id="so-tab-prep-btn" data-bs-toggle="tab" data-bs-target="#so-tab-prep" type="button" role="tab" aria-controls="so-tab-prep" aria-selected="<?= $soHasPrep ? 'true' : 'false' ?>">
                    <i class="bi bi-gear-wide-connected me-1"></i>Preparation
                </button>
            </li>
            <?php endif; ?>
            <?php if ($soHasDocs): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link<?= ! $soHasPrep ? ' active' : '' ?>" id="so-tab-docs-btn" data-bs-toggle="tab" data-bs-target="#so-tab-docs" type="button" role="tab" aria-controls="so-tab-docs" aria-selected="<?= ! $soHasPrep ? 'true' : 'false' ?>">
                    <i class="bi bi-folder2-open me-1"></i>Documents
                    <span class="badge rounded-pill bg-secondary ms-1"><?= count($relatedDocuments) ?></span>
                </button>
            </li>
            <?php endif; ?>
        </ul>
        <div class="tab-content so-detail-tab-content mb-4">
            <?php if ($soHasPrep): ?>
            <div class="tab-pane fade show active" id="so-tab-prep" role="tabpanel" aria-labelledby="so-tab-prep-btn">
                <?= $this->include('sales_orders/partials/preparation_execution') ?>
            </div>
            <?php endif; ?>
            <?php if ($soHasDocs): ?>
            <div class="tab-pane fade<?= ! $soHasPrep ? ' show active' : '' ?>" id="so-tab-docs" role="tabpanel" aria-labelledby="so-tab-docs-btn">
                <?= $this->include('sales_orders/partials/related_documents') ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="<?= base_url('assets/js/sales_order_sections.js') ?>?v=1" defer></script>
<script>
// Toggle discount and tax columns visibility
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('toggle-discount-tax');
    const cardBody = document.querySelector('.so-document-body');
    
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

    const addHeadingModal = document.getElementById('soAddHeadingModal');
    const addHeadingForm = document.getElementById('soAddHeadingForm');
    const addHeadingInput = document.getElementById('soHeadingTitle');
    const addHeadingSubmit = document.getElementById('soAddHeadingSubmit');
    const addHeadingError = document.getElementById('soAddHeadingError');

    if (addHeadingModal && addHeadingInput) {
        addHeadingModal.addEventListener('shown.bs.modal', function() {
            addHeadingInput.focus();
        });
        addHeadingModal.addEventListener('hidden.bs.modal', function() {
            addHeadingInput.value = '';
            if (addHeadingError) {
                addHeadingError.textContent = '';
                addHeadingError.classList.add('d-none');
            }
        });
    }

    if (addHeadingForm) {
        addHeadingForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const title = (addHeadingInput?.value || '').trim();
            if (!title) {
                addHeadingInput?.focus();
                return;
            }

            const originalHtml = addHeadingSubmit.innerHTML;
            addHeadingSubmit.disabled = true;
            addHeadingSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Adding…';
            if (addHeadingError) addHeadingError.classList.add('d-none');

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = window.csrfHash || csrfMeta?.getAttribute('content') || '';
            fetch(addHeadingForm.dataset.url, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    doc_type: 'sales_order',
                    document_id: <?= json_encode((string)(!empty($order['public_id']) ? $order['public_id'] : $order['id'])) ?>,
                    section_title: title
                })
            })
                .then(function(response) {
                    return response.json().catch(function() { return {}; }).then(function(data) {
                        if (!response.ok || !data.success) {
                            throw new Error(data.error || data.message || 'Could not add the heading.');
                        }
                        return data;
                    });
                })
                .then(function() { window.location.reload(); })
                .catch(function(error) {
                    if (addHeadingError) {
                        addHeadingError.textContent = error.message || 'Could not add the heading.';
                        addHeadingError.classList.remove('d-none');
                    }
                    addHeadingSubmit.disabled = false;
                    addHeadingSubmit.innerHTML = originalHtml;
                });
        });
    }

    document.querySelectorAll('[data-so-move-line]').forEach(function(moveButton) {
        moveButton.addEventListener('click', function() {
            const button = this;
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Moving…';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            const csrfToken = window.csrfHash || csrfMeta?.getAttribute('content') || '';

            fetch(<?= json_encode(site_url('document-lines/move-to-section')) ?>, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    doc_type: 'sales_order',
                    document_id: <?= json_encode((string)(!empty($order['public_id']) ? $order['public_id'] : $order['id'])) ?>,
                    line_id: Number(button.dataset.soMoveLine || 0),
                    section_id: Number(button.dataset.soTargetSection || 0)
                })
            })
                .then(function(response) {
                    return response.json().catch(function() { return {}; }).then(function(data) {
                        if (!response.ok || !data.success) throw new Error(data.error || 'Could not move the item.');
                    });
                })
                .then(function() { window.location.reload(); })
                .catch(function(error) {
                    alert(error.message || 'Could not move the item.');
                    button.disabled = false;
                    button.textContent = originalText;
                });
        });
    });

    const resetForm = document.querySelector('.so-reset-to-quote-form');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            if (!confirm('Reset to Quote will permanently delete this sales order and revert it back to a draft quotation. This cannot be undone. Continue?')) {
                e.preventDefault();
            }
        });
    }
});
</script>

<?= $this->include('partials/_document_log') ?>

<?php if (!in_array($soStatus, ['cancelled', 'canceled'], true)): ?>
<div class="modal fade" id="soAddHeadingModal" tabindex="-1" aria-labelledby="soAddHeadingModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="soAddHeadingForm" data-url="<?= site_url('document-lines/add-section') ?>">
                <div class="modal-header">
                    <h5 class="modal-title" id="soAddHeadingModalLabel"><i class="bi bi-type-bold me-2"></i>Add Order-Line Heading</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label" for="soHeadingTitle">Heading title</label>
                    <input type="text" class="form-control" id="soHeadingTitle" maxlength="255" required autocomplete="off"
                           placeholder="For example: Packing &amp; Shipping">
                    <div class="form-text">The new heading is added after the existing order lines. It does not change quantities, prices, tax, stock, or totals.</div>
                    <div class="alert alert-danger mt-3 mb-0 d-none" id="soAddHeadingError" role="alert"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="soAddHeadingSubmit">
                        <i class="bi bi-plus-lg me-1"></i>Add Heading
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($quickStockLines)): ?>
<div class="modal fade" id="soQuickStockModal" tabindex="-1" aria-labelledby="soQuickStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="soQuickStockModalLabel"><i class="bi bi-box-arrow-in-down me-2"></i>Quick Add Missing Stock</h5>
                    <div class="small text-muted">Sales order <?= esc($soNumber) ?> — adds stock through the audited Stock Adjustment workflow.</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="soQuickStockAlert" class="alert d-none" role="alert"></div>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="soQuickStockWarehouse">Default warehouse</label>
                        <select class="form-select" id="soQuickStockWarehouse">
                            <option value="">Choose per item</option>
                        </select>
                        <div class="form-text">Applied to all rows unless a row has its own warehouse.</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="soQuickStockLocation">Default internal location</label>
                        <select class="form-select" id="soQuickStockLocation" disabled>
                            <option value="">Choose per item</option>
                        </select>
                        <div class="form-text">For example Box 10; individual rows can override it with Box 30.</div>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <div class="small text-muted">Quantity defaults to the current missing amount. Vendor and cost are required for every selected line.</div>
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="soQuickStockSelectAll" checked>
                        <label class="form-check-label" for="soQuickStockSelectAll">Select all</label>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th scope="col" class="text-center">Add</th>
                                <th scope="col">Code / Product</th>
                                <th scope="col" class="text-end">Missing</th>
                                <th scope="col">Qty to add</th>
                                <th scope="col">Warehouse / Location</th>
                                <th scope="col">Vendor</th>
                                <th scope="col">Cost price</th>
                            </tr>
                        </thead>
                        <tbody id="soQuickStockLines"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="soQuickStockSubmit">
                    <i class="bi bi-check2-circle me-1"></i>Add Selected Stock
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Order Progress Modal -->
<div class="modal fade" id="soProgressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content so-progress-modal-content">
            <div class="modal-header">
                <h6 class="modal-title text-light mb-0"><i class="bi bi-diagram-3 me-2"></i>Order Progress</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="soProgressBody">
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
        const soId = btn.dataset.soId;
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

<?php if (!empty($soTagModalId)): ?>
            <div class="modal fade so-tag-modal" id="<?= $soTagModalId ?>" tabindex="-1" aria-labelledby="<?= $soTagModalId ?>_label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="<?= $soTagModalId ?>_label"><i class="bi bi-tag me-2"></i>Tags</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="so-tag-applied-wrap">
                                <div class="so-tag-section-label">Applied</div>
                                <div class="so-tag-applied" data-role="applied"></div>
                            </div>
                            <label class="visually-hidden" for="<?= $soTagModalId ?>_search">Search or create a tag</label>
                            <input type="text" class="form-control form-control-sm mt-3" id="<?= $soTagModalId ?>_search"
                                   data-role="search" placeholder="Search tags, or type a new one" autocomplete="off">
                            <button type="button" class="btn btn-sm btn-primary w-100 mt-2" data-role="create" hidden></button>
                            <div class="so-tag-section-label mt-3">Available</div>
                            <div class="so-tag-available" data-role="available"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Done</button>
                        </div>
                    </div>
                </div>
            </div>
            <script>
            (function(){
                const root = document.getElementById('document-tags-<?= esc($soTagDocType) ?>-<?= $soTagDocId ?>');
                const modal = document.getElementById('<?= $soTagModalId ?>');
                if (!root || !modal) return;

                const docType = root.dataset.documentType;
                const docId = root.dataset.documentId;
                const chips = root.querySelector('.so-tag-chips');
                const countBadge = document.querySelector('.so-tag-btn [data-role="count"]');
                const applied = modal.querySelector('[data-role="applied"]');
                const available = modal.querySelector('[data-role="available"]');
                const search = modal.querySelector('[data-role="search"]');
                const createBtn = modal.querySelector('[data-role="create"]');
                const DOC_URL = '<?= site_url('document-tags') ?>';
                const TAGS_URL = '<?= site_url('tags') ?>';

                let current = [];   // tags on this document
                let all = [];       // tags that exist in the system

                const norm = (s) => String(s || '').trim().toLowerCase();

                function chip(tag, removable){
                    const el = document.createElement('span');
                    el.className = 'tag-badge';
                    el.dataset.tagId = tag.id;
                    el.dataset.tagName = tag.name;
                    el.textContent = tag.name;
                    if (removable) {
                        const x = document.createElement('button');
                        x.type = 'button';
                        x.className = 'tag-remove-btn';
                        x.setAttribute('aria-label', 'Remove ' + tag.name);
                        x.innerHTML = '&times;';
                        x.addEventListener('click', () => removeTag(tag.id));
                        el.appendChild(x);
                    }
                    return el;
                }

                function renderHeader(){
                    chips.innerHTML = '';
                    current.forEach(t => chips.appendChild(chip(t, false)));
                    if (countBadge) {
                        countBadge.textContent = current.length;
                        countBadge.hidden = current.length === 0;
                    }
                }

                function renderModal(){
                    applied.innerHTML = '';
                    if (!current.length) {
                        applied.innerHTML = '<span class="so-tag-hint">No tags on this order yet</span>';
                    } else {
                        current.forEach(t => applied.appendChild(chip(t, true)));
                    }

                    const q = norm(search.value);
                    const used = new Set(current.map(t => norm(t.name)));
                    const options = all.filter(t => !used.has(norm(t.name)) && (q === '' || norm(t.name).indexOf(q) !== -1));

                    available.innerHTML = '';
                    if (!options.length) {
                        available.innerHTML = '<span class="so-tag-hint">' + (q ? 'No matching tag' : 'No other tags yet') + '</span>';
                    } else {
                        options.forEach(t => {
                            const row = document.createElement('button');
                            row.type = 'button';
                            row.className = 'so-tag-option';
                            row.innerHTML = '<span>' + t.name.replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c])) + '</span>';
                            if (t.usage_count) {
                                const n = document.createElement('small');
                                n.textContent = t.usage_count;
                                row.appendChild(n);
                            }
                            row.addEventListener('click', () => addTag(t.name));
                            available.appendChild(row);
                        });
                    }

                    // "create on the fly" only when the typed name does not exist yet
                    const exact = q !== '' && all.some(t => norm(t.name) === q);
                    if (q !== '' && !exact && !used.has(q)) {
                        createBtn.hidden = false;
                        createBtn.textContent = 'Create "' + search.value.trim() + '"';
                    } else {
                        createBtn.hidden = true;
                    }
                }

                function loadCurrent(){
                    return fetch(DOC_URL + '?document_type=' + docType + '&document_id=' + docId)
                        .then(r => r.json())
                        .then(d => { current = (d && d.success && Array.isArray(d.data)) ? d.data : []; })
                        .catch(() => { current = []; });
                }

                function loadAll(){
                    return fetch(TAGS_URL + '?q=&limit=100')
                        .then(r => r.json())
                        .then(d => { all = (d && d.success && Array.isArray(d.data)) ? d.data : []; })
                        .catch(() => { all = []; });
                }

                function refresh(){
                    return Promise.all([loadCurrent(), loadAll()]).then(() => { renderHeader(); renderModal(); });
                }

                function addTag(name){
                    name = String(name || '').trim();
                    if (!name) return;
                    fetch(DOC_URL, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            document_type: docType,
                            document_id: docId,
                            tags: current.map(t => t.name).concat([name]),
                        }),
                    })
                        .then(r => r.json())
                        .then(d => {
                            if (!d || !d.success) { alert('Failed to add tag: ' + ((d && d.message) || 'Unknown error')); return; }
                            search.value = '';
                            return refresh();
                        })
                        .catch(() => alert('Error adding tag'));
                }

                function removeTag(tagId){
                    fetch(DOC_URL, {
                        method: 'DELETE',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ document_type: docType, document_id: docId, tag_id: tagId }),
                    })
                        .then(r => r.json())
                        .then(d => {
                            if (!d || !d.success) { alert('Failed to remove tag: ' + ((d && d.message) || 'Unknown error')); return; }
                            return refresh();
                        })
                        .catch(() => alert('Error removing tag'));
                }

                search.addEventListener('input', renderModal);
                search.addEventListener('keydown', function(e){
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const q = norm(search.value);
                    if (!q) return;
                    const match = all.find(t => norm(t.name) === q);
                    addTag(match ? match.name : search.value.trim());
                });
                createBtn.addEventListener('click', () => addTag(search.value));

                modal.addEventListener('shown.bs.modal', function(){ refresh().then(() => search.focus()); });

                refresh();
            })();
            </script>
<?php endif; ?>


<script>
// Pull the customer's current primary address/contact onto this order. The
// endpoint refuses the request if the order is no longer a draft.
document.getElementById('refreshCustomerAddressBtn')?.addEventListener('click', function () {
    const btn = this;
    if (!confirm("Update this order's address & contact from the customer profile?")) return;
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

// Auto-Create RFQ Drafts: runs via AJAX so success/failure (including missing
// vendor assignments) is shown inline immediately, without relying on a
// full-page redirect + flash message.
function soEscapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str == null ? '' : String(str);
    return div.innerHTML;
}

document.getElementById('btnAutoCreateRfq')?.addEventListener('click', function () {
    const btn = this;
    const resultBox = document.getElementById('soAutoRfqResult');
    const originalHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating RFQ drafts…';

    fetch(btn.dataset.url, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfHash || ''
        }
    })
        .then(r => r.json().catch(() => ({ success: false, message: 'Unexpected server response.' })))
        .then(resp => {
            let html = '';
            if (resp.success) {
                html = '<div class="alert alert-success so-document-alert d-flex align-items-center gap-2">'
                    + '<i class="bi bi-check-circle-fill"></i><div>' + soEscapeHtml(resp.message || 'RFQ drafts created.') + '</div></div>';
            } else if (resp.nothing_to_purchase) {
                html = '<div class="alert alert-info so-document-alert d-flex align-items-center gap-2">'
                    + '<i class="bi bi-info-circle-fill"></i><div>' + soEscapeHtml(resp.message || 'Nothing to purchase.') + '</div></div>';
            } else {
                html = '<div class="alert alert-danger so-document-alert">'
                    + '<div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Could not create RFQ drafts</div>'
                    + '<div class="small mb-2">' + soEscapeHtml(resp.message || 'Something went wrong.') + '</div>';
                if (Array.isArray(resp.missing_vendor_items) && resp.missing_vendor_items.length) {
                    html += '<ul class="mb-0 ps-3">';
                    resp.missing_vendor_items.forEach(function (mv) {
                        const code = soEscapeHtml(mv.code || 'Unknown Product');
                        html += '<li>' + code;
                        if (mv.product_id) {
                            html += ' - <a href="<?= site_url('products/') ?>' + mv.product_id + '/edit">Assign Vendor</a>';
                        }
                        html += '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
            }
            resultBox.innerHTML = html;

            if (resp.success && (resp.created_count ?? 0) > 0) {
                setTimeout(() => location.reload(), 1200);
            } else {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            }
        })
        .catch(() => {
            resultBox.innerHTML = '<div class="alert alert-danger so-document-alert">Could not reach the server. Please try again.</div>';
            btn.disabled = false;
            btn.innerHTML = originalHtml;
        });
});
</script>

<?php if (!empty($quickStockLines)): ?>
<script>
(function () {
    const lines = <?= json_encode($quickStockLines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    const modalElement = document.getElementById('soQuickStockModal');
    const tbody = document.getElementById('soQuickStockLines');
    const warehouseSelect = document.getElementById('soQuickStockWarehouse');
    const locationSelect = document.getElementById('soQuickStockLocation');
    const selectAll = document.getElementById('soQuickStockSelectAll');
    const submitButton = document.getElementById('soQuickStockSubmit');
    const alertBox = document.getElementById('soQuickStockAlert');
    const endpoints = {
        warehouses: <?= json_encode(site_url('inventory/adjustments/warehouses')) ?>,
        vendors: <?= json_encode(site_url('inventory/adjustments/vendor-search')) ?>,
        linkedVendors: <?= json_encode(site_url('inventory/adjustments/product-vendors')) ?>,
        save: <?= json_encode(site_url('inventory/adjustments/sales-order/' . (!empty($order['public_id']) ? $order['public_id'] : $documentOrderId))) ?>
    };
    let warehouseData = null;
    let requestKey = '';

    function newRequestKey() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID().replace(/-/g, '');
        }
        return String(Date.now()) + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
    }

    function showAlert(message, type) {
        alertBox.className = 'alert alert-' + (type || 'danger');
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function clearAlert() {
        alertBox.textContent = '';
        alertBox.className = 'alert d-none';
    }

    function buildCell(row, text, className) {
        const cell = document.createElement('td');
        if (className) cell.className = className;
        cell.textContent = text;
        row.appendChild(cell);
        return cell;
    }

    function loadVendors(line, select, costInput) {
        const linkedUrl = endpoints.linkedVendors + '?product_id=' + encodeURIComponent(line.product_id);
        const allUrl = endpoints.vendors + '?q=&product_id=' + encodeURIComponent(line.product_id);
        Promise.all([
            fetch(linkedUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}}).then(r => r.json()),
            fetch(allUrl, {credentials: 'same-origin', headers: {'Accept': 'application/json'}}).then(r => r.json())
        ]).then(function (responses) {
            const linked = responses[0] && responses[0].success ? (responses[0].vendors || []) : [];
            const all = responses[1] && responses[1].success ? (responses[1].vendors || []) : [];
            const linkedById = new Map(linked.map(v => [String(v.id), v]));
            const merged = new Map();
            linked.forEach(v => merged.set(String(v.id), Object.assign({}, v, {is_linked: true})));
            all.forEach(v => {
                const key = String(v.id);
                if (!merged.has(key)) merged.set(key, v);
            });

            select.innerHTML = '<option value="">Select vendor</option>';
            merged.forEach(function (vendor) {
                const option = document.createElement('option');
                option.value = vendor.id;
                option.textContent = vendor.name + (vendor.is_linked ? ' (linked)' : '');
                select.appendChild(option);
            });
            select.disabled = false;
            if (linked.length === 1) {
                select.value = String(linked[0].id);
                if (Number(linked[0].last_cost) > 0) costInput.value = Number(linked[0].last_cost).toFixed(4);
            }
            select.addEventListener('change', function () {
                const vendor = linkedById.get(select.value);
                if (vendor && Number(vendor.last_cost) > 0 && !costInput.value) {
                    costInput.value = Number(vendor.last_cost).toFixed(4);
                }
            });
        }).catch(function () {
            select.innerHTML = '<option value="">Could not load vendors</option>';
            select.disabled = false;
        });
    }

    function renderLines(targetLineId) {
        tbody.innerHTML = '';
        lines.forEach(function (line) {
            const row = document.createElement('tr');
            row.dataset.lineId = line.line_id;

            const checkCell = document.createElement('td');
            checkCell.className = 'text-center';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'form-check-input js-quick-stock-check';
            checkbox.checked = targetLineId === 'all' || String(line.line_id) === String(targetLineId);
            checkbox.setAttribute('aria-label', 'Select ' + line.name);
            checkCell.appendChild(checkbox);
            row.appendChild(checkCell);

            const productCell = document.createElement('td');
            const code = document.createElement('div');
            code.className = 'fw-semibold';
            code.textContent = line.code || ('Product #' + line.product_id);
            const name = document.createElement('div');
            name.className = 'small text-muted';
            name.textContent = line.name;
            productCell.append(code, name);
            row.appendChild(productCell);
            buildCell(row, Number(line.shortage).toFixed(2), 'text-end fw-semibold text-danger');

            const qtyCell = document.createElement('td');
            const qtyInput = document.createElement('input');
            qtyInput.type = 'number';
            qtyInput.className = 'form-control form-control-sm js-quick-stock-qty';
            qtyInput.min = '0.0001';
            qtyInput.max = String(line.shortage);
            qtyInput.step = '0.0001';
            qtyInput.value = Number(line.shortage).toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
            qtyInput.setAttribute('aria-label', 'Quantity to add for ' + line.name);
            qtyCell.appendChild(qtyInput);
            row.appendChild(qtyCell);

            const destinationCell = document.createElement('td');
            const rowWarehouse = document.createElement('select');
            rowWarehouse.className = 'form-select form-select-sm js-quick-stock-warehouse mb-1';
            rowWarehouse.setAttribute('aria-label', 'Warehouse for ' + line.name);
            rowWarehouse.innerHTML = '<option value="">Loading warehouses…</option>';
            const rowLocation = document.createElement('select');
            rowLocation.className = 'form-select form-select-sm js-quick-stock-location';
            rowLocation.setAttribute('aria-label', 'Location for ' + line.name);
            rowLocation.innerHTML = '<option value="">Use default location</option>';
            rowLocation.disabled = true;
            rowWarehouse.addEventListener('change', function () { refreshRowLocations(row); });
            destinationCell.append(rowWarehouse, rowLocation);
            row.appendChild(destinationCell);

            const vendorCell = document.createElement('td');
            const vendorSelect = document.createElement('select');
            vendorSelect.className = 'form-select form-select-sm js-quick-stock-vendor';
            vendorSelect.disabled = true;
            vendorSelect.innerHTML = '<option value="">Loading vendors…</option>';
            vendorSelect.setAttribute('aria-label', 'Vendor for ' + line.name);
            vendorCell.appendChild(vendorSelect);
            row.appendChild(vendorCell);

            const costCell = document.createElement('td');
            const costInput = document.createElement('input');
            costInput.type = 'number';
            costInput.className = 'form-control form-control-sm js-quick-stock-cost';
            costInput.min = '0.0001';
            costInput.step = '0.0001';
            costInput.placeholder = '0.0000';
            costInput.setAttribute('aria-label', 'Cost price for ' + line.name);
            costCell.appendChild(costInput);
            row.appendChild(costCell);

            tbody.appendChild(row);
            loadVendors(line, vendorSelect, costInput);
        });
        selectAll.checked = targetLineId === 'all';
        selectAll.indeterminate = false;
        if (warehouseData) populateRowDestinations();
    }

    function loadWarehouses() {
        if (warehouseData) return Promise.resolve(warehouseData);
        return fetch(endpoints.warehouses, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(r => r.json())
            .then(function (data) {
                if (!data || !data.success) throw new Error('Could not load warehouses.');
                warehouseData = data;
                warehouseSelect.innerHTML = '<option value="">Choose per item</option>';
                (data.warehouses || []).forEach(function (warehouse) {
                    const option = document.createElement('option');
                    option.value = warehouse.id;
                    option.textContent = warehouse.name;
                    warehouseSelect.appendChild(option);
                });
                populateRowDestinations();
                return data;
            });
    }

    function populateRowDestinations() {
        if (!warehouseData) return;
        tbody.querySelectorAll('tr').forEach(function (row) {
            const select = row.querySelector('.js-quick-stock-warehouse');
            const currentValue = select.value;
            select.innerHTML = '<option value="">Use default warehouse</option>';
            (warehouseData.warehouses || []).forEach(function (warehouse) {
                const option = document.createElement('option');
                option.value = warehouse.id;
                option.textContent = warehouse.name;
                select.appendChild(option);
            });
            select.value = currentValue;
            refreshRowLocations(row);
        });
    }

    function refreshRowLocations(row) {
        const rowWarehouse = row.querySelector('.js-quick-stock-warehouse');
        const rowLocation = row.querySelector('.js-quick-stock-location');
        const warehouseId = Number(rowWarehouse.value || warehouseSelect.value || 0);
        const currentValue = rowLocation.value;
        rowLocation.innerHTML = '<option value="">Use default location</option>';
        rowLocation.disabled = !warehouseData || !warehouseId;
        if (!warehouseData || !warehouseId) return;
        (warehouseData.locations || [])
            .filter(location => Number(location.warehouse_id) === warehouseId && !location.is_vendor)
            .forEach(function (location) {
                const option = document.createElement('option');
                option.value = location.id;
                option.textContent = location.name;
                rowLocation.appendChild(option);
            });
        if (Array.from(rowLocation.options).some(option => option.value === currentValue)) {
            rowLocation.value = currentValue;
        }
    }

    function refreshLocations() {
        const warehouseId = Number(warehouseSelect.value || 0);
        const previousLocation = locationSelect.value;
        locationSelect.innerHTML = '<option value="">Choose per item</option>';
        locationSelect.disabled = !warehouseId;
        if (warehouseData && warehouseId) {
            (warehouseData.locations || [])
                .filter(location => Number(location.warehouse_id) === warehouseId && !location.is_vendor)
                .forEach(function (location) {
                    const option = document.createElement('option');
                    option.value = location.id;
                    option.textContent = location.name;
                    locationSelect.appendChild(option);
                });
            if (Array.from(locationSelect.options).some(option => option.value === previousLocation)) {
                locationSelect.value = previousLocation;
            }
        }
        tbody.querySelectorAll('tr').forEach(function (row) {
            if (!row.querySelector('.js-quick-stock-warehouse').value) refreshRowLocations(row);
        });
    }

    document.querySelectorAll('.js-so-quick-stock').forEach(function (button) {
        button.addEventListener('click', function () {
            clearAlert();
            requestKey = newRequestKey();
            renderLines(button.dataset.lineId || 'all');
            loadWarehouses().then(function () {
                populateRowDestinations();
                refreshLocations();
            }).catch(error => showAlert(error.message, 'danger'));
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    });

    warehouseSelect.addEventListener('change', refreshLocations);
    selectAll.addEventListener('change', function () {
        tbody.querySelectorAll('.js-quick-stock-check').forEach(check => { check.checked = selectAll.checked; });
        selectAll.indeterminate = false;
    });
    tbody.addEventListener('change', function (event) {
        if (!event.target.classList.contains('js-quick-stock-check')) return;
        const checks = Array.from(tbody.querySelectorAll('.js-quick-stock-check'));
        const selectedCount = checks.filter(check => check.checked).length;
        selectAll.checked = selectedCount === checks.length;
        selectAll.indeterminate = selectedCount > 0 && selectedCount < checks.length;
    });

    submitButton.addEventListener('click', function () {
        clearAlert();
        const warehouseId = Number(warehouseSelect.value || 0);
        const locationId = Number(locationSelect.value || 0);

        const selectedLines = [];
        let error = '';
        tbody.querySelectorAll('tr').forEach(function (row) {
            if (error) return;
            if (!row.querySelector('.js-quick-stock-check').checked) return;
            const source = lines.find(line => String(line.line_id) === row.dataset.lineId);
            const qty = Number(row.querySelector('.js-quick-stock-qty').value);
            const vendorId = Number(row.querySelector('.js-quick-stock-vendor').value || 0);
            const unitCost = Number(row.querySelector('.js-quick-stock-cost').value);
            const rowWarehouseId = Number(row.querySelector('.js-quick-stock-warehouse').value || 0);
            const rowLocationId = Number(row.querySelector('.js-quick-stock-location').value || 0);
            const resolvedWarehouseId = rowWarehouseId || warehouseId;
            const resolvedLocationId = rowLocationId
                || ((!rowWarehouseId || rowWarehouseId === warehouseId) ? locationId : 0);
            if (!resolvedWarehouseId || !resolvedLocationId) {
                error = 'Select a warehouse and internal location for ' + (source.code || source.name) + '.';
                return;
            }
            if (!Number.isFinite(qty) || qty <= 0 || qty > Number(source.shortage) + 0.00001) {
                error = 'Quantity must be greater than zero and cannot exceed the shown missing quantity.';
                return;
            }
            if (!vendorId) {
                error = 'Select a vendor for every selected item.';
                return;
            }
            if (!Number.isFinite(unitCost) || unitCost <= 0) {
                error = 'Enter a positive cost price for every selected item.';
                return;
            }
            selectedLines.push({
                sales_order_line_id: source.line_id,
                qty: qty,
                vendor_id: vendorId,
                unit_cost: unitCost,
                warehouse_id: resolvedWarehouseId,
                location_id: resolvedLocationId
            });
        });
        if (error) {
            showAlert(error, 'warning');
            return;
        }
        if (!selectedLines.length) {
            showAlert('Select at least one missing item.', 'warning');
            return;
        }

        submitButton.disabled = true;
        const originalHtml = submitButton.innerHTML;
        submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Adding stock…';
        fetch(endpoints.save, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash || document.querySelector('meta[name="csrf-token"]')?.content || ''
            },
            body: JSON.stringify({
                request_key: requestKey,
                warehouse_id: warehouseId,
                location_id: locationId,
                lines: selectedLines
            })
        }).then(function (response) {
            return response.json().catch(() => ({})).then(data => ({ok: response.ok, data: data}));
        }).then(function (result) {
            if (!result.ok || !result.data.success) {
                throw new Error(result.data.message || 'Quick stock entry failed. No stock was added.');
            }
            showAlert(result.data.message || 'Stock added successfully.', 'success');
            setTimeout(() => window.location.reload(), 700);
        }).catch(function (error) {
            showAlert(error.message || 'Quick stock entry failed. No stock was added.', 'danger');
            submitButton.disabled = false;
            submitButton.innerHTML = originalHtml;
        });
    });
})();
</script>
<?php endif; ?>
<!-- Direct Stock Reservation Modal -->
<div class="modal fade" id="soReserveLineModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= site_url('sales-orders/reserve-line') ?>" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="sales_order_id" value="<?= (int)$order['id'] ?>">
            <input type="hidden" name="sales_order_line_id" id="so_r_line_id" value="0">
            <input type="hidden" name="product_id" id="so_r_product_id" value="0">
            <input type="hidden" name="variant_id" id="so_r_variant_id" value="0">
            
            <div class="modal-header border-bottom-0 pb-0">
                <h6 class="modal-title">Reserve product: <span id="so_r_title"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Quantity to reserve</label>
                    <div class="input-group input-group-sm">
                        <input type="number" step="0.0001" min="0.0001" name="quantity" id="so_r_qty" class="form-control" required>
                        <span class="input-group-text">pcs</span>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Available from</label>
                    <select name="location_id" id="so_r_location" class="form-select form-select-sm" required>
                    </select>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-sm btn-info"><i class="bi bi-lock me-1"></i>Reserve</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.body.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-reserve-line-stock');
        if (btn) {
            e.preventDefault();
            document.getElementById('so_r_line_id').value = btn.dataset.lineId;
            document.getElementById('so_r_product_id').value = btn.dataset.productId;
            document.getElementById('so_r_variant_id').value = btn.dataset.variantId;
            document.getElementById('so_r_title').textContent = btn.dataset.productName;
            
            const maxQty = parseFloat(btn.dataset.reservable);
            const qtyInput = document.getElementById('so_r_qty');
            qtyInput.max = maxQty;
            qtyInput.value = maxQty;
            
            const locationSelect = document.getElementById('so_r_location');
            locationSelect.innerHTML = '';
            const locations = JSON.parse(btn.dataset.locations || '[]');
            locations.forEach(function (loc) {
                const availableForOrder = parseFloat(loc.available_for_order || 0);
                if (availableForOrder > 0.00001) {
                    const opt = document.createElement('option');
                    opt.value = loc.location_id;
                    opt.textContent = (loc.warehouse_name || '') + ' / ' + (loc.location_name || '') + ' (' + availableForOrder.toFixed(2) + ' pcs unreserved)';
                    locationSelect.appendChild(opt);
                }
            });
            
            new bootstrap.Modal(document.getElementById('soReserveLineModal')).show();
        }
    });
});
</script>
<?= $this->endSection() ?>







