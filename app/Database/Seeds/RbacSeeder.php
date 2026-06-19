<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeds the RBAC tables with default roles, permissions and
 * maps the existing admin user (id = 2) to the Admin role.
 */
class RbacSeeder extends Seeder
{
    public function run()
    {
        $db = \Config\Database::connect();

        /* ================================================================
         * 1.  ROLES
         * ================================================================ */
        $roles = [
            ['name' => 'Admin',     'slug' => 'admin',     'description' => 'Full system access',                    'is_system' => 1],
            ['name' => 'Sales',     'slug' => 'sales',     'description' => 'Sales, quotations, customer management','is_system' => 1],
            ['name' => 'Warehouse', 'slug' => 'warehouse', 'description' => 'Inventory, GRN, delivery orders',       'is_system' => 1],
            ['name' => 'Accounts',  'slug' => 'accounts',  'description' => 'Accounting, invoices, payments',        'is_system' => 1],
            ['name' => 'Production','slug' => 'production','description' => 'Work orders, process runs',             'is_system' => 1],
            ['name' => 'Planner',   'slug' => 'planner',   'description' => 'Planning, scheduling, BOM',             'is_system' => 1],
            ['name' => 'QC',        'slug' => 'qc',        'description' => 'Quality control',                       'is_system' => 1],
            ['name' => 'Viewer',    'slug' => 'viewer',    'description' => 'Read-only access to dashboard & reports','is_system' => 1],
        ];
        foreach ($roles as $r) {
            $r['created_at'] = date('Y-m-d H:i:s');
            $r['updated_at'] = date('Y-m-d H:i:s');
            // Upsert
            $existing = $db->table('roles')->where('slug', $r['slug'])->get()->getRowArray();
            if (!$existing) {
                $db->table('roles')->insert($r);
            }
        }

        /* ================================================================
         * 2.  PERMISSIONS  –  module × action matrix
         * ================================================================ */
        $modules = [
            'dashboard', 'invoices', 'orders', 'sales_orders', 'quotations',
            'purchase_orders', 'rfq', 'inventory', 'products', 'product_assets', 'customers',
            'vendors', 'grn', 'delivery_orders', 'work_orders', 'accounting',
            'reports', 'settings', 'users', 'pos', 'employees',
        ];
        $actions = ['read', 'write', 'edit', 'delete'];
        $permMap = []; // slug => id
        foreach ($modules as $mod) {
            foreach ($actions as $act) {
                $existing = $db->table('permissions')->where('module', $mod)->where('action', $act)->get()->getRowArray();
                if (!$existing) {
                    $db->table('permissions')->insert([
                        'module'      => $mod,
                        'action'      => $act,
                        'description' => ucfirst($act) . ' access to ' . str_replace('_', ' ', $mod),
                        'created_at'  => date('Y-m-d H:i:s'),
                    ]);
                    $permMap[$mod . '.' . $act] = $db->insertID();
                } else {
                    $permMap[$mod . '.' . $act] = $existing['id'];
                }
            }
        }

        /* ================================================================
         * 3.  ROLE ↔ PERMISSION  mappings
         * ================================================================ */
        $roleRows  = $db->table('roles')->get()->getResultArray();
        $roleLookup = [];
        foreach ($roleRows as $rr) {
            $roleLookup[$rr['slug']] = $rr['id'];
        }

        // Admin – gets everything
        $allPermIds = array_values($permMap);
        $this->assignPerms($db, $roleLookup['admin'], $allPermIds);

        // Sales
        $salesModules = ['dashboard', 'invoices', 'orders', 'sales_orders', 'quotations', 'customers', 'products', 'reports', 'pos'];
        $salesPerms = [];
        foreach ($salesModules as $sm) {
            foreach ($actions as $a) {
                if (isset($permMap[$sm . '.' . $a])) $salesPerms[] = $permMap[$sm . '.' . $a];
            }
        }
        $this->assignPerms($db, $roleLookup['sales'], $salesPerms);

        // Warehouse
        $whModules = ['dashboard', 'inventory', 'grn', 'delivery_orders', 'products'];
        $whPerms = [];
        foreach ($whModules as $wm) {
            foreach ($actions as $a) {
                if (isset($permMap[$wm . '.' . $a])) $whPerms[] = $permMap[$wm . '.' . $a];
            }
        }
        // Warehouse gets read-only on orders & purchase_orders
        foreach (['orders', 'purchase_orders', 'sales_orders'] as $ro) {
            if (isset($permMap[$ro . '.read'])) $whPerms[] = $permMap[$ro . '.read'];
        }
        $this->assignPerms($db, $roleLookup['warehouse'], $whPerms);

        // Accounts
        $accModules = ['dashboard', 'invoices', 'accounting', 'reports', 'customers', 'vendors'];
        $accPerms = [];
        foreach ($accModules as $am) {
            foreach ($actions as $a) {
                if (isset($permMap[$am . '.' . $a])) $accPerms[] = $permMap[$am . '.' . $a];
            }
        }
        $this->assignPerms($db, $roleLookup['accounts'], $accPerms);

        // Production
        $prodModules = ['dashboard', 'work_orders', 'products', 'inventory', 'employees'];
        $prodPerms = [];
        foreach ($prodModules as $pm) {
            foreach ($actions as $a) {
                if (isset($permMap[$pm . '.' . $a])) $prodPerms[] = $permMap[$pm . '.' . $a];
            }
        }
        $this->assignPerms($db, $roleLookup['production'], $prodPerms);

        // Planner
        $planModules = ['dashboard', 'work_orders', 'products', 'sales_orders', 'purchase_orders', 'reports', 'employees'];
        $planPerms = [];
        foreach ($planModules as $pm) {
            foreach ($actions as $a) {
                if (isset($permMap[$pm . '.' . $a])) $planPerms[] = $permMap[$pm . '.' . $a];
            }
        }
        $this->assignPerms($db, $roleLookup['planner'], $planPerms);

        // QC
        $qcModules = ['dashboard', 'work_orders', 'products'];
        $qcPerms = [];
        foreach ($qcModules as $qm) {
            foreach (['read'] as $a) { // QC read-only by default
                if (isset($permMap[$qm . '.' . $a])) $qcPerms[] = $permMap[$qm . '.' . $a];
            }
        }
        $this->assignPerms($db, $roleLookup['qc'], $qcPerms);

        // Viewer
        $viewerPerms = [];
        foreach (['dashboard', 'reports'] as $vm) {
            if (isset($permMap[$vm . '.read'])) $viewerPerms[] = $permMap[$vm . '.read'];
        }
        $this->assignPerms($db, $roleLookup['viewer'], $viewerPerms);

        /* ================================================================
         * 4.  FIELD-LEVEL PERMISSIONS  (data masking defaults)
         * ================================================================ */
        // Warehouse cannot see invoice values, customer address, customer country
        $fieldRules = [
            ['role' => 'warehouse', 'module' => 'invoices',  'field_name' => 'total_amount',    'visibility' => 'masked'],
            ['role' => 'warehouse', 'module' => 'invoices',  'field_name' => 'subtotal',        'visibility' => 'masked'],
            ['role' => 'warehouse', 'module' => 'invoices',  'field_name' => 'tax_amount',      'visibility' => 'masked'],
            ['role' => 'warehouse', 'module' => 'invoices',  'field_name' => 'unit_price',      'visibility' => 'masked'],
            ['role' => 'warehouse', 'module' => 'customers', 'field_name' => 'address',         'visibility' => 'hidden'],
            ['role' => 'warehouse', 'module' => 'customers', 'field_name' => 'city',            'visibility' => 'hidden'],
            ['role' => 'warehouse', 'module' => 'customers', 'field_name' => 'country',         'visibility' => 'hidden'],
            ['role' => 'warehouse', 'module' => 'customers', 'field_name' => 'email',           'visibility' => 'masked'],
            ['role' => 'warehouse', 'module' => 'customers', 'field_name' => 'phone',           'visibility' => 'masked'],
            // Production  – no financial data
            ['role' => 'production','module' => 'invoices',  'field_name' => 'total_amount',    'visibility' => 'hidden'],
            ['role' => 'production','module' => 'invoices',  'field_name' => 'unit_price',      'visibility' => 'hidden'],
            // QC – no financial data, no customer info
            ['role' => 'qc',       'module' => 'invoices',  'field_name' => 'total_amount',    'visibility' => 'hidden'],
            ['role' => 'qc',       'module' => 'customers', 'field_name' => 'address',         'visibility' => 'hidden'],
            ['role' => 'qc',       'module' => 'customers', 'field_name' => 'country',         'visibility' => 'hidden'],
        ];

        foreach ($fieldRules as $fr) {
            $roleId = $roleLookup[$fr['role']] ?? null;
            if (!$roleId) continue;
            $existing = $db->table('field_permissions')
                ->where('role_id', $roleId)
                ->where('module', $fr['module'])
                ->where('field_name', $fr['field_name'])
                ->get()->getRowArray();
            if (!$existing) {
                $db->table('field_permissions')->insert([
                    'role_id'    => $roleId,
                    'module'     => $fr['module'],
                    'field_name' => $fr['field_name'],
                    'visibility' => $fr['visibility'],
                    'mask_value' => $fr['visibility'] === 'masked' ? '***' : null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        /* ================================================================
         * 5.  MAP EXISTING ADMIN USER → admin role
         * ================================================================ */
        $adminUser = $db->table('users')->where('role', 'admin')->get()->getRowArray();
        if ($adminUser) {
            $adminRoleId = $roleLookup['admin'];
            // Update role_id column
            $db->table('users')->where('id', $adminUser['id'])->update(['role_id' => $adminRoleId]);
            // Insert into user_roles pivot
            $exists = $db->table('user_roles')
                ->where('user_id', $adminUser['id'])
                ->where('role_id', $adminRoleId)
                ->get()->getRowArray();
            if (!$exists) {
                $db->table('user_roles')->insert([
                    'user_id'    => $adminUser['id'],
                    'role_id'    => $adminRoleId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }

        echo "RBAC seeder completed successfully.\n";
    }

    /**
     * Helper: assign permission IDs to a role (skip duplicates)
     */
    private function assignPerms($db, int $roleId, array $permIds): void
    {
        foreach (array_unique($permIds) as $pid) {
            $exists = $db->table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $pid)
                ->get()->getRowArray();
            if (!$exists) {
                $db->table('role_permissions')->insert([
                    'role_id'       => $roleId,
                    'permission_id' => $pid,
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }
}
