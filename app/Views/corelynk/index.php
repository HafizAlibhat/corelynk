<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Home<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-3">
                        <div class="section-icon section-accent-primary"><i class="bi bi-grid"></i></div>
                        <div>
                            <h5 class="mb-0 section-title">Corelynk</h5>
                            <small class="section-sub">Unified ERP shell – choose a module</small>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <style>
                        .module-tile { display:block; text-decoration:none; color:inherit; }
                        .module-tile .tile-inner { transition: transform .12s ease, box-shadow .12s ease; }
                        .module-tile:hover .tile-inner { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(2,6,23,0.15); }
                        .module-tile .tile-actions { text-align:right; }
                    </style>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <a href="<?= base_url('dashboard') ?>" class="module-tile">
                                <div class="p-4 border rounded-3 h-100 d-flex flex-column justify-content-between bg-light-subtle tile-inner">
                                    <div>
                                        <h6 class="fw-bold mb-1"><i class="bi bi-gear-wide-connected me-2"></i>Production</h6>
                                        <p class="text-muted small mb-3">Manage work orders, processes, batches, logs and operational reporting.</p>
                                    </div>
                                    <div class="tile-actions">
                                        <small class="text-muted">Open Production module</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                        <div class="col-md-6">
                            <a href="<?= base_url('accounting') ?>" class="module-tile">
                                <div class="p-4 border rounded-3 h-100 d-flex flex-column justify-content-between bg-light-subtle tile-inner">
                                    <div>
                                        <h6 class="fw-bold mb-1"><i class="bi bi-calculator me-2"></i>Accounting</h6>
                                        <p class="text-muted small mb-3">Financials module (Phase 1 skeleton). Multi-currency, tax, journals coming soon.</p>
                                    </div>
                                    <div class="tile-actions">
                                        <small class="text-muted">Enter Accounting module</small>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    <hr class="my-4" />
                    <div class="alert alert-info d-flex align-items-center" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <div>
                            Folder + DB rename in progress. This shell will anchor cross-module navigation.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>