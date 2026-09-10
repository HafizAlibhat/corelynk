<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessingRecordModel extends Model
{
    protected $table            = 'processing_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id',
        'step_id',
        'vendor_id',
        'sales_order_id',
        'sales_order_line_id',
        'qty',
        'status',
        'location_id',
        'parent_id',
        'parent_send_note_id',
        'rework_reason_id',
        'rework_vendor_id',
        'notes',
        'created_at',
    ];

    public function hasOpenRecordForStep(int $productId, int $stepId): bool
    {
        return $this->where('product_id', $productId)
            ->where('step_id', $stepId)
            ->whereIn('status', ['in_progress', 'ready_for_qc'])
            ->countAllResults() > 0;
    }

    /**
     * Take a quantity out of the open record a vendor (or our own floor) is
     * holding: the original record is closed as 'superseded' so it stops counting
     * as work in progress, and the untouched remainder is re-opened as a fresh
     * record. Append-only, so the trail keeps every intermediate state.
     *
     * Used whenever a batch splits mid-flight — rerouted to another vendor, or
     * partly accepted and partly rejected on receiving.
     *
     * @return array|null the superseded record, so callers can chain off it
     */
    public function splitOpenForStep(int $productId, int $stepId, ?int $vendorId, float $qty, string $note): ?array
    {
        $builder = $this->where('product_id', $productId)
            ->where('step_id', $stepId)
            ->whereIn('status', ['in_progress', 'ready_for_qc']);

        if ($vendorId !== null) {
            $builder->where('vendor_id', $vendorId);
        }

        $open = $builder->orderBy('id', 'ASC')->first();
        if (! $open) {
            return null;
        }

        $this->update((int) $open['id'], [
            'status' => 'superseded',
            'notes' => $note,
        ]);

        $remainder = (float) $open['qty'] - $qty;
        if ($remainder > 0.0001) {
            $this->insert([
                'product_id' => $productId,
                'step_id' => $stepId,
                'vendor_id' => $vendorId,
                'sales_order_id' => $open['sales_order_id'] ?? null,
                'sales_order_line_id' => $open['sales_order_line_id'] ?? null,
                'qty' => number_format($remainder, 4, '.', ''),
                'status' => $open['status'],
                'location_id' => $open['location_id'] ?? null,
                'parent_id' => (int) $open['id'],
                'parent_send_note_id' => $open['parent_send_note_id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $open;
    }

    /**
     * Quantities already committed to a step, so a step can be executed in
     * partial batches (send 40 now, the remaining 60 later).
     *
     * @return array{open: float, done: float}
     */
    public function qtySummaryForStep(int $productId, int $stepId): array
    {
        $rows = $this->select('status, SUM(qty) as total')
            ->where('product_id', $productId)
            ->where('step_id', $stepId)
            ->groupBy('status')
            ->findAll();

        $summary = ['open' => 0.0, 'done' => 0.0];
        foreach ($rows as $row) {
            $total = (float) ($row['total'] ?? 0);
            if (($row['status'] ?? '') === 'completed') {
                $summary['done'] += $total;
            } elseif (in_array($row['status'] ?? '', ['in_progress', 'ready_for_qc'], true)) {
                $summary['open'] += $total;
            }
        }

        return $summary;
    }

    public function findLatestCompletedForStep(int $productId, int $stepId): ?array
    {
        $row = $this->where('product_id', $productId)
            ->where('step_id', $stepId)
            ->where('status', 'completed')
            ->orderBy('id', 'DESC')
            ->first();

        return $row ?: null;
    }
}
