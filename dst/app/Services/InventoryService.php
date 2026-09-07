<?php

namespace App\Services;

use Config\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Centralized inventory mutation layer.
 * All stock quantity changes must happen here (Phase 1: GRN receive only).
 */
class InventoryService
{
    private function productsHasColumn(string $column): bool
    {
        try {
            $db = Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `products` LIKE '" . $db->escapeString($column) . "'");
            return $result && $result->getNumRows() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function tableHasColumn(string $table, string $column): bool
    {
        try {
            $db = Database::connect();
            $cols = $db->getFieldNames($table);
            return in_array($column, $cols, true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Resolve a stock item to (template product_id, optional variant_id, item_key).
     * - For simple products: item_key = 'p{product_id}'
     * - For variants: item_key = 'v{variant_id}', product_id = template id
     */
    private function resolveStockItem(int $productId, ?int $variantId): array
    {
        $db = Database::connect();

        $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
        if (!$prod) {
            throw new InvalidArgumentException('Product not found.');
        }

        // Enforce storable-only inventory (matches your "only storable in inventory" rule)
        if ($this->productsHasColumn('detailed_type')) {
            $dtype = $prod['detailed_type'] ?? 'storable';
            if ($dtype !== 'storable') {
                throw new InvalidArgumentException('Only Storable products can be received into Inventory.');
            }
        }

        $isVariable = ($prod['product_type'] ?? 'simple') === 'variable';

        if ($variantId !== null && $variantId > 0) {
            $v = $db->table('product_variants')->where('id', (int)$variantId)->get()->getRowArray();
            if (!$v) {
                throw new InvalidArgumentException('Variant not found.');
            }
            if ((int)($v['product_id'] ?? 0) !== $productId) {
                throw new InvalidArgumentException('Variant does not belong to the selected product.');
            }
            return [
                'product_id' => $productId,
                'variant_id' => (int)$variantId,
                'item_key' => 'v' . (int)$variantId,
            ];
        }

        if ($isVariable) {
            // If there is exactly one variant, we can infer it. Otherwise require explicit variant selection.
            $cnt = 0;
            try {
                $cnt = (int)$db->table('product_variants')->where('product_id', $productId)->countAllResults();
            } catch (\Throwable $e) {
                $cnt = 0;
            }

            if ($cnt === 1) {
                $one = $db->table('product_variants')->select('id')->where('product_id', $productId)->get(1)->getRowArray();
                $vid = (int)($one['id'] ?? 0);
                if ($vid > 0) {
                    return [
                        'product_id' => $productId,
                        'variant_id' => $vid,
                        'item_key' => 'v' . $vid,
                    ];
                }
            }

            throw new InvalidArgumentException('This product has variants. Please select a specific variant for stock receipt.');
        }

        return [
            'product_id' => $productId,
            'variant_id' => null,
            'item_key' => 'p' . $productId,
        ];
    }

    private function syncTemplateCurrentStock(int $productId): void
    {
        $db = Database::connect();
        if (!$this->tableHasColumn('products', 'current_stock')) return;

        try {
            $row = $db->table('stock_balances')->select('SUM(quantity) as qty')->where('product_id', $productId)->get()->getRowArray();
            $qty = isset($row['qty']) ? (float)$row['qty'] : 0.0;
        } catch (\Throwable $e) {
            return;
        }

        try {
            $db->table('products')->where('id', $productId)->update([
                'current_stock' => $qty,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Receive stock from a GRN into a specific location.
     *
     * Steps:
     * 1) Insert stock_movements (+quantity)
     * 2) Upsert stock_balances (product_id + location_id)
     * 3) Validate quantity > 0 and location present
     */
    public function receiveFromGrn(int $product_id, int $warehouse_id, int $location_id, float $quantity, int $grn_id, int $user_id, ?int $variant_id = null): bool
    {
        if ($warehouse_id <= 0) throw new InvalidArgumentException('Warehouse is required.');
        if ($location_id <= 0) throw new InvalidArgumentException('Location is required.');
        if ($product_id <= 0) {
            throw new InvalidArgumentException('Product is required.');
        }
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than zero.');
        }

        $db = Database::connect();

        // Resolve to correct stock item (product vs variant)
        $item = $this->resolveStockItem($product_id, $variant_id);

        // Validate that the location exists and belongs to the warehouse
        $loc = $db->table('warehouse_locations')->select('id, warehouse_id')->where('id', $location_id)->get()->getRowArray();
        if (!$loc) {
            throw new InvalidArgumentException('Location not found.');
        }
        if ((int)$loc['warehouse_id'] !== $warehouse_id) {
            throw new InvalidArgumentException('Location must belong to the selected warehouse.');
        }

        $db->transException(true)->transStart();

        $now = date('Y-m-d H:i:s');

        // 1) Insert stock_movements (+quantity) with column-safety
        $movementData = [
            'product_id'     => $item['product_id'],
            'variant_id'     => $item['variant_id'],
            'item_key'       => $item['item_key'],
            'warehouse_id'   => $warehouse_id,
            'location_id'    => $location_id,
            'qty_change'     => $quantity, // positive for receipt
            'movement_type'  => 'grn',
            'reference_type' => 'grn',
            'reference_id'   => $grn_id,
            'created_by'     => $user_id,
            'created_at'     => $now,
        ];
        $movementColumns = array_flip($db->getFieldNames('stock_movements'));
        if (!isset($movementColumns['warehouse_id']) || !isset($movementColumns['location_id'])) {
            $db->transRollback();
            throw new RuntimeException('stock_movements is missing warehouse_id or location_id column.');
        }
        $movementData = array_intersect_key($movementData, $movementColumns);
        $db->table('stock_movements')->insert($movementData);
        $err = $db->error();
        if (!empty($err['message'])) {
            log_message('error', 'InventoryService movement insert failed: '.$err['message']);
            $db->transRollback();
            throw new RuntimeException('Failed to receive stock from GRN (movement insert): '.$err['message']);
        }

        // 2) Upsert stock_balances
        $balances = $db->table('stock_balances');
        $balanceColumns = array_flip($db->getFieldNames('stock_balances'));
        if (!isset($balanceColumns['warehouse_id']) || !isset($balanceColumns['location_id'])) {
            $db->transRollback();
            throw new RuntimeException('stock_balances is missing warehouse_id or location_id column.');
        }

        $existingQuery = $balances
            ->where('warehouse_id', $warehouse_id)
            ->where('location_id', $location_id);

        if (isset($balanceColumns['item_key'])) {
            $existingQuery->where('item_key', $item['item_key']);
        } else {
            // fallback legacy uniqueness (including variant_id if present)
            $existingQuery->where('product_id', $item['product_id']);
            if (isset($balanceColumns['variant_id']) && $item['variant_id']) {
                $existingQuery->where('variant_id', $item['variant_id']);
            }
        }

        $existing = $existingQuery->get()->getRow();

        if ($existing) {
            $balances
                ->where('id', $existing->id)
                ->set('quantity', "quantity + {$db->escapeString((string)$quantity)}", false)
                ->set('updated_at', $now)
                ->update();
        } else {
            $balanceData = [
                'product_id'  => $item['product_id'],
                'variant_id'  => $item['variant_id'],
                'item_key'    => $item['item_key'],
                'warehouse_id'=> $warehouse_id,
                'location_id' => $location_id,
                'quantity'    => $quantity,
                'created_at'  => $now,
                'updated_at'  => $now,
            ];
            $balanceData = array_intersect_key($balanceData, $balanceColumns);
            $balances->insert($balanceData);
        }

        $err = $db->error();
        if (!empty($err['message'])) {
            log_message('error', 'InventoryService balance upsert failed: '.$err['message']);
            $db->transRollback();
            throw new RuntimeException('Failed to receive stock from GRN (balance upsert): '.$err['message']);
        }

        try {
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException('Failed to receive stock from GRN (commit): '.$e->getMessage(), 0, $e);
        }

        if ($db->transStatus() === false) {
            $err = $db->error();
            $msg = !empty($err['message']) ? $err['message'] : 'Unknown DB error';
            log_message('error', 'InventoryService transStatus false: '.$msg);
            throw new RuntimeException('Failed to receive stock from GRN: '.$msg);
        }

        // Keep template product current_stock in sync (legacy dashboards)
        $this->syncTemplateCurrentStock((int)$item['product_id']);

        // Sync variant_inventory for variant products
        if ($item['variant_id'] && $db->tableExists('variant_inventory')) {
            $sumRow = $db->table('stock_balances')
                ->select('SUM(quantity) as total')
                ->where('variant_id', $item['variant_id'])
                ->where('warehouse_id', $warehouse_id)
                ->get()->getRowArray();
            $newTotal = (float)($sumRow['total'] ?? 0);

            $viExists = $db->table('variant_inventory')
                ->where('variant_id', $item['variant_id'])
                ->where('warehouse_id', $warehouse_id)
                ->get()->getRowArray();

            $viCols = array_flip($db->getFieldNames('variant_inventory'));
            if ($viExists) {
                $db->table('variant_inventory')
                    ->where('variant_id', $item['variant_id'])
                    ->where('warehouse_id', $warehouse_id)
                    ->update(['quantity' => $newTotal, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                $insertData = [
                    'variant_id' => $item['variant_id'],
                    'warehouse_id' => $warehouse_id,
                    'quantity' => $newTotal,
                    'reserved' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
                $db->table('variant_inventory')->insert(array_intersect_key($insertData, $viCols));
            }
        }

        return true;
    }

    /**
     * Issue stock out from a specific location (scrap / return to vendor / repair out).
     */
    public function issueFromStock(
        int $product_id,
        int $warehouse_id,
        int $location_id,
        float $quantity,
        int $reference_id,
        int $user_id,
        string $movement_type = 'scrap',
        string $reference_type = 'grn_issue',
        ?int $variant_id = null
    ): bool {
        if ($warehouse_id <= 0) throw new InvalidArgumentException('Warehouse is required.');
        if ($location_id <= 0) throw new InvalidArgumentException('Location is required.');
        if ($product_id <= 0) throw new InvalidArgumentException('Product is required.');
        if ($quantity <= 0) throw new InvalidArgumentException('Quantity must be greater than zero.');

        $db = Database::connect();
        $item = $this->resolveStockItem($product_id, $variant_id);

        $loc = $db->table('warehouse_locations')->select('id, warehouse_id')->where('id', $location_id)->get()->getRowArray();
        if (!$loc) {
            throw new InvalidArgumentException('Location not found.');
        }
        if ((int)$loc['warehouse_id'] !== $warehouse_id) {
            throw new InvalidArgumentException('Location must belong to the selected warehouse.');
        }

        $db->transException(true)->transStart();
        $now = date('Y-m-d H:i:s');

        $balanceColumns = array_flip($db->getFieldNames('stock_balances'));
        if (!isset($balanceColumns['warehouse_id']) || !isset($balanceColumns['location_id'])) {
            $db->transRollback();
            throw new RuntimeException('stock_balances is missing warehouse_id or location_id column.');
        }

        $existingQuery = $db->table('stock_balances')
            ->where('warehouse_id', $warehouse_id)
            ->where('location_id', $location_id);
        if (isset($balanceColumns['item_key'])) {
            $existingQuery->where('item_key', $item['item_key']);
        } else {
            $existingQuery->where('product_id', $item['product_id']);
            if (isset($balanceColumns['variant_id']) && $item['variant_id']) {
                $existingQuery->where('variant_id', $item['variant_id']);
            }
        }
        $existing = $existingQuery->get()->getRowArray();
        $currentQty = (float)($existing['quantity'] ?? 0);
        if ($currentQty + 0.00001 < $quantity) {
            $db->transRollback();
            throw new RuntimeException('Insufficient stock in selected location for this action.');
        }

        $movementData = [
            'product_id'     => $item['product_id'],
            'variant_id'     => $item['variant_id'],
            'item_key'       => $item['item_key'],
            'warehouse_id'   => $warehouse_id,
            'location_id'    => $location_id,
            'qty_change'     => -1 * $quantity,
            'movement_type'  => $movement_type,
            'reference_type' => $reference_type,
            'reference_id'   => $reference_id,
            'created_by'     => $user_id,
            'created_at'     => $now,
        ];
        $movementColumns = array_flip($db->getFieldNames('stock_movements'));
        if (!isset($movementColumns['warehouse_id']) || !isset($movementColumns['location_id'])) {
            $db->transRollback();
            throw new RuntimeException('stock_movements is missing warehouse_id or location_id column.');
        }
        $movementData = array_intersect_key($movementData, $movementColumns);
        $db->table('stock_movements')->insert($movementData);

        $db->table('stock_balances')
            ->where('id', (int)$existing['id'])
            ->set('quantity', "quantity - {$db->escapeString((string)$quantity)}", false)
            ->set('updated_at', $now)
            ->update();

        try {
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException('Failed to issue stock (commit): ' . $e->getMessage(), 0, $e);
        }

        if ($db->transStatus() === false) {
            $err = $db->error();
            $msg = !empty($err['message']) ? $err['message'] : 'Unknown DB error';
            throw new RuntimeException('Failed to issue stock: ' . $msg);
        }

        $this->syncTemplateCurrentStock((int)$item['product_id']);
        return true;
    }

    /**
     * Move stock from one location to another (internal transfer).
     *
     * Creates two stock_movements (transfer_out + transfer_in), updates
     * stock_balances for both locations, and records the transfer in
     * the internal_transfers table for full history and audit trail.
     *
     * @return int  ID of the newly created internal_transfer record
     */
    public function internalTransfer(
        int $product_id,
        int $from_warehouse_id,
        int $from_location_id,
        int $to_warehouse_id,
        int $to_location_id,
        float $quantity,
        string $reason,
        int $user_id,
        ?int $variant_id = null,
        string $notes = ''
    ): int {
        if ($from_warehouse_id <= 0) throw new InvalidArgumentException('Source warehouse is required.');
        if ($from_location_id  <= 0) throw new InvalidArgumentException('Source location is required.');
        if ($to_warehouse_id   <= 0) throw new InvalidArgumentException('Destination warehouse is required.');
        if ($to_location_id    <= 0) throw new InvalidArgumentException('Destination location is required.');
        if ($product_id        <= 0) throw new InvalidArgumentException('Product is required.');
        if ($quantity          <= 0) throw new InvalidArgumentException('Quantity must be greater than zero.');
        if (trim($reason)     === '') throw new InvalidArgumentException('Reason is required for an internal transfer.');

        if ($from_location_id === $to_location_id && $from_warehouse_id === $to_warehouse_id) {
            throw new InvalidArgumentException('Source and destination must be different locations.');
        }

        $db   = Database::connect();
        $item = $this->resolveStockItem($product_id, $variant_id);

        // Validate source location belongs to source warehouse
        $srcLoc = $db->table('warehouse_locations')
            ->select('id, warehouse_id')->where('id', $from_location_id)->get()->getRowArray();
        if (!$srcLoc) throw new InvalidArgumentException('Source location not found.');
        if ((int)$srcLoc['warehouse_id'] !== $from_warehouse_id) {
            throw new InvalidArgumentException('Source location does not belong to the selected source warehouse.');
        }

        // Validate destination location belongs to destination warehouse
        $dstLoc = $db->table('warehouse_locations')
            ->select('id, warehouse_id')->where('id', $to_location_id)->get()->getRowArray();
        if (!$dstLoc) throw new InvalidArgumentException('Destination location not found.');
        if ((int)$dstLoc['warehouse_id'] !== $to_warehouse_id) {
            throw new InvalidArgumentException('Destination location does not belong to the selected destination warehouse.');
        }

        // Check source balance
        $balCols = array_flip($db->getFieldNames('stock_balances'));
        $srcBalQ = $db->table('stock_balances')
            ->where('warehouse_id', $from_warehouse_id)
            ->where('location_id',  $from_location_id);
        if (isset($balCols['item_key'])) {
            $srcBalQ->where('item_key', $item['item_key']);
        } else {
            $srcBalQ->where('product_id', $item['product_id']);
            if ($item['variant_id'] && isset($balCols['variant_id'])) {
                $srcBalQ->where('variant_id', $item['variant_id']);
            }
        }
        $srcBal = $srcBalQ->get()->getRowArray();
        $srcQty = (float)($srcBal['quantity'] ?? 0);
        if ($srcQty + 0.00001 < $quantity) {
            throw new RuntimeException(
                "Insufficient stock at source location. Available: " . number_format($srcQty, 4)
            );
        }

        $db->transException(true)->transStart();
        $now       = date('Y-m-d H:i:s');
        $reasonStr = trim($reason);
        $notesStr  = trim($notes) ?: null;
        $movCols   = array_flip($db->getFieldNames('stock_movements'));

        // Insert OUT movement (deduction from source)
        $outData = array_intersect_key([
            'product_id'     => $item['product_id'],
            'variant_id'     => $item['variant_id'],
            'item_key'       => $item['item_key'],
            'warehouse_id'   => $from_warehouse_id,
            'location_id'    => $from_location_id,
            'qty_change'     => -1 * $quantity,
            'movement_type'  => 'transfer_out',
            'reference_type' => 'internal_transfer',
            'reference_id'   => 0,             // updated after we get transferId
            'stock_source'   => $reasonStr,
            'created_by'     => $user_id,
            'created_at'     => $now,
        ], $movCols);
        $db->table('stock_movements')->insert($outData);
        $outMovId = (int)$db->insertID();

        // Insert IN movement (addition at destination)
        $inData = array_intersect_key([
            'product_id'     => $item['product_id'],
            'variant_id'     => $item['variant_id'],
            'item_key'       => $item['item_key'],
            'warehouse_id'   => $to_warehouse_id,
            'location_id'    => $to_location_id,
            'qty_change'     => $quantity,
            'movement_type'  => 'transfer_in',
            'reference_type' => 'internal_transfer',
            'reference_id'   => 0,             // updated after we get transferId
            'stock_source'   => $reasonStr,
            'created_by'     => $user_id,
            'created_at'     => $now,
        ], $movCols);
        $db->table('stock_movements')->insert($inData);
        $inMovId = (int)$db->insertID();

        // Insert internal_transfers record (transfer_number based on auto-increment id)
        $db->table('internal_transfers')->insert([
            'transfer_number'   => 'PENDING',
            'product_id'        => $item['product_id'],
            'variant_id'        => $item['variant_id'],
            'item_key'          => $item['item_key'],
            'quantity'          => $quantity,
            'from_warehouse_id' => $from_warehouse_id,
            'from_location_id'  => $from_location_id,
            'to_warehouse_id'   => $to_warehouse_id,
            'to_location_id'    => $to_location_id,
            'reason'            => $reasonStr,
            'notes'             => $notesStr,
            'out_movement_id'   => $outMovId,
            'in_movement_id'    => $inMovId,
            'created_by'        => $user_id,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
        $transferId     = (int)$db->insertID();
        $transferNumber = 'TRF-' . str_pad($transferId, 5, '0', STR_PAD_LEFT);

        // Back-fill transfer_number and reference_id on movements
        $db->table('internal_transfers')->where('id', $transferId)->update([
            'transfer_number' => $transferNumber,
        ]);
        $db->table('stock_movements')->whereIn('id', [$outMovId, $inMovId])->update([
            'reference_id' => $transferId,
        ]);

        // Deduct source stock_balances
        $db->table('stock_balances')
            ->where('id', (int)$srcBal['id'])
            ->set('quantity', "quantity - {$db->escapeString((string)$quantity)}", false)
            ->set('updated_at', $now)
            ->update();

        // Upsert destination stock_balances
        $dstBalQ = $db->table('stock_balances')
            ->where('warehouse_id', $to_warehouse_id)
            ->where('location_id',  $to_location_id);
        if (isset($balCols['item_key'])) {
            $dstBalQ->where('item_key', $item['item_key']);
        } else {
            $dstBalQ->where('product_id', $item['product_id']);
            if ($item['variant_id'] && isset($balCols['variant_id'])) {
                $dstBalQ->where('variant_id', $item['variant_id']);
            }
        }
        $dstBal = $dstBalQ->get()->getRowArray();
        if ($dstBal) {
            $db->table('stock_balances')
                ->where('id', (int)$dstBal['id'])
                ->set('quantity', "quantity + {$db->escapeString((string)$quantity)}", false)
                ->set('updated_at', $now)
                ->update();
        } else {
            $newBal = array_intersect_key([
                'product_id'   => $item['product_id'],
                'variant_id'   => $item['variant_id'],
                'item_key'     => $item['item_key'],
                'warehouse_id' => $to_warehouse_id,
                'location_id'  => $to_location_id,
                'quantity'     => $quantity,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], $balCols);
            $db->table('stock_balances')->insert($newBal);
        }

        try {
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException('Internal transfer failed (commit): ' . $e->getMessage(), 0, $e);
        }

        if ($db->transStatus() === false) {
            $err = $db->error();
            $msg = !empty($err['message']) ? $err['message'] : 'Unknown DB error';
            throw new RuntimeException('Internal transfer failed: ' . $msg);
        }

        $this->syncTemplateCurrentStock((int)$item['product_id']);

        // Sync variant_inventory for moved variant across source and destination warehouses
        try {
            if (!empty($item['variant_id'])) {
                $vid = (int)$item['variant_id'];
                $viCols = array_flip($db->getFieldNames('variant_inventory'));
                foreach ([$from_warehouse_id, $to_warehouse_id] as $wid) {
                    $newTotal = (float)($db->table('stock_balances')
                        ->selectSum('quantity')
                        ->where('variant_id', $vid)
                        ->where('warehouse_id', $wid)
                        ->get()
                        ->getRow()
                        ->quantity ?? 0);

                    $viRow = $db->table('variant_inventory')
                        ->where(['variant_id' => $vid, 'warehouse_id' => $wid])
                        ->get()->getRowArray();

                    if ($viRow) {
                        $updateData = ['quantity' => $newTotal, 'updated_at' => date('Y-m-d H:i:s')];
                        $db->table('variant_inventory')
                            ->where('id', (int)$viRow['id'])
                            ->update(array_intersect_key($updateData, $viCols));
                    } else {
                        $insertData = ['variant_id' => $vid, 'warehouse_id' => $wid, 'quantity' => $newTotal, 'reserved' => 0, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
                        $db->table('variant_inventory')->insert(array_intersect_key($insertData, $viCols));
                    }
                }
            }
        } catch (\Throwable $_) {
            // best-effort: do not break transfer on variant sync failures
        }

        return $transferId;
    }

    /**
     * Create the stock_adjustment_batches table if it does not exist yet.
     * Called lazily so no migration file is required.
     */
    private function ensureAdjustmentBatchTable(): void
    {
        $db = Database::connect();
        try {
            $db->query("CREATE TABLE IF NOT EXISTS `stock_adjustment_batches` (
                `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `adjustment_type`       VARCHAR(50)  NOT NULL DEFAULT 'adjustment',
                `mode`                  VARCHAR(20)  NOT NULL DEFAULT 'add',
                `notes`                 TEXT         DEFAULT NULL,
                `warehouse_id`          INT UNSIGNED DEFAULT NULL,
                `location_id`           INT UNSIGNED DEFAULT NULL,
                `line_count`            INT          NOT NULL DEFAULT 0,
                `total_estimated_value` DECIMAL(18,2) NULL DEFAULT NULL,
                `created_by`            INT          DEFAULT NULL,
                `created_at`            DATETIME     DEFAULT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            log_message('warning', 'ensureAdjustmentBatchTable create: ' . $e->getMessage());
        }
        // Ensure total_estimated_value column exists on pre-existing tables
        try {
            $cols = array_column($db->query('DESCRIBE `stock_adjustment_batches`')->getResultArray(), 'Field');
            if (!in_array('total_estimated_value', $cols, true)) {
                $db->query('ALTER TABLE `stock_adjustment_batches` ADD COLUMN `total_estimated_value` DECIMAL(18,2) NULL DEFAULT NULL');
            }
        } catch (\Throwable $e) {
            log_message('warning', 'ensureAdjustmentBatchTable alter: ' . $e->getMessage());
        }
    }

    /**
     * Create a lightweight audit table for per-line stock adjustment traces.
     */
    private function ensureAdjustmentAuditTable(): void
    {
        $db = Database::connect();
        try {
            $db->query("CREATE TABLE IF NOT EXISTS `stock_adjustment_audit` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `batch_id` INT UNSIGNED NOT NULL,
                `movement_id` INT UNSIGNED NULL,
                `product_id` INT UNSIGNED NOT NULL,
                `variant_id` INT UNSIGNED NULL,
                `warehouse_id` INT UNSIGNED NULL,
                `location_id` INT UNSIGNED NULL,
                `mode` VARCHAR(20) NOT NULL,
                `action_kind` VARCHAR(30) NOT NULL,
                `old_balance` DECIMAL(18,4) NOT NULL DEFAULT 0,
                `target_qty` DECIMAL(18,4) NOT NULL DEFAULT 0,
                `qty_change` DECIMAL(18,4) NOT NULL DEFAULT 0,
                `reason_code` VARCHAR(40) NULL,
                `reason_text` VARCHAR(255) NULL,
                `created_by` INT NULL,
                `created_at` DATETIME NULL,
                PRIMARY KEY (`id`),
                KEY `idx_saa_batch` (`batch_id`),
                KEY `idx_saa_movement` (`movement_id`),
                KEY `idx_saa_product` (`product_id`),
                KEY `idx_saa_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Throwable $e) {
            log_message('warning', 'ensureAdjustmentAuditTable create: ' . $e->getMessage());
        }
    }

    /**
     * Compact stock_source token for movement rows (max length-safe for varchar(30)).
     */
    private function buildAdjustmentStockSource(string $movementType, ?string $stockSource, ?string $zeroReasonCode): ?string
    {
        if ($movementType === 'opening_stock') {
            return $stockSource;
        }
        if (!empty($zeroReasonCode)) {
            return match ($zeroReasonCode) {
                'damaged' => 'set0_dmg',
                'expired' => 'set0_exp',
                'missing' => 'set0_lost',
                'count_correction' => 'set0_cnt',
                'qc_reject' => 'set0_qc',
                'custom' => 'set0_custom',
                default => 'set0_other',
            };
        }
        return null;
    }

    /**
     * Get the current on-hand quantity for a resolved stock item at a specific location.
     */
    private function getCurrentBalance(int $product_id, ?int $variant_id, string $item_key, int $warehouse_id, int $location_id): float
    {
        $db = Database::connect();
        $balanceColumns = @$db->getFieldNames('stock_balances') ?: [];
        $q = $db->table('stock_balances')
            ->where('warehouse_id', $warehouse_id)
            ->where('location_id', $location_id);
        if (in_array('item_key', $balanceColumns, true)) {
            $q->where('item_key', $item_key);
        } else {
            $q->where('product_id', $product_id);
            if ($variant_id && in_array('variant_id', $balanceColumns, true)) {
                $q->where('variant_id', $variant_id);
            }
        }
        $row = $q->get()->getRowArray();
        return (float)($row['quantity'] ?? 0.0);
    }

    /**
     * Process a batch stock adjustment (opening stock, add, subtract, or set exact).
     *
     * All lines are processed inside a single database transaction — either all
     * succeed or all roll back. This is the only safe way to handle bulk changes.
     *
     * @param int         $warehouse_id
     * @param int         $location_id
     * @param array       $lines         Array of ['product_id' => int, 'variant_id' => ?int, 'qty' => float]
     * @param string      $mode          'add' | 'subtract' | 'set'
     * @param string      $movement_type 'opening_stock' | 'adjustment'
     * @param int         $user_id
     * @param string|null $notes
     *
     * @return array ['batch_id' => int, 'results' => [...per-line data...]]
     * @throws InvalidArgumentException|RuntimeException
     */
    public function processBatchAdjustment(
        int $warehouse_id,
        int $location_id,
        array $lines,
        string $mode,
        string $movement_type,
        int $user_id,
        ?string $notes = null
    ): array {
        if ($warehouse_id <= 0) throw new InvalidArgumentException('Warehouse is required.');
        if ($location_id <= 0)  throw new InvalidArgumentException('Location is required.');
        if (empty($lines))      throw new InvalidArgumentException('At least one line is required.');
        if (!in_array($mode, ['add', 'subtract', 'set'], true)) {
            throw new InvalidArgumentException('Invalid mode. Must be add, subtract, or set.');
        }

        $db = Database::connect();

        // Validate warehouse location once
        $loc = $db->table('warehouse_locations')->select('id, warehouse_id')->where('id', $location_id)->get()->getRowArray();
        if (!$loc) throw new InvalidArgumentException('Location not found.');
        if ((int)$loc['warehouse_id'] !== $warehouse_id) {
            throw new InvalidArgumentException('Location does not belong to the selected warehouse.');
        }

        $this->ensureAdjustmentBatchTable();
        $this->ensureAdjustmentAuditTable();

        // ── Phase 1: Resolve and validate all lines BEFORE opening any transaction ──
        $resolved = [];
        foreach ($lines as $i => $line) {
            $pid         = (int)($line['product_id'] ?? 0);
            $vid         = isset($line['variant_id']) && $line['variant_id'] ? (int)$line['variant_id'] : null;
            $qty         = (float)($line['qty'] ?? 0);
            $unitCost    = isset($line['unit_cost']) && $line['unit_cost'] !== null && $line['unit_cost'] !== ''
                         ? (float)$line['unit_cost'] : null;
            $stockSource = isset($line['stock_source']) && $line['stock_source'] !== ''
                         ? trim((string)$line['stock_source']) : null;
            if ($stockSource !== null && !in_array($stockSource, ['old_stock', 'purchased_no_record', 'known'], true)) {
                $stockSource = null;
            }
            $vendorId = isset($line['possible_vendor_id']) && $line['possible_vendor_id']
                      ? (int)$line['possible_vendor_id'] : null;
            $zeroReasonCode = isset($line['zero_reason_code']) && $line['zero_reason_code'] !== ''
                      ? trim((string)$line['zero_reason_code']) : null;
            $zeroReasonCustom = isset($line['zero_reason_custom']) && $line['zero_reason_custom'] !== ''
                      ? trim((string)$line['zero_reason_custom']) : null;

            if ($pid <= 0) throw new InvalidArgumentException("Line " . ($i + 1) . ": product_id is required.");
            if ($qty < 0) throw new InvalidArgumentException("Line " . ($i + 1) . ": negative quantity is not allowed.");
            if ($mode !== 'set' && $qty <= 0) throw new InvalidArgumentException("Line " . ($i + 1) . ": quantity must be greater than zero.");

            // resolveStockItem validates product exists, is storable, and variant belongs to product
            $item = $this->resolveStockItem($pid, $vid);

            $oldBalance = $this->getCurrentBalance(
                $item['product_id'], $item['variant_id'], $item['item_key'],
                $warehouse_id, $location_id
            );

            // Compute the signed qty_change
            $qtyChange = match ($mode) {
                'add'      => $qty,
                'subtract' => -1 * $qty,
                'set'      => $qty - $oldBalance,
            };

            if ($mode === 'subtract' && ($oldBalance + 0.00001) < $qty) {
                $pname = $db->table('products')->select('name')->where('id', $item['product_id'])->get()->getRowArray()['name'] ?? "Product #{$item['product_id']}";
                throw new InvalidArgumentException(
                    "Line " . ($i + 1) . " ({$pname}): Cannot subtract {$qty} — only {$oldBalance} in stock at this location."
                );
            }

            if ($mode === 'set' && abs($qtyChange) < 0.000001) {
                // No actual change needed — this line is a no-op; still record for audit
                $qtyChange = 0.0;
            }

            if ($mode === 'set' && abs($qty) < 0.000001) {
                if (empty($zeroReasonCode)) {
                    throw new InvalidArgumentException("Line " . ($i + 1) . ": reason is required when setting quantity to zero.");
                }
                if ($zeroReasonCode === 'custom' && empty($zeroReasonCustom)) {
                    throw new InvalidArgumentException("Line " . ($i + 1) . ": custom reason is required for zero quantity.");
                }
            }

            $actionKind = 'adjustment';
            if ($mode === 'set' && abs($qty) < 0.000001) {
                $actionKind = 'set_zero';
            } elseif ($qtyChange > 0) {
                $actionKind = 'increase';
            } elseif ($qtyChange < 0) {
                $actionKind = 'decrease';
            } elseif ($mode === 'set') {
                $actionKind = 'set_exact';
            }

            $reasonText = null;
            if ($mode === 'set' && abs($qty) < 0.000001) {
                $base = match ($zeroReasonCode) {
                    'damaged' => 'Damaged / Defective',
                    'expired' => 'Expired / Obsolete',
                    'missing' => 'Missing / Lost',
                    'count_correction' => 'Physical Count Correction',
                    'qc_reject' => 'Rejected by QC',
                    'custom' => 'Other',
                    default => 'Other',
                };
                $reasonText = $zeroReasonCode === 'custom'
                    ? ('Other: ' . $zeroReasonCustom)
                    : $base;
            }

            $movementStockSource = $this->buildAdjustmentStockSource($movement_type, $stockSource, $zeroReasonCode);

            $resolved[] = [
                'item'               => $item,
                'qty'                => $qty,
                'qty_change'         => $qtyChange,
                'old_balance'        => $oldBalance,
                'new_balance'        => $oldBalance + $qtyChange,
                'unit_cost'          => $unitCost,
                'stock_source'       => $movementStockSource,
                'possible_vendor_id' => $vendorId,
                'zero_reason_code'   => $zeroReasonCode,
                'zero_reason_text'   => $reasonText,
                'action_kind'        => $actionKind,
            ];
        }

        // ── Phase 2: Execute everything in one transaction ──
        $db->transException(true)->transStart();
        $now = date('Y-m-d H:i:s');

        // Compute total estimated value from resolved lines
        $totalEstValue = 0.0;
        foreach ($resolved as $r) {
            if ($r['unit_cost'] !== null && $r['qty_change'] != 0.0) {
                $totalEstValue += abs((float)$r['qty_change']) * (float)$r['unit_cost'];
            }
        }

        // Create the batch record
        $db->table('stock_adjustment_batches')->insert([
            'adjustment_type'       => $movement_type,
            'mode'                  => $mode,
            'notes'                 => $notes,
            'warehouse_id'          => $warehouse_id,
            'location_id'           => $location_id,
            'line_count'            => count($resolved),
            'total_estimated_value' => $totalEstValue > 0 ? round($totalEstValue, 2) : null,
            'created_by'            => $user_id,
            'created_at'            => $now,
        ]);
        $batchId = $db->insertID();
        if (!$batchId) {
            $db->transRollback();
            throw new RuntimeException('Failed to create adjustment batch record.');
        }

        $movementColumns = array_flip($db->getFieldNames('stock_movements'));
        $balanceColumns  = array_flip($db->getFieldNames('stock_balances'));

        $results = [];
        foreach ($resolved as $r) {
            $item       = $r['item'];
            $qtyChange  = $r['qty_change'];
            $newBalance = $r['new_balance'];
            $movementId = null;

            // Skip true no-ops in 'set' mode but still record in results
            if ($qtyChange != 0.0) {
                // Insert stock_movement
                $movementData = [
                    'product_id'         => $item['product_id'],
                    'variant_id'         => $item['variant_id'],
                    'item_key'           => $item['item_key'],
                    'warehouse_id'       => $warehouse_id,
                    'location_id'        => $location_id,
                    'qty_change'         => $qtyChange,
                    'unit_cost'          => $r['unit_cost'],
                    'stock_source'       => $r['stock_source'],
                    'possible_vendor_id' => $r['possible_vendor_id'],
                    'movement_type'      => $movement_type,
                    'reference_type'     => 'stock_adjustment',
                    'reference_id'       => $batchId,
                    'created_by'         => $user_id,
                    'created_at'         => $now,
                ];
                $movementData = array_intersect_key($movementData, $movementColumns);
                $db->table('stock_movements')->insert($movementData);
                $movementId = (int)$db->insertID();
                $err = $db->error();
                if (!empty($err['message'])) {
                    $db->transRollback();
                    throw new RuntimeException('Movement insert failed: ' . $err['message']);
                }

                // Upsert stock_balances
                $existingQuery = $db->table('stock_balances')
                    ->where('warehouse_id', $warehouse_id)
                    ->where('location_id', $location_id);
                if (isset($balanceColumns['item_key'])) {
                    $existingQuery->where('item_key', $item['item_key']);
                } else {
                    $existingQuery->where('product_id', $item['product_id']);
                    if ($item['variant_id'] && isset($balanceColumns['variant_id'])) {
                        $existingQuery->where('variant_id', $item['variant_id']);
                    }
                }
                $existing = $existingQuery->get()->getRow();

                if ($existing) {
                    $db->table('stock_balances')
                        ->where('id', (int)$existing->id)
                        ->set('quantity', "quantity + {$db->escapeString((string)$qtyChange)}", false)
                        ->set('updated_at', $now)
                        ->update();
                } else {
                    $balanceData = [
                        'product_id'   => $item['product_id'],
                        'variant_id'   => $item['variant_id'],
                        'item_key'     => $item['item_key'],
                        'warehouse_id' => $warehouse_id,
                        'location_id'  => $location_id,
                        'quantity'     => $newBalance,
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ];
                    $balanceData = array_intersect_key($balanceData, $balanceColumns);
                    $db->table('stock_balances')->insert($balanceData);
                }

                $err = $db->error();
                if (!empty($err['message'])) {
                    $db->transRollback();
                    throw new RuntimeException('Balance upsert failed: ' . $err['message']);
                }
            }

            // Per-line audit trace for owner visibility and accountability.
            $db->table('stock_adjustment_audit')->insert([
                'batch_id'     => $batchId,
                'movement_id'  => $movementId ?: null,
                'product_id'   => $item['product_id'],
                'variant_id'   => $item['variant_id'],
                'warehouse_id' => $warehouse_id,
                'location_id'  => $location_id,
                'mode'         => $mode,
                'action_kind'  => $r['action_kind'],
                'old_balance'  => $r['old_balance'],
                'target_qty'   => $r['qty'],
                'qty_change'   => $qtyChange,
                'reason_code'  => $r['zero_reason_code'],
                'reason_text'  => $r['zero_reason_text'],
                'created_by'   => $user_id,
                'created_at'   => $now,
            ]);

            $results[] = [
                'product_id'  => $item['product_id'],
                'variant_id'  => $item['variant_id'],
                'item_key'    => $item['item_key'],
                'qty_change'  => $qtyChange,
                'old_balance' => $r['old_balance'],
                'new_balance' => $newBalance,
                'action_kind' => $r['action_kind'],
            ];
        }

        try {
            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw new RuntimeException('Batch commit failed: ' . $e->getMessage(), 0, $e);
        }

        if ($db->transStatus() === false) {
            $err = $db->error();
            throw new RuntimeException('Batch transaction failed: ' . (!empty($err['message']) ? $err['message'] : 'Unknown error'));
        }

        // Sync current_stock (legacy) for each unique product_id
        $syncedProducts = [];
        foreach ($resolved as $r) {
            $pid = (int)$r['item']['product_id'];
            if (!in_array($pid, $syncedProducts, true)) {
                $this->syncTemplateCurrentStock($pid);
                $syncedProducts[] = $pid;
            }
        }

        // Sync variant_inventory for every adjusted variant.
        // InventoryAvailabilityService reads variant_inventory (Priority 1) for variants,
        // so it must be kept in sync with stock_balances or availability will appear as zero.
        $syncedVariants = [];
        foreach ($resolved as $r) {
            $vid = $r['item']['variant_id'];
            if (!$vid || in_array($vid, $syncedVariants, true)) continue;
            $syncedVariants[] = $vid;

            // Total across all locations for this (variant, warehouse)
            $newTotal = (float)($db->table('stock_balances')
                ->selectSum('quantity')
                ->where('variant_id', (int)$vid)
                ->where('warehouse_id', $warehouse_id)
                ->get()->getRow()->quantity ?? 0);

            $viRow = $db->table('variant_inventory')
                ->where(['variant_id' => (int)$vid, 'warehouse_id' => $warehouse_id])
                ->get()->getRowArray();

            if ($viRow) {
                $updateData = ['quantity' => $newTotal, 'updated_at' => date('Y-m-d H:i:s')];
                $viCols = array_flip($db->getFieldNames('variant_inventory'));
                $db->table('variant_inventory')
                    ->where('id', (int)$viRow['id'])
                    ->update(array_intersect_key($updateData, $viCols));
            } else {
                $insertData = ['variant_id' => (int)$vid, 'warehouse_id' => $warehouse_id, 'quantity' => $newTotal, 'reserved' => 0];
                $viCols = array_flip($db->getFieldNames('variant_inventory'));
                $db->table('variant_inventory')->insert(array_intersect_key($insertData, $viCols));
            }
        }

        return ['batch_id' => $batchId, 'results' => $results];
    }
}

