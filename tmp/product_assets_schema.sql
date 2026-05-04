CREATE TABLE IF NOT EXISTS product_asset_groups (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NULL,
  variant_id INT UNSIGNED NULL,
  name VARCHAR(150) NOT NULL,
  description TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pag_product_id (product_id),
  KEY idx_pag_variant_id (variant_id),
  KEY idx_pag_product_variant (product_id, variant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS channels (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  width INT NULL,
  height INT NULL,
  max_file_size INT NULL,
  allowed_formats TEXT NULL,
  background_rule VARCHAR(20) NOT NULL DEFAULT 'any',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_channels_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_assets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  asset_group_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NULL,
  type VARCHAR(20) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  thumbnail_path VARCHAR(255) NULL,
  file_name VARCHAR(255) NOT NULL,
  file_size BIGINT UNSIGNED NOT NULL,
  mime_type VARCHAR(120) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  tags TEXT NULL,
  uploaded_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pa_group (asset_group_id),
  KEY idx_pa_channel (channel_id),
  KEY idx_pa_group_channel_type (asset_group_id, channel_id, type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS product_asset_listings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  channel_id INT UNSIGNED NOT NULL,
  listing_url VARCHAR(255) NOT NULL,
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME NOT NULL,
  KEY idx_pal_product (product_id),
  KEY idx_pal_channel (channel_id),
  UNIQUE KEY uq_pal_product_channel (product_id, channel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
