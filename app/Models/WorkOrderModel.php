<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkOrderModel extends Model
{
    protected $table            = 'work_orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'wo_number', 'customer_name', 'due_date', 'status', 'priority', 'notes', 'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'wo_number'        => 'permit_empty|min_length[3]|max_length[50]|is_unique[work_orders.wo_number,id,{id}]',
        'customer_name'    => 'required|min_length[2]|max_length[100]',
        'due_date'         => 'required|valid_date[Y-m-d]',
        'status'           => 'required|in_list[planned,in_progress,on_hold,completed,cancelled]',
        'priority'         => 'required|in_list[low,normal,high,urgent]'
    ];

    protected $validationMessages = [
        'wo_number' => [
            'required'    => 'Work Order number is required',
            'is_unique'   => 'Work Order number already exists'
        ],
        'customer_name' => [
            'required' => 'Customer name is required'
        ],
        'due_date' => [
            'required'    => 'Due date is required',
            'valid_date'  => 'Please enter a valid date'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['generateWoNumber'];

    /**
     * Generate work order number if not provided
     */
    protected function generateWoNumber(array $data)
    {
        if (empty($data['data']['wo_number'])) {
            $prefix = 'WO-' . date('Y') . '-';
            $lastWo = $this->like('wo_number', $prefix, 'after')
                          ->orderBy('id', 'DESC')
                          ->first();

            if ($lastWo) {
                $lastNumber = intval(substr($lastWo['wo_number'], strlen($prefix)));
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            $data['data']['wo_number'] = $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
        }

        return $data;
    }

    /**
     * Get work orders with product and user information
     */
    public function getWorkOrdersWithDetails(): array
    {
        return $this->select('work_orders.*, 
                             users.first_name as created_by_name,
                             DATEDIFF(work_orders.due_date, CURDATE()) as days_to_due,
                             GROUP_CONCAT(DISTINCT products.name SEPARATOR ", ") as product_names,
                             SUM(work_order_items.quantity_ordered) as total_quantity')
                    ->join('work_order_items', 'work_order_items.work_order_id = work_orders.id')
                    ->join('products', 'products.id = work_order_items.product_id')
                    ->join('users', 'users.id = work_orders.created_by', 'left')
                    ->groupBy('work_orders.id, users.first_name')
                    ->orderBy('work_orders.created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Get work order with complete details
     */
    public function getWorkOrderWithDetails(int $workOrderId): array|null
    {
        $workOrder = $this->select('work_orders.*, 
                                   users.first_name as created_by_name,
                                   DATEDIFF(work_orders.due_date, CURDATE()) as days_to_due')
                         ->join('users', 'users.id = work_orders.created_by', 'left')
                         ->where('work_orders.id', $workOrderId)
                         ->first();

        if (!$workOrder) {
            return null;
        }

        // Get work order items with product details
        $workOrderItemModel = new \App\Models\WorkOrderItemModel();
        $workOrder['items'] = $workOrderItemModel->getWorkOrderItemsWithDetails($workOrderId);

        // Calculate totals
        $workOrder['total_quantity'] = $workOrderItemModel->getTotalQuantityByWorkOrder($workOrderId);
        $workOrder['total_completed'] = $workOrderItemModel->getTotalCompletedByWorkOrder($workOrderId);

        // Get process runs
        $processRunModel = new WorkOrderProcessRunModel();
        $workOrder['process_runs'] = $processRunModel->getProcessRunsByWorkOrder($workOrderId);

        // Get component usage
        $componentUsageModel = new ComponentUsageModel();
        $workOrder['component_usage'] = $componentUsageModel->getUsageByWorkOrder($workOrderId);

        return $workOrder;
    }

    /**
     * Get active work orders
     */
    public function getActiveWorkOrders(): array
    {
        return $this->select('work_orders.*, 
                             GROUP_CONCAT(DISTINCT products.name SEPARATOR ", ") as product_names,
                             SUM(work_order_items.quantity_ordered) as total_quantity')
                    ->join('work_order_items', 'work_order_items.work_order_id = work_orders.id')
                    ->join('products', 'products.id = work_order_items.product_id')
                    ->whereIn('work_orders.status', ['planned', 'in_progress'])
                    ->groupBy('work_orders.id')
                    ->orderBy('work_orders.due_date', 'ASC')
                    ->findAll();
    }

    /**
     * Get overdue work orders
     */
    public function getOverdueWorkOrders(): array
    {
        return $this->select('work_orders.*, 
                             GROUP_CONCAT(DISTINCT products.name SEPARATOR ", ") as product_names,
                             SUM(work_order_items.quantity_ordered) as total_quantity,
                             DATEDIFF(CURDATE(), work_orders.due_date) as days_overdue')
                    ->join('work_order_items', 'work_order_items.work_order_id = work_orders.id')
                    ->join('products', 'products.id = work_order_items.product_id')
                    ->where('work_orders.due_date <', date('Y-m-d'))
                    ->whereIn('work_orders.status', ['planned', 'in_progress'])
                    ->groupBy('work_orders.id')
                    ->orderBy('work_orders.due_date', 'ASC')
                    ->findAll();
    }

    /**
     * Get work orders by status
     */
    public function getWorkOrdersByStatus(string $status): array
    {
        return $this->select('work_orders.*, 
                             GROUP_CONCAT(DISTINCT products.name SEPARATOR ", ") as product_names, 
                             GROUP_CONCAT(DISTINCT products.code SEPARATOR ", ") as product_codes,
                             SUM(work_order_items.quantity_ordered) as total_quantity,
                             SUM(work_order_items.quantity_completed) as total_completed')
                    ->join('work_order_items', 'work_order_items.work_order_id = work_orders.id')
                    ->join('products', 'products.id = work_order_items.product_id')
                    ->where('work_orders.status', $status)
                    ->groupBy('work_orders.id')
                    ->orderBy('work_orders.due_date', 'ASC')
                    ->findAll();
    }

    /**
     * Update work order status
     */
    public function updateStatus(int $workOrderId, string $status): bool
    {
        $updateData = ['status' => $status];
        
        if ($status === 'completed') {
            // Update all work order items to completed
            $workOrderItemModel = new \App\Models\WorkOrderItemModel();
            $workOrderItems = $workOrderItemModel->where('work_order_id', $workOrderId)->findAll();
            
            foreach ($workOrderItems as $item) {
                $workOrderItemModel->update($item['id'], [
                    'quantity_completed' => $item['quantity_ordered']
                ]);
            }
        }

        return $this->update($workOrderId, $updateData);
    }

    /**
     * Create process runs for work order
     */
    public function createProcessRuns(int $workOrderId): bool
    {
        $workOrder = $this->find($workOrderId);
        if (!$workOrder) {
            return false;
        }

        // Get all products for this work order
        $workOrderItemModel = new \App\Models\WorkOrderItemModel();
        $workOrderItems = $workOrderItemModel->where('work_order_id', $workOrderId)->findAll();
        
        if (empty($workOrderItems)) {
            return false;
        }

        $processModel = new ProcessModel();
        $processRunModel = new WorkOrderProcessRunModel();
        $this->db->transStart();

        foreach ($workOrderItems as $item) {
            $processes = $processModel->getProcessesByProduct($item['product_id']);
            
            if (!empty($processes)) {
                foreach ($processes as $process) {
                    $processRunData = [
                        'work_order_id' => $workOrderId,
                        'process_id' => $process['id'],
                        'run_number' => 1,
                        'quantity_in' => $item['quantity_ordered'],
                        'quantity_pending' => $item['quantity_ordered'],
                        'status' => $process['sequence_order'] == 1 ? 'pending' : 'pending'
                    ];

                    $processRunModel->insert($processRunData);
                }
            }
        }

        $this->db->transComplete();
        return $this->db->transStatus();
    }

    /**
     * Get work order production summary
     */
    public function getProductionSummary(int $workOrderId): array
    {
        $builder = $this->db->table('work_order_process_runs wopr')
                           ->select('
                               processes.name as process_name,
                               processes.sequence_order,
                               wopr.quantity_in,
                               wopr.quantity_out,
                               wopr.quantity_scrap,
                               wopr.quantity_pending,
                               wopr.status,
                               wopr.started_at,
                               wopr.completed_at,
                               vendors.name as vendor_name
                           ')
                           ->join('processes', 'processes.id = wopr.process_id')
                           ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                           ->where('wopr.work_order_id', $workOrderId)
                           ->orderBy('processes.sequence_order', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Get work orders with filters and pagination
     */
    public function getWorkOrdersWithFilters($searchTerm = null, $statusFilter = null, $productFilter = null, $priorityFilter = null, $perPage = 20): array
    {
        $builder = $this->select('work_orders.*, 
                                 users.first_name as created_by_name,
                                 DATEDIFF(work_orders.due_date, CURDATE()) as days_to_due,
                                 GROUP_CONCAT(DISTINCT products.name SEPARATOR ", ") as product_names,
                                 GROUP_CONCAT(DISTINCT products.code SEPARATOR ", ") as product_codes,
                                 SUM(work_order_items.quantity_ordered) as total_quantity,
                                 SUM(work_order_items.quantity_completed) as total_completed')
                        ->join('work_order_items', 'work_order_items.work_order_id = work_orders.id')
                        ->join('products', 'products.id = work_order_items.product_id')
                        ->join('users', 'users.id = work_orders.created_by', 'left')
                        ->groupBy('work_orders.id');

        // Apply search filter
        if (!empty($searchTerm)) {
            $builder->groupStart()
                    ->like('work_orders.wo_number', $searchTerm)
                    ->orLike('work_orders.customer_name', $searchTerm)
                    ->orLike('products.name', $searchTerm)
                    ->orLike('products.code', $searchTerm)
                    ->groupEnd();
        }

        // Apply status filter
        if (!empty($statusFilter)) {
            $builder->where('work_orders.status', $statusFilter);
        }

        // Apply product filter
        if (!empty($productFilter)) {
            $builder->where('work_order_items.product_id', $productFilter);
        }

        // Apply priority filter
        if (!empty($priorityFilter)) {
            $builder->where('work_orders.priority', $priorityFilter);
        }

        $builder->orderBy('work_orders.created_at', 'DESC');

        return $builder->paginate($perPage);
    }

    /**
     * Search work orders
     */
    public function searchWorkOrders(string $query): array
    {
        return $this->select('work_orders.*, 
                             GROUP_CONCAT(DISTINCT products.name SEPARATOR ", ") as product_names, 
                             GROUP_CONCAT(DISTINCT products.code SEPARATOR ", ") as product_codes,
                             SUM(work_order_items.quantity_ordered) as total_quantity,
                             SUM(work_order_items.quantity_completed) as total_completed')
                    ->join('work_order_items', 'work_order_items.work_order_id = work_orders.id')
                    ->join('products', 'products.id = work_order_items.product_id')
                    ->groupStart()
                        ->like('work_orders.wo_number', $query)
                        ->orLike('work_orders.customer_name', $query)
                        ->orLike('products.name', $query)
                        ->orLike('products.code', $query)
                    ->groupEnd()
                    ->groupBy('work_orders.id')
                    ->orderBy('work_orders.created_at', 'DESC')
                    ->findAll();
    }
}
