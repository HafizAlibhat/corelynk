<?php

namespace App\Services;

use App\Models\SalesOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\PurchaseRfqModel;
use App\Models\PurchaseRfqLineModel;
use App\Models\ProductModel;
use App\Models\ProductVendorModel;
use App\Models\CompanySettingsModel;
use App\Services\InventoryAvailabilityService;

/**
 * Phase-2: Auto Purchase Order Draft Creation Service
 * 
 * STRICT RULES:
 * - User-initiated ONLY (never auto-trigger)
 * - Creates POs in RFQ/Draft status only
 * - NO inventory writes
 * - NO stock reservation
 * - NO PO confirmation
 * - Idempotent (prevents duplicates)
 */
class AutoPurchaseSuggestionService
{
    protected $salesOrderModel;
    protected $salesOrderLineModel;
    protected $purchaseRfqModel;
    protected $purchaseRfqLineModel;
    protected $productModel;
    protected $productVendorModel;
    protected $inventoryService;
    protected $db;
    protected $defaultPurchaseCurrency;

    public function __construct()
    {
        $this->salesOrderModel = new SalesOrderModel();
        $this->salesOrderLineModel = new SalesOrderLineModel();
        $this->purchaseRfqModel = new PurchaseRfqModel();
        $this->purchaseRfqLineModel = new PurchaseRfqLineModel();
        $this->productModel = new ProductModel();
        $this->productVendorModel = new ProductVendorModel();
        $this->inventoryService = new InventoryAvailabilityService();
        $this->db = \Config\Database::connect();
        $this->defaultPurchaseCurrency = $this->resolveDefaultPurchaseCurrency();
    }

    private function resolveDefaultPurchaseCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_purchase_currency'])) return $company['default_purchase_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
            if (!empty($company['secondary_currency'])) return $company['secondary_currency'];
        } catch (\Throwable $_) {
            // ignore
        }
        return 'PKR';
    }

    /**
     * Returns a map of product_id => true for products that have variants.
     *
     * @param array $productIds
     * @return array<int,bool>
     */
    private function getVariantRequiredProductMap(array $productIds): array
    {
        $map = [];
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds), static function ($v) {
            return $v > 0;
        })));

        if (empty($ids) || !$this->db->tableExists('product_variants')) {
            return $map;
        }

        try {
            $rows = $this->db->table('product_variants')
                ->select('product_id, COUNT(*) AS cnt')
                ->whereIn('product_id', $ids)
                ->groupBy('product_id')
                ->get()
                ->getResultArray();
            foreach ($rows as $row) {
                $pid = (int)($row['product_id'] ?? 0);
                $cnt = (int)($row['cnt'] ?? 0);
                if ($pid > 0 && $cnt > 0) {
                    $map[$pid] = true;
                }
            }
        } catch (\Throwable $_) {
            return [];
        }

        return $map;
    }

    /**
    * Create draft RFQs from Sales Order shortages
     * 
     * @param int $salesOrderId
     * @param int $userId User who initiated the action
    * @return array ['success' => bool, 'created_pos' => array, 'message' => string]
     */
    public function createDraftRFQsFromSalesOrder(int $salesOrderId, int $userId): array
    {
        // Load Sales Order
        $salesOrder = $this->salesOrderModel->find($salesOrderId);
        if (!$salesOrder) {
            return [
                'success' => false,
                'created_pos' => [],
                'message' => 'Sales Order not found.'
            ];
        }

        // Load Sales Order Lines
        $lines = $this->salesOrderLineModel
            ->where('sales_order_id', $salesOrderId)
            ->findAll();

        if (empty($lines)) {
            return [
                'success' => false,
                'created_pos' => [],
                'message' => 'No order lines found.'
            ];
        }

        $lineProductIds = array_values(array_unique(array_filter(array_map(static function ($line) {
            return isset($line['product_id']) ? (int)$line['product_id'] : 0;
        }, $lines))));
        $variantRequiredMap = $this->getVariantRequiredProductMap($lineProductIds);

        if (!empty($variantRequiredMap)) {
            $productsById = [];
            try {
                $productRows = $this->productModel->whereIn('id', array_keys($variantRequiredMap))->findAll();
                foreach ($productRows as $p) {
                    $productsById[(int)($p['id'] ?? 0)] = $p;
                }
            } catch (\Throwable $_) {
                $productsById = [];
            }

            $missingVariantItems = [];
            foreach ($lines as $line) {
                $productId = isset($line['product_id']) ? (int)$line['product_id'] : 0;
                if ($productId <= 0 || empty($variantRequiredMap[$productId])) {
                    continue;
                }

                $variantId = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;
                $orderedQty = (float)($line['quantity'] ?? 0);
                if ($variantId > 0 || $orderedQty <= 0) {
                    continue;
                }

                $prod = $productsById[$productId] ?? [];
                $label = (string)($prod['code'] ?? ($prod['name'] ?? ('Product #' . $productId)));
                $missingVariantItems[] = [
                    'line_id' => (int)($line['id'] ?? 0),
                    'product_id' => $productId,
                    'code' => $label,
                ];
            }

            if (!empty($missingVariantItems)) {
                $codes = array_values(array_unique(array_column($missingVariantItems, 'code')));
                $message = 'Cannot create RFQ. Variant required for: ' . implode(', ', array_slice($codes, 0, 8));
                if (count($codes) > 8) {
                    $message .= '...';
                }
                $message .= '. Please select a specific variant on quotation/sales-order line, then retry RFQ generation.';

                return [
                    'success' => false,
                    'created_pos' => [],
                    'message' => $message,
                    'missing_variant_items' => $missingVariantItems,
                ];
            }
        }

        // Recompute availability and detect shortages
        $shortageLines = [];
        $missingVendorProducts = [];
        $skippedNonStockable = 0;
        
        foreach ($lines as $line) {
            $productId = $line['product_id'] ?? null;
            $variantId = $line['product_variant_id'] ?? null;
            $orderedQty = (float)($line['quantity'] ?? 0);

            if (!$productId || $orderedQty <= 0) {
                continue;
            }

            // Get availability
            $avail = $this->inventoryService->getAvailability($productId, $variantId);
            
            // Skip non-stockable products
            if ($avail === null) {
                $skippedNonStockable++;
                continue;
            }

            // Resolve vendor for every stockable line (strict guard before auto generation)
            $product = $this->productModel->find($productId);
            $vendorId = null;
            $variantData = null;

            if ($variantId) {
                $variantData = $this->db->table('product_variants')
                    ->select('vendor_id, cost')
                    ->where('id', $variantId)
                    ->get()
                    ->getRowArray();
                $vendorId = $variantData['vendor_id'] ?? null;
            }
            if ($vendorId === null) {
                $vendorId = $product['vendor_id'] ?? null;
            }

            $productVendorRow = null;
            if ($vendorId === null) {
                try {
                    $productVendorRow = $this->productVendorModel
                        ->where('product_id', (int)$productId)
                        ->where('is_active', 1)
                        ->orderBy('id', 'ASC')
                        ->first();
                    $vendorId = $productVendorRow['vendor_id'] ?? null;
                } catch (\Throwable $_) {
                    $productVendorRow = null;
                }
            }

            if ($vendorId === null) {
                $missingVendorProducts[] = [
                    'code' => $product['code'] ?? $product['name'] ?? ('Product #' . $productId),
                ];
                continue;
            }

            $available = (float)($avail['available'] ?? 0);
            $shortage = max(0, $orderedQty - $available);

            if ($shortage > 0) {
                // Get unit price: variant cost > product cost_price > SO unit_price
                $rawCost = $variantData ? (float)($variantData['cost'] ?? 0) : 0;
                if ($rawCost <= 0 && !empty($productVendorRow) && isset($productVendorRow['last_cost'])) {
                    $rawCost = (float)($productVendorRow['last_cost'] ?? 0);
                }
                if ($rawCost <= 0) {
                    $rawCost = (float)($product['cost_price'] ?? 0);
                }
                $fallbackSale = (float)($line['unit_price'] ?? 0);
                $unitPrice = $rawCost > 0 ? $rawCost : $fallbackSale;

                $shortageLines[] = [
                    'line_id' => $line['id'],
                    'product_id' => $productId,
                    'product_variant_id' => $variantId,
                    'product_name' => $product['name'] ?? 'Unknown',
                    'product_code' => $product['code'] ?? '',
                    'vendor_id' => $vendorId,
                    'shortage_qty' => $shortage,
                    'unit' => $line['unit'] ?? 'pcs',
                    'unit_price' => $unitPrice,
                    'line_description' => $line['description'] ?? null,
                ];
            }
        }

        // Block auto-generation when any stockable item has no vendor assignment
        if (!empty($missingVendorProducts)) {
            $uniqueMissing = [];
            foreach ($missingVendorProducts as $item) {
                $key = (int)($item['product_id'] ?? 0) . '|' . (string)($item['code'] ?? '');
                $uniqueMissing[$key] = [
                    'product_id' => (int)($item['product_id'] ?? 0),
                    'code' => (string)($item['code'] ?? 'Unknown'),
                ];
            }
            $missingList = array_values($uniqueMissing);
            $codes = array_values(array_unique(array_column($missingList, 'code')));
            $message = count($codes) . ' product(s) have no vendor assigned: ' . implode(', ', array_slice($codes, 0, 8));
            if (count($codes) > 8) {
                $message .= '...';
            }
            $message .= '. Please assign vendor(s) to these products before using auto generation.';
            return [
                'success' => false,
                'created_pos' => [],
                'message' => $message,
                'missing_vendor_items' => $missingList,
            ];
        }

        // Build detailed message for cases where no purchasing documents are required
        if (empty($shortageLines)) {
            $message = 'No purchase orders can be created. ';
            
            if ($skippedNonStockable > 0) {
                $message .= 'All shortage items are non-stockable (services/virtual products).';
            } else {
                $message .= 'All items have sufficient stock.';
            }
            
            return [
                'success' => false,
                'created_pos' => [],
                'message' => $message
            ];
        }

        // Group by vendor
        $vendorGroups = [];
        foreach ($shortageLines as $sLine) {
            $vendorId = $sLine['vendor_id'];
            if (!isset($vendorGroups[$vendorId])) {
                $vendorGroups[$vendorId] = [];
            }
            $vendorGroups[$vendorId][] = $sLine;
        }

        // Create RFQs per vendor
        $createdRFQs = [];
        $this->db->transStart();

        try {
            foreach ($vendorGroups as $vendorId => $vendorLines) {
                // Check if RFQ already exists for this vendor and SO (using notes)
                $existingRFQ = null;
                try {
                    $soNumber = $salesOrder['order_number'] ?? $salesOrderId;
                    $q = $this->purchaseRfqModel
                        ->where('vendor_id', $vendorId)
                        ->where('status !=', 'cancelled')
                        ->groupStart()
                            ->like('notes', 'SO#' . $salesOrderId)
                            ->orLike('notes', 'SO#' . $soNumber)
                        ->groupEnd();
                    $existingRFQ = $q->first();
                } catch (\Throwable $e) {
                    log_message('error', 'Error checking existing RFQ: ' . $e->getMessage());
                    $existingRFQ = null;
                }

                if ($existingRFQ) {
                    // Skip if RFQ already exists for this vendor + SO
                    continue;
                }

                $rfqNumber = $this->generateNextRfqNumber();
                $soNumber = $salesOrder['order_number'] ?? $salesOrderId;
                $rfqNotes = 'Auto RFQ from SO#' . $salesOrderId . ' (' . $soNumber . ')';

                $rfqData = [
                    'rfq_number' => $rfqNumber,
                    'vendor_id' => $vendorId,
                    'status' => 'draft',
                    'notes' => $rfqNotes,
                    'subtotal' => 0,
                    'discount' => 0,
                    'tax_amount' => 0,
                    'grand_total' => 0,
                    'total_discount' => 0,
                    'total_tax' => 0,
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                try {
                    $rfqFields = $this->db->getFieldNames('purchase_rfqs');
                    if (in_array('currency', $rfqFields)) {
                        $rfqData['currency'] = $this->defaultPurchaseCurrency ?: 'PKR';
                    }
                    if (in_array('rfq_date', $rfqFields)) {
                        $rfqData['rfq_date'] = date('Y-m-d H:i:s');
                    }
                } catch (\Throwable $e) {
                    // ignore if field lookup fails
                }

                try {
                    $rfqId = $this->purchaseRfqModel->insert($rfqData);
                    if (!$rfqId) {
                        throw new \Exception('RFQ insert returned no ID for vendor ' . $vendorId);
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Failed to insert RFQ: ' . $e->getMessage());
                    throw new \Exception('Failed to create RFQ for vendor ' . $vendorId . ': ' . $e->getMessage());
                }

                // Create RFQ Lines
                $subtotal = 0;
                foreach ($vendorLines as $sLine) {
                    $lineTotal = $sLine['shortage_qty'] * $sLine['unit_price'];
                    $subtotal += $lineTotal;

                    $desc = $sLine['line_description'] ?? $sLine['product_name'];
                    $rfqLineData = [
                        'rfq_id' => $rfqId,
                        'product_id' => $sLine['product_id'],
                        'product_variant_id' => (!empty($sLine['product_variant_id'])) ? (int)$sLine['product_variant_id'] : null,
                        'description' => $desc,
                        'quantity' => $sLine['shortage_qty'],
                        'unit_cost' => $sLine['unit_price'],
                        'line_total' => $lineTotal,
                        'discount' => 0,
                        'discount_percent' => 0,
                        'tax_percent' => 0,
                        'tax_amount' => 0,
                        'created_at' => date('Y-m-d H:i:s'),
                    ];

                    try {
                        $rfqLineId = $this->purchaseRfqLineModel->insert($rfqLineData);
                        if (!$rfqLineId) {
                            throw new \Exception('RFQ line insert returned no ID for product ' . $sLine['product_id']);
                        }
                    } catch (\Throwable $e) {
                        log_message('error', 'Failed to insert RFQ line: ' . $e->getMessage());
                        throw new \Exception('Failed to create RFQ line for product ' . $sLine['product_id'] . ': ' . $e->getMessage());
                    }
                }

                // Update RFQ totals
                try {
                    $updateData = [
                        'subtotal' => $subtotal,
                        'grand_total' => $subtotal,
                        'discount' => 0,
                        'tax_amount' => 0,
                        'total_discount' => 0,
                        'total_tax' => 0,
                    ];
                    $this->db->table('purchase_rfqs')
                        ->where('id', $rfqId)
                        ->update($updateData);
                } catch (\Throwable $e) {
                    log_message('warning', 'Could not update RFQ totals: ' . $e->getMessage());
                }

                $createdRFQs[] = [
                    'rfq_id' => $rfqId,
                    'rfq_number' => $rfqNumber,
                    'vendor_id' => $vendorId,
                    'line_count' => count($vendorLines),
                ];
            }

            $this->db->transComplete();

            if ($this->db->transStatus() === false) {
                throw new \Exception('Transaction failed');
            }

            return [
                'success' => true,
                'created_pos' => $createdRFQs,
                'message' => count($createdRFQs) . ' draft RFQ(s) created successfully.'
            ];

        } catch (\Exception $e) {
            $this->db->transRollback();
            log_message('error', 'AutoPurchaseSuggestionService error: ' . $e->getMessage());
            return [
                'success' => false,
                'created_pos' => [],
                'message' => 'Failed to create RFQs: ' . $e->getMessage()
            ];
        }
    }

    private function getRfqPrefix(): string
    {
        $default = 'RI-PO-';
        try {
            $row = $this->db->query('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1', ['purchase_rfq_prefix'])->getRowArray();
            $val = isset($row['setting_value']) ? trim((string)$row['setting_value']) : '';
            return $val !== '' ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    private function generateNextRfqNumber(): string
    {
        $prefix = $this->getRfqPrefix();
        try {
            $lastNumber = 0;

            $rfqRow = $this->db->query(
                'SELECT rfq_number AS doc_number FROM purchase_rfqs WHERE rfq_number LIKE ? ORDER BY rfq_number DESC LIMIT 1',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($rfqRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $rfqRow['doc_number']));
            }

            $poRow = $this->db->query(
                'SELECT po_number AS doc_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY po_number DESC LIMIT 1',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($poRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $poRow['doc_number']));
            }

            $next = $lastNumber + 1;
            return $prefix . str_pad((string)$next, 4, '0', STR_PAD_LEFT);
        } catch (\Throwable $e) {
            $rand = random_int(1000, 9999);
            return $prefix . $rand;
        }
    }
}
