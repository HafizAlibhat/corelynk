<tr>
    <td>
        <select class="form-select searchable" name="material_product_id[<?= esc($index) ?>]" required>
            <option value="">Select Product</option>
            <?php
            $selectedValue = (string) ($material_select_value ?? ($material_product_id ?? ''));
            $grouped = [];
            foreach (($material_items ?? []) as $item) {
                $group = (string) ($item['group'] ?? 'Items');
                if (!isset($grouped[$group])) {
                    $grouped[$group] = [];
                }
                $grouped[$group][] = $item;
            }
            ?>
            <?php foreach ($grouped as $groupLabel => $groupItems): ?>
                <optgroup label="<?= esc($groupLabel) ?>">
                    <?php foreach ($groupItems as $item): ?>
                        <option value="<?= esc((string) ($item['value'] ?? '')) ?>" <?= $selectedValue === (string) ($item['value'] ?? '') ? 'selected' : '' ?>>
                            <?= esc((string) ($item['label'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>
        </select>
    </td>
    <td>
        <input type="number" class="form-control" step="0.0001" min="0.0001" name="material_qty_per_unit[<?= esc($index) ?>]" value="<?= esc($material_qty_per_unit ?? '') ?>" required>
    </td>
    <td class="text-center">
        <input type="checkbox" class="form-check-input" name="material_is_optional[<?= esc($index) ?>]" value="1" <?= !empty($material_is_optional) ? 'checked' : '' ?>>
    </td>
    <td class="text-center">
        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMaterialRow(this)">Remove</button>
    </td>
</tr>
