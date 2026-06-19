<?php
namespace App\Controllers;

use App\Models\QuotationModel;
use App\Models\QuotationLineModel;
use App\Services\QuotationService;
use CodeIgniter\Exceptions\PageNotFoundException;
use App\Models\CompanySettingsModel;
use App\Models\Accounting\CurrencyModel;
use App\Services\InventoryAvailabilityService;
use App\Libraries\DocumentLogger;
use App\Libraries\InvoicePdfGenerator;
use App\Libraries\RoleDataAccess;

class Quotations extends BaseController
{
    protected $quotationModel;

    /**
     * Accepts DD-MM-YYYY or YYYY-MM-DD and returns YYYY-MM-DD
     */
    private function normalizeDate(?string $val): string
    {
        $val = trim((string)$val);
        if ($val === '') return date('Y-m-d');
        // detect DD-MM-YYYY
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $val, $m)) {
            return $m[3] . '-' . $m[2] . '-' . $m[1];
        }
        // fallback assume already Y-m-d
        return $val;
    }

    /**
     * Resolve the preferred currency for sales documents (quotations / sales orders).
     * Falls back to company base/secondary or USD if not set.
     */
    private function getDefaultSalesCurrency(): string
    {
        try {
            $company = (new CompanySettingsModel())->first();
            if (!empty($company['default_sales_currency'])) return $company['default_sales_currency'];
            if (!empty($company['base_currency'])) return $company['base_currency'];
            if (!empty($company['secondary_currency'])) return $company['secondary_currency'];
        } catch (\Throwable $_) {
            // ignore and fall through
        }
        return 'USD';
    }

    /**
     * Check if a date value is valid (not empty, not zero-date, not zero-datetime)
     */
    private function isValidDate(?string $d): bool
    {
        $d = trim((string)($d ?? ''));
        return $d !== '' && $d !== '0000-00-00' && $d !== '0000-00-00 00:00:00';
    }

    /**
     * Resolve the best available quotation date for PDF output.
     * Priority: issue_date -> quote_date (legacy) -> created_at -> today.
     */
    private function resolveQuotationPdfDate(array $quote): string
    {
        $candidates = [
            $quote['issue_date'] ?? '',
            $quote['quote_date'] ?? '',
            $quote['created_at'] ?? '',
        ];

        foreach ($candidates as $candidate) {
            $candidate = trim((string)$candidate);
            if ($this->isValidDate($candidate) && strtotime($candidate) !== false) {
                return $candidate;
            }
        }

        return date('Y-m-d');
    }


    public function __construct()
    {
        $this->quotationModel = new QuotationModel();
        helper('url');
    }

    public function index()
    {
        // Unified list UI
        return redirect()->to(site_url('documents'));
    }

    public function create()
    {
        try {
            $currencyModel = new CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
        } catch (\Throwable $e) {
            $currencies = [];
        }

        return view('quotations/create', [
            'currencies' => $currencies,
            'defaultCurrency' => $this->getDefaultSalesCurrency(),
        ]);
    }

    /**
     * Edit quotation (reuse create UI)
     */
    public function edit($identifier = null)
    {
        // Handle both numeric ID and public_id identifiers
        $quote = null;
        $isNumeric = is_numeric($identifier);
        
        // Try numeric ID first
        if ($isNumeric) {
            $quote = $this->quotationModel->getWithLines((int)$identifier);
        }
        
        // If not found and identifier is not numeric, try public_id lookup
        if (!$quote && !$isNumeric) {
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $quote = $this->quotationModel->getWithLines((int)$row['id']);
            }
        }
        
        if (!$quote) {
            throw new PageNotFoundException('Quotation not found');
        }

        // Load customer label for the edit UI (customer_search display)
        try {
            $cm = new \App\Models\CustomerModel();
            $cust = $cm->find((int)($quote['customer_id'] ?? 0));
            if ($cust) {
                $quote['customer_code'] = $cust['customer_code'] ?? ($cust['code'] ?? '');
                $quote['customer_name'] = $cust['name'] ?? ($cust['company_name'] ?? '');
            }
        } catch (\Throwable $_) {
            // best-effort
        }

        // Load active currencies for currency selector
        try {
            $currencyModel = new CurrencyModel();
            $currencies = $currencyModel->where('is_active', 1)->orderBy('code', 'ASC')->findAll();
        } catch (\Throwable $_) {
            $currencies = [];
        }

        // Reuse the create view with preloaded data
        return view('quotations/create', [
            'mode' => 'edit',
            'quote' => $quote,
            'lines' => $quote['lines'] ?? [],
            'currencies' => $currencies ?? [],
            'defaultCurrency' => $this->getDefaultSalesCurrency(),
        ]);
    }

    public function store()
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return redirect()->back();
        }

        $isAjax = $this->request->isAJAX() || strpos((string)$this->request->getHeaderLine('Accept'), 'application/json') !== false;
        $post = $this->request->getPost();

        $issueDateRaw = $post['issue_date'] ?? date('Y-m-d');
        $currency = strtoupper(trim((string)($post['currency'] ?? '')));
        if ($currency === '') {
            $currency = $this->getDefaultSalesCurrency();
        }
        $payload = [
            'customer_id' => $post['customer_id'] ?? null,
            'issue_date' => $this->normalizeDate($issueDateRaw),
            'notes' => $post['notes'] ?? null,
            'currency' => $currency,
            'shipping_amount' => isset($post['shipping_amount']) ? (float)$post['shipping_amount'] : 0.0,
            'status' => in_array(strtolower($post['status'] ?? 'draft'), ['draft','sent','accepted','rejected']) ? strtolower($post['status'] ?? 'draft') : 'draft',
            'lines' => []
        ];

        $allowedLineKeys = ['product_id','product_variant_id','product_code','product_name','product_image_url','description','unit','quantity','unit_price','discount_type','discount_value','tax_rate'];
        if (isset($post['lines']) && is_array($post['lines'])) {
            $cleanLines = [];
            foreach ($post['lines'] as $ln) {
                if (!is_array($ln)) continue;
                $cl = [];
                foreach ($allowedLineKeys as $k) {
                    if (array_key_exists($k, $ln)) $cl[$k] = $ln[$k];
                }
                if (isset($cl['quantity'])) $cl['quantity'] = (float)$cl['quantity'];
                if (isset($cl['unit_price'])) $cl['unit_price'] = (float)$cl['unit_price'];
                if (isset($cl['discount_value'])) $cl['discount_value'] = (float)$cl['discount_value'];
                if (isset($cl['tax_rate'])) $cl['tax_rate'] = (float)$cl['tax_rate'];
                $cleanLines[] = $cl;
            }
            $payload['lines'] = $cleanLines;
        }

        try {
            if (empty($payload['customer_id'])) {
                throw new \RuntimeException('Please select a customer');
            }

            // Log incoming shipping_amount for debugging why it may not persist
            // (removed debugging log per deterministic fix request)

            $id = $this->quotationModel->createQuotation($payload);
            if ($id) {
                // Ensure weight is calculated even for drafts
                try {
                    $svc = new QuotationService();
                    $svc->recalculateWeight($id);
                } catch (\Throwable $e) { /* best-effort */ }
                // Activity log
                DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_CREATED);
                foreach ($payload['lines'] as $ln) {
                    $pName = trim((string)($ln['product_name'] ?? ''));
                    DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_LINE_ADDED, [
                        'product' => $pName ?: ('Product #' . ($ln['product_id'] ?? '')),
                        'qty'     => $ln['quantity'] ?? null,
                        'price'   => isset($ln['unit_price']) ? number_format((float)$ln['unit_price'], 2) : null,
                    ]);
                }
            }
            if ($isAjax || $this->request->getVar('debug_probe')) {
                if ($id) {
                    return $this->response->setJSON(['success' => true, 'id' => $id]);
                }
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to create quotation']);
            }
            if ($id) {
                session()->setFlashdata('success', 'Quotation created');
                // Fetch quotation to get public_id if enabled
                $quote = $this->quotationModel->find($id);
                $viewUrlId = $id;
                if (!empty($quote['public_id']) && featureEnabled('enable_public_ids')) {
                    $viewUrlId = urlencode($quote['public_id']);
                }
                return redirect()->to(site_url('quotations/view/' . $viewUrlId));
            }
            session()->setFlashdata('error', 'Failed to create quotation (see server logs)');
            return redirect()->back()->withInput();
        } catch (\Throwable $e) {
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
            session()->setFlashdata('error', 'Failed to create quotation: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function apiCreate()
    {
        $json = $this->request->getJSON(true);
        if (!$json) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid JSON']);
        }
        try {
            if (empty($json['customer_id'])) {
                return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Please select a customer']);
            }
            if (isset($json['shipping_amount'])) {
                $json['shipping_amount'] = (float)$json['shipping_amount'];
            }
            if (isset($json['status'])) {
                $status = strtolower($json['status']);
                $json['status'] = in_array($status, ['draft','quoted','accepted','cancelled']) ? $status : 'quoted';
            } else {
                $json['status'] = 'quoted';
            }
            if (empty($json['currency'])) {
                $json['currency'] = $this->getDefaultSalesCurrency();
            }
            if (isset($json['lines']) && is_array($json['lines'])) {
                $clean = [];
                foreach ($json['lines'] as $ln) {
                    if (!is_array($ln)) continue;
                    $cl = $ln;
                    if (isset($cl['quantity'])) $cl['quantity'] = (float)$cl['quantity'];
                    if (isset($cl['unit_price'])) $cl['unit_price'] = (float)$cl['unit_price'];
                    if (isset($cl['discount_value'])) $cl['discount_value'] = (float)$cl['discount_value'];
                    if (isset($cl['tax_rate'])) $cl['tax_rate'] = (float)$cl['tax_rate'];
                    $clean[] = $cl;
                }
                $json['lines'] = $clean;
            }

            $id = $this->quotationModel->createQuotation($json);
            if ($id) {
                try {
                    $svc = new QuotationService();
                    $svc->recalculateWeight($id);
                } catch (
                    \Throwable $e
                ) { /* best-effort */ }
            }
            if ($id) {
                return $this->response->setJSON(['success' => true, 'id' => $id]);
            }
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => 'Failed to create quotation']);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function view($identifier = null)
    {
        // Handle both numeric ID and public_id identifiers
        $quote = null;
        $isNumeric = is_numeric($identifier);
        
        // Try numeric ID first
        if ($isNumeric) {
            $quote = $this->quotationModel->getWithLines((int)$identifier);
        }
        
        // If not found and identifier is not numeric, try public_id lookup
        if (!$quote && !$isNumeric) {
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $quote = $this->quotationModel->getWithLines((int)$row['id']);
            }
        }
        
        if (!$quote) {
            throw new PageNotFoundException('Quotation not found');
        }
        
        // Canonical redirect: if numeric ID was used but enable_public_ids is enabled and quote has public_id, redirect to public_id URL
        if ($isNumeric && !empty($quote['public_id']) && featureEnabled('enable_public_ids')) {
            return redirect()->to(site_url('quotations/view/' . urlencode($quote['public_id'])))->permanent();
        }

        try {
            $db = \Config\Database::connect();
            $userId = (int) (session()->get('user_id') ?? 0);
            $isAdmin = service('policy')->isAdmin();
            $rda = new RoleDataAccess();
            $access = $rda->resolveForUser($userId);
            $isolate = !empty($access['isolate_quotations']);
            if ($isolate && $db->fieldExists('created_by', 'quotations')) {
                $ownerId = (int) ($quote['created_by'] ?? 0);
                if ($ownerId > 0 && $ownerId !== $userId) {
                    return redirect()->to('/documents')->with('error', 'You are not allowed to view this quotation.');
                }
            }
            // Privacy check: block access if the owner has hidden their documents
            if (!$isAdmin && $db->fieldExists('created_by', 'quotations')) {
                $ownerId = (int) ($quote['created_by'] ?? 0);
                if ($ownerId > 0 && $ownerId !== $userId) {
                    $privateIds = $rda->getPrivateUserIds($userId, false);
                    if (in_array($ownerId, $privateIds, true)) {
                        return redirect()->to('/documents')->with('error', 'This quotation is not available.');
                    }
                }
            }
        } catch (\Throwable $_) {
            // If policy check fails, continue without blocking to avoid false-deny on legacy installs.
        }

        $lines = $this->enrichQuotationPdfLines($quote['lines'] ?? []);
        // Load customer for display (name + contact + address)
        $customer = null;
        try { $customer = (new \App\Models\CustomerModel())->find((int)($quote['customer_id'] ?? 0)); } catch (\Throwable $_) { $customer = null; }
        $address = null;
        try {
            $addrModel = new \App\Models\CustomerAddressModel();
            $addr = $addrModel->where('customer_id', (int)($quote['customer_id'] ?? 0))
                ->orderBy('is_billing', 'DESC')
                ->orderBy('is_shipping', 'DESC')
                ->orderBy('id', 'ASC')
                ->first();
            $address = $addr ?: null;
        } catch (\Throwable $_) { $address = null; }

        $logEntries = [];
        try { $logEntries = \App\Libraries\DocumentLogger::getForDocument(\App\Libraries\DocumentLogger::TYPE_QUOTATION, (int)($quote['id'] ?? 0)); } catch (\Throwable $_) { }

        return view('quotations/view', [
            'quote' => $quote,
            'lines' => $lines,
            'customer' => $customer,
            'customerAddress' => $address,
            'logEntries' => $logEntries,
        ]);
    }

    public function pdf($identifier = null)
    {
        // Handle both numeric ID and public_id identifiers
        $quote = null;
        $isNumeric = is_numeric($identifier);
        
        // Try numeric ID first
        if ($isNumeric) {
            $quote = $this->quotationModel->getWithLines((int)$identifier);
        }
        
        // If not found and identifier is not numeric, try public_id lookup
        if (!$quote && !$isNumeric) {
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $quote = $this->quotationModel->getWithLines((int)$row['id']);
            }
        }
        
        if (!$quote) {
            return redirect()->back()->with('error', 'Quotation not found');
        }

        try {
            $quoteId = (int)($quote['id'] ?? 0);
            $customerId = (int)($quote['customer_id'] ?? 0);
            $customer = [];
            try {
                $customer = (new \App\Models\CustomerModel())->find($customerId) ?? [];
            } catch (\Throwable $_) {
                $customer = [];
            }

            $customerAddress = [];
            try {
                $customerAddress = (new \App\Models\CustomerAddressModel())
                    ->where('customer_id', $customerId)
                    ->orderBy('is_default', 'DESC')
                    ->orderBy('is_billing', 'DESC')
                    ->orderBy('is_shipping', 'DESC')
                    ->orderBy('id', 'ASC')
                    ->first() ?? [];
            } catch (\Throwable $_) {
                $customerAddress = [];
            }

            if (empty($customerAddress) || (!is_array($customerAddress))) {
                $fallbackLine1 = trim((string)($customer['address'] ?? ($customer['address1'] ?? ($customer['billing_address'] ?? ''))));
                $fallbackLine2 = trim((string)($customer['address2'] ?? ($customer['shipping_address'] ?? '')));
                $fallbackCity = trim((string)($customer['city'] ?? ($customer['billing_city'] ?? '')));
                $fallbackPostal = trim((string)($customer['postal_code'] ?? ($customer['zip'] ?? '')));
                if ($fallbackLine1 !== '' || $fallbackLine2 !== '' || $fallbackCity !== '' || $fallbackPostal !== '') {
                    $customerAddress = [
                        'line1' => $fallbackLine1,
                        'line2' => $fallbackLine2,
                        'city_name' => $fallbackCity,
                        'state_name' => '',
                        'postal_code' => $fallbackPostal,
                    ];
                }
            }

            $lines = $this->enrichQuotationPdfLines($quote['lines'] ?? []);

            $company = [];
            try {
                $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?? [];
            } catch (\Throwable $_) {
                $company = [];
            }

            $invoiceLike = [
                'id' => $quote['id'] ?? null,
                'invoice_number' => $quote['quote_number'] ?? ('Q' . $quoteId),
                'issue_date' => $this->resolveQuotationPdfDate($quote),
                'currency' => $quote['quote_currency'] ?? ($quote['currency'] ?? ''),
                'subtotal' => (float)($quote['subtotal'] ?? 0),
                'discount' => (float)($quote['discount'] ?? 0),
                'tax_total' => (float)($quote['tax_total'] ?? ($quote['tax'] ?? 0)),
                'shipping_amount' => (float)($quote['shipping_amount'] ?? ($quote['shipping_cost'] ?? 0)),
                'total_amount' => (float)($quote['total'] ?? 0),
                'notes' => $quote['notes'] ?? null,
            ];

            $pdf = (new InvoicePdfGenerator())->generateSystemInvoice([
                'invoice' => $invoiceLike,
                'lines' => $lines,
                'company' => $company,
                'customer' => $customer,
                'customerAddress' => $customerAddress,
                'document_title' => 'Quotation',
                'document_number_label' => 'Quotation #',
                'document_date_label' => 'Date:',
                'document_prefix' => '',
                'pdf_show_header_address' => (int)($company['pdf_quote_show_header'] ?? 1),
                'pdf_show_footer' => (int)($company['pdf_quote_show_footer'] ?? 1),
            ]);

            if (is_array($pdf) && !empty($pdf['path']) && is_file($pdf['path'])) {
                $quoteNumber = trim((string)($quote['quote_number'] ?? ('Q' . $quoteId)));
                $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', $quoteNumber) ?: ('Q' . $quoteId);
                if ($quoteId > 0) {
                    DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $quoteId, DocumentLogger::ACTION_PDF_DOWNLOADED);
                }
                return $this->response->download($pdf['path'], null)
                    ->setFileName('quotation_' . $safeNumber . '.pdf')
                    ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                    ->setHeader('Pragma', 'no-cache')
                    ->setHeader('Expires', '0');
            }

            return redirect()->back()->with('error', 'Failed to generate quotation PDF');
        } catch (\Throwable $e) {
            log_message('error', 'Quotation PDF failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->with('error', 'Failed to generate quotation PDF: ' . $e->getMessage());
        }
    }

    public function warehousePdf($identifier = null)
    {
        $quote = null;
        $isNumeric = is_numeric($identifier);

        if ($isNumeric) {
            $quote = $this->quotationModel->getWithLines((int)$identifier);
        }

        if (!$quote && !$isNumeric) {
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $quote = $this->quotationModel->getWithLines((int)$row['id']);
            }
        }

        if (!$quote) {
            return redirect()->back()->with('error', 'Quotation not found');
        }

        if (!empty($quote['converted_to_sales_order_id'])) {
            return redirect()->to(site_url('sales-orders/warehouse-document/' . (int)$quote['converted_to_sales_order_id']));
        }

        try {
            $quoteId = (int)($quote['id'] ?? 0);
            $lines = $this->enrichWarehouseQuotationLines($quote['lines'] ?? []);

            $company = [];
            try {
                $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?? [];
            } catch (\Throwable $_) {
                $company = [];
            }

            $invoiceLike = [
                'id' => $quote['id'] ?? null,
                'invoice_number' => $quote['quote_number'] ?? ('Q' . $quoteId),
                'issue_date' => $this->resolveQuotationPdfDate($quote),
                'currency' => $quote['quote_currency'] ?? ($quote['currency'] ?? ''),
                'notes' => $quote['notes'] ?? null,
            ];

            // Resolve customer number only (no name/address) for the warehouse header
            $warehouseCustomerNumber = '';
            try {
                $whCustomer = (new \App\Models\CustomerModel())->select('customer_code')->find((int)($quote['customer_id'] ?? 0));
                $warehouseCustomerNumber = trim((string)($whCustomer['customer_code'] ?? ''));
            } catch (\Throwable $_) {}

            $pdf = (new InvoicePdfGenerator())->generate([
                'invoice' => $invoiceLike,
                'lines' => $lines,
                'company' => $company,
                'document_title' => 'Warehouse Pick List',
                'document_number_label' => 'Quotation #',
                'document_date_label' => 'Date:',
                'document_prefix' => '',
                'party_label' => 'Warehouse Use',
                'warehouse_customer_number' => $warehouseCustomerNumber,
                'pdf_show_header_address' => 0,
                'pdf_show_footer' => (int)($company['pdf_quote_show_footer'] ?? 1),
                'pdf_template' => 'warehouse_picklist',
            ], 'warehouse_picklist');

            if (is_array($pdf) && !empty($pdf['path']) && is_file($pdf['path'])) {
                $quoteNumber = trim((string)($quote['quote_number'] ?? ('Q' . $quoteId)));
                $safeNumber = preg_replace('/[^A-Za-z0-9\-_]/', '_', $quoteNumber) ?: ('Q' . $quoteId);
                if ($quoteId > 0) {
                    DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $quoteId, DocumentLogger::ACTION_PDF_DOWNLOADED);
                }
                return $this->response->download($pdf['path'], null)
                    ->setFileName('quotation_warehouse_' . $safeNumber . '.pdf')
                    ->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate')
                    ->setHeader('Pragma', 'no-cache')
                    ->setHeader('Expires', '0');
            }

            return redirect()->back()->with('error', 'Failed to generate warehouse PDF');
        } catch (\Throwable $e) {
            log_message('error', 'Warehouse quotation PDF failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->with('error', 'Failed to generate warehouse PDF: ' . $e->getMessage());
        }
    }

    public function warehouseDocument($identifier = null)
    {
        $quote = null;
        $isNumeric = is_numeric($identifier);

        if ($isNumeric) {
            $quote = $this->quotationModel->getWithLines((int)$identifier);
        }

        if (!$quote && !$isNumeric) {
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $quote = $this->quotationModel->getWithLines((int)$row['id']);
            }
        }

        if (!$quote) {
            return redirect()->back()->with('error', 'Quotation not found');
        }

        if (!empty($quote['converted_to_sales_order_id'])) {
            return redirect()->to(site_url('sales-orders/warehouse-print/' . (int)$quote['converted_to_sales_order_id']));
        }

        try {
            $quoteId = (int)($quote['id'] ?? 0);
            $lines = $this->enrichWarehouseQuotationLines($quote['lines'] ?? []);

            $company = [];
            try {
                $company = (new CompanySettingsModel())->orderBy('id', 'DESC')->first() ?? [];
            } catch (\Throwable $_) {
                $company = [];
            }

            $invoiceLike = [
                'id' => $quote['id'] ?? null,
                'invoice_number' => $quote['quote_number'] ?? ('Q' . $quoteId),
                'issue_date' => $this->resolveQuotationPdfDate($quote),
                'currency' => $quote['quote_currency'] ?? ($quote['currency'] ?? ''),
                'notes' => $quote['notes'] ?? null,
            ];

            $warehouseCustomerNumber = '';
            try {
                $whCustomer = (new \App\Models\CustomerModel())->select('customer_code')->find((int)($quote['customer_id'] ?? 0));
                $warehouseCustomerNumber = trim((string)($whCustomer['customer_code'] ?? ''));
            } catch (\Throwable $_) {}

            return view('pdf/invoice_warehouse_picklist', [
                'invoice' => $invoiceLike,
                'lines' => $lines,
                'company' => $company,
                'document_title' => 'Warehouse Pick List',
                'document_number_label' => 'Quotation #',
                'document_date_label' => 'Date:',
                'party_label' => 'Warehouse Use',
                'warehouse_customer_number' => $warehouseCustomerNumber,
                'show_print_toolbar' => true,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Warehouse quotation document failed: ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
            return redirect()->back()->with('error', 'Failed to generate warehouse document: ' . $e->getMessage());
        }
    }

    private function enrichWarehouseQuotationLines(array $lines): array
    {
        $lines = $this->enrichQuotationPdfLines($lines);
        if (empty($lines)) {
            return $lines;
        }

        $db = \Config\Database::connect();

        $keys = [];
        $productIds = [];
        foreach ($lines as $line) {
            $productId = (int)($line['product_id'] ?? 0);
            $variantId = (int)($line['product_variant_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $key = $productId . '|' . $variantId;
            $keys[$key] = true;
            $productIds[$productId] = $productId;
        }

        $locationsByKey = [];
        if (!empty($productIds)) {
            try {
                $rows = $db->table('stock_balances sb')
                    ->select('sb.product_id, sb.variant_id, sb.warehouse_id, sb.location_id, SUM(sb.quantity) AS qty, wl.name AS location_name, w.name AS warehouse_name, w.code AS warehouse_code')
                    ->join('warehouse_locations wl', 'wl.id = sb.location_id', 'left')
                    ->join('warehouses w', 'w.id = sb.warehouse_id', 'left')
                    ->whereIn('sb.product_id', array_values($productIds))
                    ->where('sb.quantity >', 0)
                    ->groupBy('sb.product_id, sb.variant_id, sb.warehouse_id, sb.location_id, wl.name, w.name, w.code')
                    ->orderBy('w.name', 'ASC')
                    ->orderBy('wl.name', 'ASC')
                    ->get()
                    ->getResultArray();

                foreach ($rows as $row) {
                    $productId = (int)($row['product_id'] ?? 0);
                    $variantId = (int)($row['variant_id'] ?? 0);
                    if ($productId <= 0) {
                        continue;
                    }
                    $key = $productId . '|' . $variantId;
                    if (!isset($keys[$key])) {
                        $fallbackKey = $productId . '|0';
                        if (!isset($keys[$fallbackKey])) {
                            continue;
                        }
                        $key = $fallbackKey;
                    }

                    $warehouseName = trim((string)($row['warehouse_name'] ?? ''));
                    $warehouseCode = trim((string)($row['warehouse_code'] ?? ''));
                    $locationName = trim((string)($row['location_name'] ?? ''));
                    $qty = (float)($row['qty'] ?? 0);
                    if ($qty <= 0) {
                        continue;
                    }

                    $parts = [];
                    if ($warehouseName !== '') {
                        $parts[] = $warehouseName;
                    } elseif ($warehouseCode !== '') {
                        $parts[] = $warehouseCode;
                    }
                    if ($locationName !== '') {
                        $parts[] = $locationName;
                    }
                    $label = !empty($parts) ? implode(' / ', $parts) : ('Warehouse #' . (int)($row['warehouse_id'] ?? 0));
                    $locationsByKey[$key][] = $label . ' (' . rtrim(rtrim(number_format($qty, 2), '0'), '.') . ')';
                }
            } catch (\Throwable $_) {
                // best effort location enrichment
            }
        }

        foreach ($lines as &$line) {
            $productId = (int)($line['product_id'] ?? 0);
            $variantId = (int)($line['product_variant_id'] ?? 0);
            $key = $productId . '|' . $variantId;
            if ($productId <= 0) {
                $line['warehouse_required_qty'] = (float)($line['quantity'] ?? 0);
                $line['warehouse_locations'] = [];
                $line['warehouse_locations_text'] = 'Not in stock';
                $line['warehouse_status'] = 'Not in Stock';
                continue;
            }

            $line['warehouse_required_qty'] = (float)($line['quantity'] ?? 0);

            $locations = $locationsByKey[$key] ?? [];
            $line['warehouse_locations'] = $locations;
            $line['warehouse_locations_text'] = !empty($locations)
                ? implode(', ', array_values(array_unique($locations)))
                : 'Not in stock';
            // Status based purely on whether physical stock exists in any location,
            // not on net available qty (which can be 0 when other orders have reservations).
            $line['warehouse_status'] = !empty($locations) ? 'In Stock' : 'Not in Stock';
        }
        unset($line);

        return $lines;
    }

    private function enrichQuotationPdfLines(array $lines): array
    {
        if (empty($lines)) {
            return $lines;
        }

        $db = \Config\Database::connect();
        $productIds = array_values(array_unique(array_filter(array_map('intval', array_column($lines, 'product_id')))));
        $variantIds = array_values(array_unique(array_filter(array_map(function ($ln) {
            return isset($ln['product_variant_id']) ? (int)$ln['product_variant_id'] : null;
        }, $lines))));
        $variantCodes = array_values(array_unique(array_filter(array_map(function ($ln) {
            return trim((string)($ln['product_code'] ?? ($ln['code'] ?? ($ln['sku'] ?? ''))));
        }, $lines))));

        $pMap = [];
        if (!empty($productIds)) {
            try {
                $productCols = [];
                try {
                    $productCols = $db->getFieldNames('products');
                } catch (\Throwable $_) {
                    $productCols = [];
                }

                $select = ['id'];
                foreach (['code', 'sku', 'name', 'unit', 'image', 'images'] as $col) {
                    if (in_array($col, $productCols, true)) {
                        $select[] = $col;
                    }
                }

                $products = $db->table('products')
                    ->select(implode(', ', $select))
                    ->whereIn('id', $productIds)
                    ->get()
                    ->getResultArray();

                foreach ($products as $product) {
                    $pid = (int)($product['id'] ?? 0);
                    if (!$pid) {
                        continue;
                    }

                    $img = (string)($product['image'] ?? '');
                    if ($img === '' && !empty($product['images'])) {
                        $arr = is_string($product['images']) ? json_decode($product['images'], true) : $product['images'];
                        if (is_array($arr) && !empty($arr[0])) {
                            $img = (string)$arr[0];
                        }
                    }

                    $pMap[$pid] = [
                        'product_code' => $product['code'] ?? ($product['sku'] ?? ''),
                        'product_name' => $product['name'] ?? '',
                        'unit' => $product['unit'] ?? null,
                        'product_image_path' => $this->resolvePdfImagePath($img, 'products'),
                    ];
                }
            } catch (\Throwable $_) {
                // best effort
            }
        }

        $vMap = [];
        $vMapByCode = [];
        if (!empty($variantIds) || !empty($variantCodes)) {
            try {
                // PRODUCTION PATCH: schema-safe variant select (some prod DBs do not have product_variants.image).
                $variantCols = [];
                try {
                    $variantCols = $db->getFieldNames('product_variants');
                } catch (\Throwable $_) {
                    $variantCols = [];
                }
                $variantSelect = ['id'];
                foreach (['name', 'art_number', 'image', 'attributes'] as $col) {
                    if (in_array($col, $variantCols, true)) {
                        $variantSelect[] = $col;
                    }
                }

                $variants = $db->table('product_variants')
                    ->select(implode(', ', $variantSelect));

                if (!empty($variantIds) && in_array('art_number', $variantCols, true) && !empty($variantCodes)) {
                    $variants = $variants
                        ->groupStart()
                            ->whereIn('id', $variantIds)
                            ->orWhereIn('art_number', $variantCodes)
                        ->groupEnd();
                } elseif (!empty($variantIds)) {
                    $variants = $variants->whereIn('id', $variantIds);
                } elseif (in_array('art_number', $variantCols, true) && !empty($variantCodes)) {
                    $variants = $variants->whereIn('art_number', $variantCodes);
                }

                $variants = $variants->get()->getResultArray();

                foreach ($variants as $variant) {
                    $vid = (int)($variant['id'] ?? 0);
                    if (!$vid) {
                        continue;
                    }
                    $attrs = [];
                    if (!empty($variant['attributes'])) {
                        $decoded = json_decode($variant['attributes'], true);
                        if (is_array($decoded)) {
                            // PRODUCTION PATCH: normalize both object-shaped and list-shaped attribute payloads.
                            $isList = array_keys($decoded) === range(0, count($decoded) - 1);
                            if ($isList) {
                                foreach ($decoded as $item) {
                                    if (!is_array($item)) {
                                        continue;
                                    }
                                    $k = trim((string)($item['name'] ?? ($item['attribute'] ?? ($item['key'] ?? ''))));
                                    $v = trim((string)($item['value'] ?? ''));
                                    if ($k !== '' && $v !== '') {
                                        $attrs[$k] = $v;
                                    }
                                }
                            } else {
                                foreach ($decoded as $k => $v) {
                                    $key = trim((string)$k);
                                    if ($key === '') {
                                        continue;
                                    }
                                    $val = is_scalar($v) ? trim((string)$v) : trim((string)json_encode($v));
                                    if ($val !== '') {
                                        $attrs[$key] = $val;
                                    }
                                }
                            }
                        }
                    }
                    $variantData = [
                        'id' => $vid,
                        'variant_code' => $variant['art_number'] ?? null,
                        'variant_name' => $variant['name'] ?? null,
                        'variant_image_path' => $this->resolvePdfImagePath((string)($variant['image'] ?? ''), 'variants'),
                        'variant_attrs' => $attrs,
                    ];
                    $vMap[$vid] = $variantData;
                    $variantCodeKey = strtoupper(trim((string)($variant['art_number'] ?? '')));
                    if ($variantCodeKey !== '') {
                        $vMapByCode[$variantCodeKey] = $variantData;
                    }
                }
            } catch (\Throwable $_) {
                // best effort
            }
        }

        foreach ($lines as &$line) {
            $pid = !empty($line['product_id']) ? (int)$line['product_id'] : 0;
            $vid = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;

            if (!empty($line['product_image_url']) && empty($line['product_image_path'])) {
                $rawLineImage = (string)$line['product_image_url'];
                $imageFolder = (stripos($rawLineImage, '/variants/') !== false || stripos($rawLineImage, '\\variants\\') !== false)
                    ? 'variants'
                    : 'products';
                $line['product_image_path'] = $this->resolvePdfImagePath($rawLineImage, $imageFolder);
            }

            if ($pid && isset($pMap[$pid])) {
                $line['product_code'] = $pMap[$pid]['product_code'] ?: ($line['product_code'] ?? '');
                $line['product_name'] = $pMap[$pid]['product_name'] ?: ($line['product_name'] ?? ($line['description'] ?? ''));
                if (!empty($pMap[$pid]['unit'])) {
                    $line['unit'] = $pMap[$pid]['unit'];
                }
                if (!empty($pMap[$pid]['product_image_path'])) {
                    $line['product_image_path'] = $pMap[$pid]['product_image_path'];
                }
            }

            $variantData = null;
            if ($vid && isset($vMap[$vid])) {
                $variantData = $vMap[$vid];
            } else {
                // PRODUCTION PATCH: recover variant info when legacy rows lost product_variant_id but kept variant code.
                $lineCode = strtoupper(trim((string)($line['product_code'] ?? ($line['code'] ?? ($line['sku'] ?? '')))));
                if ($lineCode !== '' && isset($vMapByCode[$lineCode])) {
                    $variantData = $vMapByCode[$lineCode];
                    if (empty($line['product_variant_id']) && !empty($variantData['id'])) {
                        $line['product_variant_id'] = (int)$variantData['id'];
                    }
                }
            }

            if (!empty($variantData)) {
                if (!empty($variantData['variant_code'])) {
                    $line['product_code'] = $variantData['variant_code'];
                }
                if (!empty($variantData['variant_name'])) {
                    $line['variant_name'] = $variantData['variant_name'];
                }
                if (!empty($variantData['variant_image_path'])) {
                    $line['product_image_path'] = $variantData['variant_image_path'];
                }
                if (!empty($variantData['variant_attrs'])) {
                    $line['variant_attrs'] = $variantData['variant_attrs'];
                }
            }
        }
        unset($line);

        return $lines;
    }

    private function resolvePdfImagePath(?string $rawPath, string $defaultFolder = 'products'): string
    {
        $value = trim((string)($rawPath ?? ''));
        if ($value === '') {
            return '';
        }

        if (stripos($value, 'file:/') === 0) {
            return $value;
        }

        if (preg_match('#^https?://#i', $value)) {
            $parts = parse_url($value);
            $value = $parts['path'] ?? $value;
        }

        $normalized = ltrim(str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $value), DIRECTORY_SEPARATOR);
        $candidates = [
            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . $normalized,
            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $defaultFolder . DIRECTORY_SEPARATOR . basename($normalized),
            rtrim(FCPATH, '/\\') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . basename($normalized),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                $real = str_replace('\\', '/', realpath($candidate) ?: $candidate);
                return preg_match('#^[A-Za-z]:/#', $real) ? ('file:///' . $real) : ('file://' . $real);
            }
        }

        return '';
    }


    /**
     * Update a quotation and its lines (bulk save) using the same payload shape as create.
     */
    public function update($identifier = null)
    {
        if (strtolower($this->request->getMethod()) !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        // Handle both numeric ID and public_id identifiers
        $id = null;
        $isNumeric = is_numeric($identifier);
        
        if ($isNumeric) {
            $id = (int)$identifier;
        } else {
            // Try to find by public_id
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $id = (int)$row['id'];
            }
        }
        
        if (!$id) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid quotation id']);
        }

        $isAjax = $this->request->isAJAX() || strpos((string)$this->request->getHeaderLine('Accept'), 'application/json') !== false;
        $post = $this->request->getPost();

        $quote = $this->quotationModel->find($id);
        if (!$quote) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Quotation not found']);
        }

        // PRODUCTION PATCH: keep variant linkage on update so PDFs can resolve variant image/attributes.
        $allowedLineKeys = ['id','product_id','product_variant_id','product_code','product_name','product_image_url','description','unit','quantity','unit_price','discount_type','discount_value','tax_rate'];
        $lines = [];
        if (isset($post['lines']) && is_array($post['lines'])) {
            foreach ($post['lines'] as $ln) {
                if (!is_array($ln)) continue;
                $cl = [];
                foreach ($allowedLineKeys as $k) {
                    if (array_key_exists($k, $ln)) $cl[$k] = $ln[$k];
                }
                if (isset($cl['id']) && $cl['id'] !== '') $cl['id'] = (int)$cl['id'];
                if (isset($cl['product_id']) && $cl['product_id'] !== '') $cl['product_id'] = (int)$cl['product_id'];
                if (isset($cl['product_variant_id']) && $cl['product_variant_id'] !== '') $cl['product_variant_id'] = (int)$cl['product_variant_id'];
                if (isset($cl['quantity'])) $cl['quantity'] = (float)$cl['quantity'];
                if (isset($cl['unit_price'])) $cl['unit_price'] = (float)$cl['unit_price'];
                if (isset($cl['discount_value'])) $cl['discount_value'] = (float)$cl['discount_value'];
                if (isset($cl['tax_rate'])) $cl['tax_rate'] = (float)$cl['tax_rate'];
                $lines[] = $cl;
            }
        }

    $issueDateRaw = $post['issue_date'] ?? ($quote['issue_date'] ?? date('Y-m-d'));
    $shippingAmount = isset($post['shipping_amount']) ? (float)$post['shipping_amount'] : 0.0;
        $status = strtolower($post['status'] ?? ($quote['status'] ?? 'draft'));
        $status = in_array($status, ['draft','sent','accepted','rejected']) ? $status : 'draft';
        $currency = strtoupper(trim((string)($post['currency'] ?? ($quote['currency'] ?? ''))));
        if ($currency === '') $currency = $this->getDefaultSalesCurrency();

        $db = \Config\Database::connect();
    $lineModel = new QuotationLineModel();
    $svc = new QuotationService();

    $currentCustomerId = (int) ($quote['customer_id'] ?? 0);
    $requestedCustomerId = (int) ($post['customer_id'] ?? 0);
    $hasExistingLines = (int) $lineModel->where('quotation_id', $id)->countAllResults() > 0;

    if ($requestedCustomerId > 0 && $requestedCustomerId !== $currentCustomerId && $hasExistingLines) {
        return $this->response->setStatusCode(422)->setJSON([
            'success' => false,
            'error' => 'Customer cannot be changed after lines are added. Create a new quotation for a different customer.',
        ]);
    }

    try { $cols = $db->getFieldNames($this->quotationModel->table); } catch (\Throwable $_) { $cols = $this->quotationModel->allowedFields; }

        try {
            $db->transStart();

            // header update
            $headerUpd = [
                'customer_id' => $currentCustomerId,
                'issue_date' => $this->normalizeDate($issueDateRaw),
                'notes' => $post['notes'] ?? ($quote['notes'] ?? null),
                'status' => $status,
            ];
            if (!$hasExistingLines && $requestedCustomerId > 0) {
                $headerUpd['customer_id'] = $requestedCustomerId;
            }
            if (in_array('currency', $cols)) {
                $headerUpd['currency'] = $currency;
            }
            // shipping_amount is managed only via createQuotation and updateShipping; do not overwrite here.
            // (removed debugging log per deterministic fix request)
            $this->quotationModel->update($id, $headerUpd);

            // Build existing line map for change detection
            $existing = $lineModel->where('quotation_id', $id)->findAll();
            $existingLineMap = [];
            $existingIds = [];
            foreach ($existing as $ex) {
                $existingLineMap[(int)$ex['id']] = $ex;
                $existingIds[(int)$ex['id']] = true;
            }
            $docLogEntries = [];

            $seenIds = [];
            foreach ($lines as $ln) {
                $lineId = isset($ln['id']) ? (int)$ln['id'] : 0;
                $payload = $ln;
                unset($payload['id']);
                $payload['quotation_id'] = $id;

                // compute line totals
                $calc = $lineModel->calculateLineTotal($payload);
                $payload['tax_amount'] = round($calc['tax_amount'], 2);
                $payload['line_total'] = round($calc['line_total'], 2);

                if ($lineId && isset($existingIds[$lineId])) {
                    // Detect line changes for logging
                    $old = $existingLineMap[$lineId] ?? [];
                    $oldQty   = (float)($old['quantity']   ?? 0);
                    $oldPrice = (float)($old['unit_price'] ?? 0);
                    $newQty   = (float)($payload['quantity']   ?? 0);
                    $newPrice = (float)($payload['unit_price'] ?? 0);
                    if (abs($oldQty - $newQty) > 0.0001 || abs($oldPrice - $newPrice) > 0.001) {
                        $ctx = ['product' => trim((string)($payload['product_name'] ?? $old['product_name'] ?? 'item'))];
                        if (abs($oldQty - $newQty) > 0.0001)   { $ctx['qty_from'] = $oldQty;   $ctx['qty_to'] = $newQty; }
                        if (abs($oldPrice - $newPrice) > 0.001) { $ctx['price_from'] = $oldPrice; $ctx['price_to'] = $newPrice; }
                        $docLogEntries[] = [DocumentLogger::ACTION_LINE_UPDATED, $ctx];
                    }
                    $lineModel->update($lineId, $payload);
                    $seenIds[$lineId] = true;
                } else {
                    $pName = trim((string)($payload['product_name'] ?? ''));
                    $docLogEntries[] = [DocumentLogger::ACTION_LINE_ADDED, [
                        'product' => $pName ?: 'New item',
                        'qty'     => $payload['quantity'] ?? null,
                        'price'   => isset($payload['unit_price']) ? number_format((float)$payload['unit_price'], 2) : null,
                    ]];
                    $lineModel->insert($payload);
                }
            }

            // delete removed lines
            foreach ($existingIds as $exId => $_) {
                if (!isset($seenIds[$exId])) {
                    $old = $existingLineMap[$exId] ?? [];
                    $pName = trim((string)($old['product_name'] ?? ''));
                    $docLogEntries[] = [DocumentLogger::ACTION_LINE_REMOVED, [
                        'product' => $pName ?: ('Item #' . $exId),
                    ]];
                    $lineModel->delete($exId);
                }
            }

            // server-side totals + weight update (source of truth)
            $allLines = $this->quotationModel->getQuotationLines($id);
            $totals = $this->quotationModel->calculateTotals($allLines, $shippingAmount);
            $upd = [];
            if (in_array('subtotal', $cols)) $upd['subtotal'] = $totals['subtotal'];
            if (in_array('discount', $cols)) $upd['discount'] = $totals['discount'];
            elseif (in_array('document_discount_value', $cols)) $upd['document_discount_value'] = $totals['discount'];
            if (in_array('tax', $cols)) $upd['tax'] = $totals['tax'];
            elseif (in_array('tax_total', $cols)) $upd['tax_total'] = $totals['tax'];
            // (shipping_amount intentionally not modified here)
            if (in_array('total_weight', $cols)) $upd['total_weight'] = $totals['total_weight'];
            if (in_array('total', $cols)) $upd['total'] = $totals['total'];
            if (!empty($upd)) {
                $this->quotationModel->update($id, $upd);
            }

            // Ensure weight is recalculated via service too (best-effort)
            try { $svc->recalculateWeight($id); } catch (\Throwable $e) { /* ignore */ }

            $db->transComplete();

            if ($db->transStatus() === false) {
                throw new \RuntimeException('Transaction failed');
            }

            // Write activity logs (outside transaction â€” these are immutable)
            if ($quote['status'] !== $status) {
                DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_STATUS_CHANGED, [
                    'from' => $quote['status'], 'to' => $status,
                ]);
            }
            foreach ($docLogEntries as [$action, $ctx]) {
                DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, $action, $ctx);
            }
            if ($quote['status'] === $status && empty($docLogEntries)) {
                DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_UPDATED);
            }

            if ($isAjax) {
                return $this->response->setJSON(['success' => true, 'id' => $id, 'totals' => $totals]);
            }
            session()->setFlashdata('success', 'Quotation updated');
            return redirect()->to(site_url('quotations/view/' . $id));
        } catch (\Throwable $e) {
            try { $db->transRollback(); } catch (\Throwable $_) {}
            log_message('error', 'Quotation update failed: ' . $e->getMessage() . ' | File: ' . $e->getFile() . ':' . $e->getLine() . ' | Trace: ' . $e->getTraceAsString());
            if ($isAjax) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
            session()->setFlashdata('error', 'Failed to update quotation: ' . $e->getMessage());
            return redirect()->back()->withInput();
        }
    }

    public function searchProducts()
    {
        $q = $this->request->getGet('q');
        $customerId = (int)$this->request->getGet('customer_id');
        $results = [];
        if (empty($q)) return $this->response->setJSON($results);

        $db = \Config\Database::connect();
        $prodModel = new \App\Models\ProductModel();
        $candidates = method_exists($prodModel, 'searchProducts') ? $prodModel->searchProducts($q) : $prodModel->like('name', $q)->orLike('code', $q)->limit(20)->find();

        $stockMap = [];
        if (!empty($candidates)) {
            $ids = array_values(array_unique(array_filter(array_column($candidates, 'id'))));
            if (!empty($ids)) {
                $stockRows = $db->table('stock_balances')
                    ->select('product_id, SUM(quantity) as qty')
                    ->whereIn('product_id', $ids)
                    ->groupBy('product_id')
                    ->get()
                    ->getResultArray();
                foreach ($stockRows as $sr) {
                    $stockMap[(int)$sr['product_id']] = (float)$sr['qty'];
                }
            }
        }

        $pli = new \App\Models\PriceListItemModel();

        foreach ($candidates as $p) {
            $variantUrl = '';
            if (!empty($p['variant_image'])) {
                $variantRaw = trim((string)$p['variant_image']);
                if (preg_match('#^https?://#i', $variantRaw)) {
                    $variantUrl = $variantRaw;
                } elseif (strpos($variantRaw, '/') === 0) {
                    $variantUrl = base_url($variantRaw);
                } elseif (stripos($variantRaw, 'uploads/') === 0) {
                    $variantUrl = base_url('/' . ltrim($variantRaw, '/'));
                } else {
                    $variantUrl = base_url('/uploads/variants/' . ltrim($variantRaw, '/'));
                }
            }

            $imageCandidates = [];
            if (!empty($p['image_url'])) $imageCandidates[] = $p['image_url'];
            if (!empty($p['image'])) $imageCandidates[] = $p['image'];
            if (!empty($p['images'])) {
                if (is_string($p['images'])) {
                    $decodedImages = json_decode($p['images'], true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedImages)) {
                        foreach ($decodedImages as $imgEntry) {
                            $imageCandidates[] = $imgEntry;
                        }
                    } else {
                        $imageCandidates[] = $p['images'];
                    }
                } elseif (is_array($p['images'])) {
                    foreach ($p['images'] as $imgEntry) {
                        $imageCandidates[] = $imgEntry;
                    }
                }
            }
            $imgUrl = '';
            if ($variantUrl !== '') {
                $imgUrl = $variantUrl;
            } else {
                foreach ($imageCandidates as $candidate) {
                    $value = '';
                    if (is_array($candidate)) {
                        if (!empty($candidate['url'])) $value = (string)$candidate['url'];
                        elseif (!empty($candidate['path'])) $value = (string)$candidate['path'];
                        else $value = (string)reset($candidate);
                    } else {
                        $value = (string)$candidate;
                    }
                    $resolved = $this->resolveProductImageUrl($value);
                    if ($resolved !== '') {
                        $imgUrl = $resolved;
                        break;
                    }
                }
            }
            if ($imgUrl === '') {
                $imgUrl = base_url('assets/images/no-image.png');
            }

            $unitWeight = $p['unit_weight'] ?? $p['weight'] ?? $p['weight_net'] ?? $p['weight_gross'] ?? 0.0;
            $pid = (int)$p['id'];
            if (!empty($p['variant_id'])) {
                $currentStock = $p['current_stock'] ?? $p['stock'] ?? $p['available_stock'] ?? $p['quantity'] ?? $p['qty'] ?? 0.0;
            } else {
                $currentStock = $stockMap[$pid] ?? ($p['current_stock'] ?? $p['stock'] ?? $p['available_stock'] ?? $p['quantity'] ?? $p['qty'] ?? 0.0);
            }

            $item = [
                'product_id'    => $pid,
                'id'            => $pid,
                'code'          => $p['code'] ?? ($p['sku'] ?? ''),
                'name'          => $p['name'] ?? '',
                'description'   => $p['description'] ?? '',
                'unit'          => $p['unit'] ?? 'pcs',
                'unit_weight'   => (float)$unitWeight,
                'weight'        => (float)$unitWeight, // explicit alias for clients expecting weight
                'sale_price'    => isset($p['sale_price']) ? (float)$p['sale_price'] : 0.0,
                'special_price' => isset($p['special_price']) ? (float)$p['special_price'] : null,
                'tax_rate'      => isset($p['tax_rate']) ? (float)$p['tax_rate'] : (isset($p['tax']) ? (float)$p['tax'] : 0.0),
                'image_url'     => $imgUrl,
                'image'         => $imgUrl,
                'variant_image' => $p['variant_image'] ?? null,
                'current_stock' => (float)$currentStock,
                'detailed_type' => $p['detailed_type'] ?? 'storable',
                'variant_id'    => isset($p['variant_id']) ? (int)$p['variant_id'] : null,
                'variant_art_number' => $p['variant_art_number'] ?? null,
                'variant_name'  => $p['variant_name'] ?? null,
                'variant_price' => isset($p['variant_price']) ? (float)$p['variant_price'] : null,
                'attributes'    => $p['attributes'] ?? null,
            ];

            if ($customerId) {
                $plItem = $pli->getCustomerProductPrice($customerId, $p['id'], 1);
                if ($plItem) {
                    $item['special_price'] = (float)$plItem['special_price'];
                    $item['price_list_id'] = $plItem['price_list_id'] ?? null;
                }
            }

            $results[] = $item;
        }

        // Optional on-demand debug logging: call /quotations/search-products?q=...&debug_probe=1
        // to write the returned results (server-side) to the CodeIgniter log for inspection.
        try {
            if ($this->request->getGet('debug_probe')) {
                log_message('debug', 'quotations::searchProducts results -> ' . json_encode($results));
            }
        } catch (\Throwable $_) { /* ignore logging failures */ }

        return $this->response->setJSON($results);
    }

    private function resolveProductImageUrl(?string $rawPath): string
    {
        $value = trim((string)($rawPath ?? ''));
        if ($value === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $value)) {
            return $value;
        }
        if (strpos($value, '/') === 0) {
            return base_url($value);
        }
        if (stripos($value, 'uploads/') === 0) {
            return base_url('/' . ltrim($value, '/'));
        }
        return base_url('/uploads/products/' . ltrim($value, '/'));
    }

    public function searchCustomers()
    {
        $q = $this->request->getGet('q');
        $results = [];
        if (empty($q)) return $this->response->setJSON($results);

        $customerModel = new \App\Models\CustomerModel();
        // Customers table stores the code as customer_code (not code)
        $rows = $customerModel
            ->groupStart()
                ->like('customer_code', $q)
                ->orLike('name', $q)
                ->orLike('company_name', $q)
            ->groupEnd()
            ->limit(20)
            ->find();
        foreach ($rows as $c) {
            $results[] = [
                'id' => $c['id'],
                'code' => $c['customer_code'] ?? '',
                'name' => $c['name'] ?? '',
                'company' => $c['company_name'] ?? '',
                'email' => $c['email'] ?? '',
            ];
        }
        return $this->response->setJSON($results);
    }

    public function getPriceLists($customerId = null)
    {
        $customerId = (int)$customerId;
        if (!$customerId) return $this->response->setJSON([]);
        $plModel = new \App\Models\PriceListModel();
        $lists = $plModel->getCustomerPriceList($customerId);
        return $this->response->setJSON($lists);
    }

    public function calculateQuote()
    {
        $payload = $this->request->getJSON(true);
        if (!$payload) $payload = $this->request->getPost();
        $lines = [];
        $shippingAmount = isset($payload['shipping_amount']) ? (float)$payload['shipping_amount'] : 0.0;
        if (is_array($payload) && isset($payload['lines']) && is_array($payload['lines'])) {
            $lines = $payload['lines'];
        } elseif (is_array($payload) && array_values($payload) === $payload) {
            $lines = $payload;
        }
        $qm = new QuotationModel();
        $calc = $qm->calculateTotals($lines, $shippingAmount);
        return $this->response->setJSON(['success' => true, 'data' => $calc]);
    }

    public function updateLine($lineId = null)
    {
        $method = strtolower($this->request->getMethod());
        if ($method === 'options') {
            return $this->response->setJSON(['success' => true]);
        }
        if ($method !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        $payload = $this->request->getJSON(true);
        if (!$payload) $payload = $this->request->getPost();

    $lineModel = new QuotationLineModel();
    $qModel = new QuotationModel();

        $line = $lineModel->find($lineId);
        if (!$line) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Line not found']);
        }

        $quotationId = (int)$line['quotation_id'];
        $quoteHeader = $qModel->find($quotationId) ?: [];

        $allowed = ['quantity','unit_price','discount_value','discount_type','tax_rate','description','product_code','product_name','unit'];
        $update = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $payload)) {
                $update[$k] = $payload[$k];
            }
        }
        if (isset($update['quantity'])) $update['quantity'] = (float)$update['quantity'];
        if (isset($update['unit_price'])) $update['unit_price'] = (float)$update['unit_price'];
        if (isset($update['discount_value'])) $update['discount_value'] = (float)$update['discount_value'];
        if (isset($update['tax_rate'])) $update['tax_rate'] = (float)$update['tax_rate'];

        $merged = array_merge($line, $update);
        $calc = $lineModel->calculateLineTotal($merged);

        $update['tax_amount'] = round($calc['tax_amount'], 2);
        $update['line_total'] = round($calc['line_total'], 2);

        try {
            $lineModel->update($lineId, $update);

            $shippingAmount = isset($quoteHeader['shipping_amount']) ? (float)$quoteHeader['shipping_amount'] : 0.0;
            $lines = $qModel->getQuotationLines($quotationId);
            $totals = $qModel->calculateTotals($lines, $shippingAmount);

            $db = \Config\Database::connect();
            try { $cols = $db->getFieldNames($qModel->table); } catch (\Throwable $_) { $cols = $qModel->allowedFields; }
            $upd = [];
            if (in_array('subtotal', $cols)) $upd['subtotal'] = $totals['subtotal'];
            if (in_array('discount', $cols)) $upd['discount'] = $totals['discount'];
            elseif (in_array('document_discount_value', $cols)) $upd['document_discount_value'] = $totals['discount'];
            if (in_array('tax', $cols)) $upd['tax'] = $totals['tax'];
            elseif (in_array('tax_total', $cols)) $upd['tax_total'] = $totals['tax'];
            // (shipping_amount intentionally not modified here)
            if (in_array('total_weight', $cols)) $upd['total_weight'] = $totals['total_weight'];
            if (in_array('total', $cols)) $upd['total'] = $totals['total'];
            if (!empty($upd)) {
                $qModel->update($quotationId, $upd);
            }

            $respLine = array_merge($merged, [
                'base_amount' => $calc['base_amount'],
                'discount_amount' => $calc['discount_amount'],
                'net_amount' => $calc['net_amount'],
                'tax_amount' => $calc['tax_amount'],
                'line_total' => $calc['line_total'],
                'unit_price' => $calc['unit_price'],
                'quantity' => $calc['quantity'],
                'discount_value' => $calc['discount_value'],
                'discount_type' => $calc['discount_type'],
                'tax_rate' => $calc['tax_rate']
            ]);

            return $this->response->setJSON([
                'success' => true,
                'line' => $respLine,
                'totals' => $totals
            ]);
        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Delete a quotation by id. Accepts POST or DELETE.
     */
    public function delete($identifier = null)
    {
        // Handle both numeric ID and public_id identifiers
        $id = null;
        $isNumeric = is_numeric($identifier);
        
        if ($isNumeric) {
            $id = (int)$identifier;
        } else {
            // Try to find by public_id
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $id = (int)$row['id'];
            }
        }
        
        if (!$id) {
            return $this->response->setStatusCode(400)->setBody('Invalid quotation id');
        }

        try {
            $quote = $this->quotationModel->find($id);
            if ($quote) {
                $customerName = null;
                try {
                    $cust = (new \App\Models\CustomerModel())->find((int) ($quote['customer_id'] ?? 0));
                    $customerName = $cust['name'] ?? ($cust['company_name'] ?? null);
                } catch (\Throwable $_) {
                    $customerName = null;
                }

                DocumentLogger::log(DocumentLogger::TYPE_QUOTATION, $id, DocumentLogger::ACTION_DELETED, [
                    'quote_number' => (string) ($quote['quote_number'] ?? ''),
                    'customer_id' => (int) ($quote['customer_id'] ?? 0),
                    'customer_name' => $customerName,
                    'source' => 'quotations_controller',
                ]);
            }

            $deleted = $this->quotationModel->delete($id);
            if ($this->request->isAJAX()) {
                return $this->response->setJSON(['success' => (bool)$deleted]);
            }
            if ($deleted) {
                session()->setFlashdata('success', 'Quotation deleted');
            } else {
                session()->setFlashdata('error', 'Failed to delete quotation');
            }
            return redirect()->to(site_url('quotations'));
        } catch (\Throwable $e) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
            }
            session()->setFlashdata('error', 'Failed to delete quotation: ' . $e->getMessage());
            return redirect()->to(site_url('quotations'));
        }
    }

    /**
     * Update shipping amount for a quotation.
     */
    public function updateShipping($identifier = null)
    {
        $method = strtolower($this->request->getMethod());
        if ($method === 'options') {
            return $this->response->setJSON(['success' => true]);
        }
        if ($method !== 'post') {
            return $this->response->setStatusCode(405)->setJSON(['success' => false, 'error' => 'Method not allowed']);
        }

        // Handle both numeric ID and public_id identifiers
        $id = null;
        $isNumeric = is_numeric($identifier);
        
        if ($isNumeric) {
            $id = (int)$identifier;
        } else {
            // Try to find by public_id
            $db = \Config\Database::connect();
            $row = $db->table('quotations')->where('public_id', $identifier)->get()->getRowArray();
            if ($row) {
                $id = (int)$row['id'];
            }
        }
        
        if (!$id) {
            return $this->response->setStatusCode(400)->setJSON(['success' => false, 'error' => 'Invalid quotation id']);
        }

        $payload = $this->request->getJSON(true);
        if (!$payload) $payload = $this->request->getPost();
        $shippingAmount = isset($payload['shipping_amount']) ? (float)$payload['shipping_amount'] : 0.0;

        $qModel = new QuotationModel();
        $quote = $qModel->find($id);
        if (!$quote) {
            return $this->response->setStatusCode(404)->setJSON(['success' => false, 'error' => 'Quotation not found']);
        }


        try {
            $lines = $qModel->getQuotationLines($id);
            $totals = $qModel->calculateTotals($lines, $shippingAmount);

            $db = \Config\Database::connect();
            try { $cols = $db->getFieldNames($qModel->table); } catch (\Throwable $_) { $cols = $qModel->allowedFields; }
            $upd = [];
            // Explicitly persist the user-provided shipping amount for this endpoint.
            if (in_array('shipping_amount', $cols)) $upd['shipping_amount'] = $shippingAmount;
            if (in_array('subtotal', $cols)) $upd['subtotal'] = $totals['subtotal'];
            if (in_array('discount', $cols)) $upd['discount'] = $totals['discount'];
            elseif (in_array('document_discount_value', $cols)) $upd['document_discount_value'] = $totals['discount'];
            if (in_array('tax', $cols)) $upd['tax'] = $totals['tax'];
            elseif (in_array('tax_total', $cols)) $upd['tax_total'] = $totals['tax'];
            if (in_array('total_weight', $cols)) $upd['total_weight'] = $totals['total_weight'];
            if (in_array('total', $cols)) $upd['total'] = $totals['total'];

            if (!empty($upd)) {
                $qModel->update($id, $upd);
            }

            return $this->response->setJSON(['success' => true, 'totals' => $totals]);

        } catch (\Throwable $e) {
            return $this->response->setStatusCode(500)->setJSON(['success' => false, 'error' => $e->getMessage()]);
        }
    }

            public function printView($quoteId = null)
            {
                $quote = $this->quotationModel->findByPublicIdOrId($quoteId);
                if (!$quote) {
                    return redirect()->back()->with('error', 'Quotation not found');
                }
                $quoteId = (int)$quote['id'];

                $db = \Config\Database::connect();
                $customer = [];
                try {
                    $customer = $db->table('customers')->where('id', (int)($quote['customer_id'] ?? 0))->get()->getRowArray() ?: [];
                } catch (\Throwable $_) {}

                $lineModel = new \App\Models\QuotationLineModel();
                $lines = $lineModel->where('quotation_id', $quoteId)->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();

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
                        $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
                        $variantId = isset($line['product_variant_id']) ? (int) $line['product_variant_id'] : null;

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

                $currency = strtoupper(trim((string) ($quote['currency'] ?? $this->getDefaultSalesCurrency())));
                $symbols = ['USD' => '$', 'EUR' => '€', 'GBP' => '£', 'PKR' => '₨', 'INR' => '₹'];
                $sym = $symbols[$currency] ?? $currency;
                $fmt = fn($value) => $sym . ' ' . number_format((float) $value, 2);

                $subtotal = (float) ($quote['subtotal'] ?? 0);
                $shipping = (float) ($quote['shipping_amount'] ?? 0);
                $total = (float) ($quote['total'] ?? 0);
                $quoteNumber = esc($quote['quote_number'] ?? ('QT-' . $quoteId));
                $quoteDate = '';
                $rawDate = trim((string) ($quote['issue_date'] ?? ($quote['quote_date'] ?? ($quote['created_at'] ?? ''))));
                if ($rawDate && strpos($rawDate, '0000') === false) {
                    $ts = strtotime($rawDate);
                    if ($ts) {
                        $quoteDate = date('d-m-Y', $ts);
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
        <title>Quotation <?= $quoteNumber ?></title>
        <style>
          *{box-sizing:border-box;margin:0;padding:0}
          body{font-family:Arial,sans-serif;font-size:12px;color:#1e293b;background:#f8fafc;padding:24px}
          .grn-doc{max-width:1100px;margin:0 auto}
          .grn-hero{background:linear-gradient(135deg,#0f172a 0%,#1e3a5f 100%);border-radius:.75rem .75rem 0 0;padding:1.6rem 2rem 1.4rem;color:#fff;position:relative;overflow:hidden}
          .grn-hero::after{content:'QUOTATION';position:absolute;right:-1rem;top:50%;transform:translateY(-50%);font-size:5.5rem;font-weight:900;opacity:.04;pointer-events:none;user-select:none;line-height:1}
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
            <div class="grn-doc-type">Sales Quotation</div>
            <div class="grn-hero-num"><?= $quoteNumber ?></div>
            <div class="grn-hero-sub"><?= $companyName ?></div>
            <div class="grn-hero-actions no-print">
              <button type="button" class="grn-hero-btn" onclick="window.print()">Print</button>
              <button type="button" class="grn-hero-btn" onclick="window.close()">Close</button>
            </div>
          </div>

          <div class="grn-facts">
            <div class="grn-fact"><div class="grn-fact-lbl">Customer</div><div class="grn-fact-val"><?= $customerName ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Quote Date</div><div class="grn-fact-val"><?= esc($quoteDate ?: '-') ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Currency</div><div class="grn-fact-val"><?= esc($currency) ?></div></div>
            <div class="grn-fact"><div class="grn-fact-lbl">Lines</div><div class="grn-fact-val"><?= number_format(count($printLines), 0) ?></div></div>
          </div>

          <div class="grn-sec">
            <div class="grn-sec-hd">Quotation Lines<span class="grn-sec-badge"><?= number_format(count($printLines), 0) ?></span></div>
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
