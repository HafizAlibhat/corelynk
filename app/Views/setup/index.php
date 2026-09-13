<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set up CoreLynk</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background:#0b0a17; color:#e9e9f5; }
        .setup-card { max-width: 640px; margin: 4rem auto; background:#141326; border:1px solid #2a2846; border-radius: 14px; }
        .nav-tabs .nav-link { color:#9a97c2; }
        .nav-tabs .nav-link.active { background:#1c1a34; color:#fff; border-color:#2a2846 #2a2846 #1c1a34; }
        .form-control, .form-select { background:#0f0e1f; border-color:#2a2846; color:#e9e9f5; }
        .form-control:focus, .form-select:focus { background:#0f0e1f; color:#fff; border-color:#6c63ff; box-shadow:0 0 0 .2rem rgba(108,99,255,.25); }
    </style>
</head>
<body>
<div class="container">
    <div class="setup-card p-4 p-md-5">
        <div class="text-center mb-4">
            <h1 class="h3 mb-1">Welcome to CoreLynk</h1>
            <p class="text-secondary mb-0">No admin account exists yet — set this instance up before continuing.</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-danger small"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success small"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php $validationErrors = session('validation'); ?>

        <ul class="nav nav-tabs mb-4" role="tablist">
            <li class="nav-item"><button class="nav-link <?= $active_tab === 'fresh' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-fresh" type="button">Fresh Install</button></li>
            <li class="nav-item"><button class="nav-link <?= $active_tab === 'restore' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-restore" type="button">Restore From Backup</button></li>
        </ul>

        <div class="tab-content">
            <div class="tab-pane fade <?= $active_tab === 'fresh' ? 'show active' : '' ?>" id="tab-fresh">
                <p class="text-secondary small">Builds the database schema and creates your company profile + first admin user.</p>
                <form method="post" action="<?= site_url('setup/fresh') ?>">
                    <?= csrf_field() ?>
                    <h6 class="text-uppercase text-secondary small mt-3 mb-2">Company</h6>
                    <div class="mb-2">
                        <label class="form-label small">Company name</label>
                        <input type="text" name="company_name" class="form-control form-control-sm" value="<?= old('company_name') ?>" required>
                        <?php if ($validationErrors && $validationErrors->hasError('company_name')): ?><div class="text-danger small"><?= $validationErrors->getError('company_name') ?></div><?php endif; ?>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">Phone (optional)</label>
                            <input type="text" name="company_phone" class="form-control form-control-sm" value="<?= old('company_phone') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Company email (optional)</label>
                            <input type="email" name="company_email" class="form-control form-control-sm" value="<?= old('company_email') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Address (optional)</label>
                        <input type="text" name="company_address" class="form-control form-control-sm" value="<?= old('company_address') ?>">
                    </div>

                    <h6 class="text-uppercase text-secondary small mt-3 mb-2">Admin account</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">First name</label>
                            <input type="text" name="admin_first_name" class="form-control form-control-sm" value="<?= old('admin_first_name') ?>" required>
                            <?php if ($validationErrors && $validationErrors->hasError('admin_first_name')): ?><div class="text-danger small"><?= $validationErrors->getError('admin_first_name') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Last name</label>
                            <input type="text" name="admin_last_name" class="form-control form-control-sm" value="<?= old('admin_last_name') ?>" required>
                            <?php if ($validationErrors && $validationErrors->hasError('admin_last_name')): ?><div class="text-danger small"><?= $validationErrors->getError('admin_last_name') ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small">Username</label>
                            <input type="text" name="admin_username" class="form-control form-control-sm" value="<?= old('admin_username') ?>" required>
                            <?php if ($validationErrors && $validationErrors->hasError('admin_username')): ?><div class="text-danger small"><?= $validationErrors->getError('admin_username') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Email</label>
                            <input type="email" name="admin_email" class="form-control form-control-sm" value="<?= old('admin_email') ?>" required>
                            <?php if ($validationErrors && $validationErrors->hasError('admin_email')): ?><div class="text-danger small"><?= $validationErrors->getError('admin_email') ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small">Password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="admin_password" id="adminPassword" class="form-control form-control-sm" required>
                                <button type="button" class="btn btn-outline-secondary js-toggle-pw" data-target="adminPassword" tabindex="-1"><i class="bi bi-eye"></i></button>
                            </div>
                            <?php if ($validationErrors && $validationErrors->hasError('admin_password')): ?><div class="text-danger small"><?= $validationErrors->getError('admin_password') ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Confirm password</label>
                            <div class="input-group input-group-sm">
                                <input type="password" name="admin_password_confirm" id="adminPasswordConfirm" class="form-control form-control-sm" required>
                                <button type="button" class="btn btn-outline-secondary js-toggle-pw" data-target="adminPasswordConfirm" tabindex="-1"><i class="bi bi-eye"></i></button>
                            </div>
                            <div class="form-text" id="pwMatchHint"></div>
                            <?php if ($validationErrors && $validationErrors->hasError('admin_password_confirm')): ?><div class="text-danger small"><?= $validationErrors->getError('admin_password_confirm') ?></div><?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100" id="freshInstallBtn"><i class="bi bi-rocket-takeoff me-1"></i>Install CoreLynk</button>
                </form>
            </div>

            <div class="tab-pane fade <?= $active_tab === 'restore' ? 'show active' : '' ?>" id="tab-restore">
                <p class="text-secondary small">Upload a CoreLynk backup .zip (created from Settings → Backups) to restore its database — schema, company, users and all data come from the backup.</p>
                <form method="post" action="<?= site_url('setup/restore') ?>" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label class="form-label small">Backup file (.zip)</label>
                        <input type="file" name="backup_file" accept=".zip" class="form-control form-control-sm" required>
                    </div>
                    <button type="submit" class="btn btn-outline-light w-100" id="restoreBtn"><i class="bi bi-cloud-arrow-up me-1"></i>Restore &amp; Finish Setup</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('freshInstallBtn').closest('form').addEventListener('submit', function(e) {
    var btn = document.getElementById('freshInstallBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Installing...';
});
document.getElementById('restoreBtn').closest('form').addEventListener('submit', function(e) {
    var btn = document.getElementById('restoreBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Restoring... this can take a few minutes';
});

document.querySelectorAll('.js-toggle-pw').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var input = document.getElementById(btn.getAttribute('data-target'));
        var showing = input.type === 'text';
        input.type = showing ? 'password' : 'text';
        btn.querySelector('i').className = showing ? 'bi bi-eye' : 'bi bi-eye-slash';
    });
});

(function () {
    var pw = document.getElementById('adminPassword');
    var confirm = document.getElementById('adminPasswordConfirm');
    var hint = document.getElementById('pwMatchHint');
    function checkMatch() {
        if (!confirm.value) { hint.textContent = ''; return; }
        hint.textContent = pw.value === confirm.value ? 'Passwords match.' : 'Passwords do not match yet.';
        hint.className = 'form-text ' + (pw.value === confirm.value ? 'text-success' : 'text-warning');
    }
    pw.addEventListener('input', checkMatch);
    confirm.addEventListener('input', checkMatch);
})();
</script>
</body>
</html>
