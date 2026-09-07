<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Cheque Details<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Cheque Details</h5>
        <div>
            <a class="btn btn-sm btn-outline-primary" href="<?= base_url('/accounting/cheques/'.$cheque['id'].'/pdf') ?>" target="_blank">Print / PDF</a>
            <a class="btn btn-sm btn-secondary" href="<?= base_url('/accounting/cheques') ?>">Back</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-3">
            <div class="col-md-6">
                <div><strong>Cheque #:</strong> <?= esc($cheque['cheque_number']) ?></div>
                <div><strong>Date:</strong> <?= isset($cheque['cheque_date']) ? date('d-m-Y', strtotime($cheque['cheque_date'])) : '' ?></div>
                <div><strong>Payee:</strong> <?= esc($cheque['payee_name'] ?? $cheque['vendor_name'] ?? 'N/A') ?></div>
                <div><strong>Type:</strong> <?= esc(ucfirst($cheque['payee_type'] ?? '')) ?></div>
                <?php $ptype = $cheque['payment_type'] ?? 'regular'; ?>
                <div><strong>Payment:</strong> 
                  <?php if ($ptype === 'advance'): ?>
                    <span class="badge bg-warning text-dark">Advance</span>
                  <?php else: ?>
                    <span class="badge bg-success">Regular</span>
                  <?php endif; ?>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div><strong>Bank:</strong> <?= esc($cheque['bank_name'] ?? '') ?></div>
                <div><strong>Account #:</strong> <?= esc($cheque['account_number'] ?? '') ?></div>
                <div><strong>Amount:</strong> <?= esc($amount) ?></div>
                <div class="small text-muted"><?= esc($amountWords ?? '') ?></div>
            </div>
        </div>

        <h6>Lines</h6>
        <table class="table table-sm table-striped">
            <thead><tr><th>Account</th><th>Description</th><th class="text-end">Amount</th></tr></thead>
            <tbody>
                <?php foreach (($lines ?? []) as $l): ?>
                    <tr>
                        <td><?= esc($l['account_name'] ?? 'N/A') ?></td>
                        <td><?= esc($l['description'] ?? '') ?></td>
                        <td class="text-end"><?= number_format((float)($l['amount'] ?? 0),2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td></td>
                    <td class="text-end"><strong>Total</strong></td>
                    <td class="text-end"><strong><?= esc($amount) ?></strong></td>
                </tr>
            </tfoot>
        </table>

        <div class="mt-3">
            <strong>Notes:</strong>
            <div class="small text-muted"><?= esc($cheque['notes'] ?? '') ?></div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
