<?php

namespace App\Models;

use CodeIgniter\Model;
use App\Helpers\WeightHelper;
use App\Traits\PublicIdTrait;

class QuotationModel extends Model
{
    use PublicIdTrait;

    protected $table = 'quotations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'quote_number','company_id','customer_id','price_list_id','issue_date','expires_at','status','currency','quote_currency','base_currency',
        'subtotal','discount','document_discount_type','document_discount_value','discount_exclude_shipping','tax','tax_total','shipping_amount','total_weight','total','notes','created_by','public_id','created_at','updated_at','deleted_at'
    ];

    protected $useTimestamps = true;
    protected $useSoftDeletes = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    public function __construct(?\CodeIgniter\Database\ConnectionInterface $db = null, ?\CodeIgniter\Validation\ValidationInterface $validation = null)
    {
        parent::__construct($db, $validation);
        $this->bootPublicId();
    }

    /**
     * Fetch a quotation with its lines
     */
    public function getWithLines(int $id): ?array
    {
        $quote = $this->find($id);
        if (!$quote) return null;

        $lineModel = new QuotationLineModel();
        // Avoid ordering by a column that might not exist on older schemas
        $db = \Config\Database::connect();
        $orderField = 'id';
        try {
            $lineCols = $db->getFieldNames($lineModel->table);
            if (in_array('sort_order', $lineCols)) $orderField = 'sort_order';
        } catch (\Throwable $_) {
            // fallback to id
            $orderField = 'id';
        }
        $lines = $lineModel->where('quotation_id', $id)->orderBy($orderField, 'asc')->findAll();

        // Preload products referenced by the lines to avoid N+1 queries in the view
        $productMap = [];
        $productMapByCode = [];
        $variantMap = [];
        $variantMapByArt = [];
        try {
            $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($lines, 'product_id')))));
            $lineCodes = array_values(array_unique(array_filter(array_map(static function ($ln) {
                return strtoupper(trim((string)($ln['product_code'] ?? '')));
            }, $lines))));

            if (!empty($productIds)) {
                $pm = new \App\Models\ProductModel();
                $products = $pm->whereIn('id', $productIds)->findAll();
                foreach ($products as $p) {
                    // Normalize image URL (support legacy 'image' and modern 'images' json array)
                    $imageUrl = base_url('assets/images/no-image.png');
                    if (!empty($p['image'])) {
                        $imageUrl = base_url('/uploads/products/' . ltrim($p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $imageUrl = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                        }
                    }

                    $entry = [
                        'code' => $p['code'] ?? ($p['sku'] ?? ''),
                        'sku' => $p['sku'] ?? null,
                        'name' => $p['name'] ?? null,
                        'unit' => $p['unit'] ?? null,
                        'unit_weight' => $p['unit_weight'] ?? ($p['weight'] ?? null),
                        'weight_unit' => $p['weight_unit'] ?? 'kg',
                        'image_url' => $imageUrl,
                    ];

                    $productMap[(int)$p['id']] = $entry;

                    $codeKey = strtoupper(trim((string)($p['code'] ?? '')));
                    if ($codeKey !== '') {
                        $productMapByCode[$codeKey] = $entry;
                    }
                    $skuKey = strtoupper(trim((string)($p['sku'] ?? '')));
                    if ($skuKey !== '') {
                        $productMapByCode[$skuKey] = $entry;
                    }
                }
            }

            // Fallback preload by product code for legacy/recovered lines with null product_id.
            if (!empty($lineCodes)) {
                $pm = new \App\Models\ProductModel();
                $productsByCode = $pm->groupStart()
                    ->whereIn('code', $lineCodes)
                    ->orWhereIn('sku', $lineCodes)
                    ->groupEnd()
                    ->findAll();

                foreach ($productsByCode as $p) {
                    $imageUrl = base_url('assets/images/no-image.png');
                    if (!empty($p['image'])) {
                        $imageUrl = base_url('/uploads/products/' . ltrim((string)$p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $imageUrl = base_url('/uploads/products/' . ltrim((string)$imgs[0], '/'));
                        }
                    }

                    $entry = [
                        'code' => $p['code'] ?? ($p['sku'] ?? ''),
                        'sku' => $p['sku'] ?? null,
                        'name' => $p['name'] ?? null,
                        'unit' => $p['unit'] ?? null,
                        'unit_weight' => $p['unit_weight'] ?? ($p['weight'] ?? null),
                        'weight_unit' => $p['weight_unit'] ?? 'kg',
                        'image_url' => $imageUrl,
                    ];

                    $codeKey = strtoupper(trim((string)($p['code'] ?? '')));
                    if ($codeKey !== '') {
                        $productMapByCode[$codeKey] = $entry;
                    }
                    $skuKey = strtoupper(trim((string)($p['sku'] ?? '')));
                    if ($skuKey !== '') {
                        $productMapByCode[$skuKey] = $entry;
                    }
                }
            }

            // Preload variant metadata so variant lines get the correct code and image.
            $variantIds = array_values(array_unique(array_filter(array_map('intval', array_column($lines, 'product_variant_id')))));
            if (!empty($variantIds) || !empty($lineCodes)) {
                $builder = $db->table('product_variants')->select('id, art_number, weight, image');
                if (!empty($variantIds) && !empty($lineCodes)) {
                    $builder->groupStart()->whereIn('id', $variantIds)->orWhereIn('art_number', $lineCodes)->groupEnd();
                } elseif (!empty($variantIds)) {
                    $builder->whereIn('id', $variantIds);
                } else {
                    $builder->whereIn('art_number', $lineCodes);
                }
                $variantRows = $builder->get()->getResultArray();
                foreach ($variantRows as $vr) {
                    $variantImage = trim((string)($vr['image'] ?? ''));
                    $variantImageUrl = '';
                    if ($variantImage !== '') {
                        $variantImageUrl = preg_match('#^(https?:)?//#i', $variantImage)
                            ? $variantImage
                            : base_url('/uploads/variants/' . ltrim($variantImage, '/'));
                    }

                    $entry = [
                        'art_number' => $vr['art_number'] ?? '',
                        'weight' => isset($vr['weight']) ? (float)$vr['weight'] : 0.0,
                        'image_url' => $variantImageUrl,
                    ];

                    $variantMap[(int)$vr['id']] = $entry;

                    $artKey = strtoupper(trim((string)($vr['art_number'] ?? '')));
                    if ($artKey !== '') {
                        $variantMapByArt[$artKey] = $entry;
                    }
                }
            }
        } catch (\Throwable $_) {
            // best-effort; continue without product enrichment
            $productMap = [];
            $productMapByCode = [];
            $variantMap = [];
            $variantMapByArt = [];
        }

        // Recalculate per-line amounts and document totals for display consistency.
        $calculatedLines = [];
    $subtotal = 0.0;
    $discountTotal = 0.0;
    $taxTotal = 0.0;
    $totalWeight = 0.0;

        foreach ($lines as $ln) {
            // Enrich line with product metadata if available
            $prod = null;
            if (!empty($ln['product_id'])) {
                $pid = (int)$ln['product_id'];
                $prod = $productMap[$pid] ?? null;
            }
            $lineCode = strtoupper(trim((string)($ln['product_code'] ?? '')));
            if (!$prod && $lineCode !== '' && isset($productMapByCode[$lineCode])) {
                $prod = $productMapByCode[$lineCode];
            }
            if ($prod) {
                if (empty($ln['product_code'])) {
                    // For variant products, art_number takes priority over product.code
                    $variantId = !empty($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null;
                    $artNumber = $variantId ? (($variantMap[$variantId]['art_number'] ?? '') ?: '') : '';
                    $ln['product_code'] = $artNumber ?: ($prod['code'] ?? $prod['sku']);
                }
                if (empty($ln['product_name']) && !empty($prod['name'])) {
                    $ln['product_name'] = $prod['name'];
                }
                if (empty($ln['unit']) && !empty($prod['unit'])) {
                    $ln['unit'] = $prod['unit'];
                }
                if (empty($ln['unit_weight'])) {
                    $variantId = !empty($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : 0;
                    $variantWeight = $variantId > 0 ? (float)($variantMap[$variantId]['weight'] ?? 0) : 0.0;
                    if ($variantWeight > 0) {
                        $ln['unit_weight'] = $variantWeight;
                    } elseif (!empty($prod['unit_weight'])) {
                        $ln['unit_weight'] = $prod['unit_weight'];
                    }
                }
                if (empty($ln['weight'])) {
                    $ln['weight'] = $ln['unit_weight'] ?? ($prod['unit_weight'] ?? null);
                }
                if (empty($ln['weight_unit'])) {
                    $ln['weight_unit'] = $prod['weight_unit'] ?? 'kg';
                }
                $variantId = !empty($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : 0;
                $variantImageUrl = $variantId > 0 ? (string)($variantMap[$variantId]['image_url'] ?? '') : '';
                if ($variantImageUrl === '' && $lineCode !== '' && isset($variantMapByArt[$lineCode])) {
                    $variantImageUrl = (string)($variantMapByArt[$lineCode]['image_url'] ?? '');
                }

                // Prefer variant image, then product image when line image is empty or placeholder.
                $lineImageUrl = trim((string)($ln['product_image_url'] ?? ''));
                $lineIsDefault = ($lineImageUrl !== '' && stripos($lineImageUrl, 'assets/images/no-image.png') !== false);
                if ($variantImageUrl !== '' && ($lineImageUrl === '' || $lineIsDefault)) {
                    $ln['product_image_url'] = $variantImageUrl;
                } elseif ($lineImageUrl === '' || $lineIsDefault) {
                    $ln['product_image_url'] = $prod['image_url'];
                }
            } else {
                $ln['product_image_url'] = $ln['product_image_url'] ?? base_url('assets/images/no-image.png');
                if (empty($ln['weight_unit'])) {
                    $ln['weight_unit'] = 'kg';
                }
            }

            // use lineModel helper to compute canonical amounts
            $calc = $lineModel->calculateLineTotal($ln);
            // merge calculated keys into the line for view rendering
            $ln['base_amount'] = $calc['base_amount'];
            $ln['discount_amount'] = $calc['discount_amount'];
            $ln['net_amount'] = $calc['net_amount'];
            $ln['tax_amount'] = $calc['tax_amount'];
            $ln['line_total'] = $calc['line_total'];
            $ln['quantity'] = $calc['quantity'];
            $ln['unit_price'] = $calc['unit_price'];

            $calculatedLines[] = $ln;

            $subtotal += $calc['base_amount'];
            $discountTotal += $calc['discount_amount'];
            $taxTotal += $calc['tax_amount'];

            $variantId = !empty($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : 0;
            $variantWeight = $variantId > 0 ? (float)($variantMap[$variantId]['weight'] ?? 0) : 0.0;
            if ($variantWeight <= 0 && !empty($ln['product_code'])) {
                $lineCode = strtoupper(trim((string)$ln['product_code']));
                foreach ($variantMap as $vm) {
                    if (strtoupper((string)($vm['art_number'] ?? '')) === $lineCode) {
                        $variantWeight = (float)($vm['weight'] ?? 0);
                        break;
                    }
                }
            }
            $unitWeight = isset($ln['unit_weight']) && (float)$ln['unit_weight'] > 0
                ? (float)$ln['unit_weight']
                : (isset($ln['weight']) && (float)$ln['weight'] > 0
                    ? (float)$ln['weight']
                    : ($variantWeight > 0 ? $variantWeight : 0.0));
            $weightUnit = $ln['weight_unit'] ?? ($prod['weight_unit'] ?? 'kg');
            $unitWeightKg = WeightHelper::toKilograms($unitWeight, $weightUnit);
            $totalWeight += $unitWeightKg * ((float)$ln['quantity']);
        }

        $documentDiscountType = strtolower((string)($quote['document_discount_type'] ?? 'fixed'));
        if (!in_array($documentDiscountType, ['percent', 'fixed'], true)) {
            $documentDiscountType = 'fixed';
        }
        $documentDiscountValue = (float)($quote['document_discount_value'] ?? 0);
        $excludeShipping = !array_key_exists('discount_exclude_shipping', $quote)
            ? true
            : ((int)$quote['discount_exclude_shipping'] === 1);

        $lineNet = max(0.0, $subtotal - $discountTotal);
        $shippingAmount = isset($quote['shipping_amount']) ? (float)$quote['shipping_amount'] : 0.0;
        $docBase = $lineNet + $taxTotal + ($excludeShipping ? 0.0 : $shippingAmount);
        $documentDiscountAmount = 0.0;
        if ($documentDiscountValue > 0) {
            if ($documentDiscountType === 'percent') {
                $documentDiscountAmount = $docBase * ($documentDiscountValue / 100.0);
            } else {
                $documentDiscountAmount = $documentDiscountValue;
            }
        }
        $documentDiscountAmount = min(max(0.0, $documentDiscountAmount), $docBase);

        // Attach computed header totals (rounded)
        $quote['subtotal'] = round($subtotal, 2);
        $quote['discount'] = round($discountTotal + $documentDiscountAmount, 2);
        $quote['tax'] = round($taxTotal, 2);
        // Single source of truth per spec
        $quote['shipping_amount'] = $shippingAmount;
        // Always show a computed weight from current line/product data in view payload.
        $quote['total_weight'] = round($totalWeight, 3);
        $quote['document_discount_type'] = $documentDiscountType;
        $quote['document_discount_value'] = round($documentDiscountValue, 2);
        $quote['discount_exclude_shipping'] = $excludeShipping ? 1 : 0;
        $quote['document_discount_amount'] = round($documentDiscountAmount, 2);
        $quote['total'] = round(($lineNet + $taxTotal + $shippingAmount - $documentDiscountAmount), 2);

        // Provide a 'currency' alias for consumers that expect a single currency field.
        $quote['currency'] = $quote['quote_currency'] ?? $quote['base_currency'] ?? 'USD';

        $quote['lines'] = $calculatedLines;
        return $quote;
    }

    /**
     * Alias for compatibility: return the quotation header as array or null
     */
    public function getQuotationById(int $id): ?array
    {
        return $this->find($id) ?: null;
    }

    /**
     * Alias to fetch lines for a quotation
     */
    public function getQuotationLines(int $quotationId): array
    {
        $lineModel = new QuotationLineModel();
        return $lineModel->where('quotation_id', $quotationId)->orderBy('sort_order', 'asc')->findAll();
    }

    /**
     * Generate a mostly-unique quote number. You may replace with a sequential strategy.
     */
    public function generateQuoteNumber(?\CodeIgniter\Database\BaseConnection $db = null): string
    {
        // Produce sequential numbers like RI-Q0001 (prefix configurable in Settings)
        $db = $db ?: \Config\Database::connect();

        // Read prefix from company_settings if the column exists
        $prefix = 'RI';
        try {
            $cols = $db->getFieldNames('company_settings');
            if (in_array('quotation_prefix', $cols)) {
                $row = $db->table('company_settings')->select('quotation_prefix')->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
                $val = trim((string)($row['quotation_prefix'] ?? ''));
                if ($val !== '') $prefix = $val;
            }
        } catch (\Throwable $_) {
            // best-effort
        }

    // Use Q series for quotations (RI-Q0001)
    $fullPrefix = $prefix . '-Q';
        try {
            // Lock the current tail row for this prefix to reduce race conditions.
            $escaped = str_replace("'", "''", $fullPrefix) . '%';
            $row = $db->query(
                "SELECT quote_number FROM {$this->table} WHERE quote_number LIKE '{$escaped}' ORDER BY id DESC LIMIT 1 FOR UPDATE"
            )->getRowArray();

            $lastNumber = 0;
            if ($row && ! empty($row['quote_number'])) {
                $last = (string)$row['quote_number'];
                if (preg_match('/' . preg_quote($fullPrefix, '/') . '(\d+)/', $last, $m)) {
                    $lastNumber = (int)$m[1];
                } else {
                    $lastNumber = (int)preg_replace('/\D+/', '', $last);
                }
            }
            $next = $lastNumber + 1;
            return $fullPrefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $_) {
            // fallback
            return $fullPrefix . str_pad('1', 4, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Create a quotation and its lines inside a DB transaction.
     * Expects $data to have keys for the quotation and a 'lines' array of line items.
     * Returns inserted quotation id on success or false on failure.
     */
    public function createQuotation(array $data)
    {
        $db = \Config\Database::connect();

    $lines = $data['lines'] ?? [];
    unset($data['lines']);

    // Preserve user-provided shipping_amount from payload (do NOT rely on calculators here)
    $payloadShipping = isset($data['shipping_amount']) ? (float)$data['shipping_amount'] : 0.0;
    $documentDiscountType = strtolower((string)($data['document_discount_type'] ?? 'fixed'));
    if (!in_array($documentDiscountType, ['percent', 'fixed'], true)) {
        $documentDiscountType = 'fixed';
    }
    $documentDiscountValue = isset($data['document_discount_value']) ? (float)$data['document_discount_value'] : 0.0;
    $discountExcludeShipping = !array_key_exists('discount_exclude_shipping', $data)
        ? true
        : ((int)$data['discount_exclude_shipping'] === 1);


        // Ensure quote_number
        if (empty($data['quote_number'])) {
            $data['quote_number'] = $this->generateQuoteNumber($db);
        }

        // (debug logging removed per deterministic fix request)

    // Calculate totals and normalize lines (shipping used for totals only, but do not overwrite payload shipping)
    $calc = $this->calculateTotals(
        $lines,
        $payloadShipping,
        $documentDiscountType,
        $documentDiscountValue,
        $discountExcludeShipping
    );

        // Map calculation results into whatever columns exist in the live DB.
        // Query live column names and only keep keys that exist in the table to avoid unknown column errors.
        try {
            $tableCols = $db->getFieldNames($this->table);
        } catch (\Throwable $e) {
            // Fallback: use model's allowedFields if DB introspection fails
            $tableCols = is_array($this->allowedFields) ? $this->allowedFields : [];
        }

        // If the live DB is missing the canonical 'shipping_amount' column,
        // create it automatically (best-effort) so that create/save operations
        // using 'shipping_amount' do not silently drop the value.
        if (!in_array('shipping_amount', $tableCols)) {
            try {
                // Add column with sensible precision and default 0.00
                $db->query("ALTER TABLE `" . $this->table . "` ADD COLUMN `shipping_amount` DECIMAL(12,2) NOT NULL DEFAULT 0");
                // Refresh column list
                $tableCols = $db->getFieldNames($this->table);
            } catch (\Throwable $e) {
                // If alter fails, log so admin can diagnose privileges/schema issues.
                if (function_exists('log_message')) {
                    try { log_message('error', 'QuotationModel:createQuotation - failed to add shipping_amount column: ' . $e->getMessage()); } catch (\Throwable $_) {}
                }
            }
        }

    // Best-effort mapping: prefer modern column names
    if (in_array('subtotal', $tableCols)) $data['subtotal'] = $calc['subtotal'];
        if (in_array('discount', $tableCols)) $data['discount'] = $calc['discount'];
        elseif (in_array('document_discount_value', $tableCols)) $data['document_discount_value'] = $calc['discount'];

    if (in_array('tax', $tableCols)) $data['tax'] = $calc['tax'];
    elseif (in_array('tax_total', $tableCols)) $data['tax_total'] = $calc['tax'];

        // Force writing the shipping_amount from the original payload. Do NOT overwrite with calculator output.
        $data['shipping_amount'] = $payloadShipping;
    if (in_array('total_weight', $tableCols)) $data['total_weight'] = $calc['total_weight'];

    if (in_array('total', $tableCols)) $data['total'] = $calc['total'];

        $selectedCurrency = strtoupper(trim((string)($data['currency'] ?? ($data['quote_currency'] ?? ($data['base_currency'] ?? '')))));
        if ($selectedCurrency === '') {
            $selectedCurrency = 'USD';
        }

        if (in_array('currency', $tableCols)) {
            $data['currency'] = $selectedCurrency;
        }
        if (in_array('quote_currency', $tableCols)) {
            $data['quote_currency'] = $selectedCurrency;
        }
        if (in_array('base_currency', $tableCols) && empty($data['base_currency'])) {
            $data['base_currency'] = $selectedCurrency;
        }

        // Protect against inserting fields that don't exist (e.g. price_list_id on older DBs)
        if (isset($data['price_list_id']) && !in_array('price_list_id', $tableCols)) unset($data['price_list_id']);

        // Use a direct DB insert to avoid model filtering interference and ensure shipping_amount is persisted.
        $db->transStart();
        try {
            $maxAttempts = 3;
            $attempt = 0;
            $insertId = 0;
            $lastError = null;

            while ($attempt < $maxAttempts && $insertId <= 0) {
                $attempt++;
                if ($attempt > 1 && in_array('quote_number', $tableCols, true) && empty($data['quote_number'])) {
                    $data['quote_number'] = $this->generateQuoteNumber($db);
                }

            // Build insert row only for columns that exist on the live table to avoid SQL errors
            $insertRow = [];
            if (in_array('customer_id', $tableCols)) $insertRow['customer_id'] = $data['customer_id'] ?? null;
            if (in_array('issue_date', $tableCols)) $insertRow['issue_date'] = !empty($data['issue_date']) ? $data['issue_date'] : date('Y-m-d');
            if (in_array('quote_number', $tableCols) && !empty($data['quote_number'])) $insertRow['quote_number'] = $data['quote_number'];
            if (in_array('status', $tableCols)) $insertRow['status'] = $data['status'] ?? 'draft';
            if (in_array('price_list_id', $tableCols) && isset($data['price_list_id'])) $insertRow['price_list_id'] = $data['price_list_id'];
            if (in_array('subtotal', $tableCols)) $insertRow['subtotal'] = $data['subtotal'] ?? 0;
            if (in_array('discount', $tableCols)) $insertRow['discount'] = $data['discount'] ?? ($data['document_discount_value'] ?? 0);
            if (in_array('tax', $tableCols)) $insertRow['tax'] = $data['tax'] ?? ($data['tax_total'] ?? 0);
            if (in_array('tax_total', $tableCols)) $insertRow['tax_total'] = $data['tax'] ?? ($data['tax_total'] ?? 0);
            if (in_array('total', $tableCols)) $insertRow['total'] = $data['total'] ?? 0;
            if (in_array('total_weight', $tableCols)) $insertRow['total_weight'] = $data['total_weight'] ?? 0;
            if (in_array('shipping_amount', $tableCols)) $insertRow['shipping_amount'] = $data['shipping_amount'] ?? 0;
            if (in_array('document_discount_type', $tableCols)) $insertRow['document_discount_type'] = $documentDiscountType;
            if (in_array('document_discount_value', $tableCols)) $insertRow['document_discount_value'] = round($documentDiscountValue, 2);
            if (in_array('discount_exclude_shipping', $tableCols)) $insertRow['discount_exclude_shipping'] = $discountExcludeShipping ? 1 : 0;
            if (in_array('created_by', $tableCols)) $insertRow['created_by'] = $data['created_by'] ?? (session()->get('user_id') ?? null);
            if (in_array('currency', $tableCols) && isset($data['currency'])) $insertRow['currency'] = $data['currency'];
            if (in_array('quote_currency', $tableCols) && isset($data['quote_currency'])) $insertRow['quote_currency'] = $data['quote_currency'];
            if (in_array('base_currency', $tableCols) && isset($data['base_currency'])) $insertRow['base_currency'] = $data['base_currency'];
            $insertRow['created_at'] = date('Y-m-d H:i:s');

                try {
                    $db->table($this->table)->insert($insertRow);
                    $insertId = (int)$db->insertID();
                    $lastError = null;
                } catch (\Throwable $e) {
                    $lastError = $e;
                    $msg = strtolower($e->getMessage());
                    $isDuplicate = str_contains($msg, 'duplicate') || str_contains($msg, '1062');
                    if ($isDuplicate) {
                        // Force regeneration on retry.
                        $data['quote_number'] = null;
                        continue;
                    }
                    throw $e;
                }
            }

            if ($insertId <= 0 && $lastError) {
                throw $lastError;
            }
        } catch (\Throwable $e) {
            $db->transComplete();
            return false;
        }
        if (!$insertId) {
            $db->transComplete();
            return false;
        }


        // Insert lines - only insert allowed fields to avoid schema mismatches
        $lineModel = new QuotationLineModel();
        $order = 0;
        // Determine live columns for lines table to avoid SQL errors
        try {
            $lineCols = $db->getFieldNames($lineModel->table);
        } catch (\Throwable $_) {
            $lineCols = is_array($lineModel->allowedFields) ? $lineModel->allowedFields : [];
        }

        foreach ($calc['lines'] as $ln) {
            if (!is_array($ln)) continue;
            $ln['quotation_id'] = $insertId;
            $ln['sort_order'] = $order++;
            // ensure numeric typing
            if (isset($ln['quantity'])) $ln['quantity'] = (float)$ln['quantity'];
            if (isset($ln['unit_price'])) $ln['unit_price'] = (float)$ln['unit_price'];
            if (isset($ln['discount_value'])) $ln['discount_value'] = (float)$ln['discount_value'];
            if (isset($ln['tax_rate'])) $ln['tax_rate'] = (float)$ln['tax_rate'];

            // Filter to live line columns
            $insertLn = array_intersect_key($ln, array_flip($lineCols));
            $ok = $lineModel->insert($insertLn);
            if ($ok === false) {
                $db->transComplete();
                return false;
            }
        }

        $db->transComplete();
        if ($db->transStatus() === false) {
            return false;
        }

        return $insertId;
    }

    /**
     * Calculate totals from provided lines. Returns array with subtotal, discount, tax, total and normalized lines.
     */
    public function calculateTotals(
        array $lines,
        float $shippingAmount = 0.0,
        string $documentDiscountType = 'fixed',
        float $documentDiscountValue = 0.0,
        bool $discountExcludeShipping = true
    ): array
    {
        // Collect product ids and variant ids to resolve weights from product master and variant.
        $productIds = [];
        $variantIds = [];
        $lineCodes = [];
        foreach ($lines as $line) {
            if (is_array($line) && !empty($line['product_id'])) {
                $productIds[] = (int)$line['product_id'];
            }
            if (is_array($line) && !empty($line['product_variant_id'])) {
                $variantIds[] = (int)$line['product_variant_id'];
            }
            if (is_array($line) && !empty($line['product_code'])) {
                $lineCodes[] = strtoupper(trim((string)$line['product_code']));
            }
        }
        $productWeights = [];
        $productWeightUnits = [];
        if (!empty($productIds)) {
            $prodModel = new \App\Models\ProductModel();
            $rows = $prodModel->select('id, weight, unit_weight, weight_unit')->whereIn('id', array_unique($productIds))->findAll();
            foreach ($rows as $r) {
                $pid = (int)$r['id'];
                $productWeights[$pid] = isset($r['unit_weight']) && (float)$r['unit_weight'] > 0
                    ? (float)$r['unit_weight']
                    : (isset($r['weight']) ? (float)$r['weight'] : 0.0);
                $productWeightUnits[$pid] = $r['weight_unit'] ?? 'kg';
            }
        }

        $variantWeights = [];
        $variantWeightByProductCode = [];
        $variantIds = array_values(array_unique($variantIds));
        $lineCodes = array_values(array_unique(array_filter($lineCodes)));
        if (!empty($variantIds) || !empty($lineCodes)) {
            $db = \Config\Database::connect();
            try {
                $builder = $db->table('product_variants')->select('id, product_id, art_number, weight');
                if (!empty($variantIds) && !empty($lineCodes)) {
                    $builder->groupStart()
                        ->whereIn('id', $variantIds)
                        ->orWhereIn('art_number', $lineCodes)
                        ->groupEnd();
                } elseif (!empty($variantIds)) {
                    $builder->whereIn('id', $variantIds);
                } else {
                    $builder->whereIn('art_number', $lineCodes);
                }
                $rows = $builder->get()->getResultArray();
                foreach ($rows as $r) {
                    $vid = (int)$r['id'];
                    $variantWeights[$vid] = isset($r['weight']) ? (float)$r['weight'] : 0.0;
                    $pcode = trim((string)($r['art_number'] ?? ''));
                    if ($pcode !== '') {
                        $variantWeightByProductCode[strtoupper($pcode)] = $variantWeights[$vid];
                    }
                }
            } catch (\Throwable $_) {
                $variantWeights = [];
                $variantWeightByProductCode = [];
            }
        }

        $subtotal = 0.0;
        $discountTotal = 0.0;
        $taxTotal = 0.0;
        $totalWeight = 0.0;
        $norm = [];

        foreach ($lines as $line) {
            // defensive: if line is not array, skip
            if (!is_array($line)) continue;

            $qty = isset($line['quantity']) ? (float)$line['quantity'] : 0.0;
            $unit = isset($line['unit_price']) ? (float)$line['unit_price'] : 0.0;

            // Discount: prefer explicit discount_type + discount_value coming from the form
            $discountType = $line['discount_type'] ?? ($line['document_discount_type'] ?? 'percent');
            $discountValue = isset($line['discount_value']) ? (float)$line['discount_value'] : (isset($line['document_discount_value']) ? (float)$line['document_discount_value'] : 0.0);
            if ($discountType === 'percent') {
                $discountAmount = ($qty * $unit) * ($discountValue / 100.0);
            } else {
                $discountAmount = $discountValue;
            }

            // Tax can be entered as either percent or fixed value.
            $taxType = strtolower((string)($line['tax_type'] ?? 'percent'));
            if (!in_array($taxType, ['percent', 'fixed'], true)) {
                $taxType = 'percent';
            }
            $taxValue = isset($line['tax_value'])
                ? (float)$line['tax_value']
                : (isset($line['tax_rate']) ? (float)$line['tax_rate'] : (isset($line['tax']) ? (float)$line['tax'] : 0.0));
            $raw = $qty * $unit;
            $taxable = max(0, $raw - $discountAmount);
            $taxAmount = $taxType === 'fixed' ? $taxValue : ($taxable * ($taxValue / 100.0));
            $lineTotal = $taxable + $taxAmount;

            $subtotal += $raw;
            $discountTotal += $discountAmount;
            $taxTotal += $taxAmount;

            // Resolve unit weight with precedence: line > variant > product.
            $pid = !empty($line['product_id']) ? (int)$line['product_id'] : 0;
            $vid = !empty($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;
            $lineCode = strtoupper(trim((string)($line['product_code'] ?? '')));
            $lineWeight = isset($line['unit_weight']) && (float)$line['unit_weight'] > 0
                ? (float)$line['unit_weight']
                : (isset($line['weight']) && (float)$line['weight'] > 0 ? (float)$line['weight'] : 0.0);
            $variantWeight = $vid > 0 ? (float)($variantWeights[$vid] ?? 0.0) : 0.0;
            if ($variantWeight <= 0 && $lineCode !== '' && isset($variantWeightByProductCode[$lineCode])) {
                $variantWeight = (float)$variantWeightByProductCode[$lineCode];
            }
            $productWeight = $pid > 0 ? (float)($productWeights[$pid] ?? 0.0) : 0.0;
            $unitWeightRaw = $lineWeight > 0 ? $lineWeight : ($variantWeight > 0 ? $variantWeight : $productWeight);

            $weightUnit = $line['weight_unit'] ?? ($pid > 0 ? ($productWeightUnits[$pid] ?? 'kg') : 'kg');
            $unitWeightKg = WeightHelper::toKilograms((float)$unitWeightRaw, (string)$weightUnit);
            $totalWeight += $unitWeightKg * $qty;

            // defensive: ensure we merge arrays only
            $baseLine = is_array($line) ? $line : [];
            $merged = array_merge($baseLine, [
                'quantity' => $qty,
                'unit_price' => $unit,
                'discount_type' => $discountType,
                'discount_value' => $discountValue,
                'discount_amount' => round($discountAmount, 2),
                'tax_type' => $taxType,
                'tax_value' => $taxValue,
                'tax_rate' => $taxValue,
                'tax_amount' => round($taxAmount, 2),
                'line_total' => round($lineTotal, 2),
                'unit_weight' => round((float)$unitWeightRaw, 4),
                'weight_unit' => strtoupper((string)$weightUnit),
            ]);

            // Normalize keys to primitive types to avoid storing objects
            array_walk($merged, function (&$v) { if (is_numeric($v)) $v = 0+$v; });

            $norm[] = $merged;
        }

    $documentDiscountType = strtolower($documentDiscountType);
    if (!in_array($documentDiscountType, ['percent', 'fixed'], true)) {
        $documentDiscountType = 'fixed';
    }
    $documentDiscountValue = max(0.0, (float)$documentDiscountValue);

    $lineNet = max(0.0, $subtotal - $discountTotal);
    $documentBase = $lineNet + $taxTotal + ($discountExcludeShipping ? 0.0 : $shippingAmount);
    $documentDiscountAmount = 0.0;
    if ($documentDiscountValue > 0) {
        if ($documentDiscountType === 'percent') {
            $documentDiscountAmount = $documentBase * ($documentDiscountValue / 100.0);
        } else {
            $documentDiscountAmount = $documentDiscountValue;
        }
    }
    $documentDiscountAmount = min(max(0.0, $documentDiscountAmount), $documentBase);

    $total = round($lineNet + $taxTotal + $shippingAmount - $documentDiscountAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'line_discount_total' => round($discountTotal, 2),
            'document_discount_type' => $documentDiscountType,
            'document_discount_value' => round($documentDiscountValue, 2),
            'discount_exclude_shipping' => $discountExcludeShipping ? 1 : 0,
            'document_discount_amount' => round($documentDiscountAmount, 2),
            'discount' => round($discountTotal + $documentDiscountAmount, 2),
            'tax' => round($taxTotal, 2),
            'shipping_amount' => round($shippingAmount, 2),
            'total_weight' => round($totalWeight, 3),
            'total' => $total,
            'lines' => $norm,
        ];
    }
}

