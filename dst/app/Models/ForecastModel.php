<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Deterministic forecasting utilities based on existing actuals only.
 * - No schema changes; uses work_order_process_runs, processes, work_orders, work_order_items
 * - Assumptions: flow allowed; capacities inferred from recent actual throughput
 */
class ForecastModel extends Model
{
    protected $DBGroup = 'default';

    /**
     * Average daily throughput by process over the last N days (quantity_out on completed runs).
     * @return array [ [process_id, process_name, avg_daily_output], ... ]
     */
    public function getThroughputByProcess(int $days = 14): array
    {
        $startDate = date('Y-m-d', strtotime('-' . $days . ' days'));
        // Prefer work_order_process_runs when available; otherwise fallback to process_batch_logs
        $hasWopr = $this->db->query("SHOW TABLES LIKE 'work_order_process_runs'")->getNumRows() > 0;
        if ($hasWopr) {
            $builder = $this->db->table('work_order_process_runs wopr')
                ->select('p.id as process_id, p.name as process_name, 
                          COALESCE(SUM(wopr.quantity_out), 0) / GREATEST(COUNT(DISTINCT DATE(wopr.completed_at)), 1) as avg_daily_output')
                ->join('processes p', 'p.id = wopr.process_id')
                ->where('wopr.status', 'completed')
                ->where('wopr.completed_at >=', $startDate)
                ->where('wopr.completed_at <=', date('Y-m-d 23:59:59'))
                ->groupBy('p.id');
            $rows = $builder->get()->getResultArray();
        } else {
            // Fallback: use process_batch_logs joined with process_batches to attribute totals to processes
            // Sum accepted quantities per day per process within window, then average per distinct day with logs
            $dateExpr = "COALESCE(pbl.log_date, DATE(pbl.created_at))";
            $qtyExpr = "COALESCE(pbl.qty_completed, pbl.accepted_qty, 0)";
            $sql = "
                SELECT pr.id AS process_id, pr.name AS process_name,
                       COALESCE(SUM($qtyExpr), 0) / GREATEST(COUNT(DISTINCT $dateExpr), 1) AS avg_daily_output
                FROM process_batch_logs pbl
                JOIN process_batches pb ON pb.id = COALESCE(pbl.batch_id, pbl.process_batch_id)
                JOIN processes pr ON pr.id = pb.process_id
                WHERE $dateExpr >= ? AND $dateExpr <= ?
                GROUP BY pr.id
            ";
            $rows = $this->db->query($sql, [$startDate, date('Y-m-d')])->getResultArray();
        }

        // Normalize numeric types
        foreach ($rows as &$r) {
            $r['avg_daily_output'] = (float) $r['avg_daily_output'];
        }
        return $rows;
    }

    /**
     * Current backlog by process based on runs with pending/in_progress.
     * Uses quantity_in - (quantity_out + quantity_scrap) as remaining if quantity_pending is unreliable.
     * @return array [ [process_id, process_name, backlog_qty], ... ]
     */
    public function getBacklogByProcess(): array
    {
        $hasWopr = $this->db->query("SHOW TABLES LIKE 'work_order_process_runs'")->getNumRows() > 0;
        if ($hasWopr) {
            $builder = $this->db->table('work_order_process_runs wopr')
                ->select('p.id as process_id, p.name as process_name, 
                          COALESCE(SUM(GREATEST(wopr.quantity_in - COALESCE(wopr.quantity_out,0) - COALESCE(wopr.quantity_scrap,0), 0)), 0) as backlog_qty')
                ->join('processes p', 'p.id = wopr.process_id')
                ->whereIn('wopr.status', ['pending', 'in_progress'])
                ->groupBy('p.id')
                ->orderBy('p.name', 'ASC');
            $rows = $builder->get()->getResultArray();
        } else {
            // Backlog from batches: planned - (accepted+rejected+rework)
            $qtyAcc = "COALESCE(SUM(pbl.qty_completed),0) + COALESCE(SUM(pbl.accepted_qty),0)"; // double counts if both present, but schemas are mutually exclusive
            $qtyRej = "COALESCE(SUM(pbl.qty_rejected),0) + COALESCE(SUM(pbl.rejected_qty),0)";
            $qtyRew = "COALESCE(SUM(pbl.rework_qty),0) + COALESCE(SUM(pbl.qty_rework),0) + COALESCE(SUM(pbl.sent_for_rework),0) + COALESCE(SUM(pbl.reworked_qty),0)";
            $sql = "
                SELECT pr.id AS process_id, pr.name AS process_name,
                       COALESCE(SUM(GREATEST(pb.planned_qty - (($qtyAcc) + ($qtyRej) + ($qtyRew)), 0)), 0) AS backlog_qty
                FROM process_batches pb
                JOIN processes pr ON pr.id = pb.process_id
                LEFT JOIN process_batch_logs pbl ON pb.id = COALESCE(pbl.batch_id, pbl.process_batch_id)
                GROUP BY pr.id
                ORDER BY pr.name ASC
            ";
            $rows = $this->db->query($sql)->getResultArray();
        }
        foreach ($rows as &$r) {
            $r['backlog_qty'] = (int) $r['backlog_qty'];
        }
        return $rows;
    }

    /**
     * Bottleneck index per process = backlog / avg_daily_output (higher is worse).
     * If avg_daily_output == 0, index = null (or a large number?) -> we set null and sort to top.
     */
    public function getBottlenecks(int $days = 14, int $limit = 5): array
    {
        $throughput = $this->getThroughputByProcess($days);
        $backlog = $this->getBacklogByProcess();

        $tpByProcess = [];
        foreach ($throughput as $t) {
            $tpByProcess[$t['process_id']] = $t['avg_daily_output'];
        }

        $result = [];
        foreach ($backlog as $b) {
            $avg = $tpByProcess[$b['process_id']] ?? 0.0;
            $index = $avg > 0 ? ($b['backlog_qty'] / $avg) : null; // days of work at current pace
            $result[] = [
                'process_id' => (int) $b['process_id'],
                'process_name' => $b['process_name'],
                'backlog_qty' => (int) $b['backlog_qty'],
                'avg_daily_output' => (float) $avg,
                'bottleneck_index' => $index !== null ? round($index, 2) : null
            ];
        }

        // Sort: null (no throughput) first, then by desc index
        usort($result, function ($a, $b) {
            if ($a['bottleneck_index'] === null && $b['bottleneck_index'] === null) return 0;
            if ($a['bottleneck_index'] === null) return -1;
            if ($b['bottleneck_index'] === null) return 1;
            return $b['bottleneck_index'] <=> $a['bottleneck_index'];
        });

        return array_slice($result, 0, $limit);
    }

    /**
     * Burn-up series for the last N days (cumulative output across all processes).
     * @return array [ [date: Y-m-d, daily_output, cumulative_output], ... ]
     */
    public function getBurnupSeries(int $days = 30): array
    {
        $startDate = date('Y-m-d', strtotime('-' . $days . ' days'));
        $hasWopr = $this->db->query("SHOW TABLES LIKE 'work_order_process_runs'")->getNumRows() > 0;
        if ($hasWopr) {
            $builder = $this->db->table('work_order_process_runs wopr')
                ->select('DATE(wopr.completed_at) as d, COALESCE(SUM(wopr.quantity_out), 0) as qty')
                ->where('wopr.status', 'completed')
                ->where('wopr.completed_at >=', $startDate)
                ->where('wopr.completed_at <=', date('Y-m-d 23:59:59'))
                ->groupBy('DATE(wopr.completed_at)')
                ->orderBy('d', 'ASC');
            $rows = $builder->get()->getResultArray();
        } else {
            $dateExpr = "COALESCE(pbl.log_date, DATE(pbl.created_at))";
            $qtyExpr = "COALESCE(pbl.qty_completed, pbl.accepted_qty, 0)";
            $sql = "
                SELECT $dateExpr as d, COALESCE(SUM($qtyExpr),0) as qty
                FROM process_batch_logs pbl
                WHERE $dateExpr >= ? AND $dateExpr <= ?
                GROUP BY $dateExpr
                ORDER BY d ASC
            ";
            $rows = $this->db->query($sql, [$startDate, date('Y-m-d')])->getResultArray();
        }

        // Fill missing days with zeroes
        $series = [];
        $map = [];
        foreach ($rows as $r) {
            $map[$r['d']] = (int) $r['qty'];
        }
        $cumulative = 0;
        for ($i = $days; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime('-' . $i . ' days'));
            $daily = $map[$d] ?? 0;
            $cumulative += $daily;
            $series[] = [
                'date' => $d,
                'daily_output' => $daily,
                'cumulative_output' => $cumulative
            ];
        }
        return $series;
    }

    /**
     * Work orders due within N days that still have outstanding quantity.
     * @return array minimal fields for a risk table
     */
    public function getAtRiskWorkOrders(int $daysAhead = 7): array
    {
        $deadline = date('Y-m-d', strtotime('+' . $daysAhead . ' days'));
        // If work_orders table missing, return empty gracefully
        $hasWO = $this->db->query("SHOW TABLES LIKE 'work_orders'")->getNumRows() > 0;
        if (!$hasWO) { return []; }

        $builder = $this->db->table('work_orders wo')
            ->select('wo.id, wo.wo_number, wo.customer_name, wo.due_date, 
                      COALESCE(SUM(woi.quantity_ordered),0) as qty_ordered,
                      COALESCE(SUM(woi.quantity_completed),0) as qty_completed')
            ->join('work_order_items woi', 'woi.work_order_id = wo.id')
            ->whereIn('wo.status', ['planned', 'in_progress'])
            ->where('wo.due_date IS NOT NULL')
            ->where('wo.due_date <=', $deadline)
            ->groupBy('wo.id')
            ->orderBy('wo.due_date', 'ASC');

        $rows = $builder->get()->getResultArray();

        $risks = [];
        foreach ($rows as $r) {
            $ordered = (int) $r['qty_ordered'];
            $completed = (int) $r['qty_completed'];
            $outstanding = max($ordered - $completed, 0);
            if ($outstanding > 0) {
                $risks[] = [
                    'id' => (int) $r['id'],
                    'wo_number' => $r['wo_number'],
                    'customer_name' => $r['customer_name'],
                    'due_date' => $r['due_date'],
                    'qty_ordered' => $ordered,
                    'qty_completed' => $completed,
                    'qty_outstanding' => $outstanding,
                    'days_to_due' => (int) floor((strtotime($r['due_date']) - strtotime(date('Y-m-d'))) / 86400)
                ];
            }
        }
        return $risks;
    }
}
