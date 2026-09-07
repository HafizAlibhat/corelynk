<?php $this->extend('layouts/main') ?>

<?php $this->section('title') ?>
Work Order #<?= $work_order['wo_number'] ?>
<?php $this->endSection() ?>

<?php $this->section('content') ?>

<!-- Work Order Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">
                    <i class="bi bi-clipboard-check me-2 text-primary"></i>
                    Work Order #<?= esc($work_order['wo_number']) ?>
                </h1>
                <p class="text-muted mb-0">
                    Customer: <?= esc($work_order['customer_name']) ?> | 
                    Status: <span class="badge bg-<?= $work_order['status'] == 'completed' ? 'success' : ($work_order['status'] == 'in_progress' ? 'warning' : 'primary') ?>"><?= ucfirst(str_replace('_', ' ', $work_order['status'])) ?></span>
                </p>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('/work-orders') ?>">Work Orders</a></li>
                    <li class="breadcrumb-item active"><?= esc($work_order['wo_number']) ?></li>
                </ol>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="row mb-3">
            <div class="col-12">
                <div class="btn-group" role="group">
                    <a href="<?= base_url('/work-orders') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to List
                    </a>
                    
                    <?php if ($can_edit && in_array($work_order['status'], ['planned', 'in_progress', 'on_hold'])): ?>
                        <a href="<?= base_url('/work-orders/' . $work_order['id'] . '/edit') ?>" class="btn btn-outline-warning">
                            <i class="bi bi-pencil me-1"></i> Edit
                        </a>
                    <?php endif; ?>
                    
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="bi bi-three-dots me-1"></i> Actions
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= base_url('/pdfs/work-order/' . $work_order['id']) ?>" target="_blank">
                                <i class="bi bi-file-earmark-pdf me-2"></i> Print PDF
                            </a></li>
                            
                            <?php if ($work_order['status'] == 'planned'): ?>
                                <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= $work_order['id'] ?>, 'in_progress')">
                                    <i class="bi bi-play me-2"></i> Start Production
                                </a></li>
                            <?php endif; ?>
                            
                            <?php if (in_array($work_order['status'], ['planned', 'in_progress'])): ?>
                                <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= $work_order['id'] ?>, 'on_hold')">
                                    <i class="bi bi-pause me-2"></i> Put On Hold
                                </a></li>
                            <?php endif; ?>
                            
                            <?php if ($work_order['status'] == 'on_hold'): ?>
                                <li><a class="dropdown-item" href="#" onclick="changeStatus(<?= $work_order['id'] ?>, 'in_progress')">
                                    <i class="bi bi-play me-2"></i> Resume
                                </a></li>
                            <?php endif; ?>
                            
                            <?php if ($can_delete && in_array($work_order['status'], ['planned', 'on_hold'])): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" onclick="deleteWorkOrder(<?= $work_order['id'] ?>)">
                                    <i class="bi bi-trash me-2"></i> Delete Work Order
                                </a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Work Order Details -->
<div class="content">
    <div class="container-fluid">
        <!-- Work Order Info Card -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="bi bi-info-circle me-2"></i>Work Order Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold">Work Order:</td>
                                        <td><?= esc($work_order['wo_number']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Customer:</td>
                                        <td><?= esc($work_order['customer_name']) ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Due Date:</td>
                                        <td>
                                            <?= $work_order['due_date'] ? date('M j, Y', strtotime($work_order['due_date'])) : 'Not set' ?>
                                            <?php if ($work_order['due_date'] && strtotime($work_order['due_date']) < time() && $work_order['status'] != 'completed'): ?>
                                                <span class="badge bg-danger ms-2">Overdue</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold">Status:</td>
                                        <td>
                                            <span class="badge bg-<?= $work_order['status'] == 'completed' ? 'success' : ($work_order['status'] == 'in_progress' ? 'warning' : 'primary') ?>">
                                                <?= ucfirst(str_replace('_', ' ', $work_order['status'])) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Priority:</td>
                                        <td>
                                            <span class="badge bg-<?= $work_order['priority'] == 'urgent' ? 'danger' : ($work_order['priority'] == 'high' ? 'warning' : 'info') ?>">
                                                <?= ucfirst($work_order['priority']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Created:</td>
                                        <td><?= date('M j, Y', strtotime($work_order['created_at'])) ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        <?php if (!empty($work_order['notes'])): ?>
                            <div class="row">
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <strong>Notes:</strong> <?= nl2br(esc($work_order['notes'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="bi bi-box-seam me-2"></i>Products
                        </h3>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($work_order['items'])): ?>
                            <?php foreach ($work_order['items'] as $item): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                    <div>
                                        <div class="fw-medium"><?= esc($item['product_name']) ?></div>
                                        <small class="text-muted"><?= esc($item['product_code']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?= number_format($item['quantity_ordered']) ?></div>
                                        <small class="text-muted"><?= esc($item['unit']) ?></small>
                                    </div>
                                    <div class="ms-2">
                                        <button class="btn btn-sm btn-outline-primary view-processes" 
                                                data-item-id="<?= $item['id'] ?>" 
                                                data-product-id="<?= $item['product_id'] ?>"
                                                onclick="toggleProcesses(<?= $item['id'] ?>, <?= $item['product_id'] ?>, this)">
                                            <i class="bi bi-plus-circle me-1"></i> Show Processes & Batches
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning">No products assigned to this work order.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Global container for processes and batches (full-width) -->
        <div id="global-processes-container" style="display: none; width: 100%;"></div>
    </div>
</div>

<style>
    /* Make batches section wider */
    .processes-container {
        max-width: none !important;
        width: 100%;
    }
    
    .processes-batches-content {
        max-width: none !important;
    }
</style>
<script>
function toggleProcesses(itemId, productId, toggleElement) {
    // use a global container so processes and batches render full width
    var container = document.getElementById('global-processes-container');
    var icon = toggleElement.querySelector('i');
    
    if (container.style.display === 'block') {
        // Collapse
        container.style.display = 'none';
        icon.className = 'bi bi-plus-circle me-1';
        toggleElement.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Show Processes & Batches';
    } else {
        // Expand and load data into the global container
    container.style.display = 'block';
        icon.className = 'bi bi-dash-circle me-1';
        toggleElement.innerHTML = '<i class="bi bi-dash-circle me-1"></i> Hide Processes & Batches';
        
        // Show loading message
        container.innerHTML = '<div class="card"><div class="card-body"><div class="alert alert-info mb-0"><i class="bi bi-hourglass-split"></i> Loading processes and batches...</div></div></div>';
        
    // remember the caller on the container so the global hide can restore it
    container._caller = toggleElement;
    // Load the data
    loadProcessesAndBatches(itemId, productId, container);
    }
}

function loadProcessesAndBatches(itemId, productId, container) {
    var workOrderId = <?= (int)$work_order['id'] ?>;
    var url = '<?= site_url() ?>/work-orders/' + workOrderId + '/items/' + itemId + '/processes?product_id=' + productId;
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var data = JSON.parse(xhr.responseText);
                    
                    if (data.success) {
                        renderProcessesAndBatches(data, container, itemId, productId);
                    } else {
                        container.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> ' + (data.message || 'No data available') + '</div>';
                    }
                } catch (e) {
                    container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-exclamation-circle"></i> Error: ' + e.message + '</div>';
                }
            } else {
                container.innerHTML = '<div class="alert alert-danger"><i class="bi bi-wifi-off"></i> Server error (HTTP ' + xhr.status + ')</div>';
            }
        }
    };
    
    xhr.send();
}

function renderProcessesAndBatches(data, container, itemId, productId) {
    var html = '<div class="processes-batches-content">';
    html += '<div class="d-flex justify-content-end mb-2">';
    html += '<button class="btn btn-sm btn-secondary me-2" onclick="hideGlobalProcesses()"><i class="bi bi-dash-circle me-1"></i> Hide</button>';
    html += '</div>';
    
    if (data.processes && data.processes.length > 0) {
        html += '<h6 class="mb-3"><i class="bi bi-gear-fill text-primary"></i> Manufacturing Processes</h6>';
        
        data.processes.forEach(function(process, index) {
            var processName = process.name || process.process_name || 'Process ' + process.id;
            var processType = process.is_vendor_process ? 'Outdoor (Vendor)' : 'In-house';
            var processTypeIcon = process.is_vendor_process ? 'bi-building' : 'bi-house';
            var vendorName = process.vendor_name || 'No vendor assigned';
            var quantityStarted = process.quantity_started || 0;
            var quantityRemaining = process.quantity_remaining || 0;
            var totalQuantity = process.work_order_quantity || 0;
            
            html += '<div class="card mb-3">';
            html += '<div class="card-body">';
            html += '<div class="d-flex justify-content-between align-items-start">';
            html += '<div>';
            html += '<h6 class="card-title">';
            html += '<span class="badge bg-primary me-2">' + (index + 1) + '</span>' + processName;
            html += '<span class="badge bg-info ms-2"><i class="' + processTypeIcon + ' me-1"></i>' + processType + '</span>';
            html += '</h6>';
            if (process.is_vendor_process) {
                html += '<p class="card-text mb-1"><small><strong>Vendor:</strong> ' + vendorName + '</small></p>';
            }
            html += '<div class="row">';
            html += '<div class="col-md-4"><small><strong>Total Required:</strong> ' + totalQuantity + ' pcs</small></div>';
            html += '<div class="col-md-4"><small><strong>Started:</strong> ' + quantityStarted + ' pcs</small></div>';
            html += '<div class="col-md-4"><small><strong>Remaining:</strong> ' + quantityRemaining + ' pcs</small></div>';
            html += '</div>';
            html += '</div>';
            html += '<div>';
            html += '<button class="btn btn-sm btn-success" onclick="createBatch(' + process.id + ', ' + itemId + ', ' + productId + ')">';
            html += '<i class="bi bi-plus"></i> Start New Batch';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            
            // Show batches for this process
            var processBatches = (data.batches || []).filter(function(batch) {
                return batch.process_id == process.id;
            });
            
                            if (processBatches.length > 0) {
                                html += '<div class="mt-3">';
                                html += '<h6 class="mb-2 text-muted">Active Batches</h6>';
                                // Full-width list: each batch is a card
                                processBatches.forEach(function(batch) {
                                    var status = (batch.status || 'open').toLowerCase();
                                    var statusColor = status === 'completed' ? 'success' : (status === 'in-progress' || status === 'in_progress' ? 'warning' : 'info');
                                    var started = Number(batch.planned_qty ?? batch.planned_quantity ?? batch.planned ?? 0) || 0;
                                    var completed = Number(batch.total_completed ?? batch.total_accepted ?? batch.actual_qty ?? batch.actual_quantity ?? 0) || 0;
                                    var pending = Number(batch.quantity_pending ?? (started - completed)) || 0;
                                    var pct = (started > 0) ? Math.min(100, Math.round((completed / started) * 100)) : 0;

                                    html += '<div class="card mb-2 border-start border-3 border-' + statusColor + '">';
                                    html += '<div class="card-body">';
                                    html += '<div class="row align-items-center">';
                                    
                                    // Batch Info
                                    html += '<div class="col-md-3">';
                                    html += '<strong>' + (batch.batch_code || batch.batch_number || ('#' + batch.id)) + '</strong>';
                                    html += '<br><small class="text-muted">Batch Code</small>';
                                    html += '</div>';
                                    
                                    // Quantities
                                    html += '<div class="col-md-4">';
                                    html += '<div class="row text-center">';
                                    html += '<div class="col-4"><strong class="text-primary">' + started + '</strong><br><small class="text-muted">Started</small></div>';
                                    html += '<div class="col-4"><strong class="text-success">' + completed + '</strong><br><small class="text-muted">Done</small></div>';
                                    html += '<div class="col-4"><strong class="text-warning">' + pending + '</strong><br><small class="text-muted">Pending</small></div>';
                                    html += '</div>';
                                    html += '</div>';
                                    
                                    // Progress Bar
                                    html += '<div class="col-md-3">';
                                    html += '<div class="progress" style="height:20px">';
                                    html += '<div class="progress-bar bg-' + statusColor + '" role="progressbar" style="width:' + pct + '%" aria-valuenow="' + pct + '">';
                                    html += pct + '%';
                                    html += '</div>';
                                    html += '</div>';
                                    html += '<small class="text-muted">Progress</small>';
                                    html += '</div>';

                                    // Action buttons
                                    html += '<div class="col-md-2 text-end">';
                                    html += '<div class="btn-group-vertical" role="group">';
                                    html += '<button class="btn btn-sm btn-outline-primary mb-1" title="Add Daily Log" onclick="addLog(' + batch.id + ', \'' + (batch.batch_code || batch.batch_number || '') + '\')">';
                                    html += '<i class="bi bi-list-check me-1"></i>Log';
                                    html += '</button>';
                                    html += '<button class="btn btn-sm btn-outline-success mb-1" title="Edit Batch" onclick="updateBatch(' + batch.id + ', \'' + (batch.batch_code || batch.batch_number || '') + '\')">';
                                    html += '<i class="bi bi-pencil-square me-1"></i>Edit';
                                    html += '</button>';
                                    html += '<button class="btn btn-sm btn-outline-danger" title="Delete Batch" onclick="deleteBatch(' + batch.id + ', this)">';
                                    html += '<i class="bi bi-trash me-1"></i>Delete';
                                    html += '</button>';
                                    html += '</div>';
                                    html += '</div>';
                                    
                                    html += '</div>'; // row
                                    html += '</div>'; // card-body

                                    html += '</div></div>';
                                });
                                html += '</div>';
                            }
            
            html += '</div>';
            html += '</div>';
        });
    } else {
        html += '<div class="alert alert-info"><i class="bi bi-info-circle"></i> No processes defined for this product.</div>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

function createBatch(processId, itemId, productId) {
    // Create and show the new batch creation modal
    createNewBatchModal(processId, itemId, productId);
}

function addLog(batchId, batchCode) {
    // Create and show the enhanced batch log modal
    createBatchLogModal(batchId, batchCode);
}

function updateBatch(batchId, batchCode) {
    // Create and show the batch update modal
    createBatchUpdateModal(batchId, batchCode);
}

function deleteBatch(batchId, btn) {
    if (!confirm('Delete this batch? This will remove all logs and cannot be undone.')) return;
    var tryDelete = function(path, cb) {
        var xhr = new XMLHttpRequest();
        xhr.open('POST', path, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                cb(xhr);
            }
        };
        xhr.send();
    };

    var snake = '<?= site_url() ?>/work-orders/ajax_delete_batch/' + batchId;
    var camel = '<?= site_url() ?>/work-orders/ajaxDeleteBatch/' + batchId;

    // Try snake_case first, if 404 try camelCase
    tryDelete(snake, function(xhr) {
        if (xhr.status === 200) {
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp.success) {
                    var card = btn.closest('.card'); if (card) card.remove();
                    alert('Batch deleted');
                    return;
                }
                alert('Failed: ' + (resp.message || 'Unknown'));
            } catch (e) { alert('Invalid server response'); }
        } else if (xhr.status === 404) {
            // try camel case
            tryDelete(camel, function(xhr2) {
                if (xhr2.status === 200) {
                    try {
                        var resp2 = JSON.parse(xhr2.responseText);
                        if (resp2.success) {
                            var card = btn.closest('.card'); if (card) card.remove();
                            alert('Batch deleted');
                            return;
                        }
                        alert('Failed: ' + (resp2.message || 'Unknown'));
                    } catch (e) { alert('Invalid server response'); }
                } else {
                    alert('Delete failed (HTTP ' + xhr2.status + ').');
                }
            });
        } else {
            alert('Delete failed (HTTP ' + xhr.status + ').');
        }
    });
}

// Delete work order function
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
                window.location.href = '<?= base_url('/work-orders') ?>';
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

// Change work order status function
function changeStatus(workOrderId, newStatus) {
    var statusText = newStatus.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase());
    
    if (confirm(`Are you sure you want to change the status to "${statusText}"?`)) {
        // Get CSRF token
        var csrfToken = window.csrfToken || window.csrfHash || '';
        
        fetch(`<?= base_url('/work-orders/') ?>${workOrderId}/change-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ status: newStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Status changed to ' + statusText + ' successfully!');
                location.reload();
            } else {
                alert('❌ ' + (data.message || 'Failed to change status'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ Network error occurred while changing the status');
        });
    }
}

function hideGlobalProcesses() {
    var container = document.getElementById('global-processes-container');
    if (!container) return;
    container.style.display = 'none';
    // restore caller toggle if present
    try {
        var caller = container._caller;
        if (caller) {
            var icon = caller.querySelector('i');
            if (icon) icon.className = 'bi bi-plus-circle me-1';
            caller.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Show Processes & Batches';
        }
    } catch (e) {
        // ignore
    }
}

// Enhanced Batch Log Modal Creation
function createBatchLogModal(batchId, batchCode) {
    var modalId = 'batchLogModal_' + batchId;
    
    // Remove existing modal if present
    var existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }
    
    // Get batch details first
    fetchBatchDetails(batchId, function(batchData) {
        var modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="${modalId}Label">
                                <i class="bi bi-journal-plus me-2"></i>Add Daily Log - ${batchCode}
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="batchLogForm_${batchId}">
                                <!-- Batch Summary -->
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="card bg-info text-white">
                                            <div class="card-body py-2 text-center">
                                                <h5 class="mb-0">${batchData.planned_qty || 0}</h5>
                                                <small>Started Qty</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-success text-white">
                                            <div class="card-body py-2 text-center">
                                                <h5 class="mb-0">${batchData.total_completed || 0}</h5>
                                                <small>Completed</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="card bg-warning text-dark">
                                            <div class="card-body py-2 text-center">
                                                <h5 class="mb-0">${(batchData.planned_qty || 0) - (batchData.total_completed || 0)}</h5>
                                                <small>Pending</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Process Assignment (Auto-loaded from process table) -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Process Type</label>
                                        <div class="form-control-plaintext">
                                            <span class="badge bg-${batchData.is_vendor_process ? 'warning' : 'info'}">
                                                <i class="bi bi-${batchData.is_vendor_process ? 'building' : 'house'} me-1"></i>
                                                ${batchData.is_vendor_process ? 'Outdoor (Vendor)' : 'In-house'}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-6" id="assigneeContainer_${batchId}">
                                        <!-- Will be populated based on process type -->
                                    </div>
                                </div>
                                
                                <!-- Quantity Inputs -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-arrow-down-circle text-primary"></i> Received
                                        </label>
                                        <input type="number" class="form-control" name="qty_received" min="0" placeholder="0">
                                        <small class="text-muted">Today's received quantity</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-check-circle text-success"></i> Completed
                                        </label>
                                        <input type="number" class="form-control" name="qty_completed" min="0" placeholder="0">
                                        <small class="text-muted">Work completed today</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-x-circle text-danger"></i> Rejected
                                        </label>
                                        <input type="number" class="form-control" name="qty_rejected" min="0" placeholder="0">
                                        <small class="text-muted">Quality rejected</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">
                                            <i class="bi bi-arrow-repeat text-warning"></i> For Repair
                                        </label>
                                        <input type="number" class="form-control" name="qty_for_repair" min="0" placeholder="0">
                                        <small class="text-muted">Sent for repair</small>
                                    </div>
                                </div>
                                
                                <!-- Date and Notes -->
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">Log Date</label>
                                        <input type="date" class="form-control" name="log_date" value="${new Date().toISOString().split('T')[0]}" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Log Type</label>
                                        <select class="form-select" name="log_type">
                                            <option value="progress">Daily Progress</option>
                                            <option value="completion">Completion</option>
                                            <option value="quality">Quality Check</option>
                                            <option value="repair">Repair Work</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notes</label>
                                    <textarea class="form-control" name="notes" rows="2" placeholder="Optional notes about today's work..."></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="submitBatchLog(${batchId})">
                                <i class="bi bi-save me-1"></i>Save Log Entry
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Load employee/vendor based on process type
        loadProcessAssignee(batchId, batchData);
        
        // Show modal
        var modal = new bootstrap.Modal(document.getElementById(modalId));
        modal.show();
        
        // Remove modal from DOM when hidden
        document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
            this.remove();
        });
    });
}

// Load employee or vendor based on process type
function loadProcessAssignee(batchId, batchData) {
    var container = document.getElementById('assigneeContainer_' + batchId);
    
    if (batchData.is_vendor_process) {
        // Load vendor (outdoor process)
        container.innerHTML = `
            <label class="form-label fw-bold">
                <i class="bi bi-building text-warning"></i> Vendor
            </label>
            <div class="form-control-plaintext">
                <strong>${batchData.vendor_name || 'Not assigned'}</strong>
                <input type="hidden" name="vendor_id" value="${batchData.vendor_id || ''}">
            </div>
        `;
    } else {
        // Load employee dropdown (in-house process)
        container.innerHTML = `
            <label class="form-label fw-bold">
                <i class="bi bi-person text-info"></i> Employee <span class="text-danger">*</span>
            </label>
            <select class="form-select" name="employee_id" required>
                <option value="">Select Employee...</option>
            </select>
        `;
        
        // Load employees via AJAX
        loadEmployeeOptions(batchId);
    }
}

// Submit batch log entry
function submitBatchLog(batchId) {
    var form = document.getElementById('batchLogForm_' + batchId);
    var formData = new FormData(form);
    
    // Validate at least one quantity is entered
    var received = parseInt(formData.get('qty_received') || 0);
    var completed = parseInt(formData.get('qty_completed') || 0);
    var rejected = parseInt(formData.get('qty_rejected') || 0);
    var forRepair = parseInt(formData.get('qty_for_repair') || 0);
    
    if (received + completed + rejected + forRepair === 0) {
        alert('❌ Please enter at least one quantity (received, completed, rejected, or for repair)');
        return;
    }
    
    // Show loading
    var submitBtn = document.querySelector('#batchLogModal_' + batchId + ' .btn-primary');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...';
    submitBtn.disabled = true;
    
    // Convert FormData to JSON
    var data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    // Submit to server
    fetch('<?= site_url() ?>/work-orders/batches/' + batchId + '/logs/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            alert('✅ Log entry saved successfully!');
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('batchLogModal_' + batchId));
            modal.hide();
            // Refresh the processes display
            location.reload();
        } else {
            alert('❌ Failed to save log: ' + (result.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('❌ Network error occurred');
    })
    .finally(() => {
        // Restore button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

function fetchBatchDetails(batchId, callback) {
    var url = '<?= site_url() ?>/work-orders/batches/' + batchId + '/details';
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && resp.batch) {
                        var b = resp.batch;
                        // normalize field names expected by the modal
                        var started = parseInt(b.planned_qty || b.planned_quantity || b.planned || 0, 10);
                        var completed = parseInt(b.total_completed || b.total_accepted || b.actual_qty || b.actual_quantity || 0, 10);
                        var rejected = parseInt(b.total_rejected || b.total_rejected || 0, 10);
                        var scrapped = parseInt(b.total_scrapped || 0, 10);
                        var pending = Math.max(0, started - completed - rejected - scrapped);
                        var out = {
                            started_qty: started,
                            pending_qty: pending,
                            total_completed: completed,
                            total_rejected: rejected,
                            total_scrapped: scrapped,
                            planned_qty: started,
                            planned_quantity: started,
                            actual_quantity: (b.actual_qty ?? b.actual_quantity ?? completed),
                            // Process information
                            is_vendor_process: b.is_vendor_process,
                            vendor_id: b.vendor_id,
                            vendor_name: b.vendor_name,
                            process_name: b.process_name
                        };
                        callback(out);
                        return;
                    }
                } catch (e) {
                    console.error('Error parsing batch details:', e);
                }
            }
            // default safe fallback
            callback({
                started_qty:0,
                pending_qty:0,
                total_completed:0,
                total_rejected:0,
                total_scrapped:0,
                planned_qty:0,
                planned_quantity:0,
                actual_quantity:0,
                is_vendor_process: false,
                vendor_id: null,
                vendor_name: '',
                process_name: ''
            });
        }
    };
    xhr.send();
}

function loadEmployeeOptions(batchId) {
    var select = document.querySelector('#batchLogForm_' + batchId + ' [name="employee_id"]');
    if (!select) return;

    // Fetch active employees from server
    var xhr = new XMLHttpRequest();
    xhr.open('GET', '<?= site_url() ?>/employees/getAll', true);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    if (resp.success && Array.isArray(resp.employees)) {
                        resp.employees.forEach(function(emp) {
                            var option = document.createElement('option');
                            option.value = emp.id;
                            option.textContent = emp.name || emp.employee_name || emp.full_name || ('Employee ' + emp.id);
                            select.appendChild(option);
                        });
                        return;
                    }
                } catch (e) {
                    // fallthrough
                }
            }
            // fallback - leave the default option only
        }
    };
    xhr.send();
}

function setupModalEventListeners(batchId) {
    var form = document.getElementById('batchLogForm_' + batchId);
    var logTypeSelect = form.querySelector('[name="log_type"]');
    var rejectionDiv = document.getElementById('rejectionReasonDiv');
    
    logTypeSelect.addEventListener('change', function() {
        if (this.value === 'rejection') {
            rejectionDiv.style.display = 'block';
            rejectionDiv.querySelector('textarea').required = true;
        } else {
            rejectionDiv.style.display = 'none';
            rejectionDiv.querySelector('textarea').required = false;
        }
    });
}

function saveBatchLog(batchId) {
    var form = document.getElementById('batchLogForm_' + batchId);
    var formData = new FormData(form);
    
    // Convert to object for easier handling
    var data = {};
    formData.forEach(function(value, key) {
        data[key] = value;
    });
    
    // Basic validation
    if (!data.log_type || !data.employee_id) {
        alert('Please fill in all required fields');
        return;
    }
    
    // Show loading state
    var saveBtn = document.querySelector('[onclick="saveBatchLog(' + batchId + ')"]');
    var originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Saving...';
    saveBtn.disabled = true;
    
    // Simulate AJAX save
    setTimeout(function() {
        alert('✅ Batch log saved successfully!\n\nLog Type: ' + data.log_type + '\nEmployee: ' + data.employee_id + '\nCompleted: ' + (data.qty_completed || 0) + ' pcs');
        
        // Close modal
        var modal = bootstrap.Modal.getInstance(document.getElementById('batchLogModal_' + batchId));
        modal.hide();
        
        // Remove modal from DOM
        document.getElementById('batchLogModal_' + batchId).remove();
        
        // Refresh the processes view if open
        // refreshProcessesView();
        
    }, 1500);
}

// Create New Batch Modal
function createNewBatchModal(processId, itemId, productId) {
    var modalId = 'newBatchModal';
    
    // Remove existing modal if present
    var existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }
    
    var modalHtml = `
        <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="${modalId}Label">
                            <i class="bi bi-plus-circle me-2"></i>Create New Batch
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="newBatchForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="batchCode" class="form-label fw-bold">
                                            <i class="bi bi-qr-code me-1"></i>Batch Code
                                        </label>
                                        <input type="text" class="form-control" id="batchCode" name="batch_code" 
                                               placeholder="AUTO-GENERATED" readonly>
                                        <small class="form-text text-muted">Automatically generated batch code</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="plannedQty" class="form-label fw-bold">
                                            <i class="bi bi-play-circle me-1"></i>Started Quantity
                                        </label>
                                        <input type="number" class="form-control" id="plannedQty" name="planned_quantity" 
                                               placeholder="How many pieces are you starting?" required min="1">
                                        <div class="form-text">Enter the quantity you are starting in this batch</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="startDate" class="form-label fw-bold">
                                            <i class="bi bi-calendar me-1"></i>Start Date
                                        </label>
                                        <input type="datetime-local" class="form-control" id="startDate" name="start_date" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="batchStatus" class="form-label fw-bold">
                                            <i class="bi bi-gear me-1"></i>Initial Status
                                        </label>
                                        <select class="form-select" id="batchStatus" name="status">
                                            <option value="planned">Planned</option>
                                            <option value="in_progress">In Progress</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="batchNotes" class="form-label fw-bold">
                                    <i class="bi bi-journal-text me-1"></i>Notes
                                </label>
                                <textarea class="form-control" id="batchNotes" name="notes" rows="3" 
                                          placeholder="Optional notes about this batch..."></textarea>
                            </div>
                            
                            <input type="hidden" name="process_id" value="${processId}">
                            <input type="hidden" name="work_order_item_id" value="${itemId}">
                            <input type="hidden" name="product_id" value="${productId}">
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Cancel
                        </button>
                        <button type="button" class="btn btn-success" onclick="submitNewBatch()">
                            <i class="bi bi-check-circle me-1"></i>Create Batch
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Set default start date to current time
    document.getElementById('startDate').value = new Date().toISOString().slice(0, 16);
    
    // Generate batch code
    generateBatchCode(processId, itemId);
    
    var modal = new bootstrap.Modal(document.getElementById(modalId));
    modal.show();
}

// Create Batch Update Modal
function createBatchUpdateModal(batchId, batchCode) {
    var modalId = 'updateBatchModal_' + batchId;
    
    // Remove existing modal if present
    var existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }
    
    // Fetch batch details first
    fetchBatchDetails(batchId, function(batchData) {
        var modalHtml = `
            <div class="modal fade" id="${modalId}" tabindex="-1" aria-labelledby="${modalId}Label" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title" id="${modalId}Label">
                                <i class="bi bi-pencil-square me-2"></i>Update Batch - ${batchCode}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <form id="updateBatchForm_${batchId}">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="updateStatus_${batchId}" class="form-label fw-bold">
                                                <i class="bi bi-gear me-1"></i>Status
                                            </label>
                                            <select class="form-select" id="updateStatus_${batchId}" name="status">
                                                <option value="planned" ${batchData.status === 'planned' ? 'selected' : ''}>Planned</option>
                                                <option value="in_progress" ${batchData.status === 'in_progress' ? 'selected' : ''}>In Progress</option>
                                                <option value="completed" ${batchData.status === 'completed' ? 'selected' : ''}>Completed</option>
                                                <option value="on_hold" ${batchData.status === 'on_hold' ? 'selected' : ''}>On Hold</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="actualQty_${batchId}" class="form-label fw-bold">
                                                <i class="bi bi-calculator me-1"></i>Actual Quantity
                                            </label>
                                            <input type="number" class="form-control" id="actualQty_${batchId}" 
                                                   name="actual_quantity" value="${batchData.actual_quantity || ''}" 
                                                   placeholder="Enter actual quantity" min="0">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="completedDate_${batchId}" class="form-label fw-bold">
                                                <i class="bi bi-calendar-check me-1"></i>Completion Date
                                            </label>
                                            <input type="datetime-local" class="form-control" id="completedDate_${batchId}" 
                                                   name="completion_date" value="${batchData.completion_date || ''}">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="plannedQtyUpdate_${batchId}" class="form-label fw-bold">
                                                <i class="bi bi-target me-1"></i>Planned Quantity
                                            </label>
                                            <input type="number" class="form-control" id="plannedQtyUpdate_${batchId}" 
                                                   name="planned_quantity" value="${batchData.planned_quantity}" min="1">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="updateNotes_${batchId}" class="form-label fw-bold">
                                        <i class="bi bi-journal-text me-1"></i>Notes
                                    </label>
                                    <textarea class="form-control" id="updateNotes_${batchId}" name="notes" rows="3" 
                                              placeholder="Update notes...">${batchData.notes || ''}</textarea>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle me-2"></i>
                                    <strong>Current Batch Info:</strong><br>
                                    Planned: ${batchData.planned_quantity} | 
                                    Actual: ${batchData.actual_quantity || 'Not set'} | 
                                    Status: <span class="badge bg-primary">${batchData.status}</span>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </button>
                            <button type="button" class="btn btn-warning" onclick="submitBatchUpdate(${batchId})">
                                <i class="bi bi-check-circle me-1"></i>Update Batch
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        var modal = new bootstrap.Modal(document.getElementById(modalId));
        modal.show();
    });
}

// Generate Batch Code
function generateBatchCode(processId, itemId) {
    var timestamp = new Date().toISOString().slice(0, 10).replace(/-/g, '');
    var random = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
    var batchCode = 'BTH-' + processId + '-' + itemId + '-' + timestamp + '-' + random;
    document.getElementById('batchCode').value = batchCode;
}

// Submit New Batch
function submitNewBatch() {
    var form = document.getElementById('newBatchForm');
    var formData = new FormData(form);
    var saveBtn = event.target;
    
    // Validation
    if (!formData.get('planned_quantity') || formData.get('planned_quantity') <= 0) {
        alert('❌ Please enter a valid planned quantity');
        return;
    }
    
    if (!formData.get('start_date')) {
        alert('❌ Please select a start date');
        return;
    }
    
    // Show loading state
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creating...';
    saveBtn.disabled = true;
    
    // Convert FormData to regular object for AJAX
    var data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    // AJAX request to create batch (use hyphenated route to match Routes.php)
    fetch('<?= base_url('work-orders/ajax_create_batch') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Batch created successfully!\n\nBatch Code: ' + data.batch_code + '\nQuantity: ' + data.planned_quantity);
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('newBatchModal'));
            modal.hide();
            document.getElementById('newBatchModal').remove();
            
            // Refresh processes view
            location.reload();
        } else {
            alert('❌ Error creating batch: ' + (data.message || 'Unknown error'));
            saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Create Batch';
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        alert('❌ Network error: ' + error.message);
        saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Create Batch';
        saveBtn.disabled = false;
    });
}

// Submit Batch Update
function submitBatchUpdate(batchId) {
    var form = document.getElementById('updateBatchForm_' + batchId);
    var formData = new FormData(form);
    var saveBtn = event.target;
    
    // Show loading state
    saveBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Updating...';
    saveBtn.disabled = true;
    
    // Convert FormData to regular object for AJAX
    var data = {};
    formData.forEach((value, key) => {
        data[key] = value;
    });
    
    // AJAX request to update batch
    fetch('<?= base_url('batches') ?>/' + batchId + '/update', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Batch updated successfully!\n\nStatus: ' + data.batch.status + '\nActual Qty: ' + (data.batch.actual_quantity || 'Not set'));
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('updateBatchModal_' + batchId));
            modal.hide();
            document.getElementById('updateBatchModal_' + batchId).remove();
            
            // Refresh processes view
            location.reload();
        } else {
            alert('❌ Error updating batch: ' + (data.message || 'Unknown error'));
            saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Update Batch';
            saveBtn.disabled = false;
        }
    })
    .catch(error => {
        alert('❌ Network error: ' + error.message);
        saveBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Update Batch';
        saveBtn.disabled = false;
    });
}
</script>

<?php $this->endSection() ?>
