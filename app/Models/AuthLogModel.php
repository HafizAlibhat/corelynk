<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Dedicated authentication event log.
 *
 * Complements the existing audit_log + login_attempts tables with a
 * purpose-built auth-event table that is easy to query and purge.
 */
class AuthLogModel extends Model
{
    protected $table            = 'auth_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id', 'action', 'email', 'ip_address', 'user_agent', 'details',
    ];

    protected $useTimestamps = false; // we set created_at manually

    // Valid action constants
    public const ACTION_LOGIN_SUCCESS  = 'login_success';
    public const ACTION_LOGIN_FAILED   = 'login_failed';
    public const ACTION_LOGOUT         = 'logout';
    public const ACTION_MFA_VERIFY     = 'mfa_verify';
    public const ACTION_MFA_FAILED     = 'mfa_failed';
    public const ACTION_PASSWORD_CHANGED = 'password_changed';

    /**
     * Record an authentication event.
     *
     * @param string     $action   One of the ACTION_* constants
     * @param int|null   $userId   Authenticated user ID (null for failed logins)
     * @param string|null $email   Email address involved
     * @param array|null $extra    Additional context (JSON-serialised)
     */
    public static function record(string $action, ?int $userId = null, ?string $email = null, ?array $extra = null): void
    {
        // Respect the feature flag — skip logging when disabled
        if (! self::authLoggingEnabled()) {
            return;
        }

        $request = service('request');

        $db = \Config\Database::connect();
        $db->table('auth_logs')->insert([
            'user_id'    => $userId,
            'action'     => $action,
            'email'      => $email,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => substr((string) $request->getUserAgent(), 0, 255),
            'details'    => $extra ? json_encode($extra) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Check whether auth logging is enabled via feature flag.
     * Falls back to true if the feature_flags table doesn't exist yet
     * (migration hasn't run) to be safe.
     */
    private static function authLoggingEnabled(): bool
    {
        static $enabled = null;
        if ($enabled !== null) {
            return $enabled;
        }

        try {
            $db = \Config\Database::connect();
            if (! $db->tableExists('feature_flags')) {
                return $enabled = true; // no flag table → log by default
            }
            $row = $db->table('feature_flags')
                       ->where('flag_key', 'enable_auth_logging')
                       ->get()
                       ->getRowArray();
            return $enabled = ($row ? (bool) $row['enabled'] : true);
        } catch (\Throwable) {
            return $enabled = true;
        }
    }

    /**
     * Purge entries older than the given number of days.
     */
    public function purgeOld(int $days = 90): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->where('created_at <', $cutoff)->delete();
    }

    /**
     * Paginated search with optional filters.
     */
    public function search(?string $action = null, ?int $userId = null, ?string $from = null, ?string $to = null, int $perPage = 50)
    {
        $builder = $this->select('auth_logs.*, u.username, u.first_name, u.last_name')
                        ->join('users u', 'u.id = auth_logs.user_id', 'left')
                        ->orderBy('auth_logs.created_at', 'DESC');

        if ($action) $builder->where('auth_logs.action', $action);
        if ($userId) $builder->where('auth_logs.user_id', $userId);
        if ($from)   $builder->where('auth_logs.created_at >=', $from);
        if ($to)     $builder->where('auth_logs.created_at <=', $to . ' 23:59:59');

        return $builder->paginate($perPage);
    }
}
