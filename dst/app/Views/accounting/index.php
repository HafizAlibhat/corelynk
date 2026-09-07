<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Accounting<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center gap-3">
                    <div class="section-icon section-accent-amber"><i class="bi bi-calculator"></i></div>
                    <div>
                        <h5 class="mb-0 section-title">Accounting</h5>
                        <small class="section-sub">Phase 1 skeleton – landing page</small>
                    </div>
                </div>
                <div class="card-body">
                    <p class="mb-3">Welcome to Corelynk Accounting. This is a placeholder page for Phase 1. Navigation and schema scaffolding will follow next.</p>
                    <div class="d-flex gap-2">
                        <a href="<?= base_url('/') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Back to Corelynk</a>
                        <a href="#" class="btn btn-secondary btn-sm disabled" tabindex="-1" aria-disabled="true">Coming soon</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>