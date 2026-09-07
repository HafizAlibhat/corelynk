<?php

namespace App\Models;

use CodeIgniter\Model;

class VendorGatepassModel extends Model
{
    protected $table            = 'vendor_gatepasses';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'gatepass_number', 'process_run_id', 'vendor_id', 'type', 
        'quantity_sent', 'quantity_received', 'quantity_pending', 'quantity_scrap',
        'dispatch_date', 'return_date', 'vendor_acknowledgment', 'vendor_ack_date',
        'notes', 'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'gatepass_number' => 'required|min_length[3]|max_length[50]|is_unique[vendor_gatepasses.gatepass_number,id,{id}]',
        'process_run_id'  => 'required|integer|is_not_unique[work_order_process_runs.id]',
        'vendor_id'       => 'required|integer|is_not_unique[vendors.id]',
        'type'            => 'required|in_list[out,in]',
        'quantity_sent'   => 'permit_empty|integer|greater_than_equal_to[0]',
        'quantity_received' => 'permit_empty|integer|greater_than_equal_to[0]',
        'dispatch_date'   => 'permit_empty|valid_date[Y-m-d]',
        'return_date'     => 'permit_empty|valid_date[Y-m-d]'
    ];

    protected $validationMessages = [
        'gatepass_number' => [
            'required'    => 'Gatepass number is required',
            'is_unique'   => 'Gatepass number already exists'
        ],
        'process_run_id' => [
            'required'       => 'Process run is required',
            'is_not_unique'  => 'Selected process run does not exist'
        ],
        'vendor_id' => [
            'required'       => 'Vendor is required',
            'is_not_unique'  => 'Selected vendor does not exist'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['generateGatepassNumber'];

    /**
     * Generate gatepass number if not provided
     */
    protected function generateGatepassNumber(array $data)
    {
        if (empty($data['data']['gatepass_number'])) {
            $prefix = 'GP-' . date('Y') . '-';
            $lastGp = $this->like('gatepass_number', $prefix, 'after')
                          ->orderBy('id', 'DESC')
                          ->first();

            if ($lastGp) {
                $lastNumber = intval(substr($lastGp['gatepass_number'], strlen($prefix)));
                $newNumber = $lastNumber + 1;
            } else {
                $newNumber = 1;
            }

            $data['data']['gatepass_number'] = $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
        }

        return $data;
    }

    /**
     * Get gatepasses by process run
     */
    public function getGatepassesByProcessRun(int $processRunId): array
    {
        return $this->select('vendor_gatepasses.*, vendors.name as vendor_name')
                    ->join('vendors', 'vendors.id = vendor_gatepasses.vendor_id')
                    ->where('vendor_gatepasses.process_run_id', $processRunId)
                    ->orderBy('vendor_gatepasses.created_at', 'ASC')
                    ->findAll();
    }

    /**
     * Get pending gatepasses by vendor
     */
    public function getPendingGatepassesByVendor(int $vendorId): array
    {
        return $this->select('vendor_gatepasses.*, 
                             work_orders.wo_number,
                             products.name as product_name,
                             processes.name as process_name')
                    ->join('work_order_process_runs', 'work_order_process_runs.id = vendor_gatepasses.process_run_id')
                    ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                    ->join('products', 'products.id = work_orders.product_id')
                    ->join('processes', 'processes.id = work_order_process_runs.process_id')
                    ->where('vendor_gatepasses.vendor_id', $vendorId)
                    ->where('vendor_gatepasses.type', 'out')
                    ->where('vendor_gatepasses.return_date IS NULL')
                    ->orderBy('vendor_gatepasses.dispatch_date', 'ASC')
                    ->findAll();
    }

    /**
     * Get gatepasses with details
     */
    public function getGatepassesWithDetails(): array
    {
        return $this->select('vendor_gatepasses.*, 
                             vendors.name as vendor_name,
                             work_orders.wo_number,
                             products.name as product_name,
                             processes.name as process_name,
                             users.first_name as created_by_name')
                    ->join('vendors', 'vendors.id = vendor_gatepasses.vendor_id')
                    ->join('work_order_process_runs', 'work_order_process_runs.id = vendor_gatepasses.process_run_id')
                    ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                    ->join('products', 'products.id = work_orders.product_id')
                    ->join('processes', 'processes.id = work_order_process_runs.process_id')
                    ->join('users', 'users.id = vendor_gatepasses.created_by', 'left')
                    ->orderBy('vendor_gatepasses.created_at', 'DESC')
                    ->findAll();
    }

    /**
     * Create OUT gatepass
     */
    public function createOutGatepass(int $processRunId, int $vendorId, int $quantity, string $dispatchDate, int $createdBy): int|false
    {
        $data = [
            'process_run_id' => $processRunId,
            'vendor_id' => $vendorId,
            'type' => 'out',
            'quantity_sent' => $quantity,
            'quantity_pending' => $quantity,
            'dispatch_date' => $dispatchDate,
            'created_by' => $createdBy
        ];

        return $this->insert($data);
    }

    /**
     * Create IN gatepass (return from vendor)
     */
    public function createInGatepass(int $outGatepassId, int $quantityReceived, int $quantityScrap, string $returnDate, int $createdBy): bool
    {
        $outGatepass = $this->find($outGatepassId);
        if (!$outGatepass || $outGatepass['type'] !== 'out') {
            return false;
        }

        $this->db->transStart();

        // Create IN gatepass
        $inData = [
            'process_run_id' => $outGatepass['process_run_id'],
            'vendor_id' => $outGatepass['vendor_id'],
            'type' => 'in',
            'quantity_received' => $quantityReceived,
            'quantity_scrap' => $quantityScrap,
            'return_date' => $returnDate,
            'created_by' => $createdBy
        ];

        $this->insert($inData);

        // Update OUT gatepass
        $this->update($outGatepassId, [
            'quantity_received' => $quantityReceived,
            'quantity_scrap' => $quantityScrap,
            'quantity_pending' => $outGatepass['quantity_sent'] - $quantityReceived - $quantityScrap,
            'return_date' => $returnDate
        ]);

        $this->db->transComplete();
        return $this->db->transStatus();
    }

    /**
     * Mark vendor acknowledgment
     */
    public function markVendorAcknowledgment(int $gatepassId): bool
    {
        return $this->update($gatepassId, [
            'vendor_acknowledgment' => true,
            'vendor_ack_date' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get overdue gatepasses
     */
    public function getOverdueGatepasses(int $daysOverdue = 7): array
    {
        return $this->select('vendor_gatepasses.*, 
                             vendors.name as vendor_name,
                             work_orders.wo_number,
                             products.name as product_name,
                             processes.name as process_name,
                             DATEDIFF(CURDATE(), vendor_gatepasses.dispatch_date) as days_pending')
                    ->join('vendors', 'vendors.id = vendor_gatepasses.vendor_id')
                    ->join('work_order_process_runs', 'work_order_process_runs.id = vendor_gatepasses.process_run_id')
                    ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                    ->join('products', 'products.id = work_orders.product_id')
                    ->join('processes', 'processes.id = work_order_process_runs.process_id')
                    ->where('vendor_gatepasses.type', 'out')
                    ->where('vendor_gatepasses.return_date IS NULL')
                    ->where('DATEDIFF(CURDATE(), vendor_gatepasses.dispatch_date) >=', $daysOverdue)
                    ->orderBy('vendor_gatepasses.dispatch_date', 'ASC')
                    ->findAll();
    }

    /**
     * Get vendor turnaround statistics
     */
    public function getVendorTurnaroundStats(int $vendorId): array
    {
        $builder = $this->select('
                            COUNT(CASE WHEN type = "out" THEN 1 END) as total_dispatched,
                            COUNT(CASE WHEN type = "in" THEN 1 END) as total_returned,
                            AVG(CASE WHEN type = "in" AND return_date IS NOT NULL 
                                THEN DATEDIFF(return_date, dispatch_date) END) as avg_turnaround_days,
                            SUM(CASE WHEN type = "out" THEN quantity_sent ELSE 0 END) as total_qty_sent,
                            SUM(CASE WHEN type = "in" THEN quantity_received ELSE 0 END) as total_qty_received,
                            SUM(CASE WHEN type = "in" THEN quantity_scrap ELSE 0 END) as total_qty_scrap
                        ')
                        ->where('vendor_id', $vendorId);

        $result = $builder->get()->getRowArray();

        if ($result && $result['total_qty_sent'] > 0) {
            $result['yield_percentage'] = round(($result['total_qty_received'] / $result['total_qty_sent']) * 100, 2);
        } else {
            $result['yield_percentage'] = 0;
        }

        return $result ?: [
            'total_dispatched' => 0,
            'total_returned' => 0,
            'avg_turnaround_days' => 0,
            'total_qty_sent' => 0,
            'total_qty_received' => 0,
            'total_qty_scrap' => 0,
            'yield_percentage' => 0
        ];
    }

    /**
     * Search gatepasses
     */
    public function searchGatepasses(string $query): array
    {
        return $this->select('vendor_gatepasses.*, 
                             vendors.name as vendor_name,
                             work_orders.wo_number,
                             products.name as product_name')
                    ->join('vendors', 'vendors.id = vendor_gatepasses.vendor_id')
                    ->join('work_order_process_runs', 'work_order_process_runs.id = vendor_gatepasses.process_run_id')
                    ->join('work_orders', 'work_orders.id = work_order_process_runs.work_order_id')
                    ->join('products', 'products.id = work_orders.product_id')
                    ->groupStart()
                        ->like('vendor_gatepasses.gatepass_number', $query)
                        ->orLike('work_orders.wo_number', $query)
                        ->orLike('vendors.name', $query)
                        ->orLike('products.name', $query)
                    ->groupEnd()
                    ->orderBy('vendor_gatepasses.created_at', 'DESC')
                    ->findAll();
    }
}
