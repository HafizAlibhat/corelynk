<?php

namespace App\Controllers;

use App\Services\OrganizationAccessService;

class Companies extends BaseController
{
    private function accountId(): int
    {
        return (int) ($this->session->get('account_id') ?: $this->session->get('login_pending_account_id'));
    }

    public function index()
    {
        $accountId = $this->accountId();
        if ($accountId <= 0) {
            return redirect()->to('/auth/login')->with('error', 'Please sign in first.');
        }
        $service = new OrganizationAccessService();
        return view('companies/index', [
            'companies' => $service->membershipsForAccount($accountId),
            'provisioning' => $service->provisioningForAccount($accountId),
            'activeOrganizationId' => (int) ($this->session->get('organization_id') ?? 0),
            'isPendingLogin' => ! (bool) $this->session->get('logged_in'),
        ]);
    }

    public function create()
    {
        if (! $this->session->get('logged_in') || $this->accountId() <= 0) {
            return redirect()->to('/auth/login')->with('error', 'Please sign in first.');
        }
        return view('companies/create');
    }

    public function store()
    {
        $accountId = $this->accountId();
        if (! $this->request->is('post') || ! $this->session->get('logged_in') || $accountId <= 0) {
            return redirect()->to('/auth/login')->with('error', 'Please sign in first.');
        }
        if (! $this->validate(['company_name' => 'required|min_length[2]|max_length[150]'])) {
            return redirect()->back()->withInput()->with('validation', \Config\Services::validation());
        }
        $name = trim((string) $this->request->getPost('company_name'));
        $slugBase = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'company';
        $suffix = bin2hex(random_bytes(6));
        $publicId = $this->uuidV4();
        $db = (new OrganizationAccessService())->controlDb();
        $db->table('organizations')->insert([
            'public_id' => $publicId,
            'name' => $name,
            'slug' => substr($slugBase . '-' . $suffix, 0, 80),
            'database_name' => 'corelynk_tenant_' . $suffix,
            'status' => 'provisioning',
            'created_by_account_id' => $accountId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return redirect()->to('/companies')->with('success', 'Company request created. It will appear after the provisioning worker completes.');
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function switch(string $publicId)
    {
        if (! $this->request->is('post')) {
            return redirect()->to('/companies');
        }
        $accountId = $this->accountId();
        if ($accountId <= 0 || ! preg_match('/^[a-f0-9-]{36}$/i', $publicId)) {
            return redirect()->to('/auth/login')->with('error', 'Please sign in again.');
        }

        $service = new OrganizationAccessService();
        $selected = null;
        foreach ($service->membershipsForAccount($accountId) as $membership) {
            if (hash_equals(strtolower((string) $membership['public_id']), strtolower($publicId))) {
                $selected = $membership;
                break;
            }
        }
        if (! $selected) {
            log_message('warning', 'Unauthorized company switch denied for account {account}', ['account' => $accountId]);
            return redirect()->to('/companies')->with('error', 'You do not have access to that company.');
        }

        $this->session->regenerate(true);
        $this->session->remove(['account_id', 'user_id', 'logged_in', 'role', 'role_slugs']);
        $this->session->set([
            'login_pending_account_id' => $accountId,
            'login_pending_user_id' => (int) $selected['local_user_id'],
            'organization_id' => (int) $selected['organization_id'],
            'organization_name' => (string) $selected['name'],
        ]);
        return redirect()->to('/auth/resume-login');
    }
}
