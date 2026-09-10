<?php

namespace App\Services;

use Config\Database;

/**
 * Phase-1: READ-ONLY Inventory Availability Service
 * 
 * Computes on_hand, reserved, and available quantities.
 * No side effects. No DB writes. No automation.
 */
class InventoryAvailabilityService
{
    /**
     * Sales order statuses that still represent open demand on stock.
     * Draft/cancelled orders reserve nothing; shipped/delivered orders net
     * themselves out through the delivered quantity anyway.
     */
    private const OPEN_SO_STATUSES = ['confirmed', 'processing', 'partially_shipped', 'in_progress'];

    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Check if product is storable (non-services, etc.)
     */
    private function isStorable(int $productId): bool
    {
        try {
            $cols = $this->db->getFieldNames('products');
            if (!in_array('detailed_type', $cols, true)) {
                return true; // assume storable if column doesn't exist
            }

            $prod = $this->db->table('products')
                ->select('detailed_type')
                ->where('id', $productId)
                ->get()
                ->getRowArray();

            if (!$prod) return false; // product not found

            $type = $prod['detailed_type'] ?? 'storable';
            return $type === 'storable';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get inventory availability for a product (with optional variant).
     * 
     * "reserved" is derived from open sales order demand that has not shipped
     * yet, so the same stock can never be promised to two orders at once.
     *
     * @param int $productId
     * @param int|null $variantId
     * @param string $productType Optional product type (simple, variable, etc.)
     * @param array $options exclude_sales_order_line_id: ignore that line's own
     *                       demand, so a line is never reserved against itself.
     * @return array|null
     *   {
     *     'on_hand': float,
     *     'reserved': float,
     *     'available': float
     *   }
     *   Returns NULL if product is not storable.
     */
    public function getAvailability(int $productId, ?int $variantId = null, string $productType = '', array $options = []): ?array
    {
        // Check if product is storable
        if (!$this->isStorable($productId)) {
            return null; // services, etc. have no stock
        }

        $onHand = 0.0;
        $reserved = 0.0;
        $demand = $this->openDemand(
            $productId,
            $variantId,
            $productType,
            isset($options['exclude_sales_order_line_id']) ? (int)$options['exclude_sales_order_line_id'] : null
        );

        // Priority 1: Variant inventory (if variant_id provided)
        if ($variantId !== null && $variantId > 0) {
            try {
                if ($this->db->tableExists('variant_inventory')) {
                    $row = $this->db->table('variant_inventory')
                        ->select('SUM(quantity) as quantity, SUM(reserved) as reserved')
                        ->where('variant_id', (int)$variantId)
                        ->get()
                        ->getRowArray();

                    if ($row && $row['quantity'] !== null) {
                        $onHand = (float)($row['quantity'] ?? 0);
                        $reserved = (float)($row['reserved'] ?? 0);

                        // Clamp reserved to 0 if negative
                        if ($reserved < 0) {
                            $reserved = 0;
                        }

                        return $this->buildAvailability($onHand, $reserved + $demand);
                    }
                }
            } catch (\Throwable $e) {
                // fall through to next source
            }
        }

        // Priority 2: Stock balances (aggregated by product_id and variant_id)
        try {
            if ($this->db->tableExists('stock_balances')) {
                $query = $this->db->table('stock_balances')
                    ->select('SUM(quantity) as total_qty')
                    ->where('product_id', (int)$productId);
                
                // If variant_id is provided, filter by it
                if ($variantId !== null && $variantId > 0) {
                    $query->where('variant_id', (int)$variantId);
                } elseif ($productType === 'variable') {
                    // For variable products without specific variant, sum ALL variants
                    // This handles cases where SO line didn't specify which variant
                    $query->where('variant_id IS NOT NULL', null, false);
                } else {
                    // For simple products (no variant), check non-variant stock only
                    $query->where('variant_id IS NULL', null, false);
                }
                
                $row = $query->get()->getRowArray();

                if ($row) {
                    $onHand = (float)($row['total_qty'] ?? 0);
                    // stock_balances has no reserved column: open sales demand is the reservation.
                    return $this->buildAvailability($onHand, $demand);
                }
            }
        } catch (\Throwable $e) {
            // fall through to next source
        }

        // Priority 3: Fallback to products.current_stock
        try {
            $cols = $this->db->getFieldNames('products');
            if (in_array('current_stock', $cols, true)) {
                $prod = $this->db->table('products')
                    ->select('current_stock')
                    ->where('id', (int)$productId)
                    ->get()
                    ->getRowArray();

                if ($prod) {
                    $onHand = (float)($prod['current_stock'] ?? 0);

                    return $this->buildAvailability($onHand, $demand);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // If all sources fail, return zeros (conservative: no stock)
        return $this->buildAvailability(0.0, 0.0);
    }

    /**
     * Quantity already promised to open sales orders and not yet delivered.
     *
     * This is the reservation: there is no reserved column to trust
     * (variant_inventory.reserved is never written), so it is derived from the
     * orders themselves and can therefore never drift out of sync.
     */
    private function openDemand(int $productId, ?int $variantId, string $productType, ?int $excludeLineId): float
    {
        if ($productId <= 0) {
            return 0.0;
        }

        try {
            if (!$this->db->tableExists('sales_order_lines') || !$this->db->tableExists('sales_orders')) {
                return 0.0;
            }

            // Match the same variant scope the on-hand lookup uses.
            if ($variantId !== null && $variantId > 0) {
                $variantClause = 'AND sol.product_variant_id = ' . (int)$variantId;
            } elseif ($productType === 'variable') {
                $variantClause = 'AND sol.product_variant_id IS NOT NULL AND sol.product_variant_id > 0';
            } else {
                $variantClause = 'AND (sol.product_variant_id IS NULL OR sol.product_variant_id = 0)';
            }

            $excludeClause = $excludeLineId > 0 ? 'AND sol.id <> ' . (int)$excludeLineId : '';
            $statuses = "'" . implode("','", self::OPEN_SO_STATUSES) . "'";

            $shipped = $this->db->tableExists('delivery_order_lines') && $this->db->tableExists('delivery_orders')
                ? "LEFT JOIN (
                        SELECT dol.sales_order_line_id AS line_id, SUM(dol.qty_to_ship) AS shipped
                        FROM delivery_order_lines dol
                        JOIN delivery_orders dor ON dor.id = dol.delivery_order_id
                        WHERE dor.status IN ('confirmed','shipped','delivered')
                        GROUP BY dol.sales_order_line_id
                   ) d ON d.line_id = sol.id"
                : '';
            $shippedExpr = $shipped !== '' ? 'COALESCE(d.shipped, 0)' : '0';

            $sql = "SELECT COALESCE(SUM(GREATEST(sol.quantity - {$shippedExpr}, 0)), 0) AS demand
                    FROM sales_order_lines sol
                    JOIN sales_orders so ON so.id = sol.sales_order_id AND so.deleted_at IS NULL
                    {$shipped}
                    WHERE sol.product_id = ?
                      AND sol.deleted_at IS NULL
                      AND so.status IN ({$statuses})
                      {$variantClause}
                      {$excludeClause}";

            $row = $this->db->query($sql, [$productId])->getRowArray();

            return max(0.0, (float)($row['demand'] ?? 0));
        } catch (\Throwable $e) {
            // Never block a read on this: fall back to "nothing reserved".
            return 0.0;
        }
    }

    /**
     * Helper: build availability array with clamped available qty.
     */
    private function buildAvailability(float $onHand, float $reserved): array
    {
        // Clamp reserved to >= 0
        $reserved = max(0, $reserved);

        // Available = on_hand - reserved, but never negative
        $available = max(0, $onHand - $reserved);

        return [
            'on_hand' => $onHand,
            'reserved' => $reserved,
            'available' => $available,
        ];
    }
}
