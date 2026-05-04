<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Simple Journal<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="bi bi-journal-plus"></i> Simple Journal Entry</h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= esc($error) ?></div>
                    <?php endif; ?>
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= esc($message) ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="<?= base_url('simple-journal') ?>">
                        <?= csrf_field() ?>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Date *</label>
                                <input type="date" name="date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Description</label>
                                <input type="text" name="memo" class="form-control" placeholder="Journal entry description">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Debit Account *</label>
                                <select name="debit_account" class="form-select" required>
                                    <option value="">Choose account to debit...</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= esc($account['code']) ?> - <?= esc($account['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Credit Account *</label>
                                <select name="credit_account" class="form-select" required>
                                    <option value="">Choose account to credit...</option>
                                    <?php foreach ($accounts as $account): ?>
                                        <option value="<?= $account['id'] ?>"><?= esc($account['code']) ?> - <?= esc($account['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Amount *</label>
                                <input type="number" step="0.01" name="amount" class="form-control" placeholder="0.00" required min="0.01">
                            </div>
                            <div class="col-md-8 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Post Entry
                                </button>
                                <a href="<?= base_url('accounting/journals') ?>" class="btn btn-outline-secondary ms-2">
                                    <i class="bi bi-list"></i> View All Journals
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent entries -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6><i class="bi bi-clock-history"></i> Recent Entries</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($entries)): ?>
                        <p class="text-muted">No entries yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th class="text-end">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($entries as $entry): ?>
                                        <tr>
                                            <td><?= $entry['id'] ?></td>
                                            <td><?= $entry['entry_date'] ?></td>
                                            <td><?= esc($entry['memo']) ?></td>
                                            <td class="text-end"><?= number_format($entry['total_debits'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6><i class="bi bi-list-ul"></i> Available Accounts</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($accounts)): ?>
                        <p class="text-muted">No accounts found.</p>
                    <?php else: ?>
                        <div class="small">
                            <?php foreach ($accounts as $account): ?>
                                <div class="border-bottom py-1">
                                    <strong><?= esc($account['code']) ?></strong><br>
                                    <?= esc($account['name']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>