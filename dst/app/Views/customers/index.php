<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
Customers
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
    $pager        = $pager ?? null;
    $totalRecords = (int)($total_customers ?? 0);
    $perPage      = (int)($per_page ?? 25);
    $currentPage  = $pager ? $pager->getCurrentPage() : 1;
    $totalPages   = $perPage > 0 ? (int)ceil($totalRecords / $perPage) : 1;
    if ($totalPages < 1) {
        $totalPages = 1;
    }

    $startItem = $totalRecords > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
    $endItem   = min($totalRecords, $currentPage * $perPage);
    $cur       = $status ?? 'active';
    $searchVal = (string)($current_search ?? '');
    $pgParams  = array_filter([
        'search'   => $searchVal,
        'status'   => $cur,
        'per_page' => $perPage,
    ], fn($v) => $v !== '' && $v !== null && $v !== 25);
    $pgBase = site_url('customers') . '?' . http_build_query($pgParams) . '&page=';
?>

<?php if (session()->getFlashdata('success')): ?>
  <div class="container-fluid px-3 pt-3">
    <div class="alert alert-success py-2 mb-0"><?= esc(session()->getFlashdata('success')) ?></div>
  </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
  <div class="container-fluid px-3 pt-3">
    <div class="alert alert-danger py-2 mb-0"><?= esc(session()->getFlashdata('error')) ?></div>
  </div>
<?php endif; ?>

<div class="container-fluid cl-list-page cl-customers-page" data-cl-list>
    <div class="cl-list-page-header">
        <div>
            <h2 class="mb-0"><i class="bi bi-people-fill me-2"></i>Customers</h2>
            <div class="small text-muted">Manage customers and billing contacts</div>
        </div>
        <div class="cl-list-page-actions">
            <a href="<?= site_url('customers/create') ?>" class="btn btn-primary cl-customers-new-btn">
                <i class="bi bi-plus-circle me-2"></i>Add Customer
            </a>
        </div>
    </div>

    <form method="get" action="<?= site_url('customers') ?>" class="cl-list-toolbar cl-customers-toolbar">
        <input type="hidden" name="status" value="<?= esc($cur) ?>">

        <div class="cl-list-search-group">
            <label class="form-label" for="customerSearchInput">Search</label>
            <div class="search-input-wrapper">
                <i class="bi bi-search search-icon"></i>
                <input
                    type="text"
                    id="customerSearchInput"
                    class="form-control cl-list-search"
                    name="search"
                    value="<?= esc($searchVal) ?>"
                    placeholder="Search by code, name, company..."
                    autocomplete="off"
                >
            </div>
        </div>

        <div class="cl-list-toolbar-controls">
            <select class="form-select pl-type" name="type" onchange="this.form.submit()" aria-label="Customer type">
                <option value="">All Types</option>
                <?php foreach (['retail' => 'Retail', 'wholesale' => 'Wholesale', 'government' => 'Government', 'partner' => 'Partner'] as $tv => $tl): ?>
                    <option value="<?= $tv ?>" <?= ($_GET['type'] ?? '') === $tv ? 'selected' : '' ?>><?= $tl ?></option>
                <?php endforeach; ?>
            </select>

            <select class="form-select pl-pp" name="per_page" onchange="this.form.submit()" aria-label="Rows per page">
                <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
                <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
                <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
            </select>

            <button type="submit" class="btn btn-outline-secondary cl-action-icon" title="Search customers" aria-label="Search customers">
                <i class="bi bi-search"></i>
            </button>
            <a href="<?= site_url('customers') ?>" class="btn btn-outline-secondary cl-action-icon" title="Reset" aria-label="Reset">
                <i class="bi bi-arrow-clockwise"></i>
            </a>
        </div>
    </form>

    <?php $baseQ = array_filter(['search' => $searchVal, 'per_page' => $perPage], fn($v) => $v !== '' && $v !== 25); ?>
    <div class="cl-list-tabs">
        <a href="<?= site_url('customers') . '?' . http_build_query(array_merge($baseQ, ['status' => 'active'])) ?>" class="filter-tab <?= $cur === 'active' ? 'active' : '' ?>">
            <i class="bi bi-check-circle"></i> Active
        </a>
        <a href="<?= site_url('customers') . '?' . http_build_query(array_merge($baseQ, ['status' => 'inactive'])) ?>" class="filter-tab <?= $cur === 'inactive' ? 'active' : '' ?>">
            <i class="bi bi-dash-circle"></i> Inactive
        </a>
        <a href="<?= site_url('customers') . '?' . http_build_query(array_merge($baseQ, ['status' => 'all'])) ?>" class="filter-tab <?= $cur === 'all' ? 'active' : '' ?>">
            <i class="bi bi-grid"></i> All
        </a>
    </div>

    <div class="card border-0 shadow-sm cl-list-table-card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <?php
                    $rowNum = ($currentPage - 1) * $perPage;
                    $avatarColors = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#ef4444','#6366f1'];
                ?>
                <table class="cl-table table table-hover pl-table mb-0" id="customersTable">
                    <colgroup>
                        <col style="width: 54px;">
                        <col style="width: 138px;">
                        <col style="width: 320px;">
                        <col style="width: 280px;">
                        <col style="width: 132px;">
                        <col style="width: 124px;">
                        <col style="width: 130px;">
                        <col style="width: 96px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th style="width: 58px;">#</th>
                            <th style="width: 145px;">Code</th>
                            <th style="width: 360px;">Name</th>
                            <th>Company</th>
                            <th style="width: 140px;">Type</th>
                            <th style="width: 130px;">Status</th>
                            <th style="width: 145px;">Created</th>
                            <th class="actions-col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($customers)): ?>
                        <?php foreach ($customers as $c):
                            $rowNum++;
                            $cid      = $c['id'];
                            $cName    = $c['name'] ?? '';
                            $cCode    = $c['customer_code'] ?? '';
                            $cCo      = $c['company_name'] ?? '';
                            $cType    = $c['type'] ?? 'retail';
                            $cStat    = $c['status'] ?? 'active';
                            $cEmail   = $c['email'] ?? '';
                            $cCreated = $c['created_at'] ?? null;
                            $nameParts = preg_split('/\s+/', trim($cName));
                        ?>
                        <tr>
                            <td><?= $rowNum ?></td>
                            <td>
                                <a href="<?= site_url('customers/' . $cid) ?>" class="cl-customer-code-link">
                                    <code><?= esc($cCode) ?></code>
                                </a>
                            </td>
                            <td class="customer-name-cell">
                                <a href="<?= site_url('customers/' . $cid) ?>" class="pl-name text-decoration-none"><?= esc($cName) ?></a>
                                <?php if ($cEmail): ?>
                                    <div class="pl-sub"><i class="bi bi-envelope" style="font-size:.58rem"></i> <?= esc($cEmail) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="customer-company-cell">
                                <?= $cCo ? esc($cCo) : '<span class="text-muted">-</span>' ?>
                            </td>
                            <td>
                                <?= view('components/ui/status_pill', ['status' => ucfirst($cType)]) ?>
                            </td>
                            <td>
                                <?= $cStat === 'active'
                                    ? view('components/ui/status_pill', ['status' => 'Active'])
                                    : view('components/ui/status_pill', ['status' => 'Inactive']) ?>
                            </td>
                            <td><?= $cCreated ? date('M j, Y', strtotime($cCreated)) : '-' ?></td>
                            <td class="text-end cl-table-actions">
                                <a href="<?= site_url('customers/' . $cid) ?>" class="pl-act-btn cl-action-icon cl-action-view" title="View" aria-label="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <button
                                    type="button"
                                    class="pl-act-btn cl-action-icon pl-more-trigger"
                                    data-id="<?= $cid ?>"
                                    data-view="<?= site_url('customers/' . $cid) ?>"
                                    data-edit="<?= site_url('customers/' . $cid . '/edit') ?>"
                                    data-delete="<?= site_url('customers/' . $cid . '/delete') ?>"
                                    data-status="<?= esc($cStat) ?>"
                                    data-name="<?= esc($cName) ?>"
                                    title="More actions"
                                    aria-label="More actions"
                                >
                                    <i class="bi bi-three-dots-vertical"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="cl-list-empty cl-list-empty--row">
                                    <span class="cl-list-empty__icon" aria-hidden="true"><i class="bi bi-people"></i></span>
                                    <h6>No Customers Found</h6>
                                    <p>Try adjusting the current status or search filters.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pl-footer">
            <div>Showing <?= number_format($startItem) ?>&ndash;<?= number_format($endItem) ?> of <?= number_format($totalRecords) ?></div>
            <?php if ($totalPages > 1): ?>
            <nav>
                <ul class="pagination pagination-sm">
                    <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $pgBase . ($currentPage - 1) ?>"><i class="bi bi-chevron-left" style="font-size:.65rem"></i></a>
                    </li>
                    <?php
                        $ps = max(1, $currentPage - 2);
                        $pe = min($totalPages, $currentPage + 2);
                        if ($ps > 1): ?>
                            <li class="page-item"><a class="page-link" href="<?= $pgBase . 1 ?>">1</a></li>
                            <?php if ($ps > 2): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                        <?php endif;
                        for ($p = $ps; $p <= $pe; $p++): ?>
                            <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $pgBase . $p ?>"><?= $p ?></a>
                            </li>
                        <?php endfor;
                        if ($pe < $totalPages):
                            if ($pe < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">&hellip;</span></li><?php endif; ?>
                            <li class="page-item"><a class="page-link" href="<?= $pgBase . $totalPages ?>"><?= $totalPages ?></a></li>
                        <?php endif;
                    ?>
                    <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= $pgBase . ($currentPage + 1) ?>"><i class="bi bi-chevron-right" style="font-size:.65rem"></i></a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<ul class="pl-more-menu" id="custMoreMenu">
    <li><a href="#" class="pl-menu-item" id="custMenuView"><i class="bi bi-eye"></i> View</a></li>
    <li><a href="#" class="pl-menu-item" id="custMenuEdit"><i class="bi bi-pencil"></i> Edit</a></li>
    <li><div class="pl-menu-divider"></div></li>
    <li>
        <form id="custToggleForm" method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" class="pl-menu-item w-100" id="custMenuToggle">
                <i class="bi bi-arrow-repeat"></i> <span id="custMenuToggleLabel">Toggle Status</span>
            </button>
        </form>
    </li>
    <li><div class="pl-menu-divider"></div></li>
    <li>
        <form id="custDeleteForm" method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" class="pl-menu-item danger w-100" onclick="return confirm('Delete this customer?')">
                <i class="bi bi-trash"></i> Delete
            </button>
        </form>
    </li>
</ul>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function() {
    const menu = document.getElementById('custMoreMenu');

    document.querySelectorAll('.pl-more-trigger').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const rect = btn.getBoundingClientRect();
            const cStatus = btn.dataset.status;

            document.getElementById('custMenuView').href = btn.dataset.view;
            document.getElementById('custMenuEdit').href = btn.dataset.edit;
            document.getElementById('custMenuToggleLabel').textContent = cStatus === 'active' ? 'Deactivate' : 'Activate';
            document.getElementById('custToggleForm').action = btn.dataset.delete.replace('/delete', '/toggle-status');
            document.getElementById('custDeleteForm').action = btn.dataset.delete;

            menu.style.top = (rect.bottom + 2) + 'px';
            menu.style.left = '0px';
            menu.classList.add('is-open');
            requestAnimationFrame(function() {
                menu.style.left = Math.max(4, rect.right - menu.offsetWidth) + 'px';
            });
        });
    });

    document.addEventListener('click', function() {
        menu.classList.remove('is-open');
    });

    menu.addEventListener('click', function(e) {
        e.stopPropagation();
    });
})();
</script>
<?= $this->endSection() ?>
