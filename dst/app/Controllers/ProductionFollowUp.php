<?php

namespace App\Controllers;

use CodeIgniter\Controller;

class ProductionFollowUp extends BaseController
{
    public function index()
    {
        // Interactive view with controls
        return view('production/follow_up', []);
    }

    public function screen($screenId = '1')
    {
        // Fullscreen kiosk view for a specific screen id
        $data = ['screenId' => $screenId];
        return view('production/follow_up_screen', $data);
    }

    public function apiSummary()
    {
        $db = \Config\Database::connect();

        // Active work orders
        $activeWOs = (int) $db->table('work_orders')->whereIn('status', ['pending','in_progress','released'])->countAllResults();

        // Overdue work orders
        $overdueWOs = (int) $db->table('work_orders')->where('status !=', 'completed')->where('due_date <', date('Y-m-d'))->countAllResults();

        // Pending market batches
        $pendingMarket = (int) $db->table('process_batches')->where('status', 'pending')->where('vendor_id IS NOT NULL', null, false)->countAllResults();

        // Pending in-house batches
        $pendingInhouse = (int) $db->table('process_batches')->where('status', 'pending')->where('vendor_id IS NULL', null, false)->countAllResults();

        // Throughput last 4 hours (try several possible column names)
        $since = date('Y-m-d H:i:s', time() - 4 * 3600);
        $throughput = 0;
        try {
            $logFields = $db->getFieldNames('process_batch_logs');
            $candidates = ['qty_processed','qty','qty_completed','processed_qty'];
            $col = null;
            foreach ($candidates as $c) {
                if (in_array($c, $logFields)) { $col = $c; break; }
            }
            if ($col) {
                $throughputRow = $db->table('process_batch_logs')->select("IFNULL(SUM($col),0) AS total")->where('timestamp >=', $since)->get()->getRowArray();
                $throughput = (int) ($throughputRow['total'] ?? 0);
            }
        } catch (\Exception $e) {
            // ignore and leave throughput = 0
        }

        // Recent yield: accepted / produced from process_batch_releases (last 24h)
        $since24 = date('Y-m-d H:i:s', time() - 24 * 3600);
        $released = 0; $rejected = 0; $yield = null;
        try {
            $relFields = $db->getFieldNames('process_batch_releases');
            $rCol = null; $rejCol = null;
            foreach (['qty_released','released_qty','accepted_qty','qty_accepted'] as $c) { if (in_array($c, $relFields)) { $rCol = $c; break; } }
            foreach (['qty_rejected','rejected_qty','qty_reject','qty_rejected_count'] as $c) { if (in_array($c, $relFields)) { $rejCol = $c; break; } }
            if ($rCol || $rejCol) {
                $selectParts = [];
                if ($rCol) $selectParts[] = "IFNULL(SUM($rCol),0) AS released";
                else $selectParts[] = "0 AS released";
                if ($rejCol) $selectParts[] = "IFNULL(SUM($rejCol),0) AS rejected";
                else $selectParts[] = "0 AS rejected";
                $yieldRow = $db->table('process_batch_releases')->select(implode(', ', $selectParts))->where('released_at >=', $since24)->get()->getRowArray();
                $released = (int) ($yieldRow['released'] ?? 0);
                $rejected = (int) ($yieldRow['rejected'] ?? 0);
                $yield = $released + $rejected > 0 ? round(($released / ($released + $rejected)) * 100, 1) : null;
            }
        } catch (\Exception $e) {
            // ignore
        }

        return $this->response->setJSON([
            'active_wos' => $activeWOs,
            'overdue_wos' => $overdueWOs,
            'pending_market_batches' => $pendingMarket,
            'pending_inhouse_batches' => $pendingInhouse,
            'throughput_last_4h' => $throughput,
            'yield_24h' => $yield,
            'timestamp' => date('c')
        ]);
    }

    public function apiPanels()
    {
        $db = \Config\Database::connect();
        // Pending Work Orders (limit 50) - build selects defensively based on available columns
        $wos = [];
        try {
            $woFields = $db->getFieldNames('work_orders');
            $plannedCol = null; $producedCol = null;
            foreach (['planned_qty','quantity_ordered','planned_quantity','qty_ordered','qty'] as $c) { if (in_array($c, $woFields)) { $plannedCol = $c; break; } }
            foreach (['produced_qty','produced','qty_completed','accepted_qty'] as $c) { if (in_array($c, $woFields)) { $producedCol = $c; break; } }
            $select = [
                'wo.id','wo.wo_number','p.name AS product_name','wo.status','wo.due_date','wo.priority'
            ];
            if ($plannedCol) $select[] = "wo.$plannedCol AS planned_qty"; else $select[] = "0 AS planned_qty";
            if ($producedCol) $select[] = "wo.$producedCol AS produced_qty"; else $select[] = "0 AS produced_qty";

            $wos = $db->table('work_orders wo')
                ->select(implode(', ', $select))
                ->join('products p', 'p.id = wo.product_id', 'left')
                ->whereIn('wo.status', ['pending','planned'])
                ->orderBy('wo.priority', 'DESC')
                ->orderBy('wo.due_date', 'ASC')
                ->limit(50)->get()->getResultArray();
        } catch (\Exception $e) {
            // fallback: simple query
            $wos = $db->table('work_orders')->limit(0)->get()->getResultArray();
        }

        // Pending Batches (market / inhouse) - defensive column names
        $market = [];$inhouse = [];
        try {
            $pbFields = $db->getFieldNames('process_batches');
            $pbPlanned = null; $pbCompleted = null;
            foreach (['planned_qty','planned_quantity','qty_planned','qty'] as $c) { if (in_array($c, $pbFields)) { $pbPlanned = $c; break; } }
            foreach (['completed_qty','completed','qty_completed','qty_done'] as $c) { if (in_array($c, $pbFields)) { $pbCompleted = $c; break; } }
            $pbSelect = ['pb.id','pb.batch_number','pb.process_id','pb.status','pb.vendor_id'];
            $pbSelect[] = $pbPlanned ? "pb.$pbPlanned AS planned_qty" : "0 AS planned_qty";
            $pbSelect[] = $pbCompleted ? "pb.$pbCompleted AS completed_qty" : "0 AS completed_qty";

            $market = $db->table('process_batches pb')
                ->select(implode(', ', $pbSelect))
                ->where('pb.status', 'pending')
                ->where('pb.vendor_id IS NOT NULL', null, false)
                ->orderBy('pb.created_at', 'ASC')
                ->limit(50)->get()->getResultArray();

            $inhouse = $db->table('process_batches pb')
                ->select(implode(', ', $pbSelect))
                ->where('pb.status', 'pending')
                ->where('pb.vendor_id IS NULL', null, false)
                ->orderBy('pb.created_at', 'ASC')
                ->limit(50)->get()->getResultArray();
        } catch (\Exception $e) {
            // leave arrays empty
        }

        // In-progress runs - find a qty column in logs
        $inprogress = [];
        try {
            $logFields = $db->getFieldNames('process_batch_logs');
            $qtyCol = null;
            foreach (['qty_processed','qty','qty_completed','processed_qty'] as $c) { if (in_array($c, $logFields)) { $qtyCol = $c; break; } }
            $logSelect = ['l.id','l.process_batch_id','l.operator_id','l.event','l.timestamp'];
            if ($qtyCol) $logSelect[] = "l.$qtyCol AS qty_processed"; else $logSelect[] = "0 AS qty_processed";

            $inprogress = $db->table('process_batch_logs l')
                ->select(implode(', ', $logSelect))
                ->where('l.event', 'start')
                ->orderBy('l.timestamp', 'DESC')
                ->limit(50)->get()->getResultArray();
        } catch (\Exception $e) {
            // ignore
        }

        // Completed batches (recent 30)
        $completed = [];
        try {
            $pbFields = $db->getFieldNames('process_batches');
            $completedAt = null; $completedQty = null;
            foreach (['completed_at','completed_on','finished_at'] as $c) { if (in_array($c, $pbFields)) { $completedAt = $c; break; } }
            foreach (['completed_qty','completed_quantity','qty_completed'] as $c) { if (in_array($c, $pbFields)) { $completedQty = $c; break; } }
            $compSelect = ['id','batch_number'];
            $compSelect[] = $completedAt ? $completedAt : "NULL AS completed_at";
            $compSelect[] = $completedQty ? "$completedQty AS completed_qty" : "0 AS completed_qty";

            $completed = $db->table('process_batches')
                ->select(implode(', ', $compSelect))
                ->where('status', 'completed')
                ->orderBy($completedAt ?: 'id', 'DESC')
                ->limit(30)->get()->getResultArray();
        } catch (\Exception $e) {
            // ignore
        }

        return $this->response->setJSON([
            'work_orders' => $wos,
            'market_batches' => $market,
            'inhouse_batches' => $inhouse,
            'inprogress' => $inprogress,
            'completed' => $completed,
            'timestamp' => date('c')
        ]);
    }
}
