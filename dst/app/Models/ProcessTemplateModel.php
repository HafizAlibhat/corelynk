<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessTemplateModel extends Model
{
    protected $table            = 'process_templates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name', 'description', 'category', 'category_id', 'is_vendor_process', 'vendor_id', 
    'standard_time_minutes', 'is_active', 'created_by'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'             => 'required|min_length[2]|max_length[100]',
        'category_id'      => 'permit_empty|integer|is_not_unique[process_categories.id]',
        'vendor_id'        => 'permit_empty|integer|is_not_unique[vendors.id]',
        'standard_time_minutes' => 'permit_empty|integer|greater_than_equal_to[0]',
        'is_vendor_process' => 'permit_empty|in_list[0,1]',
        'is_active'        => 'permit_empty|in_list[0,1]'
    ];

    protected $validationMessages = [
        'name' => [
            'required'    => 'Process template name is required',
            'min_length'  => 'Process template name must be at least 2 characters'
        ],
        'vendor_id' => [
            'is_not_unique' => 'Selected vendor does not exist'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    /**
     * Get process templates with filters and pagination
     */
    public function getProcessTemplatesWithFilters($searchTerm = null, $categoryFilter = null, $vendorFilter = null, $statusFilter = null, $perPage = 20): array
    {
        $builder = $this->select('process_templates.*, vendors.name as vendor_name, pc.name as category_name')
                        ->join('vendors', 'vendors.id = process_templates.vendor_id', 'left')
                        ->join('process_categories pc', 'pc.id = process_templates.category_id', 'left');

        // Apply search filter
        if (!empty($searchTerm)) {
            $builder->groupStart()
                    ->like('process_templates.name', $searchTerm)
                    ->orLike('process_templates.description', $searchTerm)
                    ->orLike('pc.name', $searchTerm)
                    ->orLike('vendors.name', $searchTerm)
                    ->groupEnd();
        }

        // Apply category filter
        if (!empty($categoryFilter)) {
            $builder->where('process_templates.category_id', $categoryFilter);
        }

        // Apply vendor filter
        if (!empty($vendorFilter)) {
            $builder->where('process_templates.vendor_id', $vendorFilter);
        }

        // Apply status filter
        if ($statusFilter !== null && $statusFilter !== '') {
            $builder->where('process_templates.is_active', (int) $statusFilter);
        }

        $builder->orderBy('pc.name', 'ASC')
                ->orderBy('process_templates.name', 'ASC');

        return $builder->paginate($perPage);
    }

    /**
     * Get process templates by category
     */
    public function getTemplatesByCategory($categoryId = null): array
    {
        $builder = $this->where('is_active', true);
        
        if ($categoryId) {
            $builder->where('category_id', $categoryId);
        }
        
        return $builder->orderBy('name', 'ASC')->findAll();
    }

    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        $db = \Config\Database::connect();
        $result = $db->table('process_categories')
                    ->select('id, name')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->get()
                    ->getResultArray();
        
        $categories = [];
        foreach ($result as $row) {
            $categories[$row['id']] = $row['name'];
        }
        
        return $categories;
    }

    /**
     * Get process template with vendor details
     */
    public function getTemplateWithDetails($id): array|null
    {
        return $this->select('process_templates.*, vendors.name as vendor_name, vendors.contact_person, vendors.phone')
                    ->join('vendors', 'vendors.id = process_templates.vendor_id', 'left')
                    ->find($id);
    }

    /**
     * Get templates for dropdown/select options
     */
    public function getTemplatesForSelect($categoryId = null): array
    {
        $builder = $this->select('process_templates.id, process_templates.name, pc.name as category_name, process_templates.standard_time_minutes')
                        ->join('process_categories pc', 'pc.id = process_templates.category_id', 'left')
                        ->where('process_templates.is_active', true);
        
        if ($categoryId) {
            $builder->where('process_templates.category_id', $categoryId);
        }
        
        $templates = $builder->orderBy('pc.name', 'ASC')
                           ->orderBy('process_templates.name', 'ASC')
                           ->findAll();
        
        $options = [];
        foreach ($templates as $template) {
            $options[$template['id']] = $template['name'] . ' (' . $template['standard_time_minutes'] . ' min)';
        }
        
        return $options;
    }

    /**
     * Duplicate a process template
     */
    public function duplicateTemplate($id, $newName): bool
    {
        $template = $this->find($id);
        if (!$template) {
            return false;
        }
        
        unset($template['id'], $template['created_at'], $template['updated_at']);
        $template['name'] = $newName;
        
        return $this->insert($template) !== false;
    }

    /**
     * Get usage count for a template
     */
    public function getUsageCount($templateId): int
    {
        $productProcessModel = new ProductProcessModel();
        return $productProcessModel->where('process_template_id', $templateId)->countAllResults();
    }

    /**
     * Get process templates with category information
     */
    public function getProcessTemplatesWithCategories()
    {
        return $this->select('process_templates.*, pc.name as category_name')
                   ->join('process_categories pc', 'pc.id = process_templates.category_id', 'left')
                   ->where('process_templates.is_active', true)
                   ->orderBy('pc.name, process_templates.name')
                   ->findAll();
    }

    /**
     * Search templates
     */
    public function searchTemplates($search, $categoryId = null)
    {
        $builder = $this->where('is_active', true)
                       ->groupStart()
                       ->like('name', $search)
                       ->orLike('description', $search)
                       ->groupEnd();
        
        if ($categoryId) {
            $builder = $builder->where('category_id', $categoryId);
        }
        
        return $builder->orderBy('name')->findAll();
    }

    /**
     * Get all active processes for dropdown/selection
     */
    public function getActiveProcesses(): array
    {
        return $this->select('id, name, standard_time_minutes, description')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }
}
