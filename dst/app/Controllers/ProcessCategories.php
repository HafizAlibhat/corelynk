<?php

namespace App\Controllers;

use App\Models\ProcessCategoryModel;
use App\Models\ProcessModel;
use App\Models\ProcessTemplateModel;

class ProcessCategories extends BaseController
{
    protected $categoryModel;
    protected $processModel;
    protected $processTemplateModel;

    public function __construct()
    {
        $this->categoryModel = new ProcessCategoryModel();
        $this->processModel = new ProcessModel();
        $this->processTemplateModel = new ProcessTemplateModel();
    }

    /**
     * Display categories list
     */
    public function index()
    {
        $this->requireAuth();

        $search = $this->request->getGet('search');
        $status = $this->request->getGet('status');

        $builder = $this->categoryModel;

        if ($search) {
            $builder = $builder->groupStart()
                             ->like('name', $search)
                             ->orLike('description', $search)
                             ->groupEnd();
        }

        if ($status !== null && $status !== '') {
            $builder = $builder->where('is_active', $status);
        }

        $categories = $builder->orderBy('name', 'ASC')->findAll();

        // Get process count for each category
        foreach ($categories as &$category) {
            $category['process_count'] = $this->processModel->where('category_id', $category['id'])->countAllResults();
            $category['template_count'] = $this->processTemplateModel->where('category_id', $category['id'])->countAllResults();
        }

        $data = $this->setPageData([
            'page_title' => 'Process Categories',
            'categories' => $categories,
            'search' => $search,
            'status' => $status,
            'can_create' => true,
            'can_edit' => true,
            'can_delete' => true
        ]);

        return view('process_categories/index', $data);
    }

    /**
     * Display create category form
     */
    public function create()
    {
        $this->requireAuth();

        $data = $this->setPageData([
            'page_title' => 'Create Process Category',
            'category' => null,
            'validation' => \Config\Services::validation()
        ]);

        return view('process_categories/form', $data);
    }

    /**
     * Handle category creation
     */
    public function store()
    {
        $this->requireAuth();

        $rules = [
            'name' => 'required|min_length[2]|max_length[100]|is_unique[process_categories.name]',
            'description' => 'permit_empty|max_length[500]'
        ];
        try {
            if (!$this->validate($rules)) {
                if ($this->request->isAJAX()) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Validation failed', 'errors' => $this->validator->getErrors()], 400);
                }
                return redirect()->back()
                               ->withInput()
                               ->with('validation', $this->validator->getErrors());
            }

            $data = [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'is_active' => $this->request->getPost('is_active') ? 1 : 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            if ($this->categoryModel->save($data)) {
                // Fetch created category for AJAX callers
                $insertId = $this->categoryModel->getInsertID();
                $created = $insertId ? $this->categoryModel->find($insertId) : null;

                if ($this->request->isAJAX()) {
                    return $this->jsonResponse(['success' => true, 'message' => 'Process category created successfully', 'category' => $created]);
                }
                return redirect()->to('/process-categories')->with('success', 'Process category created successfully.');
            } else {
                if ($this->request->isAJAX()) {
                    return $this->jsonResponse(['success' => false, 'message' => 'Failed to create process category'], 500);
                }
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'Failed to create process category.');
            }
        } catch (\Throwable $e) {
            // Log the error and return a friendly message for AJAX requests
            log_message('error', '[ProcessCategories::store] ' . $e->getMessage());

            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'An error occurred while creating the category. Please ensure the database is migrated and try again.'
                ], 500);
            }

            return redirect()->back()
                           ->withInput()
                           ->with('error', 'An error occurred while creating the category. Please ensure the database is migrated and try again.');
        }
    }

    /**
     * Display edit category form
     */
    public function edit($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process category not found');
        }

        $data = $this->setPageData([
            'page_title' => 'Edit Process Category - ' . $category['name'],
            'category' => $category,
            'validation' => \Config\Services::validation()
        ]);

        return view('process_categories/form', $data);
    }

    /**
     * Handle category update
     */
    public function update($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process category not found');
        }

        $rules = [
            'name' => "required|min_length[2]|max_length[100]|is_unique[process_categories.name,id,$id]",
            'description' => 'permit_empty|max_length[500]'
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->validator->getErrors());
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($this->categoryModel->update($id, $data)) {
            return redirect()->to('/process-categories')->with('success', 'Process category updated successfully.');
        } else {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to update process category.');
        }
    }

    /**
     * Delete category
     */
    public function delete($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            return $this->jsonResponse(['success' => false, 'message' => 'Process category not found'], 404);
        }

        // Check if category has associated processes or templates
        $processCount = $this->processModel->where('category_id', $id)->countAllResults();
        $templateCount = $this->processTemplateModel->where('category_id', $id)->countAllResults();

        if ($processCount > 0 || $templateCount > 0) {
            return $this->jsonResponse([
                'success' => false,
                'message' => "Cannot delete category. It has $processCount process(es) and $templateCount template(s). Please reassign or delete those first."
            ], 400);
        }

        if ($this->categoryModel->delete($id)) {
            return $this->jsonResponse(['success' => true, 'message' => 'Process category deleted successfully.']);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete process category.'], 500);
        }
    }

    /**
     * Toggle category status
     */
    public function toggleStatus($id = null)
    {
        $this->requireAuth();

        $category = $this->categoryModel->find($id);
        if (!$category) {
            return $this->jsonResponse(['success' => false, 'message' => 'Process category not found'], 404);
        }

        $newStatus = !$category['is_active'];
        $data = [
            'is_active' => $newStatus,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if ($this->categoryModel->update($id, $data)) {
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return $this->jsonResponse([
                'success' => true,
                'message' => "Process category {$statusText} successfully.",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update category status.'], 500);
        }
    }

    /**
     * Get categories for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'active':
                $categories = $this->categoryModel->where('is_active', true)
                                                 ->orderBy('name', 'ASC')
                                                 ->findAll();
                return $this->jsonResponse($categories);
                
            case 'search':
                $term = $this->request->getGet('term');
                $categories = $this->categoryModel->like('name', $term)
                                                 ->where('is_active', true)
                                                 ->select('id, name')
                                                 ->limit(10)
                                                 ->findAll();
                return $this->jsonResponse($categories);
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }
}
