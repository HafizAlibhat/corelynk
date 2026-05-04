<?php

namespace App\Controllers;

use App\Models\ProcessTemplateModel;
use App\Models\VendorModel;
use App\Models\ProductProcessModel;

class ProcessTemplates extends BaseController
{
    protected $processTemplateModel;

    public function __construct()
    {
        $this->processTemplateModel = new ProcessTemplateModel();
    }

    /**
     * Display process templates list
     */
    public function index()
    {
        $this->requireAuth();
        
        // For now, let's bypass permission check for testing
        // $this->requirePermission('process_templates.view');

        $searchTerm = $this->request->getGet('search');
        $categoryFilter = $this->request->getGet('category');
        $vendorFilter = $this->request->getGet('vendor');
        $statusFilter = $this->request->getGet('status');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        try {
            $processTemplates = $this->processTemplateModel->getProcessTemplatesWithFilters(
                $searchTerm, $categoryFilter, $vendorFilter, $statusFilter, $perPage
            );

            // Get filter options
            $vendorModel = new VendorModel();
            $vendors = $vendorModel->where('is_active', true)->findAll();
            $categories = $this->processTemplateModel->getCategories();

            $data = $this->setPageData([
                'page_title' => 'Process Templates Management',
                'process_templates' => $processTemplates,
                'pager' => $this->processTemplateModel->pager,
                'vendors' => $vendors,
                'categories' => $categories,
                'current_search' => $searchTerm,
                'current_category' => $categoryFilter,
                'current_vendor' => $vendorFilter,
                'current_status' => $statusFilter,
                'per_page' => $perPage,
                'can_create' => true, // $this->hasPermission('process_templates.create'),
                'can_edit' => true, // $this->hasPermission('process_templates.edit'),
                'can_delete' => true // $this->hasPermission('process_templates.delete')
            ]);

            return view('process_templates/index', $data);
            
        } catch (\Exception $e) {
            log_message('error', 'Process Templates index error: ' . $e->getMessage());
            return redirect()->to('/dashboard')->with('error', 'Error loading process templates.');
        }
    }

    /**
     * Display single process template details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('process_templates.view');

        $processTemplate = $this->processTemplateModel->getTemplateWithDetails($id);
        if (!$processTemplate) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process Template not found');
        }

        // Get usage information
        $productProcessModel = new ProductProcessModel();
        $usageCount = $this->processTemplateModel->getUsageCount($id);
        $productsUsing = $productProcessModel->getProductsByTemplate($id);

        $data = $this->setPageData([
            'page_title' => 'Process Template Details - ' . $processTemplate['name'],
            'process_template' => $processTemplate,
            'usage_count' => $usageCount,
            'products_using' => $productsUsing,
            'can_edit' => $this->hasPermission('process_templates.edit'),
            'can_delete' => $this->hasPermission('process_templates.delete')
        ]);

        return view('process_templates/show', $data);
    }

    /**
     * Show create form
     */
    public function create()
    {
        $this->requireAuth();
        $this->requirePermission('process_templates.create');

        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        $data = $this->setPageData([
            'page_title' => 'Create Process Template',
            'vendors' => $vendors,
            'validation' => \Config\Services::validation()
        ]);

        return view('process_templates/form', $data);
    }

    /**
     * Handle create form submission
     */
    public function store()
    {
        $this->requireAuth();
        $this->requirePermission('process_templates.create');

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'category' => 'required|min_length[2]|max_length[50]',
            'description' => 'permit_empty|max_length[1000]',
            'standard_time_minutes' => 'permit_empty|integer|greater_than_equal_to[0]',
            'is_vendor_process' => 'permit_empty|in_list[0,1]',
            'vendor_id' => 'required_if[is_vendor_process,1]|permit_empty|integer',
            'qc_checklist.*' => 'permit_empty|max_length[255]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        try {
            // Prepare QC checklist as JSON
            $qcItems = $this->request->getPost('qc_checklist') ?? [];
            $qcItems = array_filter($qcItems); // Remove empty items
            
            $data = [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'category' => $this->request->getPost('category'),
                'standard_time_minutes' => $this->request->getPost('standard_time_minutes') ?: 0,
                'is_vendor_process' => $this->request->getPost('is_vendor_process') ? 1 : 0,
                'vendor_id' => $this->request->getPost('vendor_id') ?: null,
                'qc_checklist' => !empty($qcItems) ? json_encode($qcItems) : null,
                'is_active' => 1,
                'created_by' => session('user')['id']
            ];

            if ($this->processTemplateModel->insert($data)) {
                return redirect()->to('/process-templates')->with('success', 'Process template created successfully.');
            } else {
            }
        } catch (\Exception $e) {
            log_message('error', 'Process template creation error: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'An error occurred while creating the process template.');
        }
    }

    public function edit($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('process_templates.edit');

        $processTemplate = $this->processTemplateModel->find($id);
        if (!$processTemplate) {
        }

        $vendorModel = new VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        // Parse QC checklist JSON
        $qcChecklist = [];
        if (!empty($processTemplate['qc_checklist'])) {
            $qcChecklist = json_decode($processTemplate['qc_checklist'], true) ?: [];
        }

        $data = $this->setPageData([
            'page_title' => 'Edit Process Template',
            'process_template' => $processTemplate,
            'qc_checklist' => $qcChecklist,
            'vendors' => $vendors,
            'validation' => \Config\Services::validation()
        ]);

        return view('process_templates/form', $data);
    }

    /**
     * Handle edit form submission
     */
    public function update($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('process_templates.edit');

        $processTemplate = $this->processTemplateModel->find($id);
        if (!$processTemplate) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process Template not found');
        }

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]',
            'category' => 'required|min_length[2]|max_length[50]',
            'description' => 'permit_empty|max_length[1000]',
            'standard_time_minutes' => 'permit_empty|integer|greater_than_equal_to[0]',
            'is_vendor_process' => 'permit_empty|in_list[0,1]',
            'vendor_id' => 'required_if[is_vendor_process,1]|permit_empty|integer',
            'qc_checklist.*' => 'permit_empty|max_length[255]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('validation', $this->validator);
        }

        try {
            // Prepare QC checklist as JSON
            $qcItems = $this->request->getPost('qc_checklist') ?? [];
            
            $data = [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'category' => $this->request->getPost('category'),
                'standard_time_minutes' => $this->request->getPost('standard_time_minutes') ?: 0,
                'is_vendor_process' => $this->request->getPost('is_vendor_process') ? 1 : 0,
            ];

            if ($this->processTemplateModel->update($id, $data)) {
                return redirect()->to('/process-templates')->with('success', 'Process template updated successfully.');
            } else {
                return redirect()->back()->withInput()->with('error', 'Failed to update process template.');
            }
            log_message('error', 'Process template update error: ' . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'An error occurred while updating the process template.');
        }
    }

    /**
     * Delete process template
     */
    public function delete($id = null)
    {
        $this->requireAuth();
        
        // Skip permission check for testing
        // $this->requirePermission('process_templates.delete');

        $processTemplate = $this->processTemplateModel->find($id);
        if (!$processTemplate) {
            return $this->response->setJSON(['success' => false, 'message' => 'Process template not found.']);
        }

        // Check if template is being used
        $usageCount = $this->processTemplateModel->getUsageCount($id);
        if ($usageCount > 0) {
            return $this->response->setJSON([
                'success' => false, 
                'message' => "Cannot delete process template. It is currently used by $usageCount product(s)."
            ]);
        }

        try {
        } catch (\Exception $e) {
            log_message('error', 'Process template deletion error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while deleting the process template.']);
        }

    /**
     * Duplicate a process template
     */
    public function duplicate($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('process_templates.create');

        $processTemplate = $this->processTemplateModel->find($id);
        if (!$processTemplate) {
            return $this->response->setJSON(['success' => false, 'message' => 'Process template not found.']);
        }

        $newName = $this->request->getPost('new_name');
        if (empty($newName)) {
            return $this->response->setJSON(['success' => false, 'message' => 'New name is required.']);
        }

        try {
            if ($this->processTemplateModel->duplicateTemplate($id, $newName)) {
                return $this->response->setJSON(['success' => true, 'message' => 'Process template duplicated successfully.']);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to duplicate process template.']);
            }
        } catch (\Exception $e) {
            log_message('error', 'Process template duplication error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while duplicating the process template.']);
        }
    }

    /**
     * Get templates by category (AJAX)
     */
    public function getByCategory()
    {
        $this->requireAuth();
        
        $category = $this->request->getGet('category');
        $templates = $this->processTemplateModel->getTemplatesByCategory($category);
        
        return $this->response->setJSON(['success' => true, 'templates' => $templates]);
    }

    /**
     * Get templates for select options (AJAX)
     */
    public function getForSelect()
    {
        $this->requireAuth();
        
        $category = $this->request->getGet('category');
        $templates = $this->processTemplateModel->getTemplatesForSelect($category);
        
        return $this->response->setJSON(['success' => true, 'options' => $templates]);
    }
}
