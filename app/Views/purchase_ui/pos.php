<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Purchase Orders
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
  <div class="card-header section-header d-flex justify-content-between align-items-center">
    <div>
      <h3 class="section-title">Purchase Orders</h3>
      <div class="section-sub">Create and confirm purchase orders</div>
    </div>
  </div>
  <div class="card-body">
    <div id="messages"></div>

    <form id="poForm" class="mb-4">
      <div class="row g-2">
        <div class="col-md-3 form-group">
          <label class="form-label">PO Number</label>
          <input name="po_number" class="form-control" />
        </div>
        <div class="col-md-2 form-group">
          <label class="form-label">RFQ ID</label>
          <input name="rfq_id" type="number" class="form-control" />
        </div>
        <div class="col-md-2 form-group">
          <label class="form-label">Vendor ID</label>
          <input name="vendor_id" type="number" class="form-control" />
        </div>
        <div class="col-md-2 form-group">
          <label class="form-label">Subtotal</label>
          <input name="subtotal" type="number" step="any" class="form-control" />
        </div>
        <div class="col-md-2 form-group">
          <label class="form-label">Total</label>
          <input name="total" type="number" step="any" class="form-control" />
        </div>
      </div>

      <h5 class="mt-3">Lines</h5>
      <div id="poLines" class="mb-2"></div>
      <button type="button" id="addPoLine" class="btn btn-sm btn-secondary">Add Line</button>
      <button type="submit" class="btn btn-primary ms-2">Create PO (draft)</button>
    </form>

    <h5>Your POs (server)</h5>
    <div id="poList"><em>Loading...</em></div>
  </div>
</div>

<script src="<?= base_url('assets/js/corelynk_autocomplete.js') ?>"></script>
<script>
(() => {
  const messages = document.getElementById('messages');
  const poList = document.getElementById('poList');
  const poLines = document.getElementById('poLines');

  function renderPos(rows){
    if(!rows || rows.length===0){ poList.innerHTML='<em>No POs found.</em>'; return; }
    let html = '<table><tr><th>ID</th><th>PO#</th><th>Vendor</th><th>Status</th><th>Created</th><th>Actions</th></tr>';
    rows.forEach(p=>{
      html += `<tr data-id="${p.id}"><td>${p.id}</td><td>${p.po_number||''}</td><td>${p.vendor_id||''}</td><td>${p.status}</td><td>${p.created_at}</td><td>`;
      if(p.status === 'draft') html += `<button data-poid="${p.id}" class="confirmBtn">Confirm</button>`;
      html += `</td></tr>`;
    });
    html += '</table>';
    poList.innerHTML = html;
    attachPoHandlers();
  }

  function addPoLineRow(){
    const idx = poLines.children.length;
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2';
    row.innerHTML = `
      <div class="col-md-2 form-group">
        <input name="lines[${idx}][product_code]" class="form-control product-code" placeholder="Code" />
        <input name="lines[${idx}][product_id]" class="product-id" type="hidden" />
      </div>
      <div class="col-md-4 form-group">
        <input name="lines[${idx}][product_name]" class="form-control product-name" placeholder="Product" />
        <input name="lines[${idx}][description]" class="form-control line-desc mt-1" placeholder="Description" />
      </div>
      <div class="col-md-2 form-group"><input name="lines[${idx}][qty]" class="form-control line-qty" placeholder="Qty" type="number" step="any"/></div>
      <div class="col-md-2 form-group"><input name="lines[${idx}][unit_price]" class="form-control line-price" placeholder="Unit Price" type="number" step="any"/></div>
      <div class="col-md-1 form-group"><button type="button" class="btn btn-sm btn-outline-danger removePoLine">Remove</button></div>
    `;
    poLines.appendChild(row);
    row.querySelector('.removePoLine').addEventListener('click', ()=>row.remove());

    // Attach the exact quotation product autocomplete behavior
    try {
        if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
        window.CoreLynkAutocomplete.attachProductAutocomplete(row.querySelector('input.product-code'), false, 'purchase');
        window.CoreLynkAutocomplete.attachProductAutocomplete(row.querySelector('input.product-name'), true, 'purchase');
      }
    } catch (e) {}
  }

  document.getElementById('addPoLine').addEventListener('click', addPoLineRow);
  addPoLineRow();

  document.getElementById('poForm').addEventListener('submit', async (ev)=>{
    ev.preventDefault(); messages.innerHTML='';
    const form = ev.target; const data = {};
    data.po_number = form.po_number.value || null;
    data.rfq_id = form.rfq_id.value ? parseInt(form.rfq_id.value,10):null;
    data.vendor_id = form.vendor_id.value ? parseInt(form.vendor_id.value,10):null;
    data.subtotal = form.subtotal.value ? parseFloat(form.subtotal.value):null;
    data.total = form.total.value ? parseFloat(form.total.value):null;
    data.lines = [];
  const inputs = poLines.querySelectorAll('.row');
  inputs.forEach((d,i)=>{ const pid = d.querySelector(`[name="lines[${i}][product_id]"]`).value; const qty = d.querySelector(`[name="lines[${i}][qty]"]`).value; const up = d.querySelector(`[name="lines[${i}][unit_price]"]`).value; const desc = d.querySelector(`[name="lines[${i}][description]"]`).value; if(!qty||parseFloat(qty)<=0) return; data.lines.push({product_id: pid?parseInt(pid,10):null, qty: parseFloat(qty), unit_price: up?parseFloat(up):null, description: desc||null}); });
    if(data.lines.length===0){ messages.innerHTML='<div class="error">Add at least one PO line with positive quantity.</div>'; return; }
    try{
  const resp = await fetch('<?= site_url("new-purchase-orders/create") ?>', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) });
      if(!resp.ok){ const txt = await resp.text(); throw new Error(txt || 'Server error'); }
      const contentType = resp.headers.get('content-type') || '';
      if(!contentType.includes('application/json')){ const txt = await resp.text(); throw new Error('Non-JSON response: '+txt); }
      const j = await resp.json();
      if(j.success){ messages.innerHTML = '<div class="success">PO created ID: '+j.po_id+'</div>'; await loadPos(); form.reset(); poLines.innerHTML=''; addPoLineRow(); } else { messages.innerHTML = '<div class="error">'+(j.error||JSON.stringify(j))+'</div>'; }
    }catch(err){ messages.innerHTML = '<div class="error">Network error: '+err.message+'</div>'; }
  });

  // Load POs from server
  async function loadPos(){
    poList.innerHTML = 'Loading...';
    try{
      const resp = await fetch('<?= site_url("new-purchase-orders") ?>', { method:'GET', headers:{'Accept':'application/json'} });
      if(!resp.ok){ const txt = await resp.text(); poList.innerHTML = '<div class="error">Failed to load: '+txt+'</div>'; return; }
      const ct = resp.headers.get('content-type') || '';
      if(!ct.includes('application/json')){ const txt = await resp.text(); poList.innerHTML = '<div class="error">Non-JSON response: '+txt+'</div>'; return; }
      const j = await resp.json(); if(!j.success){ poList.innerHTML = '<div class="error">Failed to load</div>'; return; }
      renderPos(j.data);
    }catch(e){ poList.innerHTML = '<div class="error">Network error: '+e.message+'</div>'; }
  }

  function attachPoHandlers(){
    document.querySelectorAll('.confirmBtn').forEach(b=>b.addEventListener('click', async (ev)=>{
      const poId = ev.target.getAttribute('data-poid');
      try{
        const resp = await fetch('<?= site_url("new-purchase-orders/confirm/") ?>'+poId, { method:'POST' });
        if(!resp.ok){ const txt = await resp.text(); throw new Error(txt || 'Server error'); }
        const ct = resp.headers.get('content-type') || '';
        if(!ct.includes('application/json')){ const txt = await resp.text(); throw new Error('Non-JSON response: '+txt); }
        const j = await resp.json();
        if(j.success){ messages.innerHTML = '<div class="success">PO '+poId+' confirmed.</div>'; await loadPos(); } else { messages.innerHTML = '<div class="error">'+(j.error||JSON.stringify(j))+'</div>'; }
      }catch(err){ messages.innerHTML = '<div class="error">Network error: '+err.message+'</div>'; }
    }));
  }

  // initial load
  loadPos();
})();
</script>

<?php $this->endSection() ?>
