<?php

namespace App\Models;

use CodeIgniter\Model;

class ComponentModel extends Model
{
    protected $table            = 'components';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'name', 'code', 'description', 'unit', 'current_stock', 
        'minimum_stock', 'unit_cost', 'is_active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'name'          => 'required|min_length[2]|max_length[100]',
        'code'          => 'required|min_length[2]|max_length[50]|is_unique[components.code,id,{id}]',
        'unit'          => 'required|min_length[2]|max_length[20]',
        'current_stock' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'minimum_stock' => 'permit_empty|decimal|greater_than_equal_to[0]',
        'unit_cost'     => 'permit_empty|decimal|greater_than_equal_to[0]'
    ];

    protected $validationMessages = [
        'name' => [
            'required'    => 'Component name is required',
            'min_length'  => 'Component name must be at least 2 characters'
        ],
        'code' => [
            'required'    => 'Component code is required',
            'is_unique'   => 'Component code already exists'
        ],
        'unit' => [
            'required' => 'Unit is required'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    /**
     * Get components with filters and pagination
     */
    public function getComponentsWithFilters($searchTerm = null, $stockFilter = null, $statusFilter = null, $perPage = 20): array
    {
        $builder = $this->select('components.*, 
                                 CASE 
                                     WHEN current_stock <= minimum_stock THEN "Low Stock"
                                     WHEN current_stock <= (minimum_stock * 1.5) THEN "Warning"
                                     ELSE "OK"
                                 END as stock_status,
                                 (current_stock * unit_cost) as stock_value');

        // Apply search filter
        if (!empty($searchTerm)) {
            $builder->groupStart()
                    ->like('components.name', $searchTerm)
                    ->orLike('components.code', $searchTerm)
                    ->orLike('components.description', $searchTerm)
                    ->groupEnd();
        }

        // Apply stock filter
        if (!empty($stockFilter)) {
            switch ($stockFilter) {
                case 'low':
                    $builder->where('components.current_stock <= components.minimum_stock');
                    break;
                case 'warning':
                    $builder->where('components.current_stock >', 'components.minimum_stock', false)
                            ->where('components.current_stock <=', 'components.minimum_stock * 1.5', false);
                    break;
                case 'ok':
                    $builder->where('components.current_stock >', 'components.minimum_stock * 1.5', false);
                    break;
            }
        }

        // Apply status filter
        if (!empty($statusFilter)) {
            $builder->where('components.is_active', $statusFilter === 'active' ? 1 : 0);
        } else {
            // Default to active components only
            $builder->where('components.is_active', 1);
        }

        $builder->orderBy('components.name', 'ASC');

        return $builder->paginate($perPage);
    }

    /**
     * Get active components for dropdown
     */
    public function getActiveComponentsForDropdown(): array
    {
        $components = $this->where('is_active', true)
                          ->orderBy('name', 'ASC')
                          ->findAll();

        $dropdown = ['' => 'Select Component'];
        foreach ($components as $component) {
            $dropdown[$component['id']] = $component['name'] . ' (' . $component['code'] . ')';
        }

        return $dropdown;
    }

    /**
     * Get components with stock status
     */
    public function getComponentsWithStockStatus(): array
    {
        return $this->select('components.*, 
                             CASE 
                                 WHEN current_stock <= minimum_stock THEN "Low Stock"
                                 WHEN current_stock <= (minimum_stock * 1.5) THEN "Warning"
                                 ELSE "OK"
                             END as stock_status,
                             (current_stock * unit_cost) as stock_value')
                    ->where('is_active', true)
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }

    /**
     * Get low stock components
     */
    public function getLowStockComponents(): array
    {
        return $this->where('is_active', true)
                    ->where('current_stock <= minimum_stock')
                    ->where('minimum_stock >', 0)
                    ->orderBy('current_stock', 'ASC')
                    ->findAll();
    }

    /**
     * Update stock quantity
     */
    public function updateStock(int $componentId, float $quantity, string $transactionType, string $referenceType, int $referenceId = null, int $createdBy = null): bool
    {
        $component = $this->find($componentId);
        if (!$component) {
            return false;
        }

        $this->db->transStart();

        // Calculate new stock level
        $newStock = $component['current_stock'];
        if ($transactionType === 'in') {
            $newStock += $quantity;
        } elseif ($transactionType === 'out') {
            $newStock -= $quantity;
            if ($newStock < 0) {
                $newStock = 0; // Prevent negative stock
            }
        } else { // adjustment
            $newStock = $quantity;
        }

        // Update component stock
        $this->update($componentId, ['current_stock' => $newStock]);

        // Record transaction
        $stockTransactionModel = new ComponentStockTransactionModel();
        $transactionData = [
            'component_id' => $componentId,
            'transaction_type' => $transactionType,
            'quantity' => $quantity,
            'unit_cost' => $component['unit_cost'],
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy
        ];

        $stockTransactionModel->insert($transactionData);

        $this->db->transComplete();
        return $this->db->transStatus();
    }

    /**
     * Get component usage history
     */
    public function getComponentUsageHistory(int $componentId, int $limit = 50): array
    {
        $stockTransactionModel = new ComponentStockTransactionModel();
        return $stockTransactionModel->getTransactionsByComponent($componentId, $limit);
    }

    /**
     * Search components
     */
    public function searchComponents(string $query): array
    {
        return $this->where('is_active', true)
                    ->groupStart()
                        ->like('name', $query)
                        ->orLike('code', $query)
                        ->orLike('description', $query)
                    ->groupEnd()
                    ->orderBy('name', 'ASC')
                    ->findAll();
    }

    /**
     * Get components with statistics
     */
    public function getComponentsWithStats(): array
    {
        return $this->select('components.*, 
                             COUNT(DISTINCT component_usage.work_order_id) as used_in_work_orders,
                             SUM(component_usage.quantity_used) as total_quantity_used,
                             AVG(component_usage.quantity_used) as avg_usage_per_wo')
                    ->join('component_usage', 'component_usage.component_id = components.id', 'left')
                    ->where('components.is_active', true)
                    ->groupBy('components.id')
                    ->orderBy('components.name', 'ASC')
                    ->findAll();
    }

    /**
     * Calculate total stock value
     */
    public function getTotalStockValue(): float
    {
        $result = $this->select('SUM(current_stock * unit_cost) as total_value')
                       ->where('is_active', true)
                       ->get()
                       ->getRowArray();

        return $result['total_value'] ?? 0.0;
    }

    /**
     * Get stock movement report
     */
    public function getStockMovementReport(string $startDate = null, string $endDate = null): array
    {
        $stockTransactionModel = new ComponentStockTransactionModel();
        
        $builder = $stockTransactionModel->select('
                        components.name as component_name,
                        components.code as component_code,
                        components.unit,
                        SUM(CASE WHEN transaction_type = "in" THEN quantity ELSE 0 END) as total_in,
                        SUM(CASE WHEN transaction_type = "out" THEN quantity ELSE 0 END) as total_out,
                        SUM(CASE WHEN transaction_type = "adjustment" THEN quantity ELSE 0 END) as total_adjustments,
                        COUNT(*) as total_transactions
                    ')
                    ->join('components', 'components.id = component_stock_transactions.component_id')
                    ->where('components.is_active', true);

        if ($startDate) {
            $builder->where('component_stock_transactions.created_at >=', $startDate);
        }

        if ($endDate) {
            $builder->where('component_stock_transactions.created_at <=', $endDate);
        }

        return $builder->groupBy('components.id')
                       ->orderBy('components.name', 'ASC')
                       ->findAll();
    }

    /**
     * Validate stock availability for work order
     */
    public function validateStockAvailability(int $workOrderId): array
    {
        $componentUsageModel = new ComponentUsageModel();
        $requiredComponents = $componentUsageModel->getUsageByWorkOrder($workOrderId);

        $shortages = [];
        foreach ($requiredComponents as $usage) {
            $component = $this->find($usage['component_id']);
            if ($component && $component['current_stock'] < $usage['quantity_required']) {
                $shortages[] = [
                    'component_id' => $component['id'],
                    'component_name' => $component['name'],
                    'component_code' => $component['code'],
                    'required_quantity' => $usage['quantity_required'],
                    'available_quantity' => $component['current_stock'],
                    'shortage' => $usage['quantity_required'] - $component['current_stock']
                ];
            }
        }

        return $shortages;
    }

    /**
     * Get product BOM (Bill of Materials)
     */
    public function getProductBom($productId): array
    {
        $db = \Config\Database::connect();
        
        $sql = "SELECT pb.*, c.name, c.code, c.unit as component_unit
                FROM product_bom pb
                JOIN components c ON pb.component_id = c.id
                WHERE pb.product_id = ? AND c.is_active = 1
                ORDER BY c.name";
        
        $query = $db->query($sql, [$productId]);
        return $query->getResultArray();
    }

    /**
     * Update product BOM
     */
    public function updateProductBom($productId, $bomData): bool
    {
        $db = \Config\Database::connect();
        
        try {
            $db->transStart();
            
            // Delete existing BOM entries for this product
            $db->table('product_bom')->where('product_id', $productId)->delete();
            
            // Insert new BOM entries
            if (!empty($bomData)) {
                foreach ($bomData as $item) {
                    $data = [
                        'product_id' => $productId,
                        'component_id' => $item['component_id'],
                        'quantity' => $item['quantity'],
                        'unit' => $item['unit'] ?? '',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $db->table('product_bom')->insert($data);
                }
            }
            
            $db->transComplete();
            
            return $db->transStatus();
        } catch (\Exception $e) {
            log_message('error', 'Error updating product BOM: ' . $e->getMessage());
            return false;
        }
    }
}
