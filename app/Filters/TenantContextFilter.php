<?php

namespace App\Filters;

use App\Services\OrganizationAccessService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Database;

class TenantContextFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $config = config('Database');
        $config->controlRuntime = $config->default;

        $session = service('session');
        $accountId = (int) ($session->get('account_id') ?: $session->get('login_pending_account_id'));
        $organizationId = (int) ($session->get('organization_id') ?? 0);
        if ($accountId <= 0 || $organizationId <= 0) {
            return null;
        }

        try {
            $membership = (new OrganizationAccessService())->authorizedMembership($accountId, $organizationId);
            if (! $membership || ! OrganizationAccessService::validDatabaseName((string) $membership['database_name'])) {
                $session->remove(['organization_id', 'organization_name', 'user_id', 'logged_in']);
                return null;
            }
            $config->tenantRuntime = array_merge($config->default, ['database' => (string) $membership['database_name']]);
            $config->defaultGroup = 'tenantRuntime';
            $session->set([
                'organization_name' => (string) $membership['name'],
                'organization_role' => (string) $membership['access_role'],
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Tenant context resolution failed: {message}', ['message' => $e->getMessage()]);
            $session->remove(['organization_id', 'organization_name', 'user_id', 'logged_in']);
        }
        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
