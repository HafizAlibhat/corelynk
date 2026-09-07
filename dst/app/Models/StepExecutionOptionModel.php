<?php

namespace App\Models;

use CodeIgniter\Model;

class StepExecutionOptionModel extends Model
{
    protected $table            = 'step_execution_options';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'step_id',
        'execution_type',
        'vendor_id',
        'notes',
        'is_default',
        'created_at',
    ];

    public function getByStep(int $stepId): array
    {
        return $this->where('step_id', $stepId)->orderBy('id', 'ASC')->findAll();
    }

    public function getByProfileGrouped(int $profileId): array
    {
        $rows = $this->select('step_execution_options.*, v.name AS vendor_name')
            ->join('preparation_steps ps', 'ps.id = step_execution_options.step_id', 'inner')
            ->join('vendors v', 'v.id = step_execution_options.vendor_id', 'left')
            ->where('ps.profile_id', $profileId)
            ->orderBy('step_execution_options.id', 'ASC')
            ->findAll();

        $grouped = [];
        foreach ($rows as $row) {
            $grouped[(int) $row['step_id']][] = $row;
        }

        return $grouped;
    }

    public function addOption(array $data): int
    {
        $this->insert($data);
        return (int) $this->getInsertID();
    }

    public function deleteByStepIds(array $stepIds): bool
    {
        if (empty($stepIds)) {
            return true;
        }
        return (bool) $this->whereIn('step_id', $stepIds)->delete();
    }
}
