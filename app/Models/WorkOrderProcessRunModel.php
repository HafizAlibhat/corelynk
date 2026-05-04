<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkOrderProcessRunModel extends Model
{
    protected $table            = 'work_order_process_runs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'work_order_id', 'process_id', 'run_number', 'quantity_in', 
        'quantity_out', 'quantity_scrap', 'quantity_pending', 'status', 
        'started_at', 'completed_at', 'operator_id', 'notes'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'work_order_id'   => 'required|integer|is_not_unique[work_orders.id]',
        'process_id'      => 'required|integer|is_not_unique[processes.id]',
        'run_number'      => 'required|integer|greater_than[0]',
        'quantity_in'     => 'required|integer|greater_than[0]',
        'quantity_out'    => 'permit_empty|integer|greater_than_equal_to[0]',
        'quantity_scrap'  => 'permit_empty|integer|greater_than_equal_to[0]',
        'quantity_pending'=> 'permit_empty|integer|greater_than_equal_to[0]',
        'status'          => 'required|in_list[pending,in_progress,completed,on_hold,cancelled]'
    ];

    protected $validationMessages = [
        'work_order_id' => [
            'required'       => 'Work Order is required',
            'is_not_unique'  => 'Selected work order does not exist'
        ],
        'process_id' => [
            'required'       => 'Process is required',
            'is_not_unique'  => 'Selected process does not exist'
        ],
        'quantity_in' => [
            'required'      => 'Input quantity is required',
            'greater_than'  => 'Input quantity must be greater than 0'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeUpdate   = ['validateQuantities'];

    /**
     * Validate quantities before update
     */
    protected function validateQuantities(array $data)
    {
        if (isset($data['data'])) {
            $qtyOut = $data['data']['quantity_out'] ?? 0;
            $qtyScrap = $data['data']['quantity_scrap'] ?? 0;
            $qtyPending = $data['data']['quantity_pending'] ?? 0;
            $qtyIn = $data['data']['quantity_in'] ?? 0;

            // Get current record if updating
            if (isset($data['id'])) {
                $currentRecord = $this->find($data['id'][0]);
                if ($currentRecord) {
                    $qtyIn = $currentRecord['quantity_in'];
                }
            }

            if (($qtyOut + $qtyScrap + $qtyPending) > $qtyIn) {
                throw new \InvalidArgumentException('Total output quantities cannot exceed input quantity');
            }
        }

        return $data;
    }

    /**
     * Get process runs by work order
     */
    public function getProcessRunsByWorkOrder(int $workOrderId): array
    {
        return $this->select('work_order_process_runs.*, 
                             processes.name as process_name,
                             processes.is_vendor_process,
                             vendors.name as vendor_name,
                             users.first_name as operator_name')
                    ->join('processes', 'processes.id = work_order_process_runs.process_id')
                    ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                    ->join('users', 'users.id = work_order_process_runs.operator_id', 'left')
                    ->where('work_order_process_runs.work_order_id', $workOrderId)
                    ->orderBy('work_order_process_runs.id', 'ASC')
                    ->orderBy('work_order_process_runs.run_number', 'ASC')
                    ->findAll();
    }

    /**
     * Get process runs for a work order (alias for getProcessRunsByWorkOrder)
     */
    public function getProcessRunsForWorkOrder(int $workOrderId): array
    {
        return $this->getProcessRunsByWorkOrder($workOrderId);
    }

    /**
     * Get process run with complete details
     */
    public function getProcessRunWithDetails(int $processRunId): array|null
    {
        $processRun = $this->select('work_order_process_runs.*, 
                                    work_orders.wo_number,
                                    work_orders.customer_name,
                                    products.name as product_name,
                                    products.code as product_code,
                                    processes.name as process_name,
                                    processes.is_vendor_process,
                                    vendors.name as vendor_name,
                                    users.first_name as operator_name')
                            ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                            ->join('products', 'products.id = work_orders.product_id')
                            ->join('processes', 'processes.id = work_order_process_runs.process_id')
                            ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                            ->join('users', 'users.id = work_order_process_runs.operator_id', 'left')
                            ->where('work_order_process_runs.id', $processRunId)
                            ->first();

        if (!$processRun) {
            return null;
        }

    // Get QC records
        $qcModel = new QcRecordModel();
        $processRun['qc_records'] = $qcModel->getQcRecordsByProcessRun($processRunId);

        // Get vendor gatepasses if vendor process
        if ($processRun['is_vendor_process']) {
            $gatepassModel = new VendorGatepassModel();
            $processRun['gatepasses'] = $gatepassModel->getGatepassesByProcessRun($processRunId);
        }

        // Get scrap records
        $scrapModel = new ScrapRecordModel();
        $processRun['scrap_records'] = $scrapModel->getScrapRecordsByProcessRun($processRunId);

        return $processRun;
    }

    /**
     * Start process run
     */
    public function startProcessRun(int $processRunId, int $operatorId): bool
    {
        return $this->update($processRunId, [
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
            'operator_id' => $operatorId
        ]);
    }

    /**
     * Complete process run
     */
    public function completeProcessRun(int $processRunId, array $quantities): bool
    {
        $updateData = [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'quantity_out' => $quantities['quantity_out'],
            'quantity_scrap' => $quantities['quantity_scrap'] ?? 0,
            'quantity_pending' => 0
        ];

        $result = $this->update($processRunId, $updateData);

        if ($result) {
            // Create next process run if there's output and next process exists
            $this->createNextProcessRun($processRunId, $quantities['quantity_out']);
        }

        return $result;
    }

    /**
     * Create next process run
     */
    protected function createNextProcessRun(int $currentProcessRunId, int $outputQuantity): void
    {
        if ($outputQuantity <= 0) {
            return;
        }

        $currentRun = $this->getProcessRunWithDetails($currentProcessRunId);
        if (!$currentRun) {
            return;
        }

        // Find next process (simplified - just get the next process for the same product)
        $processModel = new ProcessModel();
        $nextProcess = $processModel->where('product_id', $currentRun['product_id'])
                                   ->where('is_active', true)
                                   ->where('id >', $currentRun['process_id'])
                                   ->orderBy('id', 'ASC')
                                   ->first();

        if ($nextProcess) {
            $nextRunData = [
                'work_order_id' => $currentRun['work_order_id'],
                'process_id' => $nextProcess['id'],
                'run_number' => 1,
                'quantity_in' => $outputQuantity,
                'quantity_pending' => $outputQuantity,
                'status' => 'pending'
            ];

            $this->insert($nextRunData);
        }
    }

    /**
     * Get pending process runs for vendor
     */
    public function getPendingRunsForVendor(int $vendorId): array
    {
        return $this->select('work_order_process_runs.*, 
                             work_orders.wo_number,
                             work_orders.customer_name,
                             work_orders.due_date,
                             products.name as product_name,
                             products.code as product_code,
                             processes.name as process_name')
                    ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                    ->join('products', 'products.id = work_orders.product_id')
                    ->join('processes', 'processes.id = work_order_process_runs.process_id')
                    ->where('processes.vendor_id', $vendorId)
                    ->where('processes.is_vendor_process', true)
                    ->whereIn('work_order_process_runs.status', ['pending', 'in_progress'])
                    ->orderBy('work_orders.due_date', 'ASC')
                    ->findAll();
    }

    /**
     * Get process runs requiring QC
     */
    public function getRunsRequiringQc(): array
    {
    return $this->select('work_order_process_runs.*, 
                 work_orders.wo_number,
                 products.name as product_name,
                 processes.name as process_name')
                    ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                    ->join('products', 'products.id = work_orders.product_id')
                    ->join('processes', 'processes.id = work_order_process_runs.process_id')
                    ->join('qc_records', 'qc_records.process_run_id = work_order_process_runs.id', 'left')
                    ->where('work_order_process_runs.status', 'completed')
                    ->where('qc_records.id IS NULL')
                    ->orderBy('work_order_process_runs.completed_at', 'ASC')
                    ->findAll();
    }

    /**
     * Get production efficiency report
     */
    public function getProductionEfficiency(string $startDate = null, string $endDate = null): array
    {
        $builder = $this->select('
                            processes.name as process_name,
                            COUNT(work_order_process_runs.id) as total_runs,
                            AVG(TIMESTAMPDIFF(MINUTE, work_order_process_runs.started_at, work_order_process_runs.completed_at)) as avg_actual_time,
                            AVG(processes.standard_time_minutes) as avg_standard_time,
                            SUM(work_order_process_runs.quantity_in) as total_input,
                            SUM(work_order_process_runs.quantity_out) as total_output,
                            SUM(work_order_process_runs.quantity_scrap) as total_scrap,
                            ROUND((SUM(work_order_process_runs.quantity_out) / SUM(work_order_process_runs.quantity_in)) * 100, 2) as yield_percentage
                        ')
                        ->join('processes', 'processes.id = work_order_process_runs.process_id')
                        ->where('work_order_process_runs.status', 'completed')
                        ->where('work_order_process_runs.started_at IS NOT NULL')
                        ->where('work_order_process_runs.completed_at IS NOT NULL');

        if ($startDate) {
            $builder->where('work_order_process_runs.completed_at >=', $startDate);
        }

        if ($endDate) {
            $builder->where('work_order_process_runs.completed_at <=', $endDate);
        }

        return $builder->groupBy('processes.id')
                       ->orderBy('processes.name', 'ASC')
                       ->findAll();
    }
}
