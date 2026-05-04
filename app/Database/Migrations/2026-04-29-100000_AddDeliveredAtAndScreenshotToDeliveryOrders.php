<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDeliveredAtAndScreenshotToDeliveryOrders extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('delivery_orders')) {
            $fields = $this->db->getFieldNames('delivery_orders');

            if (!in_array('delivered_at', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'delivered_at' => [
                        'type'    => 'DATE',
                        'null'    => true,
                        'default' => null,
                        'after'   => 'delivery_confirmed_at',
                    ],
                ]);
            }

            if (!in_array('delivery_screenshot', $fields)) {
                $this->forge->addColumn('delivery_orders', [
                    'delivery_screenshot' => [
                        'type'       => 'VARCHAR',
                        'constraint' => 255,
                        'null'       => true,
                        'default'    => null,
                        'after'      => 'delivered_at',
                    ],
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('delivery_orders')) {
            $fields = $this->db->getFieldNames('delivery_orders');

            if (in_array('delivery_screenshot', $fields)) {
                $this->forge->dropColumn('delivery_orders', 'delivery_screenshot');
            }
            if (in_array('delivered_at', $fields)) {
                $this->forge->dropColumn('delivery_orders', 'delivered_at');
            }
        }
    }
}
