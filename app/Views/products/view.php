<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $productIdentifier = entityRouteIdentifier($product); ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">
                    <i class="bi bi-box me-2"></i>
                    <?= esc($product['name']) ?>
                </h2>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge bg-light text-dark"><?= esc($product['code']) ?></span>
                    <?php if ($product['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                    <span class="badge bg-info"><?= esc($product['category_name'] ?? 'No Category') ?></span>
                </div>
            </div>
            
            <div class="d-flex gap-2">
                <a href="<?= base_url('/products') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Products
                </a>
                
                <?php if ($can_edit): ?>
                    <a href="<?= base_url('/products/' . $productIdentifier . '/edit') ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-2"></i>
                        Edit Product
                    </a>
                <?php endif; ?>
                
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-three-dots"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <?php if ($can_edit): ?>
                            <li><a class="dropdown-item" href="<?= base_url('/products/' . $productIdentifier . '/bom') ?>">
                                <i class="bi bi-diagram-3 me-2"></i> Manage BOM
                            </a></li>
                            <li><a class="dropdown-item" href="<?= base_url('/products/' . $productIdentifier . '/copy') ?>">
                                <i class="bi bi-files me-2"></i> Duplicate Product
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="#" onclick="exportProductDetails()">
                            <i class="bi bi-download me-2"></i> Export Details
                        </a></li>
                        <li><a class="dropdown-item" href="#" onclick="printProductDetails()">
                            <i class="bi bi-printer me-2"></i> Print
                        </a></li>
                        <?php if ($can_edit): ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="#" onclick="toggleStatus()">
                                <i class="bi bi-arrow-repeat me-2"></i> 
                                <?= $product['is_active'] ? 'Deactivate' : 'Activate' ?>
                            </a></li>
                        <?php endif; ?>
                        <?php if ($can_delete): ?>
                            <li><a class="dropdown-item text-danger" href="#" onclick="deleteProduct()">
                                <i class="bi bi-trash me-2"></i> Delete Product
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
        <!-- Basic Information -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Product Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (!empty($product['description'])): ?>
                    <div class="mb-4">
                        <h6 class="text-muted mb-2">Description</h6>
                        <p class="mb-0"><?= nl2br(esc($product['description'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" width="40%">Product Code:</td>
                                <td><strong><?= esc($product['code']) ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Unit of Measure:</td>
                                <td><?= esc($product['unit']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Category:</td>
                                <td><?= esc($product['category_name'] ?? 'No Category') ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Status:</td>
                                <td>
                                    <?php if ($product['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" width="40%">Created:</td>
                                <td><?= date('M j, Y g:i A', strtotime($product['created_at'])) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Last Updated:</td>
                                <td><?= date('M j, Y g:i A', strtotime($product['updated_at'])) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Weight & Pricing Overview -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-currency-dollar me-2"></i>
                    Weight & Pricing
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" width="40%">Weight:</td>
                                <td>
                                    <?php if (!empty($product['weight'])): ?>
                                        <strong><?= esc($product['weight']) ?></strong>
                                        <?= !empty($product['unit']) ? esc($product['unit']) : '' ?>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Cost Price:</td>
                                <td>
                                    <?php
                                    $cost = $product['cost_price'] ?? ($product['standard_cost'] ?? 0);
                                    $costCurr = $product['cost_currency'] ?? ($product['sale_currency'] ?? ($default_currency ?? 'USD'));
                                    ?>
                                    <strong><?= $cost !== null ? esc(number_format((float)$cost, 2)) : '0.00' ?></strong>
                                    <small class="text-muted"><?= esc($costCurr) ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Sale Price:</td>
                                <td>
                                    <?php
                                    $sale = $product['sale_price'] ?? ($product['selling_price'] ?? 0);
                                    $saleCurr = $product['sale_currency'] ?? ($product['cost_currency'] ?? ($default_currency ?? 'USD'));
                                    ?>
                                    <strong><?= $sale !== null ? esc(number_format((float)$sale, 2)) : '0.00' ?></strong>
                                    <small class="text-muted"><?= esc($saleCurr) ?></small>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Vendor:</td>
                                <td>
                                    <?= esc($product['vendor_name'] ?? ($product['vendor']['name'] ?? 'N/A')) ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6 mb-3">
                        <table class="table table-borderless table-sm">
                            <tr>
                                <td class="text-muted" width="40%">Profit / Margin:</td>
                                <td>
                                    <?php
                                    $costVal = (float) ($cost ?? 0);
                                    $saleVal = (float) ($sale ?? 0);
                                    $profit = $saleVal - $costVal;
                                    $marginPct = $costVal > 0 ? ($profit / $costVal) * 100 : 0;
                                    ?>
                                    <div class="d-flex align-items-center gap-3">
                                        <div>
                                            <h4 class="mb-0 text-info"><?= esc(number_format($profit, 2)) ?></h4>
                                            <small class="text-muted">Profit</small>
                                        </div>
                                        <div>
                                            <h4 class="mb-0 text-warning"><?= esc(number_format($marginPct, 1)) ?>%</h4>
                                            <small class="text-muted">Markup</small>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Currency Pair:</td>
                                <td>
                                    <small class="text-muted">Cost: <?= esc($costCurr) ?> • Sale: <?= esc($saleCurr) ?></small>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Technical Specifications -->
        <?php if (!empty($product['specifications'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>
                        Technical Specifications
                    </h5>
                </div>
                <div class="card-body">
                    <?php 
                    $specs = json_decode($product['specifications'], true);
                    if (is_array($specs)): 
                    ?>
                        <div class="row">
                            <?php foreach ($specs as $key => $value): ?>
                                <div class="col-md-6 mb-2">
                                    <strong><?= esc(ucfirst(str_replace('_', ' ', $key))) ?>:</strong>
                                    <span class="text-muted"><?= esc($value) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="mb-0"><?= nl2br(esc($product['specifications'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quality Standards -->
        <?php if (!empty($product['quality_standards'])): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        Quality Standards
                    </h5>
                </div>
                <div class="card-body">
                    <p class="mb-0"><?= nl2br(esc($product['quality_standards'])) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Bill of Materials -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-diagram-3 me-2"></i>
                    Bill of Materials
                </h5>
                <?php if ($can_edit): ?>
                    <a href="<?= base_url('/products/' . $productIdentifier . '/bom') ?>" class="btn btn-sm btn-primary">
                        <i class="bi bi-pencil me-1"></i> Manage BOM
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($bom_items)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Component</th>
                                    <th>SKU</th>
                                    <th>Quantity</th>
                                    <th>UOM</th>
                                    <th>Cost per Unit</th>
                                    <th>Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $total_bom_cost = 0;
                                foreach ($bom_items as $item): 
                                    $item_total = $item['quantity'] * $item['unit_cost'];
                                    $total_bom_cost += $item_total;
                                ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('/products/' . $item['component_id']) ?>" class="text-decoration-none">
                                                <?= esc($item['component_name']) ?>
                                            </a>
                                        </td>
                                        <td><?= esc($item['component_sku']) ?></td>
                                        <td><?= number_format($item['quantity'], 2) ?></td>
                                        <td><?= esc($item['uom']) ?></td>
                                        <td>$<?= number_format($item['unit_cost'], 2) ?></td>
                                        <td>$<?= number_format($item_total, 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="5">Total BOM Cost:</th>
                                    <th>$<?= number_format($total_bom_cost, 2) ?></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-diagram-3 fs-1 text-muted"></i>
                        <h6 class="mt-2 text-muted">No BOM items configured</h6>
                        <p class="text-muted">This product doesn't have any bill of materials items.</p>
                        <?php if ($can_edit): ?>
                            <a href="<?= base_url('/products/' . $productIdentifier . '/bom') ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i> Add BOM Items
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Work Orders -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>
                    Recent Work Orders
                </h5>
                <a href="<?= base_url('/work-orders?product_id=' . $product['id']) ?>" class="btn btn-sm btn-outline-primary">
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
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Due Date</th>
                                    <th>Progress</th>
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
                                        <td><?= date('M j, Y', strtotime($wo['due_date'])) ?></td>
                                        <td>
                                            <?php 
                                            $progress = $wo['quantity_ordered'] > 0 ? ($wo['quantity_completed'] / $wo['quantity_ordered']) * 100 : 0;
                                            ?>
                                            <div class="progress" style="height: 6px;">
                                                <div class="progress-bar" style="width: <?= $progress ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="bi bi-clipboard-x fs-1 text-muted"></i>
                        <h6 class="mt-2 text-muted">No recent work orders</h6>
                        <p class="text-muted">This product hasn't been used in any work orders recently.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-xl-4">
        <!-- Product Image -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body text-center">
                <?php if (!empty($product['image_path'])): ?>
                    <img src="<?= base_url($product['image_path']) ?>" 
                         alt="Product Image" 
                         class="img-fluid rounded border" 
                         style="max-height: 300px;">
                <?php else: ?>
                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height: 200px;">
                        <i class="bi bi-image fs-1 text-muted"></i>
                    </div>
                    <p class="text-muted mt-3 mb-0">No image available</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Production Statistics
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-6">
                        <div class="border-end">
                            <h5 class="text-primary mb-1"><?= number_format($stats['active_work_orders'] ?? 0) ?></h5>
                            <small class="text-muted">Active Orders</small>
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
                            <h5 class="text-info mb-1"><?= number_format($stats['total_quantity_produced'] ?? 0) ?></h5>
                            <small class="text-muted">Total Produced</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <h5 class="text-warning mb-1">$<?= number_format($stats['total_value_produced'] ?? 0, 0) ?></h5>
                        <small class="text-muted">Value Produced</small>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between mb-2">
                    <small class="text-muted">Avg. Production Time:</small>
                    <small><?= isset($stats['avg_production_time']) ? $stats['avg_production_time'] . ' min' : 'N/A' ?></small>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">On-time Delivery:</small>
                    <small><?= isset($stats['on_time_delivery_rate']) ? number_format($stats['on_time_delivery_rate'], 1) . '%' : 'N/A' ?></small>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <?php if (!empty($related_products)): ?>
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-boxes me-2"></i>
                        Related Products
                    </h5>
                </div>
                <div class="card-body">
                    <?php foreach ($related_products as $related): ?>
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-light rounded-circle d-flex align-items-center justify-content-center me-3" 
                                 style="width: 40px; height: 40px;">
                                <i class="bi bi-box text-muted"></i>
                            </div>
                            <div class="flex-grow-1">
                                <a href="<?= base_url('/products/' . $related['id']) ?>" class="text-decoration-none">
                                    <h6 class="mb-0"><?= esc($related['name']) ?></h6>
                                </a>
                                <small class="text-muted"><?= esc($related['sku']) ?></small>
                            </div>
                            <small class="text-success">$<?= number_format($related['selling_price'], 2) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

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
                    <a href="<?= base_url('/work-orders/create?product_id=' . $product['id']) ?>" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>
                        Create Work Order
                    </a>
                    
                    <a href="<?= base_url('/inventory?product_id=' . $product['id']) ?>" class="btn btn-outline-info">
                        <i class="bi bi-box-seam me-2"></i>
                        Check Inventory
                    </a>
                    
                    <a href="<?= base_url('/quality-control?product_id=' . $product['id']) ?>" class="btn btn-outline-warning">
                        <i class="bi bi-shield-check me-2"></i>
                        Quality Records
                    </a>
                    
                    <a href="<?= base_url('/reports/product/' . $product['id']) ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-graph-up me-2"></i>
                        View Reports
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// Toggle product status
function toggleStatus() {
    if (confirm('Are you sure you want to change the status of this product?')) {
        fetch(`<?= base_url('/products/' . $productIdentifier . '/toggle-status') ?>`, {
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

// Delete product
function deleteProduct() {
    if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
        fetch(`<?= base_url('/products/' . $productIdentifier . '/delete') ?>`, {
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
                window.location.href = '<?= base_url('/products') ?>';
            } else {
                alert(data.message || 'Failed to delete product');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Export product details
function exportProductDetails() {
    window.location.href = '<?= base_url('/products/' . $productIdentifier . '/export') ?>';
}

// Print product details
function printProductDetails() {
    window.print();
}
</script>
<?= $this->endSection() ?>
