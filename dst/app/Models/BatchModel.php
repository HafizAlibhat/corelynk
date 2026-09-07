<?php

namespace App\Models;

use CodeIgniter\Model;

class BatchModel extends Model
{
    protected $table = 'process_batches';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    // Allow created_at so we can persist user-chosen Start Date when creating a batch
    protected $allowedFields = ['work_order_item_id', 'process_id', 'vendor_id', 'batch_code', 'planned_qty', 'status', 'created_by', 'created_at'];

    public function createBatch(array $data)
    {
        $this->insert($data);
        return $this->getInsertID();
    }

    public function getHierarchy()
    {
        $db = \Config\Database::connect();
        // Fetch batches
        $batches = $db->table('process_batches pb')
            ->select('pb.id as batch_id, pb.batch_code, pb.planned_qty, pb.status, pb.process_id, pb.vendor_id, pb.created_at, v.name as vendor_name, pb.work_order_item_id, wo.id as work_order_id, wo.wo_number, woi.product_id, woi.quantity_ordered as ordered_qty, p.name as product_name, pr.name as process_name, pr.responsibility_mode, pr.responsibility_department')
            ->join('work_order_items woi', 'woi.id = pb.work_order_item_id')
            ->join('work_orders wo', 'wo.id = woi.work_order_id')
            ->join('products p', 'p.id = woi.product_id')
            ->join('processes pr', 'pr.id = pb.process_id')
            ->join('vendors v', 'v.id = pb.vendor_id', 'left')
            ->orderBy('wo.id, p.id, pr.id')
            ->get()
            ->getResultArray();

        if (empty($batches)) return [];

        // Gather log summaries per batch
        $batchIds = array_column($batches, 'batch_id');
        $in = implode(',', array_map('intval', $batchIds));
        $logs = [];
        try {
            // Introspect columns to avoid querying non-existent fields
            $cols = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
            $fields = array_map(fn($r) => $r['Field'], $cols);
            $conds = [];
            if (in_array('batch_id', $fields, true)) { $conds[] = "batch_id IN ($in)"; }
            if (in_array('process_batch_id', $fields, true)) { $conds[] = "process_batch_id IN ($in)"; }
            $rows = [];
            if (!empty($conds)) {
                $rows = $db->query("SELECT * FROM process_batch_logs WHERE " . implode(' OR ', $conds))->getResultArray();
            }
            foreach ($rows as $r) {
                $bid = $r['batch_id'] ?? $r['process_batch_id'] ?? null;
                if (!$bid) continue;
                $logs[$bid][] = $r;
            }
        } catch (\Throwable $e) {
            // No logs table or error; leave logs empty
        }

        // Preload batch -> employees mapping and optional department if pivot exists
        $batchEmployees = [];
        $batchDepartment = [];
        try {
            $hasBE = $db->query("SHOW TABLES LIKE 'process_batch_employees'")->getNumRows() > 0;
            if ($hasBE) {
                $bids = implode(',', array_map('intval', $batchIds));
                if ($bids !== '') {
                    // Inspect columns (for department support)
                    $pbeCols = $db->query("SHOW COLUMNS FROM process_batch_employees")->getResultArray();
                    $pbeFields = array_map(fn($r)=> $r['Field'], $pbeCols);
                    $hasDeptCol = in_array('department', $pbeFields, true);
                    // join employees table if it exists
                    $empExists = $db->query("SHOW TABLES LIKE 'employees'")->getNumRows() > 0;
                    if ($empExists) {
                        $res = $db->query("SELECT pbe.batch_id, pbe.employee_id, ".($hasDeptCol?"pbe.department, ":"")."CONCAT(e.first_name, ' ', e.last_name) AS name FROM process_batch_employees pbe JOIN employees e ON e.id = pbe.employee_id WHERE pbe.batch_id IN ($bids)")->getResultArray();
                        foreach ($res as $row) { $batchEmployees[$row['batch_id']][] = ['id'=>(int)$row['employee_id'],'name'=>$row['name']]; }
                    } else {
                        $res = $db->query("SELECT batch_id, employee_id".($hasDeptCol?", department":"")." FROM process_batch_employees WHERE batch_id IN ($bids)")->getResultArray();
                        foreach ($res as $row) { $batchEmployees[$row['batch_id']][] = ['id'=>(int)$row['employee_id'],'name'=>'Employee #'.$row['employee_id']]; }
                    }
                    if ($hasDeptCol) {
                        $drows = $db->query("SELECT batch_id, department FROM process_batch_employees WHERE batch_id IN ($bids) AND department IS NOT NULL AND department <> ''")->getResultArray();
                        foreach ($drows as $dr) { $batchDepartment[$dr['batch_id']] = $dr['department']; }
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Fallback: process-level responsibility display if no batch-level assignee
        $processEmployees = [];
        try {
            $pids = array_values(array_unique(array_map(fn($b)=> (int)($b['process_id'] ?? 0), $batches)));
            if (!empty($pids)) {
                $hasPEA = $db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
                if ($hasPEA) {
                    $empExists = $db->query("SHOW TABLES LIKE 'employees'")->getNumRows() > 0;
                    if ($empExists) {
                        $rows = $db->query("SELECT pea.process_id, e.id AS employee_id, CONCAT(e.first_name,' ',e.last_name) AS name FROM process_employee_assignments pea JOIN employees e ON e.id = pea.employee_id WHERE pea.process_id IN (".implode(',', $pids).") ORDER BY name ASC")->getResultArray();
                        foreach ($rows as $r) { $processEmployees[(int)$r['process_id']][] = ['id'=>(int)$r['employee_id'],'name'=>$r['name']]; }
                    } else {
                        $rows = $db->query("SELECT process_id, employee_id FROM process_employee_assignments WHERE process_id IN (".implode(',', $pids).")")->getResultArray();
                        foreach ($rows as $r) { $processEmployees[(int)$r['process_id']][] = ['id'=>(int)$r['employee_id'],'name'=>'Employee #'.$r['employee_id']]; }
                    }
                }
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Attach totals & employees
        foreach ($batches as &$b) {
            $bid = $b['batch_id'];
            $b['logs'] = $logs[$bid] ?? [];
            // accepted can be stored as qty_completed or accepted_qty; some datasets also use repaired_qty
            $accKeys = ['qty_completed','accepted_qty','repaired_qty','qty_repaired'];
            $rejKeys = ['qty_rejected','rejected_qty'];
            $rewKeys = ['rework_qty','qty_rework','sent_for_rework','reworked_qty'];
            $totAcc = $totRej = $totRew = 0;
            foreach ($b['logs'] as $lg) {
                foreach ($accKeys as $k) { if (isset($lg[$k])) { $totAcc += (float)$lg[$k]; break; } }
                foreach ($rejKeys as $k) { if (isset($lg[$k])) { $totRej += (float)$lg[$k]; break; } }
                foreach ($rewKeys as $k) { if (isset($lg[$k])) { $totRew += (float)$lg[$k]; break; } }
            }
            // Business rule: Started should equal the quantity entered when creating the batch (planned_qty) and stay constant
            $started = (float)$b['planned_qty'];
            $b['totals'] = [
                'accepted' => $totAcc,
                'rejected' => $totRej,
                'rework' => $totRew,
                'started' => $started,
                // Pending is the remainder out of planned after accounting for accepted/rejected/rework
                'pending' => max(0, (float)$b['planned_qty'] - ($totAcc + $totRej + $totRew)),
            ];
            // pass through vendor info when available
            if (array_key_exists('vendor_id', $b)) {
                $b['vendor_id'] = $b['vendor_id'];
            }
            if (array_key_exists('vendor_name', $b)) {
                $b['vendor_name'] = $b['vendor_name'];
            }
            if (isset($batchEmployees[$bid])) {
                $b['employees'] = $batchEmployees[$bid];
            }
            if (isset($batchDepartment[$bid])) {
                $b['department'] = $batchDepartment[$bid];
            }
            // Fallbacks: if no batch-level assignee, surface process-level responsibility
            if (!isset($b['department']) || $b['department'] === null || $b['department'] === '') {
                if (!empty($b['responsibility_mode']) && $b['responsibility_mode'] === 'department' && !empty($b['responsibility_department'])) {
                    $b['department'] = $b['responsibility_department'];
                }
            }
            if (empty($b['employees']) && isset($processEmployees[$b['process_id']])) {
                $b['employees'] = $processEmployees[$b['process_id']];
            }
        }
        unset($b);

        return $batches;
    }
}
