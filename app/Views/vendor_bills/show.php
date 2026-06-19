<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Vendor Bill
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-start w-100">
      <div>
        <h3 class="mb-0">Vendor Bill <small id="billSmall" class="text-muted"></small></h3>
        <div id="billMeta" class="small text-muted mt-1"></div>
      </div>
      <div class="d-flex align-items-start flex-wrap" style="gap:.5rem;">
        <div id="statusBtnContainer"></div>
        <button id="payBillBtn" type="button" class="btn btn-sm btn-warning fw-semibold" style="display:none;" title="Go to vendor payment page with this bill pre-selected">
          <i class="bi bi-cash-coin me-1"></i>Pay This Bill
        </button>
        <div class="d-flex align-items-center flex-wrap" style="gap:.5rem;">
          <a id="backLink" href="<?= site_url('vendor-bills') ?>" class="btn btn-sm btn-outline-secondary">Back</a>
          <button id="confirmBtn" type="button" class="btn btn-sm btn-success" title="Confirm bill and post to accounting" style="display:none;">Confirm Bill</button>
          <button id="cancelBtn" type="button" class="btn btn-sm btn-danger" title="Cancel bill" style="display:none;">Cancel Bill</button>
          <button id="printBtn" type="button" class="btn btn-sm btn-outline-primary">Print</button>
        </div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div id="msg" style="display:none;" class="alert"></div>
    <div id="billContainer"></div>
  </div>
</div>

<div id="billImageLightbox" class="bill-lightbox" style="display:none;" aria-hidden="true">
  <button type="button" class="bill-lightbox-close" id="billLightboxClose" aria-label="Close image preview">&times;</button>
  <img id="billLightboxImage" src="" alt="Bill product image preview" class="bill-lightbox-image">
</div>

<style>
  .bill-paper {
    position: relative;
    background: linear-gradient(180deg, rgba(255,255,255,0.045), rgba(255,255,255,0.02));
    border: 1px solid rgba(148, 163, 184, 0.25);
    border-radius: 12px;
    padding: 14px;
  }

  .bill-paid-ribbon {
    position: absolute;
    top: 0;
    right: 0;
    background: #16a34a;
    color: #fff;
    font-size: 0.62rem;
    font-weight: 800;
    letter-spacing: .08em;
    text-transform: uppercase;
    padding: .24rem .5rem;
    border-bottom-left-radius: .5rem;
  }

  .bill-related-wrap { display:flex; flex-wrap:wrap; gap:.5rem; margin-top:.4rem; }
  .bill-related-chip {
    position: relative;
    border:1px solid rgba(148,163,184,.35);
    border-radius:.5rem;
    padding:.38rem .55rem;
    min-width:170px;
    background:rgba(15,23,42,.45);
  }
  .bill-related-chip .ribbon {
    position:absolute; top:0; right:0; background:#16a34a; color:#fff;
    font-size:.55rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase;
    padding:.14rem .35rem; border-bottom-left-radius:.35rem;
  }

  .bill-paper-header {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
    border-bottom: 1px dashed rgba(148, 163, 184, 0.35);
    padding-bottom: 10px;
    margin-bottom: 10px;
  }

  .bill-paper-title {
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .bill-paper-meta {
    font-size: 0.82rem;
    color: #9fb2cc;
    line-height: 1.5;
  }

  .bill-paper-no {
    font-weight: 700;
    font-size: 0.95rem;
    text-align: right;
  }

  .bill-lines-table thead th {
    border-bottom: 1px solid rgba(148, 163, 184, 0.4);
    color: #9fb2cc;
    font-size: 0.74rem;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  .bill-lines-table tbody td {
    padding-top: 12px;
    padding-bottom: 12px;
    border-top: 1px dashed rgba(148, 163, 184, 0.2);
  }

  .bill-product-image-link {
    border: 0;
    background: transparent;
    padding: 0;
    cursor: zoom-in;
  }

  .bill-line-thumb {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 6px;
    border: 1px solid rgba(148, 163, 184, 0.45);
    background: rgba(15, 23, 42, 0.55);
  }

  .bill-variant-parent {
    font-size: 0.72rem;
    color: #9fb2cc;
    margin-bottom: 1px;
  }

  .bill-variant-main {
    font-weight: 700;
    color: #e2e8f0;
  }

  .bill-variant-code {
    font-size: 0.72rem;
    color: #a8c2df;
  }

  .bill-attr-badge {
    display: inline-block;
    font-size: 0.64rem;
    padding: 1px 6px;
    border-radius: 3px;
    margin: 1px 2px 1px 0;
    background: rgba(99,102,241,.18);
    color: #c7d2fe;
    border: 1px solid rgba(99,102,241,.35);
  }

  .bill-info-card,
  .bill-totals-card {
    background: rgba(15, 23, 42, 0.45);
    border: 1px solid rgba(148, 163, 184, 0.2);
    border-radius: 10px;
    padding: 14px;
  }

  .bill-lightbox {
    position: fixed;
    inset: 0;
    z-index: 1300;
    background: rgba(2, 6, 23, 0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
  }

  .bill-lightbox-image {
    max-width: min(92vw, 1100px);
    max-height: 88vh;
    border-radius: 10px;
    border: 2px solid rgba(148, 163, 184, 0.5);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
    object-fit: contain;
  }

  .bill-lightbox-close {
    position: absolute;
    top: 14px;
    right: 18px;
    border: 0;
    border-radius: 999px;
    width: 40px;
    height: 40px;
    font-size: 1.7rem;
    line-height: 1;
    color: #e2e8f0;
    background: rgba(30, 41, 59, 0.75);
    cursor: pointer;
  }
</style>

<script>
(function(){
  'use strict';
  const DEFAULT_CURRENCY = '<?= $defaultCurrency ?? 'PKR' ?>';

  function fetchJson(url, opts) {
    const options = opts || {};
    options.headers = Object.assign({
      'Accept': 'application/json'
    }, options.headers || {});
    return fetch(url, options).then(async r => {
      if (!r.ok) {
        throw new Error('HTTP ' + r.status);
      }
      return r.json();
    });
  }

  function fmt(val, decimals=2) {
    const n = Number(val);
    if (!Number.isFinite(n)) {
      return Number(0).toLocaleString(undefined, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      });
    }
    return n.toLocaleString(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals
    });
  }

  function fmtQty(val) {
    const n = Number(val);
    if (!Number.isFinite(n)) return '0.00';
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function escHtml(val) {
    return String(val || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/\"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function appRootFromPath() {
    const p = String(window.location.pathname || '');
    const idx = p.indexOf('/vendor-bills/');
    if (idx >= 0) return p.substring(0, idx);
    return '';
  }

  function normalizeBillImageUrl(raw) {
    const value = String(raw || '').trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    if (value.startsWith('//')) return window.location.protocol + value;
    if (value.startsWith('/')) return value;
    return '/' + value;
  }

  function renderVariantAttributes(raw) {
    if (!raw) return '';
    try {
      const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
      if (Array.isArray(parsed)) {
        return parsed.map(v => String(v || '')).filter(Boolean).join(', ');
      }
      if (parsed && typeof parsed === 'object') {
        const parts = [];
        Object.keys(parsed).forEach(k => {
          const key = String(k || '').trim();
          const value = parsed[k];
          if (value === null || value === undefined || value === '') return;
          parts.push(key ? (key + ': ' + String(value)) : String(value));
        });
        return parts.join(', ');
      }
      return String(parsed || '');
    } catch (_) {
      return String(raw || '');
    }
  }

  function renderVariantAttributeBadges(raw) {
    if (!raw) return '';
    try {
      const parsed = typeof raw === 'string' ? JSON.parse(raw) : raw;
      if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return '';
      }
      const chips = [];
      Object.keys(parsed).forEach(k => {
        const key = String(k || '').trim();
        const value = parsed[k];
        if (!key || value === null || value === undefined || value === '') return;
        chips.push('<span class="bill-attr-badge">' + escHtml(key) + ': ' + escHtml(String(value)) + '</span>');
      });
      return chips.join('');
    } catch (_) {
      return '';
    }
  }

  function fmtDate(val) {
    if (!val) return 'N/A';
    const raw = String(val);
    const datePart = raw.substring(0, 10);
    if (/^\d{4}-\d{2}-\d{2}$/.test(datePart)) {
      const parts = datePart.split('-');
      return parts[2] + '-' + parts[1] + '-' + parts[0];
    }
    const d = new Date(raw);
    if (!Number.isNaN(d.getTime())) {
      const dd = String(d.getDate()).padStart(2, '0');
      const mm = String(d.getMonth() + 1).padStart(2, '0');
      const yyyy = d.getFullYear();
      return dd + '-' + mm + '-' + yyyy;
    }
    return datePart || raw;
  }

  function parseNumber(val) {
    const n = parseFloat(val);
    return isNaN(n) ? 0 : n;
  }

  function isServiceLine(line) {
    const t = String(line && line.product_detailed_type ? line.product_detailed_type : '').toLowerCase().trim();
    const unit = String(line && line.product_unit ? line.product_unit : '').toLowerCase().trim();
    const name = String(line && line.product_name ? line.product_name : '').toLowerCase();
    const desc = String(line && line.product_description ? line.product_description : '').toLowerCase();
    return t === 'service'
      || unit === 'svc' || unit === 'service' || unit === 'shp' || unit === 'shipment'
      || desc.indexOf('shipping') !== -1
      || name.indexOf('shipping') !== -1;
  }

  function showMsg(msg, type='info') {
    const msgDiv = document.getElementById('msg');
    msgDiv.className = 'alert alert-' + type;
    msgDiv.textContent = msg;
    msgDiv.style.display = 'block';
    setTimeout(() => { msgDiv.style.display = 'none'; }, 5000);
  }

  function showError(msg) { showMsg(msg, 'danger'); }

  const noImageSrc = '<?= base_url('assets/images/no-image.png') ?>';
  window.__billImgErr = function(imgEl) {
    try {
      const queue = JSON.parse(imgEl.getAttribute('data-fallbacks') || '[]');
      if (Array.isArray(queue) && queue.length > 0) {
        const next = queue.shift();
        imgEl.setAttribute('data-fallbacks', JSON.stringify(queue));
        imgEl.src = next;
        const btn = imgEl.closest('.bill-product-image-link');
        if (btn) {
          btn.setAttribute('data-image-src', next);
        }
        return;
      }
    } catch (_) {}
    imgEl.onerror = null;
    imgEl.src = noImageSrc;
    const btn = imgEl.closest('.bill-product-image-link');
    if (btn) {
      btn.setAttribute('data-image-src', noImageSrc);
    }
  };

  function openImageLightbox(src) {
    const lb = document.getElementById('billImageLightbox');
    const img = document.getElementById('billLightboxImage');
    if (!lb || !img || !src) return;
    img.src = src;
    lb.style.display = 'flex';
    lb.setAttribute('aria-hidden', 'false');
  }

  function closeImageLightbox() {
    const lb = document.getElementById('billImageLightbox');
    const img = document.getElementById('billLightboxImage');
    if (!lb || !img) return;
    lb.style.display = 'none';
    lb.setAttribute('aria-hidden', 'true');
    img.src = '';
  }

  // Extract bill ID from URL (last segment)
  const pathSegments = window.location.pathname.split('/').filter(Boolean);
  const id = pathSegments[pathSegments.length - 1];

  if (!id || id === 'vendor-bills') {
    showError('No bill ID provided');
    return;
  }

  const backLink = document.getElementById('backLink');
  if (backLink) {
    backLink.addEventListener('click', function(ev) {
      // If this page has navigation history, use it; else go to bills list.
      if (window.history.length > 1) {
        ev.preventDefault();
        window.history.back();
      }
    });
  }

  (async function() {
    try {
      const j = await fetchJson('<?= site_url("vendor-bills/") ?>' + id);
      if (!j.success || !j.data || !j.data.bill) {
        throw new Error(j.error || 'Failed to load bill');
      }

      const b = j.data.bill;
      const lines = j.data.lines || [];
      const paymentHistory = j.data.paymentHistory || [];
      const grnRefs = j.data.grnRefs || [];
      const relatedBills = j.data.relatedBills || [];
      const status = b.status || 'draft';
      const currency = b.currency_code || DEFAULT_CURRENCY;
      const isPaidBill = parseNumber(b.balance) <= 0.0001;
      const shippingCtx = b.shipping_context || null;
      const serviceLineCount = lines.filter(isServiceLine).length;
      const isServiceBill = lines.length > 0 && serviceLineCount === lines.length;

      // Update header
      document.getElementById('billSmall').textContent = b.bill_number || '#' + b.id;
      
      const vendorProfileUrl = b.vendor_id ? ('<?= site_url("vendors/") ?>' + b.vendor_id) : '';
      const vendorLedgerUrl = vendorProfileUrl;
      const poViewUrl = b.po_id ? ('<?= site_url("purchases/po/") ?>' + b.po_id) : '';

      let metaHtml = '<strong>Vendor:</strong> ';
      if (vendorProfileUrl) {
        metaHtml += '<a href="' + vendorProfileUrl + '" class="fw-semibold">' + escHtml(b.vendor_name || 'Unknown') + '</a>';
      } else {
        metaHtml += escHtml(b.vendor_name || 'Unknown');
      }

      if (vendorLedgerUrl) {
        metaHtml += ' <a href="' + vendorLedgerUrl + '" class="small text-info">(View Balance)</a>';
      }
      if (b.po_number) {
        metaHtml += ' | <strong>PO:</strong> ';
        if (poViewUrl) {
          metaHtml += '<a href="' + poViewUrl + '" class="fw-semibold">' + escHtml(b.po_number) + '</a>';
        } else {
          metaHtml += escHtml(b.po_number);
        }
      }

      if (grnRefs.length) {
        const grnLinks = grnRefs.map(g => {
          const gLabel = g.grn_number || ('GRN-' + g.id);
          const gUrl = '<?= site_url("new-purchase-grns/detail/") ?>' + (g.public_id || g.id);
          return '<a href="' + gUrl + '" class="fw-semibold">' + escHtml(gLabel) + '</a>';
        }).join(', ');
        metaHtml += ' | <strong>GRN:</strong> ' + grnLinks;
      }
      metaHtml += ' | <strong>Bill Date:</strong> ' + fmtDate(b.bill_date);
      document.getElementById('billMeta').innerHTML = metaHtml;

      // Status badge
      let statusClass = 'secondary';
      if (status === 'confirmed') statusClass = 'success';
      else if (status === 'cancelled') statusClass = 'danger';
      else if (status === 'paid') statusClass = 'info';
      else if (status === 'draft') statusClass = 'warning';
      
      const statusHtml = '<span class="badge bg-' + statusClass + ' text-uppercase">' + status + '</span>';
      const statusContainer = document.getElementById('statusBtnContainer');
      if (statusContainer) statusContainer.innerHTML = statusHtml;

      // Build lines table
      let html = '<div class="bill-paper">';
      if (isPaidBill) {
        html += '<span class="bill-paid-ribbon">Paid</span>';
      }
      html += '<div class="bill-paper-header">';
      html += '<div>';
      html += '<div class="bill-paper-title">Vendor Bill</div>';
      html += '<div class="bill-paper-meta">Vendor: ';
      if (vendorProfileUrl) {
        html += '<a href="' + vendorProfileUrl + '" class="fw-semibold">' + escHtml(b.vendor_name || 'Unknown') + '</a>';
        if (vendorLedgerUrl) {
          html += ' <a href="' + vendorLedgerUrl + '" class="small text-info">(Ledger/Balance)</a>';
        }
      } else {
        html += escHtml(b.vendor_name || 'Unknown');
      }
      html += '</div>';
      html += '<div class="bill-paper-meta">PO Ref: ';
      if (poViewUrl) {
        html += '<a href="' + poViewUrl + '" class="fw-semibold">' + escHtml(b.po_number || 'Manual Entry') + '</a>';
      } else {
        html += escHtml(b.po_number || 'Manual Entry');
      }
      html += '</div>';
      if (grnRefs.length) {
        const grnHeaderLinks = grnRefs.map(g => {
          const gLabel = g.grn_number || ('GRN-' + g.id);
          const gUrl = '<?= site_url("new-purchase-grns/detail/") ?>' + (g.public_id || g.id);
          return '<a href="' + gUrl + '" class="fw-semibold">' + escHtml(gLabel) + '</a>';
        }).join(', ');
        html += '<div class="bill-paper-meta">GRN Ref: ' + grnHeaderLinks + '</div>';
      }
      html += '</div>';
      html += '<div>';
      html += '<div class="bill-paper-no">Bill #' + escHtml(b.bill_number || ('VB-' + b.id)) + '</div>';
      html += '<div class="bill-paper-meta text-end">Bill Date: ' + fmtDate(b.bill_date) + '</div>';
      html += '</div>';
      html += '</div>';
      html += '<div class="table-responsive"><table class="table table-sm align-middle bill-lines-table" style="font-size:0.9rem;"><thead><tr style="white-space:nowrap;">';
      html += '<th style="width:10%">Code</th>';
      html += '<th style="width:60px;"></th>';
      html += '<th style="width:28%">Product</th>';
      html += '<th style="width:12%" class="text-end">' + (isServiceBill ? 'Service Qty / UOM' : 'Qty / UOM') + '</th>';
      if (!isServiceBill) {
        html += '<th style="width:10%" class="text-end">Processed Qty</th>';
        html += '<th style="width:10%" class="text-end">Already Billed</th>';
        html += '<th style="width:10%" class="text-end">Remaining Qty</th>';
      }
      html += '<th style="width:15%" class="text-end">Unit Price</th>';
      html += '<th style="width:15%" class="text-end">Line Total</th>';
      html += '</tr></thead><tbody>';

      let computedTotal = 0;
      lines.forEach(ln => {
        const qty = parseNumber(ln.qty);
        const unitPrice = parseNumber(ln.unit_price);
        const lineTotal = parseNumber(ln.line_total);
        computedTotal += lineTotal;

        const detailedType = String(ln.product_detailed_type || '').toLowerCase();
        const isService = detailedType === 'service';
        let unitLabel = String(ln.product_unit || '').trim().toUpperCase();
        if (isService && (!unitLabel || ['PC', 'PCS', 'PIECE', 'PIECES'].includes(unitLabel))) {
          unitLabel = 'SVC';
        }
        const qtyDisplay = unitLabel ? (fmtQty(qty) + ' ' + escHtml(unitLabel)) : fmtQty(qty);
        const processedQty = ln.processing_total_processed_qty;
        const alreadyBilledQty = ln.processing_total_billed_qty;
        const remainingQty = ln.processing_remaining_qty;

        const variantCode = ln.variant_art_number || '';
        const code = variantCode || ln.product_code || ln.product_id || '';
        const variantName = ln.variant_name || '';
        const variantAttrs = renderVariantAttributes(ln.variant_attributes || '');
        const name = ln.product_name || 'Product #' + (ln.product_id || '');
        const variantViewUrl = (ln.product_id && ln.variant_id)
          ? ('<?= site_url("inventory/stock/product/") ?>' + ln.product_id + '?variant_id=' + ln.variant_id)
          : '';
        const productRef = ln.product_public_id || ln.product_id || '';
        const productViewUrl = productRef ? ('<?= site_url("products/") ?>' + productRef) : '';
        const itemViewUrl = productViewUrl;
        const desc = ln.product_description || '';
        
        const imageUrl = normalizeBillImageUrl(ln.thumbnail_url || (Array.isArray(ln.image_urls) ? (ln.image_urls[0] || '') : ''));
        
        html += '<tr>';
        html += '<td>';
        if (itemViewUrl) {
          html += '<a href="' + itemViewUrl + '" class="fw-semibold">' + escHtml(code) + '</a>';
        } else {
          html += '<span class="fw-semibold">' + escHtml(code) + '</span>';
        }
        html += '</td>';
        html += '<td>';
        if (imageUrl) {
          html += '<button type="button" class="bill-product-image-link" data-image-src="' + escHtml(imageUrl) + '" title="Hover to preview, click to open image">';
          html += '<img src="' + imageUrl + '" alt="Product" class="bill-line-thumb js-bill-line-image js-product-hover-thumb" data-preview-src="' + escHtml(imageUrl) + '" onerror="this.onerror=null;this.src=\'' + noImageSrc + '\';this.setAttribute(\'data-preview-src\',\'' + noImageSrc + '\');var btn=this.closest(\'.bill-product-image-link\');if(btn){btn.setAttribute(\'data-image-src\',\'' + noImageSrc + '\');}">';
          html += '</button>';
        } else {
          html += '<img src="' + noImageSrc + '" alt="No image" class="bill-line-thumb">';
        }
        html += '</td>';
        html += '<td>';
        if (variantName) {
          html += '<div class="bill-variant-parent">';
          if (itemViewUrl) {
            html += '<a href="' + itemViewUrl + '">' + escHtml(name) + '</a>';
          } else {
            html += escHtml(name);
          }
          if (ln.product_code) {
            html += '<span class="ms-1">' + escHtml(ln.product_code) + '</span>';
          }
          html += '</div>';
          html += '<div class="bill-variant-main">';
          if (variantViewUrl) {
            html += '<a href="' + variantViewUrl + '" class="fw-semibold" style="color:#c7d2fe">' + escHtml(variantName) + '</a>';
          } else {
            html += escHtml(variantName);
          }
          html += '</div>';
          if (variantCode) {
            html += '<div class="bill-variant-code">' + escHtml(variantCode) + '</div>';
          }
          const attrBadges = renderVariantAttributeBadges(ln.variant_attributes || '');
          if (attrBadges) {
            html += '<div class="mt-1">' + attrBadges + '</div>';
          } else if (variantAttrs) {
            html += '<small class="text-muted d-block">Attributes: ' + escHtml(variantAttrs) + '</small>';
          }
        } else {
          html += '<div class="fw-semibold">';
          if (itemViewUrl) {
            html += '<a href="' + itemViewUrl + '">' + escHtml(name) + '</a>';
          } else {
            html += escHtml(name);
          }
          html += '</div>';
        }
        if (desc) {
          html += '<small class="text-muted d-block">' + escHtml(desc) + '</small>';
        }
        html += '</td>';
        html += '<td class="text-end">' + qtyDisplay + '</td>';
        if (!isServiceBill) {
          html += '<td class="text-end">' + (processedQty !== null && processedQty !== undefined ? fmtQty(processedQty) : '—') + '</td>';
          html += '<td class="text-end">' + (alreadyBilledQty !== null && alreadyBilledQty !== undefined ? fmtQty(alreadyBilledQty) : '—') + '</td>';
          html += '<td class="text-end">' + (remainingQty !== null && remainingQty !== undefined ? fmtQty(remainingQty) : '—') + '</td>';
        }
        html += '<td class="text-end">' + fmt(unitPrice, 2) + ' ' + currency + '</td>';
        html += '<td class="text-end fw-semibold">' + fmt(lineTotal, 2) + ' ' + currency + '</td>';
        html += '</tr>';
      });

      html += '</tbody></table></div>';

      // Totals section
      html += '<div class="row mt-3">';
      html += '<div class="col-md-6">';
      html += '<div class="bill-info-card">';
      html += '<h6 class="card-title text-muted mb-3">Bill Details</h6>';
      html += '<div class="row mb-2"><div class="col-5 text-muted">Based On:</div><div class="col-7">';
      if (poViewUrl) {
        html += '<a href="' + poViewUrl + '" class="badge bg-info text-decoration-none">' + escHtml(b.po_number || 'Manual') + '</a>';
      } else {
        html += '<span class="badge bg-info">' + escHtml(b.po_number || 'Manual') + '</span>';
      }
      html += '</div></div>';
      html += '<div class="row mb-2"><div class="col-5 text-muted">Bill Date:</div><div class="col-7">' + fmtDate(b.bill_date) + '</div></div>';
      if (shippingCtx && shippingCtx.delivery_order_id) {
        const doLabel = shippingCtx.delivery_order_number || ('DO #' + shippingCtx.delivery_order_id);
        const doUrl = '<?= site_url("delivery-orders/view/") ?>' + shippingCtx.delivery_order_id;
        html += '<div class="row mb-2"><div class="col-5 text-muted">Source DO:</div><div class="col-7"><a href="' + doUrl + '" class="fw-semibold" target="_blank" rel="noopener">' + escHtml(doLabel) + '</a></div></div>';
      }
      if (shippingCtx && parseNumber(shippingCtx.shipment_weight_kg) > 0) {
        html += '<div class="row mb-2"><div class="col-5 text-muted">Shipment Weight:</div><div class="col-7">' + fmt(parseNumber(shippingCtx.shipment_weight_kg), 3) + ' kg</div></div>';
      }
      if (grnRefs.length) {
        const grnDetailLinks = grnRefs.map(g => {
          const gLabel = g.grn_number || ('GRN-' + g.id);
          const gDate = fmtDate(g.received_at || '');
          const gUrl = '<?= site_url("new-purchase-grns/detail/") ?>' + (g.public_id || g.id);
          return '<a href="' + gUrl + '" class="fw-semibold">' + escHtml(gLabel) + '</a>' + (gDate !== 'N/A' ? (' <span class="text-muted">(' + escHtml(gDate) + ')</span>') : '');
        }).join('<br>');
        html += '<div class="row mb-2"><div class="col-5 text-muted">GRN Ref:</div><div class="col-7">' + grnDetailLinks + '</div></div>';
      }
      if (b.due_date) {
        html += '<div class="row mb-2"><div class="col-5 text-muted">Due Date:</div><div class="col-7">' + fmtDate(b.due_date) + '</div></div>';
      }
      if (relatedBills.length) {
        const relatedHtml = relatedBills.map(rb => {
          const ref = rb.bill_number || ('VB-' + rb.id);
          const url = '<?= site_url("vendor-bills/") ?>' + (rb.public_id || rb.id);
          const bal = parseNumber(rb.balance || 0);
          const paid = rb.is_paid || bal <= 0.0001;
          return '<div class="bill-related-chip">'
            + (paid ? '<span class="ribbon">Paid</span>' : '')
            + '<div><a href="' + url + '" class="fw-semibold">' + escHtml(ref) + '</a></div>'
            + '<div class="small text-muted">Total ' + fmt(parseNumber(rb.total_amount || 0)) + ' | Bal ' + fmt(bal) + '</div>'
            + '</div>';
        }).join('');
        html += '<div class="row mb-2"><div class="col-5 text-muted">Related Bills:</div><div class="col-7"><div class="bill-related-wrap">' + relatedHtml + '</div></div></div>';
      }
      html += '</div>';
      html += '</div>';
      
      html += '<div class="col-md-6">';
      html += '<div class="d-flex flex-column align-items-end">';
      html += '<div class="bill-totals-card" style="min-width: 360px;">';
      html += '<div class="d-flex justify-content-between align-items-center py-1">';
      html += '<span class="text-muted">Subtotal</span><span class="fw-semibold">' + fmt(parseNumber(b.total_amount)) + ' ' + currency + '</span>';
      html += '</div>';
      if (parseNumber(b.discount_amount) > 0) {
        html += '<div class="d-flex justify-content-between align-items-center py-1">';
        html += '<span class="text-muted">Discount</span><span class="text-danger fw-semibold">- ' + fmt(parseNumber(b.discount_amount)) + ' ' + currency + '</span>';
        html += '</div>';
      }
      if (parseNumber(b.tax_amount) > 0) {
        html += '<div class="d-flex justify-content-between align-items-center py-1">';
        html += '<span class="text-muted">Tax</span><span class="fw-semibold">' + fmt(parseNumber(b.tax_amount)) + ' ' + currency + '</span>';
        html += '</div>';
      }
      html += '<div class="d-flex justify-content-between align-items-center py-1 border-top border-secondary mt-1 pt-2">';
      html += '<span class="fw-bold">Total Amount</span><span class="fw-bold fs-5">' + fmt(parseNumber(b.total_amount)) + ' ' + currency + '</span>';
      html += '</div>';
      html += '<div class="d-flex justify-content-between align-items-center py-1">';
      html += '<span class="text-muted">Balance Due</span><span class="fw-semibold text-danger">' + fmt(parseNumber(b.balance)) + ' ' + currency + '</span>';
      html += '</div>';
      html += '</div></div></div></div>';

      // Payment History Section
      if (paymentHistory && paymentHistory.length > 0) {
        html += '<div class="mt-4"><h6 class="fw-bold mb-3">Payment History</h6>';
        html += '<div class="table-responsive"><table class="table table-sm" style="font-size:0.9rem;"><thead><tr style="white-space:nowrap; background: rgba(255,255,255,0.05);">';
        html += '<th style="width:15%">Payment Date</th>';
        html += '<th style="width:20%">Method</th>';
        html += '<th style="width:15%" class="text-end">From Advance</th>';
        html += '<th style="width:15%" class="text-end">From Bank/Cash</th>';
        html += '<th style="width:15%" class="text-end">Total</th>';
        html += '<th style="width:20%" class="text-end">Remaining</th>';
        html += '</tr></thead><tbody>';
        
        let runningBalance = parseNumber(b.total_amount);
        
        paymentHistory.forEach(p => {
          const advAmount = parseNumber(p.advance_amount || 0);
          const cashAmount = parseNumber(p.allocation_amount || 0) - advAmount;
          const totalPaid = parseNumber(p.allocation_amount || 0);
          runningBalance -= totalPaid;
          
          const payDate = fmtDate(p.payment_date);
          const method = p.payment_method ? p.payment_method.charAt(0).toUpperCase() + p.payment_method.slice(1) : 'N/A';
          
          html += '<tr>';
          html += '<td><strong>' + payDate + '</strong></td>';
          html += '<td><span class="badge bg-secondary">' + method + '</span></td>';
          html += '<td class="text-end">' + (advAmount > 0 ? '<span style="color:#4ade80">' + fmt(advAmount) + '</span>' : '-') + '</td>';
          html += '<td class="text-end">' + (cashAmount > 0 ? '<span style="color:#3b82f6">' + fmt(cashAmount) + '</span>' : '-') + '</td>';
          html += '<td class="text-end fw-semibold">' + fmt(totalPaid) + ' ' + currency + '</td>';
          html += '<td class="text-end fw-semibold" style="color:' + (runningBalance > 0 ? '#ef4444' : '#4ade80') + '">' + fmt(Math.max(0, runningBalance)) + ' ' + currency + '</td>';
          html += '</tr>';
        });
        
        html += '</tbody></table></div></div>';
      }

      html += '<div class="mt-4"><label class="form-label"><strong>Notes</strong></label><textarea class="form-control form-control-sm" rows="3" readonly>' + (b.notes || '') + '</textarea>';
      if (relatedBills.length) {
        const links = relatedBills.map(rb => {
          const ref = rb.bill_number || ('VB-' + rb.id);
          const url = '<?= site_url("vendor-bills/") ?>' + (rb.public_id || rb.id);
          return '<a href="' + url + '" class="fw-semibold me-2">' + escHtml(ref) + '</a>';
        }).join('');
        html += '<div class="small mt-2">Related bill links: ' + links + '</div>';
      }
      html += '</div>';

      html += '</div>';

      document.getElementById('billContainer').innerHTML = html;

      const root = document.getElementById('billContainer');
      if (root) {
        const imgs = root.querySelectorAll('img.js-bill-line-image');
        imgs.forEach((imgEl) => {
          if (imgEl.complete && Number(imgEl.naturalWidth || 0) === 0) {
            imgEl.src = noImageSrc;
            const btn = imgEl.closest('.bill-product-image-link');
            if (btn) btn.setAttribute('data-image-src', noImageSrc);
          }
        });
      }

      const billContainer = document.getElementById('billContainer');
      if (billContainer) {
        billContainer.addEventListener('click', function(ev) {
          const target = ev.target;
          const button = target && target.closest ? target.closest('.bill-product-image-link') : null;
          if (!button) return;
          const src = button.getAttribute('data-image-src') || '';
          openImageLightbox(src);
        });
      }

      // Pay This Bill button
      const payBillBtn = document.getElementById('payBillBtn');
      if (payBillBtn && (status === 'confirmed' || status === 'draft') && parseNumber(b.balance) > 0) {
        const payUrl = '<?= site_url("accounting/vendor-payments/pay") ?>?vendor_id=' + encodeURIComponent(b.vendor_id) + '&bill_id=' + encodeURIComponent(b.id);
        payBillBtn.style.display = 'inline-block';
        payBillBtn.addEventListener('click', function() {
          window.location.href = payUrl;
        });
      }

      // Setup action buttons
      const confirmBtn = document.getElementById('confirmBtn');
      const cancelBtn = document.getElementById('cancelBtn');

      if (status === 'draft') {
        // Show confirm and cancel buttons for draft bills
        if (confirmBtn) {
          confirmBtn.style.display = 'inline-block';
          confirmBtn.addEventListener('click', async function() {
            if (!confirm('Confirm this bill? This will post it to accounting and mark it as payable.')) {
              return;
            }
            try {
              const resp = await fetchJson('<?= site_url("vendor-bills/") ?>' + id + '/confirm', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
              });
              if (resp.success) {
                showMsg('Bill confirmed successfully', 'success');
                setTimeout(() => location.reload(), 1500);
              } else {
                showError('Failed to confirm bill: ' + (resp.error || 'Unknown error'));
              }
            } catch (e) {
              showError('Failed to confirm bill: ' + e.message);
            }
          });
        }

        if (cancelBtn) {
          cancelBtn.style.display = 'inline-block';
          cancelBtn.addEventListener('click', async function() {
            if (!confirm('Cancel this bill? This action cannot be undone.')) {
              return;
            }
            try {
              const resp = await fetchJson('<?= site_url("vendor-bills/") ?>' + id + '/cancel', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
              });
              if (resp.success) {
                showMsg('Bill cancelled successfully', 'success');
                setTimeout(() => location.reload(), 1500);
              } else {
                showError('Failed to cancel bill: ' + (resp.error || 'Unknown error'));
              }
            } catch (e) {
              showError('Failed to cancel bill: ' + e.message);
            }
          });
        }
      }

      // Print button
      document.getElementById('printBtn').addEventListener('click', function() {
        window.open('<?= site_url('vendor-bills/') ?>' + encodeURIComponent(id) + '/print', '_blank');
      });

      const lightbox = document.getElementById('billImageLightbox');
      const lightboxClose = document.getElementById('billLightboxClose');
      if (lightbox) {
        lightbox.addEventListener('click', function(ev) {
          if (ev.target === lightbox) {
            closeImageLightbox();
          }
        });
      }
      if (lightboxClose) {
        lightboxClose.addEventListener('click', closeImageLightbox);
      }
      document.addEventListener('keydown', function(ev) {
        if (ev.key === 'Escape') {
          closeImageLightbox();
        }
      });

    } catch (e) {
      showError(e.message || 'Failed to load bill');
    }
  })();
})();
</script>

<?= $this->endSection() ?>
