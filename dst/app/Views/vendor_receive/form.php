<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="container-fluid cl-form-page vr-page">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">Vendor Receiving &amp; QC</h1>
            <p class="text-muted mb-0">
                Send Note <span class="fw-semibold"><?= esc($send_note['reference_no'] ?? '-') ?></span>
                &middot; <?= esc($send_note['vendor_name'] ?? '-') ?>
                &middot; <?= esc($send_note['step_name'] ?? '-') ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= site_url('vendor-receive/' . (int) ($send_note['id'] ?? 0) . '/send-slip') ?>" class="btn btn-outline-secondary btn-sm" target="_blank">
                <i class="bi bi-printer"></i> Send note
            </a>
            <a href="<?= site_url('sales-orders') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <!-- At-a-glance shipment summary -->
    <div class="vr-summary mb-3">
        <div class="vr-summary-item">
            <span class="vr-summary-label">Product</span>
            <span class="vr-summary-value"><?= esc($send_note['product_name'] ?? '-') ?></span>
            <span class="text-muted small"><?= esc($send_note['product_code'] ?? '-') ?></span>
        </div>
        <div class="vr-summary-item">
            <span class="vr-summary-label">Sent</span>
            <span class="vr-summary-value"><?= number_format((float) ($send_note['qty'] ?? 0), 2) ?></span>
        </div>
        <div class="vr-summary-item">
            <span class="vr-summary-label">Already received</span>
            <span class="vr-summary-value"><?= number_format((float) ($already_received ?? 0), 2) ?></span>
        </div>
        <div class="vr-summary-item vr-summary-item--highlight">
            <span class="vr-summary-label">Still with vendor</span>
            <span class="vr-summary-value"><?= number_format((float) ($remaining_qty ?? 0), 2) ?></span>
        </div>
        <?php if (!empty($send_items) && isset($send_items[0]['unit_price']) && (float) $send_items[0]['unit_price'] > 0): ?>
            <div class="vr-summary-item">
                <span class="vr-summary-label">Unit price</span>
                <span class="vr-summary-value"><?= number_format((float) $send_items[0]['unit_price'], 2) ?></span>
            </div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= site_url('vendor-receive/store') ?>" id="vendorReceiveForm">
        <?= csrf_field() ?>
        <input type="hidden" name="send_note_id" value="<?= (int) ($send_note['id'] ?? 0) ?>">

        <!-- Step 1: quantities -->
        <div class="card mb-2 vr-card">
            <div class="card-header"><span class="vr-step-badge">1</span><strong>How much came back?</strong></div>
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">Qty received today</label>
                        <div class="input-group">
                            <input type="number" class="form-control vr-qty-input" name="qty_received" id="qty_received"
                                   step="0.01" min="0.01" max="<?= esc(number_format((float) ($remaining_qty ?? 0), 2, '.', '')) ?>"
                                   value="<?= esc(number_format((float) ($remaining_qty ?? 0), 2, '.', '')) ?>" required>
                            <button type="button" class="btn btn-outline-secondary" id="qty_received_reset"
                                    title="Reset to everything still with the vendor">All (<?= number_format((float) ($remaining_qty ?? 0), 2) ?>)</button>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qty accepted <span class="text-success">(passed QC)</span></label>
                        <input type="number" class="form-control" name="qty_accepted" id="qty_accepted" step="0.01" min="0" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Qty rejected <span class="text-danger">(failed QC)</span></label>
                        <input type="number" class="form-control" name="qty_rejected" id="qty_rejected" step="0.01" min="0" required>
                    </div>
                </div>
                <div class="vr-balance-hint mt-2" id="vr_balance_hint"></div>
            </div>
        </div>

        <!-- Step 2: QC -->
        <div class="card mb-2 vr-card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div><span class="vr-step-badge">2</span><strong>Quality checks</strong> <span class="text-muted small">(at least one required)</span></div>
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="addQcRow()"><i class="bi bi-plus-lg"></i> Add check</button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle vr-qc-table" id="qcTable">
                        <thead>
                            <tr>
                                <th>Check name</th>
                                <th style="width:160px;">Result</th>
                                <th>Remarks</th>
                                <th style="width:70px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><input type="text" class="form-control" name="qc_check_name[]" placeholder="e.g. Colour match" required></td>
                                <td>
                                    <select class="form-select vr-qc-status" name="qc_status[]" required>
                                        <option value="">Select…</option>
                                        <option value="pass">✓ Pass</option>
                                        <option value="fail">✗ Fail</option>
                                    </select>
                                </td>
                                <td><input type="text" class="form-control" name="qc_remarks[]" placeholder="Optional"></td>
                                <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeQcRow(this)" title="Remove"><i class="bi bi-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Step 3: where accepted stock goes -->
        <div class="card mb-2 vr-card">
            <div class="card-header"><span class="vr-step-badge">3</span><strong>Accepted stock destination</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-6">
                    <label class="form-label">Put accepted qty into</label>
                    <select class="form-select searchable" name="accepted_to_location_id">
                        <option value="">Select location…</option>
                        <?php foreach (($own_locations ?? []) as $loc): ?>
                            <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' · ' . ($loc['name'] ?? '-')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Then start next step</label>
                    <select class="form-select" name="next_step_id">
                        <option value="">Don't start automatically</option>
                        <?php foreach (($next_steps ?? []) as $step): ?>
                            <option value="<?= (int) ($step['id'] ?? 0) ?>"><?= (int) ($step['step_order'] ?? 0) ?>. <?= esc($step['name'] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Leave blank if this was the last step — the finished product is produced automatically.</div>
                </div>
            </div>
        </div>

        <!-- Step 4: rejected handling (only shown when there's something rejected) -->
        <div class="card mb-2 vr-card" id="vr_rejected_card">
            <div class="card-header"><span class="vr-step-badge vr-step-badge--danger">4</span><strong>What happens to the rejected pieces?</strong></div>
            <div class="card-body row g-2">
                <div class="col-md-4">
                    <label class="form-label">Reason</label>
                    <select class="form-select" name="rejection_reason_id" id="rejection_reason_id">
                        <option value="">Select reason…</option>
                        <?php foreach (($rejection_reasons ?? []) as $reason): ?>
                            <option value="<?= (int) ($reason['id'] ?? 0) ?>"><?= esc($reason['name'] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-8">
                    <label class="form-label">What do we do with them?</label>
                    <div class="vr-action-choice">
                        <label class="vr-action-option">
                            <input type="radio" name="rejected_action" value="hold" checked>
                            <span><strong>Keep at vendor</strong><br><small class="text-muted">Leave on hold there for now</small></span>
                        </label>
                        <label class="vr-action-option">
                            <input type="radio" name="rejected_action" value="return">
                            <span><strong>Bring into our stock</strong><br><small class="text-muted">Refinish here, re-send later</small></span>
                        </label>
                        <label class="vr-action-option">
                            <input type="radio" name="rejected_action" value="rework">
                            <span><strong>Send to another vendor</strong><br><small class="text-muted">Straight to a rework vendor</small></span>
                        </label>
                    </div>
                </div>
                <div class="col-md-6" id="return_loc_wrap" style="display:none;">
                    <label class="form-label">Return to location</label>
                    <select class="form-select searchable" name="return_to_location_id" id="return_to_location_id">
                        <option value="">Select location…</option>
                        <?php foreach (($own_locations ?? []) as $loc): ?>
                            <option value="<?= (int) ($loc['id'] ?? 0) ?>"><?= esc(($loc['warehouse_name'] ?? '-') . ' · ' . ($loc['name'] ?? '-')) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">These pieces come back into our stock, so this step re-opens for that quantity.</div>
                </div>
                <div class="col-md-3" id="rework_vendor_wrap" style="display:none;">
                    <label class="form-label">Rework vendor</label>
                    <select class="form-select searchable" name="rework_vendor_id" id="rework_vendor_id">
                        <option value="">Select vendor…</option>
                        <?php foreach (($vendors ?? []) as $v): ?>
                            <option value="<?= (int) ($v['id'] ?? 0) ?>"><?= esc($v['name'] ?? '-') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3" id="rework_loc_wrap" style="display:none;">
                    <label class="form-label">Rework vendor location</label>
                    <select class="form-select searchable" name="rework_to_location_id" id="rework_to_location_id">
                        <option value="">Select vendor first</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Sticky action bar -->
        <div class="vr-actionbar">
            <div class="vr-actionbar-totals">
                <span><i class="bi bi-check-circle text-success"></i> <span id="vr_bar_accepted">0.00</span> accepted</span>
                <span><i class="bi bi-x-circle text-danger"></i> <span id="vr_bar_rejected">0.00</span> rejected</span>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-save"></i> Save vendor receiving + QC
            </button>
        </div>
    </form>
</div>

<style>
/* Everything below reads Bootstrap 5.3's theme variables instead of fixed hex
   colours, so the page follows the app's light/dark toggle automatically. */
.vr-page { padding-bottom: 1rem; }

.vr-card { border-color: var(--bs-border-color); }
.vr-card.card > .card-header {
    background: var(--bs-tertiary-bg);
    padding: .5rem .85rem !important;
    min-height: 0 !important;
    font-size: .875rem;
}
.vr-card.card > .card-body { padding: .7rem .85rem !important; }
.vr-card + .vr-card { margin-top: .65rem !important; }

/* design-system.css forces a 42px min-height/roomy padding on every
   .form-control/.form-select — override just this form so qty fields and
   selects actually look compact. */
.vr-page .form-control,
.vr-page .form-select,
.vr-page .select2-container .select2-selection--single {
    min-height: 32px !important;
    padding: .3rem .55rem !important;
    font-size: .82rem !important;
    border-radius: 8px !important;
}
.vr-page .select2-container .select2-selection--single .select2-selection__rendered { line-height: 20px !important; padding-left: 0 !important; }
.vr-page .select2-container .select2-selection--single .select2-selection__arrow { height: 30px !important; }
.vr-page .input-group .btn { min-height: 32px; padding: .3rem .6rem; font-size: .82rem; }

.vr-summary {
    display: flex; flex-wrap: wrap;
    background: var(--bs-secondary-bg); border: 1px solid var(--bs-border-color);
    border-radius: .5rem; overflow: hidden;
}
.vr-summary-item {
    flex: 1 1 140px; padding: .5rem .9rem; display: flex; flex-direction: column; gap: 0;
    border-right: 1px solid var(--bs-border-color);
}
.vr-summary-item:last-child { border-right: none; }
.vr-summary-label { font-size: .68rem; text-transform: uppercase; letter-spacing: .04em; color: var(--bs-secondary-color); font-weight: 600; }
.vr-summary-value { font-size: 1.05rem; font-weight: 700; line-height: 1.35; color: var(--bs-emphasis-color); }
.vr-summary-item--highlight { background: var(--bs-warning-bg-subtle); }
.vr-summary-item--highlight .vr-summary-value { color: var(--bs-warning-text-emphasis); }

.vr-step-badge {
    display: inline-flex; align-items: center; justify-content: center;
    width: 1.35rem; height: 1.35rem; border-radius: 50%; background: var(--bs-primary); color: #fff;
    font-size: .7rem; font-weight: 700; margin-right: .4rem; flex-shrink: 0;
}
.vr-step-badge--danger { background: var(--bs-danger); }

.vr-card .form-label { font-size: .8rem; margin-bottom: .25rem; font-weight: 600; color: var(--bs-secondary-color); }
.vr-card .form-text { font-size: .75rem; margin-top: .25rem; }
.vr-qty-input { font-weight: 700; }

.vr-balance-hint {
    font-size: .8rem; padding: .35rem .6rem; border-radius: .35rem;
    background: var(--bs-tertiary-bg); color: var(--bs-secondary-color);
}
.vr-balance-hint.ok { background: var(--bs-success-bg-subtle); color: var(--bs-success-text-emphasis); }
.vr-balance-hint.bad { background: var(--bs-danger-bg-subtle); color: var(--bs-danger-text-emphasis); }

.vr-qc-status option[value="pass"] { color: var(--bs-success-text-emphasis); }
.vr-qc-status option[value="fail"] { color: var(--bs-danger-text-emphasis); }
.vr-qc-table td { vertical-align: middle; padding: .4rem .5rem; }

.vr-action-choice { display: flex; gap: .5rem; flex-wrap: wrap; }
.vr-action-option {
    flex: 1 1 170px; display: flex; gap: .5rem; align-items: flex-start;
    border: 1px solid var(--bs-border-color); border-radius: .5rem; padding: .5rem .65rem; cursor: pointer;
    transition: border-color .15s, background .15s; background: var(--bs-body-bg);
}
.vr-action-option:hover { border-color: var(--bs-secondary-color); }
.vr-action-option input { margin-top: .2rem; flex-shrink: 0; }
.vr-action-option span { font-size: .82rem; line-height: 1.3; }
.vr-action-option:has(input:checked) { border-color: var(--bs-primary); background: var(--bs-primary-bg-subtle); }

.vr-actionbar {
    position: sticky; bottom: 0; z-index: 20; margin-top: .85rem;
    background: var(--bs-body-bg); border: 1px solid var(--bs-border-color); border-radius: .5rem;
    box-shadow: 0 -2px 10px rgba(0, 0, 0, .08);
    padding: .6rem .9rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: .5rem;
}
.vr-actionbar-totals { display: flex; gap: 1rem; font-size: .85rem; color: var(--bs-secondary-color); }
</style>

<script>
function addQcRow() {
    const tbody = document.querySelector('#qcTable tbody');
    const row = document.createElement('tr');
    row.innerHTML = `
        <td><input type="text" class="form-control" name="qc_check_name[]" placeholder="e.g. Colour match" required></td>
        <td>
            <select class="form-select vr-qc-status" name="qc_status[]" required>
                <option value="">Select…</option>
                <option value="pass">✓ Pass</option>
                <option value="fail">✗ Fail</option>
            </select>
        </td>
        <td><input type="text" class="form-control" name="qc_remarks[]" placeholder="Optional"></td>
        <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeQcRow(this)" title="Remove"><i class="bi bi-trash"></i></button></td>
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

// Rework goods go to a place belonging to the chosen vendor; returned goods come
// back to one of our own locations. The lists are kept apart on purpose.
const vendorLocations = <?= json_encode(array_map(static function ($l) {
    return [
        'id' => (int) $l['id'],
        'name' => $l['name'],
        'vendor_id' => (int) $l['vendor_id'],
        'warehouse_name' => $l['warehouse_name'] ?? '',
    ];
}, $vendor_locations ?? [])) ?>;

function fillReworkLocations() {
    const vendorId = parseInt(document.getElementById('rework_vendor_id').value || '0', 10);
    const select = document.getElementById('rework_to_location_id');
    const matches = vendorLocations.filter(l => l.vendor_id === vendorId);
    select.innerHTML = matches.length
        ? '<option value="">Select location…</option>'
        : '<option value="">No location saved for this vendor</option>';
    matches.forEach(function (l) {
        const opt = document.createElement('option');
        opt.value = l.id;
        opt.textContent = (l.warehouse_name ? l.warehouse_name + ' · ' : '') + l.name;
        select.appendChild(opt);
    });

    // Options were just rebuilt — Select2 still shows its old cached list,
    // so it needs a destroy/re-init to pick up the new <option>s.
    if (window.jQuery && window.jQuery(select).data('select2')) {
        window.jQuery(select).select2('destroy');
        window.jQuery(select).select2({ width: '100%', placeholder: 'Search…', allowClear: true });
    }
}

function toggleReworkFields() {
    const action = document.querySelector('input[name="rejected_action"]:checked').value;
    document.getElementById('rework_vendor_wrap').style.display = action === 'rework' ? '' : 'none';
    document.getElementById('rework_loc_wrap').style.display = action === 'rework' ? '' : 'none';
    document.getElementById('return_loc_wrap').style.display = action === 'return' ? '' : 'none';
}

document.querySelectorAll('input[name="rejected_action"]').forEach(function (r) {
    r.addEventListener('change', toggleReworkFields);
});
document.getElementById('rework_vendor_id').addEventListener('change', fillReworkLocations);
toggleReworkFields();
fillReworkLocations();

// --- Quantity auto-fill & live balance ---
// Received defaults to everything still with the vendor (see value= on the input).
// Whenever received changes, accepted follows it 1:1 unless the user has already
// typed a rejected qty — then accepted is backfilled as the remainder.
const receivedInput = document.getElementById('qty_received');
const acceptedInput = document.getElementById('qty_accepted');
const rejectedInput = document.getElementById('qty_rejected');
const hint = document.getElementById('vr_balance_hint');
const rejectedCard = document.getElementById('vr_rejected_card');
const barAccepted = document.getElementById('vr_bar_accepted');
const barRejected = document.getElementById('vr_bar_rejected');

function syncAcceptedFromReceived() {
    const received = parseFloat(receivedInput.value || '0');
    const rejected = parseFloat(rejectedInput.value || '0');
    acceptedInput.value = Math.max(0, received - rejected).toFixed(2);
    updateBalance();
}

function updateBalance() {
    const received = parseFloat(receivedInput.value || '0');
    const accepted = parseFloat(acceptedInput.value || '0');
    const rejected = parseFloat(rejectedInput.value || '0');
    const diff = (accepted + rejected) - received;

    barAccepted.textContent = accepted.toFixed(2);
    barRejected.textContent = rejected.toFixed(2);
    rejectedCard.style.display = rejected > 0.0001 ? '' : 'none';

    if (Math.abs(diff) > 0.0001) {
        hint.className = 'vr-balance-hint mt-2 bad';
        hint.textContent = 'Accepted + Rejected (' + (accepted + rejected).toFixed(2) + ') must equal Received (' + received.toFixed(2) + ').';
    } else {
        hint.className = 'vr-balance-hint mt-2 ok';
        hint.textContent = 'Accepted + Rejected matches Received (' + received.toFixed(2) + ').';
    }
}

document.getElementById('qty_received_reset').addEventListener('click', function () {
    receivedInput.value = parseFloat(receivedInput.max || '0').toFixed(2);
    syncAcceptedFromReceived();
});
receivedInput.addEventListener('input', syncAcceptedFromReceived);
rejectedInput.addEventListener('input', syncAcceptedFromReceived);
acceptedInput.addEventListener('input', updateBalance);

// Auto-populate on load: whole remaining qty received, all accepted, none rejected.
rejectedInput.value = 0;
syncAcceptedFromReceived();

document.getElementById('vendorReceiveForm').addEventListener('submit', function (e) {
    const received = parseFloat(receivedInput.value || '0');
    const accepted = parseFloat(acceptedInput.value || '0');
    const rejected = parseFloat(rejectedInput.value || '0');

    if (Math.abs((accepted + rejected) - received) > 0.0001) {
        e.preventDefault();
        updateBalance();
        hint.scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
});
</script>

<?= $this->endSection() ?>
