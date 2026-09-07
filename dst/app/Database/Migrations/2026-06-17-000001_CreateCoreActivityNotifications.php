<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Creates additive activity center notification tables.
 */
class CreateCoreActivityNotifications extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('core_notifications')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'notification_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                ],
                'source_table' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                ],
                'source_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                ],
                'source_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                    'default' => null,
                ],
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'default' => null,
                ],
                'message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'payload_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'became_inactive_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
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
            $this->forge->addUniqueKey(['notification_type', 'source_table', 'source_id'], 'uniq_core_notification_source');
            $this->forge->addKey(['notification_type', 'is_active'], false, false, 'idx_core_notifications_type_active');
            $this->forge->addKey(['source_table', 'source_id'], false, false, 'idx_core_notifications_source');
            $this->forge->addKey('created_at');
            $this->forge->createTable('core_notifications', true);
        }

        if (!$this->db->tableExists('core_notification_reads')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'notification_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                ],
                'user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                ],
                'read_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                    'default' => null,
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
            $this->forge->addUniqueKey(['notification_id', 'user_id'], 'uniq_core_notification_user');
            $this->forge->addKey(['user_id', 'read_at'], false, false, 'idx_core_notification_reads_user');
            $this->forge->addKey('notification_id');
            $this->forge->createTable('core_notification_reads', true);
        }
    }

    public function down(): void
    {
        $this->forge->dropTable('core_notification_reads', true);
        $this->forge->dropTable('core_notifications', true);
    }
}
