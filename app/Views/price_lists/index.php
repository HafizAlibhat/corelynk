<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Price Lists<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header section-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="section-title">Price Lists</h3>
            <div class="section-sub">Customer and vendor pricing rules</div>
        </div>
        <a href="<?= site_url('price-lists/manage') ?>" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Price List</a>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (empty($priceLists)): ?>
            <div class="text-muted">No price lists yet. Create one to price quotations and purchase orders automatically.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="cl-table table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Applies to</th>
                        <th>Pricing</th>
                        <th class="text-end">Items</th>
                        <th>Valid</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($priceLists as $pl): ?>
                    <?php
                        $mode = $pl['pricing_mode'] ?? 'fixed';
                        $pct  = rtrim(rtrim(number_format((float)($pl['margin_percent'] ?? 0), 2), '0'), '.');
                        if ($mode === 'margin_on_cost') {
                            $modeLabel = 'Cost + ' . $pct . '% (' . (($pl['margin_method'] ?? 'markup') === 'margin' ? 'margin' : 'markup') . ')';
                        } elseif ($mode === 'discount_on_base') {
                            $modeLabel = $pct . '% off base price';
                        } else {
                            $modeLabel = 'Fixed prices';
                        }
                    ?>
                    <tr>
                        <td class="fw-semibold"><?= esc($pl['name']) ?><?= !empty($pl['currency']) ? ' <span class="text-muted small">' . esc($pl['currency']) . '</span>' : '' ?></td>
                        <td><span class="badge bg-secondary text-uppercase"><?= esc($pl['party_type'] ?? 'customer') ?></span> <?= esc($pl['party_name'] ?? '') ?></td>
                        <td><?= esc($modeLabel) ?></td>
                        <td class="text-end"><?= (int)($pl['item_count'] ?? 0) ?></td>
                        <td class="small text-muted"><?= esc($pl['valid_from'] ?: 'any') ?> &rarr; <?= esc($pl['valid_to'] ?: 'any') ?></td>
                        <td><span class="badge bg-<?= (int)($pl['is_active'] ?? 0) === 1 ? 'success' : 'secondary' ?>"><?= (int)($pl['is_active'] ?? 0) === 1 ? 'Active' : 'Inactive' ?></span></td>
                        <td class="text-end">
                            <a href="<?= site_url('price-lists/manage/' . $pl['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="<?= site_url('price-lists/delete/' . $pl['id']) ?>" class="d-inline" onsubmit="return confirm('Delete this price list?');">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
