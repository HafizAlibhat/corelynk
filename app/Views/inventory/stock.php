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

                <div class="col-md-4">
                    <label class="form-label fw-bold">Attribute</label>
                    <select class="form-select stk-attr-name" id="stkAttrNameSel">
                        <option value="">Select Attribute</option>
                        <?php foreach ($attributeOptions as $attrName => $attrValues): ?>
                            <option value="<?= esc($attrName) ?>"><?= esc($attrName) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Attribute Value</label>
                    <input type="text" class="form-control stk-attr-value" id="stkAttrValueInp" list="stkAttrValueList" placeholder="e.g. Curved">
                    <datalist id="stkAttrValueList"></datalist>
                </div>
                <div class="col-md-4 text-end">
                    <button type="button" class="btn btn-outline-primary mt-1" id="stkAttrAddBtn" onclick="addStockAttributeFilter(event)">
                        <i class="bi bi-plus"></i> Add Attribute Filter
                    </button>
                </div>

                <div class="col-12">
                    <div id="stkAttrTagsContainer" class="d-flex flex-wrap gap-2"></div>
                    <div id="stkAttrHiddenInputs" style="display:none"></div>
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
    // Multi-Attribute Filter Handler (same format as products page)
    (function() {
        const attrMap = <?= json_encode($attributeOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        const attrNameSel = document.getElementById('stkAttrNameSel');
        const attrValueInp = document.getElementById('stkAttrValueInp');
        const attrValueList = document.getElementById('stkAttrValueList');
        const tagsContainer = document.getElementById('stkAttrTagsContainer');
        const hiddenInputsDiv = document.getElementById('stkAttrHiddenInputs');

        let currentAttributes = <?= json_encode($currentAttributes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        if (!Array.isArray(currentAttributes)) {
            currentAttributes = [];
        }

        function esc(str) {
            if (!str) return '';
            const div = document.createElement('div');
            div.textContent = String(str);
            return div.innerHTML;
        }

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
                tag.className = 'stk-attr-tag';
                tag.innerHTML = '<span style="opacity:.8">' + esc(attr.name) + '</span>'
                    + '<span style="opacity:.5">:</span>'
                    + '<span class="stk-attr-tag-val">' + esc(attr.value) + '</span>'
                    + '<button type="button" class="stk-attr-tag-rm" data-idx="' + idx + '">&times;</button>';
                tagsContainer.appendChild(tag);

                const removeBtn = tag.querySelector('.stk-attr-tag-rm');
                if (removeBtn) {
                    removeBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        currentAttributes.splice(parseInt(this.dataset.idx, 10), 1);
                        renderTags();
                    });
                }

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
            const name = attrNameSel ? (attrNameSel.value || '').trim() : '';
            const value = attrValueInp ? (attrValueInp.value || '').trim() : '';
            if (!name || !value) {
                alert('Please select an attribute and enter a value');
                return;
            }

            const isDuplicate = currentAttributes.some(function(a) {
                return String(a.name).toLowerCase() === name.toLowerCase() && String(a.value).toLowerCase() === value.toLowerCase();
            });
            if (isDuplicate) {
                alert('This attribute filter already exists');
                return;
            }

            currentAttributes.push({ name: name, value: value });
            renderTags();
            if (attrNameSel) attrNameSel.value = '';
            if (attrValueInp) attrValueInp.value = '';
            updateValueSuggestions();
        }

        window.addStockAttributeFilter = function(e) {
            if (e) e.preventDefault();
            addAttribute();
        };

        if (attrNameSel) {
            attrNameSel.addEventListener('change', updateValueSuggestions);
        }
        if (attrValueInp) {
            attrValueInp.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addAttribute();
                }
            });
        }

        renderTags();
        updateValueSuggestions();
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
