<?php /** Quick Stock Adjustment Modal — shared partial for Products & Variants list views */ ?>

<!-- ═══ Quick Stock Adjustment Modal ════════════════════════════════════════ -->
<div class="modal fade" id="qadjModal" tabindex="-1" aria-labelledby="qadjModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:var(--cl-surface,#1e2d44);border:1px solid #334155">

            <!-- Header -->
            <div class="modal-header" style="border-bottom:1px solid #334155;padding:.65rem 1rem">
                <div>
                    <h6 class="modal-title mb-0" id="qadjModalLabel" style="font-size:.85rem;font-weight:700;color:var(--cl-text-primary,#e2e8f0)">
                        <i class="bi bi-clipboard2-plus text-info me-1"></i>Quick Stock Adjustment
                    </h6>
                    <div id="qadjProductDisplay" style="font-size:.72rem;color:#94a3b8;margin-top:.1rem"></div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <!-- Body -->
            <div class="modal-body" style="padding:.85rem 1rem">

                <!-- ── Mode selection ── -->
                <div class="mb-3">
                    <div style="font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:.4rem">What are you doing?</div>
                    <div class="d-flex flex-wrap gap-2" id="qadjModeGroup">
                        <button type="button" class="qadj-mode-btn selected" data-mode="add" data-type="opening_stock">
                            <span class="qmb-icon">📦</span><span>Opening / Found Stock</span>
                        </button>
                        <button type="button" class="qadj-mode-btn" data-mode="set" data-type="adjustment">
                            <span class="qmb-icon">🎯</span><span>Set Exact Qty</span>
                        </button>
                    </div>
                </div>

                <!-- ── Warehouse + Location ── -->
                <div class="row g-2 mb-2">
                    <div class="col-md-6">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">Warehouse <span class="text-danger">*</span></label>
                        <select id="qadjWarehouse" class="form-select form-select-sm">
                            <option value="">— Loading…</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">Location <span class="text-danger">*</span></label>
                        <select id="qadjLocation" class="form-select form-select-sm" disabled>
                            <option value="">— Select warehouse first —</option>
                        </select>
                    </div>
                </div>

                <!-- ── Current stock display ── -->
                <div class="mb-2 d-flex align-items-center gap-2">
                    <span style="font-size:.74rem;color:#94a3b8">Current On-Hand:</span>
                    <strong id="qadjCurStock" style="font-size:.82rem;color:var(--cl-text-primary,#e2e8f0)">—</strong>
                    <span id="qadjCurSpinner" class="spinner-border spinner-border-sm text-info"
                          style="display:none;width:.75rem;height:.75rem;border-width:.15em" aria-hidden="true"></span>
                </div>

                <!-- ── Possible Vendor ── -->
                <div class="row g-2 mb-2" id="qadjVendorRow" style="display:none">
                    <div class="col-12">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">
                            Possible Vendor <em style="font-size:.68rem;font-weight:400;color:#64748b">(optional — who may have supplied this stock)</em>
                        </label>
                        <div style="position:relative">
                            <input type="text" id="qadjVendorInput" class="form-control form-control-sm"
                                   placeholder="Search vendor…" autocomplete="off">
                            <div id="qadjVendorResults"
                                 style="position:absolute;top:100%;left:0;z-index:1070;
                                        background:var(--cl-surface,#1e2d44);border:1px solid #334155;
                                        border-radius:6px;max-height:180px;overflow:auto;
                                        display:none;box-shadow:0 4px 15px rgba(0,0,0,.4);min-width:200px"></div>
                            <input type="hidden" id="qadjVendorId" value="">
                        </div>
                    </div>
                </div>

                <!-- ── Source + Cost + Qty + Notes ── -->
                <div class="row g-2 mb-2">
                    <div class="col-sm-3" id="qadjSourceCol">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">
                            Stock Source <span class="text-danger qadj-req-star">*</span>
                        </label>
                        <select id="qadjSource" class="form-select form-select-sm">
                            <option value="">— Select source —</option>
                            <option value="old_stock">No Vendor Trail</option>
                            <option value="purchased_no_record">Purchased — No PO on File</option>
                            <option value="known">From a Vendor</option>
                        </select>
                    </div>
                    <div class="col-sm-3" id="qadjCostCol">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">
                            Est. Unit Cost <span class="text-danger qadj-req-star">*</span>
                        </label>
                        <input type="number" id="qadjCost" class="form-control form-control-sm" min="0" step="any" placeholder="0.00">
                    </div>
                    <div class="col-sm-2">
                        <label class="form-label" id="qadjQtyLabel" style="font-size:.74rem;font-weight:600">
                            Qty to Add <span class="text-danger">*</span>
                        </label>
                        <input type="number" id="qadjQty" class="form-control form-control-sm" min="0" step="any" placeholder="0">
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label" style="font-size:.74rem;font-weight:600;color:#94a3b8">
                            Notes <em style="font-weight:400">(optional)</em>
                        </label>
                        <input type="text" id="qadjNotes" class="form-control form-control-sm" placeholder="Reason / reference…">
                    </div>
                </div>

                <!-- ── Mandatory reason when setting exact qty to zero ── -->
                <div class="row g-2 mb-2" id="qadjZeroReasonWrap" style="display:none">
                    <div class="col-md-4">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">
                            Why set stock to zero? <span class="text-danger">*</span>
                        </label>
                        <select id="qadjZeroReason" class="form-select form-select-sm">
                            <option value="">— Select reason —</option>
                            <option value="damaged">Damaged / Defective</option>
                            <option value="expired">Expired / Obsolete</option>
                            <option value="missing">Missing / Lost</option>
                            <option value="count_correction">Physical Count Correction</option>
                            <option value="qc_reject">Rejected by QC</option>
                            <option value="custom">Other (custom reason)</option>
                        </select>
                    </div>
                    <div class="col-md-8" id="qadjZeroReasonCustomWrap" style="display:none">
                        <label class="form-label" style="font-size:.74rem;font-weight:600">
                            Custom reason <span class="text-danger">*</span>
                        </label>
                        <input type="text" id="qadjZeroReasonCustom" class="form-control form-control-sm" maxlength="250" placeholder="Write why stock is being set to zero…">
                    </div>
                </div>

                <!-- ── Preview bar ── -->
                <div class="d-flex align-items-center gap-3 px-2 py-2"
                     style="background:rgba(99,102,241,.07);border-radius:7px;border:1px solid rgba(99,102,241,.15);font-size:.78rem;flex-wrap:wrap">
                    <span style="color:#94a3b8">Est. Value:</span>
                    <strong id="qadjEstVal" style="color:#a5b4fc">—</strong>
                    <span style="color:#475569">│</span>
                    <span style="color:#94a3b8">New Balance:</span>
                    <strong id="qadjNewBal" style="color:var(--cl-text-primary,#e2e8f0)">—</strong>
                </div>
            </div>

            <!-- Footer -->
            <div class="modal-footer" style="border-top:1px solid #334155;padding:.5rem 1rem;gap:.4rem">
                <div id="qadjMsg" style="flex:1;font-size:.78rem"></div>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-success" id="qadjSaveBtn" disabled>
                    <i class="bi bi-check2-circle me-1"></i>Save Adjustment
                </button>
            </div>

        </div>
    </div>
</div>

<style>
.qadj-mode-btn {
    display: inline-flex; align-items: center; gap: .35rem;
    padding: .3rem .7rem; border-radius: 7px;
    border: 2px solid rgba(51,65,85,.7); background: rgba(30,41,59,.6);
    color: #94a3b8; font-size: .74rem; font-weight: 600; cursor: pointer;
    transition: all .13s;
}
.qadj-mode-btn:hover   { border-color: #6366f1; color: #e2e8f0; }
.qadj-mode-btn.selected { border-color: #6366f1; background: rgba(99,102,241,.12); color: #a5b4fc; }
.qmb-icon { font-size: .88rem; }
</style>

<script>
(function () {
    'use strict';

    var BASE    = (window.APP_BASE || '').replace(/\/$/, '');
    var _wh     = null;   // cached warehouses + locations payload
    var _mode   = 'add';
    var _type   = 'opening_stock';
    var _state  = { productId: 0, variantId: null, currentStock: 0 };
    var _bsMod  = null;

    function getBsModal() {
        if (!_bsMod) _bsMod = new bootstrap.Modal(document.getElementById('qadjModal'));
        return _bsMod;
    }

    /* ── Public entry-point called by list-view action buttons ─────────────── */
    window.qadjOpen = function (productId, variantId, displayName, isTemplate) {
        if (isTemplate) {
            // Variable products — send user to the full adjustments page where they can pick variants
            window.location.href = BASE + '/inventory/adjustments';
            return;
        }
        _state.productId    = parseInt(productId)  || 0;
        _state.variantId    = variantId ? parseInt(variantId) : null;
        _state.currentStock = 0;
        document.getElementById('qadjProductDisplay').textContent = displayName || '';
        resetForm();
        getBsModal().show();
        loadWarehouses();
        // Vendors loaded on-demand when user picks “From a Vendor” as source
    };

    /* ── Reset all fields ──────────────────────────────────────────────────── */
    function resetForm() {
        selectMode(document.querySelector('.qadj-mode-btn[data-mode="add"][data-type="opening_stock"]'));
        var wSel = document.getElementById('qadjWarehouse');
        var lSel = document.getElementById('qadjLocation');
        wSel.innerHTML = '<option value="">— Loading\u2026</option>';
        lSel.innerHTML = '<option value="">— Select warehouse first —</option>';
        lSel.disabled  = true;
        document.getElementById('qadjCurStock').textContent  = '—';
        document.getElementById('qadjVendorInput').value   = '';
        document.getElementById('qadjVendorId').value      = '';
        document.getElementById('qadjVendorResults').style.display = 'none';
        document.getElementById('qadjVendorRow').style.display = 'none';
        document.getElementById('qadjQty').value    = '';
        document.getElementById('qadjCost').value   = '';
        document.getElementById('qadjSource').value = '';
        document.getElementById('qadjNotes').value  = '';
        document.getElementById('qadjZeroReason').value = '';
        document.getElementById('qadjZeroReasonCustom').value = '';
        document.getElementById('qadjZeroReasonWrap').style.display = 'none';
        document.getElementById('qadjZeroReasonCustomWrap').style.display = 'none';
        document.getElementById('qadjMsg').innerHTML = '';
        document.getElementById('qadjSaveBtn').disabled = true;
        document.getElementById('qadjSaveBtn').innerHTML =
            '<i class="bi bi-check2-circle me-1"></i>Save Adjustment';
        recompute();
    }

    /* ── Load warehouses & locations (single fetch, then cached) ───────────── */
    function loadWarehouses() {
        if (_wh) { populateWarehouses(_wh); return; }
        fetch(BASE + '/inventory/adjustments/warehouses', {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (j) { if (j && j.success) { _wh = j; populateWarehouses(_wh); } })
        .catch(function () {});
    }

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

    function populateWarehouses(data) {
        var sel = document.getElementById('qadjWarehouse');
        sel.innerHTML = '<option value="">— Select warehouse —</option>';
        (data.warehouses || []).forEach(function (w) {
            var o = document.createElement('option');
            o.value = w.id; o.textContent = w.name;
            sel.appendChild(o);
        });
        // Auto-select when only one warehouse
        if ((data.warehouses || []).length === 1) {
            sel.value = data.warehouses[0].id;
            sel.dispatchEvent(new Event('change'));
        }
    }

    document.getElementById('qadjWarehouse').addEventListener('change', function () {
        var wid  = parseInt(this.value) || 0;
        var lSel = document.getElementById('qadjLocation');
        lSel.innerHTML = '<option value="">— Select location —</option>';
        if (!wid || !_wh) { lSel.disabled = true; refreshCurStock(); return; }
        var opts = buildLocOpts(_wh.locations || [], wid);
        opts.forEach(function (o) {
            var el = document.createElement('option');
            el.value = o.id; el.textContent = o.label;
            lSel.appendChild(el);
        });
        lSel.disabled = opts.length === 0;
        if (opts.length === 1) { lSel.value = opts[0].id; lSel.dispatchEvent(new Event('change')); }
        updateSaveState();
    });

    document.getElementById('qadjLocation').addEventListener('change', function () {
        refreshCurStock();
        updateSaveState();
    });

    /* ── Fetch current on-hand balance for the selected item + location ──────── */
    function refreshCurStock() {
        var wid = parseInt(document.getElementById('qadjWarehouse').value) || 0;
        var lid = parseInt(document.getElementById('qadjLocation').value)  || 0;
        var el  = document.getElementById('qadjCurStock');
        var sp  = document.getElementById('qadjCurSpinner');
        if (!wid || !lid || !_state.productId) {
            el.textContent = '—'; _state.currentStock = 0; recompute(); return;
        }
        sp.style.display = 'inline-block';
        var url = BASE + '/inventory/adjustments/stock-balance'
            + '?product_id='   + _state.productId
            + '&warehouse_id=' + wid
            + '&location_id='  + lid;
        if (_state.variantId) url += '&variant_id=' + _state.variantId;
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            sp.style.display = 'none';
            var qty = (j && j.success) ? parseFloat(j.qty) : 0;
            _state.currentStock = isNaN(qty) ? 0 : qty;
            el.textContent = _state.currentStock.toFixed(2);
            recompute(); updateSaveState();
        })
        .catch(function () { sp.style.display = 'none'; });
    }

    /* ── Mode chip selection ───────────────────────────────────────────────── */
    document.getElementById('qadjModeGroup').addEventListener('click', function (e) {
        var btn = e.target.closest('.qadj-mode-btn');
        if (btn) selectMode(btn);
    });

    function selectMode(btn) {
        if (!btn) return;
        document.querySelectorAll('.qadj-mode-btn').forEach(function (b) { b.classList.remove('selected'); });
        btn.classList.add('selected');
        _mode = btn.dataset.mode;
        _type = btn.dataset.type;

        var lblMap = { add: 'Qty to Add', set: 'Set Exact Qty' };
        document.getElementById('qadjQtyLabel').innerHTML =
            (lblMap[_mode] || 'Quantity') + ' <span class="text-danger">*</span>';

        // In set mode, zero is valid target. In add mode, quantity must stay > 0.
        var qtyInput = document.getElementById('qadjQty');
        qtyInput.min = _mode === 'set' ? '0' : '0.0001';

        var isRequired = (_type === 'opening_stock');
        document.getElementById('qadjSourceCol').style.display = '';
        document.getElementById('qadjCostCol').style.display   = '';
        document.querySelectorAll('.qadj-req-star').forEach(function (s) {
            s.style.display = isRequired ? '' : 'none';
        });
        recompute(); updateSaveState();
    }

    /* ── Live preview (est value + new balance) ────────────────────────────── */
    ['qadjQty', 'qadjCost', 'qadjSource'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input',  function () { recompute(); updateSaveState(); });
            el.addEventListener('change', function () { recompute(); updateSaveState(); });
        }
    });

    document.getElementById('qadjZeroReason').addEventListener('change', function () {
        document.getElementById('qadjZeroReasonCustomWrap').style.display = this.value === 'custom' ? '' : 'none';
        updateSaveState();
    });
    document.getElementById('qadjZeroReasonCustom').addEventListener('input', updateSaveState);

    // Source change drives vendor row visibility
    document.getElementById('qadjSource').addEventListener('change', function () {
        var isKnown = this.value === 'known';
        document.getElementById('qadjVendorRow').style.display = isKnown ? '' : 'none';
        if (isKnown) {
            loadVendorsForModal('');
        } else {
            document.getElementById('qadjVendorInput').value = '';
            document.getElementById('qadjVendorId').value    = '';
            document.getElementById('qadjVendorResults').style.display = 'none';
        }
    });

    /* ── Vendor search (modal) ─────────────────────────────────────────────── */
    var _vendorDebounce = null;

    function loadVendorsForModal(query) {
        var resEl = document.getElementById('qadjVendorResults');
        var url   = BASE + '/inventory/adjustments/vendor-search?q=' + encodeURIComponent(query || '');
        if (_state.productId) url += '&product_id=' + _state.productId;
        fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
            var vendors = (j && j.success) ? (j.vendors || []) : [];
            resEl.innerHTML = '';
            if (!vendors.length) { resEl.style.display = 'none'; return; }
            vendors.forEach(function (v) {
                var div = document.createElement('div');
                div.style.cssText = 'padding:.38rem .65rem;cursor:pointer;font-size:.78rem;border-bottom:1px solid rgba(255,255,255,.04);display:flex;align-items:center;gap:.4rem;color:var(--cl-text-primary,#e2e8f0)';
                div.innerHTML = escQ(v.name)
                    + (v.is_linked ? ' <span style="font-size:.62rem;background:rgba(52,211,153,.15);color:#34d399;border-radius:3px;padding:.05rem .3rem">linked</span>' : '');
                div.addEventListener('mouseover', function () { this.style.background = 'rgba(99,102,241,.15)'; });
                div.addEventListener('mouseout',  function () { this.style.background = ''; });
                div.addEventListener('mousedown', function (e) {
                    e.preventDefault();
                    document.getElementById('qadjVendorInput').value = v.name;
                    document.getElementById('qadjVendorId').value    = v.id;
                    resEl.style.display = 'none';
                });
                resEl.appendChild(div);
            });
            resEl.style.display = 'block';
        })
        .catch(function () { resEl.style.display = 'none'; });
    }

    document.getElementById('qadjVendorInput').addEventListener('input', function () {
        document.getElementById('qadjVendorId').value = '';
        clearTimeout(_vendorDebounce);
        var q = this.value.trim();
        _vendorDebounce = setTimeout(function () { loadVendorsForModal(q); }, 250);
    });
    document.getElementById('qadjVendorInput').addEventListener('focus', function () {
        if (!document.getElementById('qadjVendorId').value) {
            loadVendorsForModal(this.value.trim());
        }
    });
    document.getElementById('qadjVendorInput').addEventListener('blur', function () {
        setTimeout(function () {
            document.getElementById('qadjVendorResults').style.display = 'none';
        }, 180);
    });

    function recompute() {
        var cur    = _state.currentStock;
        var qty    = parseFloat(document.getElementById('qadjQty').value)  || 0;
        var cst    = parseFloat(document.getElementById('qadjCost').value) || 0;
        var newBal = _mode === 'add' ? cur + qty : qty;
        var estVal = (qty > 0 && cst > 0) ? (qty * cst).toFixed(2) : '—';
        var balCol = newBal < 0 ? '#f87171' : (newBal > cur + 0.0001 ? '#34d399' : 'var(--cl-text-primary,#e2e8f0)');
        document.getElementById('qadjEstVal').textContent = estVal;
        document.getElementById('qadjNewBal').textContent = _state.productId ? newBal.toFixed(2) : '—';
        document.getElementById('qadjNewBal').style.color = balCol;

        // Show mandatory reason controls only when set exact qty is 0.
        var needZeroReason = (_mode === 'set' && Math.abs(qty) < 0.000001);
        document.getElementById('qadjZeroReasonWrap').style.display = needZeroReason ? '' : 'none';
        if (!needZeroReason) {
            document.getElementById('qadjZeroReason').value = '';
            document.getElementById('qadjZeroReasonCustom').value = '';
            document.getElementById('qadjZeroReasonCustomWrap').style.display = 'none';
        }
    }

    /* ── Enable/disable Save button ────────────────────────────────────────── */
    function updateSaveState() {
        var wid  = parseInt(document.getElementById('qadjWarehouse').value) || 0;
        var lid  = parseInt(document.getElementById('qadjLocation').value)  || 0;
        var qty  = parseFloat(document.getElementById('qadjQty').value) || 0;
        var src  = (document.getElementById('qadjSource').value || '').trim();
        var cst  = parseFloat(document.getElementById('qadjCost').value) || 0;
        var zeroReason = (document.getElementById('qadjZeroReason').value || '').trim();
        var zeroReasonCustom = (document.getElementById('qadjZeroReasonCustom').value || '').trim();
        var need = (_type === 'opening_stock');
        var qtyOk = _mode === 'set' ? qty >= 0 : qty > 0;
        var noNegative = qty >= 0;
        var needZeroReason = (_mode === 'set' && qty === 0);
        var zeroReasonOk = !needZeroReason || (zeroReason !== '' && (zeroReason !== 'custom' || zeroReasonCustom !== ''));
        var ok   = wid > 0 && lid > 0 && qtyOk && noNegative && (!need || (src !== '' && cst > 0)) && zeroReasonOk;
        document.getElementById('qadjSaveBtn').disabled = !ok;
    }

    /* ── Save ──────────────────────────────────────────────────────────────── */
    document.getElementById('qadjSaveBtn').addEventListener('click', async function () {
        var wid   = parseInt(document.getElementById('qadjWarehouse').value) || 0;
        var lid   = parseInt(document.getElementById('qadjLocation').value)  || 0;
        var qty   = parseFloat(document.getElementById('qadjQty').value) || 0;
        var cst   = parseFloat(document.getElementById('qadjCost').value) || 0;
        var src   = (document.getElementById('qadjSource').value || '').trim() || null;
        var notes = (document.getElementById('qadjNotes').value || '').trim() || null;
        var zeroReason = (document.getElementById('qadjZeroReason').value || '').trim() || null;
        var zeroReasonCustom = (document.getElementById('qadjZeroReasonCustom').value || '').trim() || null;
        var csrf  = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

        if (qty < 0) {
            document.getElementById('qadjMsg').innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Negative quantity is not allowed.</span>';
            return;
        }
        if (_mode === 'set' && qty === 0) {
            if (!zeroReason) {
                document.getElementById('qadjMsg').innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Please select a reason for setting quantity to zero.</span>';
                return;
            }
            if (zeroReason === 'custom' && !zeroReasonCustom) {
                document.getElementById('qadjMsg').innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>Please enter custom reason.</span>';
                return;
            }
        }

        var btn = this;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving\u2026';
        document.getElementById('qadjMsg').innerHTML = '';

        try {
            var resp = await fetch(BASE + '/inventory/adjustments/save', {
                method: 'POST',
                headers: {
                    'Content-Type':     'application/json',
                    'Accept':           'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN':     csrf,
                },
                body: JSON.stringify({
                    warehouse_id:    wid,
                    location_id:     lid,
                    mode:            _mode,
                    adjustment_type: _type,
                    notes:           notes,
                    lines: [{
                        product_id:          _state.productId,
                        variant_id:          _state.variantId || null,
                        qty:                 qty,
                        unit_cost:           cst > 0 ? cst : null,
                        stock_source:        src,
                        possible_vendor_id:  parseInt(document.getElementById('qadjVendorId').value) || null,
                        zero_reason_code:    zeroReason,
                        zero_reason_custom:  zeroReasonCustom,
                    }]
                })
            });
            var j = await resp.json();

            if (j && j.success) {
                document.getElementById('qadjMsg').innerHTML =
                    '<span class="text-success"><i class="bi bi-check-circle me-1"></i>' +
                    escQ(j.message || 'Saved!') + '</span>';
                setTimeout(function () { getBsModal().hide(); location.reload(); }, 1300);
            } else {
                document.getElementById('qadjMsg').innerHTML =
                    '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>' +
                    escQ((j && j.message) || 'Save failed.') + '</span>';
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Save Adjustment';
            }
        } catch (e) {
            document.getElementById('qadjMsg').innerHTML =
                '<span class="text-danger">Network error: ' + escQ(e.message) + '</span>';
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2-circle me-1"></i>Save Adjustment';
        }
    });

    function escQ(s) { var d = document.createElement('div'); d.textContent = String(s || ''); return d.innerHTML; }

    // Clean up on close
    document.getElementById('qadjModal').addEventListener('hidden.bs.modal', function () {
        document.getElementById('qadjMsg').innerHTML = '';
        document.getElementById('qadjSaveBtn').disabled = true;
        document.getElementById('qadjSaveBtn').innerHTML =
            '<i class="bi bi-check2-circle me-1"></i>Save Adjustment';
    });

}());
</script>
