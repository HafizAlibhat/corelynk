<?= $this->extend('layouts/main') ?>

<?= $this->section('css') ?>
<style>
/* Clean Tree View with Proper Connectors */
.tree-container {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 4px;
    padding: 20px;
}

.tree-node {
    position: relative;
    margin: 0;
    line-height: 1.5;
}

.tree-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 6px;
    margin-left: 0;
    border-bottom: 1px solid #f1f3f5;
}

.tree-toggle {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
    border-radius: 6px;
    border: 1px solid transparent;
    background: transparent;
    color: #495057;
    cursor: pointer;
    margin-right: 8px;
    font-size: 14px;
}

.tree-toggle:hover {
    background: #f1f3f5;
    border-color: #e2e6ea;
}

/* Fallback caret if Bootstrap Icons are missing */
.tree-toggle i::before { content: '▸'; display: inline-block; transition: transform .12s ease; }
.tree-toggle[aria-expanded="true"] i::before { content: '▾'; }
.tree-toggle:focus { outline: none; box-shadow: 0 0 0 .18rem rgba(13,110,253,.15); }

/* Tree Structure with Dotted Lines */
.tree-level-1 {
    margin-left: 20px;
    position: relative;
}

.tree-level-1::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 0;
    bottom: 0;
    border-left: 1px dotted #6c757d;
}

.tree-level-1 > .tree-node::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 12px;
    width: 9px;
    border-top: 1px dotted #6c757d;
}

.tree-level-2 {
    margin-left: 20px;
    position: relative;
}

.tree-level-2::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 0;
    bottom: 0;
    border-left: 1px dotted #6c757d;
}

.tree-level-2 > .tree-node::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 12px;
    width: 9px;
    border-top: 1px dotted #6c757d;
}

.tree-level-3 {
    margin-left: 20px;
    position: relative;
}

/* Collapsible animation: use max-height transition for smooth expand/collapse */
.tree-level-1, .tree-level-2, .tree-level-3 {
    overflow: hidden;
    max-height: 0;
    transition: max-height 220ms ease-in-out, opacity 160ms ease-in-out;
    opacity: 0;
}
.tree-level-1:not(.hidden), .tree-level-2:not(.hidden), .tree-level-3:not(.hidden) {
    max-height: 2000px; /* large enough for content */
    opacity: 1;
}

.tree-level-3 > .tree-node::before {
    content: '';
    position: absolute;
    left: -10px;
    top: 12px;
    width: 9px;
    border-top: 1px dotted #6c757d;
}

/* Stop vertical line at last child */
.tree-level-1 > .tree-node:last-child::after,
.tree-level-2 > .tree-node:last-child::after {
    content: '';
    position: absolute;
    left: -11px;
    top: 12px;
    bottom: -20px;
    width: 2px;
    background: #fff;
}

/* Content Styling */
.tree-info {
    flex: 1;
}

.tree-actions {
    display: flex;
    gap: 4px;
}

.tree-actions .btn {
    padding: 2px 8px;
    font-size: 11px;
    line-height: 1.3;
}

/* Row hover for better affordance */
.tree-content:hover { background: #f8f9fa; }

/* Level-specific styling */
.wo-info {
    font-weight: 600;
    color: #212529;
}

.product-info {
    font-weight: 500;
    color: #495057;
}

.process-info {
    color: #6c757d;
    font-size: 14px;
}

.batch-info {
    color: #6c757d;
    font-size: 13px;
}

.hidden {
    display: none !important;
}

/* Badges soft tone */
.tree-container .badge.bg-info { background-color: #cff4fc !important; color: #055160 !important; border: 1px solid #b6effb; }
.tree-container .badge.bg-warning { background-color: #fff3cd !important; color: #664d03 !important; border: 1px solid #ffecb5; }
.tree-container .badge.bg-warning.text-dark { color: #5c4a02 !important; }

/* Compact card header buttons */
.card-header .btn.btn-outline-secondary.btn-sm { padding: .2rem .45rem; }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h5 class="mb-0">
            <i class="bi bi-diagram-3 me-2"></i>Production Logs
        </h5>
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#newBatchModal">
                <i class="bi bi-plus-circle me-1"></i>New Batch
            </button>
            <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#addLogModal">
                <i class="bi bi-journal-plus me-1"></i>Add Log
            </button>
        </div>
    </div>
    
    <div class="card shadow-sm">
        <div class="card-header py-2 d-flex align-items-center justify-content-between">
            <strong>Production Logs (Accordion View)</strong>
        </div>
        <div class="card-body p-3">
            <div class="tree-container">
                <?php if (!isset($hierarchy)): ?>
                    <div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 20px;">
                        <strong>Debug:</strong> $hierarchy variable is not set
                    </div>
                <?php elseif (empty($hierarchy)): ?>
                    <div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 20px;">
                        <strong>Debug:</strong> $hierarchy is empty
                    </div>
                    <div id="hierarchyEmpty" style="text-align:center; padding:50px;">
                        <i class="bi bi-folder2-open" style="font-size: 3rem; color: #dee2e6;"></i>
                        <h6 class="mt-3">No Work Orders Found</h6>
                        <p class="text-muted">No active work orders match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div style="padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 4px; margin-bottom: 20px;">
                        <strong>Debug:</strong> Found <?= count($hierarchy) ?> work orders
                    </div>
                    <?php foreach ($hierarchy as $wo): ?>
                        <!-- Accordion/Tree View Only -->
                        <div class="tree-node">
                            <div class="tree-content">
                                <div class="tree-info wo-info">
                                    <span class="tree-toggle" data-target="wo-content-<?= $wo['id'] ?>" aria-expanded="false">
                                        <i class="bi bi-caret-right-fill" aria-hidden="true"></i>
                                    </span>
                                    <strong><?= esc($wo['wo_number']) ?></strong> | <?= count($wo['products']) ?> products | <?= $wo['total_batches'] ?> batches |
                                    <span class="badge bg-info"><?= esc(strtoupper($wo['status'])) ?></span>
                                </div>
                                <div class="tree-actions">
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteWorkOrder(<?= $wo['id'] ?>)">
                                        <i class="bi bi-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Work Order Children -->
                        <div id="wo-content-<?= $wo['id'] ?>" class="tree-level-1 hidden">
                            <?php foreach ($wo['products'] as $product): ?>
                                <!-- Product Level -->
                                <div class="tree-node">
                                    <div class="tree-content">
                                        <div class="tree-info product-info">
                                            <span style="width: 16px; display: inline-block;"></span>
                                            <strong><?= esc($product['product_name']) ?></strong> (<?= esc($product['product_code']) ?>) - Ordered: <?= esc($product['quantity']) ?> pcs
                                        </div>
                                        <div class="tree-actions">
                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteProduct(<?= $product['id'] ?>)">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Process Level -->
                                <div class="tree-level-2">
                                    <?php foreach ($product['processes'] as $process): ?>
                                        <div class="tree-node">
                                            <div class="tree-content">
                                                <div class="tree-info process-info">
                                                    <span class="tree-toggle" data-target="process-content-<?= $process['id'] ?>" aria-expanded="false">
                                                        <i class="bi bi-caret-right-fill" aria-hidden="true"></i>
                                                    </span>
                                                    <strong><?= esc($process['process_name']) ?></strong> (<?= count($process['batches']) ?> batches)
                                                </div>
                                                <div class="tree-actions">
                                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteProcess(<?= $process['id'] ?>)">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Batch Level -->
                                        <div id="process-content-<?= $process['id'] ?>" class="tree-level-3 hidden">
                                            <?php foreach ($process['batches'] as $batch): ?>
                                                <div class="tree-node">
                                                    <div class="tree-content">
                                                        <div class="tree-info batch-info">
                                                            <span style="width: 16px; display: inline-block;"></span>
                                                            <strong><?= esc($batch['batch_code']) ?></strong> | <?= (int)$batch['actual_qty'] ?>/<?= (int)$batch['planned_qty'] ?> pcs |
                                                            <span class="badge bg-warning text-dark"><?= esc(ucfirst(str_replace('_', ' ', $batch['status']))) ?></span>
                                                        </div>
                                                        <div class="tree-actions">
                                                            <button class="btn btn-outline-primary btn-sm" onclick="openDailyLogModal(<?= $batch['id'] ?>, '<?= esc($batch['batch_code']) ?>', <?= (int)$batch['planned_qty'] ?>)">
                                                                <i class="bi bi-journal-plus"></i> Daily Log
                                                            </button>
                                                            <button class="btn btn-outline-danger btn-sm" onclick="deleteBatch(<?= $batch['id'] ?>)">
                                                                <i class="bi bi-trash"></i> Delete
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add Log Modal -->
<div class="modal fade" id="addLogModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Daily Log Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addLogForm">
                    <input type="hidden" id="logBatchId" name="batch_id" value="">
                    <div class="mb-3">
                        <label class="form-label">Batch</label>
                        <input type="text" id="logBatchDisplay" class="form-control" readonly>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty Received</label>
                            <input type="number" id="logQtyReceived" name="qty_received" class="form-control" min="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty Accepted</label>
                            <input type="number" id="logQtyAccepted" name="qty_accepted" class="form-control" min="0">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty Rejected</label>
                            <input type="number" id="logQtyRejected" name="qty_rejected" class="form-control" min="0" value="0">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Qty For Repair</label>
                            <input type="number" id="logQtyRepair" name="qty_for_repair" class="form-control" min="0" value="0">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Employee</label>
                        <select id="logEmployee" name="employee_id" class="form-select">
                            <option value="">Select Employee</option>
                            <option value="1">John Doe</option>
                            <option value="2">Jane Smith</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea id="logNotes" name="notes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitDailyLog()">Save Log</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// Toggle functionality
document.addEventListener('click', function(e) {
    const toggle = e.target.closest('.tree-toggle');
    if (!toggle) return;

    const targetId = toggle.getAttribute('data-target');
    const target = document.getElementById(targetId);
    if (!target) return;

    const isHidden = target.classList.contains('hidden');

    if (isHidden) {
        target.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        const icon = toggle.querySelector('i');
        if (icon) icon.classList.remove('bi-caret-right-fill');
        if (icon) icon.classList.add('bi-caret-down-fill');
    } else {
        target.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
        const icon = toggle.querySelector('i');
        if (icon) icon.classList.remove('bi-caret-down-fill');
        if (icon) icon.classList.add('bi-caret-right-fill');
    }
});

// Toast helper
function showToast(message, type = 'info') {
    let toastEl = document.getElementById('globalToast');
    if (!toastEl) {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
                <div id="globalToast" class="toast" role="alert" aria-live="polite" aria-atomic="true">
                    <div class="toast-header">
                        <strong class="me-auto">Notification</strong>
                        <small class="text-muted"></small>
                        <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                    </div>
                    <div class="toast-body"></div>
                </div>
            </div>`;
        document.body.appendChild(wrapper);
        toastEl = document.getElementById('globalToast');
    }

    toastEl.querySelector('.toast-body').textContent = message;
    const bsToast = new bootstrap.Toast(toastEl, { delay: 4000 });
    bsToast.show();
}

// CSRF token helper (CodeIgniter sets a meta tag in layout; fallback to empty)
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) return meta.getAttribute('content');
    return '';
}

// Delete Functions - Following AJAX patterns from coding instructions
function deleteWorkOrder(woId) {
    if (!confirm('Are you sure you want to delete this work order? This will delete all related products and batches.')) return;

    const payload = { work_order_id: woId };
    fetch('<?= base_url('production/ajax-delete-work-order') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Work order deleted successfully', 'success');
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error: ' + (data.message || 'Failed to delete work order'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error deleting work order', 'danger');
    });
}

function deleteProduct(productId) {
    if (!confirm('Are you sure you want to delete this product? This will delete all related batches.')) return;

    const payload = { wo_item_id: productId };
    fetch('<?= base_url('production/ajax-delete-product') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Product deleted successfully', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to delete product'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error deleting product', 'danger');
    });
}

function deleteProcess(processId) {
    if (!confirm('Are you sure you want to delete this process? This will delete all related batches.')) return;

    const payload = { product_process_id: processId };
    fetch('<?= base_url('production/ajax-delete-process') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Process deleted successfully', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to delete process'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error deleting process', 'danger');
    });
}

function deleteBatch(batchId) {
    if (!confirm('Are you sure you want to delete this batch?')) return;

    const payload = { batch_id: batchId };
    fetch('<?= base_url('production/ajax-delete-batch') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Batch deleted successfully', 'success');
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Error: ' + (data.message || 'Failed to delete batch'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error deleting batch', 'danger');
    });
}

// Daily Log Functions
function openDailyLogModal(batchId, batchCode, plannedQty) {
    document.getElementById('logBatchId').value = batchId;
    document.getElementById('logBatchDisplay').value = batchCode + ' (' + plannedQty + ' pcs)';
    
    // Clear form but keep batch info
    document.getElementById('addLogForm').reset();
    document.getElementById('logBatchId').value = batchId;
    document.getElementById('logBatchDisplay').value = batchCode + ' (' + plannedQty + ' pcs)';
    
    const modal = new bootstrap.Modal(document.getElementById('addLogModal'));
    modal.show();
}

function submitDailyLog() {
    const form = document.getElementById('addLogForm');
    const formData = new FormData(form);
    
    // Validation following project patterns
    const received = parseInt(formData.get('qty_received')) || 0;
    const accepted = parseInt(formData.get('qty_accepted')) || 0;
    const rejected = parseInt(formData.get('qty_rejected')) || 0;
    const repair = parseInt(formData.get('qty_for_repair')) || 0;
    
    if (received <= 0) {
        showToast('Please enter quantity received', 'warning');
        return;
    }

    if (accepted + rejected + repair > received) {
        showToast('Total accepted + rejected + repair cannot exceed received quantity', 'warning');
        return;
    }
    
    fetch('<?= base_url('production/ajax-add-log') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': getCsrfToken()
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast('Daily log added successfully', 'success');
            bootstrap.Modal.getInstance(document.getElementById('addLogModal')).hide();
            setTimeout(() => location.reload(), 600);
        } else {
            showToast('Error: ' + (data.message || 'Failed to add log'), 'danger');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Error adding log', 'danger');
    });
}

// Auto-expand work order on page load
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(() => {
        // Find the first toggle button and click it to show data immediately
        const firstToggle = document.querySelector('.tree-toggle');
        if (firstToggle) {
            firstToggle.click();
            
            // Also expand the first process after a short delay
            setTimeout(() => {
                const processToggle = document.querySelector('.tree-level-2 .tree-toggle');
                if (processToggle) {
                    processToggle.click();
                }
            }, 100);
        }
    }, 100);
});
</script>
<?= $this->endSection() ?>