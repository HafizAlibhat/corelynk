<?php

namespace App\Services;

use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderLineModel;
use Config\Database;

/**
 * Purchase Order Status Derivation Service
 * 
 * AUTO-CALCULATES PO status based on actual receipt quantities:
 * - confirmed: No items received yet (qty_received = 0 for all lines)
 * - partial: Some items received but not all (0 < total_received < total_ordered)
 * - completed: All items fully received (qty_received >= qty for all lines)
 * - closed: Manually force-closed (no further receipts allowed)
 * - cancelled: PO cancelled (never active)
 * 
 * STRICT RULES:
 * - Never manually set 'completed' - must be derived from receipts
 * - Status updates automatically after each GRN save
 * - 'closed' can be set manually to prevent further receiving
 */
class PurchaseOrderStatusService
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Derive and update PO status based on line-level receipt data
     * 
     * @param int $poId Purchase Order ID
     * @return string The new status
     */
    public function deriveAndUpdateStatus(int $poId): string
    {
        $poModel = new PurchaseOrderModel();
        $po = $poModel->find($poId);
        
        if (!$po) {
            throw new \RuntimeException("PO {$poId} not found");
        }

        // Don't change status if already cancelled or manually closed
        if (in_array($po['status'], ['cancelled', 'closed'])) {
            return $po['status'];
        }

        $derivedStatus = $this->calculateStatus($poId);
        
        // Only update if status changed
        if ($derivedStatus !== $po['status']) {
            $poModel->update($poId, ['status' => $derivedStatus]);
            log_message('info', "PO {$poId} status changed: {$po['status']} → {$derivedStatus}");
        }

        return $derivedStatus;
    }

    /**
     * Calculate status based on line quantities (read-only, no DB write)
     * 
     * @param int $poId
     * @return string 'confirmed' | 'partial' | 'completed'
     */
    public function calculateStatus(int $poId): string
    {
        $lineModel = new PurchaseOrderLineModel();
        $lines = $lineModel->where('po_id', $poId)->findAll();

        if (empty($lines)) {
            // No lines = still in draft/confirmed state
            return 'confirmed';
        }

        $totalOrdered = 0;
        $totalReceived = 0;
        $hasAnyReceived = false;
        $allFullyReceived = true;

        foreach ($lines as $line) {
            $qty = (float)($line['qty'] ?? 0);
            $qtyReceived = (float)($line['qty_received'] ?? 0);

            $totalOrdered += $qty;
            $totalReceived += $qtyReceived;

            if ($qtyReceived > 0) {
                $hasAnyReceived = true;
            }

            if ($qtyReceived < $qty) {
                $allFullyReceived = false;
            }
        }

        // Decision logic
        if ($allFullyReceived && $totalReceived >= $totalOrdered) {
            return 'completed';
        } elseif ($hasAnyReceived) {
            return 'partial';
        } else {
            return 'confirmed';
        }
    }

    /**
     * Get receipt summary for PO (for UI display)
     * 
     * @param int $poId
     * @return array ['total_ordered', 'total_received', 'total_pending', 'status']
     */
    public function getReceiptSummary(int $poId): array
    {
        $lineModel = new PurchaseOrderLineModel();
        $lines = $lineModel->where('po_id', $poId)->findAll();

        $totalOrdered = 0;
        $totalReceived = 0;

        foreach ($lines as $line) {
            $totalOrdered += (float)($line['qty'] ?? 0);
            $totalReceived += (float)($line['qty_received'] ?? 0);
        }

        $totalPending = max(0, $totalOrdered - $totalReceived);

        return [
            'total_ordered' => $totalOrdered,
            'total_received' => $totalReceived,
            'total_pending' => $totalPending,
            'status' => $this->calculateStatus($poId),
            'completion_percentage' => $totalOrdered > 0 ? round(($totalReceived / $totalOrdered) * 100, 2) : 0,
        ];
    }

    /**
     * Force-close PO (prevent further receipts)
     * 
     * @param int $poId
     * @return bool
     */
    public function forceClose(int $poId): bool
    {
        $poModel = new PurchaseOrderModel();
        $result = $poModel->update($poId, ['status' => 'closed']);
        
        if ($result) {
            log_message('info', "PO {$poId} manually closed");
        }
        
        return $result;
    }
}
