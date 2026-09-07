<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Journal Entry #<?= (int)($id ?? 0) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  // Ensure voucher date is always available/formatted
  $voucherDate = '';
  $ccy = strtoupper(trim((string)($currency_code ?? 'PKR')));
  $symbolMap = ['USD' => '$', 'PKR' => 'Rs', 'EUR' => 'EUR', 'GBP' => 'GBP', 'AED' => 'AED', 'SAR' => 'SAR'];
  $ccySymbol = $symbolMap[$ccy] ?? $ccy;
  if (!empty($date_display ?? '')) {
    $voucherDate = (string)$date_display;
  } elseif (!empty($date ?? '')) {
    $voucherDate = date('d-m-Y', strtotime((string)$date));
  } elseif (!empty($entry_date ?? '')) {
    $voucherDate = date('d-m-Y', strtotime((string)$entry_date));
  }
?>
<div class="container-fluid">
  <style>
    /* Dark-friendly header polish (scoped to this page) */
    .modern-card { background: rgba(13, 23, 38, 0.78); border: 1px solid rgba(255,255,255,0.06); box-shadow: 0 10px 30px rgba(0,0,0,0.25); }
    .compact-header { background: linear-gradient(90deg, rgba(17,29,49,0.92), rgba(10,20,35,0.72)); border-bottom: 1px solid rgba(255,255,255,0.06); }
    .compact-header h6 { color: #eaf2ff; letter-spacing: 0.2px; }
    .compact-header small { color: rgba(234,242,255,0.68) !important; }
    .header-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display:flex; align-items:center; justify-content:center;
      background: radial-gradient(circle at 30% 30%, rgba(76, 141, 255, 0.35), rgba(13,110,253,0.12));
      border: 1px solid rgba(90, 150, 255, 0.25);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.08), 0 10px 18px rgba(0,0,0,0.25);
      color: #eaf2ff;
    }
    .header-icon i{ font-size: 20px; }
    .compact-header .btn { border-color: rgba(255,255,255,0.18); color: rgba(234,242,255,0.90); }
    .compact-header .btn:hover { background: rgba(13,110,253,0.14); border-color: rgba(13,110,253,0.45); color:#fff; }

    /* Table header contrast for dark theme */
    .table-light { --bs-table-bg: rgba(255,255,255,0.06); --bs-table-color: #eaf2ff; }
    .table.table-compact th { font-weight:700; letter-spacing:0.2px; border-bottom-color: rgba(255,255,255,0.08); }
    .table.table-compact td { border-bottom-color: rgba(255,255,255,0.06); }
  </style>
  <div class="row">
    <div class="col-12">
      <div class="card modern-card">
        <div class="card-header compact-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <div class="header-icon"><i class="bi bi-journal-text"></i></div>
            <div>
              <h6 class="mb-0">Journal Entry #<?= (int)($id ?? 0) ?></h6>
              <small class="text-muted">Detailed view</small>
            </div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('accounting/journals') ?>"><i class="bi bi-arrow-left"></i> Back</a>
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="<?= base_url('accounting/journals/receipt/' . (int)($id ?? 0)) ?>"><i class="bi bi-receipt"></i> Voucher</a>
          </div>
        </div>

        <div class="card-body">
          <?php if(session()->getFlashdata('error')): ?>
            <div class="alert alert-danger modern-alert py-2">
              <i class="bi bi-exclamation-triangle me-2"></i><?= esc(session()->getFlashdata('error')) ?>
            </div>
          <?php endif; ?>
          <?php if(session()->getFlashdata('success')): ?>
            <div class="alert alert-success modern-alert py-2">
              <i class="bi bi-check-circle me-2"></i><?= esc(session()->getFlashdata('success')) ?>
            </div>
          <?php endif; ?>

          <div class="row g-2" style="margin-bottom:6px;">
            <div class="col-md-3">
              <div class="small text-muted">Voucher No</div>
              <div class="fw-semibold font-monospace">JV-<?= (int)($id ?? 0) ?></div>
            </div>
            <div class="col-md-3">
              <div class="small text-muted">Voucher Date</div>
              <div class="fw-semibold"><?= esc($voucherDate ?: '-') ?></div>
            </div>
            <div class="col-md-6">
              <div id="attachments" class="text-end">
                <?php $attCountHeader = is_array($attachments ?? null) ? count($attachments) : 0; ?>
                <div class="small text-muted mb-0">Attachments<?= $attCountHeader ? ' (' . (int)$attCountHeader . ')' : '' ?></div>
                <?php if (empty($attachments)): ?>
                  <div class="text-muted" style="line-height:1.1; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">No attachments uploaded.</div>
                <?php else: ?>
                  <?php $attCount = is_array($attachments) ? count($attachments) : 0; ?>
                  <style>
                    /* Attachments: compact summary + modal list */
                    .att-summary { display:flex; justify-content:flex-end; align-items:center; gap:10px; margin-top:6px; flex-wrap:wrap; }
                    /* removed separate total count badge to keep UI clean */
                    .att-items { display:flex; align-items:center; gap:8px; }
                    .att-item { display:inline-flex; align-items:center; gap:8px; padding:6px 10px; background:rgba(0,0,0,0.08); border:1px solid rgba(255,255,255,0.08); border-radius:12px; }
                    .att-item .num { display:inline-flex; width:22px; height:22px; border-radius:7px; align-items:center; justify-content:center; font-size:12px; font-weight:900; background:rgba(13,110,253,0.22); color:#0d6efd; }
                    .att-actions { display:inline-flex; gap:8px; }
                    .att-icon-btn { display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border-radius:9px; border:1px solid rgba(255,255,255,0.14); background:rgba(0,0,0,0.10); color:inherit; text-decoration:none; }
                    .att-icon-btn:hover { background:rgba(13,110,253,0.20); border-color:rgba(13,110,253,0.45); }
                    .att-more { font-weight:600; font-size:0.9rem; }
                    .att-more a { text-decoration:none; }
                    .att-modal-list { max-height:60vh; overflow:auto; }
                    .att-row { display:flex; align-items:center; justify-content:space-between; gap:14px; padding:10px 12px; border-bottom:1px solid rgba(0,0,0,0.08); }
                    .att-row .left { display:flex; align-items:center; gap:10px; min-width:0; }
                    .att-row .fname { font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:520px; }
                    .att-row .meta { font-size:12px; color:#6c757d; }
                    @media (max-width: 576px) {
                      .att-row .fname { max-width:220px; }
                    }
                  </style>

                  <div class="att-summary" aria-label="Attachments summary">
                    <div class="att-items">
                      <?php foreach (array_slice($attachments, 0, 3) as $i => $a): ?>
                        <?php
                          $filePath = (string)($a['file_path'] ?? '');
                          $url = base_url(ltrim($filePath, '/'));
                          $name = (string)($a['original_name'] ?? 'Attachment');
                          $fileOnDisk = FCPATH . ltrim($filePath, '/');
                          $exists = file_exists($fileOnDisk);
                          $mime = strtolower((string)($a['mime_type'] ?? ''));
                          $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                          $isImg = (str_starts_with($mime, 'image/') || in_array($ext, ['jpg','jpeg','png','gif','webp'], true));
                        ?>
                        <span class="att-item" title="<?= esc($name) ?>">
                          <span class="num"><?= (int)($i + 1) ?></span>
                          <span class="att-actions">
                            <?php if ($exists): ?>
                              <?php if ($isImg): ?>
                                <button type="button" class="att-icon-btn js-att-preview" data-url="<?= esc($url) ?>" data-name="<?= esc($name) ?>" title="View"><i class="bi bi-eye"></i></button>
                              <?php else: ?>
                                <a class="att-icon-btn" href="<?= esc($url) ?>" target="_blank" title="View"><i class="bi bi-eye"></i></a>
                              <?php endif; ?>
                              <a class="att-icon-btn" href="<?= esc($url) ?>" download title="Download"><i class="bi bi-download"></i></a>
                            <?php else: ?>
                              <span class="badge bg-danger">Missing</span>
                            <?php endif; ?>
                          </span>
                        </span>
                      <?php endforeach; ?>

                      <?php if ($attCount > 3): ?>
                        <span class="att-more"><a href="#" data-bs-toggle="modal" data-bs-target="#attachmentsModal">View more</a></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <!-- Attachments modal: full list with view/download icons -->
                  <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                      <div class="modal-content">
                        <div class="modal-header">
                          <h5 class="modal-title">Attachments (<?= (int)$attCount ?>)</h5>
                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                          <div class="att-modal-list">
                            <?php foreach ($attachments as $a): ?>
                              <?php
                                $filePath = (string)($a['file_path'] ?? '');
                                $url = base_url(ltrim($filePath, '/'));
                                $name = (string)($a['original_name'] ?? 'Attachment');
                                $mime = strtolower((string)($a['mime_type'] ?? ''));
                                $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
                                $isImg = (str_starts_with($mime, 'image/') || in_array($ext, ['jpg','jpeg','png','gif','webp'], true));
                                $isPdf = ($mime === 'application/pdf' || $ext === 'pdf');
                                $fileOnDisk = FCPATH . ltrim($filePath, '/');
                                $exists = file_exists($fileOnDisk);
                                $sizeLabel = '';
                                if ($exists) {
                                  $sz = filesize($fileOnDisk);
                                  if ($sz >= 1048576) $sizeLabel = round($sz/1048576,2) . ' MB';
                                  elseif ($sz >= 1024) $sizeLabel = round($sz/1024,2) . ' KB';
                                  else $sizeLabel = $sz . ' B';
                                }
                              ?>
                              <div class="att-row">
                                <div class="left">
                                  <?php if ($isPdf): ?>
                                    <i class="bi bi-file-earmark-pdf" style="font-size:18px; color:#c62828;"></i>
                                  <?php elseif ($isImg): ?>
                                    <i class="bi bi-card-image" style="font-size:18px;"></i>
                                  <?php else: ?>
                                    <i class="bi bi-paperclip" style="font-size:16px;"></i>
                                  <?php endif; ?>
                                  <div style="min-width:0;">
                                    <div class="fname" title="<?= esc($name) ?>"><?= esc($name) ?></div>
                                    <div class="meta"><?= esc($sizeLabel ?: '') ?></div>
                                  </div>
                                </div>
                                <div class="att-actions">
                                  <?php if ($exists): ?>
                                    <?php if ($isImg): ?>
                                      <button type="button" class="att-icon-btn js-att-preview" data-url="<?= esc($url) ?>" data-name="<?= esc($name) ?>" title="View"><i class="bi bi-eye"></i></button>
                                    <?php else: ?>
                                      <a class="att-icon-btn" href="<?= esc($url) ?>" target="_blank" title="View"><i class="bi bi-eye"></i></a>
                                    <?php endif; ?>
                                    <a class="att-icon-btn" href="<?= esc($url) ?>" download title="Download"><i class="bi bi-download"></i></a>
                                  <?php else: ?>
                                    <span class="badge bg-danger">Missing</span>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          </div>
                        </div>
                        <div class="modal-footer">
                          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div class="modal fade" id="attachmentPreviewModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-centered">
                      <div class="modal-content" style="background:#0b1220;border:1px solid rgba(255,255,255,.12)">
                        <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.12)">
                          <h5 class="modal-title" id="attachmentPreviewTitle">Attachment Preview</h5>
                          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body text-center" style="max-height:80vh;overflow:auto;">
                          <img id="attachmentPreviewImage" src="" alt="Attachment preview" style="max-width:100%;height:auto;border-radius:8px;" />
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <hr class="my-2">

          <div class="table-responsive">
            <table class="table table-sm table-compact align-middle">
              <thead class="table-light">
                <tr>
                  <th>Account</th>
                  <th>Description</th>
                  <th class="text-end">Debit (<?= esc($ccy) ?> <?= esc($ccySymbol) ?>)</th>
                  <th class="text-end">Credit (<?= esc($ccy) ?> <?= esc($ccySymbol) ?>)</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (($lines ?? []) as $ln): ?>
                  <tr>
                    <td class="text-truncate">
                      <span class="fw-semibold font-monospace text-primary"><?= esc($ln['account_code'] ?? '') ?></span>
                      <span class="text-body">— <?= esc($ln['account_name'] ?? '') ?></span>
                    </td>
                    <td class="text-truncate"><?= esc($ln['description'] ?? '') ?></td>
                    <td class="text-end font-monospace"><?= number_format((float)($ln['debit'] ?? 0), 2) ?></td>
                    <td class="text-end font-monospace"><?= number_format((float)($ln['credit'] ?? 0), 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr class="table-secondary fw-semibold">
                  <td colspan="2" class="text-end">Totals</td>
                  <td class="text-end font-monospace"><?= number_format((float)($sum_debit ?? 0), 2) ?></td>
                  <td class="text-end font-monospace"><?= number_format((float)($sum_credit ?? 0), 2) ?></td>
                </tr>
              </tfoot>
            </table>
          </div>

<?php
  $nc = $narrative_context ?? [];
  $hasContext = !empty($nc);
?>

<?php if ($hasContext): ?>
<style>
  .jn-card { border-radius:.55rem; border:1px solid rgba(37,99,235,.18); background:rgba(37,99,235,.05); padding:1rem 1.1rem; margin-top:.75rem; }
  .jn-title { font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; color:#2563eb; margin-bottom:.55rem; }
  .jn-story { font-size:.93rem; font-weight:600; color:var(--bs-body-color,#e2e8f0); line-height:1.55; margin-bottom:.7rem; }
  .jn-pills { display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:.6rem; }
  .jn-pill { display:inline-flex; align-items:center; gap:.3rem; padding:3px 9px; border-radius:20px; font-size:.73rem; font-weight:600; background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.1); }
  .jn-pill .pi { font-size:.78rem; }
  .jn-lines-simple { width:100%; border-collapse:collapse; margin-top:.5rem; }
  .jn-lines-simple th { font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:#94a3b8; padding:.25rem .5rem; border-bottom:1px solid rgba(255,255,255,.08); }
  .jn-lines-simple td { padding:.3rem .5rem; font-size:.82rem; border-bottom:1px solid rgba(255,255,255,.05); }
  .jn-dr { color:#16a34a; font-weight:700; }
  .jn-cr { color:#dc2626; font-weight:700; }
  .jn-amount-words { font-family:'Courier New',monospace; background:#2f3640; color:#fff; padding:6px 10px; border-radius:6px; font-size:.78rem; }
</style>
<div class="jn-card">
  <div class="jn-title"><i class="bi bi-chat-text me-1"></i>What happened? — Plain English Narration</div>
  <div class="jn-story"><?= esc($nc['human_narration'] ?? ($description ?? '')) ?></div>

  <div class="jn-pills">
    <?php if (!empty($nc['customer_name'])): ?>
      <span class="jn-pill"><i class="bi bi-person pi"></i> <?= esc($nc['customer_name']) ?></span>
    <?php endif; ?>
    <?php if (!empty($nc['amount_display'])): ?>
      <span class="jn-pill"><i class="bi bi-currency-exchange pi"></i> <?= esc($nc['amount_display']) ?></span>
    <?php endif; ?>
    <?php if (!empty($nc['payment_method'])): ?>
      <span class="jn-pill"><i class="bi bi-credit-card pi"></i> <?= esc($nc['payment_method']) ?></span>
    <?php endif; ?>
    <?php if (!empty($nc['payment_date'])): ?>
      <span class="jn-pill"><i class="bi bi-calendar pi"></i> <?= esc($nc['payment_date']) ?></span>
    <?php endif; ?>
    <?php if (!empty($nc['receiving_account'])): ?>
      <span class="jn-pill"><i class="bi bi-bank pi"></i> <?= esc($nc['receiving_account']) ?></span>
    <?php endif; ?>
    <?php foreach (($nc['invoice_numbers'] ?? []) as $inv): ?>
      <span class="jn-pill" style="border-color:rgba(37,99,235,.3);color:#2563eb"><i class="bi bi-receipt pi"></i> <?= esc($inv) ?></span>
    <?php endforeach; ?>
    <?php if (!empty($nc['reference'])): ?>
      <span class="jn-pill"><i class="bi bi-hash pi"></i> Ref: <?= esc($nc['reference']) ?></span>
    <?php endif; ?>
  </div>

  <table class="jn-lines-simple">
    <thead><tr><th>What was affected</th><th>Plain meaning</th><th class="text-end">Debit (Money In)</th><th class="text-end">Credit (Money Out / Reduced)</th></tr></thead>
    <tbody>
    <?php foreach (($lines ?? []) as $ln):
      $dr = (float)($ln['debit'] ?? 0);
      $cr = (float)($ln['credit'] ?? 0);
      if ($dr <= 0 && $cr <= 0) continue;
      $aname = $ln['account_name'] ?? '';
      $atype = strtolower($ln['account_type'] ?? '');
      if ($dr > 0) {
        if (stripos($aname, 'receivable') !== false) $meaning = 'Customer owed us less (invoice settled)';
        elseif ($atype === 'asset') $meaning = 'Money received into ' . $aname;
        elseif ($atype === 'liability') $meaning = 'Customer advance reduced';
        else $meaning = 'Debit entry';
      } else {
        if (stripos($aname, 'receivable') !== false) $meaning = 'Outstanding invoice reduced (customer paid)';
        elseif ($atype === 'asset') $meaning = 'Money moved out of ' . $aname;
        elseif ($atype === 'liability') $meaning = 'Customer advance received (pre-payment)';
        else $meaning = 'Credit entry';
      }
    ?>
      <tr>
        <td><span style="font-size:.72rem;color:#94a3b8"><?= esc(($ln['account_code'] ?? '')) ?></span> <?= esc($aname) ?></td>
        <td style="color:#94a3b8;font-size:.8rem"><?= esc($meaning) ?></td>
        <td class="text-end jn-dr"><?= $dr > 0 ? (esc($ccy) . ' ' . esc($ccySymbol) . ' ' . number_format($dr, 2)) : '—' ?></td>
        <td class="text-end jn-cr"><?= $cr > 0 ? (esc($ccy) . ' ' . esc($ccySymbol) . ' ' . number_format($cr, 2)) : '—' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
    <span class="small text-muted">Amount in words:</span>
    <span class="jn-amount-words"><?= esc($amount_words ?? $amount_in_words ?? '') ?></span>
    <span class="badge bg-success ms-2"><?= esc($ccy) ?> <?= esc($ccySymbol) ?> <?= number_format((float)($amount ?? 0), 2) ?></span>
  </div>
</div>
<?php else: ?>
<div class="d-flex align-items-start justify-content-between gap-3 mt-2 mb-2 flex-wrap">
  <div>
    <div class="small text-muted mb-1">Narration</div>
    <div><?= esc($description ?? '') ?></div>
  </div>
  <div class="text-end">
    <span class="badge bg-success"><?= esc($ccy) ?> <?= esc($ccySymbol) ?> <?= number_format((float)($amount ?? 0), 2) ?></span>
    <div class="mt-1" style="font-family:'Courier New',monospace;background:#2f3640;color:#fff;padding:6px 10px;border-radius:6px;font-size:.78rem"><?= esc($amount_words ?? $amount_in_words ?? '') ?></div>
  </div>
</div>
<?php endif; ?>

          <hr>

          <!-- Rectangle 2: Narration band under table removed as requested -->

          <!-- Attachment preview removed: view opens in new tab; full list is in Attachments modal above -->
        </div>
      </div>

    </div>
  </div>
</div>

<script>
(() => {
  const previewModalEl = document.getElementById('attachmentPreviewModal');
  if (!previewModalEl || typeof bootstrap === 'undefined') return;

  const previewModal = new bootstrap.Modal(previewModalEl);
  const previewImg = document.getElementById('attachmentPreviewImage');
  const previewTitle = document.getElementById('attachmentPreviewTitle');

  document.querySelectorAll('.js-att-preview').forEach((btn) => {
    btn.addEventListener('click', () => {
      const url = btn.getAttribute('data-url') || '';
      const name = btn.getAttribute('data-name') || 'Attachment Preview';
      if (!url || !previewImg) return;
      previewImg.src = url;
      if (previewTitle) previewTitle.textContent = name;
      previewModal.show();
    });
  });

  previewModalEl.addEventListener('hidden.bs.modal', () => {
    if (previewImg) previewImg.src = '';
  });
})();
</script>
<?= $this->endSection() ?>
