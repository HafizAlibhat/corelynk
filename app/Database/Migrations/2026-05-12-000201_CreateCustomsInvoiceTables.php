<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomsInvoiceTables extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('customs_invoices')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => false,
                ],
                'original_invoice_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'customs_invoice_no' => [
                    'type' => 'VARCHAR',
                    'constraint' => 100,
                    'null' => false,
                ],
                'mode' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'VALUE_ONLY',
                ],
                'status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'DRAFT',
                ],
                'current_version_no' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 1,
                ],
                'current_version_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'currency_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 3,
                    'default' => 'USD',
                ],
                'declared_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'default' => 0,
                ],
                'shipment_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'tracking_no' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                    'null' => true,
                ],
                'source_snapshot_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => false,
                ],
                'lock_state' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'UNLOCKED',
                ],
                'row_version' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'default' => 0,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'updated_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('uuid');
            $this->forge->addUniqueKey('customs_invoice_no');
            $this->forge->addKey('original_invoice_id');
            $this->forge->addKey('status');
            $this->forge->addKey('tracking_no');
            $this->forge->createTable('customs_invoices', true);
        }

        if (! $this->db->tableExists('customs_invoice_versions')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => false,
                ],
                'customs_invoice_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'version_no' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 1,
                ],
                'parent_version_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'change_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'CREATE',
                ],
                'change_reason' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_approved_snapshot' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'is_final_snapshot' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'sealed_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'snapshot_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'snapshot_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => false,
                ],
                'pdf_file_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'updated_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('uuid');
            $this->forge->addUniqueKey(['customs_invoice_id', 'version_no']);
            $this->forge->addKey('customs_invoice_id');
            $this->forge->addKey('sealed_at');
            $this->forge->addKey('snapshot_hash');
            $this->forge->createTable('customs_invoice_versions', true);
        }

        if (! $this->db->tableExists('customs_invoice_items')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => false,
                ],
                'customs_invoice_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'customs_invoice_version_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'line_no' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'default' => 1,
                ],
                'line_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'ORIGINAL_MAPPED',
                ],
                'source_invoice_line_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'source_product_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'custom_description' => [
                    'type' => 'TEXT',
                    'null' => false,
                ],
                'hs_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                ],
                'declared_qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'default' => 0,
                ],
                'uom' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                ],
                'declared_unit_price' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'default' => 0,
                ],
                'declared_line_total' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'default' => 0,
                ],
                'declared_weight' => [
                    'type' => 'DECIMAL',
                    'constraint' => '18,4',
                    'null' => true,
                ],
                'weight_uom' => [
                    'type' => 'VARCHAR',
                    'constraint' => 20,
                    'null' => true,
                ],
                'currency_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 3,
                    'default' => 'USD',
                ],
                'group_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                    'null' => true,
                ],
                'metadata_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'updated_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('uuid');
            $this->forge->addKey(['customs_invoice_version_id', 'line_no']);
            $this->forge->addKey('customs_invoice_id');
            $this->forge->addKey('source_invoice_line_id');
            $this->forge->createTable('customs_invoice_items', true);
        }

        if (! $this->db->tableExists('customs_invoice_audit_logs')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => false,
                ],
                'customs_invoice_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'customs_invoice_version_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'event_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => false,
                ],
                'field_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                ],
                'before_value' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'after_value' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'diff_json' => [
                    'type' => 'LONGTEXT',
                    'null' => true,
                ],
                'actor_user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'actor_role' => [
                    'type' => 'VARCHAR',
                    'constraint' => 60,
                    'null' => true,
                ],
                'actor_ip' => [
                    'type' => 'VARCHAR',
                    'constraint' => 60,
                    'null' => true,
                ],
                'actor_user_agent' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'correlation_id' => [
                    'type' => 'VARCHAR',
                    'constraint' => 80,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('uuid');
            $this->forge->addKey(['customs_invoice_id', 'created_at']);
            $this->forge->addKey(['event_type', 'created_at']);
            $this->forge->createTable('customs_invoice_audit_logs', true);
        }

        if (! $this->db->tableExists('customs_invoice_approvals')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => false,
                ],
                'customs_invoice_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'customs_invoice_version_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'approval_status' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'PENDING',
                ],
                'approval_channel' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'default' => 'PORTAL',
                ],
                'requested_to_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 150,
                    'null' => true,
                ],
                'requested_to_email' => [
                    'type' => 'VARCHAR',
                    'constraint' => 190,
                    'null' => true,
                ],
                'request_message' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'decision_comment' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'token_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 128,
                    'null' => true,
                ],
                'token_expires_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'requested_by_user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'decided_by_user_id' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'requested_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'decided_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('uuid');
            $this->forge->addKey(['customs_invoice_id', 'approval_status']);
            $this->forge->addKey('token_hash');
            $this->forge->createTable('customs_invoice_approvals', true);
        }

        if (! $this->db->tableExists('customs_invoice_files')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'uuid' => [
                    'type' => 'VARCHAR',
                    'constraint' => 36,
                    'null' => false,
                ],
                'customs_invoice_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => false,
                ],
                'customs_invoice_version_id' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'file_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 30,
                    'null' => false,
                ],
                'storage_disk' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'default' => 'local',
                ],
                'storage_path' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => false,
                ],
                'file_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => false,
                ],
                'mime_type' => [
                    'type' => 'VARCHAR',
                    'constraint' => 120,
                    'null' => true,
                ],
                'file_size' => [
                    'type' => 'BIGINT',
                    'unsigned' => true,
                    'default' => 0,
                ],
                'sha256_hash' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                ],
                'template_version' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                ],
                'render_engine_version' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                    'null' => true,
                ],
                'is_current' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_by' => [
                    'type' => 'INT',
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'deleted_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('uuid');
            $this->forge->addKey(['customs_invoice_id', 'file_type']);
            $this->forge->addKey('customs_invoice_version_id');
            $this->forge->addKey('sha256_hash');
            $this->forge->createTable('customs_invoice_files', true);
        }
    }

    public function down()
    {
        foreach ([
            'customs_invoice_files',
            'customs_invoice_approvals',
            'customs_invoice_audit_logs',
            'customs_invoice_items',
            'customs_invoice_versions',
            'customs_invoices',
        ] as $table) {
            if ($this->db->tableExists($table)) {
                $this->forge->dropTable($table, true);
            }
        }
    }
}
