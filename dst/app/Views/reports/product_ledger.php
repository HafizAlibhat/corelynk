<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<div class="container-fluid px-3">

  <!-- Breadcrumb & Title -->
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
          <li class="breadcrumb-item"><a href="<?= base_url('/') ?>">Home</a></li>
          <li class="breadcrumb-item"><a href="<?= base_url('/reports') ?>">Reports</a></li>
          <li class="breadcrumb-item active">Product Ledger</li>
        </ol>
      </nav>
      <h4 class="mb-0 fw-bold">
        <i class="bi bi-journal-text me-2 text-primary"></i>Product Ledger
      </h4>
      <p class="text-muted small mb-0">Track every stock movement — see where products came from and where they went.</p>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer me-1"></i>Print
      </button>
      <button class="btn btn-sm btn-outline-success" id="btnExportCsv" style="display:none;" onclick="exportCsv()">
        <i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV
      </button>
    </div>
  </div>

  <!-- ── Filter Bar ─────────────────────────────────────────────────────── -->
  <div class="card mb-3">
    <div class="card-body py-2">
      <div class="row g-2 align-items-end ledger-filter-row mb-1">

        <!-- Product / Variant search -->
        <div class="col-12 col-xl-5 col-xxl-4">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label class="form-label small fw-semibold mb-0">Product / Variant</label>
            <div class="form-check m-0">
              <input class="form-check-input" type="checkbox" id="allProductsToggle">
              <label class="form-check-label small text-muted" for="allProductsToggle">All Products</label>
            </div>
          </div>
          <div class="position-relative">
            <input type="text" id="productSearch" class="form-control form-control-sm"
                   placeholder="Search by name, code, or art number…" autocomplete="off" />
            <div id="productDropdown" class="dropdown-menu w-100 p-0"
                 style="max-height:300px;overflow-y:auto;display:none;"></div>
          </div>
          <input type="hidden" id="selectedProductId" value="<?= (int)($productId ?? 0) ?>" />
          <input type="hidden" id="selectedVariantId" value="0" />
        </div>

        <!-- Date from -->
        <div class="col-6 col-xl-3 col-xxl-2">
          <label class="form-label small mb-1 fw-semibold">From Date</label>
          <input type="date" id="dateFrom" class="form-control form-control-sm" />
        </div>

        <!-- Date to -->
        <div class="col-6 col-xl-3 col-xxl-2">
          <label class="form-label small mb-1 fw-semibold">To Date</label>
          <input type="date" id="dateTo" class="form-control form-control-sm" />
        </div>

        <div class="col-12 col-xl-1 col-xxl-2">
          <label class="form-label small mb-1 fw-semibold">Quick Range</label>
          <div class="d-flex flex-nowrap align-items-center gap-1 quick-range-strip">
            <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-months="1">1M</button>
            <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-months="2">2M</button>
            <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-months="3">3M</button>
            <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-months="6">6M</button>
            <button type="button" class="btn btn-outline-secondary btn-sm quick-range" data-months="12">1Y</button>
          </div>
        </div>

      </div>

      <div class="row g-2 align-items-end ledger-filter-row">

        <div class="col-8 col-sm-5 col-md-4 col-xl-3 col-xxl-2">
          <label class="form-label small mb-1 fw-semibold">Movement</label>
          <input type="hidden" id="direction" value="all" />
          <div class="d-flex gap-1 movement-toggle" role="group" aria-label="Movement Filter">
            <button type="button" class="btn btn-outline-secondary active" data-dir="all">All</button>
            <button type="button" class="btn movement-btn-in" data-dir="in">IN</button>
            <button type="button" class="btn movement-btn-out" data-dir="out">OUT</button>
          </div>
        </div>

        <div class="col-4 col-sm-3 col-md-4 col-xl-3 col-xxl-2">
          <label class="form-label small mb-1 fw-semibold">Vendor</label>
          <select id="vendorFilter" class="form-select form-select-sm ledger-searchable">
            <option value="0">All Vendors</option>
          </select>
        </div>

        <div class="col-8 col-sm-7 col-md-8 col-xl-4 col-xxl-3">
          <label class="form-label small mb-1 fw-semibold">Customer</label>
          <select id="customerFilter" class="form-select form-select-sm ledger-searchable">
            <option value="0">All Customers</option>
          </select>
        </div>

        <!-- Button -->
        <div class="col-4 col-sm-5 col-md-4 col-xl-2 col-xxl-1">
          <button id="btnLoad" class="btn btn-primary btn-sm w-100" onclick="loadHistory()">
            <i class="bi bi-search me-1"></i>Load Ledger
          </button>
        </div>

      </div>
    </div>
  </div>

  <!-- ── Product / Variant Badge ───────────────────────────────────────── -->
  <div id="productBadge" style="display:none;" class="mb-2">
    <span class="badge bg-primary fs-6 fw-normal px-3 py-2">
      <i class="bi bi-box-seam me-2"></i><span id="productBadgeName"></span>
    </span>
    <span id="variantBadge" class="badge bg-info fs-6 fw-normal px-3 py-2 ms-1" style="display:none;">
      <i class="bi bi-tag me-1"></i><span id="variantBadgeName"></span>
    </span>
    <span id="dateRangeBadge" class="badge bg-secondary fs-6 fw-normal px-3 py-2 ms-1">
      <i class="bi bi-calendar3 me-1"></i><span id="dateRangeText"></span>
    </span>
  </div>

  <div id="ledgerHints" class="alert alert-info small mb-3" style="display:none;"></div>

  <!-- ── Summary Cards ─────────────────────────────────────────────────── -->
  <div id="summarySection" style="display:none;" class="mb-3">
    <div class="summary-strip d-flex flex-nowrap gap-2 overflow-auto">
      <div class="summary-card bg-secondary bg-opacity-10 text-center">
        <div class="summary-value" id="sumOpening">0</div>
        <div class="summary-label">Opening Bal.</div>
      </div>
      <div class="summary-card bg-success bg-opacity-10 text-center">
        <div class="summary-value text-success" id="sumIn">0</div>
        <div class="summary-label">Total In</div>
      </div>
      <div class="summary-card bg-danger bg-opacity-10 text-center">
        <div class="summary-value text-danger" id="sumOut">0</div>
        <div class="summary-label">Total Out</div>
      </div>
      <div class="summary-card bg-primary bg-opacity-10 text-center">
        <div class="summary-value text-primary" id="sumClosing">0</div>
        <div class="summary-label">Closing Bal.</div>
      </div>
      <div class="summary-card bg-success bg-opacity-10 text-center">
        <div class="summary-value text-success" id="sumInValue">0</div>
        <div class="summary-label">In Value</div>
      </div>
      <div class="summary-card bg-danger bg-opacity-10 text-center">
        <div class="summary-value text-danger" id="sumOutValue">0</div>
        <div class="summary-label">Out Value</div>
      </div>
      <div class="summary-card bg-info bg-opacity-10 text-center">
        <div class="summary-value text-info" id="sumStockValue">0</div>
        <div class="summary-label">Stock Value</div>
      </div>
    </div>
    <div id="valuationNotice" class="small text-info mt-1" style="display:none;"></div>
  </div>

  <!-- ── Results Table ─────────────────────────────────────────────────── -->
  <div id="resultsSection" style="display:none;">
    <div class="card border-0 shadow-sm">
      <div class="card-header d-flex justify-content-between align-items-center py-2">
        <span class="fw-semibold"><i class="bi bi-list-ul me-2"></i>Stock Movement Ledger</span>
        <div class="d-flex align-items-center gap-2">
          <div class="input-group input-group-sm" style="width: 170px;">
            <span class="input-group-text">Date</span>
            <select id="dateSort" class="form-select">
              <option value="desc">Newest First</option>
              <option value="asc">Oldest First</option>
            </select>
          </div>
          <span class="badge bg-secondary" id="rowCount">0 records</span>
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0" id="ledgerTable">
          <thead class="table-dark">
            <tr>
              <th style="width:160px;">Date / Time</th>
              <th style="width:75px;">Dir.</th>
              <th style="width:220px;">Product</th>
              <th style="width:150px;">Movement</th>
              <th class="text-end" style="width:80px;">Qty</th>
              <th class="text-end" style="width:100px;">Unit Cost</th>
              <th class="text-end" style="width:100px;">Value</th>
              <th class="text-end" style="width:100px;">Balance</th>
              <th>Reference</th>
              <th>Variant</th>
              <th>Warehouse</th>
              <th>Counterparty</th>
              <th>By</th>
            </tr>
          </thead>
          <tbody id="ledgerBody"></tbody>
        </table>
      </div>
      <div id="ledgerMobileCards" class="d-none"></div>
    </div>
  </div>

  <!-- ── States ────────────────────────────────────────────────────────── -->
  <div id="stateLoading" style="display:none;" class="text-center py-5">
    <div class="spinner-border text-primary" role="status"></div>
    <p class="mt-2 text-muted">Loading ledger…</p>
  </div>
  <div id="stateEmpty" style="display:none;" class="text-center py-5 text-muted">
    <i class="bi bi-inbox fs-1 d-block mb-2"></i>
    No stock movements found for the selected filters.
  </div>
  <div id="statePlaceholder" class="text-center py-5 text-muted">
    <i class="bi bi-search fs-1 d-block mb-2"></i>
    Search for a product or variant above to view its stock movement history.<br>
    <small>Default: last 2 months</small>
  </div>

</div><!-- /container-fluid -->

<style>
@media print {
  nav, .btn, .card-header { display: none !important; }
  .card { border: 1px solid #ccc !important; }
}
.tr-in      { background: rgba(25, 135, 84, 0.06); }
.tr-out     { background: rgba(220, 53, 69, 0.06); }
.tr-opening { background: rgba(13, 110, 253, 0.06); }
#productDropdown .dd-item { padding: 0.5rem 0.75rem; cursor: pointer; border-bottom: 1px solid rgba(0,0,0,0.05); }
#productDropdown .dd-item:last-child { border-bottom: none; }
#productDropdown .dd-item:hover { background: var(--bs-primary); color: #fff; }
#productDropdown .dd-item:hover .dd-sub { color: rgba(255,255,255,0.7) !important; }
.dd-sub { font-size: 0.75rem; color: #888; }
.dd-type-badge { font-size: 0.65rem; padding: 0.15rem 0.4rem; }
.summary-card {
  min-width: 130px;
  border-radius: 0.55rem;
  padding: 0.35rem 0.5rem;
  border: 1px solid rgba(255,255,255,0.08);
}
.summary-value {
  font-weight: 700;
  font-size: 1rem;
  line-height: 1.15;
}
.summary-label {
  font-size: 0.72rem;
  color: var(--bs-secondary-color, #6c757d);
}
.quick-range-strip {
  overflow-x: auto;
  padding-bottom: 2px;
}
.quick-range-strip .btn {
  min-width: 42px;
  padding: 0.2rem 0.42rem;
  border-radius: 999px;
}
.movement-toggle .btn {
  font-size: 0.72rem;
  padding-left: 0.42rem;
  padding-right: 0.42rem;
  border-radius: 0.35rem !important;
  min-width: 36px;
  background: #162746;
  color: #b7c6e4;
  border-color: #2a3d61;
}
.movement-toggle .btn:hover {
  background: #21365c;
  color: #e4ecff;
}
.movement-toggle .btn[data-dir="all"].active {
  background: #2f6fed;
  border-color: #2f6fed;
  color: #fff;
}
.movement-toggle .btn[data-dir="in"],
.movement-btn-in {
  color: #4ade80;
  border-color: #4ade80;
  background: transparent;
}
.movement-toggle .btn[data-dir="in"]:hover,
.movement-btn-in:hover {
  background: rgba(74,222,128,0.15);
  color: #4ade80;
  border-color: #4ade80;
}
.movement-toggle .btn[data-dir="in"].active,
.movement-btn-in.active {
  background: #16a34a;
  border-color: #22c55e;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(34,197,94,0.25);
}
.movement-toggle .btn[data-dir="out"],
.movement-btn-out {
  color: #f87171;
  border-color: #f87171;
  background: transparent;
}
.movement-toggle .btn[data-dir="out"]:hover,
.movement-btn-out:hover {
  background: rgba(248,113,113,0.15);
  color: #f87171;
  border-color: #f87171;
}
.movement-toggle .btn[data-dir="out"].active,
.movement-btn-out.active {
  background: #dc2626;
  border-color: #ef4444;
  color: #fff;
  box-shadow: 0 0 0 3px rgba(239,68,68,0.25);
}
.form-check-input#allProductsToggle {
  margin-top: 0;
}
.ledger-filter-row .form-label {
  min-height: 18px;
}
.ledger-filter-row .form-control-sm,
.ledger-filter-row .form-select-sm,
.ledger-filter-row .btn-sm {
  min-height: 32px;
}
.ledger-filter-row .btn-sm {
  line-height: 1.1;
}
.ref-link {
  color: #cfe0ff;
  text-decoration: none;
}
.ref-link:hover {
  color: #ffffff;
  text-decoration: underline;
}
/* ── Select2 dark overrides for ledger filter dropdowns ──────────────── */
.ledger-searchable + .select2-container--default .select2-selection--single,
#vendorFilter + .select2-container--default .select2-selection--single,
#customerFilter + .select2-container--default .select2-selection--single {
  background: #1a2c4a !important;
  border-color: #2e4270 !important;
  color: #c8d8f0 !important;
}
/* Dropdown panel */
.select2-dropdown {
  background: #152238 !important;
  border: 1px solid #2e4270 !important;
  color: #c8d8f0 !important;
  border-radius: 6px !important;
}
/* Each option */
.select2-results__option {
  color: #c8d8f0 !important;
  background: transparent !important;
  padding: 7px 12px !important;
}
/* Hover / keyboard focus */
.select2-results__option--highlighted {
  background: #1e3a62 !important;
  color: #ffffff !important;
}
/* Already selected */
.select2-results__option[aria-selected="true"] {
  background: #1a4a8a !important;
  color: #90c4ff !important;
}
/* Search input inside dropdown */
.select2-search--dropdown .select2-search__field {
  background: #0e1d33 !important;
  border: 1px solid #2e4270 !important;
  color: #c8d8f0 !important;
  border-radius: 4px !important;
  padding: 5px 8px !important;
  outline: none !important;
}
.select2-search--dropdown .select2-search__field::placeholder {
  color: #6a85b0 !important;
}
/* Trigger button text + arrow */
.select2-container--default .select2-selection__rendered {
  color: #c8d8f0 !important;
  line-height: 32px !important;
}
.select2-container--default .select2-selection__arrow b {
  border-top-color: #8aaad4 !important;
}
.select2-container--default.select2-container--open .select2-selection__arrow b {
  border-bottom-color: #8aaad4 !important;
}
.ledger-date-main {
  font-weight: 600;
  font-size: 0.83rem;
  line-height: 1.1;
}
.ledger-date-sub {
  font-size: 0.72rem;
  color: var(--bs-secondary-color, #6c757d);
  line-height: 1.1;
}

#ledgerMobileCards {
  padding: 0.6rem;
}
.ledger-mobile-card {
  border: 1px solid rgba(120, 153, 201, 0.22);
  border-radius: 0.6rem;
  background: rgba(19, 33, 59, 0.72);
  padding: 0.6rem;
  margin-bottom: 0.55rem;
}
.ledger-mobile-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.45rem;
}
.ledger-mobile-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.32rem 0.6rem;
  font-size: 0.76rem;
}
.ledger-mobile-key {
  color: #8ea7d1;
}
.ledger-mobile-val {
  color: #dbe7ff;
  font-weight: 600;
  word-break: break-word;
}

@media (max-width: 1199.98px) {
  .ledger-filter-row > div {
    margin-bottom: 0.15rem;
  }
}

@media (max-width: 991.98px) {
  #ledgerTable {
    font-size: 0.78rem;
  }
  #resultsSection .table-responsive {
    display: none;
  }
  #ledgerMobileCards {
    display: block !important;
  }
  .summary-card {
    min-width: 112px;
  }
}

@media (max-width: 575.98px) {
  .ledger-mobile-grid {
    grid-template-columns: 1fr;
  }
  .quick-range-strip .btn {
    min-width: 38px;
    padding: 0.18rem 0.35rem;
  }
}
</style>

<script>
const BASE = '<?= base_url() ?>';

/* ── Default dates: 2 months ago → today ──────────────────────────────── */
(function setDefaultDates() {
    const today = new Date();
    const ago   = new Date();
    ago.setMonth(ago.getMonth() - 2);
    document.getElementById('dateTo').value   = today.toISOString().slice(0, 10);
    document.getElementById('dateFrom').value = ago.toISOString().slice(0, 10);
})();

document.querySelectorAll('.quick-range').forEach(btn => {
  btn.addEventListener('click', function() {
    const months = parseInt(this.dataset.months || '0', 10);
    if (!months) return;
    const to = new Date();
    const from = new Date();
    from.setMonth(from.getMonth() - months);
    document.getElementById('dateTo').value = to.toISOString().slice(0, 10);
    document.getElementById('dateFrom').value = from.toISOString().slice(0, 10);
    loadVendorOptions().then(() => loadHistory());
  });
});

document.getElementById('dateFrom').addEventListener('change', () => { loadVendorOptions(); });
document.getElementById('dateTo').addEventListener('change', () => { loadVendorOptions(); });
document.getElementById('dateFrom').addEventListener('change', () => { loadCustomerOptions(); });
document.getElementById('dateTo').addEventListener('change', () => { loadCustomerOptions(); });

document.getElementById('allProductsToggle').addEventListener('change', function() {
  const all = this.checked;
  if (all) {
    document.getElementById('selectedProductId').value = '0';
    document.getElementById('selectedVariantId').value = '0';
    searchInput.value = '';
    searchInput.placeholder = 'All products selected';
  } else {
    searchInput.placeholder = 'Search by name, code, or art number…';
  }
  loadVendorOptions();
  loadCustomerOptions();
});

document.querySelectorAll('.movement-toggle [data-dir]').forEach(btn => {
  btn.addEventListener('click', function() {
    const dir = this.dataset.dir || 'all';
    setDirection(dir);
    loadHistory();
  });
});

document.getElementById('dateSort').addEventListener('change', function() {
  if (window._ledgerPayload) {
    renderResults(window._ledgerPayload);
  }
});

/* ── Product / Variant autocomplete ───────────────────────────────────── */
let searchTimer = null;
const searchInput = document.getElementById('productSearch');
const dropdown    = document.getElementById('productDropdown');

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => fetchProducts(this.value.trim()), 250);
});
searchInput.addEventListener('focus', function() {
    if (this.value.trim() === '') fetchProducts('');
});
document.addEventListener('click', function(e) {
    if (!e.target.closest('#productSearch') && !e.target.closest('#productDropdown'))
        dropdown.style.display = 'none';
});

function fetchProducts(q) {
    fetch(BASE + '/product-ledger/api/search?q=' + encodeURIComponent(q))
        .then(r => r.json())
        .then(j => { if (j.success) renderDropdown(j.data); })
        .catch(() => {});
}

function renderDropdown(items) {
    if (!items || !items.length) { dropdown.style.display = 'none'; return; }
    let html = '';
    items.forEach(p => {
        const badge = p.type === 'variant'
            ? '<span class="badge bg-info dd-type-badge">Variant</span>'
            : '<span class="badge bg-secondary dd-type-badge">Product</span>';
        html += `<div class="dd-item" data-pid="${p.product_id}" data-vid="${p.variant_id}" data-label="${esc(p.label)}">
            <div class="d-flex justify-content-between align-items-center">
                <span>${esc(p.label)}</span>${badge}
            </div>
            <div class="dd-sub">${esc(p.sub)}</div>
        </div>`;
    });
    dropdown.innerHTML = html;
    dropdown.style.display = 'block';
    dropdown.querySelectorAll('.dd-item').forEach(el => {
        el.addEventListener('click', function() {
          document.getElementById('allProductsToggle').checked = false;
            document.getElementById('selectedProductId').value = this.dataset.pid;
            document.getElementById('selectedVariantId').value = this.dataset.vid;
            searchInput.value = this.dataset.label;
            dropdown.style.display = 'none';
            loadVendorOptions().then(() => loadHistory());
            loadCustomerOptions();
        });
    });
}

      function loadVendorOptions() {
        const allProducts = document.getElementById('allProductsToggle').checked;
        const productId = allProducts ? '0' : document.getElementById('selectedProductId').value;
        const variantId = allProducts ? '0' : (document.getElementById('selectedVariantId').value || '0');
        const dateFrom  = document.getElementById('dateFrom').value;
        const dateTo    = document.getElementById('dateTo').value;
        const sel       = document.getElementById('vendorFilter');

        const prev = sel.value || '0';
        const params = new URLSearchParams({ product_id: productId, variant_id: variantId });
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        return fetch(BASE + '/product-ledger/api/vendors?' + params.toString())
          .then(r => r.json())
          .then(j => {
            const list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
            let html = '<option value="0">All Vendors</option>';
            list.forEach(v => {
              html += `<option value="${Number(v.id)}">${esc(v.name)}</option>`;
            });
            sel.innerHTML = html;
            if ([...sel.options].some(o => o.value === prev)) {
              sel.value = prev;
            } else {
              sel.value = '0';
            }
            if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(sel).data('select2')) {
              window.jQuery(sel).trigger('change.select2');
            }
          })
          .catch(() => {
            sel.innerHTML = '<option value="0">All Vendors</option>';
            if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(sel).data('select2')) {
              window.jQuery(sel).trigger('change.select2');
            }
          });
      }

function loadCustomerOptions() {
        const allProducts = document.getElementById('allProductsToggle').checked;
        const productId = allProducts ? '0' : document.getElementById('selectedProductId').value;
        const variantId = allProducts ? '0' : (document.getElementById('selectedVariantId').value || '0');
        const dateFrom  = document.getElementById('dateFrom').value;
        const dateTo    = document.getElementById('dateTo').value;
        const sel       = document.getElementById('customerFilter');

        const prev = sel.value || '0';
        const params = new URLSearchParams({ product_id: productId || '0', variant_id: variantId || '0' });
        if (dateFrom) params.append('date_from', dateFrom);
        if (dateTo) params.append('date_to', dateTo);

        return fetch(BASE + '/product-ledger/api/customers?' + params.toString())
          .then(r => r.json())
          .then(j => {
            const list = (j && j.success && Array.isArray(j.data)) ? j.data : [];
            let html = '<option value="0">All Customers</option>';
            list.forEach(c => {
              html += `<option value="${Number(c.id)}">${esc(c.name)}</option>`;
            });
            sel.innerHTML = html;
            if ([...sel.options].some(o => o.value === prev)) {
              sel.value = prev;
            } else {
              sel.value = '0';
            }
            if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(sel).data('select2')) {
              window.jQuery(sel).trigger('change.select2');
            }
          })
          .catch(() => {
            sel.innerHTML = '<option value="0">All Customers</option>';
            if (window.jQuery && window.jQuery.fn.select2 && window.jQuery(sel).data('select2')) {
              window.jQuery(sel).trigger('change.select2');
            }
          });
      }

/* ── Load ledger ──────────────────────────────────────────────────────── */
function loadHistory() {
    const allProducts = document.getElementById('allProductsToggle').checked;
    const productId = allProducts ? '0' : document.getElementById('selectedProductId').value;
    const customerId = document.getElementById('customerFilter').value || '0';
    if ((!productId || productId === '0') && customerId === '0' && (document.getElementById('vendorFilter').value || '0') === '0') {
      searchInput.focus();
      return;
    }

    const variantId = allProducts ? '0' : (document.getElementById('selectedVariantId').value || '0');
    const vendorId  = document.getElementById('vendorFilter').value || '0';
    const dateFrom  = document.getElementById('dateFrom').value;
    const dateTo    = document.getElementById('dateTo').value;
    const direction = document.getElementById('direction').value;

    setState('loading');

    const params = new URLSearchParams({ product_id: productId || '0', variant_id: variantId, vendor_id: vendorId, customer_id: customerId, direction });
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo)   params.append('date_to', dateTo);

    fetch(BASE + '/product-ledger/api/history?' + params.toString())
        .then(r => r.json())
        .then(j => {
            if (!j.success) { setState('placeholder'); alert(j.error || 'Error'); return; }
          window._ledgerPayload = j;
            renderResults(j);
        })
        .catch(err => { setState('placeholder'); console.error(err); });
}

/* ── Render results ───────────────────────────────────────────────────── */
function renderResults(j) {
  const rows = j.rows || [];
    const sum  = j.summary || {};
  const sortedRows = sortRowsByDate(rows, document.getElementById('dateSort').value || 'desc');

  renderHints(j.hints || {}, rows.length);

    // Badges
    if (j.product) {
      const productLabel = (j.product.code ? '[' + j.product.code + '] ' : '') + j.product.name;
      const productUrl = BASE + '/products/' + encodeURIComponent(String(j.product.id));
      document.getElementById('productBadgeName').innerHTML =
        `<a class="ref-link" href="${productUrl}" target="_blank" rel="noopener" title="Open product">${esc(productLabel)}</a>`;
        document.getElementById('productBadge').style.display = '';
    }
    if (j.variant) {
        document.getElementById('variantBadgeName').textContent = j.variant.name || j.variant.art_number;
        document.getElementById('variantBadge').style.display = '';
    } else {
        document.getElementById('variantBadge').style.display = 'none';
    }
    if (j.filters) {
        document.getElementById('dateRangeText').textContent =
            fmtDate(j.filters.date_from) + ' → ' + fmtDate(j.filters.date_to);
    }

    // Summary cards
    document.getElementById('sumOpening').textContent  = fmt(sum.opening_balance);
    document.getElementById('sumIn').textContent       = fmt(sum.total_in);
    document.getElementById('sumOut').textContent      = fmt(sum.total_out);
    document.getElementById('sumClosing').textContent  = fmt(sum.closing_balance);
    document.getElementById('sumInValue').textContent  = fmtMoney(sum.total_in_value);
    document.getElementById('sumOutValue').textContent = fmtMoney(sum.total_out_value);
    document.getElementById('sumStockValue').textContent = fmtMoney(sum.stock_value);
    document.getElementById('summarySection').style.display = '';

    const freeIn = parseFloat(sum.total_in_free || 0);
    const notice = document.getElementById('valuationNotice');
    if (freeIn > 0) {
      notice.textContent = 'Includes ' + fmt(freeIn) + ' free replacement qty. Purchase and stock values are calculated on paid quantity only.';
      notice.style.display = '';
    } else {
      notice.textContent = '';
      notice.style.display = 'none';
    }

    // Row count
    document.getElementById('rowCount').textContent = sortedRows.length + ' record' + (sortedRows.length !== 1 ? 's' : '');

    if (sortedRows.length === 0) { setState('empty'); return; }

    // Table body — opening balance row + movements
    const tbody = document.getElementById('ledgerBody');
    const mobileBox = document.getElementById('ledgerMobileCards');
    const allProducts = !!(j.filters && j.filters.all_products);
    let html = `<tr class="tr-opening fw-semibold">
        <td class="small">${fmtDate(j.filters?.date_from)}</td>
        <td><span class="badge bg-secondary"><i class="bi bi-arrow-right-circle me-1"></i>B/F</span></td>
      <td><span class="text-muted small">${allProducts ? 'All Products' : 'Selected Product'}</span></td>
        <td><span class="text-muted small">Opening Balance</span></td>
        <td class="text-end">—</td><td class="text-end">—</td><td class="text-end">—</td>
        <td class="text-end fw-bold">${fmt(sum.opening_balance)}</td>
        <td colspan="5" class="text-muted small">Balance brought forward</td>
    </tr>`;

    let mobileHtml = `<div class="ledger-mobile-card">
      <div class="ledger-mobile-head">
        <div class="ledger-date-main">${fmtDate(j.filters?.date_from)}</div>
        <span class="badge bg-secondary">B/F</span>
      </div>
      <div class="ledger-mobile-grid">
        <div><span class="ledger-mobile-key">Movement:</span> <span class="ledger-mobile-val">Opening Balance</span></div>
        <div><span class="ledger-mobile-key">Balance:</span> <span class="ledger-mobile-val">${fmt(sum.opening_balance)}</span></div>
      </div>
    </div>`;

    sortedRows.forEach(r => {
        const isIn     = r.direction === 'IN';
        const dirBadge = isIn
            ? '<span class="badge bg-success"><i class="bi bi-arrow-down-circle me-1"></i>IN</span>'
            : '<span class="badge bg-danger"><i class="bi bi-arrow-up-circle me-1"></i>OUT</span>';
        const trCls    = isIn ? 'tr-in' : 'tr-out';
        const qtyDisp  = isIn ? ('+' + fmt(r.qty_abs)) : ('−' + fmt(r.qty_abs));
        const qtyCls   = isIn ? 'text-success' : 'text-danger';
        const unitCostCell = r.unit_cost_visible && r.unit_cost > 0
          ? fmtMoney(r.unit_cost)
          : '<span class="text-muted">—</span>';
        const valueCell = r.unit_cost_visible && r.value > 0
          ? fmtMoney(r.value)
          : ((parseFloat(r.free_qty || 0) > 0) ? '<span class="text-info">Free</span>' : '<span class="text-muted">—</span>');
        const refExtra = renderDocumentLinks(r);
        const adjustExtra = renderAdjustmentAudit(r);
        const refLabelHtml = renderReferenceLabelLink(r);
        const valuationExtra = r.valuation_note ? ('<br><span class="text-info" style="font-size:.72rem;">' + esc(r.valuation_note) + '</span>') : '';
        const partyExtra = r.counterparty_role ? ('<br><span class="text-muted" style="font-size:.72rem;">' + esc(r.counterparty_role) + '</span>') : '';
        const partyName  = esc(r.counterparty || '');
        const partyHtml  = (r.counterparty_id && r.counterparty_role === 'Vendor')
            ? `<a class="ref-link" href="${BASE}/vendors/${encodeURIComponent(String(r.counterparty_id))}" target="_blank" rel="noopener">${partyName}</a>`
            : partyName;

        html += `<tr class="${trCls}">
            <td>
              <div class="ledger-date-main">${fmtDate(r.date)}</div>
              <div class="ledger-date-sub">${fmtTime(r.date)}</div>
            </td>
            <td>${dirBadge}</td>
            <td class="small">${r.product_id ? `<a class="ref-link" href="${BASE + '/products/' + encodeURIComponent(String(r.product_id))}" target="_blank" rel="noopener">${esc(r.product_label || ('Product #' + r.product_id))}</a>` : esc(r.product_label || '')}</td>
          <td>${typeBadge(r.type, r.movement_label)}</td>
            <td class="text-end fw-semibold ${qtyCls}">${qtyDisp}</td>
          <td class="text-end">${unitCostCell}</td>
          <td class="text-end">${valueCell}</td>
            <td class="text-end fw-bold">${fmt(r.balance)}</td>
          <td class="small">${refLabelHtml}${refExtra}${adjustExtra}${valuationExtra}${(r.notes && (r.type === 'transfer_in' || r.type === 'transfer_out')) ? '<br><span class="text-muted" style="font-size:.7rem;font-style:italic;" title="Transfer Reason">&#9432; ' + esc(r.notes) + '</span>' : ''}</td>
            <td class="small text-muted">${esc(r.variant || '')}</td>
            <td class="small text-muted">${esc(r.warehouse || '')}${r.location ? ' / ' + esc(r.location) : ''}</td>
          <td class="small">${partyHtml}${partyExtra}</td>
            <td class="small text-muted">${esc(r.user || '')}</td>
        </tr>`;

        mobileHtml += `<div class="ledger-mobile-card ${trCls}">
          <div class="ledger-mobile-head">
            <div>
              <div class="ledger-date-main">${fmtDate(r.date)}</div>
              <div class="ledger-date-sub">${fmtTime(r.date)}</div>
            </div>
            <div>${dirBadge}</div>
          </div>
          <div class="ledger-mobile-grid">
            <div><span class="ledger-mobile-key">Movement:</span> <span class="ledger-mobile-val">${esc(r.movement_label || r.type || '')}</span></div>
            <div><span class="ledger-mobile-key">Product:</span> <span class="ledger-mobile-val">${r.product_id ? `<a class="ref-link" href="${BASE + '/products/' + encodeURIComponent(String(r.product_id))}" target="_blank" rel="noopener">${esc(r.product_label || ('Product #' + r.product_id))}</a>` : esc(r.product_label || '—')}</span></div>
            <div><span class="ledger-mobile-key">Qty:</span> <span class="ledger-mobile-val ${qtyCls}">${qtyDisp}</span></div>
            <div><span class="ledger-mobile-key">Unit Cost:</span> <span class="ledger-mobile-val">${r.unit_cost_visible && r.unit_cost > 0 ? fmtMoney(r.unit_cost) : '—'}</span></div>
            <div><span class="ledger-mobile-key">Value:</span> <span class="ledger-mobile-val">${r.unit_cost_visible && r.value > 0 ? fmtMoney(r.value) : (parseFloat(r.free_qty || 0) > 0 ? 'Free' : '—')}</span></div>
            <div><span class="ledger-mobile-key">Balance:</span> <span class="ledger-mobile-val">${fmt(r.balance)}</span></div>
            <div><span class="ledger-mobile-key">Reference:</span> <span class="ledger-mobile-val">${refLabelHtml}${refExtra}${adjustExtra}</span></div>
            <div><span class="ledger-mobile-key">Variant:</span> <span class="ledger-mobile-val">${esc(r.variant || '—')}</span></div>
            <div><span class="ledger-mobile-key">Warehouse:</span> <span class="ledger-mobile-val">${esc(r.warehouse || '')}${r.location ? ' / ' + esc(r.location) : ''}</span></div>
            <div><span class="ledger-mobile-key">Counterparty:</span> <span class="ledger-mobile-val">${esc(r.counterparty || '—')}</span></div>
            <div><span class="ledger-mobile-key">By:</span> <span class="ledger-mobile-val">${esc(r.user || '—')}</span></div>
          </div>
        </div>`;
    });

    tbody.innerHTML = html;
    mobileBox.innerHTML = mobileHtml;
    setState('results');
    document.getElementById('btnExportCsv').style.display = '';
    window._ledgerRows    = sortedRows;
    window._ledgerProduct = j.product;
    window._ledgerSummary = sum;
}

function setDirection(dir) {
  const hidden = document.getElementById('direction');
  hidden.value = dir;
  document.querySelectorAll('.movement-toggle [data-dir]').forEach(b => {
    b.classList.toggle('active', (b.dataset.dir || '') === dir);
  });
}

function sortRowsByDate(rows, dir) {
  const copy = Array.isArray(rows) ? [...rows] : [];
  copy.sort((a, b) => {
    const ta = Date.parse(a.date || '') || 0;
    const tb = Date.parse(b.date || '') || 0;
    return dir === 'asc' ? (ta - tb) : (tb - ta);
  });
  return copy;
}

function referenceUrl(r) {
  const serverRefUrl = String(r.ref_url || '').trim();
  if (serverRefUrl) return serverRefUrl;

  const rt = String(r.ref_type || '').toLowerCase();
  const ridRaw = String(r.ref_id || '').trim();
  const rid = Number(ridRaw || 0);
  if (!ridRaw) return '';
  if (rt === 'delivery_order') return BASE + '/delivery-orders/view/' + rid;
  if (rt === 'grn') {
    const grnToken = String(r.ref_public_id || '').trim() || ridRaw;
    return BASE + '/new-purchase-grns/detail/' + encodeURIComponent(grnToken);
  }
  if (rt === 'internal_transfer') return BASE + '/components/transfers/' + rid;
  if (rt === 'stock_adjustment') return BASE + '/components/inventory/adjustments';
  return '';
}

function documentUrl(doc) {
  const s = String(doc || '').trim();
  if (!s) return '';
  if (/^PO\s+/i.test(s)) {
    const no = s.replace(/^PO\s+/i, '').trim();
    if (!no) return '';
    return BASE + '/new-purchase-orders/' + encodeURIComponent(no);
  }
  if (/^SO\s+/i.test(s)) {
    const no = s.replace(/^SO\s+/i, '').trim();
    if (!no) return '';
    return BASE + '/sales-orders/view/' + encodeURIComponent(no);
  }
  return '';
}

function renderReferenceLabelLink(r) {
  const label = esc(r.ref_label || '—');
  const url = referenceUrl(r);
  if (!url) return label;
  return `<a class="ref-link" href="${url}" target="_blank" rel="noopener" title="Open reference">${label}</a>`;
}

function renderDocumentLinks(r) {
  const d = String(r.document || '').trim();
  if (!d) return '';
  // Prefer server-supplied doc_url (uses real public_id, never broken)
  const url = String(r.doc_url || '').trim() || documentUrl(d);
  const text = esc(d);
  if (!url) {
    return '<br><span class="text-muted" style="font-size:.72rem;">' + text + '</span>';
  }
  return '<br><a class="ref-link" style="font-size:.72rem;" href="' + BASE + url + '" target="_blank" rel="noopener" title="Open document">' + text + '</a>';
}

function renderAdjustmentAudit(r) {
  const action = String(r.adjust_action_kind || '').trim();
  if (!action) return '';

  const actionMap = {
    set_zero: 'Set to zero',
    set_exact: 'Set exact qty',
    increase: 'Increase',
    decrease: 'Decrease'
  };

  const actionLabel = actionMap[action] || action.replace(/_/g, ' ');
  const reason = String(r.adjust_reason_text || '').trim();
  const oldBal = Number(r.adjust_old_balance);
  const target = Number(r.adjust_target_qty);
  const hasOld = Number.isFinite(oldBal);
  const hasTarget = Number.isFinite(target);
  const qtyPart = (hasOld && hasTarget)
    ? ` (${fmt(oldBal)} -> ${fmt(target)})`
    : '';
  const reasonPart = reason ? `: ${esc(reason)}` : '';

  return '<br><span class="text-info" style="font-size:.72rem;" title="Adjustment audit">&#9432; ' + esc(actionLabel) + esc(qtyPart) + reasonPart + '</span>';
}

function typeBadge(t, label) {
    const m = {
        grn:           { l: 'GRN Receipt',   c: 'bg-success' },
        'in':          { l: 'Stock In',       c: 'bg-success' },
        opening_stock: { l: 'Opening Stock',  c: 'bg-info' },
        adjustment:    { l: 'Adjustment',     c: 'bg-warning text-dark' },
    shipment:      { l: 'Customer Shipment', c: 'bg-danger' },
        scrap:         { l: 'Scrap',          c: 'bg-dark' },
    return_to_vendor: { l: 'Return to Vendor', c: 'bg-danger' },
    send_for_repair: { l: 'Repair Out', c: 'bg-warning text-dark' },
    receive_repaired_back: { l: 'Repair In', c: 'bg-success' },
        subcontract_out:    { l: 'Sub. Out',    c: 'bg-danger' },
        subcontract_in:     { l: 'Sub. In',     c: 'bg-success' },
    subcontract_scrap:  { l: 'Sub. Scrap',  c: 'bg-dark' },
        subcontract_cancel: { l: 'Sub. Cancel', c: 'bg-secondary' },
        transfer_out:  { l: 'Transfer Out',   c: 'bg-danger' },
        transfer_in:   { l: 'Transfer In',    c: 'bg-success' },
    };
  const x = m[t] || { l: label || t || '—', c: 'bg-secondary' };
    return `<span class="badge ${x.c}" style="font-size:0.7rem;">${x.l}</span>`;
}

function renderHints(hints, rowCount) {
  const box = document.getElementById('ledgerHints');
  if (!box) return;

  if (!hints || Object.keys(hints).length === 0) {
    box.style.display = 'none';
    box.innerHTML = '';
    return;
  }

  let html = '';
  if (hints.message) {
    html += '<div class="fw-semibold mb-1">' + esc(hints.message) + '</div>';
  }

  const variants = Array.isArray(hints.suggested_variants) ? hints.suggested_variants : [];
  if (variants.length) {
    html += '<div class="mb-1">Try one of these variants with movement:</div>';
    html += '<div class="d-flex flex-wrap gap-1">';
    variants.forEach(v => {
      const lbl = (v.art_number ? '[' + v.art_number + '] ' : '') + (v.label || ('Variant #' + v.variant_id));
      html += `<button type="button" class="btn btn-sm btn-outline-primary" onclick="useSuggestedVariant(${Number(v.variant_id)})">${esc(lbl)} (In ${fmt(v.qty_in)} / Out ${fmt(v.qty_out)})</button>`;
    });
    html += '</div>';
  }

  if (!html && rowCount === 0) {
    box.style.display = 'none';
    return;
  }

  box.innerHTML = html;
  box.style.display = html ? '' : 'none';
}

function useSuggestedVariant(variantId) {
  const pid = document.getElementById('selectedProductId').value;
  fetch(BASE + '/product-ledger/api/search?q=')
    .then(r => r.json())
    .then(j => {
      if (!j.success || !Array.isArray(j.data)) return;
      const row = j.data.find(x => Number(x.product_id) === Number(pid) && Number(x.variant_id) === Number(variantId));
      if (row) {
        document.getElementById('selectedVariantId').value = String(row.variant_id || 0);
        document.getElementById('productSearch').value = row.label || document.getElementById('productSearch').value;
        loadVendorOptions().then(() => loadHistory());
        loadCustomerOptions();
      }
    })
    .catch(() => {});
}

/* ── State helpers ────────────────────────────────────────────────────── */
function setState(s) {
    document.getElementById('stateLoading').style.display     = s === 'loading'     ? '' : 'none';
    document.getElementById('stateEmpty').style.display       = s === 'empty'       ? '' : 'none';
    document.getElementById('statePlaceholder').style.display = s === 'placeholder' ? '' : 'none';
    document.getElementById('resultsSection').style.display   = s === 'results'     ? '' : 'none';
    if (s !== 'results' && s !== 'empty') {
        document.getElementById('summarySection').style.display = 'none';
        document.getElementById('valuationNotice').style.display = 'none';
        document.getElementById('productBadge').style.display   = 'none';
      document.getElementById('ledgerHints').style.display    = 'none';
        document.getElementById('btnExportCsv').style.display   = 'none';
    }
}

/* ── CSV Export ────────────────────────────────────────────────────────── */
function exportCsv() {
    const rows = window._ledgerRows || [];
    const p    = window._ledgerProduct || {};
    if (!rows.length) return;
    let csv = 'Date,Direction,Type,Qty,Unit Cost,Value,Balance,Reference,Document,Adjustment Action,Adjustment Reason,Variant,Warehouse,Counterparty,By\n';
    rows.forEach(r => {
        csv += [
            csvEsc(fmtDate(r.date)), csvEsc(r.direction), csvEsc(r.type),
            r.qty, r.unit_cost, r.value, r.balance,
        csvEsc(r.ref_label), csvEsc(r.document), csvEsc(r.adjust_action_kind), csvEsc(r.adjust_reason_text), csvEsc(r.variant), csvEsc(r.warehouse),
        csvEsc(r.counterparty), csvEsc(r.user)
        ].join(',') + '\n';
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'product-ledger-' + (p.code || p.id || 'export') + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

/* ── Format helpers ───────────────────────────────────────────────────── */
function fmt(v)         { const n = parseFloat(v) || 0; return n % 1 === 0 ? n.toLocaleString() : n.toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:4}); }
function fmtMoney(v)    { return (parseFloat(v) || 0).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2}); }
function fmtDate(d)     { if (!d) return '—'; try { return new Date(d).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}); } catch(e) { return d; } }
function fmtDateTime(d) { if (!d) return '—'; try { const dt = new Date(d); return dt.toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) + ' ' + dt.toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'}); } catch(e) { return d; } }
function fmtTime(d)     { if (!d) return '—'; try { return new Date(d).toLocaleTimeString('en-GB',{hour:'2-digit',minute:'2-digit'}); } catch(e) { return d; } }
function esc(s)         { if (!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
function csvEsc(s)      { if (!s) return ''; s = String(s); if (s.includes(',') || s.includes('"') || s.includes('\n')) s = '"' + s.replace(/"/g,'""') + '"'; return s; }

/* ── Auto-load if product id in URL ───────────────────────────────────── */
(function() {
  setDirection('all');
    const preId = parseInt(document.getElementById('selectedProductId').value, 10);
    if (preId > 0) {
        fetch(BASE + '/product-ledger/api/search?q=')
            .then(r => r.json())
            .then(j => {
                if (!j.success) return;
                const p = j.data.find(x => x.product_id == preId && x.variant_id == 0);
                if (p) searchInput.value = p.label;
            })
            .catch(() => {});
      Promise.all([loadVendorOptions(), loadCustomerOptions()]).then(() => loadHistory());
    } else {
      Promise.all([loadVendorOptions(), loadCustomerOptions()]);
    }
})();

    document.getElementById('vendorFilter').addEventListener('change', loadHistory);
    document.getElementById('customerFilter').addEventListener('change', loadHistory);

    /* ── Init Select2 on ledger searchable selects ─────────────────────── */
    (function initLedgerSelect2() {
      if (!window.jQuery || !window.jQuery.fn.select2) {
        setTimeout(initLedgerSelect2, 100);
        return;
      }
      window.jQuery('.ledger-searchable').each(function() {
        try {
          window.jQuery(this).select2({
            width: '100%',
            theme: 'default',
            dropdownParent: window.jQuery(this).closest('.card'),
            minimumResultsForSearch: 0
          });
        } catch(e) { console.warn('Select2 init failed', e); }
      });
      window.jQuery('#vendorFilter').on('select2:select select2:unselect', function() {
        document.getElementById('vendorFilter').value = this.value;
        loadHistory();
      });
      window.jQuery('#customerFilter').on('select2:select select2:unselect', function() {
        document.getElementById('customerFilter').value = this.value;
        loadHistory();
      });
    })();
</script>

<?= $this->endSection() ?>
