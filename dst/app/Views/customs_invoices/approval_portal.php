<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
$doc = $ctx['doc']['customs_invoice'] ?? [];
$items = $ctx['doc']['items'] ?? [];
?>
<div class="container py-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Customs Invoice Approval</strong>
            <span class="badge bg-warning text-dark">Pending Approval</span>
        </div>
        <div class="card-body">
            <?php if (session()->getFlashdata('error')): ?>
                <div class="alert alert-danger"><?= esc((string) session()->getFlashdata('error')) ?></div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('success')): ?>
                <div class="alert alert-success"><?= esc((string) session()->getFlashdata('success')) ?></div>
            <?php endif; ?>

            <div class="row mb-3">
                <div class="col-md-6">
                    <div><strong>No:</strong> <?= esc((string)($doc['customs_invoice_no'] ?? '-')) ?></div>
                    <div><strong>Mode:</strong> <?= esc((string)($doc['mode'] ?? '-')) ?></div>
                    <div><strong>Tracking:</strong> <?= esc((string)($doc['tracking_no'] ?? '-')) ?></div>
                </div>
                <div class="col-md-6 text-md-end">
                    <div><strong>Currency:</strong> <?= esc((string)($doc['currency_code'] ?? 'USD')) ?></div>
                    <div><strong>Declared Total:</strong> <?= number_format((float)($doc['declared_total'] ?? 0), 2) ?></div>
                </div>
            </div>

            <div class="table-responsive mb-3">
                <table class="table table-sm">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>Description</th>
                        <th class="text-end">Qty</th>
                        <th>UOM</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Line Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $idx => $line): ?>
                        <tr>
                            <td><?= $idx + 1 ?></td>
                            <td><?= esc((string)($line['custom_description'] ?? '')) ?></td>
                            <td class="text-end"><?= number_format((float)($line['declared_qty'] ?? 0), 2) ?></td>
                            <td><?= esc((string)($line['uom'] ?? '')) ?></td>
                            <td class="text-end"><?= number_format((float)($line['declared_unit_price'] ?? 0), 2) ?></td>
                            <td class="text-end"><?= number_format((float)($line['declared_line_total'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <form method="post" action="<?= site_url('customs-approval/' . urlencode((string)$token) . '/decision') ?>" class="d-flex gap-2 align-items-start">
                <?= csrf_field() ?>
                <textarea name="comment" class="form-control" rows="2" placeholder="Optional comment"></textarea>
                <button type="submit" class="btn btn-success" name="decision" value="APPROVE">Approve</button>
                <button type="submit" class="btn btn-danger" name="decision" value="REJECT">Reject</button>
            </form>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
