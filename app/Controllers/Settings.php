<?php

namespace App\Controllers;

use Config\Database;
use App\Models\CompanySettingsModel;
use App\Models\FiscalYearModel;
use App\Models\SecuritySettingsModel;
use App\Models\PaymentMethodModel;
use App\Models\ExchangeRateModel;
use App\Models\OdooSettingsModel;
use App\Models\FeatureFlagModel;
use App\Models\SystemBackupJobModel;
use App\Models\SystemBackupScheduleModel;
use App\Models\SystemSyncEnvironmentModel;
use App\Models\SystemSyncScanModel;
use App\Models\Accounting\CurrencyModel;
use App\Services\SystemBackupService;
use App\Services\SystemSyncService;

class Settings extends BaseController
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
        $productAssetsRawMaxMb = 1000;
        $productAssetsFinalMaxMb = 500;
        $productAssetsChannelMaxMb = 2500;
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
        try {
            $rows = $this->db->table('system_settings')
                ->whereIn('setting_key', [
                    'product_assets_raw_max_mb',
                    'product_assets_final_max_mb',
                    'product_assets_channel_max_mb',
                ])
                ->get()->getResultArray();
            $kv = [];
            foreach ($rows as $r) {
                $kv[(string) ($r['setting_key'] ?? '')] = (string) ($r['setting_value'] ?? '');
            }

            $rawMb = (int) ($kv['product_assets_raw_max_mb'] ?? $productAssetsRawMaxMb);
            if ($rawMb > 0) {
                $productAssetsRawMaxMb = $rawMb;
            }

            $finalMb = (int) ($kv['product_assets_final_max_mb'] ?? $productAssetsFinalMaxMb);
            if ($finalMb > 0) {
                $productAssetsFinalMaxMb = $finalMb;
            }

            $channelMb = (int) ($kv['product_assets_channel_max_mb'] ?? $productAssetsChannelMaxMb);
            if ($channelMb > 0) {
                $productAssetsChannelMaxMb = $channelMb;
            }
        } catch (\Throwable $_) {}

        $backupJobs = [];
        $backupSchedules = [];
        $syncEnvironments = [];
        $syncScans = [];
        try {
            $backupJobs = (new SystemBackupJobModel())
                ->orderBy('id', 'DESC')
                ->findAll(15);
            $backupSchedules = (new SystemBackupScheduleModel())
                ->orderBy('is_active', 'DESC')
                ->orderBy('name', 'ASC')
                ->findAll();
            $syncEnvironments = (new SystemSyncEnvironmentModel())
                ->orderBy('name', 'ASC')
                ->findAll();
            $syncScans = (new SystemSyncScanModel())
                ->orderBy('id', 'DESC')
                ->findAll(15);
        } catch (\Throwable $e) {
            log_message('error', 'Settings backup load error: ' . $e->getMessage());
        }

        return view('settings/index', [
            'company' => $company,
            'timezone_options' => $this->getTimezoneOptions(),
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
            'product_assets_raw_max_mb' => $productAssetsRawMaxMb,
            'product_assets_final_max_mb' => $productAssetsFinalMaxMb,
            'product_assets_channel_max_mb' => $productAssetsChannelMaxMb,
            'backup_jobs' => $backupJobs,
            'backup_schedules' => $backupSchedules,
            'sync_environments' => $syncEnvironments,
            'sync_scans' => $syncScans,
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
            'timezone' => 'permit_empty|max_length[64]',
            'invoice_footer' => 'permit_empty|max_length[500]',
            'pdf_template' => 'permit_empty|in_list[default,modern_blue,classic_green,professional_gray,bold_red,elegant_purple]',
            'use_demo_data' => 'permit_empty|in_list[0,1]',
            'logo' => 'permit_empty|uploaded[logo]|max_size[logo,2048]|ext_in[logo,png,jpg,jpeg,webp]'
        ])) {
            return redirect()->back()->withInput()->with('error', 'Please correct the errors.');
        }
        $model = new CompanySettingsModel();
        $companyRow = (new CompanySettingsModel())->first();
        $timezoneInput = trim((string)$this->request->getPost('timezone'));
        if ($timezoneInput !== '' && !in_array($timezoneInput, timezone_identifiers_list(), true)) {
            return redirect()->back()->withInput()->with('error', 'Invalid timezone selected.');
        }
        $timezoneValue = $timezoneInput !== ''
            ? $timezoneInput
            : (trim((string)($companyRow['timezone'] ?? '')) ?: 'Asia/Karachi');
        $fallbackBase = $companyRow['base_currency'] ?? 'PKR';
        $fallbackSecondary = $companyRow['secondary_currency'] ?? 'USD';
        $payload = [
            'name' => esc($this->request->getPost('name')),
            'address' => esc($this->request->getPost('address')),
            'contact' => esc($this->request->getPost('contact')),
            'phone' => esc($this->request->getPost('phone')),
            'email' => esc($this->request->getPost('email')),
            'website' => esc($this->request->getPost('website')),
            'timezone' => $timezoneValue,
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
                timezone VARCHAR(64) NULL,
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
                if (!in_array('timezone', $cols)) {
                    $this->db->query("ALTER TABLE company_settings ADD COLUMN timezone VARCHAR(64) NULL AFTER email");
                    $this->db->query("UPDATE company_settings SET timezone = 'Asia/Karachi' WHERE timezone IS NULL OR timezone = ''");
                }
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

            $this->db->query("CREATE TABLE IF NOT EXISTS system_backup_jobs (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                public_id CHAR(36) NOT NULL,
                job_type VARCHAR(20) NOT NULL DEFAULT 'manual',
                backup_type VARCHAR(20) NOT NULL DEFAULT 'full',
                status VARCHAR(20) NOT NULL DEFAULT 'queued',
                environment_name VARCHAR(50) NOT NULL DEFAULT 'production',
                app_root VARCHAR(255) NULL,
                db_name VARCHAR(128) NULL,
                archive_path VARCHAR(500) NULL,
                archive_name VARCHAR(255) NULL,
                archive_size_bytes BIGINT NULL,
                archive_sha256 CHAR(64) NULL,
                manifest_path VARCHAR(500) NULL,
                health_status VARCHAR(20) NOT NULL DEFAULT 'pending',
                health_details_json LONGTEXT NULL,
                schedule_id BIGINT NULL,
                initiated_by INT NULL,
                error_message TEXT NULL,
                started_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_system_backup_jobs_public_id (public_id),
                KEY idx_system_backup_jobs_status (status),
                KEY idx_system_backup_jobs_schedule (schedule_id),
                KEY idx_system_backup_jobs_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS system_backup_schedules (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                backup_type VARCHAR(20) NOT NULL DEFAULT 'full',
                frequency_type VARCHAR(20) NOT NULL DEFAULT 'daily',
                interval_minutes INT NULL,
                day_of_week TINYINT NULL,
                time_of_day CHAR(5) NULL,
                retention_count INT NOT NULL DEFAULT 5,
                last_run_at DATETIME NULL,
                next_run_at DATETIME NULL,
                created_by INT NULL,
                updated_by INT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_system_backup_schedules_active (is_active),
                KEY idx_system_backup_schedules_next_run (next_run_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS system_sync_environments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(80) NOT NULL,
                app_path VARCHAR(500) NOT NULL,
                db_host VARCHAR(120) NOT NULL DEFAULT '127.0.0.1',
                db_port INT NOT NULL DEFAULT 3306,
                db_name VARCHAR(120) NOT NULL,
                db_user VARCHAR(120) NOT NULL DEFAULT 'root',
                db_password VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_system_sync_env_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("CREATE TABLE IF NOT EXISTS system_sync_scans (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                public_id CHAR(36) NOT NULL,
                source_env_id INT NOT NULL,
                destination_env_id INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'scanned',
                summary_json LONGTEXT NULL,
                safe_operations_json LONGTEXT NULL,
                report_path VARCHAR(500) NULL,
                created_by INT NULL,
                applied_by INT NULL,
                applied_at DATETIME NULL,
                error_message TEXT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_system_sync_scans_public_id (public_id),
                KEY idx_system_sync_scans_status (status),
                KEY idx_system_sync_scans_source_dest (source_env_id, destination_env_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $this->db->query("INSERT INTO system_sync_environments (name, app_path, db_host, db_port, db_name, db_user, db_password, is_active)
                SELECT 'Production', 'C:\\\\xampp\\\\htdocs\\\\corelynk', '127.0.0.1', 3306, 'corelynk_db', 'root', '', 1
                WHERE NOT EXISTS (SELECT 1 FROM system_sync_environments WHERE name = 'Production')");

            $this->db->query("INSERT INTO system_sync_environments (name, app_path, db_host, db_port, db_name, db_user, db_password, is_active)
                SELECT 'Development', 'C:\\\\xampp\\\\htdocs\\\\corelynk_dev', '127.0.0.1', 3306, 'corelynk_db_dev', 'root', '', 1
                WHERE NOT EXISTS (SELECT 1 FROM system_sync_environments WHERE name = 'Development')");

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

    /**
     * Curated timezone list for company settings.
     *
     * @return array<string,string>
     */
    private function getTimezoneOptions(): array
    {
        return [
            'Asia/Karachi' => 'Asia/Karachi (UTC+05:00)',
            'Asia/Dubai' => 'Asia/Dubai (UTC+04:00)',
            'Asia/Riyadh' => 'Asia/Riyadh (UTC+03:00)',
            'Asia/Kolkata' => 'Asia/Kolkata (UTC+05:30)',
            'Asia/Dhaka' => 'Asia/Dhaka (UTC+06:00)',
            'Asia/Singapore' => 'Asia/Singapore (UTC+08:00)',
            'Europe/London' => 'Europe/London',
            'Europe/Berlin' => 'Europe/Berlin',
            'Europe/Amsterdam' => 'Europe/Amsterdam',
            'America/New_York' => 'America/New_York',
            'America/Chicago' => 'America/Chicago',
            'America/Denver' => 'America/Denver',
            'America/Los_Angeles' => 'America/Los_Angeles',
            'Australia/Sydney' => 'Australia/Sydney',
            'UTC' => 'UTC',
        ];
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
            $userId = session()->get('user_id');
            $this->saveSystemSetting(
                'mobile_api_url',
                $url,
                'Mobile app API server base URL',
                $userId
            );
        } catch (\Throwable $e) {
            log_message('error', 'saveMobileSettings error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save setting.');
        }

        return redirect()->to(base_url('settings') . '#mobile')
            ->with('success', $url ? 'Mobile server URL saved.' : 'Mobile server URL cleared.');
    }

    public function createBackup()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#backups');
        }

        $backupType = strtolower(trim((string) $this->request->getPost('backup_type')));
        $userId = session()->get('user_id');

        try {
            $job = (new SystemBackupService())->createManualBackup($backupType ?: 'full', $userId ? (int) $userId : null);
            return redirect()->to(base_url('settings') . '#backups')
                ->with('success', 'Backup created: ' . ($job['archive_name'] ?? 'archive ready'));
        } catch (\Throwable $e) {
            log_message('error', 'createBackup error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#backups')
                ->with('error', 'Backup creation failed: ' . $e->getMessage());
        }
    }

    public function downloadBackup(string $publicId = null)
    {
        if (!$publicId) {
            return redirect()->to(base_url('settings') . '#backups')->with('error', 'Missing backup reference.');
        }

        $job = (new SystemBackupJobModel())->where('public_id', $publicId)->first();
        if (!$job || empty($job['archive_path']) || !is_file((string) $job['archive_path'])) {
            return redirect()->to(base_url('settings') . '#backups')->with('error', 'Backup archive not found.');
        }

        return $this->response->download((string) $job['archive_path'], null)->setFileName((string) ($job['archive_name'] ?? basename((string) $job['archive_path'])));
    }

    public function verifyBackup(string $publicId = null)
    {
        if (!$publicId) {
            return redirect()->to(base_url('settings') . '#backups')->with('error', 'Missing backup reference.');
        }

        try {
            $job = (new SystemBackupService())->verifyBackup($publicId);
            return redirect()->to(base_url('settings') . '#backups')
                ->with('success', 'Backup verification completed with status: ' . ($job['health_status'] ?? 'unknown'));
        } catch (\Throwable $e) {
            log_message('error', 'verifyBackup error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#backups')
                ->with('error', 'Backup verification failed: ' . $e->getMessage());
        }
    }

    public function restoreBackup(string $publicId = null)
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#backups');
        }
        if (!$publicId) {
            return redirect()->to(base_url('settings') . '#backups')->with('error', 'Missing backup reference.');
        }

        $restoreMode = strtolower(trim((string) $this->request->getPost('restore_mode')));
        $confirmation = trim((string) $this->request->getPost('restore_confirmation'));
        if ($confirmation !== 'RESTORE') {
            return redirect()->to(base_url('settings') . '#backups')
                ->with('error', 'Restore confirmation text must be RESTORE.');
        }

        try {
            $result = (new SystemBackupService())->restoreBackup($publicId, $restoreMode ?: 'db_only', session()->get('user_id') ? (int) session()->get('user_id') : null);
            $message = 'Restore completed.';
            if (!empty($result['safety_backup_public_id'])) {
                $message .= ' Safety backup: ' . $result['safety_backup_public_id'];
            }
            if (!empty($result['application_files_restored'])) {
                $message .= ' Files restored: ' . (int) $result['application_files_restored'] . '.';
            }
            return redirect()->to(base_url('settings') . '#backups')->with('success', $message);
        } catch (\Throwable $e) {
            log_message('error', 'restoreBackup error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#backups')
                ->with('error', 'Backup restore failed: ' . $e->getMessage());
        }
    }

    public function saveBackupSchedule()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#backups');
        }

        $scheduleId = (int) ($this->request->getPost('schedule_id') ?? 0);
        $frequencyType = strtolower(trim((string) $this->request->getPost('frequency_type')));
        $backupType = strtolower(trim((string) $this->request->getPost('backup_type')));
        $timeOfDay = trim((string) $this->request->getPost('time_of_day'));
        $intervalMinutes = (int) ($this->request->getPost('interval_minutes') ?? 0);
        $retentionCount = (int) ($this->request->getPost('retention_count') ?? 5);
        $dayOfWeek = $this->request->getPost('day_of_week');

        if (!$this->validate([
            'name' => 'required|min_length[3]|max_length[100]',
        ])) {
            return redirect()->to(base_url('settings') . '#backups')->withInput()->with('error', 'Schedule name is required.');
        }

        if (!in_array($backupType, ['full', 'db_only', 'code_only'], true)) {
            return redirect()->to(base_url('settings') . '#backups')->withInput()->with('error', 'Invalid backup type.');
        }

        if (!in_array($frequencyType, ['daily', 'weekly', 'interval'], true)) {
            return redirect()->to(base_url('settings') . '#backups')->withInput()->with('error', 'Invalid frequency type.');
        }

        if ($frequencyType === 'interval' && $intervalMinutes < 5) {
            return redirect()->to(base_url('settings') . '#backups')->withInput()->with('error', 'Interval backups must be at least 5 minutes.');
        }

        if ($frequencyType !== 'interval' && !preg_match('/^\d{2}:\d{2}$/', $timeOfDay)) {
            return redirect()->to(base_url('settings') . '#backups')->withInput()->with('error', 'Time of day must use HH:MM format.');
        }

        $userId = session()->get('user_id');
        $model = new SystemBackupScheduleModel();
        $payload = [
            'name' => trim((string) $this->request->getPost('name')),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
            'backup_type' => $backupType,
            'frequency_type' => $frequencyType,
            'interval_minutes' => $frequencyType === 'interval' ? $intervalMinutes : null,
            'day_of_week' => $frequencyType === 'weekly' ? max(0, min(6, (int) $dayOfWeek)) : null,
            'time_of_day' => $frequencyType === 'interval' ? null : $timeOfDay,
            'retention_count' => max(1, $retentionCount),
            'updated_by' => $userId ? (int) $userId : null,
        ];

        try {
            $service = new SystemBackupService();
            $payload['next_run_at'] = $payload['is_active'] ? $service->calculateNextRunAt($payload) : null;

            if ($scheduleId > 0) {
                $model->update($scheduleId, $payload);
            } else {
                $payload['created_by'] = $userId ? (int) $userId : null;
                $model->insert($payload);
            }

            return redirect()->to(base_url('settings') . '#backups')->with('success', 'Backup schedule saved.');
        } catch (\Throwable $e) {
            log_message('error', 'saveBackupSchedule error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#backups')
                ->withInput()
                ->with('error', 'Failed to save backup schedule: ' . $e->getMessage());
        }
    }

    public function saveSyncEnvironment()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#sync');
        }

        if (!$this->validate([
            'name' => 'required|min_length[3]|max_length[80]',
            'app_path' => 'required|max_length[500]',
            'db_host' => 'required|max_length[120]',
            'db_port' => 'required|integer',
            'db_name' => 'required|max_length[120]',
            'db_user' => 'required|max_length[120]',
        ])) {
            return redirect()->to(base_url('settings') . '#sync')->withInput()->with('error', 'Invalid sync environment values.');
        }

        $id = (int) ($this->request->getPost('environment_id') ?? 0);
        $payload = [
            'name' => trim((string) $this->request->getPost('name')),
            'app_path' => trim((string) $this->request->getPost('app_path')),
            'db_host' => trim((string) $this->request->getPost('db_host')),
            'db_port' => (int) $this->request->getPost('db_port'),
            'db_name' => trim((string) $this->request->getPost('db_name')),
            'db_user' => trim((string) $this->request->getPost('db_user')),
            'db_password' => (string) $this->request->getPost('db_password'),
            'is_active' => $this->request->getPost('is_active') ? 1 : 0,
        ];

        try {
            $model = new SystemSyncEnvironmentModel();
            if ($id > 0) {
                $model->update($id, $payload);
            } else {
                $model->insert($payload);
            }
            return redirect()->to(base_url('settings') . '#sync')->with('success', 'Sync environment saved.');
        } catch (\Throwable $e) {
            log_message('error', 'saveSyncEnvironment error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#sync')->withInput()->with('error', 'Failed to save sync environment: ' . $e->getMessage());
        }
    }

    public function runSyncScan()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#sync');
        }

        $sourceId = (int) ($this->request->getPost('source_environment_id') ?? 0);
        $destinationId = (int) ($this->request->getPost('destination_environment_id') ?? 0);
        if ($sourceId <= 0 || $destinationId <= 0 || $sourceId === $destinationId) {
            return redirect()->to(base_url('settings') . '#sync')->withInput()->with('error', 'Select valid and different source/destination environments.');
        }

        try {
            $scan = (new SystemSyncService())->runScan($sourceId, $destinationId, session()->get('user_id') ? (int) session()->get('user_id') : null);
            $summary = json_decode((string) ($scan['summary_json'] ?? ''), true);
            $message = 'Sync scan complete.';
            if (is_array($summary)) {
                $message .= ' Files: ' . (int) ($summary['file_copy_count'] ?? 0) . ', SQL ops: '
                    . ((int) ($summary['table_create_count'] ?? 0) + (int) ($summary['column_add_count'] ?? 0) + (int) ($summary['index_add_count'] ?? 0)) . '.';
            }
            return redirect()->to(base_url('settings') . '#sync')->with('success', $message);
        } catch (\Throwable $e) {
            log_message('error', 'runSyncScan error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#sync')->with('error', 'Sync scan failed: ' . $e->getMessage());
        }
    }

    public function applySyncScan(string $publicId = null)
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#sync');
        }
        if (!$publicId) {
            return redirect()->to(base_url('settings') . '#sync')->with('error', 'Missing sync scan reference.');
        }

        $backupConfirmed = $this->request->getPost('backup_confirmed') ? true : false;
        try {
            $result = (new SystemSyncService())->applyScan($publicId, session()->get('user_id') ? (int) session()->get('user_id') : null, $backupConfirmed);
            return redirect()->to(base_url('settings') . '#sync')
                ->with('success', 'Sync apply completed. Files copied: ' . (int) ($result['file_results']['copied_file_count'] ?? 0)
                    . ', SQL executed: ' . (int) ($result['schema_results']['executed_sql_count'] ?? 0) . '.');
        } catch (\Throwable $e) {
            log_message('error', 'applySyncScan error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#sync')->with('error', 'Sync apply failed: ' . $e->getMessage());
        }
    }

    public function downloadSyncReport(string $publicId = null)
    {
        if (!$publicId) {
            return redirect()->to(base_url('settings') . '#sync')->with('error', 'Missing sync scan reference.');
        }

        $scan = (new SystemSyncScanModel())->where('public_id', $publicId)->first();
        if (!$scan || empty($scan['report_path']) || !is_file((string) $scan['report_path'])) {
            return redirect()->to(base_url('settings') . '#sync')->with('error', 'Sync report file not found.');
        }

        return $this->response->download((string) $scan['report_path'], null)
            ->setFileName('sync_report_' . $publicId . '.json');
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
            $userId = session()->get('user_id');
            $this->saveSystemSetting(
                'global_date_format',
                $format,
                'Global UI date format',
                $userId
            );
        } catch (\Throwable $e) {
            log_message('error', 'saveDateFormat error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to save date format setting.');
        }

        return redirect()->to(base_url('settings') . '#security')
            ->with('success', 'Global date format updated.');
    }

    public function saveProductAssetUploadSettings()
    {
        if (!$this->request->is('post')) {
            return redirect()->to(base_url('settings') . '#company');
        }

        $rawMb = (int) ($this->request->getPost('product_assets_raw_max_mb') ?? 0);
        $finalMb = (int) ($this->request->getPost('product_assets_final_max_mb') ?? 0);
        $channelMb = (int) ($this->request->getPost('product_assets_channel_max_mb') ?? 0);

        if ($rawMb <= 0 || $finalMb <= 0 || $channelMb <= 0) {
            return redirect()->to(base_url('settings') . '#company')
                ->withInput()
                ->with('error', 'Product asset upload limits must be positive numbers.');
        }

        $userId = session()->get('user_id');

        try {
            $items = [
                'product_assets_raw_max_mb' => [
                    'value' => (string) $rawMb,
                    'description' => 'Global max upload size (MB) for product asset raw images',
                ],
                'product_assets_final_max_mb' => [
                    'value' => (string) $finalMb,
                    'description' => 'Global max upload size (MB) for product asset final files',
                ],
                'product_assets_channel_max_mb' => [
                    'value' => (string) $channelMb,
                    'description' => 'Global max upload request size (MB) for product asset channel uploads',
                ],
            ];

            foreach ($items as $key => $item) {
                $this->saveSystemSetting(
                    $key,
                    (string) $item['value'],
                    (string) $item['description'],
                    $userId
                );
            }
        } catch (\Throwable $e) {
            log_message('error', 'saveProductAssetUploadSettings error: ' . $e->getMessage());
            return redirect()->to(base_url('settings') . '#company')
                ->withInput()
                ->with('error', 'Failed to save product asset upload settings.');
        }

        return redirect()->to(base_url('settings') . '#company')
            ->with('success', 'Product asset upload limits updated.');
    }

    /**
     * Save/update a system setting across environments where `system_settings.id`
     * may be a plain PK without AUTO_INCREMENT.
     */
    private function saveSystemSetting(string $key, string $value, string $description, ?int $userId): void
    {
        $table = $this->db->table('system_settings');
        $existing = $table->where('setting_key', $key)->get()->getRowArray();

        if ($existing) {
            $table->where('setting_key', $key)->update([
                'setting_value' => $value,
                'description' => $description,
                'updated_by' => $userId,
            ]);
            return;
        }

        $insert = [
            'setting_key' => $key,
            'setting_value' => $value,
            'description' => $description,
            'updated_by' => $userId,
        ];

        $fields = $this->db->getFieldNames('system_settings');
        if (in_array('id', $fields, true)) {
            $maxRow = $this->db->table('system_settings')
                ->select('MAX(id) as max_id')
                ->get()->getRowArray();
            $insert['id'] = ((int) ($maxRow['max_id'] ?? 0)) + 1;
        }

        $table->insert($insert);
    }
}
