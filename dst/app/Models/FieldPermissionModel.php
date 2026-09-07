<?php

namespace App\Models;

use CodeIgniter\Model;

class FieldPermissionModel extends Model
{
    protected $table            = 'field_permissions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['role_id', 'module', 'field_name', 'visibility', 'mask_value'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    /**
     * Get all field rules for a role + module.
     * Returns an assoc array: field_name => ['visibility'=>..., 'mask_value'=>...]
     */
    public function getRules(int $roleId, string $module): array
    {
        $rows = $this->where('role_id', $roleId)
                     ->where('module', $module)
                     ->findAll();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['field_name']] = [
                'visibility' => $r['visibility'],
                'mask_value' => $r['mask_value'],
            ];
        }
        return $map;
    }

    /**
     * Bulk-save rules for role+module: expects array of
     * ['field_name' => ..., 'visibility' => ..., 'mask_value' => ...]
     */
    public function saveRules(int $roleId, string $module, array $rules): void
    {
        // Delete existing
        $this->where('role_id', $roleId)->where('module', $module)->delete();
        $now = date('Y-m-d H:i:s');
        foreach ($rules as $r) {
            if (($r['visibility'] ?? 'visible') === 'visible') continue; // no need to store "visible"
            $this->insert([
                'role_id'    => $roleId,
                'module'     => $module,
                'field_name' => $r['field_name'],
                'visibility' => $r['visibility'],
                'mask_value' => $r['visibility'] === 'masked' ? ($r['mask_value'] ?? '***') : null,
            ]);
        }
    }
}
