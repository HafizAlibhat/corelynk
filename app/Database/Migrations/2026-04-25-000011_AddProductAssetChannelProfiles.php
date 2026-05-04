<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductAssetChannelProfiles extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('channels')) {
            if (! $this->db->fieldExists('short_code', 'channels')) {
                $this->forge->addColumn('channels', [
                    'short_code' => [
                        'type' => 'VARCHAR',
                        'constraint' => 20,
                        'null' => true,
                        'after' => 'name',
                    ],
                ]);
            }

            if (! $this->db->fieldExists('rules_json', 'channels')) {
                $this->forge->addColumn('channels', [
                    'rules_json' => [
                        'type' => 'LONGTEXT',
                        'null' => true,
                        'after' => 'allowed_formats',
                    ],
                ]);
            }
        }

        if ($this->db->tableExists('product_assets')) {
            if (! $this->db->fieldExists('section_key', 'product_assets')) {
                $this->forge->addColumn('product_assets', [
                    'section_key' => [
                        'type' => 'VARCHAR',
                        'constraint' => 60,
                        'null' => true,
                        'after' => 'type',
                    ],
                ]);
            }

            if (! $this->db->fieldExists('section_label', 'product_assets')) {
                $this->forge->addColumn('product_assets', [
                    'section_label' => [
                        'type' => 'VARCHAR',
                        'constraint' => 120,
                        'null' => true,
                        'after' => 'section_key',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('product_assets')) {
            if ($this->db->fieldExists('section_label', 'product_assets')) {
                $this->forge->dropColumn('product_assets', 'section_label');
            }
            if ($this->db->fieldExists('section_key', 'product_assets')) {
                $this->forge->dropColumn('product_assets', 'section_key');
            }
        }

        if ($this->db->tableExists('channels')) {
            if ($this->db->fieldExists('rules_json', 'channels')) {
                $this->forge->dropColumn('channels', 'rules_json');
            }
            if ($this->db->fieldExists('short_code', 'channels')) {
                $this->forge->dropColumn('channels', 'short_code');
            }
        }
    }
}
