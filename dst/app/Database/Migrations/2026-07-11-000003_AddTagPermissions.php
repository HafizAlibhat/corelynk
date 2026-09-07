<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTagPermissions extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('permissions')) {
            return;
        }

        $permissionIds = [];
        foreach (['read', 'write', 'edit', 'delete'] as $action) {
            $existing = $db->table('permissions')
                ->where('module', 'tags')
                ->where('action', $action)
                ->get()
                ->getRowArray();

            if ($existing) {
                $permissionIds[] = (int) $existing['id'];
                continue;
            }

            $db->table('permissions')->insert([
                'module' => 'tags',
                'action' => $action,
                'description' => ucfirst($action) . ' access to tags',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $permissionIds[] = (int) $db->insertID();
        }

        if (! $db->tableExists('roles') || ! $db->tableExists('role_permissions')) {
            return;
        }

        // Assign tag permissions to admin, sales, and accounts roles
        $roles = $db->table('roles')
            ->whereIn('slug', ['admin', 'sales', 'accounts'])
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            foreach ($permissionIds as $permissionId) {
                $exists = $db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_id', $permissionId)
                    ->get()
                    ->getRowArray();

                if (! $exists) {
                    $db->table('role_permissions')->insert([
                        'role_id' => (int) $role['id'],
                        'permission_id' => $permissionId,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $db = $this->db;

        if (! $db->tableExists('permissions')) {
            return;
        }

        $permissionRows = $db->table('permissions')
            ->where('module', 'tags')
            ->get()
            ->getResultArray();

        $permissionIds = array_map(static fn(array $row): int => (int) $row['id'], $permissionRows);

        if (! empty($permissionIds) && $db->tableExists('role_permissions')) {
            $db->table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        if (! empty($permissionIds)) {
            $db->table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
}
