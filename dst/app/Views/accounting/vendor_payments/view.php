<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Vendor Payment #<?= (int)($payment['id'] ?? 0) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $paymentId = (int)($payment['id'] ?? 0);
  $vendorName = (string)($payment['vendor_name'] ?? '');
  $method = strtolower(trim((string)($payment['payment_method'] ?? '')));
  $paymentType = strtolower(trim((string)($payment['payment_type'] ?? 'settlement')));
  $sourceName = (string)($payment['source_account_name'] ?? '');
  $sourceNo = (string)($payment['source_account_number'] ?? '');
  $sourceSummary = trim($sourceName . ($sourceNo ? ' ••••' . substr($sourceNo, -4) : ''));
  $status = strtolower(trim((string)($payment['status'] ?? '')));
  $currency = (string)($payment['currency_code'] ?? 'PKR');
  $amount = (float)($payment['amount'] ?? 0);
  $date = (string)($payment['payment_date'] ?? '');
  $memo = (string)($payment['memo'] ?? '');
  $notes = (string)($payment['notes'] ?? '');
  $chequePayee = (string)($payment['cheque_payee_name'] ?? '');
  $chequeNotes = (string)($payment['cheque_notes'] ?? '');
  $chequeNumber = (string)($payment['cheque_number'] ?? '');
  $chequeDeliveryType = (string)($payment['cheque_delivery_type'] ?? '');
  $postedEntryId = (int)($payment['posted_entry_id'] ?? 0);
  $attCount = is_array($attachments ?? null) ? count($attachments) : 0;

  // Badge helpers
  $statusColors = ['draft'=>['bg'=>'rgba(245,158,11,.1)','fg'=>'#d97706','bd'=>'rgba(245,158,11,.25)'], 'posted'=>['bg'=>'rgba(34,197,94,.1)','fg'=>'#16a34a','bd'=>'rgba(34,197,94,.25)'], 'void'=>['bg'=>'rgba(239,68,68,.1)','fg'=>'#ef4444','bd'=>'rgba(239,68,68,.25)']];
  $sc = $statusColors[$status] ?? $statusColors['draft'];
  $typeColors = ['advance'=>['bg'=>'rgba(220,53,69,.08)','fg'=>'#dc3545','bd'=>'rgba(220,53,69,.2)'], 'settlement'=>['bg'=>'rgba(37,99,235,.08)','fg'=>'#2563eb','bd'=>'rgba(37,99,235,.2)']];
  $tc = $typeColors[$paymentType] ?? $typeColors['settlement'];
  $methodIcons = ['cheque'=>'bi-credit-card','cash'=>'bi-cash-coin','bank'=>'bi-bank','online_transfer'=>'bi-globe'];
  $methodColors = ['cheque'=>['bg'=>'rgba(139,92,246,.08)','fg'=>'#7c3aed','bd'=>'rgba(139,92,246,.2)'], 'cash'=>['bg'=>'rgba(34,197,94,.06)','fg'=>'#16a34a','bd'=>'rgba(34,197,94,.2)'], 'bank'=>['bg'=>'rgba(37,99,235,.06)','fg'=>'#2563eb','bd'=>'rgba(37,99,235,.2)'], 'online_transfer'=>['bg'=>'rgba(245,158,11,.06)','fg'=>'#d97706','bd'=>'rgba(245,158,11,.2)']];
  $mc = $methodColors[$method] ?? $methodColors['cash'];
?>

<style>
  .vpv { max-width:1200px; margin:0 auto; }
  .vpv-card { background:var(--white,#fff); border:1px solid var(--gray-200,#e2e8f0); border-radius:.5rem; overflow:hidden; }
  .vpv-hdr {
    padding:.5rem 1rem; display:flex; align-items:center; justify-content:space-between;
    border-bottom:1px solid var(--gray-200,#e2e8f0); background:var(--gray-50,#f8fafc);
  }

  /* Inline badge style */
  .vpv-badge {
    display:inline-block; padding:2px 8px; border-radius:3px; font-size:.68rem;
    font-weight:700; text-transform:uppercase; letter-spacing:.3px;
  }

  /* Info table */
  .vpv-info { width:100%; border-collapse:collapse; }
  .vpv-info td { padding:.4rem .65rem; font-size:.82rem; vertical-align:top; border-bottom:1px solid var(--gray-100,#f1f5f9); }
  .vpv-info .lbl { width:140px; font-size:.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.4px; color:var(--gray-400,#94a3b8); white-space:nowrap; }
  .vpv-info .val { font-weight:600; color:var(--gray-700,#1e293b); }

  .vpv-amt {
    font-size:1.3rem; font-weight:800; font-family:'Courier New',monospace; color:var(--gray-800,#0f172a);
  }
  .vpv-amt .cur { font-size:.7rem; color:var(--gray-400,#94a3b8); vertical-align:top; margin-right:2px; font-weight:600; }

  /* Attachments */
  .vpv-att { display:flex; gap:6px; flex-wrap:wrap; align-items:center; }
  .vpv-att-item {
    display:inline-flex; align-items:center; gap:4px; padding:3px 8px; border-radius:4px;
    background:var(--gray-50,#f8fafc); border:1px solid var(--gray-200,#e2e8f0); font-size:.72rem; font-weight:600;
  }
  .vpv-att-item a { color:inherit; text-decoration:none; display:inline-flex; align-items:center; }
  .vpv-att-item a:hover { color:#2563eb; }

  /* Allocations */
  .vpv-alloc th {
    font-size:.68rem; text-transform:uppercase; letter-spacing:.5px; font-weight:600;
    padding:.4rem .65rem; background:var(--gray-50,#f8fafc); color:var(--gray-500,#64748b);
    border-bottom:2px solid var(--gray-200,#e2e8f0);
  }
  .vpv-alloc td { padding:.4rem .65rem; font-size:.82rem; border-bottom:1px solid var(--gray-100,#f1f5f9); color:var(--gray-600,#475569); }

  /* Section labels */
  .vpv-sec { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:var(--gray-400,#94a3b8); }

  /* Action buttons */
  .vpv-btn { padding:.25rem .6rem; font-size:.72rem; font-weight:600; border-radius:.3rem; }
</style>

<style>
  .vp-lightbox-bg {position:fixed;top:0;left:0;width:100vw;height:100vh;background:rgba(18,22,34,.85);z-index:9999;display:flex;align-items:center;justify-content:center;}
  .vp-lightbox-img {max-width:90vw;max-height:90vh;box-shadow:0 8px 32px #000a;border-radius:8px;}
  .vp-lightbox-bg:after {content:'\2715';position:absolute;top:24px;right:40px;font-size:2.2rem;color:#fff;opacity:.7;cursor:pointer;z-index:10001;transition:.2s;}
  .vp-lightbox-bg:after:hover {opacity:1;}
</style>

<div class="container-fluid px-3 py-2 vpv">

  <!-- Page Header -->
  <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="d-flex align-items-center gap-2">
      <div style="width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;font-size:16px;">
        <i class="bi bi-receipt"></i>
      </div>
      <div>
        <h6 class="mb-0 fw-bold" style="color:var(--gray-700,#1e293b);font-size:.95rem;">Vendor Payment #<?= $paymentId ?></h6>
        <div style="font-size:.7rem;color:var(--gray-400,#94a3b8)">Created <?php $ca = $payment['created_at'] ?? $date; echo $ca ? date('d-m-Y H:i', strtotime($ca)) : '-'; ?></div>
      </div>
      <span class="vpv-badge ms-2" style="background:<?= $sc['bg'] ?>;color:<?= $sc['fg'] ?>;border:1px solid <?= $sc['bd'] ?>"><?= strtoupper($status) ?></span>
      <span class="vpv-badge ms-1" style="background:rgba(100,116,139,.08);color:#64748b;border:1px solid rgba(100,116,139,.15)" title="<?= $attCount ?> attachment<?= $attCount !== 1 ? 's' : '' ?>"><i class="bi bi-paperclip me-1"></i><?= $attCount ?></span>
    </div>
    <div class="d-flex gap-1">
      <a class="btn btn-outline-secondary vpv-btn" href="<?= base_url('accounting/vendor-payments') ?>"><i class="bi bi-arrow-left me-1"></i>All Payments</a>
      <?php if ($postedEntryId > 0): ?>
        <a class="btn btn-outline-secondary vpv-btn" href="<?= base_url('accounting/journals/view/' . $postedEntryId) ?>"><i class="bi bi-journal-text me-1"></i>Journal</a>
      <?php endif; ?>
      <?php if ($status === 'draft'): ?>
        <a class="btn btn-outline-primary vpv-btn" href="<?= base_url('accounting/vendor-payments/' . $paymentId . '/edit') ?>"><i class="bi bi-pencil me-1"></i>Edit</a>
        <button type="button" class="btn btn-success vpv-btn js-confirm-payment" data-payment-id="<?= $paymentId ?>"><i class="bi bi-check-lg me-1"></i>Confirm & Post</button>
      <?php endif; ?>
    </div>
  </div>

  <div class="row g-2">
    <!-- Left: Payment Details -->
    <div class="col-lg-8">
      <div class="vpv-card mb-2">
        <div class="vpv-hdr">
          <span class="vpv-sec"><i class="bi bi-info-circle me-1"></i>Payment Details</span>
          <span class="vpv-amt"><span class="cur"><?= esc($currency) ?></span><?= number_format($amount, 2) ?></span>
        </div>
        <table class="vpv-info">
          <tr>
            <td class="lbl">Vendor</td>
            <td class="val"><?= esc($vendorName ?: '-') ?></td>
            <td class="lbl">Date</td>
            <td class="val"><?= $date ? date('d-m-Y', strtotime($date)) : '-' ?></td>
          </tr>
          <tr>
            <td class="lbl">Type</td>
            <td>
              <span class="vpv-badge" style="background:<?= $tc['bg'] ?>;color:<?= $tc['fg'] ?>;border:1px solid <?= $tc['bd'] ?>"><?= strtoupper($paymentType) ?></span>
            </td>
            <td class="lbl">Method</td>
            <td>
              <span class="vpv-badge" style="background:<?= $mc['bg'] ?>;color:<?= $mc['fg'] ?>;border:1px solid <?= $mc['bd'] ?>">
                <i class="bi <?= $methodIcons[$method] ?? 'bi-credit-card' ?> me-1"></i><?= ucfirst(str_replace('_',' ',$method)) ?>
              </span>
            </td>
          </tr>
          <tr>
            <td class="lbl">Source Account</td>
            <td class="val"><?= esc($sourceSummary ?: '-') ?></td>
            <td class="lbl">Reference</td>
            <td class="val"><?= esc($payment['reference_no'] ?? $memo ?: '-') ?></td>
          </tr>
          <?php if ($chequePayee && $method === 'cheque'): ?>
          <tr>
            <td class="lbl">Cheque Payee</td>
            <td class="val"><?= esc($chequePayee) ?></td>
            <td class="lbl">Cheque #</td>
            <td class="val"><?= esc($chequeNumber ?: 'Auto') ?></td>
          </tr>
          <?php endif; ?>
          <?php if ($chequeNotes): ?>
          <tr>
            <td class="lbl">Cheque Notes</td>
            <td class="val" colspan="3"><?= esc($chequeNotes) ?></td>
          </tr>
          <?php endif; ?>
          <?php if ($notes): ?>
          <tr>
            <td class="lbl">Notes</td>
            <td class="val" colspan="3"><?= esc($notes) ?></td>
          </tr>
          <?php endif; ?>
        </table>
      </div>

      <?php if ($method === 'cheque'): ?>
      <!-- Cheque Preview (inline) -->
      <div class="vpv-card mb-2">
        <div class="vpv-hdr">
          <span class="vpv-sec"><i class="bi bi-credit-card-2-front me-1"></i>Cheque Preview</span>
          <div class="d-flex gap-1">
            <button type="button" class="btn btn-sm btn-outline-primary vpv-btn" id="btnDownloadPdf"><i class="bi bi-file-earmark-pdf me-1"></i>PDF</button>
            <button type="button" class="btn btn-sm btn-outline-secondary vpv-btn" id="btnPrintCheque"><i class="bi bi-printer me-1"></i>Print</button>
          </div>
        </div>
        <div id="chequePreviewWrap" style="padding:.75rem;background:var(--gray-50,#f8fafc);">
          <div id="chequePreview" style="border:2px solid #1a5276; padding:22px 24px; background:linear-gradient(135deg,#eaf2fb 0%,#d6e8f8 50%,#c8dff2 100%); font-family:'Courier New',monospace; max-width:720px; margin:0 auto; position:relative; border-radius:6px; box-shadow:0 2px 12px rgba(26,82,118,.12);">
            <!-- Top security pattern -->
            <div style="position:absolute;top:0;left:0;right:0;height:4px;background:repeating-linear-gradient(90deg,#1a5276 0,#1a5276 8px,transparent 8px,transparent 12px);border-radius:6px 6px 0 0;"></div>
            <!-- Bank Header -->
            <div style="display:flex; justify-content:space-between; border-bottom:2px solid #1a5276; padding-bottom:10px; margin-bottom:12px; margin-top:4px;">
              <div>
                <div style="font-size:17px; font-weight:bold; text-transform:uppercase; color:#1a1a1a; letter-spacing:.5px;"><?= esc($sourceName ?: 'Bank Account') ?></div>
                <div style="font-size:11px; color:#333;">Account #: <?= $sourceNo ? '••••' . esc(substr($sourceNo, -4)) : '----' ?></div>
              </div>
              <div style="text-align:right;">
                <div style="font-size:12px; font-weight:bold; color:#1a1a1a;">Cheque #: <?= esc($chequeNumber ?: ($payment['reference_no'] ?? 'Auto')) ?></div>
                <div style="font-size:11px; color:#333;"><?= $date ? date('d-m-Y', strtotime($date)) : '' ?></div>
                <?php 
                  $dtMap = ['ac_payee'=>'A/C PAYEE','self'=>'SELF','cash'=>'CASH'];
                  $dtLabel = $dtMap[$chequeDeliveryType] ?? 'A/C PAYEE';
                ?>
                <span style="display:inline-block; border:1.5px solid #1a5276; color:#1a5276; padding:1px 10px; font-size:9px; font-weight:bold; margin-top:3px; border-radius:2px; background:rgba(255,255,255,.5);"><?= $dtLabel ?></span>
              </div>
            </div>
            <!-- Pay To -->
            <div style="margin-bottom:12px;">
              <span style="font-weight:bold; font-size:11px; color:#333;">PAY TO THE ORDER OF:</span>
              <span style="border-bottom:2px dotted #1a5276; display:inline-block; width:calc(100% - 160px); padding:2px 6px; font-size:15px; font-weight:bold; min-height:24px; color:#1a1a1a;"><?= esc($chequePayee ?: $vendorName ?: '_________________________') ?></span>
            </div>
            <!-- Amount Box & Words -->
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:10px;">
              <div style="flex:1; margin-right:14px;">
                <span style="font-weight:bold; font-size:11px; color:#333;">RUPEES IN WORDS:</span>
                <div style="border-bottom:2px dotted #1a5276; font-style:italic; font-size:11px; min-height:20px; padding:3px 6px; text-transform:uppercase; color:#1a1a1a;" id="chq_words">Loading...</div>
              </div>
              <div style="border:3px solid #1a5276; padding:8px 16px; background:rgba(255,255,255,.7); text-align:center; min-width:150px; border-radius:4px;">
                <div style="font-size:8px; font-weight:bold; color:#1a5276; letter-spacing:1px;">PKR</div>
                <div style="font-size:20px; font-weight:bold; color:#1a1a1a;"><?= number_format($amount, 2) ?></div>
              </div>
            </div>
            <!-- Bottom: QR + Type + Signature -->
            <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-top:8px; padding-top:8px; border-top:1px dashed #1a5276;">
              <div style="text-align:center;">
                <canvas id="chq_qr" width="70" height="70" style="border:1px solid #ccc;border-radius:3px;background:#fff;"></canvas>
                <div style="font-size:7px;color:#555;margin-top:2px;">Scan to verify</div>
              </div>
              <div style="flex:1;margin:0 14px;">
                <div style="font-size:9px; color:#555;">
                  <span style="font-weight:bold; color:#333;">Memo:</span> <span><?= esc($chequeNotes ?: $memo ?: '-') ?></span>
                </div>
                <div style="margin-top:6px;">
                  <?php $ptc = ($paymentType === 'advance') ? '#dc3545' : '#28a745'; ?>
                  <span style="display:inline-block; border:2px solid <?= $ptc ?>; color:<?= $ptc ?>; padding:2px 10px; font-size:9px; font-weight:bold; border-radius:3px; background:rgba(255,255,255,.5);"><?= strtoupper($paymentType) ?></span>
                </div>
              </div>
              <div style="text-align:center; min-width:140px;">
                <div style="border-bottom:2px solid #1a5276; margin-bottom:4px; min-height:30px;"></div>
                <div style="font-size:9px; font-weight:bold; color:#333; letter-spacing:.5px;">AUTHORISED SIGNATORY</div>
              </div>
            </div>
            <!-- Bottom security pattern -->
            <div style="position:absolute;bottom:0;left:0;right:0;height:4px;background:repeating-linear-gradient(90deg,#1a5276 0,#1a5276 8px,transparent 8px,transparent 12px);border-radius:0 0 6px 6px;"></div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <?php if (!empty($allocations)): ?>
      <!-- Allocations -->
      <div class="vpv-card mb-2">
        <div class="vpv-hdr">
          <span class="vpv-sec"><i class="bi bi-stack me-1"></i>Bill Allocations</span>
          <span class="vpv-badge" style="background:rgba(37,99,235,.08);color:#2563eb;border:1px solid rgba(37,99,235,.15)"><?= count($allocations) ?> bill<?= count($allocations) > 1 ? 's' : '' ?></span>
        </div>
        <div class="table-responsive">
          <table class="vpv-alloc" style="width:100%;border-collapse:collapse;">
            <thead><tr><th>Bill #</th><th>Date</th><th class="text-end">Bill Total</th><th class="text-end">Balance</th><th>Status</th><th class="text-end">Allocated</th></tr></thead>
            <tbody>
              <?php $allocTotal = 0; foreach ($allocations as $a): $aAmt = (float)($a['amount_allocated'] ?? $a['amount'] ?? 0); $allocTotal += $aAmt;
                $billTotal = (float)($a['bill_total'] ?? 0);
                $billBalance = (float)($a['bill_balance'] ?? 0);
                $billStatus = ucfirst($a['bill_status'] ?? 'unknown');
                $billId = (int)($a['vendor_bill_id'] ?? 0);
                $billNum = $a['bill_number'] ?? ('VB-' . $billId);
                $billDate = ($a['bill_date'] ?? null) ? date('d-m-Y', strtotime($a['bill_date'])) : '—';
                $bsColor = ['paid'=>'#16a34a','confirmed'=>'#2563eb','draft'=>'#d97706','cancelled'=>'#ef4444'][$a['bill_status'] ?? ''] ?? '#64748b';
              ?>
              <tr>
                <td class="fw-semibold"><a href="<?= base_url('vendor-bills/' . $billId) ?>" style="color:#2563eb;text-decoration:none;" title="View bill details"><?= esc($billNum) ?> <i class="bi bi-box-arrow-up-right" style="font-size:.65rem"></i></a></td>
                <td><?= $billDate ?></td>
                <td class="text-end"><?= number_format($billTotal, 2) ?></td>
                <td class="text-end" style="color:<?= $billBalance > 0 ? '#ef4444' : '#16a34a' ?>;font-weight:600"><?= number_format($billBalance, 2) ?></td>
                <td><span style="font-size:.68rem;font-weight:600;color:<?= $bsColor ?>;text-transform:uppercase"><?= $billStatus ?></span></td>
                <td class="text-end fw-semibold"><?= number_format($aAmt, 2) ?></td>
              </tr>
              <?php endforeach; ?>
              <tr style="background:var(--gray-50,#f8fafc)">
                <td colspan="5" class="fw-bold" style="font-size:.78rem;color:var(--gray-500,#64748b)">Total Allocated</td>
                <td class="text-end fw-bold" style="font-size:.85rem;color:var(--gray-700,#1e293b)"><?= number_format($allocTotal, 2) ?></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Right: Sidebar -->
    <div class="col-lg-4">
      <?php if ($attCount > 0): ?>
      <!-- Attachments (compact) -->
      <div class="vpv-card mb-2">
        <div class="vpv-hdr">
          <span class="vpv-sec"><i class="bi bi-paperclip me-1"></i>Attachments</span>
          <span class="vpv-badge" style="background:rgba(37,99,235,.08);color:#2563eb;border:1px solid rgba(37,99,235,.15)"><?= $attCount ?> file<?= $attCount > 1 ? 's' : '' ?></span>
        </div>
        <div style="padding:.5rem .75rem;">
          <div class="vpv-att">
            <?php foreach ($attachments as $i => $a):
              $filePath = (string)($a['file_path'] ?? '');
              $url = base_url(ltrim($filePath, '/'));
              $name = (string)($a['original_name'] ?? 'Receipt');
              $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
              $fileOnDisk = FCPATH . ltrim($filePath, '/');
              $exists = file_exists($fileOnDisk);
              $iconClass = in_array($ext, ['pdf']) ? 'bi-file-earmark-pdf text-danger' : (in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'bi-card-image text-primary' : 'bi-paperclip');
              $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
            ?>
              <span class="vpv-att-item" title="<?= esc($name) ?>">
                <i class="bi <?= $iconClass ?>" style="font-size:.85rem"></i>
                <span style="max-width:120px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($name) ?></span>
                <?php if ($exists): ?>
                  <?php if ($isImg): ?>
                    <a href="<?= esc($url) ?>" class="lightbox-img" tabindex="-1" title="View"><i class="bi bi-eye" style="font-size:.75rem"></i></a>
                  <?php else: ?>
                    <a href="<?= esc($url) ?>" target="_blank" title="View"><i class="bi bi-eye" style="font-size:.75rem"></i></a>
                  <?php endif; ?>
                  <a href="<?= esc($url) ?>" download title="Download"><i class="bi bi-download" style="font-size:.75rem"></i></a>
                <?php else: ?>
                  <span style="color:#ef4444;font-size:.65rem">Missing</span>
                <?php endif; ?>
              </span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Quick Summary -->
      <div class="vpv-card mb-2">
        <div class="vpv-hdr">
          <span class="vpv-sec"><i class="bi bi-bar-chart me-1"></i>Summary</span>
        </div>
        <div style="padding:.5rem .75rem;">
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)">
            <span style="font-size:.78rem;color:var(--gray-500,#64748b)">Payment Amount</span>
            <span style="font-size:.85rem;font-weight:700;color:var(--gray-700,#1e293b)"><?= esc($currency) ?> <?= number_format($amount, 2) ?></span>
          </div>
          <?php $advAmt = (float)($payment['advance_amount'] ?? 0); if ($advAmt > 0): ?>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)">
            <span style="font-size:.78rem;color:var(--gray-500,#64748b)">Advance Amount</span>
            <span style="font-size:.85rem;font-weight:700;color:#d97706"><?= esc($currency) ?> <?= number_format($advAmt, 2) ?></span>
          </div>
          <?php endif; ?>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;border-bottom:1px solid var(--gray-100,#f1f5f9)">
            <span style="font-size:.78rem;color:var(--gray-500,#64748b)">Bills Allocated</span>
            <span style="font-size:.85rem;font-weight:600"><?= is_array($allocations ?? null) ? count($allocations) : 0 ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center" style="padding:.3rem 0;">
            <span style="font-size:.78rem;color:var(--gray-500,#64748b)">Journal Entry</span>
            <span style="font-size:.85rem;font-weight:600"><?php if ($postedEntryId > 0): ?><a href="<?= base_url('accounting/journals/view/' . $postedEntryId) ?>" style="color:#2563eb">#<?= $postedEntryId ?></a><?php elseif ($status === 'draft'): ?><span style="color:#d97706;font-size:.75rem"><i class="bi bi-clock me-1"></i>Pending (Draft)</span><?php else: ?><span style="color:var(--gray-400,#94a3b8)">&mdash;</span><?php endif; ?></span>
          </div>
        </div>
      </div>


    </div>
  </div>



</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<?php if ($method === 'cheque'): ?>
<!-- html2canvas + jsPDF for PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<?php endif; ?>
<script>
(() => {
  /* ─── Confirm & Post (draft only) ─── */
  const btn = document.querySelector('.js-confirm-payment');
  if (btn) {
    const confirmEndpoint = '<?= base_url('accounting/vendor-payments/confirm') ?>';
    const csrfName = '<?= csrf_token() ?>';
    const csrfValue = '<?= csrf_hash() ?>';

    btn.addEventListener('click', async () => {
      const paymentId = btn.getAttribute('data-payment-id');
      if (!paymentId) return;
      if (!window.confirm('Post this vendor payment? This will create a journal entry and cannot be undone easily.')) return;

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
          setTimeout(() => window.location.reload(), 600);
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
    });
  }

  <?php if ($method === 'cheque'): ?>
  /* ─── Number to Words ─── */
  const numberToWords = (n) => {
    if (n === 0) return 'Zero Rupees Only';
    const ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    const tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    const scales = ['','Thousand','Lakh','Crore'];

    const twoDigit = (x) => {
      if (x < 20) return ones[x];
      return tens[Math.floor(x / 10)] + (x % 10 ? ' ' + ones[x % 10] : '');
    };
    const threeDigit = (x) => {
      const h = Math.floor(x / 100);
      const r = x % 100;
      return (h ? ones[h] + ' Hundred ' : '') + twoDigit(r);
    };

    let intPart = Math.floor(Math.abs(n));
    const decPart = Math.round((Math.abs(n) - intPart) * 100);
    const groups = [];
    groups.push(intPart % 1000); intPart = Math.floor(intPart / 1000);
    while (intPart > 0) { groups.push(intPart % 100); intPart = Math.floor(intPart / 100); }

    let words = '';
    for (let i = groups.length - 1; i >= 0; i--) {
      if (groups[i] === 0) continue;
      words += (i === 0 ? threeDigit(groups[i]) : twoDigit(groups[i])) + ' ' + scales[i] + ' ';
    }
    words = words.trim() + ' Rupees';
    if (decPart > 0) words += ' and ' + twoDigit(decPart) + ' Paisa';
    return words.replace(/\s+/g, ' ').trim() + ' Only';
  };

  /* ─── Simple QR Code (matrix-style pattern for visual) ─── */
  const drawQR = (canvas, text) => {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const size = canvas.width;
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, size, size);

    let hash = 0;
    for (let i = 0; i < text.length; i++) { hash = ((hash << 5) - hash + text.charCodeAt(i)) | 0; }

    const modules = 21;
    const cellSize = size / modules;
    ctx.fillStyle = '#1a1a1a';

    // Finder patterns (3 corners)
    const drawFinder = (ox, oy) => {
      for (let r = 0; r < 7; r++) for (let c = 0; c < 7; c++) {
        if (r === 0 || r === 6 || c === 0 || c === 6 || (r >= 2 && r <= 4 && c >= 2 && c <= 4))
          ctx.fillRect((ox + c) * cellSize, (oy + r) * cellSize, cellSize, cellSize);
      }
    };
    drawFinder(0, 0); drawFinder(modules - 7, 0); drawFinder(0, modules - 7);

    // Data modules (seeded pseudo-random from hash)
    let seed = Math.abs(hash);
    for (let r = 0; r < modules; r++) for (let c = 0; c < modules; c++) {
      if ((r < 9 && c < 9) || (r < 9 && c >= modules - 8) || (r >= modules - 8 && c < 9)) continue;
      seed = (seed * 16807 + 7) % 2147483647;
      if (seed % 3 === 0) ctx.fillRect(c * cellSize, r * cellSize, cellSize, cellSize);
    }
  };

  // Populate amount words and QR
  const amount = <?= json_encode($amount) ?>;
  const wordsEl = document.getElementById('chq_words');
  if (wordsEl) wordsEl.textContent = numberToWords(amount);

  const qrCanvas = document.getElementById('chq_qr');
  if (qrCanvas) {
    const qrData = 'VP-<?= $paymentId ?>|<?= esc($vendorName) ?>|<?= number_format($amount, 2) ?>|<?= esc($date) ?>';
    drawQR(qrCanvas, qrData);
  }

  /* ─── PDF Download ─── */
  const btnPdf = document.getElementById('btnDownloadPdf');
  if (btnPdf) {
    btnPdf.addEventListener('click', async () => {
      const chequeEl = document.getElementById('chequePreview');
      if (!chequeEl) return;

      btnPdf.disabled = true;
      const orig = btnPdf.innerHTML;
      btnPdf.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Generating...';

      try {
        const canvas = await html2canvas(chequeEl, {
          scale: 2,
          useCORS: true,
          backgroundColor: null,
          logging: false,
        });

        const imgData = canvas.toDataURL('image/png');
        const { jsPDF } = window.jspdf;
        const pdf = new jsPDF({
          orientation: 'landscape',
          unit: 'mm',
          format: [100, 210],  // cheque size ~210mm x 100mm
        });

        const pdfWidth = pdf.internal.pageSize.getWidth();
        const pdfHeight = pdf.internal.pageSize.getHeight();
        const imgRatio = canvas.width / canvas.height;
        let drawWidth = pdfWidth - 10;
        let drawHeight = drawWidth / imgRatio;
        if (drawHeight > pdfHeight - 10) {
          drawHeight = pdfHeight - 10;
          drawWidth = drawHeight * imgRatio;
        }
        const x = (pdfWidth - drawWidth) / 2;
        const y = (pdfHeight - drawHeight) / 2;

        pdf.addImage(imgData, 'PNG', x, y, drawWidth, drawHeight);
        pdf.save('Cheque_VP_<?= $paymentId ?>_<?= date('Ymd', strtotime($date ?: 'now')) ?>.pdf');
      } catch (e) {
        console.error('PDF generation failed:', e);
        alert('Failed to generate PDF. Please try printing instead.');
      } finally {
        btnPdf.disabled = false;
        btnPdf.innerHTML = orig;
      }
    });
  }

  /* ─── Print Cheque ─── */
  const btnPrint = document.getElementById('btnPrintCheque');
  if (btnPrint) {
    btnPrint.addEventListener('click', () => {
      const chequeEl = document.getElementById('chequePreviewWrap');
      if (!chequeEl) return;

      const printWin = window.open('', '_blank', 'width=800,height=500');
      printWin.document.write(`
        <!DOCTYPE html>
        <html><head><title>Cheque - VP #<?= $paymentId ?></title>
        <style>
          * { margin:0; padding:0; box-sizing:border-box; }
          body { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#fff; }
          @media print {
            @page { size:landscape; margin:5mm; }
            body { min-height:auto; }
          }
        </style></head>
        <body>${chequeEl.innerHTML}</body></html>
      `);
      printWin.document.close();
      setTimeout(() => { printWin.focus(); printWin.print(); }, 400);
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
  <?php endif; ?>
})();
</script>
<?= $this->endSection() ?>
