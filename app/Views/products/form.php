<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $productIdentifier = isset($product) && $product ? entityRouteIdentifier($product) : ''; ?>
<style>
    /* View-local compact styling (keeps layout/features unchanged) */
    .cl-compact .card { margin-bottom: 1rem !important; }
    .cl-compact .card-header { padding: .5rem .75rem; }
    .cl-compact .card-body { padding: .75rem; }
    .cl-compact .form-label { margin-bottom: .25rem; font-size: .85rem; }
    .cl-compact .form-text { margin-top: .25rem; font-size: .8rem; }
    .cl-compact .btn { padding: .3rem .6rem; }
    .cl-compact .btn-sm { padding: .25rem .5rem; }
    .cl-compact .form-control,
    .cl-compact .form-select {
        padding: .25rem .5rem;
        font-size: .9rem;
    }
    .cl-compact h2 { font-size: 1.5rem; }
    .cl-compact .table td,
    .cl-compact .table th { padding: .4rem .5rem; }
    .cl-compact .sticky-top { top: 12px !important; }

    /* “Above the fold” layout helpers */
    .cl-compact .nav-tabs .nav-link { padding: .35rem .65rem; font-size: .85rem; }
    .cl-compact .tab-pane { padding-top: .5rem; }
    .cl-compact .cl-actions { gap: .5rem; }
    .cl-compact .cl-actions .btn { white-space: nowrap; }

    /* Attribute/variant alignment & density */
    .cl-compact #attributesCard .form-text { display: none; }
    .cl-compact #attributesCard .badge { font-size: .78rem; padding: .35em .55em; }
    .cl-compact #attributesCard .border.rounded { background: #fcfcfd; }

    /* Dark theme for Attributes tab (Odoo-like) */
    .cl-compact #attributesCard.attrs-dark { background: #0f172a; color: #e5e7eb; border: 1px solid #1f2937; }
    .cl-compact #attributesCard.attrs-dark .card-header { background: #111827; color: #e5e7eb; border-bottom: 1px solid #1f2937; }
    .cl-compact #attributesCard.attrs-dark .card-body { background: #0f172a; }
    .cl-compact #attributesCard .table-responsive { overflow: visible !important; }
    .cl-compact #attributesCard.attrs-dark .form-label { color: #e5e7eb; }
    .cl-compact #attributesCard.attrs-dark .form-control,
    .cl-compact #attributesCard.attrs-dark .form-select {
        background: #0b1220; border-color: #243244; color: #e5e7eb;
    }
    .cl-compact #attributesCard.attrs-dark .form-control::placeholder { color: #94a3b8; }
    .cl-compact #attributesCard.attrs-dark .list-group-item {
        background: #0b1220; border-color: #1f2937; color: #e5e7eb;
    }
    .cl-compact #attributesCard.attrs-dark .list-group-item.active {
        background: #1e293b; border-color: #334155; color: #e5e7eb;
    }
    .cl-compact #attributesCard.attrs-dark .badge { background: #1f2937; color: #e5e7eb; }
    .cl-compact #attributesCard.attrs-dark .btn-outline-primary { color: #93c5fd; border-color: #3b82f6; }
    .cl-compact #attributesCard.attrs-dark .btn-outline-primary:hover { background: #1d4ed8; color: #fff; }
    .cl-compact #attributesCard.attrs-dark .text-muted { color: #94a3b8 !important; }
    .cl-compact #attributesCard.attrs-dark .list-group-item { display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
    .cl-compact #attributesCard.attrs-dark .list-group-item .meta { flex:1; }
    .cl-compact #attributesCard.attrs-dark .value-chip { display:inline-flex; align-items:center; gap:.25rem; }
    .cl-compact #attributesCard.attrs-dark .attr-table th,
    .cl-compact #attributesCard.attrs-dark .attr-table td {
        vertical-align: top;
    }
    .cl-compact #attributesCard.attrs-dark .attr-table td.attr-cell,
    .cl-compact #attributesCard.attrs-dark .attr-table td.values-cell {
        position: relative;
        padding-bottom: 1rem;
    }
    .cl-compact #attributesCard.attrs-dark .attr-input-wrapper,
    .cl-compact #attributesCard.attrs-dark .value-input-wrapper {
        position: relative;
    }
    .cl-compact #attributesCard.attrs-dark .attr-search-results,
    .cl-compact #attributesCard.attrs-dark .value-search-results {
        position: absolute;
        top: calc(100% + .25rem);
        left: 0;
        right: 0;
        z-index: 30;
        background: #0b1220;
        border: 1px solid #1f2937;
        border-radius: .35rem;
        max-height: 220px;
        overflow-y: auto;
        box-shadow: 0 10px 24px rgba(15, 23, 42, 0.65);
    }
    .cl-compact #attributesCard.attrs-dark .value-input-wrapper {
        width: 100%;
    }
    .cl-compact #attributesCard.attrs-dark .values-chip-input {
        background: #0b1220;
        border: 1px solid #1f2937;
        border-radius: .35rem;
        padding: .5rem .65rem;
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
        align-items: center;
        min-height: 2.9rem;
    }
    .cl-compact #attributesCard.attrs-dark .values-chip-input .form-control {
        border: none;
        background: transparent;
        color: #e5e7eb;
        box-shadow: none;
        padding: .25rem 0;
    }
    .cl-compact #attributesCard.attrs-dark .values-chip-input .form-control:focus {
        box-shadow: none;
    }
    .cl-compact #attributesCard.attrs-dark .values-chip-input .input-group {
        flex: 1 1 auto;
    }
    .cl-compact #attributesCard.attrs-dark .values-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .35rem;
    }
    .cl-compact #attributesCard.attrs-dark .attr-search-results .list-group-item,
    .cl-compact #attributesCard.attrs-dark .value-search-results .list-group-item {
        cursor: pointer;
    }

    /* Keep media previews from forcing page scroll */
    .cl-compact #imagePreview,
    .cl-compact #existingImages { max-height: 260px; overflow: auto; }
</style>

<?php
    $productTypeSelection = old('product_type', $product['product_type'] ?? 'simple');
    $isVariable = $productTypeSelection === 'variable';
?>
<div class="cl-compact">
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h2 class="mb-0">
                <i class="bi bi-box me-2"></i>
                <?= isset($product) && $product ? 'Edit Product' : 'Create Product' ?>
            </h2>
        </div>
    </div>
</div>

<?php if (session()->getFlashdata('validation')): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach (session()->getFlashdata('validation') as $error): ?>
                <li><?= esc($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle me-2"></i>
        <?= esc(session()->getFlashdata('success')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($skuFail = session()->getFlashdata('sku_allocation_failed')): ?>
    <div class="alert alert-warning">
        <strong>Art number allocation failed:</strong>
        <div><?= esc($skuFail['message'] ?? 'Unknown error') ?></div>
        <?php if (!empty($skuFail['category_id'])): ?>
            <div class="mt-2">
                <a href="<?= base_url('/product-categories/' . $skuFail['category_id'] . '/edit') ?>" class="btn btn-sm btn-primary">
                    Edit Category to adjust range
                </a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?= form_open_multipart(isset($product) && $product ? base_url('/products/' . $product['id'] . '/update') : base_url('/products/store'), 
    ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'productForm']) ?>
<?php if (empty($product) && !empty($form_submit_token)): ?>
    <input type="hidden" name="_form_submit_token" value="<?= esc($form_submit_token) ?>">
<?php endif; ?>

<div class="d-flex cl-actions flex-wrap justify-content-end mb-2">
    <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle me-2"></i>
        <?= isset($product) && $product ? 'Update Product' : 'Create Product' ?>
    </button>
    <a href="<?= base_url('/products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-x-circle me-2"></i>Cancel
    </a>
    <a href="<?= base_url('/products') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-2"></i>Back
    </a>
    <?php if (isset($product) && $product): ?>
        <a href="<?= base_url('/products/' . $productIdentifier) ?>" class="btn btn-outline-info">
            <i class="bi bi-eye me-2"></i>View
        </a>
    <?php endif; ?>
</div>

<ul class="nav nav-tabs" id="productFormTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-basic-btn" data-bs-toggle="tab" data-bs-target="#tab-basic" type="button" role="tab" aria-controls="tab-basic" aria-selected="true">
            <i class="bi bi-info-circle me-1"></i>Basics
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-attrs-btn" data-bs-toggle="tab" data-bs-target="#tab-attrs" type="button" role="tab" aria-controls="tab-attrs" aria-selected="false">
            <i class="bi bi-list-ul me-1"></i>Attributes &amp; Variants
        </button>
    </li>
    <?php if (isset($product) && $product): ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-variants-btn" data-bs-toggle="tab" data-bs-target="#tab-variants" type="button" role="tab" aria-controls="tab-variants" aria-selected="false">
            <i class="bi bi-grid-3x3-gap me-1"></i>Variants
        </button>
    </li>
    <?php endif; ?>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-media-btn" data-bs-toggle="tab" data-bs-target="#tab-media" type="button" role="tab" aria-controls="tab-media" aria-selected="false">
            <i class="bi bi-image me-1"></i>Media
        </button>
    </li>
</ul>

<div class="tab-content border border-top-0 rounded-bottom bg-white shadow-sm p-2">
    <div class="tab-pane fade show active" id="tab-basic" role="tabpanel" aria-labelledby="tab-basic-btn" tabindex="0">
        <div class="row g-2">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Basic Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="name" class="form-label">Product Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control form-control-sm" id="name" name="name" value="<?= old('name', $product['name'] ?? '') ?>" required>
                                <div class="invalid-feedback">Please provide a valid product name.</div>
                            </div>
                        </div>

                        <div class="row g-2 mt-1">
                            <div class="col-md-4">
                                <label for="category_id" class="form-label">Category</label>
                                <div class="d-flex align-items-start">
                                    <div class="flex-grow-1 me-2">
                                        <select class="form-select form-select-sm searchable" id="category_id" name="category_id">
                                            <option value="">Select Category</option>
                                            <?php if (isset($categories)): ?>
                                                <?php foreach ($categories as $category): ?>
                                                    <option value="<?= $category['id'] ?>" <?= old('category_id', $product['category_id'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                                        <?= esc($category['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary open-remote-modal" data-url="<?= base_url('/product-categories/create') ?>" title="Add category" aria-label="Add category">
                                        <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </div>
                            <?php if ($isVariable): ?>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Product Code</label>
                                    <div class="text-muted small" style="padding:0.35rem 0; font-style:italic;">— Template</div>
                                </div>
                            <?php else: ?>
                                <div class="col-md-3">
                                    <label for="code" class="form-label">Product Code<span class="text-danger">*</span></label>
                                    <input type="text" class="form-control form-control-sm" id="code" name="code" value="<?= esc(old('code', $product['code'] ?? '')) ?>" required readonly placeholder="Auto-generated from category">
                                    <div id="codePreviewMessage" class="form-text text-muted small mt-1" style="display:none;"></div>
                                    <div class="form-text">
                                        <button type="button" class="btn btn-sm btn-link p-0" id="fetchCategoryCodeBtn">Use category next art number</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div class="col-md-3">
                                <label for="unit" class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm searchable" id="unit" name="unit" required>
                                    <option value="">Select Unit</option>
                                    <?php 
                                    $units = ['PCS', 'KG', 'LTR', 'MTR', 'SQM', 'CUM', 'SET', 'PKG', 'BOX', 'CTN', 'DOZEN', 'GRAM', 'TON'];
                                    foreach ($units as $unit_option): 
                                    ?>
                                        <option value="<?= $unit_option ?>" <?= old('unit', $product['unit'] ?? '') == $unit_option ? 'selected' : '' ?>>
                                            <?= $unit_option ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback">Please select a unit of measure.</div>
                            </div>
                            <div class="col-md-2 template-weight-field weight-only">
                                <label class="form-label small mb-1">Weight</label>
                                <div class="input-group input-group-sm">
                                    <?php $selectedWeightUnit = old('weight_unit', isset($product['weight_unit']) ? $product['weight_unit'] : 'KG'); ?>
                                    <input type="number" step="0.01" min="0" class="form-control" id="weight" name="weight" value="<?= old('weight', isset($product['weight']) ? number_format((float)($product['weight'] ?? 0), 2, '.', '') : '') ?>" placeholder="0.00" style="max-width:70px;">
                                    <select class="form-select form-select-sm" id="weight_unit" name="weight_unit" style="max-width:65px">
                                        <?php foreach (($weightUnits ?? ['KG']) as $unitOption): ?>
                                            <option value="<?= esc($unitOption) ?>" <?= $selectedWeightUnit === $unitOption ? 'selected' : '' ?>><?= esc($unitOption) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if (!empty($product['id']) && isset($variants) && count($variants) > 0): ?>
                                    <button type="button" id="copyTemplateWeightBtn" class="btn btn-sm btn-primary mt-2" data-url="<?= base_url('/products/' . $product['id'] . '/copy-weight-to-variants') ?>" title="Sync weight to all variants">
                                        <i class="fas fa-copy me-1"></i> Copy to Variants
                                    </button>
                                    <div id="copyWeightFeedback" class="form-text small text-muted mt-2" aria-live="polite" style="max-width:300px; word-wrap:break-word;"></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mt-1">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control form-control-sm" id="description" name="description" rows="2" placeholder="Short description or notes"><?= old('description', $product['description'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="card-title mb-0"><i class="bi bi-gear me-2"></i>Settings</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" <?= old('is_active', $product['is_active'] ?? '1') ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="is_active">Active Product</label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small mb-1">Product Type</label>
                                <?php $dtype = old('detailed_type', $product['detailed_type'] ?? 'storable'); ?>
                                <select id="detailed_type" name="detailed_type" class="form-select form-select-sm">
                                    <option value="storable" <?= $dtype === 'storable' ? 'selected' : '' ?>>Storable Product</option>
                                    <option value="consumable" <?= $dtype === 'consumable' ? 'selected' : '' ?>>Consumable</option>
                                    <option value="service" <?= $dtype === 'service' ? 'selected' : '' ?>>Service</option>
                                </select>
                                <div class="form-text small text-muted">Only <strong>Storable</strong> appears in Inventory.</div>
                            </div>

                            <!-- Service Policy (visible only for service products) -->
                            <div class="col-md-6 service-only-field" id="servicePolicyGroup" style="display:none;">
                                <label class="form-label small mb-1">Invoicing Policy</label>
                                <?php $spolicy = old('service_policy', $product['service_policy'] ?? 'ordered_qty'); ?>
                                <select id="service_policy" name="service_policy" class="form-select form-select-sm">
                                    <option value="ordered_qty" <?= $spolicy === 'ordered_qty' ? 'selected' : '' ?>>Ordered Quantities</option>
                                    <option value="delivered_qty" <?= $spolicy === 'delivered_qty' ? 'selected' : '' ?>>Delivered Quantities</option>
                                </select>
                                <div class="form-text small text-muted">
                                    <strong>Ordered</strong>: invoice on order confirmation. <strong>Delivered</strong>: invoice only what's delivered/completed.
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small mb-1">Variant Mode</label>
                                <select id="product_type" name="product_type" class="form-select form-select-sm">
                                    <option value="simple" <?= $productTypeSelection == 'simple' ? 'selected' : '' ?>>Simple Product</option>
                                    <option value="variable" <?= $productTypeSelection == 'variable' ? 'selected' : '' ?>>Variable (has variants)</option>
                                </select>
                                <input type="hidden" id="variants_count" value="<?= isset($variants) ? count($variants) : 0 ?>">
                            </div>

                            <div class="col-12">
                                <div class="row g-2 align-items-start mt-2">
                                    <div class="col-md-6 template-vendor-field">
                                        <label for="vendor_id" class="form-label small mb-1">Vendor</label>
                                        <select class="form-select form-select-sm" id="vendor_id" name="vendor_id">
                                            <option value="">— No Vendor —</option>
                                            <?php if (!empty($vendors ?? [])): ?>
                                                <?php foreach ($vendors as $v): ?>
                                                    <option value="<?= esc($v['id']) ?>" <?= (old('vendor_id', $product['vendor_id'] ?? '') == $v['id']) ? 'selected' : '' ?>>
                                                        <?= esc($v['name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </select>
                                        <?php if (!empty($product['id']) && isset($variants) && count($variants) > 0): ?>
                                            <button type="button" id="copyTemplateVendorBtn" class="btn btn-sm btn-primary mt-2" data-url="<?= base_url('/products/' . $product['id'] . '/copy-vendor-to-variants') ?>" title="Sync vendor to all variants">
                                                <i class="fas fa-copy me-1"></i> Copy to Variants
                                            </button>
                                            <div id="copyVendorFeedback" class="form-text small text-muted mt-2" aria-live="polite" style="max-width:300px; word-wrap:break-word;"></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 template-vendor-field template-price-field">
                                        <label class="form-label small mb-1">Vendor Price PKR</label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text" style="padding:0.25rem 0.4rem;">Rs</span>
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm price-field" id="vendor_price_pkr" name="vendor_price_pkr" value="<?= old('vendor_price_pkr', isset($product['vendor_price_pkr']) ? $product['vendor_price_pkr'] : '') ?>" placeholder="0.00">
                                        </div>
                                        <?php if (!empty($product['id']) && isset($variants) && count($variants) > 0): ?>
                                            <button type="button" id="copyTemplateVendorPkrBtn" class="btn btn-sm btn-primary mt-2" data-url="<?= base_url('/products/' . $product['id'] . '/copy-vendor-pkr-to-variants') ?>" title="Sync vendor price (PKR) to all variants">
                                                <i class="fas fa-copy me-1"></i> Copy to Variants
                                            </button>
                                            <div id="copyVendorPkrFeedback" class="form-text small text-muted mt-2" aria-live="polite" style="max-width:300px; word-wrap:break-word;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="row g-2 align-items-start mt-1">
                                    <div class="col-md-6 template-price-field">
                                        <label class="form-label small mb-1">Cost Price</label>
                                        <div class="d-flex input-group-sm align-items-center gap-1">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm price-field" id="cost_price" name="cost_price" value="<?= old('cost_price', isset($product['cost_price']) ? $product['cost_price'] : '') ?>" style="flex:3; min-width:120px; font-size:0.95rem; font-weight:500;">
                                            <select class="form-select form-select-sm currency-select searchable" id="cost_currency" name="cost_currency" style="flex:0 0 auto; width:40px; font-size:0.7rem; padding:0.25rem 0.25rem;">
                                                <?php $cc = old('cost_currency', $product['cost_currency'] ?? ($default_currency ?? 'USD')); ?>
                                                <?php foreach (($currencies ?? []) as $c): ?>
                                                    <option value="<?= esc($c['code']) ?>" <?= $cc == $c['code'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if (!empty($product['id']) && isset($variants) && count($variants) > 0): ?>
                                            <button type="button" id="copyTemplateCostBtn" class="btn btn-sm btn-primary mt-2" data-url="<?= base_url('/products/' . $product['id'] . '/copy-cost-to-variants') ?>" title="Sync cost price to all variants">
                                                <i class="fas fa-copy me-1"></i> Copy to Variants
                                            </button>
                                            <div id="copyCostFeedback" class="form-text small text-muted mt-2" aria-live="polite" style="max-width:300px; word-wrap:break-word;"></div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-6 template-price-field">
                                        <label class="form-label small mb-1">Sale Price</label>
                                        <div class="d-flex input-group-sm align-items-center gap-1">
                                            <input type="number" step="0.01" min="0" class="form-control form-control-sm price-field" id="sale_price" name="sale_price" value="<?= old('sale_price', isset($product['sale_price']) ? $product['sale_price'] : '') ?>" style="flex:3; min-width:120px; font-size:0.95rem; font-weight:500;">
                                            <select class="form-select form-select-sm currency-select searchable" id="sale_currency" name="sale_currency" style="flex:0 0 auto; width:40px; font-size:0.7rem; padding:0.25rem 0.25rem;">
                                                <?php $sc = old('sale_currency', $product['sale_currency'] ?? ($default_currency ?? 'USD')); ?>
                                                <?php foreach (($currencies ?? []) as $c): ?>
                                                    <option value="<?= esc($c['code']) ?>" <?= $sc == $c['code'] ? 'selected' : '' ?>><?= esc($c['code']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if (!empty($product['id']) && isset($variants) && count($variants) > 0): ?>
                                            <button type="button" id="copyTemplateSaleBtn" class="btn btn-sm btn-primary mt-2" data-url="<?= base_url('/products/' . $product['id'] . '/copy-sale-to-variants') ?>" title="Sync sale price to all variants">
                                                <i class="fas fa-copy me-1"></i> Copy to Variants
                                            </button>
                                            <div id="copySaleFeedback" class="form-text small text-muted mt-2" aria-live="polite" style="max-width:300px; word-wrap:break-word;"></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="tab-attrs" role="tabpanel" aria-labelledby="tab-attrs-btn" tabindex="0">
        <div class="card border-0 shadow-sm attrs-dark" id="attributesCard" style="display: none;">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h5 class="card-title mb-0"><i class="bi bi-list-ul me-2"></i>Attributes &amp; Variants</h5>
                    <button type="button" class="btn btn-sm btn-primary" id="previewVariantsBtn" disabled>Preview &amp; Generate Variants</button>
                </div>
            </div>
            <div class="card-body">
                <div id="variantRemovalWarning" class="alert alert-warning small mb-2" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle attr-table mb-2">
                        <thead>
                            <tr>
                                <th style="width:35%">Attribute</th>
                                <th>Values</th>
                                <th style="width:60px"></th>
                            </tr>
                        </thead>
                        <tbody id="attributesList"></tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addAttributeRowBtn">Add a line</button>
                    <a href="<?= base_url('/product-attributes') ?>" class="btn btn-sm btn-link open-remote-modal" data-url="<?= base_url('/product-attributes') ?>">Manage Global Attributes</a>
                </div>

                <input type="hidden" name="attributes_definitions" id="attributes_definitions" value="<?= esc(old('attributes_definitions', $product['attributes_definitions'] ?? '[]')) ?>">
            </div>
        </div>

        <div class="alert alert-info small mb-0 d-flex align-items-start gap-2" id="attrsInfoNote" style="display:none;">
            <i class="bi bi-info-circle mt-1"></i>
            <div class="flex-grow-1">
                <div class="fw-semibold">Attributes are available for Variable products</div>
                <div class="text-muted">Go to <strong>Basics</strong> and set <strong>Variant Mode</strong> to <strong>Variable (has variants)</strong> to enable attribute options and generate variants.</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="tab" data-bs-target="#tab-basic" aria-controls="tab-basic">
                Go to Basics
            </button>
        </div>

        <div id="excludedValuesPanel" class="mt-3">
            <div class="card border-0 shadow-sm mb-2">
                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between">
                    <span class="fw-bold"><i class="bi bi-slash-circle me-2 text-danger"></i>Excluded Values</span>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Exclude actions">
                        <button type="button" class="btn btn-outline-primary" id="addExcludedValueBtn" onclick="window.CorelynkExcludeValue && window.CorelynkExcludeValue()"><i class="bi bi-plus"></i> Exclude Value</button>
                        <button type="button" class="btn btn-outline-primary" id="addExcludedComboBtn" onclick="window.CorelynkExcludeCombo && window.CorelynkExcludeCombo()"><i class="bi bi-diagram-3"></i> Exclude Combo</button>
                        <button type="button" class="btn btn-outline-success" id="addOnlyComboBtn" onclick="window.CorelynkOnlyCombo && window.CorelynkOnlyCombo()"><i class="bi bi-check2-circle"></i> Only Allow Combo</button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <div id="excludedValuesList" class="d-flex flex-wrap gap-2"></div>
                    <input type="hidden" name="excluded_combos" id="excluded_combos" value="<?= esc(old('excluded_combos', $product['excluded_combos'] ?? '[]')) ?>">
                    <div class="form-text text-muted mt-2">Use <b>Exclude Value</b> to block one value everywhere (e.g. Size=L). Use <b>Exclude Combo</b> to block one exact combo. Use <b>Only Allow Combo</b> for strict whitelist mode. In preview, use <b>Allow as Variant</b> on an excluded row to create a future exception.</div>
                    <div class="border rounded-3 mt-3 p-3 bg-light-subtle" id="comboBrowserPanel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                            <div>
                                <div class="fw-semibold">Combination Browser</div>
                                <div class="small text-muted">Browse all generated combinations grouped by Size or any attribute, then exclude or allow exact combos directly.</div>
                            </div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <label for="comboGroupBy" class="small text-muted mb-0">Group by</label>
                                <select id="comboGroupBy" class="form-select form-select-sm" style="min-width: 180px;"></select>
                                <input type="search" id="comboBrowserSearch" class="form-control form-control-sm" placeholder="Filter combinations" style="min-width: 220px;">
                            </div>
                        </div>
                        <div id="comboBrowserSummary" class="small text-muted mb-2"></div>
                        <div id="comboBrowserTree" class="combo-browser-tree"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($product) && $product): ?>
    <div class="tab-pane fade" id="tab-variants" role="tabpanel" aria-labelledby="tab-variants-btn" tabindex="0">
        <style>
            /* ── Compact variants table (mirrors standalone /product-variants view) ── */
            #tab-variants .pv-card-header {
                display: flex; align-items: center; justify-content: space-between;
                padding: 7px 14px; gap: .5rem; flex-wrap: wrap;
                border-bottom: 1px solid var(--cl-border);
            }
            #tab-variants .pv-card-title {
                font-size: .82rem; font-weight: 700; margin: 0; line-height: 1.2;
                color: var(--cl-text-primary);
            }
            #tab-variants .pv-card-sub { font-size: .68rem; color: var(--cl-text-muted); }
            #tab-variants .pv-total-badge {
                font-size: .68rem; padding: .18rem .55rem; border-radius: 999px;
                background: var(--cl-surface-alt); border: 1px solid var(--cl-border);
                color: var(--cl-text-secondary); white-space: nowrap;
            }
            /* ── Topbar (search + rows + pagination) ── */
            #tab-variants .pv-topbar {
                display: flex; align-items: center; justify-content: space-between;
                flex-wrap: wrap; gap: .4rem; padding: 6px 10px;
                border-bottom: 1px solid var(--cl-border);
                background: var(--cl-surface-alt);
            }
            #tab-variants .pv-search-wrap { position: relative; flex: 1 1 160px; max-width: 260px; }
            #tab-variants .pv-search-wrap .bi-search {
                position: absolute; left: 7px; top: 50%; transform: translateY(-50%);
                font-size: .7rem; color: var(--cl-text-muted); pointer-events: none;
            }
            #tab-variants .pv-search {
                width: 100%; font-size: .73rem; height: 26px;
                padding: .1rem .3rem .1rem 22px;
                border: 1px solid var(--cl-border); border-radius: 4px;
                background: var(--cl-surface); color: var(--cl-text-primary);
            }
            #tab-variants .pv-search:focus { outline: none; border-color: var(--cl-primary); box-shadow: 0 0 0 2px rgba(37,99,235,.12); }
            #tab-variants .pv-count { font-size: .72rem; color: var(--cl-text-muted); white-space: nowrap; }
            #tab-variants .pv-count strong { color: var(--cl-primary); }
            #tab-variants .pv-controls { display: flex; align-items: center; gap: .4rem; flex-wrap: nowrap; }
            #tab-variants .pv-controls .form-label { font-size: .72rem; color: var(--cl-text-muted); margin: 0; white-space: nowrap; }
            #tab-variants .pv-per-page {
                width: 60px; font-size: .73rem; padding: .1rem 1.4rem .1rem .35rem;
                height: 26px; border: 1px solid var(--cl-border); border-radius: 4px;
                background: var(--cl-surface); color: var(--cl-text-primary);
            }
            #tab-variants .pv-pagination .pagination { margin: 0; gap: 2px; }
            #tab-variants .pv-pagination .page-link {
                padding: .1rem .38rem; font-size: .72rem;
                min-width: 26px; height: 26px;
                display: inline-flex; align-items: center; justify-content: center;
            }
            /* ── Table ── */
            #tab-variants .variants-table { margin-bottom: 0; font-size: .76rem; }
            #tab-variants .variants-table thead th {
                font-size: .62rem !important; text-transform: uppercase; letter-spacing: .05em;
                color: var(--cl-text-muted); background: var(--cl-surface-alt);
                border-bottom: 1px solid var(--cl-border);
                padding: .26rem .45rem !important; white-space: nowrap; font-weight: 700 !important;
            }
            body.theme-dark #tab-variants .variants-table thead th {
                color: #94a3b8 !important; background: #162033 !important; border-bottom-color: #334155 !important;
            }
            body.theme-dark #tab-variants .variants-table tbody tr:nth-child(even) td { background: #0f1a2b !important; }
            body.theme-dark #tab-variants .variants-table tbody tr:nth-child(odd)  td { background: #1a2740 !important; }
            body.theme-dark #tab-variants .variants-table tbody tr:hover td { background: rgba(37,99,235,.1) !important; }
            #tab-variants .variants-table tbody td {
                padding: .18rem .45rem !important; border-bottom: 1px solid var(--cl-border-light);
                vertical-align: middle; font-size: .76rem !important;
            }
            #tab-variants .variants-table tbody tr:last-child td { border-bottom: none; }
            #tab-variants .variant-thumb {
                width: 24px; height: 24px; border-radius: 3px; border: 1px solid var(--cl-border);
                background: var(--cl-surface-alt); display: inline-flex; align-items: center;
                justify-content: center; color: var(--cl-text-muted); font-size: .68rem;
            }
            #tab-variants .variant-thumb img { width: 24px; height: 24px; object-fit: cover; border-radius: 3px; }
            #tab-variants .variant-attrs { font-size: .64rem; color: var(--cl-text-muted); margin-top: .05rem; line-height: 1.15; }
            #tab-variants .money-cell { font-variant-numeric: tabular-nums; white-space: nowrap; font-size: .76rem; }
            #tab-variants .currency-pill {
                display: inline-block; margin-left: .25rem; font-size: .57rem;
                border: 1px solid var(--cl-border); border-radius: 999px;
                padding: .05rem .28rem; color: var(--cl-text-muted); vertical-align: middle;
            }
            #tab-variants .actions-col { text-align: right; white-space: nowrap; }
            #tab-variants .var-act-btn {
                display: inline-flex; align-items: center; justify-content: center;
                width: 22px; height: 22px; border-radius: 4px;
                border: 1px solid var(--cl-border); background: var(--cl-surface);
                color: var(--cl-text-secondary); font-size: .7rem;
                text-decoration: none; cursor: pointer; transition: all .12s;
            }
            #tab-variants .var-act-btn:hover { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
            #tab-variants .var-act-btn + .var-act-btn { margin-left: 3px; }
            #tab-variants .pv-no-results { padding: 1.5rem; text-align: center; font-size: .78rem; color: var(--cl-text-muted); }
            #tab-variants .pv-more-menu {
                display: none; position: fixed; z-index: 1055;
                min-width: 140px; list-style: none; margin: 0; padding: .25rem;
                background: var(--cl-surface); border: 1px solid var(--cl-border);
                border-radius: .45rem;
                box-shadow: 0 8px 24px rgba(15,23,42,.12), 0 2px 6px rgba(15,23,42,.06);
            }
            #tab-variants .pv-more-menu.is-open { display: block; }
            #tab-variants .pv-more-menu li { list-style: none; }
            #tab-variants .pv-menu-item {
                display: flex; align-items: center; gap: .4rem; width: 100%;
                padding: .38rem .65rem; border-radius: .3rem; font-size: .78rem;
                font-weight: 500; color: #374151; background: none; border: none;
                cursor: pointer; text-decoration: none !important; white-space: nowrap;
            }
            #tab-variants .pv-menu-item:hover { background: var(--cl-surface-alt); color: #111; }
        </style>

        <?php
            // Pre-compute variant data into a JSON-serialisable array so JS can
            // filter and paginate without touching the server.
            $pvData = [];
            foreach (($variants ?? []) as $v) {
                $attrMap = [];
                if (!empty($v['attributes'])) {
                    $attrMap = is_string($v['attributes']) ? (json_decode($v['attributes'], true) ?? []) : (is_array($v['attributes']) ? $v['attributes'] : []);
                }
                $attrParts = [];
                if (is_array($attrMap)) {
                    foreach ($attrMap as $ak => $av) {
                        $attrParts[] = trim((string)$ak) . ': ' . trim((string)$av);
                    }
                }
                $imgName  = $v['image'] ?? '';
                $imgUrl   = $imgName ? base_url('uploads/variants/' . $imgName) : '';
                $priceVal = $v['price']  ?? $v['sale_price']  ?? null;
                $costVal  = $v['cost']   ?? $v['cost_price']  ?? null;
                $wgtVal   = $v['weight'] ?? null;
                $saleCur  = strtoupper(trim((string)($v['sale_currency'] ?? $product['sale_currency'] ?? 'PKR')));
                $costCur  = strtoupper(trim((string)($v['cost_currency'] ?? $product['cost_currency'] ?? $saleCur)));

                $pvData[] = [
                    'id'      => (int)$v['id'],
                    'art'     => $v['art_number'] ?? '',
                    'attrs'   => implode(' • ', $attrParts) ?: '—',
                    'imgUrl'  => $imgUrl,
                    'price'   => $priceVal !== null && $priceVal !== '' ? (float)$priceVal : null,
                    'cost'    => $costVal  !== null && $costVal  !== '' ? (float)$costVal  : null,
                    'weight'  => $wgtVal   !== null && $wgtVal   !== '' ? (float)$wgtVal   : null,
                    'saleCur' => $saleCur,
                    'costCur' => $costCur,
                    'wgtUnit' => $weightUnit ?? 'KG',
                ];
            }
            $pvTotal = count($pvData);
        ?>

        <div class="card border-0 shadow-sm">
            <div class="pv-card-header">
                <div>
                    <div class="pv-card-title"><i class="bi bi-grid-3x3-gap me-1"></i>Product Variants</div>
                    <div class="pv-card-sub">Individual variants, pricing, and images.</div>
                </div>
                <span class="pv-total-badge" id="pvTotalBadge">Total: <?= $pvTotal ?></span>
            </div>

            <?php if ($pvTotal === 0): ?>
                <div class="card-body">
                    <div class="alert alert-info mb-0">No variants found. Use the <strong>Attributes &amp; Variants</strong> tab to generate variants.</div>
                </div>
            <?php else: ?>
                <!-- Topbar: search + rows per page + pagination -->
                <div class="pv-topbar">
                    <div class="pv-search-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" id="pvSearchInput" class="pv-search" placeholder="Search art #, attributes…" autocomplete="off">
                    </div>
                    <span class="pv-count" id="pvCountLabel"></span>
                    <div class="pv-controls">
                        <label class="form-label" for="pvPerPage">Rows</label>
                        <select id="pvPerPage" class="pv-per-page">
                            <option value="25">25</option>
                            <option value="50" selected>50</option>
                            <option value="100">100</option>
                            <option value="200">200</option>
                        </select>
                        <nav class="pv-pagination" aria-label="Variants pagination">
                            <ul class="pagination pagination-sm mb-0" id="pvPagination"></ul>
                        </nav>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive" style="border-top:none">
                    <table class="table table-sm variants-table" id="pvTable">
                        <thead>
                            <tr>
                                <th>Art #</th>
                                <th style="width:36px">Img</th>
                                <th>Attributes</th>
                                <th class="text-end">Price<?php if (!empty($saleCurrencySymbol)): ?><span class="currency-pill"><?= esc($saleCurrencySymbol) ?></span><?php endif; ?></th>
                                <th class="text-end">Cost<?php if (!empty($costCurrencySymbol)): ?><span class="currency-pill"><?= esc($costCurrencySymbol) ?></span><?php endif; ?></th>
                                <th class="text-end">Weight<?php if (!empty($weightUnit)): ?><span class="currency-pill"><?= esc($weightUnit) ?></span><?php endif; ?></th>
                                <th class="actions-col" style="width:68px"></th>
                            </tr>
                        </thead>
                        <tbody id="pvTableBody"></tbody>
                    </table>
                    <div class="pv-no-results" id="pvNoResults" style="display:none">No variants match your search.</div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Context menu -->
        <ul class="pv-more-menu" id="pvMoreMenu">
            <li><a class="pv-menu-item" id="pvMenuEdit" href="#"><i class="bi bi-pencil" style="width:14px"></i> Edit Variant</a></li>
            <li><a class="pv-menu-item" id="pvMenuView" href="#"><i class="bi bi-eye" style="width:14px"></i> View Detail</a></li>
        </ul>

        <script>
        (function(){
            const ALL    = <?= json_encode($pvData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const BASE   = <?= json_encode(base_url('product-variants/')) ?>;
            const fmt    = (n, d) => n === null ? '-' : n.toLocaleString('en-US', {minimumFractionDigits: d, maximumFractionDigits: d});

            let filtered = ALL.slice();
            let page     = 1;
            let perPage  = 50;

            /* ── render ── */
            function render() {
                const total = filtered.length;
                const pages = Math.max(1, Math.ceil(total / perPage));
                if (page > pages) page = pages;
                const start = (page - 1) * perPage;
                const slice = filtered.slice(start, start + perPage);

                // count label
                const s = total > 0 ? start + 1 : 0;
                const e = Math.min(total, start + perPage);
                document.getElementById('pvCountLabel').innerHTML =
                    `Showing <strong>${s}</strong>–<strong>${e}</strong> of <strong>${total}</strong>`;
                document.getElementById('pvTotalBadge').textContent = `Total: ${ALL.length}`;

                // rows
                const tbody = document.getElementById('pvTableBody');
                tbody.innerHTML = slice.map(v => {
                    const img = v.imgUrl
                        ? `<span class="variant-thumb"><img src="${esc(v.imgUrl)}" alt=""></span>`
                        : `<span class="variant-thumb"><i class="bi bi-image"></i></span>`;
                    const priceHtml = v.price !== null
                        ? `${fmt(v.price,2)}<span class="currency-pill">${esc(v.saleCur)}</span>` : '-';
                    const costHtml  = v.cost  !== null
                        ? `${fmt(v.cost,2)}<span class="currency-pill">${esc(v.costCur)}</span>`  : '-';
                    const wgtHtml   = v.weight !== null
                        ? `${fmt(v.weight,3)}<span class="currency-pill">${esc(v.wgtUnit)}</span>` : '-';
                    return `<tr>
                        <td>${esc(v.art)}</td>
                        <td>${img}</td>
                        <td><div class="variant-attrs" style="font-size:.76rem;color:var(--cl-text-secondary)">${esc(v.attrs)}</div></td>
                        <td class="text-end money-cell">${priceHtml}</td>
                        <td class="text-end money-cell">${costHtml}</td>
                        <td class="text-end money-cell">${wgtHtml}</td>
                        <td class="actions-col">
                            <a href="${BASE}${v.id}/edit" class="var-act-btn" title="Edit variant"><i class="bi bi-eye"></i></a>
                            <button type="button" class="var-act-btn pv-more-trigger" data-vid="${v.id}" title="More"><i class="bi bi-three-dots-vertical"></i></button>
                        </td>
                    </tr>`;
                }).join('');

                // no-results
                document.getElementById('pvNoResults').style.display = slice.length ? 'none' : 'block';

                // rebind more buttons
                tbody.querySelectorAll('.pv-more-trigger').forEach(btn => {
                    btn.addEventListener('click', onMoreClick);
                });

                // pagination
                renderPagination(page, pages);
            }

            function renderPagination(cur, total) {
                const ul = document.getElementById('pvPagination');
                let html = '';
                html += `<li class="page-item ${cur<=1?'disabled':''}"><a class="page-link" data-p="${cur-1}">Prev</a></li>`;
                const win = 2, s = Math.max(1, cur-win), e = Math.min(total, cur+win);
                if (s > 1) html += `<li class="page-item"><a class="page-link" data-p="1">1</a></li>${s>2?'<li class="page-item disabled"><span class="page-link">…</span></li>':''}`;
                for (let i=s; i<=e; i++)
                    html += `<li class="page-item ${i===cur?'active':''}"><a class="page-link" data-p="${i}">${i}</a></li>`;
                if (e < total) html += `${e<total-1?'<li class="page-item disabled"><span class="page-link">…</span></li>':''}<li class="page-item"><a class="page-link" data-p="${total}">${total}</a></li>`;
                html += `<li class="page-item ${cur>=total?'disabled':''}"><a class="page-link" data-p="${cur+1}">Next</a></li>`;
                ul.innerHTML = html;
                ul.querySelectorAll('[data-p]').forEach(a => {
                    a.addEventListener('click', function(e){ e.preventDefault(); const p=+this.dataset.p; if(p>=1&&p<=total){page=p;render();} });
                });
            }

            /* ── search ── */
            let searchTimer;
            document.getElementById('pvSearchInput').addEventListener('input', function(){
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    const q = this.value.trim().toLowerCase();
                    filtered = q ? ALL.filter(v => v.art.toLowerCase().includes(q) || v.attrs.toLowerCase().includes(q)) : ALL.slice();
                    page = 1;
                    render();
                }, 180);
            });

            /* ── per-page ── */
            document.getElementById('pvPerPage').addEventListener('change', function(){
                perPage = +this.value; page = 1; render();
            });

            /* ── context menu ── */
            const menu    = document.getElementById('pvMoreMenu');
            const editLnk = document.getElementById('pvMenuEdit');
            const viewLnk = document.getElementById('pvMenuView');

            function onMoreClick(e) {
                e.stopPropagation();
                const vid = this.dataset.vid;
                editLnk.href = BASE + vid + '/edit';
                viewLnk.href = BASE + vid;
                const rect = this.getBoundingClientRect();
                menu.style.top  = (rect.bottom + 4 + window.scrollY) + 'px';
                menu.style.left = (rect.right  - 140  + window.scrollX) + 'px';
                menu.classList.toggle('is-open');
            }
            document.addEventListener('click', () => menu.classList.remove('is-open'));
            document.addEventListener('keydown', e => { if(e.key==='Escape') menu.classList.remove('is-open'); });

            /* ── escape helper ── */
            function esc(s) {
                return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            // Initial render
            render();
        })();
        </script>
    </div>
    <?php endif; ?>

    <div class="tab-pane fade" id="tab-media" role="tabpanel" aria-labelledby="tab-media-btn" tabindex="0">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0"><i class="bi bi-image me-2"></i>Product Images</h5>
            </div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-lg-8">
                        <label for="product_images" class="form-label">Upload Images</label>
                        <input type="file" class="form-control form-control-sm" id="product_images" name="product_images[]" multiple accept="image/*" onchange="previewImages()">
                    </div>
                    <div class="col-lg-4">
                        <div class="form-text">JPG/PNG/GIF, up to 5MB each.</div>
                    </div>
                </div>

                <div id="imagePreview" class="row g-2 mt-2"></div>

                <?php if (isset($product) && $product && !empty($product['images'])): ?>
                    <?php 
                    $existingImages = is_string($product['images']) ? json_decode($product['images'], true) : $product['images'];
                    if (!empty($existingImages) && is_array($existingImages)): 
                    ?>
                        <div class="mt-2">
                            <h6 class="mb-2">Current Images</h6>
                            <div class="row g-2" id="existingImages">
                                <?php foreach ($existingImages as $index => $image): ?>
                                    <div class="col-6 col-md-4 col-lg-3" data-image-name="<?= esc($image) ?>">
                                        <div class="card">
                                            <img src="<?= base_url('uploads/products/' . esc($image)) ?>" class="card-img-top" style="height: 110px; object-fit: cover; cursor: pointer;" onclick="openLightbox('<?= base_url('uploads/products/' . esc($image)) ?>')">
                                            <div class="card-body p-2">
                                                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeExistingImage(this, '<?= esc($image) ?>')">
                                                    <i class="bi bi-trash"></i> Remove
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?= form_close() ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('productForm');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        if (form.dataset.submitting === '1') {
            e.preventDefault();
            return;
        }
        form.dataset.submitting = '1';
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            btn.disabled = true;
        });
    });

    // Browser back-forward cache restore: allow submit again.
    window.addEventListener('pageshow', function () {
        form.dataset.submitting = '0';
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (btn) {
            btn.disabled = false;
        });
    });
});
</script>

<!-- Lightbox Modal -->
<div class="modal fade" id="lightboxModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Product Image</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="lightboxImage" src="" class="img-fluid" style="max-height: 70vh;">
            </div>
        </div>
    </div>
</div>

<script>
/* Removed manual generateCode function - product codes are provided by category preview and are readonly */

// Image preview functionality
function previewImages() {
    const fileInput = document.getElementById('product_images');
    const previewContainer = document.getElementById('imagePreview');
    
    // Clear previous previews
    previewContainer.innerHTML = '';
    
    if (fileInput.files) {
        Array.from(fileInput.files).forEach((file, index) => {
            if (file.type.startsWith('image/')) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    const colDiv = document.createElement('div');
                    colDiv.className = 'col-md-3';
                    
                    colDiv.innerHTML = `
                        <div class="card">
                            <img src="${e.target.result}" 
                                 class="card-img-top" 
                                 style="height: 150px; object-fit: cover; cursor: pointer;"
                                 onclick="openLightbox('${e.target.result}')">
                            <div class="card-body p-2">
                                <small class="text-muted">${file.name}</small>
                                <button type="button" 
                                        class="btn btn-danger btn-sm w-100 mt-1" 
                                        onclick="removePreviewImage(this, ${index})">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    `;
                    
                    previewContainer.appendChild(colDiv);
                };
                
                reader.readAsDataURL(file);
            }
        });
    }
}

// Remove preview image
function removePreviewImage(button, index) {
    const fileInput = document.getElementById('product_images');
    const dt = new DataTransfer();
    
    // Rebuild file list without the removed file
    Array.from(fileInput.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    
    fileInput.files = dt.files;
    button.closest('.col-md-3').remove();
}

// Remove existing image
function removeExistingImage(button, imageName) {
    if (confirm('Are you sure you want to remove this image?')) {
        // Add hidden input to mark image for deletion
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'remove_images[]';
        hiddenInput.value = imageName;
        document.getElementById('productForm').appendChild(hiddenInput);
        
        // Remove from UI
        const wrapper = button.closest('[data-image-name]') || button.closest('.col-md-3') || button.closest('.col-lg-3') || button.closest('.col-6');
        if (wrapper) wrapper.remove();
    }
}

// Lightbox functionality
function openLightbox(imageSrc) {
    document.getElementById('lightboxImage').src = imageSrc;
    const lightboxModal = new bootstrap.Modal(document.getElementById('lightboxModal'));
    lightboxModal.show();
}

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();


// Product type toggle: hide/show price fields and attributes card
document.addEventListener('DOMContentLoaded', function() {
    const productType = document.getElementById('product_type');
    const detailedType = document.getElementById('detailed_type');
    const attributesCard = document.getElementById('attributesCard');
    const attrsInfoNote = document.getElementById('attrsInfoNote');
    const templatePriceFields = document.querySelectorAll('.template-price-field');
    const simpleOnlyEls = document.querySelectorAll('.simple-only');
    const weightOnlyEls = document.querySelectorAll('.weight-only');

    function applyProductType(type) {
        if (type === 'variable') {
            // Template price fields should remain visible for variable products
            // They serve as defaults to copy to all variants
            templatePriceFields.forEach(r => r.classList.remove('d-none'));
            // hide simple-only fields (weight/vendor) because variants may differ
            simpleOnlyEls.forEach(el => {
                el.classList.add('d-none');
                el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = true; });
            });
            // show attributes card
            if (attributesCard) attributesCard.style.display = '';
            if (attrsInfoNote) attrsInfoNote.style.display = 'none';
        } else {
            templatePriceFields.forEach(r => r.classList.remove('d-none'));
            simpleOnlyEls.forEach(el => {
                el.classList.remove('d-none');
                el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = false; });
            });
            if (attributesCard) attributesCard.style.display = 'none';
            if (attrsInfoNote) attrsInfoNote.style.display = '';
        }
    }

    function applyDetailedType(type) {
        // Odoo-like behavior: services are not stocked/shipped; hide weight for services.
        const variantMode = productType ? productType.value : 'simple';
        const servicePolicyGroup = document.getElementById('servicePolicyGroup');
        const serviceOnlyEls = document.querySelectorAll('.service-only-field');
        const stockOnlyEls = document.querySelectorAll('.stock-only-field');

        if (variantMode === 'variable') {
            // variant mode already hides simple-only fields (including weight)
            // but still toggle service policy
            if (type === 'service') {
                serviceOnlyEls.forEach(el => el.style.display = '');
            } else {
                serviceOnlyEls.forEach(el => el.style.display = 'none');
            }
            return;
        }

        if (type === 'service') {
            // Hide weight fields for services
            weightOnlyEls.forEach(el => {
                el.classList.add('d-none');
                el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = true; });
            });
            // Hide stock-only fields
            stockOnlyEls.forEach(el => {
                el.classList.add('d-none');
                el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = true; });
            });
            // Show service-specific fields
            serviceOnlyEls.forEach(el => el.style.display = '');
        } else {
            // Show weight fields for storable/consumable
            weightOnlyEls.forEach(el => {
                el.classList.remove('d-none');
                el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = false; });
            });
            // Show stock-only fields
            stockOnlyEls.forEach(el => {
                el.classList.remove('d-none');
                el.querySelectorAll('input,select,textarea').forEach(i => { i.disabled = false; });
            });
            // Hide service-specific fields
            serviceOnlyEls.forEach(el => el.style.display = 'none');
        }
    }

    function applyTypeVisibility() {
        if (productType) applyProductType(productType.value);
        if (detailedType) applyDetailedType(detailedType.value);
    }

    if (productType) {
        applyTypeVisibility();
        productType.addEventListener('change', function(e) {
            const variantsCountEl = document.getElementById('variants_count');
            const variantsCount = variantsCountEl ? parseInt(variantsCountEl.value || '0', 10) : 0;
            if (this.value === 'simple' && variantsCount > 0) {
                alert('Cannot switch to Simple Product while variants exist. Remove variants first.');
                this.value = 'variable';
            }
            applyTypeVisibility();
        });
        const attrsTabBtn = document.getElementById('tab-attrs-btn');
        if (attrsTabBtn) {
            attrsTabBtn.addEventListener('shown.bs.tab', function() {
                applyTypeVisibility();
            });
        }
    }

    if (detailedType) {
        // ensure initial state is applied even if productType is missing
        applyTypeVisibility();
        detailedType.addEventListener('change', function() {
            applyTypeVisibility();
        });
    }

    // Attribute management (Odoo-like)
    const attributesList = document.getElementById('attributesList');
    const attributesDefInput = document.getElementById('attributes_definitions');
    const addAttributeRowBtn = document.getElementById('addAttributeRowBtn');
    const variantRemovalWarning = document.getElementById('variantRemovalWarning');
    const existingVariants = <?= json_encode($variants ?? []) ?>;

    let globalAttrNameToId = {};
    let globalAttrIdToName = {};
    const attributeCache = [];
    const attributeSearchTerm = {};
    const copyWeightBtn = document.getElementById('copyTemplateWeightBtn');
    const copyWeightFeedback = document.getElementById('copyWeightFeedback');
    const templateWeightInput = document.getElementById('weight');

    if (copyWeightBtn && templateWeightInput) {
        copyWeightBtn.addEventListener('click', function () {
            const url = copyWeightBtn.dataset.url;
            const weightValue = templateWeightInput.value.trim();
            if (weightValue === '') {
                if (copyWeightFeedback) {
                    copyWeightFeedback.textContent = 'Enter a weight before syncing variants.';
                    copyWeightFeedback.classList.add('text-danger');
                }
                return;
            }

            if (copyWeightFeedback) {
                copyWeightFeedback.textContent = 'Syncing weights…';
                copyWeightFeedback.classList.remove('text-danger', 'text-success');
            }
            copyWeightBtn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('weight', weightValue);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    const message = data.message || 'Failed to sync weights';
                    if (copyWeightFeedback) {
                        copyWeightFeedback.textContent = message;
                        copyWeightFeedback.classList.add('text-danger');
                    }
                    return;
                }
                if (copyWeightFeedback) {
                    copyWeightFeedback.textContent = data.message || 'Weights updated across all variants.';
                    copyWeightFeedback.classList.remove('text-danger');
                    copyWeightFeedback.classList.add('text-success');
                }
            })
            .catch(() => {
                if (copyWeightFeedback) {
                    copyWeightFeedback.textContent = 'Unable to reach the server.';
                    copyWeightFeedback.classList.add('text-danger');
                }
            })
            .finally(() => {
                copyWeightBtn.disabled = false;
            });
        });
    }

    // Copy Vendor to Variants
    const copyVendorBtn = document.getElementById('copyTemplateVendorBtn');
    const copyVendorFeedback = document.getElementById('copyVendorFeedback');
    const templateVendorSelect = document.getElementById('vendor_id');

    if (copyVendorBtn && templateVendorSelect) {
        copyVendorBtn.addEventListener('click', function () {
            const url = copyVendorBtn.dataset.url;
            const vendorValue = templateVendorSelect.value;

            if (copyVendorFeedback) {
                copyVendorFeedback.textContent = 'Syncing vendor…';
                copyVendorFeedback.classList.remove('text-danger', 'text-success');
            }
            copyVendorBtn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('vendor_id', vendorValue);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    const message = data.message || 'Failed to sync vendor';
                    if (copyVendorFeedback) {
                        copyVendorFeedback.textContent = message;
                        copyVendorFeedback.classList.add('text-danger');
                    }
                    return;
                }
                if (copyVendorFeedback) {
                    copyVendorFeedback.textContent = data.message || 'Variants will inherit the template vendor. Individual overrides preserved.';
                    copyVendorFeedback.classList.remove('text-danger');
                    copyVendorFeedback.classList.add('text-success');
                }
            })
            .catch(() => {
                if (copyVendorFeedback) {
                    copyVendorFeedback.textContent = 'Unable to reach the server.';
                    copyVendorFeedback.classList.add('text-danger');
                }
            })
            .finally(() => {
                copyVendorBtn.disabled = false;
            });
        });
    }

    // Copy Vendor Price PKR to Variants
    const copyVendorPkrBtn = document.getElementById('copyTemplateVendorPkrBtn');
    const copyVendorPkrFeedback = document.getElementById('copyVendorPkrFeedback');
    const templateVendorPkrInput = document.getElementById('vendor_price_pkr');

    if (copyVendorPkrBtn && templateVendorPkrInput) {
        copyVendorPkrBtn.addEventListener('click', function () {
            const url = copyVendorPkrBtn.dataset.url;
            const vendorPkrValue = templateVendorPkrInput.value !== undefined ? templateVendorPkrInput.value.trim() : '';
            const vendorPkrNum = parseFloat(vendorPkrValue);

            if (isNaN(vendorPkrNum)) {
                if (copyVendorPkrFeedback) {
                    copyVendorPkrFeedback.textContent = 'Enter a valid vendor price (PKR) before syncing variants.';
                    copyVendorPkrFeedback.classList.add('text-danger');
                }
                return;
            }

            if (copyVendorPkrFeedback) {
                copyVendorPkrFeedback.textContent = 'Syncing vendor prices (PKR)…';
                copyVendorPkrFeedback.classList.remove('text-danger', 'text-success');
            }
            copyVendorPkrBtn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('vendor_price_pkr', vendorPkrValue);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    const message = data.message || 'Failed to sync vendor prices (PKR)';
                    if (copyVendorPkrFeedback) {
                        copyVendorPkrFeedback.textContent = message;
                        copyVendorPkrFeedback.classList.add('text-danger');
                    }
                    return;
                }
                if (copyVendorPkrFeedback) {
                    copyVendorPkrFeedback.textContent = data.message || 'Vendor prices (PKR) updated across all variants.';
                    copyVendorPkrFeedback.classList.remove('text-danger');
                    copyVendorPkrFeedback.classList.add('text-success');
                }
            })
            .catch(() => {
                if (copyVendorPkrFeedback) {
                    copyVendorPkrFeedback.textContent = 'Unable to reach the server.';
                    copyVendorPkrFeedback.classList.add('text-danger');
                }
            })
            .finally(() => {
                copyVendorPkrBtn.disabled = false;
            });
        });
    }

    // Copy Cost Price to Variants
    const copyCostBtn = document.getElementById('copyTemplateCostBtn');
    const copyCostFeedback = document.getElementById('copyCostFeedback');
    const templateCostInput = document.getElementById('cost_price');

    if (copyCostBtn && templateCostInput) {
        copyCostBtn.addEventListener('click', function () {
            const url = copyCostBtn.dataset.url;
            const costValue = templateCostInput.value !== undefined ? templateCostInput.value.trim() : '';
            const costNum = parseFloat(costValue);

            if (isNaN(costNum)) {
                if (copyCostFeedback) {
                    copyCostFeedback.textContent = 'Enter a valid cost price before syncing variants.';
                    copyCostFeedback.classList.add('text-danger');
                }
                return;
            }

            if (copyCostFeedback) {
                copyCostFeedback.textContent = 'Syncing cost prices…';
                copyCostFeedback.classList.remove('text-danger', 'text-success');
            }
            copyCostBtn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('cost_price', costValue);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    const message = data.message || 'Failed to sync cost prices';
                    if (copyCostFeedback) {
                        copyCostFeedback.textContent = message;
                        copyCostFeedback.classList.add('text-danger');
                    }
                    return;
                }
                if (copyCostFeedback) {
                    copyCostFeedback.textContent = data.message || 'Cost prices updated across all variants.';
                    copyCostFeedback.classList.remove('text-danger');
                    copyCostFeedback.classList.add('text-success');
                }
            })
            .catch(() => {
                if (copyCostFeedback) {
                    copyCostFeedback.textContent = 'Unable to reach the server.';
                    copyCostFeedback.classList.add('text-danger');
                }
            })
            .finally(() => {
                copyCostBtn.disabled = false;
            });
        });
    }

    // Copy Sale Price to Variants
    const copySaleBtn = document.getElementById('copyTemplateSaleBtn');
    const copySaleFeedback = document.getElementById('copySaleFeedback');
    const templateSaleInput = document.getElementById('sale_price');

    if (copySaleBtn && templateSaleInput) {
        copySaleBtn.addEventListener('click', function () {
            const url = copySaleBtn.dataset.url;
            const saleValue = templateSaleInput.value !== undefined ? templateSaleInput.value.trim() : '';
            const saleNum = parseFloat(saleValue);

            if (isNaN(saleNum)) {
                if (copySaleFeedback) {
                    copySaleFeedback.textContent = 'Enter a valid sale price before syncing variants.';
                    copySaleFeedback.classList.add('text-danger');
                }
                return;
            }

            if (copySaleFeedback) {
                copySaleFeedback.textContent = 'Syncing sale prices…';
                copySaleFeedback.classList.remove('text-danger', 'text-success');
            }
            copySaleBtn.disabled = true;

            const payload = new URLSearchParams();
            payload.append('sale_price', saleValue);

            fetch(url, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: payload
            })
            .then(async (response) => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    const message = data.message || 'Failed to sync sale prices';
                    if (copySaleFeedback) {
                        copySaleFeedback.textContent = message;
                        copySaleFeedback.classList.add('text-danger');
                    }
                    return;
                }
                if (copySaleFeedback) {
                    copySaleFeedback.textContent = data.message || 'Sale prices updated across all variants.';
                    copySaleFeedback.classList.remove('text-danger');
                    copySaleFeedback.classList.add('text-success');
                }
            })
            .catch(() => {
                if (copySaleFeedback) {
                    copySaleFeedback.textContent = 'Unable to reach the server.';
                    copySaleFeedback.classList.add('text-danger');
                }
            })
            .finally(() => {
                copySaleBtn.disabled = false;
            });
        });
    }

    function norm(s) {
        return String(s || '').trim().toLowerCase();
    }

    function getDefs() {
        let defs = [];
        try { defs = JSON.parse(attributesDefInput.value || '[]'); } catch (e) { defs = []; }
        if (!Array.isArray(defs)) defs = [];
        return defs;
    }

    function setDefs(defs) {
        attributesDefInput.value = JSON.stringify(defs);
        renderSelectedAttributes();
        updatePreviewButtonState();
        updateVariantRemovalWarning();
        document.dispatchEvent(new CustomEvent('corelynk:attributes-definitions-changed', {
            detail: { defs: Array.isArray(defs) ? defs : [] }
        }));
    }

    // Expose helpers for safety/fallback use
    window.CorelynkAttributes = {
        getDefs,
        setDefs,
        renderSelectedAttributes,
        updatePreviewButtonState,
        syncMissingGlobalAttributes,
        escapeHtml,
    };
    window.CorelynkAddAttributeLine = function() {
        const defs = getDefs();
        defs.push({ name: '', values: [] });
        setDefs(defs);
    };

    function renderSelectedAttributes() {
        if (!attributesList) return;
        let defs = getDefs();
        if (!Array.isArray(defs) || defs.length === 0) {
            defs = [{ name: '', values: [] }];
            if (attributesDefInput) {
                attributesDefInput.value = JSON.stringify(defs);
            }
        }
        attributesList.innerHTML = '';

        defs.forEach((d, idx) => {
            const values = Array.isArray(d.values) ? d.values : [];
            const chips = values.map(v => {
                const ev = escapeHtml(v);
                const attrKey = norm(d.name || '');
                const valKey = norm(v || '');
                const usageCount = ((variantValueCounts[attrKey] || {})[valKey] || 0);
                const usageText = usageCount > 0 ? ` <span class="opacity-75">(${usageCount})</span>` : '';
                return `<span class="badge me-2 mb-2" style="cursor:default;">${ev}${usageText} <a href="#" class="text-danger text-decoration-none ms-1" data-action="remove-value" data-idx="${idx}" data-value="${ev}">&times;</a></span>`;
            }).join('');

            const tr = document.createElement('tr');
            const hasAttributeId = !!globalAttrNameToId[norm(d.name)];
            // Any named attribute row should allow value entry; missing global rows are created on demand.
            const canAddValues = !!String(d.name || '').trim();
            tr.innerHTML = `
                <td class="attr-cell">
                    <div class="attr-input-wrapper">
                        <input type="text" class="form-control form-control-sm attr-name-input" data-idx="${idx}" placeholder="Attribute" value="${escapeHtml(d.name || '')}" autocomplete="off">
                        <div class="attr-search-results list-group small mt-1" data-idx="${idx}" style="display:none;"></div>
                    </div>
                </td>
                <td class="values-cell">
                    <div class="values-chip-input">
                        <div class="d-flex align-items-center justify-content-between mb-1" style="min-height:1.4rem">
                            <div class="values-chips" data-idx="${idx}">${chips || '<span class="text-muted small">No values added yet.</span>'}</div>
                            ${values.length > 0 ? `<button type="button" class="btn btn-xs btn-outline-secondary ms-2 flex-shrink-0" data-action="clear-values" data-idx="${idx}" title="Clear all values" style="font-size:.65rem;padding:1px 6px;white-space:nowrap"><i class="bi bi-x-circle me-1"></i>Clear All</button>` : ''}
                        </div>
                        <div class="value-input-wrapper">
                            <div class="input-group input-group-sm">
                                <input type="text" class="form-control attr-value-input" data-idx="${idx}" placeholder="Search or create value" autocomplete="off" ${canAddValues ? '' : 'disabled'}>
                            </div>
                            <div class="value-search-results list-group small mt-1" data-idx="${idx}" style="display:none;"></div>
                        </div>
                    </div>
                </td>
                <td class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-action="remove-attr" data-idx="${idx}">&times;</button>
                </td>
            `;
            attributesList.appendChild(tr);
        });
    }

    function buildVariantValueCounts(variants) {
        const counts = {};
        (variants || []).forEach(v => {
            let attrs = {};
            if (v && v.attributes) {
                try {
                    attrs = typeof v.attributes === 'string' ? JSON.parse(v.attributes) : (v.attributes || {});
                } catch (e) {
                    attrs = {};
                }
            }
            if (!attrs || typeof attrs !== 'object') return;
            Object.keys(attrs).forEach(k => {
                const attr = norm(k);
                const val = norm(attrs[k]);
                if (!attr || !val) return;
                if (!counts[attr]) counts[attr] = {};
                counts[attr][val] = (counts[attr][val] || 0) + 1;
            });
        });
        return counts;
    }

    function mergeDefsWithVariantValues(defs, variants) {
        const list = Array.isArray(defs) ? defs : [];
        const out = list.map(d => ({
            name: String((d && d.name) || '').trim(),
            values: Array.isArray(d && d.values) ? d.values.map(v => String(v || '').trim()).filter(Boolean) : []
        }));
        const byName = {};
        out.forEach((d, i) => { if (d.name) byName[norm(d.name)] = i; });

        let changed = false;
        (variants || []).forEach(v => {
            let attrs = {};
            if (v && v.attributes) {
                try {
                    attrs = typeof v.attributes === 'string' ? JSON.parse(v.attributes) : (v.attributes || {});
                } catch (e) {
                    attrs = {};
                }
            }
            if (!attrs || typeof attrs !== 'object') return;
            Object.keys(attrs).forEach(k => {
                const name = String(k || '').trim();
                const value = String(attrs[k] || '').trim();
                if (!name || !value) return;
                const nk = norm(name);
                if (typeof byName[nk] === 'number') {
                    const idx = byName[nk];
                    const vals = Array.isArray(out[idx].values) ? out[idx].values : [];
                    if (!vals.some(x => norm(x) === norm(value))) {
                        vals.push(value);
                        out[idx].values = vals;
                        changed = true;
                    }
                } else {
                    out.push({ name, values: [value] });
                    byName[nk] = out.length - 1;
                    changed = true;
                }
            });
        });

        return { defs: out, changed };
    }

    function updatePreviewButtonState() {
        const btn = document.getElementById('previewVariantsBtn');
        if (!btn || !attributesDefInput) return;
        let defs = [];
        try { defs = JSON.parse(attributesDefInput.value || '[]'); } catch (e) { defs = []; }
        const hasUsable = Array.isArray(defs) && defs.some(d => d && d.name && Array.isArray(d.values) && d.values.length > 0);
        btn.disabled = !hasUsable;
        btn.title = hasUsable ? '' : 'Add at least one attribute with values first';
    }

    function normalizeDefs(raw) {
        let defs = [];
        try { defs = JSON.parse(raw || '[]'); } catch (e) { defs = []; }
        if (!Array.isArray(defs)) defs = [];
        const map = {};
        defs.forEach(d => {
            const name = String(d.name || '').trim();
            if (!name) return;
            const values = (Array.isArray(d.values) ? d.values : []).map(v => String(v || '').trim()).filter(Boolean);
            map[name] = values;
        });
        return map;
    }

    function updateVariantRemovalWarning() {
        if (!variantRemovalWarning || !attributesDefInput) return;
        if (!Array.isArray(existingVariants) || existingVariants.length === 0) {
            variantRemovalWarning.style.display = 'none';
            variantRemovalWarning.innerHTML = '';
            return;
        }

        const map = normalizeDefs(attributesDefInput.value || '[]');
        const invalid = [];

        existingVariants.forEach(v => {
            let attrs = {};
            if (v && v.attributes) {
                try { attrs = typeof v.attributes === 'string' ? JSON.parse(v.attributes) : v.attributes; } catch (e) { attrs = {}; }
            }
            if (!attrs || typeof attrs !== 'object') attrs = {};
            let isInvalid = false;
            Object.keys(attrs).forEach(k => {
                const key = String(k || '').trim();
                const val = String(attrs[k] || '').trim();
                if (!key) return;
                if (!map[key]) { isInvalid = true; return; }
                if (!map[key].includes(val)) { isInvalid = true; return; }
            });
            if (isInvalid) {
                invalid.push(v.art_number || v.name || ('#' + v.id));
            }
        });

        if (!invalid.length) {
            variantRemovalWarning.style.display = 'none';
            variantRemovalWarning.innerHTML = '';
            return;
        }

        const sample = invalid.slice(0, 5).join(', ');
        const more = invalid.length > 5 ? ` and ${invalid.length - 5} more` : '';
        variantRemovalWarning.style.display = '';
        variantRemovalWarning.innerHTML = `Removing attribute values will delete <strong>${invalid.length}</strong> variant(s): <strong>${sample}${more}</strong>. This will be blocked if stock or orders exist.`;
    }

    function ensureAttributeExistsByName(name, autoCreate = false) {
        name = String(name || '').trim();
        if (!name) return Promise.reject(new Error('Name required'));
        const id = globalAttrNameToId[norm(name)];
        if (id) return Promise.resolve({ id: id, name: globalAttrIdToName[id] || name, created: false });

        // Double-check with server before creating, in case local cache is stale.
        const searchUrl = '<?= base_url('/product-attributes/search') ?>?q=' + encodeURIComponent(name);
        return fetch(searchUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                const list = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                const exact = list.find(a => norm(a.name) === norm(name));
                if (exact && exact.id) {
                    const exactId = String(exact.id).trim();
                    const exactName = String(exact.name || name).trim();
                    globalAttrNameToId[norm(exactName)] = exactId;
                    globalAttrIdToName[exactId] = exactName;
                    return { id: exactId, name: exactName, created: false };
                }

        if (!autoCreate && !confirm('Attribute "' + name + '" does not exist. Create it now?')) {
            return Promise.reject(new Error('Not created'));
        }

        const params = new URLSearchParams();
        params.append('name', name);

        return fetch('<?= base_url('/product-attributes/store') ?>', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: params
        })
            .then(r => r.json())
            .then(d => {
                if (!d || d.success !== true || !d.attribute) {
                    throw new Error((d && d.message) ? d.message : 'Failed to create attribute');
                }
                const attr = d.attribute;
                const createdName = String(attr.name || name).trim();
                const createdId = String(attr.id || d.id || '').trim();
                if (!createdId) {
                    return { id: null, name: createdName, created: true };
                }
                globalAttrNameToId[norm(createdName)] = createdId;
                globalAttrIdToName[createdId] = createdName;
                return { id: createdId, name: createdName, created: true };
            });
            });
    }

    function addAttributeToDefs(attrName) {
        const defs = getDefs();
        const exists = defs.some(d => norm(d.name) === norm(attrName));
        if (exists) return;
        defs.push({ name: attrName, values: [] });
        setDefs(defs);
    }

    function getExistingValues(idx) {
        const defs = getDefs();
        const entry = defs[idx];
        if (!entry || !Array.isArray(entry.values)) return [];
        return entry.values.map(v => norm(v));
    }

    function hideValueDropdown(idx) {
        const container = attributesList ? attributesList.querySelector(`.value-search-results[data-idx="${idx}"]`) : null;
        if (!container) return;
        container.innerHTML = '';
        container.style.display = 'none';
    }

    let activeValueDropdownIdx = null;

    function clearValueDropdowns() {
        if (!attributesList) return;
        attributesList.querySelectorAll('.value-search-results').forEach(node => {
            node.innerHTML = '';
            node.style.display = 'none';
        });
        activeValueDropdownIdx = null;
    }

    function refreshValueSuggestions(attributeId, q, idx) {
        const container = attributesList ? attributesList.querySelector(`.value-search-results[data-idx="${idx}"]`) : null;
        if (!container) return;
        container.innerHTML = '';
        if (!attributeId) { container.style.display = 'none'; return; }
        activeValueDropdownIdx = idx;
        const qn = norm(q || '');
        const url = '<?= base_url('/product-attributes') ?>/' + encodeURIComponent(attributeId) + '/values?q=' + encodeURIComponent(q || '');
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                const list = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                const selected = getExistingValues(idx);
                const unique = list.filter(v => v && !selected.includes(norm(v)));
                let hasExact = unique.some(v => norm(v) === qn && qn);

                unique.forEach(v => {
                    const item = document.createElement('div');
                    item.className = 'list-group-item';
                    item.setAttribute('data-value', v);
                    item.textContent = v;
                    container.appendChild(item);
                });

                if (q && !hasExact && !selected.includes(qn)) {
                    const createItem = document.createElement('div');
                    createItem.className = 'list-group-item text-primary';
                    createItem.setAttribute('data-create', '1');
                    createItem.setAttribute('data-value', q);
                    createItem.textContent = 'Create "' + q + '"';
                    container.appendChild(createItem);
                }

                container.style.display = container.children.length ? 'block' : 'none';
            })
            .catch(() => {
                container.style.display = 'none';
            });
    }

    function valueExistsInGlobal(attributeId, value) {
        const url = '<?= base_url('/product-attributes') ?>/' + encodeURIComponent(attributeId) + '/values?q=' + encodeURIComponent(value || '');
        return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                const list = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                const nv = norm(value);
                return list.some(v => norm(v) === nv);
            })
            .catch(() => false);
    }

    function addValueToDefs(attrIdx, value) {
        const defs = getDefs();
        const idx = parseInt(attrIdx);
        if (isNaN(idx) || !defs[idx]) return;
        const v = String(value || '').trim();
        if (!v) return;
        const vals = Array.isArray(defs[idx].values) ? defs[idx].values : [];
        if (vals.some(x => norm(x) === norm(v))) return;
        vals.push(v);
        defs[idx].values = vals;
        setDefs(defs);
    }

    function renderAttrSearchResults(list, q, idx) {
        const container = attributesList ? attributesList.querySelector(`.attr-search-results[data-idx="${idx}"]`) : null;
        if (!container) return;
        container.innerHTML = '';
        const qn = norm(q || '');
        let hasExact = false;

        (list || []).forEach(a => {
            const name = String(a.name || '').trim();
            const id = String(a.id || '').trim();
            if (!name || !id) return;
            globalAttrNameToId[norm(name)] = id;
            globalAttrIdToName[id] = name;
            if (norm(name) === qn && qn) hasExact = true;

            const item = document.createElement('div');
            item.className = 'list-group-item';
            item.setAttribute('data-id', id);
            item.setAttribute('data-name', name);
            item.innerHTML = `<span>${escapeHtml(name)}</span>`;
            container.appendChild(item);
        });

        if (q && !hasExact) {
            const createItem = document.createElement('div');
            createItem.className = 'list-group-item';
            createItem.setAttribute('data-create', '1');
            createItem.setAttribute('data-name', q);
            createItem.innerHTML = `<span class="me-2">${escapeHtml(q)}</span><a href="#" class="small text-primary" data-action="create-attr">Create Now</a>`;
            container.appendChild(createItem);
        }

        container.style.display = container.children.length ? 'block' : 'none';
    }
    function mergeAttributeCache(items) {
        if (!Array.isArray(items)) return;
        const index = {};
        attributeCache.forEach(item => {
            if (!item || !item.id) return;
            index[String(item.id)] = item;
        });
        items.forEach(item => {
            if (!item) return;
            const id = String(item.id || '');
            const name = String(item.name || '').trim();
            if (!id || !name) return;
            index[id] = { id, name };
        });
        const merged = Object.values(index).sort((a, b) => (a.name || '').localeCompare(b.name || '', undefined, { sensitivity: 'base' }));
        attributeCache.length = 0;
        merged.forEach(item => attributeCache.push(item));
    }

    function loadAttributeCache() {
        fetch('<?= base_url('/product-attributes/list') ?>', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                if (json && json.success && Array.isArray(json.data)) {
                    mergeAttributeCache(json.data);
                }
            })
            .catch(() => {});
    }

    function syncMissingGlobalAttributes(defs) {
        const list = Array.isArray(defs) ? defs : [];
        const names = [];
        const seen = {};
        list.forEach(d => {
            const name = String((d && d.name) || '').trim();
            const key = norm(name);
            if (!name || seen[key]) return;
            seen[key] = true;
            names.push(name);
        });

        return names.reduce((chain, name) => {
            return chain.then(() => ensureAttributeExistsByName(name, true).catch(() => null));
        }, Promise.resolve());
    }

    function performAttributeSearch(q, idx) {
        const trimmed = String(q || '').trim();
        attributeSearchTerm[idx] = trimmed;
        const container = attributesList ? attributesList.querySelector(`.attr-search-results[data-idx="${idx}"]`) : null;
        if (!container) return;
        if (!trimmed) {
            container.style.display = 'none';
            container.innerHTML = '';
            return;
        }

        const qn = norm(trimmed);
        const localMatches = attributeCache.filter(a => norm(a.name).includes(qn)).slice(0, 20);
        renderAttrSearchResults(localMatches, trimmed, idx);

        const url = '<?= base_url('/product-attributes/search') ?>?q=' + encodeURIComponent(trimmed);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                const list = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                mergeAttributeCache(list);
                if (attributeSearchTerm[idx] !== trimmed) return;
                renderAttrSearchResults(list, trimmed, idx);
            })
            .catch(() => {});
    }

    // Helper: resolve attribute ID from cache (or fetch by name) then show value suggestions
    function resolveAndRefreshValueSuggestions(d, query, idx) {
        if (!d || !d.name) return;
        const cached = globalAttrNameToId[norm(d.name)] || null;
        if (cached) {
            refreshValueSuggestions(cached, query, idx);
            return;
        }
        // ID not cached yet — look it up from the search API without creating anything
        const url = '<?= base_url('/product-attributes/search') ?>?q=' + encodeURIComponent(d.name);
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(r => r.json())
            .then(json => {
                const list = (json && json.success && Array.isArray(json.data)) ? json.data : [];
                list.forEach(a => {
                    const n = String(a.name || '').trim();
                    const id = String(a.id || '').trim();
                    if (n && id) {
                        globalAttrNameToId[norm(n)] = id;
                        globalAttrIdToName[id] = n;
                    }
                });
                mergeAttributeCache(list);
                const resolvedId = globalAttrNameToId[norm(d.name)] || null;
                refreshValueSuggestions(resolvedId, query, idx);
            })
            .catch(() => {});
    }

    // Wire UI

    if (attributesList) {
        attributesList.addEventListener('click', function(e){
            const target = e.target;
            if (!target) return;
            const action = target.getAttribute('data-action');
            const defs = getDefs();

            if (action === 'clear-values') {
                e.preventDefault();
                const idx = parseInt(target.getAttribute('data-idx'));
                if (isNaN(idx) || !defs[idx]) return;
                const attrName = defs[idx].name || 'this attribute';
                if (!confirm(`Clear all ${defs[idx].values.length} value(s) from "${attrName}"?\n\nValues used by existing variants cannot be removed.`)) return;
                // Filter out only values not used by any variant
                const attrKey = norm(defs[idx].name || '');
                const usedVals = Object.keys(variantValueCounts[attrKey] || {});
                const before = defs[idx].values.length;
                defs[idx].values = (defs[idx].values || []).filter(v => usedVals.includes(norm(v)));
                const removed = before - defs[idx].values.length;
                setDefs(defs);
                if (defs[idx].values.length > 0) {
                    alert(`${removed} value(s) cleared. ${defs[idx].values.length} value(s) are still in use by existing variants and were kept.`);
                }
                return;
            }

            if (action === 'remove-value') {
                e.preventDefault();
                const idx = parseInt(target.getAttribute('data-idx'));
                if (isNaN(idx) || !defs[idx]) return;
                const value = target.getAttribute('data-value') || '';
                const attributeName = defs[idx].name || '';
                const productId = document.getElementById('product_id')?.value || document.querySelector('input[name="id"]')?.value || '<?= $product['id'] ?? 0 ?>';

                // Get actual product ID from form or current URL
                const actualProductId = parseInt(productId) || 0;
                if (!actualProductId) {
                    // If creating new product, allow deletion without checking
                    const vals = Array.isArray(defs[idx].values) ? defs[idx].values : [];
                    defs[idx].values = vals.filter(v => norm(v) !== norm(value));
                    setDefs(defs);
                    return;
                }

                // For existing products with variants, validate before deletion
                fetch('<?= base_url('/products/validate-attribute-deletion') ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: new URLSearchParams({
                        product_id: actualProductId,
                        attribute_name: attributeName,
                        attribute_value: value
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Cannot Delete Attribute:\n\n' + data.message);
                        return;
                    }
                    // Safe to delete
                    const vals = Array.isArray(defs[idx].values) ? defs[idx].values : [];
                    defs[idx].values = vals.filter(v => norm(v) !== norm(value));
                    setDefs(defs);
                })
                .catch(error => {
                    console.error('Validation error:', error);
                    alert('Error validating attribute deletion. Please try again.');
                });
                return;
            }

            if (action === 'remove-attr') {
                e.preventDefault();
                const idx = parseInt(target.getAttribute('data-idx'));
                if (isNaN(idx) || !defs[idx]) return;
                defs.splice(idx, 1);
                setDefs(defs);
                return;
            }

            const attrItem = target.closest('.attr-search-results .list-group-item');
            if (attrItem) {
                const idx = parseInt(attrItem.closest('.attr-search-results').getAttribute('data-idx'));
                if (isNaN(idx) || !defs[idx]) return;
                const name = attrItem.getAttribute('data-name') || '';
                const create = attrItem.getAttribute('data-create') === '1';
                if (!name) return;
                const container = attributesList.querySelector(`.attr-search-results[data-idx="${idx}"]`);

                // If this is a Create Now row, only create when the link itself is clicked.
                if (create && !(target && target.getAttribute && target.getAttribute('data-action') === 'create-attr')) {
                    const updated = getDefs();
                    if (!updated[idx]) return;
                    const nameChanged = norm(updated[idx].name) !== norm(name);
                    updated[idx].name = name;
                    if (nameChanged) updated[idx].values = [];
                    setDefs(updated);
                    if (container) { container.style.display = 'none'; container.innerHTML = ''; }
                    return;
                }

                if (create) {
                    ensureAttributeExistsByName(name, true)
                        .then(res => {
                            const updated = getDefs();
                            if (!updated[idx]) return;
                            const nameChanged = norm(updated[idx].name) !== norm(res.name);
                            updated[idx].name = res.name;
                            if (nameChanged) updated[idx].values = [];
                            setDefs(updated);
                            if (container) { container.style.display = 'none'; container.innerHTML = ''; }
                        })
                        .catch(() => {});
                    return;
                }

                // Existing attribute: just select it (no create)
                const updated = getDefs();
                if (!updated[idx]) return;
                const nameChanged = norm(updated[idx].name) !== norm(name);
                updated[idx].name = name;
                if (nameChanged) updated[idx].values = [];
                setDefs(updated);
                if (container) { container.style.display = 'none'; container.innerHTML = ''; }
                return;
            }

            const valueItem = target.closest('.value-search-results .list-group-item');
            if (valueItem) {
                e.preventDefault();
                // handled on mousedown to avoid blur-related dropdown close
                return;
            }
        });

        attributesList.addEventListener('input', function(e){
            const target = e.target;
            if (!target) return;
            if (target.classList.contains('attr-name-input')) {
                const idx = parseInt(target.getAttribute('data-idx'));
                performAttributeSearch(target.value || '', idx);
            }

            if (target.classList.contains('attr-value-input')) {
                const idx = parseInt(target.getAttribute('data-idx'));
                const defs = getDefs();
                const d = defs[idx];
                if (!d) return;
                resolveAndRefreshValueSuggestions(d, target.value || '', idx);
            }
        });

        attributesList.addEventListener('focusout', function(e){
            const target = e.target;
            if (!target) return;
            if (target.classList.contains('attr-value-input')) {
                setTimeout(() => {
                    if (document.activeElement && document.activeElement.closest && document.activeElement.closest('.value-search-results')) {
                        return;
                    }
                    if (document.activeElement && document.activeElement.classList.contains('attr-value-input')) {
                        return;
                    }
                    hideValueDropdown(parseInt(target.getAttribute('data-idx')));
                }, 0);
            }
        });

        attributesList.addEventListener('focusin', function(e){
            const target = e.target;
            if (!target) return;
            if (target.classList.contains('attr-value-input')) {
                const idx = parseInt(target.getAttribute('data-idx'));
                const defs = getDefs();
                const d = defs[idx];
                if (!d) return;
                resolveAndRefreshValueSuggestions(d, target.value || '', idx);
            }
        });

        document.addEventListener('mousedown', function(e){
            if (!attributesList) return;
            const target = e.target;
            if (!target) return;
            const attrItem = target.closest && target.closest('.attr-search-results .list-group-item');
            if (attrItem) {
                e.preventDefault();
                e.stopPropagation();
                const idx = parseInt(attrItem.closest('.attr-search-results').getAttribute('data-idx'));
                if (isNaN(idx)) return;
                const name = attrItem.getAttribute('data-name') || '';
                const create = attrItem.getAttribute('data-create') === '1';
                if (!name) return;
                if (!create) {
                    const input = attributesList.querySelector(`.attr-name-input[data-idx="${idx}"]`);
                    if (input) {
                        input.value = name;
                        input.focus();
                        if (typeof input.setSelectionRange === 'function') {
                            input.setSelectionRange(name.length, name.length);
                        }
                    }
                    const updated = getDefs();
                    if (!updated[idx]) return;
                    const nameChanged = norm(updated[idx].name) !== norm(name);
                    updated[idx].name = name;
                    if (nameChanged) updated[idx].values = [];
                    setDefs(updated);
                    const container = attributesList.querySelector(`.attr-search-results[data-idx="${idx}"]`);
                    if (container) { container.style.display = 'none'; container.innerHTML = ''; }
                }
                return;
            }
            const valueItem = target.closest && target.closest('.value-search-results .list-group-item');
            if (!valueItem) return;
            e.preventDefault();
            e.stopPropagation();
            const idx = parseInt(valueItem.closest('.value-search-results').getAttribute('data-idx'));
            if (isNaN(idx)) return;
            const value = valueItem.getAttribute('data-value') || '';
            if (!value) return;
            const defs = getDefs();
            if (!defs[idx]) return;
            const d = defs[idx];
            const attributeId = globalAttrNameToId[norm(d.name)] || null;
            const isCreate = valueItem.getAttribute('data-create') === '1';
            const input = attributesList.querySelector(`.attr-value-input[data-idx="${idx}"]`);
            if (input) {
                input.disabled = false;
                input.value = value;
                input.focus();
                if (typeof input.setSelectionRange === 'function') {
                    input.setSelectionRange(value.length, value.length);
                }
            }
            const proceed = () => {
                addValueToDefs(idx, value);
                if (input) input.value = '';
                if (attributeId) refreshValueSuggestions(attributeId, '', idx);
                hideValueDropdown(idx);
            };

            if (!isCreate || !attributeId) { proceed(); return; }
            const params = new URLSearchParams();
            params.append('value', value);
            fetch('<?= base_url('/product-attributes') ?>/' + encodeURIComponent(attributeId) + '/value/add', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            }).then(r => r.json()).then(json => {
                if (!json || json.success !== true) {
                    alert('Failed to create value: ' + (json && json.message ? json.message : 'Unknown'));
                    return;
                }
                proceed();
            }).catch(() => alert('Request failed'));
        }, true);

        document.addEventListener('click', function(e){
            if (!attributesList) return;
            const target = e.target;
            if (!target) return;

            const valueItem = target.closest && target.closest('.value-search-results .list-group-item');
            if (valueItem) {
                e.preventDefault();
                const idx = parseInt(valueItem.closest('.value-search-results').getAttribute('data-idx'));
                if (isNaN(idx)) return;
                const value = valueItem.getAttribute('data-value') || '';
                if (!value) return;
                const input = attributesList.querySelector(`.attr-value-input[data-idx="${idx}"]`);
                const addBtn = attributesList.querySelector(`[data-action="add-value"][data-idx="${idx}"]`);
                if (input) {
                    input.disabled = false;
                    input.value = value;
                    input.focus();
                    if (typeof input.setSelectionRange === 'function') {
                        input.setSelectionRange(value.length, value.length);
                    }
                }
                if (addBtn) addBtn.disabled = false;
                hideValueDropdown(idx);
                return;
            }

            if (target.closest && (target.closest('.attr-value-input') || target.closest('.value-search-results'))) {
                return;
            }
            clearValueDropdowns();
        });

        attributesList.addEventListener('change', function(e){
            const target = e.target;
            if (!target) return;
            if (target.classList.contains('attr-name-input')) {
                const idx = parseInt(target.getAttribute('data-idx'));
                const defs = getDefs();
                if (isNaN(idx) || !defs[idx]) return;
                const name = (target.value || '').trim();
                if (!name) {
                    defs[idx].name = '';
                    defs[idx].values = [];
                    setDefs(defs);
                    return;
                }

                const dup = defs.some((d, i) => i !== idx && norm(d.name) === norm(name));
                if (dup) {
                    alert('This attribute is already added.');
                    target.value = defs[idx].name || '';
                    return;
                }

                const updated = getDefs();
                if (!updated[idx]) return;
                const nameChanged = norm(updated[idx].name) !== norm(name);
                updated[idx].name = name;
                if (nameChanged) updated[idx].values = [];
                setDefs(updated);
            }
        });

        attributesList.addEventListener('keydown', function(e){
            const target = e.target;
            if (!target) return;
            if (target.classList.contains('attr-value-input') && e.key === 'Enter') {
                e.preventDefault();
                const idx = parseInt(target.getAttribute('data-idx'));
                const value = (target.value || '').trim();
                if (!value) return;
                const defs = getDefs();
                const d = defs[idx];
                if (!d) return;
                const resolveAttribute = () => {
                    const cachedId = globalAttrNameToId[norm(d.name)] || null;
                    if (cachedId) {
                        return Promise.resolve(cachedId);
                    }
                    return ensureAttributeExistsByName(d.name, true).then(res => res && res.id ? res.id : null).catch(() => null);
                };

                const proceed = () => {
                    addValueToDefs(idx, value);
                    target.value = '';
                    const currentId = globalAttrNameToId[norm(d.name)] || null;
                    if (currentId) refreshValueSuggestions(currentId, '', idx);
                };

                resolveAttribute().then(attributeId => {
                if (!attributeId) { proceed(); return; }
                valueExistsInGlobal(attributeId, value).then(exists => {
                    if (exists) { proceed(); return; }
                    const params = new URLSearchParams();
                    params.append('value', value);
                    fetch('<?= base_url('/product-attributes') ?>/' + encodeURIComponent(attributeId) + '/value/add', {
                        method: 'POST',
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params
                    }).then(r => r.json()).then(json => {
                        if (!json || json.success !== true) {
                            alert('Failed to create value: ' + (json && json.message ? json.message : 'Unknown'));
                            return;
                        }
                        proceed();
                    }).catch(() => alert('Request failed'));
                });
                });
            }
        });

        attributesList.addEventListener('click', function(e){
            const target = e.target;
            if (!target) return;
            if (target.getAttribute('data-action') !== 'add-value') return;
            e.preventDefault();
            const idx = parseInt(target.getAttribute('data-idx'));
            const defs = getDefs();
            const d = defs[idx];
            if (!d) return;
            const input = attributesList.querySelector(`.attr-value-input[data-idx="${idx}"]`);
            const value = input ? (input.value || '').trim() : '';
            const resolveAttribute = () => {
                const cachedId = globalAttrNameToId[norm(d.name)] || null;
                if (cachedId) {
                    return Promise.resolve(cachedId);
                }
                return ensureAttributeExistsByName(d.name, true).then(res => res && res.id ? res.id : null).catch(() => null);
            };

            if (!value) {
                if (input) {
                    input.focus();
                }
                const cachedId = globalAttrNameToId[norm(d.name)] || null;
                if (cachedId) refreshValueSuggestions(cachedId, '', idx);
                return;
            }
            const proceed = () => {
                addValueToDefs(idx, value);
                if (input) input.value = '';
                const currentId = globalAttrNameToId[norm(d.name)] || null;
                if (currentId) refreshValueSuggestions(currentId, '', idx);
                    hideValueDropdown(idx);
            };

            resolveAttribute().then(attributeId => {
            if (!attributeId) { proceed(); return; }
            valueExistsInGlobal(attributeId, value).then(exists => {
                if (exists) { proceed(); return; }
                const params = new URLSearchParams();
                params.append('value', value);
                fetch('<?= base_url('/product-attributes') ?>/' + encodeURIComponent(attributeId) + '/value/add', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                }).then(r => r.json()).then(json => {
                    if (!json || json.success !== true) {
                        alert('Failed to create value: ' + (json && json.message ? json.message : 'Unknown'));
                        return;
                    }
                    proceed();
                }).catch(() => alert('Request failed'));
            });
            });
        });
    }

    loadAttributeCache();

    function buildDefsFromVariants(variants) {
        const map = {};
        (variants || []).forEach(v => {
            let attrs = {};
            if (v && v.attributes) {
                try {
                    attrs = typeof v.attributes === 'string' ? JSON.parse(v.attributes) : (v.attributes || {});
                } catch (e) {
                    attrs = {};
                }
            }
            if (attrs && typeof attrs === 'object') {
                Object.keys(attrs).forEach(k => {
                    const name = String(k || '').trim();
                    const val = String(attrs[k] || '').trim();
                    if (!name || !val) return;
                    if (!map[name]) map[name] = [];
                    if (!map[name].some(x => norm(x) === norm(val))) {
                        map[name].push(val);
                    }
                });
            }
        });
        return Object.keys(map).map(name => ({
            name,
            values: map[name]
        }));
    }

    const variantValueCounts = buildVariantValueCounts(existingVariants || []);

    // initial state
    let initialDefs = getDefs();
    const merged = mergeDefsWithVariantValues(initialDefs, existingVariants || []);
    if (merged.changed) {
        setDefs(merged.defs);
        initialDefs = merged.defs;
    }

    const hasRealDefs = Array.isArray(initialDefs) && initialDefs.some(d => (d && String(d.name || '').trim()) || (Array.isArray(d.values) && d.values.length));
    if (!hasRealDefs && Array.isArray(existingVariants) && existingVariants.length) {
        const derivedDefs = buildDefsFromVariants(existingVariants);
        if (derivedDefs.length) {
            setDefs(derivedDefs);
        }
    }
    renderSelectedAttributes();
    updatePreviewButtonState();

    const defsToSync = getDefs();
    syncMissingGlobalAttributes(defsToSync)
        .then(() => loadAttributeCache())
        .catch(() => loadAttributeCache());

    // simple HTML escape for insertion
    function escapeHtml(str) {
        return String(str).replace(/[&<>\"'`]/g, function (s) {
            return ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":'&#39;','`':'&#96;'})[s];
        });
    }

});


/* Removed auto-generate on name blur — product code is readonly and provided by category */

// Quick add category functionality
function openAddCategoryModal() {
    const quickCategoryForm = document.getElementById('quickCategoryForm');
    if (quickCategoryForm) quickCategoryForm.reset();
    const addCategoryModal = document.getElementById('addCategoryModal');
    if (addCategoryModal) new bootstrap.Modal(addCategoryModal).show();
}

// Handle quick category form submission
const quickCategoryForm = document.getElementById('quickCategoryForm');
if (quickCategoryForm) {
    quickCategoryForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        formData.append('is_active', '1');
        fetch('<?= base_url('/product-categories/store') ?>', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .catch(() => {
            // If JSON parsing fails, assume success and reload categories
            refreshCategories();
            const addCategoryModal = document.getElementById('addCategoryModal');
            if (addCategoryModal) bootstrap.Modal.getInstance(addCategoryModal).hide();
            return { success: true };
        })
        .then(data => {
            if (data && data.success === false) {
                alert('Error: ' + (data.message || 'Failed to create category'));
            } else {
                // Success - refresh the category dropdown
                refreshCategories();
                const addCategoryModal = document.getElementById('addCategoryModal');
                if (addCategoryModal) bootstrap.Modal.getInstance(addCategoryModal).hide();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the category');
        });
    });
}

// Refresh categories dropdown
function refreshCategories() {
    fetch('<?= base_url('/product-categories/data?action=active') ?>')
    .then(response => response.json())
    .then(categories => {
        const select = document.getElementById('category_id');
        const currentValue = select.value;
        
        // Clear existing options (except the first one)
        select.innerHTML = '<option value="">Select Category</option>';
        
        // Add updated categories
        categories.forEach(category => {
            const option = new Option(category.name, category.id);
            select.appendChild(option);
        });
        
        // Restore selection if it still exists
        if (currentValue) {
            select.value = currentValue;
            // Trigger change so preview SKU updates and any listeners run
            select.dispatchEvent(new Event('change'));
        }
    })
    .catch(error => {
        console.error('Error refreshing categories:', error);
    });
}

// Auto-assign SKU preview when category changes
document.addEventListener('DOMContentLoaded', function() {
    const categorySelect = document.getElementById('category_id');
    const codeField = document.getElementById('code');
    const productForm = document.getElementById('productForm');
    // enforce readonly at runtime as well to prevent any manual edits
    if (codeField) codeField.readOnly = true;
    let autoAssigned = false;
    let lastAutoAssignedSku = null;
    let lastAutoAssignedCategoryId = null;
    let pendingSubmitWithPreview = false;

    function fetchPreviewAndFill(catId) {
        catId = catId || (categorySelect ? categorySelect.value : null);
        console.log('fetchPreviewAndFill called, catId=', catId);
        if (!catId) {
            const msgEl = document.getElementById('codePreviewMessage');
            if (msgEl) { msgEl.style.display = 'block'; msgEl.textContent = 'Please select a category first'; setTimeout(() => msgEl.style.display = 'none', 3000); }
            return Promise.resolve(null);
        }

        // Show previewing message
        const msgEl = document.getElementById('codePreviewMessage');
        if (msgEl) { msgEl.style.display = 'block'; msgEl.textContent = 'Loading preview...'; }

        return fetch('<?= base_url('/product-categories/data') ?>?action=preview_next_sku&category_id=' + encodeURIComponent(catId), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            if (data && data.sku) {
                // Assign the preview SKU when available
                if (codeField) {
                    codeField.value = data.sku;
                }
                autoAssigned = true;
                lastAutoAssignedSku = data.sku;
                lastAutoAssignedCategoryId = catId;
                if (msgEl) { msgEl.textContent = 'Auto-filled from category'; setTimeout(() => msgEl.style.display = 'none', 2500); }
                return data.sku;
            }

            // No preview available: clear the code field so previous category value is not kept
            if (codeField) {
                codeField.value = '';
            }

            if (data && data.error) {
                if (msgEl) { msgEl.textContent = 'Preview not available: ' + data.error; }
            } else {
                if (msgEl) { msgEl.textContent = 'Preview not available'; }
            }
            console.warn('Preview SKU not available', data);
            return null;
        })
        .catch(err => {
            if (msgEl) { msgEl.textContent = 'Failed to fetch SKU preview'; }
            console.error('Failed to fetch SKU preview', err);
            return null;
        });
    }

    if (categorySelect) {
        categorySelect.addEventListener('change', function() {
            const catId = (this.value || '').toString().trim();
            if (!catId) {
                const msgEl = document.getElementById('codePreviewMessage');
                if (msgEl) { msgEl.style.display = 'block'; msgEl.textContent = 'Please select a category first'; setTimeout(() => msgEl.style.display = 'none', 3000); }
                return;
            }

            // Always fetch preview for the newly selected category.
            fetchPreviewAndFill(catId).then(() => {});
        });
    }

    // The code input is readonly and only updated by the system. No manual-change handler is required.

    // Expose helper globally so other scripts can call it if needed
    window.fetchPreviewAndFill = fetchPreviewAndFill;

    // Attach click handlers to the buttons (avoid inline onclick which can run before function is available)
    try {
        const fetchBtn = document.getElementById('fetchCategoryCodeBtn');
        if (fetchBtn) {
            fetchBtn.addEventListener('click', function(e) { e.preventDefault(); fetchPreviewAndFill(); });
            console.log('Attached fetchCategoryCodeBtn listener');
        }

    // generateCode button removed — keep only category preview button
    } catch (e) {
        console.warn('Failed to attach code buttons', e);
    }

    // Initialize Select2 on searchable selects if Select2 is loaded (keeps UX consistent across app)
    try {
        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
            window.jQuery('.searchable').each(function() {
                try {
                    if (window.jQuery(this).data('select2')) {
                        window.jQuery(this).select2('destroy');
                    }
                    window.jQuery(this).select2({ width: '100%', placeholder: 'Search...', allowClear: true });
                } catch (e) {
                    console.warn('Select2 init failed for element', this, e);
                }
            });
        }
    } catch (e) { /* ignore */ }

    // If a category is already selected on load and code is empty, fetch preview once
    if (categorySelect && categorySelect.value && codeField && !codeField.value) {
        categorySelect.dispatchEvent(new Event('change'));
    }

    // Ensure code is generated before submit so the form can be saved
    if (productForm) {
        productForm.addEventListener('submit', function(e) {
            const productTypeEl = document.getElementById('product_type');
            const mode = productTypeEl ? (productTypeEl.value || 'simple') : 'simple';
            if (mode === 'variable') {
                return;
            }
            if (!codeField) return;
            const codeVal = (codeField.value || '').trim();
            if (codeVal) return;

            const catId = categorySelect ? (categorySelect.value || '').trim() : '';
            if (!catId) {
                e.preventDefault();
                e.stopPropagation();
                const msgEl = document.getElementById('codePreviewMessage');
                if (msgEl) {
                    msgEl.style.display = 'block';
                    msgEl.textContent = 'Please select a category to generate the product code.';
                }
                return;
            }

            if (pendingSubmitWithPreview) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();
            pendingSubmitWithPreview = true;

            if (window.fetchPreviewAndFill) {
                window.fetchPreviewAndFill(catId).then((sku) => {
                    if (sku && codeField.value.trim()) {
                        pendingSubmitWithPreview = false;
                        if (typeof productForm.requestSubmit === 'function') {
                            productForm.requestSubmit();
                        } else {
                            productForm.submit();
                        }
                        return;
                    }
                    pendingSubmitWithPreview = false;
                    const msgEl = document.getElementById('codePreviewMessage');
                    if (msgEl) {
                        msgEl.style.display = 'block';
                        msgEl.textContent = 'Unable to generate product code. Please try again.';
                    }
                }).catch(() => {
                    pendingSubmitWithPreview = false;
                    const msgEl = document.getElementById('codePreviewMessage');
                    if (msgEl) {
                        msgEl.style.display = 'block';
                        msgEl.textContent = 'Unable to generate product code. Please try again.';
                    }
                });
                return;
            }
        }, true);
    }
});
</script>

<!-- Quick Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">
                    <i class="bi bi-tags me-2"></i>Quick Add Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="quickCategoryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="quick_category_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="quick_category_name" 
                               name="name" 
                               required 
                               maxlength="100"
                               placeholder="Enter category name">
                    </div>
                    <div class="mb-3">
                        <label for="quick_category_description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="quick_category_description" 
                                  name="description" 
                                  rows="3"
                                  maxlength="500"
                                  placeholder="Optional description"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i>Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Variants Preview / Generate Modal -->
<div class="modal fade" id="variantsPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Preview Variants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="variantsPreviewBody">Loading…</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-warning" id="batchExcludeExistingBtn" disabled>Exclude Selected Existing</button>
                <button type="button" class="btn btn-outline-success" id="batchAllowExcludedBtn" disabled>Allow Selected Excluded</button>
                <button type="button" class="btn btn-primary" id="createVariantsConfirmBtn">Create Selected Variants</button>
            </div>
        </div>
    </div>
</div>

<?php if (isset($product) && $product): ?>
<!-- Variant Edit Modal -->
<div class="modal fade" id="variantEditModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Variant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="variantEditForm">
                <div class="modal-body">
                    <?= csrf_field() ?>
                    <input type="hidden" id="variant_edit_id" name="id" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Variant Image</label>
                            <div class="border rounded p-2 text-center bg-light">
                                <img id="variant_edit_preview" src="" alt="Variant" style="width:100%;max-height:180px;object-fit:cover;" class="rounded">
                            </div>
                            <input type="file" name="image" id="variant_edit_image" class="form-control form-control-sm mt-2" accept="image/*">
                        </div>
                        <div class="col-md-8">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Variant Name</label>
                                    <input type="text" class="form-control form-control-sm" id="variant_edit_name" name="name">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Art Number</label>
                                    <input type="text" class="form-control form-control-sm" id="variant_edit_art" name="art_number">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Price</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="variant_edit_price" name="price">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Cost</label>
                                    <input type="number" step="0.01" class="form-control form-control-sm" id="variant_edit_cost" name="cost">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Weight</label>
                                    <input type="number" step="0.001" class="form-control form-control-sm" id="variant_edit_weight" name="weight">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Attributes</label>
                                    <div class="form-control form-control-sm bg-light" id="variant_edit_attributes_text" style="min-height:56px" readonly></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="variantEditFeedback" class="mt-3"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function(){
    const previewBtn = document.getElementById('previewVariantsBtn');
    const attrsInput = document.getElementById('attributes_definitions');
    const productId = <?= isset($product) && $product ? (int)$product['id'] : 'null' ?>;
    const categorySelect = document.getElementById('category_id');
    const previewModalEl = document.getElementById('variantsPreviewModal');
    if (!previewModalEl || !window.bootstrap || !bootstrap.Modal) {
        return;
    }
    const previewModal = bootstrap.Modal.getOrCreateInstance(previewModalEl);
    const previewBody = document.getElementById('variantsPreviewBody');
    const createBtn = document.getElementById('createVariantsConfirmBtn');
    const batchExcludeBtn = document.getElementById('batchExcludeExistingBtn');
    const batchAllowBtn = document.getElementById('batchAllowExcludedBtn');
    const attrHelpers = window.CorelynkAttributes || {};
    const syncGlobalAttributes = typeof attrHelpers.syncMissingGlobalAttributes === 'function'
        ? attrHelpers.syncMissingGlobalAttributes
        : function(defs) { return Promise.resolve(defs); };
    const escapeVariantHtml = typeof attrHelpers.escapeHtml === 'function'
        ? attrHelpers.escapeHtml
        : function(str) {
            if (!str && str !== 0) return '';
            return String(str).replace(/[&<>"'`]/g, function (s) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','`':'&#96;'})[s]; });
        };

    if (previewBtn) {
        previewBtn.addEventListener('click', function(){
            let defsRaw = attrsInput ? attrsInput.value : '[]';
            let defs = [];
            try { defs = JSON.parse(defsRaw || '[]'); } catch (_) { defs = []; }
            if (!Array.isArray(defs)) defs = [];
            defs = defs.filter(d => d && d.name && Array.isArray(d.values) && d.values.length > 0);
            const categoryId = categorySelect ? (categorySelect.value || '') : '';
            if (!categoryId) {
                previewBody.innerHTML = '<div class="alert alert-warning">Please select a category in the Basics tab to generate Suggested Art #.</div>';
                previewModal.show();
                return;
            }
            syncGlobalAttributes(defs).then(() => {
            // POST to preview endpoint
            fetch('<?= base_url('/product-variants/generate-preview') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({
                    product_id: productId || '',
                    category_id: categoryId,
                    attributes_definitions: JSON.stringify(defs),
                    excluded_combos: (document.getElementById('excluded_combos') ? document.getElementById('excluded_combos').value : '[]')
                })
            }).then(r=>r.json()).then(data=>{
                if (!data || data.success !== true) {
                    previewBody.innerHTML = '<div class="alert alert-danger">Preview failed: '+(data && data.message ? data.message : 'Unknown error')+'</div>';
                    previewModal.show();
                    return;
                }

                const combos = data.combinations || [];
                if (combos.length === 0) {
                    previewBody.innerHTML = '<div class="alert alert-warning">No combinations generated (check attribute definitions).</div>';
                    previewModal.show();
                    return;
                }

                const totalCount = combos.length;
                const existingCount = combos.filter(c => c.exists === true).length;
                const excludedCount = combos.filter(c => c.excluded === true).length;
                const creatableCount = combos.filter(c => c.exists !== true && c.excluded !== true).length;
                const onlyAllowMode = combos.some(c => c.only_allow_mode === true);

                const attrNames = Object.keys((combos[0] && combos[0].attributes) ? combos[0].attributes : {});
                const defaultGroupAttr = attrNames.find(a => String(a || '').toLowerCase() === 'size') || attrNames[0] || '';
                const selectedState = {};
                const excludeSelectedState = {};
                const allowSelectedState = {};
                combos.forEach((c, idx) => {
                    selectedState[idx] = (c.exists !== true && c.excluded !== true);
                    excludeSelectedState[idx] = false;
                    allowSelectedState[idx] = false;
                });
                const excludableExistingCount = combos.filter(c => c && c.exists === true && c.existing_can_remove === true && c.existing_variant_id).length;
                const excludedCountRows = combos.filter(c => c && c.excluded === true).length;

                function appendComboExclusionToInput(attrs) {
                    if (!attrs || typeof attrs !== 'object') return;
                    const input = document.getElementById('excluded_combos');
                    if (!input) return;

                    let arr = [];
                    try { arr = JSON.parse(input.value || '[]'); } catch (_) { arr = []; }
                    if (!Array.isArray(arr)) arr = [];

                    const key = JSON.stringify(attrs);
                    const exists = arr.some(x => x && x.type === 'combo' && JSON.stringify(x.attributes || {}) === key);
                    if (!exists) {
                        arr.push({ type: 'combo', attributes: attrs });
                        input.value = JSON.stringify(arr);
                    }
                }

                function appendForceAllowToInput(attrs) {
                    if (!attrs || typeof attrs !== 'object') return;
                    const input = document.getElementById('excluded_combos');
                    if (!input) return;

                    let arr = [];
                    try { arr = JSON.parse(input.value || '[]'); } catch (_) { arr = []; }
                    if (!Array.isArray(arr)) arr = [];

                    const key = JSON.stringify(attrs);
                    const exists = arr.some(x => x && x.type === 'force_allow_combo' && JSON.stringify(x.attributes || {}) === key);
                    if (!exists) {
                        arr.push({ type: 'force_allow_combo', attributes: attrs });
                        input.value = JSON.stringify(arr);
                    }
                }

                function renderPreviewRows(groupAttr, filterText) {
                    const q = String(filterText || '').trim().toLowerCase();
                    // Build attribute order: selected group-by first, then remaining in original order
                    const attrOrder = [groupAttr, ...attrNames.filter(a => a !== groupAttr)];

                    // Filter combos by search term
                    const filtered = [];
                    combos.forEach((c, idx) => {
                        const disp = c.display || Object.values(c.attributes || {}).join(' / ');
                        if (q && String(disp).toLowerCase().indexOf(q) === -1) return;
                        filtered.push({ c, idx });
                    });

                    if (!filtered.length) {
                        return '<div class="alert alert-warning mb-0">No combinations match your filter.</div>';
                    }

                    // ── Leaf row renderer ──────────────────────────────────────────────
                    function renderLeaf(c, idx) {
                        const isExisting = c.exists === true;
                        const isExcluded = c.excluded === true;
                        const isExcludableExisting = isExisting && c.existing_can_remove === true && !!c.existing_variant_id;
                        const disabled = isExcluded || (isExisting && !isExcludableExisting);
                        const isCreatableChecked = !isExisting && !isExcluded ? !!selectedState[idx] : false;
                        const isExcludeChecked = isExcludableExisting ? !!excludeSelectedState[idx] : false;
                        const isAllowChecked = isExcluded ? !!allowSelectedState[idx] : false;
                        const art = c.simulated_art || c.existing_art || '';
                        const artHtml = art
                            ? `<code class="badge bg-dark bg-opacity-75 font-monospace fw-normal px-2">${escapeVariantHtml(art)}</code>`
                            : `<span class="text-muted small">—</span>`;

                        let selectHtml = `<input type="checkbox" class="form-check-input flex-shrink-0 mt-0" disabled title="${escapeVariantHtml(c.display || '')}">`;
                        if (!isExisting && !isExcluded) {
                            selectHtml = `<input type="checkbox" class="variant-select form-check-input flex-shrink-0 mt-0" data-idx="${idx}" ${isCreatableChecked ? 'checked' : ''} title="${escapeVariantHtml(c.display || '')}">`;
                        } else if (isExcludableExisting) {
                            selectHtml = `<input type="checkbox" class="exclude-existing-select form-check-input flex-shrink-0 mt-0" data-idx="${idx}" ${isExcludeChecked ? 'checked' : ''} title="Select existing variant for batch exclude">`;
                        } else if (isExcluded) {
                            selectHtml = `<input type="checkbox" class="allow-excluded-select form-check-input flex-shrink-0 mt-0" data-idx="${idx}" ${isAllowChecked ? 'checked' : ''} title="Select excluded variant for batch allow">`;
                        }

                        let statusHtml, actionHtml = '';
                        if (isExisting) {
                            statusHtml = `<span class="badge bg-secondary">${escapeVariantHtml(c.status_label || 'Exists')}</span>`;
                            if (c.existing_can_remove === true && c.existing_variant_id) {
                                actionHtml = `<button type="button" class="btn btn-sm btn-outline-warning py-0 exclude-existing-btn" data-idx="${idx}" style="font-size:.7rem;line-height:1.4">Exclude</button>`;
                            } else if (c.existing_block_reason) {
                                statusHtml += `<span class="text-muted small ms-1">(${escapeVariantHtml(c.existing_block_reason)})</span>`;
                            }
                        } else if (isExcluded) {
                            const cls = (c.excluded_reason || '').indexOf('Only Allow') !== -1
                                ? 'bg-warning text-dark' : 'bg-danger-subtle text-danger-emphasis';
                            statusHtml = `<span class="badge ${cls}">${escapeVariantHtml(c.status_label || 'Excluded')}</span>`;
                            if (c.excluded_reason) statusHtml += `<span class="text-muted small ms-1">(${escapeVariantHtml(c.excluded_reason)})</span>`;
                            actionHtml = `<button type="button" class="btn btn-sm btn-outline-info py-0 allow-variant-btn" data-idx="${idx}" style="font-size:.7rem;line-height:1.4">Allow</button>`;
                        } else {
                            statusHtml = `<span class="badge bg-success-subtle text-success-emphasis">${escapeVariantHtml(c.status_label || 'New')}</span>`;
                        }

                        // The leaf shows the last attribute's value (all parents already shown in tree headers)
                        const leafAttr = attrOrder[attrOrder.length - 1];
                        const leafVal = String((c.attributes || {})[leafAttr] ?? (c.display || ''));

                        return `<div class="pv-leaf d-flex align-items-center gap-1 px-1 py-0 rounded${disabled ? ' pv-leaf-disabled' : ''}" data-idx="${idx}">
                            ${selectHtml}
                            <i class="bi bi-tag-fill pv-leaf-icon flex-shrink-0"></i>
                            <span class="pv-leaf-label flex-grow-1 small fw-medium">${escapeVariantHtml(leafAttr)}: <span class="text-body">${escapeVariantHtml(leafVal)}</span></span>
                            <span class="flex-shrink-0">${artHtml}</span>
                            <span class="flex-shrink-0 d-flex align-items-center gap-1">${statusHtml}</span>
                            ${actionHtml ? `<span class="flex-shrink-0">${actionHtml}</span>` : ''}
                        </div>`;
                    }

                    // ── Recursive tree builder ─────────────────────────────────────────
                    function buildTree(items, levelIdx) {
                        const attr = attrOrder[levelIdx];
                        const isLeafLevel = levelIdx === attrOrder.length - 1;

                        // Group items by current attribute value
                        const groupMap = {};
                        const groupOrder = [];
                        items.forEach(item => {
                            const val = String((item.c.attributes || {})[attr] ?? 'N/A');
                            if (!groupMap[val]) { groupMap[val] = []; groupOrder.push(val); }
                            groupMap[val].push(item);
                        });
                        groupOrder.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }));

                        if (isLeafLevel) {
                            // At leaf level, each group has one combo — render it as a leaf row
                            return groupOrder.map(key => {
                                const [{ c, idx }] = groupMap[key];
                                return renderLeaf(c, idx);
                            }).join('');
                        }

                        const isTopLevel = levelIdx === 0;
                        return groupOrder.map((key, gi) => {
                            const groupItems = groupMap[key];
                            const groupTotal = groupItems.length;
                            const groupCreatable = groupItems.filter(({ c }) => c.exists !== true && c.excluded !== true).length;
                            const childrenHtml = buildTree(groupItems, levelIdx + 1);

                            if (isTopLevel) {
                                return `<details class="pv-node pv-node-top border rounded-2 mb-1 overflow-hidden" ${gi < 2 ? 'open' : ''}>
                                    <summary class="pv-summary pv-summary-top d-flex justify-content-between align-items-center px-2 py-1 gap-1">
                                        <span class="d-flex align-items-center gap-1 flex-grow-1 overflow-hidden">
                                            <input type="checkbox" class="group-select form-check-input flex-shrink-0 mt-0 pv-group-cb" data-tree-node="true" title="Select all creatable in this group" onclick="event.stopPropagation()">
                                            <i class="bi bi-collection-fill pv-icon-top flex-shrink-0"></i>
                                            <span class="fw-semibold text-nowrap">${escapeVariantHtml(attr)}:</span>
                                            <span class="text-truncate">${escapeVariantHtml(key)}</span>
                                        </span>
                                        <span class="d-flex align-items-center gap-1 flex-shrink-0">
                                            <span class="badge rounded-pill bg-body-secondary text-body-secondary pv-count-badge">${groupTotal}</span>
                                            <span class="badge rounded-pill bg-success-subtle text-success-emphasis pv-count-badge">${groupCreatable} creatable</span>
                                            <i class="bi bi-chevron-down pv-chevron small text-muted"></i>
                                        </span>
                                    </summary>
                                    <div class="pv-children pv-children-top ps-3 pe-1 pb-1 pt-0">${childrenHtml}</div>
                                </details>`;
                            } else {
                                return `<details class="pv-node pv-node-mid rounded-1 mb-0 overflow-hidden" open>
                                    <summary class="pv-summary pv-summary-mid d-flex justify-content-between align-items-center px-1 py-0 gap-1">
                                        <span class="d-flex align-items-center gap-1 flex-grow-1 overflow-hidden">
                                            <input type="checkbox" class="group-select form-check-input flex-shrink-0 mt-0 pv-group-cb" data-tree-node="true" style="transform:scale(.75)" title="Select all creatable in this sub-group" onclick="event.stopPropagation()">
                                            <i class="bi bi-folder2-open pv-icon-mid flex-shrink-0"></i>
                                            <span class="fw-medium text-nowrap">${escapeVariantHtml(attr)}:</span>
                                            <span class="text-truncate">${escapeVariantHtml(key)}</span>
                                        </span>
                                        <span class="d-flex align-items-center gap-1 flex-shrink-0">
                                            <span class="text-muted" style="font-size:.62rem">${groupCreatable} creatable</span>
                                            <i class="bi bi-chevron-down pv-chevron text-muted"></i>
                                        </span>
                                    </summary>
                                    <div class="pv-children pv-children-mid ps-2 pb-0 pt-0">${childrenHtml}</div>
                                </details>`;
                            }
                        }).join('');
                    }

                    return buildTree(filtered, 0);
                }

                // build table
                let html = '';
                // Tree view styles (injected once per open)
                { const pvStyle = document.getElementById('pvTreeStyles') || document.createElement('style');
                  pvStyle.id = 'pvTreeStyles';
                  pvStyle.textContent = `
                    .pv-node { transition: box-shadow .15s; }
                    .pv-node-top { border-color: rgba(255,255,255,.12) !important; }
                    .pv-node-mid { border-left: 2px solid rgba(255,255,255,.1); background: rgba(255,255,255,.015); }
                    .pv-summary { cursor:pointer; list-style:none; user-select:none; font-size:.72rem; }
                    .pv-summary::-webkit-details-marker { display:none; }
                    .pv-summary-top { background: rgba(255,255,255,.04); padding-top:4px !important; padding-bottom:4px !important; }
                    .pv-summary-top:hover { background: rgba(255,255,255,.07); }
                    .pv-summary-mid { background: rgba(255,255,255,.02); padding-top:2px !important; padding-bottom:2px !important; }
                    .pv-summary-mid:hover { background: rgba(255,255,255,.05); }
                    .pv-chevron { transition: transform .2s ease; }
                    .pv-node[open] > .pv-summary .pv-chevron { transform: rotate(180deg); }
                    .pv-icon-top { color: #6ea8fe; font-size:.7rem; }
                    .pv-icon-mid { color: #adb5bd; font-size:.65rem; }
                    .pv-count-badge { font-size:.6rem !important; }
                    .pv-children-top { border-top: 1px solid rgba(255,255,255,.07); }
                    .pv-children-mid { border-left: 2px solid rgba(255,255,255,.07); margin-left: 4px; }
                    .pv-leaf { border-left: 2px solid rgba(255,255,255,.06); margin-left: 3px; padding-top:1px !important; padding-bottom:1px !important; transition: background .12s; }
                    .pv-leaf:hover:not(.pv-leaf-disabled) { background: rgba(255,255,255,.06) !important; }
                    .pv-leaf-disabled { opacity: .5; }
                    .pv-leaf-icon { font-size:.55rem; color:#6c757d; }
                    .pv-leaf-label { font-size:.72rem; }
                    .pv-group-cb { cursor:pointer; transform:scale(.8); }
                    #previewStatsBar .badge { font-size:.65rem !important; }
                    #previewGroupBy, #previewVariantFilter { font-size:.72rem !important; }
                  `;
                  if (!document.getElementById('pvTreeStyles')) document.head.appendChild(pvStyle); }
                html += '<div class="d-flex flex-wrap gap-2 mb-2" id="previewStatsBar">';
                html += `<span class="badge bg-secondary">Total: <span id="pvTotalCount">${totalCount}</span></span>`;
                html += `<span class="badge bg-success-subtle text-success-emphasis">Creatable: <span id="pvCreatableCount">${creatableCount}</span></span>`;
                html += `<span class="badge bg-primary">Selected: <span id="pvSelectedCount">${creatableCount}</span></span>`;
                html += `<span class="badge bg-danger-subtle text-danger-emphasis">Skipped (unchecked): <span id="pvUncheckedCount">0</span></span>`;
                html += `<span class="badge bg-warning-subtle text-warning-emphasis">Existing selected for exclude: <span id="pvSelectedExistingExcludeCount">0</span></span>`;
                html += `<span class="badge bg-success-subtle text-success-emphasis">Excluded selected for allow: <span id="pvSelectedAllowCount">0</span></span>`;
                html += `<span class="badge bg-dark">Already Exists: <span id="pvExistingCount">${existingCount}</span></span>`;
                html += `<span class="badge bg-warning text-dark">Excluded by rules: <span id="pvExcludedCount">${excludedCount}</span></span>`;
                html += '</div>';
                html += '<div class="d-flex flex-wrap gap-2 align-items-center mb-2">';
                html += '<label class="small text-muted mb-0" for="previewGroupBy">Group by:</label>';
                html += '<select id="previewGroupBy" class="form-select form-select-sm" style="width:auto;min-width:170px">' +
                    attrNames.map(a => `<option value="${escapeVariantHtml(a)}" ${a === defaultGroupAttr ? 'selected' : ''}>${escapeVariantHtml(a)}</option>`).join('') +
                    '</select>';
                html += '<input type="search" id="previewVariantFilter" class="form-control form-control-sm" placeholder="Filter combinations" style="width:260px;max-width:100%">';
                html += '<div class="form-check ms-2"><input class="form-check-input" type="checkbox" id="variantsSelectAll" checked><label class="form-check-label small" for="variantsSelectAll">Select all creatable</label></div>';
                html += '<div class="form-check ms-2"><input class="form-check-input" type="checkbox" id="variantsSelectAllExistingExclude"><label class="form-check-label small" for="variantsSelectAllExistingExclude">Select all excludable existing</label></div>';
                html += '<div class="form-check ms-2"><input class="form-check-input" type="checkbox" id="variantsSelectAllAllowExcluded"><label class="form-check-label small" for="variantsSelectAllAllowExcluded">Select all excluded for allow</label></div>';
                html += '<div class="form-check ms-2"><input class="form-check-input" type="checkbox" id="previewParentChildAutoSelect" checked><label class="form-check-label small" for="previewParentChildAutoSelect">Parent -> child auto select</label></div>';
                html += '</div>';
                if (onlyAllowMode) {
                    html += '<div class="alert alert-info py-2 small mb-2"><strong>Only Allow mode is ON:</strong> combinations not in your Only Allow list are automatically excluded.</div>';
                }
                html += `<div id="previewGroupedRows">${renderPreviewRows(defaultGroupAttr, '')}</div>`;
                html += '<div class="form-text small text-muted">Only checked rows that are Creatable will be created. Existing rows can be excluded only when they are not used in sales/purchase and have no stock impact.</div>';
                previewBody.innerHTML = html;

                const selectAllEl = document.getElementById('variantsSelectAll');
                const selectAllExistingExcludeEl = document.getElementById('variantsSelectAllExistingExclude');
                const selectAllAllowExcludedEl = document.getElementById('variantsSelectAllAllowExcluded');
                const parentChildAutoEl = document.getElementById('previewParentChildAutoSelect');
                const groupByEl = document.getElementById('previewGroupBy');
                const filterEl = document.getElementById('previewVariantFilter');
                const groupedRowsEl = document.getElementById('previewGroupedRows');

                function updateSelectionStats() {
                    const selected = Object.keys(selectedState).filter(k => selectedState[k]).length;
                    const unchecked = Math.max(0, creatableCount - selected);
                    const selectedExistingExclude = Object.keys(excludeSelectedState).filter(k => excludeSelectedState[k]).length;
                    const selectedAllow = Object.keys(allowSelectedState).filter(k => allowSelectedState[k]).length;

                    const selectedNode = document.getElementById('pvSelectedCount');
                    const uncheckedNode = document.getElementById('pvUncheckedCount');
                    const selectedExistingExcludeNode = document.getElementById('pvSelectedExistingExcludeCount');
                    const selectedAllowNode = document.getElementById('pvSelectedAllowCount');
                    if (selectedNode) selectedNode.textContent = String(selected);
                    if (uncheckedNode) uncheckedNode.textContent = String(unchecked);
                    if (selectedExistingExcludeNode) selectedExistingExcludeNode.textContent = String(selectedExistingExclude);
                    if (selectedAllowNode) selectedAllowNode.textContent = String(selectedAllow);

                    if (selectAllEl) {
                        selectAllEl.checked = creatableCount > 0 && selected === creatableCount;
                        selectAllEl.indeterminate = selected > 0 && selected < creatableCount;
                    }

                    if (selectAllExistingExcludeEl) {
                        selectAllExistingExcludeEl.checked = excludableExistingCount > 0 && selectedExistingExclude === excludableExistingCount;
                        selectAllExistingExcludeEl.indeterminate = selectedExistingExclude > 0 && selectedExistingExclude < excludableExistingCount;
                    }

                    if (selectAllAllowExcludedEl) {
                        selectAllAllowExcludedEl.checked = excludedCountRows > 0 && selectedAllow === excludedCountRows;
                        selectAllAllowExcludedEl.indeterminate = selectedAllow > 0 && selectedAllow < excludedCountRows;
                    }

                    if (batchExcludeBtn) {
                        if (batchExcludeBtn.getAttribute('data-busy') !== '1') {
                            batchExcludeBtn.disabled = selectedExistingExclude === 0;
                            batchExcludeBtn.innerHTML = selectedExistingExclude > 0
                                ? 'Exclude Selected Existing (' + selectedExistingExclude + ')'
                                : 'Exclude Selected Existing';
                        }
                    }

                    if (batchAllowBtn) {
                        if (batchAllowBtn.getAttribute('data-busy') !== '1') {
                            batchAllowBtn.disabled = selectedAllow === 0;
                            batchAllowBtn.innerHTML = selectedAllow > 0
                                ? 'Allow Selected Excluded (' + selectedAllow + ')'
                                : 'Allow Selected Excluded';
                        }
                    }
                }

                function rerenderGroupedRows() {
                    const groupAttr = groupByEl ? groupByEl.value : defaultGroupAttr;
                    const filterText = filterEl ? filterEl.value : '';
                    if (groupedRowsEl) {
                        groupedRowsEl.innerHTML = renderPreviewRows(groupAttr, filterText);
                    }
                    updateSelectionStats();
                }

                if (selectAllEl) {
                    selectAllEl.addEventListener('change', function(e){
                        Object.keys(selectedState).forEach(k => {
                            selectedState[k] = !!e.target.checked;
                        });
                        previewBody.querySelectorAll('.variant-select').forEach(ch => {
                            if (!ch.disabled) {
                                const idx = parseInt(ch.getAttribute('data-idx'), 10);
                                ch.checked = !!selectedState[idx];
                            }
                        });
                        updateSelectionStats();
                    });
                }

                if (selectAllExistingExcludeEl) {
                    selectAllExistingExcludeEl.addEventListener('change', function(e){
                        const shouldCheck = !!e.target.checked;
                        combos.forEach((combo, idx) => {
                            const canExclude = combo && combo.exists === true && combo.existing_can_remove === true && !!combo.existing_variant_id;
                            if (!canExclude) return;
                            excludeSelectedState[idx] = shouldCheck;
                        });
                        previewBody.querySelectorAll('.exclude-existing-select').forEach(ch => {
                            ch.checked = shouldCheck;
                        });
                        updateSelectionStats();
                    });
                }

                if (selectAllAllowExcludedEl) {
                    selectAllAllowExcludedEl.addEventListener('change', function(e){
                        const shouldCheck = !!e.target.checked;
                        combos.forEach((combo, idx) => {
                            const canAllow = combo && combo.excluded === true;
                            if (!canAllow) return;
                            allowSelectedState[idx] = shouldCheck;
                        });
                        previewBody.querySelectorAll('.allow-excluded-select').forEach(ch => {
                            ch.checked = shouldCheck;
                        });
                        updateSelectionStats();
                    });
                }

                if (groupByEl) groupByEl.addEventListener('change', rerenderGroupedRows);
                if (filterEl) filterEl.addEventListener('input', rerenderGroupedRows);

                previewBody.onchange = function(e) {
                    const target = e.target;
                    if (!target) return;

                    if (target.classList.contains('variant-select')) {
                        const idx = parseInt(target.getAttribute('data-idx'), 10);
                        if (!isNaN(idx) && !target.disabled) {
                            selectedState[idx] = !!target.checked;
                        }
                        updateSelectionStats();
                        return;
                    }

                    if (target.classList.contains('exclude-existing-select')) {
                        const idx = parseInt(target.getAttribute('data-idx'), 10);
                        if (!isNaN(idx) && !target.disabled) {
                            excludeSelectedState[idx] = !!target.checked;
                        }
                        updateSelectionStats();
                        return;
                    }

                    if (target.classList.contains('allow-excluded-select')) {
                        const idx = parseInt(target.getAttribute('data-idx'), 10);
                        if (!isNaN(idx) && !target.disabled) {
                            allowSelectedState[idx] = !!target.checked;
                        }
                        updateSelectionStats();
                        return;
                    }

                    if (target.classList.contains('group-select') && target.getAttribute('data-tree-node')) {
                        if (parentChildAutoEl && !parentChildAutoEl.checked) {
                            updateSelectionStats();
                            return;
                        }

                        // Traverse up to find the parent <details> and select all .variant-select within it
                        const parentDetails = target.closest('details');
                        if (parentDetails) {
                            parentDetails.querySelectorAll('.group-select[data-tree-node]').forEach(ch => {
                                if (ch === target) return;
                                ch.checked = target.checked;
                                ch.indeterminate = false;
                            });

                            parentDetails.querySelectorAll('.variant-select').forEach(ch => {
                                if (!ch.disabled) ch.checked = target.checked;
                                const idx = parseInt(ch.getAttribute('data-idx'), 10);
                                if (!isNaN(idx) && !ch.disabled) {
                                    selectedState[idx] = !!target.checked;
                                }
                            });

                            parentDetails.querySelectorAll('.exclude-existing-select').forEach(ch => {
                                if (!ch.disabled) ch.checked = target.checked;
                                const idx = parseInt(ch.getAttribute('data-idx'), 10);
                                if (!isNaN(idx) && !ch.disabled) {
                                    excludeSelectedState[idx] = !!target.checked;
                                }
                            });

                            parentDetails.querySelectorAll('.allow-excluded-select').forEach(ch => {
                                if (!ch.disabled) ch.checked = target.checked;
                                const idx = parseInt(ch.getAttribute('data-idx'), 10);
                                if (!isNaN(idx) && !ch.disabled) {
                                    allowSelectedState[idx] = !!target.checked;
                                }
                            });
                        }
                        updateSelectionStats();
                    }
                };
                updateSelectionStats();

                previewBody.querySelectorAll('.allow-variant-btn').forEach(btn => {
                    btn.addEventListener('click', function(){
                        const idx = parseInt(this.getAttribute('data-idx'));
                        if (isNaN(idx) || !combos[idx] || !combos[idx].attributes) return;

                        const input = document.getElementById('excluded_combos');
                        if (!input) return;
                        let arr = [];
                        try { arr = JSON.parse(input.value || '[]'); } catch (_) { arr = []; }
                        if (!Array.isArray(arr)) arr = [];

                        const attrs = combos[idx].attributes;
                        const key = JSON.stringify(attrs);
                        const exists = arr.some(x => x && x.type === 'force_allow_combo' && JSON.stringify(x.attributes || {}) === key);
                        if (!exists) {
                            arr.push({ type: 'force_allow_combo', attributes: attrs });
                            input.value = JSON.stringify(arr);
                        }

                        // Rebuild preview with updated overrides so row becomes creatable immediately
                        if (previewBtn) previewBtn.click();
                    });
                });

                previewBody.querySelectorAll('.exclude-existing-btn').forEach(btn => {
                    btn.addEventListener('click', function(){
                        const idx = parseInt(this.getAttribute('data-idx'));
                        if (isNaN(idx) || !combos[idx]) return;
                        const combo = combos[idx];
                        const variantId = parseInt(combo.existing_variant_id || 0, 10);
                        if (!variantId || !combo.attributes) return;

                        if (!confirm('Exclude this existing variant from future lists and remove the current variant record?')) {
                            return;
                        }

                        const btnEl = this;
                        btnEl.disabled = true;

                        fetch('<?= base_url('/product-variants') ?>/' + encodeURIComponent(variantId) + '/exclude-from-list', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        }).then(r => r.json()).then(data => {
                            if (!data || data.success !== true) {
                                alert((data && data.message) ? data.message : 'Failed to exclude variant');
                                btnEl.disabled = false;
                                return;
                            }

                            appendComboExclusionToInput(combo.attributes);

                            if (previewBtn) previewBtn.click();
                        }).catch(() => {
                            alert('Failed to exclude variant');
                            btnEl.disabled = false;
                        });
                    });
                });

                if (batchExcludeBtn) {
                    batchExcludeBtn.onclick = function () {
                        const selectedIndexes = Object.keys(excludeSelectedState)
                            .filter(k => excludeSelectedState[k])
                            .map(k => parseInt(k, 10))
                            .filter(k => !isNaN(k));

                        const variantIds = [];
                        const seen = {};
                        selectedIndexes.forEach(idx => {
                            const combo = combos[idx];
                            if (!combo || combo.exists !== true || combo.existing_can_remove !== true || !combo.existing_variant_id) return;
                            const vid = parseInt(combo.existing_variant_id, 10);
                            if (!vid || seen[vid]) return;
                            seen[vid] = true;
                            variantIds.push(vid);
                        });

                        if (variantIds.length === 0) {
                            alert('No excludable existing variants selected');
                            return;
                        }

                        if (!confirm('Exclude ' + variantIds.length + ' existing variant(s) from future lists and remove current records?')) {
                            return;
                        }

                        batchExcludeBtn.setAttribute('data-busy', '1');
                        batchExcludeBtn.disabled = true;
                        batchExcludeBtn.innerHTML = 'Excluding...';

                        fetch('<?= base_url('/product-variants/bulk-exclude-from-list') ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: JSON.stringify({ ids: variantIds })
                        }).then(r => r.json()).then(data => {
                            if (!data || data.success !== true) {
                                alert((data && data.message) ? data.message : 'Failed to batch exclude variants');
                                return;
                            }

                            const excludedItems = Array.isArray(data.excluded_items) ? data.excluded_items : [];
                            excludedItems.forEach(item => {
                                if (!item || !item.excluded_entry || !item.excluded_entry.attributes) return;
                                appendComboExclusionToInput(item.excluded_entry.attributes);
                            });

                            if ((data.blocked_count || 0) > 0 || (data.failed_count || 0) > 0) {
                                alert((data.message || 'Batch exclude completed with some issues'));
                            }

                            if (previewBtn) previewBtn.click();
                        }).catch(() => {
                            alert('Failed to batch exclude variants');
                        }).finally(() => {
                            batchExcludeBtn.setAttribute('data-busy', '0');
                            updateSelectionStats();
                        });
                    };
                }

                if (batchAllowBtn) {
                    batchAllowBtn.onclick = function () {
                        const selectedIndexes = Object.keys(allowSelectedState)
                            .filter(k => allowSelectedState[k])
                            .map(k => parseInt(k, 10))
                            .filter(k => !isNaN(k));

                        const attrsList = [];
                        const seen = {};
                        selectedIndexes.forEach(idx => {
                            const combo = combos[idx];
                            if (!combo || combo.excluded !== true || !combo.attributes) return;
                            const key = JSON.stringify(combo.attributes);
                            if (seen[key]) return;
                            seen[key] = true;
                            attrsList.push(combo.attributes);
                        });

                        if (attrsList.length === 0) {
                            alert('No excluded variants selected');
                            return;
                        }

                        if (!confirm('Allow ' + attrsList.length + ' excluded variant(s) back into the generated list?')) {
                            return;
                        }

                        batchAllowBtn.setAttribute('data-busy', '1');
                        batchAllowBtn.disabled = true;
                        batchAllowBtn.innerHTML = 'Allowing...';

                        attrsList.forEach(attrs => appendForceAllowToInput(attrs));

                        if (previewBtn) previewBtn.click();

                        batchAllowBtn.setAttribute('data-busy', '0');
                        updateSelectionStats();
                    };
                }

                updateSelectionStats();

                // store combos on modal element for later
                previewModalEl._combinations = combos;
                previewModalEl._selectedState = selectedState;
                previewModal.show();
            }).catch(err=>{
                previewBody.innerHTML = '<div class="alert alert-danger">Preview request failed</div>';
                previewModal.show();
                console.error('Preview error', err);
            });
            });
        });
    }

    if (createBtn) {
        createBtn.addEventListener('click', function(){
            let defsRaw = attrsInput ? attrsInput.value : '[]';
            let defs = [];
            try { defs = JSON.parse(defsRaw || '[]'); } catch (_) { defs = []; }
            if (!Array.isArray(defs)) defs = [];
            defs = defs.filter(d => d && d.name && Array.isArray(d.values) && d.values.length > 0);

            const combos = previewModalEl._combinations || [];
            const selectedState = previewModalEl._selectedState || {};
            const selected = [];
            combos.forEach((combo, idx) => {
                if (!combo || combo.excluded === true || combo.exists === true) return;
                if (selectedState[idx]) selected.push(combo);
            });

            syncGlobalAttributes(defs).then(() => {

            if (!productId) {
                alert('Please save this product before generating variants.');
                return;
            }

            if (selected.length === 0) {
                alert('No variants selected to create');
                return;
            }

            // POST to generate endpoint
            createBtn.disabled = true;
            createBtn.innerHTML = 'Creating...';
            fetch('<?= base_url('/product-variants/generate') ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: new URLSearchParams({
                    product_id: productId || '',
                    category_id: categorySelect ? (categorySelect.value || '') : '',
                    combinations: JSON.stringify(selected),
                    excluded_combos: (document.getElementById('excluded_combos') ? document.getElementById('excluded_combos').value : '[]')
                })
            }).then(r=>r.json()).then(data=>{
                createBtn.disabled = false;
                createBtn.innerHTML = 'Create Selected Variants';
                if (!data || data.success !== true) {
                    alert('Failed to create variants: ' + (data && data.message ? data.message : 'Unknown'));
                    return;
                }

                // success - close modal and reload to show variants
                previewModal.hide();
                location.reload();
            }).catch(err=>{
                createBtn.disabled = false;
                createBtn.innerHTML = 'Create Selected Variants';
                alert('Request failed: see console');
                console.error('Generate error', err);
            });
            });
        });
    }

});
</script>

<?php if (isset($product) && $product): ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const editModalEl = document.getElementById('variantEditModal');
    if (!editModalEl || !window.bootstrap) return;
    const editModal = bootstrap.Modal.getOrCreateInstance(editModalEl);
    const form = document.getElementById('variantEditForm');
    const feedback = document.getElementById('variantEditFeedback');

    const setFeedback = (type, message) => {
        if (!feedback) return;
        if (!message) { feedback.innerHTML = ''; return; }
        const cls = type === 'success' ? 'alert-success' : 'alert-danger';
        feedback.innerHTML = `<div class="alert ${cls} py-2 mb-0">${message}</div>`;
    };

    document.addEventListener('click', function(e){
        const btn = e.target && e.target.closest ? e.target.closest('.btn-edit-variant') : null;
        if (!btn) return;
        const row = btn.closest('tr');
        if (!row) return;

        const vid = row.getAttribute('data-variant-id') || '';
        const vname = row.getAttribute('data-variant-name') || '';
        const vart = row.getAttribute('data-variant-art') || '';
        const vprice = row.getAttribute('data-variant-price') || '';
        const vcost = row.getAttribute('data-variant-cost') || '';
        const vweight = row.getAttribute('data-variant-weight') || '';
        const vattrs = row.getAttribute('data-variant-attributes') || '{}';
        const vimg = row.getAttribute('data-variant-image-url') || '';

        document.getElementById('variant_edit_id').value = vid;
        document.getElementById('variant_edit_name').value = vname;
        document.getElementById('variant_edit_art').value = vart;
        document.getElementById('variant_edit_price').value = vprice;
        document.getElementById('variant_edit_cost').value = vcost;
        document.getElementById('variant_edit_weight').value = vweight;
        let attrsText = '';
        try {
            const parsed = JSON.parse(vattrs || '{}');
            if (parsed && typeof parsed === 'object') {
                attrsText = Object.keys(parsed)
                    .map(k => `${k}: ${parsed[k]}`)
                    .join(' • ');
            }
        } catch (_) {
            attrsText = '';
        }
        const attrsEl = document.getElementById('variant_edit_attributes_text');
        if (attrsEl) {
            attrsEl.textContent = attrsText || '—';
        }
        document.getElementById('variant_edit_preview').src = vimg || '<?= base_url('assets/images/no-image.png') ?>';
        const imageInput = document.getElementById('variant_edit_image');
        if (imageInput) imageInput.value = '';

        setFeedback('', '');
        editModal.show();
    });

    const imageInput = document.getElementById('variant_edit_image');
    if (imageInput) {
        imageInput.addEventListener('change', function(){
            const file = this.files && this.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = function(ev){
                const img = document.getElementById('variant_edit_preview');
                if (img) img.src = ev.target.result;
            };
            reader.readAsDataURL(file);
        });
    }

    if (form) {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            const vid = document.getElementById('variant_edit_id').value;
            if (!vid) return;
            const actionUrl = '<?= base_url('/product-variants') ?>/' + encodeURIComponent(vid) + '/update';
            const formData = new FormData(form);
            toggleVariantSave(true);
            setFeedback('', '');

            fetch(actionUrl, {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            }).then(r => r.json()).then(data => {
                if (!data || data.success !== true) {
                    setFeedback('error', data && data.message ? data.message : 'Failed to update variant');
                    toggleVariantSave(false);
                    return;
                }
                const updated = data.variant || {};
                const row = document.querySelector(`tr[data-variant-id="${vid}"]`);
                if (row) {
                    row.setAttribute('data-variant-name', updated.name || '');
                    row.setAttribute('data-variant-art', updated.art_number || '');
                    row.setAttribute('data-variant-price', updated.price || '');
                    row.setAttribute('data-variant-cost', updated.cost || '');
                    row.setAttribute('data-variant-weight', updated.weight || '');
                    row.setAttribute('data-variant-image-url', data.image_url || row.getAttribute('data-variant-image-url') || '');

                    const cells = row.querySelectorAll('td');
                    if (cells && cells.length >= 8) {
                        const imgEl = cells[0].querySelector('img');
                        if (imgEl && data.image_url) imgEl.src = data.image_url;
                        cells[1].textContent = updated.name || '-';
                        cells[2].textContent = updated.art_number || '-';
                        cells[3].textContent = updated.price !== null && updated.price !== '' ? Number(updated.price).toFixed(2) : '-';
                        cells[4].textContent = updated.cost !== null && updated.cost !== '' ? Number(updated.cost).toFixed(2) : '-';
                        cells[5].textContent = updated.weight !== null && updated.weight !== '' ? Number(updated.weight).toFixed(3) : '-';
                    }
                }

                setFeedback('success', 'Variant updated successfully.');
                setTimeout(() => {
                    editModal.hide();
                }, 600);
                toggleVariantSave(false);
            }).catch(() => {
                setFeedback('error', 'Request failed');
                toggleVariantSave(false);
            });
        });
    }

    function toggleVariantSave(loading) {
        if (!form) return;
        const btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        if (loading) {
            btn.disabled = true;
            btn.dataset.orig = btn.textContent;
            btn.textContent = 'Saving...';
        } else {
            btn.disabled = false;
            btn.textContent = btn.dataset.orig || 'Save Changes';
        }
    }
});
</script>
<?php endif; ?>

</div>

<script>
// Safety toggle to ensure Attributes tab reflects Variant Mode even if earlier scripts fail.
document.addEventListener('DOMContentLoaded', function() {
    const productType = document.getElementById('product_type');
    const attrsCard = document.getElementById('attributesCard');
    const attrsInfo = document.getElementById('attrsInfoNote');
    const attrsTabBtn = document.getElementById('tab-attrs-btn');

    function isVariableValue(value) {
        const v = String(value || '').toLowerCase();
        return v === 'variable' || v === 'variant' || v === '1' || v.includes('variable');
    }

    function syncAttrsVisibility() {
        if (!productType || !attrsCard || !attrsInfo) return;
        if (isVariableValue(productType.value)) {
            attrsCard.style.display = '';
            attrsInfo.style.display = 'none';
            attrsInfo.hidden = true;
            attrsInfo.classList.add('d-none');
        } else {
            attrsCard.style.display = 'none';
            attrsInfo.style.display = '';
            attrsInfo.hidden = false;
            attrsInfo.classList.remove('d-none');
        }
    }

    if (productType) {
        productType.addEventListener('change', syncAttrsVisibility);
    }
    if (attrsTabBtn) {
        attrsTabBtn.addEventListener('shown.bs.tab', syncAttrsVisibility);
    }

    // Run once after load and again shortly after to catch deferred UI changes.
    syncAttrsVisibility();
    setTimeout(syncAttrsVisibility, 150);
});
</script>

<script>
// Hard fallback for "Add a line" to ensure it always works.
document.addEventListener('click', function(e) {
    const btn = e.target && e.target.closest ? e.target.closest('#addAttributeRowBtn') : null;
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();

    if (window.CorelynkAddAttributeLine) {
        window.CorelynkAddAttributeLine();
        return;
    }

    const input = document.getElementById('attributes_definitions');
    if (!input) return;
    let defs = [];
    try { defs = JSON.parse(input.value || '[]'); } catch (err) { defs = []; }
    if (!Array.isArray(defs)) defs = [];
    defs.push({ name: '', values: [] });
    input.value = JSON.stringify(defs);

    if (window.CorelynkAttributes && typeof window.CorelynkAttributes.renderSelectedAttributes === 'function') {
        window.CorelynkAttributes.renderSelectedAttributes();
    }
    if (window.CorelynkAttributes && typeof window.CorelynkAttributes.updatePreviewButtonState === 'function') {
        window.CorelynkAttributes.updatePreviewButtonState();
    }
});
</script>

<script src="<?= base_url('js/excluded-values.js') ?>"></script>
<?= $this->endSection() ?>
