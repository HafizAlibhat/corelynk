<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Credit Notes<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="d-flex align-items-center gap-3">
            <div class="section-icon section-accent-green"><i class="bi bi-receipt"></i></div>
            <div>
              <h5 class="mb-0">Credit Notes</h5>
              <small class="text-muted">Advances / Adjustments</small>
            </div>
          </div>
          <button class="btn btn-sm btn-primary" data-bs-toggle="collapse" data-bs-target="#cnCreate">New Credit Note</button>
        </div>
        <div class="collapse" id="cnCreate">
          <div class="card-body border-bottom">
            <form method="post" action="<?= site_url('accounting/credit-notes/create') ?>" class="row g-2">
              <div class="col-md-2">
                <select name="party_type" class="form-select form-select-sm" required>
                  <option value="">Party Type</option>
                  <option value="vendor">Vendor</option>
                  <option value="customer">Customer</option>
                </select>
              </div>
              <div class="col-md-3">
                <select name="party_id" class="form-select form-select-sm" required>
                  <option value="">Select Party</option>
                  <?php foreach ($vendors as $v): ?>
                    <option value="<?= esc($v['id']) ?>" data-type="vendor">Vendor: <?= esc($v['name']) ?></option>
                  <?php endforeach; ?>
                  <?php foreach ($customers as $c): ?>
                    <option value="<?= esc($c['id']) ?>" data-type="customer">Customer: <?= esc($c['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <select name="account_id" class="form-select form-select-sm" required>
                  <option value="">Account</option>
                  <?php foreach ($accounts as $a): ?>
                    <option value="<?= esc($a['id']) ?>"><?= esc($a['code']) ?> - <?= esc($a['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-2">
                <input type="number" step="0.01" min="0" name="amount" class="form-control form-control-sm" placeholder="Amount" required />
              </div>
              <div class="col-md-3">
                <input type="text" name="reference" class="form-control form-control-sm" placeholder="Reference" />
              </div>
              <div class="col-12">
                <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Note (optional)"></textarea>
              </div>
              <div class="col-12 text-end">
                <button class="btn btn-sm btn-success">Save</button>
              </div>
            </form>
          </div>
        </div>
        <div class="card-body">
          <div class="table-responsive">
            <table class="table table-sm table-striped mb-0">
              <thead class="table-light">
                <tr>
                  <th>ID</th>
                  <th>Party</th>
                  <th>Account</th>
                  <th class="text-end">Amount</th>
                  <th class="text-end">Applied</th>
                  <th>Status</th>
                  <th>Created</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($notes as $n): ?>
                  <tr>
                    <td><?= esc($n['id']) ?></td>
                    <td><?= esc($n['party_type']) ?> #<?= esc($n['party_id']) ?></td>
                    <td><?= esc($n['account_id']) ?></td>
                    <td class="text-end"><?= number_format($n['amount'],2) ?></td>
                    <td class="text-end"><?= number_format($n['applied_amount'],2) ?></td>
                    <td><span class="badge <?= $n['status']==='open'?'bg-primary':($n['status']==='closed'?'bg-secondary':'bg-warning') ?>"><?= esc($n['status']) ?></span></td>
                    <td><?= esc($n['created_at'] ?? '') ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
