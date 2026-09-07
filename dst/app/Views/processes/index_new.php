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
                <?= form_open(base_url('/processes'), ['method' => 'GET', 'class' => 'row align-items-end']) ?>
                    <div class="col-md-3 mb-3">
                        <label for="search" class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               placeholder="Process name, operation..."
                               value="<?= esc($current_search) ?>">
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="product_id" class="form-label">Product</label>
                        <select class="form-select" id="product_id" name="product_id">
                            <option value="">All Products</option>
                            <?php foreach ($products as $product): ?>
                                <option value="<?= $product['id'] ?>" 
                                        <?= $current_product == $product['id'] ? 'selected' : '' ?>>
                                    <?= esc($product['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="vendor_id" class="form-label">Vendor</label>
                        <select class="form-select" id="vendor_id" name="vendor_id">
                            <option value="">All Vendors</option>
                            <?php foreach ($vendors as $vendor): ?>
                                <option value="<?= $vendor['id'] ?>" 
                                        <?= $current_vendor == $vendor['id'] ? 'selected' : '' ?>>
                                    <?= esc($vendor['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="1" <?= $current_status === '1' ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= $current_status === '0' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    
                    <div class="col-md-2 mb-3">
                        <label for="per_page" class="form-label">Per Page</label>
                        <select class="form-select" id="per_page" name="per_page">
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

<!-- Results -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <?php if (!empty($processes)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="40">
                                        <input type="checkbox" class="form-check-input" id="selectAll">
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
                                                    <br><small class="text-muted"><?= esc(substr($process['description'], 0, 50)) ?><?= strlen($process['description']) > 50 ? '...' : '' ?></small>
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
                                            <div class="btn-group" role="group">
                                                <a href="<?= base_url('/processes/' . $process['id']) ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="View">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <?php if ($can_edit): ?>
                                                    <a href="<?= base_url('/processes/' . $process['id'] . '/edit') ?>" 
                                                       class="btn btn-sm btn-outline-secondary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if ($can_delete): ?>
                                                    <button type="button" 
                                                            class="btn btn-sm btn-outline-danger" 
                                                            onclick="confirmDelete(<?= $process['id'] ?>)" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-diagram-3 text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No Processes Found</h5>
                        <p class="text-muted">Try adjusting your search criteria or create a new process.</p>
                        <?php if ($can_create): ?>
                            <a href="<?= base_url('/processes/create') ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>
                                Create First Process
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
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

<script>
// Select all functionality
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.process-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = this.checked;
    });
});

// Delete confirmation
function confirmDelete(processId) {
    if (confirm('Are you sure you want to delete this process? This action cannot be undone.')) {
        // Create form and submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?= base_url('/processes') ?>/' + processId;
        
        const methodInput = document.createElement('input');
        methodInput.type = 'hidden';
        methodInput.name = '_method';
        methodInput.value = 'DELETE';
        
        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = '<?= csrf_token() ?>';
        csrfInput.value = '<?= csrf_hash() ?>';
        
        form.appendChild(methodInput);
        form.appendChild(csrfInput);
        document.body.appendChild(form);
        form.submit();
    }
}
</script>

<?= $this->endSection() ?>
