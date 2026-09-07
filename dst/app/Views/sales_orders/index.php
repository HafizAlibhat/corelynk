<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Sales Orders
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Order Progress Modal -->
<div class="modal fade" id="soListProgressModal" tabindex="-1" aria-labelledby="soListProgressTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a;border:1px solid rgba(255,255,255,.1);">
            <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08);padding:.75rem 1.1rem;">
                <h6 class="modal-title text-light mb-0" id="soListProgressTitle"><i class="bi bi-diagram-3 me-2" aria-hidden="true"></i>Order Progress</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="soListProgressBody" style="padding:1rem 1.1rem;">
                <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
            </div>
        </div>
    </div>
</div>
<div class="container-fluid py-3 cl-list-page cl-list-page--dashboard">
    <div class="cl-list-header">
        <div>
            <h2 class="mb-0">Sales Orders</h2>
            <small class="text-muted">Manage customer sales orders</small>
        </div>
        <a href="<?= site_url('sales-orders/create') ?>" class="btn btn-primary cl-list-primary-action"><i class="bi bi-plus-lg"></i> Create New</a>
    </div>
    <div class="card cl-list-table-card data-table">
        <div class="card-body p-0">
        <div class="table-responsive">
            <table class="cl-table table table-striped table-hover data-table-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th>Tags</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8"><div class="cl-list-empty cl-list-empty--row"><span class="cl-list-empty__icon" aria-hidden="true"><i class="bi bi-receipt"></i></span><h6>No Sales Orders Found</h6><p>New customer orders will appear here.</p></div></td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?= esc($o['id']) ?></td>
                            <td><?= esc($o['order_number'] ?? '-') ?></td>
                            <td><?= esc($o['customer_name'] ?? $o['customer_id']) ?></td>
                            <td><?= esc($o['order_date'] ?? '') ?></td>
                            <td class="text-end cl-data-number"><?= number_format((float)($o['total'] ?? 0), 2) ?></td>
                            <td>
                                <?php
                                    $oStatus = strtolower(trim((string)($o['status'] ?? 'draft')));
                                    $statusTone = match ($oStatus) {
                                        'delivered', 'completed', 'paid' => 'success',
                                        'cancelled', 'canceled', 'rejected' => 'danger',
                                        'confirmed', 'shipped', 'processing' => 'info',
                                        'draft', 'pending' => 'warning',
                                        default => 'neutral',
                                    };
                                ?>
                                <span class="cl-status-badge cl-status-badge--<?= $statusTone ?>"><span class="cl-status-badge__dot"></span><?= esc(ucwords(str_replace('_', ' ', $oStatus))) ?></span>
                            </td>
                            <td class="doc-tags" data-doc-type="sales_order" data-doc-id="<?= (int)$o['id'] ?>">&hellip;</td>
                            <td class="tt-actions">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php
                                        $soViewId = (!empty($o['public_id']) && featureEnabled('enable_public_ids'))
                                            ? $o['public_id']
                                            : $o['id'];
                                    ?>
                                    <a href="<?= site_url('sales-orders/view/'.$soViewId) ?>" class="btn btn-sm btn-outline-secondary cl-icon-action" title="View" aria-label="View sales order">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-manage-tags cl-icon-action" data-doc-type="sales_order" data-doc-id="<?= (int)$o['id'] ?>" title="Manage Tags" aria-label="Manage sales order tags">
                                        <i class="bi bi-tags" aria-hidden="true"></i>
                                    </button>
                                    <?php if (in_array($oStatus, ['confirmed','shipped','delivered','processing'], true)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-so-progress cl-icon-action"
                                        data-so-id="<?= (int)$o['id'] ?>"
                                        title="Order Progress" aria-label="View order progress">
                                        <i class="bi bi-diagram-3" aria-hidden="true"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.btn-so-progress').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const soId = this.dataset.soId;
        const body = document.getElementById('soListProgressBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading\u2026</div>';
        new bootstrap.Modal(document.getElementById('soListProgressModal')).show();
        fetch('<?= site_url('delivery-orders/progress/so/') ?>' + soId)
            .then(r => r.text())
            .then(html => { body.innerHTML = html; })
            .catch(() => { body.innerHTML = '<p class="text-danger">Failed to load progress.</p>'; });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.doc-tags').forEach(function(td){
        var id = td.dataset.docId;
        if (!id) return;
        fetch('<?= site_url('document-tags') ?>?document_type=sales_order&document_id=' + id)
            .then(r => r.json())
            .then(data => {
                if (!data || !data.success) { td.innerHTML = ''; return; }
                td.innerHTML = '';
                data.data.forEach(function(tag){
                    var span = document.createElement('span');
                    span.className = 'cl-status-badge cl-status-badge--neutral me-1';
                    span.textContent = tag.name;
                    td.appendChild(span);
                });
            }).catch(function(){ td.innerHTML = ''; });
    });
});
</script>
<?= $this->include('components/tag_modal') ?>
<?= $this->endSection() ?>
