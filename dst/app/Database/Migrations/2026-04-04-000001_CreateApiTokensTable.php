<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates the api_tokens table for bearer-token authentication.
 *
 * Roll back: php spark migrate:rollback
 * Run:       php spark migrate
 */
class CreateApiTokensTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            // SHA-256 hex → 64 chars; stored hashed so a DB leak does not
            // expose live tokens (same principle as password hashing).
            'token_hash' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            // Optional human label so users can revoke a specific client.
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
                'default'    => null,
            ],
            // Expiry NULL means "session-scoped only" (expires with explicit revoke).
            'expires_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'last_used_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'revoked' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('token_hash');           // Lookup by hash is the hot path
        $this->forge->addKey('user_id');
        $this->forge->createTable('api_tokens', true); // true = IF NOT EXISTS
    }

    public function down(): void
    {
        $this->forge->dropTable('api_tokens', true);
    }
}
