<?php // LEGACY LAYOUT — DO NOT MODIFY ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Production Management System' ?> - <?= date('Y-m-d H:i:s') ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?= base_url('/assets/css/custom.css') ?>" rel="stylesheet">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= csrf_hash() ?>">
    <!-- Cache Buster: <?= time() ?> -->
    
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
        
        .sidebar.collapsed {
            width: 60px;
        }
        
        .sidebar.collapsed .nav-link span {
            display: none;
        }
        
        .sidebar.collapsed .sidebar-brand .brand-text {
            display: none;
        }

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

        .sidebar-toggle {
            background: none;
            border: none;
            color: var(--gray-600);
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .sidebar-toggle:hover {
            color: var(--primary-color);
            background: var(--gray-100);
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
        }

        .nav-link:hover {
            background: var(--gray-100);
            color: var(--primary-color);
            text-decoration: none;
        }

        .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
            color: white;
            box-shadow: var(--shadow-md);
        }

        .nav-link i {
            width: 20px;
            margin-right: 0.75rem;
            font-size: 1rem;
        }

        /* Main content adjustment */
        .main-content {
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--light-bg);
            transition: margin-left 0.3s ease;
        }
        
        .main-content.sidebar-collapsed {
            margin-left: 70px;
        }

        .top-header {
            background: var(--white);
            border-bottom: 1px solid var(--gray-200);
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow-sm);
        }

        .content-wrapper {
            flex: 1;
            padding: 1.5rem;
            background-color: var(--gray-50);
        }

        /* Mobile responsive */
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
        }
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
        
        /* Sidebar Styles */
        .sidebar {
            background-color: #f8f9fa !important;
            border-right: 1px solid #dee2e6;
            min-height: 100vh;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            width: 250px;
            padding-top: 20px;
        }
        
        /* Main content adjustment */
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease-in-out;
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        .sidebar .nav-link {
            color: #495057;
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            margin: 0.125rem 0;
        }
        .sidebar .nav-link:hover {
            background-color: #e9ecef;
            color: #495057;
        }
        .sidebar .nav-link.active {
            background-color: #0d6efd;
            color: white;
        }
        .sidebar .nav-link.active:hover {
            background-color: #0b5ed7;
            color: white;
        }
        .sidebar .nav-link i {
            width: 16px;
            text-align: center;
        }
    </style>
    
    <!-- Additional CSS -->
    <?= $this->renderSection('css') ?>
</head>
<body>
<?= $this->include('partials/global_nav') ?>
    <?php if (isset($current_user)): ?>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-brand">
                <i class="bi bi-gear-fill"></i>
                <span class="brand-text">Production System</span>
            </div>
            <button type="button" class="btn btn-link p-0 sidebar-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>
        <div class="position-sticky pt-3">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'dashboard') ? 'active' : '' ?>" href="<?= base_url('/dashboard') ?>">
                        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
                    </a>
                </li>
                
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner', 'production'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'work-orders') ? 'active' : '' ?>" href="<?= base_url('/work-orders') ?>">
                        <i class="bi bi-clipboard-check"></i><span>Work Orders</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'employees') ? 'active' : '' ?>" href="<?= base_url('/employees') ?>">
                        <i class="bi bi-people"></i><span>Employees</span>
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= in_array(current_url(true)->getSegment(1), ['products', 'product-categories']) ? 'active' : '' ?>" 
                       data-bs-toggle="collapse" 
                       href="#productsSubmenu" 
                       role="button" 
                       aria-expanded="false" 
                       aria-controls="productsSubmenu">
                        <i class="bi bi-box me-2"></i>Products <i class="bi bi-chevron-down ms-auto"></i>
                    </a>
                    <div class="collapse" id="productsSubmenu">
                        <ul class="nav flex-column ms-3">
                            <li class="nav-item">
                                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'products') ? 'active' : '' ?>" href="<?= base_url('/products') ?>">
                                    <i class="bi bi-grid me-2"></i>Products
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?= (current_url(true)->getSegment(1) == 'product-categories') ? 'active' : '' ?>" href="<?= base_url('/product-categories') ?>">
                                    <i class="bi bi-tags me-2"></i>Categories
                                </a>
                            </li>
                        </ul>
                    </div>
                </li>
                
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'processes') ? 'active' : '' ?>" href="<?= base_url('/processes') ?>">
                        <i class="bi bi-diagram-3 me-2"></i>Processes
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'stores'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'inventory') ? 'active' : '' ?>" href="<?= base_url('/inventory') ?>">
                        <i class="bi bi-box-seam me-2"></i>Inventory
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory/locations') !== false || strpos(current_url(), '/inventory/warehouses') !== false) ? 'active' : '' ?>" href="<?= site_url('inventory/locations') ?>">
                        <i class="bi bi-diagram-3 me-2"></i>Locations
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'qc'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'quality') ? 'active' : '' ?>" href="<?= base_url('/quality') ?>">
                        <i class="bi bi-shield-check me-2"></i>Quality Control
                    </a>
                </li>
                <?php endif; ?>
                
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'vendors') ? 'active' : '' ?>" href="<?= base_url('/vendors') ?>">
                        <i class="bi bi-building me-2"></i>Vendors
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin','planner','accounts'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/rfqs') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/rfqs') ?>">
                        <i class="bi bi-file-earmark-text me-2"></i>Purchase RFQs
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/pos') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/pos') ?>">
                        <i class="bi bi-cart-check me-2"></i>Purchase Orders (New)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/grn') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/grn') ?>">
                        <i class="bi bi-box-seam me-2"></i>Goods Receipt Notes
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="nav-item">
                    <a class="nav-link <?= (current_url(true)->getSegment(1) == 'reports') ? 'active' : '' ?>" href="<?= base_url('/reports') ?>">
                        <i class="bi bi-bar-chart me-2"></i>Reports
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    
    <!-- Main content -->
    <div class="main-content">
                <!-- Top Header -->
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?= $page_title ?? 'Dashboard' ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <?= $this->include('partials/mega_menu') ?>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 24px; height: 24px; font-size: 12px;">
                                    <?= strtoupper(substr($current_user['first_name'] ?? 'U', 0, 1)) ?>
                                </div>
                                <?= $current_user['first_name'] ?? 'User' ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><h6 class="dropdown-header"><?= $current_user['first_name'] ?? 'User' ?> <?= $current_user['last_name'] ?? '' ?></h6></li>
                                <li><span class="dropdown-item-text small text-muted"><?= ucfirst($current_user['role'] ?? 'user') ?></span></li>
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
        
        // Sidebar toggle functionality
        document.addEventListener('DOMContentLoaded', function() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.querySelector('.main-content');
            
            if (sidebarToggle && sidebar && mainContent) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('sidebar-collapsed');
                    
                    // Save state to localStorage
                    if (sidebar.classList.contains('collapsed')) {
                        localStorage.setItem('sidebarCollapsed', 'true');
                    } else {
                        localStorage.removeItem('sidebarCollapsed');
                    }
                });
                
                // Restore state from localStorage
                if (localStorage.getItem('sidebarCollapsed') === 'true') {
                    sidebar.classList.add('collapsed');
                    mainContent.classList.add('sidebar-collapsed');
                }
            }
        });
    </script>
</body>
</html>
