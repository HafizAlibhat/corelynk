<?php

namespace App\Controllers;

use App\Models\QcRecordModel;
use App\Models\WorkOrderProcessRunModel;
use App\Models\WorkOrderModel;
use App\Models\ProductModel;

class QualityControl extends BaseController
{
    protected $qcModel;

    public function __construct()
    {
        $this->qcModel = new QcRecordModel();
    }

    /**
     * Display QC dashboard
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $processRunModel = new WorkOrderProcessRunModel();
        
        // Get pending inspections
        $pendingInspections = $processRunModel->getRunsRequiringQc();
        
        // Get recent QC records
        $recentRecords = $this->qcModel->getQcRecordsWithDetails(10);
        
        // Get QC statistics for today
        $todayStats = $this->qcModel->getQcStatistics(date('Y-m-d'), date('Y-m-d'));
        
        // Get weekly statistics
        $weekStart = date('Y-m-d', strtotime('-6 days'));
        $weekStats = $this->qcModel->getQcStatistics($weekStart, date('Y-m-d'));
        
        // Get failed records requiring action
        $failedRecords = $this->qcModel->getFailedQcRecords();

        $data = $this->setPageData([
            'page_title' => 'Quality Control Dashboard',
            'pending_inspections' => $pendingInspections,
            'recent_records' => $recentRecords,
            'today_stats' => $todayStats,
            'week_stats' => $weekStats,
            'failed_records' => $failedRecords,
            'can_inspect' => $this->hasPermission('qc.inspect'),
            'can_approve' => $this->hasPermission('qc.approve')
        ]);

        return view('qc/index', $data);
    }

    /**
     * Display QC records list
     */
    public function records()
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $searchTerm = $this->request->getGet('search');
        $statusFilter = $this->request->getGet('status');
        $productFilter = $this->request->getGet('product');
        $inspectorFilter = $this->request->getGet('inspector');
        $dateFrom = $this->request->getGet('date_from');
        $dateTo = $this->request->getGet('date_to');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $records = $this->qcModel->getQcRecordsWithFilters(
            $searchTerm, $statusFilter, $productFilter, $inspectorFilter, 
            $dateFrom, $dateTo, $perPage
        );

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'QC Records',
            'records' => $records,
            'products' => $products,
            'current_search' => $searchTerm,
            'current_status' => $statusFilter,
            'current_product' => $productFilter,
            'current_inspector' => $inspectorFilter,
            'current_date_from' => $dateFrom,
            'current_date_to' => $dateTo,
            'per_page' => $perPage,
            'can_inspect' => $this->hasPermission('qc.inspect'),
            'can_approve' => $this->hasPermission('qc.approve')
        ]);

        return view('qc/records', $data);
    }

    /**
     * Display single QC record details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $record = $this->qcModel->getQcRecordWithDetails($id);
        if (!$record) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('QC Record not found');
        }

        $data = $this->setPageData([
            'page_title' => 'QC Record Details - ' . $record['work_order_number'],
            'record' => $record,
            'can_edit' => $this->hasPermission('qc.edit'),
            'can_approve' => $this->hasPermission('qc.approve')
        ]);

        return view('qc/show', $data);
    }

    /**
     * Display pending inspections
     */
    public function pendingInspections()
    {
        $this->requireAuth();
        $this->requirePermission('qc.inspect');

        $processRunModel = new WorkOrderProcessRunModel();
        $pendingRuns = $processRunModel->getRunsRequiringQc();

        $data = $this->setPageData([
            'page_title' => 'Pending QC Inspections',
            'pending_runs' => $pendingRuns,
            'can_inspect' => $this->hasPermission('qc.inspect')
        ]);

        return view('qc/pending', $data);
    }

    /**
     * Display QC inspection form
     */
    public function inspect($processRunId = null)
    {
        $this->requireAuth();
        $this->requirePermission('qc.inspect');

        $processRunModel = new WorkOrderProcessRunModel();
        $processRun = $processRunModel->getProcessRunWithDetails($processRunId);

        if (!$processRun) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process run not found');
        }

        if ($processRun['status'] !== 'completed') {
            return redirect()->to('/qc/pending')
                           ->with('error', 'Can only inspect completed process runs.');
        }

        // Check if already inspected
        $existingRecord = $this->qcModel->where('process_run_id', $processRunId)->first();
        if ($existingRecord) {
            return redirect()->to('/qc/records/' . $existingRecord['id'])
                           ->with('info', 'This process run has already been inspected.');
        }

        // Get quality parameters for the product
        $productModel = new ProductModel();
        $product = $productModel->find($processRun['product_id']);
        $qualityParameters = [];
        
        if (!empty($product['quality_parameters'])) {
            $qualityParameters = json_decode($product['quality_parameters'], true) ?? [];
        }

        $data = $this->setPageData([
            'page_title' => 'QC Inspection - ' . $processRun['work_order_number'],
            'process_run' => $processRun,
            'quality_parameters' => $qualityParameters,
            'validation' => $this->validation
        ]);

        return view('qc/inspect', $data);
    }

    /**
     * Handle QC inspection submission
     */
    public function storeInspection($processRunId = null)
    {
        $this->requireAuth();
        $this->requirePermission('qc.inspect');

        $processRunModel = new WorkOrderProcessRunModel();
        $processRun = $processRunModel->find($processRunId);

        if (!$processRun) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process run not found');
        }

        // Check if already inspected
        $existingRecord = $this->qcModel->where('process_run_id', $processRunId)->first();
        if ($existingRecord) {
            return redirect()->to('/qc/records/' . $existingRecord['id'])
                           ->with('error', 'This process run has already been inspected.');
        }

        $data = [
            'process_run_id' => $processRunId,
            'work_order_id' => $processRun['work_order_id'],
            'inspector_id' => $this->currentUser['id'],
            'inspection_date' => date('Y-m-d H:i:s'),
            'quantity_inspected' => $this->request->getPost('quantity_inspected'),
            'quantity_passed' => $this->request->getPost('quantity_passed'),
            'quantity_failed' => $this->request->getPost('quantity_failed'),
            'quality_checklist' => $this->request->getPost('quality_checklist'),
            'defects_found' => $this->request->getPost('defects_found'),
            'corrective_actions' => $this->request->getPost('corrective_actions'),
            'remarks' => $this->request->getPost('remarks'),
            'status' => 'pending_approval',
            'created_by' => $this->currentUser['id']
        ];

        // Handle JSON fields
        if ($this->request->getPost('quality_checklist')) {
            $data['quality_checklist'] = json_encode($this->parseJsonField($this->request->getPost('quality_checklist')));
        }
        if ($this->request->getPost('defects_found')) {
            $data['defects_found'] = json_encode($this->parseJsonField($this->request->getPost('defects_found')));
        }
        if ($this->request->getPost('corrective_actions')) {
            $data['corrective_actions'] = json_encode($this->parseJsonField($this->request->getPost('corrective_actions')));
        }

        // Determine overall result
        $quantityInspected = (int) $data['quantity_inspected'];
        $quantityPassed = (int) $data['quantity_passed'];
        $quantityFailed = (int) $data['quantity_failed'];

        if ($quantityPassed + $quantityFailed != $quantityInspected) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Quantity passed + failed must equal quantity inspected.');
        }

        $data['result'] = ($quantityFailed == 0) ? 'pass' : 'fail';

        if ($this->qcModel->save($data)) {
            // Update process run with QC status
            $processRunModel->update($processRunId, [
                'qc_status' => $data['result'],
                'updated_by' => $this->currentUser['id']
            ]);

            return redirect()->to('/qc/records')->with('success', 'QC inspection recorded successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->qcModel->errors());
        }
    }

    /**
     * Approve/reject QC record
     */
    public function approve($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('qc.approve');

        $record = $this->qcModel->find($id);
        if (!$record) {
            return $this->jsonResponse(['success' => false, 'message' => 'QC Record not found'], 404);
        }

        if ($record['status'] !== 'pending_approval') {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Can only approve/reject records pending approval'
            ], 400);
        }

        $action = $this->request->getPost('action'); // 'approve' or 'reject'
        $remarks = $this->request->getPost('remarks');

        $updateData = [
            'status' => $action === 'approve' ? 'approved' : 'rejected',
            'approved_by' => $this->currentUser['id'],
            'approved_at' => date('Y-m-d H:i:s'),
            'approval_remarks' => $remarks,
            'updated_by' => $this->currentUser['id']
        ];

        if ($this->qcModel->update($id, $updateData)) {
            $message = $action === 'approve' ? 'QC record approved successfully' : 'QC record rejected successfully';
            return $this->jsonResponse(['success' => true, 'message' => $message]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update QC record'], 500);
        }
    }

    /**
     * QC reports
     */
    public function reports()
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $reportType = $this->request->getGet('type') ?? 'summary';
        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');

        $reportData = [];

        switch ($reportType) {
            case 'summary':
                $reportData = $this->getQcSummaryReport($dateFrom, $dateTo);
                break;
            case 'defects':
                $reportData = $this->getDefectsReport($dateFrom, $dateTo);
                break;
            case 'trends':
                $reportData = $this->getQcTrendsReport($dateFrom, $dateTo);
                break;
            case 'inspector':
                $reportData = $this->getInspectorReport($dateFrom, $dateTo);
                break;
        }

        $data = $this->setPageData([
            'page_title' => 'QC Reports',
            'report_type' => $reportType,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'report_data' => $reportData
        ]);

        return view('qc/reports', $data);
    }

    /**
     * Export QC records to CSV
     */
    public function exportCsv()
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');

        $records = $this->qcModel->getQcRecordsForExport($dateFrom, $dateTo);
        
        $filename = 'qc_records_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'Inspection Date', 'Work Order', 'Product', 'Process', 'Inspector',
            'Quantity Inspected', 'Quantity Passed', 'Quantity Failed', 
            'Result', 'Status', 'Remarks'
        ]);
        
        // CSV data
        foreach ($records as $record) {
            fputcsv($output, [
                $record['inspection_date'],
                $record['work_order_number'],
                $record['product_name'],
                $record['process_name'],
                $record['inspector_name'],
                $record['quantity_inspected'],
                $record['quantity_passed'],
                $record['quantity_failed'],
                $record['result'],
                $record['status'],
                $record['remarks']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get QC summary report
     */
    private function getQcSummaryReport($dateFrom, $dateTo): array
    {
        $stats = $this->qcModel->getQcStatistics($dateFrom, $dateTo);
        $defectStats = $this->qcModel->getDefectStatistics($dateFrom, $dateTo);
        $productStats = $this->qcModel->getProductQcStatistics($dateFrom, $dateTo);

        return [
            'overall_stats' => $stats,
            'defect_stats' => $defectStats,
            'product_stats' => $productStats
        ];
    }

    /**
     * Get defects report
     */
    private function getDefectsReport($dateFrom, $dateTo): array
    {
        return [
            'defect_categories' => $this->qcModel->getDefectsByCategory($dateFrom, $dateTo),
            'defect_trends' => $this->qcModel->getDefectTrends($dateFrom, $dateTo),
            'top_defects' => $this->qcModel->getTopDefects($dateFrom, $dateTo)
        ];
    }

    /**
     * Get QC trends report
     */
    private function getQcTrendsReport($dateFrom, $dateTo): array
    {
        return [
            'daily_trends' => $this->qcModel->getDailyQcTrends($dateFrom, $dateTo),
            'weekly_trends' => $this->qcModel->getWeeklyQcTrends($dateFrom, $dateTo),
            'pass_rate_trends' => $this->qcModel->getPassRateTrends($dateFrom, $dateTo)
        ];
    }

    /**
     * Get inspector performance report
     */
    private function getInspectorReport($dateFrom, $dateTo): array
    {
        return [
            'inspector_stats' => $this->qcModel->getInspectorStatistics($dateFrom, $dateTo),
            'inspector_efficiency' => $this->qcModel->getInspectorEfficiency($dateFrom, $dateTo)
        ];
    }

    /**
     * Generate QC certificate
     */
    public function certificate($recordId = null)
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $record = $this->qcModel->getQcRecordWithDetails($recordId);
        if (!$record) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('QC Record not found');
        }

        if ($record['result'] !== 'pass' || $record['status'] !== 'approved') {
            return redirect()->back()
                           ->with('error', 'Can only generate certificates for approved passed inspections.');
        }

        $data = $this->setPageData([
            'page_title' => 'QC Certificate',
            'record' => $record,
            'certificate_number' => $this->generateCertificateNumber($recordId)
        ]);

        return view('qc/certificate', $data);
    }

    /**
     * Get QC data for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();
        $this->requirePermission('qc.view');

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'statistics':
                $dateFrom = $this->request->getGet('date_from') ?? date('Y-m-d');
                $dateTo = $this->request->getGet('date_to') ?? date('Y-m-d');
                $stats = $this->qcModel->getQcStatistics($dateFrom, $dateTo);
                return $this->jsonResponse($stats);
                
            case 'pending_count':
                $processRunModel = new WorkOrderProcessRunModel();
                $count = count($processRunModel->getRunsRequiringQc());
                return $this->jsonResponse(['count' => $count]);
                
            case 'chart_data':
                $chartType = $this->request->getGet('chart_type');
                $chartData = $this->getChartData($chartType);
                return $this->jsonResponse($chartData);
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Get chart data for QC dashboard
     */
    private function getChartData($chartType): array
    {
        switch ($chartType) {
            case 'pass_rate_trend':
                return $this->qcModel->getPassRateTrends(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
            
            case 'defect_distribution':
                return $this->qcModel->getDefectsByCategory(date('Y-m-01'), date('Y-m-d'));
            
            case 'inspector_performance':
                return $this->qcModel->getInspectorStatistics(date('Y-m-01'), date('Y-m-d'));
            
            default:
                return [];
        }
    }

    /**
     * Generate certificate number
     */
    private function generateCertificateNumber($recordId): string
    {
        return 'QC-CERT-' . date('Y') . '-' . str_pad($recordId, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Parse JSON field from form input
     */
    private function parseJsonField($input): array
    {
        if (is_string($input)) {
            // Try to parse as JSON first
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // If not JSON, treat as key-value pairs or list
            $lines = explode("\n", $input);
            $result = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $result[trim($key)] = trim($value);
                } else {
                    $result[] = $line;
                }
            }
            return $result;
        }
        
        return is_array($input) ? $input : [];
    }
}
