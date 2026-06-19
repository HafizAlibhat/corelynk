<?php

namespace App\Libraries;

use App\Models\FieldPermissionModel;
use App\Models\AuditLogModel;

/**
 * Centralised Policy Engine
 * -----------------------------------------------------------------
 * A single class that resolves "can this user do X?" and
 * "which fields should be masked for this user in module Y?"
 *
 * Usage (from any controller or service):
 *   $policy = service('policy');          // registered as a shared service
 *   $policy->can('invoices', 'read');     // bool
 *   $policy->mask($rows, 'invoices');     // returns rows with masked fields
 */
class PolicyEngine
{
    /** @var array|null Cached user record */
    protected ?array $user;

    /** @var array Cached list of role IDs for the user */
    protected array $roleIds = [];

    /** @var array Cached permission slugs  "module.action" */
    protected array $permSlugs = [];

    /** @var bool Admin flag — admins bypass all checks */
    protected bool $isAdmin = false;

    /** @var array Field-rule cache keyed by "roleId:module" */
    protected array $fieldCache = [];

    /** @var bool Whether init() has been called */
    protected bool $initialised = false;

    // ------------------------------------------------------------------
    //  Bootstrap
    // ------------------------------------------------------------------

    /**
     * Check if PolicyEngine has been initialised for this request.
     */
    public function isInitialised(): bool
    {
        return $this->initialised;
    }

    /**
     * Initialise with a user record (must contain at least 'id').
     * Call once per request – normally done in BaseController.
     */
    public function init(?array $user): void
    {
        $this->initialised = true;
        $this->user = $user;
        if (!$user || empty($user['id'])) {
            return;
        }

        $db = \Config\Database::connect();

        // Load role IDs
        $rows = $db->table('user_roles')
            ->where('user_id', (int) $user['id'])
            ->get()
            ->getResultArray();
        $this->roleIds = array_column($rows, 'role_id');

        // Fallback: if no user_roles row but user has role_id on users table
        if (empty($this->roleIds) && !empty($user['role_id'])) {
            $this->roleIds = [(int) $user['role_id']];
        }

        // Load permission slugs for all assigned roles
        if (!empty($this->roleIds)) {
            $perms = $db->table('role_permissions rp')
                ->join('permissions p', 'p.id = rp.permission_id')
                ->whereIn('rp.role_id', $this->roleIds)
                ->select('p.module, p.action')
                ->get()
                ->getResultArray();

            foreach ($perms as $p) {
                $this->permSlugs[$p['module'] . '.' . $p['action']] = true;
            }

            // Check if any role is the admin role
            $adminRole = $db->table('roles')
                ->where('slug', 'admin')
                ->get()
                ->getRowArray();
            if ($adminRole && in_array((int) $adminRole['id'], $this->roleIds, true)) {
                $this->isAdmin = true;
            }
        }

        // Legacy compat: if user['role'] === 'admin' treat as admin
        if (($user['role'] ?? '') === 'admin') {
            $this->isAdmin = true;
        }
    }

    // ------------------------------------------------------------------
    //  Module-Level Checks
    // ------------------------------------------------------------------

    /**
     * Can the current user perform $action on $module?
     */
    public function can(string $module, string $action = 'read'): bool
    {
        if ($this->isAdmin) return true;
        if (isset($this->permSlugs[$module . '.' . $action])) {
            return true;
        }

        // Backward-compatibility bridge:
        // some legacy roles were granted products.* but not product_assets.*.
        // Allow equivalent access for product asset routes without changing data.
        if ($module === 'product_assets') {
            $productAction = $action;
            if ($action === 'view') {
                $productAction = 'read';
            }
            return isset($this->permSlugs['products.' . $productAction]);
        }

        return false;
    }

    /**
     * Throw 403 if user lacks the given permission.
     */
    public function require(string $module, string $action = 'read'): void
    {
        if (!$this->can($module, $action)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound(
                'You do not have permission to access this resource.'
            );
        }
    }

    /**
     * Does user hold any of the given role slugs?
     */
    public function hasRole(string ...$slugs): bool
    {
        if ($this->isAdmin) return true;

        $db = \Config\Database::connect();
        $matchIds = $db->table('roles')
            ->whereIn('slug', $slugs)
            ->select('id')
            ->get()
            ->getResultArray();
        $matchIds = array_column($matchIds, 'id');

        return !empty(array_intersect($this->roleIds, $matchIds));
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function getRoleIds(): array
    {
        return $this->roleIds;
    }

    // ------------------------------------------------------------------
    //  Field-Level Masking
    // ------------------------------------------------------------------

    /**
     * Apply field-level masking to a single row.
     * Returns the row with sensitive fields replaced according to rules.
     */
    public function maskRow(array $row, string $module): array
    {
        if ($this->isAdmin || empty($this->roleIds)) {
            return $row;
        }

        $rules = $this->loadFieldRules($module);
        if (empty($rules)) return $row;

        foreach ($rules as $field => $rule) {
            if (!array_key_exists($field, $row)) continue;

            switch ($rule['visibility']) {
                case 'hidden':
                    unset($row[$field]);
                    break;
                case 'masked':
                    $row[$field] = $rule['mask_value'] ?? '***';
                    break;
                // 'visible' → no change
            }
        }
        return $row;
    }

    /**
     * Apply field-level masking to an array of rows.
     */
    public function mask(array $rows, string $module): array
    {
        if ($this->isAdmin) return $rows;
        return array_map(fn($r) => $this->maskRow($r, $module), $rows);
    }

    /**
     * Get the field rules map for the current user's roles on a module.
     * Merges rules from all roles: strictest rule wins.
     */
    protected function loadFieldRules(string $module): array
    {
        $cacheKey = implode(',', $this->roleIds) . ':' . $module;
        if (isset($this->fieldCache[$cacheKey])) {
            return $this->fieldCache[$cacheKey];
        }

        $fpModel = new FieldPermissionModel();
        $merged  = [];
        $priority = ['visible' => 0, 'masked' => 1, 'hidden' => 2];

        foreach ($this->roleIds as $rid) {
            $rules = $fpModel->getRules($rid, $module);
            foreach ($rules as $field => $rule) {
                $existing = $merged[$field]['visibility'] ?? 'visible';
                // Strictest wins
                if (($priority[$rule['visibility']] ?? 0) > ($priority[$existing] ?? 0)) {
                    $merged[$field] = $rule;
                }
            }
        }

        $this->fieldCache[$cacheKey] = $merged;
        return $merged;
    }

    /**
     * Return a list of hidden field names for the user + module.
     * Useful for conditionally hiding table columns in views.
     */
    public function hiddenFields(string $module): array
    {
        $rules = $this->loadFieldRules($module);
        $hidden = [];
        foreach ($rules as $field => $rule) {
            if ($rule['visibility'] === 'hidden') {
                $hidden[] = $field;
            }
        }
        return $hidden;
    }

    /**
     * Return a list of masked field names for the user + module.
     */
    public function maskedFields(string $module): array
    {
        $rules = $this->loadFieldRules($module);
        $masked = [];
        foreach ($rules as $field => $rule) {
            if ($rule['visibility'] === 'masked') {
                $masked[] = $field;
            }
        }
        return $masked;
    }

    /**
     * Check if a specific field is visible for the user + module.
     */
    public function isFieldVisible(string $module, string $field): bool
    {
        $rules = $this->loadFieldRules($module);
        if (!isset($rules[$field])) return true;
        return $rules[$field]['visibility'] === 'visible';
    }
}
