<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>My Settings<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3" style="max-width: 640px;">
    <div class="d-flex align-items-center gap-2 mb-3">
        <i class="bi bi-gear fs-4 text-primary"></i>
        <h2 class="mb-0">My Settings</h2>
    </div>

    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-2"></i><?= session('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="post" action="<?= base_url('/auth/settings') ?>">
        <?= csrf_field() ?>

        <!-- Document Privacy -->
        <div class="card mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-eye-slash me-2"></i>Document Privacy</div>
            <div class="card-body">
                <?php if ($can_private): ?>
                    <p class="text-muted small mb-3">
                        When enabled, your quotations, sales orders, purchase RFQs and purchase orders will be
                        hidden from other users. Only you and administrators will be able to see them.
                    </p>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="documents_private" name="documents_private"
                               value="1" <?= $documents_private ? 'checked' : '' ?>>
                        <label class="form-check-label fw-medium" for="documents_private">
                            Make my documents private
                        </label>
                    </div>
                    <p class="text-muted small mt-2 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        This affects list views and direct document access for all other non-admin users.
                    </p>
                <?php else: ?>
                    <p class="text-muted small mb-0">
                        <i class="bi bi-lock me-2"></i>
                        Document privacy is not enabled for your role. Contact your administrator to request this permission.
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary" <?= $can_private ? '' : 'disabled' ?>>
                <i class="bi bi-check-lg me-1"></i>Save Settings
            </button>
            <a href="<?= base_url('/') ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</div>
<?= $this->endSection() ?>
