<?php

namespace Tests\Containment;

use PHPUnit\Framework\TestCase;

final class PasswordResetSecurityTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function testResetFlowUsesHashedExpiringSingleUseTokens(): void
    {
        $source = (string) file_get_contents($this->root('app/Controllers/Auth.php'));
        self::assertStringContainsString("hash('sha256', \$plainToken)", $source);
        self::assertStringContainsString('expires_at > ?', $source);
        self::assertStringContainsString('FOR UPDATE', $source);
        self::assertStringContainsString("Database::connect('controlRuntime')", $source);
        self::assertStringContainsString("'account_id' => \$account ?", $source);
        self::assertStringContainsString('Password updated successfully. You can now sign in.', $source);
    }

    public function testResetArtifactsExist(): void
    {
        self::assertFileExists($this->root('app/Views/auth/forgot_password.php'));
        self::assertFileExists($this->root('app/Views/auth/reset_password.php'));
        self::assertFileExists($this->root('app/Database/Migrations/2026-09-14-000001_CreatePasswordResetTokens.php'));
    }
}
