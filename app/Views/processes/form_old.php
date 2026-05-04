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
                                       name="is_vendor_process" 
                                       id="inhouse" 
                                       value="0" 
                                       <?= old('is_vendor_process', $process['is_vendor_process'] ?? '0') == '0' ? 'checked' : '' ?>
                                       onchange="toggleVendorSection()">
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
                                       name="is_vendor_process" 
                                       id="outsource" 
                                       value="1" 
                                       <?= old('is_vendor_process', $process['is_vendor_process'] ?? '0') == '1' ? 'checked' : '' ?>
                                       onchange="toggleVendorSection()">
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
                <div id="vendorSection" style="<?= old('is_vendor_process', $process['is_vendor_process'] ?? '0') == '1' ? '' : 'display: none;' ?>">
                    <div class="mb-4">
                        <label for="vendor_id" class="form-label">Select Vendor <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select class="form-select" id="vendor_id" name="vendor_id">
                                <option value="">Choose a vendor...</option>
                                <?php if (!empty($vendors)): ?>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= $vendor['id'] ?>" 
                                                <?= old('vendor_id', $process['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                            <?= esc($vendor['name']) ?> - <?= esc($vendor['type']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary" onclick="showNewVendorModal()">
                                <i class="bi bi-plus-circle"></i> New Vendor
                            </button>
                        </div>
                        <div class="form-text">Select an existing vendor or create a new one.</div>
                    </div>
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

<script>
// Toggle vendor selection based on process type
function toggleVendorSection() {
    const isOutsource = document.getElementById('outsource').checked;
    const vendorSection = document.getElementById('vendorSection');
    const vendorSelect = document.getElementById('vendor_id');
    
    if (isOutsource) {
        vendorSection.style.display = 'block';
        vendorSelect.setAttribute('required', 'required');
    } else {
        vendorSection.style.display = 'none';
        vendorSelect.removeAttribute('required');
        vendorSelect.value = '';
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
    toggleVendorSection();
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
