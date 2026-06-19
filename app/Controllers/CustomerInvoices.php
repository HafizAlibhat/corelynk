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
use App\Models\QuotationModel;
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

    private function ensureInvoiceSchemaCompat(): void
    {
        $db = \Config\Database::connect();

        try {
            if ($db->tableExists('customer_invoices') && !$db->fieldExists('sales_order_id', 'customer_invoices')) {
                $db->query("ALTER TABLE `customer_invoices` ADD COLUMN `sales_order_id` INT NULL AFTER `customer_id`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoices') && !$db->fieldExists('discount_total', 'customer_invoices')) {
                $db->query("ALTER TABLE `customer_invoices` ADD COLUMN `discount_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoices') && !$db->fieldExists('document_discount_type', 'customer_invoices')) {
                $db->query("ALTER TABLE `customer_invoices` ADD COLUMN `document_discount_type` VARCHAR(16) NOT NULL DEFAULT 'fixed' AFTER `discount_total`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoices') && !$db->fieldExists('document_discount_value', 'customer_invoices')) {
                $db->query("ALTER TABLE `customer_invoices` ADD COLUMN `document_discount_value` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `document_discount_type`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoices') && !$db->fieldExists('discount_exclude_shipping', 'customer_invoices')) {
                $db->query("ALTER TABLE `customer_invoices` ADD COLUMN `discount_exclude_shipping` TINYINT(1) NOT NULL DEFAULT 1 AFTER `document_discount_value`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('product_variant_id', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `product_variant_id` INT NULL AFTER `product_id`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('product_code', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `product_code` VARCHAR(100) NULL AFTER `product_variant_id`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('product_name', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `product_name` VARCHAR(255) NULL AFTER `product_code`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('unit', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `unit` VARCHAR(50) NULL AFTER `description`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('discount_type', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `discount_type` VARCHAR(16) NOT NULL DEFAULT 'percent' AFTER `unit_price`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('discount_value', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `discount_value` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER `discount_type`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('discount_amount', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `discount_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER `discount_value`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('tax_rate', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `tax_rate` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 AFTER `discount_amount`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('tax_amount', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `tax_amount` DECIMAL(12,4) NOT NULL DEFAULT 0.0000 AFTER `tax_rate`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('product_image_url', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `product_image_url` TEXT NULL AFTER `line_total`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('display_type', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `display_type` VARCHAR(20) NOT NULL DEFAULT 'line' AFTER `product_image_url`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('section_title', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `section_title` VARCHAR(255) NULL AFTER `display_type`");
            }
        } catch (\Throwable $_) {}

        try {
            if ($db->tableExists('customer_invoice_lines') && !$db->fieldExists('sort_order', 'customer_invoice_lines')) {
                $db->query("ALTER TABLE `customer_invoice_lines` ADD COLUMN `sort_order` INT NOT NULL DEFAULT 0 AFTER `section_title`");
                $db->query("UPDATE `customer_invoice_lines` SET sort_order = id WHERE sort_order = 0 OR sort_order IS NULL");
            }
        } catch (\Throwable $_) {}
    }

    private function normalizeInvoiceLinesForDisplay(array $invoice, array $lines): array
    {
        $salesOrderId = (int)($invoice['sales_order_id'] ?? 0);
        if ($salesOrderId <= 0 || empty($lines)) {
            return $lines;
        }

        try {
            $sourceLines = $this->salesOrderLineModel
                ->where('sales_order_id', $salesOrderId)
                ->orderBy('sort_order', 'ASC')
                ->orderBy('id', 'ASC')
                ->findAll();
        } catch (\Throwable $_) {
            $sourceLines = [];
        }

        if (empty($sourceLines)) {
            return $lines;
        }

        foreach ($lines as $idx => &$line) {
            $sourceLine = $sourceLines[$idx] ?? null;
            if (!is_array($sourceLine)) {
                continue;
            }

            $sourceDisplayType = strtolower((string)($sourceLine['display_type'] ?? 'line'));
            $lineDisplayType = strtolower((string)($line['display_type'] ?? 'line'));
            $qty = (float)($line['quantity'] ?? 0);
            $unitPrice = (float)($line['unit_price'] ?? 0);
            $discountAmount = (float)($line['discount_amount'] ?? 0);
            $taxAmount = (float)($line['tax_amount'] ?? 0);
            $taxRate = (float)($line['tax_rate'] ?? 0);
            $code = trim((string)($line['product_code'] ?? ''));
            $productId = (int)($line['product_id'] ?? 0);
            $variantId = (int)($line['product_variant_id'] ?? 0);
            $lineTotal = (float)($line['line_total'] ?? 0);
            $label = strtolower(trim((string)($line['section_title'] ?? ($line['product_name'] ?? ($line['description'] ?? '')))));
            $hasCommercialValues = abs($qty) >= 0.00001
                || abs($unitPrice) >= 0.00001
                || abs($discountAmount) >= 0.00001
                || abs($taxAmount) >= 0.00001
                || abs($taxRate) >= 0.00001
                || abs($lineTotal) >= 0.00001;
            $hasProductIdentity = $productId > 0 || $variantId > 0 || $code !== '';
            $isLikelyProductLine = $hasProductIdentity || $hasCommercialValues;
            $looksPlaceholder = !$isLikelyProductLine
                && abs($qty) < 0.00001
                && abs($unitPrice) < 0.00001
                && abs($discountAmount) < 0.00001
                && abs($taxAmount) < 0.00001
                && abs($taxRate) < 0.00001
                && in_array($label, ['new section', 'section'], true);

            if ($lineDisplayType === 'line' && !$isLikelyProductLine && ($sourceDisplayType === 'section' || $looksPlaceholder)) {
                $line['display_type'] = 'section';
                $line['section_title'] = trim((string)($sourceLine['section_title'] ?? $line['section_title'] ?? ''));
            }

            if (($line['display_type'] ?? 'line') === 'section' && ($line['section_title'] ?? '') === '' && !empty($sourceLine['section_title'])) {
                $line['section_title'] = $sourceLine['section_title'];
            }

            if ((!isset($line['sort_order']) || (int)$line['sort_order'] <= 0) && isset($sourceLine['sort_order'])) {
                $line['sort_order'] = (int)$sourceLine['sort_order'];
            }

            if (($line['display_type'] ?? 'line') === 'section') {
                $line['quantity'] = (float)($line['quantity'] ?? 0);
                $line['unit_price'] = (float)($line['unit_price'] ?? 0);
                $line['discount_amount'] = (float)($line['discount_amount'] ?? 0);
                $line['tax_amount'] = (float)($line['tax_amount'] ?? 0);
                $line['line_total'] = (float)($line['line_total'] ?? 0);
                $line['product_code'] = '';
                $line['product_name'] = $line['section_title'] ?? ($sourceLine['section_title'] ?? ($line['description'] ?? 'Section'));
                $line['unit'] = '';
                $line['product_image_url'] = '';
            }
        }
        unset($line);

        return $lines;
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
        $this->ensureInvoiceSchemaCompat();

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
        $this->ensureInvoiceSchemaCompat();

        $order = $this->salesOrderModel->find((int)$salesOrderId);
        if (!$order) {
            return redirect()->back()->with('error', 'Sales order not found');
        }

        $quote = null;
        if (!empty($order['quotation_id'])) {
            try {
                $quote = (new QuotationModel())->find((int)$order['quotation_id']);
            } catch (\Throwable $_) {
                $quote = null;
            }
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
            'sales_order_id' => (int)($order['id'] ?? 0),
            'invoice_type' => 'system',
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'subtotal' => round($subtotal, 2),
            'discount_total' => round((float)($order['discount'] ?? ($quote['discount'] ?? 0)), 2),
            'document_discount_type' => $order['document_discount_type'] ?? ($quote['document_discount_type'] ?? 'fixed'),
            'document_discount_value' => round((float)($order['document_discount_value'] ?? ($quote['document_discount_value'] ?? 0)), 2),
            'discount_exclude_shipping' => (int)($order['discount_exclude_shipping'] ?? ($quote['discount_exclude_shipping'] ?? 1)),
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
        $lines = $this->salesOrderLineModel->where('sales_order_id', $salesOrderId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
        $quoteLines = [];
        if (!empty($quote['id'])) {
            try {
                $quoteLines = (new QuotationModel())->getQuotationLines((int)$quote['id']);
            } catch (\Throwable $_) {
                $quoteLines = [];
            }
        }
        foreach ($lines as $ln) {
            $fallbackLine = [];
            foreach ($quoteLines as $candidate) {
                if ((int)($candidate['product_id'] ?? 0) === (int)($ln['product_id'] ?? 0)
                    && (float)($candidate['quantity'] ?? 0) === (float)($ln['quantity'] ?? 0)
                    && (float)($candidate['unit_price'] ?? 0) === (float)($ln['unit_price'] ?? 0)) {
                    $fallbackLine = $candidate;
                    break;
                }
            }
            if (empty($fallbackLine) && !empty($quoteLines)) {
                $fallbackLine = array_shift($quoteLines);
            }

            $qty = (float)($ln['quantity'] ?? 0);
            $price = (float)($ln['unit_price'] ?? 0);
            $lineTotal = isset($ln['line_total']) ? (float)$ln['line_total'] : round($qty * $price, 2);
            $discountVal = isset($ln['discount_value']) ? (float)$ln['discount_value'] : (isset($fallbackLine['discount_value']) ? (float)$fallbackLine['discount_value'] : null);
            $discountType = $ln['discount_type'] ?? ($fallbackLine['discount_type'] ?? 'percent');
            $discountAmt = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : (isset($fallbackLine['discount_amount']) ? (float)$fallbackLine['discount_amount'] : 0.0);
            if ($discountAmt == 0.0 && $discountVal !== null) {
                $discountAmt = ($discountType === 'percent') ? $qty * $price * ($discountVal / 100.0) : $discountVal;
            }
            $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($fallbackLine['tax_rate']) ? (float)$fallbackLine['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0));
            $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : (($qty * $price - $discountAmt) * ($taxRate / 100.0));
            if ($lineTotal == 0.0) {
                $lineTotal = ($qty * $price) - $discountAmt + $taxAmt;
            }
            $this->invoiceLineModel->insert([
                'invoice_id' => $invoiceId,
                'display_type' => $ln['display_type'] ?? 'line',
                'section_title' => $ln['section_title'] ?? null,
                'sort_order' => (int)($ln['sort_order'] ?? 0),
                'product_code' => $ln['product_code'] ?? ($fallbackLine['product_code'] ?? null),
                'product_id' => $ln['product_id'] ?? null,
                'product_variant_id' => $ln['product_variant_id'] ?? ($fallbackLine['product_variant_id'] ?? null),
                'product_name' => $ln['product_name'] ?? ($fallbackLine['product_name'] ?? null),
                'description' => $ln['description'] ?? ($fallbackLine['description'] ?? ''),
                'unit' => $ln['unit'] ?? ($fallbackLine['unit'] ?? null),
                'quantity' => $qty,
                'unit_price' => $price,
                'discount_value' => $discountVal,
                'discount_amount' => $discountAmt,
                'discount_type' => $discountType,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmt,
                'line_total' => $lineTotal,
                'product_image_url' => $ln['product_image_url'] ?? ($fallbackLine['product_image_url'] ?? null),
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
            'lines' => $this->invoiceLineModel->where('invoice_id', $invoiceId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll(),
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
        $this->ensureInvoiceSchemaCompat();

        $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
        if (!$invoice) return redirect()->back()->with('error', 'Invoice not found');
        $invoiceId = (int)$invoice['id'];

        $lines = $this->invoiceLineModel->where('invoice_id', $invoiceId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        $lines = $this->normalizeInvoiceLinesForDisplay($invoice, $lines);

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
                
                // Get product base info, but do not overwrite existing line values with empty lookup values
                $prod = $pid ? ($pmap[$pid] ?? null) : null;
                if ($prod) {
                    if (!empty($prod['code'])) {
                        $ln['product_code'] = $prod['code'];
                    } elseif (empty($ln['product_code']) && !empty($ln['code'])) {
                        $ln['product_code'] = $ln['code'];
                    }
                    if (!empty($prod['unit'])) {
                        $ln['unit'] = $prod['unit'];
                    } elseif (!isset($ln['unit'])) {
                        $ln['unit'] = null;
                    }
                    if (!empty($prod['image_url'])) {
                        $ln['product_image_url'] = $prod['image_url'];
                    }
                    if (!empty($prod['name'])) {
                        $ln['product_name'] = $prod['name'];
                    }
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

        $subtotal = (float)($invoice['subtotal'] ?? 0);
        $taxTotal = (float)($invoice['tax_total'] ?? 0);
        $shippingCost = (float)($invoice['shipping_cost'] ?? ($invoice['shipping_amount'] ?? ($invoice['shipping'] ?? 0)));
        $discountTotal = (float)($invoice['discount_total'] ?? ($invoice['discount'] ?? 0));

        $lineDiscountTotal = 0.0;
        foreach ($lines as $ln) {
            $qty = (float)($ln['quantity'] ?? 0);
            $price = (float)($ln['unit_price'] ?? 0);
            $discountValue = isset($ln['discount_value']) ? (float)$ln['discount_value'] : 0.0;
            $discountType = strtolower((string)($ln['discount_type'] ?? 'percent'));
            if (!in_array($discountType, ['percent', 'fixed'], true)) {
                $discountType = 'percent';
            }
            $discountAmount = isset($ln['discount_amount']) ? (float)$ln['discount_amount'] : 0.0;
            if ($discountAmount <= 0 && $discountValue > 0) {
                $discountAmount = $discountType === 'percent'
                    ? (($qty * $price) * ($discountValue / 100.0))
                    : $discountValue;
            }
            $lineDiscountTotal += $discountAmount;
        }

        $documentDiscountType = strtolower((string)($invoice['document_discount_type'] ?? 'fixed'));
        if (!in_array($documentDiscountType, ['percent', 'fixed'], true)) {
            $documentDiscountType = 'fixed';
        }
        $documentDiscountValue = (float)($invoice['document_discount_value'] ?? 0);
        $discountExcludeShipping = ((int)($invoice['discount_exclude_shipping'] ?? 1) === 1);
        $documentDiscountAmount = max(0.0, round($discountTotal - $lineDiscountTotal, 2));

        $linkedSalesOrder = null;
        $linkedQuote = null;
        try {
            $db = \Config\Database::connect();
            $invoiceCols = [];
            try { $invoiceCols = $db->getFieldNames('customer_invoices'); } catch (\Throwable $_) { $invoiceCols = []; }

            if (in_array('sales_order_id', $invoiceCols, true) && !empty($invoice['sales_order_id'])) {
                $linkedSalesOrder = $this->salesOrderModel->find((int)$invoice['sales_order_id']);
            }

            if (!$linkedSalesOrder && !empty($invoice['invoice_number'])) {
                $invoiceNo = trim((string)$invoice['invoice_number']);
                if (stripos($invoiceNo, 'INV-') === 0) {
                    $soNumber = substr($invoiceNo, 4);
                    if ($soNumber !== '') {
                        $linkedSalesOrder = $this->salesOrderModel->where('order_number', $soNumber)->first();
                    }
                }
            }
        } catch (\Throwable $_) {
            $linkedSalesOrder = null;
        }

        if (!empty($linkedSalesOrder['quotation_id'])) {
            try {
                $linkedQuote = (new QuotationModel())->find((int)$linkedSalesOrder['quotation_id']);
            } catch (\Throwable $_) {
                $linkedQuote = null;
            }
        }

        $sourceLineDiscountByInvoiceLineId = [];
        $sourceLineDiscountTotal = 0.0;
        $sourceLines = [];
        if (!empty($linkedQuote['id'])) {
            try {
                $sourceLines = (new QuotationModel())->getQuotationLines((int)$linkedQuote['id']);
            } catch (\Throwable $_) {
                $sourceLines = [];
            }
        }
        if (empty($sourceLines) && !empty($linkedSalesOrder['id'])) {
            try {
                $sourceLines = $this->salesOrderLineModel->where('sales_order_id', (int)$linkedSalesOrder['id'])->findAll();
            } catch (\Throwable $_) {
                $sourceLines = [];
            }
        }

        $remainingSourceLines = $sourceLines;
        foreach ($lines as $ln) {
            $matchedIndex = null;
            foreach ($remainingSourceLines as $idx => $src) {
                $sameProduct = (int)($src['product_id'] ?? 0) === (int)($ln['product_id'] ?? 0);
                $sameQty = abs((float)($src['quantity'] ?? 0) - (float)($ln['quantity'] ?? 0)) < 0.0001;
                $samePrice = abs((float)($src['unit_price'] ?? 0) - (float)($ln['unit_price'] ?? 0)) < 0.0001;
                if ($sameProduct && $sameQty && $samePrice) {
                    $matchedIndex = $idx;
                    break;
                }
            }

            $src = [];
            if ($matchedIndex !== null) {
                $src = $remainingSourceLines[$matchedIndex];
                unset($remainingSourceLines[$matchedIndex]);
            }

            $srcDiscountType = strtolower((string)($src['discount_type'] ?? 'percent'));
            if (!in_array($srcDiscountType, ['percent', 'fixed'], true)) {
                $srcDiscountType = 'percent';
            }
            $srcDiscountValue = isset($src['discount_value']) ? (float)$src['discount_value'] : 0.0;
            $srcDiscountAmount = isset($src['discount_amount']) ? (float)$src['discount_amount'] : 0.0;
            if ($srcDiscountAmount <= 0 && $srcDiscountValue > 0) {
                $srcBase = (float)($src['quantity'] ?? 0) * (float)($src['unit_price'] ?? 0);
                $srcDiscountAmount = $srcDiscountType === 'percent'
                    ? ($srcBase * ($srcDiscountValue / 100.0))
                    : $srcDiscountValue;
            }

            $lineId = (int)($ln['id'] ?? 0);
            if ($lineId > 0) {
                $sourceLineDiscountByInvoiceLineId[$lineId] = [
                    'discount_type' => $srcDiscountType,
                    'discount_value' => $srcDiscountValue,
                    'discount_amount' => round($srcDiscountAmount, 2),
                ];
            }
            $sourceLineDiscountTotal += $srcDiscountAmount;
        }

        $orderedWeightKg = 0.0;
        if (!empty($linkedSalesOrder['id'])) {
            try {
                $soLines = $this->salesOrderLineModel->where('sales_order_id', (int)$linkedSalesOrder['id'])->findAll();
                $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($soLines, 'product_id')))));
                $productWeights = [];
                $productWeightUnits = [];
                if (!empty($productIds)) {
                    $products = (new ProductModel())
                        ->select('id, unit_weight, weight, weight_unit')
                        ->whereIn('id', $productIds)
                        ->findAll();
                    foreach ($products as $product) {
                        $pid = (int)($product['id'] ?? 0);
                        $productWeights[$pid] = isset($product['unit_weight']) && (float)$product['unit_weight'] > 0
                            ? (float)$product['unit_weight']
                            : (float)($product['weight'] ?? 0);
                        $productWeightUnits[$pid] = (string)($product['weight_unit'] ?? 'kg');
                    }
                }

                $toKg = static function (float $weightValue, ?string $weightUnit): float {
                    $unit = strtolower(trim((string)$weightUnit));
                    if ($weightValue <= 0) return 0.0;
                    if ($unit === '' || in_array($unit, ['kg', 'kgs', 'kilogram', 'kilograms'], true)) return $weightValue;
                    if (in_array($unit, ['g', 'gm', 'gram', 'grams'], true)) return $weightValue / 1000;
                    if (in_array($unit, ['mg', 'milligram', 'milligrams'], true)) return $weightValue / 1000000;
                    if (in_array($unit, ['lb', 'lbs', 'pound', 'pounds'], true)) return $weightValue * 0.45359237;
                    if (in_array($unit, ['oz', 'ounce', 'ounces'], true)) return $weightValue * 0.0283495231;
                    return $weightValue;
                };

                foreach ($soLines as $soLine) {
                    $pid = (int)($soLine['product_id'] ?? 0);
                    $qty = (float)($soLine['quantity'] ?? 0);
                    $unitWeight = isset($soLine['unit_weight']) && (float)$soLine['unit_weight'] > 0
                        ? (float)$soLine['unit_weight']
                        : (float)($productWeights[$pid] ?? 0);
                    $weightUnit = $soLine['weight_unit'] ?? ($productWeightUnits[$pid] ?? 'kg');
                    $orderedWeightKg += ($qty * $toKg($unitWeight, $weightUnit));
                }
            } catch (\Throwable $_) {
                $orderedWeightKg = 0.0;
            }
        }

        if ($sourceLineDiscountTotal > 0.0001) {
            $lineDiscountTotal = round($sourceLineDiscountTotal, 2);
            $documentDiscountAmount = max(0.0, round($discountTotal - $lineDiscountTotal, 2));
        } elseif ($documentDiscountValue > 0 && $discountTotal > 0) {
            $lineDiscountTotal = 0.0;
            $documentDiscountAmount = round($discountTotal, 2);
        }

        $forceDocumentDiscountDisplay = ($lineDiscountTotal <= 0.0001 && $documentDiscountAmount > 0.0001);

        $data = [
            'invoice' => $invoice,
            'lines' => $lines,
            'customer' => $this->customerModel->find((int)($invoice['customer_id'] ?? 0)) ?? [],
            'customerAddress' => $this->getCustomerAddress((int)($invoice['customer_id'] ?? 0)),
            'company' => $this->getCompanySettings(),
            'mode' => 'view',
            'displayLineDiscount' => round($lineDiscountTotal, 2),
            'displayDocumentDiscountAmount' => round($documentDiscountAmount, 2),
            'displayDocumentDiscountType' => $documentDiscountType,
            'displayDocumentDiscountValue' => round($documentDiscountValue, 2),
            'displayDiscountExcludeShipping' => $discountExcludeShipping ? 1 : 0,
            'orderedWeightKg' => round($orderedWeightKg, 3),
            'linkedSalesOrder' => $linkedSalesOrder,
            'sourceLineDiscountByInvoiceLineId' => $sourceLineDiscountByInvoiceLineId,
            'forceDocumentDiscountDisplay' => $forceDocumentDiscountDisplay,
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
        $lines = $this->invoiceLineModel->where('invoice_id', $invoiceId)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
        $lines = $this->normalizeInvoiceLinesForDisplay($invoice, $lines);

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
            
                // Apply product enrichment without wiping existing line values
                if ($pid && isset($pMap[$pid])) {
                    if (!empty($pMap[$pid]['product_code'])) {
                        $ln['product_code'] = $pMap[$pid]['product_code'];
                    } elseif (empty($ln['product_code']) && !empty($ln['code'])) {
                        $ln['product_code'] = $ln['code'];
                    }
                    if (!empty($pMap[$pid]['product_name'])) {
                        $ln['product_name'] = $pMap[$pid]['product_name'];
                    }
                    if (!empty($pMap[$pid]['unit'])) {
                        $ln['unit'] = $pMap[$pid]['unit'];
                    }
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
            
        } catch (\Throwable $e) {
            // Log the error instead of silently failing
            log_message('error', "PDF enrichment error: " . $e->getMessage() . " | " . $e->getFile() . ":" . $e->getLine());
        }

        // Keep PDF discount-source behavior aligned with the invoice view.
        // Some historical invoices store line discount values that do not represent the actual source semantics.
        $discountTotal = (float)($invoice['discount_total'] ?? ($invoice['discount'] ?? 0));
        $docDiscountValue = (float)($invoice['document_discount_value'] ?? 0);
        $sourceLineDiscountTotal = 0.0;
        $sourceLines = [];
        $linkedSalesOrder = null;
        $linkedQuote = null;

        try {
            $db = \Config\Database::connect();
            $invoiceCols = [];
            try { $invoiceCols = $db->getFieldNames('customer_invoices'); } catch (\Throwable $_) { $invoiceCols = []; }

            if (in_array('sales_order_id', $invoiceCols, true) && !empty($invoice['sales_order_id'])) {
                $linkedSalesOrder = $this->salesOrderModel->find((int)$invoice['sales_order_id']);
            }

            if (!$linkedSalesOrder && !empty($invoice['invoice_number'])) {
                $invoiceNo = trim((string)$invoice['invoice_number']);
                if (stripos($invoiceNo, 'INV-') === 0) {
                    $soNumber = substr($invoiceNo, 4);
                    if ($soNumber !== '') {
                        $linkedSalesOrder = $this->salesOrderModel->where('order_number', $soNumber)->first();
                    }
                }
            }

            if (!empty($linkedSalesOrder['quotation_id'])) {
                $linkedQuote = (new QuotationModel())->find((int)$linkedSalesOrder['quotation_id']);
            }

            if (!empty($linkedQuote['id'])) {
                $sourceLines = (new QuotationModel())->getQuotationLines((int)$linkedQuote['id']);
            }
            if (empty($sourceLines) && !empty($linkedSalesOrder['id'])) {
                $sourceLines = $this->salesOrderLineModel->where('sales_order_id', (int)$linkedSalesOrder['id'])->findAll();
            }
        } catch (\Throwable $e) {
            log_message('error', 'PDF discount source lookup failed: ' . $e->getMessage());
            $sourceLines = [];
        }

        $remainingSourceLines = $sourceLines;
        foreach ($lines as $ln) {
            $matchedIndex = null;
            foreach ($remainingSourceLines as $idx => $src) {
                $sameProduct = (int)($src['product_id'] ?? 0) === (int)($ln['product_id'] ?? 0);
                $sameQty = abs((float)($src['quantity'] ?? 0) - (float)($ln['quantity'] ?? 0)) < 0.0001;
                $samePrice = abs((float)($src['unit_price'] ?? 0) - (float)($ln['unit_price'] ?? 0)) < 0.0001;
                if ($sameProduct && $sameQty && $samePrice) {
                    $matchedIndex = $idx;
                    break;
                }
            }

            $src = [];
            if ($matchedIndex !== null) {
                $src = $remainingSourceLines[$matchedIndex];
                unset($remainingSourceLines[$matchedIndex]);
            }

            $srcDiscountType = strtolower((string)($src['discount_type'] ?? 'percent'));
            if (!in_array($srcDiscountType, ['percent', 'fixed'], true)) {
                $srcDiscountType = 'percent';
            }
            $srcDiscountValue = isset($src['discount_value']) ? (float)$src['discount_value'] : 0.0;
            $srcDiscountAmount = isset($src['discount_amount']) ? (float)$src['discount_amount'] : 0.0;
            if ($srcDiscountAmount <= 0 && $srcDiscountValue > 0) {
                $srcBase = (float)($src['quantity'] ?? 0) * (float)($src['unit_price'] ?? 0);
                $srcDiscountAmount = $srcDiscountType === 'percent'
                    ? ($srcBase * ($srcDiscountValue / 100.0))
                    : $srcDiscountValue;
            }
            $sourceLineDiscountTotal += $srcDiscountAmount;
        }

        $isDocumentLevelOnly = false;
        if ($sourceLineDiscountTotal > 0.0001) {
            $isDocumentLevelOnly = false;
        } elseif ($docDiscountValue > 0 && $discountTotal > 0) {
            $isDocumentLevelOnly = true;
        }

        if ($isDocumentLevelOnly) {
            foreach ($lines as &$ln) {
                $ln['discount_amount'] = 0.0;
                $ln['discount_value'] = 0.0;
                $ln['discount_type'] = 'percent';
                $qty = (float)($ln['quantity'] ?? 0);
                $unitPrice = (float)($ln['unit_price'] ?? 0);
                $lineBase = $qty * $unitPrice;
                $taxAmt = isset($ln['tax_amount']) ? (float)$ln['tax_amount'] : 0.0;
                if ($taxAmt <= 0.0) {
                    $taxRate = isset($ln['tax_rate']) ? (float)$ln['tax_rate'] : (isset($ln['tax']) ? (float)$ln['tax'] : 0.0);
                    if ($taxRate > 0.0) {
                        $taxAmt = $lineBase * ($taxRate / 100.0);
                    }
                }
                $ln['line_total'] = round($lineBase + $taxAmt, 4);
            }
            unset($ln);
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
                $row = $db->table('customer_addresses')
                    ->where('customer_id', $customerId)
                    ->orderBy('is_default', 'DESC')
                    ->orderBy('is_billing', 'DESC')
                    ->orderBy('is_shipping', 'DESC')
                    ->orderBy('id', 'ASC')
                    ->get()
                    ->getFirstRow('array');
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

        public function printView($invoiceId = null)
        {
            $invoice = $this->invoiceModel->findByPublicIdOrId($invoiceId);
            if (!$invoice) {
                return redirect()->back()->with('error', 'Invoice not found');
            }
            $invoiceId = (int)$invoice['id'];

            $db = \Config\Database::connect();
            $customer = [];
            try {
                $customer = $db->table('customers')->where('id', (int)($invoice['customer_id'] ?? 0))->get()->getRowArray() ?: [];
            } catch (\Throwable $_) {}

            $lines = $this->invoiceLineModel->where('invoice_id', $invoiceId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

            try {
                $productIds = array_values(array_filter(array_unique(array_map(function ($line) {
                    return isset($line['product_id']) ? (int) $line['product_id'] : null;
                }, $lines))));
                $variantIds = array_values(array_filter(array_unique(array_map(function ($line) {
                    if (isset($line['product_variant_id']) && $line['product_variant_id']) {
                        return (int) $line['product_variant_id'];
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

                foreach ($lines as &$line) {
                    $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                    $variantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;

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
            } catch (\Throwable $_) {}

            $company = $this->getCompanySettings() ?: [];
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

            $currency = strtoupper(trim((string) ($invoice['currency'] ?? $this->getDefaultSalesCurrency())));
            $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
            $sym = $symbols[$currency] ?? $currency;
            $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

            $subtotal = (float) ($invoice['subtotal'] ?? 0);
            $total = (float) ($invoice['total'] ?? 0);
            $invoiceNumber = esc($invoice['invoice_number'] ?? ('INV-' . $invoiceId));
            $invoiceDate = '';
            $rawDate = trim((string) ($invoice['invoice_date'] ?? ($invoice['created_at'] ?? '')));
            if ($rawDate && strpos($rawDate, '0000') === false) {
                $ts = strtotime($rawDate);
                if ($ts) {
                    $invoiceDate = date('d-m-Y', $ts);
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
    <title>Invoice <?= $invoiceNumber ?></title>
    <style>
      *{box-sizing:border-box;margin:0;padding:0}
      body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
      .grn-doc{max-width:1100px;margin:0 auto}
      .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
      .grn-hero::after{content:'INVOICE';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:6rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
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
        <div class="grn-doc-type">Customer Invoice</div>
        <div class="grn-hero-num"><?= $invoiceNumber ?></div>
        <div class="grn-hero-sub"><?= $companyName ?></div>
        <div class="grn-hero-actions no-print">
          <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
          <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
        </div>
      </div>

      <div class="grn-facts">
        <div class="grn-fact"><div class="grn-fact-lbl">Customer</div><div class="grn-fact-val"><?= $customerName ?></div></div>
        <div class="grn-fact"><div class="grn-fact-lbl">Invoice Date</div><div class="grn-fact-val"><?= esc($invoiceDate ?: '-') ?></div></div>
        <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
        <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
      </div>

      <div class="grn-sec">
        <div class="grn-sec-hd">Invoice Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
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
}
