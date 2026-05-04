<?php
$step = $step ?? [];
$options = $step['options'] ?? [];
$indexValue = (string) ($index ?? 0);
?>
<div class="step-block border rounded p-3 mb-3">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <h6 class="mb-0">Step</h6>
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeStepBlock(this)">Remove</button>
    </div>

    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label">Step Name</label>
            <input type="text" class="form-control" name="step_name[<?= esc($indexValue) ?>]" value="<?= esc($step['name'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Step Order</label>
            <input type="number" class="form-control" min="1" name="step_order[<?= esc($indexValue) ?>]" value="<?= esc($step['step_order'] ?? '') ?>" required>
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="step_is_optional[<?= esc($indexValue) ?>]" value="1" <?= !empty($step['is_optional']) ? 'checked' : '' ?>>
                <label class="form-check-label">Optional Step</label>
            </div>
        </div>

        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea class="form-control" rows="2" name="step_description[<?= esc($indexValue) ?>]"><?= esc($step['description'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
            <h6 class="mb-2">Execution Options</h6>
            <div class="row g-3">
                <div class="col-md-3">
                    <div class="form-check">
                        <input id="execution_inhouse_<?= esc($indexValue) ?>" type="checkbox" class="form-check-input" name="execution_inhouse[<?= esc($indexValue) ?>]" value="1" <?= !empty($options['inhouse']) ? 'checked' : '' ?>>
                        <label for="execution_inhouse_<?= esc($indexValue) ?>" class="form-check-label">In-house</label>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-check mb-2">
                        <input id="execution_vendor_<?= esc($indexValue) ?>" data-index="<?= esc($indexValue) ?>" type="checkbox" class="form-check-input execution-vendor-check" name="execution_vendor[<?= esc($indexValue) ?>]" value="1" <?= !empty($options['vendor']) ? 'checked' : '' ?>>
                        <label for="execution_vendor_<?= esc($indexValue) ?>" class="form-check-label">Vendor</label>
                    </div>
                    <select id="execution_vendor_id_<?= esc($indexValue) ?>" class="form-select searchable" name="execution_vendor_id[<?= esc($indexValue) ?>]">
                        <option value="">Select Vendor</option>
                        <?php foreach (($vendors ?? []) as $vendor): ?>
                            <option value="<?= (int) $vendor['id'] ?>" <?= (string) ($options['vendor_id'] ?? '') === (string) $vendor['id'] ? 'selected' : '' ?>>
                                <?= esc($vendor['name'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Default</label>
                    <select class="form-select" name="execution_default[<?= esc($indexValue) ?>]">
                        <option value="">Auto</option>
                        <option value="inhouse" <?= (string) ($options['default'] ?? '') === 'inhouse' ? 'selected' : '' ?>>In-house</option>
                        <option value="vendor" <?= (string) ($options['default'] ?? '') === 'vendor' ? 'selected' : '' ?>>Vendor</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Notes</label>
                    <input type="text" class="form-control" name="execution_notes[<?= esc($indexValue) ?>]" value="<?= esc($options['notes'] ?? '') ?>" placeholder="Optional notes">
                </div>
            </div>
        </div>
    </div>
</div>
