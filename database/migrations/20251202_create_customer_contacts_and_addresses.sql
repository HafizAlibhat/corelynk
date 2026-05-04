CREATE TABLE IF NOT EXISTS customers (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  customer_code VARCHAR(32) UNIQUE,
  name VARCHAR(255) NOT NULL,
  company_name VARCHAR(255),
  type VARCHAR(32) DEFAULT 'retail',
  status VARCHAR(32) DEFAULT 'active',
  created_by INT(11),
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Migration: Add customer_contacts and customer_addresses tables

CREATE TABLE IF NOT EXISTS customer_contacts (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  customer_id INT(11) NOT NULL,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255),
  phone VARCHAR(32),
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS customer_addresses (
  id INT(11) AUTO_INCREMENT PRIMARY KEY,
  customer_id INT(11) NOT NULL,
  address_line1 VARCHAR(255) NOT NULL,
  address_line2 VARCHAR(255),
  city VARCHAR(64),
  postal_code VARCHAR(16),
  country VARCHAR(64),
  type VARCHAR(32) DEFAULT 'shipping',
  FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);
