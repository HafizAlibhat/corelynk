<?php
$pdo = new PDO('mysql:host=localhost;dbname=corelynk_db', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec("CREATE TABLE IF NOT EXISTS delivery_order_tracking_docs (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    delivery_order_id INT UNSIGNED NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    original_name VARCHAR(255) NOT NULL DEFAULT '',
    created_at    DATETIME DEFAULT NULL,
    INDEX idx_do (delivery_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

echo "delivery_order_tracking_docs table ready\n";
echo "Done!\n";
