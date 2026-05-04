<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'System Test' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h1 class="mb-0">
                            <i class="fas fa-cogs"></i>
                            Production Management System - Test Panel
                        </h1>
                    </div>
                    <div class="card-body">
                        <!-- Session Status -->
                        <div class="alert alert-success mb-4">
                            <h5><i class="fas fa-user-check"></i> Session Status: ACTIVE</h5>
                            <p class="mb-0">
                                Logged in as: <strong><?= $session_info['first_name'] ?? 'Unknown' ?> <?= $session_info['last_name'] ?? '' ?></strong>
                                (<?= $session_info['email'] ?? 'No email' ?>) - Role: <span class="badge bg-primary"><?= $session_info['role'] ?? 'No role' ?></span>
                            </p>
                        </div>

                        <!-- System Features -->
                        <h3><i class="fas fa-rocket"></i> Available Features</h3>
                        <div class="row">
                            <?php foreach($test_links as $name => $url): ?>
                            <div class="col-md-4 mb-3">
                                <div class="card border-primary">
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?= $name ?></h5>
                                        <a href="<?= $url ?>" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-external-link-alt"></i> Open
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mt-4">
                            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                            <div class="btn-group mb-3" role="group">
                                <a href="<?= base_url('/testsystem/setTestSession') ?>" class="btn btn-warning">
                                    <i class="fas fa-key"></i> Reset Test Session
                                </a>
                                <a href="<?= base_url('/batches') ?>" class="btn btn-success">
                                    <i class="fas fa-boxes"></i> Go to Batches
                                </a>
                                <a href="<?= base_url('/databasetest') ?>" class="btn btn-info">
                                    <i class="fas fa-database"></i> Database Test
                                </a>
                            </div>
                        </div>

                        <!-- Feature Checklist -->
                        <div class="mt-4">
                            <h3><i class="fas fa-check-square"></i> Feature Checklist</h3>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-group">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Batch Management System
                                            <span class="badge bg-success rounded-pill">READY</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ PDF Generation
                                            <span class="badge bg-success rounded-pill">READY</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Gate Pass Control
                                            <span class="badge bg-success rounded-pill">READY</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Delete Operations
                                            <span class="badge bg-success rounded-pill">READY</span>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-group">
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Full-Width Layouts
                                            <span class="badge bg-success rounded-pill">READY</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Database Schema
                                            <span class="badge bg-success rounded-pill">FIXED</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Authentication System
                                            <span class="badge bg-warning rounded-pill">TEST MODE</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            ✅ Professional UI/UX
                                            <span class="badge bg-success rounded-pill">COMPLETE</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- System Info -->
                        <div class="mt-4">
                            <h3><i class="fas fa-info-circle"></i> System Information</h3>
                            <div class="alert alert-info">
                                <strong>Status:</strong> All core features implemented and functional<br>
                                <strong>Database:</strong> MySQL with complete schema and sample data<br>
                                <strong>Framework:</strong> CodeIgniter 4.6.3 with PHP 8.2.12<br>
                                <strong>Features:</strong> Batch Management, PDF Generation, Gate Pass Control, Full CRUD Operations
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
