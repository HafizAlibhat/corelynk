<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RbacFilter — Global Route-Based Permission Enforcement
 * ========================================================================
 * Runs AFTER AuthFilter (user is already authenticated).
 * Maps the current URI + HTTP method to a permission key using
 * Config/RoutePermissions.php, then validates via PolicyEngine.
 *
 * FAIL-SAFE: if no mapping matches, access is DENIED (403).
 * ========================================================================
 */
class RbacFilter implements FilterInterface
{
    /**
     * Loaded route-permission map (cached per request).
     */
    protected static ?array $map = null;

    public function before(RequestInterface $request, $arguments = null)
    {
        $session = \Config\Services::session();

        // If user is not logged in, AuthFilter will handle it — skip here.
        if (!$session->get('logged_in') || !$session->get('user_id')) {
            return;
        }

        /** @var \App\Libraries\PolicyEngine $policy */
        $policy = service('policy');

        // Ensure PolicyEngine is initialised (filter runs before controller constructor)
        if (!$policy->isInitialised()) {
            $userId = (int) $session->get('user_id');
            $userModel = new \App\Models\UserModel();
            $user = $userModel->find($userId);
            if ($user) {
                $policy->init($user);
            } else {
                // User not found in DB — deny everything
                return $this->forbidden($request, 'Access denied.');
            }
        }

        // Admins bypass all permission checks
        if ($policy->isAdmin()) {
            return;
        }

        // Get the current URI path relative to base, without leading/trailing slashes
        $uri = $this->getRelativeUri($request);
        $method = strtoupper($request->getMethod());

        // Load the permission map
        $map = $this->loadMap();

        // Find the first matching rule
        $requiredPerm = $this->resolvePermission($uri, $method, $map);

        // If the rule is `true`, the route is always allowed for authenticated users
        if ($requiredPerm === true) {
            return;
        }

        // FAIL-SAFE: no rule found → DENY
        if ($requiredPerm === null || $requiredPerm === false) {
            log_message('warning', "RBAC: No permission rule for [{$method}] {$uri} — access denied for user #{$session->get('user_id')}");
            return $this->forbidden($request, 'Access denied: no permission rule defined for this resource.');
        }

        // Parse "module.action"
        $parts = explode('.', $requiredPerm, 2);
        if (count($parts) !== 2) {
            log_message('error', "RBAC: Malformed permission key '{$requiredPerm}' for URI {$uri}");
            return $this->forbidden($request, 'Access denied.');
        }

        [$module, $action] = $parts;

        if (!$policy->can($module, $action)) {
            log_message('info', "RBAC: User #{$session->get('user_id')} denied [{$module}.{$action}] for [{$method}] {$uri}");
            return $this->forbidden($request, "You do not have permission to access this resource ({$module}.{$action}).");
        }

        // Permission granted — continue
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }

    // ======================================================================
    //  INTERNAL HELPERS
    // ======================================================================

    /**
     * Get URI path relative to the app's base URL, trimmed.
     */
    protected function getRelativeUri(RequestInterface $request): string
    {
        // CI4 provides the path relative to index.php
        $uri = trim(service('uri')->getPath(), '/');

        // Strip the sub-directory if the app lives in a subfolder (e.g. "corelynk/")
        $baseSegments = trim(parse_url(config('App')->baseURL, PHP_URL_PATH) ?? '', '/');
        if ($baseSegments && stripos($uri, $baseSegments) === 0) {
            $uri = trim(substr($uri, strlen($baseSegments)), '/');
        }

        return $uri;
    }

    /**
     * Load and cache the permission map from config.
     */
    protected function loadMap(): array
    {
        if (self::$map !== null) {
            return self::$map;
        }

        $path = APPPATH . 'Config/RoutePermissions.php';
        if (!is_file($path)) {
            log_message('critical', 'RBAC: RoutePermissions.php not found!');
            self::$map = [];
            return self::$map;
        }

        self::$map = require $path;
        return self::$map;
    }

    /**
     * Find the first matching permission rule for the given URI and method.
     *
     * @return string|bool|null  permission slug, `true` (always allow), or null (no match)
     */
    protected function resolvePermission(string $uri, string $method, array $map)
    {
        foreach ($map as $pattern => $perm) {
            // Check for method-specific prefix  e.g. "GET:products"
            $requiredMethod = null;
            if (preg_match('/^(GET|POST|PUT|PATCH|DELETE|OPTIONS|HEAD):(.+)$/i', $pattern, $m)) {
                $requiredMethod = strtoupper($m[1]);
                $pattern = $m[2];
            }

            // If rule specifies a method and it doesn't match, skip
            if ($requiredMethod !== null && $requiredMethod !== $method) {
                continue;
            }

            // Match the URI pattern against the actual URI
            if ($this->matchPattern($pattern, $uri)) {
                return $perm;
            }
        }

        return null; // No match — fail-safe deny
    }

    /**
     * Match a URI against a pattern supporting * and ** wildcards.
     *
     *   *  — matches exactly one path segment (no slashes)
     *   ** — matches zero or more segments (including slashes)
     */
    protected function matchPattern(string $pattern, string $uri): bool
    {
        $pattern = trim($pattern, '/');
        $uri = trim($uri, '/');

        // Exact match (fast path)
        if ($pattern === $uri) {
            return true;
        }

        // Convert pattern to regex
        // Escape regex special chars except our wildcards
        $regex = '';
        $parts = explode('/', $pattern);
        foreach ($parts as $i => $segment) {
            if ($i > 0) {
                $regex .= '/';
            }
            if ($segment === '**') {
                // ** matches everything remaining (including empty)
                $regex .= '.*';
                break; // Nothing after ** matters
            } elseif ($segment === '*') {
                $regex .= '[^/]+';
            } else {
                $regex .= preg_quote($segment, '#');
            }
        }

        return (bool) preg_match('#^' . $regex . '$#i', $uri);
    }

    /**
     * Return a 403 Forbidden response.
     */
    protected function forbidden(RequestInterface $request, string $message = 'Forbidden')
    {
        $isAjax = false;
        try {
            if (method_exists($request, 'isAJAX') && $request->isAJAX()) $isAjax = true;
            if (!$isAjax && stripos($request->getHeaderLine('Accept') ?? '', 'application/json') !== false) $isAjax = true;
        } catch (\Throwable $_) {}

        if ($isAjax) {
            return service('response')
                ->setStatusCode(403)
                ->setJSON([
                    'success' => false,
                    'message' => $message,
                ]);
        }

        // For web requests: show a 403 error page (avoids redirect loops)
        return service('response')
            ->setStatusCode(403)
            ->setBody(view('errors/html/error_403', ['message' => $message], ['saveData' => false]));
    }
}
