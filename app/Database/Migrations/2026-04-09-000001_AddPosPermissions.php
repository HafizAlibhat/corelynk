<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Add POS permission module and assign POS permissions to the Sales role.
 * Also ensures every existing user that has roles assigned via user_roles
 * actually has the data there (belt-and-suspenders consistency).
 */
class AddPosPermissions extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // 1. Add 'pos' permission module (read, write, edit, delete)
        $actions = ['read', 'write', 'edit', 'delete'];
        $posPermIds = [];
        foreach ($actions as $action) {
            $existing = $db->table('permissions')
                ->where('module', 'pos')
                ->where('action', $action)
                ->get()->getRowArray();
            if (!$existing) {
                $db->table('permissions')->insert([
                    'module'      => 'pos',
                    'action'      => $action,
                    'description' => ucfirst($action) . ' access to POS register',
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                $posPermIds[] = $db->insertID();
            } else {
                $posPermIds[] = $existing['id'];
            }
        }

        // 2. Add 'employees' permission module
        $empPermIds = [];
        foreach ($actions as $action) {
            $existing = $db->table('permissions')
                ->where('module', 'employees')
                ->where('action', $action)
                ->get()->getRowArray();
            if (!$existing) {
                $db->table('permissions')->insert([
                    'module'      => 'employees',
                    'action'      => $action,
                    'description' => ucfirst($action) . ' access to employees / HR',
                    'created_at'  => date('Y-m-d H:i:s'),
                ]);
                $empPermIds[] = $db->insertID();
            } else {
                $empPermIds[] = $existing['id'];
            }
        }

        // 3. Assign POS permissions to Sales + Admin roles
        $salesRole = $db->table('roles')->where('slug', 'sales')->get()->getRowArray();
        $adminRole = $db->table('roles')->where('slug', 'admin')->get()->getRowArray();

        foreach ([$salesRole, $adminRole] as $role) {
            if (!$role) continue;
            foreach (array_merge($posPermIds, $empPermIds) as $permId) {
                $exists = $db->table('role_permissions')
                    ->where('role_id', $role['id'])
                    ->where('permission_id', $permId)
                    ->get()->getRowArray();
                if (!$exists) {
                    $db->table('role_permissions')->insert([
                        'role_id'       => $role['id'],
                        'permission_id' => $permId,
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }

        // 4. Assign employee permissions to production and planner roles
        $prodRole = $db->table('roles')->where('slug', 'production')->get()->getRowArray();
        $planRole = $db->table('roles')->where('slug', 'planner')->get()->getRowArray();
        foreach ([$prodRole, $planRole] as $role) {
            if (!$role) continue;
            foreach ($empPermIds as $permId) {
                $exists = $db->table('role_permissions')
                    ->where('role_id', $role['id'])
                    ->where('permission_id', $permId)
                    ->get()->getRowArray();
                if (!$exists) {
                    $db->table('role_permissions')->insert([
                        'role_id'       => $role['id'],
                        'permission_id' => $permId,
                        'created_at'    => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        // Remove pos and employees permissions from role_permissions
        $perms = $db->table('permissions')
            ->whereIn('module', ['pos', 'employees'])
            ->get()->getResultArray();
        $permIds = array_column($perms, 'id');

        if (!empty($permIds)) {
            $db->table('role_permissions')->whereIn('permission_id', $permIds)->delete();
            $db->table('permissions')->whereIn('id', $permIds)->delete();
        }
    }
}
