<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Gate Pass Management<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content-wrapper">
    <!-- Page Header -->
    <div class="page-header mb-4">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title mb-0">
                    <i class="bi bi-shield-check text-success me-3"></i>Gate Pass Management
                </h1>
                <p class="text-muted mb-0">Material Control & Security Management System</p>
            </div>
            <div class="col-auto">
                <div class="btn-group">
                    <button type="button" class="btn btn-success btn-lg" onclick="createNewGatePass('incoming')">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Incoming Pass
                    </button>
                    <button type="button" class="btn btn-warning btn-lg" onclick="createNewGatePass('outgoing')">
                        <i class="bi bi-box-arrow-right me-2"></i>Outgoing Pass
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Dashboard -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-primary">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= $stats['total_passes'] ?? 0 ?></h3>
                        <p>Total Gate Passes</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-warning">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-clock-history"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= $stats['pending_in'] ?? 0 ?></h3>
                        <p>Pending Incoming</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-info">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= $stats['pending_out'] ?? 0 ?></h3>
                        <p>Pending Outgoing</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="stats-card bg-gradient-success">
                <div class="stats-content">
                    <div class="stats-icon">
                        <i class="bi bi-calendar-day"></i>
                    </div>
                    <div class="stats-info">
                        <h3><?= $stats['today_passes'] ?? 0 ?></h3>
                        <p>Today's Passes</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-body">
            <form class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Search</label>
                    <input type="text" class="form-control" placeholder="Gate pass number, purpose...">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Type</label>
                    <select class="form-select">
                        <option value="">All Types</option>
                        <option value="incoming">Incoming</option>
                        <option value="outgoing">Outgoing</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">Status</label>
                    <select class="form-select">
                        <option value="">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Vendor</label>
                    <select class="form-select">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $vendor): ?>
                            <option value="<?= $vendor['id'] ?>"><?= $vendor['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold">&nbsp;</label>
                    <div class="d-grid">
                        <button type="button" class="btn btn-primary">
                            <i class="bi bi-funnel me-1"></i>Filter
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Gate Pass List -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-ul me-2"></i>Gate Pass List
                </h5>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-download me-1"></i>Export
                    </button>
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="refreshGatePassList()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                    </button>
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Gate Pass No.</th>
                            <th>Type</th>
                            <th>Recipient</th>
                            <th>Purpose</th>
                            <th>Status</th>
                            <th>Expected Date</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($gatePasses as $pass): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-primary"><?= $pass['gate_pass_number'] ?></div>
                                    <small class="text-muted">ID: #<?= $pass['id'] ?></small>
                                </td>
                                <td>
                                    <?php if ($pass['type'] === 'incoming'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-box-arrow-in-right me-1"></i>Incoming
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">
                                            <i class="bi bi-box-arrow-right me-1"></i>Outgoing
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php $recipient = $pass['vendor_name'] ?? ($pass['recipient_name'] ?? 'N/A'); ?>
                                    <div class="fw-bold"><?= $recipient ?></div>
                                    <?php if (!empty($pass['contact_person'])): ?>
                                        <small class="text-muted"><?= $pass['contact_person'] ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= substr($pass['purpose'], 0, 30) ?><?= strlen($pass['purpose']) > 30 ? '...' : '' ?></div>
                                    <?php if ($pass['notes']): ?>
                                        <small class="text-muted"><i class="bi bi-info-circle me-1"></i><?= substr($pass['notes'], 0, 20) ?>...</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusClasses = [
                                        'pending' => 'bg-warning text-dark',
                                        'approved' => 'bg-success',
                                        'completed' => 'bg-primary',
                                        'rejected' => 'bg-danger',
                                        'cancelled' => 'bg-secondary'
                                    ];
                                    $statusClass = $statusClasses[$pass['status']] ?? 'bg-secondary';
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= ucfirst($pass['status']) ?></span>
                                </td>
                                <td>
                                    <?php if ($pass['expected_date']): ?>
                                        <div><?= date('M d, Y', strtotime($pass['expected_date'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($pass['expected_date'])) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div><?= date('M d, Y', strtotime($pass['created_at'])) ?></div>
                                    <small class="text-muted">by <?= $pass['created_by_name'] ?? 'System' ?></small>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-primary" 
                                                onclick="viewGatePassDetails(<?= $pass['id'] ?>)"
                                                title="View Details">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <?php if ($pass['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="approveGatePass(<?= $pass['id'] ?>)"
                                                    title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    onclick="rejectGatePass(<?= $pass['id'] ?>)"
                                                    title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        <?php elseif ($pass['status'] === 'approved'): ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="completeGatePass(<?= $pass['id'] ?>)"
                                                    title="Complete">
                                                <i class="bi bi-check-circle"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                onclick="printGatePass(<?= $pass['id'] ?>)"
                                                title="Print PDF">
                                            <i class="bi bi-printer"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Create Gate Pass Modal -->
<div class="modal fade" id="createGatePassModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Create Gate Pass
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createGatePassForm">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Type</label>
                                <select class="form-select" name="type" id="gatePassType" required>
                                    <option value="">Select Type</option>
                                    <option value="incoming">Incoming</option>
                                    <option value="outgoing">Outgoing</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Send To</label>
                                <select class="form-select" name="recipient_type" id="recipientType" onchange="toggleRecipientFields()">
                                    <option value="vendor">Vendor</option>
                                    <option value="internal">Internal (Branch/Warehouse)</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4" id="vendorFieldWrap">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Vendor</label>
                                <select class="form-select" name="vendor_id" id="vendorSelect">
                                    <option value="">Select Vendor</option>
                                    <?php foreach ($vendors as $vendor): ?>
                                        <option value="<?= $vendor['id'] ?>"><?= $vendor['name'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 d-none" id="internalFieldWrap">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Internal Location</label>
                                <input type="text" class="form-control" name="recipient_name" id="recipientName" placeholder="e.g., Sialkot Setup or Wazirabad Warehouse">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Expected Date & Time</label>
                                <input type="datetime-local" class="form-control" name="expected_date">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Priority</label>
                                <select class="form-select" name="priority">
                                    <option value="normal">Normal</option>
                                    <option value="urgent">Urgent</option>
                                    <option value="emergency">Emergency</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Purpose</label>
                        <textarea class="form-control" name="purpose" rows="3" 
                                  placeholder="Describe the purpose of this gate pass (optional)..."></textarea>
                    </div>
                    
                    <!-- Items Section -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0">Items/Materials</label>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addGatePassItem()">
                                <i class="bi bi-plus-circle me-1"></i>Add Item
                            </button>
                        </div>
                        <div id="gatePassItems">
                            <div class="gate-pass-item border rounded p-3 mb-2">
                                <div class="row g-2">
                                    <div class="col-md-3">
                                        <label class="form-label">Item Source</label>
                                        <select class="form-select" name="items[0][source]" onchange="onItemSourceChange(this)">
                                            <option value="product">Product</option>
                                            <option value="raw">Raw Material</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4 item-product">
                                        <label class="form-label">Product</label>
                                        <select class="form-select" name="items[0][product_id]">
                                            <option value="">Select a product</option>
                                            <?php foreach (($products ?? []) as $p): ?>
                                                <option value="<?= $p['id'] ?>"><?= ($p['code'] ? $p['code'].' - ' : '') . $p['name'] ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-4 item-raw d-none">
                                        <label class="form-label">Description</label>
                                        <input type="text" class="form-control" name="items[0][description]" placeholder="Item description">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Quantity</label>
                                        <input type="number" class="form-control" name="items[0][quantity]" placeholder="Qty">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Unit</label>
                                        <select class="form-select" name="items[0][unit]">
                                            <option value="Pcs">Pcs</option>
                                            <option value="Kg">Kg</option>
                                            <option value="Ltr">Ltr</option>
                                            <option value="Mtr">Mtr</option>
                                            <option value="Box">Box</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Remarks</label>
                                        <input type="text" class="form-control" name="items[0][remarks]" placeholder="Optional remarks">
                                    </div>
                                    <div class="col-md-1">
                                        <label class="form-label">&nbsp;</label>
                                        <button type="button" class="btn btn-outline-danger btn-sm d-block" onclick="removeGatePassItem(this)">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Additional Notes</label>
                        <textarea class="form-control" name="notes" rows="2" 
                                  placeholder="Any additional notes or instructions..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="button" class="btn btn-success" onclick="submitGatePass()">
                    <i class="bi bi-check-circle me-1"></i>Create Gate Pass
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let itemIndex = 1;

function createNewGatePass(type = '') {
    document.getElementById('gatePassType').value = type;
    var modal = new bootstrap.Modal(document.getElementById('createGatePassModal'));
    modal.show();
}

function toggleRecipientFields() {
    const typeSel = document.getElementById('recipientType');
    const vendorWrap = document.getElementById('vendorFieldWrap');
    const internalWrap = document.getElementById('internalFieldWrap');
    if (typeSel.value === 'internal') {
        vendorWrap.classList.add('d-none');
        internalWrap.classList.remove('d-none');
    } else {
        internalWrap.classList.add('d-none');
        vendorWrap.classList.remove('d-none');
    }
}

function addGatePassItem() {
    const itemsContainer = document.getElementById('gatePassItems');
    const itemHtml = `
        <div class="gate-pass-item border rounded p-3 mb-2">
            <div class="row g-2">
                <div class="col-md-3">
                    <label class="form-label">Item Source</label>
                    <select class="form-select" name="items[${itemIndex}][source]" onchange="onItemSourceChange(this)">
                        <option value="product">Product</option>
                        <option value="raw">Raw Material</option>
                    </select>
                </div>
                <div class="col-md-4 item-product">
                    <label class="form-label">Product</label>
                    <select class="form-select" name="items[${itemIndex}][product_id]">
                        <option value="">Select a product</option>
                        <?php foreach (($products ?? []) as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= ($p['code'] ? $p['code'].' - ' : '') . $p['name'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 item-raw d-none">
                    <label class="form-label">Description</label>
                    <input type="text" class="form-control" name="items[${itemIndex}][description]" placeholder="Item description">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Quantity</label>
                    <input type="number" class="form-control" name="items[${itemIndex}][quantity]" placeholder="Qty">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Unit</label>
                    <select class="form-select" name="items[${itemIndex}][unit]">
                        <option value="Pcs">Pcs</option>
                        <option value="Kg">Kg</option>
                        <option value="Ltr">Ltr</option>
                        <option value="Mtr">Mtr</option>
                        <option value="Box">Box</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Remarks</label>
                    <input type="text" class="form-control" name="items[${itemIndex}][remarks]" placeholder="Optional remarks">
                </div>
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="button" class="btn btn-outline-danger btn-sm d-block" onclick="removeGatePassItem(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
    itemsContainer.insertAdjacentHTML('beforeend', itemHtml);
    itemIndex++;
}

function onItemSourceChange(sel) {
    const row = sel.closest('.row');
    const isRaw = sel.value === 'raw';
    row.querySelector('.item-product').classList.toggle('d-none', isRaw);
    row.querySelector('.item-raw').classList.toggle('d-none', !isRaw);
}

function removeGatePassItem(button) {
    const itemsContainer = document.getElementById('gatePassItems');
    if (itemsContainer.children.length > 1) {
        button.closest('.gate-pass-item').remove();
    } else {
        alert('At least one item is required');
    }
}

function submitGatePass() {
    const form = document.getElementById('createGatePassForm');
    const formData = new FormData(form);
    
    // Convert FormData to object and collect items
    const data = {};
    const items = [];
    
    formData.forEach((value, key) => {
        if (key.startsWith('items[')) {
            const matches = key.match(/items\[(\d+)\]\[(\w+)\]/);
            if (matches) {
                const index = matches[1];
                const field = matches[2];
                if (!items[index]) items[index] = {};
                items[index][field] = value;
            }
        } else {
            data[key] = value;
        }
    });
    
    // Filter out empty items and add to data (allow product-only or description-only)
    data.items = items.filter(item => (item.product_id || item.description) && item.quantity);
    
    if (data.items.length === 0) {
        alert('Please add at least one item');
        return;
    }
    
    // Show loading
    const submitBtn = event.target;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creating...';
    submitBtn.disabled = true;
    
    // AJAX request
    // Include recipient fields
    data.recipient_type = document.getElementById('recipientType').value;
    if (data.recipient_type === 'internal') {
        data.recipient_name = document.getElementById('recipientName').value;
        delete data.vendor_id;
    }

    fetch('<?= base_url('gate_passes/create') ?>', {
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
            alert('✅ Gate pass created successfully!\n\nGate Pass No: ' + data.gate_pass.gate_pass_number);
            var modal = bootstrap.Modal.getInstance(document.getElementById('createGatePassModal'));
            modal.hide();
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'Unknown error'));
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Create Gate Pass';
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        alert('❌ Network error: ' + error.message);
        submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Create Gate Pass';
        submitBtn.disabled = false;
    });
}

function approveGatePass(id) {
    if (confirm('Are you sure you want to approve this gate pass?')) {
        updateGatePassStatus(id, 'approved');
    }
}

function rejectGatePass(id) {
    const reason = prompt('Please enter the reason for rejection:');
    if (reason) {
        updateGatePassStatus(id, 'rejected', reason);
    }
}

function completeGatePass(id) {
    if (confirm('Mark this gate pass as completed?')) {
        updateGatePassStatus(id, 'completed');
    }
}

function updateGatePassStatus(id, status, remarks = '') {
    const data = { status: status };
    if (remarks) data.remarks = remarks;
    
    fetch('<?= base_url('gate_passes') ?>/' + id + '/status', {
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
            alert('✅ Gate pass status updated successfully!');
            location.reload();
        } else {
            alert('❌ Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        alert('❌ Network error: ' + error.message);
    });
}

function printGatePass(id) {
    window.open('<?= base_url('gate_passes') ?>/' + id + '/pdf', '_blank');
}

function viewGatePassDetails(id) {
    window.location.href = '<?= base_url('gate_passes') ?>/' + id;
}

function refreshGatePassList() {
    location.reload();
}
</script>
<?= $this->endSection() ?>
