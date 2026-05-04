<?php

namespace App\Controllers;

use App\Models\WorkOrderModel;
use App\Models\ProductModel;
use App\Models\ComponentModel;
use App\Models\VendorModel;
use App\Models\QcRecordModel;
use App\Models\WorkOrderProcessRunModel;
use App\Models\ComponentUsageModel;
use App\Models\ForecastModel;

class Reports extends BaseController
{
    /**
     * Reports dashboard
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $data = $this->setPageData([
            'page_title' => 'Reports & Analytics',
            'available_reports' => $this->getAvailableReports()
        ]);

        return view('reports/index', $data);
    }

    /**
     * Production summary report
     */
    public function productionSummary()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');
        $productId = $this->request->getGet('product_id');

        $workOrderModel = new WorkOrderModel();
        $processRunModel = new WorkOrderProcessRunModel();

        // Get production statistics
        $productionStats = $workOrderModel->getProductionStatistics($dateFrom, $dateTo, $productId);
        
        // Get efficiency data
        $efficiencyData = $processRunModel->getProductionEfficiency($dateFrom, $dateTo);
        
        // Get work order trends
        $trendData = $workOrderModel->getProductionTrends($dateFrom, $dateTo);

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Production Summary Report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'selected_product' => $productId,
            'products' => $products,
            'production_stats' => $productionStats,
            'efficiency_data' => $efficiencyData,
            'trend_data' => $trendData
        ]);

        return view('reports/production_summary', $data);
    }

    /**
     * Quality control report
     */
    public function qualityControl()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');
        $productId = $this->request->getGet('product_id');

        $qcModel = new QcRecordModel();

        // Get QC statistics
        $qcStats = $qcModel->getQcStatistics($dateFrom, $dateTo, $productId);
        
        // Get defect analysis
        $defectStats = $qcModel->getDefectStatistics($dateFrom, $dateTo, $productId);
        
        // Get QC trends
        $qcTrends = $qcModel->getDailyQcTrends($dateFrom, $dateTo);
        
        // Get inspector performance
        $inspectorStats = $qcModel->getInspectorStatistics($dateFrom, $dateTo);

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Quality Control Report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'selected_product' => $productId,
            'products' => $products,
            'qc_stats' => $qcStats,
            'defect_stats' => $defectStats,
            'qc_trends' => $qcTrends,
            'inspector_stats' => $inspectorStats
        ]);

        return view('reports/quality_control', $data);
    }

    /**
     * Inventory report
     */
    public function inventory()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $category = $this->request->getGet('category');
        $vendorId = $this->request->getGet('vendor_id');
        $stockFilter = $this->request->getGet('stock_filter'); // all, low_stock, overstock

        $componentModel = new ComponentModel();

        // Get inventory summary
        $inventorySummary = $componentModel->getInventorySummary($category, $vendorId);
        
        // Get stock analysis
        $stockAnalysis = $componentModel->getStockAnalysis($stockFilter);
        
        // Get ABC analysis
        $abcAnalysis = $componentModel->getAbcAnalysis();
        
        // Get stock movement
        $stockMovement = $componentModel->getStockMovementReport(date('Y-m-01'), date('Y-m-d'));

        $categories = $componentModel->getComponentCategories();
        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Inventory Report',
            'selected_category' => $category,
            'selected_vendor' => $vendorId,
            'selected_stock_filter' => $stockFilter,
            'categories' => $categories,
            'vendors' => $vendors,
            'inventory_summary' => $inventorySummary,
            'stock_analysis' => $stockAnalysis,
            'abc_analysis' => $abcAnalysis,
            'stock_movement' => $stockMovement
        ]);

        return view('reports/inventory', $data);
    }

    /**
     * Cost analysis report
     */
    public function costAnalysis()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');
        $productId = $this->request->getGet('product_id');

        $workOrderModel = new WorkOrderModel();
        $componentUsageModel = new ComponentUsageModel();

        // Get cost breakdown
        $costBreakdown = $this->getCostBreakdown($dateFrom, $dateTo, $productId);
        
        // Get material costs
        $materialCosts = $componentUsageModel->getMaterialCosts($dateFrom, $dateTo, $productId);
        
        // Get labor costs
        $laborCosts = $this->getLaborCosts($dateFrom, $dateTo, $productId);
        
        // Get cost trends
        $costTrends = $this->getCostTrends($dateFrom, $dateTo);

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Cost Analysis Report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'selected_product' => $productId,
            'products' => $products,
            'cost_breakdown' => $costBreakdown,
            'material_costs' => $materialCosts,
            'labor_costs' => $laborCosts,
            'cost_trends' => $costTrends
        ]);

        return view('reports/cost_analysis', $data);
    }

    /**
     * Vendor performance report
     */
    public function vendorPerformance()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');
        $vendorId = $this->request->getGet('vendor_id');

        $vendorModel = new VendorModel();

        // Get vendor performance metrics
        $vendorPerformance = $vendorModel->getVendorPerformanceReport($dateFrom, $dateTo, $vendorId);
        
        // Get delivery performance
        $deliveryPerformance = $this->getDeliveryPerformance($dateFrom, $dateTo, $vendorId);
        
        // Get quality performance
        $qualityPerformance = $this->getVendorQualityPerformance($dateFrom, $dateTo, $vendorId);

        $vendors = $vendorModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Vendor Performance Report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'selected_vendor' => $vendorId,
            'vendors' => $vendors,
            'vendor_performance' => $vendorPerformance,
            'delivery_performance' => $deliveryPerformance,
            'quality_performance' => $qualityPerformance
        ]);

        return view('reports/vendor_performance', $data);
    }

    /**
     * Work order analysis report
     */
    public function workOrderAnalysis()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');
        $status = $this->request->getGet('status');
        $priority = $this->request->getGet('priority');

        $workOrderModel = new WorkOrderModel();

        // Get work order analysis
        $woAnalysis = $workOrderModel->getWorkOrderAnalysis($dateFrom, $dateTo, $status, $priority);
        
        // Get completion analysis
        $completionAnalysis = $workOrderModel->getCompletionAnalysis($dateFrom, $dateTo);
        
        // Get bottleneck analysis
        $bottleneckAnalysis = $this->getBottleneckAnalysis($dateFrom, $dateTo);
        
        // Get on-time delivery performance
        $otdPerformance = $workOrderModel->getOtdPerformance($dateFrom, $dateTo);

        $data = $this->setPageData([
            'page_title' => 'Work Order Analysis Report',
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'selected_status' => $status,
            'selected_priority' => $priority,
            'wo_analysis' => $woAnalysis,
            'completion_analysis' => $completionAnalysis,
            'bottleneck_analysis' => $bottleneckAnalysis,
            'otd_performance' => $otdPerformance
        ]);

        return view('reports/work_order_analysis', $data);
    }

    /**
     * Custom report builder
     */
    public function customBuilder()
    {
        $this->requireAuth();
        $this->requirePermission('reports.create');

        $data = $this->setPageData([
            'page_title' => 'Custom Report Builder',
            'available_tables' => $this->getAvailableTables(),
            'available_fields' => $this->getAvailableFields()
        ]);

        return view('reports/custom_builder', $data);
    }

    /**
     * Generate custom report
     */
    public function generateCustom()
    {
        $this->requireAuth();
        $this->requirePermission('reports.create');

        $reportConfig = $this->request->getJSON(true);
        
        if (empty($reportConfig)) {
            return $this->jsonResponse(['success' => false, 'message' => 'No report configuration provided'], 400);
        }

        try {
            $reportData = $this->buildCustomReport($reportConfig);
            
            return $this->jsonResponse([
                'success' => true,
                'data' => $reportData,
                'message' => 'Report generated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to generate report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report to PDF
     */
    public function exportPdf($reportType = null)
    {
        $this->requireAuth();
        $this->requirePermission('reports.export');

        // Get report data based on type and parameters
        $reportData = $this->getReportDataForExport($reportType);
        
        // Generate PDF using TCPDF or similar library
        // This is a placeholder implementation
        
        $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Generate PDF content
        echo "PDF Report would be generated here";
        exit;
    }

    /**
     * Export report to Excel
     */
    public function exportExcel($reportType = null)
    {
        $this->requireAuth();
        $this->requirePermission('reports.export');

        // Get report data based on type and parameters
        $reportData = $this->getReportDataForExport($reportType);
        
        $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.xlsx';
        
        // Set headers for Excel download
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Generate Excel content (would use PhpSpreadsheet)
        echo "Excel Report would be generated here";
        exit;
    }

    /**
     * Dashboard analytics data
     */
    public function dashboardData()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $dateFrom = date('Y-m-01'); // This month
        $dateTo = date('Y-m-d');

        $workOrderModel = new WorkOrderModel();
        $qcModel = new QcRecordModel();
        $componentModel = new ComponentModel();

        $data = [
            'production_summary' => $workOrderModel->getProductionStatistics($dateFrom, $dateTo),
            'quality_summary' => $qcModel->getQcStatistics($dateFrom, $dateTo),
            'inventory_summary' => $componentModel->getInventorySummary(),
            'kpi_trends' => $this->getKpiTrends($dateFrom, $dateTo)
        ];

        return $this->jsonResponse($data);
    }

    /**
     * Deterministic forecast summary (JSON): bottlenecks, burn-up, and at-risk work orders.
     * Uses only actuals; no schema changes required. Parameters (optional): days, horizon, top.
     */
    public function forecastSummary()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $days = (int) ($this->request->getGet('days') ?? 14);
        $burnDays = (int) ($this->request->getGet('burn_days') ?? 30);
        $riskHorizon = (int) ($this->request->getGet('risk_horizon') ?? 7);
        $top = (int) ($this->request->getGet('top') ?? 5);

        try {
            $forecast = new ForecastModel();

            $bottlenecks = $forecast->getBottlenecks($days, $top);
            $burnup = $forecast->getBurnupSeries($burnDays);
            $risks = $forecast->getAtRiskWorkOrders($riskHorizon);

            return $this->jsonResponse([
                'success' => true,
                'summary' => [
                    'bottlenecks' => $bottlenecks,
                    'burnup' => $burnup,
                    'risks' => $risks,
                ],
                'params' => [
                    'throughput_window_days' => $days,
                    'burnup_days' => $burnDays,
                    'risk_horizon_days' => $riskHorizon,
                    'top' => $top,
                ]
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'forecastSummary failed: {message}', ['message' => $e->getMessage()]);
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to build forecast summary'
            ], 500);
        }
    }

    /**
     * Bottlenecks only (JSON)
     */
    public function forecastBottlenecks()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $days = (int) ($this->request->getGet('days') ?? 14);
        $top = (int) ($this->request->getGet('top') ?? 5);

        try {
            $forecast = new ForecastModel();
            $bottlenecks = $forecast->getBottlenecks($days, $top);
            return $this->jsonResponse(['success' => true, 'bottlenecks' => $bottlenecks]);
        } catch (\Throwable $e) {
            log_message('error', 'forecastBottlenecks failed: {message}', ['message' => $e->getMessage()]);
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to fetch bottlenecks'], 500);
        }
    }

    /**
     * Burn-up only (JSON)
     */
    public function forecastBurnup()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $burnDays = (int) ($this->request->getGet('burn_days') ?? 30);

        try {
            $forecast = new ForecastModel();
            $burnup = $forecast->getBurnupSeries($burnDays);
            return $this->jsonResponse(['success' => true, 'burnup' => $burnup]);
        } catch (\Throwable $e) {
            log_message('error', 'forecastBurnup failed: {message}', ['message' => $e->getMessage()]);
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to fetch burn-up series'], 500);
        }
    }

    /**
     * At-risk work orders only (JSON)
     */
    public function forecastRisks()
    {
        $this->requireAuth();
        $this->requirePermission('reports.view');

        $riskHorizon = (int) ($this->request->getGet('risk_horizon') ?? 7);

        try {
            $forecast = new ForecastModel();
            $risks = $forecast->getAtRiskWorkOrders($riskHorizon);
            return $this->jsonResponse(['success' => true, 'risks' => $risks]);
        } catch (\Throwable $e) {
            log_message('error', 'forecastRisks failed: {message}', ['message' => $e->getMessage()]);
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to fetch at-risk work orders'], 500);
        }
    }

    /**
     * Get available reports
     */
    private function getAvailableReports(): array
    {
        return [
            [
                'name' => 'Production Summary',
                'description' => 'Comprehensive production metrics and trends',
                'url' => '/reports/production-summary',
                'permissions' => ['reports.view']
            ],
            [
                'name' => 'Quality Control',
                'description' => 'QC statistics, defect analysis, and inspector performance',
                'url' => '/reports/quality-control',
                'permissions' => ['reports.view', 'qc.view']
            ],
            [
                'name' => 'Inventory Report',
                'description' => 'Stock levels, valuation, and movement analysis',
                'url' => '/reports/inventory',
                'permissions' => ['reports.view', 'components.view']
            ],
            [
                'name' => 'Cost Analysis',
                'description' => 'Material, labor, and overhead cost breakdown',
                'url' => '/reports/cost-analysis',
                'permissions' => ['reports.view', 'costs.view']
            ],
            [
                'name' => 'Vendor Performance',
                'description' => 'Vendor delivery and quality performance metrics',
                'url' => '/reports/vendor-performance',
                'permissions' => ['reports.view', 'vendors.view']
            ],
            [
                'name' => 'Work Order Analysis',
                'description' => 'Work order completion and bottleneck analysis',
                'url' => '/reports/work-order-analysis',
                'permissions' => ['reports.view', 'work_orders.view']
            ]
        ];
    }

    /**
     * Get cost breakdown
     */
    private function getCostBreakdown($dateFrom, $dateTo, $productId = null): array
    {
        // Placeholder implementation
        return [
            'material_costs' => 0,
            'labor_costs' => 0,
            'overhead_costs' => 0,
            'total_costs' => 0
        ];
    }

    /**
     * Get labor costs
     */
    private function getLaborCosts($dateFrom, $dateTo, $productId = null): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Get cost trends
     */
    private function getCostTrends($dateFrom, $dateTo): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Get delivery performance
     */
    private function getDeliveryPerformance($dateFrom, $dateTo, $vendorId = null): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Get vendor quality performance
     */
    private function getVendorQualityPerformance($dateFrom, $dateTo, $vendorId = null): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Get bottleneck analysis
     */
    private function getBottleneckAnalysis($dateFrom, $dateTo): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Get available tables for custom reports
     */
    private function getAvailableTables(): array
    {
        return [
            'work_orders' => 'Work Orders',
            'products' => 'Products',
            'components' => 'Components',
            'vendors' => 'Vendors',
            'qc_records' => 'QC Records',
            'processes' => 'Processes',
            'work_order_process_runs' => 'Process Runs',
            'component_usage' => 'Component Usage'
        ];
    }

    /**
     * Get available fields for custom reports
     */
    private function getAvailableFields(): array
    {
        return [
            'work_orders' => [
                'wo_number' => 'WO Number',
                'status' => 'Status',
                'priority' => 'Priority',
                'quantity_to_produce' => 'Quantity',
                'created_at' => 'Created Date'
            ],
            'products' => [
                'name' => 'Product Name',
                'sku' => 'SKU',
                'category' => 'Category',
                'standard_cost' => 'Standard Cost'
            ]
            // Add more fields as needed
        ];
    }

    /**
     * Build custom report
     */
    private function buildCustomReport($config): array
    {
        // Placeholder implementation for custom report builder
        return [];
    }

    /**
     * Get report data for export
     */
    private function getReportDataForExport($reportType): array
    {
        // Placeholder implementation
        return [];
    }

    /**
     * Get KPI trends
     */
    private function getKpiTrends($dateFrom, $dateTo): array
    {
        // Placeholder implementation
        return [];
    }
}
