<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ProductCategoriesPrimaryKey extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('product_categories')) {
            return;
        }

        // If table already has a primary key, do nothing
        $sql = "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'product_categories' AND CONSTRAINT_TYPE = 'PRIMARY KEY'";
        $hasPk = (int) ($db->query($sql)->getRow()->cnt ?? 0);
        if ($hasPk) {
            return;
        }

        // Ensure id column is proper unsigned int
        $db->query("ALTER TABLE `product_categories` MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL");

        // Reassign rows with id = 0 (if any) to unique ids starting after MAX(id)
        $maxRow = $db->query("SELECT COALESCE(MAX(id), 0) as maxid FROM `product_categories`")->getRow();
        $max = (int) ($maxRow->maxid ?? 0);

        $zeroCount = (int) $db->query("SELECT COUNT(*) as cnt FROM `product_categories` WHERE `id` = 0")->getRow()->cnt;
        if ($zeroCount > 0) {
            // Use user variable to assign incremental ids starting from $max + 1
            $start = $max + 1;
            // This update assigns new ids to rows where id = 0
            $db->query("SET @i := $start");
            $db->query("UPDATE `product_categories` SET `id` = (@i := @i + 1) WHERE `id` = 0 ORDER BY `created_at` ASC");
            // recompute max
            $max = (int) $db->query("SELECT COALESCE(MAX(id), 0) as maxid FROM `product_categories`")->getRow()->maxid;
        }

        // Add primary key and make id AUTO_INCREMENT
        // Wrap in try/catch to avoid fatal errors
        try {
            $db->query("ALTER TABLE `product_categories` ADD PRIMARY KEY (`id`)");
        } catch (\Throwable $e) {
            // ignore if already has primary key or other issue
        }

        try {
            $db->query("ALTER TABLE `product_categories` MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT");
            // Set AUTO_INCREMENT start to max+1
            $next = $max + 1;
            $db->query("ALTER TABLE `product_categories` AUTO_INCREMENT = $next");
        } catch (\Throwable $e) {
            // ignore modify errors
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if (! $db->tableExists('product_categories')) {
            return;
        }

        // Remove AUTO_INCREMENT and primary key if present
        try {
            $db->query("ALTER TABLE `product_categories` MODIFY COLUMN `id` INT(10) UNSIGNED NOT NULL");
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $db->query("ALTER TABLE `product_categories` DROP PRIMARY KEY");
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
