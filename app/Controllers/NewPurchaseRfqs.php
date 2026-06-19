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
    private function extractLineVariantId(array $line): int
    {
        $primary = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;
        if ($primary > 0) {
            return $primary;
        }
        $fallback = isset($line['variant_id']) ? (int)$line['variant_id'] : 0;
        return $fallback > 0 ? $fallback : 0;
    }

    private function normalizeLineDescription($desc): string
    {
        $text = strtolower(trim((string)$desc));
        $text = preg_replace('/\s+/', ' ', $text ?? '');
        return trim((string)$text);
    }

    private function makeLegacyLineKey(array $line): string
    {
        $pid = isset($line['product_id']) ? (int)$line['product_id'] : 0;
        $desc = $this->normalizeLineDescription($line['description'] ?? '');
        return $pid . '|' . $desc;
    }

    private function getLegacyTemplateLineKeys(int $rfqId): array
    {
        if ($rfqId <= 0) {
            return [];
        }

        $keys = [];
        try {
            $rows = Database::connect()->table('purchase_rfq_lines')
                ->select('product_id, description, quantity, unit_cost, product_variant_id')
                ->where('rfq_id', $rfqId)
                ->groupStart()
                    ->where('product_variant_id', null)
                    ->orWhere('product_variant_id', 0)
                ->groupEnd()
                ->get()
                ->getResultArray();

            foreach ($rows as $row) {
                $key = $this->makeLegacyLineKey([
                    'product_id' => $row['product_id'] ?? null,
                    'description' => $row['description'] ?? null,
                ]);
                if ($key !== '0|') {
                    $keys[$key] = true;
                }
            }
        } catch (\Throwable $_) {
            return [];
        }

        return $keys;
    }

    private function filterLegacyMissingVariantIssues(array $issues, array $submittedLines, array $legacyKeys): array
    {
        if (empty($issues['missing']) || empty($legacyKeys)) {
            return $issues;
        }

        $remaining = [];
        foreach ($issues['missing'] as $issue) {
            $lineNo = (int)($issue['line_no'] ?? 0);
            $idx = max(0, $lineNo - 1);
            $line = $submittedLines[$idx] ?? null;
            if (!is_array($line)) {
                $remaining[] = $issue;
                continue;
            }

            $key = $this->makeLegacyLineKey($line);
            if (!isset($legacyKeys[$key])) {
                $remaining[] = $issue;
            }
        }

        $issues['missing'] = $remaining;
        return $issues;
    }

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
     * Validate that products requiring variants cannot be saved as template products.
     *
     * @param array $lines
     * @return array{missing: array<int,array<string,mixed>>, invalid: array<int,array<string,mixed>>}
     */
    private function validateVariantSelections(array $lines): array
    {
        $issues = ['missing' => [], 'invalid' => []];
        if (empty($lines)) {
            return $issues;
        }

        $productIds = [];
        $variantIds = [];
        foreach ($lines as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
            $vid = $this->extractLineVariantId($ln);
            if ($pid > 0) {
                $productIds[] = $pid;
            }
            if ($vid > 0) {
                $variantIds[] = $vid;
            }
        }

        $productIds = array_values(array_unique($productIds));
        $variantIds = array_values(array_unique($variantIds));
        if (empty($productIds)) {
            return $issues;
        }

        $db = Database::connect();
        $variantRequiredMap = [];
        $variantOwners = [];
        $productLabels = [];

        try {
            if ($db->tableExists('products')) {
                $rows = $db->table('products')->select('id, name, code')->whereIn('id', $productIds)->get()->getResultArray();
                foreach ($rows as $row) {
                    $pid = (int)($row['id'] ?? 0);
                    if ($pid <= 0) {
                        continue;
                    }
                    $label = trim((string)($row['name'] ?? ''));
                    if ($label === '') {
                        $label = trim((string)($row['code'] ?? ''));
                    }
                    $productLabels[$pid] = $label !== '' ? $label : ('Product #' . $pid);
                }
            }

            if ($db->tableExists('product_variants')) {
                $rows = $db->table('product_variants')
                    ->select('product_id, COUNT(*) AS cnt')
                    ->whereIn('product_id', $productIds)
                    ->groupBy('product_id')
                    ->get()
                    ->getResultArray();
                foreach ($rows as $row) {
                    $pid = (int)($row['product_id'] ?? 0);
                    $cnt = (int)($row['cnt'] ?? 0);
                    if ($pid > 0 && $cnt > 0) {
                        $variantRequiredMap[$pid] = true;
                    }
                }

                if (!empty($variantIds)) {
                    $ownerRows = $db->table('product_variants')->select('id, product_id')->whereIn('id', $variantIds)->get()->getResultArray();
                    foreach ($ownerRows as $row) {
                        $vid = (int)($row['id'] ?? 0);
                        if ($vid > 0) {
                            $variantOwners[$vid] = (int)($row['product_id'] ?? 0);
                        }
                    }
                }
            }
        } catch (\Throwable $_) {
            return $issues;
        }

        foreach ($lines as $idx => $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $pid = isset($ln['product_id']) ? (int)$ln['product_id'] : 0;
            $vid = $this->extractLineVariantId($ln);
            if ($pid <= 0) {
                continue;
            }

            $label = (string)($productLabels[$pid] ?? ('Product #' . $pid));
            if (!empty($variantRequiredMap[$pid]) && $vid <= 0) {
                $issues['missing'][] = [
                    'line_no' => (int)$idx + 1,
                    'label' => $label,
                ];
                continue;
            }

            if ($vid > 0) {
                $ownerPid = isset($variantOwners[$vid]) ? (int)$variantOwners[$vid] : 0;
                if ($ownerPid <= 0 || $ownerPid !== $pid) {
                    $issues['invalid'][] = [
                        'line_no' => (int)$idx + 1,
                        'label' => $label,
                    ];
                }
            }
        }

        return $issues;
    }

    private function buildVariantValidationMessage(array $issues): string
    {
        $parts = [];
        if (!empty($issues['missing'])) {
            $labels = array_values(array_unique(array_map(static fn($x) => (string)($x['label'] ?? ''), $issues['missing'])));
            $parts[] = 'Variant selection is required for: ' . implode(', ', array_slice($labels, 0, 8)) . (count($labels) > 8 ? '...' : '');
        }
        if (!empty($issues['invalid'])) {
            $labels = array_values(array_unique(array_map(static fn($x) => (string)($x['label'] ?? ''), $issues['invalid'])));
            $parts[] = 'Some selected variants do not belong to their products: ' . implode(', ', array_slice($labels, 0, 8)) . (count($labels) > 8 ? '...' : '');
        }

        $base = 'Template product cannot be used when variants exist. Please select a specific variant (ART/code) for each variant-based product.';
        if (!empty($parts)) {
            $base .= ' ' . implode(' ', $parts);
        }
        return $base;
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
                    'product_variant_id' => (($this->extractLineVariantId($ln)) > 0 ? $this->extractLineVariantId($ln) : null),
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

            $variantIssues = $this->validateVariantSelections($normalizedLines);
            if (!empty($variantIssues['missing']) || !empty($variantIssues['invalid'])) {
                return $this->response->setStatusCode(422)->setJSON([
                    'success' => false,
                    'error' => $this->buildVariantValidationMessage($variantIssues),
                ]);
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
        $lineOrderField = 'id';
        try {
            $db = Database::connect();
            if ($db->fieldExists('sort_order', 'purchase_rfq_lines')) {
                $lineOrderField = 'sort_order';
            }
        } catch (\Throwable $_) {
            $lineOrderField = 'id';
        }
        $lines = $lineModel->where('rfq_id', $rfqId)->orderBy($lineOrderField, 'ASC')->orderBy('id', 'ASC')->findAll();

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

        $db = Database::connect();

        $variantIds = array_values(array_filter(array_unique(array_map(
            fn($l) => !empty($l['product_variant_id']) ? (int)$l['product_variant_id'] : (!empty($l['variant_id']) ? (int)$l['variant_id'] : null),
            $lines
        ))));
        $productIds = array_values(array_filter(array_unique(array_map(
            fn($l) => !empty($l['product_id']) ? (int)$l['product_id'] : null,
            $lines
        ))));

        $variantMeta = [];
        if (!empty($variantIds) && $db->tableExists('product_variants')) {
            try {
                $rows = $db->table('product_variants')
                    ->select('id, art_number, name')
                    ->whereIn('id', $variantIds)
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $variantMeta[(int)$r['id']] = [
                        'code' => (string)($r['art_number'] ?? ''),
                        'name' => (string)($r['name'] ?? ''),
                    ];
                }
            } catch (\Throwable $_) {}
        }

        $productMeta = [];
        if (!empty($productIds) && $db->tableExists('products')) {
            try {
                $rows = $db->table('products')
                    ->select('id, code, name')
                    ->whereIn('id', $productIds)
                    ->get()->getResultArray();
                foreach ($rows as $r) {
                    $productMeta[(int)$r['id']] = [
                        'code' => (string)($r['code'] ?? ''),
                        'name' => (string)($r['name'] ?? ''),
                    ];
                }
            } catch (\Throwable $_) {}
        }

        $pdfLines = [];
        foreach ($lines as $line) {
            $qty = (float)($line['quantity'] ?? ($line['qty'] ?? 0));
            $unitPrice = (float)($line['unit_cost'] ?? ($line['unit_price'] ?? 0));
            $lineTotal = isset($line['line_total']) ? (float)$line['line_total'] : ($qty * $unitPrice);

            $vid = !empty($line['product_variant_id']) ? (int)$line['product_variant_id'] : (!empty($line['variant_id']) ? (int)$line['variant_id'] : null);
            $pid = !empty($line['product_id']) ? (int)$line['product_id'] : null;
            $resolvedCode = ($vid && isset($variantMeta[$vid]))
                ? ($variantMeta[$vid]['code'] ?? '')
                : (($pid && isset($productMeta[$pid])) ? ($productMeta[$pid]['code'] ?? '') : '');

            $resolvedDescription = trim((string)($line['description'] ?? ''));
            if ($resolvedDescription === '') {
                $resolvedDescription = ($vid && isset($variantMeta[$vid]))
                    ? trim((string)($variantMeta[$vid]['name'] ?? ''))
                    : (($pid && isset($productMeta[$pid])) ? trim((string)($productMeta[$pid]['name'] ?? '')) : '');
            }

            $pdfLines[] = [
                'id' => $line['id'] ?? null,
                'product_id' => $pid,
                'product_variant_id' => $vid,
                'product_code' => $resolvedCode,
                'description' => $resolvedDescription,
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

    public function printView($id = null)
    {
        $rfq = (new PurchaseRfqModel())->findByPublicIdOrId($id);
        if (!$rfq) {
            return redirect()->back()->with('error', 'RFQ not found');
        }
        $rfqId = (int) $rfq['id'];

        $db = Database::connect();
        $vendor = [];
        try {
            $vendor = $db->table('vendors')->where('id', (int) ($rfq['vendor_id'] ?? 0))->get()->getRowArray() ?: [];
        } catch (\Throwable $_) {}

        $lines = (new PurchaseRfqLineModel())->where('rfq_id', $rfqId)->orderBy('id', 'ASC')->findAll();

        try {
            $productIds = array_values(array_filter(array_unique(array_map(function ($line) {
                return isset($line['product_id']) ? (int) $line['product_id'] : null;
            }, $lines))));
            $variantIds = array_values(array_filter(array_unique(array_map(function ($line) {
                if (isset($line['product_variant_id']) && $line['product_variant_id']) {
                    return (int) $line['product_variant_id'];
                }
                if (isset($line['variant_id']) && $line['variant_id']) {
                    return (int) $line['variant_id'];
                }
                return null;
            }, $lines))));

            $prodMap = [];
            $variantMap = [];

            if (!empty($productIds)) {
                $productModel = new \App\Models\ProductModel();
                $products = $productModel->whereIn('id', $productIds)->findAll();
                foreach ($products as $product) {
                    $prodMap[(int) $product['id']] = $product;
                }
            }

            if (!empty($variantIds) && $db->tableExists('product_variants')) {
                try {
                    $variants = $db->table('product_variants')
                        ->select('id, product_id, art_number, name, image')
                        ->whereIn('id', $variantIds)
                        ->get()
                        ->getResultArray();
                    foreach ($variants as $variant) {
                        $variantMap[(int) $variant['id']] = $variant;
                    }
                } catch (\Throwable $_) {}
            }

            if (!empty($variantMap)) {
                $missingProductIds = [];
                foreach ($variantMap as $variant) {
                    $variantProductId = isset($variant['product_id']) ? (int) $variant['product_id'] : 0;
                    if ($variantProductId > 0 && !isset($prodMap[$variantProductId])) {
                        $missingProductIds[] = $variantProductId;
                    }
                }
                $missingProductIds = array_values(array_unique($missingProductIds));
                if (!empty($missingProductIds)) {
                    try {
                        $productModel = $productModel ?? new \App\Models\ProductModel();
                        $products = $productModel->whereIn('id', $missingProductIds)->findAll();
                        foreach ($products as $product) {
                            $prodMap[(int) $product['id']] = $product;
                        }
                    } catch (\Throwable $_) {}
                }
            }

            foreach ($lines as &$line) {
                $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                $variantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : (isset($line['variant_id']) ? (int) $line['variant_id'] : null);

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
                }
            }
            unset($line);
        } catch (\Throwable $_) {
        }

        $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?: [];
        $printLines = [];
        foreach ($lines as $line) {
            $qty = (float) ($line['quantity'] ?? ($line['qty'] ?? 0));
            $price = (float) ($line['unit_cost'] ?? ($line['unit_price'] ?? 0));
            $storedTotal = isset($line['line_total']) ? (float) $line['line_total'] : 0.0;
            $total = $storedTotal > 0 ? $storedTotal : ($qty * $price);
            $code = trim((string) ($line['variant_code'] ?? ($line['product_code'] ?? '')));
            $desc = trim((string) ($line['variant_name'] ?? ($line['product_name'] ?? ($line['description'] ?? ''))));
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

        $currency = strtoupper(trim((string) ($rfq['currency'] ?? ($company['base_currency'] ?? 'PKR'))));
        $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
        $sym = $symbols[$currency] ?? $currency;
        $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

        $subtotal = (float) ($rfq['subtotal'] ?? 0);
        $total = (float) ($rfq['grand_total'] ?? 0);
        $rfqNumber = esc($rfq['rfq_number'] ?? ('RFQ-' . $rfqId));
        $rfqDate = '';
        $rawDate = trim((string) ($rfq['rfq_date'] ?? ($rfq['created_at'] ?? '')));
        if ($rawDate && strpos($rawDate, '0000') === false) {
            $ts = strtotime($rawDate);
            if ($ts) {
                $rfqDate = date('d-m-Y', $ts);
            }
        }
        $vendorName = esc($vendor['name'] ?? 'Vendor');
        $companyName = esc($company['name'] ?? '');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>RFQ <?= $rfqNumber ?></title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
  .grn-doc{max-width:1100px;margin:0 auto}
  .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
  .grn-hero::after{content:'RFQ';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:6rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
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
    <div class="grn-doc-type">Request for Quotation</div>
    <div class="grn-hero-num"><?= $rfqNumber ?></div>
    <div class="grn-hero-sub"><?= $companyName ?></div>
    <div class="grn-hero-actions no-print">
      <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
      <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
    </div>
  </div>

  <div class="grn-facts">
    <div class="grn-fact"><div class="grn-fact-lbl">Vendor</div><div class="grn-fact-val"><?= $vendorName ?></div></div>
    <div class="grn-fact"><div class="grn-fact-lbl">RFQ Date</div><div class="grn-fact-val"><?= esc($rfqDate ?: '-') ?></div></div>
    <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
    <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
  </div>

  <div class="grn-sec">
    <div class="grn-sec-hd">RFQ Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
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
      <tr class="grand"><td class="lbl">Total</td><td class="val"><?= esc($fmt($total)) ?></td></tr>
    </table>
  </div>
</div>
</body>
</html>
        <?php
        return $this->response->setBody(ob_get_clean())->setHeader('Content-Type', 'text/html; charset=utf-8');
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

        foreach ($lines as &$ln) {
            if (!is_array($ln)) {
                continue;
            }
            if (empty($ln['product_variant_id']) && !empty($ln['variant_id'])) {
                $ln['product_variant_id'] = (int)$ln['variant_id'];
            }
        }
        unset($ln);

        $variantIssues = $this->validateVariantSelections($lines);
        $legacyKeys = $this->getLegacyTemplateLineKeys($rfqId);
        if (!empty($legacyKeys)) {
            $variantIssues = $this->filterLegacyMissingVariantIssues($variantIssues, $lines, $legacyKeys);
        }
        if (!empty($variantIssues['missing']) || !empty($variantIssues['invalid'])) {
            return $this->response->setStatusCode(422)->setJSON([
                'success' => false,
                'error' => $this->buildVariantValidationMessage($variantIssues),
            ]);
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
                    'product_variant_id' => (($this->extractLineVariantId($ln)) > 0 ? $this->extractLineVariantId($ln) : null),
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
                // Resolve PO number: prefer the RFQ number for continuity, but fall back to a fresh
                // generated number if that value is already taken by another PO (e.g. a shipping PO
                // created by the delivery-order flow using an incomplete sequence scan).
                $candidatePoNumber = $rfq['rfq_number'] ?? null;
                if ($candidatePoNumber !== null) {
                    $collision = (int)$db->table('purchase_orders')
                        ->where('po_number', $candidatePoNumber)
                        ->countAllResults();
                    if ($collision > 0) {
                        log_message('warning', "NewPurchaseRfqs::accept - po_number collision for '{$candidatePoNumber}' (rfq_id={$rfqId}); generating a new number.");
                        $candidatePoNumber = $this->generateNextRfqNumber($db);
                    }
                }
                $poInsert = [
                    // Use RFQ number when possible; collision-safe fallback to next available
                    'po_number' => $candidatePoNumber,
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

    /**
     * Delete RFQ and its lines permanently.
     * POST required.
     */
    public function delete($id = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'POST required']);
        }

        $model = new \App\Models\PurchaseRfqModel();
        $lineModel = new \App\Models\PurchaseRfqLineModel();
        $rfq = $model->findByPublicIdOrId($id);
        if (!$rfq) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'RFQ not found']);
        }
        $rfqId = (int)$rfq['id'];

        $db = \Config\Database::connect();
        $db->transBegin();
        try {
            // delete lines then header
            $lineModel->where('rfq_id', $rfqId)->delete();
            $model->delete($rfqId);
            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_RFQ, $rfqId, DocumentLogger::ACTION_DELETED ?? 'deleted');
            $db->transCommit();
            return $this->response->setJSON(['success' => true]);
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'RFQ delete failed: ' . $e->getMessage());
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to delete RFQ']);
        }
    }
}
