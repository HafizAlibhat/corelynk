<?php
namespace App\Database\Migrations;use CodeIgniter\Database\Migration;
class CreatePurchaseOrderLines extends Migration{public function up(){ $this->forge->addField([
 'id'=>['type'=>'INT','auto_increment'=>true],'po_id'=>['type'=>'INT','null'=>false],'product_id'=>['type'=>'INT','null'=>true],'description'=>['type'=>'VARCHAR','constraint'=>255,'null'=>true],'qty'=>['type'=>'DECIMAL','constraint'=>'15,3','default'=>'0.000','null'=>false],'unit_price'=>['type'=>'DECIMAL','constraint'=>'15,4','default'=>'0.0000','null'=>false],'tax_code_id'=>['type'=>'INT','null'=>true],'line_total'=>['type'=>'DECIMAL','constraint'=>'15,2','default'=>'0.00','null'=>false],]);
 $this->forge->addKey('id', true);$this->forge->addKey('po_id');$this->forge->addKey('product_id');$this->forge->createTable('purchase_order_lines', true);}public function down(){ $this->forge->dropTable('purchase_order_lines'); }}
