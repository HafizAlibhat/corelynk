<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Trial Balance<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-3">
  <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <div class="d-flex align-items-center gap-2">
      <div class="section-icon section-accent-amber"><i class="bi bi-balance-scale"></i></div>
      <div>
        <h4 class="mb-0">Trial Balance</h4>
        <div class="small text-muted">Range: <?= esc($from) ?> to <?= esc($to) ?></div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#tbFiltersOld" aria-expanded="false"><i class="bi bi-funnel"></i> Filters</button>
      <button type="button" class="btn btn-sm btn-outline-secondary" onclick="exportCsv()"><i class="bi bi-file-earmark-spreadsheet"></i></button>
      <button type="button" class="btn btn-sm btn-outline-dark" onclick="window.print()"><i class="bi bi-printer"></i></button>
    </div>
  </div>
  <div id="tbFiltersOld" class="collapse mb-2">
    <form class="d-flex flex-wrap gap-2 filters-panel" method="get" action="">
      <input type="date" name="from" value="<?= esc($from) ?>" class="form-control form-control-sm" />
      <input type="date" name="to" value="<?= esc($to) ?>" class="form-control form-control-sm" />
      <select name="type" class="form-select form-select-sm" onchange="filterType(this.value)">
        <option value="">All Types</option>
        <?php $types = array_unique(array_map(fn($r)=>$r['type'], $rows)); sort($types); foreach($types as $t): ?>
          <option value="<?= esc($t) ?>" <?= (($_GET['type']??'')===$t)?'selected':'' ?>><?= esc($t) ?></option>
        <?php endforeach; ?>
      </select>
      <input type="search" id="tbSearch" placeholder="Search account/code" class="form-control form-control-sm" oninput="filterSearch()" />
      <button class="btn btn-sm btn-primary" type="submit"><i class="bi bi-funnel me-1"></i> Apply</button>
    </form>
  </div>

  <?php $totalDebits = 0; $totalCredits = 0; foreach($rows as $r){$totalDebits += (float)$r['debits']; $totalCredits += (float)$r['credits'];} $diff=$totalDebits-$totalCredits; ?>
  <!-- Summary Band -->
  <div class="row g-2 mb-2 collapse" id="tbSummaryOld">
    <div class="col-md-3">
      <div class="summary-tile shadow-sm">
        <div class="label">Total Debits</div>
        <div class="value text-primary">₨ <?= number_format($totalDebits,2) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="summary-tile shadow-sm">
        <div class="label">Total Credits</div>
        <div class="value text-danger">₨ <?= number_format($totalCredits,2) ?></div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="summary-tile shadow-sm">
        <div class="label">Accounts</div>
        <div class="value"><?= count($rows) ?></div>
        <span class="sub muted">Types: <?= count($types) ?></span>
      </div>
    </div>
    <div class="col-md-3">
      <div class="summary-tile shadow-sm <?= abs($diff) < 0.005 ? 'balanced' : 'unbalanced' ?>">
        <div class="label">Status</div>
        <div class="value"><?= abs($diff) < 0.005 ? '✓ Balanced' : '⚠ Unbalanced' ?></div>
        <span class="sub <?= abs($diff) < 0.005 ? 'text-success' : 'text-danger' ?>">Diff: <?= number_format($diff,2) ?></span>
      </div>
    </div>
  </div>

  <!-- Table -->
  <div class="card overflow-hidden">
    <div class="table-responsive trial-balance-table">
      <table class="table table-sm table-compact align-middle mb-0" id="trialBalanceTable">
        <thead class="table-light">
          <tr>
            <th class="text-muted" style="width:110px;">Code</th>
            <th>Account Name</th>
            <th style="width:130px;">Type</th>
            <th class="text-end" style="width:140px;">Debits (₨)</th>
            <th class="text-end" style="width:140px;">Credits (₨)</th>
          </tr>
        </thead>
        <tbody id="tbBody">
          <?php foreach($rows as $r): ?>
            <tr data-type="<?= esc($r['type']) ?>">
              <td class="font-monospace fw-semibold text-primary"><?= esc($r['code']) ?></td>
              <td><?= esc($r['name']) ?></td>
              <td><span class="badge bg-dark-subtle text-dark fw-normal"><?= esc($r['type']) ?></span></td>
              <td class="text-end font-monospace"><?= number_format((float)$r['debits'],2) ?></td>
              <td class="text-end font-monospace"><?= number_format((float)$r['credits'],2) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
          <tr class="fw-semibold">
            <td colspan="3" class="text-end">Totals</td>
            <td class="text-end font-monospace"><?= number_format($totalDebits,2) ?></td>
            <td class="text-end font-monospace"><?= number_format($totalCredits,2) ?></td>
          </tr>
          <tr>
            <td colspan="3" class="text-end">Difference</td>
            <td colspan="2" class="text-end">
              <span class="badge <?= abs($diff) < 0.005 ? 'bg-success' : 'bg-danger' ?> px-3 py-2"><?= number_format($diff,2) ?></span>
            </td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
  <div class="mt-3 d-flex gap-2 flex-wrap">
    <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text me-1"></i> Journals</a>
    <a href="<?= base_url('accounting/accounts') ?>" class="btn btn-outline-secondary btn-sm"><i class="bi bi-folder me-1"></i> Chart of Accounts</a>
    <button type="button" class="btn btn-outline-dark btn-sm" onclick="scrollTop()"><i class="bi bi-arrow-up-short"></i> Top</button>
  </div>
</div>

<script>
function filterType(type){
  document.querySelectorAll('#tbBody tr').forEach(tr => {
    if(!type || tr.dataset.type === type){ tr.style.display=''; } else { tr.style.display='none'; }
  });
}
function filterSearch(){
  const q = (document.getElementById('tbSearch').value||'').toLowerCase();
  document.querySelectorAll('#tbBody tr').forEach(tr => {
    const text = tr.textContent.toLowerCase();
    tr.style.display = text.includes(q) ? '' : 'none';
  });
}
function exportCsv(){
  const rows = [['Code','Name','Type','Debits','Credits']];
  document.querySelectorAll('#tbBody tr').forEach(tr => {
    if(tr.style.display==='none') return;
    const tds = tr.querySelectorAll('td');
    rows.push([tds[0].innerText,tds[1].innerText,tds[2].innerText.replace(/\n/g,' ').trim(),tds[3].innerText,tds[4].innerText]);
  });
  const csv = rows.map(r=>r.map(f=>`"${f.replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv],{type:'text/csv'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='trial_balance.csv'; a.click();
}
</script>

<style>
.summary-tile{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:10px 14px;position:relative;overflow:hidden}
.summary-tile:before{content:'';position:absolute;inset:0;background:linear-gradient(135deg,#f8fafc,#eef2f7);opacity:.5}
.summary-tile .label{font-size:.70rem;text-transform:uppercase;letter-spacing:.5px;color:#64748b;font-weight:600}
.summary-tile .value{font-size:1rem;font-weight:600;position:relative}
.summary-tile .sub{font-size:.65rem;position:relative}
.summary-tile.balanced{border-color:#198754}
.summary-tile.unbalanced{border-color:#dc3545}
.trial-balance-table thead th{position:sticky;top:0;z-index:5}
@media (max-width: 767px){
  .summary-tile{margin-bottom:6px}
  table.table-sm td, table.table-sm th{padding:.4rem .5rem}
}
@media print{.summary-tile,form,.btn, #tbSearch {display:none} .trial-balance-table thead th{background:#fff !important}}
</style>
<?= $this->endSection() ?>
