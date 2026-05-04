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
     * @param int $productId
     * @param int|null $variantId
     * @param string $productType Optional product type (simple, variable, etc.)
     * @return array|null
     *   {
     *     'on_hand': float,
     *     'reserved': float,
     *     'available': float
     *   }
     *   Returns NULL if product is not storable.
     */
    public function getAvailability(int $productId, ?int $variantId = null, string $productType = ''): ?array
    {
        // Check if product is storable
        if (!$this->isStorable($productId)) {
            return null; // services, etc. have no stock
        }

        $onHand = 0.0;
        $reserved = 0.0;

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

                        return $this->buildAvailability($onHand, $reserved);
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
                    // stock_balances doesn't have reserved; reserved = 0 for simple products
                    $reserved = 0.0;

                    return $this->buildAvailability($onHand, $reserved);
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
                    $reserved = 0.0;

                    return $this->buildAvailability($onHand, $reserved);
                }
            }
        } catch (\Throwable $e) {
            // fall through
        }

        // If all sources fail, return zeros (conservative: no stock)
        return $this->buildAvailability(0.0, 0.0);
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
