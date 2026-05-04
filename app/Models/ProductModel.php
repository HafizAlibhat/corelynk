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
     * Get products with filters and pagination
     * @param string|null $searchTerm Keyword search across product name, code, variant code, description, attributes
     * @param mixed $categoryFilter Category ID filter
     * @param mixed $statusFilter Status filter (1=active, 0=inactive)
     * @param int $perPage Records per page for pagination
     * @param string|null $typeFilter Product type (storable, consumable, service)
     * @param array $attributeFilters Multiple attribute filters: [{name: 'Color', value: 'Blue'}]
     */
    public function getProductsWithFilters(
        $searchTerm = null,
        $categoryFilter = null,
        $statusFilter = null,
        $perPage = 20,
        $typeFilter = null,
        $attributeFilters = []
    ): array
    {
        $builder = $this->select('products.*, product_categories.name as category_name,
                COALESCE((SELECT SUM(sb.quantity) FROM stock_balances sb WHERE sb.product_id = products.id AND sb.variant_id IS NULL), 0) AS simple_stock,
                COALESCE((SELECT SUM(vi.quantity - vi.reserved) FROM product_variants pv LEFT JOIN variant_inventory vi ON vi.variant_id = pv.id WHERE pv.product_id = products.id), 0) AS variant_stock,
                (SELECT COUNT(*) FROM product_variants pv2 WHERE pv2.product_id = products.id) AS variant_count')
                        ->join('product_categories', 'product_categories.id = products.category_id', 'left');

        $db = $this->db;

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
            foreach ($attributeFilters as $filter) {
                if (!is_array($filter)) continue;
                $attrN = trim((string)($filter['name'] ?? ''));
                $attrV = trim((string)($filter['value'] ?? ''));
                if (empty($attrN) || empty($attrV)) continue;
                $nl = strtolower($db->escapeLikeString($attrN));
                $vl = strtolower($db->escapeLikeString($attrV));
                // Each attribute pair: name AND value must both appear in the same variant JSON
                $variantConditions[] = "LOWER(pva.attributes) LIKE '%{$nl}%'";
                $variantConditions[] = "LOWER(pva.attributes) LIKE '%{$vl}%'";
            }
            if (!empty($variantConditions)) {
                $combined = implode(' AND ', $variantConditions);
                $builder->where("EXISTS (SELECT 1 FROM product_variants pva WHERE pva.product_id = products.id AND {$combined})", null, false);
            }
        }

        // Apply status filter
        if (!empty($statusFilter)) {
            $isActive = ($statusFilter === 'active' || $statusFilter === '1' || $statusFilter === 1);
            $builder->where('products.is_active', $isActive ? 1 : 0);
        } else {
            // Default to active products only when no search term is provided
            if (empty($searchTerm)) {
                $builder->where('products.is_active', 1);
            }
        }

        $builder->orderBy('products.name', 'ASC');

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
                $builder->where("LOWER(pv.attributes) LIKE '%{$nl}%' ESCAPE '!'", null, false)
                        ->where("LOWER(pv.attributes) LIKE '%{$vl}%' ESCAPE '!'", null, false);
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

