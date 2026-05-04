<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Role Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">

    <div class="cl-list-header">
        <div>
            <h2 class="mb-0"><i class="bi bi-shield-lock me-2"></i>Role Management</h2>
            <small class="text-muted">Define roles and assign granular permissions</small>
        </div>
        <a href="<?= base_url('admin/roles/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>New Role
        </a>
    </div>

    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= session('success') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="row g-3">
        <?php foreach ($roles as $r): ?>
        <div class="col-md-6 col-lg-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h5 class="card-title mb-0">
                                <?= esc($r['name']) ?>
                                <?php if ($r['is_system']): ?>
                                    <i class="bi bi-lock-fill text-muted ms-1" title="System role"></i>
                                <?php endif; ?>
                            </h5>
                            <small class="text-muted"><?= esc($r['slug']) ?></small>
                        </div>
                        <span class="badge bg-primary-subtle text-primary"><?= $r['user_count'] ?> user(s)</span>
                    </div>
                    <p class="card-text text-muted small mb-3"><?= esc($r['description'] ?? 'No description') ?></p>
                    <div class="d-flex gap-1 flex-wrap mb-3">
                        <span class="badge bg-secondary-subtle text-secondary"><?= $r['perm_count'] ?> permissions</span>
                    </div>
                </div>
                <div class="card-footer bg-transparent d-flex justify-content-end gap-2">
                    <a href="<?= base_url('admin/roles/' . $r['id'] . '/data-access') ?>" class="btn btn-sm btn-outline-secondary" title="Data Access Controls">
                        <i class="bi bi-sliders me-1"></i>Data
                    </a>
                    <a href="<?= base_url('admin/roles/' . $r['id'] . '/fields') ?>" class="btn btn-sm btn-outline-info" title="Field Permissions">
                        <i class="bi bi-eye-slash me-1"></i>Fields
                    </a>
                    <a href="<?= base_url('admin/roles/' . $r['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                        <i class="bi bi-pencil me-1"></i>Edit
                    </a>
                    <?php if (!$r['is_system']): ?>
                    <a href="<?= base_url('admin/roles/' . $r['id'] . '/delete') ?>"
                       class="btn btn-sm btn-outline-danger" title="Delete"
                       onclick="return confirm('Delete role \'<?= esc($r['name']) ?>\'? This cannot be undone.')">
                        <i class="bi bi-trash"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?= $this->endSection() ?>
