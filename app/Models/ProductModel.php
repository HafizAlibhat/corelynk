<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Services\SearchService;
use App\Traits\PublicIdTrait;

class ProductModel extends Model
{
    use PublicIdTrait;

    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;

    protected $allowedFields    = [
        'name',
        'code',
        'category_id',
        'unit',
        'description',
        'images',
        'is_active',
        'current_stock',
        'unit_cost',
        'barcode',
        'sku',
        'weight',
        'weight_unit',
        'vendor_id',
        'vendor_price',
        'vendor_price_pkr',
        'vendor_currency',
        'detailed_type',
        'service_policy',
        'product_type',
        'attributes_definitions',
        'excluded_combos',
        'public_id',
        'cost_price',
        'cost_currency',
        'sale_price',
        'sale_currency',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Validation
    protected $validationRules = [
        'id'          => 'permit_empty|integer',
        'name'        => 'required|min_length[2]|max_length[100]',
        'code'        => 'required|min_length[2]|max_length[50]|is_unique[products.code,id,{id}]',
        'category_id' => 'permit_empty|integer|is_not_unique[product_categories.id]',
        'unit'        => 'required|min_length[1]|max_length[20]',
        'description' => 'permit_empty|max_length[1000]',
        'is_active'   => 'permit_empty|in_list[0,1]',
        'detailed_type' => 'permit_empty|in_list[storable,consumable,service]',
        'service_policy'=> 'permit_empty|in_list[ordered_qty,delivered_qty]',
        'product_type'=> 'permit_empty|in_list[simple,variable]',
        // Pricing and physical attributes
        'weight'      => 'permit_empty|numeric',
        'weight_unit' => 'permit_empty|alpha_numeric|max_length[10]',
        'cost_price'  => 'permit_empty|numeric',
        'sale_price'  => 'permit_empty|numeric',
        'vendor_price' => 'permit_empty|numeric',
        'cost_currency'=> 'permit_empty|alpha_numeric|max_length[3]',
        'sale_currency'=> 'permit_empty|alpha_numeric|max_length[3]'
    ];

    protected $validationMessages = [
        'name' => [
            'required'    => 'Product name is required',
            'min_length'  => 'Product name must be at least 2 characters'
        ],
        'code' => [
            'required'    => 'Product code is required',
            'is_unique'   => 'Product code already exists'
        ],
        'category_id' => [
            'is_not_unique' => 'Selected category does not exist'
        ],
        'unit' => [
            'required' => 'Unit is required'
        ]
    ];

    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->bootPublicId();
    }

    /**
     * Normalize free text for loose matching across punctuation/spacing variants.
     * Example: "Rosegold(PC)" and "Rosegold PC" both become "rosegoldpc".
     */
    private function normalizeLooseToken(string $value): string
    {
        $normalized = strtolower(trim($value));
        return preg_replace('/[^a-z0-9]+/', '', $normalized) ?? '';
    }

    /**
     * Get products with filters and pagination
     * @param string|null $searchTerm Keyword search across product name, code, variant code, description, attributes
     * @param mixed $categoryFilter Category ID filter
     * @param mixed $statusFilter Status filter (1=active, 0=inactive)
     * @param int $perPage Records per page for pagination
     * @param string|null $typeFilter Product type (storable, consumable, service)
     * @param array $attributeFilters Multiple attribute filters: [{name: 'Color', value: 'Blue'}]
     * @param bool|string $hasAssets Filter by products with assets (1/true/yes = only with assets, 0/false/no = only without assets, empty = all)
     * @param string $sortBy Sort preset: recent, oldest, most_sold, ready_stock, most_purchased, name_az
     */
    public function getProductsWithFilters(
        $searchTerm = null,
        $categoryFilter = null,
        $statusFilter = null,
        $perPage = 20,
        $typeFilter = null,
        $attributeFilters = [],
        bool $hideServices = false,
        array $allowedCategoryIds = [],
        $hasAssets = null,
        string $sortBy = 'recent'
    ): array
    {
        $db = $this->db;

        $soldUnitsSub = '0';
        try {
            if ($db->tableExists('sales_order_lines')) {
                $solFields = $db->getFieldNames('sales_order_lines');
                $solQty = in_array('quantity', $solFields ?? [], true) ? 'quantity' : (in_array('qty', $solFields ?? [], true) ? 'qty' : null);
                if ($solQty !== null && in_array('product_id', $solFields ?? [], true)) {
                    $statusJoin = '';
                    $soldStatusFilter = '';
                    if ($db->tableExists('sales_orders')) {
                        $soFields = $db->getFieldNames('sales_orders');
                        $solFk = in_array('sales_order_id', $solFields ?? [], true) ? 'sales_order_id' : (in_array('so_id', $solFields ?? [], true) ? 'so_id' : null);
                        if ($solFk !== null && in_array('status', $soFields ?? [], true)) {
                            $statusJoin = ' LEFT JOIN sales_orders so ON so.id = sol.' . $solFk . ' ';
                            $soldStatusFilter = " AND LOWER(COALESCE(so.status, '')) NOT IN ('draft','cancelled','canceled','rejected')";
                        }
                    }
                    $soldUnitsSub = "COALESCE((SELECT SUM(COALESCE(sol." . $solQty . ",0)) FROM sales_order_lines sol" . $statusJoin . "WHERE sol.product_id = products.id" . $soldStatusFilter . "), 0)";
                }
            }
        } catch (\Throwable $e) {
            $soldUnitsSub = '0';
        }

        $purchasedUnitsSub = '0';
        try {
            if ($db->tableExists('purchase_order_lines')) {
                $polFields = $db->getFieldNames('purchase_order_lines');
                $polQty = in_array('quantity', $polFields ?? [], true) ? 'quantity' : (in_array('qty', $polFields ?? [], true) ? 'qty' : null);
                if ($polQty !== null && in_array('product_id', $polFields ?? [], true)) {
                    $statusJoin = '';
                    $purchaseStatusFilter = '';
                    if ($db->tableExists('purchase_orders')) {
                        $poFields = $db->getFieldNames('purchase_orders');
                        $polFk = in_array('po_id', $polFields ?? [], true) ? 'po_id' : (in_array('purchase_order_id', $polFields ?? [], true) ? 'purchase_order_id' : null);
                        if ($polFk !== null && in_array('status', $poFields ?? [], true)) {
                            $statusJoin = ' LEFT JOIN purchase_orders po ON po.id = pol.' . $polFk . ' ';
                            $purchaseStatusFilter = " AND LOWER(COALESCE(po.status, '')) NOT IN ('draft','cancelled','canceled','rejected')";
                        }
                    }
                    $purchasedUnitsSub = "COALESCE((SELECT SUM(COALESCE(pol." . $polQty . ",0)) FROM purchase_order_lines pol" . $statusJoin . "WHERE pol.product_id = products.id" . $purchaseStatusFilter . "), 0)";
                }
            }
        } catch (\Throwable $e) {
            $purchasedUnitsSub = '0';
        }

        // Asset count subquery - counts product assets linked via asset groups
        $assetCountSub = '0';
        try {
            if ($db->tableExists('product_asset_groups') && $db->tableExists('product_assets')) {
                $assetCountSub = "COALESCE((SELECT COUNT(DISTINCT pa.id) FROM product_asset_groups pag LEFT JOIN product_assets pa ON pa.asset_group_id = pag.id WHERE pag.product_id = products.id), 0)";
            }
        } catch (\Throwable $e) {
            $assetCountSub = '0';
        }

        $builder = $this->select('products.*, product_categories.name as category_name,
                COALESCE((SELECT SUM(sb.quantity) FROM stock_balances sb WHERE sb.product_id = products.id AND sb.variant_id IS NULL), 0) AS simple_stock,
                COALESCE((SELECT SUM(vi.quantity - vi.reserved) FROM product_variants pv LEFT JOIN variant_inventory vi ON vi.variant_id = pv.id WHERE pv.product_id = products.id), 0) AS variant_stock,
                (SELECT COUNT(*) FROM product_variants pv2 WHERE pv2.product_id = products.id) AS variant_count,
                ' . $soldUnitsSub . ' AS sold_units,
                ' . $purchasedUnitsSub . ' AS purchased_units,
                ' . $assetCountSub . ' AS asset_count')
                        ->join('product_categories', 'product_categories.id = products.category_id', 'left');

        // Apply deep search filter (includes variant code/name/attributes)
        if (!empty($searchTerm)) {
            $tokens = preg_split('/\s+/', trim((string)$searchTerm)) ?: [];
            $tokens = array_values(array_filter(array_map('trim', $tokens), static fn($v) => $v !== ''));
            if (!empty($tokens)) {
                $builder->groupStart();
                foreach ($tokens as $token) {
                    $like = $db->escapeLikeString($token);
                    $builder->groupStart();
                    $builder->like('products.name', $token)
                        ->orLike('products.code', $token)
                        ->orLike('products.sku', $token)
                        ->orLike('products.barcode', $token)
                        ->orLike('products.description', $token)
                        ->orLike('product_categories.name', $token)
                        ->orWhere("EXISTS (SELECT 1 FROM product_variants pvs WHERE pvs.product_id = products.id AND (pvs.art_number LIKE '%{$like}%' ESCAPE '!' OR pvs.name LIKE '%{$like}%' ESCAPE '!' OR pvs.attributes LIKE '%{$like}%' ESCAPE '!'))", null, false);
                    $builder->groupEnd();
                }
                $builder->groupEnd();
            }
        }

        // Apply category filter
        if (!empty($categoryFilter)) {
            $builder->where('products.category_id', $categoryFilter);
        }

        // Apply type filter
        if (!empty($typeFilter)) {
            $builder->where('products.detailed_type', $typeFilter);
        }

        // Removed: Tag/keyword filter was redundant since search already does deep matching via searchTerm parameter

    // Multiple attribute filters: ALL conditions must match in the SAME variant row.
        // Build a SINGLE EXISTS subquery combining all LIKE conditions so one variant must
        // simultaneously satisfy every attribute filter (prevents cross-variant false positives).
        if (!empty($attributeFilters) && is_array($attributeFilters)) {
            $variantConditions = [];
            $attrTextExpr = 'LOWER(pva.attributes)';
            $attrTextNormalizedExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$attrTextExpr}, ' ', ''), '-', ''), '(', ''), ')', ''), '&', ''), '/', ''), '_', ''), '.', ''), '\"', '')";
            foreach ($attributeFilters as $filter) {
                if (!is_array($filter)) continue;
                $attrN = trim((string)($filter['name'] ?? ''));
                $attrV = trim((string)($filter['value'] ?? ''));
                if (empty($attrN) || empty($attrV)) continue;
                $nl = strtolower($db->escapeLikeString($attrN));
                $vl = strtolower($db->escapeLikeString($attrV));
                $nlNorm = $db->escapeLikeString($this->normalizeLooseToken($attrN));
                $vlNorm = $db->escapeLikeString($this->normalizeLooseToken($attrV));
                // Each attribute pair must match in one variant row.
                // Use both raw and normalized matching so punctuation differences do not break filters.
                $variantConditions[] = "(({$attrTextExpr} LIKE '%{$nl}%' ESCAPE '!' AND {$attrTextExpr} LIKE '%{$vl}%' ESCAPE '!') OR ({$attrTextNormalizedExpr} LIKE '%{$nlNorm}%' ESCAPE '!' AND {$attrTextNormalizedExpr} LIKE '%{$vlNorm}%' ESCAPE '!'))";
            }
            if (!empty($variantConditions)) {
                $combined = implode(' AND ', $variantConditions);
                $builder->where("EXISTS (SELECT 1 FROM product_variants pva WHERE pva.product_id = products.id AND {$combined})", null, false);
            }
        }

        // Apply status filter.
        // Do not use empty() because '0' (inactive) is a valid filter value.
        if ($statusFilter !== null && $statusFilter !== '') {
            $isActive = ($statusFilter === 'active' || $statusFilter === '1' || $statusFilter === 1);
            $builder->where('products.is_active', $isActive ? 1 : 0);
        } else {
            // Default to active products only when no search term is provided
            if (empty($searchTerm)) {
                $builder->where('products.is_active', 1);
            }
        }

        // Role-based data access: hide service products
        if ($hideServices) {
            $builder->where('products.detailed_type !=', 'service');
        }

        // Role-based data access: restrict to allowed categories
        if (!empty($allowedCategoryIds)) {
            $builder->whereIn('products.category_id', $allowedCategoryIds);
        }

        // Filter by assets - show only products that have assets uploaded
        if (!empty($hasAssets) && ($hasAssets === '1' || $hasAssets === 1 || $hasAssets === true || $hasAssets === 'yes')) {
            $builder->where("EXISTS (SELECT 1 FROM product_asset_groups pag LEFT JOIN product_assets pa ON pa.asset_group_id = pag.id WHERE pag.product_id = products.id AND pa.id IS NOT NULL)", null, false);
        }

        $sortBy = trim(strtolower($sortBy));
        switch ($sortBy) {
            case 'oldest':
                $builder->orderBy('products.created_at', 'ASC')
                    ->orderBy('products.id', 'ASC');
                break;
            case 'most_sold':
                $builder->orderBy('sold_units', 'DESC', false)
                    ->orderBy('products.created_at', 'DESC');
                break;
            case 'ready_stock':
                $builder->orderBy('GREATEST(COALESCE(simple_stock,0), COALESCE(variant_stock,0))', 'DESC', false)
                    ->orderBy('products.created_at', 'DESC');
                break;
            case 'most_purchased':
                $builder->orderBy('purchased_units', 'DESC', false)
                    ->orderBy('products.created_at', 'DESC');
                break;
            case 'name_az':
                $builder->orderBy('products.name', 'ASC');
                break;
            case 'recent':
            default:
                $builder->orderBy('products.created_at', 'DESC')
                    ->orderBy('products.id', 'DESC');
                break;
        }

        return $builder->paginate($perPage);
    }

    /**
     * Get products with category information
     */
    public function getProductsWithCategory(): array
    {
        return $this->select('products.*, product_categories.name as category_name')
                    ->join('product_categories', 'product_categories.id = products.category_id', 'left')
                    ->where('products.is_active', true)
                    ->orderBy('products.name', 'ASC')
                    ->findAll();
    }

    /**
     * Get active products for dropdown
     */
    public function getActiveProductsForDropdown(): array
    {
        $products = $this->where('is_active', true)
                         ->orderBy('name', 'ASC')
                         ->findAll();

        $dropdown = ['' => 'Select Product'];
        foreach ($products as $product) {
            $dropdown[$product['id']] = $product['name'] . ' (' . $product['code'] . ')';
        }

        return $dropdown;
    }

    /**
     * Get product by code
     */
    public function getProductByCode(string $code): array|null
    {
        return $this->where('code', $code)
                    ->where('is_active', true)
                    ->first();
    }

    /**
     * Get product with processes
     */
    public function getProductWithProcesses(int $productId): array|null
    {
        $product = $this->find($productId);
        if (!$product) {
            return null;
        }

        $processModel = new ProcessModel();
        $product['processes'] = $processModel->getProcessesByProduct($productId);

        return $product;
    }

    /**
     * Return matching variants grouped by product_id for list-page rendering.
     * This exposes WHICH variants matched the active search/attribute filters.
     *
     * @param array $productIds
     * @param string|null $searchTerm
     * @param array $attributeFilters [{name: 'Color', value: 'Blue'}]
     * @param int $maxPerProduct
     * @return array<int, array<int, array<string,mixed>>>
     */
    public function getMatchingVariantsByProductIds(array $productIds, ?string $searchTerm = null, array $attributeFilters = [], int $maxPerProduct = 4): array
    {
        $ids = array_values(array_unique(array_map('intval', $productIds)));
        $ids = array_values(array_filter($ids, static fn($v) => $v > 0));
        if (empty($ids)) {
            return [];
        }

        $builder = $this->db->table('product_variants pv')
            ->select('pv.id, pv.product_id, pv.art_number, pv.name, pv.image, pv.attributes')
            ->whereIn('pv.product_id', $ids);

        if (!empty($searchTerm)) {
            $tokens = preg_split('/\s+/', trim((string)$searchTerm)) ?: [];
            $tokens = array_values(array_filter(array_map('trim', $tokens), static fn($v) => $v !== ''));
            foreach ($tokens as $token) {
                $like = $this->db->escapeLikeString($token);
                $builder->groupStart()
                    ->like('pv.art_number', $token)
                    ->orLike('pv.name', $token)
                    ->orWhere("LOWER(pv.attributes) LIKE '%" . strtolower($like) . "%' ESCAPE '!'", null, false)
                ->groupEnd();
            }
        }

        if (!empty($attributeFilters) && is_array($attributeFilters)) {
            $attrTextExpr = 'LOWER(pv.attributes)';
            $attrTextNormalizedExpr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$attrTextExpr}, ' ', ''), '-', ''), '(', ''), ')', ''), '&', ''), '/', ''), '_', ''), '.', ''), '\"', '')";
            foreach ($attributeFilters as $filter) {
                if (!is_array($filter)) {
                    continue;
                }
                $attrN = trim((string)($filter['name'] ?? ''));
                $attrV = trim((string)($filter['value'] ?? ''));
                if ($attrN === '' || $attrV === '') {
                    continue;
                }
                $nl = strtolower($this->db->escapeLikeString($attrN));
                $vl = strtolower($this->db->escapeLikeString($attrV));
                $nlNorm = $this->db->escapeLikeString($this->normalizeLooseToken($attrN));
                $vlNorm = $this->db->escapeLikeString($this->normalizeLooseToken($attrV));
                $builder->where("(({$attrTextExpr} LIKE '%{$nl}%' ESCAPE '!' AND {$attrTextExpr} LIKE '%{$vl}%' ESCAPE '!') OR ({$attrTextNormalizedExpr} LIKE '%{$nlNorm}%' ESCAPE '!' AND {$attrTextNormalizedExpr} LIKE '%{$vlNorm}%' ESCAPE '!'))", null, false);
            }
        }

        $rows = $builder
            ->orderBy('pv.product_id', 'ASC')
            ->orderBy('pv.id', 'ASC')
            ->get()
            ->getResultArray();

        $grouped = [];
        foreach ($rows as $row) {
            $pid = (int)($row['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }

            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [];
            }
            if (count($grouped[$pid]) >= $maxPerProduct) {
                continue;
            }

            $attrs = [];
            if (!empty($row['attributes'])) {
                try {
                    $decoded = json_decode((string)$row['attributes'], true) ?? [];
                    if (is_array($decoded)) {
                        foreach ($decoded as $k => $v) {
                            $kk = trim((string)$k);
                            $vv = is_scalar($v) ? trim((string)$v) : '';
                            if ($kk !== '' && $vv !== '') {
                                $attrs[$kk] = $vv;
                            }
                        }
                    }
                } catch (\Throwable $_) {
                    $attrs = [];
                }
            }

            $grouped[$pid][] = [
                'id' => (int)($row['id'] ?? 0),
                'art_number' => (string)($row['art_number'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
                'image' => trim((string)($row['image'] ?? '')),
                'attributes' => $attrs,
            ];
        }

        return $grouped;
    }

    /**
     * When a search term specifically matches a variant art_number (but not the
     * parent product's own code/name/sku), replace the parent template row in
     * the listing with individual variant rows so the user sees the specific
     * variant — not the generic template product.
     *
     * @param string $searchTerm  The raw search term from the request.
     * @param array  $products    The paginated product rows from getProductsWithFilters().
     * @return array              Possibly-modified product rows.
     */
    public function promoteVariantRowsIfVariantSearch(string $searchTerm, array $products): array
    {
        if ($searchTerm === '' || empty($products)) {
            return $products;
        }

        $db = \Config\Database::connect();
        if (!$db->tableExists('product_variants')) {
            return $products;
        }

        $tokens = array_values(array_filter(
            preg_split('/\s+/', trim($searchTerm)) ?: [],
            static fn($v) => trim((string)$v) !== ''
        ));
        if (empty($tokens)) {
            return $products;
        }

        // Collect template product IDs from the current result set.
        $templateIds = [];
        foreach ($products as $p) {
            if (($p['product_type'] ?? '') === 'variable') {
                $templateIds[] = (int)($p['id'] ?? 0);
            }
        }
        if (empty($templateIds)) {
            return $products;
        }

        // For each template product, check if ALL tokens match the parent product
        // fields directly (code, sku, name, barcode). If they do, no promotion needed
        // — keep it as the template row. If they do NOT match the parent directly
        // but a variant art_number matches, promote the variant(s) instead.
        $productIndexById = [];
        foreach ($products as $i => $p) {
            $productIndexById[(int)($p['id'] ?? 0)] = $i;
        }

        // Fetch matching variants for the template products.
        try {
            $variantCols = $db->getFieldNames('product_variants');
        } catch (\Throwable $_) {
            $variantCols = [];
        }
        $hasImage = in_array('image', $variantCols, true);

        $selectCols = 'pv.id AS variant_id, pv.product_id, pv.art_number, pv.name AS variant_name';
        if ($hasImage) {
            $selectCols .= ', pv.image AS variant_image';
        }
        $selectCols .= ', pv.attributes';

        $builder = $db->table('product_variants pv')
            ->select($selectCols)
            ->whereIn('pv.product_id', $templateIds);

        // Match ALL tokens against variant art_number or variant name.
        foreach ($tokens as $token) {
            $like = $db->escapeLikeString(trim((string)$token));
            $builder->where("(pv.art_number LIKE '%{$like}%' ESCAPE '!' OR pv.name LIKE '%{$like}%' ESCAPE '!')", null, false);
        }

        try {
            $variantRows = $builder->orderBy('pv.art_number', 'ASC')->get()->getResultArray();
        } catch (\Throwable $_) {
            return $products;
        }

        if (empty($variantRows)) {
            return $products;
        }

        // Group matching variant rows by parent product_id.
        $variantsByParent = [];
        foreach ($variantRows as $vr) {
            $pid = (int)($vr['product_id'] ?? 0);
            if ($pid > 0) {
                $variantsByParent[$pid][] = $vr;
            }
        }

        // Fetch stock per variant from stock_balances.
        $allVariantIds = array_map(static fn($vr) => (int)($vr['variant_id'] ?? 0), $variantRows);
        $allVariantIds = array_values(array_filter($allVariantIds));
        $variantStockMap = [];
        if (!empty($allVariantIds) && $db->tableExists('stock_balances')) {
            try {
                $stockRows = $db->table('stock_balances')
                    ->select('variant_id, SUM(quantity) AS total_qty')
                    ->whereIn('variant_id', $allVariantIds)
                    ->groupBy('variant_id')
                    ->get()->getResultArray();
                foreach ($stockRows as $sr) {
                    if (!empty($sr['variant_id'])) {
                        $variantStockMap[(int)$sr['variant_id']] = (float)($sr['total_qty'] ?? 0);
                    }
                }
            } catch (\Throwable $_) {}
        }
        // Fallback to variant_inventory if stock_balances gave nothing.
        if (empty($variantStockMap) && $db->tableExists('variant_inventory')) {
            try {
                $stockRows = $db->table('variant_inventory')
                    ->select('variant_id, SUM(quantity) AS total_qty')
                    ->whereIn('variant_id', $allVariantIds)
                    ->groupBy('variant_id')
                    ->get()->getResultArray();
                foreach ($stockRows as $sr) {
                    if (!empty($sr['variant_id'])) {
                        $variantStockMap[(int)$sr['variant_id']] = (float)($sr['total_qty'] ?? 0);
                    }
                }
            } catch (\Throwable $_) {}
        }

        // Decide for each template product whether to promote it.
        // Promotion happens when the parent product's own code/sku/name/barcode
        // does NOT match the search tokens — meaning the result came purely via variant.
        $result = [];
        foreach ($products as $p) {
            $pid = (int)($p['id'] ?? 0);
            if (($p['product_type'] ?? '') !== 'variable' || !isset($variantsByParent[$pid])) {
                // Not a template or no variants matched — keep as-is.
                $result[] = $p;
                continue;
            }

            // Check if parent product fields match ALL tokens directly.
            // If not all tokens match parent fields, keep variant promotion active.
            $parentMatchesDirectly = true;
            $parentSearchHaystack = strtolower(
                ($p['code'] ?? '') . ' ' . ($p['sku'] ?? '') . ' ' .
                ($p['name'] ?? '') . ' ' . ($p['barcode'] ?? '')
            );
            foreach ($tokens as $token) {
                if (stripos($parentSearchHaystack, strtolower(trim((string)$token))) === false) {
                    $parentMatchesDirectly = false;
                    break;
                }
            }

            if ($parentMatchesDirectly) {
                // Parent itself matches — keep the template row (user searched product name/code).
                $result[] = $p;
                continue;
            }

            // Parent only matched via variant — expand into individual variant rows.
            foreach ($variantsByParent[$pid] as $vr) {
                $vid = (int)($vr['variant_id'] ?? 0);
                $variantCode = trim((string)($vr['art_number'] ?? ''));
                $stockQty = $variantStockMap[$vid] ?? 0.0;

                // Build an attributes summary string for the description cell.
                $attrSummary = '';
                if (!empty($vr['attributes'])) {
                    $decoded = json_decode((string)$vr['attributes'], true);
                    if (is_array($decoded)) {
                        $pairs = [];
                        $isList = !empty($decoded) && array_keys($decoded) === range(0, count($decoded) - 1);
                        if ($isList) {
                            foreach ($decoded as $item) {
                                if (!is_array($item)) continue;
                                $k = trim((string)($item['name'] ?? ($item['attribute'] ?? ($item['key'] ?? ''))));
                                $v = trim((string)($item['value'] ?? ''));
                                if ($k !== '' && $v !== '') $pairs[] = $k . ': ' . $v;
                            }
                        } else {
                            foreach ($decoded as $k => $v) {
                                $kk = trim((string)$k);
                                $vv = is_scalar($v) ? trim((string)$v) : '';
                                if ($kk !== '' && $vv !== '') $pairs[] = $kk . ': ' . $vv;
                            }
                        }
                        $attrSummary = implode(' | ', $pairs);
                    }
                }

                // Shape the variant row to look like a product row for the view.
                $variantRow = $p; // inherit all parent fields (category, unit, status, dates, etc.)
                $variantRow['code']          = $variantCode !== '' ? $variantCode : $p['code'];
                $variantRow['product_type']  = ''; // Remove "Template" badge — this is a specific variant
                $variantRow['simple_stock']  = $stockQty;
                $variantRow['variant_stock'] = $stockQty;
                $variantRow['variant_count'] = 0;
                $variantRow['_is_variant_row'] = true;
                $variantRow['_variant_id']     = $vid;
                $variantRow['_variant_name']   = trim((string)($vr['variant_name'] ?? ''));
                $variantRow['_variant_attrs_summary'] = $attrSummary;
                // Use variant image if available
                if ($hasImage && !empty($vr['variant_image'])) {
                    $variantRow['images'] = json_encode(['../variants/' . ltrim($vr['variant_image'], '/')]);
                }
                $result[] = $variantRow;
            }
        }

        return $result;
    }

    /**
     * Search products
     */
    public function searchProducts(string $query): array
    {
        $q = trim($query);
        if ($q === '') return [];

        // split into tokens for broader matching (e.g. "red widget 42")
        $tokens = array_values(array_filter(preg_split('/\s+/', $q), function($token){ return trim((string)$token) !== ''; }));

        $builder = $this->select('products.*, product_categories.name as category_name, vendors.name as vendor_name')
                        ->join('product_categories', 'product_categories.id = products.category_id', 'left')
                        ->join('vendors', 'vendors.id = products.vendor_id', 'left')
                        // Treat NULL as active for legacy rows where is_active wasn't populated.
                        ->groupStart()
                            ->where('products.is_active', 1)
                            ->orWhere('products.is_active IS NULL', null, false)
                        ->groupEnd();

        $builder->groupStart();
        foreach ($tokens as $t) {
            $t = trim((string)$t);
            if ($t === '') continue;
            $builder->groupStart();
            $builder->like('products.name', $t)
                    ->orLike('products.code', $t)
                    ->orLike('products.sku', $t)
                    ->orLike('products.barcode', $t)
                    ->orLike('products.description', $t)
                    ->orLike('vendors.name', $t);
            $builder->groupEnd();
        }
        $builder->groupEnd();

        $builder->orderBy('products.name', 'ASC');
        $productRows = $builder->findAll(50);
        $variantRows = $this->searchVariantMatches($tokens, 50);

        $limit = 50;
        $combined = array_merge($variantRows, $productRows);
        $seen = [];
        $final = [];
        foreach ($combined as $row) {
            $key = !empty($row['variant_id']) ? ('v' . $row['variant_id']) : ('p' . ($row['product_id'] ?? $row['id'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $final[] = $row;
            if (count($final) >= $limit) {
                break;
            }
        }
        return $final;
    }

    /**
     * Search variant art numbers/names as a supplement to the core product search.
     */
    private function searchVariantMatches(array $tokens, int $limit = 20): array
    {
        if (empty($tokens)) {
            return [];
        }

        $db = \Config\Database::connect();
        if (!$db->tableExists('product_variants')) {
            return [];
        }

        $productFields = [];
        try {
            $productFields = $db->getFieldNames('products');
        } catch (\Throwable $_) {
            $productFields = [];
        }
        $variantFields = [];
        try {
            $variantFields = $db->getFieldNames('product_variants');
        } catch (\Throwable $_) {
            $variantFields = [];
        }

        $selectFields = [
            'pv.id as variant_id',
            'pv.product_id as parent_product_id',
            'pv.art_number',
            'pv.name as variant_name',
            'pv.price',
            'pv.cost',
            'pv.attributes',
            'p.code as parent_code',
            'p.name as parent_name',
            'p.description as parent_description',
            'p.unit as parent_unit',
            'v.name as parent_vendor_name'
        ];

        if (in_array('weight', $variantFields, true)) {
            $selectFields[] = 'pv.weight';
        }
        if (in_array('image', $variantFields, true)) {
            $selectFields[] = 'pv.image as variant_image';
        }

        $productOptionalMap = [
            'unit_weight' => 'p.unit_weight as parent_unit_weight',
            'weight' => 'p.weight as parent_weight',
            'weight_unit' => 'p.weight_unit as parent_weight_unit',
            'sale_price' => 'p.sale_price as parent_sale_price',
            'sale_currency' => 'p.sale_currency as parent_sale_currency',
            'special_price' => 'p.special_price as parent_special_price',
            'tax_rate' => 'p.tax_rate as parent_tax_rate',
            'tax' => 'p.tax as parent_tax',
            'image' => 'p.image as parent_image',
            'images' => 'p.images as parent_images',
            'vendor_id' => 'p.vendor_id as parent_vendor_id',
            'current_stock' => 'p.current_stock as parent_current_stock',
        ];
        foreach ($productOptionalMap as $field => $selectExpr) {
            if (in_array($field, $productFields, true)) {
                $selectFields[] = $selectExpr;
            }
        }

        $builder = $db->table('product_variants pv')
            ->select($selectFields)
            ->join('products p', 'p.id = pv.product_id', 'left')
            ->join('vendors v', 'v.id = p.vendor_id', 'left')
            ->groupStart()
                ->where('p.is_active', 1)
                ->orWhere('p.is_active IS NULL', null, false)
            ->groupEnd();

        $builder->groupStart();
        foreach ($tokens as $t) {
            $token = trim((string)$t);
            if ($token === '') continue;
            $builder->groupStart();
            $builder->like('pv.art_number', $token)
                    ->orLike('pv.name', $token)
                    ->orLike('pv.attributes', $token)
                    ->orLike('p.code', $token)
                    ->orLike('p.sku', $token)
                    ->orLike('p.name', $token);
            $builder->groupEnd();
        }
        $builder->groupEnd();

        $builder->orderBy('pv.art_number', 'ASC')->limit($limit);
        try {
            $variantRows = $builder->get()->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'ProductModel::searchVariantMatches failed: ' . $e->getMessage());
            return [];
        }

        $variantIds = array_values(array_filter(array_map(function($row){ return isset($row['variant_id']) ? (int)$row['variant_id'] : null; }, $variantRows)));
        $variantStockMap = [];
        if (!empty($variantIds) && $db->tableExists('variant_inventory')) {
            try {
                $stockRows = $db->table('variant_inventory')
                    ->select('variant_id, SUM(quantity) as qty')
                    ->whereIn('variant_id', $variantIds)
                    ->groupBy('variant_id')
                    ->get()
                    ->getResultArray();
                foreach ($stockRows as $stockRow) {
                    $variantStockMap[(int)$stockRow['variant_id']] = (float)($stockRow['qty'] ?? 0);
                }
            } catch (\Throwable $_) {
                // best effort: ignore if inventory table unavailable
            }
        }

        $mapped = [];
        foreach ($variantRows as $variant) {
            $mapped[] = $this->mapVariantToSearchRow($variant, $variantStockMap);
        }
        return $mapped;
    }

    private function mapVariantToSearchRow(array $variant, array $stockMap): array
    {
        $parentId = (int)($variant['parent_product_id'] ?? $variant['product_id'] ?? 0);
        $variantId = isset($variant['variant_id']) ? (int)$variant['variant_id'] : 0;
        $variantCode = trim((string)($variant['art_number'] ?? ''));
        $variantName = $variant['variant_name'] ?? '';
        $parentName = $variant['parent_name'] ?? '';
        $displayName = $parentName ?: $variantName;
        $description = '';
        if (!empty($variant['attributes'])) {
            $description = (string)$variant['attributes'];
        } elseif ($variantName && $variantName !== $displayName) {
            $description = $variantName;
        }
        $unit = $variant['unit'] ?? ($variant['parent_unit'] ?? 'pcs');
        $weightValue = $variant['weight'] ?? ($variant['unit_weight'] ?? ($variant['parent_unit_weight'] ?? ($variant['parent_weight'] ?? 0.0)));
        $salePrice = isset($variant['price']) && $variant['price'] !== '' ? (float)$variant['price'] : (float)($variant['parent_sale_price'] ?? 0);
        $specialPrice = isset($variant['parent_special_price']) && $variant['parent_special_price'] !== '' ? (float)$variant['parent_special_price'] : null;
        $stock = $stockMap[$variantId] ?? (float)($variant['parent_current_stock'] ?? 0);
        if (!empty($variant['variant_image'])) {
            $image = 'uploads/variants/' . ltrim((string)$variant['variant_image'], '/');
        } else {
            $image = $variant['parent_image'] ?? null;
        }

        return [
            'id' => $parentId,
            'product_id' => $parentId,
            'variant_id' => $variantId > 0 ? $variantId : null,
            'code' => $variantCode ?: ($variant['parent_code'] ?? ''),
            'name' => $displayName,
            'description' => $description,
            'unit' => $unit,
            'unit_weight' => (float)$weightValue,
            'weight' => (float)$weightValue,
            'weight_unit' => $variant['parent_weight_unit'] ?? ($variant['weight_unit'] ?? null),
            'sale_price' => $salePrice,
            'special_price' => $specialPrice,
            'sale_currency' => $variant['parent_sale_currency'] ?? '',
            'tax_rate' => isset($variant['parent_tax_rate']) ? (float)$variant['parent_tax_rate'] : (float)($variant['parent_tax'] ?? 0),
            'image' => $image,
            'images' => $variant['parent_images'] ?? null,
            'current_stock' => (float)$stock,
            'vendor_id' => $variant['parent_vendor_id'] ?? null,
            'vendor_name' => $variant['parent_vendor_name'] ?? null,
            'variant_art_number' => $variantCode,
            'variant_name' => $variantName,
            'variant_price' => isset($variant['price']) && $variant['price'] !== '' ? (float)$variant['price'] : null,
            'attributes' => $variant['attributes'] ?? null,
            'variant_image' => $variant['variant_image'] ?? null,
        ];
    }

    /**
     * Get product with category details
     */
    public function getProductWithDetails(int $productId): array|null
    {
        return $this->select('products.*, product_categories.name as category_name')
                    ->join('product_categories', 'product_categories.id = products.category_id', 'left')
                    ->where('products.id', $productId)
                    ->first();
    }

    /**
     * Get products with work order statistics
     */
    public function getProductsWithStats(): array
    {
        return $this->select('products.*, 
                             product_categories.name as category_name,
                             COUNT(DISTINCT work_orders.id) as total_work_orders,
                             SUM(CASE WHEN work_orders.status = "completed" THEN 1 ELSE 0 END) as completed_work_orders,
                             SUM(CASE WHEN work_orders.status IN ("planned", "in_progress") THEN 1 ELSE 0 END) as active_work_orders')
                    ->join('product_categories', 'product_categories.id = products.category_id', 'left')
                    ->join('work_orders', 'work_orders.product_id = products.id', 'left')
                    ->where('products.is_active', true)
                    ->groupBy('products.id')
                    ->orderBy('products.name', 'ASC')
                    ->findAll();
    }

    /**
     * Update stock quantity for a product and record a transaction
     */
    public function updateStock(int $productId, float $quantity, string $transactionType, string $referenceType, int $referenceId = null, int $createdBy = null, float $unitCost = null): bool
    {
        $product = $this->find($productId);
        if (!$product) {
            return false;
        }

        $this->db->transStart();

        // Calculate new stock level
        $newStock = $product['current_stock'] ?? 0;
        if ($transactionType === 'in') {
            $newStock += $quantity;
        } elseif ($transactionType === 'out') {
            $newStock -= $quantity;
            if ($newStock < 0) {
                $newStock = 0; // prevent negative stock
            }
        } else { // adjustment
            $newStock = $quantity;
        }

        // Update product stock
        $this->update($productId, ['current_stock' => $newStock, 'unit_cost' => $unitCost ?? ($product['unit_cost'] ?? 0)]);

        // Record transaction
        $stockTransactionModel = new \App\Models\ProductStockTransactionModel();
        $transactionData = [
            'product_id' => $productId,
            'transaction_type' => $transactionType,
            'quantity' => $quantity,
            'unit_cost' => $unitCost ?? ($product['unit_cost'] ?? null),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_by' => $createdBy,
        ];

        $stockTransactionModel->insert($transactionData);

        $this->db->transComplete();
        return $this->db->transStatus();
    }

    /**
     * Validate product code for uniqueness (excluding current product for updates)
     */
    public function validateProductCode(string $code, int $excludeId = null): bool
    {
        $query = $this->where('code', $code);
        if ($excludeId) {
            $query->where('id !=', $excludeId);
        }
        return $query->countAllResults() === 0;
    }

    /**
     * Safely get current stock for a product (0 if missing)
     */
    public function getCurrentStock(int $productId): float
    {
        $p = $this->find($productId);
        if (!$p) return 0.0;
        return isset($p['current_stock']) ? (float)$p['current_stock'] : 0.0;
    }

    /**
     * Search by barcode
     */
    public function searchByBarcode(string $code): array
    {
        return $this->where('barcode', $code)->where('is_active', 1)->findAll();
    }

    /**
     * Search by SKU
     */
    public function searchBySku(string $sku): array
    {
        return $this->where('sku', $sku)->where('is_active', 1)->findAll();
    }

    /**
     * Search by name or partial term
     */
    public function searchByName(string $term): array
    {
        return $this->like('name', $term)->orLike('description', $term)->where('is_active',1)->orderBy('name','ASC')->findAll(20);
    }

    /**
     * Get product with vendor info and pricing
     */
    public function getWithVendorInfo(int $productId): array|null
    {
        $p = $this->find($productId);
        if (!$p) return null;
        if (!empty($p['vendor_id'])) {
            $vendorModel = new \App\Models\VendorModel();
            $p['vendor'] = $vendorModel->find($p['vendor_id']);
        } else {
            $p['vendor'] = null;
        }
        return $p;
    }

    /**
     * Minimal stock info helper
     */
    public function getStockInfo(int $productId): array
    {
        $p = $this->find($productId);
        return [
            'current_stock' => isset($p['current_stock']) ? (float)$p['current_stock'] : 0,
            'is_low' => (isset($p['current_stock']) && $p['current_stock'] <= 0)
        ];
    }

    /**
     * Simple pricing info
     */
    public function getPricingInfo(int $productId): array
    {
        $p = $this->find($productId);
        if (!$p) return ['sale_price' => 0, 'sale_currency' => $p['sale_currency'] ?? 'USD'];
        return ['sale_price' => (float)($p['sale_price'] ?? 0), 'sale_currency' => $p['sale_currency'] ?? 'USD', 'cost_price' => (float)($p['cost_price'] ?? 0), 'cost_currency' => $p['cost_currency'] ?? 'USD'];
    }
}

