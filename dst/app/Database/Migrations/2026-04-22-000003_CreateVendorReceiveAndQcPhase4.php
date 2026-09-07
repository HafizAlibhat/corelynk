<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateVendorReceiveAndQcPhase4 extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('vendor_receive_notes')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'reference_no' => [
                    'type' => 'VARCHAR',
                    'constraint' => 50,
                ],
                'vendor_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'send_note_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('reference_no');
            $this->forge->addKey('vendor_id');
            $this->forge->addKey('send_note_id');

            if ($this->db->tableExists('vendors')) {
                $this->forge->addForeignKey('vendor_id', 'vendors', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($this->db->tableExists('vendor_send_notes')) {
                $this->forge->addForeignKey('send_note_id', 'vendor_send_notes', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('vendor_receive_notes', true);
        }

        if (! $this->db->tableExists('vendor_receive_items')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'receive_note_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'product_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'qty_received' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                ],
                'qty_accepted' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                ],
                'qty_rejected' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('receive_note_id');
            $this->forge->addKey('product_id');

            if ($this->db->tableExists('vendor_receive_notes')) {
                $this->forge->addForeignKey('receive_note_id', 'vendor_receive_notes', 'id', 'CASCADE', 'CASCADE');
            }
            if ($this->db->tableExists('products')) {
                $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('vendor_receive_items', true);
        }

        if (! $this->db->tableExists('vendor_qc_records')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'receive_item_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'check_name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['pass', 'fail'],
                ],
                'remarks' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('receive_item_id');
            $this->forge->addKey('status');

            if ($this->db->tableExists('vendor_receive_items')) {
                $this->forge->addForeignKey('receive_item_id', 'vendor_receive_items', 'id', 'CASCADE', 'CASCADE');
            }

            $this->forge->createTable('vendor_qc_records', true);
        }

        if (! $this->db->tableExists('qc_rejection_reasons')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('is_active');
            $this->forge->createTable('qc_rejection_reasons', true);

            $this->db->table('qc_rejection_reasons')->insertBatch([
                ['name' => 'Visual defect', 'is_active' => 1],
                ['name' => 'Dimension mismatch', 'is_active' => 1],
                ['name' => 'Material defect', 'is_active' => 1],
                ['name' => 'Performance failure', 'is_active' => 1],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('vendor_qc_records')) {
            $this->forge->dropTable('vendor_qc_records', true);
        }
        if ($this->db->tableExists('vendor_receive_items')) {
            $this->forge->dropTable('vendor_receive_items', true);
        }
        if ($this->db->tableExists('vendor_receive_notes')) {
            $this->forge->dropTable('vendor_receive_notes', true);
        }
        if ($this->db->tableExists('qc_rejection_reasons')) {
            $this->forge->dropTable('qc_rejection_reasons', true);
        }
    }
}
