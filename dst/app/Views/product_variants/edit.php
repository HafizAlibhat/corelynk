<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <?php $inventoryUnit = strtoupper($product['unit'] ?? 'PCS'); ?>
    <?php $weightUnit = strtoupper($product['weight_unit'] ?? 'KG'); ?>
    <?php $variantPosition = $variant_nav['position'] ?? 1; ?>
    <?php $variantTotal = max($variant_nav['total'] ?? 1, 1); ?>
    <?php $prevId = $variant_nav['prev_id'] ?? null; ?>
    <?php $nextId = $variant_nav['next_id'] ?? null; ?>
    <?php $prevUrl = $prevId ? base_url('product-variants/' . $prevId . '/edit') : '#'; ?>
    <?php $nextUrl = $nextId ? base_url('product-variants/' . $nextId . '/edit') : '#'; ?>
    <?php $prevTitle = $variant_nav['prev_label'] ? 'Previous variant: ' . $variant_nav['prev_label'] : 'Previous variant'; ?>
    <?php $nextTitle = $variant_nav['next_label'] ? 'Next variant: ' . $variant_nav['next_label'] : 'Next variant'; ?>
    <?php $productBackIdentifier = trim((string)($product_identifier ?? ($product['public_id'] ?? ''))); ?>
    <?php $productBackUrl = $productBackIdentifier !== '' ? base_url('products/' . $productBackIdentifier) : base_url('products'); ?>
    <style>
        .variant-page {
            --vp-surface: #ffffff;
            --vp-surface-soft: #f8fafc;
            --vp-surface-soft-2: #eef2ff;
            --vp-border: #d8e0ea;
            --vp-text: #1f2937;
            --vp-muted: #6b7280;
            --vp-heading: #111827;
            --vp-accent: #2563eb;
            --vp-accent-soft: #dbeafe;
            --vp-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        }
        body.theme-dark .variant-page {
            --vp-surface: #0b1220;
            --vp-surface-soft: #10192d;
            --vp-surface-soft-2: #0f172a;
            --vp-border: #1f2937;
            --vp-text: #e5e7eb;
            --vp-muted: #94a3b8;
            --vp-heading: #f8fafc;
            --vp-accent: #60a5fa;
            --vp-accent-soft: rgba(37, 99, 235, 0.18);
            --vp-shadow: 0 12px 28px rgba(0, 0, 0, 0.28);
        }
        .variant-page {
            --vp-gap: .5rem;
            color: var(--vp-text);
        }
        .variant-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 280px;
            gap: .75rem;
        }
        .variant-hero {
            background: var(--vp-surface);
            border: 1px solid var(--vp-border);
            border-radius: 1rem;
            padding: 1rem 1.1rem;
            margin-bottom: .9rem;
            box-shadow: var(--vp-shadow);
        }
        .variant-title {
            font-size: clamp(1.55rem, 2vw, 2.2rem);
            font-weight: 700;
            margin-bottom: .2rem;
            color: var(--vp-heading);
        }
        .variant-subtitle {
            font-size: .9rem;
            color: var(--vp-muted);
            margin-bottom: .15rem;
        }
        .variant-nav-meta {
            font-size: .8rem;
            color: var(--vp-muted);
        }
        .variant-nav-btn {
            min-width: 92px;
        }
        .variant-nav-warning {
            font-size: .8rem;
            color: #ca8a04;
        }
        .variant-nav-actions {
            gap: .25rem;
            flex-wrap: wrap;
        }
        .variant-action-cluster {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .variant-step-group {
            border: 1px solid var(--vp-border);
            border-radius: .65rem;
            overflow: hidden;
            box-shadow: var(--vp-shadow);
        }
        .variant-step-btn,
        .variant-top-btn {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            white-space: nowrap;
            font-weight: 600;
        }
        .variant-step-btn {
            background: var(--vp-surface);
            color: var(--vp-text);
            border: 0;
        }
        .variant-step-btn:hover,
        .variant-step-btn:focus {
            background: var(--vp-surface-soft);
            color: var(--vp-heading);
            box-shadow: none;
        }
        .variant-step-btn:disabled,
        .variant-step-btn.disabled {
            background: var(--vp-surface);
            color: var(--vp-muted);
        }
        .variant-top-btn {
            border-color: var(--vp-border);
            background: var(--vp-surface);
            color: var(--vp-text);
            box-shadow: var(--vp-shadow);
        }
        .variant-top-btn:hover,
        .variant-top-btn:focus {
            background: var(--vp-surface-soft);
            color: var(--vp-heading);
            border-color: var(--vp-accent);
        }
        .variant-action-label {
            font-size: .68rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--vp-muted);
            margin-right: .15rem;
        }
        .variant-panel,
        .compact-variant .card-body {
            padding: .9rem;
        }
        .variant-panel {
            background: var(--vp-surface);
            border: 1px solid var(--vp-border);
            border-radius: .9rem;
            box-shadow: var(--vp-shadow);
        }
        .variant-panel + .variant-panel {
            margin-top: .75rem;
        }
        .variant-panel-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .5rem;
            margin-bottom: .65rem;
        }
        .variant-panel-title {
            font-size: .86rem;
            font-weight: 700;
            letter-spacing: .02em;
            margin: 0;
            color: var(--vp-heading);
        }
        .variant-panel-hint {
            font-size: .7rem;
            color: var(--vp-muted);
            line-height: 1.25;
        }
        .variant-helper {
            font-size: .72rem;
            color: var(--vp-muted);
            line-height: 1.3;
            margin-top: .25rem;
        }
        .variant-summary-card {
            background: var(--vp-surface);
            border: 1px solid var(--vp-border);
            border-radius: .9rem;
            padding: .9rem;
            box-shadow: var(--vp-shadow);
        }
        .variant-summary-row {
            display: flex;
            justify-content: space-between;
            gap: .5rem;
            align-items: baseline;
            padding: .25rem 0;
            border-bottom: 1px solid rgba(148,163,184,.16);
            font-size: .78rem;
        }
        .variant-summary-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }
        .variant-summary-label {
            color: var(--vp-muted);
        }
        .variant-summary-value {
            color: var(--vp-text);
            font-weight: 600;
            text-align: right;
        }
        .compact-variant .form-label {
            font-size: .78rem;
            margin-bottom: .2rem;
        }
        .compact-variant .form-control,
        .compact-variant .form-select {
            min-height: calc(1.5em + .45rem + 2px);
        }
        .compact-variant .form-control-sm,
        .compact-variant .form-select-sm {
            padding: .22rem .45rem;
            font-size: .82rem;
            line-height: 1.2;
        }
        .compact-variant .input-group-sm > .form-control,
        .compact-variant .input-group-sm > .form-select {
            padding-top: .22rem;
            padding-bottom: .22rem;
            font-size: .82rem;
        }
        .dark-box {
            background: var(--vp-surface-soft);
            border: 1px solid var(--vp-border);
            color: var(--vp-text);
        }
        .dark-box .text-muted,
        .dark-box .variant-summary-value,
        .dark-box strong {
            color: var(--vp-text) !important;
        }
        .variant-image-thumb {
            max-height: 78px;
            width: auto;
            border-radius: .5rem;
        }
        .variant-image-holder {
            max-width: 100%;
        }
        .vendor-box,
        .stock-box {
            min-height: 72px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .variant-note {
            font-size: .7rem;
            line-height: 1.25;
        }
        .variant-note.warn {
            color: #b45309;
        }
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: .75rem;
            padding: .65rem .75rem;
            border: 1px solid var(--vp-border);
            border-radius: .5rem;
            background: var(--vp-surface);
            margin-bottom: .55rem;
            transition: all .2s ease;
            box-shadow: var(--vp-shadow);
        }
        .order-item:hover {
            border-color: var(--vp-accent);
            background: var(--vp-surface-soft);
        }
        .order-item-header {
            flex: 1;
        }
        .order-item-number {
            font-weight: 600;
            font-size: .88rem;
            color: var(--vp-accent);
        }
        .order-item-date {
            font-size: .72rem;
            color: var(--vp-muted);
            margin-top: .15rem;
        }
        .order-item-qty {
            text-align: right;
            font-size: .76rem;
        }
        .order-item-qty strong {
            display: block;
            color: var(--vp-text);
        }
        @media (max-width: 767px) {
            .variant-shell {
                grid-template-columns: 1fr;
            }
            .variant-hero {
                padding: .85rem;
            }
            .variant-hero .variant-nav-btn {
                flex: 1;
            }
            .variant-hero .text-end {
                width: 100%;
            }
            .variant-image-holder {
                max-width: 100%;
                margin-left: 0;
            }
        }
        @media (max-width: 991px) {
            .variant-shell {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2 variant-page">
        <div>
            <h4 class="variant-title mb-1">Edit Variant • <?= esc($variant['name'] ?? '—') ?></h4>
            <div class="variant-subtitle">
                Template Product: <strong><?= esc($product['name'] ?? '—') ?></strong>
                <?php if (!empty($product['code'])): ?>
                    <span class="ms-2">Code: <?= esc($product['code']) ?></span>
                <?php endif; ?>
            </div>
            <div class="variant-nav-meta mt-1">Variant <?= esc($variantPosition) ?> of <?= esc($variantTotal) ?></div>
        </div>
        <div class="variant-action-cluster">
            <span class="variant-action-label d-none d-md-inline">Navigate</span>
            <div class="btn-group variant-step-group" role="group" aria-label="Variant navigation">
                <a href="<?= esc($prevUrl) ?>" class="btn btn-sm variant-step-btn <?= $prevId ? '' : 'disabled' ?>" title="<?= esc($prevTitle) ?>" <?= $prevId ? '' : 'aria-disabled="true" tabindex="-1"' ?>><i class="bi bi-chevron-left"></i><span>Previous</span></a>
                <a href="<?= esc($nextUrl) ?>" class="btn btn-sm variant-step-btn <?= $nextId ? '' : 'disabled' ?>" title="<?= esc($nextTitle) ?>" <?= $nextId ? '' : 'aria-disabled="true" tabindex="-1"' ?>><span>Next</span><i class="bi bi-chevron-right"></i></a>
            </div>
            <span class="variant-action-label d-none d-md-inline">Go to</span>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= esc($productBackUrl) ?>" class="btn variant-top-btn btn-sm"><i class="bi bi-box-arrow-left"></i><span>Back to Product</span></a>
                <a href="<?= base_url('product-variants?product_id=' . ($product['id'] ?? '')) ?>" class="btn variant-top-btn btn-sm"><i class="bi bi-grid-3x3-gap"></i><span>All Variants</span></a>
            </div>
        </div>
    </div>
    <?php if (!$prevId || !$nextId): ?>
        <div class="variant-nav-warning mb-2">
            <?php if (!$prevId): ?><span>No previous variant to open.</span><?php endif; ?>
            <?php if (!$nextId): ?><span class="ms-2">No next variant available.</span><?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success py-2 mb-2"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php
        $activeTab = strtolower((string) (service('request')->getGet('tab') ?? 'details'));
        if (!in_array($activeTab, ['details', 'preparation', 'assets'], true)) {
            $activeTab = 'details';
        }
        $tabBaseUrl = base_url('product-variants/' . ($variant['id'] ?? '') . '/edit');
    ?>

    <div class="variant-panel shadow-sm mb-2">
        <div class="btn-group btn-group-sm" role="group" aria-label="Variant sections">
            <a href="<?= esc($tabBaseUrl . '?tab=details') ?>" class="btn <?= $activeTab === 'details' ? 'btn-primary' : 'variant-top-btn' ?>">
                <i class="bi bi-sliders2"></i> Details
            </a>
            <a href="<?= esc($tabBaseUrl . '?tab=preparation') ?>" class="btn <?= $activeTab === 'preparation' ? 'btn-primary' : 'variant-top-btn' ?>">
                <i class="bi bi-list-check"></i> Preparation
            </a>
            <?php if (!empty($product_identifier)): ?>
                <a href="<?= esc($tabBaseUrl . '?tab=assets') ?>" class="btn <?= $activeTab === 'assets' ? 'btn-primary' : 'variant-top-btn' ?>">
                    <i class="bi bi-images"></i> Assets
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php $variantPrepProfiles = $variant_preparation_profiles ?? []; ?>
    <div class="<?= $activeTab === 'preparation' ? '' : 'd-none' ?>">
    <div class="variant-panel shadow-sm mb-2">
        <div class="variant-panel-head">
            <div>
                <h5 class="variant-panel-title">Preparation Profiles (This Variant)</h5>
                <div class="variant-panel-hint">Define materials and preparation steps specifically for this variant.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="<?= base_url('preparation-profiles/variant/' . (int) ($variant['id'] ?? 0)) ?>" class="btn btn-sm variant-top-btn">
                    <i class="bi bi-list"></i><span>Open Full List</span>
                </a>
                <a href="<?= base_url('preparation-profiles/variant/' . (int) ($variant['id'] ?? 0) . '/create') ?>" class="btn btn-sm btn-primary">
                    <i class="bi bi-plus-lg"></i><span>Create Profile</span>
                </a>
            </div>
        </div>
        <?php if (empty($variantPrepProfiles)): ?>
            <div class="variant-helper">No variant-specific preparation profile found yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th class="text-center">Steps</th>
                            <th class="text-center">Materials</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($variantPrepProfiles as $prep): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold\"><?= esc($prep['name'] ?? '-') ?></div>
                                    <?php if (!empty($prep['description'])): ?>
                                        <small class="text-muted\"><?= esc($prep['description']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center\"><?= (int) ($prep['steps_count'] ?? 0) ?></td>
                                <td class="text-center\"><?= (int) ($prep['materials_count'] ?? 0) ?></td>
                                <td class="text-end">
                                    <a href="<?= base_url('preparation-profiles/' . (int) ($prep['id'] ?? 0) . '/edit') ?>" class="btn btn-sm btn-outline-primary">Edit</a>
                                    <form method="post" action="<?= base_url('preparation-profiles/' . (int) ($prep['id'] ?? 0) . '/delete') ?>" class="d-inline" onsubmit="return confirm('Delete this preparation profile?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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

    <?php if (!empty($product_identifier)): ?>
    <div class="<?= $activeTab === 'assets' ? '' : 'd-none' ?>">
    <div class="variant-panel shadow-sm mb-2">
        <div class="variant-panel-head">
            <div>
                <h5 class="variant-panel-title">Assets</h5>
                <div class="variant-panel-hint">Variant-scoped assets with shared product common assets.</div>
            </div>
        </div>
        <?= view('product_assets/_product_tab', [
            'productIdentifier' => $product_identifier,
            'variantId' => (int) ($variant['id'] ?? 0),
        ]) ?>
    </div>
    </div>
    <?php endif; ?>

    <div class="<?= $activeTab === 'details' ? '' : 'd-none' ?>">
    <form method="POST" action="<?= base_url('product-variants/' . ($variant['id'] ?? '') . '/update') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="variant-shell">
            <div>
                <div class="variant-panel shadow-sm">
                    <div class="variant-panel-head">
                        <div>
                            <h5 class="variant-panel-title">Identity</h5>
                            <div class="variant-panel-hint">The variant name is locked here to keep the identifier stable.</div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-8">
                            <label class="form-label">Variant Name</label>
                            <input type="text" class="form-control form-control-sm" value="<?= esc($variant['name'] ?? '') ?>" readonly aria-readonly="true">
                            <div class="variant-helper">This is kept from the generated variant name. Change the source data if you need a different label.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Art Number</label>
                            <input type="text" class="form-control form-control-sm" name="art_number" value="<?= esc($variant['art_number'] ?? '') ?>" placeholder="SKU / art number">
                        </div>
                    </div>
                </div>

                <div class="variant-panel shadow-sm">
                    <div class="variant-panel-head">
                        <div>
                            <h5 class="variant-panel-title">Pricing</h5>
                            <div class="variant-panel-hint">Set the selling and cost values for this exact variant.</div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label">Sale Price</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" class="form-control form-control-sm" name="price" value="<?= esc($variant['price'] ?? '') ?>" placeholder="0.00">
                                <?php if (!empty($has_sale_currency)): ?>
                                    <select name="sale_currency" class="form-select form-select-sm" style="max-width:84px">
                                        <option value="">—</option>
                                        <?php foreach (['PKR','USD','EUR','GBP','AED','CNY','SAR'] as $cur): ?>
                                            <option value="<?= $cur ?>" <?= (($variant['sale_currency'] ?? '') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="variant-helper">What the customer sees.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cost</label>
                            <div class="input-group input-group-sm">
                                <input type="number" step="0.01" class="form-control form-control-sm" name="cost" value="<?= esc($variant['cost'] ?? '') ?>" placeholder="0.00">
                                <?php if (!empty($has_cost_currency)): ?>
                                    <select name="cost_currency" class="form-select form-select-sm" style="max-width:84px">
                                        <option value="">—</option>
                                        <?php foreach (['PKR','USD','EUR','GBP','AED','CNY','SAR'] as $cur): ?>
                                            <option value="<?= $cur ?>" <?= (($variant['cost_currency'] ?? '') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php endif; ?>
                            </div>
                            <div class="variant-helper">Your internal acquisition cost.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Weight (<?= esc($weightUnit) ?>)</label>
                            <input type="number" step="0.001" class="form-control form-control-sm" name="weight" value="<?= esc($variant['weight'] ?? '') ?>" placeholder="0.000">
                            <div class="variant-helper">Used for inventory and process calculations. <?= $weightUnit !== 'KG' ? 'Stored in KG.' : '' ?></div>
                            <?php if ($weightUnit !== 'KG'): ?>
                                <div class="variant-note warn">Weights are stored in kilograms. Convert entries to <?= esc($weightUnit) ?> if required.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="variant-panel shadow-sm">
                    <div class="variant-panel-head">
                        <div>
                            <h5 class="variant-panel-title">Procurement</h5>
                            <div class="variant-panel-hint">Leave blank to inherit from the template product.</div>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <?php if (!empty($has_variant_vendor)): ?>
                                <label class="form-label">Variant Vendor</label>
                                <select name="vendor_id" class="form-select form-select-sm">
                                    <option value="">— Use Template Vendor (<?= esc($product['vendor_name'] ?? 'Not Set') ?>) —</option>
                                    <?php if (!empty($vendors)): ?>
                                        <?php foreach ($vendors as $v): ?>
                                            <option value="<?= esc($v['id']) ?>" <?= ($variant_vendor_id == $v['id']) ? 'selected' : '' ?>><?= esc($v['name']) ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <div class="variant-helper">Only choose a different vendor when this variant is sourced separately.</div>
                            <?php else: ?>
                                <label class="form-label">Template Vendor</label>
                                <div class="form-control form-control-sm dark-box vendor-box">
                                    <?php if (!empty($product['vendor_name'])): ?>
                                        <strong><?= esc($product['vendor_name']) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">No template vendor set.</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <?php if (!empty($has_variant_vendor_price)): ?>
                                <label class="form-label">Vendor Price</label>
                                <div class="input-group input-group-sm">
                                    <input type="number" step="0.01" class="form-control form-control-sm" name="vendor_price" value="<?= esc(($variant_saved_vendor_price ?? '') !== '' ? number_format((float)$variant_saved_vendor_price, 2, '.', '') : '') ?>" placeholder="0.00">
                                    <?php if (!empty($has_vendor_currency)): ?>
                                        <select name="vendor_currency" class="form-select form-select-sm" style="max-width:84px">
                                            <option value="">—</option>
                                            <?php foreach (['PKR','USD','EUR','GBP','AED','CNY','SAR'] as $cur): ?>
                                                <option value="<?= $cur ?>" <?= (($variant_saved_vendor_currency ?? '') === $cur) ? 'selected' : '' ?>><?= $cur ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php endif; ?>
                                </div>
                                <div class="variant-helper">Used to seed RFQ / PO line pricing for this variant.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <div class="variant-summary-card shadow-sm mb-2">
                    <div class="variant-panel-head mb-2">
                        <div>
                            <h5 class="variant-panel-title">Variant Snapshot</h5>
                            <div class="variant-panel-hint">Quick reference while editing.</div>
                        </div>
                    </div>
                    <div class="variant-summary-row">
                        <div class="variant-summary-label">Template</div>
                        <div class="variant-summary-value"><?= esc($product['name'] ?? '—') ?></div>
                    </div>
                    <div class="variant-summary-row">
                        <div class="variant-summary-label">Vendor</div>
                        <div class="variant-summary-value"><?= esc($variant_vendor_name ?? ($product['vendor_name'] ?? '—')) ?></div>
                    </div>
                    <div class="variant-summary-row">
                        <div class="variant-summary-label">Sale</div>
                        <div class="variant-summary-value"><?= esc(trim(($variant['price'] ?? '') . ' ' . ($variant['sale_currency'] ?? ''))) ?: '—' ?></div>
                    </div>
                    <div class="variant-summary-row">
                        <div class="variant-summary-label">Cost</div>
                        <div class="variant-summary-value"><?= esc(trim(($variant['cost'] ?? '') . ' ' . ($variant['cost_currency'] ?? ''))) ?: '—' ?></div>
                    </div>
                    <div class="variant-summary-row">
                        <div class="variant-summary-label">RFQ Price</div>
                        <div class="variant-summary-value"><?= esc(($variant_saved_vendor_price !== null && $variant_saved_vendor_price !== '') ? number_format((float)$variant_saved_vendor_price, 2) . ' ' . ($variant_saved_vendor_currency ?? '') : '—') ?></div>
                    </div>
                    <div class="variant-summary-row">
                        <div class="variant-summary-label">Weight</div>
                        <div class="variant-summary-value"><?= esc(number_format((float)($variant['weight'] ?? 0), 3)) ?> <?= esc($weightUnit) ?></div>
                    </div>
                </div>

                <div class="variant-summary-card shadow-sm mb-2">
                    <div class="variant-panel-head mb-2">
                        <div>
                            <h5 class="variant-panel-title">Image</h5>
                            <div class="variant-panel-hint">Optional, but useful for quick recognition.</div>
                        </div>
                    </div>
                    <?php $img = !empty($variant['image']) ? base_url('uploads/variants/' . $variant['image']) : ''; ?>
                    <div class="border rounded p-2 text-center dark-box variant-image-holder">
                        <?php if ($img): ?>
                            <a href="<?= esc($img) ?>" class="variant-image-link" data-bs-toggle="modal" data-bs-target="#variantImageModal">
                                <img src="<?= esc($img) ?>" class="img-fluid rounded variant-image-thumb" alt="Variant image">
                            </a>
                        <?php else: ?>
                            <div class="text-muted" style="height:76px;display:flex;align-items:center;justify-content:center;">
                                <i class="bi bi-image" style="font-size:1.6rem"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="images[]" class="form-control form-control-sm mt-2" accept="image/*" multiple>
                    <div class="variant-helper">Click the preview to enlarge.</div>
                </div>

                <div class="variant-summary-card shadow-sm">
                    <div class="variant-panel-head mb-2">
                        <div>
                            <h5 class="variant-panel-title">Stock</h5>
                            <div class="variant-panel-hint">Read-only status for this variant.</div>
                        </div>
                    </div>
                    <div class="form-control form-control-sm dark-box stock-box">
                        <div class="text-muted">On hand: <strong><?= number_format($on_hand ?? 0, 2) ?></strong></div>
                        <div class="text-muted">Reserved: <strong><?= number_format($reserved ?? 0, 2) ?></strong></div>
                        <div class="text-muted">Available: <strong><?= number_format($available ?? 0, 2) ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-2">
            <a href="<?= esc($productBackUrl) ?>" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Changes</button>
        </div>
    </form>

    <div class="row g-2 mt-2">
        <div class="col-md-6">
            <div class="card compact-variant shadow-sm border-0">
                <div class="card-header"><i class="bi bi-graph-up me-2"></i><strong>Recent Sales (last 5)</strong></div>
                <div class="card-body p-2">
                    <?php if (!empty($recent_sales)): ?>
                        <?php foreach ($recent_sales as $s): ?>
                            <a href="<?= base_url('sales-orders/view/' . ($s['sales_order_id'] ?? '#')) ?>" class="order-item text-decoration-none">
                                <div class="order-item-header">
                                    <div class="order-item-number"><?= esc($s['order_number'] ?? 'SO') ?></div>
                                    <div class="order-item-date"><?= date('M j, Y', strtotime($s['created_at'] ?? 'now')) ?></div>
                                </div>
                                <div class="order-item-qty">
                                    <strong><?= number_format((float)($s['quantity'] ?? 0), 2) ?></strong>
                                    <div class="text-muted small">@ <?= number_format((float)($s['unit_price'] ?? 0), 2) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small text-center py-3">No recent sales.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card compact-variant shadow-sm border-0">
                <div class="card-header"><i class="bi bi-box-seam me-2"></i><strong>Recent Purchases (last 5)</strong></div>
                <div class="card-body p-2">
                    <?php if (!empty($recent_purchases)): ?>
                        <?php foreach ($recent_purchases as $p): ?>
                            <a href="<?= base_url('new-purchase-orders/' . ($p['purchase_order_id'] ?? '#')) ?>" class="order-item text-decoration-none">
                                <div class="order-item-header">
                                    <div class="order-item-number"><?= esc($p['po_number'] ?? 'PO') ?></div>
                                    <div class="order-item-date"><?= date('M j, Y', strtotime($p['created_at'] ?? 'now')) ?></div>
                                </div>
                                <div class="order-item-qty">
                                    <strong><?= number_format((float)($p['quantity'] ?? 0), 2) ?></strong>
                                    <div class="text-muted small">@ <?= number_format((float)($p['unit_price'] ?? 0), 2) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="text-muted small text-center py-3">No recent purchases.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    </div>
</div>

<!-- Image Lightbox Modal -->
<div class="modal fade" id="variantImageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 text-center">
                <img id="variantImageLarge" src="" class="img-fluid rounded" style="max-height:80vh;">
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var link = document.querySelector('.variant-image-link');
    var modalImage = document.getElementById('variantImageLarge');
    if (!link || !modalImage) return;
    link.addEventListener('click', function(){
        modalImage.src = this.getAttribute('href');
    });
});
</script>
<?= $this->endSection() ?>
