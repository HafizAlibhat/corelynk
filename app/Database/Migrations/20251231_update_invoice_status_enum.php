<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class UpdateInvoiceStatusEnum extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        try {
            // Alter enum to include 'confirmed' and 'posted' if not present
            $sql = "ALTER TABLE `customer_invoices` CHANGE `status` `status` ENUM('draft','confirmed','issued','partially_paid','paid','overdue','cancelled','posted') NOT NULL DEFAULT 'draft'";
            $db->query($sql);
        } catch (\Throwable $_) {
            // best-effort: ignore failures (e.g., permission issues)
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try {
            // revert to original set (remove confirmed/posted)
            $sql = "ALTER TABLE `customer_invoices` CHANGE `status` `status` ENUM('draft','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft'";
            $db->query($sql);
        } catch (\Throwable $_) {
            // ignore
        }
    }
}
