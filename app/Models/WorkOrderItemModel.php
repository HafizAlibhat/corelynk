<?php

namespace App\Models;

use CodeIgniter\Model;

class WorkOrderItemModel extends Model
{
    protected $table = 'work_order_items';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'work_order_id',
        'product_id', 
        'quantity_ordered',
        'quantity_completed'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'work_order_id' => 'required|integer',
        'product_id' => 'required|integer',
        'quantity_ordered' => 'required|integer|greater_than[0]',
        'quantity_completed' => 'permit_empty|integer|greater_than_equal_to[0]'
    ];

    protected $validationMessages = [
        'work_order_id' => [
            'required' => 'Work order ID is required',
            'integer' => 'Work order ID must be a valid number'
        ],
        'product_id' => [
            'required' => 'Product is required',
            'integer' => 'Product ID must be a valid number'
        ],
        'quantity_ordered' => [
            'required' => 'Quantity is required',
            'integer' => 'Quantity must be a valid number',
            'greater_than' => 'Quantity must be greater than 0'
        ],
        'quantity_completed' => [
            'integer' => 'Completed quantity must be a valid number',
            'greater_than_equal_to' => 'Completed quantity cannot be negative'
        ]
    ];

    // Skip validation
    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Set default values before insert
     */
    protected function setDefaults(array $data)
    {
        if (!isset($data['data']['quantity_completed']) || $data['data']['quantity_completed'] === '') {
            $data['data']['quantity_completed'] = 0;
        }
        return $data;
    }

    /**
     * Get work order items with product details
     */
    public function getWorkOrderItemsWithDetails($workOrderId)
    {
        // Note: some installations don't have a products.images column.
        // Avoid selecting it directly to prevent SQL errors.
        return $this->select('work_order_items.*, products.name as product_name, products.code as product_code, products.unit')
                    ->join('products', 'products.id = work_order_items.product_id')
                    ->where('work_order_items.work_order_id', $workOrderId)
                    ->findAll();
    }

    /**
     * Get total quantity for a work order
     */
    public function getTotalQuantityByWorkOrder($workOrderId)
    {
        $result = $this->selectSum('quantity_ordered')
                       ->where('work_order_id', $workOrderId)
                       ->first();
        
        return $result['quantity_ordered'] ?? 0;
    }

    /**
     * Get total completed quantity for a work order
     */
    public function getTotalCompletedByWorkOrder($workOrderId)
    {
        $result = $this->selectSum('quantity_completed')
                       ->where('work_order_id', $workOrderId)
                       ->first();
        
        return $result['quantity_completed'] ?? 0;
    }

    /**
     * Update completed quantity for a specific item
     */
    public function updateCompletedQuantity($workOrderId, $productId, $quantity)
    {
        return $this->where([
            'work_order_id' => $workOrderId,
            'product_id' => $productId
        ])->set('quantity_completed', $quantity)->update();
    }

    /**
     * Delete all items for a work order
     */
    public function deleteByWorkOrder($workOrderId)
    {
        return $this->where('work_order_id', $workOrderId)->delete();
    }

    /**
     * Get processes for a work order item
     */
    public function getItemProcesses($itemId)
    {
        return $this->db->table('work_order_items woi')
            ->select('p.id, p.name, p.description, p.is_vendor_process')
            ->join('product_processes pp', 'pp.product_id = woi.product_id')
            ->join('processes p', 'p.id = pp.process_id')
            ->where('woi.id', $itemId)
            ->orderBy('p.name', 'ASC')
            ->get()
            ->getResultArray();
    }
}
