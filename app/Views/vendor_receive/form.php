<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h4 mb-1">Vendor Receiving + QC</h1>
            <p class="text-muted mb-0">Send Note: <?= esc($send_note['reference_no'] ?? '-') ?></p>
        </div>
        <a href="<?= site_url('sales-orders') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-3"><strong>Vendor:</strong> <?= esc($send_note['vendor_name'] ?? '-') ?></div>
                <div class="col-md-3"><strong>Product:</strong> <?= esc($send_note['product_name'] ?? '-') ?> (<?= esc($send_note['product_code'] ?? '-') ?>)</div>
                <div class="col-md-3"><strong>Step:</strong> <?= esc($send_note['step_name'] ?? '-') ?></div>
                <div class="col-md-3"><strong>Sent Qty:</strong> <?= number_format((float) ($send_note['qty'] ?? 0), 4) ?></div>
                <?php if (!empty($send_items) && isset($send_items[0]['unit_price'])): ?>
                    <div class="col-md-3"><strong>Unit Price:</strong> <?= number_format((float)($send_items[0]['unit_price'] ?? 0), 4) ?></div>
                <?php endif; ?>
                <div class="col-md-3"><strong>Already Received:</strong> <?= number_format((float) ($already_received ?? 0), 4) ?></div>
                <div class="col-md-3"><strong>Remaining:</strong> <?= number_format((float) ($remaining_qty ?? 0), 4) ?></div>
            </div>
        </div>
    </div>

    <form method="post" action="<?= site_url('vendor-receive/store') ?>" id="vendorReceiveForm">
        <?= csrf_field() ?>
        <input type="hidden" name="send_note_id" value="<?= (int) ($send_note['id'] ?? 0) ?>">

        <div class="card mb-3">
            <div class="card-header"><strong>Receive and Split</strong></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Qty Received</label>
                        <input type="number" class="form-control" name="qty_received" id="qty_received" step="0.0001" min="0.0001" max="<?= esc(number_format((float) ($remaining_qty ?? 0), 4, '.', '')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qty Accepted</label>
                        <input type="number" class="form-control" name="qty_accepted" id="qty_accepted" step="0.0001" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qty Rejected</label>
                        <input type="number" class="form-control" name="qty_rejected" id="qty_rejected" step="0.0001" min="0" required>
                    </div>
                </div>
                <div class="small text-muted mt-2">Rule: Accepted + Rejected must equal Received.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>QC Checks (Mandatory)</strong>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addQcRow()">Add Check</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered" id="qcTable">
                        <thead>
                            <tr>
                                <th>Check Name</th>
                                <th style="width:140px;">Status</th>
                                <th>Remarks</th>
                                <th style="width:100px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" class="form-control" name="qc_check_name[]" required></td>
                                <td>
                                    <select class="form-select" name="qc_status[]" required>
                                        <option value="">Select</option>
                                        <option value="pass">Pass</option>
                                        <option value="fail">Fail</option>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control" name="qc_remarks[]"></td>
                                <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeQcRow(this)">Remove</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Accepted Items Destination</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label">Accepted To Location</label>
                    <select class="form-select" name="accepted_to_location_id">
                        <option value="">Select Location</option>
                        <?php foreach (($locations ?? []) as $loc): ?>
                            <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' - ' . ($loc['name'] ?? '-')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Start Next Step (Optional)</label>
                    <select class="form-select" name="next_step_id">
                        <option value="">Do Not Start Automatically</option>
                        <?php foreach (($next_steps ?? []) as $step): ?>
                            <option value="<?= (int) ($step['id'] ?? 0) ?>"><?= (int) ($step['step_order'] ?? 0) ?>. <?= esc($step['name'] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><strong>Rejected Items Handling</strong></div>
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <label class="form-label">Rejection Reason</label>
                    <select class="form-select" name="rejection_reason_id" id="rejection_reason_id">
                        <option value="">Select Reason</option>
                        <?php foreach (($rejection_reasons ?? []) as $reason): ?>
                            <option value="<?= (int) ($reason['id'] ?? 0) ?>"><?= esc($reason['name'] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Rejected Action</label>
                    <select class="form-select" name="rejected_action" id="rejected_action">
                        <option value="hold">Keep Separate (Hold)</option>
                        <option value="rework">Rework</option>
                        <option value="scrap">Scrap</option>
                    </select>
                </div>
                <div class="col-md-4" id="rework_vendor_wrap" style="display:none;">
                    <label class="form-label">Rework Vendor</label>
                    <select class="form-select" name="rework_vendor_id">
                        <option value="">Select Vendor</option>
                        <?php foreach (($vendors ?? []) as $v): ?>
                            <option value="<?= (int) ($v['id'] ?? 0) ?>"><?= esc($v['name'] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="rework_loc_wrap" style="display:none;">
                    <label class="form-label">Rework To Location</label>
                    <select class="form-select" name="rework_to_location_id">
                        <option value="">Select Location</option>
                        <?php foreach (($locations ?? []) as $loc): ?>
                            <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' - ' . ($loc['name'] ?? '-')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-primary">Save Vendor Receiving + QC</button>
        </div>
    </form>
</div>

<script>
function addQcRow() {
    const tbody = document.querySelector('#qcTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="form-control" name="qc_check_name[]" required></td>
        <td>
            <select class="form-select" name="qc_status[]" required>
                <option value="">Select</option>
                <option value="pass">Pass</option>
                <option value="fail">Fail</option>
            </select>
        </td>
        <td><input type="text" class="form-control" name="qc_remarks[]"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeQcRow(this)">Remove</button></td>
    `;
    tbody.appendChild(row);
}

function removeQcRow(btn) {
    const rows = document.querySelectorAll('#qcTable tbody tr');
    if (rows.length <= 1) {
        alert('At least one QC check is required.');
        return;
    }
    btn.closest('tr').remove();
}

function toggleReworkFields() {
    const action = document.getElementById('rejected_action').value;
    const reworkVendorWrap = document.getElementById('rework_vendor_wrap');
    const reworkLocWrap = document.getElementById('rework_loc_wrap');
    const show = action === 'rework';
    reworkVendorWrap.style.display = show ? '' : 'none';
    reworkLocWrap.style.display = show ? '' : 'none';
}

document.getElementById('rejected_action').addEventListener('change', toggleReworkFields);
toggleReworkFields();

document.getElementById('vendorReceiveForm').addEventListener('submit', function (e) {
    const received = parseFloat(document.getElementById('qty_received').value || '0');
    const accepted = parseFloat(document.getElementById('qty_accepted').value || '0');
    const rejected = parseFloat(document.getElementById('qty_rejected').value || '0');

    if (Math.abs((accepted + rejected) - received) > 0.0001) {
        e.preventDefault();
        alert('Accepted + Rejected must equal Received quantity.');
        return;
    }
});
</script>

<?= $this->endSection() ?>
