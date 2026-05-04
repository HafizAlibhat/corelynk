<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="container-fluid px-3 px-lg-5 dashboard dashboard-compact">
    <div class="card shadow-sm">
        <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2">
            <div class="d-flex align-items-center gap-3">
                <div class="section-icon section-accent-primary"><i class="bi bi-speedometer2"></i></div>
                <div>
                    <h5 class="mb-0 section-title">Company Dashboard</h5>
                    <small class="section-sub">Snapshot — key metrics, inventory health, and quick links</small>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <!-- Replaced module quick-links with timezone selector + digital clock -->
                <div class="d-flex align-items-center gap-3 dashboard-topbar">
                    <!-- Compact FX (shows before timezone select) -->
                    <div id="fxSmall" class="text-start dashboard-fx">
                        <div><strong id="usdPkr">USD: Loading…</strong> <small id="fxUpdated" class="text-muted ms-2">—</small></div>
                        <div class="small text-muted" id="fxSub">EUR: Loading… &middot; GBP: Loading…</div>
                    </div>
                    <label for="tzCountrySelect" class="visually-hidden">Timezone</label>
                    <select id="tzCountrySelect" class="form-select form-select-sm dashboard-tz-select">
                        <option value="Asia/Karachi" selected>Pakistan (Karachi)</option>
                        <option value="America/New_York">USA (New York)</option>
                        <option value="Europe/Berlin">Germany (Berlin)</option>
                        <option value="Europe/London">UK (London)</option>
                        <option value="Australia/Sydney">Australia (Sydney)</option>
                        <option value="Asia/Riyadh">Saudi (Riyadh)</option>
                        <option value="Asia/Dubai">UAE (Dubai)</option>
                        <option value="Asia/Jakarta">Indonesia (Jakarta)</option>
                        <option value="America/Sao_Paulo">Brazil (Sao Paulo)</option>
                        <option value="Europe/Warsaw">Poland (Warsaw)</option>
                        <option value="Africa/Algiers">Algeria (Algiers)</option>
                        <option value="Europe/Rome">Italy (Rome)</option>
                        <option value="Europe/Paris">France (Paris)</option>
                        <option value="Europe/Athens">Greece (Athens)</option>
                        <option value="America/Santiago">Chile (Santiago)</option>
                        <option value="Asia/Beirut">Lebanon (Beirut)</option>
                        <option value="Asia/Tbilisi">Georgia (Tbilisi)</option>
                        <option value="Asia/Shanghai">China (Shanghai)</option>
                        <option value="Africa/Johannesburg">South Africa (Johannesburg)</option>
                        <option value="Asia/Baghdad">Iraq (Baghdad)</option>
                        <option value="Africa/Cairo">Egypt (Cairo)</option>
                        <option value="Indian/Comoro">Comoros</option>
                    </select>
                    <div class="text-end dashboard-clock">
                        <div id="tzTime" class="fw-semibold fs-5" style="font-family:ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;letter-spacing:1px">--:--:-- AM</div>
                        <div id="tzDate" class="small text-muted">--</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($receivables_debug) && is_array($receivables_debug)): ?>
                <div class="alert alert-info small">Receivables (debug): <?= esc(json_encode($receivables_debug)) ?></div>
            <?php endif; ?>
            <div class="row g-4">
                <div class="col-12">
                    <div class="row g-3 row-cols-1 row-cols-md-2 row-cols-xl-4">
                        <?php $kpis = $kpi_cards ?? [
                            ['label' => 'Total Sales (MTD)', 'value' => '$0.00', 'hint' => 'vs last month: +0%', 'icon' => 'bi-cash-stack', 'link' => base_url('/reports'), 'linkLabel' => 'View Sales', 'bg' => 'linear-gradient(135deg,#eef2ff,#e0e7ff)', 'textColor' => 'text-dark'],
                            ['label' => 'Total Profit (MTD)', 'value' => '$0.00', 'hint' => 'Gross margin', 'icon' => 'bi-graph-up', 'link' => base_url('/reports'), 'linkLabel' => 'Profit Report', 'bg' => 'linear-gradient(135deg,#ecfdf5,#d1fae5)', 'textColor' => 'text-dark'],
                            ['label' => 'Vendor Payables', 'value' => '$0.00', 'hint' => 'Due next 7 days: $0', 'icon' => 'bi-wallet2', 'link' => base_url('/accounting/cheques'), 'linkLabel' => 'Vendor Payments', 'bg' => 'linear-gradient(135deg,#fffbeb,#fde68a)', 'textColor' => 'text-dark'],
                            ['label' => 'Customer Receivables', 'value' => isset($kpi_cards) ? ($kpi_cards[3]['value'] ?? '$0.00') : (isset($receivablesDisplay) ? $receivablesDisplay : '$0.00'), 'hint' => isset($kpi_cards) ? ($kpi_cards[3]['hint'] ?? 'Expected this week: $0') : (isset($receivablesCount) && $receivablesCount ? ($receivablesCount . ' currency(ies) with unpaid invoices') : 'No unpaid invoices'), 'icon' => 'bi-wallet', 'link' => base_url('/customers'), 'linkLabel' => 'Customer Collections', 'bg' => 'linear-gradient(135deg,#e0f2fe,#bae6fd)', 'textColor' => 'text-dark'],
                        ]; ?>
                        <?php foreach ($kpis as $card): ?>
                            <div class="col">
                                <div class="card h-100" style="background: <?= $card['bg'] ?>;">
                                    <div class="p-3 d-flex justify-content-between align-items-start <?= $card['textColor'] ?? '' ?>">
                                        <div>
                                            <div class="text-uppercase small text-muted"><?= esc($card['label']) ?></div>
                                            <div class="fs-3 fw-semibold"><?= esc($card['value']) ?></div>
                                            <div class="small text-muted"><?= esc($card['hint']) ?></div>
                                        </div>
                                        <div class="text-end"><i class="bi <?= esc($card['icon']) ?> fs-1"></i></div>
                                    </div>
                                    <div class="px-3 pb-3"><a href="<?= $card['link'] ?>" class="small"><?= esc($card['linkLabel']) ?></a></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Sales Performance (last 90 days)</h6>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-secondary active">Daily</button>
                                        <button class="btn btn-outline-secondary">Weekly</button>
                                    </div>
                                </div>
                                <canvas id="salesTimelineChart" height="160"></canvas>
                                <div class="mt-2 small text-muted">Top channel: Direct • Best day: —</div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Expenses vs Vendor Payments (6 months)</h6>
                                </div>
                                <canvas id="expensesPaymentsChart" height="160"></canvas>
                                <div class="mt-2 small text-muted">Avg cycle time: — days • Cleared %: —%</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="card p-3 h-100">
                                <h6 class="mb-3"><i class="bi bi-box-seam"></i> Stock Health</h6>
                                <?php
                                    $trending = $trending_products ?? [];
                                    $lowStock = count(array_filter($trending, fn($p) => ($p['current_stock'] ?? 0) > 0 && ($p['current_stock'] ?? 0) < 10));
                                    $outStock = count(array_filter($trending, fn($p) => ($p['current_stock'] ?? 0) <= 0));
                                    $totalSkus = count($trending);
                                ?>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Items low in stock</span><strong><?= number_format($lowStock) ?></strong></div>
                                <div class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Items out of stock</span><strong><?= number_format($outStock) ?></strong></div>
                                <div class="d-flex justify-content-between py-2"><span class="text-muted">Total SKUs</span><strong><?= number_format($totalSkus) ?></strong></div>
                                <hr />
                                <h6 class="mb-2">Pending Purchase Orders</h6>
                                <p class="mb-1 small">Count: <strong><?= number_format($open_pos ?? 0) ?></strong></p>
                                <p class="mb-1 small">Value: <strong>$<?= number_format($open_pos_value ?? 0.0, 2) ?></strong></p>
                                <div class="small text-muted">Next arrivals:</div>
                                <ul class="ps-3 mb-0 small">
                                    <?php $upcomingPos = $upcoming_pos ?? []; ?>
                                    <?php if (!empty($upcomingPos)): ?>
                                        <?php foreach ($upcomingPos as $po): ?>
                                            <li><span class="badge bg-light text-dark">PO#<?= esc($po['id']) ?></span> <?= esc($po['vendor_name'] ?? 'Vendor') ?> • <?= esc($po['order_date'] ? date('M j', strtotime($po['order_date'])) : 'TBD') ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li>No upcoming POs.</li>
                                    <?php endif; ?>
                                </ul>
                                <div class="mt-3"><a href="<?= base_url('/accounting/purchase-orders') ?>">Reorder Recommendations</a></div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card p-3 h-100">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="mb-0">Trending Items</h6>
                                    <button class="btn btn-sm btn-outline-secondary">Filter</button>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-compact align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>Product</th>
                                                <th class="text-end">Stock</th>
                                                <th class="text-end">Unit Cost</th>
                                                <th>Status</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($trending_products)): ?>
                                                <?php foreach ($trending_products as $p): ?>
                                                    <tr>
                                                        <td><span class="badge bg-light text-dark"><?= esc($p['code']) ?></span> <?= esc($p['name']) ?></td>
                                                        <td class="text-end"><?= number_format($p['current_stock'], 2) ?></td>
                                                        <td class="text-end">$<?= number_format($p['unit_cost'], 2) ?></td>
                                                        <td>
                                                            <?php if ($p['current_stock'] <= 0): ?>
                                                                <span class="badge bg-danger">Out of Stock</span>
                                                            <?php elseif ($p['current_stock'] < 10): ?>
                                                                <span class="badge bg-warning text-dark">Low Stock</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-success">Healthy</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="text-end"><a href="<?= base_url('/products') ?>" class="btn btn-xs btn-outline-secondary"><i class="bi bi-chevron-right"></i></a></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr><td colspan="5" class="text-center text-muted">No products found.</td></tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="mt-2"><a href="<?= base_url('/products') ?>">View All</a></div>
                            </div>
                        </div>
                        <div class="col-lg-3">
                            <div class="card p-3 h-100">
                                <h6 class="mb-3"><i class="bi bi-journal-text"></i> Business Alerts</h6>
                                <?php $alertItems = $alerts ?? []; ?>
                                <?php if (!empty($alertItems)): ?>
                                    <?php foreach ($alertItems as $alert): ?>
                                        <div class="card mb-2 alert-card" style="background: <?= esc($alert['bg'] ?? 'linear-gradient(135deg,#f8fafc,#e2e8f0)') ?>;">
                                            <div class="p-3 d-flex align-items-center gap-2"><i class="bi <?= esc($alert['icon']) ?> text-dark"></i><div><strong><?= esc($alert['title']) ?></strong><div class="small"><?= esc($alert['text']) ?></div></div></div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="card alert-card" style="background: linear-gradient(135deg,#f8fafc,#e2e8f0);">
                                        <div class="p-3 text-muted small">No critical alerts.</div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="card p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Revenue Overview — YoY</h6>
                                            <div class="btn btn-sm btn-outline-secondary">Export</div>
                                        </div>
                                        <canvas id="revenueYoYChart" height="160"></canvas>
                                        <div class="mt-2 small text-muted">YTD • YoY change: —%</div>
                                    </div>
                                </div>
                                <div class="col-lg-6">
                                    <div class="card p-3 h-100">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h6 class="mb-0">Expense Breakdown</h6>
                                        </div>
                                        <canvas id="expensePieChart" height="160"></canvas>
                                        <div class="mt-2 small text-muted">Opex • Purchases • Salaries • Utilities</div>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="card p-3">
                                        <h6>Sales / Activity (recent)</h6>
                                        <canvas id="activityChart" height="120"></canvas>
                                        <div class="mt-2 small text-muted">Shows recent activity (work orders) as a simple trend.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="card p-3 h-100">
                                <h6>Recent Activity</h6>
                                <div class="list-group list-group-flush">
                                    <?php if (!empty($recent_activity)): ?>
                                        <?php foreach($recent_activity as $r): ?>
                                            <a href="<?= base_url('/work-orders/view/'.$r['id']) ?>" class="list-group-item list-group-item-action px-0 border-0">
                                                <div class="fw-semibold"><?= esc($r['wo_number'] ?? 'WO#'.$r['id']) ?></div>
                                                <small class="text-muted d-block"><?= esc($r['status'] ?? '—') ?> • <?= esc($r['created_at'] ?? '—') ?></small>
                                                <small class="text-muted">Customer: <?= esc($r['customer_name'] ?? '—') ?> • SKU: <?= esc($r['product_code'] ?? '—') ?></small>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="text-muted">No recent activity.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('js') ?>
<style>
    .dashboard-compact .section-title { font-size: 1.05rem; }
    .dashboard-compact .section-sub { font-size: 0.76rem; }
    .dashboard-compact .card h6,
    .dashboard-compact .card-title { font-size: 0.9rem; }
    .dashboard-compact .small { font-size: 0.74rem !important; }
    .dashboard-compact .table,
    .dashboard-compact .table td,
    .dashboard-compact .table th { font-size: 0.76rem; }
    .dashboard-compact .row-cols-xl-4 > .col .card .fs-3 { font-size: 1.45rem !important; }

    /* DARK MODE — Dashboard KPI Cards HARD FIX */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card {
        background: linear-gradient(180deg, #111827, #0F172A) !important;
    }

    /* Reset inheritance */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card *,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card * {
        opacity: 1 !important;
        filter: none !important;
    }

    /* KPI labels */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card .text-uppercase,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card .text-uppercase,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card .stat-label,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card .stat-label {
        color: #9CA3AF !important;
        font-weight: 500 !important;
        letter-spacing: 0.05em !important;
        text-transform: uppercase !important;
    }

    /* KPI values */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card .fs-3,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card .fs-3,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card .stat-value,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card .stat-value {
        color: #F9FAFB !important;
        font-weight: 700 !important;
        font-size: 1.35rem !important;
    }

    /* Links inside cards */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card a,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card a {
        color: #E5E7EB !important;
        font-weight: 500 !important;
    }

    /* SVG ICON FIX (CRITICAL) */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card svg,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card svg {
        stroke: #E5E7EB !important;
        fill: none !important;
        opacity: 1 !important;
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card svg path,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card svg path {
        stroke: #E5E7EB !important;
        opacity: 1 !important;
    }

    /* Remove any card overlays */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card::before,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card::after {
        display: none !important;
    }

    /* HARDEST OVERRIDE: Force pure white text and icons in KPI cards (dark mode) */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"],
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        text-shadow: none !important;
        -webkit-text-stroke: 0px transparent !important;
        mix-blend-mode: normal !important;
        isolation: isolate !important;
    }

    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] *,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] * {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        opacity: 1 !important;
        filter: none !important;
        text-shadow: none !important;
        -webkit-text-stroke: 0px transparent !important;
        mix-blend-mode: normal !important;
    }

    /* Ensure numeric values, labels, and links are white */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .fs-3,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .fs-3,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .stat-value,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .stat-value,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .text-uppercase,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .text-uppercase {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }

    /* Links, small text */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] a,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] a,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .small,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .small {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
    }

    /* SVGs & icons: force white stroke/fill */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] svg,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] svg {
        stroke: #ffffff !important;
        fill: #ffffff !important;
        opacity: 1 !important;
        vector-effect: non-scaling-stroke !important;
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] svg path,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] svg path {
        stroke: #ffffff !important;
        fill: #ffffff !important;
        opacity: 1 !important;
    }

    /* Extra HARD overrides: catch any remaining outline/text-stroke or blend-mode effects */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .text-uppercase,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .text-uppercase,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .fs-3,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .fs-3,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .small,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .small,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .p-3,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .p-3 {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        -webkit-text-stroke-width: 0px !important;
        -webkit-text-stroke-color: transparent !important;
        text-shadow: none !important;
        mix-blend-mode: normal !important;
        background-clip: border-box !important;
        opacity: 1 !important;
    }

    /* ensure icon fonts (bootstrap icons) are filled white and not stroked */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] i[class^="bi"],
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] i[class*="bi-"] {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        -webkit-text-stroke-width: 0 !important;
        opacity: 1 !important;
    }

    /* If any pseudo elements are painting outlines, hide them */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] *::before,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] *::after,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] *::before,
    body[data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] *::after {
        display: none !important;
        content: none !important;
    }

    /* BROAD OVERRIDES: target the actual dashboard DOM path used by the view */
    body.theme-dark .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] *,
    body[data-theme="dark"] .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] * {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        -webkit-text-stroke-width: 0 !important;
        -webkit-text-stroke-color: transparent !important;
        opacity: 1 !important;
        filter: none !important;
        mix-blend-mode: normal !important;
        text-shadow: none !important;
    }

    /* Specifically target the label/value/link/icon elements in KPI cards */
    body.theme-dark .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] .text-uppercase,
    body[data-theme="dark"] .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] .text-uppercase,
    body.theme-dark .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] .fs-3,
    body[data-theme="dark"] .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] .fs-3,
    body.theme-dark .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] .small,
    body[data-theme="dark"] .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] .small,
    body.theme-dark .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] a,
    body[data-theme="dark"] .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] a,
    body.theme-dark .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] i,
    body[data-theme="dark"] .container-fluid .card .card-body .row-cols-xl-4 > .col > .card[style*="background"] i {
        color: #ffffff !important;
        -webkit-text-fill-color: #ffffff !important;
        -webkit-text-stroke-width: 0 !important;
        opacity: 1 !important;
    }
    /* DASHBOARD METRIC CARDS — DARK MODE ONLY (UI/UX ENHANCED) */
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"],
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] {
        /* Card container: do not change background, only text */
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .fs-3,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .fs-3,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .stat-value,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .stat-value {
        color: #F1F5F9 !important;
        font-weight: 700 !important;
        letter-spacing: 0.01em;
        text-shadow: 0 1px 2px rgba(0,0,0,.10);
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .text-uppercase,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .text-uppercase,
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .stat-label,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .stat-label {
        color: #94A3B8 !important;
        font-weight: 500 !important;
        letter-spacing: 0.04em !important;
        text-transform: uppercase !important;
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] .small,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] .small {
        color: #94A3B8 !important;
        font-weight: 400 !important;
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] a,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] a {
        color: #F1F5F9 !important;
        font-weight: 500 !important;
        text-decoration-color: #94A3B8 !important;
    }
    body.theme-dark .dashboard .row-cols-xl-4 > .col .card[style*="background"] i,
    [data-theme="dark"] .dashboard .row-cols-xl-4 > .col .card[style*="background"] i {
        color: #F1F5F9 !important;
        opacity: 0.95 !important;
        text-shadow: 0 1px 2px rgba(0,0,0,.10);
    }
    /* Dashboard header widgets: keep FX + timezone + clock aligned and readable */
    .dashboard-topbar { flex-wrap: wrap; align-items: center; }
    .dashboard-fx { min-width: 220px; font-size: .9rem; line-height: 1.15; }
    .dashboard-tz-select { width: 210px; }
    .dashboard-clock { min-width: 150px; }

    /* Make the update label less prominent */
    #fxUpdated { font-weight: 500; }

    /* Dark theme: ensure header widget text stays readable (do not touch light theme) */
    body.theme-dark #fxSmall,
    [data-theme="dark"] #fxSmall {
        color: #e2e8f0;
    }
    body.theme-dark #fxSmall .text-muted,
    [data-theme="dark"] #fxSmall .text-muted,
    body.theme-dark #tzDate.text-muted,
    [data-theme="dark"] #tzDate.text-muted {
        color: #94a3b8 !important;
    }
    body.theme-dark #tzTime,
    [data-theme="dark"] #tzTime {
        color: #f1f5f9;
    }
    body.theme-dark #tzCountrySelect,
    [data-theme="dark"] #tzCountrySelect,
    body.theme-dark .dashboard-tz-select,
    [data-theme="dark"] .dashboard-tz-select {
        background-color: #0f172a;
        color: #e2e8f0;
        border-color: #334155;
    }
    body.theme-dark #tzCountrySelect:focus,
    [data-theme="dark"] #tzCountrySelect:focus {
        border-color: #64748b;
        box-shadow: 0 0 0 .2rem rgba(100, 116, 139, .25);
    }

    /* Dark theme: KPI cards use bright gradients; force readable text inside those cards */
    body.theme-dark .row-cols-xl-4 > .col .card[style*="background"],
    [data-theme="dark"] .row-cols-xl-4 > .col .card[style*="background"] {
        color: #18181b !important;
        text-shadow: 0 1px 2px rgba(255,255,255,.18), 0 0 1px #fff;
    }
    body.theme-dark .row-cols-xl-4 > .col .card[style*="background"] *,
    [data-theme="dark"] .row-cols-xl-4 > .col .card[style*="background"] * {
        color: #18181b !important;
        opacity: 1 !important;
        text-shadow: 0 1px 2px rgba(255,255,255,.18), 0 0 1px #fff;
        -webkit-text-fill-color: #18181b !important;
        background-clip: border-box !important;
    }
    @media (max-width: 576px) {
        .dashboard-topbar { gap: .5rem !important; }
        .dashboard-fx { min-width: 100% !important; }
        #tzCountrySelect,
        .dashboard-tz-select { width: 100% !important; }
        .dashboard-clock { width: 100%; text-align: left !important; }
        #tzTime { letter-spacing: 1px !important; }
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Timezone clock: updates `#tzTime` and `#tzDate` based on selected timezone (static separators, 12-hour AM/PM)
    (function(){
        const sel = document.getElementById('tzCountrySelect');
        const timeEl = document.getElementById('tzTime');
        const dateEl = document.getElementById('tzDate');
        function formatTimeForTZ(now, tz){
            try{
                const opts = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true, timeZone: tz };
                return new Intl.DateTimeFormat('en-US', opts).format(now);
            }catch(e){ return '--:--:--'; }
        }
        function formatDateForTZ(now, tz){
            try{
                const opts = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric', timeZone: tz };
                return new Intl.DateTimeFormat('en-GB', opts).format(now) + ' • ' + tz.split('/').pop();
            }catch(e){ return '--'; }
        }
        function updateClock(){
            const tz = (sel && sel.value) ? sel.value : 'Asia/Karachi';
            const now = new Date();
            if (timeEl) timeEl.textContent = formatTimeForTZ(now, tz);
            if (dateEl) dateEl.textContent = formatDateForTZ(now, tz);
        }
        // persist selection
        try{ const saved = localStorage.getItem('selected_tz'); if (saved && sel) sel.value = saved; }catch(e){}
        sel?.addEventListener('change', function(){ try{ localStorage.setItem('selected_tz', sel.value); }catch(e){} updateClock(); });
        updateClock();
        setInterval(updateClock, 1000);
    })();
    // FX rates fetch (show USD/EUR/GBP -> PKR; compact display USD in header)
    // Uses local endpoint to avoid browser CORS/network blocks.
    (function(){
        const usdEl = document.getElementById('usdPkr');
        const fxSub = document.getElementById('fxSub');
        const upd = document.getElementById('fxUpdated');

        function setFxLoading(){
            if (usdEl) usdEl.textContent = 'USD: Loading…';
            if (fxSub) fxSub.textContent = 'EUR: Loading… · GBP: Loading…';
            if (upd) upd.textContent = '—';
        }

        function setFxError(){
            if (usdEl) usdEl.textContent = 'USD: —';
            if (fxSub) fxSub.textContent = 'FX rate unavailable';
            if (upd) upd.textContent = 'offline';
        }

        async function fetchFx(){
            try{
                const url = <?= json_encode(site_url('corelynk/fx-rates?base=USD&symbols=PKR,EUR,GBP')) ?>;
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const j = await res.json();
                if (!res.ok || !j || !j.success || !j.rates) {
                    setFxError();
                    return;
                }

                const usd_pkr = j.rates.PKR;
                const usd_eur = j.rates.EUR;
                const usd_gbp = j.rates.GBP;
                const eur_pkr = (usd_pkr && usd_eur) ? (usd_pkr / usd_eur) : null;
                const gbp_pkr = (usd_pkr && usd_gbp) ? (usd_pkr / usd_gbp) : null;

                if (usdEl) usdEl.textContent = 'USD: ' + (usd_pkr ? usd_pkr.toFixed(2) + ' PKR' : '--');
                if (fxSub) fxSub.textContent = 'EUR: ' + (eur_pkr ? eur_pkr.toFixed(2) + ' PKR' : '--') + ' · GBP: ' + (gbp_pkr ? gbp_pkr.toFixed(2) + ' PKR' : '--');

                const when = j.fetched_at ? new Date(j.fetched_at) : new Date();
                const label = when.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                if (upd) upd.textContent = label + (j.provider ? ' · ' + j.provider : '');
            }catch(e){
                console.warn('FX fetch failed', e);
                setFxError();
            }
        }

        setFxLoading();
        fetchFx();
        setInterval(fetchFx, 1000*60*15);
    })();
    document.addEventListener('DOMContentLoaded', function(){
        const chartLabels = <?= json_encode($chart_labels ?? [], JSON_HEX_TAG) ?>;
        const salesSeries = <?= json_encode($sales_timeline ?? [], JSON_HEX_TAG) ?>;
        const paymentsSeries = <?= json_encode($payments_timeline ?? [], JSON_HEX_TAG) ?>;
        const purchaseSeries = <?= json_encode($purchase_timeline ?? [], JSON_HEX_TAG) ?>;

        const salesEl = document.getElementById('salesTimelineChart');
        if (salesEl) {
            new Chart(salesEl, {
                type: 'line',
                data: { labels: chartLabels, datasets: [{ label: 'Sales', data: salesSeries, borderColor: '#4f46e5', backgroundColor: 'rgba(79,70,229,0.12)', fill: true, tension: 0.35 }] },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
            });
        }

        const expEl = document.getElementById('expensesPaymentsChart');
        if (expEl) {
            new Chart(expEl, {
                data: {
                    labels: chartLabels,
                    datasets: [
                        { type: 'bar', label: 'Purchase Orders', data: purchaseSeries, backgroundColor: '#f59e0b' },
                        { type: 'line', label: 'Vendor Payments', data: paymentsSeries, borderColor: '#10b981', tension: 0.35, fill: false }
                    ]
                },
                options: { plugins: { legend: { display: true } }, scales: { y: { beginAtZero: true } } }
            });
        }

        const revEl = document.getElementById('revenueYoYChart');
        if (revEl) {
            const labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            const current = labels.map(()=>Math.floor(Math.random()*300)+120);
            const last = labels.map(()=>Math.floor(Math.random()*280)+100);
            new Chart(revEl, {
                type: 'line',
                data: { labels, datasets: [
                    { label:'This Year', data: current, borderColor:'#4f46e5', tension:0.35 },
                    { label:'Last Year', data: last, borderColor:'#64748b', tension:0.35 }
                ] },
                options: { plugins:{ legend:{ display:true } }, scales:{ y:{ beginAtZero:true } } }
            });
        }

        const pieEl = document.getElementById('expensePieChart');
        if (pieEl) {
            new Chart(pieEl, {
                type: 'doughnut',
                data: { labels:['Opex','Purchases','Salaries','Utilities'], datasets:[{ data:[25,45,20,10], backgroundColor:['#0ea5e9','#f59e0b','#10b981','#8b5cf6'] }] },
                options: { plugins:{ legend:{ position:'bottom' } } }
            });
        }
    });
</script>
<?= $this->endSection() ?>