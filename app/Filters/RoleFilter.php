<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RoleFilter – use as  'role:admin,sales'  in route filters.
 * Checks that the logged-in user holds at least one of the specified role slugs.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = \Config\Services::session();
        $allowed = $arguments ?: [];
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

        // Role slugs stored in session by Auth controller
        $userRoles = $session->get('role_slugs') ?: [$session->get('role')];

        // Admin always passes
        if (in_array('admin', $userRoles, true)) return;

        if (!empty($allowed) && empty(array_intersect($userRoles, $allowed))) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON([
                    'success' => false,
                    'message' => 'Forbidden: insufficient role',
                ]);
            }
            return redirect()->to('/')->with('error', 'You do not have the required role to access that page.');
        }
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
