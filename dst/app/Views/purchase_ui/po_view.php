<?= $this->extend('layouts/main') ?>

<?php $defaultCurrency = $defaultCurrency ?? 'USD'; ?>

<?= $this->section('title') ?>
Purchase Order
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/**
 * Purchase Order detail — same system as the customer detail page.
 *
 * Every colour here is a design token, so light and dark are one ruleset: the
 * old sheet hard-coded light hexes and then patched them back with a
 * `body.theme-dark` block, which is why half this page stayed white in dark
 * mode. The `.cl-detail` class on the wrapper supplies the tokens (and the
 * brightened status colours dark mode needs) from components/detail-view.css.
 */

.po-card-wrap {
  gap: 0;
  border: 1px solid var(--cl-detail-border);
  border-radius: var(--cl-radius-lg, 14px);
  background: var(--cl-detail-surface);
  box-shadow: none;
  overflow: hidden;
}

.po-card-wrap > .card-header,
.po-card-wrap > .card-body { border: 0; background: none; }
.po-card-wrap > .card-body { padding: 0 13px 13px; }

/* An empty line toolbar used to leave a blank band under the hero. */
.po-card-wrap [data-doc-line-toolbar]:empty { display: none; }

/* ------------------------------------------------------------------ hero */
.po-hero {
  position: relative;
  padding: 14px 16px;
  border-bottom: 1px solid var(--cl-detail-border);
  background: var(--cl-detail-inset);
}

.po-hero-main { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; flex-wrap: wrap; }

.po-eyebrow {
  color: var(--cl-detail-muted);
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .1em;
  text-transform: uppercase;
}

.po-title {
  margin: 2px 0 0;
  color: var(--cl-detail-text);
  font-size: 20px;
  font-weight: 700;
  line-height: 1.15;
}

.po-number { color: var(--cl-detail-muted); font-weight: 600; }

/* Title line carries the identity chips; the facts row carries the data. */
.po-title-row { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-top: 3px; }
.po-title-row .po-title { margin: 0; }

.po-route-strip { display: flex; flex-wrap: wrap; gap: 5px; margin-top: 6px; }
.po-route-strip:empty { display: none; }

/* Facts row: label over value, hairline separated. The 1px grid gap draws the
   dividers, so wrapped rows stay clean at any column count. */
.po-facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 1px;
  margin: 12px 0 0;
  border: 1px solid var(--cl-detail-border);
  border-radius: 10px;
  background: var(--cl-detail-border);
  overflow: hidden;
}
.po-fact { padding: 8px 12px; background: var(--cl-detail-surface); min-width: 0; }
.po-fact-label {
  color: var(--cl-detail-muted);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: .08em;
  text-transform: uppercase;
}
.po-fact-value {
  margin-top: 2px;
  color: var(--cl-detail-text);
  font-size: 13px;
  font-weight: 600;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.po-fact-note { margin-top: 1px; color: var(--cl-detail-muted); font-size: 11px; font-weight: 500; }
.po-fact-note.is-late { color: var(--cl-color-danger, #ef5b6b); font-weight: 600; }
.po-fact-note.is-due { color: var(--cl-color-warning, #d88a25); font-weight: 600; }

.po-status-chip,
.po-doc-type-badge,
.po-route-item {
  display: inline-flex;
  align-items: center;
  gap: 5px;
  min-height: 24px;
  padding: 3px 9px;
  border: 1px solid var(--cl-detail-border);
  border-radius: 999px;
  background: var(--cl-detail-surface);
  color: var(--cl-detail-muted);
  font-size: 11.5px;
  font-weight: 600;
  white-space: nowrap;
}

.po-route-item > i { color: var(--cl-detail-accent); font-size: .8rem; }
.po-route-item strong { color: var(--cl-detail-text); font-size: 11.5px; font-weight: 700; }

.po-status-chip { text-transform: uppercase; letter-spacing: .04em; font-size: 10.5px; font-weight: 700; }
.po-status-chip > i { font-size: .45rem; }

/* Status / type tone. color-mix keeps one rule working on both themes. */
.po-status-chip,
.po-doc-type-badge { --po-tone: var(--cl-color-info, #2f9ff3); }
.po-status-chip.confirmed,
.po-status-chip.completed,
.po-status-chip.received,
.po-status-chip.paid,
.po-doc-type-badge.inventory { --po-tone: var(--cl-color-success, #249e55); }
.po-status-chip.draft,
.po-status-chip.pending,
.po-doc-type-badge.mixed { --po-tone: var(--cl-color-warning, #d88a25); }
.po-status-chip.cancelled,
.po-status-chip.closed { --po-tone: var(--cl-color-danger, #ef5b6b); }
.po-doc-type-badge.service { --po-tone: var(--cl-color-info, #2f9ff3); }

.po-status-chip,
.po-doc-type-badge {
  border-color: color-mix(in srgb, var(--po-tone) 45%, transparent);
  background: color-mix(in srgb, var(--po-tone) 15%, transparent);
  color: var(--po-tone);
}

.po-route-item.is-late {
  --po-tone: var(--cl-color-danger, #ef5b6b);
  border-color: color-mix(in srgb, var(--po-tone) 45%, transparent);
  color: var(--po-tone);
}
.po-route-item.is-late > i,
.po-route-item.is-late strong { color: var(--po-tone); }

/* --------------------------------------------------------------- actions */
.po-action-bar { display: flex; align-items: center; gap: 6px; margin-left: auto; }

.po-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  height: 30px;
  padding: 0 11px;
  border: 1px solid var(--cl-detail-border);
  border-radius: 8px;
  background: var(--cl-detail-surface);
  color: var(--cl-detail-soft);
  font-size: 12.5px;
  font-weight: 600;
  line-height: 1;
  white-space: nowrap;
  cursor: pointer;
  text-decoration: none !important;
  transition: border-color .14s, background .14s, color .14s;
}
.po-btn:hover { border-color: var(--cl-detail-accent); color: var(--cl-detail-accent); }
.po-btn:focus-visible { outline: 2px solid var(--cl-detail-accent); outline-offset: 2px; }
.po-btn:active { transform: translateY(1px); }
.po-btn i { font-size: .85rem; }
.po-btn-more { padding: 0 8px; font-size: .9rem; }

/* Edit is the one loud action; the inline colours it used to carry are gone. */
#editBtn { border-color: var(--cl-detail-accent); background: var(--cl-detail-accent); color: #fff; }
#editBtn:hover { background: color-mix(in srgb, var(--cl-detail-accent) 85%, #000); color: #fff; }

/* ------------------------------------------------ dropdown (no Popper.js) */
.po-dropdown-wrap { position: relative; }

.po-more-menu {
  display: none;
  position: fixed;
  z-index: 1050;
  min-width: 190px;
  margin: 0;
  padding: 4px;
  list-style: none;
  border: 1px solid var(--cl-detail-border);
  border-radius: 10px;
  background: var(--cl-detail-surface);
  box-shadow: 0 12px 30px rgba(0, 0, 0, .18);
}
.po-more-menu.is-open { display: block; }
.po-more-menu li { list-style: none; }

.po-more-menu .po-menu-item {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  padding: 6px 10px;
  border: 0;
  border-radius: 7px;
  background: none;
  color: var(--cl-detail-soft);
  font-size: 12.5px;
  font-weight: 500;
  white-space: nowrap;
  cursor: pointer;
  text-decoration: none !important;
}
.po-more-menu .po-menu-item:hover { background: var(--cl-detail-inset); color: var(--cl-detail-text); }
.po-more-menu .po-menu-item.clr-warn { color: var(--cl-color-warning, #d88a25); }
.po-more-menu .po-menu-item.clr-danger { color: var(--cl-color-danger, #ef5b6b); }
.po-menu-divider { height: 1px; margin: 4px 6px; background: var(--cl-detail-border); }

/* Diagonal status stamp — same behaviour, driven by JS. */
.po-stamp {
  display: none;
  position: absolute;
  top: 46%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-16deg);
  padding: 6px 22px;
  border: 3px solid currentColor;
  border-radius: 10px;
  font-size: 2.1rem;
  font-weight: 900;
  letter-spacing: .1em;
  text-transform: uppercase;
  line-height: 1.2;
  white-space: nowrap;
  opacity: .1;
  pointer-events: none;
  user-select: none;
  z-index: 10;
}

/* --------------------------------------------------------------- panels */
.po-section,
.po-summary-card,
.po-totals-card,
.po-notes-card,
.po-bills-card {
  margin-top: 10px;
  padding: 12px 13px;
  border: 1px solid var(--cl-detail-border);
  border-radius: var(--cl-radius-md, 12px);
  background: var(--cl-detail-surface);
}

.po-section-head { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin-bottom: 8px; }

.po-section-title {
  display: flex;
  align-items: center;
  gap: 7px;
  margin: 0;
  color: var(--cl-detail-text);
  font-size: 13.5px;
  font-weight: 700;
}
.po-section-title > i { color: var(--cl-detail-accent); font-size: 1rem; }

/* ----------------------------------------------------------- line items */
.po-lines-table,
.po-bills-table { margin-bottom: 0; color: var(--cl-detail-soft); font-size: 12.5px; }

.po-lines-table > thead > tr > th,
.po-bills-table > thead > tr > th {
  padding: 6px 8px;
  border-bottom: 1px solid var(--cl-detail-border);
  background: var(--cl-detail-inset);
  color: var(--cl-detail-muted);
  font-size: 10.5px;
  font-weight: 700;
  letter-spacing: .06em;
  text-transform: uppercase;
}

.po-lines-table > tbody > tr > td,
.po-bills-table > tbody > tr > td {
  padding: 7px 8px;
  border-bottom: 1px solid var(--cl-detail-border);
  background: none;
  color: var(--cl-detail-soft);
  vertical-align: middle;
}
.po-lines-table > tbody > tr:last-child > td,
.po-bills-table > tbody > tr:last-child > td { border-bottom: 0; }
.po-lines-table > tbody > tr:hover > td { background: color-mix(in srgb, var(--cl-detail-accent) 6%, transparent); }

.po-line-code {
  display: inline-flex;
  align-items: center;
  padding: 1px 7px;
  border-radius: 999px;
  background: var(--cl-detail-inset);
  color: var(--cl-detail-muted);
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 10.5px;
  font-weight: 600;
}

.po-line-thumb {
  width: 34px !important;
  height: 34px !important;
  border: 1px solid var(--cl-detail-border);
  border-radius: 8px !important;
  background: var(--cl-detail-inset);
}

/* No image on file — an icon, not an empty frame. */
.po-line-thumb.is-empty {
  display: inline-grid;
  place-items: center;
  color: var(--cl-detail-muted);
  font-size: .95rem;
}

.po-line-title { color: var(--cl-detail-text); font-size: 12.5px; font-weight: 600; }
.po-line-total { color: var(--cl-detail-text); font-weight: 700; }
.po-lines-table .text-end,
.po-bills-table .text-end { font-variant-numeric: tabular-nums; }

/* ------------------------------------------------------- summary/totals */
.po-insight-grid { display: grid; grid-template-columns: minmax(0, 1.15fr) minmax(280px, .85fr); gap: 10px; align-items: start; margin-top: 10px; }
.po-insight-grid > * { margin-top: 0; }

.po-summary-metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; margin-top: 10px; }

.po-summary-metric {
  padding: 10px;
  border: 1px solid var(--cl-detail-border);
  border-radius: 10px;
  background: var(--cl-detail-inset);
  text-align: center;
}
.po-summary-metric span { color: var(--cl-detail-muted); font-size: 10.5px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
.po-summary-metric strong {
  display: block;
  margin-top: 3px;
  color: var(--cl-detail-accent);
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.15;
  font-variant-numeric: tabular-nums;
}

.po-info-banner {
  --po-tone: var(--cl-color-info, #2f9ff3);
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 7px;
  margin-top: 10px;
  padding: 8px 12px;
  border: 1px solid color-mix(in srgb, var(--po-tone) 40%, transparent);
  border-radius: 10px;
  background: color-mix(in srgb, var(--po-tone) 12%, transparent);
  color: var(--po-tone);
  font-size: 12px;
  font-weight: 600;
}
.po-info-banner.is-warn { --po-tone: var(--cl-color-warning, #d88a25); }
.po-info-banner.is-ok { --po-tone: var(--cl-color-success, #249e55); }

.po-totals-card { padding: 0; overflow: hidden; }

.po-totals-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 13px;
  border-bottom: 1px solid var(--cl-detail-border);
  color: var(--cl-detail-muted);
  font-size: 12.5px;
  font-weight: 600;
}
.po-totals-row strong { color: var(--cl-detail-text); font-weight: 700; font-variant-numeric: tabular-nums; }
.po-totals-row.grand {
  border-bottom: 0;
  background: var(--cl-detail-inset);
  color: var(--cl-detail-text);
  font-size: 13.5px;
}
.po-totals-row.grand strong { font-size: 1.05rem; }

/* ------------------------------------------------------------ notes/bills */
.po-notes-box {
  min-height: 56px;
  padding: 10px 12px;
  border: 1px solid var(--cl-detail-border);
  border-radius: 10px;
  background: var(--cl-detail-inset);
  color: var(--cl-detail-soft);
  font-size: 12.5px;
  line-height: 1.55;
  white-space: pre-wrap;
}

@media (max-width: 991.98px) {
  .po-insight-grid { grid-template-columns: minmax(0, 1fr); }
}

@media (max-width: 575.98px) {
  .po-hero { padding: 12px; }
  .po-title { font-size: 17px; }
  .po-facts { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .po-action-bar { width: 100%; margin-left: 0; flex-wrap: wrap; }
  .po-summary-metrics { grid-template-columns: minmax(0, 1fr); }
}
</style>
<div class="card po-card-wrap cl-detail" id="poCard">
  <!-- Odoo-style diagonal stamp (shown by JS based on status) -->
  <div id="poStamp" class="po-stamp"></div>

  <div class="card-header po-hero">
    <div class="po-hero-main">
      <div>
        <div class="po-eyebrow">Purchase document</div>
        <div class="po-title-row">
          <h2 class="po-title">Purchase Order <span id="poSmall" class="po-number"></span></h2>
          <span id="poStatusChip" class="po-status-chip"></span>
          <span id="poDocType"></span>
        </div>
      </div>

      <div class="po-action-bar">
        <!-- Back -- always visible -->
        <a id="backLink" href="<?= site_url('newpurchaseui/rfqpo') ?>" class="po-btn po-btn-back">
          <i class="bi bi-arrow-left"></i>Back
        </a>

        <?= view('partials/record_nav', [
            'table'   => 'purchase_orders',
            'id'      => $poIdentifier ?? '',
            'pattern' => 'purchases/po/{id}',
            'list'    => 'newpurchaseui/pos',
        ]) ?>

        <!-- Edit button -- shown for draft/pending orders -->
        <a id="editBtn" href="#" class="po-btn" style="display:none;" title="Edit">
          <i class="bi bi-pencil"></i>Edit
        </a>
        <button id="manageTagsBtn" type="button" class="po-btn btn-manage-tags" style="display:none;" data-doc-type="purchase_order" data-doc-id="">
          <i class="bi bi-tags"></i>Tags
        </button>

        <!-- Three-dots custom dropdown (no Bootstrap JS / no Popper) -->
        <div class="po-dropdown-wrap">
          <button id="poMoreBtn" class="po-btn po-btn-more" type="button" title="More actions">
            <i class="bi bi-three-dots-vertical"></i>
          </button>
          <ul id="poMoreMenu" class="po-more-menu" role="menu">
            <!-- Receive / View GRN – shown by JS when applicable -->
            <li>
              <a id="receiveItem" href="#" class="po-menu-item" style="display:none;">
                <i class="bi bi-box-arrow-in-down" style="color:#16a34a;"></i>
                <span id="receiveItemLabel">Receive</span>
              </a>
            </li>
            <li id="receiveItemSep" class="po-menu-divider" style="display:none;"></li>

            <!-- PDF & Print -- always -->
            <li>
              <a id="downloadPdfBtn" href="#" target="_blank" class="po-menu-item">
                <i class="bi bi-file-earmark-pdf" style="color:#dc2626;"></i>Download PDF
              </a>
            </li>
            <li>
              <button id="printBtn" type="button" class="po-menu-item">
                <i class="bi bi-printer"></i>Print
              </button>
            </li>
            <li class="po-menu-divider"></li>

            <!-- Contextual actions -- shown by JS -->
            <li>
              <button id="createBillBtn" type="button" class="po-menu-item" style="display:none;">
                <i class="bi bi-receipt" style="color:#0284c7;"></i>Create Bill
              </button>
            </li>
            <li>
              <button id="setDraftBtn" type="button" class="po-menu-item" style="display:none;">
                <i class="bi bi-pencil-square"></i>Set Draft
              </button>
            </li>
            <li>
              <button id="resetRfqBtn" type="button" class="po-menu-item clr-warn" style="display:none;">
                <i class="bi bi-arrow-counterclockwise"></i>Reset to RFQ
              </button>
            </li>
            <li id="closePoSep" class="po-menu-divider" style="display:none;"></li>
            <li>
              <button id="closePoBtn" type="button" class="po-menu-item clr-warn" style="display:none;">
                <i class="bi bi-lock"></i>Close PO
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <div id="poMeta" class="po-facts"></div>
    <div id="poDeadline" class="po-route-strip"></div>
  </div>
  <div class="card-body">
    <div id="poMessage"></div>
    <div class="d-flex justify-content-end mb-2" data-doc-line-toolbar></div>
    <div id="poContainer">Loading PO...</div>
  </div>
</div>

<script>
(() => {
  const DEFAULT_PURCHASE_CURRENCY = '<?= esc($defaultCurrency) ?>';
  const container = document.getElementById('poContainer');
  const msg = document.getElementById('poMessage');
  function showError(text){ msg.innerHTML = `<div class="alert alert-danger">${text}</div>`; container.innerHTML = ''; }
  function fetchJson(url, opts){ return fetch(url, opts||{}).then(r=>{ if(!r.ok) return r.text().then(t=>{ throw new Error(t||('HTTP '+r.status)); }); const ct = r.headers.get('content-type')||''; if(!ct.includes('application/json')) return r.text().then(t=>{ throw new Error('Non-JSON response: '+t); }); return r.json(); }); }

  function fmt(n){ try{ return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }catch(e){ return (n||0).toFixed? (n||0).toFixed(2) : (n||0); } }
  function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s || '')); return d.innerHTML; }

  // parse numeric values robustly (strip commas/spaces) and return Number
  function parseNumber(v){ if (v === null || typeof v === 'undefined') return 0; if (typeof v === 'number') return v; try{ var s = String(v).trim(); if(s.length===0) return 0; s = s.replace(/\s+/g,'').replace(/,/g,''); var x = parseFloat(s); return isNaN(x)?0:x; }catch(e){ return 0; } }

  function isServiceLine(ln){
    const norm = (v) => String(v || '').trim().toLowerCase();
    const dType = norm(ln.detailed_type || ln.product_detailed_type);
    const pType = norm(ln.product_type);
    const unit = norm(ln.unit || ln.product_unit);
    const desc = norm(ln.description);
    return dType === 'service'
      || pType === 'service'
      || unit === 'service' || unit === 'svc' || unit === 'shp' || unit === 'shipment'
      || desc.startsWith('shipping:')
      || (desc.includes('shipping') && desc.includes('(do #'));
  }

  // read PO id from PHP (supports both numeric and UUID/public_id)
  const id = '<?= esc($poIdentifier ?? '') ?>';
  if (!id) { showError('Invalid PO id'); return; }
  const downloadPdfBtn = document.getElementById('downloadPdfBtn');
  if (downloadPdfBtn) downloadPdfBtn.href = '<?= site_url('new-purchase-orders/') ?>' + id + '/pdf';

  // ─── Jerk-free dropdown (fixed-position, no Popper.js) ───────────────
  (function() {
    const mb = document.getElementById('poMoreBtn');
    const mm = document.getElementById('poMoreMenu');
    if (!mb || !mm) return;
    mb.addEventListener('click', function(e) {
      e.stopPropagation();
      if (!mm.classList.contains('is-open')) {
        const r = mb.getBoundingClientRect();
        mm.style.top   = (r.bottom + 5) + 'px';
        mm.style.right = (document.documentElement.clientWidth - r.right) + 'px';
        mm.style.left  = 'auto';
      }
      mm.classList.toggle('is-open');
    });
    document.addEventListener('click', function() { mm.classList.remove('is-open'); });
    mm.addEventListener('click', function(e) { e.stopPropagation(); });
  })();

  (async ()=>{
    try {
      container.innerHTML = 'Loading...';
      const j = await fetchJson('<?= site_url('new-purchase-orders/') ?>'+id, { method:'GET', headers:{'Accept':'application/json'} });
      if (!j.success || !j.data) throw new Error('Failed to load PO');
      const p = j.data.po || j.data;
      const lines = j.data.lines || [];
  // header/meta
  const poNumber = p.po_number || p.id || '';
  const vendor = p.vendor_name || p.vendor_id || '';
  let dateRaw = p.order_date || p.po_date || p.created_at || '';
  if (!dateRaw || dateRaw.startsWith('0000')) dateRaw = p.created_at || '';
  let dateText = dateRaw.substring(0, 10);
  if (dateText && dateText.indexOf('-') > 0) {
    const dp = dateText.split('-');
    if (dp.length === 3) dateText = `${dp[2]}-${dp[1]}-${dp[0]}`;
  }

  // Delivery date is treated as the primary/only deadline; fall back to other possible field names if missing
  const deadlineRaw = p.delivery_date || p.delivery_deadline || p.deadline || p.expected_delivery_date || p.delivery_due_date || p.due_date || '';
  const status = p.status || '';
  const currency = p.currency || DEFAULT_PURCHASE_CURRENCY;
  const serviceLineCount = lines.filter(isServiceLine).length;
  const isServiceDoc = !!p.is_service_document || (lines.length > 0 && serviceLineCount === lines.length);
  const isMixedDoc = !isServiceDoc && (serviceLineCount > 0);
  const showReceiptCols = !isServiceDoc;
  const shippingCtx = p.shipping_context || null;

  const statusLabel = String(status || 'draft').replace(/_/g, ' ');
  const statusKey = String(status || 'draft').toLowerCase().replace(/[^a-z0-9]+/g, '-');
  document.getElementById('poSmall').textContent = `#${poNumber}`;

  // Delivery deadline, resolved once: the facts row shows it, and the route
  // strip below is reserved for service-PO shipping context.
  const delivery = (function resolveDelivery(){
    if (!deadlineRaw) return { text: 'Not set', note: '', tone: '' };
    let parsed = new Date(deadlineRaw);
    if (isNaN(parsed.getTime()) && typeof deadlineRaw === 'string') parsed = new Date(String(deadlineRaw).replace(' ', 'T'));
    if (isNaN(parsed.getTime())) return { text: String(deadlineRaw), note: '', tone: '' };
    const today = new Date(); today.setHours(0,0,0,0);
    const deadline = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    const diffDays = Math.round((deadline - today) / (1000*60*60*24));
    const text = String(deadline.getDate()).padStart(2,'0') + '-' + String(deadline.getMonth()+1).padStart(2,'0') + '-' + deadline.getFullYear();
    if (diffDays < 0) return { text, note: `${Math.abs(diffDays)} day(s) overdue`, tone: 'is-late' };
    if (diffDays === 0) return { text, note: 'Due today', tone: 'is-due' };
    return { text, note: `${diffDays} day(s) remaining`, tone: '' };
  })();

  function fact(label, value, note, tone){
    return `<div class="po-fact">
        <div class="po-fact-label">${label}</div>
        <div class="po-fact-value" title="${String(value).replace(/"/g, '&quot;')}">${value}</div>
        ${note ? `<div class="po-fact-note ${tone || ''}">${note}</div>` : ''}
      </div>`;
  }

  document.getElementById('poMeta').innerHTML =
      fact('Vendor', vendor || '-')
    + fact('PO Date', dateText || '-')
    + fact('Delivery Date', delivery.text, delivery.note, delivery.tone)
    + fact('Currency', currency);
  const statusChip = document.getElementById('poStatusChip');
  if (statusChip) {
    statusChip.className = `po-status-chip ${statusKey}`;
    statusChip.innerHTML = `<i class="bi bi-circle-fill" style="font-size:.48rem"></i>${statusLabel || 'Draft'}`;
  }
  const docTypeEl = document.getElementById('poDocType');
  if (docTypeEl) {
    if (isServiceDoc) {
      docTypeEl.innerHTML = `<span class="po-doc-type-badge service"><i class="bi bi-truck"></i>Service PO (Shipping / Non-Stock)</span>`;
    } else if (isMixedDoc) {
      docTypeEl.innerHTML = `<span class="po-doc-type-badge mixed"><i class="bi bi-layers"></i>Mixed PO (Stock + Service)</span>`;
    } else {
      docTypeEl.innerHTML = `<span class="po-doc-type-badge inventory"><i class="bi bi-box-seam"></i>Inventory PO (Stock Receipt Required)</span>`;
    }
  }

  // Service POs carry shipping context (source DO, weight, ETA); stock POs get
  // their delivery info from the facts row above, so the strip stays empty.
  (function renderShippingRoute(){
    const el = document.getElementById('poDeadline'); if(!el) return;
    el.innerHTML = '';
    if (isServiceDoc) {
      const doId = shippingCtx && shippingCtx.delivery_order_id ? shippingCtx.delivery_order_id : null;
      const doNo = shippingCtx && shippingCtx.delivery_order_number ? shippingCtx.delivery_order_number : '';
      const wt = shippingCtx && Number(shippingCtx.shipment_weight_kg || 0) > 0 ? Number(shippingCtx.shipment_weight_kg || 0) : 0;
      const shippedAt = shippingCtx && shippingCtx.shipped_at ? String(shippingCtx.shipped_at).substring(0,10) : '';
      const etaDays = shippingCtx && shippingCtx.estimated_delivery_days ? Number(shippingCtx.estimated_delivery_days) : 0;
      const destination = shippingCtx && shippingCtx.destination_country ? String(shippingCtx.destination_country) : '';
      const doText = doId
        ? `<a href=\"<?= site_url('delivery-orders/view/') ?>${doId}\" target=\"_blank\" rel=\"noopener\" class=\"fw-semibold\">${doNo || ('DO #' + doId)}</a>`
        : (doNo || '<span class="text-muted">N/A</span>');
      el.innerHTML = `<span class="po-route-item"><i class="bi bi-truck"></i><strong>Source DO</strong> ${doText}</span>`
        + `${wt > 0 ? `<span class="po-route-item"><i class="bi bi-box2-heart"></i><strong>Weight</strong> ${fmt(wt)} kg</span>` : ''}`
        + `${shippedAt ? `<span class="po-route-item"><i class="bi bi-send-check"></i><strong>Shipped</strong> ${shippedAt}</span>` : ''}`
        + `${etaDays > 0 ? `<span class="po-route-item"><i class="bi bi-clock-history"></i><strong>ETA</strong> ${etaDays} day(s)</span>` : ''}`
        + `${destination ? `<span class="po-route-item"><i class="bi bi-geo-alt"></i><strong>To</strong> ${destination}</span>` : ''}`;
      return;
    }
    })();

  // (stamp is rendered after totals are computed — see try block below)

  // Variables for button setup logic (will be populated during totals calculation)
  let totalPending = 0;
  const grnId = j.data.po.grn_id || null;
  const grnPublicId = j.data.po.grn_public_id || null;

      let html = `<section class="po-section po-lines-section"><div class="po-section-head"><h5 class="po-section-title"><i class="bi bi-list-check"></i>Line Items</h5><span class="text-muted small">${lines.length} line(s)</span></div><div class="table-responsive" data-doc-lines-root><table class="table table-sm align-middle so-lines-table po-lines-table" data-doc-line-type="purchase_order" data-doc-id="${id}"><thead><tr style="white-space:nowrap;">
        <th style="width:4%" class="text-center">No.</th>
        <th style="width:6%; padding-right:6px;" class="col-code">Code</th>
        <th style="width:5%; padding-left:6px;" class="col-img">Image</th>
        <th style="width:22%">Product / Description</th>
        ${showReceiptCols ? '<th style="width:5%">Unit</th>' : ''}
        ${showReceiptCols ? '<th style="width:6%" class="text-end">Ordered</th>' : ''}
        ${showReceiptCols ? '<th style="width:6%" class="text-end">Received</th>' : ''}
        ${showReceiptCols ? '<th style="width:6%" class="text-end">Pending</th>' : ''}
        <th style="width:8%" class="text-end">Unit Price</th>
        <th style="width:9%" class="text-end">Line Total</th>
      </tr></thead><tbody>`;

      // compute totals from lines to avoid relying only on server-provided totals
      let computedSubtotal = 0, computedTotalTax = 0, computedDiscount = 0, computedGrand = 0;
      let totalOrdered = 0, totalReceived = 0;
      let lineNo = 0;
      let activeSectionId = 0;
      let activeSectionSubtotal = 0;
      const sectionLabelColspan = showReceiptCols ? 9 : 5;
      const sectionRowColspan = showReceiptCols ? 10 : 6;
      
      lines.forEach((ln, idx)=>{
        const isSection = String(ln.display_type || 'line').toLowerCase() === 'section';
        if (isSection) {
          return;
        }
        const qty = parseNumber(ln.qty);
        const qtyReceived = parseNumber(ln.qty_received);
        const qtyPending = Math.max(0, qty - qtyReceived);
        const lineIsService = isServiceLine(ln);
        
        // Accumulate quantity totals
        if (showReceiptCols || !lineIsService) {
          totalOrdered += qty;
          totalReceived += qtyReceived;
          totalPending += qtyPending;
        }
        
        const unit_price = parseNumber(ln.unit_price);
        const discPct = parseNumber(ln.discount_percent);
        const taxPct = parseNumber(ln.tax_percent);
        const lineBase = qty * unit_price;
        // prefer explicit discount_amount/tax_amount if provided, otherwise compute from percents
        const discountAmount = (typeof ln.discount_amount !== 'undefined' && ln.discount_amount !== null) ? parseNumber(ln.discount_amount) : ((discPct/100) * lineBase);
        const taxable = Math.max(0, lineBase - discountAmount);
        const taxAmount = (typeof ln.tax_amount !== 'undefined' && ln.tax_amount !== null) ? parseNumber(ln.tax_amount) : ((taxPct/100) * taxable);
        // Always compute lineTotal from components to avoid trusting possibly-zero server values
        const lineTotal = Math.max(0, lineBase - discountAmount + taxAmount);

        computedSubtotal += lineBase;
        computedDiscount += discountAmount;
        computedTotalTax += taxAmount;
        computedGrand += lineTotal;

        let img = '';
        if (ln.variant_image_url) {
          img = ln.variant_image_url;
        } else if (ln.variant_image) {
          img = '<?= base_url('/uploads/variants/') ?>' + String(ln.variant_image || '').replace(/^\//,'');
        } else if (ln.product_image_url) {
          img = ln.product_image_url;
        } else if (ln.product_image) {
          img = '<?= base_url('/uploads/products/') ?>' + String(ln.product_image || '').replace(/^\//,'');
        }
        // No image on file: an icon reads better than a grey placeholder rectangle,
        // and service lines (shipping, fees) never have one by design. A stored
        // image that fails to load still falls back to the shared placeholder.
        const noImg = '<?= base_url('assets/images/no-image.png') ?>';
        const imgCell = img
          ? `<img src="${img}" alt="" class="js-product-hover-thumb po-line-thumb" data-preview-src="${img}" style="object-fit:cover" onerror="this.onerror=null;this.src='${noImg}';this.setAttribute('data-preview-src','${noImg}')">`
          : `<span class="po-line-thumb is-empty"><i class="bi ${lineIsService ? 'bi-truck' : 'bi-image'}"></i></span>`;
        const code = ln.variant_art_number || ln.variant_code || ln.product_code || ln.product_id || '';
        const lineName = ln.product_name || '';
        const variantText = [ln.variant_art_number, ln.variant_name].filter(Boolean).join(' ');
        let lineDesc = ln.description || '';

        // Build sub-line: if we have a product name as the bold title, only show variant info
        // in the sub-line (avoids repeating the product name that's already in the description string)
        let subText = '';
        if (lineName) {
          subText = variantText || '';
        } else {
          subText = lineDesc;
          if (variantText) {
            subText = lineDesc ? `${variantText} • ${lineDesc}` : variantText;
          }
        }
        let lineText = lineName || subText || (code ? code : '—');

        lineNo++;
        html += `<tr data-line-id="${ln.id || ''}" data-display-type="line" data-line-updated-at="${ln.updated_at || ''}">`;
        html += `<td class="text-center text-muted fw-bold">${lineNo}</td>`;
        html += `<td class="col-code" style="padding-right:6px;"><span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span><span class="po-line-code">${code || '—'}</span></td>`;
        html += `<td class="col-img" style="padding-left:6px;">${imgCell}</td>`;
        html += `<td><div class="po-line-title" style="line-height:1.2;">${lineText}</div>`;
        if (subText && subText !== lineText) {
          html += `<div class="text-muted" style="font-size:0.8rem; line-height:1.2;">${subText}</div>`;
        }
        html += `</td>`;
        if (showReceiptCols) {
          html += `<td>${ln.unit || ln.product_unit || 'pcs'}</td>`;
          html += `<td class="text-end">${fmt(qty)}</td>`;
          html += `<td class="text-end"><span class="badge bg-success">${fmt(qtyReceived)}</span></td>`;
          html += `<td class="text-end"><span class="badge bg-warning text-dark">${fmt(qtyPending)}</span></td>`;
        }
        html += `<td class="text-end">${fmt(unit_price)}</td>`;
        html += `<td class="text-end"><span class="po-line-total">${fmt(lineTotal)}</span></td>`;
        html += `</tr>`;

      });
      html += `</tbody></table></div></section>`;

      const overQty = parseNumber(p.over_received_total || 0);
      const totalPhysicalReceived = totalReceived + overQty;

      // Prefer computed totals (server may omit or zero them). Fall back to server values only if no lines.
      const subtotal = (lines.length>0) ? computedSubtotal : ((typeof p.subtotal !== 'undefined')? p.subtotal : (p.total_before_tax || 0));
      const totalTax = (lines.length>0) ? computedTotalTax : ((typeof p.total_tax !== 'undefined')? p.total_tax : (p.tax_amount || 0));
      const grand = (lines.length>0) ? computedGrand : ((typeof p.grand_total !== 'undefined')? p.grand_total : (p.total || p.total_amount || 0));
      // Add summary block (receipt for inventory docs, service summary for service docs)
      html += `<div class="po-insight-grid">
        <div class="po-summary-card">`;
      if (isServiceDoc) {
        html += `<div class="po-section-head"><h5 class="po-section-title"><i class="bi bi-truck"></i>Service Summary</h5></div>
          <div class="po-summary-metrics">
            <div class="po-summary-metric">
              <span>Shipment Weight</span>
              <strong>${shippingCtx && Number(shippingCtx.shipment_weight_kg || 0) > 0 ? (fmt(Number(shippingCtx.shipment_weight_kg || 0)) + ' kg') : 'N/A'}</strong>
            </div>
            <div class="po-summary-metric">
              <span>Source DO</span>
              <strong>${shippingCtx && shippingCtx.delivery_order_id ? `<a href="<?= site_url('delivery-orders/view/') ?>${shippingCtx.delivery_order_id}" target="_blank" rel="noopener">${shippingCtx.delivery_order_number || ('DO #' + shippingCtx.delivery_order_id)}</a>` : 'N/A'}</strong>
            </div>
          </div>
          <div class="po-info-banner"><i class="bi bi-info-circle"></i> Non-stock service PO. No inventory receipt is required.</div>`;
      } else {
        html += `<div class="po-section-head"><h5 class="po-section-title"><i class="bi bi-box-seam"></i>Receipt Summary</h5></div>
          <div class="po-summary-metrics" style="grid-template-columns:repeat(3,minmax(0,1fr));">
            <div class="po-summary-metric">
              <span>Ordered</span>
              <strong>${fmt(totalOrdered)}</strong>
            </div>
            <div class="po-summary-metric">
              <span>Received</span>
              <strong style="color:var(--cl-color-success)">${fmt(totalPhysicalReceived)}</strong>
            </div>
            <div class="po-summary-metric">
              <span>Pending</span>
              <strong style="color:var(--cl-color-warning)">${fmt(totalPending)}</strong>
            </div>
          </div>
          ${totalPending > 0 ? `
          <div class="po-info-banner is-warn"><i class="bi bi-arrow-up-circle"></i>Use the <strong>Receive</strong> action to receive pending items.</div>
          ` : ''}
          ${totalPending === 0 && totalPhysicalReceived >= totalOrdered && totalOrdered > 0 ? `
          <div class="po-info-banner is-ok"><i class="bi bi-check-circle"></i> Fully Received${overQty > 0 ? ` (+${fmt(overQty)} extra)` : ''}</div>
          ` : ''}`;
      }
      html += `</div>
        <div class="po-totals-card">
          <div class="po-totals-row"><span>Subtotal</span><strong>${fmt(subtotal)} ${currency}</strong></div>
          <div class="po-totals-row"><span>Total Discount</span><strong style="color:var(--cl-color-danger)">- ${fmt(computedDiscount)} ${currency}</strong></div>
          <div class="po-totals-row"><span>Total Tax</span><strong>${fmt(totalTax)} ${currency}</strong></div>
          <div class="po-totals-row grand"><span>Grand Total</span><strong>${fmt(grand)} ${currency}</strong></div>
        </div>
      </div>`;

      html += `<section class="po-notes-card"><div class="po-section-head"><h5 class="po-section-title"><i class="bi bi-card-text"></i>Notes</h5></div><div class="po-notes-box">${esc(p.notes || 'No notes recorded for this purchase order.')}</div></section>`;

      const overReasons = Array.isArray(p.over_receipt_reasons) ? p.over_receipt_reasons : [];
      if (!isServiceDoc && overQty > 0) {
        html += `<div class="mt-3 p-3 border rounded" style="background:rgba(245,158,11,.08);border-color:rgba(245,158,11,.3)!important;">
          <div class="fw-semibold text-warning mb-1"><i class="bi bi-exclamation-triangle me-1"></i>Extra Received Qty: ${fmt(overQty)}</div>`;
        if (overReasons.length) {
          const reasonText = overReasons.map(r => {
            const t = (r.over_receipt_reason_type || '').replaceAll('_',' ');
            const d = r.over_receipt_reason_details || '';
            const dt = (r.received_at || '').toString().substring(0,10);
            const grnPart = r.grn_id
              ? `<a href="<?= site_url('new-purchase-grns/detail/') ?>${(r.grn_public_id || r.grn_id)}" class="fw-semibold" target="_blank">GRN #${r.grn_id}</a>`
              : 'GRN';
            const isFreeReplacement = (r.over_receipt_reason_type || '') === 'replacement_free';
            const policyNote = isFreeReplacement
              ? '<div class="small text-info">Accounting treatment: free replacement is received into stock, but does not increase vendor payable and does not create an extra payable bill.</div>'
              : '';
            return `<div class="small text-muted">${grnPart} | ${dt || '-'} | ${(t || 'reason not set')}${d ? (' | ' + d) : ''}</div>${policyNote}`;
          }).join('');
          html += reasonText;
        }
        html += `</div>`;
      }

      const poBills = Array.isArray(p.vendor_bills) ? p.vendor_bills : [];
      if (poBills.length) {
        html += `<section class="po-bills-card"><div class="po-section-head"><h5 class="po-section-title"><i class="bi bi-receipt"></i>PO Bills</h5><span class="text-muted small">${poBills.length} bill(s)</span></div><div class="table-responsive"><table class="table table-sm align-middle po-bills-table"><thead><tr><th>Bill</th><th>Date</th><th>Status</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Source</th><th>Action</th></tr></thead><tbody>`;
        poBills.forEach(b => {
          const st = (b.status || '').toLowerCase();
          const billDate = (b.bill_date || '').toString().substring(0,10);
          const basedOn = b.based_on || '';
          const sourceLabel = b.source_label || (basedOn || '-');
          const canConfirm = st === 'draft';
          const badge = st==='confirmed'?'success':(st==='paid'?'primary':(st==='partially_paid'?'warning':'secondary'));
          html += `<tr>
            <td><a href="<?= site_url('vendor-bills/') ?>${b.id}" target="_blank" class="fw-semibold">${b.bill_number || ('VB-'+b.id)}</a></td>
            <td>${billDate || '-'}</td>
            <td><span class="badge bg-${badge}">${(st||'draft').toUpperCase()}</span></td>
            <td class="text-end">${fmt(parseNumber(b.total_amount||0))}</td>
            <td class="text-end">${fmt(parseNumber(b.balance||0))}</td>
            <td>${sourceLabel}</td>
            <td>
              ${canConfirm
                ? `<div class="d-flex gap-1 align-items-center">
                    <input type="date" class="form-control form-control-sm bill-date" data-bill-id="${b.id}" value="${billDate || new Date().toISOString().slice(0,10)}" style="max-width:150px;">
                    <button class="btn btn-sm btn-success js-confirm-bill" data-bill-id="${b.id}">Confirm</button>
                  </div>`
                : `<a href="<?= site_url('vendor-bills/') ?>${b.id}" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>`}
            </td>
          </tr>`;
        });
        html += `</tbody></table></div></section>`;
      } else {
        html += `<section class="po-bills-card"><div class="po-section-head"><h5 class="po-section-title"><i class="bi bi-receipt"></i>PO Bills</h5><span class="text-muted small">0 bill(s)</span></div><div class="po-notes-box"><i class="bi bi-info-circle me-1"></i>No vendor bills are attached to this purchase order yet.</div></section>`;
      }
      container.innerHTML = html;
      const manageTagsBtn = document.getElementById('manageTagsBtn');
      if (manageTagsBtn) {
        manageTagsBtn.dataset.docId = (p.id || id);
        manageTagsBtn.style.display = 'inline-flex';
      }
      
      // Setup actions based on PO data
      try {
        const poStatus = (''+status).toLowerCase();
        const vendorBillStatus = (''+(p.vendor_bill_status || '')).toLowerCase();

        // ─── Diagonal stamp ───────────────────────────────────────────────
        const stampEl = document.getElementById('poStamp');
        if (stampEl) {
          const sl = poStatus;
          const stampMap = {
            received:  { label:'RECEIVED',  color:'#16a34a' },
            completed: { label:'RECEIVED',  color:'#16a34a' },
            closed:    { label:'CLOSED',    color:'#64748b' },
            cancelled: { label:'CANCELLED', color:'#dc2626' },
            cancel:    { label:'CANCELLED', color:'#dc2626' },
          };
          let stampCfg = null;
          Object.keys(stampMap).forEach(k => { if (sl.indexOf(k) !== -1) stampCfg = stampMap[k]; });
          // Also stamp RECEIVED when all lines fully received (even if status name differs)
          if (!stampCfg && totalPending === 0 && totalPhysicalReceived > 0 && totalPhysicalReceived >= totalOrdered) {
            stampCfg = { label:'RECEIVED', color:'#16a34a' };
          }
          if (stampCfg) {
            stampEl.textContent = stampCfg.label;
            stampEl.style.color = stampCfg.color;
            stampEl.style.borderColor = stampCfg.color;
            stampEl.style.display = '';
          }
        }

        // ─── Receive / View GRN in dropdown ──────────────────────────────
        const receiveItem     = document.getElementById('receiveItem');
        const receiveItemSep  = document.getElementById('receiveItemSep');
        const receiveItemIcon = receiveItem ? receiveItem.querySelector('i') : null;
        const receiveItemLbl  = document.getElementById('receiveItemLabel');
        const closePoBtn      = document.getElementById('closePoBtn');
        const createBillBtn   = document.getElementById('createBillBtn');
        const setDraftBtn     = document.getElementById('setDraftBtn');
        const resetRfqBtn     = document.getElementById('resetRfqBtn');
        const editBtn         = document.getElementById('editBtn');

        // Show Edit button for draft/pending POs
        if (editBtn && (poStatus === 'draft' || poStatus === 'pending')) {
          editBtn.href = '<?= site_url("newpurchaseui/rfqpo") ?>?edit_po=' + id;
          editBtn.style.display = '';
        }

        if (receiveItem) {
          if (isServiceDoc || p.suppress_receiving) {
            receiveItem.style.display = 'none';
            if (receiveItemSep) receiveItemSep.style.display = 'none';
          } else if (grnId) {
            if (receiveItemLbl)  receiveItemLbl.textContent = 'View GRN';
            if (receiveItemIcon) receiveItemIcon.className = 'bi bi-eye';
            if (receiveItemIcon) receiveItemIcon.style.color = '#16a34a';
            receiveItem.href = '<?= site_url("new-purchase-grns/detail/") ?>' + (grnPublicId || grnId);
            receiveItem.style.display = '';
            if (receiveItemSep) receiveItemSep.style.display = '';
          } else if (poStatus.indexOf('closed') === -1 && poStatus.indexOf('cancel') === -1) {
            if (receiveItemLbl)  receiveItemLbl.textContent = 'Receive';
            if (receiveItemIcon) receiveItemIcon.className = 'bi bi-box-arrow-in-down';
            if (receiveItemIcon) receiveItemIcon.style.color = '#16a34a';
            receiveItem.href = '<?= site_url("purchases/grn") ?>' + '?po_id=' + id;
            receiveItem.style.display = '';
            if (receiveItemSep) receiveItemSep.style.display = '';
          }
        }

        // Reopen to draft for quantity/price corrections (blocked if confirmed/paid bill exists).
        if (setDraftBtn && poStatus !== 'draft' && poStatus.indexOf('cancel') === -1 && poStatus.indexOf('closed') === -1) {
          setDraftBtn.style.display = 'flex';
          if (vendorBillStatus === 'confirmed' || vendorBillStatus === 'partially_paid' || vendorBillStatus === 'paid') {
            setDraftBtn.classList.add('disabled');
            setDraftBtn.title = 'Blocked: confirmed/paid bill exists. Use adjustment bill flow.';
          } else {
            setDraftBtn.addEventListener('click', async function() {
              if (!confirm('Set this PO to Draft mode? You can then edit quantities and recreate bill for remaining amount.')) {
                return;
              }
              try {
                const resp = await fetchJson('<?= site_url("new-purchase-orders/") ?>'+id+'/set-draft', {
                  method:'POST',
                  headers:{'Content-Type':'application/json'}
                });
                if (resp.success) {
                  alert('PO moved to draft.');
                  location.reload();
                  return;
                }
                alert(resp.error || 'Failed to set draft mode.');
              } catch (e) {
                alert('Failed to set draft mode: ' + e.message);
              }
            });
          }
        }
        
        // Reset back to the originating RFQ. Only offered while the PO is still
        // purely commercial: no receipt, no bill, no payment. The server
        // re-checks all of this, this is just to keep the menu honest.
        if (resetRfqBtn) {
          const receivedQty = lines.reduce((sum, ln) => sum + parseNumber(ln.qty_received || 0), 0);
          const resettable = !!p.rfq_id
            && ['draft', 'pending', 'confirmed'].includes(poStatus)
            && !grnId
            && receivedQty <= 0
            && poBills.length === 0;
          if (resettable) {
            resetRfqBtn.style.display = 'flex';
            resetRfqBtn.addEventListener('click', async function() {
              if (!confirm('Reset this PO back to its RFQ?\n\nThe purchase order will be removed and the RFQ restored to draft so you can edit and confirm it again. Only possible because nothing has been received or billed.')) {
                return;
              }
              resetRfqBtn.disabled = true;
              try {
                const resp = await fetchJson('<?= site_url("new-purchase-orders/") ?>'+id+'/reset-to-rfq', {
                  method:'POST',
                  headers:{'Content-Type':'application/json','Accept':'application/json'}
                });
                if (resp && resp.success) {
                  alert(resp.message || 'PO reset to RFQ.');
                  window.location.href = resp.redirect || '<?= site_url("newpurchaseui/rfqpo") ?>';
                  return;
                }
                alert(resp.error || 'Failed to reset PO to RFQ.');
              } catch (e) {
                alert('Failed to reset PO to RFQ: ' + e.message);
              }
              resetRfqBtn.disabled = false;
            });
          }
        }

        // Show View Bill whenever bill exists; otherwise show Create Bill for allowed statuses
        if (createBillBtn) {
          const payableExtraQty = overReasons.reduce((sum, r) => {
            const reasonType = (r.over_receipt_reason_type || '').toLowerCase();
            if (reasonType === 'vendor_extra' || reasonType === 'extra_ordered') {
              return sum + parseNumber(r.over_received_qty || 0);
            }
            return sum;
          }, 0);
          const baseBills = poBills.filter(b => (b.based_on || '').toLowerCase() !== 'po_over_receipt');
          const extraBills = poBills.filter(b => (b.based_on || '').toLowerCase() === 'po_over_receipt');
          const latestBaseBill = baseBills.length ? baseBills[0] : null;
          const latestExtraBill = extraBills.length ? extraBills[0] : null;
          const canCreateBill = (poStatus.indexOf('confirm') !== -1 || poStatus === 'open' || poStatus.indexOf('partial') !== -1 || poStatus.indexOf('complete') !== -1);
          const canCreateExtraBill = payableExtraQty > 0.0001 && !latestExtraBill;
          
          if (!latestBaseBill && canCreateBill) {
            createBillBtn.innerHTML = '<i class="bi bi-receipt text-primary"></i>Create Bill';
            createBillBtn.title = 'Create base vendor bill';
            createBillBtn.style.display = 'flex';
            createBillBtn.addEventListener('click', async function() {
              if (!confirm('Create vendor bill from this PO?')) {
                return;
              }
              try {
                const resp = await fetchJson('<?= site_url("new-purchase-orders/create-bill/") ?>'+id, {
                  method:'POST',
                  headers:{'Content-Type':'application/json'}
                });
                if (resp.success && resp.bill_id) {
                  if (resp.extra_bill_available) {
                    const wantsExtraBill = confirm('Base vendor bill created successfully.\n\nThis PO also has payable extra received quantity. Create a separate extra bill now?');
                    if (wantsExtraBill) {
                      const extraResp = await fetchJson('<?= site_url("new-purchase-orders/create-extra-bill/") ?>'+id, {
                        method:'POST',
                        headers:{'Content-Type':'application/json'}
                      });
                      if (extraResp.success) {
                        alert('Base bill and extra bill created successfully.');
                        location.reload();
                        return;
                      }
                      alert('Base bill created, but extra bill failed: ' + (extraResp.error || 'Unknown error'));
                      location.reload();
                      return;
                    }
                  }
                  window.location.href = '<?= site_url("vendor-bills/") ?>' + resp.bill_id;
                } else if (resp.success) {
                  alert(resp.message || 'No remaining unbilled quantity.');
                } else {
                  alert('Failed to create bill: ' + (resp.error || 'Unknown error'));
                }
              } catch (e) {
                alert('Failed to create bill: ' + e.message);
              }
            });
          } else if (canCreateExtraBill) {
            createBillBtn.innerHTML = '<i class="bi bi-receipt-cutoff text-warning"></i>Create Extra Bill';
            createBillBtn.title = 'Create vendor bill for payable extra received quantity';
            createBillBtn.style.display = 'flex';
            createBillBtn.addEventListener('click', async function() {
              if (!confirm('Create a separate vendor bill for payable extra received quantity?')) {
                return;
              }
              try {
                const resp = await fetchJson('<?= site_url("new-purchase-orders/create-extra-bill/") ?>'+id, {
                  method:'POST',
                  headers:{'Content-Type':'application/json'}
                });
                if (resp.success && resp.bill_id) {
                  alert(resp.message || 'Extra vendor bill created successfully');
                  window.location.href = '<?= site_url("vendor-bills/") ?>' + resp.bill_id;
                } else if (resp.success) {
                  alert(resp.message || 'No remaining extra quantity to bill.');
                  location.reload();
                } else {
                  alert('Failed to create extra bill: ' + (resp.error || 'Unknown error'));
                }
              } catch (e) {
                alert('Failed to create extra bill: ' + e.message);
              }
            });
          } else if (latestBaseBill) {
            // Base bill exists - change to "View Bill" button
            createBillBtn.innerHTML = '<i class="bi bi-receipt text-primary"></i>View Bill';
            createBillBtn.title = 'View existing vendor bill';
            createBillBtn.style.display = 'flex';
            createBillBtn.addEventListener('click', function() {
              window.location.href = '<?= site_url("vendor-bills/") ?>' + latestBaseBill.id;
            });
          }
        }
        
        // Show Close PO button only if there are pending quantities and PO is not already closed/cancelled
        if (closePoBtn && !isServiceDoc && totalPending > 0 && poStatus.indexOf('closed') === -1 && poStatus.indexOf('cancel') === -1) {
          closePoBtn.style.display = 'flex';
          const sep = document.getElementById('closePoSep'); if(sep) sep.style.display = '';
          closePoBtn.addEventListener('click', async function() {
            if (!confirm(`Are you sure you want to close this PO?\n\nPending quantity: ${fmt(totalPending)}\n\nThis will prevent further receipts.`)) {
              return;
            }
            try {
              const resp = await fetchJson('<?= site_url("new-purchase-orders/") ?>'+id+'/close', { 
                method:'POST', 
                headers:{'Content-Type':'application/json'} 
              });
              if (resp.success) {
                alert('PO closed successfully');
                location.reload();
              } else {
                alert('Failed to close PO: ' + (resp.error || 'Unknown error'));
              }
            } catch (e) {
              alert('Failed to close PO: ' + e.message);
            }
          });
        }
      } catch (e) { console.error('Button setup error:', e); }
      
      // wire print — open clean HTML print view in new tab
      document.getElementById('printBtn').addEventListener('click', function(){
        window.open('<?= site_url('new-purchase-orders/') ?>' + id + '/print', '_blank');
      });

      // confirm bill from PO bills table with editable date
      document.querySelectorAll('.js-confirm-bill').forEach((btn)=>{
        btn.addEventListener('click', async ()=>{
          const billId = btn.getAttribute('data-bill-id');
          if(!billId) return;
          const dateEl = document.querySelector('.bill-date[data-bill-id="'+billId+'"]');
          const billDate = dateEl ? dateEl.value : '';
          if(!confirm('Confirm this bill?')) return;
          try{
            const resp = await fetchJson('<?= site_url('vendor-bills/') ?>'+billId+'/confirm', {
              method:'POST',
              headers:{'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
              body: JSON.stringify({ bill_date: billDate })
            });
            if(resp.success){ alert('Bill confirmed'); location.reload(); }
            else alert(resp.error || 'Failed to confirm bill');
          }catch(e){ alert('Failed to confirm bill: '+e.message); }
        });
      });
    } catch (e) {
      showError(e.message||'Failed to load PO');
    }
  })();
})();

// Function to receive remaining items for PO
function receiveRemaining(poId) {
  if (!poId) return;
  // Redirect to GRN creation page with PO pre-filled
  window.location.href = '<?= site_url("purchases/grn") ?>?po_id=' + poId;
}
</script>

<!-- ── Activity Log Panel ───────────────────────────────────────────── -->
<div class="document-log-panel mt-3" id="documentActivityLog"
     style="border-top:1px solid var(--bs-border-color,#dee2e6);padding-top:1.25rem;padding-bottom:2rem;">
  <div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-clock-history text-muted fs-5"></i>
    <h6 class="mb-0 fw-semibold text-muted text-uppercase" style="letter-spacing:.05em;font-size:.8rem;">Activity Log</h6>
    <span id="poLogCount" class="badge bg-secondary rounded-pill ms-1 fw-normal" style="font-size:.7rem;">…</span>
    <span class="ms-auto small text-muted fst-italic" style="font-size:.72rem;">All changes are recorded permanently and cannot be edited or deleted.</span>
  </div>
  <div id="poLogTimeline"><span class="text-muted small">Loading activity log…</span></div>
</div>
<script>
(function () {
  var docId = '<?= esc($poIdentifier ?? '') ?>';
  if (!docId) return;
  var logEl   = document.getElementById('poLogTimeline');
  var countEl = document.getElementById('poLogCount');
  var COLORS  = ['#6366f1','#0ea5e9','#10b981','#f59e0b','#ef4444','#8b5cf6','#ec4899','#14b8a6'];
  function avatarColor(n) { var h=0; for(var i=0;i<n.length;i++) h+=n.charCodeAt(i); return COLORS[h%COLORS.length]; }
  function iconClass(a) {
    if(!a) return 'bi-dot text-muted';
    if(a.indexOf('created')===0)        return 'bi-plus-circle-fill text-success';
    if(a.indexOf('status_changed')===0) return 'bi-arrow-right-circle-fill text-primary';
    if(a.indexOf('confirmed')===0)      return 'bi-check-circle-fill text-success';
    if(a.indexOf('posted')===0)         return 'bi-check2-all text-success';
    if(a.indexOf('sent')===0)           return 'bi-send-fill text-info';
    if(a.indexOf('cancelled')===0)      return 'bi-x-circle-fill text-danger';
    if(a.indexOf('line_added')===0)     return 'bi-plus-square-fill text-success';
    if(a.indexOf('line_removed')===0)   return 'bi-dash-square-fill text-danger';
    if(a.indexOf('line_updated')===0)   return 'bi-pencil-square text-warning';
    if(a.indexOf('pdf')===0)            return 'bi-file-earmark-arrow-down text-secondary';
    return 'bi-dot text-muted';
  }
  function esc(s){ var d=document.createElement('div'); d.appendChild(document.createTextNode(s||'')); return d.innerHTML; }
  fetch('<?= site_url('activity-log/purchase_order/') ?>' + docId, {headers:{'X-Requested-With':'XMLHttpRequest'}})
    .then(function(r){ return r.json(); })
    .then(function(data) {
      var entries = data.entries || [];
      countEl.textContent = entries.length;
      if (!entries.length) {
        logEl.innerHTML = '<div class="text-center text-muted py-4" style="font-size:.85rem;"><i class="bi bi-journal-x fs-4 d-block mb-2 opacity-50"></i>No activity recorded yet.</div>';
        return;
      }
      var html = '<div class="document-log-timeline">';
      entries.forEach(function(e, i) {
        var isLast = i === entries.length - 1;
        var ac = avatarColor(e.display_name || '');
        html += '<div class="log-entry d-flex gap-3' + (isLast ? '' : ' mb-3') + '">'
          + '<div class="log-spine d-flex flex-column align-items-center" style="width:32px;flex-shrink:0;">'
          + '<div class="log-avatar d-flex align-items-center justify-content-center rounded-circle fw-bold text-white" style="width:30px;height:30px;font-size:.72rem;background:' + ac + ';flex-shrink:0;">' + esc(e.initials || '?') + '</div>'
          + (isLast ? '' : '<div style="width:2px;flex:1;background:var(--bs-border-color,#dee2e6);margin-top:4px;min-height:16px;"></div>')
          + '</div>'
          + '<div class="log-content flex-grow-1 pb-3"' + (isLast ? ' style="padding-bottom:0!important;"' : '') + '>'
          + '<div class="d-flex align-items-start gap-2 flex-wrap">'
          + '<i class="bi ' + iconClass(e.action) + ' mt-1" style="font-size:.85rem;flex-shrink:0;"></i>'
          + '<div class="flex-grow-1"><span class="fw-semibold" style="font-size:.85rem;">' + esc(e.display_name || 'System') + '</span>'
          + '<span class="text-muted" style="font-size:.85rem;"> \u2014 ' + (e.description || '') + '</span></div>'
          + '<span class="text-muted ms-auto text-nowrap" style="font-size:.75rem;" title="' + esc(e.created_at || '') + '">' + esc(e.human_time || '') + '</span>'
          + '</div></div></div>';
      });
      html += '</div>';
      logEl.innerHTML = html;
    })
    .catch(function() { logEl.innerHTML = '<div class="text-muted small">Could not load activity log.</div>'; });
})();
</script>
<script src="<?= base_url('assets/js/document_line_tools.js') ?>"></script>

<?= $this->include('components/tag_modal') ?>

<?= $this->endSection() ?>
