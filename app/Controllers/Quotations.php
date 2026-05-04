<?php
namespace App\Controllers;

use App\Models\QuotationModel;
use App\Models\QuotationLineModel;
use App\Services\QuotationService;
use CodeIgniter\Exceptions\PageNotFoundException;
use App\Models\CompanySettingsModel;
use App\Models\Accounting\CurrencyModel;
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

        $allowedLineKeys = ['product_id','product_variant_id','product_code','product_name','product_image_url','description','unit','quantity','unit_price','discount_type','discount_value','tax_rate','unit_weight','weight','weight_unit'];
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

        $lines = $this->enrichViewLines($quote['lines'] ?? []);
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
        try { $logEntries = \App\Libraries\DocumentLogger::getForDocument(\App\Libraries\DocumentLogger::TYPE_QUOTATION, (int)$id); } catch (\Throwable $_) { }

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
                'issue_date' => $quote['issue_date'] ?? date('Y-m-d'),
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

    private function enrichViewLines(array $lines): array
    {
        if (empty($lines)) {
            return $lines;
        }

        $db = \Config\Database::connect();

        // Collect product and variant IDs that have no stored image URL
        $productIds = [];
        $variantIds = [];
        foreach ($lines as $line) {
            if (empty($line['product_image_url'])) {
                if (!empty($line['product_id'])) $productIds[] = (int)$line['product_id'];
                if (!empty($line['product_variant_id'])) $variantIds[] = (int)$line['product_variant_id'];
            }
        }
        $productIds = array_values(array_unique($productIds));
        $variantIds = array_values(array_unique($variantIds));

        $pImgMap = [];
        if (!empty($productIds)) {
            try {
                $productCols = $db->getFieldNames('products');
                $select = ['id'];
                foreach (['image', 'images'] as $col) {
                    if (in_array($col, $productCols, true)) $select[] = $col;
                }
                $products = $db->table('products')->select(implode(', ', $select))->whereIn('id', $productIds)->get()->getResultArray();
                foreach ($products as $p) {
                    $pid = (int)($p['id'] ?? 0);
                    $raw = (string)($p['image'] ?? '');
                    if ($raw === '' && !empty($p['images'])) {
                        $arr = is_string($p['images']) ? json_decode($p['images'], true) : $p['images'];
                        if (is_array($arr) && !empty($arr[0])) {
                            $raw = is_array($arr[0]) ? ($arr[0]['url'] ?? $arr[0]['path'] ?? '') : (string)$arr[0];
                        }
                    }
                    if ($raw !== '') {
                        $pImgMap[$pid] = $this->resolveProductImageUrl($raw);
                    }
                }
            } catch (\Throwable $_) {}
        }

        $vImgMap = [];
        if (!empty($variantIds)) {
            try {
                $variants = $db->table('product_variants')->select('id, image')->whereIn('id', $variantIds)->get()->getResultArray();
                foreach ($variants as $v) {
                    $vid = (int)($v['id'] ?? 0);
                    $raw = (string)($v['image'] ?? '');
                    if ($raw !== '') {
                        $vImgMap[$vid] = $this->resolveProductImageUrl($raw);
                    }
                }
            } catch (\Throwable $_) {}
        }

        foreach ($lines as &$line) {
            if (!empty($line['product_image_url'])) continue;
            $vid = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;
            $pid = !empty($line['product_id']) ? (int)$line['product_id'] : 0;
            if ($vid && isset($vImgMap[$vid])) {
                $line['product_image_url'] = $vImgMap[$vid];
            } elseif ($pid && isset($pImgMap[$pid])) {
                $line['product_image_url'] = $pImgMap[$pid];
            }
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
        if (!empty($variantIds)) {
            try {
                $variants = $db->table('product_variants')
                    ->select('id, name, art_number, image, attributes')
                    ->whereIn('id', $variantIds)
                    ->get()
                    ->getResultArray();

                foreach ($variants as $variant) {
                    $vid = (int)($variant['id'] ?? 0);
                    if (!$vid) {
                        continue;
                    }
                    $attrs = [];
                    if (!empty($variant['attributes'])) {
                        $decoded = json_decode($variant['attributes'], true);
                        if (is_array($decoded)) $attrs = $decoded;
                    }
                    $vMap[$vid] = [
                        'variant_code' => $variant['art_number'] ?? null,
                        'variant_name' => $variant['name'] ?? null,
                        'variant_image_path' => $this->resolvePdfImagePath((string)($variant['image'] ?? ''), 'variants'),
                        'variant_attrs' => $attrs,
                    ];
                }
            } catch (\Throwable $_) {
                // best effort
            }
        }

        foreach ($lines as &$line) {
            $pid = !empty($line['product_id']) ? (int)$line['product_id'] : 0;
            $vid = isset($line['product_variant_id']) ? (int)$line['product_variant_id'] : 0;

            if (!empty($line['product_image_url']) && empty($line['product_image_path'])) {
                $line['product_image_path'] = $this->resolvePdfImagePath((string)$line['product_image_url'], 'products');
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

            if ($vid && isset($vMap[$vid])) {
                if (!empty($vMap[$vid]['variant_code'])) {
                    $line['product_code'] = $vMap[$vid]['variant_code'];
                }
                if (!empty($vMap[$vid]['variant_name'])) {
                    $line['variant_name'] = $vMap[$vid]['variant_name'];
                }
                if (!empty($vMap[$vid]['variant_image_path'])) {
                    $line['product_image_path'] = $vMap[$vid]['variant_image_path'];
                }
                if (!empty($vMap[$vid]['variant_attrs'])) {
                    $line['variant_attrs'] = $vMap[$vid]['variant_attrs'];
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

        $allowedLineKeys = ['id','product_id','product_variant_id','product_code','product_name','product_image_url','description','unit','quantity','unit_price','discount_type','discount_value','tax_rate','unit_weight','weight','weight_unit'];
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
        $currency = strtoupper(trim((string)($post['currency'] ?? ($quote['quote_currency'] ?? ($quote['currency'] ?? ($quote['base_currency'] ?? ''))))));
        if ($currency === '') $currency = $this->getDefaultSalesCurrency();

        $db = \Config\Database::connect();
    $lineModel = new QuotationLineModel();
    $svc = new QuotationService();

    try { $cols = $db->getFieldNames($this->quotationModel->table); } catch (\Throwable $_) { $cols = $this->quotationModel->allowedFields; }

        try {
            $db->transStart();

            // header update
            $headerUpd = [
                'customer_id' => $post['customer_id'] ?? ($quote['customer_id'] ?? null),
                'issue_date' => $this->normalizeDate($issueDateRaw),
                'notes' => $post['notes'] ?? ($quote['notes'] ?? null),
                'status' => $status,
            ];
            if (in_array('currency', $cols)) {
                $headerUpd['currency'] = $currency;
            }
            if (in_array('quote_currency', $cols)) {
                $headerUpd['quote_currency'] = $currency;
            }
            if (in_array('base_currency', $cols) && empty($quote['base_currency'])) {
                $headerUpd['base_currency'] = $currency;
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

            // Write activity logs (outside transaction — these are immutable)
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
                'weight_unit'   => strtoupper(trim((string)($p['weight_unit'] ?? 'KG'))),
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

}
