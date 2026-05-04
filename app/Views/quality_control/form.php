<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-clipboard-check me-2"></i>
                Quality Control Inspection
                <?php if (isset($qc_record)): ?>
                    - <?= esc($qc_record['qc_number']) ?>
                <?php endif; ?>
            </h2>
            <a href="<?= base_url('/quality-control') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>
                Back to QC Records
            </a>
        </div>
    </div>
</div>

<?= form_open(isset($qc_record) ? base_url('/quality-control/' . $qc_record['id'] . '/edit') : base_url('/quality-control/create'), 
    ['class' => 'needs-validation', 'novalidate' => true, 'id' => 'qcForm']) ?>
<?= csrf_field() ?>

<div class="row">
    <!-- Main Inspection Form -->
    <div class="col-xl-8">
        <!-- Basic Information -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>
                    Inspection Details
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="work_order_id" class="form-label">Work Order <span class="text-danger">*</span></label>
                        <select class="form-select" id="work_order_id" name="work_order_id" required onchange="loadWorkOrderDetails()">
                            <option value="">Select Work Order</option>
                            <?php foreach ($work_orders as $wo): ?>
                                <option value="<?= $wo['id'] ?>" 
                                        data-product-id="<?= $wo['product_id'] ?>"
                                        data-product-name="<?= esc($wo['product_name']) ?>"
                                        data-quantity="<?= $wo['quantity_ordered'] ?>"
                                        <?= old('work_order_id', $qc_record['work_order_id'] ?? '') == $wo['id'] ? 'selected' : '' ?>>
                                    <?= esc($wo['wo_number']) ?> - <?= esc($wo['product_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback">
                            Please select a work order.
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="inspection_type" class="form-label">Inspection Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="inspection_type" name="inspection_type" required>
                            <option value="">Select Inspection Type</option>
                            <option value="Incoming" <?= old('inspection_type', $qc_record['inspection_type'] ?? '') == 'Incoming' ? 'selected' : '' ?>>
                                Incoming Inspection
                            </option>
                            <option value="In-Process" <?= old('inspection_type', $qc_record['inspection_type'] ?? '') == 'In-Process' ? 'selected' : '' ?>>
                                In-Process Inspection
                            </option>
                            <option value="Final" <?= old('inspection_type', $qc_record['inspection_type'] ?? '') == 'Final' ? 'selected' : '' ?>>
                                Final Inspection
                            </option>
                            <option value="Pre-Shipment" <?= old('inspection_type', $qc_record['inspection_type'] ?? '') == 'Pre-Shipment' ? 'selected' : '' ?>>
                                Pre-Shipment Inspection
                            </option>
                        </select>
                        <div class="invalid-feedback">
                            Please select an inspection type.
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="quantity_inspected" class="form-label">Quantity Inspected <span class="text-danger">*</span></label>
                        <input type="number" 
                               class="form-control" 
                               id="quantity_inspected" 
                               name="quantity_inspected" 
                               value="<?= old('quantity_inspected', $qc_record['quantity_inspected'] ?? '') ?>" 
                               min="1" 
                               required>
                        <div class="invalid-feedback">
                            Please provide quantity inspected.
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label for="inspection_date" class="form-label">Inspection Date <span class="text-danger">*</span></label>
                        <input type="datetime-local" 
                               class="form-control" 
                               id="inspection_date" 
                               name="inspection_date" 
                               value="<?= old('inspection_date', isset($qc_record['inspection_date']) ? date('Y-m-d\TH:i', strtotime($qc_record['inspection_date'])) : date('Y-m-d\TH:i')) ?>" 
                               required>
                        <div class="invalid-feedback">
                            Please provide inspection date.
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="notes" class="form-label">Inspection Notes</label>
                    <textarea class="form-control" 
                              id="notes" 
                              name="notes" 
                              rows="3"><?= old('notes', $qc_record['notes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Test Results -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>
                    Test Results
                </h5>
                <button type="button" class="btn btn-sm btn-primary" onclick="addTest()">
                    <i class="bi bi-plus-circle me-1"></i> Add Test
                </button>
            </div>
            <div class="card-body">
                <div id="testsContainer">
                    <?php 
                    $existing_tests = [];
                    if (isset($qc_record['test_results']) && !empty($qc_record['test_results'])) {
                        $existing_tests = json_decode($qc_record['test_results'], true) ?: [];
                    }
                    
                    if (empty($existing_tests)) {
                        $existing_tests = [['test_name' => '', 'specification' => '', 'actual_value' => '', 'status' => '', 'remarks' => '']];
                    }
                    
                    foreach ($existing_tests as $index => $test): 
                    ?>
                        <div class="test-row mb-4 p-3 border rounded" data-index="<?= $index ?>">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="mb-0">Test #<span class="test-number"><?= $index + 1 ?></span></h6>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTest(this)">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Test Name <span class="text-danger">*</span></label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="tests[<?= $index ?>][test_name]" 
                                           value="<?= esc($test['test_name'] ?? '') ?>" 
                                           placeholder="e.g., Dimensional Check" 
                                           required>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Specification</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="tests[<?= $index ?>][specification]" 
                                           value="<?= esc($test['specification'] ?? '') ?>" 
                                           placeholder="e.g., 10±0.1mm">
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Actual Value</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="tests[<?= $index ?>][actual_value]" 
                                           value="<?= esc($test['actual_value'] ?? '') ?>" 
                                           placeholder="e.g., 10.05mm">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="form-select" name="tests[<?= $index ?>][status]" required onchange="updateTestStatus(this)">
                                        <option value="">Select Status</option>
                                        <option value="pass" <?= ($test['status'] ?? '') == 'pass' ? 'selected' : '' ?>>Pass</option>
                                        <option value="fail" <?= ($test['status'] ?? '') == 'fail' ? 'selected' : '' ?>>Fail</option>
                                        <option value="na" <?= ($test['status'] ?? '') == 'na' ? 'selected' : '' ?>>N/A</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Remarks</label>
                                    <input type="text" 
                                           class="form-control" 
                                           name="tests[<?= $index ?>][remarks]" 
                                           value="<?= esc($test['remarks'] ?? '') ?>" 
                                           placeholder="Additional notes">
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert alert-info d-none" id="noTestsAlert">
                    <i class="bi bi-info-circle me-2"></i>
                    No tests added yet. Click "Add Test" to start adding quality control tests.
                </div>
            </div>
        </div>

        <!-- Attachments -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-paperclip me-2"></i>
                    Attachments
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="attachments" class="form-label">Upload Files</label>
                    <input type="file" 
                           class="form-control" 
                           id="attachments" 
                           name="attachments[]" 
                           multiple 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    <div class="form-text">Supported formats: PDF, Images, Word, Excel. Max size: 10MB per file.</div>
                </div>

                <?php if (isset($qc_record) && !empty($qc_record['attachments'])): ?>
                    <div>
                        <h6 class="mb-2">Existing Attachments</h6>
                        <?php 
                        $attachments = json_decode($qc_record['attachments'], true) ?: [];
                        foreach ($attachments as $attachment): 
                        ?>
                            <div class="d-flex align-items-center justify-content-between p-2 border rounded mb-2">
                                <div>
                                    <i class="bi bi-file-earmark me-2"></i>
                                    <a href="<?= base_url($attachment['path']) ?>" target="_blank" class="text-decoration-none">
                                        <?= esc($attachment['name']) ?>
                                    </a>
                                    <small class="text-muted ms-2">(<?= formatFileSize($attachment['size']) ?>)</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAttachment('<?= $attachment['id'] ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-xl-4">
        <!-- Inspection Status -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>
                    Inspection Status
                </h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="status" class="form-label">Current Status <span class="text-danger">*</span></label>
                    <select class="form-select" id="status" name="status" required onchange="updateStatusDisplay()">
                        <option value="">Select Status</option>
                        <option value="pending" <?= old('status', $qc_record['status'] ?? 'pending') == 'pending' ? 'selected' : '' ?>>
                            Pending
                        </option>
                        <option value="in_progress" <?= old('status', $qc_record['status'] ?? '') == 'in_progress' ? 'selected' : '' ?>>
                            In Progress
                        </option>
                        <option value="passed" <?= old('status', $qc_record['status'] ?? '') == 'passed' ? 'selected' : '' ?>>
                            Passed
                        </option>
                        <option value="failed" <?= old('status', $qc_record['status'] ?? '') == 'failed' ? 'selected' : '' ?>>
                            Failed
                        </option>
                        <option value="on_hold" <?= old('status', $qc_record['status'] ?? '') == 'on_hold' ? 'selected' : '' ?>>
                            On Hold
                        </option>
                    </select>
                    <div class="invalid-feedback">
                        Please select inspection status.
                    </div>
                </div>

                <div class="mb-3" id="failureReasonSection" style="display: none;">
                    <label for="failure_reason" class="form-label">Failure Reason</label>
                    <textarea class="form-control" 
                              id="failure_reason" 
                              name="failure_reason" 
                              rows="3"><?= old('failure_reason', $qc_record['failure_reason'] ?? '') ?></textarea>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= isset($qc_record) ? 'Update Inspection' : 'Save Inspection' ?>
                    </button>
                    
                    <?php if (isset($qc_record)): ?>
                        <button type="button" class="btn btn-success" onclick="completeInspection()">
                            <i class="bi bi-check-all me-2"></i>
                            Complete & Approve
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Work Order Details -->
        <div class="card border-0 shadow-sm mb-4" id="workOrderDetails" style="display: none;">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-data me-2"></i>
                    Work Order Details
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <small class="text-muted">Product:</small>
                    <small id="woProduct">-</small>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <small class="text-muted">Quantity Ordered:</small>
                    <small id="woQuantity">-</small>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <small class="text-muted">Quantity Completed:</small>
                    <small id="woCompleted">-</small>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Progress:</small>
                    <small id="woProgress">-</small>
                </div>
            </div>
        </div>

        <!-- Test Summary -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-graph-up me-2"></i>
                    Test Summary
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="border-end">
                            <h5 class="text-primary mb-1" id="totalTests">0</h5>
                            <small class="text-muted">Total Tests</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border-end">
                            <h5 class="text-success mb-1" id="passedTests">0</h5>
                            <small class="text-muted">Passed</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <h5 class="text-danger mb-1" id="failedTests">0</h5>
                        <small class="text-muted">Failed</small>
                    </div>
                </div>

                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar bg-success" id="passRateBar" style="width: 0%"></div>
                </div>
                <div class="text-center">
                    <small class="text-muted">Pass Rate: <span id="passRate">0%</span></small>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-lightning me-2"></i>
                    Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-outline-info" onclick="loadTemplate()">
                        <i class="bi bi-file-text me-2"></i>
                        Load Template
                    </button>
                    
                    <button type="button" class="btn btn-outline-secondary" onclick="saveAsDraft()">
                        <i class="bi bi-save me-2"></i>
                        Save as Draft
                    </button>
                    
                    <?php if (isset($qc_record)): ?>
                        <button type="button" class="btn btn-outline-warning" onclick="printReport()">
                            <i class="bi bi-printer me-2"></i>
                            Print Report
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?= form_close() ?>

<!-- Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Load Test Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <select class="form-select" id="templateSelect">
                    <option value="">Select a template</option>
                    <option value="dimensional">Dimensional Inspection</option>
                    <option value="visual">Visual Inspection</option>
                    <option value="functional">Functional Testing</option>
                    <option value="material">Material Testing</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="applyTemplate()">Apply Template</button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
let testIndex = <?= count($existing_tests) ?>;

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

// Load work order details
function loadWorkOrderDetails() {
    const select = document.getElementById('work_order_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (selectedOption.value) {
        document.getElementById('woProduct').textContent = selectedOption.dataset.productName;
        document.getElementById('woQuantity').textContent = selectedOption.dataset.quantity;
        document.getElementById('workOrderDetails').style.display = 'block';
        
        // Set quantity inspected to work order quantity by default
        document.getElementById('quantity_inspected').value = selectedOption.dataset.quantity;
    } else {
        document.getElementById('workOrderDetails').style.display = 'none';
    }
}

// Add new test
function addTest() {
    const container = document.getElementById('testsContainer');
    const testRow = document.createElement('div');
    testRow.className = 'test-row mb-4 p-3 border rounded';
    testRow.setAttribute('data-index', testIndex);
    
    testRow.innerHTML = `
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="mb-0">Test #<span class="test-number">${testIndex + 1}</span></h6>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeTest(this)">
                <i class="bi bi-trash"></i>
            </button>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Test Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="tests[${testIndex}][test_name]" placeholder="e.g., Dimensional Check" required>
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Specification</label>
                <input type="text" class="form-control" name="tests[${testIndex}][specification]" placeholder="e.g., 10±0.1mm">
            </div>
            <div class="col-md-4 mb-3">
                <label class="form-label">Actual Value</label>
                <input type="text" class="form-control" name="tests[${testIndex}][actual_value]" placeholder="e.g., 10.05mm">
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4 mb-3">
                <label class="form-label">Status <span class="text-danger">*</span></label>
                <select class="form-select" name="tests[${testIndex}][status]" required onchange="updateTestStatus(this)">
                    <option value="">Select Status</option>
                    <option value="pass">Pass</option>
                    <option value="fail">Fail</option>
                    <option value="na">N/A</option>
                </select>
            </div>
            <div class="col-md-8 mb-3">
                <label class="form-label">Remarks</label>
                <input type="text" class="form-control" name="tests[${testIndex}][remarks]" placeholder="Additional notes">
            </div>
        </div>
    `;
    
    container.appendChild(testRow);
    testIndex++;
    updateTestSummary();
    updateNoTestsAlert();
}

// Remove test
function removeTest(button) {
    button.closest('.test-row').remove();
    updateTestNumbers();
    updateTestSummary();
    updateNoTestsAlert();
}

// Update test numbers
function updateTestNumbers() {
    const testRows = document.querySelectorAll('.test-row');
    testRows.forEach((row, index) => {
        row.querySelector('.test-number').textContent = index + 1;
    });
}

// Update test status and summary
function updateTestStatus(select) {
    updateTestSummary();
}

// Update test summary
function updateTestSummary() {
    const testRows = document.querySelectorAll('.test-row');
    let totalTests = testRows.length;
    let passedTests = 0;
    let failedTests = 0;
    
    testRows.forEach(row => {
        const status = row.querySelector('select[name*="[status]"]').value;
        if (status === 'pass') {
            passedTests++;
        } else if (status === 'fail') {
            failedTests++;
        }
    });
    
    const passRate = totalTests > 0 ? (passedTests / totalTests) * 100 : 0;
    
    document.getElementById('totalTests').textContent = totalTests;
    document.getElementById('passedTests').textContent = passedTests;
    document.getElementById('failedTests').textContent = failedTests;
    document.getElementById('passRate').textContent = passRate.toFixed(1) + '%';
    document.getElementById('passRateBar').style.width = passRate + '%';
    
    // Update progress bar color
    const progressBar = document.getElementById('passRateBar');
    progressBar.className = 'progress-bar ' + (passRate >= 80 ? 'bg-success' : passRate >= 60 ? 'bg-warning' : 'bg-danger');
}

// Update no tests alert
function updateNoTestsAlert() {
    const container = document.getElementById('testsContainer');
    const alert = document.getElementById('noTestsAlert');
    const hasTests = container.children.length > 0;
    
    if (hasTests) {
        alert.classList.add('d-none');
    } else {
        alert.classList.remove('d-none');
    }
}

// Update status display
function updateStatusDisplay() {
    const status = document.getElementById('status').value;
    const failureSection = document.getElementById('failureReasonSection');
    
    if (status === 'failed') {
        failureSection.style.display = 'block';
    } else {
        failureSection.style.display = 'none';
    }
}

// Load template
function loadTemplate() {
    const modal = new bootstrap.Modal(document.getElementById('templateModal'));
    modal.show();
}

// Apply template
function applyTemplate() {
    const templateType = document.getElementById('templateSelect').value;
    if (!templateType) return;
    
    const templates = {
        dimensional: [
            {test_name: 'Length Check', specification: 'As per drawing', actual_value: '', status: '', remarks: ''},
            {test_name: 'Width Check', specification: 'As per drawing', actual_value: '', status: '', remarks: ''},
            {test_name: 'Height Check', specification: 'As per drawing', actual_value: '', status: '', remarks: ''}
        ],
        visual: [
            {test_name: 'Surface Finish', specification: 'Smooth, no scratches', actual_value: '', status: '', remarks: ''},
            {test_name: 'Color Check', specification: 'As per specification', actual_value: '', status: '', remarks: ''},
            {test_name: 'Marking/Label', specification: 'Clear and legible', actual_value: '', status: '', remarks: ''}
        ],
        functional: [
            {test_name: 'Operation Test', specification: 'Functions as designed', actual_value: '', status: '', remarks: ''},
            {test_name: 'Performance Test', specification: 'Meets specifications', actual_value: '', status: '', remarks: ''},
            {test_name: 'Safety Test', specification: 'No safety issues', actual_value: '', status: '', remarks: ''}
        ],
        material: [
            {test_name: 'Material Grade', specification: 'As per specification', actual_value: '', status: '', remarks: ''},
            {test_name: 'Hardness Test', specification: 'Within range', actual_value: '', status: '', remarks: ''},
            {test_name: 'Chemical Composition', specification: 'As per standard', actual_value: '', status: '', remarks: ''}
        ]
    };
    
    // Clear existing tests
    document.getElementById('testsContainer').innerHTML = '';
    testIndex = 0;
    
    // Add template tests
    const templateTests = templates[templateType] || [];
    templateTests.forEach(test => {
        addTest();
        const lastRow = document.querySelector('.test-row:last-child');
        lastRow.querySelector('input[name*="[test_name]"]').value = test.test_name;
        lastRow.querySelector('input[name*="[specification]"]').value = test.specification;
    });
    
    bootstrap.Modal.getInstance(document.getElementById('templateModal')).hide();
}

// Complete inspection
function completeInspection() {
    document.getElementById('status').value = 'passed';
    updateStatusDisplay();
    document.getElementById('qcForm').submit();
}

// Save as draft
function saveAsDraft() {
    document.getElementById('status').value = 'pending';
    updateStatusDisplay();
    document.getElementById('qcForm').submit();
}

// Print report
function printReport() {
    window.open(`<?= base_url('/quality-control/' . ($qc_record['id'] ?? '')) ?>/print`, '_blank');
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    updateTestSummary();
    updateStatusDisplay();
    updateNoTestsAlert();
    loadWorkOrderDetails();
});
</script>
<?= $this->endSection() ?>
