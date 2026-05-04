<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use Config\Database;
use App\Models\CompanySettingsModel;
use App\Models\FiscalYearModel;
use App\Models\SecuritySettingsModel;
use App\Models\PaymentMethodModel;
use App\Models\ExchangeRateModel;
use App\Models\OdooSettingsModel;
use App\Models\FeatureFlagModel;
use App\Models\Accounting\CurrencyModel;

class Settings extends Controller
{
    protected $db;

    public function __construct()
    {
        $this->db = Database::connect();
        helper(['form', 'url', 'security', 'currency']);
        $this->ensureTables();
    }

    public function index()
    {
        $company = (new CompanySettingsModel())->first();
        $fy = (new FiscalYearModel())->where('is_active', 1)->first();
        $security = (new SecuritySettingsModel())->first();
        $featureFlags = (new FeatureFlagModel())->getAllFlags();
        $methods = (new PaymentMethodModel())->orderBy('method_name', 'ASC')->findAll();
        $rates = (new ExchangeRateModel())->where(['base_code' => 'USD', 'quote_code' => 'PKR'])
            ->orderBy('as_of', 'DESC')->orderBy('id', 'DESC')->findAll();
        $activeRate = get_active_rate('USD', 'PKR');
    $odoo = (new OdooSettingsModel())->first();
        // Load currencies (only active by default)
        try {
            $cm = new CurrencyModel();
            $currencies = $cm->orderBy('code','ASC')->findAll();
        } catch (\Throwable $e) {
            $currencies = [];
        }
        // Art number counter
        $artCounter = 1;
        try {
            $artService = new \App\Services\ArtNumberService();
            $artCounter = $artService->currentGlobalNumber();
        } catch (\Throwable $_) {}

        // Customer code settings
        $customerPrefix = 'RI';
        $customerCounter = 1;
        try {
            $customerModel = new \App\Models\CustomerModel();
            $customerPrefix = $customerModel->getCustomerCodePrefix();
            $customerCounter = $customerModel->peekNextCustomerCodeNumber($customerPrefix);
        } catch (\Throwable $_) {}

        $vendorPrefix = 'VEN';
        $vendorCounter = 1;
        try {
            $vendorModel = new \App\Models\VendorModel();
            $vendorPrefix = $vendorModel->getVendorCodePrefix();
            $vendorCounter = $vendorModel->peekNextVendorCodeNumber($vendorPrefix);
        } catch (\Throwable $_) {}

        $envPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath) && defined('ROOTPATH')) $envPath = ROOTPATH . '.env';
        $currentIp = '';
        if (is_file($envPath)) {
            $content = file_get_contents($envPath);
            if (preg_match('/^app\.baseURL\s*=\s*[\'"]?http:\/\/([a-zA-Z0-9\.\-]+)\/corelynk\/?[\'"]?/m', $content, $m)) {
                $currentIp = $m[1] === 'localhost' ? '' : $m[1];
            }
        }

        // Load mobile API URL + global date format from system_settings
        $mobileApiUrl = '';
        $globalDateFormat = 'Y-m-d';
        try {
            $row = $this->db->table('system_settings')
                ->where('setting_key', 'mobile_api_url')
                ->get()->getRowArray();
            $mobileApiUrl = $row['setting_value'] ?? '';
        } catch (\Throwable $_) {}
        try {
            $row = $this->db->table('system_settings')
                ->where('setting_key', 'global_date_format')
                ->get()->getRowArray();
            $candidate = trim((string)($row['setting_value'] ?? ''));
            if (in_array($candidate, ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'], true)) {
                $globalDateFormat = $candidate;
            }
        } catch (\Throwable $_) {}

        return view('settings/index', [
            'company' => $company,
            'fy' => $fy,
            'security' => $security,
            'feature_flags' => $featureFlags,
            'methods' => $methods,
            'rates' => $rates,
            'activeRate' => $activeRate,
            'odoo' => $odoo,
            'currencies' => $currencies,
            'art_counter' => $artCounter,
            'customer_code_prefix' => $customerPrefix,
            'customer_counter' => $customerCounter,
            'vendor_code_prefix' => $vendorPrefix,
            'vendor_counter' => $vendorCounter,
            'current_ip' => $currentIp,
            'mobile_api_url' => $mobileApiUrl,
            'global_date_format' => $globalDateFormat,
            'validation' => \Config\Services::validation(),
            'csrf' => csrf_hash()
        ]);
    }

    public function addCurrency()
    {
        if (!$this->request->is('post')) return redirect()->back();
        $code = strtoupper(trim($this->request->getPost('code')));
        $name = trim($this->request->getPost('name')) ?: $code;
        $symbol = $this->request->getPost('symbol') ?: null;
        if ($code === '') {
            return redirect()->back()->with('error','Invalid currency code');
        }
        $cm = new CurrencyModel();
        // Prevent duplicate
        if ($cm->find($code)) {
            return redirect()->back()->with('error','Currency code already exists');
        }
        $cm->insert(['code' => $code, 'name' => $name, 'symbol' => $symbol, 'is_base' => 0, 'decimals' => 2, 'is_active' => 1]);
        return redirect()->to(site_url('settings'))->with('success', 'Currency added');
    }

    public function toggleCurrency($code = null)
    {
        if (!$code) return $this->response->setJSON(['success'=>false,'message'=>'Missing code']);
        $cm = new CurrencyModel();
        $cur = $cm->find($code);
        if (!$cur) return $this->response->setJSON(['success'=>false,'message'=>'Currency not found']);
        $cm->update($code, ['is_active' => $cur['is_active'] ? 0 : 1]);
        return $this->response->setJSON(['success'=>true,'new_status' => $cur['is_active'] ? 0 : 1]);
    }

    public function saveOdoo()
    {
        $rules = [
            'host' => 'required|min_length[3]|max_length[255]',
            'port' => 'permit_empty|is_natural',
            'db_name' => 'required|min_length[1]|max_length[150]',
            'username' => 'required|min_length[1]|max_length[150]',
            'password' => 'permit_empty|max_length[255]'
        ];
        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Please correct the Odoo settings.');
        }

        $model = new OdooSettingsModel();
        $payload = [
            'host' => esc($this->request->getPost('host')),
            'port' => $this->request->getPost('port') ? (int)$this->request->getPost('port') : null,
            'db_name' => esc($this->request->getPost('db_name')),
            'username' => esc($this->request->getPost('username')),
            'password' => esc($this->request->getPost('password')),
            'job_mode' => in_array($this->request->getPost('job_mode'), ['disabled','manual','cron']) ? $this->request->getPost('job_mode') : 'manual',
            'job_interval' => $this->request->getPost('job_interval') ? (int)$this->request->getPost('job_interval') : null,
            'fetch_limit' => $this->request->getPost('fetch_limit') ? (int)$this->request->getPost('fetch_limit') : 10,
            'last_run' => $this->request->getPost('last_run') ?? null
        ];
        $existing = $model->first();
        if ($existing) {
            $model->update($existing['id'], $payload);
        } else {
            $model->insert($payload);
        }
        return redirect()->to(base_url('settings'))->with('success', 'Odoo settings saved.');
    }

    public function saveCompany()
    {
        $request = service('request');
        if (!$request->is('post')) {
            return redirect()->to(base_url('settings'));
        }
        if (!$this->validate([
            'name' => 'required|min_length[2]|max_length[150]',
            'address' => 'permit_empty|max_length[500]',
            'contact' => 'permit_empty|max_length[150]',
            'phone' => 'permit_empty|max_length[50]',
            'email' => 'permit_empty|valid_email',
            'website' => 'permit_empty|max_length[255]',
            'invoice_footer' => 'permit_empty|max_length[500]',
            'pdf_template' => 'permit_empty|in_list[default,modern_blue,classic_green,professional_gray,bold_red,elegant_purple]',
            'use_demo_data' => 'permit_empty|in_list[0,1]',
            'logo' => 'permit_empty|uploaded[logo]|max_size[logo,2048]|ext_in[logo,png,jpg,jpeg,webp]'
        ])) {
            return redirect()->back()->withInput()->with('error', 'Please correct the errors.');
        }
        $model = new CompanySettingsModel();
        $companyRow = (new CompanySettingsModel())->first();
        $fallbackBase = $companyRow['base_currency'] ?? 'PKR';
        $fallbackSecondary = $companyRow['secondary_currency'] ?? 'USD';
        $payload = [
            'name' => esc($this->request->getPost('name')),
            'address' => esc($this->request->getPost('address')),
            'contact' => esc($this->request->getPost('contact')),
            'phone' => esc($this->request->getPost('phone')),
            'email' => esc($this->request->getPost('email')),
            'website' => esc($this->request->getPost('website')),
            'invoice_footer' => esc($this->request->getPost('invoice_footer')),
            'pdf_template' => esc($this->request->getPost('pdf_template') ?? 'default'),
            'base_currency' => $fallbackBase,
            'secondary_currency' => $fallbackSecondary,
            'default_sales_currency' => esc($this->request->getPost('default_sales_currency') ?? ($companyRow['default_sales_currency'] ?? $fallbackBase)),
            'default_purchase_currency' => esc($this->request->getPost('default_purchase_currency') ?? ($companyRow['default_purchase_currency'] ?? $fallbackBase)),
            'use_demo_data' => (int)$this->request->getPost('use_demo_data'),
            'pdf_inv_show_header' => $this->request->getPost('pdf_inv_show_header') !== null ? 1 : 0,
            'pdf_inv_show_footer' => $this->request->getPost('pdf_inv_show_footer') !== null ? 1 : 0,
            'pdf_quote_show_header' => $this->request->getPost('pdf_quote_show_header') !== null ? 1 : 0,
            'pdf_quote_show_footer' => $this->request->getPost('pdf_quote_show_footer') !== null ? 1 : 0,
            'pdf_so_show_header' => $this->request->getPost('pdf_so_show_header') !== null ? 1 : 0,
            'pdf_so_show_footer' => $this->request->getPost('pdf_so_show_footer') !== null ? 1 : 0,
            'pdf_po_show_header' => $this->request->getPost('pdf_po_show_header') !== null ? 1 : 0,
            'pdf_po_show_footer' => $this->request->getPost('pdf_po_show_footer') !== null ? 1 : 0,
            'pdf_rfq_show_header' => $this->request->getPost('pdf_rfq_show_header') !== null ? 1 : 0,
            'pdf_rfq_show_footer' => $this->request->getPost('pdf_rfq_show_footer') !== null ? 1 : 0,
        ];

        // Document number prefixes (schema-safe)
        try {
            $cols = $this->db->getFieldNames('company_settings');
            if (in_array('quotation_prefix', $cols)) {
                $payload['quotation_prefix'] = trim((string)$this->request->getPost('quotation_prefix')) ?: null;
            }
            if (in_array('sales_order_prefix', $cols)) {
                $payload['sales_order_prefix'] = trim((string)$this->request->getPost('sales_order_prefix')) ?: null;
            }
            if (in_array('art_number_prefix', $cols)) {
                $artInput = (string)$this->request->getPost('art_number_prefix');
                if (trim($artInput) === '') {
                    $artInput = (string)($companyRow['art_number_prefix'] ?? 'RI');
                }
                $raw = strtoupper(preg_replace('/[^A-Za-z]/', '', $artInput));
                $payload['art_number_prefix'] = $raw ?: 'RI';
            }
            if (in_array('customer_code_prefix', $cols)) {
                $custInput = (string)$this->request->getPost('customer_code_prefix');
                if (trim($custInput) === '') {
                    $custInput = (string)($companyRow['customer_code_prefix'] ?? ($payload['art_number_prefix'] ?? 'RI'));
                }
                $cust = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $custInput));
                $payload['customer_code_prefix'] = $cust ?: ($payload['art_number_prefix'] ?? 'RI');
            }
            if (in_array('vendor_code_prefix', $cols)) {
                $vendorInput = (string)$this->request->getPost('vendor_code_prefix');
                if (trim($vendorInput) === '') {
                    $vendorInput = (string)($companyRow['vendor_code_prefix'] ?? 'VEN');
                }
                $vendorPrefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $vendorInput));
                $payload['vendor_code_prefix'] = $vendorPrefix ?: 'VEN';
            }
        } catch (\Throwable $_) {
            // best-effort
        }
        $existing = $model->first();
        $logoPath = $existing['logo_path'] ?? null;
        $logoFile = $this->request->getFile('logo');
        if ($logoFile && $logoFile->isValid()) {
            $dir = FCPATH . 'uploads/company';
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            // Delete ALL previous company logo files (any extension, any timestamp) so no stale
            // files linger on disk and every cache layer is forced to pick up the new file.
            $oldFiles = glob($dir . DIRECTORY_SEPARATOR . 'company-logo*.*') ?: [];
            foreach ($oldFiles as $oldFile) {
                if (is_file($oldFile)) {
                    @unlink($oldFile);
                }
            }
            $ext = strtolower($logoFile->getExtension());
            // Embed a timestamp in the filename so the DB path changes on every upload.
            // This busts PHP stat/realpath caches, Dompdf internal caches, and browser caches
            // — all in one step, with no need for cache-busting headers alone.
            $safeName = 'company-logo-' . time() . '.' . $ext;
            $logoFile->move($dir, $safeName, true);
            $logoPath = 'uploads/company/' . $safeName;
            $payload['logo_path'] = $logoPath;
        }
        if ($existing) {
            $model->update($existing['id'], $payload);
        } else {
            $model->insert($payload);
        }
        // Art number global counter (stored in art_number_counter table, not company_settings)
        try {
            $newCounter = $this->request->getPost('art_number_next');
            if ($newCounter !== null && $newCounter !== '') {
                $artService = new \App\Services\ArtNumberService();
                $artService->setGlobalCounter((int)$newCounter);
            }

            $newCustomerCounter = $this->request->getPost('customer_code_next');
            if ($newCustomerCounter !== null && $newCustomerCounter !== '') {
                $customerModel = new \App\Models\CustomerModel();
                $customerPrefix = (string)($payload['customer_code_prefix'] ?? ($companyRow['customer_code_prefix'] ?? ($payload['art_number_prefix'] ?? 'RI')));
                $customerModel->setNextCustomerCodeNumber((int)$newCustomerCounter, $customerPrefix);
            }

            $newVendorCounter = $this->request->getPost('vendor_code_next');
            if ($newVendorCounter !== null && $newVendorCounter !== '') {
                $vendorModel = new \App\Models\VendorModel();
                $vendorPrefix = (string)($payload['vendor_code_prefix'] ?? ($companyRow['vendor_code_prefix'] ?? 'VEN'));
                $vendorModel->setNextVendorCodeNumber((int)$newVendorCounter, $vendorPrefix);
            }
        } catch (\Throwable $e) {
            return redirect()->to(base_url('settings'))->with('error', $e->getMessage());
        }

        return redirect()->to(base_url('settings'))->with('success', 'Company settings updated.');
    }

    public function saveFiscalYear()
    {
        if (!$this->validate([
            'start_date' => 'required|valid_date',
            'end_date' => 'required|valid_date'
        ])) {
            return redirect()->back()->withInput()->with('error', 'Invalid dates.');
        }
        $model = new FiscalYearModel();
        // Deactivate existing
        $model->where('is_active', 1)->set('is_active', 0)->update();
        // Insert new
        $model->insert([
            'start_date' => $this->request->getPost('start_date'),
            'end_date' => $this->request->getPost('end_date'),
            'is_active' => 1
        ]);
        return redirect()->to(base_url('settings'))->with('success', 'Fiscal year updated.');
    }

    public function saveSecurity()
    {
        if (!$this->validate([
            'backdate_password' => 'required|min_length[6]'
        ])) {
            return redirect()->back()->withInput()->with('error', 'Password too short.');
        }
        $hash = password_hash($this->request->getPost('backdate_password'), PASSWORD_DEFAULT);
        $model = new SecuritySettingsModel();
        $existing = $model->first();
        $payload = ['backdate_password_hash' => $hash];
        if ($existing) {
            $model->update($existing['id'], $payload);
        } else {
            $model->insert($payload);
        }
        return redirect()->to(base_url('settings'))->with('success', 'Security settings saved.');
    }

    public function saveNetwork()
    {
        $ip = trim((string)$this->request->getPost('network_ip'));
        $envPath = rtrim(FCPATH, '\\/') . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env';
        if (!is_file($envPath) && defined('ROOTPATH')) $envPath = ROOTPATH . '.env';
        
        if (is_file($envPath)) {
            $content = file_get_contents($envPath);
            $newBaseURL = empty($ip) ? 'http://localhost/corelynk/' : "http://{$ip}/corelynk/";
            
            if (preg_match('/^app\.baseURL\s*=/m', $content)) {
                $content = preg_replace('/^app\.baseURL\s*=.*$/m', "app.baseURL = '{$newBaseURL}'", $content);
            } else {
                $content .= "\napp.baseURL = '{$newBaseURL}'\n";
            }
            file_put_contents($envPath, $content);
            
            return redirect()->to(base_url('settings'))->with('success', "Network IP updated! You can now access the system universally at {$newBaseURL}");
        }
        
        return redirect()->back()->with('error', 'Could not access the configuration file.');
    }

    public function addPaymentMethod()
    {
        if (!$this->validate(['method_name' => 'required|min_length[3]|max_length[100]'])) {
            return redirect()->back()->withInput()->with('error', 'Invalid method.');
        }
        $model = new PaymentMethodModel();
        $model->insert([
            'method_name' => esc($this->request->getPost('method_name')),
            'is_active' => 1
        ]);
        return redirect()->to(base_url('settings'))->with('success', 'Payment method added.');
    }

    public function addExchangeRate()
    {
        if (!$this->validate([
            'rate' => 'required|decimal',
            'as_of' => 'required|valid_date'
        ])) {
            return redirect()->back()->withInput()->with('error', 'Invalid rate or date.');
        }
        $model = new ExchangeRateModel();
        $model->insert([
            'base_code' => 'USD',
            'quote_code' => 'PKR',
            'rate' => (float)$this->request->getPost('rate'),
            'as_of' => $this->request->getPost('as_of')
        ]);
        return redirect()->to(base_url('settings'))->with('success', 'Exchange rate recorded.');
    }

    public function cleanDatabase()
    {
        if (!$this->request->is('post')) return redirect()->back();

        $password = (string)$this->request->getPost('clean_password');
        if ($password !== 'redhat') {
            return redirect()->back()->with('error', 'Invalid clean password.');
        }

        $modules = $this->request->getPost('modules');
        if (empty($modules) || !is_array($modules)) {
            return redirect()->back()->with('error', 'Select at least one module to clean.');
        }

        $map = [
            'po' => [
                'purchase_grn_lines',
                'purchase_grns',
                'purchase_order_lines',
                'purchase_orders'
            ],
            'rfq' => [
                'purchase_rfq_lines',
                'purchase_rfqs'
            ],
            'sales_orders' => [
                'sales_order_lines',
                'sales_orders'
            ],
            'quotations' => [
                'quotation_lines',
                'quotation_shipping',
                'quotations'
            ],
            'invoices' => [
                'customer_invoice_lines',
                'invoice_documents',
                'customer_invoices'
            ],
            'accounting_journals' => [
                'journal_lines',
                'journal_entries'
            ],
            'accounting_cheques' => [
                'cheque_lines',
                'cheques'
            ],
            'products' => [
                'variant_inventory',
                'product_stock_transactions',
                'product_attribute_assignments',
                'product_workflow_assignments',
                'product_processes',
                'product_vendors',
                'product_variants',
                'price_list_items',
                'products'
            ],
            'grn' => [
                'purchase_grn_lines',
                'purchase_grns'
            ],
            'delivery_orders' => [
                'delivery_order_lines',
                'delivery_orders'
            ],
            'shipped_dos' => [
                'delivery_order_lines',
                'delivery_orders'
            ],
            'ready_to_ship' => [
                'delivery_order_lines',
                'delivery_orders'
            ],
        ];

        $tables = [];
        foreach ($modules as $m) {
            if (isset($map[$m])) {
                $tables = array_merge($tables, $map[$m]);
            }
        }
        $tables = array_values(array_unique($tables));
        if (empty($tables)) {
            return redirect()->back()->with('error', 'No valid modules selected.');
        }

        $db = Database::connect();
        $db->transBegin();
        try {
            foreach ($tables as $t) {
                if ($db->tableExists($t)) {
                    $db->query("DELETE FROM `{$t}`");
                }
            }
            foreach ($tables as $t) {
                if ($db->tableExists($t)) {
                    $db->query("ALTER TABLE `{$t}` AUTO_INCREMENT = 1");
                }
            }
            $db->transCommit();
            return redirect()->back()->with('success', 'Selected modules cleaned successfully.');
        } catch (\Throwable $e) {
            $db->transRollback();
            return redirect()->back()->with('error', 'Clean failed: '.$e->getMessage());
        }
    }

    private function ensureTables(): void
    {
        try {
            // Create minimal tables if missing
            $this->db->query("CREATE TABLE IF NOT EXISTS company_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NULL,
                address VARCHAR(500) NULL,
                contact VARCHAR(150) NULL,
                email VARCHAR(150) NULL,
                logo_path VARCHAR(255) NULL,
                base_currency VARCHAR(10) DEFAULT 'PKR',
                secondary_currency VARCHAR(10) DEFAULT 'USD',
                default_sales_currency VARCHAR(10) NULL,
                default_purchase_currency VARCHAR(10) NULL,
                use_demo_data TINYINT(1) DEFAULT 1,
                quotation_prefix VARCHAR(20) NULL,
                sales_order_prefix VARCHAR(20) NULL,
                customer_code_prefix VARCHAR(20) NULL,
                vendor_code_prefix VARCHAR(20) NULL,
                pdf_inv_show_header TINYINT(1) DEFAULT 1,
                pdf_inv_show_footer TINYINT(1) DEFAULT 1,
                pdf_quote_show_header TINYINT(1) DEFAULT 1,
                pdf_quote_show_footer TINYINT(1) DEFAULT 1,
                pdf_so_show_header TINYINT(1) DEFAULT 1,
                pdf_so_show_footer TINYINT(1) DEFAULT 1,
                pdf_po_show_header TINYINT(1) DEFAULT 1,
                pdf_po_show_footer TINYINT(1) DEFAULT 1,
                pdf_rfq_show_header TINYINT(1) DEFAULT 1,
                pdf_rfq_show_footer TINYINT(1) DEFAULT 1,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Schema-safe: add columns if table existed before
            try {
                $cols = $this->db->getFieldNames('company_settings');
                if (!in_array('logo_path', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN logo_path VARCHAR(255) NULL AFTER email");
                }
                if (!in_array('quotation_prefix', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN quotation_prefix VARCHAR(20) NULL AFTER use_demo_data");
                }
                if (!in_array('sales_order_prefix', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN sales_order_prefix VARCHAR(20) NULL AFTER quotation_prefix");
                }
                if (!in_array('default_sales_currency', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN default_sales_currency VARCHAR(10) NULL AFTER secondary_currency");
                }
                if (!in_array('default_purchase_currency', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN default_purchase_currency VARCHAR(10) NULL AFTER default_sales_currency");
                }
                if (!in_array('customer_code_prefix', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN customer_code_prefix VARCHAR(20) NULL AFTER sales_order_prefix");
                }
                if (!in_array('vendor_code_prefix', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN vendor_code_prefix VARCHAR(20) NULL AFTER customer_code_prefix");
                }
                if (!in_array('pdf_po_show_footer', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN pdf_inv_show_header TINYINT(1) DEFAULT 1, ADD COLUMN pdf_inv_show_footer TINYINT(1) DEFAULT 1, ADD COLUMN pdf_quote_show_header TINYINT(1) DEFAULT 1, ADD COLUMN pdf_quote_show_footer TINYINT(1) DEFAULT 1, ADD COLUMN pdf_so_show_header TINYINT(1) DEFAULT 1, ADD COLUMN pdf_so_show_footer TINYINT(1) DEFAULT 1, ADD COLUMN pdf_po_show_header TINYINT(1) DEFAULT 1, ADD COLUMN pdf_po_show_footer TINYINT(1) DEFAULT 1, ADD COLUMN pdf_rfq_show_header TINYINT(1) DEFAULT 1, ADD COLUMN pdf_rfq_show_footer TINYINT(1) DEFAULT 1");
                }
                if (!in_array('pdf_rfq_show_header', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN pdf_rfq_show_header TINYINT(1) DEFAULT 1, ADD COLUMN pdf_rfq_show_footer TINYINT(1) DEFAULT 1");
                }
            } catch (\Throwable $_) {
                // best-effort
            }

            $this->db->query("CREATE TABLE IF NOT EXISTS fiscal_year (
                id INT AUTO_INCREMENT PRIMARY KEY,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS security_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                backdate_password_hash VARCHAR(255) NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS payment_methods (
                id INT AUTO_INCREMENT PRIMARY KEY,
                method_name VARCHAR(100) NOT NULL,
                is_active TINYINT(1) DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS exchange_rate (
                id INT AUTO_INCREMENT PRIMARY KEY,
                base_code VARCHAR(10) NOT NULL,
                quote_code VARCHAR(10) NOT NULL,
                rate DECIMAL(15,6) NOT NULL,
                as_of DATE NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_pair_date (base_code, quote_code, as_of)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Odoo integration settings
            $this->db->query("CREATE TABLE IF NOT EXISTS odoo_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                host VARCHAR(255) NULL,
                port INT NULL,
                db_name VARCHAR(150) NULL,
                username VARCHAR(150) NULL,
                password VARCHAR(255) NULL,
                job_mode VARCHAR(20) DEFAULT 'manual',
                job_interval INT NULL,
                fetch_limit INT DEFAULT 10,
                last_run DATETIME NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Backfill/add missing columns for older installs
            try {
                $this->db->query("ALTER TABLE odoo_settings ADD COLUMN IF NOT EXISTS job_mode VARCHAR(20) DEFAULT 'manual';");
                $this->db->query("ALTER TABLE odoo_settings ADD COLUMN IF NOT EXISTS job_interval INT NULL;");
                $this->db->query("ALTER TABLE odoo_settings ADD COLUMN IF NOT EXISTS fetch_limit INT DEFAULT 10;");
                $this->db->query("ALTER TABLE odoo_settings ADD COLUMN IF NOT EXISTS last_run DATETIME NULL;");
            } catch (\Throwable $e) {
                // some MySQL versions may not support IF NOT EXISTS on ALTER - ignore errors
            }

            // Mapping/cache tables for Odoo follow-up screen
            $this->db->query("CREATE TABLE IF NOT EXISTS sales_cache (
                odoo_id INT PRIMARY KEY,
                name VARCHAR(150) NULL,
                partner_id INT NULL,
                partner_code VARCHAR(150) NULL,
                date_order DATETIME NULL,
                commitment_date DATETIME NULL,
                user_id INT NULL,
                state VARCHAR(50) NULL,
                amount_total DECIMAL(15,2) NULL,
                remaining_qty DECIMAL(15,4) DEFAULT 0,
                has_pending_po TINYINT(1) DEFAULT 0,
                order_line_ids LONGTEXT NULL,
                metadata LONGTEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS sale_lines_cache (
                odoo_id INT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(255) NULL,
                product_uom_qty DECIMAL(15,4) DEFAULT 0,
                qty_delivered DECIMAL(15,4) DEFAULT 0,
                price_unit DECIMAL(15,4) DEFAULT 0,
                metadata LONGTEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_sale_order (order_id),
                INDEX idx_sale_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS purchases_cache (
                odoo_id INT PRIMARY KEY,
                name VARCHAR(150) NULL,
                partner_id INT NULL,
                partner_code VARCHAR(150) NULL,
                date_order DATETIME NULL,
                state VARCHAR(50) NULL,
                ordered_qty DECIMAL(15,4) DEFAULT 0,
                received_qty DECIMAL(15,4) DEFAULT 0,
                outstanding_qty DECIMAL(15,4) DEFAULT 0,
                order_line_ids LONGTEXT NULL,
                metadata LONGTEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS purchase_lines_cache (
                odoo_id INT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NULL,
                product_name VARCHAR(255) NULL,
                product_qty DECIMAL(15,4) DEFAULT 0,
                qty_received DECIMAL(15,4) DEFAULT 0,
                metadata LONGTEXT NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,
                INDEX idx_purchase_order (order_id),
                INDEX idx_purchase_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Seed a default USD->PKR rate if table is empty
            $count = $this->db->query("SELECT COUNT(*) AS c FROM exchange_rate")->getRow('c');
            if ((int)$count === 0) {
                $this->db->query("INSERT INTO exchange_rate (base_code, quote_code, rate, as_of) VALUES ('USD','PKR',280.0000, CURDATE())");
            }
        } catch (\Throwable $e) {
            log_message('error', 'Settings ensureTables error: ' . $e->getMessage());
        }
    }

    // ─── Mobile App Settings ─────────────────────────────────────────────────

    public function saveMobileSettings()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings'));
        }

        $url = trim($this->request->getPost('mobile_api_url') ?? '');

        // Validate: allow empty (clear) or a proper http/https URL
        if ($url !== '' && !filter_var($url, FILTER_VALIDATE_URL)) {
            return redirect()->back()->with('error', 'Invalid URL format. Use http:// or https://');
        }
        if ($url !== '' && !preg_match('/^https?:\/\//i', $url)) {
            return redirect()->back()->with('error', 'URL must start with http:// or https://');
        }

        try {
            $existing = $this->db->table('system_settings')
                ->where('setting_key', 'mobile_api_url')
                ->get()->getRowArray();

            $userId = session()->get('user_id');

            if ($existing) {
                $this->db->table('system_settings')
                    ->where('setting_key', 'mobile_api_url')
                    ->update([
                        'setting_value' => $url,
                        'updated_by'    => $userId,
                    ]);
            } else {
                $this->db->table('system_settings')->insert([
                    'setting_key'   => 'mobile_api_url',
                    'setting_value' => $url,
                    'description'   => 'Mobile app API server base URL',
                    'updated_by'    => $userId,
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'saveMobileSettings error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save setting.');
        }

        return redirect()->to(base_url('settings') . '#mobile')
            ->with('success', $url ? 'Mobile server URL saved.' : 'Mobile server URL cleared.');
    }

    public function saveDateFormat()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings'));
        }

        $format = trim((string)$this->request->getPost('global_date_format'));
        $allowed = ['Y-m-d', 'd-m-Y', 'd/m/Y', 'm/d/Y'];
        if (!in_array($format, $allowed, true)) {
            return redirect()->back()->with('error', 'Invalid date format selection.');
        }

        try {
            $existing = $this->db->table('system_settings')
                ->where('setting_key', 'global_date_format')
                ->get()->getRowArray();
            $userId = session()->get('user_id');

            if ($existing) {
                $this->db->table('system_settings')
                    ->where('setting_key', 'global_date_format')
                    ->update([
                        'setting_value' => $format,
                        'updated_by'    => $userId,
                    ]);
            } else {
                $this->db->table('system_settings')->insert([
                    'setting_key'   => 'global_date_format',
                    'setting_value' => $format,
                    'description'   => 'Global UI date format',
                    'updated_by'    => $userId,
                ]);
            }
        } catch (\Throwable $e) {
            log_message('error', 'saveDateFormat error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save date format setting.');
        }

        return redirect()->to(base_url('settings') . '#security')
            ->with('success', 'Global date format updated.');
    }
}
