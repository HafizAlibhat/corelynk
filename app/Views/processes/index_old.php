<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-diagram-3 me-2"></i>
                Manufacturing Processes
            </h2>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/processes/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    New Process
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <?= form_open('', ['method' => 'GET', 'class' => 'row g-3']) ?>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               value="<?= esc($current_search) ?>" 
                               placeholder="Process name, operation...">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Product</label>
                        <select class="form-select" name="product">
                            <option value="">All Products</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" <?= $current_product == $product['id'] ? 'selected' : '' ?>>
                                    <?= esc($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Vendor</label>
                        <select class="form-select" name="vendor">
                            <option value="">All Vendors</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" <?= $current_vendor == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= esc($vendor['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?= $current_status === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $current_status === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                            <option value="">All Status</option>
                            <option value="1" <?= $current_status === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $current_status === '0' ? 'selected' : '' ?>>Inactive</option>
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
                            <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<!-- Process Flow Diagram Toggle -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="viewMode" id="cardView" autocomplete="off" checked>
                <label class="btn btn-outline-secondary" for="cardView">
                    <i class="bi bi-grid"></i> Card View
                </label>
                
                <input type="radio" class="btn-check" name="viewMode" id="flowView" autocomplete="off">
                <label class="btn btn-outline-secondary" for="flowView">
                    <i class="bi bi-diagram-3"></i> Flow View
                </label>
                
                <input type="radio" class="btn-check" name="viewMode" id="listView" autocomplete="off">
                <label class="btn btn-outline-secondary" for="listView">
                    <i class="bi bi-list"></i> List View
                </label>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="exportProcesses()">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                            type="button" 
                            data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" onclick="bulkAction('activate')">
                            <i class="bi bi-check-circle me-2"></i> Activate Selected
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="bulkAction('deactivate')">
                            <i class="bi bi-x-circle me-2"></i> Deactivate Selected
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/processes/import') ?>">
                            <i class="bi bi-upload me-2"></i> Import Processes
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card View -->
<div id="cardViewContent">
    <?php if (!empty($processes)): ?>
        <div class="row">
            <?php foreach ($processes as $process): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="form-check">
                                    <input class="form-check-input process-checkbox" type="checkbox" value="<?= $process['id'] ?>">
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id']) ?>">
                                            <i class="bi bi-eye me-2"></i> View Details
                                        </a></li>
                                        <?php if ($can_edit): ?>
                                            <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>">
                                                <i class="bi bi-pencil me-2"></i> Edit
                                            </a></li>
                                            <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id'] . '/copy') ?>">
                                                <i class="bi bi-files me-2"></i> Duplicate
                                            </a></li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $process['id'] ?>)">
                                            <i class="bi bi-arrow-repeat me-2"></i> 
                                            <?= $process['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a></li>
                                        <?php if ($can_delete): ?>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteProcess(<?= $process['id'] ?>)">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <div class="text-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="bi bi-gear fs-4 text-primary"></i>
                                </div>
                                <h5 class="card-title mb-1">
                                    <a href="<?= base_url('/processes/' . $process['id']) ?>" class="text-decoration-none">
                                        <?= esc($process['name']) ?>
                                    </a>
                                </h5>
                                <p class="text-muted small mb-2">
                                    <?= $process['is_vendor_process'] ? 'Outsourced' : 'In-House' ?>
                                    <?php if ($process['is_vendor_process'] && !empty($process['vendor_name'])): ?>
                                        to <?= esc($process['vendor_name']) ?>
                                    <?php endif; ?>
                                </p>
                                
                                <?php if ($process['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($process['description'])): ?>
                                <p class="text-muted small mb-3">
                                    <?= esc(substr($process['description'], 0, 100)) ?><?= strlen($process['description']) > 100 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>

                            <div class="row text-center text-sm mb-3">
                                <div class="col-6">
                                    <div class="border-end">
                                        <?php if ($process['is_vendor_process']): ?>
                                            <h6 class="mb-0 text-warning">Outsource</h6>
                                            <small class="text-muted">Type</small>
                                        <?php else: ?>
                                            <h6 class="mb-0 text-primary">In-House</h6>
                                            <small class="text-muted">Type</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div>
                                        <?php if ($process['is_vendor_process'] && !empty($process['vendor_name'])): ?>
                                            <h6 class="mb-0 text-info"><?= esc($process['vendor_name']) ?></h6>
                                            <small class="text-muted">Vendor</small>
                                        <?php else: ?>
                                            <h6 class="mb-0 text-muted">-</h6>
                                            <small class="text-muted">Vendor</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div class="btn-group" role="group">
                                    <a href="<?= base_url('/processes/' . $process['id']) ?>" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                </div>
                                <div class="col-4">
                                    <h6 class="mb-0 text-success">$<?= number_format($process['labor_cost_per_hour'], 0) ?>/hr</h6>
                                    <small class="text-muted">Labor</small>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center text-muted small">
                                <span><i class="bi bi-building me-1"></i><?= esc($process['department']) ?></span>
                                <span><i class="bi bi-people me-1"></i><?= $process['required_skill_level'] ?></span>
                            </div>

                            <?php if (!empty($process['machine_required'])): ?>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="bi bi-cpu me-1"></i>
                                        Machine: <?= esc($process['machine_required']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">Setup: <?= $process['setup_time_minutes'] ?>m</small>
                                <small class="text-muted">
                                    <?php if ($process['quality_check_required']): ?>
                                        <i class="bi bi-shield-check text-warning" title="Quality Check Required"></i>
                                    <?php endif; ?>
                                    <?php if (!empty($process['safety_requirements'])): ?>
                                        <i class="bi bi-exclamation-triangle text-danger ms-1" title="Safety Requirements"></i>
                                    <?php endif; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-diagram-3 fs-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No Processes Found</h5>
            <p class="text-muted">No manufacturing processes match your current filters.</p>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/processes/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    Create First Process
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Flow View -->
<div id="flowViewContent" style="display: none;">
    <?php if (!empty($processes)): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="process-flow-container" style="overflow-x: auto; white-space: nowrap; padding: 20px 0;">
                            <?php 
                            if (function_exists('collect')) {
                                $sorted_processes = collect($processes)->sortBy('name')->values()->all();
                            } else {
                                $sorted_processes = $processes;
                                usort($sorted_processes, function($a, $b) {
                                    return strcasecmp($a['name'] ?? '', $b['name'] ?? '');
                                });
                            }
                            foreach ($sorted_processes as $index => $process): 
                            ?>
                                <div class="process-step d-inline-block text-center mx-3" style="vertical-align: top; width: 200px; white-space: normal;">
                                    <div class="card border-primary <?= $process['is_active'] ? '' : 'border-secondary' ?> mb-2">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <span class="badge <?= $process['is_active'] ? 'bg-primary' : 'bg-secondary' ?>">
                                                    <?= $process['is_vendor_process'] ? 'Outsource' : 'In-House' ?>
                                                </span>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id']) ?>">
                                                            <i class="bi bi-eye me-2"></i> View
                                                        </a></li>
                                                        <?php if ($can_edit): ?>
                                                            <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>">
                                                                <i class="bi bi-pencil me-2"></i> Edit
                                                            </a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <h6 class="card-title mb-1">
                                                <a href="<?= base_url('/processes/' . $process['id']) ?>" class="text-decoration-none">
                                                    <?= esc($process['name']) ?>
                                                </a>
                                            </h6>
                                            <p class="text-muted small mb-2">
                                                <?= $process['is_vendor_process'] ? 'Outsourced' : 'In-House' ?>
                                            </p>
                                            
                                            <div class="text-center">
                                                <?php if ($process['is_vendor_process'] && !empty($process['vendor_name'])): ?>
                                    <small class="text-muted d-block">Vendor: <?= esc($process['vendor_name']) ?></small>
                                <?php endif; ?>
                                                <small class="text-muted d-block">Dept: <?= esc($process['department']) ?></small>
                                                <?php if (!empty($process['machine_required'])): ?>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-cpu me-1"></i><?= esc(substr($process['machine_required'], 0, 15)) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($index < count($sorted_processes) - 1): ?>
                                    <div class="process-arrow d-inline-block mx-2" style="vertical-align: middle;">
                                        <i class="bi bi-arrow-right fs-3 text-primary"></i>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- List View -->
<div id="listViewContent" style="display: none;">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (!empty($processes['data'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAllList">
                                </th>
                                <th>Process Name</th>
                                <th>Product</th>
                                <th>Type</th>
                                <th>Vendor</th>
                                <th>Status</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($processes as $process): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input process-checkbox" value="<?= $process['id'] ?>">
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= base_url('/processes/' . $process['id']) ?>" class="text-decoration-none fw-semibold">
                                                <?= esc($process['name']) ?>
                                            </a>
                                            <?php if (!empty($process['description'])): ?>
                                                <br><small class="text-muted"><?= esc(substr($process['description'], 0, 50)) ?>...</small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?= esc($process['product_name'] ?? 'Unknown Product') ?></strong><br>
                                        <small class="text-muted"><?= esc($process['product_code'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if ($process['is_vendor_process']): ?>
                                            <span class="badge bg-warning text-dark">Outsourced</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">In-House</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($process['is_vendor_process'] && !empty($process['vendor_name'])): ?>
                                            <small><?= esc($process['vendor_name']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($process['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($process['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('/processes/' . $process['id']) ?>" 
                                               class="btn btn-outline-primary" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if ($can_edit): ?>
                                                <a href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>" 
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
                                                    <?php if ($can_edit): ?>
                                                        <li><a class="dropdown-item" href="<?= base_url('/processes/' . $process['id'] . '/copy') ?>">
                                                            <i class="bi bi-files me-2"></i> Duplicate
                                                        </a></li>
                                                        <li><hr class="dropdown-divider"></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $process['id'] ?>)">
                                                        <i class="bi bi-arrow-repeat me-2"></i> 
                                                        <?= $process['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                    </a></li>
                                                    <?php if ($can_delete): ?>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteProcess(<?= $process['id'] ?>)">
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
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if (isset($pager) && !empty($processes)): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Showing <?= number_format(($pager->getCurrentPage() - 1) * $pager->getPerPage() + 1) ?> 
                    to <?= number_format(min($pager->getCurrentPage() * $pager->getPerPage(), $pager->getTotal())) ?> 
                    of <?= number_format($pager->getTotal()) ?> entries
                </div>
                <?= $pager->links() ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// View mode toggle
document.getElementById('cardView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('cardViewContent').style.display = 'block';
        document.getElementById('flowViewContent').style.display = 'none';
        document.getElementById('listViewContent').style.display = 'none';
    }
});

document.getElementById('flowView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('cardViewContent').style.display = 'none';
        document.getElementById('flowViewContent').style.display = 'block';
        document.getElementById('listViewContent').style.display = 'none';
    }
});

document.getElementById('listView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('cardViewContent').style.display = 'none';
        document.getElementById('flowViewContent').style.display = 'none';
        document.getElementById('listViewContent').style.display = 'block';
    }
});

// Select all functionality
document.getElementById('selectAllList').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.process-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Export processes
function exportProcesses() {
    window.location.href = '<?= base_url('/processes/export') ?>';
}

// Toggle process status
function toggleStatus(processId) {
    if (confirm('Are you sure you want to change the status of this process?')) {
        fetch(`<?= base_url('/processes/') ?>${processId}/toggle-status`, {
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
function deleteProcess(processId) {
    if (confirm('Are you sure you want to delete this process? This action cannot be undone.')) {
        fetch(`<?= base_url('/processes/') ?>${processId}/delete`, {
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
                alert(data.message || 'Failed to delete process');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Bulk actions
function bulkAction(action) {
    const selectedIds = Array.from(document.querySelectorAll('.process-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one process');
        return;
    }

    const actionText = action === 'activate' ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${actionText} ${selectedIds.length} process(es)?`)) {
        fetch('<?= base_url('/processes/bulk-update') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash
            },
            body: JSON.stringify({
                operation: action,
                process_ids: selectedIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Bulk operation failed');
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
