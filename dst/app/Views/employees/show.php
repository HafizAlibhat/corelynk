<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Employee Details - <?= esc($employee['employee_code'] ?? '') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * @var array|null $employee
 * @var array      $skills
 * @var array      $salaryHistory  latest 24 slips, newest first
 */
helper('currency');
?>

<?php if (! empty($employeeError)): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= esc($employeeError) ?></div>
<?php endif; ?>

<?php if (empty($employee)): ?>
    <div class="alert alert-danger">Employee not found. <a href="<?= base_url('employees') ?>">Back to list</a></div>
<?php else: ?>
<?php
$name       = trim($employee['first_name'] . ' ' . $employee['last_name']);
$initials   = strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1));
$currency   = $employee['salary_currency'] ?: base_currency_code();
$salary     = $employee['monthly_salary'] ?? null;
$joined     = ! empty($employee['joining_date']) ? strtotime($employee['joining_date']) : null;
$thisYear   = date('Y');

// Year-to-date pay and anything still owed, straight off the slip history.
$ytdPaid = [];
$owed    = [];
foreach ($salaryHistory as $slip) {
    $code   = $slip['currency_code'] ?: $currency;
    $amount = (float) $slip['net_amount'];
    if ($slip['status'] === 'paid') {
        if (date('Y', strtotime($slip['period_month'])) === $thisYear) {
            $ytdPaid[$code] = ($ytdPaid[$code] ?? 0) + $amount;
        }
    } else {
        $owed[$code] = ($owed[$code] ?? 0) + $amount;
    }
}
$fmtGroup = static function (array $byCurrency) use ($currency) {
    if (! $byCurrency) {
        return format_money(0, $currency);
    }
    $parts = [];
    foreach ($byCurrency as $code => $amount) {
        $parts[] = format_money($amount, $code);
    }

    return implode(' + ', $parts);
};

$tenure = '—';
if ($joined) {
    $months = (int) floor((time() - $joined) / (30.44 * 86400));
    $tenure = $months < 12
        ? $months . ' month' . ($months === 1 ? '' : 's')
        : intdiv($months, 12) . 'y ' . ($months % 12) . 'm';
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-3">
        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold"
             style="width:56px;height:56px;font-size:1.25rem;"><?= esc($initials) ?></div>
        <div>
            <h4 class="mb-0"><?= esc($name) ?>
                <span class="badge <?= $employee['is_active'] ? 'bg-success' : 'bg-danger' ?> align-middle">
                    <?= $employee['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
            </h4>
            <div class="text-muted small">
                <?= esc($employee['employee_code']) ?>
                <?= ! empty($employee['designation']) ? ' · ' . esc($employee['designation']) : '' ?>
                <?= ! empty($employee['department']) ? ' · ' . esc($employee['department']) : '' ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('employees/payroll') ?>" class="btn btn-outline-primary">
            <i class="bi bi-cash-stack me-1"></i>Pay salary
        </a>
        <a href="<?= base_url('employees/' . $employee['id'] . '/edit') ?>" class="btn btn-warning">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="<?= base_url('employees') ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Monthly salary</div>
            <div class="fs-5 fw-bold">
                <?= $salary !== null ? esc(format_money((float) $salary, $currency)) : '<span class="text-muted fs-6">Not set</span>' ?>
            </div>
            <?php if ($salary === null): ?>
                <a class="small" href="<?= base_url('employees/' . $employee['id'] . '/edit') ?>">Set it to enable payroll</a>
            <?php endif; ?>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Paid in <?= esc($thisYear) ?></div>
            <div class="fs-5 fw-bold text-success"><?= esc($fmtGroup($ytdPaid)) ?></div>
            <div class="small text-muted">year to date</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Unpaid slips</div>
            <div class="fs-5 fw-bold <?= $owed ? 'text-danger' : '' ?>"><?= esc($fmtGroup($owed)) ?></div>
            <div class="small text-muted">awaiting payment</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Time with company</div>
            <div class="fs-5 fw-bold"><?= esc($tenure) ?></div>
            <div class="small text-muted">
                <?= $joined ? 'since ' . esc(date('d M Y', $joined)) : 'joining date not set' ?>
            </div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-person-lines-fill me-2 text-primary"></i>Contact</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Phone</span>
                    <span><?= ! empty($employee['phone'])
                        ? '<a href="tel:' . esc($employee['phone'], 'attr') . '">' . esc($employee['phone']) . '</a>'
                        : '<span class="text-muted">—</span>' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Email</span>
                    <span><?= ! empty($employee['email'])
                        ? '<a href="mailto:' . esc($employee['email'], 'attr') . '">' . esc($employee['email']) . '</a>'
                        : '<span class="text-muted">—</span>' ?></span>
                </li>
            </ul>
        </div>

        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white"><strong><i class="bi bi-briefcase me-2 text-primary"></i>Employment</strong></div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Designation</span><span><?= esc($employee['designation'] ?: '—') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Department</span><span><?= esc($employee['department'] ?: '—') ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Joining date</span><span><?= $joined ? esc(date('d M Y', $joined)) : '—' ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">Salary currency</span><span><?= esc($currency) ?></span>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span class="text-muted">On record since</span>
                    <span><?= ! empty($employee['created_at']) ? esc(date('d M Y', strtotime($employee['created_at']))) : '—' ?></span>
                </li>
            </ul>
        </div>

        <!-- System access: the login this person uses to get into CoreLynk. -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-shield-lock me-2 text-primary"></i>System access</strong>
                <?php if (! empty($account)): ?>
                    <span class="badge <?= $account['is_active'] ? 'bg-success' : 'bg-secondary' ?>">
                        <?= $account['is_active'] ? 'Can sign in' : 'Blocked' ?>
                    </span>
                <?php else: ?>
                    <span class="badge bg-light text-dark border">No login</span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (! empty($account)): ?>
                    <ul class="list-unstyled small mb-3">
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Username</span><span class="fw-semibold"><?= esc($account['username']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Login email</span><span><?= esc($account['email']) ?></span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Role</span>
                            <span>
                                <?php if (! empty($account['roles'])): ?>
                                    <?php foreach ($account['roles'] as $r): ?>
                                        <span class="badge bg-info"><?= esc($r['name']) ?></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted"><?= esc($account['role'] ?: 'none') ?></span>
                                <?php endif; ?>
                            </span>
                        </li>
                        <li class="d-flex justify-content-between py-1">
                            <span class="text-muted">Last login</span>
                            <span><?= ! empty($account['last_login']) ? esc(date('d M Y H:i', strtotime($account['last_login']))) : 'never' ?></span>
                        </li>
                    </ul>

                    <?php if (! empty($canManageUsers)): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="<?= base_url('admin/users/' . (int) $account['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil me-1"></i>Edit user &amp; roles
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#passwordModal">
                                <i class="bi bi-key me-1"></i>Set password
                            </button>
                            <?= form_open('admin/employees/' . $employee['id'] . '/access/toggle', ['class' => 'd-inline']) ?>
                                <button class="btn btn-sm <?= $account['is_active'] ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <i class="bi bi-power me-1"></i><?= $account['is_active'] ? 'Block sign-in' : 'Allow sign-in' ?>
                                </button>
                            <?= form_close() ?>
                            <?= form_open('admin/employees/' . $employee['id'] . '/access/unlink', [
                                'class'    => 'd-inline',
                                'onsubmit' => "return confirm('Unlink this login? The user account itself is kept.');",
                            ]) ?>
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-link-45deg me-1"></i>Unlink</button>
                            <?= form_close() ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted small mb-0">Only an admin can change this login.</p>
                    <?php endif; ?>

                <?php elseif (! empty($canManageUsers)): ?>
                    <p class="text-muted small">
                        This employee cannot sign in yet. Give them an existing account, or create a new login.
                    </p>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createLoginModal">
                            <i class="bi bi-person-plus me-1"></i>Create login
                        </button>
                        <?php if (! empty($freeUsers)): ?>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#linkUserModal">
                                <i class="bi bi-link-45deg me-1"></i>Link existing user
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small mb-0">No login yet. An admin can create one from this page.</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-tools me-2 text-primary"></i>Skills</strong>
                <span class="badge bg-primary"><?= count($skills) ?></span>
            </div>
            <div class="card-body">
                <?php if (empty($skills)): ?>
                    <p class="text-muted mb-0 small">
                        No skills yet. <a href="<?= base_url('employees/' . $employee['id'] . '/edit') ?>">Add some</a>.
                    </p>
                <?php else: ?>
                    <?php foreach ($skills as $skill):
                        $stars = match ($skill['proficiency_level']) {
                            'expert' => 5, 'advanced' => 4, 'intermediate' => 3, default => 2,
                        };
                        ?>
                        <div class="d-flex justify-content-between align-items-center py-1">
                            <div>
                                <div class="fw-semibold small"><?= esc($skill['skill_name']) ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?= esc(ucfirst($skill['proficiency_level'])) ?></div>
                            </div>
                            <div class="text-nowrap">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="bi bi-star<?= $i <= $stars ? '-fill text-warning' : '' ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <strong><i class="bi bi-cash-stack me-2 text-primary"></i>Salary history</strong>
                <a href="<?= base_url('employees/payroll') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-calendar-check me-1"></i>Monthly salary sheet
                </a>
            </div>
            <?php if (empty($salaryHistory)): ?>
                <div class="card-body text-muted small">
                    No salary slips yet. Open the <a href="<?= base_url('employees/payroll') ?>">salary sheet</a>,
                    press <em>Prepare this month</em>, then pay the slip from a cash or bank account.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Month</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allow.</th>
                                <th class="text-end">Deduct.</th>
                                <th class="text-end">Net</th>
                                <th>Status</th>
                                <th>Paid from</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($salaryHistory as $slip): ?>
                                <tr>
                                    <td><?= esc(date('M Y', strtotime($slip['period_month']))) ?></td>
                                    <td class="text-end"><?= esc(number_format((float) $slip['basic_amount'], 2)) ?></td>
                                    <td class="text-end text-success"><?= esc(number_format((float) $slip['allowances'], 2)) ?></td>
                                    <td class="text-end text-danger"><?= esc(number_format((float) $slip['deductions'], 2)) ?></td>
                                    <td class="text-end fw-semibold">
                                        <?= esc(format_money((float) $slip['net_amount'], $slip['currency_code'] ?: $currency)) ?>
                                    </td>
                                    <td>
                                        <?php if ($slip['status'] === 'paid'): ?>
                                            <span class="badge bg-success">Paid</span>
                                            <div class="text-muted" style="font-size:.75rem;">
                                                <?= $slip['paid_on'] ? esc(date('d M Y', strtotime($slip['paid_on']))) : '' ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if ($slip['status'] !== 'paid'): ?>
                                            <span class="text-muted">—</span>
                                        <?php else: ?>
                                            <?= ! empty($slip['account_name'])
                                                ? esc($slip['account_code'] . ' ' . $slip['account_name'])
                                                : '<span class="text-danger">no account recorded</span>' ?>
                                            <div class="text-muted">
                                                <?= esc(ucfirst(str_replace('_', ' ', (string) $slip['payment_method']))) ?><?= ! empty($slip['cheque_number']) ? ' #' . esc($slip['cheque_number']) : '' ?>
                                                <?php if (! empty($slip['cheque_image'])): ?>
                                                    · <a href="<?= base_url($slip['cheque_image']) ?>" target="_blank"><i class="bi bi-image"></i> cheque</a>
                                                <?php endif; ?>
                                            </div>
                                            <?php if (! empty($slip['posted_entry_id'])): ?>
                                                <a href="<?= base_url('accounting/journals/view/' . (int) $slip['posted_entry_id']) ?>" class="text-success">
                                                    <i class="bi bi-journal-check"></i> JE #<?= (int) $slip['posted_entry_id'] ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> not in ledger</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <?php if (! empty($recent_assignments)): ?>
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white"><strong><i class="bi bi-clipboard-check me-2 text-primary"></i>Recent batch assignments</strong></div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Work Order</th><th>Product</th><th>Process</th>
                                <th class="text-end">Qty</th><th>Date</th><th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_assignments as $assignment): ?>
                                <tr>
                                    <td><a href="<?= base_url('work-orders/' . $assignment['work_order_id']) ?>"><?= esc($assignment['work_order_code']) ?></a></td>
                                    <td><?= esc($assignment['product_name']) ?></td>
                                    <td><?= esc($assignment['process_name']) ?></td>
                                    <td class="text-end"><?= number_format($assignment['assigned_quantity']) ?></td>
                                    <td><?= esc(date('d M Y', strtotime($assignment['assigned_date']))) ?></td>
                                    <td>
                                        <span class="badge <?= match ($assignment['status']) {
                                            'completed' => 'bg-success',
                                            'in_progress' => 'bg-primary',
                                            'pending' => 'bg-warning text-dark',
                                            default => 'bg-secondary',
                                        } ?>"><?= esc(ucfirst(str_replace('_', ' ', $assignment['status']))) ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php if (! empty($canManageUsers)): ?>
    <?php if (! empty($account)): ?>
        <div class="modal fade" id="passwordModal" tabindex="-1">
            <div class="modal-dialog">
                <?= form_open('admin/employees/' . $employee['id'] . '/access/password', ['class' => 'modal-content']) ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Set password &mdash; <?= esc($account['username']) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">New password</label>
                        <input type="text" name="password" class="form-control" minlength="8" required placeholder="At least 8 characters">
                        <div class="form-text">Shown in plain text so you can pass it on. Any lockout on the account is cleared too.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Save password</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    <?php else: ?>
        <div class="modal fade" id="createLoginModal" tabindex="-1">
            <div class="modal-dialog">
                <?= form_open('admin/employees/' . $employee['id'] . '/access', ['class' => 'modal-content']) ?>
                    <input type="hidden" name="mode" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title">Create login &mdash; <?= esc($name) ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body row g-3">
                        <div class="col-6">
                            <label class="form-label">Username</label>
                            <input type="text" name="username" class="form-control" required minlength="3"
                                   value="<?= esc(strtolower($employee['first_name'] . '.' . $employee['last_name']), 'attr') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Login email</label>
                            <input type="email" name="email" class="form-control" required
                                   value="<?= esc($employee['email'] ?: '', 'attr') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Password</label>
                            <input type="text" name="password" class="form-control" required minlength="8" placeholder="At least 8 characters">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Role</label>
                            <select name="role_ids[]" class="form-select">
                                <?php foreach ($allRoles as $r): ?>
                                    <option value="<?= (int) $r['id'] ?>"><?= esc($r['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Decides what they can see. Change it any time under Settings &rarr; Users.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Create login</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>

        <?php if (! empty($freeUsers)): ?>
        <div class="modal fade" id="linkUserModal" tabindex="-1">
            <div class="modal-dialog">
                <?= form_open('admin/employees/' . $employee['id'] . '/access', ['class' => 'modal-content']) ?>
                    <input type="hidden" name="mode" value="link">
                    <div class="modal-header">
                        <h5 class="modal-title">Link an existing user</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <label class="form-label">User account</label>
                        <select name="user_id" class="form-select" required>
                            <option value="">Select&hellip;</option>
                            <?php foreach ($freeUsers as $u): ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    <?= esc($u['username']) ?> &mdash; <?= esc(trim($u['first_name'] . ' ' . $u['last_name'])) ?>
                                    (<?= esc($u['email']) ?>)<?= empty($u['is_active']) ? ' [blocked]' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Only accounts not already linked to another employee are listed.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-primary">Link user</button>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
<?php endif; ?>

<?php endif; ?>

<?= $this->endSection() ?>
