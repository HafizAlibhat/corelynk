<?php

namespace App\Models;

use CodeIgniter\Model;

class QcRecordModel extends Model
{
    protected $table            = 'qc_records';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'process_run_id', 'qc_checklist_data', 'quantity_checked', 
        'quantity_passed', 'quantity_failed', 'qc_decision', 'remarks', 
        'inspected_by', 'inspected_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'process_run_id'    => 'required|integer|is_not_unique[work_order_process_runs.id]',
        'quantity_checked'  => 'required|integer|greater_than[0]',
        'quantity_passed'   => 'permit_empty|integer|greater_than_equal_to[0]',
        'quantity_failed'   => 'permit_empty|integer|greater_than_equal_to[0]',
        'qc_decision'       => 'required|in_list[pass,rework,reject]',
        'inspected_by'      => 'permit_empty|integer|is_not_unique[users.id]'
    ];

    protected $validationMessages = [
        'process_run_id' => [
            'required'       => 'Process run is required',
            'is_not_unique'  => 'Selected process run does not exist'
        ],
        'quantity_checked' => [
            'required'      => 'Quantity checked is required',
            'greater_than'  => 'Quantity checked must be greater than 0'
        ],
        'qc_decision' => [
            'required' => 'QC decision is required'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['setInspectedAt'];
    protected $beforeUpdate   = ['validateQuantities'];

    /**
     * Set inspected_at timestamp
     */
    protected function setInspectedAt(array $data)
    {
        if (empty($data['data']['inspected_at'])) {
            $data['data']['inspected_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    /**
     * Validate quantities
     */
    protected function validateQuantities(array $data)
    {
        if (isset($data['data'])) {
            $qtyPassed = $data['data']['quantity_passed'] ?? 0;
            $qtyFailed = $data['data']['quantity_failed'] ?? 0;
            $qtyChecked = $data['data']['quantity_checked'] ?? 0;

            if (($qtyPassed + $qtyFailed) > $qtyChecked) {
                throw new \InvalidArgumentException('Total passed and failed quantities cannot exceed checked quantity');
            }
        }

        return $data;
    }

    /**
     * Get QC records by process run
     */
    public function getQcRecordsByProcessRun(int $processRunId): array
    {
        return $this->select('qc_records.*, users.first_name as inspector_name')
                    ->join('users', 'users.id = qc_records.inspected_by', 'left')
                    ->where('qc_records.process_run_id', $processRunId)
                    ->orderBy('qc_records.inspected_at', 'DESC')
                    ->findAll();
    }
}
