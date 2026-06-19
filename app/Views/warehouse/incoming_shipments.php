<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Incoming Stock<?= $this->endSection() ?>

<?= $this->section('content') ?>
<style>
    .po-card { min-width: 300px; }
    .po-card .table th, .po-card .table td { padding: .45rem; vertical-align: middle; }
    .po-meta .badge { display:block; margin-bottom:.35rem; }
    .create-grn-btn { min-width:92px; }
    .incoming-summary-card {
        border: 1px solid var(--bs-border-color);
        border-radius: .6rem;
        padding: .7rem .8rem;
        background: rgba(59,130,246,.06);
    }
    .incoming-summary-label {
        font-size: .72rem;
        text-transform: uppercase;
        letter-spacing: .04em;
        opacity: .85;
    }
    .incoming-summary-value {
        font-size: 1.1rem;
        font-weight: 700;
        line-height: 1.2;
    }
    .incoming-flow-note {
        border: 1px dashed var(--bs-border-color);
        border-radius: .6rem;
        padding: .65rem .8rem;
        font-size: .86rem;
        background: rgba(16,185,129,.05);
    }
    .status-chip {
        display: inline-flex;
        align-items: center;
        border-radius: 999px;
        font-size: .72rem;
        padding: .2rem .55rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .status-chip.confirmed { background: rgba(14,165,233,.18); color: #38bdf8; }
    .status-chip.completed { background: rgba(34,197,94,.18); color: #22c55e; }
    .status-chip.draft { background: rgba(251,191,36,.2); color: #f59e0b; }
    .status-chip.other { background: rgba(148,163,184,.2); color: #94a3b8; }
    @media (max-width:767px) { .po-grid-cols { flex-direction: column; } }
</style>

<div class="card">
    <div class="card-body">
        <?php $req = service('request'); ?>
        <?php
            $buildPoUrl = static function ($poId): string {
                return site_url('purchases/po/' . rawurlencode((string) ((int) $poId)));
            };

            $rowsForSummary = $flatList ?? [];
            if (empty($rowsForSummary) && !empty($poList)) {
                foreach ($poList as $po) {
                    foreach (($po['lines'] ?? []) as $ln) {
                        $rowsForSummary[] = [
                            'po_id' => (int) ($po['po_id'] ?? 0),
                            'pending_qty' => (float) ($ln['pending_qty'] ?? 0),
                            'expected_date' => $po['expected_date'] ?? null,
                        ];
                    }
                }
            }

            $summaryPoIds = [];
            $summaryPendingLines = 0;
            $summaryPendingQty = 0.0;
            $summaryEtaNext7 = 0;
            $todayTs = strtotime(date('Y-m-d'));
            $next7Ts = strtotime('+7 days', $todayTs);

            foreach ($rowsForSummary as $sr) {
                $poId = (int) ($sr['po_id'] ?? 0);
                if ($poId > 0) {
                    $summaryPoIds[$poId] = true;
                }
                $pending = max(0, (float) ($sr['pending_qty'] ?? 0));
                if ($pending > 0) {
                    $summaryPendingLines++;
                }
                $summaryPendingQty += $pending;
                $etaRaw = (string) ($sr['expected_date'] ?? '');
                if ($etaRaw !== '') {
                    $etaTs = strtotime($etaRaw);
                    if ($etaTs !== false && $etaTs >= $todayTs && $etaTs <= $next7Ts) {
                        $summaryEtaNext7++;
                    }
                }
            }

            $summaryPoCount = count($summaryPoIds);
        ?>

        <form id="dateFilterForm" class="row g-2 mb-3 align-items-end" method="get" action="<?= site_url('warehouse/incoming-shipments') ?>">
            <div class="col-auto">
                <label class="form-label mb-1 small">Start date</label>
                <input type="date" name="start_date" id="start_date" class="form-control form-control-sm" value="<?= esc($req->getGet('start_date') ?? '') ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">End date</label>
                <input type="date" name="end_date" id="end_date" class="form-control form-control-sm" value="<?= esc($req->getGet('end_date') ?? '') ?>">
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">&nbsp;</label><br>
                <button class="btn btn-sm btn-primary" type="submit">Filter</button>
                <a class="btn btn-sm btn-outline-secondary ms-1" href="<?= site_url('warehouse/incoming-shipments') ?>">Clear</a>
            </div>
            <div class="col-auto">
                <label class="form-label mb-1 small">Quick Filters</label><br>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('week')">Next Week</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('month')">Next 1 Month</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('4month')">Next 4 Months</button>
                    <button type="button" class="btn btn-outline-secondary" onclick="setDateRange('6month')">Next 6 Months</button>
                </div>
            </div>
            <div class="col-auto ms-auto">
                <?php $viewMode = esc($req->getGet('view') ?? 'grid'); ?>
                <label class="form-label mb-1 small">View</label><br>
                <div class="btn-group btn-group-sm" role="group">
                    <a href="<?= site_url('warehouse/incoming-shipments') ?>?<?= http_build_query(array_merge($req->getGet(), ['view' => 'grid'])) ?>" class="btn btn-sm <?= $viewMode === 'grid' ? 'btn-primary' : 'btn-outline-secondary' ?>">Grid</a>
                    <a href="<?= site_url('warehouse/incoming-shipments') ?>?<?= http_build_query(array_merge($req->getGet(), ['view' => 'list'])) ?>" class="btn btn-sm <?= $viewMode === 'list' ? 'btn-primary' : 'btn-outline-secondary' ?>">List</a>
                </div>
            </div>
        </form>

        <div class="row g-2 mb-3">
            <div class="col-12 col-md-3">
                <div class="incoming-summary-card">
                    <div class="incoming-summary-label">Open Incoming POs</div>
                    <div class="incoming-summary-value"><?= number_format($summaryPoCount) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="incoming-summary-card">
                    <div class="incoming-summary-label">Pending Lines</div>
                    <div class="incoming-summary-value"><?= number_format($summaryPendingLines) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="incoming-summary-card">
                    <div class="incoming-summary-label">Pending Qty</div>
                    <div class="incoming-summary-value"><?= number_format($summaryPendingQty, 2) ?></div>
                </div>
            </div>
            <div class="col-12 col-md-3">
                <div class="incoming-summary-card">
                    <div class="incoming-summary-label">ETA Next 7 Days</div>
                    <div class="incoming-summary-value"><?= number_format($summaryEtaNext7) ?></div>
                </div>
            </div>
        </div>

        <div class="incoming-flow-note mb-3">
            <strong>Warehouse flow:</strong> Open PO -> verify pending lines -> create GRN on arrival -> pending quantity updates automatically.
        </div>

        <script>
        function setDateRange(type) {
            const today = new Date();
            let start = new Date(today);
            let end = new Date(today);
            if (type === 'week') {
                end.setDate(end.getDate() + 7);
            } else if (type === 'month') {
                end.setMonth(end.getMonth() + 1);
            } else if (type === '4month') {
                end.setMonth(end.getMonth() + 4);
            } else if (type === '6month') {
                end.setMonth(end.getMonth() + 6);
            }
            document.getElementById('start_date').value = start.toISOString().slice(0,10);
            document.getElementById('end_date').value = end.toISOString().slice(0,10);
            document.getElementById('dateFilterForm').submit();
        }
        </script>

        <?php if (($viewMode ?? ($req->getGet('view') ?? 'grid')) === 'list'): ?>
            <?php $rows = $flatList ?? []; ?>
            <div class="table-responsive">
                <table class="table table-sm table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>PO</th>
                            <th>Vendor</th>
                            <th>ETA</th>
                            <th>Product</th>
                            <th class="text-end">Ordered</th>
                            <th class="text-end">Received</th>
                            <th class="text-end">Pending</th>
                            <th>Related SOs</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php $ridx=1; foreach ($rows as $r): ?>
                        <tr<?= ($r['pending_qty'] > 0 ? ' class="table-warning"' : '') ?>>
                            <td><?= $ridx++ ?></td>
                            <td><a href="<?= esc($buildPoUrl($r['po_id'])) ?>"><?= esc($r['po_number']) ?></a></td>
                            <td><?= esc($r['vendor_name']) ?></td>
                            <td><?= !empty($r['expected_date']) ? date('d M Y', strtotime($r['expected_date'])) : '&mdash;' ?></td>
                            <td>
                                <div class="fw-semibold"><?= esc($r['product_name']) ?></div>
                                <?php if (!empty($r['variant_name'])): ?>
                                    <div class="small text-muted">
                                        Variant: <?= esc($r['variant_name']) ?>
                                        <?php if (!empty($r['variant_art_number'])): ?>
                                            <span class="ms-1">(<?= esc($r['variant_art_number']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end"><?= number_format($r['ordered_qty'],2) ?></td>
                            <td class="text-end"><?= number_format($r['received_qty'],2) ?></td>
                            <td class="text-end fw-bold"><?= number_format($r['pending_qty'],2) ?></td>
                            <td>
                                <?php if (!empty($r['related_sos'])): foreach ($r['related_sos'] as $so): ?>
                                    <span class="badge bg-light text-dark border me-1">SO: <?= esc($so) ?></span>
                                <?php endforeach; else: ?>
                                    <span class="text-muted small">None</span>
                                <?php endif; ?>
                            </td>
                            <td><button class="btn btn-sm btn-success create-grn-btn" data-po-line="<?= (int)$r['line_id'] ?>" data-po="<?= (int)$r['po_id'] ?>">Create GRN</button></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif (!empty($poList)): ?>
            <div class="row g-3 po-grid-cols">
            <?php foreach ($poList as $po): ?>
                <div class="col-12 col-md-6">
                    <div class="card po-card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center bg-light">
                            <div>
                                <strong><a href="<?= esc($buildPoUrl($po['po_id'])) ?>" class="text-decoration-none">PO: <?= esc($po['po_number']) ?></a></strong>
                                <div class="po-meta mt-2">
                                    <?php $st = strtolower((string)($po['status'] ?? '')); ?>
                                    <span class="status-chip <?= in_array($st, ['confirmed','completed','draft'], true) ? $st : 'other' ?>">Status: <?= esc($st !== '' ? $st : 'unknown') ?></span>
                                    <span class="badge bg-info text-white">Vendor: <?= esc($po['vendor_name']) ?></span>
                                </div>
                            </div>
                            <div class="text-end small text-muted">
                                <div>ETA</div>
                                <div><?= !empty($po['expected_date']) ? date('d M Y', strtotime($po['expected_date'])) : 'N/A' ?></div>
                            </div>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light small">
                                        <tr>
                                            <th style="width:4%;">#</th>
                                            <th>Product</th>
                                            <th class="text-end" style="width:10%;">Ordered</th>
                                            <th class="text-end" style="width:10%;">Received</th>
                                            <th class="text-end" style="width:10%;">Pending</th>
                                            <th style="width:18%;">Related SOs</th>
                                            <th style="width:10%;">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php $i=1; foreach ($po['lines'] as $ln): ?>
                                        <tr<?= ($ln['pending_qty'] > 0 ? ' class="table-warning"' : '') ?>>
                                            <td><?= $i++ ?></td>
                                            <td>
                                                <div class="fw-semibold"><?= esc($ln['product_name']) ?></div>
                                                <?php if (!empty($ln['variant_name'])): ?>
                                                    <div class="small text-muted">
                                                        Variant: <?= esc($ln['variant_name']) ?>
                                                        <?php if (!empty($ln['variant_art_number'])): ?>
                                                            <span class="ms-1">(<?= esc($ln['variant_art_number']) ?>)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?= number_format($ln['ordered_qty'], 2) ?></td>
                                            <td class="text-end"><?= number_format($ln['received_qty'], 2) ?></td>
                                            <td class="text-end fw-bold"><?= number_format($ln['pending_qty'], 2) ?></td>
                                            <td>
                                                <?php if (!empty($ln['related_sos'])): ?>
                                                    <?php foreach ($ln['related_sos'] as $so): ?>
                                                        <span class="badge bg-light text-dark border me-1">SO: <?= esc($so) ?></span>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <span class="text-muted small">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-success create-grn-btn" data-po-line="<?= (int)$ln['line_id'] ?>" data-po="<?= (int)$po['po_id'] ?>" data-product="<?= esc($ln['product_name']) ?>" data-pending="<?= number_format($ln['pending_qty'],2) ?>">Create GRN</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted mb-3">
                <p class="mb-0">No incoming stock found for the selected date range.</p>
                <p class="small">Open purchase orders with expected receipts will appear here.</p>
            </div>

            <?php if (!empty($debugRawLines)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-danger text-white">Debug: Sample purchase_order_lines (first 5 rows)</div>
                    <div class="card-body">
                        <pre style="font-size: 0.9em; white-space: pre-wrap;"><?php echo htmlspecialchars(print_r($debugRawLines, true)); ?></pre>
                        <div class="text-muted">Share this output with your developer to help fix the incoming stock view.</div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($candidatePos)): ?>
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="mb-2">Candidate Purchase Orders (sample)</h6>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>PO</th><th>Vendor</th><th>Status</th><th>Expected</th></tr>
                                </thead>
                                <tbody>
                                <?php foreach ($candidatePos as $cp): ?>
                                    <tr>
                                        <td><a href="<?= esc($buildPoUrl($cp['id'])) ?>"><?= esc($cp['po_number']) ?></a></td>
                                        <td><?= esc($cp['vendor_name']) ?></td>
                                        <?php $cst = strtolower((string)($cp['status'] ?? '')); ?>
                                        <td><span class="status-chip <?= in_array($cst, ['confirmed','completed','draft'], true) ? $cst : 'other' ?>"><?= esc($cst !== '' ? $cst : 'unknown') ?></span></td>
                                        <td><?= !empty($cp['expected_date']) ? date('d M Y', strtotime($cp['expected_date'])) : '&mdash;' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-muted">If POs above exist but incoming lines are not shown, the PO lines may be fully received or the line schema differs (column names). Provide a sample PO/PO line if you want me to debug further.</div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
