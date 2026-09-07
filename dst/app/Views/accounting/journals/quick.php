<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Quick Journal<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row">
    <div class="col-12 col-lg-8">
      <div class="card">
        <div class="card-header d-flex align-items-center gap-3">
          <div class="section-icon section-accent-green"><i class="bi bi-journal-text"></i></div>
          <div>
            <h5 class="mb-0 section-title">Quick Journal</h5>
            <small class="section-sub">Post a simple two-line journal entry</small>
          </div>
        </div>
        <div class="card-body">
          <?php if(session()->getFlashdata('error')): ?>
            <div class="alert alert-danger py-2 mb-3"><i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
          <?php endif; ?>
          <?php if(session()->getFlashdata('success')): ?>
            <div class="alert alert-success py-2 mb-3"><i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
          <?php endif; ?>
          <form method="post" action="<?= base_url('accounting/journals/quick') ?>">
            <?= csrf_field() ?>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">Date</label>
                <input type="date" name="entry_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-md-8">
                <label class="form-label">Memo</label>
                <input type="text" name="memo" class="form-control" placeholder="Description" maxlength="255">
              </div>
              <div class="col-md-6">
                <label class="form-label">Debit Account</label>
                <select name="account_debit" class="form-select" required>
                  <option value="">Select account…</option>
                  <?php foreach (($accounts ?? []) as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"><?= esc($a['code']) ?> — <?= esc($a['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Credit Account</label>
                <select name="account_credit" class="form-select" required>
                  <option value="">Select account…</option>
                  <?php foreach (($accounts ?? []) as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"><?= esc($a['code']) ?> — <?= esc($a['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Amount</label>
                <input type="number" step="0.01" min="0.01" name="amount" class="form-control" required>
              </div>
            </div>
            <div class="mt-4 d-flex justify-content-between gap-2">
              <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-outline-secondary"><i class="bi bi-lightning-charge-fill"></i> Use Journal Lite</a>
              <div>
                <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Post Journal</button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>
<?= $this->endSection() ?>
