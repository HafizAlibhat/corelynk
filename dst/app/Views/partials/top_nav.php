<?php
// FULL-WIDTH TOP NAVIGATION — same menu data as the sidebar (app/Config/ModuleNav.php),
// rendered as horizontal module dropdowns, plus the global action cluster.
// This is the only navigation in the app; the left sidebar has been removed.
helper('nav');
$modules      = cl_nav_modules();
$isAdmin      = service('policy')->isAdmin();
$navFirstName = trim((string) (session()->get('first_name') ?? 'User'));
$navLastName  = trim((string) (session()->get('last_name') ?? ''));
$navInitials  = strtoupper(mb_substr($navFirstName, 0, 1) . mb_substr($navLastName, 0, 1));
$isHome       = rtrim(current_url(), '/') === rtrim(base_url('/'), '/');
?>
<header class="cl-topbar" id="clTopBar">
  <div class="cl-topbar-inner">
    <a class="cl-topbar-brand" href="<?= base_url('/') ?>">
      <span class="cl-brand-mark" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
      <span class="cl-topbar-brand-name">CoreLynk</span>
    </a>

    <nav class="cl-topbar-nav" aria-label="Module navigation">
      <a class="cl-topbar-link<?= $isHome ? ' active' : '' ?>" href="<?= base_url('/') ?>"<?= $isHome ? ' aria-current="page"' : '' ?>>
        <i class="bi bi-grid-1x2" aria-hidden="true"></i><span>Dashboard</span>
      </a>
      <?php foreach ($modules as $group => $data): ?>
        <?php
          $subs     = $data['submodules'];
          $menuId   = cl_nav_module_id($group, 'topnav');
          $isActive = false;
          foreach ($subs as $sub) {
              if (cl_nav_is_active((string) $sub['route'])) {
                  $isActive = true;
                  break;
              }
          }
        ?>
        <div class="dropdown cl-topbar-item">
          <button class="cl-topbar-link<?= $isActive ? ' active' : '' ?>" type="button" id="<?= esc($menuId) ?>" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi <?= esc($data['icon']) ?>" aria-hidden="true"></i><span><?= esc($group) ?></span>
            <i class="bi bi-chevron-down cl-topbar-caret" aria-hidden="true"></i>
          </button>
          <ul class="dropdown-menu cl-topbar-menu" aria-labelledby="<?= esc($menuId) ?>">
            <?php foreach ($subs as $sub): ?>
              <?php $isSubActive = cl_nav_is_active((string) $sub['route']); ?>
              <li>
                <a class="dropdown-item<?= $isSubActive ? ' active' : '' ?>" href="<?= site_url(ltrim((string) $sub['route'], '/')) ?>"<?= $isSubActive ? ' aria-current="page"' : '' ?>>
                  <i class="bi <?= esc($sub['icon']) ?>" aria-hidden="true"></i><span><?= esc($sub['label']) ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </nav>

    <!-- Global actions (moved out of the sidebar so one bar owns them) -->
    <div class="d-flex align-items-center gap-1 nav-actions flex-shrink-0" role="group" aria-label="Global tools">
      <button id="clMobileGlobalSearch" class="cl-icon-btn cl-mobile-search d-lg-none" type="button" data-cl-search-open title="Search CoreLynk" aria-label="Search CoreLynk">
        <i class="bi bi-search" aria-hidden="true"></i>
      </button>
      <!-- Global Search -->
      <form class="cl-topbar-search d-none d-lg-flex" action="<?= site_url('search') ?>" method="get" role="search">
        <label class="visually-hidden" for="clTopbarSearchInput">Search CoreLynk</label>
        <i class="bi bi-search cl-topbar-search-icon" aria-hidden="true"></i>
        <input
          id="clTopbarSearchInput"
          name="q"
          type="search"
          placeholder="Search resources, SKUs..."
          autocomplete="off"
        >
        <button id="clOpenGlobalSearch" class="cl-topbar-search-advanced" type="button" data-cl-search-open title="Advanced search (Ctrl+K)" aria-label="Open advanced search">
          <i class="bi bi-command" aria-hidden="true"></i>
        </button>
      </form>

      <!-- Quick Create Button (Primary Action) -->
      <div class="dropdown">
        <button class="cl-quick-create" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Quick Create Document" id="quickCreateBtn">
          <i class="bi bi-plus-lg"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end cl-quick-menu" aria-labelledby="quickCreateBtn">
          <li><h6 class="dropdown-header"><i class="bi bi-plus-circle me-1"></i>Quick Create</h6></li>
          <li><a class="dropdown-item" href="<?= base_url('/document-studio') ?>"><i class="bi bi-receipt me-2"></i>Quotation</a></li>
          <li><a class="dropdown-item" href="<?= base_url('/newpurchaseui/rfqpo') ?>"><i class="bi bi-cart-plus me-2"></i>Purchase</a></li>
          <li><a class="dropdown-item" href="<?= base_url('/work-orders/create') ?>"><i class="bi bi-clipboard-plus me-2"></i>Work Order</a></li>
          <li><hr class="dropdown-divider"></li>
          <li><a class="dropdown-item" href="<?= base_url('/vendors/create') ?>"><i class="bi bi-shop me-2"></i>Vendor</a></li>
          <li><a class="dropdown-item" href="<?= base_url('/customers/create') ?>"><i class="bi bi-people me-2"></i>Customer</a></li>
          <li><a class="dropdown-item" href="<?= base_url('/products/create') ?>"><i class="bi bi-box-seam me-2"></i>Product</a></li>
        </ul>
      </div>

      <a class="cl-icon-btn" href="<?= base_url('/#activityCenterCard') ?>" title="Activity Center" aria-label="Open activity center">
        <i class="bi bi-bell" aria-hidden="true"></i>
      </a>

      <!-- Settings & Theme Dropdown -->
      <div class="dropdown">
        <button class="cl-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Settings & Theme" id="settingsBtn">
          <i class="bi bi-gear-fill"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="settingsBtn">
          <li><a class="dropdown-item py-2" href="<?= base_url('/settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>

          <!-- Admin-only -->
          <?php if ($isAdmin): ?>
            <li><a class="dropdown-item py-2" href="<?= base_url('/admin/users') ?>"><i class="bi bi-people-fill me-2"></i>Users</a></li>
            <li><a class="dropdown-item py-2" href="<?= base_url('/admin/roles') ?>"><i class="bi bi-shield-lock me-2"></i>Roles</a></li>
            <li><a class="dropdown-item py-2" href="<?= base_url('/admin/audit-log') ?>"><i class="bi bi-clock-history me-2"></i>Audit</a></li>
            <li><a class="dropdown-item py-2" href="<?= base_url('/admin/mobile-app') ?>"><i class="bi bi-phone me-2"></i>Mobile App</a></li>
          <?php endif; ?>

          <!-- Theme -->
          <li><hr class="dropdown-divider my-1"></li>
          <li><span class="dropdown-item-text small text-muted">Theme</span></li>
          <li><a class="dropdown-item py-1" href="#" data-theme="auto"><i class="bi bi-laptop me-2"></i>Auto</a></li>
          <li><a class="dropdown-item py-1" href="#" data-theme="light"><i class="bi bi-brightness-high me-2"></i>Light</a></li>
          <li><a class="dropdown-item py-1" href="#" data-theme="dark"><i class="bi bi-moon-stars me-2"></i>Dark</a></li>
        </ul>
      </div>

      <!-- User Profile -->
      <div class="dropdown">
        <button class="cl-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="User Profile & Logout" id="userBtn">
          <span class="cl-user-avatar" aria-hidden="true"><?= esc($navInitials ?: 'U') ?></span>
          <span class="cl-user-name"><?= esc($navFirstName) ?></span>
          <i class="bi bi-chevron-down cl-user-chevron" aria-hidden="true"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userBtn">
          <li><h6 class="dropdown-header small"><?= esc($navFirstName) ?></h6></li>
          <li><a class="dropdown-item py-2" href="<?= base_url('/auth/mfa-setup') ?>"><i class="bi bi-shield-lock me-2"></i>2FA Setup</a></li>
          <li><a class="dropdown-item py-2" href="<?= base_url('/auth/change-password') ?>"><i class="bi bi-lock me-2"></i>Password</a></li>
          <li><a class="dropdown-item py-2" href="<?= base_url('/auth/settings') ?>"><i class="bi bi-gear me-2"></i>My Settings</a></li>
          <li><hr class="dropdown-divider my-1"></li>
          <li><a class="dropdown-item py-2 text-danger" href="<?= base_url('/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
        </ul>
      </div>
    </div>
  </div>
</header>
