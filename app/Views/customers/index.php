<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
    $pager         = $pager ?? null;
    $totalRecords  = (int)($total_customers ?? 0);
    $perPage       = (int)($per_page ?? 25);
    $currentPage   = $pager ? $pager->getCurrentPage() : 1;
    $totalPages    = $perPage > 0 ? (int)ceil($totalRecords / $perPage) : 1;
    if ($totalPages < 1) $totalPages = 1;
    $startItem = $totalRecords > 0 ? (($currentPage - 1) * $perPage) + 1 : 0;
    $endItem   = min($totalRecords, $currentPage * $perPage);
    $cur       = $status ?? 'active';
    $searchVal = (string)($current_search ?? '');
    $pgParams  = array_filter(['search' => $searchVal, 'status' => $cur, 'per_page' => $perPage], fn($v) => $v !== '' && $v !== null && $v !== 25);
    $pgBase    = site_url('customers') . '?' . http_build_query($pgParams) . '&page=';
?>

<style>
    .pl-wrap { padding: .75rem 1rem; }
    .pl-card { border: 1px solid var(--cl-border); border-radius: 6px; overflow: hidden; box-shadow: var(--cl-shadow-xs); background: var(--cl-surface); }
    .pl-card-header { display: flex; align-items: center; justify-content: space-between; padding: .45rem .75rem; border-bottom: 1px solid var(--cl-border); gap: .5rem; }
    .pl-card-header-title { font-size: .82rem; font-weight: 700; color: var(--cl-text-primary); line-height: 1; }
    .pl-card-header-sub { font-size: .66rem; color: var(--cl-text-muted); margin-top: .1rem; }
    .pl-filter-bar { display: flex; align-items: center; flex-wrap: wrap; gap: .3rem; padding: .4rem .75rem; border-bottom: 1px solid var(--cl-border); background: var(--cl-surface); }
    .pl-filter-bar .form-control, .pl-filter-bar .form-select { font-size: .74rem; padding: .18rem .45rem; height: 26px; border-radius: 4px; }
    .pl-search-wrap { position: relative; flex: 1; min-width: 140px; max-width: 240px; }
    .pl-search-wrap .bi-search { position: absolute; left: .45rem; top: 50%; transform: translateY(-50%); font-size: .7rem; color: var(--cl-text-muted); pointer-events: none; }
    .pl-search-wrap .form-control { padding-left: 1.55rem; }
    .pl-filter-bar select.pl-type { width: 110px; }
    .pl-filter-bar select.pl-pp { width: 68px; }
    .pl-btn { display: inline-flex; align-items: center; justify-content: center; height: 26px; padding: 0 .55rem; font-size: .72rem; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); cursor: pointer; gap: .28rem; text-decoration: none; white-space: nowrap; transition: all .12s; }
    .pl-btn:hover { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
    .pl-btn.pl-btn-primary { background: var(--cl-primary); border-color: var(--cl-primary); color: #fff; }
    .pl-btn.pl-btn-primary:hover { opacity: .88; color: #fff; }
    .pl-table { margin-bottom: 0; font-size: .76rem; }
    .pl-card .pl-table thead th { font-size: .62rem !important; text-transform: uppercase; letter-spacing: .05em; color: var(--cl-text-muted); background: var(--cl-surface-alt); border-bottom: 1px solid var(--cl-border); padding: .26rem .45rem !important; white-space: nowrap; font-weight: 700 !important; }
    body.theme-dark .pl-card .pl-table thead th { color: #94a3b8 !important; background: #162033 !important; border-bottom-color: #334155 !important; }
    .pl-card .pl-table tbody td { padding: .18rem .45rem !important; border-bottom: 1px solid var(--cl-border-light); vertical-align: middle; font-size: .76rem !important; }
    .pl-table tbody tr:last-child td { border-bottom: none; }
    body.theme-dark .pl-table tbody tr:nth-child(even) td { background: #0f1a2b !important; }
    body.theme-dark .pl-table tbody tr:nth-child(odd) td  { background: #1a2740 !important; }
    body.theme-dark .pl-table tbody tr:hover td { background: rgba(37,99,235,.1) !important; }
    .cust-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--cl-border); flex-shrink: 0; display: block; }
    .cust-avatar-initials { width: 28px; height: 28px; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: .62rem; font-weight: 700; color: #fff; flex-shrink: 0; }
    .pl-name { font-size: .76rem; font-weight: 600; line-height: 1.1; }
    .pl-sub { font-size: .63rem; color: var(--cl-text-muted); margin-top: .05rem; line-height: 1.15; }
    .pl-act-btn { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 4px; border: 1px solid var(--cl-border); background: var(--cl-surface); color: var(--cl-text-secondary); font-size: .7rem; text-decoration: none; cursor: pointer; transition: all .12s; }
    .pl-act-btn:hover, .pl-act-btn:focus { border-color: var(--cl-primary); color: var(--cl-primary); background: var(--cl-primary-50); }
    .pl-act-btn + .pl-act-btn { margin-left: 3px; }
    .pl-more-menu { display: none; position: fixed; z-index: 1055; min-width: 148px; list-style: none; margin: 0; padding: .25rem; background: var(--cl-surface); border: 1px solid var(--cl-border); border-radius: .45rem; box-shadow: 0 8px 24px rgba(15,23,42,.12), 0 2px 6px rgba(15,23,42,.06); }
    .pl-more-menu.is-open { display: block; }
    .pl-more-menu li { list-style: none; }
    .pl-menu-item { display: flex; align-items: center; gap: .4rem; width: 100%; padding: .38rem .65rem; border-radius: .3rem; font-size: .78rem; font-weight: 500; color: #374151; background: none; border: none; cursor: pointer; text-decoration: none !important; white-space: nowrap; }
    .pl-menu-item:hover { background: var(--cl-surface-alt); color: #111; }
    .pl-menu-item.danger { color: #dc2626; }
    .pl-menu-item.danger:hover { background: #fee2e2; }
    .pl-menu-divider { height: 1px; background: var(--cl-border); margin: .2rem .4rem; }
    .pl-footer { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .3rem; padding: .35rem .75rem; border-top: 1px solid var(--cl-border); font-size: .72rem; color: var(--cl-text-muted); }
    .pl-footer .pagination { margin: 0; gap: 2px; }
    .pl-footer .page-link { padding: .15rem .42rem; font-size: .72rem; min-width: 26px; height: 26px; display: inline-flex; align-items: center; justify-content: center; }
    .pl-tab-bar { display: flex; gap: .25rem; padding: .4rem .75rem; border-bottom: 1px solid var(--cl-border); }
    .pl-tab { display: inline-flex; align-items: center; height: 24px; padding: 0 .65rem; font-size: .72rem; border-radius: 12px; border: 1px solid transparent; color: var(--cl-text-muted); text-decoration: none; white-space: nowrap; font-weight: 500; transition: all .12s; }
    .pl-tab:hover { color: var(--cl-primary); border-color: var(--cl-primary-200, #bfdbfe); background: var(--cl-primary-50, #eff6ff); }
    .pl-tab.active { background: var(--cl-primary); border-color: var(--cl-primary); color: #fff; }
</style>

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

<div class="pl-wrap container-fluid">
<div class="pl-card">

    <!-- Card Header -->
    <div class="pl-card-header">
        <div>
            <div class="pl-card-header-title"><i class="bi bi-people-fill me-1"></i>Customers</div>
            <div class="pl-card-header-sub">Manage customers and billing contacts</div>
        </div>
        <a href="<?= site_url('customers/create') ?>" class="pl-btn pl-btn-primary"><i class="bi bi-plus-circle"></i> Add Customer</a>
    </div>

    <!-- Status Tabs -->
    <?php $baseQ = array_filter(['search' => $searchVal, 'per_page' => $perPage], fn($v) => $v !== '' && $v !== 25); ?>
    <div class="pl-tab-bar">
        <a href="<?= site_url('customers') . '?' . http_build_query(array_merge($baseQ, ['status' => 'active'])) ?>" class="pl-tab <?= $cur === 'active' ? 'active' : '' ?>">Active</a>
        <a href="<?= site_url('customers') . '?' . http_build_query(array_merge($baseQ, ['status' => 'inactive'])) ?>" class="pl-tab <?= $cur === 'inactive' ? 'active' : '' ?>">Inactive</a>
        <a href="<?= site_url('customers') . '?' . http_build_query(array_merge($baseQ, ['status' => 'all'])) ?>" class="pl-tab <?= $cur === 'all' ? 'active' : '' ?>">All</a>
    </div>

    <!-- Filter Bar -->
    <form method="get" action="<?= site_url('customers') ?>" class="pl-filter-bar">
        <input type="hidden" name="status" value="<?= esc($cur) ?>">
        <div class="pl-search-wrap">
            <i class="bi bi-search"></i>
            <input type="text" class="form-control" name="search" value="<?= esc($searchVal) ?>" placeholder="Code, name, company…">
        </div>
        <select class="form-select pl-type" name="type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach (['retail' => 'Retail', 'wholesale' => 'Wholesale', 'government' => 'Government', 'partner' => 'Partner'] as $tv => $tl): ?>
                <option value="<?= $tv ?>" <?= ($_GET['type'] ?? '') === $tv ? 'selected' : '' ?>><?= $tl ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select pl-pp" name="per_page" onchange="this.form.submit()">
            <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25</option>
            <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50</option>
            <option value="100" <?= $perPage == 100 ? 'selected' : '' ?>>100</option>
        </select>
        <button type="submit" class="pl-btn"><i class="bi bi-search"></i></button>
        <a href="<?= site_url('customers') ?>" class="pl-btn" title="Reset"><i class="bi bi-arrow-clockwise"></i></a>
    </form>

    <!-- Table -->
    <div class="table-responsive">
    <?php
        $rowNum = ($currentPage - 1) * $perPage;
        $avatarColors = ['#3b82f6','#8b5cf6','#ec4899','#f59e0b','#10b981','#06b6d4','#ef4444','#6366f1'];
    ?>
    <table class="table table-hover pl-table mb-0">
        <thead>
            <tr>
                <th width="32" class="text-center" style="color:var(--cl-text-muted)">#</th>
                <th width="36"></th>
                <th>Code</th>
                <th>Name</th>
                <th>Company</th>
                <th>Type</th>
                <th>Status</th>
                <th>Created</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($customers)): ?>
            <?php foreach ($customers as $c):
                $rowNum++;
                $cid     = $c['id'];
                $cName   = $c['name'] ?? '';
                $cCode   = $c['customer_code'] ?? '';
                $cCo     = $c['company_name'] ?? '';
                $cType   = $c['type'] ?? 'retail';
                $cStat   = $c['status'] ?? 'active';
                $cEmail  = $c['email'] ?? '';
                $cAvatar = $c['avatar_path'] ?? '';
                $cCreated= $c['created_at'] ?? null;
                $colorIdx = abs(crc32($cName)) % count($avatarColors);
                $avatarBg = $avatarColors[$colorIdx];
                $nameParts = preg_split('/\s+/', trim($cName));
                $initials  = strtoupper(substr($nameParts[0] ?? '', 0, 1));
                if (count($nameParts) > 1) $initials .= strtoupper(substr(end($nameParts), 0, 1));
                $typeCls = match($cType) {
                    'wholesale'  => ['bg' => '#dbeafe', 'fg' => '#1d4ed8'],
                    'government' => ['bg' => '#ede9fe', 'fg' => '#6d28d9'],
                    'partner'    => ['bg' => '#e0f2fe', 'fg' => '#0369a1'],
                    default      => ['bg' => '#f3f4f6', 'fg' => '#374151'],
                };
            ?>
            <tr>
                <td class="text-center" style="font-size:.68rem;color:var(--cl-text-muted);font-variant-numeric:tabular-nums"><?= $rowNum ?></td>
                <td style="padding-left:.5rem!important;padding-right:.25rem!important">
                    <?php if ($cAvatar): ?>
                        <img src="<?= base_url('uploads/customers/' . esc($cAvatar)) ?>" alt="" class="cust-avatar">
                    <?php else: ?>
                        <span class="cust-avatar-initials" style="background:<?= $avatarBg ?>"><?= esc($initials) ?></span>
                    <?php endif; ?>
                </td>
                <td><code style="font-size:.7rem"><?= esc($cCode) ?></code></td>
                <td>
                    <a href="<?= site_url('customers/' . $cid) ?>" class="pl-name text-decoration-none"><?= esc($cName) ?></a>
                    <?php if ($cEmail): ?><div class="pl-sub"><i class="bi bi-envelope" style="font-size:.58rem"></i> <?= esc($cEmail) ?></div><?php endif; ?>
                </td>
                <td style="font-size:.74rem"><?= $cCo ? esc($cCo) : '<span style="color:var(--cl-text-muted)">—</span>' ?></td>
                <td>
                    <span style="font-size:.65rem;font-weight:600;color:<?= $typeCls['fg'] ?>;background:<?= $typeCls['bg'] ?>;padding:.12rem .4rem;border-radius:8px;white-space:nowrap">
                        <?= esc(ucfirst($cType)) ?>
                    </span>
                </td>
                <td>
                    <?= $cStat === 'active'
                        ? '<span class="badge bg-success" style="font-size:.62rem">Active</span>'
                        : '<span class="badge bg-secondary" style="font-size:.62rem">Inactive</span>' ?>
                </td>
                <td><span style="font-size:.71rem;color:var(--cl-text-muted)"><?= $cCreated ? date('M j, Y', strtotime($cCreated)) : '—' ?></span></td>
                <td class="text-end" style="white-space:nowrap">
                    <a href="<?= site_url('customers/' . $cid) ?>" class="pl-act-btn" title="View"><i class="bi bi-eye"></i></a>
                    <button type="button" class="pl-act-btn pl-more-trigger"
                        data-id="<?= $cid ?>"
                        data-view="<?= site_url('customers/' . $cid) ?>"
                        data-edit="<?= site_url('customers/' . $cid . '/edit') ?>"
                        data-delete="<?= site_url('customers/' . $cid . '/delete') ?>"
                        data-status="<?= esc($cStat) ?>"
                        data-name="<?= esc($cName) ?>"
                        title="More actions"><i class="bi bi-three-dots-vertical"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="9" class="text-center py-4 text-muted" style="font-size:.78rem">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
    </div>

    <!-- Footer -->
    <div class="pl-footer">
        <div>Showing <?= $startItem ?>–<?= $endItem ?> of <?= number_format($totalRecords) ?></div>
        <?php if ($totalPages > 1): ?>
        <nav>
            <ul class="pagination pagination-sm">
                <li class="page-item <?= $currentPage <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $pgBase . ($currentPage - 1) ?>"><i class="bi bi-chevron-left" style="font-size:.65rem"></i></a>
                </li>
                <?php
                    $ps = max(1, $currentPage - 2); $pe = min($totalPages, $currentPage + 2);
                    if ($ps > 1): ?><li class="page-item"><a class="page-link" href="<?= $pgBase . 1 ?>">1</a></li><?php
                        if ($ps > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
                    endif;
                    for ($p = $ps; $p <= $pe; $p++): ?>
                        <li class="page-item <?= $p === $currentPage ? 'active' : '' ?>"><a class="page-link" href="<?= $pgBase . $p ?>"><?= $p ?></a></li>
                    <?php endfor;
                    if ($pe < $totalPages):
                        if ($pe < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif;
                        ?><li class="page-item"><a class="page-link" href="<?= $pgBase . $totalPages ?>"><?= $totalPages ?></a></li><?php
                    endif;
                ?>
                <li class="page-item <?= $currentPage >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= $pgBase . ($currentPage + 1) ?>"><i class="bi bi-chevron-right" style="font-size:.65rem"></i></a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
    </div>

</div><!-- /.pl-card -->
</div><!-- /.pl-wrap -->

<!-- Shared dropdown menu -->
<ul class="pl-more-menu" id="custMoreMenu">
    <li><a href="#" class="pl-menu-item" id="custMenuView"><i class="bi bi-eye"></i> View</a></li>
    <li><a href="#" class="pl-menu-item" id="custMenuEdit"><i class="bi bi-pencil"></i> Edit</a></li>
    <li><div class="pl-menu-divider"></div></li>
    <li>
        <form id="custToggleForm" method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" class="pl-menu-item w-100" id="custMenuToggle"><i class="bi bi-arrow-repeat"></i> <span id="custMenuToggleLabel">Toggle Status</span></button>
        </form>
    </li>
    <li><div class="pl-menu-divider"></div></li>
    <li>
        <form id="custDeleteForm" method="post" action="">
            <?= csrf_field() ?>
            <button type="submit" class="pl-menu-item danger w-100" onclick="return confirm('Delete this customer?')"><i class="bi bi-trash"></i> Delete</button>
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

            menu.style.top  = (rect.bottom + window.scrollY + 2) + 'px';
            menu.style.left = '0px';
            menu.classList.add('is-open');
            requestAnimationFrame(function() {
                menu.style.left = Math.max(4, rect.right + window.scrollX - menu.offsetWidth) + 'px';
            });
        });
    });

    document.addEventListener('click', function() { menu.classList.remove('is-open'); });
    menu.addEventListener('click', function(e) { e.stopPropagation(); });
})();
</script>
<?= $this->endSection() ?>
