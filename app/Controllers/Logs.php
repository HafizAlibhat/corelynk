<?php

namespace App\Controllers;

use App\Models\BatchModel;
use App\Models\BatchLogModel;

class Logs extends BaseController
{
    protected $db;

    protected $batchModel;
    protected $batchLogModel;

    public function __construct()
    {
        // Let BaseController initialize session and other services via initController
        $this->db = \Config\Database::connect();
        $this->batchModel = new BatchModel();
        $this->batchLogModel = new BatchLogModel();
    }

    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        // Load work orders for selection
        $workOrders = $this->db->table('work_orders')->select('id, wo_number, status')->orderBy('id', 'DESC')->get()->getResultArray();
        $data = $this->setPageData(['workOrders' => $workOrders, 'page_title' => 'Production Logs']);
        return view('logs/index', $data);
    }

    protected function isAjaxPost()
    {
        $methodOk = in_array(strtolower($this->request->getMethod()), ['post', 'put'], true);
        $isAjax = $this->request->isAJAX();
        $isJson = stripos($this->request->getHeaderLine('Content-Type'), 'application/json') !== false;
        // Accept standard AJAX posts and JSON fetch posts
        return $methodOk && ($isAjax || $isJson);
    }

    public function getProducts($workOrderId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX only']);
        }
        $woId = (int) ($workOrderId ?? $this->request->getGet('work_order_id'));
        if ($woId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid work order id']);
        }

        $rows = $this->db->table('work_order_items woi')
            ->select('woi.id as work_order_item_id, p.id as product_id, p.name as product_name, woi.quantity_ordered')
            ->join('products p', 'p.id = woi.product_id')
            ->where('woi.work_order_id', $woId)
            ->get()
            ->getResultArray();

        return $this->response->setJSON(['success' => true, 'products' => $rows]);
    }

    public function getProcesses($productId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX only']);
        }
        $pId = (int) ($productId ?? $this->request->getGet('product_id'));
        if ($pId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid product id']);
        }

        // Prefer product_processes if available, otherwise return all processes
        $hasTable = $this->db->query("SHOW TABLES LIKE 'product_processes'")->getNumRows() > 0;
        if ($hasTable) {
            try {
                $colsRes = $this->db->query("SHOW COLUMNS FROM product_processes")->getResultArray();
                $ppCols = array_map(fn($r) => $r['Field'], $colsRes);
                $orderCandidates = ['sequence','sort_order','step_order','order_index','order_no','position','display_order','priority'];
                $ordCol = null;
                foreach ($orderCandidates as $c) { if (in_array($c, $ppCols, true)) { $ordCol = $c; break; } }

                $select = 'pr.id as process_id, pr.name as process_name';
                if ($ordCol) { $select .= ", pp.{$ordCol} as seq"; }

                $builder = $this->db->table('product_processes pp')
                    ->select($select)
                    ->join('processes pr', 'pr.id = pp.process_id')
                    ->where('pp.product_id', $pId);
                if ($ordCol) { $builder->orderBy("pp.$ordCol", 'ASC'); } else { $builder->orderBy('pr.id', 'ASC'); }

                $rows = $builder->get()->getResultArray();
            } catch (\Throwable $e) {
                log_message('error', 'getProcesses failed on product_processes: ' . $e->getMessage());
                $rows = $this->db->table('processes')->select('id as process_id, name as process_name')->orderBy('id')->get()->getResultArray();
            }
        } else {
            $rows = $this->db->table('processes')->select('id as process_id, name as process_name')->orderBy('id')->get()->getResultArray();
        }

        return $this->response->setJSON(['success' => true, 'processes' => $rows]);
    }

    /**
     * Return vendor choices for a process.
     * Priority: process_vendors (approved list) -> processes.vendor_id -> all active vendors
     */
    public function getVendorsForProcess($processId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX only']);
        }
        $pid = (int) ($processId ?? $this->request->getGet('process_id'));
        if ($pid <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid process id']);
        }

        try {
            $proc = $this->db->table('processes')->select('id, is_vendor_process, vendor_id')->where('id', $pid)->get()->getRowArray();
            if (!$proc) return $this->response->setJSON(['success' => false, 'message' => 'Process not found']);

            $vendors = [];
            // If pivot exists, use it
            $hasPV = $this->db->query("SHOW TABLES LIKE 'process_vendors'")->getNumRows() > 0;
            if ($hasPV) {
                // Try active vendors first
                $vendors = $this->db->table('process_vendors pv')
                    ->select('v.id, v.name')
                    ->join('vendors v', 'v.id = pv.vendor_id')
                    ->where('pv.process_id', $pid)
                    ->where('pv.is_active', 1)
                    ->where('v.is_active', 1)
                    ->orderBy('v.name', 'ASC')
                    ->get()->getResultArray();
                // If none, fall back to any vendors on pivot (even inactive) to avoid empty lists
                if (empty($vendors)) {
                    $vendors = $this->db->table('process_vendors pv')
                        ->select('v.id, v.name')
                        ->join('vendors v', 'v.id = pv.vendor_id')
                        ->where('pv.process_id', $pid)
                        ->orderBy('v.name', 'ASC')
                        ->get()->getResultArray();
                }
            }
            // Fallback to single vendor on process
            if (empty($vendors) && !empty($proc['vendor_id'])) {
                // Try active; then include regardless of active
                $v = $this->db->table('vendors')->select('id, name')->where('id', (int)$proc['vendor_id'])->where('is_active', 1)->get()->getRowArray();
                if (!$v) {
                    $v = $this->db->table('vendors')->select('id, name')->where('id', (int)$proc['vendor_id'])->get()->getRowArray();
                }
                if ($v) $vendors = [$v];
            }
            // Last fallback: return all active vendors (only if process is marked vendor process)
            if (empty($vendors) && !empty($proc['is_vendor_process'])) {
                $vendors = $this->db->table('vendors')->select('id, name')->where('is_active', 1)->orderBy('name', 'ASC')->get()->getResultArray();
                if (empty($vendors)) {
                    // As a last resort, show all vendors
                    $vendors = $this->db->table('vendors')->select('id, name')->orderBy('name', 'ASC')->get()->getResultArray();
                }
            }

            return $this->response->setJSON([
                'success' => true,
                'vendors' => $vendors,
                'is_vendor_process' => !empty($proc['is_vendor_process']) ? 1 : 0,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getVendorsForProcess failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to load vendors']);
        }
    }

    /**
     * Return employee choices for an in‑house process.
     * Logic hierarchy:
     * 1. If process is vendor process => return empty (frontend hides employee select)
     * 2. If responsibility_mode = 'employees' and pivot assignments exist -> return those active employees (fallback to any on pivot even if inactive if empty)
     * 3. If responsibility_mode = 'department' and department value set -> return active employees in that department (fallback to all in department)
     * 4. Fallback -> all active employees (then all employees) to avoid empty selector
     */
    public function getEmployeesForProcess($processId = null)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX only']);
        }
        $pid = (int) ($processId ?? $this->request->getGet('process_id'));
        if ($pid <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid process id']);
        }
        try {
            // Load process with responsibility columns if they exist
            $procCols = [];
            try {
                $cRes = $this->db->query("SHOW COLUMNS FROM processes")->getResultArray();
                $procCols = array_map(fn($r) => $r['Field'], $cRes);
            } catch (\Throwable $e) { /* ignore */ }
            $select = 'id, is_vendor_process, vendor_id';
            foreach (['responsibility_mode','responsibility_department'] as $col) {
                if (in_array($col, $procCols, true)) { $select .= ", $col"; }
            }
            $proc = $this->db->table('processes')->select($select)->where('id', $pid)->get()->getRowArray();
            if (!$proc) {
                return $this->response->setJSON(['success' => false, 'message' => 'Process not found']);
            }
            if (!empty($proc['is_vendor_process'])) {
                return $this->response->setJSON(['success' => true, 'employees' => [], 'is_vendor_process' => 1]);
            }

            $mode = $proc['responsibility_mode'] ?? null;
            $dept = $proc['responsibility_department'] ?? null;
            $employees = [];
            $departments = [];

            $hasPEA = $this->db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
            if ($mode === 'employees' && $hasPEA) {
                // Active first
                $employees = $this->db->table('process_employee_assignments pea')
                    ->select('e.id, CONCAT(e.first_name, " ", e.last_name) as name')
                    ->join('employees e', 'e.id = pea.employee_id')
                    ->where('pea.process_id', $pid)
                    ->where('e.is_active', 1)
                    ->orderBy('e.first_name', 'ASC')->orderBy('e.last_name','ASC')
                    ->get()->getResultArray();
                if (empty($employees)) {
                    // fallback any employees on pivot even inactive
                    $employees = $this->db->table('process_employee_assignments pea')
                        ->select('e.id, CONCAT(e.first_name, " ", e.last_name) as name')
                        ->join('employees e', 'e.id = pea.employee_id')
                        ->where('pea.process_id', $pid)
                        ->orderBy('e.first_name', 'ASC')->orderBy('e.last_name','ASC')
                        ->get()->getResultArray();
                }
                // Departments derived from assigned employees
                try {
                    $empIds = array_map(fn($r)=> (int)$r['id'], $employees);
                    if (!empty($empIds)) {
                        $empList = implode(',', array_map('intval', $empIds));
                        $deptRows = $this->db->query("SELECT DISTINCT department FROM employees WHERE id IN ($empList) AND department IS NOT NULL AND department <> '' ORDER BY department ASC")->getResultArray();
                        $departments = array_values(array_map(fn($r)=> $r['department'], $deptRows));
                    }
                } catch (\Throwable $e) { /* ignore */ }
            } elseif ($mode === 'department' && !empty($dept)) {
                // Department mode
                $employees = $this->db->table('employees')
                    ->select('id, CONCAT(first_name, " ", last_name) as name')
                    ->where('department', $dept)
                    ->where('is_active', 1)
                    ->orderBy('first_name','ASC')->orderBy('last_name','ASC')
                    ->get()->getResultArray();
                if (empty($employees)) {
                    $employees = $this->db->table('employees')
                        ->select('id, CONCAT(first_name, " ", last_name) as name')
                        ->where('department', $dept)
                        ->orderBy('first_name','ASC')->orderBy('last_name','ASC')
                        ->get()->getResultArray();
                }
                $departments = [$dept];
            }

            if (empty($employees)) {
                // Fallback active employees
                $employees = $this->db->table('employees')
                    ->select('id, CONCAT(first_name, " ", last_name) as name')
                    ->where('is_active', 1)
                    ->orderBy('first_name','ASC')->orderBy('last_name','ASC')
                    ->get()->getResultArray();
                if (empty($employees)) {
                    $employees = $this->db->table('employees')
                        ->select('id, CONCAT(first_name, " ", last_name) as name')
                        ->orderBy('first_name','ASC')->orderBy('last_name','ASC')
                        ->get()->getResultArray();
                }
            }

            return $this->response->setJSON([
                'success' => true,
                'employees' => $employees,
                'departments' => $departments,
                'responsibility_mode' => $mode,
                'responsibility_department' => $dept,
                'is_vendor_process' => 0,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getEmployeesForProcess failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to load employees']);
        }
    }

    public function createBatch()
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        if (!$this->isAjaxPost()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX POST required']);
        }
        $data = json_decode($this->request->getBody() ?: '{}', true);
        $woItemId = (int) ($data['work_order_item_id'] ?? 0);
        $processId = (int) ($data['process_id'] ?? 0);
    $qty = (float) ($data['planned_qty'] ?? 0);
    $startDate = trim((string)($data['start_date'] ?? ''));
    $vendorId = (int) ($data['vendor_id'] ?? 0);
        $startTime = trim((string)($data['start_time'] ?? ''));
        $employeeIds = $data['employee_ids'] ?? [];
        // New unified assignee support (employee or department)
        $assignee = $data['assignee'] ?? null; // e.g., "emp:3" or "dept:Finishing"
        $assigneeType = $data['assignee_type'] ?? null;
        $assigneeValue = $data['assignee_value'] ?? null;
        $selectedEmployeeId = null; $selectedDepartment = null;
        if (is_string($assignee) && strpos($assignee, ':') !== false) {
            [$t,$v] = explode(':', $assignee, 2);
            if ($t === 'emp') { $selectedEmployeeId = (int)$v; }
            if ($t === 'dept') { $selectedDepartment = trim($v); }
        }
        if (!$selectedEmployeeId && $assigneeType === 'employee') { $selectedEmployeeId = (int)$assigneeValue; }
        if (!$selectedDepartment && $assigneeType === 'department') { $selectedDepartment = is_string($assigneeValue)?trim($assigneeValue):null; }
        if ($selectedEmployeeId && empty($employeeIds)) { $employeeIds = [$selectedEmployeeId]; }
        if (!is_array($employeeIds)) { $employeeIds = []; }
        $employeeIds = array_values(array_filter(array_map('intval', $employeeIds), fn($v)=>$v>0));

        if ($woItemId <= 0 || $processId <= 0 || $qty <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Missing or invalid fields', 'errors' => ['work_order_item_id','process_id','planned_qty']]);
        }

        // Validate existence of WO item and process
        try {
            $woi = $this->db->table('work_order_items')->where('id', $woItemId)->get()->getRowArray();
            if (!$woi) {
                return $this->response->setJSON(['success' => false, 'message' => 'Work order item not found']);
            }
            // Load process record and, if available, include short-code/name fields for batch-code generation
            // Include responsibility columns if present
            $procCols = [];
            try { $cRes = $this->db->query("SHOW COLUMNS FROM processes")->getResultArray(); $procCols = array_map(fn($r)=>$r['Field'],$cRes); } catch (\Throwable $e) { /* ignore */ }
            $select = 'id, is_vendor_process, vendor_id';
            foreach (['responsibility_mode','responsibility_department'] as $rc) { if (in_array($rc, $procCols, true)) { $select .= ", $rc"; } }
            $proc = $this->db->table('processes')->select($select)->where('id', $processId)->get()->getRowArray();
            try {
                $cols = $this->db->query("SHOW COLUMNS FROM processes")->getResultArray();
                $fields = array_map(fn($r) => $r['Field'], $cols);
                $extra = [];
                $candidates = ['name','process_name','short_code','code','abbr','shortname'];
                $sel = [];
                foreach ($candidates as $c) { if (in_array($c, $fields, true)) { $sel[] = $c; } }
                if (!empty($sel)) {
                    $extra = $this->db->table('processes')->select(implode(', ', $sel))->where('id', $processId)->get()->getRowArray() ?: [];
                }
                if (!empty($extra)) { $proc = array_merge($proc ?? [], $extra); }
            } catch (\Throwable $e) { /* ignore; fall back to defaults */ }
            if (!$proc) {
                return $this->response->setJSON(['success' => false, 'message' => 'Process not found']);
            }
        } catch (\Throwable $e) {
            log_message('error', 'createBatch validation failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Validation error']);
        }

        // Validate vendor selection for vendor processes
        try {
            $isVendorProc = !empty($proc['is_vendor_process']);
            $hasPV = $this->db->query("SHOW TABLES LIKE 'process_vendors'")->getNumRows() > 0;
            $pvCount = 0;
            if ($hasPV) {
                $pvCount = (int) $this->db->table('process_vendors')->where('process_id', $processId)->where('is_active', 1)->countAllResults();
            }
            $requiresVendor = $isVendorProc && ($pvCount > 0 || !empty($proc['vendor_id']));

            if ($requiresVendor && $vendorId <= 0) {
                return $this->response->setJSON(['success' => false, 'message' => 'Vendor is required for this process']);
            }

            if ($vendorId > 0) {
                if ($hasPV) {
                    $cnt = $this->db->table('process_vendors')->where('process_id', $processId)->where('vendor_id', $vendorId)->where('is_active', 1)->countAllResults();
                    if ($cnt <= 0) {
                        return $this->response->setJSON(['success' => false, 'message' => 'Selected vendor is not approved for this process']);
                    }
                } else if (!empty($proc['vendor_id'])) {
                    if ((int)$proc['vendor_id'] !== $vendorId) {
                        return $this->response->setJSON(['success' => false, 'message' => 'Selected vendor does not match configured vendor for this process']);
                    }
                } else {
                    // At minimum, ensure vendor exists and active
                    $vExists = $this->db->table('vendors')->where('id', $vendorId)->where('is_active', 1)->countAllResults();
                    if ($vExists <= 0) {
                        return $this->response->setJSON(['success' => false, 'message' => 'Vendor not found or inactive']);
                    }
                }
            } else if (!$isVendorProc) {
                // Not a vendor process: ignore provided vendor_id
                $vendorId = 0;
            }
        } catch (\Throwable $e) {
            log_message('error', 'createBatch vendor validation failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Vendor validation error']);
        }

        // Validate employee selection for in-house processes
        try {
            if (empty($proc['is_vendor_process'])) {
                $mode = $proc['responsibility_mode'] ?? null;
                $dept = $proc['responsibility_department'] ?? null;
                $hasPEA = $this->db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
                if ($mode === 'employees' && $hasPEA) {
                    // Allowed set
                    $allowed = $this->db->table('process_employee_assignments')
                        ->select('employee_id')
                        ->where('process_id', $processId)
                        ->get()->getResultArray();
                    $allowedIds = array_map(fn($r)=>(int)$r['employee_id'],$allowed);
                    if (!empty($allowedIds)) {
                        // Require exactly one assignee (employee) for a batch
                        if (empty($employeeIds) && !$selectedEmployeeId) {
                            return $this->response->setJSON(['success'=>false,'message'=>'Select an employee for this batch']);
                        }
                        // Validate subset
                        foreach ($employeeIds as $eid) {
                            if (!in_array($eid, $allowedIds, true)) {
                                return $this->response->setJSON(['success'=>false,'message'=>'Invalid employee selected for this process']);
                            }
                        }
                        if ($selectedEmployeeId && !in_array($selectedEmployeeId, $allowedIds, true)) {
                            return $this->response->setJSON(['success'=>false,'message'=>'Invalid employee selected for this process']);
                        }
                    }
                } elseif ($mode === 'department' && !empty($dept)) {
                    // Optional: ensure selected employees (if any) are in department
                    if (!empty($employeeIds)) {
                        $cnt = $this->db->table('employees')
                            ->whereIn('id', $employeeIds)
                            ->where('department', $dept)
                            ->countAllResults();
                        if ($cnt !== count($employeeIds)) {
                            return $this->response->setJSON(['success'=>false,'message'=>'Selected employees don\'t match department']);
                        }
                    }
                    // Require department selection when mode=department
                    if (empty($selectedDepartment)) {
                        $selectedDepartment = $dept; // default to configured department if present
                    }
                } else {
                    // No specific responsibility; allow empty or any active employees
                }
            } else {
                // Vendor process: ignore any employee ids
                $employeeIds = [];
            }
        } catch (\Throwable $e) {
            log_message('error','createBatch employee validation failed: '.$e->getMessage());
            return $this->response->setJSON(['success'=>false,'message'=>'Employee validation error']);
        }

        // Generate short, human-friendly batch code: <PROC_SHORT>-<series>
        // - PROC_SHORT: try processes.short_code/code/abbr if present; else initials of name (max 3 chars)
        // - series: process_id * 100 + running index for this process (1..99)
        $procShort = null;
        try {
            $procCols = $this->db->query("SHOW COLUMNS FROM processes")->getResultArray();
            $pFields = array_map(fn($r) => $r['Field'], $procCols);
            foreach (['short_code','code','abbr','shortname'] as $cand) {
                if (in_array($cand, $pFields, true) && !empty($proc[$cand])) { $procShort = strtoupper(preg_replace('/[^A-Za-z0-9]/','', (string)$proc[$cand])); break; }
            }
        } catch (\Throwable $e) { /* ignore, derive from name below */ }
        if (!$procShort) {
            $name = (string)($proc['name'] ?? $proc['process_name'] ?? 'PRC');
            // Prefer acronym inside parentheses, e.g., "Temper (HRC)" => HRC
            if (preg_match('/\(([A-Za-z0-9]{2,})\)/', $name, $m)) {
                $procShort = strtoupper(substr($m[1], 0, 4));
            } else {
                $parts = preg_split('/\s+|[-_]/', trim($name)) ?: [];
                $initials = '';
                foreach ($parts as $w) { if ($w !== '') { $initials .= strtoupper($w[0]); } if (strlen($initials) >= 3) break; }
                if ($initials === '') { $initials = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','', $name), 0, 3) ?: 'PRC'); }
                $procShort = $initials;
            }
        }

        // Compute series number within a 100-block for the process
        $existingCount = 0;
        try {
            $existingCount = (int)$this->db->table('process_batches')->where('process_id', $processId)->countAllResults();
        } catch (\Throwable $e) { $existingCount = 0; }
        $nextIndex = $existingCount + 1; // 1-based index per process
        $seriesNumber = ($processId * 100) + $nextIndex; // e.g., pid=2 -> 201 for first
        $batchCode = $procShort . '-' . $seriesNumber;
        // Ensure uniqueness if older data causes a collision
        try {
            while (true) {
                $exists = (int)$this->db->table('process_batches')->where('batch_code', $batchCode)->countAllResults();
                if ($exists <= 0) break;
                $nextIndex++; $seriesNumber = ($processId * 100) + $nextIndex; $batchCode = $procShort . '-' . $seriesNumber;
            }
        } catch (\Throwable $e) { /* if check fails, keep current $batchCode */ }
        // Resolve created_by safely against users FK
        $createdBy = session()->get('user_id') ?? session()->get('id');
        if (!empty($createdBy)) {
            try {
                $exists = $this->db->table('users')->where('id', (int)$createdBy)->countAllResults();
                if ($exists <= 0) { $createdBy = null; }
            } catch (\Throwable $e) {
                $createdBy = null; // if users table not available, avoid FK
            }
        }

        // compute created_at based on provided start_date (date only)
        $createdAt = date('Y-m-d H:i:s');
        if (!empty($startDate)) {
            // Accept YYYY-MM-DD plus optional HH:MM
            $dateTs = strtotime($startDate);
            $timeTs = false;
            if (!empty($startTime)) {
                // Combine into one string to parse robustly
                $timeTs = strtotime($startDate . ' ' . $startTime . ':00');
            }
            $ts = $timeTs !== false && $timeTs !== null ? $timeTs : $dateTs;
            if ($ts !== false) { $createdAt = date('Y-m-d H:i:s', $ts); }
        }

        $ins = [
            'work_order_item_id' => $woItemId,
            'process_id' => $processId,
            ...(($vendorId > 0) ? ['vendor_id' => $vendorId] : []),
            'batch_code' => $batchCode,
            'planned_qty' => $qty,
            'status' => 'planned',
            // Only include created_by if we have a valid user id to satisfy FK
            // Some schemas may have NOT NULL; if so, consider setting a valid default user in DB
            ...(is_null($createdBy) ? [] : ['created_by' => (int)$createdBy]),
            'created_at' => $createdAt,
        ];
        // Optionally accept first log inline
        $inlineLog = [
            'enabled' => !empty($data['add_log_now']),
            'qty_completed' => $data['qty_completed'] ?? null,
            'qty_rejected' => $data['qty_rejected'] ?? null,
            'rework_qty' => $data['rework_qty'] ?? null,
            'qty_received' => $data['qty_received'] ?? null,
            'log_date' => $data['log_date'] ?? null,
            'log_time' => $data['log_time'] ?? null,
            'created_by' => $createdBy,
        ];

        $this->db->transStart();
        try {
            log_message('debug', 'createBatch PRE: ' . json_encode(['wo_item_id' => $woItemId, 'process_id' => $processId, 'qty' => $qty, 'created_by' => $createdBy]));
            $newId = $this->batchModel->createBatch($ins);
            log_message('debug', 'createBatch OK id=' . $newId);

            // Insert batch assignee: prefer single employee or department
            if ($newId) {
                try {
                    $hasBE = $this->db->query("SHOW TABLES LIKE 'process_batch_employees'")->getNumRows() > 0;
                    if ($hasBE) {
                        $cols = $this->db->query("SHOW COLUMNS FROM process_batch_employees")->getResultArray();
                        $fields = array_map(fn($r)=> $r['Field'], $cols);
                        if ($selectedDepartment && in_array('department',$fields,true)) {
                            $this->db->table('process_batch_employees')->insert([
                                'batch_id' => (int)$newId,
                                'employee_id' => null,
                                'department' => $selectedDepartment,
                                'created_at' => date('Y-m-d H:i:s'),
                            ]);
                        } elseif (!empty($employeeIds)) {
                            // Use the first employee if multiple were sent
                            $eid = (int)($selectedEmployeeId ?: $employeeIds[0]);
                            if ($eid > 0) {
                                $this->db->table('process_batch_employees')->insert([
                                    'batch_id' => (int)$newId,
                                    'employee_id' => $eid,
                                    'created_at' => date('Y-m-d H:i:s'),
                                ]);
                            }
                        }
                    }
                } catch (\Throwable $e) {
                    log_message('error','createBatch: failed inserting batch employees pivot: '.$e->getMessage());
                }
            }

            $logAdded = false;
            if ($inlineLog['enabled'] && $newId) {
                // Prepare created_at from log_date + log_time if available
                $logCreatedAt = null;
                if (!empty($inlineLog['log_date'])) {
                    $dtStr = $inlineLog['log_date'] . (!empty($inlineLog['log_time']) ? (' ' . $inlineLog['log_time'] . ':00') : ' 00:00:00');
                    $ts = strtotime($dtStr); if ($ts !== false) $logCreatedAt = date('Y-m-d H:i:s', $ts);
                }
                $payload = [
                    'batch_id' => (int)$newId,
                    'qty_completed' => $inlineLog['qty_completed'],
                    'qty_rejected' => $inlineLog['qty_rejected'],
                    'rework_qty' => $inlineLog['rework_qty'],
                    'qty_received' => $inlineLog['qty_received'],
                    'log_date' => $inlineLog['log_date'] ?? date('Y-m-d'),
                    'created_by' => $inlineLog['created_by'],
                ];
                if (!empty($logCreatedAt)) { $payload['created_at'] = $logCreatedAt; }
                // Insert only if any quantity is provided (avoid empty logs)
                $qtySum = (float)($payload['qty_completed'] ?? 0) + (float)($payload['qty_rejected'] ?? 0) + (float)($payload['rework_qty'] ?? 0) + (float)($payload['qty_received'] ?? 0);
                if ($qtySum > 0) {
                    $this->batchLogModel->insertLog($payload);
                    $logAdded = true;
                }
            }

            $this->db->transComplete();
            if ($this->db->transStatus() === false) {
                log_message('error', 'createBatch transaction failed');
                return $this->response->setJSON(['success' => false, 'message' => 'Transaction failed']);
            }
            return $this->response->setJSON(['success' => true, 'message' => $inlineLog['enabled'] ? ($logAdded ? 'Batch started and first log added' : 'Batch started (no log values provided)') : 'Batch started', 'batch_id' => (int)$newId, 'log_added' => $logAdded]);
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'createBatch failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'DB insert failed']);
        }
    }

    public function addLog()
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');

        if (!$this->isAjaxPost()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX POST required']);
        }
    $data = json_decode($this->request->getBody() ?: '{}', true);
        $batchId = (int) ($data['batch_id'] ?? 0);
        if ($batchId <= 0) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid batch id']);
        }

        // Ensure batch exists
        try {
            $batch = $this->db->table('process_batches')->where('id', $batchId)->get()->getRowArray();
            if (!$batch) {
                return $this->response->setJSON(['success' => false, 'message' => 'Batch not found']);
            }
        } catch (\Throwable $e) {
            log_message('error', 'addLog validation failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Validation error']);
        }

        try {
            $payload = [
                'batch_id' => $batchId,
                // accept either qty_completed or accepted_qty from client
                'qty_completed' => $data['qty_completed'] ?? null,
                'accepted_qty' => $data['accepted_qty'] ?? null,
                // accept either qty_rejected or rejected_qty
                'qty_rejected' => $data['qty_rejected'] ?? null,
                'rejected_qty' => $data['rejected_qty'] ?? null,
                // received quantity (support both keys)
                'qty_received' => $data['qty_received'] ?? ($data['received_qty'] ?? null),
                // rework variants
                'rework_qty' => $data['rework_qty'] ?? null,
                'qty_rework' => $data['qty_rework'] ?? null,
                'log_date' => $data['log_date'] ?? date('Y-m-d'),
                'notes' => $data['notes'] ?? '',
                // try to capture who created the log
                'created_by' => session()->get('user_id') ?? session()->get('id') ?? null,
            ];
            // If a log_time is provided, override created_at to reflect the actual production time
            $logTime = trim((string)($data['log_time'] ?? ''));
            if (!empty($logTime)) {
                $dtStr = ($payload['log_date'] ?? date('Y-m-d')) . ' ' . $logTime . ':00';
                $ts = strtotime($dtStr);
                if ($ts !== false) {
                    $payload['created_at'] = date('Y-m-d H:i:s', $ts);
                }
            }
            log_message('debug', 'addLog PRE: ' . json_encode(['batch_id' => $batchId, 'payload' => $payload]));
            $this->batchLogModel->insertLog($payload);
            log_message('debug', 'addLog OK for batch_id=' . $batchId);
            return $this->response->setJSON(['success' => true, 'message' => 'Log added']);
        } catch (\Throwable $e) {
            log_message('error', 'addLog failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'DB insert failed']);
        }
    }

    public function updateLog($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');
        if (!$this->isAjaxPost()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX POST required']);
        }
        // Accept id from route segment, query string, or JSON body
        $rawBody = $this->request->getBody() ?: '{}';
        $json = json_decode($rawBody, true) ?: [];
        $logId = (int)($id ?? ($this->request->getGet('id') ?? ($json['id'] ?? 0)));
        if ($logId <= 0) return $this->response->setJSON(['success' => false, 'message' => 'Invalid id']);
        $data = $json;
        try {
            $ok = $this->batchLogModel->updateLogById($logId, $data);
            return $this->response->setJSON(['success' => (bool)$ok, 'message' => $ok ? 'Updated' : 'No changes']);
        } catch (\Throwable $e) {
            log_message('error', 'updateLog failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'DB update failed']);
        }
    }

    public function deleteLog($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('production.manage');
        if (!$this->isAjaxPost()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX POST required']);
        }
        // Accept id from route segment, query string, or JSON body
        $rawBody = $this->request->getBody() ?: '{}';
        $json = json_decode($rawBody, true) ?: [];
        $logId = (int)($id ?? ($this->request->getGet('id') ?? ($json['id'] ?? 0)));
        if ($logId <= 0) return $this->response->setJSON(['success' => false, 'message' => 'Invalid id']);
        try {
            $ok = $this->batchLogModel->deleteLogById($logId);
            return $this->response->setJSON(['success' => (bool)$ok, 'message' => $ok ? 'Deleted' : 'Not found']);
        } catch (\Throwable $e) {
            log_message('error', 'deleteLog failed: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'DB delete failed']);
        }
    }

    public function hierarchy()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setJSON(['success' => false, 'message' => 'AJAX only']);
        }

        // Produce a lightweight tree: work_orders -> products -> processes -> batches
        $this->requireAuth();
        $this->requirePermission('production.view');

        $batches = $this->batchModel->getHierarchy();

        $tree = [];
        foreach ($batches as $b) {
            $woId = $b['work_order_id'];
            $prodId = $b['product_id'];
            $procId = $b['process_id'];
            if (!isset($tree[$woId])) {
                $tree[$woId] = ['work_order_id' => $woId, 'wo_number' => $b['wo_number'], 'products' => []];
            }
            if (!isset($tree[$woId]['products'][$prodId])) {
                $tree[$woId]['products'][$prodId] = ['product_id' => $prodId, 'product_name' => $b['product_name'], 'ordered_qty' => ($b['ordered_qty'] ?? null), 'processes' => []];
            } else if (empty($tree[$woId]['products'][$prodId]['ordered_qty']) && isset($b['ordered_qty'])) {
                $tree[$woId]['products'][$prodId]['ordered_qty'] = $b['ordered_qty'];
            }
            if (!isset($tree[$woId]['products'][$prodId]['processes'][$procId])) {
                $tree[$woId]['products'][$prodId]['processes'][$procId] = ['process_id' => $procId, 'process_name' => $b['process_name'], 'ordered_qty' => ($b['ordered_qty'] ?? null), 'batches' => []];
            }
            $batchNode = [
                'batch_id' => $b['batch_id'],
                'batch_code' => $b['batch_code'],
                'planned_qty' => $b['planned_qty'],
                'status' => $b['status'],
                'ordered_qty' => $b['ordered_qty'] ?? null,
                // surface metadata for UI badges
                'created_at' => $b['created_at'] ?? null,
            ];
            // pass-through vendor info when present
            if (array_key_exists('vendor_id', $b)) { $batchNode['vendor_id'] = $b['vendor_id']; }
            if (array_key_exists('vendor_name', $b)) { $batchNode['vendor_name'] = $b['vendor_name']; }
            // Pass through totals and logs if provided by model
            if (isset($b['totals']) && is_array($b['totals'])) {
                $batchNode['totals'] = $b['totals'];
            }
            if (isset($b['logs']) && is_array($b['logs'])) {
                $batchNode['logs'] = array_values($b['logs']);
            }
            // pass-through employees and department if present
            if (isset($b['employees'])) { $batchNode['employees'] = $b['employees']; }
            if (isset($b['department'])) { $batchNode['department'] = $b['department']; }
            $tree[$woId]['products'][$prodId]['processes'][$procId]['batches'][] = $batchNode;
        }

        // Re-index arrays for JSON friendliness
        $out = [];
        foreach ($tree as $wo) {
            $products = [];
            foreach ($wo['products'] as $p) {
                $processes = [];
                foreach ($p['processes'] as $pr) {
                    $processes[] = $pr;
                }
                $p['processes'] = $processes;
                $products[] = $p;
            }
            $wo['products'] = $products;
            $out[] = $wo;
        }

        return $this->response->setJSON(['success' => true, 'hierarchy' => $out]);
    }
}
