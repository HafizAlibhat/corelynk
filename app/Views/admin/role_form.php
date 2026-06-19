<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= isset($role) && $role ? 'Edit' : 'Create' ?> Role<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3" style="max-width:960px">

    <div class="cl-list-header mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-shield-<?= isset($role) && $role ? 'check' : 'plus' ?> me-2"></i><?= isset($role) && $role ? 'Edit' : 'Create' ?> Role</h2>
            <small class="text-muted">Configure role details and module permissions (Read / Write / Edit / Delete)</small>
        </div>
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <?php if (isset($validation)): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= $validation->listErrors() ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php
        $action = isset($role) && $role
            ? base_url('admin/roles/' . $role['id'] . '/update')
            : base_url('admin/roles/store');
    ?>
    <?= form_open($action) ?>

    <div class="card mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-info-circle me-1"></i>Role Details</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Role Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                           value="<?= esc(old('name', $role['name'] ?? '')) ?>" required
                           <?= (isset($role) && $role && $role['is_system']) ? 'readonly' : '' ?>>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Description</label>
                    <input type="text" name="description" class="form-control"
                           value="<?= esc(old('description', $role['description'] ?? '')) ?>"
                           placeholder="Brief description of this role">
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
            <span><i class="bi bi-key me-1"></i>Module Permissions</span>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" onclick="selectAll(true)">Select All</button>
                <button type="button" class="btn btn-outline-secondary" onclick="selectAll(false)">Clear All</button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>Module</th>
                            <th class="text-center" style="width:90px"><i class="bi bi-eye me-1"></i>Read</th>
                            <th class="text-center" style="width:90px"><i class="bi bi-pencil me-1"></i>Write</th>
                            <th class="text-center" style="width:90px"><i class="bi bi-pen me-1"></i>Edit</th>
                            <th class="text-center" style="width:90px"><i class="bi bi-trash me-1"></i>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($permissionGroups as $module => $perms): ?>
                        <tr>
                            <td class="fw-semibold text-capitalize"><?= str_replace('_', ' ', $module) ?></td>
                            <?php
                                $actionOrder = ['read', 'write', 'edit', 'delete', 'sensitive_overview'];
                                $permByAction = [];
                                foreach ($perms as $p) { $permByAction[$p['action']] = $p; }
                            ?>
                            <?php foreach ($actionOrder as $act): ?>
                            <td class="text-center">
                                <?php if (isset($permByAction[$act])): ?>
                                <?php if ($act === 'sensitive_overview'): ?>
                                <input type="checkbox" class="form-check-input"
                                       name="products_sensitive_overview"
                                       value="1"
                                       <?= in_array($permByAction[$act]['id'], $rolePermIds ?? []) ? 'checked' : '' ?>
                                       title="Allow viewing sensitive product overview metrics">
                                <?php else: ?>
                                <input type="checkbox" class="form-check-input perm-cb"
                                       name="permission_ids[]"
                                       value="<?= $permByAction[$act]['id'] ?>"
                                       <?= in_array($permByAction[$act]['id'], $rolePermIds ?? []) ? 'checked' : '' ?>>
                                <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">–</span>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2">
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= isset($role) && $role ? 'Update Role' : 'Create Role' ?>
        </button>
    </div>

    <?= form_close() ?>
</div>

<script>
function selectAll(state) {
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = state);
}
</script>
<?= $this->endSection() ?>
