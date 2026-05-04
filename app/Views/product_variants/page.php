<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<style>
    .search-container {
        position: relative;
        width: 100%;
        max-width: 760px;
    }

    .search-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
        background: var(--cl-surface);
        border-radius: 8px;
        border: 1px solid var(--cl-border);
        transition: all 0.3s ease;
        box-shadow: var(--cl-shadow-xs);
    }

    .search-input-wrapper:hover {
        border-color: var(--cl-border);
        box-shadow: var(--cl-shadow-sm);
    }

    .search-input-wrapper:focus-within {
        border-color: var(--cl-primary);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
    }

    .search-input-wrapper .search-icon {
        position: absolute;
        left: 12px;
        color: var(--cl-text-muted);
        font-size: 16px;
        pointer-events: none;
    }

    .search-input-wrapper input {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--cl-text-primary);
        padding: 10px 12px 10px 38px;
        font-size: 14px;
        outline: none;
    }

    .search-input-wrapper input::placeholder {
        color: var(--cl-text-muted);
    }

    .search-input-wrapper .clear-btn {
        background: transparent;
        border: none;
        color: var(--cl-text-muted);
        padding: 8px 12px;
        cursor: pointer;
        font-size: 16px;
        transition: color 0.2s ease;
        display: none;
    }

    .search-input-wrapper .clear-btn:hover {
        color: var(--cl-text-primary);
    }

    .search-input-wrapper input:not(:placeholder-shown) ~ .clear-btn {
        display: block;
    }

    .search-results-panel {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--cl-surface);
        border: 1px solid var(--cl-border);
        border-top: none;
        border-radius: 0 0 8px 8px;
        max-height: 400px;
        overflow-y: auto;
        display: none;
        z-index: 1000;
        box-shadow: var(--cl-shadow-lg);
    }

    .search-results-panel.show {
        display: block;
    }

    .search-result-item {
        padding: 10px 12px;
        border-bottom: 1px solid var(--cl-border-light);
        cursor: pointer;
        transition: background-color 0.2s ease;
        color: var(--cl-text-secondary);
        font-size: 13px;
    }

    .search-result-item:last-child {
        border-bottom: none;
    }

    .search-result-item:hover {
        background-color: var(--cl-primary-50);
        color: var(--cl-text-primary);
    }

    .search-result-item .result-code {
        font-weight: 600;
        color: var(--cl-primary);
    }

    .search-result-item .result-name {
        display: block;
        color: var(--cl-text-muted);
        font-size: 12px;
        margin-top: 2px;
    }

    .search-no-results {
        padding: 20px 12px;
        text-align: center;
        color: var(--cl-text-muted);
        font-size: 13px;
    }

    .search-loading {
        padding: 10px 12px;
        text-align: center;
        color: var(--cl-text-muted);
        font-size: 13px;
    }

    .header-section {
        background: var(--cl-surface);
        border: 1px solid var(--cl-border);
        padding: 7px 14px;
        border-radius: 8px;
        margin-bottom: 10px;
        box-shadow: var(--cl-shadow-xs);
    }

    .header-row {
        display: flex;
        justify-content: space-between;
        gap: .5rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .header-title {
        color: var(--cl-text-primary);
        font-size: 18px;
        font-weight: 600;
        margin: 0;
        line-height: 1.1;
    }

    .header-stats {
        color: var(--cl-text-muted);
        font-size: 12px;
        margin-top: 2px;
    }

    .header-stats strong {
        color: var(--cl-primary);
        font-weight: 600;
    }

    .header-actions {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        align-items: center;
    }
    .mgr-btn {
        display: inline-flex;
        align-items: center;
        gap: .35rem;
        padding: .28rem .7rem;
        border-radius: 20px;
        font-size: .75rem;
        font-weight: 600;
        cursor: pointer;
        border: 1.5px solid;
        transition: all .14s;
        text-decoration: none !important;
        white-space: nowrap;
    }
    .mgr-btn-combos {
        background: transparent;
        color: var(--cl-primary);
        border-color: var(--cl-primary);
    }
    .mgr-btn-combos:hover {
        background: var(--cl-primary-light);
        color: var(--cl-primary);
    }
    .mgr-btn-values {
        background: var(--cl-primary);
        color: #fff;
        border-color: var(--cl-primary);
    }
    .mgr-btn-values:hover {
        background: var(--cl-primary-hover, #1d4ed8);
        border-color: var(--cl-primary-hover, #1d4ed8);
        color: #fff;
    }

    .search-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .35rem;
        margin-bottom: .3rem;
    }

    .search-hint {
        font-size: .73rem;
        color: var(--cl-text-muted);
    }

    .search-hint kbd {
        font-size: .68rem;
        border: 1px solid var(--cl-border);
        border-bottom-width: 2px;
        border-radius: 4px;
        padding: .08rem .32rem;
        background: var(--cl-surface-alt);
        color: var(--cl-text-secondary);
    }

    .search-run-btn {
        border: none;
        background: transparent;
        color: var(--cl-text-muted);
        padding: 8px 10px;
    }

    .search-run-btn:hover {
        color: var(--cl-primary);
    }

    /* ── Offcanvas: attribute value chips ──────── */
    .val-chips {
        display: flex;
        flex-wrap: wrap;
        gap: 5px;
        margin-bottom: 2px;
    }
    .val-chip {
        display: inline-flex;
        align-items: center;
        gap: 3px;
        background: var(--cl-surface-alt);
        border: 1px solid var(--cl-border);
        border-radius: 999px;
        padding: 2px 8px 2px 10px;
        font-size: .75rem;
        color: var(--cl-text-primary);
        max-width: 200px;
    }
    .val-chip-text {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .val-chip-del {
        flex-shrink: 0;
        background: none;
        border: none;
        padding: 0;
        margin-left: 1px;
        color: var(--cl-text-muted);
        font-size: .85rem;
        line-height: 1;
        cursor: pointer;
        border-radius: 50%;
        width: 16px;
        height: 16px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: background .1s, color .1s;
    }
    .val-chip-del:hover {
        background: var(--cl-danger-light);
        color: var(--cl-danger);
    }
    body.theme-dark .val-chip {
        background: #1a2740;
        border-color: #334155;
        color: #e2e8f0;
    }
    body.theme-dark .val-chip-del { color: #64748b; }
    body.theme-dark .val-chip-del:hover { background: rgba(220,38,38,.2); color: #f87171; }

    /* ── Product group banner in list ──────────── */
    .variant-product-banner {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: 5px 10px;
        background: var(--cl-primary-light, rgba(37,99,235,.08));
        border: 1px solid var(--cl-border);
        border-bottom: none;
        border-radius: 6px 6px 0 0;
        font-size: .78rem;
        font-weight: 600;
        color: var(--cl-primary);
    }
    .variant-product-banner .vpb-label {
        color: var(--cl-text-muted);
        font-weight: 400;
        font-size: .7rem;
        margin-right: 2px;
    }
    body.theme-dark .variant-product-banner {
        background: rgba(37,99,235,.1);
        border-color: #334155;
        color: #93c5fd;
    }

    @media (max-width: 768px) {
        .search-container {
            max-width: 100%;
        }

        .header-section {
            padding: 15px;
        }

        .header-title {
            font-size: 22px;
        }

        .header-row {
            align-items: stretch;
        }
    }
</style>

<div class="container-fluid py-2">
    <div class="row">
        <div class="col-12">
            <!-- Header with Title and Stats -->
            <div class="header-section mb-2">
                <div class="header-row">
                    <div>
                        <?php
                        $pvHeaderName = '';
                        $pvHeaderCode = '';
                        if (!empty($product_id) && !empty($variants)) {
                            $pvHeaderName = $variants[0]['product_name'] ?? '';
                            $pvHeaderCode = $variants[0]['product_code'] ?? '';
                        } elseif (!empty($product_id)) {
                            // No variants yet — look up product directly
                            try {
                                $pvProd = \Config\Database::connect()->table('products')->select('name,code')->where('id',(int)$product_id)->get()->getRowArray();
                                if ($pvProd) { $pvHeaderName = $pvProd['name']; $pvHeaderCode = $pvProd['code']; }
                            } catch (\Throwable $_) {}
                        }
                        ?>
                        <h1 class="header-title">
                            <?php if ($pvHeaderName): ?>
                                <?= esc($pvHeaderName) ?> — Variants
                            <?php else: ?>
                                Product Variants
                            <?php endif; ?>
                        </h1>
                        <div class="header-stats">
                            <?php if ($pvHeaderCode): ?>
                                <span style="color:var(--cl-text-muted);margin-right:8px">Code: <?= esc($pvHeaderCode) ?></span>
                            <?php endif; ?>
                            Total items: <strong><?= isset($total) ? (int)$total : 0 ?></strong>
                        </div>
                    </div>
                    <div class="header-actions">
                        <?php if (!empty($product_id)): ?>
                            <a href="<?= base_url('products/' . (int)$product_id) ?>" class="mgr-btn" style="background:transparent;color:var(--cl-text-secondary,#94a3b8);border-color:var(--cl-border,#334155)">
                                <i class="bi bi-arrow-left"></i>Back to Product
                            </a>
                            <button type="button" class="mgr-btn mgr-btn-combos"
                                    data-bs-toggle="offcanvas" data-bs-target="#combosOffcanvas" aria-controls="combosOffcanvas">
                                <i class="bi bi-diagram-3"></i>Combos
                            </button>
                            <button type="button" class="mgr-btn mgr-btn-values"
                                    data-bs-toggle="offcanvas" data-bs-target="#valuesOffcanvas" aria-controls="valuesOffcanvas">
                                <i class="bi bi-tags"></i>Values
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Live Search Box -->
            <div class="search-container mb-2">
                <div class="search-toolbar">
                    <div class="search-hint">
                        Improved search: code, product, attributes. Press <kbd>Enter</kbd> to apply filter.
                    </div>
                </div>
                <div class="search-input-wrapper">
                    <i class="bi bi-search search-icon"></i>
                    <input 
                        type="text" 
                        id="liveSearchInput" 
                        value="<?= esc($search ?? '') ?>" 
                        class="form-control form-control-sm" 
                        placeholder="Search code, name, or attributes..." 
                        autocomplete="off"
                    />
                    <button type="button" class="search-run-btn" id="runSearchBtn" title="Apply filter">
                        <i class="bi bi-arrow-right-circle"></i>
                    </button>
                    <button type="button" class="clear-btn" id="clearSearchBtn" title="Clear search">
                        <i class="bi bi-x-circle-fill"></i>
                    </button>
                    <div class="search-results-panel" id="searchResultsPanel"></div>
                </div>
                <input type="hidden" name="product_id" id="productIdInput" value="<?= esc($product_id ?? '') ?>" />
                <input type="hidden" name="per_page" id="perPageInput" value="<?= (int)($perPage ?? 50) ?>" />
            </div>

            <!-- Variants Table -->
            <div id="variantsTableContainer">
                <?= view('product_variants/index', ['variants' => $variants ?? [], 'total' => $total ?? 0, 'page' => $page ?? 1, 'perPage' => $perPage ?? 50, 'product_id' => $product_id ?? null, 'search' => $search ?? null]) ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('liveSearchInput');
    const resultsPanel = document.getElementById('searchResultsPanel');
    const clearBtn = document.getElementById('clearSearchBtn');
    const runSearchBtn = document.getElementById('runSearchBtn');
    let searchTimeout;

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
        filterBySearch('');
    });

    runSearchBtn.addEventListener('click', function() {
        filterBySearch(searchInput.value.trim());
    });

    // Close results panel when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-container')) {
            resultsPanel.classList.remove('show');
        }
    });

    // Perform live search
    function performLiveSearch(query) {
        const productId = document.getElementById('productIdInput').value;
        const url = new URL('<?= base_url('product-variants/search') ?>', window.location.origin);
        url.searchParams.append('q', query);
        if (productId) url.searchParams.append('product_id', productId);

        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Search response:', data);
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
            resultsPanel.innerHTML = '<div class="search-no-results"><i class="bi bi-search"></i> No results found for "' + escapeHtml(query) + '"</div>';
            return;
        }

        let html = '';
        results.forEach(item => {
            const attrDisplay = item.attributes ? 
                Object.entries(item.attributes)
                    .map(([k, v]) => k + ': ' + v)
                    .join(' • ') 
                : '';
            
            html += `
                <div class="search-result-item" onclick="selectVariant(${item.id})">
                    <div class="result-code">${escapeHtml(item.art_number)}</div>
                    <div class="result-name">${escapeHtml(item.product_name)} ${attrDisplay ? '• ' + escapeHtml(attrDisplay) : ''}</div>
                </div>
            `;
        });

        // Add "View all results" if there are more results than shown
        if (total > results.length) {
            html += `
                <div style="padding: 10px 12px; background: var(--cl-surface-alt); border-top: 1px solid var(--cl-border); text-align: center;">
                    <a href="javascript:void(0)" onclick="filterBySearch('${escapeHtml(query)}')" style="color: var(--cl-primary); font-size: 13px; font-weight: 600; text-decoration: none;">
                        View all ${total} results <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            `;
        }

        resultsPanel.innerHTML = html;
    }

    // Select a variant from results
    window.selectVariant = function(variantId) {
        window.location.href = '<?= base_url('product-variants/') ?>' + variantId + '/edit';
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
        url.searchParams.set('page', '1');
        const perPageVal = document.getElementById('perPageInput')?.value;
        if (perPageVal) {
            url.searchParams.set('per_page', perPageVal);
        }
        window.location.href = url.toString();
    };
});
</script>

<?php if (!empty($product_id)): ?>
<!-- ─── Manage Combos Offcanvas ──────────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="combosOffcanvas"
     aria-labelledby="combosOffcanvasLabel" style="width:min(680px,94vw)">
    <div class="offcanvas-header border-bottom py-2">
        <h5 class="offcanvas-title mb-0" id="combosOffcanvasLabel">
            <i class="bi bi-sliders me-2 text-primary"></i>Manage Variant Combos
            <small class="text-muted fw-normal ms-2" style="font-size:.75rem">Exclusion / Include rules</small>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3" id="combosOffcanvasBody">
        <?= view('products/variant_exclusions_panel', ['product' => ['id' => (int)$product_id]]) ?>
    </div>
</div>

<!-- ─── Manage Values Offcanvas ─────────────────────────────── -->
<div class="offcanvas offcanvas-end" tabindex="-1" id="valuesOffcanvas"
     aria-labelledby="valuesOffcanvasLabel" style="width:min(520px,94vw)">
    <div class="offcanvas-header border-bottom py-2">
        <h5 class="offcanvas-title mb-0" id="valuesOffcanvasLabel">
            <i class="bi bi-list-check me-2 text-secondary"></i>Manage Attribute Values
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-3" id="valuesOffcanvasBody">
        <div id="valuesLoadingState" class="text-center text-muted py-4">
            <span class="spinner-border spinner-border-sm me-2"></span>Loading attributes...
        </div>
        <div id="valuesContent" style="display:none"></div>
    </div>
</div>

<!-- Add Value Modal (for values offcanvas) -->
<div class="modal fade" id="addValueModal" tabindex="-1" aria-labelledby="addValueModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title" id="addValueModalLabel">Add Value</h6>
                <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body py-2">
                <input type="hidden" id="addValueAttrId" value="">
                <div class="mb-2">
                    <label class="form-label small mb-1" id="addValueAttrLabel">Attribute</label>
                    <input type="text" id="addValueInput" class="form-control form-control-sm"
                           placeholder="New value name..." maxlength="120">
                </div>
                <div id="addValueError" class="text-danger small" style="display:none"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary" id="saveAddValueBtn">Add</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const valuesOffcanvasEl = document.getElementById('valuesOffcanvas');
    if (!valuesOffcanvasEl) return;

    let attributesLoaded = false;

    valuesOffcanvasEl.addEventListener('show.bs.offcanvas', function() {
        if (attributesLoaded) return;
        loadAttributes();
    });

    async function loadAttributes() {
        const loadingEl = document.getElementById('valuesLoadingState');
        const contentEl = document.getElementById('valuesContent');
        loadingEl.style.display = '';
        contentEl.style.display = 'none';

        try {
            const res = await fetch('<?= base_url('/product-attributes/list') ?>', {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await res.json();
            const attrs = (data && data.success && Array.isArray(data.data)) ? data.data : [];
            if (!attrs.length) {
                contentEl.innerHTML = '<div class="alert alert-info small">No attributes found. Create attributes first.</div>';
                contentEl.style.display = '';
                loadingEl.style.display = 'none';
                return;
            }
            renderAttributes(attrs);
            attributesLoaded = true;
        } catch(e) {
            contentEl.innerHTML = '<div class="alert alert-danger small">Failed to load attributes.</div>';
            contentEl.style.display = '';
        }
        loadingEl.style.display = 'none';
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function renderAttributes(attrs) {
        const contentEl = document.getElementById('valuesContent');
        let html = '<div class="accordion" id="valuesAccordion">';
        attrs.forEach((attr, i) => {
            const collapseId = 'attrCollapse' + attr.id;
            html += `
            <div class="accordion-item border mb-2 rounded overflow-hidden" id="attrSection${attr.id}">
                <h2 class="accordion-header">
                    <button class="accordion-button ${i > 0 ? 'collapsed' : ''} py-2 px-3"
                            type="button" data-bs-toggle="collapse"
                            data-bs-target="#${collapseId}" style="font-size:.82rem;font-weight:600">
                        <i class="bi bi-tag me-2 text-muted"></i>${esc(attr.name)}
                        <span class="badge bg-secondary ms-2 val-count-badge" id="countBadge${attr.id}" style="font-size:.65rem"></span>
                    </button>
                </h2>
                <div id="${collapseId}" class="accordion-collapse collapse ${i===0?'show':''}"
                     data-attr-id="${attr.id}" data-attr-name="${esc(attr.name)}">
                    <div class="accordion-body p-2">
                        <div id="valsList${attr.id}" class="mb-2">
                            <div class="text-muted small text-center py-2"><span class="spinner-border spinner-border-sm"></span></div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary w-100 add-value-btn"
                                data-attr-id="${attr.id}" data-attr-name="${esc(attr.name)}"
                                style="font-size:.75rem;padding:.22rem">
                            <i class="bi bi-plus-circle me-1"></i>Add Value
                        </button>
                    </div>
                </div>
            </div>`;
        });
        html += '</div>';
        contentEl.innerHTML = html;
        contentEl.style.display = '';

        // Load values when a panel is shown
        document.querySelectorAll('.accordion-collapse').forEach(el => {
            loadValuesForAttr(el.getAttribute('data-attr-id'), el.getAttribute('data-attr-name'));
            el.addEventListener('show.bs.collapse', function() {
                const aid = this.getAttribute('data-attr-id');
                loadValuesForAttr(aid, this.getAttribute('data-attr-name'));
            });
        });
    }

    async function loadValuesForAttr(attrId, attrName) {
        const listEl = document.getElementById('valsList' + attrId);
        if (!listEl) return;
        try {
            const res = await fetch(`<?= base_url('/product-attributes') ?>/${encodeURIComponent(attrId)}/values?q=`, {
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            });
            const data = await res.json();
            const vals = (data && Array.isArray(data.values)) ? data.values :
                         (data && Array.isArray(data.data)) ? data.data : [];
            renderValues(attrId, vals);
        } catch(e) {
            if(listEl) listEl.innerHTML = '<div class="text-danger small">Failed to load.</div>';
        }
    }

    function renderValues(attrId, vals) {
        const listEl = document.getElementById('valsList' + attrId);
        const badge  = document.getElementById('countBadge' + attrId);
        if (!listEl) return;
        if (badge) badge.textContent = vals.length;
        if (!vals.length) {
            listEl.innerHTML = '<p class="text-muted small mb-0">No values yet. Add one below.</p>';
            return;
        }
        let html = '<div class="val-chips">';
        vals.forEach(v => {
            // API returns plain strings (attribute values stored as JSON string array)
            const vname = typeof v === 'string' ? v : (v.value || v.name || String(v));
            if (!vname) return;
            html += `<span class="val-chip">
                        <span class="val-chip-text">${esc(vname)}</span>
                        <button type="button" class="val-chip-del del-value-btn"
                                data-attr-id="${attrId}" data-val-name="${esc(vname)}"
                                title="Remove '${esc(vname)}'">×</button>
                     </span>`;
        });
        html += '</div>';
        listEl.innerHTML = html;
    }

    // ── Add value ─────────────────────────────────
    document.addEventListener('click', function(e) {
        const addBtn = e.target.closest('.add-value-btn');
        if (addBtn) {
            const aid  = addBtn.getAttribute('data-attr-id');
            const aname = addBtn.getAttribute('data-attr-name');
            document.getElementById('addValueAttrId').value = aid;
            document.getElementById('addValueAttrLabel').textContent = aname;
            document.getElementById('addValueInput').value = '';
            document.getElementById('addValueError').style.display = 'none';
            new bootstrap.Modal(document.getElementById('addValueModal')).show();
            return;
        }
        const delBtn = e.target.closest('.del-value-btn');
        if (delBtn) {
            const aid   = delBtn.getAttribute('data-attr-id');
            const vname = delBtn.getAttribute('data-val-name');
            if (!confirm('Remove value "' + vname + '"? This may affect variants using it.')) return;
            deleteValue(aid, vname);
        }
    });

    document.getElementById('saveAddValueBtn').addEventListener('click', async function() {
        const attrId   = document.getElementById('addValueAttrId').value;
        const valInput = document.getElementById('addValueInput');
        const errEl    = document.getElementById('addValueError');
        const val = valInput.value.trim();
        errEl.style.display = 'none';
        if (!val) { errEl.textContent = 'Enter a value name.'; errEl.style.display = ''; return; }
        this.disabled = true;
        try {
            const fd = new FormData();
            fd.append('value', val);
            fd.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
            const res = await fetch(`<?= base_url('/product-attributes') ?>/${encodeURIComponent(attrId)}/value/add`, {
                method: 'POST', headers: {'X-Requested-With': 'XMLHttpRequest'}, body: fd
            });
            const data = await res.json();
            if (data && data.success) {
                bootstrap.Modal.getInstance(document.getElementById('addValueModal')).hide();
                await loadValuesForAttr(attrId, '');
            } else {
                errEl.textContent = (data && data.message) ? data.message : 'Failed to add.';
                errEl.style.display = '';
            }
        } catch(e) {
            errEl.textContent = 'Request failed.';
            errEl.style.display = '';
        }
        this.disabled = false;
    });

    async function deleteValue(attrId, valName) {
        try {
            const res = await fetch(`<?= base_url('/product-attributes') ?>/${encodeURIComponent(attrId)}/value/delete`, {
                method: 'POST',
                headers: {'X-Requested-With': 'XMLHttpRequest',
                          'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({value: valName, '<?= csrf_token() ?>': '<?= csrf_hash() ?>'})
            });
            const data = await res.json().catch(() => ({}));
            if (data && data.success) {
                await loadValuesForAttr(attrId, '');
            } else {
                alert((data && data.message) ? data.message : 'Could not delete value — it may be in use by variants.');
            }
        } catch(e) {
            alert('Request failed.');
        }
    }
})();
</script>
<?php endif; ?>

<?= $this->include('inventory/_quickadjust_modal') ?>
<?= $this->endSection() ?>
