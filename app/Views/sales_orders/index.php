<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Sales Orders
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Order Progress Modal -->
<div class="modal fade" id="soListProgressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a;border:1px solid rgba(255,255,255,.1);">
            <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08);padding:.75rem 1.1rem;">
                <h6 class="modal-title text-light mb-0"><i class="bi bi-diagram-3 me-2"></i>Order Progress</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="soListProgressBody" style="padding:1rem 1.1rem;">
                <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
            </div>
        </div>
    </div>
</div>
<div class="container-fluid py-3 cl-list-page">
    <div class="cl-list-header">
        <div>
            <h2 class="mb-0">Sales Orders</h2>
            <small class="text-muted">Manage customer sales orders</small>
        </div>
        <a href="<?= site_url('sales-orders/create') ?>" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Create New</a>
    </div>
    <div class="card cl-list-table-card">
        <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Order Number</th>
                        <th>Customer</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="6" class="text-center text-muted">No sales orders found</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><?= esc($o['id']) ?></td>
                            <td><?= esc($o['order_number'] ?? '-') ?></td>
                            <td><?= esc($o['customer_name'] ?? $o['customer_id']) ?></td>
                            <td><?= esc($o['order_date'] ?? '') ?></td>
                            <td class="text-end"><?= number_format((float)($o['total'] ?? 0), 2) ?></td>
                            <td class="tt-actions">
                                <div class="btn-group" role="group" aria-label="Actions">
                                    <?php
                                        $soViewId = (!empty($o['public_id']) && featureEnabled('enable_public_ids'))
                                            ? $o['public_id']
                                            : $o['id'];
                                    ?>
                                    <a href="<?= site_url('sales-orders/view/'.$soViewId) ?>" class="btn btn-sm btn-outline-secondary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php $oStatus = strtolower($o['status'] ?? ''); ?>
                                    <?php if (in_array($oStatus, ['confirmed','shipped','delivered','processing'], true)): ?>
                                    <button type="button" class="btn btn-sm btn-outline-info btn-so-progress"
                                        data-so-id="<?= (int)$o['id'] ?>"
                                        title="Order Progress">
                                        <i class="bi bi-diagram-3"></i>
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
<?= $this->endSection() ?>
