<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ProductsPrimaryKey extends Migration
{
    public function up()
    {
        // Make migration robust:
        // 1) Normalize id column type
        // 2) Reassign any id = 0 rows to unique ids
        // 3) Add primary key and AUTO_INCREMENT if not present
    $db = \Config\Database::connect();

        try {
            // Ensure id column type is integer unsigned not null (no auto_increment yet)
            $db->query("ALTER TABLE `products` MODIFY `id` INT(10) UNSIGNED NOT NULL");

            // If there are rows with id = 0, assign them unique ids starting after current max(id)
            $hasZero = $db->query("SELECT COUNT(*) AS cnt FROM `products` WHERE `id` = 0")->getRow()->cnt ?? 0;
            if ($hasZero > 0) {
                // initialize variable to max id
                $db->query("SET @m := (SELECT COALESCE(MAX(id),0) FROM `products`)");
                // assign new ids to rows with id = 0
                $db->query("UPDATE `products` SET `id` = (@m := @m + 1) WHERE `id` = 0");
            }

            // If there's no primary key yet, add it with AUTO_INCREMENT
            $pk = $db->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND CONSTRAINT_TYPE = 'PRIMARY KEY'")->getRow()->cnt ?? 0;
            if ($pk == 0) {
                // Ensure auto_increment starts after current max id
                $maxId = $db->query("SELECT COALESCE(MAX(id),0) AS m FROM `products`")->getRow()->m ?? 0;
                $next = (int)$maxId + 1;

                $db->query("ALTER TABLE `products` MODIFY `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT, ADD PRIMARY KEY (`id`)");
                // set auto_increment to next value
                $db->query("ALTER TABLE `products` AUTO_INCREMENT = $next");
            }
        } catch (\Throwable $e) {
            // If anything fails, throw so migration runner sees the error
            throw $e;
        }
    }

    public function down()
    {
        // Revert to non-AUTO_INCREMENT and drop primary key (if needed)
        $this->db->query('ALTER TABLE `products`
            MODIFY `id` INT(10) UNSIGNED NOT NULL,
            DROP PRIMARY KEY');
    }
}
