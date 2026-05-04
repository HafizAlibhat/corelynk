<?php /** @var array $customers */ /** @var array $sourceAccounts */ /** @var array $methods */ ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
/* Payment form tweaks */
.form-label { font-weight:600; }
.form-text { font-size:0.85rem; color:var(--gray-600); }
/* Ensure select dropdowns are visible and aligned */
.form-select { z-index: 1200; }
@media (max-width: 767px) { .col-md-2 { flex: 0 0 50%; max-width:50%; } }
</style>

<div class="container py-3">
  <h5>Receive Payment</h5>
  <form id="paymentForm">
    <div class="row g-2">
      <div class="col-md-4">
        <label class="form-label">Customer</label>
        <select id="customer_id" name="customer_id" class="form-select">
          <option value="">-- Select Customer --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (isset($prefill['customer_id']) && $prefill['customer_id'] == $c['id']) ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Date</label>
        <input type="date" id="payment_date" name="payment_date" class="form-control" value="<?= esc($prefill['payment_date'] ?? date('Y-m-d')) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Method</label>
        <select id="payment_method" name="payment_method" class="form-select">
          <?php foreach ($methods as $val => $lbl): ?>
            <option value="<?= esc($val) ?>" <?= (isset($prefill['payment_method']) && strtolower((string)$prefill['payment_method']) == strtolower((string)$val)) ? 'selected' : '' ?>><?= esc($lbl) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Destination Account</label>
        <select id="source_account_id" name="source_account_id" class="form-select">
          <option value="">-- Select Account --</option>
          <?php foreach ($sourceAccounts as $a): ?>
            <option value="<?= (int)$a['id'] ?>" <?= (isset($prefill['source_account_id']) && (int)$prefill['source_account_id'] === (int)$a['id']) ? 'selected' : '' ?>><?= esc($a['name']) ?><?= !empty($a['account_number']) ? ' ••••'.esc(substr($a['account_number'],-4)) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Amount</label>
        <input type="number" step="0.01" id="amount" name="amount" class="form-control" value="<?= esc($prefill['amount'] ?? '') ?>">
      </div>
    </div>

    <div class="row mt-3">
      <div class="col-md-8">
        <label class="form-label">Notes</label>
         <input type="text" id="notes" name="notes" class="form-control" value="<?= esc($prefill['notes'] ?? ($prefill['memo'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Attachments</label>
        <input type="file" id="attachments" name="attachments[]" class="form-control" multiple>
        <?php if (!empty($prefill['existing_attachments']) && is_array($prefill['existing_attachments'])): ?>
          <div class="mt-2 small">
            <div class="text-muted mb-1">Existing attachments:</div>
            <?php foreach ($prefill['existing_attachments'] as $att): ?>
              <div>
                <a href="<?= base_url(ltrim((string)($att['file_path'] ?? ''), '/')) ?>" target="_blank"><?= esc($att['original_name'] ?? ('Attachment #' . (int)($att['id'] ?? 0))) ?></a>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mt-3">
      <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <strong>Allocate to Invoices</strong>
          <span class="text-muted ms-2" style="font-size:.82rem">Select one or more invoices below and enter the amount received for each</span>
        </div>
        <div class="d-flex align-items-center gap-2">
          <button type="button" id="btn_auto_distribute" class="btn btn-sm btn-outline-primary" title="Distribute payment amount evenly across selected invoices">Auto Distribute</button>
          <button type="button" id="btn_refresh_invoices" class="btn btn-sm btn-outline-secondary">Refresh</button>
        </div>
      </div>

      <!-- Live allocation summary -->
      <div id="alloc_summary_bar" style="display:none;background:var(--gray-50,#f8fafc);border-bottom:1px solid var(--gray-200,#e2e8f0);padding:.5rem 1rem;font-size:.82rem;">
        <div class="d-flex flex-wrap gap-3 align-items-center">
          <span>Selected invoices: <strong id="lbl_inv_count">0</strong></span>
          <span>Total to allocate: <strong id="lbl_alloc_sum">0.00</strong></span>
          <span id="lbl_payment_vs_alloc"></span>
          <span id="lbl_alloc_status" style="font-weight:700"></span>
        </div>
      </div>

      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0" id="invoiceAllocTable">
            <thead>
              <tr>
                <th style="width:44px;"></th>
                <th>Invoice #</th>
                <th>Status</th>
                <th>Date</th>
                <th class="text-end">Invoice Total</th>
                <th class="text-end">Already Paid</th>
                <th class="text-end" style="color:#ef4444">Balance Due</th>
                <th class="text-end" style="min-width:140px;">Paying Now</th>
              </tr>
            </thead>
            <tbody id="invoiceAllocBody">
              <tr><td colspan="8" class="text-center text-muted py-3">Select customer to load unpaid invoices.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card mt-3" id="customerCreditsCard" style="display:none;">
      <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Existing Customer Payments (Unallocated Balance)</strong>
        <span class="small text-muted" id="creditTotalsLine"></span>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-sm mb-0">
            <thead>
              <tr>
                <th>Payment #</th>
                <th>Date</th>
                <th>Status</th>
                <th class="text-end">Total</th>
                <th class="text-end">Allocated</th>
                <th class="text-end">Unallocated</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody id="customerCreditBody">
              <tr><td colspan="7" class="text-center text-muted py-2">No pending customer balances.</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <?php if (!empty($prefill) && !empty($prefill['invoice_number'])): ?>
      <input type="hidden" id="prefill_invoice_id" value="<?= (int)$prefill['id'] ?>">
      <input type="hidden" id="prefill_invoice_number" value="<?= esc($prefill['invoice_number'] ?? '') ?>">
      <input type="hidden" id="prefill_issue_date" value="<?= esc($prefill['issue_date'] ?? '') ?>">
      <input type="hidden" id="prefill_due_date" value="<?= esc($prefill['due_date'] ?? '') ?>">
      <input type="hidden" id="prefill_invoice_currency" value="<?= esc($prefill['currency_code'] ?? '') ?>">
    <?php endif; ?>
    <?php if (!empty($prefill) && !empty($prefill['allocations'])): ?>
      <input type="hidden" id="existing_allocations" value='<?= json_encode($prefill['allocations'], JSON_HEX_APOS|JSON_HEX_QUOT) ?>'>
    <?php endif; ?>
      <?php if (!empty($prefill) && !empty($prefill['id']) && !empty($prefill['amount'])): ?>
        <input type="hidden" id="prefill_amount" value="<?= esc($prefill['amount']) ?>">
      <?php endif; ?>

      <?php if (!empty($prefill) && !empty($prefill['is_payment_edit'])): ?>
        <input type="hidden" id="editing_payment_id" value="<?= (int)($prefill['id'] ?? 0) ?>">
      <?php endif; ?>

    <div class="mt-3">
      <button type="button" id="btn_save" class="btn btn-primary">Save Draft</button>
      <button type="button" id="btn_confirm" class="btn btn-success">Confirm &amp; Post</button>
      <a href="<?= base_url('accounting/customer-payments') ?>" class="btn btn-link">Back to payments</a>
    </div>
  </form>
</div>

<script>
(function() {
  const urls = {
    draft: '<?= base_url('accounting/customer-payments/draft') ?>',
    confirm: '<?= base_url('accounting/customer-payments/confirm') ?>',
    view: '<?= base_url('accounting/customer-payments/view/') ?>',
    openInvoices: '<?= base_url('accounting/customer-payments/open-invoices/') ?>'
  };

  const el = {
    customer: document.getElementById('customer_id'),
    amount: document.getElementById('amount'),
    paymentDate: document.getElementById('payment_date'),
    paymentMethod: document.getElementById('payment_method'),
    sourceAccount: document.getElementById('source_account_id'),
    notes: document.getElementById('notes'),
    attachments: document.getElementById('attachments'),
    saveBtn: document.getElementById('btn_save'),
    confirmBtn: document.getElementById('btn_confirm'),
    refreshBtn: document.getElementById('btn_refresh_invoices'),
    tableBody: document.getElementById('invoiceAllocBody'),
    existingAlloc: document.getElementById('existing_allocations'),
    editPaymentId: document.getElementById('editing_payment_id'),
    prefillInvoiceId: document.getElementById('prefill_invoice_id')
  };

  const creditEl = {
    card: document.getElementById('customerCreditsCard'),
    body: document.getElementById('customerCreditBody'),
    totals: document.getElementById('creditTotalsLine')
  };

  const num = (v) => {
    const n = parseFloat(String(v ?? '').replace(/,/g, '').trim());
    return Number.isFinite(n) ? n : 0;
  };
  const fmt = (v) => num(v).toFixed(2);
  const esc = (s) => String(s ?? '').replace(/[&<>\"']/g, (c) => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
  const globalDateFormat = '<?= esc($global_date_format ?? 'Y-m-d') ?>';

  const formatDate = (dateStr) => {
    const raw = String(dateStr || '').trim();
    if (!raw) return '-';
    const m = raw.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (!m) return raw;
    const y = m[1], mo = m[2], d = m[3];
    switch (globalDateFormat) {
      case 'd-m-Y': return `${d}-${mo}-${y}`;
      case 'd/m/Y': return `${d}/${mo}/${y}`;
      case 'm/d/Y': return `${mo}/${d}/${y}`;
      default: return `${y}-${mo}-${d}`;
    }
  };

  let openInvoices = [];
  let customerCredits = [];
  let postedCreditTotal = 0;
  let draftPendingTotal = 0;
  const prefillAmount = num(el.amount.value);
  const prefillInvoiceNumberEl = document.getElementById('prefill_invoice_number');
  const prefillIssueDateEl = document.getElementById('prefill_issue_date');
  const prefillDueDateEl = document.getElementById('prefill_due_date');
  let existingAllocRows = [];
  let existingAllocMap = {};
  if (el.existingAlloc && el.existingAlloc.value) {
    try {
      const arr = JSON.parse(el.existingAlloc.value);
      if (Array.isArray(arr)) {
        existingAllocRows = arr;
        arr.forEach((row) => {
          const id = parseInt(row.invoice_id, 10);
          if (id > 0) existingAllocMap[id] = num(row.amount);
        });
      }
    } catch (e) {
      existingAllocRows = [];
      existingAllocMap = {};
    }
  }

  const gatherAllocations = () => {
    const out = [];
    let remainingCredit = postedCreditTotal;
    Array.from(el.tableBody.querySelectorAll('tr[data-id]')).forEach((tr) => {
      const cb = tr.querySelector('.inv-cb');
      const inp = tr.querySelector('.alloc-amt');
      if (!cb || !inp || !cb.checked) return;
      const amount = num(inp.value);
      if (amount <= 0) return;
      const advanceAmount = Math.min(amount, Math.max(0, remainingCredit));
      const cashAmount = Math.max(0, amount - advanceAmount);
      remainingCredit = Math.max(0, remainingCredit - advanceAmount);
      out.push({
        invoice_id: parseInt(tr.getAttribute('data-id'), 10),
        amount: amount,
        cash_amount: cashAmount,
        advance_amount: advanceAmount
      });
    });
    return out;
  };

  const syncAmountFromAllocations = () => {
    const allocations = gatherAllocations();
    if (!allocations.length) {
      if (!(el.editPaymentId && prefillAmount > 0)) {
        el.amount.value = fmt(0);
      }
      return;
    }
    const cashTotal = allocations.reduce((s, a) => s + num(a.cash_amount), 0);
    el.amount.value = fmt(cashTotal);
  };

  const bindTableEvents = () => {
    Array.from(el.tableBody.querySelectorAll('.alloc-amt')).forEach((inp) => {
      inp.addEventListener('input', () => {
        const tr = inp.closest('tr[data-id]');
        const cb = tr ? tr.querySelector('.inv-cb') : null;
        const max = num(inp.getAttribute('data-max'));
        let v = num(inp.value);
        if (v > max + 0.005) {
          v = max;
          inp.value = fmt(v);
        }
        if (cb) cb.checked = v > 0;
        syncAmountFromAllocations();
        updateAllocSummary();
      });
      inp.addEventListener('blur', () => {
        const v = num(inp.value);
        inp.value = v > 0 ? fmt(v) : '';
        syncAmountFromAllocations();
        updateAllocSummary();
      });
    });

    Array.from(el.tableBody.querySelectorAll('.inv-cb')).forEach((cb) => {
      cb.addEventListener('change', () => {
        const tr = cb.closest('tr[data-id]');
        const inp = tr ? tr.querySelector('.alloc-amt') : null;
        if (!inp) return;
        if (cb.checked && num(inp.value) <= 0) {
          inp.value = fmt(num(inp.getAttribute('data-max')));
        }
        if (!cb.checked) {
          inp.value = '';
        }
        syncAmountFromAllocations();
        updateAllocSummary();
      });
    });
  };

  const updateAllocSummary = () => {
    const summaryBar = document.getElementById('alloc_summary_bar');
    const lblCount = document.getElementById('lbl_inv_count');
    const lblSum = document.getElementById('lbl_alloc_sum');
    const lblVsAlloc = document.getElementById('lbl_payment_vs_alloc');
    const lblStatus = document.getElementById('lbl_alloc_status');
    if (!summaryBar) return;
    const rows = Array.from(el.tableBody.querySelectorAll('tr[data-id]'));
    let count = 0;
    let total = 0;
    rows.forEach((tr) => {
      const cb = tr.querySelector('.inv-cb');
      const inp = tr.querySelector('.alloc-amt');
      if (cb && cb.checked && inp) {
        const v = num(inp.value);
        if (v > 0) { count++; total += v; }
      }
    });
    total = Math.round(total * 100) / 100;
    summaryBar.style.display = count > 0 ? '' : 'none';
    if (lblCount) lblCount.textContent = count;
    if (lblSum) lblSum.textContent = fmt(total);
    const payAmt = num(el.amount.value);
    const diff = Math.round((total - payAmt) * 100) / 100;
    if (lblVsAlloc && payAmt > 0) {
      if (Math.abs(diff) < 0.005) {
        lblVsAlloc.innerHTML = '<span style="color:#16a34a">✓ Allocation matches payment amount</span>';
      } else if (diff > 0) {
        lblVsAlloc.innerHTML = '<span style="color:#ef4444">⚠ Allocation exceeds payment by ' + fmt(diff) + ' — adjust amounts</span>';
      } else {
        lblVsAlloc.innerHTML = '<span style="color:#d97706">⚠ Allocation is ' + fmt(-diff) + ' less than payment amount</span>';
      }
    } else if (lblVsAlloc) { lblVsAlloc.innerHTML = ''; }
    if (lblStatus) lblStatus.textContent = '';
  };

  const renderInvoices = () => {
    if (!openInvoices.length && !existingAllocRows.length) {
      const prefillInvoiceId = parseInt((el.prefillInvoiceId && el.prefillInvoiceId.value) ? el.prefillInvoiceId.value : '0', 10);
      const prefillInvoiceNumber = prefillInvoiceNumberEl ? String(prefillInvoiceNumberEl.value || '').trim() : '';
      const prefillIssueDate = prefillIssueDateEl ? String(prefillIssueDateEl.value || '').trim() : '';
      const prefillDueDate = prefillDueDateEl ? String(prefillDueDateEl.value || '').trim() : '';
      const fallbackOutstanding = num(document.getElementById('prefill_amount') ? document.getElementById('prefill_amount').value : prefillAmount);
      if (prefillInvoiceId > 0 && fallbackOutstanding > 0) {
        let html = buildInvRow({
          id: prefillInvoiceId,
          invoice_number: prefillInvoiceNumber || ('INV-' + prefillInvoiceId),
          issue_date: prefillIssueDate,
          outstanding: fallbackOutstanding,
          total_amount: fallbackOutstanding,
          paid_amount: 0,
          status: '',
        }, true, Math.min(fallbackOutstanding, prefillAmount || fallbackOutstanding), true);
        el.tableBody.innerHTML = html;
        bindTableEvents();
        syncAmountFromAllocations();
        updateAllocSummary();
        return;
      }
      el.tableBody.innerHTML = '<tr><td colspan="8" class="text-center text-muted py-3">No unpaid invoices for this customer.</td></tr>';
      syncAmountFromAllocations();
      updateAllocSummary();
      return;
    }

    let html = '';
    const renderedIds = {};
    openInvoices.forEach((inv) => {
      const id = parseInt(inv.id, 10);
      renderedIds[id] = true;
      const outstanding = num(inv.outstanding);
      const totalAmt = num(inv.total_amount);
      const paidAmt = num(inv.paid_amount);
      const prefillAmt = existingAllocMap[id] > 0 ? Math.min(outstanding, num(existingAllocMap[id])) : 0;
      const isPrefillInv = el.prefillInvoiceId && parseInt(el.prefillInvoiceId.value || '0', 10) === id;
      const prefillAmtForThis = isPrefillInv ? num(document.getElementById('prefill_amount') ? document.getElementById('prefill_amount').value : prefillAmount) : 0;
      const checked = prefillAmt > 0 || isPrefillInv;
      const autoAmt = prefillAmt > 0 ? prefillAmt
        : (isPrefillInv && prefillAmtForThis > 0 ? Math.min(outstanding, prefillAmtForThis)
        : (checked ? (prefillAmount > 0 ? Math.min(outstanding, prefillAmount) : outstanding) : 0));
      html += buildInvRow(inv, checked, autoAmt, false);
    });

    // Keep previously saved allocations visible in edit mode.
    existingAllocRows.forEach((row) => {
      const id = parseInt(row.invoice_id, 10);
      if (!id || renderedIds[id]) return;
      const saved = num(row.amount);
      html += buildInvRow({
        id: id,
        invoice_number: row.invoice_number || ('INV-' + id),
        issue_date: row.issue_date || '',
        outstanding: saved,
        total_amount: saved,
        paid_amount: 0,
        status: 'saved',
      }, true, saved, false, true);
    });

    el.tableBody.innerHTML = html;
    bindTableEvents();
    syncAmountFromAllocations();
    updateAllocSummary();
  };

  const buildInvRow = (inv, checked, autoAmt, isPrefill, isSaved) => {
    const id = parseInt(inv.id, 10);
    const outstanding = num(inv.outstanding);
    const totalAmt = num(inv.total_amount);
    // Prefer explicit paid_amount field from server; fall back to total-outstanding
    const serverPaid = num(inv.paid_amount);
    const paidAmt = serverPaid > 0 ? serverPaid : Math.max(0, Math.round((totalAmt - outstanding) * 100) / 100);
    const invStatus = String(inv.status || '').toLowerCase();
    const isPartial = paidAmt > 0.005 && outstanding > 0.005;

    // Status badge HTML
    let statusBadge = '';
    if (isSaved) {
      statusBadge = '<span style="font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:3px;background:rgba(100,116,139,.1);color:#64748b;border:1px solid rgba(100,116,139,.2)">SAVED</span>';
    } else if (isPrefill) {
      statusBadge = '<span style="font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:3px;background:rgba(37,99,235,.1);color:#2563eb;border:1px solid rgba(37,99,235,.2)">PRE-SELECTED</span>';
    } else if (isPartial) {
      statusBadge = '<span style="font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:3px;background:rgba(245,158,11,.12);color:#92400e;border:1px solid rgba(245,158,11,.3)">PARTIAL</span>';
    } else {
      statusBadge = '<span style="font-size:.68rem;font-weight:700;padding:1px 6px;border-radius:3px;background:rgba(239,68,68,.08);color:#b91c1c;border:1px solid rgba(239,68,68,.2)">UNPAID</span>';
    }

    // Paid progress bar (only show for partial invoices)
    let progressHtml = '';
    if (isPartial && totalAmt > 0) {
      const pct = Math.min(100, Math.round(paidAmt / totalAmt * 100));
      progressHtml = '<div style="height:4px;background:#e2e8f0;border-radius:4px;width:100%;margin-top:2px;"><div style="height:100%;width:' + pct + '%;background:#f59e0b;border-radius:4px;"></div></div>';
    }

    let html = '<tr data-id="' + id + '" style="' + (isPartial ? 'background:rgba(245,158,11,.03)' : '') + '">';
    html += '<td style="vertical-align:middle"><input type="checkbox" class="form-check-input inv-cb" ' + (checked ? 'checked' : '') + ' title="Include in this payment"></td>';
    html += '<td style="vertical-align:middle"><a href="<?= site_url('customer-invoices/view/') ?>' + id + '" target="_blank" style="font-weight:700;color:#2563eb;">' + esc(inv.invoice_number || ('INV-' + id)) + '</a>' + (progressHtml ? '<br>' + progressHtml : '') + '</td>';
    html += '<td style="vertical-align:middle">' + statusBadge + '</td>';
    html += '<td style="vertical-align:middle;font-size:.8rem;color:#64748b">' + esc(formatDate(inv.issue_date || '')) + '</td>';
    html += '<td class="text-end" style="vertical-align:middle;font-weight:600">' + fmt(totalAmt) + '</td>';
    html += '<td class="text-end" style="vertical-align:middle;color:' + (paidAmt > 0.005 ? '#16a34a' : '#94a3b8') + ';font-weight:' + (paidAmt > 0.005 ? '700' : '400') + '">' + (paidAmt > 0.005 ? fmt(paidAmt) : '—') + '</td>';
    html += '<td class="text-end" style="vertical-align:middle;color:#ef4444;font-weight:700">' + fmt(outstanding) + '</td>';
    html += '<td class="text-end" style="vertical-align:middle"><input type="number" step="0.01" min="0" data-max="' + outstanding + '" value="' + (autoAmt > 0 ? fmt(autoAmt) : '') + '" class="form-control form-control-sm alloc-amt text-end" placeholder="0.00"></td>';
    html += '</tr>';
    return html;
  };

  const renderCredits = () => {
    if (!creditEl.card || !creditEl.body || !creditEl.totals) return;

    if (!customerCredits.length) {
      creditEl.card.style.display = 'none';
      creditEl.body.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-2">No pending customer balances.</td></tr>';
      creditEl.totals.textContent = '';
      return;
    }

    creditEl.card.style.display = '';
    creditEl.totals.textContent = 'Posted credit: ' + fmt(postedCreditTotal) + ' | Draft pending: ' + fmt(draftPendingTotal);

    let html = '';
    customerCredits.forEach((p) => {
      const pid = parseInt(p.id, 10) || 0;
      const isPosted = parseInt(p.is_posted, 10) === 1;
      const badge = isPosted
        ? '<span class="badge bg-success-subtle text-success border">Posted</span>'
        : '<span class="badge bg-warning-subtle text-warning border">Draft</span>';
      html += '<tr>';
      html += '<td>#' + pid + '</td>';
      html += '<td>' + esc(formatDate(p.payment_date || '')) + '</td>';
      html += '<td>' + badge + '</td>';
      html += '<td class="text-end">' + fmt(p.amount || 0) + '</td>';
      html += '<td class="text-end">' + fmt(p.allocated_total || 0) + '</td>';
      html += '<td class="text-end fw-semibold ' + (isPosted ? 'text-success' : 'text-warning') + '">' + fmt(p.unallocated_amount || 0) + '</td>';
      html += '<td><a class="btn btn-sm btn-outline-secondary" href="' + urls.view + pid + '" target="_blank">View</a></td>';
      html += '</tr>';
    });
    creditEl.body.innerHTML = html;
  };

  const loadInvoices = async () => {
    const customerId = parseInt(el.customer.value || '0', 10);
    if (customerId <= 0) {
      openInvoices = [];
      customerCredits = [];
      postedCreditTotal = 0;
      draftPendingTotal = 0;
      renderInvoices();
      renderCredits();
      return;
    }

    el.tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-3">Loading unpaid invoices...</td></tr>';
    try {
      const excludePaymentId = parseInt((el.editPaymentId && el.editPaymentId.value) ? el.editPaymentId.value : '0', 10);
      const openUrl = urls.openInvoices + customerId + (excludePaymentId > 0 ? ('?exclude_payment_id=' + excludePaymentId) : '');
      const res = await fetch(openUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      const json = await res.json();
      openInvoices = json && json.success && Array.isArray(json.data) ? json.data : [];
      customerCredits = json && json.success && Array.isArray(json.credits) ? json.credits : [];
      postedCreditTotal = json && json.success ? num(json.posted_credit_total) : 0;
      draftPendingTotal = json && json.success ? num(json.draft_pending_total) : 0;
      renderInvoices();
      renderCredits();
    } catch (e) {
      openInvoices = [];
      customerCredits = [];
      postedCreditTotal = 0;
      draftPendingTotal = 0;
      el.tableBody.innerHTML = '<tr><td colspan="7" class="text-center text-danger py-3">Failed to load unpaid invoices.</td></tr>';
      renderCredits();
    }
  };

  const buildDraftPayload = () => {
    const fd = new FormData();
    fd.append('customer_id', el.customer.value || '');
    fd.append('payment_date', el.paymentDate.value || '');
    fd.append('payment_method', el.paymentMethod.value || '');
    fd.append('source_account_id', el.sourceAccount.value || '');
    fd.append('notes', el.notes.value || '');
    fd.append('memo', el.notes.value || '');

    const allocations = gatherAllocations();
    // Fallback for explicit invoice prefill if table has not loaded yet.
    if (!allocations.length && el.prefillInvoiceId && parseInt(el.prefillInvoiceId.value || '0', 10) > 0 && num(el.amount.value) > 0) {
      const amount = num(el.amount.value);
      const advanceAmount = Math.min(amount, postedCreditTotal);
      allocations.push({
        invoice_id: parseInt(el.prefillInvoiceId.value, 10),
        amount: amount,
        cash_amount: Math.max(0, amount - advanceAmount),
        advance_amount: advanceAmount
      });
    }

    if (el.editPaymentId && el.editPaymentId.value) {
      fd.append('payment_id', el.editPaymentId.value);
    }

    fd.append('allocations', JSON.stringify(allocations));
    fd.append('advance_amount', '0');

    const files = el.attachments.files || [];
    for (let i = 0; i < files.length; i++) {
      fd.append('attachments[]', files[i]);
    }
    return fd;
  };

  el.saveBtn.addEventListener('click', async () => {
    const fd = buildDraftPayload();
    const res = await fetch(urls.draft, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const json = await res.json();
    if (!json.success) {
      alert('Error: ' + (json.message || 'Unknown'));
      return;
    }
    alert('Draft saved - ID: ' + json.payment_id);
    window.location = urls.view + json.payment_id;
  });

  el.confirmBtn.addEventListener('click', async () => {
    const fd = buildDraftPayload();
    const saveRes = await fetch(urls.draft, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const saveJson = await saveRes.json();
    if (!saveJson.success) {
      alert('Save failed: ' + (saveJson.message || 'Unknown'));
      return;
    }

    const cf = new FormData();
    cf.append('payment_id', saveJson.payment_id);
    const postRes = await fetch(urls.confirm, { method: 'POST', body: cf, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    const postJson = await postRes.json();
    if (postJson.success) {
      alert('Payment posted successfully.');
    } else {
      alert('Post failed: ' + (postJson.message || 'Unknown'));
    }
    window.location = urls.view + saveJson.payment_id;
  });

  el.customer.addEventListener('change', loadInvoices);
  el.refreshBtn.addEventListener('click', loadInvoices);

  // Auto-distribute: spread payment amount across checked invoices in proportion to their balance
  const autoDistributeBtn = document.getElementById('btn_auto_distribute');
  if (autoDistributeBtn) {
    autoDistributeBtn.addEventListener('click', () => {
      const payAmt = num(el.amount.value);
      if (payAmt <= 0) { alert('Enter a payment amount first, then use Auto Distribute.'); return; }
      const rows = Array.from(el.tableBody.querySelectorAll('tr[data-id]'));
      // Collect checked rows with their max balance
      const selected = rows.map(tr => {
        const cb = tr.querySelector('.inv-cb');
        const inp = tr.querySelector('.alloc-amt');
        if (!cb || !inp || !cb.checked) return null;
        return { tr, inp, max: num(inp.getAttribute('data-max')) };
      }).filter(Boolean);
      if (!selected.length) { alert('Check at least one invoice row before distributing.'); return; }
      let remaining = payAmt;
      // Distribute: fill each invoice up to its max in order, leftover goes to next
      selected.forEach((row, i) => {
        const isLast = (i === selected.length - 1);
        const give = isLast ? Math.min(remaining, row.max) : Math.min(row.max, remaining);
        row.inp.value = give > 0 ? fmt(give) : '';
        remaining = Math.max(0, Math.round((remaining - give) * 100) / 100);
      });
      syncAmountFromAllocations();
      updateAllocSummary();
    });
  }

  // When payment amount is manually changed, warn if it differs from allocation total
  el.amount.addEventListener('input', updateAllocSummary);

  loadInvoices();
})();
</script>

<?= $this->endSection() ?>
