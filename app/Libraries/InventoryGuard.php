<?php

namespace App\Libraries;

use App\Models\ProductModel;
use App\Models\ProductStockTransactionModel;

/**
 * InventoryGuard
 *
 * Phase-1 CONTROL LAYER for Inventory ownership.
 * - Does NOT change behavior or DB schema.
 * - Does NOT replace existing logic. It wraps existing ProductModel::updateStock calls.
 * - Logs caller information and provides light-weight inconsistency detection
 *   to warn if stock appears to be mutated outside of recorded transactions.
 *
 * Usage (example only, do NOT apply anywhere):
 *
 * // $guard = new \App\Libraries\InventoryGuard();
 * // $guard->increaseProductStock(123, 10.0, 'grn', 55, 12, 200.0, 'PurchaseModule');
 *
 * Notes:
 * - This file only adds a control layer. It intentionally avoids enforcing
 *   rules or changing any existing code paths. Enforcement and automatic
 *   detection/reporting will be added in later phases (TODO markers present).
 */
class InventoryGuard
{
    protected ProductModel $productModel;
    protected ProductStockTransactionModel $txModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
        $this->txModel = new ProductStockTransactionModel();
    }

    /**
     * Increase product stock (wrapper)
     *
     * @param int $productId
     * @param float $quantity
     * @param string $referenceType e.g. 'grn', 'production', 'adjustment'
     * @param int|null $referenceId
     * @param int|null $createdBy
     * @param float|null $unitCost
     * @param string $caller Module name (pass the caller module when possible)
     * @return bool result of underlying updateStock()
     */
    public function increaseProductStock(int $productId, float $quantity, string $referenceType, int $referenceId = null, int $createdBy = null, float $unitCost = null, string $caller = 'unknown'): bool
    {
        // Log intent and origin (non-blocking)
        log_message('info', sprintf('InventoryGuard: increaseProductStock called - product=%d qty=%s caller=%s ref=%s:%s', $productId, (string)$quantity, $caller, $referenceType, (string)$referenceId));

        // Call existing canonical method (do not duplicate logic)
        $result = $this->productModel->updateStock($productId, $quantity, 'in', $referenceType, $referenceId, $createdBy, $unitCost);

        // After call, do a light-weight consistency check and WARN if mismatch found
        if (!$this->isProductStockConsistent($productId)) {
            log_message('warning', sprintf('InventoryGuard: possible inconsistency detected after increase for product=%d caller=%s. See detectAllInconsistencies() for details.', $productId, $caller));
        }

        // TODO: In future phases we may record a deterministic audit trace
        // (e.g., write a small guard-driven audit table or signed marker) so
        // automated detectors can determine whether a change went through the
        // guard. For PHASE 1 we only log and run consistency checks.

        return $result;
    }

    /**
     * Decrease product stock (wrapper)
     */
    public function decreaseProductStock(int $productId, float $quantity, string $referenceType, int $referenceId = null, int $createdBy = null, float $unitCost = null, string $caller = 'unknown'): bool
    {
        log_message('info', sprintf('InventoryGuard: decreaseProductStock called - product=%d qty=%s caller=%s ref=%s:%s', $productId, (string)$quantity, $caller, $referenceType, (string)$referenceId));

        $result = $this->productModel->updateStock($productId, $quantity, 'out', $referenceType, $referenceId, $createdBy, $unitCost);

        if (!$this->isProductStockConsistent($productId)) {
            log_message('warning', sprintf('InventoryGuard: possible inconsistency detected after decrease for product=%d caller=%s. See detectAllInconsistencies() for details.', $productId, $caller));
        }

        return $result;
    }

    /**
     * Set (adjust) product stock to an absolute value (wrapper)
     */
    public function setProductStock(int $productId, float $absoluteQuantity, string $referenceType = 'adjustment', int $referenceId = null, int $createdBy = null, float $unitCost = null, string $caller = 'unknown'): bool
    {
        log_message('info', sprintf('InventoryGuard: setProductStock called - product=%d qty=%s caller=%s ref=%s:%s', $productId, (string)$absoluteQuantity, $caller, $referenceType, (string)$referenceId));

        $result = $this->productModel->updateStock($productId, $absoluteQuantity, 'adjustment', $referenceType, $referenceId, $createdBy, $unitCost);

        if (!$this->isProductStockConsistent($productId)) {
            log_message('warning', sprintf('InventoryGuard: possible inconsistency detected after set for product=%d caller=%s. See detectAllInconsistencies() for details.', $productId, $caller));
        }

        return $result;
    }

    /**
     * Check product stock consistency by replaying transactions.
     *
     * This attempts to recompute the product current stock by applying
     * product_stock_transactions in chronological order and comparing the
     * computed value with the `products.current_stock` column. If they differ
     * we WARN in logs. This helps detect direct SQL mutations that did not
     * create matching transactions.
     *
     * @param int $productId
     * @return bool true if consistent, false otherwise
     */
    public function isProductStockConsistent(int $productId): bool
    {
        $product = $this->productModel->find($productId);
        if (!$product) return true; // nothing to check

        $currentStock = isset($product['current_stock']) ? (float)$product['current_stock'] : 0.0;

        // Build computed stock by replaying transactions, respecting adjustments
        $txs = $this->txModel->where('product_id', $productId)->orderBy('id', 'ASC')->findAll();

        $computed = 0.0;
        foreach ($txs as $tx) {
            $type = $tx['transaction_type'] ?? '';
            $qty = isset($tx['quantity']) ? (float)$tx['quantity'] : 0.0;
            if ($type === 'adjustment') {
                // adjustments are stored as the absolute stock value
                $computed = $qty;
            } elseif ($type === 'in') {
                $computed += $qty;
            } elseif ($type === 'out') {
                $computed -= $qty;
            } else {
                // unknown types treated conservatively as added quantity
                $computed += $qty;
            }
        }

        // Normalize small float diffs
        if (abs($computed - $currentStock) > 0.0001) {
            log_message('warning', sprintf('InventoryGuard: stock inconsistency for product=%d computed=%s current_stock=%s', $productId, (string)$computed, (string)$currentStock));
            return false;
        }

        return true;
    }

    /**
     * Scan multiple products and log any inconsistencies found.
     * Useful to run as part of diagnostics (non-blocking).
     *
     * @param int|null $limit optional limit of products to scan (null = all)
     * @return array list of product ids that were inconsistent
     */
    public function detectAllInconsistencies(int $limit = null): array
    {
        $inconsistent = [];
        $builder = $this->productModel->builder();
        if ($limit) {
            $products = $this->productModel->findAll($limit);
        } else {
            $products = $this->productModel->findAll();
        }

        foreach ($products as $p) {
            $pid = (int)$p['id'];
            if (!$this->isProductStockConsistent($pid)) {
                $inconsistent[] = $pid;
            }
        }

        if (!empty($inconsistent)) {
            log_message('warning', sprintf('InventoryGuard: detectAllInconsistencies found %d products with mismatched stock', count($inconsistent)));
        } else {
            log_message('info', 'InventoryGuard: detectAllInconsistencies found no mismatches');
        }

        return $inconsistent;
    }

    /**
     * TODO: Future enforcement helpers
     * - Record a small guard-specific audit marker when the guard is used so
     *   that later scans can deterministically know whether a transaction
     *   was created via the guard. This would require a small DB table or
     *   a signed log; deferred to future phase (PHASE 2 DESIGN).
     *
     * - Provide an injectable enforcement toggle that can be enabled by ops to
     *   reject direct DB mutations or to require callers to use InventoryGuard.
     */
}
