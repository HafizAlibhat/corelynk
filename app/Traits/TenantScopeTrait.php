<?php

namespace App\Traits;

use App\Models\FeatureFlagModel;

/**
 * Tenant isolation trait for models.
 *
 * When the `enable_tenant_isolation` feature flag is ON, this trait
 * automatically scopes all SELECT queries to the current user's tenant.
 *
 * Usage:
 *   class VendorModel extends Model {
 *       use TenantScopeTrait;
 *   }
 *
 * The trait hooks into CI4's model event system via `beforeFind` and
 * `beforeInsert`. Existing queries are NOT broken — the scope is only
 * applied when the feature flag is enabled AND a tenant_id column exists
 * on the table.
 */
trait TenantScopeTrait
{
    /**
     * Boot the tenant scope. Call this in the model constructor AFTER parent::__construct().
     */
    protected function bootTenantScope(): void
    {
        // Only activate when feature flag is on
        if (! FeatureFlagModel::isEnabled(FeatureFlagModel::FLAG_ENABLE_TENANT_ISOLATION)) {
            return;
        }

        // Only scope tables that actually have a tenant_id column
        try {
            if (! $this->db->fieldExists('tenant_id', $this->table)) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        // Register callbacks
        $this->beforeFind[]   = 'applyTenantScope';
        $this->beforeInsert[] = 'injectTenantId';
        $this->beforeUpdate[] = 'injectTenantId';
    }

    /**
     * Apply WHERE tenant_id = ? to find queries.
     */
    protected function applyTenantScope(array $data): array
    {
        $tenantId = self::getCurrentTenantId();
        if ($tenantId !== null) {
            $builder = $data['builder'] ?? null;
            if ($builder && method_exists($builder, 'where')) {
                $builder->where($this->table . '.tenant_id', $tenantId);
            }
        }
        return $data;
    }

    /**
     * Auto-inject tenant_id on insert/update if not already set.
     */
    protected function injectTenantId(array $data): array
    {
        $tenantId = self::getCurrentTenantId();
        if ($tenantId !== null) {
            if (isset($data['data']) && is_array($data['data'])) {
                $data['data']['tenant_id'] = $data['data']['tenant_id'] ?? $tenantId;
            }
        }
        return $data;
    }

    /**
     * Get the current user's tenant ID from session.
     *
     * Returns null when not logged in or tenant isolation is off,
     * which causes the scope to be skipped (backward compatible).
     */
    public static function getCurrentTenantId(): ?int
    {
        $session = session();
        $tenantId = $session->get('tenant_id');
        return $tenantId !== null ? (int) $tenantId : null;
    }
}
