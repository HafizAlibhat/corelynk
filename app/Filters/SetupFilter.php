<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Sends every request to the first-run setup wizard until the app has a
 * users table with at least one account in it. Runs before the auth filter
 * so a brand-new deployment doesn't just dead-end at a login page for a
 * user that doesn't exist yet.
 */
class SetupFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $path = ltrim((string) $request->getUri()->getPath(), '/');
        if ($path === 'setup' || str_starts_with($path, 'setup/')) {
            return;
        }

        if (self::isInstalled()) {
            return;
        }

        return redirect()->to('/setup');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No-op
    }

    public static function isInstalled(): bool
    {
        try {
            $db = \Config\Database::connect();

            return $db->tableExists('users') && $db->table('users')->countAllResults() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
