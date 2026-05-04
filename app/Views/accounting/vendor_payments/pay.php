<?php /** @var array $vendors */ /** @var array $sourceAccounts */ /** @var array $methods */ ?>
<?= $this->extend('layouts/main') ?>
<?= $this->section('content') ?>

<style>
  .vp { max-width: 1440px; margin: 0 auto; }
  .vp .vp-card {
    background: var(--white, #fff); border: 1px solid var(--gray-200, #e2e8f0);
    border-radius: .5rem; color: var(--gray-600, #475569);
  }
  .vp .vp-hdr {
    padding: .5rem .85rem; border-bottom: 1px solid var(--gray-200, #e2e8f0);
    font-size: .82rem; font-weight: 600; color: var(--gray-600, #475569);
    display: flex; justify-content: space-between; align-items: center;
  }

  /* Table */
  .vp .bt { margin: 0; }
  .vp .bt th {
    font-size: .68rem; text-transform: uppercase; letter-spacing: .4px;
    padding: .45rem .5rem; white-space: nowrap;
    background: var(--gray-100, #f1f5f9); color: var(--gray-500, #64748b);
    border-bottom: 2px solid var(--gray-200, #e2e8f0);
    position: sticky; top: 0; z-index: 2;
  }
  .vp .bt td {
    padding: .4rem .5rem; font-size: .82rem; vertical-align: middle;
    border-bottom: 1px solid var(--gray-200, #e2e8f0); color: var(--gray-600, #475569);
  }
  .vp .bt tbody tr { transition: background .1s; }
  .vp .bt tbody tr:hover { background: var(--gray-50, #f8fafc); }
  .vp .bt tbody tr.row-sel { background: rgba(59,130,246,.05); }
  .vp .bt .bill-fp td { opacity: .35; }

  /* Column header colors */
  .vp .th-adv { color: #16a34a !important; }
  .vp .th-cash { color: #2563eb !important; }

  /* Pay inputs */
  .vp .pay-in {
    width: 100px; text-align: right; font-size: .8rem;
    padding: .25rem .4rem; border-radius: .25rem;
    background: var(--gray-50, #fff); border: 1px solid var(--gray-300, #cbd5e1);
    color: var(--gray-600, #334155); transition: border-color .15s;
  }
  .vp .pay-in:focus { outline: none; box-shadow: 0 0 0 2px rgba(59,130,246,.1); }
  .vp .pay-in.in-adv { border-color: #86efac; }
  .vp .pay-in.in-adv:focus { border-color: #16a34a; box-shadow: 0 0 0 2px rgba(22,163,74,.12); }
  .vp .pay-in.in-cash { border-color: #93c5fd; }
  .vp .pay-in.in-cash:focus { border-color: #2563eb; box-shadow: 0 0 0 2px rgba(37,99,235,.12); }

  /* Advance info strip */
  .vp .adv-strip {
    display: flex; align-items: center; gap: .75rem;
    padding: .45rem .85rem; font-size: .8rem;
    border-bottom: 1px solid var(--gray-200, #e2e8f0);
    color: var(--gray-500, #64748b);
  }
  .vp .adv-strip .adv-amt { font-weight: 700; font-size: .9rem; }
  .vp .adv-strip .adv-pos { color: #16a34a; }
  .vp .adv-strip .adv-zero { color: var(--gray-400, #94a3b8); }

  /* Summary */
  .vp .sr { display: flex; justify-content: space-between; padding: .2rem 0; font-size: .82rem; color: var(--gray-500, #64748b); }
  .vp .sr .v { font-weight: 600; color: var(--gray-700, #334155); }
  .vp .sr-t { border-top: 2px solid var(--gray-300, #cbd5e1); margin-top: .35rem; padding-top: .4rem; }
  .vp .sr-t .v { font-size: 1rem; color: #2563eb; font-weight: 700; }

  /* Empty */
  .vp .emp { padding: 1.5rem 1rem; text-align: center; color: var(--gray-400, #94a3b8); font-size: .85rem; }

  /* Scroll */
  .vp .bs { max-height: 360px; overflow-y: auto; }

  /* Advance-only mode */
  .vp .adv-only-box {
    padding: .65rem 1rem; display:flex; align-items:center; gap:1rem;
    background: rgba(22,163,74,.04);
    border-top: 1px solid rgba(22,163,74,.15);
  }
  .vp .adv-only-box .amt-in {
    width: 160px; text-align: right; font-size: 1.1rem; font-weight: 700;
    padding: .3rem .6rem; border-radius: .3rem;
    background: var(--white, #fff); border: 1.5px solid #16a34a;
    color: #16a34a;
  }
  .vp .adv-only-box .amt-in:focus { outline: none; box-shadow: 0 0 0 2px rgba(22,163,74,.15); }
  .vp .toggle-adv { cursor: pointer; user-select: none; }
  .vp .toggle-adv:hover { opacity: .8; }

  .vp .cheque-bridge {
    margin-top: .45rem; padding: .45rem .6rem; border-radius: .4rem;
    border: 1px dashed rgba(37,99,235,.35); background: rgba(37,99,235,.06);
    display: flex; align-items: center; justify-content: space-between; gap: .5rem;
    font-size: .78rem;
  }

  /* Hide advance column when no advance available */
  .vp .col-adv-hidden,
  .vp .col-adv-hidden .col-adv,
  #col_adv_header.hidden,
  .col-adv.hidden { display: none !important; }
  /* Lightbox */
  .vp-lightbox-bg {position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(18,22,34,.85);z-index:9999;display:flex;align-items:center;justify-content:center;}
  .vp-lightbox-img {max-width:90vw;max-height:90vh;box-shadow:0 8px 32px #000a;border-radius:8px;}
</style>

<div class="container-fluid px-3 py-2 vp">

  <!-- Header -->
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold" style="color:var(--gray-700,#1e293b)">
      <?php if (isset($payment) && !empty($payment['id'])): ?>
        <i class="bi bi-pencil me-1" style="color:#f59e0b"></i>Edit Draft Payment #<?= (int)$payment['id'] ?>
      <?php else: ?>
        Vendor Payment
      <?php endif; ?>
    </h5>
    <div class="d-flex gap-2">
      <?php if (isset($payment) && !empty($payment['id'])): ?>
        <a href="<?= base_url('accounting/vendor-payments/' . (int)$payment['id']) ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back to Payment</a>
      <?php else: ?>
        <a href="<?= base_url('accounting/vendor-payments') ?>" class="btn btn-outline-primary btn-sm">Drafts</a>
        <a href="<?= base_url('/accounting/cheques') ?>" class="btn btn-outline-secondary btn-sm">Back</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Vendor + Payment Config -->
  <div class="vp-card mb-3">
    <div class="card-body py-2 px-3">
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label mb-1 small fw-semibold">Vendor <span class="text-danger">*</span></label>
          <select id="vendor_id" class="form-select form-select-sm">
            <option value="">-- Select Vendor --</option>
            <?php foreach ($vendors as $v): ?>
              <option value="<?= (int)$v['id'] ?>"
                <?php
                  $sel = false;
                  if (isset($payment) && $payment['vendor_id'] == $v['id']) $sel = true;
                  elseif (!empty($preVendorId) && (int)$preVendorId === (int)$v['id']) $sel = true;
                  echo $sel ? 'selected' : '';
                ?>
              ><?= esc($v['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1 small fw-semibold">Date</label>
          <input type="date" id="payment_date" class="form-control form-control-sm" value="<?= isset($payment) ? esc($payment['payment_date']) : date('Y-m-d') ?>">
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1 small fw-semibold">Method</label>
          <select id="payment_method" class="form-select form-select-sm">
            <?php foreach ($methods as $val => $lbl): ?>
              <option value="<?= esc($val) ?>" <?= isset($payment) && $payment['payment_method'] == $val ? 'selected' : '' ?>><?= esc($lbl) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3" id="source_account_wrap">
          <label class="form-label mb-1 small fw-semibold">Source Account <span class="text-danger">*</span></label>
          <select id="source_account_id" class="form-select form-select-sm">
            <option value="">-- Select Source Account --</option>
            <?php foreach ($sourceAccounts as $a): ?>
              <option value="<?= (int) $a['id'] ?>" <?= isset($payment) && (int) ($payment['source_account_id'] ?? 0) === (int) $a['id'] ? 'selected' : '' ?>>
                <?= esc($a['name']) ?><?= !empty($a['account_number']) ? ' ('.esc($a['account_number']).')' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>



        <div class="col-12" id="cheque_bridge_wrap" style="display:none;">
          <div class="cheque-bridge mb-2">
            <span><strong>Cheque Mode:</strong> Fill cheque details below. The live preview updates as you type.</span>
            <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2" id="btn_issue_cheque" title="Open dedicated cheque page instead"><i class="bi bi-box-arrow-up-right"></i> Full Cheque Page</button>
          </div>
          <!-- Cheque extra fields Row 1 -->
          <div class="row g-2 mb-2">
            <div class="col-md-2">
              <label class="form-label mb-1 small fw-semibold">Bank Account <span class="text-danger">*</span></label>
              <select id="cheque_bank_account_id" class="form-select form-select-sm">
                <option value="">Select Bank</option>
                <?php foreach ($sourceAccounts as $a): ?>
                  <option value="<?= (int)$a['id'] ?>"><?= esc($a['name']) ?><?= !empty($a['account_number']) ? ' ••••'.esc(substr($a['account_number'],-4)) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1 small fw-semibold">Cheque Amount <span class="text-danger">*</span></label>
              <input type="number" id="cheque_amount" class="form-control form-control-sm" placeholder="0.00" step="0.01" min="0" style="font-weight:600;color:#0d6efd">
            </div>
            <div class="col-md-1">
              <label class="form-label mb-1 small fw-semibold">Cheque #</label>
              <input type="text" id="cheque_number" class="form-control form-control-sm" placeholder="Auto">
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1 small fw-semibold">Cheque Type</label>
              <select id="cheque_delivery_type" class="form-select form-select-sm">
                <option value="ac_payee">A/C Payee (Cross)</option>
                <option value="self">Self</option>
                <option value="cash">Cash</option>
              </select>
            </div>
            <div class="col-md-2">
              <label class="form-label mb-1 small fw-semibold">Cheque Notes <span class="text-danger">*</span></label>
              <input type="text" id="cheque_notes" class="form-control form-control-sm" placeholder="Why is this cheque being issued?">
            </div>
            <div class="col-md-1 d-flex align-items-end">
              <div class="form-check mb-1">
                <input type="checkbox" id="cheque_advance_mode" class="form-check-input" style="cursor:pointer">
                <label class="form-check-label small fw-semibold" for="cheque_advance_mode" style="color:#dc3545;cursor:pointer;white-space:nowrap">Advance</label>
              </div>
            </div>
          </div>
          <!-- Cheque Payee Row 2 -->
          <div class="row g-2 mb-2" id="cheque_payee_row">
            <div class="col-md-3">
              <label class="form-label mb-1 small fw-semibold">Pay To (Name on Cheque) <span class="text-danger">*</span></label>
              <input type="text" id="cheque_payee_name" class="form-control form-control-sm" placeholder="Defaults to vendor name" style="font-weight:600">
              <div class="form-text" style="font-size:.68rem;color:var(--gray-400,#94a3b8);margin-top:2px">Edit to write cheque in a different name</div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1 small fw-semibold">Or Pick Contact</label>
              <select id="cheque_contact_id" class="form-select form-select-sm">
                <option value="">-- Use custom name above --</option>
              </select>
            </div>
            <div class="col-md-3" id="cheque_quick_add_wrap">
              <label class="form-label mb-1 small fw-semibold">Quick Add Contact</label>
              <div class="input-group input-group-sm">
                <input type="text" id="cheque_new_contact_name" class="form-control" placeholder="New contact name">
                <input type="text" id="cheque_new_contact_phone" class="form-control" placeholder="Phone" style="max-width:100px">
                <button type="button" id="btn_save_contact" class="btn btn-outline-success" title="Save as vendor contact"><i class="bi bi-person-plus"></i></button>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label mb-1 small fw-semibold">Payment Record</label>
              <div style="font-size:.75rem;color:var(--gray-500,#64748b);padding:.35rem .5rem;border:1px dashed var(--gray-200,#e2e8f0);border-radius:.35rem;background:var(--gray-50,#f8fafc);min-height:32px;">
                <span id="cheque_payee_audit">Vendor: <strong id="cheque_vendor_label">-</strong><br>Cheque issued to: <strong id="cheque_issued_to_label">-</strong></span>
              </div>
            </div>
          </div>
          <!-- Advance cheque info banner -->
          <div id="cheque_advance_banner" class="mb-2" style="display:none;padding:.45rem .65rem;border-radius:.35rem;background:rgba(220,53,69,.06);border:1px dashed rgba(220,53,69,.3);font-size:.78rem;color:#dc3545">
            <i class="bi bi-info-circle"></i> <strong>Advance Cheque:</strong> This cheque is an advance payment — not tied to any bill. You can adjust it against future vendor bills.
          </div>
          <!-- Live Cheque Template Preview -->
          <div id="chequePreviewCard" style="border:1px solid var(--gray-200,#e2e8f0); border-radius:.5rem; padding:.75rem; background:var(--gray-50,#f8fafc);">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
              <span style="font-size:.75rem;font-weight:600;color:var(--gray-500,#64748b);text-transform:uppercase;letter-spacing:.5px"><i class="bi bi-credit-card-2-front"></i> Live Cheque Preview</span>
              <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1" id="togglePayChequePreview" style="font-size:.7rem"><i class="bi bi-eye-slash"></i> Hide</button>
            </div>
            <div id="payChequeTplBody">
              <div id="payChequeTpl" style="border:2px solid #1a5276; padding:22px 24px; background:linear-gradient(135deg,#eaf2fb 0%,#d6e8f8 50%,#c8dff2 100%); font-family:'Courier New',monospace; max-width:720px; margin:0 auto; position:relative; border-radius:6px; box-shadow:0 2px 12px rgba(26,82,118,.12);">
                <!-- Decorative top security pattern -->
                <div style="position:absolute;top:0;left:0;right:0;height:4px;background:repeating-linear-gradient(90deg,#1a5276 0,#1a5276 8px,transparent 8px,transparent 12px);border-radius:6px 6px 0 0;"></div>
                <!-- Bank Header -->
                <div style="display:flex; justify-content:space-between; border-bottom:2px solid #1a5276; padding-bottom:10px; margin-bottom:12px; margin-top:4px;">
                  <div>
                    <div style="font-size:17px; font-weight:bold; text-transform:uppercase; color:#1a1a1a; letter-spacing:.5px;" id="vp_tpl_bank">Select Bank Account</div>
                    <div style="font-size:11px; color:#333;" id="vp_tpl_accno">Account #: ----</div>
                  </div>
                  <div style="text-align:right;">
                    <div style="font-size:12px; font-weight:bold; color:#1a1a1a;" id="vp_tpl_chqno">Cheque #: Auto</div>
                    <div style="font-size:11px; color:#333;" id="vp_tpl_date"><?= date('d-m-Y') ?></div>
                    <span id="vp_tpl_type_badge" style="display:inline-block; border:1.5px solid #1a5276; color:#1a5276; padding:1px 10px; font-size:9px; font-weight:bold; margin-top:3px; border-radius:2px; background:rgba(255,255,255,.5);">A/C PAYEE</span>
                  </div>
                </div>
                <!-- Pay To -->
                <div style="margin-bottom:12px;">
                  <span style="font-weight:bold; font-size:11px; color:#333;">PAY TO THE ORDER OF:</span>
                  <span style="border-bottom:2px dotted #1a5276; display:inline-block; width:calc(100% - 160px); padding:2px 6px; font-size:15px; font-weight:bold; min-height:24px; color:#1a1a1a;" id="vp_tpl_payee">_________________________</span>
                </div>
                <!-- Amount Box & Words -->
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
                  <div style="flex:1; margin-right:14px;">
                    <span style="font-weight:bold; font-size:11px; color:#333;">RUPEES IN WORDS:</span>
                    <div style="border-bottom:2px dotted #1a5276; font-style:italic; font-size:11px; min-height:20px; padding:3px 6px; text-transform:uppercase; color:#1a1a1a;" id="vp_tpl_words">Zero Rupees Only</div>
                  </div>
                  <div style="border:3px solid #1a5276; padding:8px 16px; background:rgba(255,255,255,.7); text-align:center; min-width:150px; border-radius:4px;">
                    <div style="font-size:8px; font-weight:bold; color:#1a5276; letter-spacing:1px;">PKR</div>
                    <div style="font-size:20px; font-weight:bold; color:#1a1a1a;" id="vp_tpl_amount">0.00</div>
                  </div>
                </div>
                <!-- Bottom: QR + Payment Type + Signature -->
                <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:8px; padding-top:8px; border-top:1px dashed #1a5276;">
                  <!-- QR Code -->
                  <div style="text-align:center;">
                    <canvas id="vp_tpl_qr" width="70" height="70" style="border:1px solid #ccc;border-radius:3px;background:#fff;"></canvas>
                    <div style="font-size:7px;color:#555;margin-top:2px;">Scan to verify</div>
                  </div>
                  <!-- Memo -->
                  <div style="flex:1;margin:0 14px;">
                    <div style="font-size:9px; color:#555;">
                      <span style="font-weight:bold; color:#333;">Memo:</span> <span id="vp_tpl_notes">-</span>
                    </div>
                    <div style="margin-top:6px;">
                      <span id="vp_tpl_ptype" style="display:inline-block; border:2px solid #28a745; color:#28a745; padding:2px 10px; font-size:9px; font-weight:bold; border-radius:3px; background:rgba(255,255,255,.5);">SETTLEMENT</span>
                    </div>
                  </div>
                  <!-- Signature line -->
                  <div style="text-align:center; min-width:140px;">
                    <div style="border-bottom:2px solid #1a5276; margin-bottom:4px; min-height:30px;"></div>
                    <div style="font-size:9px; font-weight:bold; color:#333; letter-spacing:.5px;">AUTHORISED SIGNATORY</div>
                  </div>
                </div>
                <!-- Decorative bottom security pattern -->
                <div style="position:absolute;bottom:0;left:0;right:0;height:4px;background:repeating-linear-gradient(90deg,#1a5276 0,#1a5276 8px,transparent 8px,transparent 12px);border-radius:0 0 6px 6px;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bills Card -->
  <div class="vp-card mb-3">
    <div class="vp-hdr">
      <span id="bills_hdr_txt">Outstanding Bills</span>
      <div class="d-flex align-items-center gap-3">
        <label class="toggle-adv mb-0 d-flex align-items-center gap-2" style="font-size:.82rem;font-weight:500;color:#16a34a" title="Give advance payment without any bill">
          <input type="checkbox" id="advance_only_mode" class="form-check-input mb-0" style="cursor:pointer">
          <span>Advance Only (No Bills)</span>
        </label>
        <small style="color:var(--gray-400,#94a3b8)" id="bills_status">Select a vendor</small>
      </div>
    </div>
    <!-- Advance info strip (hidden until vendor loaded) -->
    <div class="adv-strip" id="adv_strip" style="display:none">
      <span>Vendor Advance Balance:</span>
      <span class="adv-amt" id="adv_display">0.00</span>
      <span style="color:var(--gray-400,#94a3b8)">|</span>
      <span>Used: <strong id="adv_used" style="color:#f59e0b">0.00</strong></span>
      <span>Remaining: <strong id="adv_remain" style="color:#16a34a">0.00</strong></span>
      <button type="button" id="btn_auto" class="btn btn-sm btn-outline-success py-0 px-2 ms-auto" style="display:none; font-size:.72rem;">Auto Apply Advance</button>
    </div>
    <!-- Advance-only mode section -->
    <div id="advance_only_section" style="display:none">
      <div class="adv-only-box">
        <i class="bi bi-wallet2" style="font-size:1.1rem;color:#16a34a"></i>
        <div style="flex:1;min-width:0">
          <div style="font-size:.78rem;font-weight:600;color:#16a34a">Advance Payment</div>
          <div style="font-size:.68rem;color:var(--gray-400,#94a3b8)">Not tied to any bill — available for future clearing</div>
        </div>
        <input type="number" id="advance_only_amount" class="amt-in" placeholder="0.00" step="0.01" min="0">
      </div>
    </div>
    <div class="bs">
      <table class="table table-sm bt">
        <thead>
          <tr>
            <th style="width:30px"><input type="checkbox" id="select_all" class="form-check-input"></th>
            <th>Bill #</th>
            <th>Date</th>
            <th>Products</th>
            <th class="text-end">Total</th>
            <th class="text-end">Paid</th>
            <th class="text-end">Balance</th>
            <th id="col_adv_header" class="text-end th-adv" style="width:115px" title="Pay using vendor's advance balance">From Advance</th>
            <th class="text-end th-cash" style="width:115px" title="Pay via cash or bank transfer">Cash / Bank</th>
            <th class="text-end" style="width:90px">Row Total</th>
          </tr>
        </thead>
        <tbody id="bills_body">
          <tr><td colspan="10"><div class="emp">Select a vendor to load outstanding bills</div></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Bottom: Details + Summary + Actions -->
  <div class="row g-3 mb-4">
    <div class="col-lg-5">
      <div class="vp-card h-100">
        <div class="card-body px-3 py-2">
          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label mb-1 small">Reference</label>
              <input type="text" id="memo" class="form-control form-control-sm" placeholder="e.g. INV-001" value="<?= isset($payment) ? esc($payment['memo'] ?? '') : '' ?>">
            </div>
            <div class="col-6">
              <label class="form-label mb-1 small">Notes</label>
              <input type="text" id="notes" class="form-control form-control-sm" placeholder="Internal notes" value="<?= isset($payment) ? esc($payment['notes'] ?? '') : '' ?>">
            </div>
          </div>
          <div id="attach_section">
            <div class="d-flex align-items-center justify-content-between mb-1">
              <label class="form-label mb-0 small">Attachments</label>
              <span class="small" id="attach_counter" style="color:#9ca3af;">0 / 5</span>
            </div>
            <div id="existing_attachments"></div>
            <div id="new_attach_wrap">
              <input type="file" id="attachments" class="form-control form-control-sm" multiple accept="application/pdf,image/png,image/jpeg">
              <div class="small mt-1" id="attach_hint" style="color:#9ca3af;">PDF, JPG, PNG — max 5 total</div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="vp-card h-100">
        <div class="card-body px-3 py-2">
          <div class="sr"><span>Bills Selected</span><span class="v" id="s_cnt">0</span></div>
          <div class="sr"><span>Outstanding Total</span><span class="v" id="s_out">0.00</span></div>
          <div class="sr"><span style="color:#16a34a">From Advance</span><span class="v" id="s_adv" style="color:#16a34a">0.00</span></div>
          <div class="sr"><span style="color:#2563eb">Cash / Bank</span><span class="v" id="s_cash" style="color:#2563eb">0.00</span></div>
          <div class="sr sr-t"><span class="fw-bold">Payment Total</span><span class="v" id="s_total">0.00</span></div>
        </div>
      </div>
    </div>
    <div class="col-lg-3 d-flex flex-column justify-content-center gap-2">
      <button type="button" class="btn btn-primary" id="btn_save"><?php if (isset($payment) && !empty($payment['id'])): ?>Update Draft<?php else: ?>Save Draft<?php endif; ?></button>
      <button type="button" class="btn btn-success" id="btn_confirm" <?= (isset($payment) && !empty($payment['id'])) ? '' : 'disabled' ?>>Confirm &amp; Post</button>
      <div id="feedback" class="small text-center mt-1"></div>
    </div>
  </div>

</div>

<!-- Bill Products Modal -->
<div class="modal fade" id="billLinesModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content vp-bill-modal-content">
      <div class="modal-header vp-bill-modal-header" style="padding:.6rem 1rem">
        <h6 class="modal-title mb-0 fw-semibold" id="billLinesModalTitle">Bill Products</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-0">
        <table class="table table-sm mb-0 vp-bill-modal-table" style="font-size:.82rem">
          <thead style="position:sticky;top:0;z-index:2">
            <tr>
              <th style="padding:.45rem .5rem;font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;width:32px">#</th>
              <th style="padding:.45rem .5rem;width:62px"></th>
              <th style="padding:.45rem .5rem;font-size:.68rem;text-transform:uppercase;letter-spacing:.4px">Product / Variant</th>
              <th class="text-center" style="padding:.45rem .5rem;font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;width:60px">Qty</th>
              <th class="text-end" style="padding:.45rem .5rem;font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;width:100px">Unit Price</th>
              <th class="text-end" style="padding:.45rem .5rem;font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;width:100px">Line Total</th>
            </tr>
          </thead>
          <tbody id="billLinesModalBody" style="max-height:420px;overflow-y:auto"></tbody>
          <tfoot id="billLinesModalFoot" style="position:sticky;bottom:0"></tfoot>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
.vp-inline-thumb{width:32px;height:32px;object-fit:cover;border-radius:5px;border:1px solid rgba(148,163,184,.45);background:#0f172a;flex:0 0 32px;}
.vp-prod-wrap{display:flex;align-items:flex-start;gap:.45rem;min-width:220px;}
.vp-prod-meta{min-width:0;}
.blm-img{width:52px;height:52px;object-fit:cover;border-radius:6px;border:1px solid var(--gray-700,#374151);background:var(--gray-800,#1f2937);}
.blm-attr-badge{display:inline-block;font-size:.65rem;padding:1px 6px;border-radius:3px;background:rgba(56,189,248,.14);color:#bae6fd;border:1px solid rgba(56,189,248,.35);margin:1px 2px 1px 0;}
.vp-bill-modal-content{background:linear-gradient(180deg,#15223a 0%, #101a2e 100%);color:#e5edf8;border:1px solid rgba(148,163,184,.4);box-shadow:0 20px 60px rgba(0,0,0,.45);}
.vp-bill-modal-header{border-bottom:1px solid rgba(148,163,184,.3);background:rgba(226,232,240,.92);}
.vp-bill-modal-table{
  --bs-table-color:#dbe7f7;
  --bs-table-bg:#162338;
  --bs-table-border-color:rgba(148,163,184,.28);
  --bs-table-striped-color:#e5edf8;
  --bs-table-striped-bg:#101a2e;
  --bs-table-active-color:#ffffff;
  --bs-table-active-bg:#223b65;
  --bs-table-hover-color:#ffffff;
  --bs-table-hover-bg:#1b2f4f;
  color:#dbe7f7 !important;
  background:#162338 !important;
}
.vp-bill-modal-table thead th{color:#9fb2cc !important;border-bottom:1px solid rgba(148,163,184,.35);background:#0f1f39 !important;}
.vp-bill-modal-table tbody tr{border-bottom:1px solid rgba(148,163,184,.22);background:#162338 !important;}
.vp-bill-modal-table tbody tr:nth-child(even){background:#101a2e !important;}
.vp-bill-modal-table tbody td{background:transparent !important;color:#dbe7f7 !important;}
.vp-bill-modal-table tfoot,
.vp-bill-modal-table tfoot td{background:#0f1f39 !important;border-top:1px solid rgba(148,163,184,.35);color:#dbe7f7 !important;}
.vp-bill-modal-table a{color:#e5edf8;}
.vp-bill-modal-content .modal-title,
.vp-bill-modal-content td,
.vp-bill-modal-content th,
.vp-bill-modal-content .fw-semibold,
.vp-bill-modal-content .fw-bold,
.vp-bill-modal-content small,
.vp-bill-modal-content span,
.vp-bill-modal-content div { color:#dbe7f7 !important; }
.vp-bill-modal-content .modal-header .modal-title{color:#0f172a !important;}
.vp-bill-modal-content .btn-close{filter:none;opacity:.75;background-color:transparent;}
.vp-bill-modal-content .btn-close:hover{opacity:1;}
</style>

<script>
(() => {
  const URLS = {
    bills:   '<?= base_url("accounting/cheques/vendor-bills") ?>',
    balance: '<?= base_url("accounting/cheques/balanceData") ?>',
    chequeCreate: '<?= base_url("accounting/cheques/create") ?>',
    contacts:'<?= base_url("accounting/cheques/vendor-contacts") ?>',
    addContact:'<?= base_url("vendors") ?>',
    draft:   '<?= base_url("accounting/vendor-payments/draft") ?>',
    update:  '<?= base_url("accounting/vendor-payments/update") ?>',
    confirm: '<?= base_url("accounting/vendor-payments/confirm") ?>',
    view:    '<?= base_url("accounting/vendor-payments") ?>',
    deleteAttachment: '<?= base_url("accounting/vendor-payments/attachment/delete") ?>',
  };
  const PRE_BILL_ID = <?= !empty($preBillId) ? (int)$preBillId : 'null' ?>;
  const EDIT_PAYMENT_ID = <?= (isset($payment) && !empty($payment['id'])) ? (int)$payment['id'] : 'null' ?>;
  const EDIT_ATTACHMENTS = <?= json_encode(array_map(function($a){ return ['id'=>(int)$a['id'],'name'=>$a['original_name'],'url'=>base_url(ltrim($a['file_path']??'','/')), 'mime'=>$a['mime_type']??'']; }, $editAttachments ?? [])) ?>;
  const EDIT_ALLOCATIONS = <?= (isset($editAllocations) && !empty($editAllocations)) ? json_encode(array_map(function($a){ return ['vendor_bill_id' => (int)$a['vendor_bill_id'], 'amount' => (float)$a['amount'], 'cash_amount' => (float)($a['cash_amount'] ?? 0), 'advance_amount' => (float)($a['advance_amount'] ?? 0)]; }, $editAllocations)) : '[]' ?>;
  const CSRF = { name: '<?= csrf_token() ?>', value: '<?= csrf_hash() ?>' };
  const q=s=>document.querySelector(s), qa=s=>document.querySelectorAll(s);

  const el = {
    vendor:q('#vendor_id'), date:q('#payment_date'), method:q('#payment_method'),
    type:q('#payment_type'), source:q('#source_account_id'),
    memo:q('#memo'), notes:q('#notes'), attach:q('#attachments'),
    body:q('#bills_body'), selAll:q('#select_all'),
    btnAuto:q('#btn_auto'), btnSave:q('#btn_save'), btnConfirm:q('#btn_confirm'),
    fb:q('#feedback'), status:q('#bills_status'),
    advStrip:q('#adv_strip'), advDisp:q('#adv_display'), advUsedEl:q('#adv_used'), advRemEl:q('#adv_remain'),
    sCnt:q('#s_cnt'), sOut:q('#s_out'), sAdv:q('#s_adv'), sCash:q('#s_cash'), sTotal:q('#s_total'),
    advOnlyMode:q('#advance_only_mode'), advOnlySection:q('#advance_only_section'),
    advOnlyAmt:q('#advance_only_amount'), billsTable:q('.bs'), hdrTxt:q('#bills_hdr_txt'),
    chequeBridgeWrap:q('#cheque_bridge_wrap'), btnIssueCheque:q('#btn_issue_cheque'),
    chqBank:q('#cheque_bank_account_id'), chqNum:q('#cheque_number'),
    chqType:q('#cheque_delivery_type'), chqNotes:q('#cheque_notes'),
    chqAmt:q('#cheque_amount'), chqAdvance:q('#cheque_advance_mode'),
    chqPayee:q('#cheque_payee_name'), chqContact:q('#cheque_contact_id'),
    chqNewName:q('#cheque_new_contact_name'), chqNewPhone:q('#cheque_new_contact_phone'),
    btnSaveContact:q('#btn_save_contact'),
    chqVendorLabel:q('#cheque_vendor_label'), chqIssuedTo:q('#cheque_issued_to_label'),
    chqPreviewBody:q('#payChequeTplBody'), chqToggle:q('#togglePayChequePreview'),
  };

  let bills=[], advance=0, draftId=EDIT_PAYMENT_ID;

  const fmt=v=>new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v||0);
  const num=v=>{const n=parseFloat(String(v||'').replace(/,/g,''));return Number.isFinite(n)?n:0;};
  const sourceVal=()=>el.source?String(el.source.value||'').trim():'';
  const esc=s=>String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
  const fmtD=d=>{if(!d)return'-';const p=d.split('-');return p.length===3?p[2]+'/'+p[1]+'/'+p[0]:d;};
  const ph=h=>{el.body.innerHTML='<tr><td colspan="10"><div class="emp">'+h+'</div></td></tr>';};
  const getVid=()=>{const r=el.vendor.value;if(r===''||r==null)return null;const v=parseInt(r,10);return Number.isFinite(v)&&v>=0?v:null;};

  const msg=(t,txt)=>{
    if(!txt){el.fb.innerHTML='';return;}
    const c={success:'#22c55e',warning:'#f59e0b',danger:'#ef4444'};
    el.fb.innerHTML='<span style="color:'+(c[t]||'inherit')+'">'+txt+'</span>';
  };
  const spin=(btn,on)=>{if(!btn._t)btn._t=btn.innerHTML;btn.disabled=on;btn.innerHTML=on?'<span class="spinner-border spinner-border-sm me-1"></span>Wait...':btn._t;};

  /* Append cheque-specific fields to FormData when in cheque mode */
  const appendChequeFields=(fd)=>{
    const isCheque=(String(el.method.value||'').toLowerCase().indexOf('cheque')!==-1 || String(el.method.value||'').toLowerCase().indexOf('check')!==-1);
    if(!isCheque) return;
    fd.append('cheque_payee_name',(el.chqPayee?el.chqPayee.value:'').trim());
    fd.append('cheque_notes',(el.chqNotes?el.chqNotes.value:'').trim());
    fd.append('cheque_number',(el.chqNum?el.chqNum.value:'').trim());
    fd.append('cheque_delivery_type',(el.chqType?el.chqType.value:'').trim());
  };

  /* Sum all advance inputs (skip one if comparing) */
  const totalAdv=(skip)=>{let t=0;qa('.in-adv').forEach(i=>{if(i!==skip)t+=num(i.value);});return t;};
  /* Sum all cash inputs */
  const totalCash=()=>{let t=0;qa('.in-cash').forEach(i=>{t+=num(i.value);});return t;};

  /* REFRESH all summary numbers */
  const refresh=()=>{
    const advOnlyMode=el.advOnlyMode.checked;
    const isChequeMethod=(String(el.method.value||'').toLowerCase().indexOf('cheque')!==-1 || String(el.method.value||'').toLowerCase().indexOf('check')!==-1);
    const isChequeAdvance=isChequeMethod && el.chqAdvance && el.chqAdvance.checked;
    if(el.chequeBridgeWrap) el.chequeBridgeWrap.style.display=isChequeMethod?'':'none';
    /* Hide Source Account when in cheque mode — Bank Account in cheque section replaces it */
    const srcWrap=q('#source_account_wrap');
    if(srcWrap) srcWrap.style.display=isChequeMethod?'none':'';
    
    if(advOnlyMode || isChequeAdvance){
      /* Advance mode: either via checkbox toggle OR cheque advance checkbox */
      const advAmt=isChequeAdvance ? num(el.chqAmt?el.chqAmt.value:0) : num(el.advOnlyAmt.value);
      el.sCnt.textContent='—';
      el.sOut.textContent='—';
      el.sAdv.textContent='—';
      el.sCash.textContent='—';
      el.sTotal.textContent=fmt(advAmt);
      msg('','');
      return;
    }
    
    /* Normal mode */
    const usedAdv=totalAdv();
    const usedCash=totalCash();
    const rem=Math.max(0,advance-usedAdv);

    // Advance strip
    el.advDisp.textContent=fmt(advance);
    el.advDisp.className='adv-amt '+(advance>0?'adv-pos':'adv-zero');
    el.advUsedEl.textContent=fmt(usedAdv);
    el.advRemEl.textContent=fmt(rem);
    el.btnAuto.style.display=advance>0?'':'none';

    // Toggle visibility of advance columns based on whether advance available
    const hasAdvance=advance>0;
    const advHeader=document.getElementById('col_adv_header');
    const advDataCols=document.querySelectorAll('.col-adv');
    
    if(advHeader) {
      if(hasAdvance) {
        advHeader.classList.remove('hidden');
        advHeader.style.display='';
      } else {
        advHeader.classList.add('hidden');
        advHeader.style.display='none !important';
      }
    }
    advDataCols.forEach(col=>{
      if(hasAdvance) {
        col.classList.remove('hidden');
        col.style.display='';
      } else {
        col.classList.add('hidden');
        col.style.display='none !important';
      }
    });
    
    // Also hide in summary when no advance
    const advSummaryRow=el.sAdv.parentElement;
    if(advSummaryRow) {
      if(hasAdvance) {
        advSummaryRow.style.display='';
      } else {
        advSummaryRow.style.display='none !important';
      }
    }

    // Summary
    let cnt=0,selOut=0,selAdv=0,selCash=0;
    qa('.bill-row').forEach(row=>{
      const cb=row.querySelector('.bill-cb');
      const checked=cb&&cb.checked;
      row.classList.toggle('row-sel',!!checked);
      if(checked){
        cnt++;
        selOut+=num(row.dataset.balance);
        const ai=row.querySelector('.in-adv'), ci=row.querySelector('.in-cash');
        selAdv+=num(ai?ai.value:0);
        selCash+=num(ci?ci.value:0);
      }
    });
    el.sCnt.textContent=cnt;
    el.sOut.textContent=fmt(selOut);
    el.sAdv.textContent=fmt(selAdv);
    el.sAdv.parentElement.style.display=hasAdvance?'':'none';
    el.sCash.textContent=fmt(selCash);
    el.sTotal.textContent=fmt(selAdv+selCash);

    // Update row totals
    qa('.bill-row').forEach(row=>{
      const ai=row.querySelector('.in-adv'), ci=row.querySelector('.in-cash'), rt=row.querySelector('.row-total');
      if(rt) rt.textContent=fmt(num(ai?ai.value:0)+num(ci?ci.value:0));
    });

    // Warnings
    if(usedAdv>advance&&advance>0) msg('warning','Advance applied ('+fmt(usedAdv)+') exceeds available balance ('+fmt(advance)+')');
    else msg('','');

    // Update cheque template if in cheque mode
    updatePayChequeTemplate();
  };

  /* RENDER bills table */
  const render=()=>{
    if(!bills.length){ph('No outstanding bills for this vendor');el.status.textContent='No bills';return;}
    el.status.textContent=bills.length+' bill'+(bills.length!==1?'s':'');
    let html='';
    for(let i=0;i<bills.length;i++){
      const b=bills[i];
      const bal=Math.max(0,num(b.balance)),paid=Math.max(0,num(b.paid)),total=Math.max(0,num(b.total));
      const fp=bal<=0;
      const lines=Array.isArray(b.lines)?b.lines:[];
      const firstLine=lines[0]||null;
      let productCell='';
      if(firstLine){
        const dispName=firstLine.is_variant&&firstLine.variant_name?firstLine.variant_name:firstLine.product_name;
        const dispCode=firstLine.is_variant&&firstLine.variant_code?firstLine.variant_code:firstLine.product_code;
        const pname=esc(dispName||'\u2014');
        const pcode=dispCode?'<span style="color:var(--gray-400,#94a3b8);font-size:.7rem"> ('+esc(dispCode)+')</span>':'';
        const thumb=esc(firstLine.thumbnail_url||noImgSrc);
        productCell='<div class="vp-prod-wrap">'
          +'<img src="'+thumb+'" class="vp-inline-thumb" onerror="this.onerror=null;this.src=\''+noImgSrc+'\'" alt="Product">'
          +'<div class="vp-prod-meta"><span style="font-size:.8rem">'+pname+pcode+'</span>';
        if(lines.length>1){
          productCell+=' <a href="#" class="btn-view-lines" style="font-size:.7rem;color:#2563eb;text-decoration:none;white-space:nowrap;margin-left:4px" data-bill-id="'+b.id+'" data-bill-num="'+esc(b.bill_number||'VB-'+b.id)+'" data-lines="'+esc(JSON.stringify(lines))+'">View all '+lines.length+' <i class="bi bi-grid"></i></a>';
        } else {
          productCell+=' <a href="#" class="btn-view-lines" style="font-size:.7rem;color:var(--gray-500,#64748b);text-decoration:none;white-space:nowrap;margin-left:4px" data-bill-id="'+b.id+'" data-bill-num="'+esc(b.bill_number||'VB-'+b.id)+'" data-lines="'+esc(JSON.stringify(lines))+'"><i class="bi bi-info-circle"></i></a>';
        }
        productCell+='</div></div>';
      } else {
        productCell='<span style="color:var(--gray-400,#94a3b8);font-size:.8rem">\u2014</span>';
      }
      html+='<tr class="bill-row'+(fp?' bill-fp':'')+'" data-id="'+b.id+'" data-balance="'+bal+'">';
      html+='<td>'+(fp?'':'<input type="checkbox" class="form-check-input bill-cb">')+'</td>';
      html+='<td class="fw-semibold">'+esc(b.bill_number||'VB-'+b.id)+'</td>';
      html+='<td>'+fmtD(b.bill_date)+'</td>';
      html+='<td>'+productCell+'</td>';
      html+='<td class="text-end">'+fmt(total)+'</td>';
      html+='<td class="text-end">'+fmt(paid)+'</td>';
      html+='<td class="text-end fw-semibold" style="color:'+(bal>0?'#ef4444':'#22c55e')+'">'+fmt(bal)+'</td>';
      if(bal>0){
        html+='<td class="text-end col-adv"><input type="number" step="0.01" min="0" max="'+bal+'" class="pay-in in-adv" value="0.00" title="Pay from vendor advance balance"></td>';
        html+='<td class="text-end"><input type="number" step="0.01" min="0" max="'+bal+'" class="pay-in in-cash" value="0.00" title="Pay via cash or bank transfer"></td>';
        html+='<td class="text-end fw-semibold row-total" style="color:var(--gray-600,#475569)">0.00</td>';
      } else {
        html+='<td class="text-end col-adv">-</td><td class="text-end">-</td><td class="text-end">-</td>';
      }
      html+='</tr>';
    }
    el.body.innerHTML=html;
    
    // Hide advance columns immediately if no advance available
    if(advance<=0){
      const hdr=document.getElementById('col_adv_header');
      if(hdr) {
        hdr.classList.add('hidden');
        hdr.style.display='none !important';
      }
      document.querySelectorAll('.col-adv').forEach(col=>{
        col.classList.add('hidden');
        col.style.display='none !important';
      });
    } else {
      // Show if advance is available
      const hdr=document.getElementById('col_adv_header');
      if(hdr) {
        hdr.classList.remove('hidden');
        hdr.style.display='';
      }
      document.querySelectorAll('.col-adv').forEach(col=>{
        col.classList.remove('hidden');
        col.style.display='';
      });
    }
    
    bindRows();
    refresh();
  };

  /* BILL LINES MODAL */
  const noImgSrc='<?= base_url("assets/images/no-image.png") ?>';
  const openBillLinesModal=(billNum, lines)=>{
    const fmt2=v=>new Intl.NumberFormat('en-US',{minimumFractionDigits:2,maximumFractionDigits:2}).format(v||0);
    const esc2=s=>String(s||'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
    document.getElementById('billLinesModalTitle').textContent=billNum+' — Products ('+lines.length+')';
    let tbody='', grandTotal=0;
    lines.forEach((ln,i)=>{
      grandTotal+=parseFloat(ln.line_total||0);
      const rowBg = (i % 2 === 0) ? '#162338' : '#101a2e';
      const isVar=ln.is_variant;
      // Image cell
      const imgSrc=ln.thumbnail_url||noImgSrc;
      const imgCell='<td style="padding:.35rem .5rem;width:62px;vertical-align:middle;background:'+rowBg+' !important">'
        +'<img src="'+esc2(imgSrc)+'" class="blm-img" onerror="this.onerror=null;this.src=\''+noImgSrc+'\'">'
        +'</td>';
      // Product/variant name cell
      let nameHtml='';
      if(isVar){
        // Parent product name + code
        nameHtml+='<div style="font-size:.72rem;color:#9fb2cc;margin-bottom:1px">'
          +esc2(ln.product_name||'')
          +(ln.product_code?'<span style="margin-left:5px;color:#7f95b3">'+esc2(ln.product_code)+'</span>':'')
          +'</div>';
        // Variant name bold
        nameHtml+='<div style="font-weight:700;color:#e5edf8">'
          +esc2(ln.variant_name||ln.product_name||'—')+'</div>';
        // Variant code
        if(ln.variant_code) nameHtml+='<div style="font-size:.7rem;color:#9fb2cc">'+esc2(ln.variant_code)+'</div>';
        // Variant attributes as badges
        const attrs=ln.variant_attrs||{};
        const attrKeys=Object.keys(attrs);
        if(attrKeys.length){
          nameHtml+='<div style="margin-top:3px">';
          attrKeys.forEach(k=>{nameHtml+='<span class="blm-attr-badge">'+esc2(k)+': '+esc2(attrs[k])+'</span>';});
          nameHtml+='</div>';
        }
      } else {
        nameHtml+='<div style="font-weight:700;color:#e5edf8">'+esc2(ln.product_name||'—')+'</div>';
        if(ln.product_code) nameHtml+='<div style="font-size:.7rem;color:#9fb2cc">'+esc2(ln.product_code)+'</div>';
      }
      tbody+='<tr style="border-bottom:1px solid #e2e8f0;background:'+rowBg+' !important">'
        +'<td style="padding:.4rem .75rem;color:#9fb2cc;vertical-align:middle;font-size:.78rem;background:'+rowBg+' !important">'+(i+1)+'</td>'
        +imgCell
        +'<td style="padding:.4rem .75rem;vertical-align:middle;background:'+rowBg+' !important;color:#e5edf8 !important">'+nameHtml+'</td>'
        +'<td class="text-center" style="padding:.4rem .75rem;color:#c8d7ec;vertical-align:middle;white-space:nowrap;background:'+rowBg+' !important">'+parseFloat(ln.qty||0).toFixed(2)+'</td>'
        +'<td class="text-end" style="padding:.4rem .75rem;color:#c8d7ec;vertical-align:middle;white-space:nowrap;background:'+rowBg+' !important">'+fmt2(ln.unit_price)+'</td>'
        +'<td class="text-end fw-semibold" style="padding:.4rem .75rem;color:#7dd3fc;vertical-align:middle;white-space:nowrap;background:'+rowBg+' !important">'+fmt2(ln.line_total)+'</td>'
        +'</tr>';
    });
    const totalQty = lines.reduce((s, ln) => s + parseFloat(ln.qty||0), 0);
    const totalItems = lines.length;
    document.getElementById('billLinesModalBody').innerHTML=tbody;
    document.getElementById('billLinesModalFoot').innerHTML='<tr>'
      +'<td colspan="3" style="padding:.5rem .75rem;background:#0f1f39 !important;color:#9fb2cc;font-size:.78rem">'
      +'<span style="color:#bae6fd;font-weight:600">'+totalItems+'</span> product'+(totalItems!==1?'s':'')
      +' &nbsp;|&nbsp; Total Qty: <span style="color:#bae6fd;font-weight:600">'+parseFloat(totalQty).toFixed(2)+'</span>'
      +'</td>'
      +'<td colspan="3" class="text-end fw-bold" style="padding:.5rem .75rem;background:#0f1f39 !important;color:#dbe7f7;font-size:.8rem">Grand Total &nbsp;<span style="color:#7dd3fc;font-size:.9rem">'+fmt2(grandTotal)+'</span></td>'
      +'</tr>';
    const modal=new bootstrap.Modal(document.getElementById('billLinesModal'));
    modal.show();
  };

  document.getElementById('bills_body').addEventListener('click', e=>{
    const link=e.target.closest('.btn-view-lines');
    if(!link) return;
    e.preventDefault();
    const billNum=link.dataset.billNum||'Bill';
    let lines=[];
    try{ lines=JSON.parse(link.dataset.lines||'[]'); }catch(err){}
    openBillLinesModal(billNum, lines);
  });


  const bindRows=()=>{
    /* Advance inputs — capped by available advance and bill balance */
    qa('.in-adv').forEach(inp=>{
      inp.addEventListener('input',()=>{
        const row=inp.closest('.bill-row'),bal=num(row.dataset.balance);
        const ci=row.querySelector('.in-cash'),cashV=num(ci?ci.value:0);
        const maxBal=Math.max(0,bal-cashV);
        const otherAdv=totalAdv(inp);
        const maxAdv=advance>0?Math.min(maxBal,Math.max(0,advance-otherAdv)):0;
        const v=num(inp.value);
        if(v>maxAdv&&inp.value.trim()!=='') inp.value=maxAdv.toFixed(2);
        /* Auto-check */
        const cb=row.querySelector('.bill-cb');
        if(num(inp.value)>0&&cb&&!cb.checked) cb.checked=true;
        refresh();
      });
      inp.addEventListener('blur',()=>{inp.value=num(inp.value)>0?num(inp.value).toFixed(2):'0.00';refresh();});
      inp.addEventListener('focus',()=>{if(num(inp.value)===0)inp.value='';});
    });

    /* Cash inputs — capped by bill balance minus advance */
    qa('.in-cash').forEach(inp=>{
      inp.addEventListener('input',()=>{
        const row=inp.closest('.bill-row'),bal=num(row.dataset.balance);
        const ai=row.querySelector('.in-adv'),advV=num(ai?ai.value:0);
        const maxCash=Math.max(0,bal-advV);
        const v=num(inp.value);
        if(v>maxCash&&inp.value.trim()!=='') inp.value=maxCash.toFixed(2);
        const cb=row.querySelector('.bill-cb');
        if(num(inp.value)>0&&cb&&!cb.checked) cb.checked=true;
        refresh();
      });
      inp.addEventListener('blur',()=>{inp.value=num(inp.value)>0?num(inp.value).toFixed(2):'0.00';refresh();});
      inp.addEventListener('focus',()=>{if(num(inp.value)===0)inp.value='';});
    });

    /* Checkboxes */
    qa('.bill-cb').forEach(cb=>{
      cb.addEventListener('change',()=>{
        if(!cb.checked){
          const r=cb.closest('.bill-row');
          const ai=r.querySelector('.in-adv'),ci=r.querySelector('.in-cash');
          if(ai)ai.value='0.00';if(ci)ci.value='0.00';
        }
        autoChequeDistribute();
        refresh();
      });
    });
    if(el.selAll&&!el.selAll._b){
      el.selAll._b=true;
      el.selAll.addEventListener('change',()=>{
        qa('.bill-cb').forEach(c=>{c.checked=el.selAll.checked;});
        if(!el.selAll.checked){qa('.in-adv').forEach(i=>{i.value='0.00';});qa('.in-cash').forEach(i=>{i.value='0.00';});}
        autoChequeDistribute();
        refresh();
      });
    }
  };

  /* AUTO-APPLY advance to checked bills */
  el.btnAuto.addEventListener('click',()=>{
    if(advance<=0)return;
    /* Clear existing advance entries first */
    qa('.in-adv').forEach(i=>{i.value='0.00';});
    let rem=advance;
    const rows=Array.from(qa('.bill-row'));
    const sel=rows.filter(r=>r.querySelector('.bill-cb')&&r.querySelector('.bill-cb').checked);
    const targets=sel.length>0?sel:rows;
    if(sel.length===0) targets.forEach(r=>{const c=r.querySelector('.bill-cb');if(c)c.checked=true;});
    targets.forEach(r=>{
      const bal=num(r.dataset.balance);
      const ci=r.querySelector('.in-cash'),ai=r.querySelector('.in-adv');
      if(!ai||bal<=0)return;
      const cashV=num(ci?ci.value:0);
      const a=Math.min(bal-cashV,rem);
      ai.value=a>0?a.toFixed(2):'0.00';
      rem-=a;
    });
    refresh();
  });

  /* ADVANCE-ONLY MODE TOGGLE */
  el.advOnlyMode.addEventListener('change',()=>{
    const advOnly=el.advOnlyMode.checked;
    el.advOnlySection.style.display=advOnly?'':'none';
    el.billsTable.style.display=advOnly?'none':'';
    el.hdrTxt.textContent=advOnly?'Advance Payment':'Outstanding Bills';
    el.advStrip.style.display=(advOnly||getVid()===null)?'none':'';
    if(advOnly){
      el.advOnlyAmt.value='';
      el.advOnlyAmt.focus();
      el.status.textContent='Enter advance amount';
    } else {
      el.status.textContent=bills.length?(bills.length+' bill'+(bills.length!==1?'s':'')):'No bills';
    }
    refresh();
  });

  /* ADVANCE-ONLY AMOUNT INPUT */
  el.advOnlyAmt.addEventListener('input',()=>{refresh();});

  /* LOAD bills */
  const loadBills=async()=>{
    const vid=getVid();
    if(vid===null){ph('Select a vendor to load outstanding bills');el.status.textContent='Select a vendor';return;}
    ph('<span class="spinner-border spinner-border-sm"></span> Loading...');
    el.status.textContent='Loading...';
    try{
      const r=await fetch(URLS.bills+'/'+vid,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      if(!r.ok){ph('Server error ('+r.status+')');el.status.textContent='Error';return;}
      const d=await r.json();
      if(d&&d.success){
        bills=d.data||[];
        render();
        // Pre-fill allocations when editing an existing draft
        if(EDIT_ALLOCATIONS && EDIT_ALLOCATIONS.length>0){
          EDIT_ALLOCATIONS.forEach(alloc=>{
            const row=el.body.querySelector('.bill-row[data-id="'+alloc.vendor_bill_id+'"]');
            if(!row)return;
            const cb=row.querySelector('.bill-cb');
            if(cb&&!cb.checked) cb.checked=true;
            const ai=row.querySelector('.in-adv'), ci=row.querySelector('.in-cash');
            if(ai) ai.value=(alloc.advance_amount||0).toFixed(2);
            if(ci) ci.value=(alloc.cash_amount||0).toFixed(2);
          });
          refresh();
        }
        // Auto-check & fill pre-selected bill from URL param (new payment only)
        if(PRE_BILL_ID && (!EDIT_ALLOCATIONS || EDIT_ALLOCATIONS.length===0)){
          const targetRow=el.body.querySelector('.bill-row[data-id="'+PRE_BILL_ID+'"]');
          if(targetRow){
            const cb=targetRow.querySelector('.bill-cb');
            if(cb&&!cb.checked){
              cb.checked=true;
              cb.dispatchEvent(new Event('change',{bubbles:true}));
              // Fill cash input with full balance
              const cashIn=targetRow.querySelector('.in-cash');
              if(cashIn){
                const bal=num(targetRow.dataset.balance);
                cashIn.value=bal.toFixed(2);
                cashIn.dispatchEvent(new Event('input',{bubbles:true}));
              }
              targetRow.scrollIntoView({behavior:'smooth',block:'center'});
            }
          }
        }
      }
      else{ph(esc((d&&d.message)||'Failed'));el.status.textContent='Error';}
    }catch(e){ph('Network error');el.status.textContent='Error';console.error(e);}
  };

  /* LOAD advance balance */
  const loadBalance=async()=>{
    const vid=getVid();
    if(vid===null){advance=0;el.advStrip.style.display='none';return;}
    try{
      const r=await fetch(URLS.balance+'?type=vendor&id='+vid,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      const d=await r.json();
      if(d&&d.success){advance=num(d.advance);el.advStrip.style.display='';}
      else{advance=0;el.advStrip.style.display='none';}
    }catch(e){advance=0;el.advStrip.style.display='none';}
    refresh();
  };

  /* GATHER allocations for API */
  const gather=()=>{
    const out=[];
    qa('.bill-row').forEach(row=>{
      const cb=row.querySelector('.bill-cb');
      if(!cb||!cb.checked)return;
      const ai=row.querySelector('.in-adv'),ci=row.querySelector('.in-cash');
      const advV=num(ai?ai.value:0),cashV=num(ci?ci.value:0);
      const amt=advV+cashV;
      if(amt>0) out.push({vendor_bill_id:parseInt(row.dataset.id,10),amount:amt,cash_amount:cashV,advance_amount:advV});
    });
    return out;
  };

  /**
   * AUTO-DISTRIBUTE cheque amount to CASH/BANK fields of checked bills.
   * Called when: cheque amount changes, bill checkbox toggled ON, select-all toggled ON.
   * Only active when cheque mode is ON and advance mode is OFF.
   */
  const autoChequeDistribute=()=>{
    const isChequeMethod=(String(el.method.value||'').toLowerCase().indexOf('cheque')!==-1 || String(el.method.value||'').toLowerCase().indexOf('check')!==-1);
    const isChequeAdvance=isChequeMethod && el.chqAdvance && el.chqAdvance.checked;
    const advOnlyMode=el.advOnlyMode.checked;
    if(!isChequeMethod||isChequeAdvance||advOnlyMode) return;

    const chequeAmt=num(el.chqAmt?el.chqAmt.value:0);
    if(chequeAmt<=0) return;

    let remaining=chequeAmt;
    const checkedRows=Array.from(qa('.bill-row')).filter(r=>{
      const cb=r.querySelector('.bill-cb');
      return cb&&cb.checked;
    });
    if(checkedRows.length===0) return;

    checkedRows.forEach(row=>{
      const bal=num(row.dataset.balance);
      const ai=row.querySelector('.in-adv');
      const ci=row.querySelector('.in-cash');
      const advV=num(ai?ai.value:0);
      const maxCash=Math.max(0,bal-advV);
      const fill=Math.min(maxCash,remaining);
      if(ci) ci.value=fill>0?fill.toFixed(2):'0.00';
      remaining-=fill;
    });
    refresh();
  };

  /* SAVE DRAFT */
  el.btnSave.addEventListener('click',async()=>{
    const vid=getVid();
    if(vid===null){msg('warning','Select a vendor.');return;}
    
    /* Cheque notes/narration is mandatory when paying by cheque */
    const isChequeMethod=(String(el.method.value||'').toLowerCase().indexOf('cheque')!==-1 || String(el.method.value||'').toLowerCase().indexOf('check')!==-1);
    if(isChequeMethod){
      const chqNotesVal=(el.chqNotes?el.chqNotes.value:'').trim();
      if(!chqNotesVal){
        msg('warning','Enter cheque notes — describe why this cheque is being issued.');
        if(el.chqNotes) el.chqNotes.focus();
        return;
      }
    }
    
    const advOnlyMode=el.advOnlyMode.checked;
    const isChequeAdvance=!advOnlyMode && el.chqAdvance && el.chqAdvance.checked && (String(el.method.value||'').toLowerCase().indexOf('cheque')!==-1);
    
    if(advOnlyMode || isChequeAdvance){
      /* Advance payment — either via advance-only mode or cheque advance */
      const amt=isChequeAdvance ? num(el.chqAmt?el.chqAmt.value:0) : num(el.advOnlyAmt.value);
      if(amt<=0){msg('warning',isChequeAdvance?'Enter cheque amount.':'Enter a valid advance amount.');return;}
      if(!sourceVal()){msg('warning','Select a source account.');return;}
      
      const fd=new FormData();
      fd.append(CSRF.name,CSRF.value);
      fd.append('vendor_id',vid);
      fd.append('payment_method',el.method.value);
      fd.append('payment_type','advance');
      fd.append('payment_date',el.date.value);
      fd.append('source_account_id',sourceVal());
      fd.append('allocations','[]'); /* No allocations — pure advance */
      fd.append('advance_amount',amt.toFixed(2));
      fd.append('amount',amt.toFixed(2));
      fd.append('memo',(el.memo.value||'').trim());
      fd.append('notes',(el.notes.value||'').trim());
      if(el.attach&&el.attach.files) Array.from(el.attach.files).forEach(f=>fd.append('attachments[]',f));
      appendChequeFields(fd);
      if(EDIT_PAYMENT_ID) fd.append('payment_id', EDIT_PAYMENT_ID);
      
      spin(el.btnSave,true);msg('','');
      const saveUrlAdv = EDIT_PAYMENT_ID ? URLS.update : URLS.draft;
      try{
        const r=await fetch(saveUrlAdv,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
        const d=await r.json();
        if(d&&d.success){
          /* Redirect to payment view */
          window.location.href=URLS.view+'/'+(d.payment_id||EDIT_PAYMENT_ID);
          return;
        }
        else msg('danger',(d&&d.message)||'Failed to save.');
      }catch(e){msg('danger','Network error.');console.error(e);}
      finally{spin(el.btnSave,false);}
      return;
    }
    
    /* Normal bill payment mode */
    const allocs=gather();
    if(allocs.length===0){msg('warning','Select bills and enter payment amounts.');return;}

    const sumAdv=allocs.reduce((s,a)=>s+a.advance_amount,0);
    const sumCash=allocs.reduce((s,a)=>s+a.cash_amount,0);
    const total=sumAdv+sumCash;

    /* Payment type is ALWAYS 'settlement' when paying bills - even if using only advance */
    const payType='settlement';

    /* Source account ONLY required when paying with cash/bank */
    if(sumCash>0&&!sourceVal()){msg('warning','Select a source account — required for cash/bank payment.');return;}

    const fd=new FormData();
    fd.append(CSRF.name,CSRF.value);
    fd.append('vendor_id',vid);
    fd.append('payment_method',el.method.value);
    fd.append('payment_type',payType);
    fd.append('payment_date',el.date.value);
    fd.append('source_account_id',sourceVal()||'0');
    fd.append('allocations',JSON.stringify(allocs));
    fd.append('advance_amount',sumAdv.toFixed(2));
    fd.append('memo',(el.memo.value||'').trim());
    fd.append('notes',(el.notes.value||'').trim());
    if(el.attach&&el.attach.files) Array.from(el.attach.files).forEach(f=>fd.append('attachments[]',f));
    appendChequeFields(fd);
    if(EDIT_PAYMENT_ID) fd.append('payment_id', EDIT_PAYMENT_ID);

    spin(el.btnSave,true);msg('','');
    const saveUrl = EDIT_PAYMENT_ID ? URLS.update : URLS.draft;
    try{
      const r=await fetch(saveUrl,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
      const d=await r.json();
      if(d&&d.success){
        /* Redirect to payment view */
        window.location.href=URLS.view+'/'+(d.payment_id||EDIT_PAYMENT_ID);
        return;
      }
      else msg('danger',(d&&d.message)||'Failed to save.');
    }catch(e){msg('danger','Network error.');console.error(e);}
    finally{spin(el.btnSave,false);}
  });

  /* CONFIRM & POST */
  el.btnConfirm.addEventListener('click',async()=>{
    if(!draftId){msg('warning','Save a draft first.');return;}
    const fd=new FormData();
    fd.append(CSRF.name,CSRF.value);
    fd.append('payment_id',draftId);
    spin(el.btnConfirm,true);msg('','');
    try{
      const r=await fetch(URLS.confirm,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
      const d=await r.json();
      if(d&&d.success){msg('success','Posted! <a href="'+URLS.view+'/'+draftId+'" style="color:#22c55e;font-weight:700">View</a>');draftId=null;el.btnConfirm.disabled=true;await loadBalance();await loadBills();}
      else msg('danger',(d&&d.message)||'Failed.');
    }catch(e){msg('danger','Network error.');console.error(e);}
    finally{spin(el.btnConfirm,false);}
  });

  /* ── NUMBER TO WORDS (supports up to billions, PKR) ── */
  const numberToWords=(n)=>{
    if(n===0)return'Zero Rupees Only';
    const ones=['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine'];
    const teens=['Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    const tens=['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    const lt1k=(x)=>{if(x===0)return'';if(x<10)return ones[x];if(x<20)return teens[x-10];if(x<100){return tens[Math.floor(x/10)]+(x%10?' '+ones[x%10]:'');}return ones[Math.floor(x/100)]+' Hundred'+(x%100?' '+lt1k(x%100):'');};
    let int=Math.floor(n), dec=Math.round((n-int)*100), r='';
    if(int>=1e9){r+=lt1k(Math.floor(int/1e9))+' Billion ';int%=1e9;}
    if(int>=1e6){r+=lt1k(Math.floor(int/1e6))+' Million ';int%=1e6;}
    if(int>=1e3){r+=lt1k(Math.floor(int/1e3))+' Thousand ';int%=1e3;}
    if(int>0)r+=lt1k(int);
    r=r.trim()+' Rupees';
    if(dec>0)r+=' and '+lt1k(dec)+' Paisa';
    return r+' Only';
  };

  /* ── MINI QR CODE GENERATOR (alphanumeric, version 1-M) ── */
  const drawQR=(canvas,text)=>{
    if(!canvas)return;
    const ctx=canvas.getContext('2d');
    const size=canvas.width;
    // Simple QR-like pattern using hash-based deterministic grid
    ctx.clearRect(0,0,size,size);
    ctx.fillStyle='#fff';ctx.fillRect(0,0,size,size);
    const grid=21,cell=Math.floor(size/grid);
    const off=Math.floor((size-grid*cell)/2);
    ctx.fillStyle='#1a1a1a';
    // Fixed finder patterns (3 corners)
    const drawFinder=(ox,oy)=>{
      for(let r=0;r<7;r++)for(let c=0;c<7;c++){
        const outer=r===0||r===6||c===0||c===6;
        const inner=r>=2&&r<=4&&c>=2&&c<=4;
        if(outer||inner) ctx.fillRect(off+(ox+c)*cell,off+(oy+r)*cell,cell,cell);
      }
    };
    drawFinder(0,0);drawFinder(grid-7,0);drawFinder(0,grid-7);
    // Timing patterns
    for(let i=8;i<grid-8;i++){if(i%2===0){ctx.fillRect(off+6*cell,off+i*cell,cell,cell);ctx.fillRect(off+i*cell,off+6*cell,cell,cell);}}
    // Data modules from text hash
    let hash=0;for(let i=0;i<text.length;i++){hash=((hash<<5)-hash+text.charCodeAt(i))|0;}
    let seed=Math.abs(hash);
    const rng=()=>{seed=(seed*16807+0)%2147483647;return seed;};
    for(let r=0;r<grid;r++)for(let c=0;c<grid;c++){
      // Skip finder areas + timing
      if((r<8&&c<8)||(r<8&&c>=grid-8)||(r>=grid-8&&c<8))continue;
      if(r===6||c===6)continue;
      if(rng()%3===0) ctx.fillRect(off+c*cell,off+r*cell,cell,cell);
    }
  };

  /* ── CHEQUE TEMPLATE LIVE UPDATE ── */
  const updatePayChequeTemplate=()=>{
    const isCheque=(String(el.method.value||'').toLowerCase().indexOf('cheque')!==-1);
    if(!isCheque)return;
    // Bank name
    const bankSel=el.chqBank;
    let bankLabel='Select Bank Account';
    if(bankSel){
      const bankText=bankSel.options[bankSel.selectedIndex]?.text||'Select Bank Account';
      bankLabel=bankText.split(' ••••')[0]||bankText;
      const g=q('#vp_tpl_bank');if(g)g.textContent=bankLabel;
      const accNo=bankText.includes('••••')?'Account #: ••••'+bankText.split('••••')[1]?.trim():'Account #: ----';
      const g2=q('#vp_tpl_accno');if(g2)g2.textContent=accNo;
    }
    // Cheque number
    const chqNo=el.chqNum?el.chqNum.value:'';
    const g3=q('#vp_tpl_chqno');if(g3)g3.textContent='Cheque #: '+(chqNo||'Auto');
    // Date (display as DD-MM-YYYY)
    const rawDate=el.date.value||'-';
    let chqDateFmt=rawDate;
    if(rawDate&&rawDate.indexOf('-')>0&&rawDate.length===10){const dp=rawDate.split('-');chqDateFmt=dp[2]+'-'+dp[1]+'-'+dp[0];}
    const g4=q('#vp_tpl_date');if(g4)g4.textContent=chqDateFmt;
    // Delivery type badge
    const delType=el.chqType?el.chqType.value:'ac_payee';
    const typeMap={'ac_payee':'A/C PAYEE','self':'SELF','cash':'CASH'};
    const g5=q('#vp_tpl_type_badge');if(g5)g5.textContent=typeMap[delType]||'A/C PAYEE';
    // Payee — custom name overrides vendor name
    let vendorName='_________________________';
    if(el.vendor&&el.vendor.selectedIndex>0){
      vendorName=el.vendor.options[el.vendor.selectedIndex].text||vendorName;
    }
    const customPayee=(el.chqPayee?el.chqPayee.value:'').trim();
    const payeeName=customPayee||vendorName;
    const g6=q('#vp_tpl_payee');if(g6)g6.textContent=payeeName;
    // Update audit trail labels
    if(el.chqVendorLabel) el.chqVendorLabel.textContent=vendorName!=='_________________________'?vendorName:'-';
    if(el.chqIssuedTo) el.chqIssuedTo.textContent=payeeName!=='_________________________'?payeeName:'-';
    // Amount — use dedicated cheque amount field, otherwise fall back to summary
    let totalAmt=num(el.chqAmt?el.chqAmt.value:0);
    if(totalAmt<=0){
      if(el.advOnlyMode.checked) totalAmt=num(el.advOnlyAmt.value);
      else totalAmt=num(el.sTotal.textContent);
    }
    const g7=q('#vp_tpl_amount');if(g7)g7.textContent=fmt(totalAmt);
    const g8=q('#vp_tpl_words');if(g8)g8.textContent=totalAmt>0?numberToWords(totalAmt):'Zero Rupees Only';
    // Payment type badge — advance if cheque advance mode OR advance-only mode
    const isAdvChq=el.chqAdvance&&el.chqAdvance.checked;
    const pType=isAdvChq||el.advOnlyMode.checked?'advance':(el.type?el.type.value:'settlement');
    const g9=q('#vp_tpl_ptype');
    if(g9){
      if(pType==='advance'){g9.textContent='ADVANCE';g9.style.borderColor='#dc3545';g9.style.color='#dc3545';}
      else{g9.textContent='SETTLEMENT';g9.style.borderColor='#28a745';g9.style.color='#28a745';}
    }
    // Advance banner
    const advBanner=q('#cheque_advance_banner');
    if(advBanner) advBanner.style.display=isAdvChq?'':'none';
    // Notes
    const notesVal=(el.chqNotes?el.chqNotes.value:'')||(el.memo?el.memo.value:'')||'-';
    const g10=q('#vp_tpl_notes');if(g10)g10.textContent=notesVal;
    // QR Code — encode cheque data for tracking
    const qrData=['CHQ',chqNo||'AUTO',payeeName,fmt(totalAmt),el.date.value||'',bankLabel,pType.toUpperCase()].join('|');
    drawQR(q('#vp_tpl_qr'),qrData);
  };

  /* Toggle cheque preview visibility */
  if(el.chqToggle){
    el.chqToggle.addEventListener('click',function(){
      const body=el.chqPreviewBody;
      if(!body)return;
      const hidden=body.classList.toggle('d-none');
      this.innerHTML=hidden?'<i class="bi bi-eye"></i> Show':'<i class="bi bi-eye-slash"></i> Hide';
    });
  }

  /* Wire cheque fields to update template */
  ['cheque_bank_account_id','cheque_number','cheque_delivery_type','cheque_notes','cheque_amount','cheque_payee_name'].forEach(id=>{
    const e=document.getElementById(id);
    if(e){e.addEventListener('change',updatePayChequeTemplate);e.addEventListener('input',updatePayChequeTemplate);}
  });

  /* ── LOAD VENDOR CONTACTS for cheque payee ── */
  const loadContacts=async()=>{
    const vid=getVid();
    if(!el.chqContact)return;
    el.chqContact.innerHTML='<option value="">-- Use custom name above --</option>';
    if(vid===null)return;
    try{
      const r=await fetch(URLS.contacts+'/'+vid,{headers:{'X-Requested-With':'XMLHttpRequest'}});
      const d=await r.json();
      if(d&&d.success&&d.data&&d.data.length){
        d.data.forEach(c=>{
          const opt=document.createElement('option');
          opt.value=c.id;
          opt.textContent=c.name+(c.phone?' (☎ '+c.phone+')':'')+(c.designation?' — '+c.designation:'');
          opt.dataset.name=c.name;
          el.chqContact.appendChild(opt);
        });
      }
    }catch(e){console.error('loadContacts',e);}
  };

  /* Contact dropdown → fill payee name */
  if(el.chqContact){
    el.chqContact.addEventListener('change',()=>{
      const opt=el.chqContact.options[el.chqContact.selectedIndex];
      if(opt&&opt.dataset.name){
        el.chqPayee.value=opt.dataset.name;
      }
      updatePayChequeTemplate();
    });
  }

  /* Quick-add contact */
  if(el.btnSaveContact){
    el.btnSaveContact.addEventListener('click',async()=>{
      const vid=getVid();
      if(vid===null){msg('warning','Select a vendor first.');return;}
      const cName=(el.chqNewName?el.chqNewName.value:'').trim();
      if(!cName){msg('warning','Enter contact name.');return;}
      const fd=new FormData();
      fd.append(CSRF.name,CSRF.value);
      fd.append('name',cName);
      fd.append('phone',(el.chqNewPhone?el.chqNewPhone.value:'').trim());
      el.btnSaveContact.disabled=true;
      try{
        const r=await fetch(URLS.addContact+'/'+vid+'/addContact',{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest'},body:fd});
        /* Refresh CSRF token from response cookie */
        try{
          const cookies=document.cookie.split(';');
          for(const c of cookies){
            const [k,v]=c.trim().split('=');
            if(k.trim()==='csrf_cookie_name'){CSRF.value=decodeURIComponent(v);break;}
          }
        }catch(ce){}
        let d;
        const contentType=r.headers.get('content-type')||'';
        if(contentType.indexOf('json')!==-1){
          d=await r.json();
        } else {
          const txt=await r.text();
          console.error('addContact non-JSON response:',r.status,txt);
          msg('danger','Server error ('+r.status+'). Check console.');
          return;
        }
        if(d&&(d.success||d.status==='success')){
          if(el.chqPayee) el.chqPayee.value=cName;
          if(el.chqNewName) el.chqNewName.value='';
          if(el.chqNewPhone) el.chqNewPhone.value='';
          await loadContacts();
          const opts=el.chqContact.options;
          for(let i=0;i<opts.length;i++){if(opts[i].dataset.name===cName){el.chqContact.selectedIndex=i;break;}}
          updatePayChequeTemplate();
          msg('success','Contact "'+cName+'" saved.');
        } else {
          msg('danger',(d&&d.message)||'Failed to save contact. '+r.status);
          console.error('addContact response:',d);
        }
      }catch(e){msg('danger','Network error saving contact.');console.error(e);}
      finally{el.btnSaveContact.disabled=false;}
    });
  }
  /* Cheque advance mode toggle — just updates badge, does NOT show separate advance section */
  if(el.chqAdvance){
    el.chqAdvance.addEventListener('change',()=>{
      updatePayChequeTemplate();
      refresh();
    });
  }
  /* Sync cheque bank account → source account */
  if(el.chqBank){
    el.chqBank.addEventListener('change',()=>{
      if(el.source) el.source.value=el.chqBank.value;
    });
  }
  /* Cheque amount always refreshes summary (used for cheque advance mode too) */
  if(el.chqAmt){
    el.chqAmt.addEventListener('input',()=>{
      if(el.advOnlyMode.checked && el.advOnlyAmt){
        el.advOnlyAmt.value=el.chqAmt.value;
      }
      autoChequeDistribute();
      refresh();
    });
  }

  /* VENDOR CHANGE */
  const onVendorChange=()=>{
    draftId=null;el.btnConfirm.disabled=true;msg('','');
    const vid=getVid();
    if(vid!==null){
      loadBalance();loadBills();loadContacts();
      // Default payee name to vendor name
      if(el.chqPayee&&el.vendor.selectedIndex>0){
        el.chqPayee.value=el.vendor.options[el.vendor.selectedIndex].text;
        el.chqPayee.placeholder=el.vendor.options[el.vendor.selectedIndex].text;
      }
    } else {
      bills=[];advance=0;el.advStrip.style.display='none';
      ph('Select a vendor to load outstanding bills');
      if(el.chqPayee){el.chqPayee.value='';el.chqPayee.placeholder='Defaults to vendor name';}
      if(el.chqContact) el.chqContact.innerHTML='<option value="">-- Use custom name above --</option>';
      refresh();
    }
  };

  /* METHOD CHANGE */
  el.method.addEventListener('change',()=>{ autoChequeDistribute(); refresh(); });
  /* Date / memo changes also update cheque template */
  el.date.addEventListener('change',updatePayChequeTemplate);
  if(el.memo) el.memo.addEventListener('input',updatePayChequeTemplate);

  /* OPEN dedicated cheque flow with prefilled values */
  if(el.btnIssueCheque){
    el.btnIssueCheque.addEventListener('click',()=>{
      const vid=getVid();
      if(vid===null){msg('warning','Select a vendor first.');return;}

      let amount=0;
      let mode='settlement';
      const advOnlyMode=el.advOnlyMode.checked;
      if(advOnlyMode){
        amount=num(el.advOnlyAmt.value);
        mode='advance';
      } else {
        amount=num(el.sTotal.textContent);
      }

      if(amount<=0){
        msg('warning','Enter/select an amount first, then open cheque page.');
        return;
      }

      const params=new URLSearchParams();
      params.set('vendor_id',String(vid));
      params.set('amount',amount.toFixed(2));
      params.set('date',el.date.value||'');
      params.set('mode',mode);
      if((el.memo.value||'').trim()) params.set('memo',(el.memo.value||'').trim());
      if((el.notes.value||'').trim()) params.set('notes',(el.notes.value||'').trim());

      window.location.href=URLS.chequeCreate+'?'+params.toString();
    });
  }
  el.vendor.addEventListener('change',onVendorChange);
  try{if(window.jQuery)window.jQuery(el.vendor).on('change select2:select',onVendorChange);}catch(e){}

  /* ── Attachment Management ─────────────────────────── */
  const MAX_ATTACH = 5;
  let existingAtts = EDIT_ATTACHMENTS.slice();

  function renderAttachments() {
    const container = q('#existing_attachments');
    const counter   = q('#attach_counter');
    const hint      = q('#attach_hint');
    const newWrap   = q('#new_attach_wrap');
    if (!container) return;

    container.innerHTML = '';
    existingAtts.forEach(att => {
      const isImg = /\.(jpg|jpeg|png)$/i.test(att.name);
      const row = document.createElement('div');
      row.className = 'd-flex align-items-center gap-2 mb-1 px-2 py-1 rounded';
      row.style.cssText = 'background:#1e2633;border:1px solid #2d3748;font-size:12px;';
      const preview = isImg
        ? `<a href="${esc(att.url)}" class="lightbox-img" tabindex="-1"><img src="${esc(att.url)}" style="height:30px;width:30px;object-fit:cover;border-radius:3px;flex-shrink:0;" onerror="this.style.display='none'" /></a>`
        : `<i class="bi bi-file-earmark-pdf" style="font-size:1.1rem;color:#7eb4f7;flex-shrink:0;"></i>`;
      row.innerHTML = `${preview}<span class="flex-grow-1 text-truncate" style="max-width:180px;color:#d1d5db;" title="${esc(att.name)}">${esc(att.name)}</span><button type="button" class="btn btn-sm py-0 px-1 att-del" data-id="${att.id}" style="color:#ef4444;border:1px solid #ef444466;font-size:11px;line-height:1.4;background:transparent;" title="Delete"><i class="bi bi-trash3"></i></button>`;
      container.appendChild(row);
    });

    const used = existingAtts.length;
    const remaining = MAX_ATTACH - used;
    if (counter) { counter.textContent = `${used} / ${MAX_ATTACH}`; counter.style.color = used >= MAX_ATTACH ? '#ef4444' : '#9ca3af'; }
    if (newWrap) newWrap.style.display = remaining > 0 ? '' : 'none';
    if (hint) hint.textContent = remaining > 0 ? `Add up to ${remaining} more file${remaining !== 1 ? 's' : ''} \u2014 PDF, JPG, PNG` : '';
    if (el.attach) el.attach.value = '';
  }

  document.addEventListener('click', async function(e) {
    const btn = e.target.closest('#existing_attachments .att-del');
    if (!btn) return;
    const id = parseInt(btn.dataset.id, 10);
    if (!id) return;
    if (!confirm('Delete this attachment?')) return;
    btn.disabled = true;
    try {
      const fd = new FormData();
      fd.append('attachment_id', id);
      fd.append(CSRF.name, CSRF.value);
      const r = await fetch(URLS.deleteAttachment, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd });
      const d = await r.json();
      if (d.success) {
        existingAtts = existingAtts.filter(a => a.id !== id);
        renderAttachments();
      } else {
        alert(d.message || 'Failed to delete');
        btn.disabled = false;
      }
    } catch (err) {
      alert('Error deleting attachment');
      btn.disabled = false;
    }
  });

  if (el.attach) {
    el.attach.addEventListener('change', function() {
      const remaining = MAX_ATTACH - existingAtts.length;
      if (this.files.length > remaining) {
        alert(`You can only add ${remaining} more file${remaining !== 1 ? 's' : ''} (max ${MAX_ATTACH} total)`);
        this.value = '';
      }
    });
  }

  document.addEventListener('click', function(e) {
    const a = e.target.closest('a.lightbox-img');
    if (!a) return;
    e.preventDefault();
    const src = a.getAttribute('href');
    const bg = document.createElement('div');
    bg.className = 'vp-lightbox-bg';
    bg.innerHTML = `<img src="${src}" class="vp-lightbox-img" />`;
    document.body.appendChild(bg);
    bg.addEventListener('click', function(ev) {
      if (ev.target === bg) bg.remove();
    });
    bg.addEventListener('keydown', function(ev) {
      if (ev.key === 'Escape') bg.remove();
    });
    bg.focus();
  }, true);

  renderAttachments();

  /* INIT */
  if(getVid()!==null){loadBalance();loadBills();}
})();
</script>
<?= $this->endSection() ?>
