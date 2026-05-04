<?php // LEGACY LAYOUT — DO NOT MODIFY ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Production Management System' ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-light: #3b82f6;
            --primary-dark: #1d4ed8;
            --secondary-color: #64748b;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
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
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --border-radius: 8px;
            --border-radius-lg: 12px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light-bg);
            color: var(--gray-700);
            line-height: 1.6;
            margin: 0;
            padding: 0;
        }

        /* Modern Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, var(--white) 0%, var(--gray-50) 100%);
            border-right: 1px solid var(--gray-200);
            z-index: 1000;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-sm);
        }

        .sidebar-header {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid var(--gray-200);
            background: var(--white);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            text-decoration: none;
            color: var(--gray-800);
            font-weight: 700;
            font-size: 1.25rem;
        }

        .sidebar-brand i {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        .sidebar-nav {
            padding: 1rem 0;
            height: calc(100vh - 80px);
            overflow-y: auto;
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
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all 0.2s ease;
            position: relative;
        }

        .nav-link:hover {
            background-color: var(--gray-100);
            color: var(--gray-800);
            transform: translateX(2px);
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-link.active::before {
            content: '';
            position: absolute;
            left: -0.75rem;
            top: 50%;
            transform: translateY(-50%);
            width: 3px;
            height: 24px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background-color: var(--light-bg);
        }

        /* Top Header */
        .top-header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-left h1 {
            margin: 0;
            color: var(--gray-800);
            font-size: 1.75rem;
            font-weight: 700;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* User Dropdown */
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
            transition: all 0.2s ease;
        }

        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-md);
        }

        /* Content Area */
        .content-wrapper {
            padding: 2rem;
        }

        /* Cards */
        .card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-sm);
            transition: all 0.2s ease;
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .card-header {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            padding: 1.25rem 1.5rem;
            border-radius: var(--border-radius-lg) var(--border-radius-lg) 0 0;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Buttons */
        .btn {
            font-weight: 500;
            border-radius: var(--border-radius);
            padding: 0.5rem 1rem;
            transition: all 0.2s ease;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-color));
            transform: translateY(-1px);
            box-shadow: var(--shadow-md);
        }

        /* Tables */
        .table {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .table th {
            background: var(--gray-50);
            border-bottom: 1px solid var(--gray-200);
            font-weight: 600;
            color: var(--gray-700);
        }

        /* Status badges */
        .badge {
            font-weight: 500;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .content-wrapper {
                padding: 1rem;
            }
        }

        /* Alerts */
        .alert {
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
        }

        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .alert-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .alert-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .alert-info {
            background-color: #dbeafe;
            color: #1e40af;
        }
    </style>
    
    <!-- Additional CSS -->
    <?= $this->renderSection('css') ?>
</head>
<body>
<?= $this->include('partials/global_nav') ?>
    <?php if (isset($current_user)): ?>
    <!-- Modern Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <a href="<?= base_url('/dashboard') ?>" class="sidebar-brand">
                <i class="bi bi-gear-fill"></i>
                Production MS
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'dashboard') ? 'active' : '' ?>" 
                   href="<?= base_url('/dashboard') ?>">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
            </div>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner', 'production'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'work-orders') ? 'active' : '' ?>" 
                   href="<?= base_url('/work-orders') ?>">
                    <i class="bi bi-clipboard-check"></i>
                    Work Orders
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'products') ? 'active' : '' ?>" 
                   href="<?= base_url('/products') ?>">
                    <i class="bi bi-box"></i>
                    Products
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner', 'production'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'processes') ? 'active' : '' ?>" 
                   href="<?= base_url('/processes') ?>">
                    <i class="bi bi-arrow-repeat"></i>
                    Processes
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'stores'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'components') ? 'active' : '' ?>" 
                   href="<?= base_url('/components') ?>">
                    <i class="bi bi-layers"></i>
                    Inventory
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'inventory' && current_url(true)->getSegment(2) == 'warehouses') ? 'active' : '' ?>" 
                   href="<?= base_url('/inventory/warehouses') ?>">
                    <i class="bi bi-building"></i>
                    Warehouses
                </a>
            </div>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'inventory' && current_url(true)->getSegment(2) == 'locations') ? 'active' : '' ?>" 
                   href="<?= base_url('/inventory/locations') ?>">
                    <i class="bi bi-geo-alt"></i>
                    Locations
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'qc'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'qc') ? 'active' : '' ?>" 
                   href="<?= base_url('/qc') ?>">
                    <i class="bi bi-shield-check"></i>
                    Quality
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'accounts'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'vendors') ? 'active' : '' ?>" 
                   href="<?= base_url('/vendors') ?>">
                    <i class="bi bi-building"></i>
                    Vendors
                </a>
            </div>
            <?php endif; ?>
            
            <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner'])): ?>
            <div class="nav-item">
                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'reports') ? 'active' : '' ?>" 
                   href="<?= base_url('/reports') ?>">
                    <i class="bi bi-graph-up"></i>
                    Reports
                </a>
            </div>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <div class="top-header">
            <div class="header-left">
                <h1><?= $page_title ?? 'Dashboard' ?></h1>
            </div>
            <div class="header-right">
                <?= $this->include('partials/mega_menu') ?>
                <div class="dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= strtoupper(substr($current_user['first_name'] ?? 'U', 0, 1)) ?>
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header"><?= $current_user['first_name'] ?? 'User' ?> <?= $current_user['last_name'] ?? '' ?></h6></li>
                        <li><span class="dropdown-item-text small text-muted"><?= ucfirst($current_user['role']) ?></span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('/settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/auth/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <div class="content-wrapper">
            <?php if (session()->has('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= session('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->has('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= session('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->has('info')): ?>
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle-fill me-2"></i>
                    <?= session('info') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->has('warning')): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <?= session('warning') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Content -->
            <?= $this->renderSection('content') ?>
        </div>
    </div>
    <?php else: ?>
    <!-- Login Layout -->
    <div class="container-fluid h-100">
        <?= $this->renderSection('content') ?>
    </div>
    <?php endif; ?>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Additional JS -->
    <?= $this->renderSection('js') ?>

    <script>
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // CSRF Token for AJAX requests
        window.csrfToken = '<?= csrf_token() ?>';
        window.csrfHash = '<?= csrf_hash() ?>';
    </script>
</body>
</html>
