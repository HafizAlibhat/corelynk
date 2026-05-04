<?= $this->extend('layouts/print') ?>

<?= $this->section('title') ?>Gatepass<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <div class="text-center mb-4">
        <h3>Gatepass</h3>
        <p class="small text-muted">Batch: <?= esc($release['batch_code']) ?></p>
    </div>

    <table class="table table-borderless">
        <tr><th>Gatepass ID</th><td><?= esc($release['id']) ?></td></tr>
        <tr><th>Batch Code</th><td><?= esc($release['batch_code']) ?></td></tr>
        <tr><th>Released Qty</th><td><?= number_format($release['released_qty']) ?></td></tr>
        <tr><th>Carrier</th><td><?= esc($release['carrier'] ?? '') ?></td></tr>
        <tr><th>Released At</th><td><?= esc($release['released_at']) ?></td></tr>
        <tr><th>Notes</th><td><?= nl2br(esc($release['notes'] ?? '')) ?></td></tr>
    </table>

    <div class="mt-4 text-center">
        <button class="btn btn-primary" onclick="window.print()">Print Gatepass</button>
        <a href="<?= base_url('/work-orders/' . ($release['work_order_id'] ?? '')) ?>" class="btn btn-secondary">Back</a>
    </div>
</div>
<?= $this->endSection() ?>
