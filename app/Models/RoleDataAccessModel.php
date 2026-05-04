<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleDataAccessModel extends Model
{
    protected $table            = 'role_data_access';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;

    protected $allowedFields = [
        'role_id',
        'dashboard_sales_visible',
        'dashboard_purchases_visible',
        'dashboard_finance_visible',
        'isolate_quotations',
        'isolate_sales_orders',
        'isolate_purchase_orders',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function ensureSchema(): void
    {
        $db = $this->db;
        if (! $db->tableExists($this->table)) {
            $db->query(
                'CREATE TABLE role_data_access (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    role_id INT NOT NULL,
                    dashboard_sales_visible TINYINT(1) NOT NULL DEFAULT 1,
                    dashboard_purchases_visible TINYINT(1) NOT NULL DEFAULT 1,
                    dashboard_finance_visible TINYINT(1) NOT NULL DEFAULT 1,
                    isolate_quotations TINYINT(1) NOT NULL DEFAULT 0,
                    isolate_sales_orders TINYINT(1) NOT NULL DEFAULT 0,
                    isolate_purchase_orders TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NULL,
                    updated_at DATETIME NULL,
                    UNIQUE KEY uq_role_data_access_role (role_id),
                    KEY idx_role_data_access_role (role_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
            );
        }
    }

    public function getForRole(int $roleId): array
    {
        if ($roleId <= 0) {
            return [];
        }

        return $this->where('role_id', $roleId)->first() ?: [];
    }

    public function upsertForRole(int $roleId, array $payload): bool
    {
        if ($roleId <= 0) {
            return false;
        }

        $existing = $this->where('role_id', $roleId)->first();
        if ($existing) {
            return (bool) $this->update((int) $existing['id'], $payload);
        }

        $payload['role_id'] = $roleId;
        return (bool) $this->insert($payload);
    }
}
