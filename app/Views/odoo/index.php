<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Odoo Integration<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-8">
            <h3>Odoo Integration</h3>
            <p class="text-muted">Live view of Sales Orders and Purchase Orders from configured Odoo server.</p>
        </div>
        <div class="col-4 text-end">
            <button id="refreshBtn" class="btn btn-primary">Refresh</button>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Recent Sales Orders</div>
                <div class="card-body" id="salesList">Loading…</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Recent Purchase Orders</div>
                <div class="card-body" id="purchasesList">Loading…</div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
async function fetchJson(url){
    const r = await fetch(url); if(!r.ok) throw new Error('HTTP '+r.status);
    return r.json();
}

function renderOrders(elId, items){
    const el = document.getElementById(elId);
    if(!items || items.length===0){ el.innerHTML = '<div class="text-muted">No records</div>'; return; }
    let html = '<table class="table table-sm"><thead><tr><th>#</th><th>Partner</th><th>Date</th><th>State</th><th class="text-end">Total</th></tr></thead><tbody>';
    items.forEach(i => {
        const partner = Array.isArray(i.partner_id) ? (i.partner_id[1]||i.partner_id[0]) : (i.partner_id||'');
        html += `<tr><td>${i.name||i.id}</td><td>${partner}</td><td>${i.date_order||''}</td><td>${i.state||''}</td><td class="text-end">${i.amount_total||''}</td></tr>`;
    });
    html += '</tbody></table>';
    el.innerHTML = html;
}

async function loadAll(){
    document.getElementById('salesList').innerHTML = 'Loading…';
    document.getElementById('purchasesList').innerHTML = 'Loading…';
    try{
        const s = await fetchJson('<?= base_url('/integrations/odoo/api/sales') ?>');
        const p = await fetchJson('<?= base_url('/integrations/odoo/api/purchases') ?>');
        renderOrders('salesList', s.data);
        renderOrders('purchasesList', p.data);
    }catch(e){
        document.getElementById('salesList').innerHTML = '<div class="text-danger">Error loading</div>';
        document.getElementById('purchasesList').innerHTML = '<div class="text-danger">Error loading</div>';
        console.error(e);
    }
}

document.getElementById('refreshBtn').addEventListener('click', loadAll);
loadAll();
</script>
<?= $this->endSection() ?>
