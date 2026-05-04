<?php

namespace App\Filters;

use App\Models\UserModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = \Config\Services::session();
        $isAuthenticated = (bool) $session->get('logged_in');
        $userId = (int) ($session->get('user_id') ?? 0);

        if ($isAuthenticated && $userId > 0) {
            // Guard against stale/tampered sessions: user must exist and be active.
            try {
                $user = (new UserModel())
                    ->select('id, is_active')
                    ->find($userId);

                if (!$user || empty($user['is_active'])) {
                    $session->destroy();
                    $isAuthenticated = false;
                }
            } catch (\Throwable $_) {
                // Fail safe: if we cannot validate user state, treat as unauthenticated.
                $session->destroy();
                $isAuthenticated = false;
            }
        }

        if (!$isAuthenticated || $userId <= 0) {
            // Detect AJAX / JSON requests
            $isAjax = false;
            try {
                if (method_exists($request, 'isAJAX') && $request->isAJAX()) $isAjax = true;
                if (!$isAjax && $request->getGet('ajax')) $isAjax = true;
                if (!$isAjax && method_exists($request, 'getPost') && $request->getPost('ajax')) $isAjax = true;
                if (!$isAjax && stripos($request->getHeaderLine('Accept') ?? '', 'application/json') !== false) $isAjax = true;
            } catch (\Throwable $_) {}

            if ($isAjax) {
                return \Config\Services::response()
                    ->setStatusCode(401)
                    ->setJSON(['success' => false, 'message' => 'Authentication required']);
            }

            // Store intended URL for post-login redirect
            $session->set('redirect_url', (string) $request->getUri());

            return redirect()->to('/auth/login')->with('error', 'Please login to access this page.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }
}
