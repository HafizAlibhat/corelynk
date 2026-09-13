<?php

namespace App\Commands;

use App\Models\AuthLogModel;
use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Emergency account recovery when the only admin locks themselves out.
 * Deliberately CLI-only (server/container shell access required) instead
 * of a web-exposed "master password" — a single shared secret that can
 * reset any account is a much bigger blast radius if it ever leaks.
 *
 * Run: php spark user:reset-password admin@company.com NewPassword123
 */
class ResetUserPassword extends BaseCommand
{
    protected $group       = 'Auth';
    protected $name        = 'user:reset-password';
    protected $description = 'Reset a user\'s password by email (CLI-only account recovery).';
    protected $usage       = 'user:reset-password <email> <new_password>';

    public function run(array $params)
    {
        [$email, $newPassword] = $params + [null, null];

        if (!$email || !$newPassword) {
            CLI::error('Usage: php spark user:reset-password <email> <new_password>');
            return;
        }

        if (strlen($newPassword) < 8) {
            CLI::error('New password must be at least 8 characters.');
            return;
        }

        $userModel = new UserModel();
        $user      = $userModel->where('email', $email)->first();

        if (!$user) {
            CLI::error("No user found with email: {$email}");
            return;
        }

        $userModel->skipValidation(true)->update((int) $user['id'], [
            'password'           => $newPassword,
            'failed_login_count' => 0,
            'locked_until'       => null,
        ]);

        try {
            // Best-effort: auth/audit logging read the HTTP user-agent,
            // which doesn't exist under CLIRequest. The reset above must
            // never be blocked by that.
            AuthLogModel::record(AuthLogModel::ACTION_PASSWORD_CHANGED, (int) $user['id'], $user['email']);
        } catch (\Throwable $e) {
            CLI::write('(auth log skipped: ' . $e->getMessage() . ')', 'yellow');
        }

        CLI::write("Password reset for {$email}.", 'green');
    }
}
