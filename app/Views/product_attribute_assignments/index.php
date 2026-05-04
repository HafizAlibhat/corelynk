<?php /** @var array $rows */ ?>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Product Attribute Assignments (Phase 1)</h4>
    <a class="btn btn-primary btn-sm" href="<?= base_url('product-attribute-assignments/create') ?>">Assign Attribute</a>
  </div>

  <div class="alert alert-warning py-2">
    Phase 1 only. Assignments are not yet enforced by product/variant logic.
  </div>

  <div class="card">
    <div class="card-body p-2">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:40%">Product</th>
              <th style="width:40%">Attribute</th>
              <th style="width:8%" class="text-end">Pos</th>
              <th style="width:12%" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="4" class="text-center text-muted py-4">No assignments found</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td>
                  <?= esc($r['product_name'] ?? '-') ?>
                  <?php if (!empty($r['product_code'])): ?>
                    <div class="text-muted small"><?= esc($r['product_code']) ?></div>
                  <?php endif; ?>
                </td>
                <td><?= esc($r['attribute_name'] ?? '-') ?></td>
                <td class="text-end"><?= esc((string)($r['position'] ?? 0)) ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('product-attribute-assignments/' . (int)$r['id'] . '/edit') ?>">Edit</a>
                  <form method="post" action="<?= base_url('product-attribute-assignments/' . (int)$r['id'] . '/delete') ?>" style="display:inline-block" onsubmit="return confirm('Delete this assignment?')">
                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
