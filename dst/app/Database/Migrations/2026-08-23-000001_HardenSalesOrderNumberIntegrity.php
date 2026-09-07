<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class HardenSalesOrderNumberIntegrity extends Migration
{
    public function up()
    {
        // Ensure no duplicate order numbers exist before adding unique index.
        $dups = $this->db->query(
            "SELECT order_number, COUNT(*) AS cnt FROM sales_orders GROUP BY order_number HAVING COUNT(*) > 1 LIMIT 1"
        )->getRowArray();

        if (!empty($dups)) {
            throw new \RuntimeException(
                'Cannot add unique index to sales_orders.order_number; duplicate value found: ' . (string) ($dups['order_number'] ?? '')
            );
        }

        $idx = $this->db->query("SHOW INDEX FROM sales_orders WHERE Key_name = 'uq_sales_orders_order_number'")->getResultArray();
        if (empty($idx)) {
            $this->db->query('ALTER TABLE sales_orders ADD UNIQUE KEY uq_sales_orders_order_number (order_number)');
        }
    }

    public function down()
    {
        $idx = $this->db->query("SHOW INDEX FROM sales_orders WHERE Key_name = 'uq_sales_orders_order_number'")->getResultArray();
        if (!empty($idx)) {
            $this->db->query('ALTER TABLE sales_orders DROP INDEX uq_sales_orders_order_number');
        }
    }
}
