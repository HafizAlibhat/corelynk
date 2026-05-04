<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-gear-wide-connected me-2"></i>
                Process Templates
            </h2>
            <?php if ($can_create): ?>
                <a href="<?= base_url('/process-templates/create') ?>" class="btn btn-primary">
                    <i class="bi bi-plus-circle me-2"></i>
                    New Process Template
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
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" 
                               class="form-control" 
                               name="search" 
                               value="<?= esc($current_search) ?>" 
                               placeholder="Process name, description...">
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Category</label>
                        <select class="form-select" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= esc($category) ?>" <?= $current_category == $category ? 'selected' : '' ?>>
                                    <?= esc(ucfirst($category)) ?>
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
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="bi bi-search"></i>
                            </button>
                            <a href="<?= base_url('/process-templates') ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i>
                            </a>
                        </div>
                    </div>
                <?= form_close() ?>
            </div>
        </div>
    </div>
</div>

<!-- Process Templates Table -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Process Templates List</h5>
                    <div class="d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary" onclick="exportTemplates()">
                            <i class="bi bi-download me-1"></i> Export
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <?php if (!empty($process_templates)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Time (min)</th>
                                    <th>Type</th>
                                    <th>Vendor</th>
                                    <th>Status</th>
                                    <th>Usage</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($process_templates as $template): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $categoryIcons = [
                                                    'machining' => 'bi-gear-wide-connected text-primary',
                                                    'assembly' => 'bi-puzzle text-success',
                                                    'finishing' => 'bi-brush text-warning',
                                                    'quality' => 'bi-shield-check text-info',
                                                    'packaging' => 'bi-box text-secondary',
                                                    'testing' => 'bi-speedometer2 text-danger',
                                                    'general' => 'bi-gear text-muted'
                                                ];
                                                $iconClass = $categoryIcons[$template['category']] ?? $categoryIcons['general'];
                                                ?>
                                                <i class="bi <?= $iconClass ?> me-2"></i>
                                                <div>
                                                    <div class="fw-medium"><?= esc($template['name']) ?></div>
                                                    <?php if (!empty($template['description'])): ?>
                                                        <small class="text-muted"><?= esc(substr($template['description'], 0, 60)) ?><?= strlen($template['description']) > 60 ? '...' : '' ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= esc(ucfirst($template['category'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fw-medium"><?= $template['standard_time_minutes'] ?></span> min
                                        </td>
                                        <td>
                                            <?php if ($template['is_vendor_process']): ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-building me-1"></i>Vendor
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-house me-1"></i>In-House
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($template['vendor_name']): ?>
                                                <small><?= esc($template['vendor_name']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info usage-count" data-template-id="<?= $template['id'] ?>">
                                                <i class="bi bi-boxes me-1"></i>
                                                <span class="usage-number">-</span>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="<?= base_url('/process-templates/' . $template['id']) ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php if ($can_edit): ?>
                                                    <a href="<?= base_url('/process-templates/' . $template['id'] . '/edit') ?>" 
                                                       class="btn btn-outline-secondary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <button class="btn btn-outline-info" 
                                                            onclick="duplicateTemplate(<?= $template['id'] ?>, '<?= esc($template['name']) ?>')" 
                                                            title="Duplicate">
                                                        <i class="bi bi-copy"></i>
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($can_delete): ?>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="deleteTemplate(<?= $template['id'] ?>, '<?= esc($template['name']) ?>')" 
                                                            title="Delete">
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
                    
                    <!-- Pagination -->
                    <?php if (isset($pager) && $pager): ?>
                        <div class="card-footer bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <small class="text-muted">
                                        Process Templates List
                                    </small>
                                </div>
                                <div>
                                    <?= $pager->links() ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="bi bi-gear-wide-connected display-4 text-muted"></i>
                        <p class="mt-3 mb-0 text-muted">No process templates found</p>
                        <?php if ($can_create): ?>
                            <a href="<?= base_url('/process-templates/create') ?>" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-circle me-2"></i>Create First Template
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Duplicate Template Modal -->
<div class="modal fade" id="duplicateTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Duplicate Process Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="duplicateTemplateForm">
                    <div class="mb-3">
                        <label class="form-label">Original Template</label>
                        <input type="text" class="form-control" id="originalTemplateName" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="newTemplateName" class="form-label">New Template Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newTemplateName" name="new_name" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmDuplicate()">Duplicate Template</button>
            </div>
        </div>
    </div>
</div>

<script>
let templateToDelete = null;
let templateToDuplicate = null;

// Load usage counts
document.addEventListener('DOMContentLoaded', function() {
    loadUsageCounts();
});

function loadUsageCounts() {
    document.querySelectorAll('.usage-count').forEach(element => {
        const templateId = element.dataset.templateId;
        const usageNumberSpan = element.querySelector('.usage-number');
        
        // You could make an AJAX call here to get the actual usage count
        // For now, setting a placeholder
        usageNumberSpan.textContent = '0';
    });
}

function deleteTemplate(id, name) {
    if (confirm(`Are you sure you want to delete the process template "${name}"?`)) {
        fetch(`/process-templates/${id}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showAlert('success', data.message);
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                showAlert('danger', data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showAlert('danger', 'An error occurred while deleting the template.');
        });
    }
}

function duplicateTemplate(id, name) {
    templateToDuplicate = id;
    document.getElementById('originalTemplateName').value = name;
    document.getElementById('newTemplateName').value = name + ' (Copy)';
    
    const modal = new bootstrap.Modal(document.getElementById('duplicateTemplateModal'));
    modal.show();
}

function confirmDuplicate() {
    const newName = document.getElementById('newTemplateName').value.trim();
    
    if (!newName) {
        showAlert('warning', 'Please enter a name for the new template.');
        return;
    }
    
    fetch(`/process-templates/${templateToDuplicate}/duplicate`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `new_name=${encodeURIComponent(newName)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('duplicateTemplateModal'));
            modal.hide();
            setTimeout(() => {
                location.reload();
            }, 1500);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while duplicating the template.');
    });
}

function exportTemplates() {
    showAlert('info', 'Export functionality will be implemented soon.');
}

function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '9999';
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(alertDiv);
    
    setTimeout(() => {
        alertDiv.remove();
    }, 5000);
}
</script>

<?= $this->endSection() ?>
