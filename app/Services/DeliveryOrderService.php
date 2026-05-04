<?php

namespace App\Services;

use App\Models\SalesOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\ProductModel;
use App\Models\DeliveryOrderModel;
use App\Models\DeliveryOrderLineModel;
use Config\Database;

/**
 * Phase-C: Delivery Order Draft Creation Service (READ-ONLY)
 *
 * STRICT RULES:
 * - No inventory writes
 * - No stock confirmation
 * - Draft status only
 * - User can edit qty_to_ship before confirmation
 */
class DeliveryOrderService
{
    protected $db;
    protected $readyService;

    public function __construct()
    {
        $this->db = Database::connect();
        $this->readyService = new ReadyToShipService();
    }

    /**
     * Create delivery order draft from sales order.
     *
     * Only includes lines where ready_now > 0.
     * Sets qty_to_ship to ready_now by default.
     *
     * @param int $salesOrderId
     * @return int|null Delivery Order ID, or null if failed
     */
    public function createDraftFromSalesOrder(int $salesOrderId): ?int
    {
        $salesOrderId = (int)$salesOrderId;
        if ($salesOrderId <= 0) {
            return null;
        }

        $soModel = new SalesOrderModel();
        $salesOrder = $soModel->find($salesOrderId);
        if (!$salesOrder) {
            return null;
        }

        try {
            $this->db->transStart();

            $doNumber = $this->generateDeliveryOrderNumber();
                        log_message('info', "DeliveryOrderService: Generated DO number: $doNumber for SO $salesOrderId");
            
            log_message('info', 'DeliveryOrderService: Creating DO with number ' . $doNumber . ' for SO ' . $salesOrderId);

            $doModel = new DeliveryOrderModel();
            $doId = $doModel->insert([
                'sales_order_id' => $salesOrderId,
                'do_number' => $doNumber,
                'status' => 'draft',
            ]);

            if (!$doId) {
                                log_message('error', "DeliveryOrderService: DO insert failed for SO $salesOrderId. Errors: " . json_encode($doModel->errors()));
                $errors = $doModel->errors();
                log_message('error', 'DeliveryOrderService: Failed to insert DO. Errors: ' . json_encode($errors));
                $this->db->transRollback();
            
                            log_message('info', "DeliveryOrderService: Created DO ID $doId for SO $salesOrderId");
                return null;
            }
            
            log_message('info', 'DeliveryOrderService: Created DO ID ' . $doId);

            try {
                $readyData = $this->readyService->getLineReadiness($salesOrderId);
                log_message('info', 'DeliveryOrderService: getLineReadiness returned ' . count($readyData['lines'] ?? []) . ' lines');
            } catch (\Throwable $e) {
                log_message('error', 'DeliveryOrderService: Error calling ReadyToShipService.getLineReadiness: ' . $e->getMessage() . ' | ' . $e->getTraceAsString());
                $this->db->transRollback();
                throw $e; // re-throw so main catch block handles it
            }
            
            if (empty($readyData['lines'])) {
                log_message('error', 'DeliveryOrderService: No lines ready to ship for SO ' . $salesOrderId);
                $this->db->transRollback();
                return null;
            }

            $lineModel = new DeliveryOrderLineModel();
            $soLineModel = new SalesOrderLineModel();
            $linesInserted = 0;

            foreach ($readyData['lines'] as $rl) {
                $readyNow = (float)($rl['ready_now'] ?? 0);
                
                if ($readyNow <= 0) {
                                        log_message('info', "DeliveryOrderService: Skipping line " . ($rl['sales_order_line_id'] ?? 'unknown') . " - ready_now is $readyNow");
                    continue;
                }

                $soLineId = (int)($rl['sales_order_line_id'] ?? 0);
                $soLine = $soLineModel->find($soLineId);
                if (!$soLine) {
                                        log_message('error', "DeliveryOrderService: SO line $soLineId not found in database");
                    continue;
                }

                $insertData = [
                    'delivery_order_id' => $doId,
                    'sales_order_line_id' => $soLineId,
                    'product_id' => (int)($soLine['product_id'] ?? 0),
                    'quantity_ordered' => (float)($soLine['quantity'] ?? 0),
                    'ready_qty' => $readyNow,
                        'qty_to_ship' => $readyNow,
                    ];
                
                    // Include variant_id if present
                    if (!empty($soLine['product_variant_id'])) {
                        $insertData['variant_id'] = (int)$soLine['product_variant_id'];
                    }
                
                    $lineInsertResult = $lineModel->insert($insertData);
                
                if ($lineInsertResult) {
                    $linesInserted++;
                    log_message('info', "DeliveryOrderService: Inserted DO line for SO line $soLineId, ready_qty=$readyNow");
                } else {
                    log_message('error', "DeliveryOrderService: Failed to insert DO line for SO line $soLineId. Errors: " . json_encode($lineModel->errors()));
                }
            
                        log_message('info', "DeliveryOrderService: Inserted $linesInserted lines for DO $doId");
            
                        if ($linesInserted === 0) {
                            log_message('error', "DeliveryOrderService: No lines were inserted for DO $doId - rolling back");
                            $this->db->transRollback();
                            return null;
                        }
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                                log_message('error', "DeliveryOrderService: Transaction failed for DO $doId");
            
                            log_message('info', "DeliveryOrderService: Successfully created DO $doId with $linesInserted lines");
                return null;
            }

            return $doId;
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrderService.createDraftFromSalesOrder error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate qty_to_ship against ready_qty for a line.
     *
     * @param int $doLineId
     * @param float $qtyToShip
     * @return array {success: bool, message: string}
     */
    public function validateQtyToShip(int $doLineId, float $qtyToShip): array
    {
        $lineModel = new DeliveryOrderLineModel();
        $line = $lineModel->find($doLineId);
        if (!$line) {
            return ['success' => false, 'message' => 'Line not found'];
        }

        $qtyToShip = (float)$qtyToShip;
        $readyQty = (float)($line['ready_qty'] ?? 0);

        if ($qtyToShip < 0) {
            return ['success' => false, 'message' => 'Quantity to ship cannot be negative'];
        }

        if ($qtyToShip > $readyQty) {
            return ['success' => false, 'message' => "Quantity to ship ({$qtyToShip}) exceeds ready quantity ({$readyQty})"];
        }

        return ['success' => true, 'message' => 'Valid'];
    }

    /**
     * Update qty_to_ship for a delivery order line.
     *
     * @param int $doLineId
     * @param float $qtyToShip
     * @return bool
     */
    public function updateQtyToShip(int $doLineId, float $qtyToShip): bool
    {
        $validation = $this->validateQtyToShip($doLineId, $qtyToShip);
        if (!$validation['success']) {
            return false;
        }

        try {
            $lineModel = new DeliveryOrderLineModel();
            return $lineModel->update($doLineId, ['qty_to_ship' => (float)$qtyToShip]);
        } catch (\Throwable $e) {
            log_message('error', 'DeliveryOrderService.updateQtyToShip error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirm delivery order (stock out / create inventory movements).
     *
     * For each line:
     * - Create negative inventory_movements entries (qty_change = -qty_to_ship)
     * - Update stock_balances to reflect deduction
     *
     * After all lines processed:
     * - Set delivery_orders.status = 'confirmed'
     * - Returns array with success status and message
     *
     * @param int $doId
     * @return array {success: bool, message: string}
     */
    public function confirm(int $doId): array
    {
        $doId = (int)$doId;
        if ($doId <= 0) {
            return ['success' => false, 'message' => 'Invalid delivery order ID'];
        }

        $doModel = new DeliveryOrderModel();
        $do = $doModel->getWithLines($doId);
        if (!$do) {
            return ['success' => false, 'message' => 'Delivery order not found'];
        }

        if ($do['status'] === 'confirmed') {
            return ['success' => false, 'message' => 'Delivery order already confirmed'];
        }

        try {
            $this->db->transStart();

            if (empty($do['lines'])) {
                $this->db->transRollback();
                return ['success' => false, 'message' => 'No lines to confirm'];
            }

            // Get current user ID (null if not available)
            $createdBy = null;
            try {
                if (function_exists('auth') && auth() && method_exists(auth(), 'id')) {
                    $createdBy = auth()->id();
                }
            } catch (\Exception $e) {
                // Silently continue if auth fails
                $createdBy = null;
            }

            // PHASE 1: VALIDATE STOCK AVAILABILITY (BEFORE ANY DEDUCTION)
            foreach ($do['lines'] as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $variantId = !empty($line['variant_id']) ? (int)$line['variant_id'] : null;
                $qtyToShip = (float)($line['qty_to_ship'] ?? 0);

                if ($productId <= 0 || $qtyToShip <= 0) {
                    continue;
                }

                // Calculate total available stock for this product/variant
                $query = $this->db->table('stock_balances')
                    ->selectSum('quantity', 'total_qty')
                    ->where('product_id', $productId);
                
                if ($variantId) {
                    $query->where('variant_id', $variantId);
                } else {
                    $query->groupStart()
                        ->where('variant_id IS NULL')
                        ->orWhere('variant_id', 0)
                    ->groupEnd();
                }
                
                $result = $query->get()->getRowArray();
                $availableStock = (float)($result['total_qty'] ?? 0);

                // HARD FAIL if trying to ship more than available
                if ($qtyToShip > $availableStock) {
                    $this->db->transRollback();
                    
                    $productName = $this->getProductName($productId, $variantId);
                    $errorMsg = sprintf(
                        'Cannot ship %.2f units of %s (Product ID: %d%s). Only %.2f units available in stock.',
                        $qtyToShip,
                        $productName,
                        $productId,
                        $variantId ? ', Variant ID: ' . $variantId : '',
                        $availableStock
                    );
                    
                    log_message('error', 'DeliveryOrderService: Over-shipment prevented - ' . $errorMsg);
                    throw new \RuntimeException($errorMsg);
                }
            }

            // PHASE 2: DEDUCT STOCK (Only executes if all validations passed)
            foreach ($do['lines'] as $line) {
                $productId = (int)($line['product_id'] ?? 0);
                $variantId = !empty($line['variant_id']) ? (int)$line['variant_id'] : null;
                $qtyToShip = (float)($line['qty_to_ship'] ?? 0);
                $soLineId = (int)($line['sales_order_line_id'] ?? 0);

                if ($productId <= 0 || $qtyToShip <= 0) {
                    continue;
                }

                // Decrement stock_balances across available rows (all warehouses/locations)
                $remainingToShip = $qtyToShip;
                $balanceRows = $this->db->table('stock_balances')
                    ->where('product_id', $productId)
                    ->groupStart()
                        ->where($variantId ? 'variant_id' : 'variant_id IS NULL', $variantId, !$variantId)
                    ->groupEnd()
                    ->where('quantity >', 0) // Only get rows with positive quantity
                    ->orderBy('quantity', 'DESC')
                    ->get()
                    ->getResultArray();

                foreach ($balanceRows as $balance) {
                    if ($remainingToShip <= 0) {
                        break;
                    }

                    $availableQty = (float)($balance['quantity'] ?? 0);
                    if ($availableQty <= 0) {
                        continue;
                    }

                    $deductQty = min($availableQty, $remainingToShip);
                    $newQty = $availableQty - $deductQty;

                    $this->db->table('stock_balances')
                        ->where('id', (int)$balance['id'])
                        ->update([
                            'quantity' => $newQty,
                            'updated_at' => date('Y-m-d H:i:s'),
                        ]);

                    // Create stock movement for this warehouse/location
                    $movementData = [
                        'product_id' => $productId,
                        'warehouse_id' => (int)($balance['warehouse_id'] ?? 1),
                        'location_id' => (int)($balance['location_id'] ?? 1),
                        'qty_change' => -$deductQty,
                        'movement_type' => 'shipment',
                        'reference_type' => 'delivery_order',
                        'reference_id' => $doId,
                        'created_by' => $createdBy,
                        'created_at' => date('Y-m-d H:i:s'),
                    ];
                    if ($variantId) {
                        $movementData['variant_id'] = $variantId;
                        $movementData['item_key'] = 'v' . $variantId;
                    } else {
                        $movementData['item_key'] = 'p' . $productId;
                    }
                    $moved = $this->db->table('stock_movements')->insert($movementData);

                    if (!$moved) {
                        $this->db->transRollback();
                        return ['success' => false, 'message' => 'Failed to create inventory movement for product ID ' . $productId];
                    }

                    $remainingToShip -= $deductQty;
                }

                // Validation in PHASE 1 ensures $remainingToShip will always be 0 here
                // No negative balance fallback needed - this should never execute
                if ($remainingToShip > 0) {
                    $this->db->transRollback();
                    log_message('error', 'DeliveryOrderService: CRITICAL - Remaining qty after deduction despite validation. Product ID: ' . $productId . ', Remaining: ' . $remainingToShip);
                    throw new \RuntimeException('Stock deduction failed - integrity check failed for product ID ' . $productId);
                }

                // Sync variant_inventory for variant products
                if ($variantId && $this->db->tableExists('variant_inventory')) {
                    $warehouseIds = array_unique(array_column($balanceRows, 'warehouse_id'));
                    foreach ($warehouseIds as $whId) {
                        $whId = (int)$whId;
                        $sumRow = $this->db->table('stock_balances')
                            ->select('SUM(quantity) as total')
                            ->where('variant_id', $variantId)
                            ->where('warehouse_id', $whId)
                            ->get()->getRowArray();
                        $newTotal = (float)($sumRow['total'] ?? 0);

                        $existing = $this->db->table('variant_inventory')
                            ->where('variant_id', $variantId)
                            ->where('warehouse_id', $whId)
                            ->get()->getRowArray();

                        if ($existing) {
                            $this->db->table('variant_inventory')
                                ->where('variant_id', $variantId)
                                ->where('warehouse_id', $whId)
                                ->update(['quantity' => $newTotal, 'updated_at' => date('Y-m-d H:i:s')]);
                        }
                    }
                }
            }

            // 3. Update delivery order status to confirmed
            $doModel->update($doId, ['status' => 'confirmed', 'updated_at' => date('Y-m-d H:i:s')]);

            // 4. Update sales order status to 'shipped'
            $soId = (int)($do['sales_order_id'] ?? 0);
            if ($soId > 0 && $this->db->tableExists('sales_orders')) {
                $this->db->table('sales_orders')->where('id', $soId)->update([
                    'status' => 'shipped',
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                log_message('info', "DeliveryOrderService: Updated sales order $soId status to 'shipped'");
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                return ['success' => false, 'message' => 'Transaction failed'];
            }

            return ['success' => true, 'message' => 'Delivery confirmed - order shipped and inventory updated'];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'DeliveryOrderService.confirm error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Confirmation failed: ' . $e->getMessage()];
        }
    }

    /**
     * Generate next delivery order number.
     *
     * @return string
     */
    protected function generateDeliveryOrderNumber(): string
    {
        $db = $this->db;
        $table = 'delivery_orders';

        if (!$db->tableExists($table)) {
            return 'DO-001';
        }

        $row = $db->table($table)
            ->selectMax('id')
            ->get()
            ->getRowArray();

        $nextNum = ((int)($row['id'] ?? 0) + 1);
        return 'DO-' . str_pad($nextNum, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Get product name for error messages.
     *
     * @param int $productId
     * @param int|null $variantId
     * @return string
     */
    protected function getProductName(int $productId, ?int $variantId = null): string
    {
        try {
            if ($variantId && $this->db->tableExists('product_variants')) {
                $variant = $this->db->table('product_variants')
                    ->select('name, art_number')
                    ->where('id', $variantId)
                    ->get()
                    ->getRowArray();
                
                if ($variant) {
                    return ($variant['name'] ?? 'Unknown') . ' (' . ($variant['art_number'] ?? 'N/A') . ')';
                }
            }

            if ($this->db->tableExists('products')) {
                $product = $this->db->table('products')
                    ->select('name, sku, code')
                    ->where('id', $productId)
                    ->get()
                    ->getRowArray();
                
                if ($product) {
                    $name = $product['name'] ?? 'Unknown Product';
                    $code = $product['code'] ?? $product['sku'] ?? '';
                    return $code ? "$name ($code)" : $name;
                }
            }
        } catch (\Exception $e) {
            log_message('debug', 'DeliveryOrderService: Could not fetch product name: ' . $e->getMessage());
        }

        return "Product #$productId";
    }
}
