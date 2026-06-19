<?php /** @var array $rows */ /** @var array $warehouses */ /** @var array $filters */ ?>
<?= $this->extend('layouts/main') ?>

<?php
    $attributeOptions = $attribute_options ?? [];
    $currentAttributes = $filters['attributes'] ?? [];
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
    .stk-filter-hint { font-size: .78rem; color: var(--cl-text-muted); margin-top: .35rem; }
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
</style>
<div class="main-content-wrapper">
    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title mb-0"><i class="bi bi-box-seam text-info me-2"></i>Inventory Stock</h1>
                <p class="text-muted mb-0">Read-only stock by warehouse and location</p>
            </div>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Warehouse</label>
                    <select name="warehouse_id" class="form-select">
                        <option value="">All warehouses</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= esc($w['id']) ?>" <?= (isset($filters['warehouse_id']) && $filters['warehouse_id']==$w['id'])? 'selected':'' ?>><?= esc($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Product</label>
                    <input type="search" name="product_search" id="productSearch" list="productList" class="form-control" placeholder="Name, code, SKU, variant code/name, attributes" value="<?= esc($filters['product_search'] ?? '') ?>">
                    <datalist id="productList"></datalist>
                </div>
                <div class="col-md-2 text-end">
                    <button type="submit" class="btn btn-primary mt-1"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                    <a href="<?= base_url('inventory/stock') ?>" class="btn btn-link text-decoration-none mt-1">Reset</a>
                </div>

                <div class="col-12">
                    <label class="form-label fw-bold">Attribute Filter</label>
                    <div class="stk-af-row" id="stkAfRow">
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

                        <button type="button" class="btn btn-outline-primary mt-1 stk-af-add-btn" id="stkAfAddBtn" disabled>
                            <i class="bi bi-plus"></i> Add Attribute Filter
                        </button>
                    </div>

                    <div id="stkAfChips" class="d-flex flex-wrap gap-2 mt-2"></div>
                    <div id="stkAfHidden" style="display:none"></div>
                    <div class="stk-filter-hint">Pro tip: This stock search now supports product code, variant code/name, and attribute filters exactly like products search.</div>
                </div>
            </form>
        </div>
    </div>

    <!-- Stock Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle" style="font-size:0.85rem;">
                    <thead class="table-dark">
                                <tr>
                                    <th style="width:11%">Product Code</th>
                                    <th style="width:7%">Image</th>
                                    <th style="width:50%">Product</th>
                                    <th class="text-end" style="width:32%">Stock by Location</th>
                                </tr>
                    </thead>
                            <tbody>
                                <?php if (empty($rows)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-4">No stock found</td>
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
                                                ];
                                            }
                                            $qty = isset($r['quantity']) ? (float)$r['quantity'] : 0.0;
                                            $groups[$key]['rows'][] = $r;
                                            $groups[$key]['total'] += $qty;
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
                                            <tr class="product-summary align-middle" data-group="<?= esc($gid) ?>" aria-expanded="false" style="cursor:pointer;">
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
                                                <td class="py-1 align-middle small fw-bold" aria-expanded="true" title="<?= esc($displayCode ?? 'No Code') ?>">
                                                    <?= esc($displayCode !== '' && $displayCode !== null ? $displayCode : '-') ?>
                                                </td>
                                                <td class="py-1 align-middle">
                                                    <img src="<?= esc($img) ?>" alt="" data-preview="<?= esc($img) ?>" style="width:34px;height:30px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.dataset.preview='<?= base_url('assets/images/no-image.png') ?>';">
                                                </td>
                                                <td class="py-1 align-middle">
                                                    <div class="fw-semibold" style="line-height:1.2;">
                                                        <?= esc($g['product_name'] ?: 'Unnamed Product') ?>
                                                        <?php if (!empty($g['variant_id'])): ?>
                                                            <span class="text-muted fw-normal">— <?= esc($g['variant_name'] ?: 'Variant') ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                            </td>
                                            <td class="py-1 align-middle text-end pe-3">
                                                <span class="badge bg-success" style="font-size:0.85rem;"><?= number_format($g['total'], 2) ?></span>
                                            </td>
                                        </tr>
                                        <!-- Location detail rows -->
                                <?php foreach ($g['rows'] as $dr): ?>
                                    <tr class="product-detail group-<?= esc($gid) ?>" style="background-color: rgba(255,255,255,0.015); border-left: 3px solid #0d6efd;">
                                        <td class="py-1" style="padding-left: 2.5rem;"></td>
                                        <td class="py-1"></td>
                                        <td class="py-1 small" style="padding-left:2.5rem;">
                                        </td>
                                        <td class="py-1 text-end pe-3" style="font-size:0.9rem;">
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 1.5rem;">
                                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                                    <i class="bi bi-arrow-return-right text-muted" style="font-size:0.8rem;"></i>
                                                    <span class="text-muted"><?= esc($dr['warehouse_name'] ?? '') ?> / <?= esc($dr['location_path'] ?? $dr['location_name'] ?? '-') ?></span>
                                                </div>
                                                <span class="fw-bold" style="color: #10b981; min-width: 45px; text-align: right;"><?= number_format($dr['quantity'], 2) ?></span>
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
