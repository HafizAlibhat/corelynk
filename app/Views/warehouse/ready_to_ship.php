<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Ready to Ship<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Order Progress Modal -->
<div class="modal fade" id="orderProgressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content" style="background:#0f172a;border:1px solid rgba(255,255,255,.1);">
            <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.08);padding:.75rem 1.1rem;">
                <h6 class="modal-title text-light mb-0"><i class="bi bi-diagram-3 me-2"></i>Order Progress</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="orderProgressBody" style="padding:1rem 1.1rem;">
                <div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="mb-0">Ready to Ship Queue</h3>
        <div>
            <a href="<?= site_url('delivery-orders/shipped') ?>" class="btn btn-sm btn-success me-2">
                <i class="bi bi-truck me-1"></i>View Shipped Orders
            </a>
            <a href="<?= site_url('documents') ?>" class="btn btn-sm btn-outline-secondary">Back to Documents</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width: 13%;">SO Number</th>
                        <th style="width: 28%;">Customer</th>
                        <th class="text-end" style="width: 10%;">Ready Qty</th>
                        <th style="width: 16%;">Status</th>
                        <th class="text-end" style="width: 33%;">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $row): ?>
                        <?php 
                            $status = $row['fulfillment_status'] ?? 'UNKNOWN';
                            $badgeClass = match($status) {
                                'READY' => 'bg-success',
                                'PARTIAL_READY' => 'bg-warning text-dark',
                                'NOT_READY' => 'bg-danger',
                                default => 'bg-secondary'
                            };
                            $statusLabel = match($status) {
                                'READY' => 'Ready to Ship',
                                'PARTIAL_READY' => 'Partially Ready',
                                'NOT_READY' => 'Not Ready',
                                default => 'Unknown'
                            };
                            $draftDoId  = $row['draft_do_id'] ?? null;
                            $soId = (int)$row['id'];
                        ?>
                        <tr>
                            <td>
                                <a href="<?= site_url('sales-orders/view/' . $soId) ?>" class="text-decoration-none fw-medium">
                                    <?= esc($row['order_number']) ?>
                                </a>
                            </td>
                            <td><?= esc($row['customer'] ?? '') ?></td>
                            <td class="text-end">
                                <span class="badge bg-info text-dark"><?= number_format((float)($row['ready_qty'] ?? 0), 2) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>"><?= $statusLabel ?></span>
                            </td>
                            <td class="text-end d-flex align-items-center justify-content-end gap-1 flex-wrap">
                                <!-- Order Progress button -->
                                <button type="button" class="btn btn-sm btn-outline-info btn-progress"
                                    data-so-id="<?= $soId ?>"
                                    title="View Order Progress">
                                    <i class="bi bi-diagram-3"></i> Progress
                                </button>

                                <?php if ($draftDoId): ?>
                                    <!-- Draft already exists — show View DO -->
                                    <a href="<?= site_url('delivery-orders/view/' . $draftDoId) ?>" class="btn btn-sm btn-warning">
                                        <i class="bi bi-eye me-1"></i>View Draft DO
                                    </a>
                                <?php elseif ($status === 'READY' || $status === 'PARTIAL_READY'): ?>
                                    <form method="post" action="<?= site_url('delivery-orders/create-from-sales-order/' . $soId) ?>" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-success" title="Create Delivery Order">
                                            <i class="bi bi-truck me-1"></i>Create DO
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-secondary" disabled title="No items ready to ship">
                                        <i class="bi bi-truck me-1"></i>Create DO
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-muted">No sales orders ready to ship.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.btn-progress').forEach(function(btn) {
    btn.addEventListener('click', function() {
        const soId = this.dataset.soId;
        const body = document.getElementById('orderProgressBody');
        body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm me-2"></span>Loading…</div>';
        new bootstrap.Modal(document.getElementById('orderProgressModal')).show();
        fetch('<?= site_url('delivery-orders/progress/so/') ?>' + soId)
            .then(r => r.text())
            .then(html => { body.innerHTML = html; })
            .catch(() => { body.innerHTML = '<p class="text-danger">Failed to load progress.</p>'; });
    });
});
</script>
<?= $this->endSection() ?>

