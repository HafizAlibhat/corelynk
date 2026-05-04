<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddInvoiceIndexes extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // idx_parent_invoice
        $has = $db->query("SHOW INDEX FROM customer_invoices WHERE Key_name = 'idx_parent_invoice'")->getResultArray();
        if (empty($has)) {
            try {
                $db->query('ALTER TABLE `customer_invoices` ADD INDEX `idx_parent_invoice` (`parent_invoice_id`)');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        // idx_invoice_type
        $has2 = $db->query("SHOW INDEX FROM customer_invoices WHERE Key_name = 'idx_invoice_type'")->getResultArray();
        if (empty($has2)) {
            try {
                $db->query('ALTER TABLE `customer_invoices` ADD INDEX `idx_invoice_type` (`invoice_type`)');
            } catch (\Throwable $e) {
                // ignore
            }
        }
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try { $db->query('ALTER TABLE `customer_invoices` DROP INDEX `idx_parent_invoice`'); } catch (\Throwable $e) {}
        try { $db->query('ALTER TABLE `customer_invoices` DROP INDEX `idx_invoice_type`'); } catch (\Throwable $e) {}
    }
}
