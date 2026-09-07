<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-diagram-3 me-2"></i>
                    <?= esc($process['name']) ?>
                </h2>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-primary">Sequence <?= $process['sequence_number'] ?></span>
                    <span class="badge bg-light text-dark"><?= esc($process['operation_type']) ?></span>
                    <?php if ($process['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                    <?php if ($process['quality_check_required']): ?>
                        <span class="badge bg-warning">Quality Check Required</span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Processes
                </a>
                
                <?php if ($can_edit): ?>
                    <a href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-2"></i>
                        Edit Process
                    </a>
                <?php endif; ?>
                
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <?php if ($can_edit): ?>
                            <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id'] . '/copy') ?>">
                                <i class="bi bi-files me-2"></i> Duplicate Process
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="#" onclick="exportProcessDetails()">
                            <i class="bi bi-download me-2"></i> Export Details
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="printProcessDetails()">
                            <i class="bi bi-printer me-2"></i> Print Work Instructions
                        </a></li>
                        <?php if ($can_edit): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="toggleStatus()">
                                <i class="bi bi-arrow-repeat me-2"></i> 
                                <?= $process['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </a></li>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteProcess()">
                                <i class="bi bi-trash me-2"></i> Delete Process
                            </a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Main Content -->
    <div class="col-xl-8">
        <!-- Process Overview -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Process Overview
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($process['description'])): ?>
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Description</h6>
                        <p class="mb-0"><?= nl2br(esc($process['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" width="40%">Department:</td>
                                <td><span class="badge bg-light text-dark"><?= esc($process['department']) ?></span></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Skill Level Required:</td>
                                <td><?= esc($process['required_skill_level']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Parallel Processing:</td>
                                <td>
                                    <?php if ($process['parallel_processing']): ?>
                                        <i class="bi bi-check-circle text-success"></i> Allowed
                                    <?php else: ?>
                                        <i class="bi bi-x-circle text-danger"></i> Not Allowed
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Created:</td>
                                <td><?= date('M j, Y g:i A', strtotime($process['created_at'])) ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" width="40%">Machine Required:</td>
                                <td><?= !empty($process['machine_required']) ? esc($process['machine_required']) : 'None specified' ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tools Required:</td>
                                <td><?= !empty($process['tools_required']) ? esc($process['tools_required']) : 'None specified' ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Quality Check:</td>
                                <td>
                                    <?php if ($process['quality_check_required']): ?>
                                        <i class="bi bi-shield-check text-warning"></i> Required
                                    <?php else: ?>
                                        <i class="bi bi-shield text-muted"></i> Not Required
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Last Updated:</td>
                                <td><?= date('M j, Y g:i A', strtotime($process['updated_at'])) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Time and Cost Analysis -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock me-2"></i>
                    Time and Cost Analysis
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-primary mb-1"><?= $process['standard_time_minutes'] ?></h4>
                            <small class="text-muted">Standard Time (min)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-info mb-1"><?= $process['setup_time_minutes'] ?: '0' ?></h4>
                            <small class="text-muted">Setup Time (min)</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border-end">
                            <h4 class="text-success mb-1">$<?= number_format($process['labor_cost_per_hour'], 2) ?></h4>
                            <small class="text-muted">Labor Cost/Hour</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-warning mb-1">$<?= number_format($process['total_cost_per_unit'], 2) ?></h4>
                        <small class="text-muted">Total Cost/Unit</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <h6 class="mb-3">Cost Breakdown</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Labor Cost:</span>
                            <span>$<?= number_format(($process['labor_cost_per_hour'] * $process['standard_time_minutes']) / 60, 2) ?></span>
                        </div>
                        <?php if ($process['overhead_cost_per_hour'] > 0): ?>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-muted">Overhead Cost:</span>
                                <span>$<?= number_format(($process['overhead_cost_per_hour'] * $process['standard_time_minutes']) / 60, 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total Cost per Unit:</strong>
                            <strong>$<?= number_format($process['total_cost_per_unit'], 2) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="mb-3">Efficiency Metrics</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Time per Unit:</span>
                            <span><?= $process['standard_time_minutes'] ?> minutes</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Units per Hour:</span>
                            <span><?= number_format(60 / $process['standard_time_minutes'], 1) ?> units</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Daily Capacity (8h):</span>
                            <span><?= number_format((60 * 8) / $process['standard_time_minutes'], 0) ?> units</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Work Instructions -->
        <?php if (!empty($process['work_instructions'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-clipboard-check me-2"></i>
                        Work Instructions
                    </h5>
                    <button class="btn btn-sm btn-outline-primary" onclick="printInstructions()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>
                <div class="card-body">
                    <div class="work-instructions">
                        <?= nl2br(esc($process['work_instructions'])) ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Equipment Specifications -->
        <?php if (!empty($process['equipment_specifications'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-cpu me-2"></i>
                        Equipment Specifications
                    </h5>
                </div>
                <div class="card-body">
                    <?= nl2br(esc($process['equipment_specifications'])) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quality Standards and Safety -->
        <div class="row">
            <?php if (!empty($process['quality_standards'])): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-shield-check me-2"></i>
                                Quality Standards
                            </h5>
                        </div>
                        <div class="card-body">
                            <?= nl2br(esc($process['quality_standards'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($process['safety_requirements'])): ?>
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Safety Requirements
                            </h5>
                        </div>
                        <div class="card-body">
                            <?= nl2br(esc($process['safety_requirements'])) ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Process Usage in Work Orders -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>
                    Recent Work Orders Using This Process
                </h5>
                <a href="<?= base_url('/work-orders?process_id=' . $process['id']) ?>" class="btn btn-sm btn-outline-primary">
                    View All
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_work_orders)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Work Order</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Progress</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_work_orders as $wo): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('/work-orders/' . $wo['id']) ?>" class="text-decoration-none">
                                                <?= esc($wo['wo_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <a href="<?= base_url('/products/' . $wo['product_id']) ?>" class="text-decoration-none">
                                                <?= esc($wo['product_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= number_format($wo['quantity_ordered']) ?></td>
                                        <td>
                                            <?php
                                            $status_class = match($wo['status']) {
                                                'pending' => 'bg-warning',
                                                'in_progress' => 'bg-primary',
                                                'completed' => 'bg-success',
                                                'on_hold' => 'bg-secondary',
                                                'cancelled' => 'bg-danger',
                                                default => 'bg-light text-dark'
                                            };
                                            ?>
                                            <span class="badge <?= $status_class ?>"><?= esc(ucfirst(str_replace('_', ' ', $wo['status']))) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                            $progress = $wo['quantity_ordered'] > 0 ? ($wo['quantity_completed'] / $wo['quantity_ordered']) * 100 : 0;
                                            ?>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($wo['due_date'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                        <h6 class="mt-2 text-muted">No recent work orders</h6>
                        <p class="text-muted">This process hasn't been used in any work orders recently.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-xl-4">
        <!-- Process Flow Navigation -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-diagram-3 me-2"></i>
                    Process Flow
                </h5>
            </div>
            <div class="card-body">
                <!-- Previous Process -->
                <?php if (isset($previous_process)): ?>
                    <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                        <div class="me-3">
                            <i class="bi bi-arrow-up-circle text-muted"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">
                                <a href="<?= base_url('/processes/' . $previous_process['id']) ?>" class="text-decoration-none">
                                    <?= esc($previous_process['name']) ?>
                                </a>
                            </h6>
                            <small class="text-muted">Step <?= $previous_process['sequence_number'] ?> - Previous</small>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Current Process -->
                <div class="d-flex align-items-center mb-3 p-3 bg-primary bg-opacity-10 rounded border border-primary">
                    <div class="me-3">
                        <i class="bi bi-gear text-primary"></i>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0 text-primary"><?= esc($process['name']) ?></h6>
                        <small class="text-muted">Step <?= $process['sequence_number'] ?> - Current</small>
                    </div>
                </div>

                <!-- Next Process -->
                <?php if (isset($next_process)): ?>
                    <div class="d-flex align-items-center mb-3 p-3 bg-light rounded">
                        <div class="me-3">
                            <i class="bi bi-arrow-down-circle text-muted"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-0">
                                <a href="<?= base_url('/processes/' . $next_process['id']) ?>" class="text-decoration-none">
                                    <?= esc($next_process['name']) ?>
                                </a>
                            </h6>
                            <small class="text-muted">Step <?= $next_process['sequence_number'] ?> - Next</small>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="text-center">
                    <a href="<?= base_url('/processes?view=flow') ?>" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-diagram-3 me-1"></i> View Full Flow
                    </a>
                </div>
            </div>
        </div>

        <!-- Performance Statistics -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Performance Statistics
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-primary mb-1"><?= number_format($stats['total_work_orders'] ?? 0) ?></h5>
                            <small class="text-muted">Total Orders</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-success mb-1"><?= number_format($stats['completed_orders'] ?? 0) ?></h5>
                        <small class="text-muted">Completed</small>
                    </div>
                </div>

                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-info mb-1"><?= isset($stats['avg_actual_time']) ? number_format($stats['avg_actual_time'], 1) . 'm' : 'N/A' ?></h5>
                            <small class="text-muted">Avg. Actual Time</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-warning mb-1"><?= isset($stats['efficiency_rate']) ? number_format($stats['efficiency_rate'], 1) . '%' : 'N/A' ?></h5>
                        <small class="text-muted">Efficiency</small>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between mb-2">
                    <small class="text-muted">Total Units Produced:</small>
                    <small><?= number_format($stats['total_units_produced'] ?? 0) ?></small>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <small class="text-muted">Avg. Setup Time:</small>
                    <small><?= isset($stats['avg_setup_time']) ? $stats['avg_setup_time'] . 'm' : 'N/A' ?></small>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Quality Pass Rate:</small>
                    <small><?= isset($stats['quality_pass_rate']) ? number_format($stats['quality_pass_rate'], 1) . '%' : 'N/A' ?></small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightning me-2"></i>
                    Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= base_url('/work-orders/create?process_id=' . $process['id']) ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        Create Work Order
                    </a>
                    
                    <a href="<?= base_url('/quality-control?process_id=' . $process['id']) ?>" class="btn btn-outline-warning">
                        <i class="bi bi-shield-check me-2"></i>
                        Quality Records
                    </a>
                    
                    <a href="<?= base_url('/reports/process/' . $process['id']) ?>" class="btn btn-outline-info">
                        <i class="bi bi-graph-up me-2"></i>
                        Performance Report
                    </a>
                    
                    <a href="<?= base_url('/processes/' . $process['id'] . '/time-study') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-stopwatch me-2"></i>
                        Time Study
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// Toggle process status
function toggleStatus() {
    if (confirm('Are you sure you want to change the status of this process?')) {
        fetch(`<?= base_url('/processes/' . $process['id'] . '/toggle-status') ?>`, {
            method: 'POST',
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
                alert(data.message || 'Failed to change status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Delete process
function deleteProcess() {
    if (confirm('Are you sure you want to delete this process? This action cannot be undone.')) {
        fetch(`<?= base_url('/processes/' . $process['id'] . '/delete') ?>`, {
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
                window.location.href = '<?= base_url('/processes') ?>';
            } else {
                alert(data.message || 'Failed to delete process');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Export process details
function exportProcessDetails() {
    window.location.href = '<?= base_url('/processes/' . $process['id'] . '/export') ?>';
}

// Print process details
function printProcessDetails() {
    window.print();
}

// Print work instructions only
function printInstructions() {
    const instructions = document.querySelector('.work-instructions').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Work Instructions - <?= esc($process['name']) ?></title>
                <style>
                    body { font-family: Arial, sans-serif; margin: 20px; }
                    h1 { color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
                    .header { margin-bottom: 20px; }
                    .content { white-space: pre-line; }
                </style>
            </head>
            <body>
                <div class="header">
                    <h1><?= esc($process['name']) ?> - Work Instructions</h1>
                    <p><strong>Operation Type:</strong> <?= esc($process['operation_type']) ?></p>
                    <p><strong>Department:</strong> <?= esc($process['department']) ?></p>
                    <p><strong>Sequence:</strong> Step <?= $process['sequence_number'] ?></p>
                    <p><strong>Standard Time:</strong> <?= $process['standard_time_minutes'] ?> minutes</p>
                </div>
                <div class="content">${instructions}</div>
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}
</script>
<?= $this->endSection() ?>
