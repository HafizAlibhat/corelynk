<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixPurchaseOrdersAutoIncrement extends Migration
{
    public function up()
    {
        // Make sure id is auto_increment
        $this->db->query("ALTER TABLE `purchase_orders` MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    public function down()
    {
        // revert to INT without auto_increment (risky) - keep as no-op for safety
        // $this->db->query("ALTER TABLE `purchase_orders` MODIFY `id` INT(11) UNSIGNED NOT NULL");
    }
}
