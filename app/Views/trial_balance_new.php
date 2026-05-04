<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Trial Balance (New)<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="container-fluid py-3" id="tbRoot">
    <div class="tb-hero d-flex align-items-center justify-content-between mb-3 gap-2 flex-wrap">
        <div class="d-flex align-items-center gap-2">
            <div class="tb-icon"><i class="bi bi-balance-scale"></i></div>
            <div>
                <h4 class="mb-0">Trial Balance</h4>
                <div class="small tb-subtitle">Financial position snapshot across all posted ledger activity</div>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-primary btn-sm"><i class="bi bi-journal-text me-1"></i> Journals</a>
            <button class="btn btn-outline-success btn-sm" onclick="window.print()"><i class="bi bi-printer me-1"></i> Print</button>
        </div>
    </div>

        <?php
            $diff = ($totals['debit'] ?? 0) - ($totals['credit'] ?? 0);
            $types = array_keys($trialBalance ?? []);
            $isBalanced = abs($diff) < 0.01;
            $hasRows = !empty($trialBalance);
            $typeClassMap = [
                'asset' => 'tb-pill-asset',
                'expense' => 'tb-pill-expense',
                'liability' => 'tb-pill-liability',
                'equity' => 'tb-pill-equity',
                'revenue' => 'tb-pill-revenue',
            ];
        ?>
        <div class="tb-balance-status mb-2 <?= $isBalanced ? 'ok' : 'bad' ?>">
            <span class="tb-balance-dot"></span>
            <span class="fw-semibold"><?= $isBalanced ? 'Balanced' : 'Unbalanced' ?></span>
            <span class="text-muted">Difference: <?= number_format($diff,2) ?></span>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex gap-2">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#tbSummary" aria-expanded="true"><i class="bi bi-layout-text-sidebar-reverse"></i> Summary</button>
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#tbFilters" aria-expanded="false"><i class="bi bi-funnel"></i> Filters</button>
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-dark" onclick="exportTrialCsv()"><i class="bi bi-file-earmark-spreadsheet"></i> CSV</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
            </div>
        </div>
        <div id="tbSummary" class="collapse show mb-2">
            <div class="row g-2">
                <div class="col-6 col-md-3"><div class="tile"><div class="label">Total Debits</div><div class="value text-primary">₨ <?= number_format($totals['debit'] ?? 0,2) ?></div></div></div>
                <div class="col-6 col-md-3"><div class="tile"><div class="label">Total Credits</div><div class="value text-danger">₨ <?= number_format($totals['credit'] ?? 0,2) ?></div></div></div>
                <div class="col-6 col-md-3"><div class="tile"><div class="label">Accounts</div><div class="value"><?= (int)($stats['total_accounts'] ?? 0) ?></div></div></div>
                <div class="col-6 col-md-3"><div class="tile status <?= abs($diff) < 0.01 ? 'ok' : 'bad' ?>"><div class="label">Status</div><div class="value"><?= abs($diff) < 0.01 ? '✓ Balanced' : '⚠ Unbalanced' ?></div><div class="sub">Diff: <?= number_format($diff,2) ?></div></div></div>
            </div>
        </div>
        <div id="tbFilters" class="collapse mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2 filterbar p-2">
                <select id="typeFilter" class="form-select form-select-sm" style="width:160px">
                    <option value="">All types</option>
                    <?php foreach($types as $t): ?><option value="<?= esc($t) ?>"><?= esc($t) ?></option><?php endforeach; ?>
                </select>
                <input type="search" id="tbSearch" class="form-control form-control-sm" placeholder="Search account or code" style="min-width:220px">
                <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" onclick="resetTbFilters()"><i class="bi bi-x-circle"></i> Reset</button>
            </div>
        </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm overflow-hidden tb-table-shell">
        <div class="table-responsive">
            <table class="table table-sm table-compact align-middle mb-0" id="tbTable">
                <thead class="table-light sticky-top">
                    <tr>
                        <th style="width:90px">Code</th>
                        <th>Account Name</th>
                        <th style="width:130px">Type</th>
                        <th class="text-end" style="width:160px">Debit (₨)</th>
                        <th class="text-end" style="width:160px">Credit (₨)</th>
                    </tr>
                </thead>
                <tbody id="tbBody">
                <?php foreach ($trialBalance as $type => $accounts): ?>
                    <tr class="type-row"><td colspan="5" class="type-badge"><?= esc($type) ?></td></tr>
                    <?php foreach ($accounts as $account): ?>
                        <?php $pillClass = $typeClassMap[strtolower((string)($account['type'] ?? ''))] ?? 'tb-pill-default'; ?>
                        <tr data-type="<?= esc($account['type']) ?>">
                            <td class="font-monospace fw-semibold text-primary"><?= esc($account['code']) ?></td>
                            <td><?= esc($account['name']) ?></td>
                            <td><span class="tb-type-pill <?= $pillClass ?>"><?= esc($account['type']) ?></span></td>
                            <td class="text-end font-monospace"><?= $account['debit_balance'] > 0 ? number_format($account['debit_balance'],2) : '<span class="text-muted">—</span>' ?></td>
                            <td class="text-end font-monospace"><?= $account['credit_balance'] > 0 ? number_format($account['credit_balance'],2) : '<span class="text-muted">—</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                <?php if (!$hasRows): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No journal balances found for this period.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
                <tfoot class="table-secondary">
                    <tr class="fw-semibold">
                        <td colspan="3" class="text-end">Totals</td>
                        <td class="text-end font-monospace"><?= number_format($totals['debit'] ?? 0,2) ?></td>
                        <td class="text-end font-monospace"><?= number_format($totals['credit'] ?? 0,2) ?></td>
                    </tr>
                    <tr>
                        <td colspan="3" class="text-end">Difference</td>
                        <td colspan="2" class="text-end"><span class="badge <?= abs($diff) < 0.01 ? 'bg-success' : 'bg-danger' ?> px-3 py-2"><?= number_format($diff,2) ?></span></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
function applyTbFilters(){
    const t = document.getElementById('typeFilter').value;
    const q = (document.getElementById('tbSearch').value||'').toLowerCase();
    let lastTypeShown = false;
    document.querySelectorAll('#tbBody tr').forEach(tr => {
        if (tr.classList.contains('type-row')) { tr.style.display = ''; lastTypeShown=false; return; }
        const type = tr.dataset.type || '';
        const text = tr.textContent.toLowerCase();
        const visible = (!t || type===t) && (!q || text.includes(q));
        tr.style.display = visible ? '' : 'none';
        if (visible) lastTypeShown = true;
        if (!visible && tr.previousElementSibling && tr.previousElementSibling.classList.contains('type-row')) {
            // hide header when all subsequent rows of that type are hidden; handled in next pass
        }
    });
    // Hide type badges that have no visible rows beneath
    document.querySelectorAll('#tbBody .type-row').forEach(row => {
        let next = row.nextElementSibling; let any=false;
        while(next && !next.classList.contains('type-row')) { if (next.style.display !== 'none') { any=true; break; } next = next.nextElementSibling; }
        row.style.display = any ? '' : 'none';
    });
}
['typeFilter','tbSearch'].forEach(id=>{const el=document.getElementById(id); if(el){ el.addEventListener('input', applyTbFilters); el.addEventListener('change', applyTbFilters);} });
function resetTbFilters(){ const s=document.getElementById('typeFilter'); if(s) s.value=''; const q=document.getElementById('tbSearch'); if(q) q.value=''; applyTbFilters(); }
function exportTrialCsv(){
    const rows=[["Code","Name","Type","Debit","Credit"]];
    document.querySelectorAll('#tbBody tr').forEach(tr=>{
        if (tr.classList.contains('type-row') || tr.style.display==='none') return;
        const tds=tr.querySelectorAll('td');
        rows.push([tds[0].innerText, tds[1].innerText, tds[2].innerText, tds[3].innerText, tds[4].innerText]);
    });
    const csv = rows.map(r=>r.map(f=>`"${(f||'').toString().replace(/"/g,'""')}"`).join(',')).join('\n');
    const blob=new Blob([csv],{type:'text/csv'}); const a=document.createElement('a'); a.href=URL.createObjectURL(blob); a.download='trial_balance.csv'; a.click();
}
document.addEventListener('DOMContentLoaded', applyTbFilters);
</script>

<style>
#tbRoot { --surface:#ffffff; --surface-alt:#f8fafc; --surface-2:#f1f5f9; --border:#e2e8f0; --text:#1f2937; --muted:#64748b; --accent:#2563eb; --ok:#10b981; --bad:#ef4444; }
body.theme-dark #tbRoot { --surface:#111827; --surface-alt:#1f2937; --surface-2:#0f172a; --border:#334155; --text:#e5edf5; --muted:#94a3b8; --accent:#60a5fa; --ok:#34d399; --bad:#f87171; }
.tb-hero{ background:linear-gradient(135deg,var(--surface),var(--surface-alt)); border:1px solid var(--border); border-radius:12px; padding:.7rem .9rem; }
.tb-icon{ width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg,#2563eb,#0ea5e9); color:#fff; font-size:1rem; }
.tb-subtitle{ color:var(--muted); }
.tb-balance-status{ display:inline-flex; align-items:center; gap:.45rem; padding:.32rem .65rem; border-radius:999px; border:1px solid var(--border); background:var(--surface); font-size:.78rem; }
.tb-balance-status.ok{ border-color:rgba(16,185,129,.4); }
.tb-balance-status.bad{ border-color:rgba(239,68,68,.4); }
body.theme-dark .tb-balance-status.ok{ border-color:rgba(52,211,153,.45); }
body.theme-dark .tb-balance-status.bad{ border-color:rgba(248,113,113,.45); }
.tb-balance-dot{ width:8px; height:8px; border-radius:50%; background:var(--ok); display:inline-block; }
.tb-balance-status.bad .tb-balance-dot{ background:var(--bad); }
.tile { background:linear-gradient(135deg,var(--surface),var(--surface-alt)); border:1px solid var(--border); border-radius:12px; padding:10px 14px; height:100%; }
.tile .label{ font-size:.7rem; text-transform:uppercase; letter-spacing:.5px; color:var(--muted); font-weight:600 }
.tile .value{ font-weight:800; color:var(--text); }
.tile.status.ok{ border-color:var(--ok) }
.tile.status.bad{ border-color:var(--bad) }
.tile.status .sub{ font-size:.7rem; color:var(--muted) }
.filterbar{ background:linear-gradient(145deg,var(--surface),var(--surface-alt)); border:1px solid var(--border); border-radius:12px; padding:.75rem 1rem }
.tb-table-shell{ border:1px solid var(--border); border-radius:12px; background:var(--surface); }
.type-row td{ padding:.32rem .65rem !important; border-top:1px solid var(--border); border-bottom:1px solid var(--border); }
.type-badge{ background:var(--surface-2); color:var(--text); font-weight:700; letter-spacing:.06em; text-transform:uppercase; font-size:.68rem; border-left:4px solid var(--accent) }
.tb-type-pill{ display:inline-block; border-radius:999px; padding:2px 9px; font-size:.67rem; font-weight:700; letter-spacing:.02em; border:1px solid transparent; }
.tb-pill-asset{ background:rgba(37,99,235,.12); color:#1d4ed8; border-color:rgba(37,99,235,.26); }
.tb-pill-expense{ background:rgba(245,158,11,.14); color:#b45309; border-color:rgba(245,158,11,.3); }
.tb-pill-liability{ background:rgba(239,68,68,.12); color:#b91c1c; border-color:rgba(239,68,68,.26); }
.tb-pill-equity{ background:rgba(16,185,129,.14); color:#047857; border-color:rgba(16,185,129,.3); }
.tb-pill-revenue{ background:rgba(139,92,246,.14); color:#6d28d9; border-color:rgba(139,92,246,.28); }
.tb-pill-default{ background:rgba(100,116,139,.14); color:#475569; border-color:rgba(100,116,139,.28); }
body.theme-dark .tb-pill-asset{ background:rgba(96,165,250,.2); color:#bfdbfe; border-color:rgba(96,165,250,.35); }
body.theme-dark .tb-pill-expense{ background:rgba(251,191,36,.2); color:#fde68a; border-color:rgba(251,191,36,.35); }
body.theme-dark .tb-pill-liability{ background:rgba(248,113,113,.2); color:#fecaca; border-color:rgba(248,113,113,.35); }
body.theme-dark .tb-pill-equity{ background:rgba(52,211,153,.2); color:#a7f3d0; border-color:rgba(52,211,153,.35); }
body.theme-dark .tb-pill-revenue{ background:rgba(167,139,250,.2); color:#ddd6fe; border-color:rgba(167,139,250,.35); }
body.theme-dark .tb-pill-default{ background:rgba(148,163,184,.2); color:#cbd5e1; border-color:rgba(148,163,184,.35); }
.table thead.sticky-top th{ position:sticky; top:0; z-index:5; background:var(--surface-2) !important; color:var(--muted); border-bottom:1px solid var(--border); }
.table#tbTable td{ border-color:var(--border); }
.table#tbTable tbody tr:not(.type-row):hover td{ background:rgba(37,99,235,.06); }
body.theme-dark .table#tbTable tbody tr:not(.type-row):hover td{ background:rgba(96,165,250,.12); }
.table#tbTable tfoot td{ background:var(--surface-2); border-color:var(--border); }
body.theme-dark #tbRoot .text-muted{ color:var(--muted) !important; }
/* Compact density */
.table-compact th, .table-compact td { padding:.35rem .5rem !important; }
.table-compact { font-size:.88rem; }
@media (max-width: 767px){ .filterbar{ flex-direction:column } }
</style>
<?= $this->endSection() ?>
