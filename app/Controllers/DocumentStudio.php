<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ProductCategoryModel;
use App\Models\CustomerModel;
use App\Models\VendorModel;
use App\Models\QuotationModel;
use App\Models\QuotationLineModel;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\PurchaseRfqModel;
use App\Models\PurchaseRfqLineModel;
use App\Models\PurchaseOrderModel;
use App\Models\PurchaseOrderLineModel;
use App\Libraries\RoleDataAccess;
use App\Libraries\DocumentLogger;
use App\Models\CompanySettingsModel;
use Config\Database;

class DocumentStudio extends BaseController
{
    protected $productModel;
    protected $categoryModel;
    protected $customerModel;
    protected $vendorModel;

    public function __construct()
    {
        $this->productModel  = new ProductModel();
        $this->categoryModel = new ProductCategoryModel();
        $this->customerModel = new CustomerModel();
        $this->vendorModel   = new VendorModel();
        helper(['form', 'url']);
    }

    /* ─── helpers ─── */

    private function normalizeDate(?string $val): string
    {
        $val = trim((string)$val);
        if ($val === '') return date('Y-m-d');
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        return $val;
    }

    private function getDefaultSalesCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_sales_currency'])) return $company['default_sales_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
        } catch (\Throwable $_) {}
        return 'USD';
    }

    private function getDefaultPurchaseCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_purchase_currency'])) return $company['default_purchase_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
        } catch (\Throwable $_) {}
        return 'PKR';
    }

    private function getPurchaseDocumentPrefix(): string
    {
        $default = 'RI-PO-';
        try {
            $db = Database::connect();
            $row = $db->query('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1', ['purchase_rfq_prefix'])->getRowArray();
            $val = isset($row['setting_value']) ? trim((string) $row['setting_value']) : '';
            return $val !== '' ? $val : $default;
        } catch (\Throwable $_) {}

        return $default;
    }

    private function generateNextPurchaseDocumentNumber(\CodeIgniter\Database\BaseConnection $db): string
    {
        $prefix = $this->getPurchaseDocumentPrefix();
        $lastNumber = 0;

        try {
            $rfqRow = $db->query(
                'SELECT rfq_number AS doc_number FROM purchase_rfqs WHERE rfq_number LIKE ? ORDER BY rfq_number DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($rfqRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $rfqRow['doc_number']));
            }
        } catch (\Throwable $_) {}

        try {
            $poRow = $db->query(
                'SELECT po_number AS doc_number FROM purchase_orders WHERE po_number LIKE ? ORDER BY po_number DESC LIMIT 1 FOR UPDATE',
                [$prefix . '%']
            )->getRowArray();
            if (!empty($poRow['doc_number'])) {
                $lastNumber = max($lastNumber, (int) preg_replace('/[^0-9]/', '', (string) $poRow['doc_number']));
            }
        } catch (\Throwable $_) {}

        return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
    }

    /* ═══════════════════ Main Page ═══════════════════ */

    public function index()
    {
        $categories = [];
        try {
            $categories = $this->categoryModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        } catch (\Throwable $_) {}

        $products = [];
        try {
            $products = $this->productModel->where('is_active', 1)->orderBy('name', 'ASC')->findAll();
        } catch (\Throwable $_) {}

        $catalogItems = [];
        $db = Database::connect();

        $productMap = [];
        foreach ($products as $p) {
            $pid = (int)($p['id'] ?? 0);
            if ($pid > 0) {
                $productMap[$pid] = $p;
            }
        }

        $stockByProduct = [];
        $stockByVariant = [];
        try {
            if ($db->tableExists('stock_balances')) {
                $sbCols = $db->getFieldNames('stock_balances');
                $hasVariantInStock = in_array('variant_id', $sbCols, true);

                if ($hasVariantInStock) {
                    $rows = $db->table('stock_balances')
                        ->select('product_id, variant_id, SUM(quantity) as qty')
                        ->groupBy('product_id, variant_id')
                        ->get()->getResultArray();
                    foreach ($rows as $row) {
                        $pid = (int)($row['product_id'] ?? 0);
                        $vid = (int)($row['variant_id'] ?? 0);
                        $qty = (float)($row['qty'] ?? 0);
                        if ($pid > 0 && $vid === 0) {
                            $stockByProduct[$pid] = $qty;
                        }
                        if ($vid > 0) {
                            $stockByVariant[$vid] = $qty;
                        }
                    }
                } else {
                    $rows = $db->table('stock_balances')
                        ->select('product_id, SUM(quantity) as qty')
                        ->groupBy('product_id')
                        ->get()->getResultArray();
                    foreach ($rows as $row) {
                        $pid = (int)($row['product_id'] ?? 0);
                        if ($pid > 0) {
                            $stockByProduct[$pid] = (float)($row['qty'] ?? 0);
                        }
                    }
                }
            }
        } catch (\Throwable $_) {}

        // Count variants per product to identify template products
        $variantCounts = [];
        try {
            if ($db->tableExists('product_variants')) {
                $vcRows = $db->table('product_variants')
                    ->select('product_id, COUNT(id) as cnt')
                    ->groupBy('product_id')
                    ->get()->getResultArray();
                foreach ($vcRows as $vc) {
                    $variantCounts[(int)$vc['product_id']] = (int)$vc['cnt'];
                }
            }
        } catch (\Throwable $_) {}

        // Helper to resolve product image URL from JSON images field
        $resolveImage = function($p) {
            $noImg = base_url('assets/images/no-image.png');
            $raw = $p['images'] ?? null;
            if (empty($raw)) return $noImg;
            $imgs = is_string($raw) ? json_decode($raw, true) : $raw;
            if (is_array($imgs) && !empty($imgs[0])) {
                return base_url('uploads/products/' . ltrim($imgs[0], '/'));
            }
            return $noImg;
        };

        foreach ($products as $p) {
            $pid = (int)($p['id'] ?? 0);
            $stock = $stockByProduct[$pid] ?? (float)($p['current_stock'] ?? 0);
            $hasVariants = ($variantCounts[$pid] ?? 0) > 0;
            $catalogItems[] = [
                'product_id'   => $pid,
                'variant_id'   => null,
                'is_variant'   => false,
                'is_template'  => $hasVariants,
                'name'         => (string)($p['name'] ?? ''),
                'code'         => (string)($p['code'] ?? ($p['sku'] ?? '')),
                'barcode'      => (string)($p['barcode'] ?? ''),
                'sale_price'   => (float)($p['sale_price'] ?? 0),
                'cost_price'   => (float)($p['cost_price'] ?? ($p['unit_cost'] ?? 0)),
                'unit'         => (string)($p['unit'] ?? 'pcs'),
                'stock'        => (float)$stock,
                'category_id'  => $p['category_id'] ?? null,
                'image_url'    => $resolveImage($p),
            ];
        }

        try {
            if ($db->tableExists('product_variants')) {
                $pvCols = $db->getFieldNames('product_variants');
                $selectCols = ['id', 'product_id'];
                foreach (['name', 'art_number', 'sale_price', 'cost_price', 'price', 'cost'] as $col) {
                    if (in_array($col, $pvCols, true)) $selectCols[] = $col;
                }

                $variantRows = $db->table('product_variants')
                    ->select(implode(',', $selectCols))
                    ->orderBy(in_array('art_number', $pvCols, true) ? 'art_number' : 'id', 'ASC')
                    ->get()->getResultArray();

                foreach ($variantRows as $v) {
                    $variantId = (int)($v['id'] ?? 0);
                    $productId = (int)($v['product_id'] ?? 0);
                    if ($variantId <= 0 || $productId <= 0 || !isset($productMap[$productId])) {
                        continue;
                    }

                    $base = $productMap[$productId];
                    $variantName = trim((string)($v['name'] ?? ''));
                    $variantCode = trim((string)($v['art_number'] ?? ''));
                    $displayName = trim((string)($base['name'] ?? ''));
                    if ($variantName !== '') {
                        $displayName .= ' — ' . $variantName;
                    }

                    $salePrice = isset($v['sale_price']) ? (float)$v['sale_price'] : (isset($v['price']) ? (float)$v['price'] : (float)($base['sale_price'] ?? 0));
                    $costPrice = isset($v['cost_price']) ? (float)$v['cost_price'] : (isset($v['cost']) ? (float)$v['cost'] : (float)($base['cost_price'] ?? ($base['unit_cost'] ?? 0)));
                    $stock = $stockByVariant[$variantId] ?? ($stockByProduct[$productId] ?? (float)($base['current_stock'] ?? 0));

                    $catalogItems[] = [
                        'product_id'   => $productId,
                        'variant_id'   => $variantId,
                        'is_variant'   => true,
                        'is_template'  => false,
                        'name'         => $displayName,
                        'variant_name' => $variantName,
                        'code'         => $variantCode !== '' ? $variantCode : (string)($base['code'] ?? ($base['sku'] ?? '')),
                        'barcode'      => (string)($base['barcode'] ?? ''),
                        'sale_price'   => $salePrice,
                        'cost_price'   => $costPrice,
                        'unit'         => (string)($base['unit'] ?? 'pcs'),
                        'stock'        => (float)$stock,
                        'category_id'  => $base['category_id'] ?? null,
                        'image_url'    => $resolveImage($base),
                    ];
                }
            }
        } catch (\Throwable $_) {}

        $company = null;
        try { $company = (new CompanySettingsModel())->first(); } catch (\Throwable $_) {}

        // ── Smart sorting: recently sold/quoted first, then recently added ──
        $productSoldRecency = [];
        try {
            // Check quotation_lines and sales_order_lines for recent activity
            $recentTables = [];
            if ($db->tableExists('quotation_lines')) $recentTables[] = 'quotation_lines';
            if ($db->tableExists('sales_order_lines')) $recentTables[] = 'sales_order_lines';
            if ($db->tableExists('customer_invoice_lines')) $recentTables[] = 'customer_invoice_lines';

            foreach ($recentTables as $tbl) {
                $cols = $db->getFieldNames($tbl);
                $hasVariant = in_array('product_variant_id', $cols, true) || in_array('variant_id', $cols, true);
                $variantCol = in_array('product_variant_id', $cols, true) ? 'product_variant_id' : (in_array('variant_id', $cols, true) ? 'variant_id' : null);
                $hasPid = in_array('product_id', $cols, true);
                $hasCreated = in_array('created_at', $cols, true);

                if (!$hasPid || !$hasCreated) continue;

                $rows = $db->table($tbl)
                    ->select('product_id' . ($hasVariant ? ", {$variantCol} as vid" : ', 0 as vid') . ', MAX(created_at) as last_used')
                    ->groupBy('product_id' . ($hasVariant ? ", {$variantCol}" : ''))
                    ->get()->getResultArray();

                foreach ($rows as $r) {
                    $pid = (int)($r['product_id'] ?? 0);
                    $vid = (int)($r['vid'] ?? 0);
                    $key = $pid . ':' . $vid;
                    $ts  = strtotime($r['last_used'] ?? '2000-01-01');
                    if (!isset($productSoldRecency[$key]) || $ts > $productSoldRecency[$key]) {
                        $productSoldRecency[$key] = $ts;
                    }
                }
            }
        } catch (\Throwable $_) {}

        // Sort catalog items: recently used → recently created → alphabetical
        usort($catalogItems, function ($a, $b) use ($productSoldRecency) {
            $keyA = ($a['product_id'] ?? 0) . ':' . ($a['variant_id'] ?? 0);
            $keyB = ($b['product_id'] ?? 0) . ':' . ($b['variant_id'] ?? 0);
            $soldA = $productSoldRecency[$keyA] ?? 0;
            $soldB = $productSoldRecency[$keyB] ?? 0;

            // Templates always go after non-templates
            $tplA = !empty($a['is_template']) ? 1 : 0;
            $tplB = !empty($b['is_template']) ? 1 : 0;
            if ($tplA !== $tplB) return $tplA - $tplB;

            // Recently sold items first
            if ($soldA !== $soldB) return $soldB <=> $soldA;

            // Then by product_id descending (recently added)
            $pidA = (int)($a['product_id'] ?? 0);
            $pidB = (int)($b['product_id'] ?? 0);
            if ($pidA !== $pidB) return $pidB <=> $pidA;

            // Variants by variant_id descending
            return ($b['variant_id'] ?? 0) <=> ($a['variant_id'] ?? 0);
        });

        return view('document_studio/index', [
            'title'      => 'Document Studio',
            'categories' => $categories,
            'products'   => $products,
            'catalogItems' => $catalogItems,
            'company'    => $company,
            'defaultSalesCurrency'    => $this->getDefaultSalesCurrency(),
            'defaultPurchaseCurrency' => $this->getDefaultPurchaseCurrency(),
        ]);
    }

    /* ═══════════════════ AJAX: search products ═══════════════════ */

    public function searchProducts()
    {
        $q = trim((string)$this->request->getGet('q'));
        if ($q === '') return $this->response->setJSON([]);

        $db = Database::connect();

        $candidates = method_exists($this->productModel, 'searchProducts')
            ? $this->productModel->searchProducts($q)
            : $this->productModel->like('name', $q)->orLike('code', $q)->limit(20)->find();

        // stock balances
        $stockMap = [];
        if (!empty($candidates)) {
            $ids = array_values(array_unique(array_filter(array_column($candidates, 'id'))));
            if (!empty($ids)) {
                try {
                    $stockRows = $db->table('stock_balances')
                        ->select('product_id, SUM(quantity) as qty')
                        ->whereIn('product_id', $ids)
                        ->groupBy('product_id')
                        ->get()->getResultArray();
                    foreach ($stockRows as $sr) {
                        $stockMap[(int)$sr['product_id']] = (float)$sr['qty'];
                    }
                } catch (\Throwable $_) {}
            }
        }

        $results = [];
        foreach ($candidates as $p) {
            $pid = (int)($p['id'] ?? 0);
            $results[] = [
                'id'          => $pid,
                'code'        => $p['code'] ?? ($p['sku'] ?? ''),
                'name'        => $p['name'] ?? '',
                'description' => $p['description'] ?? '',
                'unit'        => $p['unit'] ?? 'pcs',
                'sale_price'  => (float)($p['sale_price'] ?? 0),
                'cost_price'  => (float)($p['cost_price'] ?? ($p['unit_cost'] ?? 0)),
                'stock'       => $stockMap[$pid] ?? (float)($p['current_stock'] ?? 0),
                'category_id' => $p['category_id'] ?? null,
                'variant_id'  => isset($p['variant_id']) ? (int)$p['variant_id'] : null,
                'variant_name'=> $p['variant_name'] ?? null,
            ];
        }
        return $this->response->setJSON($results);
    }

    /* ═══════════════════ AJAX: search customers ═══════════════════ */

    public function searchCustomers()
    {
        $q = trim((string)$this->request->getGet('q'));
        if ($q === '') return $this->response->setJSON([]);

        $rows = $this->customerModel
            ->groupStart()
                ->like('customer_code', $q)
                ->orLike('name', $q)
                ->orLike('company_name', $q)
            ->groupEnd()
            ->limit(20)->find();

        $out = [];
        foreach ($rows as $c) {
            $out[] = [
                'id'      => $c['id'],
                'code'    => $c['customer_code'] ?? '',
                'name'    => $c['name'] ?? '',
                'company' => $c['company_name'] ?? '',
                'email'   => $c['email'] ?? '',
            ];
        }
        return $this->response->setJSON($out);
    }

    /* ═══════════════════ AJAX: search vendors ═══════════════════ */

    public function searchVendors()
    {
        $q = trim((string)$this->request->getGet('q'));
        if ($q === '') return $this->response->setJSON([]);

        $rows = $this->vendorModel
            ->groupStart()
                ->like('name', $q)
                ->orLike('contact_person', $q)
            ->groupEnd()
            ->where('is_active', 1)
            ->limit(20)->find();

        $out = [];
        foreach ($rows as $v) {
            $out[] = [
                'id'             => $v['id'],
                'name'           => $v['name'] ?? '',
                'contact_person' => $v['contact_person'] ?? '',
            ];
        }
        return $this->response->setJSON($out);
    }

    /* ═══════════════════ LOAD: Quotation for editing ═══════════════════ */

    public function loadQuotation($id = null)
    {
        $id = (int)$id;
        if (!$id) return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid id']);

        try {
            $model = new QuotationModel();
            $quote = $model->getWithLines($id);
            if (!$quote) return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Quotation not found']);

            // Resolve customer label
            $customerLabel = '';
            $customerId = (int)($quote['customer_id'] ?? 0);
            if ($customerId) {
                try {
                    $cust = $this->customerModel->find($customerId);
                    if ($cust) {
                        $code = $cust['customer_code'] ?? ($cust['code'] ?? '');
                        $name = $cust['name'] ?? ($cust['company_name'] ?? '');
                        $customerLabel = $code ? ($code . ' - ' . $name) : $name;
                    }
                } catch (\Throwable $_) {}
            }

            // Format lines for Document Studio
            $lines = [];
            foreach (($quote['lines'] ?? []) as $ln) {
                $lines[] = [
                    'id'             => (int)($ln['id'] ?? 0),
                    'product_id'     => $ln['product_id'] ? (int)$ln['product_id'] : null,
                    'variant_id'     => $ln['product_variant_id'] ?? null,
                    'product_code'   => $ln['product_code'] ?? '',
                    'product_name'   => $ln['product_name'] ?? ($ln['description'] ?? ''),
                    'description'    => $ln['description'] ?? ($ln['product_name'] ?? ''),
                    'unit'           => $ln['unit'] ?? 'pcs',
                    'quantity'       => (float)($ln['quantity'] ?? 0),
                    'unit_price'     => (float)($ln['unit_price'] ?? 0),
                    'discount_type'  => $ln['discount_type'] ?? 'fixed',
                    'discount_value' => (float)($ln['discount_value'] ?? 0),
                    'tax_rate'       => (float)($ln['tax_rate'] ?? 0),
                    'image_url'      => $ln['product_image_url'] ?? '',
                ];
            }

            $issueDate = $quote['issue_date'] ?? date('Y-m-d');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $issueDate)) {
                $issueDate = date('d-m-Y', strtotime($issueDate));
            }

            return $this->response->setJSON([
                'success'        => true,
                'id'             => $id,
                'quote_number'   => $quote['quote_number'] ?? '',
                'customer_id'    => $customerId,
                'customer_label' => $customerLabel,
                'issue_date'     => $issueDate,
                'currency'       => $quote['currency'] ?? ($quote['quote_currency'] ?? 'USD'),
                'status'         => $quote['status'] ?? 'draft',
                'notes'          => $quote['notes'] ?? '',
                'shipping_amount'=> (float)($quote['shipping_amount'] ?? 0),
                'lines'          => $lines,
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ═══════════════════ UPDATE: Quotation ═══════════════════ */

    public function updateQuotation($id = null)
    {
        $id = (int)$id;
        if (!$id) return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid id']);

        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            $model = new QuotationModel();
            $lineModel = new QuotationLineModel();
            $quote = $model->find($id);
            if (!$quote) return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Quotation not found']);

            $isDraft = !empty($post['is_draft']);
            $customerId = (int)($post['customer_id'] ?? 0);
            if (!$customerId && !$isDraft) throw new \RuntimeException('Please select a customer');

            $currency = strtoupper(trim((string)($post['currency'] ?? '')));
            if ($currency === '') $currency = $this->getDefaultSalesCurrency();
            $shippingAmount = (float)($post['shipping_amount'] ?? 0);
            $status = $isDraft ? 'draft' : ($quote['status'] ?? 'draft');

            $db = Database::connect();
            try { $cols = $db->getFieldNames($model->table); } catch (\Throwable $_) { $cols = $model->allowedFields; }

            $lines = [];
            if (isset($post['lines']) && is_array($post['lines'])) {
                foreach ($post['lines'] as $ln) {
                    if (!is_array($ln)) continue;
                    $lines[] = [
                        'id'            => isset($ln['line_id']) ? (int)$ln['line_id'] : 0,
                        'product_id'    => $ln['product_id'] ?? null,
                        'product_variant_id' => isset($ln['variant_id']) && $ln['variant_id'] !== '' ? (int)$ln['variant_id'] : null,
                        'product_code'  => $ln['product_code'] ?? '',
                        'product_name'  => $ln['product_name'] ?? ($ln['description'] ?? ''),
                        'description'   => $ln['description'] ?? ($ln['product_name'] ?? ''),
                        'unit'          => $ln['unit'] ?? 'pcs',
                        'quantity'      => (float)($ln['quantity'] ?? 0),
                        'unit_price'    => (float)($ln['unit_price'] ?? 0),
                        'discount_type' => $ln['discount_type'] ?? 'fixed',
                        'discount_value'=> (float)($ln['discount_value'] ?? 0),
                        'tax_rate'      => (float)($ln['tax_rate'] ?? 0),
                    ];
                }
            }

            $db->transStart();

            // Update header
            $headerUpd = [
                'customer_id'   => $customerId ?: ($quote['customer_id'] ?? null),
                'issue_date'    => $this->normalizeDate($post['issue_date'] ?? ''),
                'status'        => $status,
            ];
            if (in_array('notes', $cols))            $headerUpd['notes'] = $post['notes'] ?? ($quote['notes'] ?? null);
            if (in_array('currency', $cols))         $headerUpd['currency'] = $currency;
            if (in_array('quote_currency', $cols))   $headerUpd['quote_currency'] = $currency;
            if (in_array('shipping_amount', $cols))  $headerUpd['shipping_amount'] = $shippingAmount;
            $model->update($id, $headerUpd);

            // Sync lines: update existing, insert new, delete removed
            $existing = $lineModel->where('quotation_id', $id)->findAll();
            $existingIds = [];
            foreach ($existing as $ex) $existingIds[(int)$ex['id']] = true;

            $seenIds = [];
            foreach ($lines as $ln) {
                $lineId = (int)($ln['id'] ?? 0);
                $payload = $ln;
                unset($payload['id']);
                $payload['quotation_id'] = $id;

                $calc = $lineModel->calculateLineTotal($payload);
                $payload['tax_amount'] = round($calc['tax_amount'], 2);
                $payload['line_total'] = round($calc['line_total'], 2);

                if ($lineId && isset($existingIds[$lineId])) {
                    $lineModel->update($lineId, $payload);
                    $seenIds[$lineId] = true;
                } else {
                    $lineModel->insert($payload);
                }
            }

            // Delete removed lines
            foreach ($existingIds as $exId => $_) {
                if (!isset($seenIds[$exId])) {
                    $lineModel->delete($exId);
                }
            }

            // Recalculate totals
            $allLines = $model->getQuotationLines($id);
            $totals = $model->calculateTotals($allLines, $shippingAmount);
            $upd = [];
            if (in_array('subtotal', $cols))    $upd['subtotal'] = $totals['subtotal'];
            if (in_array('discount', $cols))    $upd['discount'] = $totals['discount'];
            if (in_array('tax', $cols))         $upd['tax'] = $totals['tax'];
            elseif (in_array('tax_total', $cols)) $upd['tax_total'] = $totals['tax'];
            if (in_array('total_weight', $cols)) $upd['total_weight'] = $totals['total_weight'];
            if (in_array('total', $cols))       $upd['total'] = $totals['total'];
            if (!empty($upd)) $model->update($id, $upd);

            $db->transComplete();

            if ($db->transStatus() === false) throw new \RuntimeException('Transaction failed');

            // Log lines removed
            $removedLines = array_diff_key($existingIds, $seenIds);
            foreach ($removedLines as $removedId => $_) {
                $removedLine = null;
                foreach ($existing as $ex) { if ((int)$ex['id'] === $removedId) { $removedLine = $ex; break; } }
                DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_LINE_REMOVED, [
                    'product_name' => $removedLine['product_name'] ?? $removedLine['description'] ?? '',
                    'quantity'     => (float)($removedLine['quantity'] ?? 0),
                    'source'       => 'document_studio',
                ]);
            }

            DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_UPDATED, [
                'lines_count' => count($lines),
                'source'      => 'document_studio',
            ]);

            // Get updated quotation to retrieve public_id if enabled
            $updatedQuote = $model->find($id);
            $viewUrlId = $id;
            if (!empty($updatedQuote['public_id']) && featureEnabled('enable_public_ids')) {
                $viewUrlId = urlencode($updatedQuote['public_id']);
            }

            return $this->response->setJSON([
                'success'  => true,
                'id'       => $id,
                'doc_type' => 'quotation',
                'message'  => 'Quotation updated successfully',
                'view_url' => site_url('quotations/view/' . $viewUrlId),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ═══════════════════ SAVE: Quotation ═══════════════════ */

    public function saveQuotation()
    {
        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            $isDraft = !empty($post['is_draft']);
            $customerId = (int)($post['customer_id'] ?? 0);
            if (!$customerId && !$isDraft) throw new \RuntimeException('Please select a customer');

            $currency = strtoupper(trim((string)($post['currency'] ?? '')));
            if ($currency === '') $currency = $this->getDefaultSalesCurrency();
            $payload = [
                'customer_id'     => $customerId,
                'issue_date'      => $this->normalizeDate($post['issue_date'] ?? ''),
                'notes'           => $post['notes'] ?? null,
                'currency'        => $currency,
                'shipping_amount' => (float)($post['shipping_amount'] ?? 0),
                'status'          => $isDraft ? 'draft' : 'sent',
                'lines'           => [],
            ];

            if (isset($post['lines']) && is_array($post['lines'])) {
                foreach ($post['lines'] as $ln) {
                    if (!is_array($ln)) continue;
                    $payload['lines'][] = [
                        'product_id'    => $ln['product_id'] ?? null,
                        'product_variant_id' => isset($ln['variant_id']) && $ln['variant_id'] !== '' ? (int)$ln['variant_id'] : null,
                        'product_code'  => $ln['product_code'] ?? '',
                        'product_name'  => $ln['product_name'] ?? ($ln['description'] ?? ''),
                        'description'   => $ln['description'] ?? ($ln['product_name'] ?? ''),
                        'unit'          => $ln['unit'] ?? 'pcs',
                        'quantity'      => (float)($ln['quantity'] ?? 0),
                        'unit_price'    => (float)($ln['unit_price'] ?? 0),
                        'discount_type' => $ln['discount_type'] ?? 'fixed',
                        'discount_value'=> (float)($ln['discount_value'] ?? 0),
                        'tax_rate'      => (float)($ln['tax_rate'] ?? 0),
                    ];
                }
            }

            $model = new QuotationModel();
            $id = $model->createQuotation($payload);
            if (!$id) throw new \RuntimeException('Insert failed');

            DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, (int)$id, DocumentLogger::ACTION_CREATED, [
                'lines_count' => count($payload['lines']),
                'is_draft'    => $isDraft,
                'source'      => 'document_studio',
            ]);

            // Fetch quotation to get public_id if enabled
            $quote = $model->find($id);
            $viewUrlId = $id;
            if (!empty($quote['public_id']) && featureEnabled('enable_public_ids')) {
                $viewUrlId = urlencode($quote['public_id']);
            }

            return $this->response->setJSON([
                'success'  => true,
                'id'       => (int)$id,
                'doc_type' => 'quotation',
                'message'  => $isDraft ? 'Quotation saved as draft' : 'Quotation created successfully',
                'view_url' => site_url('quotations/view/' . $viewUrlId),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ═══════════════════ SAVE: Sales Order ═══════════════════ */

    public function saveSalesOrder()
    {
        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            $isDraft = !empty($post['is_draft']);
            $customerId = (int)($post['customer_id'] ?? 0);
            if (!$customerId && !$isDraft) throw new \RuntimeException('Please select a customer');

            $currency = strtoupper(trim((string)($post['currency'] ?? '')));
            if ($currency === '') $currency = $this->getDefaultSalesCurrency();
            $shippingAmount = (float)($post['shipping_amount'] ?? 0);
            $orderDate = $this->normalizeDate($post['issue_date'] ?? ($post['order_date'] ?? ''));

            $linesIn = [];
            if (isset($post['lines']) && is_array($post['lines'])) {
                foreach ($post['lines'] as $ln) {
                    if (!is_array($ln)) continue;
                    $linesIn[] = [
                        'product_id'  => isset($ln['product_id']) && $ln['product_id'] !== '' ? (int)$ln['product_id'] : null,
                        'product_variant_id' => isset($ln['variant_id']) && $ln['variant_id'] !== '' ? (int)$ln['variant_id'] : null,
                        'description' => $ln['description'] ?? ($ln['product_name'] ?? ''),
                        'quantity'    => (float)($ln['quantity'] ?? 0),
                        'unit_price'  => (float)($ln['unit_price'] ?? 0),
                    ];
                }
            }

            $db = Database::connect();
            $db->transStart();

            $soModel   = new SalesOrderModel();
            $lineModel = new SalesOrderLineModel();

            $orderNo = 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $n = 1;
            while ($soModel->where('order_number', $orderNo)->countAllResults() > 0) {
                $orderNo = 'SO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)) . '-' . $n;
                $n++;
            }

            $subtotal = 0.0;
            foreach ($linesIn as $ln) {
                $subtotal += (float)$ln['quantity'] * (float)$ln['unit_price'];
            }
            $total = round($subtotal + $shippingAmount, 2);

            try { $orderCols = $db->getFieldNames('sales_orders'); } catch (\Throwable $_) { $orderCols = []; }

            $header = [
                'order_number' => $orderNo,
                'customer_id'  => $customerId ?: null,
                'order_date'   => $orderDate,
                'subtotal'     => round($subtotal, 2),
                'tax_total'    => 0,
                'total'        => $total,
                'status'       => $isDraft ? 'draft' : 'pending',
                'created_by'   => session()->get('user_id') ?? null,
            ];
            if (!empty($orderCols)) {
                if (in_array('currency', $orderCols)) $header['currency'] = $currency;
                if (in_array('shipping_amount', $orderCols)) $header['shipping_amount'] = round($shippingAmount, 2);
                elseif (in_array('shipping_cost', $orderCols)) $header['shipping_cost'] = round($shippingAmount, 2);
            }

            $orderId = $soModel->insert($header);
            if (!$orderId) throw new \RuntimeException('Failed to create order');

            try { $lineCols = $db->getFieldNames('sales_order_lines'); } catch (\Throwable $_) { $lineCols = []; }

            foreach ($linesIn as $ln) {
                if (empty($ln['description']) && empty($ln['product_id'])) continue;
                $qty = (float)($ln['quantity'] ?? 0);
                $price = (float)($ln['unit_price'] ?? 0);
                $lineInsert = [
                    'sales_order_id' => $orderId,
                    'product_id'     => $ln['product_id'],
                    'description'    => $ln['description'] ?? '',
                    'quantity'       => $qty,
                    'unit_price'     => $price,
                    'line_total'     => round($qty * $price, 2),
                ];
                if (!empty($lineCols) && in_array('product_variant_id', $lineCols, true) && !empty($ln['product_variant_id'])) {
                    $lineInsert['product_variant_id'] = (int)$ln['product_variant_id'];
                }
                $lineModel->insert($lineInsert);
            }

            $db->transComplete();
            if ($db->transStatus() === false) throw new \RuntimeException('Transaction failed');

            DocumentLogger::log(DocumentLogger::TYPE_SALES_ORDER, (int)$orderId, DocumentLogger::ACTION_CREATED, [
                'order_number' => $orderNo,
                'lines_count'  => count($linesIn),
                'is_draft'     => $isDraft,
                'source'       => 'document_studio',
            ]);

            $soIdentifier = (string) $orderId;
            if (featureEnabled('enable_public_ids')) {
                try {
                    $row = $db->table('sales_orders')->select('public_id')->where('id', (int)$orderId)->get()->getRowArray();
                    if (!empty($row['public_id'])) {
                        $soIdentifier = (string) $row['public_id'];
                    }
                } catch (\Throwable $_) {
                    // keep numeric fallback
                }
            }

            return $this->response->setJSON([
                'success'  => true,
                'id'       => (int)$orderId,
                'doc_type' => 'sales_order',
                'message'  => $isDraft ? 'Sales Order saved as draft' : ('Sales Order ' . $orderNo . ' created'),
                'view_url' => site_url('sales-orders/view/' . $soIdentifier),
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ═══════════════════ SAVE: Purchase RFQ ═══════════════════ */

    public function saveRfq()
    {
        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            $isDraft = !empty($post['is_draft']);
            if (!$isDraft && (!array_key_exists('vendor_id', $post) || $post['vendor_id'] === '' || $post['vendor_id'] === null)) {
                throw new \RuntimeException('Please select a vendor');
            }
            $vendorId = (int)($post['vendor_id'] ?? 0);

            $currency = strtoupper(trim((string)($post['currency'] ?? '')));
            if ($currency === '') $currency = $this->getDefaultPurchaseCurrency();

            $db = Database::connect();
            $db->transBegin();

            $rfqModel  = new PurchaseRfqModel();
            $lineModel = new PurchaseRfqLineModel();

            $rfqNumber = $this->generateNextPurchaseDocumentNumber($db);

            $lines = $post['lines'] ?? [];
            $subtotal = 0; $totalDiscount = 0; $totalTax = 0; $grandTotal = 0;
            $normalizedLines = [];
            foreach ($lines as $ln) {
                if (!is_array($ln)) continue;
                $qty = (float)($ln['quantity'] ?? ($ln['qty'] ?? 0));
                if ($qty <= 0) continue;
                $unitCost = (float)($ln['unit_price'] ?? ($ln['unit_cost'] ?? 0));
                $lineBase = $qty * $unitCost;
                $disc = (float)($ln['discount'] ?? 0);
                $taxPct = (float)($ln['tax_percent'] ?? 0);
                $taxable = max(0, $lineBase - $disc);
                $taxAmt = ($taxPct / 100) * $taxable;
                $lineTotal = $taxable + $taxAmt;

                $subtotal += $lineBase;
                $totalDiscount += $disc;
                $totalTax += $taxAmt;
                $grandTotal += $lineTotal;

                $normalizedLines[] = [
                    'product_id'       => isset($ln['product_id']) ? (int)$ln['product_id'] : null,
                    'product_variant_id'=> $ln['variant_id'] ?? null,
                    'description'      => $ln['description'] ?? ($ln['product_name'] ?? ''),
                    'qty'              => $qty,
                    'unit_cost'        => $unitCost,
                    'discount'         => $disc,
                    'tax_percent'      => $taxPct,
                    'tax_amount'       => round($taxAmt, 2),
                    'line_total'       => round($lineTotal, 2),
                ];
            }

            if (empty($normalizedLines)) throw new \RuntimeException('At least one line with qty > 0 required');

            // Ensure currency column exists
            try {
                $rfqCols = $db->getFieldNames('purchase_rfqs');
                if (!in_array('currency', $rfqCols)) {
                    $db->query("ALTER TABLE purchase_rfqs ADD COLUMN currency VARCHAR(10) NULL AFTER vendor_id");
                }
            } catch (\Throwable $_) {}

            $rfqInsert = [
                'rfq_number'     => $rfqNumber,
                'vendor_id'      => $vendorId,
                'status'         => 'draft',
                'currency'       => $currency,
                'rfq_date'       => $this->normalizeDate($post['issue_date'] ?? ''),
                'subtotal'       => round($subtotal, 2),
                'total_discount' => round($totalDiscount, 2),
                'total_tax'      => round($totalTax, 2),
                'grand_total'    => round($grandTotal, 2),
                'notes'          => $post['notes'] ?? null,
                'created_by'     => session()->get('user_id') ?? null,
                'created_at'     => date('Y-m-d H:i:s'),
            ];
            $rfqId = $rfqModel->insert($rfqInsert, true);
            if (!$rfqId) throw new \RuntimeException('RFQ insert failed');

            foreach ($normalizedLines as $nl) {
                $nl['rfq_id'] = $rfqId;
                $nl['created_at'] = date('Y-m-d H:i:s');
                $lineModel->insert($nl);
            }

            $db->transCommit();

            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_RFQ, (int)$rfqId, DocumentLogger::ACTION_CREATED, [
                'rfq_number'  => $rfqNumber,
                'lines_count' => count($normalizedLines),
                'is_draft'    => $isDraft,
                'source'      => 'document_studio',
            ]);

            return $this->response->setJSON([
                'success'  => true,
                'id'       => (int)$rfqId,
                'doc_type' => 'purchase_rfq',
                'number'   => $rfqNumber,
                'message'  => $isDraft ? 'RFQ saved as draft' : ('RFQ ' . $rfqNumber . ' created'),
                'view_url' => site_url('new-purchase-rfqs/' . $rfqId),
            ]);
        } catch (\Throwable $e) {
            $db = Database::connect();
            if ($db->transStatus() === false) $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ═══════════════════ SAVE: Purchase Order ═══════════════════ */

    public function savePurchaseOrder()
    {
        $post = $this->request->getJSON(true) ?: $this->request->getPost();
        try {
            $isDraft = !empty($post['is_draft']);
            if (!$isDraft && (!array_key_exists('vendor_id', $post) || $post['vendor_id'] === '' || $post['vendor_id'] === null)) {
                throw new \RuntimeException('Please select a vendor');
            }
            $vendorId = (int)($post['vendor_id'] ?? 0);

            $currency = strtoupper(trim((string)($post['currency'] ?? '')));
            if ($currency === '') $currency = $this->getDefaultPurchaseCurrency();

            $db = Database::connect();
            $db->transBegin();

            $poModel   = new PurchaseOrderModel();
            $lineModel = new PurchaseOrderLineModel();

            $poNum = $this->generateNextPurchaseDocumentNumber($db);

            try { $poCols = $db->getFieldNames('purchase_orders'); } catch (\Throwable $_) { $poCols = []; }

            $lines = $post['lines'] ?? [];
            $subtotal = 0;
            $normalizedLines = [];
            foreach ($lines as $ln) {
                if (!is_array($ln)) continue;
                $qty = (float)($ln['quantity'] ?? ($ln['qty'] ?? 0));
                if ($qty <= 0) continue;
                $unitPrice = (float)($ln['unit_price'] ?? ($ln['unit_cost'] ?? 0));
                $subtotal += $qty * $unitPrice;
                $normalizedLines[] = [
                    'product_id'  => isset($ln['product_id']) ? (int)$ln['product_id'] : null,
                    'variant_id'  => $ln['variant_id'] ?? null,
                    'description' => $ln['description'] ?? ($ln['product_name'] ?? ''),
                    'qty'         => $qty,
                    'unit_price'  => $unitPrice,
                ];
            }

            if (empty($normalizedLines)) throw new \RuntimeException('At least one line with qty > 0 required');

            $poInsert = [
                'po_number'  => $poNum,
                'vendor_id'  => $vendorId,
                'status'     => 'draft',
                'subtotal'   => round($subtotal, 2),
                'total'      => round($subtotal, 2),
                'created_by' => session()->get('user_id') ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if (in_array('currency', $poCols)) $poInsert['currency'] = $currency;

            $poId = $poModel->insert($poInsert, true);
            if (!$poId) throw new \RuntimeException('PO insert failed');

            $lineCols = [];
            try { $lineCols = $db->getFieldNames('purchase_order_lines'); } catch (\Throwable $_) {}

            foreach ($normalizedLines as $nl) {
                $lineInsert = [
                    'po_id'        => $poId,
                    'product_id'   => $nl['product_id'],
                    'description'  => $nl['description'],
                    'qty'          => $nl['qty'],
                    'unit_price'   => $nl['unit_price'],
                    'qty_received' => 0,
                    'created_at'   => date('Y-m-d H:i:s'),
                ];
                if (!empty($lineCols) && in_array('variant_id', $lineCols) && !empty($nl['variant_id'])) {
                    $lineInsert['variant_id'] = (int)$nl['variant_id'];
                }
                $lineModel->insert($lineInsert);
            }

            $db->transCommit();

            DocumentLogger::log(DocumentLogger::TYPE_PURCHASE_ORDER, (int)$poId, DocumentLogger::ACTION_CREATED, [
                'po_number'   => $poNum,
                'lines_count' => count($normalizedLines),
                'is_draft'    => $isDraft,
                'source'      => 'document_studio',
            ]);

            return $this->response->setJSON([
                'success'  => true,
                'id'       => (int)$poId,
                'doc_type' => 'purchase_order',
                'number'   => $poNum,
                'message'  => $isDraft ? 'Purchase Order saved as draft' : ('Purchase Order ' . $poNum . ' created'),
                'view_url' => site_url('new-purchase-orders/' . $poId),
            ]);
        } catch (\Throwable $e) {
            $db = Database::connect();
            if ($db->transStatus() === false) $db->transRollback();
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /* ═══════════════════ List Documents ═══════════════════ */

    public function listDocuments()
    {
        $type = trim((string)$this->request->getGet('type'));
        $db = Database::connect();
        $out = [];
        $userId = (int) (session()->get('user_id') ?? 0);
        $access = (new RoleDataAccess())->resolveForUser($userId);
        $isolateQuotation = !empty($access['isolate_quotations']);
        $isolateSalesOrder = !empty($access['isolate_sales_orders']);
        $isolatePurchaseOrder = !empty($access['isolate_purchase_orders']);

        $isAdmin = service('policy')->isAdmin();
        $rda = new RoleDataAccess();
        $privateUserIds = $rda->getPrivateUserIds($userId, $isAdmin);

        try {
            switch ($type) {
                case 'quotation':
                    $qSql = 'SELECT q.id, q.quote_number, q.status, q.issue_date, q.total, c.name AS party_name
                         FROM quotations q
                         LEFT JOIN customers c ON c.id = q.customer_id';
                    $qBind = [];
                    $qWhere = [];
                    if ($isolateQuotation && $db->fieldExists('created_by', 'quotations')) {
                        $qWhere[] = 'q.created_by = ?';
                        $qBind[] = $userId;
                    }
                    if (!empty($privateUserIds) && $db->fieldExists('created_by', 'quotations')) {
                        $qWhere[] = 'q.created_by NOT IN (' . implode(',', array_fill(0, count($privateUserIds), '?')) . ')';
                        array_push($qBind, ...$privateUserIds);
                    }
                    if (!empty($qWhere)) {
                        $qSql .= ' WHERE ' . implode(' AND ', $qWhere);
                    }
                    $qSql .= ' ORDER BY q.id DESC LIMIT 50';
                    $rows = $db->query($qSql, $qBind)->getResultArray();
                    foreach ($rows as $r) {
                        $out[] = [
                            'id'         => $r['id'],
                            'number'     => $r['quote_number'] ?? ('QTN-' . $r['id']),
                            'status'     => $r['status'] ?? '',
                            'date'       => $r['issue_date'] ?? '',
                            'total'      => number_format((float)($r['total'] ?? 0), 2),
                            'party_name' => $r['party_name'] ?? '',
                            'view_url'   => site_url('quotations/view/' . $r['id']),
                        ];
                    }
                    break;

                case 'sales_order':
                    $sSql = 'SELECT s.id, s.public_id, s.order_number, s.status, s.order_date, s.total, c.name AS party_name
                         FROM sales_orders s
                         LEFT JOIN customers c ON c.id = s.customer_id';
                    $sBind = [];
                    $sWhere = [];
                    if ($isolateSalesOrder && $db->fieldExists('created_by', 'sales_orders')) {
                        $sWhere[] = 's.created_by = ?';
                        $sBind[] = $userId;
                    }
                    if (!empty($privateUserIds) && $db->fieldExists('created_by', 'sales_orders')) {
                        $sWhere[] = 's.created_by NOT IN (' . implode(',', array_fill(0, count($privateUserIds), '?')) . ')';
                        array_push($sBind, ...$privateUserIds);
                    }
                    if (!empty($sWhere)) {
                        $sSql .= ' WHERE ' . implode(' AND ', $sWhere);
                    }
                    $sSql .= ' ORDER BY s.id DESC LIMIT 50';
                    $rows = $db->query($sSql, $sBind)->getResultArray();
                    foreach ($rows as $r) {
                        $out[] = [
                            'id'         => $r['id'],
                            'number'     => $r['order_number'] ?? ('SO-' . $r['id']),
                            'status'     => $r['status'] ?? '',
                            'date'       => $r['order_date'] ?? '',
                            'total'      => number_format((float)($r['total'] ?? 0), 2),
                            'party_name' => $r['party_name'] ?? '',
                            'view_url'   => site_url('sales-orders/view/' . ((!empty($r['public_id']) && featureEnabled('enable_public_ids')) ? $r['public_id'] : $r['id'])),
                        ];
                    }
                    break;

                case 'purchase_rfq':
                    $rSql = 'SELECT r.id, r.rfq_number, r.status, r.rfq_date, r.grand_total, v.name AS party_name
                         FROM purchase_rfqs r
                         LEFT JOIN vendors v ON v.id = r.vendor_id';
                    $rBind = [];
                    if (!empty($privateUserIds) && $db->fieldExists('created_by', 'purchase_rfqs')) {
                        $rSql .= ' WHERE r.created_by NOT IN (' . implode(',', array_fill(0, count($privateUserIds), '?')) . ')';
                        array_push($rBind, ...$privateUserIds);
                    }
                    $rSql .= ' ORDER BY r.id DESC LIMIT 50';
                    $rows = $db->query($rSql, $rBind)->getResultArray();
                    foreach ($rows as $r) {
                        $out[] = [
                            'id'         => $r['id'],
                            'number'     => $r['rfq_number'] ?? ('RFQ-' . $r['id']),
                            'status'     => $r['status'] ?? '',
                            'date'       => $r['rfq_date'] ?? '',
                            'total'      => number_format((float)($r['grand_total'] ?? 0), 2),
                            'party_name' => $r['party_name'] ?? '',
                            'view_url'   => site_url('new-purchase-rfqs/' . $r['id']),
                        ];
                    }
                    break;

                case 'purchase_order':
                    $pSql = 'SELECT p.id, p.po_number, p.status, p.created_at, p.total, v.name AS party_name
                         FROM purchase_orders p
                         LEFT JOIN vendors v ON v.id = p.vendor_id';
                    $pBind = [];
                    $pWhere = [];
                    if ($isolatePurchaseOrder && $db->fieldExists('created_by', 'purchase_orders')) {
                        $pWhere[] = 'p.created_by = ?';
                        $pBind[] = $userId;
                    }
                    if (!empty($privateUserIds) && $db->fieldExists('created_by', 'purchase_orders')) {
                        $pWhere[] = 'p.created_by NOT IN (' . implode(',', array_fill(0, count($privateUserIds), '?')) . ')';
                        array_push($pBind, ...$privateUserIds);
                    }
                    if (!empty($pWhere)) {
                        $pSql .= ' WHERE ' . implode(' AND ', $pWhere);
                    }
                    $pSql .= ' ORDER BY p.id DESC LIMIT 50';
                    $rows = $db->query($pSql, $pBind)->getResultArray();
                    foreach ($rows as $r) {
                        $out[] = [
                            'id'         => $r['id'],
                            'number'     => $r['po_number'] ?? ('PO-' . $r['id']),
                            'status'     => $r['status'] ?? '',
                            'date'       => $r['created_at'] ?? '',
                            'total'      => number_format((float)($r['total'] ?? 0), 2),
                            'party_name' => $r['party_name'] ?? '',
                            'view_url'   => site_url('new-purchase-orders/' . $r['id']),
                        ];
                    }
                    break;

                default:
                    break;
            }
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['error' => $e->getMessage()]);
        }

        return $this->response->setJSON($out);
    }
}
