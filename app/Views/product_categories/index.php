<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-0"><i class="bi bi-tags me-2"></i>Product Categories</h2>
            <div class="small text-muted">Manage product category classifications</div>
        </div>
        <?php if ($can_create): ?>
            <a href="<?= base_url('/product-categories/create') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle me-2"></i>
                Add Category
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= base_url('/product-categories') ?>" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               value="<?= esc($search ?? '') ?>" 
                               placeholder="Search categories...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="1" <?= ($status === '1') ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= ($status === '0') ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex align-items-end">
                        <button type="submit" class="btn btn-outline-primary me-2">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                        <a href="<?= base_url('/product-categories') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-clockwise me-1"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Categories Table -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list me-2"></i>
                    Categories List (<?= count($categories) ?> total)
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($categories)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Suffix</th>
                                    <th>Parent</th>
                                    <th>Description</th>
                                    <th class="text-center">Products</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Created</th>
                                    <th class="actions-col text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $index => $category): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm bg-primary bg-gradient rounded-circle me-2 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-tag text-white small"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= esc($category['name']) ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php if (!empty($category['suffix'])): ?>
                                                <span class="badge bg-primary bg-opacity-10 text-primary fw-semibold"><?= esc($category['suffix']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                $full = $category['parent_chain'] ?? ($category['parent_name'] ?? null);
                                                if (empty($full)) {
                                                    echo '—';
                                                } else {
                                                    $parts = explode(' > ', $full);
                                                    if (count($parts) <= 3) {
                                                        echo esc($full);
                                                    } else {
                                                        $short = $parts[0] . ' > ... > ' . end($parts);
                                                        // show truncated chain with full chain in tooltip
                                                        echo '<span class="d-inline-block text-truncate" style="max-width:240px;" data-bs-toggle="tooltip" data-bs-placement="top" title="' . esc($full) . '">' . esc($short) . '</span>';
                                                    }
                                                }
                                                ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="text-muted">
                                                <?= esc($category['description'] ?: 'No description provided') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($category['product_count'] > 0): ?>
                                                <a href="<?= base_url('/products?category_id=' . $category['id']) ?>" 
                                                   class="badge bg-info text-decoration-none">
                                                    <?= $category['product_count'] ?> product<?= $category['product_count'] > 1 ? 's' : '' ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted">0 products</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-switch d-flex justify-content-center">
                                                <input class="form-check-input" 
                                                       type="checkbox" 
                                                       <?= $category['is_active'] ? 'checked' : '' ?>
                                                       onchange="toggleStatus(<?= $category['id'] ?>)"
                                                       title="Toggle status">
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($category['created_at'])) ?>
                                            </small>
                                        </td>
                                        <td class="text-center actions-col">
                                            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('/products?category_id=' . $category['id']) ?>" title="View products" aria-label="View products">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if ($can_edit): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('/product-categories/' . $category['id'] . '/edit') ?>" title="Edit" aria-label="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($can_delete && $category['product_count'] == 0): ?>
                                                <button class="btn btn-sm btn-outline-danger" type="button" onclick="deleteCategory(<?= $category['id'] ?>)" title="Delete" aria-label="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-tags display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No Categories Found</h5>
                        <p class="text-muted">Start by creating your first product category.</p>
                        <?php if ($can_create): ?>
                            <a href="<?= base_url('/product-categories/create') ?>" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>
                                Create First Category
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript for interactions -->
<script>
function toggleStatus(categoryId) {
    if (confirm('Are you sure you want to toggle this category status?')) {
        fetch(`<?= base_url('/product-categories/') ?>${categoryId}/toggle-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to update category status');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating category status');
        });
    }
}

function deleteCategory(categoryId) {
    if (confirm('Are you sure you want to delete this category? This action cannot be undone.')) {
        fetch(`<?= base_url('/product-categories/') ?>${categoryId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'Failed to delete category');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while deleting category');
        });
    }
}
</script>

<style>
.avatar {
    width: 32px;
    height: 32px;
}
    /* Fix: allow dropdowns inside table-responsive to be visible (avoid clipping) */
    .table-responsive {
        overflow: visible;
    }
    /* Ensure dropdowns appear above other elements */
    .dropdown-menu {
        z-index: 2050; /* higher than table/toolbars */
    }
    .actions-col .dropdown-menu {
        min-width: 150px;
    }
</style>

<?= $this->endSection() ?>
