<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <?php $isVariantContext = !empty($variant['id']); ?>
    <?php $backUrl = $isVariantContext ? base_url('product-variants/' . (int) $variant['id'] . '/edit') : base_url('products/' . (int) ($product['id'] ?? 0) . '?tab=preparation'); ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Preparation<?= $isVariantContext ? ' (Variant)' : '' ?></h1>
            <p class="text-muted mb-0">Product: <?= esc($product['name'] ?? '-') ?></p>
            <?php if ($isVariantContext): ?>
                <p class="text-muted mb-0">Variant: <?= esc($variant['name'] ?? ('#' . ($variant['id'] ?? ''))) ?></p>
            <?php endif; ?>
        </div>
        <a href="<?= $backUrl ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Back
        </a>
    </div>

    <?= view('preparation_profiles/_product_tab', [
        'product' => $product,
        'variant' => $variant ?? null,
        'preparation_profiles' => $preparation_profiles ?? [],
    ]) ?>
</div>
<?= $this->endSection() ?>
