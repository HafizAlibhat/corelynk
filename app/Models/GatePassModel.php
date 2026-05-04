<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Traits\PublicIdTrait;

class GatePassModel extends Model
{
    use PublicIdTrait;
    protected $table = 'gate_passes';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $protectFields = true;
    protected $allowedFields = [
        'public_id',
        'gate_pass_number',
        'type',
        'recipient_type',
        'recipient_name',
        'vendor_id',
        'purpose',
        'items',
        'status',
        'expected_date',
        'actual_date',
        'notes',
        'remarks',
        'created_by',
        'completed_by',
        'created_at',
        'updated_at'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    // Validation
    protected $validationRules = [
        'gate_pass_number' => 'required|max_length[50]',
        'type' => 'required|in_list[incoming,outgoing]',
        'recipient_type' => 'permit_empty|in_list[vendor,internal]',
        'recipient_name' => 'permit_empty|max_length[150]',
        'vendor_id' => 'permit_empty|numeric',
        'purpose' => 'permit_empty|max_length[500]',
        'status' => 'in_list[pending,approved,completed,rejected,cancelled]'
    ];

    protected $validationMessages = [
        'gate_pass_number' => [
            'required' => 'Gate pass number is required',
            'max_length' => 'Gate pass number cannot exceed 50 characters'
        ],
        'type' => [
            'required' => 'Gate pass type is required',
            'in_list' => 'Invalid gate pass type'
        ],
        'vendor_id' => [
            'numeric' => 'Invalid vendor ID'
        ],
        'purpose' => [
            'max_length' => 'Purpose cannot exceed 500 characters'
        ]
    ];

    protected $skipValidation = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert = ['generateGatePassNumber'];
    protected $afterInsert = [];
    protected $beforeUpdate = [];
    protected $afterUpdate = [];
    protected $beforeFind = [];
    protected $afterFind = [];
    protected $beforeDelete = [];
    protected $afterDelete = [];

    /**
     * Generate gate pass number before insert
     */
    protected function generateGatePassNumber(array $data)
    {
        if (!isset($data['data']['gate_pass_number'])) {
            $count = $this->countAll() + 1;
            $data['data']['gate_pass_number'] = 'GP-' . date('Ymd') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
        }
        return $data;
    }

    /**
     * Get gate passes with vendor information
     */
    public function getGatePassesWithVendor($limit = null, $offset = null)
    {
        $builder = $this->select('
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.contact_person,
            vendors.phone as vendor_phone,
            users.username as created_by_name
        ')
        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
        ->join('users', 'users.id = gate_passes.created_by', 'left')
        ->orderBy('gate_passes.created_at', 'DESC');

        if ($limit !== null) {
            $builder->limit($limit, $offset);
        }

        return $builder->findAll();
    }

    /**
     * Get gate passes by status
     */
    public function getByStatus($status, $limit = null)
    {
        $builder = $this->where('status', $status)
            ->orderBy('created_at', 'DESC');

        if ($limit !== null) {
            $builder->limit($limit);
        }

        return $builder->findAll();
    }

    /**
     * Get gate passes by type
     */
    public function getByType($type, $limit = null)
    {
        $builder = $this->where('type', $type)
            ->orderBy('created_at', 'DESC');

        if ($limit !== null) {
            $builder->limit($limit);
        }

        return $builder->findAll();
    }

    /**
     * Get gate passes by date range
     */
    public function getByDateRange($startDate, $endDate)
    {
        return $this->where('created_at >=', $startDate)
            ->where('created_at <=', $endDate)
            ->orderBy('created_at', 'DESC')
            ->findAll();
    }

    /**
     * Get statistics for dashboard
     */
    public function getStatistics()
    {
        $stats = [];
        
        // Total gate passes
        $stats['total'] = $this->countAll();
        
        // By status
        $stats['pending'] = $this->where('status', 'pending')->countAllResults(false);
        $stats['approved'] = $this->where('status', 'approved')->countAllResults(false);
        $stats['completed'] = $this->where('status', 'completed')->countAllResults(false);
        $stats['rejected'] = $this->where('status', 'rejected')->countAllResults(false);
        
        // By type
        $stats['incoming'] = $this->where('type', 'incoming')->countAllResults(false);
        $stats['outgoing'] = $this->where('type', 'outgoing')->countAllResults(false);
        
        // Today's passes
        $stats['today'] = $this->where('DATE(created_at)', date('Y-m-d'))->countAllResults(false);
        
        // This month's passes
        $stats['this_month'] = $this->where('MONTH(created_at)', date('m'))
            ->where('YEAR(created_at)', date('Y'))
            ->countAllResults(false);
        
        return $stats;
    }

    /**
     * Search gate passes
     */
    public function searchGatePasses($searchTerm, $filters = [])
    {
        $builder = $this->select('
            gate_passes.*,
            vendors.name as vendor_name,
            vendors.contact_person,
            users.username as created_by_name
        ')
        ->join('vendors', 'vendors.id = gate_passes.vendor_id', 'left')
        ->join('users', 'users.id = gate_passes.created_by', 'left');

        // Search in multiple fields
        if (!empty($searchTerm)) {
            $builder->groupStart()
                ->like('gate_passes.gate_pass_number', $searchTerm)
                ->orLike('gate_passes.purpose', $searchTerm)
                ->orLike('vendors.name', $searchTerm)
                ->orLike('gate_passes.notes', $searchTerm)
                ->groupEnd();
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $builder->where('gate_passes.status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $builder->where('gate_passes.type', $filters['type']);
        }

        if (!empty($filters['vendor_id'])) {
            $builder->where('gate_passes.vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['date_from'])) {
            $builder->where('gate_passes.created_at >=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $builder->where('gate_passes.created_at <=', $filters['date_to']);
        }

        return $builder->orderBy('gate_passes.created_at', 'DESC')->findAll();
    }
}
