<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendInvoiceStatusEnum extends Migration
{
    public function up()
    {
        $sql = "ALTER TABLE `customer_invoices` CHANGE `status` `status` ENUM('draft','confirmed','posted','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft';";
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            // If enum already contains values, ignore error
        }
    }

    public function down()
    {
        $sql = "ALTER TABLE `customer_invoices` CHANGE `status` `status` ENUM('draft','issued','partially_paid','paid','overdue','cancelled') NOT NULL DEFAULT 'draft';";
        try {
            $this->db->query($sql);
        } catch (\Throwable $e) {
            // ignore
        }
    }
}