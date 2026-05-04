<?php

namespace App\Controllers;

use App\Services\InventoryService;
use Config\Database;
use InvalidArgumentException;
use RuntimeException;

class StockAdjustments extends BaseController
{
    // ─────────────────────────────────────────────────────────────────────────
    //  Index – render the adjustments UI
    // ─────────────────────────────────────────────────────────────────────────
    public function index()
    {
        $this->requireAuth();
        $db = Database::connect();

        $warehouses = $db->table('warehouses')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        // Load all locations up-front (JS cascades by warehouse)
        $locations = $db->table('warehouse_locations wl')
            ->select('wl.id, wl.name, wl.warehouse_id, wl.parent_id, w.name as warehouse_name')
            ->join('warehouses w', 'w.id = wl.warehouse_id', 'left')
            ->where('wl.is_active', 1)
            ->orderBy('wl.name', 'ASC')
            ->get()->getResultArray();

        return view('inventory/adjustments', [
            'warehouses' => $warehouses,
            'locations'  => $locations,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Product search  –  GET inventory/adjustments/product-search
    //  Returns product list with current on-hand stock at the given location
    // ─────────────────────────────────────────────────────────────────────────
    public function productSearch()
    {
        $this->requireAuth();

        $q           = trim((string)($this->request->getGet('q') ?? ''));
        $warehouseId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $locationId  = (int)($this->request->getGet('location_id') ?? 0);

        if ($q === '') {
            return $this->response->setJSON(['success' => true, 'data' => []]);
        }

        $db = Database::connect();

        // Helper – fetch current balance for an item_key at a location
        $getBalance = function (string $itemKey) use ($db, $warehouseId, $locationId): float {
            $qb = $db->table('stock_balances')->where('item_key', $itemKey);
            if ($warehouseId > 0) $qb->where('warehouse_id', $warehouseId);
            if ($locationId  > 0) $qb->where('location_id', $locationId);
            $row = $qb->select('SUM(quantity) as qty')->get()->getRowArray();
            return (float)($row['qty'] ?? 0.0);
        };

        $out = [];

        // ── Simple / consumable products ──
        $pBuilder = $db->table('products')
            ->select('id, name, sku, code, product_type')
            ->groupStart()
                ->like('name', $q)
                ->orLike('sku',  $q)
                ->orLike('code', $q)
            ->groupEnd();
        try {
            if ($db->getFieldNames('products') && in_array('detailed_type', $db->getFieldNames('products'), true)) {
                $pBuilder->where('detailed_type', 'storable');
            }
        } catch (\Throwable $e) {}
        $pRows = $pBuilder->limit(12)->get()->getResultArray();

        foreach ($pRows as $r) {
            $isVariable = ($r['product_type'] ?? 'simple') === 'variable';
            if ($isVariable) continue; // variable products are returned per-variant below
            $code    = $r['code'] ?? ($r['sku'] ?? '');
            $itemKey = 'p' . $r['id'];
            $out[] = [
                'product_id'    => (int)$r['id'],
                'variant_id'    => null,
                'item_key'      => $itemKey,
                'text'          => ($r['name'] ?? '') . ($code ? ' (' . $code . ')' : ''),
                'current_stock' => $getBalance($itemKey),
            ];
        }

        // ── Variants ──
        try {
            $vBuilder = $db->table('product_variants pv')
                ->select('pv.id as variant_id, pv.art_number, pv.name as variant_name, p.id as product_id, p.name as product_name')
                ->join('products p', 'p.id = pv.product_id', 'left')
                ->groupStart()
                    ->like('pv.art_number', $q)
                    ->orLike('pv.name',       $q)
                    ->orLike('p.name',         $q)
                ->groupEnd();
            try {
                if (in_array('detailed_type', $db->getFieldNames('products'), true)) {
                    $vBuilder->where('p.detailed_type', 'storable');
                }
            } catch (\Throwable $e) {}
            $vRows = $vBuilder->limit(12)->get()->getResultArray();

            foreach ($vRows as $vr) {
                $itemKey = 'v' . $vr['variant_id'];
                $txt = ($vr['product_name'] ?? '') . ' — ' . ($vr['variant_name'] ?? '');
                if (!empty($vr['art_number'])) $txt .= ' (' . $vr['art_number'] . ')';
                $out[] = [
                    'product_id'    => (int)$vr['product_id'],
                    'variant_id'    => (int)$vr['variant_id'],
                    'item_key'      => $itemKey,
                    'text'          => trim($txt),
                    'current_stock' => $getBalance($itemKey),
                ];
            }
        } catch (\Throwable $e) { /* variants table may not exist in all installs */ }

        // De-duplicate by item_key + limit
        $seen = [];
        $final = [];
        foreach ($out as $row) {
            $k = $row['item_key'];
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $final[] = $row;
            if (count($final) >= 20) break;
        }

        return $this->response->setJSON(['success' => true, 'data' => $final]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Save batch  –  POST inventory/adjustments/save
    // ─────────────────────────────────────────────────────────────────────────
    public function save()
    {
        $this->requireAuth();

        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'message' => 'Method not allowed.']);
        }

        $body = $this->request->getJSON(true) ?: [];

        $warehouseId   = (int)($body['warehouse_id'] ?? 0);
        $locationId    = (int)($body['location_id']  ?? 0);
        $mode          = trim((string)($body['mode']  ?? 'add'));
        $movementType  = trim((string)($body['adjustment_type'] ?? 'adjustment'));
        $notes         = trim((string)($body['notes'] ?? '')) ?: null;
        $rawLines      = $body['lines'] ?? [];

        if (!in_array($movementType, ['opening_stock', 'adjustment'], true)) {
            $movementType = 'adjustment';
        }

        if (!is_array($rawLines) || empty($rawLines)) {
            return $this->response->setJSON(['success' => false, 'message' => 'No product lines provided.']);
        }

        // Sanitise lines
        $lines = [];
        $zeroReasonAllowed = ['damaged', 'expired', 'missing', 'count_correction', 'qc_reject', 'custom'];
        foreach ($rawLines as $i => $l) {
            $pid    = (int)($l['product_id'] ?? 0);
            $vid    = isset($l['variant_id']) && $l['variant_id'] ? (int)$l['variant_id'] : null;
            $qty    = (float)($l['qty'] ?? 0);
            $cost   = isset($l['unit_cost']) && $l['unit_cost'] !== null && $l['unit_cost'] !== ''
                    ? (float)$l['unit_cost'] : null;
            $source = isset($l['stock_source']) && $l['stock_source'] !== ''
                    ? trim((string)$l['stock_source']) : null;
            $vendorId = isset($l['possible_vendor_id']) && $l['possible_vendor_id']
                    ? (int)$l['possible_vendor_id'] : null;
            $zeroReasonCode = isset($l['zero_reason_code']) && $l['zero_reason_code'] !== ''
                    ? trim((string)$l['zero_reason_code']) : null;
            $zeroReasonCustom = isset($l['zero_reason_custom']) && $l['zero_reason_custom'] !== ''
                    ? trim((string)$l['zero_reason_custom']) : null;

            if ($pid <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Line ' . ($i + 1) . ': product is required.',
                ]);
            }
            if ($qty < 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Line ' . ($i + 1) . ': negative quantity is not allowed.',
                ]);
            }
            if ($mode !== 'set' && $qty <= 0) {
                return $this->response->setJSON([
                    'success' => false,
                    'message' => 'Line ' . ($i + 1) . ': quantity must be greater than zero.',
                ]);
            }
            if ($mode === 'set' && abs($qty) < 0.000001) {
                if (empty($zeroReasonCode) || !in_array($zeroReasonCode, $zeroReasonAllowed, true)) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Line ' . ($i + 1) . ': reason is required when setting quantity to zero.',
                    ]);
                }
                if ($zeroReasonCode === 'custom' && empty($zeroReasonCustom)) {
                    return $this->response->setJSON([
                        'success' => false,
                        'message' => 'Line ' . ($i + 1) . ': custom reason is required for zero quantity.',
                    ]);
                }
            }
            if ($movementType === 'opening_stock') {
                if (empty($source)) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Line ' . ($i + 1) . ': stock source is required for opening stock.']);
                }
                if ($cost === null || $cost <= 0) {
                    return $this->response->setJSON(['success' => false, 'message' => 'Line ' . ($i + 1) . ': estimated unit cost must be greater than zero for opening stock.']);
                }
            }
            $lines[] = [
                'product_id' => $pid,
                'variant_id' => $vid,
                'qty' => $qty,
                'unit_cost' => $cost,
                'stock_source' => $source,
                'possible_vendor_id' => $vendorId,
                'zero_reason_code' => $zeroReasonCode,
                'zero_reason_custom' => $zeroReasonCustom,
            ];
        }

        $userId = $this->getCurrentUserId();

        try {
            $service = new InventoryService();
            $result  = $service->processBatchAdjustment(
                $warehouseId,
                $locationId,
                $lines,
                $mode,
                $movementType,
                $userId,
                $notes
            );
        } catch (InvalidArgumentException $e) {
            return $this->response->setJSON(['success' => false, 'message' => $e->getMessage()]);
        } catch (RuntimeException $e) {
            log_message('error', 'StockAdjustments::save RuntimeException: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'Failed to save adjustment: ' . $e->getMessage()]);
        } catch (\Throwable $e) {
            log_message('error', 'StockAdjustments::save unexpected: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An unexpected error occurred.']);
        }

        return $this->response->setJSON([
            'success'  => true,
            'message'  => 'Stock adjustment saved — ' . count($result['results']) . ' line(s) processed.',
            'batch_id' => $result['batch_id'],
            'results'  => $result['results'],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  History  –  GET inventory/adjustments/history
    // ─────────────────────────────────────────────────────────────────────────
    public function history()
    {
        $this->requireAuth();

        $db   = Database::connect();
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $per  = max(10, min(100, (int)($this->request->getGet('per_page') ?? 20)));

        // Check batch table exists
        try {
            $exists = $db->query("SHOW TABLES LIKE 'stock_adjustment_batches'")->getNumRows() > 0;
        } catch (\Throwable $e) {
            $exists = false;
        }

        if (!$exists) {
            return $this->response->setJSON(['success' => true, 'data' => [], 'total' => 0]);
        }

        $total = (int)$db->table('stock_adjustment_batches')->countAllResults();
        $batches = $db->table('stock_adjustment_batches sab')
            ->select('sab.*, w.name as warehouse_name, wl.name as location_name, u.username as user_name')
            ->join('warehouses w',          'w.id  = sab.warehouse_id', 'left')
            ->join('warehouse_locations wl','wl.id = sab.location_id',  'left')
            ->join('users u',               'u.id  = sab.created_by',   'left')
            ->orderBy('sab.created_at', 'DESC')
            ->limit($per, ($page - 1) * $per)
            ->get()->getResultArray();

        // Attach line summaries
        foreach ($batches as &$b) {
            try {
                $lines = $db->table('stock_movements sm')
                    ->select('sm.qty_change, sm.unit_cost, sm.stock_source, sm.product_id, sm.variant_id, p.name as product_name, pv.name as variant_name, pv.art_number')
                    ->join('products p',         'p.id  = sm.product_id',  'left')
                    ->join('product_variants pv','pv.id = sm.variant_id',  'left')
                    ->where('sm.reference_type', 'stock_adjustment')
                    ->where('sm.reference_id',   (int)$b['id'])
                    ->get()->getResultArray();
                $b['lines'] = $lines;
            } catch (\Throwable $e) {
                $b['lines'] = [];
            }
        }
        unset($b);

        return $this->response->setJSON([
            'success' => true,
            'data'    => $batches,
            'total'   => $total,
            'page'    => $page,
            'per_page'=> $per,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Product Vendors JSON  –  GET inventory/adjustments/product-vendors?product_id=N
    //  Returns active vendors linked to a product via product_vendors table.
    // ─────────────────────────────────────────────────────────────────────────
    public function productVendors()
    {
        $this->requireAuth();

        $productId = (int)($this->request->getGet('product_id') ?? 0);
        if ($productId <= 0) {
            return $this->response->setJSON(['success' => true, 'vendors' => []]);
        }

        $db = \Config\Database::connect();
        $rows = $db->table('product_vendors pv')
            ->select('v.id, v.name, pv.last_cost')
            ->join('vendors v', 'v.id = pv.vendor_id', 'inner')
            ->where('pv.product_id', $productId)
            ->where('pv.is_active',  1)
            ->where('v.is_active',   1)
            ->orderBy('v.name', 'ASC')
            ->get()->getResultArray();

        $vendors = array_values(array_map(fn($r) => [
            'id'        => (int)$r['id'],
            'name'      => $r['name'],
            'last_cost' => $r['last_cost'] !== null ? (float)$r['last_cost'] : null,
        ], $rows));

        return $this->response->setJSON(['success' => true, 'vendors' => $vendors]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Vendor Search JSON  –  GET inventory/adjustments/vendor-search?q=...&product_id=N
    //  Returns ALL active vendors. Vendors linked to the given product are
    //  flagged is_linked=true and sorted to the top.
    // ─────────────────────────────────────────────────────────────────────────
    public function vendorSearch()
    {
        $this->requireAuth();

        $q         = trim((string)($this->request->getGet('q') ?? ''));
        $productId = (int)($this->request->getGet('product_id') ?? 0);

        $db = \Config\Database::connect();

        // IDs of vendors linked to this product
        $linkedIds = [];
        if ($productId > 0) {
            $linked = $db->table('product_vendors')
                ->select('vendor_id')
                ->where('product_id', $productId)
                ->where('is_active',  1)
                ->get()->getResultArray();
            $linkedIds = array_column($linked, 'vendor_id');
        }

        $builder = $db->table('vendors')
            ->select('id, name')
            ->where('is_active', 1);

        if ($q !== '') {
            $builder->like('name', $q, 'both');
        }

        $rows = $builder->orderBy('name', 'ASC')->limit(40)->get()->getResultArray();

        $vendors = array_map(function ($r) use ($linkedIds) {
            return [
                'id'        => (int)$r['id'],
                'name'      => $r['name'],
                'is_linked' => in_array((int)$r['id'], $linkedIds),
            ];
        }, $rows);

        // Linked vendors first, then alphabetical
        usort($vendors, fn($a, $b) =>
            ($b['is_linked'] <=> $a['is_linked']) ?: strcmp($a['name'], $b['name'])
        );

        return $this->response->setJSON(['success' => true, 'vendors' => $vendors]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Warehouses + Locations JSON  –  GET inventory/adjustments/warehouses
    //  Used by the Quick Adjust modal on the Products & Variants list views.
    // ─────────────────────────────────────────────────────────────────────────
    public function warehouses()
    {
        $this->requireAuth();
        $db = \Config\Database::connect();

        $warehouses = $db->table('warehouses')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        $locations = $db->table('warehouse_locations')
            ->select('id, name, warehouse_id, parent_id')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();

        return $this->response->setJSON([
            'success'    => true,
            'warehouses' => array_values(array_map(fn($w) => ['id' => (int)$w['id'], 'name' => $w['name']], $warehouses)),
            'locations'  => array_values(array_map(fn($l) => [
                'id'           => (int)$l['id'],
                'name'         => $l['name'],
                'warehouse_id' => (int)$l['warehouse_id'],
                'parent_id'    => isset($l['parent_id']) && $l['parent_id'] ? (int)$l['parent_id'] : null,
            ], $locations)),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Stock Balance  –  GET inventory/adjustments/stock-balance
    //  Returns on-hand qty for a specific product/variant at a location.
    // ─────────────────────────────────────────────────────────────────────────
    public function stockBalance()
    {
        $this->requireAuth();

        $productId   = (int)($this->request->getGet('product_id')   ?? 0);
        $variantId   = $this->request->getGet('variant_id')  ? (int)$this->request->getGet('variant_id')  : null;
        $warehouseId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $locationId  = (int)($this->request->getGet('location_id')  ?? 0);

        if ($productId <= 0 || $warehouseId <= 0 || $locationId <= 0) {
            return $this->response->setJSON(['success' => true, 'qty' => 0.0]);
        }

        $itemKey = $variantId ? ('v' . $variantId) : ('p' . $productId);
        $db      = \Config\Database::connect();

        $row = $db->table('stock_balances')
            ->select('SUM(quantity) as qty')
            ->where('warehouse_id', $warehouseId)
            ->where('location_id',  $locationId)
            ->where('item_key',     $itemKey)
            ->get()->getRowArray();

        return $this->response->setJSON(['success' => true, 'qty' => (float)($row['qty'] ?? 0.0)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  Helper: get current authenticated user ID
    // ─────────────────────────────────────────────────────────────────────────
    private function getCurrentUserId(): int
    {
        try {
            $session = session();
            return (int)($session->get('user_id') ?? $session->get('id') ?? 0);
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
