<?php

namespace App\Models;

use CodeIgniter\Model;

class GrnReceiptHistoryModel extends Model
{
    protected $table = 'grn_receipt_history';
    protected $primaryKey = 'id';
    protected $useAutoIncrement = true;
    protected $returnType = 'array';
    protected $useSoftDeletes = false;
    protected $allowedFields = [
        'po_id', 'po_line_id', 'grn_id', 'grn_line_id', 'product_id', 'variant_id',
        'unit_price', 'qty_ordered', 'qty_previously_received', 'qty_received_this_grn',
        'warehouse_id', 'location_id', 'received_date', 'previous_grn_id', 'notes', 'created_by'
    ];
    protected $useTimestamps = true;
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';

    /**
     * Get receipt history for a PO line (all GRNs that received from this line)
     */
    public function getLineHistory(int $poLineId): array
    {
        return $this->where('po_line_id', $poLineId)
            ->orderBy('received_date', 'ASC')
            ->find All();
    }

    /**
     * Get receipt history for a PO (all GRNs for this PO)
     */
    public function getPoHistory(int $poId): array
    {
        return $this->where('po_id', $poId)
            ->orderBy('received_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll();
    }

    /**
     * Get receipt history for a specific GRN
     */
    public function getGrnHistory(int $grnId): array
    {
        return $this->where('grn_id', $grnId)->findAll();
    }

    /**
     * Calculate total received qty for a PO line across all GRNs
     */
    public function getTotalReceivedQty(int $poLineId): float
    {
        $result = $this->selectSum('qty_received_this_grn')
            ->where('po_line_id', $poLineId)
            ->first();
        
        return (float)($result['qty_received_this_grn'] ?? 0);
    }

    /**
     * Get all GRNs that reference a previous GRN (continuation receipts)
     */
    public function getRelatedGrns(int $previousGrnId): array
    {
        return $this->where('previous_grn_id', $previousGrnId)
            ->select('DISTINCT grn_id')
            ->findAll();
    }

    /**
     * Record a receipt event in the audit trail
     */
    public function recordReceipt(array $data): int
    {
        // Ensure required fields
        $required = ['po_id', 'po_line_id', 'grn_id', 'product_id', 'qty_received_this_grn', 'received_date', 'created_by'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \RuntimeException("Required field missing: {$field}");
            }
        }

        return $this->insert($data, true);
    }
}
