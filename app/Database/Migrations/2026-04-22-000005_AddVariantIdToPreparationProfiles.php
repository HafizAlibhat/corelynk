<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantIdToPreparationProfiles extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('preparation_profiles')) {
            return;
        }

        $fields = $this->db->getFieldNames('preparation_profiles');

        if (! in_array('variant_id', $fields, true)) {
            $this->forge->addColumn('preparation_profiles', [
                'variant_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'product_id',
                ],
            ]);
        }

        try {
            $this->db->query('CREATE INDEX idx_preparation_profiles_variant ON preparation_profiles (variant_id)');
        } catch (\Throwable $_) {
            // Index may already exist.
        }

        try {
            $this->db->query('CREATE INDEX idx_preparation_profiles_product_variant_active ON preparation_profiles (product_id, variant_id, is_active)');
        } catch (\Throwable $_) {
            // Index may already exist.
        }

        if ($this->db->tableExists('product_variants')) {
            try {
                $this->db->query('ALTER TABLE preparation_profiles ADD CONSTRAINT fk_preparation_profiles_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE ON UPDATE CASCADE');
            } catch (\Throwable $_) {
                // FK may already exist or DB engine may not permit it.
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('preparation_profiles')) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE preparation_profiles DROP FOREIGN KEY fk_preparation_profiles_variant');
        } catch (\Throwable $_) {
            // FK may not exist.
        }

        $fields = $this->db->getFieldNames('preparation_profiles');
        if (in_array('variant_id', $fields, true)) {
            $this->forge->dropColumn('preparation_profiles', 'variant_id');
        }
    }
}
