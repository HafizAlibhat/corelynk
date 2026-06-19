<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCustomsInvoicePermissions extends Migration
{
    public function up()
    {
        $db = $this->db;

        if (! $db->tableExists('permissions')) {
            return;
        }

        $permissionIds = [];
        foreach (['read', 'write', 'edit', 'delete', 'approve', 'finalize', 'audit'] as $action) {
            $existing = $db->table('permissions')
                ->where('module', 'customs_invoices')
                ->where('action', $action)
                ->get()
                ->getRowArray();

            if ($existing) {
                $permissionIds[] = (int) $existing['id'];
                continue;
            }

            $db->table('permissions')->insert([
                'module' => 'customs_invoices',
                'action' => $action,
                'description' => ucfirst($action) . ' access to customs invoices',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $permissionIds[] = (int) $db->insertID();
        }

        if (! $db->tableExists('roles') || ! $db->tableExists('role_permissions')) {
            return;
        }

        $roles = $db->table('roles')
            ->whereIn('slug', ['admin', 'sales', 'accounts', 'warehouse'])
            ->get()
            ->getResultArray();

        foreach ($roles as $role) {
            $slug = strtolower((string) ($role['slug'] ?? ''));
            $allowActions = ['read'];

            if (in_array($slug, ['admin', 'sales', 'accounts'], true)) {
                $allowActions = ['read', 'write', 'edit'];
            }
            if (in_array($slug, ['admin', 'accounts'], true)) {
                $allowActions[] = 'approve';
                $allowActions[] = 'finalize';
                $allowActions[] = 'audit';
                $allowActions[] = 'delete';
            }

            foreach ($allowActions as $action) {
                $perm = $db->table('permissions')
                    ->where('module', 'customs_invoices')
                    ->where('action', $action)
                    ->get()
                    ->getRowArray();

                if (! $perm) {
                    continue;
                }

                $exists = $db->table('role_permissions')
                    ->where('role_id', (int) $role['id'])
                    ->where('permission_id', (int) $perm['id'])
                    ->get()
                    ->getRowArray();

                if (! $exists) {
                    $db->table('role_permissions')->insert([
                        'role_id' => (int) $role['id'],
                        'permission_id' => (int) $perm['id'],
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
            ->where('module', 'customs_invoices')
            ->get()
            ->getResultArray();

        if (empty($permissionRows)) {
            return;
        }

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $permissionRows);

        if ($db->tableExists('role_permissions')) {
            $db->table('role_permissions')->whereIn('permission_id', $ids)->delete();
        }

        $db->table('permissions')->whereIn('id', $ids)->delete();
    }
}
