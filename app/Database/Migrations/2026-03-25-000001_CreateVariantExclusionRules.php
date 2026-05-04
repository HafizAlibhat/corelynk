<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateVariantExclusionRules extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('variant_exclusion_rules')) {
            $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'product_id' => [
                'type'       => 'INT',
                'null'       => false,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => false,
                'comment'    => 'Exclusion rule name (e.g., "Feather Only for Flat Billet")',
            ],
            'description' => [
                'type'       => 'TEXT',
                'null'       => true,
                'comment'    => 'Detailed explanation of the exclusion rule',
            ],
            'rule_type' => [
                'type'       => 'ENUM',
                'constraint' => ['include', 'exclude'],
                'default'    => 'exclude',
                'null'       => false,
                'comment'    => 'include = only generate for these values | exclude = skip these values',
            ],
            'is_active' => [
                'type'       => 'TINYINT',
                'default'    => 1,
                'null'       => false,
                'comment'    => 'Whether this rule is currently applied',
            ],
            'created_at' => [
                'type'       => 'DATETIME',
                'null'       => false,
                'default'    => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
            'updated_at' => [
                'type'       => 'DATETIME',
                'null'       => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('product_id');
        $this->forge->addKey('is_active');
        $this->forge->addKey(['product_id', 'is_active']);
        
        // Foreign key constraint
        $this->forge->addForeignKey('product_id', 'products', 'id', 'CASCADE', 'CASCADE');
        
            $this->forge->createTable('variant_exclusion_rules');
        }

        // ============================================================
        // variant_exclusion_conditions: Stores individual conditions
        // ============================================================
        // Example: 
        //   rule_id=1, attribute_id=5, attribute_value_id=12  (Feather)
        //   rule_id=1, attribute_id=6, attribute_value_id=18  (Flat)
        // This means: "EXCLUDE variants that have BOTH Feather AND Flat"
        // OR
        // Example (include mode):
        //   rule_id=2, attribute_id=5, attribute_value_id=12  (Feather only)
        // This means: "ONLY INCLUDE variants with exactly this value for that attribute"

        if (! $this->db->tableExists('variant_exclusion_conditions')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'auto_increment' => true,
                ],
            'rule_id' => [
                'type'       => 'INT',
                'null'       => false,
                'unsigned'   => true,
            ],
            'attribute_id' => [
                'type'       => 'INT',
                'null'       => false,
                'unsigned'   => true,
            ],
            'attribute_value_id' => [
                'type'       => 'INT',
                'null'       => true,
                'unsigned'   => true,
                'comment'    => 'ID of the specific attribute value, null = all values for this attribute',
            ],
            'attribute_value_name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
                'null'       => true,
                'comment'    => 'Denormalized value name for display/search',
            ],
            'created_at' => [
                'type'       => 'DATETIME',
                'null'       => false,
                'default'    => new \CodeIgniter\Database\RawSql('CURRENT_TIMESTAMP'),
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('rule_id');
        $this->forge->addKey('attribute_id');
        $this->forge->addKey(['rule_id', 'attribute_id']);
        
        // Foreign keys
        $this->forge->addForeignKey('rule_id', 'variant_exclusion_rules', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('attribute_id', 'product_attributes', 'id', 'CASCADE', 'CASCADE');
        if ($this->db->tableExists('attribute_values')) {
            $this->forge->addForeignKey('attribute_value_id', 'attribute_values', 'id', 'SET NULL', 'CASCADE');
        }
        
            $this->forge->createTable('variant_exclusion_conditions');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('variant_exclusion_conditions')) {
            $this->forge->dropTable('variant_exclusion_conditions', true);
        }
        if ($this->db->tableExists('variant_exclusion_rules')) {
            $this->forge->dropTable('variant_exclusion_rules', true);
        }
    }
}
