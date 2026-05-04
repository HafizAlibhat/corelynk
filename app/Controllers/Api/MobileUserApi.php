<?php

namespace App\Controllers\Api;

use App\Models\UserModel;
use App\Models\ApiTokenModel;

/**
 * Mobile User Management — admin controls for managing Corelynk mobile users.
 *
 * GET  /api/admin/users                    — List all users with mobile access info
 * GET  /api/admin/users/{id}               — User detail with tokens
 * POST /api/admin/users/{id}/toggle-active — Enable/disable user account
 * POST /api/admin/users/{id}/reset-password— Reset user password from mobile
 * GET  /api/admin/roles                    — List available roles
 * POST /api/admin/users/{id}/set-role      — Change user role
 */
class MobileUserApi extends BaseApiController
{
    /**
     * GET /api/admin/users
     */
    public function index(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $users = $db->query(
            "SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                    u.is_active, u.last_login, u.role_id,
                    r.name AS role_name,
                    (SELECT COUNT(*) FROM api_tokens t WHERE t.user_id = u.id AND t.revoked = 0) AS active_tokens,
                    (SELECT MAX(t2.last_used_at) FROM api_tokens t2 WHERE t2.user_id = u.id AND t2.revoked = 0) AS last_mobile_activity
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             ORDER BY u.id"
        )->getResultArray();

        return $this->success($users);
    }

    /**
     * GET /api/admin/users/{id}
     */
    public function show(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();

        $user = $db->query(
            "SELECT u.id, u.username, u.email, u.first_name, u.last_name,
                    u.is_active, u.last_login, u.role_id, u.created_at,
                    r.name AS role_name
             FROM users u
             LEFT JOIN roles r ON u.role_id = r.id
             WHERE u.id = ?",
            [$id]
        )->getRowArray();

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Get user's API tokens
        $tokens = $db->query(
            "SELECT id, name AS token_name, created_at, last_used_at, revoked, expires_at
             FROM api_tokens WHERE user_id = ?
             ORDER BY created_at DESC",
            [$id]
        )->getResultArray();

        $user['tokens'] = $tokens;

        return $this->success($user);
    }

    /**
     * POST /api/admin/users/{id}/toggle-active
     *
     * Toggles user active status (enable/disable account).
     */
    public function toggleActive(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'edit')) {
            return $this->response;
        }

        $db = \Config\Database::connect();
        $user = $db->query("SELECT id, is_active FROM users WHERE id = ?", [$id])->getRowArray();

        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Don't allow disabling yourself
        if ((int) $id === (int) ($this->apiUser['id'] ?? 0)) {
            return $this->error('Cannot disable your own account.');
        }

        $newStatus = $user['is_active'] ? 0 : 1;
        $db->query("UPDATE users SET is_active = ? WHERE id = ?", [$newStatus, $id]);

        // If disabling, revoke all their tokens too
        if ($newStatus === 0) {
            $db->query("UPDATE api_tokens SET revoked = 1 WHERE user_id = ? AND revoked = 0", [$id]);
        }

        return $this->success([
            'user_id'   => $id,
            'is_active' => $newStatus,
        ], $newStatus ? 'User activated.' : 'User deactivated and all tokens revoked.');
    }

    /**
     * POST /api/admin/users/{id}/reset-password
     *
     * Resets user password. Requires { "new_password": "..." } in body.
     */
    public function resetPassword(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'edit')) {
            return $this->response;
        }

        $body = $this->getJsonBody();

        $newPassword = trim($body['new_password'] ?? '');
        if (strlen($newPassword) < 6) {
            return $this->error('Password must be at least 6 characters.');
        }

        $db = \Config\Database::connect();
        $user = $db->query("SELECT id FROM users WHERE id = ?", [$id])->getRowArray();
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Hash password (same way as UserModel)
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $hash = password_hash($newPassword, $algo);

        $db->query("UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE id = ?", [$hash, $id]);

        return $this->success(null, 'Password reset successfully.');
    }

    /**
     * GET /api/admin/roles
     */
    public function roles(): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'read')) {
            return $this->response;
        }

        $db = \Config\Database::connect();
        $roles = $db->query("SELECT id, name, slug, description FROM roles ORDER BY id")->getResultArray();

        return $this->success($roles);
    }

    /**
     * POST /api/admin/users/{id}/set-role
     *
     * Change user role. Requires { "role_id": 2 } in body.
     */
    public function setRole(int $id): \CodeIgniter\HTTP\Response
    {
        if (!$this->authenticate()) {
            return $this->response;
        }
        if (!$this->requirePermission('users', 'edit')) {
            return $this->response;
        }

        $body = $this->getJsonBody();
        $roleId = (int) ($body['role_id'] ?? 0);

        if ($roleId <= 0) {
            return $this->error('role_id is required.');
        }

        $db = \Config\Database::connect();

        // Verify user exists
        $user = $db->query("SELECT id FROM users WHERE id = ?", [$id])->getRowArray();
        if (!$user) {
            return $this->error('User not found.', 404);
        }

        // Verify role exists
        $role = $db->query("SELECT id, name FROM roles WHERE id = ?", [$roleId])->getRowArray();
        if (!$role) {
            return $this->error('Role not found.', 404);
        }

        $db->query("UPDATE users SET role_id = ? WHERE id = ?", [$roleId, $id]);

        return $this->success([
            'user_id'   => $id,
            'role_id'   => $roleId,
            'role_name' => $role['name'],
        ], 'Role updated successfully.');
    }
}
