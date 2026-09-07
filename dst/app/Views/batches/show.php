<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Batch Details - <?= $batch['batch_number'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content-wrapper">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-2">
                        <li class="breadcrumb-item"><a href="<?= base_url('batches') ?>">Batches</a></li>
                        <li class="breadcrumb-item active"><?= $batch['batch_number'] ?></li>
                    </ol>
                </nav>
                <h1 class="page-title mb-0">
                    <i class="bi bi-box text-primary me-3"></i><?= $batch['batch_number'] ?>
                </h1>
                <p class="text-muted mb-0"><?= $batch['product_name'] ?> - <?= $batch['process_name'] ?></p>
            </div>
            <div class="col-auto">
                <div class="btn-group">
                    <button type="button" class="btn btn-warning" onclick="updateBatchDetails(<?= $batch['id'] ?>, '<?= $batch['batch_number'] ?>')">
                        <i class="bi bi-pencil me-2"></i>Update Batch
                    </button>
                    <button type="button" class="btn btn-success" onclick="addBatchLog(<?= $batch['id'] ?>, '<?= $batch['batch_number'] ?>')">
                        <i class="bi bi-plus-circle me-2"></i>Add Log
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="exportBatchPDF(<?= $batch['id'] ?>)">
                        <i class="bi bi-filetype-pdf me-2"></i>Export PDF
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Overview Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-primary">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-target"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= number_format($batch['quantity']) ?></h3>
                        <p>Planned Quantity</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-success">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-check-circle"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= $batch['quantity_completed'] ? number_format($batch['quantity_completed']) : '-' ?></h3>
                        <p>Actual Quantity</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-warning">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-activity"></i>
                    </div>
                    <div class="stats-info">
                        <h3>
                            <?php
                            $statusClasses = [
                                'planned' => 'bg-secondary',
                                'in_progress' => 'bg-primary',
                                'completed' => 'bg-success',
                                'on_hold' => 'bg-warning'
                            ];
                            $statusClass = $statusClasses[$batch['status']] ?? 'bg-secondary';
                            ?>
                            <span class="badge <?= $statusClass ?> fs-6"><?= ucwords(str_replace('_', ' ', $batch['status'])) ?></span>
                        </h3>
                        <p>Current Status</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-info">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-journal-text"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= count($logs) ?></h3>
                        <p>Total Logs</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Details Card -->
    <div class="row">
        <div class="col-md-8">
            <!-- Batch Logs -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-journal-bookmark me-2"></i>Batch Logs
                        </h5>
                        <button type="button" class="btn btn-success btn-sm" onclick="addBatchLog(<?= $batch['id'] ?>, '<?= $batch['batch_number'] ?>')">
                            <i class="bi bi-plus me-1"></i>Add New Log
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x display-1 text-muted"></i>
                            <h5 class="text-muted mt-3">No logs found</h5>
                            <p class="text-muted">Start by adding the first batch log</p>
                            <button type="button" class="btn btn-success" onclick="addBatchLog(<?= $batch['id'] ?>, '<?= $batch['batch_number'] ?>')">
                                <i class="bi bi-plus-circle me-2"></i>Add First Log
                            </button>
                        </div>
                    <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($logs as $index => $log): ?>
                                <div class="timeline-item <?= $index === 0 ? 'timeline-item-latest' : '' ?>">
                                    <div class="timeline-marker">
                                        <?php
                                        $logIcons = [
                                            'production_start' => 'bi-play-circle text-success',
                                            'production_end' => 'bi-stop-circle text-danger',
                                            'quality_check' => 'bi-shield-check text-info',
                                            'material_issue' => 'bi-exclamation-triangle text-warning',
                                            'maintenance' => 'bi-tools text-secondary',
                                            'general' => 'bi-journal-text text-primary'
                                        ];
                                        $iconClass = $logIcons[$log['log_type'] ?? 'general'] ?? 'bi-journal-text text-primary';
                                        ?>
                                        <i class="<?= $iconClass ?>"></i>
                                    </div>
                                    <div class="timeline-content">
                                        <div class="timeline-header">
                                            <h6 class="timeline-title">
                                                <?= ucwords(str_replace('_', ' ', $log['log_type'] ?? 'General Log')) ?>
                                                <?php if ($index === 0): ?>
                                                    <span class="badge bg-success ms-2">Latest</span>
                                                <?php endif; ?>
                                            </h6>
                                            <small class="timeline-time text-muted">
                                                <i class="bi bi-clock me-1"></i><?= date('M d, Y H:i', strtotime($log['created_at'])) ?>
                                                <?php if ($log['employee_name']): ?>
                                                    | <i class="bi bi-person me-1"></i><?= $log['employee_name'] ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="timeline-body">
                                            <?php if ($log['notes']): ?>
                                                <p class="mb-2"><?= nl2br(htmlspecialchars($log['notes'])) ?></p>
                                            <?php endif; ?>
                                            
                                            <?php if ((isset($log['qty_received']) && $log['qty_received']) || (isset($log['qty_completed']) && $log['qty_completed']) || (isset($log['qty_rejected']) && $log['qty_rejected']) || (isset($log['qty_scrapped']) && $log['qty_scrapped'])): ?>
                                                <div class="quantity-summary">
                                                    <strong>Quantity Details:</strong>
                                                    <div class="row mt-2">
                                                        <?php if (isset($log['qty_received']) && $log['qty_received']): ?>
                                                            <div class="col-sm-3">
                                                                <small class="text-muted">Received</small>
                                                                <div class="fw-bold text-info"><?= number_format($log['qty_received']) ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (isset($log['qty_completed']) && $log['qty_completed']): ?>
                                                            <div class="col-sm-3">
                                                                <small class="text-muted">Completed</small>
                                                                <div class="fw-bold text-success"><?= number_format($log['qty_completed']) ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (isset($log['qty_rejected']) && $log['qty_rejected']): ?>
                                                            <div class="col-sm-3">
                                                                <small class="text-muted">Rejected</small>
                                                                <div class="fw-bold text-danger"><?= number_format($log['qty_rejected']) ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                        <?php if (isset($log['qty_scrapped']) && $log['qty_scrapped']): ?>
                                                            <div class="col-sm-3">
                                                                <small class="text-muted">Scrapped</small>
                                                                <div class="fw-bold text-warning"><?= number_format($log['qty_scrapped']) ?></div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- Batch Information -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-bottom">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Batch Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <strong>Work Order:</strong>
                        <span>WO-<?= str_pad($batch['work_order_id'], 4, '0', STR_PAD_LEFT) ?></span>
                        <div class="text-muted small"><?= $batch['work_order_number'] ?? 'N/A' ?></div>
                    </div>
                    
                    <div class="info-item">
                        <strong>Product:</strong>
                        <span><?= $batch['product_name'] ?></span>
                        <div class="text-muted small"><?= $batch['code'] ?? 'N/A' ?></div>
                    </div>
                    
                    <div class="info-item">
                        <strong>Process:</strong>
                        <span><?= $batch['process_name'] ?></span>
                        <div class="text-muted small"><?= $batch['process_code'] ?? 'N/A' ?></div>
                    </div>
                    
                    <div class="info-item">
                        <strong>Start Date:</strong>
                        <span><?= date('M d, Y H:i', strtotime($batch['start_date'])) ?></span>
                    </div>
                    
                    <?php if ($batch['completion_date']): ?>
                        <div class="info-item">
                            <strong>Completion Date:</strong>
                            <span><?= date('M d, Y H:i', strtotime($batch['completion_date'])) ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <div class="info-item">
                        <strong>Progress:</strong>
                        <?php
                        $progress = 0;
                        if ($batch['quantity'] > 0 && $batch['quantity_completed'] > 0) {
                            $progress = ($batch['quantity_completed'] / $batch['quantity']) * 100;
                            $progress = min(100, $progress);
                        }
                        ?>
                        <div class="progress mt-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $progress ?>%">
                                <?= number_format($progress, 1) ?>%
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($batch['notes']): ?>
                        <div class="info-item">
                            <strong>Notes:</strong>
                            <div class="mt-1 text-break"><?= nl2br(htmlspecialchars($batch['notes'])) ?></div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.info-item {
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid #e9ecef;
}

.info-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 1rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    margin-bottom: 2rem;
}

.timeline-marker {
    position: absolute;
    left: -2.5rem;
    top: 0.25rem;
    width: 2rem;
    height: 2rem;
    background: white;
    border: 2px solid #e9ecef;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}

.timeline-item-latest .timeline-marker {
    border-color: #198754;
    background: #198754;
    color: white;
}

.timeline-content {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
    margin-left: 1rem;
}

.timeline-header {
    margin-bottom: 0.75rem;
}

.timeline-title {
    margin-bottom: 0.25rem;
    color: #495057;
}

.timeline-time {
    font-size: 0.875rem;
}

.quantity-summary {
    background: rgba(108, 117, 125, 0.1);
    border-radius: 6px;
    padding: 0.75rem;
    margin-top: 0.5rem;
}
</style>

<script>
function updateBatchDetails(batchId, batchCode) {
    // Reuse the update modal from the list page
    createBatchUpdateModal(batchId, batchCode);
}

function addBatchLog(batchId, batchCode) {
    alert('📝 Add Batch Log functionality will be implemented here.\n\nThis will open a comprehensive form for detailed batch logging.');
}

function exportBatchPDF(batchId) {
    window.open('<?= base_url('pdfs/batch') ?>/' + batchId, '_blank');
}

// Include the update modal function
function createBatchUpdateModal(batchId, batchCode) {
    var modalId = 'updateBatchModal_' + batchId;
    
    // Remove existing modal if present
    var existingModal = document.getElementById(modalId);
    if (existingModal) {
        existingModal.remove();
    }
    
    // Fetch batch details first
    fetch('<?= base_url('batches') ?>/' + batchId + '/details')
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                var batchData = result.batch;
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
                                                           name="actual_quantity" value="${batchData.quantity_completed || ''}" 
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
                                                           name="planned_quantity" value="${batchData.quantity}" min="1">
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
            }
        })
        .catch(error => {
            alert('❌ Error loading batch details: ' + error.message);
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
            alert('✅ Batch updated successfully!\n\nStatus: ' + data.batch.status + '\nActual Qty: ' + (data.batch.quantity_completed || 'Not set'));
            
            // Close modal
            var modal = bootstrap.Modal.getInstance(document.getElementById('updateBatchModal_' + batchId));
            modal.hide();
            document.getElementById('updateBatchModal_' + batchId).remove();
            
            // Refresh page
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
<?= $this->endSection() ?>
