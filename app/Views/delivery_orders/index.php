<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Delivery Orders
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $allDOs       = $delivery_orders ?? [];
    $totalDOs     = count($allDOs);
    $draftCount   = 0; $confirmedCount = 0; $deliveredCount = 0;
    foreach ($allDOs as $_d) {
        $s = $_d['status'] ?? 'draft';
        if ($s === 'draft')          $draftCount++;
        elseif ($s === 'confirmed')  $confirmedCount++;
        elseif ($s === 'delivered')  $deliveredCount++;
    }
?>
<style>
    .pl-wrap { padding: .75rem 1rem; }
    .pl-card { border: 1px solid var(--cl-border); border-radius: 6px; overflow: hidden; background: var(--cl-surface); }
    .pl-card-header { display: flex; align-items: center; justify-content: space-between; padding: .45rem .75rem; border-bottom: 1px solid var(--cl-border); gap: .5rem; }
    .pl-card-header-title { font-size: .82rem; font-weight: 700; color: var(--cl-text-primary); line-height: 1; }
    .pl-card-header-sub  { font-size: .66rem; color: var(--cl-text-muted); margin-top: .1rem; }
    .pl-filter-bar { display: flex; align-items: center; flex-wrap: wrap; gap: .3rem; padding: .4rem .75rem; border-bottom: 1px solid var(--cl-border); background: var(--cl-surface); }
    .pl-filter-bar .form-control, .pl-filter-bar .form-select { font-size: .74rem; padding: .18rem .45rem; height: 26px; border-radius: 4px; }
    .pl-search-wrap { position: relative; flex: 1; min-width: 140px; max-width: 240px; }
    .pl-search-wrap .bi-search { position: absolute; left: .45rem; top: 50%; transform: translateY(-50%); font-size: .7rem; color: var(--cl-text-muted); pointer-events: none; }
    .pl-search-wrap .form-control { padding-left: 1.55rem; }
    .pl-filter-bar .form-select { padding-right: 1.6rem; }
    .pl-btn { display: inline-flex; align-items: center; justify-content: center; height: 26px; padding: 0 .55rem; font-size: .72rem; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); cursor: pointer; gap: .28rem; text-decoration: none; white-space: nowrap; transition: all .12s; }
    .pl-btn:hover { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
    .pl-btn.pl-btn-primary { background: var(--cl-primary); border-color: var(--cl-primary); color: #fff; }
    .pl-btn.pl-btn-primary:hover { opacity: .88; color: #fff; }
    .pl-table { margin-bottom: 0; font-size: .76rem; }
    .pl-card .pl-table thead th { font-size: .62rem !important; text-transform: uppercase; letter-spacing: .05em; color: var(--cl-text-muted); background: var(--cl-surface-alt); border-bottom: 1px solid var(--cl-border); padding: .26rem .45rem !important; white-space: nowrap; font-weight: 700 !important; }
    body.theme-dark .pl-card .pl-table thead th { color: #94a3b8 !important; background: #162033 !important; border-bottom-color: #334155 !important; }
    .pl-card .pl-table tbody td { padding: .22rem .45rem !important; border-bottom: 1px solid var(--cl-border-light); vertical-align: middle; font-size: .76rem !important; }
    .pl-table tbody tr:last-child td { border-bottom: none; }
    body.theme-dark .pl-table tbody tr:nth-child(even) td { background: #0f1a2b !important; }
    body.theme-dark .pl-table tbody tr:nth-child(odd)  td { background: #1a2740 !important; }
    body.theme-dark .pl-table tbody tr:hover td { background: rgba(37,99,235,.1) !important; cursor: pointer; }
    .do-chip { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; border-radius: 12px; font-size: .63rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    .do-chip-draft     { background: rgba(250,204,21,.12); color: #fbbf24; border: 1px solid rgba(250,204,21,.22); }
    .do-chip-confirmed { background: rgba(52,211,153,.1);  color: #34d399; border: 1px solid rgba(52,211,153,.2); }
    .do-chip-delivered { background: rgba(96,165,250,.1);  color: #60a5fa; border: 1px solid rgba(96,165,250,.2); }
    .do-stat-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 9px; border-radius: 10px; font-size: .7rem; font-weight: 600; border: 1px solid var(--cl-border); background: var(--cl-surface-alt); color: var(--cl-text-muted); }
    .do-num { font-weight: 700; color: #60a5fa; font-size: .78rem; }
    .do-customer { font-weight: 600; font-size: .76rem; }
    .do-so-ref { font-size: .67rem; color: var(--cl-text-muted); }
    .pl-act-btn { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); font-size: .7rem; text-decoration: none; cursor: pointer; transition: all .12s; }
    .pl-act-btn.danger { border-color: rgba(239,68,68,.3); color: #f87171; background: rgba(239,68,68,.07); }
    .pl-act-btn.danger:hover { border-color: rgba(239,68,68,.5); background: rgba(239,68,68,.18); color: #fca5a5; }
    .pl-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .3rem; padding: .35rem .75rem; border-top: 1px solid var(--cl-border); font-size: .72rem; color: var(--cl-text-muted); }
    .do-empty-state { text-align: center; padding: 3rem 1rem; }
    .do-empty-state .icon { font-size: 2.4rem; color: var(--cl-text-muted); margin-bottom: .6rem; }
    tr.do-row-hidden { display: none; }
</style>

<div class="pl-wrap container-fluid">
<div class="pl-card">

    <!-- Card Header -->
    <div class="pl-card-header">
        <div>
            <div class="pl-card-header-title"><i class="bi bi-truck me-1"></i>Delivery Orders</div>
            <div class="pl-card-header-sub">
                <span class="do-stat-badge me-1"><span style="color:#fbbf24"><?= $draftCount ?></span> Draft</span>
                <span class="do-stat-badge me-1"><span style="color:#34d399"><?= $confirmedCount ?></span> Confirmed</span>
                <span class="do-stat-badge"><span style="color:#60a5fa"><?= $deliveredCount ?></span> Delivered</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('delivery-orders/pending-followup') ?>" class="pl-btn" title="Open pending shipment follow-up monitor">
                <i class="bi bi-hourglass-split"></i> Pending Shipments
            </a>
            <a href="<?= site_url('warehouse/ready-to-ship') ?>" class="pl-btn pl-btn-primary">
                <i class="bi bi-plus-circle"></i> New DO
            </a>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="pl-filter-bar">
        <div class="pl-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" id="doSearch" placeholder="DO number, customer…" oninput="filterDOs()">
        </div>
        <select class="form-select" id="doStatusFilter" style="width:110px" onchange="filterDOs()">
            <option value="">All Status</option>
            <option value="draft">Draft</option>
            <option value="confirmed">Confirmed</option>
            <option value="delivered">Delivered</option>
        </select>
        <button type="button" class="pl-btn ms-auto" onclick="document.getElementById('doSearch').value='';document.getElementById('doStatusFilter').value='';filterDOs()" title="Reset filters">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
    </div>

    <!-- Table -->
    <div class="table-responsive">
        <table class="table table-hover pl-table mb-0" id="doTable">
            <thead>
                <tr>
                    <th style="width:130px">DO Number</th>
                    <th style="width:95px">Status</th>
                    <th>Customer / SO</th>
                    <th class="text-center" style="width:60px">Items</th>
                    <th style="width:110px">Created</th>
                    <th style="width:110px">Updated</th>
                    <th style="width:44px"></th>
                </tr>
            </thead>
            <tbody id="doTbody">
            <?php if (!empty($allDOs)): ?>
            <?php foreach ($allDOs as $do): ?>
                <tr class="do-row"
                    data-search="<?= strtolower(esc($do['do_number']) . ' ' . esc($do['sales_order']['customer_code'] ?? '') . ' ' . esc($do['sales_order']['customer_name'] ?? '') . ' ' . esc($do['sales_order']['order_number'] ?? '')) ?>"
                    data-status="<?= esc($do['status']) ?>"
                    onclick="window.location.href='<?= site_url('delivery-orders/view/'.(int)$do['do_id']) ?>'">
                    <td><span class="do-num"><?= esc($do['do_number']) ?></span></td>
                    <td>
                        <span class="do-chip do-chip-<?= esc($do['status']) ?>">
                            <i class="bi bi-<?= $do['status']==='draft' ? 'pencil-square' : ($do['status']==='delivered' ? 'check-all' : 'check-circle') ?>"></i>
                            <?= esc(ucfirst($do['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($do['sales_order'])): ?>
                            <div class="do-customer"><?= esc($do['sales_order']['customer_code'] ?? '') ?><span class="ms-1 do-so-ref"><?= esc($do['sales_order']['customer_name'] ?? '') ?></span></div>
                            <div class="do-so-ref"><?= esc($do['sales_order']['order_number'] ?? '') ?></div>
                        <?php else: ?>
                            <span style="color:var(--cl-text-muted)">--</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center" style="color:var(--cl-text-muted)"><?= (int)$do['line_count'] ?></td>
                    <td style="font-size:.72rem">
                        <?php if (!empty($do['created_at'])): ?>
                            <?= date('d M Y', strtotime($do['created_at'])) ?><br>
                            <span style="color:var(--cl-text-muted)"><?= date('H:i', strtotime($do['created_at'])) ?></span>
                        <?php else: ?>--<?php endif; ?>
                    </td>
                    <td style="font-size:.72rem">
                        <?php if (!empty($do['updated_at']) && $do['updated_at'] !== $do['created_at']): ?>
                            <?= date('d M Y', strtotime($do['updated_at'])) ?><br>
                            <span style="color:var(--cl-text-muted)"><?= date('H:i', strtotime($do['updated_at'])) ?></span>
                        <?php else: ?><span style="color:var(--cl-text-muted)">--</span><?php endif; ?>
                    </td>
                    <td onclick="event.stopPropagation()">
                        <?php if ($do['status'] === 'draft'): ?>
                        <button type="button" class="pl-act-btn danger" title="Delete" onclick="deleteDO(<?= (int)$do['do_id'] ?>, '<?= esc($do['do_number']) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        <?php if (empty($allDOs)): ?>
        <div class="do-empty-state">
            <div class="icon"><i class="bi bi-inbox"></i></div>
            <div style="font-weight:600;color:var(--cl-text-primary);margin-bottom:.3rem">No Delivery Orders</div>
            <div style="font-size:.8rem;color:var(--cl-text-muted);margin-bottom:.9rem">Create delivery orders from your ready-to-ship queue.</div>
            <a href="<?= site_url('warehouse/ready-to-ship') ?>" class="pl-btn pl-btn-primary"><i class="bi bi-arrow-right"></i> Ready-to-Ship Queue</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <div class="pl-footer">
        <span id="doCount"><?= $totalDOs ?> delivery order<?= $totalDOs !== 1 ? 's' : '' ?></span>
        <span id="doFilterNote" style="display:none;color:var(--cl-primary)"></span>
    </div>

</div><!-- /.pl-card -->
</div><!-- /.pl-wrap -->

<form id="doDeleteForm" method="post" style="display:none">
    <?= csrf_field() ?>
    <input type="hidden" name="_method" value="DELETE">
</form>

<script>
function deleteDO(id, num) {
    if (!confirm('Delete ' + num + '? This cannot be undone.')) return;
    const form = document.getElementById('doDeleteForm');
    form.action = '<?= site_url('delivery-orders/delete/') ?>' + id;
    form.submit();
}
function filterDOs() {
    const q      = document.getElementById('doSearch').value.toLowerCase().trim();
    const status = document.getElementById('doStatusFilter').value;
    const rows   = document.querySelectorAll('#doTbody .do-row');
    let visible  = 0;
    rows.forEach(function(row) {
        const matchSearch = !q      || row.dataset.search.includes(q);
        const matchStatus = !status || row.dataset.status === status;
        const show = matchSearch && matchStatus;
        row.classList.toggle('do-row-hidden', !show);
        if (show) visible++;
    });
    document.getElementById('doCount').textContent = visible + ' delivery order' + (visible !== 1 ? 's' : '');
    const note = document.getElementById('doFilterNote');
    if (visible < rows.length) {
        note.textContent = '(filtered from ' + rows.length + ')';
        note.style.display = '';
    } else {
        note.style.display = 'none';
    }
}
</script>
<?= $this->endSection() ?>

