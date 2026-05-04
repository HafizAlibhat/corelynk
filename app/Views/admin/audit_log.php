<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Audit Log<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="cl-list-page">

    <div class="cl-list-header mb-3">
        <div>
            <h2 class="mb-0"><i class="bi bi-clock-history me-2"></i>Audit Log</h2>
            <small class="text-muted">Security and activity audit trail</small>
        </div>
    </div>

    <!-- Filters -->
    <div class="cl-list-filters mb-3">
        <?= form_open(base_url('admin/audit-log'), ['method' => 'get', 'class' => 'd-flex flex-wrap gap-2 align-items-end']) ?>

        <div style="min-width:160px">
            <label class="form-label mb-1 small">Action</label>
            <select name="action" class="form-select form-select-sm">
                <option value="">All actions</option>
                <?php foreach ($actionsList as $act): ?>
                    <option value="<?= esc($act) ?>" <?= ($filters['action'] ?? '') === $act ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $act))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width:160px">
            <label class="form-label mb-1 small">User</label>
            <select name="user_id" class="form-select form-select-sm">
                <option value="">All users</option>
                <?php foreach ($usersDropdown as $uid => $uname): ?>
                    <option value="<?= $uid ?>" <?= ($filters['user_id'] ?? '') == $uid ? 'selected' : '' ?>><?= esc($uname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="min-width:140px">
            <label class="form-label mb-1 small">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= esc($filters['date_from'] ?? '') ?>">
        </div>

        <div style="min-width:140px">
            <label class="form-label mb-1 small">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= esc($filters['date_to'] ?? '') ?>">
        </div>

        <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-funnel me-1"></i>Filter</button>
        <a href="<?= base_url('admin/audit-log') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>

        <?= form_close() ?>
    </div>

    <!-- Log Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:160px">Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th style="width:140px">IP Address</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No audit log entries found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $actionClass = 'secondary';
                                $actionIcon  = 'bi-activity';
                                $act = $log['action'] ?? '';
                                if (str_contains($act, 'login_success')) { $actionClass = 'success'; $actionIcon = 'bi-box-arrow-in-right'; }
                                elseif (str_contains($act, 'login_fail'))  { $actionClass = 'danger';  $actionIcon = 'bi-x-circle'; }
                                elseif (str_contains($act, 'lockout'))     { $actionClass = 'warning'; $actionIcon = 'bi-lock-fill'; }
                                elseif (str_contains($act, 'logout'))      { $actionClass = 'info';    $actionIcon = 'bi-box-arrow-right'; }
                                elseif (str_contains($act, 'create'))      { $actionClass = 'success'; $actionIcon = 'bi-plus-circle'; }
                                elseif (str_contains($act, 'update') || str_contains($act, 'edit')) { $actionClass = 'primary'; $actionIcon = 'bi-pencil'; }
                                elseif (str_contains($act, 'delete'))      { $actionClass = 'danger';  $actionIcon = 'bi-trash'; }
                                elseif (str_contains($act, 'mfa'))         { $actionClass = 'warning'; $actionIcon = 'bi-shield-lock'; }

                                $details = '';
                                if (!empty($log['details'])) {
                                    $d = json_decode($log['details'], true);
                                    if (is_array($d)) {
                                        $parts = [];
                                        foreach ($d as $k => $v) {
                                            if (is_array($v)) $v = json_encode($v);
                                            $parts[] = '<strong>' . esc($k) . '</strong>: ' . esc((string)$v);
                                        }
                                        $details = implode(' &bull; ', $parts);
                                    } else {
                                        $details = esc($log['details']);
                                    }
                                }
                            ?>
                            <tr>
                                <td class="small text-nowrap">
                                    <?= date('M j, Y', strtotime($log['created_at'])) ?><br>
                                    <span class="text-muted"><?= date('H:i:s', strtotime($log['created_at'])) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($log['username'])): ?>
                                        <?= esc($log['username']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">System</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $actionClass ?>">
                                        <i class="bi <?= $actionIcon ?> me-1"></i><?= esc(ucwords(str_replace('_', ' ', $act))) ?>
                                    </span>
                                </td>
                                <td class="small"><?= $details ?></td>
                                <td class="small text-muted font-monospace"><?= esc($log['ip_address'] ?? '') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (isset($pager)): ?>
    <div class="d-flex justify-content-center mt-3">
        <?= $pager->links('default', 'default_full') ?>
    </div>
    <?php endif; ?>
</div>
<?= $this->endSection() ?>
