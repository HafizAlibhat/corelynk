CREATE TABLE IF NOT EXISTS preparation_profiles (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  product_id INT(10) UNSIGNED NOT NULL,
  
ame VARCHAR(255) NOT NULL,
  description TEXT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY idx_preparation_profiles_product_id (product_id),
  KEY idx_preparation_profiles_product_active (product_id, is_active),
  CONSTRAINT k_preparation_profiles_product_id FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS preparation_components (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  profile_id INT(10) UNSIGNED NOT NULL,
  product_id INT(10) UNSIGNED NOT NULL,
  qty_per_unit DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
  is_optional TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_preparation_components_profile_id (profile_id),
  KEY idx_preparation_components_product_id (product_id),
  CONSTRAINT k_preparation_components_profile_id FOREIGN KEY (profile_id) REFERENCES preparation_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT k_preparation_components_product_id FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS preparation_steps (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  profile_id INT(10) UNSIGNED NOT NULL,
  step_order INT(11) NOT NULL,
  
ame VARCHAR(255) NOT NULL,
  description TEXT NULL,
  is_optional TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_preparation_steps_profile_id (profile_id),
  KEY idx_preparation_steps_profile_order (profile_id, step_order),
  CONSTRAINT k_preparation_steps_profile_id FOREIGN KEY (profile_id) REFERENCES preparation_profiles(id) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS step_execution_options (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  step_id INT(10) UNSIGNED NOT NULL,
  execution_type ENUM('vendor','inhouse') NOT NULL,
  endor_id INT(10) UNSIGNED NULL,
  
otes TEXT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_step_execution_options_step_id (step_id),
  KEY idx_step_execution_options_vendor_id (endor_id),
  KEY idx_step_execution_options_step_default (step_id, is_default),
  CONSTRAINT k_step_execution_options_step_id FOREIGN KEY (step_id) REFERENCES preparation_steps(id) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT k_step_execution_options_vendor_id FOREIGN KEY (endor_id) REFERENCES endors(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
