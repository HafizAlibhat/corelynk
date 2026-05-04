<?php
$connect = mysqli_connect('localhost', 'root', '', 'corelynk_db');
if (!$connect) {
    echo 'Connection failed: ' . mysqli_connect_error();
    exit;
}

echo "Creating tables...\n\n";

// Table 1: product_asset_groups
$sql1 = "CREATE TABLE IF NOT EXISTS `product_asset_groups` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned DEFAULT NULL,
  `variant_id` int(11) unsigned DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` text,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `variant_id` (`variant_id`),
  KEY `product_id_variant_id` (`product_id`,`variant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($connect, $sql1)) {
    echo "✅ product_asset_groups created\n";
} else {
    echo "❌ product_asset_groups: " . mysqli_error($connect) . "\n";
}

// Table 2: channels
$sql2 = "CREATE TABLE IF NOT EXISTS `channels` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  `width` int(11) DEFAULT NULL,
  `height` int(11) DEFAULT NULL,
  `max_file_size` int(11) DEFAULT NULL,
  `allowed_formats` text,
  `background_rule` varchar(20) DEFAULT 'any',
  `notes` text,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($connect, $sql2)) {
    echo "✅ channels created\n";
} else {
    echo "❌ channels: " . mysqli_error($connect) . "\n";
}

// Table 3: product_assets
$sql3 = "CREATE TABLE IF NOT EXISTS `product_assets` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `asset_group_id` int(11) unsigned NOT NULL,
  `channel_id` int(11) unsigned DEFAULT NULL,
  `type` varchar(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) unsigned NOT NULL,
  `mime_type` varchar(120) NOT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `tags` text,
  `uploaded_by` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `asset_group_id` (`asset_group_id`),
  KEY `channel_id` (`channel_id`),
  KEY `asset_group_id_channel_id_type` (`asset_group_id`,`channel_id`,`type`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($connect, $sql3)) {
    echo "✅ product_assets created\n";
} else {
    echo "❌ product_assets: " . mysqli_error($connect) . "\n";
}

// Table 4: product_asset_listings
$sql4 = "CREATE TABLE IF NOT EXISTS `product_asset_listings` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int(11) unsigned NOT NULL,
  `channel_id` int(11) unsigned NOT NULL,
  `listing_url` varchar(255) NOT NULL,
  `notes` text,
  `created_by` int(11) unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `channel_id` (`channel_id`),
  UNIQUE KEY `product_id_channel_id` (`product_id`,`channel_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

if (mysqli_query($connect, $sql4)) {
    echo "✅ product_asset_listings created\n";
} else {
    echo "❌ product_asset_listings: " . mysqli_error($connect) . "\n";
}

echo "\n✅ All tables created successfully!\n";
mysqli_close($connect);
