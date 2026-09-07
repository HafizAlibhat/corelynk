<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductProcessModel extends Model
{
    protected $table            = 'product_processes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        // Support both legacy template-based mapping and actual process mapping
        'product_id', 'process_template_id', 'process_id', 'sequence_order', 
        'custom_time_minutes', 'custom_notes', 'is_active'
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'product_id'          => 'required|integer|is_not_unique[products.id]',
        // Allow either template-based or actual process mapping; both optional individually
        'process_template_id' => 'permit_empty|integer|is_not_unique[process_templates.id]',
        'process_id'          => 'permit_empty|integer|is_not_unique[processes.id]',
        'sequence_order'      => 'required|integer|greater_than[0]',
        'custom_time_minutes' => 'permit_empty|integer|greater_than_equal_to[0]',
        'is_active'          => 'permit_empty|in_list[0,1]'
    ];

    protected $validationMessages = [
        'product_id' => [
            'required'      => 'Product is required',
            'is_not_unique' => 'Selected product does not exist'
        ],
        'process_template_id' => [
            'is_not_unique' => 'Selected process template does not exist'
        ],
        'process_id' => [
            'is_not_unique' => 'Selected process does not exist'
        ],
        'sequence_order' => [
            'required'     => 'Sequence order is required',
            'greater_than' => 'Sequence order must be greater than 0'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    /**
     * Get product processes with workflow details
     */
    public function getProductProcessesWithDetails($productId): array
    {
        // Detect optional columns
        $seqCol = 'sequence_order';
        $ppHasActive = false;
        try {
            $cols = $this->db->query('SHOW COLUMNS FROM product_processes')->getResultArray();
            $names = array_column($cols, 'Field');
            $ppHasActive = in_array('is_active', $names, true);
            if (!in_array($seqCol, $names, true)) {
                if (in_array('sequence_number', $names, true)) $seqCol = 'sequence_number';
                elseif (in_array('sort_order', $names, true)) $seqCol = 'sort_order';
                elseif (in_array('order_index', $names, true)) $seqCol = 'order_index';
            }
        } catch (\Throwable $e) { /* ignore */ }

        // Step 1: fetch product_processes rows without joins (most robust)
        // Build select list defensively depending on which columns exist in the table
        $ppCols = [];
        try {
            $ppCols = $this->db->query('SHOW COLUMNS FROM product_processes')->getResultArray();
        } catch (\Throwable $e) {
            // table might be missing; return empty
            return [];
        }
        $ppColNames = array_column($ppCols, 'Field');

        $selectFields = ["id AS product_process_id", 'product_id', 'process_id', "{$seqCol} AS sequence_order"];
        if (in_array('custom_time_minutes', $ppColNames, true)) {
            $selectFields[] = 'custom_time_minutes';
        }
        if (in_array('custom_notes', $ppColNames, true)) {
            $selectFields[] = 'custom_notes';
        }

        $qb = $this->db->table('product_processes')
                       ->select(implode(', ', $selectFields), false)
                       ->where('product_id', $productId)
                       ->orderBy($seqCol, 'ASC');
        // Note: Not filtering on is_active here to show all attached processes, even if marked inactive
        $ppRows = $qb->get()->getResultArray() ?: [];
        if (empty($ppRows)) return [];

        // Step 2: fetch processes in one shot
        $pids = array_values(array_unique(array_map(fn($r)=> (int)($r['process_id'] ?? 0), $ppRows)));
        $procMap = [];
        if (!empty($pids)) {
            $procRows = $this->db->table('processes p')
                                 ->select('p.id, p.name, p.description, p.standard_time_minutes, p.is_vendor_process, p.vendor_id, v.name as vendor_name')
                                 ->join('vendors v', 'v.id = p.vendor_id', 'left')
                                 ->whereIn('p.id', $pids)
                                 ->get()->getResultArray();
            foreach ($procRows as $pr) {
                $procMap[(int)$pr['id']] = $pr;
            }
        }

        // Step 3: merge
        $out = [];
        foreach ($ppRows as $row) {
            $pid = (int)($row['process_id'] ?? 0);
            $proc = $procMap[$pid] ?? [];
            $out[] = [
                'product_process_id' => (int)$row['product_process_id'],
                'product_id' => (int)$row['product_id'],
                'process_id' => $pid,
                'sequence_order' => (int)($row['sequence_order'] ?? 0),
                'custom_time_minutes' => $row['custom_time_minutes'] ?? null,
                'custom_notes' => $row['custom_notes'] ?? null,
                'process_name' => $proc['name'] ?? null,
                'process_description' => $proc['description'] ?? null,
                'standard_time_minutes' => $proc['standard_time_minutes'] ?? null,
                'is_vendor_process' => isset($proc['is_vendor_process']) ? (int)$proc['is_vendor_process'] : 0,
                'vendor_id' => isset($proc['vendor_id']) ? (int)$proc['vendor_id'] : null,
                'vendor_name' => $proc['vendor_name'] ?? null,
            ];
        }
        return $out;
    }

    /**
     * Get all products that use a specific process template
     */
    public function getProductsByTemplate($templateId): array
    {
        return $this->select('product_processes.*, products.name as product_name, products.code as product_code')
                    ->join('products', 'products.id = product_processes.product_id')
                    ->where('product_processes.process_template_id', $templateId)
                    ->where('product_processes.is_active', true)
                    ->orderBy('products.name', 'ASC')
                    ->findAll();
    }

    /**
     * Add multiple processes to a product
     */
    public function addProcessesToProduct($productId, $processTemplateIds): bool
    {
        $this->db->transStart();
        
        try {
            // Get current max sequence order for this product
            $maxSequence = $this->where('product_id', $productId)
                               ->selectMax('sequence_order')
                               ->first()['sequence_order'] ?? 0;
            
            $sequence = $maxSequence + 1;
            
            foreach ($processTemplateIds as $templateId) {
                $data = [
                    'product_id' => $productId,
                    'process_template_id' => $templateId,
                    'sequence_order' => $sequence,
                    'is_active' => true
                ];
                
                $this->insert($data);
                $sequence++;
            }
            
            $this->db->transComplete();
            return $this->db->transStatus();
            
        } catch (\Exception $e) {
            $this->db->transRollback();
            return false;
        }
    }

    /**
     * Add actual processes to product (not templates)
     */
    public function addActualProcessesToProduct($productId, $processIds): bool
    {
        log_message('info', 'Adding processes to product: ' . $productId . ' with IDs: ' . print_r($processIds, true));
        
        $this->db->transStart();
        
        try {
            // Get current max sequence order for this product
            $maxSequence = $this->where('product_id', $productId)
                               ->selectMax('sequence_order')
                               ->first()['sequence_order'] ?? 0;
            
            $sequence = $maxSequence + 1;
            
            foreach ($processIds as $processId) {
                // Check if this process is already assigned to this product
                $existing = $this->where('product_id', $productId)
                               ->where('process_id', $processId)
                               ->where('is_active', true)
                               ->first();
                
                if ($existing) {
                    log_message('info', 'Process ' . $processId . ' already assigned to product ' . $productId);
                    continue; // Skip if already assigned
                }
                
                $data = [
                    'product_id' => $productId,
                    'process_id' => $processId,
                    'sequence_order' => $sequence,
                    'is_active' => true
                ];
                
                log_message('info', 'Inserting data: ' . print_r($data, true));
                $this->insert($data);
                $sequence++;
            }
            
            $this->db->transComplete();
            $status = $this->db->transStatus();
            log_message('info', 'Transaction status: ' . ($status ? 'success' : 'failed'));
            return $status;
            
        } catch (\Exception $e) {
            log_message('error', 'Error in addActualProcessesToProduct: ' . $e->getMessage());
            $this->db->transRollback();
            return false;
        }
    }

    /**
     * Bulk assign processes to multiple products
     */
    public function bulkAssignProcesses($productIds, $processTemplateIds): bool
    {
        $this->db->transStart();
        
        try {
            foreach ($productIds as $productId) {
                // Get current max sequence order for this product
                $maxSequence = $this->where('product_id', $productId)
                                   ->selectMax('sequence_order')
                                   ->first()['sequence_order'] ?? 0;
                
                $sequence = $maxSequence + 1;
                
                foreach ($processTemplateIds as $templateId) {
                    // Check if this combination already exists
                    $existing = $this->where('product_id', $productId)
                                    ->where('process_template_id', $templateId)
                                    ->first();
                    
                    if (!$existing) {
                        $data = [
                            'product_id' => $productId,
                            'process_template_id' => $templateId,
                            'sequence_order' => $sequence,
                            'is_active' => true
                        ];
                        
                        $this->insert($data);
                        $sequence++;
                    }
                }
            }
            
            $this->db->transComplete();
            return $this->db->transStatus();
            
        } catch (\Exception $e) {
            $this->db->transRollback();
            return false;
        }
    }

    /**
     * Reorder product processes
     */
    public function reorderProcesses($productId, $processIds): bool
    {
        $this->db->transStart();

        try {
            // Detect sequence column
            $seqCol = 'sequence_order';
            try {
                $cols = $this->db->query('SHOW COLUMNS FROM product_processes')->getResultArray();
                $names = array_column($cols, 'Field');
                if (!in_array($seqCol, $names, true)) {
                    if (in_array('sequence_number', $names, true)) $seqCol = 'sequence_number';
                    elseif (in_array('sort_order', $names, true)) $seqCol = 'sort_order';
                    elseif (in_array('order_index', $names, true)) $seqCol = 'order_index';
                }
            } catch (\Throwable $e) { /* keep default */ }

            $sequence = 1;
            foreach ($processIds as $processId) {
                $this->update($processId, [ $seqCol => $sequence ]);
                $sequence++;
            }

            $this->db->transComplete();
            return $this->db->transStatus();
        } catch (\Exception $e) {
            $this->db->transRollback();
            return false;
        }
    }

    /**
     * Remove process from product
     */
    public function removeProcessFromProduct($productId, $processTemplateId): bool
    {
        $this->db->transStart();
        
        try {
            // Get the process to remove
            $processToRemove = $this->where('product_id', $productId)
                                   ->where('process_template_id', $processTemplateId)
                                   ->first();
            
            if (!$processToRemove) {
                return false;
            }
            
            // Delete the process
            $this->delete($processToRemove['id']);
            
            // Reorder remaining processes
            $remainingProcesses = $this->where('product_id', $productId)
                                      ->where('is_active', true)
                                      ->orderBy('sequence_order', 'ASC')
                                      ->findAll();
            
            $sequence = 1;
            foreach ($remainingProcesses as $process) {
                $this->update($process['id'], [
                    'sequence_order' => $sequence
                ]);
                $sequence++;
            }
            
            $this->db->transComplete();
            return $this->db->transStatus();
            
        } catch (\Exception $e) {
            $this->db->transRollback();
            return false;
        }
    }

    /**
     * Get total estimated time for a product
     */
    public function getProductTotalTime($productId): int
    {
        $processes = $this->getProductProcessesWithDetails($productId);
        $totalTime = 0;
        
        foreach ($processes as $process) {
            // Use custom time if available, otherwise use standard time, default to 0
            $time = $process['custom_time_minutes'] ?? $process['standard_time_minutes'] ?? 0;
            $totalTime += (int) $time;
        }
        
        return $totalTime;
    }

    /**
     * Get process statistics by category
     */
    public function getProcessStatsByCategory(): array
    {
        return $this->select('process_templates.category, COUNT(*) as usage_count, AVG(COALESCE(product_processes.custom_time_minutes, process_templates.standard_time_minutes)) as avg_time')
                    ->join('process_templates', 'process_templates.id = product_processes.process_template_id')
                    ->where('product_processes.is_active', true)
                    ->groupBy('process_templates.category')
                    ->orderBy('usage_count', 'DESC')
                    ->findAll();
    }

    /**
     * Copy processes from one product to another
     */
    public function copyProcessesToProduct($fromProductId, $toProductId): bool
    {
        $sourceProcesses = $this->where('product_id', $fromProductId)
                               ->where('is_active', true)
                               ->orderBy('sequence_order', 'ASC')
                               ->findAll();
        
        if (empty($sourceProcesses)) {
            return false;
        }
        
        $this->db->transStart();
        
        try {
            foreach ($sourceProcesses as $process) {
                $newProcess = [
                    'product_id' => $toProductId,
                    'process_template_id' => $process['process_template_id'],
                    'sequence_order' => $process['sequence_order'],
                    'custom_time_minutes' => $process['custom_time_minutes'],
                    'custom_notes' => $process['custom_notes'],
                    'is_active' => true
                ];
                
                $this->insert($newProcess);
            }
            
            $this->db->transComplete();
            return $this->db->transStatus();
            
        } catch (\Exception $e) {
            $this->db->transRollback();
            return false;
        }
    }
}
