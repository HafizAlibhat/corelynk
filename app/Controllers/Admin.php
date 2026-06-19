<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Models\RoleModel;
use App\Models\PermissionModel;
use App\Models\FieldPermissionModel;
use App\Models\RoleDataAccessModel;
use App\Models\AuditLogModel;

/**
 * Admin controller – User management, Role management, Audit log.
 * All routes guarded by 'role:admin' filter.
 */
class Admin extends BaseController
{
    private function normalizeLegacyRoleSlug(?string $slug): string
    {
        $slug = strtolower(trim((string) $slug));
        $allowed = ['admin', 'planner', 'production', 'qc', 'stores', 'accounts', 'viewer'];
        if (in_array($slug, $allowed, true)) {
            return $slug;
        }

        return match ($slug) {
            'warehouse' => 'stores',
            default => 'viewer',
        };
    }

    // ==================================================================
    //  USER MANAGEMENT
    // ==================================================================

    public function users()
    {
        $this->requireAuth();
        $userModel = new UserModel();
        $roleModel = new RoleModel();

        $search = $this->request->getGet('search');
        $builder = $userModel->orderBy('created_at', 'DESC');
        if ($search) {
            $builder->groupStart()
                ->like('username', $search)
                ->orLike('email', $search)
                ->orLike('first_name', $search)
                ->orLike('last_name', $search)
            ->groupEnd();
        }

        $users = $builder->paginate(20);
        $pager = $userModel->pager;

        // Attach roles to each user
        foreach ($users as &$u) {
            $u['roles'] = $userModel->getRoles((int) $u['id']);
        }

        return view('admin/users', $this->setPageData([
            'page_title' => 'User Management – CoreLynk',
            'users'      => $users,
            'pager'      => $pager,
            'search'     => $search,
            'allRoles'   => $roleModel->findAll(),
        ]));
    }

    public function createUser()
    {
        $this->requireAuth();
        $roleModel = new RoleModel();

        return view('admin/user_form', $this->setPageData([
            'page_title' => 'Create User – CoreLynk',
            'user'       => null,
            'userRoles'  => [],
            'allRoles'   => $roleModel->findAll(),
        ]));
    }

    public function storeUser()
    {
        $this->requireAuth();
        $userModel = new UserModel();

        $rules = [
            'username'         => 'required|min_length[3]|max_length[50]|is_unique[users.username]',
            'email'            => 'required|valid_email|is_unique[users.email]',
            'password'         => 'required|min_length[8]',
            'confirm_password' => 'required|matches[password]',
            'first_name'       => 'required|min_length[2]|max_length[50]',
            'last_name'        => 'required|min_length[2]|max_length[50]',
        ];
        if (!$this->validate($rules)) {
            $roleModel = new RoleModel();
            return view('admin/user_form', $this->setPageData([
                'page_title' => 'Create User – CoreLynk',
                'user'       => null,
                'userRoles'  => [],
                'allRoles'   => $roleModel->findAll(),
                'validation' => \Config\Services::validation(),
            ]));
        }

        $roleIds = array_map('intval', (array) ($this->request->getPost('role_ids') ?: []));
        $legacySlug = 'viewer';
        if (!empty($roleIds)) {
            $db = \Config\Database::connect();
            $r  = $db->table('roles')->where('id', $roleIds[0])->get()->getRowArray();
            if ($r) $legacySlug = $this->normalizeLegacyRoleSlug($r['slug'] ?? null);
        }

        $userModel->skipValidation(true);
        $newId = $userModel->insert([
            'username'   => $this->request->getPost('username'),
            'email'      => $this->request->getPost('email'),
            'password'   => $this->request->getPost('password'),
            'first_name' => $this->request->getPost('first_name'),
            'last_name'  => $this->request->getPost('last_name'),
            'role'       => $legacySlug,
            'role_id'    => $roleIds[0] ?? null,
            'is_active'  => $this->request->getPost('is_active') ? 1 : 0,
        ]);

        if ($newId && !empty($roleIds)) {
            $userModel->syncRoles($newId, $roleIds);
        }

        AuditLogModel::record('user_created', (int) $this->session->get('user_id'), 'users', $newId, [
            'username' => $this->request->getPost('username'),
            'roles'    => $roleIds,
        ]);

        return redirect()->to('/admin/users')->with('success', 'User created successfully.');
    }

    public function editUser(int $id)
    {
        $this->requireAuth();
        $userModel = new UserModel();
        $roleModel = new RoleModel();

        $user = $userModel->find($id);
        if (!$user) return redirect()->to('/admin/users')->with('error', 'User not found.');

        $userRoles = array_column($userModel->getRoles($id), 'id');

        return view('admin/user_form', $this->setPageData([
            'page_title' => 'Edit User – CoreLynk',
            'user'       => $user,
            'userRoles'  => $userRoles,
            'allRoles'   => $roleModel->findAll(),
        ]));
    }

    public function updateUser(int $id)
    {
        $this->requireAuth();
        $userModel = new UserModel();

        $rules = [
            'username'   => "required|min_length[3]|max_length[50]|is_unique[users.username,id,{$id}]",
            'email'      => "required|valid_email|is_unique[users.email,id,{$id}]",
            'first_name' => 'required|min_length[2]|max_length[50]',
            'last_name'  => 'required|min_length[2]|max_length[50]',
        ];
        if (!$this->validate($rules)) {
            $roleModel = new RoleModel();
            return view('admin/user_form', $this->setPageData([
                'page_title' => 'Edit User – CoreLynk',
                'user'       => $userModel->find($id),
                'userRoles'  => array_column($userModel->getRoles($id), 'id'),
                'allRoles'   => $roleModel->findAll(),
                'validation' => \Config\Services::validation(),
            ]));
        }

        $update = [
            'username'   => $this->request->getPost('username'),
            'email'      => $this->request->getPost('email'),
            'first_name' => $this->request->getPost('first_name'),
            'last_name'  => $this->request->getPost('last_name'),
            'is_active'  => $this->request->getPost('is_active') ? 1 : 0,
            'can_make_documents_private' => $this->request->getPost('can_make_documents_private') ? 1 : 0,
        ];

        // Optional password change
        $pw = $this->request->getPost('password');
        if ($pw && strlen($pw) >= 8) {
            $update['password'] = $pw;
        }

        $roleIds = array_map('intval', (array) ($this->request->getPost('role_ids') ?: []));
        if (!empty($roleIds)) {
            $db = \Config\Database::connect();
            $r  = $db->table('roles')->where('id', $roleIds[0])->get()->getRowArray();
            $update['role']    = $this->normalizeLegacyRoleSlug($r['slug'] ?? null);
            $update['role_id'] = $roleIds[0];
        }

        $userModel->skipValidation(true)->update($id, $update);
        if (!empty($roleIds)) {
            $userModel->syncRoles($id, $roleIds);
        }

        AuditLogModel::record('user_updated', (int) $this->session->get('user_id'), 'users', $id, [
            'changes' => array_keys($update),
            'roles'   => $roleIds,
        ]);

        return redirect()->to('/admin/users')->with('success', 'User updated successfully.');
    }

    public function toggleUser(int $id)
    {
        $this->requireAuth();
        $userModel = new UserModel();
        $user = $userModel->find($id);
        if (!$user) return redirect()->to('/admin/users')->with('error', 'User not found.');

        $newStatus = $user['is_active'] ? 0 : 1;
        $userModel->skipValidation(true)->update($id, ['is_active' => $newStatus]);

        AuditLogModel::record($newStatus ? 'user_activated' : 'user_deactivated',
            (int) $this->session->get('user_id'), 'users', $id);

        $label = $newStatus ? 'activated' : 'deactivated';
        return redirect()->to('/admin/users')->with('success', "User {$label}.");
    }

    // ==================================================================
    //  ROLE MANAGEMENT
    // ==================================================================

    public function roles()
    {
        $this->requireAuth();
        $roleModel = new RoleModel();
        $roles = $roleModel->orderBy('name')->findAll();
        $dataAccessModel = new RoleDataAccessModel();
        try {
            $dataAccessModel->ensureSchema();
        } catch (\Throwable $_) {
            // keep roles page operational even if schema creation fails
        }

        // Count users per role
        $db = \Config\Database::connect();
        foreach ($roles as &$r) {
            $r['user_count'] = $db->table('user_roles')->where('role_id', $r['id'])->countAllResults();
            $r['perm_count'] = $db->table('role_permissions')->where('role_id', $r['id'])->countAllResults();
            $r['has_data_access_rule'] = false;
            try {
                $r['has_data_access_rule'] = (bool) $dataAccessModel->where('role_id', (int) $r['id'])->first();
            } catch (\Throwable $_) {
                $r['has_data_access_rule'] = false;
            }
        }

        return view('admin/roles', $this->setPageData([
            'page_title' => 'Role Management – CoreLynk',
            'roles'      => $roles,
        ]));
    }

    public function createRole()
    {
        $this->requireAuth();
        $permModel = new PermissionModel();

        return view('admin/role_form', $this->setPageData([
            'page_title'       => 'Create Role – CoreLynk',
            'role'             => null,
            'rolePermIds'      => [],
            'permissionGroups' => $permModel->groupedByModule(),
        ]));
    }

    public function storeRole()
    {
        $this->requireAuth();
        $roleModel = new RoleModel();

        $rules = [
            'name' => 'required|min_length[2]|max_length[60]|is_unique[roles.name]',
        ];
        if (!$this->validate($rules)) {
            $permModel = new PermissionModel();
            return view('admin/role_form', $this->setPageData([
                'page_title'       => 'Create Role – CoreLynk',
                'role'             => null,
                'rolePermIds'      => [],
                'permissionGroups' => $permModel->groupedByModule(),
                'validation'       => \Config\Services::validation(),
            ]));
        }

        $name = $this->request->getPost('name');
        $slug = url_title($name, '-', true);

        $newId = $roleModel->insert([
            'name'        => $name,
            'slug'        => $slug,
            'description' => $this->request->getPost('description'),
            'is_system'   => 0,
        ]);

        $permIds = array_map('intval', (array) ($this->request->getPost('permission_ids') ?: []));
        if ($this->request->getPost('products_sensitive_overview')) {
            $p = $permModel->where('module', 'products')->where('action', 'sensitive_overview')->first();
            if (!empty($p['id'])) {
                $permIds[] = (int)$p['id'];
            }
        }
        if ($newId && !empty($permIds)) {
            $roleModel->syncPermissions($newId, $permIds);
        }

        AuditLogModel::record('role_created', (int) $this->session->get('user_id'), 'roles', $newId, [
            'name'        => $name,
            'permissions' => $permIds,
        ]);

        return redirect()->to('/admin/roles')->with('success', 'Role created successfully.');
    }

    public function editRole(int $id)
    {
        $this->requireAuth();
        $roleModel = new RoleModel();
        $permModel = new PermissionModel();

        $role = $roleModel->find($id);
        if (!$role) return redirect()->to('/admin/roles')->with('error', 'Role not found.');

        $rolePerms  = $roleModel->getPermissions($id);
        $rolePermIds = array_column($rolePerms, 'id');

        return view('admin/role_form', $this->setPageData([
            'page_title'       => 'Edit Role – CoreLynk',
            'role'             => $role,
            'rolePermIds'      => $rolePermIds,
            'permissionGroups' => $permModel->groupedByModule(),
        ]));
    }

    public function updateRole(int $id)
    {
        $this->requireAuth();
        $roleModel = new RoleModel();

        $role = $roleModel->find($id);
        if (!$role) return redirect()->to('/admin/roles')->with('error', 'Role not found.');

        $roleModel->update($id, [
            'name'        => $this->request->getPost('name'),
            'description' => $this->request->getPost('description'),
        ]);

        $permIds = array_map('intval', (array) ($this->request->getPost('permission_ids') ?: []));
        if ($this->request->getPost('products_sensitive_overview')) {
            $permModel = new PermissionModel();
            $p = $permModel->where('module', 'products')->where('action', 'sensitive_overview')->first();
            if (!empty($p['id'])) {
                $permIds[] = (int)$p['id'];
            }
        }
        $roleModel->syncPermissions($id, $permIds);

        AuditLogModel::record('role_updated', (int) $this->session->get('user_id'), 'roles', $id, [
            'name'        => $this->request->getPost('name'),
            'permissions' => $permIds,
        ]);

        return redirect()->to('/admin/roles')->with('success', 'Role updated successfully.');
    }

    public function deleteRole(int $id)
    {
        $this->requireAuth();
        $roleModel = new RoleModel();
        $role = $roleModel->find($id);
        if (!$role) return redirect()->to('/admin/roles')->with('error', 'Role not found.');

        if ($role['is_system']) {
            return redirect()->to('/admin/roles')->with('error', 'Cannot delete system roles.');
        }

        $roleModel->delete($id);

        AuditLogModel::record('role_deleted', (int) $this->session->get('user_id'), 'roles', $id, [
            'name' => $role['name'],
        ]);

        return redirect()->to('/admin/roles')->with('success', 'Role deleted.');
    }

    // ==================================================================
    //  FIELD-LEVEL PERMISSIONS
    // ==================================================================

    public function fieldPermissions(int $roleId)
    {
        $this->requireAuth();
        $roleModel = new RoleModel();
        $fpModel   = new FieldPermissionModel();

        $role = $roleModel->find($roleId);
        if (!$role) return redirect()->to('/admin/roles')->with('error', 'Role not found.');

        // Define the modules and fields that are maskable
        $maskableFields = $this->getMaskableFields();

        // Current rules
        $currentRules = [];
        foreach (array_keys($maskableFields) as $module) {
            $currentRules[$module] = $fpModel->getRules($roleId, $module);
        }

        return view('admin/field_permissions', $this->setPageData([
            'page_title'     => 'Field Permissions: ' . $role['name'] . ' – CoreLynk',
            'role'           => $role,
            'maskableFields' => $maskableFields,
            'currentRules'   => $currentRules,
        ]));
    }

    public function dataAccess(int $roleId)
    {
        $this->requireAuth();
        $roleModel = new RoleModel();
        $role = $roleModel->find($roleId);
        if (!$role) {
            return redirect()->to('/admin/roles')->with('error', 'Role not found.');
        }

        $model = new RoleDataAccessModel();
        try {
            $model->ensureSchema();
        } catch (\Throwable $e) {
            return redirect()->to('/admin/roles')->with('error', 'Unable to initialize data access controls: ' . $e->getMessage());
        }

        $existing = $model->getForRole($roleId);
        $defaults = [
            'dashboard_sales_visible' => 1,
            'dashboard_purchases_visible' => 1,
            'dashboard_finance_visible' => 1,
            'isolate_quotations' => 0,
            'isolate_sales_orders' => 0,
            'isolate_purchase_orders' => 0,
            'product_hide_services' => 0,
            'product_allowed_categories' => '',
        ];
        $settings = array_merge($defaults, $existing ?: []);

        // Product categories for the category-restriction picker
        $categoryModel = new \App\Models\ProductCategoryModel();
        $productCategories = $categoryModel->where('is_active', true)->orderBy('name')->findAll();

        // Parse saved category IDs into an array for the view
        $savedCategoryIds = array_filter(
            array_map('intval', explode(',', (string) ($settings['product_allowed_categories'] ?? ''))),
            fn($v) => $v > 0
        );

        return view('admin/data_access', $this->setPageData([
            'page_title' => 'Data Access: ' . $role['name'] . ' - CoreLynk',
            'role' => $role,
            'settings' => $settings,
            'productCategories' => $productCategories,
            'savedCategoryIds' => array_values($savedCategoryIds),
        ]));
    }

    public function saveDataAccess(int $roleId)
    {
        $this->requireAuth();
        $roleModel = new RoleModel();
        $role = $roleModel->find($roleId);
        if (!$role) {
            return redirect()->to('/admin/roles')->with('error', 'Role not found.');
        }

        $model = new RoleDataAccessModel();
        try {
            $model->ensureSchema();
        } catch (\Throwable $e) {
            return redirect()->to('/admin/roles')->with('error', 'Unable to initialize data access controls: ' . $e->getMessage());
        }

        $payload = [
            'dashboard_sales_visible' => $this->request->getPost('dashboard_sales_visible') ? 1 : 0,
            'dashboard_purchases_visible' => $this->request->getPost('dashboard_purchases_visible') ? 1 : 0,
            'dashboard_finance_visible' => $this->request->getPost('dashboard_finance_visible') ? 1 : 0,
            'isolate_quotations' => $this->request->getPost('isolate_quotations') ? 1 : 0,
            'isolate_sales_orders' => $this->request->getPost('isolate_sales_orders') ? 1 : 0,
            'isolate_purchase_orders' => $this->request->getPost('isolate_purchase_orders') ? 1 : 0,
            'product_hide_services' => $this->request->getPost('product_hide_services') ? 1 : 0,
            'product_allowed_categories' => implode(',', array_filter(
                array_map('intval', (array) ($this->request->getPost('product_allowed_categories') ?? [])),
                fn($v) => $v > 0
            )),
        ];

        $ok = $model->upsertForRole($roleId, $payload);
        if (! $ok) {
            return redirect()->to('/admin/roles/' . $roleId . '/data-access')->with('error', 'Failed to save data access controls.');
        }

        AuditLogModel::record('role_data_access_updated', (int) $this->session->get('user_id'), 'roles', $roleId, [
            'role' => $role['name'],
            'settings' => $payload,
        ]);

        return redirect()->to('/admin/roles/' . $roleId . '/data-access')->with('success', 'Data access controls saved.');
    }

    public function saveFieldPermissions(int $roleId)
    {
        $this->requireAuth();
        $fpModel   = new FieldPermissionModel();
        $roleModel = new RoleModel();

        $role = $roleModel->find($roleId);
        if (!$role) return redirect()->to('/admin/roles')->with('error', 'Role not found.');

        $maskableFields = $this->getMaskableFields();
        $posted = $this->request->getPost('fields') ?: [];

        foreach ($maskableFields as $module => $fields) {
            $rules = [];
            foreach ($fields as $fieldName => $label) {
                $vis = $posted[$module][$fieldName] ?? 'visible';
                if (in_array($vis, ['visible', 'masked', 'hidden'])) {
                    $rules[] = [
                        'field_name' => $fieldName,
                        'visibility' => $vis,
                        'mask_value' => '***',
                    ];
                }
            }
            $fpModel->saveRules($roleId, $module, $rules);
        }

        AuditLogModel::record('field_permissions_updated', (int) $this->session->get('user_id'), 'roles', $roleId, [
            'role' => $role['name'],
        ]);

        return redirect()->to("/admin/roles/{$roleId}/fields")->with('success', 'Field permissions saved.');
    }

    /**
     * Define which fields are maskable per module.
     */
    protected function getMaskableFields(): array
    {
        return [
            'invoices' => [
                'total_amount' => 'Invoice Total',
                'subtotal'     => 'Subtotal',
                'tax_amount'   => 'Tax Amount',
                'unit_price'   => 'Unit Price',
                'discount'     => 'Discount',
            ],
            'customers' => [
                'email'   => 'Email',
                'phone'   => 'Phone',
                'address' => 'Address',
                'city'    => 'City',
                'country' => 'Country',
            ],
            'vendors' => [
                'email'          => 'Email',
                'phone'          => 'Phone',
                'address'        => 'Address',
                'bank_account'   => 'Bank Account',
            ],
            'orders' => [
                'total_amount'  => 'Order Total',
                'unit_price'    => 'Unit Price',
                'profit_margin' => 'Profit Margin',
            ],
            'products' => [
                'cost_price'  => 'Cost Price',
                'vendor_price'=> 'Vendor Price',
            ],
        ];
    }

    // ==================================================================
    //  AUDIT LOG
    // ==================================================================

    public function auditLog()
    {
        $this->requireAuth();
        $logModel = new AuditLogModel();

        $action = $this->request->getGet('action');
        $userId = $this->request->getGet('user_id') ? (int) $this->request->getGet('user_id') : null;
        $from   = $this->request->getGet('from');
        $to     = $this->request->getGet('to');

        $logs  = $logModel->search($action, $userId, $from, $to, 50);
        $pager = $logModel->pager;

        // Users dropdown for filter
        $userModel = new UserModel();
        $usersDropdown = $userModel->getActiveUsersForDropdown();

        // Distinct actions for filter
        $db = \Config\Database::connect();
        $actionsList = $db->table('audit_log')
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->get()
            ->getResultArray();

        return view('admin/audit_log', $this->setPageData([
            'page_title'    => 'Audit Log – CoreLynk',
            'logs'          => $logs,
            'pager'         => $pager,
            'filter_action' => $action,
            'filter_user'   => $userId,
            'filter_from'   => $from,
            'filter_to'     => $to,
            'usersDropdown' => $usersDropdown,
            'actionsList'   => $actionsList,
        ]));
    }
}
