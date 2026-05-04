<?php

namespace App\Services;

/**
 * Phase-3: Sales Order Fulfillment Tracking
 * 
 * STRICT RULES:
 * - READ-ONLY service (NO database writes)
 * - NO inventory updates
 * - NO stock reservation
 * - NO automation
 * - Fulfillment status DERIVED from existing data only
 * 
 * Derives per-line and per-order fulfillment readiness using:
 * Sales Order → PO mappings (sales_order_line_po_map) → GRN receipts
 */
class FulfillmentStatusService
{
    protected $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    /**
     * Get complete fulfillment status for a sales order
     * 
     * @param int $salesOrderId
     * @return array {
     *   orderStatus: 'NOT_READY' | 'PARTIAL_READY' | 'READY',
     *   lines: [
     *     {
     *       sales_order_line_id,
     *       product_id,
     *       product_name,
     *       is_stockable,
     *       ordered_qty,
     *       incoming_qty,
     *       received_qty,
     *       pending_qty,
     *       ready_to_ship_qty,
     *       po_details: [{ po_id, po_number, po_line_id, po_qty }]
     *     }
     *   ]
     * }
     */
    public function getSalesOrderFulfillment($salesOrderId)
    {
        $salesOrderId = (int) $salesOrderId;
        if (!$salesOrderId) {
            return $this->getEmptyFulfillment();
        }

        try {
            // Get all SO lines with variant support
            $soLines = $this->db->table('sales_order_lines sol')
                ->select('sol.id as line_id, sol.product_id, sol.product_variant_id, sol.quantity, p.name as product_name, p.product_type')
                ->join('products p', 'p.id = sol.product_id', 'left')
                ->where('sol.sales_order_id', $salesOrderId)
                ->get()
                ->getResultArray();

            if (empty($soLines)) {
                return $this->getEmptyFulfillment();
            }

            $fulfillmentLines = [];
            $hasStockableItems = false;
            $allReadyCount = 0;
            $noneReadyCount = 0;

            foreach ($soLines as $line) {
                $soLineId = $line['line_id'];
                $productId = $line['product_id'];
                $orderedQty = (float) $line['quantity'];
                $productName = $line['product_name'] ?? 'Unknown';
                
                // Determine if product is stockable (non-services)
                $isStockable = !in_array($line['product_type'], ['service', 'virtual']);
                if ($isStockable) {
                    $hasStockableItems = true;
                }

                // Get all mapped POs for this SO line
                try {
                    $poMappings = $this->db->table('sales_order_line_po_map pomap')
                        ->select('pomap.purchase_order_line_id, pomap.purchase_order_id, pol.quantity as po_qty')
                        ->join('purchase_order_lines pol', 'pol.id = pomap.purchase_order_line_id', 'left')
                        ->where('pomap.sales_order_line_id', $soLineId)
                        ->get()
                        ->getResultArray();
                } catch (\Exception $e) {
                    log_message('error', 'Failed to load PO mappings for SO line ' . $soLineId . ': ' . $e->getMessage());
                    $poMappings = [];
                }

                $incomingQty = 0;
                $poDetails = [];

                if (!empty($poMappings)) {
                    // Calculate incoming quantity and collect PO details
                    foreach ($poMappings as $mapping) {
                        $poQty = (float) ($mapping['po_qty'] ?? 0);
                        $incomingQty += $poQty;
                        $poId = (int)($mapping['purchase_order_id'] ?? 0);
                        
                        // Get PO details separately to avoid join issues
                        $poInfo = $this->db->table('purchase_orders')
                            ->select('id, order_number')
                            ->where('id', $poId)
                            ->get()
                            ->getRowArray();

                        $poDetails[] = [
                            'po_id' => $poId,
                            'po_number' => $poInfo['order_number'] ?? 'N/A',
                            'po_line_id' => $mapping['purchase_order_line_id'],
                            'po_qty' => $poQty,
                        ];
                    }

                    // Get received quantity from GRN for mapped PO lines
                    $receivedQty = $this->getReceivedQuantity($poMappings);
                } else {
                    // No POs mapped - check actual stock balance as fallback
                    // If line has product_variant_id, check variant-specific stock
                    $variantId = (int)($line['product_variant_id'] ?? 0);
                    $productType = $line['product_type'] ?? '';
                    
                    // If variant_id is NULL but product is variable type, check ANY variant stock
                    $receivedQty = $this->getAvailableStock($productId, $variantId > 0 ? $variantId : null, $productType);
                }

                // Calculate derived quantities
                $pendingQty = max(0, $orderedQty - $receivedQty);
                $readyToShipQty = min($receivedQty, $orderedQty);

                $fulfillmentLines[] = [
                    'sales_order_line_id' => $soLineId,
                    'product_id' => $productId,
                    'product_name' => $productName,
                    'is_stockable' => $isStockable,
                    'ordered_qty' => $orderedQty,
                    'incoming_qty' => $incomingQty,
                    'received_qty' => $receivedQty,
                    'pending_qty' => $pendingQty,
                    'ready_to_ship_qty' => $readyToShipQty,
                    'po_details' => $poDetails,
                ];

                // Track readiness
                if ($isStockable) {
                    if ($receivedQty >= $orderedQty) {
                        $allReadyCount++;
                    } elseif ($receivedQty === 0) {
                        $noneReadyCount++;
                    }
                }
            }

            // Determine order status
            $orderStatus = $this->deriveOrderStatus($fulfillmentLines, $hasStockableItems);

            return [
                'orderStatus' => $orderStatus,
                'lines' => $fulfillmentLines,
            ];

        } catch (\Exception $e) {
            $errorMsg = 'FulfillmentStatusService error: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ' | Line: ' . $e->getLine();
            log_message('error', $errorMsg);
            error_log($errorMsg); // Also log to PHP error log
            return $this->getEmptyFulfillment();
        }
    }

    /**
     * Get received quantity from GRN for mapped PO lines
     * 
     * @param array $poMappings Array of PO line mappings
     * @return float Total received quantity
     */
    protected function getReceivedQuantity($poMappings)
    {
        if (empty($poMappings)) {
            return 0;
        }

        $poLineIds = array_column($poMappings, 'purchase_order_line_id');
        
        try {
            $result = $this->db->table('purchase_grn_lines')
                ->selectSum('qty_received')
                ->whereIn('po_line_id', $poLineIds)
                ->get()
                ->getRowArray();

            return (float) ($result['qty_received'] ?? 0);
        } catch (\Exception $e) {
            log_message('error', 'Failed to get received quantity: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Derive order-level fulfillment status from line-level data
     * 
     * Order Status Rules:
     * - NOT_READY: No received_qty for any stockable lines
     * - PARTIAL_READY: Some received_qty > 0 but not all stockable lines complete
     * - READY: All stockable lines have received_qty >= ordered_qty
     * 
     * @param array $fulfillmentLines
     * @param bool $hasStockableItems
     * @return string
     */
    protected function deriveOrderStatus($fulfillmentLines, $hasStockableItems)
    {
        if (!$hasStockableItems) {
            // All items are non-stockable (services, virtual)
            return 'READY';
        }

        $totalReceived = 0;
        $totalOrdered = 0;
        $readyLineCount = 0;
        $stockableLineCount = 0;

        foreach ($fulfillmentLines as $line) {
            if (!$line['is_stockable']) {
                continue;
            }

            $stockableLineCount++;
            $orderedQty = $line['ordered_qty'];
            $receivedQty = $line['received_qty'];

            $totalOrdered += $orderedQty;
            $totalReceived += $receivedQty;

            if ($receivedQty >= $orderedQty) {
                $readyLineCount++;
            }
        }

        if ($readyLineCount === $stockableLineCount) {
            return 'READY';
        } elseif ($totalReceived === 0) {
            return 'NOT_READY';
        } else {
            return 'PARTIAL_READY';
        }
    }

    /**
     * Get empty fulfillment structure
     * 
     * @return array
     */
    protected function getEmptyFulfillment()
    {
        return [
            'orderStatus' => 'NOT_READY',
            'lines' => [],
        ];
    }

    /**
     * Get available stock for a product from stock_balances table
     * Supports both simple products and variants
     * 
     * @param int $productId
     * @param int|null $variantId Optional variant ID for variant products
     * @param string $productType Product type to handle variable products correctly
     * @return float Available stock quantity
     */
    protected function getAvailableStock($productId, $variantId = null, $productType = '')
    {
        if (!$productId) {
            return 0;
        }

        try {
            // Check if stock_balances table exists and has data
            if ($this->db->tableExists('stock_balances')) {
                $query = $this->db->table('stock_balances')
                    ->selectSum('quantity')
                    ->where('product_id', $productId);
                
                // If variant_id is provided, check only that variant's stock
                if (!empty($variantId)) {
                    $query->where('variant_id', (int)$variantId);
                } elseif ($productType === 'variable') {
                    // For variable products without specific variant_id, sum ALL variants
                    // This handles cases where SO line didn't specify which variant
                    $query->where('variant_id IS NOT NULL', null, false);
                } else {
                    // For simple products, check non-variant stock only
                    $query->where('variant_id IS NULL', null, false);
                }
                
                $result = $query->get()->getRowArray();
                $stockQty = (float) ($result['quantity'] ?? 0);
                
                return $stockQty;
            }
            
            // Fallback: check products table for current_stock if exists
            if ($this->db->fieldExists('current_stock', 'products')) {
                $result = $this->db->table('products')
                    ->select('current_stock')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();
                
                return (float) ($result['current_stock'] ?? 0);
            }
            
            return 0;
        } catch (\Exception $e) {
            log_message('error', 'Failed to get available stock: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get fulfillment status badge class for UI
     * 
     * @param string $status
     * @return string Bootstrap badge class
     */
    public static function getStatusBadgeClass($status)
    {
        return match ($status) {
            'READY' => 'bg-success',
            'PARTIAL_READY' => 'bg-warning text-dark',
            'NOT_READY' => 'bg-danger',
            default => 'bg-secondary',
        };
    }

    /**
     * Get fulfillment status badge label
     * 
     * @param string $status
     * @return string
     */
    public static function getStatusLabel($status)
    {
        return match ($status) {
            'READY' => 'Ready to Ship',
            'PARTIAL_READY' => 'Partially Ready',
            'NOT_READY' => 'Not Ready',
            default => 'Unknown',
        };
    }
}
