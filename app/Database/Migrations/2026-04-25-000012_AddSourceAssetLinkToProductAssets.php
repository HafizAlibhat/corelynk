<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSourceAssetLinkToProductAssets extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('product_assets')) {
            return;
        }

        if (! $this->db->fieldExists('source_asset_id', 'product_assets')) {
            $this->forge->addColumn('product_assets', [
                'source_asset_id' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'channel_id',
                ],
            ]);
        }

        $indexes = array_map(static fn($row) => strtolower((string) ($row->Key_name ?? '')), $this->db->query('SHOW INDEX FROM product_assets')->getResult());
        if (! in_array('idx_product_assets_source_asset_id', $indexes, true)) {
            $this->db->query('CREATE INDEX idx_product_assets_source_asset_id ON product_assets (source_asset_id)');
        }
    }

    public function down()
    {
        if (! $this->db->tableExists('product_assets')) {
            return;
        }

        $indexes = array_map(static fn($row) => strtolower((string) ($row->Key_name ?? '')), $this->db->query('SHOW INDEX FROM product_assets')->getResult());
        if (in_array('idx_product_assets_source_asset_id', $indexes, true)) {
            $this->db->query('DROP INDEX idx_product_assets_source_asset_id ON product_assets');
        }

        if ($this->db->fieldExists('source_asset_id', 'product_assets')) {
            $this->forge->dropColumn('product_assets', 'source_asset_id');
        }
    }
}
