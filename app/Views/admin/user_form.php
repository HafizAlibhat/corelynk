<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= isset($user) && $user ? 'Edit' : 'Create' ?> User<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3" style="max-width:800px">

    <div class="cl-list-header mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-person-<?= isset($user) && $user ? 'gear' : 'plus' ?> me-2"></i><?= isset($user) && $user ? 'Edit' : 'Create' ?> User</h2>
            <small class="text-muted"><?= isset($user) && $user ? 'Update user details & roles' : 'Register a new user account' ?></small>
        </div>
        <a href="<?= base_url('admin/users') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($validation)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $validation->listErrors() ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body">
            <?php
                $action = isset($user) && $user
                    ? base_url('admin/users/' . $user['id'] . '/update')
                    : base_url('admin/users/store');
            ?>
            <?= form_open($action) ?>

                <div class="row g-3">
                    <!-- Username -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Username <span class="text-danger">*</span></label>
                        <input type="text" name="username" class="form-control"
                               value="<?= esc(old('username', $user['username'] ?? '')) ?>"
                               required>
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control"
                               value="<?= esc(old('email', $user['email'] ?? '')) ?>" required>
                    </div>

                    <!-- First Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control"
                               value="<?= esc(old('first_name', $user['first_name'] ?? '')) ?>" required>
                    </div>

                    <!-- Last Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control"
                               value="<?= esc(old('last_name', $user['last_name'] ?? '')) ?>" required>
                    </div>

                    <!-- Password -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Password <?= !(isset($user) && $user) ? '<span class="text-danger">*</span>' : '' ?></label>
                        <input type="password" name="password" class="form-control" minlength="8"
                               <?= !(isset($user) && $user) ? 'required' : '' ?>
                               placeholder="<?= isset($user) && $user ? 'Leave blank to keep current' : 'Min 8 characters' ?>">
                    </div>

                    <!-- Confirm Password -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control"
                               <?= !(isset($user) && $user) ? 'required' : '' ?>>
                    </div>

                    <!-- Roles -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">Roles <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <?php foreach ($allRoles as $r): ?>
                            <div class="col-md-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="role_ids[]"
                                           value="<?= $r['id'] ?>" id="role_<?= $r['id'] ?>"
                                           <?= in_array($r['id'], $userRoles ?? []) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="role_<?= $r['id'] ?>">
                                        <?= esc($r['name']) ?>
                                        <?php if ($r['is_system']): ?>
                                            <i class="bi bi-lock-fill text-muted ms-1" title="System role"></i>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Active -->
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="isActive"
                                   <?= old('is_active', $user['is_active'] ?? 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-semibold" for="isActive">Active Account</label>
                        </div>
                    </div>

                    <!-- Document Privacy Permission -->
                    <?php if (isset($user) && $user): ?>
                    <div class="col-12">
                        <div class="card border-warning-subtle bg-warning-subtle">
                            <div class="card-body py-2 px-3">
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" name="can_make_documents_private"
                                           value="1" id="canPrivate"
                                           <?= !empty($user['can_make_documents_private']) ? 'checked' : '' ?>>
                                    <label class="form-check-label fw-semibold" for="canPrivate">
                                        <i class="bi bi-eye-slash me-1 text-warning"></i>
                                        Allow this user to hide their Quotations &amp; Sales Orders from others
                                    </label>
                                </div>
                                <p class="text-muted small mb-0 mt-1">
                                    When enabled, this user will see a toggle in their <strong>My Settings</strong> to make their documents private.
                                    Only admins can still see their documents.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <hr>
                <div class="d-flex justify-content-end gap-2">
                    <a href="<?= base_url('admin/users') ?>" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1"></i><?= isset($user) && $user ? 'Update User' : 'Create User' ?>
                    </button>
                </div>
            <?= form_close() ?>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
