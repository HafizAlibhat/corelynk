<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductWorkflowAssignmentModel extends Model
{
    protected $table = 'product_workflow_assignments';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'product_id',
        'workflow_template_id',
        'assigned_by',
        'is_active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'assigned_at';
    protected $updatedField = '';

    // Validation
    protected $validationRules = [
        'product_id' => 'required|integer',
        'workflow_template_id' => 'required|integer',
        'assigned_by' => 'permit_empty|integer',
        'is_active' => 'permit_empty|boolean'
    ];

    protected $validationMessages = [
        'product_id' => [
            'required' => 'Product ID is required',
            'integer' => 'Product ID must be a number'
        ],
        'workflow_template_id' => [
            'required' => 'Workflow template ID is required',
            'integer' => 'Workflow template ID must be a number'
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
     * Get assigned products for a workflow template
     */
    public function getAssignedProducts($workflowId)
    {
        return $this->select('
                product_workflow_assignments.*,
                products.name as product_name,
                products.code as product_code,
                product_categories.name as category_name,
                users.username as assigned_by_name
            ')
            ->join('products', 'products.id = product_workflow_assignments.product_id')
            ->join('product_categories', 'product_categories.id = products.category_id', 'left')
            ->join('users', 'users.id = product_workflow_assignments.assigned_by', 'left')
            ->where('product_workflow_assignments.workflow_template_id', $workflowId)
            ->where('product_workflow_assignments.is_active', 1)
            ->orderBy('products.name')
            ->findAll();
    }

    /**
     * Get assigned workflows for a product
     */
    public function getAssignedWorkflows($productId)
    {
        return $this->select('
                product_workflow_assignments.*,
                process_workflow_templates.name as workflow_name,
                process_workflow_templates.description as workflow_description,
                process_workflow_templates.estimated_total_time_minutes,
                process_categories.name as category_name,
                users.username as assigned_by_name
            ')
            ->join('process_workflow_templates', 'process_workflow_templates.id = product_workflow_assignments.workflow_template_id')
            ->join('process_categories', 'process_categories.id = process_workflow_templates.category_id', 'left')
            ->join('users', 'users.id = product_workflow_assignments.assigned_by', 'left')
            ->where('product_workflow_assignments.product_id', $productId)
            ->where('product_workflow_assignments.is_active', 1)
            ->orderBy('process_workflow_templates.name')
            ->findAll();
    }

    /**
     * Check if a workflow is assigned to a product
     */
    public function isWorkflowAssigned($productId, $workflowId)
    {
        return $this->where([
            'product_id' => $productId,
            'workflow_template_id' => $workflowId,
            'is_active' => 1
        ])->first() !== null;
    }

    /**
     * Get all product-workflow assignments with details
     */
    public function getAllAssignmentsWithDetails()
    {
        return $this->select('
                product_workflow_assignments.*,
                products.name as product_name,
                products.code as product_code,
                process_workflow_templates.name as workflow_name,
                process_categories.name as category_name,
                users.username as assigned_by_name
            ')
            ->join('products', 'products.id = product_workflow_assignments.product_id')
            ->join('process_workflow_templates', 'process_workflow_templates.id = product_workflow_assignments.workflow_template_id')
            ->join('process_categories', 'process_categories.id = process_workflow_templates.category_id', 'left')
            ->join('users', 'users.id = product_workflow_assignments.assigned_by', 'left')
            ->where('product_workflow_assignments.is_active', 1)
            ->orderBy('products.name')
            ->findAll();
    }

    /**
     * Assign workflow to product
     */
    public function assignWorkflow($productId, $workflowId, $assignedBy)
    {
        // Check if already assigned
        if ($this->isWorkflowAssigned($productId, $workflowId)) {
            return false; // Already assigned
        }

        $data = [
            'product_id' => $productId,
            'workflow_template_id' => $workflowId,
            'assigned_by' => $assignedBy,
            'is_active' => 1
        ];

        return $this->insert($data);
    }

    /**
     * Remove workflow from product
     */
    public function removeWorkflow($productId, $workflowId)
    {
        return $this->where([
            'product_id' => $productId,
            'workflow_template_id' => $workflowId
        ])->delete();
    }

    /**
     * Deactivate workflow assignment
     */
    public function deactivateAssignment($productId, $workflowId)
    {
        return $this->where([
            'product_id' => $productId,
            'workflow_template_id' => $workflowId
        ])->set(['is_active' => 0])->update();
    }

    /**
     * Reactivate workflow assignment
     */
    public function reactivateAssignment($productId, $workflowId)
    {
        return $this->where([
            'product_id' => $productId,
            'workflow_template_id' => $workflowId
        ])->set(['is_active' => 1])->update();
    }

    /**
     * Get workflow assignment statistics
     */
    public function getAssignmentStats()
    {
        $db = \Config\Database::connect();
        
        // Total assignments
        $totalAssignments = $this->where('is_active', 1)->countAllResults();
        
        // Products with workflows
        $productsWithWorkflows = $this->distinct()
                                     ->select('product_id')
                                     ->where('is_active', 1)
                                     ->countAllResults();
        
        // Most used workflows
        $mostUsedWorkflows = $db->query("
            SELECT 
                pwt.name as workflow_name,
                COUNT(pwa.id) as assignment_count
            FROM product_workflow_assignments pwa
            JOIN process_workflow_templates pwt ON pwt.id = pwa.workflow_template_id
            WHERE pwa.is_active = 1
            GROUP BY pwa.workflow_template_id
            ORDER BY assignment_count DESC
            LIMIT 5
        ")->getResultArray();
        
        return [
            'total_assignments' => $totalAssignments,
            'products_with_workflows' => $productsWithWorkflows,
            'most_used_workflows' => $mostUsedWorkflows
        ];
    }

    /**
     * Bulk assign workflow to multiple products
     */
    public function bulkAssignWorkflow($productIds, $workflowId, $assignedBy)
    {
        $db = \Config\Database::connect();
        $db->transStart();

        try {
            $successCount = 0;
            foreach ($productIds as $productId) {
                if (!$this->isWorkflowAssigned($productId, $workflowId)) {
                    $data = [
                        'product_id' => $productId,
                        'workflow_template_id' => $workflowId,
                        'assigned_by' => $assignedBy,
                        'is_active' => 1
                    ];
                    
                    if ($this->insert($data)) {
                        $successCount++;
                    }
                }
            }

            $db->transComplete();
            
            if ($db->transStatus()) {
                return $successCount;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            $db->transRollback();
            return false;
        }
    }
}
