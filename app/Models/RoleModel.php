<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name', 'slug', 'description', 'is_system'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all permissions assigned to this role.
     */
    public function getPermissions(int $roleId): array
    {
        return $this->db->table('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->get()
            ->getResultArray();
    }

    /**
     * Sync permissions: replace all current perms with the given list.
     */
    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $this->db->table('role_permissions')->where('role_id', $roleId)->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($permissionIds as $pid) {
            $this->db->table('role_permissions')->insert([
                'role_id'       => $roleId,
                'permission_id' => (int) $pid,
                'created_at'    => $now,
            ]);
        }
    }

    /**
     * Check if a role has a specific module.action permission.
     */
    public function hasPermission(int $roleId, string $module, string $action): bool
    {
        return (bool) $this->db->table('role_permissions rp')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->where('p.module', $module)
            ->where('p.action', $action)
            ->countAllResults();
    }

    /**
     * Get all users assigned to a role.
     */
    public function getUsers(int $roleId): array
    {
        return $this->db->table('user_roles ur')
            ->join('users u', 'u.id = ur.user_id')
            ->where('ur.role_id', $roleId)
            ->select('u.*')
            ->get()
            ->getResultArray();
    }

    /**
     * Dropdown-friendly list: id => name
     */
    public function dropdown(): array
    {
        $rows = $this->orderBy('name')->findAll();
        $out  = [];
        foreach ($rows as $r) {
            $out[$r['id']] = $r['name'];
        }
        return $out;
    }
}
