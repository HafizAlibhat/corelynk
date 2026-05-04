<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-boxes me-2"></i>
                Components & Inventory
            </h2>
            <?php if ($can_create): ?>
                <div class="d-flex gap-2">
                    <a href="<?= base_url('/components/create') ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        New Component
                    </a>
                    <a href="<?= base_url('/inventory/transaction') ?>" class="btn btn-success">
                        <i class="bi bi-arrow-left-right me-2"></i>
                        Stock Transaction
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Inventory Summary Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-boxes fs-2 text-primary mb-2"></i>
                <h4 class="text-primary mb-1"><?= number_format($stats['total_components'] ?? 0) ?></h4>
                <small class="text-muted">Total Components</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-exclamation-triangle fs-2 text-warning mb-2"></i>
                <h4 class="text-warning mb-1"><?= number_format($stats['low_stock_items'] ?? 0) ?></h4>
                <small class="text-muted">Low Stock Alerts</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-graph-up fs-2 text-success mb-2"></i>
                <h4 class="text-success mb-1">$<?= number_format($stats['total_value'] ?? 0, 0) ?></h4>
                <small class="text-muted">Total Inventory Value</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="bi bi-arrow-repeat fs-2 text-info mb-2"></i>
                <h4 class="text-info mb-1"><?= number_format($stats['transactions_today'] ?? 0) ?></h4>
                <small class="text-muted">Today's Transactions</small>
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
                               placeholder="Component name, SKU...">
                    </div>
                    
                    <div class="col-md-2">
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
                        <label class="form-label">Stock Status</label>
                        <select class="form-select" name="stock_status">
                            <option value="">All Stock</option>
                            <option value="in_stock" <?= $current_stock_status == 'in_stock' ? 'selected' : '' ?>>In Stock</option>
                            <option value="low_stock" <?= $current_stock_status == 'low_stock' ? 'selected' : '' ?>>Low Stock</option>
                            <option value="out_of_stock" <?= $current_stock_status == 'out_of_stock' ? 'selected' : '' ?>>Out of Stock</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location">
                            <option value="">All Locations</option>
                            <?php foreach ($locations as $location): ?>
                                <option value="<?= esc($location) ?>" <?= $current_location == $location ? 'selected' : '' ?>>
                                    <?= esc($location) ?>
                                </option>
                            <?php endforeach; ?>
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
                            <a href="<?= base_url('/components') ?>" class="btn btn-outline-secondary">
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
                <input type="radio" class="btn-check" name="viewMode" id="tableView" autocomplete="off" checked>
                <label class="btn btn-outline-secondary" for="tableView">
                    <i class="bi bi-list"></i> Table
                </label>
                
                <input type="radio" class="btn-check" name="viewMode" id="cardView" autocomplete="off">
                <label class="btn btn-outline-secondary" for="cardView">
                    <i class="bi bi-grid"></i> Cards
                </label>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="exportInventory()">
                    <i class="bi bi-download me-1"></i> Export
                </button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                            type="button" 
                            data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('/inventory/stock-take') ?>">
                            <i class="bi bi-clipboard-check me-2"></i> Stock Take
                        </a></li>
                        <li><a class="dropdown-item" href="<?= base_url('/inventory/adjustments') ?>">
                            <i class="bi bi-sliders me-2"></i> Stock Adjustments
                        </a></li>
                        <li><a class="dropdown-item" href="<?= base_url('/inventory/reports') ?>">
                            <i class="bi bi-graph-up me-2"></i> Inventory Reports
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?= base_url('/components/import') ?>">
                            <i class="bi bi-upload me-2"></i> Import Components
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Table View -->
<div id="tableViewContent">
    <?php if (!empty($components['data'])): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>Component</th>
                                <th>SKU</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Reserved</th>
                                <th>Available</th>
                                <th>Unit Cost</th>
                                <th>Total Value</th>
                                <th>Location</th>
                                <th width="120">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($components['data'] as $component): ?>
                                <tr class="<?= $component['current_stock'] <= $component['reorder_level'] ? 'table-warning' : '' ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input component-checkbox" value="<?= $component['id'] ?>">
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="me-3">
                                                <?php if ($component['current_stock'] <= 0): ?>
                                                    <i class="bi bi-exclamation-circle text-danger" title="Out of Stock"></i>
                                                <?php elseif ($component['current_stock'] <= $component['reorder_level']): ?>
                                                    <i class="bi bi-exclamation-triangle text-warning" title="Low Stock"></i>
                                                <?php else: ?>
                                                    <i class="bi bi-check-circle text-success" title="In Stock"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <a href="<?= base_url('/components/' . $component['id']) ?>" class="text-decoration-none fw-semibold">
                                                    <?= esc($component['name']) ?>
                                                </a>
                                                <?php if (!empty($component['description'])): ?>
                                                    <br><small class="text-muted"><?= esc(substr($component['description'], 0, 50)) ?>...</small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= esc($component['sku']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= esc($component['category']) ?></span>
                                    </td>
                                    <td>
                                        <div>
                                            <span class="fw-semibold"><?= number_format($component['current_stock'], 2) ?></span>
                                            <small class="text-muted"><?= esc($component['uom']) ?></small>
                                            <?php if ($component['current_stock'] <= $component['reorder_level']): ?>
                                                <br><small class="text-warning">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    Reorder Level: <?= number_format($component['reorder_level'], 2) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-muted"><?= number_format($component['reserved_stock'] ?? 0, 2) ?></span>
                                        <small class="text-muted"><?= esc($component['uom']) ?></small>
                                    </td>
                                    <td>
                                        <span class="text-success fw-semibold"><?= number_format($component['current_stock'] - ($component['reserved_stock'] ?? 0), 2) ?></span>
                                        <small class="text-muted"><?= esc($component['uom']) ?></small>
                                    </td>
                                    <td>$<?= number_format($component['unit_cost'], 2) ?></td>
                                    <td>$<?= number_format($component['current_stock'] * $component['unit_cost'], 2) ?></td>
                                    <td>
                                        <small class="text-muted"><?= esc($component['location'] ?? 'Not set') ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <a href="<?= base_url('/components/' . $component['id']) ?>" 
                                               class="btn btn-outline-primary" 
                                               title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            
                                            <?php if ($can_edit): ?>
                                                <a href="<?= base_url('/components/' . $component['id'] . '/edit') ?>" 
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
                                                    <li><a class="dropdown-item" href="#" onclick="quickAdjust(<?= $component['id'] ?>)">
                                                        <i class="bi bi-sliders me-2"></i> Quick Adjust
                                                    </a></li>
                                                    <li><a class="dropdown-item" href="<?= base_url('/inventory/transactions?component_id=' . $component['id']) ?>">
                                                        <i class="bi bi-clock-history me-2"></i> View History
                                                    </a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item" href="#" onclick="createPurchaseOrder(<?= $component['id'] ?>)">
                                                        <i class="bi bi-cart-plus me-2"></i> Create PO
                                                    </a></li>
                                                    <?php if ($can_delete): ?>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteComponent(<?= $component['id'] ?>)">
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
            <i class="bi bi-boxes fs-1 text-muted"></i>
            <h5 class="mt-3 text-muted">No Components Found</h5>
            <p class="text-muted">No components match your current filters.</p>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/components/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add First Component
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Card View -->
<div id="cardViewContent" style="display: none;">
    <?php if (!empty($components['data'])): ?>
        <div class="row">
            <?php foreach ($components['data'] as $component): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card border-0 shadow-sm h-100 <?= $component['current_stock'] <= $component['reorder_level'] ? 'border-warning' : '' ?>">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div class="form-check">
                                    <input class="form-check-input component-checkbox" type="checkbox" value="<?= $component['id'] ?>">
                                </div>
                                
                                <!-- Stock Status Badge -->
                                <?php if ($component['current_stock'] <= 0): ?>
                                    <span class="badge bg-danger">Out of Stock</span>
                                <?php elseif ($component['current_stock'] <= $component['reorder_level']): ?>
                                    <span class="badge bg-warning">Low Stock</span>
                                <?php else: ?>
                                    <span class="badge bg-success">In Stock</span>
                                <?php endif; ?>
                            </div>

                            <div class="text-center mb-3">
                                <div class="bg-primary bg-opacity-10 rounded-circle mx-auto mb-3 d-flex align-items-center justify-content-center" 
                                     style="width: 60px; height: 60px;">
                                    <i class="bi bi-box fs-4 text-primary"></i>
                                </div>
                                <h6 class="card-title mb-1">
                                    <a href="<?= base_url('/components/' . $component['id']) ?>" class="text-decoration-none">
                                        <?= esc($component['name']) ?>
                                    </a>
                                </h6>
                                <p class="text-muted small mb-2"><?= esc($component['sku']) ?></p>
                                <span class="badge bg-light text-dark"><?= esc($component['category']) ?></span>
                            </div>

                            <!-- Stock Information -->
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="border-end">
                                        <h6 class="mb-0 text-primary"><?= number_format($component['current_stock'], 1) ?></h6>
                                        <small class="text-muted">Current</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="border-end">
                                        <h6 class="mb-0 text-warning"><?= number_format($component['reserved_stock'] ?? 0, 1) ?></h6>
                                        <small class="text-muted">Reserved</small>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <h6 class="mb-0 text-success"><?= number_format($component['current_stock'] - ($component['reserved_stock'] ?? 0), 1) ?></h6>
                                    <small class="text-muted">Available</small>
                                </div>
                            </div>

                            <!-- Component Details -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Unit Cost:</small>
                                    <small>$<?= number_format($component['unit_cost'], 2) ?></small>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">Total Value:</small>
                                    <small class="fw-semibold">$<?= number_format($component['current_stock'] * $component['unit_cost'], 2) ?></small>
                                </div>
                                
                                <div class="d-flex justify-content-between mb-2">
                                    <small class="text-muted">UOM:</small>
                                    <small><?= esc($component['uom']) ?></small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">Location:</small>
                                    <small><?= esc($component['location'] ?? 'Not set') ?></small>
                                </div>
                            </div>

                            <!-- Reorder Alert -->
                            <?php if ($component['current_stock'] <= $component['reorder_level']): ?>
                                <div class="alert alert-warning p-2 mb-3">
                                    <small>
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Reorder Level: <?= number_format($component['reorder_level'], 2) ?> <?= esc($component['uom']) ?>
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-footer bg-transparent border-top-0 pt-0">
                            <div class="d-flex gap-2">
                                <a href="<?= base_url('/components/' . $component['id']) ?>" class="btn btn-sm btn-outline-primary flex-fill">
                                    <i class="bi bi-eye me-1"></i> View
                                </a>
                                
                                <?php if ($can_edit): ?>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="quickAdjust(<?= $component['id'] ?>)">
                                        <i class="bi bi-sliders"></i>
                                    </button>
                                <?php endif; ?>
                                
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                            type="button" 
                                            data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="<?= base_url('/inventory/transactions?component_id=' . $component['id']) ?>">
                                            <i class="bi bi-clock-history me-2"></i> History
                                        </a></li>
                                        <li><a class="dropdown-item" href="#" onclick="createPurchaseOrder(<?= $component['id'] ?>)">
                                            <i class="bi bi-cart-plus me-2"></i> Create PO
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

<!-- Quick Adjust Modal -->
<div class="modal fade" id="quickAdjustModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Quick Stock Adjustment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="adjustForm">
                    <input type="hidden" id="adjustComponentId">
                    <div class="mb-3">
                        <label class="form-label">Component</label>
                        <input type="text" class="form-control" id="adjustComponentName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Current Stock</label>
                        <input type="text" class="form-control" id="adjustCurrentStock" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adjustment Type</label>
                        <select class="form-select" id="adjustType" required>
                            <option value="">Select Type</option>
                            <option value="addition">Stock In</option>
                            <option value="reduction">Stock Out</option>
                            <option value="correction">Correction</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" class="form-control" id="adjustQuantity" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Reason</label>
                        <textarea class="form-control" id="adjustReason" rows="2" placeholder="Reason for adjustment"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitAdjustment()">Adjust Stock</button>
            </div>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if (isset($components['pager']) && !empty($components['data'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted">
                    Showing <?= number_format(($components['pager']->getCurrentPage() - 1) * $components['pager']->getPerPage() + 1) ?> 
                    to <?= number_format(min($components['pager']->getCurrentPage() * $components['pager']->getPerPage(), $components['pager']->getTotal())) ?> 
                    of <?= number_format($components['pager']->getTotal()) ?> entries
                </div>
                <?= $components['pager']->links() ?>
            </div>
        </div>
    </div>
<?php endif; ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// View mode toggle
document.getElementById('tableView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('tableViewContent').style.display = 'block';
        document.getElementById('cardViewContent').style.display = 'none';
    }
});

document.getElementById('cardView').addEventListener('change', function() {
    if (this.checked) {
        document.getElementById('tableViewContent').style.display = 'none';
        document.getElementById('cardViewContent').style.display = 'block';
    }
});

// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.component-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Export inventory
function exportInventory() {
    window.location.href = '<?= base_url('/components/export') ?>';
}

// Quick adjust stock
function quickAdjust(componentId) {
    // Get component data from the table row
    const row = document.querySelector(`input[value="${componentId}"]`).closest('tr');
    const componentName = row.querySelector('a').textContent.trim();
    const currentStock = row.cells[4].querySelector('.fw-semibold').textContent.trim();
    
    document.getElementById('adjustComponentId').value = componentId;
    document.getElementById('adjustComponentName').value = componentName;
    document.getElementById('adjustCurrentStock').value = currentStock;
    
    const modal = new bootstrap.Modal(document.getElementById('quickAdjustModal'));
    modal.show();
}

// Submit stock adjustment
function submitAdjustment() {
    const componentId = document.getElementById('adjustComponentId').value;
    const adjustType = document.getElementById('adjustType').value;
    const quantity = document.getElementById('adjustQuantity').value;
    const reason = document.getElementById('adjustReason').value;
    
    if (!adjustType || !quantity) {
        alert('Please fill in all required fields');
        return;
    }
    
    fetch('<?= base_url('/inventory/quick-adjust') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': window.csrfHash
        },
        body: JSON.stringify({
            component_id: componentId,
            type: adjustType,
            quantity: parseFloat(quantity),
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('quickAdjustModal')).hide();
            location.reload();
        } else {
            alert(data.message || 'Failed to adjust stock');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

// Create purchase order
function createPurchaseOrder(componentId) {
    window.location.href = `<?= base_url('/purchase-orders/create?component_id=') ?>${componentId}`;
}

// Delete component
function deleteComponent(componentId) {
    if (confirm('Are you sure you want to delete this component? This action cannot be undone.')) {
        fetch(`<?= base_url('/components/') ?>${componentId}/delete`, {
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
                alert(data.message || 'Failed to delete component');
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
