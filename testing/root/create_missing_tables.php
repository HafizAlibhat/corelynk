<?php
$mysqli = new mysqli('127.0.0.1', 'root', '', 'production_management_system', 3306);
if ($mysqli->connect_errno) {
    echo 'CONNECT_ERR: ' . $mysqli->connect_error . PHP_EOL;
    exit(1);
}

echo "Creating process_categories table..." . PHP_EOL;
$sql = "CREATE TABLE IF NOT EXISTS `process_categories` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql)) {
    echo "✅ process_categories table created successfully" . PHP_EOL;
} else {
    echo "❌ Error creating process_categories: " . $mysqli->error . PHP_EOL;
}

echo "Creating product_processes table..." . PHP_EOL;
$sql = "CREATE TABLE IF NOT EXISTS `product_processes` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `product_id` int(10) unsigned NOT NULL,
    `process_id` int(10) unsigned NOT NULL,
    `sequence_order` int(11) DEFAULT 1,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($mysqli->query($sql)) {
    echo "✅ product_processes table created successfully" . PHP_EOL;
} else {
    echo "❌ Error creating product_processes: " . $mysqli->error . PHP_EOL;
}

echo "Inserting default process categories..." . PHP_EOL;
$categories = [
    ['Manufacturing', 'Manufacturing processes'],
    ['Assembly', 'Assembly processes'],
    ['Quality Control', 'Quality control processes'],
    ['Finishing', 'Finishing processes'],
    ['Packaging', 'Packaging processes'],
];

foreach ($categories as $category) {
    $name = $mysqli->real_escape_string($category[0]);
    $desc = $mysqli->real_escape_string($category[1]);
    $sql = "INSERT IGNORE INTO `process_categories` (`name`, `description`, `is_active`) VALUES ('$name', '$desc', 1)";
    if ($mysqli->query($sql)) {
        echo "✅ Added category: $name" . PHP_EOL;
    } else {
        echo "❌ Error adding category $name: " . $mysqli->error . PHP_EOL;
    }
}

echo PHP_EOL . "Final verification:" . PHP_EOL;
$res = $mysqli->query('SHOW TABLES LIKE "%process%"');
while ($row = $res->fetch_array()) {
    echo "- " . $row[0] . PHP_EOL;
}
