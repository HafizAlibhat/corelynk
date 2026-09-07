<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessBatchLogModel extends Model
{
    protected $table = 'process_batch_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    // Add new enhanced fields to allowedFields while keeping legacy names for backward-compatibility
    protected $allowedFields = [
        'process_batch_id', 'batch_id', 'log_date', 'log_type',
        // legacy fields
        'accepted_qty', 'repaired_qty', 'rejected_qty',
        // enhanced fields
        'qty_received', 'qty_completed', 'qty_rejected', 'qty_scrapped', 'qty_for_repair',
        // assignee fields
        'employee_id', 'vendor_id',
        'operator_id', 'notes', 'created_by',
        // allow explicit timestamp so we can capture log time
        'created_at'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    // process_batch_logs table does not have an `updated_at` column, avoid writing it
    protected $updatedField  = '';

    public function getLogsForBatch(int $batchId): array
    {
        // Support both batch_id and process_batch_id columns for compatibility
        $query = $this->asArray()->orderBy('log_date', 'ASC');
        
        // Check which column exists in the table
        $columns = $this->db->getFieldNames($this->table);
        if (in_array('batch_id', $columns)) {
            $query->where('batch_id', $batchId);
        } else {
            $query->where('process_batch_id', $batchId);
        }
        
        return $query->findAll();
    }

    /**
     * Get logs for batch with employee details
     */
    public function getLogsForBatchWithDetails(int $batchId): array
    {
        $builder = $this->db->table($this->table . ' pbl')
            ->select('pbl.*, e.name as employee_name, e.employee_code, u.name as created_by_name')
            ->join('employees e', 'e.id = pbl.employee_id', 'left')
            ->join('users u', 'u.id = pbl.created_by', 'left')
            ->orderBy('pbl.log_date', 'ASC');

        // Check which column exists for batch reference
        $columns = $this->db->getFieldNames($this->table);
        if (in_array('batch_id', $columns)) {
            $builder->where('pbl.batch_id', $batchId);
        } else {
            $builder->where('pbl.process_batch_id', $batchId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Get daily summary for a date range
     */
    public function getDailySummary($startDate, $endDate, $filters = [])
    {
        $builder = $this->db->table($this->table . ' pbl')
            ->select('pbl.log_date,
                      SUM(pbl.qty_received) as total_received,
                      SUM(pbl.qty_completed) as total_completed,
                      SUM(pbl.qty_rejected) as total_rejected,
                      SUM(pbl.qty_for_repair) as total_repair,
                      COUNT(DISTINCT pbl.batch_id) as active_batches')
            ->where('pbl.log_date >=', $startDate)
            ->where('pbl.log_date <=', $endDate)
            ->groupBy('pbl.log_date')
            ->orderBy('pbl.log_date', 'ASC');

        // Apply filters if provided
        if (!empty($filters['work_order_id'])) {
            $builder->join('process_batches pb', 'pb.id = pbl.batch_id')
                    ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
                    ->where('woi.work_order_id', $filters['work_order_id']);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Get efficiency report data
     */
    public function getEfficiencyReport($filters = [])
    {
        $builder = $this->db->table($this->table . ' pbl')
            ->select('pb.batch_code, p.name as product_name, pr.name as process_name,
                      pb.planned_qty,
                      SUM(pbl.qty_received) as total_received,
                      SUM(pbl.qty_completed) as total_completed,
                      SUM(pbl.qty_rejected) as total_rejected,
                      ROUND((SUM(pbl.qty_completed) / NULLIF(SUM(pbl.qty_received), 0)) * 100, 2) as efficiency_percent')
            ->join('process_batches pb', 'pb.id = pbl.batch_id')
            ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
            ->join('products p', 'p.id = woi.product_id')
            ->join('processes pr', 'pr.id = pb.process_id')
            ->groupBy('pb.id, pb.batch_code, p.name, pr.name, pb.planned_qty')
            ->orderBy('efficiency_percent', 'DESC');

        // Apply filters
        if (!empty($filters['start_date'])) {
            $builder->where('pbl.log_date >=', $filters['start_date']);
        }
        if (!empty($filters['end_date'])) {
            $builder->where('pbl.log_date <=', $filters['end_date']);
        }

        return $builder->get()->getResultArray();
    }
}
