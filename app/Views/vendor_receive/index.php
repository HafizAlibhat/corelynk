<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Vendor Receiving + QC</h1>
            <p class="text-muted mb-0">Open vendor send notes waiting for receiving and quality checks.</p>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php if (empty($send_notes ?? [])): ?>
                <p class="text-muted mb-0">No pending vendor send notes found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cl-table table table-sm table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Vendor</th>
                                <th>Product</th>
                                <th>Step</th>
                                <th class="text-end">Sent Qty</th>
                                <th class="text-end">Remaining Qty</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (($send_notes ?? []) as $note): ?>
                                <tr>
                                    <td><?= esc($note['reference_no'] ?? '-') ?></td>
                                    <td>
                                        <span class="fw-medium"><?= esc($note['vendor_name'] ?? '-') ?></span><?php if (! empty($note['to_location_name'])): ?> <span class="text-muted small">(<?= esc($note['to_location_name']) ?>)</span><?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (! empty($note['resolved_code'])): ?><span class="badge bg-secondary-subtle text-secondary-emphasis border me-1 font-monospace" style="font-size: 0.72rem;"><?= esc($note['resolved_code']) ?></span><?php endif; ?><span class="fw-semibold"><?= esc($note['product_name'] ?? '-') ?></span><?php if (! empty($note['resolved_variant_name'])): ?> <span class="text-muted">&mdash; <?= esc($note['resolved_variant_name']) ?></span><?php endif; ?>
                                    </td>
                                    <td><?= esc($note['step_name'] ?? '-') ?></td>
                                    <td class="text-end"><?= number_format((float) ($note['qty'] ?? 0), 2) ?></td>
                                    <td class="text-end"><?= number_format((float) ($note['remaining_qty'] ?? 0), 2) ?></td>
                                    <td><span class="badge bg-secondary text-uppercase"><?= esc($note['status'] ?? '-') ?></span></td>
                                    <td class="text-end">
                                        <a href="<?= site_url('vendor-receive/' . (int) ($note['id'] ?? 0)) ?>" class="btn btn-sm btn-primary">
                                            Receive + QC
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
