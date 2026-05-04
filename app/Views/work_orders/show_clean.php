<?php

namespace App\Views;

?>

<?php $this->extend('layouts/dashboard') ?>

<?php $this->section('title') ?>
Work Order #<?= $work_order['wo_number'] ?>
<?php $this->endSection() ?>

<?php $this->section('content') ?>

<div class="row">
    <div class="col-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-clipboard-data me-2"></i>Work Order #<?= esc($work_order['wo_number']) ?>
                    </h6>
                    <div>
                        <span class="badge bg-<?= $work_order['status'] === 'completed' ? 'success' : ($work_order['status'] === 'in-progress' ? 'warning' : 'secondary') ?>">
                            <?= ucfirst($work_order['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Work Order Number</label>
                            <p class="mb-0"><?= esc($work_order['wo_number']) ?></p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Products</label>
                            <?php if (!empty($work_order['items'])): ?>
                                <?php foreach ($work_order['items'] as $item): ?>
                                    <div class="mb-2">
                                        <p class="mb-0"><?= esc($item['product_name']) ?></p>
                                        <small class="text-muted">
                                            Code: <?= esc($item['product_code']) ?> | 
                                            Qty: <?= number_format($item['quantity_ordered']) ?> <?= esc($item['unit']) ?>
                                        </small>
                                        <!-- Processes and batches expandable UI -->
                                        <div class="mt-2">
                                            <div class="d-flex align-items-center">
                                                <span class="expand-toggle" 
                                                      onclick="toggleProcesses(<?= $item['id'] ?>, <?= $item['product_id'] ?>, this)"
                                                      style="cursor: pointer; font-weight: bold; color: #007bff; user-select: none;">
                                                    <i class="bi bi-plus-circle me-1"></i> Show Processes & Batches
                                                </span>
                                            </div>
                                            <div class="processes-container mt-2" id="processes-for-item-<?= $item['id'] ?>" style="display:none; border-left: 3px solid #007bff; padding-left: 15px; margin-left: 10px;"></div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="mb-0 text-muted">No products assigned</p>
                            <?php endif; ?>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Customer</label>
                            <p class="mb-0"><?= esc($work_order['customer_name']) ?></p>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Total Quantity</label>
                            <p class="mb-0">
                                Ordered: <strong><?= number_format($work_order['total_quantity'] ?? 0) ?></strong><br>
                                Completed: <strong><?= number_format($work_order['total_completed'] ?? 0) ?></strong>
                            </p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Priority</label>
                            <p class="mb-0">
                                <span class="badge bg-<?= $work_order['priority'] === 'high' ? 'danger' : ($work_order['priority'] === 'medium' ? 'warning' : 'success') ?>">
                                    <?= ucfirst($work_order['priority'] ?? 'normal') ?>
                                </span>
                            </p>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Dates</label>
                            <p class="mb-0">
                                <small>Created: <?= date('M d, Y', strtotime($work_order['created_at'])) ?></small><br>
                                <small>Due Date: <?= $work_order['due_date'] ? date('M d, Y', strtotime($work_order['due_date'])) : 'Not set' ?></small>
                            </p>
                        </div>
                    </div>
                </div>
                
                <?php if (!empty($work_order['description'])): ?>
                <div class="row">
                    <div class="col-12">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted">Description</label>
                            <p class="mb-0"><?= esc($work_order['description']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php 
                    $total_qty = $work_order['total_quantity'] ?? 1;
                    $completed_qty = $work_order['total_completed'] ?? 0;
                    $progress = $total_qty > 0 ? ($completed_qty / $total_qty) * 100 : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted small">Completion Progress</span>
                        <span class="text-muted small"><?= number_format($progress, 1) ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: <?= $progress ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Clean expand/collapse functionality for processes and batches
function toggleProcesses(itemId, productId, toggleElement) {
    var container = document.getElementById('processes-for-item-' + itemId);
    var icon = toggleElement.querySelector('i');
    
    if (container.style.display === 'block') {
        // Collapse
        container.style.display = 'none';
        icon.className = 'bi bi-plus-circle me-1';
        toggleElement.innerHTML = '<i class="bi bi-plus-circle me-1"></i> Show Processes & Batches';
    } else {
        // Expand and load data
        container.style.display = 'block';
        icon.className = 'bi bi-dash-circle me-1';
        toggleElement.innerHTML = '<i class="bi bi-dash-circle me-1"></i> Hide Processes & Batches';
        
        // Show loading message
        container.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Loading processes and batches...</div>';
        
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
    
    if (data.processes && data.processes.length > 0) {
        html += '<h6 class="mb-3"><i class="bi bi-gear-fill text-primary"></i> Manufacturing Processes</h6>';
        
        data.processes.forEach(function(process, index) {
            var processName = process.process_name || 'Process ' + process.process_id;
            var processDesc = process.process_description || 'No description';
            var vendorName = process.vendor_name || 'No vendor assigned';
            
            html += '<div class="card mb-3">';
            html += '<div class="card-body">';
            html += '<div class="d-flex justify-content-between align-items-start">';
            html += '<div>';
            html += '<h6 class="card-title">';
            html += '<span class="badge bg-primary me-2">' + (index + 1) + '</span>' + processName;
            html += '</h6>';
            html += '<p class="card-text mb-1"><small class="text-muted">' + processDesc + '</small></p>';
            html += '<p class="card-text mb-2"><small><strong>Vendor:</strong> ' + vendorName + '</small></p>';
            html += '</div>';
            html += '<div>';
            html += '<button class="btn btn-sm btn-success" onclick="createBatch(' + process.process_id + ', ' + itemId + ', ' + productId + ')">';
            html += '<i class="bi bi-plus"></i> New Batch';
            html += '</button>';
            html += '</div>';
            html += '</div>';
            
            // Show batches for this process
            var processBatches = (data.batches || []).filter(function(batch) {
                return batch.process_id == process.process_id;
            });
            
            if (processBatches.length > 0) {
                html += '<div class="mt-3 pt-3 border-top">';
                html += '<h6 class="mb-2"><i class="bi bi-box-seam text-success"></i> Batches:</h6>';
                
                processBatches.forEach(function(batch) {
                    var statusColor = batch.status === 'completed' ? 'success' : (batch.status === 'in-progress' ? 'warning' : 'secondary');
                    
                    html += '<div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">';
                    html += '<div>';
                    html += '<strong>' + batch.batch_code + '</strong> ';
                    html += '<span class="badge bg-' + statusColor + '">' + batch.status + '</span>';
                    html += '<br><small>Planned: ' + batch.planned_qty + ' | Actual: ' + (batch.actual_qty || '0') + '</small>';
                    html += '</div>';
                    html += '<div>';
                    html += '<button class="btn btn-sm btn-outline-primary me-1" onclick="addLog(' + batch.id + ', \'' + batch.batch_code + '\')">';
                    html += '<i class="bi bi-journal-plus"></i> Add Log';
                    html += '</button>';
                    html += '<button class="btn btn-sm btn-outline-success" onclick="updateBatch(' + batch.id + ', \'' + batch.batch_code + '\')">';
                    html += '<i class="bi bi-pencil"></i> Update';
                    html += '</button>';
                    html += '</div>';
                    html += '</div>';
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
    alert('🆕 CREATE NEW BATCH\n\nProcess ID: ' + processId + '\nItem ID: ' + itemId + '\nProduct ID: ' + productId + '\n\n✅ This will open a form to create a new batch with:\n- Batch code generation\n- Planned quantity\n- Start date\n- Notes');
}

function addLog(batchId, batchCode) {
    alert('📝 ADD BATCH LOG\n\nBatch: ' + batchCode + ' (ID: ' + batchId + ')\n\n✅ This will open a form to add:\n- Work progress\n- Quantity completed\n- Issues/Notes\n- Photos\n- Timestamp');
}

function updateBatch(batchId, batchCode) {
    alert('✏️ UPDATE BATCH\n\nBatch: ' + batchCode + ' (ID: ' + batchId + ')\n\n✅ This will open a form to update:\n- Status (In Progress/Completed)\n- Actual quantity\n- Completion date\n- Final notes');
}
</script>

<?php $this->endSection() ?>
