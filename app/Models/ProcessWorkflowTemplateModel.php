<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessWorkflowTemplateModel extends Model
{
    protected $table = 'process_workflow_templates';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'name',
        'description', 
        'category_id',
        'estimated_total_time_minutes',
        'is_active',
        'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'name' => 'required|min_length[3]|max_length[100]',
        'description' => 'permit_empty|max_length[1000]',
        'category_id' => 'permit_empty|integer',
        'estimated_total_time_minutes' => 'permit_empty|integer',
        'is_active' => 'permit_empty|boolean',
        'created_by' => 'permit_empty|integer'
    ];

    protected $validationMessages = [
        'name' => [
            'required' => 'Workflow template name is required',
            'min_length' => 'Workflow template name must be at least 3 characters',
            'max_length' => 'Workflow template name cannot exceed 100 characters'
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
     * Get all workflow templates with their categories
     */
    public function getWorkflowsWithCategories()
    {
        return $this->select('process_workflow_templates.*, process_categories.name as category_name')
                    ->join('process_categories', 'process_categories.id = process_workflow_templates.category_id', 'left')
                    ->where('process_workflow_templates.is_active', 1)
                    ->orderBy('process_workflow_templates.name')
                    ->findAll();
    }

    /**
     * Get active workflow templates
     */
    public function getActiveWorkflows()
    {
        return $this->where('is_active', 1)
                    ->orderBy('name')
                    ->findAll();
    }

    /**
     * Get workflow template with detailed information
     */
    public function getWorkflowWithDetails($id)
    {
        return $this->select('process_workflow_templates.*, process_categories.name as category_name, users.username as created_by_name')
                    ->join('process_categories', 'process_categories.id = process_workflow_templates.category_id', 'left')
                    ->join('users', 'users.id = process_workflow_templates.created_by', 'left')
                    ->where('process_workflow_templates.id', $id)
                    ->first();
    }

    /**
     * Get workflows by category
     */
    public function getWorkflowsByCategory($categoryId)
    {
        return $this->where('category_id', $categoryId)
                    ->where('is_active', 1)
                    ->orderBy('name')
                    ->findAll();
    }

    /**
     * Search workflow templates
     */
    public function searchWorkflows($searchTerm)
    {
        return $this->select('process_workflow_templates.*, process_categories.name as category_name')
                    ->join('process_categories', 'process_categories.id = process_workflow_templates.category_id', 'left')
                    ->where('process_workflow_templates.is_active', 1)
                    ->groupStart()
                        ->like('process_workflow_templates.name', $searchTerm)
                        ->orLike('process_workflow_templates.description', $searchTerm)
                        ->orLike('process_categories.name', $searchTerm)
                    ->groupEnd()
                    ->orderBy('process_workflow_templates.name')
                    ->findAll();
    }

    /**
     * Get workflow statistics
     */
    public function getWorkflowStats($id)
    {
        $db = \Config\Database::connect();
        
        // Get step count
        $stepCount = $db->table('process_workflow_steps')
                       ->where('workflow_template_id', $id)
                       ->countAllResults();
        
        // Get assigned products count
        $productCount = $db->table('product_workflow_assignments')
                          ->where('workflow_template_id', $id)
                          ->where('is_active', 1)
                          ->countAllResults();
        
        return [
            'step_count' => $stepCount,
            'assigned_products' => $productCount
        ];
    }
}
