<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Production Follow-Up<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row mb-3">
        <div class="col-8">
            <h3>Production Follow-Up</h3>
            <p class="text-muted">Interactive dashboard and kiosk screens for factory floor follow-up.</p>
        </div>
        <div class="col-4 text-end">
            <label for="refreshSelect">Refresh:</label>
            <select id="refreshSelect" class="form-control d-inline-block" style="width:120px">
                <option value="10">10s</option>
                <option value="30" selected>30s</option>
                <option value="60">60s</option>
                <option value="0">Manual</option>
            </select>
            <button id="refreshNow" class="btn btn-primary ms-2">Refresh Now</button>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-md-3">
            <div class="kpi text-center panel">
                <h5>Active WOs</h5>
                <div id="kpi-active" class="display-4">—</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi text-center panel">
                <h5>Overdue WOs</h5>
                <div id="kpi-overdue" class="display-4 text-danger">—</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi text-center panel">
                <h5>Pending Market Batches</h5>
                <div id="kpi-market" class="display-4">—</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi text-center panel">
                <h5>Throughput (4h)</h5>
                <div id="kpi-throughput" class="display-4">—</div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="panel" id="panel-work-orders">
                <h5>Pending Work Orders</h5>
                <div id="wo-list">Loading…</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel" id="panel-batches">
                <h5>Pending Batches (Market / In-house)</h5>
                <div id="batch-list">Loading…</div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-6">
            <div class="panel" id="panel-odoo-sales">
                <h5>Odoo — Sales Orders</h5>
                <div id="odoo-sales">Loading…</div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="panel" id="panel-odoo-purchases">
                <h5>Odoo — Purchase Orders</h5>
                <div id="odoo-purchases">Loading…</div>
            </div>
        </div>
    </div>

    <hr>
    <p class="text-muted small">Kiosk screens available at:
        <a href="<?= base_url('/production/follow-up/screen/1') ?>"><?= esc('/production/follow-up/screen/1') ?></a>
        &middot;
        <a href="<?= base_url('/production/follow-up/screen/2') ?>"><?= esc('/production/follow-up/screen/2') ?></a>
    </p>

</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function(){
    const apiSummary = '<?= base_url('/production/follow-up/api/summary') ?>';
    const apiPanels = '<?= base_url('/production/follow-up/api/panels') ?>';
    let timer = null;

    function renderSummary(d){
        document.getElementById('kpi-active').textContent = d.active_wos;
        document.getElementById('kpi-overdue').textContent = d.overdue_wos;
        document.getElementById('kpi-market').textContent = d.pending_market_batches;
        document.getElementById('kpi-throughput').textContent = d.throughput_last_4h;
    }

    function renderPanels(d){
        // Work Orders
        const woEl = document.getElementById('wo-list');
        if(!d.work_orders || d.work_orders.length===0){ woEl.innerHTML = '<div class="text-muted">No pending work orders</div>'; }
        else{
            let html = '<ul class="list-group">';
            d.work_orders.slice(0,50).forEach(w => {
                html += `<li class="list-group-item d-flex justify-content-between align-items-center"><div><strong>${w.wo_number}</strong> — ${w.product_name||''} <small class="text-muted">due ${w.due_date||'—'}</small></div><span class="badge bg-primary rounded-pill">${w.planned_qty||0}</span></li>`;
            });
            html += '</ul>';
            woEl.innerHTML = html;
        }

        // Batches
        const bEl = document.getElementById('batch-list');
        const parts = [];
        if(d.market_batches && d.market_batches.length) parts.push('<h6>Market</h6><ul class="list-group mb-2">' + d.market_batches.slice(0,30).map(b => `<li class="list-group-item">${b.batch_number||('Batch '+b.id)} <span class="badge bg-secondary ms-2">${b.planned_qty||0}</span></li>`).join('') + '</ul>');
        if(d.inhouse_batches && d.inhouse_batches.length) parts.push('<h6>In-house</h6><ul class="list-group mb-2">' + d.inhouse_batches.slice(0,30).map(b => `<li class="list-group-item">${b.batch_number||('Batch '+b.id)} <span class="badge bg-secondary ms-2">${b.planned_qty||0}</span></li>`).join('') + '</ul>');
        if(parts.length===0) bEl.innerHTML = '<div class="text-muted">No pending batches</div>'; else bEl.innerHTML = parts.join('');
    }

    function fetchAndRender(){
        fetch(apiSummary).then(r=>r.json()).then(renderSummary).catch(e=>console.error(e));
        fetch(apiPanels).then(r=>r.json()).then(renderPanels).catch(e=>console.error(e));
        // Odoo widgets
        fetch('<?= base_url('/integrations/odoo/api/sales') ?>').then(r=>r.json()).then(d=>{
            const el = document.getElementById('odoo-sales');
            if(!d || !d.data) return el.innerHTML = '<div class="text-muted">No data</div>';
            if(d.data.error) return el.innerHTML = '<div class="text-danger">'+(d.data.error||'Error')+'</div>';
            const rows = d.data;
            if(!rows || rows.length===0) return el.innerHTML = '<div class="text-muted">No recent sales</div>';
            el.innerHTML = '<ul class="list-group">' + rows.slice(0,10).map(r=>'<li class="list-group-item d-flex justify-content-between align-items-center"><div><strong>'+ (r.name||r[1]||r.id) +'</strong> <small class="text-muted">'+(r.date_order||'')+'</small></div><span class="badge bg-primary">'+(r.amount_total||'')+'</span></li>').join('') + '</ul>';
        }).catch(e=>console.error(e));

        fetch('<?= base_url('/integrations/odoo/api/purchases') ?>').then(r=>r.json()).then(d=>{
            const el = document.getElementById('odoo-purchases');
            if(!d || !d.data) return el.innerHTML = '<div class="text-muted">No data</div>';
            if(d.data.error) return el.innerHTML = '<div class="text-danger">'+(d.data.error||'Error')+'</div>';
            const rows = d.data;
            if(!rows || rows.length===0) return el.innerHTML = '<div class="text-muted">No recent purchases</div>';
            el.innerHTML = '<ul class="list-group">' + rows.slice(0,10).map(r=>'<li class="list-group-item d-flex justify-content-between align-items-center"><div><strong>'+ (r.name||r[1]||r.id) +'</strong> <small class="text-muted">'+(r.date_order||'')+'</small></div><span class="badge bg-secondary">'+(r.amount_total||'')+'</span></li>').join('') + '</ul>';
        }).catch(e=>console.error(e));
    }

    document.getElementById('refreshNow').addEventListener('click', ()=> fetchAndRender());
    document.getElementById('refreshSelect').addEventListener('change', ()=>{
        const v = parseInt(document.getElementById('refreshSelect').value,10);
        if(timer) clearInterval(timer);
        if(v>0) timer = setInterval(fetchAndRender, v*1000);
    });

    // start
    fetchAndRender();
    timer = setInterval(fetchAndRender, 30000);
})();
</script>
<?= $this->endSection() ?>
