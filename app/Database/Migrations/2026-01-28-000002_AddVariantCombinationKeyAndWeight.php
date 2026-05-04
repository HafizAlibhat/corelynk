<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddVariantCombinationKeyAndWeight extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // If table doesn't exist yet, do nothing.
        try {
            $existing = $db->getFieldNames('product_variants');
        } catch (\Throwable $e) {
            return;
        }

        if (!in_array('weight', $existing, true)) {
            $this->forge->addColumn('product_variants', [
                'weight' => [
                    'type' => 'DECIMAL',
                    'constraint' => '15,3',
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }

        if (!in_array('combination_key', $existing, true)) {
            $this->forge->addColumn('product_variants', [
                'combination_key' => [
                    'type' => 'VARCHAR',
                    'constraint' => 40,
                    'null' => true,
                    'default' => null,
                ],
            ]);
        }

        // Backfill combination_key for existing rows
        try {
            $rows = $db->table('product_variants')->select('id, attributes')->get()->getResultArray();
            foreach ($rows as $r) {
                $id = (int)($r['id'] ?? 0);
                if ($id <= 0) continue;

                $attrsRaw = $r['attributes'] ?? null;
                $attrs = [];
                if (is_string($attrsRaw) && trim($attrsRaw) !== '') {
                    try { $attrs = json_decode($attrsRaw, true) ?? []; } catch (\Throwable $e) { $attrs = []; }
                }
                if (!is_array($attrs)) $attrs = [];

                $norm = [];
                foreach ($attrs as $k => $v) {
                    $kk = trim((string)$k);
                    if ($kk === '') continue;
                    $vv = is_scalar($v) ? trim((string)$v) : trim(json_encode($v));
                    if ($vv === '') continue;
                    $norm[$kk] = $vv;
                }
                ksort($norm);

                $sig = json_encode($norm, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $ck = sha1($sig ?: '{}');

                $db->table('product_variants')->where('id', $id)->update(['combination_key' => $ck]);
            }
        } catch (\Throwable $e) {
            // ignore backfill errors
        }

        // Add unique index on (product_id, combination_key) if possible
        try {
            $db->query('ALTER TABLE `product_variants` ADD UNIQUE KEY `ux_product_variants_product_combination` (`product_id`,`combination_key`)');
        } catch (\Throwable $e) {
            // index may already exist or table engine may not allow; ignore
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try {
            $existing = $db->getFieldNames('product_variants');
        } catch (\Throwable $e) {
            return;
        }

        // Drop unique index if present
        try {
            $db->query('ALTER TABLE `product_variants` DROP INDEX `ux_product_variants_product_combination`');
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if (in_array('combination_key', $existing, true)) {
                $db->query('ALTER TABLE `product_variants` DROP COLUMN `combination_key`');
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            if (in_array('weight', $existing, true)) {
                $db->query('ALTER TABLE `product_variants` DROP COLUMN `weight`');
            }
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
