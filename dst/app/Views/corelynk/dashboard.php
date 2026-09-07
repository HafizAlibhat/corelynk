<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap">
<link rel="stylesheet" href="<?= base_url('assets/css/command-center.css') ?>?v=21">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php
$kpis = is_array($kpi_cards ?? null) ? $kpi_cards : [];
$products = is_array($trending_products ?? null) ? $trending_products : [];
$pendingInvoices = is_array($pending_customer_invoices ?? null) ? $pending_customer_invoices : [];
$recentInvoices = is_array($recent_customer_invoices ?? null) ? $recent_customer_invoices : $pendingInvoices;
$recentInvoiceCounts = ['unpaid' => 0, 'draft' => 0, 'paid' => 0];
foreach ($recentInvoices as $recentInvoice) {
    $recentStatus = strtolower((string) ($recentInvoice['status'] ?? ''));
    $recentOutstanding = (float) ($recentInvoice['outstanding'] ?? 0);
    if ($recentStatus === 'draft') {
        $recentInvoiceCounts['draft']++;
    } elseif ($recentStatus === 'paid') {
        $recentInvoiceCounts['paid']++;
    } elseif (!in_array($recentStatus, ['cancelled', 'void'], true) && $recentOutstanding > 0) {
        $recentInvoiceCounts['unpaid']++;
    }
}
$workOrders = is_array($recent_activity ?? null) ? $recent_activity : [];
$businessAlerts = is_array($alerts ?? null) ? $alerts : [];
$ownerMetrics = is_array($owner_metrics ?? null) ? $owner_metrics : [];
$ownerAttentionCount = (int) ($owner_attention_count ?? 0);
$topCustomersMonth = is_array($top_customers_month ?? null) ? $top_customers_month : ['currency' => 'USD', 'labels' => [], 'values' => [], 'summary' => ['label' => 'No customer data', 'amount' => 0, 'count' => 0, 'total' => 0]];
$topCustomersYear = is_array($top_customers_year ?? null) ? $top_customers_year : ['currency' => 'USD', 'labels' => [], 'values' => [], 'summary' => ['label' => 'No customer data', 'amount' => 0, 'count' => 0, 'total' => 0]];
$topCustomerDefaultRange = array_sum(array_map('floatval', $topCustomersMonth['values'] ?? [])) > 0 ? 'month' : 'year';
$salesTotal = 0.0;
foreach (($chart_labels ?? []) as $chartIndex => $chartLabel) {
    if (str_ends_with((string) $chartLabel, date('Y'))) {
        $salesTotal += (float) ($revenue_timeline[$chartIndex] ?? 0);
    }
}
$kpiTones = ['violet', 'teal', 'rose', 'amber'];
?>
<section
    class="cl-command-center"
    aria-labelledby="commandCenterTitle"
    data-chart-labels="<?= esc(json_encode(array_values($chart_labels ?? [])), 'attr') ?>"
    data-revenue-series="<?= esc(json_encode(array_values($revenue_timeline ?? [])), 'attr') ?>"
    data-expense-series="<?= esc(json_encode(array_values($expense_timeline ?? [])), 'attr') ?>"
    data-chart-series-by-currency="<?= esc(json_encode($chart_series_by_currency ?? []), 'attr') ?>"
    data-chart-currency="<?= esc($chart_currency ?? 'PKR', 'attr') ?>"
    data-top-customers-month="<?= esc(json_encode($topCustomersMonth), 'attr') ?>"
    data-top-customers-year="<?= esc(json_encode($topCustomersYear), 'attr') ?>"
    data-fx-url="<?= esc(site_url('corelynk/fx-rates?base=USD&symbols=PKR,EUR,GBP'), 'attr') ?>"
    data-activity-feed-url="<?= esc(site_url('corelynk/activity-center/feed?limit=10'), 'attr') ?>"
    data-activity-read-url="<?= esc(site_url('corelynk/activity-center/read'), 'attr') ?>"
    data-activity-read-all-url="<?= esc(site_url('corelynk/activity-center/read-all'), 'attr') ?>"
>
    <header class="cc-page-heading">
        <div>
            <h1 id="commandCenterTitle">Dashboard</h1>
            <p>Real-time systemic overview</p>
        </div>
    </header>

    <?php if (!empty($receivables_debug) && is_array($receivables_debug)): ?>
        <div class="cc-debug" role="status">Receivables: <?= esc(json_encode($receivables_debug)) ?></div>
    <?php endif; ?>

    <div class="cc-kpi-grid" aria-label="Key performance indicators">
        <?php foreach ($kpis as $index => $card): ?>
            <?php
            $tone = $kpiTones[$index % count($kpiTones)];
            $hint = trim((string) ($card['hint'] ?? ''));
            $trendClass = str_contains($hint, '-') ? 'is-negative' : 'is-positive';
            ?>
            <a class="cc-kpi-card cc-tone-<?= esc($tone) ?>" href="<?= esc($card['link'] ?? '#') ?>">
                <div class="cc-kpi-topline">
                    <span class="cc-eyebrow"><?= esc($card['label'] ?? 'Metric') ?></span>
                    <span class="cc-kpi-icon"><i class="bi <?= esc($card['icon'] ?? 'bi-bar-chart') ?>" aria-hidden="true"></i></span>
                </div>
                <strong class="cc-kpi-value"><?= esc($card['value'] ?? '0') ?></strong>
                <span class="cc-kpi-trend <?= $trendClass ?>">
                    <i class="bi <?= $trendClass === 'is-negative' ? 'bi-arrow-down' : 'bi-arrow-up' ?>" aria-hidden="true"></i>
                    <?= esc($hint !== '' ? $hint : ($card['linkLabel'] ?? 'View details')) ?>
                </span>
            </a>
        <?php endforeach; ?>
        <?php if ($kpis === []): ?>
            <div class="cc-empty cc-empty-wide">No dashboard metrics are available for your current permissions.</div>
        <?php endif; ?>
    </div>

    <div class="cc-dashboard-grid">
        <div class="cc-primary-column">
            <article class="cc-panel cc-revenue-panel">
                <header class="cc-panel-heading cc-revenue-heading">
                    <div>
                        <h2>Annual Revenue Matrix</h2>
                        <p>Posted revenue and expenses in the selected document currency</p>
                    </div>
                    <div class="cc-captured-value">
                        <span>Revenue captured</span>
                        <strong id="ccCapturedValue" aria-live="polite"><?= esc($chart_currency ?? 'PKR') ?> <?= number_format($salesTotal, 0) ?></strong>
                    </div>
                    <div class="cc-period-controls" aria-label="Revenue timeline controls">
                        <label class="cc-period" for="ccRevenueCurrency">
                            <span class="visually-hidden">Document currency</span>
                            <select id="ccRevenueCurrency" aria-label="Document currency">
                                <?php foreach (array_keys($chart_series_by_currency ?? [$chart_currency ?? 'USD' => []]) as $currencyCode): ?>
                                    <option value="<?= esc($currencyCode) ?>" <?= $currencyCode === ($chart_currency ?? 'USD') ? 'selected' : '' ?>><?= esc($currencyCode) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </label>
                        <label class="cc-period" for="ccRevenueYear">
                            <span class="visually-hidden">Reporting year</span>
                            <select id="ccRevenueYear" aria-label="Reporting year">
                                <option value="<?= esc(date('Y')) ?>"><?= esc(date('Y')) ?></option>
                            </select>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </label>
                        <label class="cc-period" for="ccRevenuePeriod">
                            <span class="visually-hidden">Month range</span>
                            <select id="ccRevenuePeriod" aria-label="Month or range">
                                <option value="year" selected>All Months</option>
                                <option value="6">Last 6M</option>
                                <option value="3">Last 3M</option>
                                <optgroup label="Specific month">
                                    <?php foreach (['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'] as $monthIndex => $monthLabel): ?>
                                        <option value="month-<?= $monthIndex ?>"><?= esc($monthLabel) ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                            <i class="bi bi-chevron-down" aria-hidden="true"></i>
                        </label>
                    </div>
                </header>
                <div class="cc-chart-wrap">
                    <canvas id="ccRevenueChart" aria-label="Posted revenue and posted expenses by selected currency" role="img"></canvas>
                    <div class="cc-chart-empty" id="ccRevenueEmpty" hidden>
                        <i class="bi bi-bar-chart" aria-hidden="true"></i>
                        <strong>No posted activity</strong>
                        <span>No revenue or expenses were posted for this selection.</span>
                    </div>
                </div>
                <div class="cc-chart-legend" aria-hidden="true">
                    <span><i class="is-gross"></i>Posted Revenue</span>
                    <span><i class="is-cost"></i>Posted Expenses</span>
                </div>
            </article>

            <article class="cc-panel cc-items-panel">
                <header class="cc-panel-heading">
                    <h2>Top Velocity Items</h2>
                    <a href="<?= base_url('/products') ?>">View Directory</a>
                </header>
                <div class="cc-table-scroll">
                    <table class="cc-data-table">
                        <thead>
                            <tr>
                                <th scope="col">Item Code</th>
                                <th scope="col">Description</th>
                                <th scope="col">On Hand</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $stock = (float) ($product['current_stock'] ?? 0);
                                $statusClass = $stock <= 0 ? 'danger' : ($stock < 10 ? 'warning' : 'success');
                                $statusLabel = $stock <= 0 ? 'Out of Stock' : ($stock < 10 ? 'Low Stock' : 'Optimal');
                                ?>
                                <?php
                                $productCode = trim((string) ($product['code'] ?? ''));
                                $variantCount = (int) ($product['variant_count'] ?? 0);
                                $productRef = entityRouteIdentifier($product);
                                $productHref = $productRef !== ''
                                    ? base_url('/products/' . $productRef)
                                    : base_url('/products');
                                ?>
                                <tr>
                                    <td>
                                        <span class="cc-code"><?= esc($productCode !== '' ? $productCode : '-') ?></span>
                                        <?php if ($variantCount > 1): ?>
                                            <span class="cc-code-more" title="<?= esc($variantCount) ?> variants">+<?= esc($variantCount - 1) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a class="cc-product-link" href="<?= $productHref ?>"><?= esc($product['name'] ?? 'Unnamed product') ?></a>
                                        <small>Unit cost <?= esc($product['cost_currency'] ?? 'USD') ?> <?= number_format((float) ($product['unit_cost'] ?? 0), 2) ?></small>
                                    </td>
                                    <td class="cc-mono"><?= number_format($stock, 2) ?></td>
                                    <td><span class="cc-status cc-status-<?= $statusClass ?>"><i></i><?= esc($statusLabel) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($products === []): ?>
                                <tr><td colspan="4"><div class="cc-empty">No product signals are available.</div></td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="cc-panel cc-owner-panel" aria-labelledby="ownerActionTitle">
                <header class="cc-panel-heading">
                    <div>
                        <h2 id="ownerActionTitle">Owner Action Center</h2>
                        <p>Operational priorities that need review or follow-through</p>
                    </div>
                    <span class="cc-owner-attention<?= $ownerAttentionCount > 0 ? ' has-attention' : '' ?>">
                        <?= number_format($ownerAttentionCount) ?> active <?= $ownerAttentionCount === 1 ? 'priority' : 'priorities' ?>
                    </span>
                </header>
                <div class="cc-owner-grid">
                    <?php foreach ($ownerMetrics as $metric): ?>
                        <a class="cc-owner-metric cc-owner-tone-<?= esc($metric['tone'] ?? 'slate') ?>" href="<?= esc($metric['link'] ?? '#') ?>">
                            <span class="cc-owner-metric-icon"><i class="bi <?= esc($metric['icon'] ?? 'bi-graph-up') ?>" aria-hidden="true"></i></span>
                            <span class="cc-owner-metric-copy">
                                <small><?= esc($metric['label'] ?? 'Business metric') ?></small>
                                <strong><?= esc($metric['value'] ?? '0') ?></strong>
                                <span><?= esc($metric['detail'] ?? 'Review details') ?></span>
                            </span>
                            <i class="bi bi-arrow-up-right cc-owner-metric-arrow" aria-hidden="true"></i>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($ownerMetrics === []): ?>
                        <div class="cc-empty cc-empty-wide">No owner metrics are available for your current permissions.</div>
                    <?php endif; ?>
                </div>
                <footer class="cc-owner-footer">
                    <i class="bi bi-shield-check" aria-hidden="true"></i>
                    <span>Live operational counts. Currency values remain grouped by their source documents.</span>
                </footer>
            </article>
        </div>

        <aside class="cc-secondary-column" aria-label="Operational signals">
            <article class="cc-panel cc-recent-orders-panel">
                <header class="cc-panel-heading">
                    <h2><i class="bi bi-receipt" aria-hidden="true"></i>Recent Sales Orders</h2>
                    <a href="<?= base_url('/sales-orders') ?>">View all</a>
                </header>
                <div class="cc-recent-orders-list" id="ccRecentSalesOrders" aria-live="polite">
                    <div class="cc-empty">Loading recent sales orders...</div>
                </div>
            </article>

            <article class="cc-panel cc-invoices-panel">
                <header class="cc-panel-heading">
                    <div>
                        <h2>Customer Invoices</h2>
                        <p>Recent activity with clear payment status</p>
                    </div>
                    <a href="<?= base_url('/customer-invoices') ?>">View all</a>
                </header>
                <div class="cc-invoice-overview" aria-label="Status of recent customer invoices">
                    <div class="is-unpaid"><span>Unpaid</span><strong><?= $recentInvoiceCounts['unpaid'] ?></strong></div>
                    <div class="is-draft"><span>Draft</span><strong><?= $recentInvoiceCounts['draft'] ?></strong></div>
                    <div class="is-paid"><span>Paid</span><strong><?= $recentInvoiceCounts['paid'] ?></strong></div>
                </div>
                <?php if ($recentInvoiceCounts['unpaid'] === 0 && $recentInvoices !== []): ?>
                    <div class="cc-invoice-note"><i class="bi bi-info-circle" aria-hidden="true"></i><span>No collectible balance. Drafts below have not been issued to customers.</span></div>
                <?php endif; ?>
                <div class="cc-invoice-list">
                    <?php foreach ($recentInvoices as $invoice): ?>
                        <?php
                            $invoiceId = (int) ($invoice['id'] ?? 0);
                            $outstanding = (float) ($invoice['outstanding'] ?? 0);
                            $totalAmount = (float) ($invoice['total_amount'] ?? 0);
                            $invoiceStatus = strtolower((string) ($invoice['status'] ?? 'open'));
                            $isCollectible = !in_array($invoiceStatus, ['draft', 'paid', 'cancelled', 'void'], true) && $outstanding > 0;
                            $isOverdue = $isCollectible && !empty($invoice['due_date']) && strtotime((string) $invoice['due_date']) < time();
                            $statusClass = $isOverdue ? 'danger' : ($invoiceStatus === 'paid' ? 'success' : ($invoiceStatus === 'draft' ? 'neutral' : ($invoiceStatus === 'partially_paid' ? 'warning' : 'info')));
                            $statusLabel = $isOverdue ? 'Overdue' : ucfirst(str_replace('_', ' ', $invoiceStatus));
                            $dueDate = !empty($invoice['due_date']) ? date('M j, Y', strtotime((string) $invoice['due_date'])) : 'Due soon';
                            $issueDate = !empty($invoice['issue_date']) ? date('M j, Y', strtotime((string) $invoice['issue_date'])) : '';
                            $displayAmount = $isCollectible ? $outstanding : $totalAmount;
                            $amountLabel = $isCollectible ? 'outstanding' : ($invoiceStatus === 'paid' ? 'paid' : 'draft total');
                        ?>
                        <a href="<?= base_url('/customer-invoices/view/' . $invoiceId) ?>" class="cc-invoice-item">
                            <div class="cc-invoice-main">
                                <strong><?= esc($invoice['invoice_number'] ?? ('INV-' . $invoiceId)) ?></strong>
                                <span><?= esc($invoice['customer_name'] ?? 'Customer') ?></span>
                                <?php if ($issueDate !== ''): ?>
                                    <small>Issued <?= esc($issueDate) ?></small>
                                <?php endif; ?>
                                <?php if (!empty($invoice['sales_order_number'])): ?>
                                    <small><?= esc($invoice['sales_order_number']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="cc-invoice-meta">
                                <?php if ($isCollectible): ?><span class="cc-eta">Due <?= esc($dueDate) ?></span><?php endif; ?>
                                <b><?= esc($invoice['currency_code'] ?? 'USD') ?> <?= number_format($displayAmount, 2) ?></b>
                                <span class="cc-invoice-amount-label"><?= esc($amountLabel) ?></span>
                                <small class="cc-status-pill cc-status-<?= $statusClass ?>"><?= esc($statusLabel) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if ($recentInvoices === []): ?>
                        <div class="cc-empty">No customer invoices have been created yet.</div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="cc-panel cc-customer-panel">
                <header class="cc-panel-heading">
                    <div>
                        <h2>Top Customers</h2>
                        <p>Share of invoiced value for the selected period</p>
                    </div>
                    <label class="cc-period cc-period-compact" for="ccTopCustomerRange">
                        <span class="visually-hidden">Top customer range</span>
                        <select id="ccTopCustomerRange" aria-label="Top customer range">
                            <option value="month" <?= $topCustomerDefaultRange === 'month' ? 'selected' : '' ?>>This Month</option>
                            <option value="year" <?= $topCustomerDefaultRange === 'year' ? 'selected' : '' ?>>This Year</option>
                        </select>
                        <i class="bi bi-chevron-down" aria-hidden="true"></i>
                    </label>
                </header>
                <div class="cc-top-customer">
                    <div class="cc-top-customer-chart">
                        <canvas id="ccTopCustomerChart" aria-label="Customer invoice value distribution" role="img"></canvas>
                        <div class="cc-top-customer-share" aria-hidden="true">
                            <strong id="ccTopCustomerShare">0%</strong>
                            <span>top share</span>
                        </div>
                    </div>
                    <div class="cc-top-customer-summary">
                        <span class="cc-top-customer-kicker">Leading customer</span>
                        <strong id="ccTopCustomerName">No customer data</strong>
                        <span class="cc-top-customer-amount" id="ccTopCustomerAmount">USD 0.00</span>
                        <small id="ccTopCustomerMeta">0 invoices | 0 total</small>
                        <div class="cc-top-customer-legend" id="ccTopCustomerLegend" aria-label="Customer chart legend"></div>
                    </div>
                </div>
            </article>

            <article class="cc-panel cc-alerts-panel">
                <header class="cc-panel-heading">
                    <h2>Business Signals</h2>
                    <i class="bi bi-broadcast" aria-hidden="true"></i>
                </header>
                <div class="cc-business-list">
                    <?php foreach ($businessAlerts as $alert): ?>
                        <div><i class="bi <?= esc($alert['icon'] ?? 'bi-info-circle') ?>" aria-hidden="true"></i><span><strong><?= esc($alert['title'] ?? 'Signal') ?></strong><small><?= esc($alert['text'] ?? '') ?></small></span></div>
                    <?php endforeach; ?>
                    <?php foreach (array_slice($workOrders, 0, 3) as $workOrder): ?>
                        <div>
                            <i class="bi bi-clipboard-check" aria-hidden="true"></i>
                            <span>
                                <strong><a href="<?= base_url('/work-orders/view/' . ($workOrder['id'] ?? '')) ?>"><?= esc($workOrder['wo_number'] ?? ('WO-' . ($workOrder['id'] ?? ''))) ?></a></strong>
                                <small><?= esc($workOrder['status'] ?? 'Open') ?> / <?= esc($workOrder['customer_name'] ?? 'Customer') ?></small>
                            </span>
                        </div>
                    <?php endforeach; ?>
                    <?php if ($businessAlerts === [] && $workOrders === []): ?>
                        <div class="cc-empty">No business alerts require attention.</div>
                    <?php endif; ?>
                </div>
            </article>
        </aside>
    </div>

    <section class="cc-panel cc-activity-panel" id="activityCenterCard" aria-labelledby="activityCenterTitle">
        <header class="cc-panel-heading">
            <div>
                <h2 id="activityCenterTitle">Activity Center</h2>
                <p>Latest active and ready-to-ship sales orders</p>
            </div>
            <div class="cc-activity-actions">
                <span class="cc-unread-badge" id="activityUnreadBadge" hidden>0 unread</span>
                <button class="cc-button cc-button-quiet" type="button" id="activityMarkAllBtn">Mark all read</button>
            </div>
        </header>
        <div class="cc-activity-grid">
            <div>
                <h3>Active Sales Orders</h3>
                <div id="activityActiveOrders" class="cc-activity-list"><div class="cc-empty">Loading activity...</div></div>
            </div>
            <div>
                <h3>Ready to Ship</h3>
                <div id="activityReadyOrders" class="cc-activity-list"><div class="cc-empty">Loading activity...</div></div>
            </div>
        </div>
    </section>

    <section class="cc-panel cc-system-panel" aria-labelledby="systemContextTitle">
        <header class="cc-panel-heading">
            <div>
                <h2 id="systemContextTitle">System Context</h2>
                <p>Exchange-rate and timezone utilities</p>
            </div>
        </header>
        <div class="cc-system-grid">
            <div class="cc-fx-block">
                <span>Market rates</span>
                <strong id="usdPkr">USD: Loading...</strong>
                <small id="fxSub">EUR: Loading... / GBP: Loading...</small>
                <small id="fxUpdated">Updating</small>
            </div>
            <label class="cc-timezone-field" for="tzCountrySelect">
                <span>Timezone</span>
                <select id="tzCountrySelect">
                    <option value="Asia/Karachi" selected>Pakistan (Karachi)</option>
                    <option value="America/New_York">USA (New York)</option>
                    <option value="Europe/Berlin">Germany (Berlin)</option>
                    <option value="Europe/London">UK (London)</option>
                    <option value="Australia/Sydney">Australia (Sydney)</option>
                    <option value="Asia/Riyadh">Saudi Arabia (Riyadh)</option>
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
                </select>
            </label>
            <div class="cc-clock-block">
                <span>Local time</span>
                <strong id="tzTime">--:--:--</strong>
                <small id="tzDate">--</small>
            </div>
            <div class="cc-portfolio-block">
                <span>Inventory position</span>
                <strong><?= esc($stock_value_display ?? 'USD 0.00') ?></strong>
                <small><?= number_format((int) ($open_pos ?? 0)) ?> active POs / <?= esc($open_pos_value_display ?? 'PKR 0.00') ?></small>
            </div>
        </div>
    </section>
</section>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script src="<?= base_url('assets/js/command-center.js') ?>?v=11" defer></script>
<?= $this->endSection() ?>
