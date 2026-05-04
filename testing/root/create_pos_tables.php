<?php
/**
 * Create POS tables for Point of Sale module.
 * Run this file once: php create_pos_tables.php
 * Or visit it in the browser.
 */

// Bootstrap CodeIgniter
$_SERVER['CI_ENVIRONMENT'] = 'development';
require_once __DIR__ . '/vendor/autoload.php';

// Minimal CI bootstrap
$app = \Config\Services::codeigniter();
$app->initialize();

$db = \Config\Database::connect();
$forge = \Config\Database::forge();

echo "<h2>Creating POS Tables</h2>";

// ── pos_orders ──
if (!$db->tableExists('pos_orders')) {
    $forge->addField([
        'id' => [
            'type'           => 'INT',
            'constraint'     => 11,
            'unsigned'       => true,
            'auto_increment' => true,
        ],
        'order_number' => [
            'type'       => 'VARCHAR',
            'constraint' => 30,
        ],
        'order_type' => [
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'default'    => 'dine_in',
        ],
        'customer_name' => [
            'type'       => 'VARCHAR',
            'constraint' => 100,
            'default'    => 'Walk-in',
        ],
        'table_number' => [
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'null'       => true,
        ],
        'subtotal' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'tax_rate' => [
            'type'       => 'DECIMAL',
            'constraint' => '5,2',
            'default'    => 0,
        ],
        'tax_amount' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'discount_amount' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'discount_type' => [
            'type'       => 'VARCHAR',
            'constraint' => 10,
            'default'    => 'fixed',
        ],
        'total' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'amount_paid' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'change_due' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'payment_method' => [
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'default'    => 'cash',
        ],
        'status' => [
            'type'       => 'VARCHAR',
            'constraint' => 20,
            'default'    => 'open',
        ],
        'notes' => [
            'type' => 'TEXT',
            'null' => true,
        ],
        'cashier_id' => [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
        ],
        'created_at' => [
            'type' => 'DATETIME',
            'null' => true,
        ],
        'updated_at' => [
            'type' => 'DATETIME',
            'null' => true,
        ],
    ]);
    $forge->addKey('id', true);
    $forge->addKey('order_number');
    $forge->addKey('status');
    $forge->addKey('created_at');
    $forge->createTable('pos_orders');
    echo "<p style='color:green;'>✓ Created table: pos_orders</p>";
} else {
    echo "<p style='color:blue;'>ℹ Table pos_orders already exists</p>";
}

// ── pos_order_lines ──
if (!$db->tableExists('pos_order_lines')) {
    $forge->addField([
        'id' => [
            'type'           => 'INT',
            'constraint'     => 11,
            'unsigned'       => true,
            'auto_increment' => true,
        ],
        'pos_order_id' => [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
        ],
        'product_id' => [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
        ],
        'variant_id' => [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
        ],
        'product_name' => [
            'type'       => 'VARCHAR',
            'constraint' => 200,
        ],
        'variant_name' => [
            'type'       => 'VARCHAR',
            'constraint' => 200,
            'default'    => '',
        ],
        'quantity' => [
            'type'       => 'INT',
            'constraint' => 11,
            'default'    => 1,
        ],
        'unit_price' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'discount' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'line_total' => [
            'type'       => 'DECIMAL',
            'constraint' => '12,2',
            'default'    => 0,
        ],
        'notes' => [
            'type'       => 'VARCHAR',
            'constraint' => 500,
            'default'    => '',
        ],
    ]);
    $forge->addKey('id', true);
    $forge->addKey('pos_order_id');
    $forge->addKey('product_id');
    $forge->createTable('pos_order_lines');
    echo "<p style='color:green;'>✓ Created table: pos_order_lines</p>";
} else {
    echo "<p style='color:blue;'>ℹ Table pos_order_lines already exists</p>";
}

echo "<h3>Done!</h3>";
echo "<p><a href='" . site_url('pos') . "'>→ Open POS Register</a></p>";
