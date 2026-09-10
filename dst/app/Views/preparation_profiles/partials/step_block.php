<?php
$step = $step ?? [];
$options = $step['options'] ?? [];
$indexValue = (string) ($index ?? 0);
$serviceValue = (string) ($step['service_value'] ?? '');
$serviceLabel = (string) ($step['service_label'] ?? '');
$servicePrice = (string) ($options['service_price'] ?? '');
$vendorChecked = ! empty($options['vendor']);
?>
<div class="step-block prep-step<?= $vendorChecked ? ' is-outsourced' : '' ?>">
    <div class="prep-step-rail">
        <span class="step-number">1</span>
    </div>

    <div class="prep-step-body">
        <div class="prep-step-head">
            <input type="text" class="form-control step-title-input" name="step_name[<?= esc($indexValue) ?>]" value="<?= esc($step['name'] ?? '') ?>" placeholder="Name this operation, e.g. Gold plasma coating" required>

            <div class="prep-step-head-meta">
                <div class="prep-order" title="Position in the sequence">
                    <label class="visually-hidden" for="step_order_<?= esc($indexValue) ?>">Runs at position</label>
                    <span class="prep-order-tag">Runs at</span>
                    <input id="step_order_<?= esc($indexValue) ?>" type="number" class="form-control" min="1" name="step_order[<?= esc($indexValue) ?>]" value="<?= esc($step['step_order'] ?? '') ?>" required>
                </div>

                <div class="form-check form-switch mb-0" title="This operation can be skipped on a job">
                    <input id="step_is_optional_<?= esc($indexValue) ?>" type="checkbox" class="form-check-input" name="step_is_optional[<?= esc($indexValue) ?>]" value="1" <?= !empty($step['is_optional']) ? 'checked' : '' ?>>
                    <label for="step_is_optional_<?= esc($indexValue) ?>" class="form-check-label">Can be skipped</label>
                </div>

                <button type="button" class="btn btn-sm prep-remove" onclick="removeStepBlock(this)" title="Remove this operation">
                    <i class="bi bi-trash"></i><span class="visually-hidden">Remove this operation</span>
                </button>
            </div>
        </div>

        <div class="prep-route">
            <span class="prep-route-label">Performed by</span>
            <div class="prep-route-picks">
                <input id="execution_inhouse_<?= esc($indexValue) ?>" type="checkbox" class="btn-check" name="execution_inhouse[<?= esc($indexValue) ?>]" value="1" <?= !empty($options['inhouse']) ? 'checked' : '' ?>>
                <label for="execution_inhouse_<?= esc($indexValue) ?>" class="btn prep-route-btn">
                    <i class="bi bi-house-gear"></i>Our factory
                </label>

                <input id="execution_vendor_<?= esc($indexValue) ?>" data-index="<?= esc($indexValue) ?>" type="checkbox" class="btn-check execution-vendor-check" name="execution_vendor[<?= esc($indexValue) ?>]" value="1" <?= $vendorChecked ? 'checked' : '' ?>>
                <label for="execution_vendor_<?= esc($indexValue) ?>" class="btn prep-route-btn prep-route-btn-vendor">
                    <i class="bi bi-truck"></i>Outside vendor
                </label>
            </div>

            <div class="prep-route-default" id="default_field_<?= esc($indexValue) ?>">
                <label class="form-label mb-0" for="execution_default_<?= esc($indexValue) ?>">Prefer</label>
                <select id="execution_default_<?= esc($indexValue) ?>" class="form-select form-select-sm" name="execution_default[<?= esc($indexValue) ?>]">
                    <option value="">Whichever is free</option>
                    <option value="inhouse" <?= (string) ($options['default'] ?? '') === 'inhouse' ? 'selected' : '' ?>>Our factory</option>
                    <option value="vendor" <?= (string) ($options['default'] ?? '') === 'vendor' ? 'selected' : '' ?>>Outside vendor</option>
                </select>
            </div>
        </div>

        <div class="prep-vendor-panel vendor-only" id="vendor_panel_<?= esc($indexValue) ?>">
            <div class="row g-2">
                <div class="col-12 col-lg-4" id="vendor_field_<?= esc($indexValue) ?>">
                    <label class="form-label" for="execution_vendor_id_<?= esc($indexValue) ?>">Vendor</label>
                    <select id="execution_vendor_id_<?= esc($indexValue) ?>" data-index="<?= esc($indexValue) ?>" class="form-select form-select-sm searchable execution-vendor-select" name="execution_vendor_id[<?= esc($indexValue) ?>]">
                        <option value="">Select vendor</option>
                        <?php foreach (($vendors ?? []) as $vendor): ?>
                            <option value="<?= (int) $vendor['id'] ?>" <?= (string) ($options['vendor_id'] ?? '') === (string) $vendor['id'] ? 'selected' : '' ?>>
                                <?= esc($vendor['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-5" id="service_field_<?= esc($indexValue) ?>">
                    <label class="form-label" for="step_service_product_id_<?= esc($indexValue) ?>">Service they bill us for</label>
                    <div class="input-group input-group-sm">
                        <select id="step_service_product_id_<?= esc($indexValue) ?>" data-index="<?= esc($indexValue) ?>" data-selected="<?= esc($serviceValue) ?>" class="form-select step-service-select" name="step_service_product_id[<?= esc($indexValue) ?>]">
                            <?php if ($serviceValue !== ''): ?>
                                <option value="<?= esc($serviceValue) ?>" selected><?= esc($serviceLabel !== '' ? $serviceLabel : $serviceValue) ?></option>
                            <?php else: ?>
                                <option value="">Select a vendor first</option>
                            <?php endif; ?>
                        </select>
                        <button type="button" class="btn btn-outline-secondary" title="Add a service for this vendor" onclick="openServiceModal(<?= esc($indexValue) ?>)">
                            <i class="bi bi-plus-lg"></i><span class="visually-hidden">Add a service for this vendor</span>
                        </button>
                    </div>
                </div>

                <div class="col-12 col-lg-3" id="price_field_<?= esc($indexValue) ?>">
                    <label class="form-label" for="execution_service_price_<?= esc($indexValue) ?>">Cost per piece</label>
                    <div class="input-group input-group-sm prep-price">
                        <input type="number" step="0.0001" min="0" id="execution_service_price_<?= esc($indexValue) ?>" class="form-control step-service-price" name="execution_service_price[<?= esc($indexValue) ?>]" value="<?= esc($servicePrice) ?>" placeholder="0.00">
                        <span class="input-group-text step-service-currency" id="step_service_currency_<?= esc($indexValue) ?>"><?= esc($options['currency'] ?? 'PKR') ?></span>
                    </div>
                </div>

                <div class="col-12">
                    <small class="text-muted step-service-hint" id="step_service_hint_<?= esc($indexValue) ?>"></small>
                </div>
            </div>
        </div>

        <div class="row g-2 prep-step-notes">
            <div class="col-12 col-lg-6">
                <label class="form-label" for="step_description_<?= esc($indexValue) ?>">Instructions for the floor</label>
                <input id="step_description_<?= esc($indexValue) ?>" type="text" class="form-control form-control-sm" name="step_description[<?= esc($indexValue) ?>]" value="<?= esc($step['description'] ?? '') ?>" placeholder="How this operation should be carried out">
            </div>
            <div class="col-12 col-lg-6">
                <label class="form-label" for="execution_notes_<?= esc($indexValue) ?>">Internal note</label>
                <input id="execution_notes_<?= esc($indexValue) ?>" type="text" class="form-control form-control-sm" name="execution_notes[<?= esc($indexValue) ?>]" value="<?= esc($options['notes'] ?? '') ?>" placeholder="Only visible here">
            </div>
        </div>
    </div>
</div>
