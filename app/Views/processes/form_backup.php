<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-diagram-3 me-2"></i>
                <?= isset($process) ? 'Edit Process' : 'Create New Process' ?>
            </h2>
            <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Processes
            </a>
        </div>
    </div>
</div>

<?= form_open(isset($process) ? base_url('/processes/' . $process['id'] . '/edit') : base_url('/processes/store'), 
    ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'processForm']) ?>
<?= csrf_field() ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Process Information
                </h5>
            </div>
            <div class="card-body">
                <!-- Process Name -->
                <div class="mb-4">
                    <label for="name" class="form-label">Process Name <span class="text-danger">*</span></label>
                    <input type="text" 
                           class="form-control" 
                           id="name" 
                           name="name" 
                           value="<?= old('name', $process['name'] ?? '') ?>" 
                           required
                           placeholder="e.g., Cutting, Welding, Assembly, Quality Check">
                    <div class="invalid-feedback">
                        Please provide a process name.
                    </div>
                    <div class="form-text">Enter a descriptive name for this manufacturing process.</div>
                </div>

                <!-- Process Description -->
                <div class="mb-4">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="3"
                              placeholder="Brief description of what this process does..."><?= old('description', $process['description'] ?? '') ?></textarea>
                    <div class="form-text">Optional: Provide additional details about this process.</div>
                </div>

                <!-- Process Type -->
                <div class="mb-4">
                    <label class="form-label">Process Type <span class="text-danger">*</span></label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-check-card">
                                <input class="form-check-input" 
                                       type="radio" 
                                       name="process_type" 
                                       id="inhouse" 
                                       value="in_house" 
                                       <?= old('process_type', $process['process_type'] ?? 'in_house') == 'in_house' ? 'checked' : '' ?>
                                       onclick="hideVendorSection()">
                                <label class="form-check-label card" for="inhouse">
                                    <div class="card-body text-center">
                                        <i class="bi bi-building text-primary" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">In-House</h6>
                                        <small class="text-muted">Process done internally</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check form-check-card">
                                <input class="form-check-input" 
                                       type="radio" 
                                       name="process_type" 
                                       id="outsource" 
                                       value="outsource" 
                                       <?= old('process_type', $process['process_type'] ?? 'in_house') == 'outsource' ? 'checked' : '' ?>
                                       onclick="showVendorSection()">
                                <label class="form-check-label card" for="outsource">
                                    <div class="card-body text-center">
                                        <i class="bi bi-truck text-warning" style="font-size: 2rem;"></i>
                                        <h6 class="mt-2">Outsource</h6>
                                        <small class="text-muted">Process done by vendor</small>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vendor Selection (only shows when outsource is selected) -->
                <div id="vendorSection" style="display: none; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; margin: 10px 0; border-radius: 5px;">
                    <div class="mb-4">
                        <label for="vendor_id" class="form-label">Select Vendor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select class="form-select" id="vendor_id" name="vendor_id">
                                <option value="">Choose a vendor...</option>
                                <?php if (!empty($vendors)): ?>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= $vendor['id'] ?>" 
                                                <?= old('vendor_id', $process['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                            <?= esc($vendor['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option disabled>No vendors available</option>
                                <?php endif; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" onclick="showNewVendorModal()">
                                <i class="bi bi-plus-circle"></i> New Vendor
                            </button>
                        </div>
                        <div class="form-text">Select an existing vendor or create a new one.</div>
                    </div>
                </div>
                
                <!-- Debug section -->
                <div id="debugInfo" style="background: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 5px; font-size: 12px;">
                    <strong>Debug Info:</strong>
                    <div>Vendor section display: <span id="vendorDisplay">unknown</span></div>
                    <div>Outsource checked: <span id="outsourceStatus">unknown</span></div>
                    <button type="button" onclick="forceShowVendor()" class="btn btn-sm btn-warning">Force Show Vendor</button>
                    <button type="button" onclick="checkStatus()" class="btn btn-sm btn-info">Check Status</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-gear me-2"></i>
                    Settings
                </h5>
            </div>
            <div class="card-body">
                <!-- Status -->
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1" 
                               <?= old('is_active', $process['is_active'] ?? '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Active Process
                        </label>
                    </div>
                    <div class="form-text">Active processes are available for work orders.</div>
                </div>

                <!-- Save Button -->
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-2"></i>
                        <?= isset($process) ? 'Update Process' : 'Create Process' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<?= form_close() ?>

<!-- New Vendor Modal -->
<div class="modal fade" id="newVendorModal" tabindex="-1" aria-labelledby="newVendorModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="newVendorModalLabel">
                    <i class="bi bi-plus-circle me-2"></i>Create New Vendor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="newVendorForm">
                    <div class="mb-3">
                        <label for="vendorName" class="form-label">Vendor Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="vendorName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="vendorContact" class="form-label">Contact Person</label>
                        <input type="text" class="form-control" id="vendorContact" name="contact_person">
                    </div>
                    <div class="mb-3">
                        <label for="vendorPhone" class="form-label">Phone</label>
                        <input type="text" class="form-control" id="vendorPhone" name="phone">
                    </div>
                    <div class="mb-3">
                        <label for="vendorEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="vendorEmail" name="email">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createVendor()" id="createVendorBtn">Create Vendor</button>
            </div>
        </div>
    </div>
</div>

<script>
// Simple functions to show/hide vendor section
function showVendorSection() {
    console.log('*** SHOWING vendor section ***');
    var vendorSection = document.getElementById('vendorSection');
    var vendorSelect = document.getElementById('vendor_id');
    
    if (vendorSection) {
        vendorSection.style.display = 'block';
        console.log('Vendor section display set to block');
    }
    
    if (vendorSelect) {
        vendorSelect.setAttribute('required', 'required');
    }
    
    updateDebugInfo();
}

function hideVendorSection() {
    console.log('*** HIDING vendor section ***');
    var vendorSection = document.getElementById('vendorSection');
    var vendorSelect = document.getElementById('vendor_id');
    
    if (vendorSection) {
        vendorSection.style.display = 'none';
        console.log('Vendor section display set to none');
    }
    
    if (vendorSelect) {
        vendorSelect.removeAttribute('required');
        vendorSelect.value = '';
    }
    
    updateDebugInfo();
}

// Debug functions
function forceShowVendor() {
    var vendorSection = document.getElementById('vendorSection');
    if (vendorSection) {
        vendorSection.style.display = 'block';
        console.log('Force showed vendor section');
    }
    updateDebugInfo();
}

function checkStatus() {
    var outsourceRadio = document.getElementById('outsource');
    var vendorSection = document.getElementById('vendorSection');
    
    console.log('=== STATUS CHECK ===');
    console.log('Outsource radio exists:', !!outsourceRadio);
    console.log('Outsource radio checked:', outsourceRadio ? outsourceRadio.checked : 'N/A');
    console.log('Vendor section exists:', !!vendorSection);
    console.log('Vendor section display:', vendorSection ? vendorSection.style.display : 'N/A');
    
    updateDebugInfo();
}

function updateDebugInfo() {
    var outsourceRadio = document.getElementById('outsource');
    var vendorSection = document.getElementById('vendorSection');
    var outsourceStatus = document.getElementById('outsourceStatus');
    var vendorDisplay = document.getElementById('vendorDisplay');
    
    if (outsourceStatus) {
        outsourceStatus.textContent = outsourceRadio ? outsourceRadio.checked : 'not found';
    }
    
    if (vendorDisplay) {
        vendorDisplay.textContent = vendorSection ? vendorSection.style.display : 'not found';
    }
}

// Show new vendor modal
function showNewVendorModal() {
    var modal = new bootstrap.Modal(document.getElementById('newVendorModal'));
    modal.show();
}

// Initialize when page loads
window.onload = function() {
    console.log('*** Page loaded, checking initial state ***');
    var outsourceRadio = document.getElementById('outsource');
    
    if (outsourceRadio && outsourceRadio.checked) {
        showVendorSection();
    } else {
        hideVendorSection();
    }
    
    updateDebugInfo();
};
</script>
// Show new vendor modal
function showNewVendorModal() {
    const modal = new bootstrap.Modal(document.getElementById('newVendorModal'));
    modal.show();
}

// Create vendor via AJAX
function createVendor() {
    const form = document.getElementById('newVendorForm');
    const formData = new FormData(form);
    const createBtn = document.getElementById('createVendorBtn');
    
    // Disable button and show loading state
    createBtn.disabled = true;
    createBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Creating...';
    
    fetch('<?= base_url('processes/create-vendor') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new vendor to dropdown
            const vendorSelect = document.getElementById('vendor_id');
            const newOption = document.createElement('option');
            newOption.value = data.vendor.id;
            newOption.textContent = data.vendor.name;
            newOption.selected = true;
            vendorSelect.appendChild(newOption);
            
            // Close modal and reset form
            const modal = bootstrap.Modal.getInstance(document.getElementById('newVendorModal'));
            modal.hide();
            form.reset();
            
            // Show success message
            showAlert('success', data.message);
        } else {
            showAlert('danger', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('danger', 'An error occurred while creating the vendor.');
    })
    .finally(() => {
        // Reset button
        createBtn.disabled = false;
        createBtn.innerHTML = 'Create Vendor';
    });
}

// Show alert message
function showAlert(type, message) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    // Insert at top of main content
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv && alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}

// Debug functions
function forceShowVendor() {
    const vendorSection = document.getElementById('vendorSection');
    vendorSection.style.display = 'block';
    updateDebugInfo();
    console.log('Force showed vendor section');
}

function checkStatus() {
    const outsourceRadio = document.getElementById('outsource');
    const vendorSection = document.getElementById('vendorSection');
    
    console.log('=== STATUS CHECK ===');
    console.log('Outsource radio exists:', !!outsourceRadio);
    console.log('Outsource radio checked:', outsourceRadio ? outsourceRadio.checked : 'N/A');
    console.log('Vendor section exists:', !!vendorSection);
    console.log('Vendor section display:', vendorSection ? vendorSection.style.display : 'N/A');
    
    updateDebugInfo();
}

function updateDebugInfo() {
    const outsourceRadio = document.getElementById('outsource');
    const vendorSection = document.getElementById('vendorSection');
    
    document.getElementById('outsourceStatus').textContent = outsourceRadio ? outsourceRadio.checked : 'not found';
    document.getElementById('vendorDisplay').textContent = vendorSection ? vendorSection.style.display : 'not found';
}

// Toggle vendor selection based on process type
function toggleVendorSection() {
    console.log('🔄 Toggling vendor section...');
    
    const outsourceRadio = document.getElementById('outsource');
    const vendorSection = document.getElementById('vendorSection');
    const vendorSelect = document.getElementById('vendor_id');
    
    if (!outsourceRadio || !vendorSection) {
        console.error('❌ Missing elements:', {
            outsourceRadio: !!outsourceRadio,
            vendorSection: !!vendorSection
        });
        return;
    }
    
    if (outsourceRadio.checked) {
        // Show vendor section for outsource
        vendorSection.style.display = 'block';
        if (vendorSelect) vendorSelect.setAttribute('required', 'required');
        console.log('✅ SHOWING vendor section (outsource selected)');
    } else {
        // Hide vendor section for in-house
        vendorSection.style.display = 'none';
        if (vendorSelect) {
            vendorSelect.removeAttribute('required');
            vendorSelect.value = '';
        }
        console.log('❌ HIDING vendor section (in-house selected)');
    }
    
    updateDebugInfo();
}

// Manual test function for debugging
function testToggle() {
    console.log('Manual test triggered');
    const vendorSection = document.getElementById('vendorSection');
    if (vendorSection.style.display === 'none') {
        vendorSection.style.display = 'block';
        console.log('Manually showing vendor section');
    } else {
        vendorSection.style.display = 'none';
        console.log('Manually hiding vendor section');
    }
}

// Form validation
(function() {
    'use strict';
    window.addEventListener('load', function() {
        var forms = document.getElementsByClassName('needs-validation');
        var validation = Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                if (form.checkValidity() === false) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up process form...');
    
    // Get radio buttons
    const inhouseRadio = document.getElementById('inhouse');
    const outsourceRadio = document.getElementById('outsource');
    
    if (inhouseRadio && outsourceRadio) {
        // Add change event listeners
        inhouseRadio.addEventListener('change', function() {
            console.log('In-house selected');
            toggleVendorSection();
        });
        
        outsourceRadio.addEventListener('change', function() {
            console.log('Outsource selected');
            toggleVendorSection();
        });
        
        console.log('✅ Event listeners added to radio buttons');
        
        // Set initial state
        toggleVendorSection();
    } else {
        console.error('❌ Radio buttons not found');
    }
});
</script>

<style>
.form-check-card .card {
    cursor: pointer;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.form-check-card .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.form-check-card input[type="radio"]:checked + .card {
    border-color: var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.1);
}

.form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}
</style>

<?= $this->endSection() ?>
