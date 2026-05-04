<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Product Stock Locations<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content-wrapper">
  <div class="card border-0 shadow-sm mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h4 class="mb-0">Product Stock Locations</h4>
        <div class="small text-muted mt-1">
          <strong>Product:</strong>
          <a href="<?= site_url('products/' . (int)($product['id'] ?? 0)) ?>" class="fw-semibold">
            <?= esc($product['name'] ?? 'Product') ?>
          </a>
          <?php if (!empty($product['code']) || !empty($product['sku'])): ?>
            | <?= esc(($product['code'] ?? '') !== '' ? $product['code'] : ($product['sku'] ?? '')) ?>
          <?php endif; ?>
          <?php if (!empty($variant)): ?>
            <br>
            <strong>Variant:</strong>
            <span class="fw-semibold">
              <?= esc(($variant['art_number'] ?? '') !== '' ? ($variant['art_number'] . ' - ' . ($variant['name'] ?? 'Variant')) : ($variant['name'] ?? 'Variant')) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary" href="<?= site_url('inventory/stock') ?>">Back to Inventory Stock</a>
      </div>
    </div>
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="small text-muted">Location paths are shown as Parent \ Child \ Sub-child.</div>
        <div class="fw-semibold">Total Qty: <?= number_format((float)($totalQty ?? 0), 2) ?></div>
      </div>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-dark">
            <tr>
              <th style="width:24%">Warehouse</th>
              <th style="width:56%">Location Path</th>
              <th style="width:20%" class="text-end">Quantity</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($rows)): ?>
              <tr>
                <td colspan="3" class="text-center text-muted py-4">No stock available for this item.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <tr>
                  <td><?= esc($row['warehouse_name'] ?? '-') ?></td>
                  <td><?= esc($row['location_path'] ?? ($row['location_name'] ?? '-')) ?></td>
                  <td class="text-end fw-semibold"><?= number_format((float)($row['quantity'] ?? 0), 2) ?></td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
