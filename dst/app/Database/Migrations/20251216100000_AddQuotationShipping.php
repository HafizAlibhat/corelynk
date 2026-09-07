<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddQuotationShipping extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();

        // Create quotation_shipping table if it doesn't exist
        if (! $db->tableExists('quotation_shipping')) {
            $this->forge->addField([
                'id' => ['type' => 'INT','constraint'=>11,'unsigned'=>true,'auto_increment'=>true],
                'quotation_id' => ['type' => 'INT','constraint'=>11,'unsigned'=>true,'null'=>false],
                'carrier' => ['type' => 'VARCHAR','constraint'=>64,'null'=>true],
                'service' => ['type' => 'VARCHAR','constraint'=>128,'null'=>true],
                'product_weight' => ['type'=>'DECIMAL','constraint'=>'12,3','default'=>'0.000'],
                'packing_weight' => ['type'=>'DECIMAL','constraint'=>'12,3','default'=>'0.000'],
                'box_weight' => ['type'=>'DECIMAL','constraint'=>'12,3','default'=>'0.000'],
                'shipment_weight' => ['type'=>'DECIMAL','constraint'=>'12,3','default'=>'0.000'],
                'shipping_method' => ['type'=>'VARCHAR','constraint'=>32,'null'=>true],
                'shipping_cost' => ['type'=>'DECIMAL','constraint'=>'12,2','null'=>true],
                'shipping_cost_currency' => ['type'=>'VARCHAR','constraint'=>8,'null'=>true],
                'shipping_taxable' => ['type'=>'TINYINT','constraint'=>1,'default'=>0],
                'shipping_tax_rate' => ['type'=>'DECIMAL','constraint'=>'5,2','null'=>true],
                'shipping_tax_amount' => ['type'=>'DECIMAL','constraint'=>'12,2','null'=>true],
                'shipping_total' => ['type'=>'DECIMAL','constraint'=>'12,2','null'=>true],
                'show_to_customer' => ['type'=>'TINYINT','constraint'=>1,'default'=>0],
                'show_weight_on_pdf' => ['type'=>'TINYINT','constraint'=>1,'default'=>0],
                'actual_shipping_cost' => ['type'=>'DECIMAL','constraint'=>'12,2','null'=>true],
                'notes' => ['type'=>'TEXT','null'=>true],
                'metadata' => ['type'=>'TEXT','null'=>true],
                'created_by' => ['type'=>'INT','constraint'=>11,'null'=>true],
                'created_at' => ['type'=>'DATETIME','null'=>true],
                'updated_by' => ['type'=>'INT','constraint'=>11,'null'=>true],
                'updated_at' => ['type'=>'DATETIME','null'=>true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey('quotation_id');
            $this->forge->createTable('quotation_shipping');
        }

        // Ensure products.unit_weight exists
        if ($db->tableExists('products')) {
            $fields = $db->getFieldNames('products');
            if (! in_array('unit_weight', $fields)) {
                $this->forge->addColumn('products', [
                    'unit_weight' => ['type'=>'DECIMAL','constraint'=>'12,3','default'=>'0.000','null'=>false]
                ]);
            }
        }
    }

    public function down()
    {
        if ($this->db->tableExists('quotation_shipping')) {
            $this->forge->dropTable('quotation_shipping');
        }
        // Do not remove product column during rollback to avoid data loss
    }
}
