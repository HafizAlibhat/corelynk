<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Salaries<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
/**
 * Monthly salary sheet in two plain steps: prepare the slips, then pay them.
 * Paying credits a real cash/bank account and posts to the ledger, so the
 * account is always asked for, and a cheque needs its number and picture.
 *
 * @var string $month   first day of the month being shown
 * @var array  $sheet
 * @var array  $totals
 * @var array  $currencies
 * @var array  $accounts  cash/bank heads salaries may be paid from
 */
helper('currency');
$base  = base_currency_code();
$label = date('F Y', strtotime($month));

$fmtGroup = static function (array $byCurrency) use ($base) {
    if (! $byCurrency) {
        return format_money(0, $base);
    }
    $parts = [];
    foreach ($byCurrency as $code => $amount) {
        $parts[] = format_money($amount, $code);
    }

    return implode(' + ', $parts);
};

$prepared = 0;
foreach ($sheet as $row) {
    if (! empty($row['payment_id'])) {
        $prepared++;
    }
}

$methodLabels = [
    'cash'            => 'Cash',
    'bank'            => 'Bank transfer',
    'online_transfer' => 'Online transfer',
    'cheque'          => 'Cheque',
];
?>

<div class="row">
    <div class="col-12">

        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h5 class="mb-0"><i class="bi bi-cash-stack me-2 text-primary"></i>Salaries — <?= esc($label) ?></h5>
                <small class="text-muted">Prepare the slips, then pay each one from a cash or bank account. Every payment posts to your books.</small>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <form method="get" action="<?= base_url('employees/payroll') ?>" class="d-flex align-items-center gap-2">
                    <input type="month" name="month" class="form-control form-control-sm" value="<?= esc(date('Y-m', strtotime($month))) ?>">
                    <button class="btn btn-sm btn-outline-secondary" type="submit"><i class="bi bi-arrow-repeat"></i></button>
                </form>
                <?= form_open('employees/payroll/generate', ['class' => 'd-inline']) ?>
                    <input type="hidden" name="month" value="<?= esc($month) ?>">
                    <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-magic me-1"></i>Prepare <?= esc($label) ?></button>
                <?= form_close() ?>
                <a href="<?= base_url('employees') ?>" class="btn btn-sm btn-secondary"><i class="bi bi-people me-1"></i>Employees</a>
            </div>
        </div>

        <!-- The page never said where paying actually happens; it does now. -->
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body py-3">
                <div class="row g-3 small">
                    <div class="col-md-4 d-flex gap-2">
                        <span class="badge bg-primary align-self-start">1</span>
                        <div><strong>Prepare</strong><br><span class="text-muted">Creates a slip for every employee with an agreed monthly salary, for whichever month is picked above — past months included. Nothing is paid yet.</span></div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <span class="badge bg-primary align-self-start">2</span>
                        <div><strong>Adjust</strong><br><span class="text-muted">Edit allowances, commission or deductions on any unpaid slip with the pencil button.</span></div>
                    </div>
                    <div class="col-md-4 d-flex gap-2">
                        <span class="badge bg-success align-self-start">3</span>
                        <div><strong>Pay</strong><br><span class="text-muted">Pick the cash/bank account the money leaves. Cheque? Attach the cheque picture. Posted to the ledger automatically.</span></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-bold">Slips prepared</div>
                    <div class="fs-5 fw-bold"><?= $prepared ?> <span class="text-muted fw-normal fs-6">of <?= count($sheet) ?></span></div>
                    <div class="small text-muted">active employee(s)</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-bold">Still to pay</div>
                    <div class="fs-5 fw-bold text-danger"><?= esc($fmtGroup($totals['pending'])) ?></div>
                    <div class="small text-muted"><?= (int) $totals['pending_count'] ?> slip(s) pending</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                    <div class="text-muted small text-uppercase fw-bold">Paid this month</div>
                    <div class="fs-5 fw-bold text-success"><?= esc($fmtGroup($totals['paid'])) ?></div>
                    <div class="small text-muted"><?= (int) $totals['paid_count'] ?> slip(s) paid</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body py-3">
                    <?php
                    $combined = $totals['paid'];
                    foreach ($totals['pending'] as $code => $amount) {
                        $combined[$code] = ($combined[$code] ?? 0) + $amount;
                    }
                    ?>
                    <div class="text-muted small text-uppercase fw-bold">Month payroll cost</div>
                    <div class="fs-5 fw-bold"><?= esc($fmtGroup($combined)) ?></div>
                    <div class="small text-muted">booked to Salaries Expense</div>
                </div></div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <?php if (empty($sheet)): ?>
                <div class="card-body text-center text-muted py-4">
                    No active employees yet. <a href="<?= base_url('employees/create') ?>">Add one</a> to start the payroll.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Employee</th>
                                <th class="text-end">Basic</th>
                                <th class="text-end">Allowances</th>
                                <th class="text-end">Commission</th>
                                <th class="text-end">Deductions</th>
                                <th class="text-end">Net pay</th>
                                <th>Status</th>
                                <th>Paid from / ledger</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sheet as $row):
                                $currency = $row['currency_code'] ?: ($row['salary_currency'] ?: $base);
                                $name     = trim($row['first_name'] . ' ' . $row['last_name']);
                                $hasSlip  = ! empty($row['payment_id']);
                                $isPaid   = $hasSlip && $row['status'] === 'paid';
                                $payload  = [
                                    'employee_id'   => (int) $row['employee_id'],
                                    'name'          => $name,
                                    'basic_amount'  => (float) ($row['basic_amount'] ?? $row['monthly_salary'] ?? 0),
                                    'allowances'      => (float) ($row['allowances'] ?? 0),
                                    'commission'      => (float) ($row['commission'] ?? 0),
                                    'commission_note' => $row['commission_note'] ?? '',
                                    'deductions'    => (float) ($row['deductions'] ?? 0),
                                    'currency_code' => $currency,
                                    'notes'         => $row['notes'] ?? '',
                                ];
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?= base_url('employees/' . (int) $row['employee_id']) ?>" class="fw-semibold text-decoration-none"><?= esc($name) ?></a>
                                        <div class="small text-muted">
                                            <?= esc($row['employee_code'] ?: '—') ?><?= $row['designation'] ? ' · ' . esc($row['designation']) : '' ?><?= $row['department'] ? ' · ' . esc($row['department']) : '' ?>
                                        </div>
                                    </td>
                                    <?php if (! $hasSlip): ?>
                                        <td colspan="5" class="text-muted small">
                                            No slip for <?= esc($label) ?>.
                                            <?php if ($row['monthly_salary'] === null || (float) $row['monthly_salary'] <= 0): ?>
                                                <a href="<?= base_url('employees/' . (int) $row['employee_id'] . '/edit') ?>">Set a monthly salary first</a>,
                                                then press <em>Prepare this month</em>.
                                            <?php else: ?>
                                                Press <em>Prepare this month</em>, or add it manually with the pencil button.
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge bg-secondary">No slip</span></td>
                                        <td class="text-muted">—</td>
                                    <?php else: ?>
                                        <td class="text-end"><?= esc(number_format((float) $row['basic_amount'], 2)) ?></td>
                                        <td class="text-end text-success"><?= esc(number_format((float) $row['allowances'], 2)) ?></td>
                                        <td class="text-end text-success">
                                            <?= esc(number_format((float) $row['commission'], 2)) ?>
                                            <?php if (! empty($row['commission_note'])): ?>
                                                <i class="bi bi-info-circle text-muted" title="<?= esc($row['commission_note'], 'attr') ?>"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end text-danger"><?= esc(number_format((float) $row['deductions'], 2)) ?></td>
                                        <td class="text-end fw-semibold"><?= esc(format_money((float) $row['net_amount'], $currency)) ?></td>
                                        <td>
                                            <?php if ($isPaid): ?>
                                                <span class="badge bg-success">Paid</span>
                                                <div class="small text-muted"><?= $row['paid_on'] ? esc(date('d M Y', strtotime($row['paid_on']))) : '' ?></div>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Unpaid</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small">
                                            <?php if (! $isPaid): ?>
                                                <span class="text-muted">—</span>
                                            <?php else: ?>
                                                <?= $row['account_name']
                                                    ? esc($row['account_code'] . ' ' . $row['account_name'])
                                                    : '<span class="text-danger">no account recorded</span>' ?>
                                                <div class="text-muted">
                                                    <?= esc($methodLabels[$row['payment_method']] ?? ucfirst((string) $row['payment_method'])) ?><?= $row['cheque_number'] ? ' #' . esc($row['cheque_number']) : '' ?>
                                                    <?php if ($row['cheque_image']): ?>
                                                        · <a href="<?= base_url($row['cheque_image']) ?>" target="_blank"><i class="bi bi-image"></i> cheque</a>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if ($row['posted_entry_id']): ?>
                                                    <a href="<?= base_url('accounting/journals/view/' . (int) $row['posted_entry_id']) ?>" class="text-success">
                                                        <i class="bi bi-journal-check"></i> JE #<?= (int) $row['posted_entry_id'] ?>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-danger"><i class="bi bi-exclamation-triangle"></i> not in ledger</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-end text-nowrap">
                                        <?php if (! $isPaid): ?>
                                            <button type="button" class="btn btn-sm btn-outline-primary js-edit-salary"
                                                    data-slip='<?= esc(json_encode($payload), 'attr') ?>' title="Edit amounts">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php if ($hasSlip): ?>
                                                <button type="button" class="btn btn-sm btn-success js-pay-salary"
                                                        data-id="<?= (int) $row['payment_id'] ?>"
                                                        data-name="<?= esc($name, 'attr') ?>"
                                                        data-amount="<?= esc(format_money((float) $row['net_amount'], $currency), 'attr') ?>">
                                                    <i class="bi bi-cash-coin me-1"></i>Pay
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?= form_open('employees/payroll/' . (int) $row['payment_id'] . '/unpay', [
                                                'class'    => 'd-inline',
                                                'onsubmit' => "return confirm('Undo this payment?');",
                                            ]) ?>
                                                <button class="btn btn-sm btn-outline-danger" type="submit">Undo</button>
                                            <?= form_close() ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit the amounts only. Paying is a separate, deliberate action. -->
<div class="modal fade" id="salaryModal" tabindex="-1">
    <div class="modal-dialog">
        <?= form_open('employees/payroll/save', ['class' => 'modal-content']) ?>
            <div class="modal-header">
                <h5 class="modal-title">Salary slip — <span id="salaryEmployeeName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="month" value="<?= esc($month) ?>">
                <input type="hidden" name="employee_id" id="salaryEmployeeId">
                <p class="text-muted small">For <?= esc($label) ?>. Net pay is basic plus allowances and commission, less deductions. Saving does not pay anything.</p>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label">Basic</label>
                        <input type="number" step="0.01" min="0" class="form-control js-amount" name="basic_amount" id="salaryBasic" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Currency</label>
                        <select name="currency_code" class="form-select" id="salaryCurrency">
                            <?php foreach ($currencies as $code => $info): ?>
                                <option value="<?= esc($code) ?>"><?= esc($code) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Allowances / bonus</label>
                        <input type="number" step="0.01" min="0" class="form-control js-amount" name="allowances" id="salaryAllowances" value="0">
                    </div>
                    <div class="col-6">
                        <label class="form-label">Commission</label>
                        <input type="number" step="0.01" min="0" class="form-control js-amount" name="commission" id="salaryCommission" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Why this commission?</label>
                        <textarea class="form-control" name="commission_note" id="salaryCommissionNote" rows="2" maxlength="500"
                                  placeholder="e.g. 2% on the Al-Noor order delivered in March"></textarea>
                        <div class="form-text">Kept with this month's slip and carried onto the ledger entry for Commission Expense.</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Deductions / advances</label>
                        <input type="number" step="0.01" min="0" class="form-control js-amount" name="deductions" id="salaryDeductions" value="0">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notes</label>
                        <input type="text" class="form-control" name="notes" id="salaryNotes" maxlength="255" placeholder="Optional">
                    </div>
                    <div class="col-12">
                        <div class="alert alert-light border mb-0 d-flex justify-content-between">
                            <span>Net pay</span><strong id="salaryNet">0.00</strong>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save slip</button>
            </div>
        <?= form_close() ?>
    </div>
</div>

<!-- Pay: this is where money leaves an account and the ledger gets the entry. -->
<div class="modal fade" id="payModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="post" id="payForm" enctype="multipart/form-data" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pay salary — <span id="payEmployeeName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex justify-content-between align-items-center py-2 mb-3">
                    <span>Amount to pay</span><strong id="payAmount"></strong>
                </div>
                <div class="row g-3">
                    <div class="col-6">
                        <label class="form-label">Payment date</label>
                        <input type="date" name="paid_on" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Method</label>
                        <select name="payment_method" class="form-select" id="payMethod">
                            <?php foreach ($methodLabels as $value => $text): ?>
                                <option value="<?= esc($value) ?>"><?= esc($text) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Paid from <span class="text-danger">*</span></label>
                        <select name="source_account_id" class="form-select" required>
                            <option value="">Select the cash or bank account…</option>
                            <?php foreach ($accounts as $a): ?>
                                <option value="<?= (int) $a['id'] ?>">
                                    <?= esc($a['code'] . ' — ' . $a['name']) ?><?= $a['currency_code'] ? ' (' . esc($a['currency_code']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">This account is credited and Salaries Expense is debited, so payroll shows up in your accounts.</div>
                    </div>
                    <div class="col-12 d-none" id="payChequeFields">
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Cheque number <span class="text-danger">*</span></label>
                                <input type="text" name="cheque_number" class="form-control" placeholder="e.g. 000123">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Cheque picture <span class="text-danger">*</span></label>
                                <input type="file" name="cheque_image" class="form-control" accept="image/jpeg,image/png,image/webp">
                                <div class="form-text">JPG, PNG or WEBP, up to 5 MB.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Pay &amp; post to ledger</button>
            </div>
        </form>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(function () {
    var slipModal = new bootstrap.Modal(document.getElementById('salaryModal'));
    var payModal  = new bootstrap.Modal(document.getElementById('payModal'));

    function recalcNet() {
        var net = (parseFloat(document.getElementById('salaryBasic').value) || 0)
                + (parseFloat(document.getElementById('salaryAllowances').value) || 0)
                + (parseFloat(document.getElementById('salaryCommission').value) || 0)
                - (parseFloat(document.getElementById('salaryDeductions').value) || 0);
        document.getElementById('salaryNet').textContent =
            net.toFixed(2) + ' ' + document.getElementById('salaryCurrency').value;
    }

    document.querySelectorAll('.js-amount').forEach(function (input) {
        input.addEventListener('input', recalcNet);
    });
    document.getElementById('salaryCurrency').addEventListener('change', recalcNet);

    document.querySelectorAll('.js-edit-salary').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var s = JSON.parse(btn.getAttribute('data-slip'));
            document.getElementById('salaryEmployeeName').textContent = s.name;
            document.getElementById('salaryEmployeeId').value = s.employee_id;
            document.getElementById('salaryBasic').value = s.basic_amount;
            document.getElementById('salaryAllowances').value = s.allowances;
            document.getElementById('salaryCommission').value = s.commission;
            document.getElementById('salaryCommissionNote').value = s.commission_note || '';
            document.getElementById('salaryDeductions').value = s.deductions;
            document.getElementById('salaryCurrency').value = s.currency_code;
            document.getElementById('salaryNotes').value = s.notes || '';
            recalcNet();
            slipModal.show();
        });
    });

    document.querySelectorAll('.js-pay-salary').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('payForm').action =
                '<?= base_url('employees/payroll') ?>/' + btn.getAttribute('data-id') + '/pay';
            document.getElementById('payEmployeeName').textContent = btn.getAttribute('data-name');
            document.getElementById('payAmount').textContent = btn.getAttribute('data-amount');
            payModal.show();
        });
    });

    // Cheque number and picture only matter when the method is a cheque.
    document.getElementById('payMethod').addEventListener('change', function () {
        var box = document.getElementById('payChequeFields');
        var on  = this.value === 'cheque';
        box.classList.toggle('d-none', !on);
        box.querySelectorAll('input').forEach(function (i) { i.required = on; });
    });
}());
</script>
<?= $this->endSection() ?>
