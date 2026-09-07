<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsDefaultToCustomerAddresses extends Migration
{
    public function up()
    {
        // Add is_default column if it doesn't exist
        $db = \Config\Database::connect();
        
        if (!$db->fieldExists('is_default', 'customer_addresses')) {
            $db->query("ALTER TABLE `customer_addresses` ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag to mark this as the default address for the customer'");
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();

        if ($db->fieldExists('is_default', 'customer_addresses')) {
            $db->query("ALTER TABLE `customer_addresses` DROP COLUMN `is_default`");
        }
    }
}
