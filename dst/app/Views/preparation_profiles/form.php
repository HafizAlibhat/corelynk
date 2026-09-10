<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid cl-form-page">
    <?php $isVariantContext = !empty($variant['id']); ?>
    <?php $backUrl = $isVariantContext ? base_url('product-variants/' . (int) $variant['id'] . '/edit') : base_url('products/' . (int) ($product['id'] ?? 0) . '?tab=preparation'); ?>
    <?php $autoProfileName = trim((string) ($product['name'] ?? '')); ?>
    <?php if ($isVariantContext): ?>
        <?php $autoProfileName .= ' / ' . trim((string) ($variant['name'] ?? ('Variant #' . (int) ($variant['id'] ?? 0)))); ?>
    <?php endif; ?>
    <div class="prep-header">
        <a href="<?= $backUrl ?>" class="prep-back" title="<?= $isVariantContext ? 'Back to variant' : 'Back to product' ?>">
            <i class="bi bi-arrow-left"></i>
            <span class="visually-hidden"><?= $isVariantContext ? 'Back to variant' : 'Back to product' ?></span>
        </a>
        <div class="prep-header-text">
            <p class="prep-kicker"><?= isset($profile) && $profile ? 'Preparation profile' : 'New preparation profile' ?></p>
            <h1 class="prep-title"><?= esc($product['name'] ?? '-') ?></h1>
            <p class="prep-subtitle">
                <?php if ($isVariantContext): ?>
                    <span class="prep-variant-chip"><?= esc($variant['name'] ?? ('#' . ($variant['id'] ?? ''))) ?></span>
                <?php endif; ?>
                What we consume and the operations we run to make one finished piece.
            </p>
        </div>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div id="preparation-form-error" class="alert alert-danger d-none"></div>

    <form id="preparation-profile-form" method="post" action="<?= isset($profile) && $profile ? base_url('preparation-profiles/' . (int) $profile['id'] . '/update') : base_url('preparation-profiles/store') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0) ?>">
        <input type="hidden" name="variant_id" value="<?= $isVariantContext ? (int) ($variant['id'] ?? 0) : '' ?>">

        <div class="card mb-2">
            <div class="card-body p-2">
                <div class="row g-2 align-items-end">
                    <div class="col-12 col-lg-5">
                        <label class="form-label">Profile name <span class="text-muted">(auto)</span></label>
                        <input type="text" class="form-control form-control-sm" value="<?= esc($autoProfileName) ?>" readonly>
                    </div>
                    <div class="col-12 col-lg-7">
                        <label class="form-label">Description <span class="text-muted">(optional)</span></label>
                        <input type="text" class="form-control form-control-sm" name="description" value="<?= esc(old('description', $profile['description'] ?? '')) ?>" placeholder="What this profile is for">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-2" id="materials-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="card-title mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Materials</h6>
                    <small class="text-muted">Stock used for one finished piece &mdash; e.g. one silver tweezer.</small>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMaterialRow()">
                    <i class="bi bi-plus-lg me-1"></i>Add Material
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="materials-table">
                        <thead class="table-light">
                            <tr>
                                <th>Material / Base product</th>
                                <th style="width: 160px;">Qty per piece</th>
                                <th style="width: 110px;" class="text-center">Optional</th>
                                <th style="width: 70px;"></th>
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
                <div>
                    <h6 class="card-title mb-0"><i class="bi bi-list-ol me-2 text-primary"></i>Steps</h6>
                    <small class="text-muted">Work done in order &mdash; e.g. gold plasma coating by a vendor.</small>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="addStepBlock()">
                    <i class="bi bi-plus-lg me-1"></i>Add Step
                </button>
            </div>
            <div class="card-body" id="steps-container">
                <?php
                $serviceLabels = [];
                foreach (($services ?? []) as $svc) {
                    $serviceLabels[$svc['value']] = $svc['label'];
                }
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
                                'service_price' => old('execution_service_price.' . $i, ''),
                            ],
                            'service_value' => old('step_service_product_id.' . $i, ''),
                            'service_label' => $serviceLabels[old('step_service_product_id.' . $i, '')] ?? '',
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
                            'service_price' => '',
                            'currency' => 'PKR',
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
                                $mappedOptions['service_price'] = ($option['service_price'] ?? null) !== null ? rtrim(rtrim((string) $option['service_price'], '0'), '.') : '';
                                $mappedOptions['currency'] = $option['currency'] ?? 'PKR';
                                if ((int) ($option['is_default'] ?? 0) === 1) {
                                    $mappedOptions['default'] = 'vendor';
                                }
                            }
                            if ($mappedOptions['notes'] === '' && !empty($option['notes'])) {
                                $mappedOptions['notes'] = $option['notes'];
                            }
                        }

                        $stepServiceValue = '';
                        if (! empty($step['service_variant_id'])) {
                            $stepServiceValue = 'variant:' . (int) $step['service_variant_id'];
                        } elseif (! empty($step['service_product_id'])) {
                            $stepServiceValue = 'product:' . (int) $step['service_product_id'];
                        }

                        echo view('preparation_profiles/partials/step_block', [
                            'index' => $i,
                            'step' => [
                                'name' => $step['name'] ?? '',
                                'step_order' => $step['step_order'] ?? '',
                                'description' => $step['description'] ?? '',
                                'is_optional' => (int) ($step['is_optional'] ?? 0) === 1 ? 1 : null,
                                'service_value' => $stepServiceValue,
                                'service_label' => $serviceLabels[$stepServiceValue] ?? '',
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

<style>
.cl-form-page {
    --prep-out: #a9701c;
    --prep-out-bg: rgba(169, 112, 28, .07);
    --prep-line: var(--bs-border-color);
    max-width: 1180px;
}
[data-bs-theme="dark"] .cl-form-page {
    --prep-out: #d8a545;
    --prep-out-bg: rgba(216, 165, 69, .1);
}

/* Page header */
.cl-form-page .prep-header { display: flex; align-items: flex-start; gap: .85rem; margin: 1rem 0 1.25rem; }
.cl-form-page .prep-back {
    flex: 0 0 auto; margin-top: .35rem;
    width: 2rem; height: 2rem; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    color: var(--bs-secondary-color); border: 1px solid var(--prep-line); text-decoration: none;
}
.cl-form-page .prep-back:hover { color: var(--bs-body-color); border-color: var(--bs-secondary-color); }
.cl-form-page .prep-kicker { margin: 0; font-size: .8125rem; color: var(--bs-secondary-color); }
.cl-form-page .prep-title { margin: .05rem 0 .2rem; font-size: 1.5rem; font-weight: 650; letter-spacing: -.01em; }
.cl-form-page .prep-subtitle { margin: 0; font-size: .875rem; color: var(--bs-secondary-color); max-width: 62ch; }
.cl-form-page .prep-variant-chip {
    display: inline-block; margin-right: .4rem; padding: .05rem .45rem;
    border: 1px solid var(--prep-line); border-radius: .25rem;
    font-size: .75rem; color: var(--bs-body-color);
}

/* one label style everywhere so every column lines up on the same baseline */
.cl-form-page .form-label {
    font-size: .75rem; font-weight: 500; margin-bottom: .15rem;
    color: var(--bs-secondary-color);
}
.cl-form-page .card-header { padding-top: .4rem; padding-bottom: .4rem; }
.cl-form-page .step-block .form-check-label { cursor: pointer; }

/* Operation spine (one step block) */
.cl-form-page .prep-step { display: flex; gap: .85rem; padding: .9rem 1rem; margin: 0 -1rem; border-top: 1px solid var(--prep-line); }
.cl-form-page .prep-step:first-child { border-top: 0; padding-top: 0; }
.cl-form-page .prep-step-rail { flex: 0 0 auto; padding-top: .1rem; }
.cl-form-page .prep-step .step-number {
    width: 1.75rem; height: 1.75rem; border-radius: 50%;
    display: inline-flex; align-items: center; justify-content: center;
    font-size: .8125rem; font-weight: 600;
    background: var(--bs-body-bg); color: var(--bs-body-color);
    border: 1px solid var(--bs-secondary-color);
}
.cl-form-page .prep-step.is-outsourced .step-number { border-color: var(--prep-out); color: var(--prep-out); }
.cl-form-page .prep-step-body { flex: 1 1 auto; min-width: 0; }
.cl-form-page .prep-step-head { display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: center; }
.cl-form-page .prep-step-head .step-title-input {
    flex: 1 1 18rem; font-weight: 600; font-size: .9375rem;
    border: 0; border-bottom: 1px solid var(--prep-line); border-radius: 0;
    padding: .15rem .1rem; background: transparent;
}
.cl-form-page .prep-step-head .step-title-input:focus {
    box-shadow: none; border-bottom-color: var(--bs-body-color); background: transparent;
}
.cl-form-page .prep-step-head-meta { display: flex; align-items: center; gap: .75rem; }
.cl-form-page .prep-order { display: inline-flex; align-items: center; gap: .35rem; }
.cl-form-page .prep-order-tag { font-size: .75rem; color: var(--bs-secondary-color); }
.cl-form-page .prep-order input { width: 3.5rem; padding: .1rem .4rem; font-size: .8125rem; }
.cl-form-page .prep-step-head-meta .form-check-label { font-size: .8125rem; color: var(--bs-secondary-color); cursor: pointer; }
.cl-form-page .prep-remove { color: var(--bs-secondary-color); border: 0; padding: .1rem .35rem; }
.cl-form-page .prep-remove:hover { color: var(--bs-danger); }

/* Route picker (in-house vs vendor) */
.cl-form-page .prep-route { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .75rem; margin-top: .6rem; }
.cl-form-page .prep-route-label { font-size: .75rem; color: var(--bs-secondary-color); }
.cl-form-page .prep-route-picks { display: inline-flex; }
.cl-form-page .prep-route-btn {
    font-size: .8125rem; padding: .2rem .6rem;
    display: inline-flex; align-items: center; gap: .35rem;
    border: 1px solid var(--prep-line); color: var(--bs-secondary-color); background: transparent;
}
.cl-form-page .prep-route-picks .prep-route-btn:first-of-type { border-radius: .3rem 0 0 .3rem; }
.cl-form-page .prep-route-picks .prep-route-btn:last-of-type { border-radius: 0 .3rem .3rem 0; margin-left: -1px; }
.cl-form-page .btn-check:checked + .prep-route-btn {
    color: var(--bs-body-color); border-color: var(--bs-body-color);
    background: var(--bs-tertiary-bg); z-index: 1;
}
.cl-form-page .btn-check:checked + .prep-route-btn-vendor {
    color: var(--prep-out); border-color: var(--prep-out); background: var(--prep-out-bg);
}
.cl-form-page .btn-check:focus-visible + .prep-route-btn { outline: 2px solid var(--bs-body-color); outline-offset: 1px; }
.cl-form-page .prep-route-default { display: inline-flex; align-items: center; gap: .35rem; margin-left: auto; }
.cl-form-page .prep-route-default select { width: auto; font-size: .8125rem; }

/* Vendor panel — the fields that only matter once a step is outsourced */
.cl-form-page .prep-vendor-panel {
    margin-top: .65rem; padding: .65rem .75rem;
    border-left: 2px solid var(--prep-out); border-radius: 0 .35rem .35rem 0;
    background: var(--prep-out-bg);
}
.cl-form-page .prep-price .step-service-currency { font-size: .75rem; }
.cl-form-page .prep-step-notes { margin-top: .65rem; }
.cl-form-page .vendor-off { opacity: .45; }
.cl-form-page .vendor-off .form-select,
.cl-form-page .vendor-off .form-control,
.cl-form-page .vendor-off .btn { pointer-events: none; }
.cl-form-page #materials-table td,
.cl-form-page #materials-table th { padding: .35rem .5rem; vertical-align: middle; }
/* select2 renders its own box; force it to match form-select-sm exactly */
.cl-form-page .select2-container { width: 100% !important; }
.cl-form-page .select2-container--default .select2-selection--single {
    height: calc(1.5em + .5rem + 2px);
    border-color: var(--bs-border-color);
    background-color: var(--bs-body-bg);
    border-radius: var(--bs-border-radius-sm);
}
.cl-form-page .select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: calc(1.5em + .5rem); font-size: .875rem; color: var(--bs-body-color); padding-left: .5rem;
}
.cl-form-page .select2-container--default .select2-selection--single .select2-selection__arrow { height: calc(1.5em + .5rem); }
.cl-form-page .select2-container--default .select2-selection--single .select2-selection__clear { line-height: calc(1.5em + .5rem); }
.cl-form-page .input-group-sm > .select2-container .select2-selection--single { border-top-right-radius: 0; border-bottom-right-radius: 0; }
</style>

<script>
let materialIndex = document.querySelectorAll('#materials-table tbody tr').length;
let stepIndex = document.querySelectorAll('#steps-container .step-block').length;

// Multi-word tokenized matcher: every space-separated token must appear in the option text.
// Allows searching like "16cm rosegold" or "16cm pc" to narrow results independently per word.
function multiWordMatcher(params, data) {
    if (!params.term || params.term.trim() === '') {
        return data;
    }
    var tokens = params.term.trim().toLowerCase().split(/\s+/);
    var text = (data.text || '').toLowerCase();
    for (var i = 0; i < tokens.length; i++) {
        if (tokens[i] && text.indexOf(tokens[i]) === -1) {
            return null;
        }
    }
    return data;
}

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
            var opts = {
                width: '100%',
                placeholder: 'Search...',
                allowClear: true,
            };
            // Apply multi-word matcher only to the materials product dropdown
            if (window.jQuery(this).attr('name') && window.jQuery(this).attr('name').indexOf('material_product_id') !== -1) {
                opts.matcher = multiWordMatcher;
            }
            window.jQuery(this).select2(opts);
            // select2 events are the only reliable signal here: its change is fired
            // through jQuery and never reaches a native addEventListener.
            window.jQuery(this).off('select2:select select2:clear').on('select2:select select2:clear', function() {
                if (this.classList.contains('execution-vendor-select')) {
                    refreshStepServices(this.getAttribute('data-index'));
                }
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

// Header badges just show position, so they are recomputed whenever the list changes.
function renumberSteps() {
    document.querySelectorAll('#steps-container .step-block .step-number').forEach(function(badge, i) {
        badge.textContent = i + 1;
    });
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
    renumberSteps();
}

function removeStepBlock(button) {
    const blocks = document.querySelectorAll('#steps-container .step-block');
    if (blocks.length <= 1) {
        alert('At least one step is required.');
        return;
    }
    button.closest('.step-block').remove();
    renumberSteps();
}

// Items a vendor can bill us for. Only the vendors already on this form are embedded;
// the rest are fetched once per vendor and cached, because unscoped this is thousands of rows.
const PREP_SERVICE_CACHE = {};
<?php foreach (($services ?? []) as $svc): ?>
(PREP_SERVICE_CACHE[<?= (int) $svc['vendor_id'] ?>] = PREP_SERVICE_CACHE[<?= (int) $svc['vendor_id'] ?>] || []).push(<?= json_encode($svc, JSON_UNESCAPED_UNICODE) ?>);
<?php endforeach; ?>

async function servicesForVendor(vendorId) {
    if (!PREP_SERVICE_CACHE[vendorId]) {
        try {
            const res = await fetch('<?= base_url('preparation-profiles/ajax/vendor-services') ?>/' + vendorId);
            const data = await res.json();
            PREP_SERVICE_CACHE[vendorId] = data.services || [];
        } catch (e) {
            PREP_SERVICE_CACHE[vendorId] = [];
        }
    }
    return PREP_SERVICE_CACHE[vendorId];
}

// Rebuild a step's service dropdown from the vendor picked on that step.
async function refreshStepServices(index) {
    const vendorSelect = document.getElementById('execution_vendor_id_' + index);
    const serviceSelect = document.getElementById('step_service_product_id_' + index);
    const hint = document.getElementById('step_service_hint_' + index);
    if (!vendorSelect || !serviceSelect) {
        return;
    }

    const vendorId = vendorSelect.value;
    const keep = serviceSelect.value || serviceSelect.getAttribute('data-selected') || '';

    if (vendorId) {
        serviceSelect.innerHTML = '<option value="">Loading…</option>';
    }
    const list = vendorId ? await servicesForVendor(vendorId) : [];

    serviceSelect.innerHTML = '';
    const blank = document.createElement('option');
    blank.value = '';
    blank.textContent = vendorId ? (list.length ? 'Not billed for this step' : 'Nothing attached to this vendor yet') : 'Select a vendor first';
    serviceSelect.appendChild(blank);

    list.forEach(function(s) {
        const opt = document.createElement('option');
        opt.value = s.value;
        opt.textContent = s.label + (s.price > 0 ? ' \u2014 ' + s.currency + ' ' + s.price : '');
        opt.dataset.price = s.price;
        opt.dataset.currency = s.currency;
        if (String(s.value) === String(keep)) {
            opt.selected = true;
        }
        serviceSelect.appendChild(opt);
    });

    serviceSelect.disabled = !vendorId;
    if (hint) {
        hint.textContent = vendorId && !list.length ? 'Nothing is attached to this vendor yet \u2014 click + to add a service.' : '';
    }
    applyServicePrice(index, false);
}

// Pull the vendor's agreed price into the per-item price box (never overwrite a price already typed).
function applyServicePrice(index, force) {
    const serviceSelect = document.getElementById('step_service_product_id_' + index);
    const priceInput = document.getElementById('execution_service_price_' + index);
    const currencyBox = document.getElementById('step_service_currency_' + index);
    if (!serviceSelect || !priceInput) {
        return;
    }
    const opt = serviceSelect.selectedOptions[0];
    if (!opt || !opt.value) {
        return;
    }
    if (force || priceInput.value === '' || parseFloat(priceInput.value) === 0) {
        priceInput.value = opt.dataset.price || '';
    }
    if (currencyBox && opt.dataset.currency) {
        currencyBox.textContent = opt.dataset.currency;
    }
}

function toggleVendorSelect(index) {
    const vendorCheck = document.getElementById('execution_vendor_' + index);
    const vendorSelect = document.getElementById('execution_vendor_id_' + index);
    if (!vendorCheck || !vendorSelect) {
        return;
    }
    vendorSelect.disabled = !vendorCheck.checked;
    if (!vendorCheck.checked) {
        vendorSelect.value = '';
    }
    ['vendor_field_', 'service_field_', 'price_field_'].forEach(function(prefix) {
        const field = document.getElementById(prefix + index);
        if (field) {
            field.classList.toggle('vendor-off', !vendorCheck.checked);
        }
    });
    const priceInput = document.getElementById('execution_service_price_' + index);
    if (priceInput) {
        priceInput.disabled = !vendorCheck.checked;
    }
    // Select2 keeps its own copy of the disabled state, so re-init to keep the widget in sync.
    initSearchableSelects(vendorSelect.parentElement);
    refreshStepServices(index);
}

document.addEventListener('change', function(e) {
    if (!e.target) {
        return;
    }
    if (e.target.classList.contains('execution-vendor-check')) {
        toggleVendorSelect(e.target.getAttribute('data-index'));
    }
    if (e.target.classList.contains('execution-vendor-select')) {
        refreshStepServices(e.target.getAttribute('data-index'));
    }
    if (e.target.classList.contains('step-service-select')) {
        applyServicePrice(e.target.getAttribute('data-index'), true);
    }
});

// Select2 fires its change through jQuery, which never reaches a native addEventListener,
// so the vendor dropdown also needs a jQuery-side binding or the services never refresh.
// The layout loads jQuery *after* this section renders, so bind once it exists — checking
// window.jQuery inline here would always be false and silently skip the binding.
document.addEventListener('DOMContentLoaded', function() {
    if (!window.jQuery) {
        return;
    }
    window.jQuery(document).on('change', 'select.execution-vendor-select', function() {
        refreshStepServices(this.getAttribute('data-index'));
    });
});

document.querySelectorAll('.execution-vendor-check').forEach(function(check) {
    toggleVendorSelect(check.getAttribute('data-index'));
});
renumberSteps();

// Select2 hides the real <select>, so a blank required one blocks submit with no visible message.
// Surface it instead of the form looking dead.
const prepForm = document.getElementById('preparation-profile-form');
const prepFormError = document.getElementById('preparation-form-error');

prepForm.addEventListener('invalid', function(e) {
    const field = e.target;
    const label = field.closest('td, [class*="col-"]');
    const labelText = label && label.querySelector('label') ? label.querySelector('label').textContent.trim() : field.name;
    prepFormError.textContent = 'Please complete this field before saving: ' + labelText;
    prepFormError.classList.remove('d-none');
    const anchor = field.classList.contains('searchable') && field.nextElementSibling ? field.nextElementSibling : field;
    anchor.scrollIntoView({ behavior: 'smooth', block: 'center' });
    anchor.classList.add('border', 'border-danger');
    setTimeout(function() { anchor.classList.remove('border-danger'); }, 4000);
}, true);

prepForm.addEventListener('submit', function() {
    prepFormError.classList.add('d-none');
    // Disabled controls post nothing; re-enable so the chosen service and price are saved.
    document.querySelectorAll('.step-service-select, .step-service-price').forEach(function(el) {
        el.disabled = false;
    });
});

// --- Inline "add a service for this vendor" ---
let serviceModalIndex = null;

function openServiceModal(index) {
    const vendorSelect = document.getElementById('execution_vendor_id_' + index);
    if (!vendorSelect || !vendorSelect.value) {
        alert('Select the vendor for this step first.');
        return;
    }
    serviceModalIndex = index;
    document.getElementById('new_service_vendor_name').textContent =
        vendorSelect.options[vendorSelect.selectedIndex].textContent.trim();
    document.getElementById('new_service_name').value = '';
    document.getElementById('new_service_price').value = '';
    document.getElementById('new_service_error').classList.add('d-none');
    new bootstrap.Modal(document.getElementById('newServiceModal')).show();
}

async function saveNewService() {
    const index = serviceModalIndex;
    const vendorSelect = document.getElementById('execution_vendor_id_' + index);
    const errorBox = document.getElementById('new_service_error');
    const btn = document.getElementById('new_service_save');

    const body = new FormData();
    body.append('name', document.getElementById('new_service_name').value.trim());
    body.append('price', document.getElementById('new_service_price').value || '0');
    body.append('currency', document.getElementById('new_service_currency').value);
    body.append('vendor_id', vendorSelect.value);

    btn.disabled = true;
    try {
        const res = await fetch('<?= base_url('preparation-profiles/ajax/create-service') ?>', { method: 'POST', body: body });
        const data = await res.json();
        if (!data.success) {
            errorBox.textContent = data.message || 'Could not save the service.';
            errorBox.classList.remove('d-none');
            return;
        }
        (PREP_SERVICE_CACHE[data.service.vendor_id] = PREP_SERVICE_CACHE[data.service.vendor_id] || []).push(data.service);
        document.getElementById('step_service_product_id_' + index).setAttribute('data-selected', data.service.value);
        await refreshStepServices(index);
        applyServicePrice(index, true);
        bootstrap.Modal.getInstance(document.getElementById('newServiceModal')).hide();
    } catch (err) {
        errorBox.textContent = 'Could not reach the server.';
        errorBox.classList.remove('d-none');
    } finally {
        btn.disabled = false;
    }
}

initSearchableSelects(document);
</script>

<div class="modal fade" id="newServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">New Vendor Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="new_service_error" class="alert alert-danger d-none py-2"></div>
                <p class="text-muted small mb-3">Vendor: <strong id="new_service_vendor_name"></strong></p>
                <div class="mb-3">
                    <label class="form-label">Service Name</label>
                    <input type="text" class="form-control" id="new_service_name" placeholder="e.g. Gold Coloring Service">
                </div>
                <div class="row g-2">
                    <div class="col-8">
                        <label class="form-label">Price per item</label>
                        <input type="number" step="0.0001" min="0" class="form-control" id="new_service_price" placeholder="120">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Currency</label>
                        <select class="form-select" id="new_service_currency">
                            <option value="PKR">PKR</option>
                            <option value="USD">USD</option>
                            <option value="CNY">CNY</option>
                            <option value="EUR">EUR</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="new_service_save" onclick="saveNewService()">Save Service</button>
            </div>
        </div>
    </div>
</div>

<template id="material-row-template">
    <tr>
        <td>
            <select class="form-select form-select-sm searchable" name="material_product_id[__INDEX__]" required>
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
            <input type="number" class="form-control form-control-sm" step="0.01" min="0.01" name="material_qty_per_unit[__INDEX__]" placeholder="0.00" required>
        </td>
        <td class="text-center">
            <input type="checkbox" class="form-check-input" name="material_is_optional[__INDEX__]" value="1">
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMaterialRow(this)" title="Remove this material"><i class="bi bi-trash"></i></button>
        </td>
    </tr>
</template>

<template id="step-block-template">
<?= view('preparation_profiles/partials/step_block', [
    'index' => '__INDEX__',
    'step' => [
        'name' => '',
        'step_order' => '__ORDER__',
        'description' => '',
        'is_optional' => null,
        'service_value' => '',
        'options' => ['inhouse' => false, 'vendor' => false, 'vendor_id' => '', 'notes' => '', 'default' => ''],
    ],
    'vendors' => $vendors,
]) ?>
</template>

<?= $this->endSection() ?>
