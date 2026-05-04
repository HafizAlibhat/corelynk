<?php

namespace App\Libraries;

use App\Models\RoleDataAccessModel;
use Config\Database;

class RoleDataAccess
{
    private array $defaults = [
        'dashboard_sales_visible' => true,
        'dashboard_purchases_visible' => true,
        'dashboard_finance_visible' => true,
        'isolate_quotations' => false,
        'isolate_sales_orders' => false,
        'isolate_purchase_orders' => false,
    ];

    public function resolveForUser(?int $userId): array
    {
        $userId = (int) ($userId ?? 0);
        if ($userId <= 0) {
            return $this->defaults;
        }

        $model = new RoleDataAccessModel();
        try {
            $model->ensureSchema();
        } catch (\Throwable $_) {
            return $this->defaults;
        }

        $roleIds = $this->resolveRoleIds($userId);
        if (empty($roleIds)) {
            return $this->defaults;
        }

        $rows = $model->whereIn('role_id', $roleIds)->findAll();
        if (empty($rows)) {
            return $this->defaults;
        }

        // Restrictive merge for visibility, permissive merge for isolation.
        $merged = $this->defaults;
        foreach ($rows as $row) {
            $merged['dashboard_sales_visible'] = $merged['dashboard_sales_visible'] && ((int) ($row['dashboard_sales_visible'] ?? 1) === 1);
            $merged['dashboard_purchases_visible'] = $merged['dashboard_purchases_visible'] && ((int) ($row['dashboard_purchases_visible'] ?? 1) === 1);
            $merged['dashboard_finance_visible'] = $merged['dashboard_finance_visible'] && ((int) ($row['dashboard_finance_visible'] ?? 1) === 1);

            $merged['isolate_quotations'] = $merged['isolate_quotations'] || ((int) ($row['isolate_quotations'] ?? 0) === 1);
            $merged['isolate_sales_orders'] = $merged['isolate_sales_orders'] || ((int) ($row['isolate_sales_orders'] ?? 0) === 1);
            $merged['isolate_purchase_orders'] = $merged['isolate_purchase_orders'] || ((int) ($row['isolate_purchase_orders'] ?? 0) === 1);
        }

        return $merged;
    }

    public function shouldIsolate(string $module, ?int $userId): bool
    {
        $cfg = $this->resolveForUser($userId);

        return match (strtolower(trim($module))) {
            'quotation', 'quotations' => (bool) ($cfg['isolate_quotations'] ?? false),
            'sales_order', 'sales_orders' => (bool) ($cfg['isolate_sales_orders'] ?? false),
            'purchase_order', 'purchase_orders' => (bool) ($cfg['isolate_purchase_orders'] ?? false),
            default => false,
        };
    }

    /**
     * Returns true if the admin has granted this specific user the ability to hide documents.
     * This is a per-user flag (users.can_make_documents_private), NOT a role setting.
     */
    public function canMakeDocumentsPrivate(?int $userId): bool
    {
        if (!$userId || $userId <= 0) {
            return false;
        }
        try {
            $db = Database::connect();
            if (! $db->fieldExists('can_make_documents_private', 'users')) {
                return false;
            }
            $row = $db->table('users')
                ->select('can_make_documents_private')
                ->where('id', $userId)
                ->get()->getRowArray();
            return (bool) ((int) ($row['can_make_documents_private'] ?? 0));
        } catch (\Throwable $_) {
            return false;
        }
    }

    /**
     * Returns an array of user IDs whose documents should be hidden from the current user.
     * Returns empty array if current user is admin (admins see everything).
     * Excludes current user from the result (users always see their own docs).
     *
     * @param int|null $currentUserId
     * @param bool     $isAdmin
     * @return int[]
     */
    public function getPrivateUserIds(?int $currentUserId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return [];
        }

        $db = Database::connect();
        try {
            if (! $db->fieldExists('documents_private', 'users')) {
                return [];
            }
            $rows = $db->table('users')
                ->select('id')
                ->where('documents_private', 1)
                ->get()
                ->getResultArray();
            $ids = array_map('intval', array_column($rows, 'id'));
            // Always exclude the current user — they can see their own docs
            if ($currentUserId > 0) {
                $ids = array_values(array_filter($ids, fn($id) => $id !== (int) $currentUserId));
            }
            return $ids;
        } catch (\Throwable $_) {
            return [];
        }
    }

    private function resolveRoleIds(int $userId): array
    {
        $db = Database::connect();
        $ids = [];

        try {
            if ($db->tableExists('user_roles')) {
                $rows = $db->table('user_roles')->select('role_id')->where('user_id', $userId)->get()->getResultArray();
                foreach ($rows as $r) {
                    $rid = (int) ($r['role_id'] ?? 0);
                    if ($rid > 0) {
                        $ids[] = $rid;
                    }
                }
            }
        } catch (\Throwable $_) {
        }

        try {
            $u = $db->table('users')->select('role_id')->where('id', $userId)->get()->getRowArray();
            $legacyRoleId = (int) ($u['role_id'] ?? 0);
            if ($legacyRoleId > 0) {
                $ids[] = $legacyRoleId;
            }
        } catch (\Throwable $_) {
        }

        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        return $ids;
    }
}
