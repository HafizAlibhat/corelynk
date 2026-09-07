<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Production Logs<?= $this->endSection() ?>
<?= $this->section('content') ?>

<style>
    .tree { font-family: Arial, sans-serif; }
    .node { margin-left: 1rem; border-left: 1px dotted #ccc; padding-left: .5rem; }
    .toggle { cursor: pointer; user-select: none; }
    .toggle .chev { display: inline-block; transition: transform .15s ease; width: 1rem; }
    .children { overflow: hidden; transition: height .22s ease, opacity .22s ease; height: 0; opacity: 0; }
        .expanded > .children { height: auto; opacity: 1; }
        .collapsed > .label .chev { transform: rotate(0deg); }
        .badge-pill { border-radius: 50rem; padding: .25rem .5rem; }
        /* lighter badges */
        .badge-acc { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
        .badge-rej { background:#f8d7da; color:#842029; border:1px solid #f5c2c7; }
        .badge-rew { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
        .badge-pend { background:#e2e3e5; color:#41464b; border:1px solid #d3d6d8; }
        .badge-stat { background:#cff4fc; color:#055160; border:1px solid #b6effb; }
    .badge-acc { background:#d1e7dd; color:#0f5132; border:1px solid #badbcc; }
    .badge-rej { background:#f8d7da; color:#842029; border:1px solid #f5c2c7; }
    .badge-rew { background:#fff3cd; color:#664d03; border:1px solid #ffecb5; }
    .badge-pend { background:#e2e3e5; color:#41464b; border:1px solid #d3d6d8; }
    .badge-stat { background:#cff4fc; color:#055160; border:1px solid #b6effb; }
    .small-muted { font-size: .85rem; color: #6c757d; }
    .flex-gap { gap: .5rem; }
    .log-row { font-size: .9rem; background: #f9fafb; border: 1px solid #edf1f5; border-radius: .375rem; padding: .35rem .6rem; margin-bottom: .35rem; }
    /* formula bar */
    .formula { border-top: 1px dashed #e5e7eb; padding-top: .5rem; margin-top: .25rem; }
    .formula-label { font-size: .8rem; color: #6c757d; }
    .formula-value { font-size: 1rem; }
    .formula-sep { font-size: 1.1rem; color: #6c757d; line-height: 1; margin-bottom: .2rem; }
    /* compact batch header and progress bars */
    .batch-header { padding: 6px 10px; }
    .vbar { width: 6px; height: 18px; background:#e9ecef; border-radius: 4px; overflow: hidden; display:inline-block; }
    .vbar-fill { width:100%; height:0; background-color:#0d6efd; background-image: linear-gradient(180deg, rgba(255,255,255,.35) 0, rgba(255,255,255,.35) 40%, transparent 40%); background-size: 100% 8px; animation: progressPulse 1.6s linear infinite; transition: height .35s ease; }
    .vbar-fill.warn { background-color:#fd7e14; }
    .vbar-fill.good { background-color:#20c997; }
    .vbar-fill.done { background-color:#198754; background-image:none; animation:none; }
    .hbar { display:inline-block; width: 120px; height: 6px; background:#eef2f6; border-radius: 4px; overflow:hidden; vertical-align: middle; }
    .hbar-fill { height:100%; width:0; background-color:#0d6efd; background-image: linear-gradient(90deg, rgba(255,255,255,.35) 0, rgba(255,255,255,.35) 40%, transparent 40%); background-size: 40px 100%; animation: progressSlide 1.2s linear infinite; transition: width .45s ease; }
    .hbar-fill.warn { background-color:#fd7e14; }
    .hbar-fill.good { background-color:#20c997; }
    .hbar-fill.done { background-color:#198754; background-image:none; animation:none; }
    @keyframes progressSlide { from { background-position: 0 0; } to { background-position: 40px 0; } }
    @keyframes progressPulse { 0% { opacity: .9; } 50% { opacity: .7; } 100% { opacity: .9; } }
        /* visual separation (modernized) */
        .wo-label { background:#fafbfc; border:1px solid #eceff3; border-radius:8px; padding:6px 8px; margin-bottom:6px; font-weight:600; }
        .product-label { background:#fbfcfe; border:1px solid #eceff3; border-left:3px solid #e1e7ee; border-radius:10px; padding:8px 10px; margin:6px 0; font-weight:600; }
        .process-label { background:#fbfcfe; border:1px solid #eceff3; border-left:3px solid #e8ecf2; border-radius:10px; padding:8px 10px; margin:6px 0; font-style: italic; }
        .batch-header { background:#ffffff; border:1px solid #eceff3; border-left:3px solid #e8ecf2; border-radius:12px; padding:10px 12px; margin:8px 0; box-shadow:0 1px 2px rgba(0,0,0,.03); }
        .logs-wrap { margin-left: .75rem; }
    .log-row small { color: #6c757d; }
    .w-110 { width: 110px; }
    @media (max-width: 768px) { .w-110 { width: auto; } }

    /* Tree table styles */
    .tree-table { width: 100%; border-collapse: collapse; }
    .tree-table th, .tree-table td { padding: .5rem .6rem; border-bottom: 1px solid #eef2f6; vertical-align: middle; }
    .tree-table thead th { position: sticky; top: 0; background: #fff; z-index: 1; border-bottom: 1px solid #e6eaef; }
    .tt-row.hidden { display: none; }
    .tt-toggle { cursor: pointer; user-select: none; color: #0d6efd; text-decoration: none; }
    .tt-caret { display: inline-block; width: 1rem; text-align: center; color: #6c757d; }
    .tt-indent-0 { padding-left: .2rem; position: relative; }
    .tt-indent-1, .tt-indent-2, .tt-indent-3, .tt-indent-4 { position: relative; }
    .tt-indent-1 { padding-left: 2rem; }
    .tt-indent-2 { padding-left: 3rem; }
    .tt-indent-3 { padding-left: 4rem; }
    /* push child (log) rows slightly further for clearer hierarchy and add right spacing */
    .tt-indent-4 { padding-left: 6.5rem; padding-right: 1rem; }
    /* nicer single-line chip for log details */
    .log-line { display:inline-block; background:#f8f9fb; border:1px solid #eef2f6; border-radius:8px; padding:.2rem .5rem; }
    /* tree connectors */
    .tt-indent-1::before, .tt-indent-2::before, .tt-indent-3::before, .tt-indent-4::before {
        content: '';
        position: absolute;
        left: 1.1rem;
        top: -10px;
        bottom: -10px;
        border-left: 1px dotted #d0d7de;
    }
    .tt-indent-1::after, .tt-indent-2::after, .tt-indent-3::after, .tt-indent-4::after {
        content: '';
        position: absolute;
        left: 1.1rem;
        top: 50%;
        width: .8rem;
        border-top: 1px dotted #d0d7de;
    }
    .tt-meta { color: #6c757d; font-size: .85rem; }
    .tt-badge { display:inline-block; background:#f1f3f5; border:1px solid #e6eaef; border-radius:10rem; padding:.1rem .5rem; font-size:.8rem; margin-left:.25rem; }
    .tt-actions .btn { padding: .15rem .45rem; font-size: .8rem; }

    /* row accents by level */
    .tree-table tbody tr[data-level="0"] td:first-child { background: #fafbfc; }
    .tree-table tbody tr[data-level="1"] td:first-child { background: #fbfcfe; }
    .tree-table tbody tr[data-level="2"] td:first-child { background: #ffffff; }
    .tree-table tbody tr:hover td { background: #f8fafc; }

    /* status badges */
    .tt-status { display:inline-flex; align-items:center; gap:.35rem; padding:.15rem .5rem; border-radius: 1rem; font-size: .75rem; border:1px solid transparent; }
    .tt-s-plan { background:#fff3cd; color:#664d03; border-color:#ffecb5; }
    .tt-s-inprog { background:#e7f1ff; color:#0a58ca; border-color:#d0e3ff; }
    .tt-s-done { background:#d1e7dd; color:#0f5132; border-color:#badbcc; }
    .tt-loading { width:.45rem; height:.45rem; border-radius:50%; background:#0d6efd; display:inline-block; animation: ttPulse 1s ease-in-out infinite; }
    @keyframes ttPulse { 0%, 100% { transform: scale(.8); opacity:.6; } 50% { transform: scale(1); opacity:1; } }

    /* Start a Batch form stability */
    #startBatchForm .form-label { margin-bottom: .25rem; }
    #startBatchForm .form-select, #startBatchForm .form-control { min-height: 38px; height: 40px; }
    #startBatchForm .form-text { min-height: 1.25rem; }
    #productHint { min-height: 1rem; white-space: nowrap; }
    #startControls { display: flex; align-items: center; gap: .5rem; flex-wrap: wrap; }
    #startInfo { min-height: 1rem; display: inline-block; }
</style>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-diagram-3 me-2"></i>Start a Batch</h5></div>
    <div class="card-body">
        <form id="startBatchForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label for="workOrder" class="form-label">Work Order</label>
                <select id="workOrder" class="form-select">
                    <option value="">-- select --</option>
                    <?php foreach (($workOrders ?? []) as $wo): ?>
                        <option value="<?= (int)$wo['id'] ?>">#<?= esc($wo['wo_number']) ?> — <?= esc($wo['status'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-4">
                <label for="product" class="form-label">Product</label>
                <select id="product" class="form-select" disabled>
                    <option value="">-- select work order first --</option>
                </select>
                <div id="productHint" class="form-text"></div>
            </div>
            <div class="col-md-3">
                <label for="process" class="form-label">Process</label>
                <select id="process" class="form-select" disabled>
                    <option value="">-- select product first --</option>
                </select>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-2">
                <label for="qty" class="form-label">Planned Qty</label>
                <input id="qty" type="number" min="1" class="form-control" disabled>
                <div class="form-text">&nbsp;</div>
            </div>
            <div class="col-md-12">
                <div id="startControls">
                    <button id="startBtn" type="submit" class="btn btn-primary" disabled>
                        <i class="bi bi-play-circle me-1"></i> Start Batch
                    </button>
                    <span id="startInfo" class="small-muted"></span>
                </div>
            </div>
        </form>
    </div>
    <div id="messages" class="px-3 pb-3"></div>
    <script>
    // Resolve base from current URL so it works under XAMPP subfolders
    const LOGS_BASE = window.location.pathname.replace(/\/$/, '');
    function getCsrf() { return document.querySelector('meta[name="csrf-token"]')?.content || ''; }
    async function api(path, method='GET', body=null) {
        const url = path.startsWith('http') ? path : `${LOGS_BASE}/${path.replace(/^\//,'')}`;
        const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf() } };
        if (body) { opts.body = JSON.stringify(body); opts.headers['Content-Type'] = 'application/json'; }
        try {
            const r = await fetch(url, opts);
            const ct = r.headers.get('content-type') || '';
            if (!ct.includes('application/json')) {
                const text = await r.text();
                return { success: false, message: `Unexpected response (${r.status})`, raw: text };
            }
            return await r.json();
        } catch (e) {
            return { success: false, message: 'Network error: ' + (e?.message || e) };
        }
    }

    const els = {
        workOrder: document.getElementById('workOrder'),
        product: document.getElementById('product'),
        process: document.getElementById('process'),
        qty: document.getElementById('qty'),
        startBtn: document.getElementById('startBtn'),
        productHint: document.getElementById('productHint'),
        startInfo: document.getElementById('startInfo'),
        messages: document.getElementById('messages'),
    };

    function showMessage(type, text) {
        const el = document.createElement('div');
        el.className = 'alert alert-' + type + ' mt-2 mb-0';
        el.textContent = text;
        els.messages.appendChild(el);
        setTimeout(() => el.remove(), 5000);
    }

    async function loadProducts() {
        // reset downstream
    els.product.innerHTML = '<option value="">-- select work order first --</option>';
    els.product.disabled = true; els.productHint.textContent = '';
    els.process.innerHTML = '<option value="">-- select product first --</option>';
    els.process.disabled = true; els.qty.value = ''; els.qty.disabled = true;
    els.startBtn.disabled = true; els.startInfo.textContent = '';

        const wo = els.workOrder.value;
        if (!wo) return;
        els.product.innerHTML = '<option>Loading…</option>';
        const res = await api('getProducts/' + encodeURIComponent(wo));
        if (!res?.success) {
            els.product.innerHTML = '<option value="">Failed to load</option>';
            showMessage('danger', res?.message || 'Failed to load products');
            return;
        }
        if (!res.products?.length) {
            els.product.innerHTML = '<option value="">No products for this WO</option>';
            return;
        }
        els.product.innerHTML = '<option value="">-- select --</option>' + res.products.map(p =>
            `<option value="${p.work_order_item_id}" data-product-id="${p.product_id}" data-ordered="${p.quantity_ordered}">${p.product_name} (ordered: ${p.quantity_ordered})</option>`
        ).join('');
    els.product.disabled = false;
    }

    async function loadProcesses() {
        // reset downstream
    els.process.innerHTML = '<option value="">-- select product first --</option>';
    els.process.disabled = true; els.qty.value = ''; els.qty.disabled = true;
    els.startBtn.disabled = true; els.startInfo.textContent = '';

        const opt = els.product.selectedOptions[0];
        if (!opt || !opt.dataset.productId) return;
        els.productHint.textContent = `Ordered: ${opt.dataset.ordered}`;
        els.process.innerHTML = '<option>Loading…</option>';
        const res = await api('getProcesses?product_id=' + encodeURIComponent(opt.dataset.productId));
        if (!res?.success) {
            els.process.innerHTML = '<option value="">Failed to load</option>';
            showMessage('danger', res?.message || 'Failed to load processes');
            return;
        }
        if (!res.processes?.length) {
            els.process.innerHTML = '<option value="">No processes for product</option>';
            return;
        }
        els.process.innerHTML = '<option value="">-- select --</option>' + res.processes.map(pr =>
            `<option value="${pr.process_id}">${pr.process_name}</option>`
        ).join('');
    els.process.disabled = false;
    }

    function onProcessChange() {
    els.qty.disabled = !els.process.value;
    els.qty.value = '';
    els.startBtn.disabled = true;
    }

    function onQtyInput() {
        const q = parseFloat(els.qty.value || '0');
        els.startBtn.disabled = !(els.workOrder.value && els.product.value && els.process.value && q > 0);
        els.startInfo.textContent = q > 0 ? `Ready to create batch of ${q}` : '';
    }

    async function startBatch() {
        const woItemId = parseInt(els.product.value, 10);
        const processId = parseInt(els.process.value, 10);
        const qty = parseFloat(els.qty.value || '0');
        if (!(woItemId > 0 && processId > 0 && qty > 0)) return;
        els.startBtn.disabled = true;
        els.startBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Starting…';
        const res = await api('createBatch', 'POST', { work_order_item_id: woItemId, process_id: processId, planned_qty: qty });
        els.startBtn.innerHTML = '<i class="bi bi-play-circle me-1"></i> Start Batch';
        els.startBtn.disabled = false;
        showMessage(res.success ? 'success' : 'danger', res.message || (res.success ? 'Done' : 'Failed'));
        if (res.success) {
            els.qty.value = '';
            els.startBtn.disabled = true;
            setStepState(4, 'done');
            loadTree();
        }
    }

    els.workOrder.addEventListener('change', loadProducts);
    els.product.addEventListener('change', loadProcesses);
    els.process.addEventListener('change', onProcessChange);
    els.qty.addEventListener('input', onQtyInput);
    document.getElementById('startBatchForm').addEventListener('submit', function(e){ e.preventDefault(); startBatch(); });

    </script>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white"><h5 class="mb-0"><i class="bi bi-list-task me-2"></i>Active Batches</h5></div>
    <div class="card-body">
        <div id="tree" class="tree"></div>
    </div>
</div>

<script>
function el(tag, className, html) {
    const e = document.createElement(tag);
    if (className) e.className = className;
    if (html !== undefined) e.innerHTML = html;
    return e;
}

async function loadTree() {
    const tree = document.getElementById('tree');
    tree.innerHTML = '<div class="text-muted">Loading…</div>';
    let res;
    try {
        res = await api('hierarchy');
    } catch (e) {
        tree.innerHTML = `<div class="alert alert-danger">Error loading: ${e?.message || e}</div>`;
        return;
    }
    if (!res || res.success === false) { tree.innerHTML = `<div class=\"alert alert-danger\">Failed to load${res?.message ? ': ' + res.message : ''}</div>`; return; }
    if (!Array.isArray(res.hierarchy) || res.hierarchy.length === 0) { tree.innerHTML = '<div class="text-muted">No batches started</div>'; return; }

    // Helpers
    const sums = (batches) => {
        let planned=0, acc=0, rej=0, rew=0, started=0, pend=0;
        (batches||[]).forEach(b => {
            planned += Number(b.planned_qty||0);
            const t=b.totals||{}; const a=Number(t.accepted||0), r=Number(t.rejected||0), w=Number(t.rework||0);
            const s = Number(t.started ?? (a+r+w));
            const p = Number(t.pending ?? Math.max(0, (b.planned_qty||0) - s));
            acc += a; rej += r; rew += w; started += s; pend += p;
        });
        return { planned, acc, rej, rew, started, pend };
    };
    const fmt = (n) => {
        const v = Number(n||0);
        return v > 0 ? v : '';
    };
    const fmtDate = (str) => {
        if (!str) return '';
        // Normalize to a Date; if invalid keep original string
        const d = new Date(str);
        if (isNaN(d.getTime())) return str;
        const dd = String(d.getDate()).padStart(2,'0');
        const mmm = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][d.getMonth()];
        const yy = String(d.getFullYear()).slice(-2);
        return `${dd}-${mmm}-${yy}`; // e.g., 31-Oct-25
    };

    // Build table
    const table = el('table', 'tree-table table table-sm');
    // Remove column headings to reduce clutter; keep only tbody
    table.innerHTML = `<tbody></tbody>`;
    const tbody = table.querySelector('tbody');

    const addRow = (obj) => { tbody.appendChild(obj); return obj; };

    res.hierarchy.forEach(wo => {
        const woId = `wo-${wo.work_order_id}`;
        // Collect all batches under this WO
        const woBatches = (wo.products||[]).flatMap(p => (p.processes||[]).flatMap(pr => pr.batches||[]));
        const wsum = sums(woBatches);
        const trWO = el('tr', 'tt-row');
        trWO.dataset.level = '0'; trWO.dataset.id = woId;
        trWO.innerHTML = `
            <td class="tt-indent-0">
                <span class="tt-caret">▸</span>
                <a href="#" class="tt-toggle" data-target="${woId}">WO ${wo.wo_number}</a>
                <span class="tt-meta ms-2">${woBatches.length} batches</span>
            </td>
            <td class="text-end"></td>
        `;
        addRow(trWO);

        (wo.products||[]).forEach(p => {
            const pid = `prod-${wo.work_order_id}-${p.product_id}`;
            const pBatches = (p.processes||[]).flatMap(pr => pr.batches||[]);
            const psum = sums(pBatches);
            const trP = el('tr', 'tt-row hidden');
            trP.dataset.level = '1'; trP.dataset.id = pid; trP.dataset.parent = woId;
            trP.innerHTML = `
                <td class="tt-indent-1">
                    <span class="tt-caret">▸</span>
                    <a href="#" class="tt-toggle" data-target="${pid}">${p.product_name}</a>
                    ${p.ordered_qty ? `<span class=\"tt-badge\">Target ${p.ordered_qty}</span>` : ''}
                </td>
                <td class="text-end"></td>
            `;
            addRow(trP);

            (p.processes||[]).forEach(pr => {
                const prid = `proc-${wo.work_order_id}-${p.product_id}-${pr.process_id}`;
                const rsum = sums(pr.batches||[]);
                const trR = el('tr', 'tt-row hidden');
                trR.dataset.level = '2'; trR.dataset.id = prid; trR.dataset.parent = pid;
                trR.innerHTML = `
                    <td class="tt-indent-2">
                        <span class="tt-caret">▸</span>
                        <a href="#" class="tt-toggle" data-target="${prid}">${pr.process_name}</a>
                        ${pr.ordered_qty ? `<span class=\"tt-badge\">Target ${pr.ordered_qty}</span>` : ''}
                    </td>
                    <td class="text-end"></td>
                `;
                addRow(trR);

                (pr.batches||[]).forEach(b => {
                    const bid = `batch-${b.batch_id}`;
                    const t = b.totals || { accepted:0, rejected:0, rework:0, started:0, pending: (b.planned_qty||0) };
                    const trB = el('tr', 'tt-row hidden');
                    trB.dataset.level = '3'; trB.dataset.id = bid; trB.dataset.parent = prid;
                    const statusBadge = (pendingVal) => {
                        const p = Number(pendingVal||0);
                        if (p <= 0) return `<span class=\"tt-status tt-s-done\">✓ Completed</span>`;
                        return `<span class=\"tt-status tt-s-inprog\"><span class=\"tt-loading\"></span> In progress</span>`;
                    };
                    const startedVal = (t.started != null ? t.started : ((t.accepted||0)+(t.rejected||0)+(t.rework||0)));
                    const pendingVal = (t.pending != null ? t.pending : Math.max(0, (b.planned_qty||0) - startedVal));
                    trB.innerHTML = `
                        <td class="tt-indent-3">
                            <span class="tt-caret">▸</span>
                            <a href=\"#\" class=\"tt-toggle\" data-target=\"${bid}\">${b.batch_code}</a>
                            <span class=\"ms-2\">${statusBadge(pendingVal)}</span>
                            <span class=\"tt-badge\">Started: ${fmt(startedVal) || 0}</span>
                            <span class=\"tt-badge\" style=\"background:#e9f7ef;border-color:#d1f2e5;color:#0f5132;\">Accepted: ${fmt(t.accepted) || 0}</span>
                            <span class=\"tt-badge\" style=\"background:#fdecea;border-color:#f5c2c7;color:#842029;\">Rejected: ${fmt(t.rejected) || 0}</span>
                            <span class=\"tt-badge\" style=\"background:#fff7e6;border-color:#ffe0a3;color:#8a5a00;\">Rework: ${fmt(t.rework) || 0}</span>
                            <span class=\"tt-badge\">Pending: ${fmt(pendingVal) || 0}</span>
                        </td>
                        <td class="text-end tt-actions">
                            <button class=\"btn btn-outline-primary btn-sm\" data-add-log=\"${b.batch_id}\" data-acc=\"${t.accepted||0}\" data-rej=\"${t.rejected||0}\" data-rew=\"${t.rework||0}\" data-pend=\"${pendingVal}\" data-started=\"${startedVal}\">Add Log</button>
                        </td>
                    `;
                    addRow(trB);

                    const logs = b.logs || [];
                    // If no logs, show a Delete Batch button
                    if (!logs.length) {
                        const actCell = trB.querySelector('.tt-actions');
                        const delBtn = document.createElement('button');
                        delBtn.className = 'btn btn-outline-danger btn-sm ms-1';
                        delBtn.textContent = 'Delete Batch';
                        delBtn.setAttribute('data-del-batch', String(b.batch_id));
                        actCell.appendChild(delBtn);
                    }

                    logs.forEach(lg => {
                        const rcv = lg.qty_received ?? lg.received_qty ?? 0;
                        const acc = lg.qty_completed ?? lg.accepted_qty ?? 0;
                        const rej = lg.qty_rejected ?? lg.rejected_qty ?? 0;
                        const rew = lg.rework_qty ?? lg.qty_rework ?? lg.sent_for_rework ?? lg.reworked_qty ?? 0;
                        const dt = lg.log_date || lg.created_at || '';
                        // Build a single-line summary and format date per request
                        const dtFmt = fmtDate(dt);
                        const lid = `log-${lg.id}`;
                        const trL = el('tr', 'tt-row hidden');
                        trL.dataset.level = '4'; trL.dataset.id = lid; trL.dataset.parent = bid;
                        trL.innerHTML = `
                            <td class="tt-indent-4">
                                <span class="tt-caret">•</span>
                                <span class="tt-meta"><strong>${dtFmt}</strong> - <span class="log-line">Received = ${rcv} -> Accepted = ${acc} -> Rejected = ${rej} -> Rework = ${rew} ( Pending = ${fmt(pendingVal) || 0} )</span></span>
                            </td>
                            <td class="text-end tt-actions">
                                <button class="btn btn-outline-secondary btn-sm me-1" data-edit-log="${lg.id}" data-rcv="${rcv}" data-acc="${acc}" data-rej="${rej}" data-rew="${rew}" data-dt="${dt}" data-notes="">
                                    Edit
                                </button>
                                <button class="btn btn-outline-danger btn-sm" data-del-log="${lg.id}">Delete</button>
                            </td>
                        `;
                        addRow(trL);
                    });
                });
            });
        });

        // After each WO section, insert a light divider row
        const divTr = el('tr');
        const td = el('td', '', '<div style="height:4px;"></div>');
        td.colSpan = 2; divTr.appendChild(td); addRow(divTr);
    });

    // Render the table
    tree.innerHTML = '';
    tree.appendChild(table);

    // Wire toggles
    const getLevel = tr => parseInt(tr.dataset.level||'0',10);
    function collapseFrom(tr) {
        const level = getLevel(tr);
        let n = tr.nextElementSibling;
        while (n && n.dataset && n.classList.contains('tt-row')) {
            const nl = getLevel(n);
            if (nl <= level) break;
            n.classList.add('hidden');
            n.querySelector?.('.tt-caret') && (n.querySelector('.tt-caret').textContent = '▸');
            n = n.nextElementSibling;
        }
        tr.querySelector('.tt-caret').textContent = '▸';
        tr.dataset.expanded = 'false';
    }
    function expandImmediate(tr) {
        const id = tr.dataset.id; const level = getLevel(tr);
        let n = tr.nextElementSibling;
        while (n && n.dataset && n.classList.contains('tt-row')) {
            const nl = getLevel(n);
            if (nl <= level) break;
            if (n.dataset.parent === id) { n.classList.remove('hidden'); }
            n = n.nextElementSibling;
        }
        tr.querySelector('.tt-caret').textContent = '▾';
        tr.dataset.expanded = 'true';
    }
    table.querySelectorAll('.tt-toggle').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const tr = a.closest('tr');
            if (tr.dataset.expanded === 'true') collapseFrom(tr); else expandImmediate(tr);
        });
    });

    // Wire actions
    table.querySelectorAll('[data-add-log]').forEach(btn => {
        btn.addEventListener('click', () => {
            const bid = parseInt(btn.getAttribute('data-add-log'), 10);
            // read totals from data attributes
            const totals = {
                accepted: parseFloat(btn.getAttribute('data-acc'))||0,
                rejected: parseFloat(btn.getAttribute('data-rej'))||0,
                rework: parseFloat(btn.getAttribute('data-rew'))||0,
                pending: parseFloat(btn.getAttribute('data-pend'))||0,
                started: parseFloat(btn.getAttribute('data-started'))||0,
            };
            const tr = btn.closest('tr');
            const code = tr.querySelector('.tt-toggle')?.textContent?.trim() || ('#' + bid);
            openAddLog(bid, code, totals);
        });
    });
    table.querySelectorAll('[data-edit-log]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.getAttribute('data-edit-log'), 10);
            // Extract row data
            const acc = parseFloat(btn.getAttribute('data-acc'))||0;
            const rej = parseFloat(btn.getAttribute('data-rej'))||0;
            const rew = parseFloat(btn.getAttribute('data-rew'))||0;
            const dt = btn.getAttribute('data-dt') || '';
            const notesTxt = btn.getAttribute('data-notes') || '';
            openEditLog(id, { acc, rej, rew, dt, notes: notesTxt });
        });
    });
    table.querySelectorAll('[data-del-log]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = parseInt(btn.getAttribute('data-del-log'), 10);
            deleteLog(id);
        });
    });
    // Delete batch (uses existing production endpoint). Only shown when no logs exist.
    table.querySelectorAll('[data-del-batch]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const bid = parseInt(btn.getAttribute('data-del-batch'), 10);
            if (!confirm('Delete this batch? This is only allowed when no logs exist.')) return;
            let res = { success:false, message:'Not attempted' };
            try {
                const r = await fetch('<?= base_url('production/ajax-delete-batch') ?>', {
                    method: 'POST',
                    headers: { 'X-Requested-With':'XMLHttpRequest', 'X-CSRF-TOKEN': getCsrf(), 'Content-Type': 'application/json' },
                    body: JSON.stringify({ batch_id: bid })
                });
                const ct = r.headers.get('content-type')||'';
                res = ct.includes('application/json') ? await r.json() : { success:false, message: await r.text() };
            } catch(e) {
                res = { success:false, message: (e?.message||e) };
            }
            const messages = document.getElementById('messages');
            const msg = document.createElement('div');
            msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
            msg.textContent = res.message || (res.success ? 'Deleted' : 'Delete failed');
            messages.appendChild(msg);
            setTimeout(() => msg.remove(), 5000);
            if (res.success) loadTree();
        });
    });
}

// Add Log modal wiring
function openAddLog(batchId, batchCode, totals) {
    const t = totals || { accepted: 0, rejected: 0, rework: 0, pending: 0 };
    const html = `
        <div class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Add Log for ${batchCode}</h5></div>
                    <div class="modal-body">
                        <div class="mb-2">
                            <span class="badge badge-stat me-1">Started: ${t.started ?? (t.accepted + t.rejected + t.rework) ?? 0}</span>
                            <span class="badge badge-pend">Pending: ${t.pending ?? 0}</span>
                        </div>
                        <div class="row g-2">
                            <div class="col-6"><label class="form-label">Pending (info)</label><input id="log_pending" type="number" class="form-control" value="${t.pending ?? 0}" disabled></div>
                            <div class="col-6"><label class="form-label">Received (for QC)</label><input id="log_received" type="number" min="0" class="form-control" placeholder="e.g., 100"></div>
                            <div class="col-6"><label class="form-label">Accepted</label><input id="log_accepted" type="number" min="0" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Rejected</label><input id="log_rejected" type="number" min="0" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Rework</label><input id="log_rework" type="number" min="0" class="form-control"></div>
                            <div class="col-6"><label class="form-label">Date</label><input id="log_date" type="date" class="form-control" value="${new Date().toISOString().slice(0,10)}"></div>
                        </div>
                        <div class="mt-2 small-muted">Totals — Started: <strong>${t.started ?? (t.accepted + t.rejected + t.rework) ?? 0}</strong> | Accepted: <strong>${t.accepted ?? 0}</strong> | Rejected: <strong>${t.rejected ?? 0}</strong> | Rework: <strong>${t.rework ?? 0}</strong> | Pending: <strong>${t.pending ?? 0}</strong></div>
                        <div class="mt-2"><label class="form-label">Notes</label><textarea id="log_notes" class="form-control" rows="3"></textarea></div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.querySelector('.modal').remove()">Cancel</button>
                        <button class="btn btn-primary" onclick="submitAddLog(${batchId})">Save</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}

async function submitAddLog(batchId) {
    const received = parseFloat(document.getElementById('log_received').value || 0);
    const accepted = parseFloat(document.getElementById('log_accepted').value || 0);
    const rejected = parseFloat(document.getElementById('log_rejected').value || 0);
    const rework = parseFloat(document.getElementById('log_rework').value || 0);
    const log_date = document.getElementById('log_date').value || undefined;
    const notes = document.getElementById('log_notes').value || '';
    // simple validation to keep data quality
    const sum = accepted + rejected + rework;
    if (received > 0 && sum !== received) {
        const warn = document.createElement('div');
        warn.className = 'alert alert-warning';
        warn.textContent = `Sum mismatch: Accepted(${accepted}) + Rejected(${rejected}) + Rework(${rework}) = ${sum}, but Received is ${received}. Align values or clear Received.`;
        document.querySelector('.modal .modal-body').prepend(warn);
        setTimeout(() => warn.remove(), 5000);
        return;
    }
    const payload = { batch_id: batchId, qty_completed: accepted, qty_rejected: rejected, rework_qty: rework, log_date, notes };
    if (received > 0) payload.qty_received = received;
    const res = await api('addLog', 'POST', payload);
    const messages = document.getElementById('messages');
    const msg = document.createElement('div');
    msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
    msg.textContent = res.message || (res.success ? 'Saved' : 'Failed');
    messages.appendChild(msg);
    setTimeout(() => msg.remove(), 5000);
    document.querySelector('.modal')?.remove();
    loadTree();
}

// initial tree load
document.addEventListener('DOMContentLoaded', () => {
    loadTree();
});

// Edit & Delete handlers
function openEditLog(id, data) {
    const html = `
        <div class="modal" tabindex="-1" style="display:block;background:rgba(0,0,0,.5);">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header"><h5 class="modal-title">Edit Log</h5></div>
                    <div class="modal-body">
                        <div class="row g-2">
                            <div class="col-4"><label class="form-label">Accepted</label><input id="e_accepted" type="number" min="0" class="form-control" value="${data.acc}"></div>
                            <div class="col-4"><label class="form-label">Rejected</label><input id="e_rejected" type="number" min="0" class="form-control" value="${data.rej}"></div>
                            <div class="col-4"><label class="form-label">Rework</label><input id="e_rework" type="number" min="0" class="form-control" value="${data.rew}"></div>
                            <div class="col-6"><label class="form-label">Date</label><input id="e_date" type="date" class="form-control" value="${data.dt}"></div>
                            <div class="col-12"><label class="form-label">Notes</label><textarea id="e_notes" class="form-control" rows="3">${data.notes || ''}</textarea></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" onclick="document.querySelector('.modal').remove()">Cancel</button>
                        <button class="btn btn-primary" onclick="submitEditLog(${id})">Save</button>
                    </div>
                </div>
            </div>
        </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
}

async function submitEditLog(id) {
    const payload = {
        qty_completed: parseFloat(document.getElementById('e_accepted').value || 0),
        qty_rejected: parseFloat(document.getElementById('e_rejected').value || 0),
        rework_qty: parseFloat(document.getElementById('e_rework').value || 0),
        log_date: document.getElementById('e_date').value || undefined,
        notes: document.getElementById('e_notes').value || ''
    };
    const res = await api('updateLog', 'POST', Object.assign({ id }, payload));
    const messages = document.getElementById('messages');
    const msg = document.createElement('div');
    msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
    msg.textContent = res.message || (res.success ? 'Saved' : 'Failed');
    messages.appendChild(msg);
    setTimeout(() => msg.remove(), 5000);
    document.querySelector('.modal')?.remove();
    loadTree();
}

async function deleteLog(id) {
    if (!confirm('Delete this log entry?')) return;
    const res = await api('deleteLog', 'POST', { id });
    const messages = document.getElementById('messages');
    const msg = document.createElement('div');
    msg.className = 'alert ' + (res.success ? 'alert-success' : 'alert-danger');
    msg.textContent = res.message || (res.success ? 'Deleted' : 'Failed');
    messages.appendChild(msg);
    setTimeout(() => msg.remove(), 5000);
    loadTree();
}
</script>

<?= $this->endSection() ?>
