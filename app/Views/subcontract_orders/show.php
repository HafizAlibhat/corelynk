<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<?php
    $st = $statuses[$order['status']] ?? ['label' => ucfirst($order['status']), 'badge' => 'secondary'];
    $isDraft    = $order['status'] === 'draft';
    $isConfirmed = $order['status'] === 'confirmed';
    $isIssued   = in_array($order['status'], ['issued', 'partial_return']);
    $isDone     = $order['status'] === 'done';
    $isCancelled = $order['status'] === 'cancelled';
?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0">
                <i class="bi bi-arrow-repeat me-2"></i>
                <?= esc($order['order_number']) ?>
                <span class="badge bg-<?= $st['badge'] ?> ms-2"><?= $st['label'] ?></span>
            </h1>
            <small class="text-muted">
                Vendor: <strong><?= esc($order['vendor_name'] ?? '—') ?></strong>
                &middot; Service: <strong><?= esc($order['service_product_name'] ?? '—') ?></strong>
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('/subcontract-orders') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back
            </a>

            <?php if ($isDraft): ?>
                <a href="<?= base_url('/subcontract-orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                <form method="post" action="<?= base_url('/subcontract-orders/' . $order['id'] . '/confirm') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-primary btn-sm" onclick="return confirm('Confirm this order?')">
                        <i class="bi bi-check-circle me-1"></i> Confirm
                    </button>
                </form>
            <?php endif; ?>

            <?php if ($isConfirmed): ?>
                <a href="<?= base_url('/subcontract-orders/' . $order['id'] . '/edit') ?>" class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
                <form method="post" action="<?= base_url('/subcontract-orders/' . $order['id'] . '/issue-materials') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Issue materials to vendor? This will deduct stock from the warehouse.')">
                        <i class="bi bi-box-arrow-right me-1"></i> Issue Materials
                    </button>
                </form>
            <?php endif; ?>

            <?php if (!$isDone && !$isCancelled): ?>
                <form method="post" action="<?= base_url('/subcontract-orders/' . $order['id'] . '/cancel') ?>" class="d-inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm('Cancel this order? Stock will be restored if materials were issued.')">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Order Details -->
        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0"><i class="bi bi-info-circle me-2"></i>Order Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Vendor:</dt>
                                <dd class="col-sm-7"><strong><?= esc($order['vendor_name'] ?? '—') ?></strong></dd>

                                <dt class="col-sm-5">Service:</dt>
                                <dd class="col-sm-7"><?= esc($order['service_product_name'] ?? '—') ?> <small class="text-muted"><?= esc($order['service_product_code'] ?? '') ?></small></dd>

                                <dt class="col-sm-5">Quantity:</dt>
                                <dd class="col-sm-7"><strong><?= number_format((float)$order['quantity'], 0) ?></strong> <?= esc($order['service_unit'] ?? 'pcs') ?></dd>

                                <dt class="col-sm-5">Unit Price:</dt>
                                <dd class="col-sm-7"><?= esc($order['currency']) ?> <?= number_format((float)$order['unit_price'], 2) ?></dd>

                                <dt class="col-sm-5">Total:</dt>
                                <dd class="col-sm-7"><strong class="text-primary fs-5"><?= esc($order['currency']) ?> <?= number_format((float)$order['total'], 2) ?></strong></dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-5">Issued Date:</dt>
                                <dd class="col-sm-7"><?= $order['issued_date'] ? date('M j, Y', strtotime($order['issued_date'])) : '<span class="text-muted">Not yet issued</span>' ?></dd>

                                <dt class="col-sm-5">Expected Return:</dt>
                                <dd class="col-sm-7"><?= $order['expected_return_date'] ? date('M j, Y', strtotime($order['expected_return_date'])) : '—' ?></dd>

                                <dt class="col-sm-5">Actual Return:</dt>
                                <dd class="col-sm-7"><?= $order['actual_return_date'] ? date('M j, Y', strtotime($order['actual_return_date'])) : '—' ?></dd>

                                <dt class="col-sm-5">Created:</dt>
                                <dd class="col-sm-7 small text-muted"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></dd>
                            </dl>
                        </div>
                    </div>
                    <?php if (!empty($order['notes'])): ?>
                        <hr>
                        <p class="mb-0 text-muted"><i class="bi bi-sticky me-1"></i> <?= nl2br(esc($order['notes'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Material Lines -->
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0"><i class="bi bi-box-seam me-2"></i>Materials</h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($isIssued): ?>
                        <!-- Receive form -->
                        <form method="post" action="<?= base_url('/subcontract-orders/' . $order['id'] . '/receive-materials') ?>" id="receiveForm">
                            <?= csrf_field() ?>
                    <?php endif; ?>

                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Description</th>
                                    <th class="text-end">Qty Sent</th>
                                    <th class="text-end">Qty Received</th>
                                    <th class="text-end">Qty Scrap</th>
                                    <th class="text-end">Pending</th>
                                    <?php if ($isIssued): ?>
                                        <th class="text-end">Receive Now</th>
                                        <th class="text-end">Scrap Now</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($lines)): ?>
                                    <tr><td colspan="8" class="text-center text-muted py-3">No material lines.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($lines as $line): ?>
                                        <?php
                                            $pending = (float)$line['qty_sent'] - (float)$line['qty_received'] - (float)$line['qty_scrap'];
                                            $pendingClass = $pending > 0 ? 'text-warning' : 'text-success';
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= esc($line['product_name'] ?? '—') ?></strong>
                                                <?php if (!empty($line['variant_art_number'])): ?>
                                                    <br><small class="text-muted"><?= esc($line['variant_art_number']) ?> — <?= esc($line['variant_name'] ?? '') ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small"><?= esc($line['description'] ?? '') ?></td>
                                            <td class="text-end"><?= number_format((float)$line['qty_sent'], 0) ?></td>
                                            <td class="text-end text-success"><?= number_format((float)$line['qty_received'], 0) ?></td>
                                            <td class="text-end text-danger"><?= number_format((float)$line['qty_scrap'], 0) ?></td>
                                            <td class="text-end <?= $pendingClass ?>"><strong><?= number_format($pending, 0) ?></strong></td>
                                            <?php if ($isIssued): ?>
                                                <td>
                                                    <input type="hidden" name="line_id[]" value="<?= $line['id'] ?>">
                                                    <input type="number" name="qty_received[]" class="form-control form-control-sm text-end" min="0" max="<?= $pending ?>" value="0" style="width:80px;margin-left:auto">
                                                </td>
                                                <td>
                                                    <input type="number" name="qty_scrap[]" class="form-control form-control-sm text-end" min="0" max="<?= $pending ?>" value="0" style="width:80px;margin-left:auto">
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="2">Totals</th>
                                    <th class="text-end"><?= number_format($totals['total_sent'], 0) ?></th>
                                    <th class="text-end text-success"><?= number_format($totals['total_received'], 0) ?></th>
                                    <th class="text-end text-danger"><?= number_format($totals['total_scrap'], 0) ?></th>
                                    <th class="text-end"><?= number_format($totals['total_sent'] - $totals['total_received'] - $totals['total_scrap'], 0) ?></th>
                                    <?php if ($isIssued): ?>
                                        <th colspan="2"></th>
                                    <?php endif; ?>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <?php if ($isIssued): ?>
                            <div class="p-3">
                                <button type="submit" class="btn btn-success" onclick="return confirm('Record received and scrap quantities?')">
                                    <i class="bi bi-box-arrow-in-left me-1"></i> Record Receipt
                                </button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right column: Status timeline -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0"><i class="bi bi-clock-history me-2"></i>Status</h5>
                </div>
                <div class="card-body">
                    <?php
                        $statusFlow = ['draft', 'confirmed', 'issued', 'partial_return', 'done'];
                        $currentIdx = array_search($order['status'], $statusFlow);
                        if ($currentIdx === false && $order['status'] === 'cancelled') {
                            $currentIdx = -1;
                        }
                    ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($statusFlow as $idx => $step): ?>
                            <?php
                                $stepInfo = $statuses[$step] ?? ['label' => ucfirst($step), 'badge' => 'secondary'];
                                $isActive = ($step === $order['status']);
                                $isPast = ($currentIdx !== false && $idx < $currentIdx);
                                $iconClass = $isPast ? 'bi-check-circle-fill text-success' : ($isActive ? 'bi-circle-fill text-primary' : 'bi-circle text-muted');
                            ?>
                            <div class="d-flex align-items-center gap-2 <?= $isActive ? 'fw-bold' : ($isPast ? '' : 'text-muted') ?>">
                                <i class="bi <?= $iconClass ?>"></i>
                                <span><?= $stepInfo['label'] ?></span>
                            </div>
                        <?php endforeach; ?>
                        <?php if ($isCancelled): ?>
                            <div class="d-flex align-items-center gap-2 fw-bold text-danger">
                                <i class="bi bi-x-circle-fill"></i>
                                <span>Cancelled</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <?php if (!empty($lines)): ?>
                <div class="card mb-3">
                    <div class="card-header py-2">
                        <h5 class="card-title mb-0"><i class="bi bi-bar-chart me-2"></i>Progress</h5>
                    </div>
                    <div class="card-body">
                        <?php
                            $totalSent = $totals['total_sent'];
                            $totalBack = $totals['total_received'] + $totals['total_scrap'];
                            $pct = $totalSent > 0 ? round(($totals['total_received'] / $totalSent) * 100) : 0;
                            $scrapPct = $totalSent > 0 ? round(($totals['total_scrap'] / $totalSent) * 100) : 0;
                        ?>
                        <div class="mb-2 small">
                            <span class="text-success"><?= number_format($totals['total_received'], 0) ?> received</span>
                            &middot;
                            <span class="text-danger"><?= number_format($totals['total_scrap'], 0) ?> scrap</span>
                            &middot;
                            <span class="text-muted"><?= number_format($totalSent - $totalBack, 0) ?> pending</span>
                        </div>
                        <div class="progress" style="height: 20px;">
                            <div class="progress-bar bg-success" style="width: <?= $pct ?>%" title="Received"><?= $pct ?>%</div>
                            <div class="progress-bar bg-danger" style="width: <?= $scrapPct ?>%" title="Scrap"><?= $scrapPct ?>%</div>
                        </div>
                        <?php if ($totalSent > 0): ?>
                            <div class="mt-2 small text-muted">
                                Yield: <strong><?= $pct ?>%</strong>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
