<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Edit Account<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-7 col-xl-6">
      <div class="card">
        <div class="card-header d-flex align-items-center gap-3">
          <div class="section-icon section-accent-amber"><i class="bi bi-wallet2"></i></div>
          <div>
            <h5 class="mb-0 section-title">Edit Account</h5>
            <small class="section-sub">Modify code, name, type or hierarchy</small>
          </div>
        </div>
        <div class="card-body">
          <form method="post" action="<?= base_url('accounting/accounts/'.$account['id'].'/update') ?>">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label">Code</label>
              <input type="text" name="code" value="<?= esc($account['code']) ?>" class="form-control" required maxlength="32">
            </div>
            <div class="mb-3">
              <label class="form-label">Name</label>
              <input type="text" name="name" value="<?= esc($account['name']) ?>" class="form-control" required maxlength="128">
            </div>
            <div class="mb-3">
              <label class="form-label">Type</label>
              <select name="type" class="form-select" required>
                <?php foreach ($types as $t): ?>
                  <option value="<?= esc($t) ?>" <?= ($t===$account['type']?'selected':'') ?>><?= esc($t) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Currency</label>
              <select name="currency_code" class="form-select">
                <option value="">—</option>
                <?php foreach ($currencies as $c): ?>
                  <option value="<?= esc($c['code']) ?>" <?= ($c['code']===($account['currency_code']??'')?'selected':'') ?>><?= esc($c['code']) ?> — <?= esc($c['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3 form-check">
              <input type="checkbox" class="form-check-input" id="is_bank" name="is_bank" value="1" <?= !empty($account['is_bank']) ? 'checked' : '' ?>>
              <label class="form-check-label" for="is_bank"><i class="bi bi-bank2 me-1"></i>Is Bank Account?</label>
              <div class="form-text">Tick if this account represents a bank account (for cheque module).</div>
            </div>
            <!-- Parent selection removed; use drag-and-drop on the main tree to organize hierarchy -->
            <div class="d-flex justify-content-between mt-4">
              <a href="<?= base_url('accounting/accounts') ?>" class="btn btn-outline-secondary">Back</a>
              <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
