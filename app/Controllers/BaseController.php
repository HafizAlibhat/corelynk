<?php

namespace App\Controllers;

use App\Models\FeatureFlagModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    /**
     * Authentication bypass flag.
     * Set to false for production – the RBAC system is now active.
     */
    protected bool $authDisabled = false;
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var list<string>
     */
    protected $helpers = ['form', 'url', 'html', 'cookie', 'date', 'security'];

    /**
     * Session instance
     */
    protected $session;

    /**
     * Current user data
     */
    protected $currentUser;

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = service('session');
        $this->session = \Config\Services::session();

        // When auth is disabled, ensure session mimics a logged-in user
        if ($this->authDisabled) {
            // Seed session to look like a logged-in admin for legacy checks
            $sessionDefaults = [
                'user_id'    => 1,
                'username'   => 'demo',
                'email'      => 'demo@example.com',
                'role'       => 'admin',
                'first_name' => 'Demo',
                'last_name'  => 'Admin',
                'logged_in'  => true,
            ];
            foreach ($sessionDefaults as $k => $v) {
                if (!$this->session->has($k)) {
                    $this->session->set($k, $v);
                }
            }
        }

        // Load current user data (will stub when auth disabled)
        $this->loadCurrentUser();

        // Initialise the centralised Policy Engine for RBAC & field masking
        $policy = service('policy');
        $policy->init($this->currentUser);
        
        // Set CSRF protection for all forms
        $this->setCsrfSettings();
    }

    /**
     * Load current user data from session
     */
    protected function loadCurrentUser(): void
    {
        // If auth is disabled, provide a safe default admin-like user stub
        if ($this->authDisabled) {
            $this->currentUser = $this->getDefaultUserStub();
            return;
        }

        if ($this->session->has('user_id')) {
            $userModel = new \App\Models\UserModel();
            $this->currentUser = $userModel->find($this->session->get('user_id'));

            if (!$this->currentUser || empty($this->currentUser['is_active'])) {
                $this->session->destroy();
                $this->currentUser = null;
            }
        }
    }

    /**
     * Set CSRF settings
     */
    protected function setCsrfSettings(): void
    {
        // CSRF token will be automatically added to forms
        helper('form');
    }

    /**
     * Check if user is logged in
     */
    protected function isLoggedIn(): bool
    {
        // When auth is disabled, always consider the user as logged in
        if ($this->authDisabled) {
            return true;
        }
        return !empty($this->currentUser);
    }

    /**
     * Require authentication
     */
    protected function requireAuth(): void
    {
        // No-op when auth is disabled
        if ($this->authDisabled) {
            return;
        }

        if (!$this->isLoggedIn()) {
            // If the request is an AJAX call, return JSON 401 instead of redirecting
            if (isset($this->request) && method_exists($this->request, 'isAJAX') && $this->request->isAJAX()) {
                $payload = ['success' => false, 'message' => 'Authentication required'];
                $this->response->setStatusCode(401)
                    ->setContentType('application/json')
                    ->setBody(json_encode($payload));
                $this->response->send();
                exit;
            }

            $this->session->setFlashdata('error', 'Please log in to access this page.');
            redirect()->to('/auth/login')->send();
            exit;
        }

        // Enforce global MFA policy if enabled by admin.
        if ($this->shouldForceMfaSetup()) {
            if (isset($this->request) && method_exists($this->request, 'isAJAX') && $this->request->isAJAX()) {
                $payload = [
                    'success' => false,
                    'message' => 'Two-factor authentication setup is required by security policy.',
                    'redirect' => base_url('auth/mfa-setup'),
                ];
                $this->response->setStatusCode(403)
                    ->setContentType('application/json')
                    ->setBody(json_encode($payload));
                $this->response->send();
                exit;
            }

            $this->session->setFlashdata('warning', 'Two-factor authentication is required by admin policy. Please complete MFA setup.');
            redirect()->to('/auth/mfa-setup')->send();
            exit;
        }
    }

    /**
     * Return true when logged-in user must be redirected to MFA setup.
     */
    protected function shouldForceMfaSetup(): bool
    {
        if ($this->authDisabled || ! $this->isLoggedIn()) {
            return false;
        }

        if (! FeatureFlagModel::isEnabled(FeatureFlagModel::FLAG_ENABLE_2FA)) {
            return false;
        }

        $path = trim((string) $this->request->getUri()->getPath(), '/');
        $allowed = [
            'auth/mfa-setup',
            'auth/mfa-verify',
            'auth/logout',
            'logout',
        ];
        foreach ($allowed as $suffix) {
            if ($path === $suffix || str_ends_with($path, '/' . $suffix)) {
                return false;
            }
        }

        $userId = (int) ($this->session->get('user_id') ?? 0);
        if ($userId <= 0) {
            return false;
        }

        try {
            $row = \Config\Database::connect()->table('user_mfa')
                ->select('id')
                ->where('user_id', $userId)
                ->where('mfa_enabled', 1)
                ->get()
                ->getRowArray();

            return empty($row);
        } catch (\Throwable) {
            // If MFA table is unavailable, do not block app usage.
            return false;
        }
    }

    /**
     * Default user stub used when auth is disabled.
     */
    protected function getDefaultUserStub(): array
    {
        return [
            'id' => 1,
            'email' => 'demo@example.com',
            'username' => 'demo',
            'first_name' => 'Demo',
            'last_name' => 'Admin',
            'role' => 'admin',
            'is_active' => 1,
            'last_login' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check user permission — checks primary role AND all roles in user_roles table
     */
    protected function hasPermission(string $action): bool
    {
        if (!$this->currentUser) {
            return false;
        }

        $userModel = new \App\Models\UserModel();

        // Check primary role first
        if ($userModel->hasPermission($this->currentUser['role'], $action)) {
            return true;
        }

        // Also check every role assigned via user_roles junction table
        $db = \Config\Database::connect();
        $extraRoles = $db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $this->currentUser['id'])
            ->select('r.slug')
            ->get()
            ->getResultArray();

        foreach ($extraRoles as $roleRow) {
            if ($userModel->hasPermission($roleRow['slug'], $action)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Require specific permission
     */
    protected function requirePermission(string $action): void
    {
        $this->requireAuth();
        
        if (!$this->hasPermission($action)) {
            $this->session->setFlashdata('error', 'You do not have permission to perform this action.');
            redirect()->to('/')->send();
            exit;
        }
    }

    /**
     * Set page data for views
     */
    protected function setPageData(array $data = []): array
    {
        $defaultData = [
            'page_title' => 'Production Management System',
            'current_user' => $this->currentUser,
            'is_logged_in' => $this->isLoggedIn(),
            'csrf_token' => csrf_token(),
            'csrf_hash' => csrf_hash(),
        ];

        return array_merge($defaultData, $data);
    }

    /**
     * Return JSON response
     */
    protected function jsonResponse(array $data, int $statusCode = 200): ResponseInterface
    {
        return $this->response
                    ->setStatusCode($statusCode)
                    ->setContentType('application/json')
                    ->setBody(json_encode($data));
    }
}
