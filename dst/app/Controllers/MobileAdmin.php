<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\AuditLogModel;

/**
 * Mobile App Administration — manage mobile users, permissions, API tokens.
 * All routes guarded by 'role:admin' filter.
 */
class MobileAdmin extends BaseController
{
    /**
     * Main mobile admin dashboard — users, tokens, exposed APIs.
     */
    public function index()
    {
        $this->requireAuth();
        $db = \Config\Database::connect();
        $userModel = new UserModel();
        $roleModel = new RoleModel();

        // Fetch users with mobile token info
        $users = $userModel->orderBy('created_at', 'DESC')->findAll();
        foreach ($users as &$u) {
            $u['roles'] = $userModel->getRoles((int) $u['id']);
            // Count active API tokens
            $u['active_tokens'] = (int) $db->table('api_tokens')
                ->where('user_id', $u['id'])
                ->where('revoked', 0)
                ->where('(expires_at IS NULL OR expires_at > NOW())')
                ->countAllResults();
            // Last mobile access
            $lastToken = $db->table('api_tokens')
                ->where('user_id', $u['id'])
                ->orderBy('last_used_at', 'DESC')
                ->get(1)->getRowArray();
            $u['last_mobile_access'] = $lastToken['last_used_at'] ?? null;
            $u['mobile_enabled'] = (int) ($u['is_active'] ?? 0);
        }

        // Exposed APIs
        $exposedApis = $this->getExposedApis();

        // Token summary
        $totalTokens = (int) $db->table('api_tokens')
            ->where('revoked', 0)
            ->where('(expires_at IS NULL OR expires_at > NOW())')
            ->countAllResults();
        $totalUsers = (int) $db->table('api_tokens')
            ->select('user_id')
            ->where('revoked', 0)
            ->where('(expires_at IS NULL OR expires_at > NOW())')
            ->distinct()
            ->countAllResults();

        return view('admin/mobile_app', $this->setPageData([
            'page_title'   => 'Mobile App Management – CoreLynk',
            'users'        => $users,
            'allRoles'     => $roleModel->findAll(),
            'exposedApis'  => $exposedApis,
            'totalTokens'  => $totalTokens,
            'totalUsers'   => $totalUsers,
            'totalApis'    => count($exposedApis),
        ]));
    }

    /**
     * Toggle a user's mobile app access (enable/disable + revoke tokens).
     */
    public function toggleMobileAccess(int $id)
    {
        $this->requireAuth();
        $db = \Config\Database::connect();
        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return redirect()->to('/admin/mobile-app')->with('error', 'User not found.');
        }

        // Prevent self-disable
        if ((int) $user['id'] === (int) $this->session->get('user_id')) {
            return redirect()->to('/admin/mobile-app')->with('error', 'Cannot disable your own account.');
        }

        $newStatus = ((int) $user['is_active'] === 1) ? 0 : 1;
        $userModel->update($id, ['is_active' => $newStatus]);

        // If disabling, revoke all active API tokens
        if ($newStatus === 0) {
            $db->table('api_tokens')
                ->where('user_id', $id)
                ->where('revoked', 0)
                ->update(['revoked' => 1]);
        }

        AuditLogModel::record(
            $newStatus ? 'mobile_access_enabled' : 'mobile_access_disabled',
            (int) $this->session->get('user_id'),
            'users', $id,
            ['target_user' => $user['username']]
        );

        $status = $newStatus ? 'enabled' : 'disabled';
        return redirect()->to('/admin/mobile-app')
            ->with('success', "Mobile access {$status} for {$user['username']}.");
    }

    /**
     * Reset a user's password from admin panel.
     */
    public function resetPassword(int $id)
    {
        $this->requireAuth();
        $userModel = new UserModel();
        $user = $userModel->find($id);

        if (!$user) {
            return redirect()->to('/admin/mobile-app')->with('error', 'User not found.');
        }

        $newPassword = $this->request->getPost('new_password');
        if (!$newPassword || strlen($newPassword) < 6) {
            return redirect()->to('/admin/mobile-app')
                ->with('error', 'Password must be at least 6 characters.');
        }

        // Hash with Argon2id if available, else bcrypt
        if (defined('PASSWORD_ARGON2ID')) {
            $hash = password_hash($newPassword, PASSWORD_ARGON2ID);
        } else {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        }

        $userModel->update($id, [
            'password_hash'      => $hash,
            'failed_login_count' => 0,
            'locked_until'       => null,
        ]);

        AuditLogModel::record('mobile_password_reset', (int) $this->session->get('user_id'), 'users', $id, [
            'target_user' => $user['username'],
        ]);

        return redirect()->to('/admin/mobile-app')
            ->with('success', "Password reset for {$user['username']}.");
    }

    /**
     * Revoke a specific API token.
     */
    public function revokeToken(int $tokenId)
    {
        $this->requireAuth();
        $db = \Config\Database::connect();

        $token = $db->table('api_tokens')->where('id', $tokenId)->get()->getRowArray();
        if (!$token) {
            return redirect()->to('/admin/mobile-app')->with('error', 'Token not found.');
        }

        $db->table('api_tokens')->where('id', $tokenId)->update(['revoked' => 1]);

        AuditLogModel::record('mobile_token_revoked', (int) $this->session->get('user_id'), 'api_tokens', $tokenId, [
            'user_id' => $token['user_id'],
        ]);

        return redirect()->to('/admin/mobile-app')
            ->with('success', 'Token revoked. Device will be logged out.');
    }

    /**
     * Revoke ALL tokens for a user.
     */
    public function revokeAllTokens(int $userId)
    {
        $this->requireAuth();
        $db = \Config\Database::connect();
        $userModel = new UserModel();
        $user = $userModel->find($userId);

        if (!$user) {
            return redirect()->to('/admin/mobile-app')->with('error', 'User not found.');
        }

        $count = (int) $db->table('api_tokens')
            ->where('user_id', $userId)
            ->where('revoked', 0)
            ->countAllResults();

        $db->table('api_tokens')
            ->where('user_id', $userId)
            ->where('revoked', 0)
            ->update(['revoked' => 1]);

        AuditLogModel::record('mobile_all_tokens_revoked', (int) $this->session->get('user_id'), 'users', $userId, [
            'target_user' => $user['username'],
            'tokens_revoked' => $count,
        ]);

        return redirect()->to('/admin/mobile-app')
            ->with('success', "Revoked {$count} active session(s) for {$user['username']}.");
    }

    /**
     * Get the list of exposed mobile API endpoints.
     */
    private function getExposedApis(): array
    {
        return [
            ['method' => 'POST', 'path' => '/api/login', 'description' => 'Authenticate & get token'],
            ['method' => 'POST', 'path' => '/api/logout', 'description' => 'Revoke current token'],
            ['method' => 'GET',  'path' => '/api/user', 'description' => 'Current user profile'],
            ['method' => 'GET',  'path' => '/api/owner/summary', 'description' => 'Owner dashboard summary'],
            ['method' => 'GET',  'path' => '/api/products', 'description' => 'List products'],
            ['method' => 'GET',  'path' => '/api/products/{id}', 'description' => 'Product detail'],
            ['method' => 'GET',  'path' => '/api/sales-orders', 'description' => 'List sales orders'],
            ['method' => 'GET',  'path' => '/api/sales-orders/{id}', 'description' => 'Sales order detail'],
            ['method' => 'POST', 'path' => '/api/sales-orders', 'description' => 'Create sales order'],
            ['method' => 'GET',  'path' => '/api/purchase-orders', 'description' => 'List purchase orders'],
            ['method' => 'GET',  'path' => '/api/purchase-orders/{id}', 'description' => 'Purchase order detail'],
            ['method' => 'POST', 'path' => '/api/purchase-orders', 'description' => 'Create purchase order'],
            ['method' => 'GET',  'path' => '/api/quotations', 'description' => 'List quotations'],
            ['method' => 'GET',  'path' => '/api/quotations/{id}', 'description' => 'Quotation detail'],
            ['method' => 'POST', 'path' => '/api/quotations', 'description' => 'Create quotation'],
            ['method' => 'GET',  'path' => '/api/expenses', 'description' => 'List expenses (journal entries)'],
            ['method' => 'POST', 'path' => '/api/expenses', 'description' => 'Record expense (journal entry)'],
            ['method' => 'GET',  'path' => '/api/expense-accounts', 'description' => 'Expense GL accounts'],
            ['method' => 'GET',  'path' => '/api/payment-accounts', 'description' => 'Cash/bank accounts'],
            ['method' => 'GET',  'path' => '/api/customers', 'description' => 'List customers'],
            ['method' => 'GET',  'path' => '/api/customers/{id}', 'description' => 'Customer detail'],
            ['method' => 'GET',  'path' => '/api/vendors', 'description' => 'List vendors'],
            ['method' => 'GET',  'path' => '/api/vendors/{id}', 'description' => 'Vendor detail'],
            ['method' => 'GET',  'path' => '/api/receivables', 'description' => 'Accounts receivable summary'],
            ['method' => 'GET',  'path' => '/api/payables', 'description' => 'Accounts payable summary'],
            ['method' => 'GET',  'path' => '/api/admin/api-tokens', 'description' => 'List API tokens'],
            ['method' => 'POST', 'path' => '/api/admin/api-tokens/{id}/revoke', 'description' => 'Revoke token'],
            ['method' => 'GET',  'path' => '/api/admin/api-stats', 'description' => 'API usage statistics'],
            ['method' => 'GET',  'path' => '/api/admin/users', 'description' => 'List mobile users'],
            ['method' => 'GET',  'path' => '/api/admin/users/{id}', 'description' => 'User detail'],
            ['method' => 'POST', 'path' => '/api/admin/users/{id}/toggle-active', 'description' => 'Enable/disable user'],
            ['method' => 'POST', 'path' => '/api/admin/users/{id}/reset-password', 'description' => 'Reset user password'],
            ['method' => 'POST', 'path' => '/api/admin/users/{id}/set-role', 'description' => 'Change user role'],
            ['method' => 'GET',  'path' => '/api/admin/roles', 'description' => 'List roles'],
        ];
    }
}
