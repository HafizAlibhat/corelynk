<?= $this->extend('layouts/main') ?>

<?php
  $currencyList = $currencies ?? [];
  $defaultCurrency = $defaultCurrency ?? 'USD';
?>

<?= $this->section('title') ?>
RFQ / PO
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="rfqpoPage" class="rfqpo-shell">
<div class="card">
  <div class="card-header section-header d-flex justify-content-between align-items-center" style="padding: 0.75rem 1rem;">
    <h3 class="section-title" style="margin: 0; font-size: 1.1rem;">RFQ / PO</h3>
  </div>
  <div class="card-body">
    <div id="messages" style="margin-bottom: 0.5rem;"></div>

    <script>
      const DEFAULT_PURCHASE_CURRENCY = '<?= esc($defaultCurrency) ?>';
    </script>

    <!-- Single Form: always creates RFQ (draft). When accepted, it becomes a PO (same number). -->
  <div id="formWrapper" style="display:none">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="mb-0 fw-semibold" id="formTitle">New RFQ</h6>
    <button type="button" id="backToListBtn" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Back to List
    </button>
  </div>
  <form id="rfqpoForm" class="mb-4">
      <input id="rfqId" name="rfq_id" type="hidden" />
      <div class="row g-2">
        <div class="col-md-3 form-group">
          <label class="form-label">Number</label>
          <input id="rfqNumber" name="rfq_number" class="form-control" readonly />
        </div>
        <div class="col-md-3 form-group">
          <label class="form-label">Vendor <span style="color:red;">*</span></label>
          <select id="vendorSelect" name="vendor_id" class="form-select" style="width:100%">
            <option value="">-- Search vendor --</option>
          </select>
          <small id="vendorValidation" style="color:red;display:none;margin-top:4px;">Please select a vendor</small>
        </div>
        <div class="col-md-3 form-group">
          <label class="form-label">Date</label>
          <input id="rfqDate" name="rfq_date" class="form-control" type="date" />
        </div>
        <div class="col-md-3 form-group">
          <label class="form-label">Delivery</label>
          <input id="deliveryDate" name="delivery_date" class="form-control" type="date" />
        </div>
        <div class="col-md-3 form-group">
          <label class="form-label">Currency</label>
          <select id="currency" name="currency" class="form-select">
            <?php foreach ($currencyList as $cur): ?>
              <option value="<?= esc($cur['code']) ?>" <?= ($defaultCurrency === ($cur['code'] ?? '')) ? 'selected' : '' ?>><?= esc($cur['code']) ?> <?= esc($cur['name'] ?? '') ?></option>
            <?php endforeach; ?>
            <?php if (empty($currencyList)): ?>
              <option value="<?= esc($defaultCurrency) ?>" selected><?= esc($defaultCurrency) ?></option>
            <?php endif; ?>
          </select>
        </div>
      </div>

      <div class="d-flex justify-content-between align-items-center mt-3 mb-1 lines-toolbar">
        <div class="fw-semibold" style="font-size:0.9rem; letter-spacing:.02em;">Lines</div>
      </div>
      <!-- Header for line items to show field names (separate Image and Code columns) -->
      <style>
        /* Page-level layout to keep header stable and single scroll area */
        #rfqpoPage.rfqpo-shell {
          min-height: calc(100vh - 72px);
          display: flex;
          flex-direction: column;
          gap: 0.75rem;
        }
        #rfqpoPage .card {
          flex: 1;
          display: flex;
          flex-direction: column;
          margin-bottom: 0;
        }
        #rfqpoPage .card-body {
          flex: 1;
          overflow-y: auto;
          overflow-x: hidden;
          padding-bottom: 1.25rem;
          padding: 0.75rem 1rem 1.25rem 1rem;
        }
        /* Keep the local section header sticky within the card so top nav/menu stays untouched */
        #rfqpoPage .section-header {
          position: sticky;
          top: 0;
          z-index: 5;
          background: var(--bs-body-bg, #0b1220);
        }
        /* Avoid unintended horizontal scrollbars */
        body { overflow-x: hidden; }

        /* CORELYNK compact line-items styling (dark theme friendly) */
        .lines-row { margin-bottom:0.15rem; }
        .line-total { font-weight:700; font-size:0.95rem; color:var(--bs-body-color); }
        .product-thumb { width:32px !important; height:32px !important; border-radius:6px; }
        .line-header-row { padding: 6px 6px; border-radius:8px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.04); margin-bottom: 6px; }
        .line-header-row .h { font-size: 0.72rem; text-transform: uppercase; letter-spacing: .06em; color: rgba(255,255,255,0.55); }
        .line-header-row .h.text-end { text-align: right; }
        .add-line-btn { padding: .18rem .5rem; font-size: .78rem; border-radius: 6px; }
        /* Autocomplete dropdown styling: make it float and wide so name/code and image show properly */
        .autocomplete-list {
          position: absolute !important;
          z-index: 12000 !important;
          min-width: 320px !important;
          max-width: 520px !important;
          box-shadow: 0 8px 20px rgba(2,6,23,0.6);
          border-radius: 8px;
          overflow: auto;
          background: var(--bs-body-bg, #fff);
          color: var(--bs-body-color, #000);
          padding: 6px 6px !important;
        }
        .autocomplete-list .p-2 { padding: 10px !important; }
        .autocomplete-list img { width:56px !important; height:56px !important; object-fit:cover !important; border-radius:6px; }
        .autocomplete-list .text-muted.small { opacity:0.9; }
        /* When the list is inside an overflow container, allow fixed positioning fallback (actual left/top set by JS) */
        .autocomplete-list[data-fixed="1"] { position: fixed !important; }

        /* Make inputs compact and consistent height */
        .lines-row .form-control-sm { height:30px; padding: .2rem .45rem; border-radius:6px; background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.04); color: var(--bs-body-color); }
        .lines-row .form-control-sm::placeholder { color: rgba(255,255,255,0.35); }
        .lines-row .form-control-sm:focus { box-shadow: none; border-color: rgba(255,255,255,0.08); }
        .lines-row .line-desc { margin-top: 2px; height: 26px; font-size: 0.78rem; }
        .lines-row .product-name { height: 28px; font-size: 0.82rem; }
        .lines-row .form-group { margin-bottom: 0; }

        /* Remove button subtle */
        .removeBtn { padding: .1rem .32rem; font-size:0.7rem; height:24px; line-height:1; }
        .line-total-wrap { display:flex; align-items:center; justify-content:flex-end; gap:6px; }

        /* Totals panel compact styling */
        .totals-panel { width: 320px; max-width: 90vw; background: transparent; border: 1px solid rgba(255,255,255,0.03); }
        .totals-panel .small-label { font-size: .85rem; color: rgba(255,255,255,0.6); text-align: right; }
        .totals-panel .value { font-size: .9rem; color: var(--bs-body-color); text-align: right; font-variant-numeric: tabular-nums; }
        .totals-panel .grand { font-size: 1.02rem; font-weight: 800; color: var(--bs-body-color); text-align: right; font-variant-numeric: tabular-nums; }
        .totals-grid { display: grid; grid-template-columns: 1fr 140px; gap: 4px 10px; align-items: center; }
        .totals-grid hr { grid-column: 1 / -1; margin: 8px 0; border-color: rgba(255,255,255,0.05); }
        
        /* List View: Compact and professional styling */
        #listWrapper { margin-top: 0; padding-top: 0.5rem; }
        .rfqpo-list-top {
          display: flex;
          justify-content: space-between;
          align-items: flex-start;
          gap: 0.75rem;
          flex-wrap: wrap;
        }
        .rfqpo-list-actions {
          display: flex;
          gap: 0.5rem;
          flex-wrap: wrap;
          justify-content: flex-end;
          flex-shrink: 0;
        }
        .rfqpo-list-actions .btn { white-space: nowrap; }
        #listTabs { display: flex; flex-wrap: wrap; gap: 0.5rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--bs-border-color); }
        #listTabs .nav-link { 
          position: relative;
          padding: 0.35rem 0.75rem !important;
          font-size: 0.9rem;
          border: none;
          color: var(--bs-secondary);
          text-decoration: none;
          transition: all 0.15s ease;
          border-bottom: 2px solid transparent;
          margin-bottom: -1px;
        }
        #listTabs .nav-link:hover { 
          color: var(--bs-primary);
          border-bottom-color: var(--bs-primary);
        }
        #listTabs .nav-link.active {
          color: var(--bs-primary);
          border-bottom-color: var(--bs-primary);
          background: none;
        }
        #listTabs .count-badge {
          padding: 0.15rem 0.35rem !important;
          font-size: 0.75rem !important;
          margin-left: 0.35rem;
        }
        
        /* Table: Compact rows with narrow padding */
        .table-hover tbody tr:hover {
          background-color: rgba(255,255,255,0.03);
        }
        .table-sm tbody tr { height: 36px; }
        .table-sm td { padding: 0.35rem 0.5rem; vertical-align: middle; }
        .table-sm thead { background-color: var(--bs-gray-800, #1a1a1a); border-bottom: 1px solid var(--bs-border-color); }
        .table-sm thead th { padding: 0.35rem 0.5rem; font-size: 0.8rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.02em; }
        
        /* Badges: Compact sizing */
        .badge { padding: 0.25rem 0.45rem; font-size: 0.7rem; font-weight: 500; }
        .btn-xs {
          padding: 0.25rem 0.4rem !important;
          font-size: 0.75rem !important;
          line-height: 1;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 28px;
        }
        .btn-xs i { font-size: 0.8rem; }
        
        /* Pagination: Compact styling */
        .pagination-container { margin-top: 1rem; }
        .page-item .page-link { padding: 0.25rem 0.4rem; font-size: 0.75rem; }
        .pagination { gap: 0.2rem; }

        /* ── pl-list: compact card list (matches products list style) ── */
        .pl-list-card { border:1px solid var(--cl-border,var(--bs-border-color)); border-radius:6px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,.08); background:var(--cl-surface,var(--bs-body-bg)); }
        .pl-list-header { display:flex; align-items:center; justify-content:space-between; padding:.45rem .75rem; border-bottom:1px solid var(--cl-border,var(--bs-border-color)); gap:.5rem; flex-wrap:wrap; }
        .pl-list-header-title { font-size:.82rem; font-weight:700; line-height:1; }
        .pl-list-header-sub  { font-size:.66rem; color:var(--cl-text-muted,#6b7280); margin-top:.1rem; }
        .pl-list-filter-bar { display:flex; align-items:center; flex-wrap:wrap; gap:.3rem; padding:.4rem .75rem; border-bottom:1px solid var(--cl-border,var(--bs-border-color)); }
        .pl-list-filter-bar .form-control, .pl-list-filter-bar .form-select { font-size:.74rem; padding:.18rem .45rem; height:26px; border-radius:4px; }
        .pl-search-wrap2 { position:relative; flex:1; min-width:140px; max-width:240px; }
        .pl-search-wrap2 .bi-search { position:absolute; left:.45rem; top:50%; transform:translateY(-50%); font-size:.7rem; color:var(--cl-text-muted,#6b7280); pointer-events:none; }
        .pl-search-wrap2 .form-control { padding-left:1.55rem; }
        .pl-list-btn { display:inline-flex; align-items:center; justify-content:center; height:26px; padding:0 .55rem; font-size:.72rem; border-radius:4px; border:1px solid var(--cl-border,var(--bs-border-color)); background:var(--cl-surface,var(--bs-body-bg)); color:var(--cl-text-secondary,var(--bs-body-color)); cursor:pointer; gap:.28rem; text-decoration:none; white-space:nowrap; transition:all .12s; }
        .pl-list-btn:hover { border-color:var(--cl-primary,var(--bs-primary)); color:var(--cl-primary,var(--bs-primary)); }
        .pl-list-btn.pl-list-btn-primary { background:var(--cl-primary,var(--bs-primary)); border-color:var(--cl-primary,var(--bs-primary)); color:#fff; }
        .pl-list-btn.pl-list-btn-primary:hover { opacity:.88; color:#fff; }
        .pl-list-tabs { display:flex; flex-wrap:wrap; gap:.2rem; padding:.3rem .75rem; border-bottom:1px solid var(--cl-border,var(--bs-border-color)); }
        .pl-list-tab { display:inline-flex; align-items:center; gap:.25rem; padding:.22rem .6rem; font-size:.72rem; font-weight:500; border-radius:4px; border:none; background:transparent; color:var(--cl-text-muted,#6b7280); cursor:pointer; text-decoration:none; transition:all .12s; }
        .pl-list-tab:hover { color:var(--cl-primary,var(--bs-primary)); background:var(--cl-primary-50,rgba(37,99,235,.08)); }
        .pl-list-tab.active { color:var(--cl-primary,var(--bs-primary)); background:var(--cl-primary-50,rgba(37,99,235,.08)); font-weight:600; }
        .pl-list-tab .count-badge { font-size:.62rem; padding:.1rem .3rem; border-radius:3px; }
        /* pl-table */
        .pl-ltable { margin-bottom:0; font-size:.76rem; width:100%; }
        .pl-list-card .pl-ltable thead th { font-size:.62rem !important; text-transform:uppercase; letter-spacing:.05em; color:var(--cl-text-muted,#64748b); background:var(--cl-surface-alt,rgba(0,0,0,.03)); border-bottom:1px solid var(--cl-border,var(--bs-border-color)); padding:.26rem .45rem !important; white-space:nowrap; font-weight:700 !important; }
        body.theme-dark .pl-list-card .pl-ltable thead th { color:#94a3b8 !important; background:#162033 !important; border-bottom-color:#334155 !important; }
        .pl-list-card .pl-ltable tbody td { padding:.22rem .45rem !important; border-bottom:1px solid var(--cl-border-light,var(--bs-border-color)); vertical-align:middle; font-size:.76rem !important; }
        .pl-ltable tbody tr:last-child td { border-bottom:none; }
        body.theme-dark .pl-ltable tbody tr:nth-child(even) td { background:#0f1a2b !important; }
        body.theme-dark .pl-ltable tbody tr:nth-child(odd) td  { background:#1a2740 !important; }
        body.theme-dark .pl-ltable tbody tr:hover td { background:rgba(37,99,235,.1) !important; }
        .pl-list-act { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:4px; border:1px solid var(--cl-border,var(--bs-border-color)); background:var(--cl-surface,var(--bs-body-bg)); color:var(--cl-text-secondary,var(--bs-body-color)); font-size:.7rem; cursor:pointer; text-decoration:none; transition:all .12s; }
        .pl-list-act:hover { border-color:var(--cl-primary,var(--bs-primary)); color:var(--cl-primary,var(--bs-primary)); }
        .pl-list-act + .pl-list-act { margin-left:3px; }
        .pl-list-more-menu { display:none; position:fixed; z-index:1055; min-width:148px; list-style:none; margin:0; padding:.25rem; background:var(--cl-surface,var(--bs-body-bg)); border:1px solid var(--cl-border,var(--bs-border-color)); border-radius:.45rem; box-shadow:0 8px 24px rgba(15,23,42,.12),0 2px 6px rgba(15,23,42,.06); }
        .pl-list-more-menu.is-open { display:block; }
        .pl-list-more-menu li { list-style:none; }
        .pl-lmenu-item { display:flex; align-items:center; gap:.4rem; width:100%; padding:.38rem .65rem; border-radius:.3rem; font-size:.78rem; font-weight:500; color:var(--bs-body-color); background:none; border:none; cursor:pointer; text-decoration:none; white-space:nowrap; }
        .pl-lmenu-item:hover { background:var(--cl-surface-alt,rgba(0,0,0,.05)); }
        .pl-lmenu-item.danger { color:#dc2626; }
        .pl-lmenu-item.danger:hover { background:#fee2e2; }
        .pl-lmenu-divider { height:1px; background:var(--cl-border,var(--bs-border-color)); margin:.2rem .4rem; }
        .pl-list-footer { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.3rem; padding:.35rem .75rem; border-top:1px solid var(--cl-border,var(--bs-border-color)); font-size:.72rem; color:var(--cl-text-muted,#6b7280); }
        .pl-list-footer .pagination { margin:0; gap:2px; }
        .pl-list-footer .page-link { padding:.15rem .42rem; font-size:.72rem; min-width:26px; height:26px; display:inline-flex; align-items:center; justify-content:center; }
        .pl-prod-name { font-size:.76rem; font-weight:600; line-height:1.15; }
        .pl-prod-code { font-size:.65rem; color:var(--cl-text-muted,#64748b); font-family:monospace; }
      </style>
      <div class="row g-2 align-items-center line-header-row">
        <div class="col-md-1 h">Code</div>
        <div class="col-md-1 h">Img</div>
        <div class="col-md-4 h">Product / Description</div>
        <div class="col-md-1 h text-end">Qty</div>
        <div class="col-md-2 h text-end">Unit Price</div>
        <div class="col-md-1 h text-end">Disc %</div>
        <div class="col-md-1 h text-end">Tax %</div>
        <div class="col-md-1 h text-end">Line Total</div>
      </div>
      <div id="linesContainer" class="mb-1"></div>
      <div id="linesFooter" class="d-flex justify-content-between align-items-start mt-2">
        <button type="button" id="addLineBtn" class="btn btn-sm btn-outline-light add-line-btn">+ Add Line</button>
        <div id="totalsMount"></div>
      </div>
      <div class="d-flex justify-content-end gap-2 mt-2">
        <button type="button" id="closeFormBtn" class="btn btn-outline-secondary btn-sm">Close</button>
        <button type="submit" class="btn btn-primary btn-sm">Save RFQ (Draft)</button>
      </div>

      <!-- Bottom section: notes only (compact) -->
      <div class="mt-3 border-top pt-2">
        <div class="row">
          <div class="col-12 form-group">
            <label class="form-label">Notes</label>
            <textarea name="notes" rows="2" class="form-control"></textarea>
          </div>
        </div>
      </div>
  </form>
  </div>

    <div id="listWrapper">
      <div class="pl-list-card">
        <!-- Card Header -->
        <div class="pl-list-header">
          <div>
            <div class="pl-list-header-title"><i class="bi bi-cart3 me-1"></i>RFQ / Purchase Orders</div>
            <div class="pl-list-header-sub">Request for Quotations and confirmed Purchase Orders</div>
          </div>
          <div class="d-flex gap-1 align-items-center">
            <button id="refreshList" class="pl-list-btn" title="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
            <button id="showFormBtn" class="pl-list-btn pl-list-btn-primary"><i class="bi bi-plus-circle"></i> New RFQ</button>
          </div>
        </div>
        <!-- Filter Tabs -->
        <div class="pl-list-tabs" id="listTabs">
          <a href="#" class="pl-list-tab active" data-filter="all">All <span class="count-badge badge bg-secondary ms-1"></span></a>
          <a href="#" class="pl-list-tab" data-filter="open">Open <span class="count-badge badge bg-info ms-1"></span></a>
          <a href="#" class="pl-list-tab" data-filter="partial">Partial Received <span class="count-badge badge bg-warning ms-1"></span></a>
          <a href="#" class="pl-list-tab" data-filter="late">Late PO <span class="count-badge badge bg-danger ms-1"></span></a>
          <a href="#" class="pl-list-tab" data-filter="closed">Completed <span class="count-badge badge bg-success ms-1"></span></a>
          <a href="#" class="pl-list-tab" data-filter="cancelled">Cancelled <span class="count-badge badge bg-danger ms-1"></span></a>
        </div>
        <!-- Filter Bar -->
        <div class="pl-list-filter-bar">
          <div class="pl-search-wrap2">
            <i class="bi bi-search"></i>
            <input id="rfqpoSearch" class="form-control" placeholder="Search PO #, vendor, product…" />
          </div>
          <button id="searchListBtn" class="pl-list-btn"><i class="bi bi-search"></i></button>
          <button id="clearListBtn" class="pl-list-btn"><i class="bi bi-x-circle"></i> Clear</button>
        </div>
        <!-- Table -->
        <div class="table-responsive" style="overflow:visible;">
          <div id="combinedList"><em>Loading…</em></div>
        </div>
        <!-- Footer -->
        <div class="pl-list-footer" id="plListFooter" style="display:none;">
          <span id="plListShowing"></span>
          <nav><ul class="pagination" id="plListPagination"></ul></nav>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Lines Preview Modal -->
<div class="modal fade" id="linesPreviewModal" tabindex="-1" aria-labelledby="linesPreviewModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2 px-3">
        <h6 class="modal-title fw-semibold mb-0" id="linesPreviewModalTitle" style="font-size:.84rem">Products</h6>
        <button type="button" class="btn-close btn-close-sm" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0" id="linesPreviewModalBody">
        <div class="p-4 text-center text-muted" style="font-size:.78rem">Loading…</div>
      </div>
    </div>
  </div>
</div>

<?php
  // Cache-bust autocomplete script so browser picks up latest fixes
  $autoPath = (defined('FCPATH') ? (FCPATH . 'assets/js/corelynk_autocomplete.js') : null);
  $autoV = ($autoPath && @is_file($autoPath)) ? @filemtime($autoPath) : time();
?>
<script src="<?= base_url('assets/js/corelynk_autocomplete.js?v=' . $autoV) ?>"></script>
<script src="<?= base_url('js/list-utils.js') ?>"></script>
<script>
(() => {
  const messages = document.getElementById('messages');
  const linesContainer = document.getElementById('linesContainer');
  const combinedList = document.getElementById('combinedList');

  function msg(html, cls){
    messages.innerHTML = `<div class="alert alert-${cls||'info'}">${html}</div>`;
  }

  function addLineRow(){
    const idx = linesContainer.children.length;
    const row = document.createElement('div');
    row.className = 'row g-1 mb-1 align-items-center lines-row';
    row.innerHTML = `
      <div class="col-md-1 form-group">
        <input name="lines[${idx}][product_code]" class="form-control form-control-sm product-code" placeholder="Code" />
        <input name="lines[${idx}][product_id]" class="product-id" type="hidden" />
        <input name="lines[${idx}][product_variant_id]" class="product-variant-id" type="hidden" />
      </div>
      <div class="col-md-1">
        <img class="product-thumb" alt="" style="object-fit:cover;border-radius:6px;background:#0b1220" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" />
      </div>
      <div class="col-md-4 form-group">
        <input name="lines[${idx}][product_name]" class="form-control form-control-sm product-name" placeholder="Product" />
        <input name="lines[${idx}][description]" class="form-control form-control-sm line-desc" placeholder="Description" />
      </div>
      <div class="col-md-1 form-group">
        <input name="lines[${idx}][qty]" class="form-control form-control-sm line-qty text-end numeric-compact" placeholder="Qty" type="number" step="any"/>
      </div>
      <div class="col-md-2 form-group">
        <input name="lines[${idx}][unit_price]" class="form-control form-control-sm line-price text-end numeric-compact" placeholder="Unit Price" type="number" step="any"/>
      </div>
      <div class="col-md-1 form-group">
        <input name="lines[${idx}][discount_percent]" class="form-control form-control-sm line-discount-percent text-end numeric-compact" placeholder="Disc %" type="number" step="any" />
      </div>
      <div class="col-md-1 form-group">
        <input name="lines[${idx}][tax_percent]" class="form-control form-control-sm line-tax-percent text-end numeric-compact" placeholder="Tax %" type="number" step="any" />
      </div>
      <div class="col-md-1">
        <div class="line-total-wrap">
          <div class="line-total">0.00</div>
          <button type="button" class="btn btn-sm btn-outline-secondary removeBtn" title="Remove">✕</button>
        </div>
      </div>
    `;
    linesContainer.appendChild(row);
    row.querySelector('.removeBtn').addEventListener('click', ()=>row.remove());

    // Attach product autocomplete (works for both code and name).
    // Prefer the new module; fall back to legacy quotation autocomplete if present.
    try {
      var codeInput = row.querySelector('input.product-code');
      var nameInput = row.querySelector('input.product-name');
      if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
        window.CoreLynkAutocomplete.attachProductAutocomplete(codeInput, false, 'purchase');
        window.CoreLynkAutocomplete.attachProductAutocomplete(nameInput, true, 'purchase');
      } else if (window.attachProductAutocomplete) {
        // legacy global from quotation scripts
        window.attachProductAutocomplete(codeInput, false);
        window.attachProductAutocomplete(nameInput, true);
      } else {
        try { if (console && console.warn) console.warn('Product autocomplete not loaded'); } catch (e) {}
      }
    } catch (e) {}

    // Attach change handlers for live per-line calculations (qty, price, discount%, tax%)
    try {
      var qtyEl = row.querySelector('.line-qty');
      var priceEl = row.querySelector('.line-price');
      var discPctEl = row.querySelector('.line-discount-percent');
      var taxPctEl = row.querySelector('.line-tax-percent');
      function computeRow(){
        var q = parseFloat((qtyEl && qtyEl.value) || 0) || 0;
        var p = parseFloat((priceEl && priceEl.value) || 0) || 0;
        var discPct = parseFloat((discPctEl && discPctEl.value) || 0) || 0;
        if (discPct < 0) discPct = 0;
        var lineBase = q * p;
        var discAmount = (discPct / 100) * lineBase;
        var taxable = Math.max(0, lineBase - discAmount);
        var taxPct = parseFloat((taxPctEl && taxPctEl.value) || 0) || 0;
        if (taxPct < 0) taxPct = 0;
        var taxAmount = (taxPct / 100) * taxable;
        var lineTotal = taxable + taxAmount;
        var ltEl = row.querySelector('.line-total');
        if (ltEl) ltEl.textContent = lineTotal.toFixed(2);
        // store computed amounts on DOM so computeTotals can read them without re-calculating heavy logic
        row.dataset.lineBase = lineBase.toFixed(4);
        row.dataset.discountAmount = discAmount.toFixed(4);
        row.dataset.taxAmount = taxAmount.toFixed(4);
        row.dataset.lineTotal = lineTotal.toFixed(4);
        computeTotals();
      }
      if (qtyEl) { qtyEl.addEventListener('input', computeRow); qtyEl.addEventListener('change', computeRow); }
      if (priceEl) { priceEl.addEventListener('input', computeRow); priceEl.addEventListener('change', computeRow); }
      if (discPctEl) { discPctEl.addEventListener('input', computeRow); discPctEl.addEventListener('change', computeRow); }
      if (taxPctEl) { taxPctEl.addEventListener('input', computeRow); taxPctEl.addEventListener('change', computeRow); }
      // run once to initialise dataset
      computeRow();
    } catch (e) {}
  }

  document.getElementById('addLineBtn').addEventListener('click', addLineRow);
  addLineRow();

  // --- Totals panel UI ---
  const totalsWrapper = document.createElement('div');
  totalsWrapper.className = 'd-flex justify-content-end';

  const totalsPanel = document.createElement('div');
  totalsPanel.className = 'card totals-panel';
  totalsPanel.innerHTML = `
    <div class="card-body p-2">
      <div class="totals-grid">
        <div class="small-label">Subtotal</div>
        <div id="subtotalDisplay" class="value">0.00 PKR</div>

        <div class="small-label">Discount</div>
        <div id="totalDiscountDisplay" class="value">0.00 PKR</div>

        <div class="small-label">Tax</div>
        <div id="totalTaxDisplay" class="value">0.00 PKR</div>

        <hr />

        <div class="small-label">Grand Total</div>
        <div id="grandTotalDisplay" class="grand">0.00 PKR</div>
      </div>
    </div>
  `;
  totalsWrapper.appendChild(totalsPanel);
  const totalsMount = document.getElementById('totalsMount');
  if (totalsMount) {
    totalsMount.appendChild(totalsWrapper);
  } else {
    linesContainer.parentNode.insertBefore(totalsWrapper, linesContainer.nextSibling);
  }

  const subtotalDisplay = document.getElementById('subtotalDisplay');
  const totalDiscountDisplay = document.getElementById('totalDiscountDisplay');
  const totalTaxDisplay = document.getElementById('totalTaxDisplay');
  const grandTotalDisplay = document.getElementById('grandTotalDisplay');


  function currentCurrency(){
    try {
      const sel = document.getElementById('currency');
      return (sel && sel.value) ? sel.value : DEFAULT_PURCHASE_CURRENCY;
    } catch (e) {
      return DEFAULT_PURCHASE_CURRENCY;
    }
  }

  function computeTotals(){
    var subtotal = 0;
    var totalDiscount = 0;
    var totalTax = 0;
    var grand = 0;
    linesContainer.querySelectorAll('.row').forEach(function(r){
      try {
        var lineBase = parseFloat(r.dataset.lineBase) || 0;
        var discAmount = parseFloat(r.dataset.discountAmount) || 0;
        var taxAmount = parseFloat(r.dataset.taxAmount) || 0;
        var lineTotal = parseFloat(r.dataset.lineTotal) || 0;
        subtotal += lineBase;
        totalDiscount += discAmount;
        totalTax += taxAmount;
        grand += lineTotal;
      } catch (e) {}
    });
    subtotal = parseFloat(subtotal) || 0;
    totalDiscount = parseFloat(totalDiscount) || 0;
    totalTax = parseFloat(totalTax) || 0;
    grand = parseFloat(grand) || 0;

    const cur = currentCurrency();
    subtotalDisplay.textContent = subtotal.toFixed(2) + ' ' + cur;
    totalDiscountDisplay.textContent = totalDiscount.toFixed(2) + ' ' + cur;
    totalTaxDisplay.textContent = totalTax.toFixed(2) + ' ' + cur;
    grandTotalDisplay.textContent = grand.toFixed(2) + ' ' + cur;
    return { subtotal, totalDiscount, totalTax, grand };
  }

  // initial compute
  computeTotals();

  // update totals labels on currency change
  try {
    const curEl = document.getElementById('currency');
    if (curEl) curEl.addEventListener('change', computeTotals);
  } catch (e) {}

  async function fetchJson(url, opts){
    const resp = await fetch(url, opts || {});
    const ct = resp.headers.get('content-type') || '';
    if (!ct.includes('application/json')) {
      const txt = await resp.text();
      throw new Error('Non-JSON response: ' + txt);
    }
    const j = await resp.json();
    if (!resp.ok) {
      throw new Error(j.error || j.message || 'Request failed');
    }
    return j;
  }

  // Expose helper globally so other inline scripts/IIFEs (modal helpers) can reuse it.
  try { window.fetchJson = fetchJson; } catch (e) {}

  // --- Vendor Select2 dropdown with AJAX search ---
  var vendorSelectEl = document.getElementById('vendorSelect');
  var vendorSelect2InitAttempts = 0;
  function _initVendorSelect2() {
    if (!vendorSelectEl) return;
    if (typeof $ === 'undefined' || !$.fn.select2) {
      return false;
    }
    if ($(vendorSelectEl).data('select2')) return true; // already initialized
    $(vendorSelectEl).select2({
      placeholder: 'Search vendor by name or code…',
      allowClear: true,
      minimumInputLength: 1,
      width: '100%',
      ajax: {
        url: '<?= site_url("vendors/search") ?>',
        type: 'GET',
        dataType: 'json',
        delay: 300,
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        data: function(params) { return { q: params.term || '' }; },
        processResults: function(data) {
          var items = (data && data.data) ? data.data : [];
          return {
            results: items.map(function(v) {
              var label = v.name || '';
              if (v.vendor_code) label += ' (' + v.vendor_code + ')';
              return { id: v.id, text: label };
            })
          };
        },
        cache: true
      }
    });
    // When vendor is selected/changed, attempt to load vendor cost for any existing product lines
    try {
      var basePath = (window.APP_BASE && window.APP_BASE !== '') ? window.APP_BASE : (function(){ var parts = location.pathname.split('/'); return (parts && parts.length>1)?'/'+parts[1]:''; })();
      $(vendorSelectEl).on('change', function(){
        var vid = this.value || null;
        if (!vid) return;
        linesContainer.querySelectorAll('.row').forEach(function(r){
          try {
            var pid = (r.querySelector('.product-id') || {}).value || null;
            var priceEl = r.querySelector('.line-price');
            if (!pid || !priceEl) return;
            var u = basePath + '/product-vendors/price?product_id=' + encodeURIComponent(pid) + '&vendor_id=' + encodeURIComponent(vid);
            fetch(u).then(function(res){ return res.json(); }).then(function(j){
              if (j && j.success && typeof j.cost_price !== 'undefined') {
                try {
                  var existing = parseFloat(priceEl.value);
                  var incoming = parseFloat(j.cost_price);
                  if (!(existing > 0) && !isNaN(incoming) && incoming > 0) {
                    priceEl.value = incoming.toFixed(2);
                    priceEl.dataset.currency = j.currency || 'PKR';
                    priceEl.title = j.currency || 'PKR';
                    try { priceEl.dispatchEvent(new Event('change')); } catch (e) {}
                  }
                } catch (e) {}
              }
            }).catch(function(err){ try { if (console && console.error) console.error('vendor price load failed', err); } catch(e){} });
          } catch (e) {}
        });
      });
    } catch (e) {}
    return true;
  }
  function initVendorSelect2WithRetry() {
    if (_initVendorSelect2()) return;
    vendorSelect2InitAttempts += 1;
    if (vendorSelect2InitAttempts > 20) {
      console.warn('Vendor Select2 initialization failed after multiple attempts');
      return;
    }
    setTimeout(initVendorSelect2WithRetry, 150);
  }
  document.addEventListener('DOMContentLoaded', initVendorSelect2WithRetry);
  window.addEventListener('load', initVendorSelect2WithRetry);
  initVendorSelect2WithRetry();



  document.getElementById('rfqpoForm').addEventListener('submit', async (ev)=>{
    ev.preventDefault();
    console.log('Save clicked');
    const form = ev.target;
    // format date inputs as DD-MM-YYYY before sending (controller accepts either)
    function toDDMMYYYY(v){ if(!v) return null; try{ var parts = v.split('-'); if(parts.length===3) return parts[2]+'-'+parts[1]+'-'+parts[0]; var d=new Date(v); if(isNaN(d)) return v; var dd=('0'+d.getDate()).slice(-2); var mm=('0'+(d.getMonth()+1)).slice(-2); var yyyy=d.getFullYear(); return dd+'-'+mm+'-'+yyyy; }catch(e){ return v; } }

    // Get vendor_id from the Select2 dropdown
    const vendorSelectElement = document.getElementById('vendorSelect');
    const vendorIdValue = vendorSelectElement ? vendorSelectElement.value : null;

    const data = {
      rfq_number: form.rfq_number.value || null,
      vendor_id: vendorIdValue ? parseInt(vendorIdValue, 10) : null,
      rfq_date: toDDMMYYYY(form.rfq_date ? form.rfq_date.value : null),
      delivery_date: toDDMMYYYY(form.delivery_date ? form.delivery_date.value : null),
      currency: (form.currency && form.currency.value) ? form.currency.value : DEFAULT_PURCHASE_CURRENCY,
      notes: form.notes.value || null,
      subtotal: 0,
      discount: 0,
      tax_amount: 0,
      grand_total: 0,
      status: 'draft',
      lines: []
    };

    // gather lines and compute per-line discount/tax from dataset
    try {
      linesContainer.querySelectorAll('.row').forEach((d)=>{
        console.log('Processing line row', d);
        const pid = (d.querySelector('.product-id') || {}).value;
        const pvid = (d.querySelector('.product-variant-id') || {}).value;
        const parsedVariantId = parseInt(pvid || '', 10);
        const qty = parseFloat((d.querySelector('.line-qty') || {}).value) || 0;
        const up = parseFloat((d.querySelector('.line-price') || {}).value) || 0;
        const desc = (d.querySelector('.line-desc') || {}).value || null;
        const discPct = parseFloat((d.querySelector('.line-discount-percent') || {}).value) || 0;
        const taxPct = parseFloat((d.querySelector('.line-tax-percent') || {}).value) || 0;
        if (!qty || qty <= 0) return; // skip empty lines
        if (!up || up <= 0) {
          msg('Each line must have a unit price greater than 0.', 'warning');
          throw new Error('Validation failed: unit_price <= 0');
        }
        // read computed amounts from dataset (these are kept in sync by computeRow)
        const lineBase = parseFloat(d.dataset.lineBase) || (qty * up);
        const discountAmount = parseFloat(d.dataset.discountAmount) || ((discPct/100) * lineBase);
        const taxAmount = parseFloat(d.dataset.taxAmount) || ( (taxPct/100) * Math.max(0, lineBase - discountAmount) );
        const lineTotal = parseFloat(d.dataset.lineTotal) || (lineBase - discountAmount + taxAmount);

        data.lines.push({
          product_id: pid ? parseInt(pid,10):null,
          product_variant_id: (Number.isFinite(parsedVariantId) && parsedVariantId > 0) ? parsedVariantId : null,
          qty: qty,
          unit_price: up,
          discount_percent: discPct,
          discount: parseFloat(discountAmount.toFixed(2)),
          tax_percent: taxPct,
          tax_amount: parseFloat(taxAmount.toFixed(2)),
          line_total: parseFloat(lineTotal.toFixed(2)),
          description: desc
        });
      });
    } catch (e) {
      console.error('Submission validation failed', e);
      return; // validation message already shown
    }

    // ensure we have at least one line
    if (data.lines.length === 0) {
      msg('Add at least one line with quantity > 0.', 'warning');
      return;
    }

    // vendor validation
    const vendorIdVal = vendorIdValue ? parseInt(vendorIdValue, 10) : null;
    if (!vendorIdVal || isNaN(vendorIdVal)) {
      const vendorValidation = document.getElementById('vendorValidation');
      if (vendorValidation) vendorValidation.style.display = 'block';
      msg('Please select a vendor before saving.', 'warning');
      if (vendorSelectElement) vendorSelectElement.focus();
      return;
    }

  // compute totals using per-line dataset sums
  const totals = computeTotals();
  data.subtotal = parseFloat(totals.subtotal.toFixed(2));
  data.total_discount = parseFloat(totals.totalDiscount.toFixed(2));
  data.total_tax = parseFloat(totals.totalTax.toFixed(2));
  data.grand_total = parseFloat(totals.grand.toFixed(2));
  // keep backward-compatible fields as well
  data.discount = parseFloat(totals.totalDiscount.toFixed(2));
  data.tax_amount = parseFloat(totals.totalTax.toFixed(2));

    // Submit with loading state
    const submitBtn = form.querySelector('button[type="submit"]');
    const rfqId = form.rfq_id ? (form.rfq_id.value || null) : null;
    
    // Check if we're editing a PO by looking at URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const isEditingPo = !!urlParams.get('edit_po');
    
    // Determine the correct endpoint
    let endpoint;
    let actionLabel;
    if (rfqId && isEditingPo) {
      endpoint = '<?= site_url("new-purchase-orders/") ?>' + rfqId + '/update';
      actionLabel = 'PO';
    } else if (rfqId) {
      endpoint = '<?= site_url("new-purchase-rfqs/") ?>' + rfqId + '/update';
      actionLabel = 'RFQ';
    } else {
      endpoint = '<?= site_url("new-purchase-rfqs/create") ?>';
      actionLabel = 'RFQ';
    }
    
    try {
      console.log('Submitting ' + actionLabel + ' payload', data);
      console.log('POST -> ' + endpoint);
      if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }
      const j = await fetchJson(endpoint, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
      });
      console.log('Server response', j);
      const newId = (j && (j.rfq_id || j.po_id || j.id)) ? (j.rfq_id || j.po_id || j.id) : rfqId;
      if (!rfqId && newId) {
        form.rfq_id.value = newId;
        if (j && j.rfq_number) form.rfq_number.value = j.rfq_number;
        if (j && j.po_number) form.rfq_number.value = j.po_number;
      }
      msg(rfqId ? (actionLabel + ' updated.') : (actionLabel + ' saved.'), 'success');
      try { await loadCombined(); } catch (e) {}
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save RFQ (Draft)'; }
    } catch (e) {
      try { if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save RFQ (Draft)'; } } catch (er) {}
      msg(e.message || ('Failed to save ' + actionLabel), 'danger');
    }
  });

  // Format total with commas or K/M notation
  function formatTotal(value, currency) {
    if (value === null || isNaN(value)) return '—';
    const num = parseFloat(value);
    let formatted = '';
    if (Math.abs(num) >= 1000000) {
      formatted = (num / 1000000).toFixed(2).replace(/\.?0+$/, '') + 'M';
    } else if (Math.abs(num) >= 1000) {
      formatted = (num / 1000).toFixed(2).replace(/\.?0+$/, '') + 'K';
    } else {
      formatted = num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    return `${currency} ${formatted}`;
  }

  function renderCombinedWithPagination(paginationData){
    const {rows, totalRows} = paginationData;
    if(!rows || rows.length===0){ combinedList.innerHTML = '<div class="alert alert-info m-2 mb-0">No records found.</div>'; return; }

    let html = '<table class="table table-hover pl-ltable mb-0">';
    html += '<thead><tr>';
    html += '<th width="32" class="text-center" style="color:var(--cl-text-muted,#6b7280)">#</th>';
    html += '<th style="width:11%">PO / RFQ #</th>';
    html += '<th style="width:14%">Vendor</th>';
    html += '<th style="width:26%">Product</th>';
    html += '<th style="width:9%">Created</th>';
    html += '<th style="width:9%">Delivery</th>';
    html += '<th style="width:10%; text-align:right">Total</th>';
    html += '<th style="width:13%">Status</th>';
    html += '<th style="width:6%; text-align:right">Actions</th>';
    html += '</tr></thead><tbody>';

    rows.forEach((r, idx) => {
      const viewUrl = r.type === 'RFQ' ? `<?= site_url('purchases/rfq/') ?>${r.id}` : `<?= site_url('purchases/po/') ?>${r.id}`;
      html += `<tr>`;
      // # serial
      html += `<td class="text-center" style="font-size:.68rem;color:var(--cl-text-muted,#64748b);font-variant-numeric:tabular-nums">${idx+1}</td>`;
      // PO/RFQ number
      const displayNum = escHtml(r.number || r.id || '');
      html += `<td><strong style="font-size:.76rem">${displayNum}</strong></td>`;
      // Vendor
      html += `<td style="font-size:.76rem">${escHtml(r.vendor_name || '—')}</td>`;
      
      // Product cell — show product name first, code + variant info as sub-line
      const spName = r.sample_product || '';
      const spCode = r.sample_product_code || '';
      const spVariantCode = r.sample_variant_code || '';
      const spVariantName = r.sample_variant_name || '';
      const displayCode = spVariantCode || spCode;
      const cnt = r.line_count ? parseInt(r.line_count, 10) : 0;
      const moreCount = cnt > 1 ? cnt - 1 : 0;
      const moreLink = moreCount ? ` <a href="#" class="view-more-lines" data-id="${r.id}" data-type="${r.type}" style="font-size:.65rem;white-space:nowrap">+${moreCount} more</a>` : '';
      let prodHtml = '';
      if (spName || displayCode) {
        const nameLine = spName ? `<div class="pl-prod-name">${escHtml(spName)}</div>` : '';
        let subParts = [];
        if (displayCode) subParts.push(`<span class="pl-prod-code">${escHtml(displayCode)}</span>`);
        if (spVariantName) subParts.push(`<span style="font-size:.65rem;color:var(--cl-text-muted,#64748b)">${escHtml(spVariantName)}</span>`);
        const subContent = subParts.join(' <span style="color:#ccc">·</span> ');
        const subLine = (subContent || moreLink) ? `<div style="margin-top:.1rem;display:flex;align-items:center;gap:.25rem;flex-wrap:wrap">${subContent}${moreLink}</div>` : '';
        prodHtml = nameLine + subLine;
      } else {
        prodHtml = '<span class="text-muted" style="font-size:.72rem">—</span>';
      }
      html += `<td class="products-col" data-id="${r.id}" data-type="${r.type}">${prodHtml}</td>`;

      // Created date
      let dateStr = '—';
      if (r.created_at) {
        const d = r.created_at.substring(0,10);
        const parts = d.split('-');
        dateStr = parts.length === 3 ? `${parts[2]}-${parts[1]}-${parts[0]}` : d;
      }
      html += `<td style="font-size:.76rem;white-space:nowrap">${dateStr}</td>`;

      // Delivery date
      let deliveryStr = '—';
      if (r.delivery_date) {
        const dd = r.delivery_date.substring(0,10);
        const dparts = dd.split('-');
        deliveryStr = dparts.length === 3 ? `${dparts[2]}-${dparts[1]}-${dparts[0]}` : dd;
      }
      html += `<td style="font-size:.76rem;white-space:nowrap">${deliveryStr}</td>`;
      
      // Total - prioritize grand_total (which includes taxes) over total
      const totalVal = (r.grand_total !== undefined && r.grand_total !== null) ? r.grand_total : ((r.total !== undefined && r.total !== null) ? r.total : (r.subtotal !== undefined && r.subtotal !== null ? r.subtotal : null));
      const rowCurrency = r.currency || DEFAULT_PURCHASE_CURRENCY;
      html += `<td style="font-size:.76rem;text-align:right;white-space:nowrap">${formatTotal(totalVal, rowCurrency)}</td>`;
      
      // Status badges
      let statusHtml = '';
      if (r.type === 'RFQ') {
        const rfqState = (r.state || 'draft').toLowerCase();
        statusHtml = `<span class="badge" style="background:#003366;font-size:.68rem;color:#fff">RFQ</span> `;
        if (rfqState === 'draft') statusHtml += `<span class="badge" style="background:#ffc107;font-size:.68rem;color:#000">Draft</span>`;
        else if (rfqState === 'sent') statusHtml += `<span class="badge bg-info" style="font-size:.68rem;color:#000">Sent</span>`;
        else if (rfqState === 'cancelled') statusHtml += `<span class="badge bg-danger" style="font-size:.68rem">Cancelled</span>`;
      } else {
        const recStatus = r.receipt_status || 'open';
        statusHtml = `<span class="badge bg-primary" style="font-size:.68rem">PO</span> `;
        if (recStatus === 'open') statusHtml += `<span class="badge bg-info" style="font-size:.68rem;color:#000">Open</span>`;
        else if (recStatus === 'partial_received') statusHtml += `<span class="badge" style="background:#ffc107;font-size:.68rem;color:#000">Partial</span>`;
        else if (recStatus === 'fully_received') statusHtml += `<span class="badge bg-success" style="font-size:.68rem">Received</span>`;
        else if (recStatus === 'closed') statusHtml += `<span class="badge text-bg-secondary" style="font-size:.68rem">Closed</span>`;
        else if (recStatus === 'cancelled') statusHtml += `<span class="badge bg-danger" style="font-size:.68rem">Cancelled</span>`;
      }
      html += `<td>${statusHtml}</td>`;
      
      // Actions
      html += `<td style="text-align:right;white-space:nowrap;overflow:visible" onclick="event.stopPropagation()">`;
      html += `<a href="${viewUrl}" class="pl-list-act" title="View" onclick="event.stopPropagation()"><i class="bi bi-eye"></i></a>`;
      html += `<div class="dropdown" style="display:inline-block;position:relative">`;
      html += `<button class="pl-list-act ms-1" type="button" data-bs-toggle="dropdown" aria-expanded="false" onclick="event.stopPropagation()"><i class="bi bi-three-dots-vertical"></i></button>`;
      html += `<ul class="dropdown-menu dropdown-menu-end" style="font-size:.78rem;min-width:120px;">`;
      if (r.type === 'RFQ') {
        if (r.state === 'draft' || r.state === 'sent') html += `<li><button class="dropdown-item convertRfqBtn" data-id="${r.id}" style="color:#28a745;font-weight:500" onclick="event.stopPropagation()"><i class="bi bi-arrow-right-circle me-1"></i>RFQ to PO</button></li>`;
        if (r.state === 'draft') {
          html += `<li><button class="dropdown-item editBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-pencil me-1"></i>Edit</button></li>`;
          html += `<li><hr class="dropdown-divider"></li>`;
          html += `<li><button class="dropdown-item text-danger deleteBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-trash me-1"></i>Delete</button></li>`;
        }
        if (r.state !== 'cancelled') html += `<li><button class="dropdown-item text-danger cancelBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-x-circle me-1"></i>Cancel</button></li>`;
      }
      if (r.type === 'PO') {
        if (r.state === 'draft' || r.state === 'pending') {
          html += `<li><button class="dropdown-item editPoBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-pencil me-1"></i>Edit</button></li>`;
          html += `<li><button class="dropdown-item confirmPoBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-check2-square me-1"></i>Confirm</button></li>`;
          html += `<li><hr class="dropdown-divider"></li>`;
          html += `<li><button class="dropdown-item text-danger deletePoBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-trash me-1"></i>Delete</button></li>`;
        }
        if (r.state !== 'cancelled') html += `<li><button class="dropdown-item text-danger cancelPoBtn" data-id="${r.id}" onclick="event.stopPropagation()"><i class="bi bi-x-circle me-1"></i>Cancel</button></li>`;
      }
      html += `</ul></div></td>`;
      html += `</tr>`;
    });

    html += '</tbody></table>';
    
    combinedList.innerHTML = html;
    wireUpTableEvents();
  }

  // Helper function to escape HTML (must be global for use in event handlers)
  function escHtml(s){ const d=document.createElement('div'); d.textContent=String(s||''); return d.innerHTML; }

  function wireUpTableEvents(){
    // Delegate clicks for view-more lines modal (only "+X more" link shows modal)
    combinedList.addEventListener('click', async function(ev){
      const a = ev.target.closest && ev.target.closest('.view-more-lines');
      if (!a) return;
      ev.preventDefault();
      const id   = a.dataset.id;
      const type = (a.dataset.type || '').toUpperCase();

      // Show modal immediately
      const modalEl = document.getElementById('linesPreviewModal');
      const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
      const title = type === 'PO' ? 'PO' : 'RFQ';
      document.getElementById('linesPreviewModalTitle').textContent = `All products in ${title} #${id}`;
      const body = document.getElementById('linesPreviewModalBody');
      body.innerHTML = '<div class="p-4 text-center text-muted" style="font-size:.78rem">Loading…</div>';
      modal.show();

      // Fetch all lines
      try {
        const url = type === 'PO'
          ? '<?= site_url("new-purchase-orders/") ?>' + id
          : '<?= site_url("new-purchase-rfqs/") ?>' + id;
        const j = await fetchJson(url, { method:'GET', headers:{'Accept':'application/json'} });
        const lines = (j.data && Array.isArray(j.data.lines)) ? j.data.lines : [];

        if (!lines || lines.length === 0) {
          body.innerHTML = '<div class="p-3 text-muted text-center" style="font-size:.78rem">No product lines found.</div>';
          return;
        }

        let tbl = '<table class="table table-sm mb-0" style="font-size:.76rem"><thead><tr>'
          + '<th style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:700;padding:.3rem .6rem;white-space:nowrap">Product</th>'
          + '<th style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:700;padding:.3rem .6rem;text-align:right">Qty</th>'
          + '<th style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:700;padding:.3rem .6rem;text-align:right">Unit Price</th>'
          + '<th style="font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;color:#64748b;font-weight:700;padding:.3rem .6rem;text-align:right">Total</th>'
          + '</tr></thead><tbody>';

        lines.forEach(function(ln) {
          const pName     = escHtml(ln.product_name || ln.description || '—');
          const pCode     = ln.product_code || '';
          const vCode     = ln.variant_code || ln.variant_art_number || '';
          const vName     = ln.variant_name || '';
          const qtyRaw    = ln.qty !== undefined ? ln.qty : (ln.quantity !== undefined ? ln.quantity : '—');
          const qty       = qtyRaw !== '—' ? parseFloat(qtyRaw).toFixed(2) : '—';
          const price     = ln.unit_price !== undefined ? parseFloat(ln.unit_price) : null;
          const total     = ln.line_total !== undefined ? parseFloat(ln.line_total) : (price !== null && qtyRaw !== '—' ? price * parseFloat(qtyRaw) : null);
          const currency  = ln.currency || DEFAULT_PURCHASE_CURRENCY;

          // Build product cell: name then variant/code sub-line
          const codeDisplay = vCode || pCode;
          let subParts = [];
          if (codeDisplay) subParts.push(`<span style="font-family:ui-monospace,monospace;font-size:.65rem;color:#64748b">${escHtml(codeDisplay)}</span>`);
          if (vName)       subParts.push(`<span style="font-size:.68rem;color:#64748b">${escHtml(vName)}</span>`);
          const subLine = subParts.length ? `<div style="margin-top:.08rem;display:flex;gap:.3rem;align-items:center;flex-wrap:wrap">${subParts.join('<span style="color:#ccc">·</span>')}</div>` : '';

          const priceStr = price !== null ? formatTotal(price, currency) : '—';
          const totalStr = total !== null ? formatTotal(total, currency) : '—';

          tbl += `<tr>
            <td style="padding:.3rem .6rem"><div style="font-size:.76rem;font-weight:600">${pName}</div>${subLine}</td>
            <td style="padding:.3rem .6rem;text-align:right;white-space:nowrap">${escHtml(String(qty))}</td>
            <td style="padding:.3rem .6rem;text-align:right;white-space:nowrap">${priceStr}</td>
            <td style="padding:.3rem .6rem;text-align:right;white-space:nowrap">${totalStr}</td>
          </tr>`;
        });

        tbl += '</tbody></table>';
        body.innerHTML = tbl;
      } catch(e) {
        console.error('Failed to load PO/RFQ lines', e);
        body.innerHTML = '<div class="p-3 text-danger text-center" style="font-size:.78rem">Failed to load lines. ' + (e.message ? e.message : 'Please refresh the page.') + '</div>';
      }
    });

    // Attach event handlers for buttons
    combinedList.querySelectorAll('.editBtn').forEach(b => b.addEventListener('click', async (ev)=>{
      const id = b.dataset.id;
      try {
        const j = await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+id, { method:'GET', headers:{'Accept':'application/json'} });
        if (!j.success || !j.data) throw new Error('Failed to load RFQ');
        const form = document.getElementById('rfqpoForm');
        if (!form) return;
        form.reset();
        form.rfq_id.value = j.data.id || '';
        form.rfq_number.value = j.data.rfq_number || '';
        
        // Set vendor in Select2 dropdown
        const loadedVendorId = j.data.vendor_id || '';
        const vendorName = j.data.vendor_name || '';
        if (typeof $ !== 'undefined' && loadedVendorId) {
          var newOpt = new Option(vendorName || ('Vendor #' + loadedVendorId), loadedVendorId, true, true);
          $('#vendorSelect').empty().append(newOpt).trigger('change');
        } else {
          if (typeof $ !== 'undefined') $('#vendorSelect').val(null).trigger('change');
        }
        
        form.rfq_date.value = (j.data.rfq_date || '').toString().substring(0, 10);
        form.delivery_date.value = (j.data.delivery_date || '').toString().substring(0, 10);
        form.notes.value = j.data.notes || '';
        if (form.currency && j.data.currency) {
          form.currency.value = j.data.currency;
        }
        // Don't trigger vendor change yet - wait until lines are loaded
        linesContainer.innerHTML = '';
        (j.data.lines || []).forEach((ln, idx) => {
          addLineRow();
          const row = linesContainer.children[idx];
          if (!row) return;
          (row.querySelector('.product-id')||{}).value = ln.product_id || '';
          const loadPvid = parseInt((ln.product_variant_id ?? ''), 10) || 0;
          const loadVid = parseInt((ln.variant_id ?? ''), 10) || 0;
          (row.querySelector('.product-variant-id')||{}).value = loadPvid > 0 ? loadPvid : (loadVid > 0 ? loadVid : '');
          
          // Load variant code if available, otherwise product code
          const variantCode = ln.variant_code || ln.variant_art_number || '';
          const productCode = ln.product_code || ln.code || '';
          (row.querySelector('.product-code')||{}).value = variantCode || productCode || '';
          
          const pname = (ln.product_name || ln.name || '').toString();
          (row.querySelector('.product-name')||{}).value = pname;
          
          // Load description with variant name if available
          const pdesc = (ln.description || '').toString();
          const descInput = row.querySelector('.line-desc');
          if (descInput) {
            descInput.value = (pdesc.trim().toLowerCase() === pname.trim().toLowerCase()) ? '' : pdesc;
            if (ln.variant_name) {
              descInput.value = ln.variant_name;
            }
          }
          
          // Load product image
          const imgEl = row.querySelector('.product-thumb');
          if (imgEl && ln.product_image) {
            imgEl.src = ln.product_image;
            imgEl.style.backgroundColor = '#0b1220';
          } else if (imgEl && ln.image) {
            imgEl.src = ln.image;
            imgEl.style.backgroundColor = '#0b1220';
          }
          
          (row.querySelector('.line-qty')||{}).value = ln.qty || '';
          (row.querySelector('.line-price')||{}).value = (ln.unit_price !== undefined && ln.unit_price !== null) ? ln.unit_price : (ln.unit_cost || '');
          (row.querySelector('.line-discount-percent')||{}).value = ln.discount_percent || 0;
          (row.querySelector('.line-tax-percent')||{}).value = ln.tax_percent || 0;
          try {
            (row.querySelector('.line-qty')||{}).dispatchEvent(new Event('input'));
            (row.querySelector('.line-price')||{}).dispatchEvent(new Event('input'));
            (row.querySelector('.line-discount-percent')||{}).dispatchEvent(new Event('input'));
            (row.querySelector('.line-tax-percent')||{}).dispatchEvent(new Event('input'));
          } catch (e) {}
        });
        // Keep saved RFQ prices intact while editing
        computeTotals();
        // Show form, hide list
        document.getElementById('formWrapper').style.display = 'block';
        document.getElementById('listWrapper').style.display = 'none';
        const ftEdit = document.getElementById('formTitle');
        if (ftEdit) ftEdit.textContent = 'Edit RFQ #' + (j.data.rfq_number || id);
      } catch(e){ msg(e.message, 'danger'); }
    }));

    combinedList.querySelectorAll('.deleteBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      if (!confirm('Delete this RFQ? This cannot be undone.')) return;
      try {
        await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+id+'/delete', { method:'POST', headers:{'Content-Type':'application/json'} });
        msg('RFQ deleted.', 'success');
        await loadCombined();
      } catch(e){ msg(e.message, 'danger'); }
    }));

    // PO actions: View -> navigate to full-page PO detail route
    combinedList.querySelectorAll('.viewPoBtn').forEach(b => b.addEventListener('click', (ev)=>{
      // allow normal link navigation but ensure we use the intended route if JS intercepts
      const href = b.getAttribute('href');
      if (href) {
        ev.preventDefault();
        window.location.href = href;
      }
    }));

    // RFQ actions: View -> navigate to full-page RFQ detail route
    combinedList.querySelectorAll('.viewRfqBtn').forEach(b => b.addEventListener('click', (ev)=>{
      const href = b.getAttribute('href');
      if (href) {
        ev.preventDefault();
        window.location.href = href;
      }
    }));

    combinedList.querySelectorAll('.editPoBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      window.location.href = '<?= site_url("newpurchaseui/rfqpo") ?>?edit_po=' + id;
    }));

    combinedList.querySelectorAll('.deletePoBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      if (!confirm('Delete this PO? This is permanent.')) return;
      try {
        await fetchJson('<?= site_url("new-purchase-orders/") ?>'+id+'/delete', { method:'POST', headers:{'Content-Type':'application/json'} });
        msg('PO deleted.', 'success');
        await loadCombined();
      } catch(e){ msg(e.message, 'danger'); }
    }));

    combinedList.querySelectorAll('.cancelPoBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      const reason = prompt('Cancel reason (optional)') || null;
      if (!confirm('Are you sure you want to cancel this PO?')) return;
      try {
        await fetchJson('<?= site_url("new-purchase-orders/") ?>'+id+'/cancel', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ reason }) });
        msg('PO cancelled.', 'success');
        await loadCombined();
      } catch(e){ msg(e.message, 'danger'); }
    }));

    combinedList.querySelectorAll('.acceptBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      const deliveryDate = prompt('Delivery date (YYYY-MM-DD)');
      if (!deliveryDate) {
        msg('Delivery date is required to confirm the RFQ.', 'warning');
        return;
      }
      if (!confirm('Confirm this RFQ and create a confirmed PO?')) return;
      try {
        const j = await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+id+'/accept', {
          method:'POST',
          headers:{'Content-Type':'application/json','Accept':'application/json'},
          body: JSON.stringify({ delivery_date: deliveryDate, confirm_po: true })
        });
        msg('RFQ confirmed. PO created/confirmed: '+(j.po_id || ''), 'success');
        await loadCombined();
      } catch(e){ msg(e.message, 'danger'); }
    }));

    combinedList.querySelectorAll('.convertRfqBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      
      // Create a nice modal dialog for date selection
      const defaultDate = new Date();
      defaultDate.setDate(defaultDate.getDate() + 30); // Default to 30 days from now
      const defaultDateStr = defaultDate.toISOString().split('T')[0];
      
      const modal = document.createElement('div');
      modal.innerHTML = `
        <div style="position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center; z-index:9999;">
          <div style="background:white; padding:2rem; border-radius:8px; box-shadow:0 4px 20px rgba(0,0,0,0.3); max-width:400px; width:90%;">
            <h5 style="margin-bottom:1rem; color:#333;">Convert RFQ to Purchase Order</h5>
            <p style="margin-bottom:1.5rem; color:#666; font-size:0.95rem;">Select the delivery date for this PO:</p>
            <input type="date" id="deliveryDateInput" value="${defaultDateStr}" style="width:100%; padding:0.5rem; border:1px solid #ddd; border-radius:4px; font-size:1rem; margin-bottom:1.5rem;">
            <div style="display:flex; gap:0.5rem;">
              <button id="confirmConvert" class="btn btn-success" style="flex:1; padding:0.5rem;">Convert to PO</button>
              <button id="cancelConvert" class="btn btn-secondary" style="flex:1; padding:0.5rem;">Cancel</button>
            </div>
          </div>
        </div>
      `;
      
      document.body.appendChild(modal);
      
      document.getElementById('cancelConvert').addEventListener('click', () => {
        modal.remove();
      });
      
      document.getElementById('confirmConvert').addEventListener('click', async () => {
        const deliveryDate = document.getElementById('deliveryDateInput').value;
        if (!deliveryDate) {
          alert('Please select a delivery date');
          return;
        }
        
        modal.remove();
        
        try {
          const j = await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+id+'/accept', {
            method:'POST',
            headers:{'Content-Type':'application/json','Accept':'application/json'},
            body: JSON.stringify({ delivery_date: deliveryDate, confirm_po: true })
          });
          msg('✓ RFQ successfully converted to PO!', 'success');
          await loadCombined();
        } catch(e){ msg(e.message, 'danger'); }
      });
    }));

    combinedList.querySelectorAll('.cancelBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      const reason = prompt('Cancel reason (optional)') || null;
      try { await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+id+'/cancel', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({reason}) }); await loadCombined(); }
      catch(e){ msg(e.message, 'danger'); }
    }));

    combinedList.querySelectorAll('.confirmPoBtn').forEach(b => b.addEventListener('click', async ()=>{
      const id = b.dataset.id;
      try { await fetchJson('<?= site_url("new-purchase-orders/confirm/") ?>'+id, { method:'POST' }); await loadCombined(); }
      catch(e){ msg(e.message, 'danger'); }
    }));
  }

  async function loadCombined(){
    combinedList.innerHTML = 'Loading...';
    try {
      const q = (document.getElementById('rfqpoSearch') || {}).value || '';
      const qs = q ? ('?q=' + encodeURIComponent(q)) : '';
      const [rfqs, pos] = await Promise.all([
        fetchJson('<?= site_url("new-purchase-rfqs") ?>' + qs, { method:'GET', headers:{'Accept':'application/json'} }),
        fetchJson('<?= site_url("new-purchase-orders") ?>' + qs, { method:'GET', headers:{'Accept':'application/json'} })
      ]);

      // Build vendor lookup from RFQs (which have vendor_name)
      const vendorMap = {};
      (rfqs.data || []).forEach(r => {
        if (r.vendor_id && r.vendor_name) vendorMap[r.vendor_id] = r.vendor_name;
      });

      const rows = [];
      // Skip RFQs that have already been accepted or confirmed (they become POs)
      (rfqs.data || []).forEach(r => {
        const st = (r.status || '').toLowerCase();
        if (st === 'accepted' || st === 'confirmed') return;
        rows.push({
          type: 'RFQ',
          id: r.id,
          number: r.rfq_number,
          vendor_id: r.vendor_id,
          vendor_name: r.vendor_name,
          state: r.status,
          created_at: r.created_at,
          subtotal: r.subtotal || null,
          grand_total: r.grand_total || null,
          currency: r.currency || DEFAULT_PURCHASE_CURRENCY,
          sample_product: r.sample_product || null,
          sample_product_code: r.sample_product_code || null,
          sample_variant_code: r.sample_variant_code || null,
          sample_variant_name: r.sample_variant_name || null,
          sample_description: r.sample_description || null,
          delivery_date: r.delivery_date || null,
          line_count: r.line_count || 0
        });
      });
      (pos.data || []).forEach(p => rows.push({
        type: 'PO',
        id: p.id,
        number: p.po_number,
        vendor_id: p.vendor_id,
        vendor_name: p.vendor_name || ((p.vendor_id && vendorMap[p.vendor_id]) ? vendorMap[p.vendor_id] : ''),
        state: p.status,
        created_at: p.created_at,
        subtotal: p.subtotal || null,
        total: p.total || null,
        grand_total: p.grand_total || null,
        currency: p.currency || DEFAULT_PURCHASE_CURRENCY,
        delivery_date: p.delivery_date || null,
        grn_id: p.grn_id || null,
        receipt_status: p.receipt_status || 'open',
        line_count: p.line_count || 0,
        sample_product: p.sample_product || null,
        sample_product_code: p.sample_product_code || null,
        sample_variant_code: p.sample_variant_code || null,
        sample_variant_name: p.sample_variant_name || null,
      }));

      // fallback: if vendor_name still missing, show vendor_id
      rows.forEach(rr => { rr.vendor_name = rr.vendor_name || rr.vendor_id; });

      // Sort by created_at then id (best-effort)
      rows.sort((a,b)=>{
        const da = a.created_at || '';
        const db = b.created_at || '';
        if (da === db) return (b.id||0) - (a.id||0);
        return (db > da) ? 1 : -1;
      });

      // Initialize ListManager if not already done
      if (!window.poListManager) {
        window.poListManager = new ListManager({
          containerSelector: '#combinedList',
          tabsSelector: '#listTabs',
          pageSize: 20,
          defaultFilter: 'all',
          filters: {
            'all': (row) => {
              if (row.type === 'RFQ') return true;
              // For POs, include all except ones in 'closed' or 'cancelled' if checking status
              return true;
            },
            'open': (row) => {
              if (row.type === 'RFQ') return ['draft', 'sent'].includes((row.state || '').toLowerCase());
              if (row.type === 'PO') return (row.receipt_status === 'open');
              return false;
            },
            'partial': (row) => {
              if (row.type === 'PO') return (row.receipt_status === 'partial_received');
              return false;
            },
            'late': (row) => {
              if (row.type !== 'PO') return false;
              if (['closed', 'fully_received', 'cancelled'].includes(row.receipt_status)) return false;
              if (!row.delivery_date) return false;
              const today = new Date();
              today.setHours(0, 0, 0, 0);
              const deliveryDate = new Date(row.delivery_date);
              deliveryDate.setHours(0, 0, 0, 0);
              return deliveryDate < today;
            },
            'closed': (row) => {
              if (row.type === 'PO') return ['closed', 'fully_received'].includes(row.receipt_status) || (row.state || '').toLowerCase().includes('closed');
              return false;
            },
            'cancelled': (row) => {
              return (row.state || '').toLowerCase().includes('cancel');
            }
          },
          onRender: renderCombinedWithPagination
        });
        window.poListManager.setupTabs();
      }

      window.poListManager.setRows(rows);
    } catch (e) {
      combinedList.innerHTML = `<div class="alert alert-danger">${e.message}</div>`;
    }
  }

  document.getElementById('refreshList').addEventListener('click', loadCombined);
  document.getElementById('searchListBtn').addEventListener('click', loadCombined);
  document.getElementById('clearListBtn').addEventListener('click', function(){
    const el = document.getElementById('rfqpoSearch');
    if (el) el.value = '';
    loadCombined();
  });
  document.getElementById('rfqpoSearch').addEventListener('keydown', function(ev){
    if (ev.key === 'Enter') { ev.preventDefault(); loadCombined(); }
  });

  // Show form when Add RFQ clicked: reset form and show wrapper
  const showFormBtn = document.getElementById('showFormBtn');
  const formWrapper = document.getElementById('formWrapper');
  if (showFormBtn && formWrapper) {
    showFormBtn.addEventListener('click', function(){
      // reset form fields
      try {
        const form = document.getElementById('rfqpoForm');
        form.reset();
        // clear lines and add a fresh one
        linesContainer.innerHTML = '';
        addLineRow();
        // load next number
        loadNextNumber();
      } catch (e) {}
      // hide list and show form
      const listWrapper = document.getElementById('listWrapper');
      if (listWrapper) listWrapper.style.display = 'none';
      formWrapper.style.display = 'block';
      const ft = document.getElementById('formTitle');
      if (ft) ft.textContent = 'New RFQ';
      formWrapper.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  }

  // Close form and show list again
  const closeFormBtn = document.getElementById('closeFormBtn');
  const listWrapperEl = document.getElementById('listWrapper');
  if (closeFormBtn) {
    closeFormBtn.addEventListener('click', function(){
      try { formWrapper.style.display = 'none'; } catch(e){}
      try { if (listWrapperEl) listWrapperEl.style.display = 'block'; } catch(e){}
      // scroll to list
      try { if (listWrapperEl) listWrapperEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(e){}
    });
  }

  // Back to List button at top of form (mirrors closeFormBtn)
  const backToListBtn = document.getElementById('backToListBtn');
  if (backToListBtn) {
    backToListBtn.addEventListener('click', function(){
      try { formWrapper.style.display = 'none'; } catch(e){}
      try { if (listWrapperEl) listWrapperEl.style.display = 'block'; } catch(e){}
      try { if (listWrapperEl) listWrapperEl.scrollIntoView({ behavior: 'smooth', block: 'start' }); } catch(e){}
    });
  }

  async function loadNextNumber(){
    const numEl = document.getElementById('rfqNumber');
    if (!numEl) return;
    try {
      const j = await fetchJson('<?= site_url("new-purchase-rfqs/next-number") ?>', { method:'GET', headers:{'Accept':'application/json'} });
      numEl.value = j.rfq_number || '';
    } catch (e) {
      // non-blocking; user can still save and server will auto-generate
      numEl.value = '';
    }
  }

  // initial
  loadNextNumber();
  loadCombined();

  // Auto-open edit form if ?edit=<id> is in the URL (navigated from rfq_view)
  (async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const autoEditId = urlParams.get('edit');
    if (!autoEditId) return;
    try {
      const j = await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+autoEditId, { method:'GET', headers:{'Accept':'application/json'} });
      if (!j.success || !j.data) return;
      const form = document.getElementById('rfqpoForm');
      if (!form) return;
      form.reset();
      form.rfq_id.value = j.data.id || '';
      form.rfq_number.value = j.data.rfq_number || '';
      // Set vendor in Select2
      if (typeof $ !== 'undefined' && j.data.vendor_id) {
        var av = j.data.vendor_id, an = j.data.vendor_name || ('Vendor #' + av);
        var aOpt = new Option(an, av, true, true);
        $('#vendorSelect').empty().append(aOpt).trigger('change');
      } else if (typeof $ !== 'undefined') {
        $('#vendorSelect').val(null).trigger('change');
      }
      form.rfq_date.value = (j.data.rfq_date || '').toString().substring(0, 10);
      form.delivery_date.value = (j.data.delivery_date || '').toString().substring(0, 10);
      form.notes.value = j.data.notes || '';
      if (form.currency && j.data.currency) form.currency.value = j.data.currency;
      linesContainer.innerHTML = '';
      (j.data.lines || []).forEach((ln, idx) => {
        addLineRow();
        const row = linesContainer.children[idx];
        if (!row) return;
        (row.querySelector('.product-id')||{}).value = ln.product_id || '';
        const loadPvid = parseInt((ln.product_variant_id ?? ''), 10) || 0;
        const loadVid = parseInt((ln.variant_id ?? ''), 10) || 0;
        (row.querySelector('.product-variant-id')||{}).value = loadPvid > 0 ? loadPvid : (loadVid > 0 ? loadVid : '');
        (row.querySelector('.product-code')||{}).value = ln.product_code || '';
        const pname = (ln.product_name || '').toString();
        const pdesc = (ln.description || '').toString();
        (row.querySelector('.product-name')||{}).value = pname;
        (row.querySelector('.line-desc')||{}).value = (pdesc.trim().toLowerCase() === pname.trim().toLowerCase()) ? '' : pdesc;
        (row.querySelector('.line-qty')||{}).value = ln.qty || '';
        (row.querySelector('.line-price')||{}).value = (ln.unit_price !== undefined && ln.unit_price !== null) ? ln.unit_price : (ln.unit_cost || '');
        (row.querySelector('.line-discount-percent')||{}).value = ln.discount_percent || 0;
        (row.querySelector('.line-tax-percent')||{}).value = ln.tax_percent || 0;
        try {
          (row.querySelector('.line-qty')||{}).dispatchEvent(new Event('input'));
          (row.querySelector('.line-price')||{}).dispatchEvent(new Event('input'));
          (row.querySelector('.line-discount-percent')||{}).dispatchEvent(new Event('input'));
          (row.querySelector('.line-tax-percent')||{}).dispatchEvent(new Event('input'));
        } catch (e) {}
      });
      computeTotals();
      document.getElementById('listWrapper').style.display = 'none';
      document.getElementById('formWrapper').style.display = 'block';
      const ft = document.getElementById('formTitle');
      if (ft) ft.textContent = 'Edit RFQ #' + (j.data.rfq_number || autoEditId);
    } catch (e) {}
  })();

  // Auto-open edit form if ?edit_po=<id> is in the URL (edit PO in full form)
  (async () => {
    const urlParams = new URLSearchParams(window.location.search);
    const editPoId = urlParams.get('edit_po');
    if (!editPoId) return;
    try {
      const j = await fetchJson('<?= site_url("new-purchase-orders/") ?>'+editPoId, { method:'GET', headers:{'Accept':'application/json'} });
      if (!j.success || !j.data) return;
      
      let po = j.data;
      let poLines = [];
      if (po.po) { poLines = po.lines || po.po.lines || []; po = po.po; }
      else if (Array.isArray(po)) { poLines = po; po = {}; }
      else { poLines = po.lines || []; }

      console.log('Loaded PO data:', po);
      console.log('Loaded PO lines count:', poLines.length);
      console.log('PO lines:', poLines);

      const form = document.getElementById('rfqpoForm');
      if (!form) return;
      form.reset();
      form.rfq_id.value = po.id || '';
      form.rfq_number.value = po.po_number || po.id || '';
      
      // Set vendor in Select2
      if (typeof $ !== 'undefined' && po.vendor_id) {
        var pv = po.vendor_id, pn = po.vendor_name || ('Vendor #' + pv);
        var pOpt = new Option(pn, pv, true, true);
        $('#vendorSelect').empty().append(pOpt).trigger('change');
      } else if (typeof $ !== 'undefined') {
        $('#vendorSelect').val(null).trigger('change');
      }
      
      form.rfq_date.value = (po.po_date || po.created_at || '').toString().substring(0, 10);
      form.delivery_date.value = (po.delivery_date || '').toString().substring(0, 10);
      form.notes.value = po.notes || '';
      if (form.currency && po.currency) form.currency.value = po.currency;
      
      linesContainer.innerHTML = '';
      (poLines || []).forEach((ln, idx) => {
        addLineRow();
        const row = linesContainer.children[idx];
        if (!row) return;
        
        // Set product IDs
        (row.querySelector('.product-id')||{}).value = ln.product_id || '';
        const loadPvid = parseInt((ln.product_variant_id ?? ''), 10) || 0;
        const loadVid = parseInt((ln.variant_id ?? ''), 10) || 0;
        (row.querySelector('.product-variant-id')||{}).value = loadPvid > 0 ? loadPvid : (loadVid > 0 ? loadVid : '');
        
        // Set product code - prefer variant code if available
        const variantCode = ln.variant_code || ln.variant_art_number || '';
        const productCode = ln.product_code || ln.code || '';
        (row.querySelector('.product-code')||{}).value = variantCode || productCode || '';
        
        // Set product name
        const pname = (ln.product_name || ln.name || '').toString();
        (row.querySelector('.product-name')||{}).value = pname;
        
        // Set description - only if it's different from the product name
        const pdesc = (ln.description || '').toString();
        const descInput = row.querySelector('.line-desc');
        if (descInput) {
          descInput.value = (pdesc.trim().toLowerCase() === pname.trim().toLowerCase()) ? '' : pdesc;
          // If there's a variant description, add it
          if (ln.variant_name) {
            descInput.value = ln.variant_name;
          }
        }
        
        // Load product image
        const imgEl = row.querySelector('.product-thumb');
        if (imgEl && ln.product_image) {
          imgEl.src = ln.product_image;
          imgEl.style.backgroundColor = '#0b1220';
        } else if (imgEl && ln.image) {
          imgEl.src = ln.image;
          imgEl.style.backgroundColor = '#0b1220';
        }
        
        // Set quantities and prices
        (row.querySelector('.line-qty')||{}).value = ln.qty || ln.quantity || '';
        (row.querySelector('.line-price')||{}).value = (ln.unit_price !== undefined && ln.unit_price !== null) ? ln.unit_price : (ln.unit_cost || '');
        (row.querySelector('.line-discount-percent')||{}).value = ln.discount_percent || 0;
        (row.querySelector('.line-tax-percent')||{}).value = ln.tax_percent || 0;
        try {
          (row.querySelector('.line-qty')||{}).dispatchEvent(new Event('input'));
          (row.querySelector('.line-price')||{}).dispatchEvent(new Event('input'));
          (row.querySelector('.line-discount-percent')||{}).dispatchEvent(new Event('input'));
          (row.querySelector('.line-tax-percent')||{}).dispatchEvent(new Event('input'));
        } catch (e) {}
      });
      computeTotals();
      document.getElementById('listWrapper').style.display = 'none';
      document.getElementById('formWrapper').style.display = 'block';
      const ft = document.getElementById('formTitle');
      if (ft) ft.textContent = 'Edit PO #' + (po.po_number || editPoId);
      
      // Update submit button text for PO
      const submitBtn = document.querySelector('#rfqpoForm button[type="submit"]');
      if (submitBtn) submitBtn.textContent = 'Update PO';
    } catch (e) { console.error('Failed to load PO for editing', e); }
  })();
})();
</script>

<!-- Confirm RFQ to PO Modal -->
<div class="modal fade" id="confirmRfqModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Confirm RFQ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label">Delivery Date <span style="color:red;">*</span></label>
        <input type="date" id="confirmRfqDeliveryDate" class="form-control" required />
        <small class="text-muted mt-2">This will create a confirmed Purchase Order with this delivery date.</small>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" id="confirmRfqBtn" class="btn btn-primary">Confirm RFQ</button>
      </div>
    </div>
  </div>
</div>

<!-- PO Modal -->
<div class="modal fade" id="poModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Purchase Order</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="poModalBody">
        <div id="poModalContent">Loading...</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="button" id="poModalCancelBtn" class="btn btn-danger" style="display:none">Cancel PO</button>
        <button type="button" id="poModalSaveBtn" class="btn btn-primary" style="display:none">Save changes</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  // Modal helpers for PO view/edit
  const poModalEl = document.getElementById('poModal');
  let _poModal = null;
  const poModalBody = document.getElementById('poModalBody');
  const poModalContent = document.getElementById('poModalContent');
  const poModalSaveBtn = document.getElementById('poModalSaveBtn');
  const poModalCancelBtn = document.getElementById('poModalCancelBtn');

  function showModal(){
    try {
      if (window.bootstrap && bootstrap.Modal) {
        if (!_poModal) _poModal = new bootstrap.Modal(poModalEl);
        _poModal.show();
      } else {
        poModalEl.style.display = 'block';
        poModalEl.classList.add('show');
      }
    } catch(e){ poModalEl.style.display = 'block'; }
  }
  function hideModal(){
    try {
      if (_poModal) _poModal.hide();
      else { poModalEl.style.display = 'none'; poModalEl.classList.remove('show'); }
    } catch(e){ poModalEl.style.display = 'none'; }
  }

  // Build modal content for PO. If editable=true, show inputs and Save button.
  async function openPoModal(id, editable){
    poModalContent.innerHTML = 'Loading PO...';
    poModalSaveBtn.style.display = editable ? 'inline-block' : 'none';
    poModalCancelBtn.style.display = editable ? 'inline-block' : 'none';
    showModal();

    function escapeHtml(value) {
      return String(value || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function parseNumber(value) {
      const n = parseFloat(String(value || '').replace(/,/g, '').trim());
      return isNaN(n) ? 0 : n;
    }

    try {
      const j = await fetchJson('<?= site_url("new-purchase-orders/") ?>' + id, { method:'GET', headers:{'Accept':'application/json'} });
      if (!j.success || !j.data) throw new Error('Failed to load PO');

      let p = j.data;
      let lines = [];
      if (p.po) { lines = p.lines || p.po.lines || []; p = p.po; }
      else if (Array.isArray(p)) { lines = p; p = {}; }
      else { lines = p.lines || []; }

      try { const mt = poModalEl.querySelector('.modal-title'); if (mt) mt.textContent = 'Purchase Order — ' + (p.po_number || p.id); } catch (e) {}

      let html = '<div class="mb-2"><strong>Vendor:</strong> ' + escapeHtml(p.vendor_name || p.vendor_id || '') + ' &nbsp; <strong class="ms-3">Date:</strong> ' + escapeHtml(p.po_date || p.created_at || '') + ' &nbsp; <strong class="ms-3">Type:</strong> PO &nbsp; <strong class="ms-3">Status:</strong> ' + escapeHtml(p.status || '') + '</div>';
      if (editable) {
        html += '<div class="d-flex justify-content-between align-items-center mb-2"><div class="fw-semibold">Editable Purchase Order</div><button type="button" id="poAddLineBtn" class="btn btn-sm btn-outline-primary">+ Add product</button></div>';
      }

      html += '<div class="table-responsive"><table class="table table-sm"><thead><tr>';
      if (editable) {
        html += '<th style="min-width:100px">Code</th><th style="min-width:150px">Product</th><th>Description</th><th class="text-end" style="width:80px">Qty</th><th class="text-end" style="width:95px">Unit</th><th class="text-end" style="width:90px">Disc %</th><th class="text-end" style="width:90px">Tax %</th><th class="text-end" style="width:100px">Total</th><th style="width:60px"></th>';
      } else {
        html += '<th>Code</th><th>Desc</th><th>Qty</th><th>Unit</th><th>Disc %</th><th>Tax %</th><th class="text-end">Line Total</th>';
      }
      html += '</tr></thead><tbody id="poModalLines"></tbody></table></div>';
      html += '<div class="mt-2"><strong>Notes:</strong><div>' + (editable ? '<textarea id="poNotes" class="form-control">' + escapeHtml(p.notes || '') + '</textarea>' : escapeHtml(p.notes || '')) + '</div></div>';
      poModalContent.innerHTML = html;

      const linesTbody = document.getElementById('poModalLines');
      if (!linesTbody) throw new Error('Unable to build PO line editor');

      function renderPoLineRow(ln) {
        const productCode = escapeHtml(ln.product_code || ln.code || ln.sku || ln.part_no || ln.part_number || ln.product_sku || (ln.product && (ln.product.code || ln.product.sku || ln.product.part_no)) || '');
        const productName = escapeHtml(ln.product_name || ln.name || '');
        const description = escapeHtml(ln.description || '');
        const qty = parseNumber(ln.qty ?? ln.quantity ?? 0);
        const unitPrice = parseNumber(ln.unit_price ?? ln.unit_cost ?? 0);
        const discountPct = parseNumber(ln.discount_percent ?? 0);
        const taxPct = parseNumber(ln.tax_percent ?? 0);
        const lineTotal = qty * unitPrice * (1 - discountPct / 100) + ((taxPct / 100) * Math.max(0, qty * unitPrice * (1 - discountPct / 100)));

        const tr = document.createElement('tr');
        tr.innerHTML = `
          <td>
            <input type="hidden" class="product-id" value="${escapeHtml(ln.product_id || ln.productId || '')}" />
            <input type="hidden" class="product-variant-id" value="${escapeHtml(ln.variant_id || ln.product_variant_id || ln.variantId || '')}" />
            <input class="form-control form-control-sm product-code" placeholder="Code" value="${productCode}" />
          </td>
          <td><input class="form-control form-control-sm product-name" placeholder="Product" value="${productName}" /></td>
          <td><input class="form-control form-control-sm line-desc" placeholder="Description" value="${description}" /></td>
          <td class="text-end"><input class="form-control form-control-sm text-end po-line-qty" type="number" step="any" min="0" value="${qty}" /></td>
          <td class="text-end"><input class="form-control form-control-sm text-end po-line-price" type="number" step="any" min="0" value="${unitPrice}" /></td>
          <td class="text-end"><input class="form-control form-control-sm text-end po-line-disc" type="number" step="any" min="0" value="${discountPct}" /></td>
          <td class="text-end"><input class="form-control form-control-sm text-end po-line-tax" type="number" step="any" min="0" value="${taxPct}" /></td>
          <td class="text-end"><span class="po-line-total">${lineTotal.toFixed(2)}</span></td>
          <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger po-remove-line-btn">✕</button></td>
        `;

        const qtyEl = tr.querySelector('.po-line-qty');
        const priceEl = tr.querySelector('.po-line-price');
        const discEl = tr.querySelector('.po-line-disc');
        const taxEl = tr.querySelector('.po-line-tax');
        const totalEl = tr.querySelector('.po-line-total');
        const removeBtn = tr.querySelector('.po-remove-line-btn');

        function updateRowTotal() {
          const q = parseNumber(qtyEl.value);
          const p = parseNumber(priceEl.value);
          const d = parseNumber(discEl.value);
          const t = parseNumber(taxEl.value);
          const base = q * p;
          const discount = Math.max(0, (d / 100) * base);
          const taxable = Math.max(0, base - discount);
          const taxAmount = (t / 100) * taxable;
          const total = taxable + taxAmount;
          if (totalEl) totalEl.textContent = total.toFixed(2);
        }

        [qtyEl, priceEl, discEl, taxEl].forEach((el) => {
          if (el) el.addEventListener('input', updateRowTotal);
        });

        if (removeBtn) {
          removeBtn.addEventListener('click', function(){ tr.remove(); });
        }

        if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
          try {
            window.CoreLynkAutocomplete.attachProductAutocomplete(tr.querySelector('.product-code'), false, 'purchase');
            window.CoreLynkAutocomplete.attachProductAutocomplete(tr.querySelector('.product-name'), true, 'purchase');
          } catch (e) { console.warn('Product autocomplete attach failed', e); }
        }

        return tr;
      }

      lines.forEach(function(ln){ linesTbody.appendChild(renderPoLineRow(ln)); });
      if (editable && lines.length === 0) {
        linesTbody.appendChild(renderPoLineRow({}));
      }

      if (editable) {
        const addLineBtn = document.getElementById('poAddLineBtn');
        if (addLineBtn) {
          addLineBtn.addEventListener('click', function(){ linesTbody.appendChild(renderPoLineRow({})); });
        }
      }

      poModalSaveBtn.onclick = async function(){
        const payloadLines = [];
        poModalContent.querySelectorAll('#poModalLines tr').forEach(function(tr){
          const qty = parseNumber((tr.querySelector('.po-line-qty') || {}).value);
          const price = parseNumber((tr.querySelector('.po-line-price') || {}).value);
          if (qty <= 0) return;
          payloadLines.push({
            product_id: parseInt((tr.querySelector('.product-id') || {}).value || '', 10) || null,
            product_variant_id: parseInt((tr.querySelector('.product-variant-id') || {}).value || '', 10) || null,
            description: (tr.querySelector('.line-desc') || {}).value || '',
            qty: qty,
            unit_price: price,
            discount_percent: parseNumber((tr.querySelector('.po-line-disc') || {}).value),
            tax_percent: parseNumber((tr.querySelector('.po-line-tax') || {}).value)
          });
        });

        if (payloadLines.length === 0) {
          msg('Add at least one line before saving.', 'warning');
          return;
        }

        const payload = {
          notes: (document.getElementById('poNotes') || {}).value || '',
          lines: payloadLines
        };
        try {
          poModalSaveBtn.disabled = true;
          poModalSaveBtn.textContent = 'Saving...';
          await fetchJson('<?= site_url("new-purchase-orders/") ?>' + id + '/update', {
            method:'POST',
            headers:{'Content-Type':'application/json'},
            body: JSON.stringify(payload)
          });
          msg('PO updated.', 'success');
          hideModal();
          await loadCombined();
        } catch(err) {
          msg(err.message || 'Update failed', 'danger');
        } finally {
          poModalSaveBtn.disabled = false;
          poModalSaveBtn.textContent = 'Save changes';
        }
      };

      poModalCancelBtn.onclick = async function(){
        const reason = prompt('Cancellation reason (optional)') || null;
        if (!confirm('Are you sure you want to cancel this PO?')) return;
        try {
          await fetchJson('<?= site_url("new-purchase-orders/") ?>' + id + '/cancel', { method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({ reason }) });
          msg('PO cancelled.', 'success');
          hideModal();
          await loadCombined();
        } catch(err) {
          msg(err.message || 'Cancel failed', 'danger');
        }
      };

    } catch(e) {
      poModalContent.innerHTML = '<div class="alert alert-danger">' + escapeHtml(e.message || 'Failed to load PO') + '</div>';
    }
  }

  // expose to global scope within this view
  window.openPoModal = openPoModal;

  // Show read-only RFQ details (lines) in the same modal
  async function openRfqModal(id){
    poModalContent.innerHTML = 'Loading RFQ...';
    poModalSaveBtn.style.display = 'none';
    poModalCancelBtn.style.display = 'none';
    showModal();
    try {
      const j = await fetchJson('<?= site_url("new-purchase-rfqs/") ?>'+id, { method:'GET', headers:{'Accept':'application/json'} });
      if (!j.success || !j.data) throw new Error('Failed to load RFQ');
      const r = j.data;
  // set modal title to include RFQ number
  try { const mt = poModalEl.querySelector('.modal-title'); if (mt) mt.textContent = 'RFQ — ' + (r.rfq_number||r.id); } catch(e){}
  // show Vendor/Date/Type/Status only in body (RFQ number is in the title)
  let html = `<div class="mb-2"><strong>Vendor:</strong> ${r.vendor_name||r.vendor_id||''} &nbsp; <strong class="ms-3">Date:</strong> ${r.rfq_date||r.created_at||''} &nbsp; <strong class="ms-3">Type:</strong> RFQ &nbsp; <strong class="ms-3">Status:</strong> ${r.status||''}</div>`;
  html += `<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Code</th><th>Desc</th><th>Qty</th><th>Unit</th><th>Disc %</th><th>Tax %</th><th class="text-end">Line Total</th></tr></thead><tbody>`;
      (r.lines||[]).forEach((ln,idx)=>{
        html += `<tr data-line-index="${idx}">`;
  const rpc = (ln.product_code || ln.code || ln.sku || ln.part_no || ln.part_number || ln.product_sku || (ln.product && (ln.product.code || ln.product.sku || ln.product.part_no)) || '');
  html += `<td>` + (rpc ? rpc : '') + `</td>`;
        html += `<td>${ln.description||''}</td>`;
        const qtyVal = parseFloat(ln.qty||ln.quantity||0);
        html += `<td>${isNaN(qtyVal) ? 0 : qtyVal.toFixed(2)}</td>`;
        const priceVal = parseFloat(ln.unit_price||ln.unit_cost||0);
        html += `<td>${isNaN(priceVal) ? 0 : priceVal.toFixed(2)}</td>`;
        html += `<td>${ln.discount_percent||0}</td>`;
        html += `<td>${ln.tax_percent||0}</td>`;
        html += `<td class="text-end">${(ln.line_total||0).toFixed ? (ln.line_total||0).toFixed(2) : (ln.line_total||0)}</td>`;
        html += `</tr>`;
      });
      html += `</tbody></table></div>`;
      html += `<div class="mt-2"><strong>Notes:</strong><div>${r.notes||''}</div></div>`;
      poModalContent.innerHTML = html;
    } catch (e) {
      poModalContent.innerHTML = '<div class="alert alert-danger">'+(e.message||'Failed to load RFQ')+'</div>';
    }
  }
  window.openRfqModal = openRfqModal;

})();
</script>

<?= $this->endSection() ?>
