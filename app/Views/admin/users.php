<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>User Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">

    <!-- Page Header -->
    <div class="cl-list-header">
        <div>
            <h2 class="mb-0"><i class="bi bi-people me-2"></i>User Management</h2>
            <small class="text-muted">Create, edit and manage user accounts & role assignments</small>
        </div>
        <a href="<?= base_url('admin/users/create') ?>" class="btn btn-primary">
            <i class="bi bi-person-plus me-2"></i>New User
        </a>
    </div>

    <!-- Flash Messages -->
    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= session('success') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Search -->
    <div class="card cl-list-filters mb-3">
        <div class="card-body">
            <?= form_open('admin/users', ['method' => 'GET', 'class' => 'row g-3']) ?>
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" name="search"
                           value="<?= esc($search ?? '') ?>"
                           placeholder="Name, email, username...">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2"><i class="bi bi-search me-1"></i>Search</button>
                    <a href="<?= base_url('admin/users') ?>" class="btn btn-outline-secondary">Clear</a>
                </div>
            <?= form_close() ?>
        </div>
    </div>

    <!-- Users Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-center" style="width:160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td class="text-muted"><?= $u['id'] ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="cl-avatar-sm"><?= strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1)) ?></div>
                                    <div>
                                        <div class="fw-semibold"><?= esc($u['first_name'] . ' ' . $u['last_name']) ?></div>
                                        <small class="text-muted">@<?= esc($u['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= esc($u['email']) ?></td>
                            <td>
                                <?php foreach ($u['roles'] as $r): ?>
                                    <span class="badge bg-primary-subtle text-primary"><?= esc($r['name']) ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($u['roles'])): ?>
                                    <span class="badge bg-secondary-subtle text-secondary"><?= ucfirst($u['role'] ?? 'none') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '—' ?></td>
                            <td class="text-center">
                                <a href="<?= base_url('admin/users/' . $u['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?= base_url('admin/users/' . $u['id'] . '/toggle') ?>"
                                   class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?>"
                                   title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>"
                                   onclick="return confirm('<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?> this user?')">
                                    <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($pager)): ?>
        <div class="card-footer d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <?= count($users) ?> user(s)</small>
            <?= $pager->links('default', 'default_full') ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.cl-avatar-sm {
    width: 36px; height: 36px; border-radius: 50%;
    background: var(--cl-primary, var(--primary-color, #4f46e5));
    color: #fff; font-size: 0.75rem; font-weight: 600;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
</style>
<?= $this->endSection() ?>
