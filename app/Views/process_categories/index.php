<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">
                    <i class="bi bi-collection me-2"></i>
                    Process Categories
                </h2>
                <p class="text-muted mb-0">Manage process category classifications</p>
            </div>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/process-categories/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    Add Category
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Search and Filter Bar -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <form method="GET" action="<?= base_url('/process-categories') ?>" class="row g-3">
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
                        <a href="<?= base_url('/process-categories') ?>" class="btn btn-outline-secondary">
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
                                    <th width="5%">#</th>
                                    <th width="25%">Name</th>
                                    <th width="35%">Description</th>
                                    <th width="10%" class="text-center">Processes</th>
                                    <th width="10%" class="text-center">Templates</th>
                                    <th width="5%" class="text-center">Status</th>
                                    <th width="10%" class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $index => $category): ?>
                                    <tr>
                                        <td><?= $index + 1 ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar avatar-sm bg-info bg-gradient rounded-circle me-2 d-flex align-items-center justify-content-center">
                                                    <i class="bi bi-gear text-white small"></i>
                                                </div>
                                                <div>
                                                    <h6 class="mb-0"><?= esc($category['name']) ?></h6>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="text-muted">
                                                <?= esc($category['description'] ?: 'No description provided') ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($category['process_count'] > 0): ?>
                                                <a href="<?= base_url('/processes?category_id=' . $category['id']) ?>" 
                                                   class="badge bg-primary text-decoration-none">
                                                    <?= $category['process_count'] ?> process<?= $category['process_count'] > 1 ? 'es' : '' ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted">0 processes</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($category['template_count'] > 0): ?>
                                                <a href="<?= base_url('/process-templates?category_id=' . $category['id']) ?>" 
                                                   class="badge bg-success text-decoration-none">
                                                    <?= $category['template_count'] ?> template<?= $category['template_count'] > 1 ? 's' : '' ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted">0 templates</span>
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
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" 
                                                        data-bs-toggle="dropdown">
                                                    <i class="bi bi-three-dots"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if ($can_edit): ?>
                                                        <li><a class="dropdown-item" href="<?= base_url('/process-categories/' . $category['id'] . '/edit') ?>">
                                                            <i class="bi bi-pencil me-2"></i> Edit
                                                        </a></li>
                                                    <?php endif; ?>
                                                    <li><a class="dropdown-item" href="#" onclick="toggleStatus(<?= $category['id'] ?>)">
                                                        <i class="bi bi-arrow-repeat me-2"></i> 
                                                        <?= $category['is_active'] ? 'Deactivate' : 'Activate' ?>
                                                    </a></li>
                                                    <?php if ($can_delete && $category['process_count'] == 0 && $category['template_count'] == 0): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="#" onclick="deleteCategory(<?= $category['id'] ?>)">
                                                            <i class="bi bi-trash me-2"></i> Delete
                                                        </a></li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-collection display-1 text-muted"></i>
                        <h5 class="mt-3 text-muted">No Process Categories Found</h5>
                        <p class="text-muted">Start by creating your first process category.</p>
                        <?php if ($can_create): ?>
                            <a href="<?= base_url('/process-categories/create') ?>" class="btn btn-primary">
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
        fetch(`<?= base_url('/process-categories/') ?>${categoryId}/toggle-status`, {
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
        fetch(`<?= base_url('/process-categories/') ?>${categoryId}`, {
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
</style>

<?= $this->endSection() ?>
