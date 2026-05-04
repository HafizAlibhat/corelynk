<?php /** @var array $rows */ /** @var array $vendorMap */ /** @var array $types */ /** @var array $filters */ /** @var array $pagination */ ?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Stock Journal<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="main-content-wrapper">
    <div class="page-header mb-3">
        <div class="row align-items-center">
            <div class="col">
                <h1 class="page-title mb-0"><i class="bi bi-journal-text text-info me-2"></i>Stock Journal</h1>
                <p class="text-muted mb-0">Complete audit trail of all stock movements</p>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3 border-0 shadow-sm">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold small mb-1">Product</label>
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Name, SKU, or variant" value="<?= esc($filters['q'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1">Type</label>
                    <select name="type" class="form-select form-select-sm">
                        <option value="">All types</option>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= esc($t) ?>" <?= ($filters['type'] ?? '') === $t ? 'selected' : '' ?>><?= esc(ucwords(str_replace('_', ' ', $t))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1">From</label>
                    <input type="date" name="from" class="form-control form-control-sm" value="<?= esc($filters['from'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-bold small mb-1">To</label>
                    <input type="date" name="to" class="form-control form-control-sm" value="<?= esc($filters['to'] ?? '') ?>">
                </div>
                <div class="col-md-3 text-end">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel-fill me-1"></i>Filter</button>
                    <a href="<?= base_url('inventory/journal') ?>" class="btn btn-link btn-sm text-decoration-none">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Movements Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-2">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle" style="font-size:0.82rem;">
                    <thead class="table-dark">
                        <tr>
                            <th style="width:12%">Date</th>
                            <th style="width:22%">Product</th>
                            <th style="width:11%">Type</th>
                            <th class="text-end" style="width:8%">Qty</th>
                            <th style="width:8%">Unit Cost</th>
                            <th style="width:15%">Location</th>
                            <th style="width:12%">Reference</th>
                            <th style="width:12%">By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">No movements found</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $r):
                                $qty = (float)($r['qty_change'] ?? 0);
                                $isIn = $qty > 0;
                                $qtyClass = $isIn ? 'text-success' : 'text-danger';
                                $qtyIcon  = $isIn ? 'bi-arrow-up-circle-fill' : 'bi-arrow-down-circle-fill';

                                // Movement type badge
                                $typeMap = [
                                    'opening_stock' => ['bg-primary',   'Opening Stock'],
                                    'grn'           => ['bg-success',   'GRN Receipt'],
                                    'in'            => ['bg-success',   'Stock In'],
                                    'adjustment'    => ['bg-warning text-dark', 'Adjustment'],
                                    'shipment'      => ['bg-info text-dark',    'Shipment'],
                                    'scrap'         => ['bg-danger',    'Scrap'],
                                    'subcontract_out' => ['bg-secondary', 'Subcontract Out'],
                                    'subcontract_in'  => ['bg-secondary', 'Subcontract In'],
                                ];
                                $mvType = $r['movement_type'] ?? '';
                                $badgeInfo = $typeMap[$mvType] ?? ['bg-secondary', ucwords(str_replace('_', ' ', $mvType))];

                                // Reference link
                                $refType = $r['reference_type'] ?? '';
                                $refId   = $r['reference_id'] ?? '';
                                $refPublicId = trim((string)($r['reference_public_id'] ?? ''));
                                $refLink = '';
                                if ($refType === 'grn' && $refId) {
                                    $refToken = $refPublicId !== '' ? $refPublicId : $refId;
                                    $refLink = '<a href="' . esc(base_url("new-purchase-grns/detail/{$refToken}")) . '" class="text-info">GRN #' . esc($refId) . '</a>';
                                } elseif ($refType === 'stock_adjustment' && $refId) {
                                    $refLink = '<span class="text-muted">Adj Batch #' . esc($refId) . '</span>';
                                } elseif ($refType && $refId) {
                                    $refLink = '<span class="text-muted">' . esc(ucwords(str_replace('_', ' ', $refType))) . ' #' . esc($refId) . '</span>';
                                } else {
                                    $refLink = '<span class="text-muted">—</span>';
                                }

                                // Source label
                                $source = $r['stock_source'] ?? '';
                                $vendorId = $r['possible_vendor_id'] ?? null;
                                $sourceLabel = '';
                                if ($vendorId && isset($vendorMap[$vendorId])) {
                                    $sourceLabel = '<br><small class="text-muted">Vendor: ' . esc($vendorMap[$vendorId]) . '</small>';
                                }

                                // Variant display
                                $variantLabel = '';
                                if (!empty($r['variant_id'])) {
                                    $vn = $r['variant_name'] ?? '';
                                    $va = $r['variant_art'] ?? '';
                                    $variantLabel = $vn ?: $va;
                                }

                                // User
                                $userName = trim($r['user_name'] ?? '');
                            ?>
                            <tr>
                                <td class="py-1">
                                    <div><?= esc(date('d M Y', strtotime($r['created_at']))) ?></div>
                                    <small class="text-muted"><?= esc(date('H:i', strtotime($r['created_at']))) ?></small>
                                </td>
                                <td class="py-1">
                                    <div class="fw-semibold"><?= esc($r['product_name'] ?? 'Unknown') ?></div>
                                    <?php if ($variantLabel): ?>
                                        <small class="text-muted">Variant: <?= esc($variantLabel) ?></small>
                                    <?php endif; ?>
                                    <?php
                                        $code = $r['variant_art'] ?? $r['product_code'] ?? $r['product_sku'] ?? '';
                                        if ($code): ?>
                                        <br><small class="text-muted"><?= esc($code) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-1">
                                    <span class="badge <?= $badgeInfo[0] ?>" style="font-size:0.72rem;"><?= esc($badgeInfo[1]) ?></span>
                                    <?= $sourceLabel ?>
                                </td>
                                <td class="py-1 text-end fw-bold <?= $qtyClass ?>">
                                    <i class="bi <?= $qtyIcon ?>" style="font-size:0.7rem;"></i>
                                    <?= ($isIn ? '+' : '') . number_format($qty, 2) ?>
                                </td>
                                <td class="py-1">
                                    <?php if ($r['unit_cost'] !== null && (float)$r['unit_cost'] > 0): ?>
                                        <span><?= number_format((float)$r['unit_cost'], 2) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-1">
                                    <?php if ($r['warehouse_name']): ?>
                                        <div class="small"><?= esc($r['warehouse_name']) ?></div>
                                    <?php endif; ?>
                                    <?php if ($r['location_path']): ?>
                                        <small class="text-muted"><?= esc($r['location_path']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="py-1"><?= $refLink ?></td>
                                <td class="py-1">
                                    <?php if ($userName): ?>
                                        <small><?= esc($userName) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">System</small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php
            $totalPages = max(1, ceil($pagination['total'] / $pagination['per_page']));
            $curPage    = $pagination['page'];
        ?>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer border-0 d-flex justify-content-between align-items-center py-2" style="font-size:0.82rem;">
            <span class="text-muted"><?= number_format($pagination['total']) ?> movements</span>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php
                        $qp = $filters;
                        unset($qp['page']);
                        $qs = http_build_query($qp);
                    ?>
                    <li class="page-item <?= $curPage <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('inventory/journal') ?>?<?= $qs ?>&page=<?= $curPage - 1 ?>">Prev</a>
                    </li>
                    <?php
                        $start = max(1, $curPage - 2);
                        $end   = min($totalPages, $curPage + 2);
                        for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?= $i === $curPage ? 'active' : '' ?>">
                            <a class="page-link" href="<?= base_url('inventory/journal') ?>?<?= $qs ?>&page=<?= $i ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $curPage >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= base_url('inventory/journal') ?>?<?= $qs ?>&page=<?= $curPage + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?= $this->endSection() ?>
