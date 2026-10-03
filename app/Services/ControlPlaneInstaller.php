<?php

namespace App\Services;

use App\Database\Migrations\CreateOrganizationControlPlane;
use App\Database\Migrations\CreatePasswordResetTokens;
use Config\Database;

class ControlPlaneInstaller
{
    public function install(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-09-14-000001_CreatePasswordResetTokens.php';
        require_once APPPATH . 'Database/Migrations/2026-09-14-000002_CreateOrganizationControlPlane.php';
        $config = config('Database');
        $config->controlRuntime = $config->default;
        $db = Database::connect('controlRuntime');
        $forge = Database::forge('controlRuntime');
        if (! $db->tableExists('password_reset_tokens')) {
            (new CreatePasswordResetTokens($forge))->up();
        }
        if (! $db->tableExists('identity_accounts') || ! $db->tableExists('organizations') || ! $db->tableExists('organization_memberships')) {
            (new CreateOrganizationControlPlane($forge))->up();
        }
    }

    public function uninstallForDisposableTest(): void
    {
        require_once APPPATH . 'Database/Migrations/2026-09-14-000001_CreatePasswordResetTokens.php';
        require_once APPPATH . 'Database/Migrations/2026-09-14-000002_CreateOrganizationControlPlane.php';
        $config = config('Database');
        $config->controlRuntime = $config->default;
        $db = Database::connect('controlRuntime');
        if (! str_starts_with((string) $db->getDatabase(), 'corelynk_controlplane_qa_')) {
            throw new \RuntimeException('Refusing control-plane down outside a disposable QA database.');
        }
        $forge = Database::forge('controlRuntime');
        (new CreateOrganizationControlPlane($forge))->down();
        (new CreatePasswordResetTokens($forge))->down();
    }
}
