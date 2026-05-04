<?php // LEGACY LAYOUT — DO NOT MODIFY ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - Production Management System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background-color: var(--light-bg);
            color: var(--gray-700);
            line-height: 1.6;
        }

        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(180deg, var(--white) 0%, var(--gray-50) 100%);
            border-right: 1px solid var(--gray-200);
            transition: all 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
        }

        .sidebar.collapsed {
            width: 70px;
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
            margin-left: 250px;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .content-wrapper {
            flex: 1;
            padding: 1.5rem;
            background-color: var(--gray-50);
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

        /* Status badges */
        .badge {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 6px;
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

        /* Utility classes */
        .border-left-primary {
            border-left: 4px solid var(--primary-color);
        }

        .border-left-success {
            border-left: 4px solid var(--success-color);
        }

        .border-left-info {
            border-left: 4px solid var(--info-color);
        }

        .border-left-warning {
            border-left: 4px solid var(--warning-color);
        }

        .text-xs {
            font-size: 0.75rem;
        }

        /* Expandable sections */
        .expandable-section {
            border: 1px solid var(--gray-200);
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .expandable-header {
            padding: 1rem;
            background: var(--white);
            cursor: pointer;
            display: flex;
            justify-content: between;
            align-items: center;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }

        .expandable-header:hover {
            background: var(--gray-50);
        }

        .expandable-header.active {
            background: var(--primary-color);
            color: white;
        }

        .expandable-content {
            padding: 0 1rem 1rem;
            display: none;
        }

        .expandable-content.show {
            display: block;
        }

        .toggle-icon {
            transition: transform 0.3s ease;
        }

        .toggle-icon.rotated {
            transform: rotate(90deg);
        }

        /* Modal improvements */
        .modal-content {
            border-radius: 12px;
            border: none;
            box-shadow: var(--shadow-lg);
        }

        .modal-header {
            border-bottom: 1px solid var(--gray-200);
            border-radius: 12px 12px 0 0;
        }

        .modal-footer {
            border-top: 1px solid var(--gray-200);
            border-radius: 0 0 12px 12px;
        }
    </style>
</head>
<body>
<?= $this->include('partials/global_nav') ?>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= base_url('/') ?>" class="sidebar-brand">
                <div class="brand-icon">
                    <i class="bi bi-gear-wide-connected"></i>
                </div>
                <span class="brand-text">Production System</span>
            </a>
            <button type="button" class="btn btn-link p-0 sidebar-toggle" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= (current_url() == base_url('/')) ? 'active' : '' ?>" href="<?= base_url('/') ?>">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/work-orders') !== false) ? 'active' : '' ?>" href="<?= base_url('/work-orders') ?>">
                        <i class="bi bi-clipboard-check"></i>
                        <span>Work Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/products') !== false) ? 'active' : '' ?>" href="<?= base_url('/products') ?>">
                        <i class="bi bi-box-seam"></i>
                        <span>Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/processes') !== false) ? 'active' : '' ?>" href="<?= base_url('/processes') ?>">
                        <i class="bi bi-gear"></i>
                        <span>Processes</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/employees') !== false) ? 'active' : '' ?>" href="<?= base_url('/employees') ?>">
                        <i class="bi bi-people"></i>
                        <span>Employees</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/vendors') !== false) ? 'active' : '' ?>" href="<?= base_url('/vendors') ?>">
                        <i class="bi bi-building"></i>
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
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/quality-control') !== false) ? 'active' : '' ?>" href="<?= base_url('/quality-control') ?>">
                        <i class="bi bi-award"></i>
                        <span>Quality Control</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory') !== false) ? 'active' : '' ?>" href="<?= base_url('/inventory') ?>">
                        <i class="bi bi-boxes"></i>
                        <span>Inventory</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/inventory/locations') !== false || strpos(current_url(), '/inventory/warehouses') !== false) ? 'active' : '' ?>" href="<?= site_url('inventory/locations') ?>">
                        <i class="bi bi-diagram-3"></i>
                        <span>Locations</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= (strpos(current_url(), '/reports') !== false) ? 'active' : '' ?>" href="<?= base_url('/reports') ?>">
                        <i class="bi bi-graph-up"></i>
                        <span>Reports</span>
                    </a>
                </li>
            </ul>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Header -->
        <header class="top-header">
            <div class="header-left">
                <h1><?= $this->renderSection('title') ?></h1>
            </div>
            <div class="header-right">
                <?= $this->include('partials/mega_menu') ?>
                <div class="dropdown">
                    <div class="user-avatar" data-bs-toggle="dropdown">
                        S
                    </div>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">System Admin</h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/profile') ?>"><i class="bi bi-person me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('/settings') ?>"><i class="bi bi-gear me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/logout') ?>"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

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

    <script>
        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (alert.classList.contains('show')) {
                        const bsAlert = new bootstrap.Alert(alert);
                        bsAlert.close();
                    }
                }, 5000);
            });
        });

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
