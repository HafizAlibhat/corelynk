<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Trial Balance<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card trial-balance-card">
                <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="d-flex align-items-center justify-content-center rounded-circle bg-primary bg-opacity-10" style="width: 44px; height: 44px;">
                            <i class="bi bi-calculator fs-5 text-primary"></i>
                        </div>
                        <div>
                            <h5 class="mb-0 fw-semibold">Trial Balance</h5>
                            <small class="text-muted">As of <?= date('F j, Y') ?></small>
                        </div>
                    </div>
                    <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-lg me-1"></i> New Entry
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($stats['error'])): ?>
                        <div class="alert alert-danger modern-alert">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading trial balance: <?= esc($stats['error']) ?>
                        </div>
                    <?php else: ?>
                        
                        <!-- Clean Summary Stats -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <div class="card border-0 bg-primary bg-opacity-10 h-100">
                                    <div class="card-body">
                                        <small class="text-muted d-block mb-1">Total Debit</small>
                                        <h4 class="mb-0 fw-bold">PKR <?= number_format($totals['debit'], 2) ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-danger bg-opacity-10 h-100">
                                    <div class="card-body">
                                        <small class="text-muted d-block mb-1">Total Credit</small>
                                        <h4 class="mb-0 fw-bold">PKR <?= number_format($totals['credit'], 2) ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 bg-light h-100">
                                    <div class="card-body">
                                        <small class="text-muted d-block mb-1">Total Accounts</small>
                                        <h4 class="mb-0 fw-bold"><?= $stats['total_accounts'] ?? 0 ?></h4>
                                        <small class="text-muted"><?= $stats['total_entries'] ?? 0 ?> entries</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card border-0 <?= ($stats['balanced'] ?? false) ? 'bg-success' : 'bg-warning' ?> bg-opacity-10 h-100">
                                    <div class="card-body">
                                        <small class="text-muted d-block mb-1">Status</small>
                                        <h5 class="mb-0 fw-bold <?= ($stats['balanced'] ?? false) ? 'text-success' : 'text-warning' ?>">
                                            <?= ($stats['balanced'] ?? false) ? 'Balanced' : 'Unbalanced' ?>
                                        </h5>
                                        <?php if (!($stats['balanced'] ?? false)): ?>
                                            <small class="text-muted">Diff: PKR <?= number_format(abs($totals['debit'] - $totals['credit']), 2) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Intelligent Audit Panel -->
                        <?php if (!empty($auditFindings)): ?>
                        <div class="card border-start border-4 <?= in_array('critical', array_column($auditFindings, 'severity')) ? 'border-danger' : (in_array('error', array_column($auditFindings, 'severity')) ? 'border-warning' : 'border-info') ?> mb-3">
                            <div class="card-header bg-white d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-robot fs-4"></i>
                                    <div>
                                        <h6 class="mb-0 fw-semibold">Intelligent Audit Analysis</h6>
                                        <small class="text-muted">AI-powered accounting error detection</small>
                                    </div>
                                </div>
                                <span class="badge bg-danger"><?= count($auditFindings) ?> Issues Found</span>
                            </div>
                            <div class="card-body">
                                <div class="accordion" id="auditAccordion">
                                    <?php foreach ($auditFindings as $index => $finding): ?>
                                        <div class="accordion-item border mb-2">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?= $index > 0 ? 'collapsed' : '' ?> d-flex align-items-center" type="button" data-bs-toggle="collapse" data-bs-target="#finding<?= $index ?>" aria-expanded="<?= $index === 0 ? 'true' : 'false' ?>">
                                                    <div class="d-flex align-items-center gap-3 w-100">
                                                        <!-- Severity Badge -->
                                                        <?php
                                                        $badgeClass = [
                                                            'critical' => 'bg-danger',
                                                            'error' => 'bg-warning text-dark',
                                                            'warning' => 'bg-info text-dark',
                                                            'info' => 'bg-secondary'
                                                        ][$finding['severity']] ?? 'bg-secondary';
                                                        $icon = [
                                                            'critical' => 'x-circle-fill',
                                                            'error' => 'exclamation-triangle-fill',
                                                            'warning' => 'exclamation-circle-fill',
                                                            'info' => 'info-circle-fill'
                                                        ][$finding['severity']] ?? 'info-circle-fill';
                                                        ?>
                                                        <span class="badge <?= $badgeClass ?>">
                                                            <i class="bi bi-<?= $icon ?> me-1"></i>
                                                            <?= strtoupper($finding['severity']) ?>
                                                        </span>
                                                        
                                                        <!-- Category Badge -->
                                                        <span class="badge bg-dark bg-opacity-10 text-dark"><?= esc($finding['category']) ?></span>
                                                        
                                                        <!-- Title -->
                                                        <span class="fw-semibold flex-grow-1"><?= esc($finding['title']) ?></span>
                                                        
                                                        <!-- Affected Accounts Count -->
                                                        <?php if (!empty($finding['accounts'])): ?>
                                                            <span class="badge bg-primary bg-opacity-10 text-primary">
                                                                <?= count($finding['accounts']) ?> Account(s)
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                </button>
                                            </h2>
                                            <div id="finding<?= $index ?>" class="accordion-collapse collapse <?= $index === 0 ? 'show' : '' ?>" data-bs-parent="#auditAccordion">
                                                <div class="accordion-body">
                                                    <!-- Description -->
                                                    <div class="mb-3">
                                                        <h6 class="text-muted small mb-1">DESCRIPTION</h6>
                                                        <p class="mb-0"><?= esc($finding['description']) ?></p>
                                                    </div>
                                                    
                                                    <!-- Impact -->
                                                    <div class="mb-3">
                                                        <h6 class="text-muted small mb-1">IMPACT</h6>
                                                        <div class="alert alert-<?= $finding['severity'] === 'critical' || $finding['severity'] === 'error' ? 'danger' : 'warning' ?> alert-sm mb-0">
                                                            <i class="bi bi-lightning-fill me-2"></i>
                                                            <?= esc($finding['impact']) ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Suggestions -->
                                                    <div class="mb-3">
                                                        <h6 class="text-muted small mb-2">
                                                            <i class="bi bi-lightbulb-fill text-warning me-1"></i>
                                                            RECOMMENDED ACTIONS
                                                        </h6>
                                                        <ol class="mb-0 ps-3">
                                                            <?php foreach ($finding['suggestions'] as $suggestion): ?>
                                                                <li class="mb-1"><?= esc($suggestion) ?></li>
                                                            <?php endforeach; ?>
                                                        </ol>
                                                    </div>
                                                    
                                                    <!-- Affected Accounts -->
                                                    <?php if (!empty($finding['accounts'])): ?>
                                                    <div class="mb-3">
                                                        <h6 class="text-muted small mb-2">AFFECTED ACCOUNTS</h6>
                                                        <div class="d-flex flex-wrap gap-2">
                                                            <?php foreach ($finding['accounts'] as $accountCode): ?>
                                                                <span class="badge bg-light text-dark border font-monospace"><?= esc($accountCode) ?></span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Action Buttons -->
                                                    <div class="d-flex gap-2 mt-3">
                                                        <?php if (isset($finding['entry_id'])): ?>
                                                            <a href="<?= base_url('accounting/journal-entry/edit/' . $finding['entry_id']) ?>" class="btn btn-sm btn-primary">
                                                                <i class="bi bi-pencil me-1"></i> Fix Entry #<?= esc($finding['entry_number'] ?? $finding['entry_id']) ?>
                                                            </a>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="alert('Detailed analysis coming soon!')">
                                                            <i class="bi bi-graph-up me-1"></i> View Details
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="alert('Mark as reviewed')">
                                                            <i class="bi bi-check-lg me-1"></i> Mark Reviewed
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                
                                <!-- Audit Summary Footer -->
                                <div class="mt-3 pt-3 border-top">
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Critical Issues</small>
                                            <strong class="text-danger"><?= count(array_filter($auditFindings, fn($f) => $f['severity'] === 'critical')) ?></strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Errors</small>
                                            <strong class="text-warning"><?= count(array_filter($auditFindings, fn($f) => $f['severity'] === 'error')) ?></strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Warnings</small>
                                            <strong class="text-info"><?= count(array_filter($auditFindings, fn($f) => $f['severity'] === 'warning')) ?></strong>
                                        </div>
                                        <div class="col-md-3">
                                            <small class="text-muted d-block">Info</small>
                                            <strong class="text-secondary"><?= count(array_filter($auditFindings, fn($f) => $f['severity'] === 'info')) ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success border-start border-4 border-success mb-3">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-shield-check fs-4"></i>
                                <div>
                                    <strong>No Issues Detected</strong>
                                    <p class="mb-0 small">The intelligent audit system found no errors or unusual patterns in your accounting data.</p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Simple Search & Filters -->
                        <div class="card border mb-3">
                            <div class="card-body py-2">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-6">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                                            <input id="tbSearch" type="text" class="form-control" placeholder="Search by code or name...">
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="tbNonZero">
                                            <label class="form-check-label small" for="tbNonZero">Non-zero only</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="checkbox" id="tbCollapseTypes">
                                            <label class="form-check-label small" for="tbCollapseTypes">Collapse all</label>
                                        </div>
                                        <span class="badge bg-secondary ms-2">
                                            <span id="tbCountTotal"><?= $stats['total_accounts'] ?? 0 ?></span> shown
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trial Balance Table -->
                        <?php if (empty($trialBalance)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="bi bi-calculator"></i>
                                </div>
                                <h5 class="empty-title">No Account Activity</h5>
                                <p class="empty-text">
                                    Create journal entries to see account balances in the trial balance.
                                </p>
                                <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Create First Entry
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 10%;">Code</th>
                                            <th style="width: 45%;">Account Name</th>
                                            <th style="width: 20%;" class="text-end">Debit (PKR)</th>
                                            <th style="width: 20%;" class="text-end">Credit (PKR)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($trialBalance as $type => $accounts): ?>
                                            <!-- Type Header Row -->
                                            <tr class="type-header table-secondary" data-type="<?= esc($type) ?>" style="cursor: pointer;">
                                                <td colspan="4">
                                                    <div class="d-flex align-items-center justify-content-between">
                                                        <strong>
                                                            <i class="bi bi-<?= $type === 'Asset' ? 'wallet2' : ($type === 'Liability' ? 'credit-card' : ($type === 'Equity' ? 'bank' : ($type === 'Revenue' ? 'graph-up-arrow' : 'receipt'))) ?> me-2"></i>
                                                            <?= esc($type) ?>
                                                            <span class="badge bg-dark ms-2"><?= count($accounts) ?></span>
                                                        </strong>
                                                        <button type="button" class="btn btn-sm btn-link text-dark text-decoration-none p-0 type-toggle" data-type="<?= esc($type) ?>">
                                                            <i class="bi bi-chevron-up"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                            
                                            <!-- Account Rows -->
                                            <?php foreach ($accounts as $account): ?>
                                                <tr class="account-row" data-type="<?= esc($type) ?>" data-code="<?= esc($account['code']) ?>" data-name="<?= esc($account['name']) ?>" data-debit="<?= (float)($account['debit_balance'] ?? 0) ?>" data-credit="<?= (float)($account['credit_balance'] ?? 0) ?>">
                                                    <td class="font-monospace fw-bold text-primary"><?= esc($account['code']) ?></td>
                                                    <td>
                                                        <div><?= esc($account['name']) ?></div>
                                                        <small class="text-muted"><?= esc($account['currency_code'] ?? 'PKR') ?></small>
                                                    </td>
                                                    <td class="text-end font-monospace">
                                                        <?php if ($account['debit_balance'] > 0): ?>
                                                            <strong><?= number_format($account['debit_balance'], 2) ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-end font-monospace">
                                                        <?php if ($account['credit_balance'] > 0): ?>
                                                            <strong><?= number_format($account['credit_balance'], 2) ?></strong>
                                                        <?php else: ?>
                                                            <span class="text-muted">—</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endforeach; ?>
                                        
                                        <!-- Totals Row -->
                                        <tr class="table-dark fw-bold">
                                            <td colspan="2" class="text-end">TOTALS</td>
                                            <td class="text-end font-monospace"><?= number_format($totals['debit'], 2) ?></td>
                                            <td class="text-end font-monospace"><?= number_format($totals['credit'], 2) ?></td>
                                        </tr>
                                        
                                        <!-- Balance Status Row -->
                                        <tr class="<?= ($stats['balanced'] ?? false) ? 'table-success' : 'table-warning' ?>">
                                            <td colspan="2">
                                                <strong>
                                                    <i class="bi bi-<?= ($stats['balanced'] ?? false) ? 'check-circle-fill' : 'exclamation-triangle-fill' ?> me-2"></i>
                                                    <?= ($stats['balanced'] ?? false) ? 'Books Balanced' : 'Out of Balance' ?>
                                                </strong>
                                            </td>
                                            <td colspan="2" class="text-end">
                                                <?php if (!($stats['balanced'] ?? false)): ?>
                                                    <span class="badge bg-danger">Difference: PKR <?= number_format(abs($totals['debit'] - $totals['credit']), 2) ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-success"><i class="bi bi-check-lg"></i> Verified</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                        
                    <?php endif; ?>
                    
                    <div class="mt-3 text-center">
                        <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-secondary me-2">
                            <i class="bi bi-journal-text"></i> View All Journals
                        </a>
                        <a href="<?= base_url('accounting/accounts') ?>" class="btn btn-outline-primary">
                            <i class="bi bi-wallet2"></i> Manage Accounts
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        const searchInput = document.getElementById('tbSearch');
        const nonZero = document.getElementById('tbNonZero');
        const collapseAll = document.getElementById('tbCollapseTypes');
        const countDisplay = document.getElementById('tbCountTotal');

        function applyFilters(){
            const q = (searchInput?.value || '').trim().toLowerCase();
            const nz = !!(nonZero && nonZero.checked);
            const rows = Array.from(document.querySelectorAll('.account-row'));
            let visibleCount = 0;
            
            rows.forEach(row => {
                const code = (row.dataset.code || '').toLowerCase();
                const name = (row.dataset.name || '').toLowerCase();
                const debit = parseFloat(row.dataset.debit || '0');
                const credit = parseFloat(row.dataset.credit || '0');
                const type = row.dataset.type || '';
                const header = document.querySelector('.type-header[data-type="' + CSS.escape(type) + '"]');
                const isCollapsed = header?.classList.contains('collapsed');
                
                let ok = (!q || code.includes(q) || name.includes(q));
                if (nz) ok = ok && (debit > 0 || credit > 0);
                if (isCollapsed) ok = false;
                
                row.style.display = ok ? '' : 'none';
                if (ok) visibleCount++;
            });
            
            if (countDisplay) countDisplay.textContent = visibleCount;
        }

        // Bind inputs
        searchInput?.addEventListener('input', applyFilters);
        nonZero?.addEventListener('change', applyFilters);

        // Type toggle buttons
        document.querySelectorAll('.type-toggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const type = btn.getAttribute('data-type') || '';
                const header = document.querySelector('.type-header[data-type="' + CSS.escape(type) + '"]');
                header?.classList.toggle('collapsed');
                const icon = btn.querySelector('i');
                if (icon) icon.className = header?.classList.contains('collapsed') ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
                applyFilters();
            });
        });

        // Click type header to toggle
        document.querySelectorAll('.type-header').forEach(header => {
            header.addEventListener('click', () => {
                header.classList.toggle('collapsed');
                const type = header.getAttribute('data-type') || '';
                const btn = document.querySelector('.type-toggle[data-type="' + CSS.escape(type) + '"]');
                const icon = btn?.querySelector('i');
                if (icon) icon.className = header.classList.contains('collapsed') ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
                applyFilters();
            });
        });

        // Collapse/expand all
        collapseAll?.addEventListener('change', () => {
            const collapsed = !!collapseAll.checked;
            document.querySelectorAll('.type-header').forEach(h => {
                h.classList.toggle('collapsed', collapsed);
                const t = h.getAttribute('data-type') || '';
                const ic = document.querySelector('.type-toggle[data-type="' + CSS.escape(t) + '"] i');
                if (ic) ic.className = collapsed ? 'bi bi-chevron-down' : 'bi bi-chevron-up';
            });
            applyFilters();
        });

        // Initial render
        applyFilters();
    });
    </script>

<style>
/* Clean, minimal styling */
.card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    border-radius: 12px;
}

.type-header {
    transition: background-color 0.2s;
}

.type-header:hover {
    background-color: #f8f9fa !important;
}

.type-header.collapsed + .account-row {
    display: none;
}

.account-row {
    transition: background-color 0.15s;
}

.account-row:hover {
    background-color: #f8f9fa;
}
</style>

<?= $this->endSection() ?>