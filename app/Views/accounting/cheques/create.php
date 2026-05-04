<?php /** @var array $banks */ /** @var array $vendors */ /** @var array $expenseAccounts */ /** @var array $advanceAccounts */ ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>
<?php
  $req = service('request');
  $prefVendorId = (int)($req->getGet('vendor_id') ?? 0);
  $prefAmount = (float)($req->getGet('amount') ?? 0);
  $prefDate = trim((string)($req->getGet('date') ?? ''));
  $prefMode = trim((string)($req->getGet('mode') ?? 'settlement'));
  $prefMemo = trim((string)($req->getGet('memo') ?? ''));
  $prefNotes = trim((string)($req->getGet('notes') ?? ''));
  if ($prefDate === '') { $prefDate = date('Y-m-d'); }
?>
<div class="container-fluid">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">New Cheque</h5>
    <a href="<?= base_url('/accounting/cheques') ?>" class="btn btn-secondary btn-sm">Back</a>
  </div>

  <?php if (session('error')): ?>
    <div class="alert alert-danger py-2 px-3 btn-xs"><?= esc(session('error')) ?></div>
  <?php endif; ?>
  
  <?php if (session('success')): ?>
    <div class="alert alert-success py-2 px-3 btn-xs"><?= esc(session('success')) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= base_url('/accounting/cheques/store') ?>" id="chequeForm">
    <?= csrf_field() ?>

    <!-- Live Cheque Template Preview -->
    <div class="card mb-3 border-primary">
      <div class="card-header py-2 bg-primary bg-opacity-10 d-flex justify-content-between align-items-center">
        <span><i class="bi bi-file-earmark-text"></i> Cheque Preview</span>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="togglePreview">
          <i class="bi bi-eye"></i> Toggle
        </button>
      </div>
      <div class="card-body p-3" id="chequePreviewBody">
        <div id="chequeTemplate" style="border:3px double #000; padding:20px; background:#fffef5; font-family: 'Courier New', monospace; max-width:700px; margin:0 auto; position:relative;">
          <!-- Bank Header -->
          <div style="display:flex; justify-content:space-between; border-bottom:2px solid #000; padding-bottom:10px; margin-bottom:12px;">
            <div>
              <div style="font-size:16px; font-weight:bold; text-transform:uppercase;" id="tpl_bank_name">Select Bank Account</div>
              <div style="font-size:11px; color:#555;" id="tpl_account_no">Account #: ----</div>
            </div>
            <div style="text-align:right;">
              <div style="font-size:12px; font-weight:bold;" id="tpl_cheque_no">Cheque #: Auto</div>
              <div style="font-size:11px;" id="tpl_cheque_date"><?= isset($prefDate) ? date('d-m-Y', strtotime($prefDate)) : date('d-m-Y') ?></div>
              <span id="tpl_type_badge" style="display:inline-block; border:1px solid #000; padding:1px 8px; font-size:9px; font-weight:bold; margin-top:2px;">A/C PAYEE</span>
            </div>
          </div>
          <!-- Pay To -->
          <div style="margin-bottom:10px;">
            <span style="font-weight:bold; font-size:11px;">PAY TO:</span>
            <span style="border-bottom:2px dotted #000; display:inline-block; width:calc(100% - 80px); padding:2px 4px; font-size:14px; font-weight:bold; min-height:22px;" id="tpl_payee">_________________________</span>
          </div>
          <!-- Amount Box & Words -->
          <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
            <div style="flex:1; margin-right:12px;">
              <span style="font-weight:bold; font-size:11px;">RUPEES:</span>
              <div style="border-bottom:2px dotted #000; font-style:italic; font-size:11px; min-height:18px; padding:2px 4px; text-transform:uppercase;" id="tpl_amount_words">Zero Rupees Only</div>
            </div>
            <div style="border:3px solid #000; padding:8px 15px; background:#fff; text-align:center; min-width:140px;">
              <div style="font-size:8px; font-weight:bold;">PKR</div>
              <div style="font-size:18px; font-weight:bold;" id="tpl_amount">0.00</div>
            </div>
          </div>
          <!-- Payment Type Badge -->
          <div style="text-align:right; margin-top:4px;">
            <span id="tpl_payment_type_badge" style="display:inline-block; border:2px solid #28a745; color:#28a745; padding:2px 10px; font-size:9px; font-weight:bold; border-radius:3px;">REGULAR</span>
          </div>
          <!-- Notes -->
          <div style="font-size:9px; color:#888; margin-top:8px; border-top:1px dashed #ccc; padding-top:4px;">
            <span style="font-weight:bold;">Memo:</span> <span id="tpl_notes">-</span>
          </div>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2">Bank Info</div>
      <div class="card-body p-2">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label form-label-sm">Bank Account</label>
            <select name="bank_account_id" id="bank_account_id" class="form-select form-select-sm" required>
              <option value="">Select Bank</option>
              <?php foreach ($banks as $b): $last4 = $b['account_number'] ? substr($b['account_number'], -4) : ''; ?>
                <option value="<?= (int)$b['id'] ?>"><?= esc($b['name']) ?><?= $last4 ? (' ••••'.esc($last4)) : '' ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Cheque Date</label>
            <input type="date" name="cheque_date" class="form-control form-control-sm" value="<?= esc($prefDate) ?>" required>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Cheque #</label>
            <input type="text" name="cheque_number" id="cheque_number" class="form-control form-control-sm" placeholder="Auto" >
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Cheque type</label>
            <select name="delivery_type" id="delivery_type" class="form-select form-select-sm">
              <option value="ac_payee">Cross cheque (transfer company to company)</option>
              <option value="self">Self cheque (paid to you)</option>
              <option value="cash">Cash cheque (only cashier can encash)</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Payment Type</label>
            <select name="payment_type" id="payment_type" class="form-select form-select-sm">
              <option value="regular" <?= $prefMode === 'advance' ? '' : 'selected' ?>>Regular (Expense/Bill)</option>
              <option value="advance" <?= $prefMode === 'advance' ? 'selected' : '' ?>>Advance (No Bill)</option>
            </select>
          </div>
          <div class="col-md-2">
            <label class="form-label form-label-sm">Notes / Narration <span class="text-danger">*</span></label>
            <input type="text" name="notes" class="form-control form-control-sm" placeholder="Why is this cheque being issued?" value="<?= esc($prefNotes) ?>" required>
          </div>
        </div>
        <?php if ($prefVendorId > 0 || $prefAmount > 0): ?>
        <div class="alert alert-info py-2 px-3 mt-2 mb-0 small">
          <strong>Prefilled from Vendor Payment:</strong>
          <?= $prefVendorId > 0 ? ('Vendor ID: '.(int)$prefVendorId) : '' ?>
          <?= $prefAmount > 0 ? (' | Suggested amount: '.number_format($prefAmount,2)) : '' ?>
          <?= $prefMode ? (' | Mode: '.esc(ucfirst($prefMode))) : '' ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2">Payee Info</div>
      <div class="card-body p-2">
        <div class="row g-2 mb-2">
          <div class="col-md-12">
            <label class="form-label form-label-sm me-3">Payee</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="payee_type" id="payee_vendor" value="vendor" checked>
              <label class="form-check-label" for="payee_vendor">Vendor</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="payee_type" id="payee_person" value="person">
              <label class="form-check-label" for="payee_person">Person</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="payee_type" id="payee_employee" value="employee">
              <label class="form-check-label" for="payee_employee">Employee</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="payee_type" id="payee_self" value="self">
              <label class="form-check-label" for="payee_self">Self</label>
            </div>
          </div>
          <div class="col-md-4 payee-vendor">
            <label class="form-label form-label-sm">Vendor</label>
            <select name="vendor_id" id="vendor_id" class="form-select form-select-sm" required>
              <option value="">Select Vendor</option>
              <?php foreach ($vendors as $v): ?>
                <option value="<?= (int)$v['id'] ?>" <?= ((int)$v['id'] === $prefVendorId ? 'selected' : '') ?>><?= esc($v['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 payee-vendor">
            <label class="form-label form-label-sm">Contact</label>
            <select name="contact_id" id="contact_id" class="form-select form-select-sm">
              <option value="">Select Contact (optional)</option>
            </select>
          </div>
          <div class="col-md-2 payee-vendor d-none" id="add_contact_col">
            <label class="form-label form-label-sm">&nbsp;</label>
            <button type="button" id="open_add_contact" class="btn btn-outline-secondary btn-sm w-100">Add Contact</button>
          </div>
          <div class="col-md-4 payee-person d-none">
            <label class="form-label form-label-sm">Payee Name</label>
            <input type="text" name="payee_name" id="payee_name" class="form-control form-control-sm" placeholder="Full name">
          </div>
          <div class="col-md-4 payee-employee d-none">
            <label class="form-label form-label-sm">Employee</label>
            <select name="employee_id" id="employee_id" class="form-select form-select-sm">
              <option value="">Select employee</option>
              <?php if (!empty($employees)): foreach ($employees as $emp): ?>
                <option value="<?= (int)$emp['id'] ?>"><?= esc(trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''))) ?><?= !empty($emp['employee_code']) ? ' • '.esc($emp['employee_code']) : '' ?></option>
              <?php endforeach; endif; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Advance Mode Banner -->
    <div class="alert alert-warning py-2 px-3 mb-3 d-none" id="advance_banner">
      <i class="bi bi-info-circle"></i> <strong>Advance Cheque Mode:</strong> This cheque will be recorded as an advance payment to the vendor. No bill is needed. You can later adjust this advance against pending vendor bills from the <a href="<?= base_url('/accounting/cheques/adjust-advance') ?>">Adjust Advance</a> page.
      <div class="mt-1 small" id="vendor_advance_info"></div>
    </div>

    <div id="regular_sections">
    <div class="card mb-3">
      <div class="card-header py-2">Expense Details</div>
      <div class="card-body p-2">
        <div class="table-responsive">
          <table class="table table-sm table-compact align-middle" id="lines_table">
            <thead>
              <tr>
                <th style="width:45%">Expense Account</th>
                <th style="width:35%">Description</th>
                <th style="width:15%" class="text-end">Amount</th>
                <th style="width:5%"></th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <select name="line_account_id[]" class="form-select form-select-sm">
                    <option value="">Select account</option>
                    <?php foreach ($expenseAccounts as $ea): ?>
                      <option value="<?= (int)$ea['id'] ?>"><?= esc($ea['code'].' - '.$ea['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </td>
                <td>
                  <input type="text" name="line_description[]" class="form-control form-control-sm" placeholder="Optional">
                </td>
                <td>
                  <input type="text" step="0.01" min="0" name="line_amount[]" class="form-control form-control-sm text-end" required>
                </td>
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs remove-line">×</button></td>
              </tr>
            </tbody>
          </table>
        </div>
        <button type="button" id="add_line" class="btn btn-outline-primary btn-xs">Add line</button>
        <div class="float-end pe-1">
          <div id="amount_in_words" class="text-muted small mb-1" style="font-style: italic;"></div>
          <strong>Lines Total: <span id="total_amount">0.00</span></strong>
        </div>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header py-2">Bills & PO Payments</div>
      <div class="card-body p-2">
        <div class="table-responsive">
          <table class="table table-sm table-compact align-middle" id="bills_table">
            <thead>
              <tr>
                <th style="width:25%">PO #</th>
                <th style="width:20%">Date</th>
                <th style="width:20%" class="text-end">Amount</th>
                <th style="width:20%" class="text-end">Balance</th>
                <th style="width:15%" class="text-end">Pay Now</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
        <div class="d-flex gap-2">
          <button type="button" id="load_bills" class="btn btn-outline-secondary btn-xs">Load Bills</button>
          <button type="button" id="auto_apply" class="btn btn-outline-primary btn-xs">Auto-Apply</button>
        </div>
        <div class="mt-2 small text-muted">Tip: Use cheque for vendor bill settlement or vendor advance and keep cash/bank payments in Vendor Payment page.</div>
      </div>
    </div>
    </div><!-- /regular_sections -->

    <!-- Advance Mode: Simple Amount Entry -->
    <div id="advance_sections" class="d-none">
    <div class="card mb-3">
      <div class="card-header py-2"><i class="bi bi-cash-stack"></i> Advance Amount</div>
      <div class="card-body p-2">
        <div class="row g-2">
          <div class="col-md-4">
            <label class="form-label form-label-sm">Advance Amount (PKR)</label>
            <input type="text" id="advance_amount" class="form-control form-control-sm text-end" placeholder="Enter amount" value="<?= $prefMode === 'advance' && $prefAmount > 0 ? number_format($prefAmount,2) : '' ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Description / Purpose</label>
            <input type="text" id="advance_description" class="form-control form-control-sm" placeholder="e.g. Advance for material purchase">
          </div>
          <div class="col-md-4">
            <label class="form-label form-label-sm">Amount in Words</label>
            <div class="form-control form-control-sm bg-light" id="advance_amount_words" style="font-style:italic; min-height:31px;">-</div>
          </div>
        </div>
      </div>
    </div>
    </div><!-- /advance_sections -->

    <div class="card mb-3">
      <div class="card-header py-2">Cheque Summary</div>
      <div class="card-body p-2">
        <div class="row g-2">
          <div class="col-md-3"><div class="small text-muted">Expense Lines</div><div class="fw-semibold" id="sum_lines">0.00</div></div>
          <div class="col-md-3"><div class="small text-muted">Bill Payments</div><div class="fw-semibold" id="sum_bills">0.00</div></div>
          <div class="col-md-3"><div class="small text-muted">Cheque Total</div><div class="fw-bold" id="sum_total">0.00</div></div>
          <div class="col-md-3"><div class="small text-muted">Target</div><div class="fw-semibold" id="sum_target"><?= number_format($prefAmount,2) ?></div></div>
        </div>
        <div class="mt-2 small" id="sum_status"></div>
      </div>
    </div>

    <div class="text-end">
      <button type="submit" class="btn btn-success btn-sm"><i class="bi bi-check-circle"></i> Post Cheque</button>
    </div>
  </form>
</div>

<script>
(function(){
  const PREFILL = {
    amount: <?= json_encode($prefAmount) ?>,
    mode: <?= json_encode($prefMode) ?>,
    memo: <?= json_encode($prefMemo) ?>,
  };

  // --- Payment Type Toggle ---
  const paymentTypeSelect = document.getElementById('payment_type');
  const regularSections = document.getElementById('regular_sections');
  const advanceSections = document.getElementById('advance_sections');
  const advanceBanner = document.getElementById('advance_banner');
  const advanceAmountInput = document.getElementById('advance_amount');
  const advanceDescInput = document.getElementById('advance_description');

  function togglePaymentType() {
    const isAdvance = paymentTypeSelect.value === 'advance';
    regularSections.classList.toggle('d-none', isAdvance);
    advanceSections.classList.toggle('d-none', !isAdvance);
    advanceBanner.classList.toggle('d-none', !isAdvance);
    // When switching to advance, sync amount
    if (isAdvance) {
      syncAdvanceToLines();
      // Load vendor advance balance
      const vid = document.getElementById('vendor_id').value;
      if (vid) loadVendorAdvanceInfo(vid);
    }
    recalc();
    updateChequeTemplate();
  }
  paymentTypeSelect.addEventListener('change', togglePaymentType);

  function syncAdvanceToLines() {
    // For advance mode, inject amount into the hidden line fields
    const amt = removeCommas(advanceAmountInput.value || '0');
    const desc = advanceDescInput.value || 'Vendor Advance';
    // Set first line amount and description
    const lineAmounts = document.querySelectorAll('input[name="line_amount[]"]');
    const lineDescs = document.querySelectorAll('input[name="line_description[]"]');
    if (lineAmounts.length > 0) {
      lineAmounts[0].value = amt > 0 ? amt.toString() : '';
      if (lineDescs.length > 0) lineDescs[0].value = desc;
    }
  }

  advanceAmountInput.addEventListener('input', function() {
    syncAdvanceToLines();
    recalc();
    updateChequeTemplate();
    // Update advance amount in words
    const amt = removeCommas(this.value || '0');
    document.getElementById('advance_amount_words').innerText = amt > 0 ? numberToWords(amt) : '-';
  });
  advanceAmountInput.addEventListener('blur', function() {
    const num = removeCommas(this.value);
    if (num > 0) this.value = formatWithCommas(num);
  });
  advanceAmountInput.addEventListener('focus', function() {
    if (this.value) this.value = removeCommas(this.value).toString();
  });
  advanceDescInput.addEventListener('input', function() {
    syncAdvanceToLines();
    updateChequeTemplate();
  });

  function loadVendorAdvanceInfo(vendorId) {
    fetch(`<?= base_url('/accounting/cheques/vendorAdvanceBalance') ?>?vendor_id=${vendorId}`, {
      headers: {'X-Requested-With':'XMLHttpRequest'}
    }).then(r=>r.json()).then(j=>{
      if (j && j.success) {
        const info = document.getElementById('vendor_advance_info');
        info.innerHTML = `<strong>Current Advance Balance:</strong> PKR ${Number(j.balance||0).toLocaleString('en-US',{minimumFractionDigits:2})}`;
      }
    }).catch(()=>{});
  }

  // --- Cheque Template Preview ---
  const togglePreviewBtn = document.getElementById('togglePreview');
  const previewBody = document.getElementById('chequePreviewBody');
  togglePreviewBtn.addEventListener('click', function() {
    previewBody.classList.toggle('d-none');
    this.innerHTML = previewBody.classList.contains('d-none') ? '<i class="bi bi-eye"></i> Show' : '<i class="bi bi-eye-slash"></i> Hide';
  });

  function updateChequeTemplate() {
    const isAdvance = paymentTypeSelect.value === 'advance';
    // Bank name
    const bankSelect = document.getElementById('bank_account_id');
    const bankText = bankSelect.options[bankSelect.selectedIndex]?.text || 'Select Bank Account';
    document.getElementById('tpl_bank_name').textContent = bankText.split(' ••••')[0] || bankText;
    const bankAccNo = bankText.includes('••••') ? 'Account #: ' + bankText.split('••••')[1]?.trim() : 'Account #: ----';
    document.getElementById('tpl_account_no').textContent = bankAccNo;

    // Cheque number
    const chequeNum = document.getElementById('cheque_number').value || 'Auto';
    document.getElementById('tpl_cheque_no').textContent = 'Cheque #: ' + chequeNum;

    // Date (display as DD-MM-YYYY)
    const chequeDate = document.querySelector('input[name="cheque_date"]').value || '-';
    let chqDateDisplay = chequeDate;
    if(chequeDate && chequeDate.indexOf('-')>0 && chequeDate.length===10){
      const dp=chequeDate.split('-'); chqDateDisplay=dp[2]+'-'+dp[1]+'-'+dp[0];
    }
    document.getElementById('tpl_cheque_date').textContent = chqDateDisplay;

    // Delivery type badge
    const deliveryType = document.getElementById('delivery_type').value;
    const typeMap = {'ac_payee':'A/C PAYEE','self':'SELF','cash':'CASH'};
    document.getElementById('tpl_type_badge').textContent = typeMap[deliveryType] || 'A/C PAYEE';

    // Payment type badge
    const ptBadge = document.getElementById('tpl_payment_type_badge');
    if (isAdvance) {
      ptBadge.textContent = 'ADVANCE';
      ptBadge.style.borderColor = '#dc3545';
      ptBadge.style.color = '#dc3545';
    } else {
      ptBadge.textContent = 'REGULAR';
      ptBadge.style.borderColor = '#28a745';
      ptBadge.style.color = '#28a745';
    }

    // Payee
    const payeeType = document.querySelector('input[name="payee_type"]:checked')?.value || 'vendor';
    let payeeName = '_________________________';
    if (payeeType === 'vendor') {
      const vSel = document.getElementById('vendor_id');
      payeeName = vSel.options[vSel.selectedIndex]?.text || payeeName;
      if (payeeName === 'Select Vendor') payeeName = '_________________________';
    } else if (payeeType === 'person') {
      payeeName = document.getElementById('payee_name').value || '_________________________';
    } else if (payeeType === 'employee') {
      const eSel = document.getElementById('employee_id');
      payeeName = eSel.options[eSel.selectedIndex]?.text || '_________________________';
      if (payeeName === 'Select employee') payeeName = '_________________________';
    } else {
      payeeName = 'SELF';
    }
    document.getElementById('tpl_payee').textContent = payeeName;

    // Amount
    let totalAmt = 0;
    if (isAdvance) {
      totalAmt = removeCommas(advanceAmountInput.value || '0');
    } else {
      document.querySelectorAll('input[name="line_amount[]"]').forEach(i => { totalAmt += removeCommas(i.value || 0); });
      document.querySelectorAll('input[name="bill_amount[]"]').forEach(i => { totalAmt += removeCommas(i.value || 0); });
    }
    document.getElementById('tpl_amount').textContent = formatWithCommas(totalAmt);
    document.getElementById('tpl_amount_words').textContent = totalAmt > 0 ? numberToWords(totalAmt) : 'Zero Rupees Only';

    // Notes
    const notes = document.querySelector('input[name="notes"]').value || '-';
    document.getElementById('tpl_notes').textContent = notes;
  }

  // Attach template update to all relevant fields
  ['bank_account_id','cheque_number','delivery_type','payment_type','vendor_id','employee_id','payee_name'].forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.addEventListener('change', updateChequeTemplate);
      el.addEventListener('input', updateChequeTemplate);
    }
  });
  document.querySelector('input[name="cheque_date"]')?.addEventListener('change', updateChequeTemplate);
  document.querySelector('input[name="notes"]')?.addEventListener('input', updateChequeTemplate);
  document.querySelectorAll('input[name="payee_type"]').forEach(r => r.addEventListener('change', updateChequeTemplate));

  // Format number with commas
  const formatWithCommas = (value) => {
    const num = parseFloat(value.toString().replace(/,/g, '')) || 0;
    return num.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
  };

  // Remove commas for calculation
  const removeCommas = (value) => {
    return parseFloat(value.toString().replace(/,/g, '')) || 0;
  };

  // Convert number to words (supports up to billions)
  const numberToWords = (num) => {
    if (num === 0) return 'Zero';
    
    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine'];
    const teens = ['Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    const convertLessThanThousand = (n) => {
      if (n === 0) return '';
      if (n < 10) return ones[n];
      if (n < 20) return teens[n - 10];
      if (n < 100) {
        const ten = Math.floor(n / 10);
        const one = n % 10;
        return tens[ten] + (one > 0 ? ' ' + ones[one] : '');
      }
      const hundred = Math.floor(n / 100);
      const remainder = n % 100;
      return ones[hundred] + ' Hundred' + (remainder > 0 ? ' ' + convertLessThanThousand(remainder) : '');
    };
    
    let intPart = Math.floor(num);
    const decimalPart = Math.round((num - intPart) * 100);
    
    let result = '';
    
    if (intPart >= 1000000000) {
      const billions = Math.floor(intPart / 1000000000);
      result += convertLessThanThousand(billions) + ' Billion ';
      intPart %= 1000000000;
    }
    if (intPart >= 1000000) {
      const millions = Math.floor(intPart / 1000000);
      result += convertLessThanThousand(millions) + ' Million ';
      intPart %= 1000000;
    }
    if (intPart >= 1000) {
      const thousands = Math.floor(intPart / 1000);
      result += convertLessThanThousand(thousands) + ' Thousand ';
      intPart %= 1000;
    }
    if (intPart > 0) {
      result += convertLessThanThousand(intPart);
    }
    
    result = result.trim() + ' Rupees';
    
    if (decimalPart > 0) {
      result += ' and ' + convertLessThanThousand(decimalPart) + ' Paisa';
    }
    
    return result + ' Only';
  };

  const fmt = x => (Number(x||0).toFixed(2));
  const recalc = ()=>{
    const isAdvance = paymentTypeSelect.value === 'advance';

    let linesTot=0;
    if (isAdvance) {
      linesTot = removeCommas(advanceAmountInput.value || '0');
    } else {
      document.querySelectorAll('input[name="line_amount[]"]').forEach(i=>{ 
        const rawValue = i.value;
        const val = removeCommas(rawValue);
        linesTot += val;
      });
    }

    let billsTot=0;
    if (!isAdvance) {
      document.querySelectorAll('input[name="bill_amount[]"]').forEach(i=>{
        billsTot += removeCommas(i.value || 0);
      });
    }

    const tot = linesTot + billsTot;
    
    // Display formatted total
    document.getElementById('total_amount').innerText = formatWithCommas(linesTot);
    const sumLines = document.getElementById('sum_lines');
    const sumBills = document.getElementById('sum_bills');
    const sumTotal = document.getElementById('sum_total');
    const sumStatus = document.getElementById('sum_status');
    if (sumLines) sumLines.innerText = formatWithCommas(linesTot);
    if (sumBills) sumBills.innerText = formatWithCommas(billsTot);
    if (sumTotal) sumTotal.innerText = formatWithCommas(tot);

    if (sumStatus) {
      if (PREFILL.amount > 0) {
        const diff = PREFILL.amount - tot;
        if (Math.abs(diff) < 0.01) {
          sumStatus.innerHTML = '<span class="text-success">✔ Cheque total matches target amount.</span>';
        } else if (diff > 0) {
          sumStatus.innerHTML = '<span class="text-warning">Remaining to allocate: ' + formatWithCommas(diff) + '</span>';
        } else {
          sumStatus.innerHTML = '<span class="text-danger">Over allocated by: ' + formatWithCommas(Math.abs(diff)) + '</span>';
        }
      } else {
        sumStatus.innerHTML = '<span class="text-muted">Set amounts in lines or bills to compute final cheque total.</span>';
      }
    }
    
    // Update amount in words
    const wordsElement = document.getElementById('amount_in_words');
    if (tot > 0) {
      const words = numberToWords(tot);
      wordsElement.innerText = words;
    } else {
      wordsElement.innerText = '';
    }
    // Update live cheque template
    updateChequeTemplate();
  };

  // Format amount inputs with commas on blur
  const formatAmountInput = (input) => {
    if (input.value) {
      const num = removeCommas(input.value);
      input.value = formatWithCommas(num);
    }
  };

  // Remove commas on focus for easier editing
  const unformatAmountInput = (input) => {
    if (input.value) {
      input.value = removeCommas(input.value).toString();
    }
  };

  // Add event listeners to existing amount inputs
  document.querySelectorAll('input[name="line_amount[]"]').forEach(input => {
    input.addEventListener('focus', function() { unformatAmountInput(this); });
    input.addEventListener('blur', function() { formatAmountInput(this); recalc(); });
    input.addEventListener('input', function() { recalc(); });
  });

  document.getElementById('add_line').addEventListener('click', function(){
    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>
        <select name="line_account_id[]" class="form-select form-select-sm">
          <option value="">Select account</option>
          <?php foreach ($expenseAccounts as $ea): ?>
            <option value="<?= (int)$ea['id'] ?>"><?= esc($ea['code'].' - '.$ea['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </td>
      <td><input type="text" name="line_description[]" class="form-control form-control-sm" placeholder="Optional"></td>
      <td><input type="text" step="0.01" min="0" name="line_amount[]" class="form-control form-control-sm text-end" required></td>
      <td class="text-center"><button type="button" class="btn btn-outline-danger btn-xs remove-line">×</button></td>`;
    document.querySelector('#lines_table tbody').appendChild(tr);
    
    // Add event listeners to new amount input
    const newInput = tr.querySelector('input[name="line_amount[]"]');
    newInput.addEventListener('focus', function() { unformatAmountInput(this); });
    newInput.addEventListener('blur', function() { formatAmountInput(this); recalc(); });
    newInput.addEventListener('input', function() { recalc(); });
  });

  document.addEventListener('click', function(e){ if (e.target && e.target.classList.contains('remove-line')) { e.target.closest('tr').remove(); recalc(); }});
  
  // Before form submit, remove commas from all amount inputs and submit via AJAX to capture server response
  document.querySelector('form').addEventListener('submit', async function(e) {
    e.preventDefault();
    const form = this;
    const isAdvance = paymentTypeSelect.value === 'advance';

    // Notes/narration is mandatory — user must explain why this cheque is being issued
    const notesInput = document.querySelector('input[name="notes"]');
    if(!notesInput || !notesInput.value.trim()){
      showAlert('warning', 'Enter cheque notes — describe why this cheque is being issued.');
      if(notesInput) notesInput.focus();
      return;
    }

    // Sync advance amount to hidden line fields before submit
    if (isAdvance) {
      syncAdvanceToLines();
    }

    // remove commas for amounts
    document.querySelectorAll('input[name="line_amount[]"]').forEach(input => {
      if (input.value) {
        input.value = removeCommas(input.value).toString();
      }
    });

    // If the form is invalid, find the first invalid control
    if (!form.checkValidity()) {
      const invalid = form.querySelector(':invalid');
      try {
        console.error('Form invalid. First invalid element:', invalid);
        if (invalid) {
          const isVisible = (invalid.offsetParent !== null);
          if (!isVisible && invalid.hasAttribute('required')) {
            // clear hidden required so user can submit
            invalid.removeAttribute('required');
            form.querySelectorAll('[required]').forEach(r => { if (r.offsetParent === null) r.removeAttribute('required'); });
            alert('Some hidden required fields were auto-cleared to allow submission. Please review the form if needed.');
          }
        }
      } catch (err) { console.error(err); }
      if (!form.checkValidity()) { form.reportValidity(); return; }
    }

    // Build FormData and submit via fetch
    const fd = new FormData(form);
    const action = form.getAttribute('action') || window.location.href;
    // disable submit button to prevent duplicate posts
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) { submitBtn.disabled = true; submitBtn.dataset.origText = submitBtn.textContent; submitBtn.textContent = 'Posting...'; }

    try {
      // show an inline 'posting' message
      showAlert('info', 'Posting cheque — please wait...', 0);

      const resp = await fetch(action, {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      const contentType = resp.headers.get('content-type') || '';
      if (contentType.indexOf('application/json') !== -1) {
        const j = await resp.json();
        if (j && j.success) {
          // success: show inline message then redirect
          showAlert('success', j.message || 'Cheque posted successfully', 3000);
          if (j.redirect) { setTimeout(()=> window.location.href = j.redirect, 800); } else { setTimeout(()=> window.location.href = '<?= base_url('/accounting/cheques') ?>', 800); }
        } else {
          showAlert('danger', 'Failed to post cheque: ' + (j.message || JSON.stringify(j)), 8000);
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.origText || 'Post Cheque'; }
        }
      } else {
        // non-JSON (HTML redirect or error). If 200 and contains redirect, follow; otherwise open response text for debugging
        if (resp.status >= 200 && resp.status < 300) {
          const text = await resp.text();
          // try to detect a redirect meta or location
          if (resp.url && resp.url.indexOf('/accounting/cheques') !== -1) {
            window.location.href = resp.url;
          } else {
            // show HTML in a new window for debugging
            const w = window.open('', '_blank'); w.document.open(); w.document.write(text); w.document.close();
            showAlert('danger', 'Server returned HTML response (opened in new tab) — check it for details', 8000);
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.origText || 'Post Cheque'; }
          }
        } else {
          const text = await resp.text();
          showAlert('danger', 'Server error: ' + resp.status + '\n' + text, 10000);
          if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.origText || 'Post Cheque'; }
        }
      }
    } catch (err) {
      console.error(err);
      showAlert('danger', 'Network or server error while posting cheque. See console for details.', 10000);
      if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = submitBtn.dataset.origText || 'Post Cheque'; }
    }
  });

  // Inline alert helper
  function showAlert(type, message, timeout = 4000) {
    try {
      let container = document.getElementById('ajax_alert');
      if (!container) {
        container = document.createElement('div');
        container.id = 'ajax_alert';
        const formEl = document.querySelector('form');
        formEl.parentNode.insertBefore(container, formEl);
      }
      container.innerHTML = `<div class="alert alert-${type} small mb-3" role="alert">${message}</div>`;
      if (timeout && timeout > 0) {
        setTimeout(()=>{ try{ if (container) container.innerHTML = ''; }catch(e){} }, timeout);
      }
    } catch(e){ console.error(e); }
  }
  
  recalc();

  // Payee switching
  const vendorSelect = document.getElementById('vendor_id');
  const contactSelect = document.getElementById('contact_id');

  const togglePayee = ()=>{
    const t = document.querySelector('input[name="payee_type"]:checked').value;
  document.querySelectorAll('.payee-vendor').forEach(el=>el.classList.toggle('d-none', t!=='vendor'));
  document.querySelectorAll('.payee-person').forEach(el=>el.classList.toggle('d-none', t!=='person'));
  document.querySelectorAll('.payee-employee').forEach(el=>el.classList.toggle('d-none', t!=='employee'));
    
    // Toggle required attributes
    const vendorSelect = document.getElementById('vendor_id');
    const payeeNameInput = document.getElementById('payee_name');
    
    if (t === 'vendor') {
      vendorSelect.setAttribute('required', 'required');
      payeeNameInput.removeAttribute('required');
      document.getElementById('employee_id').removeAttribute('required');
      // contact remains optional (do not force HTML required)
      // hide/show add contact column depends on vendor selection
      document.getElementById('add_contact_col').classList.remove('d-none');
    } else if (t === 'person') {
      vendorSelect.removeAttribute('required');
      payeeNameInput.setAttribute('required', 'required');
      contactSelect.removeAttribute('required');
      document.getElementById('employee_id').removeAttribute('required');
      document.getElementById('add_contact_col').classList.add('d-none');
    } else if (t === 'employee') {
      vendorSelect.removeAttribute('required');
      payeeNameInput.removeAttribute('required');
      contactSelect.removeAttribute('required');
      document.getElementById('employee_id').setAttribute('required','required');
      document.getElementById('add_contact_col').classList.add('d-none');
    } else {
      vendorSelect.removeAttribute('required');
      payeeNameInput.removeAttribute('required');
      contactSelect.removeAttribute('required');
      document.getElementById('employee_id').removeAttribute('required');
      document.getElementById('add_contact_col').classList.add('d-none');
    }
  };
  document.querySelectorAll('input[name="payee_type"]').forEach(i=>i.addEventListener('change', togglePayee));
  togglePayee();

  // Load contacts when vendor changes
  vendorSelect.addEventListener('change', function(){
    const vid = this.value; contactSelect.innerHTML = '<option value="">Select Contact (optional)</option>';
    if (!vid) return;
    fetch(`<?= base_url('/accounting/cheques/vendor-contacts/') ?>${vid}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
      .then(r=>r.json()).then(j=>{
        if (j && j.success && Array.isArray(j.data)) {
          j.data.forEach(c=>{
            const opt = document.createElement('option');
            opt.value = c.id; opt.textContent = c.name + (c.cnic? (' / '+c.cnic):'') + (c.phone? (' / '+c.phone):'');
            contactSelect.appendChild(opt);
          });
          // show add contact button
          document.getElementById('add_contact_col').classList.remove('d-none');
        }
      }).catch(()=>{});
    // Load advance info if in advance mode
    if (paymentTypeSelect.value === 'advance') {
      loadVendorAdvanceInfo(vid);
    }
    updateChequeTemplate();
  });
  
  // Inline add contact form (show/hide)
  const addContactCol = document.getElementById('add_contact_col');
  const openAddContact = document.getElementById('open_add_contact');
  // create inline form element
  const inlineFormWrapper = document.createElement('div'); inlineFormWrapper.className='mt-2 d-none'; inlineFormWrapper.id='inline_contact_form';
  inlineFormWrapper.innerHTML = `
    <div class="input-group input-group-sm">
      <input id="inline_contact_name" class="form-control" placeholder="Name">
      <input id="inline_contact_phone" class="form-control" placeholder="Phone">
      <button id="inline_contact_save" type="button" class="btn btn-primary">Save</button>
      <button id="inline_contact_cancel" type="button" class="btn btn-secondary">Cancel</button>
    </div>`;
  addContactCol.parentElement.appendChild(inlineFormWrapper);

  openAddContact.addEventListener('click', function(){
    if (!vendorSelect.value) return alert('Select vendor first');
    inlineFormWrapper.classList.remove('d-none');
    openAddContact.classList.add('d-none');
  });
  document.getElementById('inline_contact_cancel').addEventListener('click', function(){ inlineFormWrapper.classList.add('d-none'); openAddContact.classList.remove('d-none'); });
  document.getElementById('inline_contact_save').addEventListener('click', function(){
    const name = document.getElementById('inline_contact_name').value.trim(); if (!name) return alert('Name required');
    const phone = document.getElementById('inline_contact_phone').value.trim();
    const vid = vendorSelect.value; if (!vid) return alert('Select vendor first');
    const fd = new FormData(); fd.append('name', name); fd.append('phone', phone||'');
    // append CSRF token if available (main cheque form has csrf_field)
    try {
      const csrfName = '<?= csrf_token() ?>';
      const csrfInput = document.querySelector('form input[name="<?= csrf_token() ?>"]');
      if (csrfInput && csrfInput.value) fd.append(csrfName, csrfInput.value);
    } catch(err){}
    fetch(`<?= base_url('vendors/') ?>${vid}/addContact`, { method:'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }})
      .then(r=>r.json()).then(j=>{
        if (j && j.success && j.data) {
          const opt = document.createElement('option'); opt.value = j.data.id; opt.textContent = j.data.name + (j.data.cnic?(' / '+j.data.cnic):'') + (j.data.phone?(' / '+j.data.phone):''); opt.selected = true; contactSelect.appendChild(opt);
          // reset and hide
          document.getElementById('inline_contact_name').value=''; document.getElementById('inline_contact_phone').value=''; inlineFormWrapper.classList.add('d-none'); openAddContact.classList.remove('d-none');
        } else { alert(j.message || 'Failed to add contact'); }
      }).catch(()=>alert('Failed to add contact'));
  });

  // Bills loading and auto-apply
  const billsTbody = document.querySelector('#bills_table tbody');
  function renderBills(rows){
    billsTbody.innerHTML = '';
    rows.filter(r=> (parseFloat(r.balance)||0) > 0).forEach(r=>{
      const tr=document.createElement('tr');
      tr.innerHTML = `
        <td>${r.bill_number || r.order_number || r.id}</td>
        <td>${r.bill_date || r.order_date || ''}</td>
        <td class="text-end">${Number(r.total||0).toFixed(2)}</td>
        <td class="text-end">${Number(r.balance||0).toFixed(2)}</td>
        <td class="text-end">
          <input type="hidden" name="bill_id[]" value="${r.id}">
          <input type="number" step="0.01" min="0" max="${Number(r.balance||0).toFixed(2)}" name="bill_amount[]" class="form-control form-control-sm text-end bill-amt" value="0">
        </td>`;
      billsTbody.appendChild(tr);
    });
  }
  document.getElementById('load_bills').addEventListener('click', function(){
    const vid = vendorSelect.value; if (!vid) return;
    fetch(`<?= base_url('/accounting/cheques/vendor-bills/') ?>${vid}`, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
      .then(r=>r.json()).then(j=>{ if (j && j.success) renderBills(j.data||[]); });
  });
  document.getElementById('auto_apply').addEventListener('click', function(){
    // Apply available total to bills in order
    let avail = (PREFILL.amount > 0 ? PREFILL.amount : 0);
    if (avail <= 0) {
      document.querySelectorAll('input[name="line_amount[]"]').forEach(i=> avail += removeCommas(i.value||0));
    }
    const inputs = billsTbody.querySelectorAll('input.bill-amt');
    inputs.forEach(inp=>{
      const max = parseFloat(inp.getAttribute('max')) || 0; const v = Math.min(avail, max); inp.value = v.toFixed(2); avail -= v; });
    recalc();
  });

  // Prefill behavior
  const memoInput = document.querySelector('input[name="line_description[]"]');
  if (PREFILL.memo && memoInput && !memoInput.value) memoInput.value = PREFILL.memo;

  if (PREFILL.amount > 0 && PREFILL.mode === 'advance') {
    const firstAmt = document.querySelector('input[name="line_amount[]"]');
    if (firstAmt && !firstAmt.value) {
      firstAmt.value = formatWithCommas(PREFILL.amount);
    }
    // Also set advance amount input
    if (advanceAmountInput && !advanceAmountInput.value) {
      advanceAmountInput.value = formatWithCommas(PREFILL.amount);
    }
  }

  if (vendorSelect.value) {
    if (paymentTypeSelect.value !== 'advance') {
      document.getElementById('load_bills').click();
      setTimeout(()=>{
        if (PREFILL.amount > 0 && PREFILL.mode !== 'advance') {
          document.getElementById('auto_apply').click();
        }
        recalc();
      }, 300);
    }
  }

  // Initialize payment type toggle and cheque template
  togglePaymentType();
  updateChequeTemplate();
  recalc();
})();
</script>
<?= $this->endSection() ?>
