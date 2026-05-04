<?php
// Partial or full list of variants. Expects $variants array
?>
<?php if (empty($variants)): ?>
    <div class="alert alert-info">No variants found.</div>
<?php else: ?>
    <style>
        .variants-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: .4rem;
            margin-bottom: .5rem;
        }
        .variants-count {
            font-size: .76rem;
            color: var(--cl-text-muted);
        }
        .variants-count strong {
            color: var(--cl-primary);
        }
        .variants-controls {
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: nowrap;
        }
        .variants-controls .form-select {
            width: 70px;
            font-size: .73rem;
            padding: .15rem 1.5rem .15rem .35rem;
            height: 26px;
        }
        .variants-controls .form-label {
            font-size: .72rem;
            color: var(--cl-text-muted);
            margin-bottom: 0;
            white-space: nowrap;
        }
        .variants-pagination .pagination {
            margin: 0;
            gap: 2px;
        }
        .variants-pagination .page-link {
            padding: .15rem .42rem;
            font-size: .72rem;
            min-width: 26px;
            height: 26px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .variants-table-wrap {
            border: 1px solid var(--cl-border);
            border-radius: 6px;
            overflow: hidden;
            box-shadow: var(--cl-shadow-xs);
        }
        .variants-table {
            margin-bottom: 0;
            font-size: .76rem;
        }
        .variants-table thead th {
            font-size: .62rem;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--cl-text-muted);
            background: var(--cl-surface-alt);
            border-bottom: 1px solid var(--cl-border);
            padding: .26rem .45rem;
            white-space: nowrap;
            font-weight: 700;
        }
        /* Dark mode: make column headers readable */
        body.theme-dark .variants-table thead th {
            color: #94a3b8 !important;
            background: #162033 !important;
            border-bottom-color: #334155 !important;
        }
        body.theme-dark .variants-table tbody tr:nth-child(even) td {
            background: #0f1a2b !important;
        }
        body.theme-dark .variants-table tbody tr:nth-child(odd) td {
            background: #1a2740 !important;
        }
        body.theme-dark .variants-table tbody tr:hover td {
            background: rgba(37,99,235,.1) !important;
        }
        .variants-table tbody td {
            padding: .18rem .45rem;
            border-bottom: 1px solid var(--cl-border-light);
            vertical-align: middle;
            font-size: .76rem;
        }
        .variants-table tbody tr:last-child td {
            border-bottom: none;
        }
        .variant-thumb {
            width: 24px;
            height: 24px;
            border-radius: 3px;
            border: 1px solid var(--cl-border);
            background: var(--cl-surface-alt);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: var(--cl-text-muted);
            font-size: .68rem;
        }
        .variant-thumb img {
            width: 24px;
            height: 24px;
            object-fit: cover;
            border-radius: 3px;
            cursor: zoom-in;
        }
        /* Image hover preview */
        #varImgPreview { display:none; position:fixed; z-index:9100; pointer-events:none; padding:5px; background:var(--cl-surface); border:1px solid var(--cl-border); border-radius:8px; box-shadow:0 12px 32px rgba(15,23,42,.22),0 2px 8px rgba(15,23,42,.10); opacity:0; transition:opacity .1s; }
        #varImgPreview.is-visible { display:block; opacity:1; }
        #varImgPreview img { display:block; max-width:220px; max-height:220px; width:auto; height:auto; object-fit:contain; border-radius:5px; }
        body.theme-dark #varImgPreview { background:#1e293b; border-color:#334155; box-shadow:0 12px 32px rgba(0,0,0,.45),0 2px 8px rgba(0,0,0,.25); }
        .variant-name {
            font-size: .76rem;
            font-weight: 600;
            line-height: 1.1;
        }
        .variant-attrs {
            font-size: .64rem;
            color: var(--cl-text-muted);
            margin-top: .05rem;
            line-height: 1.15;
        }
        .money-cell {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-size: .76rem;
        }
        .qty-cell {
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
            font-size: .76rem;
        }
        .currency-pill {
            display: inline-block;
            margin-left: .25rem;
            font-size: .57rem;
            border: 1px solid var(--cl-border);
            border-radius: 999px;
            padding: .05rem .28rem;
            color: var(--cl-text-muted);
            vertical-align: middle;
        }
        .actions-col { text-align: right; white-space: nowrap; }
        .var-act-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 22px;
            height: 22px;
            border-radius: 4px;
            border: 1px solid var(--cl-border);
            background: var(--cl-surface);
            color: var(--cl-text-secondary);
            font-size: .7rem;
            text-decoration: none;
            cursor: pointer;
            transition: all .12s;
        }
        .var-act-btn:hover {
            border-color: var(--cl-primary);
            color: var(--cl-primary);
            background: var(--cl-primary-50);
        }
        .var-act-btn + .var-act-btn { margin-left: 3px; }
        .var-more-menu {
            display: none;
            position: fixed;
            z-index: 9000;
            min-width: 170px;
            list-style: none;
            margin: 0;
            padding: .25rem;
            background: var(--cl-surface, #fff);
            border: 1px solid var(--cl-border, #e5e7eb);
            border-radius: .45rem;
            box-shadow: 0 8px 24px rgba(15,23,42,.18), 0 2px 6px rgba(15,23,42,.10);
        }
        .var-more-menu.is-open { display: block; }
        .var-more-menu li { list-style: none; }
        .var-more-menu .var-menu-item {
            display: flex;
            align-items: center;
            gap: .4rem;
            width: 100%;
            padding: .38rem .65rem;
            border-radius: .3rem;
            font-size: .78rem;
            font-weight: 500;
            color: var(--cl-text-secondary, #4b5563);
            background: none;
            border: none;
            cursor: pointer;
            text-decoration: none !important;
            white-space: nowrap;
        }
        .var-more-menu .var-menu-item:hover {
            background: var(--cl-surface-alt, #f3f4f6);
            color: var(--cl-text-primary, #111827);
        }
        .var-more-menu .var-menu-item.danger { color: #dc2626; }
        .var-more-menu .var-menu-item.danger:hover { background: rgba(220,38,38,.12); color: #dc2626; }
        .var-more-menu .var-menu-divider { height: 1px; background: var(--cl-border, #e5e7eb); margin: .2rem .4rem; }
        /* Dark mode overrides for 3-dot menu */
        body.theme-dark .var-more-menu {
            background: #1e293b !important;
            border-color: #334155 !important;
            box-shadow: 0 8px 24px rgba(0,0,0,.4), 0 2px 6px rgba(0,0,0,.2) !important;
        }
        body.theme-dark .var-more-menu .var-menu-item {
            color: #e2e8f0 !important;
        }
        body.theme-dark .var-more-menu .var-menu-item:hover {
            background: #334155 !important;
            color: #f1f5f9 !important;
        }
        body.theme-dark .var-more-menu .var-menu-item.danger {
            color: #f87171 !important;
        }
        body.theme-dark .var-more-menu .var-menu-item.danger:hover {
            background: rgba(248,113,113,.12) !important;
            color: #f87171 !important;
        }
        body.theme-dark .var-more-menu .var-menu-divider {
            background: #334155 !important;
        }
        /* Bulk delete action bar */
        .bulk-action-bar {
            display: none;
            align-items: center;
            gap: .6rem;
            padding: .45rem .75rem;
            background: rgba(220,38,38,.08);
            border: 1px solid rgba(220,38,38,.2);
            border-radius: 6px;
            margin-bottom: .5rem;
            font-size: .78rem;
        }
        .bulk-action-bar.visible { display: flex; }
        .bulk-action-bar .bulk-count { font-weight: 700; color: #f87171; }
        /* Row number column */
        .row-num-cell { color: var(--cl-text-muted); font-size: .68rem; text-align: center; width: 32px; }
        /* Checkbox column */
        .chk-cell { width: 28px; text-align: center; }
        .chk-cell .form-check-input { margin: 0; }
        /* Override global theme !important on thead th */
        .variants-table-wrap .variants-table thead th {
            font-size: .62rem !important;
            padding: .26rem .45rem !important;
            font-weight: 700 !important;
        }
        body.theme-dark .variants-table-wrap .variants-table thead th {
            color: #94a3b8 !important;
            background: #162033 !important;
        }
        /* Compact global table override for td inside variants */
        .variants-table-wrap .variants-table tbody td {
            padding: .18rem .45rem !important;
            font-size: .76rem !important;
        }
    </style>

    <?php
        $currentPage = max(1, (int)($page ?? 1));
        $currentPerPage = max(1, (int)($perPage ?? 50));
        $currentTotal = (int)($total ?? count($variants));
        $currentPages = max(1, (int)ceil(($currentTotal > 0 ? $currentTotal : 1) / $currentPerPage));
        if ($currentPage > $currentPages) $currentPage = $currentPages;
        $startItem = $currentTotal > 0 ? (($currentPage - 1) * $currentPerPage) + 1 : 0;
        $endItem = min($currentTotal, $currentPage * $currentPerPage);
        $queryBase = '?';
        if (!empty($product_id)) $queryBase .= 'product_id=' . (int)$product_id . '&';
        if (!empty($search)) $queryBase .= 'q=' . urlencode($search) . '&';
        $queryBase .= 'per_page=' . $currentPerPage . '&';

        // When filtered by a single product, grab product name once from first row
        $singleProduct = !empty($product_id);
        $headerProductName = '';
        $headerProductCode = '';
        if ($singleProduct && !empty($variants)) {
            $headerProductName = $variants[0]['product_name'] ?? '';
            $headerProductCode = $variants[0]['product_code'] ?? '';
        }
    ?>

    <div class="variants-topbar">
        <div class="variants-count">
            <?php if ($singleProduct): ?>
                Showing <strong><?= $startItem ?></strong>–<strong><?= $endItem ?></strong> of <strong><?= $currentTotal ?></strong> variants
            <?php else: ?>
                Showing <strong><?= $startItem ?></strong>–<strong><?= $endItem ?></strong> of <strong><?= $currentTotal ?></strong> variants
            <?php endif; ?>
        </div>
        <div class="variants-controls">
            <form method="get" class="d-flex align-items-center gap-1 mb-0">
                <?php if (!empty($product_id)): ?>
                    <input type="hidden" name="product_id" value="<?= (int)$product_id ?>">
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <input type="hidden" name="q" value="<?= esc($search) ?>">
                <?php endif; ?>
                <input type="hidden" name="page" value="1">
                <label class="form-label mb-0" for="perPageSelect">Rows</label>
                <select id="perPageSelect" name="per_page" class="form-select form-select-sm" onchange="this.form.submit()" style="width:60px">
                    <?php foreach ([25, 50, 100, 200] as $pp): ?>
                        <option value="<?= $pp ?>" <?= $currentPerPage === $pp ? 'selected' : '' ?>><?= $pp ?></option>
                    <?php endforeach; ?>
                </select>
            </form>

            <nav class="variants-pagination mb-0" aria-label="Variants pagination top">
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('product-variants' . $queryBase . 'page=' . max(1, $currentPage - 1)) ?>">Prev</a>
                    </li>
                    <?php
                        $window = 2;
                        $startPg = max(1, $currentPage - $window);
                        $endPg = min($currentPages, $currentPage + $window);
                        for ($p = $startPg; $p <= $endPg; $p++):
                    ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('product-variants' . $queryBase . 'page=' . $p) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $currentPage >= $currentPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('product-variants' . $queryBase . 'page=' . min($currentPages, $currentPage + 1)) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
    </div>

    <?php if ($singleProduct && $headerProductName): ?>
    <div class="variant-product-banner">
        <i class="bi bi-box-seam" style="font-size:.85rem"></i>
        <span class="vpb-label">Product:</span>
        <a href="<?= base_url('products/' . (int)$product_id) ?>" style="color:inherit;text-decoration:none;font-weight:600" title="View product page">
            <?= esc($headerProductName) ?>
            <i class="bi bi-box-arrow-up-right" style="font-size:.6rem;opacity:.6;margin-left:2px"></i>
        </a>
        <?php if ($headerProductCode): ?>
            <span style="opacity:.55;font-weight:400">(<?= esc($headerProductCode) ?>)</span>
        <?php endif; ?>
        <span class="ms-auto" style="font-weight:400;font-size:.7rem;color:var(--cl-text-muted)">
            <?= $currentTotal ?> variant<?= $currentTotal !== 1 ? 's' : '' ?>
        </span>
    </div>
    <?php endif; ?>

    <!-- Bulk action bar -->
    <div class="bulk-action-bar" id="bulkActionBar">
        <i class="bi bi-check2-square" style="color:#f87171"></i>
        <span><span class="bulk-count" id="bulkCount">0</span> variant(s) selected</span>
        <button type="button" class="btn btn-sm btn-outline-danger" id="bulkDeleteBtn" style="font-size:.75rem;padding:2px 10px">
            <i class="bi bi-trash me-1"></i>Delete Selected
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="bulkClearBtn" style="font-size:.75rem;padding:2px 10px">
            Clear Selection
        </button>
    </div>

    <div class="table-responsive variants-table-wrap" <?= $singleProduct && $headerProductName ? 'style="border-top-left-radius:0;border-top-right-radius:0"' : '' ?>>
        <table class="table table-sm variants-table">
            <thead>
                <tr>
                    <th class="chk-cell"><input type="checkbox" class="form-check-input" id="selectAllVariants" title="Select all"></th>
                    <th class="row-num-cell">#</th>
                    <th>Art #</th>
                    <th style="width:36px">Image</th>
                    <th><?= $singleProduct ? 'Attributes' : 'Product / Attributes' ?></th>
                    <th class="text-end">Price</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">On Hand</th>
                    <th class="text-end">Reserved</th>
                    <th class="text-end">Available</th>
                    <th class="text-end">Sold</th>
                    <th class="text-end" style="width:80px">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php $rowNum = $startItem; foreach ($variants as $v): ?>
                    <?php
                        $imgName = $v['image'] ?? '';
                        $imgUrl = $imgName ? base_url('uploads/variants/' . $imgName) : '';
                        $attrMap = [];
                        if (!empty($v['attributes'])) {
                            $attrMap = is_string($v['attributes']) ? (json_decode($v['attributes'], true) ?? []) : (is_array($v['attributes']) ? $v['attributes'] : []);
                        }
                        $attrParts = [];
                        if (is_array($attrMap)) {
                            foreach ($attrMap as $ak => $av) {
                                $attrParts[] = trim((string)$ak) . ': ' . trim((string)$av);
                            }
                        }
                        $attrDisplay = !empty($attrParts) ? implode(' • ', $attrParts) : '—';
                        $onHand = (float)($v['on_hand'] ?? 0);
                        $reserved = (float)($v['reserved'] ?? 0);
                        $available = $onHand - $reserved;
                        $priceRaw = $v['price'] ?? null;
                        $salePriceRaw = $v['sale_price'] ?? null;
                        $costRaw = $v['cost'] ?? null;
                        $costPriceRaw = $v['cost_price'] ?? null;

                        $displayPrice = $priceRaw;
                        if (($displayPrice === null || $displayPrice === '' || (float)$displayPrice == 0.0) && $salePriceRaw !== null && $salePriceRaw !== '' && (float)$salePriceRaw != 0.0) {
                            $displayPrice = $salePriceRaw;
                        }

                        $displayCost = $costRaw;
                        if (($displayCost === null || $displayCost === '' || (float)$displayCost == 0.0) && $costPriceRaw !== null && $costPriceRaw !== '' && (float)$costPriceRaw != 0.0) {
                            $displayCost = $costPriceRaw;
                        }

                        $saleCurrency = strtoupper(trim((string)($v['sale_currency'] ?? '')));
                        if ($saleCurrency === '') $saleCurrency = 'PKR';

                        $costCurrency = strtoupper(trim((string)($v['cost_currency'] ?? '')));
                        if ($costCurrency === '') $costCurrency = $saleCurrency;
                    ?>
                    <tr data-variant-id="<?= (int)$v['id'] ?>">
                        <td class="chk-cell"><input type="checkbox" class="form-check-input variant-chk" value="<?= (int)$v['id'] ?>" data-art="<?= esc($v['art_number'] ?? $v['id']) ?>"></td>
                        <td class="row-num-cell"><?= $rowNum++ ?></td>
                        <td><?= esc($v['art_number']) ?></td>
                        <td>
                            <?php if ($imgUrl): ?>
                                <span class="variant-thumb"><img src="<?= esc($imgUrl) ?>" alt="Variant" data-preview="<?= esc($imgUrl) ?>"></span>
                            <?php else: ?>
                                <span class="variant-thumb"><i class="bi bi-image"></i></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$singleProduct): ?>
                                <div class="variant-name text-truncate"><?= esc($v['product_name'] ?? '') ?></div>
                            <?php endif; ?>
                            <div class="variant-attrs" style="<?= $singleProduct ? 'margin-top:0;font-size:.76rem;color:var(--cl-text-secondary)' : '' ?>"><?= esc($attrDisplay) ?></div>
                        </td>
                        <td class="text-end money-cell"><?= $displayPrice !== null && $displayPrice !== '' ? number_format((float)$displayPrice,2) : '-' ?><?php if ($displayPrice !== null && $displayPrice !== ''): ?><span class="currency-pill"><?= esc($saleCurrency) ?></span><?php endif; ?></td>
                        <td class="text-end money-cell"><?= $displayCost !== null && $displayCost !== '' ? number_format((float)$displayCost,2) : '-' ?><?php if ($displayCost !== null && $displayCost !== ''): ?><span class="currency-pill"><?= esc($costCurrency) ?></span><?php endif; ?></td>
                        <td class="text-end qty-cell"><?= number_format($onHand, 2) ?></td>
                        <td class="text-end qty-cell"><?= number_format($reserved, 2) ?></td>
                        <td class="text-end qty-cell <?= $available < 0 ? 'text-danger' : '' ?>"><?= number_format($available, 2) ?></td>
                        <td class="text-end qty-cell"><?= number_format((float)($v['sold'] ?? 0), 2) ?></td>
                        <td class="actions-col">
                            <a href="<?= base_url('product-variants/' . $v['id'] . '/edit') ?>" class="var-act-btn" title="View / Edit">
                                <i class="bi bi-eye"></i>
                            </a>
                            <button type="button" class="var-act-btn var-more-btn" title="More actions"
                                    data-vid="<?= esc($v['id']) ?>">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                            <ul class="var-more-menu" id="varMenu<?= esc($v['id']) ?>">
                                <li><a href="<?= base_url('product-variants/' . $v['id'] . '/edit') ?>" class="var-menu-item"><i class="bi bi-pencil"></i>Edit</a></li>
                                <li><button type="button" class="var-menu-item btn-adjust-variant"
                                        data-pid="<?= (int)$v['product_id'] ?>"
                                        data-vid="<?= (int)$v['id'] ?>"
                                        data-name="<?= esc(trim(($v['product_name'] ?? '') . ' ' . ($v['art_number'] ?? ''))) ?>">
                                    <i class="bi bi-clipboard2-plus text-info"></i>Adjust Stock</button></li>
                                <li class="var-menu-divider"></li>
                                <li><button type="button" class="var-menu-item danger btn-delete-variant" data-id="<?= esc($v['id']) ?>" data-name="<?= esc($v['art_number'] ?? $v['id']) ?>"><i class="bi bi-trash"></i>Delete</button></li>
                            </ul>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Delete confirmation modal -->
<div class="modal fade" id="deleteVariantModal" tabindex="-1" aria-labelledby="deleteVariantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteVariantModalLabel">Delete Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete variant <strong id="deleteVariantName"></strong>?</p>
            </div>
            <div class="modal-footer">
                <form id="deleteVariantForm" method="POST" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Bulk delete confirmation modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h6 class="modal-title"><i class="bi bi-exclamation-triangle-fill me-2"></i>Bulk Delete Variants</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>You are about to delete <strong id="bulkDeleteCount">0</strong> variant(s).</p>
                <p class="text-muted" style="font-size:.82rem">Variants linked to quotations, purchase orders, or sales orders will be skipped and not deleted.</p>
                <p class="fw-bold text-danger">This action cannot be undone. Continue?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-danger" id="confirmBulkDeleteBtn"><i class="bi bi-trash me-1"></i>Delete</button>
            </div>
        </div>
    </div>
</div>
<!-- Bulk delete result modal -->
<div class="modal fade" id="bulkResultModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title"><i class="bi bi-info-circle me-2"></i>Bulk Delete Results</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="bulkResultBody"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">OK</button>
            </div>
        </div>
    </div>
</div>

<!-- Image hover preview -->
<div id="varImgPreview"><img src="" alt="" id="varImgPreviewImg"></div>

<script>
(function(){
    // ── Image hover zoom preview ─────────────────────────────────────
    (function(){
        var preview = document.getElementById('varImgPreview');
        var previewImg = document.getElementById('varImgPreviewImg');
        if (!preview || !previewImg) return;
        var hideTimer = null;

        function position(e) {
            var vw = window.innerWidth, vh = window.innerHeight;
            var pw = 230, ph = 230;
            var left = e.clientX + 16;
            var top  = e.clientY + 16;
            if (left + pw > vw - 8) left = e.clientX - pw - 12;
            if (top  + ph > vh - 8) top  = e.clientY - ph - 12;
            preview.style.left = left + 'px';
            preview.style.top  = top  + 'px';
        }

        document.addEventListener('mouseover', function(e) {
            var thumb = e.target.closest('[data-preview]');
            if (!thumb) return;
            clearTimeout(hideTimer);
            previewImg.src = thumb.dataset.preview;
            preview.classList.add('is-visible');
            position(e);
        });

        document.addEventListener('mousemove', function(e) {
            if (!preview.classList.contains('is-visible')) return;
            if (!e.target.closest('[data-preview]')) return;
            position(e);
        });

        document.addEventListener('mouseout', function(e) {
            var thumb = e.target.closest('[data-preview]');
            if (!thumb) return;
            hideTimer = setTimeout(function(){ preview.classList.remove('is-visible'); }, 80);
        });
    })();

    // ── Custom fixed-position ⋮ dropdown for variants ──────────────
    var openMenu = null;

    document.addEventListener('click', function(e) {
        var moreBtn = e.target.closest('.var-more-btn');
        if (moreBtn) {
            e.stopPropagation();
            var vid = moreBtn.getAttribute('data-vid');
            var menu = document.getElementById('varMenu' + vid);
            if (!menu) return;
            if (menu.classList.contains('is-open')) {
                menu.classList.remove('is-open');
                openMenu = null;
                return;
            }
            if (openMenu) openMenu.classList.remove('is-open');

            // Move menu to <body> to escape any overflow:hidden / stacking context in table
            if (menu.parentNode !== document.body) {
                document.body.appendChild(menu);
            }

            // ── Two-phase positioning: show off-screen → measure → clamp to viewport ──
            menu.style.top  = '-9999px';
            menu.style.left = '-9999px';
            menu.classList.add('is-open');
            var mw   = menu.offsetWidth  || 170;
            var mh   = menu.offsetHeight || 110;
            var rect = moreBtn.getBoundingClientRect();
            var top  = rect.bottom + 4;
            var left = rect.right - mw;
            // Horizontal clamp: keep within viewport
            if (left < 4) left = Math.min(rect.left, 4);
            left = Math.max(4, Math.min(left, window.innerWidth - mw - 4));
            // Vertical flip: if menu overflows bottom, open upward
            if (top + mh > window.innerHeight - 8) {
                var upTop = rect.top - mh - 4;
                if (upTop >= 4) top = upTop;
            }
            menu.style.top  = top  + 'px';
            menu.style.left = left + 'px';
            openMenu = menu;
            return;
        }
        // Close on outside click
        if (openMenu && !openMenu.contains(e.target)) {
            openMenu.classList.remove('is-open');
            openMenu = null;
        }
        // Adjust button inside menu
        var adjBtn = e.target.closest('.btn-adjust-variant');
        if (adjBtn) {
            if (openMenu) { openMenu.classList.remove('is-open'); openMenu = null; }
            window.qadjOpen(
                adjBtn.getAttribute('data-pid'),
                adjBtn.getAttribute('data-vid'),
                adjBtn.getAttribute('data-name') || '',
                false
            );
            return;
        }
        // Delete button inside menu
        var delBtn = e.target.closest('.btn-delete-variant');
        if (!delBtn) return;
        if (openMenu) { openMenu.classList.remove('is-open'); openMenu = null; }
        var id   = delBtn.getAttribute('data-id');
        var name = delBtn.getAttribute('data-name') || id;
        var form = document.getElementById('deleteVariantForm');
        form.action = '<?= base_url('/product-variants') ?>/' + encodeURIComponent(id);
        document.getElementById('deleteVariantName').textContent = name;
        new bootstrap.Modal(document.getElementById('deleteVariantModal')).show();
    });

    // Close on scroll
    document.addEventListener('scroll', function() {
        if (openMenu) { openMenu.classList.remove('is-open'); openMenu = null; }
    }, true);

    // ── Bulk select / delete logic ──────────────
    var selectAll = document.getElementById('selectAllVariants');
    var bulkBar = document.getElementById('bulkActionBar');
    var bulkCountEl = document.getElementById('bulkCount');
    var allChks = document.querySelectorAll('.variant-chk');

    function updateBulkBar() {
        var checked = document.querySelectorAll('.variant-chk:checked');
        var cnt = checked.length;
        if (bulkCountEl) bulkCountEl.textContent = cnt;
        if (bulkBar) {
            if (cnt > 0) bulkBar.classList.add('visible');
            else bulkBar.classList.remove('visible');
        }
        // Update select-all state
        if (selectAll) {
            selectAll.checked = cnt > 0 && cnt === allChks.length;
            selectAll.indeterminate = cnt > 0 && cnt < allChks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            allChks.forEach(function(c) { c.checked = selectAll.checked; });
            updateBulkBar();
        });
    }
    allChks.forEach(function(c) {
        c.addEventListener('change', updateBulkBar);
    });

    // Clear selection
    document.getElementById('bulkClearBtn')?.addEventListener('click', function() {
        allChks.forEach(function(c) { c.checked = false; });
        if (selectAll) selectAll.checked = false;
        updateBulkBar();
    });

    // Open bulk delete confirmation modal
    document.getElementById('bulkDeleteBtn')?.addEventListener('click', function() {
        var checked = document.querySelectorAll('.variant-chk:checked');
        if (checked.length === 0) return;
        document.getElementById('bulkDeleteCount').textContent = checked.length;
        new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
    });

    // Confirm bulk delete
    document.getElementById('confirmBulkDeleteBtn')?.addEventListener('click', async function() {
        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting…';
        var checked = document.querySelectorAll('.variant-chk:checked');
        var ids = Array.from(checked).map(function(c) { return parseInt(c.value); });

        try {
            var resp = await fetch('<?= base_url("product-variants/bulk-delete") ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ ids: ids })
            });
            var data = await resp.json();
            bootstrap.Modal.getInstance(document.getElementById('bulkDeleteModal'))?.hide();

            var html = '';
            if (data.deleted_count > 0) {
                html += '<div class="alert alert-success py-2" style="font-size:.85rem"><i class="bi bi-check-circle me-1"></i><strong>' + data.deleted_count + '</strong> variant(s) deleted successfully.</div>';
                // Remove deleted rows from DOM
                (data.deleted_ids || []).forEach(function(id) {
                    var row = document.querySelector('tr[data-variant-id="' + id + '"]');
                    if (row) row.remove();
                });
            }
            if (data.blocked_count > 0) {
                html += '<div class="alert alert-warning py-2" style="font-size:.85rem"><i class="bi bi-shield-exclamation me-1"></i><strong>' + data.blocked_count + '</strong> variant(s) could not be deleted (linked to documents):</div>';
                html += '<ul style="font-size:.82rem;margin:0">';
                (data.blocked || []).forEach(function(b) {
                    html += '<li><strong>' + b.art_number + '</strong> — ' + b.reason + '</li>';
                });
                html += '</ul>';
            }
            if (!html) html = '<div class="alert alert-info py-2">No changes made.</div>';
            document.getElementById('bulkResultBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('bulkResultModal')).show();

            // Reset checkboxes
            allChks.forEach(function(c) { c.checked = false; });
            if (selectAll) selectAll.checked = false;
            updateBulkBar();
        } catch (err) {
            alert('Error: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-trash me-1"></i>Delete';
        }
    });
})();
</script>
