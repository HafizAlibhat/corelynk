<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0"><?= $page_title ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= base_url('/') ?>">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('/process-templates') ?>">Process Templates</a></li>
                    <li class="breadcrumb-item active"><?= isset($process_template) && $process_template ? 'Edit' : 'Create' ?></li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-gear mr-2"></i>
                            Process Template Information
                        </h3>
                        <div class="card-tools">
                            <a href="<?= base_url('/process-templates') ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i> Back to List
                            </a>
                        </div>
                    </div>

                    <?= form_open(isset($process_template) && $process_template ? '/process-templates/' . $process_template['id'] . '/update' : '/process-templates/store', [
                        'class' => 'needs-validation',
                        'novalidate' => true
                    ]) ?>
                    <?= csrf_field() ?>
                    
                    <?php if (session()->getFlashdata('error')): ?>
                        <div class="alert alert-danger">
                            <?= session()->getFlashdata('error') ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="card-body">
                        <div class="row">
                            <!-- Process Name -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Process Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control <?= $validation->hasError('name') ? 'is-invalid' : '' ?>" 
                                           id="name" 
                                           name="name" 
                                           value="<?= old('name', $process_template['name'] ?? '') ?>"
                                           required>
                                    <?php if ($validation->hasError('name')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('name') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Category -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="category">Category <span class="text-danger">*</span></label>
                                    <select class="form-control <?= $validation->hasError('category') ? 'is-invalid' : '' ?>" 
                                            id="category" 
                                            name="category" 
                                            required>
                                        <option value="">Select Category</option>
                                        <option value="machining" <?= old('category', $process_template['category'] ?? '') == 'machining' ? 'selected' : '' ?>>Machining</option>
                                        <option value="assembly" <?= old('category', $process_template['category'] ?? '') == 'assembly' ? 'selected' : '' ?>>Assembly</option>
                                        <option value="finishing" <?= old('category', $process_template['category'] ?? '') == 'finishing' ? 'selected' : '' ?>>Finishing</option>
                                        <option value="quality" <?= old('category', $process_template['category'] ?? '') == 'quality' ? 'selected' : '' ?>>Quality Control</option>
                                        <option value="packaging" <?= old('category', $process_template['category'] ?? '') == 'packaging' ? 'selected' : '' ?>>Packaging</option>
                                        <option value="testing" <?= old('category', $process_template['category'] ?? '') == 'testing' ? 'selected' : '' ?>>Testing</option>
                                        <option value="general" <?= old('category', $process_template['category'] ?? '') == 'general' ? 'selected' : '' ?>>General</option>
                                    </select>
                                    <?php if ($validation->hasError('category')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('category') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Standard Time -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="standard_time_minutes">Standard Time (minutes)</label>
                                    <input type="number" 
                                           class="form-control <?= $validation->hasError('standard_time_minutes') ? 'is-invalid' : '' ?>" 
                                           id="standard_time_minutes" 
                                           name="standard_time_minutes" 
                                           value="<?= old('standard_time_minutes', $process_template['standard_time_minutes'] ?? '0') ?>"
                                           min="0">
                                    <?php if ($validation->hasError('standard_time_minutes')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('standard_time_minutes') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Process Type -->
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="is_vendor_process">Process Type</label>
                                    <div class="form-check">
                                        <input type="checkbox" 
                                               class="form-check-input" 
                                               id="is_vendor_process" 
                                               name="is_vendor_process" 
                                               value="1"
                                               <?= old('is_vendor_process', $process_template['is_vendor_process'] ?? false) ? 'checked' : '' ?>
                                               onchange="toggleVendorField()">
                                        <label class="form-check-label" for="is_vendor_process">
                                            This is a vendor/outsourced process
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <!-- Vendor Selection -->
                            <div class="col-md-6" id="vendor-field" style="display: <?= old('is_vendor_process', $process_template['is_vendor_process'] ?? false) ? 'block' : 'none' ?>">
                                <div class="form-group">
                                    <label for="vendor_id">Vendor <span class="text-danger">*</span></label>
                                    <select class="form-control <?= $validation->hasError('vendor_id') ? 'is-invalid' : '' ?>" 
                                            id="vendor_id" 
                                            name="vendor_id">
                                        <option value="">Select Vendor</option>
                                        <?php foreach ($vendors as $vendor): ?>
                                            <option value="<?= $vendor['id'] ?>" 
                                                    <?= old('vendor_id', $process_template['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                                <?= esc($vendor['name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($validation->hasError('vendor_id')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('vendor_id') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Status -->
                            <?php if (isset($process_template) && $process_template): ?>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="is_active">Status</label>
                                        <div class="form-check">
                                            <input type="checkbox" 
                                                   class="form-check-input" 
                                                   id="is_active" 
                                                   name="is_active" 
                                                   value="1"
                                                   <?= old('is_active', $process_template['is_active'] ?? true) ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="is_active">
                                                Active
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Description -->
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea class="form-control <?= $validation->hasError('description') ? 'is-invalid' : '' ?>" 
                                              id="description" 
                                              name="description" 
                                              rows="3"
                                              placeholder="Detailed description of the process..."><?= old('description', $process_template['description'] ?? '') ?></textarea>
                                    <?php if ($validation->hasError('description')): ?>
                                        <div class="invalid-feedback">
                                            <?= $validation->getError('description') ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Quality Control Checklist -->
                        <div class="row">
                            <div class="col-12">
                                <div class="form-group">
                                    <label>Quality Control Checklist</label>
                                    <div id="qc-checklist-container">
                                        <?php 
                                        $qcItems = isset($qc_checklist) ? $qc_checklist : [];
                                        if (empty($qcItems)) {
                                            $qcItems = [''];
                                        }
                                        ?>
                                        <?php foreach ($qcItems as $index => $item): ?>
                                            <div class="qc-item mb-2">
                                                <div class="input-group">
                                                    <input type="text" 
                                                           class="form-control" 
                                                           name="qc_checklist[]" 
                                                           value="<?= esc($item) ?>"
                                                           placeholder="Quality check item...">
                                                    <button type="button" class="btn btn-outline-danger" onclick="removeQcItem(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="addQcItem()">
                                        <i class="bi bi-plus me-1"></i> Add Checklist Item
                                    </button>
                                    <small class="form-text text-muted">Add quality control checklist items for this process</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="<?= base_url('/process-templates') ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle me-1"></i>
                                <?= isset($process_template) && $process_template ? 'Update Template' : 'Create Template' ?>
                            </button>
                        </div>
                    </div>
                    <?= form_close() ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleVendorField() {
    const isVendorProcess = document.getElementById('is_vendor_process').checked;
    const vendorField = document.getElementById('vendor-field');
    const vendorSelect = document.getElementById('vendor_id');
    
    if (isVendorProcess) {
        vendorField.style.display = 'block';
        vendorSelect.setAttribute('required', 'required');
    } else {
        vendorField.style.display = 'none';
        vendorSelect.removeAttribute('required');
        vendorSelect.value = '';
    }
}

function addQcItem() {
    const container = document.getElementById('qc-checklist-container');
    const newItem = document.createElement('div');
    newItem.className = 'qc-item mb-2';
    newItem.innerHTML = `
        <div class="input-group">
            <input type="text" 
                   class="form-control" 
                   name="qc_checklist[]" 
                   placeholder="Quality check item...">
            <button type="button" class="btn btn-outline-danger" onclick="removeQcItem(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `;
    container.appendChild(newItem);
}

function removeQcItem(button) {
    const container = document.getElementById('qc-checklist-container');
    const items = container.querySelectorAll('.qc-item');
    
    if (items.length > 1) {
        button.closest('.qc-item').remove();
    } else {
        // Clear the input if it's the last item
        const input = button.closest('.qc-item').querySelector('input');
        input.value = '';
    }
}

// Initialize vendor field visibility
document.addEventListener('DOMContentLoaded', function() {
    toggleVendorField();
});
</script>

<?= $this->endSection() ?>
