<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$header = $doc['customs_invoice'] ?? [];
$items = $doc['items'] ?? [];
?>
<div class="container-fluid py-3" id="customsWorkspace" data-uuid="<?= esc((string)$doc_uuid) ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Customs Workspace: <?= esc((string)($header['customs_invoice_no'] ?? '-')) ?></h4>
            <div class="small text-muted">Mode: <?= esc((string)($header['mode'] ?? '-')) ?> | Status: <strong id="statusText"><?= esc((string)($header['status'] ?? '-')) ?></strong></div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= site_url('customs-invoices') ?>">Back</a>
            <?php if (!empty($can_edit)): ?><button class="btn btn-sm btn-primary" id="saveDraftBtn">Save Draft</button><?php endif; ?>
            <?php if (!empty($can_edit)): ?><button class="btn btn-sm btn-warning" id="submitApprovalBtn">Submit Approval</button><?php endif; ?>
            <?php if (!empty($can_approve)): ?><button class="btn btn-sm btn-success" id="approveBtn">Approve</button><?php endif; ?>
            <?php if (!empty($can_approve)): ?><button class="btn btn-sm btn-danger" id="rejectBtn">Reject</button><?php endif; ?>
            <?php if (!empty($can_finalize)): ?><button class="btn btn-sm btn-dark" id="finalizeBtn">Finalize</button><?php endif; ?>
            <button class="btn btn-sm btn-info" id="previewPdfBtn">Preview PDF</button>
            <?php if (!empty($can_finalize)): ?><button class="btn btn-sm btn-secondary" id="finalPdfBtn">Final PDF</button><?php endif; ?>
        </div>
    </div>

    <div id="msg"></div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <strong>Declared Items</strong>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-primary" id="addLineBtn">Add Line</button>
                        <button class="btn btn-sm btn-outline-primary" id="duplicateLineBtn">Duplicate Selected</button>
                        <button class="btn btn-sm btn-outline-primary" id="mergeLinesBtn">Merge Selected</button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="itemsTable">
                            <thead>
                            <tr>
                                <th style="width:30px"></th>
                                <th>Description</th>
                                <th style="width:90px" class="text-end">Qty</th>
                                <th style="width:80px">UOM</th>
                                <th style="width:120px" class="text-end">Unit Price</th>
                                <th style="width:120px" class="text-end">Line Total</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($items as $idx => $it): ?>
                                <tr>
                                    <td><input type="checkbox" class="line-check"></td>
                                    <td><input class="form-control form-control-sm line-desc" value="<?= esc((string)($it['custom_description'] ?? '')) ?>"></td>
                                    <td><input class="form-control form-control-sm text-end line-qty" type="number" step="any" value="<?= esc((string)($it['declared_qty'] ?? 0)) ?>"></td>
                                    <td><input class="form-control form-control-sm line-uom" value="<?= esc((string)($it['uom'] ?? '')) ?>"></td>
                                    <td><input class="form-control form-control-sm text-end line-price" type="number" step="any" value="<?= esc((string)($it['declared_unit_price'] ?? 0)) ?>"></td>
                                    <td><input class="form-control form-control-sm text-end line-total" type="number" step="any" value="<?= esc((string)($it['declared_line_total'] ?? 0)) ?>"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><strong>Header</strong></div>
                <div class="card-body">
                    <div class="mb-2">
                        <label class="form-label">Currency</label>
                        <input id="currencyCode" class="form-control form-control-sm" value="<?= esc((string)($header['currency_code'] ?? 'USD')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Shipment ID</label>
                        <input id="shipmentId" class="form-control form-control-sm" value="<?= esc((string)($header['shipment_id'] ?? '')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Tracking No</label>
                        <input id="trackingNo" class="form-control form-control-sm" value="<?= esc((string)($header['tracking_no'] ?? '')) ?>">
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Change Reason</label>
                        <textarea id="changeReason" class="form-control form-control-sm" rows="2">Operator update</textarea>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Quick Value Tools</strong></div>
                <div class="card-body">
                    <div class="input-group input-group-sm mb-2">
                        <span class="input-group-text">%</span>
                        <input id="adjustPercent" type="number" step="any" class="form-control" placeholder="e.g. -15">
                        <button class="btn btn-outline-primary" id="applyPercentBtn">Apply</button>
                    </div>
                    <div class="small text-muted">Applies percentage adjustment to selected lines. If none selected, applies to all lines.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Totals</strong></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>Declared Total</span><strong id="declaredTotalText">0.00</strong></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Generated Files</strong>
            <button class="btn btn-sm btn-outline-secondary" id="refreshFilesBtn">Refresh</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Type</th><th>Name</th><th>Hash</th><th>Time</th></tr></thead>
                    <tbody id="filesTbody"><tr><td colspan="4" class="text-center text-muted">No files yet.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const root = document.getElementById('customsWorkspace');
    if (!root) return;
    const uuid = root.dataset.uuid;
    const msg = document.getElementById('msg');
    const tbody = document.querySelector('#itemsTable tbody');
    const declaredTotalText = document.getElementById('declaredTotalText');

    function flash(type, text){ msg.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-2">' + text + '</div>'; }
    function rows(){ return Array.from(tbody.querySelectorAll('tr')); }
    function checkedRows(){ return rows().filter(r => r.querySelector('.line-check') && r.querySelector('.line-check').checked); }

    function recalc(){
        let total = 0;
        rows().forEach(function(r){
            const q = parseFloat((r.querySelector('.line-qty')||{}).value || 0) || 0;
            const p = parseFloat((r.querySelector('.line-price')||{}).value || 0) || 0;
            const tEl = r.querySelector('.line-total');
            if (tEl && (!tEl.value || tEl.dataset.auto === '1')) {
                tEl.value = (q * p).toFixed(4);
                tEl.dataset.auto = '1';
            }
            const lt = parseFloat((tEl||{}).value || 0) || 0;
            total += lt;
        });
        declaredTotalText.textContent = total.toFixed(2);
        return total;
    }

    function readPayload(){
        const items = rows().map(function(r){
            return {
                line_type: 'MANUAL',
                custom_description: (r.querySelector('.line-desc')||{}).value || '',
                declared_qty: parseFloat((r.querySelector('.line-qty')||{}).value || 0) || 0,
                uom: (r.querySelector('.line-uom')||{}).value || '',
                declared_unit_price: parseFloat((r.querySelector('.line-price')||{}).value || 0) || 0,
                declared_line_total: parseFloat((r.querySelector('.line-total')||{}).value || 0) || 0,
                currency_code: (document.getElementById('currencyCode')||{}).value || 'USD'
            };
        }).filter(function(it){ return (it.custom_description || '').trim() !== ''; });

        return {
            header: {
                currency_code: (document.getElementById('currencyCode')||{}).value || 'USD',
                shipment_id: (document.getElementById('shipmentId')||{}).value || null,
                tracking_no: (document.getElementById('trackingNo')||{}).value || null,
                change_reason: (document.getElementById('changeReason')||{}).value || 'Operator update'
            },
            items: items
        };
    }

    async function post(url, body){
        const headers = {'Content-Type':'application/json','Accept':'application/json','X-Requested-With':'XMLHttpRequest'};
        if (window.csrfHash) headers['X-CSRF-TOKEN'] = window.csrfHash;
        const res = await fetch(url, { method:'POST', headers: headers, body: JSON.stringify(body || {}) });
        const j = await res.json();
        if (!res.ok || !j.success) throw new Error(j.message || 'Request failed');
        return j;
    }

    function addLine(data){
        const tr = document.createElement('tr');
        tr.innerHTML = ''+
            '<td><input type="checkbox" class="line-check"></td>'+
            '<td><input class="form-control form-control-sm line-desc" value="'+ (data?.custom_description || '') +'"></td>'+
            '<td><input class="form-control form-control-sm text-end line-qty" type="number" step="any" value="'+ (data?.declared_qty || 0) +'"></td>'+
            '<td><input class="form-control form-control-sm line-uom" value="'+ (data?.uom || '') +'"></td>'+
            '<td><input class="form-control form-control-sm text-end line-price" type="number" step="any" value="'+ (data?.declared_unit_price || 0) +'"></td>'+
            '<td><input class="form-control form-control-sm text-end line-total" type="number" step="any" value="'+ (data?.declared_line_total || 0) +'"></td>';
        tbody.appendChild(tr);
        recalc();
    }

    document.getElementById('addLineBtn')?.addEventListener('click', function(){ addLine(); });

    document.getElementById('duplicateLineBtn')?.addEventListener('click', function(){
        const selected = checkedRows();
        if (!selected.length) { flash('warning', 'Select line(s) to duplicate.'); return; }
        selected.forEach(function(r){
            addLine({
                custom_description: (r.querySelector('.line-desc')||{}).value || '',
                declared_qty: (r.querySelector('.line-qty')||{}).value || 0,
                uom: (r.querySelector('.line-uom')||{}).value || '',
                declared_unit_price: (r.querySelector('.line-price')||{}).value || 0,
                declared_line_total: (r.querySelector('.line-total')||{}).value || 0,
            });
        });
        flash('success', 'Line(s) duplicated.');
    });

    document.getElementById('mergeLinesBtn')?.addEventListener('click', function(){
        const selected = checkedRows();
        if (selected.length < 2) { flash('warning', 'Select at least 2 lines to merge.'); return; }
        const desc = prompt('Merged Description', 'Grouped Customs Set');
        if (!desc) return;
        let qty = 0, total = 0;
        let uom = '';
        selected.forEach(function(r){
            qty += parseFloat((r.querySelector('.line-qty')||{}).value || 0) || 0;
            total += parseFloat((r.querySelector('.line-total')||{}).value || 0) || 0;
            if (!uom) uom = (r.querySelector('.line-uom')||{}).value || '';
            r.remove();
        });
        addLine({ custom_description: desc, declared_qty: qty, uom: uom, declared_unit_price: qty ? (total/qty) : total, declared_line_total: total });
        flash('success', 'Selected lines merged.');
    });

    document.getElementById('applyPercentBtn')?.addEventListener('click', function(){
        const pct = parseFloat((document.getElementById('adjustPercent')||{}).value || 0) || 0;
        const targetRows = checkedRows().length ? checkedRows() : rows();
        if (!targetRows.length) return;
        targetRows.forEach(function(r){
            const pEl = r.querySelector('.line-price');
            const old = parseFloat((pEl||{}).value || 0) || 0;
            const next = old + ((pct / 100) * old);
            if (pEl) pEl.value = next.toFixed(4);
            const t = r.querySelector('.line-total');
            if (t) t.dataset.auto = '1';
        });
        recalc();
        flash('success', 'Percentage adjustment applied.');
    });

    tbody.addEventListener('input', function(){ recalc(); });

    document.getElementById('saveDraftBtn')?.addEventListener('click', async function(){
        try {
            recalc();
            const payload = readPayload();
            await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/draft/save', payload);
            flash('success', 'Draft saved.');
        } catch (e) { flash('danger', e.message || 'Save failed'); }
    });

    document.getElementById('submitApprovalBtn')?.addEventListener('click', async function(){
        try {
            const res = await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/submit-approval', { approval_channel: 'PORTAL' });
            document.getElementById('statusText').textContent = 'PENDING_APPROVAL';
            const token = res?.data?.approval_token || '';
            if (token) {
                const link = '<?= site_url('customs-approval') ?>/' + encodeURIComponent(token);
                flash('success', 'Submitted for approval. Portal link: <a href="' + link + '" target="_blank">' + link + '</a>');
            } else {
                flash('success', 'Submitted for approval.');
            }
        } catch (e) { flash('danger', e.message || 'Submit failed'); }
    });

    document.getElementById('approveBtn')?.addEventListener('click', async function(){
        try {
            await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/approve', { decision_comment: 'Approved' });
            document.getElementById('statusText').textContent = 'APPROVED';
            flash('success', 'Approved.');
        } catch (e) { flash('danger', e.message || 'Approve failed'); }
    });

    document.getElementById('rejectBtn')?.addEventListener('click', async function(){
        try {
            const reason = prompt('Rejection reason', 'Please revise declarations');
            await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/reject', { decision_comment: reason || 'Rejected' });
            document.getElementById('statusText').textContent = 'REJECTED';
            flash('warning', 'Rejected.');
        } catch (e) { flash('danger', e.message || 'Reject failed'); }
    });

    document.getElementById('finalizeBtn')?.addEventListener('click', async function(){
        try {
            await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/finalize', {});
            document.getElementById('statusText').textContent = 'FINALIZED';
            flash('success', 'Finalized.');
        } catch (e) { flash('danger', e.message || 'Finalize failed'); }
    });

    async function refreshFiles(){
        const tbodyFiles = document.getElementById('filesTbody');
        if (!tbodyFiles) return;
        try {
            const res = await fetch('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/files', { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const j = await res.json();
            if (!res.ok || !j.success) throw new Error(j.message || 'Failed to load files');
            const rows = Array.isArray(j.data) ? j.data : [];
            if (!rows.length) {
                tbodyFiles.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No files yet.</td></tr>';
                return;
            }
            tbodyFiles.innerHTML = rows.map(function(f){
                const dl = '<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/files/' + encodeURIComponent(f.id) + '/download';
                return '<tr>'+
                    '<td>'+ (f.file_type || '-') +'</td>'+
                    '<td><a href="'+ dl +'" target="_blank">'+ (f.file_name || '-') +'</a></td>'+
                    '<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis">'+ (f.sha256_hash || '-') +'</td>'+
                    '<td>'+ (f.created_at || '-') +'</td>'+
                '</tr>';
            }).join('');
        } catch(e) {
            tbodyFiles.innerHTML = '<tr><td colspan="4" class="text-center text-danger">' + (e.message || 'Failed') + '</td></tr>';
        }
    }

    document.getElementById('refreshFilesBtn')?.addEventListener('click', refreshFiles);

    document.getElementById('previewPdfBtn')?.addEventListener('click', async function(){
        try {
            const r = await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/pdf/preview', {});
            flash('success', 'Preview PDF generated: ' + (r.data?.file_name || 'OK'));
            refreshFiles();
        } catch (e) { flash('danger', e.message || 'Preview PDF failed'); }
    });

    document.getElementById('finalPdfBtn')?.addEventListener('click', async function(){
        try {
            const r = await post('<?= site_url('customs-invoices') ?>/' + encodeURIComponent(uuid) + '/pdf/final', {});
            flash('success', 'Final PDF generated: ' + (r.data?.file_name || 'OK'));
            refreshFiles();
        } catch (e) { flash('danger', e.message || 'Final PDF failed'); }
    });

    recalc();
    refreshFiles();
})();
</script>
<?= $this->endSection() ?>
