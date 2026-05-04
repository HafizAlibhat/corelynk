<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Production Batches (Archived)<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <h4 class="mb-3">Production Batches page has been archived</h4>
                <p class="text-muted mb-4">Batches are now managed from the Work Orders screen (Work Order → Product → Process → Batches → Logs).</p>
                <a href="<?= site_url('work-orders') ?>" class="btn btn-primary">Go to Work Orders</a>
            </div>
        </div>
    </div>
</div>

<script>
    // Ensure any legacy routes that open this page immediately redirect to Work Orders to avoid confusion
    if (window && window.location) {
        // Short delay so the message is visible if the user intentionally opened the page
        setTimeout(function(){
            if (window.location.pathname.endsWith('/batches') || window.location.pathname.endsWith('/batches/')) {
                window.location.href = '<?= site_url('work-orders') ?>';
            }
        }, 1200);
    }
</script>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <nav aria-label="Batch pagination" class="mt-4">
        <ul class="pagination justify-content-center">
            <li class="page-item disabled">
                <span class="page-link">Previous</span>
            </li>
            <li class="page-item active">
                <span class="page-link">1</span>
            </li>
            <li class="page-item">
                <a class="page-link" href="#">2</a>
            </li>
            <li class="page-item">
                <a class="page-link" href="#">Next</a>
            </li>
        </ul>
    </nav>
</div>

<script>
// Filter and search functionality
function applyFilters() {
    var searchTerm = document.getElementById('searchBatch').value;
    var statusFilter = document.getElementById('statusFilter').value;
    var dateFilter = document.getElementById('dateFilter').value;
    var workOrderFilter = document.getElementById('workOrderFilter').value;
    
    // Build query parameters
    var params = new URLSearchParams();
    if (searchTerm) params.append('search', searchTerm);
    if (statusFilter) params.append('status', statusFilter);
    if (dateFilter) params.append('date', dateFilter);
    if (workOrderFilter) params.append('work_order', workOrderFilter);
    
    // Reload page with filters
    window.location.href = '<?= base_url('batches') ?>?' + params.toString();
}

function resetFilters() {
    document.getElementById('filterForm').reset();
    window.location.href = '<?= base_url('batches') ?>';
}

function refreshBatchList() {
    location.reload();
}

// Batch actions from list
function viewBatchDetails(batchId, batchCode) {
    window.location.href = '<?= base_url('batches') ?>/' + batchId;
}

function updateBatchFromList(batchId, batchCode) {
    // Reuse the update modal from work orders view
    createBatchUpdateModal(batchId, batchCode);
}

function addBatchLogFromList(batchId, batchCode) {
    // Reuse the log modal from work orders view
    createBatchLogModal(batchId, batchCode);
}

function showCreateBatchModal() {
    alert('⚠️ To create a new batch, please go to the specific Work Order and Process.\n\nBatches must be created within the context of a work order process.');
    window.location.href = '<?= base_url('work_orders') ?>';
}

function exportBatchList() {
    window.open('<?= base_url('pdfs/batches') ?>', '_blank');
}

// Search on enter key
document.getElementById('searchBatch').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        applyFilters();
    }
});

// Auto-refresh every 30 seconds
setInterval(function() {
    // Only refresh if no modals are open
    if (!document.querySelector('.modal.show')) {
        refreshBatchList();
    }
}, 30000);

// Reuse modal functions from work order view for consistency
// Create Batch Update Modal
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
                                        
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            <strong>Current Batch Info:</strong><br>
                                            Planned: ${batchData.quantity} | 
                                            Actual: ${batchData.quantity_completed || 'Not set'} | 
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

// Create Batch Log Modal (simplified for batch list page)
function createBatchLogModal(batchId, batchCode) {
    alert('📝 Add Batch Log\n\nThis will redirect you to the detailed batch view where you can add comprehensive logs.\n\nBatch: ' + batchCode);
    window.location.href = '<?= base_url('batches') ?>/' + batchId;
}

// Delete Batch
function deleteBatch(batchId, batchCode) {
    if (confirm('⚠️ Are you sure you want to delete batch "' + batchCode + '"?\n\nThis action cannot be undone and will also delete all associated batch logs.')) {
        fetch('<?= base_url('batches') ?>/' + batchId, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('✅ Batch deleted successfully!');
                location.reload();
            } else {
                alert('❌ Error deleting batch: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            alert('❌ Network error: ' + error.message);
        });
    }
}

// Toggle all batch checkboxes
function toggleAllBatches(checkbox) {
    const checkboxes = document.querySelectorAll('.batch-checkbox');
    checkboxes.forEach(cb => cb.checked = checkbox.checked);
}

// Bulk batch actions
function bulkBatchAction(action) {
    const selectedIds = Array.from(document.querySelectorAll('.batch-checkbox:checked')).map(cb => cb.value);
    
    if (selectedIds.length === 0) {
        alert('Please select at least one batch');
        return;
    }

    if (action === 'delete') {
        if (confirm(`⚠️ Are you sure you want to delete ${selectedIds.length} batch(es)?\n\nThis action cannot be undone and will also delete all associated batch logs.`)) {
            // Delete each batch
            let promises = selectedIds.map(id => {
                return fetch(`<?= base_url('batches') ?>/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
            });

            Promise.all(promises)
                .then(responses => Promise.all(responses.map(r => r.json())))
                .then(results => {
                    const successful = results.filter(r => r.success).length;
                    const failed = results.length - successful;
                    
                    if (failed === 0) {
                        alert(`✅ Successfully deleted ${successful} batch(es)`);
                    } else {
                        alert(`⚠️ Deleted ${successful} batch(es), but ${failed} failed to delete`);
                    }
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred during bulk delete');
                });
        }
    } else {
        console.log(`Bulk ${action} for batches:`, selectedIds);
        alert(`Bulk ${action} feature will be implemented soon.`);
    }
}
</script>

    </div>
</div>
<?= $this->endSection() ?>
