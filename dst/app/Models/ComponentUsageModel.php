<?php

namespace App\Models;

use CodeIgniter\Model;

class ComponentUsageModel extends Model
{
    protected $table            = 'component_usage';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'work_order_id', 'component_id', 'quantity_required', 
        'quantity_used', 'quantity_remaining', 'issued_by', 'issued_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'work_order_id'      => 'required|integer|is_not_unique[work_orders.id]',
        'component_id'       => 'required|integer|is_not_unique[components.id]',
        'quantity_required'  => 'required|decimal|greater_than[0]',
        'quantity_used'      => 'permit_empty|decimal|greater_than_equal_to[0]',
        'quantity_remaining' => 'permit_empty|decimal|greater_than_equal_to[0]'
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    /**
     * Get component usage by work order
     */
    public function getUsageByWorkOrder(int $workOrderId): array
    {
        return $this->select('component_usage.*, 
                             components.name as component_name,
                             components.code as component_code,
                             components.unit,
                             components.current_stock,
                             users.first_name as issued_by_name')
                    ->join('components', 'components.id = component_usage.component_id')
                    ->join('users', 'users.id = component_usage.issued_by', 'left')
                    ->where('component_usage.work_order_id', $workOrderId)
                    ->orderBy('components.name', 'ASC')
                    ->findAll();
    }

    /**
     * Issue components to work order
     */
    public function issueComponents(int $workOrderId, int $componentId, float $quantityToIssue, int $issuedBy): bool
    {
        $usage = $this->where('work_order_id', $workOrderId)
                     ->where('component_id', $componentId)
                     ->first();

        if (!$usage) {
            return false;
        }

        $componentModel = new ComponentModel();
        $component = $componentModel->find($componentId);
        
        if (!$component || $component['current_stock'] < $quantityToIssue) {
            return false;
        }

        $this->db->transStart();

        // Update usage record
        $newQuantityUsed = $usage['quantity_used'] + $quantityToIssue;
        $newQuantityRemaining = $usage['quantity_required'] - $newQuantityUsed;

        $updateData = [
            'quantity_used' => $newQuantityUsed,
            'quantity_remaining' => max(0, $newQuantityRemaining),
            'issued_by' => $issuedBy,
            'issued_at' => date('Y-m-d H:i:s')
        ];

        $this->update($usage['id'], $updateData);

        // Update component stock
        $componentModel->updateStock(
            $componentId, 
            $quantityToIssue, 
            'out', 
            'work_order', 
            $workOrderId, 
            $issuedBy
        );

        $this->db->transComplete();
        return $this->db->transStatus();
    }
}

class ComponentStockTransactionModel extends Model
{
    protected $table            = 'component_stock_transactions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'component_id', 'transaction_type', 'quantity', 'unit_cost',
        'reference_type', 'reference_id', 'notes', 'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';

    /**
     * Get transactions by component
     */
    public function getTransactionsByComponent(int $componentId, int $limit = 50): array
    {
        return $this->select('component_stock_transactions.*, users.first_name as created_by_name')
                    ->join('users', 'users.id = component_stock_transactions.created_by', 'left')
                    ->where('component_stock_transactions.component_id', $componentId)
                    ->orderBy('component_stock_transactions.created_at', 'DESC')
                    ->limit($limit)
                    ->findAll();
    }
}

class ReworkRecordModel extends Model
{
    protected $table            = 'rework_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'qc_record_id', 'original_process_run_id', 'rework_process_run_id',
        'quantity_reworked', 'reason', 'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}

class ScrapRecordModel extends Model
{
    protected $table            = 'scrap_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'process_run_id', 'quantity_scrapped', 'reason', 'estimated_cost',
        'actual_cost', 'recorded_by', 'recorded_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get scrap records by process run
     */
    public function getScrapRecordsByProcessRun(int $processRunId): array
    {
        return $this->select('scrap_records.*, users.first_name as recorded_by_name')
                    ->join('users', 'users.id = scrap_records.recorded_by', 'left')
                    ->where('scrap_records.process_run_id', $processRunId)
                    ->orderBy('scrap_records.recorded_at', 'DESC')
                    ->findAll();
    }

    /**
     * Get scrap summary
     */
    public function getScrapSummary(string $startDate = null, string $endDate = null): array
    {
        $builder = $this->select('
                        SUM(quantity_scrapped) as total_quantity_scrapped,
                        SUM(actual_cost) as total_scrap_cost,
                        COUNT(*) as total_scrap_incidents,
                        AVG(actual_cost) as avg_cost_per_incident
                    ');

        if ($startDate) {
            $builder->where('recorded_at >=', $startDate);
        }

        if ($endDate) {
            $builder->where('recorded_at <=', $endDate);
        }

        $result = $builder->get()->getRowArray();

        return $result ?: [
            'total_quantity_scrapped' => 0,
            'total_scrap_cost' => 0,
            'total_scrap_incidents' => 0,
            'avg_cost_per_incident' => 0
        ];
    }

    /**
     * Get component usage for a work order (alias for getUsageByWorkOrder)
     */
    public function getComponentUsageForWorkOrder(int $workOrderId): array
    {
        return $this->getUsageByWorkOrder($workOrderId);
    }
}
