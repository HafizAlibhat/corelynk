<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Authentication Logs<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0"><i class="bi bi-journal-text me-2"></i>Authentication Logs</h4>
        <a href="<?= base_url('admin/security') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i>Back to Security Settings
        </a>
    </div>

    <!-- Filters -->
    <form method="get" class="card mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small">Action</label>
                    <select name="action" class="form-select form-select-sm">
                        <option value="">All Actions</option>
                        <?php foreach (['login_success','login_failed','logout','mfa_verify','mfa_failed','password_changed'] as $a): ?>
                            <option value="<?= $a ?>" <?= ($filters['action'] ?? '') === $a ? 'selected' : '' ?>>
                                <?= esc(str_replace('_', ' ', ucfirst($a))) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= esc($filters['from'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= esc($filters['to'] ?? '') ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                    <a href="<?= base_url('admin/security/auth-logs') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>User</th>
                            <th>Email</th>
                            <th>IP Address</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">
                                No authentication logs found.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="text-nowrap small"><?= esc($log['created_at']) ?></td>
                                    <td>
                                        <?php
                                            $badges = [
                                                'login_success'    => 'bg-success',
                                                'login_failed'     => 'bg-danger',
                                                'logout'           => 'bg-secondary',
                                                'mfa_verify'       => 'bg-info',
                                                'mfa_failed'       => 'bg-warning text-dark',
                                                'password_changed' => 'bg-primary',
                                            ];
                                            $cls = $badges[$log['action']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?= $cls ?>"><?= esc($log['action']) ?></span>
                                    </td>
                                    <td><?= esc(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?></td>
                                    <td class="small"><?= esc($log['email'] ?? '-') ?></td>
                                    <td class="small text-muted"><?= esc($log['ip_address']) ?></td>
                                    <td class="small text-muted"><?php
                                        if ($log['details']) {
                                            $d = json_decode($log['details'], true);
                                            echo esc(is_array($d) ? implode(', ', array_map(fn($k,$v) => "$k=$v", array_keys($d), $d)) : $log['details']);
                                        }
                                    ?></td>
                                </tr>
                            <?php endforeach ?>
                        <?php endif ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if (isset($pager)): ?>
            <div class="card-footer">
                <?= $pager->links() ?>
            </div>
        <?php endif ?>
    </div>
</div>
<?= $this->endSection() ?>
