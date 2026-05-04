<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Customer Payments<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $drafts = $drafts ?? [];
  $posted = $posted ?? [];
  $voided = $voided ?? [];
  $filteredInvoice = $filteredInvoice ?? null;
  $filterInvoiceId = (int)($filterInvoiceId ?? 0);

  $currencySymbols = [
    'USD' => '$',
    'PKR' => 'Rs',
    'EUR' => 'EUR',
    'GBP' => 'GBP',
    'AED' => 'AED',
    'SAR' => 'SAR',
  ];

  $fmtCurrency = static function($code, $amount) use ($currencySymbols) {
    $ccy = strtoupper(trim((string)($code ?: 'PKR')));
    $sym = $currencySymbols[$ccy] ?? $ccy;
    return $ccy . ' ' . $sym . ' ' . number_format((float)$amount, 2);
  };

  $sumByCurrency = static function(array $rows) {
    $map = [];
    foreach ($rows as $r) {
      $ccy = strtoupper(trim((string)($r['currency_code'] ?? 'PKR')));
      if ($ccy === '') $ccy = 'PKR';
      $map[$ccy] = ($map[$ccy] ?? 0.0) + (float)($r['amount'] ?? 0);
    }
    return $map;
  };

  $renderCurrencySummary = static function(array $map) use ($fmtCurrency) {
    if (empty($map)) return '0.00';
    $parts = [];
    foreach ($map as $ccy => $amt) {
      $parts[] = $fmtCurrency($ccy, $amt);
    }
    return implode(' | ', $parts);
  };
?>

<style>
  .cpl { max-width:1440px; margin:0 auto; }
  .cpl-card { background:var(--white,#fff); border:1px solid var(--gray-200,#e2e8f0); border-radius:.5rem; overflow:hidden; }
  .cpl-stats { display:flex; gap:.5rem; flex-wrap:wrap; }
  .cpl-stat { flex:1; min-width:120px; padding:.45rem .75rem; border-radius:.4rem; border:1px solid var(--gray-200,#e2e8f0); background:var(--white,#fff); }
  .cpl-stat-label { font-size:.62rem; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-400,#94a3b8); font-weight:600; }
  .cpl-stat-val { font-size:1rem; font-weight:700; color:var(--gray-700,#1e293b); margin-top:1px; }
  .cpl .currency { font-size:.62rem; color:var(--gray-400,#94a3b8); margin-right:2px; }
  .cpl .amt { font-weight:700; font-family:'Courier New',monospace; font-size:.82rem; }

  .cpl-tabs { display:flex; border-bottom:2px solid var(--gray-200,#e2e8f0); }
  .cpl-tab { padding:.5rem 1.2rem; font-size:.78rem; font-weight:600; cursor:pointer; border:none; background:none; color:var(--gray-400,#94a3b8); border-bottom:2px solid transparent; margin-bottom:-2px; display:flex; gap:6px; align-items:center; }
  .cpl-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
  .cpl-count { display:inline-flex; align-items:center; justify-content:center; min-width:18px; height:18px; border-radius:9px; font-size:.65rem; font-weight:700; padding:0 5px; }
  .cpl-count-draft { background:rgba(245,158,11,.12); color:#d97706; }
  .cpl-count-posted { background:rgba(34,197,94,.12); color:#16a34a; }
  .cpl-count-void { background:rgba(239,68,68,.12); color:#ef4444; }
  .cpl-tab-panel { display:none; }
  .cpl-tab-panel.active { display:block; }

  .cpl table { margin:0; width:100%; }
  .cpl table th { font-size:.68rem; text-transform:uppercase; letter-spacing:.5px; font-weight:600; padding:.45rem .65rem; white-space:nowrap; background:var(--gray-50,#f8fafc); color:var(--gray-500,#64748b); border-bottom:2px solid var(--gray-200,#e2e8f0); }
  .cpl table td { padding:.45rem .65rem; font-size:.8rem; vertical-align:middle; border-bottom:1px solid var(--gray-100,#f1f5f9); color:var(--gray-600,#475569); }
  .cpl .cust-name { font-weight:600; color:var(--gray-700,#1e293b); font-size:.8rem; }
  .cpl .action-btn { padding:.2rem .5rem; font-size:.7rem; font-weight:600; border-radius:.25rem; }
  .cpl .empty-msg { padding:1.5rem 1rem; text-align:center; color:var(--gray-400,#94a3b8); font-size:.82rem; }

  .badge-method { display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:3px; font-size:.65rem; font-weight:600; letter-spacing:.2px; }
  .method-cheque { background:rgba(139,92,246,.1); color:#7c3aed; border:1px solid rgba(139,92,246,.2); }
  .method-cash { background:rgba(34,197,94,.08); color:#16a34a; border:1px solid rgba(34,197,94,.2); }
  .method-bank { background:rgba(37,99,235,.08); color:#2563eb; border:1px solid rgba(37,99,235,.2); }
  .method-online_transfer { background:rgba(245,158,11,.08); color:#d97706; border:1px solid rgba(245,158,11,.2); }
</style>

<div class="container-fluid px-3 py-2 cpl">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-0 fw-bold" style="color:var(--gray-700,#1e293b)"><i class="bi bi-wallet2 me-2"></i>Customer Payments</h5>
      <small style="color:var(--gray-400,#94a3b8)">Manage drafts, posted, and voided customer payments</small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= base_url('accounting/customer-payments/pay') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>Receive Payment</a>
      <a href="<?= base_url('accounting/customer-payments') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-clockwise me-1"></i>Refresh</a>
    </div>
  </div>

  <?php if ($filterInvoiceId > 0): ?>
    <div class="alert alert-info py-2 d-flex justify-content-between align-items-center">
      <div>
        Showing payments for invoice
        <strong><?= esc($filteredInvoice['invoice_number'] ?? ('INV-' . $filterInvoiceId)) ?></strong>
      </div>
      <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-primary" href="<?= base_url('customer-invoices/view/' . $filterInvoiceId) ?>">Open Invoice</a>
        <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('accounting/customer-payments') ?>">Clear Filter</a>
      </div>
    </div>
  <?php endif; ?>

  <?php
    $draftTotalsByCurrency = $sumByCurrency($drafts);
    $postedTotalsByCurrency = $sumByCurrency($posted);
    $hasVoided = !empty($voided);
    $allPayments = [];
    foreach ($drafts as $__r) { $__r['_ui_status'] = 'draft'; $allPayments[] = $__r; }
    foreach ($posted as $__r) { $__r['_ui_status'] = 'posted'; $allPayments[] = $__r; }
    foreach ($voided as $__r) { $__r['_ui_status'] = 'void'; $allPayments[] = $__r; }
    usort($allPayments, static function($a, $b) {
      $da = strtotime((string)($a['payment_date'] ?? '1970-01-01')) ?: 0;
      $db = strtotime((string)($b['payment_date'] ?? '1970-01-01')) ?: 0;
      if ($da === $db) {
        return ((int)($b['id'] ?? 0)) <=> ((int)($a['id'] ?? 0));
      }
      return $db <=> $da;
    });
  ?>

  <div class="cpl-stats mb-2">
    <div class="cpl-stat"><div class="cpl-stat-label">Drafts</div><div class="cpl-stat-val" style="color:#d97706"><?= count($drafts) ?></div></div>
    <div class="cpl-stat"><div class="cpl-stat-label">Draft Amount</div><div class="cpl-stat-val" style="font-size:.85rem"><?= esc($renderCurrencySummary($draftTotalsByCurrency)) ?></div></div>
    <div class="cpl-stat"><div class="cpl-stat-label">Posted</div><div class="cpl-stat-val" style="color:#16a34a"><?= count($posted) ?></div></div>
    <div class="cpl-stat"><div class="cpl-stat-label">Posted Amount</div><div class="cpl-stat-val" style="font-size:.85rem"><?= esc($renderCurrencySummary($postedTotalsByCurrency)) ?></div></div>
  </div>

  <div class="cpl-card mb-3" id="drafts-section">
    <div class="cpl-tabs">
      <button class="cpl-tab active" data-tab="all"><i class="bi bi-collection" style="color:#2563eb;font-size:.8rem"></i>All <span class="cpl-count" style="background:rgba(37,99,235,.12);color:#2563eb"><?= count($allPayments) ?></span></button>
      <button class="cpl-tab" data-tab="drafts"><i class="bi bi-hourglass-split" style="color:#d97706;font-size:.8rem"></i>Drafts <span class="cpl-count cpl-count-draft"><?= count($drafts) ?></span></button>
      <button class="cpl-tab" data-tab="posted"><i class="bi bi-check-circle" style="color:#16a34a;font-size:.8rem"></i>Posted <span class="cpl-count cpl-count-posted"><?= count($posted) ?></span></button>
      <?php if ($hasVoided): ?>
      <button class="cpl-tab" data-tab="voided"><i class="bi bi-x-circle" style="color:#ef4444;font-size:.8rem"></i>Voided <span class="cpl-count cpl-count-void"><?= count($voided) ?></span></button>
      <?php endif; ?>
    </div>

    <div class="cpl-tab-panel active" id="panel-all">
      <?php if (empty($allPayments)): ?>
        <div class="empty-msg"><i class="bi bi-inbox me-1"></i>No payments found</div>
      <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Status</th>
              <th>Method</th>
              <th class="text-end">Amount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($allPayments as $row):
            $id = (int)($row['id'] ?? 0);
            $status = strtolower((string)($row['_ui_status'] ?? 'draft'));
            $statusBadge = $status === 'posted'
              ? '<span class="badge bg-success">POSTED</span>'
              : ($status === 'void' ? '<span class="badge bg-danger">VOID</span>' : '<span class="badge bg-warning text-dark">DRAFT</span>');
            $method = strtolower((string)($row['payment_method'] ?? 'cash'));
            $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
            $fmtDate = !empty($row['payment_date']) ? date('d:m:Y', strtotime($row['payment_date'])) : '-';
            $rowCurrency = strtoupper(trim((string)($row['currency_code'] ?? 'PKR')));
            if ($rowCurrency === '') $rowCurrency = 'PKR';
          ?>
            <tr<?= $status === 'void' ? ' style="opacity:.6"' : '' ?>>
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td class="cust-name"><?= esc($row['customer_name'] ?? '-') ?></td>
              <td><?= $statusBadge ?></td>
              <td><span class="badge-method method-<?= esc($method) ?>"><i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i><?= esc(ucfirst(str_replace('_', ' ', $method))) ?></span></td>
              <td class="text-end"><span class="amt"><?= esc($fmtCurrency($rowCurrency, (float)($row['amount'] ?? 0))) ?></span></td>
              <td>
                <div class="d-flex gap-1">
                  <a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/customer-payments/view/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a>
                  <?php if ($status === 'draft'): ?>
                    <button type="button" class="btn btn-success action-btn js-confirm-payment" data-payment-id="<?= $id ?>"><i class="bi bi-check-lg me-1"></i>Post</button>
                    <a href="<?= base_url('accounting/customer-payments/' . $id . '/edit') ?>" class="btn btn-sm btn-outline-primary action-btn">Edit</a>
                    <button type="button" class="btn btn-sm btn-outline-danger action-btn js-delete-payment" data-payment-id="<?= $id ?>">Delete</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="cpl-tab-panel" id="panel-drafts">
      <?php if (empty($drafts)): ?>
        <div class="empty-msg"><i class="bi bi-inbox me-1"></i>No pending drafts</div>
      <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Date</th>
              <th>Customer</th>
              <th>Method</th>
              <th class="text-end">Amount</th>
              <th class="text-center">Files</th>
              <th class="text-center">Invoices</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($drafts as $row):
            $id = (int)($row['id'] ?? 0);
            $method = strtolower((string)($row['payment_method'] ?? 'cash'));
            $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
            $fmtDate = !empty($row['payment_date']) ? date('d:m:Y', strtotime($row['payment_date'])) : '-';
            $rowCurrency = strtoupper(trim((string)($row['currency_code'] ?? 'PKR')));
            if ($rowCurrency === '') $rowCurrency = 'PKR';
          ?>
            <tr>
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td class="cust-name"><?= esc($row['customer_name'] ?? '-') ?></td>
              <td>
                <span class="badge-method method-<?= esc($method) ?>">
                  <i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i>
                  <?= esc(ucfirst(str_replace('_', ' ', $method))) ?>
                </span>
              </td>
              <td class="text-end"><span class="amt"><?= esc($fmtCurrency($rowCurrency, (float)($row['amount'] ?? 0))) ?></span></td>
              <td class="text-center"><?php $ac=(int)($row['attachment_count'] ?? 0); ?><?= $ac > 0 ? '<span class="badge bg-secondary" style="font-size:.6rem">'.$ac.'</span>' : '<span style="color:var(--gray-300,#cbd5e1)">—</span>' ?></td>
              <td class="text-center"><?php $alc=(int)($row['allocation_count'] ?? 0); ?><?= $alc > 0 ? '<span class="badge bg-secondary" style="font-size:.6rem">'.$alc.'</span>' : '<span style="color:var(--gray-300,#cbd5e1)">—</span>' ?></td>
              <td>
                <div class="d-flex gap-1">
                  <a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/customer-payments/view/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a>
                  <button type="button" class="btn btn-success action-btn js-confirm-payment" data-payment-id="<?= $id ?>"><i class="bi bi-check-lg me-1"></i>Post</button>
                  <a href="<?= base_url('accounting/customer-payments/' . $id . '/edit') ?>" class="btn btn-sm btn-outline-primary action-btn">Edit</a>
                  <button type="button" class="btn btn-sm btn-outline-danger action-btn js-delete-payment" data-payment-id="<?= $id ?>">Delete</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="cpl-tab-panel" id="panel-posted">
      <?php if (empty($posted)): ?>
        <div class="empty-msg"><i class="bi bi-inbox me-1"></i>No posted payments yet</div>
      <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($posted as $row):
            $id = (int)($row['id'] ?? 0);
            $method = strtolower((string)($row['payment_method'] ?? 'cash'));
            $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
            $fmtDate = !empty($row['payment_date']) ? date('d:m:Y', strtotime($row['payment_date'])) : '-';
            $rowCurrency = strtoupper(trim((string)($row['currency_code'] ?? 'PKR')));
            if ($rowCurrency === '') $rowCurrency = 'PKR';
          ?>
            <tr>
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td class="cust-name"><?= esc($row['customer_name'] ?? '-') ?></td>
              <td><span class="badge-method method-<?= esc($method) ?>"><i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i><?= esc(ucfirst(str_replace('_', ' ', $method))) ?></span></td>
              <td class="text-end"><span class="amt"><?= esc($fmtCurrency($rowCurrency, (float)($row['amount'] ?? 0))) ?></span></td>
              <td><a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/customer-payments/view/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <?php if ($hasVoided): ?>
    <div class="cpl-tab-panel" id="panel-voided">
      <div class="table-responsive">
        <table>
          <thead><tr><th>#</th><th>Date</th><th>Customer</th><th>Method</th><th class="text-end">Amount</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach ($voided as $row):
            $id = (int)($row['id'] ?? 0);
            $method = strtolower((string)($row['payment_method'] ?? 'cash'));
            $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
            $fmtDate = !empty($row['payment_date']) ? date('d:m:Y', strtotime($row['payment_date'])) : '-';
            $rowCurrency = strtoupper(trim((string)($row['currency_code'] ?? 'PKR')));
            if ($rowCurrency === '') $rowCurrency = 'PKR';
          ?>
            <tr style="opacity:.6">
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td class="cust-name"><?= esc($row['customer_name'] ?? '-') ?></td>
              <td><span class="badge-method method-<?= esc($method) ?>"><i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i><?= esc(ucfirst(str_replace('_', ' ', $method))) ?></span></td>
              <td class="text-end"><span class="amt" style="text-decoration:line-through"><?= esc($fmtCurrency($rowCurrency, (float)($row['amount'] ?? 0))) ?></span></td>
              <td><a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/customer-payments/view/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
(() => {
  document.querySelectorAll('.cpl-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.cpl-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.cpl-tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      const panel = document.getElementById('panel-' + tab.dataset.tab);
      if (panel) panel.classList.add('active');
    });
  });

  const csrfName = '<?= csrf_token() ?>';
  const csrfValue = '<?= csrf_hash() ?>';
  const confirmEndpoint = '<?= base_url('accounting/customer-payments/confirm') ?>';
  const deleteEndpointBase = '<?= base_url('accounting/customer-payments/') ?>';

  document.querySelectorAll('.js-confirm-payment').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const paymentId = btn.getAttribute('data-payment-id');
      if (!paymentId) return;
      if (!window.confirm('Post this customer payment? This will create journal entries.')) return;

      const formData = new FormData();
      formData.append(csrfName, csrfValue);
      formData.append('payment_id', paymentId);

      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';

      try {
        const resp = await fetch(confirmEndpoint, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const data = await resp.json();
        if (data && data.success) {
          window.location.reload();
          return;
        }
        alert((data && data.message) ? data.message : 'Failed to post payment.');
      } catch (e) {
        alert('Network error while posting payment.');
      }

      btn.disabled = false;
      btn.innerHTML = original;
    });
  });

  document.querySelectorAll('.js-delete-payment').forEach((btn) => {
    btn.addEventListener('click', async () => {
      const paymentId = btn.getAttribute('data-payment-id');
      if (!paymentId) return;
      if (!window.confirm('Delete this draft payment?')) return;

      const formData = new FormData();
      formData.append(csrfName, csrfValue);

      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = 'Deleting...';

      try {
        const resp = await fetch(deleteEndpointBase + paymentId + '/delete', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: formData
        });
        const data = await resp.json();
        if (data && data.success) {
          window.location.reload();
          return;
        }
        alert((data && data.message) ? data.message : 'Failed to delete payment.');
      } catch (e) {
        alert('Network error while deleting payment.');
      }

      btn.disabled = false;
      btn.innerHTML = original;
    });
  });
})();
</script>
<?= $this->endSection() ?>
