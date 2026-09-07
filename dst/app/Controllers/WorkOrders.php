<?php

namespace App\Controllers;

use App\Models\WorkOrderModel;
use App\Models\ProductModel;
use App\Models\ProcessModel;
use App\Models\WorkOrderProcessRunModel;
use App\Models\ComponentModel;
use App\Models\ComponentUsageModel;

class WorkOrders extends BaseController
{
    protected $workOrderModel;
    // cache for process_batch_logs columns
    protected $pblCols = null;

    /**
     * Constructor - initialize commonly used models
     */
    public function __construct()
    {
        // Ensure BaseController constructor runs if it exists
        if (method_exists(get_parent_class($this), '__construct')) {
            parent::__construct();
        }

        // Initialize the WorkOrderModel so methods can safely use $this->workOrderModel
        $this->workOrderModel = new WorkOrderModel();
    }

    /**
     * Display work orders list (index)
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.view');

        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');
        $product = $this->request->getGet('product');
        $priority = $this->request->getGet('priority');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $this->workOrderModel = new WorkOrderModel();
        $workOrders = $this->workOrderModel->getWorkOrdersWithFilters($search, $status, $product, $priority, $perPage);

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Work Orders',
            'work_orders' => $workOrders,
            'pager' => $this->workOrderModel->pager,
            'products' => $products,
            'current_search' => $search,
            'current_status' => $status,
            'current_product' => $product,
            'current_priority' => $priority,
            'per_page' => $perPage,
            'can_create' => $this->hasPermission('work_orders.create'),
            'can_edit' => $this->hasPermission('work_orders.edit'),
            'can_delete' => $this->hasPermission('work_orders.delete')
        ]);

        return view('work_orders/index', $data);
    }

    /**
     * Display single work order details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.view');

        $workOrderModel = new WorkOrderModel();
        $workOrder = $workOrderModel->getWorkOrderWithDetails((int) $id);

        if (!$workOrder) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Work Order not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Work Order - ' . ($workOrder['wo_number'] ?? ''),
            'work_order' => $workOrder,
            'can_edit' => $this->hasPermission('work_orders.edit'),
            'can_delete' => $this->hasPermission('work_orders.delete')
        ]);

        return view('work_orders/show', $data);
    }

    /**
     * AJAX: create a process batch for a work order item
     */
    public function ajaxCreateBatch($workOrderId = null, $itemId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        // Accept form-encoded and JSON payloads
        $post = $this->request->getPost();
        $raw = @file_get_contents('php://input');
        if (empty($post) && !empty($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $post = $decoded;
            }
        }

        $processId = (int) ($post['process_id'] ?? 0);
        $plannedQty = (int) ($post['planned_qty'] ?? $post['planned_quantity'] ?? 0);

        if (empty($itemId)) {
            $itemId = (int) ($post['work_order_item_id'] ?? $post['item_id'] ?? 0);
        }

        if (!$itemId || !$processId || $plannedQty <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid input']);
        }

        $batchModel = new \App\Models\ProcessBatchModel();
        $batchCode = 'BATCH-' . time() . '-' . rand(100,999);

        $data = [
            'work_order_item_id' => $itemId,
            'process_id' => $processId,
            'planned_qty' => $plannedQty,
            'status' => 'open',
            'created_by' => $this->currentUser['id'] ?? 1
        ];

        // Inspect columns once and map the identifier to existing fields only.
        try {
            $db = \Config\Database::connect();
            $colsRes = $db->query("SHOW COLUMNS FROM process_batches")->getResultArray();
            $cols = array_column($colsRes, 'Field');

            if (in_array('batch_code', $cols, true)) {
                $data['batch_code'] = $batchCode;
            }
            if (in_array('batch_number', $cols, true)) {
                $data['batch_number'] = $batchCode;
            }

            if (!empty($cols)) {
                $data = array_intersect_key($data, array_flip($cols));
            }
        } catch (\Throwable $e) {
            // If inspection fails, set legacy batch_number as a safe fallback.
            $data['batch_number'] = $batchCode;
        }

        if ($batchModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'batch_id' => $batchModel->getInsertID(), 'batch_code' => $batchCode, 'planned_quantity' => $plannedQty]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to create batch']);
    }

    /**
     * AJAX: return processes for a work order item and existing batches
     */
    public function ajaxGetItemProcesses($workOrderId = null, $itemId = null)
    {
        $this->requireAuth();

        if (!$workOrderId || !$itemId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing ids']);
        }

        $db = \Config\Database::connect();
        $productId = (int) $this->request->getGet('product_id') ?: 0;
        
        // Get processes for this product with vendor/employee info using product_processes mapping (ordered workflow)
        $processes = [];
        if ($productId > 0) {
            try {
                // Prefer product_processes mapping to get an ordered workflow for the product
                $pp = $db->table('product_processes pp')
                    ->select('pp.id as product_process_id, pp.sequence_order, pp.custom_time_minutes, pp.custom_notes, p.*, v.name as vendor_name')
                    ->join('processes p', 'p.id = pp.process_id', 'left')
                    ->join('vendors v', 'v.id = p.vendor_id', 'left')
                    ->where('pp.product_id', $productId)
                    ->where('pp.is_active', 1)
                    ->orderBy('pp.sequence_order', 'ASC')
                    ->get();

                $processes = $pp->getResultArray();

                // Determine completed tally columns once
                $cols = $this->getProcessBatchLogColumns();
                $completedExpr = null;
                if (in_array('qty_completed', $cols)) {
                    $completedExpr = 'COALESCE(SUM(pbl.qty_completed),0)';
                } elseif (in_array('accepted_qty', $cols) && in_array('repaired_qty', $cols)) {
                    $completedExpr = 'COALESCE(SUM(pbl.accepted_qty + pbl.repaired_qty),0)';
                }

                // Get work order quantity for this item/product
                $woTotal = $db->table('work_order_items woi')
                    ->select('woi.quantity_ordered')
                    ->where('woi.id', $itemId)
                    ->where('woi.work_order_id', $workOrderId)
                    ->where('woi.product_id', $productId)
                    ->get()->getRowArray();
                $woQty = (int)($woTotal['quantity_ordered'] ?? 0);

                foreach ($processes as &$process) {
                    // Started = sum planned quantities of batches for this item+process
                    $started = $db->table('process_batches pb')
                        ->select('COALESCE(SUM(pb.planned_qty),0) as total_started')
                        ->where('pb.process_id', $process['id'])
                        ->where('pb.work_order_item_id', $itemId)
                        ->get()->getRowArray();
                    $startedQty = (int)($started['total_started'] ?? 0);

                    // Completed = sum logs for batches of this process+item
                    $completedQty = 0;
                    if ($completedExpr) {
                        $res = $db->table('process_batches pb')
                            ->select($completedExpr . ' as total_completed', false)
                            ->join('process_batch_logs pbl', 'pbl.process_batch_id = pb.id', 'left')
                            ->where('pb.process_id', $process['id'])
                            ->where('pb.work_order_item_id', $itemId)
                            ->get()->getRowArray();
                        $completedQty = (int)($res['total_completed'] ?? 0);
                    }

                    $process['work_order_quantity'] = $woQty;
                    $process['quantity_started'] = $startedQty;
                    $process['quantity_completed'] = $completedQty;
                    $process['quantity_remaining'] = max(0, $woQty - $completedQty);
                }
                unset($process);
            } catch (\Throwable $e) {
                log_message('error', 'WorkOrders::ajaxGetItemProcesses - failed to load processes: ' . $e->getMessage());
                $processes = [];
            }
        }

        $cols = $this->getProcessBatchLogColumns();

        // Build query using query builder and only join logs if appropriate columns exist
        try {
            $pb = $db->table('process_batches pb')->where('pb.work_order_item_id', $itemId);

            $shouldJoinLogs = false;
            $totalExpr = null;

            if (in_array('qty_completed', $cols)) {
                $shouldJoinLogs = true;
                $totalExpr = 'COALESCE(SUM(pbl.qty_completed),0)';
            } elseif (in_array('accepted_qty', $cols) && in_array('repaired_qty', $cols)) {
                $shouldJoinLogs = true;
                $totalExpr = 'COALESCE(SUM((pbl.accepted_qty + pbl.repaired_qty)),0)';
            }

            if ($shouldJoinLogs && $totalExpr !== null) {
                $pb->select('pb.*, p.name as process_name, p.is_vendor_process, p.vendor_id, v.name as vendor_name, ' . $totalExpr . ' as total_completed')
                   ->join('processes p', 'p.id = pb.process_id', 'left')
                   ->join('vendors v', 'v.id = p.vendor_id', 'left')
                   ->join('process_batch_logs pbl', 'pbl.process_batch_id = pb.id', 'left')
                   ->groupBy('pb.id, p.name, p.is_vendor_process, p.vendor_id, v.name')
                   ->orderBy('pb.created_at', 'DESC');
            } else {
                $pb->select('pb.*, p.name as process_name, p.is_vendor_process, p.vendor_id, v.name as vendor_name')
                   ->join('processes p', 'p.id = pb.process_id', 'left') 
                   ->join('vendors v', 'v.id = p.vendor_id', 'left')
                   ->orderBy('pb.created_at', 'DESC');
            }

            $batches = $pb->get()->getResultArray() ?: [];
            foreach ($batches as &$b) {
                // Ensure numeric planned_qty is present regardless of column name
                $b['planned_qty'] = (int) ($b['planned_qty'] ?? $b['planned_quantity'] ?? $b['planned'] ?? 0);
                // Ensure total_completed exists (from joined aggregation) and coerce numeric
                $b['total_completed'] = (int) ($b['total_completed'] ?? $b['total_accepted'] ?? $b['actual_qty'] ?? $b['actual_quantity'] ?? 0);
                // Normalize batch code field
                if (empty($b['batch_code']) && !empty($b['batch_number'])) {
                    $b['batch_code'] = $b['batch_number'];
                }
                if (empty($b['batch_code'])) {
                    $b['batch_code'] = '#' . ($b['id'] ?? '');
                }
                
                // Calculate pending quantity for this batch
                $b['quantity_pending'] = $b['planned_qty'] - $b['total_completed'];
            }
        } catch (\Throwable $e) {
            log_message('error', 'WorkOrders::ajaxGetItemProcesses - batch query failed: ' . $e->getMessage());
            $batches = [];
        }

        return $this->response->setJSON(['success' => true, 'processes' => $processes, 'batches' => $batches]);
    }

    /**
     * Backwards-compatible AJAX create endpoint (snake_case) used by some client code.
     * Accepts JSON or form POST and creates a process batch.
     */
    public function ajax_create_batch()
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        $post = $this->request->getPost();
        $raw = @file_get_contents('php://input');
        if (empty($post) && !empty($raw)) {
            $decoded = json_decode($raw, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $post = $decoded;
            }
        }

        $processId = (int) ($post['process_id'] ?? 0);
        $plannedQty = (int) ($post['planned_qty'] ?? $post['planned_quantity'] ?? 0);
        $itemId = (int) ($post['work_order_item_id'] ?? $post['item_id'] ?? 0);

        if (!$itemId || !$processId || $plannedQty <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid input']);
        }

        $batchModel = new \App\Models\ProcessBatchModel();
        $batchCode = trim((string) ($post['batch_code'] ?? ''));
        if ($batchCode === '') {
            $batchCode = 'BATCH-' . time() . '-' . rand(100,999);
        }

        $data = [
            'work_order_item_id' => $itemId,
            'process_id' => $processId,
            'planned_qty' => $plannedQty,
            'status' => $post['status'] ?? 'open',
            'created_by' => $this->currentUser['id'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];

        try {
            $db = \Config\Database::connect();
            $colsRes = $db->query("SHOW COLUMNS FROM process_batches")->getResultArray();
            $cols = array_column($colsRes, 'Field');

            if (in_array('batch_code', $cols, true)) {
                $data['batch_code'] = $batchCode;
            }
            if (in_array('batch_number', $cols, true)) {
                $data['batch_number'] = $batchCode;
            }

            if (!empty($cols)) {
                $data = array_intersect_key($data, array_flip($cols));
            }
        } catch (\Throwable $e) {
            // Fallback: ensure legacy batch_number set so UNIQUE constraints aren't violated by empty values
            $data['batch_number'] = $batchCode;
        }

        if ($batchModel->insert($data)) {
            return $this->response->setJSON(['success' => true, 'batch_id' => $batchModel->getInsertID(), 'batch_code' => $batchCode, 'planned_quantity' => $plannedQty]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to create batch']);
    }

    /**
     * AJAX: add a log entry to a process batch
     */
    public function ajaxAddBatchLog($batchId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        $post = $this->request->getPost();
        
        // Support both old and new field formats
        $accepted = (int) ($post['accepted'] ?? $post['qty_completed'] ?? 0);
        $repaired = (int) ($post['repaired'] ?? 0);
        $rejected = (int) ($post['rejected'] ?? $post['qty_rejected'] ?? 0);
        $scrapped = (int) ($post['qty_scrapped'] ?? 0);
        $received = (int) ($post['qty_received'] ?? 0);
        $forRepair = (int) ($post['qty_for_repair'] ?? 0);
        $employee_id = (int) ($post['employee_id'] ?? 0);
        $vendor_id = (int) ($post['vendor_id'] ?? 0);
        $log_type = $post['log_type'] ?? 'progress';
        $notes = $post['notes'] ?? null;

        if (!$batchId || ($accepted + $repaired + $rejected + $scrapped + $received + $forRepair) <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid input - no quantities entered']);
        }

        $db = \Config\Database::connect();
        $batchModel = new \App\Models\ProcessBatchModel();
        $logModel = new \App\Models\ProcessBatchLogModel();
        $woItemModel = new \App\Models\WorkOrderItemModel();

        // Fetch batch to get planned_qty and work_order_item_id
        $batch = $db->table('process_batches pb')
            ->join('processes p', 'p.id = pb.process_id', 'left')
            ->select('pb.*, p.is_vendor_process')
            ->where('pb.id', $batchId)
            ->get()->getRowArray();
            
        if (!$batch) {
            return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);
        }

        $planned = (int) ($batch['planned_qty'] ?? 0);

        // Sum existing logs for this batch using model that handles schema variants
        $pbModel = new \App\Models\ProcessBatchModel();
        $existingTotals = $pbModel->getBatchTotals($batchId);
        $already = (int) ($existingTotals['accepted'] ?? 0) + (int) ($existingTotals['repaired'] ?? 0);
        $incoming = $accepted + $repaired + $rejected + $scrapped + $received;

        if ($planned > 0 && ($already + $incoming) > $planned) {
            return $this->response->setJSON(['success' => false, 'message' => 'Entry exceeds planned batch quantity']);
        }

        // Validate assignee based on process type
        if (!empty($batch['is_vendor_process'])) {
            if ($vendor_id <= 0) {
                return $this->response->setJSON(['success' => false, 'message' => 'Vendor is required for outsourced process logs']);
            }
        } else {
            if ($employee_id <= 0) {
                return $this->response->setJSON(['success' => false, 'message' => 'Employee is required for in-house process logs']);
            }
        }

        // Insert log and update totals inside transaction
        $db->transStart();
        $steps = [];
        try {
            $logData = [
                'process_batch_id' => $batchId,
                'log_date' => $post['log_date'] ?? date('Y-m-d'),
                'log_type' => $log_type,
                'qty_received' => $received,
                'qty_completed' => $accepted,
                'qty_rejected' => $rejected,
                'qty_scrapped' => $scrapped,
                // New field for repair workflow
                'qty_for_repair' => $forRepair,
                // Keep old fields for backward compatibility
                'accepted_qty' => $accepted,
                'repaired_qty' => $repaired,
                'rejected_qty' => $rejected + $scrapped,
                'operator_id' => $this->currentUser['id'] ?? 1,
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            // Add employee or vendor based on process type
            if ($batch['is_vendor_process'] && $vendor_id > 0) {
                $logData['vendor_id'] = $vendor_id;
            } elseif (!$batch['is_vendor_process'] && $employee_id > 0) {
                $logData['employee_id'] = $employee_id;
            }
            
            $ins = $logModel->insert($logData);
            $modelErrors = $logModel->errors() ?: [];
            $steps[] = ['action' => 'insert_log', 'ok' => (bool)$ins, 'insert_id' => $logModel->getInsertID(), 'model_errors' => $modelErrors];
            log_message('debug', 'ajaxAddBatchLog step: insert_log ok=' . ($ins ? '1' : '0') . ' id=' . $logModel->getInsertID() . ' errors=' . json_encode($modelErrors));

            // Update work_order_items completed quantity: use accepted + repaired as contribution
            $completedInc = $accepted + $repaired;
            if ($completedInc > 0) {
                try {
                    // Detect the correct column name for completed quantity to be defensive across schema variants
                    $woItemTable = 'work_order_items';
                    $columns = $db->query("SHOW COLUMNS FROM {$woItemTable}")->getResultArray();
                    $colNames = array_column($columns, 'Field');
                    $candidate = null;
                    if (in_array('quantity_completed', $colNames)) {
                        $candidate = 'quantity_completed';
                    } elseif (in_array('completed_qty', $colNames)) {
                        $candidate = 'completed_qty';
                    } elseif (in_array('quantity_done', $colNames)) {
                        $candidate = 'quantity_done';
                    }

                    if ($candidate) {
                        $res = $db->table($woItemTable)
                            ->set($candidate, "{$candidate} + {$completedInc}", false)
                            ->where('id', $batch['work_order_item_id'])
                            ->update();
                        $err = $db->error();
                        $steps[] = ['action' => 'update_wo_item', 'ok' => (bool)$res, 'db_errno' => $err['code'], 'db_msg' => $err['message']];
                        log_message('debug', 'ajaxAddBatchLog step: update_wo_item ok=' . ($res ? '1' : '0') . ' err=' . json_encode($err));
                    } else {
                        // No suitable column found; log and continue without failing the transaction
                        $steps[] = ['action' => 'update_wo_item', 'ok' => false, 'reason' => 'no_completed_column'];
                        log_message('warning', "WorkOrders::ajaxAddBatchLog - no completed qty column found in {$woItemTable}; skipping update for batch {$batchId}");
                    }
                } catch (\Exception $e) {
                    log_message('error', 'Failed to update work_order_items completed quantity: ' . $e->getMessage());
                    // Continue; do not rethrow so transaction can handle other parts
                }
            }

            // Recalculate totals via model (handles schema variants) and possibly close batch
            $totals = $pbModel->getBatchTotals($batchId);
            $steps[] = ['action' => 'recalc_totals', 'totals' => $totals];
            log_message('debug', 'ajaxAddBatchLog step: recalc_totals ' . json_encode($totals));

            $totalAll = ((int) ($totals['accepted'] ?? 0)) + ((int) ($totals['repaired'] ?? 0)) + ((int) ($totals['rejected'] ?? 0));

            if ($planned > 0 && $totalAll >= $planned) {
                $res = $db->table('process_batches')->where('id', $batchId)->update(['status' => 'closed', 'completed_at' => date('Y-m-d H:i:s')]);
                $err = $db->error();
                $steps[] = ['action' => 'close_batch', 'ok' => (bool)$res, 'db_errno' => $err['code'], 'db_msg' => $err['message']];
                log_message('debug', 'ajaxAddBatchLog step: close_batch ok=' . ($res ? '1' : '0') . ' err=' . json_encode($err));
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                $dberr = $db->error();
                $errMsg = isset($dberr['message']) ? $dberr['message'] : 'Unknown DB error';
                // Log the DB error for server-side debugging and return message to caller for diagnosis
                log_message('error', 'ajaxAddBatchLog transaction failed: ' . $errMsg . ' steps=' . json_encode($steps));
                return $this->response->setJSON(['success' => false, 'message' => 'Transaction failed: ' . $errMsg, 'steps' => $steps]);
            }

            return $this->response->setJSON([
                'success' => true, 
                'log_id' => $logModel->getInsertID(),
                'log_type' => $log_type,
                'employee_id' => $employee_id,
                'qty_completed' => $accepted,
                'qty_rejected' => $rejected,
                'qty_scrapped' => $scrapped
            ]);

        } catch (\Throwable $e) {
            // Catch any throwable, rollback and return detailed debug info
            $db->transRollback();
            $dberr = $db->error();
            $errMsg = isset($dberr['message']) ? $dberr['message'] : '';
            $payload = [
                'success' => false,
                'message' => 'Failed to add log: ' . $e->getMessage(),
                'db_error' => $errMsg,
                'exception_trace' => $e->getTraceAsString()
            ];
            // Log server-side too
            log_message('error', 'ajaxAddBatchLog exception: ' . $e->getMessage() . ' DB: ' . $errMsg . "\n" . $e->getTraceAsString());
            return $this->response->setJSON($payload);
        }
    }

    /**
     * AJAX: Get batch details for forms
     */
    public function ajaxGetBatchDetails($batchId = null)
    {
        $this->requireAuth();

        if (!$batchId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid batch ID']);
        }

        $db = \Config\Database::connect();
        
        $batch = $db->table('process_batches pb')
            ->join('processes p', 'pb.process_id = p.id', 'left')
            ->join('vendors v', 'p.vendor_id = v.id', 'left')
            ->join('work_order_items woi', 'pb.work_order_item_id = woi.id', 'left')
            ->join('work_orders wo', 'woi.work_order_id = wo.id', 'left')
            ->select('pb.*, p.name as process_name, p.is_vendor_process, p.vendor_id, v.name as vendor_name, wo.wo_number, woi.quantity_ordered')
            ->where('pb.id', $batchId)
            ->get()->getRowArray();

        if (!$batch) {
            return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);
        }

        // Get batch logs summary (defensive against schema variants)
        $logs = [];
        $cols = $this->getProcessBatchLogColumns();

        try {
            if (in_array('qty_completed', $cols)) {
                $logs = $db->table('process_batch_logs')
                    ->select('SUM(qty_completed) as total_completed, SUM(qty_rejected) as total_rejected, SUM(qty_scrapped) as total_scrapped, COUNT(*) as log_count')
                    ->where('process_batch_id', $batchId)
                    ->get()->getRowArray();
                $batch['total_completed'] = $logs['total_completed'] ?? 0;
                $batch['total_rejected'] = $logs['total_rejected'] ?? 0;
                $batch['total_scrapped'] = $logs['total_scrapped'] ?? 0;
                $batch['log_count'] = $logs['log_count'] ?? 0;
            } else {
                // Older schema: aggregate accepted + repaired as completed
                $logs = $db->table('process_batch_logs')
                    ->select('SUM(accepted_qty) as total_accepted, SUM(repaired_qty) as total_repaired, SUM(rejected_qty) as total_rejected, COUNT(*) as log_count')
                    ->where('process_batch_id', $batchId)
                    ->get()->getRowArray();
                $totalAccepted = (int) ($logs['total_accepted'] ?? 0);
                $totalRepaired = (int) ($logs['total_repaired'] ?? 0);
                $batch['total_completed'] = $totalAccepted + $totalRepaired;
                $batch['total_rejected'] = (int) ($logs['total_rejected'] ?? 0);
                $batch['total_scrapped'] = 0;
                $batch['log_count'] = $logs['log_count'] ?? 0;
            }
        } catch (\Throwable $e) {
            log_message('error', 'WorkOrders::ajaxGetBatchDetails - logs aggregation failed: ' . $e->getMessage());
            $batch['total_completed'] = 0;
            $batch['total_rejected'] = 0;
            $batch['total_scrapped'] = 0;
            $batch['log_count'] = 0;
        }

    // Normalize fields for client
    $batch['planned_qty'] = (int) ($batch['planned_qty'] ?? $batch['planned_quantity'] ?? $batch['planned'] ?? 0);
    if (empty($batch['batch_code']) && !empty($batch['batch_number'])) $batch['batch_code'] = $batch['batch_number'];
    $batch['actual_quantity'] = $batch['actual_qty'] ?? $batch['actual_quantity'] ?? $batch['total_completed'] ?? 0;

    return $this->response->setJSON(['success' => true, 'batch' => $batch]);
    }

    /**
     * AJAX: Update batch status and details
     */
    public function ajaxUpdateBatch($batchId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        if (!$batchId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid batch ID']);
        }

        $post = $this->request->getPost();
        $status = $post['status'] ?? '';
        $actualQty = (int) ($post['actual_qty'] ?? 0);
        $completedAt = $post['completed_at'] ?? null;
        $notes = trim($post['notes'] ?? '');

        if (!in_array($status, ['open', 'in-progress', 'completed', 'closed'])) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid status']);
        }

        $db = \Config\Database::connect();
        $batchModel = new \App\Models\ProcessBatchModel();

        $updateData = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($actualQty > 0) {
            $updateData['actual_qty'] = $actualQty;
        }

        if ($completedAt) {
            $updateData['completed_at'] = $completedAt;
        }

        if ($notes) {
            $updateData['notes'] = $notes;
        }

        if ($batchModel->update($batchId, $updateData)) {
            return $this->response->setJSON([
                'success' => true, 
                'message' => 'Batch updated successfully',
                'batch_id' => $batchId,
                'status' => $status
            ]);
        }

        return $this->response->setJSON(['success' => false, 'message' => 'Failed to update batch']);
    }

    /**
     * AJAX: delete a batch
     */
    public function ajaxDeleteBatch($batchId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        if (!$batchId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid batch ID']);
        }

        $db = \Config\Database::connect();
        $batch = $db->table('process_batches')->where('id', $batchId)->get()->getRowArray();
        if (!$batch) {
            return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);
        }

        try {
            $batchModel = new \App\Models\ProcessBatchModel();
            $res = $batchModel->delete($batchId);
            if ($res) {
                return $this->response->setJSON(['success' => true, 'message' => 'Batch deleted']);
            }
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to delete batch']);
        } catch (\Throwable $e) {
            log_message('error', 'ajaxDeleteBatch failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Server error']);
        }
    }

    /**
     * Snake_case alias for compatibility with client code that calls ajax_delete_batch
     */
    public function ajax_delete_batch($batchId = null)
    {
        return $this->ajaxDeleteBatch($batchId);
    }

    /**
     * AJAX: release quantity from a batch and create a gatepass record
     */
    public function ajaxReleaseBatch($batchId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        $post = $this->request->getPost();
        $releaseQty = (int) ($post['released_qty'] ?? 0);
        $carrier = $post['carrier'] ?? null;
        $notes = $post['notes'] ?? null;

        if (!$batchId || $releaseQty <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid input']);
        }

        $db = \Config\Database::connect();
        $batch = $db->table('process_batches')->where('id', $batchId)->get()->getRowArray();
        if (!$batch) return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);

        // ensure enough completed quantity exists (simple check)
        $logs = $db->table('process_batch_logs')->select('SUM(accepted_qty) as a, SUM(repaired_qty) as r')->where('process_batch_id', $batchId)->get()->getRowArray();
        $available = ((int)($logs['a'] ?? 0)) + ((int)($logs['r'] ?? 0));

        // subtract previous releases
        $released = (int) ($db->table('process_batch_releases')->select('SUM(released_qty) as s')->where('process_batch_id', $batchId)->get()->getRowArray()['s'] ?? 0);

        if ($releaseQty > ($available - $released)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Not enough available quantity to release']);
        }

        $db->transStart();
        try {
            $releaseModel = new \App\Models\ProcessBatchReleaseModel();
            $releaseModel->insert([
                'process_batch_id' => $batchId,
                'released_qty' => $releaseQty,
                'released_by' => $this->currentUser['id'] ?? 1,
                'released_at' => date('Y-m-d H:i:s'),
                'carrier' => $carrier,
                'notes' => $notes
            ]);

            // Optionally mark batch as partially released or released
            $db->table('process_batches')->where('id', $batchId)->update(['status' => 'released']);

            $db->transComplete();
            if ($db->transStatus() === false) throw new \Exception('Transaction failed');

            $releaseId = $releaseModel->getInsertID();
            return $this->response->setJSON(['success' => true, 'release_id' => $releaseId]);
        } catch (\Exception $e) {
            $db->transRollback();
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to release: ' . $e->getMessage()]);
        }
    }

    /**
     * Printable gatepass for a release
     */
    public function gatepass($releaseId = null)
    {
        $this->requireAuth();
        if (!$releaseId) throw new \CodeIgniter\Exceptions\PageNotFoundException('Gatepass not found');

        $db = \Config\Database::connect();
        $release = $db->table('process_batch_releases')
            ->select('process_batch_releases.*, process_batches.batch_code, process_batches.process_id, work_order_items.work_order_id')
            ->join('process_batches', 'process_batches.id = process_batch_releases.process_batch_id')
            ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id')
            ->where('process_batch_releases.id', $releaseId)
            ->get()->getRowArray();

        if (!$release) throw new \CodeIgniter\Exceptions\PageNotFoundException('Gatepass not found');

        $data = $this->setPageData([
            'page_title' => 'Gatepass - ' . ($release['batch_code'] ?? ''),
            'release' => $release
        ]);

        return view('work_orders/gatepass', $data);
    }

    /**
     * Generate PDF gatepass (server-side) if dompdf is installed, otherwise redirect to HTML gatepass
     */
    public function gatepassPdf($releaseId = null)
    {
        $this->requireAuth();
        if (!$releaseId) throw new \CodeIgniter\Exceptions\PageNotFoundException('Gatepass not found');

        $db = \Config\Database::connect();
        $release = $db->table('process_batch_releases')
            ->select('process_batch_releases.*, process_batches.batch_code, process_batches.process_id, work_order_items.work_order_id')
            ->join('process_batches', 'process_batches.id = process_batch_releases.process_batch_id')
            ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id')
            ->where('process_batch_releases.id', $releaseId)
            ->get()->getRowArray();

        if (!$release) throw new \CodeIgniter\Exceptions\PageNotFoundException('Gatepass not found');

        // If Dompdf is not installed, redirect to HTML gatepass
        if (!class_exists('\\Dompdf\\Dompdf')) {
            return redirect()->to('/releases/' . $releaseId . '/gatepass');
        }

        $html = view('work_orders/gatepass', ['release' => $release, 'page_title' => 'Gatepass']);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $this->response->setHeader('Content-Type', 'application/pdf')
            ->setHeader('Content-Disposition', 'inline; filename="gatepass_' . $releaseId . '.pdf"')
            ->setBody($dompdf->output());
    }

    /**
     * Inspect and cache columns for process_batch_logs table.
     * Returns an array of column names (strings). Returns empty array on failure.
     */
    protected function getProcessBatchLogColumns(): array
    {
        if ($this->pblCols !== null) return $this->pblCols;
        $db = \Config\Database::connect();
        try {
            $cols = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
            $this->pblCols = array_column($cols, 'Field');
            return $this->pblCols;
        } catch (\Throwable $e) {
            log_message('warning', 'WorkOrders::getProcessBatchLogColumns - unable to inspect process_batch_logs columns: ' . $e->getMessage());
            $this->pblCols = [];
            return $this->pblCols;
        }
    }

    /**
     * Get products for a work order (AJAX endpoint)
     */
    public function getProducts($id = null)
    {
        $this->requireAuth();
        
        if (!$id) {
            return $this->response->setJSON(['success' => false, 'message' => 'Work order ID required']);
        }

        $workOrderItemModel = new \App\Models\WorkOrderItemModel();
        $products = $workOrderItemModel->getWorkOrderItemsWithDetails($id);
        
        if (empty($products)) {
            return $this->response->setJSON(['success' => false, 'message' => 'No products found']);
        }

        // Calculate totals
        $totalProducts = count($products);
        $totalQuantity = 0;
        $totalCompleted = 0;

        foreach ($products as $product) {
            $totalQuantity += $product['quantity_ordered'];
            $totalCompleted += $product['quantity_completed'];
        }

        return $this->response->setJSON([
            'success' => true,
            'products' => $products,
            'totals' => [
                'total_products' => $totalProducts,
                'total_quantity' => $totalQuantity,
                'total_completed' => $totalCompleted
            ]
        ]);
    }

    /**
     * Display create work order form
     */
    public function create()
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.create');

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Create New Work Order',
            'work_order' => null,
            'products' => $products,
            'validation' => \Config\Services::validation()
        ]);

        return view('work_orders/form', $data);
    }

    /**
     * Handle work order creation
     */
    public function store()
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.create');

        log_message('info', 'WorkOrders::store called; remote=' . ($_SERVER['REMOTE_ADDR'] ?? 'cli') . ' ; user=' . ($this->currentUser['id'] ?? 'anon'));
        $rawPost = $this->request->getPost();
        log_message('debug', 'WorkOrders::store POST keys: ' . implode(',', array_keys($rawPost ?? [])) . ' ; counts: ' . count($rawPost));

        $workOrderData = [
            'wo_number' => $this->request->getPost('wo_number') ?: null,
            'customer_name' => $this->request->getPost('customer_name'),
            'priority' => $this->request->getPost('priority'),
            'due_date' => $this->request->getPost('due_date'),
            'notes' => $this->request->getPost('notes'),
            'status' => 'planned',
            'created_by' => $this->currentUser['id'] ?? 1
        ];

        $products = $this->request->getPost('products');

        // Validate work order data and products
        if (empty($workOrderData['customer_name']) || empty($workOrderData['priority']) || empty($workOrderData['due_date']) || empty($products)) {
            log_message('warning', 'WorkOrders::store validation failed - missing required fields or products. customer=' . ($workOrderData['customer_name'] ?? '') . ' priority=' . ($workOrderData['priority'] ?? '') . ' due_date=' . ($workOrderData['due_date'] ?? '') . ' products_present=' . (empty($products) ? 'no' : 'yes'));
            $productModel = new ProductModel();
            $allProducts = $productModel->where('is_active', true)->findAll();
            
            $validation = \Config\Services::validation();
            
            $pageData = $this->setPageData([
                'page_title' => 'Create New Work Order',
                'work_order' => null,
                'products' => $allProducts,
                'validation' => $validation,
                'error_message' => 'Please fill in all required fields including at least one product.'
            ]);

            // Redirect back with input and a flash message so user sees the error clearly
            return redirect()->back()->withInput()->with('error', 'Please fill in all required fields including at least one product.');
        }

        // Validate products array
        $hasValidProducts = false;
        $productErrors = [];
        
        if (empty($products)) {
            $productErrors[] = 'No products data received';
        } else {
            foreach ($products as $index => $product) {
                if (empty($product['product_id'])) {
                    $productErrors[] = "Product #" . ($index + 1) . ": No product selected";
                } elseif (empty($product['quantity'])) {
                    $productErrors[] = "Product #" . ($index + 1) . ": No quantity specified";
                } elseif (!is_numeric($product['quantity']) || $product['quantity'] <= 0) {
                    $productErrors[] = "Product #" . ($index + 1) . ": Invalid quantity";
                } else {
                    $hasValidProducts = true;
                }
            }
        }

        if (!$hasValidProducts) {
            log_message('warning', 'WorkOrders::store validation failed - product list invalid: ' . implode('; ', $productErrors));
            $productModel = new ProductModel();
            $allProducts = $productModel->where('is_active', true)->findAll();
            
            $validation = \Config\Services::validation();
            $errorMessage = 'Please select at least one product with a valid quantity.';
            if (!empty($productErrors)) {
                $errorMessage .= ' Issues found: ' . implode('; ', $productErrors);
            }
            
            // Redirect back with input and error details
            return redirect()->back()->withInput()->with('error', $errorMessage);
        }

    $db = \Config\Database::connect();
    $db->transStart();

        try {
            // WO number will be auto-generated by the model's beforeInsert callback (standard schema)
            // If the DB still uses legacy product_id/quantity columns on work_orders, detect columns for compatibility
            try {
                $dbCheck = \Config\Database::connect();
                $colsRes = $dbCheck->query("SHOW COLUMNS FROM work_orders")->getResultArray();
                $woCols = array_column($colsRes, 'Field');
            } catch (\Throwable $e) {
                $woCols = [];
            }

            // Defensive: ensure created_by refers to an existing user id, otherwise drop it so FK won't fail on older schemas
            if (!empty($workOrderData['created_by'])) {
                try {
                    $dbCheck = \Config\Database::connect();
                    $userExists = $dbCheck->table('users')->where('id', (int)$workOrderData['created_by'])->countAllResults();
                } catch (\Throwable $e) {
                    $userExists = 0;
                }
                if (!$userExists) {
                    // Remove the created_by to allow NULL and avoid FK error
                    unset($workOrderData['created_by']);
                }
            }

            // If legacy columns exist on work_orders (e.g., product_id), do a manual insert to include them
            if (!empty($woCols) && in_array('product_id', $woCols, true)) {
                // Populate legacy fields from the first selected product
                $firstProduct = $products[0] ?? null;
                if (!empty($firstProduct) && !empty($firstProduct['product_id']) && !empty($firstProduct['quantity'])) {
                    if (in_array('product_id', $woCols, true)) $workOrderData['product_id'] = (int)$firstProduct['product_id'];
                    if (in_array('quantity_ordered', $woCols, true)) $workOrderData['quantity_ordered'] = (int)$firstProduct['quantity'];
                    if (in_array('quantity_completed', $woCols, true)) $workOrderData['quantity_completed'] = 0;
                }
                // Ensure wo_number present since model callback won't run
                if (empty($workOrderData['wo_number'])) {
                    if (method_exists($this, 'generateWorkOrderNumber')) {
                        $workOrderData['wo_number'] = $this->generateWorkOrderNumber();
                    } else {
                        // Fallback: simple prefix
                        $workOrderData['wo_number'] = 'WO-' . date('Y') . '-' . str_pad((string)rand(1, 999), 3, '0', STR_PAD_LEFT);
                    }
                }
                // Filter to existing columns only
                $filtered = array_intersect_key($workOrderData, array_flip($woCols));
                if (!$db->table('work_orders')->insert($filtered)) {
                    $db->transRollback();
                    return redirect()->back()->withInput()->with('error', 'Failed to save work order (legacy schema insert).');
                }
                $workOrderId = (int)$db->insertID();
            } else {
                // Standard path through model
                if (!$this->workOrderModel->save($workOrderData)) {
                    $errors = $this->workOrderModel->errors();
                    log_message('error', 'WorkOrders::store model save failed: ' . json_encode($errors));
                    $db->transRollback();

                    // Redirect back with input and validation errors
                    $errorMsg = 'Failed to save work order';
                    if (!empty($errors)) {
                        $errorMsg .= ': ' . implode('; ', $errors);
                    }
                    return redirect()->back()->withInput()->with('error', $errorMsg);
                }
                $workOrderId = $this->workOrderModel->getInsertID();
            }

            log_message('info', 'WorkOrders::store created work_order id=' . $workOrderId);

            // Save work order items
            $workOrderItemModel = new \App\Models\WorkOrderItemModel();
            foreach ($products as $product) {
                if (!empty($product['product_id']) && !empty($product['quantity']) && is_numeric($product['quantity']) && $product['quantity'] > 0) {
                    $itemData = [
                        'work_order_id' => $workOrderId,
                        'product_id' => $product['product_id'],
                        'quantity_ordered' => (int)$product['quantity'],
                        'quantity_completed' => 0
                    ];
                    
                    if (!$workOrderItemModel->save($itemData)) {
                        // Get validation errors for debugging
                        $errors = $workOrderItemModel->errors();
                        $errorMessage = 'Failed to save work order item';
                        if (!empty($errors)) {
                            $errorMessage .= ': ' . implode(', ', $errors);
                        }
                        throw new \Exception($errorMessage);
                    }
                }
            }

            // Create process runs (if method exists)
            if (method_exists($this->workOrderModel, 'createProcessRuns')) {
                $this->workOrderModel->createProcessRuns($workOrderId);
            }

            $db->transComplete();

            if ($db->transStatus() === false) {
                log_message('error', 'WorkOrders::store transaction failed for work_order id=' . $workOrderId);
                throw new \Exception('Transaction failed');
            }

            log_message('info', 'WorkOrders::store completed, redirecting to list.');
            return redirect()->to('/work-orders')->with('success', 'Work Order created successfully.');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create work order: ' . $e->getMessage());
        }
    }

    /**
     * Display edit work order form
     */
    public function edit($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.edit');

        $workOrder = $this->workOrderModel->find($id);
        if (!$workOrder) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Work Order not found');
        }

        // Check if work order can be edited
        if (in_array($workOrder['status'], ['completed', 'cancelled'])) {
            return redirect()->to('/work-orders/' . $id)
                           ->with('error', 'Cannot edit completed or cancelled work orders.');
        }

        // Get work order items
        $workOrderItemModel = new \App\Models\WorkOrderItemModel();
        $workOrder['items'] = $workOrderItemModel->getWorkOrderItemsWithDetails($id);

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Edit Work Order - ' . $workOrder['wo_number'],
            'work_order' => $workOrder,
            'products' => $products,
            'validation' => \Config\Services::validation()
        ]);

        return view('work_orders/form', $data);
    }

    /**
     * Handle work order update
     */
    public function update($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.edit');

        $workOrder = $this->workOrderModel->find($id);
        if (!$workOrder) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Work Order not found');
        }

        // Check if work order can be edited
        if (in_array($workOrder['status'], ['completed', 'cancelled'])) {
            return redirect()->to('/work-orders/' . $id)
                           ->with('error', 'Cannot edit completed or cancelled work orders.');
        }

        $workOrderData = [
            'wo_number' => $this->request->getPost('wo_number') ?: $workOrder['wo_number'],
            'customer_name' => $this->request->getPost('customer_name'),
            'priority' => $this->request->getPost('priority'),
            'due_date' => $this->request->getPost('due_date'),
            'notes' => $this->request->getPost('notes')
        ];
        // Validate wo_number uniqueness manually to avoid placeholder errors
        $validation = \Config\Services::validation();
        $rules = $this->workOrderModel->getValidationRules();
        // Replace wo_number rule to ignore current id
        $rules['wo_number'] = 'permit_empty|min_length[3]|max_length[50]|is_unique[work_orders.wo_number,id,' . (int)$id . ']';
        $validation->setRules($rules);
        if (!$validation->run($workOrderData)) {
            $workOrderItemModel = new \App\Models\WorkOrderItemModel();
            $workOrder['items'] = $workOrderItemModel->getWorkOrderItemsWithDetails($id);
            $productModel = new ProductModel();
            $allProducts = $productModel->where('is_active', true)->findAll();
            $pageData = $this->setPageData([
                'page_title' => 'Edit Work Order - ' . $workOrder['wo_number'],
                'work_order' => $workOrder,
                'products' => $allProducts,
                'validation' => $validation,
            ]);
            return view('work_orders/form', $pageData);
        }

        $products = $this->request->getPost('products');

        // Validate work order data and products
        if (empty($workOrderData['customer_name']) || empty($workOrderData['priority']) || empty($workOrderData['due_date']) || empty($products)) {
            $workOrderItemModel = new \App\Models\WorkOrderItemModel();
            $workOrder['items'] = $workOrderItemModel->getWorkOrderItemsWithDetails($id);
            
            $productModel = new ProductModel();
            $allProducts = $productModel->where('is_active', true)->findAll();
            
            $validation = \Config\Services::validation();
            
            $pageData = $this->setPageData([
                'page_title' => 'Edit Work Order - ' . $workOrder['wo_number'],
                'work_order' => $workOrder,
                'products' => $allProducts,
                'validation' => $validation,
                'error_message' => 'Please fill in all required fields including at least one product.'
            ]);
            
            return view('work_orders/form', $pageData);
        }

        // Validate products array
        $hasValidProducts = false;
        foreach ($products as $product) {
            if (!empty($product['product_id']) && !empty($product['quantity']) && is_numeric($product['quantity']) && $product['quantity'] > 0) {
                $hasValidProducts = true;
                break;
            }
        }

        if (!$hasValidProducts) {
            $workOrderItemModel = new \App\Models\WorkOrderItemModel();
            $workOrder['items'] = $workOrderItemModel->getWorkOrderItemsWithDetails($id);
            
            $productModel = new ProductModel();
            $allProducts = $productModel->where('is_active', true)->findAll();
            
            $validation = \Config\Services::validation();
            
            $pageData = $this->setPageData([
                'page_title' => 'Edit Work Order - ' . $workOrder['wo_number'],
                'work_order' => $workOrder,
                'products' => $allProducts,
                'validation' => $validation,
                'error_message' => 'Please select at least one product with a valid quantity.'
            ]);
            
            return view('work_orders/form', $pageData);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Update work order
            if (!$this->workOrderModel->update($id, $workOrderData)) {
                $db->transRollback();
                
                $workOrderItemModel = new \App\Models\WorkOrderItemModel();
                $workOrder['items'] = $workOrderItemModel->getWorkOrderItemsWithDetails($id);
                
                $productModel = new ProductModel();
                $allProducts = $productModel->where('is_active', true)->findAll();
                
                $validation = \Config\Services::validation();
                $rules = $this->workOrderModel->getValidationRules();
                $rules['wo_number'] = 'permit_empty|min_length[3]|max_length[50]|is_unique[work_orders.wo_number,id,' . (int)$id . ']';
                $validation->setRules($rules);
                $validation->run($workOrderData);
                
                $pageData = $this->setPageData([
                    'page_title' => 'Edit Work Order - ' . $workOrder['wo_number'],
                    'work_order' => $workOrder,
                    'products' => $allProducts,
                    'validation' => $validation
                ]);
                
                return view('work_orders/form', $pageData);
            }

            // Upsert work order items to preserve references (e.g., existing batches)
            $workOrderItemModel = new \App\Models\WorkOrderItemModel();
            // Index existing items by product_id
            $existing = $workOrderItemModel->where('work_order_id', $id)->findAll();
            $byProduct = [];
            foreach ($existing as $it) { $byProduct[$it['product_id']] = $it; }

            // Update or insert items
            $incomingProducts = [];
            foreach ($products as $product) {
                if (!empty($product['product_id']) && !empty($product['quantity']) && is_numeric($product['quantity']) && $product['quantity'] > 0) {
                    $pid = (int)$product['product_id'];
                    $incomingProducts[$pid] = true;
                    $qty = (int)$product['quantity'];
                    if (isset($byProduct[$pid])) {
                        // Update existing row's quantity_ordered
                        $workOrderItemModel->update($byProduct[$pid]['id'], [ 'quantity_ordered' => $qty ]);
                    } else {
                        // Insert new row
                        $workOrderItemModel->save([ 'work_order_id' => $id, 'product_id' => $pid, 'quantity_ordered' => $qty, 'quantity_completed' => 0 ]);
                    }
                }
            }
            // Optionally, do not delete missing products to avoid breaking existing batches; you can enable deletion if safe.

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            return redirect()->to('/work-orders')->with('success', 'Work Order updated successfully.');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to update work order: ' . $e->getMessage());
        }
    }

    /**
     * Delete work order
     */
    public function delete($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.delete');

        $workOrder = $this->workOrderModel->find($id);
        if (!$workOrder) {
            return $this->jsonResponse(['success' => false, 'message' => 'Work Order not found'], 404);
        }

        // Check if work order can be deleted
        if (in_array($workOrder['status'], ['in_progress', 'completed'])) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Cannot delete work orders that are in progress or completed.'
            ], 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Delete related process runs
            $processRunModel = new WorkOrderProcessRunModel();
            $processRunModel->where('work_order_id', $id)->delete();

            // Delete component usage records
            $componentUsageModel = new ComponentUsageModel();
            $componentUsageModel->where('work_order_id', $id)->delete();

            // Delete work order
            $this->workOrderModel->delete($id);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            return $this->jsonResponse(['success' => true, 'message' => 'Work Order deleted successfully.']);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete work order.'], 500);
        }
    }

    /**
     * Change work order status
     */
    public function changeStatus($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.edit');

        $workOrder = $this->workOrderModel->find($id);
        if (!$workOrder) {
            return $this->jsonResponse(['success' => false, 'message' => 'Work Order not found'], 404);
        }

        $newStatus = $this->request->getPost('status');
        $allowedStatuses = ['planned', 'in_progress', 'on_hold', 'completed', 'cancelled'];

        if (!in_array($newStatus, $allowedStatuses)) {
            return $this->jsonResponse(['success' => false, 'message' => 'Invalid status'], 400);
        }

        // Validate status transitions
        $currentStatus = $workOrder['status'];
        if (!$this->isValidStatusTransition($currentStatus, $newStatus)) {
            return $this->jsonResponse([
                'success' => false,
                'message' => "Cannot change status from {$currentStatus} to {$newStatus}"
            ], 400);
        }

        $updateData = [
            'status' => $newStatus,
            'updated_by' => $this->currentUser['id']
        ];

        // Set completion date if completing
        if ($newStatus === 'completed') {
            $updateData['completed_at'] = date('Y-m-d H:i:s');
            
            // Validate that all process runs are completed
            $processRunModel = new WorkOrderProcessRunModel();
            $incompleteRuns = $processRunModel->where('work_order_id', $id)
                                            ->whereNotIn('status', ['completed', 'skipped'])
                                            ->countAllResults();
            
            if ($incompleteRuns > 0) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Cannot complete work order with incomplete process runs'
                ], 400);
            }
        }

        if ($this->workOrderModel->update($id, $updateData)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => "Work Order status changed to {$newStatus}",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update status'], 500);
        }
    }

    /**
     * Wrapper to start production (route: POST /work-orders/{id}/start)
     */
    public function start($id = null)
    {
        return $this->startProduction($id);
    }

    /**
     * Wrapper to mark work order completed (route: POST /work-orders/{id}/complete)
     */
    public function complete($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.edit');

        $updated = $this->workOrderModel->updateStatus((int)$id, 'completed');
        if ($updated) {
            return $this->jsonResponse(['success' => true, 'message' => 'Work Order marked as completed']);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to mark as completed'], 500);
    }

    /**
     * Wrapper to put work order on hold (route: POST /work-orders/{id}/hold)
     */
    public function hold($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.edit');

        $updated = $this->workOrderModel->updateStatus((int)$id, 'on_hold');
        if ($updated) {
            return $this->jsonResponse(['success' => true, 'message' => 'Work Order put on hold']);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to put on hold'], 500);
    }

    /**
     * Wrapper to cancel work order (route: POST /work-orders/{id}/cancel)
     */
    public function cancel($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.delete');

        $updated = $this->workOrderModel->updateStatus((int)$id, 'cancelled');
        if ($updated) {
            return $this->jsonResponse(['success' => true, 'message' => 'Work Order cancelled']);
        }

        return $this->jsonResponse(['success' => false, 'message' => 'Failed to cancel work order'], 500);
    }

    /**
     * Wrapper to export CSV (route: GET /work-orders/export)
     */
    public function export()
    {
        return $this->exportCsv();
    }

    /**
     * Start production for work order
     */
    public function startProduction($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        $workOrder = $this->workOrderModel->getWorkOrderWithDetails($id);
        if (!$workOrder) {
            return $this->jsonResponse(['success' => false, 'message' => 'Work Order not found'], 404);
        }

        if ($workOrder['status'] !== 'planned') {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Can only start production for planned work orders'
            ], 400);
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Update work order status
            $this->workOrderModel->update($id, [
                'status' => 'in_progress',
                'started_at' => date('Y-m-d H:i:s'),
                'updated_by' => $this->currentUser['id']
            ]);

            // Reserve components for production
            $this->reserveComponents($id, $workOrder['quantity_to_produce']);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Failed to start production');
            }

            return $this->jsonResponse([
                'success' => true,
                'message' => 'Production started successfully'
            ]);

        } catch (\Exception $e) {
            $db->transRollback();
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to start production: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export work orders to CSV
     */
    public function exportCsv()
    {
        $this->requireAuth();
        $this->requirePermission('work_orders.view');

        $workOrders = $this->workOrderModel->getWorkOrdersWithDetails();
        
        $filename = 'work_orders_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'WO Number', 'Product', 'Quantity', 'Priority', 'Status', 
            'Start Date', 'Due Date', 'Created Date', 'Created By', 'Notes'
        ]);
        
        // CSV data
        foreach ($workOrders as $wo) {
            fputcsv($output, [
                $wo['wo_number'],
                $wo['product_name'],
                $wo['quantity_to_produce'],
                $wo['priority'],
                $wo['status'],
                $wo['start_date'],
                $wo['due_date'],
                $wo['created_at'],
                $wo['created_by_name'],
                $wo['notes']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Generate unique work order number
     */
    private function generateWorkOrderNumber(): string
    {
        $prefix = 'WO';
        $date = date('Ymd');
        
        // Get the next sequence number for today
        $lastWo = $this->workOrderModel->like('wo_number', $prefix . $date)
                                     ->orderBy('wo_number', 'DESC')
                                     ->first();
        
        if ($lastWo) {
            $lastNumber = substr($lastWo['wo_number'], -3);
            $nextNumber = str_pad((int)$lastNumber + 1, 3, '0', STR_PAD_LEFT);
        } else {
            $nextNumber = '001';
        }
        
        return $prefix . $date . $nextNumber;
    }

    /**
     * Create process runs for work order
     */
    private function createProcessRuns($workOrderId, $productId): void
    {
        $processModel = new ProcessModel();
        $processes = $processModel->getProcessesForProduct($productId);

        $processRunModel = new WorkOrderProcessRunModel();
        
        foreach ($processes as $process) {
            $processRunData = [
                'work_order_id' => $workOrderId,
                'process_id' => $process['id'],
                'sequence_number' => $process['sequence_number'],
                'status' => 'pending',
                'standard_time_minutes' => $process['standard_time_minutes'],
                'created_by' => $this->currentUser['id']
            ];
            
            $processRunModel->save($processRunData);
        }
    }

    /**
     * Reserve components for production
     */
    private function reserveComponents($workOrderId, $quantity): void
    {
        $componentModel = new ComponentModel();
        $workOrder = $this->workOrderModel->find($workOrderId);
        
        $bomComponents = $componentModel->getProductBom($workOrder['product_id']);
        
        $componentUsageModel = new ComponentUsageModel();
        
        foreach ($bomComponents as $bomComponent) {
            $requiredQty = $bomComponent['quantity_per_unit'] * $quantity;
            
            // Check if enough stock available
            if ($bomComponent['current_stock'] < $requiredQty) {
                throw new \Exception("Insufficient stock for component: " . $bomComponent['name']);
            }
            
            // Create component usage record
            $usageData = [
                'work_order_id' => $workOrderId,
                'component_id' => $bomComponent['id'],
                'quantity_required' => $requiredQty,
                'quantity_used' => 0,
                'created_by' => $this->currentUser['id']
            ];
            
            $componentUsageModel->save($usageData);
            
            // Update component reserved quantity
            $componentModel->update($bomComponent['id'], [
                'reserved_stock' => $bomComponent['reserved_stock'] + $requiredQty
            ]);
        }
    }

    /**
     * Check if status transition is valid
     */
    private function isValidStatusTransition($currentStatus, $newStatus): bool
    {
        $validTransitions = [
            'planned' => ['in_progress', 'on_hold', 'cancelled'],
            'in_progress' => ['completed', 'on_hold', 'cancelled'],
            'on_hold' => ['in_progress', 'cancelled'],
            'completed' => [], // No transitions from completed
            'cancelled' => [] // No transitions from cancelled
        ];

        return in_array($newStatus, $validTransitions[$currentStatus] ?? []);
    }
}
