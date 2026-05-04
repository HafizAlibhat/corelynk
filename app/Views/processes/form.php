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

<?php if (!empty($processError)): ?>
    <div class="alert alert-warning">
        <strong>Notice:</strong> <?= esc($processError) ?>
    </div>
<?php endif; ?>

<?= form_open(isset($process) ? base_url('/processes/' . $process['id'] . '/edit') : base_url('/processes/store'), 
    ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'processForm']) ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Process Information
                </h5>
            </div>
            <div class="card-body">
                <?php if (session()->getFlashdata('error')): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= session()->getFlashdata('error') ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($validation) && $validation->getErrors()): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Please fix the following errors:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($validation->getErrors() as $error): ?>
                                <li><?= esc($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="name" class="form-label">Process Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= (isset($validation) && $validation->hasError('name')) ? 'is-invalid' : '' ?>" 
                                   id="name" name="name" value="<?= old('name', $process['name'] ?? '') ?>" required>
                            <div class="invalid-feedback">
                                <?= $validation->getError('name') ?? 'Process name is required.' ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="standard_time_minutes" class="form-label">Standard Time (minutes)</label>
                            <input type="number" class="form-control" id="standard_time_minutes" name="standard_time_minutes" 
                                   value="<?= old('standard_time_minutes', $process['standard_time_minutes'] ?? '0') ?>" min="0">
                            <div class="form-text">Estimated time required for this process</div>
                        </div>
                    </div>
                </div>

                <!-- In-House Responsibility (shown only when In-House selected) -->
                <div class="mb-3" id="responsibility_section" style="display:none;">
                    <label class="form-label">Responsibility</label>
                    <div class="d-flex flex-column gap-2">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="responsibility_mode" id="resp_department" value="department" <?= old('responsibility_mode', $process['responsibility_mode'] ?? 'employees')==='department'?'checked':'' ?>>
                            <label class="form-check-label" for="resp_department"><i class="bi bi-collection me-1"></i>Department</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="responsibility_mode" id="resp_employees" value="employees" <?= old('responsibility_mode', $process['responsibility_mode'] ?? 'employees')==='employees'?'checked':'' ?>>
                            <label class="form-check-label" for="resp_employees"><i class="bi bi-people me-1"></i>Specific Employees</label>
                        </div>
                    </div>
                    <div id="department_select_wrapper" class="mt-2" style="display:none;">
                        <select name="responsibility_department" class="form-select">
                            <option value="">Select department…</option>
                            <?php foreach (($departments ?? []) as $dept): ?>
                                <option value="<?= esc($dept) ?>" <?= old('responsibility_department', $process['responsibility_department'] ?? '')===$dept?'selected':'' ?>><?= esc($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">All employees in the selected department are considered responsible.</div>
                    </div>
                    <div id="employee_select_wrapper" class="mt-2" style="display:none;">
                        <div id="employee_rows" class="d-flex flex-column gap-2"></div>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addEmployeeRow()"><i class="bi bi-plus"></i> Add Employee</button>
                        <div class="form-text">Select individual employees responsible for this process.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= old('description', $process['description'] ?? '') ?></textarea>
                    <div class="form-text">Detailed description of the process</div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="category_id" class="form-label">Category</label>
                            <div class="input-group">
                                <select class="form-select" id="category_id" name="category_id">
                                    <option value="">Select a category (optional)</option>
                                    <?php if (!empty($categories)): ?>
                                        <?php foreach ($categories as $categoryId => $categoryName): ?>
                                            <option value="<?= $categoryId ?>" 
                                                    <?= old('category_id', $process['category_id'] ?? '') == $categoryId ? 'selected' : '' ?>>
                                                <?= esc($categoryName) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                                <button type="button" class="btn btn-outline-primary" onclick="showNewCategoryModal()">
                                    <i class="bi bi-plus-circle"></i> New Category
                                </button>
                            </div>
                            <div class="form-text">Categorize this process for better organization.</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label class="form-label">Process Type</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="process_type" id="in_house" value="in_house" 
                                       <?= old('process_type', (!isset($process) || !$process['is_vendor_process']) ? 'in_house' : '') == 'in_house' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="in_house">
                                    <i class="bi bi-house me-1"></i>In-House Process
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="process_type" id="outsource" value="outsource"
                                       <?= old('process_type', (isset($process) && $process['is_vendor_process']) ? 'outsource' : '') == 'outsource' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="outsource">
                                    <i class="bi bi-building me-1"></i>Outsourced to Vendor
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vendor Selection (shown only for outsourced processes) -->
                <div class="mb-3" id="vendor_selection" style="display: none;">
                    <a id="vendor_section"></a>
                    <label class="form-label">Approved Vendors <span class="text-danger">*</span></label>
                    <div id="vendor_rows" class="d-flex flex-column gap-2"></div>
                    <div class="mt-2 d-flex gap-2">
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addVendorRow()">
                            <i class="bi bi-plus"></i> Add Vendor Row
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="showNewVendorModal()">
                            <i class="bi bi-plus-circle"></i> New Vendor
                        </button>
                    </div>
                    <div class="form-text mt-1">Add one vendor per row. The first row will be treated as the default vendor for this process.</div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-gear me-2"></i>Process Settings
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1"
                               <?= old('is_active', $process['is_active'] ?? '1') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_active">
                            Active Process
                        </label>
                    </div>
                    <div class="form-text">Inactive processes won't appear in selection lists</div>
                </div>

                <div class="mb-3">
                    <!-- QC Checklist removed as per requirement -->
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= isset($process) ? 'Update Process' : 'Create Process' ?>
                    </button>
                    <a href="<?= base_url('/processes') ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?= form_close() ?>

<!-- New Vendor Modal -->
<div class="modal fade" id="newVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Create New Vendor
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newVendorForm">
                    <?= csrf_field() ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="vendorName" class="form-label">Vendor Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="vendorName" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="vendorContactPerson" class="form-label">Contact Person</label>
                                <input type="text" class="form-control" id="vendorContactPerson" name="contact_person">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="vendorPhone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="vendorPhone" name="phone">
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="vendorEmail" class="form-label">Email</label>
                        <input type="email" class="form-control" id="vendorEmail" name="email">
                    </div>
                    <div class="mb-3">
                        <label for="vendorAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="vendorAddress" name="address" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createVendor()">Create Vendor</button>
            </div>
        </div>
    </div>
</div>

<!-- New Category Modal -->
<div class="modal fade" id="newCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Create New Process Category
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newCategoryForm">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required
                               placeholder="e.g., Machining, Assembly, Quality Control">
                    </div>
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="2"
                                  placeholder="Brief description of this category..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="createCategory()">Create Category</button>
            </div>
        </div>
    </div>
</div>

<script>
// Show/hide vendor selection based on process type
document.addEventListener('DOMContentLoaded', function() {
    const inHouseRadio = document.getElementById('in_house');
    const outsourceRadio = document.getElementById('outsource');
    const vendorSelection = document.getElementById('vendor_selection');
    const vendorRows = document.getElementById('vendor_rows');
    const responsibilitySection = document.getElementById('responsibility_section');
    const respDept = document.getElementById('resp_department');
    const respEmps = document.getElementById('resp_employees');
    const deptWrapper = document.getElementById('department_select_wrapper');
    const empWrapper = document.getElementById('employee_select_wrapper');
    const employeeRows = document.getElementById('employee_rows');

    function toggleVendorSelection() {
        if (outsourceRadio.checked) {
            vendorSelection.style.display = 'block';
            responsibilitySection.style.display = 'none';
        } else {
            vendorSelection.style.display = 'none';
            // clear rows
            vendorRows.innerHTML = '';
            responsibilitySection.style.display = 'block';
            toggleResponsibilityMode();
        }
    }

    function toggleResponsibilityMode(){
        const mode = respDept.checked ? 'department' : 'employees';
        deptWrapper.style.display = (mode === 'department') ? 'block' : 'none';
        empWrapper.style.display = (mode === 'employees') ? 'block' : 'none';
        if (mode === 'employees' && employeeRows.childElementCount === 0) { addEmployeeRow(''); }
    }

    inHouseRadio.addEventListener('change', toggleVendorSelection);
    outsourceRadio.addEventListener('change', toggleVendorSelection);
    respDept.addEventListener('change', toggleResponsibilityMode);
    respEmps.addEventListener('change', toggleResponsibilityMode);

    // Initial state
    toggleVendorSelection();
});

// Show new vendor modal
function showNewVendorModal() {
    const modal = new bootstrap.Modal(document.getElementById('newVendorModal'));
    modal.show();
}

// Utilities to manage vendor rows
function vendorSelectHtml(selectedId) {
    const options = [
        <?php foreach (($vendors ?? []) as $v): ?>
        { id: <?= (int)$v['id'] ?>, text: '<?= addslashes($v['name'] . (!empty($v['contact_person']) ? ' - ' . $v['contact_person'] : '')) ?>' },
        <?php endforeach; ?>
    ];
    const opts = options.map(o => `<option value="${o.id}" ${selectedId == o.id ? 'selected' : ''}>${o.text}</option>`).join('');
    return `<select class="form-select" name="vendor_ids[]"><option value="">-- select vendor --</option>${opts}</select>`;
}

function addVendorRow(selectedId) {
    const container = document.getElementById('vendor_rows') || vendorRows;
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center';
    row.innerHTML = `${vendorSelectHtml(selectedId||'')}<button type=\"button\" class=\"btn btn-outline-danger btn-sm\" onclick=\"this.parentElement.remove()\"><i class=\"bi bi-x\"></i></button>`;
    container.appendChild(row);
}

// Employee selection helpers
function employeeSelectHtml(selectedId) {
    const options = [
        <?php foreach (($employees ?? []) as $e): ?>
        { id: <?= (int)$e['id'] ?>, text: '<?= addslashes($e['first_name'].' '.$e['last_name'].(!empty($e['department'])?' ('.$e['department'].')':'')) ?>' },
        <?php endforeach; ?>
    ];
    const opts = options.map(o => `<option value="${o.id}" ${selectedId == o.id ? 'selected' : ''}>${o.text}</option>`).join('');
    return `<select class="form-select" name="employee_ids[]"><option value="">-- select employee --</option>${opts}</select>`;
}
function addEmployeeRow(selectedId) {
    const container = document.getElementById('employee_rows');
    const row = document.createElement('div');
    row.className = 'd-flex gap-2 align-items-center';
    row.innerHTML = `${employeeSelectHtml(selectedId||'')}<button type=\"button\" class=\"btn btn-outline-danger btn-sm\" onclick=\"this.parentElement.remove()\"><i class=\"bi bi-x\"></i></button>`;
    container.appendChild(row);
}

// Create new vendor
function createVendor() {
    const form = document.getElementById('newVendorForm');
    const formData = new FormData(form);

    fetch('<?= base_url('/processes/create-vendor') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Add new vendor to select
            // add newly created vendor as a new row preselected
            addVendorRow(data.vendor.id);
            
            // Close modal and reset form
            bootstrap.Modal.getInstance(document.getElementById('newVendorModal')).hide();
            form.reset();
            
            // Show success message
            showAlert('Vendor created successfully!', 'success');
        } else {
            showAlert(data.message || 'Failed to create vendor', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while creating the vendor', 'error');
    });
}

// Show new category modal
function showNewCategoryModal() {
    const modal = new bootstrap.Modal(document.getElementById('newCategoryModal'));
    modal.show();
}

// Create new category
function createCategory() {
    const form = document.getElementById('newCategoryForm');
    const formData = new FormData(form);

    fetch('<?= base_url('/process-categories/store') ?>', {
        method: 'POST',
        body: formData,
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(async response => {
        // Try to parse JSON; fall back to text
        let data;
        try {
            data = await response.json();
        } catch (e) {
            const text = await response.text();
            console.error('Non-JSON response from server:', text);
            showAlert('Server error occurred while creating the category', 'error');
            return;
        }

        if (data && data.success) {
            // Add new category to select if data provided
            const categorySelect = document.getElementById('category_id');
            if (data.category && categorySelect) {
                const option = new Option(data.category.name, data.category.id, true, true);
                try { categorySelect.add(option); } catch (e) { console.warn(e); }
            }

            // Close modal and reset form
            const modalEl = document.getElementById('newCategoryModal');
            if (modalEl) bootstrap.Modal.getInstance(modalEl)?.hide();
            form.reset();

            // Show success message
            showAlert(data.message || 'Category created successfully!', 'success');
        } else {
            showAlert((data && data.message) ? data.message : 'Failed to create category', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showAlert('An error occurred while creating the category', 'error');
    });
}

// Utility function to show alerts
function showAlert(message, type) {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Insert alert at the top of the form
    const form = document.getElementById('processForm');
    form.insertAdjacentHTML('afterbegin', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = form.querySelector('.alert');
        if (alert) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    }, 5000);
}

// Form validation: ensure at least one vendor row selected for outsource
(function() {
    'use strict';
    window.addEventListener('load', function() {
        const forms = document.getElementsByClassName('needs-validation');
        Array.prototype.filter.call(forms, function(form) {
            form.addEventListener('submit', function(event) {
                const outsourceChecked = document.getElementById('outsource').checked;
                let vendorOk = true;
                if (outsourceChecked) {
                    const selects = vendorRows.querySelectorAll('select[name="vendor_ids[]"]');
                    vendorOk = Array.from(selects).some(s => s.value && parseInt(s.value,10) > 0);
                    if (!vendorOk) {
                        showAlert('Select at least one vendor for outsourced process', 'error');
                    }
                }
                if (form.checkValidity() === false || !vendorOk) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }, false);
})();

// Pre-populate vendor rows from server data
document.addEventListener('DOMContentLoaded', function(){
    try {
        const ids = <?= json_encode(array_values(old('vendor_ids', $approvedVendorIds ?? []))) ?>;
        if (Array.isArray(ids) && ids.length) {
            ids.forEach(id => addVendorRow(id));
        } else {
            // start with one empty row to prompt selection when outsourced
            addVendorRow('');
        }
        // Pre-populate employee assignments
        const empIds = <?= json_encode(array_values(old('employee_ids', $assignedEmployeeIds ?? []))) ?>;
        if (Array.isArray(empIds) && empIds.length) {
            empIds.forEach(eid => addEmployeeRow(eid));
        }
        // Ensure responsibility mode visibility on initial load
        const modeRadio = document.querySelector('input[name="responsibility_mode"]:checked');
        if (modeRadio && document.getElementById('in_house').checked) {
            if (modeRadio.value === 'employees' && (empIds.length === 0)) { addEmployeeRow(''); }
        }
    } catch(e) { /* no-op */ }
});
</script>
<?= $this->endSection() ?>
