<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Adjust Advance Against Bills<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Adjust Vendor Advance Against Bills</h5>
    <div>
      <a href="<?= base_url('/accounting/cheques') ?>" class="btn btn-secondary btn-sm">Back to Cheques</a>
      <a href="<?= base_url('/accounting/cheques/create?mode=advance') ?>" class="btn btn-primary btn-sm">New Advance Cheque</a>
    </div>
  </div>

  <div id="ajax_alert"></div>

  <!-- Hidden CSRF token for AJAX -->
  <input type="hidden" id="csrf_token_name" value="<?= csrf_token() ?>">
  <input type="hidden" id="csrf_token_value" value="<?= csrf_hash() ?>">

  <div class="card mb-3">
    <div class="card-header py-2">Select Vendor</div>
    <div class="card-body p-3">
      <div class="row g-2">
        <div class="col-md-4">
          <label class="form-label form-label-sm">Vendor</label>
          <select id="vendor_id" class="form-select form-select-sm">
            <option value="">Select Vendor</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= (int)$v['id'] ?>"><?= esc($v['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label form-label-sm">Adjustment Date</label>
          <input type="date" id="adjustment_date" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">Available Advance Balance</label>
          <div class="form-control form-control-sm bg-light fw-bold text-success" id="advance_balance">PKR 0.00</div>
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">&nbsp;</label>
          <button type="button" id="load_data" class="btn btn-outline-primary btn-sm w-100">Load Data</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Advance Cheques -->
  <div class="card mb-3 d-none" id="advances_card">
    <div class="card-header py-2"><i class="bi bi-cash-stack"></i> Advance Cheques</div>
    <div class="card-body p-2">
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Cheque #</th>
              <th>Date</th>
              <th class="text-end">Amount</th>
              <th class="text-end">Adjusted</th>
              <th class="text-end">Remaining</th>
            </tr>
          </thead>
          <tbody id="advances_tbody"></tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Pending Bills -->
  <div class="card mb-3 d-none" id="bills_card">
    <div class="card-header py-2"><i class="bi bi-receipt"></i> Pending Bills - Allocate Advance</div>
    <div class="card-body p-2">
      <div class="table-responsive">
        <table class="table table-sm table-striped align-middle">
          <thead class="table-light">
            <tr>
              <th>Bill #</th>
              <th>Date</th>
              <th class="text-end">Total</th>
              <th class="text-end">Balance</th>
              <th class="text-end" style="width:150px;">Apply Amount</th>
            </tr>
          </thead>
          <tbody id="bills_tbody"></tbody>
        </table>
      </div>
      <div class="row g-2 mt-2">
        <div class="col-md-3">
          <button type="button" id="auto_apply" class="btn btn-outline-primary btn-sm">Auto-Apply All</button>
        </div>
        <div class="col-md-3">
          <label class="form-label form-label-sm">Notes (optional)</label>
          <input type="text" id="notes" class="form-control form-control-sm" placeholder="Adjustment notes">
        </div>
        <div class="col-md-3">
          <div class="small text-muted">Total Allocating</div>
          <div class="fw-bold fs-6" id="total_allocating">PKR 0.00</div>
        </div>
        <div class="col-md-3 text-end">
          <label class="form-label form-label-sm">&nbsp;</label>
          <button type="button" id="apply_advance" class="btn btn-success btn-sm w-100" disabled>
            <i class="bi bi-check-circle"></i> Apply Advance to Bills
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- No Data Message -->
  <div class="alert alert-info d-none" id="no_data_msg">
    <i class="bi bi-info-circle"></i> Select a vendor and click "Load Data" to see advance cheques and pending bills.
  </div>
</div>

<script>
(function() {
  const vendorSelect = document.getElementById('vendor_id');
  const loadBtn = document.getElementById('load_data');
  const advancesCard = document.getElementById('advances_card');
  const billsCard = document.getElementById('bills_card');
  const advancesTbody = document.getElementById('advances_tbody');
  const billsTbody = document.getElementById('bills_tbody');
  const balanceEl = document.getElementById('advance_balance');
  const totalAllocEl = document.getElementById('total_allocating');
  const applyBtn = document.getElementById('apply_advance');
  const noDataMsg = document.getElementById('no_data_msg');

  let currentBalance = 0;

  const fmtMoney = (v) => 'PKR ' + Number(v||0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2});
  const removeCommas = (v) => parseFloat(v.toString().replace(/,/g, '')) || 0;

  function recalcTotal() {
    let total = 0;
    billsTbody.querySelectorAll('.bill-amt').forEach(inp => {
      total += removeCommas(inp.value || '0');
    });
    totalAllocEl.textContent = fmtMoney(total);
    applyBtn.disabled = (total <= 0 || total > currentBalance + 0.01);
    if (total > currentBalance + 0.01) {
      totalAllocEl.classList.add('text-danger');
      totalAllocEl.classList.remove('text-success');
    } else {
      totalAllocEl.classList.remove('text-danger');
      totalAllocEl.classList.add('text-success');
    }
  }

  loadBtn.addEventListener('click', function() {
    const vid = vendorSelect.value;
    if (!vid) { return alert('Select a vendor first'); }
    loadBtn.disabled = true;
    loadBtn.textContent = 'Loading...';

    fetch(`<?= base_url('/accounting/cheques/vendorAdvanceData') ?>?vendor_id=${vid}`, {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(r => r.json())
    .then(j => {
      loadBtn.disabled = false;
      loadBtn.textContent = 'Load Data';
      if (!j || !j.success) {
        alert(j.message || 'Failed to load data');
        return;
      }

      currentBalance = j.advance_balance || 0;
      balanceEl.textContent = fmtMoney(currentBalance);

      // Render advances
      advancesTbody.innerHTML = '';
      if (j.advances && j.advances.length > 0) {
        j.advances.forEach(a => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${a.cheque_number || '#'+a.id}</td>
            <td>${a.cheque_date || '-'}</td>
            <td class="text-end">${fmtMoney(a.amount)}</td>
            <td class="text-end">${fmtMoney(a.adjusted_amount)}</td>
            <td class="text-end fw-bold ${Number(a.remaining) > 0 ? 'text-success' : 'text-muted'}">${fmtMoney(a.remaining)}</td>`;
          advancesTbody.appendChild(tr);
        });
        advancesCard.classList.remove('d-none');
      } else {
        advancesCard.classList.add('d-none');
      }

      // Render bills
      billsTbody.innerHTML = '';
      const bills = (j.bills || []).filter(b => Number(b.effective_balance) > 0);
      if (bills.length > 0) {
        bills.forEach(b => {
          const tr = document.createElement('tr');
          tr.innerHTML = `
            <td>${b.bill_number || '#'+b.id}</td>
            <td>${b.bill_date || '-'}</td>
            <td class="text-end">${fmtMoney(b.total)}</td>
            <td class="text-end">${fmtMoney(b.effective_balance)}</td>
            <td class="text-end">
              <input type="hidden" name="bill_id[]" value="${b.id}">
              <input type="number" step="0.01" min="0" max="${Number(b.effective_balance).toFixed(2)}" name="bill_amount[]" class="form-control form-control-sm text-end bill-amt" value="0">
            </td>`;
          billsTbody.appendChild(tr);
          tr.querySelector('.bill-amt').addEventListener('input', recalcTotal);
        });
        billsCard.classList.remove('d-none');
      } else {
        billsCard.classList.add('d-none');
      }

      noDataMsg.classList.toggle('d-none', currentBalance > 0 || bills.length > 0);
      if (currentBalance <= 0 && bills.length > 0) {
        showAlert('warning', 'No advance balance available for this vendor. Create an advance cheque first.', 5000);
      }
      recalcTotal();
    })
    .catch(err => {
      loadBtn.disabled = false;
      loadBtn.textContent = 'Load Data';
      console.error(err);
      alert('Error loading data');
    });
  });

  // Auto-apply: fill bills in order up to available balance
  document.getElementById('auto_apply').addEventListener('click', function() {
    let avail = currentBalance;
    billsTbody.querySelectorAll('.bill-amt').forEach(inp => {
      const max = parseFloat(inp.getAttribute('max')) || 0;
      const v = Math.min(avail, max);
      inp.value = v > 0 ? v.toFixed(2) : '0';
      avail -= v;
    });
    recalcTotal();
  });

  // Apply advance
  applyBtn.addEventListener('click', async function() {
    const vid = vendorSelect.value;
    if (!vid) return alert('Select a vendor');

    const billIds = [];
    const billAmts = [];
    billsTbody.querySelectorAll('tr').forEach(tr => {
      const idInput = tr.querySelector('input[name="bill_id[]"]');
      const amtInput = tr.querySelector('input[name="bill_amount[]"]');
      if (idInput && amtInput) {
        const amt = removeCommas(amtInput.value || '0');
        if (amt > 0) {
          billIds.push(idInput.value);
          billAmts.push(amt.toString());
        }
      }
    });

    if (billIds.length === 0) return alert('Allocate at least one bill amount');

    if (!confirm('Apply advance against ' + billIds.length + ' bill(s)? This action cannot be undone.')) return;

    applyBtn.disabled = true;
    applyBtn.textContent = 'Applying...';

    try {
      const fd = new FormData();
      fd.append('vendor_id', vid);
      fd.append('adjustment_date', document.getElementById('adjustment_date').value);
      fd.append('notes', document.getElementById('notes').value || '');
      billIds.forEach(id => fd.append('bill_id[]', id));
      billAmts.forEach(amt => fd.append('bill_amount[]', amt));

      // CSRF
      const csrfName = document.getElementById('csrf_token_name').value;
      const csrfValue = document.getElementById('csrf_token_value').value;
      if (csrfName && csrfValue) fd.append(csrfName, csrfValue);

      const resp = await fetch('<?= base_url('/accounting/cheques/applyAdvance') ?>', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });

      const j = await resp.json();
      if (j && j.success) {
        showAlert('success', j.message || 'Advance applied successfully!', 5000);
        // Reload data
        setTimeout(() => loadBtn.click(), 1000);
      } else {
        showAlert('danger', j.message || 'Failed to apply advance', 8000);
      }
    } catch (err) {
      console.error(err);
      showAlert('danger', 'Network error while applying advance', 8000);
    } finally {
      applyBtn.disabled = false;
      applyBtn.innerHTML = '<i class="bi bi-check-circle"></i> Apply Advance to Bills';
    }
  });

  function showAlert(type, message, timeout = 4000) {
    const container = document.getElementById('ajax_alert');
    if (!container) return;
    container.innerHTML = `<div class="alert alert-${type} py-2 px-3 small" role="alert">${message}</div>`;
    if (timeout > 0) {
      setTimeout(() => { try { container.innerHTML = ''; } catch(e) {} }, timeout);
    }
  }

  // Auto-load if vendor pre-selected (from URL param)
  const urlParams = new URLSearchParams(window.location.search);
  const preVendor = urlParams.get('vendor_id');
  if (preVendor) {
    vendorSelect.value = preVendor;
    setTimeout(() => loadBtn.click(), 200);
  }
})();
</script>
<?= $this->endSection() ?>
