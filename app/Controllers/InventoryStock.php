<?php

namespace App\Controllers;

use Config\Database;

class InventoryStock extends BaseController
{
    /**
     * Check whether the products table has a given column.
     */
    private function productsHasColumn(string $column): bool
    {
        try {
            $db = Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `products` LIKE '" . $db->escapeString($column) . "'");
            return $result && $result->getNumRows() > 0;
        } catch (\Throwable $e) {
            log_message('error', 'InventoryStock::productsHasColumn check failed: ' . $e->getMessage());
            return false;
        }
    }

    // Focused stock view for a single product (optionally a variant), grouped by warehouse/location.
    public function product($productId = null)
    {
        if (strtolower($this->request->getMethod()) !== 'get') {
            return $this->response->setStatusCode(405);
        }

        $productId = (int)$productId;
        if ($productId <= 0) {
            return $this->response->setStatusCode(400)->setBody('Invalid product id');
        }

        $variantId = (int)($this->request->getGet('variant_id') ?? 0);
        $db = Database::connect();
        $hasVariantStock = $this->tableHasColumn('stock_balances', 'variant_id');

        $product = $db->table('products')
            ->select('id, name, sku, code, images, product_type')
            ->where('id', $productId)
            ->get()
            ->getRowArray();

        if (!$product) {
            return $this->response->setStatusCode(404)->setBody('Product not found');
        }

        $variant = null;
        if ($variantId > 0) {
            try {
                $variant = $db->table('product_variants')
                    ->select('id, product_id, art_number, name, attributes, image')
                    ->where('id', $variantId)
                    ->where('product_id', $productId)
                    ->get()
                    ->getRowArray();
                if (!$variant) {
                    $variantId = 0;
                }
            } catch (\Throwable $_) {
                $variantId = 0;
                $variant = null;
            }
        }

        $select = 'sb.warehouse_id, w.name as warehouse_name, sb.location_id, l.name as location_name, SUM(sb.quantity) as quantity';
        $builder = $db->table('stock_balances sb')
            ->select($select)
            ->join('warehouses w', 'w.id = sb.warehouse_id', 'left')
            ->join('warehouse_locations l', 'l.id = sb.location_id', 'left')
            ->where('sb.product_id', $productId)
            ->groupBy('sb.warehouse_id, sb.location_id')
            ->orderBy('w.name', 'ASC')
            ->orderBy('l.name', 'ASC');

        if ($hasVariantStock) {
            if ($variantId > 0) {
                $builder->where('sb.variant_id', $variantId);
            }
        }

        $rows = $builder->get()->getResultArray();

        $locs = $db->table('warehouse_locations')
            ->select('id, name, parent_id')
            ->orderBy('parent_id', 'ASC')
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $locMap = [];
        foreach ($locs as $loc) {
            $locMap[(int)$loc['id']] = $loc;
        }

        $buildPath = function ($locId) use (&$locMap) {
            $locId = (int)$locId;
            if ($locId <= 0 || !isset($locMap[$locId])) {
                return '';
            }
            $parts = [];
            $seen = [];
            while ($locId > 0 && isset($locMap[$locId]) && !in_array($locId, $seen, true)) {
                $seen[] = $locId;
                $parts[] = (string)($locMap[$locId]['name'] ?? '');
                $locId = (int)($locMap[$locId]['parent_id'] ?? 0);
            }
            return implode(' \\ ', array_reverse(array_filter($parts, static fn($v) => $v !== '')));
        };

        $totalQty = 0.0;
        foreach ($rows as &$row) {
            $row['quantity'] = isset($row['quantity']) ? (float)$row['quantity'] : 0.0;
            $row['location_path'] = $buildPath((int)($row['location_id'] ?? 0));
            $totalQty += $row['quantity'];
        }

        return view('inventory/product_stock', [
            'product' => $product,
            'variant' => $variant,
            'rows' => $rows,
            'totalQty' => $totalQty,
        ]);
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

    // Read-only stock view grouped by Warehouse -> Location -> Product
    public function index()
    {
        $method = strtolower($this->request->getMethod());
        if ($method !== 'get') { return $this->response->setStatusCode(405); }

        $db = Database::connect();

        $hasVariantStock = $this->tableHasColumn('stock_balances', 'variant_id');

        // Filters
        $warehouseId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $productSearch = trim((string)($this->request->getGet('product_search') ?? ''));
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = max(10, min(200, (int)($this->request->getGet('per_page') ?? 50)));

        // Multi-attribute filters compatible with products list format:
        // attr[0][name]=Shape&attr[0][value]=Curved
        $attributeFilters = [];
        $attrParamArray = $this->request->getGet('attr');
        if (!is_array($attrParamArray)) {
            $attrParamArray = [];
            $rawQuery = (string) ($this->request->getServer('QUERY_STRING') ?? '');
            if ($rawQuery !== '') {
                $parsed = [];
                parse_str($rawQuery, $parsed);
                if (isset($parsed['attr']) && is_array($parsed['attr'])) {
                    $attrParamArray = $parsed['attr'];
                }
            }
        }
        $seenAttrPairs = [];
        if (is_array($attrParamArray)) {
            foreach ($attrParamArray as $attrPair) {
                if (!is_array($attrPair)) {
                    continue;
                }
                $name = trim((string)($attrPair['name'] ?? ''));
                $value = trim((string)($attrPair['value'] ?? ''));
                if ($name === '' || $value === '') {
                    continue;
                }
                $dedupeKey = mb_strtolower($name) . '::' . mb_strtolower($value);
                if (isset($seenAttrPairs[$dedupeKey])) {
                    continue;
                }
                $seenAttrPairs[$dedupeKey] = true;
                $attributeFilters[] = ['name' => $name, 'value' => $value];
            }
        }

        // Build query: aggregate quantity from stock_balances only
        $select = "sb.warehouse_id, w.name as warehouse_name, sb.location_id, l.name as location_name, sb.product_id, p.name as product_name, p.sku as product_sku, p.images as product_images";
        if ($hasVariantStock) {
            $select .= ", sb.variant_id, pv.art_number as variant_art_number, pv.name as variant_name, pv.image as variant_image";
        }
        $select .= ", SUM(sb.quantity) as quantity";

        $builder = $db->table('stock_balances sb')
            ->select($select)
            ->join('warehouses w', 'w.id = sb.warehouse_id', 'left')
            ->join('warehouse_locations l', 'l.id = sb.location_id', 'left')
            ->join('products p', 'p.id = sb.product_id', 'left')
            ->groupBy($hasVariantStock ? 'sb.warehouse_id, sb.location_id, sb.product_id, sb.variant_id' : 'sb.warehouse_id, sb.location_id, sb.product_id')
            ->orderBy('w.name', 'ASC')
            ->orderBy('l.name', 'ASC')
            ->orderBy('p.name', 'ASC');

        if ($hasVariantStock) {
            $builder->join('product_variants pv', 'pv.id = sb.variant_id', 'left');
        }

        // Only storable products should appear in Inventory (Odoo-like detailed_type)
        if ($this->productsHasColumn('detailed_type')) {
            $builder->where('p.detailed_type', 'storable');
        }

        if ($warehouseId > 0) {
            $builder->where('sb.warehouse_id', $warehouseId);
        }

        if ($productSearch !== '') {
            // Deep search across product + variant fields
            if ($hasVariantStock) {
                $builder->where("(p.name LIKE " . $db->escape('%' . $productSearch . '%') . " OR p.sku LIKE " . $db->escape('%' . $productSearch . '%') . " OR p.code LIKE " . $db->escape('%' . $productSearch . '%') . " OR pv.art_number LIKE " . $db->escape('%' . $productSearch . '%') . " OR pv.name LIKE " . $db->escape('%' . $productSearch . '%') . " OR pv.attributes LIKE " . $db->escape('%' . $productSearch . '%') . ")", null, false);
            } else {
                $builder->where("(p.name LIKE " . $db->escape('%' . $productSearch . '%') . " OR p.sku LIKE " . $db->escape('%' . $productSearch . '%') . " OR p.code LIKE " . $db->escape('%' . $productSearch . '%') . ")", null, false);
            }
        }

        // Attribute filters: apply on variant JSON attributes (same behavior as products page)
        if (!empty($attributeFilters) && $hasVariantStock) {
            $builder->where('sb.variant_id IS NOT NULL', null, false);
            foreach ($attributeFilters as $filter) {
                $attrN = trim((string)($filter['name'] ?? ''));
                $attrV = trim((string)($filter['value'] ?? ''));
                if ($attrN === '' || $attrV === '') {
                    continue;
                }
                $nl = strtolower($db->escapeLikeString($attrN));
                $vl = strtolower($db->escapeLikeString($attrV));
                $attrExpr = "LOWER(REPLACE(pv.attributes, CHAR(92), ''))";
                $builder->where("{$attrExpr} LIKE '%{$nl}%' ESCAPE '!'", null, false)
                        ->where("{$attrExpr} LIKE '%{$vl}%' ESCAPE '!'", null, false);
            }
        }

        // For pagination we need total count of grouped rows. Use compiled select as subquery.
        $sqlForCount = $builder->getCompiledSelect(false);
        $countSql = "SELECT COUNT(*) as cnt FROM (" . $sqlForCount . ") tmpcnt";
        $total = (int)$db->query($countSql)->getRow()->cnt;

        $offset = ($page - 1) * $perPage;
        $rows = $builder->limit($perPage, $offset)->get()->getResultArray();

        // Fetch all warehouse locations to build full paths for display
        $locs = $db->table('warehouse_locations')->select('id, name, parent_id, warehouse_id')->orderBy('parent_id','ASC')->orderBy('name','ASC')->get()->getResultArray();
        $locMap = [];
        foreach ($locs as $l) { $locMap[$l['id']] = $l; }

        // Helper to build full path for a location id
        $buildPath = function($locId) use (&$locMap) {
            if (!$locId || !isset($locMap[$locId])) return '';
            $parts = [];
            $seen = [];
            while ($locId && isset($locMap[$locId]) && !in_array($locId, $seen)) {
                $seen[] = $locId;
                $parts[] = $locMap[$locId]['name'];
                $locId = $locMap[$locId]['parent_id'] ?? null;
            }
            return implode(' / ', array_reverse($parts));
        };

        // Attach full_path to rows
        foreach ($rows as &$r) {
            $r['location_path'] = $buildPath($r['location_id']);
            // ensure quantity is numeric
            $r['quantity'] = isset($r['quantity']) ? (float)$r['quantity'] : 0;
        }

        // Warehouse list for filter
        $warehouses = $db->table('warehouses')->orderBy('name','ASC')->get()->getResultArray();

        // Attribute options for guided multi-attribute filter UI
        $attributeRows = $db->table('product_attributes')
            ->select('name, values')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
        $attributeOptions = [];
        foreach ($attributeRows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $vals = [];
            try {
                $vals = json_decode((string)($row['values'] ?? '[]'), true) ?? [];
            } catch (\Throwable $_) {
                $vals = [];
            }
            if (!is_array($vals)) {
                $vals = [];
            }
            $cleanVals = [];
            $seen = [];
            foreach ($vals as $v) {
                $vv = trim((string)$v);
                if ($vv === '') {
                    continue;
                }
                $key = mb_strtolower($vv);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $cleanVals[] = $vv;
            }
            sort($cleanVals, SORT_NATURAL | SORT_FLAG_CASE);
            $attributeOptions[$name] = $cleanVals;
        }

        return view('inventory/stock', [
            'rows' => $rows,
            'warehouses' => $warehouses,
            'filters' => ['warehouse_id' => $warehouseId, 'product_search' => $productSearch, 'page' => $page, 'per_page' => $perPage, 'attributes' => $attributeFilters],
            'attribute_options' => $attributeOptions,
            'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage]
        ]);
    }

    // JSON API for dashboards (same aggregated data, supports pagination and filters)
    public function api()
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405); }
        $db = Database::connect();
        $warehouseId = (int)($this->request->getGet('warehouse_id') ?? 0);
        $productSearch = trim((string)($this->request->getGet('product_search') ?? ''));
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = max(10, min(500, (int)($this->request->getGet('per_page') ?? 100)));

        $builder = $db->table('stock_balances sb')
            ->select("sb.warehouse_id, sb.location_id, sb.product_id, SUM(sb.quantity) as quantity")
            ->join('products p', 'p.id = sb.product_id', 'left')
            ->groupBy('sb.warehouse_id, sb.location_id, sb.product_id')
            ->orderBy('sb.warehouse_id','ASC');

        // Only storable products should appear in Inventory
        if ($this->productsHasColumn('detailed_type')) {
            $builder->where('p.detailed_type', 'storable');
        }

        if ($warehouseId > 0) $builder->where('sb.warehouse_id', $warehouseId);
        if ($productSearch !== '') {
            $builder->where("(p.name LIKE " . $db->escape('%' . $productSearch . '%') . " OR p.sku LIKE " . $db->escape('%' . $productSearch . '%') . ")", null, false);
        }

        $sqlForCount = $builder->getCompiledSelect(false);
        $countSql = "SELECT COUNT(*) as cnt FROM (" . $sqlForCount . ") tmpcnt";
        $total = (int)$db->query($countSql)->getRow()->cnt;

        $offset = ($page - 1) * $perPage;
        $data = $builder->limit($perPage, $offset)->get()->getResultArray();

        return $this->response->setJSON(['success' => true, 'data' => $data, 'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage]]);
    }

    /**
     * Stock Journal / Movement History
     */
    public function journal()
    {
        if (strtolower($this->request->getMethod()) !== 'get') {
            return $this->response->setStatusCode(405);
        }

        $db = Database::connect();

        // Filters
        $productSearch = trim((string)($this->request->getGet('q') ?? ''));
        $movementType  = trim((string)($this->request->getGet('type') ?? ''));
        $dateFrom      = trim((string)($this->request->getGet('from') ?? ''));
        $dateTo        = trim((string)($this->request->getGet('to') ?? ''));
        $page          = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage       = 50;

        $hasVariant = $this->tableHasColumn('stock_movements', 'variant_id');

        $select = "sm.id, sm.product_id, sm.location_id, sm.qty_change, sm.movement_type, sm.reference_type, sm.reference_id, sm.created_by, sm.created_at, sm.unit_cost, sm.stock_source, sm.possible_vendor_id, sm.warehouse_id,"
            . " p.name as product_name, p.sku as product_sku, p.code as product_code,"
            . " wl.name as location_name, w.name as warehouse_name,"
            . " CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) as user_name";

        if ($hasVariant) {
            $select .= ", sm.variant_id, pv.name as variant_name, pv.art_number as variant_art";
        }

        $builder = $db->table('stock_movements sm')
            ->select($select)
            ->join('products p', 'p.id = sm.product_id', 'left')
            ->join('warehouse_locations wl', 'wl.id = sm.location_id', 'left')
            ->join('warehouses w', 'w.id = sm.warehouse_id', 'left')
            ->join('users u', 'u.id = sm.created_by', 'left');

        if ($hasVariant) {
            $builder->join('product_variants pv', 'pv.id = sm.variant_id', 'left');
        }

        // Apply filters
        if ($productSearch !== '') {
            $like = $db->escape('%' . $productSearch . '%');
            $clause = "(p.name LIKE {$like} OR p.sku LIKE {$like} OR p.code LIKE {$like}";
            if ($hasVariant) {
                $clause .= " OR pv.name LIKE {$like} OR pv.art_number LIKE {$like}";
            }
            $clause .= ")";
            $builder->where($clause, null, false);
        }
        if ($movementType !== '') {
            $builder->where('sm.movement_type', $movementType);
        }
        if ($dateFrom !== '') {
            $builder->where('sm.created_at >=', $dateFrom . ' 00:00:00');
        }
        if ($dateTo !== '') {
            $builder->where('sm.created_at <=', $dateTo . ' 23:59:59');
        }

        // Count
        $countSql = "SELECT COUNT(*) as cnt FROM (" . $builder->getCompiledSelect(false) . ") tmpcnt";
        $total = (int)$db->query($countSql)->getRow()->cnt;

        // Fetch page
        $offset = ($page - 1) * $perPage;
        $rows = $builder->orderBy('sm.created_at', 'DESC')->orderBy('sm.id', 'DESC')
            ->limit($perPage, $offset)->get()->getResultArray();

        // Build location paths
        $locs = $db->table('warehouse_locations')->select('id, name, parent_id')->orderBy('parent_id','ASC')->get()->getResultArray();
        $locMap = [];
        foreach ($locs as $l) { $locMap[$l['id']] = $l; }
        $buildPath = function($locId) use (&$locMap) {
            if (!$locId || !isset($locMap[$locId])) return '';
            $parts = []; $seen = [];
            while ($locId && isset($locMap[$locId]) && !in_array($locId, $seen)) {
                $seen[] = $locId;
                $parts[] = $locMap[$locId]['name'];
                $locId = $locMap[$locId]['parent_id'] ?? null;
            }
            return implode(' / ', array_reverse($parts));
        };
        foreach ($rows as &$r) {
            $r['location_path'] = $buildPath($r['location_id']);
        }

        // Vendor names for possible_vendor_id
        $vendorIds = array_filter(array_unique(array_column($rows, 'possible_vendor_id')));
        $vendorMap = [];
        if (!empty($vendorIds)) {
            $vRows = $db->table('vendors')->select('id, name')->whereIn('id', $vendorIds)->get()->getResultArray();
            foreach ($vRows as $v) { $vendorMap[$v['id']] = $v['name']; }
        }

        // Distinct movement types for filter dropdown
        $types = array_column($db->query("SELECT DISTINCT movement_type FROM stock_movements ORDER BY movement_type")->getResultArray(), 'movement_type');

        return view('inventory/journal', [
            'rows'       => $rows,
            'vendorMap'  => $vendorMap,
            'types'      => $types,
            'filters'    => ['q' => $productSearch, 'type' => $movementType, 'from' => $dateFrom, 'to' => $dateTo, 'page' => $page],
            'pagination' => ['total' => $total, 'page' => $page, 'per_page' => $perPage],
        ]);
    }

    // Product autocomplete endpoint used by UI
    public function product_autocomplete()
    {
        if (strtolower($this->request->getMethod()) !== 'get') { return $this->response->setStatusCode(405); }
        $q = trim((string)($this->request->getGet('q') ?? ''));
        if ($q === '') return $this->response->setJSON(['success' => true, 'data' => []]);
        $db = Database::connect();
        $out = [];

        // 1) Template products
        $pBuilder = $db->table('products')->select('id, name, sku, code');
        $pBuilder->groupStart()->like('name', $q)->orLike('sku', $q)->orLike('code', $q)->groupEnd();
        if ($this->productsHasColumn('detailed_type')) {
            $pBuilder->where('detailed_type', 'storable');
        }
        $pRows = $pBuilder->limit(10)->get()->getResultArray();
        foreach ($pRows as $r) {
            $code = $r['code'] ?? ($r['sku'] ?? '');
            $out[] = ['id' => $r['id'], 'text' => ($r['name'] ?? '') . ($code ? ' (' . $code . ')' : '')];
        }

        // 2) Variants (art number / name)
        try {
            $vBuilder = $db->table('product_variants pv')
                ->select('pv.id as variant_id, pv.art_number, pv.name as variant_name, p.id as product_id, p.name as product_name')
                ->join('products p', 'p.id = pv.product_id', 'left');
            $vBuilder->groupStart()->like('pv.art_number', $q)->orLike('pv.name', $q)->orLike('p.name', $q)->groupEnd();
            if ($this->productsHasColumn('detailed_type')) {
                $vBuilder->where('p.detailed_type', 'storable');
            }
            $vRows = $vBuilder->limit(10)->get()->getResultArray();
            foreach ($vRows as $vr) {
                $txt = ($vr['product_name'] ?? '') . ' — ' . ($vr['variant_name'] ?? '') . ' (' . ($vr['art_number'] ?? '') . ')';
                $out[] = ['id' => $vr['product_id'], 'text' => trim($txt)];
            }
        } catch (\Throwable $e) {
            // ignore if variants table missing
        }

        // de-dup by text
        $seen = [];
        $final = [];
        foreach ($out as $row) {
            $t = $row['text'] ?? '';
            if ($t === '' || isset($seen[$t])) continue;
            $seen[$t] = true;
            $final[] = $row;
            if (count($final) >= 20) break;
        }

        return $this->response->setJSON(['success' => true, 'data' => $final]);
    }
}
