<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>GRNs<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">
  <div class="cl-list-header">
    <div>
      <h3 class="mb-0">Goods Receipt Notes</h3>
      <small class="text-muted">Read-only list of GRNs</small>
    </div>
  </div>
  <div class="card cl-list-table-card">
    <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>GRN Number</th>
            <th>Vendor</th>
            <th>PO Number</th>
            <th>Received Date</th>
            <th>Warehouse</th>
            <th>Location</th>
            <th>Created By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($grns)): ?>
            <tr><td colspan="7" class="text-muted">No GRNs found.</td></tr>
          <?php else: ?>
            <?php foreach ($grns as $g): ?>
              <tr>
                <?php $grnRef = !empty($g['public_id']) ? $g['public_id'] : (string)$g['id']; ?>
                <td><a href="<?= site_url('new-purchase-grns/detail/'.$grnRef) ?>" class="text-decoration-none"><?= esc($g['grn_number'] ?: ('GRN-'.$g['id'])) ?></a></td>
                <td><?= esc($g['vendor_name'] ?? $g['vendor_id'] ?? '') ?></td>
                <td><?= esc($g['po_number'] ?? $g['po_id'] ?? '') ?></td>
                <td><?= esc($g['received_at'] ?? $g['created_at'] ?? '') ?></td>
                <td><?= esc($g['warehouse_name'] ?? '') ?></td>
                <td><?= esc($g['location_name'] ?? '') ?></td>
                <td><?= esc($g['created_by_name'] ?? $g['created_by_username'] ?? $g['created_by'] ?? '') ?></td>
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
