<?php

namespace App\Services;

use Config\Database;

/**
 * Phase-A: Ready-to-Ship Detection (READ-ONLY)
 *
 * STRICT RULES:
 * - No inventory writes
 * - No reservation
 * - No schema changes
 * - No automation
 * - Derived logic only
 */
class ReadyToShipService
{
    protected $db;
    protected $inventoryService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->inventoryService = new InventoryAvailabilityService();
    }

    /**
     * Get sales order IDs that have at least one line ready to ship now.
     *
     * @return array<int>
     */
    public function getReadySalesOrders(): array
    {
        $ready = [];

        try {
            if (!$this->db->tableExists('sales_orders')) {
                return [];
            }

            $orders = $this->db->table('sales_orders')->select('id')->get()->getResultArray();
            foreach ($orders as $row) {
                $orderId = (int)($row['id'] ?? 0);
                if ($orderId <= 0) {
                    continue;
                }
                $res = $this->getLineReadiness($orderId);
                if (!empty($res['readyToShip'])) {
                    $ready[] = $orderId;
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'ReadyToShipService.getReadySalesOrders error: ' . $e->getMessage());
        }

        return $ready;
    }

    /**
     * Get per-line readiness for a sales order.
     *
     * @param int $salesOrderId
     * @return array {
     *   readyToShip: bool,
     *   lines: array<array>
     * }
     */
    public function getLineReadiness(int $salesOrderId): array
    {
        $salesOrderId = (int)$salesOrderId;
        if ($salesOrderId <= 0) {
            return ['readyToShip' => false, 'lines' => []];
        }

        $readyToShip = false;
        $linesOut = [];

        try {
            if (!$this->db->tableExists('sales_order_lines')) {
                return ['readyToShip' => false, 'lines' => []];
            }

            $lines = $this->db->table('sales_order_lines sol')
                ->select('sol.id, sol.sales_order_id, sol.product_id, sol.product_variant_id, sol.quantity, p.product_type, p.detailed_type')
                ->join('products p', 'p.id = sol.product_id', 'left')
                ->where('sol.sales_order_id', $salesOrderId)
                ->get()
                ->getResultArray();

            foreach ($lines as $line) {
                $lineId = (int)($line['id'] ?? 0);
                $productId = (int)($line['product_id'] ?? 0);
                // Treat NULL and 0 as no specific variant selected
                $variantId = ((int)($line['product_variant_id'] ?? 0)) > 0 ? (int)$line['product_variant_id'] : null;
                $orderedQty = (float)($line['quantity'] ?? 0);
                $productType = $line['product_type'] ?? '';
                $detailedType = $line['detailed_type'] ?? 'storable';

                $onHand = 0.0;
                $reserved = 0.0;
                $available = 0.0;

                // Service products have no stock – skip them from delivery readiness
                if ($detailedType === 'service') {
                    continue;
                }

                if ($productId > 0) {
                    $availability = $this->inventoryService->getAvailability($productId, $variantId, $productType);
                    if (is_array($availability)) {
                        $onHand = (float)($availability['on_hand'] ?? 0);
                        $reserved = (float)($availability['reserved'] ?? 0);
                        $available = $onHand - $reserved;
                    }
                }

                $shippedQty = $this->getShippedQtyForLine($salesOrderId, $lineId);
                $remaining = max(0, $orderedQty - $shippedQty);
                $readyNow = min($available, $remaining);
                $readyNow = max(0, $readyNow);

                if ($readyNow > 0) {
                    $readyToShip = true;
                }

                $linesOut[] = [
                    'sales_order_line_id' => $lineId,
                    'product_id' => $productId,
                    'ordered_qty' => $orderedQty,
                    'on_hand' => $onHand,
                    'reserved' => $reserved,
                    'available' => $available,
                    'shipped_qty' => $shippedQty,
                    'remaining_qty' => $remaining,
                    'ready_now' => $readyNow,
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'ReadyToShipService.getLineReadiness error: ' . $e->getMessage());
            return ['readyToShip' => false, 'lines' => []];
        }

        return [
            'readyToShip' => $readyToShip,
            'lines' => $linesOut,
        ];
    }

    /**
     * Sum shipped qty for a sales order line from confirmed delivery orders.
     */
    protected function getShippedQtyForLine(int $salesOrderId, int $salesOrderLineId): float
    {
        try {
            if (!$this->db->tableExists('delivery_order_lines')) {
                return 0.0;
            }

            $lineCols = $this->safeGetFieldNames('delivery_order_lines');
            if (empty($lineCols)) {
                return 0.0;
            }

            $qtyCol = null;
            if (in_array('qty_to_ship', $lineCols, true)) {
                $qtyCol = 'qty_to_ship';
            } elseif (in_array('quantity', $lineCols, true)) {
                $qtyCol = 'quantity';
            }

            if ($qtyCol === null) {
                return 0.0;
            }

            $builder = $this->db->table('delivery_order_lines dol');

            if (in_array('sales_order_line_id', $lineCols, true)) {
                $builder->where('dol.sales_order_line_id', $salesOrderLineId);
            } elseif (in_array('sales_order_id', $lineCols, true)) {
                $builder->where('dol.sales_order_id', $salesOrderId);
            } else {
                return 0.0;
            }

            $builder = $this->applyConfirmedFilter($builder, $lineCols);

            $row = $builder->selectSum('dol.' . $qtyCol, 'qty_sum')
                ->get()
                ->getRowArray();

            return (float)($row['qty_sum'] ?? 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * Apply confirmation filter if available.
     */
    protected function applyConfirmedFilter($builder, array $lineCols)
    {
        if (in_array('confirmed', $lineCols, true)) {
            return $builder->where('dol.confirmed', 1);
        }
        if (in_array('is_confirmed', $lineCols, true)) {
            return $builder->where('dol.is_confirmed', 1);
        }
        if (in_array('status', $lineCols, true)) {
            return $builder->whereIn('dol.status', ['confirmed', 'delivered', 'shipped']);
        }

        if (!$this->db->tableExists('delivery_orders')) {
            return $builder;
        }

        $orderCols = $this->safeGetFieldNames('delivery_orders');
        if (empty($orderCols)) {
            return $builder;
        }

        $fk = null;
        $fkCandidates = ['delivery_order_id', 'do_id', 'delivery_id'];
        foreach ($fkCandidates as $candidate) {
            if (in_array($candidate, $lineCols, true)) {
                $fk = $candidate;
                break;
            }
        }

        if ($fk === null) {
            return $builder;
        }

        $builder->join('delivery_orders do', 'do.id = dol.' . $fk, 'left');

        if (in_array('confirmed', $orderCols, true)) {
            return $builder->where('do.confirmed', 1);
        }
        if (in_array('is_confirmed', $orderCols, true)) {
            return $builder->where('do.is_confirmed', 1);
        }
        if (in_array('status', $orderCols, true)) {
            return $builder->whereIn('do.status', ['confirmed', 'delivered', 'shipped']);
        }

        return $builder;
    }

    protected function safeGetFieldNames(string $table): array
    {
        try {
            return $this->db->getFieldNames($table);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
