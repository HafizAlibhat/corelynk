<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Professional Dashboard Styles */
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .dashboard-header h1 {
            font-size: 2.5rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }
        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        .metric-card {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #667eea;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .metric-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        .metric-card .card-title {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .metric-card .card-value {
            font-size: 2rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
        }
        .metric-card .card-change {
            font-size: 0.85rem;
        }
        .metric-card .card-change.positive {
            color: #28a745;
        }
        .metric-card .card-change.negative {
            color: #dc3545;
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .chart-container h3 {
            color: #495057;
            margin-bottom: 1.5rem;
            font-weight: 600;
        }
        .table-responsive {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
            color: #495057;
        }
        .recent-activity {
            background: white;
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .activity-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .activity-item:last-child {
            border-bottom: none;
        }
        .activity-item .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        .activity-item .activity-icon.success {
            background-color: #d4edda;
            color: #155724;
        }
        .activity-item .activity-icon.warning {
            background-color: #fff3cd;
            color: #856404;
        }
        .activity-item .activity-icon.info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        .gauge-container {
            text-align: center;
            margin-bottom: 2rem;
        }
        .gauge-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
        }
        .gauge {
            width: 120px;
            height: 120px;
            margin: 0 auto;
        }
        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 2rem;
            }
            .metric-card {
                margin-bottom: 1rem;
            }
            .chart-container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <!-- Dashboard Header -->
        <div class="dashboard-header text-center">
            <h1><i class="bi bi-graph-up-arrow me-3"></i>Accounting Dashboard</h1>
            <p>Real-time financial insights and analytics</p>
        </div>

        <!-- Metric Cards Row -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="metric-card">
                    <div class="card-title">Total Revenue</div>
                    <div class="card-value">$<?= number_format($total_revenue ?? 0, 2) ?></div>
                    <div class="card-change positive">
                        <i class="bi bi-arrow-up"></i> +12.5% from last month
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="metric-card">
                    <div class="card-title">Total Expenses</div>
                    <div class="card-value">$<?= number_format($total_expenses ?? 0, 2) ?></div>
                    <div class="card-change negative">
                        <i class="bi bi-arrow-down"></i> -3.2% from last month
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="metric-card">
                    <div class="card-title">Net Profit</div>
                    <div class="card-value">$<?= number_format(($total_revenue ?? 0) - ($total_expenses ?? 0), 2) ?></div>
                    <div class="card-change positive">
                        <i class="bi bi-arrow-up"></i> +8.7% from last month
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="metric-card">
                    <div class="card-title">Cash Position</div>
                    <div class="card-value">$<?= number_format($cash_position_value ?? 0, 2) ?></div>
                    <div class="card-change positive">
                        <i class="bi bi-arrow-up"></i> +5.1% from last month
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="chart-container">
                    <h3><i class="bi bi-bar-chart-line me-2"></i>Income vs Expenses</h3>
                    <canvas id="incomeExpensesChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-4 mb-4">
                <div class="chart-container">
                    <h3><i class="bi bi-pie-chart me-2"></i>AP/AR Balance</h3>
                    <canvas id="apArChart" width="200" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="table-responsive">
                    <h3><i class="bi bi-receipt me-2"></i>Sales by Product</h3>
                    <table class="table table-hover">
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
                                        <td>$<?= number_format($product['sales'] ?? 0, 2) ?></td>
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
                    <table class="table table-hover">
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
                                        <td>$<?= number_format($vendor['expenses'] ?? 0, 2) ?></td>
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
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="gauge-container">
                    <div class="gauge-title">YTD Expenses</div>
                    <canvas id="ytdExpensesGauge" class="gauge"></canvas>
                </div>
            </div>
        </div>

        <!-- Additional Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="chart-container">
                    <h3><i class="bi bi-graph-up me-2"></i>Monthly Income vs Expenses</h3>
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                </div>
            </div>
            <div class="col-lg-6 mb-4">
                <div class="chart-container">
                    <h3><i class="bi bi-people me-2"></i>Top Customers</h3>
                    <canvas id="topCustomersChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3><i class="bi bi-activity me-2"></i>Recent Activity</h3>
            <div class="activity-list">
                <?php if (isset($recent_activity) && is_array($recent_activity)): ?>
                    <?php foreach ($recent_activity as $activity): ?>
                        <div class="activity-item d-flex align-items-center">
                            <div class="activity-icon <?= esc($activity['type'] ?? 'info') ?>">
                                <i class="bi <?= esc($activity['icon'] ?? 'bi-info-circle') ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <div class="fw-bold"><?= esc($activity['title'] ?? 'Activity') ?></div>
                                <small class="text-muted"><?= esc($activity['description'] ?? '') ?> - <?= esc($activity['time'] ?? '') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="activity-item d-flex align-items-center">
                        <div class="activity-icon info">
                            <i class="bi bi-info-circle"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="fw-bold">No Recent Activity</div>
                            <small class="text-muted">Activity feed is empty</small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Sample data fallbacks
        const ie_income = <?= json_encode($ie_income ?? [12000, 15000, 18000, 22000, 25000, 28000]) ?>;
        const ie_expenses = <?= json_encode($ie_expenses ?? [8000, 9500, 11000, 13000, 14000, 16000]) ?>;
        const ap_data = <?= json_encode($ap_data ?? [45000, 32000]) ?>;
        const ar_data = <?= json_encode($ar_data ?? [52000, 38000]) ?>;
        const monthly_income = <?= json_encode($monthly_income ?? [10000, 12000, 14000, 16000, 18000, 20000]) ?>;
        const monthly_expenses = <?= json_encode($monthly_expenses ?? [8000, 9000, 10000, 11000, 12000, 13000]) ?>;
        const top_customers_labels = <?= json_encode($top_customers_labels ?? ['Customer A', 'Customer B', 'Customer C', 'Customer D', 'Customer E']) ?>;
        const top_customers_data = <?= json_encode($top_customers_data ?? [25000, 22000, 18000, 15000, 12000]) ?>;
        const ytd_revenue = <?= json_encode($ytd_revenue ?? 75) ?>;
        const ytd_expenses = <?= json_encode($ytd_expenses ?? 60) ?>;

        // Income vs Expenses Chart
        const ctx1 = document.getElementById('incomeExpensesChart');
        if (ctx1) {
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
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
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
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
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>