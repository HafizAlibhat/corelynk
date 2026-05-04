<?php /** @var array $rows */ ?>

<div class="container-fluid py-3">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Normalized Attribute Values (Phase 1)</h4>
    <a class="btn btn-primary btn-sm" href="<?= base_url('product-attribute-values/create') ?>">Add Value</a>
  </div>

  <div class="alert alert-warning py-2">
    This screen is Phase 1 only. Existing JSON values in <code>product_attributes.values</code> remain authoritative.
  </div>

  <div class="card">
    <div class="card-body p-2">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th style="width:22%">Attribute</th>
              <th style="width:28%">Value</th>
              <th style="width:12%">Code</th>
              <th style="width:10%" class="text-end">Sort</th>
              <th style="width:10%" class="text-center">Active</th>
              <th style="width:18%" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="6" class="text-center text-muted py-4">No values found</td></tr>
          <?php else: ?>
            <?php foreach ($rows as $r): ?>
              <tr>
                <td><?= esc($r['attribute_name'] ?? '-') ?></td>
                <td><?= esc($r['value'] ?? '') ?></td>
                <td><code><?= esc($r['code'] ?? '') ?></code></td>
                <td class="text-end"><?= esc((string)($r['sort_order'] ?? 0)) ?></td>
                <td class="text-center"><?= !empty($r['is_active']) ? 'Yes' : 'No' ?></td>
                <td class="text-end">
                  <a class="btn btn-outline-secondary btn-sm" href="<?= base_url('product-attribute-values/' . (int)$r['id'] . '/edit') ?>">Edit</a>
                  <form method="post" action="<?= base_url('product-attribute-values/' . (int)$r['id'] . '/delete') ?>" style="display:inline-block" onsubmit="return confirm('Delete this value?')">
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
