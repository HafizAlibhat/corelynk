<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-building me-2"></i>
                Vendors & Suppliers
            </h2>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/vendors/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    New Vendor
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
                               placeholder="Vendor name, contact...">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= esc($category) ?>" <?= $current_category == $category ? 'selected' : '' ?>>
                                    <?= esc($category) ?>
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
                            <a href="<?= base_url('/vendors') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<!-- View Toggle -->
<div class="row mb-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div class="btn-group" role="group">
                <input type="radio" class="btn-check" name="viewMode" id="cardView" autocomplete="off" checked>
                <label class="btn btn-outline-secondary" for="cardView">
                    <i class="bi bi-grid"></i> Cards
                </label>
                
                <input type="radio" class="btn-check" name="viewMode" id="tableView" autocomplete="off">
                <label class="btn btn-outline-secondary" for="tableView">
                    <i class="bi bi-list"></i> Table
                </label>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="exportVendors()">
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
                        <li><a class="dropdown-item" href="<?= base_url('/vendors/import') ?>">
                            <i class="bi bi-upload me-2"></i> Import Vendors
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Card View -->
<div id="cardViewContent">
    <?php if (!empty($vendors['data'])): ?>
        <div class="row">
            <?php foreach ($vendors['data'] as $vendor): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="form-check">
                                    <input class="form-check-input vendor-checkbox" type="checkbox" value="<?= $vendor['id'] ?>">
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="<?= base_url('/vendors/' . $vendor['id']) ?>">
                                            <i class="bi bi-eye me-2"></i> View Details
                                        </a></li>
                                        <?php if ($can_edit): ?>
                                            <li><a class="dropdown-item" href="<?= base_url('/vendors/' . $vendor['id'] . '/edit') ?>">
                                                <i class="bi bi-pencil me-2"></i> Edit
                                            </a></li>
                                        <?php endif; ?>
                                        <li><a class="dropdown-item" href="<?= base_url('/purchase-orders/create?vendor_id=' . $vendor['id']) ?>">
                                            <i class="bi bi-cart-plus me-2"></i> Create PO
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $vendor['id'] ?>)">
                                            <i class="bi bi-arrow-repeat me-2"></i> 
                                            <?= $vendor['is_active'] ? 'Deactivate' : 'Activate' ?>
                                        </a></li>
                                        <?php if ($can_delete): ?>
                                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteVendor(<?= $vendor['id'] ?>)">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </a></li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>

                            <div class="text-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="bi bi-building fs-4 text-primary"></i>
                                </div>
                                <h5 class="card-title mb-1">
                                    <a href="<?= base_url('/vendors/' . $vendor['id']) ?>" class="text-decoration-none">
                                        <?= esc($vendor['name']) ?>
                                    </a>
                                </h5>
                                <?php if (!empty($vendor['contact_person'])): ?>
                                    <p class="text-muted small mb-2"><?= esc($vendor['contact_person']) ?></p>
                                <?php endif ?>
                                
                                <div class="d-flex justify-content-center gap-2 mb-2">
                                    <?php if ($vendor['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                    
                                    <span class="badge bg-info"><?= $vendor['process_count'] ?? 0 ?> Processes</span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <?php if (!empty($vendor['contact_person'])): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-person me-1"></i>
                                            <?= esc($vendor['contact_person']) ?>
                                        </small>
                                    </div>
                                <?php endif ?>
                                
                                <?php if (!empty($vendor['phone'])): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-telephone me-1"></i>
                                            <?= esc($vendor['phone']) ?>
                                        </small>
                                    </div>
                                <?php endif ?>
                                
                                <?php if (!empty($vendor['email'])): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-envelope me-1"></i>
                                            <?= esc($vendor['email']) ?>
                                        </small>
                                    </div>
                                <?php endif ?>
                                
                                <?php if (!empty($vendor['address'])): ?>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <small class="text-muted">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            <?= esc($vendor['address']) ?>
                                        </small>
                                    </div>
                                <?php endif ?>
                                
                                <?php if (!empty($vendor['address'])): ?>
                                    <small class="text-muted">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        <?= esc($vendor['address']) ?>
                                    </small>
                                <?php endif ?>
                                </div>
                            </div>

                            <!-- Performance Indicators -->
                            <div class="row text-center text-sm">
                                <div class="col-4">
                                    <div class="border-end">
                                        <h6 class="mb-0 text-info"><?= $vendor['process_count'] ?? 0 ?></h6>
                                        <small class="text-muted">Processes</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <h6 class="mb-0 <?= $vendor['is_active'] ? 'text-success' : 'text-secondary' ?>">
                                            <?= $vendor['is_active'] ? 'Active' : 'Inactive' ?>
                                        </h6>
                                        <small class="text-muted">Status</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <h6 class="mb-0 text-primary"><?= date('M Y', strtotime($vendor['created_at'])) ?></h6>
                                    <small class="text-muted">Joined</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <?php if (!empty($vendor['certifications'])): ?>
                                        <i class="bi bi-award text-success" title="Certified"></i>
                                    <?php endif; ?>
                                    Since <?= date('Y', strtotime($vendor['created_at'])) ?>
                                </small>
                                <small class="text-success">$<?= number_format($vendor['total_value'] ?? 0, 0) ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-building fs-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No Vendors Found</h5>
            <p class="text-muted">No vendors match your current filters.</p>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/vendors/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add First Vendor
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Table View -->
<div id="tableViewContent" style="display: none;">
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if (!empty($vendors['data'])): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAllTable">
                                </th>
                                <th>Vendor Name</th>
                                <th>Contact Person</th>
                                <th>Phone</th>
                                <th>Email</th>
                                <th>Process Count</th>
                                <th>Status</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendors['data'] as $vendor): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input vendor-checkbox" value="<?= $vendor['id'] ?>">
                                    </td>
                                    <td>
                                        <div>
                                            <a href="<?= base_url('/vendors/' . $vendor['id']) ?>" class="text-decoration-none fw-semibold">
                                                <?= esc($vendor['name']) ?>
                                            </a>
                                            <?php if (!empty($vendor['address'])): ?>
                                                <br><small class="text-muted"><?= esc($vendor['address']) ?></small>
                                            <?php endif ?>
                                        </div>
                                    </td>
                                    <td><?= esc($vendor['contact_person'] ?? '-') ?></td>
                                    <td><?= esc($vendor['phone'] ?? '-') ?></td>
                                    <td><?= esc($vendor['email'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-info"><?= $vendor['process_count'] ?? 0 ?></span>
                                    </td>
                                    <td>
                                        <?php if ($vendor['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactive</span>
                                        <?php endif ?>
                                    </td>
                                    </td>
                                    <td><?= esc($vendor['vendor_code']) ?></td>
                                    <td>
                                        <div>
                                            <?= esc($vendor['contact_person']) ?>
                                            <br><small class="text-muted"><?= esc($vendor['email']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= esc($vendor['category']) ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php 
                                            $rating_class = match(true) {
                                                $vendor['rating'] >= 4 => 'text-success',
                                                $vendor['rating'] >= 3 => 'text-warning',
                                                default => 'text-danger'
                                            };
                                            ?>
                                            <span class="<?= $rating_class ?>"><?= number_format($vendor['rating'], 1) ?></span>
                                            <div class="ms-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star<?= $i <= $vendor['rating'] ? '-fill' : '' ?> text-warning" style="font-size: 0.8rem;"></i>
                                                <?php endfor; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $vendor['payment_terms'] ?> days</td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            <?php if ($vendor['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                            
                                            <?php if ($vendor['is_preferred']): ?>
                                                <span class="badge bg-warning">Preferred</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('/vendors/' . $vendor['id']) ?>" 
                                               class="btn btn-outline-primary" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if ($can_edit): ?>
                                                <a href="<?= base_url('/vendors/' . $vendor['id'] . '/edit') ?>" 
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
                                                    <li><a class="dropdown-item" href="<?= base_url('/purchase-orders/create?vendor_id=' . $vendor['id']) ?>">
                                                        <i class="bi bi-cart-plus me-2"></i> Create PO
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $vendor['id'] ?>)">
                                                        <i class="bi bi-arrow-repeat me-2"></i> 
                                                        <?= $vendor['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                    </a></li>
                                                    <?php if ($can_delete): ?>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteVendor(<?= $vendor['id'] ?>)">
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
<?php if (isset($vendors['pager']) && !empty($vendors['data'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Showing <?= number_format(($vendors['pager']->getCurrentPage() - 1) * $vendors['pager']->getPerPage() + 1) ?> 
                    to <?= number_format(min($vendors['pager']->getCurrentPage() * $vendors['pager']->getPerPage(), $vendors['pager']->getTotal())) ?> 
                    of <?= number_format($vendors['pager']->getTotal()) ?> entries
                </div>
                <?= $vendors['pager']->links() ?>
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
        document.getElementById('tableViewContent').style.display = 'none';
    }
});

document.getElementById('tableView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('cardViewContent').style.display = 'none';
        document.getElementById('tableViewContent').style.display = 'block';
    }
});

// Select all functionality
document.getElementById('selectAllTable').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.vendor-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Export vendors
function exportVendors() {
    window.location.href = '<?= base_url('/vendors/export') ?>';
}

// Toggle vendor status
function toggleStatus(vendorId) {
    if (confirm('Are you sure you want to change the status of this vendor?')) {
        fetch(`<?= base_url('/vendors/') ?>${vendorId}/toggle-status`, {
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

// Delete vendor
function deleteVendor(vendorId) {
    if (confirm('Are you sure you want to delete this vendor? This action cannot be undone.')) {
        fetch(`<?= base_url('/vendors/') ?>${vendorId}/delete`, {
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
                alert(data.message || 'Failed to delete vendor');
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
    const selectedIds = Array.from(document.querySelectorAll('.vendor-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one vendor');
        return;
    }

    const actionText = action === 'activate' ? 'activate' : 'deactivate';
    if (confirm(`Are you sure you want to ${actionText} ${selectedIds.length} vendor(s)?`)) {
        fetch('<?= base_url('/vendors/bulk-update') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': window.csrfHash
            },
            body: JSON.stringify({
                operation: action,
                vendor_ids: selectedIds
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
