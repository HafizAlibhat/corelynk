<?php

namespace App\Controllers;

use App\Models\ProcessWorkflowTemplateModel;
use App\Models\ProcessWorkflowStepModel;
use App\Models\ProductWorkflowAssignmentModel;
use App\Models\ProcessTemplateModel;
use App\Models\ProcessCategoryModel;
use App\Models\ProductModel;

class WorkflowTemplates extends BaseController
{
    protected $workflowModel;
    protected $stepModel;
    protected $assignmentModel;
    protected $processModel;
    protected $categoryModel;
    protected $productModel;

    public function __construct()
    {
        $this->workflowModel = new ProcessWorkflowTemplateModel();
        $this->stepModel = new ProcessWorkflowStepModel();
        $this->assignmentModel = new ProductWorkflowAssignmentModel();
        $this->processModel = new ProcessTemplateModel();
        $this->categoryModel = new ProcessCategoryModel();
        $this->productModel = new ProductModel();
    }

    public function index()
    {
        $data = [
            'title' => 'Process Workflow Templates',
            'workflows' => $this->workflowModel->getWorkflowsWithCategories(),
            'categories' => $this->categoryModel->getActiveCategoriesArray()
        ];

        return view('workflow_templates/index', $data);
    }

    public function create()
    {
        $data = [
            'title' => 'Create Workflow Template',
            'categories' => $this->categoryModel->getActiveCategoriesArray(),
            'processes' => $this->processModel->getActiveProcesses()
        ];

        return view('workflow_templates/create', $data);
    }

    public function store()
    {
        $validation = \Config\Services::validation();
        
        $validation->setRules([
            'name' => 'required|min_length[3]|max_length[100]',
            'description' => 'permit_empty|max_length[1000]',
            'category_id' => 'permit_empty|integer',
            'steps' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Create workflow template
            $workflowData = [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'category_id' => $this->request->getPost('category_id') ?: null,
                'created_by' => session('user_id')
            ];

            $workflowId = $this->workflowModel->insert($workflowData);

            if (!$workflowId) {
                throw new \Exception('Failed to create workflow template');
            }

            // Create workflow steps
            $steps = json_decode($this->request->getPost('steps'), true);
            $totalTime = 0;

            foreach ($steps as $step) {
                $stepData = [
                    'workflow_template_id' => $workflowId,
                    'step_number' => $step['step_number'],
                    'process_template_id' => $step['process_template_id'],
                    'description' => $step['description'] ?? '',
                    'estimated_time_minutes' => $step['estimated_time_minutes'] ?? 0,
                    'qc_required' => isset($step['qc_required']) ? 1 : 0
                ];

                $this->stepModel->insert($stepData);
                $totalTime += $stepData['estimated_time_minutes'];
            }

            // Update total estimated time
            $this->workflowModel->update($workflowId, ['estimated_total_time_minutes' => $totalTime]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            return redirect()->to('/workflow-templates')->with('success', 'Workflow template created successfully');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to create workflow template: ' . $e->getMessage());
        }
    }

    public function view($id)
    {
        $workflow = $this->workflowModel->getWorkflowWithDetails($id);
        
        if (!$workflow) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Workflow template not found');
        }

        $data = [
            'title' => 'Workflow Template: ' . $workflow['name'],
            'workflow' => $workflow,
            'steps' => $this->stepModel->getWorkflowSteps($id),
            'products' => $this->assignmentModel->getAssignedProducts($id)
        ];

        return view('workflow_templates/view', $data);
    }

    public function edit($id)
    {
        $workflow = $this->workflowModel->find($id);
        
        if (!$workflow) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Workflow template not found');
        }

        $data = [
            'title' => 'Edit Workflow Template',
            'workflow' => $workflow,
            'categories' => $this->categoryModel->getActiveCategoriesArray(),
            'processes' => $this->processModel->getActiveProcesses(),
            'steps' => $this->stepModel->getWorkflowSteps($id)
        ];

        return view('workflow_templates/edit', $data);
    }

    public function update($id)
    {
        $workflow = $this->workflowModel->find($id);
        
        if (!$workflow) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Workflow template not found');
        }

        $validation = \Config\Services::validation();
        
        $validation->setRules([
            'name' => 'required|min_length[3]|max_length[100]',
            'description' => 'permit_empty|max_length[1000]',
            'category_id' => 'permit_empty|integer',
            'steps' => 'required'
        ]);

        if (!$validation->withRequest($this->request)->run()) {
            return redirect()->back()->withInput()->with('errors', $validation->getErrors());
        }

        $db = \Config\Database::connect();
        $db->transStart();

        try {
            // Update workflow template
            $workflowData = [
                'name' => $this->request->getPost('name'),
                'description' => $this->request->getPost('description'),
                'category_id' => $this->request->getPost('category_id') ?: null
            ];

            $this->workflowModel->update($id, $workflowData);

            // Delete existing steps
            $this->stepModel->where('workflow_template_id', $id)->delete();

            // Create new workflow steps
            $steps = json_decode($this->request->getPost('steps'), true);
            $totalTime = 0;

            foreach ($steps as $step) {
                $stepData = [
                    'workflow_template_id' => $id,
                    'step_number' => $step['step_number'],
                    'process_template_id' => $step['process_template_id'],
                    'description' => $step['description'] ?? '',
                    'estimated_time_minutes' => $step['estimated_time_minutes'] ?? 0,
                    'qc_required' => isset($step['qc_required']) ? 1 : 0
                ];

                $this->stepModel->insert($stepData);
                $totalTime += $stepData['estimated_time_minutes'];
            }

            // Update total estimated time
            $this->workflowModel->update($id, ['estimated_total_time_minutes' => $totalTime]);

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            return redirect()->to('/workflow-templates/' . $id)->with('success', 'Workflow template updated successfully');

        } catch (\Exception $e) {
            $db->transRollback();
            return redirect()->back()->withInput()->with('error', 'Failed to update workflow template: ' . $e->getMessage());
        }
    }

    public function delete($id)
    {
        $workflow = $this->workflowModel->find($id);
        
        if (!$workflow) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Workflow template not found');
        }

        // Check if workflow is assigned to any products
        $assignedProducts = $this->assignmentModel->where('workflow_template_id', $id)->countAllResults();
        
        if ($assignedProducts > 0) {
            return redirect()->back()->with('error', 'Cannot delete workflow template that is assigned to products');
        }

        if ($this->workflowModel->delete($id)) {
            return redirect()->to('/workflow-templates')->with('success', 'Workflow template deleted successfully');
        } else {
            return redirect()->back()->with('error', 'Failed to delete workflow template');
        }
    }

    public function assignToProduct()
    {
        $productId = $this->request->getPost('product_id');
        $workflowId = $this->request->getPost('workflow_id');

        if (!$productId || !$workflowId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing required parameters']);
        }

        $assignmentData = [
            'product_id' => $productId,
            'workflow_template_id' => $workflowId,
            'assigned_by' => session('user_id')
        ];

        if ($this->assignmentModel->insert($assignmentData)) {
            return $this->response->setJSON(['success' => true, 'message' => 'Workflow assigned to product successfully']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to assign workflow to product']);
        }
    }

    public function removeFromProduct()
    {
        $productId = $this->request->getPost('product_id');
        $workflowId = $this->request->getPost('workflow_id');

        if (!$productId || !$workflowId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing required parameters']);
        }

        if ($this->assignmentModel->where(['product_id' => $productId, 'workflow_template_id' => $workflowId])->delete()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Workflow removed from product successfully']);
        } else {
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to remove workflow from product']);
        }
    }
}
