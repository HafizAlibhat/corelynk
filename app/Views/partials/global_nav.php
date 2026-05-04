<?php
// ACTIVE NAVIGATION RENDERER — Professional Flat Navbar
// Data source: app/Config/ModuleNav.php
// Mega-menus for Sales and Purchases; standard dropdowns for rest.
// Module visibility driven by PolicyEngine (RBAC permission checks).
$modules = require APPPATH . 'Config/ModuleNav.php';
$currentUrl = current_url();

// Permission-based module visibility via PolicyEngine
$_policy = service('policy');
$_isAdmin = $_policy->isAdmin();

// Filter a list of submodule items down to only those the user can access.
// Each item may have a 'perm' key like 'module.action'; if absent the item is always shown.
$_filterSubs = function(array $subs) use ($_policy, $_isAdmin): array {
    if ($_isAdmin) return $subs;
    return array_values(array_filter($subs, function($item) use ($_policy) {
        if (empty($item['perm'])) return true;
        $parts = explode('.', $item['perm'], 2);
        return $_policy->can($parts[0], $parts[1] ?? 'read');
    }));
};

// Mega-menu groups: these get the wide multi-column dropdown
$megaMenuGroups = ['Sales', 'Purchases'];

// Segments that are action words and should never be standalone links
$nonLinkableSegments = ['view', 'edit', 'create', 'show', 'update', 'delete', 'add', 'new'];

// Build breadcrumb from URL segments
$segments = service('uri')->getSegments();
$breadcrumbs = [];
$buildPath = '';
foreach ($segments as $seg) {
    $buildPath .= '/' . $seg;
    $label = ucwords(str_replace(['-', '_'], ' ', $seg));
    $isNumeric = ctype_digit($seg);
    $isAction  = in_array(strtolower($seg), $nonLinkableSegments, true);
    // Detect UUID / public_id segments (standard UUID or 32-char hex) — show short form
    $isUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $seg)
           || (bool) preg_match('/^[0-9a-f]{32}$/i', $seg);
    if ($isUuid) {
        $label   = '#' . strtolower(substr($seg, 0, 8));
        $isAction = true; // treat as non-linkable last crumb
    }
    $breadcrumbs[] = [
        'label'    => $label,
        'url'      => base_url($buildPath),
        'no_link'  => $isNumeric || $isAction,
    ];
}
?>
<nav id="globalNavBar" class="navbar navbar-expand-xl sticky-top" role="navigation">
  <div class="container-fluid px-3">
    <a class="navbar-brand fw-bold" href="<?= base_url('/') ?>">
      <i class="bi bi-grid-fill me-1"></i>CoreLynk
    </a>
    <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#globalNav" aria-controls="globalNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="globalNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0 flex-nowrap">
        <?php foreach ($modules as $group => $data): ?>
          <?php
            $_subs = isset($data['submodules']) ? $_filterSubs($data['submodules']) : [];
            if (!$_isAdmin && empty($_subs)) continue;
          ?>
          <?php if (!empty($data['submodules'])): ?>
            <?php $isMega = in_array($group, $megaMenuGroups); ?>
            <li class="nav-item dropdown<?= $isMega ? ' cl-mega-dropdown' : '' ?>">
              <a class="nav-link dropdown-toggle<?= (stripos($currentUrl, strtolower($group)) !== false ? ' active' : '') ?>"
                 href="#" id="nav-<?= strtolower($group) ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi <?= esc($data['icon']) ?> me-1"></i><?= esc($group) ?>
              </a>
              <?php if ($isMega): ?>
                <!-- Mega Menu -->
                <div class="dropdown-menu cl-mega-menu" aria-labelledby="nav-<?= strtolower($group) ?>">
                  <div class="d-flex gap-4">
                    <?php
                      $subs = $_subs;
                      $half = ceil(count($subs) / 2);
                      $col1 = array_slice($subs, 0, $half);
                      $col2 = array_slice($subs, $half);
                    ?>
                    <div class="cl-mega-col flex-fill">
                      <div class="cl-mega-heading"><?= esc($group) ?></div>
                      <?php foreach ($col1 as $sub): ?>
                        <a class="dropdown-item<?= (strpos($currentUrl, $sub['route']) !== false ? ' active' : '') ?>"
                           href="<?= base_url($sub['route']) ?>">
                          <i class="bi <?= esc($sub['icon']) ?>"></i><?= esc($sub['label']) ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                    <?php if (!empty($col2)): ?>
                    <div class="cl-mega-col flex-fill">
                      <div class="cl-mega-heading">More</div>
                      <?php foreach ($col2 as $sub): ?>
                        <a class="dropdown-item<?= (strpos($currentUrl, $sub['route']) !== false ? ' active' : '') ?>"
                           href="<?= base_url($sub['route']) ?>">
                          <i class="bi <?= esc($sub['icon']) ?>"></i><?= esc($sub['label']) ?>
                        </a>
                      <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php else: ?>
                <!-- Standard Dropdown -->
                <ul class="dropdown-menu" aria-labelledby="nav-<?= strtolower($group) ?>">
                  <?php foreach ($_subs as $sub): ?>
                    <li>
                      <a class="dropdown-item<?= (strpos($currentUrl, $sub['route']) !== false ? ' active' : '') ?>"
                         href="<?= base_url($sub['route']) ?>">
                        <i class="bi <?= esc($sub['icon']) ?> me-2"></i><?= esc($sub['label']) ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>

      </ul>

      <!-- Right side actions -->
      <div class="d-flex align-items-center gap-1 nav-actions flex-shrink-0">
        <!-- Global Search Trigger -->
        <button
          id="clOpenGlobalSearch"
          class="cl-global-search-trigger d-none d-lg-inline-flex"
          type="button"
          title="Global Search (Ctrl+K)"
          aria-label="Open global search"
        >
          <i class="bi bi-search"></i>
        </button>

        <!-- Quick Create Button (Primary Action) -->
        <div class="dropdown">
          <button class="cl-quick-create" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Quick Create Document" id="quickCreateBtn">
            <i class="bi bi-plus-lg"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end cl-quick-menu" aria-labelledby="quickCreateBtn">
            <li><h6 class="dropdown-header"><i class="bi bi-plus-circle me-1"></i>Quick Create</h6></li>
            <li><a class="dropdown-item" href="<?= base_url('/document-studio') ?>"><i class="bi bi-receipt me-2" style="color: #4f46e5;"></i>Quotation</a></li>
            <li><a class="dropdown-item" href="<?= base_url('/newpurchaseui/rfqpo') ?>"><i class="bi bi-cart-plus me-2" style="color: #10b981;"></i>Purchase</a></li>
            <li><a class="dropdown-item" href="<?= base_url('/work-orders/create') ?>"><i class="bi bi-clipboard-plus me-2" style="color: #f59e0b;"></i>Work Order</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="<?= base_url('/vendors/create') ?>"><i class="bi bi-shop me-2"></i>Vendor</a></li>
            <li><a class="dropdown-item" href="<?= base_url('/customers/create') ?>"><i class="bi bi-people me-2"></i>Customer</a></li>
            <li><a class="dropdown-item" href="<?= base_url('/products/create') ?>"><i class="bi bi-box-seam me-2"></i>Product</a></li>
          </ul>
        </div>

        <!-- Settings & Theme Dropdown -->
        <div class="dropdown">
          <button class="cl-icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Settings & Theme" id="settingsBtn">
            <i class="bi bi-gear-fill"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="settingsBtn" style="min-width: 180px; font-size: 0.9rem;">
            <li><a class="dropdown-item py-2" href="<?= base_url('/settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
            
            <!-- Admin-only -->
            <?php if ($_isAdmin): ?>
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
            <i class="bi bi-person-circle"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userBtn" style="min-width: 160px; font-size: 0.9rem;">
            <li><h6 class="dropdown-header small"><?= esc(session()->get('first_name') ?? 'User') ?></h6></li>
            <li><a class="dropdown-item py-2" href="<?= base_url('/auth/mfa-setup') ?>"><i class="bi bi-shield-lock me-2"></i>2FA Setup</a></li>
            <li><a class="dropdown-item py-2" href="<?= base_url('/auth/change-password') ?>"><i class="bi bi-lock me-2"></i>Password</a></li>
            <li><a class="dropdown-item py-2" href="<?= base_url('/auth/settings') ?>"><i class="bi bi-gear me-2"></i>My Settings</a></li>
            <li><hr class="dropdown-divider my-1"></li>
            <li><a class="dropdown-item py-2 text-danger" href="<?= base_url('/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- Global Search Palette -->
<div id="clGlobalSearchPalette" class="cl-global-palette d-none" aria-hidden="true">
  <div class="cl-global-palette-backdrop" data-close="1"></div>
  <div class="cl-global-palette-panel" role="dialog" aria-label="Global Search" aria-modal="true">
    <div class="cl-global-palette-inputwrap">
      <i class="bi bi-search"></i>
      <input
        id="clGlobalSearchInput"
        type="search"
        class="form-control"
        placeholder="Search PO, RFQ, SO, quotation, customer, product..."
        autocomplete="off"
      >
      <button id="clCloseGlobalSearch" type="button" class="cl-global-palette-close" aria-label="Close search">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>
    <div id="clGlobalSearchDropdown" class="cl-global-search-dropdown d-none" role="listbox" aria-label="Global search results"></div>
  </div>
</div>

<!-- Breadcrumb Bar -->
<?php if (!empty($breadcrumbs)): ?>
<div class="cl-breadcrumb-bar">
  <a href="<?= base_url('/') ?>">Dashboard</a>
  <?php foreach ($breadcrumbs as $i => $bc): ?>
    <span class="separator">/</span>
    <?php if ($i === count($breadcrumbs) - 1 || !empty($bc['no_link'])): ?>
      <span style="color:var(--cl-text-secondary); font-weight:500;"><?= esc($bc['label']) ?></span>
    <?php else: ?>
      <a href="<?= esc($bc['url']) ?>"><?= esc($bc['label']) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
  document.addEventListener('DOMContentLoaded', function(){
    // ── Theme Preference Handler ──
    const THEME_KEY = 'global_theme_pref';
    let mediaQuery;
    
    function applyTheme(mode) {
      const body = document.body;
      // Remove existing listener
      if (mediaQuery) {
        if (mediaQuery.removeEventListener) mediaQuery.removeEventListener('change', mqHandler);
        mediaQuery = null;
      }
      if (mode === 'dark') {
        body.classList.add('theme-dark');
        document.documentElement.setAttribute('data-bs-theme', 'dark');
      } else {
        body.classList.remove('theme-dark');
        document.documentElement.setAttribute('data-bs-theme', 'light');
      }
    }
    
    function mqHandler(e) {
      applyTheme(e.matches ? 'dark' : 'light');
    }
    
    function setThemePreference(pref) {
      if (pref === 'auto') {
        mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        applyTheme(mediaQuery.matches ? 'dark' : 'light');
        if (mediaQuery && mediaQuery.addEventListener) mediaQuery.addEventListener('change', mqHandler);
      } else if (pref === 'light') {
        applyTheme('light');
      } else if (pref === 'dark') {
        applyTheme('dark');
      } else if (pref === 'disabled') {
        applyTheme('light');
      }
    }
    
    const stored = localStorage.getItem(THEME_KEY) || 'light';
    setThemePreference(stored);
    
    // Handle theme dropdown items
    document.addEventListener('click', function(e) {
      const link = e.target.closest('[data-theme]');
      if (!link) return;
      e.preventDefault();
      const pref = link.getAttribute('data-theme');
      if (pref !== 'disabled') {
        localStorage.setItem(THEME_KEY, pref);
      } else {
        localStorage.removeItem(THEME_KEY);
      }
      setThemePreference(pref);
    });

    // ── Global Quick Search ──
    var openSearchBtn = document.getElementById('clOpenGlobalSearch');
    var closeSearchBtn = document.getElementById('clCloseGlobalSearch');
    var searchPalette = document.getElementById('clGlobalSearchPalette');
    var searchInput = document.getElementById('clGlobalSearchInput');
    var searchDropdown = document.getElementById('clGlobalSearchDropdown');
    var searchTimer = null;
    var activeIndex = -1;
    var endpoint = '<?= site_url('search') ?>';

    function escHtml(str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function closeDropdown() {
      if (!searchDropdown) return;
      searchDropdown.classList.add('d-none');
      searchDropdown.innerHTML = '';
      activeIndex = -1;
    }

    function openPalette() {
      if (!searchPalette) return;
      searchPalette.classList.remove('d-none');
      searchPalette.setAttribute('aria-hidden', 'false');
      document.body.classList.add('cl-global-search-open');
      setTimeout(function(){ if (searchInput) searchInput.focus(); }, 0);
    }

    function closePalette() {
      if (!searchPalette) return;
      searchPalette.classList.add('d-none');
      searchPalette.setAttribute('aria-hidden', 'true');
      document.body.classList.remove('cl-global-search-open');
      if (searchInput) searchInput.blur();
      closeDropdown();
    }

    function highlightActive(items) {
      if (!items || !items.length) return;
      items.forEach(function(el, idx){
        el.classList.toggle('active', idx === activeIndex);
      });
    }

    function renderResults(items, q) {
      if (!searchDropdown) return;

      if (!Array.isArray(items) || items.length === 0) {
        searchDropdown.innerHTML = '<div class="cl-global-search-empty">No results for "' + escHtml(q) + '"</div>' +
          '<a class="cl-global-search-all" href="' + endpoint + '?q=' + encodeURIComponent(q) + '">Open full search</a>';
        searchDropdown.classList.remove('d-none');
        return;
      }

      var html = items.slice(0, 12).map(function(item) {
        return '<a class="cl-global-search-item" href="' + escHtml(item.url || '#') + '">' +
          '<div class="cl-global-search-main">' +
            '<span class="cl-global-search-title"><i class="bi ' + escHtml(item.icon || 'bi-search') + ' me-1"></i>' + escHtml(item.title || '') + '</span>' +
            '<span class="cl-global-search-module">' + escHtml(item.module || 'Result') + '</span>' +
          '</div>' +
          '<div class="cl-global-search-sub">' + escHtml(item.subtitle || '') + '</div>' +
        '</a>';
      }).join('');

      html += '<a class="cl-global-search-all" href="' + endpoint + '?q=' + encodeURIComponent(q) + '">See all results</a>';

      searchDropdown.innerHTML = html;
      searchDropdown.classList.remove('d-none');
      activeIndex = -1;
    }

    function fetchResults(q) {
      if (!q || q.length < 2) {
        closeDropdown();
        return;
      }

      fetch(endpoint + '?format=json&limit=8&q=' + encodeURIComponent(q), {
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function(res) { return res.json(); })
        .then(function(payload) {
          renderResults(payload.data || [], q);
        })
        .catch(function() {
          closeDropdown();
        });
    }

    if (searchInput) {
      searchInput.addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(searchTimer);
        searchTimer = setTimeout(function() { fetchResults(q); }, 220);
      });

      searchInput.addEventListener('focus', function() {
        var q = this.value.trim();
        if (q.length >= 2) fetchResults(q);
      });

      searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
          closePalette();
          return;
        }

        var items = searchDropdown ? Array.from(searchDropdown.querySelectorAll('.cl-global-search-item')) : [];

        if (e.key === 'ArrowDown' && items.length) {
          e.preventDefault();
          activeIndex = activeIndex < items.length - 1 ? activeIndex + 1 : 0;
          highlightActive(items);
          return;
        }

        if (e.key === 'ArrowUp' && items.length) {
          e.preventDefault();
          activeIndex = activeIndex > 0 ? activeIndex - 1 : items.length - 1;
          highlightActive(items);
          return;
        }

        if (e.key === 'Enter') {
          e.preventDefault();
          var q = this.value.trim();
          if (!q) return;

          if (items.length && activeIndex >= 0 && items[activeIndex]) {
            window.location.href = items[activeIndex].getAttribute('href');
          } else if (items.length) {
            window.location.href = items[0].getAttribute('href');
          } else {
            window.location.href = endpoint + '?q=' + encodeURIComponent(q);
          }
        }
      });
    }

    if (openSearchBtn) {
      openSearchBtn.addEventListener('click', openPalette);
    }

    if (closeSearchBtn) {
      closeSearchBtn.addEventListener('click', closePalette);
    }

    function isTypingTarget(el) {
      if (!el) return false;
      var tag = (el.tagName || '').toLowerCase();
      return tag === 'input' || tag === 'textarea' || tag === 'select' || !!el.isContentEditable;
    }

    document.addEventListener('keydown', function(e) {
      var key = (e.key || '').toLowerCase();
      var typing = isTypingTarget(e.target);

      // Primary: Ctrl/Cmd + K
      if ((e.ctrlKey || e.metaKey) && key === 'k') {
        if (!searchInput) return;
        e.preventDefault();
        openPalette();
        searchInput.focus();
        searchInput.select();
      }

      // Secondary: Alt + K
      if (!typing && e.altKey && !e.ctrlKey && !e.metaKey && key === 'k') {
        if (!searchInput) return;
        e.preventDefault();
        openPalette();
        searchInput.focus();
        searchInput.select();
      }

      // Quick open: /
      if (!typing && !e.ctrlKey && !e.metaKey && !e.altKey && e.key === '/') {
        if (!searchInput) return;
        e.preventDefault();
        openPalette();
        searchInput.focus();
      }

      if (e.key === 'Escape' && searchPalette && !searchPalette.classList.contains('d-none')) {
        closePalette();
      }
    });

    document.addEventListener('click', function(e) {
      if (!searchPalette || searchPalette.classList.contains('d-none')) return;
      if (e.target && e.target.getAttribute('data-close') === '1') {
        closePalette();
      }
    });

    // Keep navbar collapse stable across viewport changes and close after mobile navigation.
    var globalNav = document.getElementById('globalNav');
    var globalNavToggler = document.querySelector('#globalNavBar .navbar-toggler');

    function isCompactNav() {
      return window.innerWidth < 1200;
    }

    function closeGlobalNav() {
      if (!globalNav) return;
      globalNav.classList.remove('show');
      if (globalNavToggler) {
        globalNavToggler.classList.add('collapsed');
        globalNavToggler.setAttribute('aria-expanded', 'false');
      }
    }

    if (globalNav) {
      globalNav.addEventListener('click', function(e) {
        if (!isCompactNav()) return;
        var link = e.target.closest('.dropdown-item, .nav-link');
        if (link && !link.classList.contains('dropdown-toggle')) {
          closeGlobalNav();
        }
      });

      window.addEventListener('resize', function() {
        if (!isCompactNav()) {
          closeGlobalNav();
        }
      });
    }
  });
</script>
