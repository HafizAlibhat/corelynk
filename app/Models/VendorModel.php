<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class VendorModel extends Model
{
    use PublicIdTrait;

    protected static bool $schemaEnsured = false;

    protected $table            = 'vendors';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'public_id', 'vendor_code', 'name', 'contact_person', 'phone', 'email', 'address', 'is_active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'           => 'required|min_length[2]|max_length[100]',
        'contact_person' => 'permit_empty|max_length[100]',
        'phone'          => 'permit_empty|max_length[20]',
        'email'          => 'permit_empty|valid_email|max_length[100]',
        'address'        => 'permit_empty'
    ];

    protected $validationMessages = [
        'name' => [
            'required'    => 'Vendor name is required',
            'min_length'  => 'Vendor name must be at least 2 characters'
        ],
        'email' => [
            'valid_email' => 'Please enter a valid email address'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    protected function initialize()
    {
        $this->bootPublicId();
        $this->ensureVendorSchema();
    }

    private function ensureVendorSchema(): void
    {
        if (self::$schemaEnsured) {
            return;
        }

        try {
            $ddlRow = $this->db->query('SHOW CREATE TABLE `vendors`')->getRowArray();
            $ddl = (string)($ddlRow['Create Table'] ?? '');
            if ($ddl !== '' && stripos($ddl, 'AUTO_INCREMENT') === false) {
                $this->db->query('ALTER TABLE `vendors` MODIFY `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT');
            }
        } catch (\Throwable $_) {
            // best-effort
        }

        try {
            $cols = $this->db->getFieldNames('vendors');
            if (!in_array('vendor_code', $cols, true)) {
                $this->db->query('ALTER TABLE `vendors` ADD COLUMN `vendor_code` VARCHAR(50) NULL AFTER `id`');
            }
            $idxRows = $this->db->query('SHOW INDEX FROM `vendors` WHERE Key_name = ?', ['uniq_vendor_code'])->getResultArray();
            if (empty($idxRows)) {
                $this->db->query('ALTER TABLE `vendors` ADD UNIQUE KEY `uniq_vendor_code` (`vendor_code`)');
            }
        } catch (\Throwable $_) {
            // best-effort
        }

        self::$schemaEnsured = true;
    }

    public function generateVendorCode(): string
    {
        $db = $this->db;
        $prefix = $this->getVendorCodePrefix();
        $seqName = $this->getVendorSequenceName($prefix);

        $this->ensureSequencesTable();

        $db->transStart();
        $db->query(
            'INSERT IGNORE INTO `sequences` (`name`, `last_value`, `updated_at`) VALUES (?, 0, NOW())',
            [$seqName]
        );

        $row = $db->query(
            'SELECT last_value FROM `sequences` WHERE `name` = ? FOR UPDATE',
            [$seqName]
        )->getRowArray();

        $lastValue = (int)($row['last_value'] ?? 0);
        $maxExisting = $this->getMaxExistingVendorCodeNumber($prefix);
        $nextValue = max($lastValue + 1, $maxExisting + 1);

        $db->query(
            'UPDATE `sequences` SET `last_value` = ?, `updated_at` = NOW() WHERE `name` = ?',
            [$nextValue, $seqName]
        );
        $db->transComplete();

        if ($db->transStatus() === false) {
            throw new \RuntimeException('Failed to allocate next vendor code.');
        }

        return $prefix . '-' . $nextValue;
    }

    public function peekNextVendorCode(): string
    {
        $prefix = $this->getVendorCodePrefix();
        return $prefix . '-' . $this->peekNextVendorCodeNumber($prefix);
    }

    public function peekNextVendorCodeNumber(?string $prefix = null): int
    {
        $prefix = $this->normalizeVendorPrefix($prefix ?? $this->getVendorCodePrefix());
        $seqName = $this->getVendorSequenceName($prefix);
        $this->ensureSequencesTable();

        $row = $this->db->query(
            'SELECT last_value FROM `sequences` WHERE `name` = ?',
            [$seqName]
        )->getRowArray();

        $lastValue = (int)($row['last_value'] ?? 0);
        $maxExisting = $this->getMaxExistingVendorCodeNumber($prefix);
        return max($lastValue + 1, $maxExisting + 1);
    }

    public function setNextVendorCodeNumber(int $nextNumber, ?string $prefix = null): int
    {
        $prefix = $this->normalizeVendorPrefix($prefix ?? $this->getVendorCodePrefix());
        if ($nextNumber < 1) {
            throw new \RuntimeException('Vendor next number must be at least 1.');
        }

        $minAllowed = $this->peekNextVendorCodeNumber($prefix);
        if ($nextNumber < $minAllowed) {
            throw new \RuntimeException("Cannot set vendor next number to {$nextNumber}. Minimum allowed is {$minAllowed}.");
        }

        $this->ensureSequencesTable();
        $seqName = $this->getVendorSequenceName($prefix);
        $this->db->query(
            'INSERT IGNORE INTO `sequences` (`name`, `last_value`, `updated_at`) VALUES (?, 0, NOW())',
            [$seqName]
        );
        $this->db->query(
            'UPDATE `sequences` SET `last_value` = ?, `updated_at` = NOW() WHERE `name` = ?',
            [$nextNumber - 1, $seqName]
        );

        return $nextNumber;
    }

    public function getVendorCodePrefix(): string
    {
        try {
            $cols = $this->db->getFieldNames('company_settings');
            $selectCols = [];
            if (in_array('vendor_code_prefix', $cols, true)) {
                $selectCols[] = 'vendor_code_prefix';
            }
            if (in_array('art_number_prefix', $cols, true)) {
                $selectCols[] = 'art_number_prefix';
            }

            if (!empty($selectCols)) {
                $row = $this->db->table('company_settings')
                    ->select(implode(',', $selectCols))
                    ->orderBy('id', 'ASC')
                    ->limit(1)
                    ->get()
                    ->getRowArray();

                $vendorPrefix = $this->normalizeVendorPrefix((string)($row['vendor_code_prefix'] ?? ''));
                if ($vendorPrefix !== '') {
                    return $vendorPrefix;
                }

                $artPrefix = $this->normalizeVendorPrefix((string)($row['art_number_prefix'] ?? ''));
                if ($artPrefix !== '') {
                    return $artPrefix;
                }
            }
        } catch (\Throwable $_) {
            // fallback below
        }

        return 'VEN';
    }

    private function getVendorSequenceName(string $prefix): string
    {
        return 'vendor_code_' . strtolower($this->normalizeVendorPrefix($prefix));
    }

    private function normalizeVendorPrefix(string $prefix): string
    {
        $prefix = strtoupper(trim($prefix));
        return preg_replace('/[^A-Z0-9]/', '', $prefix);
    }

    private function getMaxExistingVendorCodeNumber(string $prefix): int
    {
        $prefix = strtoupper(trim($prefix));
        try {
            $row = $this->db->query(
                "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(vendor_code, '-', -1) AS UNSIGNED)), 0) AS max_num
                 FROM `vendors`
                 WHERE vendor_code IS NOT NULL AND vendor_code <> '' AND vendor_code LIKE ?",
                [$prefix . '-%']
            )->getRowArray();
            return (int)($row['max_num'] ?? 0);
        } catch (\Throwable $_) {
            return 0;
        }
    }

    private function ensureSequencesTable(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `sequences` (
            `name` VARCHAR(100) NOT NULL PRIMARY KEY,
            `last_value` BIGINT UNSIGNED NOT NULL DEFAULT 0,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    /**
     * Get active vendors for dropdown
     */
    public function getActiveVendorsForDropdown(): array
    {
        $vendors = $this->where('is_active', true)
                        ->orderBy('name', 'ASC')
                        ->findAll();

        $dropdown = ['' => 'Select Vendor'];
        foreach ($vendors as $vendor) {
            $dropdown[$vendor['id']] = $vendor['name'];
        }

        return $dropdown;
    }

    /**
     * Get vendors with process count
     */
    public function getVendorsWithProcessCount(): array
    {
        return $this->select('vendors.*, COUNT(processes.id) as process_count')
                    ->join('processes', 'processes.vendor_id = vendors.id AND processes.is_vendor_process = 1', 'left')
                    ->where('vendors.is_active', true)
                    ->groupBy('vendors.id')
                    ->orderBy('vendors.name', 'ASC')
                    ->findAll();
    }

    /**
     * Get vendor with pending work summary
     */
    public function getVendorWithPendingWork(int $vendorId): array|null
    {
        $vendor = $this->find($vendorId);
        if (!$vendor) {
            return null;
        }

        // Get pending work summary
        $processModel = new ProcessModel();
        $vendor['pending_processes'] = $processModel->getVendorProcesses($vendorId);

        // Get pending gatepasses
        $gatepassModel = new VendorGatepassModel();
        $vendor['pending_gatepasses'] = $gatepassModel->getPendingGatepassesByVendor($vendorId);

        return $vendor;
    }

    /**
     * Search vendors
     */
    public function searchVendors(string $query): array
    {
        return $this->where('is_active', true)
                    ->groupStart()
                        ->like('vendor_code', $query)
                        ->orLike('name', $query)
                        ->orLike('contact_person', $query)
                        ->orLike('email', $query)
                        ->orLike('phone', $query)
                    ->groupEnd()
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }

    /**
     * Get vendors with statistics
     */
    public function getVendorsWithStats(): array
    {
        return $this->select('vendors.*, 
                             COUNT(DISTINCT processes.id) as total_processes,
                             COUNT(DISTINCT vendor_gatepasses.id) as total_gatepasses,
                             SUM(CASE WHEN vendor_gatepasses.type = "out" AND vendor_gatepasses.return_date IS NULL THEN vendor_gatepasses.quantity_sent ELSE 0 END) as pending_quantity')
                    ->join('processes', 'processes.vendor_id = vendors.id AND processes.is_vendor_process = 1', 'left')
                    ->join('vendor_gatepasses', 'vendor_gatepasses.vendor_id = vendors.id', 'left')
                    ->where('vendors.is_active', true)
                    ->groupBy('vendors.id')
                    ->orderBy('vendors.name', 'ASC')
                    ->findAll();
    }

    /**
     * Get vendor performance metrics
     */
    public function getVendorPerformance(int $vendorId, string $startDate = null, string $endDate = null): array
    {
        $builder = $this->db->table('vendor_gatepasses vg')
                           ->select('
                               COUNT(vg.id) as total_jobs,
                               AVG(DATEDIFF(vg.return_date, vg.dispatch_date)) as avg_turnaround_days,
                               SUM(vg.quantity_sent) as total_quantity_sent,
                               SUM(vg.quantity_received) as total_quantity_received,
                               SUM(vg.quantity_scrap) as total_quantity_scrap,
                               ROUND((SUM(vg.quantity_received) / SUM(vg.quantity_sent)) * 100, 2) as yield_percentage
                           ')
                           ->where('vg.vendor_id', $vendorId)
                           ->where('vg.type', 'in')
                           ->where('vg.return_date IS NOT NULL');

        if ($startDate) {
            $builder->where('vg.dispatch_date >=', $startDate);
        }

        if ($endDate) {
            $builder->where('vg.dispatch_date <=', $endDate);
        }

        $result = $builder->get()->getRowArray();

        return $result ?: [
            'total_jobs' => 0,
            'avg_turnaround_days' => 0,
            'total_quantity_sent' => 0,
            'total_quantity_received' => 0,
            'total_quantity_scrap' => 0,
            'yield_percentage' => 0
        ];
    }

    /**
     * Get vendors with filters (for index page)
     */
    public function getVendorsWithFilters(string $searchTerm = null, string $typeFilter = null, string $statusFilter = null, int $perPage = 20)
    {
        $builder = $this->select('vendors.*, COUNT(processes.id) as process_count')
                        ->join('processes', 'processes.vendor_id = vendors.id', 'left')
                        ->groupBy('vendors.id');

        if ($searchTerm) {
            $builder->groupStart()
                    ->like('vendors.name', $searchTerm)
                    ->orLike('vendors.contact_person', $searchTerm)
                    ->orLike('vendors.email', $searchTerm)
                    ->orLike('vendors.phone', $searchTerm)
                    ->groupEnd();
        }

        if ($statusFilter !== null && $statusFilter !== '') {
            $builder->where('vendors.is_active', $statusFilter === '1');
        }

        $builder->orderBy('vendors.name', 'ASC');

        // Return paginated results
        return $builder->paginate($perPage);
    }

    /**
     * Get vendor types (simplified for now)
     */
    public function getVendorTypes(): array
    {
        return [
            'manufacturing',
            'service', 
            'supplier',
            'contractor'
        ];
    }

    /**
     * Get vendor with detailed information
     */
    public function getVendorWithDetails(int $vendorId): array|null
    {
        $vendor = $this->find($vendorId);
        if (!$vendor) {
            return null;
        }

        // Get process count
        $processModel = new \App\Models\ProcessModel();
        $vendor['process_count'] = $processModel->where('vendor_id', $vendorId)->countAllResults();

        // Get processes list
        $vendor['processes'] = $processModel->where('vendor_id', $vendorId)
                                          ->select('id, name, product_id')
                                          ->findAll();

        // Get vendor contacts (if any)
        $vcModel = new \App\Models\VendorContactModel();
        $vendor['contacts'] = $vcModel->getByVendor($vendorId);

        return $vendor;
    }
}
