<?php /** @var array $cheques */ ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Cheques Register</h5>
    <div>
      <a href="<?= base_url('/accounting/cheques/adjust-advance') ?>" class="btn btn-outline-warning btn-sm me-1"><i class="bi bi-arrow-left-right"></i> Adjust Advance</a>
      <a href="<?= base_url('/accounting/cheques/create') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> New Cheque</a>
    </div>
  </div>

  <?php if (session('success')): ?>
    <div class="alert alert-success py-2 px-3 btn-xs"><?= esc(session('success')) ?></div>
  <?php endif; ?>
  <?php if (session('error')): ?>
    <div class="alert alert-danger py-2 px-3 btn-xs"><?= esc(session('error')) ?></div>
  <?php endif; ?>

  <div class="table-responsive">
    <table class="table table-sm table-striped table-hover table-compact align-middle">
      <thead class="table-light">
        <tr>
          <th>Date</th>
          <th>#</th>
          <th>Bank</th>
          <th>Payee</th>
          <th>Type</th>
          <th class="text-end">Amount</th>
          <th>Status</th>
          <th>Journal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($cheques as $c): ?>
        <tr>
          <td><?= isset($c['cheque_date']) ? date('d-m-Y', strtotime($c['cheque_date'])) : '-' ?></td>
          <td>
            <span title="Receipt" class="text-muted">
              <!-- Inline SVG fallback for receipt (shows even if icon font/CSS is blocked) -->
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" class="me-1" aria-hidden="true" role="img">
                <title>Receipt</title>
                <path d="M3 0h8a1 1 0 0 1 1 1v13l-1-1-1 1-1-1-1 1-1-1-1 1-1-1V1a1 1 0 0 1 1-1z"/>
                <path fill-rule="evenodd" d="M4 3h6v1H4V3zm0 2h6v1H4V5zm0 2h4v1H4V7z"/>
              </svg>
            </span>
            <?= esc($c['cheque_number']) ?>
          </td>
          <td><?= esc($c['bank_name'] ?? '') ?></td>
          <td><?= esc($c['payee_type']==='vendor' ? ($c['vendor_name'] ?? 'Vendor') : ($c['payee_name'] ?? '')) ?></td>
          <td>
            <?php $ptype = $c['payment_type'] ?? 'regular'; ?>
            <?php if ($ptype === 'advance'): ?>
              <span class="badge bg-warning text-dark">Advance</span>
            <?php else: ?>
              <span class="badge bg-light text-muted">Regular</span>
            <?php endif; ?>
          </td>
          <td class="text-end"><?= number_format((float)($c['amount'] ?? 0), 2) ?></td>
          <td><span class="badge bg-<?= $c['status']==='posted' ? 'success' : ($c['status']==='void'?'secondary':'warning') ?>"><?= esc(ucfirst($c['status'] ?? '')) ?></span></td>
          <td><?= $c['posted_entry_id'] ? ('#'.(int)$c['posted_entry_id']) : '-' ?></td>
          <td class="text-end">
            <a class="btn btn-outline-primary btn-xs me-1" href="<?= base_url('/accounting/cheques/'.(int)$c['id'].'/view') ?>" title="View Cheque">
              <i class="bi bi-eye"></i> View
            </a>
            <a class="btn btn-outline-secondary btn-xs me-1" href="<?= base_url('/accounting/cheques/'.(int)$c['id'].'/pdf') ?>" target="_blank" title="Download PDF">
              <i class="bi bi-file-pdf"></i> PDF
            </a>
            <a class="btn btn-outline-success btn-xs" href="<?= base_url('/accounting/receipts/'.(int)$c['id']) ?>" target="_blank" title="View Receipt">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" style="vertical-align:middle;">
                <title>Receipt</title>
                <path d="M3 0h8a1 1 0 0 1 1 1v13l-1-1-1 1-1-1-1 1-1-1-1 1-1-1V1a1 1 0 0 1 1-1z"/>
                <path fill-rule="evenodd" d="M4 3h6v1H4V3zm0 2h6v1H4V5zm0 2h4v1H4V7z"/>
              </svg> Receipt
            </a>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?= $this->endSection() ?>
