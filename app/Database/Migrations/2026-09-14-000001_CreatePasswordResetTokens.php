<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePasswordResetTokens extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'account_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'request_ip' => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME'],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('token_hash', 'uq_password_reset_token_hash');
        $this->forge->addKey(['user_id', 'used_at'], false, false, 'idx_password_reset_user_used');
        $this->forge->addKey(['account_id', 'used_at'], false, false, 'idx_password_reset_account_used');
        $this->forge->addKey(['request_ip', 'created_at'], false, false, 'idx_password_reset_ip_created');
        $this->forge->createTable('password_reset_tokens', true);
    }

    public function down()
    {
        $this->forge->dropTable('password_reset_tokens', true);
    }
}
