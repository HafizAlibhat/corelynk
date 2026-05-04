<?php

namespace App\Controllers;

use App\Models\CustomerModel;

class Customers extends BaseController
{
    protected CustomerModel $customerModel;

    public function __construct()
    {
        $this->customerModel = new CustomerModel();
    }

    /**
     * Validate and normalize optional contact fields.
     */
    private function validateAndNormalizeContactFields(array $input): array
    {
        $email = trim((string)($input['email'] ?? ''));
        $phone = trim((string)($input['phone'] ?? ''));
        $mobile = trim((string)($input['mobile'] ?? ''));
        $website = trim((string)($input['website'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Primary Email must be a valid email address.'];
        }

        $phonePattern = '/^[0-9+\-\s().]{3,30}$/';
        if ($phone !== '' && !preg_match($phonePattern, $phone)) {
            return ['ok' => false, 'message' => 'Phone format is invalid.'];
        }
        if ($mobile !== '' && !preg_match($phonePattern, $mobile)) {
            return ['ok' => false, 'message' => 'Mobile format is invalid.'];
        }

        if ($website !== '') {
            $candidate = $website;
            if (!preg_match('#^https?://#i', $candidate)) {
                $candidate = 'https://' . $candidate;
            }
            if (!filter_var($candidate, FILTER_VALIDATE_URL)) {
                return ['ok' => false, 'message' => 'Website URL is invalid.'];
            }
            $website = $candidate;
        }

        return [
            'ok' => true,
            'data' => [
                'email' => $email !== '' ? $email : null,
                'phone' => $phone !== '' ? $phone : null,
                'mobile' => $mobile !== '' ? $mobile : null,
                'website' => $website !== '' ? $website : null,
            ],
        ];
    }

    /**
     * Resolve customer by public_id or numeric id. Throws 404 if not found.
     */
    private function resolveCustomerOrFail($identifier): array
    {
        $cust = $this->customerModel->findByPublicIdOrId($identifier);
        if (!$cust) {
            throw new \CodeIgniter\Exceptions\PageNotFoundException('Customer not found');
        }
        return $cust;
    }

    /**
     * Redirect to canonical public_id URL if feature is enabled and identifier is not already the public_id.
     */
    private function redirectToCanonicalCustomerUrl(array $cust, $identifier, string $suffix = '')
    {
        $publicId = trim((string)($cust['public_id'] ?? ''));
        if (!featureEnabled('enable_public_ids') || $publicId === '' || (string)$identifier === $publicId) {
            return null;
        }
        return redirect()->to(site_url('customers/' . urlencode($publicId) . $suffix));
    }

    public function index()
    {
        $page = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 25);
        if (!in_array($perPage, [25, 50, 100], true)) { $perPage = 25; }

        $status = strtolower(trim((string)($this->request->getGet('status') ?? 'active')));
        if (!in_array($status, ['active', 'inactive', 'all'], true)) {
            $status = 'active';
        }

        $search = trim((string)($this->request->getGet('search') ?? ''));

        // Build base query with optional status + DB search filter
        $builder = $this->customerModel->orderBy('name', 'ASC');
        if ($status !== 'all') {
            $builder = $builder->where('status', $status);
        }

        if ($search !== '') {
            $builder = $builder->groupStart()
                ->like('customer_code', $search)
                ->orLike('name', $search)
                ->orLike('company_name', $search)
                ->groupEnd();
        }

        $customers = $builder->paginate($perPage, 'default', $page);
        $pager = $this->customerModel->pager;

        $data['customers'] = $customers;
        $data['pager'] = $pager;
        $data['status'] = $status;
        $data['current_search'] = $search;
        $data['total_customers'] = (int) $pager->getTotal('default');
        $data['per_page'] = $perPage;

        return view('customers/index', $data);
    }

    public function create()
    {
        $method = strtolower($this->request->getMethod());
        log_message('debug', 'Customers::create called. Method=' . $method);

        if ($method === 'post') {
            log_message('debug', 'Customers::create POST payload: ' . json_encode($this->request->getPost()));
            log_message('debug', 'Customers::create CSRF token: ' . ($this->request->getPost(csrf_token()) ?? 'MISSING'));

            $submitToken = (string)($this->request->getPost('_form_submit_token') ?? '');
            if (!consumeFormSubmissionToken('customer_create', $submitToken)) {
                session()->setFlashdata('error', 'This form was already submitted. Please try once from a fresh form.');
                return redirect()->back()->withInput();
            }

            $name = trim($this->request->getPost('name') ?? '');
            if ($name === '') {
                log_message('error', 'Customers::create validation failed: name is empty');
                session()->setFlashdata('error', 'Name is required.');
                return redirect()->back()->withInput();
            }

            $contactValidation = $this->validateAndNormalizeContactFields($this->request->getPost() ?? []);
            if (!$contactValidation['ok']) {
                session()->setFlashdata('error', $contactValidation['message']);
                return redirect()->back()->withInput();
            }
            $contactData = $contactValidation['data'];

            $payload = [
                'customer_code' => $this->customerModel->generateCustomerCode(),
                'name' => $name,
                'company_name' => $this->request->getPost('company_name'),
                'type' => $this->request->getPost('type') ?? 'retail',
                'status' => $this->request->getPost('status') ?? 'active',
                'created_by' => session()->get('user_id') ?? null,
                // top-level contact fields (preferred for import)
                'email' => $contactData['email'],
                'phone' => $contactData['phone'],
                'mobile' => $contactData['mobile'],
                'website' => $contactData['website'],
                'odoo_id' => $this->request->getPost('odoo_id') ?: null,
            ];

            // Keep legacy number and any other misc fields in metadata
            $meta = [];
            foreach (['legacy_number'] as $field) {
                $val = $this->request->getPost($field);
                if (!empty($val)) {
                    $meta[$field] = $val;
                }
            }
            $payload['metadata'] = !empty($meta) ? json_encode($meta) : null;

            $insertId = $this->customerModel->insert($payload);
            if (!$insertId) {
                $errs = $this->customerModel->errors();
                $dbErr = $this->customerModel->db->error();
                $msg = !empty($errs)
                    ? (is_array($errs) ? implode('; ', $errs) : (string) $errs)
                    : (!empty($dbErr['message']) ? $dbErr['message'] : 'Failed to create customer.');
                log_message('error', 'Customers::create insert failed. Errors=' . json_encode($errs) . ' DBErr=' . json_encode($dbErr));
                session()->setFlashdata('error', $msg);
                return redirect()->back()->withInput();
            }

            // Insert multiple addresses
            $addressesPost = $this->request->getPost('addresses') ?? [];
            $defaultIdx = (string)($this->request->getPost('default_address_idx') ?? '0');
            if (is_array($addressesPost) && !empty($addressesPost)) {
                $db = \Config\Database::connect();
                foreach ($addressesPost as $idx => $addr) {
                    $hasContent = !empty($addr['line1']) || !empty($addr['line2']) || !empty($addr['country_id'])
                               || !empty($addr['postal_code']) || !empty($addr['city_id']) || !empty($addr['state_id']);
                    if (!$hasContent) continue;
                    $isDefault = ((string)$idx === $defaultIdx) ? 1 : 0;
                    $countryId = !empty($addr['country_id']) ? (int)$addr['country_id'] : null;
                    $stateId   = !empty($addr['state_id'])   ? (int)$addr['state_id']   : null;
                    $cityId    = !empty($addr['city_id'])    ? (int)$addr['city_id']    : null;
                    $countryName = $stateName = $cityName = null;
                    if ($countryId) {
                        $r = $db->table('countries')->select('name')->where('id', $countryId)->get()->getRowArray();
                        $countryName = $r['name'] ?? null;
                    }
                    if ($stateId) {
                        $r = $db->table('states')->select('name')->where('id', $stateId)->get()->getRowArray();
                        $stateName = $r['name'] ?? null;
                    }
                    if ($cityId) {
                        $r = $db->table('cities')->select('name')->where('id', $cityId)->get()->getRowArray();
                        $cityName = $r['name'] ?? null;
                    }
                    $db->table('customer_addresses')->insert([
                        'customer_id' => $insertId,
                        'label'       => !empty($addr['label']) ? $addr['label'] : null,
                        'line1'       => !empty($addr['line1']) ? $addr['line1'] : null,
                        'line2'       => !empty($addr['line2']) ? $addr['line2'] : null,
                        'country_id'  => $countryId,
                        'state_id'    => $stateId,
                        'city_id'     => $cityId,
                        'country_name'=> $countryName,
                        'state_name'  => $stateName,
                        'city_name'   => $cityName,
                        'postal_code' => !empty($addr['postal_code']) ? $addr['postal_code'] : null,
                        'is_billing'  => !empty($addr['is_billing'])  ? 1 : 0,
                        'is_shipping' => !empty($addr['is_shipping']) ? 1 : 0,
                        'is_default'  => $isDefault,
                        'created_at'  => date('Y-m-d H:i:s'),
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ]);
                }
            }

            session()->setFlashdata('success', 'Customer created successfully.');
            return redirect()->to(site_url('customers'));
        }

        // Provide countries list and next customer code for the form (GET)
        try {
            $db = \Config\Database::connect();
            $countries = $db->table('countries')->select('id,name')->orderBy('name')->get()->getResultArray();
        } catch (\Throwable $e) {
            $countries = [];
        }
        return view('customers/form', [
            'countries' => $countries,
            'form_submit_token' => issueFormSubmissionToken('customer_create'),
            'next_customer_code' => $this->customerModel->peekNextCustomerCode(),
        ]);
    }

    public function edit($id)
    {
        $cust = $this->customerModel->findByPublicIdOrId($id);
        if (!$cust) {
            return redirect()->to(site_url('customers'))->with('error', 'Customer not found');
        }
        $numericId = (int)$cust['id'];

        $method = strtolower($this->request->getMethod());
        // When showing the edit form (GET), load all addresses from customer_addresses
        if ($method !== 'post') {
            try {
                $db = \Config\Database::connect();
                $addresses = $db->table('customer_addresses')
                    ->where('customer_id', $numericId)
                    ->orderBy('is_default', 'DESC')
                    ->orderBy('id', 'ASC')
                    ->get()->getResultArray();
                $cust['__addresses'] = $addresses;
            } catch (\Throwable $e) {
                $cust['__addresses'] = [];
            }
            // provide countries list for the form
            try {
                $countries = $db->table('countries')->select('id,name')->orderBy('name')->get()->getResultArray();
            } catch (\Throwable $e) {
                $countries = [];
            }
            $cust['__countries'] = $countries;
        }
        if ($method === 'post') {
            $post = $this->request->getPost();

            $contactValidation = $this->validateAndNormalizeContactFields($post ?? []);
            if (!$contactValidation['ok']) {
                session()->setFlashdata('error', $contactValidation['message']);
                return redirect()->back()->withInput();
            }
            $contactData = $contactValidation['data'];

            $update = [
                'name' => trim($post['name'] ?? $cust['name']),
                'company_name' => $post['company_name'] ?? $cust['company_name'],
                'type' => $post['type'] ?? $cust['type'],
                'status' => $post['status'] ?? $cust['status'],
                // top-level contact fields
                'email' => $contactData['email'],
                'phone' => $contactData['phone'],
                'mobile' => $contactData['mobile'],
                'website' => $contactData['website'],
                'odoo_id' => $post['odoo_id'] ?? $cust['odoo_id'] ?? null,
            ];

            $meta = [];
            foreach (['legacy_number'] as $field) {
                $val = $post[$field] ?? null;
                if (!empty($val)) {
                    $meta[$field] = $val;
                }
            }
            $update['metadata'] = !empty($meta) ? json_encode($meta) : null;

            if (!$this->customerModel->update($numericId, $update)) {
                $errs = $this->customerModel->errors();
                $msg = !empty($errs)
                    ? (is_array($errs) ? implode('; ', $errs) : (string) $errs)
                    : 'Failed to update customer.';
                session()->setFlashdata('error', $msg);
                return redirect()->back()->withInput();
            }

            // Upsert multiple addresses
            $addressesPost = $post['addresses'] ?? [];
            $defaultIdx = (string)($post['default_address_idx'] ?? '0');
            $db = \Config\Database::connect();
            $submittedAddrIds = [];
            if (is_array($addressesPost)) {
                foreach ($addressesPost as $idx => $addr) {
                    $hasContent = !empty($addr['line1']) || !empty($addr['line2']) || !empty($addr['country_id'])
                               || !empty($addr['postal_code']) || !empty($addr['city_id']) || !empty($addr['state_id']);
                    $existingId = !empty($addr['id']) ? (int)$addr['id'] : null;
                    if (!$hasContent && !$existingId) continue;
                    $isDefault = ((string)$idx === $defaultIdx) ? 1 : 0;
                    $countryId = !empty($addr['country_id']) ? (int)$addr['country_id'] : null;
                    $stateId   = !empty($addr['state_id'])   ? (int)$addr['state_id']   : null;
                    $cityId    = !empty($addr['city_id'])    ? (int)$addr['city_id']    : null;
                    $countryName = $stateName = $cityName = null;
                    if ($countryId) {
                        $r = $db->table('countries')->select('name')->where('id', $countryId)->get()->getRowArray();
                        $countryName = $r['name'] ?? null;
                    }
                    if ($stateId) {
                        $r = $db->table('states')->select('name')->where('id', $stateId)->get()->getRowArray();
                        $stateName = $r['name'] ?? null;
                    }
                    if ($cityId) {
                        $r = $db->table('cities')->select('name')->where('id', $cityId)->get()->getRowArray();
                        $cityName = $r['name'] ?? null;
                    }
                    $addrData = [
                        'label'       => !empty($addr['label']) ? $addr['label'] : null,
                        'line1'       => !empty($addr['line1']) ? $addr['line1'] : null,
                        'line2'       => !empty($addr['line2']) ? $addr['line2'] : null,
                        'country_id'  => $countryId,
                        'state_id'    => $stateId,
                        'city_id'     => $cityId,
                        'country_name'=> $countryName,
                        'state_name'  => $stateName,
                        'city_name'   => $cityName,
                        'postal_code' => !empty($addr['postal_code']) ? $addr['postal_code'] : null,
                        'is_billing'  => !empty($addr['is_billing'])  ? 1 : 0,
                        'is_shipping' => !empty($addr['is_shipping']) ? 1 : 0,
                        'is_default'  => $isDefault,
                        'updated_at'  => date('Y-m-d H:i:s'),
                    ];
                    if ($existingId) {
                        $db->table('customer_addresses')
                           ->where('id', $existingId)
                           ->where('customer_id', $numericId)
                           ->update($addrData);
                        $submittedAddrIds[] = $existingId;
                    } else {
                        $addrData['customer_id'] = $numericId;
                        $addrData['created_at']  = date('Y-m-d H:i:s');
                        $db->table('customer_addresses')->insert($addrData);
                        $submittedAddrIds[] = $db->insertID();
                    }
                }
            }
            // Delete explicitly removed addresses
            $deletedIds = array_filter(array_map('intval', (array)($post['deleted_address_ids'] ?? [])));
            if (!empty($deletedIds)) {
                $db->table('customer_addresses')
                   ->where('customer_id', $numericId)
                   ->whereIn('id', $deletedIds)
                   ->delete();
            }

            return redirect()->to(site_url('customers'))->with('success', 'Customer updated');
        }

        return view('customers/form', ['customer' => $cust, 'countries' => $cust['__countries'] ?? []]);
    }

    /**
     * AJAX: return countries list optionally filtered by q
     */
    public function countries()
    {
        $q = $this->request->getGet('q');
        $db = \Config\Database::connect();
        $builder = $db->table('countries')->select('id,name');
        if (!empty($q)) {
            $builder->like('name', $q);
        }
        $rows = $builder->orderBy('name')->limit(200)->get()->getResultArray();
        return $this->response->setJSON($rows);
    }

    /**
     * AJAX: return states for a country id (country_id via param or GET)
     */
    public function states($countryId = null)
    {
        $countryId = $countryId ?? $this->request->getGet('country_id');
        $q = $this->request->getGet('q');
        $db = \Config\Database::connect();
        // If countryId provided, try to fetch states for all country rows that share the same canonical name.
        if (!empty($countryId)) {
            // fetch country name
            $c = $db->table('countries')->select('name')->where('id', (int)$countryId)->get()->getRowArray();
            if (!empty($c['name'])) {
                // find all country ids with same name
                $alt = $db->table('countries')->select('id')->where('name', $c['name'])->get()->getResultArray();
                $ids = array_map(function($r){ return (int)$r['id']; }, $alt);
                if (empty($ids)) $ids = [(int)$countryId];
                $builder = $db->table('states')->select('id,name')->whereIn('country_id', $ids)->orderBy('name');
                if (!empty($q)) $builder->like('name', $q);
                $rows = $builder->limit(5000)->get()->getResultArray();
            } else {
                // fallback to simple by id
                $builder = $db->table('states')->select('id,name')->where('country_id', (int)$countryId)->orderBy('name');
                if (!empty($q)) $builder->like('name', $q);
                $rows = $builder->limit(500)->get()->getResultArray();
            }
        } else {
            // no country specified: return generic search by q
            $builder = $db->table('states')->select('id,name')->orderBy('name');
            if (!empty($q)) $builder->like('name', $q);
            $rows = $builder->limit(200)->get()->getResultArray();
        }

        // remove duplicate state names (some imports may have duplicates across country rows)
        $seen = [];
        $uniq = [];
        foreach ($rows as $r) {
            $key = strtolower(trim($r['name']));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $uniq[] = $r;
        }

        return $this->response->setJSON($uniq);
    }

    /**
     * AJAX: search by ZIP or city name, return matching city/state/country rows
     */
    public function zipSearch()
    {
        $q = trim($this->request->getGet('q'));
        if ($q === '') return $this->response->setJSON([]);

        $db = \Config\Database::connect();
        $builder = $db->table('cities AS c')
            ->select('c.id AS city_id, c.name AS city, s.id AS state_id, s.name AS state, co.id AS country_id, co.name AS country')
            ->join('states AS s', 's.id = c.state_id', 'left')
            ->join('countries AS co', 'co.id = s.country_id', 'left')
            ->like('c.name', $q)
            ->orderBy('co.name')->orderBy('s.name')->orderBy('c.name')
            ->limit(50);

        $rows = $builder->get()->getResultArray();
        return $this->response->setJSON($rows);
    }

    /**
     * AJAX: return cities for a state id (state_id via param or GET)
     */
    public function cities($stateId = null)
    {
        $stateId = $stateId ?? $this->request->getGet('state_id');
        $q = $this->request->getGet('q');
        $db = \Config\Database::connect();
        $builder = $db->table('cities')->select('id,name')->orderBy('name');
        if (!empty($stateId)) {
            $builder->where('state_id', (int) $stateId);
        }
        if (!empty($q)) {
            $builder->like('name', $q);
        }
        $rows = $builder->limit(2000)->get()->getResultArray();

        // If we returned very few cities for a state id, it's possible the import created
        // multiple state rows with the same name (different country rows). Try a fallback
        // that finds other states with the same name and returns cities from all of them.
        if (!empty($stateId) && (empty($rows) || count($rows) < 5)) {
            // get state name for this id
            $s = $db->table('states')->select('name')->where('id', (int)$stateId)->get()->getRowArray();
            if (!empty($s['name'])) {
                $alt = $db->table('states')->select('id')->where('name', $s['name'])->get()->getResultArray();
                $ids = array_map(function($r){ return (int)$r['id']; }, $alt);
                if (!empty($ids)) {
                    $builder2 = $db->table('cities')->select('id,name')->whereIn('state_id', $ids)->orderBy('name');
                    if (!empty($q)) $builder2->like('name', $q);
                    $rows2 = $builder2->limit(5000)->get()->getResultArray();
                    if (!empty($rows2)) {
                        // dedupe by city name
                        $seen = [];
                        $uniq = [];
                        foreach ($rows2 as $r) {
                            $k = strtolower(trim($r['name']));
                            if (isset($seen[$k])) continue;
                            $seen[$k] = true;
                            $uniq[] = $r;
                        }
                        $rows = $uniq;
                    }
                }
            }
        }

        return $this->response->setJSON($rows);
    }

    public function delete($id)
    {
        $cust = $this->customerModel->findByPublicIdOrId($id);
        if (!$cust) {
            return redirect()->to(site_url('customers'))->with('error', 'Customer not found');
        }
        $numericId = (int)$cust['id'];
        $this->customerModel->update($numericId, ['status' => 'inactive']);
        return redirect()->to(site_url('customers'))->with('success', 'Customer deactivated');
    }

    public function toggleStatus($id)
    {
        $cust = $this->customerModel->findByPublicIdOrId($id);
        if (!$cust) {
            return redirect()->to(site_url('customers'))->with('error', 'Customer not found');
        }
        $numericId = (int)$cust['id'];
        $newStatus = ($cust['status'] ?? 'active') === 'active' ? 'inactive' : 'active';
        $this->customerModel->update($numericId, ['status' => $newStatus]);
        return redirect()->to(site_url('customers'))->with('success', 'Customer ' . $newStatus);
    }

    public function show($id)
    {
        $cust = $this->customerModel->findByPublicIdOrId($id);
        if (!$cust) {
            return redirect()->to(site_url('customers'))->with('error', 'Customer not found');
        }
        if ($redirect = $this->redirectToCanonicalCustomerUrl($cust, $id)) return $redirect;
        $numericId = (int)$cust['id'];

        // Try to load contacts/addresses if models exist; otherwise query the tables directly
        $contacts = [];
        $addresses = [];
        if (class_exists('\App\Models\CustomerContactModel')) {
            $contactModel = new \App\Models\CustomerContactModel();
            $contacts = $contactModel->where('customer_id', $numericId)->findAll();
        }
        if (class_exists('\App\Models\CustomerAddressModel')) {
            $addressModel = new \App\Models\CustomerAddressModel();
            $addresses = $addressModel->where('customer_id', $numericId)->findAll();
        } else {
            // No address model present; fetch from customer_addresses table directly
            $db = \Config\Database::connect();
            $addresses = $db->table('customer_addresses')->where('customer_id', $numericId)->get()->getResultArray();
        }

        $db = \Config\Database::connect();

        // Owner-level business analytics for this customer
        $fromDateInput = trim((string)($this->request->getGet('from') ?? ''));
        $toDateInput = trim((string)($this->request->getGet('to') ?? ''));
        $dateFilterError = null;
        $customFrom = null;
        $customTo = null;
        if ($fromDateInput !== '' || $toDateInput !== '') {
            $isValidFrom = $fromDateInput !== ''
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDateInput)
                && strtotime($fromDateInput) !== false;
            $isValidTo = $toDateInput !== ''
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDateInput)
                && strtotime($toDateInput) !== false;
            if (!$isValidFrom || !$isValidTo) {
                $dateFilterError = 'Custom date range is invalid. Use format YYYY-MM-DD.';
            } elseif ($fromDateInput > $toDateInput) {
                $dateFilterError = 'Custom date range is invalid. From date must be before To date.';
            } else {
                $customFrom = $fromDateInput;
                $customTo = $toDateInput;
            }
        }

        $analytics = [
            'lifetime' => [
                'order_count' => 0,
                'revenue' => 0.0,
                'avg_order_value' => 0.0,
                'units_bought' => 0.0,
                'unique_products' => 0,
                'first_order_date' => null,
                'last_order_date' => null,
            ],
            'yearly' => ['order_count' => 0, 'revenue' => 0.0, 'units_bought' => 0.0],
            'monthly' => ['order_count' => 0, 'revenue' => 0.0, 'units_bought' => 0.0],
            'custom' => [
                'order_count' => 0,
                'revenue' => 0.0,
                'units_bought' => 0.0,
                'from' => $customFrom,
                'to' => $customTo,
                'enabled' => ($customFrom !== null && $customTo !== null),
            ],
            'top_products' => [],
            'monthly_trend' => [],
            'yearly_trend' => [],
            'date_error' => $dateFilterError,
        ];

        try {
            if ($db->tableExists('sales_orders')) {
                $currentYear = (int)date('Y');
                $currentMonth = (int)date('n');

                $life = $db->query(
                    'SELECT COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue, COALESCE(AVG(total),0) AS avg_order_value, '
                    . 'MIN(order_date) AS first_order_date, MAX(order_date) AS last_order_date '
                    . 'FROM sales_orders WHERE customer_id = ?',
                    [$numericId]
                )->getRowArray() ?? [];
                $analytics['lifetime']['order_count'] = (int)($life['order_count'] ?? 0);
                $analytics['lifetime']['revenue'] = (float)($life['revenue'] ?? 0);
                $analytics['lifetime']['avg_order_value'] = (float)($life['avg_order_value'] ?? 0);
                $analytics['lifetime']['first_order_date'] = $life['first_order_date'] ?? null;
                $analytics['lifetime']['last_order_date'] = $life['last_order_date'] ?? null;

                $yr = $db->query(
                    'SELECT COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue '
                    . 'FROM sales_orders WHERE customer_id = ? AND YEAR(order_date) = ?',
                    [$numericId, $currentYear]
                )->getRowArray() ?? [];
                $analytics['yearly']['order_count'] = (int)($yr['order_count'] ?? 0);
                $analytics['yearly']['revenue'] = (float)($yr['revenue'] ?? 0);

                $mo = $db->query(
                    'SELECT COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue '
                    . 'FROM sales_orders WHERE customer_id = ? AND YEAR(order_date) = ? AND MONTH(order_date) = ?',
                    [$numericId, $currentYear, $currentMonth]
                )->getRowArray() ?? [];
                $analytics['monthly']['order_count'] = (int)($mo['order_count'] ?? 0);
                $analytics['monthly']['revenue'] = (float)($mo['revenue'] ?? 0);

                if ($customFrom !== null && $customTo !== null) {
                    $cr = $db->query(
                        'SELECT COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue '
                        . 'FROM sales_orders WHERE customer_id = ? AND order_date BETWEEN ? AND ?',
                        [$numericId, $customFrom, $customTo]
                    )->getRowArray() ?? [];
                    $analytics['custom']['order_count'] = (int)($cr['order_count'] ?? 0);
                    $analytics['custom']['revenue'] = (float)($cr['revenue'] ?? 0);
                }

                if ($db->tableExists('sales_order_lines')) {
                    $lineLife = $db->query(
                        'SELECT COALESCE(SUM(sol.quantity),0) AS units_bought, '
                        . 'COUNT(DISTINCT COALESCE(sol.product_id, 0), COALESCE(sol.product_variant_id, 0), COALESCE(sol.description, "")) AS unique_products '
                        . 'FROM sales_order_lines sol '
                        . 'INNER JOIN sales_orders so ON so.id = sol.sales_order_id '
                        . 'WHERE so.customer_id = ?',
                        [$numericId]
                    )->getRowArray() ?? [];
                    $analytics['lifetime']['units_bought'] = (float)($lineLife['units_bought'] ?? 0);
                    $analytics['lifetime']['unique_products'] = (int)($lineLife['unique_products'] ?? 0);

                    $lineYr = $db->query(
                        'SELECT COALESCE(SUM(sol.quantity),0) AS units_bought '
                        . 'FROM sales_order_lines sol '
                        . 'INNER JOIN sales_orders so ON so.id = sol.sales_order_id '
                        . 'WHERE so.customer_id = ? AND YEAR(so.order_date) = ?',
                        [$numericId, $currentYear]
                    )->getRowArray() ?? [];
                    $analytics['yearly']['units_bought'] = (float)($lineYr['units_bought'] ?? 0);

                    $lineMo = $db->query(
                        'SELECT COALESCE(SUM(sol.quantity),0) AS units_bought '
                        . 'FROM sales_order_lines sol '
                        . 'INNER JOIN sales_orders so ON so.id = sol.sales_order_id '
                        . 'WHERE so.customer_id = ? AND YEAR(so.order_date) = ? AND MONTH(so.order_date) = ?',
                        [$numericId, $currentYear, $currentMonth]
                    )->getRowArray() ?? [];
                    $analytics['monthly']['units_bought'] = (float)($lineMo['units_bought'] ?? 0);

                    if ($customFrom !== null && $customTo !== null) {
                        $lineCr = $db->query(
                            'SELECT COALESCE(SUM(sol.quantity),0) AS units_bought '
                            . 'FROM sales_order_lines sol '
                            . 'INNER JOIN sales_orders so ON so.id = sol.sales_order_id '
                            . 'WHERE so.customer_id = ? AND so.order_date BETWEEN ? AND ?',
                            [$numericId, $customFrom, $customTo]
                        )->getRowArray() ?? [];
                        $analytics['custom']['units_bought'] = (float)($lineCr['units_bought'] ?? 0);
                    }

                    $analytics['top_products'] = $db->query(
                        'SELECT COALESCE(NULLIF(TRIM(sol.description), ""), CONCAT("Product #", COALESCE(sol.product_id,0))) AS product_name, '
                        . 'COALESCE(SUM(sol.quantity),0) AS total_qty, COALESCE(SUM(sol.line_total),0) AS total_sales, '
                        . 'COUNT(DISTINCT so.id) AS order_count '
                        . 'FROM sales_order_lines sol '
                        . 'INNER JOIN sales_orders so ON so.id = sol.sales_order_id '
                        . 'WHERE so.customer_id = ? '
                        . 'GROUP BY product_name '
                        . 'ORDER BY total_qty DESC, total_sales DESC '
                        . 'LIMIT 10',
                        [$numericId]
                    )->getResultArray();
                }

                $analytics['monthly_trend'] = $db->query(
                    'SELECT DATE_FORMAT(order_date, "%Y-%m") AS period, COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue '
                    . 'FROM sales_orders '
                    . 'WHERE customer_id = ? AND order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) '
                    . 'GROUP BY DATE_FORMAT(order_date, "%Y-%m") '
                    . 'ORDER BY period ASC',
                    [$numericId]
                )->getResultArray();

                $analytics['yearly_trend'] = $db->query(
                    'SELECT YEAR(order_date) AS period, COUNT(*) AS order_count, COALESCE(SUM(total),0) AS revenue '
                    . 'FROM sales_orders '
                    . 'WHERE customer_id = ? '
                    . 'GROUP BY YEAR(order_date) '
                    . 'ORDER BY period DESC '
                    . 'LIMIT 8',
                    [$numericId]
                )->getResultArray();
            }
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show analytics calculation failed: ' . $e->getMessage());
        }
        
        // Use the new CustomerReceivablesHelper for reliable receivables calculation
        try {
            $receivablesData = \App\Helpers\CustomerReceivablesHelper::getUnpaidInvoices($numericId, $db, false);
            $unpaidInvoices = $receivablesData['unpaid'];
            $orderReceivables = $receivablesData['order_receivables'];
            
            $receivableSummary = [
                'open_invoice_count' => $receivablesData['count'],
                'total_receivable' => $receivablesData['total'],
                'posted_payments_total' => 0.0,
                'draft_payments_total' => 0.0,
                'advance_balance' => 0.0,
            ];
            
            // Add payment totals and advance balance
            $paymentData = \App\Helpers\CustomerReceivablesHelper::recalculatePendingAmount($numericId, $db);
            $receivableSummary['posted_payments_total'] = $paymentData['posted_payments'];
            $receivableSummary['draft_payments_total'] = $paymentData['draft_payments'];
            $receivableSummary['advance_balance'] = $paymentData['advance_balance'];
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show receivables calculation failed: ' . $e->getMessage());
            $unpaidInvoices = [];
            $orderReceivables = [];
            $receivableSummary = [
                'open_invoice_count' => 0,
                'total_receivable' => 0.0,
                'posted_payments_total' => 0.0,
                'draft_payments_total' => 0.0,
                'advance_balance' => 0.0,
            ];
        }
        
        // Fetch payment history
        $paymentHistory = [];
        try {
            if ($db->tableExists('customer_payments')) {
                $payCols = $db->getFieldNames('customer_payments');
                $statusExpr = in_array('status', $payCols, true) ? 'cp.status' : "'draft' AS status";
                $methodExpr = in_array('payment_method', $payCols, true)
                    ? 'cp.payment_method'
                    : (in_array('payment_method_id', $payCols, true) ? "CONCAT('method#', cp.payment_method_id)" : "''");

                $allocExpr = 'COALESCE(cpa.amount, cpa.amount_allocated, cpa.allocated_amount, 0)';
                $paymentHistory = $db->query(
                    'SELECT cp.id, cp.payment_date, cp.amount, '
                    . $statusExpr . ', '
                    . $methodExpr . ' AS payment_method, '
                    . 'cp.memo, cp.notes, cp.posted_entry_id, '
                    . '(SELECT COALESCE(SUM(' . $allocExpr . '),0) FROM customer_payment_allocations cpa WHERE cpa.payment_id = cp.id) AS allocated_amount '
                    . 'FROM customer_payments cp '
                    . 'WHERE cp.customer_id = ? '
                    . 'ORDER BY cp.payment_date DESC, cp.id DESC '
                    . 'LIMIT 50',
                    [(int)$numericId]
                )->getResultArray();
            }
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show payment history failed: ' . $e->getMessage());
            $paymentHistory = [];
        }

        usort($orderReceivables, static function ($a, $b) {
            return ($b['pending_amount'] <=> $a['pending_amount']);
        });

        return view('customers/show', [
            'customer' => $cust,
            'contacts' => $contacts,
            'addresses' => $addresses,
            'unpaidInvoices' => $unpaidInvoices,
            'paymentHistory' => $paymentHistory,
            'orderReceivables' => $orderReceivables,
            'receivableSummary' => $receivableSummary,
            'analytics' => $analytics,
        ]);
    }

    /**
     * DIAGNOSTIC ENDPOINT: Debug why pending invoices aren't showing for a customer
     * Usage: GET /customers/diagnostic/{id}
     */
    public function diagnostic($id)
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(400)
                ->setJSON(['error' => 'AJAX request required']);
        }
        
        $customerId = (int)$id;
        $db = \Config\Database::connect();
        $diagnostics = [
            'customer_id' => $customerId,
            'timestamp' => date('Y-m-d H:i:s'),
            'debug_checks' => [],
            'table_structure' => [],
            'invoices_raw' => [],
            'invoices_with_calcs' => [],
            'payments' => [],
            'recommendations' => [],
            'error' => null,
        ];
        
        try {
            // Check 1: Customer exists
            $cust = $this->customerModel->find($customerId);
            $diagnostics['debug_checks']['customer_exists'] = !empty($cust);
            $diagnostics['debug_checks']['customer_code'] = $cust['customer_code'] ?? null;
            
            // Check 2: Table structure
            if ($db->tableExists('customer_invoices')) {
                $invCols = $db->getFieldNames('customer_invoices');
                $diagnostics['table_structure']['customer_invoices_columns'] = $invCols;
                $diagnostics['debug_checks']['customer_invoices_exists'] = true;
            } else {
                $diagnostics['debug_checks']['customer_invoices_exists'] = false;
                $diagnostics['recommendations'][] = 'customer_invoices table does not exist!';
            }
            
            // Check 3: Raw invoice count for this customer
            if ($db->tableExists('customer_invoices')) {
                $count = $db->table('customer_invoices')
                    ->where('customer_id', $customerId)
                    ->countAllResults();
                $diagnostics['debug_checks']['total_invoices_for_customer'] = $count;
                
                // Check 4: Raw invoices WITHOUT any filters
                $diagnostics['invoices_raw'] = $db->table('customer_invoices')
                    ->select('id, invoice_number, customer_id, status, total_amount, deleted_at')
                    ->where('customer_id', $customerId)
                    ->orderBy('id', 'DESC')
                    ->limit(10)
                    ->get()
                    ->getResultArray();
                
                $diagnostics['debug_checks']['raw_invoice_sample_count'] = count($diagnostics['invoices_raw']);
                
                // Check 5: Apply filters one by one to see where invoices drop out
                $filtered = $diagnostics['invoices_raw'];
                
                // Filter: deleted_at
                $nonDeleted = array_filter($filtered, function($inv) {
                    return empty($inv['deleted_at']);
                });
                $diagnostics['debug_checks']['after_delete_filter'] = count($nonDeleted);
                
                // Filter: status NOT IN ('cancelled', 'void')
                $notCancelled = array_filter($nonDeleted, function($inv) {
                    $status = strtolower((string)($inv['status'] ?? ''));
                    return !in_array($status, ['cancelled', 'void']);
                });
                $diagnostics['debug_checks']['after_status_filter'] = count($notCancelled);
                
                // Check 6: Get detailed invoice data with payment calculations
                foreach ($diagnostics['invoices_raw'] as $inv) {
                    // Calculate paid amount
                    $allocExpr = 'COALESCE(cpa.amount, cpa.amount_allocated, cpa.allocated_amount, 0)';
                    $paidResult = $db->query(
                        'SELECT COALESCE(SUM(' . $allocExpr . '),0) AS paid_amount '
                        . 'FROM customer_payment_allocations cpa '
                        . 'INNER JOIN customer_payments cp ON cp.id = cpa.payment_id '
                        . "WHERE cpa.invoice_id = ? AND LOWER(COALESCE(cp.status, '')) = 'posted'",
                        [(int)$inv['id']]
                    )->getRow();
                    
                    $total = (float)($inv['total_amount'] ?? 0);
                    $paid = (float)($paidResult->paid_amount ?? 0);
                    $outstanding = round($total - $paid, 2);
                    
                    $diagnostics['invoices_with_calcs'][] = [
                        'id' => $inv['id'],
                        'invoice_number' => $inv['invoice_number'],
                        'status' => $inv['status'],
                        'deleted_at' => $inv['deleted_at'],
                        'total_amount' => $total,
                        'paid_amount' => $paid,
                        'outstanding' => $outstanding,
                        'would_include' => ($outstanding > 0.005 && empty($inv['deleted_at']) && !in_array(strtolower((string)($inv['status'] ?? '')), ['cancelled', 'void'])),
                    ];
                }
                
                // Check 7: Payment totals
                if ($db->tableExists('customer_payments')) {
                    $diagnostics['payments'] = $db->query(
                        'SELECT cp.id, cp.payment_date, cp.amount, cp.status '
                        . 'FROM customer_payments cp '
                        . 'WHERE cp.customer_id = ? '
                        . 'ORDER BY cp.payment_date DESC '
                        . 'LIMIT 10',
                        [(int)$customerId]
                    )->getResultArray();
                }
            }
            
            // Check 8: Run the helper function and compare
            $helperResult = \App\Helpers\CustomerReceivablesHelper::getUnpaidInvoices($customerId, $db, false);
            $diagnostics['debug_checks']['helper_unpaid_count'] = $helperResult['count'];
            $diagnostics['debug_checks']['helper_total_receivable'] = $helperResult['total'];
            
            // Recommendations
            if ($diagnostics['debug_checks']['total_invoices_for_customer'] > 0 && $diagnostics['debug_checks']['helper_unpaid_count'] == 0) {
                $diagnostics['recommendations'][] = 'Invoices exist but are being filtered out - check status and paid amounts';
                $diagnostics['recommendations'][] = 'Check if all invoices are marked as "paid" or "cancelled"';
                $diagnostics['recommendations'][] = 'Verify customer_payment_allocations table for entries';
            }
            
            if ($diagnostics['debug_checks']['total_invoices_for_customer'] == 0) {
                $diagnostics['recommendations'][] = 'No invoices found for this customer';
            }
            
        } catch (\Throwable $e) {
            $diagnostics['error'] = $e->getMessage();
            $diagnostics['recommendations'][] = 'Exception occurred: ' . $e->getMessage();
        }
        
        return $this->response->setJSON($diagnostics);
    }

    public function apiCreate()
    {
        try {
            $input = $this->request->getJSON(true) ?? $this->request->getPost();
        } catch (\Throwable $e) {
            $input = $this->request->getPost();
        }

        if (empty($input['name'])) {
            return $this->response->setStatusCode(400)
                ->setJSON(['success' => false, 'message' => 'Name is required']);
        }

        $payload = [
            'customer_code' => $this->customerModel->generateCustomerCode(),
            'name' => $input['name'],
            'type' => $input['type'] ?? 'retail',
            'status' => $input['status'] ?? 'active',
            'created_by' => session()->get('user_id') ?? null,
        ];
        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $payload['metadata'] = json_encode($input['metadata']);
        }

        $insertId = $this->customerModel->insert($payload);
        if (!$insertId) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'Failed to create customer']);
        }
        return $this->response->setJSON(['success' => true, 'message' => 'Created', 'id' => $insertId]);
    }
}
