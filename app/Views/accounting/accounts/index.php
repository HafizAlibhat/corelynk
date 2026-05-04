<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Accounts<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <div class="card">
        <div class="card-header coa-header d-flex align-items-center justify-content-between">
          <div class="d-flex align-items-center gap-3">
            <div class="coa-icon" aria-hidden="true"><i class="bi bi-wallet2"></i></div>
            <div>
              <h5 class="coa-title">Chart of Accounts</h5>
              <div class="coa-subtitle">Grouped by major category (Assets, Liabilities, Equity, Income, Expenses)</div>
            </div>
          </div>
          <div>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addAccountModal"><i class="bi bi-plus-lg"></i> Add Account</button>
          </div>
        </div>
        <div class="card-body">
          <style>
            /* Color accents for major heads */
            .accordion-button.coa-type-asset { border-left:4px solid #198754; background:rgba(25,135,84,.08); }
            .accordion-button.coa-type-liability { border-left:4px solid #fd7e14; background:rgba(253,126,20,.08); }
            .accordion-button.coa-type-equity { border-left:4px solid #0dcaf0; background:rgba(13,202,240,.08); }
            .accordion-button.coa-type-revenue { border-left:4px solid #0d6efd; background:rgba(13,110,253,.08); }
            .accordion-button.coa-type-expense { border-left:4px solid #dc3545; background:rgba(220,53,69,.08); }
            /* Drag & drop styling */
            .coa-drop-top-level { border:1px dashed rgba(255,255,255,.25); background:rgba(108,117,125,.10); padding:.35rem .55rem; border-radius:.35rem; font-size:.75rem; }
            .coa-drop-top-level.coa-zone-active { border-color:var(--bs-primary); background:rgba(13,110,253,.12); }
            tr.coa-row-dragging { opacity:.55; }
            tr.coa-row-drop-ok { outline:2px dashed var(--bs-primary); outline-offset:-2px; background:rgba(13,110,253,.14)!important; }
            table.coa-hier-table tbody tr[draggable="true"] { cursor:grab; }
            table.coa-hier-table tbody tr[draggable="true"]:active { cursor:grabbing; }
            .coa-dnd-help { font-size:.65rem; color:var(--bs-secondary-color); margin-top:.15rem; }
            /* Tiles + filter bar */
            .tile { background: linear-gradient(135deg, var(--bs-body-bg), rgba(0,0,0,.02)); border:1px solid var(--bs-border-color); border-radius: .75rem; padding:.75rem 1rem; height:100%; }
            .tile .label { font-size:.7rem; text-transform:uppercase; letter-spacing:.4px; color: var(--bs-secondary-color); font-weight:600; }
            .tile .value { font-weight:700; }
            .filterbar { background: linear-gradient(145deg, var(--bs-body-bg), rgba(0,0,0,.02)); border:1px solid var(--bs-border-color); border-radius:.75rem; padding:.6rem .75rem; }
            /* Header polish (dark-theme friendly) */
            .coa-header{
              background: linear-gradient(180deg, rgba(20, 24, 33, 0.9), rgba(20, 24, 33, 0.55));
              border: 1px solid rgba(255,255,255,0.08);
              border-radius: 12px;
              padding: 14px 16px;
            }
            .coa-header .coa-title{
              color: rgba(255,255,255,0.95);
              font-weight: 700;
              letter-spacing: .2px;
              margin: 0;
            }
            .coa-header .coa-subtitle{
              color: rgba(255,255,255,0.68);
              font-size: .92rem;
              margin: 2px 0 0 0;
            }
            .coa-header .coa-icon{
              width: 38px;
              height: 38px;
              border-radius: 10px;
              display: inline-flex;
              align-items: center;
              justify-content: center;
              background: rgba(255,255,255,0.10);
              border: 1px solid rgba(255,255,255,0.12);
              color: rgba(255,255,255,0.95);
            }
          </style>

          <?php if (empty($accounts)): ?>
            <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>No accounts yet.</div>
          <?php else: ?>

          <?php
            // Summary counts
            $byType = ['Asset'=>0,'Liability'=>0,'Equity'=>0,'Revenue'=>0,'Expense'=>0];
            $activeCount = 0; $parentCount = 0; $childCount = 0;
            foreach (($accounts ?? []) as $a) {
              $t = $a['type'] ?? '';
              if (isset($byType[$t])) { $byType[$t]++; }
              if (((int)($a['is_active'] ?? 1)) === 1) { $activeCount++; }
              if (!empty($a['parent_id'])) { $childCount++; } else { $parentCount++; }
            }
            $totalCount = count($accounts ?? []);
          ?>

          <!-- Toolbar: toggles for Summary and Filters -->
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="d-flex gap-2">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#coaSummary" aria-expanded="false"><i class="bi bi-layout-text-sidebar-reverse"></i> Summary</button>
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#coaFilters" aria-expanded="false"><i class="bi bi-funnel"></i> Filters</button>
            </div>
            <div class="d-flex gap-2">
              <button type="button" class="btn btn-sm btn-outline-dark" id="accExportCsv"><i class="bi bi-file-earmark-spreadsheet"></i> CSV</button>
            </div>
          </div>

          <!-- Summary tiles (collapsed by default) -->
          <div id="coaSummary" class="collapse mb-2">
            <div class="row g-2">
              <div class="col-6 col-md-3"><div class="tile"><div class="label">Total Accounts</div><div class="value h5 mb-0"><?= (int)$totalCount ?></div></div></div>
              <div class="col-6 col-md-3"><div class="tile"><div class="label">Active</div><div class="value h5 mb-0 text-success"><?= (int)$activeCount ?></div></div></div>
              <div class="col-6 col-md-3"><div class="tile"><div class="label">Parents</div><div class="value h5 mb-0"><?= (int)$parentCount ?></div><div class="text-muted small">Children: <?= (int)$childCount ?></div></div></div>
              <div class="col-6 col-md-3"><div class="tile"><div class="label">Distribution</div>
                <div class="d-flex flex-wrap gap-1 mt-1">
                  <?php foreach ($byType as $k=>$v): if ($v>0): ?>
                    <span class="badge bg-body-secondary text-body"><?= esc($k) ?>: <span class="fw-semibold"><?= (int)$v ?></span></span>
                  <?php endif; endforeach; ?>
                </div>
              </div></div>
            </div>
          </div>

          <!-- Global Filters (collapsed by default) -->
          <div id="coaFilters" class="collapse mb-2">
            <div class="d-flex flex-wrap align-items-center gap-2 filterbar p-2">
              <input type="search" id="accSearch" class="form-control form-control-sm" placeholder="Search code or name" style="min-width:220px">
              <select id="accTypeFilter" class="form-select form-select-sm" style="width:180px">
                <option value="">All types</option>
                <?php foreach (['Asset','Liability','Equity','Revenue','Expense'] as $t): ?>
                  <option value="<?= esc($t) ?>"><?= esc($t) ?></option>
                <?php endforeach; ?>
              </select>
              <select id="accActiveFilter" class="form-select form-select-sm" style="width:160px">
                <option value="">All statuses</option>
                <option value="1">Active only</option>
                <option value="0">Inactive only</option>
              </select>
              <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="accResetFilters"><i class="bi bi-x-circle"></i> Reset</button>
            </div>
          </div>
          
          <!-- Quick Parent Assignment Form -->
          <div class="card mb-3" style="background-color: rgba(13,110,253,.03); border: 1px solid rgba(13,110,253,.15);">
            <div class="card-body py-2">
              <div class="row align-items-center g-2">
                <div class="col-auto">
                  <small class="text-muted fw-semibold">Set Parent:</small>
                </div>
                <div class="col-auto">
                  <select id="quickChildSelect" class="form-select form-select-sm" style="width: 220px;">
                    <option value="">Select child account...</option>
                    <?php foreach ($accounts as $acc): ?>
                      <option value="<?= $acc['id'] ?>" data-type="<?= esc($acc['type']) ?>" data-parent="<?= (int)($acc['parent_id'] ?? 0) ?>"><?= esc($acc['code']) ?> — <?= esc($acc['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-auto">
                  <i class="bi bi-arrow-right text-primary"></i>
                </div>
                <div class="col-auto">
                  <select id="quickParentSelect" class="form-select form-select-sm" style="width: 220px;" disabled>
                    <option value="">Choose parent...</option>
                  </select>
                </div>
                <div class="col-auto">
                  <button type="button" id="quickSetParentBtn" class="btn btn-sm btn-primary" disabled>
                    <i class="bi bi-check2"></i> Set
                  </button>
                </div>
                <div class="col-auto">
                  <small id="currentParentDisplay" class="text-muted fst-italic"></small>
                </div>
              </div>
            </div>
          </div>
          
          <!-- Toolbar removed per request (Expand/Collapse/Auto-Assign/Dry Run) -->

          <?php
            $order = ['Asset'=>'Assets','Liability'=>'Liabilities','Equity'=>'Equity','Revenue'=>'Income','Expense'=>'Expenses'];
            $groups = $groupedAccounts ?? [];
            $idx = 0;
          ?>
          <div class="accordion" id="coaAccordion">
          <?php foreach ($order as $key => $label): $idx++; $collapseId = 'coaCollapse'.$idx; $headingId = 'coaHeading'.$idx; ?>
            <div class="accordion-item">
              <h2 class="accordion-header" id="<?= $headingId ?>">
                <button class="accordion-button <?= 'coa-type-'.strtolower($key) ?> <?= $idx === 1 ? '' : 'collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="<?= $idx === 1 ? 'true' : 'false' ?>" aria-controls="<?= $collapseId ?>">
                  <?= esc($label) ?>
                </button>
              </h2>
              <div id="<?= $collapseId ?>" class="accordion-collapse collapse <?= $idx === 1 ? 'show' : '' ?>" aria-labelledby="<?= $headingId ?>" data-bs-parent="#coaAccordion">
                <div class="accordion-body">
                  <div class="coa-drop-top-level mb-2" data-type="<?= esc($key) ?>">Drag an account here to make it top-level in <?= esc($label) ?>.</div>
                  <div class="coa-dnd-help">Drag a row onto another (same type) to make it a child. Drop into the shaded bar to remove parent.</div>
                  <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0 coa-hier-table table-compact" data-type="<?= esc($key) ?>">
                      <thead>
                        <tr>
                          <th style="width: 140px;">Code</th>
                          <th>Name</th>
                          <th style="width: 100px;">Currency</th>
                          <th style="width: 80px;">Active</th>
                          <th style="width: 100px;">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                        $hierRows = $hierarchicalAccounts[$key] ?? [];
                        if (empty($hierRows)) {
                          echo '<tr><td colspan="5" class="text-muted">No entries</td></tr>';
                        } else {
                          $renderNode = function($node, $level = 0) use (&$renderNode, $accountBalances) {
                            // Replace dash-prefix indentation with visual padding — calculate padding (rem)
                            $pad = (float)($level * 1.25);
                            $id = (int)$node['id'];
                            $code = esc($node['code']);
                            $name = esc($node['name']);
                            // Bank indicator icon if flagged
                            $bankIcon = (!empty($node['is_bank'])) ? '<span class="ms-1 text-primary" title="Bank Account" data-bs-toggle="tooltip"><i class="bi bi-bank2"></i></span>' : '';
                            $currency = esc($node['currency_code'] ?? '');
                            $active = ((int)($node['is_active'] ?? 1)) ? 'Yes' : 'No';
                            $balData = $accountBalances[$id] ?? ['lines'=>0,'balance'=>0];
                            $canDelete = ($balData['lines'] === 0 && abs(($balData['balance'] ?? 0)) < 0.0001);
                            $hasChildren = !empty($node['children']);
                            $badge = $hasChildren ? '<span class="badge bg-light text-dark ms-1">'.count($node['children']).'</span>' : '';
                            // Toggle button shown for rows that have children
                            $toggleBtn = $hasChildren ? '<button type="button" class="btn btn-sm btn-outline-secondary btn-toggle-children ms-2" data-id="'.$id.'" title="Collapse/expand children"><i class="bi bi-chevron-down"></i></button>' : '';
                            $balStr = number_format((float)($balData['balance'] ?? 0), 2, '.', '');
                            $action = '<div class="btn-group" role="group" aria-label="Account actions">'
                                    . '<a class="btn btn-sm btn-outline-primary" title="Edit" data-bs-toggle="tooltip" href="'.base_url('accounting/accounts/'.$id.'/edit').'"><i class="bi bi-pencil"></i></a>'
                                    . ($canDelete
                                        ? '<button type="button" class="btn btn-sm btn-outline-danger" title="Delete (0 balance)" data-bs-toggle="tooltip" data-action="delete" data-id="'.$id.'"><i class="bi bi-trash"></i></button>'
                                        : '<button type="button" class="btn btn-sm btn-outline-warning" title="Deactivate" data-bs-toggle="tooltip" data-action="deactivate" data-id="'.$id.'"><i class="bi bi-slash-circle"></i></button>'
                                          . '<button type="button" class="btn btn-sm btn-outline-success" title="Merge" data-bs-toggle="tooltip" data-action="merge" data-id="'.$id.'" data-code="'.$code.'"><i class="bi bi-diagram-2"></i></button>'
                                      )
                                    . '</div>';
                            $dataType = esc($node['type'] ?? '');
                            $dataParent = (int)($node['parent_id'] ?? 0);
                            $dataActive = ((int)($node['is_active'] ?? 1)) ? '1' : '0';
                   echo '<tr data-id="'.$id.'" data-level="'.$level.'" data-lines="'.((int)($balData['lines'] ?? 0)).'" data-balance="'.$balStr.'" data-type="'.$dataType.'" data-parent="'.$dataParent.'" data-active="'.$dataActive.'" data-code="'.$code.'" data-name="'.$name.'" draggable="true">'
                     . '<td>'.$code.'</td>'
                     // use padding to show hierarchy instead of dashes
                               . '<td><div class="coa-name" style="padding-left:'.($pad).'rem">'.$toggleBtn.'<span class="coa-name-text">'.$name.'</span> '.$bankIcon.' '.$badge.'</div></td>'
                               . '<td>'.$currency.'</td>'
                               . '<td>'.$active.'</td>'
                               . '<td>'.$action.'</td>'
                               . '</tr>';
                            if (!empty($node['children'])) { foreach ($node['children'] as $child) { $renderNode($child, $level+1); } }
                          };
                          foreach ($hierRows as $root) { $renderNode($root, 0); }
                        }
                        ?>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Add Account Modal -->
<div class="modal fade" id="addAccountModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i>Add Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="addAccountForm" method="post" action="<?= base_url('accounting/accounts/create') ?>">
        <?= csrf_field() ?>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Code</label>
            <input type="text" name="code" class="form-control" required maxlength="32" value="<?= old('code') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required maxlength="128" value="<?= old('name') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Type</label>
            <select name="type" class="form-select" required>
              <?php if (!empty($typesOptions) && is_array($typesOptions)): ?>
                <?php foreach ($typesOptions as $val => $label): $sel = (old('type')===$val)?'selected':''; ?>
                  <option value="<?= esc($val) ?>" <?= $sel ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
              <?php else: ?>
                <?php foreach (($types ?? ['Asset','Liability','Equity','Revenue','Expense']) as $t): $sel = (old('type')===$t)?'selected':''; ?>
                  <option value="<?= esc($t) ?>" <?= $sel ?>><?= esc($t) ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Currency (optional)</label>
            <select name="currency_code" class="form-select">
              <option value="">—</option>
              <?php foreach (($currencies ?? []) as $c): ?>
                <option value="<?= esc($c['code']) ?>" <?= (old('currency_code')===$c['code']?'selected':'') ?>><?= esc($c['code']) ?> — <?= esc($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="is_bank" name="is_bank" value="1" <?= old('is_bank') ? 'checked' : '' ?>>
            <label class="form-check-label" for="is_bank"><i class="bi bi-bank2 me-1"></i>Is Bank Account?</label>
            <div class="form-text">Tick if this account represents a bank account (for cheque module).</div>
          </div>
          <?php if (session('form_errors')): $fe = session('form_errors'); ?>
            <div class="alert alert-danger py-2" id="formErrorsServer">
              <?php foreach ($fe as $fmsg): ?>
                <div><?= esc($fmsg) ?></div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="alert alert-danger py-2 d-none" id="formErrorsAjax"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Create</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// Expand/Collapse All for accordion
document.addEventListener('DOMContentLoaded', function(){
  const expandAll = document.getElementById('expandAllBtn');
  const collapseAll = document.getElementById('collapseAllBtn');
  const acc = document.getElementById('coaAccordion');
  if (expandAll && collapseAll && acc) {
    expandAll.addEventListener('click', function(){
      acc.querySelectorAll('.accordion-collapse').forEach(function(el){
        if (!el.classList.contains('show')) new bootstrap.Collapse(el, {toggle:true});
      });
    });
    collapseAll.addEventListener('click', function(){
      acc.querySelectorAll('.accordion-collapse.show').forEach(function(el){
        new bootstrap.Collapse(el, {toggle:true});
      });
    });
  }

  // If there were form errors on submit, auto-open the Add modal so user sees issues
  <?php if (session('error') && session('form_errors')): ?>
    const addModalEl = document.getElementById('addAccountModal');
    if (addModalEl) {
      const m = new bootstrap.Modal(addModalEl);
      m.show();
    }
  <?php endif; ?>

  // Prefer native form POST for maximum compatibility (CI routing/filters)
  // If you want AJAX later, set window.ACCOUNTS_USE_AJAX=true to enable the block below.
  const addForm = document.getElementById('addAccountForm');
  if (addForm && window.ACCOUNTS_USE_AJAX) {
    addForm.addEventListener('submit', function(e){ /* placeholder: disabled by default */ });
  }

  // Dry run button (AJAX preview of changes)
  const dryRunBtn = document.getElementById('dryRunBtn');
  if (dryRunBtn) {
    dryRunBtn.addEventListener('click', function(){
      dryRunBtn.disabled = true;
      dryRunBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Dry Run...';
      fetch('<?= base_url('accounting/accounts/auto-assign-parents') ?>?dry_run=1', {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r=>r.json()).then(data=>{
          dryRunBtn.disabled = false;
          dryRunBtn.innerHTML = '<i class="bi bi-search me-1"></i>Dry Run';
          if (!data.success) { alert('Dry run failed'); return; }
          const changes = data.data.changes || [];
          let msg = 'Dry Run Parent Assignments (total '+changes.length+'):\n';
          changes.slice(0,50).forEach(c=>{ msg += c.code+' -> '+c.parent_code+'\n'; });
          if (changes.length > 50) msg += '... ('+(changes.length-50)+' more)';
          alert(msg);
        }).catch(err=>{
          dryRunBtn.disabled = false;
          dryRunBtn.innerHTML = '<i class="bi bi-search me-1"></i>Dry Run';
          alert('Error: '+err);
        });
    });
  }

  // Global filters (search/type/active)
  const accSearch = document.getElementById('accSearch');
  const accTypeFilter = document.getElementById('accTypeFilter');
  const accActiveFilter = document.getElementById('accActiveFilter');
  function applyAccountFilters(){
    const q = (accSearch && accSearch.value ? accSearch.value.toLowerCase() : '').trim();
    const t = (accTypeFilter && accTypeFilter.value) || '';
    const a = (accActiveFilter && accActiveFilter.value) || '';
    document.querySelectorAll('table.coa-hier-table tbody tr').forEach(function(tr){
      let visible = true;
      if (t && (tr.dataset.type !== t)) visible = false;
      if (a !== '' && (tr.dataset.active !== a)) visible = false;
      if (q) {
        const code = (tr.dataset.code || '').toLowerCase();
        const name = (tr.dataset.name || '').toLowerCase();
        if (!code.includes(q) && !name.includes(q)) visible = false;
      }
      tr.style.display = visible ? '' : 'none';
    });
    // Auto-expand while searching for better visibility
    if (acc && q) { acc.querySelectorAll('.accordion-collapse').forEach(el=>{ if (!el.classList.contains('show')) new bootstrap.Collapse(el, {toggle:true}); }); }
  }
  if (accSearch) accSearch.addEventListener('input', applyAccountFilters);
  if (accTypeFilter) accTypeFilter.addEventListener('change', applyAccountFilters);
  if (accActiveFilter) accActiveFilter.addEventListener('change', applyAccountFilters);

  // CSV export of visible rows
  const allAccounts = <?= json_encode($accounts ?? []) ?>;
  const accountCodeById = {}; (allAccounts||[]).forEach(a=>{ accountCodeById[String(a.id)] = a.code; });
  const accExportCsvBtn = document.getElementById('accExportCsv');
  if (accExportCsvBtn) {
    accExportCsvBtn.addEventListener('click', function(){
      const rows = [["Code","Name","Type","Currency","Active","Parent","Lines","Balance"]];
      document.querySelectorAll('table.coa-hier-table tbody tr').forEach(function(tr){
        if (tr.style.display === 'none') return;
        const tds = tr.querySelectorAll('td');
        const code = tds[0]?.innerText?.trim() || '';
  const name = tds[1]?.innerText?.trim() || '';
        const currency = tds[2]?.innerText?.trim() || '';
        const active = tds[3]?.innerText?.trim() || '';
        const parent = accountCodeById[tr.dataset.parent] || '';
        const lines = tr.dataset.lines || '';
        const bal = tr.dataset.balance || '';
        rows.push([code,name,tr.dataset.type||'',currency,active,parent,lines,bal]);
      });
      const csv = rows.map(r=>r.map(f=>`"${(f||'').toString().replace(/"/g,'""')}"`).join(',')).join('\n');
      const blob = new Blob([csv], {type:'text/csv'}); const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download='accounts.csv'; a.click();
    });
  }
  const accResetBtn = document.getElementById('accResetFilters');
  if (accResetBtn) accResetBtn.addEventListener('click', function(){ if (accSearch) accSearch.value=''; if (accTypeFilter) accTypeFilter.value=''; if (accActiveFilter) accActiveFilter.value=''; applyAccountFilters(); });

  // Action handlers (Delete, Deactivate, Merge)
  document.querySelectorAll('table.coa-hier-table').forEach(function(tbl){
    tbl.addEventListener('click', function(e){
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const id = btn.getAttribute('data-id');
      const action = btn.getAttribute('data-action');
      if (!id) return;
      openReasonFlow(action, id, btn);
    });
  });

  // Tooltip init
  const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.forEach(function (tooltipTriggerEl) { new bootstrap.Tooltip(tooltipTriggerEl); });

  // Quick parent assignment form
  const quickChildSelect = document.getElementById('quickChildSelect');
  const quickParentSelect = document.getElementById('quickParentSelect');
  const quickSetParentBtn = document.getElementById('quickSetParentBtn');
  const currentParentDisplay = document.getElementById('currentParentDisplay');
  // allAccounts already defined above for CSV; if not, define fallback
  const allAccountsFallback = <?= json_encode($accounts ?? []) ?>;
  const allAccountsUse = Array.isArray(allAccounts) ? allAccounts : allAccountsFallback;
  
  if (quickChildSelect && quickParentSelect && quickSetParentBtn) {
    quickChildSelect.addEventListener('change', function() {
      const childId = quickChildSelect.value;
      const childOption = quickChildSelect.querySelector(`option[value="${childId}"]`);
      const childType = childOption ? childOption.getAttribute('data-type') : '';
      const currentParentId = childOption ? childOption.getAttribute('data-parent') : '0';
      
      // Clear and populate parent options
      quickParentSelect.innerHTML = '<option value="">— None (top-level) —</option>';
      
      if (childId && childType) {
        // Filter accounts of same type, excluding the child itself
        const validParents = allAccountsUse.filter(acc => 
          acc.type === childType && String(acc.id) !== String(childId)
        );
        
        validParents.forEach(acc => {
          const option = document.createElement('option');
          option.value = acc.id;
          option.textContent = acc.code + ' — ' + acc.name;
          // Pre-select current parent
          if (String(acc.id) === String(currentParentId)) {
            option.selected = true;
          }
          quickParentSelect.appendChild(option);
        });
        
        // Show current parent
        const currentParent = allAccountsUse.find(a => String(a.id) === String(currentParentId));
        if (currentParent) {
          currentParentDisplay.textContent = 'Current: ' + currentParent.code + ' — ' + currentParent.name;
        } else {
          currentParentDisplay.textContent = 'Current: (none)';
        }
        
        quickParentSelect.disabled = false;
        quickSetParentBtn.disabled = false;
      } else {
        quickParentSelect.disabled = true;
        quickSetParentBtn.disabled = true;
        currentParentDisplay.textContent = '';
      }
    });
    
    quickSetParentBtn.addEventListener('click', function() {
      const childId = quickChildSelect.value;
      const parentId = quickParentSelect.value;
      const childOption = quickChildSelect.querySelector(`option[value="${childId}"]`);
      const childType = childOption ? childOption.getAttribute('data-type') : '';
      
      if (!childId) {
        alert('Please select a child account');
        return;
      }
      
      quickSetParentBtn.disabled = true;
      quickSetParentBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Setting...';
      
      const body = new URLSearchParams();
      body.append('child_id', childId);
      body.append('new_parent_id', parentId || '');
      // If no parent selected, send the child's current type to keep it
      if (!parentId || parentId === '') {
        body.append('new_type', childType);
      }
      body.append('<?= csrf_token() ?>', '<?= csrf_hash() ?>');
      
      console.log('Sending reparent request:', {
        child_id: childId,
        new_parent_id: parentId || '',
        new_type: (!parentId || parentId === '') ? childType : '(not sent)'
      });
      
      fetch('<?= base_url('accounting/accounts/reparent') ?>', {
        method: 'POST',
        headers: {'X-Requested-With': 'XMLHttpRequest'},
        body: body
      })
      .then(r => r.json())
      .then(data => {
        console.log('Reparent response:', data);
        if (data && data.success) {
          location.reload();
        } else {
          alert('Error: ' + (data.message || 'Failed to set parent'));
          quickSetParentBtn.disabled = false;
          quickSetParentBtn.innerHTML = '<i class="bi bi-check2"></i> Set';
        }
      })
      .catch(err => {
        console.error('Reparent error:', err);
        alert('Network Error: ' + err);
        quickSetParentBtn.disabled = false;
        quickSetParentBtn.innerHTML = '<i class="bi bi-check2"></i> Set';
      });
    });
  }

  function openReasonFlow(action, accountId, btn){
    const modalEl = document.getElementById('reasonModal');
    if (!modalEl) return;
    const title = {
      'delete':'Delete Account',
      'deactivate':'Deactivate Account',
      'merge':'Merge Account'
    }[action] || 'Confirm Action';
    document.getElementById('reasonModalTitle').textContent = title;
    document.getElementById('reasonActionType').value = action;
    document.getElementById('reasonAccountId').value = accountId;
    document.getElementById('reasonTargetId').value = '';
    document.getElementById('reasonText').value='';
    document.getElementById('reasonSubmitLabel').textContent = 'Confirm';
    document.getElementById('reasonError').classList.add('d-none');
    if (action === 'merge') {
      document.getElementById('reasonLabel').textContent = 'Reason & Target Code';
      document.getElementById('reasonText').placeholder = 'Reason for merge; first line: target account CODE';
    } else {
      document.getElementById('reasonLabel').textContent = 'Reason';
      document.getElementById('reasonText').placeholder = 'Reason for '+action+' (audit log)';
    }
    const m = new bootstrap.Modal(modalEl); m.show();
    const submitBtn = document.getElementById('reasonSubmitBtn');
    submitBtn.onclick = function(){ performReasonAction(btn); };
  }

  function performReasonAction(originBtn){
    const action = document.getElementById('reasonActionType').value;
    const accountId = document.getElementById('reasonAccountId').value;
    const reasonRaw = document.getElementById('reasonText').value.trim();
    const errBox = document.getElementById('reasonError');
    if (reasonRaw === '') { errBox.textContent='Reason required'; errBox.classList.remove('d-none'); return; }
    let targetId = null; let reason = reasonRaw;
    if (action === 'merge') {
      const parts = reasonRaw.split(/\n|\r/).filter(p=>p.trim()!=='');
      if (parts.length === 0) { errBox.textContent='Provide target code in first line'; errBox.classList.remove('d-none'); return; }
      const targetCode = parts[0].trim();
      reason = parts.slice(1).join(' ').trim() || 'Merge '+targetCode;
      // Find target id by code
      document.querySelectorAll('table.coa-hier-table tr').forEach(function(r){
        const c = r.querySelector('td');
        if (c && c.textContent.trim() === targetCode) { targetId = r.getAttribute('data-id'); }
      });
      if (!targetId) { errBox.textContent='Target code not found: '+targetCode; errBox.classList.remove('d-none'); return; }
      if (targetId === accountId) { errBox.textContent='Target cannot be same as source'; errBox.classList.remove('d-none'); return; }
    }
    originBtn.disabled = true; originBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
    const modalEl = document.getElementById('reasonModal');
    const mInstance = bootstrap.Modal.getInstance(modalEl); if (mInstance) mInstance.hide();
    let url = '<?= base_url('accounting/accounts') ?>/'+accountId+'/'+(action==='delete'?'delete':(action==='deactivate'?'deactivate':'merge'));
    let body = 'reason='+encodeURIComponent(reason)+'&<?= csrf_token() ?>=<?= csrf_hash() ?>';
    if (action === 'merge') body += '&target_id='+encodeURIComponent(targetId);
    fetch(url,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Content-Type':'application/x-www-form-urlencoded'},body:body})
      .then(r=>r.json()).then(data=>{ if(data.success){ location.reload(); } else { alert(data.message||'Action failed'); originBtn.disabled=false; originBtn.innerHTML='<i class="bi '+(action==='delete'?'bi-trash':(action==='deactivate'?'bi-slash-circle':'bi-diagram-2'))+'"></i>'; } })
      .catch(err=>{ alert('Error: '+err); originBtn.disabled=false; originBtn.innerHTML='<i class="bi '+(action==='delete'?'bi-trash':(action==='deactivate'?'bi-slash-circle':'bi-diagram-2'))+'"></i>'; });
  }
  // Drag & Drop reparent
  const csrfName = '<?= csrf_token() ?>';
  const csrfValue = '<?= csrf_hash() ?>';
  let dragId = null; let dragType = null; let dragDescendants = [];
  function cleanDropHints(){
    document.querySelectorAll('tr.coa-row-drop-ok').forEach(r=>r.classList.remove('coa-row-drop-ok'));
    document.querySelectorAll('.coa-drop-top-level.coa-zone-active').forEach(z=>z.classList.remove('coa-zone-active'));
  }
  document.querySelectorAll('table.coa-hier-table tbody tr').forEach(function(tr){
    tr.addEventListener('dragstart', function(ev){
      dragId = tr.dataset.id; dragType = tr.dataset.type; tr.classList.add('coa-row-dragging');
      dragDescendants = collectDescendants(dragId);
      try { ev.dataTransfer.setData('text/plain', dragId); } catch(e){}
      ev.dataTransfer.effectAllowed = 'move';
    });
    tr.addEventListener('dragend', function(){ tr.classList.remove('coa-row-dragging'); cleanDropHints(); dragId=null; dragType=null; dragDescendants=[]; });
    tr.addEventListener('dragover', function(ev){
      if(!dragId) return; if(tr.dataset.id===dragId) return; if(tr.dataset.type!==dragType) return; if(dragDescendants.includes(tr.dataset.id)) return; ev.preventDefault(); tr.classList.add('coa-row-drop-ok');
    });
    tr.addEventListener('dragleave', function(){ tr.classList.remove('coa-row-drop-ok'); });
    tr.addEventListener('drop', function(ev){ if(!dragId) return; if(tr.dataset.type!==dragType) return; if(dragDescendants.includes(tr.dataset.id)) { ev.preventDefault(); alert('Cannot move under a descendant.'); return; } ev.preventDefault();
      postReparent(dragId, tr.dataset.id, null);
    });
  });
  document.querySelectorAll('.coa-drop-top-level').forEach(function(zone){
    zone.addEventListener('dragover', function(ev){ if(!dragId) return; if(zone.dataset.type!==dragType) return; ev.preventDefault(); zone.classList.add('coa-zone-active'); });
    zone.addEventListener('dragleave', function(){ zone.classList.remove('coa-zone-active'); });
    zone.addEventListener('drop', function(ev){ if(!dragId) return; if(zone.dataset.type!==dragType) return; ev.preventDefault();
      postReparent(dragId, '', zone.dataset.type);
    });
  });
  function postReparent(childId, newParentId, newType){
    const body = new URLSearchParams();
    body.append('child_id', childId);
    if (newParentId !== '' && newParentId !== null) { body.append('new_parent_id', newParentId); }
    else { body.append('new_parent_id', ''); body.append('new_type', newType || ''); }
    body.append(csrfName, csrfValue);
    // Send URLSearchParams object directly so the browser sets the correct
    // Content-Type (application/x-www-form-urlencoded); avoid toString() which
    // causes PHP to not populate $_POST in some environments leading to
    // missing child_id and 'Child not found' server response.
    fetch('<?= base_url('accounting/accounts/reparent') ?>', { method:'POST', headers:{'X-Requested-With':'XMLHttpRequest'}, body: body })
      .then(r=>r.json()).then(data=>{ if(data && data.success){ location.reload(); } else { alert(data.message||'Reparent failed'); cleanDropHints(); } })
      .catch(err=>{ alert('Error: '+err); cleanDropHints(); });
  }
  function collectDescendants(rootId){
    const rows = Array.from(document.querySelectorAll('table.coa-hier-table tbody tr'));
    const byParent = {};
    rows.forEach(r=>{ const pid = r.dataset.parent; if(pid){ (byParent[pid] = byParent[pid]||[]).push(r.dataset.id); } });
    const stack=[rootId]; const out=[];
    while(stack.length){ const cur=stack.pop(); (byParent[cur]||[]).forEach(k=>{ out.push(k); stack.push(k); }); }
    return out;
  }

  // Collapse/expand children toggle
  document.addEventListener('click', function(e){
    const btn = e.target.closest('.btn-toggle-children');
    if (!btn) return;
    const id = btn.getAttribute('data-id'); if (!id) return;
    const descendants = collectDescendants(id);
    if (!descendants.length) return;
    const isCollapsed = btn.classList.contains('collapsed');
    if (isCollapsed) {
      // expand: show all descendants
      descendants.forEach(did => { const r = document.querySelector('tr[data-id="'+did+'"]'); if (r) r.style.display = ''; });
      btn.classList.remove('collapsed');
      btn.innerHTML = '<i class="bi bi-chevron-down"></i>';
    } else {
      // collapse: hide all descendants
      descendants.forEach(did => { const r = document.querySelector('tr[data-id="'+did+'"]'); if (r) r.style.display = 'none'; });
      btn.classList.add('collapsed');
      btn.innerHTML = '<i class="bi bi-chevron-right"></i>';
    }
  });
});
</script>
<?= $this->endSection() ?>

<!-- Reparent Modal -->
<div class="modal fade" id="reparentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Set Parent Account</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="rpChildId">
        <div class="mb-2">
          <label class="form-label">Filter</label>
          <input type="text" class="form-control" id="rpFilter" placeholder="Search code or name">
        </div>
        <div class="mb-2">
          <label class="form-label">Parent</label>
          <select id="rpParent" class="form-select" size="8"></select>
          <div class="form-text">Pick a parent of the same type, or choose None to make top-level.</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-primary" id="rpSaveBtn"><i class="bi bi-check2"></i> Save</button>
      </div>
    </div>
  </div>
</div>

<style>
/* Color accents for major heads */
.accordion-button.coa-type-asset { border-left: 4px solid #198754; background-color: rgba(25,135,84,0.08); }
.accordion-button.coa-type-liability { border-left: 4px solid #fd7e14; background-color: rgba(253,126,20,0.08); }
.accordion-button.coa-type-equity { border-left: 4px solid #0dcaf0; background-color: rgba(13,202,240,0.08); }
.accordion-button.coa-type-revenue { border-left: 4px solid #0d6efd; background-color: rgba(13,110,253,0.08); }
.accordion-button.coa-type-expense { border-left: 4px solid #dc3545; background-color: rgba(220,53,69,0.08); }
</style>
<style>
/* Replace dash indentation with padding-based hierarchy display */
.coa-name { display:block; }
.coa-hier-table tbody td .coa-name { transition: padding-left .12s ease; }
.btn-toggle-children { padding:.18rem .35rem; font-size:.78rem; vertical-align:middle; }
.btn-toggle-children.collapsed { opacity:.8; }
</style>
<style>
/* Compact density for accounts table */
.table-compact th, .table-compact td { padding:.35rem .5rem !important; }
.table-compact { font-size:.88rem; }
</style>