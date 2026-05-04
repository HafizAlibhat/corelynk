<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">
                    <i class="bi bi-diagram-3 me-2"></i>
                    <?= isset($process) ? 'Edit Process' : 'Create Process' ?>
                </h2>
                <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-2"></i>
                    Back to Processes
                </a>
            </div>
        </div>
    </div>

    <?= form_open(isset($process) ? base_url('/processes/' . $process['id'] . '/update') : base_url('/processes/store'), 
        ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'processForm']) ?>
    <?= csrf_field() ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Process Information</h5>
                </div>
                <div class="card-body">
                    <!-- Process Name -->
                    <div class="mb-3">
                        <label for="name" class="form-label">Process Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control" 
                               id="name" 
                               name="name" 
                               value="<?= old('name', $process['name'] ?? '') ?>" 
                               placeholder="e.g. Material Preparation, Assembly, Quality Check"
                               required>
                        <div class="invalid-feedback">
                            Please provide a process name.
                        </div>
                    </div>

                    <!-- Product Selection -->
                    <div class="mb-3">
                        <label for="product_id" class="form-label">Product <span class="text-danger">*</span></label>
                        <select class="form-select" id="product_id" name="product_id" required>
                            <option value="">Select Product</option>
                            <?php if (isset($products)): ?>
                                <?php foreach ($products as $product): ?>
                                    <option value="<?= $product['id'] ?>" 
                                            <?= old('product_id', $process['product_id'] ?? '') == $product['id'] ? 'selected' : '' ?>>
                                        <?= esc($product['name']) ?> (<?= esc($product['code']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a product.
                        </div>
                    </div>

                    <!-- Sequence Order -->
                    <div class="mb-3">
                        <label for="sequence_order" class="form-label">Sequence Order <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="sequence_order" 
                               name="sequence_order" 
                               value="<?= old('sequence_order', $process['sequence_order'] ?? 1) ?>" 
                               min="1"
                               required>
                        <div class="form-text">Order in which this process should be performed (1, 2, 3...)</div>
                        <div class="invalid-feedback">
                            Please provide a valid sequence order.
                        </div>
                    </div>

                    <!-- Process Type -->
                    <div class="mb-3">
                        <label class="form-label">Process Type <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="is_vendor_process" 
                                           id="inhouse" 
                                           value="0" 
                                           <?= old('is_vendor_process', $process['is_vendor_process'] ?? 0) == 0 ? 'checked' : '' ?>
                                           onchange="toggleVendorSelection()">
                                    <label class="form-check-label" for="inhouse">
                                        <i class="bi bi-house me-2"></i>In-House
                                        <small class="d-block text-muted">Process done internally</small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="radio" 
                                           name="is_vendor_process" 
                                           id="outsource" 
                                           value="1" 
                                           <?= old('is_vendor_process', $process['is_vendor_process'] ?? 0) == 1 ? 'checked' : '' ?>
                                           onchange="toggleVendorSelection()">
                                    <label class="form-check-label" for="outsource">
                                        <i class="bi bi-building me-2"></i>Outsourced
                                        <small class="d-block text-muted">Process done by vendor</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vendor Selection (shown only when outsourced) -->
                    <div class="mb-3" id="vendorSelection" style="display: none;">
                        <label for="vendor_id" class="form-label">Select Vendor <span class="text-danger">*</span></label>
                        <select class="form-select" id="vendor_id" name="vendor_id">
                            <option value="">Choose Vendor</option>
                            <?php if (isset($vendors)): ?>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?= $vendor['id'] ?>" 
                                            <?= old('vendor_id', $process['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= esc($vendor['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a vendor for outsourced process.
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" 
                                  id="description" 
                                  name="description" 
                                  rows="3"
                                  placeholder="Describe what this process involves..."><?= old('description', $process['description'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Status -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Status</h5>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1"
                               <?= old('is_active', $process['is_active'] ?? 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Active Process
                        </label>
                    </div>
                </div>
            </div>

            <!-- Optional Settings -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="card-title mb-0">Optional Settings</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="standard_time_minutes" class="form-label">Standard Time (minutes)</label>
                        <input type="number" 
                               class="form-control" 
                               id="standard_time_minutes" 
                               name="standard_time_minutes" 
                               value="<?= old('standard_time_minutes', $process['standard_time_minutes'] ?? '') ?>" 
                               min="1"
                               placeholder="e.g. 60">
                        <div class="form-text">Expected time to complete this process</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submit Button -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i>Cancel
                        </a>
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
</div>

<script>
// Toggle vendor selection based on process type
function toggleVendorSelection() {
    const isOutsourced = document.getElementById('outsource').checked;
    const vendorSelection = document.getElementById('vendorSelection');
    const vendorSelect = document.getElementById('vendor_id');
    
    if (isOutsourced) {
        vendorSelection.style.display = 'block';
        vendorSelect.required = true;
    } else {
        vendorSelection.style.display = 'none';
        vendorSelect.required = false;
        vendorSelect.value = '';
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    toggleVendorSelection();
});

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
</script>

<?= $this->endSection() ?>
