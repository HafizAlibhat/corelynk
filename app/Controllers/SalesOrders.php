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
use App\Services\PreparationPlannerService;

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
            $productMap = [];
            $variantMap = [];

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

            if (!empty($variantIds)) {
                $pvm = new ProductVariantModel();
                $variants = $pvm->whereIn('id', $variantIds)->findAll();
                foreach ($variants as $v) {
                    $variantMap[(int)$v['id']] = [
                        'weight' => $v['weight'] ?? 0,
                    ];
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

            foreach ($lines as &$ln) {
                $prod = null;
                if (!empty($ln['product_id'])) {
                    $prod = $productMap[(int)$ln['product_id']] ?? null;
                }
                $variantId = (int)($ln['product_variant_id'] ?? ($ln['variant_id'] ?? 0));
                $variant = $variantId > 0 ? ($variantMap[$variantId] ?? null) : null;
                $ln['product_code'] = $ln['product_code'] ?? ($prod['code'] ?? ($ln['product_id'] ?? ''));
                $ln['product_name'] = $ln['product_name'] ?? ($prod['name'] ?? '');
                $ln['product_image_url'] = $ln['product_image_url'] ?? ($prod['image_url'] ?? base_url('assets/images/no-image.png'));
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
            $readyData = $readyService->getLineReadiness((int)$orderId);
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

        // Phase-2: Read-only preparation planning for lines with stock shortage.
        $preparationPlans = [];
        try {
            $planner = new PreparationPlannerService();
            foreach ($data['lines'] as &$line) {
                $lineId = (int) ($line['id'] ?? 0);
                $productId = (int) ($line['product_id'] ?? 0);
                $isStockable = (bool) ($line['is_stockable'] ?? true);
                $requiredQty = array_key_exists('required_qty', $line)
                    ? (float) ($line['required_qty'] ?? 0)
                    : (float) ($line['quantity'] ?? 0);
                $availableQty = array_key_exists('available', $line)
                    ? (float) ($line['available'] ?? 0)
                    : 0.0;

                $line['preparation_plan'] = null;
                if ($lineId <= 0 || $productId <= 0 || ! $isStockable || $requiredQty <= 0) {
                    continue;
                }

                if ($availableQty < $requiredQty) {
                    $plan = $planner->generatePlan($productId, $requiredQty);
                    $line['preparation_plan'] = $plan;
                    $preparationPlans[$lineId] = $plan;
                }
            }
            unset($line);
        } catch (\Throwable $e) {
            log_message('error', 'Preparation plan generation failed: ' . $e->getMessage());
            foreach ($data['lines'] as &$line) {
                $line['preparation_plan'] = null;
            }
            unset($line);
            $preparationPlans = [];
        }

        $data['preparationPlans'] = $preparationPlans;
        $data['hasPreparationPlans'] = !empty($preparationPlans);

        try {
            $vendorModel = new \App\Models\VendorModel();
            $data['prepVendors'] = $vendorModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        } catch (\Throwable $_) {
            $data['prepVendors'] = [];
        }

        try {
            $db = \Config\Database::connect();
            $data['prepLocations'] = $db->table('warehouse_locations wl')
                ->select('wl.id, wl.name, wl.warehouse_id, w.name AS warehouse_name')
                ->join('warehouses w', 'w.id = wl.warehouse_id', 'left')
                ->where('wl.is_active', 1)
                ->orderBy('w.name', 'ASC')
                ->orderBy('wl.name', 'ASC')
                ->get()
                ->getResultArray();
        } catch (\Throwable $_) {
            $data['prepLocations'] = [];
        }

        $data['vendorSendNotesByProduct'] = [];
        try {
            $db = \Config\Database::connect();
            $lineProductIds = array_values(array_unique(array_filter(array_map(
                static fn ($ln) => (int) ($ln['product_id'] ?? 0),
                (array) ($data['lines'] ?? [])
            ))));

            if (!empty($lineProductIds)) {
                $rows = $db->table('vendor_send_notes vsn')
                    ->select('vsn.*, v.name AS vendor_name, ps.name AS step_name')
                    ->select('(vsn.qty - COALESCE((SELECT SUM(vri.qty_received) FROM vendor_receive_notes vrn INNER JOIN vendor_receive_items vri ON vri.receive_note_id = vrn.id WHERE vrn.send_note_id = vsn.id), 0)) AS remaining_qty', false)
                    ->join('vendors v', 'v.id = vsn.vendor_id', 'left')
                    ->join('preparation_steps ps', 'ps.id = vsn.step_id', 'left')
                    ->whereIn('vsn.product_id', $lineProductIds)
                    ->whereIn('vsn.status', ['sent', 'draft'])
                    ->orderBy('vsn.id', 'DESC')
                    ->get()
                    ->getResultArray();

                $map = [];
                foreach ($rows as $row) {
                    if ((float) ($row['remaining_qty'] ?? 0) <= 0) {
                        continue;
                    }
                    $pid = (int) ($row['product_id'] ?? 0);
                    $map[$pid][] = $row;
                }
                $data['vendorSendNotesByProduct'] = $map;
            }
        } catch (\Throwable $_) {
            $data['vendorSendNotesByProduct'] = [];
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

        $data['orderedWeightKg'] = round($orderedWeightKg, 3);
        $data['estimatedShipmentWeightKg'] = round($remainingWeightKg, 3);
        
        try { $data['logEntries'] = \App\Libraries\DocumentLogger::getForDocument(\App\Libraries\DocumentLogger::TYPE_SALES_ORDER, (int)$orderId); } catch (\Throwable $_) { $data['logEntries'] = []; }

        // Check for existing draft or confirmed DO
        $data['existingDo'] = null;
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('delivery_orders')) {
                $data['existingDo'] = $db->table('delivery_orders')
                    ->select('id, do_number, status')
                    ->where('sales_order_id', (int)$orderId)
                    ->orderBy("CASE status WHEN 'delivered' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'draft' THEN 3 ELSE 4 END", '', false)
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
                'currency_code' => $order['currency'] ?? ($order['currency_code'] ?? $this->getDefaultSalesCurrency()),
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

        // Numbering: <PREFIX>-S0001 (PREFIX configurable in Settings)
        $prefix = (string)($this->getDocPrefix('sales_order') ?? 'RI');
        $orderNo = $this->generateNextDocNumber('sales_order', $prefix);

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
            // Currency: prefer quotation quote_currency/base_currency on schemas that do not have quotations.currency.
            $currency = $quote['currency'] ?? ($quote['quote_currency'] ?? ($quote['base_currency'] ?? $this->getDefaultSalesCurrency()));
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

            // Order number (same style as quotation numbering, but use SO- prefix)
            $orderNo = 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $baseOrderNo = $orderNo;
            $n = 1;
            while ($this->model->where('order_number', $orderNo)->countAllResults() > 0) {
                $n++;
                $orderNo = $baseOrderNo . '-' . $n;
            }

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
}
