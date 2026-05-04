<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Global Search<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">
  <div class="cl-list-header">
    <div>
      <h2 class="mb-0">Global Search</h2>
      <small class="text-muted">Quickly find PO, RFQ, quotations, sales orders, customers, products and more.</small>
    </div>
  </div>

  <div class="cl-list-filters">
    <form method="get" action="<?= site_url('search') ?>" class="row g-2 align-items-end">
      <div class="col-md-8">
        <label class="form-label">Search</label>
        <input type="search" name="q" class="form-control" value="<?= esc($query ?? '') ?>" placeholder="Type PO number, quotation, customer, product..." autofocus>
      </div>
      <div class="col-md-4 d-flex gap-2">
        <button class="btn btn-primary" type="submit"><i class="bi bi-search me-1"></i>Search</button>
        <a class="btn btn-outline-secondary" href="<?= site_url('search') ?>">Clear</a>
      </div>
    </form>
  </div>

  <div class="card cl-list-table-card">
    <div class="card-body p-0">
      <?php if (empty($query)): ?>
        <div class="p-4 text-muted">Start typing above to search across the whole system.</div>
      <?php elseif (empty($results)): ?>
        <div class="p-4 text-muted">No matching records found for "<?= esc($query) ?>".</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table mb-0">
            <thead>
              <tr>
                <th style="width:180px">Module</th>
                <th>Record</th>
                <th>Details</th>
                <th class="text-end" style="width:140px">Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($results as $item): ?>
                <tr>
                  <td>
                    <span class="badge bg-light text-dark border">
                      <i class="bi <?= esc($item['icon'] ?? 'bi-search') ?> me-1"></i><?= esc($item['module'] ?? 'Result') ?>
                    </span>
                  </td>
                  <td class="fw-semibold"><?= esc($item['title'] ?? '') ?></td>
                  <td class="text-muted"><?= esc($item['subtitle'] ?? '') ?></td>
                  <td class="text-end">
                    <a href="<?= esc($item['url'] ?? '#') ?>" class="btn btn-sm btn-outline-primary">Open</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
