<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PermissionFilter – use as  'permission:invoices.read'  in route filters.
 * Checks the DB-backed RBAC permission tables via PolicyEngine.
 */
class PermissionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = \Config\Services::session();
        $userId = (int) ($session->get('user_id') ?? 0);

        if (!$session->get('logged_in') || $userId <= 0) {
            return $this->unauthorized($request);
        }

        // Validate that the session user still exists and is active.
        try {
            $user = (new UserModel())->select('id, is_active')->find($userId);
            if (!$user || empty($user['is_active'])) {
                $session->destroy();
                return $this->unauthorized($request);
            }
        } catch (\Throwable $_) {
            $session->destroy();
            return $this->unauthorized($request);
        }

        /** @var \App\Libraries\PolicyEngine $policy */
        $policy = service('policy');

        // arguments come as ['invoices.read', 'invoices.write'] etc.
        if (empty($arguments)) return;

        foreach ($arguments as $permSlug) {
            $parts = explode('.', $permSlug, 2);
            if (count($parts) !== 2) continue;

            [$module, $action] = $parts;
            if ($policy->can($module, $action)) {
                return; // At least one perm matches → allow
            }
        }

        // None matched
        if ($request->isAJAX()) {
            return service('response')->setStatusCode(403)->setJSON([
                'success' => false,
                'message' => 'Forbidden: insufficient permissions',
            ]);
        }
        return redirect()->to('/')->with('error', 'You do not have permission to access that page.');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }

    protected function unauthorized(RequestInterface $request)
    {
        if ($request->isAJAX()) {
            return service('response')->setStatusCode(401)->setJSON([
                'success' => false,
                'message' => 'Authentication required',
            ]);
        }

        return redirect()->to('/auth/login')->with('error', 'Please log in first.');
    }
}
