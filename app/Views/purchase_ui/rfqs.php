<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Purchase RFQs
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
  <div class="card-header section-header d-flex justify-content-between align-items-center">
    <div>
      <h3 class="section-title">Purchase RFQs</h3>
      <div class="section-sub">Create and manage vendor RFQs</div>
    </div>
  </div>
  <div class="card-body">
    <div id="messages"></div>

    <form id="rfqForm" class="mb-4">
      <div class="row">
        <div class="col-md-3 form-group">
          <label class="form-label">RFQ Number</label>
          <input name="rfq_number" class="form-control" />
        </div>
        <div class="col-md-3 form-group">
          <label class="form-label">Vendor ID</label>
          <input name="vendor_id" type="number" class="form-control" />
        </div>
        <div class="col-md-6 form-group">
          <label class="form-label">Notes</label>
          <textarea name="notes" rows="2" class="form-control"></textarea>
        </div>
      </div>

      <h5 class="mt-3">Lines (add at least one)</h5>
      <div id="linesContainer" class="mb-2"></div>
      <button type="button" id="addLineBtn" class="btn btn-sm btn-secondary">Add Line</button>
      <button type="submit" class="btn btn-primary ms-2">Create RFQ</button>
    </form>

    <h5>Your RFQs (server)</h5>
    <div class="mb-2"><button id="refreshServer" class="btn btn-sm btn-outline-secondary">Refresh Server RFQs</button></div>
    <div id="serverRfqs"><em>Not loaded</em></div>
  </div>
</div>

<script src="<?= base_url('assets/js/corelynk_autocomplete.js') ?>"></script>
<script>
(() => {
  const messages = document.getElementById('messages');
  const rfqList = document.getElementById('rfqList');
  const linesContainer = document.getElementById('linesContainer');

  // Render RFQs from server response
  function renderRfqs(rows){
    if(!rows || rows.length===0){ rfqList.innerHTML='<em>No RFQs found.</em>'; return; }
    let html = '<table><tr><th>ID</th><th>RFQ#</th><th>Vendor</th><th>Status</th><th>Created</th><th>Actions</th></tr>';
    for(const r of rows){
      html += `<tr data-id="${r.id}"><td>${r.id}</td><td>${r.rfq_number||''}</td><td>${r.vendor_id||''}</td><td>${r.status}</td><td>${r.created_at}</td><td>`;
      if(r.status === 'draft') html += `<button class="sendBtn" data-id="${r.id}">Send</button>`;
      if(r.status === 'sent') html += `<button class="acceptBtn" data-id="${r.id}">Accept</button>`;
      if(r.status !== 'cancelled') html += `<button class="cancelBtn" data-id="${r.id}">Cancel</button>`;
      if(r.status === 'accepted') html += ` <button class="createPoBtn" data-id="${r.id}">Create PO</button>`;
      html += `</td></tr>`;
    }
    html += '</table>';
    rfqList.innerHTML = html;
    attachRfqsHandlers();
  }

  function addLineRow(){
    const idx = linesContainer.children.length;
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
      <div class="col-md-1 form-group"><button type="button" class="btn btn-sm btn-outline-danger removeBtn">Remove</button></div>
    `;
    linesContainer.appendChild(row);
    row.querySelector('.removeBtn').addEventListener('click', ()=>{ row.remove(); });

    // Attach the exact quotation product autocomplete behavior
    try {
        if (window.CoreLynkAutocomplete && window.CoreLynkAutocomplete.attachProductAutocomplete) {
        window.CoreLynkAutocomplete.attachProductAutocomplete(row.querySelector('input.product-code'), false, 'purchase');
        window.CoreLynkAutocomplete.attachProductAutocomplete(row.querySelector('input.product-name'), true, 'purchase');
      }
    } catch (e) {}
  }

  document.getElementById('addLineBtn').addEventListener('click', addLineRow);
  addLineRow();

  document.getElementById('rfqForm').addEventListener('submit', async (ev)=>{
    ev.preventDefault(); messages.innerHTML='';
    const form = ev.target;
    const data = {};
    data.rfq_number = form.rfq_number.value || null;
    data.vendor_id = form.vendor_id.value ? parseInt(form.vendor_id.value,10) : null;
    data.notes = form.notes.value || null;
    data.lines = [];
    // gather lines
    const inputs = linesContainer.querySelectorAll('.row');
    inputs.forEach((d)=>{
      const pid = (d.querySelector('.product-id') || {}).value;
      const qty = (d.querySelector('.line-qty') || {}).value;
      const up = (d.querySelector('.line-price') || {}).value;
      const desc = (d.querySelector('.line-desc') || {}).value;
      if(!qty || parseFloat(qty)<=0) return; // skip
      data.lines.push({
        product_id: pid ? parseInt(pid,10):null,
        qty: parseFloat(qty),
        unit_price: up?parseFloat(up):null,
        description: desc||null
      });
    });
    if(data.lines.length===0){ messages.innerHTML='<div class="error">Add at least one line with positive quantity.</div>'; return; }

    try{
  const resp = await fetch('<?= site_url("new-purchase-rfqs/create") ?>', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(data)});
      const json = await resp.json();
      if(resp.ok && json.success){
        messages.innerHTML = '<div class="success">RFQ created. ID: '+json.rfq_id+'</div>';
        // reload server RFQs
        await loadServerRfqs();
        form.reset(); linesContainer.innerHTML=''; addLineRow();
      } else {
        messages.innerHTML = '<div class="error">'+(json.error||JSON.stringify(json))+'</div>';
      }
    }catch(err){ messages.innerHTML = '<div class="error">Network error: '+err.message+'</div>'; }
  });

  // Server-side RFQ list and actions
  document.getElementById('refreshServer').addEventListener('click', async ()=>{ await loadServerRfqs(); });

  async function postAction(path, body) {
    try{
      const resp = await fetch(path, { method:'POST', headers:{'Content-Type':'application/json'}, body: body ? JSON.stringify(body) : null });
      if(!resp.ok){ const txt = await resp.text(); throw new Error(txt || 'Server error'); }
      const contentType = resp.headers.get('content-type') || '';
      if(!contentType.includes('application/json')){ const txt = await resp.text(); throw new Error(txt || 'Non-JSON response'); }
      return await resp.json();
    }catch(e){ alert('Network error: '+e.message); }
  }

  async function loadServerRfqs(){
    const dst = document.getElementById('serverRfqs'); dst.innerHTML='Loading...';
    try{
      const resp = await fetch('<?= site_url("new-purchase-rfqs") ?>', { method:'GET', headers:{'Accept':'application/json'} });
      if(!resp.ok){ const txt = await resp.text(); dst.innerHTML = '<div class="error">Failed to load: '+txt+'</div>'; return; }
      const contentType = resp.headers.get('content-type') || '';
      if(!contentType.includes('application/json')){ const txt = await resp.text(); dst.innerHTML = '<div class="error">Server returned non-JSON: '+txt+'</div>'; return; }
      const j = await resp.json();
      if(!j.success){ dst.innerHTML = '<div class="error">Failed to load</div>'; return; }
      dst.innerHTML = '';
      renderRfqs(j.data);
    }catch(err){ dst.innerHTML = '<div class="error">Network error: '+err.message+'</div>'; }
  }

  function attachRfqsHandlers(){
    const dst = document.getElementById('serverRfqs');
    dst.querySelectorAll('.sendBtn').forEach(b=>b.addEventListener('click', async (ev)=>{ const id = ev.target.dataset.id; await postAction('new-purchase-rfqs/'+id+'/send'); await loadServerRfqs(); }));
    dst.querySelectorAll('.acceptBtn').forEach(b=>b.addEventListener('click', async (ev)=>{ const id = ev.target.dataset.id; await postAction('new-purchase-rfqs/'+id+'/accept'); await loadServerRfqs(); }));
    dst.querySelectorAll('.cancelBtn').forEach(b=>b.addEventListener('click', async (ev)=>{ const id = ev.target.dataset.id; const reason = prompt('Cancel reason (optional)'); await postAction('new-purchase-rfqs/'+id+'/cancel', { reason }); await loadServerRfqs(); }));
    dst.querySelectorAll('.createPoBtn').forEach(b=>b.addEventListener('click', async (ev)=>{ const id = ev.target.dataset.id; try{ const resp2 = await fetch('<?= site_url("new-purchase-orders/from-rfq/") ?>'+id, { method:'POST' }); if(!resp2.ok){ const txt = await resp2.text(); alert('Create PO failed: '+txt); } else { const contentType = resp2.headers.get('content-type') || ''; if(!contentType.includes('application/json')){ const txt = await resp2.text(); alert('Create PO failed (non-json): '+txt); } else { const j2 = await resp2.json(); if(j2.success) alert('PO created: '+j2.po_id); else alert('Create PO failed: '+(j2.error||JSON.stringify(j2))); } } }catch(e){ alert('Network error: '+e.message); } await loadServerRfqs(); }));
  }

  // Load server RFQs on page load
  loadServerRfqs();
})();
</script>

<?php $this->endSection() ?>
