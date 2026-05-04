<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Production Follow-Up — Screen <?= esc($screenId) ?><?= $this->endSection() ?>

<?= $this->section('styles') ?>
<style>
    html,body{height:100%;}
    body{background:#111;color:#fff;font-family:Helvetica,Arial,sans-serif;margin:0;padding:10px}
    .row{display:flex;gap:10px}
    .kpi{flex:1;padding:20px;background:rgba(255,255,255,0.05);border-radius:8px;text-align:center}
    .panel{background:rgba(255,255,255,0.03);padding:10px;border-radius:6px;height:calc(50vh - 40px);overflow:auto}
    .small-muted{color:rgba(255,255,255,0.6);font-size:0.9rem}
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div id="root">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div><h2 style="margin:0">Factory Dashboard — Screen <?= esc($screenId) ?></h2><div class="small-muted">Auto-refreshing kiosk view</div></div>
        <div class="small-muted">Updated: <span id="ts">—</span></div>
    </div>

    <div class="row" style="margin-bottom:10px">
        <div class="kpi"><div class="small-muted">Active WOs</div><div id="k-active" style="font-size:44px">—</div></div>
        <div class="kpi"><div class="small-muted">Overdue</div><div id="k-overdue" style="font-size:44px;color:#ff6666">—</div></div>
        <div class="kpi"><div class="small-muted">Pending Batches</div><div id="k-batches" style="font-size:44px">—</div></div>
        <div class="kpi"><div class="small-muted">Throughput (4h)</div><div id="k-through" style="font-size:44px">—</div></div>
    </div>

    <div class="row">
        <div class="panel" id="p-left">
            <h4>In Progress / Pending</h4>
            <div id="left-list">Loading…</div>
        </div>
        <div class="panel" id="p-right">
            <h4>Recent Completions</h4>
            <div id="right-list">Loading…</div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function(){
    const apiSummary = '<?= base_url('/production/follow-up/api/summary') ?>';
    const apiPanels = '<?= base_url('/production/follow-up/api/panels') ?>';
    const refresh = 30 * 1000; // default 30s

    function updateSummary(d){
        document.getElementById('k-active').textContent = d.active_wos;
        document.getElementById('k-overdue').textContent = d.overdue_wos;
        document.getElementById('k-batches').textContent = (d.pending_market_batches + d.pending_inhouse_batches);
        document.getElementById('k-through').textContent = d.throughput_last_4h;
        document.getElementById('ts').textContent = new Date(d.timestamp).toLocaleTimeString();
    }

    function updatePanels(d){
        const left = document.getElementById('left-list');
        const right = document.getElementById('right-list');

        left.innerHTML = '';
        if(d.inprogress && d.inprogress.length){
            d.inprogress.slice(0,50).forEach(i=>{
                const el = document.createElement('div');
                el.style.padding='6px'; el.style.borderBottom='1px solid rgba(255,255,255,0.03)';
                el.innerHTML = `<strong>Batch ${i.process_batch_id}</strong> — ${i.event} <span style="float:right;opacity:0.8">${i.qty_processed||''}</span>`;
                left.appendChild(el);
            });
        } else if(d.market_batches && d.market_batches.length){
            d.market_batches.slice(0,50).forEach(b=>{
                const el = document.createElement('div');
                el.style.padding='6px'; el.style.borderBottom='1px solid rgba(255,255,255,0.03)';
                el.innerHTML = `<strong>${b.batch_number||('Batch '+b.id)}</strong> — Market <span style="float:right;opacity:0.8">${b.planned_qty||0}</span>`;
                left.appendChild(el);
            });
        } else {
            left.innerHTML = '<div class="small-muted">No activity</div>';
        }

        right.innerHTML = '';
        if(d.completed && d.completed.length){
            d.completed.slice(0,50).forEach(c=>{
                const el = document.createElement('div');
                el.style.padding='6px'; el.style.borderBottom='1px solid rgba(255,255,255,0.03)';
                el.innerHTML = `<strong>${c.batch_number||('Batch '+c.id)}</strong> <span style="float:right;opacity:0.8">${c.completed_at||''}</span>`;
                right.appendChild(el);
            });
        } else {
            right.innerHTML = '<div class="small-muted">No recent completions</div>';
        }
    }

    function refreshAll(){
        fetch(apiSummary).then(r=>r.json()).then(updateSummary).catch(()=>{});
        fetch(apiPanels).then(r=>r.json()).then(updatePanels).catch(()=>{});
    }

    refreshAll();
    setInterval(refreshAll, refresh);
})();
</script>
<?= $this->endSection() ?>