<?php

namespace App\Commands;

use App\Database\Seeds\RbacSeeder;
use App\Models\UserModel;
use App\Services\OrganizationAccessService;
use App\Services\SystemBackupService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

class ProvisionCompany extends BaseCommand
{
    protected $group = 'SaaS';
    protected $name = 'company:provision';
    protected $description = 'Provision one approved company into its own isolated database.';
    protected $usage = 'company:provision <organization-public-id>';

    public function run(array $params)
    {
        $publicId = strtolower(trim((string) ($params[0] ?? '')));
        if (! preg_match('/^[a-f0-9-]{36}$/', $publicId)) {
            CLI::error('Usage: php spark company:provision <organization-public-id>');
            return;
        }

        $config = config('Database');
        $config->controlRuntime = $config->default;
        $controlDb = Database::connect('controlRuntime');
        $organization = $controlDb->table('organizations')->where('public_id', $publicId)->get()->getRowArray();
        if (! $organization || ! in_array($organization['status'], ['provisioning', 'failed'], true)) {
            CLI::error('No provisionable company request was found.');
            return;
        }
        $databaseName = (string) $organization['database_name'];
        if (! OrganizationAccessService::validDatabaseName($databaseName)) {
            CLI::error('Provisioning request contains an invalid database name.');
            return;
        }

        $account = $controlDb->table('identity_accounts')->where('id', (int) $organization['created_by_account_id'])->where('is_active', 1)->get()->getRowArray();
        if (! $account) {
            CLI::error('The company owner account is missing or inactive.');
            return;
        }

        $controlDb->table('organizations')->where('id', (int) $organization['id'])->update(['status' => 'processing', 'updated_at' => date('Y-m-d H:i:s')]);
        try {
            // Identifier is generated server-side and strictly validated above; values remain parameter-bound elsewhere.
            $controlDb->query('CREATE DATABASE `' . $databaseName . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $tenantConfig = array_merge($config->controlRuntime, ['database' => $databaseName]);
            $config->tenantProvisioning = $tenantConfig;
            (new SystemBackupService())->installFreshSchemaForDatabase($tenantConfig);

            $previousGroup = $config->defaultGroup;
            $config->defaultGroup = 'tenantProvisioning';
            try {
                Database::seeder('tenantProvisioning')->call(RbacSeeder::class);
                $tenantDb = Database::connect('tenantProvisioning');
                $tenantDb->table('company_settings')->insert(['name' => (string) $organization['name'], 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
                $adminRole = $tenantDb->table('roles')->where('slug', 'admin')->get()->getRowArray();
                if (! $adminRole) {
                    throw new \RuntimeException('Admin role was not seeded.');
                }
                $usernameBase = preg_replace('/[^a-z0-9._-]/i', '', strstr((string) $account['email'], '@', true) ?: 'owner') ?: 'owner';
                $userModel = new UserModel();
                $userModel->skipValidation(true);
                $localUserId = $userModel->insert([
                    'username' => substr($usernameBase, 0, 40),
                    'email' => (string) $account['email'],
                    'password_hash' => (string) $account['password_hash'],
                    'first_name' => (string) $account['first_name'],
                    'last_name' => (string) $account['last_name'],
                    'role' => 'admin',
                    'role_id' => (int) $adminRole['id'],
                    'is_active' => 1,
                ]);
                if (! $localUserId) {
                    throw new \RuntimeException('Owner user could not be created.');
                }
                $userModel->syncRoles((int) $localUserId, [(int) $adminRole['id']]);
            } finally {
                $config->defaultGroup = $previousGroup;
            }

            $controlDb->transStart();
            $controlDb->table('organization_memberships')->insert([
                'organization_id' => (int) $organization['id'],
                'account_id' => (int) $account['id'],
                'local_user_id' => (int) $localUserId,
                'access_role' => 'owner',
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $controlDb->table('organizations')->where('id', (int) $organization['id'])->update(['status' => 'active', 'updated_at' => date('Y-m-d H:i:s')]);
            $controlDb->transComplete();
            if (! $controlDb->transStatus()) {
                throw new \RuntimeException('Control-plane activation failed.');
            }
            CLI::write('Company provisioned: ' . $organization['name'], 'green');
        } catch (\Throwable $e) {
            $controlDb->table('organizations')->where('id', (int) $organization['id'])->update(['status' => 'failed', 'updated_at' => date('Y-m-d H:i:s')]);
            log_message('error', 'Company provisioning failed for {organization}: {message}', ['organization' => $publicId, 'message' => $e->getMessage()]);
            CLI::error('Company provisioning failed. Review the server log; the isolated database was left intact for diagnosis.');
        }
    }
}
