<?php
  /** @var array|null $row */
  $isEdit = !empty($row);
?>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Add' ?> Attribute Value</h4>
    <a class="btn btn-link" href="<?= base_url('product-attribute-values') ?>">Back</a>
  </div>

  <div class="alert alert-warning py-2">
    Phase 1 only. This does not change existing product behavior.
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= $isEdit ? base_url('product-attribute-values/' . (int)$row['id'] . '/update') : base_url('product-attribute-values/store') ?>">

        <div class="mb-3">
          <label class="form-label">Attribute</label>
          <select name="attribute_id" class="form-select" required>
            <option value="">Select attribute</option>
            <?php foreach (($attributes ?? []) as $a): ?>
              <option value="<?= (int)$a['id'] ?>" <?= $isEdit && (int)$row['attribute_id'] === (int)$a['id'] ? 'selected' : '' ?>>
                <?= esc($a['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Value</label>
            <input name="value" class="form-control" value="<?= esc(old('value', $row['value'] ?? '')) ?>" required>
          </div>
          <div class="col-md-3">
            <label class="form-label">Code</label>
            <input name="code" class="form-control" value="<?= esc(old('code', $row['code'] ?? '')) ?>" required>
            <div class="form-text">Use stable tokens like RED, BLU, XL</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Sort Order</label>
            <input name="sort_order" type="number" class="form-control" value="<?= esc(old('sort_order', (string)($row['sort_order'] ?? 0))) ?>">
          </div>
        </div>

        <div class="form-check mt-3">
          <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?= old('is_active', ($row['is_active'] ?? 1)) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_active">Active</label>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update' : 'Create' ?></button>
        </div>

      </form>
    </div>
  </div>
</div>
