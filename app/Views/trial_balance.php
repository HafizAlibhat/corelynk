<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Trial Balance<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid py-3">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-1">Trial Balance</h4>
            <p class="text-muted small mb-0">As of <?= date('F j, Y') ?></p>
        </div>
        <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Entry
        </a>
    </div>

    <?php if (isset($stats['error'])): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            Error loading trial balance: <?= esc($stats['error']) ?>
        </div>
    <?php else: ?>

        <!-- Summary Cards - Compact -->
        <div class="row g-2 mb-3">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="text-muted small">Total Debit</div>
                        <h5 class="mb-0 text-primary">₨ <?= number_format($totals['debit'], 2) ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="text-muted small">Total Credit</div>
                        <h5 class="mb-0 text-danger">₨ <?= number_format($totals['credit'], 2) ?></h5>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body p-3">
                        <div class="text-muted small">Total Accounts</div>
                        <h5 class="mb-0"><?= $stats['total_accounts'] ?? 0 ?></h5>
                        <small class="text-muted"><?= $stats['total_entries'] ?? 0 ?> entries</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-<?= ($stats['balanced'] ?? false) ? 'success' : 'danger' ?>">
                    <div class="card-body p-3">
                        <div class="text-muted small">Balance Status</div>
                        <h5 class="mb-0 text-<?= ($stats['balanced'] ?? false) ? 'success' : 'danger' ?>">
                            <?= ($stats['balanced'] ?? false) ? '✓ Balanced' : '✗ Unbalanced' ?>
                        </h5>
                        <?php if (!($stats['balanced'] ?? false)): ?>
                            <small class="text-danger">Diff: ₨ <?= number_format(abs($totals['debit'] - $totals['credit']), 2) ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit Findings - Compact Alert -->
        <?php if (!empty($auditFindings)): ?>
            <?php 
            $criticalCount = count(array_filter($auditFindings, fn($f) => $f['severity'] === 'critical'));
            $errorCount = count(array_filter($auditFindings, fn($f) => $f['severity'] === 'error'));
            ?>
            <div class="alert alert-<?= $criticalCount > 0 ? 'danger' : 'warning' ?> border-start border-4 mb-3">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <h6 class="alert-heading mb-1">
                            <i class="bi bi-robot me-1"></i>
                            AI Audit Alert: <?= count($auditFindings) ?> Issues Detected
                        </h6>
                        <p class="mb-2 small">
                            <?= $criticalCount ?> Critical, <?= $errorCount ?> Errors, 
                            <?= count($auditFindings) - $criticalCount - $errorCount ?> Warnings
                        </p>
                        <button class="btn btn-sm btn-outline-dark" type="button" data-bs-toggle="collapse" data-bs-target="#auditDetails">
                            <i class="bi bi-chevron-down me-1"></i> View Details & Suggestions
                        </button>
                    </div>
                </div>
                
                <!-- Collapsible Audit Details -->
                <div class="collapse mt-3" id="auditDetails">
                    <hr>
                    <?php foreach ($auditFindings as $index => $finding): ?>
                        <div class="card mb-2">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-start gap-2 mb-2">
                                    <span class="badge bg-<?= ['critical'=>'danger','error'=>'warning','warning'=>'info','info'=>'secondary'][$finding['severity']] ?>">
                                        <?= strtoupper($finding['severity']) ?>
                                    </span>
                                    <span class="badge bg-light text-dark"><?= esc($finding['category']) ?></span>
                                    <strong class="flex-grow-1"><?= esc($finding['title']) ?></strong>
                                </div>
                                <p class="mb-2 small"><?= esc($finding['description']) ?></p>
                                <div class="small">
                                    <strong>Suggestions:</strong>
                                    <ul class="mb-0 ps-3">
                                        <?php foreach (array_slice($finding['suggestions'], 0, 3) as $suggestion): ?>
                                            <li><?= esc($suggestion) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <?php if (isset($finding['entry_id'])): ?>
                                    <a href="<?= base_url('accounting/journal-entry/edit/' . $finding['entry_id']) ?>" class="btn btn-sm btn-primary mt-2">
                                        <i class="bi bi-pencil me-1"></i> Fix Entry
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Clean Tabular Trial Balance -->
        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 100px;">Code</th>
                                <th>Account Name</th>
                                <th class="text-end" style="width: 180px;">Debit (₨)</th>
                                <th class="text-end" style="width: 180px;">Credit (₨)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($trialBalance)): ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No transactions found
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($trialBalance as $type => $accounts): ?>
                                    <!-- Type Header Row -->
                                    <tr class="table-secondary">
                                        <td colspan="4" class="fw-bold py-2">
                                            <i class="bi bi-<?= ['Asset'=>'wallet2', 'Liability'=>'credit-card', 'Equity'=>'bank', 'Revenue'=>'graph-up-arrow', 'Expense'=>'receipt'][$type] ?? 'folder' ?> me-2"></i>
                                            <?= esc($type) ?>
                                            <span class="badge bg-dark ms-2"><?= count($accounts) ?></span>
                                        </td>
                                    </tr>
                                    
                                    <!-- Account Rows -->
                                    <?php foreach ($accounts as $account): ?>
                                        <tr>
                                            <td class="font-monospace text-primary fw-bold"><?= esc($account['code']) ?></td>
                                            <td><?= esc($account['name']) ?></td>
                                            <td class="text-end font-monospace">
                                                <?php if ($account['debit_balance'] > 0): ?>
                                                    <?= number_format($account['debit_balance'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end font-monospace">
                                                <?php if ($account['credit_balance'] > 0): ?>
                                                    <?= number_format($account['credit_balance'], 2) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                                
                                <!-- Totals Row -->
                                <tr class="table-dark fw-bold">
                                    <td colspan="2" class="text-end">TOTAL</td>
                                    <td class="text-end font-monospace"><?= number_format($totals['debit'], 2) ?></td>
                                    <td class="text-end font-monospace"><?= number_format($totals['credit'], 2) ?></td>
                                </tr>
                                
                                <!-- Difference Row (if unbalanced) -->
                                <?php if (!($stats['balanced'] ?? false)): ?>
                                    <tr class="table-warning">
                                        <td colspan="2" class="text-end">
                                            <i class="bi bi-exclamation-triangle me-2"></i>
                                            <strong>DIFFERENCE</strong>
                                        </td>
                                        <td colspan="2" class="text-end">
                                            <span class="badge bg-danger fs-6">
                                                ₨ <?= number_format(abs($totals['debit'] - $totals['credit']), 2) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-3 d-flex gap-2">
            <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-primary">
                <i class="bi bi-journal-text me-1"></i> View All Journals
            </a>
            <a href="<?= base_url('accounting/accounts') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-folder me-1"></i> Manage Accounts
            </a>
            <button class="btn btn-outline-success" onclick="window.print()">
                <i class="bi bi-printer me-1"></i> Print
            </button>
        </div>

    <?php endif; ?>
</div>

<style>
@media print {
    .btn, .alert { display: none; }
    .card { border: none; box-shadow: none; }
}
</style>
<?= $this->endSection() ?>
