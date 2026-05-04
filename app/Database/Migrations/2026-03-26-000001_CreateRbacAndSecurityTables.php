<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the full RBAC, field-level permissions, brute-force protection,
 * MFA, and audit-log tables for the CoreLynk User Management System.
 *
 * Existing `users` table is ALTER-ed; new tables are created.
 */
class CreateRbacAndSecurityTables extends Migration
{
    public function up()
    {
        /* ----------------------------------------------------------------
         * 1. ROLES
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 60, 'unique' => true],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 60, 'unique' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'is_system'   => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0, 'comment' => '1 = built-in, cannot be deleted'],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('roles', true);

        /* ----------------------------------------------------------------
         * 2. PERMISSIONS  (module + action granularity)
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 60, 'comment' => 'e.g. invoices, orders, inventory'],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 30, 'comment' => 'read / write / edit / delete'],
            'description' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['module', 'action'], 'uq_module_action');
        $this->forge->createTable('permissions', true);

        /* ----------------------------------------------------------------
         * 3. ROLE ↔ PERMISSION  pivot
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_id'       => ['type' => 'INT', 'unsigned' => true],
            'permission_id' => ['type' => 'INT', 'unsigned' => true],
            'created_at'    => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['role_id', 'permission_id'], 'uq_role_perm');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('role_permissions', true);

        /* ----------------------------------------------------------------
         * 4. USER ↔ ROLE  pivot  (one user may hold multiple roles)
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'role_id'    => ['type' => 'INT', 'unsigned' => true],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['user_id', 'role_id'], 'uq_user_role');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_roles', true);

        /* ----------------------------------------------------------------
         * 5. FIELD-LEVEL PERMISSIONS  (data masking rules per role)
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'role_id'     => ['type' => 'INT', 'unsigned' => true],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 60],
            'field_name'  => ['type' => 'VARCHAR', 'constraint' => 100, 'comment' => 'DB column or virtual field name'],
            'visibility'  => ['type' => "ENUM('visible','masked','hidden')", 'default' => 'visible'],
            'mask_value'  => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true, 'default' => '***', 'comment' => 'Replacement text when masked'],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['role_id', 'module', 'field_name'], 'uq_role_module_field');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('field_permissions', true);

        /* ----------------------------------------------------------------
         * 6. LOGIN ATTEMPTS  (brute-force protection)
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'ip_address' => ['type' => 'VARCHAR', 'constraint' => 45],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'success'    => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['email', 'created_at'], false, false, 'idx_login_email_time');
        $this->forge->addKey(['ip_address', 'created_at'], false, false, 'idx_login_ip_time');
        $this->forge->createTable('login_attempts', true);

        /* ----------------------------------------------------------------
         * 7. AUDIT LOG
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action'      => ['type' => 'VARCHAR', 'constraint' => 60, 'comment' => 'login, logout, perm_change, data_access, etc.'],
            'module'      => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            'resource_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'details'     => ['type' => 'JSON', 'null' => true, 'comment' => 'Extra context in JSON'],
            'ip_address'  => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('user_id', false, false, 'idx_audit_user');
        $this->forge->addKey('action', false, false, 'idx_audit_action');
        $this->forge->addKey('created_at', false, false, 'idx_audit_time');
        $this->forge->createTable('audit_log', true);

        /* ----------------------------------------------------------------
         * 8. MFA TOKENS  (TOTP secret + recovery codes)
         * ---------------------------------------------------------------- */
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user_id'         => ['type' => 'INT', 'unsigned' => true],
            'mfa_secret'      => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'comment' => 'TOTP secret (encrypted)'],
            'mfa_enabled'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'recovery_codes'  => ['type' => 'TEXT', 'null' => true, 'comment' => 'JSON array of hashed recovery codes'],
            'created_at'      => ['type' => 'TIMESTAMP', 'null' => true],
            'updated_at'      => ['type' => 'TIMESTAMP', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('user_id', 'uq_mfa_user');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('user_mfa', true);

        /* ----------------------------------------------------------------
         * 9. ALTER existing `users` table
         *    – add role_id FK, avatar, lockout fields
         *    – keep legacy `role` enum for backward compat (nullable)
         * ---------------------------------------------------------------- */
        // Conditionally add columns if they don't exist
        $cols = [];
        if (!$this->db->fieldExists('role_id', 'users')) {
            $cols['role_id'] = [
                'type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'role',
                'comment' => 'FK to roles table – replaces enum role'
            ];
        }
        if (!$this->db->fieldExists('avatar_path', 'users')) {
            $cols['avatar_path'] = [
                'type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'last_name',
            ];
        }
        if (!$this->db->fieldExists('failed_login_count', 'users')) {
            $cols['failed_login_count'] = [
                'type' => 'TINYINT', 'unsigned' => true, 'default' => 0, 'after' => 'is_active',
            ];
        }
        if (!$this->db->fieldExists('locked_until', 'users')) {
            $cols['locked_until'] = [
                'type' => 'TIMESTAMP', 'null' => true, 'after' => 'failed_login_count',
            ];
        }
        if (!$this->db->fieldExists('password_changed_at', 'users')) {
            $cols['password_changed_at'] = [
                'type' => 'TIMESTAMP', 'null' => true, 'after' => 'locked_until',
            ];
        }
        
        if (!empty($cols)) {
            $this->forge->addColumn('users', $cols);
        }
    }

    // ------------------------------------------------------------------

    public function down()
    {
        // Remove added columns from users
        $this->forge->dropColumn('users', ['role_id', 'avatar_path', 'failed_login_count', 'locked_until', 'password_changed_at']);

        // Drop tables in correct FK order
        $this->forge->dropTable('user_mfa', true);
        $this->forge->dropTable('audit_log', true);
        $this->forge->dropTable('login_attempts', true);
        $this->forge->dropTable('field_permissions', true);
        $this->forge->dropTable('user_roles', true);
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('permissions', true);
        $this->forge->dropTable('roles', true);
    }
}
