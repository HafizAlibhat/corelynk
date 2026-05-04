<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-shield-check me-2"></i>
                Quality Control Records
            </h2>
            <?php if ($can_create): ?>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('/quality-control/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        New QC Record
                    </a>
                    <a href="<?= base_url('/quality-control/inspection') ?>" class="btn btn-success">
                        <i class="bi bi-clipboard-check me-2"></i>
                        Start Inspection
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-clipboard-check fs-2 text-primary mb-2"></i>
                <h4 class="text-primary mb-1"><?= number_format($stats['total_inspections'] ?? 0) ?></h4>
                <small class="text-muted">Total Inspections</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-check-circle fs-2 text-success mb-2"></i>
                <h4 class="text-success mb-1"><?= number_format($stats['passed_inspections'] ?? 0) ?></h4>
                <small class="text-muted">Passed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-x-circle fs-2 text-danger mb-2"></i>
                <h4 class="text-danger mb-1"><?= number_format($stats['failed_inspections'] ?? 0) ?></h4>
                <small class="text-muted">Failed</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-graph-up fs-2 text-info mb-2"></i>
                <h4 class="text-info mb-1"><?= isset($stats['pass_rate']) ? number_format($stats['pass_rate'], 1) . '%' : 'N/A' ?></h4>
                <small class="text-muted">Pass Rate</small>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?= form_open('', ['method' => 'GET', 'class' => 'row g-3']) ?>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               value="<?= esc($current_search) ?>" 
                               placeholder="QC number, product...">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="pending" <?= $current_status == 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="in_progress" <?= $current_status == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="passed" <?= $current_status == 'passed' ? 'selected' : '' ?>>Passed</option>
                            <option value="failed" <?= $current_status == 'failed' ? 'selected' : '' ?>>Failed</option>
                            <option value="on_hold" <?= $current_status == 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Inspector</label>
                        <select class="form-select" name="inspector">
                            <option value="">All Inspectors</option>
                            <?php foreach ($inspectors as $inspector): ?>
                                <option value="<?= $inspector['id'] ?>" <?= $current_inspector == $inspector['id'] ? 'selected' : '' ?>>
                                    <?= esc($inspector['first_name'] . ' ' . $inspector['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Date Range</label>
                        <select class="form-select" name="date_range">
                            <option value="">All Time</option>
                            <option value="today" <?= $current_date_range == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $current_date_range == 'week' ? 'selected' : '' ?>>This Week</option>
                            <option value="month" <?= $current_date_range == 'month' ? 'selected' : '' ?>>This Month</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Per Page</label>
                        <select class="form-select" name="per_page">
                            <option value="20" <?= $per_page == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $per_page == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $per_page == 100 ? 'selected' : '' ?>>100</option>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="<?= base_url('/quality-control') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<!-- Actions Bar -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="viewMode" id="listView" autocomplete="off" checked>
                <label class="btn btn-outline-secondary" for="listView">
                    <i class="bi bi-list"></i> List
                </label>
                
                <input type="radio" class="btn-check" name="viewMode" id="cardView" autocomplete="off">
                <label class="btn btn-outline-secondary" for="cardView">
                    <i class="bi bi-grid"></i> Cards
                </label>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="exportQCRecords()">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                            type="button" 
                            data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('/quality-control/templates') ?>">
                            <i class="bi bi-file-text me-2"></i> Inspection Templates
                        </a></li>
                        <li><a class="dropdown-item" href="<?= base_url('/quality-control/reports') ?>">
                            <i class="bi bi-graph-up me-2"></i> Quality Reports
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/quality-control/settings') ?>">
                            <i class="bi bi-gear me-2"></i> QC Settings
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- List View -->
<div id="listViewContent">
    <?php if (!empty($qc_records['data'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>QC Number</th>
                                <th>Product/WO</th>
                                <th>Inspection Type</th>
                                <th>Inspector</th>
                                <th>Status</th>
                                <th>Test Results</th>
                                <th>Date</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($qc_records['data'] as $record): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input qc-checkbox" value="<?= $record['id'] ?>">
                                    </td>
                                    <td>
                                        <a href="<?= base_url('/quality-control/' . $record['id']) ?>" class="text-decoration-none fw-semibold">
                                            <?= esc($record['qc_number']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= base_url('/products/' . $record['product_id']) ?>" class="text-decoration-none">
                                                <?= esc($record['product_name']) ?>
                                            </a>
                                            <?php if (!empty($record['work_order_id'])): ?>
                                                <br><small class="text-muted">
                                                    WO: <a href="<?= base_url('/work-orders/' . $record['work_order_id']) ?>" class="text-decoration-none">
                                                        <?= esc($record['wo_number']) ?>
                                                    </a>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= esc($record['inspection_type']) ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <?= esc($record['inspector_name']) ?>
                                            <br><small class="text-muted"><?= esc($record['inspector_role']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_config = [
                                            'pending' => ['class' => 'bg-warning', 'icon' => 'clock'],
                                            'in_progress' => ['class' => 'bg-info', 'icon' => 'play-circle'],
                                            'passed' => ['class' => 'bg-success', 'icon' => 'check-circle'],
                                            'failed' => ['class' => 'bg-danger', 'icon' => 'x-circle'],
                                            'on_hold' => ['class' => 'bg-secondary', 'icon' => 'pause-circle']
                                        ];
                                        $config = $status_config[$record['status']] ?? ['class' => 'bg-light text-dark', 'icon' => 'question-circle'];
                                        ?>
                                        <span class="badge <?= $config['class'] ?>">
                                            <i class="bi bi-<?= $config['icon'] ?> me-1"></i>
                                            <?= esc(ucfirst(str_replace('_', ' ', $record['status']))) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($record['test_results'])): ?>
                                            <?php 
                                            $results = json_decode($record['test_results'], true);
                                            $passed_tests = 0;
                                            $total_tests = 0;
                                            
                                            if (is_array($results)) {
                                                foreach ($results as $test) {
                                                    $total_tests++;
                                                    if (isset($test['status']) && $test['status'] === 'pass') {
                                                        $passed_tests++;
                                                    }
                                                }
                                            }
                                            
                                            $pass_rate = $total_tests > 0 ? ($passed_tests / $total_tests) * 100 : 0;
                                            ?>
                                            <div class="progress" style="height: 8px;">
                                                <div class="progress-bar <?= $pass_rate >= 80 ? 'bg-success' : ($pass_rate >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                                     style="width: <?= $pass_rate ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= $passed_tests ?>/<?= $total_tests ?> tests</small>
                                        <?php else: ?>
                                            <small class="text-muted">No tests</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <?= date('M j, Y', strtotime($record['inspection_date'])) ?>
                                            <br><small class="text-muted"><?= date('g:i A', strtotime($record['inspection_date'])) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('/quality-control/' . $record['id']) ?>" 
                                               class="btn btn-outline-primary" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if ($can_edit && in_array($record['status'], ['pending', 'in_progress'])): ?>
                                                <a href="<?= base_url('/quality-control/' . $record['id'] . '/edit') ?>" 
                                                   class="btn btn-outline-secondary" 
                                                   title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <div class="dropdown">
                                                <button class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                        type="button" 
                                                        data-bs-toggle="dropdown">
                                                    <span class="visually-hidden">Toggle Dropdown</span>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($record['status'] === 'pending'): ?>
                                                        <li><a class="dropdown-item" href="<?= base_url('/quality-control/' . $record['id'] . '/start') ?>">
                                                            <i class="bi bi-play-circle me-2"></i> Start Inspection
                                                        </a></li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (in_array($record['status'], ['passed', 'failed'])): ?>
                                                        <li><a class="dropdown-item" href="#" onclick="printCertificate(<?= $record['id'] ?>)">
                                                            <i class="bi bi-printer me-2"></i> Print Certificate
                                                        </a></li>
                                                    <?php endif; ?>
                                                    
                                                    <li><a class="dropdown-item" href="#" onclick="exportRecord(<?= $record['id'] ?>)">
                                                        <i class="bi bi-download me-2"></i> Export Record
                                                    </a></li>
                                                    
                                                    <?php if ($can_delete && $record['status'] === 'pending'): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteRecord(<?= $record['id'] ?>)">
                                                            <i class="bi bi-trash me-2"></i> Delete
                                                        </a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-shield-check fs-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No QC Records Found</h5>
            <p class="text-muted">No quality control records match your current filters.</p>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/quality-control/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    Create First QC Record
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Card View -->
<div id="cardViewContent" style="display: none;">
    <?php if (!empty($qc_records['data'])): ?>
        <div class="row">
            <?php foreach ($qc_records['data'] as $record): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="form-check">
                                    <input class="form-check-input qc-checkbox" type="checkbox" value="<?= $record['id'] ?>">
                                </div>
                                
                                <?php
                                $status_config = [
                                    'pending' => ['class' => 'bg-warning', 'icon' => 'clock'],
                                    'in_progress' => ['class' => 'bg-info', 'icon' => 'play-circle'],
                                    'passed' => ['class' => 'bg-success', 'icon' => 'check-circle'],
                                    'failed' => ['class' => 'bg-danger', 'icon' => 'x-circle'],
                                    'on_hold' => ['class' => 'bg-secondary', 'icon' => 'pause-circle']
                                ];
                                $config = $status_config[$record['status']] ?? ['class' => 'bg-light text-dark', 'icon' => 'question-circle'];
                                ?>
                                <span class="badge <?= $config['class'] ?>">
                                    <i class="bi bi-<?= $config['icon'] ?> me-1"></i>
                                    <?= esc(ucfirst(str_replace('_', ' ', $record['status']))) ?>
                                </span>
                            </div>

                            <div class="text-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="bi bi-shield-check fs-4 text-primary"></i>
                                </div>
                                <h6 class="card-title mb-1">
                                    <a href="<?= base_url('/quality-control/' . $record['id']) ?>" class="text-decoration-none">
                                        <?= esc($record['qc_number']) ?>
                                    </a>
                                </h6>
                                <p class="text-muted small mb-2"><?= esc($record['inspection_type']) ?></p>
                            </div>

                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Product:</small>
                                    <small>
                                        <a href="<?= base_url('/products/' . $record['product_id']) ?>" class="text-decoration-none">
                                            <?= esc($record['product_name']) ?>
                                        </a>
                                    </small>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Inspector:</small>
                                    <small><?= esc($record['inspector_name']) ?></small>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Date:</small>
                                    <small><?= date('M j, Y', strtotime($record['inspection_date'])) ?></small>
                                </div>
                            </div>

                            <?php if (!empty($record['test_results'])): ?>
                                <?php 
                                $results = json_decode($record['test_results'], true);
                                $passed_tests = 0;
                                $total_tests = 0;
                                
                                if (is_array($results)) {
                                    foreach ($results as $test) {
                                        $total_tests++;
                                        if (isset($test['status']) && $test['status'] === 'pass') {
                                            $passed_tests++;
                                        }
                                    }
                                }
                                
                                $pass_rate = $total_tests > 0 ? ($passed_tests / $total_tests) * 100 : 0;
                                ?>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <small class="text-muted">Test Results:</small>
                                        <small class="text-muted"><?= $passed_tests ?>/<?= $total_tests ?></small>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar <?= $pass_rate >= 80 ? 'bg-success' : ($pass_rate >= 60 ? 'bg-warning' : 'bg-danger') ?>" 
                                             style="width: <?= $pass_rate ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="d-flex gap-2">
                                <a href="<?= base_url('/quality-control/' . $record['id']) ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                    <i class="bi bi-eye me-1"></i> View
                                </a>
                                
                                <?php if ($can_edit && in_array($record['status'], ['pending', 'in_progress'])): ?>
                                    <a href="<?= base_url('/quality-control/' . $record['id'] . '/edit') ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                                
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <?php if ($record['status'] === 'pending'): ?>
                                            <li><a class="dropdown-item" href="<?= base_url('/quality-control/' . $record['id'] . '/start') ?>">
                                                <i class="bi bi-play-circle me-2"></i> Start
                                            </a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="#" onclick="exportRecord(<?= $record['id'] ?>)">
                                            <i class="bi bi-download me-2"></i> Export
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<?php if (isset($qc_records['pager']) && !empty($qc_records['data'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Showing <?= number_format(($qc_records['pager']->getCurrentPage() - 1) * $qc_records['pager']->getPerPage() + 1) ?> 
                    to <?= number_format(min($qc_records['pager']->getCurrentPage() * $qc_records['pager']->getPerPage(), $qc_records['pager']->getTotal())) ?> 
                    of <?= number_format($qc_records['pager']->getTotal()) ?> entries
                </div>
                <?= $qc_records['pager']->links() ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// View mode toggle
document.getElementById('listView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('listViewContent').style.display = 'block';
        document.getElementById('cardViewContent').style.display = 'none';
    }
});

document.getElementById('cardView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('listViewContent').style.display = 'none';
        document.getElementById('cardViewContent').style.display = 'block';
    }
});

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.qc-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Export QC records
function exportQCRecords() {
    window.location.href = '<?= base_url('/quality-control/export') ?>';
}

// Export single record
function exportRecord(recordId) {
    window.location.href = `<?= base_url('/quality-control/') ?>${recordId}/export`;
}

// Print certificate
function printCertificate(recordId) {
    window.open(`<?= base_url('/quality-control/') ?>${recordId}/certificate`, '_blank');
}

// Delete record
function deleteRecord(recordId) {
    if (confirm('Are you sure you want to delete this QC record? This action cannot be undone.')) {
        fetch(`<?= base_url('/quality-control/') ?>${recordId}/delete`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete record');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}
</script>
<?= $this->endSection() ?>
