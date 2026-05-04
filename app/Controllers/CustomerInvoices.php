<?php
namespace App\Controllers;

use App\Models\CustomerInvoiceModel;
use App\Models\CustomerInvoiceLineModel;
use App\Models\InvoiceDocumentModel;
use App\Models\SalesOrderModel;
use App\Models\SalesOrderLineModel;
use App\Models\CustomerModel;
use App\Models\CustomerAddressModel;
use App\Models\ProductModel;
use App\Services\AccountingPostingService;
use App\Models\CompanySettingsModel;
use App\Services\DualInvoiceService;
use App\Libraries\DocumentLogger;
use App\Libraries\InvoicePdfGenerator;

class CustomerInvoices extends BaseController
{
    protected $invoiceModel;
    protected $invoiceLineModel;
    protected $documentModel;
    protected $invoiceService;
    protected $pdfGenerator;
    protected $salesOrderModel;
    protected $salesOrderLineModel;
    protected $customerModel;
    protected $customerAddressModel;
    protected $companySettingsModel;
    
    public function __construct()
    {
        $this->invoiceModel = new CustomerInvoiceModel();
        $this->invoiceLineModel = new CustomerInvoiceLineModel();
        $this->documentModel = new InvoiceDocumentModel();
        $this->invoiceService = new DualInvoiceService();
        $this->pdfGenerator = new InvoicePdfGenerator();
    $this->salesOrderModel = new SalesOrderModel();
    $this->salesOrderLineModel = new SalesOrderLineModel();
    $this->customerModel = new CustomerModel();
    $this->customerAddressModel = new CustomerAddressModel();
    $this->companySettingsModel = new CompanySettingsModel();
        
        helper(['form', 'url', 'number']);
    }
    
    // Create system invoice (actual transaction)
    public function createSystem()
    {
        if ($this->request->getMethod() === 'post') {
            $data = $this->request->getPost();
            
            $result = $this->invoiceService->createSystemInvoice($data);
            
            if (!empty($result['id'])) {
                return redirect()->to('/customer-invoices/view/' . $result['id'])
                                 ->with('success', 'System invoice created successfully');
            } else {
                return redirect()->back()
                                 ->withInput()
                                 ->with('error', 'Failed to create invoice');
            }
        }
        
        // Show create form
        return view('invoices/create_system');
    }
    
    // Create custom invoice based on system invoice
    public function createCustom($systemInvoiceId)
    {
        $systemInvoice = $this->invoiceModel->find($systemInvoiceId);
        
        if (!$systemInvoice || $systemInvoice['invoice_type'] !== 'system') {
            return redirect()->to('/customer-invoices')
                             ->with('error', 'System invoice not found');
        }
        
        if ($this->request->getMethod() === 'post') {
            $customData = $this->request->getPost();
            
            $result = $this->invoiceService->createCustomInvoice($systemInvoiceId, $customData);
            
            if (!empty($result['id'])) {
                return redirect()->to('/customer-invoices/view/' . $result['id'])
                                 ->with('success', 'Custom invoice created successfully');
            } else {
                return redirect()->back()
                                 ->withInput()
                                 ->with('error', 'Failed to create custom invoice');
            }
        }
        
        // Show custom invoice form with system invoice data
        $data = [
            'systemInvoice' => $systemInvoice,
            'lines' => $this->invoiceLineModel->where('invoice_id', $systemInvoiceId)->findAll()
        ];
        
        return view('invoices/create_custom', $data);
    }
    
    // List all invoices
    public function index()
    {
        $db = \Config\Database::connect();
        // Check if sales_orders table and the FK column exist before joining.
        $hasSO = $db->tableExists('sales_orders');
        $hasSalesOrderId = $hasSO && in_array('sales_order_id', $db->getFieldNames('customer_invoices'), true);

        $query = $db->table('customer_invoices ci')
            ->select('ci.*, c.name AS customer_name'
                . ($hasSalesOrderId ? ', so.order_number AS so_number' : ', NULL AS so_number'));
        $query->join('customers c', 'c.id = ci.customer_id', 'left');
        if ($hasSalesOrderId) {
            $query->join('sales_orders so', 'so.id = ci.sales_order_id', 'left');
        }
        $invoices = $query
            ->where('ci.deleted_at IS NULL')
            ->orderBy('ci.created_at', 'DESC')
            ->get()->getResultArray();

        return view('invoices/index', ['invoices' => $invoices]);
    }

    /**
     * Phase-1: Create invoice from Sales Order (draft) and redirect to edit.
     */
    public function createFromSalesOrder($salesOrderId)
    {
        $order = $this->salesOrderModel->find((int)$salesOrderId);
        if (!$order) {
            return redirect()->back()->with('error', 'Sales order not found');
        }

        $invoiceNumber = $this->invoiceModel->generateInvoiceNumber();
        $issueDate = date('Y-m-d');
        $dueDate = date('Y-m-d', strtotime('+30 days'));

        $subtotal = (float)($order['subtotal'] ?? 0);
        $tax = (float)($order['tax_total'] ?? 0);
        $shipping = (float)($order['shipping_amount'] ?? ($order['shipping_cost'] ?? 0));
        $total = (float)($order['total'] ?? ($subtotal + $tax + $shipping));

        $header = [
            'invoice_number' => $invoiceNumber,
            'customer_id' => $order['customer_id'] ?? null,
            'invoice_type' => 'system',
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'subtotal' => round($subtotal, 2),
            'tax_total' => round($tax, 2),
            'total_amount' => round($total, 2),
            'shipping_cost' => round($shipping, 2),
            'status' => 'draft',
            'created_by' => session()->get('user_id') ?? null,
        ];

        // Carry currency from sales order (prefer explicit `currency`, then `currency_code`).
        $currency = $order['currency'] ?? ($order['currency_code'] ?? $this->getDefaultSalesCurrency());
        $header['currency_code'] = $currency;

        $invoiceId = $this->invoiceModel->insert($header);
        if (!$invoiceId) {
            return redirect()->back()->with('error', 'Failed to create invoice');
        }

        // Copy lines
        $lines = $this->salesOrderLineModel->where('sales_order_id', $salesOrderId)->findAll();
        foreach ($lines as $ln) {
            $qty = (float)($ln['quantity'] ?? 0);
            $price = (float)($ln['unit_price'] ?? 0);
            $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : round($qty * $price, 2);
            $discountVal = isset($ln['discount_value']) ? (float)$ln['discount_value'] : null;
            $discountType = $ln['discount_type'] ?? 'percent';
            $discountAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
            if ($discountAmt == 0.0 && $discountVal !== null) {
                $discountAmt = ($discountType === 'percent') ? $qty * $price * ($discountVal / 100.0) : $discountVal;
            }
            $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
            $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($qty * $price - $discountAmt) * ($taxRate / 100.0));
            if ($lineTotal == 0.0) {
                $lineTotal = ($qty * $price) - $discountAmt + $taxAmt;
            }
            $this->invoiceLineModel->insert([
                'invoice_id' => $invoiceId,
                'product_code' => $ln['product_code'] ?? null,
                'product_id' => $ln['product_id'] ?? null,
                'description' => $ln['description'] ?? '',
                'unit' => $ln['unit'] ?? null,
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_value' => $discountVal,
                'discount_amount' => $discountAmt,
                'discount_type' => $discountType,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmt,
                'line_total' => $lineTotal,
            ]);
        }

        DocumentLogger::log(DocumentLogger::TYPE_INVOICE, (int)$invoiceId, DocumentLogger::ACTION_CREATED);
        return redirect()->to('/customer-invoices/edit/' . $invoiceId)->with('success', 'Invoice created as draft');
    }

    /**
     * edit() (simple form) for draft invoices.
     */
    public function edit($invoiceId)
    {
        $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
        if (!$invoice) return redirect()->back()->with('error', 'Invoice not found');
        $invoiceId = (int)$invoice['id'];

        $data = [
            'invoice' => $invoice,
            'lines' => $this->invoiceLineModel->where('invoice_id', $invoiceId)->findAll(),
            'customer' => $this->customerModel->find((int)($invoice['customer_id'] ?? 0)) ?? [],
            'customerAddress' => $this->getCustomerAddress((int)($invoice['customer_id'] ?? 0)),
            'company' => $this->getCompanySettings(),
            'mode' => 'edit',
        ];
        return view('invoices/simple_invoice', $data);
    }

    /**
     * Update basic header fields for draft invoices.
     */
    public function update($invoiceId)
    {
        $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
        if (!$invoice) return redirect()->back()->with('error', 'Invoice not found');
        $invoiceId = (int)$invoice['id'];

        $payload = $this->request->getPost();
        $update = [
            'issue_date' => $payload['issue_date'] ?? $invoice['issue_date'],
            'due_date' => $payload['due_date'] ?? $invoice['due_date'],
            'notes' => $payload['notes'] ?? $invoice['notes'] ?? null,
        ];
        $this->invoiceModel->update($invoiceId, $update);
        DocumentLogger::log(DocumentLogger::TYPE_INVOICE, (int)$invoiceId, DocumentLogger::ACTION_UPDATED);
        return redirect()->to('/customer-invoices/edit/' . $invoiceId)->with('success', 'Invoice updated');
    }

    /**
     * Mark invoice as posted/finalized.
     */
    public function post($invoiceId)
    {
        $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
        if (!$invoice) return redirect()->back()->with('error', 'Invoice not found');
        $invoiceId = (int)$invoice['id'];
        // Implement a two-step flow: draft -> confirmed -> posted
        try {
            $db = \Config\Database::connect();
            $cols = $db->getFieldNames('customer_invoices');
        } catch (\Throwable $_) {
            $cols = [];
        }

        $current = strtolower(trim((string)($invoice['status'] ?? '')));
        if ($current === 'draft' || $current === '') {
            // move draft -> confirmed
            $this->invoiceModel->update($invoiceId, ['status' => 'confirmed']);
            DocumentLogger::log(DocumentLogger::TYPE_INVOICE, (int)$invoiceId, DocumentLogger::ACTION_STATUS_CHANGED, ['from' => 'draft', 'to' => 'confirmed']);
            // Guard: do not post if posted_entry_id is already set.
            $postedEntryId = (int)($invoice['posted_entry_id'] ?? 0);
            if ($postedEntryId > 0) {
                log_message('debug', 'Invoice confirmation: invoice_id=' . (int)$invoiceId . ' accounting skipped (already posted_entry_id=' . $postedEntryId . ')');
            } else {
                try {
                    $svc = new AccountingPostingService();
                    $res = $svc->postCustomerInvoice((int)$invoiceId);
                    if (!empty($res['success'])) {
                        if (!empty($res['skipped'])) {
                            log_message('debug', 'Invoice confirmation: invoice_id=' . (int)$invoiceId . ' accounting skipped: ' . ($res['message'] ?? '')); 
                        } else {
                            log_message('debug', 'Invoice confirmation: invoice_id=' . (int)$invoiceId . ' accounting posted entry_id=' . (int)($res['posted_entry_id'] ?? 0));
                        }
                    } else {
                        log_message('error', 'Invoice confirmation: invoice_id=' . (int)$invoiceId . ' accounting post failed: ' . ($res['message'] ?? 'unknown error'));
                    }
                } catch (\Throwable $e) {
                    log_message('error', 'Invoice confirmation: invoice_id=' . (int)$invoiceId . ' accounting post exception: ' . $e->getMessage());
                }
            }

            return redirect()->to('/customer-invoices/view/' . $invoiceId)->with('success', 'Invoice confirmed');
        }

        if ($current === 'confirmed') {
            // move confirmed -> posted and set posted metadata when available
            $update = ['status' => 'posted'];
            if (in_array('posted_at', $cols)) $update['posted_at'] = date('Y-m-d H:i:s');
            if (in_array('posted_by', $cols)) $update['posted_by'] = session()->get('user_id') ?? null;
            $this->invoiceModel->update($invoiceId, $update);
            DocumentLogger::log(DocumentLogger::TYPE_INVOICE, (int)$invoiceId, DocumentLogger::ACTION_POSTED);
            return redirect()->to('/customer-invoices/view/' . $invoiceId)->with('success', 'Invoice posted');
        }

        // If already posted or unknown status, just redirect back
        return redirect()->to('/customer-invoices/view/' . $invoiceId)->with('info', 'No action taken');
    }

    /**
     * Simple view of invoice header + lines.
     */
    public function view($invoiceId)
    {
        $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
        if (!$invoice) return redirect()->back()->with('error', 'Invoice not found');
        $invoiceId = (int)$invoice['id'];

        $lines = $this->invoiceLineModel->where('invoice_id', $invoiceId)->findAll();

        // Enrich lines with product code/image/unit and variant data when possible
        try {
            $db = \Config\Database::connect();
            $pm = new ProductModel();
            $allProducts = $pm->findAll();
            $pmap = [];
            foreach ($allProducts as $p) {
                $img = base_url('assets/images/no-image.png');
                if (!empty($p['image'])) {
                    $img = base_url('/uploads/products/' . ltrim($p['image'], '/'));
                } elseif (!empty($p['images'])) {
                    $imgs = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                    if (is_array($imgs) && !empty($imgs[0])) {
                        $img = base_url('/uploads/products/' . ltrim($imgs[0], '/'));
                    }
                }
                $pmap[(int)$p['id']] = [
                    'code' => $p['code'] ?? ($p['sku'] ?? ''),
                    'unit' => $p['unit'] ?? null,
                    'image_url' => $img,
                    'name' => $p['name'] ?? null,
                ];
            }
            
            // Load variant data for explicit variant IDs
            $variantIds = array_values(array_filter(array_unique(array_map(function($l){ return isset($l['product_variant_id']) ? (int)$l['product_variant_id'] : null; }, $lines))));
            $variantMap = [];
            
            // Also load ALL variants for products in the lines (for description-based matching)
            $productIds = array_values(array_unique(array_filter(array_map(function($l){ return !empty($l['product_id']) ? (int)$l['product_id'] : null; }, $lines))));
            $allVariants = [];
            if (!empty($productIds)) {
                $allVariantsRows = $db->table('product_variants')->whereIn('product_id', $productIds)->get()->getResultArray();
                foreach ($allVariantsRows as $v) {
                    $vid = (int)($v['id'] ?? 0);
                    $vimg = null;
                    // Only set variant image URL if variant actually has an image
                    if (!empty($v['image'])) {
                        $vimg = base_url('/uploads/variants/' . ltrim($v['image'], '/'));
                    }
                    $vdata = [
                        'id' => $vid,
                        'product_id' => (int)($v['product_id'] ?? 0),
                        'image_url' => $vimg,
                        'name' => $v['name'] ?? null,
                        'code' => $v['art_number'] ?? null,
                        'attributes' => $v['attributes'] ?? null,
                    ];
                    $allVariants[] = $vdata;
                    if (in_array($vid, $variantIds)) {
                        $variantMap[$vid] = $vdata;
                    }
                }
            }
            
            foreach ($lines as &$ln) {
                $vid = isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null;
                $pid = !empty($ln['product_id']) ? (int)$ln['product_id'] : null;
                
                // Get product base info - ALWAYS update from product lookup for consistency
                $prod = $pid ? ($pmap[$pid] ?? null) : null;
                if ($prod) {
                    // Always use product code from lookup (never use stored value)
                    $ln['product_code'] = $prod['code'] ?: null;
                    // Always use unit from lookup
                    $ln['unit'] = $prod['unit'] ?: ($ln['unit'] ?? null);
                    // Always use product image from lookup (variant will override below)
                    $ln['product_image_url'] = $prod['image_url'];
                    // Always use product name from lookup (variant may augment below)
                    $ln['product_name'] = $prod['name'];
                }
                
                // Try to match variant by description if variant_id is not set
                if (!$vid && $pid && !empty($ln['description'])) {
                    foreach ($allVariants as $vdata) {
                        if ($vdata['product_id'] != $pid) continue;
                        
                        // Try to match description against variant attributes
                        $attrs = $vdata['attributes'];
                        if (!empty($attrs)) {
                            $attrsArr = is_string($attrs) ? json_decode($attrs, true) : $attrs;
                            if (is_array($attrsArr)) {
                                // Build expected description from attributes (e.g., "Size: 12" x 2" • colors: Red")
                                $match = true;
                                foreach ($attrsArr as $key => $value) {
                                    $searchPattern = $key . ': ' . $value;
                                    // Check if this attribute is in the description
                                    if (stripos($ln['description'], $value) === false) {
                                        $match = false;
                                        break;
                                    }
                                }
                                
                                if ($match) {
                                    // Found matching variant!
                                    $vid = $vdata['id'];
                                    $ln['product_variant_id'] = $vid;
                                    $variantMap[$vid] = $vdata;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Override with variant data if available
                if ($vid && isset($variantMap[$vid])) {
                    $vdata = $variantMap[$vid];
                    // Use variant code (art_number) instead of product code
                    $ln['product_code'] = $vdata['code'];
                    // Use variant image ONLY if variant has an actual image (not null/empty), otherwise keep product image
                    if (!empty($vdata['image_url'])) {
                        $ln['product_image_url'] = $vdata['image_url'];
                    }
                    // If variant has a name, store separately for two-line display
                    if (!empty($vdata['name'])) {
                        $ln['variant_name'] = $vdata['name'];
                    }
                }
                
                // Fallback matching by description
                if (empty($ln['product_id']) && !empty($ln['description'])) {
                    foreach ($pmap as $p) {
                        if (!empty($p['name']) && strcasecmp($p['name'], $ln['description']) === 0) {
                            $ln['product_code'] = $p['code'];
                            $ln['unit'] = $p['unit'];
                            $ln['product_image_url'] = $p['image_url'];
                            break;
                        }
                    }
                }
            }
            unset($ln);
        } catch (\Throwable $e) {
            // Log the error for debugging
            log_message('error', 'Invoice enrichment error: ' . $e->getMessage());
        }

        $data = [
            'invoice' => $invoice,
            'lines' => $lines,
            'customer' => $this->customerModel->find((int)($invoice['customer_id'] ?? 0)) ?? [],
            'customerAddress' => $this->getCustomerAddress((int)($invoice['customer_id'] ?? 0)),
            'company' => $this->getCompanySettings(),
            'mode' => 'view',
        ];

        // Show payment history linked to this invoice (for quick receivables traceability).
        $invoicePayments = [];
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('customer_payment_allocations') && $db->tableExists('customer_payments')) {
                $payCols = $db->getFieldNames('customer_payments');
                $allocCols = $db->getFieldNames('customer_payment_allocations');
                $statusExpr = in_array('status', $payCols, true)
                    ? 'cp.status'
                    : (in_array('posted_entry_id', $payCols, true)
                        ? "(CASE WHEN cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0 THEN 'posted' ELSE 'draft' END) AS status"
                        : "'draft' AS status");
                $methodExpr = in_array('payment_method', $payCols, true)
                    ? 'cp.payment_method'
                    : (in_array('payment_method_id', $payCols, true) ? "CONCAT('method#', cp.payment_method_id)" : "''");
                $memoExpr = in_array('memo', $payCols, true) ? 'cp.memo' : "'' AS memo";
                $notesExpr = in_array('notes', $payCols, true) ? 'cp.notes' : "'' AS notes";
                $postedExpr = in_array('posted_entry_id', $payCols, true) ? 'cp.posted_entry_id' : '0 AS posted_entry_id';
                // Dynamically resolve allocation amount column — avoid crashing on unknown columns
                $allocAmtCol = 'allocated_amount';
                foreach (['allocated_amount', 'amount_allocated', 'amount'] as $c) {
                    if (in_array($c, $allocCols, true)) { $allocAmtCol = $c; break; }
                }
                $allocExpr = 'COALESCE(cpa.' . $allocAmtCol . ', 0)';

                $invoicePayments = $db->query(
                    'SELECT cp.id, cp.payment_date, cp.amount, ' . $statusExpr . ', ' . $methodExpr . ' AS payment_method, '
                    . $memoExpr . ', ' . $notesExpr . ', ' . $postedExpr . ', '
                    . $allocExpr . ' AS allocated_to_this_invoice '
                    . 'FROM customer_payment_allocations cpa '
                    . 'INNER JOIN customer_payments cp ON cp.id = cpa.payment_id '
                    . 'WHERE cpa.invoice_id = ? '
                    . 'ORDER BY cp.payment_date DESC, cp.id DESC',
                    [(int)$invoiceId]
                )->getResultArray();
            }
        } catch (\Throwable $e) {
            log_message('error', 'CustomerInvoices::view invoicePayments query failed: ' . $e->getMessage());
            $invoicePayments = [];
        }

        $data['invoicePayments'] = $invoicePayments;
        try { $data['logEntries'] = \App\Libraries\DocumentLogger::getForDocument(\App\Libraries\DocumentLogger::TYPE_INVOICE, (int)$invoiceId); } catch (\Throwable $_) { $data['logEntries'] = []; }
        return view('invoices/simple_invoice', $data);
    }

    /** Download system invoice PDF */
    public function pdf($invoiceId)
    {
        $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
        if (!$invoice) return redirect()->back()->with('error', 'Invoice not found');
        $invoiceId = (int)$invoice['id'];
        $lines = $this->invoiceLineModel->where('invoice_id', $invoiceId)->findAll();

        $customerId = (int)($invoice['customer_id'] ?? 0);
        $customer = $this->customerModel->find($customerId) ?? [];
        $customerAddress = $this->getCustomerAddress($customerId);
        $paymentSnapshot = $this->buildInvoicePaymentSnapshot($invoice);

        // Enrich lines with product metadata (especially image paths) so the PDF can render images reliably.
        // Also enrich with variant data for variant products.
        try {
            $db = \Config\Database::connect();
            $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($lines, 'product_id')))));
            $variantIds = array_values(array_unique(array_filter(array_map(function($l){ return isset($l['product_variant_id']) ? (int)$l['product_variant_id'] : null; }, $lines))));
        
            $pMap = [];
            $vMap = [];
        
            if (!empty($productIds)) {
                $cols = [];
                try { $cols = $db->getFieldNames('products'); } catch (\Throwable $_) { $cols = []; }
                $select = ['id'];
                foreach (['code','sku','image','images','name','unit'] as $c) {
                    if (in_array($c, $cols, true)) $select[] = $c;
                }
                $products = $db->table('products')->select(implode(', ', $select))->whereIn('id', $productIds)->get()->getResultArray();
            
                foreach ($products as $p) {
                    $pid = (int)($p['id'] ?? 0);
                    if (!$pid) continue;
                    $img = $p['image'] ?? '';
                    if (empty($img) && !empty($p['images'])) {
                        $arr = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($arr) && !empty($arr[0])) $img = $arr[0];
                    }
                    $imgPath = '';
                    if (!empty($img)) {
                        $normalized = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, (string)$img), DIRECTORY_SEPARATOR);
                        $candidates = [
                            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $normalized,
                            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . $normalized,
                            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products' . DIRECTORY_SEPARATOR . basename($normalized),
                        ];
                        foreach ($candidates as $cand) {
                            if (is_file($cand)) {
                                $real = realpath($cand) ?: $cand;
                                $real = str_replace('\\', '/', $real);
                                $imgPath = preg_match('#^[A-Za-z]:/#', $real) ? ('file:///' . $real) : ('file://' . $real);
                                break;
                            }
                        }
                    }
                    $pMap[$pid] = [
                        'product_code' => $p['code'] ?? ($p['sku'] ?? ''),
                        'product_name' => $p['name'] ?? null,
                        'unit' => $p['unit'] ?? null,
                        'product_image_path' => $imgPath,
                    ];
                }
            }
        
            if (!empty($variantIds)) {
                $variants = $db->table('product_variants')->select('id, name, art_number, image')->whereIn('id', $variantIds)->get()->getResultArray();
                foreach ($variants as $v) {
                    $vid = (int)($v['id'] ?? 0);
                    if (!$vid) continue;
                    $img = $v['image'] ?? '';
                    $imgPath = '';
                    if (!empty($img)) {
                        $normalized = ltrim(str_replace(['\\','/'], DIRECTORY_SEPARATOR, (string)$img), DIRECTORY_SEPARATOR);
                        $candidates = [
                            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $normalized,
                            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'variants' . DIRECTORY_SEPARATOR . $normalized,
                            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'variants' . DIRECTORY_SEPARATOR . basename($normalized),
                        ];
                        foreach ($candidates as $cand) {
                            if (is_file($cand)) {
                                $real = realpath($cand) ?: $cand;
                                $real = str_replace('\\', '/', $real);
                                $imgPath = preg_match('#^[A-Za-z]:/#', $real) ? ('file:///' . $real) : ('file://' . $real);
                                break;
                            }
                        }
                    }
                    $vMap[$vid] = [
                        'variant_code' => $v['art_number'] ?? null,  // Use variant's art_number as code
                        'variant_name' => $v['name'] ?? null,
                        'variant_image_path' => $imgPath,
                    ];
                }
            }
        
            foreach ($lines as &$ln) {
                $pid = !empty($ln['product_id']) ? (int)$ln['product_id'] : 0;
                $vid = isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null;
            
                // Apply product enrichment - ALWAYS update from lookup for consistency
                if ($pid && isset($pMap[$pid])) {
                    $ln['product_code'] = $pMap[$pid]['product_code'] ?: null;
                    $ln['product_name'] = $pMap[$pid]['product_name'];
                    $ln['unit'] = $pMap[$pid]['unit'] ?: ($ln['unit'] ?? null);
                    if (!empty($pMap[$pid]['product_image_path'])) {
                        $ln['product_image_path'] = $pMap[$pid]['product_image_path'];
                    }
                }
            
                // Override with variant data if available
                if ($vid && isset($vMap[$vid])) {
                    // Use variant code (art_number) instead of product code
                    $ln['product_code'] = $vMap[$vid]['variant_code'] ?: $ln['product_code'];
                    // Use variant image if available
                    if (!empty($vMap[$vid]['variant_image_path'])) {
                        $ln['product_image_path'] = $vMap[$vid]['variant_image_path'];
                    }
                    // Store variant name separately for two-line display
                    if (!empty($vMap[$vid]['variant_name'])) {
                        $ln['variant_name'] = $vMap[$vid]['variant_name'];
                    }
                }
            }
            unset($ln);
        } catch (\Throwable $_) {
            // best effort
        }

        // If address table returns nothing but the UI shows address/contact, it's likely stored on the customer row.
        // Normalize a few common customer fields into the structure the PDF template expects.
        if (empty($customerAddress) || !is_array($customerAddress) || (
            empty($customerAddress['line1']) && empty($customerAddress['line2']) && empty($customerAddress['city_name'])
        )) {
            if (is_array($customer) && !empty($customer)) {
                $line1 = trim((string)($customer['address'] ?? ($customer['address1'] ?? ($customer['billing_address'] ?? ''))));
                $line2 = trim((string)($customer['address2'] ?? ($customer['shipping_address'] ?? '')));
                $city = trim((string)($customer['city'] ?? ($customer['billing_city'] ?? '')));
                $postal = trim((string)($customer['postal_code'] ?? ($customer['zip'] ?? '')));
                if ($line1 !== '' || $line2 !== '' || $city !== '' || $postal !== '') {
                    $customerAddress = [
                        'line1' => $line1,
                        'line2' => $line2,
                        'city_name' => $city,
                        'state_name' => '',
                        'postal_code' => $postal,
                    ];
                }
            }
        }

        // Last-resort: query customer_addresses table directly (some installs use different models/columns)
        if (empty($customerAddress) || !is_array($customerAddress) || (
            empty($customerAddress['line1']) && empty($customerAddress['line2']) && empty($customerAddress['city_name'])
        )) {
            try {
                $db = \Config\Database::connect();
                $row = $db->table('customer_addresses')->where('customer_id', $customerId)->get()->getFirstRow('array');
                if (!empty($row) && is_array($row)) {
                    $customerAddress = [
                        'line1' => $row['line1'] ?? ($row['address'] ?? ''),
                        'line2' => $row['line2'] ?? '',
                        'city_name' => $row['city_name'] ?? ($row['city'] ?? ''),
                        'state_name' => $row['state_name'] ?? '',
                        'postal_code' => $row['postal_code'] ?? ($row['postal'] ?? ''),
                    ];
                }
            } catch (\Throwable $_) {
                // ignore
            }
        }

        $company = $this->getCompanySettings();
        $payload = [
            'invoice' => $invoice,
            'lines' => $lines,
            'company' => $company,
            'customer' => $customer,
            'customerAddress' => $customerAddress,
            'paymentSnapshot' => $paymentSnapshot,
            'pdf_show_header_address' => (int)($company['pdf_inv_show_header'] ?? 1),
            'pdf_show_footer' => (int)($company['pdf_inv_show_footer'] ?? 1),
        ];

        $pdf = $this->pdfGenerator->generateSystemInvoice($payload);
        if (is_array($pdf) && !empty($pdf['path'])) {
            return $this->response->download($pdf['path'], null)
                ->setFileName($pdf['name'])
                ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                ->setHeader('Pragma', 'no-cache')
                ->setHeader('Expires', '0');
        }
        return redirect()->back()->with('error', 'Failed to generate PDF');
    }

    private function getCustomerAddress(int $customerId)
    {
        if (!$customerId) return null;
        try {
            $addr = $this->customerAddressModel
                ->where('customer_id', $customerId)
                ->orderBy('is_billing', 'DESC')
                ->orderBy('is_shipping', 'DESC')
                ->orderBy('id', 'ASC')
                ->first();

            // If there is no address row, fall back to customer master fields when available.
            // This prevents PDFs showing only the generic "Customer" label.
            if (empty($addr) || (!is_array($addr))) {
                $cust = $this->customerModel->find($customerId);
                if (is_array($cust)) {
                    $fallbackLine1 = trim((string)($cust['address'] ?? ($cust['address1'] ?? ($cust['billing_address'] ?? ''))));
                    $fallbackLine2 = trim((string)($cust['address2'] ?? ($cust['shipping_address'] ?? '')));
                    $fallbackCity = trim((string)($cust['city'] ?? ($cust['billing_city'] ?? '')));
                    $fallbackPostal = trim((string)($cust['postal_code'] ?? ($cust['zip'] ?? '')));

                    if ($fallbackLine1 !== '' || $fallbackLine2 !== '' || $fallbackCity !== '' || $fallbackPostal !== '') {
                        return [
                            'line1' => $fallbackLine1,
                            'line2' => $fallbackLine2,
                            'city_name' => $fallbackCity,
                            'state_name' => '',
                            'postal_code' => $fallbackPostal,
                        ];
                    }
                }
                return null;
            }

            return $addr;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function buildInvoicePaymentSnapshot(array $invoice): array
    {
        $invoiceId = (int)($invoice['id'] ?? 0);
        if ($invoiceId <= 0) {
            return [
                'invoice_total' => 0.0,
                'paid_total' => 0.0,
                'balance_due' => 0.0,
                'status' => 'unpaid',
                'paid_on' => null,
                'payments' => [],
            ];
        }

        $invoiceTotal = 0.0;
        foreach (['total_amount', 'total', 'amount'] as $f) {
            if (isset($invoice[$f]) && $invoice[$f] !== null && $invoice[$f] !== '') {
                $invoiceTotal = (float)$invoice[$f];
                break;
            }
        }
        if ($invoiceTotal <= 0.0) {
            $subtotal = (float)($invoice['subtotal'] ?? 0);
            $tax = (float)($invoice['tax_total'] ?? 0);
            $shipping = (float)($invoice['shipping_amount'] ?? ($invoice['shipping_cost'] ?? ($invoice['shipping'] ?? 0)));
            $discount = (float)($invoice['discount_total'] ?? ($invoice['discount'] ?? 0));
            $invoiceTotal = max(0.0, $subtotal + $tax + $shipping - $discount);
        }

        $payments = [];
        try {
            $db = \Config\Database::connect();
            if ($db->tableExists('customer_payment_allocations') && $db->tableExists('customer_payments')) {
                $allocCols = [];
                $payCols = [];
                try { $allocCols = $db->getFieldNames('customer_payment_allocations'); } catch (\Throwable $_) { $allocCols = []; }
                try { $payCols = $db->getFieldNames('customer_payments'); } catch (\Throwable $_) { $payCols = []; }

                $allocAmtCol = 'allocated_amount';
                foreach (['allocated_amount', 'amount_allocated', 'amount'] as $c) {
                    if (in_array($c, $allocCols, true)) { $allocAmtCol = $c; break; }
                }

                $refExpr = "'' AS payment_reference";
                if (in_array('reference_number', $payCols, true)) {
                    $refExpr = 'cp.reference_number AS payment_reference';
                } elseif (in_array('reference_no', $payCols, true)) {
                    $refExpr = 'cp.reference_no AS payment_reference';
                } elseif (in_array('memo', $payCols, true)) {
                    $refExpr = 'cp.memo AS payment_reference';
                }

                $postedExpr = in_array('posted_entry_id', $payCols, true)
                    ? 'cp.posted_entry_id'
                    : '0 AS posted_entry_id';

                $statusWhere = '';
                if (in_array('status', $payCols, true)) {
                    $statusWhere = " AND LOWER(COALESCE(cp.status, '')) = 'posted'";
                } elseif (in_array('posted_entry_id', $payCols, true)) {
                    $statusWhere = ' AND (cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0)';
                }

                $payments = $db->query(
                    'SELECT cp.id AS payment_id, cp.payment_date, '
                    . 'COALESCE(cpa.' . $allocAmtCol . ', 0) AS allocated_amount, '
                    . $refExpr . ', '
                    . $postedExpr . ' '
                    . 'FROM customer_payment_allocations cpa '
                    . 'INNER JOIN customer_payments cp ON cp.id = cpa.payment_id '
                    . 'WHERE cpa.invoice_id = ?' . $statusWhere . ' '
                    . 'ORDER BY cp.payment_date ASC, cp.id ASC',
                    [$invoiceId]
                )->getResultArray();
            }
        } catch (\Throwable $e) {
            log_message('error', 'CustomerInvoices::buildInvoicePaymentSnapshot failed: ' . $e->getMessage());
            $payments = [];
        }

        $paidTotal = 0.0;
        $paidOn = null;
        foreach ($payments as &$p) {
            $alloc = max(0.0, (float)($p['allocated_amount'] ?? 0));
            $paidTotal += $alloc;
            $p['allocated_amount'] = $alloc;
            $p['payment_reference'] = trim((string)($p['payment_reference'] ?? ''));
            $p['posted_entry_id'] = (int)($p['posted_entry_id'] ?? 0);

            $dt = trim((string)($p['payment_date'] ?? ''));
            if ($dt !== '' && $dt !== '0000-00-00' && $dt !== '0000-00-00 00:00:00') {
                $paidOn = $dt;
            }
        }
        unset($p);

        $paidTotal = round($paidTotal, 2);
        $balanceDue = max(0.0, round($invoiceTotal - $paidTotal, 2));

        $status = 'unpaid';
        if ($paidTotal > 0.0 && $balanceDue <= 0.005) {
            $status = 'paid';
        } elseif ($paidTotal > 0.0) {
            $status = 'partial';
        }

        // Fallback for legacy/manual flows: invoice status can be paid even if allocation rows are missing.
        $invoiceStatus = strtolower(trim((string)($invoice['status'] ?? '')));
        if ($status === 'unpaid' && in_array($invoiceStatus, ['paid', 'settled', 'closed'], true) && $invoiceTotal > 0.0) {
            $status = 'paid';
            $paidTotal = round($invoiceTotal, 2);
            $balanceDue = 0.0;
            if (empty($paidOn)) {
                $candidatePaidOn = trim((string)($invoice['updated_at'] ?? ($invoice['posted_at'] ?? '')));
                if ($candidatePaidOn !== '' && $candidatePaidOn !== '0000-00-00' && $candidatePaidOn !== '0000-00-00 00:00:00') {
                    $paidOn = $candidatePaidOn;
                }
            }
        }

        return [
            'invoice_total' => round($invoiceTotal, 2),
            'paid_total' => $paidTotal,
            'balance_due' => $balanceDue,
            'status' => $status,
            'paid_on' => $status === 'paid' ? $paidOn : null,
            'payments' => $payments,
        ];
    }

    private function getCompanySettings()
    {
        try {
            return $this->companySettingsModel->orderBy('id', 'DESC')->first();
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getDefaultSalesCurrency(): string
    {
        try {
            $company = $this->getCompanySettings();
            if (!empty($company['default_sales_currency'])) return $company['default_sales_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
            if (!empty($company['secondary_currency'])) return $company['secondary_currency'];
        } catch (\Throwable $_) {
            // ignore
        }
        return 'USD';
    }
}
