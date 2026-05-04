<?php
// Quick migration script for POS tables — uses raw mysqli
ini_set('display_errors', '1');
error_reporting(E_ALL);

$conn = new mysqli('localhost', 'root', '', 'corelynk_db');
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// pos_orders
$sql1 = "CREATE TABLE IF NOT EXISTS pos_orders (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    order_number VARCHAR(30) NOT NULL,
    order_type VARCHAR(20) DEFAULT 'dine_in',
    customer_name VARCHAR(100) DEFAULT 'Walk-in',
    table_number VARCHAR(20) NULL,
    subtotal DECIMAL(12,2) DEFAULT 0,
    tax_rate DECIMAL(5,2) DEFAULT 0,
    tax_amount DECIMAL(12,2) DEFAULT 0,
    discount_amount DECIMAL(12,2) DEFAULT 0,
    discount_type VARCHAR(10) DEFAULT 'fixed',
    total DECIMAL(12,2) DEFAULT 0,
    amount_paid DECIMAL(12,2) DEFAULT 0,
    change_due DECIMAL(12,2) DEFAULT 0,
    payment_method VARCHAR(20) DEFAULT 'cash',
    status VARCHAR(20) DEFAULT 'open',
    notes TEXT NULL,
    cashier_id INT(11) UNSIGNED NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_order_number (order_number),
    KEY idx_status (status),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql1)) {
    echo "pos_orders: OK\n";
} else {
    echo "pos_orders ERROR: " . $conn->error . "\n";
}

// pos_order_lines
$sql2 = "CREATE TABLE IF NOT EXISTS pos_order_lines (
    id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    pos_order_id INT(11) UNSIGNED NOT NULL,
    product_id INT(11) UNSIGNED NULL,
    variant_id INT(11) UNSIGNED NULL,
    product_name VARCHAR(200) NOT NULL,
    variant_name VARCHAR(200) DEFAULT '',
    quantity INT(11) DEFAULT 1,
    unit_price DECIMAL(12,2) DEFAULT 0,
    discount DECIMAL(12,2) DEFAULT 0,
    line_total DECIMAL(12,2) DEFAULT 0,
    notes VARCHAR(500) DEFAULT '',
    PRIMARY KEY (id),
    KEY idx_pos_order_id (pos_order_id),
    KEY idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql2)) {
    echo "pos_order_lines: OK\n";
} else {
    echo "pos_order_lines ERROR: " . $conn->error . "\n";
}

$conn->close();
echo "Done!\n";
