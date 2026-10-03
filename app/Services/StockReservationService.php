<?php

namespace App\Services;

use App\Models\PreparationComponentModel;
use App\Models\PreparationProfileModel;
use Config\Database;
use DomainException;

/**
 * Explicit, location-level stock holds. Reservations never change physical
 * stock; the normal inventory movement consumes the hold when work starts.
 */
class StockReservationService
{
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function isAvailable(): bool
    {
        return $this->db->tableExists('stock_reservations');
    }

    public function reservedQuantity(int $productId, ?int $variantId = null, ?int $excludeSalesOrderLineId = null): float
    {
        if (! $this->isAvailable()) {
            return 0.0;
        }

        $builder = $this->db->table('stock_reservations')
            ->select('COALESCE(SUM(reserved_quantity - consumed_quantity - released_quantity), 0) AS qty', false)
            ->where('product_id', $productId)
            ->where('status', 'active');
        $this->applyVariantFilter($builder, $variantId);
        if ($excludeSalesOrderLineId !== null && $excludeSalesOrderLineId > 0) {
            $builder->where('sales_order_line_id !=', $excludeSalesOrderLineId);
        }

        $row = $builder->get()->getRowArray();
        return max(0.0, (float) ($row['qty'] ?? 0));
    }

    /** @return array<int, array<string, mixed>> */
    public function locationAvailability(int $productId, ?int $variantId, int $salesOrderLineId): array
    {
        $builder = $this->db->table('stock_balances sb')
            ->select('sb.warehouse_id, sb.location_id, SUM(sb.quantity) AS on_hand, wl.name AS location_name, w.name AS warehouse_name')
            ->join('warehouse_locations wl', 'wl.id = sb.location_id', 'inner')
            ->join('warehouses w', 'w.id = sb.warehouse_id', 'left')
            ->where('sb.product_id', $productId)
            ->where('wl.is_active', 1)
            ->where('wl.vendor_id IS NULL', null, false);
        $this->applyVariantFilter($builder, $variantId, 'sb.variant_id');
        $rows = $builder
            ->groupBy('sb.warehouse_id, sb.location_id, wl.name, w.name')
            ->having('SUM(sb.quantity) >', 0)
            ->orderBy('on_hand', 'DESC')
            ->get()->getResultArray();

        foreach ($rows as &$row) {
            $reservedAll = $this->locationReserved($productId, $variantId, (int) $row['location_id']);
            $reservedHere = $salesOrderLineId > 0
                ? $this->locationReserved($productId, $variantId, (int) $row['location_id'], $salesOrderLineId)
                : 0.0;
            $row['reserved'] = round($reservedAll, 4);
            $row['reserved_for_order'] = round($reservedHere, 4);
            $row['available'] = round(max(0.0, (float) $row['on_hand'] - $reservedAll), 4);
            $row['available_for_order'] = round(max(0.0, (float) $row['on_hand'] - $reservedAll + $reservedHere), 4);
            $row['reserved_orders'] = $this->locationReservationLabels($productId, $variantId, (int) $row['location_id']);
            // Backward-compatible key used by existing execution modals.
            $row['qty'] = $row['available_for_order'];
        }
        unset($row);

        return $rows;
    }

    /** @return array{ok: bool, message: string} */
    public function reserve(
        int $salesOrderId,
        int $salesOrderLineId,
        int $productId,
        ?int $variantId,
        int $locationId,
        float $quantity,
        int $userId
    ): array {
        if (! $this->isAvailable()) {
            return ['ok' => false, 'message' => 'Stock reservation migration has not been applied.'];
        }
        if ($salesOrderId <= 0 || $salesOrderLineId <= 0 || $productId <= 0 || $locationId <= 0 || $quantity <= 0) {
            return ['ok' => false, 'message' => 'Order line, material, location, and a positive quantity are required.'];
        }

        $this->db->transBegin();
        try {
            $line = $this->db->query(
                'SELECT sol.*, so.status AS order_status FROM sales_order_lines sol JOIN sales_orders so ON so.id = sol.sales_order_id WHERE sol.id = ? AND sol.sales_order_id = ? FOR UPDATE',
                [$salesOrderLineId, $salesOrderId]
            )->getRowArray();
            if (! $line) {
                throw new DomainException('Sales order line does not belong to this order.');
            }
            if (! in_array(strtolower((string) $line['order_status']), ['confirmed', 'processing', 'in_progress', 'partially_shipped'], true)) {
                throw new DomainException('Only an active confirmed sales order can reserve stock.');
            }

            $requirement = $this->materialRequirement($line, $productId, $variantId);
            if ($requirement <= 0) {
                throw new DomainException('This material is not required by the selected order line profile.');
            }

            $stock = $this->lockedStockRow($productId, $variantId, $locationId);
            if (! $stock || ! empty($stock['vendor_id'])) {
                throw new DomainException('Select an active internal stock location for this material.');
            }

            $reservedAtLocation = $this->locationReserved($productId, $variantId, $locationId);
            $free = max(0.0, (float) $stock['quantity'] - $reservedAtLocation);
            if ($quantity > $free + 0.0001) {
                throw new DomainException('Only ' . number_format($free, 4) . ' is unreserved at this location.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('stock_reservations')->insert([
                'document_type' => 'sales_order',
                'document_id' => $salesOrderId,
                'sales_order_id' => $salesOrderId,
                'sales_order_line_id' => $salesOrderLineId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'warehouse_id' => (int) $stock['warehouse_id'],
                'location_id' => $locationId,
                'reserved_quantity' => number_format($quantity, 4, '.', ''),
                'status' => 'active',
                'created_by' => $userId > 0 ? $userId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->db->transCommit();
            return ['ok' => true, 'message' => number_format($quantity, 2) . ' pcs reserved for this sales order.'];
        } catch (DomainException $e) {
            $this->db->transRollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Stock reservation failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Stock could not be reserved. Please try again.'];
        }
    }

    /**
     * Reserve material across multiple locations/boxes in a single atomic transaction.
     *
     * @param array<int, array{location_id: int, quantity: float}> $allocations
     * @return array{ok: bool, message: string}
     */
    public function reserveMulti(
        int $salesOrderId,
        int $salesOrderLineId,
        int $productId,
        ?int $variantId,
        array $allocations,
        int $userId
    ): array {
        if ($salesOrderId <= 0 || $salesOrderLineId <= 0 || $productId <= 0 || empty($allocations)) {
            return ['ok' => false, 'message' => 'Order line, material, and at least one location allocation are required.'];
        }

        // Clean & group allocations by location_id
        $cleaned = [];
        $totalQuantity = 0.0;
        foreach ($allocations as $alloc) {
            $locId = (int) ($alloc['location_id'] ?? 0);
            $qty = (float) ($alloc['quantity'] ?? 0);
            if ($locId <= 0 || $qty <= 0.00001) {
                continue;
            }
            if (! isset($cleaned[$locId])) {
                $cleaned[$locId] = 0.0;
            }
            $cleaned[$locId] += $qty;
            $totalQuantity += $qty;
        }

        if (empty($cleaned) || $totalQuantity <= 0.00001) {
            return ['ok' => false, 'message' => 'Please enter a positive reservation quantity for at least one location.'];
        }

        if (! $this->isAvailable()) {
            return ['ok' => false, 'message' => 'Stock reservation migration has not been applied.'];
        }

        $this->db->transBegin();
        try {
            $line = $this->db->query(
                'SELECT sol.*, so.status AS order_status FROM sales_order_lines sol JOIN sales_orders so ON so.id = sol.sales_order_id WHERE sol.id = ? AND sol.sales_order_id = ? FOR UPDATE',
                [$salesOrderLineId, $salesOrderId]
            )->getRowArray();
            if (! $line) {
                throw new DomainException('Sales order line does not belong to this order.');
            }
            if (! in_array(strtolower((string) $line['order_status']), ['confirmed', 'processing', 'in_progress', 'partially_shipped'], true)) {
                throw new DomainException('Only an active confirmed sales order can reserve stock.');
            }

            $requirement = $this->materialRequirement($line, $productId, $variantId);
            if ($requirement <= 0) {
                throw new DomainException('This material is not required by the selected order line profile.');
            }

            $now = date('Y-m-d H:i:s');
            foreach ($cleaned as $locId => $qty) {
                $stock = $this->lockedStockRow($productId, $variantId, $locId);
                if (! $stock || ! empty($stock['vendor_id'])) {
                    throw new DomainException("Selected location #{$locId} is not an active internal stock location.");
                }

                $reservedAtLocation = $this->locationReserved($productId, $variantId, $locId);
                $free = max(0.0, (float) $stock['quantity'] - $reservedAtLocation);
                if ($qty > $free + 0.0001) {
                    throw new DomainException("Requested " . number_format($qty, 2) . " exceeds unreserved stock at location (" . number_format($free, 2) . " free).");
                }

                $this->db->table('stock_reservations')->insert([
                    'document_type' => 'sales_order',
                    'document_id' => $salesOrderId,
                    'sales_order_id' => $salesOrderId,
                    'sales_order_line_id' => $salesOrderLineId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'warehouse_id' => (int) $stock['warehouse_id'],
                    'location_id' => $locId,
                    'reserved_quantity' => number_format($qty, 4, '.', ''),
                    'status' => 'active',
                    'created_by' => $userId > 0 ? $userId : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            $this->db->transCommit();
            $locCount = count($cleaned);
            return [
                'ok' => true,
                'message' => number_format($totalQuantity, 2) . " pcs reserved across {$locCount} location" . ($locCount === 1 ? '' : 's') . " for this sales order."
            ];
        } catch (DomainException $e) {
            $this->db->transRollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Stock multi-reservation failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Stock could not be reserved. Please try again.'];
        }
    }

    /** @return array{ok: bool, message: string} */
    public function reserveDirectStock(
        int $salesOrderId,
        int $salesOrderLineId,
        int $productId,
        ?int $variantId,
        int $locationId,
        float $quantity,
        int $userId
    ): array {
        if (! $this->isAvailable()) {
            return ['ok' => false, 'message' => 'Stock reservation migration has not been applied.'];
        }
        if ($salesOrderId <= 0 || $salesOrderLineId <= 0 || $productId <= 0 || $locationId <= 0 || $quantity <= 0) {
            return ['ok' => false, 'message' => 'Order line, material, location, and a positive quantity are required.'];
        }

        $this->db->transBegin();
        try {
            $line = $this->db->query(
                'SELECT sol.*, so.status AS order_status FROM sales_order_lines sol JOIN sales_orders so ON so.id = sol.sales_order_id WHERE sol.id = ? AND sol.sales_order_id = ? FOR UPDATE',
                [$salesOrderLineId, $salesOrderId]
            )->getRowArray();
            if (! $line) {
                throw new DomainException('Sales order line does not belong to this order.');
            }
            if (! in_array(strtolower((string) $line['order_status']), ['confirmed', 'processing', 'in_progress', 'partially_shipped'], true)) {
                throw new DomainException('Only an active confirmed sales order can reserve stock.');
            }

            // Direct line reservation limit: order qty - already shipped
            $readyMap = (new ReadyToShipService())->getLineReadiness($salesOrderId);
            $remaining = 0.0;
            foreach ($readyMap['lines'] ?? [] as $rLine) {
                if ((int)$rLine['sales_order_line_id'] === $salesOrderLineId) {
                    $remaining = max(0.0, (float)($rLine['remaining_qty'] ?? $line['quantity']));
                    break;
                }
            }
            
            $alreadyForLine = $this->lineReserved($salesOrderLineId, $productId, $variantId);
            if ($alreadyForLine + $quantity > $remaining + 0.0001) {
                throw new DomainException('Reservation exceeds the remaining required quantity for this line. Remaining limit: ' . number_format(max(0, $remaining - $alreadyForLine), 4));
            }

            $stock = $this->lockedStockRow($productId, $variantId, $locationId);
            if (! $stock || ! empty($stock['vendor_id'])) {
                throw new DomainException('Select an active internal stock location for this material.');
            }

            $reservedAtLocation = $this->locationReserved($productId, $variantId, $locationId);
            $free = max(0.0, (float) $stock['quantity'] - $reservedAtLocation);
            if ($quantity > $free + 0.0001) {
                throw new DomainException('Only ' . number_format($free, 4) . ' is unreserved at this location.');
            }

            $now = date('Y-m-d H:i:s');
            $this->db->table('stock_reservations')->insert([
                'document_type' => 'sales_order',
                'document_id' => $salesOrderId,
                'sales_order_id' => $salesOrderId,
                'sales_order_line_id' => $salesOrderLineId,
                'product_id' => $productId,
                'variant_id' => $variantId,
                'warehouse_id' => (int) $stock['warehouse_id'],
                'location_id' => $locationId,
                'reserved_quantity' => number_format($quantity, 4, '.', ''),
                'status' => 'active',
                'created_by' => $userId > 0 ? $userId : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->db->transCommit();
            return ['ok' => true, 'message' => number_format($quantity, 2) . ' pcs reserved for this sales order line.'];
        } catch (DomainException $e) {
            $this->db->transRollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Stock reservation failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Stock could not be reserved. Please try again.'];
        }
    }

    /** @return array{ok: bool, message: string} */
    public function releaseLineReservations(int $lineId, int $salesOrderId, int $userId): array
    {
        if (! $this->isAvailable() || $lineId <= 0 || $salesOrderId <= 0) {
            return ["ok" => false, "message" => "Invalid release request."];
        }

        $reservations = $this->db->table("stock_reservations")
            ->where("sales_order_line_id", $lineId)
            ->where("sales_order_id", $salesOrderId)
            ->where("status", "active")
            ->get()->getResultArray();

        if (empty($reservations)) {
            return ["ok" => false, "message" => "No active reservations found for this line."];
        }

        $releasedCount = 0;
        foreach ($reservations as $r) {
            // Check permission: must be creator or admin
            if ($r["created_by"] != $userId && !service("policy")->isAdmin()) {
                continue;
            }
            $this->release((int)$r["id"], $salesOrderId, $userId);
            $releasedCount++;
        }

        if ($releasedCount === 0) {
            return ["ok" => false, "message" => "No reservations were released (check permissions)."];
        }

        return ["ok" => true, "message" => "$releasedCount reservation(s) released successfully."];
    }

    public function release(int $reservationId, int $salesOrderId, int $userId): array
    {
        if (! $this->isAvailable() || $reservationId <= 0 || $salesOrderId <= 0) {
            return ['ok' => false, 'message' => 'Invalid reservation.'];
        }

        $this->db->transBegin();
        try {
            $row = $this->db->query(
                "SELECT * FROM stock_reservations WHERE id = ? AND (sales_order_id = ? OR document_id = ?) AND status = 'active' FOR UPDATE",
                [$reservationId, $salesOrderId, $salesOrderId]
            )->getRowArray();
            if (! $row) {
                throw new DomainException('Reservation was already released or does not belong to this order.');
            }
            
            // Security check: only creator or admin
            $isAdmin = false;
            if (function_exists('service')) {
                $isAdmin = service('policy')->isAdmin();
            }
            if (!$isAdmin && $userId > 0 && (int)$row['created_by'] !== $userId) {
                throw new DomainException('You are not authorized to release this reservation. Only the user who reserved it or an administrator can release it.');
            }

            $remaining = max(0.0, (float) $row['reserved_quantity'] - (float) $row['consumed_quantity'] - (float) $row['released_quantity']);
            $now = date('Y-m-d H:i:s');
            $this->db->table('stock_reservations')->where('id', $reservationId)->update([
                'released_quantity' => (float) $row['released_quantity'] + $remaining,
                'status' => 'released',
                'released_by' => $userId > 0 ? $userId : null,
                'release_reason' => 'Released from document',
                'released_at' => $now,
                'updated_at' => $now,
            ]);
            $this->db->transCommit();
            return ['ok' => true, 'message' => 'Stock reservation released.'];
        } catch (DomainException $e) {
            $this->db->transRollback();
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Stock reservation release failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Stock reservation could not be released. Please try again.'];
        }
    }

    public function consumeForExecution(int $salesOrderLineId, int $productId, ?int $variantId, int $locationId, float $quantity): void
    {
        if (! $this->isAvailable() || $quantity <= 0) {
            return;
        }

        $builder = $this->db->table('stock_reservations')
            ->where('sales_order_line_id', $salesOrderLineId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('status', 'active');
        $this->applyVariantFilter($builder, $variantId);
        $sql = $builder->orderBy('id', 'ASC')->getCompiledSelect();
        $rows = $this->db->query($sql . ' FOR UPDATE')->getResultArray();

        $left = $quantity;
        foreach ($rows as $row) {
            if ($left <= 0.0001) {
                break;
            }
            $remaining = max(0.0, (float) $row['reserved_quantity'] - (float) $row['consumed_quantity'] - (float) $row['released_quantity']);
            $take = min($remaining, $left);
            if ($take <= 0) {
                continue;
            }
            $newConsumed = (float) $row['consumed_quantity'] + $take;
            $isComplete = $newConsumed + (float) $row['released_quantity'] >= (float) $row['reserved_quantity'] - 0.0001;
            $this->db->table('stock_reservations')->where('id', (int) $row['id'])->update([
                'consumed_quantity' => number_format($newConsumed, 4, '.', ''),
                'status' => $isComplete ? 'consumed' : 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $left -= $take;
        }
    }

    public function restoreForExecution(int $salesOrderLineId, int $productId, ?int $variantId, int $locationId, float $quantity): void
    {
        if (! $this->isAvailable() || $quantity <= 0) {
            return;
        }

        $builder = $this->db->table('stock_reservations')
            ->where('sales_order_line_id', $salesOrderLineId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('consumed_quantity >', 0);
        $this->applyVariantFilter($builder, $variantId);
        $sql = $builder->orderBy('id', 'DESC')->getCompiledSelect();
        $rows = $this->db->query($sql . ' FOR UPDATE')->getResultArray();

        $left = $quantity;
        foreach ($rows as $row) {
            if ($left <= 0.0001) {
                break;
            }
            $consumed = (float) $row['consumed_quantity'];
            $giveBack = min($consumed, $left);
            if ($giveBack <= 0) {
                continue;
            }
            $newConsumed = max(0.0, $consumed - $giveBack);
            $this->db->table('stock_reservations')->where('id', (int) $row['id'])->update([
                'consumed_quantity' => number_format($newConsumed, 4, '.', ''),
                'status' => 'active',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $left -= $giveBack;
        }
    }

    public function reservationsForOrder(int $salesOrderId): array
    {
        if (! $this->isAvailable()) {
            return [];
        }
        return $this->db->table('stock_reservations sr')
            ->select('sr.*, p.name AS product_name, pv.name AS variant_name, w.name AS warehouse_name, wl.name AS location_name, so.order_number')
            ->join('products p', 'p.id = sr.product_id', 'left')
            ->join('product_variants pv', 'pv.id = sr.variant_id', 'left')
            ->join('warehouses w', 'w.id = sr.warehouse_id', 'left')
            ->join('warehouse_locations wl', 'wl.id = sr.location_id', 'left')
            ->join('sales_orders so', 'so.id = sr.sales_order_id', 'left')
            ->where('sr.sales_order_id', $salesOrderId)
            ->where('sr.status', 'active')
            ->orderBy('sr.id', 'ASC')->get()->getResultArray();
    }

    private function materialRequirement(array $line, int $productId, ?int $variantId): float
    {
        $profiles = (new PreparationProfileModel())->getForProductOrVariant(
            (int) $line['product_id'],
            (int) ($line['product_variant_id'] ?? 0),
            true
        );
        $profile = $profiles[0] ?? null;
        if (! $profile) {
            return 0.0;
        }
        foreach ((new PreparationComponentModel())->getByProfile((int) $profile['id']) as $component) {
            $componentVariant = (int) ($component['variant_id'] ?? 0) ?: null;
            if ((int) $component['product_id'] === $productId && $componentVariant === $variantId && empty($component['is_optional'])) {
                $startedQty = 0.0;
                if ($this->db->tableExists('processing_records')) {
                    $firstStep = $this->db->table('preparation_steps')->select('id')
                        ->where('profile_id', (int) $profile['id'])->orderBy('step_order', 'ASC')->orderBy('id', 'ASC')->get(1)->getRowArray();
                    if ($firstStep) {
                        $started = $this->db->table('processing_records')
                            ->select('COALESCE(SUM(qty), 0) AS qty', false)
                            ->where('sales_order_line_id', (int) $line['id'])
                            ->where('step_id', (int) $firstStep['id'])
                            ->whereIn('status', ['in_progress', 'ready_for_qc', 'completed'])
                            ->get()->getRowArray();
                        $startedQty = (float) ($started['qty'] ?? 0);
                    }
                }
                $remainingOutput = max(0.0, (float) $line['quantity'] - $startedQty);
                return round($remainingOutput * (float) $component['qty_per_unit'], 4);
            }
        }
        return 0.0;
    }

    private function lockedStockRow(int $productId, ?int $variantId, int $locationId): ?array
    {
        $variantSql = $variantId !== null ? 'sb.variant_id = ?' : 'sb.variant_id IS NULL';
        $params = $variantId !== null ? [$productId, $locationId, $variantId] : [$productId, $locationId];
        return $this->db->query(
            "SELECT sb.warehouse_id, sb.quantity, wl.vendor_id FROM stock_balances sb JOIN warehouse_locations wl ON wl.id = sb.location_id WHERE sb.product_id = ? AND sb.location_id = ? AND {$variantSql} FOR UPDATE",
            $params
        )->getRowArray() ?: null;
    }

    private function locationReserved(int $productId, ?int $variantId, int $locationId, ?int $salesOrderLineId = null): float
    {
        if (! $this->isAvailable()) {
            return 0.0;
        }
        $builder = $this->db->table('stock_reservations')
            ->select('COALESCE(SUM(reserved_quantity - consumed_quantity - released_quantity), 0) AS qty', false)
            ->where('product_id', $productId)->where('location_id', $locationId)->where('status', 'active');
        $this->applyVariantFilter($builder, $variantId);
        if ($salesOrderLineId !== null) {
            $builder->where('sales_order_line_id', $salesOrderLineId);
        }
        return max(0.0, (float) ($builder->get()->getRowArray()['qty'] ?? 0));
    }

    public function lineReserved(int $lineId, int $productId, ?int $variantId): float
    {
        $builder = $this->db->table('stock_reservations')
            ->select('COALESCE(SUM(reserved_quantity - consumed_quantity - released_quantity), 0) AS qty', false)
            ->where('sales_order_line_id', $lineId)->where('product_id', $productId)->where('status', 'active');
        $this->applyVariantFilter($builder, $variantId);
        return max(0.0, (float) ($builder->get()->getRowArray()['qty'] ?? 0));
    }

    /**
     * A preparation batch may include a small, explicitly reserved safety
     * overage. Without a reservation, execution remains capped at the order
     * requirement; with one, only the quantity held at the selected box may
     * extend that limit.
     */
    public static function executionQuantityLimit(float $remainingRequired, float $reservedAtLocation): float
    {
        return max(0.0, $remainingRequired, $reservedAtLocation);
    }

    private function locationReservationLabels(int $productId, ?int $variantId, int $locationId): array
    {
        if (! $this->isAvailable()) {
            return [];
        }
        $builder = $this->db->table('stock_reservations sr')
            ->select('sr.sales_order_id, so.order_number, SUM(sr.reserved_quantity - sr.consumed_quantity - sr.released_quantity) AS quantity', false)
            ->join('sales_orders so', 'so.id = sr.sales_order_id', 'left')
            ->where('sr.product_id', $productId)
            ->where('sr.location_id', $locationId)
            ->where('sr.status', 'active');
        $this->applyVariantFilter($builder, $variantId, 'sr.variant_id');
        return $builder->groupBy('sr.sales_order_id, so.order_number')->having('quantity >', 0)->get()->getResultArray();
    }

    private function applyVariantFilter($builder, ?int $variantId, string $field = 'variant_id'): void
    {
        if ($variantId !== null && $variantId > 0) {
            $builder->where($field, $variantId);
        } else {
            $builder->where($field . ' IS NULL', null, false);
        }
    }
}

