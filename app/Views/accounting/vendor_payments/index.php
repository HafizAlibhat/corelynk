<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Vendor Payments<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $drafts = $drafts ?? [];
  $posted = $posted ?? [];
  $voided = $voided ?? [];
  $savedId = (int)($_GET['saved'] ?? 0);
?>

<style>
  .vpl { max-width:1440px; margin:0 auto; }
  .vpl-card {
    background:var(--white,#fff); border:1px solid var(--gray-200,#e2e8f0);
    border-radius:.5rem; overflow:hidden;
  }
  .vpl-hdr {
    padding:.6rem 1rem; display:flex; align-items:center; justify-content:space-between;
    border-bottom:1px solid var(--gray-200,#e2e8f0);
  }
  .vpl-hdr-title { font-size:.85rem; font-weight:700; color:var(--gray-700,#1e293b); letter-spacing:.2px; }
  .vpl-count {
    display:inline-flex; align-items:center; justify-content:center;
    min-width:18px; height:18px; border-radius:9px; font-size:.65rem; font-weight:700;
    padding:0 5px;
  }
  .vpl-count-draft { background:rgba(245,158,11,.12); color:#d97706; }
  .vpl-count-posted { background:rgba(34,197,94,.12); color:#16a34a; }
  .vpl-count-void { background:rgba(239,68,68,.12); color:#ef4444; }

  .vpl table { margin:0; width:100%; }
  .vpl table th {
    font-size:.68rem; text-transform:uppercase; letter-spacing:.5px; font-weight:600;
    padding:.45rem .65rem; white-space:nowrap;
    background:var(--gray-50,#f8fafc); color:var(--gray-500,#64748b);
    border-bottom:2px solid var(--gray-200,#e2e8f0);
  }
  .vpl table td {
    padding:.45rem .65rem; font-size:.8rem; vertical-align:middle;
    border-bottom:1px solid var(--gray-100,#f1f5f9); color:var(--gray-600,#475569);
  }
  .vpl table tbody tr { transition:background .1s; }
  .vpl table tbody tr:hover { background:var(--gray-50,#f8fafc); }
  .vpl table tbody tr.row-highlight { animation:rowFlash 2s ease; }
  @keyframes rowFlash { 0%,40%{background:rgba(59,130,246,.12)} 100%{background:transparent} }

  .vpl .empty-msg {
    padding:1.5rem 1rem; text-align:center; color:var(--gray-400,#94a3b8); font-size:.82rem;
  }

  /* Badges */
  .vpl .badge-type {
    display:inline-block; padding:2px 7px; border-radius:3px; font-size:.65rem; font-weight:600;
    text-transform:uppercase; letter-spacing:.3px;
  }
  .badge-settlement { background:rgba(37,99,235,.1); color:#2563eb; border:1px solid rgba(37,99,235,.2); }
  .badge-advance { background:rgba(220,53,69,.1); color:#dc3545; border:1px solid rgba(220,53,69,.2); }

  .vpl .badge-method {
    display:inline-flex; align-items:center; gap:3px; padding:2px 7px; border-radius:3px;
    font-size:.65rem; font-weight:600; letter-spacing:.2px;
  }
  .method-cheque { background:rgba(139,92,246,.1); color:#7c3aed; border:1px solid rgba(139,92,246,.2); }
  .method-cash { background:rgba(34,197,94,.08); color:#16a34a; border:1px solid rgba(34,197,94,.2); }
  .method-bank { background:rgba(37,99,235,.08); color:#2563eb; border:1px solid rgba(37,99,235,.2); }
  .method-online_transfer { background:rgba(245,158,11,.08); color:#d97706; border:1px solid rgba(245,158,11,.2); }

  .vpl .amt { font-weight:700; font-family:'Courier New',monospace; font-size:.82rem; }
  .vpl .currency { font-size:.62rem; color:var(--gray-400,#94a3b8); margin-right:2px; }

  .vpl .vendor-name { font-weight:600; color:var(--gray-700,#1e293b); font-size:.8rem; }
  .vpl .vendor-src { font-size:.66rem; color:var(--gray-400,#94a3b8); }

  .vpl .action-btn {
    padding:.2rem .5rem; font-size:.7rem; font-weight:600; border-radius:.25rem;
  }

  /* Toast notification */
  .vpl-toast {
    position:fixed; top:80px; right:24px; z-index:9999;
    padding:.5rem 1rem; border-radius:.4rem; font-size:.82rem; font-weight:600;
    background:#16a34a; color:#fff; box-shadow:0 4px 20px rgba(0,0,0,.15);
    animation:toastSlideIn .3s ease, toastFadeOut .5s ease 3s forwards;
  }
  @keyframes toastSlideIn { from{transform:translateX(100px);opacity:0} to{transform:translateX(0);opacity:1} }
  @keyframes toastFadeOut { to{opacity:0;transform:translateY(-10px)} }

  /* Stats row */
  .vpl-stats { display:flex; gap:.5rem; flex-wrap:wrap; }
  .vpl-stat {
    flex:1; min-width:120px; padding:.45rem .75rem; border-radius:.4rem;
    border:1px solid var(--gray-200,#e2e8f0); background:var(--white,#fff);
  }
  .vpl-stat-label { font-size:.62rem; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-400,#94a3b8); font-weight:600; }
  .vpl-stat-val { font-size:1rem; font-weight:700; color:var(--gray-700,#1e293b); margin-top:1px; }

  /* Tabs */
  .vpl-tabs {
    display:flex; gap:0; border-bottom:2px solid var(--gray-200,#e2e8f0); margin-bottom:0;
  }
  .vpl-tab {
    padding:.5rem 1.2rem; font-size:.78rem; font-weight:600; cursor:pointer;
    border:none; background:none; color:var(--gray-400,#94a3b8);
    border-bottom:2px solid transparent; margin-bottom:-2px;
    display:flex; align-items:center; gap:6px; transition:all .15s;
  }
  .vpl-tab:hover { color:var(--gray-600,#475569); }
  .vpl-tab.active { color:#2563eb; border-bottom-color:#2563eb; }
  .vpl-tab-panel { display:none; }
  .vpl-tab-panel.active { display:block; }
</style>

<div class="container-fluid px-3 py-2 vpl">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h5 class="mb-0 fw-bold" style="color:var(--gray-700,#1e293b)"><i class="bi bi-wallet2 me-2"></i>Vendor Payments</h5>
      <small style="color:var(--gray-400,#94a3b8)">Manage drafts, posted, and voided vendor payments</small>
    </div>
    <div class="d-flex gap-2">
      <a href="<?= base_url('accounting/vendor-payments/pay') ?>" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Payment</a>
      <a href="<?= base_url('accounting/cheques') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-receipt me-1"></i>Cheques</a>
    </div>
  </div>

  <?php if ($savedId > 0): ?>
    <div class="vpl-toast" id="saved_toast"><i class="bi bi-check-circle me-1"></i> Payment #<?= $savedId ?> saved as draft</div>
  <?php endif; ?>

  <!-- Stats -->
  <?php
    $totalDraftAmt = array_sum(array_column($drafts, 'amount'));
    $totalPostedAmt = array_sum(array_column($posted, 'amount'));
    $hasVoided = !empty($voided);
  ?>
  <div class="vpl-stats mb-2">
    <div class="vpl-stat">
      <div class="vpl-stat-label">Drafts</div>
      <div class="vpl-stat-val" style="color:#d97706"><?= count($drafts) ?></div>
    </div>
    <div class="vpl-stat">
      <div class="vpl-stat-label">Draft Amount</div>
      <div class="vpl-stat-val"><span class="currency">PKR</span><?= number_format($totalDraftAmt, 2) ?></div>
    </div>
    <div class="vpl-stat">
      <div class="vpl-stat-label">Posted</div>
      <div class="vpl-stat-val" style="color:#16a34a"><?= count($posted) ?></div>
    </div>
    <div class="vpl-stat">
      <div class="vpl-stat-label">Posted Amount</div>
      <div class="vpl-stat-val"><span class="currency">PKR</span><?= number_format($totalPostedAmt, 2) ?></div>
    </div>
    <?php if ($hasVoided): ?>
    <div class="vpl-stat">
      <div class="vpl-stat-label">Voided</div>
      <div class="vpl-stat-val" style="color:#ef4444"><?= count($voided) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Tabbed Card -->
  <div class="vpl-card mb-3">
    <div class="vpl-tabs">
      <button class="vpl-tab active" data-tab="drafts">
        <i class="bi bi-hourglass-split" style="color:#d97706;font-size:.8rem"></i>Drafts
        <span class="vpl-count vpl-count-draft"><?= count($drafts) ?></span>
      </button>
      <button class="vpl-tab" data-tab="posted">
        <i class="bi bi-check-circle" style="color:#16a34a;font-size:.8rem"></i>Posted
        <span class="vpl-count vpl-count-posted"><?= count($posted) ?></span>
      </button>
      <?php if ($hasVoided): ?>
      <button class="vpl-tab" data-tab="voided">
        <i class="bi bi-x-circle" style="color:#ef4444;font-size:.8rem"></i>Voided
        <span class="vpl-count vpl-count-void"><?= count($voided) ?></span>
      </button>
      <?php endif; ?>
    </div>

    <!-- Drafts Panel -->
    <div class="vpl-tab-panel active" id="panel-drafts">
      <?php if (empty($drafts)): ?>
        <div class="empty-msg"><i class="bi bi-inbox me-1"></i>No pending drafts</div>
      <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th style="width:45px">#</th>
              <th>Date</th>
              <th>Vendor</th>
              <th>Type</th>
              <th>Method</th>
              <th class="text-end">Amount</th>
              <th>Source Account</th>
              <th style="width:50px" class="text-center">Files</th>
              <th style="width:50px" class="text-center">Bills</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($drafts as $row):
              $id = (int)($row['id'] ?? 0);
              $type = strtolower($row['payment_type'] ?? 'settlement');
              $method = strtolower($row['payment_method'] ?? 'cash');
              $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
              $rawDate = $row['payment_date'] ?? '';
              $fmtDate = $rawDate ? date('d:m:Y', strtotime($rawDate)) : '-';
            ?>
            <tr class="<?= $id === $savedId ? 'row-highlight' : '' ?>">
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td>
                <div class="vendor-name"><?= esc($row['vendor_name'] ?? '-') ?></div>
                <?php if (!empty($row['source_account_name'])): ?>
                  <div class="vendor-src">via <?= esc($row['source_account_name']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge-type badge-<?= $type ?>"><?= ucfirst($type) ?></span></td>
              <td>
                <span class="badge-method method-<?= $method ?>">
                  <i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i>
                  <?= ucfirst(str_replace('_', ' ', $method)) ?>
                </span>
              </td>
              <td class="text-end">
                <span class="currency">PKR</span>
                <span class="amt"><?= number_format((float)($row['amount'] ?? 0), 2) ?></span>
              </td>
              <td style="font-size:.75rem;color:var(--gray-500,#64748b)"><?= esc($row['source_account_name'] ?? '-') ?></td>
              <td class="text-center">
                <?php $ac = (int)($row['attachment_count'] ?? 0); ?>
                <?php if ($ac > 0): ?>
                  <span class="badge bg-secondary" style="font-size:.6rem"><?= $ac ?></span>
                <?php else: ?>
                  <span style="color:var(--gray-300,#cbd5e1)">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php $alc = (int)($row['allocation_count'] ?? 0); ?>
                <?php if ($alc > 0): ?>
                  <span class="badge bg-secondary" style="font-size:.6rem"><?= $alc ?></span>
                <?php else: ?>
                  <span style="color:var(--gray-300,#cbd5e1)">—</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="d-flex gap-1">
                  <a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/vendor-payments/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a>
                  <button type="button" class="btn btn-success action-btn js-confirm-payment" data-payment-id="<?= $id ?>"><i class="bi bi-check-lg me-1"></i>Post</button>
                  <a href="<?= base_url('accounting/vendor-payments/pay?payment_id=' . $id) ?>" class="btn btn-sm btn-outline-primary action-btn">Edit</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Posted Panel -->
    <div class="vpl-tab-panel" id="panel-posted">
      <?php if (empty($posted)): ?>
        <div class="empty-msg"><i class="bi bi-inbox me-1"></i>No posted payments yet</div>
      <?php else: ?>
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th style="width:45px">#</th>
              <th>Date</th>
              <th>Vendor</th>
              <th>Type</th>
              <th>Method</th>
              <th class="text-end">Amount</th>
              <th>Source Account</th>
              <th style="width:50px" class="text-center">Files</th>
              <th style="width:50px" class="text-center">Bills</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($posted as $row):
              $id = (int)($row['id'] ?? 0);
              $type = strtolower($row['payment_type'] ?? 'settlement');
              $method = strtolower($row['payment_method'] ?? 'cash');
              $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
              $rawDate = $row['payment_date'] ?? '';
              $fmtDate = $rawDate ? date('d:m:Y', strtotime($rawDate)) : '-';
            ?>
            <tr>
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td>
                <div class="vendor-name"><?= esc($row['vendor_name'] ?? '-') ?></div>
                <?php if (!empty($row['source_account_name'])): ?>
                  <div class="vendor-src">via <?= esc($row['source_account_name']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge-type badge-<?= $type ?>"><?= ucfirst($type) ?></span></td>
              <td>
                <span class="badge-method method-<?= $method ?>">
                  <i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i>
                  <?= ucfirst(str_replace('_', ' ', $method)) ?>
                </span>
              </td>
              <td class="text-end">
                <span class="currency">PKR</span>
                <span class="amt"><?= number_format((float)($row['amount'] ?? 0), 2) ?></span>
              </td>
              <td style="font-size:.75rem;color:var(--gray-500,#64748b)"><?= esc($row['source_account_name'] ?? '-') ?></td>
              <td class="text-center">
                <?php $ac = (int)($row['attachment_count'] ?? 0); ?>
                <?php if ($ac > 0): ?>
                  <span class="badge bg-secondary" style="font-size:.6rem"><?= $ac ?></span>
                <?php else: ?>
                  <span style="color:var(--gray-300,#cbd5e1)">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php $alc = (int)($row['allocation_count'] ?? 0); ?>
                <?php if ($alc > 0): ?>
                  <span class="badge bg-secondary" style="font-size:.6rem"><?= $alc ?></span>
                <?php else: ?>
                  <span style="color:var(--gray-300,#cbd5e1)">—</span>
                <?php endif; ?>
              </td>
              <td>
                <a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/vendor-payments/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <!-- Voided Panel -->
    <?php if ($hasVoided): ?>
    <div class="vpl-tab-panel" id="panel-voided">
      <div class="table-responsive">
        <table>
          <thead>
            <tr>
              <th style="width:45px">#</th>
              <th>Date</th>
              <th>Vendor</th>
              <th>Type</th>
              <th>Method</th>
              <th class="text-end">Amount</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($voided as $row):
              $id = (int)($row['id'] ?? 0);
              $type = strtolower($row['payment_type'] ?? 'settlement');
              $method = strtolower($row['payment_method'] ?? 'cash');
              $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
              $rawDate = $row['payment_date'] ?? '';
              $fmtDate = $rawDate ? date('d:m:Y', strtotime($rawDate)) : '-';
            ?>
            <tr style="opacity:.6">
              <td class="fw-semibold">#<?= $id ?></td>
              <td><?= esc($fmtDate) ?></td>
              <td class="vendor-name"><?= esc($row['vendor_name'] ?? '-') ?></td>
              <td><span class="badge-type badge-<?= $type ?>"><?= ucfirst($type) ?></span></td>
              <td>
                <span class="badge-method method-<?= $method ?>">
                  <i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?>"></i>
                  <?= ucfirst(str_replace('_', ' ', $method)) ?>
                </span>
              </td>
              <td class="text-end">
                <span class="currency">PKR</span>
                <span class="amt" style="text-decoration:line-through"><?= number_format((float)($row['amount'] ?? 0), 2) ?></span>
              </td>
              <td>
                <a class="btn btn-outline-secondary action-btn" href="<?= base_url('accounting/vendor-payments/' . $id) ?>"><i class="bi bi-eye me-1"></i>View</a>
              </td>
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
  /* ─── Tab Switching ─── */
  document.querySelectorAll('.vpl-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.vpl-tab').forEach(t => t.classList.remove('active'));
      document.querySelectorAll('.vpl-tab-panel').forEach(p => p.classList.remove('active'));
      tab.classList.add('active');
      const panel = document.getElementById('panel-' + tab.dataset.tab);
      if (panel) panel.classList.add('active');
    });
  });

  /* ─── Confirm & Post ─── */
  const confirmButtons = document.querySelectorAll('.js-confirm-payment');
  if (!confirmButtons.length) return;

  const confirmEndpoint = '<?= base_url('accounting/vendor-payments/confirm') ?>';
  const csrfName = '<?= csrf_token() ?>';
  const csrfValue = '<?= csrf_hash() ?>';

  const submitConfirm = async (btn) => {
    const paymentId = btn.getAttribute('data-payment-id');
    if (!paymentId) return;

    if (!window.confirm('Post this vendor payment? This will create a journal entry and cannot be undone easily.')) {
      return;
    }

    const formData = new FormData();
    formData.append(csrfName, csrfValue);
    formData.append('payment_id', paymentId);

    btn.disabled = true;
    const original = btn.innerHTML;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Posting...';

    try {
      const resp = await fetch(confirmEndpoint, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData,
      });
      const data = await resp.json();
      if (data && data.success) {
        btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Posted!';
        btn.classList.replace('btn-success', 'btn-outline-success');
        setTimeout(() => {
          window.location.href = '<?= base_url('accounting/vendor-payments') ?>';
        }, 500);
      } else {
        alert((data && data.message) ? data.message : 'Failed to confirm payment.');
        btn.disabled = false;
        btn.innerHTML = original;
      }
    } catch (e) {
      alert('Network error while confirming payment.');
      console.error(e);
      btn.disabled = false;
      btn.innerHTML = original;
    }
  };

  confirmButtons.forEach((btn) => {
    btn.addEventListener('click', () => submitConfirm(btn));
  });

  /* Auto-remove toast */
  const toast = document.getElementById('saved_toast');
  if (toast) setTimeout(() => toast.remove(), 4000);
})();
</script>
<?= $this->endSection() ?>
