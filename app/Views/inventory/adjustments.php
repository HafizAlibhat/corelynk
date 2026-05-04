<?php /** @var array $warehouses */ /** @var array $locations */ ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Stock Adjustments<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/* ── Page shell ─────────────────────────────── */
.sadj-page { max-width:1200px; margin:0 auto; }

/* ── Mode cards ─────────────────────────────── */
.mode-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:.6rem; }
.mode-card {
    border:2px solid transparent; border-radius:10px; padding:.7rem .9rem;
    cursor:pointer; background:var(--cl-surface,#1e293b);
    transition:all .15s; user-select:none;
}
.mode-card:hover { border-color:#6366f1; }
.mode-card.selected { border-color:#6366f1; background:rgba(99,102,241,.08); }
.mode-card .mc-icon { font-size:1.6rem; margin-bottom:.35rem; }
.mode-card .mc-title { font-size:.8rem; font-weight:700; color:#e2e8f0; }
.mode-card .mc-desc  { font-size:.7rem; color:#94a3b8; margin-top:.2rem; line-height:1.4; }

/* ── Lines flex layout ───────────────────────── */
.lines-wrap { overflow:visible; }
.lines-header {
    display:flex; align-items:center; gap:.4rem;
    font-size:.68rem; text-transform:uppercase; letter-spacing:.04em;
    color:#64748b; font-weight:700; padding:.4rem .3rem;
    border-bottom:1px solid #1e293b;
}
.line-row {
    display:flex; align-items:flex-start; gap:.4rem;
    padding:.35rem .3rem; border-bottom:1px solid rgba(255,255,255,.04);
    font-size:.8rem;
}
.lr-product { flex:0 0 210px; min-width:0; position:relative; }
.lr-source  { flex:0 0 140px; min-width:0; }
.lr-cost    { flex:0 0 95px;  min-width:0; }
.lr-vendor  { flex:0 0 155px; min-width:0; }
.lr-curr    { flex:0 0 68px;  text-align:right; padding-top:.3rem; white-space:nowrap; }
.lr-qty     { flex:0 0 85px;  min-width:0; }
.lr-est-val { flex:0 0 72px;  text-align:right; padding-top:.3rem; }
.lr-newbal  { flex:0 0 72px;  text-align:right; padding-top:.3rem; }
.lr-note    { flex:1 1 auto;  min-width:0; }
.lr-rm      { flex:0 0 22px;  text-align:center; padding-top:.25rem; }
/* ── Vendor search combo ─────────────────────────── */
.vs-results {
    position:absolute; top:100%; left:0; z-index:1070;
    background:var(--cl-surface,#1e293b); border:1px solid #334155;
    border-radius:6px; max-height:180px; overflow:auto;
    display:none; box-shadow:0 4px 15px rgba(0,0,0,.4); min-width:180px;
}
.vs-results .vs-item {
    padding:.38rem .65rem; cursor:pointer; font-size:.78rem;
    border-bottom:1px solid rgba(255,255,255,.04);
    display:flex; align-items:center; gap:.4rem; color:#e2e8f0;
}
.vs-results .vs-item:hover { background:rgba(99,102,241,.15); }
.vs-linked-badge { font-size:.62rem; background:rgba(52,211,153,.15); color:#34d399; border-radius:3px; padding:.05rem .3rem; }
.line-qty-input { text-align:right; }
.remove-btn { border:none; background:none; color:#f87171; padding:0; font-size:.9rem; cursor:pointer; }
.remove-btn:hover { color:#ef4444; }
.new-bal { font-weight:600; font-size:.78rem; }
.new-bal.positive { color:#34d399; }
.new-bal.negative { color:#f87171; }
.new-bal.neutral  { color:#94a3b8; }
.est-val { font-size:.78rem; color:#a5b4fc; }

/* ── Product search dropdown ─────────────────── */
.product-search-wrap { position:relative; }
.ps-results {
    position:absolute; top:100%; left:0; z-index:1060;
    background:var(--cl-surface,#1e293b); border:1px solid #334155;
    border-radius:6px; max-height:220px; overflow:auto;
    display:none; box-shadow:0 4px 15px rgba(0,0,0,.4);
    min-width:280px;
}
.ps-results .ps-item {
    padding:.45rem .7rem; cursor:pointer; font-size:.78rem;
    border-bottom:1px solid rgba(255,255,255,.04);
}
.ps-results .ps-item:hover { background:rgba(99,102,241,.15); }
.ps-results .ps-item .ps-name { color:#e2e8f0; font-weight:600; }
.ps-results .ps-item .ps-stock { color:#64748b; font-size:.7rem; margin-left:.5rem; }

/* ── History table ───────────────────────────── */
.hist-badge { font-size:.65rem; padding:.2em .5em; border-radius:4px; font-weight:700; }
.hist-type-opening_stock { background:rgba(52,211,153,.15); color:#34d399; }
.hist-type-adjustment    { background:rgba(99,102,241,.15); color:#a5b4fc; }

/* ── Misc ──────────────────────────────────────── */
.section-card { background:var(--cl-surface,#1e293b); border:1px solid #1e293b; border-radius:10px; padding:1rem 1.2rem; margin-bottom:1rem; }
</style>

<div class="main-content-wrapper sadj-page">

    <!-- ══ Page header ══════════════════════════════════════════════════════ -->
    <div class="page-header mb-3">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h1 class="page-title mb-0">
                    <i class="bi bi-clipboard2-plus text-info me-2"></i>Stock Adjustment
                </h1>
                <p class="text-muted mb-0" style="font-size:.82rem">
                    Record opening/existing stock or correct on-hand quantities without linking to a purchase order.
                </p>
            </div>
            <a href="<?= site_url('inventory/stock') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-box-seam me-1"></i>View Live Stock
            </a>
        </div>
    </div>

    <div id="sadjMsg" class="mb-2" style="min-height:1rem"></div>

    <!-- ══ Step 1 — Choose mode ══════════════════════════════════════════════ -->
    <div class="section-card">
        <div class="fw-bold mb-3" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">
            Step 1 — What are you doing?
        </div>
        <div class="mode-grid" id="modeGrid">
            <div class="mode-card selected" data-mode="add" data-type="opening_stock">
                <div class="mc-icon">📦</div>
                <div class="mc-title">Opening / Found Stock</div>
                <div class="mc-desc">Record existing stock you already have. Quantity is added on top of whatever is already in the system.</div>
            </div>
            <div class="mode-card" data-mode="set" data-type="adjustment">
                <div class="mc-icon">🎯</div>
                <div class="mc-title">Correct to Exact Qty</div>
                <div class="mc-desc">After a physical stock-count, set the recorded quantity to exactly what you actually have.</div>
            </div>
        </div>
    </div>

    <!-- ══ Step 2 — Where & Notes ════════════════════════════════════════════ -->
    <div class="section-card">
        <div class="fw-bold mb-3" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">
            Step 2 — Where &amp; Why
        </div>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.78rem">Warehouse <span class="text-danger">*</span></label>
                <select id="warehouseSelect" class="form-select form-select-sm">
                    <option value="">-- Select warehouse --</option>
                    <?php foreach ($warehouses as $w): ?>
                        <option value="<?= (int)$w['id'] ?>"><?= esc($w['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.78rem">Location <span class="text-danger">*</span></label>
                <select id="locationSelect" class="form-select form-select-sm" disabled>
                    <option value="">-- Select warehouse first --</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold" style="font-size:.78rem">Notes / Reason</label>
                <input type="text" id="adjNotes" class="form-control form-control-sm" placeholder="e.g. Opening stock — warehouse clean-up April 2026">
            </div>
        </div>
    </div>

    <!-- ══ Step 3 — Product lines ═════════════════════════════════════════════ -->
    <div class="section-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-bold" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">
                Step 3 — Products
            </div>
            <button type="button" id="addLineBtn" class="btn btn-sm" style="background:#6366f1;color:#fff;border:none;font-size:.75rem">
                <i class="bi bi-plus-circle me-1"></i>Add Product Line
            </button>
        </div>

        <div class="lines-wrap" id="linesWrap">
            <!-- Column headers -->
            <div class="lines-header">
                <div class="lr-product">Product / Variant</div>
                <div class="lr-source">Source <span class="text-danger" id="srcRequiredStar">*</span></div>
                <div class="lr-vendor">Possible Vendor</div>
                <div class="lr-cost">Est. Unit Cost <span class="text-danger" id="costRequiredStar">*</span></div>
                <div class="lr-curr">Current</div>
                <div class="lr-qty"><span id="qtyColLabel">Qty to Add</span></div>
                <div class="lr-est-val">Est. Value</div>
                <div class="lr-newbal">New Balance</div>
                <div class="lr-note">Line Notes</div>
                <div class="lr-rm"></div>
            </div>
            <!-- Lines body -->
            <div id="linesBody">
                <div id="emptyRow" class="text-center text-muted py-4" style="font-size:.78rem">
                    <i class="bi bi-arrow-up-circle me-1"></i>Click "Add Product Line" to begin
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mt-3 flex-wrap gap-2">
            <div>
                <span id="lineCount" class="text-muted" style="font-size:.75rem">0 lines</span>
                <span id="estTotalWrap" class="ms-3 text-muted" style="font-size:.75rem;display:none">
                    Est. Total: <strong id="estTotal" class="text-info">—</strong>
                </span>
            </div>
            <button type="button" id="submitBtn" class="btn btn-success px-4" disabled>
                <i class="bi bi-check2-circle me-1"></i>Save Stock Adjustment
            </button>
        </div>
    </div>

    <!-- ══ History ═══════════════════════════════════════════════════════════ -->
    <div class="section-card">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-bold" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b">
                Adjustment History
            </div>
            <button type="button" id="refreshHistBtn" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem">
                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
            </button>
        </div>
        <div id="histContainer">
            <div class="text-muted text-center py-3" style="font-size:.78rem">Loading history…</div>
        </div>
    </div>
</div>

<script>
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────────────────────
    var currentMode = 'add';
    var currentType = 'opening_stock';

    var ALL_LOCATIONS = <?= json_encode(array_map(function($l) {
        return [
            'id'           => (int)$l['id'],
            'name'         => $l['name'],
            'warehouse_id' => (int)$l['warehouse_id'],
            'parent_id'    => isset($l['parent_id']) && $l['parent_id'] ? (int)$l['parent_id'] : null,
        ];
    }, $locations), JSON_UNESCAPED_UNICODE) ?>;

    var BASE_URL = '<?= rtrim(site_url(), '/') ?>';

    var EMPTY_ROW_HTML = '<div id="emptyRow" class="text-center text-muted py-4" style="font-size:.78rem">'
        + '<i class="bi bi-arrow-up-circle me-1"></i>Click "Add Product Line" to begin</div>';

    // ── Mini toast helper ────────────────────────────────────────────────────
    function msg(text, type) {
        type = type || 'info';
        var colors = { success: '#22c55e', danger: '#ef4444', warning: '#f59e0b', info: '#38bdf8' };
        var el = document.getElementById('sadjMsg');
        el.innerHTML = '<div style="background:rgba(' + (type==='success'?'34,197,94':'type'==='danger'?'239,68,68':'type'==='warning'?'245,158,11':'56,189,248') + ',.08);border:1px solid rgba(200,200,200,.15);border-radius:7px;padding:.55rem .85rem;font-size:.8rem;color:' + (colors[type] || colors.info) + '">'
            + '<i class="bi bi-' + (type==='success'?'check-circle':'exclamation-circle') + ' me-1"></i>' + escHtml(text) + '</div>';
        setTimeout(function(){ el.innerHTML = ''; }, 7000);
    }
    function escHtml(s) {
        var d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML;
    }

    // ── Cost-column visibility ───────────────────────────────────────────────
    function refreshCostFieldVisibility() {
        var isRequired = (currentType === 'opening_stock');
        document.getElementById('srcRequiredStar').style.display  = isRequired ? '' : 'none';
        document.getElementById('costRequiredStar').style.display = isRequired ? '' : 'none';
        document.querySelectorAll('.line-row').forEach(function(row) {
            var srcSel  = row.querySelector('.source-select');
            var costInp = row.querySelector('.unit-cost-input');
            if (srcSel)  srcSel.required  = isRequired;
            if (costInp) costInp.required = isRequired;
        });
    }

    // ── Mode selection ───────────────────────────────────────────────────────
    document.querySelectorAll('.mode-card').forEach(function (card) {
        card.addEventListener('click', function () {
            document.querySelectorAll('.mode-card').forEach(function(c){ c.classList.remove('selected'); });
            card.classList.add('selected');
            currentMode = card.dataset.mode;
            currentType = card.dataset.type;
            var lbl = { add: 'Qty to Add', set: 'Set to Exact Qty' };
            document.getElementById('qtyColLabel').textContent = lbl[currentMode] || 'Quantity';
            refreshCostFieldVisibility();
            document.querySelectorAll('.line-row').forEach(recomputeRow);
            updateSubmitState();
        });
    });
    // ── Location hierarchy builder ───────────────────────────────────────────────
    function buildLocOpts(locs, warehouseId) {
        var wlocs = locs.filter(function(l) { return l.warehouse_id === warehouseId; });
        var byPid = {};
        wlocs.forEach(function(l) {
            var k = l.parent_id || 0;
            if (!byPid[k]) byPid[k] = [];
            byPid[k].push(l);
        });
        var result = [];
        function walk(pid, depth) {
            var kids = (byPid[pid] || []).slice().sort(function(a, b) { return a.name.localeCompare(b.name); });
            kids.forEach(function(l) {
                var prefix = depth === 0 ? '' : '\u00A0\u00A0'.repeat(depth) + '\u203A ';
                result.push({ id: l.id, label: prefix + l.name });
                walk(l.id, depth + 1);
            });
        }
        walk(0, 0);
        return result;
    }
    // ── Warehouse → Location cascade ─────────────────────────────────────────
    var warehouseSel = document.getElementById('warehouseSelect');
    var locationSel  = document.getElementById('locationSelect');

    warehouseSel.addEventListener('change', function () {
        var wid = parseInt(this.value) || 0;
        locationSel.innerHTML = '<option value="">-- Select location --</option>';
        if (!wid) { locationSel.disabled = true; updateSubmitState(); return; }
        var opts = buildLocOpts(ALL_LOCATIONS, wid);
        opts.forEach(function(o) {
            var el = document.createElement('option');
            el.value = o.id; el.textContent = o.label;
            locationSel.appendChild(el);
        });
        locationSel.disabled = opts.length === 0;
        if (opts.length === 1) locationSel.value = opts[0].id;
        updateSubmitState();
        document.querySelectorAll('.line-row').forEach(function(row){
            var ik = row.dataset.itemKey;
            if (ik) refreshCurrentStock(row, ik);
        });
    });
    locationSel.addEventListener('change', function(){
        updateSubmitState();
        document.querySelectorAll('.line-row').forEach(function(row){
            var ik = row.dataset.itemKey;
            if (ik) refreshCurrentStock(row, ik);
        });
    });

    // ── Line row generation ──────────────────────────────────────────────────
    var lineCounter = 0;

    function addLineRow() {
        var empty = document.getElementById('emptyRow');
        if (empty) empty.remove();

        lineCounter++;
        var lid = 'line_' + lineCounter;
        var isRequired = (currentType === 'opening_stock');
        var div = document.createElement('div');
        div.className = 'line-row';
        div.dataset.lineId     = lid;
        div.dataset.productId  = '';
        div.dataset.variantId  = '';
        div.dataset.itemKey    = '';
        div.dataset.currentStock = '0';

        div.innerHTML = [
            '<div class="lr-product">',
              '<div class="product-search-wrap">',
                '<input type="text" class="form-control form-control-sm ps-input" placeholder="Type product name or code…" autocomplete="off" data-lid="' + lid + '">',
                '<div class="ps-results" id="psr_' + lid + '"></div>',
              '</div>',
              '<input type="hidden" class="pid-hidden" value="">',
              '<input type="hidden" class="vid-hidden" value="">',
              '<div class="ps-chosen text-muted" style="font-size:.68rem;margin-top:2px"></div>',
            '</div>',
            '<div class="lr-source">',
              '<select class="form-select form-select-sm source-select"' + (isRequired ? ' required' : '') + '>',
                '<option value="">— Select source —</option>',
                '<option value="old_stock">No Vendor Trail</option>',
                '<option value="purchased_no_record">Purchased — No PO on File</option>',
                '<option value="known">From a Vendor</option>',
              '</select>',
            '</div>',
            '<div class="lr-vendor" style="visibility:hidden;pointer-events:none">',
              '<div style="position:relative">',
                '<input type="text" class="form-control form-control-sm vendor-input" placeholder="Search vendor\u2026" autocomplete="off">',
                '<div class="vs-results" id="vsr_' + lid + '"></div>',
                '<input type="hidden" class="vendor-hidden" value="">',
              '</div>',
            '</div>',
            '<div class="lr-cost">',
              '<input type="number" class="form-control form-control-sm unit-cost-input" min="0.0001" step="any" placeholder="Unit cost"' + (isRequired ? ' required' : '') + '>',
            '</div>',
            '<div class="lr-curr">',
              '<span class="cur-stock text-muted" style="font-size:.8rem">—</span>',
            '</div>',
            '<div class="lr-qty">',
              '<input type="number" class="form-control form-control-sm line-qty-input" min="0.0001" step="any" placeholder="0">',
            '</div>',
            '<div class="lr-est-val">',
              '<span class="est-val text-muted" style="font-size:.8rem">—</span>',
            '</div>',
            '<div class="lr-newbal">',
              '<span class="new-bal neutral" style="font-size:.8rem">—</span>',
            '</div>',
            '<div class="lr-note">',
              '<input type="text" class="form-control form-control-sm line-note-input" placeholder="optional note…">',
            '</div>',
            '<div class="lr-rm">',
              '<button type="button" class="remove-btn" title="Remove line"><i class="bi bi-x-circle"></i></button>',
            '</div>',
        ].join('');

        document.getElementById('linesBody').appendChild(div);
        wireLineRow(div);
        updateLineCount();
        updateSubmitState();
        div.querySelector('.ps-input').focus();
    }

    function wireLineRow(row) {
        var psInput  = row.querySelector('.ps-input');
        var pidHid   = row.querySelector('.pid-hidden');
        var vidHid   = row.querySelector('.vid-hidden');
        var chosen   = row.querySelector('.ps-chosen');
        var curStock = row.querySelector('.cur-stock');
        var qtyInput = row.querySelector('.line-qty-input');
        var costInput= row.querySelector('.unit-cost-input');
        var removeBtn= row.querySelector('.remove-btn');
        var lid      = row.dataset.lineId;
        var resultsEl= document.getElementById('psr_' + lid);

        var debounceTimer = null;

        psInput.addEventListener('input', function(){
            var q = this.value.trim();
            clearTimeout(debounceTimer);
            if (q.length < 2) { resultsEl.style.display = 'none'; return; }
            debounceTimer = setTimeout(function(){ doProductSearch(q, row, resultsEl, psInput, pidHid, vidHid, curStock, chosen); }, 280);
        });

        psInput.addEventListener('blur', function(){
            setTimeout(function(){ resultsEl.style.display = 'none'; }, 180);
        });

        qtyInput.addEventListener('input',  function(){ recomputeRow(row); updateSubmitState(); });
        qtyInput.addEventListener('change', function(){ recomputeRow(row); updateSubmitState(); });

        if (costInput) {
            costInput.addEventListener('input',  function(){ recomputeRow(row); updateEstTotal(); updateSubmitState(); });
            costInput.addEventListener('change', function(){ recomputeRow(row); updateEstTotal(); updateSubmitState(); });
        }

        row.querySelector('.source-select').addEventListener('change', function() {
            updateSubmitState();
            var isKnown    = this.value === 'known';
            var vendorCell = row.querySelector('.lr-vendor');
            if (vendorCell) {
                vendorCell.style.visibility    = isKnown ? 'visible' : 'hidden';
                vendorCell.style.pointerEvents = isKnown ? 'auto'    : 'none';
            }
            if (!isKnown) {
                var vi = row.querySelector('.vendor-input');
                var vh = row.querySelector('.vendor-hidden');
                if (vi) vi.value = '';
                if (vh) vh.value = '';
            } else {
                doVendorSearch(row, '');
            }
        });

        var vendorInput  = row.querySelector('.vendor-input');
        var vendorHidden = row.querySelector('.vendor-hidden');
        if (vendorInput) {
            var vDebounce = null;
            vendorInput.addEventListener('input', function () {
                if (vendorHidden) vendorHidden.value = '';
                clearTimeout(vDebounce);
                vDebounce = setTimeout(function () { doVendorSearch(row, vendorInput.value.trim()); }, 250);
            });
            vendorInput.addEventListener('focus', function () {
                if (!(vendorHidden && vendorHidden.value)) {
                    doVendorSearch(row, vendorInput.value.trim());
                }
            });
            vendorInput.addEventListener('blur', function () {
                var vsrId = 'vsr_' + row.dataset.lineId;
                var resEl = document.getElementById(vsrId);
                setTimeout(function () { if (resEl) resEl.style.display = 'none'; }, 180);
            });
        }

        removeBtn.addEventListener('click', function(){
            row.remove();
            updateLineCount();
            updateSubmitState();
            updateEstTotal();
            if (document.querySelectorAll('.line-row').length === 0) {
                document.getElementById('linesBody').innerHTML = EMPTY_ROW_HTML;
            }
        });
    }

    // ── Vendor AJAX search for line rows ─────────────────────────────────────

    function doVendorSearch(row, query) {
        var lid   = row.dataset.lineId;
        var pid   = row.dataset.productId || '';
        var resEl = document.getElementById('vsr_' + lid);
        if (!resEl) return;

        var url = BASE_URL + '/inventory/adjustments/vendor-search?q=' + encodeURIComponent(query);
        if (pid) url += '&product_id=' + pid;

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                var vendors = (j && j.success) ? (j.vendors || []) : [];
                resEl.innerHTML = '';
                if (!vendors.length) { resEl.style.display = 'none'; return; }
                vendors.forEach(function (v) {
                    var div = document.createElement('div');
                    div.className = 'vs-item';
                    div.innerHTML = escHtml(v.name)
                        + (v.is_linked ? ' <span class="vs-linked-badge">linked</span>' : '');
                    div.addEventListener('mousedown', function (e) {
                        e.preventDefault();
                        row.querySelector('.vendor-input').value  = v.name;
                        row.querySelector('.vendor-hidden').value = v.id;
                        resEl.style.display = 'none';
                    });
                    resEl.appendChild(div);
                });
                resEl.style.display = 'block';
            })
            .catch(function () { resEl.style.display = 'none'; });
    }

    // Called after a product is selected to pre-load vendor suggestions
    function loadVendorsForRow(row) {
        var srcSel = row.querySelector('.source-select');
        if (srcSel && srcSel.value === 'known') {
            doVendorSearch(row, '');
        }
    }

    // ── Product AJAX search ──────────────────────────────────────────────────
    function doProductSearch(q, row, resultsEl, psInput, pidHid, vidHid, curStockEl, chosenEl) {
        var wid = document.getElementById('warehouseSelect').value || 0;
        var lid = document.getElementById('locationSelect').value  || 0;
        var url = BASE_URL + '/inventory/adjustments/product-search?q=' + encodeURIComponent(q)
            + '&warehouse_id=' + wid + '&location_id=' + lid;

        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r){ return r.json(); })
            .then(function(j){
                resultsEl.innerHTML = '';
                var list = (j && j.data) ? j.data : [];
                if (!list.length) { resultsEl.style.display = 'none'; return; }
                list.forEach(function(item){
                    var div = document.createElement('div');
                    div.className = 'ps-item';
                    div.innerHTML = '<span class="ps-name">' + escHtml(item.text) + '</span>'
                        + '<span class="ps-stock">Current: ' + parseFloat(item.current_stock || 0).toFixed(2) + '</span>';
                    div.addEventListener('mousedown', function(e){
                        e.preventDefault();
                        pidHid.value = item.product_id;
                        vidHid.value = item.variant_id || '';
                        row.dataset.productId    = item.product_id;
                        row.dataset.variantId    = item.variant_id || '';
                        row.dataset.itemKey      = item.item_key;
                        row.dataset.currentStock = item.current_stock || 0;
                        psInput.value = item.text;
                        chosenEl.textContent = '';
                        curStockEl.textContent = parseFloat(item.current_stock || 0).toFixed(2);
                        curStockEl.style.color = '#94a3b8';
                        resultsEl.style.display = 'none';
                        recomputeRow(row);
                        updateSubmitState();
                        loadVendorsForRow(row);
                        var qI = row.querySelector('.line-qty-input');
                        if (qI) qI.focus();
                    });
                    resultsEl.appendChild(div);
                });
                resultsEl.style.display = 'block';
            })
            .catch(function(){ resultsEl.style.display = 'none'; });
    }

    function refreshCurrentStock(row, itemKey) {
        var wid = document.getElementById('warehouseSelect').value || 0;
        var lid = document.getElementById('locationSelect').value  || 0;
        if (!wid || !lid) return;
        var pid = row.dataset.productId || '';
        if (!pid) return;
        var url = BASE_URL + '/inventory/adjustments/product-search?q=' + encodeURIComponent(row.querySelector('.ps-input').value || '')
            + '&warehouse_id=' + wid + '&location_id=' + lid;
        fetch(url, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'} })
            .then(function(r){ return r.json(); })
            .then(function(j){
                var list = (j && j.data) ? j.data : [];
                var found = list.find(function(i){ return i.item_key === itemKey; });
                if (found) {
                    row.dataset.currentStock = found.current_stock || 0;
                    row.querySelector('.cur-stock').textContent = parseFloat(found.current_stock||0).toFixed(2);
                    recomputeRow(row);
                }
            }).catch(function(){});
    }

    // ── Compute new balance + est value per line ─────────────────────────────
    function recomputeRow(row) {
        var cur  = parseFloat(row.dataset.currentStock) || 0;
        var qty  = parseFloat(row.querySelector('.line-qty-input').value) || 0;
        var cost = parseFloat(row.querySelector('.unit-cost-input').value) || 0;
        var hasProduct = !!row.dataset.productId;
        var newBal, sign;

        if (currentMode === 'add') { newBal = cur + qty;  sign = qty>0?'positive':'neutral'; }
        else                       { newBal = qty;        sign = newBal>cur?'positive':(newBal<cur?'negative':'neutral'); }

        var balCell = row.querySelector('.new-bal');
        balCell.className = 'new-bal ' + sign;
        balCell.textContent = (isNaN(newBal) || !hasProduct) ? '—' : newBal.toFixed(2);

        var estCell = row.querySelector('.est-val');
        if (estCell) {
            var estVal = qty * cost;
            estCell.textContent = (estVal > 0 && hasProduct) ? estVal.toFixed(2) : '—';
        }
    }

    // ── Update estimated grand total ─────────────────────────────────────────
    function updateEstTotal() {
        var total = 0;
        document.querySelectorAll('.line-row').forEach(function(row){
            var qty  = parseFloat(row.querySelector('.line-qty-input').value) || 0;
            var cost = parseFloat(row.querySelector('.unit-cost-input').value) || 0;
            total += qty * cost;
        });
        var wrap = document.getElementById('estTotalWrap');
        var el   = document.getElementById('estTotal');
        if (total > 0) {
            wrap.style.display = '';
            el.textContent = total.toFixed(2);
        } else {
            wrap.style.display = 'none';
        }
    }

    // ── Counters & submit state ──────────────────────────────────────────────
    function updateLineCount() {
        var n = document.querySelectorAll('#linesBody .line-row').length;
        document.getElementById('lineCount').textContent = n + ' line' + (n===1?'':'s');
    }

    function updateSubmitState() {
        var wid  = parseInt(document.getElementById('warehouseSelect').value) || 0;
        var lid  = parseInt(document.getElementById('locationSelect').value)  || 0;
        var rows = document.querySelectorAll('#linesBody .line-row');
        var hasValidLine = false;
        var costRequired = (currentType === 'opening_stock');
        var allCostValid = true;

        rows.forEach(function(row){
            var hasProd = !!row.dataset.productId;
            var qty     = parseFloat(row.querySelector('.line-qty-input').value) || 0;
            if (hasProd && qty > 0) hasValidLine = true;
            if (hasProd && qty > 0 && costRequired) {
                var src  = (row.querySelector('.source-select').value || '').trim();
                var cost = parseFloat(row.querySelector('.unit-cost-input').value) || 0;
                if (!src || cost <= 0) allCostValid = false;
            }
        });

        document.getElementById('submitBtn').disabled = !(wid && lid && hasValidLine && allCostValid);
    }

    // ── Add line button ──────────────────────────────────────────────────────
    document.getElementById('addLineBtn').addEventListener('click', addLineRow);

    // ── Submit ───────────────────────────────────────────────────────────────
    document.getElementById('submitBtn').addEventListener('click', async function () {
        var wid   = parseInt(document.getElementById('warehouseSelect').value) || 0;
        var lid   = parseInt(document.getElementById('locationSelect').value)  || 0;
        var notes = document.getElementById('adjNotes').value.trim();

        if (!wid || !lid) { msg('Please select a warehouse and location.', 'warning'); return; }

        var lines = [];
        var valid = true;
        var costRequired = (currentType === 'opening_stock');

        document.querySelectorAll('#linesBody .line-row').forEach(function(row){
            var pid  = parseInt(row.dataset.productId) || 0;
            var vid  = parseInt(row.dataset.variantId) || 0;
            var qty  = parseFloat(row.querySelector('.line-qty-input').value) || 0;
            var src      = (row.querySelector('.source-select').value || '').trim() || null;
            var cost     = parseFloat(row.querySelector('.unit-cost-input').value) || null;
            var vendorId = parseInt((row.querySelector('.vendor-hidden') || {}).value) || null;

            if (!pid || qty <= 0) { valid = false; return; }
            if (costRequired && (!src || cost <= 0)) { valid = false; return; }
            lines.push({
                product_id:          pid,
                variant_id:          vid || null,
                qty:                 qty,
                stock_source:        src || null,
                unit_cost:           (cost > 0) ? cost : null,
                possible_vendor_id:  vendorId,
            });
        });

        if (!valid || !lines.length) {
            msg(costRequired
                ? 'Every line needs a product, quantity, stock source, and estimated unit cost.'
                : 'Every line needs a product and a quantity greater than zero.',
                'warning');
            return;
        }

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving…';

        try {
            var resp = await fetch(BASE_URL + '/inventory/adjustments/save', {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    warehouse_id:    wid,
                    location_id:     lid,
                    mode:            currentMode,
                    adjustment_type: currentType,
                    notes:           notes || null,
                    lines:           lines,
                })
            });
            var j = await resp.json();
            if (j && j.success) {
                msg(j.message || 'Saved!', 'success');
                document.getElementById('linesBody').innerHTML = EMPTY_ROW_HTML;
                updateLineCount();
                updateSubmitState();
                updateEstTotal();
                document.getElementById('adjNotes').value = '';
                loadHistory();
            } else {
                msg((j && j.message) ? j.message : 'Save failed.', 'danger');
            }
        } catch(e) {
            msg('Network error: ' + e.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Save Stock Adjustment';
            updateSubmitState();
        }
    });

    // ── History loader ───────────────────────────────────────────────────────
    async function loadHistory(page) {
        page = page || 1;
        var container = document.getElementById('histContainer');
        container.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.78rem">Loading…</div>';
        try {
            var resp = await fetch(BASE_URL + '/inventory/adjustments/history?page=' + page + '&per_page=15', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            var j = await resp.json();
            if (!j.success || !j.data || !j.data.length) {
                container.innerHTML = '<div class="text-muted text-center py-3" style="font-size:.78rem">No adjustments recorded yet.</div>';
                return;
            }
            renderHistory(j.data, container);
        } catch(e) {
            container.innerHTML = '<div class="text-danger text-center py-3" style="font-size:.78rem">Failed to load history.</div>';
        }
    }

    function renderHistory(batches, container) {
        var typeLabel = { opening_stock: 'Opening Stock', adjustment: 'Adjustment' };
        var modeLabel = { add: '+Add', subtract: '−Remove', set: '=Set' };

        var html = '<div class="table-responsive"><table class="table table-sm table-hover align-middle" style="font-size:.78rem">';
        html += '<thead><tr>'
            + '<th style="font-size:.65rem">Date</th>'
            + '<th style="font-size:.65rem">Type</th>'
            + '<th style="font-size:.65rem">Mode</th>'
            + '<th style="font-size:.65rem">Warehouse / Location</th>'
            + '<th style="font-size:.65rem">Lines</th>'
            + '<th style="font-size:.65rem">Est. Value</th>'
            + '<th style="font-size:.65rem">Notes</th>'
            + '<th style="font-size:.65rem">By</th>'
            + '</tr></thead><tbody>';

        batches.forEach(function(b){
            var dateStr = b.created_at ? b.created_at.substring(0,16).replace('T',' ') : '—';
            var typeStr = typeLabel[b.adjustment_type] || b.adjustment_type;
            var modeStr = modeLabel[b.mode] || b.mode;
            var where   = [b.warehouse_name || '—', b.location_name || '—'].filter(Boolean).join(' / ');
            var estVal  = (b.total_estimated_value && parseFloat(b.total_estimated_value) > 0)
                        ? parseFloat(b.total_estimated_value).toFixed(2) : '—';
            var linesSummary = '';
            if (b.lines && b.lines.length) {
                linesSummary = b.lines.slice(0,3).map(function(l){
                    var nm  = l.variant_name ? (l.product_name + ' — ' + l.variant_name) : l.product_name;
                    var sign = parseFloat(l.qty_change) >= 0 ? '+' : '';
                    var costStr = (l.unit_cost && parseFloat(l.unit_cost) > 0)
                        ? ' <span style="color:#a5b4fc">@ ' + parseFloat(l.unit_cost).toFixed(2) + '</span>' : '';
                    return '<span style="color:#94a3b8">' + escHtml(nm||'?') + ' <strong style="color:#e2e8f0">' + sign + parseFloat(l.qty_change).toFixed(2) + '</strong>' + costStr + '</span>';
                }).join('<br>');
                if (b.lines.length > 3) linesSummary += '<br><span style="color:#64748b;font-size:.68rem">+' + (b.lines.length-3) + ' more</span>';
            }

            html += '<tr>'
                + '<td style="white-space:nowrap">' + escHtml(dateStr) + '</td>'
                + '<td><span class="hist-badge hist-type-' + escHtml(b.adjustment_type) + '">' + escHtml(typeStr) + '</span></td>'
                + '<td>' + escHtml(modeStr) + '</td>'
                + '<td style="font-size:.72rem">' + escHtml(where) + '</td>'
                + '<td style="max-width:220px">' + linesSummary + '</td>'
                + '<td style="font-size:.72rem;color:#a5b4fc">' + escHtml(estVal) + '</td>'
                + '<td style="font-size:.72rem;max-width:160px;color:#94a3b8">' + escHtml(b.notes || '—') + '</td>'
                + '<td style="font-size:.72rem">' + escHtml(b.user_name || '—') + '</td>'
                + '</tr>';
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    document.getElementById('refreshHistBtn').addEventListener('click', function(){ loadHistory(); });

    // ── Init ─────────────────────────────────────────────────────────────────
    refreshCostFieldVisibility();
    loadHistory();
}());
</script>
<?= $this->endSection() ?>
