<?php
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCustomers extends Migration
{
    public function up()
    {
        // customers
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_code' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => false],
            'type' => ['type' => 'ENUM', 'constraint' => ['retail','wholesale','government','partner','other'], 'default' => 'retail'],
            'primary_contact_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'default_currency' => ['type' => 'CHAR', 'constraint' => 3, 'null' => true],
            'billing_address_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'shipping_address_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'tax_id_encrypted' => ['type' => 'VARBINARY', 'constraint' => 1024, 'null' => true],
            'credit_limit' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => '0.00'],
            'payment_terms' => ['type' => 'VARCHAR', 'constraint' => 64, 'default' => 'NET30'],
            'status' => ['type' => 'ENUM', 'constraint' => ['active','inactive','prospect','suspended'], 'default' => 'active'],
            'metadata' => ['type' => 'JSON', 'null' => true],
            'version' => ['type' => 'INT', 'constraint' => 11, 'default' => 1],
            'created_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
            'updated_at' => ['type' => 'DATETIME', 'null' => false, 'default' => 'CURRENT_TIMESTAMP']
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_code');
        $this->forge->createTable('customers', true);

        // customer_contacts
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'phone' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'role' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'preferred' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => false, 'default' => 'CURRENT_TIMESTAMP']
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->createTable('customer_contacts', true);

        // customer_addresses
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'null' => false],
            'type' => ['type' => 'ENUM', 'constraint' => ['billing','shipping','other'], 'default' => 'shipping'],
            'line1' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'line2' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'city' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'state' => ['type' => 'VARCHAR', 'constraint' => 128, 'null' => true],
            'postal_code' => ['type' => 'VARCHAR', 'constraint' => 32, 'null' => true],
            'country' => ['type' => 'CHAR', 'constraint' => 2, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false, 'default' => 'CURRENT_TIMESTAMP']
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->createTable('customer_addresses', true);

        // customer_audit
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'customer_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'action' => ['type' => 'ENUM', 'constraint' => ['create','update','delete','view_sensitive','import'], 'null' => false],
            'changed_by' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'changed_at' => ['type' => 'DATETIME', 'null' => false, 'default' => 'CURRENT_TIMESTAMP'],
            'diff' => ['type' => 'JSON', 'null' => true],
            'note' => ['type' => 'TEXT', 'null' => true]
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('customer_id');
        $this->forge->createTable('customer_audit', true);

        // customer_sequences
        $this->forge->addField([
            'year' => ['type' => 'SMALLINT', 'constraint' => 5, 'null' => false],
            'seq' => ['type' => 'INT', 'constraint' => 11, 'default' => 0]
        ]);
        $this->forge->addKey('year', true);
        $this->forge->createTable('customer_sequences', true);
    }

    public function down()
    {
        $this->forge->dropTable('customer_sequences', true);
        $this->forge->dropTable('customer_audit', true);
        $this->forge->dropTable('customer_addresses', true);
        $this->forge->dropTable('customer_contacts', true);
        $this->forge->dropTable('customers', true);
    }
}
