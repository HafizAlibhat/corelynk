<?php

namespace App\Models;

use CodeIgniter\Model;

class ProcessModel extends Model
{
    protected $table            = 'processes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id', 'name', 'category_id', 'sequence_order', 'is_vendor_process', 'vendor_id',
        'description', 'standard_time_minutes', 'is_active',
        // Responsibility (in-house processes)
        'responsibility_mode', // 'department' | 'employees'
        'responsibility_department'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'             => 'required|min_length[2]|max_length[100]',
        'vendor_id'        => 'permit_empty|integer|is_not_unique[vendors.id]',
        'standard_time_minutes' => 'permit_empty|integer|greater_than_equal_to[0]'
    ];

    protected $validationMessages = [
        'name' => [
            'required'    => 'Process name is required',
            'min_length'  => 'Process name must be at least 2 characters'
        ],
        'vendor_id' => [
            'is_not_unique' => 'Selected vendor does not exist'
        ]
    ];

    /**
     * Ensure responsibility columns exist (self-healing for environments where migration not yet applied).
     */
    public function ensureResponsibilitySchema(): void
    {
        try {
            $db = \Config\Database::connect();
            $hasMode = $db->query("SHOW COLUMNS FROM processes LIKE 'responsibility_mode'")->getNumRows() > 0;
            $hasDept = $db->query("SHOW COLUMNS FROM processes LIKE 'responsibility_department'")->getNumRows() > 0;
            if (!$hasMode) {
                $db->query("ALTER TABLE processes ADD COLUMN responsibility_mode VARCHAR(20) NULL AFTER standard_time_minutes");
            }
            if (!$hasDept) {
                // place after responsibility_mode if that now exists
                $db->query("ALTER TABLE processes ADD COLUMN responsibility_department VARCHAR(100) NULL AFTER responsibility_mode");
            }
            // Ensure pivot table exists for employee assignments
            $hasPivot = $db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
            if (!$hasPivot) {
                $db->query("CREATE TABLE IF NOT EXISTS process_employee_assignments (\n                    id INT UNSIGNED NOT NULL AUTO_INCREMENT,\n                    process_id INT UNSIGNED NOT NULL,\n                    employee_id INT UNSIGNED NOT NULL,\n                    created_at DATETIME NULL,\n                    PRIMARY KEY(id),\n                    INDEX(process_id),\n                    INDEX(employee_id)\n                ) ENGINE=InnoDB");
            }
        } catch (\Throwable $e) {
            // Log only; do not throw (controller will still attempt save and may fail with friendly message)
            log_message('error', 'ensureResponsibilitySchema failed: ' . $e->getMessage());
        }
    }

    /**
     * Get assigned employee IDs for a process (from pivot table if exists)
     */
    public function getAssignedEmployeeIds(int $processId): array
    {
        try {
            $db = \Config\Database::connect();
            $hasTbl = $db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
            if (!$hasTbl) return [];
            $rows = $db->table('process_employee_assignments')->select('employee_id')->where('process_id', $processId)->get()->getResultArray();
            return array_values(array_unique(array_map(fn($r)=> (int)$r['employee_id'], $rows)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Replace assigned employees for a process (pivot table)
     */
    public function setAssignedEmployees(int $processId, array $employeeIds): bool
    {
        $db = \Config\Database::connect();
        $hasTbl = $db->query("SHOW TABLES LIKE 'process_employee_assignments'")->getNumRows() > 0;
        if (!$hasTbl) return true; // pivot not present yet
        $employeeIds = array_values(array_unique(array_filter(array_map('intval', $employeeIds), fn($v)=> $v > 0)));
        try {
            $db->transStart();
            $db->table('process_employee_assignments')->where('process_id', $processId)->delete();
            foreach ($employeeIds as $eid) {
                $db->table('process_employee_assignments')->insert([
                    'process_id' => $processId,
                    'employee_id' => $eid,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            $db->transComplete();
            return $db->transStatus();
        } catch (\Throwable $e) {
            log_message('error', 'setAssignedEmployees failed: '.$e->getMessage());
            if ($db->transStatus() === false) { $db->transRollback(); }
            return false;
        }
    }

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    /**
     * Get processes with filters and pagination
     */
    public function getProcessesWithFilters($searchTerm = null, $productFilter = null, $vendorFilter = null, $statusFilter = null, $perPage = 20, $categoryFilter = null): array
    {
        // We'll build queries using the Model instance so we can call Model::paginate()
        // If a join to process_categories fails (table missing), fall back to a simpler model instance.
        try {
            // Use $this (Model) to build the query so paginate() is available
            $this->select('processes.*, vendors.name as vendor_name, process_categories.name as category_name')
                 ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                 ->join('process_categories', 'process_categories.id = processes.category_id', 'left');

            if (!empty($searchTerm)) {
                $this->groupStart()
                     ->like('processes.name', $searchTerm)
                     ->orLike('processes.description', $searchTerm)
                     ->orLike('vendors.name', $searchTerm)
                     ->orLike('process_categories.name', $searchTerm)
                     ->groupEnd();
            }

            if (!empty($vendorFilter)) {
                $this->where('processes.vendor_id', $vendorFilter);
            }

            if (!empty($categoryFilter)) {
                $this->where('processes.category_id', $categoryFilter);
            }

            if ($statusFilter !== null && $statusFilter !== '') {
                $this->where('processes.is_active', (int) $statusFilter);
            }

            $this->orderBy('processes.name', 'ASC');

            return $this->paginate($perPage);
        } catch (\Throwable $e) {
            log_message('error', 'getProcessesWithFilters(): falling back due to DB error: ' . $e->getMessage());
            // Use a fresh model instance for the fallback to avoid state pollution
            try {
                $fallback = new self();
                $fallback->select('processes.*, vendors.name as vendor_name')
                         ->join('vendors', 'vendors.id = processes.vendor_id', 'left');

                if (!empty($searchTerm)) {
                    $fallback->groupStart()
                             ->like('processes.name', $searchTerm)
                             ->orLike('processes.description', $searchTerm)
                             ->orLike('vendors.name', $searchTerm)
                             ->groupEnd();
                }

                if (!empty($vendorFilter)) {
                    $fallback->where('processes.vendor_id', $vendorFilter);
                }

                if (!empty($categoryFilter)) {
                    $fallback->where('processes.category_id', $categoryFilter);
                }

                if ($statusFilter !== null && $statusFilter !== '') {
                    $fallback->where('processes.is_active', (int) $statusFilter);
                }

                $fallback->orderBy('processes.name', 'ASC');
                return $fallback->paginate($perPage);
            } catch (\Throwable $e2) {
                log_message('error', 'getProcessesWithFilters(): fallback query failed: ' . $e2->getMessage());
                return [];
            }
        }
    }

    /**
     * Get approved vendor IDs for a process from the pivot table, if present.
     */
    public function getApprovedVendorIds(int $processId): array
    {
        try {
            $db = \Config\Database::connect();
            $hasPV = $db->query("SHOW TABLES LIKE 'process_vendors'")->getNumRows() > 0;
            if (!$hasPV) return [];
            $rows = $db->table('process_vendors')->select('vendor_id')->where('process_id', $processId)->where('is_active', 1)->get()->getResultArray();
            return array_values(array_unique(array_map(fn($r)=> (int)$r['vendor_id'], $rows)));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Replace approved vendor set for a process in the pivot table.
     */
    public function setApprovedVendors(int $processId, array $vendorIds): bool
    {
        $db = \Config\Database::connect();
        $hasPV = $db->query("SHOW TABLES LIKE 'process_vendors'")->getNumRows() > 0;
        if (!$hasPV) return true; // nothing to do if pivot absent
        $vendorIds = array_values(array_unique(array_map('intval', $vendorIds)));
        try {
            $db->transStart();
            $db->table('process_vendors')->where('process_id', $processId)->delete();
            foreach ($vendorIds as $vid) {
                if ($vid > 0) {
                    $db->table('process_vendors')->insert([
                        'process_id' => $processId,
                        'vendor_id' => $vid,
                        'is_active' => 1,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
            $db->transComplete();
            return $db->transStatus();
        } catch (\Throwable $e) {
            log_message('error', 'setApprovedVendors failed: ' . $e->getMessage());
            if ($db->transStatus() === false) { $db->transRollback(); }
            return false;
        }
    }

    /**
     * Get processes by product ID
     */
    public function getProcessesByProduct(int $productId): array
    {
        return $this->select('processes.*, vendors.name as vendor_name')
                    ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                    ->where('processes.product_id', $productId)
                    ->where('processes.is_active', true)
                    ->orderBy('processes.sequence_order', 'ASC')
                    ->findAll();
    }

    /**
     * Get process with vendor information
     */
    public function getProcessWithVendor(int $processId): array|null
    {
        return $this->select('processes.*, vendors.name as vendor_name, vendors.contact_person, vendors.phone, vendors.email')
                    ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                    ->where('processes.id', $processId)
                    ->first();
    }

    /**
     * Get vendor processes
     */
    public function getVendorProcesses(int $vendorId): array
    {
        return $this->select('processes.*, products.name as product_name, products.code as product_code')
                    ->join('products', 'products.id = processes.product_id')
                    ->where('processes.vendor_id', $vendorId)
                    ->where('processes.is_vendor_process', true)
                    ->where('processes.is_active', true)
                    ->orderBy('products.name', 'ASC')
                    ->orderBy('processes.sequence_order', 'ASC')
                    ->findAll();
    }

    /**
     * Reorder process sequences
     */
    public function reorderProcesses(int $productId, array $processOrders): bool
    {
        $this->db->transStart();

        foreach ($processOrders as $processId => $order) {
            $this->update($processId, [
                'sequence_order' => $order,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        $this->db->transComplete();
        return $this->db->transStatus();
    }

    /**
     * Get next sequence order for product
     */
    public function getNextSequenceOrder(int $productId): int
    {
        $lastProcess = $this->where('product_id', $productId)
                           ->orderBy('sequence_order', 'DESC')
                           ->first();

        return $lastProcess ? $lastProcess['sequence_order'] + 1 : 1;
    }

    /**
     * Duplicate processes for new product
     */
    public function duplicateProcesses(int $sourceProductId, int $targetProductId): bool
    {
        $sourceProcesses = $this->where('product_id', $sourceProductId)
                               ->where('is_active', true)
                               ->orderBy('sequence_order', 'ASC')
                               ->findAll();

        if (empty($sourceProcesses)) {
            return true; // No processes to duplicate
        }

        $this->db->transStart();

        foreach ($sourceProcesses as $process) {
            unset($process['id'], $process['created_at'], $process['updated_at']);
            $process['product_id'] = $targetProductId;
            $this->insert($process);
        }

        $this->db->transComplete();
        return $this->db->transStatus();
    }

    /**
     * Get processes with pending work
     */
    public function getProcessesWithPendingWork(): array
    {
        return $this->select('processes.*, 
                             products.name as product_name, 
                             products.code as product_code,
                             vendors.name as vendor_name,
                             COUNT(work_order_process_runs.id) as pending_runs,
                             SUM(work_order_process_runs.quantity_pending) as total_pending_qty')
                    ->join('products', 'products.id = processes.product_id')
                    ->join('vendors', 'vendors.id = processes.vendor_id', 'left')
                    ->join('work_order_process_runs', 'work_order_process_runs.process_id = processes.id AND work_order_process_runs.status IN ("pending", "in_progress")', 'inner')
                    ->where('processes.is_active', true)
                    ->groupBy('processes.id')
                    ->having('pending_runs >', 0)
                    ->orderBy('total_pending_qty', 'DESC')
                    ->findAll();
    }

    /**
     * Validate QC checklist JSON
     */
    // QC checklist support removed per requirements
}
