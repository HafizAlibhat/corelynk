<?php /** @var array $rows */ /** @var array $warehouses */ /** @var array $filters */ ?>
<?= $this->extend('layouts/main') ?>

<?php
    $attributeOptions = $attribute_options ?? [];
    $currentAttributes = $filters['attributes'] ?? [];
    $attributeFilterCount = is_array($currentAttributes) ? count($currentAttributes) : 0;
?>

<?= $this->section('title') ?>Inventory Stock<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    /* Image hover preview (same interaction style as products list) */
    #stockImgPreview {
        display: none;
        position: fixed;
        z-index: 1080;
        pointer-events: none;
        padding: 5px;
        background: var(--cl-surface);
        border: 1px solid var(--cl-border);
        border-radius: 8px;
        box-shadow: 0 12px 32px rgba(15, 23, 42, .18), 0 2px 8px rgba(15, 23, 42, .08);
        transition: opacity .12s;
        opacity: 0;
    }
    #stockImgPreview.is-visible {
        display: block;
        opacity: 1;
    }
    #stockImgPreview img {
        display: block;
        max-width: 220px;
        max-height: 220px;
        width: auto;
        height: auto;
        object-fit: contain;
        border-radius: 5px;
    }

    .stk-attr-name { min-width: 180px; }
    .stk-attr-value { min-width: 180px; }
    .stk-filter-hint { font-size: .68rem; color: var(--cl-text-muted); margin-top: .35rem; }
    .stk-attr-tag { display: inline-flex; align-items: center; gap: .25rem; background: #2563eb; color: #fff; border-radius: 999px; padding: .18rem .5rem .18rem .68rem; font-size: .78rem; font-weight: 500; }
    .stk-attr-tag-val { font-weight: 700; }
    .stk-attr-tag-rm { display: inline-flex; align-items: center; justify-content: center; width: 16px; height: 16px; border-radius: 50%; border: none; background: rgba(255,255,255,.22); color: #fff; font-size: .7rem; line-height: 1; cursor: pointer; }
    .stk-attr-tag-rm:hover { background: rgba(255,255,255,.42); }

    /* Stock attribute filter dropdown (products-style) */
    .stk-af-row { display: flex; align-items: center; gap: .35rem; flex-wrap: nowrap; }
    .stk-af-name-sel { min-width: 180px; }
    .stk-af-val-wrap { position: relative; min-width: 220px; }
    .stk-af-val-ico {
        position: absolute;
        left: .55rem;
        top: 50%;
        transform: translateY(-50%);
        font-size: .72rem;
        color: var(--cl-text-muted);
        pointer-events: none;
        z-index: 2;
    }
    .stk-af-val-inp { padding-left: 1.7rem !important; padding-right: 1rem !important; }
    .stk-af-drop {
        display: none;
        position: absolute;
        top: calc(100% + 2px);
        left: 0;
        right: 0;
        z-index: 1070;
        margin: 0;
        padding: .2rem 0;
        list-style: none;
        background: var(--cl-surface);
        border: 1px solid var(--cl-border);
        border-radius: 6px;
        box-shadow: 0 10px 24px rgba(15,23,42,.16);
        max-height: 230px;
        overflow-y: auto;
    }
    .stk-af-drop.stk-af-open { display: block; }
    .stk-af-drop li {
        padding: .34rem .72rem;
        font-size: .78rem;
        cursor: pointer;
        color: var(--cl-text-primary);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        border-radius: 3px;
        margin: 0 .2rem;
    }
    .stk-af-drop li:hover,
    .stk-af-drop li.stk-af-active {
        background: #2563eb;
        color: #fff;
    }
    .stk-af-drop li.stk-af-none {
        color: var(--cl-text-muted);
        font-style: italic;
        cursor: default;
        pointer-events: none;
    }
    .stk-af-drop li.stk-af-none:hover { background: transparent; color: var(--cl-text-muted); }
    .stk-af-drop li mark {
        background: rgba(255,230,0,.45);
        color: inherit;
        padding: 0;
        border-radius: 2px;
        font-weight: 700;
    }
    .stk-af-drop li.stk-af-active mark { background: rgba(255,255,255,.3); color: #fff; }
    .stk-af-add-btn:disabled { opacity: .5; pointer-events: none; }
    /* Compact, Professional Stock Breakdown UI */
    .pl-stock-table .product-summary {
        cursor: pointer;
        transition: background-color 0.15s ease;
    }
    .pl-stock-table .product-summary:hover {
        background-color: var(--bs-table-hover-bg, rgba(99, 102, 241, 0.04)) !important;
    }
    .pl-stock-table .product-summary[aria-expanded="true"] {
        background-color: var(--bs-table-active-bg, rgba(99, 102, 241, 0.06)) !important;
    }

    .pl-stock-table tr.product-detail > td {
        padding-top: 0.35rem !important;
        padding-bottom: 0.35rem !important;
        background-color: rgba(15, 23, 42, 0.02) !important;
        border-bottom: 1px solid rgba(226, 232, 240, 0.5) !important;
        font-size: 0.8125rem;
    }
    html[data-bs-theme="dark"] .pl-stock-table tr.product-detail > td,
    body.theme-dark .pl-stock-table tr.product-detail > td {
        background-color: rgba(255, 255, 255, 0.018) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.04) !important;
    }

    .stk-tree-icon {
        color: var(--cl-text-muted, #64748b);
        opacity: 0.45;
        font-size: 0.75rem;
    }

    .stk-loc-row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: nowrap;
        min-width: 0;
    }
    .stk-wh-badge {
        display: inline-flex;
        align-items: center;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.025em;
        text-transform: uppercase;
        padding: 0.12rem 0.45rem;
        border-radius: 4px;
        background: rgba(99, 102, 241, 0.1);
        color: #4f46e5;
        border: 1px solid rgba(99, 102, 241, 0.2);
        white-space: nowrap;
        line-height: 1.25;
    }
    html[data-bs-theme="dark"] .stk-wh-badge,
    body.theme-dark .stk-wh-badge {
        background: rgba(99, 102, 241, 0.18);
        color: #a5b4fc;
        border-color: rgba(99, 102, 241, 0.3);
    }

    .stk-loc-breadcrumb {
        display: inline-flex;
        align-items: center;
        gap: 0.28rem;
        font-size: 0.8rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .stk-loc-node {
        color: var(--cl-text-muted, #64748b);
        font-size: 0.78rem;
    }
    .stk-loc-sep {
        font-size: 0.55rem;
        color: var(--cl-text-muted, #94a3b8);
        opacity: 0.5;
        display: inline-flex;
        align-items: center;
    }
    .stk-loc-leaf {
        color: var(--cl-text-primary, #0f172a);
        font-weight: 600;
        font-size: 0.8rem;
        background: rgba(0, 0, 0, 0.04);
        padding: 0.08rem 0.38rem;
        border-radius: 3px;
        border: 1px solid rgba(0, 0, 0, 0.06);
    }
    html[data-bs-theme="dark"] .stk-loc-leaf,
    body.theme-dark .stk-loc-leaf {
        color: #f1f5f9;
        background: rgba(255, 255, 255, 0.06);
        border-color: rgba(255, 255, 255, 0.1);
    }

    .stk-qty-cell {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        flex-wrap: wrap;
    }
    .stk-qty-num {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
        font-size: 0.84rem;
        min-width: 45px;
        text-align: right;
    }
    .stk-qty-pos {
        color: #10b981;
    }
    html[data-bs-theme="dark"] .stk-qty-pos,
    body.theme-dark .stk-qty-pos {
        color: #34d399;
    }
    .stk-qty-zero {
        color: var(--cl-text-muted, #94a3b8);
        opacity: 0.65;
    }

    .stk-res-group {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        flex-wrap: wrap;
    }
    .stk-res-chip {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.7rem;
        font-weight: 500;
        line-height: 1.2;
        padding: 0.12rem 0.45rem;
        border-radius: 999px;
        background: rgba(14, 165, 233, 0.1);
        color: #0284c7;
        border: 1px solid rgba(14, 165, 233, 0.25);
        cursor: default;
        transition: all 0.12s ease;
    }
    .stk-res-chip:hover {
        border-color: rgba(14, 165, 233, 0.5);
        transform: translateY(-1px);
    }
    html[data-bs-theme="dark"] .stk-res-chip,
    body.theme-dark .stk-res-chip {
        background: rgba(56, 189, 248, 0.12);
        color: #38bdf8;
        border-color: rgba(56, 189, 248, 0.28);
    }
    .stk-res-chip i {
        font-size: 0.62rem;
    }
    .stk-res-val {
        font-weight: 700;
        font-variant-numeric: tabular-nums;
    }
    .stk-res-doc {
        font-size: 0.65rem;
        opacity: 0.85;
    }

    /* Stat Pills for Total -> Reserved -> Left */
    .stk-summary-stat-group {
        display: inline-flex;
        align-items: center;
        justify-content: flex-end;
        gap: 0.45rem;
        flex-wrap: wrap;
    }
    .stk-stat-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
        padding: 0.15rem 0.5rem;
        border-radius: 5px;
        font-size: 0.74rem;
        line-height: 1.25;
        white-space: nowrap;
        font-variant-numeric: tabular-nums;
        transition: all 0.12s ease;
    }
    .stk-stat-lbl {
        font-size: 0.64rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        opacity: 0.75;
    }
    .stk-stat-val {
        font-weight: 700;
    }

    /* Summary Row Pills */
    .stk-stat-total {
        background: rgba(148, 163, 184, 0.12);
        color: var(--cl-text-secondary, #475569);
        border: 1px solid rgba(148, 163, 184, 0.25);
    }
    html[data-bs-theme="dark"] .stk-stat-total,
    body.theme-dark .stk-stat-total {
        background: rgba(255, 255, 255, 0.06);
        color: #cbd5e1;
        border-color: rgba(255, 255, 255, 0.12);
    }

    .stk-stat-res {
        background: rgba(245, 158, 11, 0.12);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.28);
    }
    html[data-bs-theme="dark"] .stk-stat-res,
    body.theme-dark .stk-stat-res {
        background: rgba(245, 158, 11, 0.18);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.35);
    }

    .stk-stat-left {
        background: rgba(16, 185, 129, 0.12);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.28);
    }
    html[data-bs-theme="dark"] .stk-stat-left,
    body.theme-dark .stk-stat-left {
        background: rgba(16, 185, 129, 0.18);
        color: #34d399;
        border-color: rgba(16, 185, 129, 0.35);
    }

    /* Detail Row Compact Pills */
    .stk-stat-total-sm {
        background: rgba(148, 163, 184, 0.08);
        color: var(--cl-text-muted, #64748b);
        border: 1px solid rgba(148, 163, 184, 0.18);
        font-size: 0.71rem;
        padding: 0.1rem 0.38rem;
    }
    html[data-bs-theme="dark"] .stk-stat-total-sm,
    body.theme-dark .stk-stat-total-sm {
        background: rgba(255, 255, 255, 0.04);
        color: #94a3b8;
        border-color: rgba(255, 255, 255, 0.08);
    }

    .stk-stat-left-sm {
        background: rgba(16, 185, 129, 0.1);
        color: #059669;
        border: 1px solid rgba(16, 185, 129, 0.24);
        font-size: 0.72rem;
        padding: 0.1rem 0.42rem;
    }
    html[data-bs-theme="dark"] .stk-stat-left-sm,
    body.theme-dark .stk-stat-left-sm {
        background: rgba(16, 185, 129, 0.15);
        color: #34d399;
        border-color: rgba(16, 185, 129, 0.3);
    }

    .stk-stat-zero-sm {
        background: rgba(148, 163, 184, 0.08);
        color: var(--cl-text-muted, #94a3b8);
        border: 1px solid rgba(148, 163, 184, 0.16);
        font-size: 0.71rem;
        padding: 0.1rem 0.38rem;
        opacity: 0.7;
    }

    .stk-summary-res-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.22rem;
        font-size: 0.68rem;
        font-weight: 600;
        padding: 0.1rem 0.4rem;
        border-radius: 999px;
        background: rgba(245, 158, 11, 0.1);
        color: #d97706;
        border: 1px solid rgba(245, 158, 11, 0.25);
        margin-left: 0.4rem;
        vertical-align: middle;
    }
    html[data-bs-theme="dark"] .stk-summary-res-badge,
    body.theme-dark .stk-summary-res-badge {
        background: rgba(245, 158, 11, 0.16);
        color: #fbbf24;
        border-color: rgba(245, 158, 11, 0.32);
    }
</style>
<div class="container-fluid cl-list-page cl-stock-list" data-cl-list>
<div class="cl-stock-shell data-table">
    <div class="cl-list-page-header cl-stock-header">
        <div>
            <h2 class="mb-0"><i class="bi bi-boxes me-2"></i>Inventory Stock</h2>
            <div class="small text-muted">Review product availability by warehouse and location</div>
        </div>
    </div>

    <form method="get" class="pl-filter-form cl-list-toolbar cl-stock-toolbar">
        <div class="pl-filter-toolbar">
            <div class="pl-filter-toolbar-search">
                <div class="pl-filter-search-label">
                    <label for="productSearch">Search stock</label>
                    <span>Press Enter to search</span>
                </div>
                <div class="pl-search-shell">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" name="product_search" id="productSearch" list="productList" class="form-control pl-filter-control pl-filter-control-search" placeholder="Search product name, code, SKU, variant, or attribute" value="<?= esc($filters['product_search'] ?? '') ?>">
                    <datalist id="productList"></datalist>
                </div>
            </div>
            <div class="pl-filter-toolbar-actions">
                <button type="submit" class="btn btn-primary pl-filter-btn pl-filter-btn-primary"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                <a href="<?= base_url('inventory/stock') ?>" class="btn btn-outline-secondary pl-filter-btn pl-filter-btn-soft text-decoration-none">Reset</a>
            </div>
        </div>

        <div class="pl-filter-chipbar" aria-label="Stock filters">
            <div class="pl-chip-field pl-chip-field-lg">
                <label class="pl-chip-label" for="stockWarehouse">Warehouse</label>
                <select name="warehouse_id" id="stockWarehouse" class="form-select pl-filter-control">
                    <option value="">All warehouses</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= esc($w['id']) ?>" <?= (isset($filters['warehouse_id']) && $filters['warehouse_id']==$w['id'])? 'selected':'' ?>><?= esc($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

                <details class="pl-more-filters cl-list-more-filters" <?= $attributeFilterCount > 0 ? 'open' : '' ?>>
                    <summary class="pl-more-filters-summary">
                        <span>More filters</span>
                        <span class="pl-more-filters-meta cl-list-more-filters-meta"><?= $attributeFilterCount ?> attribute filter<?= $attributeFilterCount === 1 ? '' : 's' ?></span>
                    </summary>
                    <div class="pl-more-filters-panel cl-list-more-filters-panel">
                    <div class="stk-af-row" id="stkAfRow" aria-label="Stock attribute filters">
                        <select class="form-select stk-af-name-sel" id="stkAfNameSel" title="Select attribute">
                            <option value="">Attribute</option>
                            <?php foreach ($attributeOptions as $attrName => $attrValues): ?>
                                <option value="<?= esc($attrName) ?>"><?= esc($attrName) ?></option>
                            <?php endforeach; ?>
                        </select>

                        <div class="stk-af-val-wrap">
                            <i class="bi bi-search stk-af-val-ico"></i>
                            <input type="text" class="form-control stk-af-val-inp" id="stkAfValueInp" placeholder="Search value…" autocomplete="off">
                            <ul id="stkAfDrop" class="stk-af-drop" role="listbox" aria-label="Attribute values"></ul>
                        </div>

                        <button type="button" class="btn btn-outline-primary stk-af-add-btn" id="stkAfAddBtn" disabled>
                            <i class="bi bi-plus"></i> Add Attribute Filter
                        </button>
                    </div>

                    <div id="stkAfChips" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div id="stkAfHidden" style="display:none"></div>
                    <div class="stk-filter-hint">Pro tip: This stock search now supports product code, variant code/name, and attribute filters exactly like products search.</div>
                    </div>
                </details>
    </form>

    <!-- Stock Table -->
    <div class="card border-0 shadow-sm cl-list-table-card">
    <div class="card-body p-0">
            <div class="table-responsive">
                <table class="cl-table table table-sm table-hover pl-table pl-stock-table data-table-table mb-0 align-middle">
                    <caption class="visually-hidden">Inventory stock grouped by product and location</caption>
                    <colgroup>
                        <col style="width: 44px;">
                        <col style="width: 128px;">
                        <col style="width: 76px;">
                        <col>
                        <col style="width: 380px;">
                    </colgroup>
                    <thead>
                                <tr>
                                    <th scope="col" class="text-center">#</th>
                                    <th scope="col">Product Code</th>
                                    <th scope="col">Image</th>
                                    <th scope="col">Product</th>
                                    <th scope="col" class="text-end">Stock Breakdown</th>
                                </tr>
                    </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="5"><div class="cl-list-empty cl-list-empty--row"><span class="cl-list-empty__icon" aria-hidden="true"><i class="bi bi-boxes"></i></span><h6>No Stock Found</h6><p>Try another warehouse, product, or attribute filter.</p></div></td>
                                    </tr>
                                <?php else: ?>
                                    <?php
                                        // Group rows by variant_id when present, else product_id
                                        $groups = [];
                                        foreach ($rows as $r) {
                                            $vid = $r['variant_id'] ?? null;
                                            $pid = $r['product_id'] ?? null;
                                            if (!empty($vid)) {
                                                $key = 'v' . (int)$vid;
                                            } else {
                                                $key = $pid ? 'p'.$pid : 's'.($r['product_sku'] ?? uniqid('x', true));
                                            }
                                            if (!isset($groups[$key])) {
                                                $groups[$key] = [
                                                    'product_id' => $pid,
                                                    'variant_id' => $vid,
                                                    'product_name' => $r['product_name'] ?? '',
                                                    'product_sku' => $r['product_sku'] ?? '',
                                                    'variant_name' => $r['variant_name'] ?? '',
                                                    'variant_art_number' => $r['variant_art_number'] ?? '',
                                                    'rows' => [],
                                                    'total' => 0.0,
                                                    'total_stock' => 0.0,
                                                    'reserved_total' => 0.0,
                                                ];
                                            }
                                            $qty = isset($r['quantity']) ? (float)$r['quantity'] : 0.0;
                                            $tStock = isset($r['total_stock']) ? (float)$r['total_stock'] : ($qty + (float)($r['reserved_total'] ?? 0.0));
                                            $groups[$key]['rows'][] = $r;
                                            $groups[$key]['total'] += $qty;
                                            $groups[$key]['total_stock'] += $tStock;
                                            $groups[$key]['reserved_total'] += (float)($r['reserved_total'] ?? 0.0);
                                        }
                                        $grpIndex = 0;
                                    ?>
                                    <?php
                                        $db = \Config\Database::connect();
                                        $skuCache = [];
                                        // Batch-resolve SKUs for products that are missing product_sku in the grouped rows
                                        $idsNeedingSku = [];
                                        foreach ($groups as $gk => $g) {
                                            if (empty($g['variant_id']) && (empty($g['product_sku']) || $g['product_sku'] === '') && !empty($g['product_id'])) {
                                                $idsNeedingSku[] = (int)$g['product_id'];
                                            }
                                        }
                                        $idsNeedingSku = array_values(array_unique(array_filter($idsNeedingSku)));
                                        if (!empty($idsNeedingSku)) {
                                            $chunks = array_chunk($idsNeedingSku, 500);
                                            foreach ($chunks as $chunk) {
                                                $rowsSku = $db->table('products')->select('id, code, sku')->whereIn('id', $chunk)->get()->getResultArray();
                                                foreach ($rowsSku as $rs) {
                                                    $idKey = (int)$rs['id'];
                                                    $skuCache[$idKey] = [
                                                        'code' => $rs['code'] ?? null,
                                                        'sku'  => $rs['sku'] ?? null,
                                                    ];
                                                }
                                            }
                                        }
                                    ?>
                                    <?php foreach ($groups as $gk => $g): $grpIndex++; $gid = 'grp'.$grpIndex; ?>
                                        <!-- Product summary row -->
                                            <tr class="product-summary align-middle" data-group="<?= esc($gid) ?>" aria-expanded="false" tabindex="0" role="button" style="cursor:pointer;">
                                            <?php $detailCount = count($g['rows']); ?>
                                            <?php
                                                // Display code: prefer variant art number, else product code/sku
                                                $displayCode = null;
                                                if (!empty($g['variant_id']) && !empty($g['variant_art_number'])) {
                                                    $displayCode = $g['variant_art_number'];
                                                } else {
                                                    $displayCode = $g['product_sku'] ?? null;
                                                    if (!empty($g['product_id'])) {
                                                        $pid = (int)$g['product_id'];
                                                        if (isset($skuCache[$pid])) {
                                                            $cache = $skuCache[$pid];
                                                            if (!empty($cache['code'])) {
                                                                $displayCode = $cache['code'];
                                                            } elseif (!empty($cache['sku'])) {
                                                                $displayCode = $cache['sku'];
                                                            }
                                                        }
                                                    }
                                                }
                                                    // Image: use variant image when available, else product images
                                                    $img = base_url('assets/images/no-image.png');
                                                    if (!empty($g['variant_id']) && !empty($g['rows'][0]['variant_image'] ?? null)) {
                                                        $img = base_url('/uploads/variants/' . ltrim($g['rows'][0]['variant_image'], '/'));
                                                    } elseif (!empty($g['rows'][0]['product_images'] ?? null)) {
                                                        $imgs = is_string($g['rows'][0]['product_images']) ? json_decode($g['rows'][0]['product_images'], true) : $g['rows'][0]['product_images'];
                                                        if (is_array($imgs) && !empty($imgs[0])) {
                                                            $img = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                                                        }
                                                    }
                                            ?>
                                                <td class="py-1 align-middle text-center cl-stock-row-number">
                                                    <?= (int)$grpIndex ?>
                                                </td>
                                                <td class="py-1 align-middle small fw-bold" title="<?= esc($displayCode ?? 'No Code') ?>">
                                                    <code class="cl-stock-code"><?= esc($displayCode !== '' && $displayCode !== null ? $displayCode : '-') ?></code>
                                                </td>
                                                <td class="py-1 align-middle">
                                                    <img src="<?= esc($img) ?>" alt="" class="cl-stock-thumb" data-preview="<?= esc($img) ?>" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.dataset.preview='<?= base_url('assets/images/no-image.png') ?>';">
                                                </td>
                                                <td class="py-1 align-middle">
                                                    <div class="fw-semibold cl-stock-product-name">
                                                        <i class="bi bi-chevron-right toggle-chevron me-2" aria-hidden="true"></i>
                                                        <?= esc($g['product_name'] ?: 'Unnamed Product') ?>
                                                        <?php if (!empty($g['variant_id'])): ?>
                                                            <span class="text-muted fw-normal">— <?= esc($g['variant_name'] ?: 'Variant') ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                            </td>
                                            <td class="py-1 align-middle text-end pe-3">
                                                <?php
                                                    $grpTotalStock = $g['total_stock'] ?? ($g['total'] + ($g['reserved_total'] ?? 0.0));
                                                    $grpReserved = $g['reserved_total'] ?? 0.0;
                                                    $grpLeft = $g['total'];
                                                ?>
                                                <div class="stk-summary-stat-group">
                                                    <span class="stk-stat-pill stk-stat-total" title="Total initial physical stock across all locations">
                                                        <span class="stk-stat-lbl">Total</span>
                                                        <span class="stk-stat-val"><?= number_format($grpTotalStock, 2) ?></span>
                                                    </span>
                                                    <?php if ($grpReserved > 0.0001): ?>
                                                        <span class="stk-stat-pill stk-stat-res" title="Total reserved by documents across all locations">
                                                            <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                            <span class="stk-stat-lbl">Reserved</span>
                                                            <span class="stk-stat-val"><?= number_format($grpReserved, 2) ?></span>
                                                        </span>
                                                    <?php endif; ?>
                                                    <span class="stk-stat-pill stk-stat-left" title="Available stock remaining (Free to use)">
                                                        <span class="stk-stat-lbl">Left</span>
                                                        <span class="stk-stat-val"><?= number_format($grpLeft, 2) ?></span>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        <!-- Location detail rows -->
                                <?php foreach ($g['rows'] as $dr): ?>
                                    <?php
                                        $resSummary = [];
                                        if (!empty($dr['reservations'])) {
                                            foreach ($dr['reservations'] as $res) {
                                                $rem = (float)($res['remaining'] ?? 0);
                                                if ($rem <= 0.0001) continue;
                                                $doc = $res['order_number'] ?: ($res['document_type'] . ' #' . $res['document_id']);
                                                if (!isset($resSummary[$doc])) {
                                                    $resSummary[$doc] = [
                                                        'total' => 0.0,
                                                        'allocations' => 0,
                                                        'parts' => [],
                                                    ];
                                                }
                                                $resSummary[$doc]['total'] += $rem;
                                                $resSummary[$doc]['allocations']++;
                                                $resSummary[$doc]['parts'][] = number_format($rem, 2);
                                            }
                                        }
                                        $path = $dr['location_path'] ?? $dr['location_name'] ?? '-';
                                        $segments = array_values(array_filter(array_map('trim', explode('/', $path))));
                                        $segCount = count($segments);
                                    ?>
                                    <tr class="product-detail group-<?= esc($gid) ?>">
                                        <td class="py-1 text-center align-middle">
                                            <i class="bi bi-arrow-return-right stk-tree-icon" aria-hidden="true"></i>
                                        </td>
                                        <td class="py-1"></td>
                                        <td class="py-1"></td>
                                        <td class="py-1 align-middle">
                                            <div class="stk-loc-row">
                                                <?php if (!empty($dr['warehouse_name'])): ?>
                                                    <span class="stk-wh-badge"><?= esc($dr['warehouse_name']) ?></span>
                                                <?php endif; ?>
                                                <div class="stk-loc-breadcrumb" title="<?= esc($path) ?>">
                                                    <?php if ($segCount > 0): ?>
                                                        <?php foreach ($segments as $sIdx => $seg): ?>
                                                            <?php $isLeaf = ($sIdx === $segCount - 1); ?>
                                                            <span class="<?= $isLeaf ? 'stk-loc-leaf' : 'stk-loc-node' ?>"><?= esc($seg) ?></span>
                                                            <?php if (!$isLeaf): ?>
                                                                <span class="stk-loc-sep"><i class="bi bi-chevron-right"></i></span>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <span class="stk-loc-leaf">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-1 align-middle text-end pe-3">
                                            <?php
                                                $locQty = (float)$dr['quantity'];
                                                $locReserved = (float)($dr['reserved_total'] ?? 0.0);
                                                $locTotal = isset($dr['total_stock']) ? (float)$dr['total_stock'] : ($locQty + $locReserved);
                                            ?>
                                            <div class="stk-qty-cell">
                                                <span class="stk-stat-pill stk-stat-total-sm" title="Total initial stock in this box: <?= number_format($locTotal, 2) ?>">
                                                    <span class="stk-stat-lbl">Total</span>
                                                    <span class="stk-stat-val"><?= number_format($locTotal, 2) ?></span>
                                                </span>
                                                <?php if (!empty($resSummary)): ?>
                                                    <div class="stk-res-group">
                                                        <?php foreach ($resSummary as $docRef => $info): ?>
                                                            <?php
                                                                $tooltip = 'Reserved: ' . number_format($info['total'], 2) . ' for ' . $docRef;
                                                                if ($info['allocations'] > 1) {
                                                                    $tooltip .= ' (' . $info['allocations'] . ' allocations: ' . implode(' + ', $info['parts']) . ')';
                                                                }
                                                            ?>
                                                            <span class="stk-res-chip" title="<?= esc($tooltip) ?>">
                                                                <i class="bi bi-lock-fill" aria-hidden="true"></i>
                                                                <span class="stk-res-val"><?= number_format($info['total'], 2) ?></span>
                                                                <span class="stk-res-doc">(<?= esc($docRef) ?>)</span>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="stk-stat-pill <?= $locQty > 0.0001 ? 'stk-stat-left-sm' : 'stk-stat-zero-sm' ?>" title="Available stock remaining in this box: <?= number_format($locQty, 2) ?>">
                                                    <span class="stk-stat-lbl">Left</span>
                                                    <span class="stk-stat-val"><?= number_format($locQty, 2) ?></span>
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                </table>
            </div>
    </div>
    </div>
</div>

    <!-- Image hover preview -->
    <div id="stockImgPreview"><img src="" alt="" id="stockImgPreviewImg"></div>

    <script>
    // Stock attribute filter handler (products-style interactive dropdown)
    (function() {
        const attrMap = <?= json_encode($attributeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?> || {};
        const nameSel = document.getElementById('stkAfNameSel');
        const valInp = document.getElementById('stkAfValueInp');
        const drop = document.getElementById('stkAfDrop');
        const addBtn = document.getElementById('stkAfAddBtn');
        const chipsRow = document.getElementById('stkAfChips');
        const hiddenDiv = document.getElementById('stkAfHidden');

        if (!nameSel || !valInp || !drop || !addBtn || !chipsRow || !hiddenDiv) {
            return;
        }

        let activeIdx = -1;
        let filters = <?= json_encode($currentAttributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
        if (!Array.isArray(filters)) {
            filters = [];
        }

        function esc(str) {
            const d = document.createElement('div');
            d.textContent = String(str || '');
            return d.innerHTML;
        }

        function updateAddBtn() {
            addBtn.disabled = !(nameSel.value.trim() && valInp.value.trim());
        }

        function showDrop(values, keyword) {
            drop.innerHTML = '';
            activeIdx = -1;

            if (!values.length) {
                const li = document.createElement('li');
                li.className = 'stk-af-none';
                li.textContent = keyword ? ('No values match "' + keyword + '"') : 'No values available';
                drop.appendChild(li);
            } else {
                values.forEach(function(v) {
                    const li = document.createElement('li');
                    li.setAttribute('role', 'option');
                    li.innerHTML = highlight(esc(v), keyword);
                    li.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        selectValue(v);
                    });
                    drop.appendChild(li);
                });
            }

            drop.classList.add('stk-af-open');
        }

        function hideDrop() {
            drop.classList.remove('stk-af-open');
            activeIdx = -1;
        }

        function highlight(escapedHtml, keyword) {
            if (!keyword) return escapedHtml;
            const kw = keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return escapedHtml.replace(new RegExp('(' + kw + ')', 'gi'), '<mark>$1</mark>');
        }

        function openList() {
            const attr = nameSel.value.trim();
            if (!attr) {
                drop.innerHTML = '<li class="stk-af-none">Pick an attribute first</li>';
                drop.classList.add('stk-af-open');
                return;
            }
            const values = Array.isArray(attrMap[attr]) ? attrMap[attr] : [];
            const kw = valInp.value.trim().toLowerCase();
            const filtered = kw ? values.filter(v => String(v).toLowerCase().indexOf(kw) !== -1) : values;
            showDrop(filtered, kw);
        }

        function moveCursor(dir) {
            const items = drop.querySelectorAll('li:not(.stk-af-none)');
            if (!items.length) return;
            items.forEach(el => el.classList.remove('stk-af-active'));
            activeIdx = Math.max(0, Math.min(items.length - 1, activeIdx + dir));
            items[activeIdx].classList.add('stk-af-active');
            items[activeIdx].scrollIntoView({ block: 'nearest' });
        }

        function selectValue(v) {
            valInp.value = v;
            hideDrop();
            updateAddBtn();
            valInp.focus();
        }

        function renderChips() {
            chipsRow.innerHTML = '';
            hiddenDiv.innerHTML = '';

            filters.forEach(function(f, idx) {
                const chip = document.createElement('span');
                chip.className = 'stk-attr-tag';
                chip.innerHTML = '<span style="opacity:.8">' + esc(f.name) + '</span>'
                    + '<span style="opacity:.5">:</span>'
                    + '<span class="stk-attr-tag-val">' + esc(f.value) + '</span>'
                    + '<button type="button" class="stk-attr-tag-rm" data-i="' + idx + '">&times;</button>';

                const rm = chip.querySelector('.stk-attr-tag-rm');
                rm.addEventListener('click', function() {
                    filters.splice(parseInt(this.dataset.i, 10), 1);
                    renderChips();
                });

                chipsRow.appendChild(chip);

                const ni = document.createElement('input');
                ni.type = 'hidden';
                ni.name = 'attr[' + idx + '][name]';
                ni.value = f.name;
                hiddenDiv.appendChild(ni);

                const vi = document.createElement('input');
                vi.type = 'hidden';
                vi.name = 'attr[' + idx + '][value]';
                vi.value = f.value;
                hiddenDiv.appendChild(vi);
            });
        }

        function addFilter() {
            const name = nameSel.value.trim();
            const value = valInp.value.trim();
            if (!name || !value) return;

            const dup = filters.some(function(f) {
                return String(f.name).toLowerCase() === name.toLowerCase()
                    && String(f.value).toLowerCase() === value.toLowerCase();
            });
            if (dup) return;

            filters.push({ name: name, value: value });
            renderChips();
            valInp.value = '';
            updateAddBtn();
            valInp.focus();
            hideDrop();
        }

        nameSel.addEventListener('change', function() {
            valInp.value = '';
            updateAddBtn();
            openList();
            valInp.focus();
        });

        valInp.addEventListener('input', function() {
            updateAddBtn();
            openList();
        });

        valInp.addEventListener('mousedown', function() {
            if (!drop.classList.contains('stk-af-open')) {
                openList();
            }
        });

        valInp.addEventListener('focus', function() {
            if (!drop.classList.contains('stk-af-open')) {
                openList();
            }
        });

        valInp.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (drop.classList.contains('stk-af-open')) moveCursor(1);
                else openList();
                return;
            }
            if (e.key === 'ArrowUp') {
                e.preventDefault();
                moveCursor(-1);
                return;
            }
            if (e.key === 'Escape') {
                hideDrop();
                return;
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                if (drop.classList.contains('stk-af-open') && activeIdx >= 0) {
                    const items = drop.querySelectorAll('li:not(.stk-af-none)');
                    if (items[activeIdx]) {
                        selectValue(items[activeIdx].textContent);
                        return;
                    }
                }
                addFilter();
            }
        });

        addBtn.addEventListener('click', function(e) {
            e.preventDefault();
            addFilter();
        });

        document.addEventListener('mousedown', function(e) {
            if (!drop.contains(e.target) && e.target !== valInp) {
                hideDrop();
            }
        });

        renderChips();
        updateAddBtn();
    })();

    // Expand/collapse product groups
    (function(){
        document.querySelectorAll('.product-summary').forEach(function(row){
            // default collapsed
            var gidInit = row.getAttribute('data-group');
            var detailsInit = document.querySelectorAll('.group-'+gidInit);
            detailsInit.forEach(function(d){ d.style.display = 'none'; });
            row.setAttribute('aria-expanded', 'false');
            var cheInit = row.querySelector('.toggle-chevron');
            if (cheInit) cheInit.className = 'bi bi-chevron-right toggle-chevron me-2';

            row.addEventListener('click', function(){
                var gid = row.getAttribute('data-group');
                var details = document.querySelectorAll('.group-'+gid);
                var expanded = row.getAttribute('aria-expanded') === 'true';
                details.forEach(function(d){ d.style.display = expanded ? 'none' : ''; });
                row.setAttribute('aria-expanded', (!expanded).toString());
                var che = row.querySelector('.toggle-chevron');
                if (che) che.className = (!expanded) ? 'bi bi-chevron-down toggle-chevron me-2' : 'bi bi-chevron-right toggle-chevron me-2';
            });
            row.addEventListener('keydown', function(event){
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    row.click();
                }
            });
        });
    })();

    // Simple product autocomplete: fetch suggestions and populate datalist
    (function(){
        const input = document.getElementById('productSearch');
        const list = document.getElementById('productList');
        let last = '';
        if (!input) return;
        input.addEventListener('input', function(){
            const v = this.value.trim();
            if (v.length < 2 || v === last) return;
            last = v;
            fetch('<?= base_url('inventory/product-autocomplete') ?>?q=' + encodeURIComponent(v))
                .then(r => r.json())
                .then(js => {
                    if (!js.success) return;
                    list.innerHTML = '';
                    js.data.forEach(item => {
                        const opt = document.createElement('option');
                        opt.value = item.text;
                        list.appendChild(opt);
                    });
                }).catch(()=>{});
        });
    })();

    // Image hover preview
    (function () {
        const preview = document.getElementById('stockImgPreview');
        const previewImg = document.getElementById('stockImgPreviewImg');
        if (!preview || !previewImg) return;

        let hideTimer = null;

        function position(e) {
            const vw = window.innerWidth, vh = window.innerHeight;
            const pw = 190, ph = 190;
            let left = e.clientX + 14;
            let top = e.clientY + 14;
            if (left + pw > vw - 8) left = e.clientX - pw - 10;
            if (top + ph > vh - 8) top = e.clientY - ph - 10;
            preview.style.left = left + 'px';
            preview.style.top = top + 'px';
        }

        document.addEventListener('mouseover', function (e) {
            const thumb = e.target.closest('[data-preview]');
            if (!thumb) return;
            clearTimeout(hideTimer);
            previewImg.src = thumb.dataset.preview || '';
            preview.classList.add('is-visible');
            position(e);
        });

        document.addEventListener('mousemove', function (e) {
            if (!preview.classList.contains('is-visible')) return;
            if (!e.target.closest('[data-preview]')) return;
            position(e);
        });

        document.addEventListener('mouseout', function (e) {
            const thumb = e.target.closest('[data-preview]');
            if (!thumb) return;
            hideTimer = setTimeout(() => preview.classList.remove('is-visible'), 80);
        });
    })();
    </script>
</div>

<?= $this->endSection() ?>


