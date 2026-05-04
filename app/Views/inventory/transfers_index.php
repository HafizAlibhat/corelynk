<?php /** @var array $records */ /** @var int $page */ /** @var int $totalPages */ /** @var int $total */ /** @var string $search */ ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Internal Stock Transfers<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
.it-page { max-width:1200px; margin:0 auto; }
.badge-trf { background:rgba(99,102,241,.15); color:#a5b4fc; border:1px solid rgba(99,102,241,.3); font-weight:600; font-size:.75rem; letter-spacing:.03em; }
.reason-cell { max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.transfer-arrow { color:#94a3b8; font-size:.85rem; }
</style>

<div class="it-page">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-arrow-left-right text-indigo me-2" style="color:#818cf8"></i>Internal Stock Transfers</h4>
            <div class="text-muted small">Move stock between locations and keep a full audit trail.</div>
        </div>
        <a href="<?= base_url('/inventory/transfers/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i>New Transfer
        </a>
    </div>

    <!-- Search -->
    <form method="get" action="<?= base_url('/inventory/transfers') ?>" class="mb-3">
        <div class="input-group" style="max-width:380px;">
            <span class="input-group-text border-0 bg-transparent"><i class="bi bi-search text-muted"></i></span>
            <input type="text" name="q" class="form-control border-0 shadow-none bg-transparent"
                   placeholder="Search by transfer #, product, reason…"
                   value="<?= esc($search) ?>">
            <?php if ($search): ?>
                <a href="<?= base_url('/inventory/transfers') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <?php if (empty($records)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-arrow-left-right" style="font-size:2.5rem; opacity:.3; display:block; margin-bottom:1rem;"></i>
                    <?= $search ? 'No transfers match your search.' : 'No internal transfers yet. Click <strong>New Transfer</strong> to get started.' ?>
                </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" style="font-size:.88rem;">
                    <thead class="table-light text-secondary" style="font-size:.8rem; text-transform:uppercase; letter-spacing:.04em;">
                        <tr>
                            <th class="ps-3">Transfer #</th>
                            <th>Date</th>
                            <th>Product</th>
                            <th>From</th>
                            <th class="px-1 text-center transfer-arrow"></th>
                            <th>To</th>
                            <th class="text-end">Qty</th>
                            <th>Reason</th>
                            <th>By</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $r): ?>
                        <tr>
                            <td class="ps-3">
                                <span class="badge badge-trf"><?= esc($r['transfer_number']) ?></span>
                            </td>
                            <td class="text-muted small">
                                <?= date('d-m-Y', strtotime($r['created_at'])) ?>
                                <div style="font-size:.7rem; color:#64748b"><?= date('H:i', strtotime($r['created_at'])) ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= esc($r['product_name']) ?></div>
                                <?php if ($r['variant_name']): ?>
                                    <small class="text-muted"><?= esc($r['variant_name']) ?> &middot; <?= esc($r['art_number']) ?></small>
                                <?php endif; ?>
                                <?php if ($r['product_code']): ?>
                                    <small class="text-secondary d-block">[<?= esc($r['product_code']) ?>]</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= esc($r['from_location']) ?></div>
                                <small class="text-muted"><?= esc($r['from_warehouse']) ?></small>
                            </td>
                            <td class="text-center px-1">
                                <i class="bi bi-arrow-right" style="color:#6366f1; font-size:1rem;"></i>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?= esc($r['to_location']) ?></div>
                                <small class="text-muted"><?= esc($r['to_warehouse']) ?></small>
                            </td>
                            <td class="text-end fw-bold" style="color:#34d399;">
                                <?= number_format((float)$r['quantity'], 2) ?>
                            </td>
                            <td class="reason-cell" title="<?= esc($r['reason']) ?>">
                                <span class="text-muted small"><?= esc($r['reason']) ?></span>
                            </td>
                            <td class="small text-muted"><?= esc($r['created_by_name'] ?: '—') ?></td>
                            <td class="text-end pe-3">
                                <a href="<?= base_url('/inventory/transfers/' . $r['id']) ?>"
                                   class="btn btn-sm btn-outline-secondary py-0 px-2">
                                    <i class="bi bi-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="d-flex justify-content-between align-items-center px-3 py-2 border-top small text-muted">
                <span>Showing page <?= $page ?> of <?= $totalPages ?> &middot; <?= number_format($total) ?> records</span>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($p = max(1, $page - 2); $p <= min($totalPages, $page + 2); $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>&q=<?= urlencode($search) ?>"><?= $p ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

</div>
<?= $this->endSection() ?>
