<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Field Permissions: <?= esc($role['name']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3" style="max-width:960px">

    <div class="cl-list-header mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-eye-slash me-2"></i>Field Permissions</h2>
            <small class="text-muted">Control which data fields are visible, masked, or hidden for <strong><?= esc($role['name']) ?></strong></small>
        </div>
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Roles</a>
    </div>

    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= session('success') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="bi bi-info-circle me-2"></i>
        <strong>Visible</strong> = field shown normally &nbsp;|&nbsp;
        <strong>Masked</strong> = field replaced with *** &nbsp;|&nbsp;
        <strong>Hidden</strong> = field removed entirely from the response &amp; UI
    </div>

    <?= form_open(base_url('admin/roles/' . $role['id'] . '/fields/save')) ?>

    <?php foreach ($maskableFields as $module => $fields): ?>
    <div class="card mb-3">
        <div class="card-header fw-semibold text-capitalize">
            <i class="bi bi-database me-1"></i><?= str_replace('_', ' ', $module) ?>
        </div>
        <div class="card-body p-0">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Field</th>
                        <th class="text-center" style="width:120px">Visible</th>
                        <th class="text-center" style="width:120px">Masked (***)</th>
                        <th class="text-center" style="width:120px">Hidden</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($fields as $fieldName => $fieldLabel): ?>
                    <?php $current = $currentRules[$module][$fieldName]['visibility'] ?? 'visible'; ?>
                    <tr>
                        <td><?= esc($fieldLabel) ?> <code class="ms-2 text-muted small"><?= esc($fieldName) ?></code></td>
                        <td class="text-center">
                            <input type="radio" class="form-check-input" name="fields[<?= $module ?>][<?= $fieldName ?>]"
                                   value="visible" <?= $current === 'visible' ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="radio" class="form-check-input" name="fields[<?= $module ?>][<?= $fieldName ?>]"
                                   value="masked" <?= $current === 'masked' ? 'checked' : '' ?>>
                        </td>
                        <td class="text-center">
                            <input type="radio" class="form-check-input" name="fields[<?= $module ?>][<?= $fieldName ?>]"
                                   value="hidden" <?= $current === 'hidden' ? 'checked' : '' ?>>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= base_url('admin/roles') ?>" class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i>Save Field Permissions
        </button>
    </div>

    <?= form_close() ?>
</div>
<?= $this->endSection() ?>
