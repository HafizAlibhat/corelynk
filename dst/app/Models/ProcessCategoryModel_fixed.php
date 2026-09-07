<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessCategoryModel extends Model
{
    protected $table            = 'process_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $insertID         = 0;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name',
        'description', 
        'is_active',
        'created_at',
        'updated_at'
    ];

    // Dates
    protected $useTimestamps = false;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [
        'name' => 'required|min_length[2]|max_length[100]',
        'description' => 'permit_empty|max_length[500]',
        'is_active' => 'permit_empty|in_list[0,1]'
    ];
    protected $validationMessages   = [
        'name' => [
            'required' => 'Category name is required',
            'min_length' => 'Category name must be at least 2 characters',
            'max_length' => 'Category name cannot exceed 100 characters'
        ]
    ];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Get active categories for dropdown (returns associative array)
     */
    public function getActiveCategories()
    {
        $categories = $this->where('is_active', 1)
                          ->orderBy('name', 'ASC')
                          ->findAll();
        
        $result = [];
        foreach ($categories as $category) {
            $result[$category['id']] = $category['name'];
        }
        
        return $result;
    }

    /**
     * Get category with process count
     */
    public function getCategoryWithCount($id)
    {
        $category = $this->find($id);
        if ($category) {
            $processModel = new ProcessModel();
            $templateModel = new ProcessTemplateModel();
            
            $category['process_count'] = $processModel->where('category_id', $id)->countAllResults();
            $category['template_count'] = $templateModel->where('category_id', $id)->countAllResults();
        }
        
        return $category;
    }

    /**
     * Search categories by name
     */
    public function searchByName($term, $limit = 10)
    {
        return $this->like('name', $term)
                   ->where('is_active', 1)
                   ->orderBy('name', 'ASC')
                   ->limit($limit)
                   ->findAll();
    }

    /**
     * Check if category can be deleted
     */
    public function canDelete($id)
    {
        $processModel = new ProcessModel();
        $templateModel = new ProcessTemplateModel();
        
        $processCount = $processModel->where('category_id', $id)->countAllResults();
        $templateCount = $templateModel->where('category_id', $id)->countAllResults();
        
        return ($processCount === 0 && $templateCount === 0);
    }

    /**
     * Get categories with statistics
     */
    public function getCategoriesWithStats()
    {
        $categories = $this->orderBy('name', 'ASC')->findAll();
        
        $processModel = new ProcessModel();
        $templateModel = new ProcessTemplateModel();
        
        foreach ($categories as &$category) {
            $category['process_count'] = $processModel->where('category_id', $category['id'])->countAllResults();
            $category['template_count'] = $templateModel->where('category_id', $category['id'])->countAllResults();
            $category['total_items'] = $category['process_count'] + $category['template_count'];
        }
        
        return $categories;
    }
}
