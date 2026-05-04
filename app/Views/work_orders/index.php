<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3 cl-list-page">

<div class="cl-list-header">
    <div>
        <h2 class="mb-0">Work Orders</h2>
        <small class="text-muted">Manage production work orders</small>
    </div>
    <?php if ($can_create): ?>
        <a href="<?= base_url('/work-orders/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-circle me-2"></i>New Work Order
        </a>
    <?php endif; ?>
</div>

<!-- Filters -->
<div class="card cl-list-filters mb-3">
    <div class="card-body">
                <?= form_open('', ['method' => 'GET', 'class' => 'row g-3']) ?>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               value="<?= esc($current_search) ?>" 
                               placeholder="WO Number, Product...">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Status</option>
                            <option value="planned" <?= $current_status == 'planned' ? 'selected' : '' ?>>Planned</option>
                            <option value="in_progress" <?= $current_status == 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                            <option value="completed" <?= $current_status == 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="on_hold" <?= $current_status == 'on_hold' ? 'selected' : '' ?>>On Hold</option>
                            <option value="cancelled" <?= $current_status == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
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
                    
                    <div class="col-md-2">
                        <label class="form-label">Priority</label>
                        <select class="form-select" name="priority">
                            <option value="">All Priorities</option>
                            <option value="low" <?= $current_priority == 'low' ? 'selected' : '' ?>>Low</option>
                            <option value="medium" <?= $current_priority == 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="high" <?= $current_priority == 'high' ? 'selected' : '' ?>>High</option>
                            <option value="urgent" <?= $current_priority == 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="<?= base_url('/work-orders') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                <?= form_close() ?>
    </div>
</div>

<!-- Work Orders Table -->
<div class="card cl-list-table-card">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Work Orders List</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportToCSV()">
                            <i class="bi bi-download me-1"></i> Export CSV
                        </button>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                    type="button" 
                                    data-bs-toggle="dropdown">
                                <i class="bi bi-three-dots"></i>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('priority')">
                                    <i class="bi bi-flag me-2"></i> Change Priority
                                </a></li>
                                <li><a class="dropdown-item" href="#" onclick="bulkAction('status')">
                                    <i class="bi bi-arrow-repeat me-2"></i> Change Status
                                </a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="bulkAction('delete')">
                                    <i class="bi bi-trash me-2"></i> Delete Selected
                                </a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (!empty($work_orders) && count($work_orders) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm mb-0">
                            <thead class="table-light">
                                <tr style="font-size: 0.85rem;">
                                    <th width="30" class="text-center">
                                        <input type="checkbox" class="form-check-input form-check-input-sm" id="selectAll">
                                    </th>
                                    <th>WO Number</th>
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th width="80" class="text-center">Quantity</th>
                                    <th width="90">Status</th>
                                    <th width="80">Priority</th>
                                    <th width="100">Due Date</th>
                                    <th width="60" class="text-center">Progress</th>
                                    <th width="80" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($work_orders as $wo): ?>
                                    <tr>
                                        <td class="text-center">
                                            <input type="checkbox" class="form-check-input form-check-input-sm wo-checkbox" value="<?= $wo['id'] ?>">
                                        </td>
                                        <td>
                                            <a href="<?= base_url('/work-orders/' . $wo['id']) ?>" class="text-decoration-none fw-medium small">
                                                <?= esc($wo['wo_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div>
                                                <?php 
                                                // Check if there are multiple products
                                                $productNames = explode(', ', $wo['product_names'] ?? '');
                                                $productCount = count($productNames);
                                                ?>
                                                
                                                <?php if ($productCount > 1): ?>
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <span class="badge bg-secondary text-white small"><?= $productCount ?> items</span>
                                                        <button type="button" class="btn btn-outline-primary btn-xs px-2 py-1" 
                                                                onclick="viewWorkOrderProducts(<?= $wo['id'] ?>)" 
                                                                style="font-size: 0.75rem;">
                                                            <i class="bi bi-eye"></i> Details
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="small fw-medium"><?= esc($wo['product_names'] ?? 'No Product') ?></div>
                                                    <?php if (!empty($wo['product_codes'])): ?>
                                                        <div class="text-muted" style="font-size: 0.75rem;"><?= esc($wo['product_codes']) ?></div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small"><?= esc($wo['customer_name']) ?></div>
                                        </td>
                                        <td>
                                            <div class="text-center">
                                                <div class="fw-bold small"><?= number_format($wo['total_quantity'] ?? 0) ?></div>
                                                <?php if (!empty($wo['total_completed']) && $wo['total_completed'] > 0): ?>
                                                    <div class="text-success" style="font-size: 0.7rem;">✓ <?= number_format($wo['total_completed']) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'planned' => 'primary',
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                'on_hold' => 'secondary',
                                                'cancelled' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$wo['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $statusColor ?> badge-sm" style="font-size: 0.7rem;">
                                                <?= ucfirst(str_replace('_', ' ', $wo['status'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $priorityColors = [
                                                'low' => 'success',
                                                'normal' => 'info',
                                                'high' => 'warning',
                                                'urgent' => 'danger'
                                            ];
                                            $priorityColor = $priorityColors[$wo['priority']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $priorityColor ?> badge-sm" style="font-size: 0.7rem;">
                                                <?= ucfirst($wo['priority']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($wo['due_date']): ?>
                                                <div class="small"><?= date('M j, Y', strtotime($wo['due_date'])) ?></div>
                                                <?php if (strtotime($wo['due_date']) < time() && $wo['status'] != 'completed'): ?>
                                                    <div class="text-danger" style="font-size: 0.65rem;">
                                                        <i class="bi bi-exclamation-triangle"></i> Overdue
                                                    </div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $progress = 0;
                                            if ($wo['status'] == 'completed') {
                                                $progress = 100;
                                            } elseif ($wo['status'] == 'in_progress') {
                                                $progress = $wo['progress_percentage'] ?? 25;
                                            } elseif ($wo['status'] == 'planned') {
                                                $progress = 0;
                                            }
                                            ?>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar bg-<?= $statusColor ?>" 
                                                     role="progressbar" 
                                                     style="width: <?= $progress ?>%">
                                                </div>
                                            </div>
                                            <small class="text-muted"><?= $progress ?>%</small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="<?= base_url('/work-orders/' . $wo['id']) ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php if ($can_edit && in_array($wo['status'], ['planned', 'in_progress', 'on_hold'])): ?>
                                                    <a href="<?= base_url('/work-orders/' . $wo['id'] . '/edit') ?>" 
                                                       class="btn btn-outline-secondary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php if ($can_delete && in_array($wo['status'], ['planned', 'on_hold'])): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-danger" 
                                                            title="Delete" 
                                                            onclick="deleteWorkOrder(<?= $wo['id'] ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <div class="dropdown">
                                                    <button class="btn btn-outline-secondary dropdown-toggle dropdown-toggle-split" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown">
                                                        <span class="visually-hidden">Toggle Dropdown</span>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <?php if ($wo['status'] == 'planned'): ?>
                                                            <li><a class="dropdown-item" href="#" onclick="startProduction(<?= $wo['id'] ?>)">
                                                                <i class="bi bi-play me-2"></i> Start Production
                                                            </a></li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (in_array($wo['status'], ['planned', 'in_progress'])): ?>
                                                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= $wo['id'] ?>, 'on_hold')">
                                                                <i class="bi bi-pause me-2"></i> Put On Hold
                                                            </a></li>
                                                        <?php endif; ?>
                                                        
                                                        <?php if ($wo['status'] == 'on_hold'): ?>
                                                            <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= $wo['id'] ?>, 'in_progress')">
                                                                <i class="bi bi-play me-2"></i> Resume
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
                    
                    <!-- Pagination -->
                    <?php if (isset($pager) && $pager): ?>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    Showing results
                                </div>
                                <?= $pager->links() ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox fs-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No Work Orders Found</h5>
                        <p class="text-muted">No work orders match your current filters.</p>
                        <?php if ($can_create ?? false): ?>
                            <a href="<?= base_url('/work-orders/create') ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>
                                Create First Work Order
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
</div>
</div>

<!-- Status Change Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="statusForm">
                    <input type="hidden" id="statusWorkOrderId">
                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select class="form-select" id="newStatus" required>
                            <option value="">Select Status</option>
                            <option value="planned">Planned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Remarks (Optional)</label>
                        <textarea class="form-control" id="statusRemarks" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitStatusChange()">Update Status</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.wo-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Export to CSV
function exportToCSV() {
    window.location.href = '<?= base_url('/work-orders/export-csv') ?>';
}

// Start production
function startProduction(workOrderId) {
    if (confirm('Are you sure you want to start production for this work order?')) {
        fetch(`<?= base_url('/work-orders/') ?>${workOrderId}/start-production`, {
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
                alert(data.message || 'Failed to start production');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Change status
function changeStatus(workOrderId, status) {
    document.getElementById('statusWorkOrderId').value = workOrderId;
    document.getElementById('newStatus').value = status;
    new bootstrap.Modal(document.getElementById('statusModal')).show();
}

// Submit status change
function submitStatusChange() {
    const workOrderId = document.getElementById('statusWorkOrderId').value;
    const newStatus = document.getElementById('newStatus').value;
    const remarks = document.getElementById('statusRemarks').value;

    if (!newStatus) {
        alert('Please select a status');
        return;
    }

    fetch(`<?= base_url('/work-orders/') ?>${workOrderId}/change-status`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfHash
        },
        body: JSON.stringify({
            status: newStatus,
            remarks: remarks
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
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

// Delete work order
function deleteWorkOrder(workOrderId) {
    if (confirm('Are you sure you want to delete this work order?\n\nThis action cannot be undone and will remove all associated data.')) {
        // Get CSRF token from meta tag or window
        var csrfToken = '';
        var metaToken = document.querySelector('meta[name="csrf-token"]');
        if (metaToken) {
            csrfToken = metaToken.getAttribute('content');
        } else {
            csrfToken = window.csrfToken || window.csrfHash || '';
        }
        
        // Try DELETE method first, fallback to POST if needed
        fetch(`<?= base_url('/work-orders/') ?>${workOrderId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => {
            if (response.status === 404) {
                // If DELETE route not found, try POST to /delete endpoint
                return fetch(`<?= base_url('/work-orders/') ?>${workOrderId}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
            }
            return response;
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Work order deleted successfully!');
                location.reload();
            } else {
                alert('❌ ' + (data.message || 'Failed to delete work order'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Network error occurred while deleting the work order');
        });
    }
}

// Bulk actions
function bulkAction(action) {
    const selectedIds = Array.from(document.querySelectorAll('.wo-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one work order');
        return;
    }

    if (action === 'delete') {
        if (confirm(`Are you sure you want to delete ${selectedIds.length} work order(s)? This action cannot be undone.`)) {
            // Get CSRF token
            var csrfToken = window.csrfToken || window.csrfHash || '';
            
            // Delete each work order
            let promises = selectedIds.map(id => {
                return fetch(`<?= base_url('/work-orders/') ?>${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken
                    }
                }).catch(error => {
                    // If DELETE fails, try POST to /delete
                    return fetch(`<?= base_url('/work-orders/') ?>${id}/delete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken
                        }
                    });
                });
            });

            Promise.all(promises)
                .then(responses => Promise.all(responses.map(r => r.json())))
                .then(results => {
                    const successful = results.filter(r => r.success).length;
                    const failed = results.length - successful;
                    
                    if (failed === 0) {
                        alert(`✅ Successfully deleted ${successful} work order(s)`);
                    } else {
                        alert(`⚠️ Deleted ${successful} work order(s), but ${failed} failed to delete`);
                    }
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during bulk delete');
                });
        }
    } else {
        console.log(`Bulk ${action} for work orders:`, selectedIds);
        alert(`Bulk ${action} feature will be implemented soon.`);
    }
}

// View work order products
function viewWorkOrderProducts(workOrderId) {
    const modal = new bootstrap.Modal(document.getElementById('productsModal'));
    modal.show();
    
    // Reset content
    document.getElementById('totalProducts').textContent = '-';
    document.getElementById('totalQuantity').textContent = '-';
    document.getElementById('totalCompleted').textContent = '-';
    document.getElementById('modalProductsList').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div class="mt-2">Loading products...</div>
        </div>
    `;
    
    // Fetch products
    fetch(`<?= base_url('work-orders') ?>/${workOrderId}/products`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update summary
                document.getElementById('totalProducts').textContent = data.totals.total_products;
                document.getElementById('totalQuantity').textContent = data.totals.total_quantity;
                document.getElementById('totalCompleted').textContent = data.totals.total_completed;
                
                // Build products HTML
                let productsHtml = '';
                
                if (data.products && data.products.length > 0) {
                    data.products.forEach(product => {
                        // Handle images field which may be missing or JSON
                        let imagePath = '<?= base_url('assets/images/no-image.png') ?>';
                        try {
                            if (product && product.images) {
                                // If images is JSON array
                                if (typeof product.images === 'string') {
                                    try {
                                        const images = JSON.parse(product.images);
                                        if (images && images.length > 0) {
                                            imagePath = `<?= base_url('uploads/products/') ?>${images[0]}`;
                                        }
                                    } catch (e) {
                                        // Not JSON — treat as single filename
                                        imagePath = `<?= base_url('uploads/products/') ?>${product.images}`;
                                    }
                                } else if (Array.isArray(product.images) && product.images.length > 0) {
                                    imagePath = `<?= base_url('uploads/products/') ?>${product.images[0]}`;
                                }
                            }
                        } catch (e) {
                            // Defensive fallback — keep placeholder
                            console.debug('Product image handling fallback', e);
                        }
                        
                        const completionPercentage = product.quantity_ordered > 0 ? Math.round((product.quantity_completed / product.quantity_ordered) * 100) : 0;
                        
                        productsHtml += `
                            <div class="card mb-2 border-0 shadow-sm">
                                <div class="card-body p-3">
                                    <div class="row align-items-center">
                                        <div class="col-2">
                                            <img src="${imagePath}" 
                                                 class="img-fluid rounded" 
                                                 alt="${product.product_name || 'Product'}"
                                                 style="height: 45px; width: 45px; object-fit: cover;">
                                        </div>
                                        <div class="col-5">
                                            <h6 class="mb-1 fw-bold">${product.product_name || 'Unknown Product'}</h6>
                                            <small class="text-muted">${product.product_code || 'N/A'}</small>
                                        </div>
                                        <div class="col-5">
                                            <div class="row text-center">
                                                <div class="col-4">
                                                    <div class="fw-bold text-primary">${product.quantity_ordered}</div>
                                                    <small class="text-muted">Ordered</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold text-success">${product.quantity_completed}</div>
                                                    <small class="text-muted">Done</small>
                                                </div>
                                                <div class="col-4">
                                                    <div class="fw-bold text-info">${completionPercentage}%</div>
                                                    <small class="text-muted">Complete</small>
                                                </div>
                                            </div>
                                            <div class="progress mt-2" style="height: 4px;">
                                                <div class="progress-bar ${completionPercentage === 100 ? 'bg-success' : completionPercentage > 0 ? 'bg-warning' : 'bg-secondary'}" 
                                                     style="width: ${completionPercentage}%">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    productsHtml = `
                        <div class="text-center py-4">
                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No products found</h5>
                            <p class="text-muted">This work order doesn't have any products assigned.</p>
                        </div>
                    `;
                }
                
                document.getElementById('modalProductsList').innerHTML = productsHtml;
            } else {
                document.getElementById('modalProductsList').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error loading products: ${data.message || 'Unknown error'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('modalProductsList').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error loading products. Please try again.
                </div>
            `;
        });
}
</script>

<!-- Products Modal -->
<div class="modal fade" id="productsModal" tabindex="-1" aria-labelledby="productsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light border-bottom">
                <h5 class="modal-title text-dark" id="productsModalLabel">
                    <i class="bi bi-box-seam me-2"></i>Work Order Products
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Summary Cards -->
                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <div class="text-center p-2 bg-primary text-white rounded">
                            <div class="h5 mb-0" id="totalProducts">-</div>
                            <small>Products</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 bg-success text-white rounded">
                            <div class="h5 mb-0" id="totalQuantity">-</div>
                            <small>Total Qty</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="text-center p-2 bg-info text-white rounded">
                            <div class="h5 mb-0" id="totalCompleted">-</div>
                            <small>Completed</small>
                        </div>
                    </div>
                </div>

                <!-- Products List -->
                <div id="modalProductsList">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <div class="mt-2">Loading products...</div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* Make table more compact and professional */
.table td, .table th {
    padding: 0.5rem 0.75rem;
    vertical-align: middle;
    font-size: 0.875rem;
}

.table tbody tr:hover {
    background-color: rgba(0,0,0,0.02);
}

.btn-xs {
    padding: 0.125rem 0.5rem;
    font-size: 0.75rem;
    line-height: 1.25;
    border-radius: 0.2rem;
}

.badge-sm {
    padding: 0.25em 0.6em;
    font-size: 0.7rem;
}

/* Compact form controls */
.form-select, .form-control {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Modal improvements */
.modal-body {
    padding: 1rem;
}

.card {
    border-radius: 0.375rem;
}

.card-body {
    padding: 0.75rem;
}

/* Progress bar improvements */
.progress {
    height: 0.5rem;
    border-radius: 0.25rem;
}
</style>

</script>
<?= $this->endSection() ?>
