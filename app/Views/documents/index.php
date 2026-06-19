<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Documents
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('components/search_styles') ?>

<style>
    #documentsTable th,
    #documentsTable td {
        font-size: 12.5px;
    }

    #documentsTable td {
        white-space: nowrap;
    }

    #documentsTable .products-col {
        max-width: 360px;
    }

    #latestOrdersModal .modal-dialog {
        max-width: 1180px;
    }

    #latestOrdersModal .latest-orders-table {
        font-size: 12px;
        table-layout: fixed;
        width: 100%;
    }

    #latestOrdersModal .latest-orders-table th,
    #latestOrdersModal .latest-orders-table td {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        vertical-align: middle;
    }

    .latest-order-row-new {
        background: rgba(255, 193, 7, 0.16) !important;
        box-shadow: inset 4px 0 0 #ffc107;
    }

    .latest-order-row-new td {
        font-weight: 600;
    }

    .new-order-chip {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        font-size: 10px;
        font-weight: 700;
        color: #2d2100;
        background: #ffc107;
        border-radius: 999px;
        padding: 2px 8px;
        margin-left: 8px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
    }

    .order-alert-btn {
        border-width: 2px;
    }

    .order-alert-btn.order-alert-active {
        background: #dc3545 !important;
        color: #ffffff !important;
        border-color: #dc3545 !important;
        animation: orderAlarmBlink 0.9s ease-in-out infinite;
    }

    .order-alert-btn.order-alert-active i {
        animation: orderAlarmBellShake 1s ease-in-out infinite;
    }

    .order-alert-btn .order-alert-text {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.3px;
        margin-left: 6px;
        vertical-align: middle;
    }

    @keyframes orderAlarmBlink {
        0%, 100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.55); }
        50% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0.18); }
    }

    @keyframes orderAlarmBellShake {
        0%, 100% { transform: rotate(0deg); }
        20% { transform: rotate(-12deg); }
        40% { transform: rotate(10deg); }
        60% { transform: rotate(-8deg); }
        80% { transform: rotate(6deg); }
    }

    .order-alert-btn .order-alert-badge {
        font-size: 10px;
    }

    .filter-tab {
        padding: 8px 16px;
        background: transparent;
        border: 1px solid var(--gray-300);
        border-radius: 6px;
        color: var(--gray-600);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .filter-tab:hover {
        background: var(--gray-100);
        border-color: var(--gray-400);
        color: var(--gray-700);
    }

    .filter-tab.active {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .filter-tab .count {
        background: rgba(0, 0, 0, 0.1);
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }

    .search-input-wrapper {
        position: relative;
    }

    .search-input-wrapper input {
        padding-left: 38px;
        padding-right: 38px;
    }

    .search-input-wrapper .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--gray-500);
        font-size: 16px;
        pointer-events: none;
    }

    .search-input-wrapper .clear-btn {
        position: absolute;
        right: 8px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--gray-500);
        font-size: 18px;
        cursor: pointer;
        padding: 4px;
        opacity: 0;
        transition: opacity 0.2s ease;
    }

    .search-input-wrapper input:not(:placeholder-shown) ~ .clear-btn {
        opacity: 1;
    }

    .search-input-wrapper .clear-btn:hover {
        color: var(--danger-color);
    }
</style>

<style>
    /* Override dark search input from global search_styles on this page */
    .search-input-wrapper .form-control {
        background: #ffffff !important;
        color: var(--gray-800) !important;
        border: 1px solid var(--gray-200) !important;
        box-shadow: none !important;
        height: 46px;
        border-radius: 8px;
        padding-left: 44px; /* space for icon */
    }
    .search-input-wrapper .form-control::placeholder { color: var(--gray-400) !important; }
    .search-input-wrapper .search-icon { color: var(--gray-400) !important; }
    .search-input-wrapper { padding: 8px 12px; }
    .card-body .search-input-wrapper { padding: 6px 12px; }
</style>

<style>
    /* Further override: remove the dark wrapper and make the entire input area light */
    .search-input-wrapper {
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        padding: 0 !important;
    }
    .search-input-wrapper .form-control {
        background: #fff !important;
        border: 1px solid var(--gray-200) !important;
        box-shadow: none !important;
        width: 100%;
    }
    .search-results-panel {
        background: #fff !important;
        color: var(--gray-800) !important;
        border: 1px solid var(--gray-200) !important;
        box-shadow: 0 6px 18px rgba(15,23,42,0.06) !important;
    }
</style>

<style>
    /* Allow dropdown menus and product popovers to escape the table responsive clipping on this page */
    .table-responsive {
        overflow: visible !important;
    }
    .table-responsive .dropdown-menu {
        z-index: 3000;
    }
</style>

<style>
    /* Products column link styling */
    .products-col .view-more-products,
    .products-col .view-more-link {
        color: var(--primary-color);
        text-decoration: none;
        font-weight: 600;
        margin-left: 8px;
        vertical-align: baseline;
        font-size: 0.92rem;
    }
    .products-col .view-more-products:hover,
    .products-col .view-more-link:hover {
        text-decoration: underline;
    }

    /* Ensure product text truncation aligns nicely */
    .products-col .text-truncate { display: inline-block; vertical-align: middle; }

    /* Actions column alignment and dropdown sizing */
    .tt-actions { white-space: nowrap; }
    .tt-actions .dropdown-toggle { padding: 0.28rem 0.5rem; border-radius: 6px; }
    .tt-actions .dropdown-menu { min-width: 160px; box-shadow: 0 6px 18px rgba(32,33,36,0.08); }

    /* Make dropdown caret aligned with the cell center */
    .tt-actions .dropdown { display: inline-block; vertical-align: middle; }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Reinitialize dropdowns so Popper uses document.body as boundary to avoid clipping inside table responsive
    if (typeof bootstrap !== 'undefined' && bootstrap.Dropdown) {
        document.querySelectorAll('.tt-actions .dropdown-toggle').forEach(btn => {
            try {
                // Dispose existing instance if any
                if (btn._bootstrapDropdown) {
                    try { btn._bootstrapDropdown.dispose(); } catch(e){}
                }
                // Create new Dropdown with custom popperConfig
                const dd = new bootstrap.Dropdown(btn, {
                    popperConfig: function(defaultBsPopperConfig) {
                        const cfg = defaultBsPopperConfig || {};
                        cfg.modifiers = cfg.modifiers || [];
                        // Ensure dropdown doesn't get clipped by table container
                        cfg.modifiers.push({ name: 'preventOverflow', options: { boundary: document.body } });
                        cfg.modifiers.push({ name: 'offset', options: { offset: [0, 6] } });
                        return cfg;
                    }
                });
                // expose for later
                btn._bootstrapDropdown = dd;
            } catch (err) {
                // ignore
            }
        });
    }
});
</script>

<script>
// Ensure dropdown menus are appended to body while open to avoid clipping/overflow issues
document.addEventListener('DOMContentLoaded', function() {
    const dropdownWrappers = document.querySelectorAll('.tt-actions .dropdown');
    dropdownWrappers.forEach(wrapper => {
        const toggle = wrapper.querySelector('[data-bs-toggle="dropdown"]');
        const menu = wrapper.querySelector('.dropdown-menu');
        if (!toggle || !menu) return;

        // store original parent/next sibling
        menu.dataset.origParent = '';
        let origParent = null;
        let origNext = null;

        wrapper.addEventListener('show.bs.dropdown', function (ev) {
            try {
                origParent = menu.parentNode;
                origNext = menu.nextSibling;
                menu.dataset.origParent = '1';
                document.body.appendChild(menu);
                menu.style.position = 'absolute';
                menu.style.zIndex = 4000;
                // position near toggle
                const rect = toggle.getBoundingClientRect();
                // place menu under the toggle, aligned to the end if dropdown-menu-end present
                const alignEnd = menu.classList.contains('dropdown-menu-end');
                const left = alignEnd ? (rect.right - menu.offsetWidth) : rect.left;
                menu.style.top = (rect.bottom + window.scrollY + 6) + 'px';
                menu.style.left = (left + window.scrollX) + 'px';
            } catch (err) {
                // fallback: do nothing
            }
        });

        wrapper.addEventListener('hide.bs.dropdown', function (ev) {
            try {
                // restore menu into original place
                if (origParent) {
                    if (origNext) origParent.insertBefore(menu, origNext);
                    else origParent.appendChild(menu);
                }
                // cleanup styles
                menu.style.position = '';
                menu.style.top = '';
                menu.style.left = '';
                menu.style.zIndex = '';
            } catch (err) {}
        });
    });
});
</script>

<div class="container-fluid py-3">
    <?php
        $latestSalesOrders = array_values(array_filter($documents ?? [], static function($doc) {
            if (($doc['doc_type'] ?? '') !== 'sales_order') {
                return false;
            }
            $status = strtolower((string)($doc['status'] ?? 'confirmed'));
            return !in_array($status, ['shipped', 'completed', 'cancelled'], true);
        }));
        $latestSalesOrders = array_slice($latestSalesOrders, 0, 10);
        $unreadOrderAlerts = isset($unreadOrderAlerts) ? (int)$unreadOrderAlerts : 0;
        $newOrderIds = isset($newOrderIds) && is_array($newOrderIds) ? array_map('intval', $newOrderIds) : [];
    ?>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-files me-2"></i>Documents</h2>
            <div class="small text-muted">Quotations and sales orders</div>
        </div>
        <div class="d-flex gap-2">
            <button
                type="button"
                id="orderAlarmButton"
                class="btn btn-outline-warning position-relative order-alert-btn <?= $unreadOrderAlerts > 0 ? 'order-alert-active' : '' ?>"
                data-bs-toggle="modal"
                data-bs-target="#latestOrdersModal"
                title="Latest 10 sales orders"
            >
                <i class="bi bi-bell"></i>
                <?php if ($unreadOrderAlerts > 0): ?>
                    <span id="orderAlarmText" class="order-alert-text">ALERT</span>
                <?php endif; ?>
                <?php if ($unreadOrderAlerts > 0): ?>
                    <span id="orderAlarmBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger order-alert-badge">
                        <?= $unreadOrderAlerts ?>
                    </span>
                <?php endif; ?>
            </button>
            <a href="<?= site_url('warehouse/ready-to-ship') ?>" class="btn btn-outline-success" title="View orders ready to ship">
                <i class="bi bi-truck me-2"></i>Ready to Ship
            </a>
            <a href="<?= site_url('quotations/create') ?>" class="btn btn-outline-primary">
                <i class="bi bi-plus-circle me-2"></i>New Quotation
            </a>
            <a href="<?= site_url('sales-orders/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>New Sales Order
            </a>
        </div>
    </div>

    <!-- Search and Filters Card -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <div class="search-input-wrapper">
                        <i class="bi bi-search search-icon"></i>
                        <input 
                            type="text" 
                            id="liveSearchInput" 
                            value="<?= esc($search ?? '') ?>" 
                            class="form-control" 
                            placeholder="Search by number, customer, or status..." 
                            autocomplete="off"
                        />
                        <button type="button" class="clear-btn" id="clearSearchBtn" title="Clear search">
                            <i class="bi bi-x-circle-fill"></i>
                        </button>
                        <div class="search-results-panel" id="searchResultsPanel"></div>
                    </div>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <a href="<?= base_url('documents') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-clockwise me-1"></i>Reset
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="mb-3 d-flex gap-2 flex-wrap">
        <a href="#" class="filter-tab active" data-filter="all">
            <i class="bi bi-grid"></i> All 
            <span class="count"><?= count($documents) ?></span>
        </a>
        <a href="#" class="filter-tab" data-filter="quotation">
            <i class="bi bi-file-text"></i> Quotations 
            <span class="count"><?= count(array_filter($documents, fn($d) => ($d['doc_type'] ?? '') === 'quotation')) ?></span>
        </a>
        <a href="#" class="filter-tab" data-filter="sales_order">
            <i class="bi bi-receipt"></i> Sales Orders 
            <span class="count"><?= count(array_filter($documents, fn($d) => ($d['doc_type'] ?? '') === 'sales_order')) ?></span>
        </a>
        <a href="#" class="filter-tab" data-filter="confirmed">
            <i class="bi bi-check-circle"></i> Confirmed 
            <span class="count"><?= count(array_filter($documents, fn($d) => in_array($d['status'] ?? '', ['confirmed', 'approved', 'accepted']))) ?></span>
        </a>
    </div>

    <!-- Documents Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php
                $currencySymbols = [
                    'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹', 'JPY' => '¥', 'CNY' => '¥',
                ];
            ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="documentsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th style="width: 180px;">Number</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th style="width: 120px;">Type</th>
                            <th style="width: 120px;">Date</th>
                            <th style="width: 130px;">Status</th>
                            <th class="text-end" style="width: 140px;">Total</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($documents)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">No documents found</td></tr>
                    <?php else: ?>
                        <?php foreach ($documents as $d): ?>
                            <?php
                                $isQuote = ($d['doc_type'] ?? '') === 'quotation';
                                $typeLabel = $isQuote ? 'Quotation' : 'Sales Order';
                                $typeBadge = $isQuote ? 'text-bg-info' : 'text-bg-primary';

                                $status = (string)($d['status'] ?? '');
                                $statusBadge = 'text-bg-secondary';
                                if ($status === 'quoted') $statusBadge = 'text-bg-info';
                                elseif ($status === 'draft') $statusBadge = 'text-bg-secondary';
                                elseif ($status === 'accepted') $statusBadge = 'text-bg-success';
                                elseif ($status === 'cancelled') $statusBadge = 'text-bg-danger';
                                elseif (in_array($status, ['confirmed','approved','completed'])) $statusBadge = 'text-bg-success';

                                $id = (int)($d['id'] ?? 0);
                                $soIdentifier = (!empty($d['public_id']) && featureEnabled('enable_public_ids')) ? $d['public_id'] : $id;
                                $quotationIdentifier = (!empty($d['public_id']) && featureEnabled('enable_public_ids')) ? urlencode($d['public_id']) : $id;
                                $viewUrl = $isQuote ? site_url('quotations/view/'.$quotationIdentifier) : site_url('sales-orders/view/'.$soIdentifier);
                                $editUrl = $isQuote ? site_url('quotations/edit/'.$quotationIdentifier) : null;
                                $quotePrintUrl = site_url('quotations/print/' . $quotationIdentifier);
                                $quoteWarehouseUrl = site_url('quotations/warehouse-document/' . $quotationIdentifier);
                                $soPrintUrl = site_url('sales-orders/print/' . $soIdentifier);
                                $soWarehouseUrl = site_url('sales-orders/warehouse-document/' . $soIdentifier);
                                $convertedSoId = $isQuote ? (int)($d['converted_sales_order_id'] ?? 0) : 0;
                            ?>
                            <tr data-doc-type="<?= esc($d['doc_type']) ?>" data-status="<?= esc($status) ?>">
                                <td><?= esc($id) ?></td>
                                <td><strong><?= esc($d['number'] ?: '-') ?></strong></td>
                                <td><?= esc($d['customer'] ?: '-') ?></td>
                                <td class="products-col">
                                    <?php $plist = $d['product_list'] ?? []; ?>
                                    <?php if (!empty($plist) && count($plist) > 0): ?>
                                        <span class="d-inline-block text-truncate" style="max-width:340px;" title="<?= esc($plist[0]) ?>"><?= esc($plist[0]) ?></span>
                                        <?php if (count($plist) > 1): ?>
                                            <?php $extra = count($plist) - 1; ?>
                                            <a href="#" class="small ms-2 view-more-products view-more-link" data-products='<?= esc(json_encode($plist)) ?>' title="View <?= $extra ?> more product<?= $extra > 1 ? 's' : '' ?>">View <?= $extra ?> more</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= esc($d['product'] ?: '-') ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= esc($typeBadge) ?>"><?= esc($typeLabel) ?></span></td>
                                <td><?= esc($d['date'] ? date('M d, Y', strtotime($d['date'])) : '') ?></td>
                                <td><span class="badge <?= esc($statusBadge) ?>"><?= esc(ucfirst($status ?: '')) ?></span></td>
                                <?php
                                    $code = strtoupper(trim((string)($d['currency'] ?? '')));
                                    $sym = $currencySymbols[$code] ?? ($code !== '' ? $code . ' ' : '');
                                ?>
                                <td class="text-end"><strong><?= esc($sym) ?><?= number_format((float)($d['total'] ?? 0), 2) ?></strong></td>
                                <td class="tt-actions text-end">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item doc-view" href="<?= $viewUrl ?>"><i class="bi bi-eye me-2"></i>View</a></li>
                                            <?php if ($isQuote): ?>
                                                <li><a class="dropdown-item" href="<?= $editUrl ?>"><i class="bi bi-pencil me-2"></i>Edit</a></li>
                                                <li><a class="dropdown-item" href="<?= $quotePrintUrl ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-2"></i>Print View</a></li>
                                                <li><a class="dropdown-item" href="<?= $quoteWarehouseUrl ?>" target="_blank" rel="noopener"><i class="bi bi-box-seam me-2"></i>Warehouse PDF</a></li>
                                                <?php if ($convertedSoId > 0): ?>
                                                    <li><a class="dropdown-item" href="<?= site_url('sales-orders/view/'.$convertedSoId) ?>"><i class="bi bi-box-arrow-up-right me-2"></i>View Sales Order</a></li>
                                                <?php else: ?>
                                                    <li><a class="dropdown-item" href="<?= site_url('sales-orders/create-from-quotation/'.$id) ?>"><i class="bi bi-arrow-right-square me-2"></i>Convert to Sales Order</a></li>
                                                <?php endif; ?>
                                                <li>
                                                    <form action="<?= site_url('quotations/delete/'.$id) ?>" method="post" onsubmit="return confirm('Delete this quotation?');">
                                                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-trash me-2"></i>Delete</button>
                                                    </form>
                                                </li>
                                            <?php else: ?>
                                                <li><a class="dropdown-item" href="<?= site_url('sales-orders/invoice/'.$id) ?>"><i class="bi bi-receipt me-2"></i>Invoice</a></li>
                                                <li><a class="dropdown-item" href="<?= $soPrintUrl ?>" target="_blank" rel="noopener"><i class="bi bi-printer me-2"></i>Print View</a></li>
                                                <li><a class="dropdown-item" href="<?= $soWarehouseUrl ?>" target="_blank" rel="noopener"><i class="bi bi-box-seam me-2"></i>Warehouse PDF</a></li>
                                                <li>
                                                    <form action="<?= site_url('sales-orders/cancel/'.$id) ?>" method="post" onsubmit="return confirm('Cancel this sales order?');">
                                                        <button class="dropdown-item text-danger" type="submit"><i class="bi bi-x-circle me-2"></i>Cancel</button>
                                                    </form>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Latest 10 Sales Orders modal -->
<div class="modal fade" id="latestOrdersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Latest 10 Sales Orders</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php if (!empty($newOrderIds)): ?>
                    <div id="newOrdersBanner" class="alert alert-warning py-2 px-3 mb-3" role="alert" style="font-size:12px;">
                        Highlighted rows are new since your last &ldquo;I have seen it&rdquo; action.
                    </div>
                <?php else: ?>
                    <div id="newOrdersBanner" class="alert alert-warning py-2 px-3 mb-3" role="alert" style="font-size:12px;display:none;">
                        Highlighted rows are new since your last &ldquo;I have seen it&rdquo; action.
                    </div>
                <?php endif; ?>
                <?php if (empty($latestSalesOrders)): ?>
                    <div class="text-muted">No recent sales orders found.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0 latest-orders-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Order No</th>
                                    <th>Customer</th>
                                    <th>Country</th>
                                    <th>Created By</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th class="text-end">Total</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latestSalesOrders as $idx => $order): ?>
                                    <?php
                                        $sid = (int)($order['id'] ?? 0);
                                        $isNewOrder = in_array($sid, $newOrderIds, true);
                                        $soIdentifier = (!empty($order['public_id']) && featureEnabled('enable_public_ids')) ? $order['public_id'] : $sid;
                                        $status = strtolower((string)($order['status'] ?? 'confirmed'));
                                        $statusBadge = 'text-bg-secondary';
                                        if (in_array($status, ['confirmed','approved','completed'], true)) {
                                            $statusBadge = 'text-bg-success';
                                        } elseif (in_array($status, ['draft','pending'], true)) {
                                            $statusBadge = 'text-bg-warning';
                                        } elseif ($status === 'cancelled') {
                                            $statusBadge = 'text-bg-danger';
                                        }
                                        $code = strtoupper(trim((string)($order['currency'] ?? '')));
                                        $sym = $currencySymbols[$code] ?? ($code !== '' ? $code . ' ' : '');
                                    ?>
                                    <tr class="<?= $isNewOrder ? 'latest-order-row-new' : '' ?>" data-order-id="<?= $sid ?>">
                                        <td><?= $idx + 1 ?></td>
                                        <td>
                                            <strong><?= esc((string)($order['number'] ?? '')) ?></strong>
                                            <span class="new-order-chip" style="<?= $isNewOrder ? '' : 'display:none' ?>"><i class="bi bi-exclamation-circle"></i>New</span>
                                        </td>
                                        <td><?= esc((string)($order['customer'] ?? '-')) ?></td>
                                        <td><?= esc(trim((string)($order['customer_country'] ?? '')) !== '' ? (string)$order['customer_country'] : '-') ?></td>
                                        <td><?= esc(trim((string)($order['created_by_name'] ?? '')) !== '' ? (string)$order['created_by_name'] : '-') ?></td>
                                        <td><?= !empty($order['date']) ? esc(date('M d, Y', strtotime((string)$order['date']))) : '-' ?></td>
                                        <td><span class="badge <?= esc($statusBadge) ?>"><?= esc(ucfirst($status)) ?></span></td>
                                        <td class="text-end"><strong><?= esc($sym) ?><?= number_format((float)($order['total'] ?? 0), 2) ?></strong></td>
                                        <td class="text-end">
                                            <a href="<?= site_url('sales-orders/view/' . $soIdentifier) ?>" class="btn btn-sm btn-outline-secondary" title="View"><i class="bi bi-eye"></i></a>
                                            <a href="<?= site_url('sales-orders/print/' . $soIdentifier) ?>" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" title="Print View"><i class="bi bi-printer"></i></a>
                                            <a href="<?= site_url('sales-orders/warehouse-document/' . $soIdentifier) ?>" class="btn btn-sm btn-outline-warning" target="_blank" rel="noopener" title="Warehouse PDF"><i class="bi bi-box-seam"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" id="acknowledgeOrderAlarmBtn" class="btn btn-danger" <?= $unreadOrderAlerts > 0 ? '' : 'disabled' ?>>
                    <i class="bi bi-check2-circle me-1"></i>I have seen it, stop alarm
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Products modal -->
<div class="modal fade" id="productsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Products <small id="productsModalCount" class="text-muted"></small></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm mb-0" id="productsModalTable">
                        <thead>
                            <tr>
                                <th style="width:48px;">#</th>
                                <th>Product</th>
                            </tr>
                        </thead>
                        <tbody id="productsModalList"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearchInput');
    const resultsPanel = document.getElementById('searchResultsPanel');
    const clearBtn = document.getElementById('clearSearchBtn');
    const filterTabs = document.querySelectorAll('.filter-tab');
    const tableRows = document.querySelectorAll('#documentsTable tbody tr[data-doc-type]');
    let searchTimeout;

    // Filter functionality
    filterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            const filter = this.dataset.filter;
            
            // Update active state
            filterTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            
            // Filter rows
            tableRows.forEach(row => {
                const docType = row.dataset.docType;
                const status = row.dataset.status;
                
                if (filter === 'all') {
                    row.style.display = '';
                } else if (filter === 'confirmed') {
                    row.style.display = ['confirmed', 'approved', 'accepted'].includes(status) ? '' : 'none';
                } else {
                    row.style.display = docType === filter ? '' : 'none';
                }
            });
        });
    });

    // Live search on input
    searchInput.addEventListener('input', function(e) {
        clearTimeout(searchTimeout);
        const query = e.target.value.trim();

        if (query.length < 1) {
            resultsPanel.classList.remove('show');
            resultsPanel.innerHTML = '';
            return;
        }

        // Show loading indicator
        resultsPanel.classList.add('show');
        resultsPanel.innerHTML = '<div class="search-loading"><i class="bi bi-hourglass-split"></i> Searching...</div>';

        // Debounce the search
        searchTimeout = setTimeout(() => {
            performLiveSearch(query);
        }, 300);
    });

    // Clear search
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        resultsPanel.classList.remove('show');
        resultsPanel.innerHTML = '';
        searchInput.focus();
    });

    // Close results panel when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container') && !e.target.closest('.search-input-wrapper')) {
            resultsPanel.classList.remove('show');
        }
    });

    // Perform live search
    function performLiveSearch(query) {
        const url = new URL('<?= base_url('documents/search') ?>', window.location.origin);
        url.searchParams.append('q', query);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            displaySearchResults(data, query);
        })
        .catch(error => {
            console.error('Search error:', error);
            resultsPanel.innerHTML = '<div class="search-no-results">Error loading results</div>';
        });
    }

    // Display search results
    function displaySearchResults(data, query) {
        const results = data.results || [];
        const total = data.total || 0;

        if (!Array.isArray(results) || results.length === 0) {
            resultsPanel.innerHTML = '<div class="search-no-results"><i class="bi bi-search"></i> No documents found for "' + escapeHtml(query) + '"</div>';
            return;
        }

        let html = '';
        results.forEach(item => {
            const typeLabel = item.doc_type === 'quotation' ? 'Quotation' : 'Sales Order';
            const typeBadge = item.doc_type === 'quotation' ? 'info' : 'primary';
            html += `
                <div class="search-result-item" onclick="selectDocument('${item.doc_type}', ${item.id}, '${item.public_id || ''}')">
                    <div class="result-header">
                        <span class="badge text-bg-${typeBadge}" style="font-size: 10px;">${typeLabel}</span>
                        <span class="result-code">${escapeHtml(item.number)}</span>
                    </div>
                    <div class="result-name">${escapeHtml(item.customer_name || 'N/A')}</div>
                    <div class="result-meta">
                        <span><i class="bi bi-circle-fill"></i> ${escapeHtml(item.status || 'Unknown')}</span>
                    </div>
                </div>
            `;
        });

        // Add "View all results" if there are more results than shown
        if (total > results.length) {
            html += `
                <div style="padding: 10px 12px; background: #0f172a; border-top: 1px solid #334155; text-align: center;">
                    <a href="javascript:void(0)" onclick="filterBySearch('${escapeHtml(query)}')" style="color: #3b82f6; font-size: 13px; font-weight: 600; text-decoration: none;">
                        View all ${total} results <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            `;
        }

        resultsPanel.innerHTML = html;
    }

    // Select a document from results
    window.selectDocument = function(docType, docId, publicId) {
        const identifier = ('<?= featureEnabled('enable_public_ids') ? '1' : '0' ?>' === '1' && publicId)
            ? publicId
            : docId;
        const url = docType === 'quotation' 
            ? '<?= base_url('quotations/view/') ?>' + identifier 
            : '<?= base_url('sales-orders/view/') ?>' + identifier;
        window.location.href = url;
    };

    // Escape HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Allow Enter key to trigger filter
    searchInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            filterBySearch(this.value.trim());
        }
    });

    // Filter table by search
    window.filterBySearch = function(query) {
        const url = new URL(window.location);
        if (query) {
            url.searchParams.set('q', query);
        } else {
            url.searchParams.delete('q');
        }
        window.location.href = url.toString();
    };

    // Add row click to view
    tableRows.forEach(row => {
        row.style.cursor = 'pointer';
        row.addEventListener('click', function(e) {
            // Don't trigger if clicking on buttons or forms
            if (e.target.closest('.btn') || e.target.closest('form')) {
                return;
            }
            const viewLink = this.querySelector('.doc-view');
            if (viewLink) {
                window.location.href = viewLink.href;
            }
        });
    });

    // Products modal: show full product list when '+N' clicked
    const productsModalEl = document.getElementById('productsModal');
    let productsModal = null;
    if (productsModalEl && typeof bootstrap !== 'undefined') {
        productsModal = new bootstrap.Modal(productsModalEl);
    }

    document.querySelectorAll('.view-more-products').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const raw = this.dataset.products || '[]';
            let items = [];
            try { items = JSON.parse(raw); } catch (err) { items = []; }
            const list = document.getElementById('productsModalList');
            const countEl = document.getElementById('productsModalCount');
            if (!list) return;
            list.innerHTML = '';
            const n = (items && items.length) ? items.length : 0;
            if (countEl) countEl.textContent = n ? `(${n})` : '';
            if (!items || items.length === 0) {
                list.innerHTML = '<tr><td colspan="2" class="text-muted">No products</td></tr>';
            } else {
                // Build table rows with serial and product description
                list.innerHTML = '';
                items.forEach((p, idx) => {
                    const tr = document.createElement('tr');
                    const tdIdx = document.createElement('td');
                    tdIdx.className = 'align-middle text-muted';
                    tdIdx.style.width = '48px';
                    tdIdx.textContent = (idx + 1).toString();
                    const tdProd = document.createElement('td');
                    tdProd.className = 'align-middle';
                    tdProd.textContent = p;
                    tr.appendChild(tdIdx);
                    tr.appendChild(tdProd);
                    list.appendChild(tr);
                });
            }
            if (productsModal) productsModal.show();
        });
    });

    const orderAlarmButton = document.getElementById('orderAlarmButton');
    const acknowledgeOrderAlarmBtn = document.getElementById('acknowledgeOrderAlarmBtn');
    const latestOrdersModalEl = document.getElementById('latestOrdersModal');

    // Live set of new order IDs — seeded from PHP at page load, refreshed by polling.
    let currentNewOrderIds = new Set(<?= json_encode(array_values($newOrderIds)) ?>);

    function applyNewOrderHighlights() {
        const rows = document.querySelectorAll('#latestOrdersModal tbody tr[data-order-id]');
        rows.forEach(function(row) {
            const id = parseInt(row.dataset.orderId, 10);
            const isNew = currentNewOrderIds.has(id);
            row.classList.toggle('latest-order-row-new', isNew);
            const chip = row.querySelector('.new-order-chip');
            if (chip) chip.style.display = isNew ? '' : 'none';
        });
        // Show/hide the "highlighted rows are new" banner
        const banner = document.getElementById('newOrdersBanner');
        if (banner) banner.style.display = currentNewOrderIds.size > 0 ? '' : 'none';
    }

    function updateOrderAlarm(unreadCount) {
        if (!orderAlarmButton) return;

        const count = Math.max(0, parseInt(unreadCount, 10) || 0);
        let badge = document.getElementById('orderAlarmBadge');

        if (count > 0) {
            orderAlarmButton.classList.add('order-alert-active');
            orderAlarmButton.classList.remove('btn-outline-warning');
            orderAlarmButton.classList.add('btn-danger');
            let text = document.getElementById('orderAlarmText');
            if (!text) {
                text = document.createElement('span');
                text.id = 'orderAlarmText';
                text.className = 'order-alert-text';
                text.textContent = 'ALERT';
                orderAlarmButton.appendChild(text);
            }
            if (!badge) {
                badge = document.createElement('span');
                badge.id = 'orderAlarmBadge';
                badge.className = 'position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger order-alert-badge';
                orderAlarmButton.appendChild(badge);
            }
            badge.textContent = String(count);
        } else {
            orderAlarmButton.classList.remove('order-alert-active');
            orderAlarmButton.classList.remove('btn-danger');
            orderAlarmButton.classList.add('btn-outline-warning');
            const text = document.getElementById('orderAlarmText');
            if (text) {
                text.remove();
            }
            if (badge) {
                badge.remove();
            }
        }

        if (acknowledgeOrderAlarmBtn) {
            acknowledgeOrderAlarmBtn.disabled = count <= 0;
        }
    }

    function pollOrderAlarmState() {
        fetch('<?= site_url('documents/order-alert-state') ?>', {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data && data.success) {
                updateOrderAlarm(data.unreadOrderAlerts || 0);
                // Update live new-order IDs so modal highlights are always fresh
                if (Array.isArray(data.newOrderIds)) {
                    currentNewOrderIds = new Set(data.newOrderIds.map(Number));
                    applyNewOrderHighlights();
                }
            }
        })
        .catch(() => {
            // Keep the last known UI state; polling retries automatically.
        });
    }

    if (acknowledgeOrderAlarmBtn) {
        acknowledgeOrderAlarmBtn.addEventListener('click', function() {
            if (acknowledgeOrderAlarmBtn.disabled) return;

            const originalHtml = acknowledgeOrderAlarmBtn.innerHTML;
            acknowledgeOrderAlarmBtn.disabled = true;
            acknowledgeOrderAlarmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>Saving...';

            fetch('<?= site_url('documents/acknowledge-orders-alarm') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: encodeURIComponent(window.csrfToken || '<?= csrf_token() ?>') + '=' + encodeURIComponent(window.csrfHash || '<?= csrf_hash() ?>')
            })
            .then(response => response.json())
            .then(data => {
                if (data && data.csrfToken && data.csrfHash) {
                    window.csrfToken = data.csrfToken;
                    window.csrfHash = data.csrfHash;
                }

                if (data && data.success) {
                    currentNewOrderIds = new Set();
                    applyNewOrderHighlights();
                    updateOrderAlarm(0);
                    if (latestOrdersModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        const modal = bootstrap.Modal.getOrCreateInstance(latestOrdersModalEl);
                        modal.hide();
                    }
                    return;
                }

                acknowledgeOrderAlarmBtn.disabled = false;
                acknowledgeOrderAlarmBtn.innerHTML = originalHtml;
                alert((data && data.message) ? data.message : 'Failed to acknowledge order alerts.');
            })
            .catch(() => {
                acknowledgeOrderAlarmBtn.disabled = false;
                acknowledgeOrderAlarmBtn.innerHTML = originalHtml;
                alert('Failed to acknowledge order alerts. Please try again.');
            });
        });
    }

    // Apply initial highlights from PHP-rendered new order IDs
    applyNewOrderHighlights();

    // Re-apply highlights every time the modal is opened (in case poll already updated IDs)
    if (latestOrdersModalEl) {
        latestOrdersModalEl.addEventListener('show.bs.modal', function() {
            applyNewOrderHighlights();
        });
    }

    // Poll so newly added sales orders trigger alarm without page refresh.
    pollOrderAlarmState();
    setInterval(pollOrderAlarmState, 30000);
});
</script>

<?= $this->endSection() ?>
