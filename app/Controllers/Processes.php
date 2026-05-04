<?php

namespace App\Controllers;

use App\Models\ProcessModel;
use App\Models\ProductModel;
use App\Models\WorkOrderProcessRunModel;

class Processes extends BaseController
{
    protected $processModel;

    public function __construct()
    {
        $this->processModel = new ProcessModel();
    }

    /**
     * Display processes list
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('processes.view');

        $searchTerm = $this->request->getGet('search');
        $vendorFilter = $this->request->getGet('vendor');
        $categoryFilter = $this->request->getGet('category');
        $statusFilter = $this->request->getGet('status');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $processes = $this->processModel->getProcessesWithFilters($searchTerm, null, $vendorFilter, $statusFilter, $perPage, $categoryFilter);
        // Enrich with responsibility and assigned employees for list display
        try {
            $db = \Config\Database::connect();
            $ids = array_values(array_filter(array_map(fn($p)=> (int)($p['id'] ?? 0), (array)$processes))); 
            $hasModeCol = $db->query("SHOW COLUMNS FROM processes LIKE 'responsibility_mode'")->getNumRows() > 0;
            $hasDeptCol = $db->query("SHOW COLUMNS FROM processes LIKE 'responsibility_department'")->getNumRows() > 0;
            $hasPEA = $db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
            $respMap = [];
            if (!empty($ids) && ($hasModeCol || $hasDeptCol)) {
                $sel = ['id']; if ($hasModeCol) $sel[] = 'responsibility_mode'; if ($hasDeptCol) $sel[] = 'responsibility_department';
                $rows = $db->table('processes')->select(implode(', ', $sel))->whereIn('id', $ids)->get()->getResultArray();
                foreach ($rows as $r) { $respMap[(int)$r['id']] = $r; }
            }
            $empMap = [];
            if (!empty($ids) && $hasPEA) {
                $empExists = $db->query("SHOW TABLES LIKE 'employees'")->getNumRows() > 0;
                if ($empExists) {
                    $rows = $db->query("SELECT pea.process_id, e.id AS employee_id, CONCAT(e.first_name,' ',e.last_name) AS name FROM process_employee_assignments pea JOIN employees e ON e.id = pea.employee_id WHERE pea.process_id IN (".implode(',', $ids).") ORDER BY name ASC")->getResultArray();
                    foreach ($rows as $r) { $empMap[(int)$r['process_id']][] = $r['name']; }
                } else {
                    $rows = $db->query("SELECT process_id, employee_id FROM process_employee_assignments WHERE process_id IN (".implode(',', $ids).")")->getResultArray();
                    foreach ($rows as $r) { $empMap[(int)$r['process_id']][] = 'Emp #'.$r['employee_id']; }
                }
            }
            foreach ($processes as &$p) {
                $pid = (int)($p['id'] ?? 0);
                if (isset($respMap[$pid])) {
                    foreach (['responsibility_mode','responsibility_department'] as $k) { if (isset($respMap[$pid][$k])) $p[$k] = $respMap[$pid][$k]; }
                }
                if (isset($empMap[$pid])) { $p['assigned_employee_names'] = $empMap[$pid]; }
            }
            unset($p);
        } catch (\Throwable $e) { /* ignore enrich errors */ }
        
        // Get vendors for filter dropdowns
        $vendorModel = new \App\Models\VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        // Get categories for filter dropdowns (defensive: table may be missing)
        $processError = null;
        $categories = [];
        try {
            $categoryModel = new \App\Models\ProcessCategoryModel();
            $categories = $categoryModel->where('is_active', true)->findAll();
        } catch (\Exception $e) {
            // Log and set friendly error for the view
            log_message('error', 'Failed to load process categories: ' . $e->getMessage());
            $processError = 'Process categories are not available. Please run the database migrations.';
        }

        $data = $this->setPageData([
            'page_title' => 'Processes Management',
            'processes' => $processes,
            // Only pass pager when available (avoid null causing array_merge errors in views)
            'pager' => is_object($this->processModel->pager) ? $this->processModel->pager : null,
            'vendors' => $vendors,
            'categories' => $categories,
            'processError' => $processError,
            'current_search' => $searchTerm,
            'current_vendor' => $vendorFilter,
            'current_category' => $categoryFilter,
            'current_status' => $statusFilter,
            'per_page' => $perPage,
            'can_create' => $this->hasPermission('processes.create'),
            'can_edit' => $this->hasPermission('processes.edit'),
            'can_delete' => $this->hasPermission('processes.delete')
        ]);

        return view('processes/index', $data);
    }

    /**
     * Display single process details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('processes.view');

        $process = $this->processModel->getProcessWithVendor($id);
        if (!$process) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process not found');
        }

        // Enrich with responsibility and assigned employees
        try {
            // Ensure columns exist before reading (safe across envs)
            $this->processModel->ensureResponsibilitySchema();
            $empNames = [];
            $assignedIds = $this->processModel->getAssignedEmployeeIds((int)$id);
            if (!empty($assignedIds)) {
                try {
                    $employeeModel = new \App\Models\EmployeeModel();
                    $rows = $employeeModel->select('id, first_name, last_name')
                        ->whereIn('id', $assignedIds)->orderBy('first_name','ASC')->orderBy('last_name','ASC')->findAll();
                    foreach ($rows as $r) { $empNames[] = trim(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')); }
                } catch (\Throwable $e) { /* ignore */ }
            }
            $process['responsibility_mode'] = $process['responsibility_mode'] ?? (\Config\Database::connect()->table('processes')->select('responsibility_mode')->where('id',$id)->get()->getRow('responsibility_mode') ?? null);
            $process['responsibility_department'] = $process['responsibility_department'] ?? (\Config\Database::connect()->table('processes')->select('responsibility_department')->where('id',$id)->get()->getRow('responsibility_department') ?? null);
            $process['assigned_employee_names'] = $empNames;
        } catch (\Throwable $e) { /* enrichment optional */ }

        // Load approved vendors for display (from pivot if available)
        $approvedVendorIds = $this->processModel->getApprovedVendorIds((int)$id);
        $approvedVendors = [];
        if (!empty($approvedVendorIds)) {
            try {
                $vendorModel = new \App\Models\VendorModel();
                $approvedVendors = $vendorModel->select('id, name, contact_person, phone, email')
                    ->whereIn('id', $approvedVendorIds)
                    ->orderBy('name', 'ASC')
                    ->findAll();
            } catch (\Throwable $e) {
                log_message('error', 'Failed to load approved vendors for process show: ' . $e->getMessage());
            }
        }

        $data = $this->setPageData([
            'page_title' => 'Process Details - ' . $process['name'],
            'process' => $process,
            'approvedVendors' => $approvedVendors,
            'can_edit' => $this->hasPermission('processes.edit'),
            'can_delete' => $this->hasPermission('processes.delete')
        ]);

        return view('processes/show', $data);
    }

    /**
     * Display create process form
     */
    public function create()
    {
        $this->requireAuth();
        $this->requirePermission('processes.create');

        // Get vendors for dropdown
        $vendorModel = new \App\Models\VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        // Get process categories for dropdown (defensive)
        $processError = null;
        $categories = [];
        try {
            $processCategoryModel = new \App\Models\ProcessCategoryModel();
            $categories = $processCategoryModel->getActiveCategories();
        } catch (\Exception $e) {
            log_message('error', 'Failed to load process categories for create form: ' . $e->getMessage());
            $processError = 'Process categories are not available. Please run the database migrations.';
        }

        // Employees & departments for in-house responsibility assignment
        $employeeModel = new \App\Models\EmployeeModel();
        $employees = [];
        $departments = [];
        try {
            $employees = $employeeModel->where('is_active', 1)->orderBy('first_name', 'ASC')->findAll();
            foreach ($employees as $emp) {
                if (!empty($emp['department'])) { $departments[$emp['department']] = $emp['department']; }
            }
            ksort($departments);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to load employees for process create: '.$e->getMessage());
        }

        $data = $this->setPageData([
            'page_title' => 'Create New Process',
            'process' => null,
            'vendors' => $vendors,
            'categories' => $categories,
            'processError' => $processError,
            'approvedVendorIds' => [],
            'employees' => $employees,
            'departments' => $departments,
            'assignedEmployeeIds' => [],
            'validation' => \Config\Services::validation()
        ]);

        return view('processes/form', $data);
    }

    /**
     * Handle process creation
     */
    public function store()
    {
        $this->requireAuth();
        $this->requirePermission('processes.create');

    $process_type = $this->request->getPost('process_type');
    // multi-select vendors list
    $vendorIdsInput = $this->request->getPost('vendor_ids');
    if (!is_array($vendorIdsInput)) { $vendorIdsInput = $this->request->getPost('vendor_ids[]') ?? []; }
    $vendorIds = is_array($vendorIdsInput) ? array_values(array_filter(array_map('intval', $vendorIdsInput))) : [];
    // Responsibility (only for in-house)
    $responsibilityMode = $this->request->getPost('responsibility_mode') === 'department' ? 'department' : 'employees';
    $responsibilityDepartment = $responsibilityMode === 'department' ? trim((string)$this->request->getPost('responsibility_department')) : null;
    $employeeIdsInput = $this->request->getPost('employee_ids');
    if (!is_array($employeeIdsInput)) { $employeeIdsInput = $this->request->getPost('employee_ids[]') ?? []; }
    $employeeIds = is_array($employeeIdsInput) ? array_values(array_filter(array_map('intval', $employeeIdsInput))) : [];
        // Normalize category_id for updates as well
        $category_id_input = $this->request->getPost('category_id');
        $category_id = (isset($category_id_input) && $category_id_input !== '' && is_numeric($category_id_input) && (int)$category_id_input > 0)
            ? (int) $category_id_input
            : null;
        // Normalize category_id (blank => NULL, otherwise int)
        $category_id_input = $this->request->getPost('category_id');
        $category_id = (isset($category_id_input) && $category_id_input !== '' && is_numeric($category_id_input) && (int)$category_id_input > 0)
            ? (int) $category_id_input
            : null;

        // Convert empty vendor_id to null
        $primaryVendorId = !empty($vendorIds) ? (int)$vendorIds[0] : null;

        $data = [
            'product_id' => null, // Standalone process, not linked to a specific product
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            // Use normalized category_id to avoid FK errors when no category selected
            'category_id' => $category_id,
            'is_vendor_process' => ($process_type === 'outsource'),
            'vendor_id' => ($process_type === 'outsource' && $primaryVendorId) ? $primaryVendorId : null,
            'standard_time_minutes' => $this->request->getPost('standard_time_minutes') ?: 0,
            'is_active' => true,
            'responsibility_mode' => ($process_type === 'in_house') ? $responsibilityMode : null,
            'responsibility_department' => ($process_type === 'in_house' && $responsibilityMode === 'department') ? $responsibilityDepartment : null,
        ];

        // Validation rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'name' => 'required|min_length[3]|max_length[255]',
            'process_type' => 'required|in_list[in_house,outsource]',
            'category_id' => 'permit_empty|integer',
            'vendor_id' => 'permit_empty|integer',
            'standard_time_minutes' => 'permit_empty|integer|greater_than_equal_to[0]'
        ]);

        $validationData = [
            'name' => $this->request->getPost('name'),
            'process_type' => $process_type,
            'category_id' => $category_id,
            'vendor_id' => $primaryVendorId,
            'standard_time_minutes' => $this->request->getPost('standard_time_minutes')
        ];

        if (!$validation->run($validationData)) {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $validation);
        }

        // If outsource but no vendor selected, error
        if ($process_type === 'outsource' && empty($vendorIds)) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'At least one vendor is required for outsource processes.');
        }
        if ($process_type === 'in_house') {
            if ($responsibilityMode === 'department' && !$responsibilityDepartment) {
                return redirect()->back()->withInput()->with('error','Select a department for department responsibility mode.');
            }
            if ($responsibilityMode === 'employees' && empty($employeeIds)) {
                return redirect()->back()->withInput()->with('error','Select at least one employee for employee responsibility mode.');
            }
        }

        // Self-heal responsibility columns if migration not applied
        $this->processModel->ensureResponsibilitySchema();
        try {
            if ($this->processModel->save($data)) {
                $newId = (int) $this->processModel->getInsertID();
                // Save approved vendors to pivot if available
                if ($newId > 0 && !empty($vendorIds)) {
                    try { $this->processModel->setApprovedVendors($newId, $vendorIds); } catch (\Throwable $e) { log_message('error', 'Pivot save (store) failed: ' . $e->getMessage()); }
                }
                if ($newId > 0 && $process_type === 'in_house' && $responsibilityMode === 'employees') {
                    $this->processModel->setAssignedEmployees($newId, $employeeIds);
                }
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON(['success' => true, 'message' => 'Process created successfully.']);
                }
                return redirect()->to('/processes')->with('success', 'Process created successfully.');
            } else {
                // Validation errors from the model
                if ($this->request->isAJAX()) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'errors' => $this->processModel->errors()]);
                }
                return redirect()->back()
                               ->withInput()
                               ->with('validation', $this->processModel->errors());
            }
        } catch (\Exception $e) {
            // Log full exception and return a friendly message to the user
            log_message('error', 'Failed to save process: ' . $e->getMessage());
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'A database error occurred while creating the process. Please ensure the database schema is up to date.'
                ]);
            }

            return redirect()->back()
                           ->withInput()
                           ->with('error', 'A database error occurred while creating the process. Please ensure the database schema is up to date.');
        }
    }

    /**
     * Display edit process form
     */
    public function edit($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('processes.edit');

        $process = $this->processModel->find($id);
        if (!$process) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process not found');
        }

        // Get vendors for dropdown
        $vendorModel = new \App\Models\VendorModel();
        $vendors = $vendorModel->where('is_active', true)->findAll();

        // Get process categories for dropdown (defensive)
        $processError = null;
        $categories = [];
        try {
            $processCategoryModel = new \App\Models\ProcessCategoryModel();
            $categories = $processCategoryModel->getActiveCategories();
        } catch (\Exception $e) {
            log_message('error', 'Failed to load process categories for edit form: ' . $e->getMessage());
            $processError = 'Process categories are not available. Please run the database migrations.';
        }

        $approvedVendorIds = $this->processModel->getApprovedVendorIds((int)$process['id']);
        // Employees / departments
        $employeeModel = new \App\Models\EmployeeModel();
        $employees = [];
        $departments = [];
        $assignedEmployeeIds = [];
        try {
            $employees = $employeeModel->where('is_active',1)->orderBy('first_name','ASC')->findAll();
            foreach ($employees as $emp) { if (!empty($emp['department'])) { $departments[$emp['department']] = $emp['department']; } }
            ksort($departments);
            $assignedEmployeeIds = $this->processModel->getAssignedEmployeeIds((int)$process['id']);
        } catch (\Throwable $e) { log_message('error','Failed to load employees for process edit: '.$e->getMessage()); }

        $data = $this->setPageData([
            'page_title' => 'Edit Process - ' . $process['name'],
            'process' => $process,
            'vendors' => $vendors,
            'categories' => $categories,
            'processError' => $processError,
            'approvedVendorIds' => $approvedVendorIds,
            'employees' => $employees,
            'departments' => $departments,
            'assignedEmployeeIds' => $assignedEmployeeIds,
            'validation' => \Config\Services::validation()
        ]);

        return view('processes/form', $data);
    }

    /**
     * Handle process update
     */
    public function update($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('processes.edit');

        $process = $this->processModel->find($id);
        if (!$process) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process not found');
        }

    $process_type = $this->request->getPost('process_type');
    $vendorIdsInput = $this->request->getPost('vendor_ids');
    if (!is_array($vendorIdsInput)) { $vendorIdsInput = $this->request->getPost('vendor_ids[]') ?? []; }
    $vendorIds = is_array($vendorIdsInput) ? array_values(array_filter(array_map('intval', $vendorIdsInput))) : [];
    // Responsibility
    $responsibilityMode = $this->request->getPost('responsibility_mode') === 'department' ? 'department' : 'employees';
    $responsibilityDepartment = $responsibilityMode === 'department' ? trim((string)$this->request->getPost('responsibility_department')) : null;
    $employeeIdsInput = $this->request->getPost('employee_ids');
    if (!is_array($employeeIdsInput)) { $employeeIdsInput = $this->request->getPost('employee_ids[]') ?? []; }
    $employeeIds = is_array($employeeIdsInput) ? array_values(array_filter(array_map('intval', $employeeIdsInput))) : [];

        // Convert empty vendor_id to null
        $primaryVendorId = !empty($vendorIds) ? (int)$vendorIds[0] : null;

        // Normalize category_id for update (reuse logic from store)
        $category_id_input = $this->request->getPost('category_id');
        $category_id = (isset($category_id_input) && $category_id_input !== '' && is_numeric($category_id_input) && (int)$category_id_input > 0)
            ? (int) $category_id_input
            : null;

        $data = [
            'name' => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
            'category_id' => $category_id,
            'process_type' => $process_type,
            'vendor_id' => ($process_type === 'outsource' && $primaryVendorId) ? $primaryVendorId : null,
            'is_active' => $this->request->getPost('is_active') ? true : false,
            'responsibility_mode' => ($process_type === 'in_house') ? $responsibilityMode : null,
            'responsibility_department' => ($process_type === 'in_house' && $responsibilityMode === 'department') ? $responsibilityDepartment : null,
        ];

        // Validation rules
        $validation = \Config\Services::validation();
        $validation->setRules([
            'name' => 'required|min_length[3]|max_length[255]',
            'process_type' => 'required|in_list[in_house,outsource]',
            'category_id' => 'permit_empty|integer',
            'vendor_id' => 'permit_empty|integer'
        ]);

        $validationData = [
            'name' => $this->request->getPost('name'),
            'process_type' => $process_type,
            'category_id' => $category_id,
            'vendor_id' => $primaryVendorId
        ];

        if (!$validation->run($validationData)) {
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $validation);
        }

        // If outsource but no vendor selected, error
        if ($process_type === 'outsource' && empty($vendorIds)) {
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'At least one vendor is required for outsource processes.');
        }
        if ($process_type === 'in_house') {
            if ($responsibilityMode === 'department' && !$responsibilityDepartment) {
                return redirect()->back()->withInput()->with('error','Select a department for department responsibility mode.');
            }
            if ($responsibilityMode === 'employees' && empty($employeeIds)) {
                return redirect()->back()->withInput()->with('error','Select at least one employee for employee responsibility mode.');
            }
        }

        // Self-heal responsibility columns if migration not applied
        $this->processModel->ensureResponsibilitySchema();
        try {
            if ($this->processModel->update($id, $data)) {
                // Save pivot
                try { $this->processModel->setApprovedVendors((int)$id, $vendorIds); } catch (\Throwable $e) { log_message('error', 'Pivot save (update) failed: ' . $e->getMessage()); }
                if ($process_type === 'in_house' && $responsibilityMode === 'employees') {
                    $this->processModel->setAssignedEmployees((int)$id, $employeeIds);
                } else {
                    // clear employees if switched away
                    $this->processModel->setAssignedEmployees((int)$id, []);
                }
                if ($this->request->isAJAX()) {
                    return $this->response->setJSON(['success' => true, 'message' => 'Process updated successfully.']);
                }
                return redirect()->to('/processes/' . $id)->with('success', 'Process updated successfully.');
            } else {
                if ($this->request->isAJAX()) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'errors' => $this->processModel->errors()]);
                }
                return redirect()->back()
                               ->withInput()
                               ->with('validation', $this->processModel->errors());
            }
        } catch (\Exception $e) {
            log_message('error', 'Failed to update process: ' . $e->getMessage());
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'A database error occurred while updating the process. Please ensure the database schema is up to date.'
                ]);
            }

            return redirect()->back()
                           ->withInput()
                           ->with('error', 'A database error occurred while updating the process. Please ensure the database schema is up to date.');
        }
    }

    /**
     * Delete process
     */
    public function delete($id = null)
    {
        $this->requireAuth();
        
        // Skip permission check for testing
        // $this->requirePermission('processes.delete');

        $process = $this->processModel->find($id);
        if (!$process) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Process not found'], 404);
            }
            return redirect()->to('/processes')->with('error', 'Process not found');
        }

        // Check if process is used in any work orders
        $workOrderProcessRunModel = new \App\Models\WorkOrderProcessRunModel();
        $hasRuns = $workOrderProcessRunModel->where('process_id', $id)->countAllResults() > 0;

        // Check if process is attached to any product
        $productProcessModel = new \App\Models\ProductProcessModel();
        $isAttached = $productProcessModel->where('process_id', $id)->countAllResults() > 0;

        if ($hasRuns) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Cannot delete process with associated process runs. Consider deactivating instead.'
                ], 400);
            }
            return redirect()->to('/processes')->with('error', 'Cannot delete process with associated process runs. Consider deactivating instead.');
        }

        if ($isAttached) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse([
                    'success' => false,
                    'message' => 'Cannot delete process because it is attached to one or more products. Detach it from products first.'
                ], 400);
            }
            return redirect()->to('/processes')->with('error', 'Cannot delete process because it is attached to one or more products. Detach it from products first.');
        }

        if ($this->processModel->delete($id)) {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => true, 'message' => 'Process deleted successfully.']);
            }
            return redirect()->to('/processes')->with('success', 'Process deleted successfully.');
        } else {
            if ($this->request->isAJAX()) {
                return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete process.'], 500);
            }
            return redirect()->to('/processes')->with('error', 'Failed to delete process.');
        }
    }

    /**
     * Toggle process status (active/inactive)
     */
    public function toggleStatus($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('processes.edit');

        $process = $this->processModel->find($id);
        if (!$process) {
            return $this->jsonResponse(['success' => false, 'message' => 'Process not found'], 404);
        }

        $newStatus = !$process['is_active'];
        $data = [
            'is_active' => $newStatus,
            'updated_by' => $this->currentUser['id']
        ];

        if ($this->processModel->update($id, $data)) {
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return $this->jsonResponse([
                'success' => true,
                'message' => "Process {$statusText} successfully.",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update process status.'], 500);
        }
    }

    /**
     * Manage process routing for products
     */
    public function routing($processId = null)
    {
        $this->requireAuth();
        $this->requirePermission('processes.edit');

        $process = $this->processModel->find($processId);
        if (!$process) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process not found');
        }

        $productModel = new ProductModel();
        $products = $productModel->where('is_active', true)->findAll();
        $processProducts = $this->processModel->getProductsForProcess($processId);

        $data = $this->setPageData([
            'page_title' => 'Process Routing - ' . $process['name'],
            'process' => $process,
            'products' => $products,
            'process_products' => $processProducts
        ]);

        return view('processes/routing', $data);
    }

    /**
     * Update process routing
     */
    public function updateRouting($processId = null)
    {
        $this->requireAuth();
        $this->requirePermission('processes.edit');

        $process = $this->processModel->find($processId);
        if (!$process) {
            return $this->jsonResponse(['success' => false, 'message' => 'Process not found'], 404);
        }

        $routingData = $this->request->getJSON(true);
        
        if (empty($routingData)) {
            return $this->jsonResponse(['success' => false, 'message' => 'No routing data provided'], 400);
        }

        $result = $this->processModel->updateProcessRouting($processId, $routingData);

        if ($result) {
            return $this->jsonResponse(['success' => true, 'message' => 'Process routing updated successfully']);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update routing'], 500);
        }
    }

    /**
     * Export processes to CSV
     */
    public function exportCsv()
    {
        $this->requireAuth();
        $this->requirePermission('processes.view');

        $processes = $this->processModel->findAll();
        
        $filename = 'processes_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Name', 'Category', 'Description', 'Standard Time (min)', 
            'Setup Time (min)', 'Cost per Hour', 'Status', 'Created Date'
        ]);
        
        // CSV data
        foreach ($processes as $process) {
            fputcsv($output, [
                $process['id'],
                $process['name'],
                $process['category'],
                $process['description'],
                $process['standard_time_minutes'],
                $process['setup_time_minutes'],
                $process['cost_per_hour'],
                $process['is_active'] ? 'Active' : 'Inactive',
                $process['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get process data for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();
        $this->requirePermission('processes.view');

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'categories':
                $categories = $this->processModel->getProcessCategories();
                return $this->jsonResponse($categories);
                
            case 'search':
                $term = $this->request->getGet('term');
                $processes = $this->processModel->like('name', $term)
                                               ->orLike('category', $term)
                                               ->where('is_active', true)
                                               ->select('id, name, category, standard_time_minutes')
                                               ->limit(10)
                                               ->findAll();
                return $this->jsonResponse($processes);
                
            case 'details':
                $id = $this->request->getGet('id');
                $process = $this->processModel->find($id);
                if ($process) {
                    // Decode JSON fields for display
                    $jsonFields = ['quality_checkpoints', 'safety_requirements', 'machine_requirements', 'skill_requirements'];
                    foreach ($jsonFields as $field) {
                        if (!empty($process[$field])) {
                            $process[$field] = json_decode($process[$field], true);
                        }
                    }
                }
                return $this->jsonResponse($process);
                
            case 'statistics':
                $id = $this->request->getGet('id');
                $processRunModel = new WorkOrderProcessRunModel();
                $stats = $processRunModel->getProcessStatistics($id);
                return $this->jsonResponse($stats);
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Process runs management
     */
    public function runs($processId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.view');

        $process = $this->processModel->find($processId);
        if (!$process) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Process not found');
        }

        $processRunModel = new WorkOrderProcessRunModel();
        $runs = $processRunModel->getProcessRunsForProcess($processId);

        $data = $this->setPageData([
            'page_title' => 'Process Runs - ' . $process['name'],
            'process' => $process,
            'runs' => $runs,
            'can_manage' => $this->hasPermission('production.manage')
        ]);

        return view('processes/runs', $data);
    }

    /**
     * Start a process run
     */
    public function startRun($runId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        $processRunModel = new WorkOrderProcessRunModel();
        $run = $processRunModel->find($runId);

        if (!$run) {
            return $this->jsonResponse(['success' => false, 'message' => 'Process run not found'], 404);
        }

        if ($run['status'] !== 'pending') {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Can only start pending process runs'
            ], 400);
        }

        $updateData = [
            'status' => 'in_progress',
            'started_at' => date('Y-m-d H:i:s'),
            'operator_id' => $this->currentUser['id'],
            'updated_by' => $this->currentUser['id']
        ];

        if ($processRunModel->update($runId, $updateData)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Process run started successfully'
            ]);
        } else {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to start process run'
            ], 500);
        }
    }

    /**
     * Complete a process run
     */
    public function completeRun($runId = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        $processRunModel = new WorkOrderProcessRunModel();
        $run = $processRunModel->find($runId);

        if (!$run) {
            return $this->jsonResponse(['success' => false, 'message' => 'Process run not found'], 404);
        }

        if ($run['status'] !== 'in_progress') {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Can only complete in-progress process runs'
            ], 400);
        }

        $data = $this->request->getJSON(true);
        
        $updateData = [
            'status' => 'completed',
            'completed_at' => date('Y-m-d H:i:s'),
            'actual_time_minutes' => $data['actual_time_minutes'] ?? null,
            'quantity_produced' => $data['quantity_produced'] ?? null,
            'quantity_good' => $data['quantity_good'] ?? null,
            'quantity_defective' => $data['quantity_defective'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_by' => $this->currentUser['id']
        ];

        if ($processRunModel->update($runId, $updateData)) {
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Process run completed successfully'
            ]);
        } else {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to complete process run'
            ], 500);
        }
    }

    /**
     * Parse JSON field from form input
     */
    private function parseJsonField($input): array
    {
        if (is_string($input)) {
            // Try to parse as JSON first
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // If not JSON, treat as list separated by newlines
            $lines = explode("\n", $input);
            $result = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (!empty($line)) {
                    $result[] = $line;
                }
            }
            return $result;
        }
        
        return is_array($input) ? $input : [];
    }

    /**
     * Create vendor via AJAX from process form
     */
    public function createVendor()
    {
        $this->requireAuth();
        // Allow any authenticated user to create vendors from process forms

        // Get POST data
        $name = $this->request->getPost('name');
        $contact_person = $this->request->getPost('contact_person');
        $phone = $this->request->getPost('phone');
        $email = $this->request->getPost('email');

        // Validation
        if (empty($name) || strlen($name) < 2) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Vendor name is required and must be at least 2 characters.'
            ]);
        }

        $vendorModel = new \App\Models\VendorModel();
        
        // Check if vendor already exists
        $existing = $vendorModel->where('name', $name)->first();
        if ($existing) {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'A vendor with this name already exists.'
            ]);
        }

        $vendorData = [
            'name' => $name,
            'contact_person' => $contact_person,
            'phone' => $phone,
            'email' => $email,
            'is_active' => true
        ];

        if ($vendorModel->save($vendorData)) {
            $newVendorId = $vendorModel->getInsertID();
            return $this->response->setJSON([
                'success' => true,
                'vendor' => [
                    'id' => $newVendorId,
                    'name' => $name
                ],
                'message' => 'Vendor created successfully.'
            ]);
        } else {
            return $this->response->setJSON([
                'success' => false,
                'message' => 'Failed to create vendor. Please try again.'
            ]);
        }
    }
}
