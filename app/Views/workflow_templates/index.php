<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>
<?= $title ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><?= $title ?></h2>
                <a href="/workflow-templates/create" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create New Workflow Template
                </a>
            </div>

            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <?= session()->getFlashdata('success') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= session()->getFlashdata('error') ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="/workflow-templates">
                        <div class="row">
                            <div class="col-md-4">
                                <label for="category_filter" class="form-label">Filter by Category</label>
                                <select name="category_id" id="category_filter" class="form-select">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $category['id'] ?>" 
                                                <?= (request()->getGet('category_id') == $category['id']) ? 'selected' : '' ?>>
                                            <?= esc($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" name="search" id="search" class="form-control" 
                                       placeholder="Search workflows..." 
                                       value="<?= esc(request()->getGet('search')) ?>">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                                <a href="/workflow-templates" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Workflow Templates Table -->
            <div class="card">
                <div class="card-body">
                    <?php if (empty($workflows)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-project-diagram fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No workflow templates found</h5>
                            <p class="text-muted">Create your first workflow template to get started.</p>
                            <a href="/workflow-templates/create" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Workflow Template
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Category</th>
                                        <th>Description</th>
                                        <th>Steps</th>
                                        <th>Est. Time</th>
                                        <th>Products</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($workflows as $workflow): ?>
                                        <?php
                                        // Get workflow stats
                                        $workflowModel = new \App\Models\ProcessWorkflowTemplateModel();
                                        $stats = $workflowModel->getWorkflowStats($workflow['id']);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= esc($workflow['name']) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($workflow['category_name']): ?>
                                                    <span class="badge bg-info"><?= esc($workflow['category_name']) ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">No Category</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($workflow['description']): ?>
                                                    <?= esc(substr($workflow['description'], 0, 100)) ?>
                                                    <?= strlen($workflow['description']) > 100 ? '...' : '' ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No description</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary"><?= $stats['step_count'] ?> steps</span>
                                            </td>
                                            <td>
                                                <?php if ($workflow['estimated_total_time_minutes']): ?>
                                                    <?= $workflow['estimated_total_time_minutes'] ?> min
                                                    <?php if ($workflow['estimated_total_time_minutes'] >= 60): ?>
                                                        <br><small class="text-muted">
                                                            (<?= floor($workflow['estimated_total_time_minutes'] / 60) ?>h 
                                                            <?= $workflow['estimated_total_time_minutes'] % 60 ?>m)
                                                        </small>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($stats['assigned_products'] > 0): ?>
                                                    <span class="badge bg-success"><?= $stats['assigned_products'] ?> products</span>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($workflow['is_active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="/workflow-templates/<?= $workflow['id'] ?>" 
                                                       class="btn btn-outline-primary" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="/workflow-templates/<?= $workflow['id'] ?>/edit" 
                                                       class="btn btn-outline-secondary" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            title="Delete" onclick="confirmDelete(<?= $workflow['id'] ?>, '<?= esc($workflow['name']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the workflow template "<span id="workflowName"></span>"?</p>
                <p class="text-danger"><strong>Warning:</strong> This action cannot be undone. All steps in this workflow will also be deleted.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" class="btn btn-danger">Delete Workflow</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(workflowId, workflowName) {
    document.getElementById('workflowName').textContent = workflowName;
    document.getElementById('deleteForm').action = '/workflow-templates/' + workflowId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-submit form when category filter changes
document.getElementById('category_filter').addEventListener('change', function() {
    this.form.submit();
});
</script>
<?= $this->endSection() ?>
