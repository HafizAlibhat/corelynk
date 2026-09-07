<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantIdToPreparationComponents extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('preparation_components')) {
            return;
        }

        $fields = $this->db->getFieldNames('preparation_components');

        if (! in_array('variant_id', $fields, true)) {
            $this->forge->addColumn('preparation_components', [
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
            $this->db->query('CREATE INDEX idx_preparation_components_variant ON preparation_components (variant_id)');
        } catch (\Throwable $_) {
            // Index may already exist.
        }

        if ($this->db->tableExists('product_variants')) {
            try {
                $this->db->query('ALTER TABLE preparation_components ADD CONSTRAINT fk_preparation_components_variant FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL ON UPDATE CASCADE');
            } catch (\Throwable $_) {
                // FK may already exist or unsupported.
            }
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('preparation_components')) {
            return;
        }

        try {
            $this->db->query('ALTER TABLE preparation_components DROP FOREIGN KEY fk_preparation_components_variant');
        } catch (\Throwable $_) {
            // FK may not exist.
        }

        $fields = $this->db->getFieldNames('preparation_components');
        if (in_array('variant_id', $fields, true)) {
            $this->forge->dropColumn('preparation_components', 'variant_id');
        }
    }
}
