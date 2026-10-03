<?php

namespace App\Services;

use Config\Database;
use InvalidArgumentException;
use RuntimeException;

/**
 * Posts stock specifically required by sales-order shortage lines.
 *
 * Product, quantity, vendor and cost are all revalidated server-side. The
 * request key makes a browser retry safe, while the row locks keep the
 * shortage check and stock posting in the same database transaction.
 */
class SalesOrderQuickStockService
{
    public function addMissingStock(array $order, array $payload, int $userId): array
    {
        $orderId = (int) ($order['id'] ?? 0);
        $requestKey = trim((string) ($payload['request_key'] ?? ''));
        $warehouseId = (int) ($payload['warehouse_id'] ?? 0);
        $locationId = (int) ($payload['location_id'] ?? 0);
        $rawLines = $payload['lines'] ?? null;

        if ($orderId <= 0) {
            throw new InvalidArgumentException('Sales order was not found.');
        }
        if ($userId <= 0) {
            throw new InvalidArgumentException('An authenticated user is required.');
        }
        if (! preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestKey)) {
            throw new InvalidArgumentException('Invalid request token. Refresh the page and try again.');
        }
        if (! is_array($rawLines) || $rawLines === [] || count($rawLines) > 100) {
            throw new InvalidArgumentException('Select between 1 and 100 missing-stock lines.');
        }

        $status = strtolower(trim((string) ($order['status'] ?? '')));
        if (in_array($status, ['cancelled', 'canceled', 'shipped', 'delivered', 'completed', 'closed'], true)) {
            throw new InvalidArgumentException('Quick stock entry is not available for a finalized sales order.');
        }

        $submitted = [];
        foreach ($rawLines as $index => $rawLine) {
            if (! is_array($rawLine)) {
                throw new InvalidArgumentException('Line ' . ($index + 1) . ': invalid line data.');
            }
            $lineId = (int) ($rawLine['sales_order_line_id'] ?? 0);
            if ($lineId <= 0 || isset($submitted[$lineId])) {
                throw new InvalidArgumentException('Each selected sales-order line must be valid and unique.');
            }

            $qty = $this->positiveDecimal($rawLine['qty'] ?? null, 'quantity', $index);
            $cost = $this->positiveDecimal($rawLine['unit_cost'] ?? null, 'cost price', $index);
            $vendorId = (int) ($rawLine['vendor_id'] ?? 0);
            if ($vendorId <= 0) {
                throw new InvalidArgumentException('Line ' . ($index + 1) . ': vendor is required.');
            }
            $lineWarehouseId = (int) ($rawLine['warehouse_id'] ?? 0);
            $lineLocationId = (int) ($rawLine['location_id'] ?? 0);
            if ($lineWarehouseId <= 0) {
                $lineWarehouseId = $warehouseId;
            }
            if ($lineLocationId <= 0) {
                $lineLocationId = $locationId;
            }
            if ($lineWarehouseId <= 0 || $lineLocationId <= 0) {
                throw new InvalidArgumentException(
                    'Line ' . ($index + 1) . ': select a global storage destination or an individual warehouse and location.'
                );
            }

            $submitted[$lineId] = [
                'qty' => $qty,
                'unit_cost' => $cost,
                'vendor_id' => $vendorId,
                'warehouse_id' => $lineWarehouseId,
                'location_id' => $lineLocationId,
            ];
        }

        $db = Database::connect();
        if (! $db->tableExists('stock_adjustment_requests') || ! $db->tableExists('stock_adjustment_request_batches')) {
            throw new RuntimeException('Quick stock entry is not installed yet. Run the pending database migrations.');
        }

        $destinationLocationIds = array_values(array_unique(array_column($submitted, 'location_id')));
        $this->validateDestinations($db, $submitted);

        $existing = $db->table('stock_adjustment_requests')
            ->where('request_key', $requestKey)
            ->get()->getRowArray();
        if ($existing) {
            return $this->replayResult($existing, $orderId, $userId);
        }

        $inventoryService = new InventoryService();
        $inventoryService->ensureAdjustmentInfrastructure();

        $db->transException(true)->transBegin();
        try {
            $db->table('stock_adjustment_requests')->insert([
                'request_key' => $requestKey,
                'sales_order_id' => $orderId,
                'batch_id' => null,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $requestId = (int) $db->insertID();

            $destinationPlaceholders = implode(',', array_fill(0, count($destinationLocationIds), '?'));
            $db->query(
                "SELECT id FROM warehouse_locations WHERE id IN ({$destinationPlaceholders}) FOR UPDATE",
                $destinationLocationIds
            );
            $destinationWarehouseIds = array_values(array_unique(array_column($submitted, 'warehouse_id')));
            $warehousePlaceholders = implode(',', array_fill(0, count($destinationWarehouseIds), '?'));
            $db->query(
                "SELECT id FROM warehouses WHERE id IN ({$warehousePlaceholders}) FOR UPDATE",
                $destinationWarehouseIds
            );
            $this->validateDestinations($db, $submitted);

            $lineIds = array_keys($submitted);
            $placeholders = implode(',', array_fill(0, count($lineIds), '?'));
            $db->query(
                "SELECT id FROM sales_order_lines WHERE sales_order_id = ? AND id IN ({$placeholders}) FOR UPDATE",
                array_merge([$orderId], $lineIds)
            );

            $orderLines = $db->table('sales_order_lines')
                ->where('sales_order_id', $orderId)
                ->whereIn('id', $lineIds)
                ->get()->getResultArray();
            if (count($orderLines) !== count($submitted)) {
                throw new InvalidArgumentException('One or more selected lines do not belong to this sales order.');
            }

            $productIds = array_values(array_unique(array_map(
                static fn (array $line): int => (int) ($line['product_id'] ?? 0),
                $orderLines
            )));
            if (in_array(0, $productIds, true)) {
                throw new InvalidArgumentException('A selected sales-order line is not linked to a product.');
            }

            $productPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
            if ($db->tableExists('stock_balances')) {
                $db->query(
                    "SELECT id FROM stock_balances WHERE product_id IN ({$productPlaceholders}) FOR UPDATE",
                    $productIds
                );
            }
            if ($db->tableExists('stock_reservations')) {
                $db->query(
                    "SELECT id FROM stock_reservations WHERE product_id IN ({$productPlaceholders}) AND status = ? FOR UPDATE",
                    array_merge($productIds, ['active'])
                );
            }

            $vendors = $db->table('vendors')
                ->select('id')
                ->where('is_active', 1)
                ->whereIn('id', array_values(array_unique(array_column($submitted, 'vendor_id'))))
                ->get()->getResultArray();
            $activeVendorIds = array_fill_keys(array_map('intval', array_column($vendors, 'id')), true);

            $products = $db->table('products')->whereIn('id', $productIds)->get()->getResultArray();
            $productMap = [];
            foreach ($products as $product) {
                $productMap[(int) $product['id']] = $product;
            }

            $readyMap = [];
            $readyData = (new ReadyToShipService())->getLineReadiness($orderId);
            foreach (($readyData['lines'] ?? []) as $readyLine) {
                $readyMap[(int) ($readyLine['sales_order_line_id'] ?? 0)] = $readyLine;
            }

            $availabilityService = new InventoryAvailabilityService();
            $adjustmentGroups = [];
            $adjustmentLineCount = 0;
            foreach ($orderLines as $orderLine) {
                $lineId = (int) $orderLine['id'];
                $input = $submitted[$lineId];
                $productId = (int) $orderLine['product_id'];
                $variantId = (int) ($orderLine['product_variant_id'] ?? ($orderLine['variant_id'] ?? 0));
                $variantId = $variantId > 0 ? $variantId : null;
                $product = $productMap[$productId] ?? null;

                if (! $product || strtolower((string) ($product['detailed_type'] ?? 'storable')) !== 'storable') {
                    throw new InvalidArgumentException('Only storable products can be added through quick stock entry.');
                }
                if (! isset($activeVendorIds[$input['vendor_id']])) {
                    throw new InvalidArgumentException('An inactive or invalid vendor was selected.');
                }

                $orderedQty = (float) ($orderLine['quantity'] ?? 0);
                $remainingQty = max(0.0, (float) ($readyMap[$lineId]['remaining_qty'] ?? $orderedQty));
                $availability = $availabilityService->getAvailability(
                    $productId,
                    $variantId,
                    strtolower((string) ($product['product_type'] ?? '')),
                    ['exclude_sales_order_line_id' => $lineId]
                );
                $shortage = max(0.0, $remainingQty - (float) ($availability['available'] ?? 0));
                if ($shortage <= 0.00001) {
                    throw new InvalidArgumentException('Stock availability changed and a selected line is no longer short. Refresh and try again.');
                }
                if ($input['qty'] - $shortage > 0.00001) {
                    throw new InvalidArgumentException(
                        'Requested quantity exceeds the current shortage for sales-order line #' . $lineId . '.'
                    );
                }

                $destinationKey = $input['warehouse_id'] . ':' . $input['location_id'];
                if (! isset($adjustmentGroups[$destinationKey])) {
                    $adjustmentGroups[$destinationKey] = [
                        'warehouse_id' => $input['warehouse_id'],
                        'location_id' => $input['location_id'],
                        'lines' => [],
                    ];
                }
                $adjustmentGroups[$destinationKey]['lines'][] = [
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'qty' => $input['qty'],
                    'unit_cost' => $input['unit_cost'],
                    'stock_source' => 'known',
                    'possible_vendor_id' => $input['vendor_id'],
                ];
                $adjustmentLineCount++;
            }

            $orderNumber = trim((string) ($order['order_number'] ?? ('SO-' . $orderId)));
            $batchIds = [];
            foreach ($adjustmentGroups as $group) {
                $result = $inventoryService->processBatchAdjustment(
                    (int) $group['warehouse_id'],
                    (int) $group['location_id'],
                    $group['lines'],
                    'add',
                    'opening_stock',
                    $userId,
                    'Quick stock entry for ' . mb_substr($orderNumber, 0, 100)
                );
                $batchId = (int) $result['batch_id'];
                $batchIds[] = $batchId;
                $db->table('stock_adjustment_request_batches')->insert([
                    'request_id' => $requestId,
                    'batch_id' => $batchId,
                    'warehouse_id' => (int) $group['warehouse_id'],
                    'location_id' => (int) $group['location_id'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $db->table('stock_adjustment_requests')->where('id', $requestId)->update([
                'batch_id' => $batchIds[0] ?? null,
            ]);
            $db->transCommit();

            return [
                'batch_id' => $batchIds[0] ?? 0,
                'batch_ids' => $batchIds,
                'line_count' => $adjustmentLineCount,
                'replayed' => false,
            ];
        } catch (\Throwable $e) {
            $db->transRollback();

            $existing = $db->table('stock_adjustment_requests')
                ->where('request_key', $requestKey)
                ->get()->getRowArray();
            if ($existing) {
                return $this->replayResult($existing, $orderId, $userId);
            }
            throw $e;
        }
    }

    private function positiveDecimal($value, string $label, int $index): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Line ' . ($index + 1) . ': ' . $label . ' is required.');
        }
        $number = (float) $value;
        if (! is_finite($number) || $number <= 0 || $number > 999999999999.9999) {
            throw new InvalidArgumentException('Line ' . ($index + 1) . ': enter a valid positive ' . $label . '.');
        }

        return $number;
    }

    private function validateDestinations($db, array $submitted): void
    {
        $locationIds = array_values(array_unique(array_column($submitted, 'location_id')));
        $select = 'wl.id, wl.warehouse_id, wl.is_active, w.is_active AS warehouse_is_active';
        if ($db->fieldExists('vendor_id', 'warehouse_locations')) {
            $select .= ', wl.vendor_id';
        }
        $locations = $db->table('warehouse_locations wl')
            ->select($select)
            ->join('warehouses w', 'w.id = wl.warehouse_id', 'inner')
            ->whereIn('wl.id', $locationIds)
            ->get()->getResultArray();
        $locationMap = [];
        foreach ($locations as $location) {
            $locationMap[(int) $location['id']] = $location;
        }
        foreach ($submitted as $lineId => $input) {
            $location = $locationMap[$input['location_id']] ?? null;
            if (
                ! $location
                || (int) ($location['warehouse_id'] ?? 0) !== $input['warehouse_id']
                || empty($location['is_active'])
                || empty($location['warehouse_is_active'])
                || ! empty($location['vendor_id'])
            ) {
                throw new InvalidArgumentException(
                    'Sales-order line #' . $lineId . ': select an active internal warehouse location that belongs to its warehouse.'
                );
            }
        }
    }

    private function replayResult(array $request, int $orderId, int $userId): array
    {
        if ((int) ($request['sales_order_id'] ?? 0) !== $orderId || (int) ($request['created_by'] ?? 0) !== $userId) {
            throw new InvalidArgumentException('This request token cannot be used for this sales order.');
        }
        $batchId = (int) ($request['batch_id'] ?? 0);
        if ($batchId <= 0) {
            throw new RuntimeException('This quick stock request is still processing. Please wait and refresh.');
        }

        $batchIds = [$batchId];
        $db = Database::connect();
        if ($db->tableExists('stock_adjustment_request_batches')) {
            $rows = $db->table('stock_adjustment_request_batches')
                ->select('batch_id')
                ->where('request_id', (int) ($request['id'] ?? 0))
                ->orderBy('id', 'ASC')
                ->get()->getResultArray();
            if ($rows !== []) {
                $batchIds = array_values(array_unique(array_map('intval', array_column($rows, 'batch_id'))));
            }
        }

        return [
            'batch_id' => $batchId,
            'batch_ids' => $batchIds,
            'line_count' => 0,
            'replayed' => true,
        ];
    }
}
