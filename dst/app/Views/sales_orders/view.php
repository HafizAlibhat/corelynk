<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Sales Order <?= esc($order['order_number'] ?? ('SO-' . ($order['id'] ?? ''))) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $documentOrderId = (int)($order['id'] ?? 0);

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
?>

<div class="card cl-detail-page cl-sales-order-detail">
    <div class="card-header section-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <div class="so-detail-eyebrow">Sales / Order Detail</div>
            <h3 class="section-title mb-0">Sales Order <?= esc($order['order_number'] ?? ('SO-' . ($order['id'] ?? ''))) ?></h3>
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
                <form method="post" action="<?= site_url('delivery-orders/create-from-sales-order/' . (int)$order['id']) ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-truck me-1"></i>Create Delivery Order
                    </button>
                </form>
            <?php endif; ?>
            
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
                                <form method="post" action="<?= site_url('sales-orders/create-purchase-drafts/' . ($order['id'] ?? 0)) ?>" class="m-0">
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
            </div>
            <address class="so-address-content"><?= nl2br(esc($addrText ?: 'N/A')) ?></address>
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

        <div class="so-lines-heading d-flex justify-content-between align-items-center gap-3">
            <div class="so-panel-heading mb-0">
                <span class="so-panel-icon"><i class="bi bi-list-check"></i></span>
                <div><h5>Order Lines</h5><p><?= count($lines ?? []) ?> item<?= count($lines ?? []) === 1 ? '' : 's' ?> on this order</p></div>
            </div>
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" id="toggle-discount-tax">
                <label class="form-check-label" for="toggle-discount-tax">Show Discount &amp; Tax</label>
            </div>
        </div>
        <div class="table-responsive so-lines-scroll">
            <table class="table table-sm align-middle so-lines-table">
                <thead>
                    <tr>
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
                            <td><img src="<?= esc($img) ?>" alt="" class="js-product-hover-thumb so-product-thumb" data-preview-src="<?= esc($img) ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.setAttribute('data-preview-src','<?= base_url('assets/images/no-image.png') ?>');"></td>
                            <td>
                                <div class="fw-semibold so-product-name">
                                    <?php if ($productUrl): ?>
                                        <a href="<?= esc($productUrl) ?>" class="text-decoration-none"><?= esc($lineText) ?></a>
                                    <?php else: ?>
                                        <?= esc($lineText) ?>
                                    <?php endif; ?>
                                </div>
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
                                    <?= number_format($available, 2) ?>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end <?= ($shortage > 0) ? 'text-danger fw-semibold' : '' ?>" title="<?php if ($shortage > 0): ?>Remaining: <?= number_format($requiredQty, 2) ?>, Shipped: <?= number_format($shippedQty, 2) ?>, Available: <?= number_format($available, 2) ?>, Shortage: <?= number_format($shortage, 2) ?><?php endif; ?>">
                                <?php if ($isStockable): ?>
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
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="17" class="text-muted">No lines</td></tr>
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
    </div>
</div>

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
    if (!confirm('Update this order's address & contact from the customer profile?')) return;
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
