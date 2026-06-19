<?php
namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\ProductVariantModel;
use App\Services\ArtNumberService;
use App\Services\SearchService;
use Config\Database;

class ProductVariants extends BaseController
{
    protected $variants;
    protected $artService;

    protected $exclusionService;

    public function __construct()
    {
        $this->variants = new ProductVariantModel();
        $this->artService = new ArtNumberService();
        $this->exclusionService = new \App\Services\VariantExclusionService();
    }

    private function variantsHasColumn(string $column): bool
    {
        try {
            $db = Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `product_variants` LIKE '" . $db->escapeString($column) . "'");
            return $result && $result->getNumRows() > 0;
        } catch (\Throwable $e) {
            log_message('error', 'variantsHasColumn check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureVariantsImageColumn(): bool
    {
        if ($this->variantsHasColumn('image')) {
            return true;
        }
        try {
            $db = Database::connect();
            $db->query("ALTER TABLE `product_variants` ADD COLUMN `image` VARCHAR(255) NULL");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Failed to add product_variants.image: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureSalesOrderLinesVariantColumn(): bool
    {
        try {
            $db = Database::connect();
            if (! $db->tableExists('sales_order_lines')) return false;
            $result = $db->query("SHOW COLUMNS FROM `sales_order_lines` LIKE 'product_variant_id'");
            if ($result && $result->getNumRows() > 0) return true;
            $db->query("ALTER TABLE `sales_order_lines` ADD COLUMN `product_variant_id` INT NULL AFTER `product_id`");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'ensureSalesOrderLinesVariantColumn failed: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureVariantsWeightColumn(): bool
    {
        if ($this->variantsHasColumn('weight')) {
            return true;
        }
        try {
            $db = Database::connect();
            $db->query("ALTER TABLE `product_variants` ADD COLUMN `weight` DECIMAL(15,4) NULL");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Failed to add product_variants.weight: ' . $e->getMessage());
            return false;
        }
    }

    private function normalizeAttributes($attrs): array
    {
        if (!is_array($attrs)) return [];
        $norm = [];
        foreach ($attrs as $k => $v) {
            $kk = trim((string)$k);
            if ($kk === '') continue;
            $vv = is_scalar($v) ? trim((string)$v) : trim(json_encode($v));
            if ($vv === '') continue;
            $norm[$kk] = $vv;
        }
        ksort($norm);
        return $norm;
    }

    private function combinationKeyFromAttributes(array $attrs): string
    {
        $sig = json_encode($this->normalizeAttributes($attrs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return sha1($sig ?: '{}');
    }

    private function allocateUniqueArtNumber(int $categoryId): string
    {
        // ArtNumberService increments global art counter; ensure uniqueness in product_variants.
        for ($i = 0; $i < 10; $i++) {
            $art = $this->artService->generateForCategory($categoryId);
            $exists = $this->variants->where('art_number', $art)->first();
            if (!$exists) return $art;
        }
        // Fallback: last generated value (should be unique unless data corrupted)
        return $this->artService->generateForCategory($categoryId);
    }

    private function firstVariantIdFromRow(array $row, array $columns): int
    {
        foreach ($columns as $col) {
            $v = (int)($row[$col] ?? 0);
            if ($v > 0) {
                return $v;
            }
        }
        return 0;
    }

    private function getVariantRemovalEligibility(array $variantIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $variantIds), static fn($v) => $v > 0)));
        $out = [];
        foreach ($ids as $id) {
            $out[$id] = ['can_remove' => true, 'reasons' => []];
        }
        if (empty($ids)) {
            return $out;
        }

        $db = Database::connect();
        $addReason = static function (array &$slot, string $reason): void {
            if (!in_array($reason, $slot['reasons'], true)) {
                $slot['reasons'][] = $reason;
            }
        };

        try {
            if ($db->tableExists('sales_order_lines')) {
                $salesColsAll = $db->getFieldNames('sales_order_lines');
                $salesCols = array_values(array_filter(['product_variant_id', 'variant_id'], static fn($c) => in_array($c, $salesColsAll ?? [], true)));
                if (!empty($salesCols)) {
                    $qb = $db->table('sales_order_lines')->select(implode(', ', $salesCols));
                    $qb->groupStart();
                    foreach ($salesCols as $i => $col) {
                        if ($i === 0) {
                            $qb->whereIn($col, $ids);
                        } else {
                            $qb->orWhereIn($col, $ids);
                        }
                    }
                    $qb->groupEnd();
                    $rows = $qb->get()->getResultArray();
                    foreach ($rows as $row) {
                        $vid = $this->firstVariantIdFromRow($row, $salesCols);
                        if ($vid > 0 && isset($out[$vid])) {
                            $addReason($out[$vid], 'linked in sales orders');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Variant eligibility sales-order check failed: ' . $e->getMessage());
        }

        try {
            if ($db->tableExists('purchase_order_lines')) {
                $poColsAll = $db->getFieldNames('purchase_order_lines');
                $poCols = array_values(array_filter(['variant_id', 'product_variant_id'], static fn($c) => in_array($c, $poColsAll ?? [], true)));
                if (!empty($poCols)) {
                    $qb = $db->table('purchase_order_lines')->select(implode(', ', $poCols));
                    $qb->groupStart();
                    foreach ($poCols as $i => $col) {
                        if ($i === 0) {
                            $qb->whereIn($col, $ids);
                        } else {
                            $qb->orWhereIn($col, $ids);
                        }
                    }
                    $qb->groupEnd();
                    $rows = $qb->get()->getResultArray();
                    foreach ($rows as $row) {
                        $vid = $this->firstVariantIdFromRow($row, $poCols);
                        if ($vid > 0 && isset($out[$vid])) {
                            $addReason($out[$vid], 'linked in purchase orders');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Variant eligibility purchase-order check failed: ' . $e->getMessage());
        }

        try {
            if ($db->tableExists('variant_inventory')) {
                $inv = $db->table('variant_inventory')
                    ->select('variant_id, COALESCE(SUM(quantity), 0) as qty, COALESCE(SUM(reserved), 0) as res', false)
                    ->whereIn('variant_id', $ids)
                    ->groupBy('variant_id')
                    ->get()->getResultArray();
                foreach ($inv as $row) {
                    $vid = (int)($row['variant_id'] ?? 0);
                    if ($vid <= 0 || !isset($out[$vid])) {
                        continue;
                    }
                    $qty = (float)($row['qty'] ?? 0);
                    $res = (float)($row['res'] ?? 0);
                    if (abs($qty) > 0.000001 || abs($res) > 0.000001) {
                        $addReason($out[$vid], 'has stock or reserved quantity');
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Variant eligibility inventory check failed: ' . $e->getMessage());
        }

        try {
            if ($db->tableExists('stock_balances')) {
                $sbCols = $db->getFieldNames('stock_balances');
                if (in_array('variant_id', $sbCols ?? [], true) && in_array('quantity', $sbCols ?? [], true)) {
                    $sb = $db->table('stock_balances')
                        ->select('variant_id, COALESCE(SUM(quantity), 0) as qty', false)
                        ->whereIn('variant_id', $ids)
                        ->groupBy('variant_id')
                        ->get()->getResultArray();
                    foreach ($sb as $row) {
                        $vid = (int)($row['variant_id'] ?? 0);
                        if ($vid <= 0 || !isset($out[$vid])) {
                            continue;
                        }
                        $qty = (float)($row['qty'] ?? 0);
                        if (abs($qty) > 0.000001) {
                            $addReason($out[$vid], 'has stock balance entries');
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Variant eligibility stock-balance check failed: ' . $e->getMessage());
        }

        foreach ($out as &$slot) {
            if (!empty($slot['reasons'])) {
                $slot['can_remove'] = false;
            }
        }
        unset($slot);

        return $out;
    }

    private function appendComboExclusion(int $productId, array $attrs): array
    {
        $attrs = $this->normalizeAttributes($attrs);
        $db = Database::connect();
        $prod = $db->table('products')->select('excluded_combos')->where('id', $productId)->get()->getRowArray();
        $arr = [];
        if (!empty($prod['excluded_combos'])) {
            try {
                $arr = json_decode((string)$prod['excluded_combos'], true) ?? [];
            } catch (\Throwable $e) {
                $arr = [];
            }
        }
        if (!is_array($arr)) {
            $arr = [];
        }

        $needle = json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $exists = false;
        foreach ($arr as $item) {
            if (!is_array($item) || (string)($item['type'] ?? '') !== 'combo' || !isset($item['attributes']) || !is_array($item['attributes'])) {
                continue;
            }
            $candidate = json_encode($this->normalizeAttributes($item['attributes']), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($candidate === $needle) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            $arr[] = ['type' => 'combo', 'attributes' => $attrs];
            $db->table('products')->where('id', $productId)->update(['excluded_combos' => json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
        }

        return $arr;
    }

    private function purgeVariantRows(int $variantId): void
    {
        $db = Database::connect();
        try {
            if ($db->tableExists('variant_inventory')) {
                $db->table('variant_inventory')->where('variant_id', $variantId)->delete();
            }
        } catch (\Throwable $e) {}
        try {
            if ($db->tableExists('stock_balances')) {
                $cols = $db->getFieldNames('stock_balances');
                if (in_array('variant_id', $cols ?? [], true)) {
                    $db->table('stock_balances')->where('variant_id', $variantId)->delete();
                }
            }
        } catch (\Throwable $e) {}

        $this->variants->delete($variantId);
    }

    // List variants (if product_id provided, show for that product)
    public function index()
    {
        $productId = $this->request->getGet('product_id');
        $searchTerm = $this->request->getGet('q') ?? $this->request->getGet('search');
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPageInput = (int)($this->request->getGet('per_page') ?? 50);
        $allowedPerPage = [25, 50, 100, 200];
        $perPage = in_array($perPageInput, $allowedPerPage, true) ? $perPageInput : 50;
        $db = Database::connect();
        $this->ensureVariantsWeightColumn();
        $this->ensureSalesOrderLinesVariantColumn();
        $variantFields = $db->getFieldNames('product_variants');
        $this->ensureVariantsWeightColumn();
        $select = 'pv.*';
        if (in_array('image', $variantFields ?? [], true)) {
            $select .= ', pv.image';
        }

        $salesFields = [];
        $salesVariantCols = [];
        $salesQtyCol = null;
        try {
            if ($db->tableExists('sales_order_lines')) {
                $salesFields = $db->getFieldNames('sales_order_lines');
                $salesVariantCols = array_values(array_filter(['product_variant_id', 'variant_id'], static fn($c) => in_array($c, $salesFields ?? [], true)));
                $salesQtyCol = in_array('quantity', $salesFields ?? [], true) ? 'quantity' : (in_array('qty', $salesFields ?? [], true) ? 'qty' : null);
            }
        } catch (\Throwable $e) {
            $salesVariantCols = [];
            $salesQtyCol = null;
        }

        $soldSub = null;
        if (!empty($salesVariantCols) && $salesQtyCol !== null) {
            $salesVariantExpr = 'COALESCE(' . implode(', ', $salesVariantCols) . ')';
            $soldSubBuilder = $db->table('sales_order_lines sol')
                ->select($salesVariantExpr . ' as variant_id', false)
                ->select('SUM(COALESCE(sol.' . $salesQtyCol . ',0)) as sold', false);

            if ($db->tableExists('sales_orders')) {
                try {
                    $soFields = $db->getFieldNames('sales_orders');
                    $salesFk = in_array('sales_order_id', $salesFields ?? [], true) ? 'sales_order_id' : (in_array('so_id', $salesFields ?? [], true) ? 'so_id' : null);
                    if ($salesFk !== null && in_array('status', $soFields ?? [], true)) {
                        $soldSubBuilder->join('sales_orders so', 'so.id = sol.' . $salesFk, 'left')
                            ->where("LOWER(COALESCE(so.status,'')) NOT IN ('draft','cancelled','canceled','rejected')", null, false);
                    }
                } catch (\Throwable $e) {}
            }

            $soldSubBuilder->where($salesVariantExpr . ' IS NOT NULL', null, false)
                ->where($salesVariantExpr . ' > 0', null, false)
                ->groupBy('variant_id');
            $soldSub = $soldSubBuilder;
        }

        $purchaseFields = [];
        $purchaseVariantCols = [];
        $purchaseQtyCol = null;
        try {
            if ($db->tableExists('purchase_order_lines')) {
                $purchaseFields = $db->getFieldNames('purchase_order_lines');
                $purchaseVariantCols = array_values(array_filter(['variant_id', 'product_variant_id'], static fn($c) => in_array($c, $purchaseFields ?? [], true)));
                $purchaseQtyCol = in_array('quantity', $purchaseFields ?? [], true) ? 'quantity' : (in_array('qty', $purchaseFields ?? [], true) ? 'qty' : null);
            }
        } catch (\Throwable $e) {
            $purchaseVariantCols = [];
            $purchaseQtyCol = null;
        }

        $purchasedSub = null;
        if (!empty($purchaseVariantCols) && $purchaseQtyCol !== null) {
            $purchaseVariantExpr = 'COALESCE(' . implode(', ', $purchaseVariantCols) . ')';
            $purchaseSubBuilder = $db->table('purchase_order_lines pol')
                ->select($purchaseVariantExpr . ' as variant_id', false)
                ->select('SUM(COALESCE(pol.' . $purchaseQtyCol . ',0)) as purchased', false);

            if ($db->tableExists('purchase_orders')) {
                try {
                    $poFields = $db->getFieldNames('purchase_orders');
                    $poFk = in_array('po_id', $purchaseFields ?? [], true) ? 'po_id' : (in_array('purchase_order_id', $purchaseFields ?? [], true) ? 'purchase_order_id' : null);
                    if ($poFk !== null && in_array('status', $poFields ?? [], true)) {
                        $purchaseSubBuilder->join('purchase_orders po', 'po.id = pol.' . $poFk, 'left')
                            ->where("LOWER(COALESCE(po.status,'')) NOT IN ('draft','cancelled','canceled','rejected')", null, false);
                    }
                } catch (\Throwable $e) {}
            }

            $purchaseSubBuilder->where($purchaseVariantExpr . ' IS NOT NULL', null, false)
                ->where($purchaseVariantExpr . ' > 0', null, false)
                ->groupBy('variant_id');
            $purchasedSub = $purchaseSubBuilder;
        }

        $builder = $db->table('product_variants pv')
            ->select($select)
            ->select('p.name as product_name, p.code as product_code')
            ->select('COALESCE(SUM(vi.quantity), 0) as on_hand, COALESCE(SUM(vi.reserved), 0) as reserved')
            ->select($soldSub ? 'COALESCE(sol.sold, 0) as sold' : '0 as sold')
            ->select($purchasedSub ? 'COALESCE(polx.purchased, 0) as purchased' : '0 as purchased')
            ->join('products p', 'p.id = pv.product_id', 'left')
            ->join('variant_inventory vi', 'vi.variant_id = pv.id', 'left');

        if ($soldSub) {
            $builder->join('(' . $soldSub->getCompiledSelect() . ') sol', 'sol.variant_id = pv.id', 'left');
        }
        if ($purchasedSub) {
            $builder->join('(' . $purchasedSub->getCompiledSelect() . ') polx', 'polx.variant_id = pv.id', 'left');
        }
        if ($productId) {
            $builder->where('pv.product_id', (int)$productId);
        }

        if (!empty($searchTerm)) {
            SearchService::applyKeywordSearch($builder, $searchTerm, [
                'pv.art_number',
                'pv.name',
                'pv.attributes',
                'p.name',
                'p.code'
            ]);
        }

        // Count total variants for current filter
        try {
            $countBuilder = $db->table('product_variants pv')->select('COUNT(DISTINCT pv.id) as cnt')
                ->join('products p', 'p.id = pv.product_id', 'left')
                ->join('variant_inventory vi', 'vi.variant_id = pv.id', 'left');
            if ($productId) {
                $countBuilder->where('pv.product_id', (int)$productId);
            }
            if (!empty($searchTerm)) {
                SearchService::applyKeywordSearch($countBuilder, $searchTerm, [
                    'pv.art_number',
                    'pv.name',
                    'pv.attributes',
                    'p.name',
                    'p.code'
                ]);
            }
            $totalRow = $countBuilder->get()->getRowArray();
            $total = isset($totalRow['cnt']) ? (int)$totalRow['cnt'] : 0;
        } catch (\Throwable $e) {
            $total = 0;
        }

        // Pagination (always enforce page size for consistent UI and performance)
        // Clamp page if user requested beyond available range
        $pages = max(1, (int)ceil(($total > 0 ? $total : 1) / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $offset = ($page - 1) * $perPage;
        $builder->groupBy('pv.id')->orderBy('pv.art_number', 'ASC')->limit($perPage, $offset);

        $list = $builder->get()->getResultArray();

        // If AJAX or modal, render partial list view; otherwise render full page with layout wrapper
        if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
            return view('product_variants/index', [
                'variants' => $list,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'product_id' => $productId,
                'search' => $searchTerm
            ]);
        }

        return view('product_variants/page', [
            'variants' => $list,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'product_id' => $productId,
            'search' => $searchTerm
        ]);
    }

    // Live search AJAX endpoint
    public function search()
    {
        $searchTerm = $this->request->getGet('q');
        $productId = $this->request->getGet('product_id');

        if (empty($searchTerm) || strlen($searchTerm) < 1) {
            return $this->response->setJSON(['results' => [], 'total' => 0]);
        }

        $db = Database::connect();
        $builder = $db->table('product_variants pv')
            ->select('pv.id, pv.art_number, pv.name, pv.attributes, p.name as product_name')
            ->join('products p', 'p.id = pv.product_id', 'left');

        if ($productId) {
            $builder->where('pv.product_id', (int)$productId);
        }

        // Apply search across multiple fields
        SearchService::applyKeywordSearch($builder, $searchTerm, [
            'pv.art_number',
            'pv.name',
            'pv.attributes',
            'p.name',
            'p.code'
        ]);

        // Get total count first
        $countBuilder = clone $builder;
        $totalRow = $countBuilder->select('COUNT(*) as cnt', false)->get()->getRowArray();
        $total = isset($totalRow['cnt']) ? (int)$totalRow['cnt'] : 0;

        // Get limited results for preview (show 8 in dropdown, rest via view all)
        $results = $builder->limit(8)->get()->getResultArray();

        // Parse attributes JSON for each result
        foreach ($results as &$row) {
            if (is_string($row['attributes'])) {
                try {
                    $row['attributes'] = json_decode($row['attributes'], true) ?? [];
                } catch (\Throwable $e) {
                    $row['attributes'] = [];
                }
            }
        }

        return $this->response->setJSON([
            'results' => $results,
            'total' => $total,
            'searchTerm' => $searchTerm
        ]);
    }

    // Show create form (supports quick modal by ?modal=1)
    public function create()
    {
        $productId = $this->request->getGet('product_id');
        $data = ['product_id' => $productId];
        if ($this->request->isAJAX() || $this->request->getGet('modal') == '1') {
            return view('product_variants/form', $data);
        }
        return view('product_variants/form', $data);
    }

    // Store a new variant
    public function store()
    {
        $post = $this->request->getPost();
        $productId = isset($post['product_id']) ? (int)$post['product_id'] : null;
        if (! $productId) {
            return redirect()->back()->with('error', 'Product ID required');
        }

        // Fetch template product for defaults
        $db = Database::connect();
        $variantFields = $db->getFieldNames('product_variants');
        $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
        if (! $prod) return redirect()->back()->with('error', 'Product not found');

        // Prevent duplicate variant combinations for same product
        $attrs = [];
        if (isset($post['attributes']) && is_string($post['attributes']) && trim($post['attributes']) !== '') {
            try { $attrs = json_decode($post['attributes'], true) ?? []; } catch (\Throwable $e) { $attrs = []; }
        }
        if (!is_array($attrs)) $attrs = [];
        $combKey = $this->combinationKeyFromAttributes($attrs);
        $dup = $this->variants->where('product_id', $productId)->where('combination_key', $combKey)->first();
        if ($dup) {
            return redirect()->back()->with('error', 'A variant with the same attribute combination already exists');
        }

        $art = isset($post['art_number']) && trim($post['art_number']) !== '' ? trim($post['art_number']) : null;

        // If no art_number provided, generate using category linked to product
        if (! $art) {
            $categoryId = $prod['category_id'] ?? null;
            if (! $categoryId) return redirect()->back()->with('error', 'Product has no category (cannot generate art number)');
            $art = $this->allocateUniqueArtNumber((int)$categoryId);
        }

        // Ensure art_number unique
        $exists = $this->variants->where('art_number', $art)->first();
        if ($exists) {
            return redirect()->back()->with('error', 'Art number already exists');
        }

        $insert = [
            'product_id' => $productId,
            'art_number' => $art,
            'name' => $post['name'] ?? null,
            // inherit template defaults if not provided
            'price' => ($post['price'] ?? null) !== null && $post['price'] !== '' ? $post['price'] : ($prod['sale_price'] ?? null),
            'cost' => ($post['cost'] ?? null) !== null && $post['cost'] !== '' ? $post['cost'] : ($prod['cost_price'] ?? null),
            'attributes' => json_encode($this->normalizeAttributes($attrs), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        if (in_array('combination_key', $variantFields ?? [], true)) {
            $insert['combination_key'] = $combKey;
        }

        if (in_array('weight', $variantFields ?? [], true)) {
            $insert['weight'] = ($post['weight'] ?? null) !== null && $post['weight'] !== '' ? $post['weight'] : ($prod['weight'] ?? null);
        }

        $this->variants->insert($insert);

        // If AJAX, return JSON with new variant
        if ($this->request->isAJAX()) {
            $id = $this->variants->getInsertID();
            $variant = $this->variants->find($id);
            return $this->response->setJSON(['success' => true, 'variant' => $variant]);
        }

        return redirect()->to('/product-variants?product_id=' . $productId)->with('success', 'Variant created');
    }

    // Delete variant
    public function delete($id = null)
    {
        if (! $id) return redirect()->back()->with('error', 'Invalid variant');
        $variantId = (int)$id;
        $v = $this->variants->find($variantId);
        if (! $v) return redirect()->back()->with('error', 'Variant not found');

        $eligibility = $this->getVariantRemovalEligibility([$variantId]);
        $slot = $eligibility[$variantId] ?? ['can_remove' => false, 'reasons' => ['Unable to validate variant usage']];
        if (!$slot['can_remove']) {
            $msg = 'Variant cannot be removed: ' . implode(', ', $slot['reasons']);
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(409)->setJSON(['success' => false, 'message' => $msg, 'reasons' => $slot['reasons']]);
            }
            return redirect()->back()->with('error', $msg);
        }

        $this->purgeVariantRows($variantId);
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => 'Variant deleted']);
        }
        return redirect()->back()->with('success', 'Variant deleted');
    }

    /**
     * Remove an existing variant and immediately add its attribute combination to
     * products.excluded_combos so it stays hidden from future variant generation.
     */
    public function excludeFromList($id = null)
    {
        $this->requireAuth();

        $variantId = (int)($id ?? 0);
        if ($variantId <= 0) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid variant id']);
        }

        $variant = $this->variants->find($variantId);
        if (!$variant) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Variant not found']);
        }

        $eligibility = $this->getVariantRemovalEligibility([$variantId]);
        $slot = $eligibility[$variantId] ?? ['can_remove' => false, 'reasons' => ['Unable to validate variant usage']];
        if (!$slot['can_remove']) {
            return $this->response->setStatusCode(409)->setJSON([
                'success' => false,
                'message' => 'Variant cannot be excluded/removed: ' . implode(', ', $slot['reasons']),
                'reasons' => $slot['reasons'],
            ]);
        }

        $attrs = [];
        if (!empty($variant['attributes'])) {
            try {
                $attrs = json_decode((string)$variant['attributes'], true) ?? [];
            } catch (\Throwable $e) {
                $attrs = [];
            }
        }
        if (!is_array($attrs)) {
            $attrs = [];
        }

        $db = Database::connect();
        $db->transStart();
        $this->purgeVariantRows($variantId);
        $excluded = $this->appendComboExclusion((int)$variant['product_id'], $attrs);
        $db->transComplete();

        if (!$db->transStatus()) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Failed to exclude/remove variant']);
        }

        return $this->response->setJSON([
            'success' => true,
            'message' => 'Variant removed and excluded from future variant lists',
            'variant_id' => $variantId,
            'excluded_entry' => ['type' => 'combo', 'attributes' => $this->normalizeAttributes($attrs)],
            'excluded_combos' => $excluded,
        ]);
    }

    /**
     * Bulk remove existing variants and add their combinations to excluded_combos.
     * Expects POST JSON/body: ids => [variantId, ...]
     */
    public function bulkExcludeFromList()
    {
        $this->requireAuth();

        $body = $this->request->getJSON(true);
        if (!is_array($body) || empty($body)) {
            $body = $this->request->getPost();
        }

        $ids = $body['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'No variants selected',
            ]);
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn($id) => $id > 0)));
        if (empty($ids)) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Invalid variant IDs',
            ]);
        }

        $variantRows = $this->variants->whereIn('id', $ids)->findAll();
        $variantMap = [];
        foreach ($variantRows as $row) {
            $variantMap[(int)($row['id'] ?? 0)] = $row;
        }

        $eligibility = $this->getVariantRemovalEligibility($ids);
        $db = Database::connect();

        $excludedItems = [];
        $blocked = [];
        $failed = [];

        foreach ($ids as $variantId) {
            $variant = $variantMap[$variantId] ?? null;
            if (!$variant) {
                $blocked[] = [
                    'variant_id' => $variantId,
                    'message' => 'Variant not found',
                    'reasons' => ['Variant not found'],
                ];
                continue;
            }

            $slot = $eligibility[$variantId] ?? ['can_remove' => false, 'reasons' => ['Unable to validate variant usage']];
            if (!$slot['can_remove']) {
                $blocked[] = [
                    'variant_id' => $variantId,
                    'art_number' => $variant['art_number'] ?? null,
                    'message' => 'Variant cannot be excluded/removed',
                    'reasons' => array_values((array)($slot['reasons'] ?? [])),
                ];
                continue;
            }

            $attrs = [];
            if (!empty($variant['attributes'])) {
                try {
                    $attrs = json_decode((string)$variant['attributes'], true) ?? [];
                } catch (\Throwable $e) {
                    $attrs = [];
                }
            }
            if (!is_array($attrs)) {
                $attrs = [];
            }

            $db->transStart();
            $this->purgeVariantRows($variantId);
            $this->appendComboExclusion((int)$variant['product_id'], $attrs);
            $db->transComplete();

            if (!$db->transStatus()) {
                $failed[] = [
                    'variant_id' => $variantId,
                    'art_number' => $variant['art_number'] ?? null,
                    'message' => 'Failed to exclude/remove variant',
                ];
                continue;
            }

            $excludedItems[] = [
                'variant_id' => $variantId,
                'art_number' => $variant['art_number'] ?? null,
                'product_id' => (int)($variant['product_id'] ?? 0),
                'excluded_entry' => [
                    'type' => 'combo',
                    'attributes' => $this->normalizeAttributes($attrs),
                ],
            ];
        }

        $excludedCount = count($excludedItems);
        $blockedCount = count($blocked);
        $failedCount = count($failed);

        $message = 'No variants were excluded';
        if ($excludedCount > 0) {
            $message = $excludedCount . ' variant(s) excluded successfully';
            if ($blockedCount > 0 || $failedCount > 0) {
                $message .= ' (' . $blockedCount . ' blocked, ' . $failedCount . ' failed)';
            }
        }

        return $this->response->setJSON([
            'success' => $excludedCount > 0,
            'message' => $message,
            'excluded_count' => $excludedCount,
            'excluded_items' => $excludedItems,
            'blocked_count' => $blockedCount,
            'blocked' => $blocked,
            'failed_count' => $failedCount,
            'failed' => $failed,
        ]);
    }

    /**
     * Bulk delete variants via AJAX POST.
     * Checks each variant for linked documents (quotation_lines, purchase_order_lines, sales_order_lines).
     * Returns JSON with results.
     */
    public function bulkDelete()
    {
        $body = $this->request->getJSON(true) ?: $this->request->getPost();
        $ids = $body['ids'] ?? [];
        if (!is_array($ids) || empty($ids)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'No variants selected']);
        }

        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 0);
        if (empty($ids)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid variant IDs']);
        }

        $db = \Config\Database::connect();
        $blocked = [];
        $deleted = [];

        foreach ($ids as $vid) {
            $v = $this->variants->find($vid);
            if (!$v) continue;

            $artNumber = $v['art_number'] ?? '#' . $vid;
            $reasons = [];

            // Check quotation_lines
            try {
                if ($db->tableExists('quotation_lines')) {
                    $cnt = (int)$db->table('quotation_lines')
                        ->groupStart()
                            ->where('variant_id', $vid)
                            ->orWhere('product_variant_id', $vid)
                        ->groupEnd()
                        ->countAllResults();
                    if ($cnt > 0) $reasons[] = $cnt . ' quotation line(s)';
                }
            } catch (\Throwable $e) {}

            // Check purchase_order_lines
            try {
                if ($db->tableExists('purchase_order_lines')) {
                    $cnt = (int)$db->table('purchase_order_lines')
                        ->where('variant_id', $vid)
                        ->countAllResults();
                    if ($cnt > 0) $reasons[] = $cnt . ' PO line(s)';
                }
            } catch (\Throwable $e) {}

            // Check sales_order_lines
            try {
                if ($db->tableExists('sales_order_lines')) {
                    $cnt = (int)$db->table('sales_order_lines')
                        ->groupStart()
                            ->where('variant_id', $vid)
                            ->orWhere('product_variant_id', $vid)
                        ->groupEnd()
                        ->countAllResults();
                    if ($cnt > 0) $reasons[] = $cnt . ' sales order line(s)';
                }
            } catch (\Throwable $e) {}

            if (!empty($reasons)) {
                $blocked[] = ['id' => $vid, 'art_number' => $artNumber, 'reason' => implode(', ', $reasons)];
            } else {
                $this->variants->delete($vid);
                $deleted[] = $vid;
            }
        }

        return $this->response->setJSON([
            'success'       => true,
            'deleted_count' => count($deleted),
            'deleted_ids'   => $deleted,
            'blocked_count' => count($blocked),
            'blocked'       => $blocked,
        ]);
    }

    /**
     * Preview generated variants (combinations) without persisting.
     * Expects POST: product_id, attributes_definitions (JSON string or object)
     * Returns JSON with combinations and simulated art numbers (non-destructive).
     */
    public function generatePreview()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'AJAX required']);
        }

        $post = $this->request->getPost();
        $productId = isset($post['product_id']) ? (int)$post['product_id'] : null;
        $categoryIdOverride = isset($post['category_id']) ? (int)$post['category_id'] : null;
        $defs = [];
        if (! empty($post['attributes_definitions'])) {
            $raw = $post['attributes_definitions'];
            if (is_string($raw)) {
                try { $defs = json_decode($raw, true) ?? []; } catch (\Throwable $e) { $defs = []; }
            } elseif (is_array($raw)) {
                $defs = $raw;
            }
        } else {
            // fallback: attempt to read from product
            if ($productId) {
                $db = Database::connect();
                $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
                if ($prod && ! empty($prod['attributes_definitions'])) {
                    $defs = json_decode($prod['attributes_definitions'], true) ?? [];
                }
            }
        }

        if (empty($defs) || ! is_array($defs)) {
            return $this->response->setJSON(['success' => false, 'message' => 'No attribute definitions provided']);
        }

        // Existing combinations (for idempotent UX)
        $existingKeys = [];
        $existingArtByKey = [];
        $existingIdByKey = [];
        $existingVariantIds = [];
        if ($productId) {
            try {
                $existing = $this->variants->where('product_id', $productId)->findAll();
                foreach ($existing as $ev) {
                    $ea = [];
                    if (!empty($ev['attributes'])) {
                        try { $ea = json_decode($ev['attributes'], true) ?? []; } catch (\Throwable $e) { $ea = []; }
                    }
                    if (!is_array($ea)) $ea = [];
                    $k = $this->combinationKeyFromAttributes($ea);
                    $existingKeys[$k] = true;
                    $existingArtByKey[$k] = $ev['art_number'] ?? null;
                    $existingIdByKey[$k] = (int)($ev['id'] ?? 0);
                    if (!empty($ev['id'])) {
                        $existingVariantIds[] = (int)$ev['id'];
                    }
                }
            } catch (\Throwable $e) {
                $existingKeys = [];
                $existingArtByKey = [];
                $existingIdByKey = [];
                $existingVariantIds = [];
            }
        }

        $existingEligibility = $this->getVariantRemovalEligibility($existingVariantIds);

        // Build arrays of values
        $valueLists = [];
        $names = [];
        foreach ($defs as $d) {
            $names[] = $d['name'] ?? '';
            $valueLists[] = array_values(array_filter(array_map('trim', (array)($d['values'] ?? []))));
        }

        // Cartesian product
        $combinations = [[]];
        foreach ($valueLists as $list) {
            $new = [];
            foreach ($combinations as $c) {
                foreach ($list as $v) {
                    $nc = $c;
                    $nc[] = $v;
                    $new[] = $nc;
                }
            }
            $combinations = $new;
        }

        // Simulate art numbers using universal art-number system (non-destructive)
        $simulatedArts = [];
        $db = Database::connect();
        $globalNext = null;
        $brandCode = strtoupper(trim((string)$this->artService->getBrandCode()));
        $categorySuffix = '';
        $digits = \App\Services\ArtNumberService::PAD_DIGITS;
        $productCategoryId = null;
        if ($productId) {
            $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
            if ($prod) {
                $productCategoryId = $prod['category_id'] ?? null;
                $categoryId = $productCategoryId;
            }
        }
        // Prefer explicit category from UI (if provided), otherwise fallback to product's category.
        $categoryId = $categoryIdOverride ?: $productCategoryId;
        if ($categoryId) {
            $cat = $db->table('product_categories')->where('id', $categoryId)->get()->getRowArray();
            if ($cat) {
                $categorySuffix = strtoupper(trim((string)($cat['suffix'] ?? '')));
            }
        }

        try {
            $globalNext = (int)$this->artService->currentGlobalNumber();
        } catch (\Throwable $e) {
            $globalNext = null;
        }

        $count = count($combinations);
        for ($i = 0; $i < $count; $i++) {
            $num = ($globalNext !== null) ? ($globalNext + $i) : null;
            if ($num !== null) {
                $numStr = str_pad((string)$num, $digits, '0', STR_PAD_LEFT);
                if ($brandCode !== '' && $categorySuffix !== '') {
                    $art = $brandCode . '-' . $categorySuffix . '-' . $numStr;
                } else {
                    $art = null;
                }
            } else {
                $art = null;
            }
            $simulatedArts[] = $art;
        }

        // Format response combinations
        $result = [];
        // Optional: accept excluded_combos from POST (when product edits are unsaved in form)
        $providedExcluded = null;
        if (!empty($post['excluded_combos'])) {
            if (is_string($post['excluded_combos'])) {
                try { $providedExcluded = json_decode($post['excluded_combos'], true) ?? null; } catch (\Throwable $_) { $providedExcluded = null; }
            } elseif (is_array($post['excluded_combos'])) {
                $providedExcluded = $post['excluded_combos'];
            }
        }

        $onlyAllowCombos = [];
        if (is_array($providedExcluded)) {
            foreach ($providedExcluded as $item) {
                if (!is_array($item)) continue;
                if (($item['type'] ?? '') === 'only_allow_combo' && isset($item['attributes']) && is_array($item['attributes'])) {
                    $onlyAllowCombos[] = $item['attributes'];
                }
            }
        }
        $onlyAllowMode = !empty($onlyAllowCombos);

        $matchesCombo = function(array $need, array $given): bool {
            foreach ($need as $attr => $val) {
                $needAttr = strtolower(trim((string)$attr));
                $needVal  = strtolower(trim((string)$val));
                $found = false;
                foreach ($given as $k => $v) {
                    if (strtolower(trim((string)$k)) === $needAttr && strtolower(trim((string)$v)) === $needVal) {
                        $found = true;
                        break;
                    }
                }
                if (! $found) return false;
            }
            return true;
        };

        foreach ($combinations as $idx => $comb) {
            $attrs = [];
            foreach ($names as $k => $n) {
                $attrs[$n] = $comb[$k] ?? null;
            }
            $ck = $this->combinationKeyFromAttributes($attrs);

            $exists = isset($existingKeys[$ck]) ? true : false;

            $matchesOnlyAllow = false;
            if ($onlyAllowMode) {
                foreach ($onlyAllowCombos as $inc) {
                    if ($matchesCombo($inc, $attrs)) {
                        $matchesOnlyAllow = true;
                        break;
                    }
                }
            }
            
            // Check if combination is excluded (use provided excluded_combos if present)
            $isExcluded = false;
            if ($productId) {
                $isExcluded = $this->exclusionService->isVariantExcluded((int)$productId, $attrs, $providedExcluded);
            }

            $excludedReason = null;
            if ($isExcluded) {
                $excludedReason = ($onlyAllowMode && !$matchesOnlyAllow)
                    ? 'Not in Only Allow combo list'
                    : 'Matches your exclusion settings';
            }

            $statusLabel = 'New';
            if ($exists) {
                $statusLabel = 'Already exists';
            } elseif ($isExcluded && $excludedReason === 'Not in Only Allow combo list') {
                $statusLabel = 'Excluded (Only Allow active)';
            } elseif ($isExcluded) {
                $statusLabel = 'Excluded by rules';
            } elseif ($onlyAllowMode && $matchesOnlyAllow) {
                $statusLabel = 'Allowed by Only Allow';
            }
            
            $result[] = [
                'attributes' => $attrs,
                'display' => implode(' | ', array_map(function($k, $v){ return $k.': '.$v; }, array_keys($attrs), $attrs)),
                'simulated_art' => $simulatedArts[$idx] ?? null,
                'exists' => $exists,
                'existing_art' => $existingArtByKey[$ck] ?? null,
                'existing_variant_id' => $exists ? (int)($existingIdByKey[$ck] ?? 0) : null,
                'existing_can_remove' => $exists ? (bool)($existingEligibility[(int)($existingIdByKey[$ck] ?? 0)]['can_remove'] ?? false) : false,
                'existing_block_reason' => $exists ? implode(', ', (array)($existingEligibility[(int)($existingIdByKey[$ck] ?? 0)]['reasons'] ?? [])) : null,
                'excluded' => $isExcluded,
                'excluded_reason' => $excludedReason,
                'only_allow_mode' => $onlyAllowMode,
                'matches_only_allow' => $matchesOnlyAllow,
                'status_label' => $statusLabel,
            ];
        }

        return $this->response->setJSON(['success' => true, 'combinations' => $result]);
    }

    /**
     * Generate and persist variants for a product from attribute combinations.
     * Expects POST: product_id, combinations (array of attribute maps)
     * Returns JSON with created variants.
     */
    public function generate()
    {
        if (! $this->request->isAJAX()) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'AJAX required']);
        }

        $post = $this->request->getPost();
        $productId = isset($post['product_id']) ? (int)$post['product_id'] : null;
        $categoryIdOverride = isset($post['category_id']) ? (int)$post['category_id'] : null;
        $combinations = [];
        if (! empty($post['combinations'])) {
            $raw = $post['combinations'];
            if (is_string($raw)) {
                try { $combinations = json_decode($raw, true) ?? []; } catch (\Throwable $e) { $combinations = []; }
            } elseif (is_array($raw)) {
                $combinations = $raw;
            }
        }

        if (! $productId || empty($combinations)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Product and combinations required']);
        }

        $db = Database::connect();
        $variantModel = $this->variants;
        $inventoryModel = new \App\Models\VariantInventoryModel();
        $variantFields = $db->getFieldNames('product_variants');
        $created = [];

        // template product defaults
        $prod = $db->table('products')->where('id', $productId)->get()->getRowArray();
        if (! $prod) {
            return $this->response->setJSON(['success' => false, 'message' => 'Product not found']);
        }
        // Prefer category selected in UI (if provided), otherwise fallback to product's saved category.
        $categoryId = $categoryIdOverride ?: ($prod['category_id'] ?? null);
        if (! $categoryId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Product has no category']);
        }

        // existing combinations (idempotent)
        $existingKeys = [];
        try {
            $existing = $variantModel->where('product_id', $productId)->findAll();
            foreach ($existing as $ev) {
                $ea = [];
                if (!empty($ev['attributes'])) {
                    try { $ea = json_decode($ev['attributes'], true) ?? []; } catch (\Throwable $e) { $ea = []; }
                }
                if (!is_array($ea)) $ea = [];
                $existingKeys[$this->combinationKeyFromAttributes($ea)] = true;
            }
        } catch (\Throwable $e) {
            $existingKeys = [];
        }

        // Accept optional excluded_combos from POST to honor unsaved product excludes
        $providedExcluded = null;
        if (!empty($post['excluded_combos'])) {
            if (is_string($post['excluded_combos'])) {
                try { $providedExcluded = json_decode($post['excluded_combos'], true) ?? null; } catch (\Throwable $_) { $providedExcluded = null; }
            } elseif (is_array($post['excluded_combos'])) {
                $providedExcluded = $post['excluded_combos'];
            }
        }

        foreach ($combinations as $comb) {
            $attrsArr = $comb['attributes'] ?? $comb;
            if (!is_array($attrsArr)) $attrsArr = [];
            $attrsArr = $this->normalizeAttributes($attrsArr);
            $combKey = $this->combinationKeyFromAttributes($attrsArr);

            // Skip if already exists (idempotent)
            if (isset($existingKeys[$combKey])) {
                continue;
            }

            // Check if this combination is excluded by rules (use provided excluded list if present)
            if ($this->exclusionService->isVariantExcluded($productId, $attrsArr, $providedExcluded)) {
                continue;
            }

            // Attributes map -> normalized JSON
            $attrsJson = json_encode($attrsArr, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            // Build variant display name
            $parts = [];
            foreach (($comb['attributes'] ?? $comb) as $k => $v) {
                $parts[] = $v;
            }
            $vname = implode(' / ', $parts);

            // Determine art number via ArtNumberService (increments global art counter)
            try {
                $art = $this->allocateUniqueArtNumber((int)$categoryId);
            } catch (\Throwable $e) {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to allocate art number: ' . $e->getMessage()]);
            }

            // Insert variant
            $insert = [
                'product_id' => $productId,
                'art_number' => $art,
                'name' => $vname,
                // inherit defaults from template (can be edited per-variant later)
                'price' => $prod['sale_price'] ?? null,
                'cost' => $prod['cost_price'] ?? null,
                'attributes' => $attrsJson,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (in_array('weight', $variantFields ?? [], true)) {
                $insert['weight'] = $prod['weight'] ?? null;
            }

            if (in_array('combination_key', $variantFields ?? [], true)) {
                $insert['combination_key'] = $combKey;
            }

            $variantModel->insert($insert);
            $vid = $variantModel->getInsertID();

            // Insert inventory row with warehouse_id = NULL and quantity = 0
            $inventoryModel->insert(['variant_id' => $vid, 'warehouse_id' => null, 'quantity' => 0, 'reserved' => 0, 'created_at' => date('Y-m-d H:i:s')]);

            $created[] = $variantModel->find($vid);

            // mark created to prevent duplicates inside the same request
            $existingKeys[$combKey] = true;
        }

        return $this->response->setJSON(['success' => true, 'created' => $created]);
    }

    /**
     * Update a variant (name, art number, price, cost, weight, image)
     */
    public function update($id = null)
    {
        $this->requireAuth();

        $variantId = (int)$id;
        if (! $variantId) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid variant id']);
        }

        $variant = $this->variants->find($variantId);
        if (! $variant) {
            return $this->response->setJSON(['success' => false, 'message' => 'Variant not found']);
        }

        $post = $this->request->getPost();
        $db = Database::connect();
        $variantFields = $db->getFieldNames('product_variants');

        $data = [
            'name' => $variant['name'] ?? null,
            'art_number' => $post['art_number'] ?? ($variant['art_number'] ?? null),
            'price' => ($post['price'] ?? '') !== '' ? $post['price'] : ($variant['price'] ?? null),
            'cost' => ($post['cost'] ?? '') !== '' ? $post['cost'] : ($variant['cost'] ?? null),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Handle vendor_id for variant (can override template vendor)
        if (isset($post['vendor_id'])) {
            $data['vendor_id'] = $post['vendor_id'] !== '' ? (int)$post['vendor_id'] : null;
        }

        if (in_array('weight', $variantFields ?? [], true)) {
            $data['weight'] = ($post['weight'] ?? '') !== '' ? $post['weight'] : ($variant['weight'] ?? null);
        }

        // Variant-level currency and vendor pricing fields
        if (in_array('sale_currency', $variantFields ?? [], true)) {
            $data['sale_currency'] = ($post['sale_currency'] ?? '') !== '' ? trim($post['sale_currency']) : null;
        }
        if (in_array('cost_currency', $variantFields ?? [], true)) {
            $data['cost_currency'] = ($post['cost_currency'] ?? '') !== '' ? trim($post['cost_currency']) : null;
        }
        if (in_array('vendor_price', $variantFields ?? [], true)) {
            $data['vendor_price'] = ($post['vendor_price'] ?? '') !== '' ? $post['vendor_price'] : null;
        }
        if (in_array('vendor_currency', $variantFields ?? [], true)) {
            $data['vendor_currency'] = ($post['vendor_currency'] ?? '') !== '' ? trim($post['vendor_currency']) : null;
        }

        // Handle variant image upload (optional, multi-select uses first as default)
        $imageColumnReady = $this->ensureVariantsImageColumn();
        if ($imageColumnReady) {
            $uploadDir = FCPATH . 'uploads/variants';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            $defaultImage = null;
            $multiFiles = $this->request->getFileMultiple('images');
            if (!empty($multiFiles)) {
                foreach ($multiFiles as $file) {
                    if (!$file || !$file->isValid()) continue;
                    $newName = uniqid('variant_', true) . '.' . $file->getExtension();
                    if ($file->move($uploadDir, $newName, true)) {
                        if ($defaultImage === null) {
                            $defaultImage = $newName;
                        }
                    }
                }
            }

            if ($defaultImage === null) {
                $imageFile = $this->request->getFile('image');
                if ($imageFile && $imageFile->isValid()) {
                    $newName = uniqid('variant_', true) . '.' . $imageFile->getExtension();
                    if ($imageFile->move($uploadDir, $newName, true)) {
                        $defaultImage = $newName;
                    }
                }
            }

            if ($defaultImage) {
                // remove old file
                if (!empty($variant['image'])) {
                    $oldPath = $uploadDir . DIRECTORY_SEPARATOR . $variant['image'];
                    if (is_file($oldPath)) {
                        @unlink($oldPath);
                    }
                }
                $data['image'] = $defaultImage;
            }
        }

        $this->variants->update($variantId, $data);

        $updated = $this->variants->find($variantId);
        $imageUrl = !empty($updated['image']) ? base_url('uploads/variants/' . $updated['image']) : base_url('assets/images/no-image.png');

        if (! $this->request->isAJAX()) {
            return redirect()->to('/product-variants/' . $variantId . '/edit')
                ->with('success', 'Variant updated successfully.');
        }

        return $this->response->setJSON([
            'success' => true,
            'variant' => $updated,
            'image_url' => $imageUrl,
        ]);
    }

    /**
     * Show edit form for a single variant (separate from template product).
     */
    public function edit($id = null)
    {
        $this->requireAuth();

        $variantId = (int)$id;
        if (! $variantId) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Variant not found');
        }

        $db = Database::connect();
        $variantFields = $db->getFieldNames('product_variants');
        $productFields = $db->getFieldNames('products');
        $select = 'pv.*';
        if (in_array('image', $variantFields ?? [], true)) {
            $select .= ', pv.image';
        }
        if (in_array('weight', $variantFields ?? [], true)) {
            $select .= ', pv.weight';
        }
        if (in_array('vendor_id', $variantFields ?? [], true)) {
            $select .= ', pv.vendor_id as variant_vendor_id';
        }
        // Explicitly select new variant price/currency columns to guarantee they aren't overwritten by joined table columns
        if (in_array('sale_currency', $variantFields ?? [], true)) {
            $select .= ', pv.sale_currency';
        }
        if (in_array('cost_currency', $variantFields ?? [], true)) {
            $select .= ', pv.cost_currency';
        }
        if (in_array('vendor_price', $variantFields ?? [], true)) {
            $select .= ', pv.vendor_price as variant_vendor_price';
        }
        if (in_array('vendor_currency', $variantFields ?? [], true)) {
            $select .= ', pv.vendor_currency as variant_vendor_currency';
        }
        if (in_array('unit', $productFields ?? [], true)) {
            $select .= ', p.unit as product_unit';
        }
        if (in_array('weight_unit', $productFields ?? [], true)) {
            $select .= ', p.weight_unit as product_weight_unit';
        }

        $row = $db->table('product_variants pv')
            ->select($select)
            // Alias template-level vendor_price/vendor_currency to avoid collision with variant columns
            ->select('p.id as product_id, p.public_id as product_public_id, p.name as product_name, p.code as product_code, p.vendor_id as template_vendor_id, p.vendor_price as template_vendor_price, p.vendor_currency as template_vendor_currency')
            ->select('v.name as vendor_name')
            ->select('vv.name as variant_vendor_name')
            ->join('products p', 'p.id = pv.product_id', 'left')
            ->join('vendors v', 'v.id = p.vendor_id', 'left')
            ->join('vendors vv', in_array('vendor_id', $variantFields ?? [], true) ? 'vv.id = pv.vendor_id' : '1=0', 'left')
            ->where('pv.id', $variantId)
            ->get()
            ->getRowArray();

        if (! $row) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Variant not found');
        }

        // Fetch all vendors for dropdown
        $vendors = $db->table('vendors')
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();

        $attributes = [];
        if (!empty($row['attributes'])) {
            $attributes = is_string($row['attributes']) ? (json_decode($row['attributes'], true) ?? []) : (is_array($row['attributes']) ? $row['attributes'] : []);
        }

        $data = [
            'page_title' => 'Edit Variant - ' . ($row['art_number'] ?? $variantId),
            'variant' => $row,
            'product' => [
                'id' => $row['product_id'] ?? null,
                'public_id' => $row['product_public_id'] ?? null,
                'name' => $row['product_name'] ?? null,
                'code' => $row['product_code'] ?? null,
                'vendor_id' => $row['template_vendor_id'] ?? null,
                'vendor_name' => $row['vendor_name'] ?? null,
                'vendor_price' => $row['template_vendor_price'] ?? null,
                'vendor_currency' => $row['template_vendor_currency'] ?? null,
                'unit' => $row['product_unit'] ?? null,
                'weight_unit' => $row['product_weight_unit'] ?? null,
            ],
            'vendors' => $vendors,
            'variant_vendor_id' => $row['variant_vendor_id'] ?? null,
            'variant_vendor_name' => $row['variant_vendor_name'] ?? null,
            'attributes' => $attributes,
            'has_weight' => in_array('weight', $variantFields ?? [], true),
            'has_image' => in_array('image', $variantFields ?? [], true),
            'has_variant_vendor' => in_array('vendor_id', $variantFields ?? [], true),
            'has_sale_currency' => in_array('sale_currency', $variantFields ?? [], true),
            'has_cost_currency' => in_array('cost_currency', $variantFields ?? [], true),
            'has_variant_vendor_price' => in_array('vendor_price', $variantFields ?? [], true),
            'has_vendor_currency' => in_array('vendor_currency', $variantFields ?? [], true),
            // Pass variant-level vendor price/currency explicitly (aliased to avoid collision)
            'variant_saved_vendor_price' => $row['variant_vendor_price'] ?? null,
            'variant_saved_vendor_currency' => $row['variant_vendor_currency'] ?? null,
            'can_view_assets' => $this->hasPermission('product_assets.read')
                || $this->hasPermission('product_assets.view')
                || $this->hasPermission('products.edit'),
            'product_identifier' => (string) ($row['product_public_id'] ?? ''),
        ];

        // compute stock aggregates for this variant
        try {
            $inv = $db->table('variant_inventory')
                ->select('COALESCE(SUM(quantity),0) as on_hand, COALESCE(SUM(reserved),0) as reserved')
                ->where('variant_id', $variantId)
                ->get()->getRowArray();
            $onHand = isset($inv['on_hand']) ? (float)$inv['on_hand'] : 0.0;
            $reserved = isset($inv['reserved']) ? (float)$inv['reserved'] : 0.0;
            $available = $onHand - $reserved;
        } catch (\Throwable $e) {
            $onHand = 0.0; $reserved = 0.0; $available = 0.0;
        }
        $data['on_hand'] = $onHand;
        $data['reserved'] = $reserved;
        $data['available'] = $available;

        // recent sales - show last 5 sales of THIS SPECIFIC VARIANT (not all variants combined)
        $recentSales = [];
        try {
            if (!empty($variantId)) {
                // Check if product_variant_id column exists in sales_order_lines
                $salesCols = [];
                try { $salesCols = $db->getFieldNames('sales_order_lines'); } catch (\Throwable $_) { $salesCols = []; }
                
                // For each row, show the exact line item (no grouping) so we get the true per-variant sales
                $recentSales = $db->table('sales_order_lines sol')
                        ->select('so.id as sales_order_id, so.order_number, so.created_at, sol.quantity, sol.unit_price')
                    ->join('sales_orders so', 'so.id = sol.sales_order_id', 'left');
                
                // Filter by variant_id if column exists and has data, else fall back to product_id
                if (in_array('product_variant_id', $salesCols, true)) {
                    $recentSales = $recentSales->where('sol.product_variant_id', $variantId);
                } else {
                    // Legacy: only filter by product_id if variant column doesn't exist
                    $recentSales = $recentSales->where('sol.product_id', $row['product_id']);
                }
                
                $recentSales = $recentSales->orderBy('so.created_at', 'DESC')
                    ->limit(5)
                    ->get()->getResultArray();
            }
        } catch (\Throwable $e) {
            $recentSales = [];
        }
        $data['recent_sales'] = $recentSales;

        // recent purchases - show last 5 purchases of template product (variants not tracked in purchases yet)
        $recentPurchases = [];
        try {
            if (!empty($row['product_id'])) {
                // Show individual line items for purchases (no grouping)
                $recentPurchases = $db->table('purchase_order_lines pol')
                        ->select('po.id as purchase_order_id, po.order_number as po_number, po.created_at, pol.qty as quantity, pol.unit_price')
                    ->join('purchase_orders po', 'po.id = pol.po_id', 'left')
                    ->where('pol.product_id', $row['product_id'])
                    ->orderBy('po.created_at', 'DESC')
                    ->limit(5)
                    ->get()->getResultArray();
            }
        } catch (\Throwable $e) {
            $recentPurchases = [];
        }
        $data['recent_purchases'] = $recentPurchases;

        $variantNav = [
            'position' => null,
            'total' => 0,
            'prev_id' => null,
            'next_id' => null,
            'prev_label' => null,
            'next_label' => null,
        ];
        if (!empty($row['product_id'])) {
            try {
                $sequence = $db->table('product_variants')
                    ->select('id, name, art_number')
                    ->where('product_id', $row['product_id'])
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getResultArray();
                $variantNav['total'] = count($sequence);
                $ids = array_map(fn($item) => (int)$item['id'], $sequence);
                $currentIndex = array_search($variantId, $ids, true);
                if ($currentIndex !== false) {
                    $variantNav['position'] = $currentIndex + 1;
                    if ($currentIndex > 0) {
                        $variantNav['prev_id'] = $ids[$currentIndex - 1];
                    }
                    if ($currentIndex < $variantNav['total'] - 1) {
                        $variantNav['next_id'] = $ids[$currentIndex + 1];
                    }
                }
                $lookup = [];
                foreach ($sequence as $item) {
                    $lookup[(int)$item['id']] = $item;
                }
                if ($variantNav['prev_id'] && isset($lookup[$variantNav['prev_id']])) {
                    $variantNav['prev_label'] = $lookup[$variantNav['prev_id']]['art_number'] ?? $lookup[$variantNav['prev_id']]['name'] ?? null;
                }
                if ($variantNav['next_id'] && isset($lookup[$variantNav['next_id']])) {
                    $variantNav['next_label'] = $lookup[$variantNav['next_id']]['art_number'] ?? $lookup[$variantNav['next_id']]['name'] ?? null;
                }
            } catch (\Throwable $e) {
                log_message('error', 'Variant navigation lookup failed: ' . $e->getMessage());
            }
        }
        $data['variant_nav'] = $variantNav;

        try {
            $preparationProfileModel = new \App\Models\PreparationProfileModel();
            $data['variant_preparation_profiles'] = $preparationProfileModel->getWithCountsByVariant($variantId, true);
        } catch (\Throwable $e) {
            $data['variant_preparation_profiles'] = [];
        }

        return view('product_variants/edit', $data);
    }

    /**
     * API: return JSON list of variants for a product
     */
    public function apiList($productId = null)
    {
        $productId = (int)$productId;
        if (! $productId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Product ID required']);
        }

        $list = $this->variants->where('product_id', $productId)->findAll();
        $out = [];
        foreach ($list as $v) {
            $attrs = [];
            if (! empty($v['attributes'])) {
                $attrs = json_decode($v['attributes'], true) ?? [];
            }
            $out[] = [
                'id' => $v['id'],
                'art_number' => $v['art_number'],
                'name' => $v['name'],
                'price' => $v['price'],
                'cost' => $v['cost'],
                'attributes' => $attrs,
            ];
        }

        return $this->response->setJSON(['success' => true, 'variants' => $out]);
    }
}
