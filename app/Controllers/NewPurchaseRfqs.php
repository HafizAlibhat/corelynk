<?php

namespace App\Controllers;

use App\Models\PurchaseRfqModel;
use App\Models\PurchaseRfqLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderLineModel;
use Config\Database;
use App\Models\VendorModel;
use App\Models\CompanySettingsModel;
use App\Services\SearchService;
use App\Libraries\InvoicePdfGenerator;
use App\Libraries\DocumentLogger;
use App\Libraries\RoleDataAccess;

class NewPurchaseRfqs extends BaseController
{
    private function getDefaultPurchaseCurrency(): string
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
     * Get RFQ/PO prefix from system_settings.
     * Falls back to a safe default if the table/key doesn't exist.
     */
    private function getRfqPrefix(): string
    {
        $default = 'RI-PO-';
        try {
            $db = Database::connect();
            $row = $db->query('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1', ['purchase_rfq_prefix'])->getRowArray();
            $val = isset($row['setting_value']) ? trim((string) $row['setting_value']) : '';
            return $val !== '' ? $val : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Generate next sequential RFQ number like RI-PO-0001.
     * Uses row-level locking to avoid duplicates under concurrency.
     */
    private function generateNextRfqNumber(\CodeIgniter\Database\BaseConnection $db): string
    {
        $prefix = $this->getRfqPrefix();
        $lastNumber = 0;

        try {
            $rfqRow = $db->query(
                'SELECT rfq_number AS doc_number FROM purchase_rfqs WHERE rfq_number LIKE ? ORDER BY rfq_number DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($rfqRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $rfqRow['doc_number']));
            }
        } catch (\Throwable $_) {
            // ignore and fall back to defaults
        }

        try {
            $poRow = $db->query(
                'SELECT po_number AS doc_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY po_number DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($poRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $poRow['doc_number']));
            }
        } catch (\Throwable $_) {
            // ignore and fall back to defaults
        }

        $next = $lastNumber + 1;
        return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // Create a new RFQ (draft)
    public function create()
    {
        // Use case-insensitive check for the HTTP method to avoid environment differences
        if (strtolower($this->request->getMethod()) !== 'post') {
            // Log unexpected access to the create endpoint to aid debugging (non-blocking)
            $method = $this->request->getMethod();
            $ip = $this->request->getIPAddress();
            log_message('warning', "NewPurchaseRfqs::create called with method={$method} from {$ip}");
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed, POST required']);
        }

    // Debug: mark that controller was hit for tracing
    log_message('debug', 'NewPurchaseRfqs::create HIT from ' . $this->request->getIPAddress());

    // Support JSON payloads (application/json) as well as form-encoded posts
        // Use a tolerant approach: try to decode raw body first, fallback to getPost()
        $body = $this->request->getBody();
        // If framework did not expose the raw body, fall back to php://input
        if (empty($body)) {
            $body = file_get_contents('php://input');
        }
        // Debug: log raw body to help diagnose parsing issues in dev environment
        if (!empty($body)) {
            log_message('debug', 'NewPurchaseRfqs raw body: ' . (is_string($body) ? $body : json_encode($body)));
        } else {
            log_message('debug', 'NewPurchaseRfqs raw body: <empty>');
        }

            // Debug: log raw body to help diagnose parsing issues in dev environment
            if (!empty($body)) {
                log_message('debug', 'NewPurchaseRfqs raw body: ' . (is_string($body) ? $body : json_encode($body)));
            } else {
                log_message('debug', 'NewPurchaseRfqs raw body: <empty>');
            }

            $decoded = null;
            if ($body) {
                $decoded = json_decode($body, true);
                // If json_decode failed, attempt a tolerant fix: quote unquoted keys (e.g. { a:1 } -> { "a":1 })
                if ($decoded === null) {
                    $fixed = preg_replace('/([\{\s,])([a-zA-Z0-9_]+)\s*:/', '$1"$2":', $body);
                    if ($fixed !== null) {
                        log_message('debug', 'NewPurchaseRfqs attempted fixed body: ' . $fixed);
                        $decoded = json_decode($fixed, true);
                    }
                }
            }
            if (is_array($decoded)) {
                $data = $decoded;
            } else {
                $data = $this->request->getPost();
            }

        // Basic validation: must have at least one line with positive qty
        $lines = $data['lines'] ?? [];
        $hasPositive = false;
        foreach ($lines as $ln) {
            // accept either 'qty' (preferred) or legacy 'quantity'
            $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
            if ($qty > 0) { $hasPositive = true; break; }
        }
        if (!$hasPositive) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'At least one line with quantity > 0 is required']);
        }

    $vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        // Server-side validation: vendor required (allow vendor_id = 0)
        if (!array_key_exists('vendor_id', $data) || $data['vendor_id'] === '' || $data['vendor_id'] === null) {
            log_message('debug', 'NewPurchaseRfqs::create validation failed: vendor_id missing');
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Please select a vendor from the dropdown']);
        }

        $db = Database::connect();
        // Ensure currency columns exist (best-effort, ignore failures)
        try {
            $rfqCols = $db->getFieldNames('purchase_rfqs');
            if (!in_array('currency', $rfqCols)) {
                $db->query("ALTER TABLE purchase_rfqs ADD COLUMN currency VARCHAR(10) NULL AFTER vendor_id");
                $rfqCols = $db->getFieldNames('purchase_rfqs');
            }
        } catch (\Throwable $_) { $rfqCols = []; }
        try {
            $poCols = $db->getFieldNames('purchase_orders');
            if (!in_array('currency', $poCols)) {
                $db->query("ALTER TABLE purchase_orders ADD COLUMN currency VARCHAR(10) NULL AFTER vendor_id");
                $poCols = $db->getFieldNames('purchase_orders');
            }
        } catch (\Throwable $_) { $poCols = []; }
        $db->transBegin();
        try {
            $currency = isset($data['currency']) && $data['currency'] !== '' ? strtoupper(trim((string)$data['currency'])) : $this->getDefaultPurchaseCurrency();
            // Server-side: compute and validate per-line discount/tax and header totals
            $computedSubtotal = 0.0;
            $computedTotalDiscount = 0.0;
            $computedTotalTax = 0.0;
            $computedGrand = 0.0;
            $normalizedLines = [];
            foreach ($lines as $ln) {
                $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                if ($qty <= 0) continue;
                $unitCostVal = isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null);
                if ($unitCostVal === null || $unitCostVal <= 0) {
                    log_message('debug', 'NewPurchaseRfqs::create validation failed: unit_price missing/invalid for product ' . (isset($ln['product_id']) ? (int)$ln['product_id'] : 'n/a'));
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Validation failed: unit_price missing or <= 0']);
                }
                $lineBase = $qty * $unitCostVal;
                // discount: prefer explicit 'discount' amount, otherwise use discount_percent
                $discAmount = 0.0;
                if (isset($ln['discount'])) {
                    $discAmount = (float)$ln['discount'];
                } elseif (isset($ln['discount_percent'])) {
                    $discAmount = ((float)$ln['discount_percent'] / 100.0) * $lineBase;
                }
                if ($discAmount < 0) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid discount value']);
                }
                $taxPct = isset($ln['tax_percent']) ? (float)$ln['tax_percent'] : 0.0;
                if ($taxPct < 0) {
                    return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid tax percent']);
                }
                $taxable = max(0, $lineBase - $discAmount);
                $taxAmount = ($taxPct / 100.0) * $taxable;
                $lineTotal = $taxable + $taxAmount;

                $computedSubtotal += $lineBase;
                $computedTotalDiscount += $discAmount;
                $computedTotalTax += $taxAmount;
                $computedGrand += $lineTotal;

                $normalizedLines[] = [
                    'product_id' => isset($ln['product_id']) ? (int)$ln['product_id'] : null,
                    'product_variant_id' => isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null,
                    'description' => $ln['description'] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => $unitCostVal,
                    'discount' => $discAmount,
                    'discount_percent' => isset($ln['discount_percent']) ? (float)$ln['discount_percent'] : null,
                    'tax_percent' => $taxPct,
                    'tax_amount' => $taxAmount,
                    'line_total' => $lineTotal,
                ];
            }

            if (count($normalizedLines) === 0) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'At least one valid line is required']);
            }
            // Auto-generate rfq_number if empty
            $rfqNumber = isset($data['rfq_number']) ? trim((string) $data['rfq_number']) : '';
            if ($rfqNumber === '') {
                $rfqNumber = $this->generateNextRfqNumber($db);
            }

            // Normalize date inputs: accept DD-MM-YYYY or YYYY-MM-DD
            $rfqDateRaw = $data['rfq_date'] ?? null;
            $deliveryRaw = $data['delivery_date'] ?? null;
            $rfqDate = null;
            $deliveryDate = null;
            if ($rfqDateRaw) {
                // dd-mm-yyyy -> yyyy-mm-dd 00:00:00
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $rfqDateRaw, $m)) {
                    $rfqDate = $m[3] . '-' . $m[2] . '-' . $m[1] . ' 00:00:00';
                } else {
                    // try to parse as ISO
                    $rfqDate = date('Y-m-d H:i:s', strtotime($rfqDateRaw));
                }
            }
            if ($deliveryRaw) {
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $deliveryRaw, $m)) {
                    $deliveryDate = $m[3] . '-' . $m[2] . '-' . $m[1];
                } else {
                    $d = strtotime($deliveryRaw);
                    if ($d !== false) $deliveryDate = date('Y-m-d', $d);
                }
            }

            $rfqModel = new PurchaseRfqModel();
            // Prepare header data, but only include columns that actually exist in the DB table
            $insertData = [
                'rfq_number' => $rfqNumber,
                'vendor_id' => $vendorId,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'rfq_date' => $rfqDate,
                'delivery_date' => $deliveryDate,
                // header totals computed server-side from normalizedLines
                'subtotal' => $computedSubtotal,
                'total_discount' => $computedTotalDiscount,
                'total_tax' => $computedTotalTax,
                'grand_total' => $computedGrand,
                // keep legacy fields populated for compatibility
                'discount' => $computedTotalDiscount,
                'tax_amount' => $computedTotalTax,
                'created_by' => session()->get('user_id')?:null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            // filter by actual DB columns to avoid "Unknown column" errors on older schemas
            try {
                $dbCols = $rfqCols ?: $db->getFieldNames('purchase_rfqs');
                foreach ($insertData as $k => $v) {
                    if (! in_array($k, $dbCols)) unset($insertData[$k]);
                }
            } catch (\Throwable $e) {
                // if any issue reading table metadata, proceed with unfiltered insert (will likely error and rollback)
                log_message('debug', 'NewPurchaseRfqs::create could not read purchase_rfqs columns: ' . $e->getMessage());
            }
            if (isset($dbCols) && in_array('currency', $dbCols)) {
                $insertData['currency'] = $currency;
            }

            $rfqId = $rfqModel->insert($insertData, true);
            if (!$rfqId) throw new \RuntimeException('RFQ insert failed');
            $lineModel = new PurchaseRfqLineModel();
            // Filter line fields to only columns present in purchase_rfq_lines
            try {
                $lineCols = $db->getFieldNames('purchase_rfq_lines');
            } catch (\Throwable $e) {
                $lineCols = null;
                log_message('debug', 'NewPurchaseRfqs::create could not read purchase_rfq_lines columns: ' . $e->getMessage());
            }
            foreach ($normalizedLines as $ln) {
                $lineData = [
                    'rfq_id' => $rfqId,
                    'product_id' => $ln['product_id'] ?? null,
                    'product_variant_id' => $ln['product_variant_id'] ?? null,
                    'description' => $ln['description'] ?? null,
                    'quantity' => $ln['quantity'],
                    'unit_cost' => $ln['unit_cost'],
                    'discount' => $ln['discount'],
                    'discount_percent' => $ln['discount_percent'],
                    'tax_percent' => $ln['tax_percent'],
                    'tax_amount' => $ln['tax_amount'],
                    'line_total' => $ln['line_total'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                if (is_array($lineCols)) {
                    foreach ($lineData as $k => $v) {
                        if (! in_array($k, $lineCols)) unset($lineData[$k]);
                    }
                }
                $res = $lineModel->insert($lineData);
                if (!$res) {
                    throw new \RuntimeException('RFQ line insert failed');
                }
            }

            $db->transCommit();
            log_message('debug', 'NewPurchaseRfqs::create success rfq_id=' . $rfqId);
            // If request wants JSON (AJAX or Accept header) return JSON, otherwise redirect to list view
            $accept = $this->request->getHeaderLine('Accept') ?: '';
            $wantsJson = $this->request->isAJAX() || stripos($accept, 'application/json') !== false;
            if ($wantsJson) {
                return $this->response->setStatusCode(200)->setJSON(['success' => true, 'rfq_id' => $rfqId]);
            } else {
                // Browser form submit: redirect back to RFQ list page with flash message
                session()->setFlashdata('success', 'RFQ created successfully (ID: ' . $rfqId . ')');
                return redirect()->to(site_url('new-purchase-rfqs'));
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'RFQ create failed: '.$e->getMessage());
            // If AJAX caller expects JSON return JSON error, otherwise redirect back with flash message
            $accept = $this->request->getHeaderLine('Accept') ?: '';
            $wantsJson = $this->request->isAJAX() || stripos($accept, 'application/json') !== false;
            if ($wantsJson) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to create RFQ']);
            } else {
                session()->setFlashdata('error', 'Failed to create RFQ');
                return redirect()->back()->withInput();
            }
        }
    }

    // Show a single RFQ with lines (for view/edit UI)
    public function show($id = null)
    {
        $rfqModel = new PurchaseRfqModel();
        $rfq = $rfqModel->findByPublicIdOrId($id);
        if (!$rfq) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']);
        }
        $rfqId = (int)$rfq['id'];

        $lineModel = new PurchaseRfqLineModel();
        $lines = $lineModel->where('rfq_id', $rfqId)->orderBy('id', 'ASC')->findAll();

        // Enrich lines with product info
        try {
            $db = Database::connect();
            $productIds = array_values(array_filter(array_unique(array_map(function($l){ return isset($l['product_id']) ? (int)$l['product_id'] : null; }, $lines))));
            
            // Try to find original sales order from notes field if this RFQ was auto-created
            $soLines = [];
            if (!empty($rfq['notes']) && preg_match('/SO#(\d+)/', $rfq['notes'], $m)) {
                $soId = (int)$m[1];
                $soLinesResult = $db->table('sales_order_lines')->where('sales_order_id', $soId)->orderBy('id', 'ASC')->get()->getResultArray();
                foreach ($soLinesResult as $idx => $soLine) {
                    $soLines[$idx] = $soLine;
                }
            }
            
            $prodMap = [];
            $variantMap = [];
            $allVariants = [];
            
            if (!empty($productIds)) {
                $productModel = new \App\Models\ProductModel();
                $products = $productModel->whereIn('id', $productIds)->findAll();
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
                    $prodMap[(int)$p['id']] = [
                        'name' => $p['name'] ?? null,
                        'code' => $p['code'] ?? ($p['sku'] ?? null),
                        'unit' => $p['unit'] ?? null,
                        'image_url' => $img,
                        'cost_price' => isset($p['cost_price']) ? (float)$p['cost_price'] : null,
                    ];
                }
            }
            
            if (!empty($productIds)) {
                $variants = $db->table('product_variants')->whereIn('product_id', $productIds)->get()->getResultArray();
                foreach ($variants as $v) {
                    $vid = (int)($v['id'] ?? 0);
                    $vimg = null;
                    if (!empty($v['image'])) {
                        $vimg = base_url('/uploads/variants/' . ltrim($v['image'], '/'));
                    }
                    $vdata = [
                        'id' => $vid,
                        'product_id' => (int)($v['product_id'] ?? 0),
                        'name' => $v['name'] ?? null,
                        'code' => $v['art_number'] ?? null,
                        'image_url' => $vimg,
                        'attributes' => $v['attributes'] ?? null,
                        'cost' => isset($v['cost']) ? (float)$v['cost'] : null,
                        'vendor_price' => ($v['vendor_price'] ?? '') !== '' ? (float)$v['vendor_price'] : null,
                        'vendor_currency' => $v['vendor_currency'] ?? null,
                    ];
                    $allVariants[] = $vdata;
                }
            }
            
            foreach ($lines as $idx => &$ln) {
                $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : null;
                $vid = isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null;
                
                $productCost = null;
                $variantCost = null;
                $variantVendorPrice = null;
                $variantVendorCurrency = null;
                
                // Get product info
                if ($pid && isset($prodMap[$pid])) {
                    $p = $prodMap[$pid];
                    $ln['product_name'] = $p['name'] ?? null;
                    $ln['product_code'] = $p['code'] ?? null;
                    $ln['product_image_url'] = $p['image_url'] ?? null;
                    $ln['unit'] = $p['unit'] ?? ($ln['unit'] ?? null);
                    $productCost = $p['cost_price'] ?? null;
                }
                
                // Try to match with corresponding SO line by index
                if (isset($soLines[$idx])) {
                    $soLine = $soLines[$idx];
                    // Use the SO line description which has variant details
                    if (!empty($soLine['description'])) {
                        $ln['description'] = $soLine['description'];
                    }
                    // If SO line has variant_id, try to find that variant
                    if (!empty($soLine['product_variant_id'])) {
                        $vid = (int)$soLine['product_variant_id'];
                        $ln['product_variant_id'] = $vid;
                    }
                }
                
                // If variant_id is still missing, try to match by description against variant attributes
                if (!$vid && $pid && !empty($ln['description'])) {
                    foreach ($allVariants as $vdata) {
                        if ($vdata['product_id'] !== $pid) continue;
                        $attrs = $vdata['attributes'];
                        if (!empty($attrs)) {
                            $attrsArr = is_string($attrs) ? json_decode($attrs, true) : $attrs;
                            if (is_array($attrsArr)) {
                                $match = true;
                                foreach ($attrsArr as $key => $value) {
                                    if (stripos($ln['description'], (string)$value) === false) {
                                        $match = false;
                                        break;
                                    }
                                }
                                if ($match) {
                                    $vid = $vdata['id'];
                                    $ln['product_variant_id'] = $vid;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Get variant info if we have a variant ID
                if ($vid) {
                    foreach ($allVariants as $vdata) {
                        if ($vdata['id'] === $vid) {
                            $variantMap[$vid] = $vdata;
                            $variantCost = $vdata['cost'] ?? null;
                            $variantVendorPrice = isset($vdata['vendor_price']) ? $vdata['vendor_price'] : null;
                            $variantVendorCurrency = $vdata['vendor_currency'] ?? null;
                            if (!empty($vdata['name'])) {
                                $ln['variant_name'] = $vdata['name'];
                            }
                            if (!empty($vdata['code'])) {
                                $ln['product_code'] = $vdata['code'];
                            }
                            if (!empty($vdata['image_url'])) {
                                $ln['product_image_url'] = $vdata['image_url'];
                            }
                            break;
                        }
                    }
                }
                
                $ln['qty'] = isset($ln['qty']) ? $ln['qty'] : (isset($ln['quantity']) ? $ln['quantity'] : null);
                $unitPrice = isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null);
                
                // Backfill price: variant vendor_price > variant cost > product cost > existing unit price
                if ($unitPrice === null || $unitPrice <= 0) {
                    if (isset($variantVendorPrice) && $variantVendorPrice !== null && $variantVendorPrice > 0) {
                        $unitPrice = $variantVendorPrice;
                        // Also carry over the vendor currency for this line if not already set
                        if (!empty($variantVendorCurrency) && empty($ln['currency'])) {
                            $ln['currency'] = $variantVendorCurrency;
                        }
                    } elseif ($variantCost !== null && $variantCost > 0) {
                        $unitPrice = $variantCost;
                    } elseif ($productCost !== null && $productCost > 0) {
                        $unitPrice = $productCost;
                    }
                }
                $ln['unit_price'] = $unitPrice;
                // Always expose vendor_price for the line in case RFQ UI wants to show it
                if (!empty($variantVendorPrice)) {
                    $ln['variant_vendor_price'] = $variantVendorPrice;
                }
                if (!empty($variantVendorCurrency)) {
                    $ln['variant_vendor_currency'] = $variantVendorCurrency;
                }
                unset($variantVendorPrice, $variantVendorCurrency, $variantCost);
            }
            unset($ln);
        } catch (\Throwable $_) {
            // best-effort enrichment
        }

        // Vendor name for display
        $vendorName = null;
        try {
            $row = Database::connect()->table('vendors')->select('name')->where('id', (int)($rfq['vendor_id'] ?? 0))->get()->getRowArray();
            $vendorName = $row['name'] ?? null;
        } catch (\Throwable $_) {
            $vendorName = null;
        }

        // Find linked PO (if RFQ was confirmed)
        $linkedPoId = null;
        try {
            $poRow = Database::connect()->table('purchase_orders')->select('id')->where('rfq_id', $rfqId)->orderBy('id', 'DESC')->get()->getRowArray();
            $linkedPoId = $poRow['id'] ?? null;
        } catch (\Throwable $_) {}

        return $this->response->setJSON([
            'success' => true,
            'data' => [
                'id' => $rfq['id'],
                'rfq_number' => $rfq['rfq_number'] ?? null,
                'vendor_id' => $rfq['vendor_id'] ?? null,
                'vendor_name' => $vendorName,
                'rfq_date' => $rfq['rfq_date'] ?? null,
                'delivery_date' => $rfq['delivery_date'] ?? null,
                'notes' => $rfq['notes'] ?? null,
                'currency' => $rfq['currency'] ?? null,
                'status' => $rfq['status'] ?? null,
                'po_id' => $linkedPoId,
                'lines' => $lines,
            ]
        ]);
    }

    public function pdf($id = null)
    {
        $rfq = (new PurchaseRfqModel())->findByPublicIdOrId($id);
        if (!$rfq) {
            return redirect()->back()->with('error', 'RFQ not found');
        }
        $rfqId = (int)$rfq['id'];

        $lines = (new PurchaseRfqLineModel())->where('rfq_id', $rfqId)->orderBy('id', 'ASC')->findAll();
        $vendor = [];
        try {
            $vendor = Database::connect()->table('vendors')->where('id', (int)($rfq['vendor_id'] ?? 0))->get()->getRowArray() ?: [];
        } catch (\Throwable $_) {
            $vendor = [];
        }

        $pdfLines = [];
        foreach ($lines as $line) {
            $qty = (float)($line['quantity'] ?? ($line['qty'] ?? 0));
            $unitPrice = (float)($line['unit_cost'] ?? ($line['unit_price'] ?? 0));
            $lineTotal = isset($line['line_total']) ? (float)$line['line_total'] : ($qty * $unitPrice);
            $pdfLines[] = [
                'id' => $line['id'] ?? null,
                'product_id' => $line['product_id'] ?? null,
                'product_variant_id' => $line['product_variant_id'] ?? null,
                'description' => $line['description'] ?? '',
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'discount_value' => (float)($line['discount_percent'] ?? 0),
                'discount_amount' => (float)($line['discount'] ?? 0),
                'tax_rate' => (float)($line['tax_percent'] ?? 0),
                'tax_amount' => (float)($line['tax_amount'] ?? 0),
            ];
        }

        $vendorParty = [
            'name' => $vendor['name'] ?? 'Vendor',
            'phone' => $vendor['phone'] ?? '',
            'email' => $vendor['email'] ?? '',
        ];
        $vendorAddress = [
            'line1' => trim((string)($vendor['address'] ?? '')),
            'line2' => '',
            'city_name' => '',
            'state_name' => '',
            'postal_code' => '',
        ];

        $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];
        $payload = [
            'invoice' => [
                'id' => $rfq['id'] ?? $rfqId,
                'invoice_number' => $rfq['rfq_number'] ?? ('RFQ-' . $rfqId),
                'issue_date' => $rfq['rfq_date'] ?? date('Y-m-d'),
                'subtotal' => (float)($rfq['subtotal'] ?? 0),
                'discount' => (float)($rfq['total_discount'] ?? ($rfq['discount'] ?? 0)),
                'tax_total' => (float)($rfq['total_tax'] ?? ($rfq['tax_amount'] ?? 0)),
                'total_amount' => (float)($rfq['grand_total'] ?? 0),
                'currency_code' => $rfq['currency'] ?? $this->getDefaultPurchaseCurrency(),
                'status' => $rfq['status'] ?? 'draft',
            ],
            'lines' => $pdfLines,
            'company' => $company,
            'customer' => $vendorParty,
            'customerAddress' => $vendorAddress,
            'document_title' => 'RFQ',
            'document_number_label' => 'RFQ #',
            'document_date_label' => 'Date:',
            'document_prefix' => '',
            'party_label' => 'Vendor',
            'hide_company_logo' => true,
            'hide_company_website' => true,
            'pdf_show_header_address' => (int)($company['pdf_rfq_show_header'] ?? 1),
            'pdf_show_footer' => (int)($company['pdf_rfq_show_footer'] ?? 1),
        ];

        $pdf = (new InvoicePdfGenerator())->generateSystemInvoice($payload);
        if (is_array($pdf) && !empty($pdf['path']) && is_file($pdf['path'])) {
            $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', (string)($rfq['rfq_number'] ?? ('RFQ-' . $rfqId))) ?: ('RFQ-' . $rfqId);
            return $this->response->download($pdf['path'], null)
                ->setFileName('rfq_' . $safeNumber . '.pdf')
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', '0');
        }

        return redirect()->back()->with('error', 'Failed to generate RFQ PDF');
    }

    // Update an existing RFQ (draft only)
    public function update($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed, POST required']);
        }

        $rfqModel = new PurchaseRfqModel();
        $rfq = $rfqModel->findByPublicIdOrId($id);
        if (!$rfq) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']);
        }
        $rfqId = (int)$rfq['id'];
        if (!empty($rfq['status']) && $rfq['status'] !== 'draft') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Only draft RFQs can be edited']);
        }

        // Parse JSON or form payload
        $body = $this->request->getBody();
        if (empty($body)) {
            $body = file_get_contents('php://input');
        }
        $decoded = null;
        if ($body) {
            $decoded = json_decode($body, true);
        }
        $data = is_array($decoded) ? $decoded : $this->request->getPost();

        $lines = $data['lines'] ?? [];
        $hasPositive = false;
        foreach ($lines as $ln) {
            $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
            if ($qty > 0) { $hasPositive = true; break; }
        }
        if (!$hasPositive) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'At least one line with quantity > 0 is required']);
        }

        $vendorId = isset($data['vendor_id']) ? (int)$data['vendor_id'] : null;
        if (!array_key_exists('vendor_id', $data) || $data['vendor_id'] === '' || $data['vendor_id'] === null) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Please select a vendor from the dropdown']);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            // Normalize dates: accept DD-MM-YYYY or YYYY-MM-DD (view sends DD-MM-YYYY)
            $rfqDateRaw  = $data['rfq_date'] ?? null;
            $deliveryRaw = $data['delivery_date'] ?? null;
            $rfqDateNorm = null;
            $deliveryNorm = null;
            if ($rfqDateRaw) {
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $rfqDateRaw, $m)) {
                    $rfqDateNorm = $m[3] . '-' . $m[2] . '-' . $m[1] . ' 00:00:00';
                } else {
                    $t = strtotime($rfqDateRaw);
                    if ($t !== false) $rfqDateNorm = date('Y-m-d H:i:s', $t);
                }
            }
            if ($deliveryRaw) {
                if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $deliveryRaw, $m)) {
                    $deliveryNorm = $m[3] . '-' . $m[2] . '-' . $m[1];
                } else {
                    $t = strtotime($deliveryRaw);
                    if ($t !== false) $deliveryNorm = date('Y-m-d', $t);
                }
            }

            $update = [
                'vendor_id' => $vendorId,
                'rfq_date' => $rfqDateNorm,
                'delivery_date' => $deliveryNorm,
                'notes' => $data['notes'] ?? null,
                'subtotal' => $data['subtotal'] ?? 0,
                'discount' => $data['discount'] ?? 0,
                'tax_amount' => $data['tax_amount'] ?? 0,
                'grand_total' => $data['grand_total'] ?? 0,
                'total_discount' => $data['total_discount'] ?? 0,
                'total_tax' => $data['total_tax'] ?? 0,
                'currency' => $data['currency'] ?? ($rfq['currency'] ?? $this->getDefaultPurchaseCurrency()),
                'updated_at' => date('Y-m-d H:i:s')
            ];

            $rfqModel->update($rfqId, $update);

            // Replace lines
            $lineModel = new PurchaseRfqLineModel();
            $lineModel->where('rfq_id', $rfqId)->delete();
            foreach ($lines as $ln) {
                $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                if ($qty <= 0) continue;
                $lineModel->insert([
                    'rfq_id' => $rfqId,
                    'product_id' => isset($ln['product_id']) ? (int)$ln['product_id'] : null,
                    'product_variant_id' => isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null,
                    'description' => $ln['description'] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null),
                    'discount_percent' => $ln['discount_percent'] ?? 0,
                    'discount' => $ln['discount'] ?? 0,
                    'tax_percent' => $ln['tax_percent'] ?? 0,
                    'tax_amount' => $ln['tax_amount'] ?? 0,
                    'line_total' => $ln['line_total'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }

            $db->transCommit();
            return $this->response->setJSON(['success' => true, 'rfq_id' => $rfqId]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'RFQ update failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to update RFQ']);
        }
    }

    /**
     * Preview the next RFQ/PO number (does not create any record).
     * Used by the unified RFQ/PO UI to auto-load and display a readonly number.
     */
    public function nextNumber()
    {
        if (strtolower($this->request->getMethod()) !== 'get') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            $num = $this->generateNextRfqNumber($db);
            $db->transCommit();
            return $this->response->setStatusCode(200)->setJSON([
                'success' => true,
                'rfq_number' => $num,
                'prefix' => $this->getRfqPrefix(),
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'RFQ nextNumber failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to get next number']);
        }
    }

    // List RFQs (read-only) with optional status filter
    public function index()
    {
        if (strtolower($this->request->getMethod()) !== 'get') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }
        $status = $this->request->getGet('status');
        $searchTerm = $this->request->getGet('q') ?? $this->request->getGet('search');
        // Use a LEFT JOIN to fetch vendor_name in a single query for performance and reliability
        $db = Database::connect();
        $hasCurrency = false;
        try { $colsCheck = $db->getFieldNames('purchase_rfqs'); $hasCurrency = in_array('currency', $colsCheck); } catch (\Throwable $_) { $hasCurrency = false; }

        $userId = (int) (session()->get('user_id') ?? 0);
        $isAdmin = service('policy')->isAdmin();
        $privateUserIds = (new RoleDataAccess())->getPrivateUserIds($userId, $isAdmin);

       $sql = 'SELECT r.id, r.rfq_number, r.vendor_id, COALESCE(v.name, "") AS vendor_name, r.status, r.status AS state, r.created_at, r.subtotal, r.grand_total, r.delivery_date'
           . ($hasCurrency ? ', r.currency' : '')
           . ', (SELECT COUNT(*) FROM purchase_rfq_lines pl WHERE pl.rfq_id = r.id) AS line_count, '
           . '(SELECT COALESCE(p.name, pl.description, "") FROM purchase_rfq_lines pl LEFT JOIN products p ON p.id = pl.product_id WHERE pl.rfq_id = r.id LIMIT 1) AS sample_product, '
           . '(SELECT COALESCE(p.code, "") FROM purchase_rfq_lines pl LEFT JOIN products p ON p.id = pl.product_id WHERE pl.rfq_id = r.id LIMIT 1) AS sample_product_code, '
           . '(SELECT COALESCE(pv.art_number, "") FROM purchase_rfq_lines pl LEFT JOIN product_variants pv ON pv.id = pl.product_variant_id WHERE pl.rfq_id = r.id LIMIT 1) AS sample_variant_code, '
           . '(SELECT COALESCE(pv.name, "") FROM purchase_rfq_lines pl LEFT JOIN product_variants pv ON pv.id = pl.product_variant_id WHERE pl.rfq_id = r.id LIMIT 1) AS sample_variant_name, '
           . '(SELECT COALESCE(pl.description, "") FROM purchase_rfq_lines pl WHERE pl.rfq_id = r.id LIMIT 1) AS sample_description '
           . 'FROM purchase_rfqs r LEFT JOIN vendors v ON v.id = r.vendor_id';
        $params = [];
        $whereClauses = [];
        if ($status) {
            $whereClauses[] = 'r.status = ?';
            $params[] = $status;
        }
        if (!empty($privateUserIds) && $db->fieldExists('created_by', 'purchase_rfqs')) {
            $whereClauses[] = 'r.created_by NOT IN (' . implode(',', array_fill(0, count($privateUserIds), '?')) . ')';
            array_push($params, ...$privateUserIds);
        }
        if (!empty($whereClauses)) {
            $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
        }
        $sql .= ' ORDER BY r.created_at DESC';
        try {
            $query = $db->query($sql, $params);
            $rows = $query->getResultArray();
        } catch (\Throwable $e) {
            // fallback to the model-based approach if raw query fails
            $rfqModel = new PurchaseRfqModel();
            if ($status) {
                $rows = $rfqModel->select('id, rfq_number, vendor_id, status, created_at')->where('status', $status)->orderBy('created_at', 'DESC')->findAll();
            } else {
                $rows = $rfqModel->select('id, rfq_number, vendor_id, status, created_at')->orderBy('created_at', 'DESC')->findAll();
            }
        }

        if (!empty($searchTerm)) {
            $rows = SearchService::filterRows($rows, $searchTerm, [
                'rfq_number',
                'vendor_name',
                'vendor_id',
                'status',
                'sample_product'
            ]);
        }

        // Decide response type: AJAX or Accept:application/json should receive JSON
        $accept = $this->request->getHeaderLine('Accept') ?: '';
        $wantsJson = $this->request->isAJAX() || stripos($accept, 'application/json') !== false;
        if ($wantsJson) {
            // Ensure pure JSON response with no debugbar/HTML appended
            return $this->response->setStatusCode(200)->setJSON(['success' => true, 'data' => $rows]);
        }

        // Non-AJAX browser request: render the HTML page (the view contains JS which will call the JSON endpoint when needed)
        return view('purchase_ui/rfqs');
    }

    // Mark RFQ as sent
    public function send($id = null)
    {
        $model = new PurchaseRfqModel();
        $rfq = $model->findByPublicIdOrId($id);
        if (!$rfq) { $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']); $this->response->send(); exit; }
        $rfqId = (int)$rfq['id'];
        if ($rfq['status'] !== 'draft') { $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Only draft RFQ can be sent']); $this->response->send(); exit; }

        $model->update($rfqId, ['status' => 'sent', 'updated_at' => date('Y-m-d H:i:s')]);
        DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_RFQ, $rfqId, DocumentLogger::ACTION_SENT);
        $this->response->setJSON(['success' => true]); $this->response->send(); exit;
    }

    // Mark RFQ as accepted
    public function accept($id = null)
    {
        $model = new PurchaseRfqModel();
        $rfq = $model->findByPublicIdOrId($id);
        if (!$rfq) { $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']); $this->response->send(); exit; }
        $rfqId = (int)$rfq['id'];
    // Allow accepting RFQ if it is 'sent' or directly from 'draft' when user confirms from list
    if (! in_array($rfq['status'], ['sent','draft'])) { $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Only sent or draft RFQ can be accepted']); $this->response->send(); exit; }

        // Read optional payload (delivery_date, confirm_po)
        $body = $this->request->getBody();
        if (empty($body)) { $body = @file_get_contents('php://input'); }
        $data = json_decode((string)$body, true);
        if (!is_array($data)) { $data = $this->request->getPost(); }
        $confirmPo = !array_key_exists('confirm_po', $data) ? true : (bool)$data['confirm_po'];
        $deliveryRaw = $data['delivery_date'] ?? null;
        $deliveryDate = null;
        if ($deliveryRaw) {
            if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $deliveryRaw, $m)) {
                $deliveryDate = $m[3] . '-' . $m[2] . '-' . $m[1];
            } else {
                $d = strtotime($deliveryRaw);
                if ($d) $deliveryDate = date('Y-m-d', $d);
            }
        }

        $db = Database::connect();
        $db->transBegin();
        $poCols = [];
        try { $poCols = $db->getFieldNames('purchase_orders'); } catch (\Throwable $_) { $poCols = []; }
        try {
            // Mark RFQ accepted
            $model->update($rfqId, ['status' => 'accepted', 'updated_at' => date('Y-m-d H:i:s')]);

            // Ensure there's exactly one PO per RFQ
            $poModel = new PurchaseOrderModel();
            $existing = $poModel->where('rfq_id', $rfqId)->orderBy('id', 'DESC')->first();
            $poId = $existing['id'] ?? null;

            if (!$poId) {
                // Log the RFQ header we're converting so we can see if totals exist
                try {
                    log_message('error', 'NewPurchaseRfqs::accept rfq: ' . json_encode($rfq));
                } catch (\Throwable $_) { }
                // Build PO insert data but only include columns present in the DB to avoid "Unknown column" errors
                $poInsert = [
                    // IMPORTANT: keep same number when RFQ converts to PO
                    'po_number' => $rfq['rfq_number'] ?? null,
                    'rfq_id' => $rfqId,
                    'vendor_id' => $rfq['vendor_id'] ?? null,
                    'status' => 'draft',
                    // Use RFQ totals if present; fall back to zero to satisfy NOT NULL constraints
                    'subtotal' => isset($rfq['subtotal']) ? $rfq['subtotal'] : (isset($rfq['total']) ? $rfq['total'] : 0.0),
                    'total' => isset($rfq['grand_total']) ? $rfq['grand_total'] : (isset($rfq['total']) ? $rfq['total'] : 0.0),
                    'created_by' => session()->get('user_id')?:null,
                    'created_at' => date('Y-m-d H:i:s')
                ];
                try {
                    $poCols = $db->getFieldNames('purchase_orders');
                    foreach ($poInsert as $k => $v) {
                        if (! in_array($k, $poCols)) unset($poInsert[$k]);
                    }
                    if (in_array('currency', $poCols)) {
                        $poInsert['currency'] = $rfq['currency'] ?? $this->getDefaultPurchaseCurrency();
                    }
                    if ($deliveryDate && in_array('delivery_date', $poCols)) {
                        $poInsert['delivery_date'] = $deliveryDate;
                    }
                    if ($confirmPo && in_array('status', $poCols)) {
                        $poInsert['status'] = 'confirmed';
                    }
                } catch (\Throwable $e) {
                    // If metadata read fails, log and continue; insert may still fail which we'll catch below
                    log_message('debug', 'NewPurchaseRfqs::accept could not read purchase_orders columns: ' . $e->getMessage());
                }

                // Log the final PO payload to help debug schema mismatch issues (log as error to ensure it's recorded)
                try {
                    log_message('error', 'NewPurchaseRfqs::accept PO insert payload: ' . json_encode($poInsert));
                } catch (\Throwable $_) { /* ignore logging errors */ }

                $poId = $poModel->insert($poInsert, true);
                if (!$poId) throw new \RuntimeException('PO insert failed');

                // Copy lines
                $rfqLineModel = new PurchaseRfqLineModel();
                $poLineModel = new PurchaseOrderLineModel();
                $rfqLines = $rfqLineModel->where('rfq_id', $rfqId)->findAll();
                foreach ($rfqLines as $ln) {
                    $qty = isset($ln['qty']) ? (float)$ln['qty'] : (isset($ln['quantity']) ? (float)$ln['quantity'] : 0);
                    if ($qty <= 0) continue;
                    $poLineModel->insert([
                        'po_id' => $poId,
                        'product_id' => $ln['product_id'] ?? null,
                        'description' => $ln['description'] ?? null,
                        'qty' => $qty,
                        'unit_price' => isset($ln['unit_price']) ? (float)$ln['unit_price'] : (isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : null),
                        'qty_received' => 0,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                }
            }

            // Update PO with delivery date / confirmed status if requested
            $poUpdate = [];
            if ($deliveryDate && in_array('delivery_date', $poCols)) { $poUpdate['delivery_date'] = $deliveryDate; }
            if ($confirmPo && in_array('status', $poCols)) { $poUpdate['status'] = 'confirmed'; }
            if (!empty($poUpdate)) {
                $poUpdate['updated_at'] = date('Y-m-d H:i:s');
                $poModel->update($poId, $poUpdate);
            }

            $db->transCommit();
            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_RFQ, $rfqId, DocumentLogger::ACTION_STATUS_CHANGED, ['from' => $rfq['status'], 'to' => 'accepted']);
            if ($poId) { DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, (int)$poId, DocumentLogger::ACTION_CREATED); }
            $this->response->setJSON(['success' => true, 'po_id' => $poId]);
            $this->response->send();
            exit;
        } catch (\Throwable $e) {
            $db->transRollback();
            // Log full exception for diagnostics
            log_message('error', 'RFQ accept/convert failed: ' . $e->getMessage());
            log_message('error', $e->getTraceAsString());
            // Return error message to client for easier debugging in dev
            $msg = 'Failed to accept RFQ';
            try { if (!empty($e->getMessage())) $msg = $e->getMessage(); } catch (\Throwable $_) {}
            $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $msg]);
            $this->response->send();
            exit;
        }
    }

    /**
     * Confirm RFQ — marks it as confirmed AND creates a Purchase Order from it.
     * This is the primary "Confirm RFQ" action used in the UI.
     * Returns the newly-created PO id so the front-end can redirect to the PO view.
     */
    public function confirm($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'POST required']);
        }

        $model = new PurchaseRfqModel();
        $rfq = $model->findByPublicIdOrId($id);

        if (!$rfq) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']);
        }
        $rfqId = (int)$rfq['id'];

        if ($rfq['status'] === 'cancelled') {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Cannot confirm cancelled RFQ']);
        }

        // If already confirmed, check if a PO was already created and return it
        $poModel = new PurchaseOrderModel();
        $existingPo = $poModel->where('rfq_id', $rfqId)->orderBy('id', 'DESC')->first();
        if ($rfq['status'] === 'confirmed' && $existingPo) {
            return $this->response->setJSON([
                'success' => true,
                'message' => 'RFQ already confirmed. PO exists.',
                'po_id' => $existingPo['id'],
            ]);
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            // 1. Mark RFQ as confirmed
            $model->update($rfqId, [
                'status' => 'confirmed',
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            // 2. Create a Purchase Order from this RFQ (if one doesn't already exist)
            $poId = null;
            if ($existingPo) {
                $poId = $existingPo['id'];
                // Ensure PO status is confirmed
                $poModel->update($poId, ['status' => 'confirmed', 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                // Read available PO columns to avoid "Unknown column" errors
                $poCols = [];
                try { $poCols = $db->getFieldNames('purchase_orders'); } catch (\Throwable $_) {}

                $poInsert = [
                    'po_number'  => $rfq['rfq_number'] ?? null,     // keep same number
                    'rfq_id'     => $rfqId,
                    'vendor_id'  => $rfq['vendor_id'] ?? null,
                    'status'     => 'confirmed',
                    'subtotal'   => $rfq['subtotal'] ?? ($rfq['total'] ?? 0.0),
                    'total'      => $rfq['grand_total'] ?? ($rfq['total'] ?? 0.0),
                    'created_by' => session()->get('user_id') ?: null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'delivery_date' => $rfq['delivery_date'] ?? null,
                ];
                if (in_array('currency', $poCols)) {
                    $poInsert['currency'] = $rfq['currency'] ?? $this->getDefaultPurchaseCurrency();
                }
                if (in_array('currency_code', $poCols)) {
                    $poInsert['currency_code'] = $rfq['currency'] ?? $this->getDefaultPurchaseCurrency();
                }
                // Only include columns that actually exist in the table
                foreach ($poInsert as $k => $v) {
                    if (!empty($poCols) && !in_array($k, $poCols)) unset($poInsert[$k]);
                }

                log_message('debug', 'RFQ confirm → PO insert: ' . json_encode($poInsert));
                $poId = $poModel->insert($poInsert, true);
                if (!$poId) throw new \RuntimeException('PO insert failed');

                // 3. Copy RFQ lines → PO lines
                $rfqLineModel = new PurchaseRfqLineModel();
                $poLineModel  = new PurchaseOrderLineModel();
                $rfqLines     = $rfqLineModel->where('rfq_id', $rfqId)->findAll();
                $poLineCols   = [];
                try { $poLineCols = $db->getFieldNames('purchase_order_lines'); } catch (\Throwable $_) {}

                foreach ($rfqLines as $ln) {
                    $qty = isset($ln['quantity']) ? (float)$ln['quantity'] : (isset($ln['qty']) ? (float)$ln['qty'] : 0);
                    if ($qty <= 0) continue;

                    $lineInsert = [
                        'po_id'       => $poId,
                        'product_id'  => $ln['product_id'] ?? null,
                        'description' => $ln['description'] ?? null,
                        'qty'         => $qty,
                        'unit_price'  => isset($ln['unit_cost']) ? (float)$ln['unit_cost'] : (isset($ln['unit_price']) ? (float)$ln['unit_price'] : 0),
                        'qty_received'=> 0,
                        'created_at'  => date('Y-m-d H:i:s'),
                    ];
                    // Copy variant id if column exists
                    if (!empty($poLineCols) && in_array('variant_id', $poLineCols)) {
                        $vid = $ln['product_variant_id'] ?? ($ln['variant_id'] ?? null);
                        if (!empty($vid)) $lineInsert['variant_id'] = (int)$vid;
                    }

                    $poLineModel->insert($lineInsert);
                }
            }

            $db->transCommit();

            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_RFQ, $rfqId, DocumentLogger::ACTION_STATUS_CHANGED, ['from' => $rfq['status'], 'to' => 'confirmed']);
            if ($poId) { DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, (int)$poId, DocumentLogger::ACTION_CREATED); }

            return $this->response->setJSON([
                'success' => true,
                'message' => 'RFQ confirmed and Purchase Order created!',
                'po_id'   => $poId,
            ]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'RFQ confirm+PO creation failed: ' . $e->getMessage());
            log_message('error', $e->getTraceAsString());
            return $this->response->setStatusCode(500)->setJSON([
                'success' => false,
                'error'   => 'Failed to confirm RFQ: ' . $e->getMessage(),
            ]);
        }
    }

    // Cancel RFQ (soft cancel with reason)
    public function cancel($id = null)
    {
    if (strtolower($this->request->getMethod()) !== 'post') { $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'POST required']); $this->response->send(); exit; }
        $payload = $this->request->getJSON(true) ?: $this->request->getPost();
        $reason = $payload['reason'] ?? null;
        $model = new PurchaseRfqModel();
        $rfq = $model->findByPublicIdOrId($id);
        if (!$rfq) { $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']); $this->response->send(); exit; }
        $rfqId = (int)$rfq['id'];
        if ($rfq['status'] === 'cancelled') { $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'RFQ already cancelled']); $this->response->send(); exit; }

        $model->update($rfqId, [
            'status' => 'cancelled',
            'cancel_reason' => $reason,
            'cancelled_at' => date('Y-m-d H:i:s'),
            'cancelled_by' => session()->get('user_id')?:null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_RFQ, $rfqId, DocumentLogger::ACTION_CANCELLED,
            $reason ? ['reason' => $reason] : []);
        $this->response->setJSON(['success' => true]); $this->response->send(); exit;
    }
}
