<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container py-4">
    <h3>Development Tools — Clear Data</h3>
    <p class="text-muted">This page is available only in development. Use with care. To clear a module's data type <code>CLEAR_NOW</code> in the confirmation box and press the button.</p>

    <div class="mb-3">
        <h6>Application Environment</h6>
        <p class="small text-muted">Current environment: <strong><?= esc(defined('ENVIRONMENT') ? ENVIRONMENT : ($_SERVER['CI_ENVIRONMENT'] ?? 'unknown')) ?></strong></p>

        <form method="post" action="<?= base_url('/devtools/set-env') ?>" class="row g-2 align-items-end" style="max-width:480px;">
            <?= csrf_field() ?>
            <div class="col-6">
                <label class="form-label small">Set environment</label>
                <select name="env" class="form-select form-select-sm">
                    <option value="development">development</option>
                    <option value="testing">testing</option>
                    <option value="production">production</option>
                </select>
            </div>
            <div class="col-4">
                <label class="form-label small">Confirmation token</label>
                <input name="confirm_token" type="text" class="form-control form-control-sm" placeholder="Type SET_ENV">
            </div>
            <div class="col-2">
                <button class="btn btn-outline-primary btn-sm">Set</button>
            </div>
        </form>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <div class="row">
        <?php foreach ($modules as $key => $desc): ?>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-capitalize"><?= esc($key) ?></h5>
                        <p class="card-text small text-muted"><?= esc($desc) ?></p>

                        <form method="post" action="<?= base_url('/devtools/clear-data') ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="module" value="<?= esc($key) ?>">
                            <div class="mb-2">
                                <label class="form-label small">Confirmation token</label>
                                <input name="confirm_token" type="text" class="form-control form-control-sm" placeholder="Type CLEAR_NOW to enable">
                            </div>
                            <button class="btn btn-danger btn-sm" onclick="return confirm('Are you sure? This will permanently delete data for <?= esc($key) ?> (irreversible).')">Clear <?= esc($key) ?> data</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?= $this->endSection() ?>
