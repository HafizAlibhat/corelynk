<?php

namespace App\Services;

use App\Models\PurchaseOrderLineModel;
use App\Models\PurchaseGrnModel;
use App\Models\PurchaseGrnLineModel;
use App\Models\GrnReceiptHistoryModel;
use Config\Database;

class GrnPartialReceiptService
{
    protected $db;
    protected $receiptHistoryModel;
    protected $poLineFields = [];
    protected $qtyColumn = 'qty';
    protected $unitPriceColumn = null;
    protected $hasReceiveStatus = false;
    protected $hasFullyReceivedDate = false;
    protected $hasVariantId = false;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->receiptHistoryModel = new GrnReceiptHistoryModel();
        $this->poLineFields = array_flip($this->db->getFieldNames('purchase_order_lines'));
        $this->qtyColumn = isset($this->poLineFields['qty']) ? 'qty' : 'quantity';
        $this->unitPriceColumn = isset($this->poLineFields['unit_price'])
            ? 'unit_price'
            : (isset($this->poLineFields['unit_cost']) ? 'unit_cost' : null);
        $this->hasReceiveStatus = isset($this->poLineFields['receive_status']);
        $this->hasFullyReceivedDate = isset($this->poLineFields['fully_received_date']);
        $this->hasVariantId = isset($this->poLineFields['variant_id']);
    }

    protected function getPoLine(int $poLineId, array $columns = []): ?array
    {
        $selects = [];
        foreach ($columns as $column) {
            if (isset($this->poLineFields[$column])) {
                $selects[] = $column;
            }
        }

        if (empty($selects)) {
            $selects[] = 'id';
        }

        return $this->db->table('purchase_order_lines')
            ->select(implode(', ', array_unique($selects)))
            ->where('id', $poLineId)
            ->get()
            ->getRowArray();
    }

    protected function deriveStatusFromQuantities(float $orderedQty, float $receivedQty): string
    {
        if ($receivedQty <= 0.00001) {
            return 'pending';
        }

        if ($receivedQty + 0.01 < $orderedQty) {
            return 'partially_received';
        }

        return 'fully_received';
    }

    protected function getLineStatus(array $line): string
    {
        if ($this->hasReceiveStatus && !empty($line['receive_status'])) {
            return (string)$line['receive_status'];
        }

        $orderedQty = (float)($line[$this->qtyColumn] ?? 0);
        $receivedQty = (float)($line['qty_received'] ?? 0);
        return $this->deriveStatusFromQuantities($orderedQty, $receivedQty);
    }

    /**
     * Get pending qty for a PO line (ordered - previously received)
     */
    public function getPendingQty(int $poLineId): float
    {
        $poLine = $this->getPoLine($poLineId, [$this->qtyColumn, 'qty_received']);

        if (!$poLine) {
            return 0.0;
        }

        $orderedQty = (float)($poLine[$this->qtyColumn] ?? 0);
        $received = (float)($poLine['qty_received'] ?? 0);

        $pending = $orderedQty - $received;
        return max(0.0, $pending);
    }

    /**
     * Validate that qty received doesn't exceed pending
     * 
     * @throws \RuntimeException if validation fails
     */
    public function validateReceiptQty(int $poLineId, float $qtyToReceive): bool
    {
        $pending = $this->getPendingQty($poLineId);

        if ($qtyToReceive > $pending + 0.01) { // Allow 0.01 rounding tolerance
            throw new \RuntimeException(
                "Cannot receive {$qtyToReceive} units. Only {$pending} units pending."
            );
        }

        if ($qtyToReceive < 0) {
            throw new \RuntimeException("Receive quantity must be greater than 0");
        }

        return true;
    }

    /**
     * Calculate what the PO line status should be
     */
    public function calculateLineStatus(int $poLineId): string
    {
        $columns = [$this->qtyColumn, 'qty_received'];
        if ($this->hasReceiveStatus) {
            $columns[] = 'receive_status';
        }

        $poLine = $this->getPoLine($poLineId, $columns);

        if (!$poLine) {
            return 'pending';
        }

        if ($this->hasReceiveStatus && !empty($poLine['receive_status'])) {
            return (string)$poLine['receive_status'];
        }

        $orderedQty = (float)($poLine[$this->qtyColumn] ?? 0);
        $receivedQty = (float)($poLine['qty_received'] ?? 0);

        return $this->deriveStatusFromQuantities($orderedQty, $receivedQty);
    }

    /**
     * Update PO line status based on receipt history
     */
    public function updateLineStatus(int $poLineId): bool
    {
        if (!$this->hasReceiveStatus) {
            return true;
        }

        $status = $this->calculateLineStatus($poLineId);
        $update = ['receive_status' => $status];

        // If fully received, set the date
        if ($status === 'fully_received' && $this->hasFullyReceivedDate) {
            $update['fully_received_date'] = date('Y-m-d H:i:s');
        }

        return (bool)$this->db->table('purchase_order_lines')
            ->where('id', $poLineId)
            ->update($update);
    }

    /**
     * Process GRN creation with partial receipt support
     * 
     * Wraps in transaction to ensure atomicity
     */
    public function processGrnCreation(int $grnId, array $lineData, int $userId): array
    {
        $this->db->transBegin();

        try {
            $result = [
                'success' => false,
                'message' => '',
                'updated_lines' => [],
            ];

            // Validate GRN exists
            $grn = $this->db->table('purchase_grns')
                ->select('id, po_id')
                ->where('id', $grnId)
                ->first();

            if (!$grn) {
                throw new \RuntimeException('GRN not found: ' . $grnId);
            }

            $poId = (int)$grn->po_id;

            // Process each GRN line
            foreach ($lineData as $grnLine) {
                $poLineId = (int)($grnLine['po_line_id'] ?? 0);
                $qtyReceived = (float)($grnLine['qty_received'] ?? 0);
                $warehouseId = (int)($grnLine['warehouse_id'] ?? 0);
                $locationId = (int)($grnLine['location_id'] ?? 0);

                if ($poLineId <= 0 || $qtyReceived <= 0) {
                    continue; // Skip empty lines
                }

                // Validate qty doesn't exceed pending
                $this->validateReceiptQty($poLineId, $qtyReceived);

                // Get PO line details for receipt history
                $columns = ['product_id', $this->qtyColumn];
                if ($this->hasVariantId) {
                    $columns[] = 'variant_id';
                }
                if ($this->unitPriceColumn) {
                    $columns[] = $this->unitPriceColumn;
                }

                $poLine = $this->getPoLine($poLineId, $columns);

                if (!$poLine) {
                    throw new \RuntimeException('PO line not found: ' . $poLineId);
                }

                // Controller updates purchase_order_lines.qty_received before calling this service,
                // so subtract the current GRN receipt to capture the pre-submit value when possible.
                $previouslyReceived = max(0.0, ((float)($grnLine['previous_qty_received'] ?? 0)));

                // Record in grn_receipt_history (audit trail)
                $historyRecord = [
                    'po_id' => $poId,
                    'po_line_id' => $poLineId,
                    'grn_id' => $grnId,
                    'product_id' => (int)($poLine['product_id'] ?? 0),
                    'variant_id' => (int)($poLine['variant_id'] ?? 0) ?: null,
                    'unit_price' => $this->unitPriceColumn ? (float)($poLine[$this->unitPriceColumn] ?? 0) : 0.0,
                    'qty_ordered' => (float)($poLine[$this->qtyColumn] ?? 0),
                    'qty_previously_received' => $previouslyReceived,
                    'qty_received_this_grn' => $qtyReceived,
                    'warehouse_id' => $warehouseId ?: null,
                    'location_id' => $locationId ?: null,
                    'received_date' => date('Y-m-d H:i:s'),
                    'created_by' => $userId,
                ];

                $historyRecord['grn_line_id'] = $this->db->table('purchase_grn_lines')
                    ->select('id')
                    ->where('grn_id', $grnId)
                    ->where('po_line_id', $poLineId)
                    ->get()
                    ->getRow('id');

                // Insert receipt history
                $this->receiptHistoryModel->recordReceipt($historyRecord);

                // Update PO line status based on new receipt
                $this->updateLineStatus($poLineId);

                $result['updated_lines'][] = [
                    'po_line_id' => $poLineId,
                    'qty_received' => $qtyReceived,
                    'new_status' => $this->calculateLineStatus($poLineId),
                ];
            }

            // All validations passed and history recorded
            $this->db->transCommit();
            
            $result['success'] = true;
            $result['message'] = 'GRN processed successfully with ' . count($result['updated_lines']) . ' line(s)';

            return $result;

        } catch (\Throwable $e) {
            $this->db->transRollback();
            
            return [
                'success' => false,
                'message' => 'GRN processing failed: ' . $e->getMessage(),
                'updated_lines' => [],
            ];
        }
    }

    /**
     * Get remaining pending items for a PO (for continuation receipts)
     */
    public function getPendingLines(int $poId): array
    {
        $selectParts = ['id', 'product_id', $this->qtyColumn, 'qty_received'];
        if ($this->hasVariantId) {
            $selectParts[] = 'variant_id';
        }
        if ($this->hasReceiveStatus) {
            $selectParts[] = 'receive_status';
        }

        // Get all PO lines with calculated pending qty
        $poLines = $this->db->table('purchase_order_lines')
            ->select(implode(', ', $selectParts))
            ->where('po_id', $poId)
            ->where($this->qtyColumn . ' >', 0) // Only lines with qty > 0
            ->get()
            ->getResultArray();

        $pending = [];

        foreach ($poLines as $line) {
            $pendingQty = $this->getPendingQty((int)$line['id']);
            
            if ($pendingQty > 0.005) { // More than rounding tolerance
                $pending[] = [
                    'po_line_id' => (int)$line['id'],
                    'product_id' => (int)$line['product_id'],
                    'variant_id' => (int)($line['variant_id'] ?? 0) ?: null,
                    'qty_ordered' => (float)($line[$this->qtyColumn] ?? 0),
                    'qty_pending' => $pendingQty,
                    'status' => $this->getLineStatus($line),
                ];
            }
        }

        return $pending;
    }

    /**
     * Check if PO is fully received (all lines fully_received)
     */
    public function isPoFullyReceived(int $poId): bool
    {
        if (!$this->hasReceiveStatus) {
            return empty($this->getPendingLines($poId));
        }

        // Count lines that are NOT fully received
        $notFullyReceived = $this->db->table('purchase_order_lines')
            ->where('po_id', $poId)
            ->where($this->qtyColumn . ' >', 0)
            ->where('receive_status !=', 'fully_received')
            ->countAllResults();

        return $notFullyReceived === 0;
    }

    /**
     * Get PO receipt summary 
     */
    public function getPoReceiptSummary(int $poId): array
    {
        $selectParts = ['id', $this->qtyColumn, 'qty_received'];
        if ($this->hasReceiveStatus) {
            $selectParts[] = 'receive_status';
        }

        $poLines = $this->db->table('purchase_order_lines')
            ->select(implode(', ', $selectParts))
            ->where('po_id', $poId)
            ->where($this->qtyColumn . ' >', 0)
            ->get()
            ->getResultArray();

        $summary = [
            'total_lines' => 0,
            'pending_lines' => 0,
            'partially_received_lines' => 0,
            'fully_received_lines' => 0,
            'total_qty_ordered' => 0.0,
            'total_qty_pending' => 0.0,
            'is_fully_received' => false,
        ];

        foreach ($poLines as $line) {
            $summary['total_lines']++;
            $summary['total_qty_ordered'] += (float)($line[$this->qtyColumn] ?? 0);

            $status = $this->getLineStatus($line);
            if ($status === 'pending') {
                $summary['pending_lines']++;
                $summary['total_qty_pending'] += (float)($line[$this->qtyColumn] ?? 0);
            } elseif ($status === 'partially_received') {
                $summary['partially_received_lines']++;
                $pendingQty = $this->getPendingQty((int)$line['id']);
                $summary['total_qty_pending'] += $pendingQty;
            } elseif ($status === 'fully_received') {
                $summary['fully_received_lines']++;
            }
        }

        $summary['is_fully_received'] = $this->isPoFullyReceived($poId);

        return $summary;
    }
}
