<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="bi bi-speedometer2 me-2"></i>
                Dashboard
            </h2>
            <div class="d-flex align-items-center gap-3">
                <div class="text-muted me-2">
                    Welcome back, <strong><?= esc($dashboard_data['user_name']) ?></strong>
                    <span class="badge bg-primary ms-2"><?= ucfirst($dashboard_data['role']) ?></span>
                </div>
                <div>
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="modulesModalBtn">
                        <i class="bi bi-grid-3x3-gap me-1"></i> Modules
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-primary bg-gradient rounded-circle p-3">
                            <i class="bi bi-clipboard-check text-white fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Active Work Orders</h6>
                        <h3 class="mb-0"><?= number_format($dashboard_data['total_active_work_orders']) ?></h3>
                        <?php if ($dashboard_data['overdue_work_orders'] > 0): ?>
                            <small class="text-danger">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= $dashboard_data['overdue_work_orders'] ?> overdue
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-success bg-gradient rounded-circle p-3">
                            <i class="bi bi-box text-white fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Active Products</h6>
                        <h3 class="mb-0"><?= number_format($dashboard_data['total_products']) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-info bg-gradient rounded-circle p-3">
                            <i class="bi bi-building text-white fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Active Vendors</h6>
                        <h3 class="mb-0"><?= number_format($dashboard_data['total_vendors']) ?></h3>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="flex-shrink-0">
                        <div class="bg-warning bg-gradient rounded-circle p-3">
                            <i class="bi bi-layers text-white fs-4"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h6 class="text-muted mb-1">Stock Value</h6>
                        <h3 class="mb-0">
                            <?php if (isset($dashboard_data['total_stock_value'])): ?>
                                $<?= number_format($dashboard_data['total_stock_value'], 0) ?>
                            <?php else: ?>
                                $0
                            <?php endif; ?>
                        </h3>
                        <?php if (isset($dashboard_data['low_stock_count']) && $dashboard_data['low_stock_count'] > 0): ?>
                            <small class="text-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <?= $dashboard_data['low_stock_count'] ?> low stock
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Shipment Follow-up -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-truck me-2"></i>
                    Pending Shipment Follow-up
                </h5>
                <a href="<?= site_url('delivery-orders/pending-followup') ?>" class="btn btn-sm btn-outline-primary">
                    Open Monitor
                </a>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <strong><?= (int)($dashboard_data['pending_shipments_count'] ?? 0) ?></strong>
                        <span class="text-muted">shipments are pending delivery confirmation</span>
                    </div>
                </div>

                <?php $pendingShipments = $dashboard_data['pending_shipments_list'] ?? []; ?>
                <?php if (!empty($pendingShipments)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>DO</th>
                                    <th>SO / Customer</th>
                                    <th>Tracking</th>
                                    <th>Transit</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingShipments as $row): ?>
                                    <?php
                                        $shippedAt = $row['shipped_at'] ?? null;
                                        $estDays = (int)($row['estimated_delivery_days'] ?? 0);
                                        $daysInTransit = $shippedAt ? (int)floor((time() - strtotime($shippedAt)) / 86400) : 0;
                                        $isOverdue = $estDays > 0 && $daysInTransit > $estDays;
                                        $doIdentifier = !empty($row['public_id']) ? $row['public_id'] : (int)$row['id'];
                                    ?>
                                    <tr>
                                        <td>
                                            <a href="<?= site_url('delivery-orders/view/' . $doIdentifier) ?>" class="text-decoration-none fw-semibold">
                                                <?= esc($row['do_number'] ?? 'DO') ?>
                                            </a>
                                        </td>
                                        <td>
                                            <div><?= esc($row['order_number'] ?? '-') ?></div>
                                            <small class="text-muted"><?= esc($row['customer_code'] ?? ($row['customer_name'] ?? 'N/A')) ?></small>
                                        </td>
                                        <td>
                                            <?php if (!empty($row['tracking_number'])): ?>
                                                <span class="badge bg-light text-dark border"><?= esc($row['tracking_number']) ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Missing</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge <?= $isOverdue ? 'bg-danger' : 'bg-secondary' ?>">
                                                Day <?= $daysInTransit ?><?= $estDays > 0 ? ' / ' . $estDays : '' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No pending shipments at the moment.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Role-specific Content -->
<div class="row">
    <?php if (in_array($dashboard_data['role'], ['admin', 'planner'])): ?>
        <!-- Planner Dashboard -->
        <div class="col-lg-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-graph-up me-2"></i>
                        Work Orders Overview
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="workOrdersChart" height="300"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Alerts & Notifications
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($dashboard_data['overdue_list']) && !empty($dashboard_data['overdue_list'])): ?>
                        <h6 class="text-danger">Overdue Work Orders</h6>
                        <ul class="list-unstyled">
                            <?php foreach (array_slice($dashboard_data['overdue_list'], 0, 5) as $overdue): ?>
                                <li class="mb-2">
                                    <a href="<?= base_url('/work-orders/' . $overdue['id']) ?>" class="text-decoration-none">
                                        <small class="text-muted"><?= esc($overdue['wo_number']) ?></small><br>
                                        <span class="text-dark"><?= esc($overdue['product_name'] ?? 'N/A') ?></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (isset($dashboard_data['low_stock_list']) && !empty($dashboard_data['low_stock_list'])): ?>
                        <h6 class="text-warning mt-3">Low Stock Components</h6>
                        <ul class="list-unstyled">
                            <?php foreach (array_slice($dashboard_data['low_stock_list'], 0, 5) as $component): ?>
                                <li class="mb-2">
                                    <a href="<?= base_url('/components/' . $component['id']) ?>" class="text-decoration-none">
                                        <span class="text-dark"><?= esc($component['name']) ?></span><br>
                                        <small class="text-muted">
                                            Stock: <?= $component['current_stock'] ?> / Min: <?= $component['minimum_stock'] ?>
                                        </small>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($dashboard_data['role'] == 'production'): ?>
        <!-- Production Dashboard -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat me-2"></i>
                        My Production Tasks
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="text-primary"><?= $dashboard_data['pending_process_runs'] ?? 0 ?></h3>
                            <small class="text-muted">Pending</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-warning"><?= $dashboard_data['in_progress_process_runs'] ?? 0 ?></h3>
                            <small class="text-muted">In Progress</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-success"><?= $dashboard_data['completed_runs_today'] ?? 0 ?></h3>
                            <small class="text-muted">Completed Today</small>
                        </div>
                    </div>
                    
                    <?php if (isset($dashboard_data['efficiency_today'])): ?>
                        <hr>
                        <div class="text-center">
                            <h4 class="mb-1"><?= number_format($dashboard_data['efficiency_today'], 1) ?>%</h4>
                            <small class="text-muted">Today's Efficiency</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-list-task me-2"></i>
                        Pending Assignments
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($dashboard_data['pending_runs_list']) && !empty($dashboard_data['pending_runs_list'])): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($dashboard_data['pending_runs_list'], 0, 5) as $run): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= esc($run['wo_number']) ?></h6>
                                            <p class="mb-1"><?= esc($run['product_name'] ?? 'N/A') ?> - <?= esc($run['process_name'] ?? '') ?></p>
                                        </div>
                                        <small class="text-muted"><?= date('M j', strtotime($run['created_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No pending assignments</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($dashboard_data['role'] == 'qc'): ?>
        <!-- QC Dashboard -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check me-2"></i>
                        Quality Control Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6">
                            <h3 class="text-warning"><?= $dashboard_data['pending_inspections'] ?? 0 ?></h3>
                            <small class="text-muted">Pending Inspections</small>
                        </div>
                        <div class="col-6">
                            <h3 class="text-success"><?= $dashboard_data['inspections_today'] ?? 0 ?></h3>
                            <small class="text-muted">Inspected Today</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <h4 class="text-success"><?= number_format($dashboard_data['pass_rate_today'] ?? 0, 1) ?>%</h4>
                            <small class="text-muted">Today's Pass Rate</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-info"><?= number_format($dashboard_data['monthly_pass_rate'] ?? 0, 1) ?>%</h4>
                            <small class="text-muted">Monthly Pass Rate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-clock me-2"></i>
                        Pending Inspections
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($dashboard_data['pending_inspection_list']) && !empty($dashboard_data['pending_inspection_list'])): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($dashboard_data['pending_inspection_list'], 0, 5) as $inspection): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= esc($inspection['wo_number']) ?></h6>
                                            <p class="mb-1"><?= esc($inspection['product_name'] ?? 'N/A') ?></p>
                                        </div>
                                        <small class="text-muted"><?= date('M j', strtotime($inspection['completed_at'])) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">No pending inspections</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($dashboard_data['role'] == 'stores'): ?>
        <!-- Stores Dashboard -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-boxes me-2"></i>
                        Inventory Summary
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <h3 class="text-success"><?= $dashboard_data['stock_status_summary']['ok'] ?? 0 ?></h3>
                            <small class="text-muted">Good Stock</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-warning"><?= $dashboard_data['stock_status_summary']['warning'] ?? 0 ?></h3>
                            <small class="text-muted">Warning</small>
                        </div>
                        <div class="col-4">
                            <h3 class="text-danger"><?= $dashboard_data['stock_status_summary']['low_stock'] ?? 0 ?></h3>
                            <small class="text-muted">Low Stock</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h5 class="mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Low Stock Items
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (isset($dashboard_data['low_stock_list']) && !empty($dashboard_data['low_stock_list'])): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($dashboard_data['low_stock_list'], 0, 5) as $item): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= esc($item['name']) ?></h6>
                                            <p class="mb-1">Current: <?= $item['current_stock'] ?> | Min: <?= $item['minimum_stock'] ?></p>
                                        </div>
                                        <span class="badge bg-warning">Low</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">All stock levels are good</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Recent Work Orders -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Recent Work Orders
                    </h5>
                    <a href="<?= base_url('/work-orders') ?>" class="btn btn-sm btn-outline-primary">
                        View All <i class="bi bi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($dashboard_data['recent_work_orders'])): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>WO Number</th>
                                    <th>Product</th>
                                    <th>Quantity</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboard_data['recent_work_orders'] as $wo): ?>
                                    <tr>
                                        <td>
                                            <a href="<?= base_url('/work-orders/' . ($wo['id'] ?? '#')) ?>" class="text-decoration-none">
                                                <?= esc($wo['wo_number'] ?? 'N/A') ?>
                                            </a>
                                        </td>
                                        <td><?= esc($wo['product_name'] ?? 'N/A') ?></td>
                                        <td><?= number_format($wo['quantity_ordered'] ?? 0) ?></td>
                                        <td>
                                            <?php
                                            $statusColors = [
                                                'planned' => 'primary',
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                'on_hold' => 'secondary',
                                                'cancelled' => 'danger'
                                            ];
                                            $statusColor = $statusColors[$wo['status'] ?? 'unknown'] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $statusColor ?>">
                                                <?= ucfirst(str_replace('_', ' ', $wo['status'] ?? 'Unknown')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $priorityColors = [
                                                'low' => 'success',
                                                'normal' => 'info',
                                                'high' => 'warning',
                                                'urgent' => 'danger'
                                            ];
                                            $priorityColor = $priorityColors[$wo['priority'] ?? 'normal'] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $priorityColor ?>">
                                                <?= ucfirst($wo['priority'] ?? 'Normal') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (isset($wo['due_date']) && $wo['due_date']): ?>
                                                <?= date('M j, Y', strtotime($wo['due_date'])) ?>
                                                <?php if (strtotime($wo['due_date']) < time() && ($wo['status'] ?? '') != 'completed'): ?>
                                                    <i class="bi bi-exclamation-triangle text-danger ms-1" title="Overdue"></i>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not set</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-1"></i>
                        <p class="mt-2">No recent work orders</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?= view('components/modules_drawer') ?>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('modulesModalBtn');
    if (btn) {
        btn.addEventListener('click', function(e) {
            // Fallback: force open modal if Bootstrap data attributes fail
            setTimeout(function() {
                var modalEl = document.getElementById('modulesModal');
                if (modalEl && !modalEl.classList.contains('show')) {
                    if (window.PMS && PMS.modal && PMS.modal.show) {
                        PMS.modal.show('modulesModal');
                    } else if (window.bootstrap && window.bootstrap.Modal) {
                        var modal = new window.bootstrap.Modal(modalEl);
                        modal.show();
                    }
                }
            }, 200);
        });
    }
});
</script>
<?= $this->endSection() ?>

<?= $this->section('js') ?>
<script>
// Lightweight lazy loader for Chart.js: prefer local file, fallback to CDN
function ensureChartJs(){
    return new Promise((resolve, reject)=>{
        if (window.Chart) return resolve();
        const tryLoad = (src, onfail)=>{ const s=document.createElement('script'); s.src=src; s.defer=true; s.onload=()=>resolve(); s.onerror=()=>onfail&&onfail(); document.head.appendChild(s); };
        // Try local first (drop chart.umd.min.js into public/assets/vendor/chart.js/)
        tryLoad('<?= base_url('assets/vendor/chart.js/chart.umd.min.js') ?>', ()=>{
            // Fallback to CDN
            tryLoad('https://cdn.jsdelivr.net/npm/chart.js');
        });
        // Safety timeout to avoid hanging forever
        setTimeout(()=>{ if(!window.Chart) reject(new Error('Chart.js load timeout')); }, 8000);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    <?php if (in_array($dashboard_data['role'], ['admin', 'planner'])): ?>
    ensureChartJs()
      .then(()=> loadWorkOrdersChart())
      .catch((e)=>{ console.warn('Chart.js not available, skipping chart.', e); });
    <?php endif; ?>
});

<?php if (in_array($dashboard_data['role'], ['admin', 'planner'])): ?>
function loadWorkOrdersChart() {
    fetch('<?= base_url('/dashboard/widget-data') ?>?widget=work_orders_chart')
        .then(response => response.json())
        .then(data => {
            const ctx = document.getElementById('workOrdersChart').getContext('2d');
            new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'bottom' } }
                }
            });
        })
        .catch(error => { console.error('Error loading chart:', error); });
}
<?php endif; ?>
</script>
<?= $this->endSection() ?>

<?= $this->section('modals') ?>
<!-- Modules Modal -->
<!-- Keep the Bootstrap modal as a fallback (hidden) but prefer the no-dependency drawer -->
<div class="modal fade" id="modulesModal" tabindex="-1" aria-labelledby="modulesModalLabel" aria-modal="true" role="dialog" style="display:none;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modulesModalLabel">System Modules</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?= view('components/modules_panel') ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<?= view('components/modules_drawer') ?>
<?= $this->endSection() ?>
