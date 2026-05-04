i <?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakeProductCategoriesAutoIncrement extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('product_categories')) {
            return;
        }

        // Reassign any id=0 rows
        $zeroCount = (int) $db->query("SELECT COUNT(*) as cnt FROM `product_categories` WHERE `id` = 0")->getRow()->cnt;
        $max = (int) $db->query("SELECT COALESCE(MAX(id), 0) as maxid FROM `product_categories`")->getRow()->maxid;
        if ($zeroCount > 0) {
            $start = $max + 1;
            $db->query("SET @i := $start");
            $db->query("UPDATE `product_categories` SET `id` = (@i := @i + 1) WHERE `id` = 0 ORDER BY `created_at` ASC");
            $max = (int) $db->query("SELECT COALESCE(MAX(id), 0) as maxid FROM `product_categories`")->getRow()->maxid;
        }

        // Now modify id to be AUTO_INCREMENT
        try {
            $db->query("ALTER TABLE `product_categories` MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
            $next = $max + 1;
            $db->query("ALTER TABLE `product_categories` AUTO_INCREMENT = $next");
        } catch (\Throwable $e) {
            // ignore - but surface error to logs if needed
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        if (! $db->tableExists('product_categories')) {
            return;
        }
        try {
            $db->query("ALTER TABLE `product_categories` MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL");
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
