<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Customer Payment #<?= (int)($payment['id'] ?? 0) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $paymentId = (int)($payment['id'] ?? 0);
  $customerName = (string)($payment['customer_name'] ?? '');
  $methodSource = trim((string)($payment['payment_method'] ?? ($payment['payment_method_name'] ?? '')));
  $method = strtolower(str_replace([' ', '-'], '_', $methodSource));
  $customerId = (int)($payment['customer_id'] ?? 0);
  $sourceName = (string)($payment['source_account_name'] ?? '');
  $sourceAccountId = (int)($payment['source_account_id'] ?? 0);
  $sourceNo = (string)($payment['source_account_number'] ?? '');
  $sourceSummary = trim($sourceName . ($sourceNo ? ' ****' . substr($sourceNo, -4) : ''));
  $postedEntryId = (int)($payment['posted_entry_id'] ?? 0);
  $status = strtolower(trim((string)($payment['status'] ?? '')));
  if ($status === '') {
    $status = $postedEntryId > 0 ? 'posted' : 'draft';
  }
  $currency = (string)($displayCurrency ?? $payment['currency_code'] ?? 'PKR');
  $amount = (float)($payment['amount'] ?? 0);
  $date = (string)($payment['payment_date'] ?? '');
  $memo = (string)($payment['memo'] ?? '');
  $notes = (string)($payment['notes'] ?? '');
  $attCount = is_array($attachments ?? null) ? count($attachments) : 0;
  $isDraft = $status === 'draft';

  $statusColors = ['draft'=>['bg'=>'rgba(245,158,11,.1)','fg'=>'#d97706','bd'=>'rgba(245,158,11,.25)'], 'posted'=>['bg'=>'rgba(34,197,94,.1)','fg'=>'#16a34a','bd'=>'rgba(34,197,94,.25)'], 'void'=>['bg'=>'rgba(239,68,68,.1)','fg'=>'#ef4444','bd'=>'rgba(239,68,68,.25)']];
  $sc = $statusColors[$status] ?? $statusColors['draft'];
  $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
  $methodColors = ['cheque'=>['bg'=>'rgba(139,92,246,.08)','fg'=>'#7c3aed','bd'=>'rgba(139,92,246,.2)'], 'cash'=>['bg'=>'rgba(34,197,94,.06)','fg'=>'#16a34a','bd'=>'rgba(34,197,94,.2)'], 'bank'=>['bg'=>'rgba(37,99,235,.06)','fg'=>'#2563eb','bd'=>'rgba(37,99,235,.2)'], 'online_transfer'=>['bg'=>'rgba(245,158,11,.06)','fg'=>'#d97706','bd'=>'rgba(245,158,11,.2)']];
  $mc = $methodColors[$method] ?? $methodColors['cash'];
?>

<style>
  .cpv { max-width:1200px; margin:0 auto; }
  .cpv-card { background:var(--white,#fff); border:1px solid var(--gray-200,#e2e8f0); border-radius:.5rem; overflow:hidden; }
  .cpv-hdr { padding:.5rem 1rem; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--gray-200,#e2e8f0); background:var(--gray-50,#f8fafc); }
  .cpv-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
  .cpv-info { width:100%; border-collapse:collapse; }
  .cpv-info td { padding:.4rem .65rem; font-size:.82rem; vertical-align:top; border-bottom:1px solid var(--gray-100,#f1f5f9); }
  .cpv-info .lbl { width:140px; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.4px; color:var(--gray-400,#94a3b8); white-space:nowrap; }
  .cpv-info .val { font-weight:600; color:var(--gray-700,#1e293b); }
  .cpv-amt { font-size:1.3rem; font-weight:800; font-family:'Courier New',monospace; color:var(--gray-800,#0f172a); }
  .cpv-amt .cur { font-size:.7rem; color:var(--gray-400,#94a3b8); vertical-align:top; margin-right:2px; font-weight:600; }
  .cpv-sec { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-400,#94a3b8); }
  .cpv-btn { padding:.25rem .6rem; font-size:.72rem; font-weight:600; border-radius:.3rem; }
  .cpv-alloc th { font-size:.68rem; text-transform:uppercase; letter-spacing:.5px; font-weight:600; padding:.4rem .65rem; background:var(--gray-50,#f8fafc); color:var(--gray-500,#64748b); border-bottom:2px solid var(--gray-200,#e2e8f0); }
  .cpv-alloc td { padding:.4rem .65rem; font-size:.82rem; border-bottom:1px solid var(--gray-100,#f1f5f9); color:var(--gray-600,#475569); }
  .cpv-att-item { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:5px 8px; border-radius:4px; border:1px solid var(--gray-200,#e2e8f0); background:var(--gray-50,#f8fafc); margin-bottom:6px; font-size:.75rem; }
  .cpv-payment-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.55px; color:var(--gray-400,#94a3b8); }
  .cpv-this-amount { font-size:1.5rem; font-weight:900; font-family:'Courier New',monospace; color:var(--gray-800,#0f172a); letter-spacing:-.01em; }
  .cpv-this-amount .cur { font-size:.72rem; color:var(--gray-400,#94a3b8); vertical-align:top; margin-right:2px; font-weight:600; }
  .cpv-field-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:.1rem 0; }
  .cpv-field { padding:.35rem .65rem; }
  .cpv-field .cpv-field-lbl { font-size:.66rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-400,#94a3b8); display:block; margin-bottom:1px; }
  .cpv-field .cpv-field-val { font-size:.82rem; font-weight:600; color:var(--gray-700,#1e293b); }
  .cpv-single-view { border-left:4px solid #2563eb; background:linear-gradient(90deg,rgba(37,99,235,.08),transparent); }
  body.theme-dark .cpv-field .cpv-field-val { color:var(--gray-700,#e2e8f0) !important; }

  /* === INVOICE PAYMENT JOURNEY === */
  .ipj { border:1px solid var(--gray-200,#e2e8f0); border-radius:.5rem; background:var(--white,#fff); margin-bottom:.5rem; overflow:hidden; }
  .ipj-head { padding:.6rem 1rem; background:var(--gray-50,#f8fafc); border-bottom:1px solid var(--gray-200,#e2e8f0); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; }
  .ipj-toggle { width:100%; border:0; text-align:left; cursor:pointer; }
  .ipj .ipj-body { display:none; }
  .ipj.is-open .ipj-body { display:block; }
  .ipj-chevron { transition:transform .2s ease; }
  .ipj.is-open .ipj-chevron { transform:rotate(180deg); }
  .ipj-title { font-size:.78rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-500,#64748b); }
  .ipj-body { padding:.75rem 1rem; background:var(--white,#fff); }
  .ipj-invoice-row { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem; margin-bottom:.7rem; }
  .ipj-inv-link { font-size:.92rem; font-weight:700; color:#2563eb; text-decoration:none; }
  .ipj-inv-link:hover { text-decoration:underline; }
  .ipj-status-pill { display:inline-flex; align-items:center; gap:4px; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.3px; }
  .ipj-progress-wrap { margin-bottom:.65rem; }
  .ipj-progress-labels { display:flex; justify-content:space-between; margin-bottom:3px; }
  .ipj-progress-labels span { font-size:.72rem; color:var(--gray-500,#64748b); }
  .ipj-progress-bar { height:10px; background:var(--gray-200,#e2e8f0); border-radius:10px; overflow:hidden; }
  .ipj-progress-fill { height:100%; border-radius:10px; transition:width .3s; }
  .ipj-amounts { display:grid; grid-template-columns:repeat(3,1fr); gap:.4rem; margin-bottom:.65rem; }
  .ipj-amount-cell { background:var(--gray-50,#f8fafc); border:1px solid var(--gray-200,#e2e8f0); border-radius:.35rem; padding:.4rem .6rem; text-align:center; }
  .ipj-amount-cell .label { font-size:.65rem; font-weight:600; text-transform:uppercase; letter-spacing:.4px; color:var(--gray-400,#94a3b8); display:block; margin-bottom:2px; }
  .ipj-amount-cell .value { font-size:.9rem; font-weight:800; font-family:'Courier New',monospace; color:var(--gray-700,#1e293b); }
  .ipj-payments-list { margin-top:.4rem; }
  .ipj-payments-list .pitem { display:flex; align-items:center; gap:.5rem; padding:.35rem .5rem; border-radius:.3rem; border:1px solid var(--gray-200,#e2e8f0); background:var(--gray-50,#f8fafc); margin-bottom:.3rem; font-size:.78rem; }
  .ipj-payments-list .pitem-num { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:.65rem; font-weight:800; flex-shrink:0; }
  .ipj-payments-list .pitem-body { flex:1; min-width:0; }
  .ipj-payments-list .pitem-amt { font-weight:800; font-family:'Courier New',monospace; font-size:.82rem; flex-shrink:0; }
  /* dark-theme overrides for hardcoded inline colours */
  body.theme-dark .ipj-amount-cell { background:var(--gray-100,#1e293b) !important; border-color:var(--gray-300,#475569) !important; }
  body.theme-dark .ipj-payments-list .pitem { border-color:var(--gray-300,#475569) !important; background:var(--gray-100,#1e293b) !important; }
  body.theme-dark .cpv-info td { border-bottom-color:var(--gray-200,#334155); }
  body.theme-dark .cpv-info .val { color:var(--gray-700,#e2e8f0); }
  body.theme-dark .cpv-amt { color:var(--gray-800,#f1f5f9); }
</style>

<div class="container-fluid px-3 py-2 cpv">
  <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-size:16px;flex-shrink:0"><i class="bi bi-receipt"></i></div>
        <div>
          <h6 class="mb-0 fw-bold" style="color:var(--gray-700,#1e293b);font-size:.95rem;">Customer Payment #<?= $paymentId ?></h6>
          <div style="font-size:.7rem;color:var(--gray-400,#94a3b8)">
            <?= esc($customerName ?: 'N/A') ?> &nbsp;&bull;&nbsp;
            <?= $date ? date('d M Y', strtotime($date)) : 'N/A' ?> &nbsp;&bull;&nbsp;
            <span style="font-weight:700;color:var(--gray-500,#64748b)"><?= esc($currency) ?> <?= number_format($amount, 2) ?> received</span>
          </div>
        </div>
        <span class="cpv-badge ms-1" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;border:1px solid <?= $sc['bd'] ?>"><?= strtoupper($status) ?></span>
        <?php if ($attCount > 0): ?>
        <span class="cpv-badge" style="background:rgba(100,116,139,.08);color:#64748b;border:1px solid rgba(100,116,139,.15)"><i class="bi bi-paperclip me-1"></i><?= $attCount ?></span>
        <?php endif; ?>
      </div>
    <div class="d-flex gap-1">
      <a class="btn btn-outline-secondary cpv-btn" href="<?= base_url('accounting/customer-payments') ?>"><i class="bi bi-arrow-left me-1"></i>All Payments</a>
      <?php if ($postedEntryId > 0): ?>
        <a class="btn btn-outline-secondary cpv-btn" href="<?= base_url('accounting/journals/view/' . $postedEntryId) ?>"><i class="bi bi-journal-text me-1"></i>Journal</a>
      <?php endif; ?>
      <?php if ($isDraft): ?>
        <a class="btn btn-outline-primary cpv-btn" href="<?= base_url('accounting/customer-payments/' . $paymentId . '/edit') ?>"><i class="bi bi-pencil-square me-1"></i>Edit Draft</a>
        <button type="button" class="btn btn-success cpv-btn js-confirm-payment" data-payment-id="<?= $paymentId ?>"><i class="bi bi-check-lg me-1"></i>Post This Payment</button>
      <?php endif; ?>
      <?php
        // Show Receive Payment button in header if any allocations have a remaining balance
        $headerPartials = [];
        foreach ($allocations ?? [] as $_ha) {
          $haBal = (float)($_ha['invoice_balance'] ?? 0);
          $haIid = (int)($_ha['invoice_id'] ?? 0);
          if ($haBal > 0.005 && $haIid > 0 && !isset($headerPartials[$haIid])) {
            $headerPartials[$haIid] = ['invoice_id'=>$haIid, 'invoice_number'=>$_ha['invoice_number']??('INV-'.$haIid), 'balance'=>$haBal];
          }
        }
      ?>
      <?php if (!empty($headerPartials)): ?>
        <?php foreach ($headerPartials as $hp): ?>
          <a class="btn btn-warning cpv-btn fw-semibold" href="<?= base_url('accounting/customer-payments/pay?invoice_id=' . $hp['invoice_id']) ?>">
            <i class="bi bi-cash-coin me-1"></i>Receive Pending Payment
            <span style="opacity:.8;font-size:.68rem;margin-left:2px">(<?= esc($currency) ?> <?= number_format($hp['balance'], 2) ?>)</span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="cpv-card cpv-single-view mb-2">
    <div style="padding:.55rem .85rem;display:flex;align-items:center;justify-content:space-between;gap:.5rem;flex-wrap:wrap;">
      <div class="d-flex align-items-center gap-2">
        <span class="cpv-badge" style="background:rgba(37,99,235,.12);color:#2563eb;border:1px solid rgba(37,99,235,.25)">Single Payment View</span>
        <span style="font-size:.76rem;color:var(--gray-500,#64748b)">You are viewing details for payment #<?= $paymentId ?> only.</span>
      </div>
      <span style="font-size:.72rem;color:var(--gray-400,#94a3b8)">Use <strong>All Payments</strong> to see the full customer payment history.</span>
    </div>
  </div>

  <div class="row g-2">
    <div class="col-lg-8">
      <!-- ===== THIS PAYMENT card ===== -->
      <div class="cpv-card mb-2">
        <div style="padding:.65rem 1rem .5rem 1rem;display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:.5rem;border-bottom:1px solid var(--gray-200,#e2e8f0);">
          <div>
            <div class="cpv-payment-title"><i class="bi bi-receipt me-1"></i>Payment #<?= $paymentId ?> &mdash; <span style="font-weight:400;text-transform:none;letter-spacing:0">received from</span> <strong><?= esc($customerName ?: 'N/A') ?></strong></div>
            <div style="font-size:.75rem;color:var(--gray-500,#64748b);margin-top:2px;"><?= $date ? date('d M Y', strtotime($date)) : 'N/A' ?></div>
          </div>
          <div style="text-align:right;">
            <div style="font-size:.66rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--gray-400,#94a3b8);margin-bottom:1px;">Amount Received in This Payment</div>
            <div class="cpv-this-amount"><span class="cur"><?= esc($currency) ?></span><?= number_format($amount, 2) ?></div>
          </div>
        </div>
        <div class="cpv-field-grid" style="border-bottom:1px solid var(--gray-100,#f1f5f9);">
          <div class="cpv-field" style="border-right:1px solid var(--gray-100,#f1f5f9);">
            <span class="cpv-field-lbl">Payment Method</span>
            <span class="cpv-badge" style="background:<?= $mc['bg'] ?>;color:<?= $mc['fg'] ?>;border:1px solid <?= $mc['bd'] ?>"><i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?> me-1"></i><?= esc(ucfirst(str_replace('_',' ',$method))) ?></span>
          </div>
          <div class="cpv-field" style="border-right:1px solid var(--gray-100,#f1f5f9);">
            <span class="cpv-field-lbl">Deposited Into</span>
            <span class="cpv-field-val"><?= esc($sourceSummary ?: 'N/A') ?></span>
          </div>
          <div class="cpv-field" style="border-right:1px solid var(--gray-100,#f1f5f9);">
            <span class="cpv-field-lbl">Reference / Cheque #</span>
            <span class="cpv-field-val"><?= esc($memo ?: 'N/A') ?></span>
          </div>
          <div class="cpv-field">
            <span class="cpv-field-lbl">Payment Status</span>
            <span class="cpv-badge" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;border:1px solid <?= $sc['bd'] ?>"><?= strtoupper($status) ?></span>
          </div>
        </div>
        <?php if ($notes !== ''): ?>
        <div class="cpv-field"><span class="cpv-field-lbl">Notes</span><span class="cpv-field-val"><?= esc($notes) ?></span></div>
        <?php endif; ?>
      </div>

      <?php
        $allocatedSoFar = 0.0;
        foreach ($allocations ?? [] as $_a) { $allocatedSoFar += (float)($_a['allocated_amount'] ?? $_a['amount'] ?? 0); }
        $unallocated = max(0.0, round($amount - $allocatedSoFar, 2));
        // Build per-invoice payment journey data
        $invoiceJourneys = [];
        foreach ($allocations ?? [] as $a) {
          $iid = (int)($a['invoice_id'] ?? 0);
          if (!isset($invoiceJourneys[$iid])) {
            $invoiceJourneys[$iid] = [
              'invoice_id'      => $iid,
              'invoice_number'  => $a['invoice_number'] ?? ('INV-' . $iid),
              'issue_date'      => $a['issue_date'] ?? '',
              'invoice_total'   => (float)($a['invoice_total'] ?? 0),
              'invoice_balance' => (float)($a['invoice_balance'] ?? 0),
              'invoice_status'  => strtolower(trim((string)($a['invoice_status'] ?? ''))),
              'payments'        => [],
            ];
          }
          $invoiceJourneys[$iid]['payments'][] = [
            'payment_id' => $paymentId,
            'date'       => $date,
            'amount'     => (float)($a['allocated_amount'] ?? $a['amount'] ?? 0),
            'method'     => $method,
            'status'     => $status,
          ];
        }
        foreach ($invoiceJourneys as $iid => &$jrn) {
          $invTotal   = $jrn['invoice_total'];
          $invBalance = $jrn['invoice_balance'];
          $jrn['total_paid_all']   = max(0.0, round($invTotal - $invBalance, 2));
          $thisPaid = array_sum(array_column($jrn['payments'], 'amount'));
          $jrn['paid_before_this'] = max(0.0, round($jrn['total_paid_all'] - $thisPaid, 2));
        }
        unset($jrn);
      ?>

      <!-- Invoice Payment Journey -->
      <?php foreach ($invoiceJourneys as $jrn):
        $invTotal   = $jrn['invoice_total'];
        $invBalance = $jrn['invoice_balance'];
        $totalPaid  = $jrn['total_paid_all'];
        $paidBefore = $jrn['paid_before_this'];
        $thisPmt    = array_sum(array_column($jrn['payments'], 'amount'));
        $pct        = $invTotal > 0 ? min(100, round($totalPaid / $invTotal * 100, 1)) : 0;
        $isFullyPaid = ($invBalance <= 0.005);
        if ($isFullyPaid) {
          $fillColor = '#16a34a'; $pillBg = 'rgba(34,197,94,.12)'; $pillFg = '#16a34a'; $pillBd = 'rgba(34,197,94,.3)'; $pillIcon = 'bi-check-circle-fill'; $pillLabel = 'Fully Paid';
        } elseif ($totalPaid > 0.005) {
          $fillColor = '#f59e0b'; $pillBg = 'rgba(245,158,11,.12)'; $pillFg = '#b45309'; $pillBd = 'rgba(245,158,11,.3)'; $pillIcon = 'bi-hourglass-split'; $pillLabel = 'Partially Paid';
        } else {
          $fillColor = '#ef4444'; $pillBg = 'rgba(239,68,68,.08)'; $pillFg = '#b91c1c'; $pillBd = 'rgba(239,68,68,.25)'; $pillIcon = 'bi-x-circle'; $pillLabel = 'Unpaid';
        }
      ?>
      <div class="ipj">
        <div class="ipj-head ipj-toggle" role="button" tabindex="0" aria-expanded="false">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="ipj-title"><i class="bi bi-receipt me-1"></i>Invoice Payment</span>
            <a href="<?= base_url('customer-invoices/view/' . $jrn['invoice_id']) ?>" class="ipj-inv-link"><?= esc($jrn['invoice_number']) ?> <i class="bi bi-box-arrow-up-right" style="font-size:.65rem"></i></a>
            <?php if (!empty($jrn['issue_date'])): ?><span style="font-size:.72rem;color:var(--gray-400,#94a3b8)"><?= date('d M Y', strtotime($jrn['issue_date'])) ?></span><?php endif; ?>
          </div>
          <div class="d-flex align-items-center gap-2 ms-auto">
            <span style="font-size:.72rem;color:var(--gray-500,#64748b);font-weight:700;white-space:nowrap;">This payment: <?= esc($currency) ?> <?= number_format($thisPmt, 2) ?></span>
            <span class="ipj-status-pill" style="background:<?= $pillBg ?>;color:<?= $pillFg ?>;border:1px solid <?= $pillBd ?>">
              <i class="bi <?= $pillIcon ?>"></i><?= $pillLabel ?>
            </span>
            <i class="bi bi-chevron-down ipj-chevron" style="color:var(--gray-400,#94a3b8);"></i>
          </div>
        </div>
        <div class="ipj-body">
          <div class="ipj-amounts">
            <div class="ipj-amount-cell">
              <span class="label">Invoice Total</span>
              <span class="value"><?= esc($currency) ?> <?= number_format($invTotal, 2) ?></span>
            </div>
            <div class="ipj-amount-cell">
              <span class="label">Total Paid</span>
              <span class="value" style="color:<?= $isFullyPaid ? '#16a34a' : '#d97706' ?>"><?= esc($currency) ?> <?= number_format($totalPaid, 2) ?></span>
            </div>
            <div class="ipj-amount-cell">
              <span class="label">Still Owed</span>
              <span class="value" style="color:<?= $isFullyPaid ? '#16a34a' : ($totalPaid > 0.005 ? '#b45309' : '#b91c1c') ?>"><?= esc($currency) ?> <?= number_format($invBalance, 2) ?></span>
            </div>
          </div>
          <div class="ipj-progress-wrap">
            <div class="ipj-progress-labels">
              <span><?= $pct ?>% paid</span>
              <span><?= esc($currency) ?> <?= number_format($totalPaid, 2) ?> of <?= number_format($invTotal, 2) ?></span>
            </div>
            <div class="ipj-progress-bar">
              <?php if ($paidBefore > 0.005 && !$isFullyPaid): ?>
                <div style="display:flex;height:100%;">
                  <div style="width:<?= min(100, round($paidBefore/$invTotal*100,1)) ?>%;background:#86efac;border-radius:10px 0 0 10px;"></div>
                  <div style="width:<?= min(100-round($paidBefore/$invTotal*100,1), round($thisPmt/$invTotal*100,1)) ?>%;background:<?= $fillColor ?>;"></div>
                </div>
              <?php else: ?>
                <div class="ipj-progress-fill" style="width:<?= $pct ?>%;background:<?= $fillColor ?>;"></div>
              <?php endif; ?>
            </div>
          </div>
          <div style="font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--gray-400,#94a3b8);margin-bottom:.35rem;">
            <i class="bi bi-list-ol me-1"></i>Payments Made Against This Invoice
          </div>
          <div class="ipj-payments-list">
            <?php if ($paidBefore > 0.005): ?>
            <div class="pitem" style="border-color:rgba(34,197,94,.35) !important;background:rgba(34,197,94,.07) !important">
              <div class="pitem-num" style="background:rgba(34,197,94,.15);color:#16a34a">&#10003;</div>
              <div class="pitem-body">
                <div style="font-weight:700;color:var(--gray-700,#1e293b)">Payments before #<?= $paymentId ?></div>
                <div style="color:var(--gray-400,#94a3b8);font-size:.7rem">Combined amount paid before payment #<?= $paymentId ?></div>
              </div>
              <div class="pitem-amt" style="color:#16a34a"><?= esc($currency) ?> <?= number_format($paidBefore, 2) ?></div>
            </div>
            <?php endif; ?>
            <?php $pmtCount = $paidBefore > 0.005 ? 2 : 1; foreach ($jrn['payments'] as $pmt): $pmtAmt = $pmt['amount']; ?>
            <div class="pitem" style="border-color:rgba(37,99,235,.3) !important;background:rgba(37,99,235,.07) !important;">
              <div class="pitem-num" style="background:rgba(37,99,235,.15);color:#2563eb"><?= $pmtCount ?></div>
              <div class="pitem-body">
                <div style="font-weight:700;color:var(--gray-700,#374151)">Payment #<?= $paymentId ?> <span style="font-weight:400;color:var(--gray-400,#94a3b8);font-size:.7rem">&mdash; this payment</span></div>
                <div style="color:var(--gray-400,#94a3b8);font-size:.7rem">
                  <?= $pmt['date'] ? date('d M Y', strtotime($pmt['date'])) : 'N/A' ?>
                  &nbsp;&bull;&nbsp;<?= esc(ucfirst(str_replace('_',' ',$pmt['method']))) ?>
                  &nbsp;&bull;&nbsp;<span style="text-transform:uppercase;font-weight:600;color:<?= $pmt['status']==='posted'?'#16a34a':'#d97706' ?>"><?= esc($pmt['status']) ?></span>
                </div>
              </div>
              <div class="pitem-amt" style="color:#2563eb"><?= esc($currency) ?> <?= number_format($pmtAmt, 2) ?></div>
            </div>
            <?php endforeach; ?>
            <?php if ($isFullyPaid): ?>
            <div class="pitem" style="border-color:rgba(34,197,94,.4) !important;background:rgba(34,197,94,.08) !important">
              <div class="pitem-num" style="background:rgba(34,197,94,.2);color:#16a34a"><i class="bi bi-check2-all" style="font-size:.75rem"></i></div>
              <div class="pitem-body"><div style="font-weight:700;color:#16a34a">Invoice fully settled &mdash; no balance remaining</div></div>
              <div class="pitem-amt" style="color:#16a34a">&#10003;</div>
            </div>
            <?php else: ?>
            <div class="pitem" style="border:1px dashed #f59e0b !important;background:rgba(245,158,11,.08) !important">
              <div class="pitem-num" style="background:rgba(251,191,36,.15);color:#b45309"><i class="bi bi-three-dots" style="font-size:.75rem"></i></div>
              <div class="pitem-body">
                <div style="font-weight:700;color:#b45309">Remaining balance &mdash; <?= esc($currency) ?> <?= number_format($invBalance, 2) ?> still unpaid</div>
                <div style="font-size:.7rem;color:var(--gray-400,#94a3b8)">A further payment is needed to fully close this invoice</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <span class="pitem-amt" style="color:#b45309"><?= esc($currency) ?> <?= number_format($invBalance, 2) ?></span>
                <a href="<?= base_url('accounting/customer-payments/pay?invoice_id=' . $jrn['invoice_id']) ?>" class="btn btn-warning cpv-btn fw-semibold" style="font-size:.68rem;white-space:nowrap;"><i class="bi bi-cash-coin me-1"></i>Receive Payment</a>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (empty($invoiceJourneys) && $isDraft && !empty($openInvoices)): ?>
      <div class="cpv-card mb-2">
        <div class="cpv-hdr">
          <span class="cpv-sec"><i class="bi bi-stack me-1"></i>Allocate to Invoice</span>
          <?php if ($unallocated > 0.005): ?>
            <span class="cpv-badge" style="background:rgba(245,158,11,.1);color:#d97706;border:1px solid rgba(245,158,11,.25)"><i class="bi bi-exclamation-circle me-1"></i>Unallocated: <?= esc($currency) ?> <?= number_format($unallocated, 2) ?></span>
          <?php endif; ?>
        </div>
        <div class="table-responsive">
          <table class="cpv-alloc" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Invoice #</th><th>Date</th><th class="text-end">Invoice Total</th><th class="text-end">Balance Due</th><th class="text-end">Allocate</th></tr></thead>
            <tbody>
              <?php foreach ($openInvoices as $oi):
                $oiId = (int)($oi['id'] ?? 0); $oiNum = $oi['invoice_number'] ?? ('INV-'. $oiId);
                $oiDate = !empty($oi['issue_date']) ? date('d-m-Y', strtotime((string)$oi['issue_date'])) : 'N/A';
                $oiTotal = (float)($oi['total_amount'] ?? 0); $oiOutstand = (float)($oi['outstanding'] ?? 0);
                $shouldAutoSelect = count($openInvoices) === 1 && $unallocated > 0.005;
              ?>
              <tr class="alloc-row">
                <td class="fw-semibold">
                  <label class="d-inline-flex align-items-center gap-2 mb-0" style="cursor:pointer;">
                    <input type="checkbox" class="alloc-chk form-check-input" data-invoice-id="<?= $oiId ?>" data-outstanding="<?= $oiOutstand ?>" <?= $shouldAutoSelect ? 'checked' : '' ?>>
                    <a href="<?= base_url('customer-invoices/view/' . $oiId) ?>" style="color:#2563eb;text-decoration:none;" target="_blank"><?= esc($oiNum) ?></a>
                  </label>
                </td>
                <td><?= esc($oiDate) ?></td>
                <td class="text-end"><?= number_format($oiTotal, 2) ?></td>
                <td class="text-end fw-semibold" style="color:#ef4444"><?= number_format($oiOutstand, 2) ?></td>
                <td class="text-end"><input type="number" step="0.01" min="0" max="<?= $oiOutstand ?>" class="form-control form-control-sm text-end alloc-amt" data-invoice-id="<?= $oiId ?>" value="" placeholder="0.00" disabled style="width:110px;margin-left:auto"></td>
              </tr>
              <?php endforeach; ?>
              <tr><td colspan="5" style="padding:.7rem .65rem;">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                  <div style="font-size:.78rem;color:#64748b;">Total to allocate: <strong id="lbl-alloc-total">0.00</strong> <?= esc($currency) ?> &nbsp;|&nbsp; Remaining: <strong id="lbl-remaining" style="color:<?= $unallocated > 0.005 ? '#d97706' : '#16a34a' ?>"><?= esc($currency) ?> <?= number_format($unallocated, 2) ?></strong></div>
                  <button type="button" id="btn-save-alloc" class="btn btn-primary cpv-btn" disabled><i class="bi bi-save me-1"></i>Save Allocations</button>
                </div>
              </td></tr>
            </tbody>
          </table>
        </div>
      </div>
      <?php elseif (empty($invoiceJourneys) && (!$isDraft || empty($openInvoices))): ?>
      <div class="cpv-card mb-2 p-3 text-muted" style="font-size:.83rem"><i class="bi bi-info-circle me-1"></i>No invoice allocations recorded for this payment.</div>
      <?php endif; ?>
    </div>
    <div class="col-lg-4">
      <div class="cpv-card mb-2">
        <div class="cpv-hdr"><span class="cpv-sec"><i class="bi bi-paperclip me-1"></i>Attachments</span></div>
        <div style="padding:.5rem .75rem;">
          <?php if (!empty($attachments)): ?>
            <?php foreach ($attachments as $att): ?>
              <div class="cpv-att-item">
                <span><i class="bi bi-file-earmark me-1"></i><?= esc($att['original_name'] ?? 'file') ?></span>
                <a href="<?= base_url(ltrim((string)($att['file_path'] ?? ''), '/')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary cpv-btn">Open</a>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="text-muted" style="font-size:.8rem">No attachments.</div>
          <?php endif; ?>
        </div>
      </div>

      <div class="cpv-card mb-2">
        <div class="cpv-hdr"><span class="cpv-sec"><i class="bi bi-bar-chart me-1"></i>Summary</span></div>
        <div style="padding:.5rem .75rem;">
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)"><span style="font-size:.78rem;color:var(--gray-500,#64748b)">Payment Amount</span><span style="font-size:.85rem;font-weight:700;color:var(--gray-700,#1e293b)"><?= esc($currency) ?> <?= number_format($amount, 2) ?></span></div>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)"><span style="font-size:.78rem;color:var(--gray-500,#64748b)">Allocated Invoices</span><span style="font-size:.85rem;font-weight:600"><?= count($allocations ?? []) ?></span></div>
          <?php
            // Show per-invoice balance status in summary
            $summaryAllPaid = true;
            $summaryAnyPartial = false;
            $summaryTotalBalance = 0.0;
            foreach ($invoiceJourneys as $jrn) {
              if ($jrn['invoice_balance'] > 0.005) { $summaryAllPaid = false; $summaryAnyPartial = true; }
              $summaryTotalBalance += $jrn['invoice_balance'];
            }
          ?>
          <?php if (!empty($invoiceJourneys)): ?>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)">
            <span style="font-size:.78rem;color:var(--gray-500,#64748b)">Invoice Status</span>
            <?php if ($summaryAllPaid): ?>
              <span class="cpv-badge" style="background:rgba(34,197,94,.1);color:#16a34a;border:1px solid rgba(34,197,94,.25)"><i class="bi bi-check-circle-fill me-1"></i>Fully Paid</span>
            <?php elseif ($summaryAnyPartial): ?>
              <span class="cpv-badge" style="background:rgba(245,158,11,.1);color:#b45309;border:1px solid rgba(245,158,11,.3)"><i class="bi bi-hourglass-split me-1"></i>Partially Paid</span>
            <?php endif; ?>
          </div>
          <?php if ($summaryAnyPartial): ?>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)">
            <span style="font-size:.78rem;color:var(--gray-500,#64748b)">Total Still Owed</span>
            <span style="font-size:.85rem;font-weight:700;color:#b45309"><?= esc($currency) ?> <?= number_format($summaryTotalBalance, 2) ?></span>
          </div>
          <?php endif; ?>
          <?php endif; ?>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;"><span style="font-size:.78rem;color:var(--gray-500,#64748b)">Journal Entry</span><span style="font-size:.85rem;font-weight:600"><?php if ($postedEntryId > 0): ?><a href="<?= base_url('accounting/journals/view/' . $postedEntryId) ?>" style="color:#2563eb">#<?= $postedEntryId ?></a><?php else: ?><span style="color:#d97706;font-size:.75rem"><i class="bi bi-clock me-1"></i>Pending</span><?php endif; ?></span></div>
        </div>
      </div>

      <?php
        // "Pay Remaining" shortcut â€” show for each partially-paid invoice
        $partialInvoices = array_filter($invoiceJourneys, fn($j) => $j['invoice_balance'] > 0.005);
      ?>
      <?php if (!empty($partialInvoices)): ?>
      <div class="cpv-card mb-2" style="border:1px solid rgba(245,158,11,.4);">
        <div style="padding:.6rem .9rem;">
          <div style="font-size:.78rem;font-weight:700;color:#92400e;margin-bottom:.4rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Remaining Balance to Collect</div>
          <?php foreach ($partialInvoices as $pjrn): ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <div>
              <div style="font-size:.82rem;font-weight:600;color:var(--gray-700,#1e293b)"><?= esc($pjrn['invoice_number']) ?></div>
              <div style="font-size:.72rem;color:var(--gray-400,#94a3b8)">Balance: <?= esc($currency) ?> <?= number_format($pjrn['invoice_balance'], 2) ?></div>
            </div>
            <a href="<?= base_url('accounting/customer-payments/pay?invoice_id=' . $pjrn['invoice_id']) ?>"
               class="btn btn-sm btn-warning cpv-btn fw-semibold"
               style="font-size:.72rem">
              <i class="bi bi-cash-coin me-1"></i>Pay <?= esc($currency) ?> <?= number_format($pjrn['invoice_balance'], 2) ?>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($isDraft): ?>
      <div class="d-flex gap-2">
        <a href="<?= base_url('accounting/customer-payments/' . $paymentId . '/edit') ?>" class="btn btn-outline-primary cpv-btn"><i class="bi bi-pencil-square me-1"></i>Edit Draft</a>
        <button id="btn-delete" class="btn btn-outline-danger cpv-btn"><i class="bi bi-trash me-1"></i>Delete Draft</button>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(() => {
  const csrfName = '<?= csrf_token() ?>';
  const csrfValue = '<?= csrf_hash() ?>';
  const paymentId = <?= json_encode((int)$paymentId) ?>;
  const customerId = <?= json_encode((int)$customerId) ?>;
  const paymentMethod = <?= json_encode((string)$method) ?>;
  const paymentDate = <?= json_encode((string)($date ?: date('Y-m-d'))) ?>;
  const sourceAccountId = <?= json_encode((int)$sourceAccountId) ?>;
  const memo = <?= json_encode((string)$memo) ?>;
  const notes = <?= json_encode((string)$notes) ?>;
  const currency = <?= json_encode((string)$currency) ?>;
  const paymentAmount = <?= json_encode((float)$amount) ?>;

  const btnPost = document.querySelector('.js-confirm-payment');
  const deleteBtn = document.getElementById('btn-delete');
  const btnSaveAlloc = document.getElementById('btn-save-alloc');
  const lblRemaining = document.getElementById('lbl-remaining');
  const lblTotal = document.getElementById('lbl-alloc-total');
  const allocationChecks = Array.from(document.querySelectorAll('.alloc-chk'));
  const allocationInputs = Array.from(document.querySelectorAll('.alloc-amt'));

  const confirmEndpoint = '<?= base_url('accounting/customer-payments/confirm') ?>';
  const deleteEndpointBase = '<?= base_url('accounting/customer-payments/') ?>';
  const draftEndpoint = '<?= base_url('accounting/customer-payments/draft') ?>';

  function parseMoney(value) {
    const parsed = parseFloat(String(value ?? '').replace(/,/g, '').trim());
    return Number.isFinite(parsed) ? parsed : 0;
  }

  // Keep invoice journey collapsed by default so focus stays on the single payment summary.
  document.querySelectorAll('.ipj .ipj-toggle').forEach((toggle) => {
    const card = toggle.closest('.ipj');
    if (!card) return;
    const toggleCard = () => {
      const isOpen = card.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    };
    toggle.addEventListener('click', toggleCard);
    toggle.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleCard();
      }
    });
  });

  if (btnPost) {
    btnPost.addEventListener('click', async () => {
      if (!paymentId) return;
      if (!window.confirm('Post this customer payment? This will create journal entries.')) return;

      const formData = new FormData();
      formData.append(csrfName, csrfValue);
      formData.append('payment_id', String(paymentId));

      const original = btnPost.innerHTML;
      btnPost.disabled = true;
      btnPost.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';

      try {
        const resp = await fetch(confirmEndpoint, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData,
        });
        const data = await resp.json();
        if (data && data.success) {
          window.location.reload();
          return;
        }
        alert((data && data.message) ? data.message : 'Failed to post payment.');
      } catch (error) {
        alert('Network error while posting payment.');
      }

      btnPost.disabled = false;
      btnPost.innerHTML = original;
    });
  }

  if (deleteBtn) {
    deleteBtn.addEventListener('click', async () => {
      if (!paymentId) return;
      if (!window.confirm('Delete this draft payment?')) return;

      const formData = new FormData();
      formData.append(csrfName, csrfValue);

      const original = deleteBtn.innerHTML;
      deleteBtn.disabled = true;
      deleteBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Deleting...';

      try {
        const resp = await fetch(deleteEndpointBase + paymentId + '/delete', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData,
        });
        const data = await resp.json();
        if (data && data.success) {
          window.location = '<?= base_url('accounting/customer-payments') ?>';
          return;
        }
        alert((data && data.message) ? data.message : 'Delete failed.');
      } catch (error) {
        alert('Network error while deleting payment.');
      }

      deleteBtn.disabled = false;
      deleteBtn.innerHTML = original;
    });
  }

  function findAmountInput(invoiceId) {
    return document.querySelector('.alloc-amt[data-invoice-id="' + invoiceId + '"]');
  }

  function calculateAllocatedTotal() {
    return allocationInputs.reduce((sum, input) => {
      if (input.disabled) return sum;
      return sum + parseMoney(input.value);
    }, 0);
  }

  function updateAllocationSummary() {
    const total = Math.round(calculateAllocatedTotal() * 100) / 100;
    const remaining = Math.round((paymentAmount - total) * 100) / 100;

    if (lblTotal) {
      lblTotal.textContent = total.toFixed(2);
    }
    if (lblRemaining) {
      lblRemaining.textContent = currency + ' ' + remaining.toFixed(2);
      lblRemaining.style.color = remaining < -0.005 ? '#ef4444' : (remaining > 0.005 ? '#d97706' : '#16a34a');
    }
    if (btnSaveAlloc) {
      btnSaveAlloc.disabled = !(total > 0 && Math.abs(remaining) <= 0.005);
    }
  }

  function enableAllocationRow(checkbox, preserveValue = false) {
    const invoiceId = checkbox.dataset.invoiceId;
    const outstanding = parseMoney(checkbox.dataset.outstanding);
    const input = findAmountInput(invoiceId);
    if (!input) return;

    input.disabled = false;
    input.max = String(outstanding);
    if (!preserveValue || parseMoney(input.value) <= 0) {
      const usedElsewhere = allocationInputs.reduce((sum, current) => {
        if (current === input || current.disabled) return sum;
        return sum + parseMoney(current.value);
      }, 0);
      const remainingForThisRow = Math.max(0, paymentAmount - usedElsewhere);
      const suggested = Math.min(outstanding, remainingForThisRow);
      input.value = suggested > 0 ? suggested.toFixed(2) : '';
    }
  }

  function disableAllocationRow(checkbox) {
    const invoiceId = checkbox.dataset.invoiceId;
    const input = findAmountInput(invoiceId);
    if (!input) return;
    input.value = '';
    input.disabled = true;
  }

  function collectAllocations() {
    const rows = [];
    allocationChecks.forEach((checkbox) => {
      if (!checkbox.checked) return;
      const invoiceId = parseInt(checkbox.dataset.invoiceId || '0', 10);
      const input = findAmountInput(invoiceId);
      const amount = parseMoney(input ? input.value : 0);
      if (invoiceId > 0 && amount > 0) {
        rows.push({
          invoice_id: invoiceId,
          amount: amount,
          cash_amount: amount,
          advance_amount: 0,
        });
      }
    });
    return rows;
  }

  async function saveAllocationsToDraft() {
    const allocations = collectAllocations();
    if (!allocations.length) {
      return { success: false, message: 'Select at least one invoice allocation.' };
    }

    const formData = new FormData();
    formData.append(csrfName, csrfValue);
    formData.append('payment_id', String(paymentId));
    formData.append('customer_id', String(customerId));
    formData.append('payment_method', paymentMethod);
    formData.append('payment_type', 'settlement');
    formData.append('source_account_id', String(sourceAccountId));
    formData.append('payment_date', paymentDate);
    formData.append('currency_code', currency);
    formData.append('memo', memo);
    formData.append('notes', notes);
    formData.append('allocations', JSON.stringify(allocations));

    try {
      const response = await fetch(draftEndpoint, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const data = await response.json();
      return data || { success: false, message: 'Failed to save allocations.' };
    } catch (error) {
      return { success: false, message: 'Network error while saving allocations.' };
    }
  }

  allocationChecks.forEach((checkbox) => {
    checkbox.addEventListener('change', function () {
      if (this.checked) {
        enableAllocationRow(this, false);
      } else {
        disableAllocationRow(this);
      }
      updateAllocationSummary();
    });
  });

  allocationInputs.forEach((input) => {
    input.addEventListener('input', function () {
      const max = parseMoney(this.max);
      let value = parseMoney(this.value);
      if (value < 0) value = 0;
      if (max > 0 && value > max) value = max;
      this.value = value > 0 ? String(value) : '';
      updateAllocationSummary();
    });
    input.addEventListener('blur', function () {
      const value = parseMoney(this.value);
      this.value = value > 0 ? value.toFixed(2) : '';
      updateAllocationSummary();
    });
  });

  allocationChecks.forEach((checkbox) => {
    if (checkbox.checked) {
      enableAllocationRow(checkbox, true);
    }
  });
  updateAllocationSummary();

  if (btnSaveAlloc) {
    btnSaveAlloc.addEventListener('click', async () => {
      btnSaveAlloc.disabled = true;
      const original = btnSaveAlloc.innerHTML;
      btnSaveAlloc.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

      const result = await saveAllocationsToDraft();
      if (result.success) {
        window.location.reload();
        return;
      }

      alert(result.message || 'Failed to save allocations.');
      btnSaveAlloc.innerHTML = original;
      updateAllocationSummary();
    });
  }
})();
</script>
<?= $this->endSection() ?>
