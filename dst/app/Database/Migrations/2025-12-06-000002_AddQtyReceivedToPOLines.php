<?php
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;

class AddQtyReceivedToPOLines extends Migration
{
    public function up()
    {
        // Add qty_received to purchase_order_lines
        $this->forge->addColumn('purchase_order_lines', [
            'qty_received' => ['type' => 'DECIMAL', 'constraint' => '15,3', 'default' => '0.000', 'null' => false],
        ]);
    }

    public function down()
    {
        $db = \Config\Database::connect();
        try {
            $db->query("ALTER TABLE `purchase_order_lines` DROP COLUMN `qty_received`");
        } catch (\Throwable $e) {
            // ignore
        }
    }
}
