<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Security Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Security Settings</h4>
        <a href="<?= base_url('admin/security/auth-logs') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-journal-text me-1"></i>View Auth Logs
        </a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= esc(session()->getFlashdata('success')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= esc(session()->getFlashdata('error')) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif ?>

    <div class="card">
        <div class="card-header">
            <h6 class="mb-0">Feature Flags</h6>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width:35%">Feature</th>
                        <th>Description</th>
                        <th style="width:100px" class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($flags)): ?>
                        <tr><td colspan="3" class="text-center text-muted py-4">
                            No feature flags configured. Run the security migration first:<br>
                            <code>php spark migrate</code>
                        </td></tr>
                    <?php else: ?>
                        <?php foreach ($flags as $key => $flag): ?>
                            <tr>
                                <td><code><?= esc($key) ?></code></td>
                                <td class="text-muted small"><?= esc($flag['description']) ?></td>
                                <td class="text-center">
                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                        <input class="form-check-input flag-toggle"
                                               type="checkbox"
                                               data-flag="<?= esc($key) ?>"
                                               <?= $flag['enabled'] ? 'checked' : '' ?>
                                               role="switch">
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    <?php endif ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Security Status</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">Password Hashing</div>
                        <span class="badge bg-success mt-1">Argon2id / Bcrypt</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">Session Security</div>
                        <span class="badge bg-success mt-1">DB-backed, Regenerate on Login</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">Login Rate Limiting</div>
                        <span class="badge bg-success mt-1">5 attempts / 15 min</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">Cookie Flags</div>
                        <span class="badge bg-success mt-1">HttpOnly, SameSite=Lax, Secure=Auto</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">RBAC</div>
                        <span class="badge bg-success mt-1">PolicyEngine Active</span>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="border rounded p-3 text-center">
                        <div class="text-muted small">Environment</div>
                        <span class="badge bg-<?= ENVIRONMENT === 'production' ? 'success' : 'warning text-dark' ?> mt-1">
                            <?= esc(ENVIRONMENT) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
document.querySelectorAll('.flag-toggle').forEach(toggle => {
    toggle.addEventListener('change', async function() {
        const flagKey = this.dataset.flag;
        const enabled = this.checked ? 1 : 0;
        try {
            const resp = await fetch('<?= base_url('admin/security/toggle-flag') ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': '<?= csrf_hash() ?>'
                },
                body: `flag_key=${encodeURIComponent(flagKey)}&enabled=${enabled}&<?= csrf_token() ?>=<?= csrf_hash() ?>`
            });
            const data = await resp.json();
            if (!data.success) {
                alert('Failed to toggle flag: ' + (data.message || 'Unknown error'));
                this.checked = !this.checked;
            }
        } catch (e) {
            alert('Network error toggling flag.');
            this.checked = !this.checked;
        }
    });
});
</script>
<?= $this->endSection() ?>
