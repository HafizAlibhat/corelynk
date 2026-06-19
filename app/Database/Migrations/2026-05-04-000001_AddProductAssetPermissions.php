<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductAssetPermissions extends Migration
{
    public function up()
    {
        $db = $this->db;
        if (! $db->tableExists('permissions')) {
            return;
        }

        $actions = ['read', 'write', 'edit', 'delete'];
        $permissionIds = [];

        foreach ($actions as $action) {
            $existing = $db->table('permissions')
                ->where('module', 'product_assets')
                ->where('action', $action)
                ->get()
                ->getRowArray();

            if (! $existing) {
                $db->table('permissions')->insert([
                    'module' => 'product_assets',
                    'action' => $action,
                    'description' => ucfirst($action) . ' access to product assets',
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $permissionIds[] = (int) $db->insertID();
            } else {
                $permissionIds[] = (int) $existing['id'];
            }
        }

        if (! $db->tableExists('roles') || ! $db->tableExists('role_permissions')) {
            return;
        }

        $adminRole = $db->table('roles')->where('slug', 'admin')->get()->getRowArray();
        if (! $adminRole) {
            return;
        }

        foreach ($permissionIds as $permissionId) {
            $exists = $db->table('role_permissions')
                ->where('role_id', (int) $adminRole['id'])
                ->where('permission_id', $permissionId)
                ->get()
                ->getRowArray();

            if (! $exists) {
                $db->table('role_permissions')->insert([
                    'role_id' => (int) $adminRole['id'],
                    'permission_id' => $permissionId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        $db = $this->db;
        if (! $db->tableExists('permissions')) {
            return;
        }

        $permissionIds = array_map(
            static fn(array $row): int => (int) $row['id'],
            $db->table('permissions')->where('module', 'product_assets')->get()->getResultArray()
        );

        if (! empty($permissionIds) && $db->tableExists('role_permissions')) {
            $db->table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        if (! empty($permissionIds)) {
            $db->table('permissions')->whereIn('id', $permissionIds)->delete();
        }
    }
}