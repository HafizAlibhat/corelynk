<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - Corelynk</title>
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <base href="<?= rtrim(site_url(), '\/') ?>/">
    <script>
        window.APP_BASE = window.APP_BASE || '<?= rtrim(site_url(), '\/') ?>';
    </script>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Inter Font (Professional Typography) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Accounting Dashboard Styles -->
        <link rel="stylesheet" href="<?= base_url('assets/css/accounting-dashboard.css') ?>">
    <!-- Fallback duplicate (root-relative) in case base_url misconfigured -->
    <link rel="stylesheet" href="/corelynk/public/assets/css/accounting-dashboard.css" onerror="this.remove()">
        <!-- Universal List/Table Styles -->
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css') ?>?v=2">
    <!-- Select2 (searchable selects) -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- CoreLynk Professional Theme (loads LAST to override all legacy styles) -->
    <link rel="stylesheet" href="<?= base_url('assets/css/corelynk-theme.css') ?>?v=<?= time() ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/product-image-hover-preview.css') ?>?v=1">
    <!-- Speed: avoid blocking on third-party CDNs. Chart.js is now loaded lazily only on pages that need it. -->
    <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <!-- jQuery (required by Select2) and Select2 will be initialized at the end of the page -->

    <script>
        // Early theme application to avoid FOUC and ensure correct mode before paint
        (function() {
            try {
                var pref = localStorage.getItem('global_theme_pref') || 'dark';
                var dark = false;
                if (pref === 'dark') {
                    dark = true;
                } else if (pref === 'light' || pref === 'disabled') {
                    dark = false;
                } else { // auto
                    dark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
                }
                if (dark) {
                    document.documentElement.setAttribute('data-bs-theme', 'dark');
                    document.addEventListener('DOMContentLoaded', function(){ document.body.classList.add('theme-dark'); });
                } else {
                    document.documentElement.setAttribute('data-bs-theme', 'light');
                    document.addEventListener('DOMContentLoaded', function(){ document.body.classList.remove('theme-dark'); });
                }
            } catch(e) { /* ignore */ }
        })();
    </script>

    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-light: #818cf8;
            --primary-dark: #3730a3;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #06b6d4;
            --light-bg: #f8fafc;
            --white: #ffffff;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 1px 3px 0 rgb(0 0 0 / 0.1), 0 1px 2px -1px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --sidebar-width: 250px; /* default sidebar width */
            --sidebar-collapsed-width: 70px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body {
            height: 100%;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            color: var(--gray-700);
            line-height: 1.6;
            color-scheme: light dark; /* hint for native form controls */
            overflow: hidden;               /* prevent body scrollbar */
            display: flex;
            flex-direction: column;
        }

        /* Dark theme variables (applied when body has .theme-dark) */
        body.theme-dark {
            --light-bg: #0f172a;
            --white: #1e293b;
            --gray-50: #162033;
            --gray-100: #1e293b;
            --gray-200: #334155;
            --gray-300: #475569;
            --gray-400: #64748b;
            --gray-500: #94a3b8;
            --gray-600: #cbd5e1;
            --gray-700: #e2e8f0;
            --gray-800: #f1f5f9;
            --gray-900: #f8fafc;
            background-color: var(--light-bg);
            color: var(--gray-600);
        }
            /* ...existing code... */

    /* Smooth theme transitions */
    body, .sidebar, .top-header, .card, .card-header, .content-wrapper { transition: background-color .25s ease, color .25s ease, border-color .25s ease; }
    .top-header .btn-group .btn { display:inline-flex; align-items:center; gap:.25rem; }
    .top-header .btn-group .btn i { line-height:1; }

    body.theme-dark .sidebar { background: linear-gradient(180deg, var(--white) 0%, var(--gray-50) 100%); border-color: var(--gray-200); }
        body.theme-dark .top-header { background: var(--white); border-color: var(--gray-200); }
        body.theme-dark .content-wrapper { background-color: var(--gray-50); }
        body.theme-dark .card { background: var(--white); color: var(--gray-600); }
        body.theme-dark .card-header { background: var(--white); border-color: var(--gray-200); }
        body.theme-dark .nav-link { color: var(--gray-500); }
        body.theme-dark .nav-link:hover { background: var(--gray-100); color: var(--gray-700); }
        body.theme-dark .nav-link.active { color: #fff; }
        body.theme-dark .dropdown-menu { background: var(--white); border-color: var(--gray-200); }
        body.theme-dark .modal-content { background: var(--white); }
    body.theme-dark .table { color: var(--gray-600); border-color: var(--gray-300); }
    body.theme-dark .table thead th { background: var(--gray-100); color: var(--gray-700); border-color: var(--gray-300); }
    body.theme-dark .table tbody tr { background: var(--white); border-color: var(--gray-300); }
    body.theme-dark .table-striped tbody tr:nth-of-type(odd) { background: var(--gray-50); }
    body.theme-dark .tree-table { color: var(--gray-600); }
    body.theme-dark .form-control, body.theme-dark .form-select { background: var(--gray-50); border-color: var(--gray-300); color: var(--gray-600); }
    body.theme-dark .form-control::placeholder { color: var(--gray-500); }
        body.theme-dark .form-control:disabled, body.theme-dark .form-select:disabled { background: var(--gray-100); color: var(--gray-500); }
        body.theme-dark .btn-outline-secondary { color: var(--gray-500); border-color: var(--gray-300); }
        body.theme-dark .btn-outline-secondary:hover { background: var(--gray-100); }
        body.theme-dark .btn-outline-primary { color: var(--primary-light); border-color: var(--primary-light); }
        body.theme-dark hr { border-color: var(--gray-200); }
        body.theme-dark .alert { background: var(--gray-100); color: var(--gray-700); border-color: var(--gray-200); }
    body.theme-dark .badge.bg-light, body.theme-dark .badge.text-bg-light, body.theme-dark .tt-badge { background: var(--gray-50) !important; color: var(--gray-700) !important; border-color: var(--gray-300) !important; }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(180deg, var(--white) 0%, var(--gray-50) 100%);
            border-right: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: var(--sidebar-collapsed-width);
        }

        .sidebar.collapsed .nav-link span {
            display: none;
        }

        .sidebar.collapsed .sidebar-brand .brand-text {
            display: none;
        }

        /* Hide old left sidebar UI: top menu replaces it (keeps markup for backward compatibility) */
        .sidebar, .sidebar-backdrop, .sidebar-toggle { display: none !important; }
        .main-content { margin-left: 0 !important; }

        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--white);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--gray-800);
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1.25rem;
            color: white;
        }

        .brand-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--gray-800);
        }

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--gray-500);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .sidebar-toggle:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0 0.75rem 0.25rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.75rem 1rem;
            color: var(--gray-600);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--gray-800);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: var(--shadow);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        /* Main Content Layout */
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.3s ease;
        }

        .main-content.sidebar-collapsed {
            margin-left: var(--sidebar-collapsed-width);
        }

        .top-header {
            display: none;
        }

        /* Ensure top nav links are visible and accessible (desktop) */
        .top-header .nav .nav-link { color: var(--gray-700); padding: .5rem .75rem; font-weight:600; }
        .top-header .nav .nav-link:hover, .top-header .nav .nav-link:focus { color: var(--primary-dark); background: transparent; }
        .top-header .nav .dropdown-menu { min-width: 220px; }

        /* When overlay sidebar open prevent body scroll */
        body.no-scroll {
            overflow: hidden;
        }

        /* Root scroll strategy: header stays fixed/sticky; only content scrolls */
        .content-wrapper {
            flex: 1;
            min-height: 0;           /* allows flex child to scroll */
            overflow-y: auto;
            overflow-x: hidden;
            padding: 1rem 1.5rem;
            background-color: var(--gray-50);
            margin-top: 1rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
        }

        /* Cards */
        .card {
            border: none;
            box-shadow: var(--shadow-sm);
            border-radius: 12px;
        }

        .card-header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            border-radius: 12px 12px 0 0;
        }

        /* Global section header pattern (reused across pages)
           Use .section-header in a .card-header, plus optional .section-icon, .section-title, .section-sub */
        .section-header { background: linear-gradient(90deg, #ffffff, #f8fafc); border-bottom: 1px solid var(--gray-200); }
        .section-icon { width:40px; height:40px; border-radius:8px; font-size:1.1rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,.08); }
        .section-title { font-weight:600; letter-spacing:.5px; }
        .section-sub { color:#6c757d; }
        /* Color accents helpers */
        .section-accent-primary { background:#0d6efd; color:#fff; }
        .section-accent-amber { background:#f59e0b; color:#0f172a; }
        .section-accent-green { background:#22c55e; color:#0f172a; }

        /* Back-compat: map existing logs headers to the generic pattern */
        .start-batch-header, .active-batches-header { background: linear-gradient(90deg,#ffffff,#f8fafc); border-bottom:1px solid var(--gray-200); }
        .sbh-icon, .abh-icon { width:40px; height:40px; border-radius:8px; font-size:1.1rem; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 4px rgba(0,0,0,.08); }
        .sbh-title, .abh-title { font-weight:600; letter-spacing:.5px; }
        .sbh-sub, .abh-sub { color:#6c757d; }

          /* Compact action icon buttons and consistent action cell spacing
             NOTE: Action/button styles (including .btn-icon and .actions-col) are centralized
             in public/assets/css/style.css. Per-view/inline duplicates removed to avoid
             overriding the single source of truth. */
          .tt-actions { white-space: nowrap; }

        /* Lightweight pill badges used in tables */
        .tt-badge { display:inline-flex; align-items:center; gap:.25rem; padding:.12rem .45rem; border-radius:.9rem; font-size:.7rem; border:1px solid var(--gray-200); background:#f8f9fb; color:#495057; }
        /* Status chips */
        .tt-status { display:inline-flex; align-items:center; gap:.25rem; padding:.12rem .45rem; border-radius:.9rem; font-size:.7rem; border:1px solid transparent; }
        .tt-s-plan { background:#fff3cd; color:#664d03; border-color:#ffecb5; }
        .tt-s-inprog { background:#e7f1ff; color:#0a58ca; border-color:#d0e3ff; }
        .tt-s-done { background:#d1e7dd; color:#0f5132; border-color:#badbcc; }

        /* Dark variants */
        body.theme-dark .section-header { background: linear-gradient(90deg,#0f172a,#1e293b); border-bottom-color: var(--gray-200); }
        body.theme-dark .section-sub { color: var(--gray-500); }
        body.theme-dark .sbh-icon { background:#22c55e; color:#0f172a; }
        body.theme-dark .abh-icon { background:#f59e0b; color:#0f172a; }
        body.theme-dark .tt-badge { background: var(--gray-50); border-color: var(--gray-300); color: var(--gray-700); }
        body.theme-dark .tt-status { border-color: var(--gray-300); }

        /* Make Bootstrap's utility .bg-white respect dark theme for headers/cards */
        body.theme-dark .bg-white {
            background: var(--gray-100) !important;
            color: var(--gray-700) !important;
            border-color: var(--gray-300) !important;
        }

        /* Ensure card headers that used bg-white blend with dark theme and use subtle gradient */
        body.theme-dark .card-header.bg-white {
            background: linear-gradient(90deg, var(--gray-100) 0%, var(--white) 100%) !important;
            border-bottom: 1px solid var(--gray-300) !important;
            color: var(--gray-700) !important;
        }

        /* Price input compact styling for right-panel */
        .price-row .price-field { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .price-row .currency-select { border-top-left-radius: 0; border-bottom-left-radius: 0; margin-left: -1px; max-width: 110px; }
        .price-row .input-group-sm .price-field, .price-row .input-group-sm .currency-select { height: calc(1.5em + .5rem); }
        .price-row .form-label { display:block; margin-bottom:.25rem; }

        /* Slightly tighten the Settings card spacing */
        .card .card-body .form-label.small { font-weight:600; color:var(--gray-500); }

    /* Actions column: keep it compact and right aligned across list tables */
    .table .actions-col, .table th.actions-col { white-space: nowrap; width: 1%; }
    .table td.actions-col { text-align: right; }
    .table .btn-group, .table .btn-group-sm { gap: .25rem; }
    /* Make sure tables use full available width */
    .table { width: 100%; }

    /* Action/button visual rules have been moved to public/assets/css/style.css
       to ensure consistent styling across all views and to avoid inline overrides. */

        /* Status badges */
        .badge {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
        }

        /* Stats Cards */
        .stats-card {
            background: linear-gradient(135deg, #0c1531, #111933 85%);
            border-radius: 12px;
            padding: 1.5rem;
            color: white;
            height: 120px;
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            box-shadow: var(--shadow-md);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .stats-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stats-card.bg-gradient-primary {
            background: linear-gradient(135deg, #1f2a44, #12213b);
        }

        .stats-card.bg-gradient-success {
            background: linear-gradient(135deg, #1d2d26, #101b16);
        }

        .stats-card.bg-gradient-warning {
            background: linear-gradient(135deg, #3f3012, #1f1707);
        }

        .stats-card.bg-gradient-info {
            background: linear-gradient(135deg, #14232f, #0b1720);
        }

        /* Global compact data-first utilities (applies app-wide) */
        .table-compact { font-size: .88rem; }
        .table-compact th, .table-compact td { padding: .35rem .5rem !important; }
        .btn-xs { --bs-btn-padding-y: .125rem; --bs-btn-padding-x: .35rem; --bs-btn-font-size: .7rem; }
        .truncate { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .filters-toolbar { display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:.5rem; }
        .filters-panel { border:1px solid var(--gray-200); border-radius:10px; padding:.5rem; }

        .stats-content {
            display: flex;
            align-items: center;
            width: 100%;
        }

        .stats-icon {
            font-size: 2.5rem;
            margin-right: 1rem;
            opacity: 0.8;
        }

        .stats-info h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            line-height: 1;
        }

        .stats-info p {
            margin: 0.25rem 0 0 0;
            font-size: 0.9rem;
            opacity: 0.9;
        }

    /* Responsive */
    @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .main-content.sidebar-collapsed {
                margin-left: 0;
            }
            
            .top-header {
                padding: 1rem;
            }
            
            .content-wrapper {
                padding: 1rem;
            }
        }

        /* Backdrop for mobile/sidebar overlay */
        .sidebar-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.45);
            z-index: 900;
            transition: opacity 0.2s ease;
        }

        .sidebar.show + .sidebar-backdrop {
            display: block;
        }

        /* Utility classes */
        .border-left-primary {
            border-left: 4px solid var(--primary-color);
        }

        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item section-title text-uppercase small text-muted px-3 mt-2">Purchases</li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/accounting/purchase-orders') !== false) ? 'active' : '' ?>" href="<?= base_url('/accounting/purchase-orders') ?>">
                        <i class="bi bi-cart4"></i>
                        <span>Purchase Orders</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/accounting/purchase-orders/receive') !== false) ? 'active' : '' ?>" href="<?= base_url('/accounting/purchase-orders') ?>">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Receipts / GRNs</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/vendors') !== false) ? 'active' : '' ?>" href="<?= base_url('/vendors') ?>">
                        <i class="bi bi-people"></i>
                        <span>Vendors</span>
                    </a>
                </li>

                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/rfqs') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/rfqs') ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>Purchase RFQs</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/pos') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/pos') ?>">
                        <i class="bi bi-cart-check"></i>
                        <span>Purchase Orders (New)</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/grn') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/grn') ?>">
                        <i class="bi bi-box-seam"></i>
                        <span>Goods Receipt Notes</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/new-purchase-grns/list') !== false) ? 'active' : '' ?>" href="<?= site_url('new-purchase-grns/list') ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>GRN List</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/subcontract-orders') !== false) ? 'active' : '' ?>" href="<?= base_url('/subcontract-orders') ?>">
                        <i class="bi bi-arrows-angle-contract"></i>
                        <span>Subcontract Orders</span>
                    </a>
                </li>

                <li class="nav-item section-title text-uppercase small text-muted px-3 mt-3">Warehouse</li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/warehouse/ready-to-ship') !== false) ? 'active' : '' ?>" href="<?= base_url('/warehouse/ready-to-ship') ?>">
                        <i class="bi bi-box-seam-checked"></i>
                        <span>Ready to Ship</span>
                    </a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link <?= (strpos(current_url(), '/warehouse/incoming-shipments') !== false) ? 'active' : '' ?>" href="<?= base_url('/warehouse/incoming-shipments') ?>">
                        <i class="bi bi-box-arrow-in-down"></i>
                        <span>Incoming Stock</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/delivery-orders/shipped') !== false) ? 'active' : '' ?>" href="<?= base_url('/delivery-orders/shipped') ?>">
                        <i class="bi bi-truck"></i>
                        <span>Shipped Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/delivery-orders') !== false && strpos(current_url(), '/shipped') === false) ? 'active' : '' ?>" href="<?= base_url('/delivery-orders') ?>">
                        <i class="bi bi-file-earmark-text"></i>
                        <span>All Delivery Orders</span>
                    </a>
                </li>

                <li class="nav-item section-title text-uppercase small text-muted px-3 mt-3">Inventory</li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/products') !== false) ? 'active' : '' ?>" href="<?= base_url('/products') ?>">
                        <i class="bi bi-box-seam"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/components') !== false) ? 'active' : '' ?>" href="<?= base_url('/components') ?>">
                        <i class="bi bi-tools"></i>
                        <span>Components</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory') !== false && strpos(current_url(), '/inventory/adjustments') === false && strpos(current_url(), '/inventory/locations') === false && strpos(current_url(), '/inventory/journal') === false && strpos(current_url(), '/inventory/transfers') === false) ? 'active' : '' ?>" href="<?= base_url('/inventory') ?>">
                        <i class="bi bi-boxes"></i>
                        <span>Stock</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory/adjustments') !== false) ? 'active' : '' ?>" href="<?= base_url('/inventory/adjustments') ?>">
                        <i class="bi bi-clipboard2-plus"></i>
                        <span>Stock Adjustment</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory/transfers') !== false) ? 'active' : '' ?>" href="<?= base_url('/inventory/transfers') ?>">
                        <i class="bi bi-arrow-left-right"></i>
                        <span>Stock Transfers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory/locations') !== false) ? 'active' : '' ?>" href="<?= base_url('/inventory/locations') ?>">
                        <i class="bi bi-diagram-3"></i>
                        <span>Locations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory/journal') !== false) ? 'active' : '' ?>" href="<?= base_url('/inventory/journal') ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Stock Journal</span>
                    </a>
                </li>

                <li class="nav-item section-title text-uppercase small text-muted px-3 mt-3">Production</li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/work-orders') !== false) ? 'active' : '' ?>" href="<?= base_url('/work-orders') ?>">
                        <i class="bi bi-list-check"></i>
                        <span>Work Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/processes') !== false) ? 'active' : '' ?>" href="<?= base_url('/processes') ?>">
                        <i class="bi bi-gear"></i>
                        <span>Processes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/logs') !== false) ? 'active' : '' ?>" href="<?= base_url('/logs') ?>">
                        <i class="bi bi-clipboard-data"></i>
                        <span>Production Logs</span>
                    </a>
                </li>


                <li class="nav-item section-title text-uppercase small text-muted px-3 mt-3">Sales</li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/pos') !== false) ? 'active' : '' ?>" href="<?= base_url('/pos') ?>">
                        <i class="bi bi-display"></i>
                        <span>POS Register</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/customers') !== false) ? 'active' : '' ?>" href="<?= base_url('/customers') ?>">
                        <i class="bi bi-people-fill"></i>
                        <span>Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/product-categories') !== false) ? 'active' : '' ?>" href="<?= base_url('/product-categories') ?>">
                        <i class="bi bi-tags"></i>
                        <span>Category Management</span>
                    </a>
                </li>

                <li class="nav-item section-title text-uppercase small text-muted px-3 mt-3">Accounting</li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/accounting/cheques') !== false) ? 'active' : '' ?>" href="<?= base_url('/accounting/cheques') ?>">
                        <i class="bi bi-receipt"></i>
                        <span>Cheques & Payments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/accounting/journals') !== false) ? 'active' : '' ?>" href="<?= base_url('/accounting/journals') ?>">
                        <i class="bi bi-journal-text"></i>
                        <span>Journals</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/accounting/accounts') !== false) ? 'active' : '' ?>" href="<?= base_url('/accounting/accounts') ?>">
                        <i class="bi bi-list-ul"></i>
                        <span>Accounts</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Backdrop for overlay sidebar on smaller screens -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header (Title only - main controls moved to global navbar) -->
        <header class="top-header">
            <div class="header-left d-flex align-items-center gap-3">
                <h1 class="mb-0"><?= $this->renderSection('title') ?></h1>
            </div>
            <div class="header-right d-flex align-items-center gap-2">
                <!-- Logged-in user badge -->
                <?php
                    $sessFN = session()->get('first_name') ?? '';
                    $sessLN = session()->get('last_name')  ?? '';
                    $sessRole = session()->get('role') ?? '';
                    $fullName = trim($sessFN . ' ' . $sessLN);
                    $initials = strtoupper(
                        ($sessFN ? mb_substr($sessFN, 0, 1) : '') .
                        ($sessLN ? mb_substr($sessLN, 0, 1) : '')
                    ) ?: '?';
                ?>
                <?php if ($fullName): ?>
                <div class="d-none d-md-flex align-items-center gap-2" style="background:rgba(99,102,241,.1);border:1px solid rgba(99,102,241,.25);border-radius:8px;padding:.3rem .65rem;">
                    <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#6366f1,#8b5cf6);display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;color:#fff;flex-shrink:0;">
                        <?= esc($initials) ?>
                    </div>
                    <div style="line-height:1.2;">
                        <div style="font-size:.78rem;font-weight:600;color:var(--text-primary,#e2e8f0);white-space:nowrap;"><?= esc($fullName) ?></div>
                        <?php if ($sessRole): ?>
                        <div style="font-size:.68rem;color:#94a3b8;text-transform:capitalize;"><?= esc($sessRole) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <!-- Mobile hamburger (visible on xs-sm) -->
                <button id="mobileMenuToggle" class="btn btn-outline-secondary d-md-none" aria-label="Open menu">
                    <span class="navbar-toggler-icon">☰</span>
                </button>
            </div>
        </header>

    <!-- Mobile Fullscreen Menu -->
    <div id="mobileFullMenu" class="mobile-fullmenu" aria-hidden="true">
        <button class="menu-close" id="mobileMenuClose" aria-label="Close menu">&times;</button>
        <div class="container">
            <h3>Menu</h3>
            <div class="menu-section">
                <strong>Purchases</strong>
                <a href="<?= base_url('/accounting/purchase-orders') ?>">Purchase Orders</a>
                <a href="<?= base_url('/accounting/purchase-orders') ?>">Receipts / GRNs</a>
                <a href="<?= site_url('new-purchase-grns/list') ?>">GRN List</a>
                <a href="<?= site_url('newpurchaseui/grn') ?>">Create GRN</a>
                <a href="<?= base_url('/vendors') ?>">Vendors</a>
                <a href="<?= base_url('/vendor-contacts') ?>">Vendor Contacts</a>
            </div>
            <div class="menu-section">
                <strong>Inventory</strong>
                <a href="<?= base_url('/products') ?>">Products</a>
                <a href="<?= base_url('/product-attributes') ?>">Attributes</a>
                <a href="<?= base_url('/components') ?>">Components</a>
                <a href="<?= base_url('/inventory') ?>">Stock</a>
                <a href="<?= base_url('/inventory/adjustments') ?>">Stock Adjustment</a>
                <a href="<?= base_url('/inventory/locations') ?>">Locations</a>
                <a href="<?= base_url('/inventory/journal') ?>">Stock Journal</a>
                <a href="<?= base_url('/product-processes') ?>">Product Processes</a>
            </div>
            <div class="menu-section">
                <strong>Production</strong>
                <a href="<?= base_url('/work-orders') ?>">Work Orders</a>
                <a href="<?= base_url('/processes') ?>">Processes</a>
                <a href="<?= base_url('/logs') ?>">Production Logs</a>
                <a href="<?= base_url('/process-templates') ?>">Process Templates</a>
            </div>
            <div class="menu-section">
                <strong>Sales</strong>
                <a href="<?= base_url('/document-studio') ?>">Document Studio</a>
                <a href="<?= base_url('/customers') ?>">Customers</a>
                <a href="<?= base_url('/product-categories') ?>">Categories</a>
                <a href="<?= base_url('/reports') ?>">Reporting</a>
                <a href="<?= base_url('/product-ledger') ?>">Product Ledger</a>
                <a href="<?= base_url('/quotations') ?>">Quotations</a>
            </div>
            <div class="menu-section">
                <strong>Accounting</strong>
                <a href="<?= base_url('/accounting/cheques') ?>">Cheques & Payments</a>
                <a href="<?= base_url('/accounting/journals') ?>">Journals</a>
                <a href="<?= base_url('/accounting/accounts') ?>">Accounts</a>
                <a href="<?= base_url('/tax-codes') ?>">Tax Codes</a>
            </div>
        </div>
    </div>

    <style>
        /* Mobile full-screen menu styles */
        .mobile-fullmenu { display:none; position:fixed; inset:0; background:rgba(15,23,42,0.98); color:#fff; z-index:1200; padding:2.5rem 1.25rem; overflow-y:auto; }
        .mobile-fullmenu.show { display:block; animation:mm-fadein .18s ease-out; }
        .mobile-fullmenu .menu-close { position:absolute; top:1rem; right:1rem; background:transparent; border:none; color:#fff; font-size:1.5rem; }
        .mobile-fullmenu h3 { margin-top:0; color:#fff; }
        .mobile-fullmenu .menu-section { margin:1.25rem 0; }
        .mobile-fullmenu .menu-section a { display:block; padding:.6rem 0; color:#e6eef8; font-weight:600; text-decoration:none; }
        .mobile-fullmenu .menu-section a:hover { color:#fff; }
        @keyframes mm-fadein { from { opacity:0; transform:translateY(-6px); } to { opacity:1; transform:none; } }
    </style>
    <!-- Select2 dark-theme fixes: ensure searchable selects fit the app dark theme -->
    <style>
        /* Use CSS variables so this adapts to light/dark modes */
        body.theme-dark .select2-container--default .select2-selection--single,
        body.theme-dark .select2-container--default .select2-selection--multiple {
            background: var(--gray-50) !important;
            border-color: var(--gray-300) !important;
            color: var(--gray-600) !important;
        }

        body.theme-dark .select2-container--default .select2-selection__rendered {
            color: var(--gray-600) !important;
        }

        body.theme-dark .select2-container--default .select2-selection__arrow b {
            border-top-color: var(--gray-600) !important;
        }

        body.theme-dark .select2-dropdown {
            background: var(--white) !important;
            color: var(--gray-600) !important;
            border-color: var(--gray-300) !important;
        }

        body.theme-dark .select2-results__option--highlighted,
        body.theme-dark .select2-results__option[aria-selected="true"] {
            background: var(--gray-100) !important;
            color: var(--gray-700) !important;
        }

        body.theme-dark .select2-search--dropdown .select2-search__field,
        body.theme-dark .select2-search--inline .select2-search__field {
            background: transparent !important;
            color: var(--gray-600) !important;
        }

        /* Fix for single-select placeholder color */
        body.theme-dark .select2-container--default .select2-selection__placeholder {
            color: var(--gray-500) !important;
        }

        /* Make select2 dropdowns match input sizes and spacing */
        .select2-container .select2-selection--single { height: calc(1.5em + 0.75rem); }
        .select2-container .select2-selection__rendered { line-height: calc(1.5em + 0.75rem); }
    </style>
    </style>
</head>
<body>
    <?php // ACTIVE NAVIGATION: single top menu source (partials/global_nav + Config/ModuleNav). ?>
    <?= $this->include('partials/global_nav') ?>

        <!-- Content -->
        <div class="content-wrapper">
            <!-- Flash Messages -->
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= session()->getFlashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= session()->getFlashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('warning')): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= session()->getFlashdata('warning') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('info')): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    <?= session()->getFlashdata('info') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?= $this->renderSection('content') ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- jQuery and Select2 for searchable dropdowns -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="<?= base_url('assets/js/product-image-hover-preview.js') ?>?v=1"></script>

    <script>
        // Initialize Select2 on elements with .searchable once DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            try {
                if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                    window.jQuery('.searchable').each(function() {
                        try {
                            if (window.jQuery(this).data('select2')) {
                                window.jQuery(this).select2('destroy');
                            }
                            window.jQuery(this).select2({ width: '100%', placeholder: 'Search...', allowClear: true });
                        } catch (e) {
                            console.warn('Select2 init failed', e);
                        }
                    });
                }
            } catch (e) { /* ignore */ }
        });
    </script>

    <script>
        // Ensure Inventory dropdown includes Locations + Stock Adjustment (header nav)
        document.addEventListener('DOMContentLoaded', function() {
            const invToggle = document.getElementById('nav-inventory');
            const menu = invToggle?.nextElementSibling;
            if (!menu || !menu.classList.contains('dropdown-menu')) return;

            // Stock Adjustment
            if (!menu.querySelector('[data-nav-link="inventory-adjustments"]')) {
                const liAdj = document.createElement('li');
                const aAdj  = document.createElement('a');
                aAdj.className = 'dropdown-item';
                if (window.location.pathname.startsWith('/inventory/adjustments')) aAdj.classList.add('active');
                aAdj.href = '<?= base_url('/inventory/adjustments') ?>';
                aAdj.dataset.navLink = 'inventory-adjustments';
                aAdj.innerHTML = '<i class="bi bi-clipboard2-plus me-2"></i>Stock Adjustment';
                liAdj.appendChild(aAdj);
                menu.appendChild(liAdj);
            }

            // Locations
            const already = menu.querySelector('[data-nav-link="inventory-locations"]');
            if (already) return;

            const li = document.createElement('li');
            const a = document.createElement('a');
            a.className = 'dropdown-item';
            if (window.location.pathname.startsWith('/inventory/locations')) {
                a.classList.add('active');
            }
            a.href = '<?= base_url('/inventory/locations') ?>';
            a.dataset.navLink = 'inventory-locations';
            a.innerHTML = '<i class="bi bi-diagram-3 me-2"></i>Locations';
            li.appendChild(a);
            menu.appendChild(li);
        });
    </script>

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    try {
                        if (alert.classList.contains('show')) {
                            if (window.bootstrap && window.bootstrap.Alert) {
                                const bsAlert = new window.bootstrap.Alert(alert);
                                bsAlert.close();
                            } else {
                                // Fallback: remove element if Bootstrap isn't ready
                                alert.parentNode && alert.parentNode.removeChild(alert);
                            }
                        }
                    } catch (e) { /* ignore */ }
                }, 5000);
            });
        });

        // CSRF Token for AJAX requests
        window.csrfToken = '<?= csrf_token() ?>';
        window.csrfHash = '<?= csrf_hash() ?>';
        
        // Sidebar toggle functionality with backdrop support
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            const backdrop = document.getElementById('sidebarBackdrop');
            const MOBILE_BREAKPOINT = 768; // match CSS @media breakpoint

            function isMobile() {
                return window.innerWidth < MOBILE_BREAKPOINT;
            }

            function openSidebarOverlay() {
                if (!sidebar) return;
                sidebar.classList.add('show');
                if (backdrop) backdrop.style.display = 'block';
                document.body.classList.add('no-scroll');
            }

            function closeSidebarOverlay() {
                if (!sidebar) return;
                sidebar.classList.remove('show');
                if (backdrop) backdrop.style.display = 'none';
                document.body.classList.remove('no-scroll');
            }

            function handleToggleClick() {
                    if (isMobile()) {
                        // On mobile treat as overlay
                        if (sidebar.classList.contains('show')) {
                            closeSidebarOverlay();
                        } else {
                            openSidebarOverlay();
                        }
                        return;
                    }

                    // Desktop/tablet: collapse/push
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');

                    // Save state to localStorage
                    if (sidebar.classList.contains('collapsed')) {
                        localStorage.setItem('sidebarCollapsed', 'true');
                    } else {
                        localStorage.removeItem('sidebarCollapsed');
                    }
            }

            if (sidebar && mainContent) {
                if (sidebarToggle) sidebarToggle.addEventListener('click', handleToggleClick);
                if (sidebarToggleTop) sidebarToggleTop.addEventListener('click', handleToggleClick);

                // Backdrop click closes overlay
                if (backdrop) {
                    backdrop.addEventListener('click', function() {
                        closeSidebarOverlay();
                    });
                }

                // Restore desktop collapsed state from localStorage
                if (!isMobile() && localStorage.getItem('sidebarCollapsed') === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                }
            }

                // Ensure sidebar closes when window resizes to desktop
                window.addEventListener('resize', function() {
                    if (!isMobile()) {
                        // remove overlay styles
                        closeSidebarOverlay();
                    } else {
                        // hide backdrop when switching to mobile until opened
                        if (backdrop) backdrop.style.display = 'none';
                    }
                });
            });
    </script>

    <script>
        // Mobile menu toggle
        document.addEventListener('DOMContentLoaded', function(){
            var openBtn = document.getElementById('mobileMenuToggle');
            var closeBtn = document.getElementById('mobileMenuClose');
            var menu = document.getElementById('mobileFullMenu');

            function openMenu(){
                if (!menu) return;
                menu.classList.add('show');
                menu.setAttribute('aria-hidden','false');
                document.body.classList.add('no-scroll');
            }
            function closeMenu(){
                if (!menu) return;
                menu.classList.remove('show');
                menu.setAttribute('aria-hidden','true');
                document.body.classList.remove('no-scroll');
            }

            if (openBtn) openBtn.addEventListener('click', function(e){ e.preventDefault(); openMenu(); });
            if (closeBtn) closeBtn.addEventListener('click', function(e){ e.preventDefault(); closeMenu(); });

            // Close when clicking any link inside menu
            if (menu) {
                menu.addEventListener('click', function(e){
                var a = e.target.closest('a');
                if (a) {
                    // allow normal navigation, but ensure menu closes immediately
                    closeMenu();
                }
            });
            }

            // Close on escape
            document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeMenu(); });
        });
    </script>

    <!-- Remote modal (loads pages via AJAX into a modal so users don't leave the current page) -->
    <div class="modal fade" id="remoteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="remoteModalLabel">Loading...</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center py-4" id="remoteModalSpinner">
                        <div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>
                    </div>
                    <div id="remoteModalContent"></div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Delegated handler for opening links/buttons in the remote modal
        document.addEventListener('click', function(e) {
            var trigger = e.target.closest && e.target.closest('.open-remote-modal');
            if (!trigger) return;
            e.preventDefault();
            var url = trigger.getAttribute('data-url') || trigger.getAttribute('href');
            if (!url) return;
            var title = trigger.getAttribute('data-title') || trigger.getAttribute('title') || 'Manage';

            var modalEl = document.getElementById('remoteModal');
            var modal = new bootstrap.Modal(modalEl);
            document.getElementById('remoteModalLabel').textContent = title;
            document.getElementById('remoteModalContent').innerHTML = '';
            document.getElementById('remoteModalSpinner').style.display = 'block';
            modal.show();

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function(res){ return res.text(); })
                .then(function(html){
                    document.getElementById('remoteModalSpinner').style.display = 'none';
                    var modalContent = document.getElementById('remoteModalContent');
                    modalContent.innerHTML = html;

                    // Execute any inline <script> tags included in the fetched HTML so that
                    // page-specific initializers (like the Add Value handlers) run. When
                    // HTML is injected via innerHTML the browser doesn't execute scripts,
                    // so we manually recreate them.
                    try {
                        Array.from(modalContent.querySelectorAll('script')).forEach(function(s){
                            var ns = document.createElement('script');
                            if (s.src) {
                                ns.src = s.src; // external script will load and execute
                                // preserve async/defer if present
                                if (s.async) ns.async = true;
                                if (s.defer) ns.defer = true;
                            } else {
                                ns.text = s.innerHTML;
                            }
                            s.parentNode.replaceChild(ns, s);
                        });
                    } catch(e) { console.error('Error executing modal scripts', e); }

                    // Initialize Select2 inside loaded content if available
                    try {
                        if (window.jQuery && window.jQuery.fn && window.jQuery.fn.select2) {
                            window.jQuery('#remoteModalContent').find('.searchable').each(function(){
                                try { if (window.jQuery(this).data('select2')) window.jQuery(this).select2('destroy'); } catch(e){}
                                try { window.jQuery(this).select2({ width: '100%', placeholder: 'Search...', allowClear: true }); } catch(e){}
                            });
                        }
                    } catch(e) { /* ignore */ }
                })
                .catch(function(err){
                    document.getElementById('remoteModalSpinner').style.display = 'none';
                    document.getElementById('remoteModalContent').innerHTML = '<div class="alert alert-danger">Failed to load content. <a href="'+url+'" target="_blank">Open in new tab</a></div>';
                });
        });
    </script>

    <!-- Page-specific JavaScript -->
    <?= $this->renderSection('js') ?>

    <!-- Bootstrap JS (removed duplicate include) -->


</body>
</html>


