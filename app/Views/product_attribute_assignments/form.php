<?php
  /** @var array|null $row */
  $isEdit = !empty($row);
?>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><?= $isEdit ? 'Edit' : 'Create' ?> Assignment</h4>
    <a class="btn btn-link" href="<?= base_url('product-attribute-assignments') ?>">Back</a>
  </div>

  <div class="alert alert-warning py-2">
    Phase 1 only. Assignments are stored for future use; no runtime enforcement yet.
  </div>

  <div class="card">
    <div class="card-body">
      <form method="post" action="<?= $isEdit ? base_url('product-attribute-assignments/' . (int)$row['id'] . '/update') : base_url('product-attribute-assignments/store') ?>">

        <div class="mb-3">
          <label class="form-label">Product</label>
          <select name="product_id" class="form-select" required>
            <option value="">Select product</option>
            <?php foreach (($products ?? []) as $p): ?>
              <option value="<?= (int)$p['id'] ?>" <?= $isEdit && (int)$row['product_id'] === (int)$p['id'] ? 'selected' : '' ?>>
                <?= esc($p['name']) ?><?= !empty($p['code']) ? ' (' . esc($p['code']) . ')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

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

        <div class="mb-3">
          <label class="form-label">Position</label>
          <input name="position" type="number" class="form-control" value="<?= esc(old('position', (string)($row['position'] ?? 0))) ?>">
          <div class="form-text">Lower comes first (stable ordering for future variant generation)</div>
        </div>

        <div class="mt-4">
          <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Update' : 'Create' ?></button>
        </div>

      </form>
    </div>
  </div>
</div>
