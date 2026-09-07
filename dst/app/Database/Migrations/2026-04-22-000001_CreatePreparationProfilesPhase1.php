<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreatePreparationProfilesPhase1 extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('preparation_profiles')) {
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
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_active' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 1,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('product_id');
            $this->forge->addKey(['product_id', 'is_active']);
            if ($this->db->tableExists('products')) {
                $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
            }
            $this->forge->createTable('preparation_profiles', true);
        }

        if (! $this->db->tableExists('preparation_components')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'profile_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'product_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'qty_per_unit' => [
                    'type' => 'DECIMAL',
                    'constraint' => '10,4',
                    'default' => 0,
                ],
                'is_optional' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('profile_id');
            $this->forge->addKey('product_id');
            if ($this->db->tableExists('preparation_profiles')) {
                $this->forge->addForeignKey('profile_id', 'preparation_profiles', 'id', 'CASCADE', 'CASCADE');
            }
            if ($this->db->tableExists('products')) {
                $this->forge->addForeignKey('product_id', 'products', 'id', 'RESTRICT', 'CASCADE');
            }
            $this->forge->createTable('preparation_components', true);
        }

        if (! $this->db->tableExists('preparation_steps')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'profile_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'step_order' => [
                    'type' => 'INT',
                    'constraint' => 11,
                ],
                'name' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                ],
                'description' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_optional' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('profile_id');
            $this->forge->addKey(['profile_id', 'step_order']);
            if ($this->db->tableExists('preparation_profiles')) {
                $this->forge->addForeignKey('profile_id', 'preparation_profiles', 'id', 'CASCADE', 'CASCADE');
            }
            $this->forge->createTable('preparation_steps', true);
        }

        if (! $this->db->tableExists('step_execution_options')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'step_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'execution_type' => [
                    'type' => 'ENUM',
                    'constraint' => ['vendor', 'inhouse'],
                    'null' => false,
                ],
                'vendor_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'is_default' => [
                    'type' => 'TINYINT',
                    'constraint' => 1,
                    'default' => 0,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => false,
                    'default' => new RawSql('CURRENT_TIMESTAMP'),
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('step_id');
            $this->forge->addKey('vendor_id');
            $this->forge->addKey(['step_id', 'is_default']);
            if ($this->db->tableExists('preparation_steps')) {
                $this->forge->addForeignKey('step_id', 'preparation_steps', 'id', 'CASCADE', 'CASCADE');
            }
            if ($this->db->tableExists('vendors')) {
                $this->forge->addForeignKey('vendor_id', 'vendors', 'id', 'SET NULL', 'CASCADE');
            }
            $this->forge->createTable('step_execution_options', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('step_execution_options')) {
            $this->forge->dropTable('step_execution_options', true);
        }
        if ($this->db->tableExists('preparation_steps')) {
            $this->forge->dropTable('preparation_steps', true);
        }
        if ($this->db->tableExists('preparation_components')) {
            $this->forge->dropTable('preparation_components', true);
        }
        if ($this->db->tableExists('preparation_profiles')) {
            $this->forge->dropTable('preparation_profiles', true);
        }
    }
}
