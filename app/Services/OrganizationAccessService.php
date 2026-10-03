<?php

namespace App\Services;

use Config\Database;

class OrganizationAccessService
{
    public function controlDb()
    {
        return Database::connect('controlRuntime');
    }

    public function membershipsForAccount(int $accountId): array
    {
        return $this->controlDb()->table('organization_memberships m')
            ->join('organizations o', 'o.id = m.organization_id')
            ->where('m.account_id', $accountId)->where('m.is_active', 1)->where('o.status', 'active')
            ->select('m.organization_id, m.local_user_id, m.access_role, o.public_id, o.name, o.slug, o.database_name')
            ->orderBy('o.name', 'ASC')->get()->getResultArray();
    }

    public function authorizedMembership(int $accountId, int $organizationId): ?array
    {
        $row = $this->controlDb()->table('organization_memberships m')
            ->join('organizations o', 'o.id = m.organization_id')
            ->where('m.account_id', $accountId)->where('m.organization_id', $organizationId)
            ->where('m.is_active', 1)->where('o.status', 'active')
            ->select('m.organization_id, m.local_user_id, m.access_role, o.public_id, o.name, o.slug, o.database_name')
            ->get()->getRowArray();
        return $row ?: null;
    }

    public function provisioningForAccount(int $accountId): array
    {
        return $this->controlDb()->table('organizations')
            ->where('created_by_account_id', $accountId)
            ->whereIn('status', ['provisioning', 'processing', 'failed'])
            ->select('public_id, name, status, created_at')->orderBy('created_at', 'DESC')->get()->getResultArray();
    }

    public static function validDatabaseName(string $name): bool
    {
        return (bool) preg_match('/^[a-zA-Z0-9_]{1,64}$/', $name);
    }

    public function linkLocalUser(int $organizationId, array $user, string $accessRole = 'member'): int
    {
        $email = strtolower(trim((string) ($user['email'] ?? '')));
        if ($organizationId <= 0 || ! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($user['password_hash']) || empty($user['id'])) {
            throw new \InvalidArgumentException('A valid organization and local user are required.');
        }
        if (! in_array($accessRole, ['owner', 'admin', 'member'], true)) {
            $accessRole = 'member';
        }

        $db = $this->controlDb();
        $db->transBegin();
        try {
            $account = $db->table('identity_accounts')->where('email', $email)->get()->getRowArray();
            if (! $account) {
                $db->table('identity_accounts')->insert([
                    'email' => $email,
                    'password_hash' => (string) $user['password_hash'],
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'last_name' => (string) ($user['last_name'] ?? ''),
                    'is_active' => 1,
                    'failed_login_count' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
                $accountId = (int) $db->insertID();
            } else {
                $accountId = (int) $account['id'];
            }

            $membership = $db->table('organization_memberships')
                ->where('organization_id', $organizationId)->where('account_id', $accountId)->get()->getRowArray();
            $membershipData = [
                'local_user_id' => (int) $user['id'],
                'access_role' => $accessRole,
                'is_active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if ($membership) {
                $db->table('organization_memberships')->where('id', (int) $membership['id'])->update($membershipData);
            } else {
                $membershipData += ['organization_id' => $organizationId, 'account_id' => $accountId, 'created_at' => date('Y-m-d H:i:s')];
                $db->table('organization_memberships')->insert($membershipData);
            }
            $db->transCommit();
            return $accountId;
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }
}
