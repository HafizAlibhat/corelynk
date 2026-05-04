<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Gate Pass Details<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">
      <i class="bi bi-file-text me-2"></i>
      Gate Pass <?= esc($pass['gate_pass_number'] ?? ('#'.$pass['id'])) ?>
    </h2>
    <div>
      <a class="btn btn-outline-secondary" href="<?= base_url('gate_passes') ?>"><i class="bi bi-arrow-left me-1"></i>Back</a>
      <a class="btn btn-primary" target="_blank" href="<?= base_url('gate_passes/'.$pass['id'].'/pdf') ?>"><i class="bi bi-printer me-1"></i>Print PDF</a>
    </div>
  </div>

  <div class="row g-3">
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header bg-white">
          <strong>Details</strong>
        </div>
        <div class="card-body">
          <div class="row mb-2">
            <div class="col-md-6"><strong>Type:</strong> <?= ucfirst($pass['type']) ?></div>
            <div class="col-md-6"><strong>Status:</strong> <?= ucfirst($pass['status']) ?></div>
          </div>
          <div class="row mb-2">
            <div class="col-md-6"><strong>Created:</strong> <?= date('M d, Y H:i', strtotime($pass['created_at'])) ?></div>
            <div class="col-md-6"><strong>Expected:</strong> <?= $pass['expected_date'] ? date('M d, Y H:i', strtotime($pass['expected_date'])) : '—' ?></div>
          </div>
          <div class="mb-2"><strong>Purpose:</strong><br><?= esc($pass['purpose'] ?? '') ?></div>
          <?php if (!empty($pass['notes'])): ?>
          <div class="mb-2"><strong>Notes:</strong><br><?= esc($pass['notes']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm mt-3">
        <div class="card-header bg-white"><strong>Items / Materials</strong></div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table mb-0">
              <thead class="table-light">
                <tr>
                  <th>#</th>
                  <th>Description</th>
                  <th class="text-end">Qty</th>
                  <th>Unit</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($pass['items_decoded'])): ?>
                  <tr><td colspan="5" class="text-muted">No items</td></tr>
                <?php else: foreach ($pass['items_decoded'] as $i => $item): ?>
                  <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= esc($item['description'] ?? '') ?></td>
                    <td class="text-end"><?= esc($item['quantity'] ?? '') ?></td>
                    <td><?= esc($item['unit'] ?? '') ?></td>
                    <td><?= esc($item['remarks'] ?? '') ?></td>
                  </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm">
        <div class="card-header bg-white"><strong>Vendor</strong></div>
        <div class="card-body">
          <div><strong>Name:</strong> <?= esc($pass['vendor_name'] ?? '—') ?></div>
          <div><strong>Contact:</strong> <?= esc($pass['contact_person'] ?? '—') ?></div>
          <div><strong>Phone:</strong> <?= esc($pass['vendor_phone'] ?? '—') ?></div>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
