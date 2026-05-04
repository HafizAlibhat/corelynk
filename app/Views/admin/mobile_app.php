<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Mobile App Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">

    <!-- Page Header -->
    <div class="cl-list-header">
        <div>
            <h2 class="mb-0"><i class="bi bi-phone me-2"></i>Mobile App Management</h2>
            <small class="text-muted">Control mobile app users, API access, connected devices &amp; permissions</small>
        </div>
    </div>

    <!-- Flash Messages -->
    <?php if (session()->has('success')): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle me-2"></i><?= session('success') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (session()->has('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?= session('error') ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-primary"><?= esc($totalUsers) ?></div>
                    <div class="text-muted"><i class="bi bi-people me-1"></i>Active Mobile Users</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-success"><?= esc($totalTokens) ?></div>
                    <div class="text-muted"><i class="bi bi-phone me-1"></i>Connected Devices</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <div class="fs-1 fw-bold text-info"><?= esc($totalApis) ?></div>
                    <div class="text-muted"><i class="bi bi-plug me-1"></i>Exposed API Endpoints</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-users"><i class="bi bi-people me-1"></i>Mobile Users</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-devices"><i class="bi bi-phone me-1"></i>Connected Devices</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-apis"><i class="bi bi-plug me-1"></i>Exposed APIs</a>
        </li>
    </ul>

    <div class="tab-content">

        <!-- ============================================================ -->
        <!-- TAB 1: Mobile Users                                          -->
        <!-- ============================================================ -->
        <div class="tab-pane fade show active" id="tab-users">
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
                                    <th class="text-center">Mobile Access</th>
                                    <th class="text-center">Active Devices</th>
                                    <th>Last Mobile Activity</th>
                                    <th class="text-center" style="width:260px">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($users)): ?>
                                <tr><td colspan="8" class="text-center py-4 text-muted">No users found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td class="text-muted"><?= $u['id'] ?></td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="cl-avatar-sm"><?= strtoupper(substr($u['first_name'] ?? 'U', 0, 1) . substr($u['last_name'] ?? '', 0, 1)) ?></div>
                                            <div>
                                                <div class="fw-semibold"><?= esc(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')) ?></div>
                                                <small class="text-muted">@<?= esc($u['username'] ?? '') ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= esc($u['email'] ?? '') ?></td>
                                    <td>
                                        <?php foreach ($u['roles'] as $r): ?>
                                            <span class="badge bg-primary-subtle text-primary"><?= esc($r['name'] ?? '') ?></span>
                                        <?php endforeach; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($u['mobile_enabled']): ?>
                                            <span class="badge bg-success-subtle text-success"><i class="bi bi-check-circle me-1"></i>Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger"><i class="bi bi-x-circle me-1"></i>Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-info-subtle text-info"><?= $u['active_tokens'] ?></span>
                                    </td>
                                    <td>
                                        <?php if ($u['last_mobile_access']): ?>
                                            <small><?= date('d M Y H:i', strtotime($u['last_mobile_access'])) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <!-- Toggle Access -->
                                        <?= form_open("admin/mobile-app/toggle/{$u['id']}", ['class' => 'd-inline']) ?>
                                            <button type="submit" class="btn btn-sm <?= $u['mobile_enabled'] ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                                    title="<?= $u['mobile_enabled'] ? 'Disable mobile access' : 'Enable mobile access' ?>"
                                                    onclick="return confirm('<?= $u['mobile_enabled'] ? 'Disable mobile access? All active sessions will be revoked.' : 'Enable mobile access for this user?' ?>')">
                                                <i class="bi <?= $u['mobile_enabled'] ? 'bi-phone-fill' : 'bi-phone' ?>"></i>
                                                <?= $u['mobile_enabled'] ? 'Disable' : 'Enable' ?>
                                            </button>
                                        <?= form_close() ?>

                                        <!-- Reset Password -->
                                        <button type="button" class="btn btn-sm btn-outline-warning" title="Reset password"
                                                data-bs-toggle="modal" data-bs-target="#resetModal<?= $u['id'] ?>">
                                            <i class="bi bi-key"></i> Reset
                                        </button>

                                        <!-- Revoke All Sessions -->
                                        <?php if ($u['active_tokens'] > 0): ?>
                                        <?= form_open("admin/mobile-app/revoke-all/{$u['id']}", ['class' => 'd-inline']) ?>
                                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Revoke all sessions"
                                                    onclick="return confirm('Revoke all <?= $u['active_tokens'] ?> active session(s)?')">
                                                <i class="bi bi-x-lg"></i> Sessions
                                            </button>
                                        <?= form_close() ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reset Password Modals -->
            <?php foreach ($users as $u): ?>
            <div class="modal fade" id="resetModal<?= $u['id'] ?>" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <?= form_open("admin/mobile-app/reset-password/{$u['id']}") ?>
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="bi bi-key me-2"></i>Reset Password — @<?= esc($u['username'] ?? '') ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning py-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>
                                This will change the user's password. They will need to log in again.
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                <input type="password" name="new_password" class="form-control" required
                                       minlength="6" placeholder="Minimum 6 characters" autocomplete="new-password">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-warning"><i class="bi bi-key me-1"></i>Reset Password</button>
                        </div>
                        <?= form_close() ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 2: Connected Devices (Active API Tokens)                 -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-devices">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle" id="devicesTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User</th>
                                    <th>Token Name</th>
                                    <th>Created</th>
                                    <th>Last Used</th>
                                    <th>Expires</th>
                                    <th>Status</th>
                                    <th class="text-center" style="width:100px">Action</th>
                                </tr>
                            </thead>
                            <tbody id="devicesBody">
                                <tr><td colspan="8" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> Loading devices...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- TAB 3: Exposed APIs                                          -->
        <!-- ============================================================ -->
        <div class="tab-pane fade" id="tab-apis">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-plug me-2"></i>Exposed API Endpoints (<?= count($exposedApis) ?>)</h5>
                    <small class="text-muted">These endpoints are accessible via the mobile app with a valid API token</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th style="width:80px">Method</th>
                                    <th>Endpoint</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($exposedApis as $api): ?>
                                <tr>
                                    <td>
                                        <?php
                                        $m = $api['method'];
                                        $cls = match($m) {
                                            'GET'    => 'bg-success-subtle text-success',
                                            'POST'   => 'bg-primary-subtle text-primary',
                                            'PUT'    => 'bg-warning-subtle text-warning',
                                            'DELETE' => 'bg-danger-subtle text-danger',
                                            default  => 'bg-secondary-subtle text-secondary',
                                        };
                                        ?>
                                        <span class="badge <?= $cls ?> fw-bold" style="min-width:50px"><?= esc($m) ?></span>
                                    </td>
                                    <td><code><?= esc($api['path']) ?></code></td>
                                    <td class="text-muted"><?= esc($api['description']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- tab-content -->
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Load connected devices via AJAX when tab is shown
document.addEventListener('DOMContentLoaded', function() {
    const devicesTab = document.querySelector('a[href="#tab-devices"]');
    let loaded = false;

    devicesTab.addEventListener('shown.bs.tab', function() {
        if (loaded) return;
        loaded = true;
        loadDevices();
    });

    function loadDevices() {
        fetch('<?= base_url('api/admin/api-tokens') ?>', {
            headers: {
                'Accept': 'application/json',
                'Authorization': 'Bearer internal-web-session'
            }
        })
        .then(r => r.json())
        .then(data => {
            // Fall back to server-rendered data
            renderDevicesFromServer();
        })
        .catch(() => {
            renderDevicesFromServer();
        });
    }

    function renderDevicesFromServer() {
        const body = document.getElementById('devicesBody');
        const users = <?= json_encode(array_map(function($u) {
            return [
                'id' => $u['id'],
                'username' => $u['username'] ?? '',
                'first_name' => $u['first_name'] ?? '',
                'last_name' => $u['last_name'] ?? '',
                'active_tokens' => $u['active_tokens'],
            ];
        }, $users)) ?>;

        // Get token data via PHP
        <?php
        $db = \Config\Database::connect();
        $tokens = $db->table('api_tokens t')
            ->select('t.id, t.user_id, t.name, t.created_at, t.last_used_at, t.expires_at, t.revoked, u.username, u.first_name, u.last_name')
            ->join('users u', 'u.id = t.user_id')
            ->where('t.revoked', 0)
            ->orderBy('t.last_used_at', 'DESC')
            ->get()->getResultArray();
        ?>
        const tokens = <?= json_encode($tokens) ?>;

        if (!tokens.length) {
            body.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No active devices connected.</td></tr>';
            return;
        }

        let html = '';
        tokens.forEach((t, i) => {
            const isRecent = t.last_used_at && (Date.now() - new Date(t.last_used_at).getTime()) < 3600000;
            const statusBadge = isRecent
                ? '<span class="badge bg-success-subtle text-success">Active</span>'
                : '<span class="badge bg-secondary-subtle text-secondary">Idle</span>';
            const initials = ((t.first_name||'U')[0] + (t.last_name||'')[0]).toUpperCase();

            html += `<tr>
                <td class="text-muted">${t.id}</td>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <div class="cl-avatar-sm">${initials}</div>
                        <div>
                            <div class="fw-semibold">@${t.username || ''}</div>
                        </div>
                    </div>
                </td>
                <td>${t.name || 'Mobile App'}</td>
                <td><small>${t.created_at ? new Date(t.created_at).toLocaleDateString() : '—'}</small></td>
                <td><small>${t.last_used_at ? new Date(t.last_used_at).toLocaleString() : 'Never'}</small></td>
                <td><small>${t.expires_at ? new Date(t.expires_at).toLocaleDateString() : 'Never'}</small></td>
                <td>${statusBadge}</td>
                <td class="text-center">
                    <form method="post" action="<?= base_url('admin/mobile-app/revoke-token') ?>/${t.id}" class="d-inline"
                          onsubmit="return confirm('Revoke this token? The device will be logged out.')">
                        <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-x-lg"></i></button>
                    </form>
                </td>
            </tr>`;
        });
        body.innerHTML = html;
    }

    // Auto-load if navigated directly to devices tab
    if (window.location.hash === '#tab-devices') {
        const tab = new bootstrap.Tab(devicesTab);
        tab.show();
    }
});
</script>
<?= $this->endSection() ?>
