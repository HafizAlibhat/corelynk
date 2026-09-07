<?php
$currencySymbol = '$';
if (($selected_currency ?? 'PKR') === 'PKR') {
    $currencySymbol = '₨';
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Accounting Dashboard<?= $this->endSection() ?>

<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/accounting-dashboard.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<div class="accounting-scope">
<div class="container-fluid">
    <?php if (!empty($diagnostics)): ?>
    <div class="alert alert-warning py-2 mb-3 small">
        <strong>Diagnostics:</strong>
        <?php if (isset($diagnostics['error'])): ?>
            <span class="text-danger"><?= esc($diagnostics['error']) ?></span>
        <?php else: ?>
            <span>Debits Sum: <?= number_format(($diagnostics['journal_entry_sum_debits'] ?? 0),2) ?> | Credits Sum: <?= number_format(($diagnostics['journal_entry_sum_credits'] ?? 0),2) ?> | Revenue Display: <?= number_format(($diagnostics['revenue_calc'] ?? 0),2) ?> | Delta (Revenue - Credits): <?= number_format(($diagnostics['revenue_vs_entries_delta'] ?? 0),2) ?> (View: <?= esc($diagnostics['currency_view'] ?? '') ?>)</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <!-- Currency Switcher and Quick Actions -->
    <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
        <div class="currency-switcher d-flex align-items-center gap-3">
                <form method="get" id="currencySwitcherForm" class="d-inline">
                    <label for="currencySelect" class="me-2 fw-bold text-light">Currency:</label>
                    <select name="currency" id="currencySelect" class="form-select form-select-sm d-inline w-auto" onchange="document.getElementById('currencySwitcherForm').submit()">
                        <option value="PKR" <?= (($selected_currency ?? 'PKR') === 'PKR') ? 'selected' : '' ?>>PKR (₨)</option>
                        <option value="USD" <?= (($selected_currency ?? 'PKR') === 'USD') ? 'selected' : '' ?>>USD ($)</option>
                    </select>
                </form>
                <?php $fx = $fx_rates ?? []; ?>
                <div class="live-rates small text-light ms-3">
                    <span>USD: <span id="fx-usd"><?= isset($fx['USD']) ? '₨'.number_format($fx['USD'],2) : 'Loading…' ?></span></span>
                    &nbsp;·&nbsp;
                    <span>EUR: <span id="fx-eur"><?= isset($fx['EUR']) ? '₨'.number_format($fx['EUR'],2) : 'Loading…' ?></span></span>
                    &nbsp;·&nbsp;
                    <span>GBP: <span id="fx-gbp"><?= isset($fx['GBP']) ? '₨'.number_format($fx['GBP'],2) : 'Loading…' ?></span></span>
                    <small id="fx-source" class="ms-2 text-muted">source: exchangerate.host</small>
                </div>
            </div>
        <div class="quick-actions d-flex gap-2 align-items-center">
            <a href="<?= base_url('accounting/journal-lite') ?>" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i> New Entry</a>
            <a href="<?= base_url('accounting/trial-balance') ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-calculator me-1"></i> Trial Balance</a>
            <a href="<?= base_url('accounting/chart-of-accounts') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul me-1"></i> Chart of Accounts</a>
            <!-- Amount display toggle: Short (e.g., 7M) or Full -->
            <button id="amountToggleBtn" type="button" class="btn btn-sm btn-outline-secondary" title="Toggle amount display">Short</button>
        </div>
    </div>

    <!-- Metric Cards Row -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="card-title"><i class="bi bi-cash-coin"></i> Total Revenue</div>
                <div class="card-value"><span class="amount amount-full" data-raw="<?= (float)($total_revenue ?? 0) ?>" data-symbol="<?= esc($currencySymbol) ?>"><?= $currencySymbol ?><?= number_format(($total_revenue ?? 0), 2) ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="card-title"><i class="bi bi-wallet2"></i> Total Expenses</div>
                <div class="card-value"><span class="amount amount-full" data-raw="<?= (float)($total_expenses ?? 0) ?>" data-symbol="<?= esc($currencySymbol) ?>"><?= $currencySymbol ?><?= number_format(($total_expenses ?? 0), 2) ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="card-title"><i class="bi bi-graph-up"></i> Net Profit</div>
                <div class="card-value"><span class="amount amount-full" data-raw="<?= (float)(($total_revenue ?? 0) - ($total_expenses ?? 0)) ?>" data-symbol="<?= esc($currencySymbol) ?>"><?= $currencySymbol ?><?= number_format(($total_revenue ?? 0) - ($total_expenses ?? 0), 2) ?></span></div>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="metric-card">
                <div class="card-title"><i class="bi bi-bank2"></i> Cash Position</div>
                <div class="card-value"><span class="amount amount-full" data-raw="<?= (float)($cash_position_value ?? 0) ?>" data-symbol="<?= esc($currencySymbol) ?>"><?= $currencySymbol ?><?= number_format(($cash_position_value ?? 0), 2) ?></span></div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-lg-8 mb-4">
            <div class="chart-container">
                <h3 class="section-title"><i class="bi bi-bar-chart-line me-2"></i>Income vs Expenses</h3>
                <canvas id="incomeExpensesChart"></canvas>
            </div>
        </div>
        <div class="col-lg-4 mb-4">
            <div class="chart-container">
                <h3 class="section-title"><i class="bi bi-pie-chart me-2"></i>AP/AR Balance</h3>
                <canvas id="apArChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="table-responsive">
                <h3><i class="bi bi-receipt me-2"></i>Sales by Product</h3>
                <table class="table table-sm table-compact table-hover">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Sales</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($sales_by_product) && is_array($sales_by_product)): ?>
                            <?php foreach ($sales_by_product as $product): ?>
                                <tr>
                                    <td><?= esc($product['product_name'] ?? 'N/A') ?></td>
                                    <td><?= $currencySymbol ?><?= number_format($product['sales'] ?? 0, 2) ?></td>
                                    <td><?= number_format(($product['percentage'] ?? 0), 1) ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="table-responsive">
                <h3><i class="bi bi-truck me-2"></i>Vendor Expenses</h3>
                <table class="table table-sm table-compact table-hover">
                    <thead>
                        <tr>
                            <th>Vendor</th>
                            <th>Expenses</th>
                            <th>% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($vendor_expenses) && is_array($vendor_expenses)): ?>
                            <?php foreach ($vendor_expenses as $vendor): ?>
                                <tr>
                                    <td><?= esc($vendor['vendor_name'] ?? 'N/A') ?></td>
                                    <td><?= $currencySymbol ?><?= number_format($vendor['expenses'] ?? 0, 2) ?></td>
                                <td><?= number_format(($vendor['percentage'] ?? 0), 1) ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3">No data available</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Gauges Row -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="gauge-container">
                <div class="gauge-title">YTD Revenue</div>
                <canvas id="ytdRevenueGauge" class="gauge"></canvas>
                <div class="text-muted small mt-2" id="ytdRevenueInfo"></div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="gauge-container">
                <div class="gauge-title">YTD Expenses</div>
                <canvas id="ytdExpensesGauge" class="gauge"></canvas>
                <div class="text-muted small mt-2" id="ytdExpensesInfo"></div>
            </div>
        </div>
    </div>

    <!-- Additional Charts Row -->
    <div class="row mb-4">
        <div class="col-lg-6 mb-4">
            <div class="chart-container">
                <h3 class="section-title"><i class="bi bi-graph-up me-2"></i>Monthly Income vs Expenses</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="chart-container">
                <h3 class="section-title"><i class="bi bi-people me-2"></i>Top Customers</h3>
                <canvas id="topCustomersChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="recent-activity">
        <h3><i class="bi bi-activity me-2"></i>Recent Activity</h3>
        <div class="activity-list">
            <?php if (!empty($recent_activity) && is_array($recent_activity)): ?>
                <?php foreach ($recent_activity as $entry): ?>
                        <?php
                        // Parse original values for each line and group by currency
                        $debits = explode(',', $entry['debits'] ?? '');
                        $credits = explode(',', $entry['credits'] ?? '');
                        $currencies = explode(',', $entry['currencies'] ?? '');
                        $fx_rates = explode(',', $entry['fx_rates'] ?? '');
                        $lines = max(count($debits), count($credits), count($currencies));
                        $pkrs = [];
                        $usds = [];
                        for ($i = 0; $i < $lines; $i++) {
                            $amt = ((float)($debits[$i] ?? 0)) - ((float)($credits[$i] ?? 0));
                            $cur = $currencies[$i] ?? '';
                            $fx = $fx_rates[$i] ?? '';
                            if ($cur === 'PKR') {
                                $pkrs[] = '₨' . number_format($amt, 2) . ($fx ? " (fx $fx)" : '');
                            } elseif ($cur === 'USD') {
                                $usds[] = '$' . number_format($amt, 2) . ($fx ? " (fx $fx)" : '');
                            }
                        }
                    ?>
                    <div class="activity-item d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div class="activity-icon info" title="Voucher">
                                                                <!-- Inline SVG fallback for voucher/document icon to ensure visibility if icon font doesn't load -->
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true" role="img">
                                                                    <title>Voucher</title>
                                                                    <path d="M14 4.5V14a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h6.5L14 4.5zM10.5 2v2a1 1 0 0 0 1 1h2l-3-3z"/>
                                                                    <path d="M5 6.5h6v1H5v-1zm0 2.5h6v1H5v-1z"/>
                                                                </svg>
                                                        </div>
                            <div>
                                <div class="fw-bold">Entry #<?= esc($entry['id']) ?> — <?= esc($entry['memo'] ?? 'Journal Entry') ?></div>
                                <small class="text-muted"><?= date('M d, Y', strtotime($entry['entry_date'])) ?> • <?= (int)($entry['line_count'] ?? 0) ?> lines</small>
                                <div class="small text-muted activity-currencies">
                                    <?php if (!empty($pkrs)): ?> <span class="badge bg-light text-dark me-1">PKR: <?= esc(implode(' / ', $pkrs)) ?></span><?php endif; ?>
                                    <?php if (!empty($usds)): ?> <span class="badge bg-light text-dark">USD: <?= esc(implode(' / ', $usds)) ?></span><?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="text-end fw-bold activity-amount">
                            <?php if (!empty($pkrs)): $pk_sum = array_sum(array_map(function($s){ return (float) filter_var($s, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); }, $pkrs)); ?><div class="d-block pk-amt"><span class="amount amount-full" data-raw="<?= $pk_sum ?>" data-symbol="₨">₨<?= number_format($pk_sum,2) ?></span></div><?php endif; ?>
                            <?php if (!empty($usds)): $usd_sum = array_sum(array_map(function($s){ return (float) filter_var($s, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); }, $usds)); ?><div class="d-block usd-amt"><span class="amount amount-full" data-raw="<?= $usd_sum ?>" data-symbol="$">$<?= number_format($usd_sum,2) ?></span></div><?php endif; ?>
                            <?php if (empty($pkrs) && empty($usds)): $t = (float)($entry['total_amount'] ?? 0); ?><span class="amount amount-full" data-raw="<?= $t ?>" data-symbol="<?= esc($currencySymbol) ?>"><?= $currencySymbol ?><?= number_format($t, 2) ?></span><?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="activity-item d-flex align-items-center">
                    <div class="activity-icon info"><i class="bi bi-info-circle"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-bold">No Recent Activity</div>
                        <small class="text-muted">Create a journal entry to see it here.</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ie_income = <?= json_encode($monthly_income ?? []) ?>;
const ie_expenses = <?= json_encode($monthly_expenses ?? []) ?>;
const ap_data = <?= json_encode($ap_data ?? []) ?>;
const ar_data = <?= json_encode($ar_data ?? []) ?>;
const monthly_income = <?= json_encode($monthly_income ?? []) ?>;
const monthly_expenses = <?= json_encode($monthly_expenses ?? []) ?>;
const top_customers_labels = <?= json_encode($top_customers_labels ?? []) ?>;
const top_customers_data = <?= json_encode($top_customers_data ?? []) ?>;
const ytd_revenue = <?= json_encode($ytd_revenue ?? 0) ?>;
const ytd_expenses = <?= json_encode($ytd_expenses ?? 0) ?>;

// Dynamic month labels from backend (fallback to Jan-Jun if empty)
const months_labels = <?= json_encode($months_labels ?? []) ?>;
const chartLabels = (Array.isArray(months_labels) && months_labels.length > 0) ? months_labels : ['Jan','Feb','Mar','Apr','May','Jun'];

// Income vs Expenses Chart
const ctx1 = document.getElementById('incomeExpensesChart');
if (ctx1) {
    new Chart(ctx1, {
        type: 'line',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Income',
                data: ie_income,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4
            }, {
                label: 'Expenses',
                data: ie_expenses,
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// AP/AR Balance Chart
const ctx2 = document.getElementById('apArChart');
if (ctx2) {
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['Accounts Payable', 'Accounts Receivable'],
            datasets: [{
                data: ap_data,
                backgroundColor: ['#ff6384', '#36a2eb'],
                hoverBackgroundColor: ['#ff6384', '#36a2eb']
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });
}

// Monthly Income vs Expenses Chart
const ctx3 = document.getElementById('monthlyChart');
if (ctx3) {
    new Chart(ctx3, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [{
                label: 'Income',
                data: monthly_income,
                backgroundColor: '#28a745'
            }, {
                label: 'Expenses',
                data: monthly_expenses,
                backgroundColor: '#dc3545'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

// Top Customers Chart
const ctx4 = document.getElementById('topCustomersChart');
if (ctx4) {
    new Chart(ctx4, {
        type: 'bar',
        data: {
            labels: top_customers_labels,
            datasets: [{
                label: 'Revenue',
                data: top_customers_data,
                backgroundColor: '#667eea'
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

// YTD Revenue Gauge
const ctx5 = document.getElementById('ytdRevenueGauge');
if (ctx5) {
    new Chart(ctx5, {
        type: 'doughnut',
        data: {
            labels: ['Achieved', 'Remaining'],
            datasets: [{
                data: [ytd_revenue, 100 - ytd_revenue],
                backgroundColor: ['#28a745', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        },
        plugins: [{
            id: 'doughnutCenterText',
            beforeDraw: function(chart) {
                const width = chart.width,
                    height = chart.height,
                    ctx = chart.ctx;
                ctx.restore();
                ctx.font = '2rem sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#495057';
                const text = ytd_revenue + '%',
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / 2;
                ctx.fillText(text, textX, textY);
                ctx.save();
            }
        }]
    });
}

// YTD Expenses Gauge
const ctx6 = document.getElementById('ytdExpensesGauge');
if (ctx6) {
    new Chart(ctx6, {
        type: 'doughnut',
        data: {
            labels: ['Spent', 'Remaining'],
            datasets: [{
                data: [ytd_expenses, 100 - ytd_expenses],
                backgroundColor: ['#dc3545', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    enabled: false
                }
            }
        },
        plugins: [{
            id: 'doughnutCenterText',
            beforeDraw: function(chart) {
                const width = chart.width,
                    height = chart.height,
                    ctx = chart.ctx;
                ctx.restore();
                ctx.font = '2rem sans-serif';
                ctx.textBaseline = 'middle';
                ctx.fillStyle = '#495057';
                const text = ytd_expenses + '%',
                    textX = Math.round((width - ctx.measureText(text).width) / 2),
                    textY = height / 2;
                ctx.fillText(text, textX, textY);
                ctx.save();
            }
        }]
    });
}

// Update gauge info labels (human-readable)
const yrdEl = document.getElementById('ytdRevenueInfo');
const yexEl = document.getElementById('ytdExpensesInfo');
if (yrdEl) { yrdEl.textContent = `${ytd_revenue}% of annual revenue goal`; }
if (yexEl) { yexEl.textContent = `${ytd_expenses}% of annual expense budget`; }
</script>
<script>
// Client-side fallback: fetch live 1 USD/EUR/GBP -> PKR rates directly from exchangerate.host
(function(){
    const usdEl = document.getElementById('fx-usd');
    const eurEl = document.getElementById('fx-eur');
    const gbpEl = document.getElementById('fx-gbp');
    const srcEl = document.getElementById('fx-source');
    if (!usdEl || !eurEl || !gbpEl) return;

    function setUnavailable() {
        usdEl.textContent = 'Unavailable';
        eurEl.textContent = 'Unavailable';
        gbpEl.textContent = 'Unavailable';
        if (srcEl) srcEl.textContent = 'source: exchangerate.host (unavailable)';
    }

    // First try: combined latest PKR->currencies and invert
    fetch('https://api.exchangerate.host/latest?base=PKR&symbols=USD,EUR,GBP', { cache: 'no-cache' })
        .then(resp => resp.json())
        .then(data => {
            if (!data || !data.rates) throw new Error('No rates');
            const r = data.rates || {};
            let updated = false;
            if (r.USD && r.USD > 0) { usdEl.textContent = '₨' + (1 / r.USD).toFixed(2); updated = true; }
            if (r.EUR && r.EUR > 0) { eurEl.textContent = '₨' + (1 / r.EUR).toFixed(2); updated = true; }
            if (r.GBP && r.GBP > 0) { gbpEl.textContent = '₨' + (1 / r.GBP).toFixed(2); updated = true; }
            if (updated) {
                if (srcEl) {
                    const d = data.date ? new Date(data.date) : new Date();
                    srcEl.textContent = 'source: exchangerate.host (updated: ' + d.toLocaleString() + ')';
                }
                return;
            }
            // Fallback to per-currency convert endpoint
            throw new Error('Empty or zero rates, fallback');
        })
        .catch(err => {
            console.debug('Combined FX fetch failed, trying per-currency convert', err);
            // Try per-currency convert endpoints in parallel
            const conv = (from) => fetch('https://api.exchangerate.host/convert?from=' + from + '&to=PKR', { cache: 'no-cache' }).then(r => r.json());
            Promise.all([conv('USD'), conv('EUR'), conv('GBP')])
                .then(results => {
                    let any = false;
                    if (results[0] && typeof results[0].result === 'number') { usdEl.textContent = '₨' + (results[0].result).toFixed(2); any = true; }
                    if (results[1] && typeof results[1].result === 'number') { eurEl.textContent = '₨' + (results[1].result).toFixed(2); any = true; }
                    if (results[2] && typeof results[2].result === 'number') { gbpEl.textContent = '₨' + (results[2].result).toFixed(2); any = true; }
                    if (any) {
                        if (srcEl) srcEl.textContent = 'source: exchangerate.host (converted)';
                        return;
                    }
                    setUnavailable();
                })
                .catch(e2 => {
                    console.debug('Per-currency FX convert failed', e2);
                    setUnavailable();
                });
        });
})();
</script>
            <script>
            // Amount display helper: abbreviate (e.g., 7.04M) or show full formatted
            function abbreviateNumber(value) {
                const abs = Math.abs(value);
                if (abs >= 1e12) return (value / 1e12).toFixed(2).replace(/\.00$/, '') + 'T';
                if (abs >= 1e9) return (value / 1e9).toFixed(2).replace(/\.00$/, '') + 'B';
                if (abs >= 1e6) return (value / 1e6).toFixed(2).replace(/\.00$/, '') + 'M';
                if (abs >= 1e3) return (value / 1e3).toFixed(1).replace(/\.0$/, '') + 'k';
                return value.toFixed(2).replace(/\.00$/, '');
            }

            function formatFull(value, symbol) {
                // simple thousands formatting with 2 decimals
                const num = Number(value) || 0;
                return (symbol || '') + num.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
            }

            function refreshAmounts(mode) {
                const els = document.querySelectorAll('.amount');
                els.forEach(el => {
                    const raw = parseFloat(el.getAttribute('data-raw')) || 0;
                    const sym = el.getAttribute('data-symbol') || '';
                    if (mode === 'short') {
                        el.textContent = (sym || '') + abbreviateNumber(raw);
                        el.classList.remove('amount-full'); el.classList.add('amount-short');
                    } else {
                        el.textContent = formatFull(raw, sym);
                        el.classList.remove('amount-short'); el.classList.add('amount-full');
                    }
                });
            }

            document.addEventListener('DOMContentLoaded', function(){
                const btn = document.getElementById('amountToggleBtn');
                const pref = localStorage.getItem('amountDisplay') || 'short';
                // Initialize label and view
                if (btn) btn.textContent = (pref === 'short') ? 'Short' : 'Full';
                refreshAmounts(pref === 'short' ? 'short' : 'full');

                if (btn) btn.addEventListener('click', function(){
                    const current = localStorage.getItem('amountDisplay') || 'short';
                    const next = (current === 'short') ? 'full' : 'short';
                    localStorage.setItem('amountDisplay', next);
                    btn.textContent = (next === 'short') ? 'Short' : 'Full';
                    refreshAmounts(next);
                });
            });
            </script>
<?= $this->endSection() ?>