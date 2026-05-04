<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Price Lists
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header section-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="section-title">Price Lists</h3>
            <div class="section-sub">Customer-specific price lists</div>
        </div>
        <div>
            <a href="<?= site_url('price-lists/manage') ?>" class="btn btn-primary">Create Price List</a>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($priceLists)): ?>
            <div class="text-muted">No price lists found.</div>
        <?php else: ?>
            <table class="table table-striped">
                <thead><tr><th>#</th><th>Name</th><th>Customer</th><th>Valid From</th><th>Valid To</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($priceLists as $pl): ?>
                    <tr>
                        <td><?= $pl['id'] ?></td>
                        <td><?= esc($pl['name']) ?></td>
                        <td><?= esc($pl['customer_id']) ?></td>
                        <td><?= esc($pl['valid_from']) ?></td>
                        <td><?= esc($pl['valid_to']) ?></td>
                        <td>
                            <a href="<?= site_url('price-lists/manage/'.$pl['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Edit" aria-label="Edit">
                                <i class="bi bi-pencil"></i>
                                <span class="visually-hidden">Edit</span>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
