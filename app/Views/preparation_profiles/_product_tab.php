<?php
$productId = (int) ($product['id'] ?? 0);
$profiles = $preparation_profiles ?? [];
$variantId = (int) ($variant['id'] ?? 0);
$isVariantContext = $variantId > 0;
$createUrl = $isVariantContext
    ? base_url('preparation-profiles/variant/' . $variantId . '/create')
    : base_url('preparation-profiles/product/' . $productId . '/create');
?>

<div class="card mt-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">Preparation Profiles<?= $isVariantContext ? ' (Variant)' : '' ?></h5>
        <a href="<?= $createUrl ?>" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg"></i> Create
        </a>
    </div>
    <div class="card-body">
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <?php if (empty($profiles)): ?>
            <p class="text-muted mb-0">No preparation profiles found for this product.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Profile Name</th>
                            <th class="text-center">Steps</th>
                            <th class="text-center">Materials</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($profiles as $profile): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= esc($profile['name']) ?></div>
                                    <?php if (!empty($profile['description'])): ?>
                                        <small class="text-muted"><?= esc($profile['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?= (int) ($profile['steps_count'] ?? 0) ?></td>
                                <td class="text-center"><?= (int) ($profile['materials_count'] ?? 0) ?></td>
                                <td class="text-end">
                                    <a href="<?= base_url('preparation-profiles/' . (int) $profile['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">Edit</a>
                                    <form method="post" action="<?= base_url('preparation-profiles/' . (int) $profile['id'] . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete this preparation profile?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
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
