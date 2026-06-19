<?= $this->extend('layouts/main') ?>

<?php $defaultCurrency = $defaultCurrency ?? 'USD'; ?>

<?= $this->section('title') ?>
Purchase Order
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
/* ─── PO Action Bar ─────────────────────────────── */
.po-action-bar {
  display: flex;
  align-items: center;
  flex-shrink: 0;
  gap: .45rem;
}

/* Shared button base */
.po-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: .35rem;
  padding: .44rem 1rem;
  border-radius: .45rem;
  font-size: .8rem;
  font-weight: 600;
  border: none;
  cursor: pointer;
  text-decoration: none !important;
  white-space: nowrap;
  line-height: 1.3;
  transition: filter .14s, transform .1s, box-shadow .14s;
}
.po-btn:focus-visible { outline: 2px solid currentColor; outline-offset: 2px; }
.po-btn:active        { transform: translateY(1px) !important; }
.po-btn i             { font-size: .85rem; line-height: 1; }

/* Back – quiet ghost */
.po-btn-back { background: transparent; color: #64748b; border: 1.5px solid #cbd5e1; }
.po-btn-back:hover { background: #f1f5f9; color: #1e293b; border-color: #94a3b8; }

/* Three-dots trigger */
.po-btn-more {
  background: #f8fafc;
  color: #475569;
  border: 1.5px solid #e2e8f0;
  padding: .44rem .65rem;
  font-size: .95rem;
}
.po-btn-more:hover { background: #e2e8f0; color: #1e293b; }

/* ─── Custom dropdown (fixed-position, zero Popper.js) ─── */
.po-dropdown-wrap { position: relative; }

.po-more-menu {
  display: none;
  position: fixed;
  z-index: 1050;
  min-width: 195px;
  list-style: none;
  margin: 0;
  padding: .3rem;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: .6rem;
  box-shadow: 0 10px 32px rgba(15,23,42,.13), 0 2px 6px rgba(15,23,42,.06);
}
.po-more-menu.is-open { display: block; }

.po-more-menu li { list-style: none; }

.po-more-menu .po-menu-item {
  display: flex;
  align-items: center;
  gap: .5rem;
  width: 100%;
  padding: .52rem .8rem;
  border-radius: .35rem;
  font-size: .82rem;
  font-weight: 500;
  color: #374151;
  background: none;
  border: none;
  cursor: pointer;
  text-decoration: none !important;
  transition: background .1s;
  white-space: nowrap;
}
.po-more-menu .po-menu-item:hover { background: #f1f5f9; color: #111827; }
.po-more-menu .po-menu-item.clr-warn { color: #b45309; }
.po-more-menu .po-menu-item.clr-warn:hover { background: #fffbeb; }
.po-more-menu .po-menu-item.clr-danger { color: #dc2626; }
.po-more-menu .po-menu-divider { height: 1px; background: #e2e8f0; margin: .25rem .5rem; }

.po-doc-type-badge {
  display: inline-flex;
  align-items: center;
  gap: .35rem;
  margin-top: .5rem;
  border-radius: 999px;
  padding: .22rem .65rem;
  font-size: .72rem;
  font-weight: 700;
  letter-spacing: .02em;
  border: 1px solid transparent;
}
.po-doc-type-badge.service { background: #ecfeff; color: #155e75; border-color: #a5f3fc; }
.po-doc-type-badge.inventory { background: #f0fdf4; color: #166534; border-color: #86efac; }
.po-doc-type-badge.mixed { background: #fffbeb; color: #92400e; border-color: #fcd34d; }

/* ─── Odoo-style diagonal stamp ─── */
.po-card-wrap { position: relative; overflow: hidden; }

.po-stamp {
  display: none;
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%) rotate(-28deg);
  font-size: clamp(2.8rem, 6vw, 5.5rem);
  font-weight: 900;
  letter-spacing: .2em;
  text-transform: uppercase;
  border: 6px solid currentColor;
  border-radius: .55rem;
  padding: .5rem 2.2rem;
  opacity: .09;
  pointer-events: none;
  z-index: 10;
  white-space: nowrap;
  user-select: none;
  line-height: 1.2;
}

/* Dark mode */
body.theme-dark .po-btn-back { color: #94a3b8; border-color: #334155; }
body.theme-dark .po-btn-back:hover { background: #1e293b; color: #e2e8f0; border-color: #475569; }
body.theme-dark .po-btn-more { background: #1e293b; color: #94a3b8; border-color: #334155; }
body.theme-dark .po-btn-more:hover { background: #334155; color: #e2e8f0; }
body.theme-dark .po-more-menu { background: #1e293b; border-color: #334155; box-shadow: 0 10px 32px rgba(0,0,0,.4); }
body.theme-dark .po-more-menu .po-menu-item { color: #cbd5e1; }
body.theme-dark .po-more-menu .po-menu-item:hover { background: #334155; color: #f1f5f9; }
body.theme-dark .po-more-menu .po-menu-divider { background: #334155; }
body.theme-dark .po-stamp { opacity: .14; }
body.theme-dark .po-doc-type-badge.service { background: rgba(8,145,178,.2); color: #67e8f9; border-color: rgba(34,211,238,.35); }
body.theme-dark .po-doc-type-badge.inventory { background: rgba(22,163,74,.2); color: #86efac; border-color: rgba(134,239,172,.35); }
body.theme-dark .po-doc-type-badge.mixed { background: rgba(217,119,6,.22); color: #fcd34d; border-color: rgba(252,211,77,.35); }

@media (max-width: 768px) {
  .po-action-bar { flex-wrap: wrap; justify-content: flex-end; }
  .po-btn { font-size: .75rem; padding: .38rem .7rem; }
}
</style>
<div class="card po-card-wrap" id="poCard">
  <!-- Odoo-style diagonal stamp (shown by JS based on status) -->
  <div id="poStamp" class="po-stamp"></div>

  <div class="card-header">
    <div class="d-flex justify-content-between align-items-start w-100" style="gap:1rem;">
      <div>
        <h3 class="mb-0">Purchase Order <small id="poSmall" class="text-muted"></small></h3>
        <div id="poMeta" class="small text-muted mt-1"></div>
        <div id="poDeadline" class="small mt-1"></div>
        <div id="poDocType" class="small mt-1"></div>
      </div>

      <div class="po-action-bar">
        <!-- Back -- always visible -->
        <a id="backLink" href="<?= site_url('newpurchaseui/rfqpo') ?>" class="po-btn po-btn-back">
          <i class="bi bi-arrow-left"></i>Back
        </a>

        <!-- Edit button -- shown for draft/pending orders -->
        <a id="editBtn" href="#" class="po-btn" style="background: #2563eb; color: #fff; display:none;" title="Edit">
          <i class="bi bi-pencil"></i>Edit
        </a>

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

  document.getElementById('poSmall').textContent = `#${poNumber}`;
  document.getElementById('poMeta').innerHTML = `<strong>Vendor:</strong> ${vendor} &nbsp;&nbsp; <strong>PO Date:</strong> ${dateText}`;
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

  // Deadline / days remaining display
  (function renderDeadline(){
    const el = document.getElementById('poDeadline'); if(!el) return;
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
      el.innerHTML = `<span class=\"text-info fw-semibold\">Source DO:</span> ${doText}`
        + `${wt > 0 ? ` &nbsp;&nbsp; <span class=\"text-info fw-semibold\">Weight:</span> ${fmt(wt)} kg` : ''}`
        + `${shippedAt ? ` &nbsp;&nbsp; <span class=\"text-muted\">Shipped: ${shippedAt}</span>` : ''}`
        + `${etaDays > 0 ? ` &nbsp;&nbsp; <span class=\"text-muted\">ETA: ${etaDays} day(s)</span>` : ''}`
        + `${destination ? ` &nbsp;&nbsp; <span class=\"text-muted\">To: ${destination}</span>` : ''}`;
      return;
    }
  if (!deadlineRaw) { el.innerHTML = '<span class="text-muted">No delivery deadline provided</span>'; return; }
    // robust parse: support date or date-time with space by normalising to ISO if needed
    let parsed = new Date(deadlineRaw);
    if (isNaN(parsed.getTime()) && typeof deadlineRaw === 'string') {
      const norm = deadlineRaw.replace(' ', 'T');
      parsed = new Date(norm);
    }
    if (isNaN(parsed.getTime())) { el.innerHTML = `<span class="text-muted">Deadline: ${deadlineRaw}</span>`; return; }
    const today = new Date(); today.setHours(0,0,0,0);
    const deadline = new Date(parsed.getFullYear(), parsed.getMonth(), parsed.getDate());
    const diffDays = Math.round((deadline - today) / (1000*60*60*24));
    
    // Format to DD-MM-YYYY
    const formattedDeadline = deadline.toISOString().slice(0,10).split('-').reverse().join('-');

    if (diffDays < 0) {
      el.innerHTML = `<span class="text-danger fw-semibold">${Math.abs(diffDays)} day(s) overdue</span> <span class="text-muted">(Delivery date: ${formattedDeadline})</span>`;
    } else if (diffDays === 0) {
      el.innerHTML = `<span class="text-warning fw-semibold">Due today (Delivery: ${formattedDeadline})</span>`;
    } else {
      el.innerHTML = `<span class="text-success fw-semibold">${diffDays} day(s) remaining</span> <span class="text-muted">(Delivery date: ${formattedDeadline})</span>`;
    }
  })();

  // (stamp is rendered after totals are computed — see try block below)

  // Variables for button setup logic (will be populated during totals calculation)
  let totalPending = 0;
  const grnId = j.data.po.grn_id || null;
  const grnPublicId = j.data.po.grn_public_id || null;

      let html = `<div class="table-responsive" data-doc-lines-root><table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;" data-doc-line-type="purchase_order" data-doc-id="${id}"><thead><tr style="white-space:nowrap;">
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
        if (!img) {
          img = '<?= base_url('assets/images/no-image.png') ?>';
        }
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
        html += `<td class="text-center text-muted">${lineNo}</td>`;
        html += `<td class="col-code" style="padding-right:6px;"><span class="doc-drag-handle me-1" title="Drag line" style="cursor:grab;opacity:.65;"><i class="bi bi-grip-vertical"></i></span><small class="text-muted">${code || '—'}</small></td>`;
        html += `<td class="col-img" style="padding-left:6px;"><img src="${img}" alt="" class="js-product-hover-thumb" data-preview-src="${img}" style="width:40px;height:40px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>';this.setAttribute('data-preview-src','<?= base_url('assets/images/no-image.png') ?>')"></td>`;
        html += `<td><div class="fw-semibold" style="line-height:1.2;">${lineText}</div>`;
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
        html += `<td class="text-end"><span>${fmt(lineTotal)}</span></td>`;
        html += `</tr>`;

      });
      html += `</tbody></table></div>`;

      const overQty = parseNumber(p.over_received_total || 0);
      const totalPhysicalReceived = totalReceived + overQty;

      // Prefer computed totals (server may omit or zero them). Fall back to server values only if no lines.
      const subtotal = (lines.length>0) ? computedSubtotal : ((typeof p.subtotal !== 'undefined')? p.subtotal : (p.total_before_tax || 0));
      const totalTax = (lines.length>0) ? computedTotalTax : ((typeof p.total_tax !== 'undefined')? p.total_tax : (p.tax_amount || 0));
      const grand = (lines.length>0) ? computedGrand : ((typeof p.grand_total !== 'undefined')? p.grand_total : (p.total || p.total_amount || 0));
      // Add summary block (receipt for inventory docs, service summary for service docs)
      html += `<div class="row mt-3">
        <div class="col-md-6">
          <div class="card">
            <div class="card-body">`;
      if (isServiceDoc) {
        html += `<h6 class="card-title text-muted mb-3"><i class="bi bi-truck"></i> Service Summary</h6>
          <div class="row">
            <div class="col-6 text-center">
              <div class="text-muted small">Shipment Weight</div>
              <div class="fs-5 fw-bold text-info">${shippingCtx && Number(shippingCtx.shipment_weight_kg || 0) > 0 ? (fmt(Number(shippingCtx.shipment_weight_kg || 0)) + ' kg') : 'N/A'}</div>
            </div>
            <div class="col-6 text-center">
              <div class="text-muted small">Source DO</div>
              <div class="fs-6 fw-bold text-info">${shippingCtx && shippingCtx.delivery_order_id ? `<a href="<?= site_url('delivery-orders/view/') ?>${shippingCtx.delivery_order_id}" target="_blank" rel="noopener">${shippingCtx.delivery_order_number || ('DO #' + shippingCtx.delivery_order_id)}</a>` : 'N/A'}</div>
            </div>
          </div>
          <div class="mt-3">
            <span class="badge bg-info-subtle text-info-emphasis w-100 p-2"><i class="bi bi-info-circle"></i> Non-stock service PO. No inventory receipt is required.</span>
          </div>`;
      } else {
        html += `<h6 class="card-title text-muted mb-3"><i class="bi bi-box-seam"></i> Receipt Summary</h6>
          <div class="row">
            <div class="col-4 text-center">
              <div class="text-muted small">Ordered</div>
              <div class="fs-5 fw-bold text-primary">${fmt(totalOrdered)}</div>
            </div>
            <div class="col-4 text-center">
              <div class="text-muted small">Received</div>
              <div class="fs-5 fw-bold text-success">${fmt(totalPhysicalReceived)}</div>
            </div>
            <div class="col-4 text-center">
              <div class="text-muted small">Pending</div>
              <div class="fs-5 fw-bold text-warning">${fmt(totalPending)}</div>
            </div>
          </div>
          ${totalPending > 0 ? `
          <div class="mt-3">
            <div class="small text-muted text-center"><i class="bi bi-arrow-up-circle me-1"></i>Use the <strong>Receive</strong> button above to receive pending items.</div>
          </div>
          ` : ''}
          ${totalPending === 0 && totalPhysicalReceived >= totalOrdered && totalOrdered > 0 ? `
          <div class="mt-3">
            <span class="badge bg-success w-100 p-2"><i class="bi bi-check-circle"></i> Fully Received${overQty > 0 ? ` (+${fmt(overQty)} extra)` : ''}</span>
          </div>
          ` : ''}`;
      }
      html += `</div>
          </div>
        </div>
        <div class="col-md-6">`;
      html += `<div class="row mt-3">
        <div class="col-12 d-flex justify-content-end">
          <div class="table-responsive">
            <table class="table table-sm table-borderless w-auto ms-auto">
              <tbody>
                <tr>
                  <td class="text-muted text-end pe-3">Subtotal</td>
                  <td class="text-end">${fmt(subtotal)} ${currency}</td>
                </tr>
                <tr>
                  <td class="text-muted text-end pe-3">Total Discount</td>
                  <td class="text-end text-danger">- ${fmt(computedDiscount)} ${currency}</td>
                </tr>
                <tr>
                  <td class="text-muted text-end pe-3">Total Tax</td>
                  <td class="text-end">${fmt(totalTax)} ${currency}</td>
                </tr>
                <tr class="table-active">
                  <td class="fw-bold text-end pe-3">Grand Total</td>
                  <td class="text-end fw-bold">${fmt(grand)} ${currency}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      </div>`;

      html += `<div class="mt-4"><label class="form-label"><strong>Notes</strong></label><textarea class="form-control form-control-sm" rows="3" readonly>${(p.notes||'')}</textarea></div>`;

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
        html += `<div class="mt-3"><h6 class="mb-2">PO Bills</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Bill</th><th>Date</th><th>Status</th><th class="text-end">Amount</th><th class="text-end">Balance</th><th>Source</th><th>Action</th></tr></thead><tbody>`;
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
        html += `</tbody></table></div></div>`;
      }
      container.innerHTML = html;
      
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
<div class="document-log-panel mt-5" id="documentActivityLog"
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

<?= $this->endSection() ?>
