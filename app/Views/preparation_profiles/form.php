<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <?php $isVariantContext = !empty($variant['id']); ?>
    <?php $backUrl = $isVariantContext ? base_url('product-variants/' . (int) $variant['id'] . '/edit') : base_url('products/' . (int) ($product['id'] ?? 0) . '?tab=preparation'); ?>
    <?php $autoProfileName = trim((string) ($product['name'] ?? '')); ?>
    <?php if ($isVariantContext): ?>
        <?php $autoProfileName .= ' / ' . trim((string) ($variant['name'] ?? ('Variant #' . (int) ($variant['id'] ?? 0)))); ?>
    <?php endif; ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1"><?= isset($profile) && $profile ? 'Edit Preparation Profile' : 'Create Preparation Profile' ?></h1>
            <p class="text-muted mb-0">Product: <?= esc($product['name'] ?? '-') ?></p>
            <?php if ($isVariantContext): ?>
                <p class="text-muted mb-0">Variant: <?= esc($variant['name'] ?? ('#' . ($variant['id'] ?? ''))) ?></p>
            <?php endif; ?>
        </div>
        <a href="<?= $backUrl ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> <?= $isVariantContext ? 'Back to Variant' : 'Back to Product' ?>
        </a>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="alert alert-info py-2">
        <strong>Quick setup:</strong> 1) Add required materials, 2) define preparation steps, 3) choose execution option (in-house/vendor), 4) save.
    </div>

    <form method="post" action="<?= isset($profile) && $profile ? base_url('preparation-profiles/' . (int) $profile['id'] . '/update') : base_url('preparation-profiles/store') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
        <input type="hidden" name="variant_id" value="<?= $isVariantContext ? (int) ($variant['id'] ?? 0) : '' ?>">

        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0">Profile Details</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Profile Name (Auto Generated)</label>
                        <input type="text" class="form-control" value="<?= esc($autoProfileName) ?>" readonly>
                        <small class="text-muted">System-generated from <?= $isVariantContext ? 'product + variant' : 'product' ?> to keep names clean and consistent.</small>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description (Optional)</label>
                        <textarea class="form-control" name="description" rows="2"><?= esc(old('description', $profile['description'] ?? '')) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3" id="materials-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Materials Needed</h5>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMaterialRow()">Add Material</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="materials-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="width: 180px;">Qty Per Unit</th>
                                <th style="width: 120px;">Optional</th>
                                <th style="width: 90px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $oldMaterials = old('material_product_id');
                            if (is_array($oldMaterials)) {
                                foreach ($oldMaterials as $i => $mProductId) {
                                    $mQty = old('material_qty_per_unit.' . $i, '');
                                    $mOptional = old('material_is_optional.' . $i);
                                    echo view('preparation_profiles/partials/material_row', [
                                        'index' => $i,
                                        'material_items' => $material_items ?? [],
                                        'material_product_id' => $mProductId,
                                        'material_select_value' => $mProductId,
                                        'material_qty_per_unit' => $mQty,
                                        'material_is_optional' => $mOptional,
                                    ]);
                                }
                            } elseif (!empty($materials ?? [])) {
                                foreach (($materials ?? []) as $i => $material) {
                                    $materialSelectValue = !empty($material['variant_id'])
                                        ? ('variant:' . (int) $material['variant_id'])
                                        : ('product:' . (int) ($material['product_id'] ?? 0));

                                    echo view('preparation_profiles/partials/material_row', [
                                        'index' => $i,
                                        'material_items' => $material_items ?? [],
                                        'material_product_id' => $material['product_id'] ?? '',
                                        'material_select_value' => $materialSelectValue,
                                        'material_qty_per_unit' => $material['qty_per_unit'] ?? '',
                                        'material_is_optional' => (int) ($material['is_optional'] ?? 0) === 1 ? 1 : null,
                                    ]);
                                }
                            } else {
                                echo view('preparation_profiles/partials/material_row', [
                                    'index' => 0,
                                    'material_items' => $material_items ?? [],
                                    'material_product_id' => '',
                                    'material_select_value' => '',
                                    'material_qty_per_unit' => '',
                                    'material_is_optional' => null,
                                ]);
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Steps to Prepare</h5>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addStepBlock()">Add Step</button>
            </div>
            <div class="card-body" id="steps-container">
                <?php
                $oldStepNames = old('step_name');
                if (is_array($oldStepNames)) {
                    foreach ($oldStepNames as $i => $stepName) {
                        $stepData = [
                            'name' => old('step_name.' . $i, ''),
                            'step_order' => old('step_order.' . $i, ''),
                            'description' => old('step_description.' . $i, ''),
                            'is_optional' => old('step_is_optional.' . $i),
                            'options' => [
                                'inhouse' => old('execution_inhouse.' . $i) ? true : false,
                                'vendor' => old('execution_vendor.' . $i) ? true : false,
                                'vendor_id' => old('execution_vendor_id.' . $i, ''),
                                'notes' => old('execution_notes.' . $i, ''),
                                'default' => old('execution_default.' . $i, ''),
                            ],
                        ];
                        echo view('preparation_profiles/partials/step_block', [
                            'index' => $i,
                            'step' => $stepData,
                            'vendors' => $vendors,
                        ]);
                    }
                } elseif (!empty($steps ?? [])) {
                    foreach (($steps ?? []) as $i => $step) {
                        $options = $step_options[(int) $step['id']] ?? [];
                        $mappedOptions = [
                            'inhouse' => false,
                            'vendor' => false,
                            'vendor_id' => '',
                            'notes' => '',
                            'default' => '',
                        ];
                        foreach ($options as $option) {
                            if (($option['execution_type'] ?? '') === 'inhouse') {
                                $mappedOptions['inhouse'] = true;
                                if ((int) ($option['is_default'] ?? 0) === 1) {
                                    $mappedOptions['default'] = 'inhouse';
                                }
                            }
                            if (($option['execution_type'] ?? '') === 'vendor') {
                                $mappedOptions['vendor'] = true;
                                $mappedOptions['vendor_id'] = $option['vendor_id'] ?? '';
                                if ((int) ($option['is_default'] ?? 0) === 1) {
                                    $mappedOptions['default'] = 'vendor';
                                }
                            }
                            if ($mappedOptions['notes'] === '' && !empty($option['notes'])) {
                                $mappedOptions['notes'] = $option['notes'];
                            }
                        }

                        echo view('preparation_profiles/partials/step_block', [
                            'index' => $i,
                            'step' => [
                                'name' => $step['name'] ?? '',
                                'step_order' => $step['step_order'] ?? '',
                                'description' => $step['description'] ?? '',
                                'is_optional' => (int) ($step['is_optional'] ?? 0) === 1 ? 1 : null,
                                'options' => $mappedOptions,
                            ],
                            'vendors' => $vendors,
                        ]);
                    }
                } else {
                    echo view('preparation_profiles/partials/step_block', [
                        'index' => 0,
                        'step' => [
                            'name' => '',
                            'step_order' => 1,
                            'description' => '',
                            'is_optional' => null,
                            'options' => [
                                'inhouse' => true,
                                'vendor' => false,
                                'vendor_id' => '',
                                'notes' => '',
                                'default' => 'inhouse',
                            ],
                        ],
                        'vendors' => $vendors,
                    ]);
                }
                ?>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= $backUrl ?>" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">Save Profile</button>
        </div>
    </form>
</div>

<script>
let materialIndex = document.querySelectorAll('#materials-table tbody tr').length;
let stepIndex = document.querySelectorAll('#steps-container .step-block').length;

function initSearchableSelects(scope) {
    if (!(window.jQuery && window.jQuery.fn && window.jQuery.fn.select2)) {
        return;
    }

    const $root = scope ? window.jQuery(scope) : window.jQuery(document);
    $root.find('select.searchable').each(function() {
        try {
            if (window.jQuery(this).data('select2')) {
                window.jQuery(this).select2('destroy');
            }
            window.jQuery(this).select2({
                width: '100%',
                placeholder: 'Search...',
                allowClear: true,
            });
        } catch (e) {
            console.warn('Select2 init failed on preparation form', e);
        }
    });
}

function addMaterialRow() {
    const tbody = document.querySelector('#materials-table tbody');
    const template = document.getElementById('material-row-template').innerHTML.replaceAll('__INDEX__', materialIndex);
    tbody.insertAdjacentHTML('beforeend', template);
    const newRow = tbody.lastElementChild;
    if (newRow) {
        initSearchableSelects(newRow);
    }
    materialIndex++;
}

function removeMaterialRow(button) {
    const rows = document.querySelectorAll('#materials-table tbody tr');
    if (rows.length <= 1) {
        alert('At least one material is required.');
        return;
    }
    button.closest('tr').remove();
}

function addStepBlock() {
    const container = document.getElementById('steps-container');
    const template = document.getElementById('step-block-template').innerHTML.replaceAll('__INDEX__', stepIndex).replaceAll('__ORDER__', stepIndex + 1);
    container.insertAdjacentHTML('beforeend', template);
    const newBlock = container.lastElementChild;
    if (newBlock) {
        initSearchableSelects(newBlock);
    }
    stepIndex++;
}

function removeStepBlock(button) {
    const blocks = document.querySelectorAll('#steps-container .step-block');
    if (blocks.length <= 1) {
        alert('At least one step is required.');
        return;
    }
    button.closest('.step-block').remove();
}

function toggleVendorSelect(index) {
    const vendorCheck = document.getElementById('execution_vendor_' + index);
    const vendorSelect = document.getElementById('execution_vendor_id_' + index);
    vendorSelect.disabled = !vendorCheck.checked;
    if (!vendorCheck.checked) {
        vendorSelect.value = '';
    }
}

document.addEventListener('change', function(e) {
    if (e.target && e.target.classList.contains('execution-vendor-check')) {
        const index = e.target.getAttribute('data-index');
        toggleVendorSelect(index);
    }
});

document.querySelectorAll('.execution-vendor-check').forEach(function(check) {
    const index = check.getAttribute('data-index');
    toggleVendorSelect(index);
});

initSearchableSelects(document);
</script>

<template id="material-row-template">
    <tr>
        <td>
            <select class="form-select searchable" name="material_product_id[__INDEX__]" required>
                <option value="">Select Product</option>
                <?php
                $templateGrouped = [];
                foreach (($material_items ?? []) as $item) {
                    $group = (string) ($item['group'] ?? 'Items');
                    if (!isset($templateGrouped[$group])) {
                        $templateGrouped[$group] = [];
                    }
                    $templateGrouped[$group][] = $item;
                }
                ?>
                <?php foreach ($templateGrouped as $groupLabel => $groupItems): ?>
                    <optgroup label="<?= esc($groupLabel) ?>">
                        <?php foreach ($groupItems as $item): ?>
                            <option value="<?= esc((string) ($item['value'] ?? '')) ?>">
                                <?= esc((string) ($item['label'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control" step="0.0001" min="0.0001" name="material_qty_per_unit[__INDEX__]" required>
        </td>
        <td class="text-center">
            <input type="checkbox" class="form-check-input" name="material_is_optional[__INDEX__]" value="1">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMaterialRow(this)">Remove</button>
        </td>
    </tr>
</template>

<template id="step-block-template">
    <div class="step-block border rounded p-3 mb-3">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <h6 class="mb-0">Step</h6>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeStepBlock(this)">Remove</button>
        </div>

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label">Step Name</label>
                <input type="text" class="form-control" name="step_name[__INDEX__]" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Step Order</label>
                <input type="number" class="form-control" min="1" name="step_order[__INDEX__]" value="__ORDER__" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input type="checkbox" class="form-check-input" name="step_is_optional[__INDEX__]" value="1">
                    <label class="form-check-label">Optional Step</label>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" rows="2" name="step_description[__INDEX__]"></textarea>
            </div>

            <div class="col-12">
                <h6 class="mb-2">Execution Options</h6>
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input id="execution_inhouse___INDEX__" type="checkbox" class="form-check-input" name="execution_inhouse[__INDEX__]" value="1">
                            <label for="execution_inhouse___INDEX__" class="form-check-label">In-house</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-check mb-2">
                            <input id="execution_vendor___INDEX__" data-index="__INDEX__" type="checkbox" class="form-check-input execution-vendor-check" name="execution_vendor[__INDEX__]" value="1">
                            <label for="execution_vendor___INDEX__" class="form-check-label">Vendor</label>
                        </div>
                        <select id="execution_vendor_id___INDEX__" class="form-select searchable" name="execution_vendor_id[__INDEX__]">
                            <option value="">Select Vendor</option>
                            <?php foreach (($vendors ?? []) as $vendor): ?>
                                <option value="<?= (int) $vendor['id'] ?>"><?= esc($vendor['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Default</label>
                        <select class="form-select" name="execution_default[__INDEX__]">
                            <option value="">Auto</option>
                            <option value="inhouse">In-house</option>
                            <option value="vendor">Vendor</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="execution_notes[__INDEX__]" placeholder="Optional notes">
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<?= $this->endSection() ?>
