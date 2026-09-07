<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessBatchModel extends Model
{
    protected $table = 'process_batches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    // Include legacy `batch_number` so inserts that map batch_code->batch_number are allowed
    protected $allowedFields = ['work_order_item_id', 'process_id', 'batch_code', 'batch_number', 'planned_qty', 'started_at', 'completed_at', 'status', 'created_by'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get batch totals (accepted/repaired/rejected) by summing logs.
     */
    public function getBatchTotals(int $batchId): array
    {
        $db = \Config\Database::connect();
        $cols = [];

        // Inspect columns of process_batch_logs once
        $res = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
        foreach ($res as $r) {
            $cols[] = $r['Field'];
        }

    $builder = $db->table('process_batch_logs');
        // Prefer enhanced qty_* columns when present
        if (in_array('qty_completed', $cols, true)) {
            $builder->select('SUM(qty_completed) as total_accepted, SUM(qty_rejected) as total_rejected, SUM(qty_scrapped) as total_scrapped');
        } elseif (in_array('accepted_qty', $cols, true) && in_array('repaired_qty', $cols, true)) {
            // legacy schema: treat accepted + repaired as accepted equivalent
            $builder->select('SUM(accepted_qty) as total_accepted, SUM(repaired_qty) as total_repaired, SUM(rejected_qty) as total_rejected');
        } else {
            // unknown schema, return zeros safely
            return ['accepted' => 0.0, 'repaired' => 0.0, 'rejected' => 0.0];
        }

    // Use the appropriate foreign-key column name in process_batch_logs (schema may vary)
    $logBatchCol = $this->detectProcessBatchLogColumn($db);
    $builder->where($logBatchCol, $batchId);
        $row = $builder->get()->getRowArray() ?? [];

        return [
            'accepted' => (float) ($row['total_accepted'] ?? 0),
            'repaired' => (float) ($row['total_repaired'] ?? 0),
            'rejected' => (float) ($row['total_rejected'] ?? 0),
        ];
    }

    /**
     * Return list of columns present in process_batch_logs table.
     */
    public static function getLogColumns(): array
    {
        $db = \Config\Database::connect();
        $res = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
        $cols = [];
        foreach ($res as $r) {
            $cols[] = $r['Field'];
        }
        return $cols;
    }

    /**
     * Detect whether process_batch_logs references the batch as `batch_id` or `process_batch_id`.
     * Returns the column name to use in queries.
     */
    protected function detectProcessBatchLogColumn($db = null): string
    {
        $db = $db ?? \Config\Database::connect();
        try {
            $res = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
        } catch (\Throwable $e) {
            // If table is missing or query fails, default to a safe column name that may exist in some schemas
            return 'process_batch_id';
        }

        $fields = array_map(fn($r) => $r['Field'], $res);
        if (in_array('batch_id', $fields, true)) {
            return 'batch_id';
        }
        if (in_array('process_batch_id', $fields, true)) {
            return 'process_batch_id';
        }

        // Fallback
        return $fields[0] ?? 'process_batch_id';
    }

    /**
     * Detect which column in products should be treated as the product code.
     * Returns an SQL fragment like `p.code as product_code` or `'' as product_code`.
     */
    protected function productCodeSelectExpr()
    {
        $db = \Config\Database::connect();
        try {
            $res = $db->query("SHOW COLUMNS FROM products")->getResultArray();
        } catch (\Throwable $e) {
            return "'' as product_code";
        }
        $fields = array_map(fn($r) => $r['Field'], $res);
        if (in_array('product_code', $fields, true)) {
            return 'COALESCE(p.product_code, "") as product_code';
        }
        if (in_array('code', $fields, true)) {
            return 'COALESCE(p.code, "") as product_code';
        }
        return "'' as product_code";
    }

    /**
     * Detect which column in users table should be used for display name.
     * Returns an SQL fragment like `COALESCE(u.name, u.username, '') as created_by_name`.
     */
    protected function userNameSelectExpr()
    {
        $db = \Config\Database::connect();
        try {
            $res = $db->query("SHOW COLUMNS FROM users")->getResultArray();
        } catch (\Throwable $e) {
            return "COALESCE(u.username, '') as created_by_name";
        }
        $fields = array_map(fn($r) => $r['Field'], $res);
        if (in_array('name', $fields, true) && in_array('username', $fields, true)) {
            return "COALESCE(u.name, u.username, '') as created_by_name";
        }
        if (in_array('username', $fields, true)) {
            return "COALESCE(u.username, '') as created_by_name";
        }
        if (in_array('name', $fields, true)) {
            return "COALESCE(u.name, '') as created_by_name";
        }
        return "'' as created_by_name";
    }

    /**
     * Get batches with full details for production logs view
     */
    public function getBatchesWithDetails($filters = [])
    {
        // Build subquery for log aggregates to keep main query simple and compatible with ONLY_FULL_GROUP_BY
        $logBatchCol = $this->detectProcessBatchLogColumn($this->db);
        $logsSub = $this->db->table('process_batch_logs')
            ->select("{$logBatchCol} as log_batch_id, COALESCE(SUM(qty_completed),0) as completed_qty, COALESCE(SUM(qty_rejected),0) as rejected_qty, COALESCE(SUM(qty_received),0) as received_qty, MAX(log_date) as last_log_date")
            ->groupBy($logBatchCol)
            ->getCompiledSelect();

        $productCodeExpr = $this->productCodeSelectExpr();
        $builder = $this->db->table($this->table . ' pb')
            ->select("pb.*, woi.product_id, woi.quantity_ordered, wo.id as work_order_id, wo.wo_number, wo.customer_name,
                      COALESCE(p.name, '') as product_name, {$productCodeExpr},
                      pr.id as process_id, pr.name as process_name, pr.is_vendor_process,
                      pl.completed_qty, pl.rejected_qty, pl.received_qty, pl.last_log_date")
            ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
            ->join('work_orders wo', 'wo.id = woi.work_order_id')
            ->join('products p', 'p.id = woi.product_id')
            ->join('processes pr', 'pr.id = pb.process_id')
            // Left join the aggregates subquery
            ->join("($logsSub) pl", "pl.log_batch_id = pb.id", 'left');

        // Apply filters
        if (!empty($filters['work_order_id'])) {
            $builder->where('wo.id', $filters['work_order_id']);
        }
        if (!empty($filters['product_id'])) {
            $builder->where('p.id', $filters['product_id']);
        }
        if (!empty($filters['process_id'])) {
            $builder->where('pr.id', $filters['process_id']);
        }
        if (!empty($filters['status'])) {
            $builder->where('pb.status', $filters['status']);
        }

        $builder->orderBy('pb.created_at', 'DESC');

        return $builder->get()->getResultArray();
    }

    /**
     * Get active batches for dropdown selection
     */
    public function getActiveBatchesForDropdown()
    {
        return $this->db->table($this->table . ' pb')
            ->select('pb.id, pb.batch_code, p.name as product_name, pr.name as process_name')
            ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
            ->join('products p', 'p.id = woi.product_id')
            ->join('processes pr', 'pr.id = pb.process_id')
            ->whereIn('pb.status', ['planned', 'in_progress'])
            ->orderBy('pb.created_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Get batch summary with totals
     */
    public function getBatchSummary($batchId)
    {
        $logBatchCol = $this->detectProcessBatchLogColumn($this->db);

        $batch = $this->db->table($this->table . ' pb')
            ->select("pb.*, 
                      COALESCE(SUM(pbl.qty_received), 0) as received_qty,
                      COALESCE(SUM(pbl.qty_completed), 0) as completed_qty,
                      COALESCE(SUM(pbl.qty_rejected), 0) as rejected_qty,
                      COALESCE(SUM(pbl.qty_for_repair), 0) as repair_qty")
            ->join('process_batch_logs pbl', "pbl.{$logBatchCol} = pb.id", 'left')
            ->where('pb.id', $batchId)
            ->groupBy('pb.id')
            ->get()
            ->getRowArray();

        if ($batch) {
            $batch['pending_qty'] = $batch['planned_qty'] - $batch['completed_qty'] - $batch['rejected_qty'];
        }

        return $batch;
    }

    /**
     * Get batch with all details for detailed view
     */
    public function getBatchWithAllDetails($batchId)
    {
        $productCodeExpr = $this->productCodeSelectExpr();
        $userNameExpr = $this->userNameSelectExpr();
        return $this->db->table($this->table . ' pb')
            ->select("pb.*, woi.product_id, woi.quantity_ordered, wo.wo_number, wo.customer_name,
                      COALESCE(p.name, '') as product_name, {$productCodeExpr}, p.description as product_description,
                      pr.name as process_name, pr.description as process_description, pr.is_vendor_process,
                      {$userNameExpr}")
            ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
            ->join('work_orders wo', 'wo.id = woi.work_order_id')
            ->join('products p', 'p.id = woi.product_id')
            ->join('processes pr', 'pr.id = pb.process_id')
            ->join('users u', 'u.id = pb.created_by', 'left')
            ->where('pb.id', $batchId)
            ->get()
            ->getRowArray();
    }
}
