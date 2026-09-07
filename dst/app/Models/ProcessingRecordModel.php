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
        'qty',
        'status',
        'location_id',
        'parent_id',
        'created_at',
    ];

    public function hasOpenRecordForStep(int $productId, int $stepId): bool
    {
        return $this->where('product_id', $productId)
            ->where('step_id', $stepId)
            ->whereIn('status', ['in_progress', 'ready_for_qc'])
            ->countAllResults() > 0;
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
