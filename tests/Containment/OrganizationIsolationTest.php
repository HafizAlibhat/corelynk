<?php

namespace Tests\Containment;

use App\Services\OrganizationAccessService;
use PHPUnit\Framework\TestCase;

final class OrganizationIsolationTest extends TestCase
{
    private function root(string $path): string
    {
        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function testDatabaseIdentifiersAreStrictlyConstrained(): void
    {
        self::assertTrue(OrganizationAccessService::validDatabaseName('corelynk_tenant_01'));
        self::assertFalse(OrganizationAccessService::validDatabaseName('../other'));
        self::assertFalse(OrganizationAccessService::validDatabaseName('tenant`; DROP DATABASE x;--'));
        self::assertFalse(OrganizationAccessService::validDatabaseName(''));
    }

    public function testTenantFilterRequiresAuthorizedMembership(): void
    {
        $source = (string) file_get_contents($this->root('app/Filters/TenantContextFilter.php'));
        self::assertStringContainsString('authorizedMembership($accountId, $organizationId)', $source);
        self::assertStringContainsString("remove(['organization_id', 'organization_name', 'user_id', 'logged_in'])", $source);
        self::assertStringNotContainsString('getGet(', $source);
        self::assertStringNotContainsString('getPost(', $source);
    }

    public function testCompanySwitchIsServerAuthorizedAndSessionRotated(): void
    {
        $source = (string) file_get_contents($this->root('app/Controllers/Companies.php'));
        self::assertStringContainsString('membershipsForAccount($accountId)', $source);
        self::assertStringContainsString('hash_equals(', $source);
        self::assertStringContainsString('regenerate(true)', $source);
    }

    public function testExistingMasterPasswordIsNotOverwrittenWhenAccessIsGranted(): void
    {
        $source = (string) file_get_contents($this->root('app/Services/OrganizationAccessService.php'));
        $existingBranch = substr($source, (int) strpos($source, '$account ='));
        self::assertStringContainsString('if (! $account)', $existingBranch);
        self::assertSame(1, substr_count($existingBranch, "'password_hash' =>"));
    }

    public function testProvisioningIsCliOnlyAndUsesCanonicalSchema(): void
    {
        $command = (string) file_get_contents($this->root('app/Commands/ProvisionCompany.php'));
        $controller = (string) file_get_contents($this->root('app/Controllers/Companies.php'));
        self::assertStringContainsString("protected \$name = 'company:provision'", $command);
        self::assertStringContainsString('installFreshSchemaForDatabase', $command);
        self::assertStringNotContainsString('CREATE DATABASE', $controller);
        self::assertStringContainsString("'status' => 'provisioning'", $controller);
    }

    public function testSnapshotInstallHasTargetedIdempotentControlPlaneBootstrap(): void
    {
        $installer = (string) file_get_contents($this->root('app/Services/ControlPlaneInstaller.php'));
        $setup = (string) file_get_contents($this->root('app/Controllers/Setup.php'));
        self::assertStringContainsString("tableExists('password_reset_tokens')", $installer);
        self::assertStringContainsString('new CreateOrganizationControlPlane($forge)', $installer);
        self::assertStringContainsString('ControlPlaneInstaller())->install()', $setup);
        self::assertStringContainsString("str_starts_with((string) \$db->getDatabase(), 'corelynk_controlplane_qa_')", $installer);
    }
}
