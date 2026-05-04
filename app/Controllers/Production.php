<?php

namespace App\Controllers;

use App\Models\ProcessBatchModel;
use App\Models\ProcessBatchLogModel;
use App\Models\WorkOrderModel;
use App\Models\WorkOrderItemModel;
use App\Models\ProductProcessModel;
use App\Models\ProductModel;
use App\Models\ProcessModel;
use App\Models\EmployeeModel;

class Production extends BaseController
{
    protected $batchModel;
    protected $logModel;
    protected $workOrderModel;
    protected $workOrderItemModel;
    protected $productProcessModel;
    protected $productModel;
    protected $processModel;
    protected $employeeModel;
    protected $db;

    public function __construct()
    {
        $this->batchModel = new ProcessBatchModel();
        $this->logModel = new ProcessBatchLogModel();
        $this->workOrderModel = new WorkOrderModel();
        $this->workOrderItemModel = new WorkOrderItemModel();
        $this->productProcessModel = new ProductProcessModel();
        $this->productModel = new ProductModel();
        $this->processModel = new ProcessModel();
        $this->employeeModel = new EmployeeModel();
        $this->db = \Config\Database::connect();
    }

    public function logs()
    {
        if (!$this->checkPermission('production.view')) {
            return $this->response->setStatusCode(403)->setJSON(['error' => 'Insufficient permissions']);
        }

        $employees = [];
        try {
            $employees = $this->employeeModel->where('is_active', 1)->findAll() ?? [];
        } catch (\Exception $e) {
            log_message('error', 'Failed to load employees: ' . $e->getMessage());
        }

        $data = [
            'title' => 'Production Logs',
            'hierarchy' => $this->buildProductionHierarchy(),
            'employees' => $employees
        ];

        return view('production/logs', $data);
    }

    private function buildProductionHierarchy()
    {
        // Build a hierarchical structure from process_batches (work orders -> products -> processes -> batches)
        try {
            $batches = $this->batchModel->getBatchesWithDetails();
            if (empty($batches)) {
                return [];
            }

            $hier = [];
            foreach ($batches as $b) {
                $woId = $b['work_order_id'] ?? ($b['work_order_id'] ?? null);
                $woNumber = $b['wo_number'] ?? 'WO-UNKNOWN';
                $woCustomer = $b['customer_name'] ?? '';

                if (!isset($hier[$woId])) {
                    $hier[$woId] = [
                        'id' => $woId,
                        'wo_number' => $woNumber,
                        'status' => $b['wo_status'] ?? 'unknown',
                        'customer_name' => $woCustomer,
                        'products' => [],
                        'total_batches' => 0
                    ];
                }

                $productId = $b['product_id'] ?? null;
                if (!isset($hier[$woId]['products'][$productId])) {
                    $hier[$woId]['products'][$productId] = [
                        'id' => $productId,
                        'product_name' => $b['product_name'] ?? '',
                        'product_code' => $b['product_code'] ?? '',
                        'quantity' => $b['quantity_ordered'] ?? 0,
                        'processes' => []
                    ];
                }

                $procId = $b['process_id'] ?? null;
                if (!isset($hier[$woId]['products'][$productId]['processes'][$procId])) {
                    $hier[$woId]['products'][$productId]['processes'][$procId] = [
                        'id' => $procId,
                        'process_name' => $b['process_name'] ?? '',
                        'batches' => []
                    ];
                }

                // Add batch
                $batchData = [
                    'id' => $b['id'],
                    'batch_code' => $b['batch_code'] ?? '',
                    'planned_qty' => $b['planned_qty'] ?? 0,
                    'actual_qty' => $b['completed_qty'] ?? 0,
                    'status' => $b['status'] ?? 'unknown'
                ];

                $hier[$woId]['products'][$productId]['processes'][$procId]['batches'][] = $batchData;
                $hier[$woId]['total_batches'] += 1;
            }

            // Re-index products/processes arrays for view compatibility
            $out = [];
            foreach ($hier as $wo) {
                $products = [];
                foreach ($wo['products'] as $p) {
                    $procs = [];
                    foreach ($p['processes'] as $pr) {
                        $procs[] = $pr;
                    }
                    $p['processes'] = $procs;
                    $products[] = $p;
                }
                $wo['products'] = $products;
                $out[] = $wo;
            }

            return $out;
        } catch (\Exception $e) {
            log_message('error', 'Failed to build production hierarchy: ' . $e->getMessage());
            // Fallback to empty data so the view doesn't crash
            return [];
        }
    }

    private function checkPermission($permission)
    {
        return session()->get('user_id') !== null;
    }

    public function ajaxDeleteWorkOrder($woId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403, 'Forbidden');
        }

        try {
            if (empty($woId)) {
                $input = $this->request->getJSON(true) ?: [];
                $woId = $input['work_order_id'] ?? $input['id'] ?? null;
            }

            if (empty($woId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Work Order ID is required.']);
            }

            // Pre-delete diagnostics: log current counts/rows for troubleshooting
            try {
                $pre = $this->db->table('work_orders')->select('id, wo_number')->where('id', $woId)->get()->getRowArray();
                log_message('debug', "ajaxDeleteWorkOrder PRE: " . json_encode($pre));
            } catch (\Exception $e) {
                log_message('debug', "ajaxDeleteWorkOrder PRE error: " . $e->getMessage());
            }

            // Use explicit transaction and validate affected rows so we only return success when rows are removed
            $this->db->transStart();
            $this->db->table('process_batches pb')
                ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
                ->where('woi.work_order_id', $woId)
                ->delete();

            $batchesDeleted = $this->db->affectedRows();

            $this->db->table('work_order_items')->where('work_order_id', $woId)->delete();
            $itemsDeleted = $this->db->affectedRows();

            $this->db->table('work_orders')->where('id', $woId)->delete();
            $ordersDeleted = $this->db->affectedRows();

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                log_message('error', "Transaction failed while deleting work order {$woId}");
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete work order (transaction failed)']);
            }

            // If nothing was deleted at all, report failure so frontend doesn't show a false positive
            if (($batchesDeleted + $itemsDeleted + $ordersDeleted) === 0) {
                log_message('warning', "Delete work order {$woId} completed but 0 rows were affected (batches: {$batchesDeleted}, items: {$itemsDeleted}, orders: {$ordersDeleted})");
                return $this->response->setJSON(['success' => false, 'message' => 'No matching work order found to delete.']);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Work order deleted successfully']);
        } catch (\Exception $e) {
            log_message('error', 'Delete work order error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Server error occurred']);
        }
    }

    public function ajaxDeleteProduct($woItemId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403, 'Forbidden');
        }

        try {
            if (empty($woItemId)) {
                $input = $this->request->getJSON(true) ?: [];
                $woItemId = $input['wo_item_id'] ?? $input['product_id'] ?? $input['id'] ?? null;
            }

            if (empty($woItemId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Product (work order item) ID is required.']);
            }

            // Pre-delete diagnostics
            try {
                $pre = $this->db->table('work_order_items')->select('id, work_order_id, product_id')->where('id', $woItemId)->get()->getRowArray();
                log_message('debug', 'ajaxDeleteProduct PRE: ' . json_encode($pre));
            } catch (\Exception $e) {
                log_message('debug', 'ajaxDeleteProduct PRE error: ' . $e->getMessage());
            }

            // Wrap in transaction and verify affected rows
            $this->db->transStart();
            $this->batchModel->where('work_order_item_id', $woItemId)->delete();
            $batchesDeleted = $this->db->affectedRows();

            $woItemModel = new WorkOrderItemModel();
            $woItemModel->delete($woItemId);
            $itemsDeleted = $this->db->affectedRows();

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                log_message('error', "Transaction failed while deleting product (wo item) {$woItemId}");
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete product (transaction failed).']);
            }

            if (($batchesDeleted + $itemsDeleted) === 0) {
                log_message('warning', "Delete product {$woItemId} completed but 0 rows were affected (batches: {$batchesDeleted}, items: {$itemsDeleted})");
                return $this->response->setJSON(['success' => false, 'message' => 'No matching product/work order item found to delete.']);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Product deleted successfully.']);
        } catch (\Exception $e) {
            log_message('error', 'Exception while deleting product (WO Item ID) ' . $woItemId . ': ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An unexpected error occurred.']);
        }
    }

    public function ajaxDeleteProcess($productProcessId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403, 'Forbidden');
        }

        try {
            if (empty($productProcessId)) {
                $input = $this->request->getJSON(true) ?: [];
                $productProcessId = $input['product_process_id'] ?? $input['id'] ?? null;
            }

            if (empty($productProcessId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Process ID is required.']);
            }

            // Pre-delete diagnostics
            try {
                $pre = $this->db->table('product_processes')->select('id, product_id, process_id')->where('id', $productProcessId)->get()->getRowArray();
                log_message('debug', 'ajaxDeleteProcess PRE: ' . json_encode($pre));
            } catch (\Exception $e) {
                log_message('debug', 'ajaxDeleteProcess PRE error: ' . $e->getMessage());
            }

            // Transaction + affected rows verification
            $this->db->transStart();
            $this->batchModel->where('product_process_id', $productProcessId)->delete();
            $batchesDeleted = $this->db->affectedRows();

            $this->productProcessModel->delete($productProcessId);
            $processDeleted = $this->db->affectedRows();

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                log_message('error', "Transaction failed while deleting product process {$productProcessId}");
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete process (transaction failed).']);
            }

            if (($batchesDeleted + $processDeleted) === 0) {
                log_message('warning', "Delete process {$productProcessId} completed but 0 rows were affected (batches: {$batchesDeleted}, process: {$processDeleted})");
                return $this->response->setJSON(['success' => false, 'message' => 'No matching process found to delete.']);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Process and its batches deleted successfully.']);
        } catch (\Exception $e) {
            log_message('error', 'Exception while deleting process (Product Process ID) ' . $productProcessId . ': ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An unexpected error occurred.']);
        }
    }

    public function ajaxDeleteBatch($batchId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403, 'Forbidden');
        }

        try {
            if (empty($batchId)) {
                $input = $this->request->getJSON(true) ?: [];
                $batchId = $input['batch_id'] ?? $input['id'] ?? null;
            }

            if (empty($batchId)) {
                return $this->response->setJSON(['success' => false, 'message' => 'Batch ID is required.']);
            }

            // Pre-delete diagnostics: fetch row and log it so we can see exactly what was attempted
            try {
                $row = $this->db->table('process_batches')->where('id', $batchId)->get()->getRowArray();
                log_message('debug', 'ajaxDeleteBatch PRE: ' . json_encode($row));
            } catch (\Exception $e) {
                log_message('debug', 'ajaxDeleteBatch PRE error: ' . $e->getMessage());
            }

            // Attempt delete and verify affected rows
            $this->db->transStart();
            $this->batchModel->delete($batchId);
            $deleted = $this->db->affectedRows();
            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                log_message('error', "Transaction failed while deleting batch {$batchId}");
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete batch (transaction failed).']);
            }

            // Diagnostic log for troubleshooting (will show number of rows deleted)
            log_message('debug', "ajaxDeleteBatch: batchId={$batchId}, affectedRows={$deleted}");

            if ($deleted === 0) {
                log_message('warning', "Delete batch {$batchId} completed but 0 rows were affected");
                return $this->response->setJSON(['success' => false, 'message' => 'No matching batch found to delete.']);
            }

            return $this->response->setJSON(['success' => true, 'message' => 'Batch deleted successfully.']);
        } catch (\Exception $e) {
            log_message('error', 'Error deleting batch ' . $batchId . ': ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Server error occurred']);
        }
    }
}