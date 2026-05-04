<?php

namespace App\Controllers;

use App\Models\WorkOrderModel;
use App\Models\ProductModel;
use App\Models\VendorModel;
use App\Models\ComponentModel;
use App\Models\WorkOrderProcessRunModel;
use App\Models\QcRecordModel;
use App\Models\VendorGatepassModel;

class Dashboard extends BaseController
{
    public function index()
    {
        $this->requireAuth();

        // Get dashboard statistics based on user role
        $dashboardData = $this->getDashboardData();

        $data = $this->setPageData([
            'page_title' => 'Dashboard - Production Management System',
            'dashboard_data' => $dashboardData
        ]);

        return view('dashboard/index', $data);
    }

    /**
     * Get dashboard data based on user role
     */
    private function getDashboardData(): array
    {
        $workOrderModel = new WorkOrderModel();
        $productModel = new ProductModel();
        $vendorModel = new VendorModel();
        $componentModel = new ComponentModel();
        $processRunModel = new WorkOrderProcessRunModel();
        $qcModel = new QcRecordModel();
        $gatepassModel = new VendorGatepassModel();

        $data = [
            'role' => $this->currentUser['role'],
            'user_name' => $this->currentUser['first_name'] . ' ' . $this->currentUser['last_name']
        ];

        // Common statistics for all roles
        $data['total_active_work_orders'] = $workOrderModel->whereIn('status', ['planned', 'in_progress'])->countAllResults();
        $data['overdue_work_orders'] = count($workOrderModel->getOverdueWorkOrders());
        $data['total_products'] = $productModel->where('is_active', true)->countAllResults();
        $data['total_vendors'] = $vendorModel->where('is_active', true)->countAllResults();

        $db = \Config\Database::connect();
        $pendingShipmentBuilder = $db->table('delivery_orders do')
            ->where('do.status', 'confirmed')
            ->groupStart()
                ->where('do.delivery_status IS NULL', null, false)
                ->orWhere('do.delivery_status !=', 'delivered')
            ->groupEnd();
        $data['pending_shipments_count'] = (int)$pendingShipmentBuilder->countAllResults(false);
        $data['pending_shipments_list'] = $pendingShipmentBuilder
            ->select('do.id, do.public_id, do.do_number, do.shipped_at, do.estimated_delivery_days, do.tracking_number, do.delivery_status, so.order_number, c.customer_code, c.name as customer_name')
            ->join('sales_orders so', 'so.id = do.sales_order_id', 'left')
            ->join('customers c', 'c.id = so.customer_id', 'left')
            ->orderBy('COALESCE(do.shipped_at, do.updated_at)', 'ASC', false)
            ->limit(5)
            ->get()
            ->getResultArray();

        // Role-specific data
        switch ($this->currentUser['role']) {
            case 'admin':
            case 'planner':
                $data = array_merge($data, $this->getPlannerDashboardData($workOrderModel, $componentModel));
                break;

            case 'production':
                $data = array_merge($data, $this->getProductionDashboardData($processRunModel));
                break;

            case 'qc':
                $data = array_merge($data, $this->getQcDashboardData($qcModel, $processRunModel));
                break;

            case 'stores':
                $data = array_merge($data, $this->getStoresDashboardData($componentModel));
                break;

            case 'accounts':
                $data = array_merge($data, $this->getAccountsDashboardData($componentModel));
                break;

            default:
                // Viewer and other roles get basic data
                break;
        }

        // Recent activities for all roles
        $data['recent_work_orders'] = $workOrderModel->getWorkOrdersWithDetails();
        if (count($data['recent_work_orders']) > 10) {
            $data['recent_work_orders'] = array_slice($data['recent_work_orders'], 0, 10);
        }

        return $data;
    }

    /**
     * Get planner-specific dashboard data
     */
    private function getPlannerDashboardData(WorkOrderModel $workOrderModel, ComponentModel $componentModel): array
    {
        return [
            'planned_work_orders' => $workOrderModel->where('status', 'planned')->countAllResults(),
            'in_progress_work_orders' => $workOrderModel->where('status', 'in_progress')->countAllResults(),
            'completed_work_orders' => $workOrderModel->where('status', 'completed')->countAllResults(),
            'low_stock_components' => count($componentModel->getLowStockComponents()),
            'work_orders_this_month' => $workOrderModel->where('MONTH(created_at)', date('m'))
                                                     ->where('YEAR(created_at)', date('Y'))
                                                     ->countAllResults(),
            'total_stock_value' => $componentModel->getTotalStockValue(),
            'overdue_list' => $workOrderModel->getOverdueWorkOrders()
        ];
    }

    /**
     * Get production-specific dashboard data
     */
    private function getProductionDashboardData(WorkOrderProcessRunModel $processRunModel): array
    {
        $pendingRuns = $processRunModel->where('status', 'pending')->countAllResults();
        $inProgressRuns = $processRunModel->where('status', 'in_progress')->countAllResults();
        $completedToday = $processRunModel->where('status', 'completed')
                                        ->where('DATE(completed_at)', date('Y-m-d'))
                                        ->countAllResults();

        return [
            'pending_process_runs' => $pendingRuns,
            'in_progress_process_runs' => $inProgressRuns,
            'completed_runs_today' => $completedToday,
            'my_assigned_runs' => $processRunModel->where('operator_id', $this->currentUser['id'])
                                                ->whereIn('status', ['pending', 'in_progress'])
                                                ->countAllResults(),
            'efficiency_today' => $this->calculateProductionEfficiency(),
            'pending_runs_list' => $processRunModel->select('work_order_process_runs.*, work_orders.wo_number, products.name as product_name, processes.name as process_name')
                                                  ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                                                  ->join('products', 'products.id = work_orders.product_id')
                                                  ->join('processes', 'processes.id = work_order_process_runs.process_id')
                                                  ->where('work_order_process_runs.status', 'pending')
                                                  ->limit(10)
                                                  ->findAll()
        ];
    }

    /**
     * Get QC-specific dashboard data
     */
    private function getQcDashboardData(QcRecordModel $qcModel, WorkOrderProcessRunModel $processRunModel): array
    {
        $pendingInspections = count($processRunModel->getRunsRequiringQc());
        $qcStats = $qcModel->getQcStatistics(date('Y-m-d'), date('Y-m-d')); // Today's stats
        $monthlyStats = $qcModel->getQcStatistics(date('Y-m-01'), date('Y-m-d')); // This month

        return [
            'pending_inspections' => $pendingInspections,
            'inspections_today' => $qcStats['total_inspections'],
            'pass_rate_today' => $qcStats['pass_rate'],
            'failed_records_requiring_action' => count($qcModel->getFailedQcRecords()),
            'monthly_pass_rate' => $monthlyStats['pass_rate'],
            'monthly_inspections' => $monthlyStats['total_inspections'],
            'pending_inspection_list' => $processRunModel->getRunsRequiringQc()
        ];
    }

    /**
     * Get stores-specific dashboard data
     */
    private function getStoresDashboardData(ComponentModel $componentModel): array
    {
        $lowStockComponents = $componentModel->getLowStockComponents();
        $componentsWithStatus = $componentModel->getComponentsWithStockStatus();
        
        $stockStatusSummary = [
            'low_stock' => 0,
            'warning' => 0,
            'ok' => 0
        ];

        foreach ($componentsWithStatus as $component) {
            switch ($component['stock_status']) {
                case 'Low Stock':
                    $stockStatusSummary['low_stock']++;
                    break;
                case 'Warning':
                    $stockStatusSummary['warning']++;
                    break;
                default:
                    $stockStatusSummary['ok']++;
                    break;
            }
        }

        return [
            'total_components' => count($componentsWithStatus),
            'low_stock_count' => count($lowStockComponents),
            'total_stock_value' => $componentModel->getTotalStockValue(),
            'stock_status_summary' => $stockStatusSummary,
            'low_stock_list' => $lowStockComponents,
            'recent_transactions' => [] // Would get from stock transaction model
        ];
    }

    /**
     * Get accounts-specific dashboard data
     */
    private function getAccountsDashboardData(ComponentModel $componentModel): array
    {
        return [
            'total_stock_value' => $componentModel->getTotalStockValue(),
            'monthly_stock_movement' => $componentModel->getStockMovementReport(date('Y-m-01'), date('Y-m-d')),
            'cost_analysis' => $this->getCostAnalysis()
        ];
    }

    /**
     * Calculate production efficiency for today
     */
    private function calculateProductionEfficiency(): float
    {
        $processRunModel = new WorkOrderProcessRunModel();
        $efficiency = $processRunModel->getProductionEfficiency(date('Y-m-d'), date('Y-m-d'));
        
        if (empty($efficiency)) {
            return 0.0;
        }

        // Calculate overall efficiency based on time and yield
        $totalEfficiency = 0;
        $count = 0;

        foreach ($efficiency as $process) {
            if ($process['avg_standard_time'] > 0 && $process['avg_actual_time'] > 0) {
                $timeEfficiency = ($process['avg_standard_time'] / $process['avg_actual_time']) * 100;
                $yieldEfficiency = $process['yield_percentage'];
                $overallEfficiency = ($timeEfficiency + $yieldEfficiency) / 2;
                
                $totalEfficiency += $overallEfficiency;
                $count++;
            }
        }

        return $count > 0 ? round($totalEfficiency / $count, 2) : 0.0;
    }

    /**
     * Get cost analysis data
     */
    private function getCostAnalysis(): array
    {
        // This would typically involve complex calculations
        // For now, return placeholder data
        return [
            'material_cost_this_month' => 0,
            'labor_cost_this_month' => 0,
            'overhead_cost_this_month' => 0,
            'total_cost_this_month' => 0
        ];
    }

    /**
     * Get dashboard widget data via AJAX
     */
    public function getWidgetData()
    {
        $this->requireAuth();

        $widget = $this->request->getGet('widget');
        $data = [];

        switch ($widget) {
            case 'work_orders_chart':
                $data = $this->getWorkOrdersChartData();
                break;
            case 'production_status':
                $data = $this->getProductionStatusData();
                break;
            case 'qc_trends':
                $data = $this->getQcTrendsData();
                break;
            case 'stock_alerts':
                $data = $this->getStockAlertsData();
                break;
            default:
                $data = ['error' => 'Invalid widget requested'];
        }

        return $this->jsonResponse($data);
    }

    /**
     * Get work orders chart data
     */
    private function getWorkOrdersChartData(): array
    {
        $workOrderModel = new WorkOrderModel();
        
        $chartData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Work Orders',
                    'data' => [],
                    'backgroundColor' => ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d']
                ]
            ]
        ];

        $statuses = ['planned', 'in_progress', 'completed', 'on_hold', 'cancelled'];
        foreach ($statuses as $status) {
            $count = $workOrderModel->where('status', $status)->countAllResults();
            $chartData['labels'][] = ucfirst(str_replace('_', ' ', $status));
            $chartData['datasets'][0]['data'][] = $count;
        }

        return $chartData;
    }

    /**
     * Get production status data
     */
    private function getProductionStatusData(): array
    {
        $processRunModel = new WorkOrderProcessRunModel();
        
        return [
            'pending' => $processRunModel->where('status', 'pending')->countAllResults(),
            'in_progress' => $processRunModel->where('status', 'in_progress')->countAllResults(),
            'completed' => $processRunModel->where('status', 'completed')->countAllResults(),
            'on_hold' => $processRunModel->where('status', 'on_hold')->countAllResults()
        ];
    }

    /**
     * Get QC trends data
     */
    private function getQcTrendsData(): array
    {
        $qcModel = new QcRecordModel();
        
        $trendData = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Pass Rate %',
                    'data' => [],
                    'borderColor' => '#28a745',
                    'backgroundColor' => 'rgba(40, 167, 69, 0.1)'
                ]
            ]
        ];

        // Get last 7 days data
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $stats = $qcModel->getQcStatistics($date, $date);
            
            $trendData['labels'][] = date('M j', strtotime($date));
            $trendData['datasets'][0]['data'][] = $stats['pass_rate'];
        }

        return $trendData;
    }

    /**
     * Get stock alerts data
     */
    private function getStockAlertsData(): array
    {
        $componentModel = new ComponentModel();
        $lowStockComponents = $componentModel->getLowStockComponents();
        
        $alerts = [];
        foreach ($lowStockComponents as $component) {
            $alerts[] = [
                'component' => $component['name'],
                'current_stock' => $component['current_stock'],
                'minimum_stock' => $component['minimum_stock'],
                'urgency' => $component['current_stock'] <= ($component['minimum_stock'] * 0.5) ? 'critical' : 'warning'
            ];
        }

        return $alerts;
    }
}
