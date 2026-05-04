<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Request for Quotation
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
  <div class="card-header">
    <div class="d-flex justify-content-between align-items-start w-100">
      <div>
        <h3 class="mb-0">RFQ <small id="rfqSmall" class="text-muted"></small></h3>
        <div id="rfqMeta" class="small text-muted mt-1"></div>
      </div>
      <div class="d-flex align-items-start" style="gap:.5rem;">
        <div id="statusBtnContainer"></div>
        <div class="btn-group">
          <button id="confirmBtn" type="button" class="btn btn-sm btn-success" style="display:none;">Confirm</button>
          <button id="editRfqBtn" type="button" class="btn btn-sm btn-outline-primary" style="display:none;"><i class="bi bi-pencil me-1"></i>Edit</button>
          <a id="downloadPdfBtn" href="#" target="_blank" class="btn btn-sm btn-outline-danger"><i class="bi bi-file-earmark-pdf me-1"></i>Download PDF</a>
          <a id="backLink" href="<?= site_url('newpurchaseui/rfqpo') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
          <button id="printBtn" type="button" class="btn btn-sm btn-outline-primary">Print</button>
        </div>
      </div>
    </div>
  </div>
  <div class="card-body">
    <div id="rfqMessage"></div>
    <div id="rfqContainer">Loading RFQ...</div>
  </div>
</div>

<script>
(() => {
  const container = document.getElementById('rfqContainer');
  const msg = document.getElementById('rfqMessage');
  function showError(text){ msg.innerHTML = `<div class="alert alert-danger">${text}</div>`; container.innerHTML = ''; }
  function fetchJson(url, opts){ return fetch(url, opts||{}).then(r=>{ if(!r.ok) return r.text().then(t=>{ throw new Error(t||('HTTP '+r.status)); }); const ct = r.headers.get('content-type')||''; if(!ct.includes('application/json')) return r.text().then(t=>{ throw new Error('Non-JSON response: '+t); }); return r.json(); }); }
  function fmt(n){ try{ return Number(n||0).toLocaleString(undefined,{minimumFractionDigits:2, maximumFractionDigits:2}); }catch(e){ return (n||0).toFixed? (n||0).toFixed(2) : (n||0); } }
  function parseNumber(v){ if (v === null || typeof v === 'undefined') return 0; if (typeof v === 'number') return v; try{ var s = String(v).trim(); if(s.length===0) return 0; s = s.replace(/\s+/g,'').replace(/,/g,''); var x = parseFloat(s); return isNaN(x)?0:x; }catch(e){ return 0; } }
  const parts = location.pathname.split('/');
  const id = '<?= esc($rfqIdentifier ?? '') ?>';
  if (!id) { showError('Invalid RFQ id'); return; }
  const downloadPdfBtn = document.getElementById('downloadPdfBtn');
  if (downloadPdfBtn) downloadPdfBtn.href = '<?= site_url('new-purchase-rfqs/') ?>' + id + '/pdf';

  (async ()=>{
    try {
      container.innerHTML = 'Loading...';
      const j = await fetchJson('<?= site_url('new-purchase-rfqs/') ?>'+id, { method:'GET', headers:{'Accept':'application/json'} });
      if (!j || (!j.success && typeof j.success !== 'undefined') || !j.data) {
        // if success flag is missing but data exists, allow it; else throw
      }
      const data = j && j.data ? j.data : j || {};
      const r = data.rfq || data;
      const lines = data.lines || r.lines || [];

      const rfqNumber = r.rfq_number || r.number || r.id || '';
      const vendor = r.vendor_name || r.vendor_id || '';
      const dateText = r.rfq_date || r.created_at || '';
      const status = r.status || r.state || '';

      document.getElementById('rfqSmall').textContent = `#${rfqNumber}`;
      document.getElementById('rfqMeta').innerHTML = `<strong>Vendor:</strong> ${vendor} &nbsp;&nbsp; <strong>Date:</strong> ${dateText}`;

      function statusClass(s){ if(!s) return 'secondary'; s = (''+s).toLowerCase(); if(s.indexOf('confirm')===0 || s==='confirmed') return 'success'; if(s.indexOf('sent')===0 || s==='sent') return 'primary'; if(s.indexOf('draft')===0) return 'secondary'; if(s.indexOf('cancel')===0) return 'danger'; return 'secondary'; }
      const sc = statusClass(status);
      const statusLabel = status ? (''+status).replace(/(^|\s)\w/g, c=>c.toUpperCase()) : '';
      const statusHtml = `<button type="button" class="btn btn-sm btn-${sc}" disabled style="pointer-events:none;">${statusLabel}</button>`;
      const statusContainer = document.getElementById('statusBtnContainer'); if(statusContainer) statusContainer.innerHTML = statusHtml;

      // Show Confirm button only if status is draft or sent
      const confirmBtn = document.getElementById('confirmBtn');
      if (confirmBtn && ['draft','sent'].includes((''+status).toLowerCase())) {
        confirmBtn.style.display = 'inline-block';
        confirmBtn.addEventListener('click', async function() {
          if (!confirm('Confirm this RFQ?\nThis will create a Purchase Order from it.')) return;
          try {
            confirmBtn.disabled = true;
            confirmBtn.textContent = 'Confirming...';
            const resp = await fetchJson('<?= site_url('new-purchase-rfqs/') ?>'+id+'/confirm', {
              method: 'POST',
              headers: {'Content-Type': 'application/json', 'Accept': 'application/json'}
            });
            if (resp && resp.success) {
              if (resp.po_id) {
                alert(resp.message || 'RFQ confirmed! Redirecting to Purchase Order...');
                window.location.href = '<?= site_url('purchases/po/') ?>' + resp.po_id;
              } else {
                alert(resp.message || 'RFQ confirmed successfully!');
                location.reload();
              }
            } else {
              throw new Error(resp.error || 'Failed to confirm RFQ');
            }
          } catch (e) {
            alert('Error: ' + (e.message || 'Failed to confirm RFQ'));
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Confirm';
          }
        });
      }
      // Show Edit button for draft/sent RFQs
      const editRfqBtn = document.getElementById('editRfqBtn');
      if (editRfqBtn && ['draft','sent'].includes((''+status).toLowerCase())) {
        editRfqBtn.style.display = 'inline-block';
        editRfqBtn.addEventListener('click', function() {
          window.location.href = '<?= site_url("newpurchaseui/rfqpo") ?>?edit=' + id;
        });
      }

      // If status is confirmed, show a "View PO" button instead
      if ((''+status).toLowerCase() === 'confirmed') {
        const btnGroup = confirmBtn ? confirmBtn.parentElement : null;
        if (btnGroup) {
          // Try to find linked PO from backend; look for po_id in response data
          const linkedPoId = data.po_id || r.po_id || null;
          if (linkedPoId) {
            const viewPoBtn = document.createElement('a');
            viewPoBtn.href = '<?= site_url('purchases/po/') ?>' + linkedPoId;
            viewPoBtn.className = 'btn btn-sm btn-primary';
            viewPoBtn.innerHTML = '<i class="bi bi-file-earmark-text me-1"></i>View PO';
            btnGroup.insertBefore(viewPoBtn, btnGroup.firstChild);
          }
        }
      }

      let html = `<div class="table-responsive"><table class="table table-sm align-middle so-lines-table" style="font-size:0.9rem;"><thead><tr style="white-space:nowrap;">
        <th style="width:8%">Code</th>
        <th style="width:5%">Image</th>
        <th style="width:28%">Product / Description</th>
        <th style="width:6%">Unit</th>
        <th style="width:6%" class="text-end">Qty</th>
        <th style="width:9%" class="text-end">Unit Price</th>
        <th style="width:9%" class="text-end">Line Total</th>
      </tr></thead><tbody>`;

      let computedSubtotal = 0, computedTotalTax = 0, computedDiscount = 0, computedGrand = 0;
      (lines || []).forEach(ln=>{
        const qty = parseNumber(ln.qty);
        const unit_price = parseNumber(ln.unit_price);
        const discPct = parseNumber(ln.discount_percent);
        const taxPct = parseNumber(ln.tax_percent);
        const lineBase = qty * unit_price;
        const discountAmount = (typeof ln.discount_amount !== 'undefined' && ln.discount_amount !== null) ? parseNumber(ln.discount_amount) : ((discPct/100) * lineBase);
        const taxable = Math.max(0, lineBase - discountAmount);
        const taxAmount = (typeof ln.tax_amount !== 'undefined' && ln.tax_amount !== null) ? parseNumber(ln.tax_amount) : ((taxPct/100) * taxable);
        const lineTotal = Math.max(0, lineBase - discountAmount + taxAmount);

        computedSubtotal += lineBase;
        computedDiscount += discountAmount;
        computedTotalTax += taxAmount;
        computedGrand += lineTotal;

        const img = ln.product_image_url || '<?= base_url('assets/images/no-image.png') ?>';
        const code = ln.product_code || ln.product_id || '';
        const lineName = ln.product_name || '';
        const lineDesc = ln.description || '';
        let lineText = lineName || lineDesc || (code ? code : '—');

        html += `<tr>`;
        html += `<td>${code}</td>`;
        html += `<td><img src="${img}" alt="" style="width:46px;height:36px;object-fit:cover;border-radius:4px" onerror="this.onerror=null;this.src='<?= base_url('assets/images/no-image.png') ?>'"></td>`;
        html += `<td><div class="fw-semibold" style="line-height:1.2;">${lineText}</div>`;
        if (lineDesc && lineDesc !== lineText) {
          html += `<div class="text-muted" style="font-size:0.8rem; line-height:1.2;">${lineDesc}</div>`;
        }
        html += `</td>`;
        html += `<td>${ln.unit || 'pcs'}</td>`;
        html += `<td class="text-end">${fmt(qty)}</td>`;
        html += `<td class="text-end">${fmt(unit_price)}</td>`;
        html += `<td class="text-end">${fmt(lineTotal)}</td>`;
        html += `</tr>`;
      });
      html += `</tbody></table></div>`;

      const subtotal = (lines.length>0) ? computedSubtotal : ((typeof r.subtotal !== 'undefined')? r.subtotal : 0);
      const totalTax = (lines.length>0) ? computedTotalTax : ((typeof r.total_tax !== 'undefined')? r.total_tax : 0);
      const grand = (lines.length>0) ? computedGrand : ((typeof r.grand_total !== 'undefined')? r.grand_total : (r.total_amount || r.total || 0));

      html += `<div class="row mt-3">
        <div class="col-6"></div>
        <div class="col-6">
          <div class="table-responsive">
            <table class="table table-sm table-borderless">
              <tbody>
                <tr>
                  <td class="text-muted">Subtotal</td>
                  <td class="text-end">${fmt(subtotal)}</td>
                </tr>
                <tr>
                  <td class="text-muted">Total Discount</td>
                  <td class="text-end text-danger">- ${fmt(computedDiscount)}</td>
                </tr>
                <tr>
                  <td class="text-muted">Total Tax</td>
                  <td class="text-end">${fmt(totalTax)}</td>
                </tr>
                <tr class="table-active">
                  <td class="fw-bold">Grand Total</td>
                  <td class="text-end fw-bold">${fmt(grand)}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>`;

      html += `<div class="mt-4"><label class="form-label"><strong>Notes</strong></label><textarea class="form-control form-control-sm" rows="3" readonly>${(r.notes||'')}</textarea></div>`;
      container.innerHTML = html;

      document.getElementById('printBtn').addEventListener('click', function(){ window.print(); });
    } catch (e) {
      showError(e.message||'Failed to load RFQ');
    }
  })();
})();
</script>

<!-- ── Activity Log Panel ───────────────────────────────────────────── -->
<div class="document-log-panel mt-5" id="documentActivityLog"
     style="border-top:1px solid var(--bs-border-color,#dee2e6);padding-top:1.25rem;padding-bottom:2rem;">
  <div class="d-flex align-items-center gap-2 mb-3">
    <i class="bi bi-clock-history text-muted fs-5"></i>
    <h6 class="mb-0 fw-semibold text-muted text-uppercase" style="letter-spacing:.05em;font-size:.8rem;">Activity Log</h6>
    <span id="rfqLogCount" class="badge bg-secondary rounded-pill ms-1 fw-normal" style="font-size:.7rem;">…</span>
    <span class="ms-auto small text-muted fst-italic" style="font-size:.72rem;">All changes are recorded permanently and cannot be edited or deleted.</span>
  </div>
  <div id="rfqLogTimeline"><span class="text-muted small">Loading activity log…</span></div>
</div>
<script>
(function () {
  var docId = '<?= esc($rfqIdentifier ?? '') ?>';
  if (!docId) return;
  var logEl   = document.getElementById('rfqLogTimeline');
  var countEl = document.getElementById('rfqLogCount');
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
  fetch('<?= site_url('activity-log/purchase_rfq/') ?>' + docId, {headers:{'X-Requested-With':'XMLHttpRequest'}})
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

<?= $this->endSection() ?>
