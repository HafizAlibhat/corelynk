<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessCategoryModel extends Model
{
    protected $table            = 'process_categories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name', 'description', 'is_active'];

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
     * Get active categories as associative array (id => name)
     * Used for dropdowns that need key-value pairs
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
     * Get active categories as array of arrays
     * Used for views that need full category objects
     */
    public function getActiveCategoriesArray()
    {
        return $this->where('is_active', 1)
                   ->orderBy('name', 'ASC')
                   ->findAll();
    }

    /**
     * Get category with process count
     */
    public function getCategoryWithCount($id)
    {
        $category = $this->find($id);
        if (!$category) {
            return null;
        }

        // Count processes in this category
        $processModel = new \App\Models\ProcessModel();
        $category['process_count'] = $processModel->where('category_id', $id)->countAllResults();

        return $category;
    }

    /**
     * Get all categories with process counts
     */
    public function getCategoriesWithCounts()
    {
        $categories = $this->findAll();
        
        $processModel = new \App\Models\ProcessModel();
        $templateModel = new \App\Models\ProcessTemplateModel();
        
        foreach ($categories as &$category) {
            $category['process_count'] = $processModel->where('category_id', $category['id'])->countAllResults();
            $category['template_count'] = $templateModel->where('category_id', $category['id'])->countAllResults();
            $category['total_items'] = $category['process_count'] + $category['template_count'];
        }
        
        return $categories;
    }
}
