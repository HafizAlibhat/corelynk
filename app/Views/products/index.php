<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $pager         = $products['pager'] ?? null;
    $totalRecords  = $pager ? $pager->getTotal()       : count($products['data']);
    $currentPage   = $pager ? $pager->getCurrentPage() : 1;
    $currentPerPage= $pager ? $pager->getPerPage()     : ($per_page ?? 20);
    $totalPages    = $currentPerPage > 0 ? (int)ceil($totalRecords / $currentPerPage) : 1;
    if ($totalPages < 1) $totalPages = 1;
    $startItem = $totalRecords > 0 ? (($currentPage - 1) * $currentPerPage) + 1 : 0;
    $endItem   = min($totalRecords, $currentPage * $currentPerPage);
    
    // Build pagination params including multiple attribute filters
    $pgParams  = array_filter([
        'search' => $current_search ?? '',
        'category' => $current_category ?? '',
        'status' => $current_status ?? '',
        'type' => $current_type ?? '',
        'per_page' => $per_page ?? 20
    ], fn($v) => $v !== '' && $v !== null);
    
    // Add multiple attribute filters to pagination params
    $current_attributes = $current_attributes ?? [];
    if (!empty($current_attributes) && is_array($current_attributes)) {
        foreach ($current_attributes as $idx => $attr) {
            if (!empty($attr['name']) && !empty($attr['value'])) {
                $pgParams["attr[{$idx}][name]"] = $attr['name'];
                $pgParams["attr[{$idx}][value]"] = $attr['value'];
            }
        }
    }
    
    $pgBase    = base_url('/products') . '?' . http_build_query($pgParams) . '&page=';
    $attributeOptions = $attribute_options ?? [];
    $matchedVariantsByProduct = $matched_variants_by_product ?? [];
?>

<style>
    .pl-wrap { padding: .75rem 1rem; }
    .pl-card { border: 1px solid var(--cl-border); border-radius: 6px; overflow: hidden; box-shadow: var(--cl-shadow-xs); background: var(--cl-surface); }
    .pl-card-header { display: flex; align-items: center; justify-content: space-between; padding: .45rem .75rem; border-bottom: 1px solid var(--cl-border); gap: .5rem; }
    .pl-card-header-title { font-size: .82rem; font-weight: 700; color: var(--cl-text-primary); line-height: 1; }
    .pl-card-header-sub { font-size: .66rem; color: var(--cl-text-muted); margin-top: .1rem; }
    .pl-filter-bar { display: flex; align-items: center; flex-wrap: wrap; gap: .3rem; padding: .4rem .75rem; border-bottom: 1px solid var(--cl-border); background: var(--cl-surface); }
    .pl-filter-bar .form-control, .pl-filter-bar .form-select { font-size: .74rem; padding: .18rem .45rem; height: 26px; border-radius: 4px; }
    .pl-search-wrap { position: relative; flex: 1; min-width: 140px; max-width: 220px; }
    .pl-search-wrap .bi-search { position: absolute; left: .45rem; top: 50%; transform: translateY(-50%); font-size: .7rem; color: var(--cl-text-muted); pointer-events: none; }
    .pl-search-wrap .form-control { padding-left: 1.55rem; }
    .pl-filter-bar .form-select { padding-right: 1.6rem; }
    .pl-filter-bar select.pl-cat { width: 130px; }
    .pl-filter-bar select.pl-status { width: 100px; }
    .pl-filter-bar select.pl-type { width: 110px; }
    .pl-filter-bar select.pl-pp { width: 68px; }
    .pl-filter-bar .pl-tag { width: 150px; }
    .pl-filter-bar .pl-attr-name { width: 170px; }
    .pl-filter-bar .pl-attr-value { width: 180px; }
    .pl-filter-hint { width: 100%; font-size: .63rem; color: var(--cl-text-muted); margin-top: .08rem; }
    body.theme-dark .pl-filter-hint { color: #94a3b8; }
    .pl-btn { display: inline-flex; align-items: center; justify-content: center; height: 26px; padding: 0 .55rem; font-size: .72rem; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); cursor: pointer; gap: .28rem; text-decoration: none; white-space: nowrap; transition: all .12s; }
    .pl-btn:hover { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
    .pl-btn.pl-btn-primary { background: var(--cl-primary); border-color: var(--cl-primary); color: #fff; }
    .pl-btn.pl-btn-primary:hover { opacity: .88; color: #fff; }
    .pl-table { margin-bottom: 0; font-size: .76rem; }
    .pl-card .pl-table thead th { font-size: .62rem !important; text-transform: uppercase; letter-spacing: .05em; color: var(--cl-text-muted); background: var(--cl-surface-alt); border-bottom: 1px solid var(--cl-border); padding: .26rem .45rem !important; white-space: nowrap; font-weight: 700 !important; }
    body.theme-dark .pl-card .pl-table thead th { color: #94a3b8 !important; background: #162033 !important; border-bottom-color: #334155 !important; }
    .pl-card .pl-table tbody td { padding: .18rem .45rem !important; border-bottom: 1px solid var(--cl-border-light); vertical-align: middle; font-size: .76rem !important; }
    .pl-table tbody tr:last-child td { border-bottom: none; }
    body.theme-dark .pl-table tbody tr:nth-child(even) td { background: #0f1a2b !important; }
    body.theme-dark .pl-table tbody tr:nth-child(odd) td  { background: #1a2740 !important; }
    body.theme-dark .pl-table tbody tr:hover td { background: rgba(37,99,235,.1) !important; }
    .pl-thumb { width: 24px; height: 24px; border-radius: 3px; border: 1px solid var(--cl-border); object-fit: cover; flex-shrink: 0; cursor: default; }
    .pl-thumb-empty { width: 24px; height: 24px; border-radius: 3px; border: 1px solid var(--cl-border); background: var(--cl-surface-alt); display: inline-flex; align-items: center; justify-content: center; color: var(--cl-text-muted); font-size: .62rem; flex-shrink: 0; }
    /* Image hover preview */
    #plImgPreview { display:none; position:fixed; z-index:1080; pointer-events:none; padding:5px; background:var(--cl-surface); border:1px solid var(--cl-border); border-radius:8px; box-shadow:0 12px 32px rgba(15,23,42,.18),0 2px 8px rgba(15,23,42,.08); transition:opacity .12s; opacity:0; }
    #plImgPreview.is-visible { display:block; opacity:1; }
    #plImgPreview img { display:block; max-width:220px; max-height:220px; width:auto; height:auto; object-fit:contain; border-radius:5px; }
    .pl-name { font-size: .76rem; font-weight: 600; line-height: 1.1; }
    .pl-desc { font-size: .63rem; color: var(--cl-text-muted); margin-top: .05rem; line-height: 1.15; }
    /* ── Variant filter mode notice bar ─────────────────────────── */
    .pl-filter-mode-bar { display: flex; align-items: center; flex-wrap: wrap; gap: .35rem; padding: .3rem .75rem; background: rgba(37,99,235,.05); border-bottom: 1px solid rgba(37,99,235,.18); }
    body.theme-dark .pl-filter-mode-bar { background: rgba(37,99,235,.1); border-bottom-color: rgba(37,99,235,.3); }
    .pl-fmode-label { font-size: .64rem; font-weight: 700; color: #2563eb; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; }
    body.theme-dark .pl-fmode-label { color: #93c5fd; }
    .pl-fmode-pill { display: inline-flex; align-items: center; gap: .15rem; background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.28); border-radius: 999px; padding: .07rem .45rem; font-size: .63rem; color: #1d4ed8; white-space: nowrap; }
    .pl-fmode-pill strong { font-weight: 700; }
    body.theme-dark .pl-fmode-pill { background: rgba(37,99,235,.18); border-color: rgba(59,130,246,.45); color: #93c5fd; }
    .pl-fmode-count { font-size: .63rem; color: var(--cl-text-muted); margin-left: auto; white-space: nowrap; }
    .pl-fmode-count strong { color: var(--cl-text-secondary); }
    /* ── Group header rows ─────────────────────────────────────── */
    .pl-group-hdr td { padding: 0 !important; border-bottom: 1px solid var(--cl-border) !important; }
    .pl-group-hdr:not(:first-child) td { border-top: 2px solid var(--cl-border) !important; }
    .pl-ghdr-inner { display: flex; align-items: center; gap: .55rem; padding: .32rem .75rem .32rem .68rem; border-left: 3px solid var(--cl-primary); background: var(--cl-surface-alt); }
    body.theme-dark .pl-ghdr-inner { background: #162033; }
    .pl-ghdr-thumb { width: 32px; height: 32px; border-radius: 4px; border: 1px solid var(--cl-border); object-fit: cover; flex-shrink: 0; }
    .pl-ghdr-thumb-empty { width: 32px; height: 32px; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); display: inline-flex; align-items: center; justify-content: center; color: var(--cl-text-muted); font-size: .82rem; flex-shrink: 0; }
    .pl-ghdr-name { font-size: .77rem; font-weight: 700; color: var(--cl-text-primary); text-decoration: none; line-height: 1; }
    .pl-ghdr-name:hover { color: var(--cl-primary); }
    .pl-ghdr-cat { font-size: .6rem; color: var(--cl-text-muted); background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: 3px; padding: .04rem .28rem; line-height: 1.4; white-space: nowrap; }
    body.theme-dark .pl-ghdr-cat { background: #0f1a2b; }
    .pl-ghdr-vmeta { font-size: .62rem; color: var(--cl-text-muted); margin-top: .1rem; display: flex; align-items: center; gap: .28rem; flex-wrap: wrap; }
    .pl-ghdr-vmatch { font-weight: 700; color: var(--cl-primary); }
    body.theme-dark .pl-ghdr-vmatch { color: #93c5fd; }
    .pl-ghdr-sep { opacity: .35; font-size: .6rem; }
    .pl-ghdr-vbtn { height: 24px !important; font-size: .63rem !important; white-space: nowrap; flex-shrink: 0; gap: .22rem !important; }
    /* ── Variant rows ──────────────────────────────────────────── */
    .pl-variant-row td { background: var(--cl-surface) !important; font-size: .94rem !important; }
    body.theme-dark .pl-variant-row td { background: #0c1829 !important; }
    .pl-variant-row:hover td { background: rgba(37,99,235,.04) !important; }
    body.theme-dark .pl-variant-row:hover td { background: rgba(37,99,235,.1) !important; }
    .pl-vr-indent { width: 28px; border-left: 3px solid rgba(37,99,235,.3) !important; padding: 0 !important; }
    body.theme-dark .pl-vr-indent { border-left-color: rgba(59,130,246,.45) !important; }
    .pl-vr-main { display: flex; align-items: center; gap: .45rem; min-width: 0; }
    .pl-vr-thumb { width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--cl-border); object-fit: cover; flex-shrink: 0; cursor: pointer; }
    .pl-vr-thumb-empty { width: 24px; height: 24px; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface-alt); display: inline-flex; align-items: center; justify-content: center; color: var(--cl-text-muted); font-size: .6rem; flex-shrink: 0; }
    .pl-vr-art { font-family: var(--bs-font-monospace,'Menlo','Consolas',monospace); font-size: .92rem; font-weight: 700; color: #2563eb; background: rgba(37,99,235,.08); border: 1px solid rgba(37,99,235,.2); border-radius: 4px; padding: .2rem .58rem; white-space: nowrap; }
    body.theme-dark .pl-vr-art { background: rgba(37,99,235,.15); border-color: rgba(59,130,246,.38); color: #93c5fd; }
    /* ── Attribute badges ─────────────────────────────────────── */
    .pl-attr-badge { display: inline-flex; align-items: center; border-radius: 4px; padding: .19rem .58rem; font-size: .82rem; line-height: 1.5; white-space: nowrap; font-weight: 500; }
    .pl-ab-k { opacity: .8; margin-right: .26rem; font-size: .78rem; }
    /* Active badge (matches the current filter) */
    .pl-ab-active { background: rgba(37,99,235,.1); border: 1px solid rgba(37,99,235,.3); color: #1d4ed8; }
    .pl-ab-active strong { font-weight: 700; color: #1d4ed8; }
    body.theme-dark .pl-ab-active { background: rgba(37,99,235,.18); border-color: rgba(59,130,246,.45); color: #93c5fd; }
    body.theme-dark .pl-ab-active strong { color: #93c5fd; }
    /* Other badge (not part of the filter) */
    .pl-ab-other { background: var(--cl-surface-alt); border: 1px solid var(--cl-border); color: var(--cl-text-muted); }
    .pl-ab-other strong { font-weight: 600; color: var(--cl-text-secondary); }
    body.theme-dark .pl-ab-other { background: #162033; border-color: #2d3e55; color: #64748b; }
    body.theme-dark .pl-ab-other strong { color: #94a3b8; }
    /* ── Filter tag pills (set by JS in filter bar) ─────────────── */
    .pl-attr-tag { display: inline-flex; align-items: center; gap: .22rem; background: var(--cl-primary); color: #fff; border-radius: 999px; padding: .12rem .4rem .12rem .6rem; font-size: .68rem; font-weight: 500; cursor: default; }
    .pl-attr-tag-val { font-weight: 700; }
    .pl-attr-tag-sep { opacity: .5; font-size: .58rem; margin: 0 .04rem; }
    .pl-attr-tag-rm { display: inline-flex; align-items: center; justify-content: center; width: 14px; height: 14px; border-radius: 50%; background: rgba(255,255,255,.22); border: none; color: #fff; cursor: pointer; font-size: .6rem; padding: 0; flex-shrink: 0; line-height: 1; transition: background .1s; }
    .pl-attr-tag-rm:hover { background: rgba(255,255,255,.44); }
    .pl-act-btn { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); font-size: .7rem; text-decoration: none; cursor: pointer; transition: all .12s; }
    .pl-act-btn:hover, .pl-act-btn:focus { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
    .pl-act-btn + .pl-act-btn { margin-left: 3px; }
    .pl-more-menu { display: none; position: fixed; z-index: 1055; min-width: 148px; list-style: none; margin: 0; padding: .25rem; background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: .45rem; box-shadow: 0 8px 24px rgba(15,23,42,.12), 0 2px 6px rgba(15,23,42,.06); }
    .pl-more-menu.is-open { display: block; }
    .pl-more-menu li { list-style: none; }
    .pl-menu-item { display: flex; align-items: center; gap: .4rem; width: 100%; padding: .38rem .65rem; border-radius: .3rem; font-size: .78rem; font-weight: 500; color: #374151; background: none; border: none; cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .pl-menu-item:hover { background: var(--cl-surface-alt); color: #111; }
    .pl-menu-item.danger { color: #dc2626; }
    .pl-menu-item.danger:hover { background: #fee2e2; }
    .pl-menu-divider { height: 1px; background: var(--cl-border); margin: .2rem .4rem; }
    body.theme-dark .pl-more-menu {
        background: #0f1a2b;
        border-color: #334155;
        box-shadow: 0 14px 30px rgba(2, 6, 23, .55), 0 2px 8px rgba(2, 6, 23, .35);
    }
    body.theme-dark .pl-menu-item {
        color: #dbe7f7;
    }
    body.theme-dark .pl-menu-item i {
        color: #9fb2cc;
    }
    body.theme-dark .pl-menu-item:hover,
    body.theme-dark .pl-menu-item:focus {
        background: #1e3354;
        color: #ffffff;
    }
    body.theme-dark .pl-menu-item:hover i,
    body.theme-dark .pl-menu-item:focus i {
        color: #cfe0f7;
    }
    body.theme-dark .pl-menu-divider {
        background: #334155;
    }
    body.theme-dark .pl-menu-item.danger {
        color: #f87171;
    }
    body.theme-dark .pl-menu-item.danger i {
        color: #f87171;
    }
    body.theme-dark .pl-menu-item.danger:hover,
    body.theme-dark .pl-menu-item.danger:focus {
        background: rgba(239, 68, 68, .16);
        color: #fecaca;
    }
    body.theme-dark .pl-menu-item.danger:hover i,
    body.theme-dark .pl-menu-item.danger:focus i {
        color: #fecaca;
    }
    .pl-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .3rem; padding: .35rem .75rem; border-top: 1px solid var(--cl-border); font-size: .72rem; color: var(--cl-text-muted); }
    .pl-footer .pagination { margin: 0; gap: 2px; }
    .pl-footer .page-link { padding: .15rem .42rem; font-size: .72rem; min-width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; }
</style>

<div class="pl-wrap container-fluid">
<div class="pl-card">

    <!-- Card Header -->
    <div class="pl-card-header">
        <div>
            <div class="pl-card-header-title"><i class="bi bi-box me-1"></i>Products</div>
            <div class="pl-card-header-sub">Inventory items and components</div>
        </div>
        <?php if ($can_create): ?>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <button type="button" class="pl-btn" id="syncServicesBtn" title="Create service products for any shipping carriers missing one" style="opacity:.75">
                    <i class="bi bi-arrow-repeat"></i> Sync Services
                </button>
                <a href="<?= base_url('/products/create') ?>" class="pl-btn pl-btn-primary"><i class="bi bi-plus-circle"></i> New Product</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filter Bar -->
    <?= form_open('', ['method' => 'GET', 'class' => 'pl-filter-bar']) ?>
        <div class="pl-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" name="search" value="<?= esc($current_search) ?>" placeholder="Name, SKUâ€¦">
        </div>
        <select class="form-select pl-cat" name="category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $categoryId => $categoryName): ?>
                <option value="<?= esc($categoryId) ?>" <?= $current_category == $categoryId ? 'selected' : '' ?>><?= esc($categoryName) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select pl-status" name="status">
            <option value="">All Status</option>
            <option value="1" <?= ($current_status ?? '') === '1' ? 'selected' : '' ?>>Active</option>
            <option value="0" <?= ($current_status ?? '') === '0' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <select class="form-select pl-type" name="type">
            <option value="">All Types</option>
            <option value="storable"  <?= ($current_type ?? '') === 'storable'   ? 'selected' : '' ?>>Storable</option>
            <option value="consumable" <?= ($current_type ?? '') === 'consumable' ? 'selected' : '' ?>>Consumable</option>
            <option value="service"   <?= ($current_type ?? '') === 'service'    ? 'selected' : '' ?>>Service</option>
        </select>
        
        <!-- Multi-Attribute Filter UI -->
        <div style="display:flex; align-items:center; gap:.3rem; flex-wrap:wrap">
            <select class="form-select pl-attr-name" id="plAttrNameSel" style="width:170px">
                <option value="">Select Attribute</option>
                <?php foreach ($attributeOptions as $attrName => $attrValues): ?>
                    <option value="<?= esc($attrName) ?>"><?= esc($attrName) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" class="form-control" id="plAttrValueInp" list="plAttrValueList" placeholder="Attribute value" style="width:180px">
            <datalist id="plAttrValueList"></datalist>
            <button type="button" class="pl-btn" id="plAttrAddBtn" title="Add attribute filter" onclick="addAttributeFilter(event)">
                <i class="bi bi-plus"></i> Add Filter
            </button>
        </div>
        
        <!-- Display selected attributes as tags/pills -->
        <div id="plAttrTagsContainer" style="display:flex; flex-wrap:wrap; gap:.3rem; align-items:center; margin-top:.3rem; min-height:26px"></div>
        
        <!-- Hidden container for actual form inputs (submitted with form) -->
        <div id="plAttrHiddenInputs" style="display:none"></div>
        
        <select class="form-select pl-pp" name="per_page">
            <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
        </select>
        <button type="submit" class="pl-btn"><i class="bi bi-search"></i></button>
        <a href="<?= base_url('/products') ?>" class="pl-btn" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
        <button type="button" class="pl-btn" onclick="exportToCSV()" title="Export CSV"><i class="bi bi-download"></i> CSV</button>
        <?php if ($can_edit): ?>
        <div class="ms-auto d-flex gap-1">
            <button type="button" class="pl-btn" onclick="bulkAction('activate')" title="Activate selected"><i class="bi bi-check-circle"></i></button>
            <button type="button" class="pl-btn" onclick="bulkAction('deactivate')" title="Deactivate selected"><i class="bi bi-x-circle"></i></button>
        </div>
        <?php endif; ?>
        <div class="pl-filter-hint">
            Pro tip: Search supports product code, variant code (art number), variant name, and attribute values. Use Attribute + Value for exact deep filtering.
        </div>
    <?= form_close() ?>

    <!-- Variant filter mode notice bar -->
    <?php if (!empty($current_attributes)): ?>
    <div class="pl-filter-mode-bar">
        <i class="bi bi-funnel-fill" style="font-size:.72rem;color:#2563eb;flex-shrink:0"></i>
        <span class="pl-fmode-label">Variant Filter</span>
        <div class="d-flex align-items-center gap-1 flex-wrap">
            <?php foreach ($current_attributes as $fa): ?>
                <span class="pl-fmode-pill">
                    <span style="font-size:.61rem;opacity:.75"><?= esc($fa['name']) ?></span>
                    <span style="opacity:.35;font-size:.58rem">•</span>
                    <strong><?= esc($fa['value']) ?></strong>
                </span>
            <?php endforeach; ?>
        </div>
        <?php if (!empty($matchedVariantsByProduct)):
            $__tv = array_sum(array_map('count', $matchedVariantsByProduct));
            $__tp = count($matchedVariantsByProduct);
        ?>
        <span class="pl-fmode-count"><strong><?= $__tv ?></strong> variant<?= $__tv!==1?'s':''?> across <strong><?= $__tp ?></strong> product<?= $__tp!==1?'s':''?></span>
        <?php elseif (empty($products['data'])): ?>
        <span class="pl-fmode-count" style="color:#ef4444">No matching variants found</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Table -->
    <div class="table-responsive">
    <?php if (!empty($products['data'])): ?>
        <table class="table table-hover pl-table mb-0">
            <thead>
                <tr>
                    <th width="28"><input type="checkbox" class="form-check-input" id="selectAll"></th>
                    <th width="32" class="text-center" style="color:var(--cl-text-muted)">#</th>
                    <th>Code</th>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Unit</th>
                    <th class="text-end">Stock</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($current_attributes) && !empty($matchedVariantsByProduct)): ?>
                <?php
                    $productInfoMap = [];
                    foreach ($products['data'] as $p) { $productInfoMap[(int)$p['id']] = $p; }
                    // Build lookup of active filter attr→value for badge highlighting
                    $activeAttrLower = [];
                    foreach ($current_attributes as $fa) {
                        $activeAttrLower[mb_strtolower(trim((string)($fa['name'] ?? '')))] = mb_strtolower(trim((string)($fa['value'] ?? '')));
                    }
                ?>
                <?php foreach ($matchedVariantsByProduct as $pid => $variantRows):
                    $prod = $productInfoMap[$pid] ?? null;
                    if (!$prod || empty($variantRows)) continue;
                    $pImgs       = !empty($prod['images']) ? json_decode($prod['images'], true) : [];
                    $pThumb      = !empty($pImgs) ? $pImgs[0] : null;
                    $pIdent      = entityRouteIdentifier($prod);
                    $vMatchCount = count($variantRows);
                    $vTotalCount = (int)($prod['variant_count'] ?? 0);
                    $vStock      = (float)($prod['variant_stock'] ?? 0);
                    $vStockCls   = $vStock > 0 ? 'text-success' : ($vStock < 0 ? 'text-danger' : 'text-muted');
                ?>
                <tr class="pl-group-hdr">
                    <td colspan="10">
                        <div class="pl-ghdr-inner">
                            <?php if ($pThumb): ?>
                                <img src="<?= base_url('uploads/products/' . $pThumb) ?>" alt="" class="pl-ghdr-thumb">
                            <?php else: ?>
                                <span class="pl-ghdr-thumb-empty"><i class="bi bi-layers"></i></span>
                            <?php endif; ?>
                            <div style="flex:1;min-width:0">
                                <div class="d-flex align-items-center gap-1 flex-wrap">
                                    <a href="<?= base_url('/products/' . $pIdent) ?>" class="pl-ghdr-name"><?= esc($prod['name']) ?></a>
                                    <span class="badge bg-primary" style="font-size:.5rem;letter-spacing:.03em;vertical-align:middle">Template</span>
                                    <?php if (!empty($prod['category_name'])): ?>
                                        <span class="pl-ghdr-cat"><?= esc($prod['category_name']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="pl-ghdr-vmeta">
                                    <span class="pl-ghdr-vmatch"><?= $vMatchCount ?> matching variant<?= $vMatchCount!==1?'s':''?></span>
                                    <?php if ($vTotalCount > $vMatchCount): ?>
                                        <span class="pl-ghdr-sep">/</span><span><?= $vTotalCount ?> total</span>
                                    <?php endif; ?>
                                    <span class="pl-ghdr-sep">&bull;</span>
                                    <span>Stock: <strong class="<?= $vStockCls ?>"><?= number_format($vStock, 0) ?></strong></span>
                                </div>
                            </div>
                            <a href="<?= base_url('product-variants?product_id=' . $pid) ?>" class="pl-btn pl-ghdr-vbtn">
                                <i class="bi bi-grid-3x3-gap"></i> All Variants
                            </a>
                        </div>
                    </td>
                </tr>
                <?php foreach ($variantRows as $mv): ?>
                <tr class="pl-variant-row">
                    <td class="pl-vr-indent"></td>
                    <td></td>
                    <td style="white-space:nowrap"><code class="pl-vr-art"><?= esc($mv['art_number'] ?: '—') ?></code></td>
                    <td>
                        <?php
                            $vImg = trim((string)($mv['image'] ?? ''));
                            $vImgUrl = $vImg !== '' ? base_url('/uploads/variants/' . ltrim($vImg, '/')) : '';
                            if ($vImgUrl === '' && !empty($pThumb)) {
                                $vImgUrl = base_url('uploads/products/' . $pThumb);
                            }
                        ?>
                        <div class="pl-vr-main">
                            <?php if ($vImgUrl !== ''): ?>
                                <img src="<?= esc($vImgUrl) ?>" alt="" class="pl-vr-thumb"
                                     data-preview="<?= esc($vImgUrl) ?>"
                                     onclick="showImageModal('<?= esc($vImgUrl) ?>','<?= esc(addslashes($prod['name'] . ' - ' . ($mv['art_number'] ?: 'Variant'))) ?>')">
                            <?php else: ?>
                                <span class="pl-vr-thumb-empty"><i class="bi bi-image"></i></span>
                            <?php endif; ?>
                            <div style="min-width:0;flex:1">
                                <?php if (!empty($mv['attributes']) && is_array($mv['attributes'])): ?>
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php foreach ($mv['attributes'] as $ak => $av):
                                            $akL = mb_strtolower((string)$ak);
                                            $avL = mb_strtolower((string)$av);
                                            $isHit = isset($activeAttrLower[$akL]) && $activeAttrLower[$akL] === $avL;
                                            $bCls = $isHit ? 'pl-attr-badge pl-ab-active' : 'pl-attr-badge pl-ab-other';
                                        ?><span class="<?= $bCls ?>"><span class="pl-ab-k"><?= esc($ak) ?>:</span><strong><?= esc($av) ?></strong></span><?php endforeach; ?>
                                    </div>
                                <?php elseif (!empty($mv['name'])): ?>
                                    <span style="font-size:.72rem;color:var(--cl-text-secondary)"><?= esc($mv['name']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.72rem">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:.71rem;color:var(--cl-text-muted)"><?= esc($prod['category_name'] ?? '—') ?></td>
                    <td style="font-size:.71rem;color:var(--cl-text-muted)"><?= esc($prod['unit'] ?? '—') ?></td>
                    <td class="text-end text-muted" style="font-size:.71rem">&mdash;</td>
                    <td><?php if ($prod['is_active']): ?><span class="badge bg-success" style="font-size:.6rem">Active</span><?php else: ?><span class="badge bg-secondary" style="font-size:.6rem">Inactive</span><?php endif; ?></td>
                    <td></td>
                    <td class="text-end" style="white-space:nowrap">
                        <a href="<?= base_url('/products/' . $pIdent) ?>" class="pl-act-btn" title="View product"><i class="bi bi-eye"></i></a>
                        <?php if ($can_edit): ?><a href="<?= base_url('product-variants/' . $mv['id'] . '/edit') ?>" class="pl-act-btn" title="Edit variant"><i class="bi bi-pencil"></i></a><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endforeach; ?>

            <?php else: ?>
                <?php $rowNum = ($currentPage - 1) * $currentPerPage; foreach ($products['data'] as $product):
                    $rowNum++;
                    $productIdentifier = entityRouteIdentifier($product);
                    $images   = !empty($product['images']) ? json_decode($product['images'], true) : [];
                    $firstImg = !empty($images) ? $images[0] : null;
                    $isVar    = ($product['product_type'] ?? '') === 'variable';
                    $desc55   = !empty($product['description']) ? substr($product['description'], 0, 55) . (strlen($product['description']) > 55 ? '…' : '') : '';
                ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input product-checkbox" value="<?= $product['id'] ?>"></td>
                    <td class="text-center" style="font-size:.68rem;color:var(--cl-text-muted);font-variant-numeric:tabular-nums"><?= $rowNum ?></td>
                    <td><code style="font-size:.7rem"><?= esc($product['code']) ?></code></td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <?php if ($firstImg): ?>
                                <img src="<?= base_url('uploads/products/' . $firstImg) ?>" alt="" class="pl-thumb"
                                     data-preview="<?= base_url('uploads/products/' . $firstImg) ?>"
                                     onclick="showImageModal('<?= base_url('uploads/products/' . $firstImg) ?>','<?= esc(addslashes($product['name'])) ?>')"
                                     style="cursor:pointer">
                            <?php else: ?>
                                <span class="pl-thumb-empty"><i class="bi bi-image"></i></span>
                            <?php endif; ?>
                            <div>
                                <a href="<?= base_url('/products/' . $productIdentifier) ?>" class="pl-name text-decoration-none">
                                    <?= esc($product['name']) ?>
                                </a>
                                <?php if ($isVar): ?><span class="badge bg-primary ms-1" style="font-size:.55rem">Template</span><?php endif; ?>
                                <?php if (($product['detailed_type'] ?? '') === 'service'): ?><span class="badge ms-1" style="font-size:.55rem;background:#0891b2">Service</span><?php endif; ?>
                                <?php if (($product['detailed_type'] ?? '') === 'consumable'): ?><span class="badge bg-warning text-dark ms-1" style="font-size:.55rem">Consumable</span><?php endif; ?>
                                <?php if ($desc55): ?><div class="pl-desc"><?= esc($desc55) ?></div><?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td><?= esc($product['category_name'] ?? '—') ?></td>
                    <td><?= esc($product['unit']) ?></td>
                    <td class="text-end" style="white-space:nowrap">
                        <?php
                            $isTemplate = ($product['product_type'] ?? '') === 'variable';
                            if ($isTemplate):
                                $vStock  = (float)($product['variant_stock'] ?? 0);
                                $vCount  = (int)($product['variant_count'] ?? 0);
                                $stockCls = $vStock > 0 ? 'text-success' : ($vStock < 0 ? 'text-danger' : 'text-muted');
                        ?>
                            <a href="<?= base_url('product-variants?product_id=' . $product['id']) ?>"
                               title="Click to view <?= $vCount ?> variant(s)"
                               class="<?= $stockCls ?>" style="font-size:.73rem;font-variant-numeric:tabular-nums;text-decoration:none">
                                <?= number_format($vStock, 2) ?>
                                <span style="font-size:.6rem;opacity:.65">(<?= $vCount ?>v)</span>
                                <i class="bi bi-box-arrow-up-right" style="font-size:.58rem"></i>
                            </a>
                        <?php else:
                            $sStock   = (float)($product['simple_stock'] ?? 0);
                            $stockCls = $sStock > 0 ? 'text-success' : ($sStock < 0 ? 'text-danger' : 'text-muted');
                        ?>
                            <span class="<?= $stockCls ?>" style="font-size:.73rem;font-variant-numeric:tabular-nums">
                                <?= number_format($sStock, 2) ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($product['is_active']): ?>
                            <span class="badge bg-success" style="font-size:.62rem">Active</span>
                        <?php else: ?>
                            <span class="badge bg-secondary" style="font-size:.62rem">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td><span style="font-size:.71rem;color:var(--cl-text-muted)"><?= date('M j, Y', strtotime($product['created_at'])) ?></span></td>
                    <td class="text-end" style="white-space:nowrap">
                        <a href="<?= base_url('/products/' . $productIdentifier) ?>" class="pl-act-btn" title="View"><i class="bi bi-eye"></i></a>
                        <button type="button" class="pl-act-btn pl-more-trigger"
                                data-pid="<?= $product['id'] ?>"
                                data-active="<?= (int)$product['is_active'] ?>"
                                data-name="<?= esc($product['name']) ?>"
                                data-isvariable="<?= ($product['product_type'] ?? '') === 'variable' ? '1' : '0' ?>"
                                title="More"><i class="bi bi-three-dots-vertical"></i></button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-inbox fs-1 text-muted"></i>
            <h6 class="mt-3 text-muted">No Products Found</h6>
            <p class="text-muted small">No products match your current filters.</p>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/products/create') ?>" class="pl-btn pl-btn-primary">
                    <i class="bi bi-plus-circle"></i> Create First Product
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    </div><!-- /.table-responsive -->

    <!-- Footer: count + pagination -->
    <?php if (!empty($products['data'])): ?>
    <div class="pl-footer">
        <div>
            <?php if (!empty($current_attributes) && !empty($matchedVariantsByProduct)):
                $__totalVars = array_sum(array_map('count', $matchedVariantsByProduct));
                $__totalProds = count($matchedVariantsByProduct);
            ?>
                Showing <strong class="mx-1"><?= $__totalVars ?></strong> matching variant<?= $__totalVars !== 1 ? 's' : '' ?> across <strong class="mx-1"><?= $__totalProds ?></strong> product<?= $__totalProds !== 1 ? 's' : '' ?>
            <?php elseif ($totalRecords > 0): ?>
                Showing <strong class="mx-1"><?= $startItem ?></strong>&ndash;<strong class="mx-1"><?= $endItem ?></strong> of <strong class="mx-1"><?= number_format($totalRecords) ?></strong> products
            <?php else: ?>No results<?php endif; ?>
        </div>
        <?php if ($totalPages > 1): ?>
        <nav aria-label="Products pagination">
            <ul class="pagination pagination-sm">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $pgBase . ($currentPage - 1) ?>"><i class="bi bi-chevron-left" style="font-size:.65rem"></i></a>
                </li>
                <?php
                $win = 2; $ps = max(1, $currentPage - $win); $pe = min($totalPages, $currentPage + $win);
                if ($ps > 1): ?><li class="page-item"><a class="page-link" href="<?= $pgBase . 1 ?>">1</a></li><?php
                    if ($ps > 2): ?><li class="page-item disabled"><span class="page-link">â€¦</span></li><?php endif;
                endif;
                for ($p = $ps; $p <= $pe; $p++): ?>
                    <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>"><a class="page-link" href="<?= $pgBase . $p ?>"><?= $p ?></a></li>
                <?php endfor;
                if ($pe < $totalPages):
                    if ($pe < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">â€¦</span></li><?php endif;
                    ?><li class="page-item"><a class="page-link" href="<?= $pgBase . $totalPages ?>"><?= $totalPages ?></a></li><?php
                endif; ?>
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $pgBase . ($currentPage + 1) ?>"><i class="bi bi-chevron-right" style="font-size:.65rem"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div><!-- /.pl-card -->
</div><!-- /.pl-wrap -->

<!-- â‹® More-actions dropdown (shared, positioned by JS) -->
<ul class="pl-more-menu" id="plMoreMenu">
    <li><a href="#" class="pl-menu-item" id="plMenuView"><i class="bi bi-eye"></i> View</a></li>
    <?php if ($can_edit): ?>
    <li><a href="#" class="pl-menu-item" id="plMenuEdit"><i class="bi bi-pencil"></i> Edit</a></li>
    <?php endif; ?>
    <li><a href="#" class="pl-menu-item" id="plMenuProcesses"><i class="bi bi-gear"></i> Processes</a></li>
    <li><button type="button" class="pl-menu-item" id="plMenuAdjust"><i class="bi bi-clipboard2-plus text-info"></i> Adjust Stock</button></li>
    <li><div class="pl-menu-divider"></div></li>
    <li><button type="button" class="pl-menu-item" id="plMenuToggle"><i class="bi bi-arrow-repeat"></i> <span id="plMenuToggleLabel">Toggle Status</span></button></li>
    <?php if ($can_delete): ?>
    <li><div class="pl-menu-divider"></div></li>
    <li><button type="button" class="pl-menu-item danger" id="plMenuDelete"><i class="bi bi-trash"></i> Delete</button></li>
    <?php endif; ?>
</ul>

<!-- Image hover preview -->
<div id="plImgPreview"><img src="" alt="" id="plImgPreviewImg"></div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="imageModalTitle">Product Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imageModalImage" src="" alt="" class="img-fluid">
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>


<?= $this->section('js') ?>
<script>
// ── Multi-Attribute Filter Handler ─────────────────────────────────
(function() {
    const attrMap = <?= json_encode($attributeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const attrNameSel = document.getElementById('plAttrNameSel');
    const attrValueInp = document.getElementById('plAttrValueInp');
    const attrValueList = document.getElementById('plAttrValueList');
    const tagsContainer = document.getElementById('plAttrTagsContainer');
    const hiddenInputsDiv = document.getElementById('plAttrHiddenInputs');
    const attrAddBtn = document.getElementById('plAttrAddBtn');
    
    let currentAttributes = [];
    
    // Parse existing attributes from current_attributes PHP variable if available
    <?php if (!empty($current_attributes) && is_array($current_attributes)): ?>
        currentAttributes = <?= json_encode($current_attributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        renderTags();
    <?php endif; ?>
    
    function updateValueSuggestions() {
        if (!attrNameSel || !attrValueList) return;
        const selected = attrNameSel.value || '';
        const values = selected && attrMap[selected] ? attrMap[selected] : [];
        attrValueList.innerHTML = '';
        values.forEach(function(v) {
            const opt = document.createElement('option');
            opt.value = v;
            attrValueList.appendChild(opt);
        });
    }
    
    function renderTags() {
        if (!tagsContainer || !hiddenInputsDiv) return;
        tagsContainer.innerHTML = '';
        hiddenInputsDiv.innerHTML = '';
        
        currentAttributes.forEach(function(attr, idx) {
            const tag = document.createElement('span');
            tag.className = 'pl-attr-tag';
            tag.innerHTML = '<span style="opacity:.75;font-size:.62rem">' + esc(attr.name) + '</span>'
                + '<span class="pl-attr-tag-sep">:</span>'
                + '<span class="pl-attr-tag-val">' + esc(attr.value) + '</span>'
                + '<button type="button" class="pl-attr-tag-rm" data-idx="' + idx + '" aria-label="Remove">&times;</button>';
            tagsContainer.appendChild(tag);
            
            tag.querySelector('.pl-attr-tag-rm').addEventListener('click', function(e) {
                e.preventDefault();
                currentAttributes.splice(parseInt(this.dataset.idx, 10), 1);
                renderTags();
            });
            
            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'attr[' + idx + '][name]';
            nameInput.value = attr.name;
            hiddenInputsDiv.appendChild(nameInput);
            
            const valueInput = document.createElement('input');
            valueInput.type = 'hidden';
            valueInput.name = 'attr[' + idx + '][value]';
            valueInput.value = attr.value;
            hiddenInputsDiv.appendChild(valueInput);
        });
    }
    
    function addAttribute() {
        const name = attrNameSel ? attrNameSel.value : '';
        const value = attrValueInp ? attrValueInp.value : '';
        
        if (!name || !value) {
            alert('Please select an attribute and enter a value');
            return;
        }
        
        // Check for duplicates
        const isDuplicate = currentAttributes.some(a => a.name === name && a.value === value);
        if (isDuplicate) {
            alert('This attribute filter already exists');
            return;
        }
        
        currentAttributes.push({ name, value });
        renderTags();
        
        // Reset inputs
        if (attrNameSel) attrNameSel.value = '';
        if (attrValueInp) attrValueInp.value = '';
        updateValueSuggestions();
    }
    
    // Expose function globally for HTML onclick
    window.addAttributeFilter = function(e) {
        e.preventDefault();
        addAttribute();
    };
    
    // Handle attribute name change
    if (attrNameSel) {
        attrNameSel.addEventListener('change', updateValueSuggestions);
    }
    
    // Handle Enter key on value input
    if (attrValueInp) {
        attrValueInp.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                addAttribute();
            }
        });
    }
    
    updateValueSuggestions();
})();

// Helper function to escape strings (basic HTML escaping)
function esc(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// Select all functionality
const selectAll = document.getElementById('selectAll');
if (selectAll) {
    selectAll.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.product-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
}

// Image modal
function showImageModal(imageSrc, title) {
    const imgEl = document.getElementById('imageModalImage');
    const titleEl = document.getElementById('imageModalTitle');
    const modalEl = document.getElementById('imageModal');
    if (!imgEl || !titleEl || !modalEl || typeof bootstrap === 'undefined') return;
    imgEl.src = imageSrc;
    titleEl.textContent = title;
    new bootstrap.Modal(modalEl).show();
}

// Export to CSV
function exportToCSV() {
    window.location.href = '<?= base_url('/products/export-csv') ?>';
}

// Toggle product status
function toggleStatus(productId) {
    if (confirm('Are you sure you want to change the status of this product?')) {
        fetch(`<?= base_url('/products/') ?>${productId}/toggle-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to change status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Delete product
function deleteProduct(productId) {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        fetch(`<?= base_url('/products/') ?>${productId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete product');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Bulk actions
function bulkAction(action) {
    const selectedIds = Array.from(document.querySelectorAll('.product-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one product');
        return;
    }

    const actionText = action === 'activate' ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${actionText} ${selectedIds.length} product(s)?`)) {
        fetch('<?= base_url('/products/bulk-update') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash
            },
            body: JSON.stringify({
                operation: action,
                product_ids: selectedIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Bulk operation failed');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// ── Image hover preview ─────────────────────────────────────────────
(function () {
    const preview = document.getElementById('plImgPreview');
    const previewImg = document.getElementById('plImgPreviewImg');
    if (!preview || !previewImg) return;
    let hideTimer = null;

    function position(e) {
        const vw = window.innerWidth, vh = window.innerHeight;
        const pw = 190, ph = 190; // approx panel size
        let left = e.clientX + 14;
        let top  = e.clientY + 14;
        if (left + pw > vw - 8) left = e.clientX - pw - 10;
        if (top  + ph > vh - 8) top  = e.clientY - ph - 10;
        preview.style.left = left + 'px';
        preview.style.top  = top  + 'px';
    }

    document.addEventListener('mouseover', function (e) {
        const thumb = e.target.closest('[data-preview]');
        if (!thumb) return;
        clearTimeout(hideTimer);
        previewImg.src = thumb.dataset.preview;
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

// ── More-actions dropdown ─────────────────────────────────────────────
(function () {
    const menu   = document.getElementById('plMoreMenu');
    if (!menu) return;

    const BASE   = '<?= base_url('/products/') ?>';
    let activePid = null, activeActive = 0, activeName = '', activeIsVar = false;

    function openMenu(btn) {
        const pid    = btn.dataset.pid;
        const active = parseInt(btn.dataset.active, 10);
        activePid    = pid;
        activeActive = active;
        activeName   = btn.dataset.name       || '';
        activeIsVar  = btn.dataset.isvariable === '1';

        const menuView      = document.getElementById('plMenuView');
        const menuEdit      = document.getElementById('plMenuEdit');
        const menuProcesses = document.getElementById('plMenuProcesses');
        const menuToggle    = document.getElementById('plMenuToggle');
        const menuToggleLbl = document.getElementById('plMenuToggleLabel');
        const menuDelete    = document.getElementById('plMenuDelete');

        if (menuView)      menuView.href      = BASE + pid;
        if (menuEdit)      menuEdit.href      = BASE + pid + '/edit';
        if (menuProcesses) menuProcesses.href = BASE + pid + '/processes';
        if (menuToggleLbl) menuToggleLbl.textContent = active ? 'Deactivate' : 'Activate';

        const rect = btn.getBoundingClientRect();
        menu.style.top   = (rect.bottom + 4 + window.scrollY) + 'px';
        menu.style.left  = 'auto';
        menu.style.right = Math.max(4, window.innerWidth - rect.right) + 'px';
        menu.classList.add('is-open');
    }

    document.addEventListener('click', function (e) {
        const trigger = e.target.closest('.pl-more-trigger');
        if (trigger) { e.stopPropagation(); openMenu(trigger); return; }
        if (!menu.contains(e.target)) menu.classList.remove('is-open');
    });

    const menuToggle = document.getElementById('plMenuToggle');
    if (menuToggle) menuToggle.addEventListener('click', function () {
        menu.classList.remove('is-open');
        if (activePid) toggleStatus(activePid);
    });

    const menuDelete = document.getElementById('plMenuDelete');
    if (menuDelete) menuDelete.addEventListener('click', function () {
        menu.classList.remove('is-open');
        if (activePid) deleteProduct(activePid);
    });

    const menuAdjust = document.getElementById('plMenuAdjust');
    if (menuAdjust) menuAdjust.addEventListener('click', function () {
        menu.classList.remove('is-open');
        if (activePid) window.qadjOpen(activePid, null, activeName, activeIsVar);
    });

    document.querySelectorAll('#plMenuView, #plMenuEdit, #plMenuProcesses').forEach(el => {
        el && el.addEventListener('click', () => menu.classList.remove('is-open'));
    });
})();

// ── Sync Services ────────────────────────────────────────────────────
(function(){
    const btn = document.getElementById('syncServicesBtn');
    if (!btn) return;
    btn.addEventListener('click', async function() {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing…';
        try {
            const res = await fetch('<?= base_url('products/backfill-services') ?>', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json' },
                body: '{}'
            });
            const data = await res.json();
            if (data.success) {
                if (data.created > 0) {
                    alert('Done! ' + data.message + ' Reload to see them.');
                    location.reload();
                } else {
                    alert('All shipping services already have service products.');
                }
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        } catch(e) {
            alert('Request failed: ' + e.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Sync Services';
        }
    });
})();
</script>
<?= $this->include('inventory/_quickadjust_modal') ?>
<?= $this->endSection() ?>
