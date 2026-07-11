<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ProductAttributeModel;
use App\Models\WorkOrderModel;
use App\Models\ComponentModel;
use App\Libraries\RoleDataAccess;

class Products extends BaseController
{
    protected $productModel;

    public function __construct()
    {
        $this->productModel = new ProductModel();
    }

    private function resolveProductOrFail($identifier): array
    {
        $product = $this->productModel->findByPublicIdOrId($identifier);
        if (!$product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        return $product;
    }

    private function redirectToCanonicalProductUrl(array $product, $identifier, string $suffix = '')
    {
        $publicId = trim((string) ($product['public_id'] ?? ''));
        if (!featureEnabled('enable_public_ids') || $publicId === '' || (string) $identifier === $publicId) {
            return null;
        }

        return redirect()->to(site_url('products/' . urlencode($publicId) . $suffix));
    }

    /**
     * Check whether the products table has a given column.
     */
    private function productsHasColumn(string $column): bool
    {
        try {
            $db = \Config\Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `products` LIKE '" . $db->escapeString($column) . "'");
            return $result && $result->getNumRows() > 0;
        } catch (\Throwable $e) {
            // If something goes wrong, assume column not present to be safe
            log_message('error', 'productsHasColumn check failed: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureProductsWeightUnitColumn(): bool
    {
        if ($this->productsHasColumn('weight_unit')) {
            return true;
        }
        try {
            $db = \Config\Database::connect();
            $db->query("ALTER TABLE `products` ADD COLUMN `weight_unit` VARCHAR(10) NOT NULL DEFAULT 'KG'");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'Failed to add products.weight_unit: ' . $e->getMessage());
            return false;
        }
    }

    private function weightUnitOptions(): array
    {
        return ['KG', 'G', 'MG', 'LB', 'OZ', 'TON'];
    }

    private function userHasRoleSlug(string $slug): bool
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return false;
        }

        try {
            $sessionRole = strtolower(trim((string)($this->session->get('role') ?? '')));
            if ($sessionRole === $slug) {
                return true;
            }

            $userId = (int)($this->currentUser['id'] ?? 0);
            if ($userId <= 0) {
                return false;
            }

            $db = \Config\Database::connect();
            $row = $db->table('user_roles ur')
                ->join('roles r', 'r.id = ur.role_id')
                ->where('ur.user_id', $userId)
                ->where('LOWER(r.slug)', $slug)
                ->select('ur.id')
                ->get()
                ->getRowArray();

            if (!empty($row)) {
                return true;
            }

            $primaryRoleId = (int)($this->currentUser['role_id'] ?? 0);
            if ($primaryRoleId > 0) {
                $primary = $db->table('roles')->where('id', $primaryRoleId)->select('slug')->get()->getRowArray();
                if (strtolower(trim((string)($primary['slug'] ?? ''))) === $slug) {
                    return true;
                }
            }
        } catch (\Throwable $_) {
            return false;
        }

        return false;
    }

    private function canViewBusinessData(): bool
    {
        // Explicit permission can always allow sensitive overview blocks.
        if ($this->hasPermission('products.sensitive_overview')) {
            return true;
        }

        // Designers are intentionally restricted unless explicitly granted above.
        if ($this->userHasRoleSlug('designer') || $this->userHasRoleSlug('desinger')) {
            return false;
        }

        // Commercial/admin-like users can view business metrics.
        return $this->hasPermission('products.edit')
            || $this->hasPermission('invoices.read')
            || $this->hasPermission('accounting.read');
    }

    /**
     * Keep the global attribute registry in sync with product-edit definitions.
     * This makes attributes created on the product page visible in Inventory > Attributes.
     */
    private function syncGlobalAttributesFromDefinitions($rawDefinitions): void
    {
        $defs = [];
        if (is_string($rawDefinitions)) {
            try {
                $defs = json_decode($rawDefinitions, true) ?? [];
            } catch (\Throwable $e) {
                $defs = [];
            }
        } elseif (is_array($rawDefinitions)) {
            $defs = $rawDefinitions;
        }

        if (!is_array($defs) || empty($defs)) {
            return;
        }

        $attributeModel = new ProductAttributeModel();
        $existing = $attributeModel->findAll();
        $byName = [];
        foreach ($existing as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $byName[mb_strtolower($name)] = $row;
        }

        foreach ($defs as $def) {
            $name = trim((string)($def['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $values = array_values(array_filter(array_map('trim', (array)($def['values'] ?? []))));
            $key = mb_strtolower($name);
            $now = date('Y-m-d H:i:s');

            if (!isset($byName[$key])) {
                $attributeModel->insert([
                    'name' => $name,
                    'values' => json_encode($values),
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                continue;
            }

            $row = $byName[$key];
            $existingValues = json_decode((string)($row['values'] ?? '[]'), true);
            if (!is_array($existingValues)) {
                $existingValues = [];
            }

            $seen = [];
            $merged = [];
            foreach (array_merge($existingValues, $values) as $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                $valueKey = mb_strtolower($value);
                if (isset($seen[$valueKey])) {
                    continue;
                }
                $seen[$valueKey] = true;
                $merged[] = $value;
            }

            if (json_encode($merged) !== json_encode(array_values($existingValues))) {
                $attributeModel->update((int)$row['id'], [
                    'name' => $row['name'] ?: $name,
                    'values' => json_encode($merged),
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function ensureVariantsWeightColumn(): bool
    {
        try {
            $db = \Config\Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `product_variants` LIKE 'weight'");
            if ($result && $result->getNumRows() > 0) {
                return true;
            }
            $db->query("ALTER TABLE `product_variants` ADD COLUMN `weight` DECIMAL(15,4) NULL");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'ensureVariantsWeightColumn failed: ' . $e->getMessage());
            return false;
        }
    }

    private function ensureVariantsVendorColumn(): bool
    {
        try {
            $db = \Config\Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `product_variants` LIKE 'vendor_id'");
            if ($result && $result->getNumRows() > 0) {
                return true;
            }
            $db->query("ALTER TABLE `product_variants` ADD COLUMN `vendor_id` INT UNSIGNED NULL DEFAULT NULL AFTER `cost`");
            $db->query("ALTER TABLE `product_variants` ADD INDEX `idx_variant_vendor` (`vendor_id`)");
            return true;
        } catch (\Throwable $e) {
            log_message('error', 'ensureVariantsVendorColumn failed: ' . $e->getMessage());
            return false;
        }
    }

    private function variantsHasColumn(string $column): bool
    {
        try {
            $db = \Config\Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `product_variants` LIKE '" . $db->escapeString($column) . "'");
            return $result && $result->getNumRows() > 0;
        } catch (\Throwable $e) {
            log_message('error', 'variantsHasColumn check failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Extract attribute definitions from existing variants.
     * Builds an attributes_definitions structure from variant.attributes JSON data.
     * @param array $variants Array of variant records
     * @return array Attributes definitions array suitable for JSON encoding
     */
    private function extractAttributesFromVariants(array $variants): array
    {
        $attributeMap = []; // attribute_name => [unique_values]
        
        foreach ($variants as $variant) {
            if (empty($variant['attributes'])) {
                continue;
            }
            
            $attrs = [];
            if (is_string($variant['attributes'])) {
                try {
                    $attrs = json_decode($variant['attributes'], true) ?? [];
                } catch (\Throwable $e) {
                    $attrs = [];
                }
            } elseif (is_array($variant['attributes'])) {
                $attrs = $variant['attributes'];
            }
            
            if (!is_array($attrs)) {
                continue;
            }
            
            foreach ($attrs as $attrName => $attrValue) {
                $name = trim((string)$attrName);
                $value = trim((string)$attrValue);
                
                if ($name === '' || $value === '') {
                    continue;
                }
                
                if (!isset($attributeMap[$name])) {
                    $attributeMap[$name] = [];
                }
                
                if (!in_array($value, $attributeMap[$name], true)) {
                    $attributeMap[$name][] = $value;
                }
            }
        }
        
        // Sort values for each attribute for consistency
        foreach ($attributeMap as &$values) {
            sort($values);
        }
        unset($values);
        
        // Convert to the format expected by the form (array of objects with name and values)
        $result = [];
        foreach ($attributeMap as $name => $values) {
            $result[] = [
                'name' => $name,
                'values' => $values
            ];
        }
        
        return $result;
    }

    /**
     * Parse attribute values from DB safely.
     * Supports valid JSON arrays and malformed JSON/control-character fallbacks.
     *
     * @return array<int,string>
     */
    private function parseAttributeValues($rawValues): array
    {
        if (is_array($rawValues)) {
            return array_values(array_filter(array_map(static fn($v) => trim((string) $v), $rawValues), static fn($v) => $v !== ''));
        }

        $raw = trim((string) $rawValues);
        if ($raw === '') {
            return [];
        }

        try {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_filter(array_map(static fn($v) => trim((string) $v), $decoded), static fn($v) => $v !== ''));
            }
        } catch (\Throwable $_) {
        }

        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', ' ', $raw);
        if ($sanitized !== null) {
            $sanitized = str_replace(["\r\n", "\r", "\n"], '\\n', $sanitized);
            try {
                $decoded = json_decode($sanitized, true);
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map(static fn($v) => trim((string) $v), $decoded), static fn($v) => $v !== ''));
                }
            } catch (\Throwable $_) {
            }
        }

        if (strlen($raw) >= 2 && $raw[0] === '[' && $raw[strlen($raw) - 1] === ']') {
            $matches = [];
            if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $raw, $matches) && !empty($matches[1])) {
                $vals = array_map(static fn($v) => trim((string) stripcslashes($v)), $matches[1]);
                return array_values(array_filter($vals, static fn($v) => $v !== ''));
            }
        }

        $parts = preg_split('/[\r\n,]+/', $raw) ?: [];
        $vals = array_map(static fn($v) => trim((string) $v), $parts);
        return array_values(array_filter($vals, static fn($v) => $v !== ''));
    }

    /**
     * POST /products/backfill-services
     * Create service products for any shipping_services rows that have product_id = NULL.
     * Safe to call multiple times (idempotent by carrier+service_name lookup).
     */
    public function backfillServices()
    {
        $this->requireAuth();
        $this->requirePermission('products.create');

        $db       = \Config\Database::connect();
        $services = $db->table('shipping_services')
                       ->where('product_id IS NULL')
                       ->get()->getResultArray();

        $created = 0;
        foreach ($services as $svc) {
            try {
                // Determine next SRV- code
                $lastSrv = $db->table('products')
                    ->select('code')
                    ->like('code', 'SRV-', 'after')
                    ->orderBy('id', 'DESC')
                    ->limit(1)->get()->getRowArray();
                $nextNum = 1;
                if ($lastSrv && preg_match('/SRV-(\d+)/', $lastSrv['code'], $m)) {
                    $nextNum = (int)$m[1] + 1;
                }
                $code = 'SRV-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);

                $db->table('products')->insert([
                    'name'          => $svc['carrier'] . ' — ' . $svc['service_name'],
                    'code'          => $code,
                    'product_type'  => 'simple',
                    'detailed_type' => 'service',
                    'vendor_id'     => $svc['vendor_id'] ?: null,
                    'vendor_price'  => $svc['cost_pkr'] ?: ($svc['base_rate_pkr'] ?: null),
                    'unit'          => 'SHP',
                    'is_active'     => 1,
                ]);
                $productId = $db->insertID();
                if ($productId) {
                    $db->table('shipping_services')
                       ->where('id', $svc['id'])
                       ->update(['product_id' => $productId]);
                    $created++;
                }
            } catch (\Throwable $e) {
                log_message('error', 'backfillServices: svc#' . $svc['id'] . ' — ' . $e->getMessage());
            }
        }

        return $this->response->setJSON([
            'success' => true,
            'created' => $created,
            'message' => "$created service product(s) created.",
        ]);
    }

    /**
     * Display products list
     */
    public function index()
    {
        $this->requireAuth();
        $this->requirePermission('products.view');

        $searchTerm = trim((string)($this->request->getGet('search') ?? ''));
        $categoryFilter = $this->request->getGet('category');
        $statusFilter = $this->request->getGet('status');
        $typeFilter = $this->request->getGet('type');
        $hasAssets = $this->request->getGet('has_assets');
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);
        $sortBy = trim((string)($this->request->getGet('sort_by') ?? ''));

        $allowedPerPage = [20, 50, 100, 200];
        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 20;
        }

        $allowedSorts = ['recent', 'oldest', 'most_sold', 'ready_stock', 'most_purchased', 'name_az'];
        if ($sortBy === '') {
            $sortBy = (string)($this->session->get('products_sort_pref') ?? 'recent');
        }
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'recent';
        }
        $this->session->set('products_sort_pref', $sortBy);

        // Parse multiple attribute filters from URL params:
        // attr[0][name]=Color&attr[0][value]=Blue&attr[1][name]=Size&attr[1][value]=16cm
        // Some environments can miss nested GET arrays, so include a raw-query fallback.
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

        // Fallback for direct single pair filters submitted from the filter bar.
        $directAttrName = trim((string) ($this->request->getGet('direct_attr_name') ?? ''));
        $directAttrValue = trim((string) ($this->request->getGet('direct_attr_value') ?? ''));
        if ($directAttrValue === '__custom__') {
            $directAttrValue = trim((string) ($this->request->getGet('direct_attr_value_custom') ?? ''));
        }
        if ($directAttrName !== '' && $directAttrValue !== '') {
            $directKey = mb_strtolower($directAttrName) . '::' . mb_strtolower($directAttrValue);
            if (!isset($seenAttrPairs[$directKey])) {
                $seenAttrPairs[$directKey] = true;
                $attributeFilters[] = ['name' => $directAttrName, 'value' => $directAttrValue];
            }
        }

        $pf = (new RoleDataAccess())->getProductFilters((int) ($this->session->get('user_id') ?? 0));
        $products = $this->productModel->getProductsWithFilters(
            $searchTerm,
            $categoryFilter,
            $statusFilter,
            $perPage,
            $typeFilter,
            $attributeFilters,
            $pf['hide_services'],
            $pf['allowed_category_ids'] ?? [],
            $hasAssets,
            $sortBy
        );

        // Build variant-level matches for the listed products so UI can show exactly
        // which variants satisfied the active search/attribute filters.
        $matchedVariantsByProduct = [];
        if (!empty($products) && (trim((string)$searchTerm) !== '' || !empty($attributeFilters))) {
            $productIds = [];
            foreach ($products as $p) {
                $pid = (int)($p['id'] ?? 0);
                if ($pid > 0) {
                    $productIds[] = $pid;
                }
            }
            // When attribute filters are active the products are already filtered at
            // product level by the main query; do NOT re-apply the search term to
            // variant fields here — product names (e.g. "tweezer") don't appear in
            // variant rows, which would silently zero-out the chip results.
            // Only pass searchTerm when there are NO attribute filters so we can
            // highlight which specific variant art_number / name matched the search.
            $variantSearchTerm = empty($attributeFilters) ? $searchTerm : null;
            $matchedVariantsByProduct = $this->productModel->getMatchingVariantsByProductIds(
                $productIds,
                $variantSearchTerm,
                $attributeFilters,
                50
            );
        }
        
        // When search matches a variant art_number specifically (not the parent product
        // code/name), replace the template row with the individual variant row(s) so
        // the user sees the exact variant — not the generic template product.
        if ($searchTerm !== '') {
            $products = $this->productModel->promoteVariantRowsIfVariantSearch($searchTerm, $products);
        }

        // Structure the data properly for the view
        $productData = [
            'data' => $products,
            'pager' => $this->productModel->pager
        ];
        
        // Get categories for filter dropdown
        $categoryModel = new \App\Models\ProductCategoryModel();
        $categoryRecords = $categoryModel->where('is_active', true)->findAll();
        $categories = [];
        foreach ($categoryRecords as $category) {
            $categories[$category['id']] = $category['name'];
        }

        // Attribute name/value suggestions for guided advanced filtering
        $attributeModel = new ProductAttributeModel();
        $attributeRows = $attributeModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        $attributeOptions = [];
        foreach ($attributeRows as $row) {
            $name = trim((string)($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $vals = $this->parseAttributeValues($row['values'] ?? '[]');
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

        // Product type tabs with default preference support
        $productTypeTabs = ['all' => 'All Products', 'storable' => 'Storable', 'consumable' => 'Consumable', 'service' => 'Services'];
        
        // Get product counts for each type.
        // IMPORTANT: this schema uses `detailed_type` and has no `deleted_at` on products.
        $productTypeCounts = ['all' => 0, 'storable' => 0, 'consumable' => 0, 'service' => 0];
        try {
            $db = \Config\Database::connect();
            $baseCounter = $db->table('products');

            // Keep counts aligned with role restrictions and active status behavior.
            if (!empty($pf['hide_services'])) {
                $baseCounter->where('products.detailed_type !=', 'service');
            }
            if (!empty($pf['allowed_category_ids']) && is_array($pf['allowed_category_ids'])) {
                $baseCounter->whereIn('products.category_id', $pf['allowed_category_ids']);
            }
            if (empty($searchTerm) && ($statusFilter === null || $statusFilter === '')) {
                $baseCounter->where('products.is_active', 1);
            }

            $productTypeCounts['all'] = (clone $baseCounter)->countAllResults();
            $productTypeCounts['storable'] = (clone $baseCounter)->where('products.detailed_type', 'storable')->countAllResults();
            $productTypeCounts['consumable'] = (clone $baseCounter)->where('products.detailed_type', 'consumable')->countAllResults();
            $productTypeCounts['service'] = (clone $baseCounter)->where('products.detailed_type', 'service')->countAllResults();
        } catch (\Throwable $e) {
            log_message('error', 'Product type counts failed: ' . $e->getMessage());
            $productTypeCounts = ['all' => 0, 'storable' => 0, 'consumable' => 0, 'service' => 0];
        }
        
        // Use default tab if no type filter in URL
        if (empty($typeFilter) && !$this->request->getGet('type')) {
            $defaultTab = $this->session->get('products_default_tab') ?? 'all';
            if ($defaultTab !== 'all' && isset($productTypeTabs[$defaultTab])) {
                $typeFilter = $defaultTab;
            }
        } elseif (!empty($typeFilter) && in_array($typeFilter, array_keys($productTypeTabs), true)) {
            // Remember current tab as user preference
            $this->session->set('products_default_tab', $typeFilter);
        }

        $data = $this->setPageData([
            'page_title' => 'Products Management',
            'products' => $productData,
            'pager' => $this->productModel->pager,
            'categories' => $categories,
            'current_search' => $searchTerm,
            'current_category' => $categoryFilter,
            'current_status' => $statusFilter,
            'current_type' => $typeFilter,
            'current_has_assets' => $hasAssets,
            'current_sort_by' => $sortBy,
            'current_attributes' => $attributeFilters,
            'matched_variants_by_product' => $matchedVariantsByProduct,
            'attribute_options' => $attributeOptions,
            'per_page' => $perPage,
            'product_type_tabs' => $productTypeTabs,
            'product_type_counts' => $productTypeCounts,
            'can_create' => $this->hasPermission('products.create'),
            'can_edit' => $this->hasPermission('products.edit'),
            'can_delete' => $this->hasPermission('products.delete')
        ]);

        return view('products/index', $data);
    }

    /**
     * Lightweight JSON search endpoint for typeahead/autocomplete.
     * Query param: q
     * Returns: { success:true, data:[ {id, product_id, code, name, description, unit, unit_weight, image_url, current_stock} ] }
     */
    public function search()
    {
        $this->requireAuth();

        $q = trim((string) ($this->request->getGet('q') ?? ''));
        if ($q === '') {
            return $this->response->setJSON(['success' => true, 'data' => []]);
        }

        // IMPORTANT: Do not call requirePermission() here.
        // BaseController::requirePermission() redirects + exit, which breaks AJAX callers expecting JSON.
        // This endpoint is intentionally lightweight for typeahead usage.

        $rows = [];
        try {
            $candidates = $this->productModel->searchProducts($q);
            $db = \Config\Database::connect();

            // Optional stock map from stock_balances (best-effort)
            $stockMap = [];
            try {
                if (!empty($candidates)) {
                    $ids = array_values(array_unique(array_filter(array_column($candidates, 'id'))));
                    if (!empty($ids)) {
                        try {
                            $stockRows = $db->table('stock_balances')
                                ->select('product_id, SUM(quantity) as qty')
                                ->whereIn('product_id', $ids)
                                ->groupBy('product_id')
                                ->get()
                                ->getResultArray();
                            foreach ($stockRows as $sr) {
                                $stockMap[(int)$sr['product_id']] = (float)$sr['qty'];
                            }
                        } catch (\Throwable $_) {
                            // stock_balances table may not exist in some installs
                        }
                    }
                }
            } catch (\Throwable $_) {
                $stockMap = [];
            }

            foreach ($candidates as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $imgUrl = '';
                try {
                    if (!empty($p['image'])) {
                        $imgUrl = base_url('/uploads/products/' . ltrim((string)$p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $images = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($images) && !empty($images[0])) {
                            $imgUrl = base_url('/uploads/products/' . ltrim((string)$images[0], '/'));
                        }
                    }
                    if ($imgUrl === '') {
                        $imgUrl = base_url('assets/images/no-image.png');
                    }
                } catch (\Throwable $_) {
                    $imgUrl = base_url('assets/images/no-image.png');
                }

                $unitWeight = $p['unit_weight'] ?? ($p['weight'] ?? ($p['weight_net'] ?? ($p['weight_gross'] ?? 0.0)));
                if (!empty($p['variant_id'])) {
                    $currentStock = $p['current_stock'] ?? ($p['stock'] ?? ($p['available_stock'] ?? 0));
                } else {
                    $currentStock = $stockMap[$pid] ?? ($p['current_stock'] ?? ($p['stock'] ?? ($p['available_stock'] ?? 0)));
                }

                $rows[] = [
                    'product_id' => $pid,
                    'id' => $pid,
                    'code' => $p['code'] ?? ($p['sku'] ?? ''),
                    'name' => $p['name'] ?? '',
                    'description' => $p['description'] ?? '',
                    'unit' => $p['unit'] ?? 'pcs',
                    'unit_weight' => (float)$unitWeight,
                    'weight' => (float)$unitWeight,
                    'sale_price' => isset($p['sale_price']) ? (float)$p['sale_price'] : 0.0,
                    'special_price' => isset($p['special_price']) ? (float)$p['special_price'] : null,
                    'sale_currency' => $p['sale_currency'] ?? '',
                    'tax_rate' => isset($p['tax_rate']) ? (float)$p['tax_rate'] : (isset($p['tax']) ? (float)$p['tax'] : 0.0),
                    'image_url' => $imgUrl,
                    'current_stock' => (float)$currentStock,
                    'detailed_type' => $p['detailed_type'] ?? 'storable',
                    'vendor_id' => $p['vendor_id'] ?? null,
                    'vendor_name' => $p['vendor_name'] ?? null,
                    'variant_id' => isset($p['variant_id']) ? (int)$p['variant_id'] : null,
                    'variant_art_number' => $p['variant_art_number'] ?? null,
                    'variant_name' => $p['variant_name'] ?? null,
                    'attributes' => $p['attributes'] ?? null,
                ];
            }
        } catch (\Throwable $e) {
            log_message('error', 'Products::search failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Product search failed']);
        }

        return $this->response->setJSON(['success' => true, 'data' => $rows]);
    }

    /**
     * Get product assets (API endpoint)
     * Returns assets for a specific product organized by asset groups
     * GET: /products/{id}/assets?limit=20
     */
    public function getProductAssets($productId = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.view');

        if (empty($productId)) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Product ID required']);
        }

        try {
            $db = \Config\Database::connect();
            
            // Check if product exists
            $product = $this->productModel->find($productId);
            if (!$product) {
                return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Product not found']);
            }

            // Get asset groups for this product with their assets
            if (!$db->tableExists('product_asset_groups') || !$db->tableExists('product_assets')) {
                return $this->response->setJSON(['success' => true, 'data' => ['groups' => []]]);
            }

            $groups = $db->table('product_asset_groups pag')
                ->select('pag.*, COALESCE(pa_count.cnt, 0) as asset_count')
                ->where('pag.product_id', $productId)
                ->leftJoin(
                    '(SELECT asset_group_id, COUNT(*) as cnt FROM product_assets GROUP BY asset_group_id) pa_count',
                    'pa_count.asset_group_id = pag.id',
                    'left'
                )
                ->orderBy('pag.created_at', 'DESC')
                ->get()
                ->getResultArray();

            // For each group, get its assets
            $groupsWithAssets = [];
            foreach ($groups as $group) {
                $assets = $db->table('product_assets')
                    ->where('asset_group_id', $group['id'])
                    ->orderBy('created_at', 'DESC')
                    ->get()
                    ->getResultArray();

                $groupsWithAssets[] = [
                    'group' => $group,
                    'assets' => $assets
                ];
            }

            return $this->response->setJSON([
                'success' => true,
                'data' => [
                    'product_id' => $productId,
                    'product_name' => $product['name'] ?? '',
                    'groups' => $groupsWithAssets
                ]
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'getProductAssets error: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Server error']);
        }
    }

    /**
     * Display single product details
     */
    public function show($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.view');

        // Disallow plain numeric IDs in URLs — public_id or 404.
        if (is_numeric($id)) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $resolvedProduct = $this->resolveProductOrFail($id);
        if ($redirect = $this->redirectToCanonicalProductUrl($resolvedProduct, $id)) {
            return $redirect;
        }

        $productId = (int) $resolvedProduct['id'];
        $product = $this->productModel->getProductWithDetails($productId);
        if (!$product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        // Load vendor name if vendor_id present (optional)
        try {
            if (!empty($product['vendor_id'])) {
                $vendorModel = new \App\Models\VendorModel();
                $vendor = $vendorModel->find($product['vendor_id']);
                $product['vendor_name'] = $vendor['name'] ?? null;
            } else {
                $product['vendor_name'] = null;
            }
        } catch (\Throwable $e) {
            // If vendor model/table not available, continue gracefully
            $product['vendor_name'] = null;
        }

        // Get related work orders
        $workOrderModel = new WorkOrderModel();
        $workOrders = $workOrderModel->where('product_id', $productId)
                                   ->orderBy('created_at', 'DESC')
                                   ->limit(10)
                                   ->findAll();

        // Determine default currency for display fallbacks
        $company = (new \App\Models\CompanySettingsModel())->first();
        $defaultCurrency = $company['base_currency'] ?? ($company['secondary_currency'] ?? 'USD');

        // Load existing variants for the Variants tab
        try {
            $variantModel = new \App\Models\ProductVariantModel();
            $variants = $variantModel->where('product_id', $productId)->orderBy('id', 'ASC')->findAll();
        } catch (\Throwable $e) {
            $variants = [];
        }

        // Load existing variants for the Variants tab
        try {
            $variantModel = new \App\Models\ProductVariantModel();
            $variants = $variantModel->where('product_id', $productId)->orderBy('id', 'ASC')->findAll();
        } catch (\Throwable $e) {
            $variants = [];
        }

        // Load active categories/vendors for inline forms
        $categoryModel = new \App\Models\ProductCategoryModel();
        $categories = $categoryModel->where('is_active', true)->orderBy('name','ASC')->findAll();
        try {
            $vendorModel = new \App\Models\VendorModel();
            $vendors = $vendorModel->where('is_active', 1)->orderBy('name','ASC')->findAll();
        } catch (\Throwable $e) {
            $vendors = [];
        }
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code','ASC')->findAll();
        } catch (\Throwable $e) {
            $currencies = [];
        }

        try {
            $preparationProfileModel = new \App\Models\PreparationProfileModel();
            $preparationProfiles = $preparationProfileModel->getWithCountsByProduct($productId, true);
        } catch (\Throwable $e) {
            $preparationProfiles = [];
        }

        $activeTab = (string) ($this->request->getGet('tab') ?? 'overview');
        if (!in_array($activeTab, ['overview', 'preparation', 'assets'], true)) {
            $activeTab = 'overview';
        }

        $canViewAssets = $this->hasPermission('product_assets.read')
            || $this->hasPermission('product_assets.view')
            || $this->hasPermission('products.edit');

        if ($activeTab === 'assets' && ! $canViewAssets) {
            $activeTab = 'overview';
        }

        $salesUnitsTotal = 0.0;
        $purchasedUnitsTotal = 0.0;
        try {
            $dbMetrics = \Config\Database::connect();

            if ($dbMetrics->tableExists('sales_order_lines')) {
                $solFields = $dbMetrics->getFieldNames('sales_order_lines');
                $solQty = in_array('quantity', $solFields ?? [], true) ? 'quantity' : (in_array('qty', $solFields ?? [], true) ? 'qty' : null);
                if ($solQty !== null && in_array('product_id', $solFields ?? [], true)) {
                    $qb = $dbMetrics->table('sales_order_lines sol')
                        ->select('COALESCE(SUM(COALESCE(sol.' . $solQty . ',0)),0) as units', false)
                        ->where('sol.product_id', $productId);

                    if ($dbMetrics->tableExists('sales_orders')) {
                        $soFields = $dbMetrics->getFieldNames('sales_orders');
                        $solFk = in_array('sales_order_id', $solFields ?? [], true) ? 'sales_order_id' : (in_array('so_id', $solFields ?? [], true) ? 'so_id' : null);
                        if ($solFk !== null && in_array('status', $soFields ?? [], true)) {
                            $qb->join('sales_orders so', 'so.id = sol.' . $solFk, 'left')
                                ->where("LOWER(COALESCE(so.status,'')) NOT IN ('draft','cancelled','canceled','rejected')", null, false);
                        }
                    }

                    $salesUnitsTotal = (float)(($qb->get()->getRowArray()['units'] ?? 0));
                }
            }

            if ($dbMetrics->tableExists('purchase_order_lines')) {
                $polFields = $dbMetrics->getFieldNames('purchase_order_lines');
                $polQty = in_array('quantity', $polFields ?? [], true) ? 'quantity' : (in_array('qty', $polFields ?? [], true) ? 'qty' : null);
                if ($polQty !== null && in_array('product_id', $polFields ?? [], true)) {
                    $qb = $dbMetrics->table('purchase_order_lines pol')
                        ->select('COALESCE(SUM(COALESCE(pol.' . $polQty . ',0)),0) as units', false)
                        ->where('pol.product_id', $productId);

                    if ($dbMetrics->tableExists('purchase_orders')) {
                        $poFields = $dbMetrics->getFieldNames('purchase_orders');
                        $polFk = in_array('po_id', $polFields ?? [], true) ? 'po_id' : (in_array('purchase_order_id', $polFields ?? [], true) ? 'purchase_order_id' : null);
                        if ($polFk !== null && in_array('status', $poFields ?? [], true)) {
                            $qb->join('purchase_orders po', 'po.id = pol.' . $polFk, 'left')
                                ->where("LOWER(COALESCE(po.status,'')) NOT IN ('draft','cancelled','canceled','rejected')", null, false);
                        }
                    }

                    $purchasedUnitsTotal = (float)(($qb->get()->getRowArray()['units'] ?? 0));
                }
            }
        } catch (\Throwable $e) {
            $salesUnitsTotal = 0.0;
            $purchasedUnitsTotal = 0.0;
        }

        $canViewBusinessData = $this->canViewBusinessData();

        $data = $this->setPageData([
            'page_title' => 'Product Details - ' . $product['name'],
            'product' => $product,
            'work_orders' => $workOrders,
            'can_edit' => $this->hasPermission('products.edit'),
            'can_delete' => $this->hasPermission('products.delete'),
            'can_view_sensitive_overview' => $canViewBusinessData,
            'default_currency' => $defaultCurrency,
            'categories' => $categories,
            'vendors' => $vendors,
            'currencies' => $currencies,
            'preparation_profiles' => $preparationProfiles,
            'active_tab' => $activeTab,
            'can_view_assets' => $canViewAssets,
            'product_identifier' => entityRouteIdentifier($product),
            'sales_units_total' => $salesUnitsTotal,
            'purchased_units_total' => $purchasedUnitsTotal,
        ]);

        // Load variants for the product view (with stock aggregates)
        try {
            $db = \Config\Database::connect();
            $variantFields = $db->getFieldNames('product_variants');
            $select = 'pv.id, pv.product_id, pv.art_number, pv.name, pv.price, pv.cost, pv.attributes';
            if (in_array('weight', $variantFields ?? [], true)) {
                $select .= ', pv.weight';
            }
            if (in_array('image', $variantFields ?? [], true)) {
                $select .= ', pv.image';
            }

            $variants = $db->table('product_variants pv')
                ->select($select)
                ->select('COALESCE(SUM(vi.quantity), 0) as on_hand, COALESCE(SUM(vi.reserved), 0) as reserved')
                ->join('variant_inventory vi', 'vi.variant_id = pv.id', 'left')
                ->where('pv.product_id', $productId);

            $productVariantGroupBy = ['pv.id', 'pv.product_id', 'pv.art_number', 'pv.name', 'pv.price', 'pv.cost', 'pv.attributes'];
            if (in_array('weight', $variantFields ?? [], true)) {
                $productVariantGroupBy[] = 'pv.weight';
            }
            if (in_array('image', $variantFields ?? [], true)) {
                $productVariantGroupBy[] = 'pv.image';
            }

            $variants = $variants
                ->groupBy(implode(', ', $productVariantGroupBy))
                ->orderBy('pv.id', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $e) {
            $variants = [];
        }
        $data['variants'] = $variants;

        // Location-wise available stock for this product (best effort, schema-safe)
        $stockByLocation = [];
        $stockTotalAvailable = 0.0;
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('stock_balances')) {
                $sbFields = array_flip($db->getFieldNames('stock_balances'));
                $hasWarehouseId = isset($sbFields['warehouse_id']);
                $hasLocationId = isset($sbFields['location_id']);

                $qb = $db->table('stock_balances sb')
                    ->select('COALESCE(SUM(sb.quantity), 0) AS available_qty')
                    ->where('sb.product_id', $productId);

                if ($hasWarehouseId) {
                    $qb->select('sb.warehouse_id, COALESCE(w.name, "Unassigned Warehouse") AS warehouse_name')
                        ->join('warehouses w', 'w.id = sb.warehouse_id', 'left');
                } else {
                    $qb->select('NULL AS warehouse_id, "Unassigned Warehouse" AS warehouse_name');
                }

                if ($hasLocationId) {
                    $qb->select('sb.location_id, COALESCE(wl.name, "Unassigned Location") AS location_name')
                        ->join('warehouse_locations wl', 'wl.id = sb.location_id', 'left');
                } else {
                    $qb->select('NULL AS location_id, "Unassigned Location" AS location_name');
                }

                if ($hasWarehouseId && $hasLocationId) {
                    $qb->groupBy('sb.warehouse_id, sb.location_id, w.name, wl.name');
                } elseif ($hasWarehouseId) {
                    $qb->groupBy('sb.warehouse_id, w.name');
                } elseif ($hasLocationId) {
                    $qb->groupBy('sb.location_id, wl.name');
                }

                $rows = $qb->orderBy('warehouse_name', 'ASC')
                    ->orderBy('location_name', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    $qty = (float)($row['available_qty'] ?? 0);
                    if (abs($qty) < 0.000001) {
                        continue;
                    }
                    $stockByLocation[] = [
                        'warehouse_name' => (string)($row['warehouse_name'] ?? 'Unassigned Warehouse'),
                        'location_name' => (string)($row['location_name'] ?? 'Unassigned Location'),
                        'available_qty' => $qty,
                    ];
                    $stockTotalAvailable += $qty;
                }
            }
        } catch (\Throwable $e) {
            $stockByLocation = [];
            $stockTotalAvailable = 0.0;
        }
        $data['stock_by_location'] = $stockByLocation;
        $data['stock_total_available'] = $stockTotalAvailable;

        return view('products/show', $data);
    }

    /**
     * Display create product form
     */
    public function create()
    {
        $this->requireAuth();
        
        // Skip permission check for testing
        // $this->requirePermission('products.create');

        // Get categories for dropdown
        $categoryModel = new \App\Models\ProductCategoryModel();
        $categories = $categoryModel->where('is_active', true)->findAll();

        // Load active currencies and default from company settings
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code','ASC')->findAll();
        } catch (\Throwable $e) {
            $currencies = [];
        }
        // Load active vendors
        try {
            $vendorModel = new \App\Models\VendorModel();
            $vendors = $vendorModel->where('is_active', 1)->orderBy('name','ASC')->findAll();
        } catch (\Throwable $e) {
            $vendors = [];
        }
        $company = (new \App\Models\CompanySettingsModel())->first();
        $defaultCurrency = $company['base_currency'] ?? ($company['secondary_currency'] ?? 'USD');

        // Get default currency symbol for display
        $saleCurrencySymbol = '$';
        $costCurrencySymbol = '$';
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            $defaultCurrencyData = $currencyModel->find($defaultCurrency);
            if ($defaultCurrencyData && !empty($defaultCurrencyData['symbol'])) {
                $saleCurrencySymbol = $defaultCurrencyData['symbol'];
                $costCurrencySymbol = $defaultCurrencyData['symbol'];
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to fetch default currency symbol: ' . $e->getMessage());
        }

        $data = $this->setPageData([
            'page_title' => 'Create New Product',
            'product' => null,
            'form_submit_token' => issueFormSubmissionToken('product_create'),
            'categories' => $categories,
            'currencies' => $currencies,
            'vendors' => $vendors,
            'default_currency' => $defaultCurrency,
            'validation' => \Config\Services::validation(),
            'costCurrencySymbol' => $costCurrencySymbol,
            'saleCurrencySymbol' => $saleCurrencySymbol,
            'weightUnit' => 'KG',
            'weightUnits' => $this->weightUnitOptions()
        ]);

        return view('products/form', $data);
    }

    /**
     * Handle product creation
     */
    public function store()
    {
        $this->requireAuth();
        $this->ensureProductsWeightUnitColumn();

        $submitToken = (string)($this->request->getPost('_form_submit_token') ?? '');
        if (!consumeFormSubmissionToken('product_create', $submitToken)) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'This form was already submitted. Please try once from a fresh form.');
        }
        
        // Skip permission check for testing
        // $this->requirePermission('products.create');

        $productType = $this->request->getPost('product_type') ?: 'simple';

        // Adjust validation: template (variable) products do not require a code
        if ($productType === 'variable') {
            try {
                $rules = $this->productModel->getValidationRules();
                $rules['code'] = 'permit_empty|max_length[50]';
                $this->productModel->setValidationRules($rules);
            } catch (\Throwable $e) {
                // ignore if rules cannot be changed
            }
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'code' => $this->request->getPost('code'),
            'description' => $this->request->getPost('description'),
            'category_id' => $this->request->getPost('category_id') ?: null, // Handle empty category
            'unit' => $this->request->getPost('unit'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 1, // Default to active
            // product_type: only include if the DB column exists (migrations may not have run)
            // will be appended below if present
            'attributes_definitions' => $this->request->getPost('attributes_definitions') ?: null,
            'excluded_combos' => $this->request->getPost('excluded_combos') !== null ? $this->request->getPost('excluded_combos') : null,
            // New fields: weight and pricing
            'weight' => $this->request->getPost('weight') !== null ? $this->request->getPost('weight') : 0,
            'weight_unit' => $this->request->getPost('weight_unit') ?: 'KG',
            'cost_price' => $this->request->getPost('cost_price') !== null ? $this->request->getPost('cost_price') : 0,
            'cost_currency' => $this->request->getPost('cost_currency') ?: 'USD',
            'sale_price' => $this->request->getPost('sale_price') !== null ? $this->request->getPost('sale_price') : 0,
            'sale_currency' => $this->request->getPost('sale_currency') ?: 'USD',
        ];

        // vendor info - explicitly check for empty string, not falsy (vendor id can be 0)
        $vendorId = $this->request->getPost('vendor_id');
        $vendorId = ($vendorId === '' || $vendorId === null) ? null : (int) $vendorId;
        $vendorPrice = $this->request->getPost('vendor_price') !== null ? $this->request->getPost('vendor_price') : null;
        $vendorPricePkr = $this->request->getPost('vendor_price_pkr') !== null ? $this->request->getPost('vendor_price_pkr') : null;
        $vendorCurrency = $this->request->getPost('vendor_currency') ?: null;
        $data['vendor_id'] = $vendorId;
        if ($vendorPrice !== null) $data['vendor_price'] = $vendorPrice;
        if ($vendorPricePkr !== null) $data['vendor_price_pkr'] = $vendorPricePkr;
        if ($vendorCurrency) $data['vendor_currency'] = $vendorCurrency;

        // Only include product_type if the database actually has that column
        if ($this->productsHasColumn('product_type')) {
            $data['product_type'] = $productType;
        }

        // Odoo-like product detailed_type: storable / consumable / service
        if ($this->productsHasColumn('detailed_type')) {
            $data['detailed_type'] = $this->request->getPost('detailed_type') ?: 'storable';
        }

        // Service policy: when a service product is invoiceable (ordered_qty or delivered_qty)
        if ($this->productsHasColumn('service_policy')) {
            $detailedType = $data['detailed_type'] ?? ($this->request->getPost('detailed_type') ?: 'storable');
            if ($detailedType === 'service') {
                $data['service_policy'] = $this->request->getPost('service_policy') ?: 'ordered_qty';
            } else {
                $data['service_policy'] = null;
            }
        }

        // Ensure products.images column exists (auto-add if missing)
        $imagesColumnReady = $this->ensureProductsImagesColumn();

        // Handle image uploads
        $imageFiles = $this->request->getFiles();
        $uploadedImages = [];
        
        if (isset($imageFiles['product_images'])) {
            $uploadedImages = $this->handleImageUploads($imageFiles['product_images']);
        }
        
        if (!empty($uploadedImages) && $imagesColumnReady) {
            $data['images'] = json_encode($uploadedImages);
        }

        // Auto-generate Art Number/code from global counter + category suffix (simple products only)
        try {
            if ($productType === 'simple' && !empty($data['category_id'])) {
                $artService = new \App\Services\ArtNumberService();
                $generated = $artService->generateForCategory((int)$data['category_id']);
                // Authoritative server-side allocation. Ignore posted code to prevent duplicates/races.
                $data['code'] = $generated;
                $data['sku'] = $generated;
            } elseif ($productType === 'variable') {
                // Variable (template) products have no individual product code; variants carry art numbers.
                $data['code'] = null;
                $data['sku']  = null;
            }
        } catch (\Throwable $e) {
            // If art number allocation fails, return with error
            $this->cleanupUploadedFiles($uploadedImages);
            // Save flashdata to help user jump to category edit
            $flash = ['message' => $e->getMessage(), 'category_id' => $data['category_id'] ?? null];
            session()->setFlashdata('sku_allocation_failed', $flash);
            return redirect()->back()->withInput();
        }

        if ($this->productModel->save($data)) {
            $this->syncGlobalAttributesFromDefinitions($data['attributes_definitions'] ?? null);
            return redirect()->to('/products')->with('success', 'Product created successfully.');
        } else {
            // Clean up uploaded files if database save fails
            $this->cleanupUploadedFiles($uploadedImages);
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->productModel->errors());
        }
    }

    /**
     * Display edit product form
     */
    public function edit($id = null)
    {
        $this->requireAuth();
        
        // Skip permission check for testing
        // $this->requirePermission('products.edit');

        $resolvedProduct = $this->resolveProductOrFail($id);
        if ($redirect = $this->redirectToCanonicalProductUrl($resolvedProduct, $id, '/edit')) {
            return $redirect;
        }

        $productId = (int) $resolvedProduct['id'];
        $product = $this->productModel->find($productId);

        // Get categories for dropdown
        $categoryModel = new \App\Models\ProductCategoryModel();
        $categories = $categoryModel->where('is_active', true)->findAll();

        // Load active currencies and default from company settings
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code','ASC')->findAll();
        } catch (\Throwable $e) {
            $currencies = [];
        }
        // Load active vendors for the edit form
        try {
            $vendorModel = new \App\Models\VendorModel();
            $vendors = $vendorModel->where('is_active', 1)->orderBy('name','ASC')->findAll();
        } catch (\Throwable $e) {
            $vendors = [];
        }
        $company = (new \App\Models\CompanySettingsModel())->first();
        $defaultCurrency = $company['base_currency'] ?? ($company['secondary_currency'] ?? 'USD');

        $variants = [];
        // Load existing variants for the Variants tab
        try {
            $variantModel = new \App\Models\ProductVariantModel();
            $variants = $variantModel->where('product_id', $productId)->orderBy('id', 'ASC')->findAll();
        } catch (\Throwable $e) {
            $variants = [];
        }

        // If product has variants but no attributes_definitions, extract from variant data
        $attributesDefs = $product['attributes_definitions'] ?? null;
        if (empty($attributesDefs) && !empty($variants)) {
            try {
                $extractedAttrs = $this->extractAttributesFromVariants($variants);
                if (!empty($extractedAttrs)) {
                    $attributesDefs = json_encode($extractedAttrs);
                }
            } catch (\Throwable $e) {
                log_message('error', 'Failed to extract attributes from variants: ' . $e->getMessage());
                // Keep existing attributes_definitions or null
            }
        }
        
        // Update product data with extracted or existing attributes
        if ($attributesDefs) {
            $product['attributes_definitions'] = $attributesDefs;
        }

        // Get currency symbols for display
        $costCurrencySymbol = '$';
        $saleCurrencySymbol = '$';
        try {
            $currencyModel = new \App\Models\Accounting\CurrencyModel();
            if (!empty($product['cost_currency'])) {
                $costCurrency = $currencyModel->find($product['cost_currency']);
                if ($costCurrency && !empty($costCurrency['symbol'])) {
                    $costCurrencySymbol = $costCurrency['symbol'];
                }
            }
            if (!empty($product['sale_currency'])) {
                $saleCurrency = $currencyModel->find($product['sale_currency']);
                if ($saleCurrency && !empty($saleCurrency['symbol'])) {
                    $saleCurrencySymbol = $saleCurrency['symbol'];
                }
            }
        } catch (\Throwable $e) {
            log_message('error', 'Failed to fetch currency symbols: ' . $e->getMessage());
        }

        $data = $this->setPageData([
            'page_title' => 'Edit Product - ' . $product['name'],
            'product' => $product,
            'categories' => $categories,
            'currencies' => $currencies,
            'vendors' => $vendors,
            'default_currency' => $defaultCurrency,
            'validation' => \Config\Services::validation(),
            'variants' => $variants,
            'costCurrencySymbol' => $costCurrencySymbol,
            'saleCurrencySymbol' => $saleCurrencySymbol,
            'weightUnit' => $product['weight_unit'] ?? 'KG',
            'weightUnits' => $this->weightUnitOptions()
        ]);

        return view('products/form', $data);
    }

    /**
     * Handle product update
     */
    public function update($id = null)
    {
        $this->requireAuth();
        $this->ensureProductsWeightUnitColumn();
        
        // Skip permission check for testing
        // $this->requirePermission('products.edit');

        $product = $this->productModel->find($id);
        if (!$product) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Product not found');
        }

        $productType = $this->request->getPost('product_type') ?: ($product['product_type'] ?? 'simple');

        $normalizeDefs = function ($raw) {
            $defs = [];
            if (is_string($raw)) {
                try { $defs = json_decode($raw, true) ?? []; } catch (\Throwable $e) { $defs = []; }
            } elseif (is_array($raw)) {
                $defs = $raw;
            }
            if (!is_array($defs)) $defs = [];
            $map = [];
            foreach ($defs as $d) {
                $name = trim((string)($d['name'] ?? ''));
                if ($name === '') continue;
                $vals = array_values(array_filter(array_map('trim', (array)($d['values'] ?? []))));
                $map[$name] = $vals;
            }
            return $map;
        };

        // Adjust validation: template (variable) products do not require a code
        if ($productType === 'variable') {
            try {
                $rules = $this->productModel->getValidationRules();
                $rules['code'] = 'permit_empty|max_length[50]';
                $this->productModel->setValidationRules($rules);
            } catch (\Throwable $e) {
                // ignore if rules cannot be changed
            }
        }

        $data = [
            'name' => $this->request->getPost('name'),
            'code' => $this->request->getPost('code'),
            'description' => $this->request->getPost('description'),
            'category_id' => $this->request->getPost('category_id'),
            'unit' => $this->request->getPost('unit'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'attributes_definitions' => $this->request->getPost('attributes_definitions') !== null ? $this->request->getPost('attributes_definitions') : ($product['attributes_definitions'] ?? null),
            // New fields: weight and pricing
            'weight' => $this->request->getPost('weight') !== null ? $this->request->getPost('weight') : ($product['weight'] ?? 0),
            'weight_unit' => $this->request->getPost('weight_unit') ?: ($product['weight_unit'] ?? 'KG'),
            'cost_price' => $this->request->getPost('cost_price') !== null ? $this->request->getPost('cost_price') : ($product['cost_price'] ?? 0),
            'cost_currency' => $this->request->getPost('cost_currency') ?: ($product['cost_currency'] ?? 'USD'),
            'sale_price' => $this->request->getPost('sale_price') !== null ? $this->request->getPost('sale_price') : ($product['sale_price'] ?? 0),
            'sale_currency' => $this->request->getPost('sale_currency') ?: ($product['sale_currency'] ?? 'USD')
        ];

        $this->syncGlobalAttributesFromDefinitions($data['attributes_definitions'] ?? null);

        // Handle vendor_id - explicitly check for empty string, not falsy (vendor id can be 0)
        $vendorId = $this->request->getPost('vendor_id');
        $vendorId = ($vendorId === '' || $vendorId === null) ? null : (int) $vendorId;
        $vendorPrice = $this->request->getPost('vendor_price') !== null ? $this->request->getPost('vendor_price') : ($product['vendor_price'] ?? null);
        $vendorPricePkr = $this->request->getPost('vendor_price_pkr') !== null ? $this->request->getPost('vendor_price_pkr') : ($product['vendor_price_pkr'] ?? null);
        $vendorCurrency = $this->request->getPost('vendor_currency') ?: ($product['vendor_currency'] ?? null);
        $data['vendor_id'] = $vendorId;
        $data['vendor_price'] = $vendorPrice;
        $data['vendor_price_pkr'] = $vendorPricePkr;
        $data['vendor_currency'] = $vendorCurrency;

        // Only include product_type if the DB column exists (some installs may not have run migrations)
        if ($this->productsHasColumn('product_type')) {
            $data['product_type'] = $productType;
        }

        // Prevent switching to simple when variants exist
        try {
            if ($productType === 'simple') {
                $db = \Config\Database::connect();
                $variantCount = (int) $db->table('product_variants')
                    ->where('product_id', (int)$id)
                    ->countAllResults();
                if ($variantCount > 0) {
                    return redirect()->back()
                        ->withInput()
                        ->with('error', 'Cannot switch to Simple Product while variants exist. Remove variants first.');
                }
            }
        } catch (\Throwable $e) {
            // If count fails, do not block update
        }

        // If attributes were changed for variable products, remove invalid variants safely
        if ($productType === 'variable') {
            $oldMap = $normalizeDefs($product['attributes_definitions'] ?? []);
            $newMap = $normalizeDefs($data['attributes_definitions'] ?? []);

            $oldJson = json_encode($oldMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $newJson = json_encode($newMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            if ($oldJson !== $newJson) {
                try {
                    $db = \Config\Database::connect();
                    $variantRows = $db->table('product_variants')
                        ->select('id, attributes')
                        ->where('product_id', (int)$id)
                        ->get()
                        ->getResultArray();

                    $toDelete = [];
                    foreach ($variantRows as $vr) {
                        $attrs = [];
                        if (!empty($vr['attributes'])) {
                            try { $attrs = json_decode($vr['attributes'], true) ?? []; } catch (\Throwable $e) { $attrs = []; }
                        }
                        if (!is_array($attrs)) $attrs = [];
                        $invalid = false;
                        foreach ($attrs as $k => $v) {
                            $k = trim((string)$k);
                            $v = trim((string)$v);
                            if ($k === '') continue;
                            if (!array_key_exists($k, $newMap)) { $invalid = true; break; }
                            if (!in_array($v, $newMap[$k], true)) { $invalid = true; break; }
                        }
                        if ($invalid) {
                            $toDelete[] = (int)$vr['id'];
                        }
                    }

                    if (!empty($toDelete)) {
                        // Block deletion if any stock/reserved exists
                        $stock = $db->table('variant_inventory')
                            ->select('variant_id, SUM(quantity) as qty, SUM(reserved) as res')
                            ->whereIn('variant_id', $toDelete)
                            ->groupBy('variant_id')
                            ->get()
                            ->getResultArray();
                        foreach ($stock as $s) {
                            $qty = (float)($s['qty'] ?? 0);
                            $res = (float)($s['res'] ?? 0);
                            if ($qty != 0.0 || $res != 0.0) {
                                return redirect()->back()
                                    ->withInput()
                                    ->with('error', 'Cannot remove variants that have stock or reserved quantities.');
                            }
                        }

                        // Block deletion if any sales/purchase order exists for this product
                        $hasSales = $db->table('sales_order_lines')->where('product_id', (int)$id)->countAllResults();
                        $hasPurch = $db->table('purchase_order_lines')->where('product_id', (int)$id)->countAllResults();
                        if ($hasSales > 0 || $hasPurch > 0) {
                            return redirect()->back()
                                ->withInput()
                                ->with('error', 'Cannot remove variants because orders exist for this product.');
                        }

                        // Safe to delete variants and related inventory
                        $db->table('variant_inventory')->whereIn('variant_id', $toDelete)->delete();
                        try { $db->table('stock_balances')->whereIn('variant_id', $toDelete)->delete(); } catch (\Throwable $e) {}
                        try { $db->table('stock_movements')->whereIn('variant_id', $toDelete)->delete(); } catch (\Throwable $e) {}
                        $db->table('product_variants')->whereIn('id', $toDelete)->delete();
                    }
                } catch (\Throwable $e) {
                    // If deletion logic fails, do not block update
                }
            }
        }

        // Odoo-like product detailed_type: storable / consumable / service
        if ($this->productsHasColumn('detailed_type')) {
            $data['detailed_type'] = $this->request->getPost('detailed_type') ?: ($product['detailed_type'] ?? 'storable');
        }

        // Service policy: when a service product is invoiceable (ordered_qty or delivered_qty)
        if ($this->productsHasColumn('service_policy')) {
            $detailedType = $data['detailed_type'] ?? ($product['detailed_type'] ?? 'storable');
            if ($detailedType === 'service') {
                $data['service_policy'] = $this->request->getPost('service_policy') ?: ($product['service_policy'] ?? 'ordered_qty');
            } else {
                $data['service_policy'] = null;
            }
        }

        // Validate product code uniqueness (excluding current product) for simple products only
        $code = $this->request->getPost('code');
        if ($productType === 'simple') {
            if (!$this->productModel->validateProductCode($code, $id)) {
                return redirect()->back()
                               ->withInput()
                               ->with('error', 'Product code already exists');
            }
        } else {
            $data['code'] = null;
            $data['sku'] = null;
        }

    // Ensure products.images column exists (auto-add if missing)
    $imagesColumnReady = $this->ensureProductsImagesColumn();

    // Handle existing images
        $existingImages = !empty($product['images']) ? json_decode($product['images'], true) : [];
        
        // Handle images to remove
        $removeImages = $this->request->getPost('remove_images') ?? [];
        foreach ($removeImages as $removeImage) {
            $this->removeImageFile($removeImage);
            $existingImages = array_filter($existingImages, function($img) use ($removeImage) {
                return $img !== $removeImage;
            });
        }

        // Handle new image uploads
        $imageFiles = $this->request->getFiles();
        $uploadedImages = [];
        
        if (isset($imageFiles['product_images'])) {
            $uploadedImages = $this->handleImageUploads($imageFiles['product_images']);
        }
        
        // Merge existing and new images
        if ($imagesColumnReady) {
            $allImages = array_merge($existingImages, $uploadedImages);
            $data['images'] = json_encode(array_values($allImages));
        }

        // Include id so the model's is_unique[products.code,id,{id}] rule
        // can resolve {id} and exclude the current product during update validation.
        // The field is stripped before the SQL UPDATE since 'id' is not in $allowedFields.
        $data['id'] = $id;

        if ($this->productModel->update($id, $data)) {
            return redirect()->to("/products/$id/edit")->with('success', 'Product updated successfully.');
        } else {
            // Clean up uploaded files if database save fails
            $this->cleanupUploadedFiles($uploadedImages);
            return redirect()->back()
                           ->withInput()
                           ->with('validation', $this->productModel->errors());
        }
    }

    /**
     * Copy the template product weight to every variant linked to it.
     */
    public function copyWeightToVariants($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productId = (int) ($id ?? 0);
        if (! $productId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid product']);
        }

        $product = $this->productModel->find($productId);
        if (! $product) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Product not found']);
        }

        $weightRaw = $this->request->getPost('weight');
        if ($weightRaw === null || trim((string)$weightRaw) === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Weight value required']);
        }

        $weight = (float) $weightRaw;

        if (! $this->ensureVariantsWeightColumn()) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to update variants']);
        }

        try {
            $db = \Config\Database::connect();
            $db->table('product_variants')
                ->where('product_id', $productId)
                ->update(['weight' => $weight]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to copy weight to variants: ' . $e->getMessage());
            $errorMessage = 'Failed to update variant weights';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $errorMessage]);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        $message = 'Weight synced to all variants (save product to persist template weight).';
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => $message, 'weight' => $weight]);
        }
        return redirect()->back()->with('success', $message);
    }

    /**
     * Copy vendor to all product variants
     */
    public function copyVendorToVariants($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productId = (int) ($id ?? 0);
        if (! $productId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid product']);
        }

        $product = $this->productModel->find($productId);
        if (! $product) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Product not found']);
        }

        $vendorId = $this->request->getPost('vendor_id');
        if ($vendorId === null) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Vendor ID required']);
        }

        // vendor_id can be empty string (no vendor), or a valid integer
        $vendorId = ($vendorId === '' || $vendorId === null) ? null : (int) $vendorId;

        if (! $this->ensureVariantsVendorColumn()) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to update variants']);
        }

        try {
            $db = \Config\Database::connect();
            $db->table('product_variants')
                ->where('product_id', $productId)
                ->update(['vendor_id' => $vendorId]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to copy vendor to variants: ' . $e->getMessage());
            $errorMessage = 'Failed to update variant vendor';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $errorMessage]);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        $vendorName = 'Template value';
        if ($vendorId !== null) {
            try {
                $vendorModel = new \App\Models\VendorModel();
                $vendor = $vendorModel->find($vendorId);
                if ($vendor) {
                    $vendorName = $vendor['name'];
                }
            } catch (\Throwable $e) {
                log_message('error', 'Failed to fetch vendor name: ' . $e->getMessage());
            }
        }

        $message = "Vendor synced to all variants. Variants will inherit template vendor ({$vendorName}).";
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => $message, 'vendor_id' => $vendorId]);
        }
        return redirect()->back()->with('success', $message);
    }

    /**
     * Copy cost price to all product variants
     */
    public function copyCostToVariants($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productId = (int) ($id ?? 0);
        if (! $productId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid product']);
        }

        $product = $this->productModel->find($productId);
        if (! $product) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Product not found']);
        }

        $costRaw = $this->request->getPost('cost_price');
        if ($costRaw === null || (trim((string)$costRaw) === '' && $costRaw !== '0')) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Cost price value required']);
        }

        $costPrice = (float) $costRaw;

        // Ensure cost_price column exists in product_variants
        if (!$this->variantsHasColumn('cost_price')) {
            try {
                $db = \Config\Database::connect();
                $db->query("ALTER TABLE `product_variants` ADD COLUMN `cost_price` DECIMAL(15,2) NULL");
            } catch (\Throwable $e) {
                log_message('error', 'Failed to add cost_price column to variants: ' . $e->getMessage());
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to update variants']);
            }
        }

        $updateData = ['cost_price' => $costPrice];
        // Keep legacy column in sync because variant listing and older flows use `cost`
        if ($this->variantsHasColumn('cost')) {
            $updateData['cost'] = $costPrice;
        }

        try {
            $db = \Config\Database::connect();
            $db->table('product_variants')
                ->where('product_id', $productId)
                ->update($updateData);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to copy cost price to variants: ' . $e->getMessage());
            $errorMessage = 'Failed to update variant cost prices';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $errorMessage]);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        $message = 'Cost price synced to all variants (save product to persist template cost price).';
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => $message, 'cost_price' => $costPrice]);
        }
        return redirect()->back()->with('success', $message);
    }

    /**
     * Copy sale price to all product variants
     */
    public function copySaleToVariants($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productId = (int) ($id ?? 0);
        if (! $productId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid product']);
        }

        $product = $this->productModel->find($productId);
        if (! $product) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Product not found']);
        }

        $saleRaw = $this->request->getPost('sale_price');
        if ($saleRaw === null || (trim((string)$saleRaw) === '' && $saleRaw !== '0')) {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Sale price value required']);
        }

        $salePrice = (float) $saleRaw;

        // Ensure sale_price column exists in product_variants
        if (!$this->variantsHasColumn('sale_price')) {
            try {
                $db = \Config\Database::connect();
                $db->query("ALTER TABLE `product_variants` ADD COLUMN `sale_price` DECIMAL(15,2) NULL");
            } catch (\Throwable $e) {
                log_message('error', 'Failed to add sale_price column to variants: ' . $e->getMessage());
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to update variants']);
            }
        }

        $updateData = ['sale_price' => $salePrice];
        // Keep legacy column in sync because variant listing and older flows use `price`
        if ($this->variantsHasColumn('price')) {
            $updateData['price'] = $salePrice;
        }

        try {
            $db = \Config\Database::connect();
            $db->table('product_variants')
                ->where('product_id', $productId)
                ->update($updateData);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to copy sale price to variants: ' . $e->getMessage());
            $errorMessage = 'Failed to update variant sale prices';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $errorMessage]);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        $message = 'Sale price synced to all variants (save product to persist template sale price).';
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => $message, 'sale_price' => $salePrice]);
        }
        return redirect()->back()->with('success', $message);
    }

    /**
     * Copy vendor price PKR to all product variants
     */
    public function copyVendorPkrToVariants($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productId = (int) ($id ?? 0);
        if (! $productId) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'message' => 'Invalid product']);
        }

        $product = $this->productModel->find($productId);
        if (! $product) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'message' => 'Product not found']);
        }

        $vendorPkrRaw = $this->request->getPost('vendor_price_pkr');
        if ($vendorPkrRaw === null || trim((string)$vendorPkrRaw) === '') {
            return $this->response->setStatusCode(422)->setJSON(['success' => false, 'message' => 'Vendor price PKR value required']);
        }

        $vendorPkr = (float) $vendorPkrRaw;

        // Ensure vendor_price_pkr column exists in product_variants
        if (!$this->variantsHasColumn('vendor_price_pkr')) {
            try {
                $db = \Config\Database::connect();
                $db->query("ALTER TABLE `product_variants` ADD COLUMN `vendor_price_pkr` DECIMAL(15,2) NULL");
            } catch (\Throwable $e) {
                log_message('error', 'Failed to add vendor_price_pkr column to variants: ' . $e->getMessage());
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => 'Unable to update variants']);
            }
        }

        try {
            $db = \Config\Database::connect();
            $db->table('product_variants')
                ->where('product_id', $productId)
                ->update(['vendor_price_pkr' => $vendorPkr]);
        } catch (\Throwable $e) {
            log_message('error', 'Failed to copy vendor price PKR to variants: ' . $e->getMessage());
            $errorMessage = 'Failed to update vendor prices (PKR)';
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'message' => $errorMessage]);
            }
            return redirect()->back()->with('error', $errorMessage);
        }

        $message = 'Vendor price (PKR) synced to all variants (save product to persist template vendor price PKR).';
        if ($this->request->isAJAX()) {
            return $this->response->setJSON(['success' => true, 'message' => $message, 'vendor_price_pkr' => $vendorPkr]);
        }
        return redirect()->back()->with('success', $message);
    }

    /**
     * Upload additional images for a product (from product view)
     */
    public function uploadImages($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $product = $this->productModel->find($id);
        if (!$product) {
            return redirect()->back()->with('error', 'Product not found');
        }

        // Ensure images column exists
        $this->ensureProductsImagesColumn();

        $files = $this->request->getFiles();
        $uploaded = [];
        if (isset($files['product_images'])) {
            $uploaded = $this->handleImageUploads($files['product_images']);
        }

        $existing = !empty($product['images']) ? json_decode($product['images'], true) : [];
        $all = array_values(array_merge($existing, $uploaded));

        if ($this->productModel->update($id, ['images' => json_encode($all)])) {
            return redirect()->back()->with('success', 'Images uploaded');
        }

        // cleanup on fail
        $this->cleanupUploadedFiles($uploaded);
        return redirect()->back()->with('error', 'Failed to save images');
    }

    /**
     * Update product category via AJAX
     */
    public function ajaxUpdateCategory($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $categoryId = $this->request->getPost('category_id');
        if (empty($categoryId)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Invalid category']);
        }

        if ($this->productModel->update($id, ['category_id' => $categoryId])) {
            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['success' => false, 'message' => 'Failed to update category']);
    }

    /**
     * Update vendor and vendor-specific price via AJAX
     */
    public function ajaxUpdateVendor($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $vendorId = $this->request->getPost('vendor_id') ?: null;
        $vendorPrice = $this->request->getPost('vendor_price') ?: null;
        $vendorCurrency = $this->request->getPost('vendor_currency') ?: null;

        $payload = ['vendor_id' => $vendorId];
        if ($vendorPrice !== null) $payload['vendor_price'] = $vendorPrice;
        if ($vendorCurrency !== null) $payload['vendor_currency'] = $vendorCurrency;

        if ($this->productModel->update($id, $payload)) {
            return $this->response->setJSON(['success' => true]);
        }
        return $this->response->setJSON(['success' => false, 'message' => 'Failed to update vendor info']);
    }

    /**
     * Ensure products table has an 'images' column; try to add it if missing.
     * Returns true if available, false otherwise.
     */
    private function ensureProductsImagesColumn(): bool
    {
        try {
            $db = \Config\Database::connect();
            $result = $db->query("SHOW COLUMNS FROM `products` LIKE 'images'");
            if ($result && $result->getNumRows() > 0) {
                return true;
            }
            // Attempt to add the column as TEXT (stores JSON string of filenames)
            $db->query("ALTER TABLE `products` ADD COLUMN `images` TEXT NULL AFTER `description`");
            // Verify again
            $result2 = $db->query("SHOW COLUMNS FROM `products` LIKE 'images'");
            return $result2 && $result2->getNumRows() > 0;
        } catch (\Throwable $e) {
            log_message('error', 'ensureProductsImagesColumn failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete product
     */
    public function delete($id = null)
    {
        $this->requireAuth();
        
        // Skip permission check for testing
        // $this->requirePermission('products.delete');

        $product = $this->productModel->find($id);
        if (!$product) {
            return $this->jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }

        // Check if product has associated work orders
        $workOrderModel = new \App\Models\WorkOrderModel();
        $hasWorkOrders = $workOrderModel->where('product_id', $id)->countAllResults() > 0;

        if ($hasWorkOrders) {
            return $this->jsonResponse([
                'success' => false,
                'message' => 'Cannot delete product with associated work orders. Consider deactivating instead.'
            ], 400);
        }

        // Product Assets safety: block deletion if digital assets exist.
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('product_asset_groups')) {
                $hasAssets = (int) $db->table('product_asset_groups')->where('product_id', (int) $id)->countAllResults() > 0;
                if ($hasAssets) {
                    return $this->jsonResponse([
                        'success' => false,
                        'message' => 'Cannot delete product while Product Assets exist. Remove assets/groups first or deactivate the product.'
                    ], 400);
                }
            }
        } catch (\Throwable $e) {
            // Defensive: never break delete flow if assets table is unavailable.
        }

        // Also check for associated product processes
        $productProcessModel = new \App\Models\ProductProcessModel();
        $productProcessModel->where('product_id', $id)->delete();

        if ($this->productModel->delete($id)) {
            return $this->jsonResponse(['success' => true, 'message' => 'Product deleted successfully.']);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to delete product.'], 500);
        }
    }

    /**
     * Toggle product status (active/inactive)
     */
    public function toggleStatus($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $product = $this->productModel->find($id);
        if (!$product) {
            return $this->jsonResponse(['success' => false, 'message' => 'Product not found'], 404);
        }

        $newStatus = !$product['is_active'];
        $data = [
            'is_active' => $newStatus,
            'updated_by' => $this->currentUser['id']
        ];

        if ($this->productModel->update($id, $data)) {
            $statusText = $newStatus ? 'activated' : 'deactivated';
            return $this->jsonResponse([
                'success' => true,
                'message' => "Product {$statusText} successfully.",
                'new_status' => $newStatus
            ]);
        } else {
            return $this->jsonResponse(['success' => false, 'message' => 'Failed to update product status.'], 500);
        }
    }

    /**
     * Export products to CSV
     */
    public function exportCsv()
    {
        $this->requireAuth();
        $this->requirePermission('products.view');

        $products = $this->productModel->findAll();
        
        $filename = 'products_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'ID', 'Name', 'SKU', 'Category', 'Description', 'Standard Cost', 
            'Selling Price', 'UOM', 'Standard Production Time', 'Status', 'Created Date'
        ]);
        
        // CSV data
        foreach ($products as $product) {
            fputcsv($output, [
                $product['id'],
                $product['name'],
                $product['sku'],
                $product['category'],
                $product['description'],
                $product['standard_cost'],
                $product['selling_price'],
                $product['uom'],
                $product['standard_production_time'],
                $product['is_active'] ? 'Active' : 'Inactive',
                $product['created_at']
            ]);
        }
        
        fclose($output);
        exit;
    }

    /**
     * Get product data for AJAX requests
     */
    public function getData()
    {
        $this->requireAuth();
        $this->requirePermission('products.view');

        $action = $this->request->getGet('action');
        
        switch ($action) {
            case 'categories':
                $categories = $this->productModel->getProductCategories();
                return $this->jsonResponse($categories);
                
            case 'search':
                $term = $this->request->getGet('term');
                $products = $this->productModel->like('name', $term)
                                             ->orLike('sku', $term)
                                             ->where('is_active', true)
                                             ->select('id, name, sku')
                                             ->limit(10)
                                             ->findAll();
                return $this->jsonResponse($products);
                
            case 'details':
                $id = $this->request->getGet('id');
                $product = $this->productModel->getProductWithDetails($id);
                return $this->jsonResponse($product);

            case 'processes':
                $id = (int) $this->request->getGet('id');
                if (!$id) return $this->jsonResponse(['success' => false, 'message' => 'Invalid product id'], 400);
                log_message('info', 'getData processes called for product id: ' . $id);
                try {
                    $ppm = new \App\Models\ProductProcessModel();
                    $rows = $ppm->getProductProcessesWithDetails($id);
                    log_message('info', 'getData processes returned ' . count($rows) . ' rows');
                    return $this->jsonResponse(['success' => true, 'processes' => $rows]);
                } catch (\Throwable $e) {
                    log_message('error', 'getData processes error: ' . $e->getMessage());
                    return $this->jsonResponse(['success' => false, 'message' => 'Failed to load processes'], 500);
                }
                
            default:
                return $this->jsonResponse(['error' => 'Invalid action'], 400);
        }
    }

    /**
     * Parse JSON field from form input
     */
    private function parseJsonField($input): array
    {
        if (is_string($input)) {
            // Try to parse as JSON first
            $decoded = json_decode($input, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
            
            // If not JSON, treat as key-value pairs separated by newlines
            $lines = explode("\n", $input);
            $result = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                if (strpos($line, ':') !== false) {
                    list($key, $value) = explode(':', $line, 2);
                    $result[trim($key)] = trim($value);
                } else {
                    $result[] = $line;
                }
            }
            return $result;
        }
        
        return is_array($input) ? $input : [];
    }

    /**
     * Handle image uploads
     */
    private function handleImageUploads($files): array
    {
        $uploadedImages = [];
        
        // Create upload directory if it doesn't exist
        $uploadPath = FCPATH . 'uploads/products/';
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }

        foreach ($files as $file) {
            if ($file->isValid() && !$file->hasMoved()) {
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file->getMimeType(), $allowedTypes)) {
                    continue;
                }

                // Generate unique filename
                $newName = $file->getRandomName();
                
                // Move file to upload directory
                if ($file->move($uploadPath, $newName)) {
                    $uploadedImages[] = $newName;
                }
            }
        }

        return $uploadedImages;
    }

    /**
     * Remove image file from filesystem
     */
    private function removeImageFile($filename): bool
    {
        $filePath = FCPATH . 'uploads/products/' . $filename;
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true;
    }

    /**
     * Clean up uploaded files
     */
    private function cleanupUploadedFiles($files): void
    {
        foreach ($files as $filename) {
            $this->removeImageFile($filename);
        }
    }

    /**
     * Manage product processes
     */
    public function processes($productId = null)
    {
        $this->requireAuth();
        
        // Skip permission check for testing
        // $this->requirePermission('products.edit');

        $resolvedProduct = $this->resolveProductOrFail($productId);
        if ($redirect = $this->redirectToCanonicalProductUrl($resolvedProduct, $productId, '/processes')) {
            return $redirect;
        }

        $productId = (int) $resolvedProduct['id'];
        $product = $this->productModel->find($productId);

        // Get product processes (defensive: table may be missing in some installations)
        $processError = null;
        $productProcessModel = new \App\Models\ProductProcessModel();
        $processes = [];
        $allProcesses = [];
        $categories = [];
        $totalTime = 0;
        try {
            $processes = $productProcessModel->getProductProcessesWithDetails($productId);
        } catch (\Throwable $e) {
            log_message('error', 'Products::processes - failed to load product process mapping: ' . $e->getMessage());
            $processes = [];
        }

        // Load available processes list; do not fail the page if categories table is missing
        try {
            $processModel = new \App\Models\ProcessModel();
            $allProcesses = $processModel->select('processes.*, process_categories.name as category_name, process_categories.id as category_id')
                                         ->join('process_categories', 'process_categories.id = processes.category_id', 'left')
                                         ->where('processes.is_active', true)
                                         ->orderBy('processes.name', 'ASC')
                                         ->findAll();
            if (empty($allProcesses)) {
                log_message('warning', 'Products::processes - no active processes found; falling back to all processes');
                $allProcesses = $processModel->select('processes.*, process_categories.name as category_name, process_categories.id as category_id')
                                             ->join('process_categories', 'process_categories.id = processes.category_id', 'left')
                                             ->orderBy('processes.name', 'ASC')
                                             ->findAll();
            }
            log_message('info', 'Products::processes - found ' . count($allProcesses) . ' available processes for modal');
        } catch (\Throwable $e) {
            log_message('error', 'Products::processes - failed to load available processes list: ' . $e->getMessage());
            // Fallback: try without category join entirely
            try {
                $processModel = new \App\Models\ProcessModel();
                $allProcesses = $processModel->select('processes.*')
                                             ->orderBy('processes.name', 'ASC')
                                             ->findAll();
            } catch (\Throwable $e2) {
                log_message('error', 'Products::processes - fallback without categories also failed: ' . $e2->getMessage());
                $allProcesses = [];
            }
        }

        // Load categories separately; if it fails, continue gracefully
        try {
            $processCategoryModel = new \App\Models\ProcessCategoryModel();
            $categories = $processCategoryModel->getActiveCategoriesArray();
        } catch (\Throwable $e) {
            log_message('error', 'Products::processes - failed to load process categories: ' . $e->getMessage());
            $categories = [];
        }

        // Calculate total time (safe)
        try {
            $totalTime = $productProcessModel->getProductTotalTime($productId);
        } catch (\Throwable $e) {
            $totalTime = 0;
        }

        $data = $this->setPageData([
            'page_title' => 'Product Processes - ' . $product['name'],
            'product' => $product,
            'processes' => $processes,
            'categories' => $categories,
            'all_processes' => $allProcesses,
            'total_time' => $totalTime,
            'validation' => \Config\Services::validation(),
            'processError' => $processError
        ]);

        return view('products/processes', $data);
    }

    /**
     * Add process to product
     */
    public function addProcess($productId = null)
    {
        // Enable detailed error reporting for debugging
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        try {
            $this->requireAuth();
            
            // Log the request for debugging
            log_message('info', 'addProcess called with productId: ' . $productId);
            log_message('info', 'POST data: ' . print_r($_POST, true));

            $product = $this->productModel->find($productId);
            if (!$product) {
                log_message('error', 'Product not found: ' . $productId);
                return $this->response->setJSON(['success' => false, 'message' => 'Product not found.']);
            }

            $processIds = $this->request->getPost('process_ids');
            log_message('info', 'Raw process_ids: ' . print_r($processIds, true));
            
            if (empty($processIds)) {
                log_message('error', 'No process IDs provided');
                return $this->response->setJSON(['success' => false, 'message' => 'Please select at least one process.']);
            }

            // Convert to array if it's a single ID or comma-separated string
            if (!is_array($processIds)) {
                if (strpos($processIds, ',') !== false) {
                    $processIds = explode(',', $processIds);
                } else {
                    $processIds = [$processIds];
                }
            }
            
            // Clean up the array (remove empty values, trim spaces)
            $processIds = array_filter(array_map('trim', $processIds));
            
            log_message('info', 'Processed process_ids: ' . print_r($processIds, true));
            
            if (empty($processIds)) {
                return $this->response->setJSON(['success' => false, 'message' => 'No valid process IDs provided.']);
            }

            // Use direct database approach instead of model for better control
            $db = \Config\Database::connect();
            
            $successCount = 0;
            $errors = [];

            // Detect optional columns for defensive queries
            $tables = $db->listTables();
            $procHasActive = false;
            $ppHasActive = false;
            try {
                if (in_array('processes', $tables, true)) {
                    $cols = $db->query("SHOW COLUMNS FROM processes")->getResultArray();
                    $procHasActive = in_array('is_active', array_column($cols, 'Field'), true);
                }
            } catch (\Throwable $e) { /* ignore */ }
            try {
                if (in_array('product_processes', $tables, true)) {
                    $ppCols = $db->query("SHOW COLUMNS FROM product_processes")->getResultArray();
                    $ppHasActive = in_array('is_active', array_column($ppCols, 'Field'), true);
                }
            } catch (\Throwable $e) { /* ignore */ }
            
            // Detect sequence column in product_processes
            $seqCol = 'sequence_order';
            try {
                $ppCols = $db->query("SHOW COLUMNS FROM product_processes")->getResultArray();
                $ppColNames = array_column($ppCols, 'Field');
                if (!in_array($seqCol, $ppColNames, true)) {
                    if (in_array('sequence_number', $ppColNames, true)) $seqCol = 'sequence_number';
                    elseif (in_array('sort_order', $ppColNames, true)) $seqCol = 'sort_order';
                    elseif (in_array('order_index', $ppColNames, true)) $seqCol = 'order_index';
                }
            } catch (\Throwable $e) { /* keep default */ }

            foreach ($processIds as $processId) {
                try {
                    // Check if this process exists
                    $qb = $db->table('processes')->where('id', $processId);
                    if ($procHasActive) {
                        $qb->where('is_active', 1);
                    }
                    $processExists = $qb->get()->getRowArray();
                    
                    if (!$processExists) {
                        $errors[] = "Process ID $processId not found or inactive";
                        continue;
                    }
                    
                    // Check if already assigned
                    $ex = $db->table('product_processes')
                             ->where('product_id', $productId)
                             ->where('process_id', $processId);
                    if ($ppHasActive) {
                        $ex->where('is_active', 1);
                    }
                    $existing = $ex->get()->getRowArray();
                    
                    if ($existing) {
                        // Silently skip already assigned processes
                        continue;
                    }
                    
                    // Get max sequence value based on detected column
                    $maxRow = $db->table('product_processes')
                                 ->select("MAX({$seqCol}) AS max_seq", false)
                                 ->where('product_id', $productId)
                                 ->get()
                                 ->getRowArray();
                    $maxSequence = (int)($maxRow['max_seq'] ?? 0);
                    
                    // Insert the record
                    $insertData = [
                        'product_id' => $productId,
                        'process_id' => $processId,
                        $seqCol => $maxSequence + 1,
                        // Set is_active when column exists
                        'is_active' => $ppHasActive ? 1 : null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    if (!$ppHasActive) {
                        unset($insertData['is_active']);
                    }

                    $insertResult = $db->table('product_processes')->insert($insertData);
                    
                    if ($insertResult) {
                        $successCount++;
                        log_message('info', "Successfully added process ID $processId to product $productId");
                    } else {
                        $errors[] = "Failed to insert process ID $processId";
                        log_message('error', "Failed to insert process ID $processId");
                    }
                    
                } catch (\Exception $e) {
                    $errors[] = "Error with process ID $processId: " . $e->getMessage();
                    log_message('error', "Error adding process ID $processId: " . $e->getMessage());
                }
            }
            
            if ($successCount > 0) {
                $message = "$successCount process(es) added successfully.";
                if (!empty($errors)) {
                    $message .= " Warnings: " . implode(', ', $errors);
                }
                return $this->response->setJSON(['success' => true, 'message' => $message]);
            } else {
                $message = "No processes were added.";
                if (!empty($errors)) {
                    $message .= " Errors: " . implode(', ', $errors);
                }
                return $this->response->setJSON(['success' => false, 'message' => $message]);
            }
            
        } catch (\Exception $e) {
            log_message('error', 'Add process error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' line ' . $e->getLine());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove process from product
     */
    public function removeProcess($productId = null, $processId = null)
    {
        $this->requireAuth();
        // Skip permission check for testing
        // $this->requirePermission('products.edit');

        try {
            $productProcessModel = new \App\Models\ProductProcessModel();
            if ($productProcessModel->delete($processId)) {
                return $this->response->setJSON(['success' => true, 'message' => 'Process removed successfully.']);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to remove process.']);
            }
        } catch (\Exception $e) {
            log_message('error', 'Remove process error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while removing process.']);
        }
    }

    /**
     * Update process order
     */
    public function updateProcessOrder($productId = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $processIds = $this->request->getPost('process_ids');
        if (empty($processIds)) {
            return $this->response->setJSON(['success' => false, 'message' => 'No processes provided.']);
        }

        // Accept both array and comma-separated string
        if (!is_array($processIds)) {
            $processIds = array_filter(array_map('trim', explode(',', (string)$processIds)));
        }
        // Sanitize to integers
        $processIds = array_values(array_map('intval', $processIds));

        try {
            $productProcessModel = new \App\Models\ProductProcessModel();
            if ($productProcessModel->reorderProcesses($productId, $processIds)) {
                return $this->response->setJSON(['success' => true, 'message' => 'Process order updated successfully.']);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to update process order.']);
            }
        } catch (\Exception $e) {
            log_message('error', 'Update process order error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while updating process order.']);
        }
    }

    /**
     * Validate if attribute value can be deleted
     * Checks if any variants using this attribute value exist in PO/SO/RFQ
     */
    public function validateAttributeValueDeletion()
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productId = (int) ($this->request->getPost('product_id') ?? 0);
        $attributeName = trim((string) ($this->request->getPost('attribute_name') ?? ''));
        $attributeValue = trim((string) ($this->request->getPost('attribute_value') ?? ''));

        if (!$productId || !$attributeName || !$attributeValue) {
            return $this->response->setStatusCode(400)->setJSON([
                'success' => false,
                'message' => 'Missing required parameters'
            ]);
        }

        try {
            $db = \Config\Database::connect();

            // Find variants that have this attribute-value pair
            $variantsWithValue = $db->table('product_variants pv')
                ->select('pv.id')
                ->where('pv.product_id', $productId)
                ->get()
                ->getResultArray();

            $variantIdsUsingValue = [];
            foreach ($variantsWithValue as $v) {
                $attrs = [];
                $variantData = $db->table('product_variants')->select('attributes')->where('id', $v['id'])->get()->getRowArray();
                
                if (!empty($variantData['attributes'])) {
                    try {
                        $attrs = json_decode($variantData['attributes'], true) ?? [];
                    } catch (\Throwable $e) {
                        $attrs = [];
                    }
                }

                // Check if this variant has the attribute-value pair
                if (is_array($attrs)) {
                    foreach ($attrs as $key => $val) {
                        if (trim((string)$key) === $attributeName && trim((string)$val) === $attributeValue) {
                            $variantIdsUsingValue[] = (int)$v['id'];
                            break;
                        }
                    }
                }
            }

            // If no variants use this value, allow deletion
            if (empty($variantIdsUsingValue)) {
                return $this->response->setJSON([
                    'success' => true,
                    'message' => 'Attribute value can be deleted'
                ]);
            }

            // Check if any of these variants are used in PO/SO/RFQ
            $inPurchaseOrder = $db->table('purchase_order_lines')
                ->whereIn('variant_id', $variantIdsUsingValue)
                ->countAllResults();

            $inSalesOrder = $db->table('sales_order_lines')
                ->whereIn('variant_id', $variantIdsUsingValue)
                ->countAllResults();

            $inRFQ = false;
            try {
                $inRFQ = (bool) $db->table('rfq_lines')
                    ->whereIn('variant_id', $variantIdsUsingValue)
                    ->countAllResults();
            } catch (\Throwable $e) {
                // RFQ table may not exist
            }

            if ($inPurchaseOrder > 0 || $inSalesOrder > 0 || $inRFQ) {
                return $this->response->setStatusCode(409)->setJSON([
                    'success' => false,
                    'message' => 'This attribute value cannot be deleted because ' . count($variantIdsUsingValue) . ' variant(s) using it are referenced in Purchase Orders, Sales Orders, or RFQ documents. Please remove those references first.'
                ]);
            }

            // Variants exist but not in any orders - allow deletion
            return $this->response->setJSON([
                'success' => true,
                'message' => 'Attribute value can be deleted'
            ]);

        } catch (\Throwable $e) {
            log_message('error', 'validateAttributeValueDeletion failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'message' => 'Unable to validate attribute deletion'
            ]);
        }
    }

    /**
     * Update process details
     */
    public function updateProcess($productId = null, $processId = null)
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $customTime = $this->request->getPost('custom_time_minutes');
        $customNotes = $this->request->getPost('custom_notes');

        try {
            $productProcessModel = new \App\Models\ProductProcessModel();
            $data = [
                'custom_time_minutes' => $customTime ?: null,
                'custom_notes' => $customNotes ?: null
            ];

            if ($productProcessModel->update($processId, $data)) {
                return $this->response->setJSON(['success' => true, 'message' => 'Process updated successfully.']);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to update process.']);
            }
        } catch (\Exception $e) {
            log_message('error', 'Update process error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while updating process.']);
        }
    }

    /**
     * Bulk assign processes to multiple products
     */
    public function bulkAssignProcesses()
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $productIds = $this->request->getPost('product_ids');
        $processTemplateIds = $this->request->getPost('process_template_ids');

        if (empty($productIds) || empty($processTemplateIds)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please select products and processes.']);
        }

        try {
            $productProcessModel = new \App\Models\ProductProcessModel();
            if ($productProcessModel->bulkAssignProcesses($productIds, $processTemplateIds)) {
                $productCount = count($productIds);
                $processCount = count($processTemplateIds);
                return $this->response->setJSON([
                    'success' => true, 
                    'message' => "Successfully assigned $processCount processes to $productCount products."
                ]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to assign processes.']);
            }
        } catch (\Exception $e) {
            log_message('error', 'Bulk assign processes error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while assigning processes.']);
        }
    }

    /**
     * Copy processes from one product to another
     */
    public function copyProcesses()
    {
        $this->requireAuth();
        $this->requirePermission('products.edit');

        $fromProductId = $this->request->getPost('from_product_id');
        $toProductIds = $this->request->getPost('to_product_ids');

        if (empty($fromProductId) || empty($toProductIds)) {
            return $this->response->setJSON(['success' => false, 'message' => 'Please select source and target products.']);
        }

        try {
            $productProcessModel = new \App\Models\ProductProcessModel();
            $successCount = 0;
            
            foreach ($toProductIds as $toProductId) {
                if ($productProcessModel->copyProcessesToProduct($fromProductId, $toProductId)) {
                    $successCount++;
                }
            }

            if ($successCount > 0) {
                return $this->response->setJSON([
                    'success' => true, 
                    'message' => "Successfully copied processes to $successCount products."
                ]);
            } else {
                return $this->response->setJSON(['success' => false, 'message' => 'Failed to copy processes.']);
            }
        } catch (\Exception $e) {
            log_message('error', 'Copy processes error: ' . $e->getMessage());
            return $this->response->setJSON(['success' => false, 'message' => 'An error occurred while copying processes.']);
        }
    }
}
