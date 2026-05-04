<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessBatchReleaseModel extends Model
{
    protected $table = 'process_batch_releases';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'process_batch_id',
        'released_qty',
        'released_by',
        'released_at',
        'gatepass_number',
        'notes'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';

    // Validation
    protected $validationRules = [
        'process_batch_id' => 'required|integer',
        'released_qty' => 'required|decimal',
        'released_by' => 'permit_empty|integer',
        'gatepass_number' => 'permit_empty|max_length[50]',
        'notes' => 'permit_empty|max_length[1000]'
    ];

    protected $validationMessages = [
        'process_batch_id' => [
            'required' => 'Process batch ID is required',
            'integer' => 'Process batch ID must be a valid number'
        ],
        'released_qty' => [
            'required' => 'Released quantity is required',
            'decimal' => 'Released quantity must be a valid number'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateGatepassNumber'];
    protected $beforeUpdate = [];
    protected $afterInsert = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];

    /**
     * Generate a unique gatepass number before insert
     */
    protected function generateGatepassNumber(array $data)
    {
        if (!isset($data['data']['gatepass_number']) || empty($data['data']['gatepass_number'])) {
            $data['data']['gatepass_number'] = 'GP' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        }
        return $data;
    }

    /**
     * Get release details with batch and product information
     */
    public function getReleaseWithDetails($releaseId)
    {
        return $this->select('
                process_batch_releases.*,
                process_batches.batch_code,
                process_batches.planned_qty,
                process_batches.status as batch_status,
                work_order_items.quantity_ordered,
                products.name as product_name,
                products.code as product_code,
                work_orders.wo_number,
                work_orders.customer_name,
                users.first_name,
                users.last_name
            ')
            ->join('process_batches', 'process_batches.id = process_batch_releases.process_batch_id')
            ->join('work_order_items', 'work_order_items.id = process_batches.work_order_item_id')
            ->join('products', 'products.id = work_order_items.product_id')
            ->join('work_orders', 'work_orders.id = work_order_items.work_order_id')
            ->join('users', 'users.id = process_batch_releases.released_by', 'left')
            ->where('process_batch_releases.id', $releaseId)
            ->first();
    }

    /**
     * Get all releases for a specific batch
     */
    public function getBatchReleases($batchId)
    {
        return $this->where('process_batch_id', $batchId)
                   ->orderBy('released_at', 'DESC')
                   ->findAll();
    }

    /**
     * Get total released quantity for a batch
     */
    public function getTotalReleasedQty($batchId)
    {
        $result = $this->selectSum('released_qty')
                      ->where('process_batch_id', $batchId)
                      ->first();
        
        return $result['released_qty'] ?? 0;
    }

    /**
     * Check if a gatepass number already exists
     */
    public function gatepassExists($gatepassNumber)
    {
        return $this->where('gatepass_number', $gatepassNumber)->countAllResults() > 0;
    }
}
