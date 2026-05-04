<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreatePreparationExecutionPhase3 extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('vendor_send_notes')) {
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
                'step_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'product_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                ],
                'from_location_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'to_location_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['draft', 'sent', 'completed', 'cancelled'],
                    'default' => 'draft',
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
            $this->forge->addKey('step_id');
            $this->forge->addKey('product_id');
            $this->forge->addKey('from_location_id');
            $this->forge->addKey('to_location_id');

            if ($this->db->tableExists('vendors')) {
                $this->forge->addForeignKey('vendor_id', 'vendors', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($this->db->tableExists('preparation_steps')) {
                $this->forge->addForeignKey('step_id', 'preparation_steps', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($this->db->tableExists('products')) {
                $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($this->db->tableExists('warehouse_locations')) {
                $this->forge->addForeignKey('from_location_id', 'warehouse_locations', 'id', 'RESTRICT', 'CASCADE');
                $this->forge->addForeignKey('to_location_id', 'warehouse_locations', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('vendor_send_notes', true);
        }

        if (! $this->db->tableExists('vendor_send_note_items')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'send_note_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'product_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('send_note_id');
            $this->forge->addKey('product_id');

            if ($this->db->tableExists('vendor_send_notes')) {
                $this->forge->addForeignKey('send_note_id', 'vendor_send_notes', 'id', 'CASCADE', 'CASCADE');
            }
            if ($this->db->tableExists('products')) {
                $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('vendor_send_note_items', true);
        }

        if (! $this->db->tableExists('processing_records')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'product_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'step_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'vendor_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'qty' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                ],
                'status' => [
                    'type' => 'ENUM',
                    'constraint' => ['in_progress', 'ready_for_qc', 'completed'],
                    'default' => 'in_progress',
                ],
                'location_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'parent_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('product_id');
            $this->forge->addKey('step_id');
            $this->forge->addKey('vendor_id');
            $this->forge->addKey('location_id');
            $this->forge->addKey('parent_id');
            $this->forge->addKey(['product_id', 'step_id', 'status']);

            if ($this->db->tableExists('products')) {
                $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($this->db->tableExists('preparation_steps')) {
                $this->forge->addForeignKey('step_id', 'preparation_steps', 'id', 'RESTRICT', 'CASCADE');
            }
            if ($this->db->tableExists('vendors')) {
                $this->forge->addForeignKey('vendor_id', 'vendors', 'id', 'SET NULL', 'CASCADE');
            }
            if ($this->db->tableExists('warehouse_locations')) {
                $this->forge->addForeignKey('location_id', 'warehouse_locations', 'id', 'RESTRICT', 'CASCADE');
            }

            $this->forge->createTable('processing_records', true);

            if ($this->db->tableExists('processing_records')) {
                $this->db->query('ALTER TABLE `processing_records` ADD CONSTRAINT `fk_processing_records_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `processing_records`(`id`) ON DELETE SET NULL ON UPDATE CASCADE');
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('processing_records')) {
            $this->forge->dropTable('processing_records', true);
        }
        if ($this->db->tableExists('vendor_send_note_items')) {
            $this->forge->dropTable('vendor_send_note_items', true);
        }
        if ($this->db->tableExists('vendor_send_notes')) {
            $this->forge->dropTable('vendor_send_notes', true);
        }
    }
}
