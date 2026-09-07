<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>GRNs<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="pl-wrap container-fluid cl-list-page">
  <div class="pl-card data-table cl-list-table-card">
    <div class="pl-card-header cl-list-header">
      <div>
        <div class="pl-card-header-title"><i class="bi bi-box-arrow-in-down me-1"></i>Goods Receipt Notes</div>
        <div class="pl-card-header-sub">Review received purchase orders and warehouse locations</div>
      </div>
    </div>

    <form method="get" action="<?= site_url('new-purchase-grns/list') ?>" class="pl-filter-form" role="search">
      <div class="pl-filter-toolbar">
        <div class="pl-filter-toolbar-search">
          <div class="pl-filter-search-label">
            <label for="grnSearch">Search receipt notes</label>
            <span>Press Enter to search</span>
          </div>
          <div class="pl-search-shell">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" name="search" id="grnSearch" class="form-control pl-filter-control pl-filter-control-search" placeholder="Search GRN, PO, vendor, warehouse, location, or creator" value="<?= esc($search ?? '') ?>">
          </div>
        </div>
        <div class="pl-filter-toolbar-actions">
          <button type="submit" class="btn btn-primary pl-filter-btn pl-filter-btn-primary">
            <i class="bi bi-search me-1" aria-hidden="true"></i>Search
            <?php if (($search ?? '') !== ''): ?><span class="pl-filter-count">1</span><?php endif; ?>
          </button>
          <a href="<?= site_url('new-purchase-grns/list') ?>" class="btn btn-outline-secondary pl-filter-btn pl-filter-btn-soft text-decoration-none">Reset</a>
        </div>
      </div>
    </form>

    <div class="table-responsive">
      <table class="cl-table table table-hover pl-table data-table-table align-middle mb-0">
        <caption class="visually-hidden">Goods receipt notes recorded in CoreLynk</caption>
        <thead>
          <tr>
            <th scope="col" width="42" class="text-center">#</th>
            <th scope="col">GRN Number</th>
            <th scope="col">Vendor</th>
            <th scope="col">PO Number</th>
            <th scope="col">Received Date</th>
            <th scope="col">Warehouse</th>
            <th scope="col">Location</th>
            <th scope="col">Created By</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($grns)): ?>
            <tr>
              <td colspan="8" class="text-center text-muted py-4">
                <i class="bi bi-inbox d-block fs-4 mb-1" aria-hidden="true"></i>
                <?= ($search ?? '') !== '' ? 'No receipt notes match your search.' : 'No goods receipt notes found.' ?>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($grns as $rowIndex => $g): ?>
              <tr>
                <?php $grnRef = !empty($g['public_id']) ? $g['public_id'] : (string)$g['id']; ?>
                <td class="text-center text-muted"><?= $rowIndex + 1 ?></td>
                <td><a href="<?= site_url('new-purchase-grns/detail/'.$grnRef) ?>" class="pl-name text-decoration-none"><?= esc($g['grn_number'] ?: ('GRN-'.$g['id'])) ?></a></td>
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
<?= $this->endSection() ?>
