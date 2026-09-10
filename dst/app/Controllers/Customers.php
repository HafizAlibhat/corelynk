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

    /**
     * Upserts the contact-person rows posted by the customer form. A customer can
     * have many people attached (consignee, buyer, agent) and exactly one primary.
     */
    private function syncCustomerPersons(int $customerId, array $post): void
    {
        $rows       = (array) ($post['contacts'] ?? []);
        $primaryIdx = (string) ($post['primary_contact_idx'] ?? '0');
        $model      = new \App\Models\CustomerPersonModel();

        foreach ($rows as $idx => $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue; // a blank row is an abandoned one, not a delete
            }

            $data = [
                'customer_id'        => $customerId,
                'name'               => $name,
                'title'              => trim((string) ($row['title'] ?? '')) ?: null,
                'email'              => trim((string) ($row['email'] ?? '')) ?: null,
                'phone'              => trim((string) ($row['phone'] ?? '')) ?: null,
                'mobile'             => trim((string) ($row['mobile'] ?? '')) ?: null,
                'is_primary_contact' => ((string) $idx === $primaryIdx) ? 1 : 0,
                'is_active'          => 1,
            ];

            $existingId = (int) ($row['id'] ?? 0);
            if ($existingId > 0) {
                $model->update($existingId, $data);
            } else {
                $model->insert($data);
            }
        }

        $deleted = array_filter(array_map('intval', (array) ($post['deleted_contact_ids'] ?? [])));
        if ($deleted !== []) {
            $model->where('customer_id', $customerId)->whereIn('id', $deleted)->delete();
        }
    }

    /* ---------------------------------------------------------------------
     * Inline contact / address maintenance used by the customer detail page.
     * Each endpoint answers with the full, freshly-read lists so the caller
     * simply repaints instead of patching its own copy of the state.
     * ------------------------------------------------------------------- */

    /**
     * The contact captured on the customer record itself at create time (name, email,
     * phone, mobile) is a contact person like any other -- it just predates the
     * customer_persons table. Promote it the first time the customer is opened so it
     * can be edited, and chosen as primary, alongside contacts added later.
     *
     * Skipped when a contact row already carries the same email/phone/mobile, so
     * running it on every page load can never duplicate one.
     */
    private function backfillRecordContact(array $customer): void
    {
        $customerId = (int) ($customer['id'] ?? 0);
        if ($customerId <= 0) {
            return;
        }

        $email  = trim((string) ($customer['email'] ?? ''));
        $phone  = trim((string) ($customer['phone'] ?? ''));
        $mobile = trim((string) ($customer['mobile'] ?? ''));
        if ($email === '' && $phone === '' && $mobile === '') {
            return; // nothing was captured on the record
        }

        try {
            $model    = new \App\Models\CustomerPersonModel();
            $existing = $model->where('customer_id', $customerId)->findAll();

            $matches = static function (string $mine, $theirs): bool {
                $theirs = trim((string) $theirs);

                return $mine !== '' && $theirs !== '' && strcasecmp($mine, $theirs) === 0;
            };
            foreach ($existing as $row) {
                if ($matches($email, $row['email'] ?? '')
                    || $matches($phone, $row['phone'] ?? '')
                    || $matches($mobile, $row['mobile'] ?? '')) {
                    return; // already promoted, or entered by hand
                }
            }

            // Only claim primary if nobody else holds it.
            $primaryTaken = false;
            foreach ($existing as $row) {
                if (!empty($row['is_primary_contact'])) {
                    $primaryTaken = true;
                    break;
                }
            }

            $model->insert([
                'customer_id'        => $customerId,
                'name'               => trim((string) ($customer['name'] ?? '')) ?: 'Primary contact',
                'title'              => 'On customer record',
                'email'              => $email ?: null,
                'phone'              => $phone ?: null,
                'mobile'             => $mobile ?: null,
                'is_primary_contact' => $primaryTaken ? 0 : 1,
                'is_active'          => 1,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Customers::backfillRecordContact failed: ' . $e->getMessage());
        }
    }

    /** @return array{contacts: array, addresses: array} */
    private function customerRelations(int $customerId): array
    {
        $db = \Config\Database::connect();

        return [
            'contacts' => (new \App\Models\CustomerPersonModel())
                ->where('customer_id', $customerId)
                ->orderBy('is_primary_contact', 'DESC')
                ->orderBy('id', 'ASC')
                ->findAll(),
            'addresses' => $db->table('customer_addresses')
                ->where('customer_id', $customerId)
                ->orderBy('is_default', 'DESC')
                ->orderBy('id', 'ASC')
                ->get()->getResultArray(),
        ];
    }

    private function relationResponse(int $customerId, string $message = '')
    {
        return $this->response->setJSON(
            ['ok' => true, 'message' => $message] + $this->customerRelations($customerId)
        );
    }

    private function relationError(string $message, int $code = 422)
    {
        return $this->response->setStatusCode($code)->setJSON(['ok' => false, 'message' => $message]);
    }

    /**
     * Resolve the customer or answer with JSON. Returns the numeric id, or null when
     * a response has already been produced (which the caller must return).
     */
    private function relationCustomerId($identifier, &$error): ?int
    {
        $cust  = $this->customerModel->findByPublicIdOrId($identifier);
        $error = $cust ? null : $this->relationError('Customer not found', 404);

        return $cust ? (int) $cust['id'] : null;
    }

    /** Re-reads contacts and addresses so the document preview can refresh on its own. */
    public function relations($identifier)
    {
        $customerId = $this->relationCustomerId($identifier, $error);

        return $customerId === null ? $error : $this->relationResponse($customerId);
    }

    public function addContact($identifier)
    {
        return $this->saveContact($identifier, 0);
    }

    public function updateContact($identifier, $contactId)
    {
        return $this->saveContact($identifier, (int) $contactId);
    }

    private function saveContact($identifier, int $contactId)
    {
        $customerId = $this->relationCustomerId($identifier, $error);
        if ($customerId === null) {
            return $error;
        }

        $name = trim((string) $this->request->getPost('name'));
        if ($name === '') {
            return $this->relationError('Contact name is required.');
        }

        $model = new \App\Models\CustomerPersonModel();
        $data  = [
            'customer_id' => $customerId,
            'name'        => $name,
            'title'       => trim((string) $this->request->getPost('title')) ?: null,
            'email'       => trim((string) $this->request->getPost('email')) ?: null,
            'phone'       => trim((string) $this->request->getPost('phone')) ?: null,
            'mobile'      => trim((string) $this->request->getPost('mobile')) ?: null,
            'is_active'   => 1,
        ];

        if ($contactId > 0) {
            $existing = $model->find($contactId);
            if (! $existing || (int) $existing['customer_id'] !== $customerId) {
                return $this->relationError('Contact not found', 404);
            }
            $model->update($contactId, $data);
        } else {
            // The first contact on file is the primary one by default.
            $data['is_primary_contact'] = $model->where('customer_id', $customerId)->countAllResults() === 0 ? 1 : 0;
            $model->insert($data);
            $contactId = (int) $model->getInsertID();
        }

        if ($this->request->getPost('is_primary_contact')) {
            $this->makeContactPrimary($customerId, $contactId);
        }

        return $this->relationResponse($customerId, 'Contact saved.');
    }

    public function deleteContact($identifier, $contactId)
    {
        $customerId = $this->relationCustomerId($identifier, $error);
        if ($customerId === null) {
            return $error;
        }

        $model    = new \App\Models\CustomerPersonModel();
        $existing = $model->find((int) $contactId);
        if (! $existing || (int) $existing['customer_id'] !== $customerId) {
            return $this->relationError('Contact not found', 404);
        }

        $model->delete((int) $contactId);

        // Never leave the customer without a primary once contacts remain.
        $remaining = $model->where('customer_id', $customerId)->orderBy('id', 'ASC')->first();
        if ($remaining && ! $model->where('customer_id', $customerId)->where('is_primary_contact', 1)->countAllResults()) {
            $this->makeContactPrimary($customerId, (int) $remaining['id']);
        }

        return $this->relationResponse($customerId, 'Contact removed.');
    }

    public function setContactPrimary($identifier, $contactId)
    {
        $customerId = $this->relationCustomerId($identifier, $error);
        if ($customerId === null) {
            return $error;
        }

        $model    = new \App\Models\CustomerPersonModel();
        $existing = $model->find((int) $contactId);
        if (! $existing || (int) $existing['customer_id'] !== $customerId) {
            return $this->relationError('Contact not found', 404);
        }

        $this->makeContactPrimary($customerId, (int) $contactId);

        return $this->relationResponse($customerId, 'Primary contact updated.');
    }

    /** Exactly one primary contact per customer. */
    private function makeContactPrimary(int $customerId, int $contactId): void
    {
        $db = \Config\Database::connect();
        $db->table('customer_persons')->where('customer_id', $customerId)->update(['is_primary_contact' => 0]);
        $db->table('customer_persons')->where('id', $contactId)->update(['is_primary_contact' => 1]);
    }

    public function addAddress($identifier)
    {
        return $this->saveAddress($identifier, 0);
    }

    public function updateAddress($identifier, $addressId)
    {
        return $this->saveAddress($identifier, (int) $addressId);
    }

    private function saveAddress($identifier, int $addressId)
    {
        $customerId = $this->relationCustomerId($identifier, $error);
        if ($customerId === null) {
            return $error;
        }

        $line1 = trim((string) $this->request->getPost('line1'));
        if ($line1 === '') {
            return $this->relationError('Address line 1 is required.');
        }

        $db        = \Config\Database::connect();
        $countryId = (int) $this->request->getPost('country_id') ?: null;
        $country   = $countryId
            ? ($db->table('countries')->select('name')->where('id', $countryId)->get()->getRowArray()['name'] ?? null)
            : null;

        $data = [
            'label'        => trim((string) $this->request->getPost('label')) ?: null,
            'line1'        => $line1,
            'line2'        => trim((string) $this->request->getPost('line2')) ?: null,
            'country_id'   => $countryId,
            'country_name' => $country,
            'state_name'   => trim((string) $this->request->getPost('state_name')) ?: null,
            'city_name'    => trim((string) $this->request->getPost('city_name')) ?: null,
            'postal_code'  => trim((string) $this->request->getPost('postal_code')) ?: null,
            'is_billing'   => $this->request->getPost('is_billing') ? 1 : 0,
            'is_shipping'  => $this->request->getPost('is_shipping') ? 1 : 0,
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        if ($addressId > 0) {
            $existing = $db->table('customer_addresses')->where('id', $addressId)->get()->getRowArray();
            if (! $existing || (int) $existing['customer_id'] !== $customerId) {
                return $this->relationError('Address not found', 404);
            }
            $db->table('customer_addresses')->where('id', $addressId)->update($data);
        } else {
            $data['customer_id'] = $customerId;
            $data['created_at']  = date('Y-m-d H:i:s');
            $data['is_default']  = $db->table('customer_addresses')->where('customer_id', $customerId)->countAllResults() === 0 ? 1 : 0;
            $db->table('customer_addresses')->insert($data);
            $addressId = (int) $db->insertID();
        }

        if ($this->request->getPost('is_default')) {
            $this->makeAddressDefault($customerId, $addressId);
        }

        return $this->relationResponse($customerId, 'Address saved.');
    }

    public function deleteAddress($identifier, $addressId)
    {
        $customerId = $this->relationCustomerId($identifier, $error);
        if ($customerId === null) {
            return $error;
        }

        $db       = \Config\Database::connect();
        $existing = $db->table('customer_addresses')->where('id', (int) $addressId)->get()->getRowArray();
        if (! $existing || (int) $existing['customer_id'] !== $customerId) {
            return $this->relationError('Address not found', 404);
        }

        $db->table('customer_addresses')->where('id', (int) $addressId)->delete();

        $remaining = $db->table('customer_addresses')->where('customer_id', $customerId)->orderBy('id', 'ASC')->get()->getRowArray();
        $hasDefault = $db->table('customer_addresses')->where('customer_id', $customerId)->where('is_default', 1)->countAllResults();
        if ($remaining && ! $hasDefault) {
            $this->makeAddressDefault($customerId, (int) $remaining['id']);
        }

        return $this->relationResponse($customerId, 'Address removed.');
    }

    public function setAddressDefault($identifier, $addressId)
    {
        $customerId = $this->relationCustomerId($identifier, $error);
        if ($customerId === null) {
            return $error;
        }

        $db       = \Config\Database::connect();
        $existing = $db->table('customer_addresses')->where('id', (int) $addressId)->get()->getRowArray();
        if (! $existing || (int) $existing['customer_id'] !== $customerId) {
            return $this->relationError('Address not found', 404);
        }

        $this->makeAddressDefault($customerId, (int) $addressId);

        return $this->relationResponse($customerId, 'Default address updated.');
    }

    /** Exactly one default address per customer. */
    private function makeAddressDefault(int $customerId, int $addressId): void
    {
        $db = \Config\Database::connect();
        $db->table('customer_addresses')->where('customer_id', $customerId)->update(['is_default' => 0]);
        $db->table('customer_addresses')->where('id', $addressId)->update(['is_default' => 1]);
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

            $this->syncCustomerPersons((int) $insertId, $this->request->getPost() ?? []);

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
            $this->backfillRecordContact($cust);
            try {
                $cust['__contacts'] = (new \App\Models\CustomerPersonModel())
                    ->where('customer_id', $numericId)
                    ->orderBy('is_primary_contact', 'DESC')
                    ->orderBy('id', 'ASC')
                    ->findAll();
            } catch (\Throwable $e) {
                $cust['__contacts'] = [];
            }
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

            $this->syncCustomerPersons($numericId, $post);

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

    /**
     * A picked year (and optional month) is shorthand for a date range. Anything the
     * select cannot produce -- junk year, month 13 -- degrades to the whole year, or
     * to no filter at all.
     *
     * @return array{0: ?int, 1: ?int, 2: ?string, 3: ?string} year, month, from, to
     */
    private function resolvePeriod(string $year, string $month): array
    {
        $year  = trim($year);
        $month = trim($month);
        if (!preg_match('/^\d{4}$/', $year)) {
            return [null, null, null, null];
        }

        $y = (int) $year;
        $m = preg_match('/^(0?[1-9]|1[0-2])$/', $month) ? (int) $month : null;
        if ($m === null) {
            return [$y, null, sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y)];
        }

        $from = sprintf('%04d-%02d-01', $y, $m);

        return [$y, $m, $from, date('Y-m-t', (int) strtotime($from))];
    }

    /** Plain-English name for the period on screen, e.g. "March 2026" or "All time". */
    private function periodLabel(?int $year, ?int $month, ?string $from, ?string $to): string
    {
        if ($year !== null) {
            return $month !== null
                ? date('F Y', (int) mktime(0, 0, 0, $month, 1, $year))
                : (string) $year;
        }
        if ($from !== null && $to !== null) {
            return date('d M Y', (int) strtotime($from)) . ' – ' . date('d M Y', (int) strtotime($to));
        }

        return 'All time';
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
        if (class_exists('\App\Models\CustomerPersonModel')) {
            $this->backfillRecordContact($cust);
            $contactModel = new \App\Models\CustomerPersonModel();
            $contacts = $contactModel->where('customer_id', $numericId)
                ->orderBy('is_primary_contact', 'DESC')
                ->orderBy('id', 'ASC')
                ->findAll();
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

        // Picking a year (and optionally a month) is the easy path; it is just a
        // shorthand for a date range, so it overwrites from/to and everything
        // downstream keeps reading a single pair.
        [$pickedYear, $pickedMonth, $pickedFrom, $pickedTo] = $this->resolvePeriod(
            (string) ($this->request->getGet('year') ?? ''),
            (string) ($this->request->getGet('month') ?? '')
        );
        if ($pickedYear !== null) {
            $fromDateInput = $pickedFrom;
            $toDateInput   = $pickedTo;
        }

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

        // Sales orders can be raised in different currencies (USD, PKR, ...). Every figure below
        // stays grouped by its own currency and is never summed across currencies.
        helper('currency');
        $baseCurrency = base_currency_code();
        $emptyPeriod = ['order_count' => 0, 'units_bought' => 0.0, 'by_currency' => []];

        $analytics = [
            'base_currency' => $baseCurrency,
            'lifetime' => $emptyPeriod + [
                'unique_products' => 0,
                'first_order_date' => null,
                'last_order_date' => null,
            ],
            'yearly' => $emptyPeriod,
            'monthly' => $emptyPeriod,
            'custom' => $emptyPeriod + [
                'from' => $customFrom,
                'to' => $customTo,
                'enabled' => ($customFrom !== null && $customTo !== null),
                'year' => $pickedYear,
                'month' => $pickedMonth,
                'label' => $this->periodLabel($pickedYear, $pickedMonth, $customFrom, $customTo),
            ],
            'top_products' => [],
            'monthly_trend' => [],
            'yearly_trend' => [],
            'date_error' => $dateFilterError,
        ];

        // Folds one currency-grouped result set into a period bucket.
        $mergeCurrency = static function (array &$period, array $rows): void {
            foreach ($rows as $row) {
                $code = currency_code_or_base($row['currency_code'] ?? null);
                $slot = $period['by_currency'][$code] ?? [
                    'currency_code' => $code,
                    'order_count' => 0,
                    'revenue' => 0.0,
                    'avg_order_value' => 0.0,
                    'units_bought' => 0.0,
                ];
                foreach (['order_count', 'revenue', 'avg_order_value', 'units_bought'] as $key) {
                    if (array_key_exists($key, $row)) {
                        $slot[$key] = $key === 'order_count' ? (int)$row[$key] : (float)$row[$key];
                    }
                }
                $period['by_currency'][$code] = $slot;
            }
            $period['order_count'] = (int)array_sum(array_column($period['by_currency'], 'order_count'));
            $period['units_bought'] = (float)array_sum(array_column($period['by_currency'], 'units_bought'));
            uasort($period['by_currency'], static fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        };

        try {
            if ($db->tableExists('sales_orders')) {
                $currentYear = (int)date('Y');
                $currentMonth = (int)date('n');

                $soCols = $db->getFieldNames('sales_orders');
                $curParts = [];
                foreach (['currency_code', 'currency'] as $col) {
                    if (in_array($col, $soCols, true)) {
                        $curParts[] = "NULLIF(so.{$col}, '')";
                    }
                }
                $curParts[] = $db->escape($baseCurrency);
                $soCur = 'COALESCE(' . implode(', ', $curParts) . ')';
                // Grouped by ordinal: under ONLY_FULL_GROUP_BY MySQL rejects the repeated
                // COALESCE() expression, and the alias 'currency_code' resolves to the raw
                // sales_orders column instead of this expression.

                $mergeCurrency($analytics['lifetime'], $db->query(
                    'SELECT ' . $soCur . ' AS currency_code, COUNT(*) AS order_count, '
                    . 'COALESCE(SUM(so.total),0) AS revenue, COALESCE(AVG(so.total),0) AS avg_order_value '
                    . 'FROM sales_orders so WHERE so.customer_id = ? '
                    . 'GROUP BY 1',
                    [$numericId]
                )->getResultArray());

                $span = $db->query(
                    'SELECT MIN(order_date) AS first_order_date, MAX(order_date) AS last_order_date '
                    . 'FROM sales_orders WHERE customer_id = ?',
                    [$numericId]
                )->getRowArray() ?? [];
                $analytics['lifetime']['first_order_date'] = $span['first_order_date'] ?? null;
                $analytics['lifetime']['last_order_date'] = $span['last_order_date'] ?? null;

                $mergeCurrency($analytics['yearly'], $db->query(
                    'SELECT ' . $soCur . ' AS currency_code, COUNT(*) AS order_count, '
                    . 'COALESCE(SUM(so.total),0) AS revenue, COALESCE(AVG(so.total),0) AS avg_order_value '
                    . 'FROM sales_orders so WHERE so.customer_id = ? AND YEAR(so.order_date) = ? '
                    . 'GROUP BY 1',
                    [$numericId, $currentYear]
                )->getResultArray());

                $mergeCurrency($analytics['monthly'], $db->query(
                    'SELECT ' . $soCur . ' AS currency_code, COUNT(*) AS order_count, '
                    . 'COALESCE(SUM(so.total),0) AS revenue, COALESCE(AVG(so.total),0) AS avg_order_value '
                    . 'FROM sales_orders so WHERE so.customer_id = ? AND YEAR(so.order_date) = ? AND MONTH(so.order_date) = ? '
                    . 'GROUP BY 1',
                    [$numericId, $currentYear, $currentMonth]
                )->getResultArray());

                if ($customFrom !== null && $customTo !== null) {
                    $mergeCurrency($analytics['custom'], $db->query(
                        'SELECT ' . $soCur . ' AS currency_code, COUNT(*) AS order_count, '
                        . 'COALESCE(SUM(so.total),0) AS revenue, COALESCE(AVG(so.total),0) AS avg_order_value '
                        . 'FROM sales_orders so WHERE so.customer_id = ? AND so.order_date BETWEEN ? AND ? '
                        . 'GROUP BY 1',
                        [$numericId, $customFrom, $customTo]
                    )->getResultArray());
                }

                if ($db->tableExists('sales_order_lines')) {
                    $lineJoin = 'FROM sales_order_lines sol '
                        . 'INNER JOIN sales_orders so ON so.id = sol.sales_order_id ';

                    $mergeCurrency($analytics['lifetime'], $db->query(
                        'SELECT ' . $soCur . ' AS currency_code, COALESCE(SUM(sol.quantity),0) AS units_bought '
                        . $lineJoin . 'WHERE so.customer_id = ? GROUP BY 1',
                        [$numericId]
                    )->getResultArray());

                    $uniq = $db->query(
                        'SELECT COUNT(DISTINCT COALESCE(sol.product_id, 0), COALESCE(sol.product_variant_id, 0), COALESCE(sol.description, "")) AS unique_products '
                        . $lineJoin . 'WHERE so.customer_id = ?',
                        [$numericId]
                    )->getRowArray() ?? [];
                    $analytics['lifetime']['unique_products'] = (int)($uniq['unique_products'] ?? 0);

                    $mergeCurrency($analytics['yearly'], $db->query(
                        'SELECT ' . $soCur . ' AS currency_code, COALESCE(SUM(sol.quantity),0) AS units_bought '
                        . $lineJoin . 'WHERE so.customer_id = ? AND YEAR(so.order_date) = ? GROUP BY 1',
                        [$numericId, $currentYear]
                    )->getResultArray());

                    $mergeCurrency($analytics['monthly'], $db->query(
                        'SELECT ' . $soCur . ' AS currency_code, COALESCE(SUM(sol.quantity),0) AS units_bought '
                        . $lineJoin . 'WHERE so.customer_id = ? AND YEAR(so.order_date) = ? AND MONTH(so.order_date) = ? GROUP BY 1',
                        [$numericId, $currentYear, $currentMonth]
                    )->getResultArray());

                    if ($customFrom !== null && $customTo !== null) {
                        $mergeCurrency($analytics['custom'], $db->query(
                            'SELECT ' . $soCur . ' AS currency_code, COALESCE(SUM(sol.quantity),0) AS units_bought '
                            . $lineJoin . 'WHERE so.customer_id = ? AND so.order_date BETWEEN ? AND ? GROUP BY 1',
                            [$numericId, $customFrom, $customTo]
                        )->getResultArray());
                    }

                    // A line's description only carries the variant attributes (or is
                    // empty), so name the product from the catalogue and keep the
                    // description/variant as the secondary detail line. CONVERT() is
                    // needed because products and product_variants use different
                    // collations, and the currency stays an ordinal for the
                    // ONLY_FULL_GROUP_BY reason noted above.
                    $nameExpr = 'NULLIF(TRIM(CONVERT(p.name USING utf8mb4)), "")';
                    $descExpr = 'NULLIF(TRIM(CONVERT(sol.description USING utf8mb4)), "")';
                    $analytics['top_products'] = $db->query(
                        'SELECT MIN(COALESCE(' . $nameExpr . ', ' . $descExpr . ', CONCAT("Product #", COALESCE(sol.product_id,0)))) AS product_name, '
                        . 'MIN(COALESCE(NULLIF(TRIM(CONVERT(pv.name USING utf8mb4)), ""), ' . $descExpr . ')) AS product_detail, '
                        . 'MIN(COALESCE(NULLIF(TRIM(CONVERT(pv.art_number USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(p.code USING utf8mb4)), ""), NULLIF(TRIM(CONVERT(p.sku USING utf8mb4)), ""))) AS product_code, '
                        . $soCur . ' AS currency_code, '
                        . 'COALESCE(SUM(sol.quantity),0) AS total_qty, COALESCE(SUM(sol.line_total),0) AS total_sales, '
                        . 'COUNT(DISTINCT so.id) AS order_count '
                        . $lineJoin
                        . 'LEFT JOIN products p ON p.id = sol.product_id '
                        . 'LEFT JOIN product_variants pv ON pv.id = sol.product_variant_id '
                        . 'WHERE so.customer_id = ? '
                        . 'GROUP BY sol.product_id, sol.product_variant_id, 4 '
                        . 'ORDER BY total_qty DESC, total_sales DESC '
                        . 'LIMIT 10',
                        [$numericId]
                    )->getResultArray();
                }

                $analytics['monthly_trend'] = $db->query(
                    'SELECT DATE_FORMAT(so.order_date, "%Y-%m") AS period, ' . $soCur . ' AS currency_code, '
                    . 'COUNT(*) AS order_count, COALESCE(SUM(so.total),0) AS revenue '
                    . 'FROM sales_orders so '
                    . 'WHERE so.customer_id = ? AND so.order_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) '
                    . 'GROUP BY 1, 2 '
                    . 'ORDER BY period ASC',
                    [$numericId]
                )->getResultArray();

                $analytics['yearly_trend'] = $db->query(
                    'SELECT YEAR(so.order_date) AS period, ' . $soCur . ' AS currency_code, '
                    . 'COUNT(*) AS order_count, COALESCE(SUM(so.total),0) AS revenue '
                    . 'FROM sales_orders so '
                    . 'WHERE so.customer_id = ? '
                    . 'GROUP BY 1, 2 '
                    . 'ORDER BY period DESC '
                    . 'LIMIT 16',
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
                'receivable_by_currency' => $receivablesData['totals_by_currency'] ?? [],
                'posted_payments_total' => 0.0,
                'draft_payments_total' => 0.0,
                'posted_by_currency' => [],
                'draft_by_currency' => [],
                'advance_balance' => 0.0,
            ];
            
            // Add payment totals and advance balance
            $paymentData = \App\Helpers\CustomerReceivablesHelper::recalculatePendingAmount($numericId, $db);
            $receivableSummary['posted_payments_total'] = $paymentData['posted_payments'];
            $receivableSummary['draft_payments_total'] = $paymentData['draft_payments'];
            $receivableSummary['posted_by_currency'] = $paymentData['posted_by_currency'] ?? [];
            $receivableSummary['draft_by_currency'] = $paymentData['draft_by_currency'] ?? [];
            $receivableSummary['advance_balance'] = $paymentData['advance_balance'];
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show receivables calculation failed: ' . $e->getMessage());
            $unpaidInvoices = [];
            $orderReceivables = [];
            $receivableSummary = [
                'open_invoice_count' => 0,
                'total_receivable' => 0.0,
                'receivable_by_currency' => [],
                'posted_payments_total' => 0.0,
                'draft_payments_total' => 0.0,
                'posted_by_currency' => [],
                'draft_by_currency' => [],
                'advance_balance' => 0.0,
            ];
        }
        
        // Fetch payment history
        $paymentHistory = [];
        try {
            if ($db->tableExists('customer_payments')) {
                $payCols = $db->getFieldNames('customer_payments');
                // A payment with a journal entry behind it is posted; this table has no
                // status column of its own, and calling every payment a draft made the
                // history disagree with the totals above it.
                $statusExpr = in_array('status', $payCols, true)
                    ? 'cp.status AS status'
                    : (in_array('posted_entry_id', $payCols, true)
                        ? "IF(cp.posted_entry_id IS NOT NULL AND cp.posted_entry_id > 0, 'posted', 'draft') AS status"
                        : "'draft' AS status");
                $payCurExpr = in_array('currency_code', $payCols, true) ? 'cp.currency_code' : "''";
                $methodExpr = in_array('payment_method', $payCols, true)
                    ? 'cp.payment_method'
                    : (in_array('payment_method_id', $payCols, true) && $db->tableExists('payment_methods')
                        ? '(SELECT pm.method_name FROM payment_methods pm WHERE pm.id = cp.payment_method_id)'
                        : (in_array('payment_method_id', $payCols, true) ? "CONCAT('method#', cp.payment_method_id)" : "''"));

                // Only columns this database actually has: cp.memo and the guessed
                // allocation columns did not exist, and the error left the whole
                // payment history empty on every customer page.
                $allocCols = $db->tableExists('customer_payment_allocations')
                    ? $db->getFieldNames('customer_payment_allocations') : [];
                $allocCol  = null;
                foreach (['allocated_amount', 'amount_allocated', 'amount'] as $candidate) {
                    if (in_array($candidate, $allocCols, true)) {
                        $allocCol = $candidate;
                        break;
                    }
                }
                $allocSelect = $allocCol
                    ? '(SELECT COALESCE(SUM(cpa.' . $allocCol . '),0) FROM customer_payment_allocations cpa WHERE cpa.payment_id = cp.id)'
                    : '0';

                $paymentHistory = $db->query(
                    'SELECT cp.id, cp.payment_date, cp.amount, '
                    . $statusExpr . ', '
                    . $methodExpr . ' AS payment_method, '
                    . $payCurExpr . ' AS currency_code, '
                    . (in_array('notes', $payCols, true) ? 'cp.notes, ' : "'' AS notes, ")
                    . (in_array('posted_entry_id', $payCols, true) ? 'cp.posted_entry_id, ' : '0 AS posted_entry_id, ')
                    . $allocSelect . ' AS allocated_amount '
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

        // Almost every customer trades in a single currency, but the system must stay
        // correct for the ones who do not. The page is therefore read one currency at a
        // time: the most-used one by default, switchable from the header.
        $currencyUsage = [];
        $bump = static function (string $code, int $orders = 0) use (&$currencyUsage): void {
            $code = currency_code_or_base($code);
            $currencyUsage[$code] = ($currencyUsage[$code] ?? 0) + $orders;
        };
        foreach ($analytics['lifetime']['by_currency'] as $code => $row) {
            $bump((string) $code, (int) ($row['order_count'] ?? 0));
        }
        foreach (array_keys($receivableSummary['receivable_by_currency']) as $code) {
            $bump((string) $code);
        }
        foreach ($paymentHistory as $payment) {
            $bump((string) ($payment['currency_code'] ?? ''));
        }
        // A customer whose only document is a quotation, or whose invoices are all
        // settled, still trades in that currency -- read every document type.
        try {
            $currencyRows = $db->query(
                "SELECT COALESCE(NULLIF(currency_code, ''), NULLIF(currency, ''), ?) AS c
                   FROM sales_orders WHERE customer_id = ? AND deleted_at IS NULL
                  UNION
                 SELECT COALESCE(NULLIF(quote_currency, ''), NULLIF(base_currency, ''), ?)
                   FROM quotations WHERE customer_id = ? AND deleted_at IS NULL
                  UNION
                 SELECT COALESCE(NULLIF(currency_code, ''), ?)
                   FROM customer_invoices WHERE customer_id = ?
                  UNION
                 SELECT COALESCE(NULLIF(currency_code, ''), ?)
                   FROM customer_payments WHERE customer_id = ?",
                [
                    $baseCurrency, $numericId,
                    $baseCurrency, $numericId,
                    $baseCurrency, $numericId,
                    $baseCurrency, $numericId,
                ]
            )->getResultArray();
            foreach ($currencyRows as $row) {
                $bump((string) ($row['c'] ?? ''));
            }
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show currency scan failed: ' . $e->getMessage());
        }
        // A customer with no orders, quotations, invoices or payments has no
        // currency of their own yet. The page still needs one to format zeroes
        // with, but it must not be presented as "this customer trades in PKR".
        $hasCurrencyHistory = $currencyUsage !== [];
        if ($currencyUsage === []) {
            $currencyUsage[$baseCurrency] = 0;
        }
        arsort($currencyUsage);

        $requested      = strtoupper(trim((string) ($this->request->getGet('currency') ?? '')));
        $activeCurrency = isset($currencyUsage[$requested]) ? $requested : (string) array_key_first($currencyUsage);

        // Latest sales orders and quotations, limited to the currency on screen so the
        // list never mixes a USD order in among PKR ones.
        $recentOrders = $recentQuotes = [];
        try {
            $recentOrders = $db->query(
                "SELECT id, public_id, order_number, order_date, status, total
                   FROM sales_orders
                  WHERE customer_id = ? AND deleted_at IS NULL
                    AND COALESCE(NULLIF(currency_code, ''), NULLIF(currency, ''), ?) = ?
               ORDER BY order_date DESC, id DESC
                  LIMIT 5",
                [$numericId, $baseCurrency, $activeCurrency]
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show recent orders failed: ' . $e->getMessage());
        }
        try {
            $recentQuotes = $db->query(
                "SELECT id, public_id, quote_number, issue_date, valid_until, status, total,
                        converted_to_sales_order_id
                   FROM quotations
                  WHERE customer_id = ? AND deleted_at IS NULL
                    AND COALESCE(NULLIF(quote_currency, ''), NULLIF(base_currency, ''), ?) = ?
               ORDER BY issue_date DESC, id DESC
                  LIMIT 5",
                [$numericId, $baseCurrency, $activeCurrency]
            )->getResultArray();
        } catch (\Throwable $e) {
            log_message('error', 'Customers::show recent quotations failed: ' . $e->getMessage());
        }

        // Country list for the inline "add address" dialog.
        try {
            $countries = $db->table('countries')->select('id,name')->orderBy('name')->get()->getResultArray();
        } catch (\Throwable $e) {
            $countries = [];
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
            'currencyUsage' => $currencyUsage,
            'activeCurrency' => $activeCurrency,
            'hasCurrencyHistory' => $hasCurrencyHistory,
            'recentOrders' => $recentOrders,
            'recentQuotes' => $recentQuotes,
            'countries' => $countries,
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

        $name = trim($input['name'] ?? '');
        if ($name === '') {
            return $this->response->setStatusCode(400)
                ->setJSON(['success' => false, 'message' => 'Name is required']);
        }

        $existing = $this->customerModel->where('LOWER(name)', strtolower($name))->first();
        if ($existing) {
            return $this->response->setStatusCode(409)
                ->setJSON(['success' => false, 'message' => 'A customer with this name already exists']);
        }

        $payload = [
            'customer_code' => $this->customerModel->generateCustomerCode(),
            'name' => $name,
            'type' => $input['type'] ?? 'retail',
            'status' => $input['status'] ?? 'active',
            'created_by' => session()->get('user_id') ?? null,
        ];
        if (!empty($input['phone'])) {
            $payload['phone'] = trim($input['phone']);
        }
        if (!empty($input['email'])) {
            $payload['email'] = trim($input['email']);
        }
        if (isset($input['metadata']) && is_array($input['metadata'])) {
            $payload['metadata'] = json_encode($input['metadata']);
        }

        $insertId = $this->customerModel->insert($payload);
        if (!$insertId) {
            return $this->response->setStatusCode(500)
                ->setJSON(['success' => false, 'message' => 'Failed to create customer']);
        }

        // Quick-add address: stored as the customer's default billing/shipping
        // address so quotations and invoices can print it immediately.
        $addr = $input['address'] ?? [];
        if (is_array($addr) && (trim((string)($addr['line1'] ?? '')) !== '' || !empty($addr['country_id']))) {
            try {
                (new \App\Models\CustomerAddressModel())->insert([
                    'customer_id' => (int)$insertId,
                    'label'       => 'Primary',
                    'line1'       => trim((string)($addr['line1'] ?? '')),
                    'line2'       => trim((string)($addr['line2'] ?? '')) ?: null,
                    'country_id'  => !empty($addr['country_id']) ? (int)$addr['country_id'] : null,
                    'city_name'   => trim((string)($addr['city_name'] ?? '')) ?: null,
                    'postal_code' => trim((string)($addr['postal_code'] ?? '')) ?: null,
                    'is_billing'  => 1,
                    'is_shipping' => 1,
                    'is_default'  => 1,
                ]);
            } catch (\Throwable $e) {
                log_message('error', 'Quick customer address save failed: ' . $e->getMessage());
            }
        }
        return $this->response->setJSON([
            'success' => true,
            'message' => 'Created',
            'id' => $insertId,
            'code' => $payload['customer_code'],
            'name' => $payload['name'],
        ]);
    }
}
