<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>

<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0"><i class="bi bi-arrow-repeat me-2"></i>Subcontract Orders</h1>
            <small class="text-muted">Send materials to vendors for processing and track returns</small>
        </div>
        <a href="<?= base_url('/subcontract-orders/create') ?>" class="btn btn-primary">
            <i class="bi bi-plus-lg me-1"></i> New Subcontract Order
        </a>
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

    <!-- What is out at vendors right now -->
    <?php $sum = $summary ?? ['open_orders' => 0, 'qty_at_vendor' => 0, 'overdue' => 0]; ?>
    <div class="row g-2 mb-3">
        <div class="col-6 col-md-3">
            <a href="<?= base_url('/subcontract-orders?status=issued') ?>" class="text-decoration-none">
                <div class="card h-100"><div class="card-body py-2">
                    <div class="small text-muted">Open at vendor</div>
                    <div class="h4 mb-0"><?= (int) $sum['open_orders'] ?> <small class="text-muted fs-6">orders</small></div>
                </div></div>
            </a>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100"><div class="card-body py-2">
                <div class="small text-muted">Quantity at vendor</div>
                <div class="h4 mb-0"><?= number_format((float) $sum['qty_at_vendor'], 0) ?> <small class="text-muted fs-6">pcs</small></div>
            </div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card h-100 <?= $sum['overdue'] > 0 ? 'border-danger' : '' ?>"><div class="card-body py-2">
                <div class="small text-muted">Overdue returns</div>
                <div class="h4 mb-0 <?= $sum['overdue'] > 0 ? 'text-danger' : '' ?>"><?= (int) $sum['overdue'] ?></div>
            </div></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="get" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, vendor, product…" value="<?= esc($filters['search'] ?? '') ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Status</label>
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All</option>
                        <?php foreach ($statuses as $key => $s): ?>
                            <option value="<?= $key ?>" <?= ($filters['status'] ?? '') === $key ? 'selected' : '' ?>><?= $s['label'] ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-1">Vendor</label>
                    <select name="vendor_id" class="form-select form-select-sm">
                        <option value="">All Vendors</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= ($filters['vendor_id'] ?? '') == $v['id'] ? 'selected' : '' ?>><?= esc($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i> Filter</button>
                    <a href="<?= base_url('/subcontract-orders') ?>" class="btn btn-sm btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Orders Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="cl-table table table-hover table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Order #</th>
                        <th>Vendor</th>
                        <th>Service</th>
                        <th class="text-end">Qty</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                        <th>Status</th>
                        <th style="min-width:130px">Returned</th>
                        <th>Issued</th>
                        <th>Expected Return</th>
                        <th>Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="11" class="text-center text-muted py-4">No subcontract orders found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $o): ?>
                            <tr style="cursor:pointer" onclick="window.location='<?= base_url('/subcontract-orders/' . $o['id']) ?>'">
                                <td><strong><a href="<?= base_url('/subcontract-orders/' . $o['id']) ?>"><?= esc($o['order_number']) ?></a></strong></td>
                                <td><?= esc($o['vendor_name'] ?? '—') ?></td>
                                <td><?= esc($o['service_product_name'] ?? '—') ?></td>
                                <td class="text-end"><?= number_format((float)($o['quantity'] ?? 0), 0) ?></td>
                                <td class="text-end"><?= number_format((float)($o['unit_price'] ?? 0), 2) ?></td>
                                <td class="text-end"><strong><?= esc($o['currency'] ?? 'PKR') ?> <?= number_format((float)($o['total'] ?? 0), 2) ?></strong></td>
                                <td>
                                    <?php
                                        $st = $statuses[$o['status']] ?? ['label' => ucfirst($o['status']), 'badge' => 'secondary'];
                                        echo '<span class="badge bg-' . $st['badge'] . '">' . $st['label'] . '</span>';
                                    ?>
                                </td>
                                <td>
                                    <?php
                                        $sent = (float) ($o['qty_sent'] ?? 0);
                                        $back = (float) ($o['qty_received'] ?? 0);
                                        $scrap = (float) ($o['qty_scrap'] ?? 0);
                                        $pct = $sent > 0 ? round(($back / $sent) * 100) : 0;
                                        $scrapPct = $sent > 0 ? round(($scrap / $sent) * 100) : 0;
                                    ?>
                                    <?php if ($sent > 0): ?>
                                        <div class="progress" style="height:6px" title="<?= number_format($back, 0) ?> received, <?= number_format($scrap, 0) ?> scrap of <?= number_format($sent, 0) ?> sent">
                                            <div class="progress-bar bg-success" style="width:<?= $pct ?>%"></div>
                                            <div class="progress-bar bg-danger" style="width:<?= $scrapPct ?>%"></div>
                                        </div>
                                        <small class="text-muted"><?= number_format($back, 0) ?> / <?= number_format($sent, 0) ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">Not issued</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= $o['issued_date'] ? date('M j, Y', strtotime($o['issued_date'])) : '—' ?></td>
                                <?php
                                    $due = $o['expected_return_date'] ?? null;
                                    $isOverdue = $due && ($o['qty_pending'] ?? 0) > 0 && strtotime($due) < strtotime(date('Y-m-d'));
                                ?>
                                <td class="<?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                                    <?= $due ? date('M j, Y', strtotime($due)) : '—' ?>
                                    <?php if ($isOverdue): ?>
                                        <span class="badge bg-danger ms-1" title="Still at vendor past the expected return date">Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if (isset($pager)): ?>
        <div class="d-flex justify-content-center mt-3">
            <?= $pager->links('default', 'default_full') ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
