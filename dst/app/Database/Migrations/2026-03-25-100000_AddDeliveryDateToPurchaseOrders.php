<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveryDateToPurchaseOrders extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('delivery_date', 'purchase_orders')) {
            $fields = [
                'delivery_date' => [
                    'type' => 'DATE',
                    'null' => true,
                    'after' => 'order_date',
                ],
            ];
            $this->forge->addColumn('purchase_orders', $fields);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('delivery_date', 'purchase_orders')) {
            $this->forge->dropColumn('purchase_orders', 'delivery_date');
        }
    }
}
