<?php

namespace App\Controllers\Api;

use App\Models\ApiTokenModel;

/**
 * API Administration endpoints — manage mobile/API connections.
 *
 * GET  /api/admin/api-tokens           — List all active API tokens (connected devices)
 * GET  /api/admin/api-stats            — API usage statistics
 * POST /api/admin/api-tokens/{id}/revoke — Revoke a specific token
 */
class ApiAdminApi extends BaseApiController
{
    /**
     * GET /api/admin/api-tokens
     *
     * Lists all active (non-revoked) API tokens with user info
     * and last activity time.
     */
    public function tokens(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $tokens = $db->query(
            "SELECT t.id, t.name AS token_name, t.created_at, t.last_used_at, t.expires_at,
                    t.revoked,
                    u.id AS user_id, COALESCE(u.name, u.email) AS user_name, u.email AS user_email
             FROM api_tokens t
             LEFT JOIN users u ON t.user_id = u.id
             WHERE t.revoked = 0
             ORDER BY t.last_used_at DESC, t.created_at DESC"
        )->getResultArray();

        // Calculate active status
        foreach ($tokens as &$token) {
            $lastUsed = $token['last_used_at'];
            $token['is_active_recently'] = false;
            if ($lastUsed) {
                $diff = time() - strtotime($lastUsed);
                $token['is_active_recently'] = $diff < 3600; // active in last hour
                $token['minutes_ago'] = (int) floor($diff / 60);
            }
        }

        return $this->success([
            'total_connected' => count($tokens),
            'tokens' => $tokens,
        ]);
    }

    /**
     * GET /api/admin/api-stats
     *
     * Shows overall API usage stats.
     */
    public function stats(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        // Total tokens ever created
        $totalTokens = (int) ($db->query("SELECT COUNT(*) AS c FROM api_tokens")->getRowArray()['c'] ?? 0);
        $activeTokens = (int) ($db->query("SELECT COUNT(*) AS c FROM api_tokens WHERE revoked = 0")->getRowArray()['c'] ?? 0);
        $revokedTokens = (int) ($db->query("SELECT COUNT(*) AS c FROM api_tokens WHERE revoked = 1")->getRowArray()['c'] ?? 0);

        // Unique users with active tokens
        $uniqueUsers = (int) ($db->query("SELECT COUNT(DISTINCT user_id) AS c FROM api_tokens WHERE revoked = 0")->getRowArray()['c'] ?? 0);

        // Recently active (used in last 24 hours)
        $recentlyActive = (int) ($db->query(
            "SELECT COUNT(*) AS c FROM api_tokens WHERE revoked = 0 AND last_used_at >= ?",
            [date('Y-m-d H:i:s', strtotime('-24 hours'))]
        )->getRowArray()['c'] ?? 0);

        // List exposed API endpoints
        $exposedApis = [
            ['method' => 'POST', 'path' => '/api/login',                    'description' => 'Authenticate & get token'],
            ['method' => 'POST', 'path' => '/api/logout',                   'description' => 'Revoke token'],
            ['method' => 'GET',  'path' => '/api/dashboard',                'description' => 'Basic dashboard counts'],
            ['method' => 'GET',  'path' => '/api/owner/summary',            'description' => 'Owner financial summary'],
            ['method' => 'GET',  'path' => '/api/products',                 'description' => 'Product list'],
            ['method' => 'GET',  'path' => '/api/products/{id}',            'description' => 'Product detail'],
            ['method' => 'GET',  'path' => '/api/vendors',                  'description' => 'Vendor list'],
            ['method' => 'GET',  'path' => '/api/vendors/{id}',             'description' => 'Vendor detail'],
            ['method' => 'GET',  'path' => '/api/sales-orders',             'description' => 'Sales orders list'],
            ['method' => 'GET',  'path' => '/api/sales-orders/{id}',        'description' => 'Sales order detail'],
            ['method' => 'POST', 'path' => '/api/sales-orders',             'description' => 'Create sales order'],
            ['method' => 'GET',  'path' => '/api/purchase-orders',          'description' => 'Purchase orders list'],
            ['method' => 'GET',  'path' => '/api/purchase-orders/{id}',     'description' => 'Purchase order detail'],
            ['method' => 'POST', 'path' => '/api/purchase-orders',          'description' => 'Create purchase order'],
            ['method' => 'GET',  'path' => '/api/quotations',               'description' => 'Quotation list'],
            ['method' => 'GET',  'path' => '/api/quotations/{id}',          'description' => 'Quotation detail'],
            ['method' => 'POST', 'path' => '/api/quotations',               'description' => 'Create quotation'],
            ['method' => 'GET',  'path' => '/api/expenses',                  'description' => 'Expense list (journal entries)'],
            ['method' => 'POST', 'path' => '/api/expenses',                  'description' => 'Record expense'],
            ['method' => 'GET',  'path' => '/api/expense-accounts',          'description' => 'Expense GL accounts'],
            ['method' => 'GET',  'path' => '/api/payment-accounts',          'description' => 'Cash/bank accounts'],
            ['method' => 'GET',  'path' => '/api/receivables',              'description' => 'Customer receivables'],
            ['method' => 'GET',  'path' => '/api/payables',                 'description' => 'Vendor payables'],
            ['method' => 'GET',  'path' => '/api/customers',                'description' => 'Customer list'],
            ['method' => 'GET',  'path' => '/api/customers/{id}',           'description' => 'Customer detail'],
            ['method' => 'GET',  'path' => '/api/admin/api-tokens',         'description' => 'Connected devices'],
            ['method' => 'GET',  'path' => '/api/admin/api-stats',          'description' => 'API usage stats'],
            ['method' => 'POST', 'path' => '/api/admin/api-tokens/{id}/revoke', 'description' => 'Revoke device'],
            ['method' => 'GET',  'path' => '/api/admin/users',              'description' => 'List all users'],
            ['method' => 'GET',  'path' => '/api/admin/users/{id}',         'description' => 'User detail'],
            ['method' => 'POST', 'path' => '/api/admin/users/{id}/toggle-active', 'description' => 'Enable/disable user'],
            ['method' => 'POST', 'path' => '/api/admin/users/{id}/reset-password','description' => 'Reset user password'],
            ['method' => 'POST', 'path' => '/api/admin/users/{id}/set-role', 'description' => 'Change user role'],
            ['method' => 'GET',  'path' => '/api/admin/roles',              'description' => 'List roles'],
        ];

        return $this->success([
            'total_tokens'     => $totalTokens,
            'active_tokens'    => $activeTokens,
            'revoked_tokens'   => $revokedTokens,
            'unique_users'     => $uniqueUsers,
            'recently_active'  => $recentlyActive,
            'total_api_endpoints' => count($exposedApis),
            'exposed_apis'     => $exposedApis,
        ]);
    }

    /**
     * POST /api/admin/api-tokens/{id}/revoke
     *
     * Revokes a specific API token by its ID.
     */
    public function revokeToken(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'edit')) {
            return $this->response;
        }

        $tokenModel = new ApiTokenModel();
        $token = $tokenModel->find($id);

        if (!$token) {
            return $this->error('Token not found.', 404);
        }

        if ($token['revoked']) {
            return $this->error('Token is already revoked.');
        }

        $tokenModel->update($id, ['revoked' => 1]);

        return $this->success(null, 'Token revoked successfully.');
    }
}
