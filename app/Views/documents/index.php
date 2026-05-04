<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Documents
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?= view('components/search_styles') ?>

<style>
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
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-files me-2"></i>Documents</h2>
            <div class="small text-muted">Quotations and sales orders</div>
        </div>
        <div class="d-flex gap-2">
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
                                $convertedSoId = $isQuote ? (int)($d['converted_sales_order_id'] ?? 0) : 0;
                            ?>
                            <tr data-doc-type="<?= esc($d['doc_type']) ?>" data-status="<?= esc($status) ?>">
                                <td><?= esc($id) ?></td>
                                <td><strong><?= esc($d['number'] ?: '-') ?></strong></td>
                                <td><?= esc($d['customer'] ?: '-') ?></td>
                                <td class="products-col">
                                    <?php $plist = $d['product_list'] ?? []; ?>
                                    <?php if (!empty($plist) && count($plist) > 0): ?>
                                        <span class="d-inline-block text-truncate" style="max-width:220px;"><?= esc($plist[0]) ?></span>
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
});
</script>

<?= $this->endSection() ?>
