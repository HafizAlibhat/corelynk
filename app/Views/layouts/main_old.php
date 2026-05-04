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
            justify-content: between;
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
            margin-left: auto;
        }

        /* User Dropdown */
        .user-dropdown {
            position: relative;
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

        /* Status badges */
        .badge {
            font-weight: 500;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
        }
    </style>
    
    <!-- Additional CSS -->
    <?= $this->renderSection('css') ?>
</head>
<body>
<?= $this->include('partials/global_nav') ?>
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (current_url(true)->getSegment(1) == 'products') ? 'active' : '' ?>" 
                               href="<?= base_url('/products') ?>">
                                <i class="bi bi-box me-1"></i> Products
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner', 'production'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (current_url(true)->getSegment(1) == 'processes') ? 'active' : '' ?>" 
                               href="<?= base_url('/processes') ?>">
                                <i class="bi bi-arrow-repeat me-1"></i> Processes
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'stores'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (current_url(true)->getSegment(1) == 'components') ? 'active' : '' ?>" 
                               href="<?= base_url('/components') ?>">
                                <i class="bi bi-layers me-1"></i> Inventory
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'qc'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (current_url(true)->getSegment(1) == 'qc') ? 'active' : '' ?>" 
                               href="<?= base_url('/qc') ?>">
                                <i class="bi bi-shield-check me-1"></i> Quality
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'accounts'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (current_url(true)->getSegment(1) == 'vendors') ? 'active' : '' ?>" 
                               href="<?= base_url('/vendors') ?>">
                                <i class="bi bi-building me-1"></i> Vendors
                            </a>
                        </li>
                        <?php endif; ?>
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin','planner','accounts'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/rfqs') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/rfqs') ?>">
                                <i class="bi bi-file-earmark-text me-1"></i> Purchase RFQs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/pos') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/pos') ?>">
                                <i class="bi bi-cart-check me-1"></i> Purchase Orders (New)
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= (strpos(current_url(), '/newpurchaseui/grn') !== false) ? 'active' : '' ?>" href="<?= site_url('newpurchaseui/grn') ?>">
                                <i class="bi bi-box-seam me-1"></i> Goods Receipt Notes
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (isset($current_user['role']) && in_array($current_user['role'], ['admin', 'planner'])): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= (current_url(true)->getSegment(1) == 'reports') ? 'active' : '' ?>" 
                               href="<?= base_url('/reports') ?>">
                                <i class="bi bi-graph-up me-1"></i> Reports
                            </a>
                        </li>
                        <?php endif; ?>
                </ul>
                
                <?php if (isset($current_user)): ?>
                <div class="navbar-nav">
                    <?= $this->include('partials/mega_menu') ?>
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle me-1"></i>
                            <?= esc($current_user['first_name'] . ' ' . $current_user['last_name']) ?>
                            <span class="badge bg-secondary ms-1"><?= ucfirst($current_user['role']) ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= base_url('/auth/profile') ?>">
                                <i class="bi bi-person me-2"></i> Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?= base_url('/auth/change-password') ?>">
                                <i class="bi bi-key me-2"></i> Change Password
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= base_url('/auth/logout') ?>">
                                <i class="bi bi-box-arrow-right me-2"></i> Logout
                            </a></li>
                        </ul>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid mt-5 pt-3">
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

    <!-- Footer -->
    <footer class="bg-light text-center text-muted py-3 mt-5">
        <div class="container">
            <p class="mb-0">
                &copy; <?= date('Y') ?> Production Management System. 
                Built with CodeIgniter 4 & Bootstrap 5.
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom JS -->
    <script src="<?= base_url('assets/js/app.js') ?>"></script>
    
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
