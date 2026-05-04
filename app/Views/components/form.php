<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-box me-2"></i>
                <?= isset($component) ? 'Edit Component' : 'New Component' ?>
            </h2>
            <a href="<?= base_url('/components') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to Components
            </a>
        </div>
    </div>
</div>

<?= form_open_multipart('', ['class' => 'needs-validation', 'novalidate' => true]) ?>
    <div class="row">
        <!-- Main Information -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pb-0">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        Basic Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Component Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       class="form-control <?= session('errors.name') ? 'is-invalid' : '' ?>" 
                                       id="name" 
                                       name="name" 
                                       value="<?= old('name', $component['name'] ?? '') ?>" 
                                       required>
                                <div class="invalid-feedback">
                                    <?= session('errors.name') ?? 'Please provide a component name.' ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="sku" class="form-label">SKU <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="text" 
                                           class="form-control <?= session('errors.sku') ? 'is-invalid' : '' ?>" 
                                           id="sku" 
                                           name="sku" 
                                           value="<?= old('sku', $component['sku'] ?? '') ?>" 
                                           required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generateSKU()">
                                        <i class="bi bi-magic"></i>
                                    </button>
                                    <div class="invalid-feedback">
                                        <?= session('errors.sku') ?? 'Please provide a SKU.' ?>
                                    </div>
                                </div>
                                <div class="form-text">Unique identifier for this component</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control <?= session('errors.description') ? 'is-invalid' : '' ?>" 
                                  id="description" 
                                  name="description" 
                                  rows="3"><?= old('description', $component['description'] ?? '') ?></textarea>
                        <div class="invalid-feedback">
                            <?= session('errors.description') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category & Specifications -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pb-0">
                    <h5 class="mb-0">
                        <i class="bi bi-tags me-2"></i>
                        Category & Specifications
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select <?= session('errors.category') ? 'is-invalid' : '' ?>" 
                                        id="category" 
                                        name="category" 
                                        required>
                                    <option value="">Select Category</option>
                                    <option value="Raw Materials" <?= old('category', $component['category'] ?? '') == 'Raw Materials' ? 'selected' : '' ?>>Raw Materials</option>
                                    <option value="Components" <?= old('category', $component['category'] ?? '') == 'Components' ? 'selected' : '' ?>>Components</option>
                                    <option value="Fasteners" <?= old('category', $component['category'] ?? '') == 'Fasteners' ? 'selected' : '' ?>>Fasteners</option>
                                    <option value="Electronics" <?= old('category', $component['category'] ?? '') == 'Electronics' ? 'selected' : '' ?>>Electronics</option>
                                    <option value="Hardware" <?= old('category', $component['category'] ?? '') == 'Hardware' ? 'selected' : '' ?>>Hardware</option>
                                    <option value="Packaging" <?= old('category', $component['category'] ?? '') == 'Packaging' ? 'selected' : '' ?>>Packaging</option>
                                    <option value="Tools" <?= old('category', $component['category'] ?? '') == 'Tools' ? 'selected' : '' ?>>Tools</option>
                                    <option value="Consumables" <?= old('category', $component['category'] ?? '') == 'Consumables' ? 'selected' : '' ?>>Consumables</option>
                                    <option value="Other" <?= old('category', $component['category'] ?? '') == 'Other' ? 'selected' : '' ?>>Other</option>
                                </select>
                                <div class="invalid-feedback">
                                    <?= session('errors.category') ?? 'Please select a category.' ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="uom" class="form-label">Unit of Measure <span class="text-danger">*</span></label>
                                <select class="form-select <?= session('errors.uom') ? 'is-invalid' : '' ?>" 
                                        id="uom" 
                                        name="uom" 
                                        required>
                                    <option value="">Select UOM</option>
                                    <option value="pcs" <?= old('uom', $component['uom'] ?? '') == 'pcs' ? 'selected' : '' ?>>Pieces (pcs)</option>
                                    <option value="kg" <?= old('uom', $component['uom'] ?? '') == 'kg' ? 'selected' : '' ?>>Kilograms (kg)</option>
                                    <option value="g" <?= old('uom', $component['uom'] ?? '') == 'g' ? 'selected' : '' ?>>Grams (g)</option>
                                    <option value="m" <?= old('uom', $component['uom'] ?? '') == 'm' ? 'selected' : '' ?>>Meters (m)</option>
                                    <option value="cm" <?= old('uom', $component['uom'] ?? '') == 'cm' ? 'selected' : '' ?>>Centimeters (cm)</option>
                                    <option value="mm" <?= old('uom', $component['uom'] ?? '') == 'mm' ? 'selected' : '' ?>>Millimeters (mm)</option>
                                    <option value="l" <?= old('uom', $component['uom'] ?? '') == 'l' ? 'selected' : '' ?>>Liters (l)</option>
                                    <option value="ml" <?= old('uom', $component['uom'] ?? '') == 'ml' ? 'selected' : '' ?>>Milliliters (ml)</option>
                                    <option value="box" <?= old('uom', $component['uom'] ?? '') == 'box' ? 'selected' : '' ?>>Box</option>
                                    <option value="pack" <?= old('uom', $component['uom'] ?? '') == 'pack' ? 'selected' : '' ?>>Pack</option>
                                    <option value="roll" <?= old('uom', $component['uom'] ?? '') == 'roll' ? 'selected' : '' ?>>Roll</option>
                                    <option value="sheet" <?= old('uom', $component['uom'] ?? '') == 'sheet' ? 'selected' : '' ?>>Sheet</option>
                                </select>
                                <div class="invalid-feedback">
                                    <?= session('errors.uom') ?? 'Please select a unit of measure.' ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Dynamic Specifications -->
                    <div class="mb-3">
                        <label class="form-label">Specifications</label>
                        <div id="specifications-container">
                            <?php 
                            $specs = old('specifications', 
                                isset($component['specifications']) ? 
                                (is_string($component['specifications']) ? 
                                    json_decode($component['specifications'], true) : 
                                    $component['specifications']) : 
                                []
                            );
                            if (empty($specs)) {
                                $specs = [['name' => '', 'value' => '']];
                            }
                            ?>
                            <?php foreach ($specs as $index => $spec): ?>
                                <div class="specification-row mb-2">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="specifications[<?= $index ?>][name]" 
                                                   placeholder="Specification name"
                                                   value="<?= esc($spec['name']) ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <input type="text" 
                                                   class="form-control" 
                                                   name="specifications[<?= $index ?>][value]" 
                                                   placeholder="Value"
                                                   value="<?= esc($spec['value']) ?>">
                                        </div>
                                        <div class="col-md-2">
                                            <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeSpecification(this)">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addSpecification()">
                            <i class="bi bi-plus me-1"></i>Add Specification
                        </button>
                    </div>
                </div>
            </div>

            <!-- Inventory Settings -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pb-0">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Inventory Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="current_stock" class="form-label">Current Stock</label>
                                <input type="number" 
                                       class="form-control <?= session('errors.current_stock') ? 'is-invalid' : '' ?>" 
                                       id="current_stock" 
                                       name="current_stock" 
                                       value="<?= old('current_stock', $component['current_stock'] ?? '0') ?>" 
                                       step="0.01" 
                                       min="0">
                                <div class="invalid-feedback">
                                    <?= session('errors.current_stock') ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="reorder_level" class="form-label">Reorder Level</label>
                                <input type="number" 
                                       class="form-control <?= session('errors.reorder_level') ? 'is-invalid' : '' ?>" 
                                       id="reorder_level" 
                                       name="reorder_level" 
                                       value="<?= old('reorder_level', $component['reorder_level'] ?? '0') ?>" 
                                       step="0.01" 
                                       min="0">
                                <div class="invalid-feedback">
                                    <?= session('errors.reorder_level') ?>
                                </div>
                                <div class="form-text">Minimum stock level before reorder alert</div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="max_stock" class="form-label">Maximum Stock</label>
                                <input type="number" 
                                       class="form-control <?= session('errors.max_stock') ? 'is-invalid' : '' ?>" 
                                       id="max_stock" 
                                       name="max_stock" 
                                       value="<?= old('max_stock', $component['max_stock'] ?? '') ?>" 
                                       step="0.01" 
                                       min="0">
                                <div class="invalid-feedback">
                                    <?= session('errors.max_stock') ?>
                                </div>
                                <div class="form-text">Maximum storage capacity</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="unit_cost" class="form-label">Unit Cost ($)</label>
                                <input type="number" 
                                       class="form-control <?= session('errors.unit_cost') ? 'is-invalid' : '' ?>" 
                                       id="unit_cost" 
                                       name="unit_cost" 
                                       value="<?= old('unit_cost', $component['unit_cost'] ?? '') ?>" 
                                       step="0.01" 
                                       min="0">
                                <div class="invalid-feedback">
                                    <?= session('errors.unit_cost') ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="location" class="form-label">Storage Location</label>
                                <input type="text" 
                                       class="form-control <?= session('errors.location') ? 'is-invalid' : '' ?>" 
                                       id="location" 
                                       name="location" 
                                       value="<?= old('location', $component['location'] ?? '') ?>" 
                                       placeholder="e.g. Warehouse A, Shelf 12-B">
                                <div class="invalid-feedback">
                                    <?= session('errors.location') ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Side Panel -->
        <div class="col-lg-4">
            <!-- Status & Vendor -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pb-0">
                    <h5 class="mb-0">
                        <i class="bi bi-gear me-2"></i>
                        Settings
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select <?= session('errors.status') ? 'is-invalid' : '' ?>" 
                                id="status" 
                                name="status">
                            <option value="active" <?= old('status', $component['status'] ?? 'active') == 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= old('status', $component['status'] ?? 'active') == 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="discontinued" <?= old('status', $component['status'] ?? 'active') == 'discontinued' ? 'selected' : '' ?>>Discontinued</option>
                        </select>
                        <div class="invalid-feedback">
                            <?= session('errors.status') ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="vendor_id" class="form-label">Primary Vendor</label>
                        <select class="form-select <?= session('errors.vendor_id') ? 'is-invalid' : '' ?>" 
                                id="vendor_id" 
                                name="vendor_id">
                            <option value="">Select Vendor</option>
                            <?php if (isset($vendors)): ?>
                                <?php foreach ($vendors as $vendor): ?>
                                    <option value="<?= $vendor['id'] ?>" <?= old('vendor_id', $component['vendor_id'] ?? '') == $vendor['id'] ? 'selected' : '' ?>>
                                        <?= esc($vendor['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">
                            <?= session('errors.vendor_id') ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lead_time" class="form-label">Lead Time (days)</label>
                        <input type="number" 
                               class="form-control <?= session('errors.lead_time') ? 'is-invalid' : '' ?>" 
                               id="lead_time" 
                               name="lead_time" 
                               value="<?= old('lead_time', $component['lead_time'] ?? '') ?>" 
                               min="0">
                        <div class="invalid-feedback">
                            <?= session('errors.lead_time') ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image Upload -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom-0 pb-0">
                    <h5 class="mb-0">
                        <i class="bi bi-image me-2"></i>
                        Component Image
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <input type="file" 
                               class="form-control <?= session('errors.image') ? 'is-invalid' : '' ?>" 
                               id="image" 
                               name="image" 
                               accept="image/*"
                               onchange="previewImage(this)">
                        <div class="invalid-feedback">
                            <?= session('errors.image') ?>
                        </div>
                        <div class="form-text">Accepted: JPG, PNG, GIF (max 2MB)</div>
                    </div>
                    
                    <div id="imagePreview" class="text-center">
                        <?php if (isset($component['image']) && !empty($component['image'])): ?>
                            <img src="<?= base_url('/uploads/components/' . $component['image']) ?>" 
                                 alt="Component Image" 
                                 class="img-fluid rounded" 
                                 style="max-height: 200px;">
                        <?php else: ?>
                            <div class="text-muted">
                                <i class="bi bi-image fs-1"></i>
                                <p class="mb-0">No image uploaded</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom-0 pb-0">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="fillSampleData()">
                            <i class="bi bi-magic me-1"></i>Fill Sample Data
                        </button>
                        
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearForm()">
                            <i class="bi bi-arrow-clockwise me-1"></i>Reset Form
                        </button>
                        
                        <?php if (isset($component)): ?>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="duplicateComponent()">
                                <i class="bi bi-files me-1"></i>Duplicate Component
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="<?= base_url('/components') ?>" class="btn btn-light">
                                <i class="bi bi-x-circle me-2"></i>
                                Cancel
                            </a>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="save_continue" class="btn btn-success">
                                <i class="bi bi-check-circle me-2"></i>
                                Save & Continue Editing
                            </button>
                            
                            <button type="submit" name="action" value="save" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>
                                <?= isset($component) ? 'Update Component' : 'Create Component' ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= form_close() ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
let specIndex = <?= count($specs ?? []) ?>;

// Generate SKU
function generateSKU() {
    const name = document.getElementById('name').value;
    const category = document.getElementById('category').value;
    
    if (!name || !category) {
        alert('Please fill in component name and category first');
        return;
    }
    
    const namePrefix = name.substring(0, 3).toUpperCase();
    const categoryPrefix = category.substring(0, 3).toUpperCase();
    const timestamp = Date.now().toString().slice(-4);
    
    const sku = `${categoryPrefix}-${namePrefix}-${timestamp}`;
    document.getElementById('sku').value = sku;
}

// Add specification
function addSpecification() {
    const container = document.getElementById('specifications-container');
    const newRow = document.createElement('div');
    newRow.className = 'specification-row mb-2';
    newRow.innerHTML = `
        <div class="row">
            <div class="col-md-4">
                <input type="text" 
                       class="form-control" 
                       name="specifications[${specIndex}][name]" 
                       placeholder="Specification name">
            </div>
            <div class="col-md-6">
                <input type="text" 
                       class="form-control" 
                       name="specifications[${specIndex}][value]" 
                       placeholder="Value">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeSpecification(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    `;
    container.appendChild(newRow);
    specIndex++;
}

// Remove specification
function removeSpecification(button) {
    const row = button.closest('.specification-row');
    row.remove();
}

// Preview image
function previewImage(input) {
    const preview = document.getElementById('imagePreview');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.innerHTML = `
                <img src="${e.target.result}" 
                     alt="Preview" 
                     class="img-fluid rounded" 
                     style="max-height: 200px;">
            `;
        };
        
        reader.readAsDataURL(input.files[0]);
    }
}

// Fill sample data
function fillSampleData() {
    document.getElementById('name').value = 'Steel Bolt M8x30';
    document.getElementById('sku').value = 'FAS-STE-1234';
    document.getElementById('description').value = 'High-grade steel bolt, M8 thread, 30mm length. Zinc plated for corrosion resistance.';
    document.getElementById('category').value = 'Fasteners';
    document.getElementById('uom').value = 'pcs';
    document.getElementById('current_stock').value = '500';
    document.getElementById('reorder_level').value = '100';
    document.getElementById('max_stock').value = '2000';
    document.getElementById('unit_cost').value = '0.25';
    document.getElementById('location').value = 'Warehouse A, Bin 15-C';
    document.getElementById('lead_time').value = '7';
    
    // Add sample specifications
    const container = document.getElementById('specifications-container');
    container.innerHTML = '';
    
    const specs = [
        {name: 'Thread', value: 'M8 x 1.25'},
        {name: 'Length', value: '30mm'},
        {name: 'Material', value: 'Steel Grade 8.8'},
        {name: 'Coating', value: 'Zinc Plated'}
    ];
    
    specs.forEach((spec, index) => {
        const newRow = document.createElement('div');
        newRow.className = 'specification-row mb-2';
        newRow.innerHTML = `
            <div class="row">
                <div class="col-md-4">
                    <input type="text" 
                           class="form-control" 
                           name="specifications[${index}][name]" 
                           value="${spec.name}">
                </div>
                <div class="col-md-6">
                    <input type="text" 
                           class="form-control" 
                           name="specifications[${index}][value]" 
                           value="${spec.value}">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeSpecification(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(newRow);
    });
    
    specIndex = specs.length;
}

// Clear form
function clearForm() {
    if (confirm('Are you sure you want to clear all form data?')) {
        document.querySelector('form').reset();
        document.getElementById('imagePreview').innerHTML = `
            <div class="text-muted">
                <i class="bi bi-image fs-1"></i>
                <p class="mb-0">No image uploaded</p>
            </div>
        `;
        document.getElementById('specifications-container').innerHTML = `
            <div class="specification-row mb-2">
                <div class="row">
                    <div class="col-md-4">
                        <input type="text" 
                               class="form-control" 
                               name="specifications[0][name]" 
                               placeholder="Specification name">
                    </div>
                    <div class="col-md-6">
                        <input type="text" 
                               class="form-control" 
                               name="specifications[0][value]" 
                               placeholder="Value">
                    </div>
                    <div class="col-md-2">
                        <button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="removeSpecification(this)">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
        specIndex = 1;
    }
}

// Duplicate component
function duplicateComponent() {
    if (confirm('Create a copy of this component?')) {
        // Clear ID and modify name
        const nameField = document.getElementById('name');
        nameField.value = nameField.value + ' (Copy)';
        
        // Clear SKU to force generation of new one
        document.getElementById('sku').value = '';
        
        // Reset stock values
        document.getElementById('current_stock').value = '0';
        
        alert('Component data copied. Please review and save.');
    }
}

// Form validation
(function() {
    'use strict';
    
    // Fetch all forms to apply validation styles to
    var forms = document.querySelectorAll('.needs-validation');
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Auto-calculate inventory value
document.addEventListener('DOMContentLoaded', function() {
    const stockField = document.getElementById('current_stock');
    const costField = document.getElementById('unit_cost');
    
    function updateInventoryValue() {
        const stock = parseFloat(stockField.value) || 0;
        const cost = parseFloat(costField.value) || 0;
        const value = stock * cost;
        
        // You could display this value somewhere if needed
        console.log('Inventory Value:', value.toFixed(2));
    }
    
    stockField.addEventListener('input', updateInventoryValue);
    costField.addEventListener('input', updateInventoryValue);
});
</script>
<?= $this->endSection() ?>
