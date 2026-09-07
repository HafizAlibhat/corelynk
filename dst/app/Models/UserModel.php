<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'username', 'email', 'password', 'password_hash', 'first_name', 'last_name',
        'role', 'role_id', 'avatar_path', 'is_active',
        'failed_login_count', 'locked_until', 'password_changed_at',
        'last_login', 'documents_private', 'can_make_documents_private',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = ['hashPassword'];
    protected $beforeUpdate   = ['hashPassword'];

    // ---------------------------------------------------------------
    //  Password handling – BCrypt / Argon2id (auto-selects best)
    // ---------------------------------------------------------------

    protected function hashPassword(array $data)
    {
        if (isset($data['data']['password'])) {
            $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            $data['data']['password_hash'] = password_hash($data['data']['password'], $algo);
            unset($data['data']['password']);
            $data['data']['password_changed_at'] = date('Y-m-d H:i:s');
        }
        return $data;
    }

    // ---------------------------------------------------------------
    //  Credential verification
    // ---------------------------------------------------------------

    public function verifyCredentialsByEmail(string $email, string $password): array|false
    {
        $user = $this->where('email', $email)->where('is_active', 1)->first();
        if (!$user) return false;

        if (password_verify($password, $user['password_hash'])) {
            // Transparent rehash if algo changed
            $preferred = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
            if (password_needs_rehash($user['password_hash'], $preferred)) {
                $this->skipValidation(true)->update($user['id'], [
                    'password_hash' => password_hash($password, $preferred),
                ]);
            }
            $this->skipValidation(true)->update($user['id'], [
                'last_login'         => date('Y-m-d H:i:s'),
                'failed_login_count' => 0,
                'locked_until'       => null,
            ]);
            unset($user['password_hash']);
            return $user;
        }
        return false;
    }

    public function verifyCredentials(string $username, string $password): array|false
    {
        $user = $this->groupStart()
                     ->where('username', $username)
                     ->orWhere('email', $username)
                     ->groupEnd()
                     ->where('is_active', 1)
                     ->first();

        if ($user && password_verify($password, $user['password_hash'])) {
            $this->skipValidation(true)->update($user['id'], [
                'last_login'         => date('Y-m-d H:i:s'),
                'failed_login_count' => 0,
                'locked_until'       => null,
            ]);
            unset($user['password_hash']);
            return $user;
        }
        return false;
    }

    // ---------------------------------------------------------------
    //  Brute-force helpers
    // ---------------------------------------------------------------

    public function recordFailedLogin(string $email, int $maxAttempts = 5, int $lockoutMinutes = 15): void
    {
        $user = $this->where('email', $email)->first();
        if (!$user) return;
        $count = ((int) ($user['failed_login_count'] ?? 0)) + 1;
        $update = ['failed_login_count' => $count];
        if ($count >= $maxAttempts) {
            $update['locked_until'] = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
        }
        $this->skipValidation(true)->update($user['id'], $update);
    }

    public function isAccountLocked(string $email): bool
    {
        $user = $this->where('email', $email)->first();
        if (!$user || empty($user['locked_until'])) return false;
        return strtotime($user['locked_until']) > time();
    }

    // ---------------------------------------------------------------
    //  RBAC helpers
    // ---------------------------------------------------------------

    public function getRoles(int $userId): array
    {
        return $this->db->table('user_roles ur')
            ->join('roles r', 'r.id = ur.role_id')
            ->where('ur.user_id', $userId)
            ->select('r.*')
            ->get()
            ->getResultArray();
    }

    public function syncRoles(int $userId, array $roleIds): void
    {
        $this->db->table('user_roles')->where('user_id', $userId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($roleIds as $rid) {
            $this->db->table('user_roles')->insert([
                'user_id' => $userId, 'role_id' => (int) $rid, 'created_at' => $now,
            ]);
        }
        if (!empty($roleIds)) {
            $this->skipValidation(true)->update($userId, ['role_id' => (int) $roleIds[0]]);
        }
    }

    /**
     * Check if user has permission for specific module.action.
     * Supports both DB-backed RBAC and legacy action strings.
     */
    public function hasPermission(string $roleSlugOrId, string $action): bool
    {
        if ($roleSlugOrId === 'admin') return true;

        $parts = explode('.', $action);
        if (count($parts) === 2) {
            [$module, $act] = $parts;
        } else {
            return $this->legacyPermCheck($roleSlugOrId, $action);
        }

        $roleId = is_numeric($roleSlugOrId)
            ? (int) $roleSlugOrId
            : $this->resolveRoleId($roleSlugOrId);
        if (!$roleId) return false;

        $adminRole = $this->db->table('roles')->where('slug', 'admin')->get()->getRowArray();
        if ($adminRole && (int) $adminRole['id'] === $roleId) return true;

        // Map view↔read as aliases (code uses 'view', DB stores 'read')
        $actValues = [$act];
        if ($act === 'view') $actValues[] = 'read';
        elseif ($act === 'read') $actValues[] = 'view';

        return (bool) $this->db->table('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->where('p.module', $module)
            ->whereIn('p.action', $actValues)
            ->countAllResults();
    }

    protected function resolveRoleId(string $slug): ?int
    {
        $row = $this->db->table('roles')->where('slug', $slug)->get()->getRowArray();
        return $row ? (int) $row['id'] : null;
    }

    protected function legacyPermCheck(string $role, string $action): bool
    {
        $legacy = [
            'admin'      => ['*'],
            'planner'    => ['view_dashboard', 'manage_work_orders', 'manage_products', 'view_reports'],
            'production' => ['view_dashboard', 'update_process_runs', 'view_work_orders'],
            'qc'         => ['view_dashboard', 'manage_qc_records', 'view_work_orders', 'view_process_runs'],
            'stores'     => ['view_dashboard', 'manage_components', 'manage_stock', 'view_work_orders'],
            'accounts'   => ['view_dashboard', 'view_reports', 'manage_costs'],
            'viewer'     => ['view_dashboard', 'view_reports'],
        ];
        if (isset($legacy[$role])) {
            return in_array('*', $legacy[$role]) || in_array($action, $legacy[$role]);
        }
        return false;
    }

    // ---------------------------------------------------------------
    //  Utility
    // ---------------------------------------------------------------

    public function getUsersByRole(string $roleSlug): array
    {
        return $this->db->table('users u')
            ->join('user_roles ur', 'ur.user_id = u.id', 'left')
            ->join('roles r', 'r.id = ur.role_id', 'left')
            ->groupStart()
                ->where('r.slug', $roleSlug)
                ->orWhere('u.role', $roleSlug)
            ->groupEnd()
            ->where('u.is_active', 1)
            ->select('u.*')
            ->get()
            ->getResultArray();
    }

    public function getActiveUsersForDropdown(): array
    {
        $users = $this->where('is_active', 1)->orderBy('first_name')->findAll();
        $dropdown = [];
        foreach ($users as $u) {
            $dropdown[$u['id']] = $u['first_name'] . ' ' . $u['last_name'] . ' (' . ucfirst($u['role'] ?? 'user') . ')';
        }
        return $dropdown;
    }
}
