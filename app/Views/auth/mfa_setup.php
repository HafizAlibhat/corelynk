<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>MFA Setup<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3" style="max-width:640px">

    <div class="cl-list-header mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Two-Factor Authentication</h2>
            <small class="text-muted">Add an extra layer of security to your account</small>
        </div>
        <a href="<?= base_url('/') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    </div>

    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= session('success') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (session()->has('warning')): ?>
        <div class="alert alert-warning alert-dismissible fade show"><i class="bi bi-exclamation-circle me-2"></i><?= session('warning') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Recovery codes (shown once after enable) -->
    <?php $codes = session('recovery_codes'); ?>
    <?php if (!empty($codes)): ?>
    <div class="card border-warning mb-4">
        <div class="card-header bg-warning text-dark fw-semibold">
            <i class="bi bi-exclamation-triangle me-1"></i>Save Your Recovery Codes
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">These codes can be used to access your account if you lose your authenticator device. <strong>Each code can only be used once.</strong> Store them securely.</p>
            <div class="row g-2 mb-3">
                <?php foreach ($codes as $c): ?>
                    <div class="col-6 col-md-3">
                        <code class="d-block text-center bg-light border rounded p-2 fw-bold"><?= esc($c) ?></code>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn btn-sm btn-outline-dark" onclick="copyRecoveryCodes()">
                <i class="bi bi-clipboard me-1"></i>Copy All
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($mfa_enabled): ?>
        <!-- MFA is currently enabled -->
        <div class="card">
            <div class="card-body text-center py-4">
                <div class="mb-3">
                    <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>MFA Enabled</span>
                </div>
                <p class="text-muted">Two-factor authentication is currently active on your account.</p>
                <?= form_open('/auth/mfa-setup') ?>
                    <input type="hidden" name="action" value="disable">
                    <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Are you sure you want to disable MFA? This will reduce your account security.')">
                        <i class="bi bi-shield-x me-1"></i>Disable MFA
                    </button>
                <?= form_close() ?>
            </div>
        </div>
    <?php else: ?>
        <!-- MFA setup flow -->
        <div class="card mb-4">
            <div class="card-header fw-semibold">
                <i class="bi bi-1-circle me-1"></i>Step 1: Scan QR Code
            </div>
            <div class="card-body">
                <p class="text-muted small">Scan the QR code below with your authenticator app (Google Authenticator, Authy, Microsoft Authenticator, etc.)</p>

                <div class="text-center mb-3">
                    <!-- QR code via public API -->
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?= urlencode($otp_uri) ?>"
                         alt="QR Code" class="border rounded p-2" style="max-width:220px" id="qrCode">
                </div>

                <div class="text-center">
                    <p class="small text-muted mb-1">Can't scan? Enter this key manually:</p>
                    <code class="user-select-all fw-bold" style="font-size:1rem;letter-spacing:.1em"><?= esc($secret_b32) ?></code>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header fw-semibold">
                <i class="bi bi-2-circle me-1"></i>Step 2: Verify Code
            </div>
            <div class="card-body">
                <p class="text-muted small">Enter the 6-digit code from your authenticator app to confirm setup.</p>

                <?= form_open('/auth/mfa-setup') ?>
                    <input type="hidden" name="secret" value="<?= esc($secret_b64) ?>">
                    <input type="hidden" name="action" value="enable">

                    <div class="row g-2 align-items-end">
                        <div class="col">
                            <input type="text" name="code" class="form-control form-control-lg text-center"
                                   maxlength="6" inputmode="numeric" placeholder="000000"
                                   autocomplete="one-time-code" autofocus required
                                   style="letter-spacing:.4em;font-weight:600">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-check-lg me-1"></i>Enable MFA
                            </button>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function copyRecoveryCodes() {
    const codes = [];
    document.querySelectorAll('.border-warning code').forEach(el => codes.push(el.textContent.trim()));
    navigator.clipboard.writeText(codes.join('\n')).then(() => {
        alert('Recovery codes copied to clipboard!');
    });
}
</script>
<?= $this->endSection() ?>
