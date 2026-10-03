<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>User Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page cl-list-page--dashboard">

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
                <table class="cl-table table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>Roles</th>
                            <th>Employee</th>
                            <th>Status</th>
                            <th>Last Login</th>
                            <th class="text-center" style="width:160px">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($users)): ?>
                        <tr><td colspan="8"><div class="cl-list-empty"><span class="cl-list-empty__icon"><i class="bi bi-people"></i></span><h6>No users found</h6><p>Try changing the search or create a new user account.</p></div></td></tr>
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
                                    <span class="cl-status-badge cl-status-badge--info"><?= esc($r['name']) ?></span>
                                <?php endforeach; ?>
                                <?php if (empty($u['roles'])): ?>
                                    <span class="cl-status-badge cl-status-badge--neutral"><?= ucfirst($u['role'] ?? 'none') ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (! empty($u['employee'])): ?>
                                    <a href="<?= base_url('employees/' . (int) $u['employee']['id']) ?>" class="text-decoration-none">
                                        <?= esc(trim($u['employee']['first_name'] . ' ' . $u['employee']['last_name'])) ?>
                                    </a>
                                    <div class="small text-muted"><?= esc($u['employee']['employee_code']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small">Not an employee</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                    <span class="cl-status-badge cl-status-badge--success"><i class="bi bi-check-circle me-1"></i>Active</span>
                                <?php else: ?>
                                    <span class="cl-status-badge cl-status-badge--danger"><i class="bi bi-x-circle me-1"></i>Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= $u['last_login'] ? date('d M Y H:i', strtotime($u['last_login'])) : '—' ?></td>
                            <td class="text-center">
                                <a href="<?= base_url('admin/users/' . $u['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary cl-icon-action" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?= form_open('admin/users/' . $u['id'] . '/toggle', [
                                    'class'    => 'd-inline',
                                    'onsubmit' => "return confirm('" . ($u['is_active'] ? 'Deactivate' : 'Activate') . " this user?')",
                                ]) ?>
                                    <button class="btn btn-sm btn-outline-<?= $u['is_active'] ? 'warning' : 'success' ?> cl-icon-action"
                                            title="<?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                        <i class="bi bi-<?= $u['is_active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                                    </button>
                                <?= form_close() ?>
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

<?= $this->endSection() ?>
