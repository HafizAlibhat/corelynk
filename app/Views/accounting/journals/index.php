<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Journals<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
  $symbolMap = ['USD' => '$', 'PKR' => 'Rs', 'EUR' => 'EUR', 'GBP' => 'GBP', 'AED' => 'AED', 'SAR' => 'SAR'];
?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card modern-card">
        <div class="card-header compact-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-2">
            <div class="header-icon"><i class="bi bi-journal-text"></i></div>
            <div>
              <h6 class="mb-0">Journal Entries</h6>
              <small class="text-muted">Transaction history</small>
            </div>
          </div>
          <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="<?= base_url('accounting/journal-lite') ?>">
              <i class="bi bi-plus-circle"></i> New Entry
            </a>
            <a class="btn btn-sm btn-outline-primary" href="<?= base_url('accounting/trial-balance') ?>">
              <i class="bi bi-calculator"></i> Trial Balance
            </a>
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
          
          <?php if (empty($entries)): ?>
            <div class="empty-state">
              <div class="empty-icon"><i class="bi bi-journal-x"></i></div>
              <h5 class="empty-title">No journal entries yet</h5>
              <p class="empty-text">Start by creating your first journal entry to record transactions</p>
              <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create First Entry
              </a>
            </div>
          <?php else: ?>
              <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex gap-2">
                  <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#journalFilters" aria-expanded="false" aria-controls="journalFilters"><i class="bi bi-funnel"></i> Filters</button>
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-sm btn-outline-dark" onclick="exportJournalsCsv()" title="Export CSV"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                  <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()" title="Print"><i class="bi bi-printer"></i></button>
                </div>
              </div>
              <div id="journalFilters" class="collapse mb-2">
                <div class="d-flex flex-wrap align-items-center gap-2 filter-bar p-2">
                  <input type="date" id="filterFrom" class="form-control form-control-sm" title="From date">
                  <input type="date" id="filterTo" class="form-control form-control-sm" title="To date">
                  <input type="search" id="filterSearch" class="form-control form-control-sm" placeholder="Search memo / account code" style="min-width:220px">
                  <input type="number" step="0.01" id="filterMin" class="form-control form-control-sm" placeholder="Min amount" style="width:130px">
                  <select id="filterBalanced" class="form-select form-select-sm" style="width:130px">
                    <option value="">All</option>
                    <option value="balanced">Balanced</option>
                    <option value="unbalanced">Unbalanced</option>
                  </select>
                  <div class="ms-auto d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="resetJournalFilters()"><i class="bi bi-x-circle"></i> Reset</button>
                  </div>
                </div>
              </div>
              <div class="modern-table-container position-relative">
                <div id="filteredCount" class="position-absolute top-0 end-0 mt-1 me-2 small text-muted"></div>
              <table class="table table-sm modern-table table-compact mb-0">
                <thead>
                  <tr>
                    <th width="80" class="text-center">ID</th>
                    <th width="120">Date</th>
                    <th>Description</th>
                    <th width="140" class="text-end">Amount</th>
                    <th width="120" class="text-center">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($entries as $e): ?>
                    <?php
                      $balanced = abs(($e['total_debits'] ?? 0) - ($e['total_credits'] ?? 0)) < 0.01;
                      $amount = (float)($e['total_debits'] ?? 0);
                      $ccy = strtoupper(trim((string)($e['currency_code'] ?? 'PKR')));
                      $sym = $symbolMap[$ccy] ?? $ccy;
                    ?>
                    <tr class="journal-row" data-entry-id="<?= (int)$e['id'] ?>" data-date="<?= esc($e['entry_date']) ?>" data-amount="<?= $amount ?>" data-balanced="<?= $balanced ? 'balanced' : 'unbalanced' ?>" data-memo="<?= esc(strtolower($e['memo'] ?: 'journal entry')) ?>">
                      <td class="text-center">
                        <span class="entry-id-badge">#<?= (int)$e['id'] ?></span>
                      </td>
                      <td>
                        <div class="date-display">
                          <div class="date-main"><?= date('M d', strtotime($e['entry_date'])) ?></div>
                          <div class="date-year"><?= date('Y', strtotime($e['entry_date'])) ?></div>
                        </div>
                      </td>
                      <td>
                        <div class="entry-description">
                          <strong class="desc-main"><?= esc($e['memo'] ?: 'Journal Entry') ?></strong>
                          <?php if ($e['memo']): ?>
                            <small class="desc-sub">Double-entry transaction</small>
                          <?php endif; ?>
                          <?php if (!empty($e['chain_note'])): ?>
                            <div class="mt-1" style="font-size:.72rem;color:#94a3b8;">
                              <i class="bi bi-link-45deg"></i> <?= esc($e['chain_note']) ?>
                            </div>
                          <?php endif; ?>
                          <?php if (!empty($e['chain_journals']) && is_array($e['chain_journals'])): ?>
                            <div class="mt-1 d-flex flex-wrap gap-1">
                              <?php foreach (array_slice(array_values(array_unique(array_map('intval', $e['chain_journals']))), 0, 4) as $jid): ?>
                                <?php if ($jid > 0 && $jid !== (int)$e['id']): ?>
                                  <a href="<?= base_url('accounting/journals/view/' . $jid) ?>" class="badge text-decoration-none" style="background:rgba(37,99,235,.16);color:#93c5fd;border:1px solid rgba(37,99,235,.35);">Related JE #<?= (int)$jid ?></a>
                                <?php endif; ?>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td class="text-end">
                        <div class="amount-display">
                          <span class="amount-value"><?= esc($ccy) ?> <?= esc($sym) ?> <?= number_format((float)($e['total_debits'] ?? 0), 2) ?></span>
                          <div class="balance-indicator">
                            <span class="balance-status <?= $balanced ? 'balanced' : 'unbalanced' ?>">
                              <i class="bi bi-<?= $balanced ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                              <?= $balanced ? 'Balanced' : 'Unbalanced' ?>
                            </span>
                          </div>
                        </div>
                      </td>
                      <td class="actions-col text-end">
                        <div class="actions-group">
                          <button type="button" class="btn btn-icon btn-outline-primary btn-toggle-lines modern-btn" 
                                  data-bs-toggle="collapse" data-bs-target="#lines-<?= (int)$e['id'] ?>" 
                                  aria-expanded="false" aria-controls="lines-<?= (int)$e['id'] ?>">
                            <i class="bi bi-caret-down-fill"></i>
                          </button>
                          <a href="<?= base_url('accounting/journals/view/'.(int)$e['id']) ?>" class="btn btn-icon btn-outline-primary" title="View Details">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="<?= base_url('accounting/journals/receipt/'.(int)$e['id']) ?>" class="btn btn-icon btn-outline-secondary" title="View Receipt" target="_blank">
                            <i class="bi bi-receipt"></i>
                          </a>
                          <a href="<?= base_url('accounting/journals/view/'.(int)$e['id']).'#attachments' ?>" class="btn btn-icon btn-outline-secondary" title="Attachments">
                            <i class="bi bi-paperclip"></i>
                          </a>
                        </div>
                      </td>
                    </tr>
                    <tr class="collapse lines-row" id="lines-<?= (int)$e['id'] ?>">
                      <td colspan="5" class="lines-container">
                        <?php
                          try {
                            $db = \Config\Database::connect();
                            $lines = $db->query('SELECT jl.*, a.code AS account_code, a.name AS account_name, a.type AS account_type FROM journal_lines jl JOIN accounts a ON a.id = jl.account_id WHERE jl.entry_id = ? ORDER BY jl.debit DESC', [(int)$e['id']])->getResultArray();
                          } catch (\Throwable $ex) { $lines = []; }
                          $sumDebit = 0.0; $sumCredit = 0.0; foreach($lines as $__r){ $sumDebit += (float)($__r['debit'] ?? 0); $sumCredit += (float)($__r['credit'] ?? 0); }
                          $diff = $sumDebit - $sumCredit; $balancedLines = abs($diff) < 0.01;
                        ?>
                        <?php if (empty($lines)): ?>
                          <div class="no-lines-message">
                            <i class="bi bi-info-circle me-2"></i>No line items found for this entry
                          </div>
                        <?php else: ?>
                          <div class="d-flex align-items-center justify-content-between mb-1 small text-muted">
                            <div>Totals: Debits <?= esc($ccy) ?> <?= esc($sym) ?> <?= number_format($sumDebit,2) ?> • Credits <?= esc($ccy) ?> <?= esc($sym) ?> <?= number_format($sumCredit,2) ?> • <?= $balancedLines ? 'Balanced' : ('Diff '.number_format($diff,2)) ?></div>
                            <div class="d-flex gap-1">
                              <button class="btn btn-xs btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#lineFilters-<?= (int)$e['id'] ?>" aria-expanded="false" aria-controls="lineFilters-<?= (int)$e['id'] ?>"><i class="bi bi-funnel"></i> Filters</button>
                              <button type="button" class="btn btn-xs btn-outline-dark" id="linesCsv-<?= (int)$e['id'] ?>" title="Export CSV"><i class="bi bi-file-earmark-spreadsheet"></i></button>
                            </div>
                          </div>
                          <div id="lineFilters-<?= (int)$e['id'] ?>" class="collapse mb-2">
                            <div class="d-flex flex-wrap align-items-center gap-2 p-2 border rounded-2">
                              <input type="search" id="linesSearch-<?= (int)$e['id'] ?>" class="form-control form-control-sm" placeholder="Search account/desc" style="min-width:180px">
                              <select id="linesType-<?= (int)$e['id'] ?>" class="form-select form-select-sm" style="width:140px">
                                <option value="">All types</option>
                                <?php foreach(['Asset','Liability','Equity','Revenue','Expense'] as $__t): ?><option value="<?= $__t ?>"><?= $__t ?></option><?php endforeach; ?>
                              </select>
                              <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="linesReset-<?= (int)$e['id'] ?>"><i class="bi bi-x-circle"></i> Reset</button>
                            </div>
                          </div>
                          <div class="lines-table-container" data-entry="<?= (int)$e['id'] ?>">
                            <table class="table table-sm table-compact lines-table mb-0" id="linesTable-<?= (int)$e['id'] ?>">
                              <thead>
                                <tr>
                                  <th style="width:260px">Account</th>
                                  <th>Description</th>
                                  <th width="120" class="text-end">Debit</th>
                                  <th width="120" class="text-end">Credit</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($lines as $l): ?>
                                  <tr class="line-item" data-type="<?= esc($l['account_type']) ?>" data-code="<?= esc($l['account_code']) ?>" data-name="<?= esc($l['account_name']) ?>" data-desc="<?= esc(strtolower($l['description'] ?? '')) ?>" data-debit="<?= (float)$l['debit'] ?>" data-credit="<?= (float)$l['credit'] ?>">
                                    <td class="account-cell text-truncate">
                                      <span class="code fw-semibold font-monospace text-primary"><?= esc($l['account_code']) ?></span>
                                      <span class="name text-body">— <?= esc($l['account_name']) ?></span>
                                    </td>
                                    <td class="text-truncate"><span class="line-description"><?= esc($l['description'] ?? '') ?></span></td>
                                    <td class="text-end font-monospace">
                                      <?= ($l['debit'] > 0) ? '<span class="amount-debit">'.esc($l['currency_code'] ?? ($e['currency_code'] ?? '₨')).' '.number_format((float)$l['debit'],2).'</span>' : '<span class="amount-empty">—</span>' ?>
                                    </td>
                                    <td class="text-end font-monospace">
                                      <?= ($l['credit'] > 0) ? '<span class="amount-credit">'.esc($l['currency_code'] ?? ($e['currency_code'] ?? '₨')).' '.number_format((float)$l['credit'],2).'</span>' : '<span class="amount-empty">—</span>' ?>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                              <tfoot>
                                <tr class="fw-semibold table-secondary">
                                  <td colspan="2" class="text-end">Totals</td>
                                  <td class="text-end font-monospace"><?= number_format($sumDebit,2) ?></td>
                                  <td class="text-end font-monospace"><?= number_format($sumCredit,2) ?></td>
                                </tr>
                              </tfoot>
                            </table>
                          </div>
                          <script>
                            (function(){
                              const id = <?= (int)$e['id'] ?>;
                              const qEl = document.getElementById('linesSearch-'+id);
                              const tEl = document.getElementById('linesType-'+id);
                              const csvBtn = document.getElementById('linesCsv-'+id);
                              const resetBtn = document.getElementById('linesReset-'+id);
                              function apply(){
                                const q = (qEl && qEl.value || '').toLowerCase();
                                const t = (tEl && tEl.value) || '';
                                document.querySelectorAll('#linesTable-'+id+' tbody tr').forEach(tr => {
                                  let vis = true;
                                  if (t && tr.dataset.type !== t) vis = false;
                                  if (q) {
                                    const code = (tr.dataset.code||'').toLowerCase();
                                    const name = (tr.dataset.name||'').toLowerCase();
                                    const desc = (tr.dataset.desc||'');
                                    if (!code.includes(q) && !name.includes(q) && !desc.includes(q)) vis = false;
                                  }
                                  tr.style.display = vis ? '' : 'none';
                                });
                              }
                              if (qEl) qEl.addEventListener('input', apply);
                              if (tEl) tEl.addEventListener('change', apply);
                              if (resetBtn) resetBtn.addEventListener('click', ()=>{ if(qEl) qEl.value=''; if(tEl) tEl.value=''; apply(); });
                              if (csvBtn) csvBtn.addEventListener('click', ()=>{
                                const rows = [["Account Code","Account Name","Type","Description","Debit","Credit"]];
                                document.querySelectorAll('#linesTable-'+id+' tbody tr').forEach(tr=>{
                                  if (tr.style.display==='none') return;
                                  const tds = tr.querySelectorAll('td');
                                  rows.push([
                                    tr.dataset.code || '',
                                    tr.dataset.name || '',
                                    tr.dataset.type || '',
                                    tds[1]?.innerText?.trim() || '',
                                    tds[2]?.innerText?.trim() || '',
                                    tds[3]?.innerText?.trim() || ''
                                  ]);
                                });
                                const csv = rows.map(r=>r.map(f=>`"${(f||'').toString().replace(/"/g,'""')}"`).join(',')).join('\n');
                                const blob = new Blob([csv],{type:'text/csv'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='journal_'+id+'_lines.csv'; a.click();
                              });
                            })();
                          </script>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Journal list client-side filters
function applyJournalFilters(){
  const from = document.getElementById('filterFrom').value;
  const to = document.getElementById('filterTo').value;
  const q = (document.getElementById('filterSearch').value||'').toLowerCase();
  const min = parseFloat(document.getElementById('filterMin').value||'0');
  const bal = document.getElementById('filterBalanced').value;
  let visible = 0;
  document.querySelectorAll('.journal-row').forEach(row => {
    const date = row.dataset.date;
    const memo = row.dataset.memo || '';
    const amt = parseFloat(row.dataset.amount || '0');
    const status = row.dataset.balanced;
    let show = true;
    if (from && date < from) show = false;
    if (to && date > to) show = false;
    if (q && !memo.includes(q) && !row.querySelector('.entry-id-badge').textContent.toLowerCase().includes(q)) show = false;
    if (!isNaN(min) && min > 0 && amt < min) show = false;
    if (bal && status !== bal) show = false;
    row.style.display = show ? '' : 'none';
    // also hide its collapse line row if main hidden
    const collapseRow = document.getElementById('lines-' + row.dataset.entryId);
    if (collapseRow) collapseRow.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  const counter = document.getElementById('filteredCount');
  if (counter) counter.textContent = visible + ' / ' + document.querySelectorAll('.journal-row').length + ' shown';
}
['filterFrom','filterTo','filterSearch','filterMin','filterBalanced'].forEach(id => {
  const el = document.getElementById(id); if(el){ el.addEventListener('input', applyJournalFilters); el.addEventListener('change', applyJournalFilters);} });
function resetJournalFilters(){ ['filterFrom','filterTo','filterSearch','filterMin','filterBalanced'].forEach(id=>{const el=document.getElementById(id); if(el) el.value='';}); applyJournalFilters(); }
function exportJournalsCsv(){
  const headers = ['ID','Date','Memo','Amount','Status'];
  const rows=[headers];
  document.querySelectorAll('.journal-row').forEach(r => { if(r.style.display==='none') return; rows.push([
    r.querySelector('.entry-id-badge').textContent.replace('#',''),
    r.dataset.date,
    r.querySelector('.desc-main').textContent.trim(),
    r.dataset.amount,
    r.dataset.balanced
  ]); });
  const csv = rows.map(r=>r.map(f=>`"${(f||'').toString().replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv],{type:'text/csv'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='journals.csv'; a.click();
}
document.addEventListener('DOMContentLoaded', applyJournalFilters);
</script>

<style>
/* Modern Card Styling */
.modern-card {
  border: none;
  border-radius: 16px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.modern-header {
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
  border-bottom: 1px solid rgba(0, 0, 0, 0.1);
  padding: 1.5rem;
}

.section-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.section-accent-amber {
  background: linear-gradient(135deg, #f59e0b, #d97706);
}

.section-title {
  color: #1e293b;
  font-weight: 700;
}

.section-sub {
  color: #64748b;
  font-size: 0.875rem;
}

.modern-btn {
  border-radius: 8px;
  font-weight: 500;
  border-width: 1px;
  transition: all 0.2s ease;
}

.modern-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

/* Compact Header Styles */
.compact-header {
    background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    border-bottom: 1px solid #e2e8f0;
    padding: 1rem;
}

.header-icon {
    width: 32px;
    height: 32px;
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 0.9rem;
}

/* Remove stats card styles since we don't use them */
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 12px;
  padding: 1.5rem;
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
  transition: all 0.2s ease;
}

.stats-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.stats-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.2rem;
  color: white;
}

.stats-primary .stats-icon { background: linear-gradient(135deg, #3b82f6, #1d4ed8); }
.stats-success .stats-icon { background: linear-gradient(135deg, #10b981, #059669); }
.stats-info .stats-icon { background: linear-gradient(135deg, #06b6d4, #0891b2); }
.stats-warning .stats-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }

.stats-number {
  font-size: 1.75rem;
  font-weight: 700;
  color: #1e293b;
  line-height: 1;
}

.stats-label {
  font-size: 0.875rem;
  color: #64748b;
  font-weight: 500;
}

/* Empty State */
.empty-state {
  text-align: center;
  padding: 3rem 2rem;
}

.empty-icon {
  font-size: 4rem;
  color: #cbd5e1;
  margin-bottom: 1rem;
}

.empty-title {
  color: #374151;
  margin-bottom: 0.5rem;
}

.empty-text {
  color: #6b7280;
  margin-bottom: 1.5rem;
}

/* Modern Table */
.modern-table-container {
  border-radius: 12px;
  overflow: hidden;
  border: 1px solid #e2e8f0;
}

.modern-table {
  margin: 0;
}

.modern-table thead th {
  background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
  border-bottom: 2px solid #cbd5e1;
  color: #374151;
  font-weight: 600;
  padding: 1rem;
  font-size: 0.875rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}
.filter-bar input, .filter-bar select { flex: 0 0 auto; }
#journalsRoot { --surface: #ffffff; --surface-alt:#f8fafc; --border:#e2e8f0; --text:#1e293b; --text-muted:#64748b; --accent:#2563eb; --accent-soft:#3b82f60d; --radius:14px; --danger:#dc2626; --success:#059669; --warning:#d97706; --font-stack:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,sans-serif; }
body.theme-dark #journalsRoot { --surface:#1f2430; --surface-alt:#242b38; --border:#2f3a46; --text:#dce3ec; --text-muted:#7b8794; --accent:#3b82f6; --accent-soft:#3b82f612; --danger:#ef4444; --success:#10b981; --warning:#f59e0b; }
.filter-bar { background:linear-gradient(145deg,var(--surface),var(--surface-alt)); border:1px solid var(--border); padding:.9rem 1.1rem; border-radius:var(--radius); backdrop-filter: blur(6px); box-shadow:0 8px 24px -6px rgba(0,0,0,.06); }
.filter-bar input,.filter-bar select { background:var(--surface-alt); border:1px solid var(--border); color:var(--text); }
.filter-bar input:focus,.filter-bar select:focus { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
table.modern-table { font-family:var(--font-stack); }
table.modern-table thead th { background:var(--surface-alt); color:var(--text-muted); border-bottom:2px solid var(--border); }
.journal-row { background:var(--surface); }
.journal-row:hover { background:linear-gradient(90deg,var(--accent-soft),transparent); box-shadow:inset 3px 0 0 var(--accent); }
.amount-value { color:var(--success); }
.balance-status.balanced { color:var(--success); }
.balance-status.unbalanced { color:var(--danger); }
.entry-id-badge { background:linear-gradient(135deg,var(--accent),#1d4ed8); }
#filteredCount { font-size:.7rem; letter-spacing:.05em; }
.lines-container { background:linear-gradient(135deg,var(--surface-alt),var(--surface)); }
.lines-table thead th { background:var(--accent); }
body.theme-dark .lines-table thead th { background:#0f172a; }
/* Pills */
.balance-status { display:inline-flex; align-items:center; gap:.35rem; padding:.2rem .55rem; border-radius:20px; background:var(--surface-alt); border:1px solid var(--border); }
.balance-status.balanced { background:var(--success); color:#fff; border-color:var(--success); }
.balance-status.unbalanced { background:var(--danger); color:#fff; border-color:var(--danger); }

/* Collapse button tweak */
.btn-toggle-lines { width:30px; height:30px; display:flex; align-items:center; justify-content:center; }

/* Smooth typography */
.desc-main { font-weight:600; letter-spacing:.2px; }
.account-name { font-weight:500; }
/* Responsive */
@media (max-width:900px){ .filter-bar { flex-direction:column; } .filter-bar .ms-auto{width:100%; justify-content:flex-end;} }
@media (max-width:700px){ .modern-table thead { display:none; } .modern-table tbody tr.journal-row { display:grid; grid-template-columns:70px 1fr auto; grid-row-gap:4px; padding:.75rem .75rem; } .journal-row td{ border:none !important; } .journal-row td.text-end{ text-align:left !important; } .balance-indicator{ margin-top:0; } }

#journalsRoot { animation:fadeIn .35s ease; }
@keyframes fadeIn { from{opacity:0; transform:translateY(6px);} to{opacity:1; transform:none;} }
#filteredCount { pointer-events:none; }

.journal-row {
  transition: all 0.2s ease;
  border-bottom: 1px solid #f1f5f9;
}

.journal-row:hover {
  background: linear-gradient(90deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
  box-shadow: inset 3px 0 0 #3b82f6;
}

.entry-id-badge {
  background: linear-gradient(135deg, #6366f1, #4f46e5);
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 20px;
  font-weight: 600;
  font-size: 0.75rem;
}

.date-display {
  text-align: center;
}

.date-main {
  font-weight: 600;
  color: #374151;
  font-size: 0.875rem;
}

.date-year {
  font-size: 0.75rem;
  color: #9ca3af;
}

.entry-description {
  padding: 0.5rem 0;
}

.desc-main {
  color: #1e293b;
  font-size: 0.95rem;
  display: block;
}

.desc-sub {
  color: #64748b;
  font-size: 0.8rem;
}

.amount-display {
  text-align: right;
}

.amount-value {
  display: block;
  font-weight: 700;
  color: #059669;
  font-size: 1rem;
}

.balance-indicator {
  margin-top: 0.25rem;
}

.balance-status {
  font-size: 0.75rem;
  font-weight: 500;
}

.balance-status.balanced {
  color: #059669;
}

.balance-status.unbalanced {
  color: #dc2626;
}

/* Lines Table */
.lines-row td {
  padding: 0 !important;
  border: none !important;
}

.lines-container {
  background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
  border-top: 1px solid #e2e8f0;
}

.lines-table-container {
  padding: .25rem .75rem;
}

.lines-table {
  background: white;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.lines-table thead th { background:#374151; color:#fff; padding:.45rem .6rem; font-weight:600; font-size:.78rem; border:none; }

.line-item {
  border-bottom: 1px solid #f3f4f6;
}

.line-item:last-child {
  border-bottom: none;
}

.account-cell .code{ margin-right:.4rem; }
.account-cell .name{ white-space:nowrap; }

.amount-debit {
  color: #dc2626;
  font-weight: 600;
}

.amount-credit {
  color: #059669;
  font-weight: 600;
}

.amount-empty {
  color: #d1d5db;
}

.no-lines-message {
  padding: 2rem;
  text-align: center;
  color: #6b7280;
  font-style: italic;
}
.table-compact th, .table-compact td { padding: .35rem .5rem !important; }
.table-compact { font-size: .88rem; }
.btn-xs { --bs-btn-padding-y: .125rem; --bs-btn-padding-x: .35rem; --bs-btn-font-size: .7rem; }

/* Mini tiles + compact filter for lines panels */
.tile.small-tile { background: linear-gradient(135deg, #fff, #f8fafc); border:1px solid #e2e8f0; border-radius:10px; padding:.6rem .75rem; height:100%; }
.tile.small-tile .label { font-size:.7rem; text-transform:uppercase; letter-spacing:.05em; color:#64748b; font-weight:600; }
.tile.small-tile .value { font-weight:700; }
.tile.small-tile.ok { border-color:#10b981 }
.tile.small-tile.bad { border-color:#ef4444 }
.tile.small-tile .sub { font-size:.7rem; color:#94a3b8 }
.filterbar { background: linear-gradient(145deg, #ffffff, #f8fafc); border:1px solid #e2e8f0; border-radius:10px; }
.code-pill { font-weight:600; font-variant-numeric: tabular-nums; }
.type-pill { font-size:.65rem; }

/* Dark Theme */
body.theme-dark .modern-card {
  background: #1e293b;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
}

body.theme-dark .modern-header {
  background: linear-gradient(135deg, #334155 0%, #1e293b 100%);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

body.theme-dark .section-title {
  color: #e2e8f0;
}

body.theme-dark .section-sub {
  color: #94a3b8;
}

body.theme-dark .stats-card {
  background: #334155;
  border: 1px solid #475569;
}

body.theme-dark .stats-number {
  color: #f1f5f9;
}

body.theme-dark .stats-label {
  color: #cbd5e1;
}

body.theme-dark .empty-icon {
  color: #64748b;
}

body.theme-dark .empty-title {
  color: #e2e8f0;
}

body.theme-dark .empty-text {
  color: #94a3b8;
}

body.theme-dark .modern-table-container {
  border: 1px solid #475569;
}

body.theme-dark .modern-table {
  background: #334155;
  color: #e2e8f0;
}

body.theme-dark .modern-table thead th {
  background: linear-gradient(135deg, #475569 0%, #334155 100%);
  color: #f1f5f9;
  border-bottom: 2px solid #64748b;
}

body.theme-dark .journal-row {
  border-bottom: 1px solid #475569;
}

body.theme-dark .journal-row:hover {
  background: linear-gradient(90deg, rgba(59, 130, 246, 0.1) 0%, rgba(59, 130, 246, 0.05) 100%);
}

body.theme-dark .desc-main {
  color: #f1f5f9;
}

body.theme-dark .desc-sub {
  color: #94a3b8;
}

body.theme-dark .date-main {
  color: #e2e8f0;
}

body.theme-dark .date-year {
  color: #64748b;
}

body.theme-dark .lines-container {
  background: linear-gradient(135deg, #475569 0%, #334155 100%);
  border-top: 1px solid #64748b;
}

body.theme-dark .lines-table {
  background: #1e293b;
}

body.theme-dark .lines-table thead th {
  background: #0f172a;
}

body.theme-dark .line-item {
  border-bottom: 1px solid #334155;
}

body.theme-dark .account-code {
  color: #f1f5f9;
}

body.theme-dark .account-name {
  color: #e2e8f0;
}

body.theme-dark .account-type {
  color: #94a3b8;
}

body.theme-dark .no-lines-message {
  color: #94a3b8;
}

body.theme-dark .modern-alert {
  background: rgba(59, 130, 246, 0.1);
  border: 1px solid rgba(59, 130, 246, 0.2);
  color: #e2e8f0;
}
</style>

<?= $this->endSection() ?>