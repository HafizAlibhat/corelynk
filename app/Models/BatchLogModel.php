<?php

namespace App\Models;

use CodeIgniter\Model;

class BatchLogModel extends Model
{
    protected $table = 'process_batch_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    /**
     * Insert a log row using available columns in the table.
     */
    public function insertLog(array $data)
    {
        $db = \Config\Database::connect();
        try {
            $cols = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
        } catch (\Throwable $e) {
            throw $e;
        }
        $fields = array_map(fn($r) => $r['Field'], $cols);

        $insert = [];
        if (in_array('batch_id', $fields, true)) {
            $insert['batch_id'] = $data['batch_id'] ?? null;
        } elseif (in_array('process_batch_id', $fields, true)) {
            $insert['process_batch_id'] = $data['batch_id'] ?? null;
        }

        // user mapping if present
        $userId = $data['created_by'] ?? $data['user_id'] ?? null;
        if ($userId !== null) {
            if (in_array('created_by', $fields, true)) { $insert['created_by'] = $userId; }
            elseif (in_array('user_id', $fields, true)) { $insert['user_id'] = $userId; }
        }

        foreach (['qty_completed','qty_rejected','qty_received','notes','log_date','accepted_qty'] as $k) {
            if (isset($data[$k]) && in_array($k, $fields, true)) {
                $insert[$k] = $data[$k];
            }
            // map legacy names
            if ($k === 'qty_completed' && isset($data['accepted_qty']) && in_array('accepted_qty', $fields, true)) {
                $insert['accepted_qty'] = $data['accepted_qty'];
            }
            if ($k === 'qty_rejected' && isset($data['rejected_qty']) && in_array('rejected_qty', $fields, true)) {
                $insert['rejected_qty'] = $data['rejected_qty'];
            }
        }

        // Repaired mapping: treat as part of completed/accepted for totals, but persist if the column exists
        if (isset($data['repaired_qty']) || isset($data['qty_repaired'])) {
            $repVal = $data['repaired_qty'] ?? $data['qty_repaired'];
            if (in_array('repaired_qty', $fields, true)) {
                $insert['repaired_qty'] = $repVal;
            } elseif (in_array('qty_repaired', $fields, true)) {
                $insert['qty_repaired'] = $repVal;
            }
        }

        // Rework mapping: try multiple possible columns
        $reworkValue = $data['rework_qty'] ?? $data['qty_rework'] ?? null;
        $reworkTargets = ['rework_qty','qty_rework','sent_for_rework','reworked_qty'];
        if ($reworkValue !== null) {
            foreach ($reworkTargets as $rk) {
                if (in_array($rk, $fields, true)) { $insert[$rk] = $reworkValue; break; }
            }
            // If none of the rework columns exist, append to notes
            if (!array_intersect($reworkTargets, $fields)) {
                $existing = isset($insert['notes']) ? (string)$insert['notes'] : '';
                $insert['notes'] = trim($existing . ' ' . '(rework=' . (float)$reworkValue . ')');
            }
        }

        // Honor explicit created_at if provided (to capture actual production time), else default to now
        if (in_array('created_at', $fields, true)) {
            $insert['created_at'] = isset($data['created_at']) && $data['created_at'] ? $data['created_at'] : date('Y-m-d H:i:s');
        }

        return $db->table('process_batch_logs')->insert($insert);
    }

    /** Update a log row by id with schema-safe mapping */
    public function updateLogById(int $id, array $data): bool
    {
        if ($id <= 0) return false;
        $db = \Config\Database::connect();
        $cols = $db->query("SHOW COLUMNS FROM process_batch_logs")->getResultArray();
        $fields = array_map(fn($r) => $r['Field'], $cols);

        $upd = [];
        $mapPairs = [
            ['qty_completed','accepted_qty'],
            ['qty_rejected','rejected_qty'],
            ['rework_qty','qty_rework','sent_for_rework','reworked_qty'],
        ];
        // accepted
        $acc = $data['qty_completed'] ?? $data['accepted_qty'] ?? null;
        if ($acc !== null) {
            if (in_array('qty_completed', $fields, true)) $upd['qty_completed'] = $acc;
            elseif (in_array('accepted_qty', $fields, true)) $upd['accepted_qty'] = $acc;
        }
        // rejected
        $rej = $data['qty_rejected'] ?? $data['rejected_qty'] ?? null;
        if ($rej !== null) {
            if (in_array('qty_rejected', $fields, true)) $upd['qty_rejected'] = $rej;
            elseif (in_array('rejected_qty', $fields, true)) $upd['rejected_qty'] = $rej;
        }
        // rework
        $rew = $data['rework_qty'] ?? $data['qty_rework'] ?? null;
        if ($rew !== null) {
            foreach (['rework_qty','qty_rework','sent_for_rework','reworked_qty'] as $rk) {
                if (in_array($rk, $fields, true)) { $upd[$rk] = $rew; break; }
            }
        }
        // repaired
        $rep = $data['repaired_qty'] ?? $data['qty_repaired'] ?? null;
        if ($rep !== null) {
            if (in_array('repaired_qty', $fields, true)) $upd['repaired_qty'] = $rep;
            elseif (in_array('qty_repaired', $fields, true)) $upd['qty_repaired'] = $rep;
        }
        if (isset($data['qty_received']) && in_array('qty_received', $fields, true)) {
            $upd['qty_received'] = $data['qty_received'];
        }
        if (isset($data['notes']) && in_array('notes', $fields, true)) { $upd['notes'] = $data['notes']; }
        if (isset($data['log_date']) && in_array('log_date', $fields, true)) { $upd['log_date'] = $data['log_date']; }

        if (empty($upd)) return true; // nothing to update
        return (bool) $db->table('process_batch_logs')->where('id', $id)->update($upd);
    }

    /** Delete a log by id */
    public function deleteLogById(int $id): bool
    {
        if ($id <= 0) return false;
        $db = \Config\Database::connect();
        return (bool) $db->table('process_batch_logs')->where('id', $id)->delete();
    }
}
