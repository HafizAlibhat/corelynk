<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessWorkflowStepModel extends Model
{
    protected $table = 'process_workflow_steps';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'workflow_template_id',
        'step_number',
        'process_template_id',
        'description',
        'estimated_time_minutes',
        'is_vendor_process',
        'vendor_id',
        'qc_required',
        'qc_checklist'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'workflow_template_id' => 'required|integer',
        'step_number' => 'required|integer',
        'process_template_id' => 'required|integer',
        'description' => 'permit_empty|max_length[1000]',
        'estimated_time_minutes' => 'permit_empty|integer',
        'is_vendor_process' => 'permit_empty|boolean',
        'vendor_id' => 'permit_empty|integer',
        'qc_required' => 'permit_empty|boolean'
    ];

    protected $validationMessages = [
        'workflow_template_id' => [
            'required' => 'Workflow template ID is required',
            'integer' => 'Workflow template ID must be a number'
        ],
        'step_number' => [
            'required' => 'Step number is required',
            'integer' => 'Step number must be a number'
        ],
        'process_template_id' => [
            'required' => 'Process template ID is required',
            'integer' => 'Process template ID must be a number'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = [];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Get workflow steps with process details
     */
    public function getWorkflowSteps($workflowId)
    {
        return $this->select('
                process_workflow_steps.*,
                process_templates.name as process_name,
                process_templates.description as process_description,
                process_templates.standard_time_minutes,
                vendors.name as vendor_name
            ')
            ->join('process_templates', 'process_templates.id = process_workflow_steps.process_template_id')
            ->join('vendors', 'vendors.id = process_workflow_steps.vendor_id', 'left')
            ->where('process_workflow_steps.workflow_template_id', $workflowId)
            ->orderBy('process_workflow_steps.step_number')
            ->findAll();
    }

    /**
     * Get steps for a specific workflow ordered by step number
     */
    public function getOrderedSteps($workflowId)
    {
        return $this->where('workflow_template_id', $workflowId)
                    ->orderBy('step_number')
                    ->findAll();
    }

    /**
     * Get the next step number for a workflow
     */
    public function getNextStepNumber($workflowId)
    {
        $lastStep = $this->where('workflow_template_id', $workflowId)
                         ->orderBy('step_number', 'DESC')
                         ->first();
        
        return $lastStep ? $lastStep['step_number'] + 1 : 1;
    }

    /**
     * Reorder steps in a workflow
     */
    public function reorderSteps($workflowId, $stepOrder)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            foreach ($stepOrder as $index => $stepId) {
                $this->update($stepId, ['step_number' => $index + 1]);
            }

            $db->transComplete();
            return $db->transStatus();
        } catch (\Exception $e) {
            $db->transRollback();
            return false;
        }
    }

    /**
     * Get vendor steps for a workflow
     */
    public function getVendorSteps($workflowId)
    {
        return $this->select('
                process_workflow_steps.*,
                process_templates.name as process_name,
                vendors.name as vendor_name,
                vendors.contact_person,
                vendors.phone
            ')
            ->join('process_templates', 'process_templates.id = process_workflow_steps.process_template_id')
            ->join('vendors', 'vendors.id = process_workflow_steps.vendor_id')
            ->where('process_workflow_steps.workflow_template_id', $workflowId)
            ->where('process_workflow_steps.is_vendor_process', 1)
            ->orderBy('process_workflow_steps.step_number')
            ->findAll();
    }

    /**
     * Get QC required steps for a workflow
     */
    public function getQCSteps($workflowId)
    {
        return $this->select('
                process_workflow_steps.*,
                process_templates.name as process_name
            ')
            ->join('process_templates', 'process_templates.id = process_workflow_steps.process_template_id')
            ->where('process_workflow_steps.workflow_template_id', $workflowId)
            ->where('process_workflow_steps.qc_required', 1)
            ->orderBy('process_workflow_steps.step_number')
            ->findAll();
    }

    /**
     * Calculate total estimated time for a workflow
     */
    public function calculateTotalTime($workflowId)
    {
        $result = $this->selectSum('estimated_time_minutes')
                       ->where('workflow_template_id', $workflowId)
                       ->first();
        
        return $result['estimated_time_minutes'] ?? 0;
    }

    /**
     * Duplicate steps from one workflow to another
     */
    public function duplicateSteps($sourceWorkflowId, $targetWorkflowId)
    {
        $sourceSteps = $this->where('workflow_template_id', $sourceWorkflowId)->findAll();
        
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            foreach ($sourceSteps as $step) {
                unset($step['id']);
                $step['workflow_template_id'] = $targetWorkflowId;
                $this->insert($step);
            }

            $db->transComplete();
            return $db->transStatus();
        } catch (\Exception $e) {
            $db->transRollback();
            return false;
        }
    }
}
