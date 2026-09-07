<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Odoo Follow-Up<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h3>Odoo Follow‑Up</h3>
    <div>
      <label class="me-2">Sort:</label>
      <select id="sortSelect" class="form-select d-inline-block me-3" style="width:140px">
        <option value="newest" selected>Newest first</option>
        <option value="oldest">Oldest first</option>
      </select>
      <label class="me-2">Refresh</label>
      <select id="refreshSelect" class="form-select d-inline-block" style="width:120px">
        <option value="10">10s</option>
        <option value="30" selected>30s</option>
        <option value="60">60s</option>
        <option value="0">Manual</option>
      </select>
      <button id="refreshNow" class="btn btn-primary ms-2">Refresh Now</button>
      <button id="pullNow" class="btn btn-outline-secondary ms-2">Pull Fresh Data</button>
    </div>
  </div>


  <div class="mb-3 d-flex gap-2" id="tabButtons">
    <button id="btnPending" class="btn btn-outline-warning flex-fill fw-bold">Pending to Ship <span id="countPending" class="badge bg-warning text-dark ms-1"></span></button>
    <button id="btnReady" class="btn btn-outline-success flex-fill fw-bold">Ready to Ship <span id="countReady" class="badge bg-success text-light ms-1"></span></button>
    <button id="btnPOs" class="btn btn-outline-info flex-fill fw-bold">Items to Rec From Market (POs) <span id="countPOs" class="badge bg-info text-dark ms-1"></span></button>
  </div>
  
  <div id="tabContent" class="mt-3"></div>
  <div class="modal fade" id="itemsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Order Items</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="itemsModalBody">Loading…</div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
      </div>
    </div>
  </div>

  <script>
  (function(){
    const apiPending = '<?= base_url('/integrations/odoo/screen/api/pending') ?>';
    const apiReady = '<?= base_url('/integrations/odoo/screen/api/ready') ?>';
    const apiPurch = '<?= base_url('/integrations/odoo/screen/api/purchases') ?>';
    let timer = null;
    let poOffset = 0; let poLimit = 20;
    let salesOffset = 0; let salesLimit = 20;
    let currentSort = 'newest';
    let pendingRows = [];
    let readyRows = [];
    let poRows = [];

    function formatDate(dstr){
      if(!dstr) return '—';
      if (typeof dstr === 'string' && dstr.trim().toLowerCase() === 'not selected') return '—';
      // Accept YYYY-MM-DD or YYYY-MM-DD hh:mm:ss
      const dt = new Date(dstr);
      if(isNaN(dt.getTime())) return '—';
      const dd = String(dt.getDate()).padStart(2,'0');
      const mm = String(dt.getMonth()+1).padStart(2,'0');
      const yy = String(dt.getFullYear()).slice(-2);
      return `${dd}-${mm}-${yy}`;
    }

    // Update button counts
    function updateButtonCounts() {
      document.getElementById('countPending').textContent = pendingRows.length;
      document.getElementById('countReady').textContent = readyRows.length;
      document.getElementById('countPOs').textContent = poRows.length;
    }

    function renderTable(rows, type) {
      let headings = [];
      if (type === 'po') {
        headings = ['Sr No.', 'PO No.', 'Vendor', 'Order Date', 'Expected Receipt', 'Outstanding', 'View Products'];
      } else {
        headings = ['Sr No.', 'Order No.', 'Customer Code', 'Order Date', 'Delivery Date', 'Responsible Person', 'View Products'];
      }
      let html = '<table class="table table-bordered table-striped align-middle"><thead><tr>';
      headings.forEach(h=> html += `<th>${h}</th>`);
      html += '</tr></thead><tbody>';
      if (!rows || !rows.length) {
        html += `<tr><td colspan="${headings.length}" class="text-center text-muted">None</td></tr>`;
      } else {
        rows.forEach((r, i) => {
          if (type === 'po') {
            const vendorName = (r.vendor && (r.vendor.name || r.vendor.code)) ? (r.vendor.name || r.vendor.code) : '';
            html += `<tr>
              <td>${i+1}</td>
              <td>${r.name || ''}</td>
              <td>${vendorName}</td>
              <td>${formatDate(r.order_date)}</td>
              <td>${formatDate(r.expected_date)}</td>
              <td>${r.outstanding_qty ?? '—'}</td>
              <td><button class="btn btn-sm btn-outline-info btn-items" data-id="${r.odoo_id}">View Products</button></td>
            </tr>`;
          } else {
            html += `<tr>
              <td>${i+1}</td>
              <td>${r.name || ''}</td>
              <td>${r.customer_code || ''}</td>
              <td>${formatDate(r.order_date)}</td>
              <td>${formatDate(r.delivery_date)}</td>
              <td>${r.assigned_user ? (r.assigned_user.full_name || r.assigned_user.name) : '—'}</td>
              <td><button class="btn btn-sm btn-outline-info btn-items" data-id="${r.odoo_id}">View Products</button></td>
            </tr>`;
          }
        });
      }
      html += '</tbody></table>';
      document.getElementById('tabContent').innerHTML = html;
      document.querySelectorAll('.btn-items').forEach(b=> b.addEventListener('click', function(){ const id=this.getAttribute('data-id'); fetchItems(id); }));
    }

    function fetchAll(){
      const q = '?limit=20&sort='+encodeURIComponent(currentSort);
      fetch(apiPending + q).then(r=>r.json()).then(d=>{ if(d.ok) { pendingRows = d.data||[]; updateButtonCounts(); if(currentTab==='pending') renderTable(pendingRows, 'pending'); } }).catch(()=>{});
      fetch(apiReady + q).then(r=>r.json()).then(d=>{ if(d.ok) { readyRows = d.data||[]; updateButtonCounts(); if(currentTab==='ready') renderTable(readyRows, 'ready'); } }).catch(()=>{});
      fetch(apiPurch + q).then(r=>r.json()).then(d=>{ if(d.ok) { poRows = d.data||[]; updateButtonCounts(); if(currentTab==='po') renderTable(poRows, 'po'); } }).catch(()=>{});
    }

    function fetchItems(odooId){
      const q = '?limit=200&sort='+encodeURIComponent(currentSort);
      // first try to locate the record quickly from cached endpoints (pending/ready)
      fetch(apiPending + q).then(r=>r.json()).then(pd=>{
        let rec = (pd.data||[]).find(x=>x.odoo_id==odooId);
        if (!rec) {
          // fallback to ready endpoint
          fetch(apiReady + q).then(r2=>r2.json()).then(rd=>{
            rec = (rd.data||[]).find(x=>x.odoo_id==odooId);
            // regardless of whether rec.products exists, fetch raw lines to show full items
            fetchRawLinesAndShow(odooId, rec);
          }).catch(()=> { fetchRawLinesAndShow(odooId, null); });
        } else {
          fetchRawLinesAndShow(odooId, rec);
        }
      }).catch(()=> { fetchRawLinesAndShow(odooId, null); });
    }

    function fetchRawLinesAndShow(odooId, rec){
      const dbg = '<?= base_url('/integrations/odoo/screen/debug/sale_lines') ?>?odoo_id=' + encodeURIComponent(odooId);
      fetch(dbg).then(r=>r.json()).then(d=>{
        if (d && d.ok && Array.isArray(d.data)) {
          const products = d.data.map(l=>{
            let pname = l.product_name || null;
            try {
              if ((!pname || pname === 'null') && l.metadata) {
                const m = JSON.parse(l.metadata);
                if (m) {
                  if (!pname && m.product_id && Array.isArray(m.product_id)) pname = m.product_id[1] || pname;
                  if (!pname && m.display_name) pname = m.display_name;
                  if (!pname && m.name) pname = m.name;
                }
              }
            } catch(e){}
            const code = (()=>{
              try {
                if (l.metadata) {
                  const m = JSON.parse(l.metadata);
                  if (m && m.default_code) return m.default_code;
                  if (m && m.product_id && Array.isArray(m.product_id)) return m.product_id[0] || l.product_id;
                }
              } catch(e){}
              return l.product_id || '';
            })();
            const qty = (l.product_uom_qty !== undefined) ? l.product_uom_qty : (l.qty || 0);
            return { product_id: l.product_id, code: code, name: pname, qty_needed: qty, raw: l };
          });
          rec = rec || { odoo_id: odooId, name: '' };
          rec.products = products;
          showProductModal(rec);
        } else {
          showProductModal(rec);
        }
      }).catch(()=> showProductModal(rec));
    }

    // Pull fresh data (background) - triggers server-side refresh spawn
    document.getElementById('pullNow').addEventListener('click', function(){
      const btn = this; btn.disabled = true; const old = btn.textContent; btn.textContent = 'Starting...';
      fetch('<?= base_url('/integrations/odoo/screen/action/refresh') ?>',{method:'POST'}).then(r=>r.json()).then(d=>{
        if(d && d.ok) {
          btn.textContent = 'Refresh started';
          setTimeout(()=>{ btn.textContent = old; btn.disabled = false; fetchAll(); }, 1500);
        } else {
          btn.textContent = 'Failed'; setTimeout(()=>{ btn.textContent = old; btn.disabled = false; },2000);
        }
      }).catch(()=>{ btn.textContent = 'Error'; setTimeout(()=>{ btn.textContent = old; btn.disabled = false; },2000); });
    });

    document.getElementById('refreshNow').addEventListener('click', ()=> fetchAll());
    document.getElementById('refreshSelect').addEventListener('change', ()=>{
      const v = parseInt(document.getElementById('refreshSelect').value,10);
      if(timer) clearInterval(timer);
      if(v>0) timer = setInterval(fetchAll, v*1000);
    });

    document.getElementById('sortSelect').addEventListener('change', function(){ currentSort = this.value; fetchAll(); });

  // Tab logic
  let currentTab = 'pending';
  document.getElementById('btnPending').addEventListener('click', function(){ currentTab='pending'; renderTable(pendingRows, 'pending'); });
  document.getElementById('btnReady').addEventListener('click', function(){ currentTab='ready'; renderTable(readyRows, 'ready'); });
  document.getElementById('btnPOs').addEventListener('click', function(){ currentTab='po'; renderTable(poRows, 'po'); });
  // Initial load: Pending to Ship as default
  fetchAll();
  timer = setInterval(fetchAll, 30000);
  })();
  </script>

</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function(){
  const apiSummary = '<?= base_url('/integrations/odoo/screen/api/summary') ?>';
  const apiPending = '<?= base_url('/integrations/odoo/screen/api/pending') ?>';
  const apiReady = '<?= base_url('/integrations/odoo/screen/api/ready') ?>';
  const apiPurch = '<?= base_url('/integrations/odoo/screen/api/purchases') ?>';
  const apiSales = '<?= base_url('/integrations/odoo/screen/api/sales') ?>';
  const apiAlerts = '<?= base_url('/integrations/odoo/screen/api/alerts') ?>';
  let timer = null;
  let poOffset = 0; let poLimit = 10;
  let salesOffset = 0; let salesLimit = 10;

  function renderKPIs(d){
    const html = `
      <div class="col-md-3"><div class="card text-center border-warning" style="min-height:100px;padding:10px"><div class="h6">Pending to Ship</div><div class="display-6 text-warning">${d.pending_to_ship_count}</div><div class="small text-muted">Qty ${d.pending_to_ship_qty}</div></div></div>
      <div class="col-md-3"><div class="card text-center border-success" style="min-height:100px;padding:10px"><div class="h6">Ready to Ship</div><div class="display-6 text-success">${d.ready_to_ship_count}</div><div class="small text-muted">Qty ${d.ready_to_ship_qty}</div></div></div>
      <div class="col-md-3"><div class="card text-center border-info" style="min-height:100px;padding:10px"><div class="h6">Open POs</div><div class="display-6 text-info">${d.open_pos_count}</div></div></div>
      <div class="col-md-3"><div class="card text-center border-danger" style="min-height:100px;padding:10px"><div class="h6">Alerts</div><div class="display-6 text-danger">${d.alerts_count}</div></div></div>
    `;
    document.getElementById('kpiRow').innerHTML = html;
  }

  function renderPending(rows){
    const el = document.getElementById('pendingList');
    document.getElementById('pendingCount').textContent = rows.length ? rows.length + ' orders' : '';
    if(!rows || !rows.length) return el.innerHTML = '<div class="text-muted">None</div>';
    let html = '<div class="list-group">';
    rows.forEach(r=>{
      html += `<div class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <strong>${r.name}</strong> - <span class="text-primary">${r.customer_code}</span>
          <div class="small text-muted">Order: ${formatDate(r.order_date)} • Delivery: ${formatDate(r.delivery_date || r.due_date || r.ship_by)}</div>
          <div class="small text-muted">Responsible: ${r.assigned_user ? (r.assigned_user.full_name || r.assigned_user.name) : '—'}</div>
        </div>
        <div>
          <button class="btn btn-sm btn-outline-info btn-items" data-id="${r.odoo_id}">View Products</button>
        </div>
      </div>`;
    });
    html += '</div>';
    el.innerHTML = html;
    document.querySelectorAll('.btn-items').forEach(b=> b.addEventListener('click', function(){ const id=this.getAttribute('data-id'); fetchItems(id); }));
  }

  function renderReady(rows){
    const el = document.getElementById('readyList');
    if(!rows || !rows.length) return el.innerHTML = '<div class="text-muted">None</div>';
    let html = '<div class="list-group">';
    rows.forEach(r=>{
      html += `<div class="list-group-item d-flex justify-content-between align-items-center">
        <div>
          <strong>${r.name}</strong> - <span class="text-primary">${r.customer_code}</span>
          <div class="small text-muted">Order: ${formatDate(r.order_date)} • Delivery: ${formatDate(r.ship_by || r.delivery_date)}</div>
          <div class="small text-muted">Responsible: ${r.assigned_user ? (r.assigned_user.full_name || r.assigned_user.name) : '—'}</div>
        </div>
        <div>
          <button class="btn btn-sm btn-outline-info btn-items" data-id="${r.odoo_id}">View Products</button>
        </div>
      </div>`;
    });
    html += '</div>';
    el.innerHTML = html;
    document.querySelectorAll('.btn-items').forEach(b=> b.addEventListener('click', function(){ const id=this.getAttribute('data-id'); fetchItems(id); }));
  }

  function renderPOs(rows, append=false){
    const el = document.getElementById('poList');
    if(!rows || !rows.length) return el.innerHTML = '<div class="text-muted">None</div>';
    let html = '<div class="list-group">';
    rows.forEach(r=>{
      const vName = r.vendor ? (r.vendor.name || r.vendor.code || '') : '';
      html += `<div class="list-group-item"><div><strong>${r.name}</strong></div><div class="small text-muted">Vendor: ${vName} • Order: ${formatDate(r.order_date)} • ETA: ${formatDate(r.expected_date)} • Out: ${r.outstanding_qty}</div></div>`;
    });
    html += '</div>';
    if (append && el.querySelector('.list-group')) {
      // append items
      const tmp = document.createElement('div'); tmp.innerHTML = html;
      const existing = el.querySelector('.list-group');
      existing.insertAdjacentHTML('beforeend', tmp.querySelector('.list-group').innerHTML);
    } else {
      el.innerHTML = html;
    }
  }

  function renderSales(rows, append=false){
    const el = document.getElementById('salesList');
    if(!rows || !rows.length) return el.innerHTML = '<div class="text-muted">None</div>';
    let html = '<div class="list-group">';
    rows.forEach(r=>{
      html += `<div class="list-group-item"><div><strong>${r.name}</strong></div><div class="small text-muted">Cust: ${r.customer_code? (r.customer_code) : '—'} • Date: ${r.date_order||''}</div></div>`;
    });
    html += '</div>';
    if (append && el.querySelector('.list-group')) {
      const tmp = document.createElement('div'); tmp.innerHTML = html;
      const existing = el.querySelector('.list-group');
      existing.insertAdjacentHTML('beforeend', tmp.querySelector('.list-group').innerHTML);
    } else {
      el.innerHTML = html;
    }
  }

  function renderAlerts(rows){
    const el = document.getElementById('alertsList');
    if(!rows || !rows.length) return el.innerHTML = '<div class="text-muted">No alerts</div>';
    el.innerHTML = rows.map(a=>`<div class="alert alert-danger mb-1">${a.message}</div>`).join('');
  }

  function fetchPOs(append=false){
    fetch(apiPurch + '?limit='+poLimit+'&offset='+poOffset).then(r=>r.json()).then(d=>{ if(d.ok) { renderPOs(d.data||[], append); if(d.data && d.data.length) poOffset += d.data.length; } }).catch(()=>{});
  }

  function fetchSales(append=false){
    fetch(apiSales + '?limit='+salesLimit+'&offset='+salesOffset).then(r=>r.json()).then(d=>{ if(d.ok) { renderSales(d.data||[], append); if(d.data && d.data.length) salesOffset += d.data.length; } }).catch(()=>{});
  }

  function fetchAll(){
    fetch(apiSummary).then(r=>r.json()).then(d=>{ if(d.ok) renderKPIs(d); }).catch(()=>{});
    fetch(apiPending).then(r=>r.json()).then(d=>{ if(d.ok) renderPending(d.data||[]); }).catch(()=>{});
    fetch(apiReady).then(r=>r.json()).then(d=>{ if(d.ok) renderReady(d.data||[]); }).catch(()=>{});
    fetchPOs(false);
    fetchSales(false);
    fetch(apiAlerts).then(r=>r.json()).then(d=>{ if(d.ok) renderAlerts(d.data||[]); }).catch(()=>{});
  }

  function fetchItems(odooId){
    // Open modal: prefer cached endpoints but always fetch raw lines to show full items
    const q = '?limit=200&sort='+encodeURIComponent('newest');
    fetch('<?= base_url('/integrations/odoo/screen/api/pending') ?>'+q).then(r=>r.json()).then(pd=>{
      let rec = (pd.data||[]).find(x=>x.odoo_id==odooId);
      const doShow = (foundRec)=>{
        const dbg = '<?= base_url('/integrations/odoo/screen/debug/sale_lines') ?>?odoo_id=' + encodeURIComponent(odooId);
        fetch(dbg).then(r=>r.json()).then(d=>{
          if (d && d.ok && Array.isArray(d.data)) {
            const products = d.data.map(l=>{
              let pname = l.product_name || null;
              try { if ((!pname || pname === 'null') && l.metadata) { const m = JSON.parse(l.metadata); if (m) { if (!pname && m.product_id && Array.isArray(m.product_id)) pname = m.product_id[1] || pname; if (!pname && m.display_name) pname = m.display_name; if (!pname && m.name) pname = m.name; } } } catch(e){}
              const code = (()=>{ try { if (l.metadata) { const m = JSON.parse(l.metadata); if (m && m.default_code) return m.default_code; if (m && m.product_id && Array.isArray(m.product_id)) return m.product_id[0] || l.product_id; } } catch(e){} return l.product_id || ''; })();
              const qty = (l.product_uom_qty !== undefined) ? l.product_uom_qty : (l.qty || 0);
              return { product_id: l.product_id, code: code, name: pname, qty_needed: qty, raw: l };
            });
            foundRec = foundRec || { odoo_id: odooId, name: '' };
            foundRec.products = products;
            showProductModal(foundRec);
          } else {
            showProductModal(foundRec);
          }
        }).catch(()=> showProductModal(foundRec));
      };
      if (!rec) {
        fetch('<?= base_url('/integrations/odoo/screen/api/ready') ?>'+q).then(r2=>r2.json()).then(rd=>{
          rec = (rd.data||[]).find(x=>x.odoo_id==odooId);
          doShow(rec);
        }).catch(()=> doShow(null));
      } else {
        doShow(rec);
      }
    }).catch(()=>{
      // if cached endpoints fail, still try debug endpoint
      const dbg = '<?= base_url('/integrations/odoo/screen/debug/sale_lines') ?>?odoo_id=' + encodeURIComponent(odooId);
      fetch(dbg).then(r=>r.json()).then(d=>{
        if (d && d.ok && Array.isArray(d.data)) {
          const products = d.data.map(l=>({ product_id: l.product_id, code: (l.metadata?JSON.parse(l.metadata).default_code||l.product_id:l.product_id), name: l.product_name, qty_needed: l.product_uom_qty }));
          showProductModal({odoo_id: odooId, products});
        } else showProductModal(null);
      }).catch(()=> showProductModal(null));
    });
  }

  window.showProductModal = function(rec){
    let html = '';
    if(rec && rec.products && rec.products.length) {
      html = `<table class="table table-bordered"><thead><tr><th>Product Code</th><th>Name</th><th>Quantity</th></tr></thead><tbody>` +
        rec.products.map(p=>`<tr><td>${p.product_id}</td><td>${p.name}</td><td>${p.qty_needed}</td></tr>`).join('') +
        '</tbody></table>';
    } else {
      html = '<div class="text-muted">No items found</div>';
    }
    document.getElementById('itemsModalBody').innerHTML = html;
    // ensure Bootstrap modal helper exists
      if (window.bootstrap && typeof window.bootstrap.Modal === 'function') {
        var modal = new bootstrap.Modal(document.getElementById('itemsModal'));
        modal.show();
      } else {
        const modalEl = document.getElementById('itemsModal');
        modalEl.classList.add('show');
        modalEl.style.display = 'block';
        const backdrop = document.createElement('div'); backdrop.className = 'modal-backdrop fade show'; document.body.appendChild(backdrop);
        document.body.classList.add('modal-open');
      }
      window.showProductModal = function(rec){
        // Render a clear tabular layout: Image | Barcode | Name | Delivered | Quantity | Copy
        const apiStockBase = '<?= base_url('/api/v1/inventory') ?>';
        function escapeHtml(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }
        function formatQty(q){ const n = parseFloat(q); if(isNaN(n)) return String(q||''); if(Math.abs(n - Math.round(n)) < 1e-9) return String(Math.round(n)); return n.toFixed(2); }
        function extractVariantLabel(m){ if(!m) return null; if(m.product_id && Array.isArray(m.product_id) && m.product_id[1]){ const txt=String(m.product_id[1]); const br=txt.match(/\[([^\]]+)\]/); if(br && br[1]) return br[1]; return txt.trim(); } if(m.default_code) return String(m.default_code); if(m.barcode) return String(m.barcode); if(m.ean13) return String(m.ean13); return null; }
        function renderImage(m){ if(!m) return '<div style="width:56px;height:56px;background:#f1f1f1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#888">No</div>'; const keys=['image_128','image_small','image_medium','image','image_url','image_src','image_512']; for(const k of keys){ if(m[k]){ const v=m[k]; if(typeof v==='string' && v.length>50 && /^[A-Za-z0-9+/=\r\n]+$/.test(v.replace(/\r|\n/g,''))){ return `<img src="data:image/png;base64,${v.replace(/\r|\n/g,'')}" style="width:56px;height:56px;object-fit:cover;border-radius:6px;"/>`; } if(typeof v==='string' && (v.startsWith('http')||v.startsWith('/'))){ return `<img src="${escapeHtml(v)}" style="width:56px;height:56px;object-fit:cover;border-radius:6px;"/>`; } } } return '<div style="width:56px;height:56px;background:#f1f1f1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#888">No</div>'; }

        let html = '';
        if (rec && Array.isArray(rec.products) && rec.products.length) {
          html = `<div class="table-responsive"><table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-dark"><tr><th style="width:72px">Image</th><th>Barcode</th><th>Name</th><th style="width:90px" class="text-end">Qty</th><th style="width:160px">Stock (Odoo)</th></tr></thead><tbody>`;

          html += rec.products.map(p=>{
            let m = null; try { if (p.raw && p.raw.metadata) m = (typeof p.raw.metadata==='string')?JSON.parse(p.raw.metadata):p.raw.metadata; } catch(e){ m = null; }
            const barcode = extractVariantLabel(m) || (m && (m.default_code||m.barcode||m.ean13)) || p.code || p.product_id || '';
            const name = p.name || '';
            const qty = (p.qty_needed!==undefined)?p.qty_needed:(p.qty||'');
            const delivered = (p.raw && (p.raw.qty_delivered!==undefined))?p.raw.qty_delivered:0;
            // image placeholder — will be replaced by async fetch below. Prefer sale_line id if available.
            const saleLineId = (p.raw && p.raw.odoo_id) ? p.raw.odoo_id : '';
            return `<tr data-product-id="${escapeHtml(String(p.product_id||''))}" data-sale-line-id="${escapeHtml(String(saleLineId))}">
              <td class="align-middle"><div class="product-thumb" style="width:56px;height:56px;background:#f1f1f1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#888">No</div></td>
              <td class="align-middle"><div>${escapeHtml(String(barcode))}</div><div class="small text-muted">ID: ${escapeHtml(String(p.product_id||''))}</div></td>
              <td class="align-middle"><div class="fw-semibold">${escapeHtml(name)}</div><div class="small text-muted stock-info" data-product-id="${escapeHtml(String(p.product_id||''))}">Stock: …</div></td>
              <td class="align-middle">${escapeHtml(formatQty(delivered))}</td>
              <td class="align-middle text-end"><strong>${escapeHtml(formatQty(qty))}</strong></td>
            </tr>`;
          }).join('');

          html += `</tbody></table></div>`;
        } else {
          html = '<div class="text-center text-muted py-4">No items found</div>';
        }
        document.getElementById('itemsModalBody').innerHTML = html;

        // populate stock info using inventory API; fallback to N/A
        document.querySelectorAll('#itemsModalBody .stock-info').forEach(el=>{
          const pid = el.getAttribute('data-product-id'); if(!pid) return; el.textContent='Loading stock…';
          fetch(`${apiStockBase}/${encodeURIComponent(pid)}/stock`).then(r=>r.json()).then(d=>{
            if(d && d.ok && d.data){ const on_hand = d.data.on_hand ?? d.data.onHand ?? d.data.quantity_on_hand ?? null; const reserved = d.data.reserved ?? d.data.reserved_qty ?? 0; const available = d.data.available ?? (on_hand !== null ? (on_hand - (reserved||0)) : null); if(available===null||available===undefined) el.innerHTML='<span class="text-muted">Stock: N/A</span>'; else el.innerHTML=`Available: <strong class="${available>0?'text-success':'text-danger'}">${escapeHtml(formatQty(available))}</strong> • Reserved: ${escapeHtml(formatQty(reserved||0))}`; } else el.innerHTML='<span class="text-muted">Stock: N/A</span>'; }).catch(()=>{ el.innerHTML='<span class="text-muted">Stock: N/A</span>'; });
        });

        // Fetch images async via new endpoint and wire magnifier
        document.querySelectorAll('#itemsModalBody tr').forEach(row=>{
          const thumb = row.querySelector('.product-thumb');
          if(!thumb) return;
          const saleLineId = row.getAttribute('data-sale-line-id') || '';
          const pid = row.getAttribute('data-product-id') || '';
          // Build query: prefer sale_line_id then product_id
          let q = '';
          if (saleLineId) q = '?sale_line_id=' + encodeURIComponent(saleLineId);
          else if (pid) q = '?product_id=' + encodeURIComponent(pid);
          if (!q) return;
          fetch('<?= base_url('/integrations/odoo/screen/product_image') ?>' + q).then(r=>r.json()).then(d=>{
            if (d && d.ok) {
              let src = d.url ?? d.data ?? null;
              if (src) {
                const img = document.createElement('img'); img.src = src; img.style.width='56px'; img.style.height='56px'; img.style.objectFit='cover'; img.style.borderRadius='6px'; img.style.cursor='zoom-in';
                // attach click handler for magnifier
                img.addEventListener('click', function(){
                  // create simple lightbox
                  const overlay = document.createElement('div'); overlay.style.position='fixed'; overlay.style.left=0; overlay.style.top=0; overlay.style.right=0; overlay.style.bottom=0; overlay.style.background='rgba(0,0,0,0.6)'; overlay.style.display='flex'; overlay.style.alignItems='center'; overlay.style.justifyContent='center'; overlay.style.zIndex=2000;
                  const big = document.createElement('img'); big.src = src; big.style.maxWidth='90%'; big.style.maxHeight='90%'; big.style.boxShadow='0 6px 24px rgba(0,0,0,0.4)'; big.style.borderRadius='8px'; overlay.appendChild(big);
                  overlay.addEventListener('click', ()=>{ try{ document.body.removeChild(overlay); }catch(e){} }); document.body.appendChild(overlay);
                });
                thumb.innerHTML = ''; thumb.appendChild(img);
                return;
              }
            }
            // fallback: keep placeholder
          }).catch(()=>{});
        });

        // Set modal title to include order name so user never loses context
        try {
          const titleEl = document.querySelector('#itemsModal .modal-title');
          if (titleEl) titleEl.textContent = (rec && rec.name) ? (String(rec.name) + ' - Order Items') : 'Order Items';
        } catch(e){}

        // filter products to only those with outstanding (not fully delivered) quantity
        try {
          const pendingRows = [];
          if (rec && Array.isArray(rec.products)) {
            rec.products.forEach(p=>{
              const raw = p.raw || {};
              const ordered = parseFloat(raw.product_uom_qty ?? raw.product_uom_qty ?? p.qty_needed ?? 0) || 0;
              const delivered = parseFloat(raw.qty_delivered ?? 0) || 0;
              const need = ordered - delivered;
              if (need > 0) {
                // attach computed fields for rendering
                p._delivered = delivered;
                p._need = need;
                pendingRows.push(p);
              }
            });
          }
          // if none pending, show message
          if (!pendingRows.length) {
            document.getElementById('itemsModalBody').innerHTML = '<div class="text-center text-muted py-4">No pending items to show (all lines delivered)</div>';
          } else {
            // re-render modal body using pendingRows by replacing table body rows
            const tbody = document.querySelector('#itemsModalBody table tbody');
            if (tbody) {
              // build rows HTML
              const rowsHtml = pendingRows.map(p=>{
                const pid = p.product_id || '';
                const saleLineId = (p.raw && p.raw.odoo_id) ? p.raw.odoo_id : '';
                const m = (()=>{ try { return (p.raw && p.raw.metadata) ? (typeof p.raw.metadata === 'string' ? JSON.parse(p.raw.metadata) : p.raw.metadata) : null; } catch(e){ return null; } })();
                const barcode = (typeof extractVariantLabel === 'function' ? (extractVariantLabel(m) || (m && (m.default_code||m.barcode||m.ean13)) || p.code || p.product_id) : (p.code||p.product_id));
                const name = p.name || '';
                const delivered = p._delivered || 0;
                const qty = p._need || p.qty_needed || 0;
                return `<tr data-product-id="${escapeHtml(String(pid||''))}" data-sale-line-id="${escapeHtml(String(saleLineId))}">
                    <td class="align-middle"><div class="product-thumb" style="width:56px;height:56px;background:#f1f1f1;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#888">No</div></td>
                    <td class="align-middle"><div>${escapeHtml(String(barcode||''))}</div><div class="small text-muted">ID: ${escapeHtml(String(pid||''))}</div></td>
                    <td class="align-middle"><div class="fw-semibold">${escapeHtml(name)}</div></td>
                    <td class="align-middle text-end"><strong>${escapeHtml(formatQty(qty))}</strong></td>
                    <td class="align-middle text-nowrap text-muted stock-cell" data-product-id="${escapeHtml(String(pid||''))}" data-sale-line-id="${escapeHtml(String(saleLineId))}">Loading…</td>
                  </tr>`;
              }).join('');
              tbody.innerHTML = rowsHtml;
              // re-run image & stock population on the new rows
              // fetch images and Odoo stock for rows
              document.querySelectorAll('#itemsModalBody tr').forEach(row=>{
                const thumb = row.querySelector('.product-thumb');
                if(!thumb) return;
                const saleLineId = row.getAttribute('data-sale-line-id') || '';
                const pid = row.getAttribute('data-product-id') || '';
                // request both image and Odoo stock (include_stock=1)
                let q = '';
                if (saleLineId) q = '?sale_line_id=' + encodeURIComponent(saleLineId) + '&include_stock=1';
                else if (pid) q = '?product_id=' + encodeURIComponent(pid) + '&include_stock=1';
                if (q) {
                  fetch('<?= base_url('/integrations/odoo/screen/product_image') ?>' + q).then(r=>r.json()).then(d=>{
                    if (d && d.ok) {
                      // image
                      const src = d.url ?? d.data ?? null;
                      if (src) {
                        const img = document.createElement('img'); img.src = src; img.style.width='56px'; img.style.height='56px'; img.style.objectFit='cover'; img.style.borderRadius='6px'; img.style.cursor='zoom-in';
                        img.addEventListener('click', function(){ const overlay = document.createElement('div'); overlay.style.position='fixed'; overlay.style.left=0; overlay.style.top=0; overlay.style.right=0; overlay.style.bottom=0; overlay.style.background='rgba(0,0,0,0.6)'; overlay.style.display='flex'; overlay.style.alignItems='center'; overlay.style.justifyContent='center'; overlay.style.zIndex=2000; const big = document.createElement('img'); big.src = src; big.style.maxWidth='90%'; big.style.maxHeight='90%'; big.style.boxShadow='0 6px 24px rgba(0,0,0,0.4)'; big.style.borderRadius='8px'; overlay.appendChild(big); overlay.addEventListener('click', ()=>{ try{ document.body.removeChild(overlay); }catch(e){} }); document.body.appendChild(overlay); });
                        thumb.innerHTML = ''; thumb.appendChild(img);
                      }
                      // odoo stock
                      const stockCell = row.querySelector('.stock-cell');
                      if (stockCell) {
                        if (d.odoo_stock) {
                          const s = d.odoo_stock;
                          const on_hand = (s.on_hand === null || s.on_hand === undefined) ? 'N/A' : formatQty(s.on_hand);
                          const reserved = (s.reserved === null || s.reserved === undefined) ? '0' : formatQty(s.reserved);
                          stockCell.innerHTML = `On-hand: <strong class="${(s.on_hand>0)?'text-success':'text-danger'}">${escapeHtml(String(on_hand))}</strong><div class="small text-muted">Reserved: ${escapeHtml(String(reserved))}</div>`;
                        } else {
                          // fallback to local inventory API for stock info
                          const pidLocal = row.getAttribute('data-product-id');
                          fetch(`${apiStockBase}/${encodeURIComponent(pidLocal)}/stock`).then(r2=>r2.json()).then(d2=>{
                            if (d2 && d2.ok && d2.data) {
                              const on_hand = d2.data.on_hand ?? d2.data.onHand ?? d2.data.quantity_on_hand ?? null;
                              const reserved = d2.data.reserved ?? d2.data.reserved_qty ?? 0;
                              if (on_hand===null || on_hand===undefined) stockCell.innerHTML='<span class="text-muted">Stock: N/A</span>'; else stockCell.innerHTML = `On-hand: <strong class="${on_hand>0?'text-success':'text-danger'}">${escapeHtml(formatQty(on_hand))}</strong><div class="small text-muted">Reserved: ${escapeHtml(formatQty(reserved||0))}</div>`;
                            } else stockCell.innerHTML='<span class="text-muted">Stock: N/A</span>';
                          }).catch(()=>{ stockCell.innerHTML='<span class="text-muted">Stock: N/A</span>'; });
                        }
                      }
                    }
                    }).catch(()=>{}).finally(()=>{
                      // if product_image returned non-ok or failed to provide stock, fallback to local inventory
                      // (we handle stock cell below when d.odoo_stock missing)
                    });
                }
                // If product_image didn't return ok (no image/stock), call local inventory API to populate stock cell
                const stockCellFallback = row.querySelector('.stock-cell');
                if (stockCellFallback) {
                  // If the product_image fetch set stock already, skip; otherwise call local API
                  // We'll call local API regardless when image endpoint didn't return od oo_stock
                  // But to detect that we need to call product_image first; since we did, we now ensure fallback when stock-cell still contains 'Loading…'
                  setTimeout(()=>{
                    if (stockCellFallback && stockCellFallback.textContent.trim().startsWith('Loading')) {
                      const pidLocal = row.getAttribute('data-product-id');
                      fetch(`${apiStockBase}/${encodeURIComponent(pidLocal)}/stock`).then(r2=>r2.json()).then(d2=>{
                        if (d2 && d2.ok && d2.data) {
                          const on_hand = d2.data.on_hand ?? d2.data.onHand ?? d2.data.quantity_on_hand ?? null;
                          const reserved = d2.data.reserved ?? d2.data.reserved_qty ?? 0;
                          if (on_hand===null || on_hand===undefined) stockCellFallback.innerHTML='<span class="text-muted">Stock: N/A</span>'; else stockCellFallback.innerHTML = `On-hand: <strong class="${on_hand>0?'text-success':'text-danger'}">${escapeHtml(formatQty(on_hand))}</strong><div class="small text-muted">Reserved: ${escapeHtml(formatQty(reserved||0))}</div>`;
                        } else stockCellFallback.innerHTML='<span class="text-muted">Stock: N/A</span>';
                      }).catch(()=>{ stockCellFallback.innerHTML='<span class="text-muted">Stock: N/A</span>'; });
                    }
                  }, 300);
                }
              });
            }
          }
        } catch(e) { console.warn('Modal filter/render error', e); }

        // show modal
        if (window.bootstrap && typeof window.bootstrap.Modal === 'function') { var modal = new bootstrap.Modal(document.getElementById('itemsModal')); modal.show(); }
        else { const modalEl = document.getElementById('itemsModal'); modalEl.classList.add('show'); modalEl.style.display='block'; const backdrop = document.createElement('div'); backdrop.className='modal-backdrop fade show'; document.body.appendChild(backdrop); document.body.classList.add('modal-open'); }
      }
  }

  function claimOrder(odooId){
    fetch('<?= base_url('/integrations/odoo/screen/action/claim') ?>',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({odoo_id:odooId})}).then(()=>{ fetchAll(); });
  }

  document.getElementById('refreshNow').addEventListener('click', ()=> fetchAll());
  document.getElementById('refreshSelect').addEventListener('change', ()=>{
    const v = parseInt(document.getElementById('refreshSelect').value,10);
    if(timer) clearInterval(timer);
    if(v>0) timer = setInterval(fetchAll, v*1000);
  });

  // start
  fetchAll();
  document.getElementById('poLoadMore')?.addEventListener('click', function(){ fetchPOs(true); });
  document.getElementById('salesLoadMore')?.addEventListener('click', function(){ fetchSales(true); });
  timer = setInterval(fetchAll, 30000);
})();
</script>
<?= $this->endSection() ?>
