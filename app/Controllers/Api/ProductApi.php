<?php

namespace App\Controllers\Api;

use App\Models\ProductModel;

/**
 * GET /api/products        — Paginated product list
 * GET /api/products/{id}   — Single product detail
 */
class ProductApi extends BaseApiController
{
    /**
     * GET /api/products
     *
     * Query params:
     *   q        — Full-text search against name / code
     *   page     — Page number (default 1)
     *   per_page — Items per page (default 20, max 100)
     *   active   — Filter by is_active: 1 | 0 (omit for all)
     *   type     — Filter by product_type: simple | variable
     *
     * Response:
     *   {
     *     "success": true,
     *     "data": {
     *       "items": [...],
     *       "total": 123,
     *       "page": 1,
     *       "per_page": 20,
     *       "total_pages": 7
     *     },
     *     "message": "OK"
     *   }
     */
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('products', 'read')) {
            return $this->response;
        }

        $model = new ProductModel();

        // ---- Filters ----
        $q       = (string) ($this->request->getGet('q')       ?? '');
        $active  = $this->request->getGet('active');
        $type    = (string) ($this->request->getGet('type')    ?? '');
        $page    = max(1, (int) ($this->request->getGet('page')     ?? 1));
        $perPage = min(1000, max(1, (int) ($this->request->getGet('per_page') ?? 20)));

        if ($q !== '') {
            $model->groupStart()
                  ->like('name', $q)
                  ->orLike('code', $q)
                  ->orLike('sku', $q)
                  ->groupEnd();
        }

        if ($active !== null && $active !== '') {
            $model->where('is_active', (int) $active);
        }

        if ($type !== '') {
            $model->where('product_type', $type);
        }

        // ---- Pagination ----
        $total  = $model->countAllResults(false); // false = keep WHERE constraints
        $offset = ($page - 1) * $perPage;
        $items  = $model->select('products.id, products.name, products.code, products.sku, products.barcode, products.unit, products.product_type, products.detailed_type, products.is_active, products.sale_price, products.sale_currency, products.cost_price, products.cost_currency, products.current_stock, products.vendor_id, products.created_at, products.updated_at,
                       (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = products.id) AS variant_count', false)
                        ->orderBy('products.name', 'ASC')
                        ->findAll($perPage, $offset);

        // Cast numeric fields — CodeIgniter returns all DB values as strings
        $items = array_map(static function (array $row): array {
            $row['id']            = (int)   $row['id'];
            $row['is_active']     = (int)   $row['is_active'];
            $row['sale_price']    = (float) ($row['sale_price']  ?? 0);
            $row['cost_price']    = (float) ($row['cost_price']  ?? 0);
            $row['current_stock'] = (int)   ($row['current_stock'] ?? 0);
            $row['vendor_id']     = isset($row['vendor_id']) ? (int) $row['vendor_id'] : null;
            $row['variant_count'] = (int)   ($row['variant_count'] ?? 0);
            return $row;
        }, $items);

        // ---- Optionally include variants inline (for create-form dropdowns) ----
        $includeVariants = $this->request->getGet('include_variants');
        if ($includeVariants) {
            $db = \Config\Database::connect();
            foreach ($items as &$item) {
                if ($item['variant_count'] > 0) {
                    $rows = $db->query(
                        "SELECT pv.id, pv.art_number, pv.name, pv.sale_price, pv.cost_price,
                                pv.sale_currency, pv.cost_currency
                         FROM product_variants pv
                         WHERE pv.product_id = ?
                         ORDER BY pv.name ASC
                         LIMIT 500",
                        [$item['id']]
                    )->getResultArray();

                    $item['variants'] = array_map(static function (array $v): array {
                        return [
                            'id'             => (int)   $v['id'],
                            'art_number'     => $v['art_number'],
                            'name'           => $v['name'],
                            'sale_price'     => (float) ($v['sale_price'] ?? 0),
                            'cost_price'     => (float) ($v['cost_price'] ?? 0),
                            'sale_currency'  => $v['sale_currency'] ?? 'PKR',
                            'cost_currency'  => $v['cost_currency'] ?? 'PKR',
                        ];
                    }, $rows);
                } else {
                    $item['variants'] = [];
                }
            }
            unset($item);
        }

        return $this->success([
            'items'       => $items,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ]);
    }

    /**
     * GET /api/products/{id}
     *
     * Returns a single product including variants and inventory.
     */
    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('products', 'read')) {
            return $this->response;
        }

        $db    = \Config\Database::connect();
        $model = new ProductModel();
        $product = $model->find($id);

        if ($product === null) {
            return $this->error('Product not found.', 404);
        }

        // Cast base product numerics
        $product['id']            = (int)   $product['id'];
        $product['is_active']     = (int)   ($product['is_active'] ?? 1);
        $product['sale_price']    = (float) ($product['sale_price'] ?? 0);
        $product['cost_price']    = (float) ($product['cost_price'] ?? 0);
        $product['current_stock'] = (float) ($product['current_stock'] ?? 0);
        $product['vendor_id']     = isset($product['vendor_id']) ? (int) $product['vendor_id'] : null;
        unset($product['attributes_definitions'], $product['excluded_combos']);

        // Helper: SQL fragment to build full location path (up to 4 levels deep)
        // l0 = leaf location, walking up via parent_id
        $pathExpr = "CONCAT_WS(' > ', w.name, l3.name, l2.name, l1.name, l0.name)";
        $locJoins = "JOIN warehouses w ON w.id = sb.warehouse_id
                 JOIN warehouse_locations l0 ON l0.id = sb.location_id
                 LEFT JOIN warehouse_locations l1 ON l1.id = l0.parent_id
                 LEFT JOIN warehouse_locations l2 ON l2.id = l1.parent_id
                 LEFT JOIN warehouse_locations l3 ON l3.id = l2.parent_id";

        // Variants with stock totals
        $variants = [];
        if ($product['product_type'] === 'variable') {
            $rows = $db->query(
                "SELECT pv.id, pv.art_number, pv.name, pv.sale_price, pv.cost_price,
                        pv.sale_currency, pv.cost_currency, pv.attributes,
                        COALESCE(SUM(vi.quantity), 0) AS total_stock,
                        COALESCE(SUM(vi.reserved), 0) AS reserved_stock
                 FROM product_variants pv
                 LEFT JOIN variant_inventory vi ON vi.variant_id = pv.id
                 WHERE pv.product_id = ?
                 GROUP BY pv.id
                 ORDER BY pv.name ASC
                 LIMIT 200",
                [$id]
            )->getResultArray();

            foreach ($rows as $row) {
                $attrs = null;
                if (!empty($row['attributes'])) {
                    $decoded = json_decode($row['attributes'], true);
                    $attrs = is_array($decoded) ? $decoded : null;
                }
                $variants[] = [
                    'id'             => (int)   $row['id'],
                    'art_number'     => $row['art_number'],
                    'name'           => $row['name'],
                    'sale_price'     => (float) ($row['sale_price'] ?? 0),
                    'cost_price'     => (float) ($row['cost_price'] ?? 0),
                    'sale_currency'  => $row['sale_currency'] ?? 'PKR',
                    'cost_currency'  => $row['cost_currency'] ?? 'PKR',
                    'attributes'     => $attrs,
                    'total_stock'    => (float) $row['total_stock'],
                    'reserved_stock' => (float) $row['reserved_stock'],
                    'available'      => (float) $row['total_stock'] - (float) $row['reserved_stock'],
                    'locations'      => [],
                ];
            }

            // Per-variant location paths from stock_balances
            if (!empty($variants)) {
                $locRows = $db->query(
                    "SELECT sb.variant_id,
                            {$pathExpr} AS location_path,
                            sb.quantity
                     FROM stock_balances sb
                     {$locJoins}
                     WHERE sb.product_id = ? AND sb.variant_id IS NOT NULL
                     ORDER BY location_path ASC",
                    [$id]
                )->getResultArray();

                // Index variants by id for fast lookup
                $varIdx = [];
                foreach ($variants as $i => $v) {
                    $varIdx[$v['id']] = $i;
                }
                foreach ($locRows as $lr) {
                    $vid = (int) $lr['variant_id'];
                    if (isset($varIdx[$vid])) {
                        $variants[$varIdx[$vid]]['locations'][] = [
                            'location_path' => $lr['location_path'],
                            'quantity'      => (float) $lr['quantity'],
                            'reserved'      => 0.0,
                            'available'     => (float) $lr['quantity'],
                        ];
                    }
                }
            }
        }

        // Inventory by physical location (full path) — used in Overview & Inventory tabs
        $inventory = [];
        if ($product['product_type'] === 'variable') {
            $invRows = $db->query(
                "SELECT {$pathExpr} AS location_path,
                        COALESCE(w.name, 'N/A') AS warehouse,
                        SUM(sb.quantity) AS quantity,
                        0 AS reserved
                 FROM stock_balances sb
                 {$locJoins}
                 JOIN product_variants pv ON pv.id = sb.variant_id
                 WHERE sb.product_id = ?
                 GROUP BY sb.location_id
                 ORDER BY location_path ASC",
                [$id]
            )->getResultArray();

            foreach ($invRows as $row) {
                $qty = (float) $row['quantity'];
                $res = (float) $row['reserved'];
                $inventory[] = [
                    'warehouse'     => $row['warehouse'],
                    'location_path' => $row['location_path'],
                    'quantity'      => $qty,
                    'reserved'      => $res,
                    'available'     => $qty - $res,
                ];
            }

            // Fallback to variant totals if no stock_balances rows
            if (empty($inventory)) {
                $totals = array_reduce($variants, function ($carry, $v) {
                    $carry['qty'] += $v['total_stock'];
                    $carry['res'] += $v['reserved_stock'];
                    return $carry;
                }, ['qty' => 0.0, 'res' => 0.0]);
                if ($totals['qty'] > 0 || count($variants) > 0) {
                    $inventory[] = [
                        'warehouse'     => 'N/A',
                        'location_path' => 'N/A',
                        'quantity'      => $totals['qty'],
                        'reserved'      => $totals['res'],
                        'available'     => $totals['qty'] - $totals['res'],
                    ];
                }
            }
        } elseif ($product['product_type'] === 'simple') {
            $invRows = $db->query(
                "SELECT {$pathExpr} AS location_path,
                        COALESCE(w.name, 'N/A') AS warehouse,
                        sb.quantity, 0 AS reserved, sb.quantity AS available
                 FROM stock_balances sb
                 {$locJoins}
                 WHERE sb.product_id = ? AND sb.variant_id IS NULL
                 ORDER BY location_path ASC",
                [$id]
            )->getResultArray();

            foreach ($invRows as $row) {
                $inventory[] = [
                    'warehouse'     => $row['warehouse'],
                    'location_path' => $row['location_path'],
                    'quantity'      => (float) $row['quantity'],
                    'reserved'      => (float) $row['reserved'],
                    'available'     => (float) $row['available'],
                ];
            }
            if (empty($inventory)) {
                $inventory[] = [
                    'warehouse'     => 'N/A',
                    'location_path' => 'N/A',
                    'quantity'      => (float) $product['current_stock'],
                    'reserved'      => 0.0,
                    'available'     => (float) $product['current_stock'],
                ];
            }
        }

        $product['variants']       = $variants;
        $product['variant_count']  = count($variants);
        $product['inventory']      = $inventory;

        return $this->success($product);
    }
}
