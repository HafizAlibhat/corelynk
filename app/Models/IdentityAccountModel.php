<?php

namespace App\Models;

use CodeIgniter\Model;

class IdentityAccountModel extends Model
{
    protected $DBGroup = 'controlRuntime';
    protected $table = 'identity_accounts';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = false;
    protected $allowedFields = ['email', 'password_hash', 'first_name', 'last_name', 'is_active', 'failed_login_count', 'locked_until', 'created_at', 'updated_at'];

    public function verifyCredentials(string $email, string $password): array|false
    {
        $account = $this->where('email', strtolower(trim($email)))->where('is_active', 1)->first();
        if ($account && ! empty($account['locked_until']) && strtotime((string) $account['locked_until']) > time()) {
            return false;
        }
        if (! $account || ! password_verify($password, (string) $account['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string) $account['password_hash'], PASSWORD_DEFAULT)) {
            $this->update((int) $account['id'], ['password_hash' => password_hash($password, PASSWORD_DEFAULT), 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->update((int) $account['id'], ['failed_login_count' => 0, 'locked_until' => null, 'updated_at' => date('Y-m-d H:i:s')]);
        unset($account['password_hash']);
        return $account;
    }

    public function recordFailedLogin(string $email, int $maxAttempts = 5, int $lockoutMinutes = 15): void
    {
        $account = $this->where('email', strtolower(trim($email)))->first();
        if (! $account) {
            return;
        }
        $count = ((int) ($account['failed_login_count'] ?? 0)) + 1;
        $data = ['failed_login_count' => $count, 'updated_at' => date('Y-m-d H:i:s')];
        if ($count >= $maxAttempts) {
            $data['locked_until'] = date('Y-m-d H:i:s', strtotime("+{$lockoutMinutes} minutes"));
        }
        $this->update((int) $account['id'], $data);
    }
}
