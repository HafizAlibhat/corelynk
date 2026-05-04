<?php

namespace App\Models;

use CodeIgniter\Model;

class LoginAttemptModel extends Model
{
    protected $table            = 'login_attempts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['email', 'ip_address', 'user_agent', 'success'];

    protected $useTimestamps = false;

    /**
     * Record login attempt.
     */
    public function record(string $email, bool $success): void
    {
        $request = service('request');
        $this->insert([
            'email'      => $email,
            'ip_address' => $request->getIPAddress(),
            'user_agent' => substr((string) $request->getUserAgent(), 0, 255),
            'success'    => $success ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Count recent failed attempts for an email within the lockout window.
     */
    public function recentFailedCount(string $email, int $windowMinutes = 15): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        return $this->where('email', $email)
                    ->where('success', 0)
                    ->where('created_at >=', $since)
                    ->countAllResults();
    }

    /**
     * Count recent failed attempts from an IP within the lockout window.
     */
    public function recentFailedCountByIp(string $ip, int $windowMinutes = 15): int
    {
        $since = date('Y-m-d H:i:s', strtotime("-{$windowMinutes} minutes"));
        return $this->where('ip_address', $ip)
                    ->where('success', 0)
                    ->where('created_at >=', $since)
                    ->countAllResults();
    }

    /**
     * Is the email currently locked out? (5 failed attempts in 15 min).
     */
    public function isLockedOut(string $email, int $maxAttempts = 5, int $windowMinutes = 15): bool
    {
        return $this->recentFailedCount($email, $windowMinutes) >= $maxAttempts;
    }

    /**
     * Purge old records (> 30 days) to keep table small.
     */
    public function purgeOld(int $days = 30): int
    {
        $cutoff = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $this->where('created_at <', $cutoff)->delete();
    }
}
