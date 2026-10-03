<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddProductAssetListingVariantId extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('product_asset_listings')) {
            return;
        }

        if (! $this->db->fieldExists('variant_id', 'product_asset_listings')) {
            $this->forge->addColumn('product_asset_listings', [
                'variant_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                ],
            ]);
        }

        $db = \Config\Database::connect();

        try {
            $db->query('ALTER TABLE product_asset_listings DROP INDEX product_id_channel_id');
        } catch (\Exception $e) {
            // ignore if the index does not exist
        }

        try {
            $db->query('ALTER TABLE product_asset_listings ADD UNIQUE INDEX product_id_variant_id_channel_id (product_id, variant_id, channel_id)');
        } catch (\Exception $e) {
            // ignore if the unique index already exists or cannot be created
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('product_asset_listings')) {
            return;
        }

        $db = \Config\Database::connect();

        try {
            $db->query('ALTER TABLE product_asset_listings DROP INDEX product_id_variant_id_channel_id');
        } catch (\Exception $e) {
            // ignore if the index does not exist
        }

        try {
            $db->query('ALTER TABLE product_asset_listings ADD UNIQUE INDEX product_id_channel_id (product_id, channel_id)');
        } catch (\Exception $e) {
            // ignore if the unique index already exists or cannot be created
        }

        if ($this->db->fieldExists('variant_id', 'product_asset_listings')) {
            $this->forge->dropColumn('product_asset_listings', ['variant_id']);
        }
    }
}
