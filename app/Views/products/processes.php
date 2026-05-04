<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php $productIdentifier = entityRouteIdentifier($product); ?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('/products') ?>">Products</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('/products/' . $productIdentifier) ?>"><?= esc($product['name']) ?></a></li>
                    <li class="breadcrumb-item active">Processes</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
                <script>
                // Cleanup stray text nodes like '200;' that might be accidentally echoed
                (function(){
                    const stray = document.body && document.body.firstChild;
                    if (stray && stray.nodeType === Node.TEXT_NODE && /\d+;/.test(stray.textContent.trim())) {
                        stray.parentNode.removeChild(stray);
                    }
                })();
                </script>
        <!-- Product Info Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-box me-2"></i>
                            <?= esc($product['name']) ?> (<?= esc($product['code']) ?>)
                            <small class="ms-3 text-white-50" style="font-weight:400; font-size:0.95rem;">- processes attached</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <p class="mb-1"><strong>Description:</strong> <?= esc($product['description']) ?: 'No description available' ?></p>
                                <p class="mb-0"><strong>Unit:</strong> <?= esc($product['unit']) ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

                <?php if (!empty($processError)): ?>
                    <div class="row mb-3">
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <strong>Note:</strong> <?= esc($processError) ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

        <div class="row">
            <!-- Current Processes -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            Manufacturing Processes
                        </h5>
                        <div>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProcessModal">
                                <i class="bi bi-plus-circle me-1"></i> Add Process
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#bulkActionsModal">
                                <i class="bi bi-gear me-1"></i> Bulk Actions
                            </button>
                        </div>
                    </div>
                    
                    <div class="card-body">
                        <?php if (!empty($processes)): ?>
                            <div id="process-list" class="sortable-list">
                                <?php foreach ($processes as $index => $process): ?>
                                    <div class="process-item border rounded p-3 mb-3" data-process-id="<?= $process['product_process_id'] ?? ($process['id'] ?? '') ?>">
                                        <div class="row align-items-center">
                                            <div class="col-md-1 text-center">
                                                <div class="drag-handle text-muted" style="cursor: move;">
                                                    <i class="bi bi-grip-vertical"></i>
                                                </div>
                                                <span class="badge bg-primary sequence-number"><?= $process['sequence_order'] ?></span>
                                            </div>
                                            
                                            <div class="col-md-7">
                                                <div class="d-flex align-items-center">
                                                    <?php // compact: removed per-item icon to reduce visual noise ?>
                                                    <div>
                                                        <div class="fw-medium">
                                                            <?= esc($process['process_name'] ?? $process['name'] ?? 'Unnamed Process') ?>
                                                            <?php if (empty($process['process_name'])): ?>
                                                                <small class="text-danger">(Process ID: <?= $process['process_id'] ?? 'NULL' ?>)</small>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php // tag: In-house or Outsource ?>
                                                        <?php if (!empty($process['is_vendor_process'])): ?>
                                                            <span class="badge bg-warning text-dark ms-2">
                                                                <i class="bi bi-building me-1"></i>
                                                                <?= esc($process['vendor_name'] ?? 'Outsource') ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success ms-2">
                                                                <i class="bi bi-house-door-fill me-1"></i>In-house
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <div class="btn-group btn-group-sm">
                                                    <button class="btn btn-outline-secondary" 
                                                            onclick="editProcess(<?= $process['product_process_id'] ?? ($process['id'] ?? 0) ?>, '<?= esc($process['process_name'] ?? '') ?>', <?= ($process['custom_time_minutes'] ?? 0) ?: ($process['standard_time_minutes'] ?? 0) ?>, '<?= esc($process['custom_notes'] ?? '') ?>', 'workflow')"
                                                            title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-outline-danger" 
                                                            onclick="removeProcess(<?= $process['product_process_id'] ?? ($process['id'] ?? 0) ?>, '<?= esc($process['process_name'] ?? '') ?>')"
                                                            title="Remove">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($process['custom_notes'])): ?>
                                            <div class="row mt-2">
                                                <div class="col-md-11 offset-md-1">
                                                    <small class="text-muted">
                                                        <i class="bi bi-note-text me-1"></i>
                                                        <?= esc($process['custom_notes']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="bi bi-gear-wide-connected display-4 text-muted"></i>
                                <p class="mt-3 mb-0 text-muted">No processes assigned to this product</p>
                                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addProcessModal">
                                    <i class="bi bi-plus-circle me-2"></i>Add First Process
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Process Templates Panel -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-collection me-2"></i>
                            Available Processes
                        </h6>
                    </div>
                    <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                        <?php 
                        $currentCategory = '';
                        // Build a set of attached process IDs to disable in UI
                        $attachedProcessIds = [];
                        if (!empty($processes)) {
                            foreach ($processes as $p) {
                                if (!empty($p['process_id'])) { $attachedProcessIds[] = (int)$p['process_id']; }
                            }
                        }
                        $attachedLookup = array_flip($attachedProcessIds);
                        if (!empty($all_processes)):
                        foreach ($all_processes as $process): 
                            $processCategory = $process['category_name'] ?? 'General';
                            if ($processCategory !== $currentCategory):
                                $currentCategory = $processCategory;
                        ?>
                                <div class="mb-3">
                                    <h6 class="text-muted border-bottom pb-1"><?= esc(ucfirst($currentCategory)) ?></h6>
                                </div>
                        <?php endif; ?>
                            <?php $isAttached = isset($attachedLookup[(int)$process['id']]); ?>
                       <div class="template-item border rounded p-2 mb-2 cursor-pointer <?= $isAttached ? 'opacity-50' : '' ?>" 
                           <?= $isAttached ? 'title="Already attached"' : 'onclick="quickAddProcess(' . (int)$process['id'] . ', \' ' . esc($process['name'], 'js') . ' \' )"' ?> >
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="fw-medium small"><?= esc($process['name']) ?></div>
                                        <?php if (!empty($process['is_vendor_process'])): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-building me-1"></i>
                                                <?= esc($process['vendor_name'] ?? 'Outsource') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-house-door-fill me-1"></i>
                                                In-house
                                            </span>
                                        <?php endif; ?>
                                        <div><small class="text-muted"><?= esc($process['category_name'] ?? '') ?></small></div>
                                    </div>
                                    <?php if ($isAttached): ?>
                                        <span class="badge bg-secondary">Attached</span>
                                    <?php else: ?>
                                        <button class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-plus"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="bi bi-gear-wide-connected display-6 d-block mb-2"></i>
                                <div>No processes to show.</div>
                                <div class="mt-2">
                                    <a href="<?= base_url('processes/create') ?>" class="btn btn-sm btn-primary">
                                        <i class="bi bi-plus-circle me-1"></i>Create a process
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Process Modal -->
<div class="modal fade" id="addProcessModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Processes to Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addProcessForm">
                    <div class="mb-3">
                        <label class="form-label">Filter by Category</label>
                        <select class="form-select" id="categoryFilter" onchange="filterTemplates()">
                            <option value="">All Categories</option>
                            <?php if (is_array($categories)): ?>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= esc($category['id']) ?>"><?= esc($category['name']) ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Search Processes</label>
                        <input type="text" class="form-control" id="processSearch" placeholder="Search by process name..." onkeyup="filterProcesses()">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Select Processes</label>
                        <div id="processesList" style="max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; padding: 10px;">
                            <?php if (!empty($all_processes)): ?>
                            <?php foreach ($all_processes as $process): ?>
                                <?php $isAttached = isset($attachedLookup[(int)$process['id']]); ?>
                                <div class="process-option form-check <?= $isAttached ? 'opacity-50' : '' ?>" data-category="<?= esc($process['category_id'] ?? '') ?>" data-name="<?= esc(strtolower($process['name'])) ?>">
                                    <input class="form-check-input" type="checkbox" value="<?= $process['id'] ?>" id="process_<?= $process['id'] ?>" <?= $isAttached ? 'disabled' : '' ?>>
                                    <label class="form-check-label w-100" for="process_<?= $process['id'] ?>">
                                        <div class="d-flex justify-content-between">
                                            <span>
                                                <?= esc($process['name']) ?>
                                                <?php if ($isAttached): ?><span class="badge bg-secondary ms-2">Attached</span><?php endif; ?>
                                            </span>
                                            <small class="text-muted"><?= $process['standard_time_minutes'] ?> min | <?= esc($process['category_name'] ?? 'No Category') ?></small>
                                        </div>
                                        <?php if (!empty($process['description'])): ?>
                                            <small class="text-muted d-block"><?= esc(substr($process['description'], 0, 60)) ?><?= strlen($process['description']) > 60 ? '...' : '' ?></small>
                                        <?php endif; ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-gear display-6 d-block mb-2"></i>
                                    <div>No processes available yet.</div>
                                    <div class="mt-2">
                                        <a href="<?= base_url('processes/create') ?>" class="btn btn-sm btn-primary">
                                            <i class="bi bi-plus-circle me-1"></i>Create your first process
                                        </a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="addSelectedProcesses()">Add Selected Processes</button>
            </div>
        </div>
    </div>
</div>

<!-- Edit Process Modal -->
<div class="modal fade" id="editProcessModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Process</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="editProcessForm">
                    <input type="hidden" id="editProcessId">
                    <div class="mb-3">
                        <label class="form-label">Process Name</label>
                        <input type="text" class="form-control" id="editProcessName" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Process Category</label>
                        <input type="text" class="form-control" id="editProcessCategory" readonly>
                        <small class="text-muted">Category is inherited from the process template</small>
                    </div>
                    <div class="mb-3">
                        <label for="editCustomTime" class="form-label">Custom Time (minutes)</label>
                        <input type="number" class="form-control" id="editCustomTime" name="custom_time_minutes" min="0">
                        <small class="text-muted">Leave empty to use template default time</small>
                    </div>
                    <div class="mb-3">
                        <label for="editCustomNotes" class="form-label">Custom Notes</label>
                        <textarea class="form-control" id="editCustomNotes" name="custom_notes" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="updateProcess()">Update Process</button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Actions Modal -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Actions</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-primary" onclick="showCopyProcessesModal()">
                        <i class="bi bi-copy me-2"></i>Copy Processes from Another Product
                    </button>
                    <button class="btn btn-outline-warning" onclick="clearAllProcesses()">
                        <i class="bi bi-trash me-2"></i>Clear All Processes
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.process-item {
    transition: all 0.18s ease;
    padding: 8px 10px;
    font-size: 0.95rem;
}

.process-item:hover {
    box-shadow: 0 1px 6px rgba(0,0,0,0.06);
}

.template-item:hover {
    background-color: #f8f9fa;
    border-color: #007bff !important;
}

.sortable-list .process-item {
    cursor: move;
}

.process-item .sequence-number {
    padding: 6px 8px;
    font-size: 0.85rem;
}

.process-item .btn-group .btn {
    padding: 4px 7px;
}

.cursor-pointer {
    cursor: pointer;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>

<script>
let productId = <?= $product['id'] ?>;

// Initialize sortable
document.addEventListener('DOMContentLoaded', function() {
    const processList = document.getElementById('process-list');
    if (processList) {
        new Sortable(processList, {
            animation: 150,
            handle: '.drag-handle',
            onEnd: function(evt) {
                updateProcessOrder();
            }
        });
    }
});

function filterTemplates() {
    const selectedCategory = document.getElementById('categoryFilter').value;
    const searchTerm = document.getElementById('processSearch') ? document.getElementById('processSearch').value.toLowerCase() : '';
    const processOptions = document.querySelectorAll('.process-option');
    
    processOptions.forEach(option => {
        const category = option.dataset.category;
        const processName = option.dataset.name;
        
        const categoryMatch = !selectedCategory || category === selectedCategory || selectedCategory === '';
        const searchMatch = !searchTerm || processName.includes(searchTerm);
        
        if (categoryMatch && searchMatch) {
            option.style.display = 'block';
        } else {
            option.style.display = 'none';
        }
    });
}

function filterProcesses() {
    filterTemplates(); // Reuse the same logic
}

// Show all processes by default when modal opens
document.getElementById('addProcessModal').addEventListener('shown.bs.modal', function() {
    // Reset filter and show all processes
    document.getElementById('categoryFilter').value = '';
    if (document.getElementById('processSearch')) {
        document.getElementById('processSearch').value = '';
    }
    filterTemplates();
});

function addSelectedProcesses() {
    const checkboxes = document.querySelectorAll('#processesList input[type="checkbox"]:checked');
    const processIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (processIds.length === 0) {
        showAlert('warning', 'Please select at least one process.');
        return;
    }
    
    // Prepare form data with CSRF token
    const formData = new URLSearchParams();
    formData.append('process_ids', processIds.join(','));
    formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
    
    fetch(`<?= base_url('products') ?>/${productId}/processes/add`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: formData.toString()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('addProcessModal'));
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
        showAlert('danger', 'An error occurred while adding processes.');
    });
}

function quickAddProcess(processId, processName) {
    if (confirm(`Add "${processName}" to this product?`)) {
        // Prepare form data with CSRF token
        const formData = new URLSearchParams();
        formData.append('process_ids', processId);
        formData.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
        
        fetch(`<?= base_url('products') ?>/${productId}/processes/add`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString()
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
            showAlert('danger', 'An error occurred while adding the process.');
        });
    }
}

function editProcess(processId, processName, customTime, customNotes, category) {
    document.getElementById('editProcessId').value = processId;
    document.getElementById('editProcessName').value = processName;
    document.getElementById('editCustomTime').value = customTime || '';
    document.getElementById('editCustomNotes').value = customNotes || '';
    document.getElementById('editProcessCategory').value = category ? category.charAt(0).toUpperCase() + category.slice(1) : 'No Category';
    
    const modal = new bootstrap.Modal(document.getElementById('editProcessModal'));
    modal.show();
}

function updateProcess() {
    const processId = document.getElementById('editProcessId').value;
    const customTime = document.getElementById('editCustomTime').value;
    const customNotes = document.getElementById('editCustomNotes').value;
    
    fetch(`<?= base_url('products') ?>/${productId}/processes/${processId}/update`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `custom_time_minutes=${customTime}&custom_notes=${encodeURIComponent(customNotes)}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            const modal = bootstrap.Modal.getInstance(document.getElementById('editProcessModal'));
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
        showAlert('danger', 'An error occurred while updating the process.');
    });
}

function removeProcess(processId, processName) {
    if (confirm(`Are you sure you want to remove "${processName}" from this product?`)) {
        fetch(`<?= base_url('products') ?>/${productId}/processes/${processId}`, {
            method: 'DELETE',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
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
            showAlert('danger', 'An error occurred while removing the process.');
        });
    }
}

function updateProcessOrder() {
    const processItems = document.querySelectorAll('.process-item');
    const processIds = Array.from(processItems).map(item => item.dataset.processId);
    
    fetch(`<?= base_url('products') ?>/${productId}/processes/reorder`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: `process_ids=${processIds.join(',')}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update sequence numbers
            processItems.forEach((item, index) => {
                const sequenceSpan = item.querySelector('.sequence-number');
                sequenceSpan.textContent = index + 1;
            });
            showAlert('success', 'Process order updated successfully.');
        } else {
            showAlert('danger', data.message);
            location.reload(); // Reload on error to reset order
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while updating process order.');
        location.reload();
    });
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
