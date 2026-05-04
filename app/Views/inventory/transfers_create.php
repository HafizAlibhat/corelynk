<?php /** @var array $warehouses */ /** @var array $locations */ ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>New Internal Transfer<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/* ── Page shell ──────────────────────────────── */
.itr-page { max-width:900px; margin:0 auto; }

/* ── Card sections ───────────────────────────── */
.step-card { border:1px solid rgba(99,102,241,.2); border-radius:12px; background:var(--cl-surface,#1e293b); margin-bottom:1rem; }
.step-header { display:flex; align-items:center; gap:.7rem; padding:.9rem 1.2rem; border-bottom:1px solid rgba(255,255,255,.06); }
.step-num { width:26px; height:26px; border-radius:50%; background:#6366f1; color:#fff; font-size:.78rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.step-title { font-size:.9rem; font-weight:700; color:#e2e8f0; }
.step-body { padding:1.1rem 1.2rem; }

/* ── Product search ───────────────────────────── */
.ps-wrap { position:relative; }
.ps-results {
    position:absolute; top:calc(100% + 2px); left:0; right:0; z-index:1060;
    background:#1e293b; border:1px solid #334155; border-radius:8px;
    max-height:220px; overflow:auto; box-shadow:0 6px 20px rgba(0,0,0,.5);
    display:none;
}
.ps-item { padding:.45rem .75rem; cursor:pointer; font-size:.8rem; border-bottom:1px solid rgba(255,255,255,.04); }
.ps-item:last-child { border-bottom:none; }
.ps-item:hover, .ps-item.selected { background:rgba(99,102,241,.15); }
.ps-item .ps-label { color:#e2e8f0; font-weight:600; }
.ps-item .ps-sub   { color:#64748b; font-size:.72rem; margin-top:.1rem; }

/* ── Location + stock panel ───────────────────── */
.loc-stock-badge { font-size:.7rem; font-weight:700; border-radius:6px; padding:.2rem .55rem; }
.loc-ok   { background:rgba(52,211,153,.12); color:#34d399; border:1px solid rgba(52,211,153,.25); }
.loc-low  { background:rgba(251,191,36,.12); color:#fbbf24; border:1px solid rgba(251,191,36,.25); }
.loc-zero { background:rgba(248,113,113,.12); color:#f87171; border:1px solid rgba(248,113,113,.25); }
.loc-na   { background:rgba(100,116,139,.1);  color:#64748b; border:1px solid rgba(100,116,139,.2); }

/* ── Route arrow ──────────────────────────────── */
.route-arrow { text-align:center; color:#6366f1; font-size:1.8rem; line-height:1; padding:.4rem 0; }

/* ── Reason field ─────────────────────────────── */
.reason-presets { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.6rem; }
.reason-chip {
    font-size:.72rem; font-weight:600; background:rgba(99,102,241,.1); color:#a5b4fc;
    border:1px solid rgba(99,102,241,.25); border-radius:20px; padding:.2rem .7rem;
    cursor:pointer; transition:all .15s;
}
.reason-chip:hover { background:rgba(99,102,241,.25); }

/* ── Submit ───────────────────────────────────── */
#btnSubmit { min-width:180px; }
.spinner-border-sm { width:1rem; height:1rem; border-width:.15em; }
</style>

<div class="itr-page">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="<?= base_url('/inventory/transfers') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h4 class="fw-bold mb-0"><i class="bi bi-arrow-left-right me-2" style="color:#818cf8"></i>New Internal Transfer</h4>
            <div class="text-muted small">Move stock from one location to another and record why.</div>
        </div>
    </div>

    <!-- Error banner -->
    <div id="errorBanner" class="alert alert-danger d-none mb-3" role="alert"></div>
    <div id="successBanner" class="alert alert-success d-none mb-3" role="alert"></div>

    <!-- Step 1: Product -->
    <div class="step-card">
        <div class="step-header">
            <div class="step-num">1</div>
            <div class="step-title">Select Product</div>
            <div id="selectedProductLabel" class="ms-auto text-success small fw-semibold d-none"></div>
        </div>
        <div class="step-body">
            <div class="ps-wrap">
                <input type="text" id="productSearch" class="form-control bg-transparent text-light border-secondary"
                       placeholder="Search by name, SKU or code…" autocomplete="off">
                <div class="ps-results" id="psResults"></div>
            </div>
            <input type="hidden" id="fProductId" name="product_id" value="0">
            <input type="hidden" id="fVariantId" name="variant_id" value="0">
        </div>
    </div>

    <!-- Step 2 + 3: Locations -->
    <div class="row g-3 mb-1">

        <!-- From -->
        <div class="col-md-5">
            <div class="step-card h-100">
                <div class="step-header">
                    <div class="step-num">2</div>
                    <div class="step-title">Move FROM</div>
                </div>
                <div class="step-body">
                    <label class="form-label small text-muted mb-1">Warehouse</label>
                    <select id="fromWarehouse" class="form-select form-select-sm bg-transparent text-light border-secondary mb-2">
                        <option value="">— Select warehouse —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= esc($w['id']) ?>"><?= esc($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small text-muted mb-1">Location</label>
                    <select id="fromLocation" class="form-select form-select-sm bg-transparent text-light border-secondary mb-2"
                            name="from_location_select" disabled>
                        <option value="">— Select location —</option>
                    </select>
                    <input type="hidden" id="fFromWarehouseId" name="from_warehouse_id" value="0">
                    <input type="hidden" id="fFromLocationId"  name="from_location_id"  value="0">

                    <!-- Stock at source -->
                    <div id="srcStockWrap" class="d-none mt-1">
                        <span class="text-muted small">Available: </span>
                        <span id="srcStock" class="loc-stock-badge loc-na">—</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Arrow -->
        <div class="col-md-2 d-flex align-items-center justify-content-center">
            <div class="route-arrow"><i class="bi bi-arrow-right-circle-fill"></i></div>
        </div>

        <!-- To -->
        <div class="col-md-5">
            <div class="step-card h-100">
                <div class="step-header">
                    <div class="step-num">3</div>
                    <div class="step-title">Move TO</div>
                </div>
                <div class="step-body">
                    <label class="form-label small text-muted mb-1">Warehouse</label>
                    <select id="toWarehouse" class="form-select form-select-sm bg-transparent text-light border-secondary mb-2">
                        <option value="">— Select warehouse —</option>
                        <?php foreach ($warehouses as $w): ?>
                            <option value="<?= esc($w['id']) ?>"><?= esc($w['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-label small text-muted mb-1">Location</label>
                    <select id="toLocation" class="form-select form-select-sm bg-transparent text-light border-secondary mb-2"
                            name="to_location_select" disabled>
                        <option value="">— Select location —</option>
                    </select>
                    <input type="hidden" id="fToWarehouseId" name="to_warehouse_id" value="0">
                    <input type="hidden" id="fToLocationId"  name="to_location_id"  value="0">

                    <!-- Stock at dest -->
                    <div id="dstStockWrap" class="d-none mt-1">
                        <span class="text-muted small">Current stock at destination: </span>
                        <span id="dstStock" class="loc-stock-badge loc-na">—</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Step 4: Quantity, Reason, Notes -->
    <div class="step-card mt-3">
        <div class="step-header">
            <div class="step-num">4</div>
            <div class="step-title">Quantity &amp; Reason</div>
        </div>
        <div class="step-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Quantity to Move <span class="text-danger">*</span></label>
                    <input type="number" id="fQuantity" name="quantity" class="form-control bg-transparent text-light border-secondary"
                           min="0.0001" step="any" placeholder="e.g. 50">
                    <div id="maxQtyHint" class="form-text text-muted small d-none"></div>
                </div>
                <div class="col-md-9">
                    <label class="form-label small text-muted mb-1">Reason for Transfer <span class="text-danger">*</span></label>

                    <!-- Quick preset chips -->
                    <div class="reason-presets">
                        <?php
                        $presets = [
                            'Wrong location — correcting placement',
                            'Re-organising storage',
                            'Consolidating stock',
                            'Preparing for dispatch',
                            'Quality inspection relocation',
                            'Damaged goods isolation',
                            'Space optimisation',
                        ];
                        foreach ($presets as $pr): ?>
                            <span class="reason-chip" data-val="<?= esc($pr) ?>"><?= esc($pr) ?></span>
                        <?php endforeach; ?>
                    </div>

                    <input type="text" id="fReason" name="reason" class="form-control bg-transparent text-light border-secondary"
                           placeholder="Why is this stock being moved?" maxlength="500">
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-12">
                    <label class="form-label small text-muted mb-1">Additional Notes <span class="text-muted">(optional)</span></label>
                    <textarea id="fNotes" name="notes" rows="2" class="form-control bg-transparent text-light border-secondary"
                              placeholder="Any extra details about this move…" maxlength="1000"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit -->
    <div class="d-flex justify-content-end gap-2 mt-3 mb-5">
        <a href="<?= base_url('/inventory/transfers') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="button" id="btnSubmit" class="btn btn-primary">
            <i class="bi bi-check2-circle me-1"></i>Confirm Transfer
        </button>
    </div>

</div>

<script>
(function () {
    'use strict';

    const BASE = '<?= base_url() ?>';

    // ── All locations JSON for cascading ───────────────────────────────────
    const allLocations = <?= json_encode(array_values($locations)) ?>;

    // ── Helpers ───────────────────────────────────────────────────────────
    function populateLocationSelect(sel, warehouseId) {
        sel.innerHTML = '<option value="">— Select location —</option>';
        const filtered = allLocations.filter(l => !warehouseId || l.warehouse_id == warehouseId);
        filtered.forEach(l => {
            const o = document.createElement('option');
            o.value = l.id;
            o.textContent = l.name;
            sel.appendChild(o);
        });
        sel.disabled = (filtered.length === 0);
    }

    function setHidden(id, val) { document.getElementById(id).value = val; }

    let debounceTimer;
    function debounce(fn, ms) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(fn, ms);
    }

    // ── Product search ─────────────────────────────────────────────────────
    const searchInput = document.getElementById('productSearch');
    const psResults   = document.getElementById('psResults');
    const selectedLabel = document.getElementById('selectedProductLabel');
    let currentProduct = { product_id: 0, variant_id: 0, label: '' };

    searchInput.addEventListener('input', () => {
        const q = searchInput.value.trim();
        if (q.length < 2) { psResults.style.display = 'none'; return; }
        debounce(() => {
            fetch(`${BASE}/inventory/transfers/product-search?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(json => {
                    psResults.innerHTML = '';
                    if (!json.data || !json.data.length) {
                        psResults.innerHTML = '<div class="ps-item text-muted">No products found.</div>';
                    } else {
                        json.data.forEach(item => {
                            if (item.type === 'parent') return; // skip variable product parents
                            const div = document.createElement('div');
                            div.className = 'ps-item';
                            div.innerHTML  = `<div class="ps-label">${esc(item.label)}</div>`;
                            if (item.sub) div.innerHTML += `<div class="ps-sub">${esc(item.sub)}</div>`;
                            div.addEventListener('mousedown', e => {
                                e.preventDefault();
                                selectProduct(item);
                            });
                            psResults.appendChild(div);
                        });
                    }
                    psResults.style.display = 'block';
                })
                .catch(() => { psResults.style.display = 'none'; });
        }, 280);
    });

    searchInput.addEventListener('blur', () => setTimeout(() => { psResults.style.display = 'none'; }, 200));

    function selectProduct(item) {
        currentProduct = item;
        searchInput.value = item.label;
        setHidden('fProductId', item.product_id);
        setHidden('fVariantId', item.variant_id || 0);
        psResults.style.display = 'none';
        selectedLabel.textContent = '✓ ' + item.label;
        selectedLabel.classList.remove('d-none');
        refreshSourceStock();
        refreshDestStock();
    }

    function esc(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Warehouse → Location cascading ────────────────────────────────────
    const fromWarehouseEl = document.getElementById('fromWarehouse');
    const fromLocationEl  = document.getElementById('fromLocation');
    const toWarehouseEl   = document.getElementById('toWarehouse');
    const toLocationEl    = document.getElementById('toLocation');

    fromWarehouseEl.addEventListener('change', () => {
        const wid = fromWarehouseEl.value;
        setHidden('fFromWarehouseId', wid);
        populateLocationSelect(fromLocationEl, wid);
        setHidden('fFromLocationId', 0);
        document.getElementById('srcStockWrap').classList.add('d-none');
    });

    fromLocationEl.addEventListener('change', () => {
        setHidden('fFromLocationId', fromLocationEl.value);
        refreshSourceStock();
    });

    toWarehouseEl.addEventListener('change', () => {
        const wid = toWarehouseEl.value;
        setHidden('fToWarehouseId', wid);
        populateLocationSelect(toLocationEl, wid);
        setHidden('fToLocationId', 0);
        document.getElementById('dstStockWrap').classList.add('d-none');
    });

    toLocationEl.addEventListener('change', () => {
        setHidden('fToLocationId', toLocationEl.value);
        refreshDestStock();
    });

    // ── Stock query helpers ─────────────────────────────────────────────────
    function fetchStock(productId, variantId, warehouseId, locationId, callback) {
        if (!productId || !locationId) { callback(null); return; }
        const url = `${BASE}/inventory/transfers/location-stock?product_id=${productId}&variant_id=${variantId}&warehouse_id=${warehouseId}&location_id=${locationId}`;
        fetch(url).then(r => r.json()).then(j => callback(j.quantity ?? 0)).catch(() => callback(null));
    }

    function renderStockBadge(el, qty) {
        el.textContent = qty === null ? '—' : (parseFloat(qty).toFixed(2) + ' units');
        el.className = 'loc-stock-badge ' + (qty === null ? 'loc-na' : qty <= 0 ? 'loc-zero' : qty < 10 ? 'loc-low' : 'loc-ok');
    }

    let srcQtyAvailable = 0;

    function refreshSourceStock() {
        const pid  = parseInt(document.getElementById('fProductId').value) || 0;
        const vid  = parseInt(document.getElementById('fVariantId').value) || 0;
        const wid  = fromWarehouseEl.value;
        const lid  = fromLocationEl.value;
        if (!pid || !lid) return;
        document.getElementById('srcStockWrap').classList.remove('d-none');
        fetchStock(pid, vid, wid, lid, qty => {
            srcQtyAvailable = parseFloat(qty) || 0;
            renderStockBadge(document.getElementById('srcStock'), qty);
            const hint = document.getElementById('maxQtyHint');
            if (qty !== null) {
                hint.textContent = 'Max: ' + srcQtyAvailable.toFixed(4) + ' available at source';
                hint.classList.remove('d-none');
                document.getElementById('fQuantity').max = srcQtyAvailable;
            } else {
                hint.classList.add('d-none');
            }
        });
    }

    function refreshDestStock() {
        const pid = parseInt(document.getElementById('fProductId').value) || 0;
        const vid = parseInt(document.getElementById('fVariantId').value) || 0;
        const wid = toWarehouseEl.value;
        const lid = toLocationEl.value;
        if (!pid || !lid) return;
        document.getElementById('dstStockWrap').classList.remove('d-none');
        fetchStock(pid, vid, wid, lid, qty => {
            renderStockBadge(document.getElementById('dstStock'), qty);
        });
    }

    // ── Reason quick chips ─────────────────────────────────────────────────
    document.querySelectorAll('.reason-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            document.getElementById('fReason').value = chip.dataset.val;
        });
    });

    // ── Submit ─────────────────────────────────────────────────────────────
    document.getElementById('btnSubmit').addEventListener('click', () => {
        const pid     = parseInt(document.getElementById('fProductId').value) || 0;
        const fromLoc = parseInt(document.getElementById('fFromLocationId').value) || 0;
        const toLoc   = parseInt(document.getElementById('fToLocationId').value) || 0;
        const qty     = parseFloat(document.getElementById('fQuantity').value) || 0;
        const reason  = document.getElementById('fReason').value.trim();

        const banner = document.getElementById('errorBanner');
        banner.classList.add('d-none');

        if (!pid)    { showError('Please select a product.'); return; }
        if (!fromLoc){ showError('Please select the source location.'); return; }
        if (!toLoc)  { showError('Please select the destination location.'); return; }
        if (qty <= 0){ showError('Please enter a valid quantity greater than zero.'); return; }
        if (!reason) { showError('Please enter a reason for this transfer.'); return; }

        if (fromLoc === toLoc
            && document.getElementById('fFromWarehouseId').value === document.getElementById('fToWarehouseId').value) {
            showError('Source and destination locations must be different.');
            return;
        }

        if (srcQtyAvailable > 0 && qty > srcQtyAvailable + 0.0001) {
            showError(`Quantity exceeds available stock at source (${srcQtyAvailable.toFixed(4)} available).`);
            return;
        }

        const btn = document.getElementById('btnSubmit');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing…';

        const payload = new FormData();
        payload.append('product_id',       pid);
        payload.append('variant_id',       document.getElementById('fVariantId').value);
        payload.append('from_warehouse_id',document.getElementById('fFromWarehouseId').value);
        payload.append('from_location_id', fromLoc);
        payload.append('to_warehouse_id',  document.getElementById('fToWarehouseId').value);
        payload.append('to_location_id',   toLoc);
        payload.append('quantity',         qty);
        payload.append('reason',           reason);
        payload.append('notes',            document.getElementById('fNotes').value.trim());

        fetch('<?= base_url('/inventory/transfers/store') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: payload,
        })
        .then(r => r.json())
        .then(json => {
            if (json.success) {
                const sbanner = document.getElementById('successBanner');
                sbanner.textContent = json.message || 'Transfer completed successfully!';
                sbanner.classList.remove('d-none');
                setTimeout(() => { window.location.href = json.redirect; }, 900);
            } else {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirm Transfer';
                showError(json.message || 'Transfer failed. Please try again.');
            }
        })
        .catch(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Confirm Transfer';
            showError('Network error. Please try again.');
        });
    });

    function showError(msg) {
        const banner = document.getElementById('errorBanner');
        banner.textContent = msg;
        banner.classList.remove('d-none');
        banner.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

})();
</script>
<?= $this->endSection() ?>
