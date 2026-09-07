<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Admin-controlled feature flags.
 *
 * Each flag is a simple key → boolean toggle stored in the `feature_flags` table.
 * Results are cached per-request to avoid repeated DB hits.
 */
class FeatureFlagModel extends Model
{
    protected $table            = 'feature_flags';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['flag_key', 'enabled', 'description', 'updated_at'];
    protected $useTimestamps    = false;

    /** Per-request cache of flag values. */
    private static array $cache = [];
    private static bool $allLoaded = false;

    // ── Known flag keys ──────────────────────────────────────────
    public const FLAG_ENABLE_2FA             = 'enable_2fa';
    public const FLAG_FORCE_HTTPS            = 'force_https';
    public const FLAG_ENABLE_RATE_LIMIT      = 'enable_rate_limit';
    public const FLAG_ENABLE_AUTH_LOGGING    = 'enable_auth_logging';
    public const FLAG_ENABLE_CSRF            = 'enable_csrf';
    public const FLAG_ENABLE_PUBLIC_IDS      = 'enable_public_ids';
    public const FLAG_ENABLE_TENANT_ISOLATION = 'enable_tenant_isolation';

    /**
     * Check if a feature flag is enabled.
     *
     * Returns false if the flag doesn't exist or the table is missing,
     * ensuring safe degradation before migration is run.
     */
    public static function isEnabled(string $flagKey): bool
    {
        if (array_key_exists($flagKey, self::$cache)) {
            return self::$cache[$flagKey];
        }

        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('feature_flags')) {
                return self::$cache[$flagKey] = false;
            }
            if (!self::$allLoaded) {
                $rows = $db->table('feature_flags')->select('flag_key, enabled')->get()->getResultArray();
                foreach ($rows as $row) {
                    if (!empty($row['flag_key'])) {
                        self::$cache[(string)$row['flag_key']] = (bool)($row['enabled'] ?? 0);
                    }
                }
                self::$allLoaded = true;
            }

            return self::$cache[$flagKey] ?? false;
        } catch (\Throwable) {
            return self::$cache[$flagKey] = false;
        }
    }

    /**
     * Set a feature flag value (admin use).
     */
    public function setFlag(string $flagKey, bool $enabled): bool
    {
        $existing = $this->where('flag_key', $flagKey)->first();
        self::$cache[$flagKey] = $enabled;

        if ($existing) {
            return $this->update($existing['id'], [
                'enabled'    => $enabled ? 1 : 0,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return (bool) $this->insert([
            'flag_key'    => $flagKey,
            'enabled'     => $enabled ? 1 : 0,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all flags as key => bool array.
     */
    public function getAllFlags(): array
    {
        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('feature_flags')) {
                return [];
            }
            $rows = $this->findAll();
            $flags = [];
            foreach ($rows as $row) {
                $flags[$row['flag_key']] = [
                    'enabled'     => (bool) $row['enabled'],
                    'description' => $row['description'] ?? '',
                ];
            }
            return $flags;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Clear the per-request cache (for testing or after admin toggle).
     */
    public static function clearCache(): void
    {
        self::$cache = [];
        self::$allLoaded = false;
    }
}
