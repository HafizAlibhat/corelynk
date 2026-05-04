<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use App\Models\ApiTokenModel;
use App\Models\UserModel;

/**
 * BaseApiController
 *
 * All API controllers extend this class instead of the web BaseController
 * so that they remain completely independent of session-based auth.
 *
 * Every response follows the envelope:
 *   { "success": bool, "data": mixed|null, "message": string }
 */
abstract class BaseApiController extends Controller
{
    protected ?array $apiUser  = null;   // Set by authenticate()
    protected ?string $rawToken = null;  // Set by authenticate()

    // -------------------------------------------------------------------------
    // Server base URL helper
    // -------------------------------------------------------------------------

    /**
     * Derive the server base URL (e.g. http://host/app/public) from the
     * current request URI so we never rely on the stale App::$baseURL config.
     *
     * Uses $_SERVER['SCRIPT_NAME'] (e.g. /corelynk/public/index.php) to get
     * the directory containing index.php, which is the public root.
     */
    protected function serverBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';

        // SCRIPT_NAME is the path to index.php, e.g. /corelynk/public/index.php
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath   = rtrim(dirname($scriptName), '/\\');   // e.g. /corelynk/public

        return $scheme . '://' . $host . $basePath;
    }

    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    /**
     * Build a successful JSON response.
     *
     * @param mixed  $data
     * @param string $message
     * @param int    $statusCode  HTTP status (default 200)
     */
    protected function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): \CodeIgniter\HTTP\Response
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody(json_encode([
                'success' => true,
                'data'    => $data,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Build an error JSON response.
     *
     * @param string $message
     * @param int    $statusCode  HTTP status (default 400)
     * @param mixed  $data        Optional additional context
     */
    protected function error(string $message, int $statusCode = 400, mixed $data = null): \CodeIgniter\HTTP\Response
    {
        return $this->response
            ->setStatusCode($statusCode)
            ->setContentType('application/json')
            ->setBody(json_encode([
                'success' => false,
                'data'    => $data,
                'message' => $message,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Validate the `Authorization: Bearer {token}` header.
     *
     * Returns true and populates $this->apiUser on success.
     * Returns false after sending a 401 response on failure – the calling
     * controller must return immediately when this method returns false.
     *
     * Usage:
     *   if (!$this->authenticate()) return $this->response;
     */
    protected function authenticate(): bool
    {
        // Apache with CGI/FastCGI may strip the Authorization header.
        // The .htaccess RewriteRule passes it as HTTP_AUTHORIZATION env var.
        $authHeader = $this->request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        }

        if (empty($authHeader) || !preg_match('/^Bearer\s+(\S+)$/i', $authHeader, $m)) {
            $this->error('Authentication required. Send Authorization: Bearer {token}', 401);
            return false;
        }

        $rawToken  = $m[1];
        $tokenModel = new ApiTokenModel();
        $tokenRow   = $tokenModel->findByRawToken($rawToken);

        if ($tokenRow === null) {
            $this->error('Invalid or expired token.', 401);
            return false;
        }

        // Load the associated user and verify they are still active
        $userModel = new UserModel();
        $user = $userModel->find($tokenRow['user_id']);

        if ($user === null || (isset($user['is_active']) && !$user['is_active'])) {
            $this->error('User account is inactive.', 401);
            return false;
        }

        // Best-effort touch (non-fatal if it fails)
        try {
            $tokenModel->touchLastUsed($rawToken);
        } catch (\Throwable) {
            // Ignore – logging or telemetry can go here if needed
        }

        $this->apiUser  = $user;
        $this->rawToken = $rawToken;

        // Initialise PolicyEngine for the authenticated API user
        $policy = service('policy');
        $policy->init($user);

        return true;
    }

    // -------------------------------------------------------------------------
    // Authorization
    // -------------------------------------------------------------------------

    /**
     * Check whether the authenticated API user has the given permission.
     *
     * Call after authenticate(). Returns true if allowed, false after
     * sending a 403 response – the calling controller must return
     * $this->response immediately when this returns false.
     *
     * Usage:
     *   if (!$this->authenticate())         return $this->response;
     *   if (!$this->requirePermission('sales_orders', 'read')) return $this->response;
     */
    protected function requirePermission(string $module, string $action = 'read'): bool
    {
        /** @var \App\Libraries\PolicyEngine $policy */
        $policy = service('policy');

        if ($policy->can($module, $action)) {
            return true;
        }

        $this->error("Forbidden: you do not have permission ({$module}.{$action}).", 403);
        return false;
    }

    // -------------------------------------------------------------------------
    // Input helpers
    // -------------------------------------------------------------------------

    /**
     * Return JSON body decoded as an associative array.
     * Falls back to POST form data when Content-Type is not JSON.
     */
    protected function getJsonBody(): array
    {
        $contentType = $this->request->getHeaderLine('Content-Type');

        if (str_contains($contentType, 'application/json')) {
            $raw = $this->request->getBody();
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }

        return $this->request->getPost() ?? [];
    }
}
