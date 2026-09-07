<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $page_title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Employees Management</h2>
            <div class="small text-muted">Manage your staff and roles</div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('employees' . (!empty($showInactive) ? '' : '?inactive=1')) ?>"
               class="btn btn-outline-secondary">
                <i class="bi bi-eye<?= !empty($showInactive) ? '-slash' : '' ?> me-1"></i>
                <?= !empty($showInactive) ? 'Hide inactive' : 'Show inactive' ?>
            </a>
            <a href="<?= base_url('/employees/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>Add Employee
            </a>
        </div>
    </div>

    <div class="card shadow">
        <div class="card-body">
                <?php if (!empty($employeeError)): ?>
                    <div class="alert alert-warning">
                        <strong>Notice:</strong> <?= esc($employeeError) ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($employees)): ?>
                <div class="table-responsive">
                    <table class="cl-table table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Employee Code</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Phone</th>
                                <th>Skills</th>
                                <th>Login</th>
                                <th>Status</th>
                                <th class="actions-col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary"><?= esc($employee['employee_code']) ?></span>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= esc($employee['first_name'] . ' ' . $employee['last_name']) ?></strong>
                                        <?php if (!empty($employee['email'])): ?>
                                        <br><small class="text-muted"><?= esc($employee['email']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if (!empty($employee['department'])): ?>
                                        <span class="badge bg-info"><?= esc($employee['department']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($employee['phone'])): ?>
                                        <i class="bi bi-telephone me-1"></i><?= esc($employee['phone']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($employee['skills'])): ?>
                                        <?php 
                                        $skills = explode(',', $employee['skills']);
                                        foreach ($skills as $skill): 
                                        ?>
                                            <span class="badge bg-success me-1"><?= esc(trim($skill)) ?></span>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <span class="text-muted">No skills assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($employee['user_id'])): ?>
                                        <span class="badge bg-primary" title="Can sign in to CoreLynk"><i class="bi bi-shield-check me-1"></i>Yes</span>
                                    <?php else: ?>
                                        <span class="text-muted small">No access</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($employee['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions-col">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?= base_url('/employees/' . $employee['id']) ?>" 
                                           class="btn btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="<?= base_url('/employees/' . $employee['id'] . '/edit') ?>" 
                                           class="btn btn-outline-secondary" title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <?php if ($employee['is_active']): ?>
                                        <button type="button"
                                                class="btn btn-outline-danger"
                                                onclick="deactivateEmployee(<?= $employee['id'] ?>)"
                                                title="Deactivate (keeps payroll and work history)">
                                            <i class="bi bi-person-x"></i>
                                        </button>
                                        <?php else: ?>
                                        <button type="button"
                                                class="btn btn-outline-success"
                                                onclick="restoreEmployee(<?= $employee['id'] ?>)"
                                                title="Reactivate">
                                            <i class="bi bi-arrow-counterclockwise"></i>
                                        </button>
                                        <button type="button"
                                                class="btn btn-outline-danger"
                                                onclick="deleteEmployee(<?= $employee['id'] ?>, '<?= esc(trim($employee['first_name'] . ' ' . $employee['last_name']), 'js') ?>')"
                                                title="Delete permanently">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-4">
                    <i class="bi bi-people display-1 text-muted"></i>
                    <h4 class="mt-3">No Employees Found</h4>
                    <p class="text-muted">Get started by adding your first employee to the system.</p>
                    <a href="<?= base_url('/employees/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add First Employee
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Every action answers JSON; a redirect here used to surface as "Network error".
function employeeAction(url, method, okMessage) {
    return fetch(url, {
        method: method,
        headers: {'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'}
    })
    .then(function (r) { return r.json().then(function (d) { return {ok: r.ok, data: d}; }); })
    .then(function (res) {
        if (res.ok && res.data.success) {
            location.reload();
        } else {
            alert(res.data.message || okMessage);
        }
    })
    .catch(function () { alert('Network error occurred'); });
}

function deactivateEmployee(id) {
    if (confirm('Deactivate this employee? Their payslips and history stay intact and you can reactivate them later.')) {
        employeeAction('<?= base_url('/employees') ?>/' + id, 'DELETE', 'Failed to deactivate employee');
    }
}

function restoreEmployee(id) {
    employeeAction('<?= base_url('/employees') ?>/' + id + '/restore', 'POST', 'Failed to reactivate employee');
}

function deleteEmployee(id, name) {
    if (confirm('Permanently delete ' + name + '? This cannot be undone, and only works if they have no salary slips, cheques or work history.')) {
        employeeAction('<?= base_url('/employees') ?>/' + id + '/permanent', 'DELETE', 'Failed to delete employee');
    }
}
</script>

<?= $this->endSection() ?>
