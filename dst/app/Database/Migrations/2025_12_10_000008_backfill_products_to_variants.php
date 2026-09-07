<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillProductsToVariants extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        // Guard: if product_variants table doesn't exist, nothing to do here
        if (! $db->tableExists('product_variants')) {
            return;
        }

        $products = $db->query('SELECT id, code, name, category_id, sale_price, cost_price, current_stock, unit_cost FROM products')->getResultArray();

        foreach ($products as $p) {
            $productId = (int) $p['id'];

            // Skip if variants already exist for this product
            $row = $db->query('SELECT COUNT(*) AS cnt FROM product_variants WHERE product_id = ?', [$productId])->getRow();
            if ($row && $row->cnt > 0) continue;

            // Determine art_number: prefer existing product.code, otherwise generate from category if available
            $artNumber = null;
            if (!empty($p['code'])) {
                $artNumber = $p['code'];
            } else {
                try {
                    if (!empty($p['category_id'])) {
                        $svc = new \App\Services\ArtNumberService();
                        $artNumber = $svc->generateForCategory((int)$p['category_id']);
                    }
                } catch (\Throwable $e) {
                    // fallback to an auto id-based art number to avoid blocking migration
                    $artNumber = 'AUTO-' . $productId;
                }
            }

            if (empty($artNumber)) {
                $artNumber = 'AUTO-' . $productId;
            }

            $price = isset($p['sale_price']) ? $p['sale_price'] : 0;
            $cost  = isset($p['cost_price']) ? $p['cost_price'] : (isset($p['unit_cost']) ? $p['unit_cost'] : 0);
            $qty   = isset($p['current_stock']) ? (float)$p['current_stock'] : 0.0;

            // Insert variant
            $db->table('product_variants')->insert([
                'product_id' => $productId,
                'art_number' => $artNumber,
                'name'       => $p['name'] ?? null,
                'price'      => $price,
                'cost'       => $cost,
                'attributes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $variantId = $db->insertID();

            // Insert inventory row (warehouse_id nullable). We create a single inventory row with warehouse_id = NULL using existing product stock.
            if ($variantId) {
                if ($db->tableExists('variant_inventory')) {
                    $db->table('variant_inventory')->insert([
                        'variant_id' => $variantId,
                        'warehouse_id' => null,
                        'quantity' => $qty,
                        'reserved' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        // Best-effort rollback: remove variants created by this backfill by matching art_number pattern 'AUTO-%' or created today.
        // Be conservative to avoid deleting intentionally created variants.
        if ($db->tableExists('product_variants')) {
            // Delete AUTO- generated art_numbers only
            $db->query("DELETE FROM product_variants WHERE art_number LIKE 'AUTO-%'");
        }

        if ($db->tableExists('variant_inventory')) {
            // Remove orphaned inventory entries with null warehouse that point to deleted variants
            $db->query("DELETE vi FROM variant_inventory vi LEFT JOIN product_variants pv ON pv.id = vi.variant_id WHERE pv.id IS NULL");
        }
    }
}
