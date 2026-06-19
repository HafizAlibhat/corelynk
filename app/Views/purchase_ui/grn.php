<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Goods Receipt Notes
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="card grn-create-card">
  <div class="card-header section-header d-flex justify-content-between align-items-center">
    <div>
      <h3 class="section-title">Create GRN</h3>
      <div class="section-sub">Receive goods for confirmed purchase orders</div>
    </div>
  </div>
  <div class="card-body">
    <div id="messages"></div>

    <form id="grnForm" class="mb-3">
      <div class="row g-3 mb-3" id="poSelectionRow">
        <div class="col-lg-6">
          <label class="form-label">Find PO</label>
          <input id="poSearch" class="form-control" type="text" placeholder="Search by PO number or vendor" autocomplete="off" />
          <div id="poResults" class="list-group mt-2" style="max-height: 200px; overflow-y: auto; display:none;"></div>
          <input type="hidden" name="po_id" id="poId" />
          <div class="small text-muted mt-2" id="poMeta"></div>
        </div>
        <div class="col-lg-3">
          <label class="form-label">GRN Number</label>
          <input id="grnNumberDisplay" class="form-control" value="Loading..." readonly />
        </div>
        <div class="col-lg-3" id="vendorFilterCol">
          <label class="form-label">Vendor (filter)</label>
          <input name="vendor_id" type="number" class="form-control" placeholder="Optional" />
        </div>
      </div>

      <div class="row g-3 mb-3">
        <div class="col-lg-4">
          <label class="form-label">Received Date <span class="text-danger">*</span></label>
          <input type="date" name="received_at" id="receivedAt" class="form-control" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="col-lg-4">
          <label class="form-label">Warehouse <span class="text-danger">*</span></label>
          <select name="warehouse_id" id="warehouseSelect" class="form-select" required></select>
        </div>
        <div class="col-lg-4">
          <label class="form-label">Location <span class="text-danger">*</span></label>
          <div class="d-flex gap-2">
            <select name="location_id" id="locationSelect" class="form-select" required></select>
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#locationModal" title="Create new location">
              <i class="bi bi-plus-lg"></i>
            </button>
          </div>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mb-2 mt-1">
        <h6 class="mb-0 fw-semibold grn-lines-title">Lines to Receive</h6>
      </div>
      <div class="table-responsive mb-2 grn-lines-wrap">
        <table class="table table-sm align-middle" id="grnLinesTable">
          <thead class="table-light">
            <tr style="white-space:nowrap;">
              <th style="width:8%">Code</th>
              <th style="width:5%">Image</th>
              <th style="width:22%">Product / Description</th>
              <th style="width:5%">Unit</th>
              <th style="width:6%" class="text-end">Ordered</th>
              <th style="width:6%" class="text-end">Received</th>
              <th style="width:6%" class="text-end">Pending</th>
              <th style="width:10%">Receive Now</th>
              <th style="width:10%">Location</th>
              <th style="width:8%">Tax</th>
            </tr>
          </thead>
          <tbody id="grnLinesBody"></tbody>
        </table>
      </div>
      <div class="small text-muted mb-2 grn-lines-help">Default receive qty equals pending. You can receive extra quantity when vendor ships over PO.</div>

      <div id="overReceiptPanel" class="over-panel border rounded p-3 mb-3" style="display:none;">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i class="bi bi-exclamation-triangle-fill" style="color:#facc15;"></i>
          <span class="fw-semibold" style="color:#dbeafe;">Extra Quantity Detected</span>
        </div>
        <div class="small" style="color:#bfdbfe;">Some lines exceed their PO pending qty. Click <strong>Set Reason</strong> on each highlighted line to specify why extra goods are being received. Payable reasons will auto-create a draft adjustment bill.</div>
      </div>

      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="closePo" name="close_po" value="1" />
        <label class="form-check-label" for="closePo">Close PO and cancel backorder</label>
      </div>

      <button type="submit" class="btn btn-primary grn-submit-btn">
        <i class="bi bi-check-circle me-2"></i>Create GRN
      </button>
    </form>

    <!-- Location Modal -->
    <div class="modal fade" id="locationModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header bg-light border-bottom">
            <h5 class="modal-title">Create New Location</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="locWarehouseId" />
            <div class="mb-3">
              <label class="form-label fw-semibold">Location Name <span class="text-danger">*</span></label>
              <input id="locName" class="form-control" placeholder="e.g., Shelf A1, Rack B2" />
            </div>
            <div class="mb-3">
              <label class="form-label">Parent Location</label>
              <select id="locParent" class="form-select"></select>
              <small class="text-muted d-block mt-1">Optional: Select a parent location for hierarchical organization</small>
            </div>
            <div id="locModalMsg" class="small text-muted"></div>
          </div>
          <div class="modal-footer border-top">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-primary" id="saveLocationBtn">Save Location</button>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Over-Receipt Reason Modal -->
<div class="modal fade" id="overReasonModal" tabindex="-1" aria-labelledby="overReasonModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" style="max-width:480px;">
    <div class="modal-content" style="border:1px solid rgba(148,163,184,.2);box-shadow:0 20px 60px rgba(2,6,23,.6);">
      <div class="modal-header" style="background:linear-gradient(180deg,#0f1d36,#091529);border-bottom:1px solid rgba(148,163,184,.2);">
        <div>
          <div style="font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#93c5fd;margin-bottom:.18rem;">Over-Receipt Reason</div>
          <h5 class="modal-title mb-0" id="overReasonModalLabel" style="font-size:1rem;color:#e2e8f0;">Why is extra quantity being received?</h5>
        </div>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="background:rgba(15,23,42,.85);padding:1.25rem;">
        <div id="overReasonProductInfo" class="mb-3 p-2" style="border:1px solid rgba(148,163,184,.18);border-radius:.55rem;background:rgba(15,23,42,.55);"></div>

        <div class="mb-3 p-2" style="border:1px solid rgba(148,163,184,.16);border-radius:.55rem;background:rgba(15,23,42,.42);">
          <div class="small mb-2" style="color:#93c5fd;font-weight:700;">Split Extra Quantity (same product)</div>
          <div class="row g-2">
            <div class="col-6">
              <label class="form-label" style="font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;font-weight:700;">Payable Qty</label>
              <input type="number" id="overReasonPayableQty" class="form-control form-control-sm" min="0" step="0.01" style="border-color:rgba(148,163,184,.25);background:rgba(15,23,42,.6);color:#e2e8f0;">
            </div>
            <div class="col-6">
              <label class="form-label" style="font-size:.7rem;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;font-weight:700;">Free Qty</label>
              <input type="number" id="overReasonFreeQty" class="form-control form-control-sm" min="0" step="0.01" style="border-color:rgba(148,163,184,.25);background:rgba(15,23,42,.6);color:#e2e8f0;">
            </div>
          </div>
          <div id="overReasonSplitHint" class="small mt-2" style="color:#64748b;"></div>
        </div>

        <div class="mb-1">
          <label class="form-label" style="font-size:.72rem;letter-spacing:.06em;text-transform:uppercase;color:#94a3b8;font-weight:700;">Details <span style="color:#64748b;font-weight:400;">(optional)</span></label>
          <input type="text" id="overReasonDetailsInput" class="form-control" placeholder="e.g. supplier reference, reason from vendor invoice..." style="border-color:rgba(148,163,184,.25);background:rgba(15,23,42,.6);color:#e2e8f0;">
        </div>
      </div>
      <div class="modal-footer" style="background:rgba(15,23,42,.85);border-top:1px solid rgba(148,163,184,.2);gap:.5rem;">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary btn-sm fw-bold" id="overReasonSaveBtn" style="min-width:100px;"><i class="bi bi-check-lg me-1"></i>Save Reason</button>
      </div>
    </div>
  </div>
</div>

<script src="<?= base_url('assets/js/corelynk_autocomplete.js') ?>"></script>
<style>
  .grn-create-card {
    border: 1px solid rgba(148, 163, 184, .2);
    box-shadow: 0 10px 30px rgba(2, 6, 23, .22);
    overflow: hidden;
  }

  .grn-create-card .card-header {
    background: linear-gradient(180deg, rgba(15, 23, 42, .55), rgba(15, 23, 42, .2));
    border-bottom: 1px solid rgba(148, 163, 184, .2);
  }

  .grn-create-card .form-label {
    font-size: .74rem;
    letter-spacing: .04em;
    text-transform: uppercase;
    color: #94a3b8;
    font-weight: 700;
    margin-bottom: .35rem;
  }

  .grn-create-card .form-control,
  .grn-create-card .form-select {
    border-color: rgba(148, 163, 184, .25);
    background: rgba(15, 23, 42, .45);
    color: #e2e8f0;
  }

  .grn-create-card .form-control:focus,
  .grn-create-card .form-select:focus {
    border-color: #3b82f6;
    box-shadow: 0 0 0 .2rem rgba(59, 130, 246, .2);
  }

  .grn-lines-wrap { overflow-x: auto; }
  #grnLinesTable { min-width: 1120px; }
  #grnLinesTable th,
  #grnLinesTable td { vertical-align: top; }

  #grnLinesTable thead th {
    background: linear-gradient(180deg, #0f1d36 0%, #0b172d 100%);
    color: #93c5fd;
    font-size: .68rem;
    letter-spacing: .08em;
    text-transform: uppercase;
    border-bottom: 1px solid rgba(59, 130, 246, .45);
    padding-top: .7rem;
    padding-bottom: .7rem;
    position: sticky;
    top: 0;
    z-index: 2;
  }

  #grnLinesTable tbody tr {
    border-bottom: 1px solid rgba(148, 163, 184, .16);
  }

  #grnLinesTable tbody tr:hover {
    background: rgba(59, 130, 246, .06);
  }

  .grn-lines-title {
    letter-spacing: .02em;
    color: #cbd5e1;
  }

  .grn-lines-help {
    color: #94a3b8 !important;
  }

  .line-code {
    display: inline-flex;
    align-items: center;
    padding: .2rem .5rem;
    border-radius: .45rem;
    background: rgba(15, 23, 42, .55);
    border: 1px solid rgba(148, 163, 184, .28);
    font-size: .72rem;
    color: #cbd5e1;
    font-weight: 700;
    white-space: nowrap;
  }

  .line-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: .45rem;
    border: 1px solid rgba(148, 163, 184, .3);
    box-shadow: 0 4px 12px rgba(2, 6, 23, .25);
  }

  .line-product-name {
    font-weight: 700;
    color: #e2e8f0;
    line-height: 1.25;
    margin-bottom: .12rem;
  }

  .line-product-desc {
    font-size: .8rem;
    color: #94a3b8;
    line-height: 1.3;
  }

  .line-uom,
  .line-num,
  .line-tax {
    font-size: .82rem;
    color: #cbd5e1;
  }

  .line-num {
    font-weight: 700;
  }

  .qty-recv {
    max-width: 120px;
    font-weight: 700;
  }

  .over-msg {
    font-size: .74rem;
    color: #facc15 !important;
    font-weight: 700;
  }

  .line-reason-badge {
    display: inline-flex;
    align-items: center;
    gap: .3rem;
    margin-top: .45rem;
    padding: .25rem .55rem;
    border-radius: .45rem;
    font-size: .7rem;
    font-weight: 700;
    cursor: pointer;
    transition: filter .15s;
  }
  .line-reason-badge:hover { filter: brightness(1.15); }
  .line-reason-badge.payable {
    background: rgba(30, 58, 138, .35);
    border: 1px solid rgba(96, 165, 250, .45);
    color: #93c5fd;
  }
  .line-reason-badge.free {
    background: rgba(120, 80, 0, .28);
    border: 1px solid rgba(251, 191, 36, .45);
    color: #fcd34d;
  }
  .line-reason-badge.mixed {
    background: rgba(8, 60, 38, .3);
    border: 1px solid rgba(45, 212, 191, .45);
    color: #5eead4;
  }
  .line-reason-btn {
    display: inline-flex;
    align-items: center;
    gap: .28rem;
    margin-top: .45rem;
    padding: .26rem .55rem;
    border-radius: .45rem;
    font-size: .7rem;
    font-weight: 700;
    border: 1px solid rgba(249, 115, 22, .5);
    background: rgba(120, 40, 0, .22);
    color: #fb923c;
    cursor: pointer;
    transition: background .15s, border-color .15s;
    white-space: nowrap;
  }
  .line-reason-btn:hover {
    background: rgba(249, 115, 22, .25);
    border-color: #fb923c;
  }

  .over-panel {
    border-color: rgba(59, 130, 246, .35) !important;
    background: rgba(30, 58, 138, .14);
  }

  .over-panel .fw-semibold {
    color: #dbeafe;
  }

  .over-panel .text-muted {
    color: #bfdbfe !important;
  }

  .grn-submit-btn {
    min-width: 138px;
    font-weight: 700;
  }

  @media (max-width: 1100px) {
    #grnLinesTable { min-width: 980px; }
    .line-thumb { width: 40px; height: 40px; }
  }

  @media (max-width: 768px) {
    #grnLinesTable { min-width: 860px; }
    .grn-create-card .card-body { padding: .9rem; }
  }
</style>
<script>
(() => {
  const messages = document.getElementById('messages');
  const grnLinesBody = document.getElementById('grnLinesBody');
  const poIdInput = document.getElementById('poId');
  const poSearch = document.getElementById('poSearch');
  const poResults = document.getElementById('poResults');
  const warehouseSelect = document.getElementById('warehouseSelect');
  const locationSelect = document.getElementById('locationSelect');
  const poMeta = document.getElementById('poMeta');
  const locParent = document.getElementById('locParent');
  const locModalMsg = document.getElementById('locModalMsg');
  const locName = document.getElementById('locName');
  const saveLocationBtn = document.getElementById('saveLocationBtn');
  const vendorInput = document.querySelector('[name="vendor_id"]');
  const grnNumberDisplay = document.getElementById('grnNumberDisplay');
  const receivedAt = document.getElementById('receivedAt');
  const overReceiptPanel = document.getElementById('overReceiptPanel');

  // Over-receipt reason modal — lazy-initialized when first needed
  // (Bootstrap JS loads after content section, so we cannot instantiate at startup)
  let _overReasonModalInstance = null;
  function getOverReasonModal() {
    if (!_overReasonModalInstance) {
      _overReasonModalInstance = new bootstrap.Modal(document.getElementById('overReasonModal'));
    }
    return _overReasonModalInstance;
  }
  const overReasonPayableQty = document.getElementById('overReasonPayableQty');
  const overReasonFreeQty = document.getElementById('overReasonFreeQty');
  const overReasonSplitHint = document.getElementById('overReasonSplitHint');
  const overReasonDetailsInput = document.getElementById('overReasonDetailsInput');
  const overReasonSaveBtn = document.getElementById('overReasonSaveBtn');
  const overReasonProductInfo = document.getElementById('overReasonProductInfo');
  let _overReasonTargetRow = null;
  let _overReasonExtraQty = 0;

  const REASON_LABELS = {
    vendor_extra: 'Vendor sent extra pcs',
    extra_ordered: 'Extra ordered after PO',
    replacement_free: 'Free replacement',
    mixed: 'Split (payable + free)'
  };
  const PAYABLE_REASONS = new Set(['vendor_extra', 'extra_ordered']);

  function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function n2(v) {
    const n = parseFloat(v);
    return Number.isFinite(n) ? n : 0;
  }

  function fmtQty(v) {
    return Number(v || 0).toFixed(2);
  }

  function syncSplitHint() {
    const pay = Math.max(0, n2(overReasonPayableQty.value));
    const free = Math.max(0, n2(overReasonFreeQty.value));
    const total = pay + free;
    const diff = _overReasonExtraQty - total;
    if (Math.abs(diff) <= 0.009) {
      overReasonSplitHint.textContent = `Total matched: ${fmtQty(pay)} payable + ${fmtQty(free)} free = ${fmtQty(_overReasonExtraQty)} extra`;
      overReasonSplitHint.style.color = '#86efac';
    } else {
      overReasonSplitHint.textContent = `Total must equal extra qty (${fmtQty(_overReasonExtraQty)}). Current split = ${fmtQty(total)}.`;
      overReasonSplitHint.style.color = '#fca5a5';
    }

  }

  overReasonPayableQty.addEventListener('input', () => {
    // Don't overwrite the active field — let user type freely
    // Only mirror into free qty so split hint stays live
    const raw = n2(overReasonPayableQty.value);
    if (!isNaN(raw) && raw >= 0 && raw <= _overReasonExtraQty) {
      overReasonFreeQty.value = fmtQty(Math.max(0, _overReasonExtraQty - raw));
    }
    syncSplitHint();
  });

  overReasonPayableQty.addEventListener('blur', () => {
    let pay = Math.min(_overReasonExtraQty, Math.max(0, n2(overReasonPayableQty.value) || 0));
    overReasonPayableQty.value = fmtQty(pay);
    overReasonFreeQty.value = fmtQty(Math.max(0, _overReasonExtraQty - pay));
    syncSplitHint();
  });

  overReasonFreeQty.addEventListener('input', () => {
    // Don't overwrite the active field — let user type freely
    const raw = n2(overReasonFreeQty.value);
    if (!isNaN(raw) && raw >= 0 && raw <= _overReasonExtraQty) {
      overReasonPayableQty.value = fmtQty(Math.max(0, _overReasonExtraQty - raw));
    }
    syncSplitHint();
  });

  overReasonFreeQty.addEventListener('blur', () => {
    let free = Math.min(_overReasonExtraQty, Math.max(0, n2(overReasonFreeQty.value) || 0));
    overReasonFreeQty.value = fmtQty(free);
    overReasonPayableQty.value = fmtQty(Math.max(0, _overReasonExtraQty - free));
    syncSplitHint();
  });

  function getRowSplitDefaults(tr, extraQty) {
    let payableQty = Math.max(0, n2(tr.dataset.overPayableQty));
    let freeQty = Math.max(0, n2(tr.dataset.overFreeQty));
    let payableReasonType = (tr.dataset.overPayableReasonType || '').trim();
    const existingReason = (tr.dataset.overReasonType || '').trim();

    if (payableQty <= 0 && freeQty <= 0) {
      if (PAYABLE_REASONS.has(existingReason)) {
        payableQty = extraQty;
        freeQty = 0;
        payableReasonType = existingReason;
      } else if (existingReason === 'replacement_free') {
        payableQty = 0;
        freeQty = extraQty;
      } else {
        payableQty = extraQty;
        freeQty = 0;
        payableReasonType = payableReasonType || 'vendor_extra';
      }
    }

    const sum = payableQty + freeQty;
    if (Math.abs(sum - extraQty) > 0.009) {
      if (sum <= 0) {
        payableQty = extraQty;
        freeQty = 0;
      } else {
        const ratio = extraQty / sum;
        payableQty = payableQty * ratio;
        freeQty = Math.max(0, extraQty - payableQty);
      }
    }

    if (payableQty <= 0.0001) {
      payableReasonType = '';
    } else if (!PAYABLE_REASONS.has(payableReasonType)) {
      payableReasonType = 'vendor_extra';
    }

    return { payableQty, freeQty, payableReasonType };
  }

  function openOverReasonModal(tr, prodName, extraQty) {
    _overReasonTargetRow = tr;
    _overReasonExtraQty = Math.max(0, n2(extraQty));

    const existing = getRowSplitDefaults(tr, _overReasonExtraQty);
    overReasonPayableQty.value = fmtQty(existing.payableQty);
    overReasonFreeQty.value = fmtQty(existing.freeQty);
    overReasonDetailsInput.value = tr.dataset.overReasonDetails || '';

    overReasonProductInfo.innerHTML =
      `<div style="font-size:.78rem;color:#94a3b8;margin-bottom:.18rem;">Product</div>` +
      `<div style="font-weight:700;color:#e2e8f0;">${escHtml(prodName)}</div>` +
      `<div style="margin-top:.28rem;font-size:.8rem;color:#f97316;font-weight:700;">+${fmtQty(_overReasonExtraQty)} units above PO pending</div>`;

    syncSplitHint();
    getOverReasonModal().show();
  }

  overReasonSaveBtn.addEventListener('click', () => {
    if (!_overReasonTargetRow) return;

    const payableQty = Math.max(0, n2(overReasonPayableQty.value));
    const freeQty = Math.max(0, n2(overReasonFreeQty.value));
    const total = payableQty + freeQty;
    if (Math.abs(total - _overReasonExtraQty) > 0.009) {
      overReasonSplitHint.style.color = '#fca5a5';
      overReasonSplitHint.textContent = `Split mismatch. Must equal ${fmtQty(_overReasonExtraQty)} extra qty.`;
      return;
    }

    const details = overReasonDetailsInput.value.trim();
    // Payable reason is automatic: vendor_extra whenever payable qty > 0
    const autoPayableReason = payableQty > 0.0001 ? 'vendor_extra' : '';
    const resolvedReasonType = payableQty > 0.0001
      ? (freeQty > 0.0001 ? 'mixed' : 'vendor_extra')
      : 'replacement_free';

    _overReasonTargetRow.dataset.overReasonType = resolvedReasonType;
    _overReasonTargetRow.dataset.overReasonDetails = details;
    _overReasonTargetRow.dataset.overPayableQty = fmtQty(payableQty);
    _overReasonTargetRow.dataset.overFreeQty = fmtQty(freeQty);
    _overReasonTargetRow.dataset.overPayableReasonType = autoPayableReason;

    updateLineBadge(_overReasonTargetRow);
    getOverReasonModal().hide();
    refreshOverReceiptPanel();
  });

  function updateLineBadge(tr) {
    const badgeSlot = tr.querySelector('.line-reason-slot');
    if (!badgeSlot) return;

    const reason = tr.dataset.overReasonType || '';
    const details = tr.dataset.overReasonDetails || '';
    const payQty = Math.max(0, n2(tr.dataset.overPayableQty));
    const freeQty = Math.max(0, n2(tr.dataset.overFreeQty));
    const qtyEl = tr.querySelector('.qty-recv');
    const isOver = qtyEl && (parseFloat(qtyEl.value||'0') > parseFloat(qtyEl.dataset.pending||'0') + 0.0001);
    if (!isOver) {
      badgeSlot.innerHTML = '';
      return;
    }

    if ((payQty + freeQty) > 0.0001) {
      let cls = 'payable';
      let icon = 'bi-receipt';
      let text = `Pay ${fmtQty(payQty)} + Free ${fmtQty(freeQty)}`;

      if (payQty > 0.0001 && freeQty > 0.0001) {
        cls = 'mixed';
        icon = 'bi-diagram-3';
        text = `Split: Pay ${fmtQty(payQty)} / Free ${fmtQty(freeQty)}`;
      } else if (payQty <= 0.0001 && freeQty > 0.0001) {
        cls = 'free';
        icon = 'bi-gift';
        text = `Free replacement ${fmtQty(freeQty)}`;
      } else if (payQty > 0.0001) {
        cls = 'payable';
        icon = 'bi-receipt';
        const pType = tr.dataset.overPayableReasonType || reason;
        text = `${REASON_LABELS[pType] || 'Payable extra'} ${fmtQty(payQty)}`;
      }

      const detailText = details ? ` · <em>${escHtml(details.slice(0,32))}${details.length>32?'...':''}</em>` : '';
      badgeSlot.innerHTML =
        `<span class="line-reason-badge ${cls}" title="Click to change split" data-reason-trigger>`
        + `<i class="bi ${icon}"></i>${escHtml(text)}${detailText}</span>`;
      return;
    }

    const label = REASON_LABELS[reason] || null;
    if (label) {
      const cls = PAYABLE_REASONS.has(reason) ? 'payable' : 'free';
      const icon = PAYABLE_REASONS.has(reason) ? 'bi-receipt' : 'bi-gift';
      const detailText = details ? ` · <em>${escHtml(details.slice(0,32))}${details.length>32?'...':''}</em>` : '';
      badgeSlot.innerHTML =
        `<span class="line-reason-badge ${cls}" title="Click to change split" data-reason-trigger>`
        + `<i class="bi ${icon}"></i>${escHtml(label)}${detailText}</span>`;
    } else {
      badgeSlot.innerHTML =
        `<button type="button" class="line-reason-btn" data-reason-trigger>`
        + `<i class="bi bi-exclamation-circle"></i>Set split reason</button>`;
    }
  }
  let eligiblePOs = [];
  const variantCache = new Map();

  function getLocationLabel(loc){
    if (!loc) return '';
    return loc.display_name || loc.location_path || loc.name || '';
  }

  function hasAnyOverReceipt(){
    let hasOver = false;
    grnLinesBody.querySelectorAll('tr').forEach((tr)=>{
      const qtyEl = tr.querySelector('.qty-recv');
      if (!qtyEl) return;
      const val = parseFloat(qtyEl.value || '0');
      const pend = parseFloat(qtyEl.dataset.pending || '0');
      if (val > pend + 0.0001) hasOver = true;
    });
    return hasOver;
  }

  function refreshOverReceiptPanel(){
    overReceiptPanel.style.display = hasAnyOverReceipt() ? '' : 'none';
  }

  function handleQtyChange(tr, qtyInput) {
    const val = parseFloat(qtyInput.value || '0');
    const pend = parseFloat(qtyInput.dataset.pending || '0');
    const isOver = val > pend + 0.0001;
    const extra = Math.max(0, val - pend);
    const overMsg = tr.querySelector('.over-msg');
    if (overMsg) {
      overMsg.style.display = isOver ? '' : 'none';
    }
    if (!isOver) {
      delete tr.dataset.overReasonType;
      delete tr.dataset.overReasonDetails;
      delete tr.dataset.overPayableQty;
      delete tr.dataset.overFreeQty;
      delete tr.dataset.overPayableReasonType;
    }
    updateLineBadge(tr);
    refreshOverReceiptPanel();
    if (isOver && !tr.dataset.overReasonType) {
      const prodName = tr.querySelector('.line-product-name')?.textContent || 'Product';
      openOverReasonModal(tr, prodName, extra);
    }
  }

  function flash(msgText, cls='info'){ messages.innerHTML = `<div class="alert alert-${cls}">${msgText}</div>`; }

  async function loadWarehouses(){
    try {
      const resp = await fetch('<?= site_url("inventory/warehouses") ?>', { headers:{'Accept':'application/json'} });
      const j = await resp.json();
      warehouseSelect.innerHTML = '<option value="">-- Select warehouse --</option>';
      (j.data||[]).forEach(w=>{
        const opt = document.createElement('option'); opt.value = w.id; opt.textContent = w.name + (w.is_active ? '' : ' (inactive)');
        warehouseSelect.appendChild(opt);
      });
    } catch(e){ flash('Failed to load warehouses: '+e.message,'danger'); }
  }

  async function loadLocations(){
    try {
      const whId = warehouseSelect.value || '';
      if (!whId) { locationSelect.innerHTML = '<option value="">-- Select warehouse first --</option>'; locParent.innerHTML='<option value="">(none)</option>'; return; }
      const url = new URL('<?= site_url("new-purchase-grns/locations") ?>', window.location.origin);
      url.searchParams.set('warehouse_id', whId);
      const resp = await fetch(url.toString(), { headers:{'Accept':'application/json'} });
      const text = await resp.text();
      let j; try { j = JSON.parse(text); } catch(err) { throw new Error('Locations response not JSON: '+text.slice(0,120)); }
      locationSelect.innerHTML = '<option value="">-- Select location --</option>';
      locParent.innerHTML = '<option value="">(none)</option>';

      const rows = j.data || [];
      rows.forEach(l=>{
        const label = getLocationLabel(l);
        const opt = document.createElement('option'); opt.value = l.id; opt.textContent = label; locationSelect.appendChild(opt);
        const opt2 = document.createElement('option'); opt2.value = l.id; opt2.textContent = label; locParent.appendChild(opt2);
      });
      
      // Also refresh location dropdowns in each product line
      updateLineLocationOptions(rows);
    } catch (e) { flash('Failed to load locations: '+e.message, 'danger'); }
  }

  function updateLineLocationOptions(locations) {
    const lineLocationSelects = document.querySelectorAll('.product-location');
    lineLocationSelects.forEach(select => {
      const currentValue = select.value;
      select.innerHTML = '<option value="">Use header location</option>';
      locations.forEach(loc => {
        const locLabel = getLocationLabel(loc);
        const opt = document.createElement('option');
        opt.value = loc.id;
        opt.textContent = locLabel;
        opt.title = locLabel;
        select.appendChild(opt);
      });
      select.value = currentValue;
    });
  }

  async function loadNextGrnNumber(){
    try {
      const resp = await fetch('<?= site_url("new-purchase-grns/next-number") ?>', { headers:{'Accept':'application/json'} });
      const text = await resp.text();
      let j; try { j = JSON.parse(text); } catch(err) { throw new Error('Non-JSON: '+text.slice(0,120)); }
      const fallback = 'GRN-000001';
      const num = (j && j.grn_number) ? j.grn_number : fallback;
      grnNumberDisplay.value = num;
      if (!j || !j.success || !j.grn_number) {
        console.warn('GRN number fallback used', j);
      }
    } catch(e){
      grnNumberDisplay.value = 'Auto-generated';
      flash('Could not fetch next GRN number: '+e.message, 'warning');
    }
  }

  async function loadVariants(productId){
    const pid = parseInt(productId||'0',10);
    if (!pid) return [];
    if (variantCache.has(pid)) return variantCache.get(pid);
    try {
      const resp = await fetch('<?= site_url("new-purchase-grns/product-variants/") ?>'+pid, { headers:{'Accept':'application/json'} });
      const j = await resp.json();
      const rows = (j && j.success && Array.isArray(j.data)) ? j.data : [];
      variantCache.set(pid, rows);
      return rows;
    } catch (e) {
      console.warn('Failed to load variants for product', pid, e);
      variantCache.set(pid, []);
      return [];
    }
  }

  async function renderLines(lines){
    grnLinesBody.innerHTML = '';
    if (!lines || !lines.length) {
      grnLinesBody.innerHTML = '<tr><td colspan="10" class="text-center text-muted py-4">Select a PO to load lines.</td></tr>';
      return;
    }
    
    // Load warehouse locations once for all rows
    let allLocations = [];
    try {
      const whId = warehouseSelect.value || '';
      if (whId) {
        const url = new URL('<?= site_url("new-purchase-grns/locations") ?>', window.location.origin);
        url.searchParams.set('warehouse_id', whId);
        const resp = await fetch(url.toString(), { headers:{'Accept':'application/json'} });
        const j = await resp.json();
        allLocations = j.data || [];
      }
    } catch (e) {
      console.warn('Failed to preload locations:', e);
    }
    
    const list = await Promise.all(lines.map(async (ln)=>{
      const pid = ln.product_id || '';
      const isVariable = (ln.product_type || '').toLowerCase() === 'variable';
      const variants = isVariable ? await loadVariants(pid) : [];
      return { ln, isVariable, variants };
    }));

    list.forEach(({ ln, isVariable, variants }, idx)=>{
      const pending = Number(ln.pending_qty ?? 0).toFixed(2);
      const tr = document.createElement('tr');
      
      // Product/variant image with proper fallback chain
      let img = '<?= base_url('assets/images/no-image.png') ?>';
      
      // Priority: variant image > product image > product images array > no-image
      if (ln.variant_image && ln.variant_image.trim()) {
        img = '<?= base_url('/uploads/variants/') ?>' + ln.variant_image.replace(/^\//,'');
      } else if (ln.product_image && ln.product_image.trim()) {
        img = '<?= base_url('/uploads/products/') ?>' + ln.product_image.replace(/^\//,'');
      } else if (ln.product_images) {
        try {
          const imgs = typeof ln.product_images === 'string' ? JSON.parse(ln.product_images) : ln.product_images;
          if (Array.isArray(imgs) && imgs.length > 0 && imgs[0] && imgs[0].trim()) {
            img = '<?= base_url('/uploads/products/') ?>' + imgs[0].replace(/^\//,'');
          }
        } catch (e) {
          console.warn('Failed to parse product_images', e);
        }
      }
      
      // Product/variant code with proper fallback
      const code = ln.variant_art_number || ln.product_code || ln.product_sku || ln.product_id || '';
      console.log('Line code:', {variant_art_number: ln.variant_art_number, product_code: ln.product_code, variant_id: ln.variant_id, product_variant_id: ln.product_variant_id, final_code: code});
      
      // Product name and description (include variant info if present)
      const prodName = ln.product_name || 'Product';
      const variantText = [ln.variant_art_number, ln.variant_name].filter(Boolean).join(' ');
      let lineDesc = ln.description || '';
      if (variantText) {
        lineDesc = lineDesc ? `${variantText} • ${lineDesc}` : variantText;
      }
      
      // Build location dropdown HTML
      let locationHtml = '<select class="form-select form-select-sm product-location" name="lines['+idx+'][location_id]"><option value="">Use header location</option>';
      if (allLocations.length > 0) {
        allLocations.forEach(loc => {
          const locLabel = getLocationLabel(loc);
          locationHtml += `<option value="${loc.id}">${locLabel}</option>`;
        });
      }
      locationHtml += '</select>';
      
      tr.innerHTML = `
        <td>
          <span class="line-code">${code || '—'}</span>
        </td>
        <td>
          <img src="${img}" alt="" class="line-thumb js-product-hover-thumb" data-preview-src="${img}"
            onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.setAttribute('data-preview-src','<?= base_url('assets/images/no-image.png') ?>')">
        </td>
        <td>
          <div class="line-product-name">${prodName}</div>
          ${lineDesc ? `<div class="line-product-desc">${lineDesc}</div>` : ''}
          <div class="line-reason-slot"></div>
          <input type="hidden" name="lines[${idx}][po_line_id]" value="${ln.id||''}" />
          <input type="hidden" name="lines[${idx}][product_id]" value="${ln.product_id||''}" />
          <input type="hidden" name="lines[${idx}][unit_price]" value="${ln.unit_price ? Number(ln.unit_price).toFixed(2) : (ln.unit_cost ? Number(ln.unit_cost).toFixed(2) : '')}" />
          <input type="hidden" name="lines[${idx}][variant_id]" value="${ln.product_variant_id||ln.variant_id||''}" />
        </td>
        <td><span class="line-uom">${ln.unit || 'pcs'}</span></td>
        <td class="text-end"><span class="line-num">${Number(ln.ordered_qty??0).toFixed(2)}</span></td>
        <td class="text-end"><span class="line-num">${Number(ln.received_qty??0).toFixed(2)}</span></td>
        <td class="text-end"><span class="line-num">${pending}</span></td>
        <td>
          <input class="form-control form-control-sm qty-recv" data-pending="${pending}" name="lines[${idx}][qty_received]" type="number" step="0.01" min="0" value="${pending > 0 ? pending : 0}" />
          <div class="over-msg mt-1" style="display:none;font-size:.7rem;font-weight:700;color:#facc15;"><i class="bi bi-arrow-up-circle"></i> Extra qty</div>
        </td>
        <td>
          ${locationHtml}
          <small class="text-muted d-block mt-1">Leave blank to use header location.</small>
        </td>
        <td><span class="line-tax">${Number(ln.tax_percent ?? 0).toFixed(2)}%</span></td>
      `;
      
      const qtyInput = tr.querySelector('.qty-recv');
      qtyInput.addEventListener('change', () => handleQtyChange(tr, qtyInput));
      qtyInput.addEventListener('blur',   () => handleQtyChange(tr, qtyInput));

      tr.addEventListener('click', ev => {
        if (ev.target.closest('[data-reason-trigger]')) {
          const prodName = tr.querySelector('.line-product-name')?.textContent || 'Product';
          const val   = parseFloat(qtyInput.value || '0');
          const pend  = parseFloat(qtyInput.dataset.pending || '0');
          const extra = Math.max(0, val - pend);
          openOverReasonModal(tr, prodName, extra);
        }
      });

      grnLinesBody.appendChild(tr);
    });
    refreshOverReceiptPanel();
  }

  function renderPoList(query=''){
    poResults.style.display = 'block';
    poResults.innerHTML = '';
    const q = query.trim().toLowerCase();
    const rows = eligiblePOs.filter(p=>{
      if (!q) return true;
      const num = String(p.po_number || p.id || '').toLowerCase();
      const ven = (p.vendor_name || '').toLowerCase();
      return num.includes(q) || ven.includes(q);
    });
    if (!rows.length) {
      poResults.innerHTML = '<div class="list-group-item text-muted">No matching purchase orders</div>';
      poIdInput.value = '';
      poMeta.textContent = '';
      return;
    }
    rows.forEach(p=>{
      const pending = p.pending_qty ?? 0;
      const item = document.createElement('button');
      item.type = 'button';
      item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';
      item.innerHTML = `
        <div>
          <div class="fw-semibold">PO-${p.po_number || p.id}</div>
          <div class="text-muted small">${p.vendor_name || 'Vendor'}</div>
        </div>
        <span class="badge bg-primary rounded-pill">${pending}</span>
      `;
      item.addEventListener('click', ()=>{
        poIdInput.value = p.id;
        poMeta.textContent = `PO-${p.po_number || p.id} | ${p.vendor_name || ''} | Pending: ${pending}`;
        loadPoLines(p.id);
        poResults.style.display = 'none';
      });
      poResults.appendChild(item);
    });
  }

  async function loadEligiblePOs(){
    poResults.style.display = 'block';
    poResults.innerHTML = '<div class="list-group-item text-muted">Loading POs...</div>';
    try {
      const vendorId = vendorInput.value || '';
      const url = new URL('<?= site_url("new-purchase-grns/eligible-pos") ?>', window.location.origin);
      if (vendorId) url.searchParams.set('vendor_id', vendorId);
      const resp = await fetch(url.toString(), { headers:{'Accept':'application/json'} });
      const j = await resp.json();
      eligiblePOs = (j.success && Array.isArray(j.data)) ? j.data : [];
      if (!eligiblePOs.length) { 
        poResults.innerHTML = '<div class="list-group-item text-muted">No pending POs found</div>'; 
        return; 
      }
      renderPoList(poSearch.value || '');
    } catch (e) {
      poResults.innerHTML = '<div class="list-group-item text-danger">Failed to load POs</div>';
      flash('Failed to load POs: '+e.message, 'danger');
    }
  }

  async function loadPoLines(poId){
    if (!poId) { renderLines([]); return; }
    try {
      // Use pending-lines endpoint to show only items not yet fully received
      // This supports continuation receipts when creating multiple GRNs for same PO
      const resp = await fetch('<?= site_url("new-purchase-grns/pending-lines/") ?>?po_id='+poId, { headers:{'Accept':'application/json'} });
      const raw = await resp.text();
      let j = null;
      try {
        j = raw ? JSON.parse(raw) : null;
      } catch (_) {
        throw new Error(`HTTP ${resp.status}: ${raw.slice(0, 180).replace(/\s+/g, ' ').trim() || 'Invalid JSON response'}`);
      }
      if (!resp.ok || !j || !j.success) throw new Error(j?.message || j?.error || `HTTP ${resp.status}: Failed to load PO lines`);
      console.log('Loaded pending PO lines:', j.lines);  // Debug: check what data is returned
      
      // Map response format to match po_lines format for backward compatibility
      const lines = j.lines.map(line => ({
        ...line,
        id: line.po_line_id,
        ordered_qty: line.ordered_qty,
        received_qty: line.already_received_qty,
        pending_qty: line.pending_qty
      }));
      
      await renderLines(lines);
      if (j.po && j.lines && j.lines.length) {
        const vendorId = vendorInput.value || '';
        const match = eligiblePOs.find(p=>String(p.id) === String(poId));
        if (match) {
          const pending = match.pending_qty ?? 0;
          poMeta.textContent = `PO-${match.po_number || poId} | ${match.vendor_name || vendorId || match.vendor_id || ''} | Pending items: ${j.total_pending_items} | Total qty: ${j.total_pending_qty}`;
        } else {
          poMeta.textContent = `PO #${poId} | Pending items: ${j.total_pending_items} | Total qty: ${j.total_pending_qty}`;
        }
      }
    } catch (e) { flash('Failed to load PO lines: '+e.message, 'danger'); renderLines([]); poMeta.textContent=''; }
  }

  // Auto-load from query param po_id for PO-screen entry
  (function prefillFromQuery(){
    const params = new URLSearchParams(window.location.search);
    const poId = params.get('po_id');
    if (poId) {
      poIdInput.value = poId;
      poMeta.textContent = `PO ${poId}`;
      const poRow = document.getElementById('poSelectionRow');
      const vendorCol = document.getElementById('vendorFilterCol');
      if (poRow) poRow.style.display = 'none';
      if (vendorCol) vendorCol.style.display = 'none';
      loadPoLines(poId);
      return;
    }
    loadEligiblePOs();
  })();

  poSearch.addEventListener('input', e=> renderPoList(e.target.value));
  poSearch.addEventListener('blur', ()=>{
    setTimeout(()=>{ poResults.style.display = 'none'; }, 200);
  });
  poSearch.addEventListener('focus', ()=>{
    if (eligiblePOs.length > 0 || poSearch.value.trim()) {
      poResults.style.display = 'block';
    }
  });
  vendorInput.addEventListener('change', loadEligiblePOs);
  warehouseSelect.addEventListener('change', loadLocations);

  document.getElementById('locationModal').addEventListener('show.bs.modal', ()=>{
    document.getElementById('locWarehouseId').value = warehouseSelect.value || '';
  });

  loadWarehouses().then(loadLocations);
  loadNextGrnNumber();

  // create location from modal
  saveLocationBtn.addEventListener('click', async ()=>{
    locModalMsg.textContent = '';
    const name = locName.value.trim();
    if (!name) { locModalMsg.textContent = 'Name is required'; return; }
    const whId = warehouseSelect.value || document.getElementById('locWarehouseId').value || '';
    if (!whId) { locModalMsg.textContent = 'Select a warehouse first'; return; }
    saveLocationBtn.disabled = true; saveLocationBtn.textContent = 'Saving...';
    try {
      const payload = {
        name: name,
        parent_id: locParent.value ? parseInt(locParent.value,10) : null,
        warehouse_id: parseInt(whId,10)
      };
      const resp = await fetch('<?= site_url("new-purchase-grns/locations") ?>', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload) });
      const j = await resp.json();
      if (!resp.ok || !j.success) throw new Error(j.error||'Failed to create location');
      await loadLocations();
      if (j.location_id) { locationSelect.value = j.location_id; }
      locModalMsg.textContent = 'Saved.';
      const modalEl = document.getElementById('locationModal');
      const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
      modal.hide();
      locName.value=''; locParent.value='';
      flash('Location created', 'success');
    } catch (e) {
      locModalMsg.textContent = e.message;
    } finally { saveLocationBtn.disabled = false; saveLocationBtn.textContent = 'Save'; }
  });

  // Load eligible POs helper
  const grnFormElement = document.getElementById('grnForm');
  console.log('GRN Form element:', grnFormElement);
  
  if (!grnFormElement) {
    console.error('CRITICAL: GRN form element not found!');
    flash('Form initialization error - please refresh page', 'danger');
  } else {
    grnFormElement.addEventListener('submit', async (ev)=>{
      console.log('GRN form submit event triggered');
      console.log('poIdInput value:', poIdInput?.value);
      console.log('warehouseSelect value:', warehouseSelect?.value);
      console.log('locationSelect value:', locationSelect?.value);
      
      ev.preventDefault(); 
      messages.innerHTML=''; 
      const f=ev.target; 
      const data={};
      
      data.po_id = parseInt(poIdInput.value,10);
      data.vendor_id = f.vendor_id.value || null;
      data.received_at = receivedAt && receivedAt.value ? receivedAt.value : null;
      data.warehouse_id = f.warehouse_id.value ? parseInt(f.warehouse_id.value,10) : null;
      data.location_id = f.location_id.value ? parseInt(f.location_id.value,10) : null;
      data.location_name = null;
      data.parent_id = null;
      data.location_type = null;
      data.close_po = f.close_po.checked ? 1 : 0;
      data.lines = [];
      const overReasonErrors = [];

      let missingVariant = false;
      grnLinesBody.querySelectorAll('tr').forEach((tr, idx)=>{
        const qtyEl = tr.querySelector('.qty-recv');
        if (!qtyEl) return;
        const qty = parseFloat(qtyEl.value||'0');
        // Always round to 2 decimals for submission
        const qtyFixed = Number(qty).toFixed(2);
        if (!qty || qty <= 0) return;
        
        // Get variant_id from hidden input or variant-select dropdown
        const variant_id_el = tr.querySelector(`[name="lines[${idx}][variant_id]"]`);
        const variantSel = tr.querySelector('.variant-select');
        let variantId = null;
        if (variant_id_el && variant_id_el.value) {
          variantId = parseInt(variant_id_el.value, 10) || null;
        } else if (variantSel && variantSel.value) {
          variantId = parseInt(variantSel.value, 10) || null;
        }
        if (variantSel && (!variantId || variantId <= 0)) { missingVariant = true; }
        
        const po_line_id_el = tr.querySelector(`[name="lines[${idx}][po_line_id]"]`);
        const product_id_el = tr.querySelector(`[name="lines[${idx}][product_id]"]`);
        const unit_price_el = tr.querySelector(`[name="lines[${idx}][unit_price]"]`);
        const description_el = tr.querySelector(`[name="lines[${idx}][description]"]`);
        const location_id_el = tr.querySelector(`[name="lines[${idx}][location_id]"]`);
        const pendingQty = parseFloat(qtyEl.dataset.pending || '0');
        const isOverReceipt = Number(qtyFixed) > pendingQty + 0.0001;
        const overReasonType    = isOverReceipt ? (tr.dataset.overReasonType    || '') : '';
        const overReasonDetails = isOverReceipt ? (tr.dataset.overReasonDetails || '') : '';
        const overPayableQty = isOverReceipt ? (parseFloat(tr.dataset.overPayableQty || '0') || 0) : 0;
        const overFreeQty = isOverReceipt ? (parseFloat(tr.dataset.overFreeQty || '0') || 0) : 0;
        const overPayableReasonType = isOverReceipt ? String(tr.dataset.overPayableReasonType || '').trim() : '';
        const overQty = isOverReceipt ? Math.max(0, Number(qtyFixed) - pendingQty) : 0;

        if (isOverReceipt) {
          if (!overReasonType) {
            overReasonErrors.push('Line ' + (idx + 1) + ': please set over-receipt split reason (' + tr.querySelector('.line-product-name')?.textContent + ').');
          }
          const splitSum = overPayableQty + overFreeQty;
          if (Math.abs(splitSum - overQty) > 0.02) {
            overReasonErrors.push('Line ' + (idx + 1) + ': payable + free split must equal extra qty (' + overQty.toFixed(2) + ').');
          }
          if (overPayableQty > 0.0001 && !['vendor_extra','extra_ordered'].includes(overPayableReasonType)) {
            overReasonErrors.push('Line ' + (idx + 1) + ': select payable reason for payable extra quantity.');
          }
        }
        
        const row = {
          po_line_id: po_line_id_el ? (parseInt(po_line_id_el.value||'0',10) || null) : null,
          product_id: product_id_el ? (parseInt(product_id_el.value||'0',10) || null) : null,
          qty_received: Number(qtyFixed),
          unit_price: unit_price_el ? (parseFloat(unit_price_el.value||'0').toFixed(2) || null) : null,
          description: description_el ? (description_el.value || null) : null,
          variant_id: variantId || null,
          location_id: location_id_el && location_id_el.value ? parseInt(location_id_el.value, 10) : null,
          over_receipt_reason_type: isOverReceipt ? overReasonType : '',
          over_receipt_reason_details: isOverReceipt ? overReasonDetails : '',
          over_receipt_payable_qty: isOverReceipt ? Number(overPayableQty.toFixed(4)) : 0,
          over_receipt_free_qty: isOverReceipt ? Number(overFreeQty.toFixed(4)) : 0,
          over_receipt_payable_reason_type: isOverReceipt ? overPayableReasonType : ''
        };
        console.log('Submitting line:', row);
        data.lines.push(row);
      });

      console.log('Validation checks - PO ID:', data.po_id, 'Warehouse:', data.warehouse_id, 'Location:', data.location_id, 'Lines:', data.lines.length);

      if(!data.po_id){ console.log('Validation failed: no PO'); flash('PO is required', 'danger'); return; }
      if(!data.received_at){ console.log('Validation failed: no received date'); flash('Received date is required', 'danger'); return; }
      if(!data.warehouse_id){ console.log('Validation failed: no warehouse'); flash('Warehouse is required', 'danger'); return; }
      
      // Check: need either PO-level location OR per-line locations
      const hasLineLocations = data.lines.some(l => l.location_id);
      if(!data.location_id && !hasLineLocations){ 
        console.log('Validation failed: no location'); 
        flash('Select a default location for PO or assign locations to individual products', 'danger'); 
        return; 
      }
      if(missingVariant){ console.log('Validation failed: missing variant'); flash('Select a variant for all variable products.', 'danger'); return; }
      if(data.lines.length===0){ console.log('Validation failed: no lines'); flash('Add at least one GRN line with qty_received > 0.', 'danger'); return; }
      if (overReasonErrors.length > 0) { flash(overReasonErrors[0], 'danger'); return; }

      console.log('All validations passed, sending data:', data);

      try{
        const reqUrl = '<?= site_url("new-purchase-grns/create") ?>';
        const reqMethod = 'POST';
        const csrfName = '<?= csrf_token() ?>';
        const csrfHash = '<?= csrf_hash() ?>';
        const headers = {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        };
        headers[csrfName] = csrfHash;
        
        console.log('Sending request to:', reqUrl);
        const resp = await fetch(reqUrl, { method:reqMethod, headers, body: JSON.stringify(data) });
        const text = await resp.text();
        const ctype = resp.headers.get('content-type') || '';
        let j = null;
        if (ctype.includes('application/json')) {
          try { j = JSON.parse(text); } catch(parseErr) {
            flash('Server returned JSON content-type but parsing failed: '+text.slice(0,200), 'danger');
            console.error('GRN create parse error', parseErr, text);
            return;
          }
        } else {
          flash(`Server response was not JSON (status ${resp.status}) from ${resp.url || reqUrl} via ${reqMethod}: ${text.slice(0,300)}`, 'danger');
          console.error('GRN create non-JSON response', { status: resp.status, ctype, body: text, url: resp.url || reqUrl, method: reqMethod });
          return;
        }

        if(resp.ok && j.success){
          const msgText = j.message ? j.message : ('GRN created ID: '+j.grn_id);
          flash(msgText, 'success');
          f.reset();
          if (receivedAt) receivedAt.value = '<?= date('Y-m-d') ?>';
          grnLinesBody.innerHTML='';
          refreshOverReceiptPanel();
          loadLocations();
          loadEligiblePOs();
          loadNextGrnNumber();
        }
        else {
          const msg = j.message ? `${j.error||'Failed to create GRN'}: ${j.message}` : (j.error||JSON.stringify(j));
          flash(msg, 'danger');
        }
      }catch(err){ 
        console.error('GRN submit error:', err);
        flash('Network error: '+err.message, 'danger'); 
      }
    });
  }
})();
</script>

<?= $this->endSection() ?>
