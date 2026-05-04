<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-graph-up me-2"></i>
                Reports & Analytics
            </h2>
            <div class="d-flex gap-2">
                <button class="btn btn-outline-primary" onclick="exportDashboard()">
                    <i class="bi bi-download me-2"></i>
                    Export Dashboard
                </button>
                <button class="btn btn-primary" onclick="scheduleReport()">
                    <i class="bi bi-calendar-plus me-2"></i>
                    Schedule Report
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Report Categories -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-2">
                        <a href="#production-reports" class="text-decoration-none" onclick="showReportSection('production')">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-gear fs-1 text-primary mb-2"></i>
                                <h6 class="mb-0">Production</h6>
                                <small class="text-muted">Work Orders, Efficiency</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#quality-reports" class="text-decoration-none" onclick="showReportSection('quality')">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-shield-check fs-1 text-success mb-2"></i>
                                <h6 class="mb-0">Quality</h6>
                                <small class="text-muted">Inspections, Defects</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#inventory-reports" class="text-decoration-none" onclick="showReportSection('inventory')">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-boxes fs-1 text-warning mb-2"></i>
                                <h6 class="mb-0">Inventory</h6>
                                <small class="text-muted">Stock, Movements</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#financial-reports" class="text-decoration-none" onclick="showReportSection('financial')">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-currency-dollar fs-1 text-info mb-2"></i>
                                <h6 class="mb-0">Financial</h6>
                                <small class="text-muted">Costs, Profitability</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#vendor-reports" class="text-decoration-none" onclick="showReportSection('vendor')">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-people fs-1 text-secondary mb-2"></i>
                                <h6 class="mb-0">Vendors</h6>
                                <small class="text-muted">Performance, Orders</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="#custom-reports" class="text-decoration-none" onclick="showReportSection('custom')">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-sliders fs-1 text-dark mb-2"></i>
                                <h6 class="mb-0">Custom</h6>
                                <small class="text-muted">Build Your Own</small>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-2">
                        <a href="<?= base_url('/product-ledger') ?>" class="text-decoration-none">
                            <div class="p-3 border rounded hover-shadow">
                                <i class="bi bi-journal-text fs-1 text-primary mb-2"></i>
                                <h6 class="mb-0">Product Ledger</h6>
                                <small class="text-muted">Buy &amp; Sell History</small>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Metrics Overview -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <i class="bi bi-graph-up-arrow fs-2 text-success mb-2"></i>
                <h4 class="text-success mb-1"><?= number_format($metrics['production_efficiency'] ?? 85.2, 1) ?>%</h4>
                <small class="text-muted">Production Efficiency</small>
                <div class="mt-2">
                    <small class="text-success">
                        <i class="bi bi-arrow-up"></i> +2.3% vs last month
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <i class="bi bi-shield-check fs-2 text-primary mb-2"></i>
                <h4 class="text-primary mb-1"><?= number_format($metrics['quality_pass_rate'] ?? 96.7, 1) ?>%</h4>
                <small class="text-muted">Quality Pass Rate</small>
                <div class="mt-2">
                    <small class="text-success">
                        <i class="bi bi-arrow-up"></i> +1.2% vs last month
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <i class="bi bi-boxes fs-2 text-warning mb-2"></i>
                <h4 class="text-warning mb-1"><?= number_format($metrics['inventory_turnover'] ?? 12.4, 1) ?>x</h4>
                <small class="text-muted">Inventory Turnover</small>
                <div class="mt-2">
                    <small class="text-danger">
                        <i class="bi bi-arrow-down"></i> -0.8x vs last month
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center">
            <div class="card-body">
                <i class="bi bi-currency-dollar fs-2 text-info mb-2"></i>
                <h4 class="text-info mb-1">$<?= number_format($metrics['cost_per_unit'] ?? 24.67, 2) ?></h4>
                <small class="text-muted">Avg Cost Per Unit</small>
                <div class="mt-2">
                    <small class="text-success">
                        <i class="bi bi-arrow-down"></i> -$1.23 vs last month
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Forecasting (Beta) -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bi bi-activity me-2"></i>
                    Forecasting (Beta)
                </h6>
                <small class="text-muted">Uses live production logs; JSON endpoints open in a new tab</small>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-outline-primary btn-sm" target="_blank" href="<?= site_url('reports/forecast/summary') ?>">
                        <i class="bi bi-bar-chart-line me-1"></i> Summary (JSON)
                    </a>
                    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="<?= site_url('reports/forecast/bottlenecks') ?>">
                        <i class="bi bi-exclamation-octagon me-1"></i> Bottlenecks
                    </a>
                    <a class="btn btn-outline-success btn-sm" target="_blank" href="<?= site_url('reports/forecast/burnup') ?>">
                        <i class="bi bi-graph-up me-1"></i> Burn-up
                    </a>
                    <a class="btn btn-outline-warning btn-sm" target="_blank" href="<?= site_url('reports/forecast/risks') ?>">
                        <i class="bi bi-exclamation-triangle me-1"></i> At-Risk WOs
                    </a>
                </div>
            </div>
        </div>
    </div>
    
</div>

<!-- Production Reports Section -->
<div id="production-section" class="report-section">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-gear me-2"></i>
                Production Reports
            </h4>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">Production Efficiency Trend</h6>
                        <div class="btn-group btn-group-sm" role="group">
                            <input type="radio" class="btn-check" name="prodPeriod" id="prod7d" checked>
                            <label class="btn btn-outline-secondary" for="prod7d">7D</label>
                            
                            <input type="radio" class="btn-check" name="prodPeriod" id="prod30d">
                            <label class="btn btn-outline-secondary" for="prod30d">30D</label>
                            
                            <input type="radio" class="btn-check" name="prodPeriod" id="prod90d">
                            <label class="btn btn-outline-secondary" for="prod90d">90D</label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <canvas id="productionChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Quick Production Reports</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="generateReport('work_orders_summary')">
                            <i class="bi bi-list-check me-2"></i>Work Orders Summary
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="generateReport('production_schedule')">
                            <i class="bi bi-calendar-check me-2"></i>Production Schedule
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="generateReport('efficiency_by_process')">
                            <i class="bi bi-speedometer2 me-2"></i>Efficiency by Process
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="generateReport('downtime_analysis')">
                            <i class="bi bi-pause-circle me-2"></i>Downtime Analysis
                        </button>
                        <button class="btn btn-outline-primary btn-sm" onclick="generateReport('capacity_utilization')">
                            <i class="bi bi-pie-chart me-2"></i>Capacity Utilization
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Production Alerts</h6>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning py-2">
                        <small>
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            3 work orders behind schedule
                        </small>
                    </div>
                    <div class="alert alert-info py-2">
                        <small>
                            <i class="bi bi-info-circle me-1"></i>
                            Assembly line efficiency down 5%
                        </small>
                    </div>
                    <div class="alert alert-success py-2">
                        <small>
                            <i class="bi bi-check-circle me-1"></i>
                            Quality targets met this week
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quality Reports Section -->
<div id="quality-section" class="report-section" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-shield-check me-2"></i>
                Quality Reports
            </h4>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Quality Pass Rates</h6>
                </div>
                <div class="card-body">
                    <canvas id="qualityChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Defect Categories</h6>
                </div>
                <div class="card-body">
                    <canvas id="defectChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Quality Control Reports</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-outline-success btn-sm w-100 mb-2" onclick="generateReport('qc_inspection_summary')">
                                <i class="bi bi-clipboard-check me-2"></i>Inspection Summary
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-success btn-sm w-100 mb-2" onclick="generateReport('defect_trend_analysis')">
                                <i class="bi bi-graph-down me-2"></i>Defect Trends
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-success btn-sm w-100 mb-2" onclick="generateReport('inspector_performance')">
                                <i class="bi bi-person-check me-2"></i>Inspector Performance
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-success btn-sm w-100 mb-2" onclick="generateReport('corrective_actions')">
                                <i class="bi bi-tools me-2"></i>Corrective Actions
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Inventory Reports Section -->
<div id="inventory-section" class="report-section" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-boxes me-2"></i>
                Inventory Reports
            </h4>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Inventory Levels by Category</h6>
                </div>
                <div class="card-body">
                    <canvas id="inventoryChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Stock Alerts</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Out of Stock</small>
                            <span class="badge bg-danger"><?= $inventory_alerts['out_of_stock'] ?? 3 ?></span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-danger" style="width: 15%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Low Stock</small>
                            <span class="badge bg-warning"><?= $inventory_alerts['low_stock'] ?? 12 ?></span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: 60%"></div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Overstock</small>
                            <span class="badge bg-info"><?= $inventory_alerts['overstock'] ?? 5 ?></span>
                        </div>
                        <div class="progress" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: 25%"></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Inventory Reports</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-warning btn-sm" onclick="generateReport('stock_valuation')">
                            <i class="bi bi-currency-dollar me-2"></i>Stock Valuation
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="generateReport('abc_analysis')">
                            <i class="bi bi-bar-chart me-2"></i>ABC Analysis
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="generateReport('movement_analysis')">
                            <i class="bi bi-arrow-left-right me-2"></i>Movement Analysis
                        </button>
                        <button class="btn btn-outline-warning btn-sm" onclick="generateReport('reorder_suggestions')">
                            <i class="bi bi-cart-plus me-2"></i>Reorder Suggestions
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Financial Reports Section -->
<div id="financial-section" class="report-section" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-currency-dollar me-2"></i>
                Financial Reports
            </h4>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Cost Breakdown</h6>
                </div>
                <div class="card-body">
                    <canvas id="costChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Profitability Trend</h6>
                </div>
                <div class="card-body">
                    <canvas id="profitChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Financial Reports</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="generateReport('cost_analysis')">
                                <i class="bi bi-graph-up me-2"></i>Cost Analysis
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="generateReport('profit_loss')">
                                <i class="bi bi-calculator me-2"></i>Profit & Loss
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="generateReport('budget_variance')">
                                <i class="bi bi-bar-chart-line me-2"></i>Budget Variance
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button class="btn btn-outline-info btn-sm w-100 mb-2" onclick="generateReport('roi_analysis')">
                                <i class="bi bi-percent me-2"></i>ROI Analysis
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Vendor Reports Section -->
<div id="vendor-section" class="report-section" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-people me-2"></i>
                Vendor Reports
            </h4>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Vendor Performance Scorecard</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Vendor</th>
                                    <th>Quality Score</th>
                                    <th>Delivery Score</th>
                                    <th>Cost Score</th>
                                    <th>Overall</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Steel Supply Co.</td>
                                    <td><span class="badge bg-success">95%</span></td>
                                    <td><span class="badge bg-warning">78%</span></td>
                                    <td><span class="badge bg-success">92%</span></td>
                                    <td><span class="badge bg-success">88%</span></td>
                                </tr>
                                <tr>
                                    <td>Electronics Parts Ltd.</td>
                                    <td><span class="badge bg-success">98%</span></td>
                                    <td><span class="badge bg-success">95%</span></td>
                                    <td><span class="badge bg-warning">85%</span></td>
                                    <td><span class="badge bg-success">93%</span></td>
                                </tr>
                                <tr>
                                    <td>Hardware Solutions</td>
                                    <td><span class="badge bg-warning">82%</span></td>
                                    <td><span class="badge bg-success">90%</span></td>
                                    <td><span class="badge bg-success">89%</span></td>
                                    <td><span class="badge bg-warning">87%</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Vendor Reports</h6>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-secondary btn-sm" onclick="generateReport('vendor_performance')">
                            <i class="bi bi-graph-up me-2"></i>Performance Report
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="generateReport('purchase_analysis')">
                            <i class="bi bi-cart-check me-2"></i>Purchase Analysis
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="generateReport('delivery_performance')">
                            <i class="bi bi-truck me-2"></i>Delivery Performance
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="generateReport('vendor_comparison')">
                            <i class="bi bi-bar-chart me-2"></i>Vendor Comparison
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Reports Section -->
<div id="custom-section" class="report-section" style="display: none;">
    <div class="row mb-4">
        <div class="col-12">
            <h4 class="mb-3">
                <i class="bi bi-sliders me-2"></i>
                Custom Report Builder
            </h4>
        </div>
    </div>
    
    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Build Custom Report</h6>
                </div>
                <div class="card-body">
                    <form id="customReportForm">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Report Name</label>
                                    <input type="text" class="form-control" placeholder="Enter report name">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Data Source</label>
                                    <select class="form-select">
                                        <option>Work Orders</option>
                                        <option>Quality Records</option>
                                        <option>Inventory</option>
                                        <option>Vendors</option>
                                        <option>Financial Data</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date Range</label>
                                    <select class="form-select">
                                        <option>Last 7 days</option>
                                        <option>Last 30 days</option>
                                        <option>Last 90 days</option>
                                        <option>Last 12 months</option>
                                        <option>Custom Range</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Chart Type</label>
                                    <select class="form-select">
                                        <option>Line Chart</option>
                                        <option>Bar Chart</option>
                                        <option>Pie Chart</option>
                                        <option>Table</option>
                                        <option>Dashboard</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Metrics to Include</label>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="metric1">
                                        <label class="form-check-label" for="metric1">Count</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="metric2">
                                        <label class="form-check-label" for="metric2">Sum</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="metric3">
                                        <label class="form-check-label" for="metric3">Average</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="metric4">
                                        <label class="form-check-label" for="metric4">Percentage</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="metric5">
                                        <label class="form-check-label" for="metric5">Trend</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="metric6">
                                        <label class="form-check-label" for="metric6">Variance</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-primary" onclick="buildCustomReport()">
                                <i class="bi bi-play me-2"></i>Generate Report
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="previewReport()">
                                <i class="bi bi-eye me-2"></i>Preview
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="saveReportTemplate()">
                                <i class="bi bi-save me-2"></i>Save Template
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-transparent border-bottom">
                    <h6 class="mb-0">Saved Report Templates</h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span>Monthly Production Summary</span>
                                <i class="bi bi-play text-primary"></i>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span>Quality Metrics Dashboard</span>
                                <i class="bi bi-play text-primary"></i>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span>Inventory Turnover Analysis</span>
                                <i class="bi bi-play text-primary"></i>
                            </div>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex justify-content-between">
                                <span>Vendor Performance Report</span>
                                <i class="bi bi-play text-primary"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Report Generation Modal -->
<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate Report</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="reportContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Generating report...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="exportReport()">
                    <i class="bi bi-download me-2"></i>Export PDF
                </button>
                <button type="button" class="btn btn-success" onclick="exportReportExcel()">
                    <i class="bi bi-file-earmark-excel me-2"></i>Export Excel
                </button>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<!-- Chart.js is lazy-loaded below when needed; local-first with CDN fallback -->
<script>
// Show report section
function showReportSection(section) {
    // Hide all sections
    document.querySelectorAll('.report-section').forEach(el => {
        el.style.display = 'none';
    });
    
    // Show selected section
    document.getElementById(section + '-section').style.display = 'block';
    
    // Initialize charts for the section
    if (section === 'production') {
    ensureChartJs().then(initProductionChart).catch(()=>console.warn('Chart.js unavailable'));
    } else if (section === 'quality') {
        initQualityCharts();
    } else if (section === 'inventory') {
    ensureChartJs().then(initInventoryChart).catch(()=>console.warn('Chart.js unavailable'));
    } else if (section === 'financial') {
        initFinancialCharts();
    }
}

// Initialize production chart
function ensureChartJs(){
    return new Promise((resolve,reject)=>{
        if(window.Chart) return resolve();
        const tryLoad=(src,next)=>{ const s=document.createElement('script'); s.src=src; s.defer=true; s.onload=()=>resolve(); s.onerror=()=>next&&next(); document.head.appendChild(s); };
        tryLoad('<?= base_url('assets/vendor/chart.js/chart.umd.min.js') ?>', ()=>{
            tryLoad('https://cdn.jsdelivr.net/npm/chart.js');
        });
        setTimeout(()=>{ if(!window.Chart) reject(new Error('timeout')); }, 8000);
    });
}

function initProductionChart() {
    const ctx = document.getElementById('productionChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Efficiency %',
                data: [78, 82, 85, 88, 84, 90, 87],
                borderColor: 'rgb(54, 162, 235)',
                backgroundColor: 'rgba(54, 162, 235, 0.1)',
                tension: 0.4
            }, {
                label: 'Target %',
                data: [85, 85, 85, 85, 85, 85, 85],
                borderColor: 'rgb(255, 99, 132)',
                borderDash: [5, 5]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100
                }
            }
        }
    });
}

// Initialize quality charts
function initQualityCharts() {
    // Quality pass rate chart
    const qCtx = document.getElementById('qualityChart');
    if (qCtx) {
        new Chart(qCtx, {
            type: 'bar',
            data: {
                labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                datasets: [{
                    label: 'Pass Rate %',
                    data: [94, 96, 98, 97],
                    backgroundColor: 'rgba(75, 192, 192, 0.6)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }
    
    // Defect categories chart
    const dCtx = document.getElementById('defectChart');
    if (dCtx) {
        new Chart(dCtx, {
            type: 'pie',
            data: {
                labels: ['Dimensional', 'Visual', 'Functional', 'Material'],
                datasets: [{
                    data: [30, 25, 35, 10],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 205, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}

// Initialize inventory chart
function initInventoryChart() {
    const ctx = document.getElementById('inventoryChart');
    if (!ctx) return;
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Raw Materials', 'Components', 'Fasteners', 'Electronics', 'Hardware'],
            datasets: [{
                label: 'Current Stock',
                data: [1200, 850, 2100, 450, 680],
                backgroundColor: 'rgba(54, 162, 235, 0.6)'
            }, {
                label: 'Reorder Level',
                data: [500, 300, 800, 200, 250],
                backgroundColor: 'rgba(255, 99, 132, 0.6)'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
}

// Initialize financial charts
function initFinancialCharts() {
    // Cost breakdown chart
    const cCtx = document.getElementById('costChart');
    if (cCtx) {
        new Chart(cCtx, {
            type: 'doughnut',
            data: {
                labels: ['Materials', 'Labor', 'Overhead', 'Equipment'],
                datasets: [{
                    data: [45, 30, 15, 10],
                    backgroundColor: [
                        'rgba(255, 99, 132, 0.6)',
                        'rgba(54, 162, 235, 0.6)',
                        'rgba(255, 205, 86, 0.6)',
                        'rgba(75, 192, 192, 0.6)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
    
    // Profitability trend chart
    const pCtx = document.getElementById('profitChart');
    if (pCtx) {
        new Chart(pCtx, {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
                datasets: [{
                    label: 'Profit Margin %',
                    data: [12, 15, 18, 16, 20, 22],
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });
    }
}

// Generate report
function generateReport(reportType) {
    const modal = new bootstrap.Modal(document.getElementById('reportModal'));
    modal.show();
    
    // Simulate report generation
    setTimeout(() => {
        document.getElementById('reportContent').innerHTML = `
            <div class="alert alert-success">
                <h6>${reportType.replace(/_/g, ' ').toUpperCase()} Report Generated Successfully</h6>
                <p>Report contains data from the last 30 days and includes detailed analysis.</p>
            </div>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Current</th>
                            <th>Previous</th>
                            <th>Change</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Total Records</td>
                            <td>1,234</td>
                            <td>1,156</td>
                            <td class="text-success">+6.7%</td>
                        </tr>
                        <tr>
                            <td>Average Value</td>
                            <td>$2,456</td>
                            <td>$2,398</td>
                            <td class="text-success">+2.4%</td>
                        </tr>
                        <tr>
                            <td>Success Rate</td>
                            <td>94.2%</td>
                            <td>92.8%</td>
                            <td class="text-success">+1.4%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;
    }, 2000);
}

// Export functions
function exportReport() {
    window.location.href = '<?= base_url('/reports/export/pdf') ?>';
}

function exportReportExcel() {
    window.location.href = '<?= base_url('/reports/export/excel') ?>';
}

function exportDashboard() {
    window.location.href = '<?= base_url('/reports/export/dashboard') ?>';
}

// Schedule report
function scheduleReport() {
    alert('Report scheduling feature coming soon!');
}

// Custom report functions
function buildCustomReport() {
    generateReport('custom_report');
}

function previewReport() {
    alert('Report preview feature coming soon!');
}

function saveReportTemplate() {
    alert('Report template saved successfully!');
}

// Initialize default section
document.addEventListener('DOMContentLoaded', function() {
    showReportSection('production');
});

// CSS for hover effects
const style = document.createElement('style');
style.textContent = `
    .hover-shadow:hover {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.15) !important;
        transform: translateY(-1px);
        transition: all 0.15s ease-in-out;
    }
`;
document.head.appendChild(style);
</script>
<?= $this->endSection() ?>
