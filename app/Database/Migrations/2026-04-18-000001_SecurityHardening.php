<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Security hardening migration.
 *
 * Creates:
 *  - auth_logs          – dedicated authentication event log
 *  - feature_flags      – admin-controlled feature toggles
 *
 * Adds:
 *  - public_id (CHAR 36) to key entity tables (for URL-safe identifiers)
 *  - tenant_id (INT)     to users table (multi-tenancy preparation)
 *
 * All ALTER TABLE operations are guarded with column-existence checks
 * so the migration is safe to re-run.
 */
class SecurityHardening extends Migration
{
    // Tables that will receive a public_id column
    private array $publicIdTables = [
        'vendors',
        'customers',
        'products',
        'purchase_orders',
        'sales_orders',
        'quotations',
        'invoices',
        'vendor_bills',
    ];

    public function up()
    {
        // ── 1. auth_logs ─────────────────────────────────────────
        if (! $this->db->tableExists('auth_logs')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'action' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'comment'    => 'login_success, login_failed, logout, mfa_verify, password_changed',
                ],
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                ],
                'ip_address' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 45,
                ],
                'user_agent' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'details' => [
                    'type' => 'JSON',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('user_id');
            $this->forge->addKey('action');
            $this->forge->addKey('created_at');
            $this->forge->addKey(['email', 'created_at'], false, false, 'idx_auth_logs_email_date');
            $this->forge->createTable('auth_logs', true);
        }

        // ── 2. feature_flags ─────────────────────────────────────
        if (! $this->db->tableExists('feature_flags')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 10,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'flag_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 80,
                ],
                'enabled' => [
                    'type'       => 'TINYINT',
                    'constraint' => 1,
                    'default'    => 0,
                ],
                'description' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('flag_key', false, true); // unique
            $this->forge->createTable('feature_flags', true);

            // Seed default flags (all off for safety)
            $this->db->table('feature_flags')->insertBatch([
                ['flag_key' => 'enable_2fa',            'enabled' => 0, 'description' => 'Enforce two-factor authentication for all users',      'updated_at' => date('Y-m-d H:i:s')],
                ['flag_key' => 'force_https',           'enabled' => 0, 'description' => 'Redirect all HTTP requests to HTTPS',                  'updated_at' => date('Y-m-d H:i:s')],
                ['flag_key' => 'enable_rate_limit',     'enabled' => 1, 'description' => 'Enable login rate limiting (already active by code)',   'updated_at' => date('Y-m-d H:i:s')],
                ['flag_key' => 'enable_auth_logging',   'enabled' => 1, 'description' => 'Log authentication events to auth_logs table',         'updated_at' => date('Y-m-d H:i:s')],
                ['flag_key' => 'enable_csrf',           'enabled' => 0, 'description' => 'Enable CSRF protection (requires form token support)', 'updated_at' => date('Y-m-d H:i:s')],
                ['flag_key' => 'enable_public_ids',     'enabled' => 0, 'description' => 'Use public_id (UUID) in URLs instead of numeric IDs',  'updated_at' => date('Y-m-d H:i:s')],
                ['flag_key' => 'enable_tenant_isolation','enabled' => 0, 'description' => 'Enforce tenant_id isolation on queries',               'updated_at' => date('Y-m-d H:i:s')],
            ]);
        }

        // ── 3. public_id columns on key entity tables ────────────
        foreach ($this->publicIdTables as $table) {
            if ($this->db->tableExists($table) && ! $this->db->fieldExists('public_id', $table)) {
                $this->forge->addColumn($table, [
                    'public_id' => [
                        'type'       => 'CHAR',
                        'constraint' => 36,
                        'null'       => true,
                        'after'      => 'id',
                    ],
                ]);
                // Add unique index
                $this->db->query("CREATE UNIQUE INDEX idx_{$table}_public_id ON `{$table}` (`public_id`)");
            }
        }

        // ── 4. tenant_id on users (preparation) ──────────────────
        if ($this->db->tableExists('users') && ! $this->db->fieldExists('tenant_id', 'users')) {
            $this->forge->addColumn('users', [
                'tenant_id' => [
                    'type'       => 'INT',
                    'constraint' => 10,
                    'unsigned'   => true,
                    'null'       => true,
                    'default'    => 1,
                    'after'      => 'id',
                    'comment'    => 'Tenant isolation — default 1 for single-tenant installs',
                ],
            ]);
        }
    }

    public function down()
    {
        // Remove public_id columns
        foreach ($this->publicIdTables as $table) {
            if ($this->db->tableExists($table) && $this->db->fieldExists('public_id', $table)) {
                $this->forge->dropColumn($table, 'public_id');
            }
        }

        // Remove tenant_id from users
        if ($this->db->tableExists('users') && $this->db->fieldExists('tenant_id', 'users')) {
            $this->forge->dropColumn('users', 'tenant_id');
        }

        $this->forge->dropTable('feature_flags', true);
        $this->forge->dropTable('auth_logs', true);
    }
}
