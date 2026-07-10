<?php
namespace App\Controllers;

use App\Models\SalesOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\QuotationModel;
use App\Models\ProductModel;
use App\Models\ProductVariantModel;
use App\Models\CustomerModel;
use App\Models\CustomerAddressModel;
use App\Services\DualInvoiceService;
use App\Models\CustomerInvoiceModel;
use App\Models\CompanySettingsModel;
use App\Models\Accounting\CurrencyModel;
use App\Models\FeatureFlagModel;
use App\Libraries\DocumentLogger;
use App\Libraries\InvoicePdfGenerator;
use App\Libraries\RoleDataAccess;

class SalesOrders extends BaseController
{
    protected $model;
    protected $lineModel;
    protected $quotationModel;
    protected $customerModel;
    protected $invoiceService;
    protected $invoiceModel;

    /**
     * Accepts DD-MM-YYYY or YYYY-MM-DD and returns YYYY-MM-DD
     */
    private function normalizeDate(?string $val): string
    {
        $val = trim((string)$val);
        if ($val === '') return date('Y-m-d');
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $val;
    }

    /**
     * Resolve default currency for sales orders.
     */
    private function getDefaultSalesCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_sales_currency'])) return $company['default_sales_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
            if (!empty($company['secondary_currency'])) return $company['secondary_currency'];
        } catch (\Throwable $_) {
            // fall through
        }
        return 'USD';
    }

    public function __construct()
    {
        $this->model = new SalesOrderModel();
        $this->lineModel = new SalesOrderLineModel();
        $this->quotationModel = new QuotationModel();
        $this->customerModel = new CustomerModel();
        $this->invoiceService = new DualInvoiceService();
        $this->invoiceModel = new CustomerInvoiceModel();
        helper(['form','url']);
    }

    public function index()
    {
        // Unified list UI
        return redirect()->to(site_url('documents'));
    }

    public function view($id)
    {
        $identifier = (string) $id;
        $order = null;

        if (ctype_digit($identifier)) {
            $order = $this->model->find((int) $identifier);
        }

        if (! $order) {
            try {
                $db = \Config\Database::connect();
                if ($db->fieldExists('public_id', 'sales_orders')) {
                    $order = $this->model->where('public_id', $identifier)->first();
                }
            } catch (\Throwable $_) {
                // best-effort public_id lookup
            }
        }

        if (!$order) return redirect()->to('/sales-orders')->with('error','Order not found');

        // Canonical URL: if public IDs are enabled, always expose public_id in URL.
        if (
            FeatureFlagModel::isEnabled(FeatureFlagModel::FLAG_ENABLE_PUBLIC_IDS)
            && !empty($order['public_id'])
            && $identifier !== (string) $order['public_id']
        ) {
            return redirect()->to('/sales-orders/view/' . $order['public_id']);
        }

        $orderId = (int) ($order['id'] ?? 0);

        try {
            $db = \Config\Database::connect();
            $userId = (int) (session()->get('user_id') ?? 0);
            $isAdmin = service('policy')->isAdmin();
            $rda = new RoleDataAccess();
            $access = $rda->resolveForUser($userId);
            $isolate = !empty($access['isolate_sales_orders']);
            if ($isolate && $db->fieldExists('created_by', 'sales_orders')) {
                $ownerId = (int) ($order['created_by'] ?? 0);
                if ($ownerId > 0 && $ownerId !== $userId) {
                    return redirect()->to('/documents')->with('error', 'You are not allowed to view this sales order.');
                }
            }
            // Privacy check: block access if the owner has hidden their documents
            if (!$isAdmin && $db->fieldExists('created_by', 'sales_orders')) {
                $ownerId = (int) ($order['created_by'] ?? 0);
                if ($ownerId > 0 && $ownerId !== $userId) {
                    $privateIds = $rda->getPrivateUserIds($userId, false);
                    if (in_array($ownerId, $privateIds, true)) {
                        return redirect()->to('/documents')->with('error', 'This sales order is not available.');
                    }
                }
            }
        } catch (\Throwable $_) {
            // best effort only
        }

        // Fetch quotation for fallback display (e.g., shipping) if needed
        $quote = null;
        $quoteWithLines = null;
        if (!empty($order['quotation_id'])) {
            try {
                $quoteWithLines = $this->quotationModel->getWithLines((int)$order['quotation_id']);
                $quote = $quoteWithLines ?? $this->quotationModel->find((int)$order['quotation_id']);
            } catch (\Throwable $_) {
                try { $quote = $this->quotationModel->find((int)$order['quotation_id']); } catch (\Throwable $__ignore) { $quote = null; }
            }
        }

        $data['order'] = $order;
        $data['quote'] = $quote;
        // If the order already has an invoice_id column/value, fetch the invoice for button logic
        $mysqlInvoice = null;
        $db = null;
        $soCols = [];
        $ciCols = [];
        try {
            $db = \Config\Database::connect();
            $soCols = $db->getFieldNames('sales_orders');
            $ciCols = $db->getFieldNames('customer_invoices');
        } catch (\Throwable $_) {
            // best-effort: ignore schema discovery failures
        }

        if (!empty($soCols) && in_array('invoice_id', $soCols) && !empty($order['invoice_id'])) {
            $mysqlInvoice = $this->invoiceModel->find((int)$order['invoice_id']);
        }

        if (empty($mysqlInvoice) && in_array('sales_order_id', $ciCols)) {
            $mysqlInvoice = $this->invoiceModel
                ->where('sales_order_id', $order['id'])
                ->orderBy('id', 'DESC')
                ->first();
            if (!empty($mysqlInvoice) && !empty($soCols) && in_array('invoice_id', $soCols) && empty($order['invoice_id']) && $db !== null) {
                try {
                    $db->table('sales_orders')->where('id', $order['id'])->update(['invoice_id' => $mysqlInvoice['id']]);
                    $order['invoice_id'] = $mysqlInvoice['id'];
                } catch (\Throwable $_) {
                    // best-effort link maintenance
                }
            }
        }

        // Fallback: on some installs there is no link column (no sales_orders.invoice_id and no customer_invoices.sales_order_id).
        // In that case, detect by the deterministic invoice number we generate: INV-<SO_NUMBER>.
        if (empty($mysqlInvoice) && $db !== null) {
            try {
                $soNumber = $order['order_number'] ?? ('SO-' . ($order['id'] ?? ''));
                $invNo = 'INV-' . $soNumber;
                $row = $db->table('customer_invoices')->where('invoice_number', $invNo)->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
                if (!empty($row)) {
                    $mysqlInvoice = $row;
                }
            } catch (\Throwable $_) {
                // ignore
            }
        }

        $data['invoice'] = $mysqlInvoice ?: null;

        // Load customer for display (name + contact + address)
        $customer = null;
        try { $customer = $this->customerModel->find((int)($order['customer_id'] ?? 0)); } catch (\Throwable $_) { $customer = null; }
        $address = null;
        try {
            $addrModel = new CustomerAddressModel();
            $addr = $addrModel->where('customer_id', (int)($order['customer_id'] ?? 0))
                ->orderBy('is_billing', 'DESC')
                ->orderBy('is_shipping', 'DESC')
                ->orderBy('id', 'ASC')
                ->first();
            $address = $addr ?: null;
            // Load country name if country_id exists
            if (!empty($address) && !empty($address['country_id'])) {
                try {
                    $countryModel = new \App\Models\CountryModel();
                    $country = $countryModel->find((int)$address['country_id']);
                    if (!empty($country)) {
                        $address['country_name'] = $country['name'] ?? null;
                    }
                } catch (\Throwable $_) {
                    // Country fetch failed, continue without it
                }
            }
        } catch (\Throwable $_) { $address = null; }
        $data['customer'] = $customer;
        $data['customerAddress'] = $address;

        // Prefer quotation lines when available (carry discount/tax/product metadata)
        $lines = $quoteWithLines['lines'] ?? $this->lineModel->where('sales_order_id', $orderId)->findAll();

        // Enrich lines with product metadata and calculated discount/tax similar to quotation view
        try {
            $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($lines, 'product_id')))));
            $variantIds = array_values(array_unique(array_filter(array_map('intval', array_map(function ($ln) {
                return $ln['product_variant_id'] ?? ($ln['variant_id'] ?? 0);
            }, $lines)))));
            $lineCodes = array_values(array_unique(array_filter(array_map(static function ($ln) {
                $code = trim((string)($ln['product_code'] ?? ''));
                return $code !== '' ? $code : null;
            }, $lines))));
            $productMap = [];
            $variantMap = [];
            $variantMapByArt = [];

            $resolveWeight = static function (...$candidates): float {
                foreach ($candidates as $candidate) {
                    if ($candidate === null || $candidate === '') {
                        continue;
                    }
                    $val = (float)$candidate;
                    if ($val > 0) {
                        return $val;
                    }
                }
                return 0.0;
            };

            $resolveWeightUnit = static function (...$candidates): string {
                $aliases = [
                    'kg' => 'kg', 'kgs' => 'kg', 'kilogram' => 'kg', 'kilograms' => 'kg',
                    'g' => 'g', 'gm' => 'g', 'gms' => 'g', 'gram' => 'g', 'grams' => 'g',
                    'mg' => 'mg', 'milligram' => 'mg', 'milligrams' => 'mg',
                    'lb' => 'lb', 'lbs' => 'lb', 'pound' => 'lb', 'pounds' => 'lb',
                    'oz' => 'oz', 'ounce' => 'oz', 'ounces' => 'oz',
                ];
                foreach ($candidates as $candidate) {
                    $unit = strtolower(trim((string)$candidate));
                    if ($unit === '') {
                        continue;
                    }
                    if (isset($aliases[$unit])) {
                        return $aliases[$unit];
                    }
                }
                return 'kg';
            };

            if (!empty($variantIds) || !empty($lineCodes)) {
                $pvm = new ProductVariantModel();
                $variantBuilder = $pvm->builder()->select('id, product_id, art_number, name, image, weight');
                if (!empty($variantIds) && !empty($lineCodes)) {
                    $variantBuilder->groupStart()->whereIn('id', $variantIds)->orWhereIn('art_number', $lineCodes)->groupEnd();
                } elseif (!empty($variantIds)) {
                    $variantBuilder->whereIn('id', $variantIds);
                } else {
                    $variantBuilder->whereIn('art_number', $lineCodes);
                }
                $variants = $variantBuilder->get()->getResultArray();
                foreach ($variants as $v) {
                    $variantImage = trim((string)($v['image'] ?? ''));
                    $variantImageUrl = '';
                    if ($variantImage !== '') {
                        $variantImageUrl = preg_match('#^(https?:)?//#i', $variantImage)
                            ? $variantImage
                            : base_url('/uploads/variants/' . ltrim($variantImage, '/'));
                    }
                    $variantMap[(int)$v['id']] = [
                        'product_id' => $v['product_id'] ?? null,
                        'name' => $v['name'] ?? '',
                        'weight' => $v['weight'] ?? 0,
                        'art_number' => $v['art_number'] ?? '',
                        'image_url' => $variantImageUrl,
                    ];
                    $art = strtoupper(trim((string)($v['art_number'] ?? '')));
                    if ($art !== '') {
                        $variantMapByArt[$art] = $variantMap[(int)$v['id']];
                    }
                }
            }

            if (!empty($productIds)) {
                $pm = new ProductModel();
                $products = $pm->whereIn('id', $productIds)->findAll();
                foreach ($products as $p) {
                    $img = base_url('assets/images/no-image.png');
                    if (!empty($p['image'])) {
                        $img = base_url('/uploads/products/' . ltrim($p['image'], '/'));
                    } elseif (!empty($p['images'])) {
                        $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($imgs) && !empty($imgs[0])) {
                            $img = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                        }
                    }
                    $productMap[(int)$p['id']] = [
                        'code' => $p['code'] ?? ($p['sku'] ?? ''),
                        'sku' => $p['sku'] ?? null,
                        'public_id' => $p['public_id'] ?? null,
                        'name' => $p['name'] ?? null,
                        'unit' => $p['unit'] ?? null,
                        'image_url' => $img,
                        'weight_unit' => $resolveWeightUnit($p['weight_unit'] ?? null, 'kg'),
                        'unit_weight' => $resolveWeight(
                            $p['unit_weight'] ?? null,
                            $p['weight'] ?? null,
                            $p['weight_net'] ?? null,
                            $p['weight_gross'] ?? null
                        ),
                    ];
                }
            }

            if (!empty($variantMap)) {
                $pm = isset($pm) ? $pm : new ProductModel();
                $missingProductIds = [];
                foreach ($variantMap as $variantRow) {
                    $variantProductId = isset($variantRow['product_id']) ? (int)$variantRow['product_id'] : 0;
                    if ($variantProductId > 0 && !isset($productMap[$variantProductId])) {
                        $missingProductIds[] = $variantProductId;
                    }
                }
                $missingProductIds = array_values(array_unique($missingProductIds));
                if (!empty($missingProductIds)) {
                    $extraProducts = $pm->whereIn('id', $missingProductIds)->findAll();
                    foreach ($extraProducts as $p) {
                        $img = base_url('assets/images/no-image.png');
                        if (!empty($p['image'])) {
                            $img = base_url('/uploads/products/' . ltrim($p['image'], '/'));
                        } elseif (!empty($p['images'])) {
                            $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                            if (is_array($imgs) && !empty($imgs[0])) {
                                $img = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                            }
                        }
                        $productMap[(int)$p['id']] = [
                            'code' => $p['code'] ?? ($p['sku'] ?? ''),
                            'sku' => $p['sku'] ?? null,
                            'public_id' => $p['public_id'] ?? null,
                            'name' => $p['name'] ?? null,
                            'unit' => $p['unit'] ?? null,
                            'image_url' => $img,
                            'weight_unit' => $resolveWeightUnit($p['weight_unit'] ?? null, 'kg'),
                            'unit_weight' => $resolveWeight(
                                $p['unit_weight'] ?? null,
                                $p['weight'] ?? null,
                                $p['weight_net'] ?? null,
                                $p['weight_gross'] ?? null
                            ),
                        ];
                    }
                }
            }

            foreach ($lines as &$ln) {
                $originalMissingRefs = ((int)($ln['product_id'] ?? 0) <= 0 && (int)($ln['product_variant_id'] ?? 0) <= 0);
                $prod = null;
                $variantId = (int)($ln['product_variant_id'] ?? ($ln['variant_id'] ?? 0));
                $lineCode = strtoupper(trim((string)($ln['product_code'] ?? '')));
                if ($variantId <= 0 && $lineCode !== '' && isset($variantMapByArt[$lineCode])) {
                    foreach ($variantMap as $candidateVariantId => $candidateVariant) {
                        if (strtoupper(trim((string)($candidateVariant['art_number'] ?? ''))) === $lineCode) {
                            $variantId = (int)$candidateVariantId;
                            $ln['product_variant_id'] = $variantId;
                            break;
                        }
                    }
                }
                if (empty($ln['product_id']) && $variantId > 0 && isset($variantMap[$variantId])) {
                    $variantProductId = (int)($variantMap[$variantId]['product_id'] ?? 0);
                    if ($variantProductId > 0) {
                        $ln['product_id'] = $variantProductId;
                    }
                }
                if (!empty($ln['product_id'])) {
                    $prod = $productMap[(int)$ln['product_id']] ?? null;
                }
                $variant = $variantId > 0 ? ($variantMap[$variantId] ?? null) : null;
                $variantCode = trim((string)($variant['art_number'] ?? ''));
                $currentCode = trim((string)($ln['product_code'] ?? ''));
                if ($currentCode === '' && $variantCode !== '') {
                    $ln['product_code'] = $variantCode;
                } else {
                    $ln['product_code'] = $ln['product_code'] ?? ($prod['code'] ?? ($ln['product_id'] ?? ''));
                }
                $ln['product_name'] = $ln['product_name'] ?? ($prod['name'] ?? '');
                if (!empty($variant['name'])) {
                    $ln['variant_name'] = $variant['name'];
                }
                $lineImageUrl = trim((string)($ln['product_image_url'] ?? ''));
                $lineIsDefault = ($lineImageUrl !== '' && stripos($lineImageUrl, 'assets/images/no-image.png') !== false);
                $variantImageUrl = trim((string)($variant['image_url'] ?? ''));
                if ($variantImageUrl !== '' && ($lineImageUrl === '' || $lineIsDefault)) {
                    $ln['product_image_url'] = $variantImageUrl;
                } else {
                    $ln['product_image_url'] = $ln['product_image_url'] ?? ($prod['image_url'] ?? base_url('assets/images/no-image.png'));
                }
                $ln['unit'] = $ln['unit'] ?? ($prod['unit'] ?? 'pcs');
                $ln['product_identifier'] = $prod['public_id'] ?? ($ln['product_id'] ?? null);
                $lineProvidedWeight = $resolveWeight(
                    $ln['unit_weight'] ?? null,
                    $ln['weight'] ?? null
                );
                $ln['unit_weight'] = $resolveWeight(
                    $ln['unit_weight'] ?? null,
                    $ln['weight'] ?? null,
                    $variant['weight'] ?? null,
                    $prod['unit_weight'] ?? null
                );
                // If line-level weight is missing/zero (legacy default rows), trust product's weight unit first.
                $ln['weight_unit'] = $lineProvidedWeight > 0
                    ? $resolveWeightUnit($ln['weight_unit'] ?? null, $prod['weight_unit'] ?? null, 'kg')
                    : $resolveWeightUnit($prod['weight_unit'] ?? null, $ln['weight_unit'] ?? null, 'kg');

                $lineDesc = trim((string)($ln['description'] ?? ''));
                $lineProductName = trim((string)($ln['product_name'] ?? ''));
                $lineVariantName = trim((string)($ln['variant_name'] ?? ''));
                $looksGenericDesc = ($lineDesc !== '' && strpos($lineDesc, '/') === false && $lineVariantName !== '' && strcasecmp($lineDesc, $lineVariantName) !== 0);
                if ($lineVariantName !== '' && ($lineDesc === '' || strcasecmp($lineDesc, $lineProductName) === 0 || $looksGenericDesc)) {
                    $ln['description'] = $lineVariantName;
                }

                if ($originalMissingRefs) {
                    $lineId = (int)($ln['id'] ?? 0);
                    if (!empty($ln['product_code']) || !empty($ln['variant_name']) || !empty($ln['product_image_url'])) {
                        log_message('warning', 'SalesOrders::view recovered missing product references for sales_order_line_id=' . $lineId . ' sales_order_id=' . $orderId . ' using variant/code fallback');
                    } else {
                        log_message('warning', 'SalesOrders::view unresolved product references for sales_order_line_id=' . $lineId . ' sales_order_id=' . $orderId . ' (missing product_id/product_variant_id)');
                    }
                }

                $qty = isset($ln['quantity']) ? (float)$ln['quantity'] : 0.0;
                $unit = isset($ln['unit_price']) ? (float)$ln['unit_price'] : 0.0;
                $discountType = $ln['discount_type'] ?? 'percent';
                $discountVal = isset($ln['discount_value']) ? (float)$ln['discount_value'] : 0.0;
                $discAmt = ($discountType === 'percent') ? ($qty * $unit) * ($discountVal / 100.0) : $discountVal;
                $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
                $raw = $qty * $unit;
                $taxable = max(0, $raw - $discAmt);
                $taxAmt = $taxable * ($taxRate / 100.0);
                $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : ($taxable + $taxAmt);

                $ln['base_amount'] = $raw;
                $ln['discount_amount'] = $discAmt;
                $ln['tax_amount'] = $taxAmt;
                $ln['line_total'] = $lineTotal;
                $ln['discount_value'] = $discountVal;
                $ln['tax_rate'] = $taxRate;
            }
            unset($ln);
        } catch (\Throwable $_) {
            // best-effort enrichment
        }

        $data['lines'] = $lines;

        // Calculate display totals from lines (aligns subtotal/tax with shown values)
        $displaySubtotal = 0.0;
        $displayTax = 0.0;
        $displayLineTotal = 0.0;
        $displayDiscount = 0.0;
        foreach ($lines as $ln) {
            $displaySubtotal += isset($ln['base_amount']) ? (float)$ln['base_amount'] : ((float)($ln['quantity'] ?? 0) * (float)($ln['unit_price'] ?? 0));
            $displayDiscount += (float)($ln['discount_amount'] ?? 0);
            $displayTax += (float)($ln['tax_amount'] ?? 0);
            $displayLineTotal += (float)($ln['line_total'] ?? 0);
        }

        // Resolve shipping for display: prefer order column, fall back to quote, otherwise infer from totals
        $orderShip = isset($order['shipping_amount']) ? (float)$order['shipping_amount'] : (isset($order['shipping_cost']) ? (float)$order['shipping_cost'] : null);
        $quoteShip = isset($quote['shipping_amount']) ? (float)$quote['shipping_amount'] : null;
        $shippingResolved = null;
        if ($orderShip !== null && $orderShip > 0) {
            $shippingResolved = $orderShip;
        } elseif ($quoteShip !== null && $quoteShip > 0) {
            $shippingResolved = $quoteShip;
        } elseif ($orderShip !== null) {
            $shippingResolved = $orderShip; // zero/negative explicit value
        } elseif ($quoteShip !== null) {
            $shippingResolved = $quoteShip;
        }
        if ($shippingResolved === null && isset($order['total'], $order['subtotal'], $order['tax_total'])) {
            $shippingResolved = max(0, (float)$order['total'] - (float)$order['subtotal'] - (float)$order['tax_total']);
        }
        if ($shippingResolved === null) $shippingResolved = 0.0;

        // Display totals prefer calculated values when present
        $data['displaySubtotal'] = round($displaySubtotal, 2);
        $data['displayDiscount'] = round($displayDiscount, 2);
        $data['displayTax'] = round($displayTax, 2);
        $data['displayTotal'] = round(($displaySubtotal - $displayDiscount + $displayTax + $shippingResolved), 2);
        $data['shippingResolved'] = $shippingResolved;

        // Currency for display: prefer order currency, then quotation, then default sales currency
        $data['currencyCode'] = $order['currency'] ?? ($quote['currency'] ?? $this->getDefaultSalesCurrency());

        // Build readiness snapshot once and reuse it for both stock-shortage and ready-now columns.
        $readyData = ['readyToShip' => false, 'lines' => []];
        $readyMap = [];
        $orderStatus = strtolower(trim((string)($order['status'] ?? '')));
        $isFinalizedFulfillment = in_array($orderStatus, ['shipped', 'delivered', 'completed', 'closed'], true);
        try {
            $readyService = new \App\Services\ReadyToShipService();
            $readyData = $readyService->getLineReadiness((int)$id);
            foreach (($readyData['lines'] ?? []) as $rl) {
                $lineId = (int)($rl['sales_order_line_id'] ?? 0);
                if ($lineId > 0) {
                    $readyMap[$lineId] = $rl;
                }
            }
        } catch (\Throwable $_) {
            $readyData = ['readyToShip' => false, 'lines' => []];
            $readyMap = [];
        }
        
        // Phase-1: Enrich lines with inventory availability data
        try {
            $inventoryService = new \App\Services\InventoryAvailabilityService();
            $hasShortage = false;
            $shortageCount = 0;
            
            foreach ($data['lines'] as &$line) {
                $productId = $line['product_id'] ?? null;
                $variantId = $line['product_variant_id'] ?? null;
                $orderedQty = (float)($line['quantity'] ?? 0);
                $lineId = (int)($line['id'] ?? 0);
                
                // Get product info to determine if stockable
                $product = null;
                if ($productId && isset($productMap[(int)$productId])) {
                    $product = $productMap[(int)$productId];
                }
                
                // Assume stockable by default (for products without explicit type)
                $isStockable = true;
                
                if ($productId) {
                    try {
                        $pm = new ProductModel();
                        $fullProduct = $pm->find($productId);
                        if ($fullProduct) {
                            $detailedType = strtolower((string)($fullProduct['detailed_type'] ?? ''));
                            $line['detailed_type'] = $fullProduct['detailed_type'] ?? null;
                            $line['product_type'] = $fullProduct['product_type'] ?? null;

                            if ($detailedType === 'service') {
                                $isStockable = false;
                            } elseif (in_array($detailedType, ['storable', 'consumable'], true)) {
                                $isStockable = true;
                            } else {
                                $isStockable = true; // default when not specified
                            }
                        }
                    } catch (\Throwable $_) {
                        // default to stockable if can't determine
                    }
                }
                
                $line['is_stockable'] = $isStockable;

                if ($isFinalizedFulfillment) {
                    // Finalized SOs should not display stock-shortage signals anymore.
                    $line['required_qty'] = 0;
                    $line['shipped_qty'] = max((float)($line['shipped_qty'] ?? 0), $orderedQty);
                    $line['shortage'] = null;
                    continue;
                }
                
                if ($isStockable && $productId) {
                    $pType = isset($fullProduct) ? strtolower((string)($fullProduct['product_type'] ?? '')) : '';
                    $availability = $inventoryService->getAvailability($productId, $variantId, $pType);
                    $line['on_hand'] = $availability['on_hand'] ?? 0;
                    $line['reserved'] = $availability['reserved'] ?? 0;
                    $line['available'] = $availability['available'] ?? 0;
                    $shippedQty = (float)($readyMap[$lineId]['shipped_qty'] ?? 0);
                    $remainingQty = (float)($readyMap[$lineId]['remaining_qty'] ?? $orderedQty);
                    $remainingQty = max(0, $remainingQty);
                    $line['required_qty'] = $remainingQty;
                    $line['shipped_qty'] = $shippedQty;
                    $line['shortage'] = max(0, $remainingQty - ($availability['available'] ?? 0));
                    
                    if ($remainingQty > 0 && $line['shortage'] > 0) {
                        $hasShortage = true;
                        $shortageCount++;
                    }
                } else {
                    // Non-stockable products
                    $line['on_hand'] = null;
                    $line['reserved'] = null;
                    $line['available'] = null;
                    $line['shortage'] = null;
                    $line['required_qty'] = 0;
                    $line['shipped_qty'] = (float)($readyMap[$lineId]['shipped_qty'] ?? 0);
                }
            }
            unset($line);

            if ($isFinalizedFulfillment) {
                $hasShortage = false;
                $shortageCount = 0;
            }
            
            $data['hasShortage'] = $hasShortage;
            $data['shortageCount'] = $shortageCount;
            
            // Check if RFQ drafts have already been created for this order
            $hasAutoRfq = false;
            try {
                $rfqModel = new \App\Models\PurchaseRfqModel();
                $soNumber = $order['order_number'] ?? null;
                $soId = $order['id'] ?? null;
                
                if ($soId || $soNumber) {
                    $query = $rfqModel->where('status !=', 'cancelled');
                    if ($soNumber) {
                        $query = $query->groupStart()
                            ->like('notes', 'SO#' . $soId)
                            ->orLike('notes', 'SO#' . $soNumber)
                            ->groupEnd();
                    } else {
                        $query = $query->like('notes', 'SO#' . $soId);
                    }
                    $existingRfq = $query->first();
                    $hasAutoRfq = !empty($existingRfq);
                }
            } catch (\Throwable $_) {
                $hasAutoRfq = false;
            }
            
            $data['hasAutoRfq'] = $hasAutoRfq;
            
        } catch (\Throwable $e) {
            log_message('error', 'Inventory availability check failed: ' . $e->getMessage());
            // Default values if service fails
            $data['hasShortage'] = false;
            $data['shortageCount'] = 0;
            $data['hasAutoRfq'] = false;
        }

        // Phase-A: Ready-to-Ship detection (read-only)
        try {
            $data['readyToShip'] = !empty($readyData['readyToShip']);

            foreach ($data['lines'] as &$line) {
                $lineId = (int)($line['id'] ?? 0);
                if ($lineId > 0 && isset($readyMap[$lineId])) {
                    $line['ready_qty'] = (float)($readyMap[$lineId]['ready_now'] ?? 0);
                    $line['shipped_qty'] = (float)($readyMap[$lineId]['shipped_qty'] ?? 0);
                    $line['remaining_qty'] = (float)($readyMap[$lineId]['remaining_qty'] ?? 0);
                } else {
                    $line['ready_qty'] = 0.0;
                }
            }
            unset($line);
        } catch (\Throwable $e) {
            $data['readyToShip'] = false;
            foreach ($data['lines'] as &$line) {
                $line['ready_qty'] = 0.0;
            }
            unset($line);
        }

        // Estimated shipment weight (kg): remaining-to-ship basis, with ordered-weight fallback.
        $toKg = static function (float $weightValue, ?string $weightUnit): float {
            $unit = strtolower(trim((string)$weightUnit));
            if ($weightValue <= 0) {
                return 0.0;
            }
            if ($unit === '' || in_array($unit, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) {
                return $weightValue;
            }
            if (in_array($unit, ['g', 'gm', 'gram', 'grams'], true)) {
                return $weightValue / 1000;
            }
            if (in_array($unit, ['mg', 'milligram', 'milligrams'], true)) {
                return $weightValue / 1000000;
            }
            if (in_array($unit, ['lb', 'lbs', 'pound', 'pounds'], true)) {
                return $weightValue * 0.45359237;
            }
            if (in_array($unit, ['oz', 'ounce', 'ounces'], true)) {
                return $weightValue * 0.0283495231;
            }
            return $weightValue;
        };

        $orderedWeightKg = 0.0;
        $remainingWeightKg = 0.0;
        foreach ($data['lines'] as $line) {
            $isStockable = (bool)($line['is_stockable'] ?? true);
            if (!$isStockable) {
                continue;
            }
            $unitWeightRaw = (float)($line['unit_weight'] ?? ($line['weight'] ?? 0));
            $unitWeightKg = $toKg($unitWeightRaw, $line['weight_unit'] ?? null);
            if ($unitWeightKg <= 0) {
                continue;
            }
            $orderedQty = (float)($line['quantity'] ?? 0);
            $requiredQty = array_key_exists('required_qty', $line)
                ? (float)($line['required_qty'] ?? 0)
                : $orderedQty;
            $orderedWeightKg += ($orderedQty * $unitWeightKg);
            $remainingWeightKg += (max(0.0, $requiredQty) * $unitWeightKg);
        }

        $data['orderedWeightKg'] = round($orderedWeightKg, 2);
        $data['estimatedShipmentWeightKg'] = round($remainingWeightKg, 2);
        
        try { $data['logEntries'] = \App\Libraries\DocumentLogger::getForDocument(\App\Libraries\DocumentLogger::TYPE_SALES_ORDER, (int)$id); } catch (\Throwable $_) { $data['logEntries'] = []; }

        // Check for existing draft or confirmed DO
        $data['existingDo'] = null;
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('delivery_orders')) {
                $data['existingDo'] = $db->table('delivery_orders')
                    ->select('id, do_number, status')
                        ->where('sales_order_id', $orderId)
                    ->orderBy("CASE status WHEN 'delivered' THEN 1 WHEN 'shipped' THEN 2 WHEN 'confirmed' THEN 3 WHEN 'draft' THEN 4 ELSE 5 END", '', false)
                    ->get()->getRowArray();
            }
        } catch (\Throwable $_) {}

        return view('sales_orders/view', $data);
    }

    public function pdf($id)
    {
        $order = $this->model->findByPublicIdOrId($id);
        if (!$order) {
            return redirect()->to('/sales-orders')->with('error', 'Sales order not found');
        }
        $orderId = (int)$order['id'];

        $quoteWithLines = null;
        if (!empty($order['quotation_id'])) {
            try {
                $quoteWithLines = $this->quotationModel->getWithLines((int)$order['quotation_id']);
            } catch (\Throwable $_) {
                $quoteWithLines = null;
            }
        }

        $customer = [];
        try {
            $customer = $this->customerModel->find((int)($order['customer_id'] ?? 0)) ?: [];
        } catch (\Throwable $_) {
            $customer = [];
        }

        $customerAddress = [];
        try {
            $addrModel = new CustomerAddressModel();
            $customerAddress = $addrModel->where('customer_id', (int)($order['customer_id'] ?? 0))
                ->orderBy('is_billing', 'DESC')
                ->orderBy('is_shipping', 'DESC')
                ->orderBy('id', 'ASC')
                ->first() ?: [];
        } catch (\Throwable $_) {
            $customerAddress = [];
        }

        $lines = $quoteWithLines['lines'] ?? $this->lineModel->where('sales_order_id', $orderId)->orderBy('id', 'ASC')->findAll();
        $pdfLines = [];
        foreach ($lines as $line) {
            $qty = (float)($line['quantity'] ?? 0);
            $unitPrice = (float)($line['unit_price'] ?? 0);
            $lineTotal = isset($line['line_total']) ? (float)$line['line_total'] : ($qty * $unitPrice);
            $pdfLines[] = [
                'id' => $line['id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'product_code' => $line['product_code'] ?? ($line['code'] ?? null),
                'product_name' => $line['product_name'] ?? null,
                'description' => $line['description'] ?? '',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'discount_value' => (float)($line['discount_value'] ?? 0),
                'discount_amount' => (float)($line['discount_amount'] ?? 0),
                'tax_rate' => (float)($line['tax_rate'] ?? ($line['tax'] ?? 0)),
                'tax_amount' => (float)($line['tax_amount'] ?? 0),
            ];
        }

        $company = (new CompanySettingsModel())->first() ?: [];
        $payload = [
            'invoice' => [
                'id' => $order['id'] ?? $orderId,
                'invoice_number' => $order['order_number'] ?? ('SO-' . $orderId),
                'issue_date' => $order['order_date'] ?? date('Y-m-d'),
                'subtotal' => (float)($order['subtotal'] ?? 0),
                'tax_total' => (float)($order['tax_total'] ?? 0),
                'total_amount' => (float)($order['total'] ?? 0),
                'shipping_cost' => (float)($order['shipping_amount'] ?? 0),
                'currency_code' => $order['currency_code'] ?? ($order['currency'] ?? $this->getDefaultSalesCurrency()),
                'status' => $order['status'] ?? 'draft',
            ],
            'lines' => $pdfLines,
            'company' => $company,
            'customer' => $customer,
            'customerAddress' => $customerAddress,
            'document_title' => 'Sales Order',
            'document_number_label' => 'Sales Order #',
            'document_date_label' => 'Date:',
            'document_prefix' => '',
            'party_label' => 'Bill To',
            'pdf_show_header_address' => (int)($company['pdf_so_show_header'] ?? 1),
            'pdf_show_footer' => (int)($company['pdf_so_show_footer'] ?? 1),
        ];

        $pdf = (new InvoicePdfGenerator())->generateSystemInvoice($payload);
        if (is_array($pdf) && !empty($pdf['path']) && is_file($pdf['path'])) {
            $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($order['order_number'] ?? ('SO-' . $orderId))) ?: ('SO-' . $orderId);
            return $this->response->download($pdf['path'], null)
                ->setFileName('sales_order_' . $safeNumber . '.pdf')
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', '0');
        }

        return redirect()->back()->with('error', 'Failed to generate sales order PDF');
    }

    public function warehouseDocument($id)
    {
        return $this->warehousePdf($id);
    }

    public function warehousePrintView($id)
    {
        $order = $this->model->findByPublicIdOrId($id);
        if (!$order) {
            return redirect()->to('/sales-orders')->with('error', 'Sales order not found');
        }

        try {
            $orderId = (int)$order['id'];
            $lines = $this->lineModel->where('sales_order_id', $orderId)->orderBy('id', 'ASC')->findAll();
            $quoteLines = [];
            if (!empty($order['quotation_id'])) {
                try {
                    $quoteWithLines = $this->quotationModel->getWithLines((int)$order['quotation_id']);
                    if (!empty($quoteWithLines['lines']) && is_array($quoteWithLines['lines'])) {
                        $quoteLines = $quoteWithLines['lines'];
                    }
                } catch (\Throwable $_) {
                    $quoteLines = [];
                }
            }

            if (!empty($quoteLines)) {
                $quoteBuckets = [];
                foreach ($quoteLines as $quoteLine) {
                    if (isset($quoteLine['display_type']) && $quoteLine['display_type'] === 'section') {
                        continue;
                    }
                    $qDesc = strtolower(trim((string)($quoteLine['description'] ?? '')));
                    $qQty = (float)($quoteLine['quantity'] ?? 0);
                    $qPrice = (float)($quoteLine['unit_price'] ?? 0);
                    $qTotal = isset($quoteLine['line_total']) ? (float)$quoteLine['line_total'] : ($qQty * $qPrice);
                    $qKey = $qDesc . '|' . number_format($qQty, 2, '.', '') . '|' . number_format($qPrice, 2, '.', '') . '|' . number_format($qTotal, 2, '.', '');
                    if (!isset($quoteBuckets[$qKey])) {
                        $quoteBuckets[$qKey] = [];
                    }
                    $quoteBuckets[$qKey][] = $quoteLine;
                }

                foreach ($lines as &$line) {
                    if ((int)($line['product_id'] ?? 0) > 0 || (int)($line['product_variant_id'] ?? 0) > 0) {
                        continue;
                    }

                    $lDesc = strtolower(trim((string)($line['description'] ?? '')));
                    $lQty = (float)($line['quantity'] ?? 0);
                    $lPrice = (float)($line['unit_price'] ?? 0);
                    $lTotal = isset($line['line_total']) ? (float)$line['line_total'] : ($lQty * $lPrice);
                    $lKey = $lDesc . '|' . number_format($lQty, 2, '.', '') . '|' . number_format($lPrice, 2, '.', '') . '|' . number_format($lTotal, 2, '.', '');

                    if (empty($quoteBuckets[$lKey])) {
                        continue;
                    }

                    $src = array_shift($quoteBuckets[$lKey]);
                    $srcProductId = isset($src['product_id']) ? (int)$src['product_id'] : 0;
                    $srcVariantId = isset($src['product_variant_id']) ? (int)$src['product_variant_id'] : 0;
                    if ($srcProductId > 0) {
                        $line['product_id'] = $srcProductId;
                    }
                    if ($srcVariantId > 0) {
                        $line['product_variant_id'] = $srcVariantId;
                    }
                    $srcCode = trim((string)($src['product_code'] ?? ''));
                    $srcName = trim((string)($src['product_name'] ?? ''));
                    if ($srcCode !== '') {
                        $line['product_code'] = $srcCode;
                    }
                    if ($srcName !== '') {
                        $line['product_name'] = $srcName;
                    }
                }
                unset($line);
            }

            $company = (new CompanySettingsModel())->first() ?: [];

            $pdfLines = [];
            $productModel = new ProductModel();
            $variantModel = new ProductVariantModel();

            $productIds = array_values(array_filter(array_unique(array_map(static function ($line) {
                return isset($line['product_id']) ? (int)$line['product_id'] : 0;
            }, $lines))));
            $variantIds = array_values(array_filter(array_unique(array_map(static function ($line) {
                return isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;
            }, $lines))));
            $lineCodes = array_values(array_filter(array_unique(array_map(static function ($line) {
                $code = trim((string)($line['product_code'] ?? ''));
                return $code !== '' ? $code : null;
            }, $lines))));

            $productMap = [];
            $variantMap = [];
            $variantMapByArt = [];

            if (!empty($productIds)) {
                try {
                    foreach ($productModel->whereIn('id', $productIds)->findAll() as $productRow) {
                        $productMap[(int)$productRow['id']] = $productRow;
                    }
                } catch (\Throwable $_) {}
            }

            if (!empty($variantIds) || !empty($lineCodes)) {
                try {
                    $vb = $variantModel->builder()->select('id, product_id, art_number, name, image');
                    if (!empty($variantIds) && !empty($lineCodes)) {
                        $vb->groupStart()->whereIn('id', $variantIds)->orWhereIn('art_number', $lineCodes)->groupEnd();
                    } elseif (!empty($variantIds)) {
                        $vb->whereIn('id', $variantIds);
                    } else {
                        $vb->whereIn('art_number', $lineCodes);
                    }
                    foreach ($vb->get()->getResultArray() as $variantRow) {
                        $variantMap[(int)$variantRow['id']] = $variantRow;
                        $art = strtoupper(trim((string)($variantRow['art_number'] ?? '')));
                        if ($art !== '') {
                            $variantMapByArt[$art] = $variantRow;
                        }
                    }
                } catch (\Throwable $_) {}
            }

            if (!empty($variantMap)) {
                $missingProductIds = [];
                foreach ($variantMap as $variantRow) {
                    $variantProductId = isset($variantRow['product_id']) ? (int)$variantRow['product_id'] : 0;
                    if ($variantProductId > 0 && !isset($productMap[$variantProductId])) {
                        $missingProductIds[] = $variantProductId;
                    }
                }
                $missingProductIds = array_values(array_unique($missingProductIds));
                if (!empty($missingProductIds)) {
                    try {
                        foreach ($productModel->whereIn('id', $missingProductIds)->findAll() as $productRow) {
                            $productMap[(int)$productRow['id']] = $productRow;
                        }
                    } catch (\Throwable $_) {}
                }
            }

            foreach ($lines as $line) {
                $productCode = $line['product_code'] ?? null;
                $productName = $line['product_name'] ?? null;
                $productImage = trim((string)($line['product_image'] ?? ''));
                $productImages = $line['product_images'] ?? null;
                $variantImage = trim((string)($line['variant_image'] ?? ''));
                $variantCode = trim((string)($line['variant_code'] ?? ''));
                $productType = null;
                $detailedType = null;

                $productId = isset($line['product_id']) ? (int)$line['product_id'] : 0;
                $variantId = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;

                if ($variantId <= 0) {
                    $lineCodeKey = strtoupper(trim((string)($productCode ?? '')));
                    if ($lineCodeKey !== '' && isset($variantMapByArt[$lineCodeKey])) {
                        $variantId = (int)($variantMapByArt[$lineCodeKey]['id'] ?? 0);
                    }
                }

                if ($productId <= 0 && $variantId > 0 && isset($variantMap[$variantId])) {
                    $productId = isset($variantMap[$variantId]['product_id']) ? (int)$variantMap[$variantId]['product_id'] : 0;
                }

                if ($productId > 0 && isset($productMap[$productId])) {
                    $productRow = $productMap[$productId];
                    $productCode = $productCode ?: ($productRow['code'] ?? ($productRow['sku'] ?? ''));
                    $productName = $productName ?: ($productRow['name'] ?? '');
                    $productImage = $productImage !== '' ? $productImage : trim((string)($productRow['image'] ?? ''));
                    $productImages = $productImages ?? ($productRow['images'] ?? null);
                    $productType = $productRow['product_type'] ?? null;
                    $detailedType = $productRow['detailed_type'] ?? null;
                }

                if ($variantId > 0 && isset($variantMap[$variantId])) {
                    $variantRow = $variantMap[$variantId];
                    $variantCode = $variantCode !== '' ? $variantCode : trim((string)($variantRow['art_number'] ?? ''));
                    $variantImage = $variantImage !== '' ? $variantImage : trim((string)($variantRow['image'] ?? ''));

                    // Warehouse flow identifies by variant code when available.
                    if ($variantCode !== '') {
                        $productCode = $variantCode;
                    }

                    if (empty($productName)) {
                        $productName = trim((string)($variantRow['name'] ?? ''));
                    }
                    if (trim((string)($line['description'] ?? '')) === '' || strcasecmp(trim((string)($line['description'] ?? '')), trim((string)($productName ?? ''))) === 0) {
                        $line['description'] = trim((string)($variantRow['name'] ?? ($line['description'] ?? '')));
                    }
                }

                $pdfLines[] = [
                    'id' => $line['id'] ?? null,
                    'product_id' => $productId > 0 ? $productId : null,
                    'product_variant_id' => $variantId > 0 ? $variantId : null,
                    'product_code' => $productCode ?? '',
                    'variant_code' => $variantCode,
                    'product_name' => $productName ?? '',
                    'product_type' => $productType,
                    'detailed_type' => $detailedType,
                    'description' => $line['description'] ?? '',
                    'product_image' => $productImage,
                    'variant_image' => $variantImage,
                    'product_images' => $productImages,
                    'product_image_url' => $line['product_image_url'] ?? '',
                    'variant_image_url' => $line['variant_image_url'] ?? '',
                    'quantity' => (float)($line['quantity'] ?? 0),
                    'unit_price' => (float)($line['unit_price'] ?? 0),
                    'unit' => $line['unit'] ?? 'pcs',
                    'line_total' => isset($line['line_total']) ? (float)$line['line_total'] : ((float)($line['quantity'] ?? 0) * (float)($line['unit_price'] ?? 0)),
                    'discount_value' => (float)($line['discount_value'] ?? 0),
                    'discount_amount' => (float)($line['discount_amount'] ?? 0),
                    'tax_rate' => (float)($line['tax_rate'] ?? ($line['tax'] ?? 0)),
                    'tax_amount' => (float)($line['tax_amount'] ?? 0),
                ];
            }

            $pdfLines = $this->enrichWarehouseStockSnapshot($pdfLines);

            $warehouseCustomerNumber = '';
            try {
                $customer = $this->customerModel->find((int)($order['customer_id'] ?? 0));
                $warehouseCustomerNumber = trim((string)($customer['customer_code'] ?? ''));
            } catch (\Throwable $_) {}

            $invoiceLike = [
                'id' => $orderId,
                'invoice_number' => $order['order_number'] ?? ('SO-' . $orderId),
                'issue_date' => $order['order_date'] ?? date('Y-m-d'),
                'currency' => $order['currency'] ?? $this->getDefaultSalesCurrency(),
                'notes' => $order['notes'] ?? null,
            ];

            return view('pdf/invoice_warehouse_picklist', [
                'invoice' => $invoiceLike,
                'lines' => $pdfLines,
                'company' => $company,
                'document_title' => 'Warehouse Pick List',
                'document_number_label' => 'Sales Order #',
                'document_date_label' => 'Date:',
                'party_label' => 'Warehouse Use',
                'warehouse_customer_number' => $warehouseCustomerNumber,
                'show_print_toolbar' => true,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'SO warehousePrintView failed: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to generate warehouse print view: ' . $e->getMessage());
        }
    }

        public function warehousePdf($id)
        {
            $order = $this->model->findByPublicIdOrId($id);
            if (!$order) {
                return redirect()->to('/sales-orders')->with('error', 'Sales order not found');
            }
            $orderId = (int)$order['id'];

            // Generate warehouse PDF from SO lines directly
            try {
                $lines = $this->lineModel->where('sales_order_id', $orderId)->orderBy('id', 'ASC')->findAll();
                $quoteLines = [];
                if (!empty($order['quotation_id'])) {
                    try {
                        $quoteWithLines = $this->quotationModel->getWithLines((int)$order['quotation_id']);
                        if (!empty($quoteWithLines['lines']) && is_array($quoteWithLines['lines'])) {
                            $quoteLines = $quoteWithLines['lines'];
                        }
                    } catch (\Throwable $_) {
                        $quoteLines = [];
                    }
                }

                if (!empty($quoteLines)) {
                    $quoteBuckets = [];
                    foreach ($quoteLines as $quoteLine) {
                        if (isset($quoteLine['display_type']) && $quoteLine['display_type'] === 'section') {
                            continue;
                        }
                        $qDesc = strtolower(trim((string)($quoteLine['description'] ?? '')));
                        $qQty = (float)($quoteLine['quantity'] ?? 0);
                        $qPrice = (float)($quoteLine['unit_price'] ?? 0);
                        $qTotal = isset($quoteLine['line_total']) ? (float)$quoteLine['line_total'] : ($qQty * $qPrice);
                        $qKey = $qDesc . '|' . number_format($qQty, 2, '.', '') . '|' . number_format($qPrice, 2, '.', '') . '|' . number_format($qTotal, 2, '.', '');
                        if (!isset($quoteBuckets[$qKey])) {
                            $quoteBuckets[$qKey] = [];
                        }
                        $quoteBuckets[$qKey][] = $quoteLine;
                    }

                    foreach ($lines as &$line) {
                        if ((int)($line['product_id'] ?? 0) > 0 || (int)($line['product_variant_id'] ?? 0) > 0) {
                            continue;
                        }

                        $lDesc = strtolower(trim((string)($line['description'] ?? '')));
                        $lQty = (float)($line['quantity'] ?? 0);
                        $lPrice = (float)($line['unit_price'] ?? 0);
                        $lTotal = isset($line['line_total']) ? (float)$line['line_total'] : ($lQty * $lPrice);
                        $lKey = $lDesc . '|' . number_format($lQty, 2, '.', '') . '|' . number_format($lPrice, 2, '.', '') . '|' . number_format($lTotal, 2, '.', '');

                        if (empty($quoteBuckets[$lKey])) {
                            continue;
                        }

                        $src = array_shift($quoteBuckets[$lKey]);
                        $srcProductId = isset($src['product_id']) ? (int)$src['product_id'] : 0;
                        $srcVariantId = isset($src['product_variant_id']) ? (int)$src['product_variant_id'] : 0;
                        if ($srcProductId > 0) {
                            $line['product_id'] = $srcProductId;
                        }
                        if ($srcVariantId > 0) {
                            $line['product_variant_id'] = $srcVariantId;
                        }
                        $srcCode = trim((string)($src['product_code'] ?? ''));
                        $srcName = trim((string)($src['product_name'] ?? ''));
                        if ($srcCode !== '') {
                            $line['product_code'] = $srcCode;
                        }
                        if ($srcName !== '') {
                            $line['product_name'] = $srcName;
                        }
                    }
                    unset($line);
                }

                $company = (new CompanySettingsModel())->first() ?: [];

                $pdfLines = [];
                $productModel = new ProductModel();
                $variantModel = new ProductVariantModel();

                $productIds = array_values(array_filter(array_unique(array_map(static function ($line) {
                    return isset($line['product_id']) ? (int)$line['product_id'] : 0;
                }, $lines))));
                $variantIds = array_values(array_filter(array_unique(array_map(static function ($line) {
                    return isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;
                }, $lines))));
                $lineCodes = array_values(array_filter(array_unique(array_map(static function ($line) {
                    $code = trim((string)($line['product_code'] ?? ''));
                    return $code !== '' ? $code : null;
                }, $lines))));

                $productMap = [];
                $variantMap = [];
                $variantMapByArt = [];

                if (!empty($productIds)) {
                    try {
                        foreach ($productModel->whereIn('id', $productIds)->findAll() as $productRow) {
                            $productMap[(int)$productRow['id']] = $productRow;
                        }
                    } catch (\Throwable $_) {}
                }

                if (!empty($variantIds) || !empty($lineCodes)) {
                    try {
                        $vb = $variantModel->builder()->select('id, product_id, art_number, name, image');
                        if (!empty($variantIds) && !empty($lineCodes)) {
                            $vb->groupStart()->whereIn('id', $variantIds)->orWhereIn('art_number', $lineCodes)->groupEnd();
                        } elseif (!empty($variantIds)) {
                            $vb->whereIn('id', $variantIds);
                        } else {
                            $vb->whereIn('art_number', $lineCodes);
                        }
                        foreach ($vb->get()->getResultArray() as $variantRow) {
                            $variantMap[(int)$variantRow['id']] = $variantRow;
                            $art = strtoupper(trim((string)($variantRow['art_number'] ?? '')));
                            if ($art !== '') {
                                $variantMapByArt[$art] = $variantRow;
                            }
                        }
                    } catch (\Throwable $_) {}
                }

                    if (!empty($variantMap)) {
                        $missingProductIds = [];
                        foreach ($variantMap as $variantRow) {
                            $variantProductId = isset($variantRow['product_id']) ? (int)$variantRow['product_id'] : 0;
                            if ($variantProductId > 0 && !isset($productMap[$variantProductId])) {
                                $missingProductIds[] = $variantProductId;
                            }
                        }
                        $missingProductIds = array_values(array_unique($missingProductIds));
                        if (!empty($missingProductIds)) {
                            try {
                                foreach ($productModel->whereIn('id', $missingProductIds)->findAll() as $productRow) {
                                    $productMap[(int)$productRow['id']] = $productRow;
                                }
                            } catch (\Throwable $_) {}
                        }
                    }

                foreach ($lines as $line) {
                    $productCode = $line['product_code'] ?? null;
                    $productName = $line['product_name'] ?? null;
                    $productImage = trim((string)($line['product_image'] ?? ''));
                    $productImages = $line['product_images'] ?? null;
                    $variantImage = trim((string)($line['variant_image'] ?? ''));
                    $variantCode = trim((string)($line['variant_code'] ?? ''));
                    $productType = null;
                    $detailedType = null;

                    $productId = isset($line['product_id']) ? (int)$line['product_id'] : 0;
                    $variantId = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;

                    if ($variantId <= 0) {
                        $lineCodeKey = strtoupper(trim((string)($productCode ?? '')));
                        if ($lineCodeKey !== '' && isset($variantMapByArt[$lineCodeKey])) {
                            $variantId = (int)($variantMapByArt[$lineCodeKey]['id'] ?? 0);
                        }
                    }

                    if ($productId <= 0 && $variantId > 0 && isset($variantMap[$variantId])) {
                        $productId = isset($variantMap[$variantId]['product_id']) ? (int)$variantMap[$variantId]['product_id'] : 0;
                    }

                    if ($productId > 0 && isset($productMap[$productId])) {
                        $productRow = $productMap[$productId];
                        $productCode = $productCode ?: ($productRow['code'] ?? ($productRow['sku'] ?? ''));
                        $productName = $productName ?: ($productRow['name'] ?? '');
                        $productImage = $productImage !== '' ? $productImage : trim((string)($productRow['image'] ?? ''));
                        $productImages = $productImages ?? ($productRow['images'] ?? null);
                        $productType = $productRow['product_type'] ?? null;
                        $detailedType = $productRow['detailed_type'] ?? null;
                    }

                    if ($variantId > 0 && isset($variantMap[$variantId])) {
                        $variantRow = $variantMap[$variantId];
                        $variantCode = $variantCode !== '' ? $variantCode : trim((string)($variantRow['art_number'] ?? ''));
                        $variantImage = $variantImage !== '' ? $variantImage : trim((string)($variantRow['image'] ?? ''));

                        if ($variantCode !== '') {
                            $productCode = $variantCode;
                        }

                        if (empty($productName)) {
                            $productName = trim((string)($variantRow['name'] ?? ''));
                        }
                        if (trim((string)($line['description'] ?? '')) === '' || strcasecmp(trim((string)($line['description'] ?? '')), trim((string)($productName ?? ''))) === 0) {
                            $line['description'] = trim((string)($variantRow['name'] ?? ($line['description'] ?? '')));
                        }
                    }

                    $pdfLines[] = [
                        'id' => $line['id'] ?? null,
                        'product_id' => $productId > 0 ? $productId : null,
                        'product_variant_id' => $variantId > 0 ? $variantId : null,
                        'product_code' => $productCode ?? '',
                        'variant_code' => $variantCode,
                        'product_name' => $productName ?? '',
                        'product_type' => $productType,
                        'detailed_type' => $detailedType,
                        'description' => $line['description'] ?? '',
                        'product_image' => $productImage,
                        'variant_image' => $variantImage,
                        'product_images' => $productImages,
                        'product_image_url' => $line['product_image_url'] ?? '',
                        'variant_image_url' => $line['variant_image_url'] ?? '',
                        'quantity' => (float)($line['quantity'] ?? 0),
                        'unit_price' => (float)($line['unit_price'] ?? 0),
                        'unit' => $line['unit'] ?? 'pcs',
                        'line_total' => isset($line['line_total']) ? (float)$line['line_total'] : ((float)($line['quantity'] ?? 0) * (float)($line['unit_price'] ?? 0)),
                        'discount_value' => (float)($line['discount_value'] ?? 0),
                        'discount_amount' => (float)($line['discount_amount'] ?? 0),
                        'tax_rate' => (float)($line['tax_rate'] ?? ($line['tax'] ?? 0)),
                        'tax_amount' => (float)($line['tax_amount'] ?? 0),
                    ];
                }

                $pdfLines = $this->enrichWarehouseStockSnapshot($pdfLines);

                $warehouseCustomerNumber = '';
                try {
                    $customer = $this->customerModel->find((int)($order['customer_id'] ?? 0));
                    $warehouseCustomerNumber = trim((string)($customer['customer_code'] ?? ''));
                } catch (\Throwable $_) {}

                $invoiceLike = [
                    'id' => $orderId,
                    'invoice_number' => $order['order_number'] ?? ('SO-' . $orderId),
                    'issue_date' => $order['order_date'] ?? date('Y-m-d'),
                    'currency' => $order['currency'] ?? $this->getDefaultSalesCurrency(),
                    'notes' => $order['notes'] ?? null,
                ];

                $pdf = (new InvoicePdfGenerator())->generate([
                    'invoice' => $invoiceLike,
                    'lines' => $pdfLines,
                    'company' => $company,
                    'document_title' => 'Warehouse Pick List',
                    'document_number_label' => 'Sales Order #',
                    'document_date_label' => 'Date:',
                    'document_prefix' => '',
                    'party_label' => 'Warehouse Use',
                    'warehouse_customer_number' => $warehouseCustomerNumber,
                    'pdf_show_header_address' => 0,
                    'pdf_show_footer' => (int)($company['pdf_so_show_footer'] ?? 1),
                    'pdf_template' => 'warehouse_picklist',
                ], 'warehouse_picklist');

                if (is_array($pdf) && !empty($pdf['path']) && is_file($pdf['path'])) {
                    $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($order['order_number'] ?? ('SO-' . $orderId))) ?: ('SO-' . $orderId);
                    return $this->response->download($pdf['path'], null)
                        ->setFileName($safeNumber . '_WHPL.pdf')
                        ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                        ->setHeader('Pragma', 'no-cache')
                        ->setHeader('Expires', '0');
                }

                return redirect()->back()->with('error', 'Failed to generate warehouse PDF');
            } catch (\Throwable $e) {
                log_message('error', 'SO warehousePdf failed: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to generate warehouse PDF: ' . $e->getMessage());
            }
        }

    private function enrichWarehouseStockSnapshot(array $lines): array
    {
        if (empty($lines)) {
            return $lines;
        }

        $keys = [];
        foreach ($lines as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $variantId = (int)($line['product_variant_id'] ?? 0);
            $keys[$productId . '|' . $variantId] = true;
            $keys[$productId . '|0'] = true;
        }

        if (empty($keys)) {
            foreach ($lines as &$line) {
                $isService = strtolower(trim((string)($line['detailed_type'] ?? ''))) === 'service'
                    || strtolower(trim((string)($line['product_type'] ?? ''))) === 'service';
                if ($isService) {
                    $line['warehouse_required_qty'] = 0.0;
                    $line['warehouse_available_qty'] = 0.0;
                    $line['warehouse_locations'] = ['Service item'];
                    $line['warehouse_locations_text'] = 'Service item';
                    $line['warehouse_status'] = 'In Stock';
                } else {
                    $line['warehouse_required_qty'] = (float)($line['quantity'] ?? 0);
                    $line['warehouse_available_qty'] = 0.0;
                    $line['warehouse_locations'] = [];
                    $line['warehouse_locations_text'] = 'Not in stock';
                    $line['warehouse_status'] = 'Not in Stock';
                }
            }
            unset($line);
            return $lines;
        }

        $productIds = [];
        foreach (array_keys($keys) as $key) {
            [$productId] = array_map('intval', explode('|', $key, 2));
            if ($productId > 0) {
                $productIds[$productId] = true;
            }
        }

        $totalsByKey = [];
        $locationsByKey = [];
        if (!empty($productIds)) {
            try {
                $db = \Config\Database::connect();
                $rows = $db->table('stock_balances sb')
                    ->select('sb.product_id, COALESCE(sb.variant_id, 0) AS variant_id, sb.warehouse_id, sb.location_id, SUM(sb.quantity) AS qty, wl.name AS location_name, w.name AS warehouse_name, w.code AS warehouse_code')
                    ->join('warehouse_locations wl', 'wl.id = sb.location_id', 'left')
                    ->join('warehouses w', 'w.id = sb.warehouse_id', 'left')
                    ->whereIn('sb.product_id', array_values(array_map('intval', array_keys($productIds))))
                    ->where('sb.quantity >', 0)
                    ->groupBy('sb.product_id, sb.variant_id, sb.warehouse_id, sb.location_id, wl.name, w.name, w.code')
                    ->orderBy('w.name', 'ASC')
                    ->orderBy('wl.name', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    $productId = (int)($row['product_id'] ?? 0);
                    $variantId = (int)($row['variant_id'] ?? 0);
                    $qty = (float)($row['qty'] ?? 0);
                    if ($productId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $candidateKey = $productId . '|' . $variantId;
                    if (!isset($keys[$candidateKey])) {
                        $candidateKey = $productId . '|0';
                        if (!isset($keys[$candidateKey])) {
                            continue;
                        }
                    }

                    $totalsByKey[$candidateKey] = ($totalsByKey[$candidateKey] ?? 0.0) + $qty;

                    $warehouseName = trim((string)($row['warehouse_name'] ?? ''));
                    $warehouseCode = trim((string)($row['warehouse_code'] ?? ''));
                    $locationName = trim((string)($row['location_name'] ?? ''));
                    $parts = [];
                    if ($warehouseName !== '') {
                        $parts[] = $warehouseName;
                    } elseif ($warehouseCode !== '') {
                        $parts[] = $warehouseCode;
                    }
                    if ($locationName !== '') {
                        $parts[] = $locationName;
                    }
                    $label = !empty($parts)
                        ? implode(' / ', $parts)
                        : ('Warehouse #' . (int)($row['warehouse_id'] ?? 0));
                    $locationsByKey[$candidateKey][] = $label . ' (' . rtrim(rtrim(number_format($qty, 2), '0'), '.') . ')';
                }
            } catch (\Throwable $_) {
                // best effort: fallback values below
            }
        }

        foreach ($lines as &$line) {
            $productId = (int)($line['product_id'] ?? 0);
            $variantId = (int)($line['product_variant_id'] ?? 0);
            $isService = strtolower(trim((string)($line['detailed_type'] ?? ''))) === 'service'
                || strtolower(trim((string)($line['product_type'] ?? ''))) === 'service';

            if ($isService) {
                $line['warehouse_required_qty'] = 0.0;
                $line['warehouse_available_qty'] = 0.0;
                $line['warehouse_locations'] = ['Service item'];
                $line['warehouse_locations_text'] = 'Service item';
                $line['warehouse_status'] = 'In Stock';
                continue;
            }

            if ($productId <= 0) {
                $line['warehouse_required_qty'] = (float)($line['quantity'] ?? 0);
                $line['warehouse_available_qty'] = 0.0;
                $line['warehouse_locations'] = [];
                $line['warehouse_locations_text'] = 'Not in stock';
                $line['warehouse_status'] = 'Not in Stock';
                continue;
            }

            $key = $productId . '|' . $variantId;
            if (!isset($totalsByKey[$key])) {
                $fallbackKey = $productId . '|0';
                if (isset($totalsByKey[$fallbackKey])) {
                    $key = $fallbackKey;
                }
            }

            $availableQty = (float)($totalsByKey[$key] ?? 0.0);
            $locations = $locationsByKey[$key] ?? [];
            if (!empty($locations)) {
                $locations = array_values(array_unique($locations));
            }

            $line['warehouse_required_qty'] = (float)($line['quantity'] ?? 0);
            $line['warehouse_available_qty'] = $availableQty;
            $line['warehouse_locations'] = $locations;
            $line['warehouse_locations_text'] = !empty($locations)
                ? implode(', ', $locations)
                : 'Not in stock';
            $line['warehouse_status'] = $availableQty > 0 ? 'In Stock' : 'Not in Stock';
        }
        unset($line);

        return $lines;
    }

    public function createFromQuotation($quotationId)
    {
        $quote = $this->quotationModel->find($quotationId);
        if (!$quote) return redirect()->back()->with('error','Quotation not found');

        // Prevent duplicate conversions
        if (!empty($quote['converted_to_sales_order_id'])) {
            return redirect()->to('/sales-orders/view/' . (int)$quote['converted_to_sales_order_id'])
                ->with('info', 'This quotation is already converted to a sales order.');
        }

        // basic conversion (same logic as Quotations controller)
        $shippingAmount = isset($quote['shipping_amount']) ? (float)$quote['shipping_amount'] : 0.0;

        // Be schema-safe: some installs may not have sales_orders.shipping_amount yet.
        $db = \Config\Database::connect();
        try {
            $orderCols = $db->getFieldNames('sales_orders');
        } catch (\Throwable $_) {
            $orderCols = [];
        }
        if (!in_array('shipping_amount', $orderCols)) {
            try {
                $db->query("ALTER TABLE `sales_orders` ADD COLUMN `shipping_amount` DECIMAL(12,2) NOT NULL DEFAULT 0");
                $orderCols = $db->getFieldNames('sales_orders');
            } catch (\Throwable $_) {
                // best-effort: if cannot add, we'll skip writing shipping to avoid SQL errors
            }
        }

        $taxTotal = $quote['tax_total'] ?? ($quote['tax'] ?? 0);
        $subtotal = $quote['subtotal'] ?? 0;
        $baseTotal = isset($quote['total']) ? (float)$quote['total'] : ((float)$subtotal + (float)$taxTotal + (float)$shippingAmount);

        // Conversion numbering: preserve quotation sequence RI-Q0045 -> RI-S0045 when possible.
        $quoteNumber = trim((string)($quote['quote_number'] ?? ''));
        $derivedFromQuote = $this->deriveSalesOrderNumberFromQuotationNumber($quoteNumber);
        if ($derivedFromQuote !== '' && $this->model->where('order_number', $derivedFromQuote)->countAllResults() === 0) {
            $orderNo = $derivedFromQuote;
        } else {
            $prefix = (string)($this->getDocPrefix('sales_order') ?? 'RI');
            $orderNo = $this->generateNextDocNumber('sales_order', $prefix);
        }

        $orderData = [
            'order_number' => $orderNo,
            'quotation_id' => $quotationId,
            'customer_id' => $quote['customer_id'],
            'order_date' => date('Y-m-d'),
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'total' => $baseTotal,
            'created_by' => session()->get('user_id') ?? null
        ];

        // If status exists on sales_orders, mark it confirmed on conversion
        if (!empty($orderCols) && in_array('status', $orderCols)) {
            $orderData['status'] = 'confirmed';
        }

        if (!empty($orderCols)) {
            // Currency: prefer quotation currency, fall back to default sales currency
            $currency = $quote['currency'] ?? $this->getDefaultSalesCurrency();
            if (in_array('currency', $orderCols)) {
                $orderData['currency'] = $currency;
            }
            if (in_array('currency_code', $orderCols)) {
                $orderData['currency_code'] = $currency;
            }
            if (in_array('shipping_amount', $orderCols)) {
                $orderData['shipping_amount'] = $shippingAmount;
            } elseif (in_array('shipping_cost', $orderCols)) {
                // legacy installs
                $orderData['shipping_cost'] = $shippingAmount;
            }
        }

        // Insert order with graceful fallback if shipping column is still absent
        $orderId = null;
        try {
            $orderId = $this->model->insert($orderData);
        } catch (\Throwable $e) {
            $msg = strtolower($e->getMessage());
            if (strpos($msg, 'unknown column') !== false && (strpos($msg, 'shipping_amount') !== false || strpos($msg, 'shipping_cost') !== false)) {
                unset($orderData['shipping_amount'], $orderData['shipping_cost']);
                $orderId = $this->model->insert($orderData);
            } else {
                throw $e;
            }
        }
        if ($orderId) {
            // QuotationModel does not expose a hasMany() relation; use its helper instead
            $lines = $this->quotationModel->getQuotationLines((int)$quotationId);
            foreach ($lines as $l) {
                $this->lineModel->insert([
                    'sales_order_id' => $orderId,
                    'product_id' => $l['product_id'],
                    'product_variant_id' => $l['product_variant_id'] ?? null,
                    'description' => $l['description'],
                    'quantity' => $l['quantity'],
                    'unit_price' => $l['unit_price'],
                    'line_total' => $l['line_total']
                ]);
            }

            // Update quotation as converted/confirmed (schema-safe)
            try {
                $db->transStart();
                $qCols = [];
                try { $qCols = $db->getFieldNames('quotations'); } catch (\Throwable $_) { $qCols = []; }
                $upd = [];
                // User wants: after converting, quotation should show confirmed
                if (in_array('status', $qCols)) $upd['status'] = 'confirmed';
                if (in_array('converted_to_sales_order_id', $qCols)) $upd['converted_to_sales_order_id'] = (int)$orderId;
                if (!empty($upd)) {
                    $db->table('quotations')->where('id', (int)$quotationId)->update($upd);
                }
                $db->transComplete();
            } catch (\Throwable $_) {
                // best-effort
            }

            return redirect()->to('/sales-orders/view/' . $orderId)->with('success','Sales order created');
        }
        return redirect()->back()->with('error','Failed to create order');
    }

    /**
     * Read doc number prefix from company_settings (schema-safe). Keys:
     * - sales_order_prefix
     * - quotation_prefix
     */
    private function getDocPrefix(string $type): ?string
    {
        $db = \Config\Database::connect();
        try { $cols = $db->getFieldNames('company_settings'); } catch (\Throwable $_) { $cols = []; }
        if (empty($cols)) return null;

        $key = $type === 'quotation' ? 'quotation_prefix' : 'sales_order_prefix';
        if (!in_array($key, $cols)) return null;

        $row = $db->table('company_settings')->select($key)->orderBy('id', 'DESC')->limit(1)->get()->getRowArray();
        $val = trim((string)($row[$key] ?? ''));
        return $val !== '' ? $val : null;
    }

    /**
     * Generate next sequential document number.
     * Format: <PREFIX>-S0001 for sales orders, <PREFIX>-Q0001 for quotations.
     */
    private function generateNextDocNumber(string $type, string $prefix): string
    {
        $db = \Config\Database::connect();

        // Both quotations and sales orders use S-series (RI-S0001)
        $type = $type === 'quotation' ? 'quotation' : 'sales_order';
        $letter = 'S';

        $table = $type === 'quotation' ? 'quotations' : 'sales_orders';
        $field = $type === 'quotation' ? 'quote_number' : 'order_number';

        $prefix = trim($prefix);
        if ($prefix === '') $prefix = 'RI';
        $fullPrefix = $prefix . '-' . $letter;

        try {
            $row = $db->table($table)
                ->select($field)
                ->like($field, $fullPrefix . '%', 'after')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRowArray();

            $last = (string)($row[$field] ?? '');
            $num = 0;
            if ($last !== '') {
                // extract digits from e.g. RI-S0001
                if (preg_match('/' . preg_quote($fullPrefix, '/') . '(\d+)/', $last, $m)) {
                    $num = (int)$m[1];
                } else {
                    $num = (int)preg_replace('/\D+/', '', $last);
                }
            }
            $next = $num + 1;
            return $fullPrefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $_) {
            return $fullPrefix . str_pad('1', 4, '0', STR_PAD_LEFT);
        }
    }

    /**
     * Convert quotation number to matching sales order number.
     * Example: RI-Q0045 => RI-S0045
     */
    private function deriveSalesOrderNumberFromQuotationNumber(string $quoteNumber): string
    {
        $quoteNumber = trim($quoteNumber);
        if ($quoteNumber === '') {
            return '';
        }

        if (preg_match('/^([A-Za-z0-9]+)-Q(\d+)$/i', $quoteNumber, $m)) {
            return strtoupper($m[1]) . '-S' . $m[2];
        }

        $candidate = preg_replace('/-Q/i', '-S', $quoteNumber, 1);
        return is_string($candidate) ? trim($candidate) : '';
    }

    /**
     * Direct Sales Order creation (no quotation).
     * Reuses the quotation create UI for consistency.
     */
    public function create()
    {
        try {
            $currencyModel = new CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code','ASC')->findAll();
        } catch (\Throwable $_) {
            $currencies = [];
        }

        return view('quotations/create', [
            'mode' => 'create',
            'docType' => 'sales_order',
            'currencies' => $currencies,
            'defaultCurrency' => $this->getDefaultSalesCurrency(),
        ]);
    }

    /**
     * Store a direct Sales Order (bulk header + lines).
     * Accepts the same payload shape as quotations/create.
     */
    public function store()
    {
        if ($this->request->getMethod() !== 'post') {
            return redirect()->back();
        }

        $isAjax = $this->request->isAJAX() || strpos((string)$this->request->getHeaderLine('Accept'), 'application/json') !== false;
        $post = $this->request->getPost();

        try {
            $customerId = (int)($post['customer_id'] ?? 0);
            if (!$customerId) throw new \RuntimeException('Please select a customer');

            $currency = strtoupper(trim((string)($post['currency'] ?? '')));
            if ($currency === '') {
                $currency = $this->getDefaultSalesCurrency();
            }

            $shippingAmount = isset($post['shipping_amount']) ? (float)$post['shipping_amount'] : 0.0;
            $orderDateRaw = $post['issue_date'] ?? ($post['order_date'] ?? date('Y-m-d'));
            $orderDate = $this->normalizeDate($orderDateRaw);

            // Collect lines (basic numeric normalization)
            $linesIn = [];
            if (isset($post['lines']) && is_array($post['lines'])) {
                foreach ($post['lines'] as $ln) {
                    if (!is_array($ln)) continue;
                    $linesIn[] = [
                        'product_id' => isset($ln['product_id']) && $ln['product_id'] !== '' ? (int)$ln['product_id'] : null,
                        'description' => $ln['description'] ?? ($ln['product_name'] ?? null),
                        'quantity' => (float)($ln['quantity'] ?? 0),
                        'unit_price' => (float)($ln['unit_price'] ?? 0),
                    ];
                }
            }

            $db = \Config\Database::connect();
            $db->transStart();

            // Order number: use configured sequential strategy for consistency.
            $prefix = (string)($this->getDocPrefix('sales_order') ?? 'RI');
            $orderNo = $this->generateNextDocNumber('sales_order', $prefix);

            // Calculate totals from lines
            $subtotal = 0.0;
            foreach ($linesIn as $ln) {
                $subtotal += (float)$ln['quantity'] * (float)$ln['unit_price'];
            }
            $taxTotal = 0.0; // keep simple for now (tax per line is not modeled in sales_order_lines)
            $total = round($subtotal + $taxTotal + $shippingAmount, 2);

            // Schema-safe insert for optional shipping column
            try { $orderCols = $db->getFieldNames('sales_orders'); } catch (\Throwable $_) { $orderCols = []; }

            $header = [
                'order_number' => $orderNo,
                'customer_id' => $customerId,
                'order_date' => $orderDate,
                'subtotal' => round($subtotal, 2),
                'tax_total' => round($taxTotal, 2),
                'total' => $total,
                'created_by' => session()->get('user_id') ?? null,
            ];
            if (!empty($orderCols)) {
                if (in_array('currency', $orderCols)) $header['currency'] = $currency;
                if (in_array('currency_code', $orderCols)) $header['currency_code'] = $currency;
                if (in_array('shipping_amount', $orderCols)) $header['shipping_amount'] = round($shippingAmount, 2);
                elseif (in_array('shipping_cost', $orderCols)) $header['shipping_cost'] = round($shippingAmount, 2);
            }

            $orderId = $this->model->insert($header);
            if (!$orderId) throw new \RuntimeException('Failed to create order');

            foreach ($linesIn as $ln) {
                if (empty($ln['description']) && empty($ln['product_id'])) continue;
                $qty = (float)($ln['quantity'] ?? 0);
                $price = (float)($ln['unit_price'] ?? 0);
                $this->lineModel->insert([
                    'sales_order_id' => $orderId,
                    'product_id' => $ln['product_id'],
                    'description' => $ln['description'] ?? '',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => round($qty * $price, 2),
                ]);
            }

            $db->transComplete();
            if ($db->transStatus() === false) throw new \RuntimeException('Transaction failed');

            DocumentLogger::log(DocumentLogger::TYPE_SALES_ORDER, (int)$orderId, DocumentLogger::ACTION_CREATED);
            foreach ($linesIn as $ln) {
                $pName = trim((string)($ln['description'] ?? ''));
                DocumentLogger::log(DocumentLogger::TYPE_SALES_ORDER, (int)$orderId, DocumentLogger::ACTION_LINE_ADDED, [
                    'product' => $pName ?: ('Product #' . ($ln['product_id'] ?? '')),
                    'qty'     => $ln['quantity'] ?? null,
                    'price'   => isset($ln['unit_price']) ? number_format((float)$ln['unit_price'], 2) : null,
                ]);
            }

            if ($isAjax) {
                return $this->response->setJSON(['success' => true, 'id' => (int)$orderId]);
            }
            return redirect()->to('/sales-orders/view/' . $orderId)->with('success', 'Sales order created');
        } catch (\Throwable $e) {
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
            return redirect()->back()->withInput()->with('error', 'Failed to create order: ' . $e->getMessage());
        }
    }

    // Create an invoice from a sales order
    public function invoice($orderId)
    {
        $order = $this->model->find($orderId);
        if (!$order) return redirect()->back()->with('error','Order not found');

        // assemble invoice data and call DualInvoiceService
        $db = \Config\Database::connect();
        $lines = $db->table('sales_order_lines')->where('sales_order_id',$orderId)->get()->getResultArray();

        $soNumber = $order['order_number'] ?? ('SO-' . ($order['id'] ?? '')); 

        $header = [
            'invoice_number' => 'INV-' . $soNumber,
            'customer_id' => $order['customer_id'],
            'issue_date' => $order['order_date'] ?? date('Y-m-d'),
            'due_date' => date('Y-m-d', strtotime('+30 days', strtotime($order['order_date'] ?? 'now'))),
            'subtotal' => $order['subtotal'],
            'tax_total' => $order['tax_total'],
            'total_amount' => $order['total'],
            'created_by' => session()->get('user_id') ?? null,
        ];

        // Preserve linkage for traceability and correct tax/discount rate sourcing (schema-safe: only when column exists)
        try {
            $ciCols = $db->getFieldNames('customer_invoices');
            if (in_array('sales_order_id', $ciCols)) {
                $header['sales_order_id'] = (int)$orderId;
            }
        } catch (\Throwable $_) {
            // ignore
        }

        $currency = $order['currency_code'] ?? ($order['currency'] ?? $this->getDefaultSalesCurrency());
        $header['currency_code'] = $currency;
        if (isset($order['shipping_amount']) || isset($order['shipping_cost'])) {
            $header['shipping_cost'] = $order['shipping_amount'] ?? ($order['shipping_cost'] ?? 0);
        }

        // sales_order_lines table may not store per-line discount/tax. If so, allocate from header totals.
        $sumBase = 0.0;
        foreach ($lines as $l) {
            $sumBase += (float)($l['quantity'] ?? 0) * (float)($l['unit_price'] ?? 0);
        }
        $sumBase = $sumBase > 0 ? $sumBase : 1.0;

        // If the Sales Order was converted from a quotation, we can preserve the *intended* per-line tax_rate
        // from quotation_lines (e.g., 12%) instead of deriving a rate from allocated tax_amount.
        $quoteTaxRateByProduct = [];
        try {
            if (!empty($order['quotation_id'])) {
                $qLines = $db->table('quotation_lines')->where('quotation_id', (int)$order['quotation_id'])->get()->getResultArray();
                foreach ($qLines as $ql) {
                    $pid = (int)($ql['product_id'] ?? 0);
                    if ($pid > 0 && isset($ql['tax_rate'])) {
                        $quoteTaxRateByProduct[$pid] = (float)$ql['tax_rate'];
                    }
                }
            }
        } catch (\Throwable $_) {
            $quoteTaxRateByProduct = [];
        }

    // Derive order-level discount from header fields if not explicitly stored.
    $orderSubtotal = (float)($order['subtotal'] ?? 0);
    $orderTaxTotal = (float)($order['tax_total'] ?? 0);
    $orderShipping = (float)($order['shipping_amount'] ?? ($order['shipping_cost'] ?? 0));
    $orderTotal = (float)($order['total'] ?? 0);
    // discount = (subtotal + tax + shipping) - total (if positive)
    $orderDiscountTotal = ($orderSubtotal + $orderTaxTotal + $orderShipping) - $orderTotal;
    if ($orderDiscountTotal < 0) $orderDiscountTotal = 0.0;

        $invLines = [];
        foreach ($lines as $l) {
            $qty = (float)($l['quantity'] ?? 0);
            $price = (float)($l['unit_price'] ?? 0);

            $base = $qty * $price;
            $share = $base / $sumBase;

            // Allocate per-line discount/tax from header totals when line columns are missing.
            $discountAmt = round($orderDiscountTotal * $share, 2);
            $taxable = max(0, $base - $discountAmt);
            $taxAmt = round($orderTaxTotal * ($taxable / max(1.0, ($sumBase - $orderDiscountTotal))), 2);

            $discountType = 'percent';
            $discountVal = $base > 0 ? round(($discountAmt / $base) * 100.0, 2) : null;
            $pid = (int)($l['product_id'] ?? 0);
            $taxRate = isset($quoteTaxRateByProduct[$pid]) ? round((float)$quoteTaxRateByProduct[$pid], 2) : ($taxable > 0 ? round(($taxAmt / $taxable) * 100.0, 2) : 0.0);

            $lineTotal = ($base - $discountAmt + $taxAmt);
            $invLines[] = [
                'product_code' => $l['product_code'] ?? null,
                'product_id' => $l['product_id'],
                'description' => $l['description'],
                'unit' => $l['unit'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_value' => $discountVal,
                'discount_amount' => $discountAmt,
                'discount_type' => $discountType,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmt,
                'line_total' => $lineTotal
            ];
        }

        // Prevent duplicate invoices: if sales_orders.invoice_id exists or an invoice with same number exists, redirect to it
        try {
            $db = \Config\Database::connect();
            $soCols = $db->getFieldNames('sales_orders');
            if (in_array('invoice_id', $soCols) && !empty($order['invoice_id'])) {
                return redirect()->to('/customer-invoices/view/' . (int)$order['invoice_id'])->with('info', 'Invoice already exists for this sales order');
            }
            // check by invoice_number too
            $existing = $db->table('customer_invoices')->where('invoice_number', $header['invoice_number'])->limit(1)->get()->getRowArray();
            if (!empty($existing)) {
                // link back if sales_orders has invoice_id column
                if (in_array('invoice_id', $soCols)) {
                    try { $db->table('sales_orders')->where('id', $orderId)->update(['invoice_id' => $existing['id']]); } catch (\Throwable $_) {}
                }
                return redirect()->to('/customer-invoices/view/' . (int)$existing['id'])->with('info', 'Invoice already exists for this sales order');
            }
        } catch (\Throwable $_) {
            // ignore schema errors and proceed to create
        }

        try {
            $invoice = $this->invoiceService->createSystemInvoice(['header' => $header, 'lines' => $invLines]);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to create invoice: ' . $e->getMessage());
        }

        if (!empty($invoice['id'])) {
            // Link invoice back to sales order and link invoice header to sales order when schema supports it
            try {
                $db = \Config\Database::connect();
                $cols = $db->getFieldNames('sales_orders');
                if (in_array('invoice_id', $cols)) {
                    $db->table('sales_orders')->where('id', $orderId)->update(['invoice_id' => $invoice['id']]);
                }

                // Also update the invoice header to reference the originating sales order when column exists
                $invCols = $db->getFieldNames('customer_invoices');
                if (in_array('sales_order_id', $invCols)) {
                    $db->table('customer_invoices')->where('id', $invoice['id'])->update(['sales_order_id' => $orderId]);
                }
            } catch (\Throwable $_) {
                // best effort: ignore failures to keep flow robust
            }

            return redirect()->to('/customer-invoices/view/' . $invoice['id'])->with('success','Invoice created from order');
        }
        return redirect()->back()->with('error','Failed to create invoice');
    }

    /**
     * Cancel a sales order (status -> cancelled if column exists)
     */
    public function cancel($id = null)
    {
        $order = $this->model->findByPublicIdOrId($id);
        if (!$order) return redirect()->back()->with('error','Order not found');
        $id = (int)$order['id'];

        $db = \Config\Database::connect();
        try { $cols = $db->getFieldNames('sales_orders'); } catch (\Throwable $_) { $cols = []; }
        $upd = [];
        if (in_array('status', $cols)) $upd['status'] = 'cancelled';
        if (!empty($upd)) {
            $db->table('sales_orders')->where('id', $id)->update($upd);
        }
        DocumentLogger::log(DocumentLogger::TYPE_SALES_ORDER, $id, DocumentLogger::ACTION_CANCELLED);
        return redirect()->to('/sales-orders/view/' . $id)->with('success','Sales order cancelled');
    }

    /**
     * Phase-2: Auto-Create RFQ Drafts from Sales Order
     * Creates draft RFQs for items with stock shortages, grouped by vendor
     */
    public function createPurchaseDrafts($id = null)
    {
        $this->requireAuth();
        $this->requirePermission('sales_orders.edit');

        $isAjax = $this->request->isAJAX() || strpos((string)$this->request->getHeaderLine('Accept'), 'application/json') !== false;
        $salesOrderId = (int)$id;
        if (!$salesOrderId) {
            if ($isAjax) {
                return $this->response->setStatusCode(400)->setJSON([
                    'success' => false,
                    'message' => 'Invalid sales order ID',
                ]);
            }
            return redirect()->back()->with('error', 'Invalid sales order ID');
        }

        try {
            // Use AutoPurchaseSuggestionService to create RFQ drafts
            $service = new \App\Services\AutoPurchaseSuggestionService();
            $userId = session()->get('user_id') ?? 1;

            $result = $service->createDraftRFQsFromSalesOrder($salesOrderId, $userId);
            if (!empty($result['missing_vendor_items']) && is_array($result['missing_vendor_items'])) {
                foreach ($result['missing_vendor_items'] as &$item) {
                    $productId = (int)($item['product_id'] ?? 0);
                    if ($productId > 0) {
                        $item['edit_url'] = site_url('products/' . $productId . '/edit');
                    }
                }
                unset($item);
            }

            if ($result['success']) {
                $count = count($result['created_pos'] ?? []);
                $message = $count > 0
                    ? "Created {$count} RFQ draft(s) successfully. Review and submit them from Purchases > RFQs."
                    : 'No RFQ drafts needed (all items have sufficient stock).';

                if ($isAjax) {
                    return $this->response->setJSON([
                        'success' => true,
                        'message' => $message,
                        'created_count' => $count,
                        'created_rfqs' => $result['created_pos'] ?? [],
                    ]);
                }

                return redirect()->to('/sales-orders/view/' . $salesOrderId)->with('success', $message);
            }

            $errorMsg = $result['message'] ?? 'Failed to create RFQ drafts';
            if ($isAjax) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'message' => $errorMsg,
                    'missing_vendor_items' => $result['missing_vendor_items'] ?? [],
                ]);
            }

            $redirect = redirect()->to('/sales-orders/view/' . $salesOrderId)->with('error', $errorMsg);
            if (!empty($result['missing_vendor_items']) && is_array($result['missing_vendor_items'])) {
                $redirect = $redirect->with('missing_vendor_items', $result['missing_vendor_items']);
            }
            return $redirect;
        } catch (\Throwable $e) {
            log_message('error', 'Failed to create purchase drafts: ' . $e->getMessage());
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON([
                    'success' => false,
                    'message' => 'Failed to create RFQ drafts: ' . $e->getMessage(),
                ]);
            }
            return redirect()->to('/sales-orders/view/' . $salesOrderId)
                ->with('error', 'Failed to create RFQ drafts: ' . $e->getMessage());

        }
    }

            public function printView($orderId = null)
            {
                $order = $this->model->findByPublicIdOrId($orderId);
                if (!$order) {
                    return redirect()->back()->with('error', 'Sales order not found');
                }
                $orderId = (int)$order['id'];

                $db = \Config\Database::connect();
                $customer = [];
                try {
                    $customer = $db->table('customers')->where('id', (int)($order['customer_id'] ?? 0))->get()->getRowArray() ?: [];
                } catch (\Throwable $_) {}

                $lines = $this->lineModel->where('sales_order_id', $orderId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

                $quoteLines = [];
                if (!empty($order['quotation_id'])) {
                    try {
                        $quoteWithLines = $this->quotationModel->getWithLines((int) $order['quotation_id']);
                        if (!empty($quoteWithLines['lines']) && is_array($quoteWithLines['lines'])) {
                            $quoteLines = $quoteWithLines['lines'];
                        }
                    } catch (\Throwable $_) {
                        $quoteLines = [];
                    }
                }

                try {
                    // Recover missing product/variant references from the linked quotation when SO lines are legacy/minimal.
                    if (!empty($quoteLines)) {
                        $quoteBuckets = [];
                        foreach ($quoteLines as $quoteLine) {
                            if (isset($quoteLine['display_type']) && $quoteLine['display_type'] === 'section') {
                                continue;
                            }
                            $qDesc = strtolower(trim((string) ($quoteLine['description'] ?? '')));
                            $qQty = (float) ($quoteLine['quantity'] ?? 0);
                            $qPrice = (float) ($quoteLine['unit_price'] ?? 0);
                            $qTotal = isset($quoteLine['line_total']) ? (float) $quoteLine['line_total'] : ($qQty * $qPrice);
                            $qKey = $qDesc . '|' . number_format($qQty, 2, '.', '') . '|' . number_format($qPrice, 2, '.', '') . '|' . number_format($qTotal, 2, '.', '');
                            if (!isset($quoteBuckets[$qKey])) {
                                $quoteBuckets[$qKey] = [];
                            }
                            $quoteBuckets[$qKey][] = $quoteLine;
                        }

                        foreach ($lines as &$line) {
                            $lineProductId = isset($line['product_id']) ? (int) $line['product_id'] : 0;
                            $lineVariantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : 0;
                            if ($lineProductId > 0 || $lineVariantId > 0) {
                                continue;
                            }

                            $lDesc = strtolower(trim((string) ($line['description'] ?? '')));
                            $lQty = (float) ($line['quantity'] ?? 0);
                            $lPrice = (float) ($line['unit_price'] ?? 0);
                            $lTotal = isset($line['line_total']) ? (float) $line['line_total'] : ($lQty * $lPrice);
                            $lKey = $lDesc . '|' . number_format($lQty, 2, '.', '') . '|' . number_format($lPrice, 2, '.', '') . '|' . number_format($lTotal, 2, '.', '');

                            if (empty($quoteBuckets[$lKey])) {
                                continue;
                            }

                            $src = array_shift($quoteBuckets[$lKey]);
                            $srcProductId = isset($src['product_id']) ? (int) $src['product_id'] : 0;
                            $srcVariantId = isset($src['product_variant_id']) ? (int) $src['product_variant_id'] : 0;
                            if ($srcProductId > 0) {
                                $line['product_id'] = $srcProductId;
                            }
                            if ($srcVariantId > 0) {
                                $line['product_variant_id'] = $srcVariantId;
                            }

                            $srcCode = trim((string) ($src['product_code'] ?? ''));
                            $srcName = trim((string) ($src['product_name'] ?? ''));
                            $srcVariantName = trim((string) ($src['variant_name'] ?? ''));
                            $srcImageUrl = trim((string) ($src['product_image_url'] ?? ''));
                            if ($srcCode !== '') {
                                $line['product_code'] = $srcCode;
                            }
                            if ($srcName !== '') {
                                $line['product_name'] = $srcName;
                            }
                            if ($srcVariantName !== '' && empty($line['variant_name'])) {
                                $line['variant_name'] = $srcVariantName;
                            }
                            if ($srcImageUrl !== '' && empty($line['product_image'])) {
                                $line['product_image'] = $srcImageUrl;
                            }
                        }
                        unset($line);
                    }

                    $productIds = array_values(array_filter(array_unique(array_map(function ($line) {
                        return isset($line['product_id']) ? (int) $line['product_id'] : null;
                    }, $lines))));
                    $variantIds = array_values(array_filter(array_unique(array_map(function ($line) {
                        if (isset($line['product_variant_id']) && $line['product_variant_id']) {
                            return (int) $line['product_variant_id'];
                        }
                        return null;
                    }, $lines))));
                    $lineCodes = array_values(array_filter(array_unique(array_map(static function ($line) {
                        $code = trim((string) ($line['product_code'] ?? ''));
                        return $code !== '' ? $code : null;
                    }, $lines))));

                    $prodMap = [];
                    $variantMap = [];
                    $variantMapByArt = [];

                    if (!empty($productIds)) {
                        $productModel = new \App\Models\ProductModel();
                        $products = $productModel->whereIn('id', $productIds)->findAll();
                        foreach ($products as $product) {
                            $prodMap[(int) $product['id']] = $product;
                        }
                    }

                    if (($db->tableExists('product_variants')) && (!empty($variantIds) || !empty($lineCodes))) {
                        try {
                            $variantBuilder = $db->table('product_variants')
                                ->select('id, product_id, art_number, name, image');
                            if (!empty($variantIds) && !empty($lineCodes)) {
                                $variantBuilder->groupStart()->whereIn('id', $variantIds)->orWhereIn('art_number', $lineCodes)->groupEnd();
                            } elseif (!empty($variantIds)) {
                                $variantBuilder->whereIn('id', $variantIds);
                            } else {
                                $variantBuilder->whereIn('art_number', $lineCodes);
                            }
                            $variants = $variantBuilder->get()->getResultArray();
                            foreach ($variants as $variant) {
                                $variantMap[(int) $variant['id']] = $variant;
                                $art = strtoupper(trim((string) ($variant['art_number'] ?? '')));
                                if ($art !== '') {
                                    $variantMapByArt[$art] = $variant;
                                }
                            }

                            // Some lines only keep variant_id; backfill missing product rows via variant->product_id.
                            $variantProductIds = [];
                            foreach ($variantMap as $variantRow) {
                                $variantPid = isset($variantRow['product_id']) ? (int) $variantRow['product_id'] : 0;
                                if ($variantPid > 0 && !isset($prodMap[$variantPid])) {
                                    $variantProductIds[] = $variantPid;
                                }
                            }
                            $variantProductIds = array_values(array_unique($variantProductIds));
                            if (!empty($variantProductIds)) {
                                $extraProducts = $productModel->whereIn('id', $variantProductIds)->findAll();
                                foreach ($extraProducts as $product) {
                                    $prodMap[(int) $product['id']] = $product;
                                }
                            }
                        } catch (\Throwable $_) {}
                    }

                    foreach ($lines as &$line) {
                        $originalMissingRefs = ((int) ($line['product_id'] ?? 0) <= 0 && (int) ($line['product_variant_id'] ?? 0) <= 0);
                        $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                        $variantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;
                        $lineCode = strtoupper(trim((string) ($line['product_code'] ?? '')));

                        if ((!$variantId || $variantId <= 0) && $lineCode !== '' && isset($variantMapByArt[$lineCode])) {
                            $variantId = (int) ($variantMapByArt[$lineCode]['id'] ?? 0);
                            if ($variantId > 0) {
                                $line['product_variant_id'] = $variantId;
                            }
                        }

                        if ((!$productId || $productId <= 0) && $variantId && isset($variantMap[$variantId])) {
                            $productId = isset($variantMap[$variantId]['product_id']) ? (int) $variantMap[$variantId]['product_id'] : null;
                            if ($productId) {
                                $line['product_id'] = $productId;
                            }
                        }

                        if ($productId && isset($prodMap[$productId])) {
                            $product = $prodMap[$productId];
                            $line['product_name'] = $product['name'] ?? null;
                            $line['product_code'] = $product['code'] ?? ($product['sku'] ?? null);
                            $line['product_unit'] = $product['unit'] ?? null;
                            $line['product_image'] = $product['image'] ?? null;
                            $line['product_images'] = $product['images'] ?? null;
                        }

                        if ($variantId && isset($variantMap[$variantId])) {
                            $variant = $variantMap[$variantId];
                            $line['variant_code'] = $variant['art_number'] ?? null;
                            $line['variant_name'] = $variant['name'] ?? null;
                            $line['variant_image'] = $variant['image'] ?? null;
                            if (empty($line['product_code']) && !empty($line['variant_code'])) {
                                $line['product_code'] = $line['variant_code'];
                            }
                        }

                        $lineDesc = trim((string)($line['description'] ?? ''));
                        $lineProductName = trim((string)($line['product_name'] ?? ''));
                        $lineVariantName = trim((string)($line['variant_name'] ?? ''));
                        $looksGenericDesc = ($lineDesc !== '' && strpos($lineDesc, '/') === false && $lineVariantName !== '' && strcasecmp($lineDesc, $lineVariantName) !== 0);
                        if ($lineVariantName !== '' && ($lineDesc === '' || strcasecmp($lineDesc, $lineProductName) === 0 || $looksGenericDesc)) {
                            $line['description'] = $lineVariantName;
                        }

                        if ($originalMissingRefs) {
                            $lineId = (int) ($line['id'] ?? 0);
                            if (!empty($line['product_code']) || !empty($line['variant_code'])) {
                                log_message('warning', 'SalesOrders::printView recovered missing product references for sales_order_line_id=' . $lineId . ' sales_order_id=' . $orderId . ' using quotation/code fallback');
                            } else {
                                log_message('warning', 'SalesOrders::printView unresolved product references for sales_order_line_id=' . $lineId . ' sales_order_id=' . $orderId . ' (missing product_id/product_variant_id)');
                            }
                        }
                    }
                    unset($line);
                } catch (\Throwable $_) {}

                $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];
                $printLines = [];
                foreach ($lines as $line) {
                    if (isset($line['display_type']) && $line['display_type'] === 'section') {
                        continue;
                    }
                    $qty = (float) ($line['quantity'] ?? ($line['qty'] ?? 0));
                    $price = (float) ($line['unit_price'] ?? 0);
                    $storedTotal = isset($line['line_total']) ? (float) $line['line_total'] : 0.0;
                    $total = $storedTotal > 0 ? $storedTotal : ($qty * $price);
                    $code = trim((string) ($line['variant_code'] ?? ($line['product_code'] ?? '')));
                    $lineDescription = trim((string) ($line['description'] ?? ''));
                    $lineProductName = trim((string) ($line['product_name'] ?? ($line['name'] ?? '')));
                    $lineVariantName = trim((string) ($line['variant_name'] ?? ''));
                    if ($lineDescription !== '' && $lineProductName !== '') {
                        $desc = stripos($lineDescription, $lineProductName) !== false
                            ? $lineDescription
                            : ($lineProductName . ' - ' . $lineDescription);
                    } else {
                        $desc = $lineProductName !== ''
                            ? $lineProductName
                            : ($lineDescription !== '' ? $lineDescription : $lineVariantName);
                    }
                    $unit = trim((string) ($line['product_unit'] ?? ($line['unit'] ?? '')));

                    $imgSrc = '';
                    $imageCandidates = [];
                    foreach ([
                        $line['variant_image'] ?? '',
                        $line['product_image'] ?? '',
                    ] as $imgRaw) {
                        $imgRaw = trim((string) $imgRaw);
                        if ($imgRaw === '') {
                            continue;
                        }
                        $norm = ltrim($imgRaw, '/\\');
                        $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $norm);
                        $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'variants' . DIRECTORY_SEPARATOR . basename($norm);
                        $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($norm);
                    }
                    if (empty($imageCandidates) && !empty($line['product_images'])) {
                        $images = is_string($line['product_images']) ? json_decode($line['product_images'], true) : $line['product_images'];
                        if (is_array($images) && !empty($images[0])) {
                            $norm = ltrim((string) $images[0], '/\\');
                            $imageCandidates[] = rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($norm);
                        }
                    }
                    foreach (array_unique($imageCandidates) as $abs) {
                        if (!is_file($abs)) {
                            continue;
                        }
                        $raw = @file_get_contents($abs);
                        if ($raw === false) {
                            continue;
                        }
                        $ext = strtolower(pathinfo($abs, PATHINFO_EXTENSION));
                        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'image/jpeg';
                        $imgSrc = 'data:' . $mime . ';base64,' . base64_encode($raw);
                        break;
                    }

                    $printLines[] = compact('code', 'desc', 'imgSrc', 'qty', 'price', 'total', 'unit');
                }

                $currency = strtoupper(trim((string) ($order['currency'] ?? $this->getDefaultSalesCurrency())));
                $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
                $sym = $symbols[$currency] ?? $currency;
                $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

                $subtotal = (float) ($order['subtotal'] ?? 0);
                $shipping = (float) ($order['shipping_amount'] ?? ($order['shipping_cost'] ?? 0));
                $total = (float) ($order['total'] ?? 0);
                $orderNumber = esc($order['order_number'] ?? ('SO-' . $orderId));
                $orderDate = '';
                $rawDate = trim((string) ($order['order_date'] ?? ($order['created_at'] ?? '')));
                if ($rawDate && strpos($rawDate, '0000') === false) {
                    $ts = strtotime($rawDate);
                    if ($ts) {
                        $orderDate = date('d-m-Y', $ts);
                    }
                }
                $customerName = esc($customer['name'] ?? 'Customer');
                $companyName = esc($company['name'] ?? '');

                ob_start();
                ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>Sales Order <?= $orderNumber ?></title>
        <style>
          *{box-sizing:border-box;margin:0;padding:0}
          body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
          .grn-doc{max-width:1100px;margin:0 auto}
          .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
          .grn-hero::after{content:'SO';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:7rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
          .grn-doc-type{display:inline-flex;align-items:center;gap:.4rem;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:2rem;padding:.22rem .8rem;font-size:.7rem;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#93c5fd;margin-bottom:.55rem}
          .grn-hero-num{font-size:1.85rem;font-weight:800;letter-spacing:-.01em;line-height:1.1;margin-bottom:.25rem}
          .grn-hero-sub{font-size:.82rem;color:rgba(255,255,255,.72)}
          .grn-hero-actions{position:absolute;top:1.05rem;right:1.1rem;display:flex;gap:.4rem;flex-wrap:wrap;justify-content:flex-end;max-width:56%}
          .grn-hero-btn{display:inline-flex;align-items:center;gap:.34rem;background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.24);border-radius:.42rem;padding:.34rem .7rem;font-size:.75rem;font-weight:700;color:rgba(255,255,255,.88);text-decoration:none;transition:background .15s,border-color .15s;cursor:pointer}
          .grn-hero-btn:hover{background:rgba(255,255,255,.18);border-color:rgba(255,255,255,.42);color:#fff}
          .grn-facts{background:#fff;border:1px solid #dee2e6;border-top:none;display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr))}
          .grn-fact{padding:.75rem 1rem;border-right:1px solid #dee2e6}.grn-fact:last-child{border-right:none}
          .grn-fact-lbl{font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b;font-weight:700;margin-bottom:.18rem}
          .grn-fact-val{font-size:.95rem;font-weight:700;color:#1e293b}
          .grn-sec{background:#fff;border:1px solid #dee2e6;border-top:none}
          .grn-sec-hd{padding:.7rem 1.3rem;border-bottom:1px solid #dee2e6;display:flex;align-items:center;gap:.55rem;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:#6c757d}
          .grn-sec-badge{margin-left:auto;background:#e0e7ff;color:#3730a3;border-radius:2rem;padding:.08rem .5rem;font-size:.68rem;font-weight:700}
          .grn-body{padding:0 1.1rem 1rem}.grn-tbl{width:100%;border-collapse:collapse}
          .grn-tbl thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%);border-bottom:2px solid #dbe5f0;padding:.72rem .65rem;text-align:left;font-size:.68rem;text-transform:uppercase;letter-spacing:.06em;color:#64748b}
          .grn-tbl tbody td{padding:.75rem .65rem;border-bottom:1px solid #eef2f7;vertical-align:middle;font-size:.84rem}.grn-tbl .r{text-align:right}
          .prod-code{display:inline-flex;align-items:center;padding:.15rem .45rem;border-radius:999px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8;font-size:.72rem;font-weight:700}
          .prod-thumb{width:42px;height:42px;object-fit:contain;border:1px solid #dbe5f0;border-radius:.35rem;background:#fff}
          .no-img{font-size:.68rem;color:#94a3b8;border:1px dashed #cbd5e1;padding:.18rem .35rem;border-radius:.25rem;display:inline-block}
          .desc-main{font-weight:700;color:#1e293b;line-height:1.45}
          .totals{padding:1rem 1.1rem 1.2rem;display:flex;justify-content:flex-end;background:#fff;border:1px solid #dee2e6;border-top:none;border-radius:0 0 .75rem .75rem}
          .totals table{width:280px;border-collapse:collapse}.totals td{padding:.33rem .2rem}.totals .lbl{color:#64748b;text-align:right;padding-right:.8rem}.totals .val{text-align:right}.totals .grand td{font-size:1.08rem;font-weight:700;border-top:2px solid #1e293b;padding-top:.55rem;color:#111827}
          @media print{*{color-adjust:exact!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}body{padding:12mm;background:#fff!important;color:#1e293b!important}.no-print,.grn-hero-actions{display:none!important}.grn-doc{max-width:1100px!important;margin:0 auto!important}.grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%)!important;border-radius:.75rem!important;color:#fff!important;border:1px solid #0a0f1a!important;page-break-inside:avoid!important}.grn-hero-num,.grn-hero-sub,.grn-doc-type{color:#fff!important}.grn-doc-type{background:rgba(255,255,255,.12)!important;border:1px solid rgba(255,255,255,.18)!important;color:#93c5fd!important}.grn-facts{background:#fff!important;border:1px solid #dee2e6!important;border-radius:0!important;page-break-inside:avoid!important}.grn-fact{border-right:1px solid #dee2e6!important;background:#fff!important}.grn-fact-lbl{color:#64748b!important}.grn-fact-val{color:#1e293b!important}.grn-sec{background:#fff!important;border:1px solid #dee2e6!important;border-radius:0!important}.grn-sec-hd{background:#f8fafc!important;color:#6c757d!important;border-bottom:1px solid #dee2e6!important}.grn-sec-badge{background:#e0e7ff!important;color:#3730a3!important;border-radius:2rem!important}.grn-body{background:#fff!important}.grn-tbl{width:100%!important;border-collapse:collapse!important;page-break-inside:avoid!important}.grn-tbl thead th{background:linear-gradient(180deg,#f8fafc 0%,#eef2f7 100%)!important;border-bottom:2px solid #dbe5f0!important;color:#64748b!important;text-align:left!important}.grn-tbl tbody td{border-bottom:1px solid #eef2f7!important;color:#1e293b!important;background:#fff!important}.grn-tbl tbody tr{background:#fff!important;page-break-inside:avoid!important}.prod-code{background:#eff6ff!important;border:1px solid #bfdbfe!important;color:#1d4ed8!important;border-radius:999px!important}.prod-thumb{border:1px solid #dbe5f0!important;background:#fff!important}.no-img{color:#94a3b8!important;border:1px dashed #cbd5e1!important;background:#fff!important}.desc-main{color:#1e293b!important;font-weight:700!important}.totals{background:#fff!important;border:1px solid #dee2e6!important;border-radius:.75rem!important;display:flex!important;justify-content:flex-end!important;page-break-inside:avoid!important}.totals table{border-collapse:collapse!important;width:280px!important}.totals td{color:#1e293b!important}.totals .lbl{color:#64748b!important}.totals .grand td{color:#111827!important;border-top:2px solid #1e293b!important;font-weight:700!important}table,thead,tbody,tr,td,th{page-break-inside:avoid!important;break-inside:avoid!important}}
          @media(max-width:768px){body{padding:12px}.grn-hero{padding:1rem 1rem .9rem}.grn-hero-num{font-size:1.3rem}.grn-hero-actions{position:static;max-width:100%;margin-top:.7rem;justify-content:flex-start}.grn-facts{grid-template-columns:1fr 1fr}.grn-fact{padding:.5rem .6rem}.grn-body{padding:0}.grn-tbl{display:block;overflow-x:auto}}
        </style>
        </head>
        <body>
        <div class="grn-doc">
          <div class="grn-hero">
            <div class="grn-doc-type">Sales Order</div>
            <div class="grn-hero-num"><?= $orderNumber ?></div>
            <div class="grn-hero-sub"><?= $companyName ?></div>
            <div class="grn-hero-actions no-print">
              <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
              <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
            </div>
          </div>

          <div class="grn-facts">
            <div class="grn-fact"><div class="grn-fact-lbl">Customer</div><div class="grn-fact-val"><?= $customerName ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Order Date</div><div class="grn-fact-val"><?= esc($orderDate ?: '-') ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
          </div>

          <div class="grn-sec">
            <div class="grn-sec-hd">Order Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
            <div class="grn-body">
              <table class="grn-tbl">
                <thead>
                  <tr>
                    <th style="width:13%">Code</th>
                    <th style="width:8%">Image</th>
                    <th>Description</th>
                    <th style="width:8%">Unit</th>
                    <th class="r" style="width:8%">Qty</th>
                    <th class="r" style="width:12%">Unit Price</th>
                    <th class="r" style="width:12%">Line Total</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($printLines as $line): ?>
                  <tr>
                    <td><span class="prod-code"><?= esc($line['code'] !== '' ? $line['code'] : '-') ?></span></td>
                    <td><?php if ($line['imgSrc']): ?><img class="prod-thumb" src="<?= $line['imgSrc'] ?>" alt=""><?php else: ?><span class="no-img">No Img</span><?php endif ?></td>
                    <td><div class="desc-main"><?= esc($line['desc'] !== '' ? $line['desc'] : '-') ?></div></td>
                    <td><?= esc($line['unit'] !== '' ? $line['unit'] : '-') ?></td>
                    <td class="r"><?= number_format($line['qty'], 2) ?></td>
                    <td class="r"><?= esc($fmt($line['price'])) ?></td>
                    <td class="r"><?= esc($fmt($line['total'])) ?></td>
                  </tr>
                  <?php endforeach ?>
                </tbody>
              </table>
            </div>
          </div>

          <div class="totals">
            <table>
              <tr><td class="lbl">Subtotal</td><td class="val"><?= esc($fmt($subtotal > 0 ? $subtotal : $total)) ?></td></tr>
                            <tr><td class="lbl">Shipping</td><td class="val"><?= esc($fmt($shipping)) ?></td></tr>
              <tr class="grand"><td class="lbl">Total</td><td class="val"><?= esc($fmt($total)) ?></td></tr>
            </table>
          </div>
        </div>
        </body>
        </html>
                <?php
                return $this->response->setBody(ob_get_clean())->setHeader('Content-Type', 'text/html; charset=utf-8');
            }
}
