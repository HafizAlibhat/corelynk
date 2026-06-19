<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Customs Invoice Customization</h4>
            <div class="text-muted small">Create, revise, approve, and archive customs declarations linked to original invoices.</div>
        </div>
    </div>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger"><?= esc((string) session()->getFlashdata('error')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success"><?= esc((string) session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?php if (!empty($can_create)): ?>
    <div class="card mb-3">
        <div class="card-body">
            <form method="post" action="<?= site_url('customs-invoices/create-from-invoice/0') ?>" id="createCustomsForm" class="row g-2">
                <?= csrf_field() ?>
                <div class="col-md-3">
                    <label class="form-label">Original Invoice ID</label>
                    <input type="number" min="1" class="form-control" id="originalInvoiceId" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Mode</label>
                    <select name="mode" class="form-select">
                        <option value="VALUE_ONLY">Value Only</option>
                        <option value="FULL_REWRITE">Full Rewrite</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Shipment ID</label>
                    <input type="number" class="form-control" name="shipment_id">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tracking No</label>
                    <input type="text" class="form-control" name="tracking_no">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-primary w-100" type="submit">Create</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                    <tr>
                        <th>No</th>
                        <th>Original Invoice</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th class="text-end">Declared Total</th>
                        <th>Tracking</th>
                        <th>Updated</th>
                        <th class="text-end">Actions</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($rows ?? [])): ?>
                        <tr><td colspan="8" class="text-center text-muted py-4">No customs invoices found.</td></tr>
                    <?php else: ?>
                        <?php foreach (($rows ?? []) as $r): ?>
                            <tr>
                                <td><strong><?= esc((string)($r['customs_invoice_no'] ?? '-')) ?></strong></td>
                                <td><?= esc((string)($r['original_invoice_number'] ?? $r['original_invoice_id'] ?? '-')) ?></td>
                                <td><span class="badge bg-secondary"><?= esc((string)($r['mode'] ?? '-')) ?></span></td>
                                <td><span class="badge bg-info text-dark"><?= esc((string)($r['status'] ?? '-')) ?></span></td>
                                <td class="text-end"><?= number_format((float)($r['declared_total'] ?? 0), 2) ?> <?= esc((string)($r['currency_code'] ?? 'USD')) ?></td>
                                <td><?= esc((string)($r['tracking_no'] ?? '-')) ?></td>
                                <td><?= esc((string)($r['updated_at'] ?? $r['created_at'] ?? '-')) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-primary" href="<?= site_url('customs-invoices/workspace/' . urlencode((string)($r['uuid'] ?? ''))) ?>">Open</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    var form = document.getElementById('createCustomsForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
        var idInput = document.getElementById('originalInvoiceId');
        var invoiceId = idInput ? parseInt(idInput.value || '0', 10) : 0;
        if (!invoiceId) {
            e.preventDefault();
            alert('Original Invoice ID is required');
            return;
        }
        form.action = '<?= site_url('customs-invoices/create-from-invoice') ?>/' + invoiceId;
    });
})();
</script>
<?= $this->endSection() ?>
