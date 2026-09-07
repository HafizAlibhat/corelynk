<?php

namespace App\Models;

use CodeIgniter\Model;

class ApiTokenModel extends Model
{
    protected $table         = 'api_tokens';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useSoftDeletes = false;
    protected $useTimestamps  = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';

    protected $allowedFields = [
        'user_id',
        'token_hash',
        'name',
        'expires_at',
        'last_used_at',
        'revoked',
    ];

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Generate a cryptographically-random token, persist its hash, and return
     * the **raw** (plain-text) token to the caller exactly once.
     *
     * @param int    $userId
     * @param string $name     Human-readable label for the token (optional)
     * @param int    $ttlDays  0 = never expires
     */
    public function createToken(int $userId, string $name = 'api', int $ttlDays = 0): string
    {
        $rawToken  = bin2hex(random_bytes(32)); // 64 hex chars = 256 bits of entropy
        $tokenHash = hash('sha256', $rawToken);

        $expiresAt = $ttlDays > 0
            ? date('Y-m-d H:i:s', strtotime("+{$ttlDays} days"))
            : null;

        $this->insert([
            'user_id'    => $userId,
            'token_hash' => $tokenHash,
            'name'       => $name,
            'expires_at' => $expiresAt,
            'revoked'    => 0,
        ]);

        return $rawToken;
    }

    /**
     * Look up a token row by the raw bearer value.
     * Returns the row (with `user_id`) or null if missing / revoked / expired.
     */
    public function findByRawToken(string $rawToken): ?array
    {
        $tokenHash = hash('sha256', $rawToken);

        $row = $this->where('token_hash', $tokenHash)
                    ->where('revoked', 0)
                    ->first();

        if ($row === null) {
            return null;
        }

        // Check expiry
        if ($row['expires_at'] !== null && strtotime($row['expires_at']) < time()) {
            return null;
        }

        return $row;
    }

    /**
     * Record that this token was used (non-blocking best-effort update).
     */
    public function touchLastUsed(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);
        $this->where('token_hash', $tokenHash)
             ->set('last_used_at', date('Y-m-d H:i:s'))
             ->update();
    }

    /**
     * Soft-revoke by the raw token value (used on logout).
     */
    public function revokeByRawToken(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);
        $this->where('token_hash', $tokenHash)->set('revoked', 1)->update();
    }

    /**
     * Revoke all tokens for a user (force-logout from all API clients).
     */
    public function revokeAllForUser(int $userId): void
    {
        $this->where('user_id', $userId)->set('revoked', 1)->update();
    }
}
