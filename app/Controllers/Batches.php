<?php

namespace App\Controllers;

use App\Models\ProcessBatchModel;
use App\Models\WorkOrderModel;
use App\Models\ProductModel;
use App\Models\ProcessModel;
use App\Models\ProcessBatchLogModel;
use CodeIgniter\Controller;

class Batches extends BaseController
{
    protected $batchModel;
    protected $workOrderModel;
    protected $productModel;
    protected $processModel;
    protected $logModel;

    public function __construct()
    {
        $this->batchModel = new ProcessBatchModel();
        $this->workOrderModel = new WorkOrderModel();
        $this->productModel = new ProductModel();
        $this->processModel = new ProcessModel();
        $this->logModel = new ProcessBatchLogModel();
    }

    /**
     * Display comprehensive batch list with full-page view
     */
    public function index()
    {
    // Batches are managed under Work Orders (product -> process -> batches -> logs)
    // Redirect to Work Orders listing to keep batches under their parent Work Order.
    return redirect()->to(base_url('/work-orders'));
    }

    /**
     * Display detailed batch view with logs
     */
    public function show($id)
    {
        // Check authentication
        if (!session()->has('user_id')) {
            return redirect()->to('/login');
        }

        // Get batch with related data
        $batch = $this->batchModel->select('
            process_batches.*,
            work_orders.id as work_order_id,
            work_orders.wo_number as work_order_number,
            products.name as product_name,
            products.code as product_code,
            processes.name as process_name
        ')
        ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id', 'left')
        ->join('work_orders', 'work_orders.id = work_order_items.work_order_id', 'left')
        ->join('products', 'products.id = work_order_items.product_id', 'left')
        ->join('processes', 'processes.id = process_batches.process_id', 'left')
        ->find($id);

        if (!$batch) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Batch not found');
        }

        // Get batch logs with employee information
        $logs = $this->logModel->select('
            process_batch_logs.*,
            employees.name as employee_name,
            employees.employee_code
        ')
        ->join('employees', 'employees.id = process_batch_logs.employee_id', 'left')
        ->where('batch_id', $id)
        ->orderBy('created_at', 'DESC')
        ->findAll();

        $data = [
            'title' => 'Batch Details - ' . $batch['batch_code'],
            'batch' => $batch,
            'logs' => $logs
        ];

        return view('batches/show', $data);
    }

    /**
     * Export batch list as PDF
     */
    public function export()
    {
        // Check authentication
        if (!session()->has('user_id')) {
            return redirect()->to('/login');
        }

        // Get same data as index but without pagination
        $search = $this->request->getGet('search');
        $statusFilter = $this->request->getGet('status');
        $dateFilter = $this->request->getGet('date');
        $workOrderFilter = $this->request->getGet('work_order');

        $builder = $this->batchModel->select('
            process_batches.*,
            work_orders.wo_number as work_order_number,
            products.name as product_name,
            products.code as product_code,
            processes.name as process_name,
            COUNT(process_batch_logs.id) as log_count
        ')
        ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id', 'left')
        ->join('work_orders', 'work_orders.id = work_order_items.work_order_id', 'left')
        ->join('products', 'products.id = work_order_items.product_id', 'left')
        ->join('processes', 'processes.id = process_batches.process_id', 'left')
        ->join('process_batch_logs', 'process_batch_logs.batch_id = process_batches.id', 'left')
        ->groupBy('process_batches.id, work_orders.wo_number, products.name, products.code, processes.name');

        // Apply same filters as index
        if ($search) {
            $builder->groupStart()
                ->like('process_batches.batch_code', $search)
                ->orLike('process_batches.notes', $search)
                ->orLike('products.name', $search)
                ->orLike('processes.name', $search)
                ->groupEnd();
        }

        if ($statusFilter) {
            $builder->where('process_batches.status', $statusFilter);
        }

        if ($workOrderFilter) {
            $builder->where('work_orders.id', $workOrderFilter);
        }

        if ($dateFilter) {
            $today = date('Y-m-d');
            switch ($dateFilter) {
                case 'today':
                    $builder->where('DATE(process_batches.start_date)', $today);
                    break;
                case 'week':
                    $builder->where('process_batches.start_date >=', date('Y-m-d', strtotime('-7 days')));
                    break;
                case 'month':
                    $builder->where('process_batches.start_date >=', date('Y-m-01'));
                    break;
            }
        }

        $builder->orderBy('process_batches.created_at', 'DESC');
        $batches = $builder->findAll();

        // Generate PDF
        $html = $this->generateBatchPDF($batches);
        
        // Set headers for PDF download
        $this->response->setHeader('Content-Type', 'application/pdf');
        $this->response->setHeader('Content-Disposition', 'attachment; filename="batch_report_' . date('Y-m-d') . '.pdf"');
        
        return $html; // For now return HTML, will implement proper PDF later
    }

    /**
     * AJAX endpoint to get batch details for modals
     */
    public function ajaxGetBatchDetails($id)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        $batch = $this->batchModel->find($id);
        
        if (!$batch) {
            return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);
        }

        return $this->response->setJSON(['success' => true, 'batch' => $batch]);
    }

    /**
     * AJAX endpoint to update batch
     */
    public function ajaxUpdateBatch($id)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid request']);
        }

        $json = $this->request->getJSON(true);
        
        if (empty($json)) {
            return $this->response->setJSON(['success' => false, 'message' => 'No data provided']);
        }

        // Prepare update data
        $updateData = [];
        if (isset($json['status'])) $updateData['status'] = $json['status'];
        if (isset($json['actual_quantity'])) $updateData['quantity_completed'] = $json['actual_quantity'];
        if (isset($json['planned_quantity'])) $updateData['quantity'] = $json['planned_quantity'];
        if (isset($json['completion_date']) && !empty($json['completion_date'])) {
            $updateData['completed_at'] = $json['completion_date'];
        }
        if (isset($json['notes'])) $updateData['notes'] = $json['notes'];
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        try {
            $this->batchModel->update($id, $updateData);
            
            // Get updated batch
            $batch = $this->batchModel->find($id);
            
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Batch updated successfully',
                'batch' => $batch
            ]);
            
        } catch (\Exception $e) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Error updating batch: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Backwards-compatible create endpoint used by Work Order UI (if it posts to /batches/create)
     */
    public function create()
    {
        // Accept JSON
        $json = $this->request->getJSON(true) ?: [];
        $post = $this->request->getPost() ?: $json;

        $workOrderItemId = (int) ($post['work_order_item_id'] ?? $post['work_order_item'] ?? 0);
        $processId = (int) ($post['process_id'] ?? 0);
        $plannedQty = (int) ($post['planned_quantity'] ?? $post['planned_qty'] ?? 0);

        if (!$workOrderItemId || !$processId || $plannedQty <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid input']);
        }

        $batchCode = $post['batch_code'] ?? ('BATCH-' . time() . '-' . rand(100,999));

        $data = [
            'work_order_item_id' => $workOrderItemId,
            'process_id' => $processId,
            'batch_code' => $batchCode,
            'planned_qty' => $plannedQty,
            'status' => $post['status'] ?? 'open',
            'created_by' => $this->currentUser['id'] ?? 1
        ];

        if ($this->batchModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'batch_id' => $this->batchModel->getInsertID(), 'batch_code' => $batchCode, 'planned_quantity' => $plannedQty]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to create batch']);
    }

    /**
     * Get batch statistics for dashboard
     */
    private function getBatchStatistics()
    {
        $stats = [];
        
        // Total batches
        $stats['total_batches'] = $this->batchModel->countAll();
        
        // Status counts
        $stats['planned'] = $this->batchModel->where('status', 'planned')->countAllResults(false);
        $stats['in_progress'] = $this->batchModel->where('status', 'in_progress')->countAllResults(false);
        $stats['completed'] = $this->batchModel->where('status', 'completed')->countAllResults(false);
        $stats['on_hold'] = $this->batchModel->where('status', 'on_hold')->countAllResults(false);
        
        // Total planned quantity
        $result = $this->batchModel->select('SUM(quantity) as total_planned')
            ->get()->getRowArray();
        $stats['total_planned_qty'] = $result['total_planned'] ?? 0;
        
        // Total actual quantity
        $result = $this->batchModel->select('SUM(quantity_completed) as total_actual')
            ->get()->getRowArray();
        $stats['total_actual_qty'] = $result['total_actual'] ?? 0;
        
        return $stats;
    }

    /**
     * Generate HTML for PDF export
     */
    private function generateBatchPDF($batches)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <title>Batch Report - ' . date('Y-m-d') . '</title>
            <style>
                body { font-family: Arial, sans-serif; margin: 20px; }
                .header { text-align: center; margin-bottom: 30px; }
                .stats { display: flex; justify-content: space-around; margin-bottom: 30px; }
                .stat-box { text-align: center; padding: 15px; border: 1px solid #ddd; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f2f2f2; }
                .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; }
                .status-planned { background-color: #6c757d; color: white; }
                .status-in-progress { background-color: #007bff; color: white; }
                .status-completed { background-color: #28a745; color: white; }
                .status-on-hold { background-color: #ffc107; color: black; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Production Batch Report</h1>
                <p>Generated on: ' . date('Y-m-d H:i:s') . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Batch Code</th>
                        <th>Work Order</th>
                        <th>Process</th>
                        <th>Product</th>
                        <th>Status</th>
                        <th>Planned Qty</th>
                        <th>Actual Qty</th>
                        <th>Start Date</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($batches as $batch) {
            $statusClass = 'status-' . str_replace('_', '-', $batch['status']);
            $statusLabel = ucwords(str_replace('_', ' ', $batch['status']));
            
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($batch['batch_number']) . '</td>
                    <td>WO-' . str_pad($batch['work_order_id'] ?? 0, 4, '0', STR_PAD_LEFT) . '</td>
                    <td>' . htmlspecialchars($batch['process_name'] ?? 'N/A') . '</td>
                    <td>' . htmlspecialchars($batch['product_name'] ?? 'N/A') . '</td>
                    <td><span class="status-badge ' . $statusClass . '">' . $statusLabel . '</span></td>
                    <td>' . number_format($batch['quantity']) . '</td>
                    <td>' . ($batch['quantity_completed'] ? number_format($batch['quantity_completed']) : '-') . '</td>
                    <td>' . date('Y-m-d H:i', strtotime($batch['started_at'])) . '</td>
                    <td>' . htmlspecialchars(substr($batch['notes'] ?? '', 0, 50)) . '</td>
                </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <div style="margin-top: 30px; text-align: center; font-size: 12px; color: #666;">
                <p>Production Management System - Batch Report</p>
            </div>
        </body>
        </html>';
        
        return $html;
    }

    /**
     * Delete a batch
     */
    public function delete($id = null)
    {
        // Check authentication
        if (!session()->has('user_id')) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Authentication required'
            ]);
        }

        if (!$id) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Batch ID is required'
            ]);
        }

        try {
            // Check if batch exists
            $batch = $this->batchModel->find($id);
            if (!$batch) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Batch not found'
                ]);
            }

            // Check if batch can be deleted (only planned or on_hold batches)
            if (!in_array($batch['status'], ['planned', 'on_hold'])) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Cannot delete batch in current status. Only planned or on-hold batches can be deleted.'
                ]);
            }

            $db = \Config\Database::connect();
            $db->transStart();

            // Delete batch logs first
            $this->logModel->where('batch_id', $id)->delete();

            // Delete the batch
            $deleted = $this->batchModel->delete($id);

            $db->transComplete();

            if ($db->transStatus() === false || !$deleted) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Failed to delete batch'
                ]);
            }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'Batch deleted successfully'
            ]);

        } catch (\Exception $e) {
            log_message('error', 'Error deleting batch: ' . $e->getMessage());
            return $this->response->setJSON([
                'success' => false,
                'message' => 'An error occurred while deleting the batch'
            ]);
        }
    }
}
