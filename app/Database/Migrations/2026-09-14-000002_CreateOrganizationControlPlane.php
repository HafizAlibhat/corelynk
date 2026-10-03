<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrganizationControlPlane extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 254],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'first_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'last_name' => ['type' => 'VARCHAR', 'constraint' => 80],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'failed_login_count' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'locked_until' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email', 'uq_identity_account_email');
        $this->forge->createTable('identity_accounts', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'public_id' => ['type' => 'CHAR', 'constraint' => 36],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 80],
            'database_name' => ['type' => 'VARCHAR', 'constraint' => 64],
            'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'active'],
            'created_by_account_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('public_id', 'uq_organization_public_id');
        $this->forge->addUniqueKey('slug', 'uq_organization_slug');
        $this->forge->addUniqueKey('database_name', 'uq_organization_database');
        $this->forge->createTable('organizations', true);

        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'organization_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'account_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'local_user_id' => ['type' => 'INT', 'unsigned' => true],
            'access_role' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'member'],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME'],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['organization_id', 'account_id'], 'uq_organization_account');
        $this->forge->addKey(['account_id', 'is_active'], false, false, 'idx_membership_account_active');
        $this->forge->createTable('organization_memberships', true);

        $this->backfillExistingInstallation();
    }

    public function down()
    {
        $this->forge->dropTable('organization_memberships', true);
        $this->forge->dropTable('organizations', true);
        $this->forge->dropTable('identity_accounts', true);
    }

    private function backfillExistingInstallation(): void
    {
        if (! $this->db->tableExists('users')) {
            return;
        }

        $company = $this->db->tableExists('company_settings')
            ? $this->db->table('company_settings')->orderBy('id', 'ASC')->get()->getRowArray()
            : null;
        $name = trim((string) ($company['name'] ?? 'My Company')) ?: 'My Company';
        $slugBase = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-')) ?: 'company';
        $databaseName = (string) $this->db->getDatabase();
        $now = date('Y-m-d H:i:s');

        $existingOrganization = $this->db->table('organizations')->where('database_name', $databaseName)->get()->getRowArray();
        if ($existingOrganization) {
            $organizationId = (int) $existingOrganization['id'];
        } else {
            $this->db->table('organizations')->insert([
                'public_id' => $this->uuidV4(),
                'name' => $name,
                'slug' => substr($slugBase . '-' . substr(hash('sha256', $databaseName), 0, 8), 0, 80),
                'database_name' => $databaseName,
                'status' => 'active',
                'created_at' => $now,
            ]);
            $organizationId = (int) $this->db->insertID();
        }

        $users = $this->db->table('users')->where('is_active', 1)->get()->getResultArray();
        $ownerAssigned = false;
        foreach ($users as $user) {
            $email = strtolower(trim((string) ($user['email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || empty($user['password_hash'])) {
                continue;
            }
            $account = $this->db->table('identity_accounts')->where('email', $email)->get()->getRowArray();
            if (! $account) {
                $this->db->table('identity_accounts')->insert([
                    'email' => $email,
                    'password_hash' => (string) $user['password_hash'],
                    'first_name' => (string) ($user['first_name'] ?? ''),
                    'last_name' => (string) ($user['last_name'] ?? ''),
                    'is_active' => 1,
                    'failed_login_count' => (int) ($user['failed_login_count'] ?? 0),
                    'locked_until' => $user['locked_until'] ?? null,
                    'created_at' => $now,
                ]);
                $accountId = (int) $this->db->insertID();
            } else {
                $accountId = (int) $account['id'];
            }
            $isAdmin = (($user['role'] ?? '') === 'admin');
            $accessRole = ! $ownerAssigned && $isAdmin ? 'owner' : ($isAdmin ? 'admin' : 'member');
            $ownerAssigned = $ownerAssigned || $accessRole === 'owner';
            $membershipExists = $this->db->table('organization_memberships')
                ->where('organization_id', $organizationId)->where('account_id', $accountId)->countAllResults() > 0;
            if (! $membershipExists) {
                $this->db->table('organization_memberships')->insert([
                    'organization_id' => $organizationId,
                    'account_id' => $accountId,
                    'local_user_id' => (int) $user['id'],
                    'access_role' => $accessRole,
                    'is_active' => 1,
                    'created_at' => $now,
                ]);
            }
        }
    }

    private function uuidV4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
